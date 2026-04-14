<?php
require_once '../config/database.php';
require_once '../config/session.php';
requireLogin();

header('Content-Type: application/json');

$barcode = $_GET['barcode'] ?? '';

if (empty($barcode)) {
    echo json_encode(['success' => false, 'message' => 'Barcode is required']);
    exit;
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    $stmt = $db->prepare("SELECT * FROM products WHERE barcode = ? LIMIT 1");
    $stmt->execute([$barcode]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($product) {
        // Enforce the expected mapping for Invoice Cart
        echo json_encode([
            'success' => true,
            'product' => [
                'id' => $product['id'],
                'product_name' => $product['product_name'],
                'price' => $product['price'],
                'stock_quantity' => $product['stock_quantity']
            ]
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Product not found']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
