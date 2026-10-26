<?php
require_once __DIR__ . '/includes/auth.php';
requireLogin();

$redirect = getDashboardRedirect();
redirect($redirect);
