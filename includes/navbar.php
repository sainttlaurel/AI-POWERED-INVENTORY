<nav class="navbar navbar-light fixed-top">
    <div class="container-fluid">
        <button class="navbar-toggler d-md-none" type="button" onclick="toggleSidebar()">
            <i class="bi bi-list"></i>
        </button>
        <a class="navbar-brand" href="dashboard.php">
            <i class="bi bi-boxes"></i> Inventory System
        </a>
        <div class="d-flex align-items-center">
            <!-- Minimal Notifications Icon -->
            <div class="notification-container me-3">
                <a href="notifications.php" class="notification-btn" id="notification-bell" title="View Notifications">
                    <i class="bi bi-bell notification-icon"></i>
                    <?php
                    // Get unread notification count
                    try {
                        require_once __DIR__ . '/../config/database.php';
                        $database = new Database();
                        $db = $database->getConnection();
                        
                        // Create notifications table if it doesn't exist
                        $db->exec("CREATE TABLE IF NOT EXISTS notifications (
                            id INT AUTO_INCREMENT PRIMARY KEY,
                            type ENUM('low_stock', 'reorder', 'sale', 'transfer', 'system') NOT NULL,
                            title VARCHAR(255) NOT NULL,
                            message TEXT NOT NULL,
                            user_id INT NULL,
                            is_read BOOLEAN DEFAULT FALSE,
                            priority ENUM('low', 'medium', 'high', 'critical') DEFAULT 'medium',
                            action_url VARCHAR(255) NULL,
                            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            INDEX idx_user_read (user_id, is_read),
                            INDEX idx_created (created_at)
                        )");
                        
                        $stmt = $db->prepare("SELECT COUNT(*) FROM notifications WHERE (user_id = ? OR user_id IS NULL) AND is_read = FALSE");
                        $stmt->execute([$_SESSION['user_id']]);
                        $unread_count = $stmt->fetchColumn();
                        if ($unread_count > 0): ?>
                            <span class="notification-badge" id="notification-count">
                                <?php echo $unread_count > 99 ? '99+' : $unread_count; ?>
                            </span>
                        <?php endif;
                    } catch (Exception $e) {
                        // Silently fail if notifications table doesn't exist yet
                        error_log("Navbar notification error: " . $e->getMessage());
                    }
                    ?>
                </a>
            </div>
            
            <span class="me-3" style="color: #64748b;">
                <i class="bi bi-person-circle"></i> <?php echo htmlspecialchars($_SESSION['username']); ?>
            </span>
            <a href="logout.php" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-box-arrow-right"></i> Logout
            </a>
        </div>
    </div>
</nav>

<!-- Include notification management script -->
<script src="js/notifications.js?v=<?php echo time(); ?>"></script>
