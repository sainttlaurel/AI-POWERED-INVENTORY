<?php
session_start();

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header("Location: login.php");
        exit();
    }
}

function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Activity logging function
function logUserActivity($action, $details = null, $user_id = null) {
    try {
        require_once __DIR__ . '/database.php';
        $database = new Database();
        $db = $database->getConnection();
        
        $user_id = $user_id ?? ($_SESSION['user_id'] ?? null);
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        
        $stmt = $db->prepare("INSERT INTO user_activity_log (user_id, action, details, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$user_id, $action, $details, $ip_address, $user_agent]);
        
        // Create notifications for important activities
        createActivityNotification($action, $details, $user_id, $db);
        
    } catch (Exception $e) {
        // Silently fail to avoid breaking the application
        error_log("Activity logging error: " . $e->getMessage());
    }
}

// Create notifications for important activities
function createActivityNotification($action, $details, $user_id, $db) {
    try {
        $notification_actions = [
            'login' => ['type' => 'system', 'priority' => 'low'],
            'create_user' => ['type' => 'system', 'priority' => 'medium'],
            'update_user' => ['type' => 'system', 'priority' => 'medium'],
            'reset_password' => ['type' => 'system', 'priority' => 'high'],
            'product_added' => ['type' => 'system', 'priority' => 'medium'],
            'stock_updated' => ['type' => 'system', 'priority' => 'medium'],
            'sale_completed' => ['type' => 'sale', 'priority' => 'low']
        ];
        
        if (isset($notification_actions[$action])) {
            $config = $notification_actions[$action];
            
            // Get username for display
            $username = 'System';
            if ($user_id) {
                $stmt = $db->prepare("SELECT username FROM users WHERE id = ?");
                $stmt->execute([$user_id]);
                $username = $stmt->fetchColumn() ?: 'Unknown User';
            }
            
            $title = ucwords(str_replace('_', ' ', $action));
            $message = "$username: $details";
            
            // Determine who should see this notification
            $target_user_id = null; // Default: all users
            
            // Login notifications should only be visible to admins
            if ($action === 'login') {
                // Get all admin user IDs
                $stmt = $db->prepare("SELECT id FROM users WHERE role = 'admin'");
                $stmt->execute();
                $admin_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
                
                // Create separate notification for each admin
                foreach ($admin_ids as $admin_id) {
                    $stmt = $db->prepare("INSERT INTO notifications (type, title, message, user_id, priority) VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([$config['type'], $title, $message, $admin_id, $config['priority']]);
                }
                return; // Don't create a general notification for login
            }
            
            // User management activities should only be visible to admins
            if (in_array($action, ['create_user', 'update_user', 'reset_password'])) {
                // Get all admin user IDs
                $stmt = $db->prepare("SELECT id FROM users WHERE role = 'admin'");
                $stmt->execute();
                $admin_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
                
                // Create separate notification for each admin
                foreach ($admin_ids as $admin_id) {
                    $stmt = $db->prepare("INSERT INTO notifications (type, title, message, user_id, priority) VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([$config['type'], $title, $message, $admin_id, $config['priority']]);
                }
                return; // Don't create a general notification
            }
            
            // Product and stock activities are relevant to all users
            if (in_array($action, ['product_added', 'stock_updated'])) {
                $target_user_id = null; // All users
            }
            
            // Sale notifications are only relevant to managers and admins
            if ($action === 'sale_completed') {
                // Get manager and admin user IDs
                $stmt = $db->prepare("SELECT id FROM users WHERE role IN ('admin', 'manager')");
                $stmt->execute();
                $manager_admin_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
                
                // Create separate notification for each manager/admin
                foreach ($manager_admin_ids as $manager_admin_id) {
                    $stmt = $db->prepare("INSERT INTO notifications (type, title, message, user_id, priority) VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([$config['type'], $title, $message, $manager_admin_id, $config['priority']]);
                }
                return; // Don't create a general notification
            }
            
            // Create general notification for remaining actions
            if ($target_user_id !== false) {
                $stmt = $db->prepare("INSERT INTO notifications (type, title, message, user_id, priority) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$config['type'], $title, $message, $target_user_id, $config['priority']]);
            }
        }
        
    } catch (Exception $e) {
        error_log("Notification creation error: " . $e->getMessage());
    }
}

// Auto-check for low stock and create notifications
function autoCheckLowStock() {
    try {
        require_once __DIR__ . '/database.php';
        $database = new Database();
        $db = $database->getConnection();
        
        // Only check every 30 minutes to avoid spam
        if (!isset($_SESSION['last_stock_check']) || (time() - $_SESSION['last_stock_check']) > 1800) {
            $stmt = $db->prepare("
                SELECT p.*, c.name as category_name 
                FROM products p 
                LEFT JOIN categories c ON p.category_id = c.id 
                WHERE p.stock_quantity <= p.reorder_level 
                AND p.id NOT IN (
                    SELECT SUBSTRING_INDEX(SUBSTRING_INDEX(message, 'ID: ', -1), ' ', 1) 
                    FROM notifications 
                    WHERE type = 'low_stock' 
                    AND created_at > DATE_SUB(NOW(), INTERVAL 2 HOUR)
                )
                LIMIT 5
            ");
            $stmt->execute();
            $low_stock_products = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($low_stock_products as $product) {
                $priority = $product['stock_quantity'] == 0 ? 'critical' : 'high';
                $title = $product['stock_quantity'] == 0 ? 'Out of Stock Alert' : 'Low Stock Alert';
                $message = "Product '{$product['product_name']}' (ID: {$product['id']}) has {$product['stock_quantity']} units remaining. Reorder level: {$product['reorder_level']}";
                
                $stmt = $db->prepare("INSERT INTO notifications (type, title, message, user_id, priority, action_url) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute(['low_stock', $title, $message, null, $priority, "products.php?highlight={$product['id']}"]);
            }
            
            $_SESSION['last_stock_check'] = time();
        }
        
    } catch (Exception $e) {
        error_log("Auto stock check error: " . $e->getMessage());
    }
}

// Auto-log page views for logged-in users
if (isLoggedIn() && !isset($_SESSION['activity_logged'])) {
    $current_page = basename($_SERVER['PHP_SELF'], '.php');
    
    // Only log certain pages to avoid spam
    $pages_to_log = ['dashboard', 'user_management', 'products', 'inventory', 'reports', 'locations', 'notifications'];
    
    if (in_array($current_page, $pages_to_log)) {
        logUserActivity('view_page', "Viewed $current_page page");
        $_SESSION['activity_logged'] = true;
        
        // Reset the flag after 5 minutes to allow re-logging
        $_SESSION['activity_reset_time'] = time() + 300;
        
        // Auto-check for low stock when viewing relevant pages
        if (in_array($current_page, ['dashboard', 'products', 'inventory', 'notifications'])) {
            autoCheckLowStock();
        }
    }
}

// Reset activity logging flag if enough time has passed
if (isset($_SESSION['activity_reset_time']) && time() > $_SESSION['activity_reset_time']) {
    unset($_SESSION['activity_logged']);
    unset($_SESSION['activity_reset_time']);
}
