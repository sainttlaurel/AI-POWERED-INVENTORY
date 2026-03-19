<?php
require_once '../config/database.php';
require_once '../config/session.php';

class NotificationAPI {
    private $db;
    private $user_id;
    
    public function __construct() {
        // Ensure session is started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (!isset($_SESSION['user_id'])) {
            $this->sendError('Unauthorized access - Please log in', 401);
        }
        
        $database = new Database();
        $this->db = $database->getConnection();
        
        if (!$this->db) {
            $this->sendError('Database connection failed', 500);
        }
        
        $this->user_id = $_SESSION['user_id'];
        
        header('Content-Type: application/json');
        header('Cache-Control: no-cache, must-revalidate');
    }
    
    private function sendSuccess($data = [], $message = 'Success') {
        echo json_encode([
            'success' => true,
            'message' => $message,
            'data' => $data,
            'timestamp' => date('c')
        ]);
        exit();
    }
    
    private function sendError($message, $code = 400) {
        http_response_code($code);
        echo json_encode([
            'success' => false,
            'error' => $message,
            'timestamp' => date('c')
        ]);
        exit();
    }
    
    public function getNotifications() {
        $limit = min((int)($_GET['limit'] ?? 50), 100);
        $last_id = (int)($_GET['last_id'] ?? 0);
        
        try {
            $query = "
                SELECT 
                    id, type, title, message, priority, is_read, action_url, created_at,
                    DATE_FORMAT(created_at, '%M %d, %Y at %h:%i %p') as formatted_date,
                    CASE 
                        WHEN created_at >= DATE_SUB(NOW(), INTERVAL 1 MINUTE) THEN 'Just now'
                        WHEN created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR) THEN CONCAT(TIMESTAMPDIFF(MINUTE, created_at, NOW()), ' min ago')
                        WHEN created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN CONCAT(TIMESTAMPDIFF(HOUR, created_at, NOW()), ' hr ago')
                        WHEN created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN CONCAT(TIMESTAMPDIFF(DAY, created_at, NOW()), ' day', IF(TIMESTAMPDIFF(DAY, created_at, NOW()) > 1, 's', ''), ' ago')
                        ELSE DATE_FORMAT(created_at, '%M %d, %Y')
                    END as time_ago
                FROM notifications 
                WHERE (user_id = ? OR user_id IS NULL)
            ";
            
            $params = [$this->user_id];
            
            if ($last_id > 0) {
                $query .= " AND id > ?";
                $params[] = $last_id;
            }
            
            $query .= " ORDER BY created_at DESC, id DESC LIMIT ?";
            $params[] = $limit;
            
            $stmt = $this->db->prepare($query);
            $stmt->execute($params);
            $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $this->sendSuccess([
                'notifications' => $notifications,
                'count' => count($notifications),
                'has_more' => count($notifications) === $limit
            ]);
            
        } catch (Exception $e) {
            error_log("Get notifications error: " . $e->getMessage());
            $this->sendError('Failed to retrieve notifications');
        }
    }
    
    public function getUnreadCount() {
        try {
            $stmt = $this->db->prepare("
                SELECT COUNT(*) 
                FROM notifications 
                WHERE (user_id = ? OR user_id IS NULL) 
                AND is_read = FALSE
            ");
            $stmt->execute([$this->user_id]);
            $count = (int)$stmt->fetchColumn();
            
            $this->sendSuccess(['count' => $count]);
            
        } catch (Exception $e) {
            error_log("Get unread count error: " . $e->getMessage());
            $this->sendError('Failed to get unread count');
        }
    }
    
    public function markAsRead() {
        $notification_id = (int)($_GET['notification_id'] ?? $_POST['notification_id'] ?? 0);
        
        if ($notification_id <= 0) {
            $this->sendError('Invalid notification ID');
        }
        
        try {
            $stmt = $this->db->prepare("
                UPDATE notifications 
                SET is_read = TRUE 
                WHERE id = ? AND (user_id = ? OR user_id IS NULL)
            ");
            $success = $stmt->execute([$notification_id, $this->user_id]);
            
            if ($success && $stmt->rowCount() > 0) {
                $this->sendSuccess([], 'Notification marked as read');
            } else {
                $this->sendError('Notification not found');
            }
            
        } catch (Exception $e) {
            error_log("Mark as read error: " . $e->getMessage());
            $this->sendError('Failed to mark notification as read');
        }
    }
    
    public function markAllAsRead() {
        try {
            $stmt = $this->db->prepare("
                UPDATE notifications 
                SET is_read = TRUE 
                WHERE (user_id = ? OR user_id IS NULL) AND is_read = FALSE
            ");
            $stmt->execute([$this->user_id]);
            $affected_rows = $stmt->rowCount();
            
            $this->sendSuccess(['marked_count' => $affected_rows]);
            
        } catch (Exception $e) {
            error_log("Mark all as read error: " . $e->getMessage());
            $this->sendError('Failed to mark all notifications as read');
        }
    }
    
    public function checkLowStock() {
        try {
            $stmt = $this->db->prepare("
                SELECT p.id, p.product_name, p.stock_quantity, p.reorder_level
                FROM products p 
                WHERE p.stock_quantity <= p.reorder_level 
                AND p.id NOT IN (
                    SELECT CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(message, 'ID: ', -1), ')', 1) AS UNSIGNED)
                    FROM notifications 
                    WHERE type = 'low_stock' 
                    AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
                    AND message LIKE CONCAT('%ID: ', p.id, ')%')
                )
                LIMIT 20
            ");
            $stmt->execute();
            $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $alerts_created = 0;
            
            foreach ($products as $product) {
                $is_critical = $product['stock_quantity'] == 0;
                $title = $is_critical ? '🚨 Out of Stock' : '⚠️ Low Stock';
                $priority = $is_critical ? 'critical' : 'high';
                
                $message = sprintf(
                    "Product '%s' (ID: %d) has %d units. Reorder level: %d",
                    $product['product_name'],
                    $product['id'],
                    $product['stock_quantity'],
                    $product['reorder_level']
                );
                
                $insert_stmt = $this->db->prepare("
                    INSERT INTO notifications (type, title, message, user_id, priority, action_url, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, NOW())
                ");
                
                if ($insert_stmt->execute([
                    'low_stock', $title, $message, null, $priority, 
                    "products.php?highlight={$product['id']}"
                ])) {
                    $alerts_created++;
                }
            }
            
            $this->sendSuccess(['alerts_created' => $alerts_created]);
            
        } catch (Exception $e) {
            error_log("Check low stock error: " . $e->getMessage());
            $this->sendError('Failed to check low stock');
        }
    }
    
    public function getSystemStats() {
        try {
            // Basic stats only to avoid complex queries
            $stats = [];
            
            $stmt = $this->db->query("SELECT COUNT(*) FROM products");
            $stats['total_products'] = (int)$stmt->fetchColumn();
            
            $stmt = $this->db->query("SELECT COUNT(*) FROM products WHERE stock_quantity = 0");
            $stats['out_of_stock_count'] = (int)$stmt->fetchColumn();
            
            $stmt = $this->db->query("SELECT COUNT(*) FROM products WHERE stock_quantity <= reorder_level AND stock_quantity > 0");
            $stats['low_stock_count'] = (int)$stmt->fetchColumn();
            
            // Set defaults for other stats
            $stats['recent_sales'] = 0;
            $stats['active_users'] = 1;
            $stats['pending_transfers'] = $stats['low_stock_count'];
            
            $this->sendSuccess($stats);
            
        } catch (Exception $e) {
            error_log("Get system stats error: " . $e->getMessage());
            $this->sendError('Failed to retrieve system statistics');
        }
    }
}

// Handle requests
try {
    $api = new NotificationAPI();
    $action = $_GET['action'] ?? $_POST['action'] ?? '';
    
    switch ($action) {
        case 'get_notifications':
            $api->getNotifications();
            break;
        case 'get_unread_count':
            $api->getUnreadCount();
            break;
        case 'mark_read':
            $api->markAsRead();
            break;
        case 'mark_all_read':
            $api->markAllAsRead();
            break;
        case 'check_low_stock':
            $api->checkLowStock();
            break;
        case 'get_system_stats':
            $api->getSystemStats();
            break;
        default:
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'Invalid action',
                'available_actions' => [
                    'get_notifications', 'get_unread_count', 'mark_read', 
                    'mark_all_read', 'check_low_stock', 'get_system_stats'
                ]
            ]);
    }
    
} catch (Exception $e) {
    error_log("Notifications API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Internal server error'
    ]);
}