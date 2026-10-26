<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('pharmacist');

$pageTitle = 'Drug Checklist';

$userId = getCurrentUserId();
$today = date('Y-m-d');
$currentTime = date('H:i');

// Handle POST - create checklist entry
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_checklist') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $_SESSION['error'] = 'Invalid CSRF token.';
        redirect('pharmacist/checklist.php');
    }

    $drug_id = (int)($_POST['drug_id'] ?? 0);
    $checklist_status = $_POST['checklist_status'] ?? '';
    $remarks = trim($_POST['remarks'] ?? '');
    $checked_date = $_POST['checked_date'] ?? $today;
    $checked_time = $_POST['checked_time'] ?? $currentTime;

    if (!$drug_id || !in_array($checklist_status, ['available', 'low_stock', 'out_of_stock', 'expired', 'damaged'])) {
        $_SESSION['error'] = 'Please select a drug and valid status.';
        redirect('pharmacist/checklist.php');
    }

    try {
        db()->beginTransaction();

        $stmt = db()->prepare("
            INSERT INTO drug_checklists (drug_id, checklist_status, remarks, checked_by, checked_date, checked_time)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$drug_id, $checklist_status, $remarks, $userId, $checked_date, $checked_time]);

        // Update drug status if applicable
        if ($checklist_status === 'expired') {
            db()->prepare("UPDATE drugs SET status = 'expired' WHERE id = ?")->execute([$drug_id]);
        } elseif ($checklist_status === 'out_of_stock') {
            db()->prepare("UPDATE drugs SET quantity = 0, status = 'out_of_stock' WHERE id = ?")->execute([$drug_id]);
        } elseif ($checklist_status === 'damaged') {
            db()->prepare("UPDATE drugs SET status = 'discontinued' WHERE id = ?")->execute([$drug_id]);
        }

        db()->commit();

        $stmt = db()->prepare("SELECT drug_name, batch_number FROM drugs WHERE id = ?");
        $stmt->execute([$drug_id]);
        $drug = $stmt->fetch();

        logAudit($userId, 'checklist', 'drug_checklist', $drug_id, "Checked {$drug['drug_name']} (Batch: {$drug['batch_number']}) as {$checklist_status}");
        notifyAllAdminsAndPharmacists('info', 'Drug Checklist Updated', "{$drug['drug_name']} (Batch: {$drug['batch_number']}) checked as {$checklist_status}.");

        $_SESSION['success'] = 'Checklist entry recorded successfully.';
    } catch (PDOException $e) {
        db()->rollBack();
        $_SESSION['error'] = 'Failed to record checklist: ' . $e->getMessage();
    }
    redirect('pharmacist/checklist.php');
}

// Get all drugs for dropdown
$allDrugs = db()->query("SELECT id, drug_name, batch_number, quantity, status FROM drugs WHERE status != 'discontinued' ORDER BY drug_name ASC")->fetchAll();

// Filters
$filterDate = $_GET['date'] ?? $today;
$where = '1=1';
$params = [];
if (!empty($filterDate)) {
    $where .= " AND dc.checked_date = ?";
    $params[] = $filterDate;
}

$pagination = getPaginatedData('drug_checklists dc', $where, $params, 'dc.checked_time DESC', 15);

// Fetch with joins for display
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage = 15;
$offset = ($page - 1) * $perPage;
$countStmt = db()->prepare("SELECT COUNT(*) as total FROM drug_checklists dc WHERE {$where}");
$countStmt->execute($params);
$total = $countStmt->fetch()['total'];
$totalPages = ceil($total / $perPage);

$stmt = db()->prepare("
    SELECT dc.*, d.drug_name, d.batch_number, u.first_name, u.last_name
    FROM drug_checklists dc
    JOIN drugs d ON dc.drug_id = d.id
    JOIN users u ON dc.checked_by = u.id
    WHERE {$where}
    ORDER BY dc.checked_time DESC
    LIMIT ? OFFSET ?
");
$stmt->execute(array_merge($params, [$perPage, $offset]));
$checklists = $stmt->fetchAll();

$paginationData = [
    'data' => $checklists,
    'current_page' => $page,
    'per_page' => $perPage,
    'total' => $total,
    'total_pages' => $totalPages,
    'offset' => $offset,
];

include_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>Drug Checklist</h1>
        <p>Check and verify drug inventory status</p>
    </div>
</div>

<div class="grid-2">
    <div class="card">
        <div class="card-header">
            <h2>New Checklist Entry</h2>
        </div>
        <div class="card-body">
            <form method="POST" action="">
                <input type="hidden" name="action" value="create_checklist">
                <input type="hidden" name="csrf_token" value="<?= getCsrfToken() ?>">

                <div class="form-group">
                    <label class="form-label">Select Drug <span class="required">*</span></label>
                    <select name="drug_id" class="form-control" required>
                        <option value="">— Choose a drug —</option>
                        <?php foreach ($allDrugs as $d): ?>
                        <option value="<?= $d['id'] ?>">
                            <?= sanitize($d['drug_name']) ?> (Batch: <?= sanitize($d['batch_number']) ?>) — <?= (int)$d['quantity'] ?> units — <?= getStatusBadge($d['status']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Checklist Status <span class="required">*</span></label>
                    <select name="checklist_status" class="form-control" required>
                        <option value="">— Select status —</option>
                        <option value="available">Available</option>
                        <option value="low_stock">Low Stock</option>
                        <option value="out_of_stock">Out of Stock</option>
                        <option value="expired">Expired</option>
                        <option value="damaged">Damaged</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Remarks</label>
                    <textarea name="remarks" class="form-control" rows="3" placeholder="Any observations or notes..."></textarea>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Date</label>
                        <input type="date" name="checked_date" class="form-control" value="<?= $today ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Time</label>
                        <input type="time" name="checked_time" class="form-control" value="<?= $currentTime ?>">
                    </div>
                </div>

                <button type="submit" class="btn btn-primary" style="width:100%;">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>
                    </svg>
                    Record Checklist Entry
                </button>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h2>Checklist Records</h2>
        </div>
        <div class="filters-bar">
            <form method="GET" style="display:flex;gap:8px;align-items:end;">
                <div class="form-group" style="margin:0;">
                    <label class="form-label" style="font-size:0.75rem;">Filter by Date</label>
                    <input type="date" name="date" class="form-control" value="<?= sanitize($filterDate) ?>">
                </div>
                <button type="submit" class="btn btn-primary btn-sm">Filter</button>
                <a href="<?= BASE_URL ?>pharmacist/checklist.php" class="btn btn-secondary btn-sm">Reset</a>
            </form>
        </div>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Drug Name</th>
                        <th>Batch</th>
                        <th>Status</th>
                        <th>Remarks</th>
                        <th>Checked By</th>
                        <th>Time</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($checklists) > 0): ?>
                    <?php foreach ($checklists as $c): ?>
                    <tr>
                        <td><strong><?= sanitize($c['drug_name']) ?></strong></td>
                        <td><?= sanitize($c['batch_number']) ?></td>
                        <td><?= getStatusBadge($c['checklist_status']) ?></td>
                        <td><?= sanitize($c['remarks'] ?? '—') ?></td>
                        <td><?= sanitize($c['first_name'] . ' ' . $c['last_name']) ?></td>
                        <td><?= date('h:i A', strtotime($c['checked_time'])) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php else: ?>
                    <tr>
                        <td colspan="6">
                            <div class="empty-state">
                                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>
                                </svg>
                                <h3>No checklist records found</h3>
                                <p>Start by recording a checklist entry.</p>
                            </div>
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php if ($totalPages > 1): ?>
        <div class="card-footer">
            <?= renderPagination($paginationData) ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php include_once __DIR__ . '/../includes/footer.php'; ?>
