<?php
/**
 * borrow_items_api.php — Revised
 * ================================
 * Changes:
 *  1. New ?fetch_accountable_serials=1&accountable_id=X endpoint — returns parsed serial
 *     numbers + person_name for the Borrow and Return modals.
 *  2. Borrowed items query now selects accountable_id, serial_number so the Return
 *     modal can show which serials are being given back.
 *  3. Role-based scoping for STAFF on the borrowed items endpoint (same rule as the
 *     main borrow_items.php page — staff sees only their own transactions).
 *  4. borrower_name derived from b.to_person (already stored at borrow time) so no
 *     extra JOIN is needed; avoids dual-table complexity.
 */

require_once __DIR__ . '/../includes/session.php';
require_login();
require_once 'config.php';

header('Content-Type: application/json');

$sessionUserId = (int)($_SESSION['id'] ?? 0);
$sessionRole   = strtoupper($_SESSION['role'] ?? 'STAFF');

// ============================================================
// Endpoint: fetch serial numbers (+ holder name) for a single
// accountable_item — used by the Borrow modal AND Return modal.
// ============================================================
if (isset($_GET['fetch_accountable_serials'])) {
    $accId = filter_input(INPUT_GET, 'accountable_id', FILTER_VALIDATE_INT);
    if (!$accId) {
        echo json_encode(['success' => false, 'serials' => [], 'person_name' => '']);
        exit;
    }

    $stmt = $conn->prepare(
        "SELECT ai.serial_number, ai.person_name, ai.assigned_quantity,
                ii.item_name, ii.are_mr_ics_num, ii.property_number
         FROM accountable_items ai
         JOIN inventory_items ii ON ii.id = ai.inventory_item_id
         WHERE ai.id = ? AND ai.is_deleted = 0"
    );
    $stmt->bind_param('i', $accId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $serials = [];
    if ($row && trim((string)$row['serial_number']) !== '') {
        // Separator stored as  " / "  (space-slash-space)
        $serials = array_values(
            array_filter(
                array_map('trim', explode('/', (string)$row['serial_number']))
            )
        );
    }

    echo json_encode([
        'success'           => true,
        'serials'           => $serials,
        'person_name'       => $row['person_name']       ?? '',
        'item_name'         => $row['item_name']         ?? '',
        'assigned_quantity' => (int)($row['assigned_quantity'] ?? 0),
        'are_mr_ics_num'    => $row['are_mr_ics_num']    ?? '',
        'property_number'   => $row['property_number']   ?? '',
    ]);
    exit;
}

// ============================================================
// Endpoint: fetch full details of a single borrow record
// Used by the Admin Return Confirmation modal.
// ============================================================
if (isset($_GET['fetch_all_borrow_details']) || isset($_GET['fetch_borrow_details'])) {
    $borrowId = filter_input(INPUT_GET, 'borrow_id', FILTER_VALIDATE_INT);
    if (!$borrowId) {
        echo json_encode(['success' => false]);
        exit;
    }

    $stmt = $conn->prepare(
        "SELECT b.borrow_id, b.accountable_id, b.inventory_item_id,
                b.from_person, b.to_person, b.borrower_employee_id, b.quantity,
                b.borrow_date, b.return_date, b.return_request_status,
                b.return_requested_by, b.return_requested_at,
                b.return_approved_by, b.return_approved_at, b.return_decision_remarks,
                b.are_mr_ics_num, b.property_number, b.po_number, b.account_code,
                b.old_account_code, b.reference_no, b.serial_number, b.is_returned,
                b.status, b.remarks, b.requested_by, b.requested_at,
                b.approved_by, b.approved_at, b.decision_remarks,
                ii.item_name
         FROM borrowed_items b
         JOIN inventory_items ii ON ii.id = b.inventory_item_id
         WHERE b.borrow_id = ? AND b.is_returned = 0"
    );
    $stmt->bind_param('i', $borrowId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) {
        echo json_encode(['success' => false, 'msg' => 'Record not found or already returned']);
        exit;
    }

    // Parse serials
    $serials = [];
    if (!empty(trim((string)$row['serial_number']))) {
        $serials = array_values(array_filter(
            array_map('trim', explode('/', (string)$row['serial_number']))
        ));
    }

    echo json_encode([
        'success'                 => true,
        'borrow_id'               => (int)$row['borrow_id'],
        'accountable_id'          => (int)$row['accountable_id'],
        'inventory_item_id'       => (int)$row['inventory_item_id'],
        'item_name'               => $row['item_name'],
        'from_person'             => $row['from_person'],
        'to_person'               => $row['to_person'],
        'borrower_employee_id'    => (int)$row['borrower_employee_id'],
        'quantity'                => (int)$row['quantity'],
        'borrow_date'             => $row['borrow_date'] ?? null,
        'return_date'             => $row['return_date'] ?? null,
        'are_mr_ics_num'          => $row['are_mr_ics_num'] ?? '',
        'property_number'         => $row['property_number'] ?? '',
        'po_number'               => $row['po_number'] ?? '',
        'account_code'            => $row['account_code'] ?? '',
        'old_account_code'        => $row['old_account_code'] ?? '',
        'reference_no'            => $row['reference_no'] ?? '',
        'serial_number'           => $row['serial_number'] ?? '',
        'serials'                 => $serials,
        'is_returned'             => (int)$row['is_returned'],
        'status'                  => $row['status'] ?? '',
        'remarks'                 => $row['remarks'] ?? '',
        'requested_by'            => (int)$row['requested_by'],
        'requested_at'            => $row['requested_at'] ?? null,
        'approved_by'             => (int)$row['approved_by'],
        'approved_at'             => $row['approved_at'] ?? null,
        'decision_remarks'        => $row['decision_remarks'] ?? '',
        'return_request_status'   => $row['return_request_status'] ?? '',
        'return_requested_by'     => (int)$row['return_requested_by'],
        'return_requested_at'     => $row['return_requested_at'] ?? null,
        'return_approved_by'      => (int)$row['return_approved_by'],
        'return_approved_at'      => $row['return_approved_at'] ?? null,
        'return_decision_remarks' => $row['return_decision_remarks'] ?? '',
    ]);
    exit;
}

// ============================================================
// Endpoint: paginated search for Available Items + Borrowed
// ============================================================
try {
    $page_size = isset($_GET['page_size']) ? max(1, intval($_GET['page_size'])) : 10;

    // ----------------------------------------------------------
    // ACCOUNTABLE ITEMS (Available Items table)
    // ----------------------------------------------------------
    $account_page   = max(1, (int)($_GET['account_page'] ?? 1));
    $account_offset = ($account_page - 1) * $page_size;
    $account_search = trim((string)($_GET['account_search'] ?? ''));

    $accWhere  = 'ai.is_deleted = 0 AND ai.assigned_quantity > 0';
    $accParams = [];
    $accTypes  = '';

    if ($account_search !== '') {
        $accWhere   .= ' AND (ii.item_name LIKE ? OR ai.person_name LIKE ?)';
        $like        = "%{$account_search}%";
        $accParams[] = $like;
        $accParams[] = $like;
        $accTypes   .= 'ss';
    }

    // Total count
    $stmtCountAcc = $conn->prepare(
        "SELECT COUNT(*) AS c
         FROM accountable_items ai
         JOIN inventory_items ii ON ii.id = ai.inventory_item_id
         WHERE {$accWhere}"
    );
    if ($accParams) {
        $bind = array_merge([$accTypes], $accParams);
        $refs = [];
        foreach ($bind as $k => &$v) $refs[$k] = &$v;
        call_user_func_array([$stmtCountAcc, 'bind_param'], $refs);
    }
    $stmtCountAcc->execute();
    $totalAccountables = (int)($stmtCountAcc->get_result()->fetch_assoc()['c'] ?? 0);
    $stmtCountAcc->close();

    // Paginated rows — include serial_number so the modal can parse it
    $stmtAcc = $conn->prepare(
        "SELECT ai.id, ii.item_name, ai.person_name, ai.assigned_quantity,
                ai.are_mr_ics_num, ai.serial_number, ai.inventory_item_id
         FROM accountable_items ai
         JOIN inventory_items ii ON ii.id = ai.inventory_item_id
         WHERE {$accWhere}
         ORDER BY ai.person_name, ii.item_name
         LIMIT ?, ?"
    );
    $bindTypes  = $accTypes . 'ii';
    $bindParams = array_merge($accParams, [$account_offset, $page_size]);
    $refs = [&$bindTypes];
    foreach ($bindParams as $k => $v) { $refs[] = &$bindParams[$k]; }
    call_user_func_array([$stmtAcc, 'bind_param'], $refs);
    $stmtAcc->execute();
    $accRes = $stmtAcc->get_result();

    $accountItems = [];
    while ($r = $accRes->fetch_assoc()) {
        $accountItems[] = [
            'id'                => (int)$r['id'],
            'item_name'         => $r['item_name'],
            'person_name'       => $r['person_name'],
            'assigned_quantity' => (int)$r['assigned_quantity'],
            'are_mr_ics_num'    => $r['are_mr_ics_num'],
            'serial_number'     => $r['serial_number'] ?? '',
            'inventory_item_id' => (int)$r['inventory_item_id'],
        ];
    }
    $stmtAcc->close();

    // ----------------------------------------------------------
    // BORROWED ITEMS (Active Transactions table)
    // ----------------------------------------------------------
    $borrow_page   = max(1, (int)($_GET['borrow_page'] ?? 1));
    $borrow_offset = ($borrow_page - 1) * $page_size;
    $borrow_search = trim((string)($_GET['borrow_search'] ?? ''));

    // Role-scoped base condition — STAFF see only their own records
    $borWhere  = '1=1';
    $borParams = [];
    $borTypes  = '';

    if ($sessionRole === 'STAFF') {
        $borWhere   .= ' AND b.requested_by = ?';
        $borParams[] = $sessionUserId;
        $borTypes   .= 'i';
    }

    if ($borrow_search !== '') {
        $like        = "%{$borrow_search}%";
        $borWhere   .= ' AND (ii.item_name LIKE ? OR b.from_person LIKE ? OR b.to_person LIKE ? OR b.reference_no LIKE ?)';
        $borParams[] = $like; $borParams[] = $like;
        $borParams[] = $like; $borParams[] = $like;
        $borTypes   .= 'ssss';
    }

    // Total count
    $stmtCountBor = $conn->prepare(
        "SELECT COUNT(*) AS c
         FROM borrowed_items b
         JOIN inventory_items ii ON ii.id = b.inventory_item_id
         WHERE {$borWhere}"
    );
    if ($borParams) {
        $bind = array_merge([$borTypes], $borParams);
        $refs = [];
        foreach ($bind as $k => &$v) $refs[$k] = &$v;
        call_user_func_array([$stmtCountBor, 'bind_param'], $refs);
    }
    $stmtCountBor->execute();
    $totalBorrowed = (int)($stmtCountBor->get_result()->fetch_assoc()['c'] ?? 0);
    $stmtCountBor->close();

    // Paginated rows — now includes accountable_id + serial_number for Return modal
    $stmtB = $conn->prepare(
        "SELECT b.borrow_id, b.accountable_id, b.inventory_item_id,
                ii.item_name,
                b.from_person, b.to_person, b.quantity,
                b.borrow_date, b.reference_no, b.status,
                b.is_returned, b.return_date,
                b.decision_remarks, b.requested_by,
                b.serial_number, b.are_mr_ics_num, b.property_number,
                b.return_request_status
         FROM borrowed_items b
         JOIN inventory_items ii ON ii.id = b.inventory_item_id
         WHERE {$borWhere}
         ORDER BY b.borrow_date DESC
         LIMIT ?, ?"
    );
    $bBindTypes  = $borTypes . 'ii';
    $bBindParams = array_merge($borParams, [$borrow_offset, $page_size]);
    $refs = [&$bBindTypes];
    foreach ($bBindParams as $k => $v) { $refs[] = &$bBindParams[$k]; }
    call_user_func_array([$stmtB, 'bind_param'], $refs);
    $stmtB->execute();
    $borrowRes = $stmtB->get_result();

    $borrowItems = [];
    while ($r = $borrowRes->fetch_assoc()) {
        $borrowItems[] = [
            'borrow_id'            => (int)$r['borrow_id'],
            'accountable_id'       => (int)$r['accountable_id'],
            'inventory_item_id'    => (int)$r['inventory_item_id'],
            'item_name'            => $r['item_name'],
            'from_person'          => $r['from_person'],
            'to_person'            => $r['to_person'],   // stored borrower name
            'quantity'             => (int)$r['quantity'],
            'borrow_date'          => $r['borrow_date']
                                      ? date('M d, Y', strtotime($r['borrow_date'])) : null,
            'reference_no'         => $r['reference_no'],
            'status'               => $r['status'],
            'is_returned'          => (int)$r['is_returned'],
            'return_date'          => $r['return_date']
                                      ? date('M d, Y', strtotime($r['return_date'])) : null,
            'decision_remarks'     => $r['decision_remarks'] ?? '',
            'requested_by'         => (int)($r['requested_by'] ?? 0),
            'serial_number'        => $r['serial_number'] ?? '',
            'are_mr_ics_num'       => $r['are_mr_ics_num'] ?? '',
            'property_number'      => $r['property_number'] ?? '',
            'return_request_status'=> $r['return_request_status'] ?? null,
        ];
    }
    $stmtB->close();

    echo json_encode([
        'success'     => true,
        'accountables' => [
            'items'     => $accountItems,
            'total'     => $totalAccountables,
            'page'      => $account_page,
            'page_size' => $page_size,
        ],
        'borrowed' => [
            'items'     => $borrowItems,
            'total'     => $totalBorrowed,
            'page'      => $borrow_page,
            'page_size' => $page_size,
        ],
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'msg' => $e->getMessage()]);
}


exit;