<?php
require_once __DIR__ . '/includes/auth.php';

if (isLoggedIn()) {
    redirect('dashboard.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = 'Please enter both username and password.';
    } elseif (loginUser($username, $password)) {
        redirect('dashboard.php');
    } else {
        $error = 'Invalid username or password.';
    }
}

$pageTitle = 'Login';
include_once __DIR__ . '/includes/header.php';
?>

<div class="auth-card">
    <div class="auth-header">
        <div class="auth-logo">
            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M22 12h-4l-3 9L9 3l-3 9H2"/>
            </svg>
        </div>
        <h1>Drug Checklist System</h1>
        <p>Sign in to your account</p>
    </div>

    <?php if ($error): ?>
    <div class="alert alert-danger">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/>
        </svg>
        <span><?= sanitize($error) ?></span>
    </div>
    <?php endif; ?>

    <form method="POST" action="" data-validate>
        <div class="form-group">
            <label class="form-label" for="username">Username <span class="required">*</span></label>
            <input type="text" id="username" name="username" class="form-control" placeholder="Enter your username" required autofocus>
        </div>
        <div class="form-group">
            <label class="form-label" for="password">Password <span class="required">*</span></label>
            <input type="password" id="password" name="password" class="form-control" placeholder="Enter your password" required>
        </div>
        <button type="submit" class="btn btn-primary btn-lg" style="width:100%;">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>
            </svg>
            Sign In
        </button>
    </form>

</div>

<?php include_once __DIR__ . '/includes/footer.php'; ?>
