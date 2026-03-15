<?php
require_once 'config/database.php';
require_once 'config/session.php';

requireLogin();

$database = new Database();
$db = $database->getConnection();

// Create notifications table if it doesn't exist
try {
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
} catch (Exception $e) {
    error_log("Notifications table creation error: " . $e->getMessage());
}

// Get notifications for display
try {
    $stmt = $db->prepare("
        SELECT * FROM notifications 
        WHERE (user_id = ? OR user_id IS NULL) 
        ORDER BY created_at DESC 
        LIMIT 50
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get unread count
    $stmt = $db->prepare("
        SELECT COUNT(*) FROM notifications 
        WHERE (user_id = ? OR user_id IS NULL) 
        AND is_read = FALSE
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $unread_count = (int)$stmt->fetchColumn();
    
} catch (Exception $e) {
    $notifications = [];
    $unread_count = 0;
    error_log("Error fetching notifications: " . $e->getMessage());
}

// Calculate stats for dashboard cards
try {
    // Low stock count
    $stmt = $db->prepare("SELECT COUNT(*) FROM products WHERE stock_quantity <= reorder_level AND stock_quantity > 0");
    $stmt->execute();
    $low_stock_count = (int)$stmt->fetchColumn();
    
    // Out of stock count
    $stmt = $db->prepare("SELECT COUNT(*) FROM products WHERE stock_quantity = 0");
    $stmt->execute();
    $out_of_stock_count = (int)$stmt->fetchColumn();
    
    // Total products
    $stmt = $db->prepare("SELECT COUNT(*) FROM products");
    $stmt->execute();
    $total_products = (int)$stmt->fetchColumn();
    
    // Recent sales (last 24 hours)
    $stmt = $db->prepare("SELECT COUNT(*) FROM sales WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)");
    $stmt->execute();
    $recent_sales = (int)$stmt->fetchColumn();
    
    // Active users (logged in last 24 hours)
    $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE last_login >= DATE_SUB(NOW(), INTERVAL 24 HOUR)");
    $stmt->execute();
    $active_users = (int)$stmt->fetchColumn();
    
    // Pending transfers/notifications
    $pending_transfers = $unread_count;
    
} catch (Exception $e) {
    $low_stock_count = 0;
    $out_of_stock_count = 0;
    $total_products = 0;
    $recent_sales = 0;
    $active_users = 0;
    $pending_transfers = 0;
    error_log("Error calculating stats: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - Inventory System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="css/style.css?v=<?php echo time(); ?>">
</head>
<body>
    <?php include 'includes/navbar.php'; ?>
    
    <div class="container-fluid">
        <div class="sidebar-overlay"></div>
        <?php include 'includes/sidebar.php'; ?>
        
        <main>
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-3 mb-4">
                <div>
                    <h1 class="h2 mb-1 d-flex align-items-center">
                        <i class="bi bi-bell me-2 text-primary"></i> Notifications 
                        <?php if ($unread_count > 0): ?>
                            <span class="badge bg-danger rounded-pill ms-2" id="unread-badge"><?php echo $unread_count; ?></span>
                        <?php endif; ?>
                    </h1>
                    <p class="text-muted mb-0">Stay updated with your inventory alerts</p>
                </div>
                <div class="d-flex flex-wrap gap-2 align-items-center">
                    <?php if ($unread_count > 0): ?>
                        <button class="btn btn-success btn-sm" onclick="markAllAsRead()" id="mark-all-btn">
                            <i class="bi bi-check-all me-1"></i> Mark All Read
                        </button>
                    <?php endif; ?>
                    <button class="btn btn-outline-secondary btn-sm" onclick="checkLowStock()">
                        <i class="bi bi-search me-1"></i> Check Stock
                    </button>
                </div>
            </div>

            <!-- Quick Stats -->
            <div class="row g-3 mb-4">
                <div class="col-md-3 col-sm-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body text-center p-3">
                            <div class="text-warning mb-2">
                                <i class="bi bi-exclamation-triangle fs-2"></i>
                            </div>
                            <h4 class="text-warning mb-1 fw-bold" id="low-stock-count"><?php echo $low_stock_count; ?></h4>
                            <small class="text-muted">Low Stock Items</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body text-center p-3">
                            <div class="text-danger mb-2">
                                <i class="bi bi-x-circle fs-2"></i>
                            </div>
                            <h4 class="text-danger mb-1 fw-bold" id="out-of-stock-count"><?php echo $out_of_stock_count; ?></h4>
                            <small class="text-muted">Out of Stock</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body text-center p-3">
                            <div class="text-success mb-2">
                                <i class="bi bi-cart-check fs-2"></i>
                            </div>
                            <h4 class="text-success mb-1 fw-bold" id="recent-sales"><?php echo $recent_sales; ?></h4>
                            <small class="text-muted">Recent Sales</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body text-center p-3">
                            <div class="text-info mb-2">
                                <i class="bi bi-box-seam fs-2"></i>
                            </div>
                            <h4 class="text-info mb-1 fw-bold" id="total-products"><?php echo $total_products; ?></h4>
                            <small class="text-muted">Total Products</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Notifications List -->
            <div class="row">
                <div class="col-12">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center py-3">
                            <h5 class="mb-0 fw-semibold">Recent Notifications</h5>
                            <div class="d-flex align-items-center gap-3">
                                <span class="badge rounded-pill bg-success d-flex align-items-center" id="connection-status">
                                    <i class="bi bi-wifi me-1"></i> Connected
                                </span>
                                <small class="text-muted">
                                    Last updated: <span id="last-updated" class="fw-medium"><?php echo date('H:i:s'); ?></span>
                                </small>
                            </div>
                        </div>
                        <div class="card-body p-0">
                            <div id="notifications-container">
                                <?php if (empty($notifications)): ?>
                                    <div class="text-center py-5" id="no-notifications">
                                        <div class="mb-3">
                                            <i class="bi bi-bell-slash text-muted" style="font-size: 3rem; opacity: 0.3;"></i>
                                        </div>
                                        <h5 class="text-muted mb-2">No notifications yet</h5>
                                        <p class="text-muted small mb-0">You'll see your notifications here when they arrive</p>
                                    </div>
                                <?php else: ?>
                                    <div class="list-group list-group-flush" id="notifications-list">
                                        <?php foreach ($notifications as $index => $notification): ?>
                                            <div class="list-group-item border-0 py-3 <?php echo $notification['is_read'] ? '' : 'bg-light border-start border-primary border-3'; ?>" 
                                                 id="notification-<?php echo $notification['id']; ?>" 
                                                 data-id="<?php echo $notification['id']; ?>">
                                                <div class="d-flex align-items-start">
                                                    <div class="flex-shrink-0 me-3">
                                                        <div class="rounded-circle p-2 d-flex align-items-center justify-content-center <?php 
                                                            echo $notification['type'] === 'low_stock' ? 'bg-warning bg-opacity-20' : 
                                                                ($notification['type'] === 'reorder' ? 'bg-info bg-opacity-20' : 
                                                                ($notification['type'] === 'sale' ? 'bg-success bg-opacity-20' : 
                                                                ($notification['type'] === 'transfer' ? 'bg-primary bg-opacity-20' : 'bg-secondary bg-opacity-20'))); 
                                                        ?>" style="width: 36px; height: 36px;">
                                                            <i class="bi bi-<?php 
                                                                echo $notification['type'] === 'low_stock' ? 'exclamation-triangle text-warning' : 
                                                                    ($notification['type'] === 'reorder' ? 'arrow-repeat text-info' : 
                                                                    ($notification['type'] === 'sale' ? 'cart text-success' : 
                                                                    ($notification['type'] === 'transfer' ? 'arrow-left-right text-primary' : 'info-circle text-secondary'))); 
                                                            ?>" style="font-size: 14px;"></i>
                                                        </div>
                                                    </div>
                                                    <div class="flex-grow-1">
                                                        <div class="d-flex justify-content-between align-items-start mb-1">
                                                            <h6 class="mb-0 fw-semibold">
                                                                <?php echo htmlspecialchars($notification['title']); ?>
                                                                <?php if ($notification['priority'] === 'critical'): ?>
                                                                    <span class="badge bg-danger ms-2 small">CRITICAL</span>
                                                                <?php elseif ($notification['priority'] === 'high'): ?>
                                                                    <span class="badge bg-warning ms-2 small">HIGH</span>
                                                                <?php endif; ?>
                                                                <?php if (!$notification['is_read']): ?>
                                                                    <span class="badge bg-primary ms-2 small">NEW</span>
                                                                <?php endif; ?>
                                                            </h6>
                                                            <small class="text-muted">
                                                                <?php echo date('M d, H:i', strtotime($notification['created_at'])); ?>
                                                            </small>
                                                        </div>
                                                        <p class="mb-2 text-muted small"><?php echo htmlspecialchars($notification['message']); ?></p>
                                                        <div class="d-flex gap-2">
                                                            <?php if ($notification['action_url']): ?>
                                                                <a href="<?php echo htmlspecialchars($notification['action_url']); ?>" class="btn btn-sm btn-outline-primary">
                                                                    <i class="bi bi-eye me-1"></i> View
                                                                </a>
                                                            <?php endif; ?>
                                                            <?php if (!$notification['is_read']): ?>
                                                                <button class="btn btn-sm btn-success" onclick="markAsRead(<?php echo $notification['id']; ?>)">
                                                                    <i class="bi bi-check me-1"></i> Mark Read
                                                                </button>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
    <?php include 'includes/chatbot.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/chatbot.js"></script>
    <script>
        // Mark single notification as read
        function markAsRead(notificationId) {
            fetch(`api/notifications.php?action=mark_read&notification_id=${notificationId}`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const notification = document.getElementById(`notification-${notificationId}`);
                    if (notification) {
                        notification.classList.remove('bg-light', 'border-start', 'border-primary', 'border-3');
                        const button = notification.querySelector('button[onclick*="markAsRead"]');
                        if (button) button.remove();
                        
                        // Remove NEW badge
                        const newBadge = notification.querySelector('.badge.bg-primary');
                        if (newBadge && newBadge.textContent === 'NEW') newBadge.remove();
                    }
                    updateUnreadCount();
                } else {
                    console.error('Error marking notification as read:', data.error);
                }
            })
            .catch(error => {
                console.error('Error marking notification as read:', error);
            });
        }

        // Mark all notifications as read
        function markAllAsRead() {
            fetch('api/notifications.php?action=mark_all_read', {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.querySelectorAll('.bg-light.border-start').forEach(item => {
                        item.classList.remove('bg-light', 'border-start', 'border-primary', 'border-3');
                    });
                    document.querySelectorAll('button[onclick*="markAsRead"]').forEach(btn => {
                        btn.remove();
                    });
                    document.querySelectorAll('.badge.bg-primary').forEach(badge => {
                        if (badge.textContent === 'NEW') badge.remove();
                    });
                    updateUnreadCount();
                } else {
                    console.error('Error marking all notifications as read:', data.error);
                }
            })
            .catch(error => {
                console.error('Error marking all notifications as read:', error);
            });
        }
        // Check for low stock
        function checkLowStock() {
            const button = event.target;
            const originalText = button.innerHTML;
            button.innerHTML = '<i class="bi bi-hourglass-split"></i> Checking...';
            button.disabled = true;
            
            fetch('api/notifications.php?action=check_low_stock', {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    if (data.alerts_created > 0) {
                        alert(`Created ${data.alerts_created} new low stock alerts`);
                        location.reload();
                    } else {
                        alert('No new low stock alerts needed');
                    }
                } else {
                    console.error('Error checking low stock:', data.error);
                }
            })
            .catch(error => {
                console.error('Error checking low stock:', error);
            })
            .finally(() => {
                button.innerHTML = originalText;
                button.disabled = false;
            });
        }

        // Update unread count
        function updateUnreadCount() {
            fetch('api/notifications.php?action=get_unread_count', {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const count = data.count;
                    
                    // Update page badge
                    const badge = document.getElementById('unread-badge');
                    const markAllBtn = document.getElementById('mark-all-btn');
                    
                    if (count > 0) {
                        if (badge) {
                            badge.textContent = count;
                            badge.style.display = 'inline';
                        }
                        if (markAllBtn) markAllBtn.style.display = 'inline-block';
                    } else {
                        if (badge) badge.style.display = 'none';
                        if (markAllBtn) markAllBtn.style.display = 'none';
                    }
                    
                    // Update notification count using unified system
                    if (window.NotificationManager) {
                        window.NotificationManager.updateNotificationCount(count);
                    }
                }
            })
            .catch(error => {
                console.error('Error updating unread count:', error);
            });
        }

        // Update system stats
        function updateSystemStats() {
            fetch('api/notifications.php?action=get_system_stats', {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('low-stock-count').textContent = data.low_stock_count || 0;
                    document.getElementById('out-of-stock-count').textContent = data.out_of_stock_count || 0;
                    document.getElementById('total-products').textContent = data.total_products || 0;
                    document.getElementById('recent-sales').textContent = data.recent_sales || 0;
                }
            })
            .catch(error => {
                console.error('Error updating system stats:', error);
            });
        }

        // Update connection status
        function updateConnectionStatus(connected) {
            const statusElement = document.getElementById('connection-status');
            
            if (connected) {
                statusElement.innerHTML = '<i class="bi bi-wifi me-1"></i> Connected';
                statusElement.className = 'badge rounded-pill bg-success d-flex align-items-center';
            } else {
                statusElement.innerHTML = '<i class="bi bi-wifi-off me-1"></i> Disconnected';
                statusElement.className = 'badge rounded-pill bg-danger d-flex align-items-center';
            }
        }

        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            updateUnreadCount();
            updateSystemStats();
            updateConnectionStatus(true);
            
            // Auto-refresh every 30 seconds
            setInterval(() => {
                updateUnreadCount();
                updateSystemStats();
                document.getElementById('last-updated').textContent = new Date().toLocaleTimeString();
            }, 30000);
        });
    </script>
    <script src="js/chatbot.js?v=<?php echo time(); ?>"></script>
    <script src="js/mobile.js?v=<?php echo time(); ?>"></script>
</body>
</html>