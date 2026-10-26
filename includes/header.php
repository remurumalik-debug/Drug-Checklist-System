<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title><?= isset($pageTitle) ? sanitize($pageTitle) . ' - ' : '' ?><?= sanitize(getSetting('system_name', 'Drug Checklist System')) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css">
    <?php if (isLoggedIn()): ?>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
    <?php endif; ?>
    <script>
        (function() {
            var theme = localStorage.getItem('theme');
            if (theme === 'dark' || (!theme && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                document.documentElement.classList.add('dark-will-load');
            }
        })();
    </script>
</head>
<body class="<?= isLoggedIn() ? getCurrentUserRole() : '' ?>">
<?php if (isLoggedIn()): ?>
<div class="app-container">
    <?php include_once __DIR__ . '/sidebar.php'; ?>
    <div class="main-content">
        <?php include_once __DIR__ . '/navbar.php'; ?>
        <div class="content-wrapper">
<?php else: ?>
<div class="auth-container">
<?php endif; ?>
