<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('staff');

$pageTitle = 'View Inventory';

$where = "d.status != 'discontinued'";
$params = [];

if (!empty($_GET['search'])) {
    $search = '%' . $_GET['search'] . '%';
    $where .= " AND (d.drug_name LIKE ? OR d.generic_name LIKE ? OR d.batch_number LIKE ?)";
    $params = array_merge($params, [$search, $search, $search]);
}

if (!empty($_GET['category_id'])) {
    $where .= " AND d.category_id = ?";
    $params[] = (int)$_GET['category_id'];
}

if (!empty($_GET['status'])) {
    $where .= " AND d.status = ?";
    $params[] = $_GET['status'];
}

$pagination = getPaginatedData(
    "drugs d LEFT JOIN drug_categories dc ON d.category_id = dc.id LEFT JOIN suppliers s ON d.supplier_id = s.id",
    $where,
    $params,
    "d.updated_at DESC",
    15
);

try {
    $categories = db()->query("SELECT id, category_name FROM drug_categories WHERE status = 'active' ORDER BY category_name")->fetchAll();
    $statuses = ['available', 'low_stock', 'out_of_stock', 'expired', 'discontinued'];
} catch (PDOException $e) {
    error_log("Inventory load error: " . $e->getMessage());
}

include_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div class="page-header-left">
        <h1 class="page-title">Drug Inventory</h1>
        <p class="page-subtitle">View all drugs in the inventory</p>
    </div>
    <div class="page-header-right">
        <a href="<?= BASE_URL ?>staff/index.php" class="btn btn-outline-secondary">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/>
            </svg>
            Back to Dashboard
        </a>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <form method="GET" class="filter-form">
            <div class="filter-row">
                <div class="filter-group">
                    <label class="form-label" for="search">Search</label>
                    <input type="text" id="search" name="search" class="form-control" placeholder="Drug name, generic name, batch #..." value="<?= sanitize($_GET['search'] ?? '') ?>">
                </div>
                <div class="filter-group">
                    <label class="form-label" for="category_id">Category</label>
                    <select id="category_id" name="category_id" class="form-control">
                        <option value="">All Categories</option>
                        <?php foreach ($categories ?? [] as $cat): ?>
                        <option value="<?= $cat['id'] ?>" <?= (isset($_GET['category_id']) && $_GET['category_id'] == $cat['id']) ? 'selected' : '' ?>><?= sanitize($cat['category_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label class="form-label" for="status">Status</label>
                    <select id="status" name="status" class="form-control">
                        <option value="">All Statuses</option>
                        <?php foreach ($statuses ?? [] as $st): ?>
                        <option value="<?= $st ?>" <?= (isset($_GET['status']) && $_GET['status'] === $st) ? 'selected' : '' ?>><?= ucwords(str_replace('_', ' ', $st)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group filter-group-btn">
                    <label class="form-label">&nbsp;</label>
                    <button type="submit" class="btn btn-primary btn-block">Filter</button>
                </div>
                <div class="filter-group filter-group-btn">
                    <label class="form-label">&nbsp;</label>
                    <a href="<?= BASE_URL ?>staff/inventory.php" class="btn btn-outline-secondary btn-block">Reset</a>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="card mt-3">
    <div class="card-header">
        <h3>Drug Inventory</h3>
        <span class="card-badge"><?= $pagination['total'] ?> Records</span>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Drug Name</th>
                        <th>Generic Name</th>
                        <th>Category</th>
                        <th>Batch #</th>
                        <th>Manufacturer</th>
                        <th>Qty</th>
                        <th>Expiry Date</th>
                        <th>Status</th>
                        <th>Storage Location</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($pagination['data'])): ?>
                    <tr><td colspan="10" class="text-center">No drugs found.</td></tr>
                    <?php else: ?>
                    <?php foreach ($pagination['data'] as $drug): ?>
                    <tr>
                        <td><?= sanitize($drug['drug_name']) ?></td>
                        <td><?= sanitize($drug['generic_name'] ?? 'N/A') ?></td>
                        <td><?= sanitize($drug['category_name'] ?? 'N/A') ?></td>
                        <td><?= sanitize($drug['batch_number']) ?></td>
                        <td><?= sanitize($drug['manufacturer'] ?? 'N/A') ?></td>
                        <td><?= (int)$drug['quantity'] ?></td>
                        <td><?= formatDate($drug['expiry_date']) ?></td>
                        <td><?= getStatusBadge($drug['status']) ?></td>
                        <td><?= sanitize($drug['storage_location'] ?? 'N/A') ?></td>
                        <td>
                            <button type="button" class="btn btn-sm btn-info" onclick="viewDrug(<?= $drug['id'] ?>)">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>
                                </svg>
                                View
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?= renderPagination($pagination) ?>
    </div>
</div>

<div class="modal fade" id="drugModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Drug Details</h5>
                <button type="button" class="modal-close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body" id="drugModalBody">
                <div class="text-center">
                    <div class="spinner"></div>
                    <p>Loading...</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
function viewDrug(id) {
    const modal = document.getElementById('drugModal');
    const body = document.getElementById('drugModalBody');
    body.innerHTML = '<div class="text-center"><div class="spinner"></div><p>Loading...</p></div>';
    modal.classList.add('show');
    modal.style.display = 'block';

    fetch('<?= BASE_URL ?>api/search.php?q=&drug_id=' + id)
        .then(r => r.json())
        .then(data => {
            if (data && data.length > 0) {
                const d = Array.isArray(data) ? data[0] : data;
                let html = '<div class="drug-detail-grid">';
                html += '<div class="detail-row"><strong>Drug Name:</strong><span>' + escapeHtml(d.drug_name || 'N/A') + '</span></div>';
                html += '<div class="detail-row"><strong>Generic Name:</strong><span>' + escapeHtml(d.generic_name || 'N/A') + '</span></div>';
                html += '<div class="detail-row"><strong>Brand Name:</strong><span>' + escapeHtml(d.brand_name || 'N/A') + '</span></div>';
                html += '<div class="detail-row"><strong>Category:</strong><span>' + escapeHtml(d.category_name || 'N/A') + '</span></div>';
                html += '<div class="detail-row"><strong>Batch Number:</strong><span>' + escapeHtml(d.batch_number || 'N/A') + '</span></div>';
                html += '<div class="detail-row"><strong>Manufacturer:</strong><span>' + escapeHtml(d.manufacturer || 'N/A') + '</span></div>';
                html += '<div class="detail-row"><strong>Quantity:</strong><span>' + (d.quantity || 0) + '</span></div>';
                html += '<div class="detail-row"><strong>Expiry Date:</strong><span>' + (d.expiry_date || 'N/A') + '</span></div>';
                html += '<div class="detail-row"><strong>Status:</strong><span>' + (d.status ? d.status.replace(/_/g, ' ') : 'N/A') + '</span></div>';
                html += '<div class="detail-row"><strong>Storage Location:</strong><span>' + escapeHtml(d.storage_location || 'N/A') + '</span></div>';
                html += '</div>';
                body.innerHTML = html;
            } else {
                body.innerHTML = '<p class="text-center">Drug not found.</p>';
            }
        })
        .catch(() => {
            body.innerHTML = '<p class="text-center text-danger">Failed to load drug details.</p>';
        });
}

function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

document.addEventListener('click', function(e) {
    const modal = document.getElementById('drugModal');
    if (e.target === modal || e.target.hasAttribute('data-dismiss')) {
        modal.classList.remove('show');
        modal.style.display = 'none';
    }
});
</script>

<?php include_once __DIR__ . '/../includes/footer.php'; ?>
