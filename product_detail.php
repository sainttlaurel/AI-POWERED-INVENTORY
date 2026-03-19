<?php
require_once 'config/database.php';
require_once 'config/session.php';

requireLogin();

$product_id = (int)($_GET['id'] ?? 0);

if ($product_id <= 0) {
    header("Location: products.php?error=" . urlencode("Invalid product ID"));
    exit();
}

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

// Get product details
$stmt = $db->prepare("
    SELECT p.*, c.name as category_name, s.name as supplier_name
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    LEFT JOIN suppliers s ON p.supplier_id = s.id
    WHERE p.id = ?
");
$stmt->execute([$product_id]);
$product = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$product) {
    header("Location: products.php?error=" . urlencode("Product not found"));
    exit();
}

// Log QR scan if accessed via QR code and QR tables exist
if (isset($_GET['qr_scan']) && $qr_columns_exist) {
    try {
        // Check if qr_scans table exists
        $stmt = $db->query("SHOW TABLES LIKE 'qr_scans'");
        $qr_scans_exists = $stmt->rowCount() > 0;
        
        if ($qr_scans_exists) {
            $stmt = $db->prepare("
                INSERT INTO qr_scans (product_id, user_id, scan_type, ip_address, user_agent)
                VALUES (?, ?, 'manual', ?, ?)
            ");
            $stmt->execute([
                $product_id,
                $_SESSION['user_id'],
                $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
            ]);
        }
    } catch (Exception $e) {
        // Silently fail - don't break the page
        error_log("QR scan logging error: " . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($product['product_name']); ?> - Product Details</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <?php include 'includes/navbar.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include 'includes/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">
                        <i class="bi bi-box-seam"></i> Product Details
                        <?php if (isset($_GET['qr_scan'])): ?>
                            <span class="badge bg-success ms-2">
                                <i class="bi bi-qr-code"></i> Scanned via QR
                            </span>
                        <?php endif; ?>
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <button class="btn btn-secondary" onclick="history.back()">
                                <i class="bi bi-arrow-left"></i> Back
                            </button>
                            <button class="btn btn-primary" onclick="window.location.href='products.php?highlight=<?php echo $product['id']; ?>'">
                                <i class="bi bi-list"></i> View in Products
                            </button>
                            <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#quickStockModal">
                                <i class="bi bi-plus-minus"></i> Update Stock
                            </button>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <!-- Product Image and QR Code -->
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header">
                                <h5><i class="bi bi-image"></i> Product Image</h5>
                            </div>
                            <div class="card-body text-center">
                                <?php if ($product['image']): ?>
                                    <img src="uploads/<?php echo htmlspecialchars($product['image']); ?>" 
                                         class="img-fluid rounded mb-3" 
                                         alt="<?php echo htmlspecialchars($product['product_name']); ?>"
                                         style="max-height: 300px;">
                                <?php else: ?>
                                    <div class="bg-light rounded d-flex align-items-center justify-content-center mb-3" style="height: 200px;">
                                        <i class="bi bi-image text-muted" style="font-size: 3rem;"></i>
                                    </div>
                                <?php endif; ?>
                                
                                <!-- QR Code -->
                                <div class="mt-3">
                                    <h6><i class="bi bi-qr-code"></i> QR Code</h6>
                                    <?php if ($qr_columns_exist): ?>
                                        <div class="d-flex justify-content-center">
                                            <canvas id="productQR" width="150" height="150"></canvas>
                                        </div>
                                        <div class="mt-2">
                                            <button class="btn btn-sm btn-primary" onclick="downloadQR()">
                                                <i class="bi bi-download"></i> Download
                                            </button>
                                            <button class="btn btn-sm btn-info" onclick="printQR()">
                                                <i class="bi bi-printer"></i> Print
                                            </button>
                                        </div>
                                    <?php else: ?>
                                        <div class="alert alert-info">
                                            <i class="bi bi-info-circle"></i>
                                            QR code features not set up yet.
                                            <a href="setup_qr_features.php" class="btn btn-sm btn-primary ms-2">
                                                <i class="bi bi-gear"></i> Setup QR Features
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Product Information -->
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-header">
                                <h5><i class="bi bi-info-circle"></i> Product Information</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <table class="table table-borderless">
                                            <tr>
                                                <td><strong>Product Name:</strong></td>
                                                <td><?php echo htmlspecialchars($product['product_name']); ?></td>
                                            </tr>
                                            <tr>
                                                <td><strong>Category:</strong></td>
                                                <td><?php echo htmlspecialchars($product['category_name'] ?? 'Uncategorized'); ?></td>
                                            </tr>
                                            <tr>
                                                <td><strong>Supplier:</strong></td>
                                                <td><?php echo htmlspecialchars($product['supplier_name'] ?? 'No Supplier'); ?></td>
                                            </tr>
                                            <tr>
                                                <td><strong>Barcode:</strong></td>
                                                <td><?php echo htmlspecialchars($product['barcode'] ?: 'N/A'); ?></td>
                                            </tr>
                                            <tr>
                                                <td><strong>Date Added:</strong></td>
                                                <td><?php echo date('M d, Y', strtotime($product['date_added'])); ?></td>
                                            </tr>
                                        </table>
                                    </div>
                                    <div class="col-md-6">
                                        <table class="table table-borderless">
                                            <tr>
                                                <td><strong>Selling Price:</strong></td>
                                                <td class="text-success">₱<?php echo number_format($product['price'], 2); ?></td>
                                            </tr>
                                            <tr>
                                                <td><strong>Cost Price:</strong></td>
                                                <td>₱<?php echo number_format($product['cost_price'], 2); ?></td>
                                            </tr>
                                            <tr>
                                                <td><strong>Current Stock:</strong></td>
                                                <td>
                                                    <span class="badge <?php echo $product['stock_quantity'] <= $product['reorder_level'] ? 'bg-warning' : 'bg-success'; ?>">
                                                        <?php echo $product['stock_quantity']; ?> units
                                                    </span>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td><strong>Reorder Level:</strong></td>
                                                <td><?php echo $product['reorder_level']; ?> units</td>
                                            </tr>
                                            <tr>
                                                <td><strong>Profit Margin:</strong></td>
                                                <td class="text-info">
                                                    <?php 
                                                    $margin = (($product['price'] - $product['cost_price']) / $product['price']) * 100;
                                                    echo number_format($margin, 1) . '%';
                                                    ?>
                                                </td>
                                            </tr>
                                        </table>
                                    </div>
                                </div>
                                
                                <?php if ($product['stock_quantity'] <= $product['reorder_level']): ?>
                                    <div class="alert alert-warning">
                                        <i class="bi bi-exclamation-triangle"></i>
                                        <strong>Low Stock Alert!</strong> 
                                        This product is at or below the reorder level. Consider restocking soon.
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Quick Stock Update Modal -->
    <div class="modal fade" id="quickStockModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-plus-minus"></i> Quick Stock Update</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="quickStockForm">
                        <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                        <div class="mb-3">
                            <label class="form-label">Current Stock</label>
                            <input type="text" class="form-control" value="<?php echo $product['stock_quantity']; ?> units" readonly>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Action</label>
                            <select name="action" class="form-select" required>
                                <option value="">Select action...</option>
                                <option value="stock_in">Stock In (+)</option>
                                <option value="stock_out">Stock Out (-)</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Quantity</label>
                            <input type="number" name="quantity" class="form-control" min="1" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Notes (Optional)</label>
                            <textarea name="notes" class="form-control" rows="2" placeholder="Reason for stock update..."></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="submitQuickStock()">Update Stock</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/qrcode@1.5.3/build/qrcode.min.js"></script>
    
    <script>
        // Generate QR code for this product (only if QR features are enabled)
        document.addEventListener('DOMContentLoaded', function() {
            <?php if ($qr_columns_exist): ?>
            const qrData = {
                type: 'product',
                id: <?php echo $product['id']; ?>,
                name: <?php echo json_encode($product['product_name']); ?>,
                barcode: <?php echo json_encode($product['barcode']); ?>,
                url: `${window.location.origin}/INVENTORY/product_detail.php?id=<?php echo $product['id']; ?>&qr_scan=1`,
                timestamp: Date.now()
            };
            
            const canvas = document.getElementById('productQR');
            if (canvas) {
                QRCode.toCanvas(canvas, JSON.stringify(qrData), {
                    width: 150,
                    height: 150,
                    margin: 2,
                    color: {
                        dark: '#1c1c1e',
                        light: '#ffffff'
                    }
                }, (error) => {
                    if (error) {
                        console.error('QR Code generation error:', error);
                    }
                });
            }
            <?php endif; ?>
        });
        
        function downloadQR() {
            <?php if ($qr_columns_exist): ?>
            const canvas = document.getElementById('productQR');
            if (canvas) {
                const link = document.createElement('a');
                link.download = `qr-code-<?php echo $product['id']; ?>-<?php echo preg_replace('/[^a-zA-Z0-9]/', '-', $product['product_name']); ?>.png`;
                link.href = canvas.toDataURL();
                link.click();
            }
            <?php else: ?>
            alert('QR code features not set up yet. Please run setup first.');
            <?php endif; ?>
        }
        
        function printQR() {
            <?php if ($qr_columns_exist): ?>
            const canvas = document.getElementById('productQR');
            if (canvas) {
                const printWindow = window.open('', '_blank');
                printWindow.document.write(`
                    <html>
                        <head>
                            <title>QR Code - <?php echo htmlspecialchars($product['product_name']); ?></title>
                            <style>
                                body { text-align: center; font-family: Arial, sans-serif; padding: 20px; }
                                .qr-container { margin: 20px auto; max-width: 300px; }
                                .qr-code { border: 1px solid #ddd; margin: 20px 0; }
                                h2 { color: #333; }
                                .details { text-align: left; margin-top: 20px; }
                            </style>
                        </head>
                        <body>
                            <div class="qr-container">
                                <h2><?php echo htmlspecialchars($product['product_name']); ?></h2>
                                <img src="${canvas.toDataURL()}" class="qr-code" alt="QR Code">
                                <div class="details">
                                    <p><strong>Product ID:</strong> <?php echo $product['id']; ?></p>
                                    <p><strong>Barcode:</strong> <?php echo htmlspecialchars($product['barcode'] ?: 'N/A'); ?></p>
                                    <p><strong>Stock:</strong> <?php echo $product['stock_quantity']; ?> units</p>
                                    <p><strong>Price:</strong> ₱<?php echo number_format($product['price'], 2); ?></p>
                                </div>
                            </div>
                        </body>
                    </html>
                `);
                printWindow.document.close();
                printWindow.print();
            }
            <?php else: ?>
            alert('QR code features not set up yet. Please run setup first.');
            <?php endif; ?>
        }
        
        function submitQuickStock() {
            const form = document.getElementById('quickStockForm');
            const formData = new FormData(form);
            
            fetch('api/quick_stock_update.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Close modal
                    const modal = bootstrap.Modal.getInstance(document.getElementById('quickStockModal'));
                    modal.hide();
                    
                    // Show success message and reload
                    alert('Stock updated successfully!');
                    location.reload();
                } else {
                    alert(data.message || 'Error updating stock');
                }
            })
            .catch(error => {
                console.error('Stock update error:', error);
                alert('Error updating stock');
            });
        }
    </script>
</body>
</html>