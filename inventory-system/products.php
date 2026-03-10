<?php
// Include required files
require_once 'config/database.php';
require_once 'config/session.php';

// Make sure user is logged in
requireLogin();

// Connect to database
$database = new Database();
$db = $database->getConnection();

// Handle form submissions (adding/deleting products)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    // Check security token
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        die('Invalid CSRF token');
    }
    
    // Adding a new product
    if ($_POST['action'] === 'add') {
        $uploaded_image = '';
        
        // Handle image upload if user selected one
        if (isset($_FILES['image']) && $_FILES['image']['error'] === 0) {
            $upload_folder = "uploads/";
            if (!file_exists($upload_folder)) {
                mkdir($upload_folder, 0777, true);
            }
            $uploaded_image = time() . '_' . basename($_FILES["image"]["name"]);
            move_uploaded_file($_FILES["image"]["tmp_name"], $upload_folder . $uploaded_image);
        }
        
        // Insert new product into database
        $insert_query = "INSERT INTO products (product_name, category_id, supplier_id, price, stock_quantity, reorder_level, barcode, image) VALUES (:name, :category, :supplier, :price, :stock, :reorder, :barcode, :image)";
        $stmt = $db->prepare($insert_query);
        $stmt->execute([
            ':name' => $_POST['product_name'],
            ':category' => $_POST['category_id'],
            ':supplier' => $_POST['supplier_id'],
            ':price' => $_POST['price'],
            ':stock' => $_POST['stock_quantity'],
            ':reorder' => $_POST['reorder_level'],
            ':barcode' => $_POST['barcode'],
            ':image' => $uploaded_image
        ]);
        header("Location: products.php?success=added");
        exit();
    }
    
    // Deleting a product (only admins can do this)
    if ($_POST['action'] === 'delete' && isAdmin()) {
        $stmt = $db->prepare("DELETE FROM products WHERE id = :id");
        $stmt->execute([':id' => $_POST['product_id']]);
        header("Location: products.php?success=deleted");
        exit();
    }
}

// Check if user is searching for something
$search_term = $_GET['search'] ?? '';
$query = "SELECT p.*, c.name as category_name, s.name as supplier_name 
          FROM products p 
          LEFT JOIN categories c ON p.category_id = c.id 
          LEFT JOIN suppliers s ON p.supplier_id = s.id";

if ($search) {
    $query .= " WHERE p.product_name LIKE :search OR p.barcode LIKE :search";
}
$query .= " ORDER BY p.date_added DESC";

$stmt = $db->prepare($query);
if ($search) {
    $stmt->bindValue(':search', "%$search%");
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
                    <div>
                        <button onclick="printProducts()" class="btn btn-outline-secondary">
                            <i class="bi bi-printer"></i> Print
                        </button>
                        <button onclick="exportProductsCSV()" class="btn btn-outline-success">
                            <i class="bi bi-file-earmark-spreadsheet"></i> Export CSV
                        </button>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addProductModal">
                            <i class="bi bi-plus-lg"></i> Add Product
                        </button>
                    </div>
                </div>

                <form method="GET" class="mb-3">
                    <div class="input-group">
                        <input type="text" name="search" class="form-control" placeholder="Search products..." value="<?php echo htmlspecialchars($search); ?>">
                        <button class="btn btn-outline-secondary" type="submit">Search</button>
                    </div>
                </form>

                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Image</th>
                                <th>Product Name</th>
                                <th>Category</th>
                                <th>Supplier</th>
                                <th>Price</th>
                                <th>Stock</th>
                                <th>Reorder Level</th>
                                <th>Barcode</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($products as $product): ?>
                                <tr>
                                    <td>
                                        <?php if ($product['image']): ?>
                                            <img src="uploads/<?php echo htmlspecialchars($product['image']); ?>" width="50" height="50" class="img-thumbnail">
                                        <?php else: ?>
                                            <div class="bg-secondary" style="width:50px;height:50px;"></div>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($product['product_name']); ?></td>
                                    <td><?php echo htmlspecialchars($product['category_name']); ?></td>
                                    <td><?php echo htmlspecialchars($product['supplier_name']); ?></td>
                                    <td>₱<?php echo number_format($product['price'], 2); ?></td>
                                    <td>
                                        <span class="badge <?php echo $product['stock_quantity'] <= $product['reorder_level'] ? 'bg-danger' : 'bg-success'; ?>">
                                            <?php echo $product['stock_quantity']; ?>
                                        </span>
                                    </td>
                                    <td><?php echo $product['reorder_level']; ?></td>
                                    <td><?php echo htmlspecialchars($product['barcode']); ?></td>
                                    <td>
                                        <a href="product_detail.php?id=<?php echo $product['id']; ?>" class="btn btn-sm btn-info">
                                            <i class="bi bi-eye"></i> View
                                        </a>
                                        <?php if ($product['stock_quantity'] > 0): ?>
                                            <button class="btn btn-sm btn-warning" onclick="openReservationModal(<?php echo $product['id']; ?>, '<?php echo htmlspecialchars($product['product_name']); ?>', <?php echo $product['stock_quantity']; ?>, <?php echo $product['price']; ?>)">
                                                <i class="bi bi-bookmark-plus"></i> Reserve
                                            </button>
                                        <?php endif; ?>
                                        <?php if (isAdmin()): ?>
                                            <form method="POST" style="display:inline;">
                                                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Delete this product?')">Delete</button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
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
                            <label class="form-label">Price</label>
                            <input type="number" step="0.01" name="price" class="form-control" required>
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
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary">Add Product</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Reservation Modal -->
    <div class="modal fade" id="reservationModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form id="reservationForm" method="POST" action="create_reservation.php">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="product_id" id="modal_product_id">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="bi bi-bookmark-plus"></i> Create Reservation
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle"></i> 
                            <strong>Product:</strong> <span id="modal_product_name"></span><br>
                            <strong>Available Stock:</strong> <span id="modal_stock"></span> units<br>
                            <strong>Price per unit:</strong> ₱<span id="modal_price"></span>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Quantity to Reserve</label>
                            <input type="number" name="quantity" id="modal_quantity" class="form-control" min="1" required placeholder="Enter quantity">
                            <div class="mt-2">
                                <small class="text-muted">Quick amounts:</small>
                                <button type="button" class="btn btn-outline-secondary btn-sm ms-1" onclick="setReservationQuantity(1)">1</button>
                                <button type="button" class="btn btn-outline-secondary btn-sm ms-1" onclick="setReservationQuantity(5)">5</button>
                                <button type="button" class="btn btn-outline-secondary btn-sm ms-1" onclick="setReservationQuantity(10)">10</button>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Customer Name (Optional)</label>
                            <input type="text" name="customer_name" class="form-control" placeholder="Enter customer name">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Notes (Optional)</label>
                            <textarea name="notes" class="form-control" rows="3" placeholder="Additional notes for this reservation..."></textarea>
                        </div>
                        
                        <div id="reservation_preview" class="alert alert-secondary" style="display: none;">
                            <strong>Reservation Summary:</strong><br>
                            <span id="preview_text"></span>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-warning">
                            <i class="bi bi-bookmark-plus"></i> Create Reservation
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php include 'includes/chatbot.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/chatbot.js?v=<?php echo time(); ?>"></script>
    <script src="js/export.js?v=<?php echo time(); ?>"></script>
    <script src="js/mobile.js?v=<?php echo time(); ?>"></script>
    
    <script>
        let currentProduct = {};
        
        function openReservationModal(productId, productName, stock, price) {
            currentProduct = { id: productId, name: productName, stock: stock, price: price };
            
            document.getElementById('modal_product_id').value = productId;
            document.getElementById('modal_product_name').textContent = productName;
            document.getElementById('modal_stock').textContent = stock;
            document.getElementById('modal_price').textContent = parseFloat(price).toFixed(2);
            document.getElementById('modal_quantity').max = stock;
            document.getElementById('modal_quantity').value = '';
            document.getElementById('reservation_preview').style.display = 'none';
            
            const modal = new bootstrap.Modal(document.getElementById('reservationModal'));
            modal.show();
        }
        
        function setReservationQuantity(amount) {
            const input = document.getElementById('modal_quantity');
            const maxStock = parseInt(currentProduct.stock);
            
            if (amount <= maxStock) {
                input.value = amount;
                updateReservationPreview();
            } else {
                input.value = maxStock;
                updateReservationPreview();
                alert(`Maximum available stock is ${maxStock} units`);
            }
        }
        
        function updateReservationPreview() {
            const quantity = parseInt(document.getElementById('modal_quantity').value) || 0;
            const preview = document.getElementById('reservation_preview');
            const previewText = document.getElementById('preview_text');
            
            if (quantity > 0 && quantity <= currentProduct.stock) {
                const totalValue = quantity * currentProduct.price;
                const remainingStock = currentProduct.stock - quantity;
                
                previewText.innerHTML = `
                    <strong>${quantity}</strong> units of <strong>${currentProduct.name}</strong><br>
                    Total Value: <strong>₱${totalValue.toFixed(2)}</strong><br>
                    Remaining Stock: <strong>${remainingStock}</strong> units
                `;
                preview.style.display = 'block';
            } else {
                preview.style.display = 'none';
            }
        }
        
        // Add event listener for quantity input
        document.addEventListener('DOMContentLoaded', function() {
            const quantityInput = document.getElementById('modal_quantity');
            if (quantityInput) {
                quantityInput.addEventListener('input', updateReservationPreview);
            }
        });
    </script>
</body>
</html>
