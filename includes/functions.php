<?php
require_once __DIR__ . '/../config/config.php';

function db() {
    return Database::getInstance()->getConnection();
}

function sanitize($input) {
    return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
}

function sanitizeArray($data) {
    if (is_array($data)) {
        return array_map('sanitizeArray', $data);
    }
    return sanitize($data);
}

function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

function validateDate($date, $format = 'Y-m-d') {
    $d = DateTime::createFromFormat($format, $date);
    return $d && $d->format($format) === $date;
}

function redirect($url) {
    header("Location: " . BASE_URL . ltrim($url, '/'));
    exit;
}

function jsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function formatDate($date, $format = 'Y-m-d') {
    if (!$date) return '';
    return date($format, strtotime($date));
}

function formatDateTime($datetime, $format = 'Y-m-d h:i A') {
    if (!$datetime) return '';
    return date($format, strtotime($datetime));
}

function formatCurrency($amount) {
    $currency = getSetting('currency', '#');
    return $currency . number_format($amount, 2);
}

function timeAgo($datetime) {
    $timestamp = strtotime($datetime);
    $diff = time() - $timestamp;
    if ($diff < 60) return 'just now';
    if ($diff < 3600) return floor($diff / 60) . ' min ago';
    if ($diff < 86400) return floor($diff / 3600) . ' hours ago';
    if ($diff < 604800) return floor($diff / 86400) . ' days ago';
    return date('M d, Y', $timestamp);
}

function getStatusBadge($status) {
    $map = [
        'available' => 'success',
        'low_stock' => 'warning',
        'out_of_stock' => 'danger',
        'expired' => 'secondary',
        'damaged' => 'danger',
        'discontinued' => 'dark',
        'active' => 'success',
        'inactive' => 'secondary',
        'suspended' => 'danger',
        'stock_in' => 'info',
        'stock_out' => 'warning',
        'adjustment' => 'primary',
    ];
    $class = $map[$status] ?? 'primary';
    $label = ucwords(str_replace('_', ' ', $status));
    return "<span class=\"badge badge-{$class}\">{$label}</span>";
}

function getSetting($key, $default = '') {
    try {
        $stmt = db()->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $row = $stmt->fetch();
        return $row ? $row['setting_value'] : $default;
    } catch (PDOException $e) {
        return $default;
    }
}

function getRoleName($roleId) {
    try {
        $stmt = db()->prepare("SELECT role_name FROM roles WHERE id = ?");
        $stmt->execute([$roleId]);
        $row = $stmt->fetch();
        return $row ? $row['role_name'] : 'Unknown';
    } catch (PDOException $e) {
        return 'Unknown';
    }
}

function getUserName($userId) {
    try {
        $stmt = db()->prepare("SELECT first_name, last_name FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $row = $stmt->fetch();
        return $row ? $row['first_name'] . ' ' . $row['last_name'] : 'Unknown';
    } catch (PDOException $e) {
        return 'Unknown';
    }
}

function logAudit($userId, $action, $entityType, $entityId, $description) {
    try {
        $stmt = db()->prepare("
            INSERT INTO audit_logs (user_id, action, entity_type, entity_id, description, ip_address, user_agent)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $userId,
            $action,
            $entityType,
            $entityId,
            $description,
            $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
            $_SERVER['HTTP_USER_AGENT'] ?? ''
        ]);
    } catch (PDOException $e) {
        error_log("Audit log failed: " . $e->getMessage());
    }
}

function createNotification($userId, $type, $title, $message, $drugId = null) {
    try {
        $stmt = db()->prepare("
            INSERT INTO notifications (user_id, notification_type, title, message, related_drug_id)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$userId, $type, $title, $message, $drugId]);
    } catch (PDOException $e) {
        error_log("Notification creation failed: " . $e->getMessage());
    }
}

function notifyAllAdminsAndPharmacists($type, $title, $message, $drugId = null) {
    try {
        $stmt = db()->prepare("
            SELECT id FROM users WHERE role_id IN (1, 2) AND status = 'active'
        ");
        $stmt->execute();
        $users = $stmt->fetchAll();
        foreach ($users as $user) {
            createNotification($user['id'], $type, $title, $message, $drugId);
        }
    } catch (PDOException $e) {
        error_log("Bulk notification failed: " . $e->getMessage());
    }
}

function generateReferenceNumber($prefix = 'TXN') {
    return $prefix . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
}

function getPaginatedData($table, $where = '', $params = [], $orderBy = 'id DESC', $perPage = 15) {
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $offset = ($page - 1) * $perPage;

    $whereClause = $where ? "WHERE {$where}" : '';
    $countStmt = db()->prepare("SELECT COUNT(*) as total FROM {$table} {$whereClause}");
    $countStmt->execute($params);
    $total = $countStmt->fetch()['total'];
    $totalPages = ceil($total / $perPage);

    $stmt = db()->prepare("SELECT * FROM {$table} {$whereClause} ORDER BY {$orderBy} LIMIT ? OFFSET ?");
    $stmt->execute(array_merge($params, [$perPage, $offset]));
    $data = $stmt->fetchAll();

    return [
        'data' => $data,
        'current_page' => $page,
        'per_page' => $perPage,
        'total' => $total,
        'total_pages' => $totalPages,
        'offset' => $offset,
    ];
}

function renderPagination($pagination) {
    if ($pagination['total_pages'] <= 1) return '';

    $html = '<nav class="pagination-container"><ul class="pagination">';

    $params = $_GET;
    unset($params['page']);

    $queryString = http_build_query($params);
    $queryString = $queryString ? '&' . $queryString : '';

    if ($pagination['current_page'] > 1) {
        $html .= '<li class="page-item"><a class="page-link" href="?page=' . ($pagination['current_page'] - 1) . $queryString . '">Previous</a></li>';
    }

    for ($i = 1; $i <= $pagination['total_pages']; $i++) {
        $active = $i === $pagination['current_page'] ? 'active' : '';
        $html .= '<li class="page-item ' . $active . '"><a class="page-link" href="?page=' . $i . $queryString . '">' . $i . '</a></li>';
    }

    if ($pagination['current_page'] < $pagination['total_pages']) {
        $html .= '<li class="page-item"><a class="page-link" href="?page=' . ($pagination['current_page'] + 1) . $queryString . '">Next</a></li>';
    }

    $html .= '</ul></nav>';
    return $html;
}

function checkAndCreateNotifications() {
    $alertDays = (int)getSetting('expiry_alert_days', 30);
    $lowStockThreshold = (int)getSetting('low_stock_threshold', 10);
    $today = date('Y-m-d');
    $alertDate = date('Y-m-d', strtotime("+{$alertDays} days"));

    try {
        // Expiring soon
        $stmt = db()->prepare("
            SELECT d.*, dc.category_name FROM drugs d
            LEFT JOIN drug_categories dc ON d.category_id = dc.id
            WHERE d.expiry_date BETWEEN ? AND ? AND d.status = 'available'
        ");
        $stmt->execute([$today, $alertDate]);
        $expiring = $stmt->fetchAll();
        foreach ($expiring as $drug) {
            notifyAllAdminsAndPharmacists(
                'expiry_alert',
                'Drug Expiring Soon',
                "{$drug['drug_name']} (Batch: {$drug['batch_number']}) expires on " . formatDate($drug['expiry_date']),
                $drug['id']
            );
        }

        // Expired
        $stmt = db()->prepare("
            SELECT * FROM drugs WHERE expiry_date < ? AND status != 'expired'
        ");
        $stmt->execute([$today]);
        $expired = $stmt->fetchAll();
        foreach ($expired as $drug) {
            db()->prepare("UPDATE drugs SET status = 'expired' WHERE id = ?")->execute([$drug['id']]);
            notifyAllAdminsAndPharmacists(
                'expired',
                'Drug Expired',
                "{$drug['drug_name']} (Batch: {$drug['batch_number']}) has expired.",
                $drug['id']
            );
        }

        // Low stock
        $stmt = db()->prepare("
            SELECT * FROM drugs WHERE quantity <= min_stock AND quantity > 0 AND status NOT IN ('expired', 'discontinued')
        ");
        $stmt->execute();
        $lowStock = $stmt->fetchAll();
        foreach ($lowStock as $drug) {
            $newStatus = $drug['quantity'] <= 0 ? 'out_of_stock' : 'low_stock';
            db()->prepare("UPDATE drugs SET status = ? WHERE id = ?")->execute([$newStatus, $drug['id']]);
            $type = $drug['quantity'] <= 0 ? 'out_of_stock' : 'low_stock';
            $msg = "{$drug['drug_name']} (Batch: {$drug['batch_number']}) has only {$drug['quantity']} units left.";
            notifyAllAdminsAndPharmacists($type, ucwords(str_replace('_', ' ', $type)) . ' Alert', $msg, $drug['id']);
        }
    } catch (PDOException $e) {
        error_log("Notification check failed: " . $e->getMessage());
    }
}
