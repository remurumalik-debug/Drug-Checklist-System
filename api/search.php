<?php
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

$q = trim($_GET['q'] ?? '');
$drugId = isset($_GET['drug_id']) ? (int)$_GET['drug_id'] : 0;

try {
    if ($drugId > 0) {
        $stmt = db()->prepare("
            SELECT d.id, d.drug_name, d.generic_name, d.brand_name, d.batch_number,
                   d.manufacturer, d.quantity, d.min_stock, d.unit_price,
                   d.expiry_date, d.storage_location, d.status, d.description,
                   dc.category_name, s.supplier_name
            FROM drugs d
            LEFT JOIN drug_categories dc ON d.category_id = dc.id
            LEFT JOIN suppliers s ON d.supplier_id = s.id
            WHERE d.id = ?
            LIMIT 1
        ");
        $stmt->execute([$drugId]);
        $results = $stmt->fetchAll();
    } elseif (strlen($q) >= 1) {
        $search = '%' . $q . '%';
        $stmt = db()->prepare("
            SELECT d.id, d.drug_name, d.generic_name, d.brand_name, d.batch_number,
                   d.manufacturer, d.quantity, d.expiry_date, d.status,
                   dc.category_name
            FROM drugs d
            LEFT JOIN drug_categories dc ON d.category_id = dc.id
            WHERE d.drug_name LIKE ?
               OR d.generic_name LIKE ?
               OR d.brand_name LIKE ?
               OR d.batch_number LIKE ?
            ORDER BY d.drug_name ASC
            LIMIT 10
        ");
        $stmt->execute([$search, $search, $search, $search]);
        $results = $stmt->fetchAll();
    } else {
        $results = [];
    }

    jsonResponse($results);
} catch (PDOException $e) {
    error_log("Search API error: " . $e->getMessage());
    jsonResponse(['error' => 'Search failed'], 500);
}
