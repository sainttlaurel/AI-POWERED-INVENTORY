<?php
require_once 'config/database.php';
require_once 'config/session.php';
requireLogin();

$database = new Database();
$db = $database->getConnection();

$report_type = $_GET['type'] ?? 'sales';
$date_from = $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
$date_to = $_GET['date_to'] ?? date('Y-m-d');

$report_data = [];
$report_title = '';

if ($report_type === 'sales') {
    $report_title = 'Sales Report';
    $query = "SELECT s.*, p.product_name, p.barcode FROM sales s 
              JOIN products p ON s.product_id = p.id 
              WHERE DATE(s.sale_date) BETWEEN :from AND :to 
              ORDER BY s.sale_date DESC";
    $stmt = $db->prepare($query);
    $stmt->execute([':from' => $date_from, ':to' => $date_to]);
    $report_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $total_sales = array_sum(array_column($report_data, 'total_price'));
    $total_quantity = array_sum(array_column($report_data, 'quantity'));
} elseif ($report_type === 'stock') {
    $report_title = 'Stock Report';
    $query = "SELECT p.*, c.name as category_name, s.name as supplier_name 
              FROM products p 
              LEFT JOIN categories c ON p.category_id = c.id 
              LEFT JOIN suppliers s ON p.supplier_id = s.id 
              ORDER BY p.product_name";
    $report_data = $db->query($query)->fetchAll(PDO::FETCH_ASSOC);
    
    $total_stock_value = 0;
    foreach ($report_data as $item) {
        $total_stock_value += $item['price'] * $item['stock_quantity'];
    }
} elseif ($report_type === 'low_stock') {
    $report_title = 'Low Stock Report';
    $query = "SELECT p.*, c.name as category_name, s.name as supplier_name 
              FROM products p 
              LEFT JOIN categories c ON p.category_id = c.id 
              LEFT JOIN suppliers s ON p.supplier_id = s.id 
              WHERE p.stock_quantity <= p.reorder_level 
              ORDER BY p.stock_quantity ASC";
    $report_data = $db->query($query)->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $report_title; ?> - Inventory System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="no-print">
        <?php include 'includes/navbar.php'; ?>
    </div>
    
    <div class="container-fluid">
        <div class="sidebar-overlay"></div>
        <div class="no-print">
            <?php include 'includes/sidebar.php'; ?>
        </div>
        
        <main>
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom no-print">
                    <h1 class="h2"><i class="bi bi-file-earmark-text"></i> Reports</h1>
                    <div>
                        <button onclick="window.print()" class="btn btn-print">
                            <i class="bi bi-printer"></i> Print Report
                        </button>
                        <button onclick="exportToCSV()" class="btn btn-success">
                            <i class="bi bi-file-earmark-spreadsheet"></i> Export CSV
                        </button>
                    </div>
                </div>

                <div class="card mb-3 no-print">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label">Report Type</label>
                                <select name="type" class="form-select" onchange="this.form.submit()">
                                    <option value="sales" <?php echo $report_type === 'sales' ? 'selected' : ''; ?>>Sales Report</option>
                                    <option value="stock" <?php echo $report_type === 'stock' ? 'selected' : ''; ?>>Stock Report</option>
                                    <option value="low_stock" <?php echo $report_type === 'low_stock' ? 'selected' : ''; ?>>Low Stock Report</option>
                                </select>
                            </div>
                            <?php if ($report_type === 'sales'): ?>
                            <div class="col-md-3">
                                <label class="form-label">From Date</label>
                                <input type="date" name="date_from" class="form-control" value="<?php echo $date_from; ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">To Date</label>
                                <input type="date" name="date_to" class="form-control" value="<?php echo $date_to; ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">&nbsp;</label>
                                <button type="submit" class="btn btn-primary w-100">Generate</button>
                            </div>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h3 class="mb-0"><?php echo $report_title; ?></h3>
                        <small class="text-muted">Generated on <?php echo date('F d, Y H:i:s'); ?></small>
                        <?php if ($report_type === 'sales'): ?>
                            <br><small class="text-muted">Period: <?php echo date('M d, Y', strtotime($date_from)); ?> - <?php echo date('M d, Y', strtotime($date_to)); ?></small>
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <?php if ($report_type === 'sales'): ?>
                            <table class="table table-striped" id="reportTable">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Product</th>
                                        <th>Barcode</th>
                                        <th>Quantity</th>
                                        <th>Total Price</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($report_data as $row): ?>
                                        <tr>
                                            <td><?php echo date('M d, Y H:i', strtotime($row['sale_date'])); ?></td>
                                            <td><?php echo htmlspecialchars($row['product_name']); ?></td>
                                            <td><?php echo htmlspecialchars($row['barcode']); ?></td>
                                            <td><?php echo $row['quantity']; ?></td>
                                            <td>₱<?php echo number_format($row['total_price'], 2); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot>
                                    <tr class="fw-bold">
                                        <td colspan="3">TOTAL</td>
                                        <td><?php echo $total_quantity; ?></td>
                                        <td>₱<?php echo number_format($total_sales, 2); ?></td>
                                    </tr>
                                </tfoot>
                            </table>
                        <?php elseif ($report_type === 'stock'): ?>
                            <table class="table table-striped" id="reportTable">
                                <thead>
                                    <tr>
                                        <th>Product Name</th>
                                        <th>Category</th>
                                        <th>Supplier</th>
                                        <th>Price</th>
                                        <th>Stock Qty</th>
                                        <th>Reorder Level</th>
                                        <th>Stock Value</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($report_data as $row): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($row['product_name']); ?></td>
                                            <td><?php echo htmlspecialchars($row['category_name']); ?></td>
                                            <td><?php echo htmlspecialchars($row['supplier_name']); ?></td>
                                            <td>₱<?php echo number_format($row['price'], 2); ?></td>
                                            <td><?php echo $row['stock_quantity']; ?></td>
                                            <td><?php echo $row['reorder_level']; ?></td>
                                            <td>₱<?php echo number_format($row['price'] * $row['stock_quantity'], 2); ?></td>
                                            <td>
                                                <?php if ($row['stock_quantity'] == 0): ?>
                                                    <span class="badge bg-danger">Out of Stock</span>
                                                <?php elseif ($row['stock_quantity'] <= $row['reorder_level']): ?>
                                                    <span class="badge bg-warning">Low Stock</span>
                                                <?php else: ?>
                                                    <span class="badge bg-success">In Stock</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot>
                                    <tr class="fw-bold">
                                        <td colspan="6">TOTAL STOCK VALUE</td>
                                        <td colspan="2">₱<?php echo number_format($total_stock_value, 2); ?></td>
                                    </tr>
                                </tfoot>
                            </table>
                        <?php elseif ($report_type === 'low_stock'): ?>
                            <table class="table table-striped" id="reportTable">
                                <thead>
                                    <tr>
                                        <th>Product Name</th>
                                        <th>Category</th>
                                        <th>Supplier</th>
                                        <th>Current Stock</th>
                                        <th>Reorder Level</th>
                                        <th>Shortage</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($report_data as $row): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($row['product_name']); ?></td>
                                            <td><?php echo htmlspecialchars($row['category_name']); ?></td>
                                            <td><?php echo htmlspecialchars($row['supplier_name']); ?></td>
                                            <td><?php echo $row['stock_quantity']; ?></td>
                                            <td><?php echo $row['reorder_level']; ?></td>
                                            <td class="text-danger"><?php echo $row['reorder_level'] - $row['stock_quantity']; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>
        </main>
    </div>

    <div class="no-print">
        <?php include 'includes/chatbot.php'; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function exportToCSV() {
            const table = document.getElementById('reportTable');
            let csv = [];
            
            for (let row of table.rows) {
                let cols = [];
                for (let cell of row.cells) {
                    cols.push('"' + cell.innerText.replace(/"/g, '""') + '"');
                }
                csv.push(cols.join(','));
            }
            
            const csvContent = csv.join('\n');
            const blob = new Blob([csvContent], { type: 'text/csv' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = '<?php echo $report_type; ?>_report_<?php echo date('Y-m-d'); ?>.csv';
            a.click();
            window.URL.revokeObjectURL(url);
        }
    </script>
    <script src="js/chatbot.js"></script>
    <script src="js/mobile.js?v=<?php echo time(); ?>"></script>
</body>
</html>
