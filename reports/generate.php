<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole(['admin', 'pharmacist', 'staff']);

$pageTitle = 'Generate Reports';
$currentRole = getCurrentUserRole();

$type = $_GET['type'] ?? 'inventory';
$fromDate = $_GET['from_date'] ?? '';
$toDate = $_GET['to_date'] ?? '';
$export = $_GET['export'] ?? '';

$allTypes = ['inventory', 'expired', 'low_stock', 'out_of_stock', 'stock_movement', 'daily_checklist', 'monthly_checklist', 'user_activities', 'audit_logs'];

if ($currentRole === 'admin') {
    $allowedTypes = $allTypes;
} elseif ($currentRole === 'pharmacist') {
    $allowedTypes = ['inventory', 'expired', 'low_stock', 'out_of_stock', 'stock_movement', 'daily_checklist', 'monthly_checklist'];
} else {
    $allowedTypes = ['inventory', 'expired', 'low_stock', 'out_of_stock', 'daily_checklist'];
}

if (!in_array($type, $allowedTypes)) {
    $_SESSION['error'] = 'You do not have permission to access this report.';
    redirect($currentRole . '/reports.php');
}

$data = [];
$columns = [];
$reportTitle = '';
$reportDescription = '';

try {
    switch ($type) {
        case 'inventory':
            $reportTitle = 'Inventory Report';
            $reportDescription = 'Complete list of all drugs in the inventory with category, supplier, quantity, price, expiry, and status.';
            $columns = ['Drug Name', 'Generic Name', 'Category', 'Supplier', 'Quantity', 'Unit Price', 'Total Value', 'Expiry Date', 'Status', 'Storage Location'];
            $where = "1=1";
            $params = [];
            if ($fromDate) { $where .= " AND d.created_at >= ?"; $params[] = $fromDate; }
            if ($toDate) { $where .= " AND d.created_at <= ?"; $params[] = $toDate . ' 23:59:59'; }
            $result = getPaginatedData(
                "drugs d LEFT JOIN drug_categories dc ON d.category_id = dc.id LEFT JOIN suppliers s ON d.supplier_id = s.id",
                $where, $params, "d.drug_name ASC", 20
            );
            $rawData = $result['data'];
            foreach ($rawData as $row) {
                $data[] = [
                    sanitize($row['drug_name']),
                    sanitize($row['generic_name'] ?? 'N/A'),
                    sanitize($row['category_name'] ?? 'N/A'),
                    sanitize($row['supplier_name'] ?? 'N/A'),
                    (int)$row['quantity'],
                    formatCurrency($row['unit_price']),
                    formatCurrency($row['quantity'] * $row['unit_price']),
                    formatDate($row['expiry_date']),
                    getStatusBadge($row['status']),
                    sanitize($row['storage_location'] ?? 'N/A'),
                ];
            }
            $csvData = $rawData;
            break;

        case 'expired':
            $reportTitle = 'Expired Drugs Report';
            $reportDescription = 'Drugs that have expired or have been marked as expired.';
            $columns = ['Drug Name', 'Batch #', 'Manufacturer', 'Quantity', 'Expiry Date', 'Status', 'Storage Location'];
            $where = "(d.status = 'expired' OR d.expiry_date < CURDATE())";
            $params = [];
            if ($fromDate) { $where .= " AND d.expiry_date >= ?"; $params[] = $fromDate; }
            if ($toDate) { $where .= " AND d.expiry_date <= ?"; $params[] = $toDate; }
            $result = getPaginatedData(
                "drugs d LEFT JOIN drug_categories dc ON d.category_id = dc.id LEFT JOIN suppliers s ON d.supplier_id = s.id",
                $where, $params, "d.expiry_date DESC", 20
            );
            foreach ($result['data'] as $row) {
                $data[] = [
                    sanitize($row['drug_name']),
                    sanitize($row['batch_number']),
                    sanitize($row['manufacturer'] ?? 'N/A'),
                    (int)$row['quantity'],
                    formatDate($row['expiry_date']),
                    getStatusBadge($row['status']),
                    sanitize($row['storage_location'] ?? 'N/A'),
                ];
            }
            $csvData = $result['data'];
            break;

        case 'low_stock':
            $reportTitle = 'Low Stock Report';
            $reportDescription = 'Drugs where current quantity is at or below the minimum stock level.';
            $columns = ['Drug Name', 'Batch #', 'Category', 'Current Qty', 'Min Stock', 'Status', 'Supplier'];
            $where = "d.quantity <= d.min_stock AND d.status NOT IN ('expired', 'discontinued')";
            $params = [];
            if ($fromDate) { $where .= " AND d.updated_at >= ?"; $params[] = $fromDate; }
            if ($toDate) { $where .= " AND d.updated_at <= ?"; $params[] = $toDate . ' 23:59:59'; }
            $result = getPaginatedData(
                "drugs d LEFT JOIN drug_categories dc ON d.category_id = dc.id LEFT JOIN suppliers s ON d.supplier_id = s.id",
                $where, $params, "d.quantity ASC", 20
            );
            foreach ($result['data'] as $row) {
                $data[] = [
                    sanitize($row['drug_name']),
                    sanitize($row['batch_number']),
                    sanitize($row['category_name'] ?? 'N/A'),
                    (int)$row['quantity'],
                    (int)$row['min_stock'],
                    getStatusBadge($row['status']),
                    sanitize($row['supplier_name'] ?? 'N/A'),
                ];
            }
            $csvData = $result['data'];
            break;

        case 'out_of_stock':
            $reportTitle = 'Out of Stock Report';
            $reportDescription = 'Drugs that are currently out of stock or have zero quantity.';
            $columns = ['Drug Name', 'Batch #', 'Category', 'Last Qty', 'Min Stock', 'Manufacturer', 'Status'];
            $where = "(d.status='out_of_stock' OR d.quantity=0) AND d.status != 'discontinued'";
            $params = [];
            if ($fromDate) { $where .= " AND d.updated_at >= ?"; $params[] = $fromDate; }
            if ($toDate) { $where .= " AND d.updated_at <= ?"; $params[] = $toDate . ' 23:59:59'; }
            $result = getPaginatedData(
                "drugs d LEFT JOIN drug_categories dc ON d.category_id = dc.id LEFT JOIN suppliers s ON d.supplier_id = s.id",
                $where, $params, "d.quantity ASC", 20
            );
            foreach ($result['data'] as $row) {
                $data[] = [
                    sanitize($row['drug_name']),
                    sanitize($row['batch_number']),
                    sanitize($row['category_name'] ?? 'N/A'),
                    (int)$row['quantity'],
                    (int)$row['min_stock'],
                    sanitize($row['manufacturer'] ?? 'N/A'),
                    getStatusBadge($row['status']),
                ];
            }
            $csvData = $result['data'];
            break;

        case 'stock_movement':
            $reportTitle = 'Stock Movement Report';
            $reportDescription = 'All stock transactions including stock in, stock out, and adjustments.';
            $columns = ['Reference #', 'Drug', 'Type', 'Previous Qty', 'Quantity', 'New Qty', 'Performed By', 'Date', 'Remarks'];
            $where = "1=1";
            $params = [];
            if ($fromDate) { $where .= " AND st.transaction_date >= ?"; $params[] = $fromDate; }
            if ($toDate) { $where .= " AND st.transaction_date <= ?"; $params[] = $toDate . ' 23:59:59'; }
            $result = getPaginatedData(
                "stock_transactions st JOIN drugs d ON st.drug_id = d.id JOIN users u ON st.performed_by = u.id",
                $where, $params, "st.transaction_date DESC", 20
            );
            foreach ($result['data'] as $row) {
                $data[] = [
                    sanitize($row['reference_number'] ?? 'N/A'),
                    sanitize($row['drug_name']),
                    '<span class="badge badge-' . ($row['transaction_type'] === 'stock_in' ? 'info' : ($row['transaction_type'] === 'stock_out' ? 'warning' : 'primary')) . '">' . ucwords(str_replace('_', ' ', $row['transaction_type'])) . '</span>',
                    (int)$row['previous_quantity'],
                    (int)$row['quantity'],
                    (int)$row['new_quantity'],
                    sanitize($row['first_name'] . ' ' . $row['last_name']),
                    formatDateTime($row['transaction_date']),
                    sanitize($row['remarks'] ?? ''),
                ];
            }
            $csvData = $result['data'];
            break;

        case 'daily_checklist':
            $reportTitle = 'Daily Checklist Report';
            $reportDescription = 'Drug checklists grouped by date.';
            $columns = ['Drug', 'Batch #', 'Status', 'Checked By', 'Date', 'Time', 'Remarks'];
            $where = "1=1";
            $params = [];
            if ($fromDate) { $where .= " AND dc.checked_date >= ?"; $params[] = $fromDate; }
            if ($toDate) { $where .= " AND dc.checked_date <= ?"; $params[] = $toDate; }
            if (!$fromDate && !$toDate) {
                $where .= " AND dc.checked_date = CURDATE()";
            }
            $result = getPaginatedData(
                "drug_checklists dc JOIN drugs d ON dc.drug_id = d.id JOIN users u ON dc.checked_by = u.id",
                $where, $params, "dc.checked_date DESC, dc.checked_time DESC", 20
            );
            foreach ($result['data'] as $row) {
                $data[] = [
                    sanitize($row['drug_name']),
                    sanitize($row['batch_number']),
                    getStatusBadge($row['checklist_status']),
                    sanitize($row['first_name'] . ' ' . $row['last_name']),
                    formatDate($row['checked_date']),
                    $row['checked_time'],
                    sanitize($row['remarks'] ?? ''),
                ];
            }
            $csvData = $result['data'];
            break;

        case 'monthly_checklist':
            $reportTitle = 'Monthly Checklist Report';
            $reportDescription = 'Drug checklists grouped by month and year.';
            $columns = ['Month', 'Year', 'Total Checked', 'Available', 'Low Stock', 'Out of Stock', 'Expired', 'Damaged'];
            $where = "1=1";
            $params = [];
            if ($fromDate) { $where .= " AND dc.checked_date >= ?"; $params[] = $fromDate; }
            if ($toDate) { $where .= " AND dc.checked_date <= ?"; $params[] = $toDate; }
            $countSql = "SELECT
                MONTH(dc.checked_date) as month_num,
                YEAR(dc.checked_date) as year_num,
                COUNT(*) as total_checked,
                SUM(CASE WHEN dc.checklist_status = 'available' THEN 1 ELSE 0 END) as available,
                SUM(CASE WHEN dc.checklist_status = 'low_stock' THEN 1 ELSE 0 END) as low_stock,
                SUM(CASE WHEN dc.checklist_status = 'out_of_stock' THEN 1 ELSE 0 END) as out_of_stock,
                SUM(CASE WHEN dc.checklist_status = 'expired' THEN 1 ELSE 0 END) as expired,
                SUM(CASE WHEN dc.checklist_status = 'damaged' THEN 1 ELSE 0 END) as damaged
                FROM drug_checklists dc
                WHERE {$where}
                GROUP BY YEAR(dc.checked_date), MONTH(dc.checked_date)
                ORDER BY year_num DESC, month_num DESC";
            $stmt = db()->prepare($countSql);
            $stmt->execute($params);
            $monthlyData = $stmt->fetchAll();
            $result = ['data' => $monthlyData, 'total' => count($monthlyData), 'current_page' => 1, 'total_pages' => 1, 'per_page' => count($monthlyData)];
            foreach ($monthlyData as $row) {
                $data[] = [
                    date('F', mktime(0, 0, 0, $row['month_num'], 1)),
                    $row['year_num'],
                    $row['total_checked'],
                    $row['available'],
                    $row['low_stock'],
                    $row['out_of_stock'],
                    $row['expired'],
                    $row['damaged'],
                ];
            }
            $csvData = $monthlyData;
            break;

        case 'user_activities':
            $reportTitle = 'User Activities Report';
            $reportDescription = 'Audit logs grouped by user showing their actions.';
            $columns = ['User', 'Role', 'Total Actions', 'Last Activity'];
            $where = "1=1";
            $params = [];
            if ($fromDate) { $where .= " AND al.created_at >= ?"; $params[] = $fromDate; }
            if ($toDate) { $where .= " AND al.created_at <= ?"; $params[] = $toDate . ' 23:59:59'; }
            $countSql = "SELECT
                u.id, u.first_name, u.last_name, r.role_name,
                COUNT(*) as total_actions,
                MAX(al.created_at) as last_activity
                FROM audit_logs al
                JOIN users u ON al.user_id = u.id
                JOIN roles r ON u.role_id = r.id
                WHERE {$where}
                GROUP BY u.id
                ORDER BY total_actions DESC";
            $stmt = db()->prepare($countSql);
            $stmt->execute($params);
            $userData = $stmt->fetchAll();
            $result = ['data' => $userData, 'total' => count($userData), 'current_page' => 1, 'total_pages' => 1, 'per_page' => count($userData)];
            foreach ($userData as $row) {
                $data[] = [
                    sanitize($row['first_name'] . ' ' . $row['last_name']),
                    sanitize($row['role_name']),
                    $row['total_actions'],
                    formatDateTime($row['last_activity']),
                ];
            }
            $csvData = $userData;
            break;

        case 'audit_logs':
            $reportTitle = 'Audit Logs Report';
            $reportDescription = 'Complete audit trail of all system activities.';
            $columns = ['User', 'Action', 'Entity Type', 'Entity ID', 'Description', 'IP Address', 'Date'];
            $where = "1=1";
            $params = [];
            if ($fromDate) { $where .= " AND al.created_at >= ?"; $params[] = $fromDate; }
            if ($toDate) { $where .= " AND al.created_at <= ?"; $params[] = $toDate . ' 23:59:59'; }
            $result = getPaginatedData(
                "audit_logs al LEFT JOIN users u ON al.user_id = u.id",
                $where, $params, "al.created_at DESC", 20
            );
            foreach ($result['data'] as $row) {
                $data[] = [
                    sanitize(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? 'System')),
                    sanitize(ucwords(str_replace('_', ' ', $row['action']))),
                    sanitize($row['entity_type'] ?? 'N/A'),
                    $row['entity_id'] ?? 'N/A',
                    sanitize($row['description'] ?? ''),
                    sanitize($row['ip_address'] ?? 'N/A'),
                    formatDateTime($row['created_at']),
                ];
            }
            $csvData = $result['data'];
            break;
    }
} catch (PDOException $e) {
    error_log("Report generation error: " . $e->getMessage());
    $_SESSION['error'] = 'Failed to generate report.';
}

if ($export === 'csv' && !empty($csvData)) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . str_replace(' ', '_', strtolower($reportTitle)) . '_' . date('Ymd') . '.csv"');
    $output = fopen('php://output', 'w');
    fputcsv($output, $columns);
    foreach ($csvData as $row) {
        $csvRow = [];
        foreach ($row as $key => $value) {
            $csvRow[$key] = strip_tags($value);
        }
        fputcsv($output, $csvRow);
    }
    fclose($output);
    exit;
}

include_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div class="page-header-left">
        <h1 class="page-title"><?= sanitize($reportTitle) ?></h1>
        <p class="page-subtitle"><?= sanitize($reportDescription) ?></p>
    </div>
    <div class="page-header-right">
        <button onclick="window.print()" class="btn btn-outline-secondary">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/>
            </svg>
            Print
        </a>
        <a href="?type=<?= urlencode($type) ?>&export=csv&from_date=<?= urlencode($fromDate) ?>&to_date=<?= urlencode($toDate) ?>" class="btn btn-success">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/>
            </svg>
            Export CSV
        </a>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <form method="GET" class="filter-form">
            <div class="filter-row">
                <div class="filter-group">
                    <label class="form-label" for="type">Report Type</label>
                    <select id="type" name="type" class="form-control" onchange="this.form.submit()">
                        <?php foreach ($allowedTypes as $t): ?>
                        <option value="<?= $t ?>" <?= $type === $t ? 'selected' : '' ?>><?= ucwords(str_replace('_', ' ', $t)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label class="form-label" for="from_date">From Date</label>
                    <input type="date" id="from_date" name="from_date" class="form-control" value="<?= sanitize($fromDate) ?>">
                </div>
                <div class="filter-group">
                    <label class="form-label" for="to_date">To Date</label>
                    <input type="date" id="to_date" name="to_date" class="form-control" value="<?= sanitize($toDate) ?>">
                </div>
                <div class="filter-group filter-group-btn">
                    <label class="form-label">&nbsp;</label>
                    <button type="submit" class="btn btn-primary btn-block">Generate</button>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="card mt-3">
    <div class="card-header">
        <h3><?= sanitize($reportTitle) ?></h3>
        <?php if (isset($result)): ?>
        <span class="card-badge"><?= $result['total'] ?? 0 ?> Records</span>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <?php foreach ($columns as $col): ?>
                        <th><?= sanitize($col) ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($data)): ?>
                    <tr><td colspan="<?= count($columns) ?>" class="text-center">No data found for this report.</td></tr>
                    <?php else: ?>
                    <?php foreach ($data as $row): ?>
                    <tr>
                        <?php foreach ($row as $cell): ?>
                        <td><?= $cell ?></td>
                        <?php endforeach; ?>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php if (isset($result) && isset($result['total_pages'])): ?>
        <?= renderPagination($result) ?>
        <?php endif; ?>
    </div>
</div>

<style media="print">
    .sidebar, .navbar, .filter-form, .page-header-right, .btn, .pagination-container, footer, .card-header .card-badge { display: none !important; }
    .main-content { margin-left: 0 !important; }
    .card { box-shadow: none !important; border: 1px solid #ddd !important; }
    body { font-size: 12pt; }
</style>

<?php include_once __DIR__ . '/../includes/footer.php'; ?>
