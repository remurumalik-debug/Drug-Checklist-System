<?php if (isLoggedIn()): ?>
        </div>
    </div>
</div>
<?php else: ?>
</div>
<?php endif; ?>
<?php if (isset($_SESSION['success'])): ?>
<div class="toast-container">
    <div class="toast toast-success" id="toast">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>
        </svg>
        <span><?= sanitize($_SESSION['success']) ?></span>
    </div>
</div>
<?php unset($_SESSION['success']); ?>
<?php endif; ?>
<?php if (isset($_SESSION['error'])): ?>
<div class="toast-container">
    <div class="toast toast-error" id="toast">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/>
        </svg>
        <span><?= sanitize($_SESSION['error']) ?></span>
    </div>
</div>
<?php unset($_SESSION['error']); ?>
<?php endif; ?>
<?php if (isset($_SESSION['warning'])): ?>
<div class="toast-container">
    <div class="toast toast-warning" id="toast">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>
        </svg>
        <span><?= sanitize($_SESSION['warning']) ?></span>
    </div>
</div>
<?php unset($_SESSION['warning']); ?>
<?php endif; ?>
<script src="<?= BASE_URL ?>assets/js/main.js"></script>
</body>
</html>
