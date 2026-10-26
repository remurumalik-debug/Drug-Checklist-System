<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('pharmacist');

checkAndCreateNotifications();

$pageTitle = 'Pharmacist Dashboard';

$userId = getCurrentUserId();
$today = date('Y-m-d');

$totalAvailable = (int)db()->query("SELECT COUNT(*) as cnt FROM drugs WHERE status='available'")->fetch()['cnt'];

$stmt = db()->query("SELECT COUNT(*) as cnt FROM drugs WHERE quantity <= min_stock AND quantity > 0 AND status NOT IN ('expired','discontinued')");
$lowStock = (int)$stmt->fetch()['cnt'];

$stmt = db()->query("SELECT COUNT(*) as cnt FROM drugs WHERE (status='out_of_stock' OR quantity=0) AND status != 'discontinued'");
$outOfStock = (int)$stmt->fetch()['cnt'];

$expiredDrugs = (int)db()->query("SELECT COUNT(*) as cnt FROM drugs WHERE status='expired' OR expiry_date < CURDATE()")->fetch()['cnt'];

$stmt = db()->prepare("SELECT COUNT(*) as cnt FROM drug_checklists WHERE checked_date = ?");
$stmt->execute([$today]);
$todayChecklists = (int)$stmt->fetch()['cnt'];

$stmt = db()->prepare("SELECT COUNT(*) as cnt FROM drug_checklists WHERE checked_date = ? AND checked_by = ?");
$stmt->execute([$today, $userId]);
$myChecklists = (int)$stmt->fetch()['cnt'];

$lowStockDrugs = db()->query("
    SELECT d.*, dc.category_name
    FROM drugs d
    LEFT JOIN drug_categories dc ON d.category_id = dc.id
    WHERE d.quantity <= d.min_stock AND d.quantity > 0 AND d.status NOT IN ('expired','discontinued')
    ORDER BY d.quantity ASC
    LIMIT 10
")->fetchAll();

$stmt = db()->prepare("
    SELECT dc.*, d.drug_name, d.batch_number
    FROM drug_checklists dc
    JOIN drugs d ON dc.drug_id = d.id
    WHERE dc.checked_date = ? AND dc.checked_by = ?
    ORDER BY dc.checked_time DESC
    LIMIT 10
");
$stmt->execute([$today, $userId]);
$myTodayChecklists = $stmt->fetchAll();

$stmt = db()->prepare("
    SELECT st.*, d.drug_name, d.batch_number
    FROM stock_transactions st
    JOIN drugs d ON st.drug_id = d.id
    WHERE st.transaction_type = 'stock_in'
    ORDER BY st.transaction_date DESC
    LIMIT 10
");
$stmt->execute();
$recentStockIn = $stmt->fetchAll();

$stmt = db()->prepare("
    SELECT st.*, d.drug_name, d.batch_number
    FROM stock_transactions st
    JOIN drugs d ON st.drug_id = d.id
    WHERE st.transaction_type = 'stock_out'
    ORDER BY st.transaction_date DESC
    LIMIT 10
");
$stmt->execute();
$recentStockOut = $stmt->fetchAll();

include_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>Pharmacist Dashboard</h1>
        <p>Welcome back &mdash; <?= formatDate($today) ?> &middot; <?= $myChecklists ?> checklists done today</p>
    </div>
    <div>
        <a href="<?= BASE_URL ?>pharmacist/checklist.php" class="btn btn-primary">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>
            </svg>
            New Checklist
        </a>
    </div>
</div>

<div class="stats-grid">
    <a href="<?= BASE_URL ?>pharmacist/drugs.php?status=available" class="stat-card">
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
    <a href="<?= BASE_URL ?>pharmacist/drugs.php?status=low_stock" class="stat-card">
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
    <a href="<?= BASE_URL ?>pharmacist/drugs.php?status=out_of_stock" class="stat-card">
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
    <a href="<?= BASE_URL ?>pharmacist/drugs.php?status=expired" class="stat-card">
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
    <a href="<?= BASE_URL ?>pharmacist/checklist.php" class="stat-card">
        <div class="stat-icon blue">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>
            </svg>
        </div>
        <div class="stat-content">
            <span class="stat-value" data-stat="today_checklists"><?= $todayChecklists ?></span>
            <span class="stat-label">Today's Checklists</span>
        </div>
    </a>
</div>

<div class="grid-2">
    <div class="chart-card">
        <div class="chart-header"><h3>Drug Status Distribution</h3></div>
        <div class="chart-container" style="max-height:280px;display:flex;justify-content:center;"><canvas id="chartStatus"></canvas></div>
    </div>
    <div class="chart-card">
        <div class="chart-header"><h3>Weekly Stock Activity</h3></div>
        <div class="chart-container" style="max-height:280px;"><canvas id="chartWeekly"></canvas></div>
    </div>
</div>
<div class="grid-2">
    <div class="chart-card">
        <div class="chart-header"><h3>Checklist Trend (7 days)</h3></div>
        <div class="chart-container" style="max-height:280px;"><canvas id="chartTrend"></canvas></div>
    </div>
    <div class="card">
        <div class="card-header">
            <h3>Low Stock Alerts</h3>
            <a href="<?= BASE_URL ?>pharmacist/stock_in.php" class="btn btn-sm btn-primary">Stock In</a>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Drug</th>
                            <th>Batch</th>
                            <th>Qty</th>
                            <th>Min</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody id="tbl-pharm-lowstock">
                        <?php if (empty($lowStockDrugs)): ?>
                        <tr><td colspan="5" class="text-center text-muted">No low stock items</td></tr>
                        <?php else: ?>
                        <?php foreach ($lowStockDrugs as $d): ?>
                        <tr>
                            <td><?= sanitize($d['drug_name']) ?></td>
                            <td><?= sanitize($d['batch_number']) ?></td>
                            <td class="text-danger fw-bold"><?= (int)$d['quantity'] ?></td>
                            <td><?= (int)$d['min_stock'] ?></td>
                            <td><?= getStatusBadge($d['status']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    </div>

<div class="grid-2">
    <div class="card">
        <div class="card-header">
            <h3>Recent Stock In</h3>
            <a href="<?= BASE_URL ?>pharmacist/stock_in.php" class="btn btn-sm btn-primary">New Stock In</a>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Drug</th>
                            <th>Qty</th>
                            <th>Ref #</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody id="tbl-pharm-stockin">
                        <?php if (empty($recentStockIn)): ?>
                        <tr><td colspan="4" class="text-center text-muted">No stock in records</td></tr>
                        <?php else: ?>
                        <?php foreach ($recentStockIn as $tx): ?>
                        <tr>
                            <td><?= sanitize($tx['drug_name']) ?> <small class="text-muted">(<?= sanitize($tx['batch_number']) ?>)</small></td>
                            <td class="text-success fw-bold">+<?= (int)$tx['quantity'] ?></td>
                            <td><small><?= sanitize($tx['reference_number'] ?? '-') ?></small></td>
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
            <h3>Recent Stock Out</h3>
            <a href="<?= BASE_URL ?>pharmacist/stock_out.php" class="btn btn-sm btn-primary">New Stock Out</a>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Drug</th>
                            <th>Qty</th>
                            <th>Ref #</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody id="tbl-pharm-stockout">
                        <?php if (empty($recentStockOut)): ?>
                        <tr><td colspan="4" class="text-center text-muted">No stock out records</td></tr>
                        <?php else: ?>
                        <?php foreach ($recentStockOut as $tx): ?>
                        <tr>
                            <td><?= sanitize($tx['drug_name']) ?> <small class="text-muted">(<?= sanitize($tx['batch_number']) ?>)</small></td>
                            <td class="text-danger fw-bold">-<?= (int)$tx['quantity'] ?></td>
                            <td><small><?= sanitize($tx['reference_number'] ?? '-') ?></small></td>
                            <td><?= formatDateTime($tx['transaction_date']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3>Today's Checklist</h3>
        <a href="<?= BASE_URL ?>pharmacist/checklist.php" class="btn btn-sm btn-primary">New Checklist</a>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Drug</th>
                        <th>Batch</th>
                        <th>Status</th>
                        <th>Time</th>
                        <th>Remarks</th>
                    </tr>
                </thead>
                <tbody id="tbl-pharm-checklists">
                    <?php if (empty($myTodayChecklists)): ?>
                    <tr><td colspan="5" class="text-center text-muted">You haven't done any checklists today. <a href="<?= BASE_URL ?>pharmacist/checklist.php">Start now</a></td></tr>
                    <?php else: ?>
                    <?php foreach ($myTodayChecklists as $cl): ?>
                    <tr>
                        <td><?= sanitize($cl['drug_name']) ?></td>
                        <td><?= sanitize($cl['batch_number']) ?></td>
                        <td><?= getStatusBadge($cl['checklist_status']) ?></td>
                        <td><?= sanitize($cl['checked_time']) ?></td>
                        <td><?= sanitize($cl['remarks'] ?? '-') ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include_once __DIR__ . '/../includes/footer.php'; ?>
