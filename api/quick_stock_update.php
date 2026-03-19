<?php
require_once '../config/database.php';
require_once '../config/session.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

$product_id = (int)($_POST['product_id'] ?? 0);
$action = $_POST['action'] ?? '';
$quantity = (int)($_POST['quantity'] ?? 0);
$notes = trim($_POST['notes'] ?? '');

if ($product_id <= 0 || !in_array($action, ['stock_in', 'stock_out']) || $quantity <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid input data']);
    exit();
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    $db->beginTransaction();
    
    // Get current product info
    $stmt = $db->prepare("SELECT product_name, stock_quantity FROM products WHERE id = ?");
    $stmt->execute([$product_id]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$product) {
        throw new Exception('Product not found');
    }
    
    // Calculate new stock quantity
    $current_stock = $product['stock_quantity'];
    $new_stock = $action === 'stock_in' ? $current_stock + $quantity : $current_stock - $quantity;
    
    // Prevent negative stock
    if ($new_stock < 0) {
        throw new Exception('Insufficient stock. Current stock: ' . $current_stock);
    }
    
    // Update product stock
    $stmt = $db->prepare("UPDATE products SET stock_quantity = ? WHERE id = ?");
    $stmt->execute([$new_stock, $product_id]);
    
    // Log the inventory change
    $log_notes = $notes ?: "Quick QR update via scanner";
    $stmt = $db->prepare("
        INSERT INTO inventory_logs (product_id, action, quantity, user_id, notes, created_at)
        VALUES (?, ?, ?, ?, ?, NOW())
    ");
    $stmt->execute([$product_id, $action, $quantity, $_SESSION['user_id'], $log_notes]);
    
    $db->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Stock updated successfully',
        'data' => [
            'product_name' => $product['product_name'],
            'old_stock' => $current_stock,
            'new_stock' => $new_stock,
            'action' => $action,
            'quantity' => $quantity
        ]
    ]);
    
} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollback();
    }
    
    error_log("Quick stock update error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}