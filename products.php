<?php
// Include required files
require_once 'config/database.php';
require_once 'config/session.php';
require_once 'config/rate_limiter.php';
require_once 'config/file_handler.php';
require_once 'config/error_handler.php';

// Make sure user is logged in
requireLogin();

// Connect to database
$database = new Database();
$db = $database->getConnection();
$rateLimiter = new RateLimiter($db);
$fileHandler = new FileHandler();

// Handle form submissions (adding/deleting products)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    // Rate limiting
    if (!$rateLimiter->checkLimit('product_action', 10, 60)) {
        die('Too many requests. Please wait before trying again.');
    }
    
    // Check security token
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        die('Invalid CSRF token');
    }
    
    try {
        // Adding a new product
        if ($_POST['action'] === 'add') {
            // Validate input
            $validation_rules = [
                'product_name' => ['required' => true, 'max_length' => 255],
                'category_id' => ['required' => true, 'type' => 'int', 'min' => 1],
                'supplier_id' => ['required' => true, 'type' => 'int', 'min' => 1],
                'price' => ['required' => true, 'type' => 'float', 'min' => 0],
                'cost_price' => ['required' => true, 'type' => 'float', 'min' => 0],
                'stock_quantity' => ['required' => true, 'type' => 'int', 'min' => 0],
                'reorder_level' => ['required' => true, 'type' => 'int', 'min' => 0]
            ];
            
            $errors = ErrorHandler::validateInput($_POST, $validation_rules);
            if (!empty($errors)) {
                throw new Exception("Validation failed: " . implode(', ', $errors));
            }
            
            $uploaded_image = '';
            
            // Handle image upload if user selected one
            if (isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
                if ($_FILES['image']['error'] === UPLOAD_ERR_OK) {
                    try {
                        $uploaded_image = $fileHandler->uploadImage($_FILES['image'], 'product_');
                    } catch (Exception $e) {
                        throw new Exception("Image upload failed: " . $e->getMessage());
                    }
                } else {
                    // Handle specific upload errors
                    $error_messages = [
                        UPLOAD_ERR_INI_SIZE => 'File is too large (exceeds server limit)',
                        UPLOAD_ERR_FORM_SIZE => 'File is too large (exceeds form limit)',
                        UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
                        UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
                        UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
                        UPLOAD_ERR_EXTENSION => 'File upload stopped by extension'
                    ];
                    $error_msg = $error_messages[$_FILES['image']['error']] ?? 'Unknown upload error';
                    throw new Exception("Image upload error: " . $error_msg);
                }
            }
            
            // Insert new product into database
            $insert_query = "INSERT INTO products (product_name, category_id, supplier_id, price, cost_price, stock_quantity, reorder_level, barcode, image) VALUES (:name, :category, :supplier, :price, :cost_price, :stock, :reorder, :barcode, :image)";
            $stmt = $db->prepare($insert_query);
            $stmt->execute([
                ':name' => trim($_POST['product_name']),
                ':category' => (int)$_POST['category_id'],
                ':supplier' => (int)$_POST['supplier_id'],
                ':price' => (float)$_POST['price'],
                ':cost_price' => (float)$_POST['cost_price'],
                ':stock' => (int)$_POST['stock_quantity'],
                ':reorder' => (int)$_POST['reorder_level'],
                ':barcode' => trim($_POST['barcode']),
                ':image' => $uploaded_image
            ]);
            
            // Log the activity
            logUserActivity('product_added', "Added new product: " . trim($_POST['product_name']));
            
            // Reset rate limit on success
            $rateLimiter->resetLimit('product_action');
            
            header("Location: products.php?success=" . urlencode("Product added successfully"));
            exit();
        }
        
        // Updating a product
        if ($_POST['action'] === 'update') {
            $product_id = (int)$_POST['product_id'];
            if ($product_id <= 0) {
                throw new Exception("Invalid product ID");
            }
            
            // Validate input
            $validation_rules = [
                'product_name' => ['required' => true, 'max_length' => 255],
                'category_id' => ['required' => true, 'type' => 'int', 'min' => 1],
                'supplier_id' => ['required' => true, 'type' => 'int', 'min' => 1],
                'price' => ['required' => true, 'type' => 'float', 'min' => 0],
                'cost_price' => ['required' => true, 'type' => 'float', 'min' => 0],
                'stock_quantity' => ['required' => true, 'type' => 'int', 'min' => 0],
                'reorder_level' => ['required' => true, 'type' => 'int', 'min' => 0]
            ];
            
            $errors = ErrorHandler::validateInput($_POST, $validation_rules);
            if (!empty($errors)) {
                throw new Exception("Validation failed: " . implode(', ', $errors));
            }
            
            // Get current product data
            $stmt = $db->prepare("SELECT image FROM products WHERE id = :id");
            $stmt->execute([':id' => $product_id]);
            $current_product = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$current_product) {
                throw new Exception("Product not found");
            }
            
            $image_path = $current_product['image'];
            
            // Handle image upload if user selected a new one
            if (isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
                if ($_FILES['image']['error'] === UPLOAD_ERR_OK) {
                    try {
                        // Delete old image if it exists
                        if ($image_path) {
                            $fileHandler->deleteFile($image_path);
                        }
                        $image_path = $fileHandler->uploadImage($_FILES['image'], 'product_');
                    } catch (Exception $e) {
                        throw new Exception("Image upload failed: " . $e->getMessage());
                    }
                } else {
                    // Handle specific upload errors
                    $error_messages = [
                        UPLOAD_ERR_INI_SIZE => 'File is too large (exceeds server limit)',
                        UPLOAD_ERR_FORM_SIZE => 'File is too large (exceeds form limit)',
                        UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
                        UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
                        UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
                        UPLOAD_ERR_EXTENSION => 'File upload stopped by extension'
                    ];
                    $error_msg = $error_messages[$_FILES['image']['error']] ?? 'Unknown upload error';
                    throw new Exception("Image upload error: " . $error_msg);
                }
            }
            
            // Update product in database
            $update_query = "UPDATE products SET 
                product_name = :name, 
                category_id = :category, 
                supplier_id = :supplier, 
                price = :price, 
                cost_price = :cost_price,
                stock_quantity = :stock, 
                reorder_level = :reorder, 
                barcode = :barcode, 
                image = :image 
                WHERE id = :id";
            
            $stmt = $db->prepare($update_query);
            $stmt->execute([
                ':name' => trim($_POST['product_name']),
                ':category' => (int)$_POST['category_id'],
                ':supplier' => (int)$_POST['supplier_id'],
                ':price' => (float)$_POST['price'],
                ':cost_price' => (float)$_POST['cost_price'],
                ':stock' => (int)$_POST['stock_quantity'],
                ':reorder' => (int)$_POST['reorder_level'],
                ':barcode' => trim($_POST['barcode']),
                ':image' => $image_path,
                ':id' => $product_id
            ]);
            
            // Log the activity
            logUserActivity('product_updated', "Updated product: " . trim($_POST['product_name']));
            
            header("Location: products.php?success=" . urlencode("Product updated successfully"));
            exit();
        }
        if ($_POST['action'] === 'delete' && isAdmin()) {
            $product_id = (int)$_POST['product_id'];
            if ($product_id <= 0) {
                throw new Exception("Invalid product ID");
            }
            
            // Get product image to delete
            $stmt = $db->prepare("SELECT image FROM products WHERE id = :id");
            $stmt->execute([':id' => $product_id]);
            $product = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($product && $product['image']) {
                $fileHandler->deleteFile($product['image']);
            }
            
            $stmt = $db->prepare("DELETE FROM products WHERE id = :id");
            $stmt->execute([':id' => $product_id]);
            
            header("Location: products.php?success=" . urlencode("Product deleted successfully"));
            exit();
        }
        
        // Bulk stock update
        if ($_POST['action'] === 'bulk_stock_update') {
            $product_ids = explode(',', $_POST['product_ids']);
            $update_type = $_POST['update_type'];
            $quantity = (int)$_POST['quantity'];
            $reason = trim($_POST['reason'] ?? '');
            
            if (empty($product_ids) || $quantity < 0) {
                throw new Exception("Invalid bulk update parameters");
            }
            
            $updated_count = 0;
            foreach ($product_ids as $product_id) {
                $product_id = (int)$product_id;
                if ($product_id <= 0) continue;
                
                // Get current stock
                $stmt = $db->prepare("SELECT product_name, stock_quantity FROM products WHERE id = ?");
                $stmt->execute([$product_id]);
                $product = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$product) continue;
                
                $new_stock = $product['stock_quantity'];
                
                switch ($update_type) {
                    case 'add':
                        $new_stock += $quantity;
                        break;
                    case 'subtract':
                        $new_stock = max(0, $new_stock - $quantity);
                        break;
                    case 'set':
                        $new_stock = $quantity;
                        break;
                }
                
                // Update stock
                $stmt = $db->prepare("UPDATE products SET stock_quantity = ? WHERE id = ?");
                $stmt->execute([$new_stock, $product_id]);
                
                // Log inventory change
                $action_type = $update_type === 'add' ? 'stock_in' : 'stock_out';
                $log_quantity = $update_type === 'set' ? abs($new_stock - $product['stock_quantity']) : $quantity;
                
                $stmt = $db->prepare("INSERT INTO inventory_logs (product_id, action, quantity, user_id, notes) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([
                    $product_id,
                    $action_type,
                    $log_quantity,
                    $_SESSION['user_id'],
                    "Bulk update: $reason"
                ]);
                
                $updated_count++;
            }
            
            // Log the activity
            logUserActivity('bulk_stock_update', "Updated stock for $updated_count products ($update_type: $quantity)");
            
            header("Location: products.php?success=" . urlencode("Stock updated for $updated_count products"));
            exit();
        }
        
        // Bulk delete (admin only)
        if ($_POST['action'] === 'bulk_delete' && isAdmin()) {
            $product_ids = explode(',', $_POST['product_ids']);
            
            if (empty($product_ids)) {
                throw new Exception("No products selected for deletion");
            }
            
            $deleted_count = 0;
            foreach ($product_ids as $product_id) {
                $product_id = (int)$product_id;
                if ($product_id <= 0) continue;
                
                // Get product info for logging and file cleanup
                $stmt = $db->prepare("SELECT product_name, image FROM products WHERE id = ?");
                $stmt->execute([$product_id]);
                $product = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($product) {
                    // Delete image file if it exists
                    if ($product['image']) {
                        $fileHandler->deleteFile($product['image']);
                    }
                    
                    // Delete product from database
                    $stmt = $db->prepare("DELETE FROM products WHERE id = ?");
                    $stmt->execute([$product_id]);
                    
                    $deleted_count++;
                }
            }
            
            // Log the activity
            logUserActivity('bulk_delete_products', "Deleted $deleted_count products");
            
            header("Location: products.php?success=" . urlencode("$deleted_count products deleted successfully"));
            exit();
        }
        
    } catch (Exception $e) {
        error_log("Product operation error: " . $e->getMessage());
        error_log("POST data: " . print_r($_POST, true));
        if (isset($_FILES)) {
            error_log("FILES data: " . print_r($_FILES, true));
        }
        
        // Show more detailed error message in development
        $error_message = $e->getMessage();
        if (empty($error_message) || $error_message === 'An error occurred. Please try again later.') {
            $error_message = "Upload failed. Please check: 1) File size under 5MB, 2) File type is JPG/PNG/GIF, 3) Uploads folder permissions";
        }
        
        header("Location: products.php?error=" . urlencode($error_message));
        exit();
    }
}

// Check if user is searching for something
$search_term = $_GET['search'] ?? '';
$category_filter = $_GET['category_filter'] ?? '';
$stock_filter = $_GET['stock_filter'] ?? '';

$query = "SELECT p.*, c.name as category_name, s.name as supplier_name 
          FROM products p 
          LEFT JOIN categories c ON p.category_id = c.id 
          LEFT JOIN suppliers s ON p.supplier_id = s.id";

$conditions = [];
$params = [];

if (!empty($search_term)) {
    $conditions[] = "(p.product_name LIKE :search OR p.barcode LIKE :search)";
    $params[':search'] = "%$search_term%";
}

if (!empty($category_filter) && $category_filter !== '') {
    $conditions[] = "p.category_id = :category";
    $params[':category'] = $category_filter;
}

if (!empty($stock_filter) && $stock_filter !== '') {
    switch ($stock_filter) {
        case 'low':
            $conditions[] = "p.stock_quantity <= p.reorder_level AND p.stock_quantity > 0";
            break;
        case 'out':
            $conditions[] = "p.stock_quantity = 0";
            break;
        case 'good':
            $conditions[] = "p.stock_quantity > p.reorder_level";
            break;
    }
}

if (!empty($conditions)) {
    $query .= " WHERE " . implode(" AND ", $conditions);
}

$query .= " ORDER BY p.date_added DESC";

$stmt = $db->prepare($query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get categories and suppliers for dropdown
$categories = $db->query("SELECT * FROM categories")->fetchAll(PDO::FETCH_ASSOC);
$suppliers = $db->query("SELECT * FROM suppliers")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Products - Inventory System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="css/style.css?v=<?php echo time(); ?>">
    <style>
        /* Fix table alignment issues */
        .table {
            table-layout: auto !important;
        }
        
        .table th,
        .table td {
            animation: none !important;
            transform: none !important;
            transition: none !important;
        }
        
        .table tbody tr {
            animation: none !important;
            animation-delay: 0s !important;
        }
        
        .table tbody tr::before {
            display: none !important;
        }
        
        /* Clean table styling */
        .table-responsive {
            overflow-x: auto;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }
        
        .table tbody tr:hover {
            background-color: #f8f9fa !important;
        }
        
        /* Enhanced Action Buttons Styling */
        .action-buttons {
            white-space: nowrap;
        }
        
        .action-buttons .btn {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            font-weight: 500;
            border: none;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            position: relative;
            overflow: hidden;
            font-size: 12px;
            text-align: center;
            justify-content: center;
            flex-shrink: 0;
        }
        
        .action-buttons .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }
        
        .action-buttons .btn:active {
            transform: translateY(0);
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        
        /* Specific button colors with enhanced styling */
        .action-buttons .btn-info {
            background: linear-gradient(135deg, #17a2b8, #138496);
            color: white;
        }
        
        .action-buttons .btn-info:hover {
            background: linear-gradient(135deg, #138496, #117a8b);
            color: white;
        }
        
        .action-buttons .btn-primary {
            background: linear-gradient(135deg, #007bff, #0056b3);
            color: white;
        }
        
        .action-buttons .btn-primary:hover {
            background: linear-gradient(135deg, #0056b3, #004085);
            color: white;
        }
        
        .action-buttons .btn-danger {
            background: linear-gradient(135deg, #dc3545, #c82333);
            color: white;
        }
        
        .action-buttons .btn-danger:hover {
            background: linear-gradient(135deg, #c82333, #bd2130);
            color: white;
        }
        
        .action-buttons .btn-warning {
            background: linear-gradient(135deg, #ffc107, #e0a800);
            color: #212529;
        }
        
        .action-buttons .btn-warning:hover {
            background: linear-gradient(135deg, #e0a800, #d39e00);
            color: #212529;
        }
        
        .action-buttons .btn-success {
            background: linear-gradient(135deg, #28a745, #1e7e34);
            color: white;
        }
        
        .action-buttons .btn-success:hover {
            background: linear-gradient(135deg, #1e7e34, #155724);
            color: white;
        }
        
        .action-buttons .btn-secondary {
            background: linear-gradient(135deg, #6c757d, #545b62);
            color: white;
        }
        
        .action-buttons .btn-secondary:hover {
            background: linear-gradient(135deg, #545b62, #495057);
            color: white;
        }
        
        /* Outline button styles */
        .action-buttons .btn-outline-secondary {
            border: 2px solid #6c757d;
            color: #6c757d;
            background: transparent;
        }
        
        .action-buttons .btn-outline-secondary:hover {
            background: linear-gradient(135deg, #6c757d, #545b62);
            border-color: #6c757d;
            color: white;
        }
        
        .action-buttons .btn-outline-success {
            border: 2px solid #28a745;
            color: #28a745;
            background: transparent;
        }
        
        .action-buttons .btn-outline-success:hover {
            background: linear-gradient(135deg, #28a745, #1e7e34);
            border-color: #28a745;
            color: white;
        }
        
        .action-buttons .btn-outline-warning {
            border: 2px solid #ffc107;
            color: #ffc107;
            background: transparent;
        }
        
        .action-buttons .btn-outline-warning:hover {
            background: linear-gradient(135deg, #ffc107, #e0a800);
            border-color: #ffc107;
            color: #212529;
        }
        
        .action-buttons .btn-outline-danger {
            border: 2px solid #dc3545;
            color: #dc3545;
            background: transparent;
        }
        
        .action-buttons .btn-outline-danger:hover {
            background: linear-gradient(135deg, #dc3545, #c82333);
            border-color: #dc3545;
            color: white;
        }
        
        /* Ripple effect for buttons */
        .action-buttons .btn::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.3);
            transform: translate(-50%, -50%);
            transition: width 0.3s, height 0.3s;
        }
        
        .action-buttons .btn:active::before {
            width: 300px;
            height: 300px;
        }
        
        /* Icon spacing */
        .action-buttons .btn i {
            margin-right: 4px;
        }
        
        /* Modal footer button styling */
        .modal-footer .btn {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            font-weight: 500;
            border: none;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        
        .modal-footer .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }
        
        /* Clean button styling */
        .btn-group .btn {
            transition: all 0.2s ease;
        }
        
        .btn-group .btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
    </style>
</head>
<body>
    <?php include 'includes/navbar.php'; ?>
    
    <div class="container-fluid">
        <div class="sidebar-overlay"></div>
        <?php include 'includes/sidebar.php'; ?>
        
        <main>
                <?php if (isset($_GET['success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="bi bi-check-circle"></i>
                        <?php echo htmlspecialchars(urldecode($_GET['success'])); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($_GET['error'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="bi bi-exclamation-triangle"></i>
                        <?php echo htmlspecialchars(urldecode($_GET['error'])); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2"><i class="bi bi-box-seam"></i> Products</h1>
                    <div class="d-flex gap-2 align-items-center">
                        <div class="btn-group" id="bulk-actions" style="display: none;">
                            <button class="btn btn-outline-warning rounded-pill px-4 py-2" onclick="bulkUpdateStock()">
                                <i class="bi bi-arrow-up-circle me-2"></i> Update Stock
                            </button>
                            <?php if (isAdmin()): ?>
                                <button class="btn btn-outline-danger rounded-pill px-4 py-2" onclick="bulkDelete()">
                                    <i class="bi bi-trash me-2"></i> Delete Selected
                                </button>
                            <?php endif; ?>
                        </div>
                        <button onclick="printProducts()" class="btn btn-outline-secondary rounded-pill px-4 py-2" title="Print Products Report">
                            <i class="bi bi-printer me-2"></i> Print
                        </button>
                        <button onclick="exportProductsCSV()" class="btn btn-outline-success rounded-pill px-4 py-2" title="Export Products to CSV">
                            <i class="bi bi-file-earmark-spreadsheet me-2"></i> Export CSV
                        </button>
                        <button class="btn btn-primary rounded-pill px-4 py-2" data-bs-toggle="modal" data-bs-target="#addProductModal">
                            <i class="bi bi-plus-lg me-2"></i> Add Product
                        </button>
                    </div>
                </div>

                <form method="GET" class="mb-3">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <input type="text" name="search" class="form-control" placeholder="🔍 Search products..." value="<?php echo htmlspecialchars($search_term); ?>">
                        </div>
                        <div class="col-md-3">
                            <select name="category_filter" class="form-select">
                                <option value="">All Categories</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo $cat['id']; ?>" <?php echo ($category_filter == $cat['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($cat['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <select name="stock_filter" class="form-select">
                                <option value="">All Stock Levels</option>
                                <option value="low" <?php echo ($stock_filter == 'low') ? 'selected' : ''; ?>>Low Stock</option>
                                <option value="out" <?php echo ($stock_filter == 'out') ? 'selected' : ''; ?>>Out of Stock</option>
                                <option value="good" <?php echo ($stock_filter == 'good') ? 'selected' : ''; ?>>Good Stock</option>
                            </select>
                        </div>
                                        <div class="col-md-2">
                            <div class="d-flex gap-1">
                                <button class="btn btn-outline-secondary rounded-pill px-3" type="submit">
                                    <i class="bi bi-funnel me-1"></i> Filter
                                </button>
                                <a href="products.php" class="btn btn-outline-danger rounded-pill px-2" title="Clear Filters">
                                    <i class="bi bi-x-lg"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                    <?php if (!empty($search_term) || !empty($category_filter) || !empty($stock_filter)): ?>
                        <div class="mt-2">
                            <small class="text-muted">
                                <i class="bi bi-funnel-fill"></i> 
                                Filters active: 
                                <?php if (!empty($search_term)): ?>
                                    <span class="badge bg-primary">Search: "<?php echo htmlspecialchars($search_term); ?>"</span>
                                <?php endif; ?>
                                <?php if (!empty($category_filter)): ?>
                                    <?php 
                                    $selected_cat = array_filter($categories, function($cat) use ($category_filter) {
                                        return $cat['id'] == $category_filter;
                                    });
                                    $selected_cat = reset($selected_cat);
                                    ?>
                                    <span class="badge bg-info">Category: <?php echo htmlspecialchars($selected_cat['name']); ?></span>
                                <?php endif; ?>
                                <?php if (!empty($stock_filter)): ?>
                                    <span class="badge bg-warning">Stock: <?php echo ucfirst($stock_filter); ?></span>
                                <?php endif; ?>
                                <a href="products.php" class="ms-2 text-decoration-none">Clear all</a>
                            </small>
                        </div>
                    <?php endif; ?>
                </form>

                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="bi bi-list-ul"></i> 
                            Showing <?php echo count($products); ?> products
                            <?php if (!empty($search_term) || !empty($category_filter) || !empty($stock_filter)): ?>
                                <small class="text-muted">(filtered)</small>
                            <?php endif; ?>
                        </h5>
                        <small class="text-muted">Last updated: <?php echo date('M d, Y H:i'); ?></small>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover mb-0">
                                <thead class="table-dark">
                                    <tr>
                                        <th>
                                            <input type="checkbox" id="select-all" onchange="toggleSelectAll()">
                                        </th>
                                        <th>Image</th>
                                        <th>Product Name</th>
                                        <th>Category</th>
                                        <th>Supplier</th>
                                        <th>Cost Price</th>
                                        <th>Selling Price</th>
                                        <th>Profit/Unit</th>
                                        <th>Profit %</th>
                                        <th>Stock</th>
                                        <th>Reorder Level</th>
                                        <th>Barcode</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                        <tbody>
                            <?php if (empty($products)): ?>
                                <tr>
                                    <td colspan="12" class="text-center py-4">
                                        <div class="text-muted">
                                            <i class="bi bi-inbox fs-1"></i>
                                            <p class="mt-2">No products found</p>
                                            <?php if (!empty($search_term) || !empty($category_filter) || !empty($stock_filter)): ?>
                                                <p><small>Try adjusting your filters or <a href="products.php">clear all filters</a></small></p>
                                            <?php else: ?>
                                                <p><small>Start by adding your first product</small></p>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($products as $product): ?>
                                    <tr>
                                        <td>
                                            <input type="checkbox" class="product-checkbox" value="<?php echo $product['id']; ?>" onchange="updateBulkActions()">
                                        </td>
                                        <td>
                                            <?php if ($product['image']): ?>
                                                <img src="uploads/<?php echo htmlspecialchars($product['image']); ?>" width="50" height="50" class="img-thumbnail">
                                            <?php else: ?>
                                                <div class="bg-secondary d-flex align-items-center justify-content-center" style="width:50px;height:50px;">
                                                    <i class="bi bi-image text-white"></i>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($product['product_name']); ?></td>
                                        <td><?php echo htmlspecialchars($product['category_name'] ?? 'No Category'); ?></td>
                                        <td><?php echo htmlspecialchars($product['supplier_name'] ?? 'No Supplier'); ?></td>
                                        <td>₱<?php echo number_format($product['cost_price'] ?? 0, 2); ?></td>
                                        <td>₱<?php echo number_format($product['price'], 2); ?></td>
                                        <td>
                                            <?php 
                                            $cost_price = $product['cost_price'] ?? 0;
                                            $selling_price = $product['price'];
                                            $profit_per_unit = $selling_price - $cost_price;
                                            $profit_color = $profit_per_unit > 0 ? 'text-success' : ($profit_per_unit < 0 ? 'text-danger' : 'text-muted');
                                            ?>
                                            <span class="<?php echo $profit_color; ?> fw-bold">
                                                ₱<?php echo number_format($profit_per_unit, 2); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php 
                                            $profit_percentage = $cost_price > 0 ? (($profit_per_unit / $cost_price) * 100) : 0;
                                            $percentage_color = $profit_percentage > 20 ? 'success' : ($profit_percentage > 10 ? 'warning' : 'danger');
                                            ?>
                                            <span class="badge bg-<?php echo $percentage_color; ?>">
                                                <?php echo number_format($profit_percentage, 1); ?>%
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge <?php echo $product['stock_quantity'] <= $product['reorder_level'] ? 'bg-danger' : 'bg-success'; ?>">
                                                <?php echo $product['stock_quantity']; ?>
                                            </span>
                                        </td>
                                        <td><?php echo $product['reorder_level']; ?></td>
                                        <td><?php echo htmlspecialchars($product['barcode']); ?></td>
                                        <td>
                                            <div class="action-buttons d-flex gap-1 align-items-center justify-content-start">
                                                <a href="product_detail.php?id=<?php echo $product['id']; ?>" class="btn btn-sm btn-info rounded-pill px-3 py-1 d-flex align-items-center" title="View Details" style="min-width: 70px; height: 32px;">
                                                    <i class="bi bi-eye me-1"></i> View
                                                </a>
                                                <button class="btn btn-sm btn-primary rounded-pill px-3 py-1 d-flex align-items-center" onclick="openEditModal(<?php echo $product['id']; ?>)" title="Edit Product" style="min-width: 70px; height: 32px;">
                                                    <i class="bi bi-pencil me-1"></i> Edit
                                                </button>
                                                <?php if (isAdmin()): ?>
                                                    <button type="button" class="btn btn-sm btn-danger rounded-pill px-3 py-1 d-flex align-items-center" onclick="deleteProduct(<?php echo $product['id']; ?>)" title="Delete Product" style="min-width: 70px; height: 32px;">
                                                        <i class="bi bi-trash me-1"></i> Delete
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                        </div>
                    </div>
                </div>
        </main>
    </div>

    <div class="modal fade" id="addProductModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="action" value="add">
                    <div class="modal-header">
                        <h5 class="modal-title">Add Product</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Product Name</label>
                            <input type="text" name="product_name" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Category</label>
                            <select name="category_id" class="form-select" required>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Supplier</label>
                            <select name="supplier_id" class="form-select" required>
                                <?php foreach ($suppliers as $sup): ?>
                                    <option value="<?php echo $sup['id']; ?>"><?php echo htmlspecialchars($sup['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Cost Price</label>
                            <input type="number" step="0.01" name="cost_price" class="form-control" required placeholder="Enter cost price">
                            <small class="text-muted">The price you pay to acquire this product</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Selling Price</label>
                            <input type="number" step="0.01" name="price" class="form-control" required placeholder="Enter selling price">
                            <small class="text-muted">The price you sell this product for</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Stock Quantity</label>
                            <input type="number" name="stock_quantity" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Reorder Level</label>
                            <input type="number" name="reorder_level" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Barcode</label>
                            <input type="text" name="barcode" class="form-control">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Product Image</label>
                            <input type="file" name="image" class="form-control" accept="image/*">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary rounded-pill px-4 py-2" data-bs-dismiss="modal">
                            <i class="bi bi-x-lg me-2"></i> Close
                        </button>
                        <button type="submit" class="btn btn-primary rounded-pill px-4 py-2">
                            <i class="bi bi-plus-circle me-2"></i> Add Product
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Product Modal -->
    <div class="modal fade" id="editProductModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="product_id" id="edit_product_id">
                    <div class="modal-header">
                        <h5 class="modal-title">Edit Product</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Product Name</label>
                            <input type="text" name="product_name" id="edit_product_name" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Category</label>
                            <select name="category_id" id="edit_category_id" class="form-select" required>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Supplier</label>
                            <select name="supplier_id" id="edit_supplier_id" class="form-select" required>
                                <?php foreach ($suppliers as $sup): ?>
                                    <option value="<?php echo $sup['id']; ?>"><?php echo htmlspecialchars($sup['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Cost Price</label>
                            <input type="number" step="0.01" name="cost_price" id="edit_cost_price" class="form-control" required>
                            <small class="text-muted">The price you pay to acquire this product</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Selling Price</label>
                            <input type="number" step="0.01" name="price" id="edit_price" class="form-control" required>
                            <small class="text-muted">The price you sell this product for</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Stock Quantity</label>
                            <input type="number" name="stock_quantity" id="edit_stock_quantity" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Reorder Level</label>
                            <input type="number" name="reorder_level" id="edit_reorder_level" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Barcode</label>
                            <input type="text" name="barcode" id="edit_barcode" class="form-control">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Product Image</label>
                            <div id="current_image_preview" class="mb-2" style="display: none;">
                                <small class="text-muted">Current image:</small><br>
                                <img id="current_image" src="" width="100" height="100" class="img-thumbnail">
                            </div>
                            <input type="file" name="image" class="form-control" accept="image/*">
                            <small class="text-muted">Leave empty to keep current image</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary rounded-pill px-4 py-2" data-bs-dismiss="modal">
                            <i class="bi bi-x-lg me-2"></i> Cancel
                        </button>
                        <button type="submit" class="btn btn-primary rounded-pill px-4 py-2">
                            <i class="bi bi-check-lg me-2"></i> Update Product
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Bulk Stock Update Modal -->
    <div class="modal fade" id="bulkStockModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form id="bulkStockForm">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <div class="modal-header">
                        <h5 class="modal-title">Bulk Stock Update</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle"></i>
                            <span id="bulk-selected-count">0</span> products selected for stock update.
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Update Type</label>
                            <select id="stock-update-type" class="form-select" required>
                                <option value="add">Add to current stock</option>
                                <option value="subtract">Subtract from current stock</option>
                                <option value="set">Set exact stock amount</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Quantity</label>
                            <input type="number" id="stock-quantity" class="form-control" min="0" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Reason (Optional)</label>
                            <input type="text" id="stock-reason" class="form-control" placeholder="e.g., New shipment, Inventory adjustment">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary rounded-pill px-4 py-2" data-bs-dismiss="modal">
                            <i class="bi bi-x-lg me-2"></i> Cancel
                        </button>
                        <button type="submit" class="btn btn-warning rounded-pill px-4 py-2">
                            <i class="bi bi-arrow-up-circle me-2"></i> Update Stock
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php include 'includes/chatbot.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/chatbot.js?v=<?php echo time(); ?>&cb=<?php echo rand(); ?>"></script>
    <script src="js/export.js?v=<?php echo time(); ?>"></script>
    <script src="js/mobile.js?v=<?php echo time(); ?>"></script>
    
    <script>
        // Define openEditModal function at the top level to ensure it's available
        function openEditModal(productId) {
            // Find the product data from the table row
            const row = document.querySelector(`input[value="${productId}"]`).closest('tr');
            const cells = row.querySelectorAll('td');
            
            // Extract data from table cells
            const productName = cells[2].textContent.trim();
            const categoryName = cells[3].textContent.trim();
            const supplierName = cells[4].textContent.trim();
            const costPrice = cells[5].textContent.replace('₱', '').replace(',', '').trim();
            const price = cells[6].textContent.replace('₱', '').replace(',', '').trim();
            const stock = cells[9].querySelector('.badge').textContent.trim();
            const reorderLevel = cells[10].textContent.trim();
            const barcode = cells[11].textContent.trim();
            
            // Get the image src if exists
            const imgElement = cells[1].querySelector('img');
            const imageSrc = imgElement ? imgElement.src : null;
            
            // Populate the edit form
            document.getElementById('edit_product_id').value = productId;
            document.getElementById('edit_product_name').value = productName;
            document.getElementById('edit_cost_price').value = costPrice;
            document.getElementById('edit_price').value = price;
            document.getElementById('edit_stock_quantity').value = stock;
            document.getElementById('edit_reorder_level').value = reorderLevel;
            document.getElementById('edit_barcode').value = barcode;
            
            // Set category dropdown
            const categorySelect = document.getElementById('edit_category_id');
            for (let option of categorySelect.options) {
                if (option.text === categoryName) {
                    option.selected = true;
                    break;
                }
            }
            
            // Set supplier dropdown
            const supplierSelect = document.getElementById('edit_supplier_id');
            for (let option of supplierSelect.options) {
                if (option.text === supplierName) {
                    option.selected = true;
                    break;
                }
            }
            
            // Show current image if exists
            const imagePreview = document.getElementById('current_image_preview');
            const currentImage = document.getElementById('current_image');
            
            if (imageSrc && !imageSrc.includes('bi-image')) {
                currentImage.src = imageSrc;
                imagePreview.style.display = 'block';
            } else {
                imagePreview.style.display = 'none';
            }
            
            // Show the modal
            const modal = new bootstrap.Modal(document.getElementById('editProductModal'));
            modal.show();
        }

        // Delete product function
        function deleteProduct(productId) {
            if (confirm('Are you sure you want to delete this product? This action cannot be undone.')) {
                // Create a form to submit the delete request
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="product_id" value="${productId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        let currentProduct = {};
        
        // Bulk actions functionality
        function toggleSelectAll() {
            const selectAll = document.getElementById('select-all');
            const checkboxes = document.querySelectorAll('.product-checkbox');
            
            checkboxes.forEach(checkbox => {
                checkbox.checked = selectAll.checked;
            });
            
            updateBulkActions();
        }
        
        function updateBulkActions() {
            const checkboxes = document.querySelectorAll('.product-checkbox:checked');
            const bulkActions = document.getElementById('bulk-actions');
            
            if (checkboxes.length > 0) {
                bulkActions.style.display = 'inline-block';
            } else {
                bulkActions.style.display = 'none';
            }
        }
        
        function getSelectedProducts() {
            const checkboxes = document.querySelectorAll('.product-checkbox:checked');
            return Array.from(checkboxes).map(cb => cb.value);
        }
        
        function bulkUpdateStock() {
            const selected = getSelectedProducts();
            if (selected.length === 0) {
                alert('Please select products to update');
                return;
            }
            
            document.getElementById('bulk-selected-count').textContent = selected.length;
            const modal = new bootstrap.Modal(document.getElementById('bulkStockModal'));
            modal.show();
        }
        
        function bulkDelete() {
            const selected = getSelectedProducts();
            if (selected.length === 0) {
                alert('Please select products to delete');
                return;
            }
            
            if (confirm(`Are you sure you want to delete ${selected.length} selected products? This action cannot be undone.`)) {
                // Create a form to submit the bulk delete
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="action" value="bulk_delete">
                    <input type="hidden" name="product_ids" value="${selected.join(',')}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        // Handle bulk stock update form submission
        document.getElementById('bulkStockForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const selected = getSelectedProducts();
            const updateType = document.getElementById('stock-update-type').value;
            const quantity = document.getElementById('stock-quantity').value;
            const reason = document.getElementById('stock-reason').value;
            
            if (!quantity || quantity < 0) {
                alert('Please enter a valid quantity');
                return;
            }
            
            // Create form to submit bulk stock update
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <input type="hidden" name="action" value="bulk_stock_update">
                <input type="hidden" name="product_ids" value="${selected.join(',')}">
                <input type="hidden" name="update_type" value="${updateType}">
                <input type="hidden" name="quantity" value="${quantity}">
                <input type="hidden" name="reason" value="${reason}">
            `;
            document.body.appendChild(form);
            form.submit();
        });
        
        // Add event listener for quantity input
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize bulk actions visibility
            updateBulkActions();
            
            // Add real-time profit calculation
            function calculateProfit() {
                const costPriceInputs = document.querySelectorAll('input[name="cost_price"]');
                const priceInputs = document.querySelectorAll('input[name="price"]');
                
                costPriceInputs.forEach((costInput, index) => {
                    const priceInput = priceInputs[index];
                    if (costInput && priceInput) {
                        const updateProfitDisplay = () => {
                            const cost = parseFloat(costInput.value) || 0;
                            const price = parseFloat(priceInput.value) || 0;
                            const profit = price - cost;
                            const margin = cost > 0 ? ((profit / cost) * 100) : 0;
                            
                            // Find or create profit display
                            let profitDisplay = costInput.parentNode.querySelector('.profit-display');
                            if (!profitDisplay) {
                                profitDisplay = document.createElement('div');
                                profitDisplay.className = 'profit-display mt-2';
                                costInput.parentNode.appendChild(profitDisplay);
                            }
                            
                            if (cost > 0 && price > 0) {
                                const profitColor = profit > 0 ? 'text-success' : 'text-danger';
                                const marginColor = margin > 20 ? 'success' : (margin > 10 ? 'warning' : 'danger');
                                
                                profitDisplay.innerHTML = `
                                    <small class="d-block">
                                        <span class="${profitColor}">
                                            <i class="bi bi-calculator"></i> 
                                            Profit: ₱${profit.toFixed(2)} per unit
                                        </span>
                                    </small>
                                    <small class="d-block">
                                        <span class="badge bg-${marginColor}">
                                            ${margin.toFixed(1)}% margin
                                        </span>
                                    </small>
                                `;
                            } else {
                                profitDisplay.innerHTML = '';
                            }
                        };
                        
                        costInput.addEventListener('input', updateProfitDisplay);
                        priceInput.addEventListener('input', updateProfitDisplay);
                    }
                });
            }
            
            // Initialize profit calculation
            calculateProfit();
            
            // Re-initialize when modals are shown
            document.getElementById('addProductModal').addEventListener('shown.bs.modal', calculateProfit);
            document.getElementById('editProductModal').addEventListener('shown.bs.modal', calculateProfit);
            
            // Add scroll reveal animations
            const observerOptions = {
                threshold: 0.1,
                rootMargin: '0px 0px -50px 0px'
            };
            
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('revealed');
                    }
                });
            }, observerOptions);
            
            // Observe all stagger items
            document.querySelectorAll('.stagger-item').forEach(item => {
                item.classList.add('scroll-reveal');
                observer.observe(item);
            });
            
            // Add loading states to buttons
            document.querySelectorAll('form').forEach(form => {
                form.addEventListener('submit', function(e) {
                    const submitBtn = form.querySelector('button[type="submit"]');
                    if (submitBtn && !submitBtn.disabled) {
                        submitBtn.disabled = true;
                        const originalText = submitBtn.innerHTML;
                        submitBtn.innerHTML = '<span class="loading-spinner"></span> Processing...';
                        
                        // Re-enable after 3 seconds as fallback
                        setTimeout(() => {
                            submitBtn.disabled = false;
                            submitBtn.innerHTML = originalText;
                        }, 3000);
                    }
                });
            });
            
            // Add hover effects to table rows with staggered animation
            document.querySelectorAll('tbody tr').forEach((row, index) => {
                row.style.animationDelay = `${index * 0.05}s`;
                row.classList.add('fade-in-row');
                
                // Enhanced row hover effects
                row.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateX(5px)';
                    this.style.transition = 'all 0.3s cubic-bezier(0.4, 0, 0.2, 1)';
                    this.style.boxShadow = '0 4px 12px rgba(0, 0, 0, 0.1)';
                });
                
                row.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateX(0)';
                    this.style.boxShadow = 'none';
                });
            });
            
            // Add success animation for form submissions
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('success')) {
                // Add success checkmark animation
                setTimeout(() => {
                    const alert = document.querySelector('.alert-success');
                    if (alert) {
                        alert.classList.add('micro-bounce');
                        
                        // Add floating success icon
                        const successIcon = document.createElement('div');
                        successIcon.innerHTML = '✓';
                        successIcon.style.cssText = `
                            position: fixed;
                            top: 20px;
                            right: 20px;
                            background: #28a745;
                            color: white;
                            width: 50px;
                            height: 50px;
                            border-radius: 50%;
                            display: flex;
                            align-items: center;
                            justify-content: center;
                            font-size: 24px;
                            font-weight: bold;
                            z-index: 9999;
                            animation: successFloat 2s ease-out forwards;
                        `;
                        document.body.appendChild(successIcon);
                        
                        setTimeout(() => {
                            successIcon.remove();
                        }, 2000);
                    }
                }, 100);
            }
            
            // Enhanced button click effects with ripple
            document.querySelectorAll('.btn').forEach(btn => {
                btn.addEventListener('click', function(e) {
                    // Create ripple effect
                    const ripple = document.createElement('span');
                    const rect = this.getBoundingClientRect();
                    const size = Math.max(rect.width, rect.height);
                    const x = e.clientX - rect.left - size / 2;
                    const y = e.clientY - rect.top - size / 2;
                    
                    ripple.style.width = ripple.style.height = size + 'px';
                    ripple.style.left = x + 'px';
                    ripple.style.top = y + 'px';
                    ripple.classList.add('ripple');
                    
                    this.appendChild(ripple);
                    
                    setTimeout(() => {
                        ripple.remove();
                    }, 600);
                    
                    // Add button press animation
                    this.style.transform = 'scale(0.95)';
                    setTimeout(() => {
                        this.style.transform = '';
                    }, 150);
                });
            });
            
            // Animate checkboxes
            document.querySelectorAll('input[type="checkbox"]').forEach(checkbox => {
                checkbox.addEventListener('change', function() {
                    if (this.checked) {
                        this.style.animation = 'checkboxPulse 0.3s ease';
                        setTimeout(() => {
                            this.style.animation = '';
                        }, 300);
                    }
                });
            });
            
            // Animate image thumbnails
            document.querySelectorAll('.img-thumbnail').forEach(img => {
                img.addEventListener('mouseenter', function() {
                    this.style.transform = 'scale(1.1) rotate(2deg)';
                    this.style.transition = 'all 0.3s cubic-bezier(0.4, 0, 0.2, 1)';
                });
                
                img.addEventListener('mouseleave', function() {
                    this.style.transform = 'scale(1) rotate(0deg)';
                });
            });
            
            // Animate badges on hover
            document.querySelectorAll('.badge').forEach(badge => {
                badge.addEventListener('mouseenter', function() {
                    this.style.transform = 'scale(1.1)';
                    this.style.transition = 'all 0.2s ease';
                });
                
                badge.addEventListener('mouseleave', function() {
                    this.style.transform = 'scale(1)';
                });
            });
            
            // Add floating animation to action buttons
            document.querySelectorAll('.action-buttons .btn').forEach((btn, index) => {
                btn.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-3px)';
                    this.style.transition = 'all 0.3s cubic-bezier(0.4, 0, 0.2, 1)';
                    this.style.zIndex = '10';
                });
                
                btn.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0)';
                    this.style.zIndex = '1';
                });
            });
            
            // Add pulse animation to primary buttons
            document.querySelectorAll('.btn-primary').forEach(btn => {
                if (btn.classList.contains('glow')) {
                    setInterval(() => {
                        btn.style.boxShadow = '0 0 20px rgba(0, 123, 255, 0.5)';
                        setTimeout(() => {
                            btn.style.boxShadow = '';
                        }, 1000);
                    }, 3000);
                }
            });
        });
        
        // Add CSS animations
        const animationStyle = document.createElement('style');
        animationStyle.textContent = `
            @keyframes successFloat {
                0% {
                    transform: translateY(100px) scale(0);
                    opacity: 0;
                }
                50% {
                    transform: translateY(0) scale(1.2);
                    opacity: 1;
                }
                100% {
                    transform: translateY(-20px) scale(1);
                    opacity: 0;
                }
            }
            
            @keyframes fadeInRow {
                from {
                    opacity: 0;
                    transform: translateX(-20px);
                }
                to {
                    opacity: 1;
                    transform: translateX(0);
                }
            }
            
            .fade-in-row {
                animation: fadeInRow 0.5s ease-out both;
            }
            
            @keyframes checkboxPulse {
                0% { transform: scale(1); }
                50% { transform: scale(1.2); }
                100% { transform: scale(1); }
            }
            
            .ripple {
                position: absolute;
                border-radius: 50%;
                background: rgba(255, 255, 255, 0.6);
                transform: scale(0);
                animation: ripple 0.6s linear;
                pointer-events: none;
            }
            
            @keyframes ripple {
                to {
                    transform: scale(4);
                    opacity: 0;
                }
            }
        `;
        document.head.appendChild(animationStyle);
    </script>
</body>
</html>
