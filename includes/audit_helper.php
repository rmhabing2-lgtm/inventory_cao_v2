<?php
/**
 * AUDIT & LOGGING HELPER
 * This file handles database-level audit trails and general system activity logging.
 */

/**
 * GLOBAL LOGGING WRAPPER
 * A simplified version to log general events that don't require row comparisons.
 */
function log_activity($conn, $module, $action, $record_id = 0, $details = null) {
    $user_id = $_SESSION['id'] ?? 0;
    // We pass null for old data and the details for new data to satisfy the schema
    log_system_edit($conn, $user_id, $module, $record_id, null, $details, $action);
}

/**
 * RE-ENGINEERED LOGGING ENGINE
 * Compares two versions of a row and saves to audit_logs only if changes are detected.
 */
function log_system_edit($conn, $user_id, $tableName, $record_id, $old_values, $new_values, $forcedAction = null) {
    // 1. Convert to arrays if they are objects
    $old = ($old_values !== null) ? (array)$old_values : [];
    $new = ($new_values !== null) ? (array)$new_values : [];

    // 2. Identify the action
    if ($forcedAction) {
        $action = strtoupper($forcedAction);
    } else {
        $action = 'UPDATE';
        if (empty($old) && !empty($new)) $action = 'CREATE';
        if (empty($new) && !empty($old)) $action = 'DELETE';
    }

    // 3. If it's an update, check if anything ACTUALLY changed
    if ($action === 'UPDATE') {
        $hasChanged = false;
        foreach ($new as $key => $value) {
            // Skip comparison for keys that don't exist in old or match exactly
            // Using != to allow string "1" to match integer 1
            if (!array_key_exists($key, $old) || $old[$key] != $value) {
                $hasChanged = true;
                break;
            }
        }
        
        if (!$hasChanged) {
            return; // No changes detected, exit function
        }
    }

    // 4. Fetch User Name for the log
    $user_full_name = 'System';
    if ($user_id > 0) {
        $u_stmt = $conn->prepare("SELECT first_name, last_name FROM user WHERE id = ?");
        $u_stmt->bind_param("i", $user_id);
        $u_stmt->execute();
        $u_res = $u_stmt->get_result();
        if ($u_row = $u_res->fetch_assoc()) {
            $user_full_name = trim($u_row['first_name'] . ' ' . $u_row['last_name']);
        }
        $u_stmt->close();
    }

    // 5. Prepare JSON data
    $old_json = !empty($old) ? json_encode($old) : null;
    $new_json = !empty($new) ? json_encode($new) : null;

    // 6. Record to the logs
    // Schema: user_id, user_name, module, action, record_id, old_data, new_data
    $sql = "INSERT INTO audit_logs (user_id, user_name, module, action, record_id, old_data, new_data) 
            VALUES (?, ?, ?, ?, ?, ?, ?)";
    
    if ($stmt = $conn->prepare($sql)) {
        // i=int, s=string
        // i (user_id), s (user_name), s (tableName), s (action), i (record_id), s (old_json), s (new_json)
        $stmt->bind_param("isssiss", $user_id, $user_full_name, $tableName, $action, $record_id, $old_json, $new_json);
        $stmt->execute();
        $stmt->close();
    }
}