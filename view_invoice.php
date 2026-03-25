<?php
require_once 'config/database.php';
require_once 'config/session.php';

requireLogin();

$database = new Database();
$db = $database->getConnection();

// Auto-create invoice tables if they don't exist (just in case)
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
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");
    
    $db->exec("CREATE TABLE IF NOT EXISTS invoice_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        invoice_id INT NOT NULL,
        product_id INT NOT NULL,
        product_name VARCHAR(255) NOT NULL,
        quantity INT NOT NULL,
        unit_price DECIMAL(10,2) NOT NULL,
        subtotal DECIMAL(12,2) NOT NULL,
        stock_status ENUM('in_stock','out_of_stock') DEFAULT 'in_stock'
    )");
} catch (Exception $e) {
    // Silently fail if tables already exist
}

$invoice_id = $_GET['id'] ?? 0;

// Get invoice details
$stmt = $db->prepare("SELECT i.*, u.username as created_by_name FROM invoices i LEFT JOIN users u ON i.created_by = u.id WHERE i.id = ?");
$stmt->execute([$invoice_id]);
$invoice = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$invoice) {
    header("Location: invoices.php?error=Invoice not found");
    exit();
}

// Get invoice items
$stmt = $db->prepare("SELECT ii.*, p.stock_quantity FROM invoice_items ii LEFT JOIN products p ON ii.product_id = p.id WHERE ii.invoice_id = ?");
$stmt->execute([$invoice_id]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice <?php echo htmlspecialchars($invoice['invoice_number']); ?> - Inventory System</title>
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
                    <h1 class="h2"><i class="bi bi-receipt"></i> Invoice Details</h1>
                    <div class="btn-toolbar">
                        <a href="print_invoice.php?id=<?php echo $invoice_id; ?>" class="btn btn-primary me-2" target="_blank">
                            <i class="bi bi-printer"></i> Print
                        </a>
                        <a href="invoices.php" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-left"></i> Back
                        </a>
                    </div>
                </div>

                <?php if (isset($_GET['success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <?php echo htmlspecialchars($_GET['success']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <div class="card mb-4">
                    <div class="card-body">
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <h3>Invoice #<?php echo htmlspecialchars($invoice['invoice_number']); ?></h3>
                                <p class="mb-1"><strong>Date:</strong> <?php echo date('F d, Y', strtotime($invoice['created_at'])); ?></p>
                                <p class="mb-1"><strong>Created by:</strong> <?php echo htmlspecialchars($invoice['created_by_name']); ?></p>
                                <p class="mb-1">
                                    <strong>Status:</strong>
                                    <?php
                                    $badge_class = [
                                        'pending' => 'warning',
                                        'paid' => 'success',
                                        'partial' => 'info',
                                        'cancelled' => 'danger'
                                    ];
                                    ?>
                                    <span class="badge bg-<?php echo $badge_class[$invoice['payment_status']]; ?>">
                                        <?php echo ucfirst($invoice['payment_status']); ?>
                                    </span>
                                </p>
                            </div>
                            <div class="col-md-6 text-md-end">
                                <h5>Customer Information</h5>
                                <p class="mb-1"><strong><?php echo htmlspecialchars($invoice['customer_name']); ?></strong></p>
                                <?php if ($invoice['customer_email']): ?>
                                    <p class="mb-1"><?php echo htmlspecialchars($invoice['customer_email']); ?></p>
                                <?php endif; ?>
                                <?php if ($invoice['customer_phone']): ?>
                                    <p class="mb-1"><?php echo htmlspecialchars($invoice['customer_phone']); ?></p>
                                <?php endif; ?>
                                <?php if ($invoice['customer_address']): ?>
                                    <p class="mb-1"><?php echo nl2br(htmlspecialchars($invoice['customer_address'])); ?></p>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead class="table-light">
                                    <tr>
                                        <th>Product</th>
                                        <th>Stock Status</th>
                                        <th class="text-center">Quantity</th>
                                        <th class="text-end">Unit Price</th>
                                        <th class="text-end">Subtotal</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($items as $item): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($item['product_name']); ?></td>
                                            <td>
                                                <?php if ($item['stock_status'] === 'in_stock'): ?>
                                                    <span class="badge bg-success">In Stock</span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger">Out of Stock</span>
                                                <?php endif; ?>
                                                <small class="text-muted">(Current: <?php echo $item['stock_quantity']; ?>)</small>
                                            </td>
                                            <td class="text-center"><?php echo $item['quantity']; ?></td>
                                            <td class="text-end">₱<?php echo number_format($item['unit_price'], 2); ?></td>
                                            <td class="text-end">₱<?php echo number_format($item['subtotal'], 2); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <td colspan="4" class="text-end"><strong>Subtotal:</strong></td>
                                        <td class="text-end">₱<?php echo number_format($invoice['subtotal'], 2); ?></td>
                                    </tr>
                                    <?php if ($invoice['discount_amount'] > 0): ?>
                                        <tr>
                                            <td colspan="4" class="text-end"><strong>Discount:</strong></td>
                                            <td class="text-end">-₱<?php echo number_format($invoice['discount_amount'], 2); ?></td>
                                        </tr>
                                    <?php endif; ?>
                                    <?php if ($invoice['tax_amount'] > 0): ?>
                                        <tr>
                                            <td colspan="4" class="text-end"><strong>Tax (<?php echo $invoice['tax_rate']; ?>%):</strong></td>
                                            <td class="text-end">₱<?php echo number_format($invoice['tax_amount'], 2); ?></td>
                                        </tr>
                                    <?php endif; ?>
                                    <tr class="table-primary">
                                        <td colspan="4" class="text-end"><strong>Total:</strong></td>
                                        <td class="text-end"><strong>₱<?php echo number_format($invoice['total_amount'], 2); ?></strong></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>

                        <?php if ($invoice['payment_method']): ?>
                            <p class="mb-1"><strong>Payment Method:</strong> <?php echo ucfirst(str_replace('_', ' ', $invoice['payment_method'])); ?></p>
                        <?php endif; ?>

                        <?php if ($invoice['notes']): ?>
                            <div class="mt-3">
                                <strong>Notes:</strong>
                                <p><?php echo nl2br(htmlspecialchars($invoice['notes'])); ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
