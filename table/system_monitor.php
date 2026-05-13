<?php
require_once __DIR__ . '/../includes/session.php';
require_login();
require_once 'config.php';

// Fallback for h() if not defined globally
if (!function_exists('h')) {
    function h($string)
    {
        return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
    }
}

// STRICT ROLE CHECK — ADMIN has full access; MANAGER has read-only access
$_monitorRole = strtoupper($_SESSION['role'] ?? '');
if (!in_array($_monitorRole, ['ADMIN', 'MANAGER'], true)) {
    header("Location: ../index.php");
    exit();
}
$_monitorIsAdmin = ($_monitorRole === 'ADMIN');

/**
 * Helper to render JSON data as a clean grid
 */
function renderAuditJson($jsonStr, $type = 'new')
{
    $data = json_decode($jsonStr, true);
    if (!$data || !is_array($data)) {
        return '<span class="text-muted small italic">' . htmlspecialchars($jsonStr) . '</span>';
    }

    $class = ($type === 'new') ? 'bg-new' : 'bg-old';
    $label = ($type === 'new') ? 'NEW DATA' : 'OLD DATA';
    $labelClass = ($type === 'new') ? 'text-success' : 'text-danger';

    $html = "<div class='data-card $class'>";
    $html .= "<strong class='$labelClass small' style='font-size: 10px; display: block; margin-bottom: 5px; letter-spacing: 0.5px;'>$label</strong>";
    $html .= "<div class='data-grid'>";

    foreach ($data as $key => $value) {
        // Skip empty fields to keep the view clean
        if ($value === "" || $value === null) continue;

        $cleanKey = str_replace('_', ' ', $key);
        $html .= "<span class='data-key'>" . ucfirst($cleanKey) . ":</span>";
        $html .= "<span class='data-val'>" . htmlspecialchars($value) . "</span>";
    }

    $html .= "</div></div>";
    return $html;
}

// Filtering Logic
$module_filter = $_GET['module'] ?? '';
$action_filter = $_GET['action'] ?? '';
$search = $_GET['search'] ?? '';

// Build dynamic WHERE clause for both Count and Main queries
$where_sql = "";
$params = [];
$types = "";

// Added 'a.' prefix to specify we are filtering the audit_logs table
if ($module_filter) {
    $where_sql .= " AND a.module = ?";
    $params[] = $module_filter;
    $types .= "s";
}
if ($action_filter) {
    $where_sql .= " AND a.action = ?";
    $params[] = $action_filter;
    $types .= "s";
}
if ($search) {
    $where_sql .= " AND (a.old_data LIKE ? OR a.new_data LIKE ?)";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $types .= "ss";
}

// 1. Pagination: Get Total Records
$count_query = "SELECT COUNT(*) as total FROM audit_logs a WHERE 1=1 " . $where_sql;
$count_stmt = $conn->prepare($count_query);
if ($types) {
    $count_stmt->bind_param($types, ...$params);
}
$count_stmt->execute();
$total_records = $count_stmt->get_result()->fetch_assoc()['total'];
$count_stmt->close();

// Pagination Config
$limit = 50;
$total_pages = ceil($total_records / $limit);
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
if ($page > $total_pages && $total_pages > 0) {
    $page = $total_pages;
}
$offset = ($page - 1) * $limit;

// 2. Main Query - UPDATED with LEFT JOIN to fetch the user's name dynamically
$query = "SELECT a.*, CONCAT(u.first_name, ' ', u.last_name) AS fetched_user_name, 
          a.user_name AS audit_user_name, 
          COALESCE(u.id, a.user_id) AS resolved_user_id, 
          COALESCE(CONCAT(u.first_name, ' ', u.last_name), a.user_name, 'System') AS resolved_display_name 
          FROM audit_logs a 
          LEFT JOIN user u ON a.user_id = u.id 
          WHERE 1=1 " . $where_sql . " 
          ORDER BY a.created_at DESC LIMIT ? OFFSET ?";

// Copy params and add pagination limits
$main_params = $params;
$main_params[] = $limit;
$main_params[] = $offset;
$main_types = $types . "ii";

$stmt = $conn->prepare($query);
if ($main_types) {
    $stmt->bind_param($main_types, ...$main_params);
}
$stmt->execute();
$audit_results = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();




$modules = ['accountable_items', 'borrowed_items', 'inventory_items', 'inventory_transactions', 'user', 'cao_employee', 'office_desks', 'messenger_messages'];
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport"
        content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />
    <title>System Activity Monitor | Admin</title>
    <link rel="icon" type="image/x-icon" href="../assets/img/favicon/favicon.ico" />
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link
        href="https://fonts.googleapis.com/css2?family=Public+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300;1,400;1,500;1,600;1,700&display=swap"
        rel="stylesheet" />
    <link rel="stylesheet" href="../assets/vendor/fonts/boxicons.css" />
    <link rel="stylesheet" href="../assets/vendor/css/core.css" class="template-customizer-core-css" />
    <link rel="stylesheet" href="../assets/vendor/css/theme-default.css" class="template-customizer-theme-css" />
    <link rel="stylesheet" href="../assets/css/demo.css" />
    <link rel="stylesheet" href="../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css" />
    <script src="../assets/vendor/js/helpers.js"></script>
    <script src="../assets/js/config.js"></script>
    <style>
        /* Modernized Data Display Styles */
        .audit-data-container {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .data-card {
            padding: 10px;
            border-radius: 6px;
            margin-bottom: 8px;
            border: 1px solid transparent;
        }

        .data-grid {
            display: grid;
            grid-template-columns: auto 1fr;
            gap: 2px 12px;
            font-size: 0.78rem;
            line-height: 1.4;
        }

        .data-key {
            color: #697a8d;
            font-weight: 600;
            white-space: nowrap;
        }

        .data-val {
            color: #32475c;
            word-break: break-all;
        }

        .bg-new {
            background-color: #e8fadf;
            border-color: #71dd3733;
        }

        .bg-old {
            background-color: #ffe5e5;
            border-color: #ff3e1d33;
        }

        .badge-id {
            font-family: monospace;
            font-size: 0.7rem;
        }

        .table-hover tbody tr:hover {
            background-color: rgba(105, 108, 255, 0.04);
        }
    </style>
</head>

<body>
    <div class="layout-wrapper layout-content-navbar">
        <div class="layout-container">
            <?php include 'sidebar.php'; ?>
            <?php include 'navbar.php'; ?>

            <div class="content-wrapper">
                <div class="container-xxl flex-grow-1 container-p-y">
                    <h4 class="fw-bold py-3 mb-1"><span class="text-muted fw-light">System /</span> Activity Monitor
                        <span class="badge bg-label-<?= $_monitorIsAdmin ? 'danger' : 'warning' ?> ms-2" style="font-size:0.7rem;vertical-align:middle;">
                            <?= h($_monitorRole) ?>
                        </span>
                    </h4>
                    <?php if (!$_monitorIsAdmin): ?>
                    <p class="text-muted small mb-3">
                        <i class="bx bx-info-circle"></i>
                        You are viewing this log as a <strong>Manager</strong> (read-only). Borrow-related actions you perform are tracked here.
                    </p>
                    <?php endif; ?>

                    <div class="card mb-4">
                        <div class="card-body">
                            <form method="GET" class="row g-3">
                                <div class="col-md-3">
                                    <label class="form-label">Table/Module</label>
                                    <select name="module" class="form-select">
                                        <option value="">All Modules</option>
                                        <?php foreach ($modules as $m): ?>
                                            <option value="<?= h($m) ?>" <?= $module_filter == $m ? 'selected' : '' ?>><?= h($m) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">Action</label>
                                    <select name="action" class="form-select">
                                        <option value="">All Actions</option>
                                        <option value="CREATE" <?= $action_filter == 'CREATE' ? 'selected' : '' ?>>Create</option>
                                        <option value="UPDATE" <?= $action_filter == 'UPDATE' ? 'selected' : '' ?>>Update</option>
                                        <option value="DELETE" <?= $action_filter == 'DELETE' ? 'selected' : '' ?>>Delete</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Search Data</label>
                                    <input type="text" name="search" class="form-control" placeholder="Search values..." value="<?= h($search) ?>">
                                </div>
                                <div class="col-md-3 d-flex align-items-end">
                                    <button type="submit" class="btn btn-primary w-100">Filter Results</button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <div class="card">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead>
                                    <tr>
                                        <th>Timestamp</th>
                                        <th>User</th>
                                        <th>Module</th>
                                        <th>Action</th>
                                        <th>Data Changes</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($audit_results as $row): ?>
                                        <tr>
                                            <td style="vertical-align: top;">
                                                <small class="text-muted d-block"><?= date('M d, Y', strtotime($row['created_at'])) ?></small>
                                                <small class="fw-bold"><?= date('H:i:s', strtotime($row['created_at'])) ?></small>
                                            </td>
                                            <td style="vertical-align: top;">
                                                <div class="d-flex flex-column">
                                                    <?php
                                                    /*
         * Resolution priority:
         * 1. Live JOIN from users table (fetched_user_name)
         * 2. Audit log snapshot name (audit_user_name) — cross-referenced
         * 3. Stored user_name at time of write (deleted user fallback)
         * 4. System label only if ALL sources are empty AND user_id is NULL
         */
                                                    $display_name   = null;
                                                    $display_source = null;

                                                    if (!empty($row['fetched_user_name'])) {
                                                        $display_name   = $row['fetched_user_name'];
                                                        $display_source = 'live';
                                                    } elseif (!empty($row['audit_user_name'])) {
                                                        // Cross-referenced from audit_logs
                                                        $display_name   = $row['audit_user_name'];
                                                        $display_source = 'audit';
                                                    } elseif (!empty($row['user_name'])) {
                                                        // Stored snapshot at write time (user was deleted after)
                                                        $display_name   = $row['user_name'];
                                                        $display_source = 'snapshot';
                                                    } else {
                                                        $display_name   = 'System';
                                                        $display_source = 'system';
                                                    }
                                                    ?>

                                                    <span class="fw-bold text-dark"><?= h($display_name) ?></span>
                                                    <small class="text-muted badge-id">
                                                        UID: #<?= h($row['resolved_user_id'] ?? $row['user_id'] ?? 'N/A') ?>
                                                    </small>

                                                    <?php if ($display_source === 'audit'): ?>
                                                        <small class="text-warning" title="Name retrieved from audit log">
                                                            <i class="bi bi-exclamation-circle"></i> via audit log
                                                        </small>
                                                    <?php elseif ($display_source === 'snapshot'): ?>
                                                        <small class="text-secondary" title="User no longer exists in the system">
                                                            <i class="bi bi-person-x"></i> deleted user
                                                        </small>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td style="vertical-align: top;">
                                                <span class="badge bg-label-secondary"><?= h($row['module']) ?></span>
                                            </td>
                                            <td style="vertical-align: top;">
                                                <?php
                                                $badge = match ($row['action']) {
                                                    'CREATE' => 'success',
                                                    'DELETE' => 'danger',
                                                    'UPDATE' => 'warning',
                                                    default  => 'primary'
                                                };
                                                ?>
                                                <span class="badge bg-<?= $badge ?>"><?= h($row['action']) ?></span>
                                            </td>
                                            <td style="min-width: 450px;">
                                                <div class="audit-data-container">
                                                    <?php
                                                    if (!empty($row['new_data'])) {
                                                        echo renderAuditJson($row['new_data'], 'new');
                                                    }
                                                    if (!empty($row['old_data'])) {
                                                        echo renderAuditJson($row['old_data'], 'old');
                                                    }
                                                    if (empty($row['new_data']) && empty($row['old_data'])) {
                                                        echo "<span class='text-muted small'>No data recorded.</span>";
                                                    }
                                                    ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($audit_results)): ?>
                                        <tr>
                                            <td colspan="5" class="text-center py-4">No activity logs found.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <?php if ($total_pages > 1): ?>
                            <div class="card-footer d-flex justify-content-between align-items-center border-top">
                                <small class="text-muted">Showing page <?= $page ?> of <?= $total_pages ?> (<?= $total_records ?> total records)</small>
                                <nav aria-label="Page navigation">
                                    <ul class="pagination pagination-sm mb-0">
                                        <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                                            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">Previous</a>
                                        </li>

                                        <?php
                                        $start_page = max(1, $page - 2);
                                        $end_page = min($total_pages, $page + 2);
                                        for ($i = $start_page; $i <= $end_page; $i++):
                                        ?>
                                            <li class="page-item <?= ($i == $page) ? 'active' : '' ?>">
                                                <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"><?= $i ?></a>
                                            </li>
                                        <?php endfor; ?>

                                        <li class="page-item <?= ($page >= $total_pages) ? 'disabled' : '' ?>">
                                            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>">Next</a>
                                        </li>
                                    </ul>
                                </nav>
                            </div>
                        <?php endif; ?>

                    </div>
                </div>
            </div>
        </div>
    </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>