<nav class="navbar navbar-expand-lg fixed-top" id="mainNavbar">
    <div class="container-fluid px-3 gap-3">

        <!-- Mobile toggle -->
        <button class="navbar-toggler d-lg-none" type="button" onclick="toggleSidebar()" style="background:rgba(255,255,255,0.06);border:1px solid rgba(99,102,241,0.2);border-radius:8px;padding:0.4rem 0.6rem;color:var(--text-secondary);">
            <i class="bi bi-list" style="font-size:1.2rem;"></i>
        </button>

        <!-- Brand -->
        <a class="navbar-brand me-auto" href="dashboard.php">
            <i class="bi bi-boxes"></i> InvenAI
        </a>

        <!-- Search -->
        <div class="navbar-search d-none d-md-flex">
            <i class="bi bi-search search-icon"></i>
            <input type="text" id="globalSearch" placeholder="Quick search..." autocomplete="off">
        </div>

        <!-- Right actions -->
        <div class="d-flex align-items-center gap-2">

            <!-- Clock -->
            <span class="navbar-clock d-none d-lg-block" id="navbar-clock">--:--:--</span>

            <!-- Dark mode -->
            <button class="dark-mode-toggle" id="darkModeToggle" title="Toggle theme">
                <i class="bi bi-sun-fill"></i>
            </button>

            <!-- Notifications -->
            <div class="position-relative">
                <a href="notifications.php" class="notification-btn" id="notification-bell" title="Notifications">
                    <i class="bi bi-bell"></i>
                    <?php
                    try {
                        $db_instance = new Database();
                        $db_nav = $db_instance->getConnection();
                        if ($db_nav) {
                            $db_nav->exec("CREATE TABLE IF NOT EXISTS notifications (
                                id INT AUTO_INCREMENT PRIMARY KEY,
                                type ENUM('low_stock','reorder','sale','transfer','system') NOT NULL,
                                title VARCHAR(255) NOT NULL,
                                message TEXT NOT NULL,
                                user_id INT NULL,
                                is_read BOOLEAN DEFAULT FALSE,
                                priority ENUM('low','medium','high','critical') DEFAULT 'medium',
                                action_url VARCHAR(255) NULL,
                                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                                INDEX idx_user_read (user_id, is_read),
                                INDEX idx_created (created_at)
                            )");
                            $stmt_nav = $db_nav->prepare("SELECT COUNT(*) FROM notifications WHERE (user_id = ? OR user_id IS NULL) AND is_read = FALSE");
                            $stmt_nav->execute([$_SESSION['user_id']]);
                            $unread_count = $stmt_nav->fetchColumn();
                            if ($unread_count > 0): ?>
                                <span class="notification-badge" id="notification-count">
                                    <?php echo $unread_count > 99 ? '99+' : $unread_count; ?>
                                </span>
                            <?php endif;
                        }
                    } catch (Exception $e) {
                        error_log("Navbar notification error: " . $e->getMessage());
                    }
                    ?>
                </a>
            </div>

            <!-- User menu -->
            <div class="dropdown">
                <a class="user-menu-btn" href="#" id="userDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                    <div class="user-avatar-sm">
                        <?php echo strtoupper(substr($_SESSION['username'] ?? 'U', 0, 1)); ?>
                    </div>
                    <span class="user-name-text d-none d-sm-inline">
                        <?php echo htmlspecialchars($_SESSION['username'] ?? 'User'); ?>
                    </span>
                    <i class="bi bi-chevron-down" style="font-size:0.7rem;color:var(--text-muted);"></i>
                </a>
                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                    <li><h6 class="dropdown-header">
                        <i class="bi bi-person-circle"></i>
                        <?php echo htmlspecialchars($_SESSION['username'] ?? 'User'); ?>
                        <span style="display:block;font-size:0.65rem;color:var(--accent-primary);font-weight:600;margin-top:2px;">
                            <?php echo isset($_SESSION['role']) ? ucfirst($_SESSION['role']) : 'Staff'; ?>
                        </span>
                    </h6></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="notifications.php">
                        <i class="bi bi-bell"></i> Notifications
                    </a></li>
                    <?php if (isAdmin()): ?>
                    <li><a class="dropdown-item" href="user_management.php">
                        <i class="bi bi-people"></i> User Management
                    </a></li>
                    <?php endif; ?>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="settings.php">
                        <i class="bi bi-gear"></i> Settings
                    </a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="logout.php" style="color:var(--accent-rose)!important;">
                        <i class="bi bi-box-arrow-right"></i> Sign Out
                    </a></li>
                </ul>
            </div>
        </div>
    </div>
</nav>

<!-- Search results overlay -->
<div id="searchOverlay" style="
    display:none;
    position:fixed;
    top:calc(var(--navbar-height) + 8px);
    left:50%;
    transform:translateX(-50%);
    width:400px;
    max-width:90vw;
    background:var(--bg-elevated);
    border:1px solid var(--border-default);
    border-radius:var(--border-radius-md);
    box-shadow:var(--shadow-lg);
    z-index:1031;
    overflow:hidden;
">
    <div style="padding:0.5rem;max-height:320px;overflow-y:auto;">
        <div style="padding:0.75rem;color:var(--text-muted);font-size:0.8rem;text-align:center;">
            <i class="bi bi-search me-1"></i> Type to search pages & features...
        </div>
    </div>
</div>

<script src="js/notifications.js?v=<?php echo time(); ?>"></script>
<script src="js/main.js?v=<?php echo time(); ?>"></script>
<script src="js/ui-enhancements.js?v=<?php echo time(); ?>"></script>
