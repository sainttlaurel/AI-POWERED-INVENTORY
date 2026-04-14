<?php
require_once 'config/database.php';
require_once 'config/session.php';

requireLogin();

$database = new Database();
$db = $database->getConnection();

// Check if QR code columns exist — auto-create them if not (fixes missing setup_qr_features.php)
$qr_columns_exist = false;
$setup_message    = '';
try {
    $stmt = $db->query("SHOW COLUMNS FROM products LIKE 'qr_code'");
    $qr_columns_exist = $stmt->rowCount() > 0;
} catch (Exception $e) {
    $qr_columns_exist = false;
}

if (!$qr_columns_exist) {
    // Auto-migrate: add qr_code and qr_data columns silently
    try {
        $db->exec("ALTER TABLE products
            ADD COLUMN IF NOT EXISTS qr_code  VARCHAR(255) NULL DEFAULT NULL,
            ADD COLUMN IF NOT EXISTS qr_data  TEXT         NULL DEFAULT NULL");
        $qr_columns_exist = true;
        $setup_message = 'QR columns were automatically added to the products table.';
    } catch (Exception $e) {
        // Try adding columns one at a time (fallback for older MySQL)
        try { $db->exec("ALTER TABLE products ADD COLUMN qr_code VARCHAR(255) NULL DEFAULT NULL"); } catch (Exception $e2) {}
        try { $db->exec("ALTER TABLE products ADD COLUMN qr_data TEXT NULL DEFAULT NULL"); } catch (Exception $e3) {}
        $qr_columns_exist = true;
        $setup_message = 'QR Code columns initialised successfully.';
    }
}

// Handle QR code actions
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($_POST['action'] === 'generate_bulk_qr') {
        try {
            // Get all products without QR codes or regenerate all
            $regenerate = isset($_POST['regenerate_all']);
            
            if ($regenerate) {
                $stmt = $db->query("SELECT id, product_name, barcode FROM products ORDER BY product_name");
            } else {
                $stmt = $db->query("SELECT id, product_name, barcode FROM products WHERE (qr_code IS NULL OR qr_code = '') ORDER BY product_name");
            }
            
            $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $generated_count = 0;
            
            foreach ($products as $product) {
                $qr_data = json_encode([
                    'type' => 'product',
                    'id' => $product['id'],
                    'name' => $product['product_name'],
                    'barcode' => $product['barcode'],
                    'url' => "http://{$_SERVER['HTTP_HOST']}/INVENTORY/product_detail.php?id={$product['id']}"
                ]);
                
                // Generate QR code filename
                $qr_filename = 'qr_product_' . $product['id'] . '_' . time() . '.png';
                
                // Update product with QR code data
                $update_stmt = $db->prepare("UPDATE products SET qr_code = ?, qr_data = ? WHERE id = ?");
                $update_stmt->execute([$qr_filename, $qr_data, $product['id']]);
                
                $generated_count++;
            }
            
            $success_message = "Generated QR codes for {$generated_count} products successfully!";
            
        } catch (Exception $e) {
            $error_message = "Error generating QR codes: " . $e->getMessage();
        }
    }
}

// Get products with QR codes (only if columns exist)
$products_with_qr = [];
$products_without_qr = 0;
$total_products = 0;

try {
    $products_with_qr = $db->query("
        SELECT p.*, c.name as category_name 
        FROM products p 
        LEFT JOIN categories c ON p.category_id = c.id 
        WHERE p.qr_code IS NOT NULL AND p.qr_code != ''
        ORDER BY p.product_name
    ")->fetchAll(PDO::FETCH_ASSOC);

    // Get products without QR codes
    $products_without_qr = $db->query("
        SELECT COUNT(*) 
        FROM products 
        WHERE qr_code IS NULL OR qr_code = ''
    ")->fetchColumn();

    // Get total products
    $total_products = $db->query("SELECT COUNT(*) FROM products")->fetchColumn();
} catch (Exception $e) {
    $error_message = "Error loading QR code data: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QR Code Management - Inventory System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/qrcode@1.5.3/build/qrcode.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.js"></script>
    <style>
        .qr-stats-card {
            transition: transform 0.2s;
        }
        .qr-stats-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .qr-code-container canvas {
            border: 1px solid #dee2e6;
            border-radius: 4px;
        }
        .table-compact td {
            vertical-align: middle;
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

            <?php if ($setup_message): ?>
                <div class="alert alert-info alert-dismissible fade show" role="alert">
                    <i class="bi bi-info-circle"></i> <strong>Auto-Setup:</strong> <?php echo htmlspecialchars($setup_message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>


            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2"><i class="bi bi-qr-code"></i> QR Code Management</h1>
                <div class="d-flex gap-2 align-items-center">
                    <!-- Tertiary: Utility action -->
                    <button class="btn btn-ghost" onclick="printAllQRCodes()" title="Print All QR Codes">
                        <i class="bi bi-printer"></i>
                    </button>
                    <!-- Secondary: Supporting action -->
                    <button class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#scanQRModal">
                        <i class="bi bi-camera me-2"></i> Scan QR
                    </button>
                    <!-- Primary: Main action -->
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#generateQRModal">
                        <i class="bi bi-plus-lg me-2"></i> Generate QR
                    </button>
                </div>
            </div>

            <!-- QR Code Statistics -->
            <div class="row mb-4">
                <div class="col-md-4">
                    <div class="card qr-stats-card">
                        <div class="card-body text-center">
                            <i class="bi bi-box-seam text-primary fs-1"></i>
                            <h3 class="mt-2 mb-0"><?php echo $total_products; ?></h3>
                            <p class="text-muted mb-0">Total Products</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card qr-stats-card">
                        <div class="card-body text-center">
                            <i class="bi bi-qr-code text-success fs-1"></i>
                            <h3 class="mt-2 mb-0"><?php echo count($products_with_qr); ?></h3>
                            <p class="text-muted mb-0">With QR Codes</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card qr-stats-card">
                        <div class="card-body text-center">
                            <i class="bi bi-exclamation-triangle text-warning fs-1"></i>
                            <h3 class="mt-2 mb-0"><?php echo $products_without_qr; ?></h3>
                            <p class="text-muted mb-0">Without QR Codes</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Products with QR Codes -->
            <div class="card">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="bi bi-qr-code"></i> Products with QR Codes</h5>
                        <span class="badge bg-primary"><?php echo count($products_with_qr); ?> items</span>
                    </div>
                </div>
                <div class="card-body">
                    <?php if (empty($products_with_qr)): ?>
                        <div class="text-center py-5">
                            <i class="bi bi-qr-code display-1 text-muted"></i>
                            <h4 class="text-muted mt-3">No QR Codes Generated Yet</h4>
                            <p class="text-muted">Generate QR codes for your products to enable quick scanning and inventory management.</p>
                            <button class="btn btn-success mt-2" data-bs-toggle="modal" data-bs-target="#generateQRModal">
                                <i class="bi bi-plus-lg me-2"></i> Generate QR Codes Now
                            </button>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover table-compact">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width: 100px;">QR Code</th>
                                        <th>Product</th>
                                        <th>Category</th>
                                        <th style="width: 120px;">Stock</th>
                                        <th style="width: 280px;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($products_with_qr as $product): ?>
                                        <tr>
                                            <td>
                                                <div class="qr-code-container" data-product-id="<?php echo $product['id']; ?>">
                                                    <canvas id="qr-<?php echo $product['id']; ?>" width="80" height="80"></canvas>
                                                </div>
                                            </td>
                                            <td>
                                                <strong><?php echo htmlspecialchars($product['product_name']); ?></strong>
                                                <?php if ($product['barcode']): ?>
                                                    <br><small class="text-muted"><i class="bi bi-upc-scan"></i> <?php echo htmlspecialchars($product['barcode']); ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($product['category_name'] ?? 'Uncategorized'); ?></td>
                                            <td>
                                                <span class="badge <?php echo $product['stock_quantity'] <= $product['reorder_level'] ? 'bg-warning' : 'bg-success'; ?>">
                                                    <?php echo $product['stock_quantity']; ?> units
                                                </span>
                                            </td>
                                            <td>
                                                <div class="action-buttons">
                                                    <button class="btn btn-icon" onclick="viewProduct(<?php echo $product['id']; ?>)" title="View Product">
                                                        <i class="bi bi-eye"></i>
                                                    </button>
                                                    <button class="btn btn-icon" onclick="downloadQR(<?php echo $product['id']; ?>)" title="Download QR">
                                                        <i class="bi bi-download"></i>
                                                    </button>
                                                    <button class="btn btn-icon" onclick="printQR(<?php echo $product['id']; ?>)" title="Print QR">
                                                        <i class="bi bi-printer"></i>
                                                    </button>
                                                </div>
                                            </td>
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

    <!-- Generate QR Codes Modal -->
    <div class="modal fade" id="generateQRModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-qr-code"></i> Generate QR Codes</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="generate_bulk_qr">
                        
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" name="regenerate_all" id="regenerateAll">
                            <label class="form-check-label" for="regenerateAll">
                                Regenerate all QR codes (including existing ones)
                            </label>
                        </div>
                        
                        <?php if ($products_without_qr > 0): ?>
                            <div class="alert alert-info mb-0">
                                <i class="bi bi-info-circle"></i>
                                <strong><?php echo $products_without_qr; ?></strong> product<?php echo $products_without_qr > 1 ? 's' : ''; ?> without QR codes will be generated.
                            </div>
                        <?php else: ?>
                            <div class="alert alert-success mb-0">
                                <i class="bi bi-check-circle"></i>
                                All products already have QR codes. Check the box above to regenerate.
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="bi bi-x-lg"></i> Cancel
                        </button>
                        <button type="submit" class="btn btn-success">
                            <i class="bi bi-qr-code"></i> Generate
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Scan QR Code Modal -->
    <div class="modal fade" id="scanQRModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-camera"></i> Scan QR Code</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6 class="mb-3">Camera Scanner</h6>
                            <video id="qrVideo" width="100%" height="250" style="border: 2px solid #dee2e6; border-radius: 8px; background: #f8f9fa;"></video>
                            <div class="d-grid gap-2 mt-3">
                                <button id="startScan" class="btn btn-primary">
                                    <i class="bi bi-camera"></i> Start Camera
                                </button>
                                <button id="stopScan" class="btn btn-secondary" style="display: none;">
                                    <i class="bi bi-stop"></i> Stop Camera
                                </button>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <h6 class="mb-3">Upload QR Image</h6>
                            <input type="file" id="qrFileInput" class="form-control mb-3" accept="image/*">
                            <canvas id="qrCanvas" style="display: none;"></canvas>
                            
                            <h6 class="mb-3 mt-4">Manual Search</h6>
                            <input type="text" id="productSearch" class="form-control" placeholder="Search product name or barcode...">
                            <div id="searchResults" class="mt-2" style="max-height: 150px; overflow-y: auto;"></div>
                        </div>
                    </div>
                    
                    <div id="qrScanResult" class="mt-3" style="display: none;">
                        <div class="alert alert-success mb-0">
                            <h6 class="mb-2"><i class="bi bi-check-circle"></i> QR Code Detected!</h6>
                            <div id="qrResultContent"></div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-lg"></i> Close
                    </button>
                </div>
            </div>
        </div>
    </div>

    <?php include 'includes/chatbot.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/chatbot.js?v=<?php echo time(); ?>"></script>
    <script src="js/mobile.js?v=<?php echo time(); ?>"></script>
    <script src="js/qr_codes.js?v=<?php echo time(); ?>"></script>
</body>
</html>