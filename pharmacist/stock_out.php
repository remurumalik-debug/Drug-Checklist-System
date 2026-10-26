<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('pharmacist');

$pageTitle = 'Stock Out';

$userId = getCurrentUserId();

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'stock_out') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $_SESSION['error'] = 'Invalid CSRF token.';
        redirect('pharmacist/stock_out.php');
    }

    $drug_id = (int)($_POST['drug_id'] ?? 0);
    $quantity = (int)($_POST['quantity'] ?? 0);
    $reference_number = trim($_POST['reference_number'] ?? generateReferenceNumber('STKOUT'));
    $remarks = trim($_POST['remarks'] ?? '');

    if (!$drug_id || $quantity <= 0) {
        $_SESSION['error'] = 'Please select a drug and enter a valid quantity.';
        redirect('pharmacist/stock_out.php');
    }

    try {
        db()->beginTransaction();

        // Get current quantity with lock
        $stmt = db()->prepare("SELECT quantity, drug_name, batch_number FROM drugs WHERE id = ? FOR UPDATE");
        $stmt->execute([$drug_id]);
        $drug = $stmt->fetch();

        if (!$drug) {
            throw new PDOException('Drug not found.');
        }

        $currentQty = (int)$drug['quantity'];

        if ($quantity > $currentQty) {
            throw new PDOException("Insufficient stock. Current quantity is {$currentQty}, but {$quantity} was requested.");
        }

        $previousQty = $currentQty;
        $newQty = $currentQty - $quantity;

        if ($newQty < 0) {
            throw new PDOException('Stock cannot go below zero.');
        }

        // Update drug
        db()->prepare("UPDATE drugs SET quantity = ? WHERE id = ?")->execute([$newQty, $drug_id]);

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
            INSERT INTO stock_transactions (drug_id, transaction_type, quantity, previous_quantity, new_quantity, reference_number, remarks, performed_by)
            VALUES (?, 'stock_out', ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$drug_id, $quantity, $previousQty, $newQty, $reference_number, $remarks, $userId]);

        db()->commit();

        logAudit($userId, 'stock_out', 'stock_transaction', $drug_id, "Stock out: {$quantity} units of {$drug['drug_name']} (Batch: {$drug['batch_number']}). Ref: {$reference_number}");
        createNotification($userId, 'info', 'Stock Out Completed', "{$quantity} units of {$drug['drug_name']} removed. Remaining: {$newQty}.");

        $_SESSION['success'] = "Stock out recorded successfully. {$quantity} units removed from {$drug['drug_name']}.";
    } catch (PDOException $e) {
        db()->rollBack();
        $_SESSION['error'] = 'Failed to process stock out: ' . $e->getMessage();
    }
    redirect('pharmacist/stock_out.php');
}

// Get all drugs for dropdown
$allDrugs = db()->query("SELECT id, drug_name, batch_number, quantity, status FROM drugs WHERE quantity > 0 AND status != 'discontinued' ORDER BY drug_name ASC")->fetchAll();

// Pagination for stock out history
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage = 15;
$offset = ($page - 1) * $perPage;

$countStmt = db()->query("SELECT COUNT(*) as total FROM stock_transactions WHERE transaction_type = 'stock_out'");
$total = $countStmt->fetch()['total'];
$totalPages = ceil($total / $perPage);

$stmt = db()->prepare("
    SELECT st.*, d.drug_name, d.batch_number, u.first_name, u.last_name
    FROM stock_transactions st
    JOIN drugs d ON st.drug_id = d.id
    JOIN users u ON st.performed_by = u.id
    WHERE st.transaction_type = 'stock_out'
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
        <h1>Stock Out</h1>
        <p>Remove stock from inventory</p>
    </div>
</div>

<div class="grid-2">
    <div class="card">
        <div class="card-header">
            <h2>Remove Stock</h2>
        </div>
        <div class="card-body">
            <form method="POST" action="" onsubmit="return validateStockOut()">
                <input type="hidden" name="action" value="stock_out">
                <input type="hidden" name="csrf_token" value="<?= getCsrfToken() ?>">

                <div class="form-group">
                    <label class="form-label">Select Drug <span class="required">*</span></label>
                    <select name="drug_id" class="form-control" id="drugSelect" required>
                        <option value="">— Choose a drug —</option>
                        <?php foreach ($allDrugs as $d): ?>
                        <option value="<?= $d['id'] ?>" data-qty="<?= (int)$d['quantity'] ?>">
                            <?= sanitize($d['drug_name']) ?> (Batch: <?= sanitize($d['batch_number'])?>) — Stock: <?= (int)$d['quantity'] ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Quantity <span class="required">*</span></label>
                    <input type="number" name="quantity" class="form-control" id="qtyInput" min="1" required placeholder="Enter quantity to remove">
                    <div class="form-help" id="stockHelp"></div>
                    <div class="form-error" id="qtyError">Quantity cannot exceed current stock.</div>
                </div>

                <div class="form-group">
                    <label class="form-label">Reference Number</label>
                    <input type="text" name="reference_number" class="form-control" value="<?= generateReferenceNumber('STKOUT') ?>">
                    <div class="form-help">Auto-generated; you may change it.</div>
                </div>

                <div class="form-group">
                    <label class="form-label">Remarks (Reason) <span class="required">*</span></label>
                    <textarea name="remarks" class="form-control" rows="3" required placeholder="e.g. Dispensed to patient, expired, damaged, etc."></textarea>
                </div>

                <button type="submit" class="btn btn-warning" style="width:100%;">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="5" y1="12" x2="19" y2="12"/>
                    </svg>
                    Process Stock Out
                </button>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h2>Stock Out History</h2>
        </div>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Drug Name</th>
                        <th>Batch</th>
                        <th>Previous Qty</th>
                        <th>Removed Qty</th>
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
                        <td class="text-danger" style="font-weight:600;">-<?= (int)$t['quantity'] ?></td>
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
                                    <line x1="5" y1="12" x2="19" y2="12"/>
                                </svg>
                                <h3>No stock out records</h3>
                                <p>Stock out transactions will appear here.</p>
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
const drugSelect = document.getElementById('drugSelect');
const qtyInput = document.getElementById('qtyInput');
const stockHelp = document.getElementById('stockHelp');
const qtyError = document.getElementById('qtyError');

function updateStockInfo() {
    const selected = drugSelect.options[drugSelect.selectedIndex];
    if (selected && selected.dataset.qty !== undefined) {
        const maxQty = parseInt(selected.dataset.qty);
        stockHelp.textContent = 'Current stock: ' + maxQty + ' units';
        qtyInput.max = maxQty;
        if (parseInt(qtyInput.value) > maxQty) {
            qtyError.classList.add('show');
        } else {
            qtyError.classList.remove('show');
        }
    } else {
        stockHelp.textContent = '';
        qtyInput.removeAttribute('max');
        qtyError.classList.remove('show');
    }
}

drugSelect.addEventListener('change', updateStockInfo);
qtyInput.addEventListener('input', updateStockInfo);

function validateStockOut() {
    const selected = drugSelect.options[drugSelect.selectedIndex];
    if (selected && selected.dataset.qty !== undefined) {
        const maxQty = parseInt(selected.dataset.qty);
        const inputQty = parseInt(qtyInput.value);
        if (inputQty > maxQty) {
            qtyError.classList.add('show');
            return false;
        }
    }
    return true;
}
</script>

<?php include_once __DIR__ . '/../includes/footer.php'; ?>
