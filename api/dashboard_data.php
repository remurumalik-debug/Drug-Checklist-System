<?php
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

header('Content-Type: application/json');
$role = getCurrentUserRole();
$userId = getCurrentUserId();

try {
    $response = ['stats' => [], 'tables' => []];

    // Common stats
    $response['stats']['available'] = (int)db()->query("SELECT COUNT(*) as cnt FROM drugs WHERE status='available'")->fetch()['cnt'];
    $response['stats']['low_stock'] = (int)db()->query("SELECT COUNT(*) as cnt FROM drugs WHERE quantity <= min_stock AND quantity > 0 AND status NOT IN ('expired','discontinued')")->fetch()['cnt'];
    $response['stats']['out_of_stock'] = (int)db()->query("SELECT COUNT(*) as cnt FROM drugs WHERE (status='out_of_stock' OR quantity=0) AND status != 'discontinued'")->fetch()['cnt'];
    $response['stats']['expired'] = (int)db()->query("SELECT COUNT(*) as cnt FROM drugs WHERE status='expired' OR expiry_date < CURDATE()")->fetch()['cnt'];
    $nStmt = db()->prepare("SELECT COUNT(*) as cnt FROM notifications WHERE user_id = ? AND is_read = 0");
    $nStmt->execute([$userId]);
    $response['stats']['notifications'] = (int)$nStmt->fetch()['cnt'];

    if ($role === 'admin') {
        $sc = [];
        $rs = db()->query("SELECT status, COUNT(*) as cnt FROM drugs GROUP BY status");
        while ($r = $rs->fetch()) { $sc[$r['status']] = (int)$r['cnt']; }
        $total = array_sum($sc);
        $response['stats']['total_drugs'] = $total;
        $response['stats']['discontinued'] = (int)($sc['discontinued'] ?? 0);
        $response['stats']['active_drugs'] = $total - ($sc['expired'] ?? 0) - ($sc['discontinued'] ?? 0);
        $response['stats']['categories'] = (int)db()->query("SELECT COUNT(*) as cnt FROM drug_categories")->fetch()['cnt'];

        // Admin transactions table
        $txRows = db()->query("
            SELECT st.*, d.drug_name, d.batch_number, u.first_name, u.last_name
            FROM stock_transactions st JOIN drugs d ON st.drug_id = d.id
            LEFT JOIN users u ON st.performed_by = u.id
            ORDER BY st.transaction_date DESC LIMIT 10
        ")->fetchAll();
        $html = '';
        if (empty($txRows)) {
            $html = '<tr><td colspan="5" class="text-center text-muted">No recent inventory activities</td></tr>';
        } else {
            foreach ($txRows as $tx) {
                $name = sanitize($tx['drug_name']) . ' <small class="text-muted">(' . sanitize($tx['batch_number']) . ')</small>';
                $badge = getStatusBadge($tx['transaction_type']);
                $qty = (int)$tx['quantity'];
                $by = sanitize(trim(($tx['first_name'] ?? '') . ' ' . ($tx['last_name'] ?? '')));
                $date = formatDateTime($tx['transaction_date']);
                $html .= "<tr><td>{$name}</td><td>{$badge}</td><td>{$qty}</td><td>{$by}</td><td>{$date}</td></tr>";
            }
        }
        $response['tables']['transactions'] = $html;

        // Admin checklists table
        $clRows = db()->query("
            SELECT dc.*, d.drug_name, d.batch_number, u.first_name, u.last_name
            FROM drug_checklists dc JOIN drugs d ON dc.drug_id = d.id
            LEFT JOIN users u ON dc.checked_by = u.id
            ORDER BY dc.checked_date DESC, dc.checked_time DESC LIMIT 10
        ")->fetchAll();
        $html = '';
        if (empty($clRows)) {
            $html = '<tr><td colspan="5" class="text-center text-muted">No recent checklist activities</td></tr>';
        } else {
            foreach ($clRows as $cl) {
                $name = sanitize($cl['drug_name']) . ' <small class="text-muted">(' . sanitize($cl['batch_number']) . ')</small>';
                $badge = getStatusBadge($cl['checklist_status']);
                $by = sanitize(trim(($cl['first_name'] ?? '') . ' ' . ($cl['last_name'] ?? '')));
                $date = formatDate($cl['checked_date']);
                $time = sanitize($cl['checked_time'] ?? '-');
                $html .= "<tr><td>{$name}</td><td>{$badge}</td><td>{$by}</td><td>{$date}</td><td>{$time}</td></tr>";
            }
        }
        $response['tables']['checklists'] = $html;

    } elseif ($role === 'pharmacist') {
        $today = date('Y-m-d');
        $stmt = db()->prepare("SELECT COUNT(*) as cnt FROM drug_checklists WHERE checked_date = ?");
        $stmt->execute([$today]);
        $response['stats']['today_checklists'] = (int)$stmt->fetch()['cnt'];
        $stmt2 = db()->prepare("SELECT COUNT(*) as cnt FROM drug_checklists WHERE checked_date = ? AND checked_by = ?");
        $stmt2->execute([$today, $userId]);
        $response['stats']['my_checklists'] = (int)$stmt2->fetch()['cnt'];

        // Low stock alerts table
        $lsRows = db()->query("
            SELECT d.*, dc.category_name FROM drugs d
            LEFT JOIN drug_categories dc ON d.category_id = dc.id
            WHERE d.quantity <= d.min_stock AND d.quantity > 0 AND d.status NOT IN ('expired','discontinued')
            ORDER BY d.quantity ASC LIMIT 10
        ")->fetchAll();
        $html = '';
        if (empty($lsRows)) {
            $html = '<tr><td colspan="5" class="text-center text-muted">No low stock items</td></tr>';
        } else {
            foreach ($lsRows as $d) {
                $html .= '<tr><td>' . sanitize($d['drug_name']) . '</td><td>' . sanitize($d['batch_number']) . '</td><td class="text-danger fw-bold">' . (int)$d['quantity'] . '</td><td>' . (int)$d['min_stock'] . '</td><td>' . getStatusBadge($d['status']) . '</td></tr>';
            }
        }
        $response['tables']['low_stock'] = $html;

        // Stock in table
        $siRows = db()->query("
            SELECT st.*, d.drug_name, d.batch_number FROM stock_transactions st
            JOIN drugs d ON st.drug_id = d.id WHERE st.transaction_type = 'stock_in'
            ORDER BY st.transaction_date DESC LIMIT 10
        ")->fetchAll();
        $html = '';
        if (empty($siRows)) {
            $html = '<tr><td colspan="4" class="text-center text-muted">No stock in records</td></tr>';
        } else {
            foreach ($siRows as $tx) {
                $name = sanitize($tx['drug_name']) . ' <small class="text-muted">(' . sanitize($tx['batch_number']) . ')</small>';
                $qty = '+<span class="text-success fw-bold">' . (int)$tx['quantity'] . '</span>';
                $ref = '<small>' . sanitize($tx['reference_number'] ?? '-') . '</small>';
                $date = formatDateTime($tx['transaction_date']);
                $html .= "<tr><td>{$name}</td><td>{$qty}</td><td>{$ref}</td><td>{$date}</td></tr>";
            }
        }
        $response['tables']['stock_in'] = $html;

        // Stock out table
        $soRows = db()->query("
            SELECT st.*, d.drug_name, d.batch_number FROM stock_transactions st
            JOIN drugs d ON st.drug_id = d.id WHERE st.transaction_type = 'stock_out'
            ORDER BY st.transaction_date DESC LIMIT 10
        ")->fetchAll();
        $html = '';
        if (empty($soRows)) {
            $html = '<tr><td colspan="4" class="text-center text-muted">No stock out records</td></tr>';
        } else {
            foreach ($soRows as $tx) {
                $name = sanitize($tx['drug_name']) . ' <small class="text-muted">(' . sanitize($tx['batch_number']) . ')</small>';
                $qty = '-<span class="text-danger fw-bold">' . (int)$tx['quantity'] . '</span>';
                $ref = '<small>' . sanitize($tx['reference_number'] ?? '-') . '</small>';
                $date = formatDateTime($tx['transaction_date']);
                $html .= "<tr><td>{$name}</td><td>{$qty}</td><td>{$ref}</td><td>{$date}</td></tr>";
            }
        }
        $response['tables']['stock_out'] = $html;

        // My today checklists table
        $stmt3 = db()->prepare("
            SELECT dc.*, d.drug_name, d.batch_number FROM drug_checklists dc
            JOIN drugs d ON dc.drug_id = d.id WHERE dc.checked_date = ? AND dc.checked_by = ?
            ORDER BY dc.checked_time DESC LIMIT 10
        ");
        $stmt3->execute([$today, $userId]);
        $myCl = $stmt3->fetchAll();
        $html = '';
        if (empty($myCl)) {
            $html = '<tr><td colspan="5" class="text-center text-muted">You haven\'t done any checklists today. <a href="' . BASE_URL . 'pharmacist/checklist.php">Start now</a></td></tr>';
        } else {
            foreach ($myCl as $cl) {
                $badge = getStatusBadge($cl['checklist_status']);
                $html .= '<tr><td>' . sanitize($cl['drug_name']) . '</td><td>' . sanitize($cl['batch_number']) . '</td><td>' . $badge . '</td><td>' . sanitize($cl['checked_time']) . '</td><td>' . sanitize($cl['remarks'] ?? '-') . '</td></tr>';
            }
        }
        $response['tables']['my_today_checklists'] = $html;

    } else { // staff
        $rdRows = db()->query("
            SELECT d.*, dc.category_name FROM drugs d
            LEFT JOIN drug_categories dc ON d.category_id = dc.id
            ORDER BY d.created_at DESC LIMIT 10
        ")->fetchAll();
        $html = '';
        if (empty($rdRows)) {
            $html = '<tr><td colspan="4" class="text-center text-muted">No drugs in inventory</td></tr>';
        } else {
            foreach ($rdRows as $d) {
                $cat = sanitize($d['category_name'] ?? '-');
                $badge = getStatusBadge($d['status']);
                $html .= '<tr><td>' . sanitize($d['drug_name']) . '</td><td>' . $cat . '</td><td>' . (int)$d['quantity'] . '</td><td>' . $badge . '</td></tr>';
            }
        }
        $response['tables']['recent_drugs'] = $html;

        $clRows = db()->query("
            SELECT dc.*, d.drug_name, d.batch_number, u.first_name, u.last_name
            FROM drug_checklists dc JOIN drugs d ON dc.drug_id = d.id
            LEFT JOIN users u ON dc.checked_by = u.id
            ORDER BY dc.checked_date DESC, dc.checked_time DESC LIMIT 10
        ")->fetchAll();
        $html = '';
        if (empty($clRows)) {
            $html = '<tr><td colspan="4" class="text-center text-muted">No checklists recorded yet</td></tr>';
        } else {
            foreach ($clRows as $cl) {
                $name = sanitize($cl['drug_name']) . ' <small class="text-muted">(' . sanitize($cl['batch_number']) . ')</small>';
                $badge = getStatusBadge($cl['checklist_status']);
                $by = sanitize(trim(($cl['first_name'] ?? '') . ' ' . ($cl['last_name'] ?? '')));
                $date = formatDate($cl['checked_date']);
                $html .= "<tr><td>{$name}</td><td>{$badge}</td><td>{$by}</td><td>{$date}</td></tr>";
            }
        }
        $response['tables']['checklists'] = $html;
    }

    jsonResponse($response);

} catch (PDOException $e) {
    error_log("Dashboard data API error: " . $e->getMessage());
    jsonResponse(['error' => 'Failed to fetch dashboard data'], 500);
}
