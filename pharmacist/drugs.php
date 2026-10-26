<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole(['admin', 'pharmacist']);

$pageTitle = 'Drug Management';

$userId = getCurrentUserId();
$action = $_POST['action'] ?? $_GET['action'] ?? '';

// Create drug
if ($action === 'create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $_SESSION['error'] = 'Invalid CSRF token.';
        redirect('pharmacist/drugs.php');
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
        redirect('pharmacist/drugs.php');
    }

    if (strtotime($expiry_date) < strtotime(date('Y-m-d'))) {
        $_SESSION['error'] = 'Expiry date cannot be in the past.';
        redirect('pharmacist/drugs.php');
    }

    try {
        $stmt = db()->prepare("SELECT id FROM drugs WHERE batch_number = ?");
        $stmt->execute([$batch_number]);
        if ($stmt->fetch()) {
            $_SESSION['error'] = 'A drug with this batch number already exists.';
            redirect('pharmacist/drugs.php');
        }

        if ($quantity <= 0) {
            $status = 'out_of_stock';
        } elseif ($quantity <= $min_stock) {
            $status = 'low_stock';
        } else {
            $status = 'available';
        }
        if (strtotime($expiry_date) < strtotime(date('Y-m-d'))) {
            $status = 'expired';
        }

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
    redirect('pharmacist/drugs.php');
}

// Edit drug
if ($action === 'edit' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $_SESSION['error'] = 'Invalid CSRF token.';
        redirect('pharmacist/drugs.php');
    }

    $drugId = (int)($_POST['drug_id'] ?? 0);
    if (!$drugId) {
        $_SESSION['error'] = 'Invalid drug ID.';
        redirect('pharmacist/drugs.php');
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
        redirect('pharmacist/drugs.php');
    }

    try {
        $stmt = db()->prepare("SELECT id FROM drugs WHERE batch_number = ? AND id != ?");
        $stmt->execute([$batch_number, $drugId]);
        if ($stmt->fetch()) {
            $_SESSION['error'] = 'Another drug with this batch number already exists.';
            redirect('pharmacist/drugs.php');
        }

        if ($quantity <= 0) {
            $status = 'out_of_stock';
        } elseif ($quantity <= $min_stock) {
            $status = 'low_stock';
        } else {
            $status = 'available';
        }
        if (strtotime($expiry_date) < strtotime(date('Y-m-d'))) {
            $status = 'expired';
        }

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
    redirect('pharmacist/drugs.php');
}

// Delete drug
if (isset($_GET['delete'])) {
    if (!isset($_GET['csrf_token']) || !verifyCsrfToken($_GET['csrf_token'])) {
        $_SESSION['error'] = 'Invalid security token.';
        redirect('pharmacist/drugs.php');
    }
    if (!isset($_GET['confirm']) || $_GET['confirm'] !== 'yes') {
        $_SESSION['warning'] = 'Are you sure you want to delete this drug? <a href="?delete=' . (int)$_GET['delete'] . '&confirm=yes&csrf_token=' . getCsrfToken() . '" class="alert-link">Yes, delete</a>';
        redirect('pharmacist/drugs.php');
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
    redirect('pharmacist/drugs.php');
}

// Status toggle
if (isset($_GET['toggle'])) {
    if (!isset($_GET['csrf_token']) || !verifyCsrfToken($_GET['csrf_token'])) {
        $_SESSION['error'] = 'Invalid security token.';
        redirect('pharmacist/drugs.php');
    }
    $drugId = (int)$_GET['toggle'];
    try {
        $stmt = db()->prepare("SELECT id, drug_name, status FROM drugs WHERE id = ?");
        $stmt->execute([$drugId]);
        $drug = $stmt->fetch();
        if (!$drug) {
            $_SESSION['error'] = 'Drug not found.';
            redirect('pharmacist/drugs.php');
        }
        $newStatus = $drug['status'] === 'available' ? 'discontinued' : 'available';
        db()->prepare("UPDATE drugs SET status = ? WHERE id = ?")->execute([$newStatus, $drugId]);
        logAudit($userId, 'toggle_status', 'drug', $drugId, "Toggled drug {$drug['drug_name']} to {$newStatus}");
        $_SESSION['success'] = "Drug status changed to {$newStatus}.";
    } catch (PDOException $e) {
        $_SESSION['error'] = 'Failed to toggle status: ' . $e->getMessage();
    }
    redirect('pharmacist/drugs.php');
}

// Build query with JOINs for proper pagination
// Role-based visibility: non-admins only see non-discontinued drugs
$roleVisibility = getCurrentUserRole() === 'admin' ? '1=1' : "d.status != 'discontinued'";
$where = $roleVisibility;
$params = [];
$search = trim($_GET['search'] ?? '');
if ($search !== '') {
    $where .= " AND (d.drug_name LIKE ? OR d.generic_name LIKE ? OR d.batch_number LIKE ? OR d.barcode LIKE ?)";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}
$catFilter = $_GET['category'] ?? '';
if ($catFilter !== '') {
    $where .= " AND d.category_id = ?";
    $params[] = (int)$catFilter;
}
$statusFilter = $_GET['status'] ?? '';
if ($statusFilter !== '') {
    $where .= " AND d.status = ?";
    $params[] = $statusFilter;
}

$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage = 15;
$offset = ($page - 1) * $perPage;

$countStmt = db()->prepare("SELECT COUNT(*) as total FROM drugs d WHERE {$where}");
$countStmt->execute($params);
$total = $countStmt->fetch()['total'];
$totalPages = ceil($total / $perPage);

$stmt = db()->prepare("
    SELECT d.*, dc.category_name, s.supplier_name
    FROM drugs d
    LEFT JOIN drug_categories dc ON d.category_id = dc.id
    LEFT JOIN suppliers s ON d.supplier_id = s.id
    WHERE {$where}
    ORDER BY d.created_at DESC
    LIMIT ? OFFSET ?
");
$stmt->execute(array_merge($params, [$perPage, $offset]));
$drugs = $stmt->fetchAll();

$paginationData = [
    'data' => $drugs,
    'current_page' => $page,
    'per_page' => $perPage,
    'total' => $total,
    'total_pages' => $totalPages,
    'offset' => $offset,
];

$categories = db()->query("SELECT id, category_name FROM drug_categories WHERE status='active' ORDER BY category_name")->fetchAll();
$suppliers = db()->query("SELECT id, supplier_name FROM suppliers WHERE status='active' ORDER BY supplier_name")->fetchAll();

include_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>Drug Management</h1>
        <p>Manage your pharmaceutical inventory</p>
    </div>
    <button class="btn btn-primary" onclick="openModal('addDrugModal')">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
        </svg>
        Add Drug
    </button>
</div>

<div class="card">
    <div class="filters-bar">
        <form method="GET" class="form-row" style="width:100%;grid-template-columns:1fr auto auto auto;align-items:end;gap:12px;">
            <div class="form-group" style="margin:0;">
                <input type="text" name="search" class="form-control" placeholder="Search by name, batch, or barcode..." value="<?= sanitize($search) ?>">
            </div>
            <div class="form-group" style="margin:0;">
                <select name="category" class="form-control">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $cat): ?>
                    <option value="<?= $cat['id'] ?>" <?= $catFilter == $cat['id'] ? 'selected' : '' ?>><?= sanitize($cat['category_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" style="margin:0;">
                <select name="status" class="form-control">
                    <option value="">All Status</option>
                    <option value="available" <?= $statusFilter === 'available' ? 'selected' : '' ?>>Available</option>
                    <option value="low_stock" <?= $statusFilter === 'low_stock' ? 'selected' : '' ?>>Low Stock</option>
                    <option value="out_of_stock" <?= $statusFilter === 'out_of_stock' ? 'selected' : '' ?>>Out of Stock</option>
                    <option value="expired" <?= $statusFilter === 'expired' ? 'selected' : '' ?>>Expired</option>
                    <option value="discontinued" <?= $statusFilter === 'discontinued' ? 'selected' : '' ?>>Discontinued</option>
                </select>
            </div>
            <button type="submit" class="btn btn-primary">Filter</button>
            <a href="<?= BASE_URL ?>pharmacist/drugs.php" class="btn btn-secondary">Reset</a>
        </form>
    </div>
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>Drug Name</th>
                    <th>Generic Name</th>
                    <th>Batch #</th>
                    <th>Category</th>
                    <th>Manufacturer</th>
                    <th>Qty</th>
                    <th>Min Stock</th>
                    <th>Price</th>
                    <th>Expiry</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($drugs) > 0): ?>
                <?php foreach ($drugs as $d): ?>
                <tr>
                    <td><strong><?= sanitize($d['drug_name']) ?></strong></td>
                    <td><?= sanitize($d['generic_name'] ?? '—') ?></td>
                    <td><?= sanitize($d['batch_number']) ?></td>
                    <td><?= sanitize($d['category_name'] ?? '—') ?></td>
                    <td><?= sanitize($d['manufacturer'] ?? '—') ?></td>
                    <td><?= (int)$d['quantity'] ?></td>
                    <td><?= (int)$d['min_stock'] ?></td>
                    <td><?= formatCurrency($d['unit_price']) ?></td>
                    <td><?= formatDate($d['expiry_date']) ?></td>
                    <td><?= getStatusBadge($d['status']) ?></td>
                    <td>
                        <div class="action-buttons">
                            <button class="action-btn view" onclick="viewDrug(<?= $d['id'] ?>)" title="View">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>
                                </svg>
                            </button>
                            <button class="action-btn edit" onclick="editDrug(<?= $d['id'] ?>)" title="Edit">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                                </svg>
                            </button>
                            <a href="?toggle=<?= $d['id'] ?>&csrf_token=<?= getCsrfToken() ?>" class="action-btn <?= $d['status'] === 'available' ? 'warning' : 'success' ?>" title="<?= $d['status'] === 'available' ? 'Deactivate' : 'Activate' ?>" onclick="return confirm('Toggle status for <?= sanitize($d['drug_name']) ?>?')">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <rect x="1" y="5" width="22" height="14" rx="7" ry="7"/><circle cx="8" cy="12" r="3.5" fill="<?= $d['status'] === 'available' ? 'currentColor' : 'none' ?>"/>
                                </svg>
                            </a>
                            <a href="?delete=<?= $d['id'] ?>&csrf_token=<?= getCsrfToken() ?>&confirm=yes" class="action-btn delete" title="Delete" onclick="return confirm('Delete <?= sanitize($d['drug_name']) ?>? This cannot be undone.')">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                                </svg>
                            </a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php else: ?>
                <tr>
                    <td colspan="11" class="empty-state">No drugs found.</td>
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
                    <span>No categories found. Please ask an admin to create drug categories first.</span>
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
                            <?php foreach ($suppliers as $sup): ?>
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

<!-- View Drug Modal -->
<div class="modal-overlay" id="viewDrugModal">
    <div class="modal modal-lg">
        <div class="modal-header">
            <h2>Drug Details</h2>
            <button type="button" class="modal-close" onclick="closeModal('viewDrugModal')">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                </svg>
            </button>
        </div>
        <div class="modal-body" id="viewDrugContent">
            <div class="loading"><div class="spinner"></div></div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal('viewDrugModal')">Close</button>
        </div>
    </div>
</div>

<script>
function openModal(id) {
    document.getElementById(id).classList.add('show');
}

function closeModal(id) {
    document.getElementById(id).classList.remove('show');
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
                        <div class="form-group">
                            <label class="form-label">Drug Name <span class="required">*</span></label>
                            <input type="text" name="drug_name" class="form-control" value="${escapeHtml(drug.drug_name)}" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Generic Name</label>
                            <input type="text" name="generic_name" class="form-control" value="${escapeHtml(drug.generic_name || '')}">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Drug Code</label>
                            <input type="text" name="drug_code" class="form-control" value="${escapeHtml(drug.drug_code || '')}">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Dosage Form</label>
                            <input type="text" name="dosage_form" class="form-control" value="${escapeHtml(drug.dosage_form || '')}">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Strength</label>
                            <input type="text" name="strength" class="form-control" value="${escapeHtml(drug.strength || '')}">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Storage Condition</label>
                            <input type="text" name="storage_condition" class="form-control" value="${escapeHtml(drug.storage_condition || '')}">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Brand Name</label>
                            <input type="text" name="brand_name" class="form-control" value="${escapeHtml(drug.brand_name || '')}">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Category</label>
                            <select name="category_id" class="form-control">
                                <option value="">Select Category</option>
                                ${drug.categories.map(c => `<option value="${c.id}" ${c.id == drug.category_id ? 'selected' : ''}>${escapeHtml(c.category_name)}</option>`).join('')}
                            </select>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Batch Number <span class="required">*</span></label>
                            <input type="text" name="batch_number" class="form-control" value="${escapeHtml(drug.batch_number)}" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Manufacturer</label>
                            <input type="text" name="manufacturer" class="form-control" value="${escapeHtml(drug.manufacturer || '')}">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Supplier</label>
                            <select name="supplier_id" class="form-control">
                                <option value="">Select Supplier</option>
                                ${drug.suppliers.map(s => `<option value="${s.id}" ${s.id == drug.supplier_id ? 'selected' : ''}>${escapeHtml(s.supplier_name)}</option>`).join('')}
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Barcode</label>
                            <input type="text" name="barcode" class="form-control" value="${escapeHtml(drug.barcode || '')}">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Quantity <span class="required">*</span></label>
                            <input type="number" name="quantity" class="form-control" min="0" value="${drug.quantity}" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Min Stock <span class="required">*</span></label>
                            <input type="number" name="min_stock" class="form-control" min="1" value="${drug.min_stock}" required>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Unit Price</label>
                            <input type="number" name="unit_price" class="form-control" min="0" step="0.01" value="${drug.unit_price}">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Storage Location</label>
                            <input type="text" name="storage_location" class="form-control" value="${escapeHtml(drug.storage_location || '')}">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Expiry Date <span class="required">*</span></label>
                            <input type="date" name="expiry_date" class="form-control" value="${drug.expiry_date}" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Manufacturing Date</label>
                            <input type="date" name="manufacturing_date" class="form-control" value="${drug.manufacturing_date || ''}">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="3">${escapeHtml(drug.description || '')}</textarea>
                    </div>
                `;
            } else {
                content.innerHTML = '<div class="alert alert-danger">Failed to load drug data.</div>';
            }
        })
        .catch(() => {
            content.innerHTML = '<div class="alert alert-danger">Failed to load drug data.</div>';
        });
}

function viewDrug(id) {
    const modal = document.getElementById('viewDrugModal');
    const content = document.getElementById('viewDrugContent');
    modal.classList.add('show');
    content.innerHTML = '<div class="loading"><div class="spinner"></div></div>';

    fetch('<?= BASE_URL ?>api/get_drug.php?id=' + id)
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                const drug = d.data;
                let transactionsHtml = '';
                if (drug.transactions && drug.transactions.length > 0) {
                    transactionsHtml = '<div class="table-container" style="margin-top:12px;"><table><thead><tr><th>Type</th><th>Qty</th><th>Previous</th><th>New</th><th>Reference</th><th>Date</th><th>By</th></tr></thead><tbody>';
                    drug.transactions.forEach(t => {
                        transactionsHtml += `<tr>
                            <td>${t.transaction_type === 'stock_in' ? '<span class="badge badge-info">Stock In</span>' : t.transaction_type === 'stock_out' ? '<span class="badge badge-warning">Stock Out</span>' : '<span class="badge badge-primary">Adjustment</span>'}</td>
                            <td>${t.quantity}</td>
                            <td>${t.previous_quantity}</td>
                            <td>${t.new_quantity}</td>
                            <td>${escapeHtml(t.reference_number || '—')}</td>
                            <td>${formatDateTime(t.transaction_date)}</td>
                            <td>${escapeHtml(t.first_name + ' ' + t.last_name)}</td>
                        </tr>`;
                    });
                    transactionsHtml += '</tbody></table></div>';
                } else {
                    transactionsHtml = '<p class="text-muted" style="margin-top:12px;">No stock transactions recorded.</p>';
                }

                content.innerHTML = `
                    <div class="grid-2">
                        <div class="form-group">
                            <label class="form-label" style="color:#6B7280;font-size:0.75rem;">Drug Name</label>
                            <p style="font-weight:600;">${escapeHtml(drug.drug_name)}</p>
                        </div>
                        <div class="form-group">
                            <label class="form-label" style="color:#6B7280;font-size:0.75rem;">Generic Name</label>
                            <p>${escapeHtml(drug.generic_name || '—')}</p>
                        </div>
                    </div>
                    <div class="grid-2">
                        <div class="form-group">
                            <label class="form-label" style="color:#6B7280;font-size:0.75rem;">Brand Name</label>
                            <p>${escapeHtml(drug.brand_name || '—')}</p>
                        </div>
                        <div class="form-group">
                            <label class="form-label" style="color:#6B7280;font-size:0.75rem;">Category</label>
                            <p>${escapeHtml(drug.category_name || '—')}</p>
                        </div>
                    </div>
                    <div class="grid-2">
                        <div class="form-group">
                            <label class="form-label" style="color:#6B7280;font-size:0.75rem;">Batch Number</label>
                            <p>${escapeHtml(drug.batch_number)}</p>
                        </div>
                        <div class="form-group">
                            <label class="form-label" style="color:#6B7280;font-size:0.75rem;">Manufacturer</label>
                            <p>${escapeHtml(drug.manufacturer || '—')}</p>
                        </div>
                    </div>
                    <div class="grid-2">
                        <div class="form-group">
                            <label class="form-label" style="color:#6B7280;font-size:0.75rem;">Supplier</label>
                            <p>${escapeHtml(drug.supplier_name || '—')}</p>
                        </div>
                        <div class="form-group">
                            <label class="form-label" style="color:#6B7280;font-size:0.75rem;">Barcode</label>
                            <p>${escapeHtml(drug.barcode || '—')}</p>
                        </div>
                    </div>
                    <div class="grid-2">
                        <div class="form-group">
                            <label class="form-label" style="color:#6B7280;font-size:0.75rem;">Quantity</label>
                            <p style="font-size:1.25rem;font-weight:700;">${drug.quantity}</p>
                        </div>
                        <div class="form-group">
                            <label class="form-label" style="color:#6B7280;font-size:0.75rem;">Min Stock</label>
                            <p>${drug.min_stock}</p>
                        </div>
                    </div>
                    <div class="grid-2">
                        <div class="form-group">
                            <label class="form-label" style="color:#6B7280;font-size:0.75rem;">Unit Price</label>
                            <p>${formatCurrency(drug.unit_price)}</p>
                        </div>
                        <div class="form-group">
                            <label class="form-label" style="color:#6B7280;font-size:0.75rem;">Status</label>
                            <p>${getStatusBadge(drug.status)}</p>
                        </div>
                    </div>
                    <div class="grid-2">
                        <div class="form-group">
                            <label class="form-label" style="color:#6B7280;font-size:0.75rem;">Expiry Date</label>
                            <p>${formatDate(drug.expiry_date)}</p>
                        </div>
                        <div class="form-group">
                            <label class="form-label" style="color:#6B7280;font-size:0.75rem;">Manufacturing Date</label>
                            <p>${drug.manufacturing_date ? formatDate(drug.manufacturing_date) : '—'}</p>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label" style="color:#6B7280;font-size:0.75rem;">Storage Location</label>
                        <p>${escapeHtml(drug.storage_location || '—')}</p>
                    </div>
                    <div class="form-group">
                        <label class="form-label" style="color:#6B7280;font-size:0.75rem;">Description</label>
                        <p>${escapeHtml(drug.description || 'No description.')}</p>
                    </div>
                    <div class="form-group" style="margin-top:16px;padding-top:16px;border-top:1px solid #E5E7EB;">
                        <label class="form-label" style="color:#6B7280;font-size:0.75rem;">Stock Transaction History</label>
                        ${transactionsHtml}
                    </div>
                `;
            } else {
                content.innerHTML = '<div class="alert alert-danger">Drug not found.</div>';
            }
        })
        .catch(() => {
            content.innerHTML = '<div class="alert alert-danger">Failed to load drug details.</div>';
        });
}

function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

function formatDate(dateStr) {
    if (!dateStr) return '—';
    const d = new Date(dateStr + 'T00:00:00');
    return d.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
}

function formatDateTime(dateTimeStr) {
    if (!dateTimeStr) return '—';
    const d = new Date(dateTimeStr);
    return d.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
}

function formatCurrency(amount) {
    return '#' + parseFloat(amount || 0).toFixed(2);
}

function getStatusBadge(status) {
    const map = {
        available: 'success', low_stock: 'warning', out_of_stock: 'danger',
        expired: 'secondary', discontinued: 'dark', stock_in: 'info', stock_out: 'warning'
    };
    const cls = map[status] || 'primary';
    const label = status.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
    return `<span class="badge badge-${cls}">${label}</span>`;
}

document.querySelectorAll('.modal-overlay').forEach(el => {
    el.addEventListener('click', function(e) {
        if (e.target === this) this.classList.remove('show');
    });
});
</script>

<?php include_once __DIR__ . '/../includes/footer.php'; ?>
