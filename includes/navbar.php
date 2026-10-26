<?php
$unreadCount = 0;
if (isLoggedIn()) {
    try {
        $stmt = db()->prepare("SELECT COUNT(*) as cnt FROM notifications WHERE user_id = ? AND is_read = 0");
        $stmt->execute([getCurrentUserId()]);
        $unreadCount = $stmt->fetch()['cnt'];
    } catch (PDOException $e) {}
}
$notifPath = BASE_URL . getCurrentUserRole() . '/notifications.php';
?>
<header class="navbar">
    <div class="navbar-left">
        <button class="sidebar-toggle-mobile" id="sidebarToggleMobile">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/>
            </svg>
        </button>
        <div class="navbar-search">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
            </svg>
            <input type="text" id="globalSearch" placeholder="Search drugs..." autocomplete="off" data-role="<?= getCurrentUserRole() ?>">
            <div id="searchResults" class="search-results"></div>
        </div>
    </div>
    <div class="navbar-right">
        <button class="theme-toggle" id="themeToggle" title="Toggle dark mode">
            <svg id="themeIconSun" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>
            <svg id="themeIconMoon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display:none"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
        </button>
        <a href="<?= $notifPath ?>" class="nav-icon notification-icon">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/>
            </svg>
            <?php if ($unreadCount > 0): ?>
            <span class="notification-badge" style="display:flex"><?= $unreadCount > 99 ? '99+' : $unreadCount ?></span>
            <?php else: ?>
            <span class="notification-badge" style="display:none">0</span>
            <?php endif; ?>
        </a>
        <div class="user-dropdown">
            <button class="user-dropdown-btn" id="userDropdownBtn">
                <div class="user-avatar">
                    <?= strtoupper(substr($_SESSION['full_name'] ?? 'U', 0, 1)) ?>
                </div>
                <div class="user-info">
                    <span class="user-name"><?= sanitize($_SESSION['full_name'] ?? 'User') ?></span>
                    <span class="user-role"><?= ucfirst(sanitize($_SESSION['role'] ?? '')) ?></span>
                </div>
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="6 9 12 15 18 9"/>
                </svg>
            </button>
            <div class="dropdown-menu" id="dropdownMenu">
                <a href="<?= BASE_URL ?>profile.php" class="dropdown-item">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                    My Profile
                </a>
                <a href="<?= BASE_URL ?>logout.php" class="dropdown-item">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/>
                    </svg>
                    Sign Out
                </a>
            </div>
        </div>
    </div>
</header>
