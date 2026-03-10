<?php
require_once 'config/database.php';
require_once 'config/session.php';
requireLogin();

$database = new Database();
$db = $database->getConnection();

// Get dashboard statistics
$total_products = $db->query("SELECT COUNT(*) FROM products")->fetchColumn();
$low_stock = $db->query("SELECT COUNT(*) FROM products WHERE stock_quantity <= reorder_level AND stock_quantity > 0")->fetchColumn();
$out_of_stock = $db->query("SELECT COUNT(*) FROM products WHERE stock_quantity = 0")->fetchColumn();
$total_sales = $db->query("SELECT COALESCE(SUM(total_price), 0) FROM sales WHERE DATE(sale_date) = CURDATE()")->fetchColumn();

// Get recent sales
$recent_sales = $db->query("SELECT s.*, p.product_name FROM sales s 
    JOIN products p ON s.product_id = p.id 
    ORDER BY s.sale_date DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);

// Get low stock products
$low_stock_products = $db->query("SELECT * FROM products 
    WHERE stock_quantity <= reorder_level 
    ORDER BY stock_quantity ASC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);

// Get sales data for chart (last 7 days)
$sales_data = $db->query("SELECT DATE(sale_date) as date, SUM(total_price) as total 
    FROM sales 
    WHERE sale_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) 
    GROUP BY DATE(sale_date) 
    ORDER BY date")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Inventory System</title>
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
                    <h1 class="h2"><i class="bi bi-house-door"></i> Dashboard</h1>
                    <div>
                        <a href="reports.php?type=sales" class="btn btn-outline-primary btn-sm">
                            <i class="bi bi-file-earmark-text"></i> View Reports
                        </a>
                    </div>
                </div>

                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card text-white bg-primary">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="card-title mb-0">Total Products</h6>
                                        <h2 class="mb-0 mt-2"><?php echo $total_products; ?></h2>
                                    </div>
                                    <i class="bi bi-box-seam" style="font-size: 2.5rem; opacity: 0.3;"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-white bg-warning">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="card-title mb-0">Low Stock</h6>
                                        <h2 class="mb-0 mt-2"><?php echo $low_stock; ?></h2>
                                    </div>
                                    <i class="bi bi-exclamation-triangle" style="font-size: 2.5rem; opacity: 0.3;"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-white bg-danger">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="card-title mb-0">Out of Stock</h6>
                                        <h2 class="mb-0 mt-2"><?php echo $out_of_stock; ?></h2>
                                    </div>
                                    <i class="bi bi-x-circle" style="font-size: 2.5rem; opacity: 0.3;"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-white bg-success">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="card-title mb-0">Today's Sales</h6>
                                        <h2 class="mb-0 mt-2">$<?php echo number_format($total_sales, 2); ?></h2>
                                    </div>
                                    <i class="bi bi-currency-dollar" style="font-size: 2.5rem; opacity: 0.3;"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row mb-4">
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-header">Sales Analytics (Last 7 Days)</div>
                            <div class="card-body">
                                <canvas id="salesChart"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header">Low Stock Alerts</div>
                            <div class="card-body">
                                <ul class="list-group">
                                    <?php foreach ($low_stock_products as $product): ?>
                                        <li class="list-group-item d-flex justify-content-between">
                                            <span><?php echo htmlspecialchars($product['product_name']); ?></span>
                                            <span class="badge bg-warning"><?php echo $product['stock_quantity']; ?> left</span>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">Recent Transactions</div>
                    <div class="card-body">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th>Quantity</th>
                                    <th>Total Price</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_sales as $sale): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($sale['product_name']); ?></td>
                                        <td><?php echo $sale['quantity']; ?></td>
                                        <td>$<?php echo number_format($sale['total_price'], 2); ?></td>
                                        <td><?php echo date('M d, Y H:i', strtotime($sale['sale_date'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <?php include 'includes/chatbot.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script>
        // Sales chart
        const salesData = <?php echo json_encode($sales_data); ?>;
        const ctx = document.getElementById('salesChart').getContext('2d');
        
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: salesData.map(d => d.date),
                datasets: [{
                    label: 'Sales ($)',
                    data: salesData.map(d => d.total),
                    borderColor: 'rgb(75, 192, 192)',
                    tension: 0.1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true
            }
        });
    </script>
    <script src="js/chatbot.js"></script>
</body>
</html>
