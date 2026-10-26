<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('admin');

$pageTitle = 'User Management';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $_SESSION['error'] = 'Invalid security token.';
        redirect('admin/users.php');
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $firstName = trim($_POST['first_name'] ?? '');
        $lastName = trim($_POST['last_name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $roleId = (int)($_POST['role_id'] ?? 0);
        $status = $_POST['status'] ?? 'active';

        if (empty($username) || empty($email) || empty($password) || empty($firstName) || empty($lastName) || empty($roleId)) {
            $_SESSION['error'] = 'Please fill in all required fields.';
            redirect('admin/users.php');
        }

        if (!validateEmail($email)) {
            $_SESSION['error'] = 'Invalid email address.';
            redirect('admin/users.php');
        }

        try {
            $stmt = db()->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
            $stmt->execute([$username, $email]);
            if ($stmt->fetch()) {
                $_SESSION['error'] = 'Username or email already exists.';
                redirect('admin/users.php');
            }

            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = db()->prepare("
                INSERT INTO users (username, email, password, first_name, last_name, phone, role_id, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$username, $email, $hash, $firstName, $lastName, $phone, $roleId, $status]);
            $userId = db()->lastInsertId();

            logAudit(getCurrentUserId(), 'create', 'user', $userId, "Created user {$username}");
            $_SESSION['success'] = 'User created successfully.';
        } catch (PDOException $e) {
            $_SESSION['error'] = 'Failed to create user: ' . $e->getMessage();
        }
        redirect('admin/users.php');
    }

    if ($action === 'edit') {
        $id = (int)($_POST['id'] ?? 0);
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $firstName = trim($_POST['first_name'] ?? '');
        $lastName = trim($_POST['last_name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $roleId = (int)($_POST['role_id'] ?? 0);
        $status = $_POST['status'] ?? 'active';
        $newPassword = $_POST['new_password'] ?? '';

        if (empty($id) || empty($username) || empty($email) || empty($firstName) || empty($lastName) || empty($roleId)) {
            $_SESSION['error'] = 'Please fill in all required fields.';
            redirect('admin/users.php');
        }

        if (!validateEmail($email)) {
            $_SESSION['error'] = 'Invalid email address.';
            redirect('admin/users.php');
        }

        try {
            $stmt = db()->prepare("SELECT id FROM users WHERE (username = ? OR email = ?) AND id != ?");
            $stmt->execute([$username, $email, $id]);
            if ($stmt->fetch()) {
                $_SESSION['error'] = 'Username or email already taken.';
                redirect('admin/users.php');
            }

            if (!empty($newPassword)) {
                $hash = password_hash($newPassword, PASSWORD_DEFAULT);
                $stmt = db()->prepare("
                    UPDATE users SET username = ?, email = ?, password = ?, first_name = ?, last_name = ?, phone = ?, role_id = ?, status = ?
                    WHERE id = ?
                ");
                $stmt->execute([$username, $email, $hash, $firstName, $lastName, $phone, $roleId, $status, $id]);
            } else {
                $stmt = db()->prepare("
                    UPDATE users SET username = ?, email = ?, first_name = ?, last_name = ?, phone = ?, role_id = ?, status = ?
                    WHERE id = ?
                ");
                $stmt->execute([$username, $email, $firstName, $lastName, $phone, $roleId, $status, $id]);
            }

            logAudit(getCurrentUserId(), 'update', 'user', $id, "Updated user {$username}");
            $_SESSION['success'] = 'User updated successfully.';
        } catch (PDOException $e) {
            $_SESSION['error'] = 'Failed to update user: ' . $e->getMessage();
        }
        redirect('admin/users.php');
    }
}

// Handle GET actions
if (isset($_GET['delete'])) {
    if (!isset($_GET['confirm']) || $_GET['confirm'] !== 'yes') {
        $_SESSION['warning'] = 'Are you sure you want to delete this user? <a href="?delete=' . (int)$_GET['delete'] . '&confirm=yes" class="alert-link">Yes, delete</a>';
        redirect('admin/users.php');
    }

    $id = (int)$_GET['delete'];
    try {
        $stmt = db()->prepare("SELECT username FROM users WHERE id = ?");
        $stmt->execute([$id]);
        $user = $stmt->fetch();

        if (!$user) {
            $_SESSION['error'] = 'User not found.';
            redirect('admin/users.php');
        }

        if ($id == getCurrentUserId()) {
            $_SESSION['error'] = 'You cannot delete your own account.';
            redirect('admin/users.php');
        }

        db()->prepare("DELETE FROM users WHERE id = ?")->execute([$id]);
        logAudit(getCurrentUserId(), 'delete', 'user', $id, "Deleted user {$user['username']}");
        $_SESSION['success'] = 'User deleted successfully.';
    } catch (PDOException $e) {
        $_SESSION['error'] = 'Failed to delete user: ' . $e->getMessage();
    }
    redirect('admin/users.php');
}

if (isset($_GET['toggle'])) {
    $id = (int)$_GET['toggle'];
    try {
        $stmt = db()->prepare("SELECT id, username, status FROM users WHERE id = ?");
        $stmt->execute([$id]);
        $user = $stmt->fetch();

        if (!$user) {
            $_SESSION['error'] = 'User not found.';
            redirect('admin/users.php');
        }

        $newStatus = $user['status'] === 'active' ? 'inactive' : 'active';
        db()->prepare("UPDATE users SET status = ? WHERE id = ?")->execute([$newStatus, $id]);
        logAudit(getCurrentUserId(), 'toggle_status', 'user', $id, "Toggled user {$user['username']} status to {$newStatus}");
        $_SESSION['success'] = "User status changed to {$newStatus}.";
    } catch (PDOException $e) {
        $_SESSION['error'] = 'Failed to toggle user status: ' . $e->getMessage();
    }
    redirect('admin/users.php');
}

// Paginated users
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage = 15;
$offset = ($page - 1) * $perPage;

$countStmt = db()->query("SELECT COUNT(*) as total FROM users");
$total = $countStmt->fetch()['total'];
$totalPages = ceil($total / $perPage);

$users = db()->prepare("
    SELECT u.*, r.role_name
    FROM users u
    LEFT JOIN roles r ON u.role_id = r.id
    ORDER BY u.created_at DESC
    LIMIT ? OFFSET ?
");
$users->execute([$perPage, $offset]);
$userList = $users->fetchAll();

$roles = db()->query("SELECT * FROM roles ORDER BY role_name")->fetchAll();

include_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <h1 class="page-title">User Management</h1>
    <button class="btn btn-primary" onclick="openModal('addUserModal')">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
        </svg>
        Add User
    </button>
</div>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Username</th>
                        <th>Full Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Last Login</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($userList)): ?>
                    <tr><td colspan="7" class="text-center text-muted">No users found</td></tr>
                    <?php else: ?>
                    <?php foreach ($userList as $user): ?>
                    <tr>
                        <td><?= sanitize($user['username']) ?></td>
                        <td><?= sanitize($user['first_name'] . ' ' . $user['last_name']) ?></td>
                        <td><?= sanitize($user['email']) ?></td>
                        <td><?= sanitize($user['role_name']) ?></td>
                        <td><?= getStatusBadge($user['status']) ?></td>
                        <td><?= $user['last_login'] ? formatDateTime($user['last_login']) : 'Never' ?></td>
                        <td>
                            <div class="action-btns">
                                <button class="btn btn-sm btn-info" onclick='editUser(<?= json_encode($user) ?>)'>
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                                    </svg>
                                </button>
                                <a href="?toggle=<?= $user['id'] ?>" class="btn btn-sm btn-secondary" onclick="return confirm('Toggle status for <?= sanitize($user['username']) ?>?')">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <rect x="1" y="5" width="22" height="14" rx="7" ry="7"/><circle cx="8" cy="12" r="3.5" fill="<?= $user['status'] === 'active' ? 'currentColor' : 'none' ?>"/>
                                    </svg>
                                </a>
                                <a href="?delete=<?= $user['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete user <?= sanitize($user['username']) ?>? This cannot be undone.')">
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
        <?php
        $paginationData = [
            'data' => $userList,
            'current_page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'total_pages' => $totalPages,
            'offset' => $offset,
        ];
        echo renderPagination($paginationData);
        ?>
    </div>
    <?php endif; ?>
</div>

<!-- Add User Modal -->
<div class="modal" id="addUserModal">
    <div class="modal-backdrop" onclick="closeModal('addUserModal')"></div>
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">Add User</h3>
            <button class="modal-close" onclick="closeModal('addUserModal')">&times;</button>
        </div>
        <form method="POST" action="">
            <div class="modal-body">
                <input type="hidden" name="csrf_token" value="<?= getCsrfToken() ?>">
                <input type="hidden" name="action" value="create">
                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label for="username">Username <span class="text-danger">*</span></label>
                        <input type="text" id="username" name="username" class="form-control" required>
                    </div>
                    <div class="form-group col-md-6">
                        <label for="email">Email <span class="text-danger">*</span></label>
                        <input type="email" id="email" name="email" class="form-control" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label for="password">Password <span class="text-danger">*</span></label>
                        <input type="password" id="password" name="password" class="form-control" required>
                    </div>
                    <div class="form-group col-md-6">
                        <label for="role_id">Role <span class="text-danger">*</span></label>
                        <select id="role_id" name="role_id" class="form-control" required>
                            <option value="">Select Role</option>
                            <?php foreach ($roles as $role): ?>
                            <option value="<?= $role['id'] ?>"><?= sanitize($role['role_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label for="first_name">First Name <span class="text-danger">*</span></label>
                        <input type="text" id="first_name" name="first_name" class="form-control" required>
                    </div>
                    <div class="form-group col-md-6">
                        <label for="last_name">Last Name <span class="text-danger">*</span></label>
                        <input type="text" id="last_name" name="last_name" class="form-control" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label for="phone">Phone</label>
                        <input type="text" id="phone" name="phone" class="form-control">
                    </div>
                    <div class="form-group col-md-6">
                        <label for="status">Status</label>
                        <select id="add_status" name="status" class="form-control">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                            <option value="suspended">Suspended</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addUserModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Create User</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit User Modal -->
<div class="modal" id="editUserModal">
    <div class="modal-backdrop" onclick="closeModal('editUserModal')"></div>
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">Edit User</h3>
            <button class="modal-close" onclick="closeModal('editUserModal')">&times;</button>
        </div>
        <form method="POST" action="">
            <div class="modal-body">
                <input type="hidden" name="csrf_token" value="<?= getCsrfToken() ?>">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="edit_id" value="">
                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label for="edit_username">Username <span class="text-danger">*</span></label>
                        <input type="text" id="edit_username" name="username" class="form-control" required>
                    </div>
                    <div class="form-group col-md-6">
                        <label for="edit_email">Email <span class="text-danger">*</span></label>
                        <input type="email" id="edit_email" name="email" class="form-control" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label for="edit_first_name">First Name <span class="text-danger">*</span></label>
                        <input type="text" id="edit_first_name" name="first_name" class="form-control" required>
                    </div>
                    <div class="form-group col-md-6">
                        <label for="edit_last_name">Last Name <span class="text-danger">*</span></label>
                        <input type="text" id="edit_last_name" name="last_name" class="form-control" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label for="edit_phone">Phone</label>
                        <input type="text" id="edit_phone" name="phone" class="form-control">
                    </div>
                    <div class="form-group col-md-6">
                        <label for="edit_role_id">Role <span class="text-danger">*</span></label>
                        <select id="edit_role_id" name="role_id" class="form-control" required>
                            <option value="">Select Role</option>
                            <?php foreach ($roles as $role): ?>
                            <option value="<?= $role['id'] ?>"><?= sanitize($role['role_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label for="edit_status">Status</label>
                        <select id="edit_status" name="status" class="form-control">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                            <option value="suspended">Suspended</option>
                        </select>
                    </div>
                    <div class="form-group col-md-6">
                        <label for="edit_new_password">New Password (leave blank to keep current)</label>
                        <input type="password" id="edit_new_password" name="new_password" class="form-control">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('editUserModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Update User</button>
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

function editUser(user) {
    document.getElementById('edit_id').value = user.id;
    document.getElementById('edit_username').value = user.username;
    document.getElementById('edit_email').value = user.email;
    document.getElementById('edit_first_name').value = user.first_name;
    document.getElementById('edit_last_name').value = user.last_name;
    document.getElementById('edit_phone').value = user.phone || '';
    document.getElementById('edit_role_id').value = user.role_id;
    document.getElementById('edit_status').value = user.status;
    openModal('editUserModal');
}
</script>

<?php include_once __DIR__ . '/../includes/footer.php'; ?>
