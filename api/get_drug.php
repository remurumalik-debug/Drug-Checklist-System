<?php
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) {
    jsonResponse(['success' => false, 'error' => 'Invalid drug ID'], 400);
}

try {
    $stmt = db()->prepare("
        SELECT d.*, dc.category_name, s.supplier_name,
               u.first_name AS created_by_first, u.last_name AS created_by_last
        FROM drugs d
        LEFT JOIN drug_categories dc ON d.category_id = dc.id
        LEFT JOIN suppliers s ON d.supplier_id = s.id
        LEFT JOIN users u ON d.created_by = u.id
        WHERE d.id = ?
    ");
    $stmt->execute([$id]);
    $drug = $stmt->fetch();

    if (!$drug) {
        jsonResponse(['success' => false, 'error' => 'Drug not found'], 404);
    }

    $categories = db()->query("SELECT id, category_name FROM drug_categories WHERE status = 'active' ORDER BY category_name")->fetchAll();
    $suppliers = db()->query("SELECT id, supplier_name FROM suppliers WHERE status = 'active' ORDER BY supplier_name")->fetchAll();

    $stmt = db()->prepare("
        SELECT st.*, u.first_name, u.last_name
        FROM stock_transactions st
        LEFT JOIN users u ON st.performed_by = u.id
        WHERE st.drug_id = ?
        ORDER BY st.transaction_date DESC
        LIMIT 20
    ");
    $stmt->execute([$id]);
    $transactions = $stmt->fetchAll();

    $drug['categories'] = $categories;
    $drug['suppliers'] = $suppliers;
    $drug['transactions'] = $transactions;

    jsonResponse(['success' => true, 'data' => $drug]);
} catch (PDOException $e) {
    error_log("get_drug API error: " . $e->getMessage());
    jsonResponse(['success' => false, 'error' => 'Database error'], 500);
}
