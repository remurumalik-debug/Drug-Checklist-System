<?php
require_once __DIR__ . '/includes/auth.php';
requireLogin();

$pageTitle = 'My Profile';

$userId = getCurrentUserId();

$stmt = db()->prepare("
    SELECT u.*, r.role_name FROM users u
    JOIN roles r ON u.role_id = r.id
    WHERE u.id = ?
");
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user) {
    $_SESSION['error'] = 'User not found.';
    redirect('dashboard.php');
}

$activeTab = $_GET['tab'] ?? 'personal';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $_SESSION['error'] = 'Invalid security token.';
        redirect('profile.php');
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
        $firstName = trim($_POST['first_name'] ?? '');
        $lastName = trim($_POST['last_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');

        if (empty($firstName) || empty($lastName) || empty($email)) {
            $_SESSION['error'] = 'First name, last name, and email are required.';
            $activeTab = 'personal';
            goto render;
        }

        if (!validateEmail($email)) {
            $_SESSION['error'] = 'Invalid email address.';
            $activeTab = 'personal';
            goto render;
        }

        try {
            $stmt = db()->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $stmt->execute([$email, $userId]);
            if ($stmt->fetch()) {
                $_SESSION['error'] = 'Email address is already taken.';
                $activeTab = 'personal';
                goto render;
            }

            $stmt = db()->prepare("
                UPDATE users SET first_name = ?, last_name = ?, email = ?, phone = ?
                WHERE id = ?
            ");
            $stmt->execute([$firstName, $lastName, $email, $phone, $userId]);

            $_SESSION['full_name'] = $firstName . ' ' . $lastName;

            logAudit($userId, 'update_profile', 'user', $userId, "Updated profile information");
            regenerateCsrfToken();
            $_SESSION['success'] = 'Profile updated successfully.';
        } catch (PDOException $e) {
            $_SESSION['error'] = 'Failed to update profile.';
        }
        redirect('profile.php');
    }

    if ($action === 'change_password') {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
            $_SESSION['error'] = 'All password fields are required.';
            $activeTab = 'password';
            goto render;
        }

        if (!password_verify($currentPassword, $user['password'])) {
            $_SESSION['error'] = 'Current password is incorrect.';
            $activeTab = 'password';
            goto render;
        }

        if (strlen($newPassword) < 8) {
            $_SESSION['error'] = 'New password must be at least 8 characters.';
            $activeTab = 'password';
            goto render;
        }

        if ($newPassword !== $confirmPassword) {
            $_SESSION['error'] = 'New password and confirmation do not match.';
            $activeTab = 'password';
            goto render;
        }

        try {
            $hash = password_hash($newPassword, PASSWORD_DEFAULT);
            $stmt = db()->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->execute([$hash, $userId]);

            logAudit($userId, 'change_password', 'user', $userId, "Changed password");
            regenerateCsrfToken();
            $_SESSION['success'] = 'Password changed successfully.';
        } catch (PDOException $e) {
            $_SESSION['error'] = 'Failed to change password.';
        }
        redirect('profile.php');
    }
}

render:

$initial = strtoupper(substr($user['first_name'], 0, 1));
$fullName = $user['first_name'] . ' ' . $user['last_name'];

include_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <h1 class="page-title">My Profile</h1>
</div>

<div class="profile-header">
    <div class="profile-avatar"><?= sanitize($initial) ?></div>
    <div class="profile-info">
        <h2><?= sanitize($fullName) ?></h2>
        <p><?= sanitize($user['email']) ?></p>
        <p><?= sanitize($user['role_name']) ?></p>
    </div>
</div>

<div class="profile-tabs">
    <button class="profile-tab <?= $activeTab === 'personal' ? 'active' : '' ?>" onclick="switchTab('personal')">
        Personal Information
    </button>
    <button class="profile-tab <?= $activeTab === 'password' ? 'active' : '' ?>" onclick="switchTab('password')">
        Change Password
    </button>
</div>

<div id="tab-personal" class="tab-content <?= $activeTab === 'personal' ? 'active' : '' ?>">
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Personal Information</h3>
        </div>
        <form method="POST" action="">
            <div class="card-body">
                <input type="hidden" name="csrf_token" value="<?= getCsrfToken() ?>">
                <input type="hidden" name="action" value="update_profile">

                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label class="form-label" for="first_name">First Name <span class="required">*</span></label>
                        <input type="text" id="first_name" name="first_name" class="form-control"
                               value="<?= sanitize($user['first_name']) ?>" required>
                    </div>
                    <div class="form-group col-md-6">
                        <label class="form-label" for="last_name">Last Name <span class="required">*</span></label>
                        <input type="text" id="last_name" name="last_name" class="form-control"
                               value="<?= sanitize($user['last_name']) ?>" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label class="form-label" for="email">Email Address <span class="required">*</span></label>
                        <input type="email" id="email" name="email" class="form-control"
                               value="<?= sanitize($user['email']) ?>" required>
                    </div>
                    <div class="form-group col-md-6">
                        <label class="form-label" for="phone">Phone Number</label>
                        <input type="text" id="phone" name="phone" class="form-control"
                               value="<?= sanitize($user['phone'] ?? '') ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Role</label>
                    <input type="text" class="form-control" value="<?= sanitize($user['role_name']) ?>" disabled>
                    <small class="form-text text-muted">Contact an administrator to change your role.</small>
                </div>

                <div class="form-group">
                    <label class="form-label">Last Login</label>
                    <input type="text" class="form-control"
                           value="<?= $user['last_login'] ? formatDateTime($user['last_login']) : 'Never' ?>" disabled>
                </div>
            </div>
            <div class="card-footer">
                <button type="submit" class="btn btn-primary">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/>
                        <polyline points="17 21 17 13 7 13 7 21"/>
                        <polyline points="7 3 7 8 15 8"/>
                    </svg>
                    Save Changes
                </button>
            </div>
        </form>
    </div>
</div>

<div id="tab-password" class="tab-content <?= $activeTab === 'password' ? 'active' : '' ?>">
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Change Password</h3>
        </div>
        <form method="POST" action="">
            <div class="card-body">
                <input type="hidden" name="csrf_token" value="<?= getCsrfToken() ?>">
                <input type="hidden" name="action" value="change_password">

                <div class="form-group">
                    <label class="form-label" for="current_password">Current Password <span class="required">*</span></label>
                    <input type="password" id="current_password" name="current_password" class="form-control" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="new_password">New Password <span class="required">*</span></label>
                    <input type="password" id="new_password" name="new_password" class="form-control" required minlength="8">
                    <small class="form-text text-muted">Must be at least 8 characters.</small>
                </div>

                <div class="form-group">
                    <label class="form-label" for="confirm_password">Confirm New Password <span class="required">*</span></label>
                    <input type="password" id="confirm_password" name="confirm_password" class="form-control" required minlength="8">
                </div>
            </div>
            <div class="card-footer">
                <button type="submit" class="btn btn-primary">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                        <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                    </svg>
                    Update Password
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function switchTab(tab) {
    document.querySelectorAll('.tab-content').forEach(function(el) {
        el.classList.remove('active');
    });
    document.querySelectorAll('.profile-tab').forEach(function(el) {
        el.classList.remove('active');
    });
    document.getElementById('tab-' + tab).classList.add('active');
    event.target.classList.add('active');
    history.replaceState(null, null, '?tab=' + tab);
}
</script>

<?php include_once __DIR__ . '/includes/footer.php'; ?>
