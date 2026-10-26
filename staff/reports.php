<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('staff');

$pageTitle = 'Reports';
include_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <h1 class="page-title">Reports</h1>
    <p>View inventory and checklist reports (read-only)</p>
</div>

<div class="reports-grid">
    <a href="<?= BASE_URL ?>reports/generate.php?type=inventory" class="card report-card">
        <div class="card-body">
            <div class="stat-icon blue" style="margin-bottom:12px">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/>
                </svg>
            </div>
            <h3>Drug Inventory Report</h3>
            <p class="text-muted">Complete list of all drugs in inventory</p>
        </div>
    </a>
    <a href="<?= BASE_URL ?>reports/generate.php?type=low_stock" class="card report-card">
        <div class="card-body">
            <div class="stat-icon yellow" style="margin-bottom:12px">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
                </svg>
            </div>
            <h3>Low Stock Report</h3>
            <p class="text-muted">Drugs at or below minimum stock level</p>
        </div>
    </a>
    <a href="<?= BASE_URL ?>reports/generate.php?type=out_of_stock" class="card report-card">
        <div class="card-body">
            <div class="stat-icon red" style="margin-bottom:12px">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/>
                </svg>
            </div>
            <h3>Out of Stock Report</h3>
            <p class="text-muted">Drugs that are currently out of stock</p>
        </div>
    </a>
    <a href="<?= BASE_URL ?>reports/generate.php?type=expired" class="card report-card">
        <div class="card-body">
            <div class="stat-icon red" style="margin-bottom:12px">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M12 8v4l3 3m6-3a9 9 0 1 1-18 0 9 9 0 0 1 18 0z"/>
                </svg>
            </div>
            <h3>Expired Drugs Report</h3>
            <p class="text-muted">Drugs that have expired</p>
        </div>
    </a>
    <a href="<?= BASE_URL ?>reports/generate.php?type=daily_checklist" class="card report-card">
        <div class="card-body">
            <div class="stat-icon green" style="margin-bottom:12px">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>
                </svg>
            </div>
            <h3>Daily Checklist Report</h3>
            <p class="text-muted">Drug checklist records</p>
        </div>
    </a>
</div>

<style>
.reports-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(260px,1fr)); gap:20px; }
.report-card { text-decoration:none; color:inherit; transition:transform .2s,box-shadow .2s; }
.report-card:hover { transform:translateY(-2px); box-shadow:0 4px 12px rgba(0,0,0,0.1); }
.report-card h3 { font-size:1rem; font-weight:600; margin-bottom:4px; }
.report-card p { font-size:0.8125rem; }
</style>

<?php include_once __DIR__ . '/../includes/footer.php'; ?>
