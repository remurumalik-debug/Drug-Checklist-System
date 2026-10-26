<?php
require_once __DIR__ . '/includes/auth.php';

if (isLoggedIn()) {
    redirect('dashboard.php');
} else {
    redirect('login.php');
}
