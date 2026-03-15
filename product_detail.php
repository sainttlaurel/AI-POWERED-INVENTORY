<?php
require_once 'config/database.php';
require_once 'config/session.php';
requireLogin();

$database = new Database();
$db = $database->getConnection();

$product_id = $_GET['id'] ?? 0;
$success_message = '';
$error_message = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        die('Invalid CSRF token');
    }
    
    if ($_POST['action'] === 'adjust_stock') {
        $adjustment = (int)$_POST['adjustment'];
        $adjustment_type = $_POST['adjustment_type'];
        $notes = $_POST['notes'] ?? '';
        
        try {
            $db->beginTransaction();
            
            if ($adjustment_type === 'add') {
                $stmt = $db->prepare("UPDATE products SET stock_quantity = stock_quantity + ? WHERE id = ?");
                $stmt->execute([$adjustment, $product_id]);
                $action_log = 'stock_in';
            } else {
                $stmt = $db->prepare("UPDATE products SET stock_quantity = stock_quantity - ? WHERE id = ?");
                $stmt->execute([$adjustment, $product_id]);
                $action_log = 'stock_out';
            }
            
            // Log the adjustment
            $stmt = $db->prepare("INSERT INTO inventory_logs (product_id, action, quantity, user_id, notes) 
                                  VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$product_id, $action_log, $adjustment, $_SESSION['user_id'], $notes]);
            
            $db->commit();
            $success_message = "Stock adjusted successfully!";
        } catch (Exception $e) {
            $db->rollback();
            $error_message = "Error adjusting stock: " . $e->getMessage();
        }
    }
    
    if ($_POST['action'] === 'edit_product') {
        $image = $_POST['current_image'];
        
        // Handle image upload
        if (isset($_FILES['image']) && $_FILES['image']['error'] === 0) {
            $target_dir = "uploads/";
            if (!file_exists($target_dir)) {
                mkdir($target_dir, 0777, true);
            }
            
            // Validate file type
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            $file_type = $_FILES['image']['type'];
            
            if (in_array($file_type, $allowed_types)) {
                $image = time() . '_' . basename($_FILES["image"]["name"]);
                if (!move_uploaded_file($_FILES["image"]["tmp_name"], "{$target_dir}{$image}")) {
                    $error_message = "Failed to upload image.";
                    $image = $_POST['current_image']; // Keep current image on failure
                }
            } else {
                $error_message = "Invalid file type. Please upload JPG, PNG, GIF, or WebP images only.";
                $image = $_POST['current_image']; // Keep current image on failure
            }
        }
        
        if (empty($error_message)) {
            try {
                $query = "UPDATE products SET 
                          product_name = ?, 
                          category_id = ?, 
                          supplier_id = ?, 
                          price = ?, 
                          reorder_level = ?, 
                          barcode = ?, 
                          image = ? 
                          WHERE id = ?";
                
                $stmt = $db->prepare($query);
                $stmt->execute([
                    $_POST['product_name'],
                    $_POST['category_id'],
                    $_POST['supplier_id'],
                    $_POST['price'],
                    $_POST['reorder_level'],
                    $_POST['barcode'],
                    $image,
                    $product_id
                ]);
                
                $success_message = "Product updated successfully!";
            } catch (Exception $e) {
                $error_message = "Error updating product: " . $e->getMessage();
            }
        }
    }
}

$stmt = $db->prepare("SELECT p.*, c.name as category_name, s.name as supplier_name FROM products p LEFT JOIN categories c ON p.category_id = c.id LEFT JOIN suppliers s ON p.supplier_id = s.id WHERE p.id = :id");
$stmt->execute([':id' => $product_id]);
$product = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$product) {
    header("Location: products.php");
    exit();
}

$stmt = $db->prepare("SELECT * FROM forecast_data WHERE product_id = :id");
$stmt->execute([':id' => $product_id]);
$forecast = $stmt->fetch(PDO::FETCH_ASSOC);

$stmt = $db->prepare("SELECT * FROM sales WHERE product_id = :id ORDER BY created_at DESC LIMIT 10");
$stmt->execute([':id' => $product_id]);
$sales = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get recent stock adjustments
$stmt = $db->prepare("SELECT il.*, u.username FROM inventory_logs il 
                      LEFT JOIN users u ON il.user_id = u.id 
                      WHERE il.product_id = :id 
                      ORDER BY il.created_at DESC LIMIT 10");
$stmt->execute([':id' => $product_id]);
$stock_logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get categories and suppliers for edit form
$categories = $db->query("SELECT * FROM categories")->fetchAll(PDO::FETCH_ASSOC);
$suppliers = $db->query("SELECT * FROM suppliers")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($product['product_name']); ?> - Details</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <?php include 'includes/navbar.php'; ?>
    
    <div class="container-fluid">
        <div class="sidebar-overlay"></div>
        <?php include 'includes/sidebar.php'; ?>
        
        <main>
                <?php if ($success_message): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo $success_message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <?php if ($error_message): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo $error_message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2"><?php echo htmlspecialchars($product['product_name']); ?></h1>
                    <div>
                        <button class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#adjustStockModal">
                            <i class="bi bi-plus-minus"></i> Adjust Stock
                        </button>
                        <?php if (isAdmin()): ?>
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#editProductModal">
                                <i class="bi bi-pencil"></i> Edit Product
                            </button>
                        <?php endif; ?>
                        <a href="products.php" class="btn btn-secondary">
                            <i class="bi bi-arrow-left"></i> Back to Products
                        </a>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-body text-center">
                                <?php if ($product['image']): ?>
                                    <img src="uploads/<?php echo htmlspecialchars($product['image']); ?>" class="img-fluid" alt="Product">
                                <?php else: ?>
                                    <div class="bg-secondary" style="height:300px;"></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-8">
                        <div class="card mb-3">
                            <div class="card-header">Product Information</div>
                            <div class="card-body">
                                <table class="table">
                                    <tr><th>Category:</th><td><?php echo htmlspecialchars($product['category_name']); ?></td></tr>
                                    <tr><th>Supplier:</th><td><?php echo htmlspecialchars($product['supplier_name']); ?></td></tr>
                                    <tr><th>Cost Price:</th><td>₱<?php echo number_format($product['cost_price'] ?? 0, 2); ?></td></tr>
                                    <tr><th>Selling Price:</th><td>₱<?php echo number_format($product['price'], 2); ?></td></tr>
                                    <tr>
                                        <th>Profit per Unit:</th>
                                        <td>
                                            <?php 
                                            $cost_price = $product['cost_price'] ?? 0;
                                            $selling_price = $product['price'];
                                            $profit_per_unit = $selling_price - $cost_price;
                                            $profit_color = $profit_per_unit > 0 ? 'text-success' : ($profit_per_unit < 0 ? 'text-danger' : 'text-muted');
                                            ?>
                                            <span class="<?php echo $profit_color; ?> fw-bold fs-5">
                                                ₱<?php echo number_format($profit_per_unit, 2); ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>Profit Margin:</th>
                                        <td>
                                            <?php 
                                            $profit_percentage = $cost_price > 0 ? (($profit_per_unit / $cost_price) * 100) : 0;
                                            $percentage_color = $profit_percentage > 20 ? 'success' : ($profit_percentage > 10 ? 'warning' : 'danger');
                                            ?>
                                            <span class="badge bg-<?php echo $percentage_color; ?> fs-6">
                                                <?php echo number_format($profit_percentage, 1); ?>%
                                            </span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>Stock:</th>
                                        <td>
                                            <span class="badge <?php echo $product['stock_quantity'] <= $product['reorder_level'] ? 'bg-danger' : 'bg-success'; ?> fs-6">
                                                <?php echo $product['stock_quantity']; ?> units
                                            </span>
                                        </td>
                                    </tr>
                                    <tr><th>Reorder Level:</th><td><?php echo $product['reorder_level']; ?></td></tr>
                                    <tr><th>Barcode:</th><td><?php echo htmlspecialchars($product['barcode']); ?></td></tr>
                                    <tr><th>Date Added:</th><td><?php echo date('M d, Y', strtotime($product['date_added'])); ?></td></tr>
                                </table>
                            </div>
                        </div>

                        <?php if ($forecast): ?>
                        <div class="card mb-3">
                            <div class="card-header bg-info text-white">AI Forecast</div>
                            <div class="card-body">
                                <p><strong>Average Daily Sales:</strong> <?php echo number_format($forecast['avg_daily_sales'], 2); ?> units</p>
                                <p><strong>Weekly Forecast:</strong> <?php echo number_format($forecast['forecast_weekly'], 0); ?> units</p>
                                <p><strong>Monthly Forecast:</strong> <?php echo number_format($forecast['forecast_monthly'], 0); ?> units</p>
                                <p><strong>Predicted Depletion:</strong> <?php echo $forecast['predicted_depletion_days']; ?> days</p>
                                <p><strong>Reorder Suggestion:</strong> <?php echo $forecast['reorder_suggestion']; ?> units</p>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Profit Analysis Section -->
                        <div class="card mb-3">
                            <div class="card-header bg-success text-white">
                                <i class="bi bi-graph-up"></i> Profit Analysis
                            </div>
                            <div class="card-body">
                                <?php 
                                $cost_price = $product['cost_price'] ?? 0;
                                $selling_price = $product['price'];
                                $current_stock = $product['stock_quantity'];
                                $profit_per_unit = $selling_price - $cost_price;
                                $total_investment = $cost_price * $current_stock;
                                $potential_revenue = $selling_price * $current_stock;
                                $potential_profit = $profit_per_unit * $current_stock;
                                ?>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <h6 class="text-muted">Current Stock Analysis</h6>
                                        <p><strong>Total Investment:</strong> ₱<?php echo number_format($total_investment, 2); ?></p>
                                        <p><strong>Potential Revenue:</strong> ₱<?php echo number_format($potential_revenue, 2); ?></p>
                                        <p><strong>Potential Profit:</strong> 
                                            <span class="<?php echo $potential_profit > 0 ? 'text-success' : 'text-danger'; ?> fw-bold">
                                                ₱<?php echo number_format($potential_profit, 2); ?>
                                            </span>
                                        </p>
                                    </div>
                                    <div class="col-md-6">
                                        <h6 class="text-muted">Performance Indicators</h6>
                                        <?php if ($profit_percentage > 30): ?>
                                            <div class="alert alert-success py-2">
                                                <i class="bi bi-check-circle"></i> Excellent profit margin (<?php echo number_format($profit_percentage, 1); ?>%)
                                            </div>
                                        <?php elseif ($profit_percentage > 15): ?>
                                            <div class="alert alert-warning py-2">
                                                <i class="bi bi-exclamation-triangle"></i> Good profit margin (<?php echo number_format($profit_percentage, 1); ?>%)
                                            </div>
                                        <?php else: ?>
                                            <div class="alert alert-danger py-2">
                                                <i class="bi bi-x-circle"></i> Low profit margin (<?php echo number_format($profit_percentage, 1); ?>%)
                                            </div>
                                        <?php endif; ?>
                                        
                                        <?php if ($cost_price > 0): ?>
                                            <p><small class="text-muted">
                                                Break-even price: ₱<?php echo number_format($cost_price, 2); ?><br>
                                                Recommended minimum price: ₱<?php echo number_format($cost_price * 1.2, 2); ?> (20% margin)
                                            </small></p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row mt-3">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">Sales History</div>
                            <div class="card-body">
                                <?php if (empty($sales)): ?>
                                    <p class="text-muted">No sales recorded yet.</p>
                                <?php else: ?>
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Quantity</th>
                                                <th>Total Price</th>
                                                <th>Date</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($sales as $sale): ?>
                                                <tr>
                                                    <td><?php echo $sale['quantity']; ?></td>
                                                    <td>₱<?php echo number_format($sale['total_price'], 2); ?></td>
                                                    <td><?php echo date('M d, Y H:i', strtotime($sale['created_at'])); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">Stock Adjustments</div>
                            <div class="card-body">
                                <?php if (empty($stock_logs)): ?>
                                    <p class="text-muted">No stock adjustments recorded yet.</p>
                                <?php else: ?>
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Action</th>
                                                <th>Qty</th>
                                                <th>User</th>
                                                <th>Date</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($stock_logs as $log): ?>
                                                <tr>
                                                    <td>
                                                        <span class="badge <?php echo $log['action'] === 'stock_in' ? 'bg-success' : 'bg-warning'; ?>">
                                                            <?php echo $log['action'] === 'stock_in' ? '+' : '-'; ?><?php echo $log['quantity']; ?>
                                                        </span>
                                                    </td>
                                                    <td><?php echo $log['quantity']; ?></td>
                                                    <td><?php echo htmlspecialchars($log['username'] ?? 'System'); ?></td>
                                                    <td><?php echo date('M d, H:i', strtotime($log['created_at'])); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
        </main>
    </div>

    <!-- Stock Adjustment Modal -->
    <div class="modal fade" id="adjustStockModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="action" value="adjust_stock">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="bi bi-plus-minus"></i> Adjust Stock - <?php echo htmlspecialchars($product['product_name']); ?>
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle"></i> Current Stock: <strong><?php echo $product['stock_quantity']; ?> units</strong>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Adjustment Type</label>
                            <select name="adjustment_type" class="form-select" required>
                                <option value="add">Add Stock (+)</option>
                                <option value="remove">Remove Stock (-)</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Quantity</label>
                            <input type="number" name="adjustment" class="form-control" min="1" required placeholder="Enter quantity to adjust">
                            <div class="mt-2">
                                <small class="text-muted">Quick amounts:</small>
                                <button type="button" class="btn btn-outline-secondary btn-sm ms-1" onclick="setAdjustment(1)">1</button>
                                <button type="button" class="btn btn-outline-secondary btn-sm ms-1" onclick="setAdjustment(5)">5</button>
                                <button type="button" class="btn btn-outline-secondary btn-sm ms-1" onclick="setAdjustment(10)">10</button>
                                <button type="button" class="btn btn-outline-secondary btn-sm ms-1" onclick="setAdjustment(25)">25</button>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Notes (Optional)</label>
                            <textarea name="notes" class="form-control" rows="3" placeholder="Reason for adjustment..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-warning">
                            <i class="bi bi-check-lg"></i> Adjust Stock
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Product Modal -->
    <?php if (isAdmin()): ?>
    <div class="modal fade" id="editProductModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="action" value="edit_product">
                    <input type="hidden" name="current_image" value="<?php echo htmlspecialchars($product['image']); ?>">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="bi bi-pencil"></i> Edit Product
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Product Name</label>
                                    <input type="text" name="product_name" class="form-control" value="<?php echo htmlspecialchars($product['product_name']); ?>" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Category</label>
                                    <select name="category_id" class="form-select" required>
                                        <?php foreach ($categories as $cat): ?>
                                            <option value="<?php echo $cat['id']; ?>" <?php echo $cat['id'] == $product['category_id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($cat['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Supplier</label>
                                    <select name="supplier_id" class="form-select" required>
                                        <?php foreach ($suppliers as $sup): ?>
                                            <option value="<?php echo $sup['id']; ?>" <?php echo $sup['id'] == $product['supplier_id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($sup['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Cost Price</label>
                                    <input type="number" step="0.01" name="cost_price" class="form-control" value="<?php echo $product['cost_price'] ?? 0; ?>" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Selling Price</label>
                                    <input type="number" step="0.01" name="price" class="form-control" value="<?php echo $product['price']; ?>" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Reorder Level</label>
                                    <input type="number" name="reorder_level" class="form-control" value="<?php echo $product['reorder_level']; ?>" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Barcode</label>
                                    <input type="text" name="barcode" class="form-control" value="<?php echo htmlspecialchars($product['barcode']); ?>">
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Product Image</label>
                            <?php if ($product['image']): ?>
                                <div class="mb-2">
                                    <img src="uploads/<?php echo htmlspecialchars($product['image']); ?>" width="100" height="100" class="img-thumbnail">
                                    <small class="text-muted d-block">Current image</small>
                                </div>
                            <?php endif; ?>
                            <input type="file" name="image" class="form-control" accept="image/*">
                            <small class="text-muted">Leave empty to keep current image</small>
                        </div>
                        
                        <div class="alert alert-warning">
                            <i class="bi bi-exclamation-triangle"></i> 
                            <strong>Note:</strong> Stock quantity cannot be changed here. Use the "Adjust Stock" button to modify inventory levels.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg"></i> Update Product
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php include 'includes/chatbot.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/chatbot.js"></script>
    <script src="js/mobile.js?v=<?php echo time(); ?>"></script>
    <script>
        // Stock adjustment preview
        document.addEventListener('DOMContentLoaded', function() {
            const adjustmentType = document.querySelector('select[name="adjustment_type"]');
            const adjustmentQty = document.querySelector('input[name="adjustment"]');
            const currentStock = <?php echo $product['stock_quantity']; ?>;
            
            function updatePreview() {
                if (adjustmentQty && adjustmentType) {
                    const qty = parseInt(adjustmentQty.value) || 0;
                    const type = adjustmentType.value;
                    let newStock = currentStock;
                    
                    if (type === 'add') {
                        newStock = currentStock + qty;
                    } else {
                        newStock = currentStock - qty;
                    }
                    
                    // Update preview in modal
                    let preview = document.querySelector('.stock-preview');
                    if (!preview) {
                        preview = document.createElement('div');
                        preview.className = 'stock-preview alert alert-secondary mt-2';
                        adjustmentQty.parentNode.appendChild(preview);
                    }
                    
                    if (qty > 0) {
                        const color = newStock < 0 ? 'text-danger' : 'text-success';
                        preview.innerHTML = `<i class="bi bi-calculator"></i> New Stock: <span class="${color}"><strong>${newStock} units</strong></span>`;
                        
                        if (newStock < 0) {
                            preview.innerHTML += '<br><small class="text-danger">⚠️ Warning: Stock will be negative!</small>';
                        }
                    } else {
                        preview.innerHTML = '';
                    }
                }
            }
            
            if (adjustmentType && adjustmentQty) {
                adjustmentType.addEventListener('change', updatePreview);
                adjustmentQty.addEventListener('input', updatePreview);
            }
        });
        
        // Quick adjustment buttons
        function setAdjustment(amount) {
            const input = document.querySelector('input[name="adjustment"]');
            if (input) {
                input.value = amount;
                input.dispatchEvent(new Event('input'));
            }
        }
    </script>
</body>
</html>
