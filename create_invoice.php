<?php
require_once 'config/database.php';
require_once 'config/session.php';

requireLogin();

$database = new Database();
$db = $database->getConnection();

// Auto-create invoice tables if they don't exist
try {
    $db->exec("CREATE TABLE IF NOT EXISTS invoices (
        id INT AUTO_INCREMENT PRIMARY KEY,
        invoice_number VARCHAR(50) NOT NULL UNIQUE,
        customer_name VARCHAR(255) NOT NULL,
        customer_email VARCHAR(255) DEFAULT NULL,
        customer_phone VARCHAR(20) DEFAULT NULL,
        customer_address TEXT DEFAULT NULL,
        subtotal DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        tax_rate DECIMAL(5,2) NOT NULL DEFAULT 0.00,
        tax_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        discount_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        total_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        payment_status ENUM('pending','paid','partial','cancelled') DEFAULT 'pending',
        payment_method VARCHAR(50) DEFAULT NULL,
        notes TEXT DEFAULT NULL,
        created_by INT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_invoice_number (invoice_number),
        INDEX idx_payment_status (payment_status)
    )");
    
    $db->exec("CREATE TABLE IF NOT EXISTS invoice_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        invoice_id INT NOT NULL,
        product_id INT NOT NULL,
        product_name VARCHAR(255) NOT NULL,
        quantity INT NOT NULL,
        unit_price DECIMAL(10,2) NOT NULL,
        subtotal DECIMAL(12,2) NOT NULL,
        stock_status ENUM('in_stock','out_of_stock') DEFAULT 'in_stock',
        INDEX idx_invoice_id (invoice_id)
    )");
} catch (Exception $e) {
    error_log("Invoice tables error: " . $e->getMessage());
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_invoice') {
    try {
        $db->beginTransaction();
        
        // Decode and validate items
        $items = json_decode($_POST['items'], true);
        
        // Validation: Check for empty items
        if (empty($items)) {
            throw new Exception('Cannot create invoice without items');
        }
        
        // Generate unique invoice number with collision check
        $invoice_number = '';
        $max_attempts = 10;
        $attempt = 0;
        
        do {
            $invoice_number = 'INV-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
            $stmt_check = $db->prepare("SELECT id FROM invoices WHERE invoice_number = ?");
            $stmt_check->execute([$invoice_number]);
            $exists = $stmt_check->fetch();
            $attempt++;
            
            if ($attempt >= $max_attempts) {
                throw new Exception('Unable to generate unique invoice number. Please try again.');
            }
        } while ($exists);
        
        // Calculate totals
        $subtotal = 0;
        
        foreach ($items as $item) {
            $subtotal += $item['quantity'] * $item['price'];
        }
        
        $tax_rate = floatval($_POST['tax_rate'] ?? 0);
        $discount = floatval($_POST['discount'] ?? 0);
        $tax_amount = ($subtotal - $discount) * ($tax_rate / 100);
        $total = $subtotal - $discount + $tax_amount;
        
        // Insert invoice
        $stmt = $db->prepare("INSERT INTO invoices (invoice_number, customer_name, customer_email, customer_phone, customer_address, subtotal, tax_rate, tax_amount, discount_amount, total_amount, payment_status, payment_method, notes, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        $stmt->execute([
            $invoice_number,
            $_POST['customer_name'],
            $_POST['customer_email'] ?? null,
            $_POST['customer_phone'] ?? null,
            $_POST['customer_address'] ?? null,
            $subtotal,
            $tax_rate,
            $tax_amount,
            $discount,
            $total,
            $_POST['payment_status'] ?? 'pending',
            $_POST['payment_method'] ?? null,
            $_POST['notes'] ?? null,
            $_SESSION['user_id']
        ]);
        
        $invoice_id = $db->lastInsertId();
        
        // Insert invoice items and update stock
        $stmt_item = $db->prepare("INSERT INTO invoice_items (invoice_id, product_id, product_name, quantity, unit_price, subtotal, stock_status) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt_product = $db->prepare("SELECT product_name, stock_quantity FROM products WHERE id = ?");
        $stmt_update = $db->prepare("UPDATE products SET stock_quantity = stock_quantity - ? WHERE id = ?");
        $stmt_log = $db->prepare("INSERT INTO inventory_logs (product_id, action, quantity, user_id, notes) VALUES (?, 'stock_out', ?, ?, ?)");
        
        foreach ($items as $item) {
            $product_id = $item['product_id'];
            $quantity = $item['quantity'];
            $price = $item['price'];
            
            // Get product info
            $stmt_product->execute([$product_id]);
            $product = $stmt_product->fetch(PDO::FETCH_ASSOC);
            
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
        header("Location: view_invoice.php?id=$invoice_id&success=" . urlencode("Invoice created successfully"));
        exit();
        
    } catch (Exception $e) {
        $db->rollBack();
        $error = "Failed to create invoice: " . $e->getMessage();
    }
}

// Get products for selection
$products = $db->query("SELECT p.*, c.name as category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id ORDER BY p.product_name")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Invoice - Inventory System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="css/style.css">
    <style>
        .product-item {
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 10px;
            background: #f8f9fa;
        }
        .stock-badge {
            font-size: 0.85rem;
        }
    </style>
</head>
<body>
    <?php include 'includes/navbar.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include 'includes/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2"><i class="bi bi-receipt-cutoff"></i> Create Invoice</h1>
                    <a href="invoices.php" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left"></i> Back to Invoices
                    </a>
                </div>

                <?php if (isset($error)): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <?php echo htmlspecialchars($error); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <form method="POST" id="invoiceForm">
                    <input type="hidden" name="action" value="create_invoice">
                    <input type="hidden" name="items" id="itemsData">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="card mb-3">
                                <div class="card-header">
                                    <h5 class="mb-0">Customer Information</h5>
                                </div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <label class="form-label">Customer Name *</label>
                                        <input type="text" class="form-control" name="customer_name" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Email</label>
                                        <input type="email" class="form-control" name="customer_email">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Phone</label>
                                        <input type="text" class="form-control" name="customer_phone">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Address</label>
                                        <textarea class="form-control" name="customer_address" rows="2"></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="card mb-3">
                                <div class="card-header">
                                    <h5 class="mb-0">Payment Details</h5>
                                </div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <label class="form-label">Payment Status</label>
                                        <select class="form-select" name="payment_status">
                                            <option value="pending">Pending</option>
                                            <option value="paid">Paid</option>
                                            <option value="partial">Partial</option>
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Payment Method</label>
                                        <select class="form-select" name="payment_method">
                                            <option value="">Select method</option>
                                            <option value="cash">Cash</option>
                                            <option value="card">Credit/Debit Card</option>
                                            <option value="bank_transfer">Bank Transfer</option>
                                            <option value="gcash">GCash</option>
                                            <option value="paymaya">PayMaya</option>
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Tax Rate (%)</label>
                                        <input type="number" class="form-control" name="tax_rate" id="taxRate" value="0" min="0" max="100" step="0.01">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Discount (₱)</label>
                                        <input type="number" class="form-control" name="discount" id="discount" value="0" min="0" step="0.01">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Notes</label>
                                        <textarea class="form-control" name="notes" rows="2"></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card mb-3">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">Invoice Items</h5>
                            <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addProductModal">
                                <i class="bi bi-plus-circle"></i> Add Product
                            </button>
                        </div>
                        <div class="card-body">
                            <div id="invoiceItems">
                                <p class="text-muted text-center py-4">No items added yet. Click "Add Product" to start.</p>
                            </div>
                            
                            <div class="row mt-4">
                                <div class="col-md-6 ms-auto">
                                    <table class="table">
                                        <tr>
                                            <td><strong>Subtotal:</strong></td>
                                            <td class="text-end">₱<span id="subtotalDisplay">0.00</span></td>
                                        </tr>
                                        <tr>
                                            <td><strong>Discount:</strong></td>
                                            <td class="text-end">₱<span id="discountDisplay">0.00</span></td>
                                        </tr>
                                        <tr>
                                            <td><strong>Tax:</strong></td>
                                            <td class="text-end">₱<span id="taxDisplay">0.00</span></td>
                                        </tr>
                                        <tr class="table-primary">
                                            <td><strong>Total:</strong></td>
                                            <td class="text-end"><strong>₱<span id="totalDisplay">0.00</span></strong></td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mb-4">
                        <button type="submit" class="btn btn-success btn-lg">
                            <i class="bi bi-check-circle"></i> Create Invoice
                        </button>
                        <a href="invoices.php" class="btn btn-secondary btn-lg">Cancel</a>
                    </div>
                </form>
            </main>
        </div>
    </div>

    <!-- Add Product Modal -->
    <div class="modal fade" id="addProductModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add Product</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="text" class="form-control mb-3" id="productSearch" placeholder="Search products...">
                    <div id="productList" style="max-height: 400px; overflow-y: auto;">
                        <?php foreach ($products as $product): ?>
                            <div class="product-item" data-product='<?php echo json_encode($product); ?>'>
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <h6 class="mb-1"><?php echo htmlspecialchars($product['product_name']); ?></h6>
                                        <small class="text-muted"><?php echo htmlspecialchars($product['category_name']); ?></small>
                                        <div class="mt-1">
                                            <?php if ($product['stock_quantity'] > 0): ?>
                                                <span class="badge bg-success stock-badge">In Stock: <?php echo $product['stock_quantity']; ?></span>
                                            <?php else: ?>
                                                <span class="badge bg-danger stock-badge">Out of Stock</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="text-end">
                                        <div class="mb-2"><strong>₱<?php echo number_format($product['price'], 2); ?></strong></div>
                                        <button type="button" class="btn btn-sm btn-primary add-product-btn">
                                            <i class="bi bi-plus"></i> Add
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let invoiceItems = [];

        // Product search
        document.getElementById('productSearch').addEventListener('input', function(e) {
            const search = e.target.value.toLowerCase();
            document.querySelectorAll('.product-item').forEach(item => {
                const text = item.textContent.toLowerCase();
                item.style.display = text.includes(search) ? 'block' : 'none';
            });
        });

        // Add product to invoice
        document.querySelectorAll('.add-product-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const productData = JSON.parse(this.closest('.product-item').dataset.product);
                
                // Check if already added
                const existing = invoiceItems.find(item => item.product_id === productData.id);
                if (existing) {
                    existing.quantity++;
                } else {
                    invoiceItems.push({
                        product_id: productData.id,
                        name: productData.product_name,
                        price: parseFloat(productData.price),
                        quantity: 1,
                        stock: parseInt(productData.stock_quantity)
                    });
                }
                
                updateInvoiceDisplay();
                bootstrap.Modal.getInstance(document.getElementById('addProductModal')).hide();
            });
        });

        function updateInvoiceDisplay() {
            const container = document.getElementById('invoiceItems');
            
            if (invoiceItems.length === 0) {
                container.innerHTML = '<p class="text-muted text-center py-4">No items added yet.</p>';
                updateTotals();
                return;
            }
            
            let html = '';
            invoiceItems.forEach((item, index) => {
                const stockStatus = item.stock >= item.quantity ? 
                    `<span class="badge bg-success">In Stock</span>` : 
                    `<span class="badge bg-danger">Out of Stock</span>`;
                
                html += `
                    <div class="product-item">
                        <div class="row align-items-center">
                            <div class="col-md-4">
                                <strong>${item.name}</strong><br>
                                ${stockStatus}
                            </div>
                            <div class="col-md-2">
                                ₱${item.price.toFixed(2)}
                            </div>
                            <div class="col-md-3">
                                <div class="input-group input-group-sm">
                                    <button class="btn btn-outline-secondary" type="button" onclick="updateQuantity(${index}, -1)">-</button>
                                    <input type="number" class="form-control text-center" value="${item.quantity}" min="1" onchange="setQuantity(${index}, this.value)">
                                    <button class="btn btn-outline-secondary" type="button" onclick="updateQuantity(${index}, 1)">+</button>
                                </div>
                            </div>
                            <div class="col-md-2 text-end">
                                <strong>₱${(item.price * item.quantity).toFixed(2)}</strong>
                            </div>
                            <div class="col-md-1 text-end">
                                <button type="button" class="btn btn-sm btn-danger" onclick="removeItem(${index})">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                `;
            });
            
            container.innerHTML = html;
            updateTotals();
        }

        function updateQuantity(index, change) {
            invoiceItems[index].quantity = Math.max(1, invoiceItems[index].quantity + change);
            updateInvoiceDisplay();
        }

        function setQuantity(index, value) {
            invoiceItems[index].quantity = Math.max(1, parseInt(value) || 1);
            updateInvoiceDisplay();
        }

        function removeItem(index) {
            invoiceItems.splice(index, 1);
            updateInvoiceDisplay();
        }

        function updateTotals() {
            const subtotal = invoiceItems.reduce((sum, item) => sum + (item.price * item.quantity), 0);
            const discount = parseFloat(document.getElementById('discount').value) || 0;
            const taxRate = parseFloat(document.getElementById('taxRate').value) || 0;
            const taxAmount = (subtotal - discount) * (taxRate / 100);
            const total = subtotal - discount + taxAmount;
            
            document.getElementById('subtotalDisplay').textContent = subtotal.toFixed(2);
            document.getElementById('discountDisplay').textContent = discount.toFixed(2);
            document.getElementById('taxDisplay').textContent = taxAmount.toFixed(2);
            document.getElementById('totalDisplay').textContent = total.toFixed(2);
        }

        // Update totals when tax or discount changes
        document.getElementById('taxRate').addEventListener('input', updateTotals);
        document.getElementById('discount').addEventListener('input', updateTotals);

        // Form submission
        document.getElementById('invoiceForm').addEventListener('submit', function(e) {
            if (invoiceItems.length === 0) {
                e.preventDefault();
                alert('Please add at least one product to the invoice.');
                return;
            }
            
            document.getElementById('itemsData').value = JSON.stringify(invoiceItems);
        });
    </script>
</body>
</html>
