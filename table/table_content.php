<?php
if (session_status() === PHP_SESSION_NONE) {
    require_once __DIR__ . '/../includes/session.php';
}
require_once 'config.php';

// Audit + system-log helpers (same helpers encoder_module.php uses)
if (file_exists(__DIR__ . '/../includes/audit_helper.php')) {
    require_once __DIR__ . '/../includes/audit_helper.php';
}
if (file_exists(__DIR__ . '/../includes/system_log_helper.php')) {
    require_once __DIR__ . '/../includes/system_log_helper.php';
}

$role     = $_SESSION['role']     ?? 'STAFF';
$user_id  = (int)($_SESSION['id'] ?? 0);
$user_name = $_SESSION['full_name'] ?? ($_SESSION['username'] ?? 'Admin');

// ── Ensure CSRF token exists ──────────────────────────────────────────────────
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Helper to verify CSRF token for POST requests
function verify_post_csrf() {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die("<script>alert('Security token invalid. Please refresh the page.'); window.history.back();</script>");
    }
}

// ── Slash-token splitter (mirrors encoder_module.php $splitTok) ──────────────
function split_tokens(string $s): array {
    return array_values(array_filter(array_map('trim', explode('/', $s))));
}

// ------------------------------------------------------------------
// 1. CRUD OPERATIONS
// ------------------------------------------------------------------

// ── CREATE ────────────────────────────────────────────────────────────────────
if (isset($_POST['add'])) {
    verify_post_csrf();

    $item_name       = trim($_POST['item_name']       ?? '');
    $particulars     = trim($_POST['particulars']     ?? '');
    $are_mr_ics_num  = trim($_POST['are_mr_ics_num']  ?? '');
    $property_number = trim($_POST['property_number'] ?? '');
    $serial_number   = trim($_POST['serial_number']   ?? '');
    $quantity        = (int)($_POST['quantity']        ?? 0);
    $value_amount    = (float)($_POST['value_amount']  ?? 0);
    $total_amount    = $value_amount * $quantity;
    $date_delivered  = trim($_POST['date_delivered']  ?? '');
    $item_status     = trim($_POST['item_status']     ?? 'Active');

    $allowed_statuses = ['Active', 'Inactive', 'Returned', 'Defective', 'Replaced'];
    if (!in_array($item_status, $allowed_statuses, true)) $item_status = 'Active';

    if (empty($item_name) || empty($particulars) || $quantity <= 0 || empty($date_delivered)) {
        echo "<script>alert('Please fill in all required fields (Item Name, Particulars, Quantity, Date Delivered).');</script>";
    } else {
        $conn->begin_transaction();
        try {
            // ── MERGE CHECK: same item_name + particulars + date_delivered ─────
            $chk = $conn->prepare(
                "SELECT id, quantity, serial_number, are_mr_ics_num, property_number,
                        value_amount, total_amount
                 FROM inventory_items
                 WHERE item_name = ? AND particulars = ? AND date_delivered = ?
                 LIMIT 1"
            );
            $chk->bind_param('sss', $item_name, $particulars, $date_delivered);
            $chk->execute();
            $merge_row = $chk->get_result()->fetch_assoc();
            $chk->close();

            if ($merge_row) {
                // ── MERGE PATH ────────────────────────────────────────────────
                $merge_id          = (int)$merge_row['id'];
                $existing_serials  = split_tokens($merge_row['serial_number'] ?? '');
                $incoming_serials  = split_tokens($serial_number);

                $dupes = array_intersect(
                    array_map('strtolower', $incoming_serials),
                    array_map('strtolower', $existing_serials)
                );
                if (!empty($dupes)) {
                    $dupe_list = htmlspecialchars(implode(', ', array_values($dupes)));
                    throw new Exception("Duplicate serial number(s) detected for existing Item #{$merge_id}: {$dupe_list}. Please remove duplicates.");
                }

                $old_snap = [
                    'id'              => $merge_id,
                    'quantity'        => (int)$merge_row['quantity'],
                    'serial_number'   => $merge_row['serial_number']    ?? '',
                    'are_mr_ics_num'  => $merge_row['are_mr_ics_num']   ?? '',
                    'property_number' => $merge_row['property_number']  ?? '',
                    'value_amount'    => (float)$merge_row['value_amount'],
                    'total_amount'    => (float)$merge_row['total_amount'],
                ];

                $merged_serial = implode(' / ', array_merge($existing_serials, $incoming_serials));
                $merged_are    = implode(' / ', array_merge(
                    split_tokens($merge_row['are_mr_ics_num']  ?? ''),
                    split_tokens($are_mr_ics_num)
                ));
                $merged_prop   = implode(' / ', array_merge(
                    split_tokens($merge_row['property_number'] ?? ''),
                    split_tokens($property_number)
                ));
                $merged_qty    = (int)$merge_row['quantity'] + $quantity;
                $merged_value  = $value_amount ?: (float)$merge_row['value_amount'];
                $merged_total  = $merged_value * $merged_qty;

                $upd = $conn->prepare(
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
                $upd->bind_param('isssddsi',
                    $merged_qty, $merged_serial, $merged_are, $merged_prop,
                    $merged_value, $merged_total, $item_status, $merge_id
                );
                $upd->execute();
                $upd->close();

                $new_snap = [
                    'id'              => $merge_id,
                    'quantity'        => $merged_qty,
                    'serial_number'   => $merged_serial,
                    'are_mr_ics_num'  => $merged_are,
                    'property_number' => $merged_prop,
                    'value_amount'    => $merged_value,
                    'total_amount'    => $merged_total,
                    'item_status'     => $item_status,
                    'note'            => "Merged +{$quantity} unit(s) by '{$user_name}'",
                ];

                // Audit + system logs
                if (function_exists('log_audit')) {
                    log_audit($conn, $user_id, 'inventory_items', 'UPDATE', $merge_id, $old_snap, $new_snap);
                } elseif (function_exists('log_system_edit')) {
                    log_system_edit($conn, $user_id, 'inventory_items', $merge_id, $old_snap, $new_snap);
                }

                $txn_ref = 'IN-MERGE-' . date('Ymd') . '-' . $merge_id;
                $txn_rem = "Merged +{$quantity} unit(s) into Item #{$merge_id} ({$item_name} — {$particulars})."
                    . ($serial_number   ? " Added Serial(s): {$serial_number}."   : '')
                    . ($are_mr_ics_num  ? " Added PAR/ICS: {$are_mr_ics_num}."    : '')
                    . ($property_number ? " Added Prop#: {$property_number}."     : '')
                    . " By: {$user_name}.";
                $txn = $conn->prepare(
                    "INSERT INTO inventory_transactions
                        (inventory_item_id, performed_by_id, performed_by_name,
                         transaction_type, quantity, reference_no, remarks)
                     VALUES (?, ?, ?, 'IN', ?, ?, ?)"
                );
                if ($txn) {
                    $txn->bind_param('iisiss', $merge_id, $user_id, $user_name, $quantity, $txn_ref, $txn_rem);
                    $txn->execute();
                    $txn->close();
                }

                if (function_exists('log_system')) {
                    log_system($conn, $user_id, 'INVENTORY_ITEM_MERGED',
                        "'{$user_name}' merged +{$quantity} unit(s) into Inventory Item #{$merge_id} ('{$item_name}' — '{$particulars}'). New qty: {$merged_qty}.",
                        ['item_id' => $merge_id, 'added_qty' => $quantity, 'new_qty' => $merged_qty]
                    );
                }

                $conn->commit();
                echo "<script>alert('Merged +{$quantity} unit(s) into existing Item #{$merge_id} — {$item_name} ({$particulars}). New total: {$merged_qty}.'); window.location.href='table_index.php';</script>";

            } else {
                // ── INSERT PATH ───────────────────────────────────────────────
                $stmt = $conn->prepare(
                    "INSERT INTO inventory_items
                        (item_name, particulars, are_mr_ics_num, property_number, serial_number,
                         quantity, amount, value_amount, total_amount, date_delivered, item_status)
                     VALUES (?, ?, ?, ?, ?, ?, '', ?, ?, ?, ?)"
                );
                // amount column kept blank (legacy); value_amount is the canonical cost field
                $stmt->bind_param('sssssiidss',
                    $item_name, $particulars, $are_mr_ics_num, $property_number, $serial_number,
                    $quantity, $value_amount, $total_amount, $date_delivered, $item_status
                );
                $stmt->execute();
                $new_id = (int)$conn->insert_id;
                $stmt->close();

                $new_data = compact(
                    'item_name', 'particulars', 'are_mr_ics_num', 'property_number', 'serial_number',
                    'quantity', 'value_amount', 'total_amount', 'date_delivered', 'item_status'
                );

                // Audit log
                if (function_exists('log_audit')) {
                    log_audit($conn, $user_id, 'inventory_items', 'CREATE', $new_id, null, $new_data);
                } elseif (function_exists('log_system_edit')) {
                    log_system_edit($conn, $user_id, 'inventory_items', $new_id, [], $new_data);
                }

                // Inventory transaction (IN)
                $txn_ref = 'IN-NEW-' . date('Ymd') . '-' . $new_id;
                $txn_rem = "New inventory item #{$new_id} created: {$item_name} — {$particulars}. Qty: {$quantity}."
                    . ($serial_number   ? " Serial(s): {$serial_number}."   : '')
                    . ($are_mr_ics_num  ? " PAR/ICS: {$are_mr_ics_num}."    : '')
                    . ($property_number ? " Prop#: {$property_number}."     : '')
                    . " By: {$user_name}.";
                $txn = $conn->prepare(
                    "INSERT INTO inventory_transactions
                        (inventory_item_id, performed_by_id, performed_by_name,
                         transaction_type, quantity, reference_no, remarks)
                     VALUES (?, ?, ?, 'IN', ?, ?, ?)"
                );
                if ($txn) {
                    $txn->bind_param('iisiss', $new_id, $user_id, $user_name, $quantity, $txn_ref, $txn_rem);
                    $txn->execute();
                    $txn->close();
                }

                // System log
                if (function_exists('log_system')) {
                    log_system($conn, $user_id, 'INVENTORY_ITEM_CREATED',
                        "'{$user_name}' added inventory item '{$item_name}' (ID:{$new_id}). Qty: {$quantity}.",
                        ['item_id' => $new_id, 'quantity' => $quantity]
                    );
                }

                // Sync identifiers → existing accountable_items that have none yet
                foreach ([
                    ['serial_number',   $serial_number],
                    ['property_number', $property_number],
                    ['are_mr_ics_num',  $are_mr_ics_num],
                ] as [$col, $val]) {
                    if (!empty($val)) {
                        $sync = $conn->prepare(
                            "UPDATE accountable_items SET {$col} = ?
                              WHERE inventory_item_id = ? AND is_deleted = 0
                                AND ({$col} IS NULL OR {$col} = '')"
                        );
                        if ($sync) {
                            $sync->bind_param('si', $val, $new_id);
                            $sync->execute();
                            $sync->close();
                        }
                    }
                }

                $conn->commit();
                echo "<script>alert('Item added successfully!'); window.location.href='table_index.php';</script>";
            }
        } catch (Exception $e) {
            $conn->rollback();
            $msg = addslashes($e->getMessage());
            echo "<script>alert('Error: {$msg}');</script>";
        }
    }
}

// ── UPDATE ────────────────────────────────────────────────────────────────────
if (isset($_POST['update'])) {
    verify_post_csrf();

    $id              = (int)($_POST['id']              ?? 0);
    $item_name       = trim($_POST['item_name']        ?? '');
    $particulars     = trim($_POST['particulars']      ?? '');
    $quantity        = (int)($_POST['quantity']        ?? 0);
    $value_amount    = (float)($_POST['value_amount']  ?? $_POST['amount'] ?? 0);
    $total_amount    = $value_amount * $quantity;
    $date_delivered  = trim($_POST['date_delivered']   ?? '');
    $item_status     = trim($_POST['item_status']      ?? 'Active');

    $conn->begin_transaction();
    try {
        $old_stmt = $conn->prepare("SELECT * FROM inventory_items WHERE id = ? FOR UPDATE");
        $old_stmt->bind_param("i", $id);
        $old_stmt->execute();
        $old_data = $old_stmt->get_result()->fetch_assoc();
        $old_stmt->close();

        if (!$old_data) throw new Exception("Item not found.");

        $stmt = $conn->prepare(
            "UPDATE inventory_items
                SET item_name = ?, particulars = ?, quantity = ?,
                    value_amount = ?, total_amount = ?,
                    date_delivered = ?, item_status = ?, date_updated = NOW()
              WHERE id = ?"
        );
        $stmt->bind_param("ssiiddsi",
            $item_name, $particulars, $quantity,
            $value_amount, $total_amount,
            $date_delivered, $item_status, $id
        );
        $stmt->execute();
        $stmt->close();

        $new_data = array_merge($old_data, [
            'item_name'     => $item_name,
            'particulars'   => $particulars,
            'quantity'      => $quantity,
            'value_amount'  => $value_amount,
            'total_amount'  => $total_amount,
            'date_delivered'=> $date_delivered,
            'item_status'   => $item_status,
            'updated_by'    => $user_name,
        ]);

        if (function_exists('log_audit')) {
            log_audit($conn, $user_id, 'inventory_items', 'UPDATE', $id, $old_data, $new_data);
        } elseif (function_exists('log_system_edit')) {
            log_system_edit($conn, $user_id, 'inventory_items', $id, $old_data, $new_data);
        }

        // Quantity-adjustment transaction
        $qty_diff = $quantity - (int)$old_data['quantity'];
        if ($qty_diff != 0) {
            $adj_type = ($qty_diff > 0) ? 'ADJUSTMENT' : 'SHRINKAGE';
            $adj_qty  = abs($qty_diff);
            $adj_ref  = 'ADJ-' . date('YmdHis');
            $adj_rem  = "Item #{$id} ({$item_name}) quantity changed {$old_data['quantity']} → {$quantity}. By: {$user_name}.";
            $t = $conn->prepare(
                "INSERT INTO inventory_transactions
                    (inventory_item_id, performed_by_id, performed_by_name,
                     transaction_type, quantity, reference_no, remarks)
                 VALUES (?, ?, ?, ?, ?, ?, ?)"
            );
            if ($t) {
                $t->bind_param('iississ', $id, $user_id, $user_name, $adj_type, $adj_qty, $adj_ref, $adj_rem);
                $t->execute();
                $t->close();
            }
        }

        if (function_exists('log_system')) {
            log_system($conn, $user_id, 'INVENTORY_ITEM_UPDATED',
                "'{$user_name}' updated Inventory Item #{$id} ('{$item_name}'). Qty: {$old_data['quantity']} → {$quantity}.",
                ['item_id' => $id]
            );
        }

        $conn->commit();
        echo "<script>alert('Item updated successfully!'); window.location.href='table_index.php';</script>";
    } catch (Exception $e) {
        $conn->rollback();
        $msg = addslashes($e->getMessage());
        echo "<script>alert('Error: {$msg}');</script>";
    }
}

// ── DELETE (Admin / Manager only) ─────────────────────────────────────────────
if (isset($_GET['delete']) && ($role === 'ADMIN' || $role === 'MANAGER')) {
    $id = (int)$_GET['delete'];
    $old_res = $conn->query("SELECT * FROM inventory_items WHERE id = {$id}");
    if ($old_data = $old_res->fetch_assoc()) {
        $stmt = $conn->prepare("DELETE FROM inventory_items WHERE id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            if (function_exists('log_audit')) {
                log_audit($conn, $user_id, 'inventory_items', 'DELETE', $id, $old_data, null);
            } elseif (function_exists('log_system_edit')) {
                log_system_edit($conn, $user_id, 'inventory_items', $id, $old_data, []);
            }
            if (function_exists('log_system')) {
                log_system($conn, $user_id, 'INVENTORY_ITEM_DELETED',
                    "'{$user_name}' deleted Inventory Item #{$id} ('{$old_data['item_name']}').",
                    ['item_id' => $id]
                );
            }
        }
        $stmt->close();
    }
    header("Location: table_index.php");
    exit;
}

// ------------------------------------------------------------------
// 2. SERVER-SIDE SEARCH & PAGINATION
// ------------------------------------------------------------------
$perPage = 10;
$page    = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset  = ($page - 1) * $perPage;
$search  = trim($_GET['search'] ?? '');

$where_sql = "1=1";
$params = [];
$types  = "";

if ($search !== '') {
    $where_sql .= " AND (item_name LIKE ? OR particulars LIKE ? OR item_status LIKE ?)";
    $like = "%$search%";
    $params = [$like, $like, $like];
    $types  = "sss";
}

$countSql  = "SELECT COUNT(*) AS cnt FROM inventory_items WHERE $where_sql";
$countStmt = $conn->prepare($countSql);
if ($types) $countStmt->bind_param($types, ...$params);
$countStmt->execute();
$total = $countStmt->get_result()->fetch_assoc()['cnt'] ?? 0;
$countStmt->close();

$totalPages = max(1, (int)ceil($total / $perPage));

$dataSql  = "SELECT * FROM inventory_items WHERE $where_sql ORDER BY id DESC LIMIT ? OFFSET ?";
$dataStmt = $conn->prepare($dataSql);
$params[] = $perPage;
$params[] = $offset;
$types   .= "ii";
$dataStmt->bind_param($types, ...$params);
$dataStmt->execute();
$result = $dataStmt->get_result();
$dataStmt->close();
?>

<?php /* ── Page header + Add button ─────────────────────────────────────── */ ?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0"><span class="text-muted fw-light">Inventory /</span> Item Masterlist</h4>
    <?php if ($role === 'ADMIN' || $role === 'MANAGER'): ?>
        <button class="btn btn-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#addModal">
            <i class="bx bx-plus me-1"></i> Add New Item
        </button>
    <?php endif; ?>
</div>

<?php /* ── Data table ──────────────────────────────────────────────────── */ ?>
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center flex-wrap gap-3">
        <h5 class="card-title mb-0">Registered Items</h5>
        <form method="GET" class="d-flex" style="max-width:320px;width:100%;">
            <div class="input-group input-group-merge shadow-none border">
                <span class="input-group-text border-0 bg-transparent"><i class="bx bx-search text-muted"></i></span>
                <input type="text" name="search" class="form-control border-0"
                    placeholder="Search items..." value="<?= htmlspecialchars($search) ?>">
                <?php if ($search): ?>
                    <a href="table_index.php" class="btn btn-sm btn-outline-secondary border-0" title="Clear Search">
                        <i class="bx bx-x"></i>
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <div class="table-responsive text-nowrap">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Item ID</th>
                    <th>Item Name &amp; Particulars</th>
                    <th class="text-center">Available Qty</th>
                    <th>Unit Cost</th>
                    <th>Delivered</th>
                    <th>Status</th>
                    <?php if ($role === 'ADMIN' || $role === 'MANAGER'): ?>
                        <th class="text-center">Action</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php if ($result->num_rows > 0): while ($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><span class="text-muted">#<?= $row['id'] ?></span></td>
                        <td>
                            <div class="d-flex flex-column">
                                <strong class="text-dark"><?= htmlspecialchars($row['item_name']) ?></strong>
                                <small class="text-muted text-truncate" style="max-width:250px;"
                                    title="<?= htmlspecialchars($row['particulars']) ?>">
                                    <?= htmlspecialchars($row['particulars']) ?>
                                </small>
                                <?php if (!empty($row['are_mr_ics_num'])): ?>
                                    <small class="text-muted" style="font-size:.72rem;">
                                        <span class="text-secondary fw-semibold">PAR/ICS:</span>
                                        <?= htmlspecialchars($row['are_mr_ics_num']) ?>
                                    </small>
                                <?php endif; ?>
                                <?php if (!empty($row['serial_number'])): ?>
                                    <small class="text-muted" style="font-size:.72rem;">
                                        <span class="text-secondary fw-semibold">S/N:</span>
                                        <?= htmlspecialchars($row['serial_number']) ?>
                                    </small>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td class="text-center">
                            <?php $qtyClass = $row['quantity'] > 0 ? 'success' : 'danger'; ?>
                            <span class="badge bg-label-<?= $qtyClass ?> fw-bold" style="font-size:.85rem;">
                                <?= number_format($row['quantity']) ?>
                            </span>
                        </td>
                        <td>₱<?= number_format((float)($row['value_amount'] ?: $row['amount']), 2) ?></td>
                        <td><small class="text-muted"><?= date('M d, Y', strtotime($row['date_delivered'])) ?></small></td>
                        <td>
                            <?php
                            $statusBadge = match(strtolower($row['item_status'])) {
                                'active'      => 'bg-label-primary',
                                'inactive'    => 'bg-label-secondary',
                                'returned'    => 'bg-label-info',
                                'defective'   => 'bg-label-danger',
                                'replaced'    => 'bg-label-warning',
                                default       => 'bg-label-dark'
                            };
                            ?>
                            <span class="badge <?= $statusBadge ?>"><?= htmlspecialchars($row['item_status']) ?></span>
                        </td>

                        <?php if ($role === 'ADMIN' || $role === 'MANAGER'): ?>
                        <td class="text-center">
                            <button type="button" class="btn btn-icon btn-sm btn-outline-primary me-1"
                                data-bs-toggle="modal" data-bs-target="#editModal" title="Edit"
                                data-id="<?= $row['id'] ?>"
                                data-item_name="<?= htmlspecialchars($row['item_name'],    ENT_QUOTES) ?>"
                                data-particulars="<?= htmlspecialchars($row['particulars'], ENT_QUOTES) ?>"
                                data-quantity="<?= $row['quantity'] ?>"
                                data-value_amount="<?= (float)($row['value_amount'] ?: $row['amount']) ?>"
                                data-date_delivered="<?= $row['date_delivered'] ?>"
                                data-status="<?= htmlspecialchars($row['item_status'], ENT_QUOTES) ?>">
                                <i class="bx bx-edit-alt"></i>
                            </button>
                            <a href="?delete=<?= $row['id'] ?>" class="btn btn-icon btn-sm btn-outline-danger"
                                onclick="return confirm('WARNING: Deleting this item may break historical transaction records. Continue?')"
                                title="Delete">
                                <i class="bx bx-trash"></i>
                            </a>
                        </td>
                        <?php endif; ?>
                    </tr>
                <?php endwhile; else: ?>
                    <tr>
                        <td colspan="7" class="text-center py-5">
                            <i class="bx bx-box text-muted fs-1 mb-2"></i>
                            <p class="mb-0 text-muted">No items found matching your criteria.</p>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($totalPages > 1): ?>
    <div class="card-footer bg-white border-top d-flex justify-content-between align-items-center py-3">
        <small class="text-muted">
            Showing <?= ($total > 0 ? $offset + 1 : 0) ?>
            to <?= min($total, $offset + $perPage) ?>
            of <?= $total ?> items
        </small>
        <ul class="pagination pagination-sm m-0">
            <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                <a class="page-link" href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>">
                    <i class="bx bx-chevron-left"></i>
                </a>
            </li>
            <?php for ($p = max(1, $page - 2); $p <= min($totalPages, $page + 2); $p++): ?>
                <li class="page-item <?= ($p == $page) ? 'active' : '' ?>">
                    <a class="page-link" href="?page=<?= $p ?>&search=<?= urlencode($search) ?>"><?= $p ?></a>
                </li>
            <?php endfor; ?>
            <li class="page-item <?= ($page >= $totalPages) ? 'disabled' : '' ?>">
                <a class="page-link" href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>">
                    <i class="bx bx-chevron-right"></i>
                </a>
            </li>
        </ul>
    </div>
    <?php endif; ?>
</div>

<?php if ($role === 'ADMIN' || $role === 'MANAGER'): ?>

<!-- ══════════════════════════════════════════════════════════════
     REGISTER NEW ITEM MODAL
     Fields + logic mirror encoder_module.php "add_inventory_item":
       • are_mr_ics_num  (PAR/ICS)  → textarea → checkbox checklist
       • property_number            → textarea → checkbox checklist
       • serial_number              → textarea → checkbox checklist
       • value_amount / total_amount (auto-calc)
       • Merge detection (same item_name + particulars + date_delivered)
     ══════════════════════════════════════════════════════════════ -->
<style>
/* ── checklist chips ─────────────────────────────────────────────────── */
.tc-checklist-wrap {
    display: flex;
    flex-wrap: wrap;
    gap: .35rem;
    margin-top: .45rem;
    padding: .45rem .55rem;
    border: 1.5px solid #d9dee3;
    border-radius: .45rem;
    background: #f8f8fb;
    min-height: 2.4rem;
}
.tc-checklist-wrap.d-none { display: none !important; }
.tc-check-chip {
    display: inline-flex;
    align-items: center;
    gap: .3rem;
    padding: .22rem .55rem;
    border-radius: 2rem;
    border: 1.5px solid #d9dee3;
    background: #fff;
    font-size: .78rem;
    font-family: monospace;
    color: #566a7f;
    cursor: pointer;
    user-select: none;
    transition: border-color .12s, background .12s;
}
.tc-check-chip input[type="checkbox"] { display: none; }
.tc-check-chip.tc-checked {
    background: #e8f4fd;
    border-color: #696cff;
    color: #696cff;
    font-weight: 600;
}
.tc-check-chip.tc-disabled {
    background: #f0f1ff;
    border-color: #c5c7ff;
    color: #9098d0;
    cursor: not-allowed;
    opacity: .7;
}
/* ── serial count badge ──────────────────────────────────────────── */
.tc-token-count {
    display: none;
    font-size: .72rem;
    font-weight: 600;
    padding: .1em .5em;
    border-radius: 2rem;
    background: #696cff;
    color: #fff;
    vertical-align: middle;
    margin-left: .35rem;
}
.tc-token-count.visible { display: inline-block; }
/* ── match hints ─────────────────────────────────────────────────── */
.tc-hint {
    font-size: .76rem;
    font-weight: 600;
    margin-top: .3rem;
    display: none;
}
.tc-hint.ok    { color: #28c76f; }
.tc-hint.warn  { color: #ff9f43; }
.tc-hint.error { color: #ea5455; }
/* ── total amount display ────────────────────────────────────────── */
#add_total_amount {
    background: #f0f1ff;
    border-color: #c5c7ff;
    color: #696cff;
    font-weight: 600;
}
</style>

<div class="modal fade" id="addModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <form method="POST" id="tc_add_form" class="modal-content" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bx bx-plus-circle me-2 text-primary"></i>Register New Item
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body">

                <!-- ── SECTION: Basic Information ─────────────────────────── -->
                <div class="mb-1">
                    <span class="badge bg-label-primary mb-2">Basic Information</span>
                </div>
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">
                            Item Name <span class="text-danger">*</span>
                        </label>
                        <input type="text" name="item_name" id="add_item_name"
                            class="form-control" required maxlength="255"
                            placeholder="e.g. Desktop Computer">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">
                            Particulars / Description <span class="text-danger">*</span>
                        </label>
                        <input type="text" name="particulars" id="add_particulars"
                            class="form-control" required maxlength="255"
                            placeholder="Brand, Model, Specs…">
                    </div>
                </div>

                <!-- ── SECTION: Identifiers ───────────────────────────────── -->
                <div class="mb-1">
                    <span class="badge bg-label-secondary mb-2">Identifiers</span>
                </div>
                <div class="row g-3 mb-3">

                    <!-- Property Number -->
                    <div class="col-12">
                        <label class="form-label fw-semibold">
                            Property Number
                            <small class="text-muted fw-normal">— separate multiple with
                                <code class="bg-light px-1 rounded">/</code>
                                (e.g. 2024-07-1169 / 2024-07-1170)
                            </small>
                        </label>
                        <!-- hidden field submitted -->
                        <input type="hidden" name="property_number" id="add_prop_hidden">
                        <div class="position-relative">
                            <textarea id="add_prop_textarea"
                                class="form-control font-monospace"
                                rows="2"
                                placeholder="e.g. 2024-07-1169 / 2024-07-1170&#10;Leave blank if no property number."
                                oninput="tcOnPropInput()"></textarea>
                            <span class="tc-token-count" id="add_prop_count">0</span>
                        </div>
                        <div id="add_prop_checklist" class="tc-checklist-wrap d-none"></div>
                        <div id="add_prop_hint" class="tc-hint"></div>
                    </div>

                    <!-- PAR / ICS Number -->
                    <div class="col-12">
                        <label class="form-label fw-semibold">
                            PAR / ICS Number
                            <small class="text-muted fw-normal">— separate multiple with
                                <code class="bg-light px-1 rounded">/</code>
                                (e.g. PAR-2024-001 / ICS-2024-002)
                            </small>
                        </label>
                        <input type="hidden" name="are_mr_ics_num" id="add_are_hidden">
                        <div class="position-relative">
                            <textarea id="add_are_textarea"
                                class="form-control font-monospace"
                                rows="2"
                                placeholder="e.g. PAR-2024-001 / ICS-2024-002&#10;Leave blank if no PAR/ICS number."
                                oninput="tcOnAreInput()"></textarea>
                            <span class="tc-token-count" id="add_are_count">0</span>
                        </div>
                        <div id="add_are_checklist" class="tc-checklist-wrap d-none"></div>
                        <div id="add_are_hint" class="tc-hint"></div>
                    </div>

                    <!-- Serial Number -->
                    <div class="col-12">
                        <label class="form-label fw-semibold">
                            Serial Number(s)
                            <small class="text-muted fw-normal">— separate multiple with
                                <code class="bg-light px-1 rounded">/</code>
                            </small>
                        </label>
                        <input type="hidden" name="serial_number" id="add_sn_hidden">
                        <div class="position-relative">
                            <textarea id="add_sn_textarea"
                                class="form-control font-monospace"
                                rows="2"
                                placeholder="e.g. SN-001 / SN-002&#10;Leave blank if no serial number."
                                oninput="tcOnSnInput()"></textarea>
                            <span class="tc-token-count" id="add_sn_count">0</span>
                        </div>
                        <div id="add_sn_checklist" class="tc-checklist-wrap d-none"></div>
                        <div id="add_sn_hint" class="tc-hint"></div>
                        <small class="text-muted d-block mt-1" id="add_sn_auto_note" style="display:none!important;"></small>
                    </div>
                </div>

                <!-- ── SECTION: Financial & Quantity ─────────────────────── -->
                <div class="mb-1">
                    <span class="badge bg-label-success mb-2">Financial &amp; Quantity</span>
                </div>
                <div class="row g-3 mb-3">
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">
                            Initial Quantity <span class="text-danger">*</span>
                        </label>
                        <input type="number" name="quantity" id="add_quantity"
                            class="form-control" min="1" required value="1"
                            placeholder="Auto-filled from serials"
                            oninput="tcCalcTotal()">
                        <small class="text-muted">Auto-fills from serial count if serials are entered.</small>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Unit Cost (₱)</label>
                        <input type="number" name="value_amount" id="add_value_amount"
                            class="form-control" step="0.01" min="0" value="0"
                            placeholder="0.00"
                            oninput="tcCalcTotal()">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">
                            Total Cost (₱)
                            <small class="text-muted fw-normal">(Unit × Qty — auto)</small>
                        </label>
                        <input type="number" name="total_amount" id="add_total_amount"
                            class="form-control" step="0.01" min="0"
                            placeholder="0.00" readonly>
                    </div>
                </div>

                <!-- ── SECTION: Delivery & Status ────────────────────────── -->
                <div class="mb-1">
                    <span class="badge bg-label-warning mb-2">Delivery &amp; Status</span>
                </div>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">
                            Date Delivered <span class="text-danger">*</span>
                        </label>
                        <input type="date" name="date_delivered" id="add_date_delivered"
                            class="form-control" required value="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Item Status</label>
                        <select name="item_status" class="form-select">
                            <option value="Active" selected>Active</option>
                            <option value="Inactive">Inactive</option>
                            <option value="Returned">Returned</option>
                            <option value="Defective">Defective</option>
                            <option value="Replaced">Replaced</option>
                        </select>
                    </div>
                </div>

            </div><!-- /modal-body -->

            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" name="add" class="btn btn-primary" id="tc_add_submit_btn">
                    <i class="bx bx-save me-1"></i> Save Item
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════════
     MODIFY ITEM MODAL
     ══════════════════════════════════════════════════════════════ -->
<div class="modal fade" id="editModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <form method="POST" class="modal-content">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" name="id" id="edit-id">

            <div class="modal-header">
                <h5 class="modal-title"><i class="bx bx-edit me-2 text-primary"></i>Modify Item</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Item Name <span class="text-danger">*</span></label>
                        <input type="text" name="item_name" id="edit-item-name" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Particulars <span class="text-danger">*</span></label>
                        <input type="text" name="particulars" id="edit-particulars" class="form-control" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Current Quantity <span class="text-danger">*</span></label>
                        <input type="number" name="quantity" id="edit-quantity" class="form-control" required>
                        <small class="text-muted d-block mt-1">Changes are logged as ADJUSTMENT / SHRINKAGE.</small>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Unit Cost (₱)</label>
                        <input type="number" name="value_amount" id="edit-amount" class="form-control" step="0.01">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Date Delivered <span class="text-danger">*</span></label>
                        <input type="date" name="date_delivered" id="edit-date" class="form-control" required>
                    </div>
                    <div class="col-md-12">
                        <label class="form-label fw-semibold">Status <span class="text-danger">*</span></label>
                        <select name="item_status" id="edit-status" class="form-select" required>
                            <option value="Active">Active</option>
                            <option value="Inactive">Inactive</option>
                            <option value="Returned">Returned</option>
                            <option value="Defective">Defective</option>
                            <option value="Replaced">Replaced</option>
                        </select>
                    </div>
                </div>
                <div class="alert alert-info mt-3 py-2 mb-0" style="font-size:.82rem;">
                    <i class="bx bx-info-circle me-1"></i>
                    To update Serial Numbers, Property Numbers, or PAR/ICS Numbers, use the
                    <strong>Encoder Module</strong> or the dedicated item detail page.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" name="update" class="btn btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════════
     JAVASCRIPT — "Register New Item" modal logic
     All functions prefixed tc_ to avoid collisions.
     ══════════════════════════════════════════════════════════════ -->
<script>
(function () {
    'use strict';

    /* ── Utility: split "/" delimited string into trimmed, non-empty tokens ── */
    function tcSplit(str) {
        return (str || '').split('/').map(function (t) { return t.trim(); }).filter(Boolean);
    }

    /* ── Build a chip checklist from tokens array ────────────────────────────
       wrapEl   : container div
       hiddenEl : hidden input that receives " / " joined checked values
       tokens   : string[] of new (checkable) tokens
       existing : string[] of already-saved tokens (shown disabled)
    */
    function tcBuildChecklist(wrapEl, hiddenEl, tokens, existing, onChangeCallback) {
        wrapEl.innerHTML = '';
        if (!tokens.length && !existing.length) {
            wrapEl.classList.add('d-none');
            if (hiddenEl) hiddenEl.value = '';
            return;
        }
        wrapEl.classList.remove('d-none');

        function syncHidden() {
            var checked = wrapEl.querySelectorAll('input[type="checkbox"]:not(:disabled):checked');
            if (hiddenEl) {
                hiddenEl.value = Array.prototype.map.call(checked, function (cb) {
                    return cb.value;
                }).join(' / ');
            }
            if (onChangeCallback) onChangeCallback();
        }

        // Existing tokens → disabled chips
        existing.forEach(function (tok) {
            var lbl = document.createElement('label');
            lbl.className = 'tc-check-chip tc-checked tc-disabled';
            lbl.title = tok + ' (already saved)';
            var cb = document.createElement('input');
            cb.type = 'checkbox'; cb.value = tok; cb.checked = true; cb.disabled = true;
            lbl.appendChild(cb);
            lbl.appendChild(document.createTextNode(tok));
            wrapEl.appendChild(lbl);
        });

        // New tokens → checkable chips (auto-checked)
        tokens.forEach(function (tok) {
            var lbl = document.createElement('label');
            lbl.className = 'tc-check-chip tc-checked';
            lbl.title = tok;
            var cb = document.createElement('input');
            cb.type = 'checkbox'; cb.value = tok; cb.checked = true;
            cb.addEventListener('change', function () {
                lbl.classList.toggle('tc-checked', cb.checked);
                syncHidden();
            });
            lbl.appendChild(cb);
            lbl.appendChild(document.createTextNode(tok));
            wrapEl.appendChild(lbl);
        });

        syncHidden(); // initialise hidden value
    }

    /* ── Update a match-hint element ──────────────────────────────────────── */
    function tcSetHint(hintEl, got, need, label) {
        if (!hintEl) return;
        if (!need || need === 0) { hintEl.style.display = 'none'; return; }
        hintEl.style.display = 'block';
        if (got === need) {
            hintEl.className = 'tc-hint ok';
            hintEl.textContent = '✓ ' + got + '/' + need + ' ' + label + ' checked — matches quantity.';
        } else if (got < need) {
            hintEl.className = 'tc-hint warn';
            hintEl.textContent = '✗ ' + got + '/' + need + ' ' + label + ' checked — need ' + (need - got) + ' more.';
        } else {
            hintEl.className = 'tc-hint error';
            hintEl.textContent = '✗ ' + got + '/' + need + ' ' + label + ' checked — ' + (got - need) + ' too many, uncheck some.';
        }
    }

    /* ── Rebuild all three checklists after any input change ─────────────── */
    function tcRebuildAll() {
        var qty    = parseInt(document.getElementById('add_quantity').value, 10) || 0;
        var snToks = tcSplit(document.getElementById('add_sn_textarea').value);
        var prToks = tcSplit(document.getElementById('add_prop_textarea').value);
        var arToks = tcSplit(document.getElementById('add_are_textarea').value);

        // Counts for badges
        function setBadge(id, count) {
            var b = document.getElementById(id);
            if (!b) return;
            b.textContent = count;
            b.classList.toggle('visible', count > 0);
        }
        setBadge('add_sn_count',   snToks.length);
        setBadge('add_prop_count', prToks.length);
        setBadge('add_are_count',  arToks.length);

        // Auto-fill quantity from serial count
        if (snToks.length > 0) {
            document.getElementById('add_quantity').value = snToks.length;
            qty = snToks.length;
            var note = document.getElementById('add_sn_auto_note');
            if (note) {
                note.textContent = '✓ ' + snToks.length + ' serial(s) entered — quantity set to ' + snToks.length + '. Edit manually if needed.';
                note.style.display = 'block';
            }
        } else {
            var note2 = document.getElementById('add_sn_auto_note');
            if (note2) note2.style.display = 'none';
        }

        tcBuildChecklist(
            document.getElementById('add_sn_checklist'),
            document.getElementById('add_sn_hidden'),
            snToks, [],
            function () { tcUpdateSnHint(qty); }
        );
        tcBuildChecklist(
            document.getElementById('add_prop_checklist'),
            document.getElementById('add_prop_hidden'),
            prToks, [],
            function () { tcUpdatePropHint(qty); }
        );
        tcBuildChecklist(
            document.getElementById('add_are_checklist'),
            document.getElementById('add_are_hidden'),
            arToks, [],
            function () { tcUpdateAreHint(qty); }
        );

        tcUpdateSnHint(qty);
        tcUpdatePropHint(qty);
        tcUpdateAreHint(qty);
        tcCalcTotal();
    }

    function tcCheckedCount(wrapId) {
        var wrap = document.getElementById(wrapId);
        if (!wrap) return 0;
        return wrap.querySelectorAll('input[type="checkbox"]:not(:disabled):checked').length;
    }

    function tcUpdateSnHint(qty) {
        tcSetHint(document.getElementById('add_sn_hint'), tcCheckedCount('add_sn_checklist'), qty, 'serial(s)');
    }
    function tcUpdatePropHint(qty) {
        tcSetHint(document.getElementById('add_prop_hint'), tcCheckedCount('add_prop_checklist'), qty, 'property number(s)');
    }
    function tcUpdateAreHint(qty) {
        tcSetHint(document.getElementById('add_are_hint'), tcCheckedCount('add_are_checklist'), qty, 'PAR/ICS number(s)');
    }

    /* ── Exposed event handlers (called from oninput attributes) ─────────── */
    window.tcOnSnInput   = function () { tcRebuildAll(); };
    window.tcOnPropInput = function () { tcRebuildAll(); };
    window.tcOnAreInput  = function () { tcRebuildAll(); };

    /* ── Total Cost auto-calculator ───────────────────────────────────────── */
    window.tcCalcTotal = function () {
        var val  = parseFloat(document.getElementById('add_value_amount').value) || 0;
        var qty  = parseInt(document.getElementById('add_quantity').value, 10)   || 0;
        var tot  = document.getElementById('add_total_amount');
        if (tot) tot.value = (val * qty) > 0 ? (val * qty).toFixed(2) : '';
        var q = parseInt(document.getElementById('add_quantity').value, 10) || 0;
        tcUpdateSnHint(q);
        tcUpdatePropHint(q);
        tcUpdateAreHint(q);
    };

    /* ── Reset modal when it's hidden ────────────────────────────────────── */
    document.addEventListener('DOMContentLoaded', function () {
        var modal = document.getElementById('addModal');
        if (modal) {
            modal.addEventListener('hidden.bs.modal', function () {
                var form = document.getElementById('tc_add_form');
                if (form) form.reset();
                ['add_sn_checklist','add_prop_checklist','add_are_checklist'].forEach(function (id) {
                    var el = document.getElementById(id);
                    if (el) { el.innerHTML = ''; el.classList.add('d-none'); }
                });
                ['add_sn_hidden','add_prop_hidden','add_are_hidden'].forEach(function (id) {
                    var el = document.getElementById(id);
                    if (el) el.value = '';
                });
                ['add_sn_hint','add_prop_hint','add_are_hint'].forEach(function (id) {
                    var el = document.getElementById(id);
                    if (el) { el.textContent = ''; el.style.display = 'none'; }
                });
                ['add_sn_count','add_prop_count','add_are_count'].forEach(function (id) {
                    var el = document.getElementById(id);
                    if (el) { el.textContent = '0'; el.classList.remove('visible'); }
                });
                var tot = document.getElementById('add_total_amount');
                if (tot) tot.value = '';
                var note = document.getElementById('add_sn_auto_note');
                if (note) note.style.display = 'none';
            });
        }

        // ── Edit modal population ─────────────────────────────────────────
        var editModal = document.getElementById('editModal');
        if (editModal) {
            editModal.addEventListener('show.bs.modal', function (event) {
                var btn = event.relatedTarget;
                document.getElementById('edit-id').value          = btn.dataset.id;
                document.getElementById('edit-item-name').value   = btn.dataset.item_name;
                document.getElementById('edit-particulars').value = btn.dataset.particulars;
                document.getElementById('edit-quantity').value    = btn.dataset.quantity;
                document.getElementById('edit-amount').value      = btn.dataset.value_amount;
                document.getElementById('edit-date').value        = btn.dataset.date_delivered;
                document.getElementById('edit-status').value      = btn.dataset.status;
            });
        }
    });

}()); // end IIFE
</script>

<?php endif; ?>