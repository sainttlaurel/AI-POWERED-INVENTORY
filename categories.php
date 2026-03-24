<?php
require_once 'config/database.php';
require_once 'config/session.php';
requireLogin();

$database = new Database();
$db = $database->getConnection();

$success_message = '';
$error_message = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        die('Invalid CSRF token');
    }
    
    if ($_POST['action'] === 'add') {
        $name = trim($_POST['category_name']);
        $description = trim($_POST['description']);
        
        if (!empty($name)) {
            try {
                $db->beginTransaction();
                
                // Add category
                $stmt = $db->prepare("INSERT INTO categories (name, description) VALUES (:name, :description)");
                $stmt->execute([':name' => $name, ':description' => $description]);
                
                // Check if admin wants to also add as location
                if (isAdmin() && isset($_POST['also_add_location_cat']) && !empty($_POST['location_code']) || !empty($_POST['location_address'])) {
                    // Create locations table if it doesn't exist
                    $db->exec("CREATE TABLE IF NOT EXISTS locations (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        name VARCHAR(100) NOT NULL,
                        code VARCHAR(20) UNIQUE NOT NULL,
                        type ENUM('warehouse', 'store', 'outlet') DEFAULT 'store',
                        address TEXT NULL,
                        phone VARCHAR(20) NULL,
                        email VARCHAR(100) NULL,
                        status ENUM('active', 'inactive') DEFAULT 'active',
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                    )");
                    
                    $location_code = !empty($_POST['location_code']) ? strtoupper(trim($_POST['location_code'])) : strtoupper(substr($name, 0, 3)) . '01';
                    $location_type = $_POST['location_type'] ?? 'store';
                    $location_address = trim($_POST['location_address'] ?? '');
                    $location_phone = trim($_POST['location_phone'] ?? '');
                    $location_email = trim($_POST['location_email'] ?? '');
                    
                    try {
                        $stmt = $db->prepare("INSERT INTO locations (name, code, type, address, phone, email) VALUES (?, ?, ?, ?, ?, ?)");
                        $stmt->execute([$name, $location_code, $location_type, $location_address, $location_phone, $location_email]);
                    } catch (Exception $e) {
                        // If code already exists, try with random suffix
                        $location_code = strtoupper(substr($name, 0, 3)) . rand(10, 99);
                        $stmt = $db->prepare("INSERT INTO locations (name, code, type, address, phone, email) VALUES (?, ?, ?, ?, ?, ?)");
                        $stmt->execute([$name, $location_code, $location_type, $location_address, $location_phone, $location_email]);
                    }
                }
                
                $db->commit();
                $success_message = "Category '{$name}' added successfully!" . (isset($_POST['also_add_location_cat']) ? " Also added as location." : "");
            } catch (Exception $e) {
                $db->rollback();
                $error_message = "Error adding category: " . $e->getMessage();
            }
        } else {
            $error_message = "Category name is required.";
        }
    }
    
    if ($_POST['action'] === 'delete' && isAdmin()) {
        $category_id = (int)$_POST['category_id'];
        $move_to_category = (int)$_POST['move_to_category'];
        
        try {
            $db->beginTransaction();
            
            // Get category name for confirmation
            $stmt = $db->prepare("SELECT name FROM categories WHERE id = :id");
            $stmt->execute([':id' => $category_id]);
            $category = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($category) {
                // Count products in this category
                $stmt = $db->prepare("SELECT COUNT(*) as count FROM products WHERE category_id = :id");
                $stmt->execute([':id' => $category_id]);
                $product_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
                
                if ($product_count > 0 && $move_to_category > 0) {
                    // Move products to selected category
                    $stmt = $db->prepare("UPDATE products SET category_id = :new_id WHERE category_id = :old_id");
                    $stmt->execute([':new_id' => $move_to_category, ':old_id' => $category_id]);
                    
                    // Get destination category name
                    $stmt = $db->prepare("SELECT name FROM categories WHERE id = :id");
                    $stmt->execute([':id' => $move_to_category]);
                    $dest_category = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    $success_message = "Moved {$product_count} products from '{$category['name']}' to '{$dest_category['name']}' and ";
                }
                
                // Delete the category
                $stmt = $db->prepare("DELETE FROM categories WHERE id = :id");
                $stmt->execute([':id' => $category_id]);
                
                $success_message .= "deleted category '{$category['name']}'.";
                $db->commit();
            } else {
                $error_message = "Category not found.";
            }
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollback();
            }
            $error_message = "Error deleting category: " . $e->getMessage();
        }
    }
    
    if ($_POST['action'] === 'edit' && isAdmin()) {
        $category_id = (int)$_POST['category_id'];
        $name = trim($_POST['category_name']);
        $description = trim($_POST['description']);
        
        if (!empty($name)) {
            try {
                $stmt = $db->prepare("UPDATE categories SET name = :name, description = :description WHERE id = :id");
                $stmt->execute([':name' => $name, ':description' => $description, ':id' => $category_id]);
                $success_message = "Category updated successfully!";
            } catch (Exception $e) {
                $error_message = "Error updating category: " . $e->getMessage();
            }
        } else {
            $error_message = "Category name is required.";
        }
    }
    
    // Supplier management actions
    if ($_POST['action'] === 'add_supplier') {
        $name = trim($_POST['supplier_name']);
        $contact_person = trim($_POST['contact_person']);
        $email = trim($_POST['email']);
        $phone = trim($_POST['phone']);
        $address = trim($_POST['address']);
        
        if (!empty($name)) {
            try {
                $db->beginTransaction();
                
                // Add supplier
                $stmt = $db->prepare("INSERT INTO suppliers (name, contact_person, email, phone, address) VALUES (:name, :contact_person, :email, :phone, :address)");
                $stmt->execute([
                    ':name' => $name, 
                    ':contact_person' => $contact_person, 
                    ':email' => $email, 
                    ':phone' => $phone, 
                    ':address' => $address
                ]);
                
                // Check if admin wants to also add as location
                if (isAdmin() && isset($_POST['also_add_location_sup']) && (!empty($_POST['location_code']) || !empty($address))) {
                    // Create locations table if it doesn't exist
                    $db->exec("CREATE TABLE IF NOT EXISTS locations (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        name VARCHAR(100) NOT NULL,
                        code VARCHAR(20) UNIQUE NOT NULL,
                        type ENUM('warehouse', 'store', 'outlet') DEFAULT 'store',
                        address TEXT NULL,
                        phone VARCHAR(20) NULL,
                        email VARCHAR(100) NULL,
                        status ENUM('active', 'inactive') DEFAULT 'active',
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                    )");
                    
                    $location_code = !empty($_POST['location_code']) ? strtoupper(trim($_POST['location_code'])) : 'SUP' . rand(10, 99);
                    $location_type = $_POST['location_type'] ?? 'warehouse';
                    
                    try {
                        $stmt = $db->prepare("INSERT INTO locations (name, code, type, address, phone, email) VALUES (?, ?, ?, ?, ?, ?)");
                        $stmt->execute([$name, $location_code, $location_type, $address, $phone, $email]);
                    } catch (Exception $e) {
                        // If code already exists, try with different suffix
                        $location_code = 'SUP' . rand(100, 999);
                        $stmt = $db->prepare("INSERT INTO locations (name, code, type, address, phone, email) VALUES (?, ?, ?, ?, ?, ?)");
                        $stmt->execute([$name, $location_code, $location_type, $address, $phone, $email]);
                    }
                }
                
                $db->commit();
                $success_message = "Supplier '{$name}' added successfully!" . (isset($_POST['also_add_location_sup']) ? " Also added as location." : "");
            } catch (Exception $e) {
                $db->rollback();
                $error_message = "Error adding supplier: " . $e->getMessage();
            }
        } else {
            $error_message = "Supplier name is required.";
        }
    }
    
    if ($_POST['action'] === 'edit_supplier' && isAdmin()) {
        $supplier_id = (int)$_POST['supplier_id'];
        $name = trim($_POST['supplier_name']);
        $contact_person = trim($_POST['contact_person']);
        $email = trim($_POST['email']);
        $phone = trim($_POST['phone']);
        $address = trim($_POST['address']);
        
        if (!empty($name)) {
            try {
                $stmt = $db->prepare("UPDATE suppliers SET name = :name, contact_person = :contact_person, email = :email, phone = :phone, address = :address WHERE id = :id");
                $stmt->execute([
                    ':name' => $name, 
                    ':contact_person' => $contact_person, 
                    ':email' => $email, 
                    ':phone' => $phone, 
                    ':address' => $address, 
                    ':id' => $supplier_id
                ]);
                $success_message = "Supplier updated successfully!";
            } catch (Exception $e) {
                $error_message = "Error updating supplier: " . $e->getMessage();
            }
        } else {
            $error_message = "Supplier name is required.";
        }
    }
    
    if ($_POST['action'] === 'delete_supplier' && isAdmin()) {
        $supplier_id = (int)$_POST['supplier_id'];
        $move_to_supplier = (int)$_POST['move_to_supplier'];
        
        try {
            $db->beginTransaction();
            
            // Get supplier name for confirmation
            $stmt = $db->prepare("SELECT name FROM suppliers WHERE id = :id");
            $stmt->execute([':id' => $supplier_id]);
            $supplier = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($supplier) {
                // Count products from this supplier
                $stmt = $db->prepare("SELECT COUNT(*) as count FROM products WHERE supplier_id = :id");
                $stmt->execute([':id' => $supplier_id]);
                $product_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
                
                if ($product_count > 0 && $move_to_supplier > 0) {
                    // Move products to selected supplier
                    $stmt = $db->prepare("UPDATE products SET supplier_id = :new_id WHERE supplier_id = :old_id");
                    $stmt->execute([':new_id' => $move_to_supplier, ':old_id' => $supplier_id]);
                    
                    // Get destination supplier name
                    $stmt = $db->prepare("SELECT name FROM suppliers WHERE id = :id");
                    $stmt->execute([':id' => $move_to_supplier]);
                    $dest_supplier = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    $success_message = "Moved {$product_count} products from '{$supplier['name']}' to '{$dest_supplier['name']}' and ";
                }
                
                // Delete the supplier
                $stmt = $db->prepare("DELETE FROM suppliers WHERE id = :id");
                $stmt->execute([':id' => $supplier_id]);
                
                $success_message .= "deleted supplier '{$supplier['name']}'.";
                $db->commit();
            } else {
                $error_message = "Supplier not found.";
            }
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollback();
            }
            $error_message = "Error deleting supplier: " . $e->getMessage();
        }
    }
    
    // Location management actions
    if ($_POST['action'] === 'add_location') {
        $name = trim($_POST['location_name']);
        $code = strtoupper(trim($_POST['location_code']));
        $type = $_POST['location_type'];
        $address = trim($_POST['location_address']);
        $phone = trim($_POST['location_phone']);
        $email = trim($_POST['location_email']);
        
        if (!empty($name) && !empty($code)) {
            try {
                // Create locations table if it doesn't exist
                $db->exec("CREATE TABLE IF NOT EXISTS locations (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    name VARCHAR(100) NOT NULL,
                    code VARCHAR(20) UNIQUE NOT NULL,
                    type ENUM('warehouse', 'store', 'outlet') DEFAULT 'store',
                    address TEXT NULL,
                    phone VARCHAR(20) NULL,
                    email VARCHAR(100) NULL,
                    status ENUM('active', 'inactive') DEFAULT 'active',
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )");
                
                $stmt = $db->prepare("INSERT INTO locations (name, code, type, address, phone, email) VALUES (:name, :code, :type, :address, :phone, :email)");
                $stmt->execute([
                    ':name' => $name, 
                    ':code' => $code, 
                    ':type' => $type, 
                    ':address' => $address, 
                    ':phone' => $phone, 
                    ':email' => $email
                ]);
                $success_message = "Location '{$name}' added successfully!";
            } catch (Exception $e) {
                $error_message = "Error adding location: " . $e->getMessage();
            }
        } else {
            $error_message = "Location name and code are required.";
        }
    }
    
    if ($_POST['action'] === 'edit_location' && isAdmin()) {
        $location_id = (int)$_POST['location_id'];
        $name = trim($_POST['location_name']);
        $code = strtoupper(trim($_POST['location_code']));
        $type = $_POST['location_type'];
        $address = trim($_POST['location_address']);
        $phone = trim($_POST['location_phone']);
        $email = trim($_POST['location_email']);
        
        if (!empty($name) && !empty($code)) {
            try {
                $stmt = $db->prepare("UPDATE locations SET name = :name, code = :code, type = :type, address = :address, phone = :phone, email = :email WHERE id = :id");
                $stmt->execute([
                    ':name' => $name, 
                    ':code' => $code, 
                    ':type' => $type, 
                    ':address' => $address, 
                    ':phone' => $phone, 
                    ':email' => $email, 
                    ':id' => $location_id
                ]);
                $success_message = "Location updated successfully!";
            } catch (Exception $e) {
                $error_message = "Error updating location: " . $e->getMessage();
            }
        } else {
            $error_message = "Location name and code are required.";
        }
    }
    
    if ($_POST['action'] === 'delete_location' && isAdmin()) {
        $location_id = (int)$_POST['location_id'];
        
        try {
            $db->beginTransaction();
            
            // Get location name for confirmation
            $stmt = $db->prepare("SELECT name FROM locations WHERE id = :id");
            $stmt->execute([':id' => $location_id]);
            $location = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($location) {
                // Check if location has any stock
                $stmt = $db->prepare("SELECT COUNT(*) as count FROM product_locations WHERE location_id = :id");
                $stmt->execute([':id' => $location_id]);
                $stock_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
                
                if ($stock_count > 0) {
                    $error_message = "Cannot delete location '{$location['name']}' - it has stock allocated. Please transfer stock first.";
                } else {
                    // Delete the location
                    $stmt = $db->prepare("DELETE FROM locations WHERE id = :id");
                    $stmt->execute([':id' => $location_id]);
                    
                    $success_message = "Location '{$location['name']}' deleted successfully.";
                    $db->commit();
                }
            } else {
                $error_message = "Location not found.";
            }
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollback();
            }
            $error_message = "Error deleting location: " . $e->getMessage();
        }
    }
}

// Get all categories with product counts
$stmt = $db->query("SELECT c.*, COUNT(p.id) as product_count 
                    FROM categories c 
                    LEFT JOIN products p ON c.id = p.category_id 
                    GROUP BY c.id 
                    ORDER BY c.name");
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all suppliers with product counts
$stmt = $db->query("SELECT s.*, COUNT(p.id) as product_count 
                    FROM suppliers s 
                    LEFT JOIN products p ON s.id = p.supplier_id 
                    GROUP BY s.id 
                    ORDER BY s.name");
$suppliers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all locations
try {
    // Create locations table if it doesn't exist
    $db->exec("CREATE TABLE IF NOT EXISTS locations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        code VARCHAR(20) UNIQUE NOT NULL,
        type ENUM('warehouse', 'store', 'outlet') DEFAULT 'store',
        address TEXT NULL,
        phone VARCHAR(20) NULL,
        email VARCHAR(100) NULL,
        status ENUM('active', 'inactive') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
    $stmt = $db->query("SELECT l.*, 
                        COALESCE(SUM(pl.stock_quantity), 0) as total_stock,
                        COUNT(DISTINCT pl.product_id) as product_count
                        FROM locations l 
                        LEFT JOIN product_locations pl ON l.id = pl.location_id 
                        GROUP BY l.id 
                        ORDER BY l.name");
    $locations = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $locations = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Categories - Inventory System</title>
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
        
        /* Ensure proper column structure */
        .table-responsive {
            overflow-x: auto;
        }
        
        /* Reset any conflicting styles */
        .table .badge {
            display: inline-block;
            white-space: nowrap;
        }
        
        /* Enhanced Action Buttons Styling */
        .action-buttons {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            align-items: center;
        }
        
        .action-buttons .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 12px 24px;
            font-size: 0.875rem;
            font-weight: 600;
            line-height: 1.5;
            border-radius: 25px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            text-decoration: none;
            white-space: nowrap;
            min-height: 44px;
            border: 2px solid transparent;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            position: relative;
            overflow: hidden;
        }
        
        .action-buttons .btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s ease;
        }
        
        .action-buttons .btn:hover::before {
            left: 100%;
        }
        
        .action-buttons .btn i {
            margin-right: 8px;
            font-size: 1.1rem;
            position: relative;
            z-index: 1;
        }
        
        .action-buttons .btn span {
            position: relative;
            z-index: 1;
        }
        
        .action-buttons .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.15);
        }
        
        .action-buttons .btn:active {
            transform: translateY(0);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }
        
        /* Specific button styles */
        .action-buttons .btn-primary {
            color: #fff;
            background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
            border-color: #007bff;
        }
        
        .action-buttons .btn-primary:hover {
            background: linear-gradient(135deg, #0056b3 0%, #004085 100%);
            border-color: #0056b3;
            color: #fff;
        }
        
        .action-buttons .btn-success {
            color: #fff;
            background: linear-gradient(135deg, #28a745 0%, #1e7e34 100%);
            border-color: #28a745;
        }
        
        .action-buttons .btn-success:hover {
            background: linear-gradient(135deg, #1e7e34 0%, #155724 100%);
            border-color: #1e7e34;
            color: #fff;
        }
        
        /* Responsive adjustments */
        @media (max-width: 768px) {
            .action-buttons {
                justify-content: center;
                width: 100%;
                margin-top: 15px;
                gap: 8px;
            }
            
            .action-buttons .btn {
                font-size: 0.8rem;
                padding: 10px 20px;
                min-height: 40px;
                border-radius: 20px;
            }
            
            .action-buttons .btn i {
                margin-right: 6px;
                font-size: 1rem;
            }
        }
        
        @media (max-width: 576px) {
            .action-buttons {
                flex-direction: column;
                gap: 10px;
            }
            
            .action-buttons .btn {
                width: 100%;
                max-width: 280px;
            }
        }
        
        /* Modal button consistency */
        .modal-footer .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 10px 20px;
            font-size: 0.875rem;
            font-weight: 500;
            line-height: 1.5;
            border-radius: 8px;
            transition: all 0.2s ease-in-out;
            text-decoration: none;
            white-space: nowrap;
            min-height: 40px;
            border: 1px solid transparent;
        }
        
        .modal-footer .btn i {
            margin-right: 6px;
        }
        
        .modal-footer .btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
        }
        
        /* Table action buttons */
        .table .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 4px 8px;
            font-size: 0.8rem;
            font-weight: 500;
            line-height: 1.4;
            border-radius: 6px;
            transition: all 0.2s ease-in-out;
            text-decoration: none;
            white-space: nowrap;
            min-height: 32px;
            margin: 0 2px;
        }
        
        .table .btn i {
            margin-right: 4px;
            font-size: 0.9rem;
        }
        
        .table .btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
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
        
        /* Enhanced table styling */
        .table-hover tbody tr:hover {
            background: linear-gradient(135deg, rgba(59, 130, 246, 0.05) 0%, rgba(37, 99, 235, 0.05) 100%) !important;
            transform: translateX(2px);
            transition: all 0.3s ease;
        }
        
        .table thead th {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-bottom: 2px solid #dee2e6;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.875rem;
            letter-spacing: 0.5px;
        }
        
        /* Badge animations */
        .badge {
            transition: all 0.2s ease;
        }
        
        .badge:hover {
            transform: scale(1.05);
        }
        
        /* Button hover effects */
        .btn-outline-primary:hover,
        .btn-outline-warning:hover,
        .btn-danger:hover {
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
            <?php if ($success_message): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bi bi-check-circle"></i> <?php echo $success_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if ($error_message): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle"></i> <?php echo $error_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2"><i class="bi bi-tags"></i> Categories, Suppliers & Locations</h1>
                <div class="d-flex gap-2 align-items-center">
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
                        <i class="bi bi-plus-lg me-2"></i> Add Category
                    </button>
                    <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addSupplierModal">
                        <i class="bi bi-plus-lg me-2"></i> Add Supplier
                    </button>
                    <button class="btn btn-info" data-bs-toggle="modal" data-bs-target="#addLocationModal">
                        <i class="bi bi-plus-lg me-2"></i> Add Location
                    </button>
                </div>
            </div>

            <!-- Navigation Tabs -->
            <ul class="nav nav-tabs mb-4" id="managementTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="categories-tab" data-bs-toggle="tab" data-bs-target="#categories-pane" type="button" role="tab">
                        <i class="bi bi-tags"></i> Categories (<?php echo count($categories); ?>)
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="suppliers-tab" data-bs-toggle="tab" data-bs-target="#suppliers-pane" type="button" role="tab">
                        <i class="bi bi-truck"></i> Suppliers (<?php echo count($suppliers); ?>)
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="locations-tab" data-bs-toggle="tab" data-bs-target="#locations-pane" type="button" role="tab">
                        <i class="bi bi-geo-alt"></i> Locations (<?php echo count($locations); ?>)
                    </button>
                </li>
            </ul>

            <!-- Tab Content -->
            <div class="tab-content" id="managementTabsContent">
                <!-- Categories Tab -->
                <div class="tab-pane fade show active" id="categories-pane" role="tabpanel">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="bi bi-tags"></i> Manage Categories</h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($categories)): ?>
                                <div class="text-center text-muted py-5">
                                    <i class="bi bi-tags" style="font-size: 3rem;"></i>
                                    <p class="mt-2">No categories found. Add your first category!</p>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-striped table-hover" id="categoriesTable">
                                        <thead class="table-dark">
                                            <tr>
                                                <th>ID</th>
                                                <th>Name</th>
                                                <th>Description</th>
                                                <th>Products</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($categories as $category): ?>
                                                <tr>
                                                    <td><?php echo $category['id']; ?></td>
                                                    <td>
                                                        <strong><?php echo htmlspecialchars($category['name']); ?></strong>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($category['description'] ?? ''); ?></td>
                                                    <td>
                                                        <span class="badge bg-primary"><?php echo $category['product_count']; ?> products</span>
                                                    </td>
                                                    <td>
                                                        <?php if (isAdmin()): ?>
                                                            <div class="d-flex gap-2">
                                                                <button class="btn btn-sm btn-primary" 
                                                                        onclick="editCategory(<?php echo $category['id']; ?>, '<?php echo htmlspecialchars($category['name']); ?>', '<?php echo htmlspecialchars($category['description'] ?? ''); ?>')">
                                                                    <i class="bi bi-pencil me-1"></i> Edit
                                                                </button>
                                                                
                                                                <?php if ($category['product_count'] == 0): ?>
                                                                    <form method="POST" style="display: inline;">
                                                                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                                                        <input type="hidden" name="action" value="delete">
                                                                        <input type="hidden" name="category_id" value="<?php echo $category['id']; ?>">
                                                                        <input type="hidden" name="move_to_category" value="0">
                                                                        <button type="submit" class="btn btn-sm btn-danger" 
                                                                                onclick="return confirm('Delete this category? This action cannot be undone.')">
                                                                            <i class="bi bi-trash me-1"></i> Delete
                                                                        </button>
                                                                    </form>
                                                                <?php else: ?>
                                                                    <button class="btn btn-sm btn-outline-danger" 
                                                                            onclick="showDeleteModal(<?php echo $category['id']; ?>, '<?php echo htmlspecialchars($category['name']); ?>', <?php echo $category['product_count']; ?>)">
                                                                        <i class="bi bi-trash me-1"></i> Delete
                                                                    </button>
                                                                <?php endif; ?>
                                                            </div>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Suppliers Tab -->
                <div class="tab-pane fade" id="suppliers-pane" role="tabpanel">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="bi bi-truck"></i> Manage Suppliers</h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($suppliers)): ?>
                                <div class="text-center text-muted py-5">
                                    <i class="bi bi-truck" style="font-size: 3rem;"></i>
                                    <p class="mt-2">No suppliers found. Add your first supplier!</p>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-striped table-hover" id="suppliersTable">
                                        <thead class="table-dark">
                                            <tr>
                                                <th>ID</th>
                                                <th>Name</th>
                                                <th>Contact Person</th>
                                                <th>Email</th>
                                                <th>Phone</th>
                                                <th>Products</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($suppliers as $supplier): ?>
                                                <tr>
                                                    <td><?php echo $supplier['id']; ?></td>
                                                    <td>
                                                        <strong><?php echo htmlspecialchars($supplier['name']); ?></strong>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($supplier['contact_person'] ?? ''); ?></td>
                                                    <td><?php echo htmlspecialchars($supplier['email'] ?? ''); ?></td>
                                                    <td><?php echo htmlspecialchars($supplier['phone'] ?? ''); ?></td>
                                                    <td>
                                                        <span class="badge bg-success"><?php echo $supplier['product_count']; ?> products</span>
                                                    </td>
                                                    <td>
                                                        <?php if (isAdmin()): ?>
                                                            <div class="d-flex gap-2">
                                                                <button class="btn btn-sm btn-primary" 
                                                                        onclick="editSupplier(<?php echo $supplier['id']; ?>, '<?php echo htmlspecialchars($supplier['name']); ?>', '<?php echo htmlspecialchars($supplier['contact_person'] ?? ''); ?>', '<?php echo htmlspecialchars($supplier['email'] ?? ''); ?>', '<?php echo htmlspecialchars($supplier['phone'] ?? ''); ?>', '<?php echo htmlspecialchars($supplier['address'] ?? ''); ?>')">
                                                                    <i class="bi bi-pencil me-1"></i> Edit
                                                                </button>
                                                                
                                                                <?php if ($supplier['product_count'] == 0): ?>
                                                                    <form method="POST" style="display: inline;">
                                                                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                                                        <input type="hidden" name="action" value="delete_supplier">
                                                                        <input type="hidden" name="supplier_id" value="<?php echo $supplier['id']; ?>">
                                                                        <input type="hidden" name="move_to_supplier" value="0">
                                                                        <button type="submit" class="btn btn-sm btn-danger" 
                                                                                onclick="return confirm('Delete this supplier? This action cannot be undone.')">
                                                                            <i class="bi bi-trash me-1"></i> Delete
                                                                        </button>
                                                                    </form>
                                                                <?php else: ?>
                                                                    <button class="btn btn-sm btn-danger" 
                                                                            onclick="showDeleteSupplierModal(<?php echo $supplier['id']; ?>, '<?php echo htmlspecialchars($supplier['name']); ?>', <?php echo $supplier['product_count']; ?>)">
                                                                        <i class="bi bi-trash me-1"></i> Delete
                                                                    </button>
                                                                <?php endif; ?>
                                                            </div>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Locations Tab -->
                <div class="tab-pane fade" id="locations-pane" role="tabpanel">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="bi bi-geo-alt"></i> Manage Locations</h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($locations)): ?>
                                <div class="text-center text-muted py-5">
                                    <i class="bi bi-geo-alt" style="font-size: 3rem;"></i>
                                    <p class="mt-2">No locations found. Add your first location!</p>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-striped table-hover" id="locationsTable">
                                        <thead class="table-dark">
                                            <tr>
                                                <th>ID</th>
                                                <th>Name</th>
                                                <th>Code</th>
                                                <th>Type</th>
                                                <th>Address</th>
                                                <th>Stock</th>
                                                <th>Products</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($locations as $location): ?>
                                                <tr>
                                                    <td><?php echo $location['id']; ?></td>
                                                    <td>
                                                        <strong><?php echo htmlspecialchars($location['name']); ?></strong>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-secondary"><?php echo htmlspecialchars($location['code']); ?></span>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-<?php echo $location['type'] === 'warehouse' ? 'primary' : ($location['type'] === 'store' ? 'success' : 'info'); ?>">
                                                            <?php echo ucfirst($location['type']); ?>
                                                        </span>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($location['address'] ?? 'Not specified'); ?></td>
                                                    <td>
                                                        <span class="badge bg-warning"><?php echo $location['total_stock']; ?> units</span>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-info"><?php echo $location['product_count']; ?> products</span>
                                                    </td>
                                                    <td>
                                                        <?php if (isAdmin()): ?>
                                                            <div class="d-flex gap-2">
                                                                <button class="btn btn-sm btn-primary" 
                                                                        onclick="editLocation(<?php echo $location['id']; ?>, '<?php echo htmlspecialchars($location['name']); ?>', '<?php echo htmlspecialchars($location['code']); ?>', '<?php echo $location['type']; ?>', '<?php echo htmlspecialchars($location['address'] ?? ''); ?>', '<?php echo htmlspecialchars($location['phone'] ?? ''); ?>', '<?php echo htmlspecialchars($location['email'] ?? ''); ?>')">
                                                                    <i class="bi bi-pencil me-1"></i> Edit
                                                                </button>
                                                                
                                                                <?php if ($location['total_stock'] == 0): ?>
                                                                    <form method="POST" style="display: inline;">
                                                                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                                                        <input type="hidden" name="action" value="delete_location">
                                                                        <input type="hidden" name="location_id" value="<?php echo $location['id']; ?>">
                                                                        <button type="submit" class="btn btn-sm btn-danger" 
                                                                                onclick="return confirm('Delete this location? This action cannot be undone.')">
                                                                            <i class="bi bi-trash me-1"></i> Delete
                                                                        </button>
                                                                    </form>
                                                                <?php else: ?>
                                                                    <button class="btn btn-sm btn-warning" disabled title="Cannot delete location with stock">
                                                                        <i class="bi bi-exclamation-triangle me-1"></i> Has Stock
                                                                    </button>
                                                                <?php endif; ?>
                                                            </div>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Add Category Modal -->
    <div class="modal fade" id="addCategoryModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="action" value="add">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="bi bi-plus-circle"></i> Add New Category
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-8">
                                <div class="mb-3">
                                    <label class="form-label">Category Name *</label>
                                    <input type="text" name="category_name" class="form-control" required placeholder="Enter category name">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <?php if (isAdmin()): ?>
                                <div class="form-check mt-4">
                                    <input class="form-check-input" type="checkbox" id="also_add_location_cat" onchange="toggleLocationFields('cat')">
                                    <label class="form-check-label" for="also_add_location_cat">
                                        <small>Also add as location</small>
                                    </label>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea name="description" class="form-control" rows="3" placeholder="Optional description"></textarea>
                        </div>
                        
                        <?php if (isAdmin()): ?>
                        <!-- Location Fields (Hidden by default) -->
                        <div id="location_fields_cat" style="display: none;">
                            <hr>
                            <h6 class="text-muted"><i class="bi bi-geo-alt"></i> Location Details</h6>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Location Code</label>
                                        <input type="text" name="location_code" class="form-control" placeholder="e.g., WH01, ST01" maxlength="20">
                                        <small class="text-muted">Will auto-generate if empty</small>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Location Type</label>
                                        <select name="location_type" class="form-select">
                                            <option value="store">Store</option>
                                            <option value="warehouse">Warehouse</option>
                                            <option value="outlet">Outlet</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Address</label>
                                <textarea name="location_address" class="form-control" rows="2" placeholder="Location address"></textarea>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Phone</label>
                                        <input type="tel" name="location_phone" class="form-control" placeholder="Location phone">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Email</label>
                                        <input type="email" name="location_email" class="form-control" placeholder="location@example.com">
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
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg me-2"></i> Add Category
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Category Modal -->
    <div class="modal fade" id="editCategoryModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="category_id" id="edit_category_id">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="bi bi-pencil"></i> Edit Category
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Category Name *</label>
                            <input type="text" name="category_name" id="edit_category_name" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea name="description" id="edit_description" class="form-control" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer d-flex justify-content-end gap-2">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="bi bi-x-lg me-2"></i> Cancel
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg me-2"></i> Update Category
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Category Modal -->
    <div class="modal fade" id="deleteCategoryModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="category_id" id="delete_category_id">
                    <div class="modal-header">
                        <h5 class="modal-title text-danger">
                            <i class="bi bi-exclamation-triangle"></i> Delete Category
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-warning">
                            <i class="bi bi-exclamation-triangle"></i>
                            <strong>Warning:</strong> This category contains <span id="delete_product_count"></span> products.
                        </div>
                        
                        <p>Category: <strong id="delete_category_name"></strong></p>
                        <p>You must move the products to another category before deletion:</p>
                        
                        <div class="mb-3">
                            <label class="form-label">Move products to:</label>
                            <select name="move_to_category" class="form-select" required>
                                <option value="">Select destination category...</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer d-flex justify-content-end gap-2">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">
                            <i class="bi bi-trash"></i> Move Products & Delete Category
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Add Supplier Modal -->
    <div class="modal fade" id="addSupplierModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="action" value="add_supplier">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="bi bi-plus-circle"></i> Add New Supplier
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Supplier Name *</label>
                                    <input type="text" name="supplier_name" class="form-control" required placeholder="Enter supplier name">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Contact Person</label>
                                    <input type="text" name="contact_person" class="form-control" placeholder="Contact person name">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Email</label>
                                    <input type="email" name="email" class="form-control" placeholder="supplier@example.com">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Phone</label>
                                    <input type="text" name="phone" class="form-control" placeholder="Phone number">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Address</label>
                                    <textarea name="address" class="form-control" rows="4" placeholder="Full address"></textarea>
                                </div>
                                <?php if (isAdmin()): ?>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="also_add_location_sup" onchange="toggleLocationFields('sup')">
                                    <label class="form-check-label" for="also_add_location_sup">
                                        <small>Also add as location</small>
                                    </label>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <?php if (isAdmin()): ?>
                        <!-- Location Fields (Hidden by default) -->
                        <div id="location_fields_sup" style="display: none;">
                            <hr>
                            <h6 class="text-muted"><i class="bi bi-geo-alt"></i> Location Details</h6>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Location Code</label>
                                        <input type="text" name="location_code" class="form-control" placeholder="e.g., SUP01, WH02" maxlength="20">
                                        <small class="text-muted">Will auto-generate if empty</small>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Location Type</label>
                                        <select name="location_type" class="form-select">
                                            <option value="warehouse">Warehouse</option>
                                            <option value="store">Store</option>
                                            <option value="outlet">Outlet</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="modal-footer d-flex justify-content-end gap-2">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">
                            <i class="bi bi-check-lg"></i> Add Supplier
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Supplier Modal -->
    <div class="modal fade" id="editSupplierModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="action" value="edit_supplier">
                    <input type="hidden" name="supplier_id" id="edit_supplier_id">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="bi bi-pencil"></i> Edit Supplier
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Supplier Name *</label>
                                    <input type="text" name="supplier_name" id="edit_supplier_name" class="form-control" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Contact Person</label>
                                    <input type="text" name="contact_person" id="edit_contact_person" class="form-control">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Email</label>
                                    <input type="email" name="email" id="edit_supplier_email" class="form-control">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Phone</label>
                                    <input type="text" name="phone" id="edit_supplier_phone" class="form-control">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Address</label>
                                    <textarea name="address" id="edit_supplier_address" class="form-control" rows="4"></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer d-flex justify-content-end gap-2">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg"></i> Update Supplier
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Supplier Modal -->
    <div class="modal fade" id="deleteSupplierModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="action" value="delete_supplier">
                    <input type="hidden" name="supplier_id" id="delete_supplier_id">
                    <div class="modal-header">
                        <h5 class="modal-title text-danger">
                            <i class="bi bi-exclamation-triangle"></i> Delete Supplier
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-warning">
                            <i class="bi bi-exclamation-triangle"></i>
                            <strong>Warning:</strong> This supplier has <span id="delete_supplier_product_count"></span> products.
                        </div>
                        
                        <p>Supplier: <strong id="delete_supplier_name"></strong></p>
                        <p>You must move the products to another supplier before deletion:</p>
                        
                        <div class="mb-3">
                            <label class="form-label">Move products to:</label>
                            <select name="move_to_supplier" class="form-select" required>
                                <option value="">Select destination supplier...</option>
                                <?php foreach ($suppliers as $sup): ?>
                                    <option value="<?php echo $sup['id']; ?>"><?php echo htmlspecialchars($sup['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer d-flex justify-content-end gap-2">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">
                            <i class="bi bi-trash"></i> Move Products & Delete Supplier
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Location Modal -->
    <div class="modal fade" id="editLocationModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="action" value="edit_location">
                    <input type="hidden" name="location_id" id="edit_location_id">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="bi bi-pencil"></i> Edit Location
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-8">
                                <div class="mb-3">
                                    <label class="form-label">Location Name</label>
                                    <input type="text" name="location_name" id="edit_location_name" class="form-control" required>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Code</label>
                                    <input type="text" name="location_code" id="edit_location_code" class="form-control" required maxlength="20">
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Type</label>
                            <select name="location_type" id="edit_location_type" class="form-select" required>
                                <option value="store">Store</option>
                                <option value="warehouse">Warehouse</option>
                                <option value="outlet">Outlet</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Address</label>
                            <textarea name="location_address" id="edit_location_address" class="form-control" rows="3"></textarea>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Phone</label>
                                    <input type="tel" name="location_phone" id="edit_location_phone" class="form-control">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Email</label>
                                    <input type="email" name="location_email" id="edit_location_email" class="form-control">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer d-flex justify-content-end gap-2">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg"></i> Update Location
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php include 'includes/chatbot.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/chatbot.js?v=<?php echo time(); ?>"></script>
    <script src="js/mobile.js?v=<?php echo time(); ?>"></script>
    
    <script>
        function editCategory(id, name, description) {
            document.getElementById('edit_category_id').value = id;
            document.getElementById('edit_category_name').value = name;
            document.getElementById('edit_description').value = description;
            
            const modal = new bootstrap.Modal(document.getElementById('editCategoryModal'));
            modal.show();
        }
        
        function showDeleteModal(id, name, productCount) {
            document.getElementById('delete_category_id').value = id;
            document.getElementById('delete_category_name').textContent = name;
            document.getElementById('delete_product_count').textContent = productCount;
            
            // Remove the category being deleted from the dropdown
            const select = document.querySelector('#deleteCategoryModal select[name="move_to_category"]');
            const options = select.querySelectorAll('option');
            options.forEach(option => {
                if (option.value == id) {
                    option.style.display = 'none';
                } else {
                    option.style.display = 'block';
                }
            });
            
            const modal = new bootstrap.Modal(document.getElementById('deleteCategoryModal'));
            modal.show();
        }

        function editSupplier(id, name, contactPerson, email, phone, address) {
            document.getElementById('edit_supplier_id').value = id;
            document.getElementById('edit_supplier_name').value = name;
            document.getElementById('edit_contact_person').value = contactPerson;
            document.getElementById('edit_supplier_email').value = email;
            document.getElementById('edit_supplier_phone').value = phone;
            document.getElementById('edit_supplier_address').value = address;
            
            const modal = new bootstrap.Modal(document.getElementById('editSupplierModal'));
            modal.show();
        }
        
        function showDeleteSupplierModal(id, name, productCount) {
            document.getElementById('delete_supplier_id').value = id;
            document.getElementById('delete_supplier_name').textContent = name;
            document.getElementById('delete_supplier_product_count').textContent = productCount;
            
            // Remove the supplier being deleted from the dropdown
            const select = document.querySelector('#deleteSupplierModal select[name="move_to_supplier"]');
            const options = select.querySelectorAll('option');
            options.forEach(option => {
                if (option.value == id) {
                    option.style.display = 'none';
                } else {
                    option.style.display = 'block';
                }
            });
            
            const modal = new bootstrap.Modal(document.getElementById('deleteSupplierModal'));
            modal.show();
        }

        function editLocation(id, name, code, type, address, phone, email) {
            document.getElementById('edit_location_id').value = id;
            document.getElementById('edit_location_name').value = name;
            document.getElementById('edit_location_code').value = code;
            document.getElementById('edit_location_type').value = type;
            document.getElementById('edit_location_address').value = address;
            document.getElementById('edit_location_phone').value = phone;
            document.getElementById('edit_location_email').value = email;
            
            const modal = new bootstrap.Modal(document.getElementById('editLocationModal'));
            modal.show();
        }

        function toggleLocationFields(type) {
            const checkbox = document.getElementById('also_add_location_' + type);
            const fields = document.getElementById('location_fields_' + type);
            
            if (checkbox.checked) {
                fields.style.display = 'block';
            } else {
                fields.style.display = 'none';
            }
        }
    </script>
</body>
</html>