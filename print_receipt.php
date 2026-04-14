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
    <title>Receipt_<?php echo htmlspecialchars($invoice['invoice_number']); ?></title>
    <style>
        body {
            margin: 0;
            padding: 0;
            font-family: 'Courier New', Courier, monospace; /* Ideal for thermal printers */
            font-size: 12px;
            color: #000;
            background-color: #fff;
        }

        .ticket {
            width: 80mm;
            max-width: 80mm;
            padding: 10px;
            margin: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        td, th, tr, table {
            border-top: 1px dotted #000;
            border-bottom: 1px dotted #000;
        }
        
        table.no-border, table.no-border td, table.no-border th, table.no-border tr {
            border: none;
        }

        th { text-align: left; padding: 4px 0; }
        td { padding: 4px 0; }

        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .text-left { text-align: left; }

        .bold { font-weight: bold; }
        .fs-large { font-size: 16px; margin-bottom: 5px; }

        .divider {
            border-top: 1px dashed #000;
            margin: 10px 0;
        }

        @media print {
            .hide-print { display: none !important; }
            @page { margin: 0; }
            body { margin: 0cm; }
            .ticket { padding: 0 5px; width: 100%; max-width: 100%; }
        }
    </style>
</head>
<body onload="window.print()">
    <div class="hide-print" style="padding:15px; text-align:center; background:#f0f0f0; margin-bottom:15px;">
        <button onclick="window.print()" style="padding:10px 20px; font-weight:bold;">Print Receipt</button>
        <button onclick="window.close()" style="padding:10px 20px;">Close Window</button>
    </div>

    <div class="ticket">
        <div class="text-center">
            <div class="bold fs-large">INVENAI SYSTEM</div>
            <div>Store Address or Tagline</div>
            <div>Tel: +63 9XX XXX XXXX</div>
            <div class="divider"></div>
            <div>RECEIPT</div>
        </div>

        <table class="no-border" style="margin-bottom: 10px; margin-top: 10px;">
            <tr>
                <td class="text-left"><strong>No:</strong> <?php echo htmlspecialchars($invoice['invoice_number']); ?></td>
                <td class="text-right"><?php echo date('Y-m-d H:i', strtotime($invoice['created_at'])); ?></td>
            </tr>
            <tr>
                <td class="text-left"><strong>Customer:</strong> <?php echo htmlspecialchars($invoice['customer_name'] ?? 'Walk-in'); ?></td>
                <td class="text-right"><strong>Cashier:</strong> <?php echo htmlspecialchars($invoice['created_by_name'] ?? 'System'); ?></td>
            </tr>
        </table>

        <table>
            <thead>
                <tr>
                    <th class="text-left">Item</th>
                    <th class="text-center">Qty</th>
                    <th class="text-right">Total</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $item): ?>
                    <tr>
                        <td class="text-left"><?php echo htmlspecialchars($item['product_name']); ?><br><small>@<?php echo number_format($item['unit_price'], 2); ?></small></td>
                        <td class="text-center"><?php echo $item['quantity']; ?></td>
                        <td class="text-right"><?php echo number_format($item['subtotal'], 2); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <table class="no-border" style="margin-top:10px;">
            <tr>
                <td class="text-left">Subtotal</td>
                <td class="text-right"><?php echo number_format($invoice['subtotal'], 2); ?></td>
            </tr>
            <?php if ($invoice['discount_amount'] > 0): ?>
            <tr>
                <td class="text-left">Discount</td>
                <td class="text-right">-<?php echo number_format($invoice['discount_amount'], 2); ?></td>
            </tr>
            <?php endif; ?>
            <?php if ($invoice['tax_amount'] > 0): ?>
            <tr>
                <td class="text-left">Tax (<?php echo $invoice['tax_rate']; ?>%)</td>
                <td class="text-right"><?php echo number_format($invoice['tax_amount'], 2); ?></td>
            </tr>
            <?php endif; ?>
            <tr>
                <td class="text-left bold fs-large">Total</td>
                <td class="text-right bold fs-large"><?php echo number_format($invoice['total_amount'], 2); ?></td>
            </tr>
            <tr>
                <td class="text-left">Method</td>
                <td class="text-right"><?php echo ucfirst(str_replace('_', ' ', $invoice['payment_method'])); ?></td>
            </tr>
        </table>

        <div class="divider"></div>
        
        <div class="text-center" style="margin-top: 10px;">
            <p>Thank you for your business!</p>
            <p style="font-size: 10px;">Powered by InvenAI</p>
            <br>
            <p style="font-size: 10px;"><?php echo date('Y-m-d H:i:s'); ?></p>
        </div>
    </div>
</body>
</html>
