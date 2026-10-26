<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('admin');

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
    <div>
        <h1>Notifications</h1>
        <p>Manage your system notifications and alerts</p>
    </div>
    <div>
        <span class="badge badge-info"><?= $unreadTotal ?> Unread</span>
    </div>
</div>

<div class="card">
    <div class="filters-bar">
        <form method="GET" action="" style="display:flex;gap:12px;flex-wrap:wrap;width:100%;">
            <select name="type" class="form-control">
                <option value="">All Types</option>
                <?php foreach ($notificationTypes as $t): ?>
                <option value="<?= sanitize($t['notification_type']) ?>" <?= ($_GET['type'] ?? '') === $t['notification_type'] ? 'selected' : '' ?>><?= ucwords(str_replace('_', ' ', $t['notification_type'])) ?></option>
                <?php endforeach; ?>
            </select>
            <select name="status" class="form-control">
                <option value="">All Status</option>
                <option value="unread" <?= ($_GET['status'] ?? '') === 'unread' ? 'selected' : '' ?>>Unread</option>
                <option value="read" <?= ($_GET['status'] ?? '') === 'read' ? 'selected' : '' ?>>Read</option>
            </select>
            <button type="submit" class="btn btn-primary btn-sm">Filter</button>
            <a href="notifications.php" class="btn btn-secondary btn-sm">Clear</a>
        </form>
    </div>
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>Type</th>
                    <th>Title</th>
                    <th>Message</th>
                    <th>Status</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($pagination['data'])): ?>
                <tr><td colspan="5"><div class="empty-state"><h3>No notifications found</h3></div></td></tr>
                <?php else: ?>
                <?php foreach ($pagination['data'] as $notif): ?>
                <tr style="<?= !$notif['is_read'] ? 'background:#F9FAFB;font-weight:500;' : '' ?>">
                    <td><?= getStatusBadge($notif['notification_type']) ?></td>
                    <td><?= sanitize($notif['title']) ?></td>
                    <td><?= sanitize($notif['message']) ?></td>
                    <td><?= $notif['is_read'] ? '<span class="badge badge-secondary">Read</span>' : '<span class="badge badge-info">New</span>' ?></td>
                    <td><?= formatDateTime($notif['created_at']) ?></td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if (!empty($pagination['data'])): ?>
    <div class="card-footer">
        <?= renderPagination($pagination) ?>
    </div>
    <?php endif; ?>
</div>

<?php include_once __DIR__ . '/../includes/footer.php'; ?>
