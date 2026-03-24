<?php
require_once 'config/database.php';
require_once 'config/session.php';

requireLogin();

$database = new Database();
$db = $database->getConnection();

$invoice_id = $_GET['id'] ?? 0;

// Get invoice details
$stmt = $db->prepare("SELECT i.*, u.username as created_by_name FROM invoices i LEFT JOIN users u ON i.created_by = u.id WHERE i.id = ?");
$stmt->execute([$invoice_id]);
$invoice = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$invoice) {
    die("Invoice not found");
}

// Get invoice items
$stmt = $db->prepare("SELECT * FROM invoice_items WHERE invoice_id = ?");
$stmt->execute([$invoice_id]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice <?php echo htmlspecialchars($invoice['invoice_number']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        @media print {
            .no-print { display: none; }
            body { padding: 20px; }
        }
        .invoice-box {
            max-width: 800px;
            margin: auto;
            padding: 30px;
            border: 1px solid #eee;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.15);
            font-size: 16px;
            line-height: 24px;
            color: #555;
        }
        .invoice-header {
            border-bottom: 3px solid #007bff;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        .company-name {
            font-size: 32px;
            font-weight: bold;
            color: #007bff;
        }
        .invoice-title {
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 10px;
        }
        .table th {
            background-color: #f8f9fa;
        }
        .total-row {
            background-color: #e7f3ff;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="invoice-box">
        <div class="no-print text-end mb-3">
            <button onclick="window.print()" class="btn btn-primary">
                <i class="bi bi-printer"></i> Print Invoice
            </button>
            <button onclick="window.close()" class="btn btn-secondary">Close</button>
        </div>

        <div class="invoice-header">
            <div class="row">
                <div class="col-6">
                    <div class="company-name">Inventory System</div>
                    <div>Your Business Address</div>
                    <div>Phone: (123) 456-7890</div>
                    <div>Email: info@yourbusiness.com</div>
                </div>
                <div class="col-6 text-end">
                    <div class="invoice-title">INVOICE</div>
                    <div><strong>Invoice #:</strong> <?php echo htmlspecialchars($invoice['invoice_number']); ?></div>
                    <div><strong>Date:</strong> <?php echo date('F d, Y', strtotime($invoice['created_at'])); ?></div>
                    <div>
                        <strong>Status:</strong>
                        <span class="badge bg-<?php 
                            echo $invoice['payment_status'] === 'paid' ? 'success' : 
                                ($invoice['payment_status'] === 'pending' ? 'warning' : 'info'); 
                        ?>">
                            <?php echo ucfirst($invoice['payment_status']); ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <div class="row mb-4">
            <div class="col-6">
                <h5>Bill To:</h5>
                <strong><?php echo htmlspecialchars($invoice['customer_name']); ?></strong><br>
                <?php if ($invoice['customer_email']): ?>
                    <?php echo htmlspecialchars($invoice['customer_email']); ?><br>
                <?php endif; ?>
                <?php if ($invoice['customer_phone']): ?>
                    <?php echo htmlspecialchars($invoice['customer_phone']); ?><br>
                <?php endif; ?>
                <?php if ($invoice['customer_address']): ?>
                    <?php echo nl2br(htmlspecialchars($invoice['customer_address'])); ?>
                <?php endif; ?>
            </div>
            <?php if ($invoice['payment_method']): ?>
                <div class="col-6 text-end">
                    <h5>Payment Details:</h5>
                    <strong>Method:</strong> <?php echo ucfirst(str_replace('_', ' ', $invoice['payment_method'])); ?>
                </div>
            <?php endif; ?>
        </div>

        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>Product</th>
                    <th>Stock Status</th>
                    <th class="text-center">Qty</th>
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
                <tr class="total-row">
                    <td colspan="4" class="text-end"><strong>TOTAL:</strong></td>
                    <td class="text-end"><strong>₱<?php echo number_format($invoice['total_amount'], 2); ?></strong></td>
                </tr>
            </tfoot>
        </table>

        <?php if ($invoice['notes']): ?>
            <div class="mt-4">
                <strong>Notes:</strong>
                <p><?php echo nl2br(htmlspecialchars($invoice['notes'])); ?></p>
            </div>
        <?php endif; ?>

        <div class="mt-5 pt-4 border-top text-center text-muted">
            <p>Thank you for your business!</p>
            <small>This is a computer-generated invoice. Created by <?php echo htmlspecialchars($invoice['created_by_name']); ?></small>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
