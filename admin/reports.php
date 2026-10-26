<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('admin');

$pageTitle = 'Reports Dashboard';
include_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <h1 class="page-title">Reports Dashboard</h1>
</div>

<div class="row reports-grid">
    <div class="col-md-4 col-lg-3">
        <a href="<?= BASE_URL ?>reports/generate.php?type=inventory" class="card report-card">
            <div class="report-card-icon" style="background: #e3f2fd; color: #1976d2;">
                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><rect x="8" y="2" width="8" height="4" rx="1" ry="1"/>
                </svg>
            </div>
            <div class="report-card-body">
                <h3 class="report-card-title">Inventory Report</h3>
                <p class="report-card-desc">Complete inventory status of all drugs in the system</p>
            </div>
        </a>
    </div>

    <div class="col-md-4 col-lg-3">
        <a href="<?= BASE_URL ?>reports/generate.php?type=expired" class="card report-card">
            <div class="report-card-icon" style="background: #fce4ec; color: #c62828;">
                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/>
                </svg>
            </div>
            <div class="report-card-body">
                <h3 class="report-card-title">Expired Drugs Report</h3>
                <p class="report-card-desc">List of all expired and soon-to-expire drugs</p>
            </div>
        </a>
    </div>

    <div class="col-md-4 col-lg-3">
        <a href="<?= BASE_URL ?>reports/generate.php?type=low_stock" class="card report-card">
            <div class="report-card-icon" style="background: #fff3e0; color: #e65100;">
                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>
                </svg>
            </div>
            <div class="report-card-body">
                <h3 class="report-card-title">Low Stock Report</h3>
                <p class="report-card-desc">Drugs that are running low on inventory</p>
            </div>
        </a>
    </div>

    <div class="col-md-4 col-lg-3">
        <a href="<?= BASE_URL ?>reports/generate.php?type=stock_movement" class="card report-card">
            <div class="report-card-icon" style="background: #e8f5e9; color: #2e7d32;">
                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
                </svg>
            </div>
            <div class="report-card-body">
                <h3 class="report-card-title">Stock Movement Report</h3>
                <p class="report-card-desc">All stock in, out, and adjustment transactions</p>
            </div>
        </a>
    </div>

    <div class="col-md-4 col-lg-3">
        <a href="<?= BASE_URL ?>reports/generate.php?type=daily_checklist" class="card report-card">
            <div class="report-card-icon" style="background: #f3e5f5; color: #7b1fa2;">
                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>
                </svg>
            </div>
            <div class="report-card-body">
                <h3 class="report-card-title">Daily Checklist Report</h3>
                <p class="report-card-desc">Today's drug checklist records</p>
            </div>
        </a>
    </div>

    <div class="col-md-4 col-lg-3">
        <a href="<?= BASE_URL ?>reports/generate.php?type=monthly_checklist" class="card report-card">
            <div class="report-card-icon" style="background: #e0f2f1; color: #00695c;">
                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>
                </svg>
            </div>
            <div class="report-card-body">
                <h3 class="report-card-title">Monthly Checklist Report</h3>
                <p class="report-card-desc">Monthly summary of drug checklist activities</p>
            </div>
        </a>
    </div>

    <div class="col-md-4 col-lg-3">
        <a href="<?= BASE_URL ?>reports/generate.php?type=user_activities" class="card report-card">
            <div class="report-card-icon" style="background: #fff8e1; color: #f57f17;">
                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                </svg>
            </div>
            <div class="report-card-body">
                <h3 class="report-card-title">User Activities Report</h3>
                <p class="report-card-desc">User login and system activity logs</p>
            </div>
        </a>
    </div>

    <div class="col-md-4 col-lg-3">
        <a href="<?= BASE_URL ?>reports/generate.php?type=audit_logs" class="card report-card">
            <div class="report-card-icon" style="background: #fbe9e7; color: #bf360c;">
                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
                </svg>
            </div>
            <div class="report-card-body">
                <h3 class="report-card-title">Audit Logs Report</h3>
                <p class="report-card-desc">Complete audit trail of all system activities</p>
            </div>
        </a>
    </div>
</div>

<?php include_once __DIR__ . '/../includes/footer.php'; ?>
