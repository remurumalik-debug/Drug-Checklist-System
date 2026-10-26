<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('admin');

$pageTitle = 'Drug Categories';

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $_SESSION['error'] = 'Invalid security token.';
        redirect('admin/categories.php');
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $name = trim($_POST['category_name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $status = $_POST['status'] ?? 'active';

        if (empty($name)) {
            $_SESSION['error'] = 'Category name is required.';
            redirect('admin/categories.php');
        }

        try {
            $stmt = db()->prepare("SELECT id FROM drug_categories WHERE category_name = ?");
            $stmt->execute([$name]);
            if ($stmt->fetch()) {
                $_SESSION['error'] = 'Category already exists.';
                redirect('admin/categories.php');
            }

            db()->prepare("INSERT INTO drug_categories (category_name, description, status) VALUES (?, ?, ?)")->execute([$name, $description, $status]);
            $catId = db()->lastInsertId();
            logAudit(getCurrentUserId(), 'create', 'category', $catId, "Created category {$name}");
            $_SESSION['success'] = 'Category created successfully.';
        } catch (PDOException $e) {
            $_SESSION['error'] = 'Failed to create category: ' . $e->getMessage();
        }
        redirect('admin/categories.php');
    }

    if ($action === 'edit') {
        $id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['category_name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $status = $_POST['status'] ?? 'active';

        if (empty($id) || empty($name)) {
            $_SESSION['error'] = 'Category name is required.';
            redirect('admin/categories.php');
        }

        try {
            $stmt = db()->prepare("SELECT id FROM drug_categories WHERE category_name = ? AND id != ?");
            $stmt->execute([$name, $id]);
            if ($stmt->fetch()) {
                $_SESSION['error'] = 'Category name already taken.';
                redirect('admin/categories.php');
            }

            db()->prepare("UPDATE drug_categories SET category_name = ?, description = ?, status = ? WHERE id = ?")->execute([$name, $description, $status, $id]);
            logAudit(getCurrentUserId(), 'update', 'category', $id, "Updated category {$name}");
            $_SESSION['success'] = 'Category updated successfully.';
        } catch (PDOException $e) {
            $_SESSION['error'] = 'Failed to update category: ' . $e->getMessage();
        }
        redirect('admin/categories.php');
    }
}

// Handle GET
if (isset($_GET['delete'])) {
    if (!isset($_GET['csrf_token']) || !verifyCsrfToken($_GET['csrf_token'])) {
        $_SESSION['error'] = 'Invalid security token.';
        redirect('admin/categories.php');
    }
    if (!isset($_GET['confirm']) || $_GET['confirm'] !== 'yes') {
        $_SESSION['warning'] = 'Are you sure you want to delete this category? <a href="?delete=' . (int)$_GET['delete'] . '&confirm=yes&csrf_token=' . getCsrfToken() . '" class="alert-link">Yes, delete</a>';
        redirect('admin/categories.php');
    }

    $id = (int)$_GET['delete'];
    try {
        $stmt = db()->prepare("SELECT category_name FROM drug_categories WHERE id = ?");
        $stmt->execute([$id]);
        $cat = $stmt->fetch();
        if (!$cat) {
            $_SESSION['error'] = 'Category not found.';
            redirect('admin/categories.php');
        }

        $stmt = db()->prepare("SELECT COUNT(*) as cnt FROM drugs WHERE category_id = ?");
        $stmt->execute([$id]);
        if ($stmt->fetch()['cnt'] > 0) {
            $_SESSION['error'] = 'Cannot delete category with existing drugs. Set it inactive instead.';
            redirect('admin/categories.php');
        }

        db()->prepare("DELETE FROM drug_categories WHERE id = ?")->execute([$id]);
        logAudit(getCurrentUserId(), 'delete', 'category', $id, "Deleted category {$cat['category_name']}");
        $_SESSION['success'] = 'Category deleted successfully.';
    } catch (PDOException $e) {
        $_SESSION['error'] = 'Failed to delete category: ' . $e->getMessage();
    }
    redirect('admin/categories.php');
}

if (isset($_GET['toggle'])) {
    if (!isset($_GET['csrf_token']) || !verifyCsrfToken($_GET['csrf_token'])) {
        $_SESSION['error'] = 'Invalid security token.';
        redirect('admin/categories.php');
    }
    $id = (int)$_GET['toggle'];
    try {
        $stmt = db()->prepare("SELECT id, category_name, status FROM drug_categories WHERE id = ?");
        $stmt->execute([$id]);
        $cat = $stmt->fetch();
        if (!$cat) {
            $_SESSION['error'] = 'Category not found.';
            redirect('admin/categories.php');
        }
        $newStatus = $cat['status'] === 'active' ? 'inactive' : 'active';
        db()->prepare("UPDATE drug_categories SET status = ? WHERE id = ?")->execute([$newStatus, $id]);
        logAudit(getCurrentUserId(), 'toggle_status', 'category', $id, "Toggled category {$cat['category_name']} to {$newStatus}");
        $_SESSION['success'] = "Category status changed to {$newStatus}.";
    } catch (PDOException $e) {
        $_SESSION['error'] = 'Failed to toggle category: ' . $e->getMessage();
    }
    redirect('admin/categories.php');
}

// Paginated categories
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage = 15;
$offset = ($page - 1) * $perPage;

$countStmt = db()->query("SELECT COUNT(*) as total FROM drug_categories");
$total = $countStmt->fetch()['total'];
$totalPages = ceil($total / $perPage);

$stmt = db()->prepare("
    SELECT c.*, (SELECT COUNT(*) FROM drugs d WHERE d.category_id = c.id) as drug_count
    FROM drug_categories c
    ORDER BY c.category_name ASC
    LIMIT ? OFFSET ?
");
$stmt->execute([$perPage, $offset]);
$categories = $stmt->fetchAll();

$paginationData = [
    'data' => $categories,
    'current_page' => $page,
    'per_page' => $perPage,
    'total' => $total,
    'total_pages' => $totalPages,
    'offset' => $offset,
];

include_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <h1 class="page-title">Drug Categories</h1>
    <button class="btn btn-primary" onclick="openModal('addCategoryModal')">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
        </svg>
        Add Category
    </button>
</div>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Category Name</th>
                        <th>Description</th>
                        <th>Status</th>
                        <th>Drugs Count</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($categories)): ?>
                    <tr><td colspan="5" class="text-center text-muted">No categories found</td></tr>
                    <?php else: ?>
                    <?php foreach ($categories as $cat): ?>
                    <tr>
                        <td><?= sanitize($cat['category_name']) ?></td>
                        <td><?= sanitize($cat['description'] ?? '-') ?></td>
                        <td><?= getStatusBadge($cat['status']) ?></td>
                        <td><span class="badge badge-info"><?= $cat['drug_count'] ?></span></td>
                        <td>
                            <div class="action-btns">
                                <button class="btn btn-sm btn-info" onclick='editCategory(<?= json_encode($cat) ?>)'>
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                                    </svg>
                                </button>
                                <a href="?toggle=<?= $cat['id'] ?>&csrf_token=<?= getCsrfToken() ?>" class="btn btn-sm btn-secondary" onclick="return confirm('Toggle status for <?= sanitize($cat['category_name']) ?>?')">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <rect x="1" y="5" width="22" height="14" rx="7" ry="7"/><circle cx="8" cy="12" r="3.5" fill="<?= $cat['status'] === 'active' ? 'currentColor' : 'none' ?>"/>
                                    </svg>
                                </a>
                                <a href="?delete=<?= $cat['id'] ?>&csrf_token=<?= getCsrfToken() ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete category <?= sanitize($cat['category_name']) ?>?')">
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

<!-- Add Category Modal -->
<div class="modal" id="addCategoryModal">
    <div class="modal-backdrop" onclick="closeModal('addCategoryModal')"></div>
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">Add Category</h3>
            <button class="modal-close" onclick="closeModal('addCategoryModal')">&times;</button>
        </div>
        <form method="POST" action="">
            <div class="modal-body">
                <input type="hidden" name="csrf_token" value="<?= getCsrfToken() ?>">
                <input type="hidden" name="action" value="create">
                <div class="form-group">
                    <label for="cat_name">Category Name <span class="text-danger">*</span></label>
                    <input type="text" id="cat_name" name="category_name" class="form-control" required>
                </div>
                <div class="form-group">
                    <label for="cat_desc">Description</label>
                    <textarea id="cat_desc" name="description" class="form-control" rows="3"></textarea>
                </div>
                <div class="form-group">
                    <label for="cat_status">Status</label>
                    <select id="cat_status" name="status" class="form-control">
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addCategoryModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Create Category</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Category Modal -->
<div class="modal" id="editCategoryModal">
    <div class="modal-backdrop" onclick="closeModal('editCategoryModal')"></div>
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">Edit Category</h3>
            <button class="modal-close" onclick="closeModal('editCategoryModal')">&times;</button>
        </div>
        <form method="POST" action="">
            <div class="modal-body">
                <input type="hidden" name="csrf_token" value="<?= getCsrfToken() ?>">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="edit_cat_id" value="">
                <div class="form-group">
                    <label for="edit_cat_name">Category Name <span class="text-danger">*</span></label>
                    <input type="text" id="edit_cat_name" name="category_name" class="form-control" required>
                </div>
                <div class="form-group">
                    <label for="edit_cat_desc">Description</label>
                    <textarea id="edit_cat_desc" name="description" class="form-control" rows="3"></textarea>
                </div>
                <div class="form-group">
                    <label for="edit_cat_status">Status</label>
                    <select id="edit_cat_status" name="status" class="form-control">
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('editCategoryModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Update Category</button>
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

function editCategory(cat) {
    document.getElementById('edit_cat_id').value = cat.id;
    document.getElementById('edit_cat_name').value = cat.category_name;
    document.getElementById('edit_cat_desc').value = cat.description || '';
    document.getElementById('edit_cat_status').value = cat.status;
    openModal('editCategoryModal');
}
</script>

<?php include_once __DIR__ . '/../includes/footer.php'; ?>
