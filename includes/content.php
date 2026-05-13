<?php
// Pull identity from session if not already set by the including script
if (!function_exists('h')) {
  function h($s)
  {
    return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8');
  }
}

// Safe fallbacks — works whether index.php sets these or not
if (empty($username)) {
  $username = $_SESSION['full_name'] ?? $_SESSION['username'] ?? 'User';
}
if (empty($role)) {
  $role = $_SESSION['role'] ?? '';
}
$role_display = strtoupper($role);

// Quick metrics
$total_items = 0;
$total_transactions = 0;
$total_assigned = 0;
$total_borrowed = 0;
$recent_transactions = [];
$by_type = [];
$top_items = [];
$total_transactions_range = 0;
$total_qty_range = 0;

// Summary range (days) for Activity Summary — safe int from GET
$summary_range = isset($_GET['summary_range']) ? (int)$_GET['summary_range'] : 30;
if ($summary_range <= 0) $summary_range = 30;

try {
  // Total inventory items (all time)
  $res = $conn->query("SELECT COUNT(*) AS cnt, IFNULL(SUM(quantity),0) AS qty_sum FROM inventory_items");
  if ($res) {
    $r = $res->fetch_assoc();
    $total_items = intval($r['cnt']);
  }

  // Total transactions (all time)
  $res = $conn->query("SELECT COUNT(*) AS cnt FROM inventory_transactions");
  if ($res) {
    $total_transactions = intval($res->fetch_assoc()['cnt'] ?? 0);
  }

  // Assigned / borrowed (all time)
  $res = $conn->query("SELECT IFNULL(SUM(assigned_quantity),0) AS total_assigned FROM accountable_items WHERE is_deleted = 0");
  if ($res) $total_assigned = intval($res->fetch_assoc()['total_assigned'] ?? 0);

  $res = $conn->query("SELECT IFNULL(SUM(quantity),0) AS total_borrowed FROM borrowed_items WHERE is_returned = 0");
  if ($res) $total_borrowed = intval($res->fetch_assoc()['total_borrowed'] ?? 0);

  // Recent transactions (latest 8)
  $sql = "
      SELECT
        it.transaction_id,
        it.transaction_date,
        ii.item_name,
        it.transaction_type,
        it.quantity,
        it.reference_no,
        (
          SELECT ai.person_name FROM accountable_items ai
          WHERE ai.inventory_item_id = it.inventory_item_id AND ai.is_deleted = 0
          ORDER BY ai.date_assigned DESC, ai.id DESC LIMIT 1
        ) AS current_holder
      FROM inventory_transactions it
      JOIN inventory_items ii ON ii.id = it.inventory_item_id
      ORDER BY it.transaction_date DESC
      LIMIT 8
    ";
  $res = $conn->query($sql);
  if ($res) $recent_transactions = $res->fetch_all(MYSQLI_ASSOC);

  // Build date-filter WHERE for activity summary
  $rangeWhere = "WHERE it.transaction_date >= DATE_SUB(NOW(), INTERVAL {$summary_range} DAY)";

  // Transactions by type (for chart) limited to range
  $sql = "SELECT it.transaction_type, COUNT(*) AS cnt
            FROM inventory_transactions it
            {$rangeWhere}
            GROUP BY it.transaction_type";
  $res = $conn->query($sql);
  if ($res) $by_type = $res->fetch_all(MYSQLI_ASSOC);

  // Top items by transacted qty within range
  $sql = "SELECT ii.item_name, IFNULL(SUM(it.quantity),0) AS total_qty
            FROM inventory_transactions it
            JOIN inventory_items ii ON ii.id = it.inventory_item_id
            {$rangeWhere}
            GROUP BY ii.item_name
            ORDER BY total_qty DESC
            LIMIT 6";
  $res = $conn->query($sql);
  if ($res) $top_items = $res->fetch_all(MYSQLI_ASSOC);

  // Totals within range
  $sql = "SELECT COUNT(*) AS cnt, IFNULL(SUM(quantity),0) AS qty_sum FROM inventory_transactions it {$rangeWhere}";
  $res = $conn->query($sql);
  if ($res) {
    $r = $res->fetch_assoc();
    $total_transactions_range = intval($r['cnt']);
    $total_qty_range = intval($r['qty_sum']);
  }
} catch (Exception $e) {
  // Quiet fallback; variables already initialized
}
?>

<style>
  .hover-card {
    transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
  }

  .hover-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 .5rem 1rem rgba(0, 0, 0, .15) !important;
  }

  .icon-box {
    width: 48px;
    height: 48px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 12px;
  }

  .icon-box i {
    font-size: 1.5rem;
  }

  .transaction-list-item:last-child {
    border-bottom: none !important;
  }

  .avatar-icon {
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
  }
</style>

<div class="content-wrapper">
  <div class="container-xxl grow container-p-y">

    <div class="d-flex justify-content-between align-items-center py-3 mb-4">
      <h4 class="fw-bold mb-0">
        <span class="text-muted fw-light">Dashboard /</span> Welcome, <?= h($username) ?>
      </h4>
      <span class="badge bg-label-primary px-3 py-2 rounded-pill"><?= h($role_display) ?></span>
    </div>

    <div class="row g-4">

      <div class="col-12 col-sm-6 col-lg-3">
        <div class="card shadow-sm hover-card h-100 border-0">
          <div class="card-body">
            <div class="d-flex align-items-center justify-content-between">
              <div>
                <small class="text-muted text-uppercase fw-semibold mb-1 d-block">Total Items</small>
                <h3 class="mb-0 fw-bold"><?= number_format($total_items) ?></h3>
                <small class="text-success fw-semibold"><i class='bx bx-check-circle me-1'></i>Inventory</small>
              </div>
              <div class="icon-box bg-label-primary">
                <i class="bx bx-package text-primary"></i>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="col-12 col-sm-6 col-lg-3">
        <div class="card shadow-sm hover-card h-100 border-0">
          <div class="card-body">
            <div class="d-flex align-items-center justify-content-between">
              <div>
                <small class="text-muted text-uppercase fw-semibold mb-1 d-block">Transactions</small>
                <h3 class="mb-0 fw-bold"><?= number_format($total_transactions) ?></h3>
                <small class="text-muted"><i class='bx bx-history me-1'></i>All time</small>
              </div>
              <div class="icon-box bg-label-success">
                <i class="bx bx-transfer-alt text-success"></i>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="col-12 col-sm-6 col-lg-3">
        <div class="card shadow-sm hover-card h-100 border-0">
          <div class="card-body">
            <div class="d-flex align-items-center justify-content-between">
              <div>
                <small class="text-muted text-uppercase fw-semibold mb-1 d-block">Assigned Items</small>
                <h3 class="mb-0 fw-bold"><?= number_format($total_assigned) ?></h3>
                <small class="text-warning fw-semibold"><i class='bx bx-user-pin me-1'></i>Accountability</small>
              </div>
              <div class="icon-box bg-label-warning">
                <i class="bx bx-user-check text-warning"></i>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="col-12 col-sm-6 col-lg-3">
        <div class="card shadow-sm hover-card h-100 border-0">
          <div class="card-body">
            <div class="d-flex align-items-center justify-content-between">
              <div>
                <small class="text-muted text-uppercase fw-semibold mb-1 d-block">Borrowed</small>
                <h3 class="mb-0 fw-bold"><?= number_format($total_borrowed) ?></h3>
                <small class="text-danger fw-semibold"><i class='bx bx-error-circle me-1'></i>Not returned</small>
              </div>
              <div class="icon-box bg-label-danger">
                <i class="bx bx-transfer text-danger"></i>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="col-12 col-lg-8">
        <div class="card h-100 shadow-sm border-0">
          <div class="card-header border-bottom bg-transparent d-flex justify-content-between align-items-center">
            <h5 class="card-title mb-0 fw-bold">Activity Summary</h5>
            <div class="d-flex align-items-center gap-2">
              <small class="text-muted d-none d-sm-inline">Range:</small>
              <div class="btn-group btn-group-sm" role="group" aria-label="Range selector">
                <button type="button" class="btn btn-outline-primary <?= $summary_range === 7 ? 'active' : '' ?>" onclick="updateRange(7)">7d</button>
                <button type="button" class="btn btn-outline-primary <?= $summary_range === 30 ? 'active' : '' ?>" onclick="updateRange(30)">30d</button>
                <button type="button" class="btn btn-outline-primary <?= $summary_range === 90 ? 'active' : '' ?>" onclick="updateRange(90)">90d</button>
                <button type="button" class="btn btn-outline-primary <?= $summary_range === 365 ? 'active' : '' ?>" onclick="updateRange(365)">1y</button>
              </div>
            </div>
          </div>
          <div class="card-body pt-4">
            <div class="row mb-4 bg-light rounded p-3 mx-0">
              <div class="col-6 col-md-4">
                <div class="small text-muted text-uppercase fw-semibold mb-1">Period Transactions</div>
                <div class="h4 mb-0 fw-bold text-primary"><?= number_format($total_transactions_range) ?></div>
              </div>
              <div class="col-6 col-md-4 border-start">
                <div class="small text-muted text-uppercase fw-semibold mb-1">Period Volume (Qty)</div>
                <div class="h4 mb-0 fw-bold text-primary"><?= number_format($total_qty_range) ?></div>
              </div>
              <div class="col-12 col-md-4 d-flex align-items-center justify-content-md-end mt-3 mt-md-0 border-start-md">
                <div class="dropdown">
                  <button class="btn btn-primary btn-sm dropdown-toggle" type="button" id="exportDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="bx bx-export me-1"></i> Export Data
                  </button>
                  <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="exportDropdown">
                    <li><a class="dropdown-item" href="<?= site_url('table/reports.php') ?>?<?= http_build_query(array_merge($_GET, ['export' => 'csv'])) ?>"><i class="bx bx-file me-2"></i>Export as CSV</a></li>
                    <li><a class="dropdown-item" href="<?= site_url('table/reports.php') ?>?<?= http_build_query(array_merge($_GET, ['export' => 'xlsx'])) ?>"><i class="bx bx-spreadsheet me-2"></i>Export as Excel</a></li>
                  </ul>
                </div>
              </div>
            </div>

            <div class="row g-4">
              <div class="col-12 col-md-5">
                <h6 class="text-center text-muted mb-3">Transactions by Type</h6>
                <div style="position: relative; height:200px;">
                  <canvas id="chartByType"></canvas>
                </div>
              </div>
              <div class="col-12 col-md-7">
                <h6 class="text-center text-muted mb-3">Top Transacted Items</h6>
                <div style="position: relative; height:200px;">
                  <canvas id="chartTopItems"></canvas>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="col-12 col-lg-4">
        <div class="card h-100 shadow-sm border-0">
          <div class="card-header border-bottom bg-transparent d-flex justify-content-between align-items-center">
            <h5 class="card-title mb-0 fw-bold">Recent Movements</h5>
            <a href="<?= site_url('table/transaction_logs.php') ?>" class="btn btn-sm btn-label-primary">View All</a>
          </div>
          <div class="card-body p-0">
            <ul class="list-unstyled mb-0 px-3 py-2">
              <?php if (!empty($recent_transactions)): foreach ($recent_transactions as $t):
                  $type = strtoupper(h($t['transaction_type']));
                  $badgeColor = ($type === 'IN' || $type === 'RETURNED') ? 'success' : (($type === 'OUT' || $type === 'ASSIGNED') ? 'danger' : 'info');
              ?>
                  <li class="d-flex align-items-start py-3 border-bottom transaction-list-item">
                    <div class="me-3 mt-1">
                      <span class="avatar-icon rounded-circle bg-label-<?= $badgeColor ?>">
                        <i class="bx <?= $badgeColor === 'success' ? 'bx-down-arrow-circle' : 'bx-up-arrow-circle' ?> fs-5"></i>
                      </span>
                    </div>
                    <div class="flex-grow-1 overflow-hidden">
                      <div class="d-flex justify-content-between align-items-center mb-1">
                        <h6 class="mb-0 text-truncate pe-2" title="<?= h($t['item_name']) ?>"><?= h($t['item_name']) ?></h6>
                        <span class="badge bg-label-secondary fw-bold">x<?= intval($t['quantity']) ?></span>
                      </div>
                      <div class="d-flex justify-content-between align-items-center">
                        <small class="text-muted text-truncate pe-2">Holder: <?= h($t['current_holder'] ?? 'Warehouse') ?></small>
                        <small class="text-muted text-nowrap"><?= date('M d', strtotime($t['transaction_date'])) ?></small>
                      </div>
                      <div class="mt-1">
                        <span class="badge bg-<?= $badgeColor ?> bg-opacity-10 text-<?= $badgeColor ?> border border-<?= $badgeColor ?> border-opacity-25" style="font-size: 0.7rem; padding: 2px 6px;">
                          <?= $type ?>
                        </span>
                        <small class="text-muted ms-1" style="font-size: 0.75rem;">Ref: <?= h($t['reference_no'] ?? '-') ?></small>
                      </div>
                    </div>
                  </li>
                <?php endforeach;
              else: ?>
                <li class="py-5 text-center">
                  <div class="mb-3"><i class="bx bx-ghost fs-1 text-muted opacity-50"></i></div>
                  <h6 class="text-muted">No recent transactions</h6>
                </li>
              <?php endif; ?>
            </ul>
          </div>
        </div>
      </div>

      <div class="col-12">
        <div class="card shadow-sm border-0 bg-primary text-white">
          <div class="card-body d-flex flex-column flex-md-row justify-content-between align-items-center p-4">
            <div class="mb-3 mb-md-0">
              <h5 class="card-title text-white mb-1 fw-bold"><i class="bx bx-rocket me-2"></i>Quick Actions</h5>
              <p class="card-text mb-0 text-white-50 small">Manage your inventory, track accountability, or view comprehensive reports instantly.</p>
            </div>
            <div class="d-flex gap-2 flex-wrap justify-content-md-end">
              <a href="<?= site_url('table/table_index.php') ?>" class="btn btn-light text-primary fw-semibold"><i class="bx bx-plus me-1"></i>New Item</a>
              <a href="<?= site_url('table/accountables.php') ?>" class="btn btn-outline-light fw-semibold"><i class="bx bx-user-check me-1"></i>Accountability</a>
              <a href="<?= site_url('table/borrow_items.php') ?>" class="btn btn-outline-light fw-semibold"><i class="bx bx-transfer me-1"></i>Lend Item</a>
            </div>
          </div>
        </div>
      </div>

    </div>
  </div>
</div>
<script>
  // Function to handle the Range filter buttons
  function updateRange(days) {
    const url = new URL(window.location.href);
    url.searchParams.set('summary_range', days);
    window.location.href = url.href;
  }

  // Chart.js Implementations
  document.addEventListener('DOMContentLoaded', function() {
    const byType = <?= json_encode($by_type ?: []) ?>;
    const topItems = <?= json_encode($top_items ?: []) ?>;

    // By Type - Doughnut Chart
    const labelsType = byType.map(r => r.transaction_type || 'UNKNOWN');
    const dataType = byType.map(r => parseInt(r.cnt || 0, 10));
    const ctxType = document.getElementById('chartByType');

    if (ctxType && typeof Chart !== 'undefined' && byType.length > 0) {
      new Chart(ctxType, {
        type: 'doughnut',
        data: {
          labels: labelsType,
          datasets: [{
            data: dataType,
            backgroundColor: ['#696cff', '#71dd37', '#ffab00', '#ff3e1d', '#03c3ec', '#8592a3'],
            borderWidth: 0,
            hoverOffset: 4
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          cutout: '70%',
          plugins: {
            legend: {
              position: 'right',
              labels: {
                usePointStyle: true,
                boxWidth: 8
              }
            }
          }
        }
      });
    } else if (ctxType) {
      ctxType.parentElement.innerHTML = '<div class="d-flex h-100 align-items-center justify-content-center text-muted small">No data available for this range.</div>';
    }

    // Top Items - Bar Chart
    const labelsTop = topItems.map(r => r.item_name.length > 15 ? r.item_name.substring(0, 15) + '...' : r.item_name);
    const dataTop = topItems.map(r => parseInt(r.total_qty || 0, 10));
    const fullLabelsTop = topItems.map(r => r.item_name); // for tooltip
    const ctxTop = document.getElementById('chartTopItems');

    if (ctxTop && typeof Chart !== 'undefined' && topItems.length > 0) {
      new Chart(ctxTop, {
        type: 'bar',
        data: {
          labels: labelsTop,
          datasets: [{
            label: 'Volume (Qty)',
            data: dataTop,
            backgroundColor: 'rgba(105, 108, 255, 0.85)',
            hoverBackgroundColor: '#696cff',
            borderRadius: 4,
            barPercentage: 0.6
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          indexAxis: 'y',
          plugins: {
            legend: {
              display: false
            },
            tooltip: {
              callbacks: {
                title: function(context) {
                  return fullLabelsTop[context[0].dataIndex];
                }
              }
            }
          },
          scales: {
            x: {
              grid: {
                display: true,
                drawBorder: false
              },
              ticks: {
                precision: 0
              }
            },
            y: {
              grid: {
                display: false,
                drawBorder: false
              }
            }
          }
        }
      });
    } else if (ctxTop) {
      ctxTop.parentElement.innerHTML = '<div class="d-flex h-100 align-items-center justify-content-center text-muted small">No data available for this range.</div>';
    }
  });
</script>