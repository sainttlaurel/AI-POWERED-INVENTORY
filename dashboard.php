<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'config/database.php';
require_once 'config/session.php';

requireLogin();

$db = new Database();
$conn = $db->getConnection();

// Get dashboard stats
$total_products = $conn->query("SELECT COUNT(*) FROM products")->fetchColumn();

// Count low stock and out of stock
$low_stock = 0;
$out_of_stock = 0;
$products_result = $conn->query("SELECT stock_quantity, reorder_level FROM products");
$all_products = $products_result->fetchAll(PDO::FETCH_ASSOC);

foreach ($all_products as $product) {
    if ($product['stock_quantity'] == 0) {
        $out_of_stock++;
    } elseif ($product['stock_quantity'] <= $product['reorder_level']) {
        $low_stock++;
    }
}

// Today's sales
$today_sales = 0;
$recent_sales = [];

// Low stock products (limit 5)
$low_stock_products = $conn->query("
    SELECT * FROM products 
    WHERE stock_quantity <= reorder_level 
    ORDER BY stock_quantity ASC 
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

// Weekly sales (empty until sales table is ready)
$weekly_sales = [];
$salesData = [];

// Monthly sales (last 6 months)
$monthly_sales = [];
for ($i = 5; $i >= 0; $i--) {
    $monthly_sales[] = [
        'month' => date('M Y', strtotime("-$i months")),
        'total' => 0
    ];
}
$monthlyData = $monthly_sales;

// Categories with product counts
$categories_data = $conn->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
$products_data = $conn->query("SELECT category_id, price, stock_quantity FROM products")->fetchAll(PDO::FETCH_ASSOC);

$categories = [];
foreach ($categories_data as $cat) {
    $count = 0;
    $value = 0;
    foreach ($products_data as $product) {
        if ($product['category_id'] == $cat['id']) {
            $count++;
            $value += $product['price'] * $product['stock_quantity'];
        }
    }
    $categories[] = [
        'name'  => $cat['name'],
        'count' => $count,
        'value' => $value
    ];
}

usort($categories, function($a, $b) {
    return $b['count'] - $a['count'];
});
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Inventory Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="css/style.css">
    <style>
        .table { table-layout: auto !important; }
        .table th, .table td {
            animation: none !important;
            transform: none !important;
            transition: none !important;
        }
        .table tbody tr { animation: none !important; animation-delay: 0s !important; }
        .table tbody tr::before { display: none !important; }
        .table-responsive { overflow-x: auto; }
        .table .badge { display: inline-block; white-space: nowrap; }
        .dashboard-card {
            transition: all 0.3s ease;
            border: none;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .dashboard-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(0,0,0,0.15);
        }
        .chart-container { position: relative; height: 200px; }
        .card.text-white:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.2);
        }
        .table tbody tr:hover {
            background: linear-gradient(135deg, rgba(59,130,246,0.05) 0%, rgba(37,99,235,0.05) 100%) !important;
            transform: translateX(3px);
            transition: all 0.3s ease;
        }
        .badge { font-size: 0.75em; padding: 0.35em 0.65em; }
        .empty-state { padding: 2rem; text-align: center; color: #6c757d; }
        .empty-state i { font-size: 3rem; margin-bottom: 1rem; opacity: 0.5; }
    </style>
</head>
<body>
    <?php include 'includes/navbar.php'; ?>

    <div class="container-fluid">
        <div class="sidebar-overlay"></div>
        <?php include 'includes/sidebar.php'; ?>

        <main>
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2"><i class="bi bi-speedometer2"></i> Dashboard</h1>
                <div class="d-flex gap-2 align-items-center">
                    <button onclick="window.print()" class="btn btn-outline-secondary">
                        <i class="bi bi-printer me-2"></i> Print
                    </button>
                    <button onclick="exportDashboard()" class="btn btn-success">
                        <i class="bi bi-file-earmark-spreadsheet me-2"></i> Export
                    </button>
                    <a href="reports.php" class="btn btn-primary">
                        <i class="bi bi-graph-up me-2"></i> View Reports
                    </a>
                </div>
            </div>

            <!-- Main Stats -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card text-white bg-primary">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-title mb-0">Total Products</h6>
                                    <h2 class="mb-0 mt-2"><?php echo $total_products; ?></h2>
                                    <small class="opacity-75">In inventory</small>
                                </div>
                                <div class="fs-1 opacity-50"><i class="bi bi-box-seam"></i></div>
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
                                    <small class="opacity-75">Need reorder</small>
                                </div>
                                <div class="fs-1 opacity-50"><i class="bi bi-exclamation-triangle"></i></div>
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
                                    <small class="opacity-75">Critical items</small>
                                </div>
                                <div class="fs-1 opacity-50"><i class="bi bi-x-circle"></i></div>
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
                                    <h2 class="mb-0 mt-2">₱<?php echo number_format($today_sales, 2); ?></h2>
                                    <small class="opacity-75"><?php echo date('M d, Y'); ?></small>
                                </div>
                                <div class="fs-1 opacity-50"><i class="bi bi-currency-dollar"></i></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="bi bi-graph-up"></i> Sales Trend (Last 7 Days)</h5>
                        </div>
                        <div class="card-body">
                            <canvas id="salesChart" height="200"></canvas>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="bi bi-pie-chart"></i> Category Distribution</h5>
                        </div>
                        <div class="card-body">
                            <canvas id="categoryChart" height="200"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="card dashboard-card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="bi bi-clock-history"></i> Recent Sales</h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($recent_sales)): ?>
                                <div class="empty-state">
                                    <i class="bi bi-cart-x"></i>
                                    <p class="mt-2">No recent sales found</p>
                                    <small>Make a sale to see data here</small>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-sm table-hover">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Product</th>
                                                <th>Quantity</th>
                                                <th>Total</th>
                                                <th>Date</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($recent_sales as $sale): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($sale['product_display'] ?? 'Multi-item Sale'); ?></td>
                                                    <td><?php echo $sale['total_quantity'] ?? '-'; ?></td>
                                                    <td>₱<?php echo number_format($sale['total_amount'] ?? 0, 2); ?></td>
                                                    <td><?php echo date('M d, H:i', strtotime($sale['created_at'])); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="card mb-3 dashboard-card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="bi bi-exclamation-triangle"></i> Low Stock Alert</h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($low_stock_products)): ?>
                                <div class="empty-state text-success">
                                    <i class="bi bi-check-circle"></i>
                                    <p class="mt-2">All products are well stocked!</p>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-sm table-hover">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Product</th>
                                                <th>Current Stock</th>
                                                <th>Reorder Level</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($low_stock_products as $product): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($product['product_name']); ?></td>
                                                    <td><?php echo $product['stock_quantity']; ?></td>
                                                    <td><?php echo $product['reorder_level']; ?></td>
                                                    <td>
                                                        <?php if ($product['stock_quantity'] == 0): ?>
                                                            <span class="badge bg-danger">Out of Stock</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-warning">Low Stock</span>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="bi bi-bar-chart"></i> Monthly Sales (Last 6 Months)</h5>
                        </div>
                        <div class="card-body">
                            <canvas id="monthlyChart" height="200"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <?php include 'includes/chatbot.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script src="js/chatbot.js"></script>

    <script>
        Chart.defaults.font.family = 'Inter, system-ui, -apple-system, sans-serif';
        Chart.defaults.color = '#6c757d';
        Chart.defaults.borderColor = '#dee2e6';

        // Sales Chart
        const salesCtx = document.getElementById('salesChart').getContext('2d');
        new Chart(salesCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode(array_column($salesData, 'date')); ?>,
                datasets: [{
                    label: 'Daily Sales',
                    data: <?php echo json_encode(array_column($salesData, 'total')); ?>,
                    borderColor: 'rgb(75, 192, 192)',
                    backgroundColor: 'rgba(75, 192, 192, 0.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { callback: v => '₱' + v.toLocaleString() }
                    }
                },
                plugins: { legend: { display: false } }
            }
        });

        // Category Chart
        const categoryCtx = document.getElementById('categoryChart').getContext('2d');
        new Chart(categoryCtx, {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode(array_column($categories, 'name')); ?>,
                datasets: [{
                    data: <?php echo json_encode(array_column($categories, 'count')); ?>,
                    backgroundColor: ['#FF6384','#36A2EB','#FFCE56','#4BC0C0','#9966FF','#FF9F40']
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { position: 'bottom' } }
            }
        });

        // Monthly Chart
        const monthlyCtx = document.getElementById('monthlyChart').getContext('2d');
        new Chart(monthlyCtx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode(array_column($monthlyData, 'month')); ?>,
                datasets: [{
                    label: 'Monthly Sales',
                    data: <?php echo json_encode(array_column($monthlyData, 'total')); ?>,
                    backgroundColor: 'rgba(54, 162, 235, 0.8)',
                    borderColor: 'rgba(54, 162, 235, 1)',
                    borderWidth: 1,
                    categoryPercentage: 0.8,
                    barPercentage: 0.9
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: { grid: { display: false } },
                    y: {
                        beginAtZero: true,
                        ticks: { callback: v => '₱' + v.toLocaleString() }
                    }
                },
                plugins: { legend: { display: false } }
            }
        });

        function exportDashboard() {
            const csv = [
                'Dashboard Summary - ' + new Date().toLocaleDateString(),
                '',
                'Metrics',
                'Total Products,<?php echo $total_products; ?>',
                'Low Stock Items,<?php echo $low_stock; ?>',
                'Out of Stock Items,<?php echo $out_of_stock; ?>',
                "Today's Sales,₱<?php echo $today_sales; ?>"
            ];
            const blob = new Blob([csv.join('\n')], { type: 'text/csv' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'dashboard_summary_' + new Date().toISOString().split('T')[0] + '.csv';
            a.click();
            window.URL.revokeObjectURL(url);
        }
    </script>
</body>
</html>