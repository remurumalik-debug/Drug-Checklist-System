<?php
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

header('Content-Type: application/json');
$role = getCurrentUserRole();

try {
    $response = [];

    $available = (int)db()->query("SELECT COUNT(*) as cnt FROM drugs WHERE status = 'available'")->fetch()['cnt'];
    $lowStock = (int)db()->query("SELECT COUNT(*) as cnt FROM drugs WHERE quantity <= min_stock AND quantity > 0 AND status NOT IN ('expired','discontinued')")->fetch()['cnt'];
    $outOfStock = (int)db()->query("SELECT COUNT(*) as cnt FROM drugs WHERE (status = 'out_of_stock' OR quantity = 0) AND status != 'discontinued'")->fetch()['cnt'];
    $expired = (int)db()->query("SELECT COUNT(*) as cnt FROM drugs WHERE status = 'expired' OR expiry_date < CURDATE()")->fetch()['cnt'];
    $discontinued = (int)db()->query("SELECT COUNT(*) as cnt FROM drugs WHERE status = 'discontinued'")->fetch()['cnt'];

    $response['statusDistribution'] = [
        'labels' => ['Available', 'Low Stock', 'Out of Stock', 'Expired', 'Discontinued'],
        'data'   => [$available, $lowStock, $outOfStock, $expired, $discontinued],
        'colors' => ['#059669', '#D97706', '#DC2626', '#94A3B8', '#475569'],
    ];

    if ($role === 'admin') {
        $monthLabels = [];
        $stockInData = [];
        $stockOutData = [];
        for ($i = 5; $i >= 0; $i--) {
            $monthLabels[] = date('M', strtotime("-{$i} months"));
            $monthStart = date('Y-m-01', strtotime("-{$i} months"));
            $monthEnd = date('Y-m-t', strtotime("-{$i} months"));

            $si = db()->prepare("SELECT COUNT(*) as cnt FROM stock_transactions WHERE transaction_type = 'stock_in' AND transaction_date BETWEEN ? AND ?");
            $si->execute([$monthStart, $monthEnd]);
            $stockInData[] = (int)$si->fetch()['cnt'];

            $so = db()->prepare("SELECT COUNT(*) as cnt FROM stock_transactions WHERE transaction_type = 'stock_out' AND transaction_date BETWEEN ? AND ?");
            $so->execute([$monthStart, $monthEnd]);
            $stockOutData[] = (int)$so->fetch()['cnt'];
        }

        $response['monthlyTransactions'] = [
            'labels'   => $monthLabels,
            'stockIn'  => $stockInData,
            'stockOut' => $stockOutData,
        ];

        $catRows = db()->query("
            SELECT dc.category_name, COUNT(d.id) as cnt
            FROM drugs d
            JOIN drug_categories dc ON d.category_id = dc.id
            GROUP BY dc.category_name
            ORDER BY cnt DESC
        ")->fetchAll();

        $response['categoryDistribution'] = [
            'labels' => array_column($catRows, 'category_name'),
            'data'   => array_map('intval', array_column($catRows, 'cnt')),
        ];

    } elseif ($role === 'pharmacist') {
        $dayLabels = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
        $weekStart = date('Y-m-d', strtotime('monday this week'));
        $weekEnd = date('Y-m-d', strtotime('sunday this week'));

        $stockInWeek = array_fill(0, 7, 0);
        $stockOutWeek = array_fill(0, 7, 0);

        $siStmt = db()->prepare("SELECT DAYOFWEEK(transaction_date) as dw, COUNT(*) as cnt FROM stock_transactions WHERE transaction_type = 'stock_in' AND transaction_date BETWEEN ? AND ? GROUP BY dw");
        $siStmt->execute([$weekStart, $weekEnd]);
        foreach ($siStmt->fetchAll() as $row) {
            $phpDay = (int)$row['dw'];
            $idx = $phpDay - 2;
            if ($idx < 0) $idx = 6;
            $stockInWeek[$idx] = (int)$row['cnt'];
        }

        $soStmt = db()->prepare("SELECT DAYOFWEEK(transaction_date) as dw, COUNT(*) as cnt FROM stock_transactions WHERE transaction_type = 'stock_out' AND transaction_date BETWEEN ? AND ? GROUP BY dw");
        $soStmt->execute([$weekStart, $weekEnd]);
        foreach ($soStmt->fetchAll() as $row) {
            $phpDay = (int)$row['dw'];
            $idx = $phpDay - 2;
            if ($idx < 0) $idx = 6;
            $stockOutWeek[$idx] = (int)$row['cnt'];
        }

        $response['weeklyActivity'] = [
            'labels'   => $dayLabels,
            'stockIn'  => $stockInWeek,
            'stockOut' => $stockOutWeek,
        ];

        $recentDates = [];
        $recentCounts = [];
        for ($i = 6; $i >= 0; $i--) {
            $d = date('Y-m-d', strtotime("-{$i} days"));
            $recentDates[] = date('M d', strtotime($d));
            $stmt = db()->prepare("SELECT COUNT(*) as cnt FROM drug_checklists WHERE checked_date = ?");
            $stmt->execute([$d]);
            $recentCounts[] = (int)$stmt->fetch()['cnt'];
        }

        $response['recentChecklistTrend'] = [
            'labels' => $recentDates,
            'data'   => $recentCounts,
        ];

    } else {
        $recentDrugs = db()->query("SELECT drug_name, quantity FROM drugs ORDER BY created_at DESC LIMIT 5")->fetchAll();

        $response['recentDrugs'] = [
            'labels' => array_column($recentDrugs, 'drug_name'),
            'data'   => array_map('intval', array_column($recentDrugs, 'quantity')),
        ];
    }

    jsonResponse($response);

} catch (PDOException $e) {
    error_log("Chart data API error: " . $e->getMessage());
    jsonResponse(['error' => 'Failed to fetch chart data'], 500);
}
