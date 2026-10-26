<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('staff');

$pageTitle = 'Staff Dashboard';

$userId = getCurrentUserId();

$totalAvailable = (int)db()->query("SELECT COUNT(*) as cnt FROM drugs WHERE status='available'")->fetch()['cnt'];

$stmt = db()->query("SELECT COUNT(*) as cnt FROM drugs WHERE quantity <= min_stock AND quantity > 0 AND status NOT IN ('expired','discontinued')");
$lowStock = (int)$stmt->fetch()['cnt'];

$stmt = db()->query("SELECT COUNT(*) as cnt FROM drugs WHERE (status='out_of_stock' OR quantity=0) AND status != 'discontinued'");
$outOfStock = (int)$stmt->fetch()['cnt'];

$expiredDrugs = (int)db()->query("SELECT COUNT(*) as cnt FROM drugs WHERE status='expired' OR expiry_date < CURDATE()")->fetch()['cnt'];

$recentDrugs = db()->query("
    SELECT d.*, dc.category_name
    FROM drugs d
    LEFT JOIN drug_categories dc ON d.category_id = dc.id
    ORDER BY d.created_at DESC
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
        <h1>Staff Dashboard</h1>
        <p>Inventory overview &mdash; <?= formatDate(date('Y-m-d')) ?></p>
    </div>
</div>

<div class="stats-grid">
    <a href="<?= BASE_URL ?>staff/inventory.php?status=available" class="stat-card">
        <div class="stat-icon green">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>
            </svg>
        </div>
        <div class="stat-content">
            <span class="stat-value" data-stat="available"><?= $totalAvailable ?></span>
            <span class="stat-label">Available Drugs</span>
        </div>
    </a>
    <a href="<?= BASE_URL ?>staff/inventory.php?status=low_stock" class="stat-card">
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
    <a href="<?= BASE_URL ?>staff/inventory.php?status=out_of_stock" class="stat-card">
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
    <a href="<?= BASE_URL ?>staff/inventory.php?status=expired" class="stat-card">
        <div class="stat-icon red">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M12 8v4l3 3m6-3a9 9 0 1 1-18 0 9 9 0 0 1 18 0z"/>
            </svg>
        </div>
        <div class="stat-content">
            <span class="stat-value" data-stat="expired"><?= $expiredDrugs ?></span>
            <span class="stat-label">Expired Drugs</span>
        </div>
    </a>
</div>

<div class="grid-2">
    <div class="chart-card">
        <div class="chart-header"><h3>Drug Status Distribution</h3></div>
        <div class="chart-container" style="max-height:280px;display:flex;justify-content:center;"><canvas id="chartStatus"></canvas></div>
    </div>
    <div class="chart-card">
        <div class="chart-header"><h3>Top Drugs by Quantity</h3></div>
        <div class="chart-container" style="max-height:280px;"><canvas id="chartDrugs"></canvas></div>
    </div>
</div>
<div class="grid-2">
    <div class="card">
        <div class="card-header">
            <h3>Drug Inventory Overview</h3>
            <a href="<?= BASE_URL ?>staff/inventory.php" class="btn btn-sm btn-outline-secondary">View Full Inventory</a>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Drug Name</th>
                            <th>Category</th>
                            <th>Qty</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody id="tbl-staff-drugs">
                        <?php if (empty($recentDrugs)): ?>
                        <tr><td colspan="4" class="text-center text-muted">No drugs in inventory</td></tr>
                        <?php else: ?>
                        <?php foreach ($recentDrugs as $d): ?>
                        <tr>
                            <td><?= sanitize($d['drug_name']) ?></td>
                            <td><?= sanitize($d['category_name'] ?? '-') ?></td>
                            <td><?= (int)$d['quantity'] ?></td>
                            <td><?= getStatusBadge($d['status']) ?></td>
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
            <h3>Recent Checklist Status</h3>
            <a href="<?= BASE_URL ?>staff/reports.php?type=daily_checklist" class="btn btn-sm btn-outline-secondary">View Report</a>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Drug</th>
                            <th>Status</th>
                            <th>Checked By</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody id="tbl-staff-checklists">
                        <?php if (empty($recentChecklists)): ?>
                        <tr><td colspan="4" class="text-center text-muted">No checklists recorded yet</td></tr>
                        <?php else: ?>
                        <?php foreach ($recentChecklists as $cl): ?>
                        <tr>
                            <td><?= sanitize($cl['drug_name']) ?> <small class="text-muted">(<?= sanitize($cl['batch_number']) ?>)</small></td>
                            <td><?= getStatusBadge($cl['checklist_status']) ?></td>
                            <td><?= sanitize(($cl['first_name'] ?? '') . ' ' . ($cl['last_name'] ?? '')) ?></td>
                            <td><?= formatDate($cl['checked_date']) ?></td>
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
