<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('admin');

$pageTitle = 'Audit Logs';
include_once __DIR__ . '/../includes/header.php';

// Filters
$userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
$actionFilter = $_GET['action'] ?? '';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';

$where = [];
$params = [];

if ($userId > 0) {
    $where[] = 'al.user_id = ?';
    $params[] = $userId;
}
if (!empty($actionFilter)) {
    $where[] = 'al.action = ?';
    $params[] = $actionFilter;
}
if (!empty($dateFrom)) {
    $where[] = 'DATE(al.created_at) >= ?';
    $params[] = $dateFrom;
}
if (!empty($dateTo)) {
    $where[] = 'DATE(al.created_at) <= ?';
    $params[] = $dateTo;
}

$whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// Pagination
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage = 20;
$offset = ($page - 1) * $perPage;

$countStmt = db()->prepare("SELECT COUNT(*) as total FROM audit_logs al {$whereClause}");
$countStmt->execute($params);
$total = $countStmt->fetch()['total'];
$totalPages = ceil($total / $perPage);

$stmt = db()->prepare("
    SELECT al.*, u.username, u.first_name, u.last_name
    FROM audit_logs al
    LEFT JOIN users u ON al.user_id = u.id
    {$whereClause}
    ORDER BY al.created_at DESC
    LIMIT ? OFFSET ?
");
$stmt->execute(array_merge($params, [$perPage, $offset]));
$logs = $stmt->fetchAll();

$paginationData = [
    'data' => $logs,
    'current_page' => $page,
    'per_page' => $perPage,
    'total' => $total,
    'total_pages' => $totalPages,
    'offset' => $offset,
];

// Filter dropdowns
$users = db()->query("SELECT id, username, first_name, last_name FROM users WHERE status = 'active' ORDER BY username")->fetchAll();
$actions = db()->query("SELECT DISTINCT action FROM audit_logs ORDER BY action")->fetchAll();
?>

<div class="page-header">
    <h1 class="page-title">Audit Logs</h1>
</div>

<div class="card">
    <div class="card-header">
        <form method="GET" action="" class="filter-form">
            <div class="form-row">
                <div class="form-group col-md-3">
                    <select name="user_id" class="form-control">
                        <option value="">All Users</option>
                        <?php foreach ($users as $u): ?>
                        <option value="<?= $u['id'] ?>" <?= $userId === (int)$u['id'] ? 'selected' : '' ?>><?= sanitize($u['username']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group col-md-2">
                    <select name="action" class="form-control">
                        <option value="">All Actions</option>
                        <?php foreach ($actions as $a): ?>
                        <option value="<?= sanitize($a['action']) ?>" <?= $actionFilter === $a['action'] ? 'selected' : '' ?>><?= sanitize(ucwords(str_replace('_', ' ', $a['action']))) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group col-md-2">
                    <input type="date" name="date_from" class="form-control" value="<?= sanitize($dateFrom) ?>" placeholder="From">
                </div>
                <div class="form-group col-md-2">
                    <input type="date" name="date_to" class="form-control" value="<?= sanitize($dateTo) ?>" placeholder="To">
                </div>
                <div class="form-group col-md-1">
                    <button type="submit" class="btn btn-primary btn-block">Filter</button>
                </div>
                <div class="form-group col-md-2">
                    <a href="?" class="btn btn-secondary btn-block">Reset</a>
                </div>
            </div>
        </form>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Action</th>
                        <th>Entity Type</th>
                        <th>Entity ID</th>
                        <th>Description</th>
                        <th>IP Address</th>
                        <th>Date/Time</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($logs)): ?>
                    <tr><td colspan="7" class="text-center text-muted">No audit logs found</td></tr>
                    <?php else: ?>
                    <?php foreach ($logs as $log): ?>
                    <tr>
                        <td><?= sanitize($log['first_name'] . ' ' . $log['last_name']) ?></td>
                        <td><?= sanitize(ucwords(str_replace('_', ' ', $log['action']))) ?></td>
                        <td><?= sanitize(ucwords(str_replace('_', ' ', $log['entity_type'] ?? '-'))) ?></td>
                        <td><?= $log['entity_id'] ? (int)$log['entity_id'] : '-' ?></td>
                        <td><?= sanitize($log['description'] ?? '-') ?></td>
                        <td><code><?= sanitize($log['ip_address'] ?? '-') ?></code></td>
                        <td><?= formatDateTime($log['created_at']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php if ($totalPages > 1): ?>
    <div class="card-footer">
        <?= renderPagination($paginationData) ?>
    </div>
    <?php endif; ?>
</div>

<?php include_once __DIR__ . '/../includes/footer.php'; ?>
