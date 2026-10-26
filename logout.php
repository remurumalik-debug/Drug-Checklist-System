<?php
require_once __DIR__ . '/includes/auth.php';
logoutUser();
$_SESSION['success'] = 'You have been logged out successfully.';
redirect('login.php');
