<?php
require_once 'config/database.php';
require_once 'config/session.php';
requireLogin();

$db = (new Database())->getConnection();

// Create tables if not exist
try {
    $db->exec("CREATE TABLE IF NOT EXISTS invoices (id INT AUTO_INCREMENT PRIMARY KEY, invoice_number VARCHAR(50) NOT NULL UNIQUE, customer_name VARCHAR(255) NOT NULL, customer_email VARCHAR(255) DEFAULT NULL, customer_phone VARCHAR(20) DEFAULT NULL, customer_address TEXT DEFAULT NULL, subtotal DECIMAL(12,2) NOT NULL DEFAULT 0.00, tax_rate DECIMAL(5,2) NOT NULL DEFAULT 0.00, tax_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00, discount_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00, total_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00, payment_status ENUM('pending','paid','partial','cancelled') DEFAULT 'pending', payment_method VARCHAR(50) DEFAULT NULL, notes TEXT DEFAULT NULL, created_by INT DEFAULT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP)");
    $db->exec("CREATE TABLE IF NOT EXISTS invoice_items (id INT AUTO_INCREMENT PRIMARY KEY, invoice_id INT NOT NULL, product_id INT NOT NULL, product_name VARCHAR(255) NOT NULL, quantity INT NOT NULL, unit_price DECIMAL(10,2) NOT NULL, subtotal DECIMAL(12,2) NOT NULL, stock_status ENUM('in_stock','out_of_stock') DEFAULT 'in_stock')");
} catch (Exception $e) {}

$invoice_id = (int)($_GET['id'] ?? 0);
$stmt = $db->prepare("SELECT i.*, u.username as created_by_name FROM invoices i LEFT JOIN users u ON i.created_by = u.id WHERE i.id = ?");
$stmt->execute([$invoice_id]);
$invoice = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$invoice) {
    header("Location: invoices.php?error=Invoice not found");
    exit();
}

$stmt = $db->prepare("SELECT ii.*, p.stock_quantity FROM invoice_items ii LEFT JOIN products p ON ii.product_id = p.id WHERE ii.invoice_id = ?");
$stmt->execute([$invoice_id]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

$badge_map = ['pending'=>'amber','paid'=>'emerald','partial'=>'cyan','cancelled'=>'rose'];
$badge     = $badge_map[$invoice['payment_status']] ?? 'primary';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php
$page_title = 'Invoice ' . htmlspecialchars($invoice['invoice_number']) . ' — InvenAI';
$extra_head = '<style>
.inv-card { background:var(--bg-card); border:1px solid var(--border-subtle); border-radius:var(--border-radius-lg); }
.inv-header { background:linear-gradient(135deg,rgba(99,102,241,0.12) 0%,rgba(6,182,212,0.05) 100%); border-bottom:1px solid var(--border-subtle); padding:1.5rem 2rem; border-radius:var(--border-radius-lg) var(--border-radius-lg) 0 0; }
.inv-brand { font-size:1.6rem; font-weight:800; background:var(--grad-primary); -webkit-background-clip:text; -webkit-text-fill-color:transparent; }
.inv-section-title { font-size:0.7rem; text-transform:uppercase; letter-spacing:0.1em; color:var(--text-muted); margin-bottom:0.3rem; }
.inv-table th { background:rgba(255,255,255,0.04)!important; font-size:0.78rem; text-transform:uppercase; letter-spacing:0.05em; color:var(--text-muted); font-weight:600; }
.inv-table td { vertical-align:middle; color:var(--text-secondary); font-size:0.875rem; }
.totals-section { background:rgba(255,255,255,0.02); border-top:1px solid var(--border-subtle); padding:1.25rem 2rem; }
.total-line { display:flex; justify-content:space-between; padding:0.3rem 0; color:var(--text-secondary); font-size:0.9rem; }
.total-line.grand { padding-top:0.75rem; border-top:2px solid var(--border-default); font-size:1.1rem; font-weight:700; color:var(--text-primary); }
.total-line .val { color:var(--text-primary); font-variant-numeric:tabular-nums; }
.total-line.grand .val { color:var(--accent-emerald); font-size:1.25rem; }
/* PDF-ready invoice (hidden in view, shown when printing/PDF) */
@media print {
    .sidebar,.navbar,.chatbot-widget-modern,.no-print { display:none!important; }
    main { margin:0!important; padding:0!important; }
    .inv-card { box-shadow:none; border:1px solid #ddd; }
    body { background:white!important; color:black!important; }
    .inv-brand { -webkit-text-fill-color:#6366f1!important; }
}
</style>';
include 'includes/head.php';
?>
</head>
<body>
    <?php include 'includes/navbar.php'; ?>

    <div class="container-fluid">
        <?php include 'includes/sidebar.php'; ?>

        <main id="mainContent">

            <!-- Toolbar -->
            <div class="d-flex justify-content-between flex-wrap gap-2 align-items-center mb-4 no-print">
                <div>
                    <h1 style="font-size:1.4rem;font-weight:700;color:var(--text-primary);margin:0;">
                        <i class="bi bi-receipt"></i> Invoice Details
                    </h1>
                    <small style="color:var(--text-muted);"><?php echo htmlspecialchars($invoice['invoice_number']); ?></small>
                </div>
                <div class="d-flex gap-2 flex-wrap">
                    <button onclick="downloadPDF()" class="btn btn-primary btn-sm">
                        <i class="bi bi-file-earmark-pdf me-1"></i> Download PDF
                    </button>
                    <a href="print_invoice.php?id=<?php echo $invoice_id; ?>" target="_blank" class="btn btn-outline-secondary btn-sm" title="Print A4">
                        <i class="bi bi-printer"></i> A4
                    </a>
                    <a href="print_receipt.php?id=<?php echo $invoice_id; ?>" target="_blank" class="btn btn-outline-secondary btn-sm" title="Thermal Receipt">
                        <i class="bi bi-receipt"></i> Thermal
                    </a>
                    <a href="invoices.php" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-arrow-left me-1"></i> Back
                    </a>
                </div>
            </div>

            <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show no-print">
                <i class="bi bi-check-circle me-2"></i><?php echo htmlspecialchars($_GET['success']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <!-- Invoice body (used for PDF render too) -->
            <div class="inv-card mb-4" id="invoiceDocument">

                <!-- Header -->
                <div class="inv-header">
                    <div class="d-flex justify-content-between flex-wrap gap-3">
                        <div>
                            <div class="inv-brand"><i class="bi bi-boxes"></i> InvenAI</div>
                            <div style="color:var(--text-muted);font-size:0.82rem;margin-top:2px;">AI-Powered Inventory System</div>
                        </div>
                        <div class="text-end">
                            <div style="font-size:1.5rem;font-weight:800;color:var(--text-primary);">
                                <?php echo htmlspecialchars($invoice['invoice_number']); ?>
                            </div>
                            <span class="badge badge-soft-<?php echo $badge; ?>" style="font-size:0.8rem;">
                                <?php echo ucfirst($invoice['payment_status']); ?>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Customer & meta -->
                <div class="d-flex flex-wrap gap-4 p-4 border-bottom" style="border-color:var(--border-subtle)!important;">
                    <div style="flex:1;min-width:180px;">
                        <div class="inv-section-title">Bill To</div>
                        <div style="font-weight:700;color:var(--text-primary);"><?php echo htmlspecialchars($invoice['customer_name']); ?></div>
                        <?php if ($invoice['customer_email']): ?><div style="color:var(--text-muted);font-size:0.85rem;"><?php echo htmlspecialchars($invoice['customer_email']); ?></div><?php endif; ?>
                        <?php if ($invoice['customer_phone']): ?><div style="color:var(--text-muted);font-size:0.85rem;"><?php echo htmlspecialchars($invoice['customer_phone']); ?></div><?php endif; ?>
                        <?php if ($invoice['customer_address']): ?><div style="color:var(--text-muted);font-size:0.85rem;margin-top:2px;"><?php echo nl2br(htmlspecialchars($invoice['customer_address'])); ?></div><?php endif; ?>
                    </div>
                    <div style="min-width:160px;">
                        <div class="inv-section-title">Invoice Date</div>
                        <div style="color:var(--text-primary);font-weight:600;"><?php echo date('F d, Y', strtotime($invoice['created_at'])); ?></div>
                    </div>
                    <div style="min-width:160px;">
                        <div class="inv-section-title">Created By</div>
                        <div style="color:var(--text-primary);font-weight:600;"><?php echo htmlspecialchars($invoice['created_by_name'] ?? 'System'); ?></div>
                    </div>
                    <?php if ($invoice['payment_method']): ?>
                    <div style="min-width:140px;">
                        <div class="inv-section-title">Payment Method</div>
                        <div style="color:var(--text-primary);font-weight:600;"><?php echo ucwords(str_replace('_',' ',$invoice['payment_method'])); ?></div>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Items table -->
                <div class="table-responsive px-4 py-3">
                    <table class="table inv-table mb-0">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Stock Status</th>
                                <th class="text-center" style="width:80px;">Qty</th>
                                <th class="text-end" style="width:110px;">Unit Price</th>
                                <th class="text-end" style="width:110px;">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($items as $item): ?>
                            <tr>
                                <td style="font-weight:600;color:var(--text-primary);"><?php echo htmlspecialchars($item['product_name']); ?></td>
                                <td>
                                    <?php if ($item['stock_status'] === 'in_stock'): ?>
                                    <span class="badge badge-soft-success">In Stock</span>
                                    <?php else: ?>
                                    <span class="badge badge-soft-danger">Out of Stock</span>
                                    <?php endif; ?>
                                    <small style="color:var(--text-muted);margin-left:4px;">(Now: <?php echo $item['stock_quantity']; ?>)</small>
                                </td>
                                <td class="text-center"><?php echo $item['quantity']; ?></td>
                                <td class="text-end">₱<?php echo number_format($item['unit_price'], 2); ?></td>
                                <td class="text-end" style="font-weight:600;color:var(--accent-emerald);">₱<?php echo number_format($item['subtotal'], 2); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Totals -->
                <div class="totals-section">
                    <div style="max-width:320px;margin-left:auto;">
                        <div class="total-line"><span>Subtotal</span><span class="val">₱<?php echo number_format($invoice['subtotal'], 2); ?></span></div>
                        <?php if ($invoice['discount_amount'] > 0): ?>
                        <div class="total-line"><span>Discount</span><span class="val" style="color:var(--accent-rose);">−₱<?php echo number_format($invoice['discount_amount'], 2); ?></span></div>
                        <?php endif; ?>
                        <?php if ($invoice['tax_amount'] > 0): ?>
                        <div class="total-line"><span>Tax (<?php echo $invoice['tax_rate']; ?>%)</span><span class="val">₱<?php echo number_format($invoice['tax_amount'], 2); ?></span></div>
                        <?php endif; ?>
                        <div class="total-line grand"><span>Total</span><span class="val">₱<?php echo number_format($invoice['total_amount'], 2); ?></span></div>
                    </div>
                </div>

                <!-- Notes -->
                <?php if ($invoice['notes']): ?>
                <div style="padding:1rem 2rem;border-top:1px solid var(--border-subtle);">
                    <div class="inv-section-title">Notes</div>
                    <div style="color:var(--text-secondary);font-size:0.875rem;"><?php echo nl2br(htmlspecialchars($invoice['notes'])); ?></div>
                </div>
                <?php endif; ?>

                <!-- Footer -->
                <div style="padding:1rem 2rem;border-top:1px solid var(--border-subtle);text-align:center;color:var(--text-muted);font-size:0.78rem;">
                    Thank you for your business! — Generated by InvenAI
                </div>
            </div>

        </main>
    </div>

    <?php include 'includes/chatbot.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- jsPDF + html2canvas for PDF download -->
    <script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/jspdf@2.5.1/dist/jspdf.umd.min.js"></script>
    <script src="js/chatbot.js"></script>
    <script src="js/ui-enhancements.js"></script>
    <script>
    async function downloadPDF() {
        const btn = document.querySelector('[onclick="downloadPDF()"]');
        const orig = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Generating...';

        try {
            const el = document.getElementById('invoiceDocument');
            const canvas = await html2canvas(el, {
                scale: 2,
                backgroundColor: '#131b2e',
                useCORS: true,
                logging: false,
            });

            const { jsPDF } = window.jspdf;
            const pdf  = new jsPDF({ unit: 'mm', format: 'a4', orientation: 'portrait' });
            const imgW = 210; // A4 width in mm
            const imgH = (canvas.height * imgW) / canvas.width;
            const imgData = canvas.toDataURL('image/png');

            // If taller than A4, split across pages
            let y = 0;
            const pageH = 297;
            while (y < imgH) {
                pdf.addImage(imgData, 'PNG', 0, -y, imgW, imgH);
                y += pageH;
                if (y < imgH) pdf.addPage();
            }

            pdf.save('<?php echo htmlspecialchars($invoice["invoice_number"]); ?>.pdf');
            if (window.InvenAI) InvenAI.toast('PDF downloaded!', 'success');
        } catch (err) {
            console.error(err);
            if (window.InvenAI) InvenAI.toast('PDF generation failed. Try printing instead.', 'danger');
        } finally {
            btn.disabled = false;
            btn.innerHTML = orig;
        }
    }
    </script>
</body>
</html>
