<?php
require_once 'config/database.php';
require_once 'config/session.php';
requireLogin();

$database = new Database();
$db = $database->getConnection();

// Check if required tables exist
try {
    // Check for inventory_logs table
    $db->query("SHOW TABLES LIKE 'inventory_logs'")->fetch();
    
    // Create inventory_logs table if it doesn't exist
    $db->exec("CREATE TABLE IF NOT EXISTS inventory_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        product_id INT NOT NULL,
        action ENUM('stock_in', 'stock_out') NOT NULL,
        quantity INT NOT NULL,
        user_id INT NULL,
        notes TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_product (product_id),
        INDEX idx_created (created_at)
    )");
    
    // Create sales table if it doesn't exist
    $db->exec("CREATE TABLE IF NOT EXISTS sales (
        id INT AUTO_INCREMENT PRIMARY KEY,
        product_id INT NULL,
        quantity INT NULL,
        total_price DECIMAL(10,2) NULL,
        customer_name VARCHAR(255) NULL,
        customer_email VARCHAR(255) NULL,
        customer_phone VARCHAR(20) NULL,
        payment_method ENUM('cash', 'card', 'digital_wallet', 'bank_transfer') DEFAULT 'cash',
        total_amount DECIMAL(10,2) NULL,
        notes TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_product (product_id),
        INDEX idx_created (created_at),
        INDEX idx_customer (customer_name)
    )");
    
    // Create sale_items table for multi-item sales
    $db->exec("CREATE TABLE IF NOT EXISTS sale_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        sale_id INT NOT NULL,
        product_id INT NOT NULL,
        quantity INT NOT NULL,
        unit_price DECIMAL(10,2) NOT NULL,
        total_price DECIMAL(10,2) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_sale (sale_id),
        INDEX idx_product (product_id)
    )");
    
} catch (Exception $e) {
    error_log("Inventory tables creation error: " . $e->getMessage());
}
// Handle stock operations
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        // More user-friendly CSRF error handling
        $_SESSION['csrf_error'] = true;
        header("Location: inventory.php?error=" . urlencode("Security token expired. Please try again."));
        exit();
    }
    
    try {
        $action = $_POST['action'];
        
        // Start transaction
        $db->beginTransaction();
        
        if ($action === 'stock_in') {
            $product_id = (int)$_POST['product_id'];
            $quantity = (int)$_POST['quantity'];
            $notes = $_POST['notes'] ?? '';
            
            // Validate inputs
            if ($product_id <= 0 || $quantity <= 0) {
                header("Location: inventory.php?error=Invalid input data");
                exit();
            }
            
            // Add stock
            $stmt = $db->prepare("UPDATE products SET stock_quantity = stock_quantity + ? WHERE id = ?");
            $stmt->execute([$quantity, $product_id]);
            
            $stmt = $db->prepare("INSERT INTO inventory_logs (product_id, action, quantity, user_id, notes) 
                                  VALUES (?, 'stock_in', ?, ?, ?)");
            $stmt->execute([$product_id, $quantity, $_SESSION['user_id'], $notes]);
            
            // Log user activity
            if (function_exists('logUserActivity')) {
                logUserActivity('stock_in', "Added $quantity units to product ID: $product_id");
            }
            
        } elseif ($action === 'stock_out') {
            // Handle multiple items sale
            $customer_name = $_POST['customer_name'] ?? '';
            $customer_email = $_POST['customer_email'] ?? '';
            $customer_phone = $_POST['customer_phone'] ?? '';
            $payment_method = $_POST['payment_method'] ?? 'cash';
            $notes = $_POST['notes'] ?? '';
            
            // Get cart items from JSON
            $cart_items = json_decode($_POST['cart_items'] ?? '[]', true);
            
            if (empty($cart_items)) {
                $db->rollBack();
                header("Location: inventory.php?error=No items in cart");
                exit();
            }
            
            $total_sale_amount = 0;
            $sale_items = [];
            
            // Validate all items first
            foreach ($cart_items as $item) {
                $product_id = (int)$item['product_id'];
                $quantity = (int)$item['quantity'];
                
                if ($product_id <= 0 || $quantity <= 0) {
                    $db->rollBack();
                    header("Location: inventory.php?error=Invalid item data");
                    exit();
                }
                
                // Check stock availability
                $stmt = $db->prepare("SELECT stock_quantity, product_name, price, barcode FROM products WHERE id = ?");
                $stmt->execute([$product_id]);
                $product = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$product) {
                    $db->rollBack();
                    header("Location: inventory.php?error=Product not found: ID $product_id");
                    exit();
                }
                
                if ($product['stock_quantity'] < $quantity) {
                    $db->rollBack();
                    header("Location: inventory.php?error=Insufficient stock for {$product['product_name']}. Available: {$product['stock_quantity']}");
                    exit();
                }
                
                $sale_items[] = [
                    'product_id' => $product_id,
                    'product' => $product,
                    'quantity' => $quantity,
                    'unit_price' => $product['price'],
                    'total_price' => $product['price'] * $quantity
                ];
                
                $total_sale_amount += $product['price'] * $quantity;
            }
            
            // Create main sale record
            try {
                $stmt = $db->prepare("INSERT INTO sales (customer_name, customer_email, customer_phone, payment_method, total_amount, notes) 
                                      VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$customer_name, $customer_email, $customer_phone, $payment_method, $total_sale_amount, $notes]);
            } catch (Exception $e) {
                // Fallback for older table structure
                $stmt = $db->prepare("INSERT INTO sales (total_price) VALUES (?)");
                $stmt->execute([$total_sale_amount]);
            }
            
            $sale_id = $db->lastInsertId();
            
            // Process each item
            foreach ($sale_items as $item) {
                // Update stock
                $stmt = $db->prepare("UPDATE products SET stock_quantity = stock_quantity - ? WHERE id = ?");
                $stmt->execute([$item['quantity'], $item['product_id']]);
                
                // Log inventory movement
                $stmt = $db->prepare("INSERT INTO inventory_logs (product_id, action, quantity, user_id, notes) 
                                      VALUES (?, 'stock_out', ?, ?, ?)");
                $stmt->execute([$item['product_id'], $item['quantity'], $_SESSION['user_id'], "Sale #$sale_id - {$item['product']['product_name']}"]);
                
                // Create sale item record
                try {
                    $stmt = $db->prepare("INSERT INTO sale_items (sale_id, product_id, quantity, unit_price, total_price) 
                                          VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([$sale_id, $item['product_id'], $item['quantity'], $item['unit_price'], $item['total_price']]);
                } catch (Exception $e) {
                    // Create individual sales records for backward compatibility
                    try {
                        $stmt = $db->prepare("INSERT INTO sales (product_id, quantity, total_price, customer_name, customer_email, customer_phone, payment_method) 
                                              VALUES (?, ?, ?, ?, ?, ?, ?)");
                        $stmt->execute([$item['product_id'], $item['quantity'], $item['total_price'], $customer_name, $customer_email, $customer_phone, $payment_method]);
                    } catch (Exception $e2) {
                        // Final fallback
                        $stmt = $db->prepare("INSERT INTO sales (product_id, quantity, total_price) VALUES (?, ?, ?)");
                        $stmt->execute([$item['product_id'], $item['quantity'], $item['total_price']]);
                    }
                }
            }
            
            // Log user activity
            if (function_exists('logUserActivity')) {
                $item_count = count($sale_items);
                logUserActivity('multi_sale', "Completed sale of $item_count items to $customer_name. Total: ₱" . number_format($total_sale_amount, 2));
            }
            
            // Commit transaction
            $db->commit();
            
            // Prepare receipt data for JavaScript
            $receipt_data = [
                'sale_id' => $sale_id,
                'customer_name' => $customer_name,
                'customer_email' => $customer_email,
                'customer_phone' => $customer_phone,
                'payment_method' => $payment_method,
                'total_amount' => $total_sale_amount,
                'items' => $sale_items,
                'date' => date('Y-m-d H:i:s'),
                'cashier' => $_SESSION['username']
            ];
            
            // Store receipt data in session for popup display
            $_SESSION['show_receipt'] = $receipt_data;
            
            // Redirect to success page
            header("Location: inventory.php?success=1&sale_completed=1");
            exit();
        }
        
        // Commit transaction for stock_in
        $db->commit();
        header("Location: inventory.php?success=1");
        exit();
        
    } catch (Exception $e) {
        // Rollback transaction on error
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        error_log("Stock operation error: " . $e->getMessage());
        header("Location: inventory.php?error=Operation failed: " . urlencode($e->getMessage()));
        exit();
    }
}

// Get inventory logs with error handling
try {
    $logs_query = "SELECT il.*, p.product_name, u.username 
                   FROM inventory_logs il 
                   JOIN products p ON il.product_id = p.id 
                   LEFT JOIN users u ON il.user_id = u.id 
                   ORDER BY il.created_at DESC 
                   LIMIT 50";
    $logs = $db->query($logs_query)->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error fetching inventory logs: " . $e->getMessage());
    $logs = [];
}

// Get products for dropdown with error handling - include category and supplier info
try {
    $products_query = "SELECT p.id, p.product_name, p.stock_quantity, p.price, p.barcode, p.image,
                              c.name as category_name, s.name as supplier_name 
                       FROM products p 
                       LEFT JOIN categories c ON p.category_id = c.id 
                       LEFT JOIN suppliers s ON p.supplier_id = s.id 
                       ORDER BY c.name, p.product_name";
    $products = $db->query($products_query)->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error fetching products: " . $e->getMessage());
    $products = [];
}

// Get categories for filtering
try {
    $categories = $db->query("SELECT id, name FROM categories ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $categories = [];
}

// Get suppliers for filtering
try {
    $suppliers = $db->query("SELECT id, name FROM suppliers ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $suppliers = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="css/style.css?v=<?php echo time(); ?>">
    <style>
        /* Fix table alignment issues */
        #inventoryTable {
            table-layout: auto !important;
        }
        
        #inventoryTable th,
        #inventoryTable td {
            animation: none !important;
            transform: none !important;
            transition: none !important;
        }
        
        #inventoryTable tbody tr {
            animation: none !important;
            animation-delay: 0s !important;
        }
        
        #inventoryTable tbody tr::before {
            display: none !important;
        }
        
        /* Ensure proper column structure */
        .table-responsive {
            overflow-x: auto;
        }
        
        /* Reset any conflicting styles */
        #inventoryTable .badge {
            display: inline-block;
            white-space: nowrap;
        }
        
        /* Enhanced Action Buttons Styling */
        .action-buttons {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            align-items: center;
        }
        
        .action-buttons .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 8px 16px;
            font-size: 0.875rem;
            font-weight: 500;
            line-height: 1.5;
            border-radius: 8px;
            transition: all 0.2s ease-in-out;
            text-decoration: none;
            white-space: nowrap;
            min-height: 38px;
            border: 1px solid transparent;
        }
        
        .action-buttons .btn i {
            margin-right: 6px;
            font-size: 1rem;
        }
        
        .action-buttons .btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }
        
        .action-buttons .btn:active {
            transform: translateY(0);
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        
        /* Specific button styles */
        .action-buttons .btn-outline-secondary {
            color: #6c757d;
            border-color: #6c757d;
            background-color: transparent;
        }
        
        .action-buttons .btn-outline-secondary:hover {
            color: #fff;
            background-color: #6c757d;
            border-color: #6c757d;
        }
        
        .action-buttons .btn-outline-success {
            color: #198754;
            border-color: #198754;
            background-color: transparent;
        }
        
        .action-buttons .btn-outline-success:hover {
            color: #fff;
            background-color: #198754;
            border-color: #198754;
        }
        
        .action-buttons .btn-success {
            color: #fff;
            background-color: #198754;
            border-color: #198754;
        }
        
        .action-buttons .btn-success:hover {
            background-color: #157347;
            border-color: #146c43;
        }
        
        .action-buttons .btn-warning {
            color: #000;
            background-color: #ffc107;
            border-color: #ffc107;
        }
        
        .action-buttons .btn-warning:hover {
            color: #000;
            background-color: #ffca2c;
            border-color: #ffc720;
        }
        
        /* Responsive adjustments */
        @media (max-width: 768px) {
            .action-buttons {
                justify-content: flex-start;
                width: 100%;
                margin-top: 10px;
            }
            
            .action-buttons .btn {
                font-size: 0.8rem;
                padding: 6px 12px;
                min-height: 34px;
            }
            
            .action-buttons .btn i {
                margin-right: 4px;
                font-size: 0.9rem;
            }
        }
        
        @media (max-width: 576px) {
            .action-buttons .btn {
                flex: 1;
                min-width: 0;
            }
        }
        
        /* Modal button consistency */
        .modal-footer .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 8px 16px;
            font-size: 0.875rem;
            font-weight: 500;
            line-height: 1.5;
            border-radius: 6px;
            transition: all 0.2s ease-in-out;
            text-decoration: none;
            white-space: nowrap;
            min-height: 38px;
        }
        
        .modal-footer .btn i {
            margin-right: 6px;
        }
        
        /* Cart button styling */
        .cart-item .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 32px;
            min-height: 32px;
            padding: 4px 8px;
        }
        
        /* Ensure consistent button spacing */
        .btn i {
            margin-right: 0.375rem;
        }
        
        .btn i:only-child {
            margin-right: 0;
        }
        
        /* Fix button group alignment */
        .d-flex.gap-2 .btn {
            margin: 0;
        }
        
        /* Ensure buttons don't wrap unnecessarily */
        .btn {
            flex-shrink: 0;
        }
    </style>
</head>
<body>
    <?php include 'includes/navbar.php'; ?>
    
    <div class="container-fluid">
        <div class="sidebar-overlay"></div>
        <?php include 'includes/sidebar.php'; ?>
        
        <main>
                <?php if (isset($_GET['success']) && !isset($_GET['sale_completed'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="bi bi-check-circle"></i>
                        Stock operation completed successfully!
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($_GET['error'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="bi bi-exclamation-triangle"></i>
                        <?php echo htmlspecialchars(urldecode($_GET['error'])); ?>
                        <?php if (strpos($_GET['error'], 'Security token') !== false): ?>
                            <br><small>This usually happens when the page has been open for a long time. The page will auto-refresh tokens to prevent this.</small>
                        <?php endif; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2"><i class="bi bi-arrow-left-right"></i> Inventory Management</h1>
                <div class="d-flex gap-2 align-items-center">
                    <button onclick="window.location.href='qr_codes.php'" class="btn btn-outline-info">
                        <i class="bi bi-qr-code me-2"></i> QR Codes
                    </button>
                    <button onclick="printInventory()" class="btn btn-outline-secondary">
                        <i class="bi bi-printer me-2"></i> Print
                    </button>
                    <button onclick="exportInventoryCSV()" class="btn btn-outline-success">
                        <i class="bi bi-file-earmark-spreadsheet me-2"></i> Export CSV
                    </button>
                    <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#stockInModal">
                        <i class="bi bi-arrow-down-circle me-2"></i> Stock In
                    </button>
                    <button class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#stockOutModal">
                        <i class="bi bi-arrow-up-circle me-2"></i> Stock Out / Sale
                    </button>
                </div>
            </div>
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <span class="text-muted">
                            <i class="bi bi-list-ul"></i> 
                            Showing <?php echo count($logs); ?> recent transaction<?php echo count($logs) !== 1 ? 's' : ''; ?>
                        </span>
                    </div>
                    <div class="text-muted">
                        <small>Last updated: <?php echo date('M d, Y H:i'); ?></small>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <span>Inventory Logs</span>
                            <small class="text-muted">Last 50 transactions</small>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if (empty($logs)): ?>
                            <div class="text-center py-4">
                                <div class="text-muted">
                                    <i class="bi bi-inbox fs-1"></i>
                                    <p class="mt-2">No inventory transactions found</p>
                                    <p><small>Start by adding or removing stock from products</small></p>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover" id="inventoryTable">
                                    <thead class="table-dark">
                                        <tr>
                                            <th>#</th>
                                            <th>Product</th>
                                            <th>Action</th>
                                            <th>Quantity</th>
                                            <th>User</th>
                                            <th>Notes</th>
                                            <th>Date</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($logs as $index => $log): ?>
                                            <tr>
                                                <td><?php echo $index + 1; ?></td>
                                                <td><?php echo htmlspecialchars($log['product_name']); ?></td>
                                                <td>
                                                    <span class="badge <?php echo $log['action'] === 'stock_in' ? 'bg-success' : 'bg-warning'; ?>">
                                                        <?php echo strtoupper(str_replace('_', ' ', $log['action'])); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo $log['quantity']; ?></td>
                                                <td><?php echo htmlspecialchars($log['username'] ?? 'System'); ?></td>
                                                <td><?php echo htmlspecialchars($log['notes']); ?></td>
                                                <td><?php echo date('M d, Y H:i', strtotime($log['created_at'])); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
        </main>
    </div>
    <div class="modal fade" id="stockInModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="action" value="stock_in">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="bi bi-arrow-down-circle"></i> Stock In</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <?php if (empty($products)): ?>
                            <div class="alert alert-warning">
                                <i class="bi bi-exclamation-triangle"></i>
                                No products available. Please add products first.
                            </div>
                        <?php else: ?>
                            <!-- Filters -->
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Filter by Category</label>
                                    <select id="categoryFilter" class="form-select" onchange="filterProducts()">
                                        <option value="">All Categories</option>
                                        <?php foreach ($categories as $cat): ?>
                                            <option value="<?php echo htmlspecialchars($cat['name']); ?>">
                                                <?php echo htmlspecialchars($cat['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Filter by Brand</label>
                                    <select id="brandFilter" class="form-select" onchange="filterProducts()">
                                        <option value="">All Brands</option>
                                        <?php foreach ($suppliers as $sup): ?>
                                            <option value="<?php echo htmlspecialchars($sup['name']); ?>">
                                                <?php echo htmlspecialchars($sup['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Product</label>
                                <select name="product_id" id="productSelect" class="form-select" required onchange="updateProductInfo()">
                                    <option value="">Select a product...</option>
                                    <?php foreach ($products as $p): ?>
                                        <option value="<?php echo $p['id']; ?>" 
                                                data-category="<?php echo htmlspecialchars($p['category_name']); ?>"
                                                data-brand="<?php echo htmlspecialchars($p['supplier_name']); ?>"
                                                data-stock="<?php echo $p['stock_quantity']; ?>"
                                                data-price="<?php echo $p['price']; ?>"
                                                data-barcode="<?php echo htmlspecialchars($p['barcode']); ?>">
                                            <?php echo htmlspecialchars($p['product_name']); ?> 
                                            - <?php echo htmlspecialchars($p['category_name']); ?> 
                                            (<?php echo htmlspecialchars($p['supplier_name']); ?>) 
                                            - Stock: <?php echo $p['stock_quantity']; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <!-- Product Info Display -->
                            <div id="productInfo" class="card mb-3" style="display: none;">
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-8">
                                            <h6 class="card-title" id="productName"></h6>
                                            <p class="card-text">
                                                <small class="text-muted">
                                                    <strong>Category:</strong> <span id="productCategory"></span><br>
                                                    <strong>Brand:</strong> <span id="productBrand"></span><br>
                                                    <strong>Current Stock:</strong> <span id="productStock"></span><br>
                                                    <strong>Price:</strong> ₱<span id="productPrice"></span><br>
                                                    <strong>Barcode:</strong> <span id="productBarcode"></span>
                                                </small>
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Quantity to Add</label>
                                <input type="number" name="quantity" class="form-control" min="1" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Notes</label>
                                <textarea name="notes" class="form-control" rows="3" placeholder="Optional notes about this stock addition"></textarea>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="modal-footer d-flex justify-content-end gap-2">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="bi bi-x-lg me-2"></i> Close
                        </button>
                        <?php if (!empty($products)): ?>
                            <button type="submit" class="btn btn-success">
                                <i class="bi bi-plus-circle me-2"></i> Add Stock
                            </button>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <div class="modal fade" id="stockOutModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <form method="POST" id="stockOutForm">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="action" value="stock_out">
                    <input type="hidden" name="cart_items" id="cartItemsInput">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="bi bi-cart-check"></i> Multi-Item Sale</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <?php if (empty($products)): ?>
                            <div class="alert alert-warning">
                                <i class="bi bi-exclamation-triangle"></i>
                                No products available. Please add products first.
                            </div>
                        <?php else: ?>
                            <div class="row">
                                <!-- Left Column: Product Selection -->
                                <div class="col-md-6">
                                    <!-- Customer Information -->
                                    <div class="card mb-3">
                                        <div class="card-header">
                                            <h6 class="mb-0"><i class="bi bi-person"></i> Customer Information</h6>
                                        </div>
                                        <div class="card-body">
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label class="form-label">Customer Name</label>
                                                        <input type="text" name="customer_name" class="form-control" placeholder="Enter customer name">
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label class="form-label">Phone Number</label>
                                                        <input type="tel" name="customer_phone" class="form-control" placeholder="Enter phone number">
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label class="form-label">Email Address</label>
                                                        <input type="email" name="customer_email" class="form-control" placeholder="Enter email address">
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label class="form-label">Payment Method</label>
                                                        <select name="payment_method" class="form-select">
                                                            <option value="cash">Cash</option>
                                                            <option value="card">Credit/Debit Card</option>
                                                            <option value="digital_wallet">Digital Wallet</option>
                                                            <option value="bank_transfer">Bank Transfer</option>
                                                        </select>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <!-- Product Selection -->
                                    <div class="card">
                                        <div class="card-header">
                                            <h6 class="mb-0"><i class="bi bi-plus-circle"></i> Add Products to Cart</h6>
                                        </div>
                                        <div class="card-body">
                                            <!-- Filters -->
                                            <div class="row mb-3">
                                                <div class="col-md-6">
                                                    <label class="form-label">Filter by Category</label>
                                                    <select id="categoryFilterOut" class="form-select" onchange="filterProductsOut()">
                                                        <option value="">All Categories</option>
                                                        <?php foreach ($categories as $cat): ?>
                                                            <option value="<?php echo htmlspecialchars($cat['name']); ?>">
                                                                <?php echo htmlspecialchars($cat['name']); ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label">Filter by Brand</label>
                                                    <select id="brandFilterOut" class="form-select" onchange="filterProductsOut()">
                                                        <option value="">All Brands</option>
                                                        <?php foreach ($suppliers as $sup): ?>
                                                            <option value="<?php echo htmlspecialchars($sup['name']); ?>">
                                                                <?php echo htmlspecialchars($sup['name']); ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label class="form-label">Select Product</label>
                                                <select id="productSelectOut" class="form-select" onchange="updateProductInfoOut()">
                                                    <option value="">Select a product...</option>
                                                    <?php foreach ($products as $p): ?>
                                                        <option value="<?php echo $p['id']; ?>" 
                                                                data-name="<?php echo htmlspecialchars($p['product_name']); ?>"
                                                                data-category="<?php echo htmlspecialchars($p['category_name']); ?>"
                                                                data-brand="<?php echo htmlspecialchars($p['supplier_name']); ?>"
                                                                data-stock="<?php echo $p['stock_quantity']; ?>"
                                                                data-price="<?php echo $p['price']; ?>"
                                                                data-barcode="<?php echo htmlspecialchars($p['barcode']); ?>"
                                                                data-image="<?php echo htmlspecialchars($p['image']); ?>">
                                                            <?php echo htmlspecialchars($p['product_name']); ?> 
                                                            - <?php echo htmlspecialchars($p['category_name']); ?> 
                                                            (<?php echo htmlspecialchars($p['supplier_name']); ?>) 
                                                            - Stock: <?php echo $p['stock_quantity']; ?> - ₱<?php echo number_format($p['price'], 2); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            
                                            <!-- Product Info Display -->
                                            <div id="productInfoOut" class="card mb-3" style="display: none;">
                                                <div class="card-body">
                                                    <div class="row">
                                                        <div class="col-md-3">
                                                            <img id="productImageOut" src="" alt="Product Image" class="img-fluid rounded" style="max-height: 80px; display: none;">
                                                        </div>
                                                        <div class="col-md-9">
                                                            <h6 class="card-title" id="productNameOut"></h6>
                                                            <p class="card-text">
                                                                <small class="text-muted">
                                                                    <strong>Category:</strong> <span id="productCategoryOut"></span><br>
                                                                    <strong>Brand:</strong> <span id="productBrandOut"></span><br>
                                                                    <strong>Available:</strong> <span id="productStockOut"></span><br>
                                                                    <strong>Price:</strong> ₱<span id="productPriceOut"></span><br>
                                                                    <strong>Barcode:</strong> <span id="productBarcodeOut"></span>
                                                                </small>
                                                            </p>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label class="form-label">Quantity</label>
                                                        <input type="number" id="quantityOut" class="form-control" min="1" placeholder="Enter quantity">
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label class="form-label">Item Total</label>
                                                        <div class="input-group">
                                                            <span class="input-group-text">₱</span>
                                                            <input type="text" id="itemTotal" class="form-control" readonly>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <button type="button" class="btn btn-primary w-100 d-flex align-items-center justify-content-center" onclick="addToCart()">
                                                <i class="bi bi-cart-plus me-2"></i> Add to Cart
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <!-- Right Column: Shopping Cart -->
                                <div class="col-md-6">
                                    <div class="card">
                                        <div class="card-header d-flex justify-content-between align-items-center">
                                            <h6 class="mb-0"><i class="bi bi-cart"></i> Shopping Cart</h6>
                                            <button type="button" class="btn btn-sm btn-outline-danger" onclick="clearCart()">
                                                <i class="bi bi-trash me-1"></i> Clear All
                                            </button>
                                        </div>
                                        <div class="card-body">
                                            <div id="cartItems" class="mb-3">
                                                <div class="text-center text-muted py-4" id="emptyCart">
                                                    <i class="bi bi-cart-x fs-1"></i>
                                                    <p class="mt-2">Cart is empty</p>
                                                    <small>Add products to start a sale</small>
                                                </div>
                                            </div>
                                            
                                            <!-- Cart Summary -->
                                            <div class="border-top pt-3" id="cartSummary" style="display: none;">
                                                <div class="d-flex justify-content-between mb-2">
                                                    <span>Items:</span>
                                                    <span id="totalItems">0</span>
                                                </div>
                                                <div class="d-flex justify-content-between mb-2">
                                                    <span>Subtotal:</span>
                                                    <span>₱<span id="subtotal">0.00</span></span>
                                                </div>
                                                <hr>
                                                <div class="d-flex justify-content-between">
                                                    <strong>Total:</strong>
                                                    <strong>₱<span id="grandTotal">0.00</span></strong>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Notes -->
                                    <div class="card mt-3">
                                        <div class="card-body">
                                            <label class="form-label">Sale Notes</label>
                                            <textarea name="notes" class="form-control" rows="3" placeholder="Optional notes about this sale"></textarea>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="modal-footer d-flex justify-content-end gap-2">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="bi bi-x-lg me-2"></i> Cancel
                        </button>
                        <?php if (!empty($products)): ?>
                            <button type="submit" class="btn btn-success" id="completeSaleBtn" disabled>
                                <i class="bi bi-cart-check me-2"></i> Complete Sale - ₱<span id="footerTotal">0.00</span>
                            </button>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php include 'includes/chatbot.php'; ?>
    
    <!-- Sale Receipt Popup Modal -->
    <?php if (isset($_SESSION['show_receipt'])): ?>
        <?php 
        $receipt = $_SESSION['show_receipt'];
        unset($_SESSION['show_receipt']); // Clear it after displaying
        ?>
        <div class="modal fade show" id="saleReceiptModal" tabindex="-1" style="display: block;" data-bs-backdrop="static">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header bg-success text-white">
                        <h5 class="modal-title"><i class="bi bi-check-circle"></i> Sale Completed Successfully!</h5>
                        <button type="button" class="btn-close btn-close-white" onclick="closeSaleReceipt()"></button>
                    </div>
                    <div class="modal-body">
                        <div id="saleReceiptContent" class="receipt-content">
                            <!-- Store Header -->
                            <div class="text-center mb-4">
                                <h3 class="mb-1">INVENTORY STORE</h3>
                                <p class="mb-1">Professional Inventory Management</p>
                                <p class="mb-1">📍 Your Store Address Here</p>
                                <p class="mb-0">📞 Contact: +1234567890</p>
                                <hr>
                            </div>
                            
                            <!-- Receipt Details -->
                            <div class="row mb-3">
                                <div class="col-6">
                                    <strong>Receipt #:</strong> <?php echo str_pad($receipt['sale_id'], 6, '0', STR_PAD_LEFT); ?><br>
                                    <strong>Date:</strong> <?php echo date('M d, Y H:i', strtotime($receipt['date'])); ?><br>
                                    <strong>Cashier:</strong> <?php echo htmlspecialchars($receipt['cashier']); ?>
                                </div>
                                <div class="col-6 text-end">
                                    <?php if ($receipt['customer_name']): ?>
                                        <strong>Customer:</strong> <?php echo htmlspecialchars($receipt['customer_name']); ?><br>
                                    <?php endif; ?>
                                    <?php if ($receipt['customer_phone']): ?>
                                        <strong>Phone:</strong> <?php echo htmlspecialchars($receipt['customer_phone']); ?><br>
                                    <?php endif; ?>
                                    <?php if ($receipt['customer_email']): ?>
                                        <strong>Email:</strong> <?php echo htmlspecialchars($receipt['customer_email']); ?><br>
                                    <?php endif; ?>
                                    <strong>Payment:</strong> <?php echo strtoupper(str_replace('_', ' ', $receipt['payment_method'])); ?>
                                </div>
                            </div>
                            <hr>
                            
                            <!-- Items -->
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Item</th>
                                        <th class="text-center">Qty</th>
                                        <th class="text-end">Price</th>
                                        <th class="text-end">Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($receipt['items'] as $item): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($item['product']['product_name']); ?></strong><br>
                                                <small class="text-muted">
                                                    Barcode: <?php echo htmlspecialchars($item['product']['barcode'] ?? 'N/A'); ?>
                                                </small>
                                            </td>
                                            <td class="text-center"><?php echo $item['quantity']; ?></td>
                                            <td class="text-end">₱<?php echo number_format($item['unit_price'], 2); ?></td>
                                            <td class="text-end">₱<?php echo number_format($item['total_price'], 2); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            
                            <hr>
                            
                            <!-- Totals -->
                            <div class="row">
                                <div class="col-6"></div>
                                <div class="col-6">
                                    <table class="table table-sm">
                                        <tr>
                                            <td><strong>Items:</strong></td>
                                            <td class="text-end"><?php echo count($receipt['items']); ?></td>
                                        </tr>
                                        <tr>
                                            <td><strong>Subtotal:</strong></td>
                                            <td class="text-end">₱<?php echo number_format($receipt['total_amount'], 2); ?></td>
                                        </tr>
                                        <tr class="table-success">
                                            <td><strong>TOTAL:</strong></td>
                                            <td class="text-end"><strong>₱<?php echo number_format($receipt['total_amount'], 2); ?></strong></td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                            
                            <!-- Footer -->
                            <div class="text-center mt-4">
                                <p class="mb-1">Thank you for your purchase!</p>
                                <hr>
                                <small class="text-muted">
                                    This receipt was generated by Inventory Management System<br>
                                    Transaction ID: <?php echo $receipt['sale_id']; ?> | <?php echo date('Y-m-d H:i:s'); ?>
                                </small>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer d-flex justify-content-end gap-2">
                                        <button type="button" class="btn btn-secondary" onclick="closeSaleReceipt()">
                            <i class="bi bi-x-circle me-2"></i> Close
                        </button>
                        <button type="button" class="btn btn-primary" onclick="printSaleReceipt()">
                            <i class="bi bi-printer me-2"></i> Print Receipt
                        </button>
                        <button type="button" class="btn btn-success" onclick="newSale()">
                            <i class="bi bi-plus-circle me-2"></i> New Sale
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <div class="modal-backdrop fade show"></div>
    <?php endif; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/chatbot.js?v=<?php echo time(); ?>"></script>
    <script src="js/export.js?v=<?php echo time(); ?>"></script>
    <script src="js/mobile.js?v=<?php echo time(); ?>"></script>
    
    <script>
        // CSRF Token refresh mechanism
        function refreshCSRFToken() {
            fetch('api/csrf_token.php')
                .then(response => response.json())
                .then(data => {
                    if (data.csrf_token) {
                        // Update all CSRF token inputs
                        document.querySelectorAll('input[name="csrf_token"]').forEach(input => {
                            input.value = data.csrf_token;
                        });
                        console.log('CSRF tokens refreshed at', new Date().toLocaleTimeString());
                    }
                })
                .catch(err => {
                    console.log('CSRF refresh failed:', err);
                    // Fallback: reload the page if CSRF refresh fails
                    if (confirm('Session may have expired. Reload page?')) {
                        location.reload();
                    }
                });
        }
        
        // Refresh CSRF tokens every 10 minutes
        setInterval(refreshCSRFToken, 600000);
        
        // Refresh on page focus (when user comes back to tab)
        window.addEventListener('focus', refreshCSRFToken);
        
        // Enhanced form submission handling
        document.addEventListener('DOMContentLoaded', function() {
            const forms = document.querySelectorAll('form[method="POST"]');
            forms.forEach(form => {
                form.addEventListener('submit', function(e) {
                    const csrfInput = form.querySelector('input[name="csrf_token"]');
                    if (csrfInput && !csrfInput.value) {
                        e.preventDefault();
                        alert('Security token missing. Please refresh the page and try again.');
                        return false;
                    }
                });
            });
            
            // Show CSRF status in console
            console.log('CSRF protection active. Tokens will refresh every 10 minutes.');
        });
    </script>
    
    <script>
        // Shopping cart functionality
        let cart = [];
        
        // Product filtering for Stock In modal
        function filterProducts() {
            const categoryFilter = document.getElementById('categoryFilter').value;
            const brandFilter = document.getElementById('brandFilter').value;
            const productSelect = document.getElementById('productSelect');
            
            Array.from(productSelect.options).forEach(option => {
                if (option.value === '') return; // Skip the default option
                
                const category = option.getAttribute('data-category');
                const brand = option.getAttribute('data-brand');
                
                const categoryMatch = !categoryFilter || category === categoryFilter;
                const brandMatch = !brandFilter || brand === brandFilter;
                
                option.style.display = (categoryMatch && brandMatch) ? 'block' : 'none';
            });
            
            // Reset selection if current selection is now hidden
            if (productSelect.selectedOptions[0] && productSelect.selectedOptions[0].style.display === 'none') {
                productSelect.value = '';
                document.getElementById('productInfo').style.display = 'none';
            }
        }
        
        // Product filtering for Stock Out modal
        function filterProductsOut() {
            const categoryFilter = document.getElementById('categoryFilterOut').value;
            const brandFilter = document.getElementById('brandFilterOut').value;
            const productSelect = document.getElementById('productSelectOut');
            
            Array.from(productSelect.options).forEach(option => {
                if (option.value === '') return;
                
                const category = option.getAttribute('data-category');
                const brand = option.getAttribute('data-brand');
                
                const categoryMatch = !categoryFilter || category === categoryFilter;
                const brandMatch = !brandFilter || brand === brandFilter;
                
                option.style.display = (categoryMatch && brandMatch) ? 'block' : 'none';
            });
            
            if (productSelect.selectedOptions[0] && productSelect.selectedOptions[0].style.display === 'none') {
                productSelect.value = '';
                document.getElementById('productInfoOut').style.display = 'none';
                document.getElementById('itemTotal').value = '';
            }
        }
        
        // Update product info for Stock In
        function updateProductInfo() {
            const select = document.getElementById('productSelect');
            const option = select.selectedOptions[0];
            const infoDiv = document.getElementById('productInfo');
            
            if (option && option.value) {
                document.getElementById('productName').textContent = option.textContent.split(' - ')[0];
                document.getElementById('productCategory').textContent = option.getAttribute('data-category');
                document.getElementById('productBrand').textContent = option.getAttribute('data-brand');
                document.getElementById('productStock').textContent = option.getAttribute('data-stock');
                document.getElementById('productPrice').textContent = parseFloat(option.getAttribute('data-price')).toFixed(2);
                document.getElementById('productBarcode').textContent = option.getAttribute('data-barcode');
                infoDiv.style.display = 'block';
            } else {
                infoDiv.style.display = 'none';
            }
        }
        
        // Update product info for Stock Out
        function updateProductInfoOut() {
            const select = document.getElementById('productSelectOut');
            const option = select.selectedOptions[0];
            const infoDiv = document.getElementById('productInfoOut');
            
            if (option && option.value) {
                document.getElementById('productNameOut').textContent = option.getAttribute('data-name');
                document.getElementById('productCategoryOut').textContent = option.getAttribute('data-category');
                document.getElementById('productBrandOut').textContent = option.getAttribute('data-brand');
                document.getElementById('productStockOut').textContent = option.getAttribute('data-stock');
                document.getElementById('productPriceOut').textContent = parseFloat(option.getAttribute('data-price')).toFixed(2);
                document.getElementById('productBarcodeOut').textContent = option.getAttribute('data-barcode');
                
                // Show product image if available
                const image = option.getAttribute('data-image');
                const imgElement = document.getElementById('productImageOut');
                if (image && image !== '') {
                    imgElement.src = 'uploads/' + image;
                    imgElement.style.display = 'block';
                } else {
                    imgElement.style.display = 'none';
                }
                
                infoDiv.style.display = 'block';
                calculateItemTotal();
            } else {
                infoDiv.style.display = 'none';
                document.getElementById('itemTotal').value = '';
            }
        }
        
        // Calculate item total
        function calculateItemTotal() {
            const select = document.getElementById('productSelectOut');
            const option = select.selectedOptions[0];
            const quantity = document.getElementById('quantityOut').value;
            
            if (option && option.value && quantity) {
                const price = parseFloat(option.getAttribute('data-price'));
                const total = price * parseInt(quantity);
                document.getElementById('itemTotal').value = total.toFixed(2);
            } else {
                document.getElementById('itemTotal').value = '';
            }
        }
        
        // Add item to cart
        function addToCart() {
            const select = document.getElementById('productSelectOut');
            const option = select.selectedOptions[0];
            const quantity = parseInt(document.getElementById('quantityOut').value);
            
            if (!option || !option.value) {
                alert('Please select a product');
                return;
            }
            
            if (!quantity || quantity <= 0) {
                alert('Please enter a valid quantity');
                return;
            }
            
            const availableStock = parseInt(option.getAttribute('data-stock'));
            
            // Check if item already in cart
            const existingItemIndex = cart.findIndex(item => item.product_id === option.value);
            const existingQuantity = existingItemIndex >= 0 ? cart[existingItemIndex].quantity : 0;
            
            if (existingQuantity + quantity > availableStock) {
                alert(`Insufficient stock. Available: ${availableStock}, Already in cart: ${existingQuantity}`);
                return;
            }
            
            const item = {
                product_id: option.value,
                name: option.getAttribute('data-name'),
                category: option.getAttribute('data-category'),
                brand: option.getAttribute('data-brand'),
                price: parseFloat(option.getAttribute('data-price')),
                quantity: quantity,
                total: parseFloat(option.getAttribute('data-price')) * quantity,
                barcode: option.getAttribute('data-barcode'),
                image: option.getAttribute('data-image')
            };
            
            if (existingItemIndex >= 0) {
                // Update existing item
                cart[existingItemIndex].quantity += quantity;
                cart[existingItemIndex].total = cart[existingItemIndex].price * cart[existingItemIndex].quantity;
            } else {
                // Add new item
                cart.push(item);
            }
            
            // Reset form
            document.getElementById('productSelectOut').value = '';
            document.getElementById('quantityOut').value = '';
            document.getElementById('itemTotal').value = '';
            
            updateCartDisplay();
        }
        
        // Remove item from cart
        function removeFromCart(index) {
            cart.splice(index, 1);
            updateCartDisplay();
        }
        
        // Clear entire cart
        function clearCart() {
            cart = [];
            updateCartDisplay();
        }
        
        // Update cart display
        function updateCartDisplay() {
            const cartContainer = document.getElementById('cartItems');
            const emptyCart = document.getElementById('emptyCart');
            const cartSummary = document.getElementById('cartSummary');
            const completeSaleBtn = document.getElementById('completeSaleBtn');
            
            if (cart.length === 0) {
                emptyCart.style.display = 'block';
                cartSummary.style.display = 'none';
                completeSaleBtn.disabled = true;
                document.getElementById('footerTotal').textContent = '0.00';
                return;
            }
            
            emptyCart.style.display = 'none';
            cartSummary.style.display = 'block';
            completeSaleBtn.disabled = false;
            
            // Build cart HTML
            let cartHTML = '';
            let totalItems = 0;
            let subtotal = 0;
            
            cart.forEach((item, index) => {
                totalItems += item.quantity;
                subtotal += item.total;
                
                cartHTML += `
                    <div class="cart-item border rounded p-2 mb-2">
                        <div class="d-flex justify-content-between align-items-start">
                            <div class="flex-grow-1">
                                <h6 class="mb-1">${item.name}</h6>
                                <small class="text-muted">
                                    ${item.category} - ${item.brand}<br>
                                    ₱${item.price.toFixed(2)} × ${item.quantity} = ₱${item.total.toFixed(2)}
                                </small>
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeFromCart(${index})">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    </div>
                `;
            });
            
            // Update cart container (remove empty cart message first)
            const existingItems = cartContainer.querySelectorAll('.cart-item');
            existingItems.forEach(item => item.remove());
            
            cartContainer.insertAdjacentHTML('afterbegin', cartHTML);
            
            // Update summary
            document.getElementById('totalItems').textContent = totalItems;
            document.getElementById('subtotal').textContent = subtotal.toFixed(2);
            document.getElementById('grandTotal').textContent = subtotal.toFixed(2);
            document.getElementById('footerTotal').textContent = subtotal.toFixed(2);
            
            // Update hidden input for form submission
            document.getElementById('cartItemsInput').value = JSON.stringify(cart);
        }
        
        // Form submission handler
        document.getElementById('stockOutForm').addEventListener('submit', function(e) {
            if (cart.length === 0) {
                e.preventDefault();
                alert('Please add items to cart before completing the sale');
                return;
            }
            
            // Update cart items input
            document.getElementById('cartItemsInput').value = JSON.stringify(cart);
        });
        
        // Initialize quantity change listener
        document.getElementById('quantityOut').addEventListener('input', calculateItemTotal);
        
        // Receipt functions
        function closeSaleReceipt() {
            document.getElementById('saleReceiptModal').style.display = 'none';
            document.querySelector('.modal-backdrop').remove();
        }
        
        function printSaleReceipt() {
            const receiptContent = document.getElementById('saleReceiptContent').innerHTML;
            const printWindow = window.open('', '_blank');
            printWindow.document.write(`
                <html>
                <head>
                    <title>Sales Receipt</title>
                    <style>
                        body { font-family: Arial, sans-serif; font-size: 12px; margin: 20px; }
                        .receipt-content { max-width: 400px; margin: 0 auto; }
                        table { width: 100%; border-collapse: collapse; }
                        th, td { padding: 4px; text-align: left; }
                        .text-center { text-align: center; }
                        .text-end { text-align: right; }
                        .table-success { background-color: #d4edda; }
                        hr { border: 1px solid #ccc; }
                        @media print {
                            body { margin: 0; }
                        }
                    </style>
                </head>
                <body>
                    <div class="receipt-content">${receiptContent}</div>
                </body>
                </html>
            `);
            printWindow.document.close();
            printWindow.print();
        }
        
        function newSale() {
            closeSaleReceipt();
            // Clear the cart and reset the form
            cart = [];
            updateCartDisplay();
            // Show the stock out modal for a new sale
            const stockOutModal = new bootstrap.Modal(document.getElementById('stockOutModal'));
            stockOutModal.show();
        }
        
        // Export functions
        function printInventory() {
            window.print();
        }
        
        function exportInventoryCSV() {
            // Basic CSV export functionality
            let csv = 'Product,Action,Quantity,User,Notes,Date\n';
            
            const rows = document.querySelectorAll('#inventoryTable tbody tr');
            rows.forEach(row => {
                const cells = row.querySelectorAll('td');
                const rowData = Array.from(cells).map(cell => 
                    '"' + cell.textContent.replace(/"/g, '""') + '"'
                ).join(',');
                csv += rowData + '\n';
            });
            
            const blob = new Blob([csv], { type: 'text/csv' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'inventory_logs_' + new Date().toISOString().split('T')[0] + '.csv';
            a.click();
            window.URL.revokeObjectURL(url);
        }
    </script>
</body>
</html>