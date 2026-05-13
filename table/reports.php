<?php
require_once __DIR__ . '/../includes/session.php';
require_login();
require_once __DIR__ . '/config.php';

/**
 * reports.php - FIXED VERSION
 * 1. Removed 'it.id' which was causing the SQL "Unknown column" error.
 * 2. Consolidated Class to prevent "Redeclare" errors.
 * 3. Enhanced PhpSpreadsheet check.
 */

/* utilities */
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
if (!function_exists('h')) {
    function h($s)
    {
        return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8');
    }
}

/* Model */
if (!class_exists('ReportsModel')) {
    class ReportsModel
    {
        private mysqli $db;
        public function __construct(mysqli $conn)
        {
            $this->db = $conn;
        }

        public function buildWhere(array $filters): array
        {
            $where = [];
            $types = '';
            $params = [];

            if (!empty($filters['search'])) {
                $where[] = "(ii.item_name LIKE ? OR it.reference_no LIKE ?)";
                $like = '%' . $filters['search'] . '%';
                $types .= 'ss';
                $params[] = $like;
                $params[] = $like;
            }
            if (!empty($filters['type'])) {
                $where[] = "it.transaction_type = ?";
                $types .= 's';
                $params[] = $filters['type'];
            }
            if (!empty($filters['person'])) {
                $where[] = "(
                    EXISTS (SELECT 1 FROM accountable_items ai WHERE ai.inventory_item_id = it.inventory_item_id AND ai.is_deleted = 0 AND ai.person_name LIKE ?)
                    OR EXISTS (SELECT 1 FROM borrowed_items b WHERE b.inventory_item_id = it.inventory_item_id AND b.to_person LIKE ?)
                )";
                $p = '%' . $filters['person'] . '%';
                $types .= 'ss';
                $params[] = $p;
                $params[] = $p;
            }
            if (!empty($filters['date_from'])) {
                $where[] = "it.transaction_date >= ?";
                $types .= 's';
                $params[] = $filters['date_from'] . ' 00:00:00';
            }
            if (!empty($filters['date_to'])) {
                $where[] = "it.transaction_date <= ?";
                $types .= 's';
                $params[] = $filters['date_to'] . ' 23:59:59';
            }

            $where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
            return [$where_sql, $types, $params];
        }

        private function baseSelect(): string
        {
            // REMOVED 'it.id' here to fix the "Unknown column" error
            return "
                SELECT
                    it.transaction_date,
                    ii.are_mr_ics_num,
                    (SELECT ai.property_number FROM accountable_items ai WHERE ai.inventory_item_id = it.inventory_item_id AND ai.is_deleted = 0 ORDER BY ai.date_assigned DESC, ai.id DESC LIMIT 1) AS property_number,
                    (SELECT ai.serial_number FROM accountable_items ai WHERE ai.inventory_item_id = it.inventory_item_id AND ai.is_deleted = 0 ORDER BY ai.date_assigned DESC, ai.id DESC LIMIT 1) AS serial_number,
                    ii.item_name,
                    it.transaction_type,
                    it.quantity,
                    it.reference_no,
                    (SELECT ai.person_name FROM accountable_items ai WHERE ai.inventory_item_id = it.inventory_item_id AND ai.is_deleted = 0 ORDER BY ai.date_assigned DESC, ai.id DESC LIMIT 1) AS current_holder_name,
                    (SELECT ai.assigned_quantity FROM accountable_items ai WHERE ai.inventory_item_id = it.inventory_item_id AND ai.is_deleted = 0 ORDER BY ai.date_assigned DESC, ai.id DESC LIMIT 1) AS current_assigned_qty,
                    (SELECT CONCAT(b.to_person, ' (Borrowed - ', b.quantity, ')') FROM borrowed_items b WHERE b.inventory_item_id = it.inventory_item_id AND b.is_returned = 0 ORDER BY b.borrow_date DESC LIMIT 1) AS borrowed_status
                FROM inventory_transactions it
                INNER JOIN inventory_items ii ON ii.id = it.inventory_item_id
            ";
        }

        public function countTransactions(array $filters): int
        {
            [$where_sql, $types, $params] = $this->buildWhere($filters);
            $sql = "SELECT COUNT(*) AS cnt FROM inventory_transactions it INNER JOIN inventory_items ii ON ii.id = it.inventory_item_id {$where_sql}";
            $stmt = $this->db->prepare($sql);
            if (!$stmt) return 0;
            if ($types !== '') bind_params_dyn($stmt, $types, $params);
            $stmt->execute();
            $res = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            return intval($res['cnt'] ?? 0);
        }

        public function fetchTransactions(array $filters, int $limit, int $offset, string $order_by = 'transaction_date DESC'): array
        {
            [$where_sql, $types, $params] = $this->buildWhere($filters);
            $sql = $this->baseSelect() . " {$where_sql} ORDER BY {$order_by} LIMIT ? OFFSET ?";
            $stmt = $this->db->prepare($sql);
            if (!$stmt) return [];
            $types_with = $types . 'ii';
            $params_with = $params;
            $params_with[] = $limit;
            $params_with[] = $offset;
            bind_params_dyn($stmt, $types_with, $params_with);
            $stmt->execute();
            $res = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
            return $res;
        }

        public function sumQuantity(array $filters): int
        {
            [$where_sql, $types, $params] = $this->buildWhere($filters);
            $sql = "SELECT SUM(it.quantity) as total_qty FROM inventory_transactions it INNER JOIN inventory_items ii ON ii.id = it.inventory_item_id {$where_sql}";
            $stmt = $this->db->prepare($sql);
            if (!$stmt) return 0;
            if ($types !== '') bind_params_dyn($stmt, $types, $params);
            $stmt->execute();
            $res = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            return intval($res['total_qty'] ?? 0);
        }

        public function summaryByType(array $filters): array
        {
            [$where_sql, $types, $params] = $this->buildWhere($filters);
            $sql = "SELECT it.transaction_type, COUNT(*) AS cnt FROM inventory_transactions it INNER JOIN inventory_items ii ON ii.id = it.inventory_item_id {$where_sql} GROUP BY it.transaction_type";
            $stmt = $this->db->prepare($sql);
            if (!$stmt) return [];
            if ($types !== '') bind_params_dyn($stmt, $types, $params);
            $stmt->execute();
            $res = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
            return $res;
        }

        public function topItems(array $filters, int $limit = 10): array
        {
            [$where_sql, $types, $params] = $this->buildWhere($filters);
            $sql = "SELECT ii.item_name, SUM(it.quantity) AS total_qty FROM inventory_transactions it INNER JOIN inventory_items ii ON ii.id = it.inventory_item_id {$where_sql} GROUP BY ii.item_name ORDER BY total_qty DESC LIMIT ?";
            $stmt = $this->db->prepare($sql);
            if (!$stmt) return [];
            $types_with = $types . 'i';
            $params_with = $params;
            $params_with[] = $limit;
            bind_params_dyn($stmt, $types_with, $params_with);
            $stmt->execute();
            $res = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
            return $res;
        }

        public function perPersonAccountability(string $searchPerson = ''): array
        {
            $where = "WHERE ai.is_deleted = 0";
            $types = '';
            $params = [];
            if ($searchPerson !== '') {
                $where .= " AND ai.person_name LIKE ?";
                $types = 's';
                $params[] = '%' . $searchPerson . '%';
            }
            $sql = "
                SELECT ai.person_name, SUM(ai.assigned_quantity) AS total_assigned, IFNULL(b.borrowed_qty,0) AS total_borrowed
                FROM accountable_items ai
                LEFT JOIN (SELECT b.to_person AS person, SUM(b.quantity) AS borrowed_qty FROM borrowed_items b WHERE b.is_returned = 0 GROUP BY b.to_person) b ON b.person = ai.person_name
                {$where} GROUP BY ai.person_name ORDER BY total_assigned DESC LIMIT 200
            ";
            $stmt = $this->db->prepare($sql);
            if (!$stmt) return [];
            if ($types !== '') bind_params_dyn($stmt, $types, $params);
            $stmt->execute();
            $res = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
            return $res;
        }

        public function personDetail(string $personName): array
        {
            $sql = "
                SELECT ii.item_name, ai.assigned_quantity, IFNULL(b.quantity,0) AS borrowed_qty, ai.date_assigned, ai.remarks
                FROM accountable_items ai
                INNER JOIN inventory_items ii ON ii.id = ai.inventory_item_id
                LEFT JOIN borrowed_items b ON b.inventory_item_id = ai.inventory_item_id AND b.to_person = ai.person_name AND b.is_returned = 0
                WHERE ai.person_name = ? AND ai.is_deleted = 0 ORDER BY ai.date_assigned DESC LIMIT 500
            ";
            $stmt = $this->db->prepare($sql);
            if (!$stmt) return [];
            bind_params_dyn($stmt, 's', [$personName]);
            $stmt->execute();
            $res = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
            return $res;
        }
    }
}

/* Controller */
$model = new ReportsModel($conn);
if (!isset($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
$CSRF = $_SESSION['csrf_token'];

// Available report columns and labels (keys map to fields returned by fetchTransactions())
$available_cols = [
    'are_mr_ics_num' => 'ARE/MR/ICS No',
    'property_number' => 'Property No',
    'serial_number' => 'Serial No',
    'transaction_date' => 'Date',
    'item_name' => 'Item',
    'transaction_type' => 'Type',
    'quantity' => 'Qty',
    'reference_no' => 'Ref',
    'current_holder_name' => 'Holder',
    'current_assigned_qty' => 'Current Assigned',
    'borrowed_status' => 'Borrowed Status'
];

$filters = [
    'search' => trim($_REQUEST['search'] ?? ''),
    'person' => trim($_REQUEST['person'] ?? ''),
    'type' => trim($_REQUEST['type'] ?? ''),
    'date_from' => trim($_REQUEST['date_from'] ?? ''),
    'date_to' => trim($_REQUEST['date_to'] ?? '')
];
$per_page = max(10, intval($_REQUEST['per_page'] ?? 25));
$page = max(1, intval($_REQUEST['page'] ?? 1));
$offset = ($page - 1) * $per_page;
$sort_by = trim($_REQUEST['sort_by'] ?? 'transaction_date');
$sort_dir = strtoupper(trim($_REQUEST['sort_dir'] ?? 'DESC')) === 'ASC' ? 'ASC' : 'DESC';
$order_by = ($sort_by === 'item_name') ? "item_name {$sort_dir}" : "transaction_date {$sort_dir}";

$action = $_REQUEST['action'] ?? '';
$export = $_REQUEST['export'] ?? '';
$cols = $_REQUEST['cols'] ?? [];
if (is_string($cols)) $cols = explode(',', $cols);

/* AJAX: person detail */
if ($action === 'person_detail' && !empty($_REQUEST['person_name'])) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'ok', 'data' => $model->personDetail($_REQUEST['person_name'])]);
    exit;
}

/* Export handling */
if ($action === 'export' && in_array($export, ['csv', 'xlsx'])) {
    // normalize selected columns; default to all available columns
    $selected_cols = is_array($cols) && count($cols) ? array_values($cols) : array_keys($available_cols);
    // sanitize: keep only known keys
    $selected_cols = array_values(array_intersect($selected_cols, array_keys($available_cols)));
    if ($export === 'csv') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="inventory_report_' . date('Ymd_His') . '.csv"');
        echo "\xEF\xBB\xBF";
        $out = fopen('php://output', 'w');
        // build header from selected columns
        $header = array_map(function($k) use ($available_cols) { return $available_cols[$k] ?? $k; }, $selected_cols);
        fputcsv($out, $header);

        $total = $model->countTransactions($filters);
        $batch = 500;
        for ($off = 0; $off < $total; $off += $batch) {
            foreach ($model->fetchTransactions($filters, $batch, $off, $order_by) as $r) {
                $line = [];
                foreach ($selected_cols as $c) {
                    $val = $r[$c] ?? '';
                    if (is_array($val) || is_object($val)) $val = json_encode($val);
                    $line[] = $val;
                }
                fputcsv($out, $line);
            }
            flush();
        }
        fclose($out);
        exit;
    }

  if ($export === 'xlsx') {
        // try common composer autoload locations (project vendor or bundled PhpSpreadsheet folder)
        $autoloadCandidates = [
            __DIR__ . '/../vendor/autoload.php',
            __DIR__ . '/../PhpSpreadsheet/vendor/autoload.php',
            __DIR__ . '/vendor/autoload.php',
            __DIR__ . '/../../vendor/autoload.php'
        ];
        $loaded = false;
        foreach ($autoloadCandidates as $p) {
            if (file_exists($p)) {
                require_once $p;
                $loaded = true;
                break;
            }
        }

        if (class_exists('\PhpOffice\PhpSpreadsheet\Spreadsheet')) {
            // Use PhpSpreadsheet when available
            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $headers = array_map(function($k) use ($available_cols) { return $available_cols[$k] ?? $k; }, $selected_cols);
            $sheet->fromArray($headers, NULL, 'A1');

            $rowNum = 2;
            $total = $model->countTransactions($filters);
            $batch = 1000;
            for ($off = 0; $off < $total; $off += $batch) {
                $rows = $model->fetchTransactions($filters, $batch, $off, $order_by);
                foreach ($rows as $r) {
                    $line = [];
                    foreach ($selected_cols as $c) {
                        $val = $r[$c] ?? '';
                        if (is_array($val) || is_object($val)) $val = json_encode($val);
                        $line[] = $val;
                    }
                    $sheet->fromArray($line, NULL, 'A' . $rowNum++);
                }
            }

            // send XLSX
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment; filename="report_' . date('Ymd_His') . '.xlsx"');
            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            $writer->save('php://output');
            exit;
        } else {
            // Fallback: Excel-compatible HTML (will open in Excel) — no third-party dependency
            $total = $model->countTransactions($filters);
            $rows = $model->fetchTransactions($filters, $total, 0, $order_by);

            header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
            header('Content-Disposition: attachment; filename="report_' . date('Ymd_His') . '.xls"');
            echo "\xEF\xBB\xBF"; // BOM for Excel UTF-8
            echo '<table border="1"><thead><tr>';
            $headers = array_map(function($k) use ($available_cols) { return $available_cols[$k] ?? $k; }, $selected_cols);
            foreach ($headers as $h) echo '<th>'.htmlspecialchars($h, ENT_QUOTES, 'UTF-8').'</th>';
            echo '</tr></thead><tbody>';
            foreach ($rows as $r) {
                echo '<tr>';
                foreach ($selected_cols as $c) {
                    echo '<td>'.htmlspecialchars($r[$c] ?? '', ENT_QUOTES, 'UTF-8').'</td>';
                }
                echo '</tr>';
            }
            echo '</tbody></table>';
            exit;
        }
    }
}

$total_records = $model->countTransactions($filters);
$transactions = $model->fetchTransactions($filters, $per_page, $offset, $order_by);
$total_qty = $model->sumQuantity($filters);
$by_type = $model->summaryByType($filters);
$top_items = $model->topItems($filters, 10);
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Reports - Inventory System</title>
    <link rel="stylesheet" href="../assets/vendor/css/core.css">
    <link rel="stylesheet" href="../assets/vendor/css/theme-default.css">
    <link rel="stylesheet" href="../assets/vendor/fonts/boxicons.css">


    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="../assets/img/favicon/favicon.ico" />

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link
        href="https://fonts.googleapis.com/css2?family=Public+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300;1,400;1,500;1,600;1,700&display=swap"
        rel="stylesheet" />

    <!-- Icons. Uncomment required icon fonts -->
    <link rel="stylesheet" href="../assets/vendor/fonts/boxicons.css" />

    <!-- Core CSS -->
    <link rel="stylesheet" href="../assets/vendor/css/core.css" class="template-customizer-core-css" />
    <link rel="stylesheet" href="../assets/vendor/css/theme-default.css" class="template-customizer-theme-css" />
    <link rel="stylesheet" href="../assets/css/demo.css" />

    <!-- Vendors CSS -->
    <link rel="stylesheet" href="../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css" />

    <!-- Page CSS -->

    <!-- Helpers -->
    <script src="../assets/vendor/js/helpers.js"></script>

    <!--! Template customizer & Theme config files MUST be included after core stylesheets and helpers.js in the <head> section -->
    <!--? Config:  Mandatory theme config file contain global vars & default theme options, Set your preferred theme option in this file.  -->
    <script src="../assets/js/config.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .report-card {
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            border: none;
            margin-bottom: 1.5rem;
        }

        .stat-val {
            font-size: 1.5rem;
            font-weight: 700;
            color: #566a7f;
        }

        .stat-label {
            font-size: 0.85rem;
            color: #a1acb8;
            text-transform: uppercase;
        }

        .table thead th {
            background-color: #f5f5f9;
            text-transform: uppercase;
            font-size: 0.75rem;
        }
    </style>
</head>


<body align="center">

    <div class="layout-wrapper layout-content-navbar">

        <div class="layout-container">
            <!-- SIDEBAR -->
            <?php include 'sidebar.php'; ?> <!-- PAGE -->
        
                <!-- NAVBAR -->
                <?php include __DIR__ . '/../includes/navbar.php'; ?>
                <div class="" style="margin-left: 250px; margin-top: 50px;">
                
                </div>


                <div class="container-xxl">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h4 class="fw-bold mb-0">Inventory Transaction Reports</h4>
                        <div>
                            <button class="btn btn-primary" type="button" data-bs-toggle="modal" data-bs-target="#exportModal">
                                <i class="bx bx-export me-1"></i> Export Data
                            </button>
                        </div>
                    </div>

                    <div class="card report-card p-3">
                        <form method="GET" class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label">Search Item/Reference</label>
                                <input type="text" name="search" class="form-control" value="<?= h($filters['search']) ?>" placeholder="e.g. Laptop...">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Type</label>
                                <select name="type" class="form-select">
                                    <option value="">All Types</option>
                                    <option value="IN" <?= $filters['type'] == 'IN' ? 'selected' : '' ?>>IN (Restock/Return)</option>
                                    <option value="OUT" <?= $filters['type'] == 'OUT' ? 'selected' : '' ?>>OUT (Assigned/Borrowed)</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">From Date</label>
                                <input type="date" name="date_from" class="form-control" value="<?= h($filters['date_from']) ?>">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">To Date</label>
                                <input type="date" name="date_to" class="form-control" value="<?= h($filters['date_to']) ?>">
                            </div>
                            <div class="col-md-3 d-flex align-items-end gap-2">
                                <button type="submit" class="btn btn-info w-100">Filter</button>
                                <a href="reports.php" class="btn btn-outline-secondary">Reset</a>
                            </div>
                        </form>
                    </div>

                    <div class="row">
                        <div class="col-md-3">
                            <div class="card report-card p-3 text-center">
                                <div class="stat-label">Total Transactions</div>
                                <div class="stat-val"><?= number_format($total_records) ?></div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card report-card p-3 text-center">
                                <div class="stat-label">Total Volume (Qty)</div>
                                <div class="stat-val"><?= number_format($total_qty) ?></div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card report-card p-3">
                                <canvas id="typeChart" style="max-height: 100px;"></canvas>
                            </div>
                        </div>
                    </div>

                    <div class="card report-card">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Item Name</th>
                                        <th>Type</th>
                                        <th class="text-end">Qty</th>
                                        <th>Reference</th>
                                        <th>Current Holder</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($transactions)): ?>
                                        <tr>
                                            <td colspan="6" class="text-center py-4">No records found matching filters.</td>
                                        </tr>
                                    <?php endif; ?>
                                    <?php foreach ($transactions as $row): ?>
                                        <tr>
                                            <td><small class="text-muted"><?= date('M d, Y', strtotime($row['transaction_date'])) ?></small></td>
                                            <td><strong><?= h($row['item_name']) ?></strong></td>
                                            <td>
                                                <span class="badge bg-label-<?= $row['transaction_type'] == 'IN' ? 'success' : 'danger' ?>">
                                                    <?= $row['transaction_type'] ?>
                                                </span>
                                            </td>
                                            <td class="text-end fw-bold"><?= number_format($row['quantity']) ?></td>
                                            <td><code class="small"><?= h($row['reference_no']) ?></code></td>
                                            <td>
                                                <a href="#" class="person-link text-primary" data-name="<?= h($row['current_holder_name']) ?>">
                                                    <?= h($row['current_holder_name'] ?: 'N/A') ?>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="card-footer d-flex justify-content-between align-items-center bg-white border-0">
                            <small class="text-muted">Showing <?= count($transactions) ?> of <?= $total_records ?> entries</small>
                            <nav>
                                <ul class="pagination pagination-sm mb-0">
                                    <?php for ($i = 1; $i <= ceil($total_records / $per_page); $i++): ?>
                                        <li class="page-item <?= $page == $i ? 'active' : '' ?>">
                                            <a class="page-link" href="?page=<?= $i ?>&<?= http_build_query($filters) ?>"><?= $i ?></a>
                                        </li>
                                    <?php endfor; ?>
                                </ul>
                            </nav>
                        </div>
                    </div>
                </div>

                <div class="modal fade" id="exportModal" tabindex="-1">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Export Report</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <form method="GET" id="exportForm">
                                <div class="modal-body">
                                    <input type="hidden" name="action" value="export">
                                    <div class="mb-2">
                                        <label class="form-label">Columns to include</label>
                                        <div class="row g-2">
                                            <?php foreach ($available_cols as $k => $label): ?>
                                                <div class="col-6">
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" name="cols[]" value="<?= h($k) ?>" id="col_<?= h($k) ?>" <?= in_array($k, $cols) || empty($cols) ? 'checked' : '' ?>>
                                                        <label class="form-check-label" for="col_<?= h($k) ?>"><?= h($label) ?></label>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>

                                    <div class="mb-2">
                                        <label class="form-label">Filters (applied to export)</label>
                                        <div class="row g-2">
                                            <div class="col-md-6"><input type="text" name="search" class="form-control" placeholder="Search" value="<?= h($filters['search']) ?>"></div>
                                            <div class="col-md-3">
                                                <select name="type" class="form-select">
                                                    <option value="" <?= $filters['type'] == '' ? 'selected' : '' ?>>All</option>
                                                    <option value="IN" <?= $filters['type'] == 'IN' ? 'selected' : '' ?>>IN</option>
                                                    <option value="OUT" <?= $filters['type'] == 'OUT' ? 'selected' : '' ?>>OUT</option>
                                                </select>
                                            </div>
                                            <div class="col-md-3"><input type="date" name="date_from" class="form-control" value="<?= h($filters['date_from']) ?>"></div>
                                            <div class="col-md-3 mt-2"><input type="date" name="date_to" class="form-control" value="<?= h($filters['date_to']) ?>"></div>
                                        </div>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-outline-secondary" id="saveReportPreset">Save Preset</button>
                                    <button type="submit" name="export" value="csv" class="btn btn-secondary">Export CSV</button>
                                    <button type="submit" name="export" value="xlsx" class="btn btn-primary">Export Excel (XLSX)</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="modal fade" id="personModal" tabindex="-1">
                    <div class="modal-dialog modal-lg modal-dialog-centered">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="personModalTitle">Accountability Detail</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body" id="personModalBody">
                                <div class="text-center p-5">
                                    <div class="spinner-border text-primary" role="status"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>



                <!-- FOOTER -->
                <?php include 'footer.php'; ?>
            </div>

            <div class="content-backdrop fade"></div>

    </div>

    <div class="layout-overlay layout-menu-toggle"></div>
 





    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js" integrity="sha384-ENjdO4Dr2bkBIFxQpeoTz1HIcje39Wm4jDKdf19U8gI4ddQ3GYNS7NTKfAdVQSZe" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.7/dist/umd/popper.min.js" integrity="sha384-zYPOMqeu1DAVkHiLqWBUTcbYfZ8osu1Nd6Z89ify25QV9guujx43ITvfi12/QExE" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.min.js" integrity="sha384-Y4oOpwW3duJdCWv5ly8SCFYWqFDsfob/3GkgExXKV4idmbt98QcxXYs9UoXAB7BZ" crossorigin="anonymous"></script>

    <script src="../assets/vendor/libs/jquery/jquery.js"></script>
    <script src="../assets/vendor/libs/popper/popper.js"></script>
    <script src="../assets/vendor/js/bootstrap.js"></script>
    <script src="../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js"></script>
    <script src="../assets/vendor/js/menu.js"></script>
    <script src="../assets/js/main.js"></script>
    <script src="../assets/vendor/libs/jquery/jquery.js"></script>
    <script src="../assets/vendor/js/bootstrap.js"></script>
    <script>
        $(document).ready(function() {
            // Chart
            const ctx = document.getElementById('typeChart').getContext('2d');
            const typeData = <?= json_encode($by_type) ?>;
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: typeData.map(d => d.transaction_type),
                    datasets: [{
                        label: 'Transaction Count',
                        data: typeData.map(d => d.cnt),
                        backgroundColor: ['#71dd37', '#ff3e1d']
                    }]
                },
                options: {
                    indexAxis: 'y',
                    plugins: {
                        legend: {
                            display: false
                        }
                    }
                }
            });

            // Person Detail AJAX
            $('.person-link').on('click', function(e) {
                e.preventDefault();
                const name = $(this).data('name');
                if (!name) return;

                $('#personModalTitle').text('Accountability: ' + name);
                $('#personModal').modal('show');

                $.get('reports.php', {
                    action: 'person_detail',
                    person_name: name
                }, function(res) {
                    if (res.status === 'ok') {
                        let html = '<table class="table table-sm"><thead><tr><th>Item</th><th>Assigned</th><th>Borrowed</th><th>Date</th></tr></thead><tbody>';
                        res.data.forEach(r => {
                            html += `<tr><td>${r.item_name}</td><td>${r.assigned_quantity}</td><td>${r.borrowed_qty}</td><td>${r.date_assigned}</td></tr>`;
                        });
                        html += '</tbody></table>';
                        $('#personModalBody').html(html);
                    }
                });
            });

            // Export preset save (client-side localStorage)
            $('#saveReportPreset').on('click', function(e) {
                e.preventDefault();
                const name = prompt('Enter preset name to save (will be stored locally in your browser):');
                if (!name) return;
                const cols = $('#exportForm input[name="cols[]"]:checked').map(function() { return this.value; }).get();
                if (!cols.length) { alert('Select at least one column to save.'); return; }
                const key = 'report_presets_v1';
                let obj = {};
                try { obj = JSON.parse(localStorage.getItem(key) || '{}'); } catch(e) { obj = {}; }
                obj[name] = cols;
                localStorage.setItem(key, JSON.stringify(obj));
                alert('Preset saved: ' + name);
            });
        });
    </script>
</body>

</html>
