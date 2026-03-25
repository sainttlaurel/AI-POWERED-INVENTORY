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
        INDEX idx_payment_status (payment_status),
        INDEX idx_created_at (created_at)
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
        INDEX idx_invoice_id (invoice_id),
        INDEX idx_product_id (product_id)
    )");
} catch (Exception $e) {
    error_log("Invoice tables creation error: " . $e->getMessage());
}

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'mark_paid') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        die('Invalid CSRF token');
    }
    
    $invoice_id = (int)$_POST['invoice_id'];
    
    try {
        $stmt = $db->prepare("UPDATE invoices SET payment_status = 'paid' WHERE id = ?");
        $stmt->execute([$invoice_id]);
        
        header("Location: invoices.php?success=" . urlencode("Invoice marked as paid successfully"));
        exit();
    } catch (Exception $e) {
        header("Location: invoices.php?error=" . urlencode("Failed to update invoice: " . $e->getMessage()));
        exit();
    }
}

// Get all invoices
$query = "SELECT i.*, u.username as created_by_name,
          (SELECT COUNT(*) FROM invoice_items WHERE invoice_id = i.id) as item_count
          FROM invoices i
          LEFT JOIN users u ON i.created_by = u.id
          ORDER BY i.created_at DESC";
$stmt = $db->query($query);
$invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoices - Inventory System</title>
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
                    <h1 class="h2"><i class="bi bi-receipt"></i> Invoices</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="create_invoice.php" class="btn btn-primary">
                            <i class="bi bi-plus-circle"></i> Create Invoice
                        </a>
                    </div>
                </div>

                <?php if (isset($_GET['success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <?php echo htmlspecialchars($_GET['success']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($_GET['error'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <?php echo htmlspecialchars($_GET['error']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Invoice #</th>
                                        <th>Customer</th>
                                        <th>Items</th>
                                        <th>Total</th>
                                        <th>Status</th>
                                        <th>Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($invoices as $invoice): ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($invoice['invoice_number']); ?></strong></td>
                                            <td><?php echo htmlspecialchars($invoice['customer_name']); ?></td>
                                            <td><?php echo $invoice['item_count']; ?> items</td>
                                            <td>₱<?php echo number_format($invoice['total_amount'], 2); ?></td>
                                            <td>
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
                                            </td>
                                            <td><?php echo date('M d, Y', strtotime($invoice['created_at'])); ?></td>
                                            <td>
                                                <div class="btn-group" role="group">
                                                    <a href="view_invoice.php?id=<?php echo $invoice['id']; ?>" class="btn btn-sm btn-info" title="View Invoice">
                                                        <i class="bi bi-eye"></i> View
                                                    </a>
                                                    <a href="print_invoice.php?id=<?php echo $invoice['id']; ?>" class="btn btn-sm btn-secondary" target="_blank" title="Print Invoice">
                                                        <i class="bi bi-printer"></i> Print
                                                    </a>
                                                    <?php if ($invoice['payment_status'] === 'pending' || $invoice['payment_status'] === 'partial'): ?>
                                                        <button type="button" class="btn btn-sm btn-success" onclick="markAsPaid(<?php echo $invoice['id']; ?>, '<?php echo htmlspecialchars($invoice['invoice_number']); ?>')" title="Mark as Paid">
                                                            <i class="bi bi-check-circle"></i> Done
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($invoices)): ?>
                                        <tr>
                                            <td colspan="7" class="text-center py-4">
                                                <i class="bi bi-inbox" style="font-size: 3rem; color: #ccc;"></i>
                                                <p class="mt-2">No invoices found. Create your first invoice!</p>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Mark as Paid Modal -->
    <div class="modal fade" id="markPaidModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" id="markPaidForm">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="action" value="mark_paid">
                    <input type="hidden" name="invoice_id" id="mark_paid_invoice_id">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="bi bi-check-circle text-success"></i> Mark Invoice as Paid
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p>Are you sure you want to mark invoice <strong id="mark_paid_invoice_number"></strong> as paid?</p>
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle"></i> This will update the payment status to "Paid" and cannot be undone.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="bi bi-x-lg"></i> Cancel
                        </button>
                        <button type="submit" class="btn btn-success">
                            <i class="bi bi-check-circle"></i> Mark as Paid
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function markAsPaid(invoiceId, invoiceNumber) {
            document.getElementById('mark_paid_invoice_id').value = invoiceId;
            document.getElementById('mark_paid_invoice_number').textContent = invoiceNumber;
            
            const modal = new bootstrap.Modal(document.getElementById('markPaidModal'));
            modal.show();
        }
    </script>
</body>
</html>
