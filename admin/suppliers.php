<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('admin');

$pageTitle = 'Supplier Management';

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $_SESSION['error'] = 'Invalid security token.';
        redirect('admin/suppliers.php');
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $supplier_name = trim($_POST['supplier_name'] ?? '');
        $contact_person = trim($_POST['contact_person'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $city = trim($_POST['city'] ?? '');
        $state = trim($_POST['state'] ?? '');
        $zip_code = trim($_POST['zip_code'] ?? '');
        $country = trim($_POST['country'] ?? '');
        $status = $_POST['status'] ?? 'active';

        if (empty($supplier_name)) {
            $_SESSION['error'] = 'Supplier name is required.';
            redirect('admin/suppliers.php');
        }

        if (!empty($email) && !validateEmail($email)) {
            $_SESSION['error'] = 'Invalid email address.';
            redirect('admin/suppliers.php');
        }

        try {
            $stmt = db()->prepare("SELECT id FROM suppliers WHERE supplier_name = ?");
            $stmt->execute([$supplier_name]);
            if ($stmt->fetch()) {
                $_SESSION['error'] = 'Supplier already exists.';
                redirect('admin/suppliers.php');
            }

            db()->prepare("INSERT INTO suppliers (supplier_name, contact_person, email, phone, address, city, state, zip_code, country, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)")->execute([$supplier_name, $contact_person, $email, $phone, $address, $city, $state, $zip_code, $country, $status]);
            $supplierId = db()->lastInsertId();
            logAudit(getCurrentUserId(), 'create', 'supplier', $supplierId, "Created supplier {$supplier_name}");
            $_SESSION['success'] = 'Supplier created successfully.';
        } catch (PDOException $e) {
            $_SESSION['error'] = 'Failed to create supplier: ' . $e->getMessage();
        }
        redirect('admin/suppliers.php');
    }

    if ($action === 'edit') {
        $id = (int)($_POST['id'] ?? 0);
        $supplier_name = trim($_POST['supplier_name'] ?? '');
        $contact_person = trim($_POST['contact_person'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $city = trim($_POST['city'] ?? '');
        $state = trim($_POST['state'] ?? '');
        $zip_code = trim($_POST['zip_code'] ?? '');
        $country = trim($_POST['country'] ?? '');
        $status = $_POST['status'] ?? 'active';

        if (empty($id) || empty($supplier_name)) {
            $_SESSION['error'] = 'Supplier name is required.';
            redirect('admin/suppliers.php');
        }

        if (!empty($email) && !validateEmail($email)) {
            $_SESSION['error'] = 'Invalid email address.';
            redirect('admin/suppliers.php');
        }

        try {
            $stmt = db()->prepare("SELECT id FROM suppliers WHERE supplier_name = ? AND id != ?");
            $stmt->execute([$supplier_name, $id]);
            if ($stmt->fetch()) {
                $_SESSION['error'] = 'Supplier name already taken.';
                redirect('admin/suppliers.php');
            }

            db()->prepare("UPDATE suppliers SET supplier_name = ?, contact_person = ?, email = ?, phone = ?, address = ?, city = ?, state = ?, zip_code = ?, country = ?, status = ? WHERE id = ?")->execute([$supplier_name, $contact_person, $email, $phone, $address, $city, $state, $zip_code, $country, $status, $id]);
            logAudit(getCurrentUserId(), 'update', 'supplier', $id, "Updated supplier {$supplier_name}");
            $_SESSION['success'] = 'Supplier updated successfully.';
        } catch (PDOException $e) {
            $_SESSION['error'] = 'Failed to update supplier: ' . $e->getMessage();
        }
        redirect('admin/suppliers.php');
    }
}

// Handle GET - Delete
if (isset($_GET['delete'])) {
    if (!isset($_GET['csrf_token']) || !verifyCsrfToken($_GET['csrf_token'])) {
        $_SESSION['error'] = 'Invalid security token.';
        redirect('admin/suppliers.php');
    }
    if (!isset($_GET['confirm']) || $_GET['confirm'] !== 'yes') {
        $_SESSION['warning'] = 'Are you sure you want to delete this supplier? <a href="?delete=' . (int)$_GET['delete'] . '&confirm=yes&csrf_token=' . getCsrfToken() . '" class="alert-link">Yes, delete</a>';
        redirect('admin/suppliers.php');
    }

    $id = (int)$_GET['delete'];
    try {
        $stmt = db()->prepare("SELECT supplier_name FROM suppliers WHERE id = ?");
        $stmt->execute([$id]);
        $supplier = $stmt->fetch();
        if (!$supplier) {
            $_SESSION['error'] = 'Supplier not found.';
            redirect('admin/suppliers.php');
        }

        db()->prepare("DELETE FROM suppliers WHERE id = ?")->execute([$id]);
        logAudit(getCurrentUserId(), 'delete', 'supplier', $id, "Deleted supplier {$supplier['supplier_name']}");
        $_SESSION['success'] = 'Supplier deleted successfully.';
    } catch (PDOException $e) {
        $_SESSION['error'] = 'Failed to delete supplier: ' . $e->getMessage();
    }
    redirect('admin/suppliers.php');
}

// Handle GET - Toggle Status
if (isset($_GET['toggle'])) {
    if (!isset($_GET['csrf_token']) || !verifyCsrfToken($_GET['csrf_token'])) {
        $_SESSION['error'] = 'Invalid security token.';
        redirect('admin/suppliers.php');
    }
    $id = (int)$_GET['toggle'];
    try {
        $stmt = db()->prepare("SELECT id, supplier_name, status FROM suppliers WHERE id = ?");
        $stmt->execute([$id]);
        $supplier = $stmt->fetch();
        if (!$supplier) {
            $_SESSION['error'] = 'Supplier not found.';
            redirect('admin/suppliers.php');
        }
        $newStatus = $supplier['status'] === 'active' ? 'inactive' : 'active';
        db()->prepare("UPDATE suppliers SET status = ? WHERE id = ?")->execute([$newStatus, $id]);
        logAudit(getCurrentUserId(), 'toggle_status', 'supplier', $id, "Toggled supplier {$supplier['supplier_name']} to {$newStatus}");
        $_SESSION['success'] = "Supplier status changed to {$newStatus}.";
    } catch (PDOException $e) {
        $_SESSION['error'] = 'Failed to toggle supplier: ' . $e->getMessage();
    }
    redirect('admin/suppliers.php');
}

// Search & Pagination
$search = trim($_GET['search'] ?? '');
$where = '';
$params = [];
if ($search !== '') {
    $where = "supplier_name LIKE ?";
    $params[] = "%{$search}%";
}

$paginationData = getPaginatedData('suppliers', $where, $params, 'supplier_name ASC', 15);
$suppliers = $paginationData['data'];

include_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <h1 class="page-title">Supplier Management</h1>
    <button class="btn btn-primary" onclick="openModal('addSupplierModal')">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
        </svg>
        Add Supplier
    </button>
</div>

<div class="card">
    <div class="card-header">
        <form method="GET" action="" class="filters-bar">
            <input type="text" name="search" class="form-control" placeholder="Search suppliers..." value="<?= sanitize($search) ?>">
            <button type="submit" class="btn btn-sm btn-primary">Search</button>
            <?php if ($search !== ''): ?>
                <a href="suppliers.php" class="btn btn-sm btn-outline-secondary">Clear</a>
            <?php endif; ?>
        </form>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Supplier Name</th>
                        <th>Contact Person</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>City</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($suppliers)): ?>
                    <tr><td colspan="8" class="text-center text-muted">No suppliers found</td></tr>
                    <?php else: ?>
                    <?php foreach ($suppliers as $i => $supplier): ?>
                    <tr>
                        <td><?= $paginationData['offset'] + $i + 1 ?></td>
                        <td><?= sanitize($supplier['supplier_name']) ?></td>
                        <td><?= sanitize($supplier['contact_person'] ?? '-') ?></td>
                        <td><?= sanitize($supplier['email'] ?? '-') ?></td>
                        <td><?= sanitize($supplier['phone'] ?? '-') ?></td>
                        <td><?= sanitize($supplier['city'] ?? '-') ?></td>
                        <td><?= getStatusBadge($supplier['status']) ?></td>
                        <td>
                            <div class="action-buttons">
                                <button class="action-btn edit" onclick='editSupplier(<?= json_encode($supplier) ?>)'>
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                                    </svg>
                                </button>
                                <a href="?toggle=<?= $supplier['id'] ?>&csrf_token=<?= getCsrfToken() ?>" class="action-btn" onclick="return confirm('Toggle status for <?= sanitize($supplier['supplier_name']) ?>?')">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <rect x="1" y="5" width="22" height="14" rx="7" ry="7"/><circle cx="8" cy="12" r="3.5" fill="<?= $supplier['status'] === 'active' ? 'currentColor' : 'none' ?>"/>
                                    </svg>
                                </a>
                                <a href="?delete=<?= $supplier['id'] ?>&csrf_token=<?= getCsrfToken() ?>" class="action-btn delete" onclick="return confirm('Delete supplier <?= sanitize($supplier['supplier_name']) ?>?')">
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
    <?php if ($paginationData['total_pages'] > 1): ?>
    <div class="card-footer">
        <?= renderPagination($paginationData) ?>
    </div>
    <?php endif; ?>
</div>

<!-- Add Supplier Modal -->
<div class="modal-overlay" id="addSupplierModal">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title">Add Supplier</h3>
            <button class="modal-close" onclick="closeModal('addSupplierModal')">&times;</button>
        </div>
        <form method="POST" action="">
            <div class="modal-body">
                <input type="hidden" name="csrf_token" value="<?= getCsrfToken() ?>">
                <input type="hidden" name="action" value="create">
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Supplier Name <span class="text-danger">*</span></label>
                        <input type="text" name="supplier_name" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Contact Person</label>
                        <input type="text" name="contact_person" class="form-control">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Phone</label>
                        <input type="text" name="phone" class="form-control">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Address</label>
                    <input type="text" name="address" class="form-control">
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">City</label>
                        <input type="text" name="city" class="form-control">
                    </div>
                    <div class="form-group">
                        <label class="form-label">State</label>
                        <input type="text" name="state" class="form-control">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Zip Code</label>
                        <input type="text" name="zip_code" class="form-control">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Country</label>
                        <input type="text" name="country" class="form-control">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-control">
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" onclick="closeModal('addSupplierModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Create Supplier</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Supplier Modal -->
<div class="modal-overlay" id="editSupplierModal">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title">Edit Supplier</h3>
            <button class="modal-close" onclick="closeModal('editSupplierModal')">&times;</button>
        </div>
        <form method="POST" action="">
            <div class="modal-body">
                <input type="hidden" name="csrf_token" value="<?= getCsrfToken() ?>">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="edit_supplier_id" value="">
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Supplier Name <span class="text-danger">*</span></label>
                        <input type="text" name="supplier_name" id="edit_supplier_name" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Contact Person</label>
                        <input type="text" name="contact_person" id="edit_contact_person" class="form-control">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" id="edit_email" class="form-control">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Phone</label>
                        <input type="text" name="phone" id="edit_phone" class="form-control">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Address</label>
                    <input type="text" name="address" id="edit_address" class="form-control">
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">City</label>
                        <input type="text" name="city" id="edit_city" class="form-control">
                    </div>
                    <div class="form-group">
                        <label class="form-label">State</label>
                        <input type="text" name="state" id="edit_state" class="form-control">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Zip Code</label>
                        <input type="text" name="zip_code" id="edit_zip_code" class="form-control">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Country</label>
                        <input type="text" name="country" id="edit_country" class="form-control">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Status</label>
                    <select name="status" id="edit_status" class="form-control">
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" onclick="closeModal('editSupplierModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Update Supplier</button>
            </div>
        </form>
    </div>
</div>

<script>
function openModal(id) {
    document.getElementById(id).classList.add('show');
}

function closeModal(id) {
    document.getElementById(id).classList.remove('show');
}

function editSupplier(s) {
    document.getElementById('edit_supplier_id').value = s.id;
    document.getElementById('edit_supplier_name').value = s.supplier_name;
    document.getElementById('edit_contact_person').value = s.contact_person || '';
    document.getElementById('edit_email').value = s.email || '';
    document.getElementById('edit_phone').value = s.phone || '';
    document.getElementById('edit_address').value = s.address || '';
    document.getElementById('edit_city').value = s.city || '';
    document.getElementById('edit_state').value = s.state || '';
    document.getElementById('edit_zip_code').value = s.zip_code || '';
    document.getElementById('edit_country').value = s.country || '';
    document.getElementById('edit_status').value = s.status;
    openModal('editSupplierModal');
}
</script>

<?php include_once __DIR__ . '/../includes/footer.php'; ?>
