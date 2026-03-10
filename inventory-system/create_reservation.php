<?php
require_once 'config/database.php';
require_once 'config/session.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: products.php");
    exit();
}

if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    die('Invalid CSRF token');
}

$database = new Database();
$db = $database->getConnection();

$product_id = (int)$_POST['product_id'];
$quantity = (int)$_POST['quantity'];
$customer_name = trim($_POST['customer_name'] ?? '');
$notes = trim($_POST['notes'] ?? '');

// Validate inputs
if ($product_id <= 0 || $quantity <= 0) {
    header("Location: products.php?error=" . urlencode("Invalid input data"));
    exit();
}

try {
    // Ensure reservations table exists with all required columns
    $db->exec("CREATE TABLE IF NOT EXISTS reservations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        reservation_id VARCHAR(50) UNIQUE,
        product_id INT NOT NULL,
        quantity INT NOT NULL,
        status ENUM('active', 'completed', 'cancelled') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
    )");
    
    // Add missing columns if they don't exist (ignore errors if columns already exist)
    $columns_to_add = [
        "ALTER TABLE reservations ADD COLUMN customer_name VARCHAR(100) NULL",
        "ALTER TABLE reservations ADD COLUMN notes TEXT NULL", 
        "ALTER TABLE reservations ADD COLUMN created_by INT NULL"
    ];
    
    foreach ($columns_to_add as $sql) {
        try {
            $db->exec($sql);
        } catch (Exception $e) {
            // Column already exists, continue
        }
    }
    
    // Start transaction AFTER table setup
    $db->beginTransaction();
    
    // Get product details and check stock
    $stmt = $db->prepare("SELECT * FROM products WHERE id = :id FOR UPDATE");
    $stmt->execute([':id' => $product_id]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$product) {
        throw new Exception("Product not found");
    }
    
    if ($product['stock_quantity'] < $quantity) {
        throw new Exception("Insufficient stock. Available: {$product['stock_quantity']}, Requested: {$quantity}");
    }
    
    // Generate unique reservation ID
    $reservation_id = 'RES-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
    
    // Check if reservation ID already exists
    $stmt = $db->prepare("SELECT id FROM reservations WHERE reservation_id = :res_id");
    $stmt->execute([':res_id' => $reservation_id]);
    
    while ($stmt->fetch()) {
        $reservation_id = 'RES-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
        $stmt->execute([':res_id' => $reservation_id]);
    }
    
    // Create reservation record
    $stmt = $db->prepare("INSERT INTO reservations (reservation_id, product_id, quantity, customer_name, notes, created_by) 
                          VALUES (:res_id, :pid, :qty, :customer, :notes, :user_id)");
    $stmt->execute([
        ':res_id' => $reservation_id,
        ':pid' => $product_id,
        ':qty' => $quantity,
        ':customer' => $customer_name ?: null,
        ':notes' => $notes ?: null,
        ':user_id' => $_SESSION['user_id']
    ]);
    
    // Update product stock
    $new_stock = $product['stock_quantity'] - $quantity;
    $stmt = $db->prepare("UPDATE products SET stock_quantity = :new_stock WHERE id = :id");
    $stmt->execute([':new_stock' => $new_stock, ':id' => $product_id]);
    
    // Log the inventory change
    $stmt = $db->prepare("INSERT INTO inventory_logs (product_id, action, quantity, user_id, notes) 
                          VALUES (:pid, 'stock_out', :qty, :uid, :notes)");
    $stmt->execute([
        ':pid' => $product_id,
        ':qty' => $quantity,
        ':uid' => $_SESSION['user_id'],
        ':notes' => "Reservation created: {$reservation_id}" . ($customer_name ? " for {$customer_name}" : "")
    ]);
    
    // Commit transaction
    $db->commit();
    
    // Calculate total value for success message
    $total_value = $product['price'] * $quantity;
    
    // Redirect with success message
    $success_message = urlencode("Reservation {$reservation_id} created successfully! Reserved {$quantity} units of {$product['product_name']} (Total: ₱" . number_format($total_value, 2) . ")");
    header("Location: products.php?success={$success_message}");
    exit();
    
} catch (Exception $e) {
    // Rollback transaction only if one is active
    if ($db->inTransaction()) {
        $db->rollback();
    }
    
    $error_message = urlencode("Error creating reservation: " . $e->getMessage());
    header("Location: products.php?error={$error_message}");
    exit();
}