<?php
require_once 'config/database.php';
require_once 'config/session.php';

requireLogin();

$database = new Database();
$db = $database->getConnection();

// Check if QR code columns exist
$qr_columns_exist = false;
try {
    $stmt = $db->query("SHOW COLUMNS FROM products LIKE 'qr_code'");
    $qr_columns_exist = $stmt->rowCount() > 0;
} catch (Exception $e) {
    // Columns don't exist
}

// If QR columns don't exist, redirect to setup
if (!$qr_columns_exist) {
    header("Location: setup_qr_features.php");
    exit();
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
</head>
<body>
    <?php include 'includes/navbar.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include 'includes/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
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
                    <h1 class="h2"><i class="bi bi-qr-code"></i> QR Code Management</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#scanQRModal">
                                <i class="bi bi-camera me-2"></i> Scan QR Code
                            </button>
                            <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#generateQRModal">
                                <i class="bi bi-plus-lg me-2"></i> Generate QR Codes
                            </button>
                            <button class="btn btn-info" onclick="printAllQRCodes()">
                                <i class="bi bi-printer me-2"></i> Print All QR Codes
                            </button>
                        </div>
                    </div>
                </div>

                <!-- QR Code Statistics -->
                <div class="row mb-4 qr-stats-compact">
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-body text-center">
                                <h5 class="card-title">Total Products</h5>
                                <h2 class="text-primary"><?php echo $total_products; ?></h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-body text-center">
                                <h5 class="card-title">With QR Codes</h5>
                                <h2 class="text-success"><?php echo count($products_with_qr); ?></h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-body text-center">
                                <h5 class="card-title">Without QR Codes</h5>
                                <h2 class="text-warning"><?php echo $products_without_qr; ?></h2>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- QR Code Scanner Result -->
                <div id="scanResult" class="alert alert-info" style="display: none;">
                    <h5><i class="bi bi-qr-code-scan"></i> Scan Result</h5>
                    <div id="scanResultContent"></div>
                </div>

                <!-- Products with QR Codes -->
                <div class="card">
                    <div class="card-header">
                        <h5><i class="bi bi-qr-code"></i> Products with QR Codes</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($products_with_qr)): ?>
                            <div class="text-center py-4">
                                <i class="bi bi-qr-code display-1 text-muted"></i>
                                <h4 class="text-muted">No QR Codes Generated Yet</h4>
                                <p class="text-muted">Generate QR codes for your products to enable quick scanning and inventory management.</p>
                                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#generateQRModal">
                                    <i class="bi bi-plus-lg me-2"></i> Generate QR Codes
                                </button>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover table-compact">
                                    <thead>
                                        <tr>
                                            <th>QR Code</th>
                                            <th>Product</th>
                                            <th>Category</th>
                                            <th>Stock</th>
                                            <th>Actions</th>
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
                                                        <br><small class="text-muted">Barcode: <?php echo htmlspecialchars($product['barcode']); ?></small>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo htmlspecialchars($product['category_name'] ?? 'Uncategorized'); ?></td>
                                                <td>
                                                    <span class="badge <?php echo $product['stock_quantity'] <= $product['reorder_level'] ? 'bg-warning' : 'bg-success'; ?>">
                                                        <?php echo $product['stock_quantity']; ?> units
                                                    </span>
                                                </td>
                                                <td>
                                                    <div class="btn-group btn-group-sm">
                                                        <button class="btn btn-primary" onclick="viewProduct(<?php echo $product['id']; ?>)">
                                                            <i class="bi bi-eye me-1"></i> View
                                                        </button>
                                                        <button class="btn btn-success" onclick="downloadQR(<?php echo $product['id']; ?>)">
                                                            <i class="bi bi-download me-1"></i> Download
                                                        </button>
                                                        <button class="btn btn-info" onclick="printQR(<?php echo $product['id']; ?>)">
                                                            <i class="bi bi-printer me-1"></i> Print
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
                        
                        <div class="mb-3">
                            <h6>QR Code Generation Options</h6>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="regenerate_all" id="regenerateAll">
                                <label class="form-check-label" for="regenerateAll">
                                    Regenerate QR codes for all products (including existing ones)
                                </label>
                            </div>
                        </div>
                        
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle"></i>
                            <strong>What will be included in QR codes:</strong>
                            <ul class="mb-0 mt-2">
                                <li>Product ID and Name</li>
                                <li>Barcode (if available)</li>
                                <li>Direct link to product details</li>
                                <li>Inventory management data</li>
                            </ul>
                        </div>
                        
                        <?php if ($products_without_qr > 0): ?>
                            <div class="alert alert-warning">
                                <i class="bi bi-exclamation-triangle"></i>
                                <strong><?php echo $products_without_qr; ?></strong> products don't have QR codes yet.
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="bi bi-x-lg me-2"></i> Cancel
                        </button>
                        <button type="submit" class="btn btn-success">
                            <i class="bi bi-qr-code me-2"></i> Generate QR Codes
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
                            <h6>Camera Scanner</h6>
                            <video id="qrVideo" width="100%" height="300" style="border: 1px solid #ddd; border-radius: 8px;"></video>
                            <div class="mt-2">
                                <button id="startScan" class="btn btn-primary">
                                    <i class="bi bi-camera me-2"></i> Start Camera
                                </button>
                                <button id="stopScan" class="btn btn-secondary" style="display: none;">
                                    <i class="bi bi-stop me-2"></i> Stop Camera
                                </button>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <h6>Upload QR Code Image</h6>
                            <input type="file" id="qrFileInput" class="form-control" accept="image/*">
                            <canvas id="qrCanvas" style="display: none;"></canvas>
                            
                            <div class="mt-3">
                                <h6>Manual Product Search</h6>
                                <input type="text" id="productSearch" class="form-control" placeholder="Search by product name or barcode...">
                                <div id="searchResults" class="mt-2"></div>
                            </div>
                        </div>
                    </div>
                    
                    <div id="qrScanResult" class="mt-3" style="display: none;">
                        <div class="alert alert-success">
                            <h6><i class="bi bi-check-circle"></i> QR Code Detected!</h6>
                            <div id="qrResultContent"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/main.js"></script>
    <script src="js/qr_codes.js"></script>
</body>
</html>