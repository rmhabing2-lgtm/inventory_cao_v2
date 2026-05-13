<?php
/**
 * user_data_change_helper.php
 * ===========================
 * Unified helper that wires together every layer of the notification stack
 * whenever a record that belongs to (or identifies) a specific system user
 * is mutated.
 *
 * Watched sources:
 *   • `user` table        — fields: first_name, last_name
 *   • `cao_employee` table — fields: end_user_id_number, FIRSTNAME, MIDDLENAME, LASTNAME
 *
 * Every call to processUserDataChange() will:
 *   1. Write a row to audit_logs   (via log_system_edit / log_audit)
 *   2. Write a row to system_logs  (via log_system)
 *   3. Resolve the affected person to a system `user`.id
 *   4. Send a USER_DATA_UPDATED notification to that user (in-app + WebSocket push)
 *   5. Optionally fan-out a copy of the notification to every active ADMIN
 *
 * -----------------------------------------------------------------------
 * QUICK USAGE
 * -----------------------------------------------------------------------
 *
 * // ① When something changes in the `user` table:
 * require_once __DIR__ . '/user_data_change_helper.php';
 * processUserDataChange(
 *     $conn,
 *     'user',                // $module  – name of the table being changed
 *     $userId,               // $recordId – PK of the row that changed
 *     $oldRow,               // $oldData  – associative array snapshot BEFORE update
 *     $newRow,               // $newData  – associative array snapshot AFTER update
 *     ['user_id' => $userId] // $employeeData – hint for resolving the target account
 *                            //   (pass 'user_id' directly when you already have it)
 * );
 *
 * // ② When something changes in the `cao_employee` table:
 * processUserDataChange(
 *     $conn,
 *     'cao_employee',
 *     $employeeRecordId,
 *     $oldRow,
 *     $newRow,
 *     [
 *         'end_user_id_number' => $row['end_user_id_number'],
 *         'first_name'  => $row['FIRSTNAME'],
 *         'last_name'   => $row['LASTNAME'],
 *     ]
 * );
 *
 * // ③ Convenience wrappers (same thing, pre-labelled):
 * notifyUserTableChange($conn, $recordId, $oldRow, $newRow);
 * notifyCaoEmployeeChange($conn, $recordId, $oldRow, $newRow);
 *
 * -----------------------------------------------------------------------
 * DEPENDENCIES (must be included before this file)
 * -----------------------------------------------------------------------
 *   require_once '.../audit_helper.php';        // log_system_edit()
 *   require_once '.../system_log_helper.php';   // log_system()
 *   require_once '.../NotificationTypes.php';   // NotificationTypes::USER_DATA_UPDATED
 *   require_once '.../NotificationHandler.php'; // NotificationHandler
 *   require_once '.../notify_push.php';         // push_notification_ws()  (auto-loaded by handler)
 */

// ── Guard: auto-load dependencies if not already loaded ──────────────────────
if (!function_exists('log_system_edit')) {
    $_auditPath = __DIR__ . '/audit_helper.php';
    if (file_exists($_auditPath)) require_once $_auditPath;
}

if (!function_exists('log_system')) {
    $sysLogPath = __DIR__ . '/system_log_helper.php';
    if (file_exists($sysLogPath)) require_once $sysLogPath;
}

if (!class_exists('NotificationTypes')) {
    $ntPath = __DIR__ . '/NotificationTypes.php';
    if (file_exists($ntPath)) require_once $ntPath;
}

if (!class_exists('NotificationHandler')) {
    $nhPath = __DIR__ . '/NotificationHandler.php';
    if (file_exists($nhPath)) require_once $nhPath;
}

// =============================================================================
// 1. USER RESOLVER
// =============================================================================
if (!function_exists('resolveUserIdFromData')) {
    /**
     * Resolves employee / profile data to a system `user`.id.
     *
     * Resolution order (first match wins):
     *   a) $employeeData['user_id']              — direct ID supplied by caller
     *   b) $employeeData['end_user_id_number']   — matches user.employee_id
     *   c) first_name + last_name                — exact name match in `user`
     *
     * @param  mysqli  $conn
     * @param  array   $employeeData  Hint data supplied by the caller.
     * @return int|false  System user.id or false when no match is found.
     */
    function resolveUserIdFromData(mysqli $conn, array $employeeData)
    {
        // a) Caller already knows the user_id — fastest path.
        if (!empty($employeeData['user_id']) && is_numeric($employeeData['user_id'])) {
            return (int)$employeeData['user_id'];
        }

        // b) Match via cao_employee.end_user_id_number → user.employee_id
        if (!empty($employeeData['end_user_id_number'])) {
            $stmt = $conn->prepare(
                "SELECT id FROM `user` WHERE employee_id = ? AND status = 'ACTIVE' LIMIT 1"
            );
            if ($stmt) {
                $stmt->bind_param('s', $employeeData['end_user_id_number']);
                $stmt->execute();
                $row = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                if ($row) return (int)$row['id'];
            }
        }

        // c) Match via exact first + last name.
        //    Accepts both 'first_name'/'last_name' keys (user table style)
        //    and 'FIRSTNAME'/'LASTNAME' keys (cao_employee table style).
        $first = $employeeData['first_name']  ?? $employeeData['FIRSTNAME']  ?? null;
        $last  = $employeeData['last_name']   ?? $employeeData['LASTNAME']   ?? null;

        if ($first !== null && $last !== null) {
            $stmt = $conn->prepare(
                "SELECT id FROM `user`
                  WHERE first_name = ? AND last_name = ? AND status = 'ACTIVE'
                  LIMIT 1"
            );
            if ($stmt) {
                $stmt->bind_param('ss', $first, $last);
                $stmt->execute();
                $row = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                if ($row) return (int)$row['id'];
            }
        }

        return false; // No matching system account found.
    }
}


// =============================================================================
// 2. CORE WRAPPER — processUserDataChange()
// =============================================================================
if (!function_exists('processUserDataChange')) {
    /**
     * One-stop handler: audit → system log → notification → WebSocket push.
     *
     * @param  mysqli   $conn
     * @param  string   $module        Table that changed: 'user' | 'cao_employee' | any
     * @param  int      $recordId      Primary key of the changed row
     * @param  array|null $oldData     Row snapshot BEFORE the change  (null = CREATE)
     * @param  array|null $newData     Row snapshot AFTER  the change  (null = DELETE)
     * @param  array    $employeeData  Hints for resolving the target user account
     *                                 Accepted keys: user_id, end_user_id_number,
     *                                                first_name / FIRSTNAME,
     *                                                last_name  / LASTNAME
     * @param  int|null  $actorUserId  ID of the admin/system that performed the change
     *                                 (defaults to $_SESSION['id'] when null)
     * @param  bool      $notifyAdmins Whether to fan-out a copy to all active ADMINs
     */
    function processUserDataChange(
        mysqli  $conn,
        string  $module,
        int     $recordId,
        ?array  $oldData,
        ?array  $newData,
        array   $employeeData  = [],
        ?int    $actorUserId   = null,
        bool    $notifyAdmins  = true
    ): void {
        // ── Actor resolution ────────────────────────────────────────────────
        if ($actorUserId === null) {
            $actorUserId = (int)($_SESSION['id'] ?? 0);
        }

        // ── 1. Audit log (field-level diff, skips if nothing changed) ───────
        if (function_exists('log_system_edit')) {
            log_system_edit($conn, $actorUserId, $module, $recordId, $oldData, $newData);
        }

        // ── 2. System log (action trail) ─────────────────────────────────────
        if (function_exists('log_system')) {
            $desc = sprintf(
                'Record updated in module [%s] | record_id=%d | actor_user_id=%d',
                $module, $recordId, $actorUserId
            );
            log_system($conn, $actorUserId, 'USER_DATA_UPDATED', $desc, [
                'module'    => $module,
                'record_id' => $recordId,
                'old'       => $oldData,
                'new'       => $newData,
            ]);
        }

        // ── 3. Resolve the affected person to a system account ───────────────
        $targetUserId = resolveUserIdFromData($conn, $employeeData);

        if (!$targetUserId) {
            // No linked system account: logging is done, nothing to notify.
            error_log("[user_data_change_helper] Could not resolve system user for module={$module} record_id={$recordId}");
            return;
        }

        // ── 4. Build notification payload ────────────────────────────────────
        $changedFields = _extractChangedFieldLabels($module, $oldData ?? [], $newData ?? []);
        $fieldList     = !empty($changedFields)
            ? implode(', ', $changedFields)
            : 'one or more fields';

        $messageBody = sprintf(
            'Changes were made to your records in the <strong>%s</strong> registry. '
          . 'Field(s) affected: %s.',
            ucfirst(str_replace('_', ' ', $module)),
            $fieldList
        );

        $metadata = [
            'module'         => $module,
            'record_id'      => $recordId,
            'changed_fields' => $changedFields,
            'old'            => $oldData,
            'new'            => $newData,
        ];

        // ── 5. Send to the affected user ─────────────────────────────────────
        if (class_exists('NotificationHandler') && class_exists('NotificationTypes')) {
            $handler = new NotificationHandler($conn, $actorUserId);
            $handler->send(
                NotificationTypes::USER_DATA_UPDATED,
                $targetUserId,
                $actorUserId,
                $recordId,
                $messageBody,
                $metadata
            );
        }

        // ── 6. Fan-out to all active ADMINs (optional) ───────────────────────
        if ($notifyAdmins) {
            _fanOutToAdmins(
                $conn,
                $actorUserId,
                $targetUserId,
                $recordId,
                $module,
                $messageBody,
                $metadata
            );
        }
    }
}


// =============================================================================
// 3. CONVENIENCE WRAPPERS
// =============================================================================

if (!function_exists('notifyUserTableChange')) {
    /**
     * Convenience wrapper for changes to the `user` table.
     *
     * Example:
     *   notifyUserTableChange($conn, $userId, $oldRow, $newRow);
     *
     * @param  mysqli   $conn
     * @param  int      $userId      PK of the row that changed (= the affected user)
     * @param  array    $oldRow      Row snapshot before UPDATE
     * @param  array    $newRow      Row snapshot after  UPDATE
     * @param  int|null $actorUserId Who performed the change (null = session user)
     */
    function notifyUserTableChange(
        mysqli $conn,
        int    $userId,
        array  $oldRow,
        array  $newRow,
        ?int   $actorUserId = null
    ): void {
        processUserDataChange(
            $conn,
            'user',
            $userId,
            $oldRow,
            $newRow,
            ['user_id' => $userId],  // fastest resolution path
            $actorUserId
        );
    }
}

if (!function_exists('notifyCaoEmployeeChange')) {
    /**
     * Convenience wrapper for changes to the `cao_employee` table.
     *
     * Example:
     *   notifyCaoEmployeeChange($conn, $empRecordId, $oldRow, $newRow);
     *
     * @param  mysqli   $conn
     * @param  int      $recordId    PK of the cao_employee row that changed
     * @param  array    $oldRow      Row snapshot before UPDATE
     * @param  array    $newRow      Row snapshot after  UPDATE
     * @param  int|null $actorUserId Who performed the change (null = session user)
     */
    function notifyCaoEmployeeChange(
        mysqli $conn,
        int    $recordId,
        array  $oldRow,
        array  $newRow,
        ?int   $actorUserId = null
    ): void {
        // Pull resolver hints from the post-change row (or pre-change if new is empty)
        $ref = !empty($newRow) ? $newRow : $oldRow;

        processUserDataChange(
            $conn,
            'cao_employee',
            $recordId,
            $oldRow,
            $newRow,
            [
                'end_user_id_number' => $ref['end_user_id_number'] ?? null,
                'first_name'         => $ref['FIRSTNAME']           ?? null,
                'last_name'          => $ref['LASTNAME']            ?? null,
            ],
            $actorUserId
        );
    }
}


// =============================================================================
// 4. PRIVATE HELPERS (internal use only, prefixed with _)
// =============================================================================

if (!function_exists('_extractChangedFieldLabels')) {
    /**
     * Returns a human-readable list of fields that actually changed,
     * filtered to only the watched/sensitive fields for each module.
     *
     * @param  string $module
     * @param  array  $old
     * @param  array  $new
     * @return string[]
     */
    function _extractChangedFieldLabels(string $module, array $old, array $new): array
    {
        // Define which fields are "interesting" per module.
        $watchedFields = [
            'user' => [
                'first_name' => 'First Name',
                'last_name'  => 'Last Name',
                'email'      => 'Email',
                'role'       => 'Role',
                'status'     => 'Status',
                'avatar'     => 'Profile Photo',
            ],
            'cao_employee' => [
                'end_user_id_number' => 'Employee ID Number',
                'FIRSTNAME'          => 'First Name',
                'MIDDLENAME'         => 'Middle Name',
                'LASTNAME'           => 'Last Name',
                'POSITION'           => 'Position',
                'DEPARTMENT'         => 'Department',
                'STATUS'             => 'Status',
            ],
        ];

        $fields    = $watchedFields[$module] ?? [];
        $changed   = [];

        foreach ($fields as $key => $label) {
            $oldVal = $old[$key] ?? null;
            $newVal = $new[$key] ?? null;
            // Use loose comparison intentionally (matches "1" == 1 etc.)
            if ($oldVal != $newVal) {
                $changed[] = $label;
            }
        }

        // Fallback: if no watched fields matched but something did change,
        // report the raw keys so nothing is silently swallowed.
        if (empty($changed)) {
            foreach ($new as $key => $newVal) {
                $oldVal = $old[$key] ?? null;
                if ($oldVal != $newVal) {
                    $changed[] = $key;
                }
            }
        }

        return array_values(array_unique($changed));
    }
}

if (!function_exists('_fanOutToAdmins')) {
    /**
     * Sends a copy of the USER_DATA_UPDATED notification to every active ADMIN,
     * skipping the actor (they already know) and the affected user
     * (they received their own copy in step 5 above).
     *
     * Uses the existing notify_roles() helper from system_log_helper.php when
     * available, otherwise falls back to a local fan-out loop.
     */
    function _fanOutToAdmins(
        mysqli $conn,
        int    $actorUserId,
        int    $targetUserId,
        int    $recordId,
        string $module,
        string $messageBody,
        array  $metadata
    ): void {
        if (!class_exists('NotificationHandler') || !class_exists('NotificationTypes')) {
            return;
        }

        // Use the helper from system_log_helper.php when available.
        if (function_exists('notify_roles')) {
            // notify_roles fans out to all users with the given roles,
            // excluding $excludeUser (we exclude the actor so admins who
            // triggered the change don't see a redundant notification).
            notify_roles(
                $conn,
                ['ADMIN'],
                $actorUserId,
                NotificationTypes::USER_DATA_UPDATED,
                $recordId,
                array_merge($metadata, ['message' => $messageBody]),
                $actorUserId // exclude the actor themselves
            );
            return;
        }

        // Fallback: manual loop
        $stmt = $conn->prepare(
            "SELECT id FROM `user` WHERE role = 'ADMIN' AND status = 'ACTIVE'
              AND id != ? AND id != ?"
        );
        if (!$stmt) return;

        $stmt->bind_param('ii', $actorUserId, $targetUserId);
        $stmt->execute();
        $adminRows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        $handler = new NotificationHandler($conn, $actorUserId);
        foreach ($adminRows as $admin) {
            $handler->send(
                NotificationTypes::USER_DATA_UPDATED,
                (int)$admin['id'],
                $actorUserId,
                $recordId,
                $messageBody,
                $metadata
            );
        }
    }
}