<?php
require_once 'config/database.php';
require_once 'config/session.php';
requireLogin();

$db = (new Database())->getConnection();

// Auto-create invoice tables
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
    // Auto-create customers table
    $db->exec("CREATE TABLE IF NOT EXISTS customers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        email VARCHAR(255) DEFAULT NULL,
        phone VARCHAR(30) DEFAULT NULL,
        address TEXT DEFAULT NULL,
        total_invoices INT NOT NULL DEFAULT 0,
        total_spent DECIMAL(14,2) NOT NULL DEFAULT 0.00,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_name (name)
    )");
} catch (Exception $e) {
    error_log("Invoice/customer tables error: " . $e->getMessage());
}

// ── Handle form submission ──────────────────────────────────────────────
$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create_invoice') {

    // CSRF check
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Security token mismatch. Please refresh and try again.';
    } else {
        try {
            $db->beginTransaction();

            $items = json_decode($_POST['items'] ?? '[]', true);
            if (empty($items)) throw new Exception('Cannot create invoice without items.');

            // Generate unique invoice number
            $invoice_number = '';
            for ($attempt = 0; $attempt < 10; $attempt++) {
                $candidate = 'INV-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
                $chk = $db->prepare("SELECT id FROM invoices WHERE invoice_number = ?");
                $chk->execute([$candidate]);
                if (!$chk->fetch()) { $invoice_number = $candidate; break; }
            }
            if (!$invoice_number) throw new Exception('Unable to generate a unique invoice number. Try again.');

            // Calculate totals
            $subtotal = array_sum(array_map(fn($i) => $i['quantity'] * $i['price'], $items));
            $tax_rate   = floatval($_POST['tax_rate']  ?? 0);
            $discount   = floatval($_POST['discount']  ?? 0);
            $tax_amount = ($subtotal - $discount) * ($tax_rate / 100);
            $total      = $subtotal - $discount + $tax_amount;

            // Insert invoice
            $stmt = $db->prepare("INSERT INTO invoices (invoice_number, customer_name, customer_email,
                customer_phone, customer_address, subtotal, tax_rate, tax_amount, discount_amount,
                total_amount, payment_status, payment_method, notes, created_by)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
            $stmt->execute([
                $invoice_number,
                trim($_POST['customer_name']),
                trim($_POST['customer_email'] ?? '') ?: null,
                trim($_POST['customer_phone'] ?? '') ?: null,
                trim($_POST['customer_address'] ?? '') ?: null,
                $subtotal, $tax_rate, $tax_amount, $discount, $total,
                $_POST['payment_status'] ?? 'pending',
                $_POST['payment_method'] ?? null,
                trim($_POST['notes'] ?? '') ?: null,
                $_SESSION['user_id'],
            ]);
            $invoice_id = $db->lastInsertId();

            // Insert items & update stock
            $ins_item   = $db->prepare("INSERT INTO invoice_items (invoice_id, product_id, product_name, quantity, unit_price, subtotal, stock_status) VALUES (?,?,?,?,?,?,?)");
            $upd_stock  = $db->prepare("UPDATE products SET stock_quantity = stock_quantity - ? WHERE id = ?");
            $ins_log    = $db->prepare("INSERT INTO inventory_logs (product_id, action, quantity, user_id, notes) VALUES (?, 'stock_out', ?, ?, ?)");
            $get_prod   = $db->prepare("SELECT product_name, stock_quantity FROM products WHERE id = ?");

            foreach ($items as $item) {
                $pid = (int)$item['product_id'];
                $qty = (int)$item['quantity'];
                $price = (float)$item['price'];
                $get_prod->execute([$pid]);
                $prod = $get_prod->fetch(PDO::FETCH_ASSOC);
                $status = ($prod && $prod['stock_quantity'] >= $qty) ? 'in_stock' : 'out_of_stock';
                $ins_item->execute([$invoice_id, $pid, $prod['product_name'] ?? '', $qty, $price, $qty * $price, $status]);
                if ($status === 'in_stock') {
                    $upd_stock->execute([$qty, $pid]);
                    $ins_log->execute([$pid, $qty, $_SESSION['user_id'], "Invoice: $invoice_number"]);
                }
            }

            // Upsert customer record
            $cust_name  = trim($_POST['customer_name']);
            $cust_email = trim($_POST['customer_email'] ?? '');
            try {
                if ($cust_email) {
                    $cx = $db->prepare("SELECT id FROM customers WHERE email = ? LIMIT 1");
                    $cx->execute([$cust_email]);
                } else {
                    $cx = $db->prepare("SELECT id FROM customers WHERE name = ? LIMIT 1");
                    $cx->execute([$cust_name]);
                }
                $cust_row = $cx->fetch(PDO::FETCH_ASSOC);
                if ($cust_row) {
                    $db->prepare("UPDATE customers SET name=?, email=?, phone=?, address=?, total_invoices=total_invoices+1, total_spent=total_spent+?, updated_at=NOW() WHERE id=?")
                       ->execute([$cust_name, $cust_email ?: null, trim($_POST['customer_phone'] ?? '') ?: null, trim($_POST['customer_address'] ?? '') ?: null, $total, $cust_row['id']]);
                } else {
                    $db->prepare("INSERT INTO customers (name, email, phone, address, total_invoices, total_spent) VALUES (?,?,?,?,1,?)")
                       ->execute([$cust_name, $cust_email ?: null, trim($_POST['customer_phone'] ?? '') ?: null, trim($_POST['customer_address'] ?? '') ?: null, $total]);
                }
            } catch (Exception $ce) { /* non-fatal */ }

            $db->commit();
            header("Location: view_invoice.php?id=$invoice_id&success=" . urlencode("Invoice $invoice_number created successfully!"));
            exit();

        } catch (Exception $e) {
            $db->rollBack();
            $error = "Failed to create invoice: " . $e->getMessage();
        }
    }
}

// Get products for selection
$products = $db->query("SELECT p.*, c.name as category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id ORDER BY p.product_name")->fetchAll(PDO::FETCH_ASSOC);
$csrf_token = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php
$page_title = 'Create Invoice — InvenAI';
$extra_head = '<style>
.product-item {
    border: 1px solid var(--border-subtle);
    border-radius: var(--border-radius-md);
    padding: 14px 16px;
    margin-bottom: 8px;
    background: var(--bg-elevated);
    transition: border-color 0.15s, box-shadow 0.15s;
    cursor: pointer;
}
.product-item:hover { border-color: var(--accent-primary); box-shadow: 0 0 0 3px rgba(99,102,241,0.08); }

/* Invoice items table */
.invoice-item-row {
    display: grid;
    grid-template-columns: 1fr 90px 120px 90px 36px;
    gap: 0.5rem;
    align-items: center;
    padding: 0.6rem 0;
    border-bottom: 1px solid var(--border-subtle);
}
.invoice-item-row:last-child { border-bottom: none; }
.invoice-item-row > * { min-width: 0; }
.invoice-item-label { font-weight: 600; color: var(--text-primary); font-size: 0.875rem; }
.invoice-item-label small { display:block; color:var(--text-muted); font-weight:400; }

/* Totals panel */
.totals-row { display: flex; justify-content: space-between; padding: 0.4rem 0; font-size: 0.9rem; color: var(--text-secondary); }
.totals-row.total-final { padding-top: 0.75rem; border-top: 2px solid var(--border-default); font-size: 1.1rem; font-weight: 700; color: var(--text-primary); }
.totals-row .amount { color: var(--accent-emerald); font-variant-numeric: tabular-nums; }
.totals-row.total-final .amount { font-size: 1.25rem; }

/* Customer autocomplete */
#customerSuggestionsBox {
    position: absolute;
    top: 100%;
    left: 0; right: 0;
    background: var(--bg-elevated);
    border: 1px solid var(--border-default);
    border-radius: 0 0 var(--border-radius-md) var(--border-radius-md);
    z-index: 200;
    max-height: 220px;
    overflow-y: auto;
    box-shadow: var(--shadow-md);
    display: none;
}
.customer-suggestion {
    padding: 0.65rem 1rem;
    cursor: pointer;
    border-bottom: 1px solid var(--border-subtle);
    font-size: 0.85rem;
    transition: background 0.12s;
}
.customer-suggestion:last-child { border-bottom: none; }
.customer-suggestion:hover { background: rgba(99,102,241,0.1); }
.customer-suggestion .cust-name { font-weight: 600; color: var(--text-primary); }
.customer-suggestion .cust-meta { color: var(--text-muted); font-size: 0.78rem; }
</style>';
include 'includes/head.php';
?>
</head>
<body>
    <?php include 'includes/navbar.php'; ?>

    <div class="container-fluid">
        <?php include 'includes/sidebar.php'; ?>

        <main id="mainContent">

            <!-- Page header -->
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center mb-4">
                <div>
                    <h1 style="font-size:1.5rem;font-weight:700;color:var(--text-primary);margin:0;">
                        <i class="bi bi-receipt-cutoff"></i> Create Invoice
                    </h1>
                    <p style="color:var(--text-muted);margin:0;font-size:0.85rem;">Fill in customer info and add products</p>
                </div>
                <a href="invoices.php" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-arrow-left"></i> Back to Invoices
                </a>
            </div>

            <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="bi bi-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <form method="POST" id="invoiceForm">
                <input type="hidden" name="action" value="create_invoice">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                <input type="hidden" name="items" id="itemsData">

                <div class="row g-4">
                    <!-- ── LEFT: Customer + Payment ─────────────────── -->
                    <div class="col-xl-5">

                        <!-- Customer card -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="bi bi-person-circle"></i> Customer Information</h5>
                            </div>
                            <div class="card-body">

                                <!-- Autocomplete name -->
                                <div class="mb-3 position-relative">
                                    <label class="form-label">Customer Name <span style="color:var(--accent-rose)">*</span></label>
                                    <div class="position-relative">
                                        <span style="position:absolute;left:12px;top:50%;transform:translateY(-50%);color:var(--text-muted);pointer-events:none;">
                                            <i class="bi bi-search" style="font-size:0.8rem;"></i>
                                        </span>
                                        <input type="text" class="form-control" name="customer_name" id="customerName"
                                               placeholder="Type to search or enter new..." required autocomplete="off"
                                               style="padding-left:2rem;">
                                        <div id="customerSuggestionsBox"></div>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Email</label>
                                    <input type="email" class="form-control" name="customer_email" id="customerEmail" placeholder="customer@email.com">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Phone</label>
                                    <input type="text" class="form-control" name="customer_phone" id="customerPhone" placeholder="09XX-XXX-XXXX">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Address</label>
                                    <textarea class="form-control" name="customer_address" id="customerAddress" rows="2" placeholder="Street, City, Province"></textarea>
                                </div>
                            </div>
                        </div>

                        <!-- Payment card -->
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="bi bi-credit-card"></i> Payment Details</h5>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <label class="form-label">Payment Status</label>
                                    <select class="form-select" name="payment_status">
                                        <option value="pending">🕐 Pending</option>
                                        <option value="paid">✅ Paid</option>
                                        <option value="partial">⏳ Partial</option>
                                        <option value="cancelled">❌ Cancelled</option>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Payment Method</label>
                                    <select class="form-select" name="payment_method">
                                        <option value="">— Select method —</option>
                                        <option value="cash">💵 Cash</option>
                                        <option value="card">💳 Credit / Debit Card</option>
                                        <option value="bank_transfer">🏦 Bank Transfer</option>
                                        <option value="gcash">📱 GCash</option>
                                        <option value="paymaya">📱 PayMaya</option>
                                    </select>
                                </div>
                                <div class="row g-3">
                                    <div class="col-6">
                                        <label class="form-label">Tax Rate (%)</label>
                                        <input type="number" class="form-control" name="tax_rate" id="taxRate" value="0" min="0" max="100" step="0.01">
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label">Discount (₱)</label>
                                        <input type="number" class="form-control" name="discount" id="discount" value="0" min="0" step="0.01">
                                    </div>
                                </div>
                                <div class="mt-3">
                                    <label class="form-label">Notes</label>
                                    <textarea class="form-control" name="notes" rows="2" placeholder="Optional remarks..."></textarea>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ── RIGHT: Invoice Items ─────────────────────── -->
                    <div class="col-xl-7">
                        <div class="card h-100">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0"><i class="bi bi-cart3"></i> Invoice Items</h5>
                                <div class="d-flex gap-2">
                                    <button type="button" class="btn btn-outline-info btn-sm" data-bs-toggle="modal" data-bs-target="#scannerModal" id="openScannerBtn">
                                        <i class="bi bi-upc-scan"></i> Scan
                                    </button>
                                    <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addProductModal">
                                        <i class="bi bi-plus-circle"></i> Add
                                    </button>
                                </div>
                            </div>
                            <div class="card-body">

                                <!-- Item list -->
                                <div id="invoiceItems">
                                    <div class="empty-state" id="emptyState">
                                        <i class="bi bi-cart-x"></i>
                                        <p>No items yet</p>
                                        <small>Click "Add Product" to begin</small>
                                    </div>
                                </div>

                                <!-- Totals panel -->
                                <div id="totalsPanel" style="margin-top:1.5rem;display:none;">
                                    <div class="totals-row"><span>Subtotal</span><span class="amount" id="subtotalDisplay">₱0.00</span></div>
                                    <div class="totals-row" id="discountRow" style="display:none;"><span>Discount</span><span class="amount" style="color:var(--accent-rose);">−₱<span id="discountDisplay">0.00</span></span></div>
                                    <div class="totals-row" id="taxRow" style="display:none;"><span>Tax (<span id="taxRateDisplay">0</span>%)</span><span class="amount">₱<span id="taxDisplay">0.00</span></span></div>
                                    <div class="totals-row total-final"><span>Total</span><span class="amount" id="totalDisplay">₱0.00</span></div>
                                </div>
                            </div>
                            <div class="card-body pt-0">
                                <button type="submit" class="btn btn-primary w-100" id="submitBtn">
                                    <i class="bi bi-check-circle me-2"></i> Create Invoice
                                </button>
                                <a href="invoices.php" class="btn btn-outline-secondary w-100 mt-2">Cancel</a>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </main>
    </div>

    <!-- Add Product Modal -->
    <div class="modal fade" id="addProductModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-boxes me-2"></i>Select Product</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="position-relative mb-3">
                        <i class="bi bi-search" style="position:absolute;left:12px;top:50%;transform:translateY(-50%);color:var(--text-muted);"></i>
                        <input type="text" class="form-control" id="productSearch" placeholder="Search by name or category..." style="padding-left:2rem;">
                    </div>
                    <div id="productList" style="max-height:440px;overflow-y:auto;display:flex;flex-direction:column;gap:0.5rem;">
                        <?php foreach ($products as $product): ?>
                        <div class="product-item" data-product='<?php echo htmlspecialchars(json_encode($product), ENT_QUOTES); ?>'>
                            <div class="d-flex justify-content-between align-items-center">
                                <div style="flex:1;min-width:0;">
                                    <div style="font-weight:600;color:var(--text-primary);font-size:0.9rem;">
                                        <?php echo htmlspecialchars($product['product_name']); ?>
                                    </div>
                                    <small style="color:var(--text-muted);">
                                        <i class="bi bi-tag me-1"></i><?php echo htmlspecialchars($product['category_name'] ?? 'Uncategorized'); ?>
                                    </small>
                                </div>
                                <div class="d-flex align-items-center gap-3">
                                    <?php if ($product['stock_quantity'] > 0): ?>
                                    <span class="badge badge-soft-success"><?php echo $product['stock_quantity']; ?> in stock</span>
                                    <?php else: ?>
                                    <span class="badge badge-soft-danger">Out of stock</span>
                                    <?php endif; ?>
                                    <span style="font-weight:700;color:var(--accent-emerald);min-width:70px;text-align:right;">
                                        ₱<?php echo number_format($product['price'], 2); ?>
                                    </span>
                                    <button type="button" class="btn btn-primary btn-sm add-product-btn" style="white-space:nowrap;">
                                        <i class="bi bi-plus me-1"></i>Add
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

    <!-- Barcode Scanner Modal -->
    <div class="modal fade" id="scannerModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-upc-scan me-2"></i>Scan Barcode</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center">
                    <div id="reader" style="width: 100%; min-height: 300px;"></div>
                    <p class="text-muted mt-2 mb-0" id="scannerStatus">Initializing camera...</p>
                </div>
            </div>
        </div>
    </div>

    <?php include 'includes/chatbot.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/html5-qrcode"></script>
    <script src="js/chatbot.js"></script>
    <script src="js/ui-enhancements.js"></script>
    <script>
    /* ── Invoice Logic ─────────────────────────────────────────────── */
    let invoiceItems = [];

    /* ── Barcode Scanner Logic ─────────────────────────────────────── */
    let html5QrcodeScanner = null;

    const scannerModalEL = document.getElementById('scannerModal');
    scannerModalEL.addEventListener('shown.bs.modal', function () {
        if (!html5QrcodeScanner) {
            html5QrcodeScanner = new Html5QrcodeScanner(
                "reader",
                { fps: 10, qrbox: {width: 250, height: 150} },
                /* verbose= */ false
            );
            html5QrcodeScanner.render(onScanSuccess, onScanFailure);
        }
    });

    scannerModalEL.addEventListener('hidden.bs.modal', function () {
        if (html5QrcodeScanner) {
            html5QrcodeScanner.clear().catch(error => {
                console.error("Failed to clear scanner", error);
            });
            html5QrcodeScanner = null;
        }
    });

    let isScanning = false;
    function onScanSuccess(decodedText, decodedResult) {
        if (isScanning) return;
        isScanning = true;
        
        document.getElementById('scannerStatus').innerHTML = '<span class="text-success"><i class="bi bi-check-circle"></i> Found: ' + decodedText + '</span>';
        
        // Query API
        fetch('api/get_product_by_barcode.php?barcode=' + encodeURIComponent(decodedText))
            .then(response => response.json())
            .then(data => {
                if (data.success && data.product) {
                    processProductAdd(data.product);
                    
                    // Flash screen green or play a small sound? Just UI feedback is enough
                    const myModal = bootstrap.Modal.getInstance(scannerModalEL);
                    myModal.hide();
                } else {
                    document.getElementById('scannerStatus').innerHTML = '<span class="text-danger"><i class="bi bi-x-circle"></i> ' + (data.message || 'Product not found') + '</span>';
                }
                setTimeout(() => isScanning = false, 1500);
            })
            .catch(error => {
                document.getElementById('scannerStatus').innerHTML = '<span class="text-danger"><i class="bi bi-x-circle"></i> Network error</span>';
                setTimeout(() => isScanning = false, 1500);
            });
    }

    function onScanFailure(error) {
        // handle scan failure, typically better to just ignore it unless we want to log
    }

    // Extracted product adding logic
    function processProductAdd(product) {
        const existing = invoiceItems.find(i => i.product_id == product.id);
        if (existing) {
            if (existing.quantity >= product.stock_quantity) {
                alert('Cannot add more. Insufficient stock!');
                return;
            }
            existing.quantity++;
        } else {
            if (product.stock_quantity <= 0) {
                alert('Item is out of stock!');
                return;
            }
            invoiceItems.push({
                product_id: product.id,
                name: product.product_name,
                price: parseFloat(product.price),
                quantity: 1,
                stock: parseInt(product.stock_quantity)
            });
        }
        renderItems();
    }

    // Product modal search

    document.getElementById('productSearch').addEventListener('input', function () {
        const q = this.value.toLowerCase();
        document.querySelectorAll('.product-item').forEach(el => {
            el.style.display = el.textContent.toLowerCase().includes(q) ? '' : 'none';
        });
    });

    // Add product to invoice
    document.querySelectorAll('.add-product-btn').forEach(btn => {
        btn.addEventListener('click', function () {
            const p = JSON.parse(this.closest('.product-item').dataset.product);
            const existing = invoiceItems.find(i => i.product_id === p.id);
            if (existing) {
                existing.quantity++;
            } else {
                invoiceItems.push({
                    product_id: p.id,
                    name:  p.product_name,
                    price: parseFloat(p.price),
                    quantity: 1,
                    stock: parseInt(p.stock_quantity)
                });
            }
            renderItems();
            bootstrap.Modal.getInstance(document.getElementById('addProductModal')).hide();
            if (window.InvenAI) InvenAI.toast(p.product_name + ' added to invoice', 'success', 2500);
        });
    });

    function renderItems() {
        const container = document.getElementById('invoiceItems');
        const empty     = document.getElementById('emptyState');
        const panel     = document.getElementById('totalsPanel');

        if (invoiceItems.length === 0) {
            container.innerHTML = '';
            container.appendChild(empty);
            panel.style.display = 'none';
            return;
        }

        container.innerHTML = invoiceItems.map((item, idx) => {
            const isLow = item.stock < item.quantity;
            return `
            <div class="invoice-item-row">
                <div class="invoice-item-label">
                    ${item.name}
                    <small>${isLow
                        ? '<span style="color:var(--accent-rose);"><i class="bi bi-exclamation-triangle"></i> low stock</span>'
                        : `<span style="color:var(--text-muted);">Stock: ${item.stock}</span>`}
                    </small>
                </div>
                <div style="color:var(--text-muted);font-size:0.85rem;">₱${item.price.toFixed(2)}</div>
                <div>
                    <div class="input-group input-group-sm">
                        <button type="button" class="btn btn-outline-secondary" onclick="changeQty(${idx},-1)">−</button>
                        <input type="number" class="form-control text-center" value="${item.quantity}" min="1"
                               onchange="setQty(${idx},this.value)" style="min-width:50px;">
                        <button type="button" class="btn btn-outline-secondary" onclick="changeQty(${idx},1)">+</button>
                    </div>
                </div>
                <div style="font-weight:700;color:var(--accent-emerald);text-align:right;">₱${(item.price*item.quantity).toFixed(2)}</div>
                <button type="button" class="btn btn-icon" onclick="removeItem(${idx})" title="Remove" style="color:var(--accent-rose);">
                    <i class="bi bi-trash"></i>
                </button>
            </div>`;
        }).join('');

        panel.style.display = '';
        updateTotals();
    }

    function changeQty(idx, d)   { invoiceItems[idx].quantity = Math.max(1, invoiceItems[idx].quantity + d); renderItems(); }
    function setQty(idx, v)      { invoiceItems[idx].quantity = Math.max(1, parseInt(v) || 1); renderItems(); }
    function removeItem(idx)     { invoiceItems.splice(idx, 1); renderItems(); }

    function updateTotals() {
        const sub      = invoiceItems.reduce((s, i) => s + i.price * i.quantity, 0);
        const discount = parseFloat(document.getElementById('discount').value) || 0;
        const taxRate  = parseFloat(document.getElementById('taxRate').value)  || 0;
        const tax      = (sub - discount) * (taxRate / 100);
        const total    = sub - discount + tax;

        document.getElementById('subtotalDisplay').textContent = '₱' + sub.toFixed(2);
        document.getElementById('discountDisplay').textContent = discount.toFixed(2);
        document.getElementById('taxDisplay').textContent      = tax.toFixed(2);
        document.getElementById('taxRateDisplay').textContent  = taxRate;
        document.getElementById('totalDisplay').textContent    = '₱' + total.toFixed(2);

        document.getElementById('discountRow').style.display = discount > 0 ? '' : 'none';
        document.getElementById('taxRow').style.display      = taxRate  > 0 ? '' : 'none';
    }

    document.getElementById('taxRate').addEventListener('input', updateTotals);
    document.getElementById('discount').addEventListener('input', updateTotals);

    // Form submit
    document.getElementById('invoiceForm').addEventListener('submit', function (e) {
        if (invoiceItems.length === 0) {
            e.preventDefault();
            if (window.InvenAI) InvenAI.toast('Please add at least one product.', 'danger');
            return;
        }
        document.getElementById('itemsData').value = JSON.stringify(invoiceItems);
        const btn = document.getElementById('submitBtn');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Creating...';
    });

    /* ── Customer Autocomplete ─────────────────────────────────────── */
    const nameInput  = document.getElementById('customerName');
    const emailInput = document.getElementById('customerEmail');
    const phoneInput = document.getElementById('customerPhone');
    const addrInput  = document.getElementById('customerAddress');
    const sugBox     = document.getElementById('customerSuggestionsBox');
    let   sugTimeout = null;

    nameInput.addEventListener('input', function () {
        clearTimeout(sugTimeout);
        const q = this.value.trim();
        if (q.length === 0) { sugBox.style.display = 'none'; return; }
        sugTimeout = setTimeout(() => fetchSuggestions(q), 220);
    });

    function fetchSuggestions(q) {
        fetch('api/get_customers.php?q=' + encodeURIComponent(q))
            .then(r => r.json())
            .then(data => {
                if (!data.length) { sugBox.style.display = 'none'; return; }
                sugBox.innerHTML = data.map(c => `
                    <div class="customer-suggestion" data-customer='${JSON.stringify(c).replace(/'/g,"&#39;")}'>
                        <div class="cust-name">${c.name}</div>
                        <div class="cust-meta">${[c.email, c.phone].filter(Boolean).join(' · ') || 'No contact info'}</div>
                    </div>`).join('');
                sugBox.style.display = 'block';

                sugBox.querySelectorAll('.customer-suggestion').forEach(el => {
                    el.addEventListener('click', function () {
                        const c = JSON.parse(this.dataset.customer);
                        nameInput.value  = c.name    || '';
                        emailInput.value = c.email   || '';
                        phoneInput.value = c.phone   || '';
                        addrInput.value  = c.address || '';
                        sugBox.style.display = 'none';
                        if (window.InvenAI) InvenAI.toast('Customer loaded: ' + c.name, 'info', 2000);
                    });
                });
            })
            .catch(() => sugBox.style.display = 'none');
    }

    // Close on outside click
    document.addEventListener('click', e => {
        if (!nameInput.contains(e.target) && !sugBox.contains(e.target))
            sugBox.style.display = 'none';
    });
    nameInput.addEventListener('keydown', e => { if (e.key === 'Escape') sugBox.style.display = 'none'; });
    </script>
</body>
</html>
