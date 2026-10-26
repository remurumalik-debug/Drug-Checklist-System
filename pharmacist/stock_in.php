<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('pharmacist');

$pageTitle = 'Stock In';

$userId = getCurrentUserId();

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'stock_in') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $_SESSION['error'] = 'Invalid CSRF token.';
        redirect('pharmacist/stock_in.php');
    }

    $drug_id = (int)($_POST['drug_id'] ?? 0);
    $quantity = (int)($_POST['quantity'] ?? 0);
    $unit_price = !empty($_POST['unit_price']) ? (float)$_POST['unit_price'] : null;
    $reference_number = trim($_POST['reference_number'] ?? generateReferenceNumber('STKIN'));
    $remarks = trim($_POST['remarks'] ?? '');

    if (!$drug_id || $quantity <= 0) {
        $_SESSION['error'] = 'Please select a drug and enter a valid quantity.';
        redirect('pharmacist/stock_in.php');
    }

    try {
        db()->beginTransaction();

        // Get current quantity
        $stmt = db()->prepare("SELECT quantity, unit_price, drug_name, batch_number FROM drugs WHERE id = ? FOR UPDATE");
        $stmt->execute([$drug_id]);
        $drug = $stmt->fetch();

        if (!$drug) {
            throw new PDOException('Drug not found.');
        }

        $previousQty = (int)$drug['quantity'];
        $newQty = $previousQty + $quantity;
        $currentPrice = $unit_price ?? (float)$drug['unit_price'];

        // Update drug
        $stmt = db()->prepare("UPDATE drugs SET quantity = ?, unit_price = COALESCE(?, unit_price) WHERE id = ?");
        $stmt->execute([$newQty, $currentPrice, $drug_id]);

        // Auto-update status
        if ($newQty <= 0) {
            $newStatus = 'out_of_stock';
        } elseif ($newQty <= (int)getSetting('low_stock_threshold', 10)) {
            $newStatus = 'low_stock';
        } else {
            $newStatus = 'available';
        }
        db()->prepare("UPDATE drugs SET status = ? WHERE id = ?")->execute([$newStatus, $drug_id]);

        // Insert stock transaction
        $stmt = db()->prepare("
            INSERT INTO stock_transactions (drug_id, transaction_type, quantity, previous_quantity, new_quantity, unit_price, reference_number, remarks, performed_by)
            VALUES (?, 'stock_in', ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$drug_id, $quantity, $previousQty, $newQty, $currentPrice, $reference_number, $remarks, $userId]);

        db()->commit();

        logAudit($userId, 'stock_in', 'stock_transaction', $drug_id, "Stock in: {$quantity} units of {$drug['drug_name']} (Batch: {$drug['batch_number']}). Ref: {$reference_number}");
        createNotification($userId, 'info', 'Stock In Completed', "{$quantity} units of {$drug['drug_name']} added. New qty: {$newQty}.");

        $_SESSION['success'] = "Stock in recorded successfully. {$quantity} units added to {$drug['drug_name']}.";
    } catch (PDOException $e) {
        db()->rollBack();
        $_SESSION['error'] = 'Failed to process stock in: ' . $e->getMessage();
    }
    redirect('pharmacist/stock_in.php');
}

// Get all drugs for dropdown
$allDrugs = db()->query("SELECT id, drug_name, batch_number, quantity, unit_price, status FROM drugs WHERE status != 'discontinued' ORDER BY drug_name ASC")->fetchAll();

// Pagination for stock in history
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage = 15;
$offset = ($page - 1) * $perPage;

$countStmt = db()->query("SELECT COUNT(*) as total FROM stock_transactions WHERE transaction_type = 'stock_in'");
$total = $countStmt->fetch()['total'];
$totalPages = ceil($total / $perPage);

$stmt = db()->prepare("
    SELECT st.*, d.drug_name, d.batch_number, u.first_name, u.last_name
    FROM stock_transactions st
    JOIN drugs d ON st.drug_id = d.id
    JOIN users u ON st.performed_by = u.id
    WHERE st.transaction_type = 'stock_in'
    ORDER BY st.transaction_date DESC
    LIMIT ? OFFSET ?
");
$stmt->execute([$perPage, $offset]);
$transactions = $stmt->fetchAll();

$paginationData = [
    'data' => $transactions,
    'current_page' => $page,
    'per_page' => $perPage,
    'total' => $total,
    'total_pages' => $totalPages,
    'offset' => $offset,
];

include_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>Stock In</h1>
        <p>Add stock to inventory</p>
    </div>
</div>

<div class="grid-2">
    <div class="card">
        <div class="card-header">
            <h2>Add Stock</h2>
        </div>
        <div class="card-body">
            <form method="POST" action="">
                <input type="hidden" name="action" value="stock_in">
                <input type="hidden" name="csrf_token" value="<?= getCsrfToken() ?>">

                <div class="form-group">
                    <label class="form-label">Select Drug <span class="required">*</span></label>
                    <select name="drug_id" class="form-control" required>
                        <option value="">— Choose a drug —</option>
                        <?php foreach ($allDrugs as $d): ?>
                        <option value="<?= $d['id'] ?>" data-price="<?= $d['unit_price'] ?>">
                            <?= sanitize($d['drug_name']) ?> (Batch: <?= sanitize($d['batch_number'])?>) — Stock: <?= (int)$d['quantity'] ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Quantity <span class="required">*</span></label>
                    <input type="number" name="quantity" class="form-control" min="1" required placeholder="Enter quantity to add">
                </div>

                <div class="form-group">
                    <label class="form-label">Unit Price</label>
                    <input type="number" name="unit_price" class="form-control" min="0" step="0.01" placeholder="Leave blank to keep existing price">
                    <div class="form-help">Leave empty to keep current unit price.</div>
                </div>

                <div class="form-group">
                    <label class="form-label">Reference Number</label>
                    <input type="text" name="reference_number" class="form-control" value="<?= generateReferenceNumber('STKIN') ?>">
                    <div class="form-help">Auto-generated; you may change it.</div>
                </div>

                <div class="form-group">
                    <label class="form-label">Remarks</label>
                    <textarea name="remarks" class="form-control" rows="3" placeholder="Reason for stock in..."></textarea>
                </div>

                <button type="submit" class="btn btn-success" style="width:100%;">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
                    </svg>
                    Process Stock In
                </button>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h2>Stock In History</h2>
        </div>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Drug Name</th>
                        <th>Batch</th>
                        <th>Previous Qty</th>
                        <th>Added Qty</th>
                        <th>New Qty</th>
                        <th>Ref #</th>
                        <th>Remarks</th>
                        <th>Date</th>
                        <th>Performed By</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($transactions) > 0): ?>
                    <?php foreach ($transactions as $t): ?>
                    <tr>
                        <td><strong><?= sanitize($t['drug_name']) ?></strong></td>
                        <td><?= sanitize($t['batch_number']) ?></td>
                        <td><?= (int)$t['previous_quantity'] ?></td>
                        <td class="text-success" style="font-weight:600;">+<?= (int)$t['quantity'] ?></td>
                        <td><?= (int)$t['new_quantity'] ?></td>
                        <td><?= sanitize($t['reference_number'] ?? '—') ?></td>
                        <td><?= sanitize($t['remarks'] ?? '—') ?></td>
                        <td><?= formatDateTime($t['transaction_date']) ?></td>
                        <td><?= sanitize($t['first_name'] . ' ' . $t['last_name']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php else: ?>
                    <tr>
                        <td colspan="9">
                            <div class="empty-state">
                                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
                                </svg>
                                <h3>No stock in records</h3>
                                <p>Stock in transactions will appear here.</p>
                            </div>
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php if ($totalPages > 1): ?>
        <div class="card-footer">
            <?= renderPagination($paginationData) ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
document.querySelector('[name="drug_id"]')?.addEventListener('change', function() {
    const selected = this.options[this.selectedIndex];
    const price = selected.dataset.price;
    const priceInput = document.querySelector('[name="unit_price"]');
    if (priceInput && !priceInput.value) {
        priceInput.placeholder = price ? 'Current: ' + price : 'Enter unit price';
    }
});
</script>

<?php include_once __DIR__ . '/../includes/footer.php'; ?>
