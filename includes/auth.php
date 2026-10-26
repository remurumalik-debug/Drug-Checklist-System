<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/functions.php';

function loginUser($username, $password) {
    try {
        $stmt = db()->prepare("
            SELECT u.*, r.role_name FROM users u
            JOIN roles r ON u.role_id = r.id
            WHERE u.username = ? AND u.status = 'active'
        ");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['full_name'] = $user['first_name'] . ' ' . $user['last_name'];
            $_SESSION['role'] = $user['role_name'];
            $_SESSION['role_id'] = $user['role_id'];
            $_SESSION['logged_in'] = true;
            $_SESSION['last_activity'] = time();

            // Update last login
            db()->prepare("UPDATE users SET last_login = NOW() WHERE id = ?")->execute([$user['id']]);

            logAudit($user['id'], 'login', 'user', $user['id'], "User {$user['username']} logged in");

            if (password_needs_rehash($user['password'], PASSWORD_DEFAULT)) {
                $newHash = password_hash($password, PASSWORD_DEFAULT);
                db()->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$newHash, $user['id']]);
            }

            return true;
        }
        return false;
    } catch (PDOException $e) {
        error_log("Login error: " . $e->getMessage());
        return false;
    }
}

function isLoggedIn() {
    return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
}

function requireLogin() {
    if (!isLoggedIn()) {
        $_SESSION['error'] = 'Please login to continue.';
        redirect('login.php');
    }
    checkSessionTimeout();
}

function checkSessionTimeout() {
    $timeout = (int)getSetting('session_timeout', 3600);
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $timeout)) {
        session_unset();
        session_destroy();
        $_SESSION['error'] = 'Session expired. Please login again.';
        redirect('login.php');
    }
    $_SESSION['last_activity'] = time();
}

function hasRole($roles) {
    if (!isLoggedIn()) return false;
    if (is_array($roles)) {
        return in_array($_SESSION['role'], $roles);
    }
    return $_SESSION['role'] === $roles;
}

function requireRole($roles) {
    requireLogin();
    if (!hasRole($roles)) {
        $_SESSION['error'] = 'You do not have permission to access this page.';
        redirect('dashboard.php');
    }
}

function getCurrentUserId() {
    return $_SESSION['user_id'] ?? 0;
}

function getCurrentUserRole() {
    return $_SESSION['role'] ?? '';
}

function logoutUser() {
    if (isset($_SESSION['user_id'])) {
        logAudit($_SESSION['user_id'], 'logout', 'user', $_SESSION['user_id'], "User {$_SESSION['username']} logged out");
    }
    session_unset();
    session_destroy();
}

function getDashboardRedirect() {
    $role = getCurrentUserRole();
    $map = [
        'admin' => 'admin/index.php',
        'pharmacist' => 'pharmacist/index.php',
        'staff' => 'staff/index.php',
    ];
    return $map[$role] ?? 'login.php';
}
