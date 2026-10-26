<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('admin');

$pageTitle = 'System Settings';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $_SESSION['error'] = 'Invalid security token.';
        redirect('admin/settings.php');
    }

    $settings = [
        'system_name' => trim($_POST['system_name'] ?? ''),
        'session_timeout' => (int)($_POST['session_timeout'] ?? 3600),
        'expiry_alert_days' => (int)($_POST['expiry_alert_days'] ?? 30),
        'low_stock_threshold' => (int)($_POST['low_stock_threshold'] ?? 10),
        'timezone' => trim($_POST['timezone'] ?? 'Asia/Manila'),
        'currency' => trim($_POST['currency'] ?? '#'),
        'company_address' => trim($_POST['company_address'] ?? ''),
        'company_phone' => trim($_POST['company_phone'] ?? ''),
    ];

    try {
        $stmt = db()->prepare("
            INSERT INTO system_settings (setting_key, setting_value, updated_by)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_by = VALUES(updated_by)
        ");

        foreach ($settings as $key => $value) {
            $stmt->execute([$key, $value, getCurrentUserId()]);
        }

        logAudit(getCurrentUserId(), 'update', 'settings', 0, 'Updated system settings');
        $_SESSION['success'] = 'System settings updated successfully.';
    } catch (PDOException $e) {
        $_SESSION['error'] = 'Failed to update settings: ' . $e->getMessage();
    }
    redirect('admin/settings.php');
}

// Load current settings
$stmt = db()->query("SELECT setting_key, setting_value FROM system_settings");
$settings = [];
while ($row = $stmt->fetch()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

$timezones = [
    'Asia/Manila' => 'Asia/Manila (UTC+8)',
    'Asia/Singapore' => 'Asia/Singapore (UTC+8)',
    'Asia/Tokyo' => 'Asia/Tokyo (UTC+9)',
    'Asia/Hong_Kong' => 'Asia/Hong Kong (UTC+8)',
    'America/New_York' => 'America/New York (UTC-5)',
    'America/Chicago' => 'America/Chicago (UTC-6)',
    'America/Los_Angeles' => 'America/Los Angeles (UTC-8)',
    'Europe/London' => 'Europe/London (UTC+0)',
    'Europe/Berlin' => 'Europe/Berlin (UTC+1)',
    'Australia/Sydney' => 'Australia/Sydney (UTC+11)',
    'Pacific/Auckland' => 'Pacific/Auckland (UTC+13)',
    'UTC' => 'UTC',
];

include_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <h1 class="page-title">System Settings</h1>
</div>

<div class="row">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">General Settings</h3>
            </div>
            <form method="POST" action="">
                <div class="card-body">
                    <input type="hidden" name="csrf_token" value="<?= getCsrfToken() ?>">

                    <div class="form-group">
                        <label for="system_name">System Name</label>
                        <input type="text" id="system_name" name="system_name" class="form-control"
                               value="<?= sanitize($settings['system_name'] ?? 'Drug Checklist Healthcare System') ?>">
                    </div>

                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label for="session_timeout">Session Timeout (seconds)</label>
                            <input type="number" id="session_timeout" name="session_timeout" class="form-control"
                                   value="<?= (int)($settings['session_timeout'] ?? 3600) ?>" min="60" step="60">
                            <small class="form-text text-muted">Default: 3600 (1 hour). Minimum: 60.</small>
                        </div>
                        <div class="form-group col-md-6">
                            <label for="expiry_alert_days">Expiry Alert Days</label>
                            <input type="number" id="expiry_alert_days" name="expiry_alert_days" class="form-control"
                                   value="<?= (int)($settings['expiry_alert_days'] ?? 30) ?>" min="1" max="365">
                            <small class="form-text text-muted">Days before expiry to trigger alert notifications.</small>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label for="low_stock_threshold">Low Stock Threshold</label>
                            <input type="number" id="low_stock_threshold" name="low_stock_threshold" class="form-control"
                                   value="<?= (int)($settings['low_stock_threshold'] ?? 10) ?>" min="1">
                            <small class="form-text text-muted">Default minimum stock level for alerts.</small>
                        </div>
                        <div class="form-group col-md-6">
                            <label for="currency">Currency Symbol</label>
                            <input type="text" id="currency" name="currency" class="form-control"
                                    value="<?= sanitize($settings['currency'] ?? '#') ?>" maxlength="10">
                            <small class="form-text text-muted">Symbol used for price display (e.g., #, $, €).</small>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="timezone">Timezone</label>
                        <select id="timezone" name="timezone" class="form-control">
                            <?php foreach ($timezones as $tz => $label): ?>
                            <option value="<?= $tz ?>" <?= ($settings['timezone'] ?? 'Asia/Manila') === $tz ? 'selected' : '' ?>><?= sanitize($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="company_address">Company Address</label>
                        <textarea id="company_address" name="company_address" class="form-control" rows="3"><?= sanitize($settings['company_address'] ?? '') ?></textarea>
                    </div>

                    <div class="form-group">
                        <label for="company_phone">Company Phone</label>
                        <input type="text" id="company_phone" name="company_phone" class="form-control"
                               value="<?= sanitize($settings['company_phone'] ?? '') ?>">
                    </div>
                </div>
                <div class="card-footer">
                    <button type="submit" class="btn btn-primary">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/>
                        </svg>
                        Save Settings
                    </button>
                </div>
            </form>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Settings Info</h3>
            </div>
            <div class="card-body">
                <div class="info-list">
                    <div class="info-item">
                        <strong>System Name</strong>
                        <p>Displayed in the browser title bar and reports.</p>
                    </div>
                    <div class="info-item">
                        <strong>Session Timeout</strong>
                        <p>How long before an inactive user is automatically logged out.</p>
                    </div>
                    <div class="info-item">
                        <strong>Expiry Alert Days</strong>
                        <p>Drugs expiring within this many days will trigger notifications to admins and pharmacists.</p>
                    </div>
                    <div class="info-item">
                        <strong>Low Stock Threshold</strong>
                        <p>The default minimum stock level. When quantity drops below this, a low stock alert is triggered.</p>
                    </div>
                    <div class="info-item">
                        <strong>Timezone</strong>
                        <p>Set the correct timezone for accurate date/time display and audit logs.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include_once __DIR__ . '/../includes/footer.php'; ?>
