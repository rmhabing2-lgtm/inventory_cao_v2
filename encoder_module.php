<?php

/**
 * ENCODER MODULE
 * ─────────────────────────────────────────────────────────────────────────────
 * Purpose  : Dedicated data-entry interface for users with the ENCODER role.
 * Access   : ENCODER role only. Redirects any other role back to their landing.
 * Logging  : Every INSERT / UPDATE is recorded in audit_logs via log_audit()
 *            and system_logs via log_system() (system_log_helper.php).
 * Tables   : inventory_items · borrowed_items · accountable_items
 */

// Start session manually — do NOT include session.php here because
// session.php may auto-call require_login() which blocks ENCODER and
// causes an infinite redirect loop.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Bootstrap path helper (site_url) without triggering require_login()
$ROOT_DIR = realpath(__DIR__);
if (!function_exists('site_url')) {
    $detected_base = '';
    try {
        $docroot = isset($_SERVER['DOCUMENT_ROOT'])
            ? str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT'])) : '';
        $root = str_replace('\\', '/', $ROOT_DIR);
        if ($docroot && $root && strpos($root, $docroot) === 0) {
            $detected_base = substr($root, strlen($docroot));
            $detected_base = '/' . trim($detected_base, '/');
        }
    } catch (Exception $e) {
    }
    if (empty($detected_base)) $detected_base = '/inventory_cao_v2';
    function site_url($path = '')
    {
        global $detected_base;
        return rtrim($detected_base, '/') . '/' . ltrim((string)$path, '/');
    }
}

require_once __DIR__ . '/login/config.php';          // provides $conn
require_once __DIR__ . '/includes/system_log_helper.php';

/* ── 1. ROLE ENFORCEMENT ──────────────────────────────────────────────────── */

// Not logged in → send to login page
if (empty($_SESSION['id']) || empty($_SESSION['role'])) {
    header('Location: ' . site_url('login/login.php'));
    exit;
}

$current_role = strtoupper(trim($_SESSION['role']));

// Any role OTHER than ENCODER does not belong here → send to main dashboard
// (index.php uses require_login() which handles its own role checks)
if ($current_role !== 'ENCODER') {
    header('Location: ' . site_url('index.php'));
    exit;
}

/* ── 2. SESSION IDENTITY ──────────────────────────────────────────────────── */
$encoder_id   = (int)($_SESSION['id'] ?? 0);
$encoder_name = $_SESSION['full_name'] ?? ($_SESSION['username'] ?? 'Encoder');

/* ── 3. CSRF ──────────────────────────────────────────────────────────────── */
$csrf_token = $_SESSION['csrf_token'] ?? '';

/* ── 4. HANDLE POST ACTIONS ───────────────────────────────────────────────── */
// $feedback = ['type' => '', 'message' => ''];

// if ($_SERVER['REQUEST_METHOD'] === 'POST') {

//     // Validate CSRF
//     $posted_csrf = $_POST['csrf_token'] ?? '';
//     if (!hash_equals($csrf_token, $posted_csrf)) {
//         $feedback = ['type' => 'danger', 'message' => 'Security token mismatch. Please refresh and try again.'];
//         goto render;
//     }

//     $action = $_POST['action'] ?? '';

/* ── AJAX: Check serial numbers for duplicates ────────────────────────────── *
 * Called via GET ?enc_action=check_serial_dupe&serials=abc / def / ghi       *
 * Returns JSON { duplicates: ['def', 'ghi'], sources: { 'def': 'inv#12 …' } }
 *                                                                             *
 * HOW IT WORKS                                                                *
 *  The serial_number columns in inventory_items and accountable_items store   *
 *  "/" delimited strings (e.g. "SN001 / SN002 / SN003").  A simple SQL       *
 *  IN(?) on the whole field would compare the entire string against a single  *
 *  token and never match.                                                     *
 *                                                                             *
 *  Instead we:                                                                *
 *   1. Fetch every non-empty serial_number row from inventory_items AND       *
 *      accountable_items (non-deleted).                                       *
 *   2. Split each row's value by "/" to get individual serial tokens.         *
 *   3. Build a lowercase lookup map: token → "source description".            *
 *   4. Check each submitted token against the map (case-insensitive).         *
 */
if (isset($_GET['enc_action']) && $_GET['enc_action'] === 'check_serial_dupe') {
    header('Content-Type: application/json; charset=utf-8');

    $raw    = trim($_GET['serials'] ?? '');
    $tokens = array_values(array_filter(array_map('trim', explode('/', $raw))));
    $dupes   = [];
    $sources = [];   // token → human-readable source ("inventory_items #12 — Desktop Computer")

    if (!empty($tokens)) {

        /* ── Step 1: fetch all slash-delimited serial_number rows ─────────── */
        $db_map = [];   // lowercase(serial_token) → source label

        // From inventory_items
        $res1 = $conn->query(
            "SELECT id, item_name, serial_number
             FROM inventory_items
             WHERE serial_number IS NOT NULL AND serial_number <> ''"
        );
        if ($res1) {
            while ($row = $res1->fetch_assoc()) {
                $parts = array_filter(array_map('trim', explode('/', $row['serial_number'])));
                $label = 'Inventory Item #' . $row['id'] . ' — ' . $row['item_name'];
                foreach ($parts as $sn) {
                    if ($sn !== '') {
                        $db_map[strtolower($sn)] = $label;
                    }
                }
            }
        }

        // From accountable_items (skip soft-deleted records)
        $res2 = $conn->query(
            "SELECT ai.id, ai.person_name, ai.serial_number, ii.item_name
             FROM accountable_items ai
             LEFT JOIN inventory_items ii ON ii.id = ai.inventory_item_id
             WHERE ai.serial_number IS NOT NULL AND ai.serial_number <> ''
               AND ai.is_deleted = 0"
        );
        if ($res2) {
            while ($row = $res2->fetch_assoc()) {
                $parts = array_filter(array_map('trim', explode('/', $row['serial_number'])));
                $label = 'Accountable Item #' . $row['id']
                    . ' (' . ($row['item_name'] ?? '?') . ')'
                    . ' → ' . $row['person_name'];
                foreach ($parts as $sn) {
                    if ($sn !== '') {
                        // Prefer inventory_items source if already set; append accountable info
                        $key = strtolower($sn);
                        if (!isset($db_map[$key])) {
                            $db_map[$key] = $label;
                        }
                    }
                }
            }
        }

        /* ── Step 2: compare submitted tokens against the map ─────────────── */
        foreach ($tokens as $tok) {
            if ($tok === '') continue;
            $key = strtolower($tok);
            if (isset($db_map[$key])) {
                $dupes[]         = $tok;
                $sources[$tok]   = $db_map[$key];
            }
        }
    }

    echo json_encode([
        'duplicates' => array_values($dupes),
        'sources'    => $sources,   // keyed by the original (case-preserved) token
    ]);
    exit;
}

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Sat, 01 Jan 2000 00:00:00 GMT");

$feedback = ['type' => '', 'message' => ''];

if (isset($_SESSION['feedback'])) {
    $feedback = $_SESSION['feedback'];
    unset($_SESSION['feedback']);
}

// ── Generate a fresh one-time form token on every GET (PRG anti-double-submit) ──
// The token is embedded in every form. On POST we validate it and rotate it
// immediately so any browser refresh / back+submit is permanently blocked.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['form_token'] = bin2hex(random_bytes(32));
}
$form_token_value = $_SESSION['form_token'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $posted_form_token  = $_POST['form_token'] ?? '';
    $session_form_token = $_SESSION['form_token'] ?? '';

    // Refresh / back-button guard: the submitted token must match the one issued
    // on the last GET. If it does not match (already consumed or missing), the
    // request is a duplicate — redirect without touching the database.
    if (
        empty($posted_form_token) || empty($session_form_token)
        || !hash_equals($session_form_token, $posted_form_token)
    ) {
        $_SESSION['feedback'] = [
            'type'    => 'warning',
            'message' => 'Duplicate submission detected — your previous entry was already saved. Please verify the records before submitting again.'
        ];
        header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
        exit;
    }

    // Rotate the token immediately so any subsequent refresh or back+submit is blocked
    unset($_SESSION['form_token']);

    $posted_csrf = $_POST['csrf_token'] ?? '';
    if (!hash_equals($csrf_token, $posted_csrf)) {
        $_SESSION['feedback'] = [
            'type'    => 'danger',
            'message' => 'Security token mismatch. Please refresh and try again.'
        ];
        header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
        exit;
    }

    $action = $_POST['action'] ?? '';





    /* ── 4A. ADD INVENTORY ITEM ─────────────────────────────────────────── */
    if ($action === 'add_inventory_item') {
        $item_name       = trim($_POST['item_name'] ?? '');
        $particulars     = trim($_POST['particulars'] ?? '');
        $are_mr_ics_num  = trim($_POST['are_mr_ics_num'] ?? '');
        $property_number = trim($_POST['property_number'] ?? '');
        $serial_number   = trim($_POST['serial_number'] ?? '');
        $quantity        = (int)($_POST['quantity'] ?? 0);
        $value_amount    = (float)($_POST['value_amount'] ?? 0);
        $total_amount    = $value_amount * $quantity;
        $date_delivered  = trim($_POST['date_delivered'] ?? '');
        $item_status     = trim($_POST['item_status'] ?? 'Active');

        $allowed_statuses = ['Active', 'Inactive', 'Returned', 'Defective', 'Replaced'];
        if (!in_array($item_status, $allowed_statuses, true)) $item_status = 'Active';

        if (empty($item_name) || empty($particulars) || $quantity <= 0 || empty($date_delivered)) {
            $_SESSION['feedback'] = ['type' => 'danger', 'message' => 'Please fill in all required fields (Item Name, Particulars, Quantity, Date Delivered).'];
            header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
            exit;
        }

        /* ── Helper: split "/" delimited serial/property/ARE strings ─── */
        $splitTok = fn(string $s): array => array_values(array_filter(array_map('trim', explode('/', $s))));

        /* ── MERGE CHECK: same item_name + particulars + date_delivered ──── */
        $merge_row = null;
        $chk = $conn->prepare(
            "SELECT id, quantity, serial_number, are_mr_ics_num, property_number,
                    value_amount, total_amount
             FROM inventory_items
             WHERE item_name = ? AND particulars = ? AND date_delivered = ?
             LIMIT 1"
        );
        if ($chk) {
            $chk->bind_param('sss', $item_name, $particulars, $date_delivered);
            $chk->execute();
            $merge_row = $chk->get_result()->fetch_assoc();
            $chk->close();
        }

        if ($merge_row) {
            /* ════════════════════════════════════════════════════════════
             * MERGE PATH — same item / particulars / date_delivered
             * Check for serial duplicates first; reject if found.
             * ════════════════════════════════════════════════════════════ */
            $merge_id          = (int)$merge_row['id'];
            $existing_serials  = $splitTok($merge_row['serial_number'] ?? '');
            $incoming_serials  = $splitTok($serial_number);

            $dupes = array_intersect(
                array_map('strtolower', $incoming_serials),
                array_map('strtolower', $existing_serials)
            );
            if (!empty($dupes)) {
                $dupe_list = implode(', ', array_values($dupes));
                $_SESSION['feedback'] = [
                    'type'    => 'danger',
                    'message' => "⚠ Duplicate serial number(s) detected for existing Inventory Item #<strong>{$merge_id}</strong> "
                        . "(<em>" . htmlspecialchars($particulars) . "</em>): <code>{$dupe_list}</code>. "
                        . "Please remove duplicates before saving.",
                ];
                header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
                exit;
            }

            /* ── Snapshot old data for audit ─────────────────────────── */
            $old_data_merge = [
                'id'             => $merge_id,
                'quantity'       => (int)$merge_row['quantity'],
                'serial_number'  => $merge_row['serial_number']   ?? '',
                'are_mr_ics_num' => $merge_row['are_mr_ics_num']  ?? '',
                'property_number' => $merge_row['property_number'] ?? '',
                'value_amount'   => (float)$merge_row['value_amount'],
                'total_amount'   => (float)$merge_row['total_amount'],
            ];

            /* ── Build merged values ──────────────────────────────────── */
            $merged_serial   = implode(' / ', array_merge($existing_serials, $incoming_serials));
            $merged_are      = implode(' / ', array_merge($splitTok($merge_row['are_mr_ics_num'] ?? ''), $splitTok($are_mr_ics_num)));
            $merged_prop     = implode(' / ', array_merge($splitTok($merge_row['property_number'] ?? ''), $splitTok($property_number)));
            $merged_qty      = (int)$merge_row['quantity'] + $quantity;
            $merged_value    = $value_amount ?: (float)$merge_row['value_amount'];  // keep existing if new is 0
            $merged_total    = $merged_value * $merged_qty;

            $upd_merge = $conn->prepare(
                "UPDATE inventory_items
                    SET quantity        = ?,
                        serial_number   = ?,
                        are_mr_ics_num  = ?,
                        property_number = ?,
                        value_amount    = ?,
                        total_amount    = ?,
                        item_status     = ?,
                        date_updated    = NOW()
                  WHERE id = ?"
            );
            if ($upd_merge) {
                $upd_merge->bind_param(
                    'isssddsi',
                    $merged_qty,
                    $merged_serial,
                    $merged_are,
                    $merged_prop,
                    $merged_value,
                    $merged_total,
                    $item_status,
                    $merge_id
                );
                $upd_merge->execute();
                $upd_merge->close();
            }

            /* ── Audit, transaction, system log for MERGE ────────────── */
            $new_data_merge = [
                'id'             => $merge_id,
                'quantity'       => $merged_qty,
                'serial_number'  => $merged_serial,
                'are_mr_ics_num' => $merged_are,
                'property_number' => $merged_prop,
                'value_amount'   => $merged_value,
                'total_amount'   => $merged_total,
                'item_status'    => $item_status,
                'note'           => "Merged +{$quantity} unit(s) from encoder '{$encoder_name}'",
            ];
            log_audit($conn, $encoder_id, 'inventory_items', 'UPDATE', $merge_id, $old_data_merge, $new_data_merge);

            $txn_ref_merge  = 'IN-MERGE-' . date('Ymd') . '-' . $merge_id;
            $txn_rem_merge  = "Merged +{$quantity} unit(s) into Item #{$merge_id} ({$item_name} — {$particulars})."
                . ($serial_number   ? " Added Serial(s): {$serial_number}."       : '')
                . ($are_mr_ics_num  ? " Added PAR/ICS: {$are_mr_ics_num}."        : '')
                . ($property_number ? " Added Prop#: {$property_number}."         : '')
                . " By: {$encoder_name}.";
            $txn_stmt_m = $conn->prepare(
                "INSERT INTO inventory_transactions
                    (inventory_item_id, performed_by_id, performed_by_name,
                     transaction_type, quantity, reference_no, remarks)
                 VALUES (?, ?, ?, 'IN', ?, ?, ?)"
            );
            if ($txn_stmt_m) {
                $txn_stmt_m->bind_param('iisiss', $merge_id, $encoder_id, $encoder_name, $quantity, $txn_ref_merge, $txn_rem_merge);
                $txn_stmt_m->execute();
                $txn_stmt_m->close();
            }

            log_system(
                $conn,
                $encoder_id,
                'INVENTORY_ITEM_MERGED',
                "Encoder '{$encoder_name}' merged +{$quantity} unit(s) into Inventory Item #{$merge_id} ('{$item_name}' — '{$particulars}', delivered {$date_delivered}). New qty: {$merged_qty}.",
                ['item_id' => $merge_id, 'added_qty' => $quantity, 'new_qty' => $merged_qty, 'serial_number' => $serial_number, 'are_mr_ics_num' => $are_mr_ics_num]
            );

            $_SESSION['feedback'] = ['type' => 'success', 'message' => "✓ Merged +<strong>{$quantity}</strong> unit(s) into existing Inventory Item #<strong>{$merge_id}</strong> (<em>" . htmlspecialchars($particulars) . "</em>). New total quantity: <strong>{$merged_qty}</strong>."];
        } else {
            /* ════════════════════════════════════════════════════════════
             * INSERT PATH — new item_name / particulars / date_delivered
             * ════════════════════════════════════════════════════════════ */
            $stmt = $conn->prepare(
                "INSERT INTO inventory_items
                    (item_name, particulars, are_mr_ics_num, property_number, serial_number, quantity, amount, value_amount, total_amount, date_delivered, item_status)
                 VALUES (?, ?, ?, ?, ?, ?, '', ?, ?, ?, ?)"
            );
            if ($stmt) {
                $stmt->bind_param(
                    'sssssiidss',
                    $item_name,
                    $particulars,
                    $are_mr_ics_num,
                    $property_number,
                    $serial_number,
                    $quantity,
                    $value_amount,
                    $total_amount,
                    $date_delivered,
                    $item_status
                );
                $stmt->execute();
                $new_id = (int)$conn->insert_id;
                $stmt->close();

                $new_data = compact(
                    'item_name',
                    'particulars',
                    'are_mr_ics_num',
                    'property_number',
                    'serial_number',
                    'quantity',
                    'value_amount',
                    'total_amount',
                    'date_delivered',
                    'item_status'
                );
                log_audit($conn, $encoder_id, 'inventory_items', 'CREATE', $new_id, null, $new_data);

                $txn_ref_new  = 'IN-NEW-' . date('Ymd') . '-' . $new_id;
                $txn_rem_new  = "New inventory item #{$new_id} created: {$item_name} — {$particulars}. Qty: {$quantity}."
                    . ($serial_number   ? " Serial(s): {$serial_number}."      : '')
                    . ($are_mr_ics_num  ? " PAR/ICS: {$are_mr_ics_num}."       : '')
                    . ($property_number ? " Prop#: {$property_number}."        : '')
                    . " By: {$encoder_name}.";
                $txn_stmt_n = $conn->prepare(
                    "INSERT INTO inventory_transactions
                        (inventory_item_id, performed_by_id, performed_by_name,
                         transaction_type, quantity, reference_no, remarks)
                     VALUES (?, ?, ?, 'IN', ?, ?, ?)"
                );
                if ($txn_stmt_n) {
                    $txn_stmt_n->bind_param('iisiss', $new_id, $encoder_id, $encoder_name, $quantity, $txn_ref_new, $txn_rem_new);
                    $txn_stmt_n->execute();
                    $txn_stmt_n->close();
                }

                log_system(
                    $conn,
                    $encoder_id,
                    'INVENTORY_ITEM_CREATED',
                    "Encoder '{$encoder_name}' added inventory item '{$item_name}' (ID:{$new_id}). | {\"item_id\":{$new_id},\"quantity\":{$quantity}}",
                    ['item_id' => $new_id, 'quantity' => $quantity]
                );

                // Sync serial_number → accountable_items with no serial yet
                if (!empty($serial_number)) {
                    $sync = $conn->prepare("UPDATE accountable_items SET serial_number = ? WHERE inventory_item_id = ? AND is_deleted = 0 AND (serial_number IS NULL OR serial_number = '')");
                    if ($sync) {
                        $sync->bind_param('si', $serial_number, $new_id);
                        $sync->execute();
                        $sync->close();
                    }
                }
                // Sync property_number → accountable_items with no property number yet
                if (!empty($property_number)) {
                    $sync_pn = $conn->prepare("UPDATE accountable_items SET property_number = ? WHERE inventory_item_id = ? AND is_deleted = 0 AND (property_number IS NULL OR property_number = '')");
                    if ($sync_pn) {
                        $sync_pn->bind_param('si', $property_number, $new_id);
                        $sync_pn->execute();
                        $sync_pn->close();
                    }
                }
                // Sync are_mr_ics_num → accountable_items with no ARE/PAR/ICS yet
                if (!empty($are_mr_ics_num)) {
                    $sync_are = $conn->prepare("UPDATE accountable_items SET are_mr_ics_num = ? WHERE inventory_item_id = ? AND is_deleted = 0 AND (are_mr_ics_num IS NULL OR are_mr_ics_num = '')");
                    if ($sync_are) {
                        $sync_are->bind_param('si', $are_mr_ics_num, $new_id);
                        $sync_are->execute();
                        $sync_are->close();
                    }
                }

                $_SESSION['feedback'] = ['type' => 'success', 'message' => "✓ Inventory item <strong>" . htmlspecialchars($item_name) . "</strong> added successfully (ID: {$new_id})."];
            } else {
                $_SESSION['feedback'] = ['type' => 'danger', 'message' => 'Database error: ' . htmlspecialchars($conn->error)];
            }
        }
    }

    /* ── 4A-2. UPDATE INVENTORY ITEM (inline table edit) ────────────────── */ elseif ($action === 'update_inventory_item') {
        $upd_id          = (int)($_POST['inv_id']         ?? 0);
        $upd_item_name   = trim($_POST['item_name']       ?? '');
        $upd_particulars = trim($_POST['particulars']     ?? '');
        $upd_are         = trim($_POST['are_mr_ics_num']  ?? '');
        $upd_serial      = trim($_POST['serial_number']   ?? '');
        $upd_prop        = trim($_POST['property_number'] ?? '');
        $upd_qty         = (int)($_POST['quantity']        ?? 0);
        $upd_value       = (float)($_POST['value_amount']  ?? 0);
        $upd_total       = $upd_value * $upd_qty;
        $upd_delivered   = trim($_POST['date_delivered']  ?? '');
        $upd_status      = trim($_POST['item_status']     ?? 'Active');

        $allowed_upd_statuses = ['Active', 'Inactive', 'Returned', 'Defective', 'Replaced'];
        if (!in_array($upd_status, $allowed_upd_statuses, true)) $upd_status = 'Active';

        if ($upd_id <= 0 || empty($upd_item_name) || empty($upd_particulars) || $upd_qty < 0 || empty($upd_delivered)) {
            $_SESSION['feedback'] = ['type' => 'danger', 'message' => 'Update failed: missing required fields.'];
            header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
            exit;
        }

        /* ── Fetch old data for audit ─────────────────────────────────── */
        $old_row = null;
        $old_stmt = $conn->prepare("SELECT * FROM inventory_items WHERE id = ? LIMIT 1");
        if ($old_stmt) {
            $old_stmt->bind_param('i', $upd_id);
            $old_stmt->execute();
            $old_row = $old_stmt->get_result()->fetch_assoc();
            $old_stmt->close();
        }
        if (!$old_row) {
            $_SESSION['feedback'] = ['type' => 'danger', 'message' => "Update failed: Inventory Item #{$upd_id} not found."];
            header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
            exit;
        }

        /* ── Build old_data / new_data snapshots ──────────────────────── */
        $old_snap = [
            'item_name'       => $old_row['item_name'],
            'particulars'     => $old_row['particulars'],
            'are_mr_ics_num'  => $old_row['are_mr_ics_num'],
            'serial_number'   => $old_row['serial_number'],
            'property_number' => $old_row['property_number'],
            'quantity'        => (int)$old_row['quantity'],
            'value_amount'    => (float)$old_row['value_amount'],
            'total_amount'    => (float)$old_row['total_amount'],
            'date_delivered'  => $old_row['date_delivered'],
            'item_status'     => $old_row['item_status'],
        ];

        /* ── Determine transaction type by qty delta ──────────────────── */
        $qty_delta   = $upd_qty - (int)$old_row['quantity'];
        $txn_type    = $qty_delta > 0 ? 'IN' : ($qty_delta < 0 ? 'OUT' : 'ADJUSTMENT');
        $txn_qty     = abs($qty_delta) ?: 1;

        /* ── Perform the UPDATE ───────────────────────────────────────── */
        $upd_stmt = $conn->prepare(
            "UPDATE inventory_items
                SET item_name       = ?,
                    particulars     = ?,
                    are_mr_ics_num  = ?,
                    serial_number   = ?,
                    property_number = ?,
                    quantity        = ?,
                    value_amount    = ?,
                    total_amount    = ?,
                    date_delivered  = ?,
                    item_status     = ?,
                    date_updated    = NOW()
              WHERE id = ?"
        );
        if (!$upd_stmt) {
            $_SESSION['feedback'] = ['type' => 'danger', 'message' => 'Database error: ' . htmlspecialchars($conn->error)];
            header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
            exit;
        }
        // $upd_stmt->bind_param('sssssiidss i',
        //     $upd_item_name, $upd_particulars, $upd_are, $upd_serial, $upd_prop,
        //     $upd_qty, $upd_value, $upd_total, $upd_delivered, $upd_status, $upd_id
        // );
       
        $upd_stmt->bind_param(
            'sssssiddssi',    
            $upd_item_name,
            $upd_particulars,
            $upd_are,
            $upd_serial,
            $upd_prop,
            $upd_qty,
            $upd_value,
            $upd_total,
            $upd_delivered,
            $upd_status,
            $upd_id
        );
        $upd_stmt->execute();
        $upd_stmt->close();

        $new_snap = [
            'item_name'       => $upd_item_name,
            'particulars'     => $upd_particulars,
            'are_mr_ics_num'  => $upd_are,
            'serial_number'   => $upd_serial,
            'property_number' => $upd_prop,
            'quantity'        => $upd_qty,
            'value_amount'    => $upd_value,
            'total_amount'    => $upd_total,
            'date_delivered'  => $upd_delivered,
            'item_status'     => $upd_status,
            'updated_by'      => $encoder_name,
        ];

        /* ── Audit, transaction, system log for UPDATE ────────────────── */
        log_audit($conn, $encoder_id, 'inventory_items', 'UPDATE', $upd_id, $old_snap, $new_snap);

        $txn_ref_upd  = 'UPD-' . date('Ymd') . '-' . $upd_id;
        $txn_rem_upd  = "Item #{$upd_id} ({$upd_item_name}) updated by encoder '{$encoder_name}'."
            . ($qty_delta !== 0 ? " Qty changed from {$old_row['quantity']} to {$upd_qty} (delta: " . ($qty_delta > 0 ? "+{$qty_delta}" : "{$qty_delta}") . ")." : " Qty unchanged ({$upd_qty}).")
            . " Status: {$upd_status}.";
        $txn_stmt_u = $conn->prepare(
            "INSERT INTO inventory_transactions
                (inventory_item_id, performed_by_id, performed_by_name,
                 transaction_type, quantity, reference_no, remarks)
             VALUES (?, ?, ?, ?, ?, ?, ?)"
        );
        if ($txn_stmt_u) {
            $txn_stmt_u->bind_param('iississ', $upd_id, $encoder_id, $encoder_name, $txn_type, $txn_qty, $txn_ref_upd, $txn_rem_upd);
            $txn_stmt_u->execute();
            $txn_stmt_u->close();
        }

        log_system(
            $conn,
            $encoder_id,
            'INVENTORY_ITEM_UPDATED',
            "Encoder '{$encoder_name}' updated Inventory Item #{$upd_id} ('{$upd_item_name}'). Qty: {$old_row['quantity']} → {$upd_qty}. Status: {$old_row['item_status']} → {$upd_status}.",
            ['item_id' => $upd_id, 'qty_before' => (int)$old_row['quantity'], 'qty_after' => $upd_qty, 'status_before' => $old_row['item_status'], 'status_after' => $upd_status]
        );

        $_SESSION['feedback'] = ['type' => 'success', 'message' => "✓ Inventory Item #<strong>{$upd_id}</strong> (<em>" . htmlspecialchars($upd_item_name) . "</em>) updated successfully."];
    }

    /* ── 4B. ADD BORROWED ITEM ──────────────────────────────────────────── */ elseif ($action === 'add_borrowed_item') {
        $accountable_id      = (int)($_POST['accountable_id'] ?? 0);
        $inventory_item_id   = (int)($_POST['inventory_item_id'] ?? 0);
        $from_person         = trim($_POST['from_person'] ?? '');
        $to_person           = trim($_POST['to_person'] ?? '');
        $borrower_employee_id = (int)($_POST['borrower_employee_id'] ?? 0) ?: null;
        $quantity            = (int)($_POST['quantity'] ?? 0);
        $borrow_date         = trim($_POST['borrow_date'] ?? date('Y-m-d H:i:s'));
        $return_date         = trim($_POST['return_date'] ?? '') ?: null;
        $are_mr_ics_num      = trim($_POST['are_mr_ics_num'] ?? '') ?: null;
        $property_number     = trim($_POST['property_number'] ?? '') ?: null;
        $serial_number       = trim($_POST['serial_number'] ?? '') ?: null;
        $po_number           = trim($_POST['po_number'] ?? '') ?: null;
        $account_code        = trim($_POST['account_code'] ?? '') ?: null;
        $old_account_code    = trim($_POST['old_account_code'] ?? '') ?: null;
        $reference_no        = trim($_POST['reference_no'] ?? '') ?: ('BRW-' . date('YmdHis') . '-' . sprintf('%04d', $encoder_id));
        $remarks             = trim($_POST['remarks'] ?? '') ?: null;
        $status              = trim($_POST['status'] ?? 'PENDING');

        $allowed_statuses = ['PENDING', 'APPROVED', 'DENIED', 'CANCELLED', 'RETURN_PENDING', 'RETURNED'];
        if (!in_array($status, $allowed_statuses, true)) $status = 'PENDING';

        if ($inventory_item_id <= 0 || empty($from_person) || empty($to_person) || $quantity <= 0) {
            $_SESSION['feedback'] = ['type' => 'danger', 'message' => 'Required fields: Inventory Item, From Person, To Person, Quantity.'];
            header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
            exit;
        }

        $stmt = $conn->prepare(
            "INSERT INTO borrowed_items
                (accountable_id, inventory_item_id, from_person, to_person, borrower_employee_id,
                 quantity, borrow_date, return_date, are_mr_ics_num, property_number,
                 serial_number, po_number, account_code, old_account_code, reference_no,
                 remarks, status, requested_by, requested_at)
             VALUES (?,?,?,?,?, ?,?,?,?,?, ?,?,?,?,?, ?,?,?,NOW())"
        );
        if ($stmt) {
            $stmt->bind_param(
                'iissiisssssssssssi',
                $accountable_id,
                $inventory_item_id,
                $from_person,
                $to_person,
                $borrower_employee_id,
                $quantity,
                $borrow_date,
                $return_date,
                $are_mr_ics_num,
                $property_number,
                $serial_number,
                $po_number,
                $account_code,
                $old_account_code,
                $reference_no,
                $remarks,
                $status,
                $encoder_id
            );
            $stmt->execute();
            $new_id = (int)$conn->insert_id;
            $stmt->close();

            $new_data = [
                'accountable_id'      => $accountable_id,
                'inventory_item_id'   => $inventory_item_id,
                'from_person'         => $from_person,
                'to_person'           => $to_person,
                'quantity'            => $quantity,
                'borrow_date'         => $borrow_date,
                'return_date'         => $return_date,
                'are_mr_ics_num'      => $are_mr_ics_num,
                'property_number'     => $property_number,
                'serial_number'       => $serial_number,
                'reference_no'        => $reference_no,
                'status'              => $status,
                'remarks'             => $remarks,
            ];

            /* ── Record OUT transaction in inventory_transactions ─────────── */
            $txn_remarks = "Borrowed {$quantity} unit(s) of item #{$inventory_item_id} from '{$from_person}' to '{$to_person}'"
                . ($serial_number   ? ". Serial(s): {$serial_number}"       : '')
                . ($are_mr_ics_num  ? ". PAR/ICS: {$are_mr_ics_num}"        : '')
                . ($property_number ? ". Property No.: {$property_number}"  : '')
                . ". Ref: {$reference_no}. By: {$encoder_name}. Borrow ID: {$new_id}.";

            $txn_stmt = $conn->prepare(
                "INSERT INTO inventory_transactions
                    (inventory_item_id, performed_by_id, performed_by_name,
                     transaction_type, quantity, reference_no, remarks)
                 VALUES (?, ?, ?, 'OUT', ?, ?, ?)"
            );
            if ($txn_stmt) {
                $txn_stmt->bind_param(
                    'iisiss',
                    $inventory_item_id,
                    $encoder_id,
                    $encoder_name,
                    $quantity,
                    $reference_no,
                    $txn_remarks
                );
                $txn_stmt->execute();
                $txn_stmt->close();
            }
            /* ── End inventory_transactions ─────────────────────────────────── */

            $syslog_detail = "Borrow ID:{$new_id} | Ref:{$reference_no} | {$quantity} unit(s) from '{$from_person}' to '{$to_person}' | Status:{$status}."
                . ($are_mr_ics_num  ? " PAR/ICS: [{$are_mr_ics_num}]."    : '')
                . ($property_number ? " Prop#: [{$property_number}]."      : '')
                . ($serial_number   ? " Serial(s): [{$serial_number}]."    : '');

            log_audit($conn, $encoder_id, 'borrowed_items', 'CREATE', $new_id, null, $new_data);
            log_system(
                $conn,
                $encoder_id,
                'BORROW_RECORD_CREATED',
                "Encoder '{$encoder_name}' created borrow record (ID:{$new_id}) for item #{$inventory_item_id} to '{$to_person}'. | {$syslog_detail}",
                ['borrow_id' => $new_id, 'quantity' => $quantity, 'reference_no' => $reference_no, 'are_mr_ics_num' => $are_mr_ics_num, 'serial_number' => $serial_number, 'property_number' => $property_number]
            );

            $_SESSION['feedback'] = ['type' => 'success', 'message' => "✓ Borrowed item record created successfully (Borrow ID: {$new_id})."];
        } else {
            $_SESSION['feedback'] = ['type' => 'danger', 'message' => 'Database error: ' . htmlspecialchars($conn->error)];
        }
    }

    /* ── 4C. ADD ACCOUNTABLE ITEM ───────────────────────────────────────── */ elseif ($action === 'add_accountable_item') {
        $inventory_item_id  = (int)($_POST['inventory_item_id'] ?? 0);
        $employee_id        = (int)($_POST['employee_id'] ?? 0) ?: null;
        $person_name        = trim($_POST['person_name'] ?? '');
        $assigned_quantity  = (int)($_POST['assigned_quantity'] ?? 0);
        $particulars        = trim($_POST['particulars'] ?? '') ?: null;
        $are_mr_ics_num     = trim($_POST['are_mr_ics_num'] ?? '') ?: null;
        $property_number    = trim($_POST['property_number'] ?? '') ?: null;
        $serial_number      = trim($_POST['serial_number'] ?? '') ?: null;
        $po_number          = trim($_POST['po_number'] ?? '') ?: null;
        $account_code       = trim($_POST['account_code'] ?? '') ?: null;
        $old_account_code   = trim($_POST['old_account_code'] ?? '') ?: null;
        $condition_status   = trim($_POST['condition_status'] ?? 'Serviceable') ?: 'Serviceable';
        $remarks            = trim($_POST['remarks'] ?? '') ?: null;

        if ($inventory_item_id <= 0 || empty($person_name) || $assigned_quantity <= 0) {
            $_SESSION['feedback'] = ['type' => 'danger', 'message' => 'Required fields: Inventory Item ID, Person Name, Assigned Quantity.'];
            header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
            exit;
        }

        $stmt = $conn->prepare(
            "INSERT INTO accountable_items
                (inventory_item_id, employee_id, person_name, assigned_quantity,
                 are_mr_ics_num, property_number, serial_number, po_number,
                 account_code, old_account_code, condition_status, remarks,
                 created_by_id, created_by_name)
             VALUES (?,?,?,?, ?,?,?,?, ?,?,?,?, ?,?)"
        );
        if ($stmt) {
            $stmt->bind_param(
                'iisissssssssss',
                $inventory_item_id,
                $employee_id,
                $person_name,
                $assigned_quantity,
                $are_mr_ics_num,
                $property_number,
                $serial_number,
                $po_number,
                $account_code,
                $old_account_code,
                $condition_status,
                $remarks,
                $encoder_id,
                $encoder_name
            );
            $stmt->execute();
            $new_id = (int)$conn->insert_id;
            $stmt->close();

            /* ── Deduct from inventory_items ──────────────────────────────── */
            // Fetch current inventory row for audit + deduction
            $inv_row = null;
            $inv_stmt = $conn->prepare(
                "SELECT quantity, serial_number, property_number, are_mr_ics_num
                 FROM inventory_items WHERE id = ? FOR UPDATE"
            );
            if ($inv_stmt) {
                $inv_stmt->bind_param('i', $inventory_item_id);
                $inv_stmt->execute();
                $inv_res = $inv_stmt->get_result();
                $inv_row = $inv_res->fetch_assoc();
                $inv_stmt->close();
            }

            if ($inv_row) {
                $old_inv_snapshot = $inv_row; // for audit

                // -- Decrement quantity
                $new_qty = max(0, (int)$inv_row['quantity'] - $assigned_quantity);

                // Helper: split a /-delimited field into trimmed, non-empty tokens
                $splitTokens = function (string $raw): array {
                    return array_values(array_filter(
                        array_map('trim', explode('/', $raw)),
                        fn($t) => $t !== ''
                    ));
                };

                // -- Remove assigned ARE/MR/ICS tokens from inventory
                $assignedAre    = $are_mr_ics_num
                    ? $splitTokens($are_mr_ics_num)
                    : [];
                $invAreTokens   = $splitTokens($inv_row['are_mr_ics_num'] ?? '');
                $remainingAre   = array_values(array_filter($invAreTokens, fn($t) => !in_array($t, $assignedAre, true)));
                $new_are        = implode(' / ', $remainingAre);

                // -- Remove assigned serial tokens from inventory
                $assignedSerials  = $serial_number
                    ? $splitTokens($serial_number)
                    : [];
                $invSerialTokens  = $splitTokens($inv_row['serial_number'] ?? '');
                $remainingSerials = array_values(array_filter($invSerialTokens, fn($t) => !in_array($t, $assignedSerials, true)));
                $new_serial       = implode(' / ', $remainingSerials);

                // -- Remove property_number tokens positionally aligned with ARE tokens
                // (inventory stores property_number in the same /-order as are_mr_ics_num)
                $invPropTokens   = $splitTokens($inv_row['property_number'] ?? '');
                $removedPropIdxs = [];
                foreach ($assignedAre as $a) {
                    $idx = array_search($a, $invAreTokens, true);
                    if ($idx !== false) $removedPropIdxs[] = $idx;
                }
                $remainingProp = [];
                foreach ($invPropTokens as $i => $pt) {
                    if (!in_array($i, $removedPropIdxs, true)) $remainingProp[] = $pt;
                }
                $new_property = implode(' / ', $remainingProp);

                $upd = $conn->prepare(
                    "UPDATE inventory_items
                        SET quantity = ?,
                            serial_number = ?,
                            property_number = ?,
                            are_mr_ics_num = ?,
                            date_updated = NOW()
                      WHERE id = ?"
                );
                if ($upd) {
                    $upd->bind_param(
                        'isssi',
                        $new_qty,
                        $new_serial,
                        $new_property,
                        $new_are,
                        $inventory_item_id
                    );
                    $upd->execute();
                    $upd->close();
                }

                // Audit the inventory change
                $inv_new_data = [
                    'id'             => $inventory_item_id,
                    'quantity'       => $new_qty,
                    'serial_number'  => $new_serial,
                    'property_number' => $new_property,
                    'are_mr_ics_num' => $new_are,
                ];
                log_audit($conn, $encoder_id, 'inventory_items', 'UPDATE', $inventory_item_id, $old_inv_snapshot, $inv_new_data);
            }
            /* ── End deduction ──────────────────────────────────────────────── */

            /* ── Record OUT transaction in inventory_transactions ─────────── */
            $txn_remarks = "Assigned {$assigned_quantity} unit(s) of item #{$inventory_item_id} to '{$person_name}'"
                . ($serial_number   ? ". Serial(s): {$serial_number}"         : '')
                . ($are_mr_ics_num  ? ". PAR/ICS: {$are_mr_ics_num}"          : '')
                . ($property_number ? ". Property No.: {$property_number}"    : '')
                . ". By: {$encoder_name}. Accountable Record ID: {$new_id}.";

            $txn_stmt = $conn->prepare(
                "INSERT INTO inventory_transactions
                    (inventory_item_id, performed_by_id, performed_by_name,
                     transaction_type, quantity, reference_no, remarks)
                 VALUES (?, ?, ?, 'OUT', ?, ?, ?)"
            );
            if ($txn_stmt) {
                $txn_ref = 'ACCT-' . date('Ymd') . '-' . $new_id;
                $txn_stmt->bind_param(
                    'iisiss',
                    $inventory_item_id,
                    $encoder_id,
                    $encoder_name,
                    $assigned_quantity,
                    $txn_ref,
                    $txn_remarks
                );
                $txn_stmt->execute();
                $txn_stmt->close();
            }
            /* ── End inventory_transactions ─────────────────────────────────── */

            /* ── Full audit snapshot for accountable_items CREATE ───────────── */
            $new_data = [
                'inventory_item_id' => $inventory_item_id,
                'person_name'       => $person_name,
                'assigned_quantity' => $assigned_quantity,
                'are_mr_ics_num'    => $are_mr_ics_num,
                'property_number'   => $property_number,
                'serial_number'     => $serial_number,
                'po_number'         => $po_number,
                'account_code'      => $account_code,
                'old_account_code'  => $old_account_code,
                'condition_status'  => $condition_status,
                'remarks'           => $remarks,
            ];

            $syslog_detail = "Accountable Record ID:{$new_id} → {$assigned_quantity} unit(s) to '{$person_name}'."
                . ($are_mr_ics_num  ? " PAR/ICS: [{$are_mr_ics_num}]."    : '')
                . ($property_number ? " Prop#: [{$property_number}]."      : '')
                . ($serial_number   ? " Serial(s): [{$serial_number}]."    : '');

            log_audit($conn, $encoder_id, 'accountable_items', 'CREATE', $new_id, null, $new_data);
            log_system(
                $conn,
                $encoder_id,
                'ACCOUNTABLE_ITEM_CREATED',
                "Encoder '{$encoder_name}' created accountable record (ID:{$new_id}) assigned to '{$person_name}'. | {$syslog_detail}",
                ['record_id' => $new_id, 'qty' => $assigned_quantity, 'are_mr_ics_num' => $are_mr_ics_num, 'serial_number' => $serial_number, 'property_number' => $property_number]
            );

            $_SESSION['feedback'] = ['type' => 'success', 'message' => "✓ Accountable item record created successfully (Record ID: {$new_id})."];
        } else {
            $_SESSION['feedback'] = ['type' => 'danger', 'message' => 'Database error: ' . htmlspecialchars($conn->error)];
        }
    }

    // ── PRG: Always redirect after POST so a browser refresh cannot re-submit ──
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
}

/* ── 5. PRE-FETCH INVENTORY ITEMS for dropdowns ───────────────────────────── */
$inventory_items_list = [];
$res = $conn->query("SELECT id, item_name, quantity, serial_number, are_mr_ics_num, property_number, particulars FROM inventory_items WHERE item_status = 'Active' ORDER BY item_name ASC LIMIT 500");
if ($res) {
    while ($row = $res->fetch_assoc()) $inventory_items_list[] = $row;
}

/* ── 5-TABLE. PRE-FETCH ALL INVENTORY ITEMS for the encoder data table ─────── */
$inv_table_rows = [];
$tbl_res = $conn->query(
    "SELECT id, item_name, particulars, are_mr_ics_num, serial_number, property_number,
            quantity, amount, value_amount, total_amount, date_delivered,
            item_status, date_updated
     FROM inventory_items
     ORDER BY date_delivered DESC, item_name ASC, id DESC
     LIMIT 2000"
);
if ($tbl_res) {
    while ($r = $tbl_res->fetch_assoc()) $inv_table_rows[] = $r;
}

/* ── 5a-1. BUILD ACCT_ITEM_MAP for Accountable-tab category → particulars ─── *
 * Groups $inventory_items_list by item_name so the Accountable tab can:        *
 *   • Show a category (item_name) selector                                     *
 *   • Autocomplete/suggest particulars filtered to the chosen item_name        *
 *   • Auto-resolve inventory_item_id once item_name + particulars are chosen   *
 */
$acct_item_names = [];   // unique sorted item names (categories)
$acct_item_map   = [];   // { itemName: [{id, p, qty, sn, are, prop}] }
foreach ($inventory_items_list as $ii) {
    $n = $ii['item_name'];
    if (!in_array($n, $acct_item_names, true)) {
        $acct_item_names[] = $n;
    }
    $acct_item_map[$n][] = [
        'id'   => (int)$ii['id'],
        'p'    => $ii['particulars']      ?? '',
        'qty'  => (int)$ii['quantity'],
        'sn'   => $ii['serial_number']   ?? '',
        'are'  => $ii['are_mr_ics_num']  ?? '',
        'prop' => $ii['property_number'] ?? '',
    ];
}
sort($acct_item_names);
$acct_item_names_json = json_encode($acct_item_names, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
$acct_item_map_json   = json_encode($acct_item_map,   JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);

/* ── 5b. PRE-FETCH for Inventory-tab smart fields ─────────────────────────── *
 * Groups every row by (item_name, particulars) so the encoder tab can:
 *   • Autocomplete item names from existing entries
 *   • Show a particulars dropdown filtered to the chosen item name
 *   • Auto-fill value_amount and property_number from the chosen particulars
 *   • Show all existing serials as a reference (so duplicates are visible)
 */
$inv_item_names  = [];   // ['Desktop Computer', 'Laptop', …]
$inv_item_map    = [];   // { 'Desktop Computer' => [ {p, pn, s, v}, … ], … }

$inv_smart_res = $conn->query(
    "SELECT
         item_name,
         particulars,
         MAX(NULLIF(TRIM(property_number), ''))                                    AS property_number,
         MAX(NULLIF(TRIM(are_mr_ics_num), ''))                                     AS are_mr_ics_num,
         GROUP_CONCAT(NULLIF(TRIM(serial_number), '') ORDER BY id ASC SEPARATOR ' / ') AS all_serials,
         MAX(value_amount) AS value_amount
     FROM inventory_items
     GROUP BY item_name, particulars
     ORDER BY item_name ASC, particulars ASC
     LIMIT 2000"
);
if ($inv_smart_res) {
    while ($row = $inv_smart_res->fetch_assoc()) {
        $n = $row['item_name'];
        if (!in_array($n, $inv_item_names, true)) {
            $inv_item_names[] = $n;
        }
        $inv_item_map[$n][] = [
            'p'   => $row['particulars'],
            'pn'  => $row['property_number'] ?? '',
            'are' => $row['are_mr_ics_num']  ?? '',
            's'   => $row['all_serials'] ?? '',
            'v'   => (float)($row['value_amount'] ?? 0),
        ];
    }
}
$inv_item_names_json = json_encode($inv_item_names,  JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
$inv_item_map_json   = json_encode($inv_item_map,    JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);

/* ── 6. PRE-FETCH EMPLOYEES for autocomplete ──────────────────────────────── */
$employees_list = [];
$emp_res = $conn->query(
    "SELECT ID,
            CONCAT(
                TRIM(FIRSTNAME), ' ',
                IF(MIDDLENAME IS NOT NULL AND MIDDLENAME <> '', CONCAT(TRIM(MIDDLENAME), ' '), ''),
                TRIM(LASTNAME),
                IF(SUFFIX IS NOT NULL AND SUFFIX <> '', CONCAT(' ', TRIM(SUFFIX)), '')
            ) AS full_name,
            end_user_id_number
     FROM cao_employee
     WHERE DELETED = 0
     ORDER BY LASTNAME ASC, FIRSTNAME ASC
     LIMIT 2000"
);
if ($emp_res) {
    while ($row = $emp_res->fetch_assoc()) {
        $employees_list[] = [
            'id'        => (int)$row['ID'],
            'full_name' => trim($row['full_name']),
            'emp_no'    => $row['end_user_id_number'],
        ];
    }
}
$employees_json = json_encode($employees_list, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);

/* ── 7. PRE-FETCH ACCOUNTABLE ITEMS for borrow-tab dynamic quantity ───────── */
$accountable_items_list = [];
$acct_res = $conn->query(
    "SELECT ai.id, ai.inventory_item_id, ai.person_name, ai.employee_id, ai.assigned_quantity,
            ai.serial_number, ai.are_mr_ics_num, ai.property_number,
            ii.item_name
     FROM accountable_items ai
     JOIN inventory_items ii ON ii.id = ai.inventory_item_id
     WHERE ai.is_deleted = 0 AND ai.assigned_quantity > 0
     ORDER BY ai.person_name ASC
     LIMIT 5000"
);
if ($acct_res) {
    while ($row = $acct_res->fetch_assoc()) {
        $accountable_items_list[] = [
            'id'                => (int)$row['id'],
            'inventory_item_id' => (int)$row['inventory_item_id'],
            'item_name'         => $row['item_name'],
            'person_name'       => $row['person_name'],
            'employee_id'       => (int)$row['employee_id'],
            'assigned_quantity' => (int)$row['assigned_quantity'],
            'serial_number'     => $row['serial_number']   ?? '',
            'are_mr_ics_num'    => $row['are_mr_ics_num']  ?? '',
            'property_number'   => $row['property_number'] ?? '',
        ];
    }
}
$accountable_items_json = json_encode($accountable_items_list, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);

/* ── 8. AUTO-GENERATE BORROW REFERENCE NUMBER ─────────────────────────────── */
// Format: BRW-YYYYMMDDHHIISS-{encoder_id} — unique per encoder per second, easily traceable
$borrow_ref_no = 'BRW-' . date('YmdHis') . '-' . sprintf('%04d', $encoder_id);

$page_title = "Encoder Module | CAO I-M-S";
?>
<!DOCTYPE html>
<html lang="en" class="light-style layout-menu-fixed" dir="ltr"
    data-theme="theme-default" data-assets-path="assets/" data-template="vertical-menu-template-free">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title><?= htmlspecialchars($page_title) ?></title>
    <link rel="icon" type="image/x-icon" href="<?= site_url('assets/img/favicon/favicon.ico') ?>" />
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@300;400;600;700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="<?= site_url('assets/vendor/fonts/boxicons.css') ?>" />
    <link rel="stylesheet" href="<?= site_url('assets/vendor/css/core.css') ?>" class="template-customizer-core-css" />
    <link rel="stylesheet" href="<?= site_url('assets/vendor/css/theme-default.css') ?>" class="template-customizer-theme-css" />
    <link rel="stylesheet" href="<?= site_url('assets/css/demo.css') ?>" />
    <script src="<?= site_url('assets/vendor/js/helpers.js') ?>"></script>
    <script src="<?= site_url('assets/js/config.js') ?>"></script>
    <style>
        /* ── Encoder Module Aesthetics ──────────────────────────────────── */
        :root {
            --enc-accent: #696cff;
            --enc-accent-soft: rgba(105, 108, 255, .08);
            --enc-success: #28c76f;
            --enc-card-border: rgba(105, 108, 255, .18);
        }

        body {
            background: #f4f5fb;
        }

        /* Full-width single-column layout — encoder sees NO sidebar */
        .encoder-root {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* ── Top ribbon ──────────────────────────────────────────────────── */
        .enc-topbar {
            background: #fff;
            border-bottom: 2px solid var(--enc-accent);
            padding: .9rem 1.75rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 200;
            box-shadow: 0 2px 12px rgba(105, 108, 255, .10);
        }

        .enc-topbar .brand {
            font-size: 1.15rem;
            font-weight: 700;
            color: var(--enc-accent);
            letter-spacing: -.3px;
        }

        .enc-topbar .brand span {
            color: #566a7f;
            font-weight: 400;
            font-size: .95rem;
        }

        .enc-topbar .enc-badge {
            background: var(--enc-accent);
            color: #fff;
            font-size: .72rem;
            font-weight: 700;
            letter-spacing: .06em;
            padding: .22em .72em;
            border-radius: 2rem;
            text-transform: uppercase;
        }

        .enc-topbar .logout-link {
            font-size: .85rem;
            color: #ea5455;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: .3rem;
        }

        .enc-topbar .logout-link:hover {
            text-decoration: underline;
        }

        /* ── Main area ───────────────────────────────────────────────────── */
        .enc-main {
            flex: 1;
            padding: 1.75rem 1.5rem 3rem;
            max-width: 960px;
            margin: 0 auto;
            width: 100%;
        }

        /* ── Tab nav ─────────────────────────────────────────────────────── */
        .enc-tabs {
            display: flex;
            gap: .5rem;
            background: #fff;
            border-radius: .75rem;
            padding: .4rem;
            box-shadow: 0 2px 8px rgba(0, 0, 0, .06);
            margin-bottom: 1.5rem;
        }

        .enc-tab-btn {
            flex: 1;
            padding: .6rem 1rem;
            border: none;
            border-radius: .5rem;
            background: transparent;
            font-size: .88rem;
            font-weight: 600;
            color: #8592a3;
            cursor: pointer;
            transition: background .18s, color .18s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: .4rem;
        }

        .enc-tab-btn.active {
            background: var(--enc-accent);
            color: #fff;
            box-shadow: 0 4px 12px rgba(105, 108, 255, .30);
        }

        .enc-tab-btn:not(.active):hover {
            background: var(--enc-accent-soft);
            color: var(--enc-accent);
        }

        /* ── Cards ───────────────────────────────────────────────────────── */
        .enc-card {
            background: #fff;
            border-radius: .85rem;
            border: 1px solid var(--enc-card-border);
            box-shadow: 0 4px 24px rgba(105, 108, 255, .07);
            overflow: hidden;
            display: none;
        }

        .enc-card.active {
            display: block;
            animation: fadeUp .22s ease;
        }

        @keyframes fadeUp {
            from {
                opacity: 0;
                transform: translateY(6px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .enc-card-header {
            background: var(--enc-accent-soft);
            border-bottom: 1px solid var(--enc-card-border);
            padding: 1rem 1.4rem;
            display: flex;
            align-items: center;
            gap: .6rem;
        }

        .enc-card-header h5 {
            margin: 0;
            font-size: 1rem;
            font-weight: 700;
            color: var(--enc-accent);
        }

        .enc-card-header .bx {
            font-size: 1.3rem;
            color: var(--enc-accent);
        }

        .enc-card-body {
            padding: 1.4rem;
        }

        /* ── Form elements ───────────────────────────────────────────────── */
        .enc-row {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(230px, 1fr));
            gap: .9rem 1.1rem;
            margin-bottom: .9rem;
        }

        .enc-field {
            display: flex;
            flex-direction: column;
            gap: .25rem;
        }

        .enc-field label {
            font-size: .78rem;
            font-weight: 700;
            color: #566a7f;
            text-transform: uppercase;
            letter-spacing: .04em;
        }

        .enc-field label .req {
            color: #ea5455;
        }

        .enc-field input,
        .enc-field select,
        .enc-field textarea {
            border: 1.5px solid #d9dee3;
            border-radius: .45rem;
            padding: .52rem .78rem;
            font-size: .9rem;
            color: #566a7f;
            background: #f8f8fb;
            transition: border-color .15s, box-shadow .15s;
            outline: none;
        }

        .enc-field input:focus,
        .enc-field select:focus,
        .enc-field textarea:focus {
            border-color: var(--enc-accent);
            box-shadow: 0 0 0 3px rgba(105, 108, 255, .12);
            background: #fff;
        }

        .enc-field textarea {
            resize: vertical;
            min-height: 72px;
        }

        .enc-field-full {
            grid-column: 1 / -1;
        }

        /* ── Submit button ───────────────────────────────────────────────── */
        .enc-submit {
            display: inline-flex;
            align-items: center;
            gap: .45rem;
            background: var(--enc-accent);
            color: #fff;
            border: none;
            border-radius: .55rem;
            padding: .65rem 1.6rem;
            font-size: .92rem;
            font-weight: 700;
            cursor: pointer;
            transition: background .18s, box-shadow .18s, transform .1s;
            box-shadow: 0 4px 12px rgba(105, 108, 255, .30);
        }

        .enc-submit:hover {
            background: #5457d6;
            box-shadow: 0 6px 20px rgba(105, 108, 255, .38);
            transform: translateY(-1px);
        }

        .enc-submit:active {
            transform: translateY(0);
        }

        /* ── Feedback banner ─────────────────────────────────────────────── */
        .enc-feedback {
            border-radius: .6rem;
            padding: .85rem 1.1rem;
            margin-bottom: 1.2rem;
            font-size: .9rem;
            font-weight: 500;
            display: flex;
            align-items: flex-start;
            gap: .55rem;
        }

        .enc-feedback.success {
            background: rgba(40, 199, 111, .10);
            border: 1px solid rgba(40, 199, 111, .30);
            color: #1a6e3e;
        }

        .enc-feedback.danger {
            background: rgba(234, 84, 85, .10);
            border: 1px solid rgba(234, 84, 85, .30);
            color: #9c2e2e;
        }

        .enc-feedback .bx {
            font-size: 1.15rem;
            flex-shrink: 0;
            margin-top: .05rem;
        }

        /* ── Section divider ─────────────────────────────────────────────── */
        .enc-section-label {
            font-size: .74rem;
            font-weight: 800;
            letter-spacing: .09em;
            color: #a1b0be;
            text-transform: uppercase;
            margin: 1.1rem 0 .5rem;
            padding-bottom: .3rem;
            border-bottom: 1px dashed #e0e4ea;
        }

        /* ── Footer ──────────────────────────────────────────────────────── */
        .enc-footer {
            text-align: center;
            font-size: .78rem;
            color: #a1b0be;
            padding: 1rem;
            background: #fff;
            border-top: 1px solid #e6e8ef;
        }

        /* ── Employee Autocomplete ───────────────────────────────────────── */
        .emp-autocomplete-wrap {
            position: relative;
        }

        .emp-autocomplete-wrap .emp-search-input {
            width: 100%;
            padding-right: 2.2rem;
        }

        .emp-autocomplete-wrap .emp-clear-btn {
            position: absolute;
            right: .6rem;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            color: #a1b0be;
            font-size: 1.1rem;
            line-height: 1;
            padding: 0;
            display: none;
        }

        .emp-autocomplete-wrap .emp-clear-btn:hover {
            color: #ea5455;
        }

        .emp-dropdown {
            position: absolute;
            top: calc(100% + 4px);
            left: 0;
            right: 0;
            background: #fff;
            border: 1.5px solid var(--enc-accent);
            border-radius: .55rem;
            box-shadow: 0 8px 28px rgba(105, 108, 255, .18);
            z-index: 500;
            max-height: 220px;
            overflow-y: auto;
            display: none;
        }

        .emp-dropdown.open {
            display: block;
            animation: fadeUp .14s ease;
        }

        .emp-dropdown-item {
            padding: .55rem .9rem;
            cursor: pointer;
            font-size: .88rem;
            color: #566a7f;
            display: flex;
            flex-direction: column;
            gap: .1rem;
            border-bottom: 1px solid #f0f1f7;
            transition: background .12s;
        }

        .emp-dropdown-item:last-child {
            border-bottom: none;
        }

        .emp-dropdown-item:hover,
        .emp-dropdown-item.focused {
            background: var(--enc-accent-soft);
            color: var(--enc-accent);
        }

        .emp-dropdown-item .emp-name {
            font-weight: 600;
        }

        .emp-dropdown-item .emp-meta {
            font-size: .75rem;
            color: #a1b0be;
        }

        .emp-dropdown-empty {
            padding: .7rem .9rem;
            font-size: .85rem;
            color: #a1b0be;
            text-align: center;
        }

        .emp-selected-chip {
            display: none;
            align-items: center;
            gap: .4rem;
            background: var(--enc-accent-soft);
            border: 1.5px solid var(--enc-accent);
            border-radius: .4rem;
            padding: .3rem .65rem;
            font-size: .82rem;
            font-weight: 600;
            color: var(--enc-accent);
            margin-top: .3rem;
        }

        .emp-selected-chip.visible {
            display: flex;
        }

        .emp-selected-chip .chip-remove {
            background: none;
            border: none;
            cursor: pointer;
            color: var(--enc-accent);
            font-size: 1rem;
            line-height: 1;
            padding: 0;
            margin-left: .2rem;
        }

        .emp-selected-chip .chip-remove:hover {
            color: #ea5455;
        }

        @media (max-width: 600px) {
            .enc-tabs {
                flex-direction: column;
            }

            .enc-row {
                grid-template-columns: 1fr;
            }
        }

        /* ── Serial Number Checklist ─────────────────────────────────────── */
        .serial-checklist-wrap {
            background: #f8f9ff;
            border: 1.5px solid #d5d9e2;
            border-radius: .45rem;
            padding: .5rem .75rem;
            min-height: 2.6rem;
            display: flex;
            flex-wrap: wrap;
            gap: .4rem .55rem;
            align-items: center;
        }

        .serial-checklist-wrap .serial-placeholder,
        .serial-checklist-wrap .serial-no-data {
            font-size: .82rem;
            color: #a1b0be;
            font-style: italic;
        }

        .serial-check-item {
            display: inline-flex;
            align-items: center;
            gap: .35rem;
            background: #fff;
            border: 1.5px solid #c5c7ff;
            border-radius: .35rem;
            padding: .28rem .6rem;
            cursor: pointer;
            transition: background .15s, border-color .15s, color .15s;
            font-size: .83rem;
            color: #566a7f;
            user-select: none;
        }

        .serial-check-item:hover {
            background: var(--enc-accent-soft);
            border-color: var(--enc-accent);
        }

        .serial-check-item input[type="checkbox"] {
            accent-color: var(--enc-accent);
            width: 1em;
            height: 1em;
            cursor: pointer;
            flex-shrink: 0;
        }

        .serial-check-item.sn-checked {
            background: var(--enc-accent-soft);
            border-color: var(--enc-accent);
            color: var(--enc-accent);
            font-weight: 600;
        }

        /* Existing tokens from DB — shown read-only, cannot be submitted again */
        .serial-check-item.sn-disabled {
            background: #f0f1f5;
            border-color: #c8ccd4;
            color: #8592a3;
            cursor: default;
            opacity: .78;
            pointer-events: none;
        }

        .serial-check-item.sn-disabled input[type="checkbox"] {
            cursor: default;
            pointer-events: none;
        }

        /* ── Serial textarea (Inventory tab) ─────────────────────────────── */
        .inv-serial-wrap {
            position: relative;
        }

        .inv-serial-wrap textarea {
            width: 100%;
            min-height: 60px;
            resize: vertical;
            font-family: monospace;
            font-size: .88rem;
            letter-spacing: .02em;
            line-height: 1.6;
            padding-right: 3.5rem;
        }

        .inv-serial-count {
            position: absolute;
            top: .45rem;
            right: .55rem;
            background: var(--enc-accent);
            color: #fff;
            font-size: .7rem;
            font-weight: 700;
            border-radius: 2rem;
            padding: .18em .6em;
            line-height: 1.4;
            pointer-events: none;
            display: none;
        }

        .inv-serial-count.visible {
            display: inline-block;
        }

        /* ── Item-name autocomplete (Inventory tab) ──────────────────────── */
        .inv-name-wrap {
            position: relative;
        }

        .inv-name-dropdown {
            position: absolute;
            top: calc(100% + 3px);
            left: 0;
            right: 0;
            background: #fff;
            border: 1.5px solid var(--enc-accent);
            border-radius: .55rem;
            box-shadow: 0 8px 28px rgba(105, 108, 255, .18);
            z-index: 600;
            max-height: 210px;
            overflow-y: auto;
            display: none;
        }

        .inv-name-dropdown.open {
            display: block;
            animation: fadeUp .14s ease;
        }

        .inv-name-drop-item {
            padding: .5rem .9rem;
            cursor: pointer;
            font-size: .88rem;
            color: #566a7f;
            border-bottom: 1px solid #f0f1f7;
            transition: background .12s;
        }

        .inv-name-drop-item:last-child {
            border-bottom: none;
        }

        .inv-name-drop-item:hover,
        .inv-name-drop-item.focused {
            background: var(--enc-accent-soft);
            color: var(--enc-accent);
            font-weight: 600;
        }

        .inv-name-drop-empty {
            padding: .6rem .9rem;
            font-size: .83rem;
            color: #a1b0be;
            text-align: center;
        }

        /* ── Particulars autocomplete (Inventory tab) ────────────────────── */
        .inv-part-wrap {
            position: relative;
        }

        .inv-part-wrap input[type="text"] {
            width: 100%;
        }

        .inv-part-dropdown {
            position: absolute;
            top: calc(100% + 3px);
            left: 0;
            right: 0;
            background: #fff;
            border: 1.5px solid var(--enc-accent);
            border-radius: .55rem;
            box-shadow: 0 8px 28px rgba(105, 108, 255, .18);
            z-index: 600;
            max-height: 220px;
            overflow-y: auto;
            display: none;
        }

        .inv-part-dropdown.open {
            display: block;
            animation: fadeUp .14s ease;
        }

        .inv-part-drop-item {
            padding: .5rem .9rem;
            cursor: pointer;
            font-size: .88rem;
            color: #566a7f;
            border-bottom: 1px solid #f0f1f7;
            transition: background .12s;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: .6rem;
        }

        .inv-part-drop-item:last-child {
            border-bottom: none;
        }

        .inv-part-drop-item:hover,
        .inv-part-drop-item.focused {
            background: var(--enc-accent-soft);
            color: var(--enc-accent);
            font-weight: 600;
        }

        .inv-part-drop-meta {
            font-size: .75rem;
            color: #a1b0be;
            font-weight: 400;
            white-space: nowrap;
            flex-shrink: 0;
        }

        .inv-part-drop-item:hover .inv-part-drop-meta,
        .inv-part-drop-item.focused .inv-part-drop-meta {
            color: var(--enc-accent);
            opacity: .8;
        }

        .inv-part-drop-new-row {
            padding: .45rem .9rem;
            font-size: .78rem;
            color: #28c76f;
            font-style: italic;
            border-top: 1px dashed #e0e4ea;
            cursor: default;
        }

        /* ── Existing-serials reference panel ────────────────────────────── */
        .inv-serial-ref {
            margin-top: .55rem;
            background: #f8f9ff;
            border: 1.5px dashed #c5c7ff;
            border-radius: .45rem;
            padding: .55rem .75rem;
            display: none;
        }

        .inv-serial-ref.visible {
            display: block;
        }

        .inv-serial-ref-label {
            font-size: .72rem;
            font-weight: 800;
            letter-spacing: .06em;
            text-transform: uppercase;
            color: #696cff;
            margin-bottom: .35rem;
        }

        .inv-serial-ref-chips {
            display: flex;
            flex-wrap: wrap;
            gap: .3rem .4rem;
        }

        .inv-serial-ref-chip {
            background: #fff;
            border: 1.5px solid #c5c7ff;
            border-radius: .3rem;
            padding: .2rem .55rem;
            font-size: .78rem;
            font-family: monospace;
            color: #566a7f;
        }

        .inv-serial-ref-none {
            font-size: .8rem;
            color: #a1b0be;
            font-style: italic;
        }

        /* ── Acct serial checklist validation ────────────────────────────── */
        .serial-checklist-wrap.sn-valid {
            border-color: #28c76f;
            background: rgba(40, 199, 111, .04);
        }

        .serial-checklist-wrap.sn-invalid {
            border-color: #ea5455;
            background: rgba(234, 84, 85, .04);
        }

        .sn-match-hint {
            font-size: .76rem;
            margin-top: .3rem;
            font-weight: 600;
        }

        .sn-match-hint.ok {
            color: #28c76f;
        }

        .sn-match-hint.warn {
            color: #ff9f43;
        }

        .sn-match-hint.error {
            color: #ea5455;
        }

        /* ── Serial-duplicate warning modal ─────────────────────────────── */
        .sn-dupe-overlay {
            position: fixed;
            inset: 0;
            background: rgba(30, 30, 50, .52);
            z-index: 9999;
            display: flex;
            align-items: center;
            justify-content: center;
            animation: fadeIn .18s ease;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
            }

            to {
                opacity: 1;
            }
        }

        .sn-dupe-modal {
            background: #fff;
            border-radius: .75rem;
            padding: 1.75rem 2rem 1.5rem;
            max-width: 480px;
            width: 92%;
            box-shadow: 0 12px 48px rgba(105, 108, 255, .22);
            border-top: 4px solid #ff9f43;
            animation: slideUp .18s ease;
        }

        @keyframes slideUp {
            from {
                transform: translateY(18px);
                opacity: 0;
            }

            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .sn-dupe-modal .sn-dupe-title {
            font-size: 1.05rem;
            font-weight: 700;
            color: #566a7f;
            display: flex;
            align-items: center;
            gap: .5rem;
            margin-bottom: .75rem;
        }

        .sn-dupe-modal .sn-dupe-title i {
            font-size: 1.35rem;
            color: #ff9f43;
        }

        .sn-dupe-modal p {
            font-size: .88rem;
            color: #566a7f;
            margin-bottom: .75rem;
            line-height: 1.55;
        }

        .sn-dupe-chips {
            display: flex;
            flex-wrap: wrap;
            gap: .35rem .45rem;
            margin-bottom: 1.1rem;
        }

        .sn-dupe-chip {
            background: rgba(234, 84, 85, .09);
            border: 1.5px solid rgba(234, 84, 85, .35);
            border-radius: .35rem;
            padding: .22rem .65rem;
            font-size: .83rem;
            font-family: monospace;
            color: #c0392b;
            font-weight: 600;
        }

        .sn-dupe-modal .sn-dupe-actions {
            display: flex;
            gap: .65rem;
            justify-content: flex-end;
            flex-wrap: wrap;
        }

        .sn-dupe-btn-cancel {
            border: 1.5px solid #d9dee3;
            background: #fff;
            color: #566a7f;
            border-radius: .5rem;
            padding: .55rem 1.25rem;
            font-size: .9rem;
            font-weight: 600;
            cursor: pointer;
            transition: background .15s, border-color .15s;
        }

        .sn-dupe-btn-cancel:hover {
            background: #f4f5fb;
            border-color: #a0a9b8;
        }

        .sn-dupe-btn-proceed {
            background: #ff9f43;
            border: none;
            color: #fff;
            border-radius: .5rem;
            padding: .55rem 1.35rem;
            font-size: .9rem;
            font-weight: 700;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(255, 159, 67, .30);
            transition: background .15s, box-shadow .15s;
        }

        .sn-dupe-btn-proceed:hover {
            background: #e08a30;
            box-shadow: 0 6px 18px rgba(255, 159, 67, .38);
        }

        /* ── Inventory Data Table ─────────────────────────────────────────── */
        .inv-data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: .82rem;
            background: #fff;
        }

        .inv-data-table thead th {
            background: #f0f1ff;
            color: #596480;
            font-weight: 700;
            padding: .55rem .65rem;
            text-align: left;
            border-bottom: 2px solid #d5d9f2;
            white-space: nowrap;
            font-size: .78rem;
            letter-spacing: .02em;
        }

        .inv-data-table tbody tr {
            border-bottom: 1px solid #eef0f6;
        }

        .inv-data-table tbody tr:hover {
            background: #f6f7ff;
        }

        .inv-data-table tbody tr[data-filtered="hidden"] {
            display: none;
        }

        .inv-data-table td {
            padding: .5rem .65rem;
            vertical-align: top;
            color: #566a7f;
        }

        .inv-tbl-id {
            font-weight: 700;
            color: #696cff;
            white-space: nowrap;
        }

        .inv-tbl-name {
            font-weight: 600;
            color: #2c3e63;
            white-space: nowrap;
        }

        .inv-tbl-part {
            max-width: 200px;
            word-break: break-word;
            font-size: .8rem;
        }

        .inv-tbl-mono {
            max-width: 180px;
            word-break: break-all;
        }

        .inv-tbl-qty {
            font-weight: 700;
            color: #28c76f;
            text-align: center;
        }

        .inv-tbl-amt {
            font-weight: 600;
            color: #3a4060;
            white-space: nowrap;
        }

        .inv-tbl-date {
            white-space: nowrap;
            font-size: .79rem;
        }

        .inv-token-chip {
            display: inline-block;
            background: #eef0ff;
            color: #696cff;
            border: 1px solid #d0d3ff;
            border-radius: .3rem;
            padding: .06rem .38rem;
            font-size: .73rem;
            font-family: monospace;
            margin: .1rem .1rem .1rem 0;
            line-height: 1.4;
        }

        .inv-tbl-edit-btn {
            background: #f0f1ff;
            border: 1.5px solid #c5c7ff;
            color: #696cff;
            border-radius: .45rem;
            padding: .36rem .6rem;
            cursor: pointer;
            font-size: .9rem;
            transition: background .13s, box-shadow .13s;
            display: inline-flex;
            align-items: center;
            gap: .2rem;
        }

        .inv-tbl-edit-btn:hover {
            background: #696cff;
            color: #fff;
            box-shadow: 0 3px 8px rgba(105, 108, 255, .25);
        }

        /* ── Inventory Edit Modal ─────────────────────────────────────────── */
        .inv-edit-overlay {
            position: fixed;
            inset: 0;
            background: rgba(40, 48, 80, .45);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            backdrop-filter: blur(2px);
        }

        .inv-edit-modal {
            background: #fff;
            border-radius: .75rem;
            width: min(98vw, 760px);
            max-height: 92vh;
            overflow-y: auto;
            box-shadow: 0 16px 60px rgba(40, 48, 80, .22);
            display: flex;
            flex-direction: column;
        }

        .inv-edit-modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 1.25rem .85rem;
            border-bottom: 1.5px solid #e7e8f0;
            font-weight: 700;
            color: #2c3e63;
            font-size: .95rem;
            position: sticky;
            top: 0;
            background: #fff;
            z-index: 2;
        }

        .inv-edit-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: .75rem 1rem;
            padding: 1rem 1.25rem;
        }

        .inv-edit-field {
            display: flex;
            flex-direction: column;
            gap: .3rem;
        }

        .inv-edit-full {
            grid-column: 1 / -1;
        }

        .inv-edit-field label {
            font-size: .82rem;
            font-weight: 600;
            color: #566a7f;
        }

        .inv-edit-field input,
        .inv-edit-field textarea,
        .inv-edit-field select {
            padding: .48rem .72rem;
            border: 1.5px solid #d9dee3;
            border-radius: .45rem;
            font-size: .87rem;
            color: #566a7f;
            background: #f8f8fb;
            outline: none;
            transition: border-color .15s, box-shadow .15s;
        }

        .inv-edit-field input:focus,
        .inv-edit-field textarea:focus,
        .inv-edit-field select:focus {
            border-color: #696cff;
            box-shadow: 0 0 0 3px rgba(105, 108, 255, .12);
            background: #fff;
        }

        .inv-edit-modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: .6rem;
            padding: .85rem 1.25rem 1rem;
            border-top: 1.5px solid #e7e8f0;
            position: sticky;
            bottom: 0;
            background: #fff;
            z-index: 2;
        }

        @media (max-width: 600px) {
            .inv-edit-grid {
                grid-template-columns: 1fr;
            }

            .inv-edit-full {
                grid-column: 1;
            }
        }
    </style>
</head>

<body>
    <div class="encoder-root">

        <!-- ── TOP BAR ─────────────────────────────────────────────────────────── -->
        <div class="enc-topbar">
            <div class="d-flex align-items-center gap-3">
                <div class="brand">
                    CAO <span>I-M-S</span>
                </div>
                <span class="enc-badge">
                    <i class="bx bx-edit-alt" style="vertical-align:middle;margin-right:3px;"></i>
                    Encoder Module
                </span>
            </div>
            <div class="d-flex align-items-center gap-3">
                <span style="font-size:.85rem;color:#566a7f;">
                    <i class="bx bx-user-circle" style="vertical-align:middle;"></i>
                    <?= htmlspecialchars($encoder_name) ?>
                </span>
                <a href="<?= site_url('login/logout.php') ?>" class="logout-link">
                    <i class="bx bx-log-out"></i> Log Out
                </a>
            </div>
        </div>

        <!-- ── MAIN ────────────────────────────────────────────────────────────── -->
        <div class="enc-main">

            <!-- Feedback banner -->
            <?php if (!empty($feedback['message'])): ?>
                <div class="enc-feedback <?= htmlspecialchars($feedback['type']) ?>">
                    <i class="bx <?= $feedback['type'] === 'success' ? 'bx-check-circle' : 'bx-x-circle' ?>"></i>
                    <div><?= $feedback['message'] /* HTML-safe, built above */ ?></div>
                </div>
            <?php endif; ?>

            <!-- Tab navigation -->
            <div class="enc-tabs">
                <button class="enc-tab-btn active" data-tab="inv">
                    <i class="bx bx-package"></i> Inventory Item
                </button>
                <button class="enc-tab-btn" data-tab="borrow">
                    <i class="bx bx-transfer"></i> Borrowed Item
                </button>
                <button class="enc-tab-btn" data-tab="account">
                    <i class="bx bx-user-pin"></i> Accountable Item
                </button>
            </div>

            <!-- ══════════════════════════════════════════════════════════════════
             TAB 1 · INVENTORY ITEM
             ══════════════════════════════════════════════════════════════════ -->
            <div class="enc-card active" id="tab-inv">
                <div class="enc-card-header">
                    <i class="bx bx-package"></i>
                    <h5>Add Inventory Item</h5>
                </div>
                <div class="enc-card-body">
                    <form method="POST" action="" autocomplete="off">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                        <input type="hidden" name="action" value="add_inventory_item">
                        <input type="hidden" name="form_token" value="<?= htmlspecialchars($form_token_value) ?>">

                        <div class="enc-section-label">Basic Information</div>
                        <div class="enc-row">
                            <!-- ── ITEM NAME — autocomplete from existing entries ── -->
                            <div class="enc-field enc-field-full">
                                <label>Item Name <span class="req">*</span></label>
                                <div class="inv-name-wrap">
                                    <input type="text"
                                        name="item_name"
                                        id="inv_item_name"
                                        placeholder="e.g. Desktop Computer"
                                        required maxlength="255"
                                        autocomplete="off"
                                        oninput="onInvItemNameInput()"
                                        onblur="setTimeout(closeInvNameDropdown,180)">
                                    <div id="inv_name_dropdown" class="inv-name-dropdown"></div>
                                </div>
                                <span id="inv_name_status" style="font-size:.74rem;margin-top:.2rem;display:none;"></span>
                            </div>

                            <!-- ── PARTICULARS — editable autocomplete ──────────────────── -->
                            <div class="enc-field enc-field-full">
                                <label>Particulars <span class="req">*</span></label>
                                <!--
                                    inv_particulars_input  : single editable autocomplete — always visible.
                                                             Suggests existing particulars for the chosen
                                                             item name; free-text entry is always allowed.
                                    inv_particulars_hidden : always submitted (synced by JS on every input
                                                             and on form submit).
                                -->
                                <input type="hidden" name="particulars" id="inv_particulars_hidden">
                                <div class="inv-part-wrap">
                                    <input type="text"
                                        id="inv_particulars_input"
                                        placeholder="Type or select brand, model, specifications…"
                                        maxlength="255"
                                        required
                                        autocomplete="off"
                                        oninput="onInvParticularsInput()"
                                        onblur="setTimeout(closeInvPartDropdown, 180)">
                                    <div id="inv_part_dropdown" class="inv-part-dropdown"></div>
                                </div>
                                <span id="inv_particulars_hint" style="font-size:.74rem;color:#8592a3;margin-top:.2rem;display:none;"></span>
                            </div>

                            <!-- ── PROPERTY NUMBER — textarea → per-entry checkboxes ── -->
                            <div class="enc-field enc-field-full">
                                <label>Property Number
                                    <span style="font-size:.72rem;font-weight:400;color:#8592a3;">
                                        — separate multiple with&nbsp;<code style="background:#eef;padding:.1em .35em;border-radius:.25rem;">/</code>&nbsp;
                                        (e.g. 2024-07-1169 / 2024-07-1170)
                                    </span>
                                </label>
                                <!-- Only checked values are submitted -->
                                <input type="hidden" name="property_number" id="inv_property_number_hidden">
                                <div class="inv-serial-wrap">
                                    <textarea id="inv_prop_num_input"
                                        placeholder="e.g. 2024-07-1169 / 2024-07-1170&#10;Type NA if item has no Property Number"
                                        oninput="onInvPropNumInput()"
                                        style="font-family:monospace;font-size:.88rem;min-height:60px;resize:vertical;padding-right:3.5rem;width:100%;border:1.5px solid #d9dee3;border-radius:.45rem;padding:.52rem .78rem;padding-right:3.5rem;color:#566a7f;background:#f8f8fb;transition:border-color .15s,box-shadow .15s;outline:none;"></textarea>
                                    <span class="inv-serial-count" id="inv_prop_num_count">0</span>
                                </div>
                                <!-- Checkbox checklist — built from textarea tokens -->
                                <div id="inv_prop_num_checklist" class="serial-checklist-wrap" style="margin-top:.55rem;display:none;">
                                    <!-- injected by JS -->
                                </div>
                                <span id="inv_prop_num_match_hint" class="sn-match-hint" style="display:none;margin-top:.3rem;"></span>
                                <span id="inv_property_number_hint" style="font-size:.74rem;color:#8592a3;margin-top:.2rem;display:none;"></span>
                            </div>

                            <!-- ── ARE / PAR / ICS NUMBER — textarea → per-entry checkboxes ── -->
                            <div class="enc-field enc-field-full">
                                <label>Property Acknowledgement Receipt (PAR) / Inventory Custodian Slip (ICS) Number
                                    <span style="font-size:.72rem;font-weight:400;color:#8592a3;">
                                        — separate multiple with&nbsp;<code style="background:#eef;padding:.1em .35em;border-radius:.25rem;">/</code>&nbsp;
                                        (e.g. PAR-2024-001 / ICS-2024-002)
                                    </span>
                                </label>
                                <!-- Only checked ARE/PAR/ICS values are submitted -->
                                <input type="hidden" name="are_mr_ics_num" id="inv_are_mr_ics_hidden">
                                <div class="inv-serial-wrap">
                                    <textarea id="inv_are_mr_ics_input"
                                        placeholder="e.g. PAR-2024-001 / ICS-2024-002&#10;Type NA if item has no PAR/ICS number."
                                        oninput="onInvAreMrIcsInput()"
                                        style="font-family:monospace;font-size:.88rem;min-height:60px;resize:vertical;padding-right:3.5rem;width:100%;border:1.5px solid #d9dee3;border-radius:.45rem;padding:.52rem .78rem;padding-right:3.5rem;color:#566a7f;background:#f8f8fb;transition:border-color .15s,box-shadow .15s;outline:none;"></textarea>
                                    <span class="inv-serial-count" id="inv_are_mr_ics_count">0</span>
                                </div>
                                <!-- Checkbox checklist — built from textarea tokens -->
                                <div id="inv_are_mr_ics_checklist" class="serial-checklist-wrap" style="margin-top:.55rem;display:none;">
                                    <!-- injected by JS -->
                                </div>
                                <span id="inv_are_mr_ics_match_hint" class="sn-match-hint" style="display:none;margin-top:.3rem;"></span>
                                <span id="inv_are_mr_ics_hint" style="font-size:.74rem;color:#8592a3;margin-top:.2rem;display:none;"></span>
                            </div>

                            <!-- ── SERIAL NUMBER — textarea + checklist (existing disabled, new checkable) ── -->
                            <div class="enc-field enc-field-full">
                                <label>Serial Number <span style="font-size:.72rem;font-weight:400;color:#8592a3;">— separate multiple with &nbsp;<code style="background:#eef;padding:.1em .35em;border-radius:.25rem;">/</code>&nbsp; (e.g. 78961 / 32184 / 65484)</span></label>
                                <!-- Only checked NEW serial values are submitted -->
                                <input type="hidden" name="serial_number" id="inv_serial_number_hidden">
                                <div class="inv-serial-wrap">
                                    <textarea id="inv_serial_number"
                                        placeholder="e.g. 78961 / 32184 / 65484&#10;Type NA if item has no Serial number."
                                        oninput="onInvSerialInput()"></textarea>
                                    <span class="inv-serial-count" id="inv_serial_count">0</span>
                                </div>
                                <span id="inv_serial_hint" style="font-size:.74rem;color:#8592a3;margin-top:.25rem;">
                                    Quantity auto-fills from the number of serials entered. You can still edit Quantity manually for items with no serial number.
                                </span>
                                <!-- Unified checklist: existing serials (disabled) + new serials (checkable) -->
                                <div id="inv_serial_checklist" class="serial-checklist-wrap" style="margin-top:.55rem;display:none;">
                                    <!-- injected by JS -->
                                </div>
                                <span id="inv_serial_match_hint" class="sn-match-hint" style="display:none;margin-top:.3rem;"></span>
                            </div>

                            <div class="enc-field">
                                <label>Quantity <span class="req">*</span></label>
                                <input type="number" name="quantity" id="inv_quantity" min="1" placeholder="Auto-counted from serials" required oninput="calcTotalAmount()">
                                <span id="inv_qty_serial_note" style="font-size:.74rem;color:#696cff;margin-top:.25rem;display:none;"></span>
                            </div>
                        </div>

                        <div class="enc-section-label">Financial Details</div>
                        <div class="enc-row">
                            <div class="enc-field">
                                <label>Unit Cost (₱)</label>
                                <input type="number" name="value_amount" id="inv_value_amount" min="0" step="0.01" placeholder="0" oninput="calcTotalAmount()">
                            </div>
                            <div class="enc-field">
                                <label>Total Cost (₱) <span style="font-size:.7rem;font-weight:400;color:#8592a3;">(Value × Qty — auto)</span></label>
                                <input type="number" name="total_amount" id="inv_total_amount" min="0" step="0.01" placeholder="0.00" readonly
                                    style="background:#f0f1ff;border-color:#c5c7ff;color:#696cff;font-weight:600;cursor:default;">
                            </div>
                        </div>

                        <div class="enc-section-label">Delivery & Status</div>
                        <div class="enc-row">
                            <div class="enc-field">
                                <label>Date Delivered <span class="req">*</span></label>
                                <input type="date" name="date_delivered" required>
                            </div>
                            <div class="enc-field">
                                <label>Item Status</label>
                                <select name="item_status">
                                    <option value="Active" selected>Active</option>
                                    <option value="Inactive">Inactive</option>
                                    <option value="Returned">Returned</option>
                                    <option value="Defective">Defective</option>
                                    <option value="Replaced">Replaced</option>
                                </select>
                            </div>
                        </div>

                        <div class="mt-3">
                            <button type="submit" class="enc-submit">
                                <i class="bx bx-save"></i> Save Inventory Item
                            </button>
                        </div>
                    </form>
                </div>

                <!-- ══════════════════════════════════════════════════════════
                     INVENTORY DATA TABLE — below the form
                     ══════════════════════════════════════════════════════ -->
                <div class="enc-card-header" style="margin-top:1.5rem;border-top:1.5px solid #e7e8f0;padding-top:1.1rem;">
                    <i class="bx bx-table"></i>
                    <h5 style="margin:0;">Inventory Items — All Records
                        <span style="font-size:.76rem;font-weight:400;color:#8592a3;margin-left:.5rem;">(<?= count($inv_table_rows) ?> rows)</span>
                    </h5>
                </div>

                <!-- Search / filter bar -->
                <div style="display:flex;gap:.6rem;flex-wrap:wrap;align-items:center;margin:.75rem 0 .6rem;">
                    <input type="text" id="inv_tbl_search"
                        placeholder="🔍  Search item name, particulars, serial, ARE/PAR…"
                        oninput="filterInvTable()"
                        style="flex:1;min-width:200px;padding:.48rem .75rem;border:1.5px solid #d9dee3;border-radius:.45rem;font-size:.85rem;color:#566a7f;background:#f8f8fb;outline:none;">
                    <select id="inv_tbl_status_filter" onchange="filterInvTable()"
                        style="padding:.45rem .65rem;border:1.5px solid #d9dee3;border-radius:.45rem;font-size:.85rem;color:#566a7f;background:#f8f8fb;outline:none;">
                        <option value="">All Statuses</option>
                        <option value="Active">Active</option>
                        <option value="Inactive">Inactive</option>
                        <option value="Returned">Returned</option>
                        <option value="Defective">Defective</option>
                        <option value="Replaced">Replaced</option>
                    </select>
                </div>

                <div style="overflow-x:auto;border-radius:.55rem;border:1.5px solid #e7e8f0;">
                    <table class="inv-data-table" id="inv_data_table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Item Name</th>
                                <th>Particulars</th>
                                <th>ARE / PAR / ICS No.</th>
                                <th>Serial No.</th>
                                <th>Property No.</th>
                                <th>Qty</th>
                                <th>Unit Cost (₱)</th>
                                <th>Total (₱)</th>
                                <th>Date Delivered</th>
                                <th>Status</th>
                                <th>Last Updated</th>
                                <th style="width:70px;">Edit</th>
                            </tr>
                        </thead>
                        <tbody id="inv_tbl_body">
                            <?php foreach ($inv_table_rows as $tr): ?>
                                <tr
                                    data-id="<?= (int)$tr['id'] ?>"
                                    data-item-name="<?= htmlspecialchars($tr['item_name']) ?>"
                                    data-particulars="<?= htmlspecialchars($tr['particulars']) ?>"
                                    data-are="<?= htmlspecialchars($tr['are_mr_ics_num'] ?? '') ?>"
                                    data-serial="<?= htmlspecialchars($tr['serial_number'] ?? '') ?>"
                                    data-prop="<?= htmlspecialchars($tr['property_number'] ?? '') ?>"
                                    data-qty="<?= (int)$tr['quantity'] ?>"
                                    data-value="<?= htmlspecialchars($tr['value_amount']) ?>"
                                    data-total="<?= htmlspecialchars($tr['total_amount']) ?>"
                                    data-delivered="<?= htmlspecialchars($tr['date_delivered']) ?>"
                                    data-status="<?= htmlspecialchars($tr['item_status'] ?? 'Active') ?>">
                                    <td class="inv-tbl-id">#<?= (int)$tr['id'] ?></td>
                                    <td class="inv-tbl-name"><?= htmlspecialchars($tr['item_name']) ?></td>
                                    <td class="inv-tbl-part" title="<?= htmlspecialchars($tr['particulars']) ?>"><?= htmlspecialchars($tr['particulars']) ?></td>
                                    <td class="inv-tbl-mono" title="<?= htmlspecialchars($tr['are_mr_ics_num'] ?? '') ?>">
                                        <?php
                                        $are_parts = array_filter(array_map('trim', explode('/', $tr['are_mr_ics_num'] ?? '')));
                                        foreach ($are_parts as $ap): ?>
                                            <span class="inv-token-chip"><?= htmlspecialchars($ap) ?></span>
                                        <?php endforeach; ?>
                                    </td>
                                    <td class="inv-tbl-mono" title="<?= htmlspecialchars($tr['serial_number'] ?? '') ?>">
                                        <?php
                                        $sn_parts = array_filter(array_map('trim', explode('/', $tr['serial_number'] ?? '')));
                                        foreach ($sn_parts as $sp): ?>
                                            <span class="inv-token-chip"><?= htmlspecialchars($sp) ?></span>
                                        <?php endforeach; ?>
                                    </td>
                                    <td class="inv-tbl-mono" title="<?= htmlspecialchars($tr['property_number'] ?? '') ?>">
                                        <?php
                                        $pn_parts = array_filter(array_map('trim', explode('/', $tr['property_number'] ?? '')));
                                        foreach ($pn_parts as $pp): ?>
                                            <span class="inv-token-chip"><?= htmlspecialchars($pp) ?></span>
                                        <?php endforeach; ?>
                                    </td>
                                    <td class="inv-tbl-qty"><?= (int)$tr['quantity'] ?></td>
                                    <td class="inv-tbl-amt"><?= number_format((float)$tr['value_amount'], 2) ?></td>
                                    <td class="inv-tbl-amt"><?= number_format((float)$tr['total_amount'], 2) ?></td>
                                    <td class="inv-tbl-date"><?= htmlspecialchars($tr['date_delivered']) ?></td>
                                    <td>
                                        <?php
                                        $stBadge = ['Active' => '#28c76f', 'Inactive' => '#8592a3', 'Returned' => '#696cff', 'Defective' => '#ea5455', 'Replaced' => '#ff9f43'];
                                        $st = $tr['item_status'] ?? 'Active';
                                        $stCol = $stBadge[$st] ?? '#8592a3';
                                        ?>
                                        <span style="display:inline-block;padding:.18rem .55rem;border-radius:3rem;font-size:.72rem;font-weight:600;color:#fff;background:<?= $stCol ?>;white-space:nowrap;"><?= htmlspecialchars($st) ?></span>
                                    </td>
                                    <td class="inv-tbl-date" style="font-size:.76rem;color:#8592a3;"><?= htmlspecialchars($tr['date_updated'] ?? '') ?></td>
                                    <td>
                                        <button type="button" class="inv-tbl-edit-btn"
                                            onclick="openInvEditModal(this.closest('tr'))"
                                            title="Edit this record">
                                            <i class="bx bx-edit-alt"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($inv_table_rows)): ?>
                                <tr>
                                    <td colspan="13" style="text-align:center;color:#8592a3;padding:1.5rem;">No inventory items found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- ══════════════════════════════════════════════════════════════════
                 EDIT INVENTORY ITEM MODAL
                 ══════════════════════════════════════════════════════════════════ -->
            <div id="inv_edit_overlay" class="inv-edit-overlay" style="display:none;" onclick="if(event.target===this)closeInvEditModal()">
                <div class="inv-edit-modal">
                    <div class="inv-edit-modal-header">
                        <span><i class="bx bx-edit-alt" style="vertical-align:middle;margin-right:.35rem;"></i>Edit Inventory Item <strong id="inv_edit_modal_id"></strong></span>
                        <button type="button" onclick="closeInvEditModal()" style="background:none;border:none;font-size:1.3rem;cursor:pointer;color:#566a7f;line-height:1;">✕</button>
                    </div>
                    <form method="POST" action="" autocomplete="off" id="inv_edit_form">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                        <input type="hidden" name="form_token" value="<?= htmlspecialchars($form_token_value) ?>">
                        <input type="hidden" name="action" value="update_inventory_item">
                        <input type="hidden" name="inv_id" id="inv_edit_id">

                        <div class="inv-edit-grid">
                            <div class="inv-edit-field inv-edit-full">
                                <label>Item Name <span class="req">*</span></label>
                                <input type="text" name="item_name" id="inv_edit_item_name" required maxlength="255">
                            </div>
                            <div class="inv-edit-field inv-edit-full">
                                <label>Particulars <span class="req">*</span></label>
                                <textarea name="particulars" id="inv_edit_particulars" required maxlength="255" rows="2" style="resize:vertical;"></textarea>
                            </div>
                            <div class="inv-edit-field inv-edit-full">
                                <label>ARE / PAR / ICS Number
                                    <span style="font-size:.72rem;font-weight:400;color:#8592a3;">(separate multiple with /)</span>
                                </label>
                                <textarea name="are_mr_ics_num" id="inv_edit_are" rows="2" style="font-family:monospace;font-size:.85rem;resize:vertical;"></textarea>
                            </div>
                            <div class="inv-edit-field inv-edit-full">
                                <label>Serial Number
                                    <span style="font-size:.72rem;font-weight:400;color:#8592a3;">(separate multiple with /)</span>
                                </label>
                                <textarea name="serial_number" id="inv_edit_serial" rows="2" style="font-family:monospace;font-size:.85rem;resize:vertical;"></textarea>
                            </div>
                            <div class="inv-edit-field inv-edit-full">
                                <label>Property Number
                                    <span style="font-size:.72rem;font-weight:400;color:#8592a3;">(separate multiple with /)</span>
                                </label>
                                <textarea name="property_number" id="inv_edit_prop" rows="2" style="font-family:monospace;font-size:.85rem;resize:vertical;"></textarea>
                            </div>
                            <div class="inv-edit-field">
                                <label>Quantity <span class="req">*</span></label>
                                <input type="number" name="quantity" id="inv_edit_qty" min="0" required oninput="invEditCalcTotal()">
                            </div>
                            <div class="inv-edit-field">
                                <label>Unit Cost (₱)</label>
                                <input type="number" name="value_amount" id="inv_edit_value" min="0" step="0.01" oninput="invEditCalcTotal()">
                            </div>
                            <div class="inv-edit-field">
                                <label>Total Cost (₱) <span style="font-size:.7rem;font-weight:400;color:#8592a3;">(auto)</span></label>
                                <input type="number" name="total_amount" id="inv_edit_total" readonly
                                    style="background:#f0f1ff;border-color:#c5c7ff;color:#696cff;font-weight:600;cursor:default;">
                            </div>
                            <div class="inv-edit-field">
                                <label>Date Delivered <span class="req">*</span></label>
                                <input type="date" name="date_delivered" id="inv_edit_delivered" required>
                            </div>
                            <div class="inv-edit-field">
                                <label>Item Status</label>
                                <select name="item_status" id="inv_edit_status">
                                    <option value="Active">Active</option>
                                    <option value="Inactive">Inactive</option>
                                    <option value="Returned">Returned</option>
                                    <option value="Defective">Defective</option>
                                    <option value="Replaced">Replaced</option>
                                </select>
                            </div>
                        </div>

                        <div class="inv-edit-modal-footer">
                            <button type="button" onclick="closeInvEditModal()"
                                style="padding:.52rem 1.1rem;border:1.5px solid #d9dee3;border-radius:.45rem;background:#fff;color:#566a7f;cursor:pointer;font-size:.9rem;">
                                Cancel
                            </button>
                            <button type="submit" class="enc-submit" style="margin:0;padding:.52rem 1.4rem;">
                                <i class="bx bx-save"></i> Save Changes
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- ══════════════════════════════════════════════════════════════════
             TAB 2 · BORROWED ITEM
             ══════════════════════════════════════════════════════════════════ -->
            <div class="enc-card" id="tab-borrow">
                <div class="enc-card-header">
                    <i class="bx bx-transfer"></i>
                    <h5>Add Borrowed Item Record</h5>
                </div>
                <div class="enc-card-body">
                    <form method="POST" action="" autocomplete="off">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                        <input type="hidden" name="action" value="add_borrowed_item">
                        <input type="hidden" name="form_token" value="<?= htmlspecialchars($form_token_value) ?>">

                        <div class="enc-section-label">Core Details</div>
                        <div class="enc-row">
                            <!-- accountable_id and reference_no resolved automatically -->
                            <input type="hidden" name="accountable_id" id="borrow_accountable_id">
                            <input type="hidden" name="reference_no" id="borrow_reference_no_hidden" value="<?= htmlspecialchars($borrow_ref_no) ?>">

                            <!-- 1. FROM PERSON ─────────────────────────────── -->
                            <div class="enc-field">
                                <label>From Person <span class="req">*</span></label>
                                <div class="emp-autocomplete-wrap" data-autocomplete="from_person">
                                    <input type="text" class="emp-search-input"
                                        placeholder="Search employee name…"
                                        autocomplete="off" maxlength="255">
                                    <button type="button" class="emp-clear-btn" title="Clear">✕</button>
                                    <div class="emp-dropdown"></div>
                                    <input type="hidden" name="from_person" id="borrow_from_person_hidden" required>
                                </div>
                                <div class="emp-selected-chip" data-chip="from_person">
                                    <i class="bx bx-user-check"></i>
                                    <span class="chip-label"></span>
                                    <button type="button" class="chip-remove" title="Remove">✕</button>
                                </div>
                            </div>

                            <!-- 2. INVENTORY ITEM (filtered by from_person) ── -->
                            <div class="enc-field">
                                <label>Inventory Item <span class="req">*</span></label>
                                <select name="inventory_item_id" id="borrow_inventory_item_id" required disabled>
                                    <option value="">— Select From Person first —</option>
                                </select>
                                <span id="borrow_item_hint" style="font-size:.76rem;color:#a1b0be;margin-top:.2rem;">
                                    Select a From Person to load their assigned items.
                                </span>
                            </div>

                            <!-- 3. TO PERSON (BORROWER) ─────────────────── -->
                            <div class="enc-field">
                                <label>To Person (Borrower) <span class="req">*</span></label>
                                <div class="emp-autocomplete-wrap" data-autocomplete="to_person">
                                    <input type="text" class="emp-search-input"
                                        placeholder="Search employee name…"
                                        autocomplete="off" maxlength="255">
                                    <button type="button" class="emp-clear-btn" title="Clear">✕</button>
                                    <div class="emp-dropdown"></div>
                                    <input type="hidden" name="to_person" required>
                                </div>
                                <div class="emp-selected-chip" data-chip="to_person">
                                    <i class="bx bx-user-check"></i>
                                    <span class="chip-label"></span>
                                    <button type="button" class="chip-remove" title="Remove">✕</button>
                                </div>
                            </div>

                            <!-- 4. BORROWER EMPLOYEE ID ──────────────────── -->
                            <div class="enc-field">
                                <label>Borrower Employee ID</label>
                                <input type="number" name="borrower_employee_id" id="borrow_borrower_employee_id"
                                    min="1" placeholder="Auto-filled on selection">
                            </div>

                            <!-- 5. QUANTITY ──────────────────────────────── -->
                            <div class="enc-field">
                                <label>Quantity <span class="req">*</span></label>
                                <input type="number" name="quantity" id="borrow_quantity"
                                    min="1" placeholder="Select item first" required disabled>
                                <span id="borrow_qty_hint" style="font-size:.76rem;color:#696cff;margin-top:.2rem;display:none;"></span>
                            </div>
                        </div>

                        <div class="enc-section-label">Dates & Status</div>
                        <div class="enc-row">
                            <div class="enc-field">
                                <label>Borrow Date</label>
                                <input type="datetime-local" name="borrow_date"
                                    value="<?= date('Y-m-d\TH:i') ?>">
                            </div>
                            <div class="enc-field">
                                <label>Expected Return Date</label>
                                <input type="datetime-local" name="return_date">
                            </div>
                            <div class="enc-field">
                                <label>Status</label>
                                <select name="status">
                                    <option value="PENDING" selected>PENDING</option>
                                    <option value="APPROVED">APPROVED</option>
                                    <option value="DENIED">DENIED</option>
                                    <option value="CANCELLED">CANCELLED</option>
                                    <option value="RETURN_PENDING">RETURN_PENDING</option>
                                    <option value="RETURNED">RETURNED</option>
                                </select>
                                <span style="font-size:.76rem;color:#8592a3;margin-top:.3rem;line-height:1.4;">
                                    <i class="bx bx-info-circle" style="vertical-align:middle;color:#696cff;"></i>
                                    Encoder sets initial status. Final approval is reviewed and confirmed
                                    by the <strong>Admin</strong> and <strong>Property Custodian</strong>
                                    before the item is released.
                                </span>
                            </div>
                        </div>

                        <div class="enc-section-label">Reference &amp; Tracking</div>
                        <div class="enc-row">
                            <div class="enc-field">
                                <label>Reference No. <span style="font-size:.7rem;font-weight:400;color:#8592a3;">(auto-generated)</span></label>
                                <div style="display:flex;align-items:center;gap:.5rem;">
                                    <input type="text" id="borrow_ref_display"
                                        value="<?= htmlspecialchars($borrow_ref_no) ?>"
                                        readonly
                                        style="background:#f0f1ff;border-color:#c5c7ff;color:#696cff;font-family:monospace;font-size:.85rem;font-weight:600;cursor:default;flex:1;">
                                    <button type="button" id="borrow_ref_copy_btn" title="Copy reference number"
                                        style="padding:.5rem .7rem;border:1.5px solid #696cff;border-radius:.45rem;background:#fff;color:#696cff;cursor:pointer;font-size:.8rem;white-space:nowrap;">
                                        <i class="bx bx-copy"></i> Copy
                                    </button>
                                </div>
                                <span style="font-size:.74rem;color:#8592a3;margin-top:.25rem;">
                                    This reference number is automatically assigned and
                                    can be used by <strong>Admin</strong> and <strong>Manager</strong>
                                    roles to quickly locate this borrow record.
                                </span>
                            </div>
                            <div class="enc-field enc-field-full">
                                <label>ARE / MR / ICS Number
                                    <span id="borrow_are_req_note" style="font-size:.72rem;font-weight:400;color:#8592a3;display:none;">
                                        — <strong id="borrow_are_checked_count">0</strong> checked
                                    </span>
                                </label>
                                <!-- Hidden input carries the joined checked values to PHP -->
                                <input type="hidden" name="are_mr_ics_num" id="borrow_are_mr_ics_hidden">
                                <!-- Checklist populated by JS when inventory item is selected -->
                                <div id="borrow_are_checklist" class="serial-checklist-wrap">
                                    <span class="serial-placeholder">Select a From Person and inventory item to load ARE / MR / ICS numbers.</span>
                                </div>
                                <span class="sn-match-hint" id="borrow_are_hint" style="display:none;margin-top:.3rem;"></span>
                            </div>
                            <div class="enc-field enc-field-full">
                                <label>Property Number
                                    <span id="borrow_prop_req_note" style="font-size:.72rem;font-weight:400;color:#8592a3;display:none;">
                                        — <strong id="borrow_prop_checked_count">0</strong> checked
                                    </span>
                                </label>
                                <!-- Hidden input carries the joined checked values to PHP -->
                                <input type="hidden" name="property_number" id="borrow_property_number_hidden">
                                <!-- Checklist populated by JS when inventory item is selected -->
                                <div id="borrow_prop_checklist" class="serial-checklist-wrap">
                                    <span class="serial-placeholder">Select a From Person and inventory item to load property numbers.</span>
                                </div>
                                <span class="sn-match-hint" id="borrow_prop_hint" style="display:none;margin-top:.3rem;"></span>
                            </div>
                            <div class="enc-field enc-field-full">
                                <label>Serial Number <span id="borrow_sn_req_note" style="font-size:.72rem;font-weight:400;color:#8592a3;display:none;">— select exactly <strong id="borrow_sn_req_count">0</strong> to match quantity</span></label>
                                <!-- Hidden input carries the joined value on submit -->
                                <input type="hidden" name="serial_number" id="borrow_serial_number_hidden">
                                <!-- Dynamic checklist rendered by JS when an inventory item is selected -->
                                <div id="borrow_serial_checklist" class="serial-checklist-wrap">
                                    <span class="serial-placeholder">Select an inventory item to load serial numbers.</span>
                                </div>
                                <span class="sn-match-hint" id="borrow_serial_hint" style="display:none;margin-top:.3rem;"></span>
                            </div>
                            <div class="enc-field">
                                <label>PO Number</label>
                                <input type="text" name="po_number" maxlength="70" placeholder="Optional">
                            </div>
                            <div class="enc-field">
                                <label>Account Code</label>
                                <input type="text" name="account_code" maxlength="50" placeholder="Optional">
                            </div>
                            <div class="enc-field">
                                <label>Old Account Code</label>
                                <input type="text" name="old_account_code" maxlength="50" placeholder="Optional">
                            </div>
                        </div>

                        <div class="enc-section-label">Remarks</div>
                        <div class="enc-row">
                            <div class="enc-field enc-field-full">
                                <label>Remarks</label>
                                <textarea name="remarks" placeholder="Any additional notes…"></textarea>
                            </div>
                        </div>

                        <div class="mt-3">
                            <button type="submit" class="enc-submit">
                                <i class="bx bx-save"></i> Save Borrow Record
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- ══════════════════════════════════════════════════════════════════
             TAB 3 · ACCOUNTABLE ITEM
             ══════════════════════════════════════════════════════════════════ -->
            <div class="enc-card" id="tab-account">
                <div class="enc-card-header">
                    <i class="bx bx-user-pin"></i>
                    <h5>Add Accountable Item Record</h5>
                </div>
                <div class="enc-card-body">
                    <form method="POST" action="" autocomplete="off">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                        <input type="hidden" name="action" value="add_accountable_item">
                        <input type="hidden" name="form_token" value="<?= htmlspecialchars($form_token_value) ?>">

                        <div class="enc-section-label">Assignment Details</div>
                        <div class="enc-row">
                            <!-- Hidden: resolved inventory_item_id (set by JS when item_name + particulars match a DB row) -->
                            <input type="hidden" name="inventory_item_id" id="acct_inventory_item_id_hidden">
                            <div class="enc-field">
                                <label>Inventory Item <span class="req">*</span></label>
                                <select id="acct_item_name_select" required>
                                    <option value="">— Select Item Category —</option>
                                    <?php foreach ($acct_item_names as $name): ?>
                                        <option value="<?= htmlspecialchars($name, ENT_QUOTES) ?>">
                                            <?= htmlspecialchars($name) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <span id="acct_item_resolve_hint" style="font-size:.74rem;margin-top:.2rem;display:none;"></span>
                            </div>
                            <div class="enc-field">
                                <label>Particulars <span class="req">*</span>
                                    <!-- <span style="font-size:.72rem;font-weight:400;color:#8592a3;"></span> -->
                                </label>
                                <div style="position:relative;">
                                    <input type="text" name="particulars" id="acct_particulars"
                                        placeholder="Select an inventory item first…"
                                        maxlength="255" autocomplete="off" disabled>
                                    <div id="acct_particulars_dropdown" class="emp-dropdown" style="top:100%;left:0;right:0;z-index:200;"></div>
                                </div>
                            </div>
                            <div class="enc-field">
                                <label>Person Name <span class="req">*</span></label>
                                <div class="emp-autocomplete-wrap" data-autocomplete="person_name">
                                    <input type="text" class="emp-search-input"
                                        placeholder="Search employee name…"
                                        autocomplete="off" maxlength="255">
                                    <button type="button" class="emp-clear-btn" title="Clear">✕</button>
                                    <div class="emp-dropdown"></div>
                                    <input type="hidden" name="person_name" required>
                                </div>
                                <div class="emp-selected-chip" data-chip="person_name">
                                    <i class="bx bx-user-check"></i>
                                    <span class="chip-label"></span>
                                    <button type="button" class="chip-remove" title="Remove">✕</button>
                                </div>
                            </div>
                            <div class="enc-field">
                                <label>Employee ID (system)</label>
                                <input type="number" name="employee_id" id="acct_employee_id" min="1" placeholder="Auto-filled on selection">
                            </div>
                            <div class="enc-field">
                                <label>Assigned Quantity <span class="req">*</span></label>
                                <input type="number" name="assigned_quantity" id="acct_assigned_quantity" min="1" placeholder="Select inventory item first" required disabled>
                                <span id="acct_qty_hint" style="font-size:.76rem;color:#696cff;margin-top:.2rem;display:none;"></span>
                            </div>
                            <div class="enc-field">
                                <label>Condition / Status</label>
                                <input type="text" name="condition_status" placeholder="Serviceable" maxlength="50" value="Serviceable">
                            </div>
                        </div>

                        <div class="enc-section-label">Reference Numbers</div>
                        <div class="enc-row">
                            <div class="enc-field enc-field-full">
                                <label>Property Acknowledgement Receipt (PAR)/ Inventory Custodian Slip (ICS) Number
                                    <span id="acct_are_req_note" style="font-size:.72rem;font-weight:400;color:#8592a3;display:none;">
                                        — <strong id="acct_are_checked_count">0</strong> checked
                                        → Assigned Quantity set automatically
                                    </span>
                                </label>
                                <!-- Hidden input carries the joined checked values to PHP -->
                                <input type="hidden" name="are_mr_ics_num" id="acct_are_mr_ics_hidden">
                                <!-- Checklist populated by JS when inventory item is selected -->
                                <div id="acct_are_checklist" class="serial-checklist-wrap">
                                    <span class="serial-placeholder">Select an inventory item to load its ARE / MR / ICS numbers.</span>
                                </div>
                                <!-- Live match hint — mirrors the serial match hint pattern -->
                                <span class="sn-match-hint" id="acct_are_match_hint" style="display:none;margin-top:.3rem;"></span>
                            </div>
                            <div class="enc-field enc-field-full">
                                <label>Property Number
                                    <span id="acct_prop_req_note" style="font-size:.72rem;font-weight:400;color:#8592a3;display:none;">
                                        — <strong id="acct_prop_checked_count">0</strong> checked
                                        → must match quantity &amp; serial count
                                    </span>
                                </label>
                                <!-- Hidden input carries the joined checked values to PHP -->
                                <input type="hidden" name="property_number" id="acct_property_number_hidden">
                                <!-- Checklist populated by JS when inventory item is selected -->
                                <div id="acct_prop_checklist" class="serial-checklist-wrap">
                                    <span class="serial-placeholder">Select an inventory item to load its property numbers.</span>
                                </div>
                                <!-- Live match hint -->
                                <span class="sn-match-hint" id="acct_prop_match_hint" style="display:none;margin-top:.3rem;"></span>
                            </div>
                            <div class="enc-field enc-field-full">
                                <label>Serial Number <span class="req">*</span> <span id="acct_sn_req_note" style="font-size:.72rem;font-weight:400;color:#8592a3;display:none;">— select exactly <strong id="acct_sn_req_count">0</strong> to match quantity</span></label>
                                <!-- Hidden input carries the joined checked serials to PHP -->
                                <input type="hidden" name="serial_number" id="acct_serial_number_hidden">
                                <!-- Checklist populated by JS when inventory item is selected -->
                                <div id="acct_serial_checklist" class="serial-checklist-wrap">
                                    <span class="serial-placeholder">Select an inventory item to load its serial numbers.</span>
                                </div>
                                <span class="sn-match-hint" id="acct_sn_match_hint" style="display:none;"></span>
                            </div>
                            <div class="enc-field">
                                <label>PO Number</label>
                                <input type="text" name="po_number" maxlength="70" placeholder="Optional">
                            </div>
                            <div class="enc-field">
                                <label>Account Code</label>
                                <input type="text" name="account_code" maxlength="50" placeholder="Optional">
                            </div>
                            <div class="enc-field">
                                <label>Old Account Code</label>
                                <input type="text" name="old_account_code" maxlength="50" placeholder="Optional">
                            </div>
                        </div>

                        <div class="enc-section-label">Remarks</div>
                        <div class="enc-row">
                            <div class="enc-field enc-field-full">
                                <label>Remarks</label>
                                <input type="text" name="remarks" placeholder="Optional notes" maxlength="255">
                            </div>
                        </div>

                        <div class="mt-3">
                            <button type="submit" class="enc-submit">
                                <i class="bx bx-save"></i> Save Accountable Record
                            </button>
                        </div>
                    </form>
                </div>
            </div>

        </div><!-- /enc-main -->

        <footer class="enc-footer">
            © <?= date('Y') ?> CAO Inventory Management System &mdash; Encoder Module.
            All actions are logged and monitored.
        </footer>

    </div><!-- /encoder-root -->

    <script src="<?= site_url('assets/vendor/libs/jquery/jquery.js') ?>"></script>
    <script>
        /* ════════════════════════════════════════════════════════════════════
         * INVENTORY TAB — Smart cascading fields
         * Data injected from PHP:
         *   INV_ITEM_NAMES : string[]  — unique item names already in DB
         *   INV_ITEM_MAP   : object    — { itemName: [{p, pn, s, v}] }
         *     p  = particulars
         *     pn = property_number
         *     s  = aggregated serial string
         *     v  = value_amount
         * ════════════════════════════════════════════════════════════════════ */
        var INV_ITEM_NAMES = <?= $inv_item_names_json ?>;
        var INV_ITEM_MAP = <?= $inv_item_map_json ?>;

        /* ── Helpers ──────────────────────────────────────────────────────── */
        function parseSerials(raw) {
            return (raw || '').split('/').map(function(s) {
                return s.trim();
            }).filter(Boolean);
        }

        function normStr(s) {
            return (s || '').trim().toLowerCase();
        }

        /* ── Item Name Autocomplete ────────────────────────────────────────── */
        var _invDropdownOpen = false;
        var _invDropFocusIdx = -1;
        var _invDropItems = [];

        function onInvItemNameInput() {
            var input = document.getElementById('inv_item_name');
            if (!input) return;
            var q = normStr(input.value);

            // Filter existing names
            _invDropItems = q.length < 1 ?
                [] :
                INV_ITEM_NAMES.filter(function(n) {
                    return normStr(n).indexOf(q) !== -1;
                }).slice(0, 30);

            renderInvNameDropdown();
            updateInvParticulars(); // cascade even while typing (for exact matches)
        }

        function renderInvNameDropdown() {
            var dd = document.getElementById('inv_name_dropdown');
            if (!dd) return;
            dd.innerHTML = '';
            _invDropFocusIdx = -1;

            if (!_invDropItems.length) {
                dd.classList.remove('open');
                _invDropdownOpen = false;
                return;
            }

            _invDropItems.forEach(function(name, idx) {
                var el = document.createElement('div');
                el.className = 'inv-name-drop-item';
                el.textContent = name;
                el.addEventListener('mousedown', function(e) {
                    e.preventDefault();
                    selectInvItemName(name);
                });
                dd.appendChild(el);
            });
            dd.classList.add('open');
            _invDropdownOpen = true;
        }

        function selectInvItemName(name) {
            var input = document.getElementById('inv_item_name');
            if (input) input.value = name;
            closeInvNameDropdown();
            updateInvParticulars();
        }

        function closeInvNameDropdown() {
            var dd = document.getElementById('inv_name_dropdown');
            if (dd) dd.classList.remove('open');
            _invDropdownOpen = false;
            _invDropFocusIdx = -1;
            // Also cascade so if user typed manually (no click) we still update
            updateInvParticulars();
        }

        // Keyboard navigation on item name input
        document.addEventListener('DOMContentLoaded', function() {
            var input = document.getElementById('inv_item_name');
            if (!input) return;
            input.addEventListener('keydown', function(e) {
                var dd = document.getElementById('inv_name_dropdown');
                var items = dd ? dd.querySelectorAll('.inv-name-drop-item') : [];
                if (!items.length) return;
                if (e.key === 'ArrowDown') {
                    e.preventDefault();
                    if (_invDropFocusIdx >= 0) items[_invDropFocusIdx].classList.remove('focused');
                    _invDropFocusIdx = Math.min(_invDropFocusIdx + 1, items.length - 1);
                    items[_invDropFocusIdx].classList.add('focused');
                    items[_invDropFocusIdx].scrollIntoView({
                        block: 'nearest'
                    });
                } else if (e.key === 'ArrowUp') {
                    e.preventDefault();
                    if (_invDropFocusIdx >= 0) items[_invDropFocusIdx].classList.remove('focused');
                    _invDropFocusIdx = Math.max(_invDropFocusIdx - 1, 0);
                    items[_invDropFocusIdx].classList.add('focused');
                    items[_invDropFocusIdx].scrollIntoView({
                        block: 'nearest'
                    });
                } else if (e.key === 'Enter' && _invDropFocusIdx >= 0 && _invDropItems[_invDropFocusIdx]) {
                    e.preventDefault();
                    selectInvItemName(_invDropItems[_invDropFocusIdx]);
                } else if (e.key === 'Escape') {
                    closeInvNameDropdown();
                }
            });
        });

        /* ── Particulars Cascade ──────────────────────────────────────────── *
         * Called whenever item name changes.
         * Populates the particulars autocomplete suggestion pool from INV_ITEM_MAP
         * and shows a contextual hint. The input stays editable at all times —
         * the encoder can type a new value and it will be added to the DB on save.
         */
        function updateInvParticulars() {
            var nameInput = document.getElementById('inv_item_name');
            var partInput = document.getElementById('inv_particulars_input');
            var hidEl = document.getElementById('inv_particulars_hidden');
            var hintEl = document.getElementById('inv_particulars_hint');
            var statusEl = document.getElementById('inv_name_status');
            if (!nameInput || !partInput || !hidEl) return;

            var typed = nameInput.value.trim();
            var key = normStr(typed);

            // Find matching key in INV_ITEM_MAP (case-insensitive)
            var matchKey = null;
            Object.keys(INV_ITEM_MAP).forEach(function(k) {
                if (normStr(k) === key) matchKey = k;
            });

            // Clear particulars input and cascaded fields whenever item name changes
            partInput.value = '';
            hidEl.value = '';
            _invPartPool = matchKey ? INV_ITEM_MAP[matchKey] : [];
            _invPartMatchedRec = null;
            updateInvSerialRef('');
            clearInvValueAmount();
            clearInvPropertyNumber();
            closeInvPartDropdown();

            if (matchKey) {
                var recs = INV_ITEM_MAP[matchKey];
                if (statusEl) {
                    statusEl.textContent = '✓ Existing item — ' + recs.length + ' particular(s) on record. Select one or type a new value.';
                    statusEl.style.color = '#696cff';
                    statusEl.style.display = 'block';
                }
                if (hintEl) {
                    hintEl.textContent = 'Selecting an existing particular auto-fills the value amount and shows registered serial numbers. You may also type a new particular — it will be saved to the database.';
                    hintEl.style.display = 'block';
                }
            } else {
                if (statusEl) {
                    if (typed) {
                        statusEl.textContent = '✦ New item name — will be added to the database.';
                        statusEl.style.color = '#28c76f';
                        statusEl.style.display = 'block';
                    } else {
                        statusEl.style.display = 'none';
                    }
                }
                if (hintEl) hintEl.style.display = 'none';
            }
        }

        /* ── Particulars Autocomplete ──────────────────────────────────────── *
         * _invPartPool    : array of {p, s, v} records for the current item name
         * _invPartDropItems : filtered subset shown in the dropdown
         * _invPartFocusIdx  : keyboard-focused row index (−1 = none)
         * _invPartMatchedRec: the DB record whose particulars exactly match the
         *                     current input value — null if none match exactly.
         */
        var _invPartPool = [];
        var _invPartDropItems = [];
        var _invPartFocusIdx = -1;
        var _invPartMatchedRec = null;

        /* Existing tokens loaded from DB when a particular is selected.
           These are shown as disabled (uncheckable) in their checklists. */
        var _invExistingPropNums = [];
        var _invExistingAreMrIcs = [];
        var _invExistingSerials  = [];

        function onInvParticularsInput() {
            var input = document.getElementById('inv_particulars_input');
            var hidEl = document.getElementById('inv_particulars_hidden');
            var hintEl = document.getElementById('inv_particulars_hint');
            if (!input || !hidEl) return;

            var q = input.value;
            hidEl.value = q.trim(); // always keep hidden in sync

            var qNorm = normStr(q);

            // Filter the pool: empty query → show all; otherwise substring match
            _invPartDropItems = qNorm.length < 1 ?
                _invPartPool.slice() :
                _invPartPool.filter(function(r) {
                    return normStr(r.p).indexOf(qNorm) !== -1;
                });

            // Exact-match check for auto-fill (case-insensitive)
            _invPartMatchedRec = null;
            var exact = null;
            for (var i = 0; i < _invPartPool.length; i++) {
                if (normStr(_invPartPool[i].p) === qNorm) {
                    exact = _invPartPool[i];
                    break;
                }
            }
            if (exact) {
                _invPartMatchedRec = exact;
                updateInvSerialRef(exact.s);
                setInvValueAmount(exact.v);
                setInvPropertyNumber(exact.pn);
                setInvAreMrIcs(exact.are || '');
                if (hintEl) {
                    hintEl.textContent = '✓ Existing particular — value amount and property number auto-filled.';
                    hintEl.style.color = '#696cff';
                    hintEl.style.display = 'block';
                }
            } else {
                updateInvSerialRef('');
                clearInvValueAmount();
                clearInvPropertyNumber();
                clearInvAreMrIcs();
                if (hintEl && q.trim().length > 0) {
                    hintEl.textContent = '✦ New particular — will be added to the database on save.';
                    hintEl.style.color = '#28c76f';
                    hintEl.style.display = 'block';
                } else if (hintEl) {
                    hintEl.style.display = 'none';
                }
            }

            renderInvPartDropdown();
        }

        function renderInvPartDropdown() {
            var dd = document.getElementById('inv_part_dropdown');
            if (!dd) return;
            dd.innerHTML = '';
            _invPartFocusIdx = -1;

            if (!_invPartDropItems.length) {
                dd.classList.remove('open');
                return;
            }

            _invPartDropItems.forEach(function(rec, idx) {
                var el = document.createElement('div');
                el.className = 'inv-part-drop-item';
                el.dataset.idx = idx;

                // Particulars label (left side)
                var label = document.createElement('span');
                label.textContent = rec.p;
                el.appendChild(label);

                // Value amount badge (right side) — only if non-zero
                if (rec.v > 0) {
                    var meta = document.createElement('span');
                    meta.className = 'inv-part-drop-meta';
                    meta.textContent = '₱' + rec.v.toLocaleString('en-PH', {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2
                    });
                    el.appendChild(meta);
                }

                el.addEventListener('mousedown', function(e) {
                    e.preventDefault();
                    selectInvParticular(rec);
                });
                dd.appendChild(el);
            });

            dd.classList.add('open');
        }

        function selectInvParticular(rec) {
            var input = document.getElementById('inv_particulars_input');
            var hidEl = document.getElementById('inv_particulars_hidden');
            var hintEl = document.getElementById('inv_particulars_hint');
            if (input) input.value = rec.p;
            if (hidEl) hidEl.value = rec.p;
            _invPartMatchedRec = rec;
            closeInvPartDropdown();
            updateInvSerialRef(rec.s);
            setInvValueAmount(rec.v);
            setInvPropertyNumber(rec.pn);
            setInvAreMrIcs(rec.are || '');
            if (hintEl) {
                hintEl.textContent = '✓ Existing particular selected — value amount and property number auto-filled.';
                hintEl.style.color = '#696cff';
                hintEl.style.display = 'block';
            }
        }

        function closeInvPartDropdown() {
            var dd = document.getElementById('inv_part_dropdown');
            if (dd) dd.classList.remove('open');
            _invPartFocusIdx = -1;
            // Ensure hidden stays in sync with whatever is in the visible input
            var input = document.getElementById('inv_particulars_input');
            var hidEl = document.getElementById('inv_particulars_hidden');
            if (input && hidEl) hidEl.value = input.value.trim();
        }

        // Keyboard navigation on particulars input
        document.addEventListener('DOMContentLoaded', function() {
            var partInput = document.getElementById('inv_particulars_input');
            if (!partInput) return;

            // Open dropdown on focus if pool has entries and field is empty
            partInput.addEventListener('focus', function() {
                if (!partInput.value.trim() && _invPartPool.length) {
                    _invPartDropItems = _invPartPool.slice();
                    renderInvPartDropdown();
                }
            });

            partInput.addEventListener('keydown', function(e) {
                var dd = document.getElementById('inv_part_dropdown');
                var items = dd ? dd.querySelectorAll('.inv-part-drop-item') : [];
                if (!items.length) return;
                if (e.key === 'ArrowDown') {
                    e.preventDefault();
                    if (_invPartFocusIdx >= 0) items[_invPartFocusIdx].classList.remove('focused');
                    _invPartFocusIdx = Math.min(_invPartFocusIdx + 1, items.length - 1);
                    items[_invPartFocusIdx].classList.add('focused');
                    items[_invPartFocusIdx].scrollIntoView({
                        block: 'nearest'
                    });
                } else if (e.key === 'ArrowUp') {
                    e.preventDefault();
                    if (_invPartFocusIdx >= 0) items[_invPartFocusIdx].classList.remove('focused');
                    _invPartFocusIdx = Math.max(_invPartFocusIdx - 1, 0);
                    items[_invPartFocusIdx].classList.add('focused');
                    items[_invPartFocusIdx].scrollIntoView({
                        block: 'nearest'
                    });
                } else if (e.key === 'Enter' && _invPartFocusIdx >= 0 && _invPartDropItems[_invPartFocusIdx]) {
                    e.preventDefault();
                    selectInvParticular(_invPartDropItems[_invPartFocusIdx]);
                } else if (e.key === 'Escape') {
                    closeInvPartDropdown();
                }
            });
        });

        /* ── Existing Serials Reference Panel ────────────────────────────── *
         * Stores DB serials as _invExistingSerials and rebuilds the unified
         * serial checklist so the encoder sees existing (disabled) entries.
         * serialStr = aggregated "321 / 654 / 654asd" string from DB
         */
        function updateInvSerialRef(serialStr) {
            _invExistingSerials = serialStr ? parseSerials(serialStr) : [];
            buildInvSerialChecklist();
        }

        /* ── Value Amount auto-fill helpers ──────────────────────────────── */
        function setInvValueAmount(amount) {
            var el = document.getElementById('inv_value_amount');
            if (!el) return;
            if (amount > 0) {
                el.value = amount.toFixed(2);
                el.style.borderColor = '#696cff';
                el.style.background = '#f0f1ff';
            }
            calcTotalAmount();
        }

        function clearInvValueAmount() {
            var el = document.getElementById('inv_value_amount');
            if (!el) return;
            el.value = '';
            el.style.borderColor = '';
            el.style.background = '';
            calcTotalAmount();
        }

        /* ── Property Number auto-fill helpers ───────────────────────────── */
        function setInvPropertyNumber(pn) {
            var ta = document.getElementById('inv_prop_num_input');
            var hintEl = document.getElementById('inv_property_number_hint');
            if (!ta) return;
            _invExistingPropNums = pn && pn.trim() ? parseSerials(pn) : [];
            if (pn && pn.trim()) {
                ta.value = pn.trim();
                ta.style.borderColor = '#696cff';
                ta.style.background = '#f0f1ff';
                if (hintEl) {
                    hintEl.textContent = '✓ Auto-filled from existing record — existing property numbers are locked; enter new ones to add.';
                    hintEl.style.color = '#696cff';
                    hintEl.style.display = 'block';
                }
            }
            onInvPropNumInput(); // rebuild checkboxes from the new value
        }

        function clearInvPropertyNumber() {
            _invExistingPropNums = [];
            var ta = document.getElementById('inv_prop_num_input');
            var hidden = document.getElementById('inv_property_number_hidden');
            var list = document.getElementById('inv_prop_num_checklist');
            var hint = document.getElementById('inv_prop_num_match_hint');
            var hintEl = document.getElementById('inv_property_number_hint');
            var badge = document.getElementById('inv_prop_num_count');
            if (ta) {
                ta.value = '';
                ta.style.borderColor = '';
                ta.style.background = '';
            }
            if (hidden) hidden.value = '';
            if (list) {
                list.innerHTML = '';
                list.style.display = 'none';
            }
            if (hint) {
                hint.textContent = '';
                hint.style.display = 'none';
            }
            if (hintEl) hintEl.style.display = 'none';
            if (badge) {
                badge.textContent = '0';
                badge.classList.remove('visible');
            }
        }

        /* ── Property Number checklist: parse textarea → render checkboxes ── */
        function onInvPropNumInput() {
            var ta = document.getElementById('inv_prop_num_input');
            var badge = document.getElementById('inv_prop_num_count');
            if (!ta) return;

            var parts = parseSerials(ta.value); // reuse existing "/" parser
            var count = parts.length;

            if (badge) {
                badge.textContent = count;
                badge.classList.toggle('visible', count > 0);
            }

            buildInvPropNumChecklist(parts);
        }

        function buildInvPropNumChecklist(parts) {
            var list = document.getElementById('inv_prop_num_checklist');
            var hidden = document.getElementById('inv_property_number_hidden');
            var hint = document.getElementById('inv_prop_num_match_hint');
            if (!list) return;

            list.innerHTML = '';
            if (hidden) hidden.value = '';
            if (hint) {
                hint.textContent = '';
                hint.style.display = 'none';
            }

            if (!parts || parts.length === 0) {
                list.style.display = 'none';
                return;
            }

            list.style.display = 'flex';

            parts.forEach(function(pn) {
                var isExisting = _invExistingPropNums.indexOf(pn) !== -1;
                var lbl = document.createElement('label');
                lbl.className = 'serial-check-item' + (isExisting ? ' sn-disabled' : '');
                lbl.title = isExisting ? pn + ' (already saved — cannot re-submit)' : pn;

                var cb = document.createElement('input');
                cb.type = 'checkbox';
                cb.value = pn;
                cb.disabled = isExisting;

                if (!isExisting) {
                    cb.addEventListener('change', function() {
                        lbl.classList.toggle('sn-checked', cb.checked);
                        syncInvPropNum();
                    });
                }

                lbl.appendChild(cb);
                lbl.appendChild(document.createTextNode(pn));
                list.appendChild(lbl);
            });

            syncInvPropNum(); // show initial hint
        }

        function syncInvPropNum() {
            var list = document.getElementById('inv_prop_num_checklist');
            var hidden = document.getElementById('inv_property_number_hidden');
            var hint = document.getElementById('inv_prop_num_match_hint');
            var qtyEl = document.getElementById('inv_quantity');
            var snHidden = document.getElementById('inv_serial_number_hidden');
            if (!list) return;

            // Only count enabled (non-disabled) checked boxes for submission
            var checked = list.querySelectorAll('input[type="checkbox"]:not(:disabled):checked');
            var vals = Array.prototype.map.call(checked, function(cb) {
                return cb.value;
            });
            if (hidden) hidden.value = vals.join(' / ');

            var got = vals.length;
            var qty = parseInt(qtyEl ? qtyEl.value : '0', 10) || 0;
            var snCount = snHidden && snHidden.value.trim() ? parseSerials(snHidden.value).length : 0;
            var need = qty; // primary target

            if (!hint) return;

            if (got === 0 && list.querySelectorAll('input[type="checkbox"]').length === 0) {
                hint.style.display = 'none';
                return;
            }

            hint.style.display = 'block';

            if (need === 0) {
                hint.className = 'sn-match-hint warn';
                hint.textContent = 'Enter a quantity, then check the matching property numbers.';
            } else if (got === need) {
                // Also warn if serial count disagrees
                if (snCount > 0 && snCount !== got) {
                    hint.className = 'sn-match-hint warn';
                    hint.textContent = '⚠ ' + got + '/' + need + ' checked — but serial count is ' + snCount + '. Quantity, serials, PAR/ICS numbers, and property numbers should all match.';
                } else {
                    hint.className = 'sn-match-hint ok';
                    hint.textContent = '✓ ' + got + '/' + need + ' checked — matches quantity.';
                    list.classList.remove('sn-invalid');
                    list.classList.add('sn-valid');
                }
            } else if (got < need) {
                hint.className = 'sn-match-hint warn';
                hint.textContent = '✗ ' + got + '/' + need + ' checked — need ' + (need - got) + ' more.';
                list.classList.remove('sn-valid');
            } else {
                hint.className = 'sn-match-hint error';
                hint.textContent = '✗ ' + got + '/' + need + ' checked — ' + (got - need) + ' too many, uncheck some.';
                list.classList.remove('sn-valid');
            }
            // Keep ARE/PAR/ICS hint in sync whenever property number changes
            syncInvAreMrIcs();
        }

        /* ── ARE / PAR / ICS Number checklist: parse textarea → render checkboxes ── */
        function setInvAreMrIcs(are) {
            var ta = document.getElementById('inv_are_mr_ics_input');
            var hintEl = document.getElementById('inv_are_mr_ics_hint');
            if (!ta) return;
            _invExistingAreMrIcs = are && are.trim() ? parseSerials(are) : [];
            if (are && are.trim()) {
                ta.value = are.trim();
                ta.style.borderColor = '#696cff';
                ta.style.background = '#f0f1ff';
                if (hintEl) {
                    hintEl.textContent = '✓ Auto-filled from existing record — existing PAR/ICS numbers are locked; enter new ones to add.';
                    hintEl.style.color = '#696cff';
                    hintEl.style.display = 'block';
                }
            }
            onInvAreMrIcsInput(); // rebuild checkboxes from the new value
        }

        function clearInvAreMrIcs() {
            _invExistingAreMrIcs = [];
            var ta = document.getElementById('inv_are_mr_ics_input');
            var hidden = document.getElementById('inv_are_mr_ics_hidden');
            var list = document.getElementById('inv_are_mr_ics_checklist');
            var hint = document.getElementById('inv_are_mr_ics_match_hint');
            var hintEl = document.getElementById('inv_are_mr_ics_hint');
            var badge = document.getElementById('inv_are_mr_ics_count');
            if (ta) { ta.value = ''; ta.style.borderColor = ''; ta.style.background = ''; }
            if (hidden) hidden.value = '';
            if (list) { list.innerHTML = ''; list.style.display = 'none'; }
            if (hint) { hint.textContent = ''; hint.style.display = 'none'; }
            if (hintEl) hintEl.style.display = 'none';
            if (badge) { badge.textContent = '0'; badge.classList.remove('visible'); }
        }

        function onInvAreMrIcsInput() {
            var ta = document.getElementById('inv_are_mr_ics_input');
            var badge = document.getElementById('inv_are_mr_ics_count');
            if (!ta) return;

            var parts = parseSerials(ta.value);
            var count = parts.length;

            if (badge) {
                badge.textContent = count;
                badge.classList.toggle('visible', count > 0);
            }

            buildInvAreMrIcsChecklist(parts);
        }

        function buildInvAreMrIcsChecklist(parts) {
            var list = document.getElementById('inv_are_mr_ics_checklist');
            var hidden = document.getElementById('inv_are_mr_ics_hidden');
            var hint = document.getElementById('inv_are_mr_ics_match_hint');
            if (!list) return;

            list.innerHTML = '';
            if (hidden) hidden.value = '';
            if (hint) {
                hint.textContent = '';
                hint.style.display = 'none';
            }

            if (!parts || parts.length === 0) {
                list.style.display = 'none';
                return;
            }

            list.style.display = 'flex';

            parts.forEach(function(are) {
                var isExisting = _invExistingAreMrIcs.indexOf(are) !== -1;
                var lbl = document.createElement('label');
                lbl.className = 'serial-check-item' + (isExisting ? ' sn-disabled' : '');
                lbl.title = isExisting ? are + ' (already saved — cannot re-submit)' : are;

                var cb = document.createElement('input');
                cb.type = 'checkbox';
                cb.value = are;
                cb.disabled = isExisting;

                if (!isExisting) {
                    cb.addEventListener('change', function() {
                        lbl.classList.toggle('sn-checked', cb.checked);
                        syncInvAreMrIcs();
                    });
                }

                lbl.appendChild(cb);
                lbl.appendChild(document.createTextNode(are));
                list.appendChild(lbl);
            });

            syncInvAreMrIcs(); // show initial hint
        }

        function syncInvAreMrIcs() {
            var list = document.getElementById('inv_are_mr_ics_checklist');
            var hidden = document.getElementById('inv_are_mr_ics_hidden');
            var hint = document.getElementById('inv_are_mr_ics_match_hint');
            var qtyEl = document.getElementById('inv_quantity');
            var snHidden = document.getElementById('inv_serial_number_hidden');
            var propList = document.getElementById('inv_prop_num_checklist');
            if (!list) return;

            // Only count enabled (non-disabled) checked boxes for submission
            var checked = list.querySelectorAll('input[type="checkbox"]:not(:disabled):checked');
            var vals = Array.prototype.map.call(checked, function(cb) {
                return cb.value;
            });
            if (hidden) hidden.value = vals.join(' / ');

            var got = vals.length;
            var qty = parseInt(qtyEl ? qtyEl.value : '0', 10) || 0;
            var snCount = snHidden && snHidden.value.trim() ? parseSerials(snHidden.value).length : 0;
            var propChecked = propList ? propList.querySelectorAll('input[type="checkbox"]:not(:disabled):checked').length : 0;

            if (!hint) return;

            if (got === 0 && list.querySelectorAll('input[type="checkbox"]').length === 0) {
                hint.style.display = 'none';
                return;
            }

            hint.style.display = 'block';

            var need = qty || snCount; // primary target: qty first, fallback to serial count

            if (need === 0) {
                hint.className = 'sn-match-hint warn';
                hint.textContent = 'Enter a quantity, then check the matching PAR/ICS numbers.';
            } else if (got === need) {
                // Warn if serial count or property number count disagrees
                if (snCount > 0 && snCount !== got) {
                    hint.className = 'sn-match-hint warn';
                    hint.textContent = '⚠ ' + got + '/' + need + ' checked — but serial count is ' + snCount + '. PAR/ICS numbers, serials, and property numbers should all match.';
                } else if (propChecked > 0 && propChecked !== got) {
                    hint.className = 'sn-match-hint warn';
                    hint.textContent = '⚠ ' + got + '/' + need + ' checked — but property numbers checked is ' + propChecked + '. All three must match.';
                } else {
                    hint.className = 'sn-match-hint ok';
                    hint.textContent = '✓ ' + got + '/' + need + ' checked — matches quantity' + (snCount > 0 ? ' and serial count' : '') + '.';
                    list.classList.remove('sn-invalid');
                    list.classList.add('sn-valid');
                }
            } else if (got < need) {
                hint.className = 'sn-match-hint warn';
                hint.textContent = '✗ ' + got + '/' + need + ' checked — need ' + (need - got) + ' more.';
                list.classList.remove('sn-valid');
            } else {
                hint.className = 'sn-match-hint error';
                hint.textContent = '✗ ' + got + '/' + need + ' checked — ' + (got - need) + ' too many, uncheck some.';
                list.classList.remove('sn-valid');
            }
        }

        /* ── Serial textarea: badge + unified checklist (existing disabled, new checkable) ── */
        function onInvSerialInput() {
            var ta = document.getElementById('inv_serial_number');
            var badge = document.getElementById('inv_serial_count');
            if (!ta) return;

            var newParts = parseSerials(ta.value);
            var count = newParts.length;

            if (badge) {
                badge.textContent = count;
                badge.classList.toggle('visible', count > 0);
            }

            buildInvSerialChecklist(newParts);
        }

        /* Builds the unified serial checklist:
           - existing serials (_invExistingSerials) → disabled (grey, uncheckable)
           - new serials from textarea (newParts)   → enabled (checkable)
        */
        function buildInvSerialChecklist(newParts) {
            var list = document.getElementById('inv_serial_checklist');
            var hidden = document.getElementById('inv_serial_number_hidden');
            var qtyEl = document.getElementById('inv_quantity');
            var note = document.getElementById('inv_qty_serial_note');
            if (!list) return;

            list.innerHTML = '';

            var hasExisting = _invExistingSerials.length > 0;
            var hasNew = newParts && newParts.length > 0;

            if (!hasExisting && !hasNew) {
                list.style.display = 'none';
                if (hidden) hidden.value = '';
                if (note) note.style.display = 'none';
                return;
            }

            list.style.display = 'flex';

            // Render existing serials as disabled
            _invExistingSerials.forEach(function(sn) {
                var lbl = document.createElement('label');
                lbl.className = 'serial-check-item sn-disabled';
                lbl.title = sn + ' (already saved — cannot re-submit)';

                var cb = document.createElement('input');
                cb.type = 'checkbox';
                cb.value = sn;
                cb.disabled = true;

                lbl.appendChild(cb);
                lbl.appendChild(document.createTextNode(sn));
                list.appendChild(lbl);
            });

            // Render new serials as enabled
            if (newParts) {
                newParts.forEach(function(sn) {
                    var lbl = document.createElement('label');
                    lbl.className = 'serial-check-item';
                    lbl.title = sn;

                    var cb = document.createElement('input');
                    cb.type = 'checkbox';
                    cb.value = sn;
                    cb.addEventListener('change', function() {
                        lbl.classList.toggle('sn-checked', cb.checked);
                        syncInvSerial();
                    });

                    lbl.appendChild(cb);
                    lbl.appendChild(document.createTextNode(sn));
                    list.appendChild(lbl);
                });
            }

            // Auto-quantity from new serials count
            var count = newParts ? newParts.length : 0;
            if (count > 0) {
                if (qtyEl) qtyEl.value = count;
                if (note) {
                    note.textContent = '✓ ' + count + ' new serial number(s) detected — quantity set to ' + count + '. Edit manually if needed.';
                    note.style.color = '#696cff';
                    note.style.display = 'block';
                }
            } else {
                if (note) note.style.display = 'none';
            }

            syncInvSerial();
            calcTotalAmount();
        }

        function syncInvSerial() {
            var list = document.getElementById('inv_serial_checklist');
            var hidden = document.getElementById('inv_serial_number_hidden');
            var hint = document.getElementById('inv_serial_match_hint');
            var qtyEl = document.getElementById('inv_quantity');
            if (!list || !hidden) return;

            var checked = list.querySelectorAll('input[type="checkbox"]:not(:disabled):checked');
            var vals = Array.prototype.map.call(checked, function(cb) { return cb.value; });
            hidden.value = vals.join(' / ');

            var got = vals.length;
            var qty = parseInt(qtyEl ? qtyEl.value : '0', 10) || 0;

            if (!hint) return;

            var enabledCbs = list.querySelectorAll('input[type="checkbox"]:not(:disabled)');
            if (enabledCbs.length === 0) {
                hint.style.display = 'none';
                return;
            }

            hint.style.display = 'block';

            if (qty === 0) {
                hint.className = 'sn-match-hint warn';
                hint.textContent = 'Enter a quantity, then check the new serial numbers to submit.';
            } else if (got === qty) {
                hint.className = 'sn-match-hint ok';
                hint.textContent = '✓ ' + got + '/' + qty + ' new serial(s) checked — matches quantity.';
                list.classList.remove('sn-invalid');
                list.classList.add('sn-valid');
            } else if (got < qty) {
                hint.className = 'sn-match-hint warn';
                hint.textContent = '✗ ' + got + '/' + qty + ' checked — need ' + (qty - got) + ' more.';
                list.classList.remove('sn-valid');
            } else {
                hint.className = 'sn-match-hint error';
                hint.textContent = '✗ ' + got + '/' + qty + ' checked — ' + (got - qty) + ' too many.';
                list.classList.remove('sn-valid');
            }

            syncInvPropNum();
            syncInvAreMrIcs();
        }

        /* ── Total Amount calculator ──────────────────────────────────────── */
        function calcTotalAmount() {
            var val = parseFloat(document.getElementById('inv_value_amount').value) || 0;
            var qty = parseInt(document.getElementById('inv_quantity').value, 10) || 0;
            var total = val * qty;
            var field = document.getElementById('inv_total_amount');
            if (field) field.value = total > 0 ? total.toFixed(2) : '';
            syncInvPropNum(); // refresh property-number match hint when qty changes
            syncInvAreMrIcs(); // refresh ARE/PAR/ICS match hint when qty changes
            syncInvSerial(); // refresh serial match hint when qty changes
        }

        /* ── Form submit guard: serial duplicate check + property number validation ── */
        document.addEventListener('DOMContentLoaded', function() {
            var invForm = document.querySelector('#tab-inv form');
            if (!invForm) return;

            /* ── Show the duplicate-warning modal, resolve when encoder decides ── */
            function showSnDupeModal(dupes, sources) {
                sources = sources || {};
                return new Promise(function(resolve) {
                    var overlay = document.createElement('div');
                    overlay.className = 'sn-dupe-overlay';

                    function esc(s) {
                        return (s || '').replace(/[&<>"']/g, function(c) {
                            return {
                                '&': '&amp;',
                                '<': '&lt;',
                                '>': '&gt;',
                                '"': '&quot;',
                                "'": '&#39;'
                            } [c];
                        });
                    }

                    // Build one chip+source row per duplicate
                    var chipsHtml = dupes.map(function(d) {
                        var src = sources[d] ? sources[d] : '';
                        return '<div style="display:flex;align-items:flex-start;gap:.55rem;margin-bottom:.45rem;">' +
                            '<span class="sn-dupe-chip" style="flex-shrink:0;">' + esc(d) + '</span>' +
                            (src ?
                                '<span style="font-size:.78rem;color:#8592a3;padding-top:.18rem;line-height:1.35;">' +
                                'found in: <em>' + esc(src) + '</em></span>' :
                                '') +
                            '</div>';
                    }).join('');

                    overlay.innerHTML =
                        '<div class="sn-dupe-modal">' +
                        '<div class="sn-dupe-title">' +
                        '<i class="bx bx-error-circle"></i>' +
                        'Duplicate Serial Number' + (dupes.length > 1 ? 's' : '') + ' Detected' +
                        '</div>' +
                        '<p>' +
                        '<strong>' + dupes.length + '</strong> serial number' +
                        (dupes.length > 1 ? 's' : '') + ' you entered ' +
                        (dupes.length > 1 ? 'are' : 'is') +
                        ' already registered in the database:' +
                        '</p>' +
                        '<div class="sn-dupe-chips" style="flex-direction:column;gap:0;">' +
                        chipsHtml +
                        '</div>' +
                        '<p style="font-size:.82rem;color:#8592a3;margin-bottom:1rem;">' +
                        'Adding them again may create duplicate records. ' +
                        'Click <strong>Go Back &amp; Fix</strong> to edit your serial numbers, ' +
                        'or <strong>Proceed Anyway</strong> if this is intentional.' +
                        '</p>' +
                        '<div class="sn-dupe-actions">' +
                        '<button type="button" class="sn-dupe-btn-cancel">Go Back &amp; Fix</button>' +
                        '<button type="button" class="sn-dupe-btn-proceed">Proceed Anyway</button>' +
                        '</div>' +
                        '</div>';

                    document.body.appendChild(overlay);

                    overlay.querySelector('.sn-dupe-btn-cancel').addEventListener('click', function() {
                        overlay.remove();
                        resolve(false);
                    });
                    overlay.querySelector('.sn-dupe-btn-proceed').addEventListener('click', function() {
                        resolve(true);
                    });
                });
            }

            invForm.addEventListener('submit', function(e) {
                e.preventDefault(); // always hold — we'll programmatically submit after checks

                // ── Sync particulars hidden ──
                var partInput = document.getElementById('inv_particulars_input');
                var hidEl = document.getElementById('inv_particulars_hidden');
                if (partInput && hidEl) hidEl.value = partInput.value.trim();

                // ── Property number checkbox validation ──
                var propList = document.getElementById('inv_prop_num_checklist');
                if (propList && propList.querySelectorAll('input[type="checkbox"]:not(:disabled)').length > 0) {
                    var qtyEl2 = document.getElementById('inv_quantity');
                    var snHidden2 = document.getElementById('inv_serial_number_hidden');
                    var need2 = parseInt(qtyEl2 ? qtyEl2.value : '0', 10) || 0;
                    var snCount2 = snHidden2 && snHidden2.value.trim() ? parseSerials(snHidden2.value).length : 0;
                    var got2 = propList.querySelectorAll('input[type="checkbox"]:not(:disabled):checked').length;
                    var phint = document.getElementById('inv_prop_num_match_hint');

                    if (need2 > 0 && got2 !== need2) {
                        if (phint) {
                            phint.className = 'sn-match-hint error';
                            phint.style.display = 'block';
                            phint.textContent = '✗ Cannot save — ' + got2 + ' new property number(s) checked but quantity is ' + need2 + '. They must match exactly.';
                        }
                        propList.classList.add('sn-invalid');
                        propList.scrollIntoView({
                            behavior: 'smooth',
                            block: 'center'
                        });
                        return;
                    }
                    if (snCount2 > 0 && got2 !== snCount2) {
                        if (phint) {
                            phint.className = 'sn-match-hint error';
                            phint.style.display = 'block';
                            phint.textContent = '✗ Cannot save — ' + got2 + ' new property number(s) checked but ' + snCount2 + ' new serial number(s) entered. They must match.';
                        }
                        propList.classList.add('sn-invalid');
                        propList.scrollIntoView({
                            behavior: 'smooth',
                            block: 'center'
                        });
                        return;
                    }
                }

                // ── ARE / PAR / ICS number checkbox validation ──
                var areList = document.getElementById('inv_are_mr_ics_checklist');
                if (areList && areList.querySelectorAll('input[type="checkbox"]:not(:disabled)').length > 0) {
                    var areQtyEl = document.getElementById('inv_quantity');
                    var areSnHidden = document.getElementById('inv_serial_number_hidden');
                    var areNeed = parseInt(areQtyEl ? areQtyEl.value : '0', 10) || 0;
                    var areSnCount = areSnHidden && areSnHidden.value.trim() ? parseSerials(areSnHidden.value).length : 0;
                    var areGot = areList.querySelectorAll('input[type="checkbox"]:not(:disabled):checked').length;
                    var areHint = document.getElementById('inv_are_mr_ics_match_hint');

                    if (areNeed > 0 && areGot !== areNeed) {
                        if (areHint) {
                            areHint.className = 'sn-match-hint error';
                            areHint.style.display = 'block';
                            areHint.textContent = '✗ Cannot save — ' + areGot + ' PAR/ICS number(s) checked but quantity is ' + areNeed + '. They must match exactly.';
                        }
                        areList.classList.add('sn-invalid');
                        areList.scrollIntoView({
                            behavior: 'smooth',
                            block: 'center'
                        });
                        return;
                    }
                    if (areSnCount > 0 && areGot !== areSnCount) {
                        if (areHint) {
                            areHint.className = 'sn-match-hint error';
                            areHint.style.display = 'block';
                            areHint.textContent = '✗ Cannot save — ' + areGot + ' new PAR/ICS number(s) checked but ' + areSnCount + ' new serial number(s) entered. They must match.';
                        }
                        areList.classList.add('sn-invalid');
                        areList.scrollIntoView({
                            behavior: 'smooth',
                            block: 'center'
                        });
                        return;
                    }
                }

                // ── Serial duplicate AJAX check ──
                var snHidden = document.getElementById('inv_serial_number_hidden');
                var serials = snHidden && snHidden.value.trim() ? parseSerials(snHidden.value) : [];

                if (serials.length === 0) {
                    // No serials entered — skip check and submit
                    invForm.submit();
                    return;
                }

                var submitBtn = invForm.querySelector('button[type="submit"]');
                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<i class="bx bx-loader bx-spin"></i> Checking serials…';
                }

                var url = window.location.pathname + '?enc_action=check_serial_dupe&serials=' +
                    encodeURIComponent(serials.join(' / '));

                fetch(url)
                    .then(function(r) {
                        return r.json();
                    })
                    .then(function(data) {
                        if (submitBtn) {
                            submitBtn.disabled = false;
                            submitBtn.innerHTML = '<i class="bx bx-save"></i> Save Inventory Item';
                        }
                        var dupes = data.duplicates || [];
                        var sources = data.sources || {};
                        if (dupes.length === 0) {
                            invForm.submit();
                        } else {
                            showSnDupeModal(dupes, sources).then(function(proceed) {
                                if (proceed) invForm.submit();
                                else {
                                    // Highlight the serial textarea so encoder can fix it
                                    if (snTa) {
                                        snTa.focus();
                                        snTa.style.borderColor = '#ea5455';
                                        snTa.style.boxShadow = '0 0 0 3px rgba(234,84,85,.15)';
                                        setTimeout(function() {
                                            snTa.style.borderColor = '';
                                            snTa.style.boxShadow = '';
                                        }, 3000);
                                    }
                                }
                            });
                        }
                    })
                    .catch(function() {
                        // Network/parse error — let them submit anyway rather than blocking
                        if (submitBtn) {
                            submitBtn.disabled = false;
                            submitBtn.innerHTML = '<i class="bx bx-save"></i> Save Inventory Item';
                        }
                        invForm.submit();
                    });
            });
        });
    </script>
    <script>
        (function() {
            /* ── Tab switching ──────────────────────────────────────────────────── */
            const tabMap = {
                inv: document.getElementById('tab-inv'),
                borrow: document.getElementById('tab-borrow'),
                account: document.getElementById('tab-account'),
            };
            const btns = document.querySelectorAll('.enc-tab-btn');

            btns.forEach(function(btn) {
                btn.addEventListener('click', function() {
                    const target = btn.dataset.tab;
                    btns.forEach(b => b.classList.remove('active'));
                    btn.classList.add('active');
                    Object.keys(tabMap).forEach(function(k) {
                        tabMap[k].classList.toggle('active', k === target);
                    });
                    // Persist active tab in sessionStorage
                    try {
                        sessionStorage.setItem('enc_tab', target);
                    } catch (_) {}
                });
            });

            // Restore tab on reload
            try {
                const saved = sessionStorage.getItem('enc_tab');
                if (saved && tabMap[saved]) {
                    document.querySelector('[data-tab="' + saved + '"]').click();
                }
            } catch (_) {}

            /* ── Auto-dismiss feedback after 8 s ───────────────────────────────── */
            const fb = document.querySelector('.enc-feedback');
            if (fb) {
                setTimeout(function() {
                    fb.style.transition = 'opacity .4s';
                    fb.style.opacity = '0';
                    setTimeout(function() {
                        fb.remove();
                    }, 450);
                }, 8000);
            }
        })();
    </script>
    <script>
        /* ── Employee Autocomplete ──────────────────────────────────────────────── */
        (function() {
            // Employee data injected from PHP
            var EMPLOYEES = <?= $employees_json ?>;

            // Accountable items: used by Borrow tab to resolve accountable_id + max qty
            var ACCOUNTABLE_ITEMS = <?= $accountable_items_json ?>;

            // Inventory items map: id → quantity (for Accountable tab max qty)
            var INVENTORY_QTY = {};
            // Inventory items map: id → particulars (for Accountable tab Particulars autocomplete)
            var INVENTORY_PARTICULARS = {};
            <?php foreach ($inventory_items_list as $ii): ?>
                INVENTORY_QTY[<?= (int)$ii['id'] ?>] = <?= (int)$ii['quantity'] ?>;
                INVENTORY_PARTICULARS[<?= (int)$ii['id'] ?>] = <?= json_encode($ii['particulars'] ?? '', JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
            <?php endforeach; ?>

            // Accountable-tab category map: itemName → [{id, p, qty, sn, are, prop}]
            var ACCT_ITEM_NAMES = <?= $acct_item_names_json ?>;
            var ACCT_ITEM_MAP = <?= $acct_item_map_json ?>;

            /**
             * Normalize a string for comparison: uppercase, collapse spaces
             */
            function norm(s) {
                return (s || '').toUpperCase().replace(/\s+/g, ' ').trim();
            }

            /**
             * Filter employees whose full_name contains all space-separated query tokens
             */
            function filterEmployees(query) {
                var tokens = norm(query).split(' ').filter(Boolean);
                if (!tokens.length) return [];
                return EMPLOYEES.filter(function(emp) {
                    var n = norm(emp.full_name);
                    return tokens.every(function(t) {
                        return n.indexOf(t) !== -1;
                    });
                }).slice(0, 40);
            }

            /**
             * Build and attach autocomplete behaviour to a wrapper element.
             * @param {HTMLElement} wrap   - .emp-autocomplete-wrap
             * @param {Function}    onSelect - called with { id, full_name, emp_no }
             */
            function initAutocomplete(wrap, onSelect) {
                var searchInput = wrap.querySelector('.emp-search-input');
                var clearBtn = wrap.querySelector('.emp-clear-btn');
                var dropdown = wrap.querySelector('.emp-dropdown');
                var hiddenInput = wrap.querySelector('input[type="hidden"]');

                // Chip element lives OUTSIDE wrap (sibling), identified by data-chip matching data-autocomplete
                var acKey = wrap.dataset.autocomplete;
                var chip = wrap.closest('.enc-field').querySelector('.emp-selected-chip[data-chip="' + acKey + '"]');
                var chipLabel = chip ? chip.querySelector('.chip-label') : null;
                var chipRemove = chip ? chip.querySelector('.chip-remove') : null;

                var focusedIndex = -1;
                var results = [];

                function openDropdown(items) {
                    results = items;
                    focusedIndex = -1;
                    dropdown.innerHTML = '';

                    if (!items.length) {
                        dropdown.innerHTML = '<div class="emp-dropdown-empty">No employees found.</div>';
                    } else {
                        items.forEach(function(emp, idx) {
                            var el = document.createElement('div');
                            el.className = 'emp-dropdown-item';
                            el.innerHTML =
                                '<span class="emp-name">' + escHtml(emp.full_name) + '</span>' +
                                '<span class="emp-meta">ID #' + (emp.emp_no || emp.id) + '</span>';
                            el.addEventListener('mousedown', function(e) {
                                e.preventDefault(); // keep focus on input
                                selectEmployee(emp);
                            });
                            dropdown.appendChild(el);
                        });
                    }
                    dropdown.classList.add('open');
                }

                function closeDropdown() {
                    dropdown.classList.remove('open');
                    focusedIndex = -1;
                }

                function moveFocus(dir) {
                    var items = dropdown.querySelectorAll('.emp-dropdown-item');
                    if (!items.length) return;
                    items[focusedIndex] && items[focusedIndex].classList.remove('focused');
                    focusedIndex = Math.max(0, Math.min(focusedIndex + dir, items.length - 1));
                    items[focusedIndex].classList.add('focused');
                    items[focusedIndex].scrollIntoView({
                        block: 'nearest'
                    });
                }

                function selectEmployee(emp) {
                    hiddenInput.value = emp.full_name;
                    searchInput.value = '';
                    clearBtn.style.display = 'none';
                    closeDropdown();
                    searchInput.style.display = 'none';
                    clearBtn.style.display = 'none';

                    // Show chip
                    if (chip && chipLabel) {
                        chipLabel.textContent = emp.full_name;
                        chip.classList.add('visible');
                    }

                    // Call callback (e.g. fill employee ID field)
                    if (typeof onSelect === 'function') onSelect(emp);
                }

                function clearSelection() {
                    hiddenInput.value = '';
                    searchInput.value = '';
                    searchInput.style.display = '';
                    clearBtn.style.display = 'none';
                    closeDropdown();
                    if (chip) chip.classList.remove('visible');
                    if (typeof onSelect === 'function') onSelect(null);
                    searchInput.focus();
                }

                // Events
                searchInput.addEventListener('input', function() {
                    var q = searchInput.value.trim();
                    clearBtn.style.display = q ? 'block' : 'none';
                    if (q.length < 1) {
                        closeDropdown();
                        return;
                    }
                    openDropdown(filterEmployees(q));
                });

                searchInput.addEventListener('keydown', function(e) {
                    if (!dropdown.classList.contains('open')) return;
                    if (e.key === 'ArrowDown') {
                        e.preventDefault();
                        moveFocus(1);
                    }
                    if (e.key === 'ArrowUp') {
                        e.preventDefault();
                        moveFocus(-1);
                    }
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        if (focusedIndex >= 0 && results[focusedIndex]) selectEmployee(results[focusedIndex]);
                    }
                    if (e.key === 'Escape') closeDropdown();
                });

                searchInput.addEventListener('blur', function() {
                    // Delay so mousedown on item fires first
                    setTimeout(closeDropdown, 180);
                });

                clearBtn.addEventListener('click', function() {
                    searchInput.value = '';
                    clearBtn.style.display = 'none';
                    closeDropdown();
                    searchInput.focus();
                });

                if (chipRemove) {
                    chipRemove.addEventListener('click', clearSelection);
                }
            }

            function escHtml(s) {
                return (s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
            }

            /* ── Wire up Borrow tab ───────────────────────────────────────────── */
            var borrowTab = document.getElementById('tab-borrow');
            if (borrowTab) {
                var borrowInvSelect = document.getElementById('borrow_inventory_item_id');
                var borrowItemHint = document.getElementById('borrow_item_hint');
                var borrowQtyInput = document.getElementById('borrow_quantity');
                var borrowQtyHint = document.getElementById('borrow_qty_hint');
                var borrowAcctId = document.getElementById('borrow_accountable_id');
                var _borrowFromEmpId = 0; // system ID of selected from_person
                var _borrowFromPerson = ''; // full_name of selected from_person

                // ── Rebuild inventory item dropdown based on from_person ────────
                function updateBorrowInventoryItems() {
                    borrowInvSelect.innerHTML = '';
                    borrowQtyInput.disabled = true;
                    borrowQtyInput.value = '';
                    if (borrowQtyHint) {
                        borrowQtyHint.style.display = 'none';
                        borrowQtyHint.textContent = '';
                    }
                    if (borrowAcctId) borrowAcctId.value = '';

                    if (!_borrowFromPerson) {
                        var defOpt = document.createElement('option');
                        defOpt.value = '';
                        defOpt.textContent = '— Select From Person first —';
                        borrowInvSelect.appendChild(defOpt);
                        borrowInvSelect.disabled = true;
                        if (borrowItemHint) {
                            borrowItemHint.textContent = 'Select a From Person to load their assigned items.';
                            borrowItemHint.style.color = '#a1b0be';
                        }
                        resetBorrowSerialChecklist('Select a From Person and item to load serial numbers.');
                        buildBorrowAreChecklist('');
                        buildBorrowPropChecklist('');
                        return;
                    }

                    // Find all accountable records for this person
                    var personRecs = ACCOUNTABLE_ITEMS.filter(function(rec) {
                        var nameMatch = rec.person_name.trim().toLowerCase() === _borrowFromPerson.trim().toLowerCase();
                        var idMatch = _borrowFromEmpId > 0 ? rec.employee_id === _borrowFromEmpId : true;
                        return nameMatch || (_borrowFromEmpId > 0 && idMatch);
                    });

                    if (personRecs.length === 0) {
                        var noOpt = document.createElement('option');
                        noOpt.value = '';
                        noOpt.textContent = '— No accountable items found for this person —';
                        borrowInvSelect.appendChild(noOpt);
                        borrowInvSelect.disabled = true;
                        if (borrowItemHint) {
                            borrowItemHint.textContent = '⚠ This person has no accountable item records.';
                            borrowItemHint.style.color = '#ea5455';
                        }
                        resetBorrowSerialChecklist('No accountable items found for this person.');
                        buildBorrowAreChecklist('');
                        buildBorrowPropChecklist('');
                        return;
                    }

                    // Enable dropdown and populate with that person's items
                    borrowInvSelect.disabled = false;
                    var placeholder = document.createElement('option');
                    placeholder.value = '';
                    placeholder.textContent = '— Select Item —';
                    borrowInvSelect.appendChild(placeholder);

                    personRecs.forEach(function(rec) {
                        var opt = document.createElement('option');
                        opt.value = rec.inventory_item_id;
                        opt.dataset.acctId = rec.id;
                        opt.dataset.maxQty = rec.assigned_quantity;
                        opt.dataset.serialNumber = rec.serial_number || '';
                        opt.dataset.areMrIcsNum = rec.are_mr_ics_num || '';
                        opt.dataset.propertyNumber = rec.property_number || '';
                        opt.textContent = '#' + rec.inventory_item_id + ' — ' + rec.item_name +
                            ' (Assigned: ' + rec.assigned_quantity + ')';
                        borrowInvSelect.appendChild(opt);
                    });

                    if (borrowItemHint) {
                        borrowItemHint.textContent = '✓ Showing ' + personRecs.length + ' item(s) assigned to this person.';
                        borrowItemHint.style.color = '#28c76f';
                    }
                    updateBorrowQty();
                }

                // ── Update quantity max from selected item's accountable record ─
                function updateBorrowQty() {
                    var selOpt = borrowInvSelect.options[borrowInvSelect.selectedIndex];
                    if (!selOpt || !selOpt.value) {
                        borrowQtyInput.disabled = true;
                        borrowQtyInput.max = '';
                        borrowQtyInput.value = '';
                        borrowQtyInput.placeholder = 'Select an item first';
                        if (borrowQtyHint) {
                            borrowQtyHint.style.display = 'none';
                        }
                        if (borrowAcctId) borrowAcctId.value = '';
                        return;
                    }
                    var maxQty = parseInt(selOpt.dataset.maxQty, 10) || 0;
                    var acctId = selOpt.dataset.acctId || '';
                    if (maxQty > 0) {
                        borrowQtyInput.disabled = false;
                        borrowQtyInput.max = maxQty;
                        borrowQtyInput.placeholder = '1 – ' + maxQty;
                        borrowQtyInput.value = '';
                        if (borrowAcctId) borrowAcctId.value = acctId;
                        if (borrowQtyHint) {
                            borrowQtyHint.textContent = '✓ Max borrowable: ' + maxQty + ' unit(s) (Accountable Record #' + acctId + ')';
                            borrowQtyHint.style.color = '#696cff';
                            borrowQtyHint.style.display = 'block';
                        }
                    } else {
                        borrowQtyInput.disabled = true;
                        borrowQtyInput.value = '';
                        borrowQtyInput.placeholder = 'No available quantity';
                        if (borrowAcctId) borrowAcctId.value = '';
                        if (borrowQtyHint) {
                            borrowQtyHint.textContent = '⚠ No quantity available on this accountable record.';
                            borrowQtyHint.style.color = '#ea5455';
                            borrowQtyHint.style.display = 'block';
                        }
                    }
                }

                if (borrowInvSelect) {
                    borrowInvSelect.addEventListener('change', updateBorrowQty);
                }

                // ── ARE / MR / ICS Number Checklist ───────────────────────────
                var borrowAreChecklist = document.getElementById('borrow_are_checklist');
                var borrowAreHidden = document.getElementById('borrow_are_mr_ics_hidden');
                var borrowAreHint = document.getElementById('borrow_are_hint');
                var borrowAreReqNote = document.getElementById('borrow_are_req_note');
                var borrowAreCheckedCount = document.getElementById('borrow_are_checked_count');

                function syncBorrowAre() {
                    var checked = [];
                    if (borrowAreChecklist) {
                        borrowAreChecklist.querySelectorAll('input[type="checkbox"]:checked').forEach(function(cb) {
                            checked.push(cb.value);
                        });
                    }
                    if (borrowAreHidden) borrowAreHidden.value = checked.join(' / ');
                    var n = checked.length;
                    if (borrowAreCheckedCount) borrowAreCheckedCount.textContent = n;

                    if (borrowAreChecklist) {
                        borrowAreChecklist.classList.remove('sn-valid', 'sn-invalid');
                        var need = parseInt(borrowQtyInput ? borrowQtyInput.value : '0', 10) || 0;
                        if (n > 0) borrowAreChecklist.classList.add(n === need ? 'sn-valid' : 'sn-invalid');
                    }
                    updateBorrowAreHint();
                }

                function updateBorrowAreHint() {
                    if (!borrowAreHint) return;
                    var need = parseInt(borrowQtyInput ? borrowQtyInput.value : '0', 10) || 0;
                    var areGot = borrowAreChecklist ?
                        borrowAreChecklist.querySelectorAll('input[type="checkbox"]:checked').length : 0;
                    var hasBoxes = borrowAreChecklist ?
                        borrowAreChecklist.querySelectorAll('input[type="checkbox"]').length > 0 : false;

                    if (!hasBoxes) {
                        borrowAreHint.style.display = 'none';
                        return;
                    }
                    borrowAreHint.style.display = 'block';

                    if (need === 0) {
                        borrowAreHint.className = 'sn-match-hint warn';
                        borrowAreHint.textContent = '⚠ Enter a quantity first.';
                    } else if (areGot === 0) {
                        borrowAreHint.className = 'sn-match-hint warn';
                        borrowAreHint.textContent = 'Select ' + need + ' ARE / PAR / ICS number(s) — none checked yet.';
                    } else if (areGot === need) {
                        borrowAreHint.className = 'sn-match-hint ok';
                        borrowAreHint.textContent = '✓ ' + areGot + '/' + need + ' ARE / PAR / ICS number(s) checked — matches quantity.';
                        if (borrowAreChecklist) {
                            borrowAreChecklist.classList.remove('sn-invalid');
                            borrowAreChecklist.classList.add('sn-valid');
                        }
                    } else if (areGot < need) {
                        borrowAreHint.className = 'sn-match-hint error';
                        borrowAreHint.textContent = '✗ ' + areGot + '/' + need + ' checked — need ' + (need - areGot) + ' more.';
                    } else {
                        borrowAreHint.className = 'sn-match-hint error';
                        borrowAreHint.textContent = '✗ ' + areGot + '/' + need + ' checked — ' + (areGot - need) + ' too many, uncheck some.';
                    }
                }

                function buildBorrowAreChecklist(rawAre) {
                    if (!borrowAreChecklist) return;
                    borrowAreChecklist.innerHTML = '';
                    borrowAreChecklist.className = 'serial-checklist-wrap';
                    if (borrowAreHidden) borrowAreHidden.value = '';
                    if (borrowAreHint) {
                        borrowAreHint.style.display = 'none';
                        borrowAreHint.textContent = '';
                    }

                    var parts = (rawAre || '').split('/').map(function(s) {
                        return s.trim();
                    }).filter(Boolean);

                    if (parts.length === 0) {
                        borrowAreChecklist.innerHTML = borrowAreReqNote ?
                            '<span class="serial-no-data">No ARE / MR / ICS numbers on record for this item.</span>' :
                            '<span class="serial-placeholder">Select a From Person and inventory item to load ARE / MR / ICS numbers.</span>';
                        if (borrowAreReqNote) borrowAreReqNote.style.display = 'none';
                        return;
                    }

                    if (borrowAreReqNote) borrowAreReqNote.style.display = 'inline';

                    var need = parseInt(borrowQtyInput ? borrowQtyInput.value : '0', 10) || 0;
                    var autoCheck = Math.min(need, parts.length);

                    parts.forEach(function(token, idx) {
                        var lbl = document.createElement('label');
                        lbl.className = 'serial-check-item';
                        lbl.title = token;

                        var cb = document.createElement('input');
                        cb.type = 'checkbox';
                        cb.value = token;
                        if (idx < autoCheck) {
                            cb.checked = true;
                            lbl.classList.add('sn-checked');
                        }

                        cb.addEventListener('change', function() {
                            lbl.classList.toggle('sn-checked', cb.checked);
                            syncBorrowAre();
                        });

                        lbl.appendChild(cb);
                        lbl.appendChild(document.createTextNode(token));
                        borrowAreChecklist.appendChild(lbl);
                    });

                    syncBorrowAre();
                }

                // ── Property Number Checklist ──────────────────────────────────
                var borrowPropChecklist = document.getElementById('borrow_prop_checklist');
                var borrowPropHidden = document.getElementById('borrow_property_number_hidden');
                var borrowPropHint = document.getElementById('borrow_prop_hint');
                var borrowPropReqNote = document.getElementById('borrow_prop_req_note');
                var borrowPropCheckedCount = document.getElementById('borrow_prop_checked_count');

                function syncBorrowProp() {
                    var checked = [];
                    if (borrowPropChecklist) {
                        borrowPropChecklist.querySelectorAll('input[type="checkbox"]:checked').forEach(function(cb) {
                            checked.push(cb.value);
                        });
                    }
                    if (borrowPropHidden) borrowPropHidden.value = checked.join(' / ');
                    var n = checked.length;
                    if (borrowPropCheckedCount) borrowPropCheckedCount.textContent = n;

                    if (borrowPropChecklist) {
                        borrowPropChecklist.classList.remove('sn-valid', 'sn-invalid');
                        var need = parseInt(borrowQtyInput ? borrowQtyInput.value : '0', 10) || 0;
                        if (n > 0) borrowPropChecklist.classList.add(n === need ? 'sn-valid' : 'sn-invalid');
                    }
                    updateBorrowPropHint();
                }

                function updateBorrowPropHint() {
                    if (!borrowPropHint) return;
                    var need = parseInt(borrowQtyInput ? borrowQtyInput.value : '0', 10) || 0;
                    var propGot = borrowPropChecklist ?
                        borrowPropChecklist.querySelectorAll('input[type="checkbox"]:checked').length : 0;
                    var hasBoxes = borrowPropChecklist ?
                        borrowPropChecklist.querySelectorAll('input[type="checkbox"]').length > 0 : false;

                    if (!hasBoxes) {
                        borrowPropHint.style.display = 'none';
                        return;
                    }
                    borrowPropHint.style.display = 'block';

                    if (need === 0) {
                        borrowPropHint.className = 'sn-match-hint warn';
                        borrowPropHint.textContent = '⚠ Enter a quantity first.';
                    } else if (propGot === 0) {
                        borrowPropHint.className = 'sn-match-hint warn';
                        borrowPropHint.textContent = 'Select ' + need + ' property number(s) — none checked yet.';
                    } else if (propGot === need) {
                        borrowPropHint.className = 'sn-match-hint ok';
                        borrowPropHint.textContent = '✓ ' + propGot + '/' + need + ' property number(s) checked — matches quantity.';
                        if (borrowPropChecklist) {
                            borrowPropChecklist.classList.remove('sn-invalid');
                            borrowPropChecklist.classList.add('sn-valid');
                        }
                    } else if (propGot < need) {
                        borrowPropHint.className = 'sn-match-hint error';
                        borrowPropHint.textContent = '✗ ' + propGot + '/' + need + ' checked — need ' + (need - propGot) + ' more.';
                    } else {
                        borrowPropHint.className = 'sn-match-hint error';
                        borrowPropHint.textContent = '✗ ' + propGot + '/' + need + ' checked — ' + (propGot - need) + ' too many, uncheck some.';
                    }
                }

                function buildBorrowPropChecklist(rawProp) {
                    if (!borrowPropChecklist) return;
                    borrowPropChecklist.innerHTML = '';
                    borrowPropChecklist.className = 'serial-checklist-wrap';
                    if (borrowPropHidden) borrowPropHidden.value = '';
                    if (borrowPropHint) {
                        borrowPropHint.style.display = 'none';
                        borrowPropHint.textContent = '';
                    }

                    var parts = (rawProp || '').split('/').map(function(s) {
                        return s.trim();
                    }).filter(Boolean);

                    if (parts.length === 0) {
                        borrowPropChecklist.innerHTML = borrowPropReqNote ?
                            '<span class="serial-no-data">No property numbers on record for this item.</span>' :
                            '<span class="serial-placeholder">Select a From Person and inventory item to load property numbers.</span>';
                        if (borrowPropReqNote) borrowPropReqNote.style.display = 'none';
                        return;
                    }

                    if (borrowPropReqNote) borrowPropReqNote.style.display = 'inline';

                    var need = parseInt(borrowQtyInput ? borrowQtyInput.value : '0', 10) || 0;
                    var autoCheck = Math.min(need, parts.length);

                    parts.forEach(function(token, idx) {
                        var lbl = document.createElement('label');
                        lbl.className = 'serial-check-item';
                        lbl.title = token;

                        var cb = document.createElement('input');
                        cb.type = 'checkbox';
                        cb.value = token;
                        if (idx < autoCheck) {
                            cb.checked = true;
                            lbl.classList.add('sn-checked');
                        }

                        cb.addEventListener('change', function() {
                            lbl.classList.toggle('sn-checked', cb.checked);
                            syncBorrowProp();
                        });

                        lbl.appendChild(cb);
                        lbl.appendChild(document.createTextNode(token));
                        borrowPropChecklist.appendChild(lbl);
                    });

                    syncBorrowProp();
                }

                // ── Serial Number Checklist ────────────────────────────────────
                var borrowSerialChecklist = document.getElementById('borrow_serial_checklist');
                var borrowSerialHidden = document.getElementById('borrow_serial_number_hidden');
                var borrowSerialHint = document.getElementById('borrow_serial_hint');
                var borrowSnReqNote = document.getElementById('borrow_sn_req_note');
                var borrowSnReqCount = document.getElementById('borrow_sn_req_count');

                function syncBorrowSerialHidden() {
                    var checked = [];
                    if (borrowSerialChecklist) {
                        borrowSerialChecklist.querySelectorAll('input[type="checkbox"]:checked').forEach(function(cb) {
                            checked.push(cb.value);
                        });
                    }
                    if (borrowSerialHidden) borrowSerialHidden.value = checked.join(' / ');
                    var got = checked.length;
                    var need = parseInt(borrowQtyInput ? borrowQtyInput.value : '0', 10) || 0;

                    if (borrowSerialChecklist) {
                        borrowSerialChecklist.classList.remove('sn-valid', 'sn-invalid');
                        if (got > 0) borrowSerialChecklist.classList.add(got === need ? 'sn-valid' : 'sn-invalid');
                    }

                    if (borrowSerialHint) {
                        borrowSerialHint.style.display = 'block';
                        if (need === 0) {
                            borrowSerialHint.className = 'sn-match-hint warn';
                            borrowSerialHint.textContent = '⚠ Enter a quantity first to know how many serials to select.';
                        } else if (got === 0) {
                            borrowSerialHint.className = 'sn-match-hint warn';
                            borrowSerialHint.textContent = 'Select ' + need + ' serial number(s) — none checked yet.';
                        } else if (got === need) {
                            borrowSerialHint.className = 'sn-match-hint ok';
                            borrowSerialHint.textContent = '✓ ' + got + '/' + need + ' serial(s) selected — matches quantity.';
                            if (borrowSerialChecklist) {
                                borrowSerialChecklist.classList.remove('sn-invalid');
                                borrowSerialChecklist.classList.add('sn-valid');
                            }
                        } else if (got < need) {
                            borrowSerialHint.className = 'sn-match-hint error';
                            borrowSerialHint.textContent = '✗ ' + got + '/' + need + ' selected — need ' + (need - got) + ' more.';
                        } else {
                            borrowSerialHint.className = 'sn-match-hint error';
                            borrowSerialHint.textContent = '✗ ' + got + '/' + need + ' selected — ' + (got - need) + ' too many, uncheck some.';
                        }
                        if (borrowSnReqCount) borrowSnReqCount.textContent = need || '…';
                    }
                    // Keep ARE and Prop hints in sync when serial count changes
                    updateBorrowAreHint();
                    updateBorrowPropHint();
                }

                /**
                 * Show a static placeholder message and clear the hidden value.
                 */
                function resetBorrowSerialChecklist(msg) {
                    if (!borrowSerialChecklist) return;
                    borrowSerialChecklist.innerHTML = '';
                    if (borrowSerialHidden) borrowSerialHidden.value = '';
                    if (borrowSerialHint) {
                        borrowSerialHint.style.display = 'none';
                        borrowSerialHint.textContent = '';
                    }
                    var ph = document.createElement('span');
                    ph.className = 'serial-placeholder';
                    ph.textContent = msg || 'Select an inventory item to load serial numbers.';
                    borrowSerialChecklist.appendChild(ph);
                }

                /**
                 * Parse the serial_number of the currently-selected inventory item,
                 * split by " / " and render checkboxes with quantity enforcement.
                 */
                function updateBorrowSerialNumbers() {
                    if (!borrowSerialChecklist) return;
                    borrowSerialChecklist.innerHTML = '';
                    if (borrowSerialHidden) borrowSerialHidden.value = '';
                    if (borrowSerialHint) {
                        borrowSerialHint.style.display = 'none';
                        borrowSerialHint.textContent = '';
                    }

                    var selOpt = borrowInvSelect ? borrowInvSelect.options[borrowInvSelect.selectedIndex] : null;
                    if (!selOpt || !selOpt.value) {
                        resetBorrowSerialChecklist();
                        if (borrowSnReqNote) borrowSnReqNote.style.display = 'none';
                        return;
                    }

                    var rawSerial = selOpt.dataset.serialNumber || '';
                    var parts = rawSerial.split('/').map(function(s) {
                        return s.trim();
                    }).filter(Boolean);

                    if (parts.length === 0) {
                        var none = document.createElement('span');
                        none.className = 'serial-no-data';
                        none.textContent = 'No serial number on record for this item.';
                        borrowSerialChecklist.appendChild(none);
                        if (borrowSnReqNote) borrowSnReqNote.style.display = 'none';
                        return;
                    }

                    var need = parseInt(borrowQtyInput ? borrowQtyInput.value : '0', 10) || 0;
                    if (borrowSnReqNote) {
                        borrowSnReqNote.style.display = 'inline';
                        if (borrowSnReqCount) borrowSnReqCount.textContent = need || '…';
                    }

                    var autoCheck = Math.min(need, parts.length);

                    parts.forEach(function(sn, idx) {
                        var lbl = document.createElement('label');
                        lbl.className = 'serial-check-item';
                        lbl.title = sn;

                        var cb = document.createElement('input');
                        cb.type = 'checkbox';
                        cb.value = sn;
                        if (idx < autoCheck) {
                            cb.checked = true;
                            lbl.classList.add('sn-checked');
                        }

                        cb.addEventListener('change', function() {
                            lbl.classList.toggle('sn-checked', cb.checked);
                            syncBorrowSerialHidden();
                        });

                        lbl.appendChild(cb);
                        lbl.appendChild(document.createTextNode(sn));
                        borrowSerialChecklist.appendChild(lbl);
                    });

                    syncBorrowSerialHidden();
                }

                // Re-render all checklists whenever the inventory item dropdown changes
                if (borrowInvSelect) {
                    borrowInvSelect.addEventListener('change', function() {
                        var selOpt = borrowInvSelect.options[borrowInvSelect.selectedIndex];
                        updateBorrowSerialNumbers();
                        buildBorrowAreChecklist(selOpt && selOpt.value ? (selOpt.dataset.areMrIcsNum || '') : '');
                        buildBorrowPropChecklist(selOpt && selOpt.value ? (selOpt.dataset.propertyNumber || '') : '');
                    });
                }

                // Re-sync hints whenever quantity changes
                if (borrowQtyInput) {
                    borrowQtyInput.addEventListener('input', function() {
                        var n = parseInt(borrowQtyInput.value, 10) || 0;
                        if (borrowSnReqCount) borrowSnReqCount.textContent = n || '…';
                        syncBorrowSerialHidden();
                        updateBorrowAreHint();
                        updateBorrowPropHint();
                    });
                }

                // ── Form submit guard: enforce serial/ARE/property count === quantity ──
                var borrowForm = borrowTab.querySelector('form');
                if (borrowForm) {
                    borrowForm.addEventListener('submit', function(e) {
                        var need = parseInt(borrowQtyInput ? borrowQtyInput.value : '0', 10) || 0;

                        // Serial count guard
                        var hasSnBoxes = borrowSerialChecklist ?
                            borrowSerialChecklist.querySelectorAll('input[type="checkbox"]').length > 0 : false;
                        if (hasSnBoxes) {
                            var snChecked = borrowSerialChecklist.querySelectorAll('input[type="checkbox"]:checked').length;
                            if (need > 0 && snChecked !== need) {
                                e.preventDefault();
                                if (borrowSerialHint) {
                                    borrowSerialHint.className = 'sn-match-hint error';
                                    borrowSerialHint.style.display = 'block';
                                    borrowSerialHint.textContent = '✗ Cannot save — ' + snChecked + ' serial(s) checked but quantity is ' + need + '. They must match exactly.';
                                }
                                if (borrowSerialChecklist) borrowSerialChecklist.classList.add('sn-invalid');
                                borrowSerialChecklist.scrollIntoView({
                                    behavior: 'smooth',
                                    block: 'center'
                                });
                                return;
                            }
                        }

                        // ARE / PAR / ICS count guard
                        var hasAreBoxes = borrowAreChecklist ?
                            borrowAreChecklist.querySelectorAll('input[type="checkbox"]').length > 0 : false;
                        if (hasAreBoxes) {
                            var areChecked = borrowAreChecklist.querySelectorAll('input[type="checkbox"]:checked').length;
                            if (need > 0 && areChecked !== need) {
                                e.preventDefault();
                                if (borrowAreHint) {
                                    borrowAreHint.className = 'sn-match-hint error';
                                    borrowAreHint.style.display = 'block';
                                    borrowAreHint.textContent = '✗ Cannot save — ' + areChecked + ' ARE / PAR / ICS number(s) checked but quantity is ' + need + '. They must match exactly.';
                                }
                                if (borrowAreChecklist) borrowAreChecklist.classList.add('sn-invalid');
                                borrowAreChecklist.scrollIntoView({
                                    behavior: 'smooth',
                                    block: 'center'
                                });
                                return;
                            }
                        }

                        // Property number count guard
                        var hasPropBoxes = borrowPropChecklist ?
                            borrowPropChecklist.querySelectorAll('input[type="checkbox"]').length > 0 : false;
                        if (hasPropBoxes) {
                            var propChecked = borrowPropChecklist.querySelectorAll('input[type="checkbox"]:checked').length;
                            if (need > 0 && propChecked !== need) {
                                e.preventDefault();
                                if (borrowPropHint) {
                                    borrowPropHint.className = 'sn-match-hint error';
                                    borrowPropHint.style.display = 'block';
                                    borrowPropHint.textContent = '✗ Cannot save — ' + propChecked + ' property number(s) checked but quantity is ' + need + '. They must match exactly.';
                                }
                                if (borrowPropChecklist) borrowPropChecklist.classList.add('sn-invalid');
                                borrowPropChecklist.scrollIntoView({
                                    behavior: 'smooth',
                                    block: 'center'
                                });
                                return;
                            }
                        }
                    });
                }

                // ── from_person autocomplete ───────────────────────────────────
                var fromWrap = borrowTab.querySelector('[data-autocomplete="from_person"]');
                if (fromWrap) {
                    initAutocomplete(fromWrap, function(emp) {
                        _borrowFromPerson = emp ? emp.full_name : '';
                        _borrowFromEmpId = emp ? (emp.id || 0) : 0;
                        updateBorrowInventoryItems();
                    });
                }

                // ── to_person → fills borrower_employee_id ────────────────────
                var toWrap = borrowTab.querySelector('[data-autocomplete="to_person"]');
                var borrowerEmpIdField = document.getElementById('borrow_borrower_employee_id');
                if (toWrap) {
                    initAutocomplete(toWrap, function(emp) {
                        if (borrowerEmpIdField) {
                            borrowerEmpIdField.value = emp ? (emp.id || '') : '';
                        }
                    });
                }

                // ── Copy reference number button ───────────────────────────────
                var copyBtn = document.getElementById('borrow_ref_copy_btn');
                if (copyBtn) {
                    copyBtn.addEventListener('click', function() {
                        var refVal = document.getElementById('borrow_ref_display').value;
                        if (navigator.clipboard) {
                            navigator.clipboard.writeText(refVal).then(function() {
                                copyBtn.innerHTML = '<i class="bx bx-check"></i> Copied!';
                                copyBtn.style.background = '#696cff';
                                copyBtn.style.color = '#fff';
                                setTimeout(function() {
                                    copyBtn.innerHTML = '<i class="bx bx-copy"></i> Copy';
                                    copyBtn.style.background = '#fff';
                                    copyBtn.style.color = '#696cff';
                                }, 2000);
                            });
                        }
                    });
                }
            }

            /* ── Wire up Accountable tab ──────────────────────────────────────── */
            var acctTab = document.getElementById('tab-account');
            if (acctTab) {
                // ── DOM references ────────────────────────────────────────────────
                var acctItemNameSelect = document.getElementById('acct_item_name_select');
                var acctItemIdHidden = document.getElementById('acct_inventory_item_id_hidden');
                var acctQtyInput = document.getElementById('acct_assigned_quantity');
                var acctQtyHint = document.getElementById('acct_qty_hint');
                var acctSerialChecklist = document.getElementById('acct_serial_checklist');
                var acctSerialHidden = document.getElementById('acct_serial_number_hidden');
                var acctSnMatchHint = document.getElementById('acct_sn_match_hint');
                var acctSnReqNote = document.getElementById('acct_sn_req_note');
                var acctSnReqCount = document.getElementById('acct_sn_req_count');
                var acctAreChecklist = document.getElementById('acct_are_checklist');
                var acctAreHidden = document.getElementById('acct_are_mr_ics_hidden');
                var acctAreReqNote = document.getElementById('acct_are_req_note');
                var acctAreCheckedCount = document.getElementById('acct_are_checked_count');
                var acctAreMatchHint = document.getElementById('acct_are_match_hint');
                var acctPropChecklist = document.getElementById('acct_prop_checklist');
                var acctPropHidden = document.getElementById('acct_property_number_hidden');
                var acctPropReqNote = document.getElementById('acct_prop_req_note');
                var acctPropCheckedCount = document.getElementById('acct_prop_checked_count');
                var acctPropMatchHint = document.getElementById('acct_prop_match_hint');
                var acctParticularsInput = document.getElementById('acct_particulars');
                var acctParticularsDropdown = document.getElementById('acct_particulars_dropdown');
                var acctResolveHint = document.getElementById('acct_item_resolve_hint');

                // Currently resolved inventory record: {id, p, qty, sn, are, prop}
                var _acctResolvedRec = null;

                /* ── Sync checked ARE tokens → hidden input + count label ─────── */
                function syncAcctAre() {
                    var checked = [];
                    if (acctAreChecklist) {
                        acctAreChecklist.querySelectorAll('input[type="checkbox"]:checked').forEach(function(cb) {
                            checked.push(cb.value);
                        });
                    }
                    if (acctAreHidden) acctAreHidden.value = checked.join(' / ');
                    var n = checked.length;
                    if (acctQtyInput && n > 0) {
                        acctQtyInput.value = n;
                        syncAcctSerials();
                    }
                    if (acctAreCheckedCount) acctAreCheckedCount.textContent = n;

                    // Apply sn-valid / sn-invalid border on the checklist wrap
                    if (acctAreChecklist) {
                        acctAreChecklist.classList.remove('sn-valid', 'sn-invalid');
                        var need = parseInt(acctQtyInput ? acctQtyInput.value : '0', 10) || 0;
                        if (n > 0) {
                            acctAreChecklist.classList.add(n === need ? 'sn-valid' : 'sn-invalid');
                        }
                    }

                    updateAreMatchHint(); // refresh the live hint span
                    updatePropMatchHint(); // keep Property Number hint in sync
                }

                /* ── Standalone ARE hint refresh (no recursion) ─────────────────
                 * Called by both syncAcctAre() and syncAcctSerials() so the hint
                 * stays current whenever quantity, serials, OR ARE tokens change.
                 * Rule enforced: checked ARE count === qty === checked serial count.
                 * ─────────────────────────────────────────────────────────────── */
                function updateAreMatchHint() {
                    if (!acctAreMatchHint) return;

                    var need = parseInt(acctQtyInput ? acctQtyInput.value : '0', 10) || 0;
                    var areGot = acctAreChecklist ?
                        acctAreChecklist.querySelectorAll('input[type="checkbox"]:checked').length : 0;
                    var snGot = acctSerialChecklist ?
                        acctSerialChecklist.querySelectorAll('input[type="checkbox"]:checked').length : 0;

                    // Only show the hint when the checklist has actual checkboxes to interact with
                    var hasAreBoxes = acctAreChecklist ?
                        acctAreChecklist.querySelectorAll('input[type="checkbox"]').length > 0 : false;
                    var hasSnBoxes = acctSerialChecklist ?
                        acctSerialChecklist.querySelectorAll('input[type="checkbox"]').length > 0 : false;

                    if (!hasAreBoxes) {
                        acctAreMatchHint.style.display = 'none';
                        return;
                    }

                    acctAreMatchHint.style.display = 'block';

                    if (need === 0) {
                        acctAreMatchHint.className = 'sn-match-hint warn';
                        acctAreMatchHint.textContent = '⚠ Enter a quantity first to know how many PAR/ICS numbers to select.';
                        return;
                    }

                    if (areGot === 0) {
                        acctAreMatchHint.className = 'sn-match-hint warn';
                        acctAreMatchHint.textContent = 'Select ' + need + ' PAR/ICS number(s) — none checked yet.';
                        return;
                    }

                    // 3-way rule: ARE count, qty, and serial count must all agree
                    var areOk = (areGot === need);
                    var snOk = !hasSnBoxes || (snGot === need); // no serial boxes = skip that check

                    if (areOk && snOk) {
                        acctAreMatchHint.className = 'sn-match-hint ok';
                        acctAreMatchHint.textContent = '✓ ' + areGot + ' PAR/ICS number(s) checked' +
                            (hasSnBoxes ? ', ' + snGot + ' serial(s) selected' : '') +
                            ' — all match quantity (' + need + ').';
                    } else if (!areOk) {
                        acctAreMatchHint.className = (areGot < need) ? 'sn-match-hint error' : 'sn-match-hint error';
                        if (areGot < need) {
                            acctAreMatchHint.textContent = '✗ ' + areGot + '/' + need + ' PAR/ICS number(s) checked — need ' + (need - areGot) + ' more to match quantity.';
                        } else {
                            acctAreMatchHint.textContent = '✗ ' + areGot + '/' + need + ' PAR/ICS number(s) checked — ' + (areGot - need) + ' too many, uncheck some.';
                        }
                        // Also note serial mismatch if relevant
                        if (hasSnBoxes && snGot !== need) {
                            acctAreMatchHint.textContent += ' Serial count: ' + snGot + '/' + need + '.';
                        }
                    } else {
                        // areOk but snOk is false
                        acctAreMatchHint.className = 'sn-match-hint warn';
                        acctAreMatchHint.textContent = '⚠ ' + areGot + '/' + need + ' PAR/ICS number(s) checked — but serial count is ' +
                            snGot + '/' + need + '. Quantity, PAR/ICS numbers, and serials must all match.';
                    }
                }

                /* ── Re-check ARE boxes to match current qty (called on qty change) ── */
                function syncAcctAreAutoCheck() {
                    if (!acctAreChecklist) return;
                    var checkboxes = Array.prototype.slice.call(
                        acctAreChecklist.querySelectorAll('input[type="checkbox"]')
                    );
                    if (!checkboxes.length) return;

                    var need = parseInt(acctQtyInput ? acctQtyInput.value : '0', 10) || 0;
                    var n = Math.min(need, checkboxes.length);

                    checkboxes.forEach(function(cb, idx) {
                        var shouldCheck = idx < n;
                        cb.checked = shouldCheck;
                        if (cb.parentElement) {
                            cb.parentElement.classList.toggle('sn-checked', shouldCheck);
                        }
                    });

                    syncAcctAre(); // refresh hidden value + count badge
                }

                /* ── Build ARE / MR / ICS checklist from resolved record ────────── */
                function buildAcctAreChecklist(rec) {
                    if (!acctAreChecklist) return;
                    acctAreChecklist.innerHTML = '';
                    acctAreChecklist.className = 'serial-checklist-wrap';
                    if (acctAreHidden) acctAreHidden.value = '';

                    // Reset hint
                    if (acctAreMatchHint) {
                        acctAreMatchHint.style.display = 'none';
                        acctAreMatchHint.textContent = '';
                    }

                    if (!rec) {
                        acctAreChecklist.innerHTML = '<span class="serial-placeholder">Select an inventory item to load its ARE / MR / ICS numbers.</span>';
                        if (acctAreReqNote) acctAreReqNote.style.display = 'none';
                        return;
                    }

                    var rawAre = rec.are || '';
                    var parts = rawAre.split('/').map(function(s) {
                        return s.trim();
                    }).filter(Boolean);

                    if (parts.length === 0) {
                        acctAreChecklist.innerHTML = '<span class="serial-no-data">No ARE / MR / ICS numbers on record for this item.</span>';
                        if (acctAreReqNote) acctAreReqNote.style.display = 'none';
                        return;
                    }

                    if (acctAreReqNote) acctAreReqNote.style.display = 'inline';

                    // Determine how many to auto-check:
                    // Use qty if known; if qty === parts.length treat as full match.
                    // If qty is 0 and only 1 token, still check it.
                    var autoQty = parseInt(acctQtyInput ? acctQtyInput.value : '0', 10) || 0;
                    if (autoQty === 0 && parts.length === 1) {
                        autoQty = 1;
                    }
                    var autoCheck = Math.min(autoQty, parts.length);

                    parts.forEach(function(token, idx) {
                        var lbl = document.createElement('label');
                        lbl.className = 'serial-check-item';
                        lbl.title = token;

                        var cb = document.createElement('input');
                        cb.type = 'checkbox';
                        cb.value = token;

                        // Auto-check the first autoCheck tokens to match quantity
                        if (idx < autoCheck) {
                            cb.checked = true;
                            lbl.classList.add('sn-checked');
                        }

                        cb.addEventListener('change', function() {
                            lbl.classList.toggle('sn-checked', cb.checked);
                            syncAcctAre();
                        });

                        lbl.appendChild(cb);
                        lbl.appendChild(document.createTextNode(token));
                        acctAreChecklist.appendChild(lbl);
                    });

                    syncAcctAre(); // update hidden input + checked count after auto-check

                    // Show initial prompt if nothing auto-checked yet
                    if (acctAreMatchHint && autoCheck === 0) {
                        var need = parseInt(acctQtyInput ? acctQtyInput.value : '0', 10) || 0;
                        acctAreMatchHint.style.display = 'block';
                        acctAreMatchHint.className = 'sn-match-hint warn';
                        acctAreMatchHint.textContent = need ?
                            'Select exactly ' + need + ' PAR/ICS number(s) to match the assigned quantity.' :
                            'Enter a quantity, then select the matching PAR/ICS numbers.';
                    }
                }

                /* ── Sync checked property tokens → hidden input ─────────────── */
                function syncAcctProp() {
                    var checked = [];
                    if (acctPropChecklist) {
                        acctPropChecklist.querySelectorAll('input[type="checkbox"]:checked').forEach(function(cb) {
                            checked.push(cb.value);
                        });
                    }
                    if (acctPropHidden) acctPropHidden.value = checked.join(' / ');
                    var n = checked.length;
                    if (acctPropCheckedCount) acctPropCheckedCount.textContent = n;

                    // Apply sn-valid / sn-invalid border on the checklist wrap
                    if (acctPropChecklist) {
                        acctPropChecklist.classList.remove('sn-valid', 'sn-invalid');
                        var need = parseInt(acctQtyInput ? acctQtyInput.value : '0', 10) || 0;
                        if (n > 0) {
                            acctPropChecklist.classList.add(n === need ? 'sn-valid' : 'sn-invalid');
                        }
                    }

                    updatePropMatchHint(); // refresh the live hint span
                }

                /* ── Standalone Property-Number hint refresh (no recursion) ─────
                 * Rule enforced: checked prop count === qty === checked serial count.
                 * ─────────────────────────────────────────────────────────────── */
                function updatePropMatchHint() {
                    if (!acctPropMatchHint) return;

                    var need = parseInt(acctQtyInput ? acctQtyInput.value : '0', 10) || 0;
                    var propGot = acctPropChecklist ?
                        acctPropChecklist.querySelectorAll('input[type="checkbox"]:checked').length : 0;
                    var snGot = acctSerialChecklist ?
                        acctSerialChecklist.querySelectorAll('input[type="checkbox"]:checked').length : 0;

                    var hasPropBoxes = acctPropChecklist ?
                        acctPropChecklist.querySelectorAll('input[type="checkbox"]').length > 0 : false;
                    var hasSnBoxes = acctSerialChecklist ?
                        acctSerialChecklist.querySelectorAll('input[type="checkbox"]').length > 0 : false;

                    if (!hasPropBoxes) {
                        acctPropMatchHint.style.display = 'none';
                        return;
                    }

                    acctPropMatchHint.style.display = 'block';

                    if (need === 0) {
                        acctPropMatchHint.className = 'sn-match-hint warn';
                        acctPropMatchHint.textContent = '⚠ Enter a quantity first to know how many property numbers to select.';
                        return;
                    }

                    if (propGot === 0) {
                        acctPropMatchHint.className = 'sn-match-hint warn';
                        acctPropMatchHint.textContent = 'Select ' + need + ' property number(s) — none checked yet.';
                        return;
                    }

                    // 3-way rule: prop count, qty, and serial count must all agree
                    var propOk = (propGot === need);
                    var snOk = !hasSnBoxes || (snGot === need);

                    if (propOk && snOk) {
                        acctPropMatchHint.className = 'sn-match-hint ok';
                        acctPropMatchHint.textContent = '✓ ' + propGot + ' property number(s) checked' +
                            (hasSnBoxes ? ', ' + snGot + ' serial(s) selected' : '') +
                            ' — all match quantity (' + need + ').';
                    } else if (!propOk) {
                        if (propGot < need) {
                            acctPropMatchHint.className = 'sn-match-hint error';
                            acctPropMatchHint.textContent = '✗ ' + propGot + '/' + need + ' property number(s) checked — need ' + (need - propGot) + ' more to match quantity.';
                        } else {
                            acctPropMatchHint.className = 'sn-match-hint error';
                            acctPropMatchHint.textContent = '✗ ' + propGot + '/' + need + ' property number(s) checked — ' + (propGot - need) + ' too many, uncheck some.';
                        }
                        if (hasSnBoxes && snGot !== need) {
                            acctPropMatchHint.textContent += ' Serial count: ' + snGot + '/' + need + '.';
                        }
                    } else {
                        // propOk but snOk is false
                        acctPropMatchHint.className = 'sn-match-hint warn';
                        acctPropMatchHint.textContent = '⚠ ' + propGot + '/' + need + ' property number(s) checked — but serial count is ' +
                            snGot + '/' + need + '. Quantity, property numbers, and serials must all match.';
                    }
                }

                /* ── Re-check prop boxes to match current qty (called on qty change) ── */
                function syncAcctPropAutoCheck() {
                    if (!acctPropChecklist) return;
                    var checkboxes = Array.prototype.slice.call(
                        acctPropChecklist.querySelectorAll('input[type="checkbox"]')
                    );
                    if (!checkboxes.length) return;

                    var need = parseInt(acctQtyInput ? acctQtyInput.value : '0', 10) || 0;
                    var n = Math.min(need, checkboxes.length);

                    checkboxes.forEach(function(cb, idx) {
                        var shouldCheck = idx < n;
                        cb.checked = shouldCheck;
                        if (cb.parentElement) {
                            cb.parentElement.classList.toggle('sn-checked', shouldCheck);
                        }
                    });

                    syncAcctProp(); // refresh hidden value + count badge
                }

                /* ── Build Property Number checklist from resolved record ─────── */
                function buildAcctPropChecklist(rec) {
                    if (!acctPropChecklist) return;
                    acctPropChecklist.innerHTML = '';
                    acctPropChecklist.className = 'serial-checklist-wrap';
                    if (acctPropHidden) acctPropHidden.value = '';

                    // Reset hint
                    if (acctPropMatchHint) {
                        acctPropMatchHint.style.display = 'none';
                        acctPropMatchHint.textContent = '';
                    }

                    if (!rec) {
                        acctPropChecklist.innerHTML = '<span class="serial-placeholder">Select an inventory item to load its property numbers.</span>';
                        if (acctPropReqNote) acctPropReqNote.style.display = 'none';
                        return;
                    }

                    var rawProp = rec.prop || '';
                    var parts = rawProp.split('/').map(function(s) {
                        return s.trim();
                    }).filter(Boolean);

                    if (parts.length === 0) {
                        acctPropChecklist.innerHTML = '<span class="serial-no-data">No property numbers on record for this item.</span>';
                        if (acctPropReqNote) acctPropReqNote.style.display = 'none';
                        return;
                    }

                    if (acctPropReqNote) acctPropReqNote.style.display = 'inline';

                    var autoQty = parseInt(acctQtyInput ? acctQtyInput.value : '0', 10) || 0;
                    if (autoQty === 0 && parts.length === 1) {
                        autoQty = 1;
                    }
                    var autoCheck = Math.min(autoQty, parts.length);

                    parts.forEach(function(token, idx) {
                        var lbl = document.createElement('label');
                        lbl.className = 'serial-check-item';
                        lbl.title = token;

                        var cb = document.createElement('input');
                        cb.type = 'checkbox';
                        cb.value = token;

                        if (idx < autoCheck) {
                            cb.checked = true;
                            lbl.classList.add('sn-checked');
                        }

                        cb.addEventListener('change', function() {
                            lbl.classList.toggle('sn-checked', cb.checked);
                            syncAcctProp();
                        });

                        lbl.appendChild(cb);
                        lbl.appendChild(document.createTextNode(token));
                        acctPropChecklist.appendChild(lbl);
                    });

                    syncAcctProp(); // update hidden input + count badge after auto-check

                    // Show initial prompt if nothing auto-checked yet
                    if (acctPropMatchHint && autoCheck === 0) {
                        var need = parseInt(acctQtyInput ? acctQtyInput.value : '0', 10) || 0;
                        acctPropMatchHint.style.display = 'block';
                        acctPropMatchHint.className = 'sn-match-hint warn';
                        acctPropMatchHint.textContent = need ?
                            'Select exactly ' + need + ' property number(s) to match the assigned quantity.' :
                            'Enter a quantity, then select the matching property numbers.';
                    }
                }

                /* ── Sync checked serials → hidden input + match hint ────────── */
                function syncAcctSerials() {
                    var checked = [];
                    if (acctSerialChecklist) {
                        acctSerialChecklist.querySelectorAll('input[type="checkbox"]:checked').forEach(function(cb) {
                            checked.push(cb.value);
                        });
                    }
                    if (acctSerialHidden) acctSerialHidden.value = checked.join(' / ');

                    var need = parseInt(acctQtyInput ? acctQtyInput.value : '0', 10) || 0;
                    var got = checked.length;

                    if (acctSerialChecklist) {
                        acctSerialChecklist.classList.remove('sn-valid', 'sn-invalid');
                        if (got > 0) acctSerialChecklist.classList.add(got === need ? 'sn-valid' : 'sn-invalid');
                    }

                    if (acctSnMatchHint) {
                        acctSnMatchHint.style.display = 'block';
                        if (need === 0) {
                            acctSnMatchHint.className = 'sn-match-hint warn';
                            acctSnMatchHint.textContent = '⚠ Enter a quantity first to know how many serials to select.';
                        } else if (got === 0) {
                            acctSnMatchHint.className = 'sn-match-hint warn';
                            acctSnMatchHint.textContent = 'Select ' + need + ' serial number(s) — none checked yet.';
                        } else if (got === need) {
                            acctSnMatchHint.className = 'sn-match-hint ok';
                            acctSnMatchHint.textContent = '✓ ' + got + '/' + need + ' serial(s) selected — matches quantity.';
                        } else if (got < need) {
                            acctSnMatchHint.className = 'sn-match-hint error';
                            acctSnMatchHint.textContent = '✗ ' + got + '/' + need + ' selected — need ' + (need - got) + ' more.';
                        } else {
                            acctSnMatchHint.className = 'sn-match-hint error';
                            acctSnMatchHint.textContent = '✗ ' + got + '/' + need + ' selected — ' + (got - need) + ' too many, uncheck some.';
                        }
                        if (acctSnReqCount) acctSnReqCount.textContent = need || '…';
                    }

                    updateAreMatchHint(); // keep the PAR/ICS hint in sync with serial changes
                    updatePropMatchHint(); // keep the Property Number hint in sync with serial changes
                }

                /* ── Rebuild serial checklist from resolved record ───────────── */
                function buildAcctSerialChecklist(rec) {
                    if (!acctSerialChecklist) return;
                    acctSerialChecklist.innerHTML = '';
                    acctSerialChecklist.className = 'serial-checklist-wrap';
                    if (acctSerialHidden) acctSerialHidden.value = '';
                    if (acctSnMatchHint) {
                        acctSnMatchHint.style.display = 'none';
                        acctSnMatchHint.textContent = '';
                    }

                    if (!rec) {
                        acctSerialChecklist.innerHTML = '<span class="serial-placeholder">Select an inventory item to load its serial numbers.</span>';
                        if (acctSnReqNote) acctSnReqNote.style.display = 'none';
                        return;
                    }

                    var rawSerial = rec.sn || '';
                    var parts = rawSerial.split('/').map(function(s) {
                        return s.trim();
                    }).filter(Boolean);

                    if (parts.length === 0) {
                        acctSerialChecklist.innerHTML = '<span class="serial-no-data">No serial numbers on record for this item.</span>';
                        if (acctSnReqNote) acctSnReqNote.style.display = 'none';
                        if (acctSnMatchHint) acctSnMatchHint.style.display = 'none';
                        return;
                    }

                    var needQty = parseInt(acctQtyInput ? acctQtyInput.value : '0', 10) || 0;
                    if (acctSnReqNote) {
                        acctSnReqNote.style.display = 'inline';
                        if (acctSnReqCount) acctSnReqCount.textContent = needQty || '…';
                    }

                    parts.forEach(function(sn) {
                        var lbl = document.createElement('label');
                        lbl.className = 'serial-check-item';
                        lbl.title = sn;

                        var cb = document.createElement('input');
                        cb.type = 'checkbox';
                        cb.value = sn;
                        cb.addEventListener('change', function() {
                            lbl.classList.toggle('sn-checked', cb.checked);
                            syncAcctSerials();
                        });

                        lbl.appendChild(cb);
                        lbl.appendChild(document.createTextNode(sn));
                        acctSerialChecklist.appendChild(lbl);
                    });

                    if (acctSnMatchHint) {
                        acctSnMatchHint.style.display = 'block';
                        acctSnMatchHint.className = 'sn-match-hint warn';
                        acctSnMatchHint.textContent = needQty ?
                            'Select exactly ' + needQty + ' serial number(s) to match the assigned quantity.' :
                            'Enter a quantity, then select the matching serial numbers.';
                    }
                }

                /* ── Apply a fully resolved inventory record to the form ──────── */
                function applyAcctRecord(rec) {
                    _acctResolvedRec = rec;
                    if (acctItemIdHidden) acctItemIdHidden.value = rec ? rec.id : '';

                    // Quantity
                    var stock = rec ? rec.qty : 0;
                    if (stock > 0) {
                        acctQtyInput.disabled = false;
                        acctQtyInput.max = stock;
                        acctQtyInput.placeholder = '1 – ' + stock;
                        if (acctQtyHint) {
                            acctQtyHint.textContent = '✓ Available stock: ' + stock + ' unit(s)';
                            acctQtyHint.style.color = '#696cff';
                            acctQtyHint.style.display = 'block';
                        }
                    } else {
                        acctQtyInput.disabled = true;
                        acctQtyInput.max = '';
                        acctQtyInput.value = '';
                        acctQtyInput.placeholder = 'No stock available';
                        if (acctQtyHint) {
                            acctQtyHint.textContent = '⚠ This item has no available stock.';
                            acctQtyHint.style.color = '#ea5455';
                            acctQtyHint.style.display = 'block';
                        }
                    }

                    // Property number checklist
                    buildAcctPropChecklist(rec);

                    // Rebuild ARE and serial checklists
                    buildAcctAreChecklist(rec);
                    buildAcctSerialChecklist(rec);

                    // Resolve hint
                    if (acctResolveHint) {
                        if (rec) {
                            acctResolveHint.textContent = '✓ Matched Inventory Record #' + rec.id;
                            acctResolveHint.style.color = '#71dd37';
                            acctResolveHint.style.display = 'block';
                        } else {
                            acctResolveHint.textContent = '';
                            acctResolveHint.style.display = 'none';
                        }
                    }
                }

                /* ── Clear resolved data when item_name changes ──────────────── */
                function clearAcctResolved() {
                    _acctResolvedRec = null;
                    if (acctItemIdHidden) acctItemIdHidden.value = '';
                    if (acctQtyInput) {
                        acctQtyInput.disabled = true;
                        acctQtyInput.value = '';
                        acctQtyInput.max = '';
                        acctQtyInput.placeholder = 'Select inventory item first';
                    }
                    if (acctQtyHint) {
                        acctQtyHint.style.display = 'none';
                        acctQtyHint.textContent = '';
                    }
                    buildAcctPropChecklist(null);
                    buildAcctAreChecklist(null);
                    buildAcctSerialChecklist(null);
                    if (acctResolveHint) {
                        acctResolveHint.textContent = '';
                        acctResolveHint.style.display = 'none';
                    }
                }

                /* ── Open the particulars suggestion dropdown ─────────────────── */
                function openParticularsDropdown(recs) {
                    if (!acctParticularsDropdown) return;
                    acctParticularsDropdown.innerHTML = '';
                    if (!recs || !recs.length) {
                        acctParticularsDropdown.classList.remove('open');
                        return;
                    }
                    recs.slice(0, 30).forEach(function(rec) {
                        var el = document.createElement('div');
                        el.className = 'emp-dropdown-item';
                        el.textContent = rec.p + (rec.qty > 0 ? ' (Stock: ' + rec.qty + ')' : ' ⚠ No stock');
                        el.addEventListener('mousedown', function(e) {
                            e.preventDefault();
                            if (acctParticularsInput) acctParticularsInput.value = rec.p;
                            acctParticularsDropdown.classList.remove('open');
                            applyAcctRecord(rec);
                        });
                        acctParticularsDropdown.appendChild(el);
                    });
                    acctParticularsDropdown.classList.add('open');
                }

                /* ── Try to resolve item from currently typed particulars ──────── */
                function tryResolveAcctParticulars() {
                    var selectedName = acctItemNameSelect ? acctItemNameSelect.value : '';
                    var typedPart = acctParticularsInput ? acctParticularsInput.value.trim() : '';
                    var recs = ACCT_ITEM_MAP[selectedName] || [];

                    // Case-insensitive exact match
                    var typedLower = typedPart.toLowerCase();
                    var match = null;
                    for (var i = 0; i < recs.length; i++) {
                        if (recs[i].p.toLowerCase() === typedLower) {
                            match = recs[i];
                            break;
                        }
                    }

                    if (match) {
                        applyAcctRecord(match);
                    } else if (typedPart) {
                        // New particular typed — clear ID (new entry under same item_name)
                        if (acctItemIdHidden) acctItemIdHidden.value = '';
                        _acctResolvedRec = null;
                        if (acctQtyInput) {
                            acctQtyInput.disabled = true;
                            acctQtyInput.value = '';
                            acctQtyInput.placeholder = 'N/A — new particular';
                        }
                        if (acctQtyHint) {
                            acctQtyHint.textContent = '⚠ New particular — no matching record yet.';
                            acctQtyHint.style.color = '#ff9f43';
                            acctQtyHint.style.display = 'block';
                        }
                        buildAcctAreChecklist(null);
                        buildAcctSerialChecklist(null);
                        if (acctResolveHint) {
                            acctResolveHint.textContent = '⚠ New particular — will use the latest matching item_name record.';
                            acctResolveHint.style.color = '#ff9f43';
                            acctResolveHint.style.display = 'block';
                        }
                        // Fall back: resolve to the first record of the item_name so inventory_item_id is still valid
                        if (recs.length > 0 && acctItemIdHidden) acctItemIdHidden.value = recs[0].id;
                    } else {
                        clearAcctResolved();
                    }
                }

                /* ── Item name (category) select change ──────────────────────── */
                if (acctItemNameSelect) {
                    acctItemNameSelect.addEventListener('change', function() {
                        var selectedName = acctItemNameSelect.value;
                        clearAcctResolved();

                        if (!selectedName) {
                            if (acctParticularsInput) {
                                acctParticularsInput.disabled = true;
                                acctParticularsInput.value = '';
                                acctParticularsInput.placeholder = 'Select an inventory item first…';
                            }
                            return;
                        }

                        // Enable particulars input
                        if (acctParticularsInput) {
                            acctParticularsInput.disabled = false;
                            acctParticularsInput.value = '';
                            acctParticularsInput.placeholder = 'Type or select a particular…';
                        }

                        var recs = ACCT_ITEM_MAP[selectedName] || [];

                        if (recs.length === 1) {
                            // Only one particular — auto-select it
                            if (acctParticularsInput) acctParticularsInput.value = recs[0].p;
                            applyAcctRecord(recs[0]);
                        } else if (recs.length > 1) {
                            // Multiple particulars — show dropdown and focus
                            openParticularsDropdown(recs);
                            if (acctParticularsInput) acctParticularsInput.focus();
                        }
                    });
                }

                /* ── Particulars input: filter suggestions while typing ─────── */
                if (acctParticularsInput) {
                    acctParticularsInput.addEventListener('input', function() {
                        var selectedName = acctItemNameSelect ? acctItemNameSelect.value : '';
                        var q = acctParticularsInput.value.trim().toLowerCase();
                        var recs = ACCT_ITEM_MAP[selectedName] || [];
                        var filtered = q ?
                            recs.filter(function(r) {
                                return r.p.toLowerCase().indexOf(q) !== -1;
                            }) :
                            recs;
                        openParticularsDropdown(filtered);
                    });
                    acctParticularsInput.addEventListener('blur', function() {
                        setTimeout(function() {
                            if (acctParticularsDropdown) acctParticularsDropdown.classList.remove('open');
                            tryResolveAcctParticulars();
                        }, 180);
                    });
                    acctParticularsInput.addEventListener('focus', function() {
                        // Re-open suggestions when user focuses the field
                        var selectedName = acctItemNameSelect ? acctItemNameSelect.value : '';
                        var q = acctParticularsInput.value.trim().toLowerCase();
                        var recs = ACCT_ITEM_MAP[selectedName] || [];
                        var filtered = q ?
                            recs.filter(function(r) {
                                return r.p.toLowerCase().indexOf(q) !== -1;
                            }) :
                            recs;
                        openParticularsDropdown(filtered);
                    });
                }

                /* ── Quantity change → refresh serial match hint + ARE auto-check ── */
                if (acctQtyInput) {
                    acctQtyInput.addEventListener('input', function() {
                        var n = parseInt(acctQtyInput.value, 10) || 0;
                        if (acctSnReqCount) acctSnReqCount.textContent = n || '…';
                        syncAcctSerials();
                        syncAcctAreAutoCheck(); // re-check ARE boxes to match new qty
                        syncAcctPropAutoCheck(); // re-check Property Number boxes to match new qty
                    });
                }

                /* ── Submit guard: enforce serial count + ARE/PAR/ICS count ── */
                var acctForm = acctTab.querySelector('form');
                if (acctForm) {
                    acctForm.addEventListener('submit', function(e) {
                        var need = parseInt(acctQtyInput ? acctQtyInput.value : '0', 10) || 0;

                        // ── Serial count guard ────────────────────────
                        var hasSns = _acctResolvedRec && (_acctResolvedRec.sn || '').split('/').map(function(s) {
                            return s.trim();
                        }).filter(Boolean).length > 0;
                        if (hasSns) {
                            var snChecked = acctSerialChecklist ?
                                acctSerialChecklist.querySelectorAll('input[type="checkbox"]:checked').length : 0;

                            if (need > 0 && snChecked !== need) {
                                e.preventDefault();
                                if (acctSnMatchHint) {
                                    acctSnMatchHint.className = 'sn-match-hint error';
                                    acctSnMatchHint.style.display = 'block';
                                    acctSnMatchHint.textContent = '✗ Cannot save — ' + snChecked + ' serial(s) checked but quantity is ' + need + '. They must match exactly.';
                                }
                                if (acctSerialChecklist) acctSerialChecklist.classList.add('sn-invalid');
                                acctSerialChecklist && acctSerialChecklist.scrollIntoView({
                                    behavior: 'smooth',
                                    block: 'center'
                                });
                                return;
                            }
                        }

                        // ── ARE / PAR / ICS count guard ───────────────────
                        var hasAres = _acctResolvedRec && (_acctResolvedRec.are || '').split('/').map(function(s) {
                            return s.trim();
                        }).filter(Boolean).length > 0;
                        if (hasAres) {
                            var areChecked = acctAreChecklist ?
                                acctAreChecklist.querySelectorAll('input[type="checkbox"]:checked').length : 0;

                            if (need > 0 && areChecked !== need) {
                                e.preventDefault();
                                if (acctAreCheckedCount) acctAreCheckedCount.textContent = areChecked;
                                if (acctAreReqNote) {
                                    acctAreReqNote.style.display = 'inline';
                                    acctAreReqNote.style.color = '#ea5455';
                                }
                                if (acctAreMatchHint) {
                                    acctAreMatchHint.style.display = 'block';
                                    acctAreMatchHint.className = 'sn-match-hint error';
                                    acctAreMatchHint.textContent = '✗ Cannot save — ' + areChecked + ' PAR/ICS number(s) checked but quantity is ' + need + '. They must match exactly.';
                                }
                                if (acctAreChecklist) acctAreChecklist.classList.add('sn-invalid');
                                acctAreChecklist && acctAreChecklist.scrollIntoView({
                                    behavior: 'smooth',
                                    block: 'center'
                                });
                                return;
                            } else {
                                if (acctAreReqNote) {
                                    acctAreReqNote.style.color = '';
                                }
                                if (acctAreChecklist) acctAreChecklist.classList.remove('sn-invalid');
                            }
                        }

                        // ── Property Number count guard ────────────────────
                        var hasProps = _acctResolvedRec && (_acctResolvedRec.prop || '').split('/').map(function(s) {
                            return s.trim();
                        }).filter(Boolean).length > 0;
                        if (hasProps) {
                            var propChecked = acctPropChecklist ?
                                acctPropChecklist.querySelectorAll('input[type="checkbox"]:checked').length : 0;

                            if (need > 0 && propChecked !== need) {
                                e.preventDefault();
                                if (acctPropCheckedCount) acctPropCheckedCount.textContent = propChecked;
                                if (acctPropReqNote) {
                                    acctPropReqNote.style.display = 'inline';
                                    acctPropReqNote.style.color = '#ea5455';
                                }
                                if (acctPropMatchHint) {
                                    acctPropMatchHint.style.display = 'block';
                                    acctPropMatchHint.className = 'sn-match-hint error';
                                    acctPropMatchHint.textContent = '✗ Cannot save — ' + propChecked + ' property number(s) checked but quantity is ' + need + '. They must match exactly.';
                                }
                                if (acctPropChecklist) acctPropChecklist.classList.add('sn-invalid');
                                acctPropChecklist && acctPropChecklist.scrollIntoView({
                                    behavior: 'smooth',
                                    block: 'center'
                                });
                                return;
                            } else {
                                if (acctPropReqNote) {
                                    acctPropReqNote.style.color = '';
                                }
                                if (acctPropChecklist) acctPropChecklist.classList.remove('sn-invalid');
                            }
                        }
                    });
                }
                // Employee autocomplete for person_name
                var personWrap = acctTab.querySelector('[data-autocomplete="person_name"]');
                var acctEmpIdField = document.getElementById('acct_employee_id');
                if (personWrap) {
                    initAutocomplete(personWrap, function(emp) {
                        // Fill with end_user_id_number (emp_no) from cao_employee
                        if (acctEmpIdField) acctEmpIdField.value = emp ? (emp.emp_no || '') : '';
                    });
                }
            }

        })();
    </script>

    <!-- ── Inventory Data Table: filter + edit modal ── -->
    <script>
        /* ── Table search / status filter ──────────────────────────────────────── */
        function filterInvTable() {
            var q = (document.getElementById('inv_tbl_search').value || '').toLowerCase().trim();
            var status = (document.getElementById('inv_tbl_status_filter').value || '').toLowerCase();
            var rows = document.querySelectorAll('#inv_tbl_body tr[data-id]');
            rows.forEach(function(row) {
                var text = row.textContent.toLowerCase();
                var rowSt = (row.dataset.status || '').toLowerCase();
                var matchQ = !q || text.includes(q);
                var matchS = !status || rowSt === status;
                row.dataset.filtered = (matchQ && matchS) ? '' : 'hidden';
                row.style.display = (matchQ && matchS) ? '' : 'none';
            });
        }

        /* ── Edit modal open / close / calc ────────────────────────────────────── */
        function openInvEditModal(row) {
            var d = row.dataset;
            document.getElementById('inv_edit_modal_id').textContent = '#' + d.id;
            document.getElementById('inv_edit_id').value = d.id;
            document.getElementById('inv_edit_item_name').value = d.itemName || '';
            document.getElementById('inv_edit_particulars').value = d.particulars || '';
            document.getElementById('inv_edit_are').value = d.are || '';
            document.getElementById('inv_edit_serial').value = d.serial || '';
            document.getElementById('inv_edit_prop').value = d.prop || '';
            document.getElementById('inv_edit_qty').value = d.qty || '0';
            document.getElementById('inv_edit_value').value = d.value || '0';
            document.getElementById('inv_edit_total').value = d.total || '0';
            document.getElementById('inv_edit_delivered').value = d.delivered || '';

            var statusSel = document.getElementById('inv_edit_status');
            for (var i = 0; i < statusSel.options.length; i++) {
                statusSel.options[i].selected = (statusSel.options[i].value === d.status);
            }

            // Re-generate form_token so the edit submission has a fresh token
            // (the modal uses the page-level form_token already embedded)
            document.getElementById('inv_edit_overlay').style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }

        function closeInvEditModal() {
            document.getElementById('inv_edit_overlay').style.display = 'none';
            document.body.style.overflow = '';
        }

        function invEditCalcTotal() {
            var qty = parseFloat(document.getElementById('inv_edit_qty').value) || 0;
            var val = parseFloat(document.getElementById('inv_edit_value').value) || 0;
            document.getElementById('inv_edit_total').value = (qty * val).toFixed(2);
        }

        /* ── Close modal on Escape key ─────────────────────────────────────────── */
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') closeInvEditModal();
        });
    </script>
</body>

</html>