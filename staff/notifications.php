<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('staff');

$pageTitle = 'Notifications';

$userId = getCurrentUserId();

try {
    $stmt = db()->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$userId]);
} catch (PDOException $e) {
    error_log("Mark notifications read error: " . $e->getMessage());
}

$where = "user_id = ?";
$params = [$userId];

if (!empty($_GET['type'])) {
    $where .= " AND notification_type = ?";
    $params[] = $_GET['type'];
}

if (isset($_GET['status']) && $_GET['status'] !== '') {
    $isRead = $_GET['status'] === 'read' ? 1 : 0;
    $where .= " AND is_read = ?";
    $params[] = $isRead;
}

$pagination = getPaginatedData("notifications", $where, $params, "created_at DESC", 20);

try {
    $unreadCount = db()->prepare("SELECT COUNT(*) as cnt FROM notifications WHERE user_id = ? AND is_read = 0");
    $unreadCount->execute([$userId]);
    $unreadTotal = $unreadCount->fetch()['cnt'];

    $types = db()->prepare("SELECT DISTINCT notification_type FROM notifications WHERE user_id = ? ORDER BY notification_type");
    $types->execute([$userId]);
    $notificationTypes = $types->fetchAll();
} catch (PDOException $e) {
    error_log("Notification load error: " . $e->getMessage());
}

include_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div class="page-header-left">
        <h1 class="page-title">Notifications</h1>
        <p class="page-subtitle">
            <?php if ($unreadTotal > 0): ?>
            You have <strong><?= $unreadTotal ?></strong> unread notification<?= $unreadTotal !== 1 ? 's' : '' ?>
            <?php else: ?>
            All notifications read
            <?php endif; ?>
        </p>
    </div>
    <div class="page-header-right">
        <a href="<?= BASE_URL ?>staff/index.php" class="btn btn-outline-secondary">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/>
            </svg>
            Dashboard
        </a>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <form method="GET" class="filter-form">
            <div class="filter-row">
                <div class="filter-group">
                    <label class="form-label" for="type">Type</label>
                    <select id="type" name="type" class="form-control">
                        <option value="">All Types</option>
                        <?php foreach ($notificationTypes ?? [] as $nt): ?>
                        <option value="<?= sanitize($nt['notification_type']) ?>" <?= (isset($_GET['type']) && $_GET['type'] === $nt['notification_type']) ? 'selected' : '' ?>><?= ucwords(str_replace('_', ' ', $nt['notification_type'])) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label class="form-label" for="status">Status</label>
                    <select id="status" name="status" class="form-control">
                        <option value="">All</option>
                        <option value="unread" <?= (isset($_GET['status']) && $_GET['status'] === 'unread') ? 'selected' : '' ?>>Unread</option>
                        <option value="read" <?= (isset($_GET['status']) && $_GET['status'] === 'read') ? 'selected' : '' ?>>Read</option>
                    </select>
                </div>
                <div class="filter-group filter-group-btn">
                    <label class="form-label">&nbsp;</label>
                    <button type="submit" class="btn btn-primary btn-block">Filter</button>
                </div>
                <div class="filter-group filter-group-btn">
                    <label class="form-label">&nbsp;</label>
                    <a href="<?= BASE_URL ?>staff/notifications.php" class="btn btn-outline-secondary btn-block">Reset</a>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="card mt-3">
    <div class="card-header">
        <h3>All Notifications</h3>
        <span class="card-badge"><?= $pagination['total'] ?> Total</span>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Type</th>
                        <th>Title</th>
                        <th>Message</th>
                        <th>Date</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($pagination['data'])): ?>
                    <tr><td colspan="5" class="text-center">No notifications found.</td></tr>
                    <?php else: ?>
                    <?php foreach ($pagination['data'] as $notif): ?>
                    <tr class="<?= $notif['is_read'] ? '' : 'table-row-unread' ?>">
                        <td>
                            <?php
                            $typeBadgeMap = [
                                'expiry_alert' => 'warning',
                                'expired' => 'secondary',
                                'low_stock' => 'warning',
                                'out_of_stock' => 'danger',
                                'system' => 'info',
                                'info' => 'primary',
                            ];
                            $badgeClass = $typeBadgeMap[$notif['notification_type']] ?? 'primary';
                            ?>
                            <span class="badge badge-<?= $badgeClass ?>"><?= ucwords(str_replace('_', ' ', $notif['notification_type'])) ?></span>
                        </td>
                        <td><strong><?= sanitize($notif['title']) ?></strong></td>
                        <td><?= sanitize($notif['message']) ?></td>
                        <td><?= formatDateTime($notif['created_at']) ?></td>
                        <td>
                            <?php if ($notif['is_read']): ?>
                            <span class="badge badge-secondary">Read</span>
                            <?php else: ?>
                            <span class="badge badge-primary">Unread</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?= renderPagination($pagination) ?>
    </div>
</div>

<?php include_once __DIR__ . '/../includes/footer.php'; ?>
