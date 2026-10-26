<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('admin');

checkAndCreateNotifications();

$pageTitle = 'Admin Dashboard';
$userId = getCurrentUserId();

$statusCounts = [];
$stmt = db()->query("SELECT status, COUNT(*) as cnt FROM drugs GROUP BY status");
while ($row = $stmt->fetch()) {
    $statusCounts[$row['status']] = (int)$row['cnt'];
}
$totalDrugs = array_sum($statusCounts);
$available   = (int)($statusCounts['available'] ?? 0);
$expired     = (int)($statusCounts['expired'] ?? 0);
$discontinued = (int)($statusCounts['discontinued'] ?? 0);
$activeDrugs = $totalDrugs - $expired - $discontinued;

$lowStock = (int)db()->query("SELECT COUNT(*) as cnt FROM drugs WHERE quantity <= min_stock AND quantity > 0 AND status NOT IN ('expired','discontinued')")->fetch()['cnt'];
$outOfStock = (int)db()->query("SELECT COUNT(*) as cnt FROM drugs WHERE (status='out_of_stock' OR quantity=0) AND status != 'discontinued'")->fetch()['cnt'];

$totalCategories = (int)db()->query("SELECT COUNT(*) as cnt FROM drug_categories")->fetch()['cnt'];

$recentTransactions = db()->query("
    SELECT st.*, d.drug_name, d.batch_number, u.first_name, u.last_name
    FROM stock_transactions st
    JOIN drugs d ON st.drug_id = d.id
    LEFT JOIN users u ON st.performed_by = u.id
    ORDER BY st.transaction_date DESC
    LIMIT 10
")->fetchAll();

$recentChecklists = db()->query("
    SELECT dc.*, d.drug_name, d.batch_number, u.first_name, u.last_name
    FROM drug_checklists dc
    JOIN drugs d ON dc.drug_id = d.id
    LEFT JOIN users u ON dc.checked_by = u.id
    ORDER BY dc.checked_date DESC, dc.checked_time DESC
    LIMIT 10
")->fetchAll();

include_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Admin Dashboard</h1>
        <p>Complete inventory analytics &mdash; <?= formatDate(date('Y-m-d')) ?></p>
    </div>
</div>

<div class="stats-grid">
    <a href="<?= BASE_URL ?>admin/drugs.php" class="stat-card">
        <div class="stat-icon blue">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><rect x="8" y="2" width="8" height="4" rx="1" ry="1"/>
            </svg>
        </div>
        <div class="stat-content">
            <span class="stat-value" data-stat="total_drugs"><?= $totalDrugs ?></span>
            <span class="stat-label">Total Drugs</span>
        </div>
    </a>
    <a href="<?= BASE_URL ?>admin/drugs.php?status=available" class="stat-card">
        <div class="stat-icon green">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>
            </svg>
        </div>
        <div class="stat-content">
            <span class="stat-value" data-stat="available"><?= $available ?></span>
            <span class="stat-label">Available</span>
        </div>
    </a>
    <a href="<?= BASE_URL ?>admin/drugs.php?status=low_stock" class="stat-card">
        <div class="stat-icon yellow">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>
            </svg>
        </div>
        <div class="stat-content">
            <span class="stat-value" data-stat="low_stock"><?= $lowStock ?></span>
            <span class="stat-label">Low Stock</span>
        </div>
    </a>
    <a href="<?= BASE_URL ?>admin/drugs.php?status=out_of_stock" class="stat-card">
        <div class="stat-icon red">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/>
            </svg>
        </div>
        <div class="stat-content">
            <span class="stat-value" data-stat="out_of_stock"><?= $outOfStock ?></span>
            <span class="stat-label">Out of Stock</span>
        </div>
    </a>
    <a href="<?= BASE_URL ?>admin/drugs.php?status=expired" class="stat-card">
        <div class="stat-icon red">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M12 8v4l3 3m6-3a9 9 0 1 1-18 0 9 9 0 0 1 18 0z"/>
            </svg>
        </div>
        <div class="stat-content">
            <span class="stat-value" data-stat="expired"><?= $expired ?></span>
            <span class="stat-label">Expired</span>
        </div>
    </a>
    <a href="<?= BASE_URL ?>admin/drugs.php?status=discontinued" class="stat-card">
        <div class="stat-icon purple">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
            </svg>
        </div>
        <div class="stat-content">
            <span class="stat-value" data-stat="discontinued"><?= $discontinued ?></span>
            <span class="stat-label">Discontinued</span>
        </div>
    </a>
    <a href="<?= BASE_URL ?>admin/drugs.php" class="stat-card">
        <div class="stat-icon indigo">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M12 8v4l3 3m6-3a9 9 0 1 1-18 0 9 9 0 0 1 18 0z"/>
            </svg>
        </div>
        <div class="stat-content">
            <span class="stat-value" data-stat="active_drugs"><?= $activeDrugs ?></span>
            <span class="stat-label">Active Drugs</span>
        </div>
    </a>
    <a href="<?= BASE_URL ?>admin/categories.php" class="stat-card">
        <div class="stat-icon green">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/>
            </svg>
        </div>
        <div class="stat-content">
            <span class="stat-value" data-stat="categories"><?= $totalCategories ?></span>
            <span class="stat-label">Categories</span>
        </div>
    </a>
    </div>

<div class="grid-2">
    <div class="chart-card">
        <div class="chart-header"><h3>Drug Status Distribution</h3></div>
        <div class="chart-container" style="max-height:280px;display:flex;justify-content:center;"><canvas id="chartStatus"></canvas></div>
    </div>
    <div class="chart-card">
        <div class="chart-header"><h3>Monthly Stock Transactions</h3></div>
        <div class="chart-container" style="max-height:280px;"><canvas id="chartMonthly"></canvas></div>
    </div>
</div>
<div class="grid-2">
    <div class="chart-card">
        <div class="chart-header"><h3>Drugs by Category</h3></div>
        <div class="chart-container" style="max-height:280px;"><canvas id="chartCategory"></canvas></div>
    </div>
    <div class="card">
        <div class="card-header">
            <h3>Recent Inventory Activities</h3>
            <a href="<?= BASE_URL ?>admin/reports.php?type=stock_movement" class="btn btn-sm btn-outline-secondary">View All</a>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Drug</th>
                            <th>Type</th>
                            <th>Qty</th>
                            <th>By</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody id="tbl-admin-transactions">
                        <?php if (empty($recentTransactions)): ?>
                        <tr><td colspan="5" class="text-center text-muted">No recent inventory activities</td></tr>
                        <?php else: ?>
                        <?php foreach ($recentTransactions as $tx): ?>
                        <tr>
                            <td><?= sanitize($tx['drug_name']) ?> <small class="text-muted">(<?= sanitize($tx['batch_number']) ?>)</small></td>
                            <td><?= getStatusBadge($tx['transaction_type']) ?></td>
                            <td><?= (int)$tx['quantity'] ?></td>
                            <td><?= sanitize(($tx['first_name'] ?? '') . ' ' . ($tx['last_name'] ?? '')) ?></td>
                            <td><?= formatDateTime($tx['transaction_date']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="card">
        <div class="card-header">
            <h3>Recent Drug Checklist Activities</h3>
            <a href="<?= BASE_URL ?>admin/reports.php?type=daily_checklist" class="btn btn-sm btn-outline-secondary">View All</a>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Drug</th>
                            <th>Status</th>
                            <th>By</th>
                            <th>Date</th>
                            <th>Time</th>
                        </tr>
                    </thead>
                    <tbody id="tbl-admin-checklists">
                        <?php if (empty($recentChecklists)): ?>
                        <tr><td colspan="5" class="text-center text-muted">No recent checklist activities</td></tr>
                        <?php else: ?>
                        <?php foreach ($recentChecklists as $cl): ?>
                        <tr>
                            <td><?= sanitize($cl['drug_name']) ?> <small class="text-muted">(<?= sanitize($cl['batch_number']) ?>)</small></td>
                            <td><?= getStatusBadge($cl['checklist_status']) ?></td>
                            <td><?= sanitize(($cl['first_name'] ?? '') . ' ' . ($cl['last_name'] ?? '')) ?></td>
                            <td><?= formatDate($cl['checked_date']) ?></td>
                            <td><?= sanitize($cl['checked_time'] ?? '-') ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include_once __DIR__ . '/../includes/footer.php'; ?>
