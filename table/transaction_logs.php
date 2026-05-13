<?php
require_once __DIR__ . '/../includes/session.php';
require_login();
require_once 'config.php';

// Helper: bind params dynamically
if (!function_exists('bind_params_dyn')) {
    function bind_params_dyn(mysqli_stmt $stmt, string $types, array $params)
    {
        if ($types === '') return;
        $refs = [];
        $refs[] = &$types;
        foreach ($params as $k => $v) {
            $refs[] = &$params[$k];
        }
        return call_user_func_array([$stmt, 'bind_param'], $refs);
    }
}

// -----------------------------------------------------------------------------
// UI Configuration
// -----------------------------------------------------------------------------
$active_tab = $_GET['tab'] ?? 'movements'; // 'movements' or 'edits'
$per_page = isset($_GET['per_page']) ? max(10, intval($_GET['per_page'])) : 20;
$current_page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($current_page - 1) * $per_page;

// Common Filters
$search = trim($_GET['search'] ?? '');
$date_from = trim($_GET['date_from'] ?? '');
$date_to = trim($_GET['date_to'] ?? '');

$where = [];
$types = '';
$params = [];

// Apply Date Filters (Common to both tabs)
if ($date_from !== '') {
    $date_col = $active_tab === 'movements' ? 'it.transaction_date' : 'al.created_at';
    $where[] = "$date_col >= ?";
    $types .= 's';
    $params[] = $date_from . ' 00:00:00';
}
if ($date_to !== '') {
    $date_col = $active_tab === 'movements' ? 'it.transaction_date' : 'al.created_at';
    $where[] = "$date_col <= ?";
    $types .= 's';
    $params[] = $date_to . ' 23:59:59';
}

// -----------------------------------------------------------------------------
// TAB 1: Inventory Movements
// -----------------------------------------------------------------------------
if ($active_tab === 'movements') {
    $filter_type = trim($_GET['type'] ?? '');
    $person = trim($_GET['person'] ?? '');

    if ($search !== '') {
        $where[] = "(ii.item_name LIKE ? OR it.reference_no LIKE ? OR it.transaction_type LIKE ?)";
        $like = '%' . $search . '%';
        $types .= 'sss';
        array_push($params, $like, $like, $like);
    }
    if ($filter_type !== '') {
        $where[] = "it.transaction_type = ?";
        $types .= 's';
        $params[] = $filter_type;
    }
    if ($person !== '') {
        $where[] = "(EXISTS (SELECT 1 FROM accountable_items ai WHERE ai.inventory_item_id = it.inventory_item_id AND ai.is_deleted = 0 AND ai.person_name LIKE ?) OR EXISTS (SELECT 1 FROM borrowed_items b WHERE b.inventory_item_id = it.inventory_item_id AND b.is_returned = 0 AND b.to_person LIKE ?))";
        $p_like = '%' . $person . '%';
        $types .= 'ss';
        array_push($params, $p_like, $p_like);
    }

    $where_sql = count($where) > 0 ? 'WHERE ' . implode(' AND ', $where) : '';

    $count_stmt = $conn->prepare("SELECT COUNT(*) as total FROM inventory_transactions it INNER JOIN inventory_items ii ON ii.id = it.inventory_item_id $where_sql");
    if ($types !== '') bind_params_dyn($count_stmt, $types, $params);
    $count_stmt->execute();
    $total_records = intval($count_stmt->get_result()->fetch_assoc()['total']);
    $count_stmt->close();

    // $sql = "SELECT it.*, ii.item_name,
    //         (SELECT ai.person_name FROM accountable_items ai WHERE ai.inventory_item_id = it.inventory_item_id AND ai.is_deleted = 0 ORDER BY ai.date_assigned DESC LIMIT 1) AS current_holder_name,
    //         (SELECT CONCAT(b.to_person, ' (Borrowed - ', b.quantity, ')') FROM borrowed_items b WHERE b.inventory_item_id = it.inventory_item_id AND b.is_returned = 0 ORDER BY b.borrow_date DESC LIMIT 1) AS borrowed_status
    //         FROM inventory_transactions it
    //         INNER JOIN inventory_items ii ON ii.id = it.inventory_item_id
    //         $where_sql ORDER BY it.transaction_date DESC LIMIT ? OFFSET ?";

    $sql = "SELECT it.*, ii.item_name, ii.particulars, 
        (SELECT ai.person_name FROM accountable_items ai WHERE ai.inventory_item_id = it.inventory_item_id AND ai.is_deleted = 0 ORDER BY ai.date_assigned DESC LIMIT 1) AS current_holder_name, 
        (SELECT CONCAT(b.to_person, ' (Borrowed - ', b.quantity, ')') FROM borrowed_items b WHERE b.inventory_item_id = it.inventory_item_id AND b.is_returned = 0 ORDER BY b.borrow_date DESC LIMIT 1) AS borrowed_status, 
        CONCAT(u.first_name, ' ', u.last_name) AS fetched_user_name, 
        al.user_name AS audit_user_name, 
        al.user_id AS audit_user_id, 
        al.action AS audit_action, 
        al.created_at AS audit_created_at, 
        sl.action_type AS sys_action_type, 
        sl.description AS sys_description, 
        COALESCE(it.user_id, al.user_id, sl.user_id) AS resolved_user_id, 
        COALESCE(CONCAT(u.first_name, ' ', u.last_name), al.user_name, 'System') AS resolved_display_name 
        FROM inventory_transactions it 
        JOIN inventory_items ii ON it.inventory_item_id = ii.id 
        LEFT JOIN user u ON it.user_id = u.id 
        LEFT JOIN audit_logs al ON al.record_id = it.transaction_id 
            AND al.module = 'inventory_transactions' 
            AND al.id = ( 
                SELECT MAX(al2.id) 
                FROM audit_logs al2 
                WHERE al2.record_id = it.transaction_id 
                  AND al2.module = 'inventory_transactions' 
            ) 
        LEFT JOIN system_logs sl ON sl.user_id = COALESCE(it.user_id, al.user_id) 
            AND ABS(TIMESTAMPDIFF(SECOND, sl.created_at, it.transaction_date)) < 5 
            AND sl.id = ( 
                SELECT sl2.id 
                FROM system_logs sl2 
                WHERE sl2.user_id = COALESCE(it.user_id, al.user_id) 
                ORDER BY ABS(TIMESTAMPDIFF(SECOND, sl2.created_at, it.transaction_date)) 
                LIMIT 1 
            ) 
        $where_sql 
        ORDER BY it.transaction_date DESC LIMIT ? OFFSET ?";

    $stmt = $conn->prepare($sql);
    $t = $types . 'ii';
    $p = $params;
    array_push($p, $per_page, $offset);
    bind_params_dyn($stmt, $t, $p);
    $stmt->execute();
    $results = $stmt->get_result();
    $stmt->close();
}
// -----------------------------------------------------------------------------
// TAB 2: Data Edits (Audit Trail) - FIXED JOIN WITH USER TABLE
// -----------------------------------------------------------------------------
else {
    $filter_module = trim($_GET['module'] ?? '');

    if ($search !== '') {
        $where[] = "(al.module LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ?)";
        $like = '%' . $search . '%';
        $types .= 'sss';
        array_push($params, $like, $like, $like);
    }
    if ($filter_module !== '') {
        $where[] = "al.module = ?";
        $types .= 's';
        $params[] = $filter_module;
    }

    $where_sql = count($where) > 0 ? 'WHERE ' . implode(' AND ', $where) : '';

    // Join user table to fetch the real name
    $count_stmt = $conn->prepare("SELECT COUNT(*) as total FROM audit_logs al LEFT JOIN user u ON al.user_id = u.id $where_sql");
    if ($types !== '') bind_params_dyn($count_stmt, $types, $params);
    $count_stmt->execute();
    $total_records = intval($count_stmt->get_result()->fetch_assoc()['total']);
    $count_stmt->close();

    // Select actual user name - LEFT JOIN to user table to get real user name
    $sql = "SELECT al.*, CONCAT(u.first_name, ' ', u.last_name) as modified_by 
    FROM audit_logs al 
    LEFT JOIN user u ON al.user_id = u.id
    $where_sql 
    ORDER BY al.created_at DESC LIMIT ? OFFSET ?";

    $stmt = $conn->prepare($sql);
    $t = $types . 'ii';
    $p = $params;
    array_push($p, $per_page, $offset);
    bind_params_dyn($stmt, $t, $p);
    $stmt->execute();
    $results = $stmt->get_result();
    $stmt->close();

    function format_changes($action, $old_json, $new_json)
    {
        if ($action === 'CREATE') return "<span class='text-success'>New record created.</span>";
        if ($action === 'DELETE') return "<span class='text-danger'>Record deleted.</span>";

        $old = json_decode($old_json, true) ?: [];
        $new = json_decode($new_json, true) ?: [];
        $changes = [];

        foreach ($new as $key => $value) {
            $old_val = $old[$key] ?? '';
            if ((string)$old_val !== (string)$value) {
                $changes[] = "<div class='change-row'><strong>" . htmlspecialchars($key) . "</strong>: " .
                    "<del class='text-danger'>" . htmlspecialchars($old_val) . "</del> &rarr; " .
                    "<ins class='text-success'>" . htmlspecialchars($value) . "</ins></div>";
            }
        }
        return empty($changes) ? "<span class='text-muted'>No visible changes</span>" : implode("", $changes);
    }
}

$total_pages = ($total_records > 0) ? ceil($total_records / $per_page) : 1;
?>
<!DOCTYPE html>
<html lang="en" class="light-style layout-menu-fixed" dir="ltr" data-theme="theme-default" data-assets-path="../assets/"
    data-template="vertical-menu-template-free">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transaction & Audit Logs - Inventory CAO</title>
    <link rel="icon" type="image/x-icon" href="../assets/img/favicon/favicon.ico" />
    <link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@300;400;600;700&display=swap"
        rel="stylesheet" />
    <link rel="stylesheet" href="../assets/vendor/fonts/boxicons.css" />
    <link rel="stylesheet" href="../assets/vendor/css/core.css" />
    <link rel="stylesheet" href="../assets/vendor/css/theme-default.css" />
    <link rel="stylesheet" href="../assets/css/demo.css" />
    <link rel="stylesheet" href="../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css" />

    <style>
        .controls {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 15px;
            align-items: center;
        }

        .controls input,
        .controls select {
            padding: 8px;
            border-radius: 4px;
            border: 1px solid #ddd;
        }

        .badge-in {
            background: #28a745;
            color: #fff;
            padding: 4px 8px;
            border-radius: 4px;
        }

        .badge-out {
            background: #dc3545;
            color: #fff;
            padding: 4px 8px;
            border-radius: 4px;
        }

        .nav-tabs .nav-link.active {
            font-weight: bold;
            border-bottom: 3px solid #696cff;
        }

        .change-row {
            font-size: 0.85rem;
            padding: 2px 0;
            border-bottom: 1px dashed #eee;
        }

        .change-row:last-child {
            border-bottom: none;
        }
    </style>
</head>

<body>
    <div class="layout-wrapper layout-content-navbar">
        <div class="layout-container">
            <?php include 'sidebar.php'; ?>
            <!-- <div class="layout-page"> -->
            <?php include __DIR__ . '/../includes/navbar.php'; ?>
            <div class="content-wrapper">
                <div class="container-xxl container-p-y">

                    <div class="page-header mb-4">
                        <h2><i class="bx bx-history"></i> System Activity Logs</h2>
                        <p class="text-muted">Track physical inventory movements and strict system data modifications.</p>
                    </div>

                    <ul class="nav nav-tabs mb-3">
                        <li class="nav-item">
                            <a class="nav-link <?= $active_tab === 'movements' ? 'active' : '' ?>" href="?tab=movements">Inventory Movements</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= $active_tab === 'edits' ? 'active' : '' ?>" href="?tab=edits">Data Audit Trail (Edits)</a>
                        </li>
                    </ul>

                    <div class="card p-3">
                        <form method="GET" class="controls" id="filterForm">
                            <input type="hidden" name="tab" value="<?= htmlspecialchars($active_tab) ?>">

                            <input type="text" name="search" placeholder="Search..." value="<?= htmlspecialchars($search) ?>" />

                            <?php if ($active_tab === 'movements'): ?>
                                <input type="text" name="person" placeholder="Person name..." value="<?= htmlspecialchars($person ?? '') ?>" />
                                <select name="type">
                                    <option value="">All Types</option>
                                    <option value="IN" <?= ($filter_type ?? '') === 'IN' ? 'selected' : '' ?>>IN</option>
                                    <option value="OUT" <?= ($filter_type ?? '') === 'OUT' ? 'selected' : '' ?>>OUT</option>
                                </select>
                            <?php else: ?>
                                <select name="module">
                                    <option value="">All Modules/Tables</option>
                                    <option value="inventory_items" <?= ($filter_module ?? '') === 'inventory_items' ? 'selected' : '' ?>>Inventory Items</option>
                                    <option value="accountable_items" <?= ($filter_module ?? '') === 'accountable_items' ? 'selected' : '' ?>>Accountable Items</option>
                                    <option value="inventory_transactions" <?= ($filter_module ?? '') === 'inventory_transactions' ? 'selected' : '' ?>>Inventory Transactions</option>
                                    <option value="cao_employee" <?= ($filter_module ?? '') === 'cao_employee' ? 'selected' : '' ?>>Employees</option>
                                </select>
                            <?php endif; ?>

                            <input type="date" name="date_from" value="<?= htmlspecialchars($date_from) ?>" title="From Date" />
                            <input type="date" name="date_to" value="<?= htmlspecialchars($date_to) ?>" title="To Date" />

                            <select name="per_page" onchange="this.form.submit()">
                                <option value="10" <?= $per_page === 10 ? 'selected' : '' ?>>10</option>
                                <option value="20" <?= $per_page === 20 ? 'selected' : '' ?>>20</option>
                                <option value="50" <?= $per_page === 50 ? 'selected' : '' ?>>50</option>
                            </select>
                            <button class="btn btn-primary" type="submit"><i class="bx bx-search"></i> Find</button>
                            <a class="btn btn-secondary" href="transaction_logs.php?tab=<?= $active_tab ?>">Reset</a>
                        </form>

                        <div class="table-responsive">
                            <table class="table table-hover">
                                <?php if ($active_tab === 'movements'): ?>
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Item Name</th>
                                            <th>Type</th>
                                            <th>Qty</th>
                                            <th>Reference</th>
                                            <th>Current Location / Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if ($results->num_rows > 0): while ($row = $results->fetch_assoc()): ?>
                                                <tr>
                                                    <td><small><?= date('M d, Y H:i', strtotime($row['transaction_date'])) ?></small></td>
                                                    <td><?= htmlspecialchars($row['item_name']) ?></td>
                                                    <td>
                                                        <span class="<?= strtoupper($row['transaction_type']) === 'IN' ? 'badge-in' : 'badge-out' ?>">
                                                            <?= htmlspecialchars($row['transaction_type']) ?>
                                                        </span>
                                                    </td>
                                                    <td><?= intval($row['quantity']) ?></td>
                                                    <td><small><?= htmlspecialchars($row['reference_no']) ?></small></td>
                                                    <td>
                                                        <strong><?= htmlspecialchars($row['current_holder_name'] ?? 'Warehouse') ?></strong>
                                                        <?php if (!empty($row['resolved_display_name'])): ?>
                                                            <br><small class="text-muted">Processed by <?= htmlspecialchars($row['resolved_display_name']) ?></small>
                                                        <?php endif; ?>
                                                        <?php if (!empty($row['borrowed_status'])): ?>
                                                            <br><small class="text-warning"><?= htmlspecialchars($row['borrowed_status']) ?></small>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endwhile;
                                        else: ?>
                                            <tr>
                                                <td colspan="6" class="text-center py-4">No movements found.</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                <?php else: ?>
                                    <thead>
                                        <tr>
                                            <th width="15%">Date</th>
                                            <th width="15%">Module / ID</th>
                                            <th width="10%">Action</th>
                                            <th width="15%">Modified By</th>
                                            <th width="45%">Specific Changes Detected</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if ($results->num_rows > 0): while ($row = $results->fetch_assoc()): ?>
                                                <tr>
                                                    <td><small><?= date('M d, Y H:i', strtotime($row['created_at'])) ?></small></td>
                                                    <td>
                                                        <strong><?= htmlspecialchars(strtoupper($row['module'])) ?></strong><br>
                                                        <small class="text-muted">Row ID: <?= intval($row['record_id']) ?></small>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-label-<?= $row['action'] === 'DELETE' ? 'danger' : ($row['action'] === 'CREATE' ? 'success' : 'primary') ?>">
                                                            <?= htmlspecialchars($row['action']) ?>
                                                        </span>
                                                    </td>
                                                    <!-- <td>
                                                        <?php
                                                        // $modified_by = trim($row['modified_by'] ?? '');

                                                        // // Fallback: If you added a 'user_name' column previously, check that too
                                                        // if (empty($modified_by) && !empty($row['user_name'])) {
                                                        //     $modified_by = trim($row['user_name']);
                                                        // }

                                                        // if (!empty($modified_by)) {
                                                        //     // We found a name!
                                                        //     echo '<span class="fw-bold text-primary">' . htmlspecialchars($modified_by) . '</span>';
                                                        //     if (!empty($row['user_id'])) {
                                                        //         echo '<br><small class="text-muted">UID: #' . intval($row['user_id']) . '</small>';
                                                        //     }
                                                        // } else {
                                                        //     // Name is missing. Let's figure out why.
                                                        //     echo '<span class="badge bg-label-secondary">System Account</span>';

                                                        //     if (!empty($row['user_id']) && $row['user_id'] > 0) {
                                                        //         // There IS an ID, but no name (User was likely deleted from the database)
                                                        //         echo '<br><small class="text-warning">UID: #' . intval($row['user_id']) . ' (Deleted User)</small>';
                                                        //     } else {
                                                        //         // ID is 0 or null (Old data or automated system action)
                                                        //         echo '<br><small class="text-muted">UID: #0</small>';
                                                        //     }
                                                        // }
                                                        ?>
                                                    </td> -->




                                                    
                                                    <td>
                                                        <?php
                                                        // 1. Try the Joined Full Name (modified_by)
                                                        // 2. Fallback to the stored user_name string in audit_logs
                                                        // 3. Final fallback to 'System'
                                                        $editor = !empty($row['modified_by']) ? $row['modified_by'] : ($row['user_name'] ?? 'System');
                                                        echo htmlspecialchars($editor);
                                                        ?>
                                                    </td>








                                                    <td>
                                                        <div style="max-height: 150px; overflow-y: auto; background:#f9f9f9; padding:10px; border-radius:5px;">
                                                            <?= format_changes($row['action'], $row['old_data'], $row['new_data']) ?>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endwhile;
                                        else: ?>
                                            <tr>
                                                <td colspan="5" class="text-center py-4">No data edits found.</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                <?php endif; ?>
                            </table>
                        </div>

                        <div class="mt-4 d-flex justify-content-between align-items-center">
                            <small>Showing page <?= $current_page ?> of <?= $total_pages ?> (<?= $total_records ?> total records)</small>
                            <ul class="pagination mb-0">
                                <?php
                                $base_q = $_GET;
                                $start_page = max(1, $current_page - 2);
                                $end_page = min($total_pages, $current_page + 2);
                                for ($i = $start_page; $i <= $end_page; $i++):
                                    $base_q['page'] = $i;
                                ?>
                                    <li class="page-item <?= $i == $current_page ? 'active' : '' ?>">
                                        <a class="page-link" href="?<?= http_build_query($base_q) ?>"><?= $i ?></a>
                                    </li>
                                <?php endfor; ?>
                            </ul>
                        </div>
                    </div>
                    <?php include 'footer.php'; ?>
                </div>
            </div>
        </div>
    </div>
    </div>
    <script src="../assets/vendor/libs/jquery/jquery.js"></script>
    <script src="../assets/vendor/libs/popper/popper.js"></script>
    <script src="../assets/vendor/js/bootstrap.js"></script>
    <script src="../assets/vendor/js/menu.js"></script>
    <script src="../assets/js/main.js"></script>
</body>

</html>