<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('pharmacist');

$pageTitle = 'Stock Adjustment';

$userId = getCurrentUserId();

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'adjustment') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $_SESSION['error'] = 'Invalid CSRF token.';
        redirect('pharmacist/adjustment.php');
    }

    $drug_id = (int)($_POST['drug_id'] ?? 0);
    $adjustment_type = trim($_POST['adjustment_type'] ?? '');
    $quantity = (int)($_POST['quantity'] ?? 0);
    $reason = trim($_POST['reason'] ?? '');
    $reference_number = trim($_POST['reference_number'] ?? generateReferenceNumber('ADJ'));

    if (!$drug_id || !in_array($adjustment_type, ['increase', 'decrease']) || $quantity <= 0 || $reason === '') {
        $_SESSION['error'] = 'Please fill in all required fields with valid values.';
        redirect('pharmacist/adjustment.php');
    }

    try {
        db()->beginTransaction();

        $stmt = db()->prepare("SELECT quantity, min_stock, drug_name, batch_number FROM drugs WHERE id = ? FOR UPDATE");
        $stmt->execute([$drug_id]);
        $drug = $stmt->fetch();

        if (!$drug) {
            throw new PDOException('Drug not found.');
        }

        $previousQty = (int)$drug['quantity'];

        if ($adjustment_type === 'increase') {
            $newQty = $previousQty + $quantity;
        } else {
            if ($quantity > $previousQty) {
                throw new PDOException("Cannot decrease by {$quantity}. Current stock is only {$previousQty}.");
            }
            $newQty = $previousQty - $quantity;
        }

        if ($newQty < 0) {
            throw new PDOException('Stock quantity cannot be negative.');
        }

        db()->prepare("UPDATE drugs SET quantity = ? WHERE id = ?")->execute([$newQty, $drug_id]);

        if ($newQty <= 0) {
            $newStatus = 'out_of_stock';
        } elseif ($newQty <= (int)getSetting('low_stock_threshold', 10)) {
            $newStatus = 'low_stock';
        } else {
            $newStatus = 'available';
        }
        db()->prepare("UPDATE drugs SET status = ? WHERE id = ?")->execute([$newStatus, $drug_id]);

        $stmt = db()->prepare("
            INSERT INTO stock_transactions (drug_id, transaction_type, quantity, previous_quantity, new_quantity, reference_number, remarks, performed_by)
            VALUES (?, 'adjustment', ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$drug_id, $quantity, $previousQty, $newQty, $reference_number, $reason, $userId]);

        db()->commit();

        $typeLabel = $adjustment_type === 'increase' ? 'Increased' : 'Decreased';
        logAudit($userId, 'stock_adjustment', 'stock_transaction', $drug_id, "Stock adjustment: {$typeLabel} by {$quantity} units of {$drug['drug_name']} (Batch: {$drug['batch_number']}). Ref: {$reference_number}. Reason: {$reason}");
        notifyAllAdminsAndPharmacists('info', 'Stock Adjustment', "{$typeLabel} by {$quantity} units of {$drug['drug_name']} (Batch: {$drug['batch_number']}). New qty: {$newQty}. By: " . getUserName($userId) . ".", $drug_id);

        $_SESSION['success'] = "Stock adjustment completed. {$typeLabel} {$quantity} units for {$drug['drug_name']}.";
    } catch (PDOException $e) {
        db()->rollBack();
        $_SESSION['error'] = 'Failed to process adjustment: ' . $e->getMessage();
    }
    redirect('pharmacist/adjustment.php');
}

// Get only available/low_stock drugs
$allDrugs = db()->query("SELECT id, drug_name, batch_number, quantity, status FROM drugs WHERE status IN ('available', 'low_stock') ORDER BY drug_name ASC")->fetchAll();

// Pagination for adjustment history
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage = 15;
$offset = ($page - 1) * $perPage;

$countStmt = db()->query("SELECT COUNT(*) as total FROM stock_transactions WHERE transaction_type = 'adjustment'");
$total = $countStmt->fetch()['total'];
$totalPages = ceil($total / $perPage);

$stmt = db()->prepare("
    SELECT st.*, d.drug_name, d.batch_number, u.first_name, u.last_name
    FROM stock_transactions st
    JOIN drugs d ON st.drug_id = d.id
    JOIN users u ON st.performed_by = u.id
    WHERE st.transaction_type = 'adjustment'
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
        <h1>Stock Adjustment</h1>
        <p>Correct inventory discrepancies by adjusting drug quantities</p>
    </div>
</div>

<div class="grid-2">
    <div class="card">
        <div class="card-header">
            <h2>Create Adjustment</h2>
        </div>
        <div class="card-body">
            <form method="POST" action="">
                <input type="hidden" name="action" value="adjustment">
                <input type="hidden" name="csrf_token" value="<?= getCsrfToken() ?>">

                <div class="form-group">
                    <label class="form-label">Select Drug <span class="required">*</span></label>
                    <select name="drug_id" class="form-control" id="drugSelect" required>
                        <option value="">— Choose a drug —</option>
                        <?php foreach ($allDrugs as $d): ?>
                        <option value="<?= $d['id'] ?>" data-qty="<?= (int)$d['quantity'] ?>" data-status="<?= sanitize($d['status']) ?>">
                            <?= sanitize($d['drug_name']) ?> (Batch: <?= sanitize($d['batch_number'])?>) — Stock: <?= (int)$d['quantity'] ?> — <?= ucfirst(str_replace('_', ' ', $d['status'])) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Adjustment Type <span class="required">*</span></label>
                    <select name="adjustment_type" class="form-control" id="adjustmentType" required>
                        <option value="">— Choose type —</option>
                        <option value="increase">Increase (Add stock)</option>
                        <option value="decrease">Decrease (Remove stock)</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Quantity <span class="required">*</span></label>
                    <input type="number" name="quantity" class="form-control" id="qtyInput" min="1" required placeholder="Enter quantity to adjust">
                    <div class="form-help" id="stockHelp"></div>
                    <div class="form-error" id="qtyError">Quantity cannot exceed current stock for decrease.</div>
                </div>

                <div class="form-group">
                    <label class="form-label">Reason <span class="required">*</span></label>
                    <textarea name="reason" class="form-control" rows="3" required placeholder="Explain why this adjustment is needed..."></textarea>
                </div>

                <div class="form-group">
                    <label class="form-label">Reference Number</label>
                    <input type="text" name="reference_number" class="form-control" value="<?= generateReferenceNumber('ADJ') ?>" readonly>
                    <div class="form-help">Auto-generated reference number.</div>
                </div>

                <button type="submit" class="btn btn-primary" style="width:100%;" onclick="return validateForm()">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="1 4 1 10 7 10"/><polyline points="23 20 23 14 17 14"/>
                        <path d="M20.49 9A9 9 0 0 0 5.64 5.64L1 10m22 4l-4.64 4.36A9 9 0 0 1 3.51 15"/>
                    </svg>
                    Process Adjustment
                </button>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h2>Adjustment History</h2>
        </div>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Drug</th>
                        <th>Type</th>
                        <th>Qty</th>
                        <th>Previous Qty</th>
                        <th>New Qty</th>
                        <th>Reason</th>
                        <th>Performed By</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($transactions) > 0): ?>
                    <?php foreach ($transactions as $t): ?>
                    <tr>
                        <td><strong><?= sanitize($t['drug_name']) ?></strong><br><small>Batch: <?= sanitize($t['batch_number']) ?></small></td>
                        <td>
                            <?php if ((int)$t['new_quantity'] > (int)$t['previous_quantity']): ?>
                                <span class="badge badge-success">Increase</span>
                            <?php elseif ((int)$t['new_quantity'] < (int)$t['previous_quantity']): ?>
                                <span class="badge badge-danger">Decrease</span>
                            <?php else: ?>
                                <span class="badge badge-secondary">No Change</span>
                            <?php endif; ?>
                        </td>
                        <td style="font-weight:600;"><?= (int)$t['quantity'] ?></td>
                        <td><?= (int)$t['previous_quantity'] ?></td>
                        <td><?= (int)$t['new_quantity'] ?></td>
                        <td><?= sanitize($t['remarks'] ?? '—') ?></td>
                        <td><?= sanitize($t['first_name'] . ' ' . $t['last_name']) ?></td>
                        <td><?= formatDateTime($t['transaction_date']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php else: ?>
                    <tr>
                        <td colspan="8">
                            <div class="empty-state">
                                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <polyline points="1 4 1 10 7 10"/><polyline points="23 20 23 14 17 14"/>
                                    <path d="M20.49 9A9 9 0 0 0 5.64 5.64L1 10m22 4l-4.64 4.36A9 9 0 0 1 3.51 15"/>
                                </svg>
                                <h3>No adjustment records</h3>
                                <p>Stock adjustments will appear here.</p>
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
const adjustmentType = document.getElementById('adjustmentType');
const qtyInput = document.getElementById('qtyInput');
const stockHelp = document.getElementById('stockHelp');
const qtyError = document.getElementById('qtyError');

function updateStockInfo() {
    const selected = drugSelect.options[drugSelect.selectedIndex];
    if (selected && selected.dataset.qty !== undefined) {
        const maxQty = parseInt(selected.dataset.qty);
        stockHelp.textContent = 'Current stock: ' + maxQty + ' units';
        if (adjustmentType.value === 'decrease') {
            qtyInput.max = maxQty;
        } else {
            qtyInput.removeAttribute('max');
        }
        qtyError.classList.remove('show');
    } else {
        stockHelp.textContent = '';
        qtyInput.removeAttribute('max');
        qtyError.classList.remove('show');
    }
}

drugSelect.addEventListener('change', updateStockInfo);
adjustmentType.addEventListener('change', updateStockInfo);
qtyInput.addEventListener('input', function() {
    if (adjustmentType.value === 'decrease') {
        const selected = drugSelect.options[drugSelect.selectedIndex];
        if (selected && selected.dataset.qty !== undefined) {
            const maxQty = parseInt(selected.dataset.qty);
            if (parseInt(qtyInput.value) > maxQty) {
                qtyError.classList.add('show');
            } else {
                qtyError.classList.remove('show');
            }
        }
    }
});

function validateForm() {
    if (adjustmentType.value === 'decrease') {
        const selected = drugSelect.options[drugSelect.selectedIndex];
        if (selected && selected.dataset.qty !== undefined) {
            const maxQty = parseInt(selected.dataset.qty);
            const inputQty = parseInt(qtyInput.value);
            if (inputQty > maxQty) {
                qtyError.classList.add('show');
                return false;
            }
        }
    }
    return true;
}
</script>

<?php include_once __DIR__ . '/../includes/footer.php'; ?>
