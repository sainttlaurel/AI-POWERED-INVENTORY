<?php
require_once '../config/database.php';
require_once '../config/session.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$query = trim($_GET['q'] ?? '');

if (strlen($query) < 2) {
    echo json_encode(['success' => false, 'message' => 'Query too short']);
    exit();
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    $search_term = "%{$query}%";
    
    $stmt = $db->prepare("
        SELECT p.id, p.product_name, p.barcode, p.stock_quantity, p.reorder_level, p.price, c.name as category_name
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE p.product_name LIKE ? 
           OR p.barcode LIKE ?
           OR c.name LIKE ?
        ORDER BY 
            CASE 
                WHEN p.product_name LIKE ? THEN 1
                WHEN p.barcode LIKE ? THEN 2
                ELSE 3
            END,
            p.product_name
        LIMIT 10
    ");
    
    $stmt->execute([$search_term, $search_term, $search_term, $search_term, $search_term]);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'products' => $products
    ]);
    
} catch (Exception $e) {
    error_log("Search products error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Search error'
    ]);
}