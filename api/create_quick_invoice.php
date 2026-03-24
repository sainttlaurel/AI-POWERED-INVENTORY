<?php
header('Content-Type: application/json');
require_once '../config/database.php';
require_once '../config/session.php';

requireLogin();

$database = new Database();
$db = $database->getConnection();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

try {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['customer_name']) || !isset($data['items']) || empty($data['items'])) {
        throw new Exception('Missing required fields');
    }
    
    $db->beginTransaction();
    
    // Generate invoice number
    $invoice_number = 'INV-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
    
    // Calculate totals
    $subtotal = 0;
    foreach ($data['items'] as $item) {
        $subtotal += $item['quantity'] * $item['price'];
    }
    
    $tax_rate = floatval($data['tax_rate'] ?? 0);
    $discount = floatval($data['discount'] ?? 0);
    $tax_amount = ($subtotal - $discount) * ($tax_rate / 100);
    $total = $subtotal - $discount + $tax_amount;
    
    // Insert invoice
    $stmt = $db->prepare("INSERT INTO invoices (invoice_number, customer_name, customer_email, customer_phone, customer_address, subtotal, tax_rate, tax_amount, discount_amount, total_amount, payment_status, payment_method, notes, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    
    $stmt->execute([
        $invoice_number,
        $data['customer_name'],
        $data['customer_email'] ?? null,
        $data['customer_phone'] ?? null,
        $data['customer_address'] ?? null,
        $subtotal,
        $tax_rate,
        $tax_amount,
        $discount,
        $total,
        $data['payment_status'] ?? 'pending',
        $data['payment_method'] ?? null,
        $data['notes'] ?? null,
        $_SESSION['user_id']
    ]);
    
    $invoice_id = $db->lastInsertId();
    
    // Insert invoice items and update stock
    $stmt_item = $db->prepare("INSERT INTO invoice_items (invoice_id, product_id, product_name, quantity, unit_price, subtotal, stock_status) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt_product = $db->prepare("SELECT product_name, stock_quantity FROM products WHERE id = ?");
    $stmt_update = $db->prepare("UPDATE products SET stock_quantity = stock_quantity - ? WHERE id = ?");
    $stmt_log = $db->prepare("INSERT INTO inventory_logs (product_id, action, quantity, user_id, notes) VALUES (?, 'stock_out', ?, ?, ?)");
    
    foreach ($data['items'] as $item) {
        $product_id = $item['product_id'];
        $quantity = $item['quantity'];
        $price = $item['price'];
        
        // Get product info
        $stmt_product->execute([$product_id]);
        $product = $stmt_product->fetch(PDO::FETCH_ASSOC);
        
        if (!$product) {
            throw new Exception("Product ID $product_id not found");
        }
        
        $stock_status = $product['stock_quantity'] >= $quantity ? 'in_stock' : 'out_of_stock';
        
        // Insert invoice item
        $stmt_item->execute([
            $invoice_id,
            $product_id,
            $product['product_name'],
            $quantity,
            $price,
            $quantity * $price,
            $stock_status
        ]);
        
        // Update stock only if in stock
        if ($stock_status === 'in_stock') {
            $stmt_update->execute([$quantity, $product_id]);
            $stmt_log->execute([$product_id, $quantity, $_SESSION['user_id'], "Invoice: $invoice_number"]);
        }
    }
    
    $db->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Invoice created successfully',
        'invoice_id' => $invoice_id,
        'invoice_number' => $invoice_number
    ]);
    
} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
