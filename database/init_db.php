<?php
/**
 * Database Initialization Script
 * Run this script once to set up the database schema and seed data.
 * Access via: http://localhost/Drug-Checklist-System/database/init_db.php
 */

$host = getenv('DB_HOST') ?: 'localhost';
$username = getenv('DB_USER') ?: 'root';
$password = getenv('DB_PASS') ?: '';
$dbname = getenv('DB_NAME') ?: 'drug_checklist_system';

$error = '';
$success = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['init'])) {
    try {
        // Connect without database to create it
        $pdo = new PDO("mysql:host={$host};charset=utf8mb4", $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);

        // Read schema file
        $schema = file_get_contents(__DIR__ . '/schema.sql');
        if (!$schema) {
            throw new Exception('Could not read schema.sql file.');
        }

        // Execute schema (split by semicolons for multi-statement execution)
        $statements = array_filter(
            array_map('trim',
                explode(';', $schema)
            ),
            function($s) { return !empty($s) && !str_starts_with($s, '--'); }
        );

        foreach ($statements as $stmt) {
            $stmt = trim(preg_replace('/^--.*$/m', '', $stmt));
            if (!empty($stmt)) {
                $pdo->exec($stmt);
            }
        }

        $success[] = 'Database schema created successfully.';

        // Seed data
        $seedFile = __DIR__ . '/seed.sql';
        if (file_exists($seedFile)) {
            $seed = file_get_contents($seedFile);
            $seedLines = array_filter(
                array_map('trim',
                    explode(';', $seed)
                ),
                function($s) { return !empty($s) && !str_starts_with($s, '--') && !str_starts_with($s, 'USE'); }
            );
            foreach ($seedLines as $stmt) {
                $stmt = trim(preg_replace('/^--.*$/m', '', $stmt));
                if (!empty($stmt)) {
                    $pdo->exec($stmt);
                }
            }
            $success[] = 'Seed data inserted successfully.';
        }

        $success[] = 'Database initialized successfully!';
        $success[] = 'Login credentials:';
        $success[] = 'Admin: admin / Admin123!';
        $success[] = 'Pharmacist: pharmacist / Pharm123!';
        $success[] = 'Staff: staff / Staff123!';

    } catch (Exception $e) {
        $error = 'Error: ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Installation - Drug Checklist System</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #F5F7FA; color: #374151; display: flex; align-items: center; justify-content: center; min-height: 100vh; padding: 20px; }
        .card { background: #fff; border-radius: 16px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); padding: 40px; max-width: 600px; width: 100%; }
        h1 { font-size: 1.5rem; font-weight: 700; color: #111827; margin-bottom: 8px; }
        p { color: #6B7280; margin-bottom: 24px; }
        .btn { display: inline-flex; align-items: center; gap: 8px; padding: 12px 24px; border-radius: 8px; font-size: 0.875rem; font-weight: 600; border: none; cursor: pointer; background: #2563EB; color: #fff; }
        .btn:hover { background: #1d4ed8; }
        .success { background: #ECFDF5; color: #065F46; padding: 12px 16px; border-radius: 8px; margin-bottom: 16px; font-size: 0.875rem; border: 1px solid #A7F3D0; }
        .error { background: #FEF2F2; color: #991B1B; padding: 12px 16px; border-radius: 8px; margin-bottom: 16px; font-size: 0.875rem; border: 1px solid #FECACA; }
        ul { margin: 8px 0; padding-left: 20px; }
        li { margin-bottom: 4px; }
    </style>
</head>
<body>
    <div class="card">
        <h1>Drug Checklist System</h1>
        <p>Database Installation Wizard</p>

        <?php if ($error): ?>
        <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
        <div class="success">
            <strong>Installation Complete!</strong>
            <ul>
                <?php foreach ($success as $msg): ?>
                <li><?= htmlspecialchars($msg) ?></li>
                <?php endforeach; ?>
            </ul>
            <p style="margin-top:12px;"><a href="../login.php" style="color:#2563EB;font-weight:600;">Proceed to Login &rarr;</a></p>
        </div>
        <?php endif; ?>

        <?php if (!$success): ?>
        <form method="POST">
            <input type="hidden" name="init" value="1">
            <p>This will create the database <strong><?= htmlspecialchars($dbname) ?></strong> with all required tables and seed data.</p>
            <p style="font-size:0.875rem;color:#9CA3AF;">Make sure your MySQL server is running and credentials are correct in config/database.php.</p>
            <button type="submit" class="btn">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/>
                </svg>
                Install Database
            </button>
        </form>
        <?php endif; ?>
    </div>
</body>
</html>
