<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('admin');

$pageTitle = 'Drug Management';

$userId = getCurrentUserId();
$action = $_POST['action'] ?? $_GET['action'] ?? '';

// Create drug
if ($action === 'create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $_SESSION['error'] = 'Invalid CSRF token.';
        redirect('admin/drugs.php');
    }

    $drug_name = trim($_POST['drug_name'] ?? '');
    $generic_name = trim($_POST['generic_name'] ?? '');
    $brand_name = trim($_POST['brand_name'] ?? '');
    $drug_code = trim($_POST['drug_code'] ?? '');
    $dosage_form = trim($_POST['dosage_form'] ?? '');
    $strength = trim($_POST['strength'] ?? '');
    $category_id = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
    $batch_number = trim($_POST['batch_number'] ?? '');
    $manufacturer = trim($_POST['manufacturer'] ?? '');
    $supplier_id = !empty($_POST['supplier_id']) ? (int)$_POST['supplier_id'] : null;
    $quantity = (int)($_POST['quantity'] ?? 0);
    $min_stock = (int)($_POST['min_stock'] ?? 10);
    $unit_price = (float)($_POST['unit_price'] ?? 0);
    $expiry_date = $_POST['expiry_date'] ?? '';
    $manufacturing_date = $_POST['manufacturing_date'] ?? null;
    $storage_location = trim($_POST['storage_location'] ?? '');
    $storage_condition = trim($_POST['storage_condition'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $barcode = trim($_POST['barcode'] ?? '');

    if (empty($drug_name) || empty($batch_number) || empty($expiry_date)) {
        $_SESSION['error'] = 'Drug name, batch number, and expiry date are required.';
        redirect('admin/drugs.php');
    }
    if (strtotime($expiry_date) < strtotime(date('Y-m-d'))) {
        $_SESSION['error'] = 'Expiry date cannot be in the past.';
        redirect('admin/drugs.php');
    }

    try {
        $stmt = db()->prepare("SELECT id FROM drugs WHERE batch_number = ?");
        $stmt->execute([$batch_number]);
        if ($stmt->fetch()) {
            $_SESSION['error'] = 'A drug with this batch number already exists.';
            redirect('admin/drugs.php');
        }

        if ($quantity <= 0) { $status = 'out_of_stock'; }
        elseif ($quantity <= $min_stock) { $status = 'low_stock'; }
        else { $status = 'available'; }
        if (strtotime($expiry_date) < strtotime(date('Y-m-d'))) { $status = 'expired'; }

        $stmt = db()->prepare("
            INSERT INTO drugs (drug_name, generic_name, brand_name, category_id,
                               batch_number, manufacturer, supplier_id, quantity, min_stock, unit_price,
                               expiry_date, manufacturing_date, storage_location, description,
                               barcode, status, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $drug_name, $generic_name, $brand_name, $category_id,
            $batch_number, $manufacturer, $supplier_id, $quantity, $min_stock, $unit_price,
            $expiry_date, $manufacturing_date, $storage_location, $description,
            $barcode, $status, $userId
        ]);
        $drugId = db()->lastInsertId();

        logAudit($userId, 'create', 'drug', $drugId, "Created drug: {$drug_name} (Batch: {$batch_number})");
        notifyAllAdminsAndPharmacists('info', 'New Drug Added', "{$drug_name} (Batch: {$batch_number}) has been added.");

        if ($quantity > 0) {
            $ref = generateReferenceNumber('STKIN');
            $stmt = db()->prepare("
                INSERT INTO stock_transactions (drug_id, transaction_type, quantity, previous_quantity, new_quantity, unit_price, reference_number, remarks, performed_by)
                VALUES (?, 'stock_in', ?, 0, ?, ?, ?, 'Initial stock', ?)
            ");
            $stmt->execute([$drugId, $quantity, $quantity, $unit_price, $ref, $userId]);
        }

        $_SESSION['success'] = 'Drug created successfully.';
        regenerateCsrfToken();
    } catch (PDOException $e) {
        $_SESSION['error'] = 'Failed to create drug: ' . $e->getMessage();
    }
    redirect('admin/drugs.php');
}

// Edit drug
if ($action === 'edit' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $_SESSION['error'] = 'Invalid CSRF token.';
        redirect('admin/drugs.php');
    }

    $drugId = (int)($_POST['drug_id'] ?? 0);
    if (!$drugId) {
        $_SESSION['error'] = 'Invalid drug ID.';
        redirect('admin/drugs.php');
    }

    $drug_name = trim($_POST['drug_name'] ?? '');
    $generic_name = trim($_POST['generic_name'] ?? '');
    $brand_name = trim($_POST['brand_name'] ?? '');
    $drug_code = trim($_POST['drug_code'] ?? '');
    $dosage_form = trim($_POST['dosage_form'] ?? '');
    $strength = trim($_POST['strength'] ?? '');
    $category_id = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
    $batch_number = trim($_POST['batch_number'] ?? '');
    $manufacturer = trim($_POST['manufacturer'] ?? '');
    $supplier_id = !empty($_POST['supplier_id']) ? (int)$_POST['supplier_id'] : null;
    $quantity = (int)($_POST['quantity'] ?? 0);
    $min_stock = (int)($_POST['min_stock'] ?? 10);
    $unit_price = (float)($_POST['unit_price'] ?? 0);
    $expiry_date = $_POST['expiry_date'] ?? '';
    $manufacturing_date = $_POST['manufacturing_date'] ?? null;
    $storage_location = trim($_POST['storage_location'] ?? '');
    $storage_condition = trim($_POST['storage_condition'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $barcode = trim($_POST['barcode'] ?? '');

    if (empty($drug_name) || empty($batch_number) || empty($expiry_date)) {
        $_SESSION['error'] = 'Drug name, batch number, and expiry date are required.';
        redirect('admin/drugs.php');
    }

    try {
        $stmt = db()->prepare("SELECT id FROM drugs WHERE batch_number = ? AND id != ?");
        $stmt->execute([$batch_number, $drugId]);
        if ($stmt->fetch()) {
            $_SESSION['error'] = 'Another drug with this batch number already exists.';
            redirect('admin/drugs.php');
        }

        if ($quantity <= 0) { $status = 'out_of_stock'; }
        elseif ($quantity <= $min_stock) { $status = 'low_stock'; }
        else { $status = 'available'; }
        if (strtotime($expiry_date) < strtotime(date('Y-m-d'))) { $status = 'expired'; }

        $stmt = db()->prepare("
            UPDATE drugs SET drug_name=?, generic_name=?, brand_name=?, category_id=?,
                            batch_number=?, manufacturer=?, supplier_id=?, quantity=?, min_stock=?, unit_price=?,
                            expiry_date=?, manufacturing_date=?, storage_location=?, description=?,
                            barcode=?, status=?, updated_at=CURRENT_TIMESTAMP
            WHERE id=?
        ");
        $stmt->execute([
            $drug_name, $generic_name, $brand_name, $category_id,
            $batch_number, $manufacturer, $supplier_id, $quantity, $min_stock, $unit_price,
            $expiry_date, $manufacturing_date, $storage_location, $description,
            $barcode, $status, $drugId
        ]);

        logAudit($userId, 'edit', 'drug', $drugId, "Edited drug: {$drug_name} (Batch: {$batch_number})");
        $_SESSION['success'] = 'Drug updated successfully.';
        regenerateCsrfToken();
    } catch (PDOException $e) {
        $_SESSION['error'] = 'Failed to update drug: ' . $e->getMessage();
    }
    redirect('admin/drugs.php');
}

// Delete drug
if (isset($_GET['delete'])) {
    if (!isset($_GET['csrf_token']) || !verifyCsrfToken($_GET['csrf_token'])) {
        $_SESSION['error'] = 'Invalid security token.';
        redirect('admin/drugs.php');
    }
    if (!isset($_GET['confirm']) || $_GET['confirm'] !== 'yes') {
        $_SESSION['warning'] = 'Are you sure you want to delete this drug? <a href="?delete=' . (int)$_GET['delete'] . '&confirm=yes&csrf_token=' . getCsrfToken() . '" class="alert-link">Yes, delete</a>';
        redirect('admin/drugs.php');
    }

    $drugId = (int)$_GET['delete'];
    try {
        $stmt = db()->prepare("SELECT drug_name, batch_number FROM drugs WHERE id = ?");
        $stmt->execute([$drugId]);
        $drug = $stmt->fetch();
        if ($drug) {
            db()->prepare("DELETE FROM drugs WHERE id = ?")->execute([$drugId]);
            logAudit($userId, 'delete', 'drug', $drugId, "Deleted drug: {$drug['drug_name']} (Batch: {$drug['batch_number']})");
            $_SESSION['success'] = 'Drug deleted successfully.';
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = 'Failed to delete drug: ' . $e->getMessage();
    }
    redirect('admin/drugs.php');
}

// Status toggle
if (isset($_GET['toggle'])) {
    if (!isset($_GET['csrf_token']) || !verifyCsrfToken($_GET['csrf_token'])) {
        $_SESSION['error'] = 'Invalid security token.';
        redirect('admin/drugs.php');
    }
    $drugId = (int)$_GET['toggle'];
    try {
        $stmt = db()->prepare("SELECT id, drug_name, status FROM drugs WHERE id = ?");
        $stmt->execute([$drugId]);
        $drug = $stmt->fetch();
        if (!$drug) { $_SESSION['error'] = 'Drug not found.'; redirect('admin/drugs.php'); }
        $newStatus = $drug['status'] === 'available' ? 'discontinued' : 'available';
        db()->prepare("UPDATE drugs SET status = ? WHERE id = ?")->execute([$newStatus, $drugId]);
        logAudit($userId, 'toggle_status', 'drug', $drugId, "Toggled drug {$drug['drug_name']} to {$newStatus}");
        $_SESSION['success'] = "Drug status changed to {$newStatus}.";
    } catch (PDOException $e) {
        $_SESSION['error'] = 'Failed to toggle status: ' . $e->getMessage();
    }
    redirect('admin/drugs.php');
}

include_once __DIR__ . '/../includes/header.php';

// Filters + Pagination with JOINs
$categoryId = isset($_GET['category_id']) ? (int)$_GET['category_id'] : 0;
$status = $_GET['status'] ?? '';
$search = trim($_GET['search'] ?? '');

$where = [];
$params = [];
if ($categoryId > 0) { $where[] = 'd.category_id = ?'; $params[] = $categoryId; }
if (!empty($status)) { $where[] = 'd.status = ?'; $params[] = $status; }
if (!empty($search)) {
    $where[] = '(d.drug_name LIKE ? OR d.generic_name LIKE ? OR d.batch_number LIKE ? OR d.brand_name LIKE ?)';
    $searchTerm = '%' . $search . '%';
    $params[] = $searchTerm; $params[] = $searchTerm; $params[] = $searchTerm; $params[] = $searchTerm;
}
$whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage = 15;
$offset = ($page - 1) * $perPage;

$countStmt = db()->prepare("SELECT COUNT(*) as total FROM drugs d {$whereClause}");
$countStmt->execute($params);
$total = $countStmt->fetch()['total'];
$totalPages = ceil($total / $perPage);

$stmt = db()->prepare("
    SELECT d.*, dc.category_name, s.supplier_name
    FROM drugs d
    LEFT JOIN drug_categories dc ON d.category_id = dc.id
    LEFT JOIN suppliers s ON d.supplier_id = s.id
    {$whereClause}
    ORDER BY d.created_at DESC
    LIMIT ? OFFSET ?
");
$stmt->execute(array_merge($params, [$perPage, $offset]));
$drugs = $stmt->fetchAll();

$paginationData = ['data' => $drugs, 'current_page' => $page, 'per_page' => $perPage, 'total' => $total, 'total_pages' => $totalPages, 'offset' => $offset];

$categories = db()->query("SELECT * FROM drug_categories WHERE status = 'active' ORDER BY category_name")->fetchAll();
$statuses = ['available', 'low_stock', 'out_of_stock', 'expired', 'discontinued'];
$allSuppliers = db()->query("SELECT id, supplier_name FROM suppliers WHERE status='active' ORDER BY supplier_name")->fetchAll();
?>

<div class="page-header">
    <h1 class="page-title">Drug Management</h1>
    <button class="btn btn-primary" onclick="openModal('addDrugModal')">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
        </svg>
        Add Drug
    </button>
</div>

<div class="card">
    <div class="card-header">
        <form method="GET" action="" class="filter-form">
            <div class="form-row">
                <div class="form-group col-md-3">
                    <input type="text" name="search" class="form-control" placeholder="Search drugs..." value="<?= sanitize($search) ?>">
                </div>
                <div class="form-group col-md-3">
                    <select name="category_id" class="form-control">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>" <?= $categoryId === (int)$cat['id'] ? 'selected' : '' ?>><?= sanitize($cat['category_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group col-md-2">
                    <select name="status" class="form-control">
                        <option value="">All Status</option>
                        <?php foreach ($statuses as $s): ?>
                        <option value="<?= $s ?>" <?= $status === $s ? 'selected' : '' ?>><?= ucwords(str_replace('_', ' ', $s)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group col-md-2">
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
                        <th>Drug Name</th>
                        <th>Generic Name</th>
                        <th>Batch #</th>
                        <th>Category</th>
                        <th>Manufacturer</th>
                        <th>Qty</th>
                        <th>Expiry</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($drugs)): ?>
                    <tr><td colspan="9" class="text-center text-muted">No drugs found</td></tr>
                    <?php else: ?>
                    <?php foreach ($drugs as $drug): ?>
                    <tr>
                        <td><?= sanitize($drug['drug_name']) ?></td>
                        <td><?= sanitize($drug['generic_name'] ?? '-') ?></td>
                        <td><?= sanitize($drug['batch_number']) ?></td>
                        <td><?= sanitize($drug['category_name'] ?? '-') ?></td>
                        <td><?= sanitize($drug['manufacturer'] ?? '-') ?></td>
                        <td><?= $drug['quantity'] ?></td>
                        <td><?= formatDate($drug['expiry_date']) ?></td>
                        <td><?= getStatusBadge($drug['status']) ?></td>
                        <td>
                            <div class="action-btns">
                                <button class="btn btn-sm btn-info" onclick='viewDrug(<?= $drug['id'] ?>)'>
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>
                                    </svg>
                                </button>
                                <button class="btn btn-sm btn-warning" onclick="editDrug(<?= $drug['id'] ?>)">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                                    </svg>
                                </button>
                                <a href="?toggle=<?= $drug['id'] ?>&csrf_token=<?= getCsrfToken() ?>" class="btn btn-sm btn-secondary" onclick="return confirm('Toggle status for <?= sanitize($drug['drug_name']) ?>?')">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <rect x="1" y="5" width="22" height="14" rx="7" ry="7"/><circle cx="8" cy="12" r="3.5" fill="<?= $drug['status'] === 'available' ? 'currentColor' : 'none' ?>"/>
                                    </svg>
                                </a>
                                <a href="?delete=<?= $drug['id'] ?>&csrf_token=<?= getCsrfToken() ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete <?= sanitize($drug['drug_name']) ?>? This cannot be undone.')">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                                    </svg>
                                </a>
                            </div>
                        </td>
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

<!-- View Drug Modal -->
<div class="modal" id="viewDrugModal">
    <div class="modal-backdrop" onclick="closeModal('viewDrugModal')"></div>
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">Drug Details</h3>
            <button class="modal-close" onclick="closeModal('viewDrugModal')">&times;</button>
        </div>
        <div class="modal-body" id="drugDetailBody">
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal('viewDrugModal')">Close</button>
        </div>
    </div>
</div>

<!-- Add Drug Modal -->
<div class="modal-overlay" id="addDrugModal">
    <div class="modal modal-lg">
        <form method="POST" action="">
            <div class="modal-header">
                <h2>Add New Drug</h2>
                <button type="button" class="modal-close" onclick="closeModal('addDrugModal')">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                    </svg>
                </button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="action" value="create">
                <input type="hidden" name="csrf_token" value="<?= getCsrfToken() ?>">
                <?php if (count($categories) === 0): ?>
                <div class="alert alert-warning">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>
                    </svg>
                    <span>No categories found. Please create drug categories first.</span>
                </div>
                <?php endif; ?>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Drug Name <span class="required">*</span></label>
                        <input type="text" name="drug_name" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Generic Name</label>
                        <input type="text" name="generic_name" class="form-control">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Drug Code</label>
                        <input type="text" name="drug_code" class="form-control" placeholder="e.g. DRG-000001">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Dosage Form</label>
                        <input type="text" name="dosage_form" class="form-control" placeholder="e.g. Tablet">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Strength</label>
                        <input type="text" name="strength" class="form-control" placeholder="e.g. 500 mg">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Storage Condition</label>
                        <input type="text" name="storage_condition" class="form-control" placeholder="e.g. Room Temperature">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Brand Name</label>
                        <input type="text" name="brand_name" class="form-control">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Category</label>
                        <select name="category_id" class="form-control">
                            <option value="">Select Category</option>
                            <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>"><?= sanitize($cat['category_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Batch Number <span class="required">*</span></label>
                        <input type="text" name="batch_number" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Manufacturer</label>
                        <input type="text" name="manufacturer" class="form-control">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Supplier</label>
                        <select name="supplier_id" class="form-control">
                            <option value="">Select Supplier</option>
                            <?php foreach ($allSuppliers as $sup): ?>
                            <option value="<?= $sup['id'] ?>"><?= sanitize($sup['supplier_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Barcode</label>
                        <input type="text" name="barcode" class="form-control">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Quantity <span class="required">*</span></label>
                        <input type="number" name="quantity" class="form-control" min="0" value="0" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Min Stock <span class="required">*</span></label>
                        <input type="number" name="min_stock" class="form-control" min="1" value="10" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Unit Price</label>
                        <input type="number" name="unit_price" class="form-control" min="0" step="0.01" value="0">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Storage Location</label>
                        <input type="text" name="storage_location" class="form-control" placeholder="e.g. Shelf A-12">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Expiry Date <span class="required">*</span></label>
                        <input type="date" name="expiry_date" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Manufacturing Date</label>
                        <input type="date" name="manufacturing_date" class="form-control">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-control" rows="3"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addDrugModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Create Drug</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Drug Modal -->
<div class="modal-overlay" id="editDrugModal">
    <div class="modal modal-lg">
        <form method="POST" action="">
            <div class="modal-header">
                <h2>Edit Drug</h2>
                <button type="button" class="modal-close" onclick="closeModal('editDrugModal')">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                    </svg>
                </button>
            </div>
            <div class="modal-body" id="editDrugContent">
                <div class="loading"><div class="spinner"></div></div>
            </div>
            <div class="modal-footer">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="drug_id" id="editDrugId">
                <input type="hidden" name="csrf_token" value="<?= getCsrfToken() ?>">
                <button type="button" class="btn btn-secondary" onclick="closeModal('editDrugModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Update Drug</button>
            </div>
        </form>
    </div>
</div>

<script>
function openModal(id) { document.getElementById(id).classList.add('show'); }
function closeModal(id) { document.getElementById(id).classList.remove('show'); }

function viewDrug(id) {
    fetch('<?= BASE_URL ?>api/get_drug.php?id=' + id)
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                const drug = d.data;
                const statusColors = {available:'success', low_stock:'warning', out_of_stock:'danger', expired:'secondary', discontinued:'dark'};
                const sc = statusColors[drug.status] || 'primary';
                const sl = drug.status.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
                document.getElementById('drugDetailBody').innerHTML = `
                    <div class="detail-grid">
                        <div class="detail-row"><span class="detail-label">Drug Name</span><span class="detail-value">${escapeHtml(drug.drug_name)}</span></div>
                        <div class="detail-row"><span class="detail-label">Generic Name</span><span class="detail-value">${escapeHtml(drug.generic_name || '-')}</span></div>
                        <div class="detail-row"><span class="detail-label">Brand Name</span><span class="detail-value">${escapeHtml(drug.brand_name || '-')}</span></div>
                        <div class="detail-row"><span class="detail-label">Batch Number</span><span class="detail-value">${escapeHtml(drug.batch_number)}</span></div>
                        <div class="detail-row"><span class="detail-label">Category</span><span class="detail-value">${escapeHtml(drug.category_name || '-')}</span></div>
                        <div class="detail-row"><span class="detail-label">Manufacturer</span><span class="detail-value">${escapeHtml(drug.manufacturer || '-')}</span></div>
                        <div class="detail-row"><span class="detail-label">Supplier</span><span class="detail-value">${escapeHtml(drug.supplier_name || '-')}</span></div>
                        <div class="detail-row"><span class="detail-label">Quantity</span><span class="detail-value">${drug.quantity}</span></div>
                        <div class="detail-row"><span class="detail-label">Min Stock</span><span class="detail-value">${drug.min_stock}</span></div>
                        <div class="detail-row"><span class="detail-label">Unit Price</span><span class="detail-value">${drug.unit_price ? '#' + parseFloat(drug.unit_price).toFixed(2) : '-'}</span></div>
                        <div class="detail-row"><span class="detail-label">Expiry Date</span><span class="detail-value">${drug.expiry_date}</span></div>
                        <div class="detail-row"><span class="detail-label">Manufacturing Date</span><span class="detail-value">${drug.manufacturing_date || '-'}</span></div>
                        <div class="detail-row"><span class="detail-label">Storage Location</span><span class="detail-value">${escapeHtml(drug.storage_location || '-')}</span></div>
                        <div class="detail-row"><span class="detail-label">Barcode</span><span class="detail-value">${escapeHtml(drug.barcode || '-')}</span></div>
                        <div class="detail-row"><span class="detail-label">Status</span><span class="detail-value"><span class="badge badge-${sc}">${sl}</span></span></div>
                    </div>`;
            } else {
                document.getElementById('drugDetailBody').innerHTML = '<p class="text-danger">Drug not found.</p>';
            }
            openModal('viewDrugModal');
        })
        .catch(() => {
            document.getElementById('drugDetailBody').innerHTML = '<p class="text-danger">Failed to load drug details.</p>';
            openModal('viewDrugModal');
        });
}

function editDrug(id) {
    const modal = document.getElementById('editDrugModal');
    const content = document.getElementById('editDrugContent');
    const idInput = document.getElementById('editDrugId');
    modal.classList.add('show');
    content.innerHTML = '<div class="loading"><div class="spinner"></div></div>';
    idInput.value = id;

    fetch('<?= BASE_URL ?>api/get_drug.php?id=' + id)
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                const drug = d.data;
                content.innerHTML = `
                    <div class="form-row">
                        <div class="form-group"><label class="form-label">Drug Name <span class="required">*</span></label><input type="text" name="drug_name" class="form-control" value="${escapeHtml(drug.drug_name)}" required></div>
                        <div class="form-group"><label class="form-label">Generic Name</label><input type="text" name="generic_name" class="form-control" value="${escapeHtml(drug.generic_name || '')}"></div>
                    </div>
                    <div class="form-row">
                        <div class="form-group"><label class="form-label">Drug Code</label><input type="text" name="drug_code" class="form-control" value="${escapeHtml(drug.drug_code || '')}"></div>
                        <div class="form-group"><label class="form-label">Dosage Form</label><input type="text" name="dosage_form" class="form-control" value="${escapeHtml(drug.dosage_form || '')}"></div>
                    </div>
                    <div class="form-row">
                        <div class="form-group"><label class="form-label">Strength</label><input type="text" name="strength" class="form-control" value="${escapeHtml(drug.strength || '')}"></div>
                        <div class="form-group"><label class="form-label">Storage Condition</label><input type="text" name="storage_condition" class="form-control" value="${escapeHtml(drug.storage_condition || '')}"></div>
                    </div>
                    <div class="form-row">
                        <div class="form-group"><label class="form-label">Brand Name</label><input type="text" name="brand_name" class="form-control" value="${escapeHtml(drug.brand_name || '')}"></div>
                        <div class="form-group"><label class="form-label">Category</label><select name="category_id" class="form-control"><option value="">Select Category</option>${drug.categories.map(c => '<option value="'+c.id+'" '+(c.id==drug.category_id?'selected':'')+'>'+escapeHtml(c.category_name)+'</option>').join('')}</select></div>
                    </div>
                    <div class="form-row">
                        <div class="form-group"><label class="form-label">Batch Number <span class="required">*</span></label><input type="text" name="batch_number" class="form-control" value="${escapeHtml(drug.batch_number)}" required></div>
                        <div class="form-group"><label class="form-label">Manufacturer</label><input type="text" name="manufacturer" class="form-control" value="${escapeHtml(drug.manufacturer || '')}"></div>
                    </div>
                    <div class="form-row">
                        <div class="form-group"><label class="form-label">Supplier</label><select name="supplier_id" class="form-control"><option value="">Select Supplier</option>${drug.suppliers.map(s => '<option value="'+s.id+'" '+(s.id==drug.supplier_id?'selected':'')+'>'+escapeHtml(s.supplier_name)+'</option>').join('')}</select></div>
                        <div class="form-group"><label class="form-label">Barcode</label><input type="text" name="barcode" class="form-control" value="${escapeHtml(drug.barcode || '')}"></div>
                    </div>
                    <div class="form-row">
                        <div class="form-group"><label class="form-label">Quantity <span class="required">*</span></label><input type="number" name="quantity" class="form-control" min="0" value="${drug.quantity}" required></div>
                        <div class="form-group"><label class="form-label">Min Stock <span class="required">*</span></label><input type="number" name="min_stock" class="form-control" min="1" value="${drug.min_stock}" required></div>
                    </div>
                    <div class="form-row">
                        <div class="form-group"><label class="form-label">Unit Price</label><input type="number" name="unit_price" class="form-control" min="0" step="0.01" value="${drug.unit_price}"></div>
                        <div class="form-group"><label class="form-label">Storage Location</label><input type="text" name="storage_location" class="form-control" value="${escapeHtml(drug.storage_location || '')}"></div>
                    </div>
                    <div class="form-row">
                        <div class="form-group"><label class="form-label">Expiry Date <span class="required">*</span></label><input type="date" name="expiry_date" class="form-control" value="${drug.expiry_date}" required></div>
                        <div class="form-group"><label class="form-label">Manufacturing Date</label><input type="date" name="manufacturing_date" class="form-control" value="${drug.manufacturing_date || ''}"></div>
                    </div>
                    <div class="form-group"><label class="form-label">Description</label><textarea name="description" class="form-control" rows="3">${escapeHtml(drug.description || '')}</textarea></div>`;
            } else {
                content.innerHTML = '<div class="alert alert-danger">Failed to load drug data.</div>';
            }
        })
        .catch(() => { content.innerHTML = '<div class="alert alert-danger">Failed to load drug data.</div>'; });
}

function escapeHtml(str) { if (!str) return ''; const d = document.createElement('div'); d.textContent = str; return d.innerHTML; }
function formatDate(dateStr) { if (!dateStr) return '-'; const d = new Date(dateStr + 'T00:00:00'); return d.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' }); }
function formatDateTime(dateTimeStr) { if (!dateTimeStr) return '-'; const d = new Date(dateTimeStr); return d.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' }); }
</script>

<style>
.detail-grid { display: grid; gap: 12px; }
.detail-row { display: flex; padding: 8px 0; border-bottom: 1px solid var(--border-color, #e0e0e0); }
.detail-label { font-weight: 600; min-width: 160px; color: var(--text-muted, #666); }
.detail-value { flex: 1; }
</style>

<?php include_once __DIR__ . '/../includes/footer.php'; ?>
