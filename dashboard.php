<?php
require_once 'config/database.php';
require_once 'config/session.php';

requireLogin();

$database = new Database();
$db = $database->getConnection();

// Count stuff
$total_products = $db->query("SELECT COUNT(*) FROM products")->fetchColumn();
$low_stock = $db->query("SELECT COUNT(*) FROM products WHERE stock_quantity <= reorder_level AND stock_quantity > 0")->fetchColumn();
$out_of_stock = $db->query("SELECT COUNT(*) FROM products WHERE stock_quantity = 0")->fetchColumn();

// Get today sales
try {
    $today_sales = $db->query("SELECT COALESCE(SUM(total_price), 0) FROM sales WHERE DATE(created_at) = CURDATE()")->fetchColumn();
} catch (Exception $e) {
    $today_sales = 0;
}

// Get recent sales - just get them
$recent_sales = [];
try {
    $sales_stmt = $db->prepare("
        SELECT s.id, s.created_at, s.customer_name, s.product_id, s.quantity, 
               s.total_price as total_amount,
               p.product_name, sup.name as supplier_name
        FROM sales s
        LEFT JOIN products p ON s.product_id = p.id
        LEFT JOIN suppliers sup ON p.supplier_id = sup.id
        ORDER BY s.created_at DESC 
        LIMIT 10
    ");
    $sales_stmt->execute();
    $sales_data = $sales_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($sales_data as $sale) {
        // Check if multi-item sale
        $items_stmt = $db->prepare("
            SELECT COUNT(*) as item_count, SUM(si.quantity) as total_qty
            FROM sale_items si 
            WHERE si.sale_id = ?
        ");
        $items_stmt->execute([$sale['id']]);
        $item_info = $items_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($item_info['item_count'] > 0) {
            // Multi-item sale
            if ($item_info['item_count'] > 1) {
                $product_display = "Multi-item Sale ({$item_info['item_count']} items)";
                $quantity = $item_info['total_qty'];
            } else {
                // Single item
                $single_item_stmt = $db->prepare("
                    SELECT p.product_name, sup.name as supplier_name, si.quantity
                    FROM sale_items si
                    LEFT JOIN products p ON si.product_id = p.id
                    LEFT JOIN suppliers sup ON p.supplier_id = sup.id
                    WHERE si.sale_id = ?
                    LIMIT 1
                ");
                $single_item_stmt->execute([$sale['id']]);
                $single_item = $single_item_stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($single_item) {
                    $product_display = ($single_item['product_name'] ?? 'Unknown') . ' (' . ($single_item['supplier_name'] ?? 'No Brand') . ')';
                    $quantity = $single_item['quantity'];
                } else {
                    $product_display = "Sale #{$sale['id']}";
                    $quantity = 0;
                }
            }
        } else {
            // Single item sale (old way)
            if ($sale['product_name']) {
                $product_display = $sale['product_name'] . ' (' . ($sale['supplier_name'] ?? 'No Brand') . ')';
                $quantity = $sale['quantity'] ?? 0;
            } else {
                $product_display = "Sale #{$sale['id']}";
                $quantity = $sale['quantity'] ?? 0;
            }
        }
        
        $recent_sales[] = [
            'id' => $sale['id'],
            'created_at' => $sale['created_at'],
            'product_display' => $product_display,
            'total_quantity' => $quantity,
            'total_amount' => $sale['total_amount']
        ];
        
        if (count($recent_sales) >= 5) break;
    }
    
} catch (Exception $e) {
    $recent_sales = [];
}

// Get low stock products
$low_stock_products = $db->query("SELECT * FROM products 
    WHERE stock_quantity <= reorder_level 
    ORDER BY stock_quantity ASC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);

// Get weekly sales for chart
try {
    $weekly_sales = $db->query("SELECT DATE(created_at) as date, SUM(total_price) as total 
        FROM sales 
        WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) 
        GROUP BY DATE(created_at) 
        ORDER BY date")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $weekly_sales = [];
}

// Get categories for pie chart
$categories = $db->query("SELECT c.name, COUNT(p.id) as count, SUM(p.price * p.stock_quantity) as value
    FROM categories c 
    LEFT JOIN products p ON c.id = p.category_id 
    GROUP BY c.id 
    ORDER BY count DESC")->fetchAll(PDO::FETCH_ASSOC);

// Monthly sales (last 6 months)
try {
    // Make 6 month template
    $months_template = [];
    for ($i = 5; $i >= 0; $i--) {
        $month_key = date('Y-m', strtotime("-$i months"));
        $month_display = date('M Y', strtotime("-$i months"));
        $months_template[$month_key] = [
            'month' => $month_display,
            'total' => 0
        ];
    }
    
    // Get monthly data
    $monthly_sales_raw = $db->query("SELECT DATE_FORMAT(created_at, '%Y-%m') as month, SUM(total_price) as total 
        FROM sales 
        WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
        GROUP BY DATE_FORMAT(created_at, '%Y-%m') 
        ORDER BY month")->fetchAll(PDO::FETCH_ASSOC);
    
    // Fill template with data
    foreach ($monthly_sales_raw as $sale) {
        if (isset($months_template[$sale['month']])) {
            $months_template[$sale['month']]['total'] = (float)$sale['total'];
        }
    }
    
    $monthly_sales = array_values($months_template);
    
} catch (Exception $e) {
    // Empty template if error
    $monthly_sales = [];
    for ($i = 5; $i >= 0; $i--) {
        $monthly_sales[] = [
            'month' => date('M Y', strtotime("-$i months")),
            'total' => 0
        ];
    }
}

// Prepare chart data
$salesData = [];
$monthlyData = [];

// Sales chart data
foreach ($weekly_sales as $sale) {
    $salesData[] = [
        'date' => $sale['date'],
        'total' => (float)$sale['total']
    ];
}

foreach ($monthly_sales as $month) {
    $monthlyData[] = [
        'month' => $month['month'],
        'total' => (float)$month['total']
    ];
}
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
        /* Fix table alignment issues */
        .table {
            table-layout: auto !important;
        }
        
        .table th,
        .table td {
            animation: none !important;
            transform: none !important;
            transition: none !important;
        }
        
        .table tbody tr {
            animation: none !important;
            animation-delay: 0s !important;
        }
        
        .table tbody tr::before {
            display: none !important;
        }
        
        /* Ensure proper column structure */
        .table-responsive {
            overflow-x: auto;
        }
        
        /* Reset any conflicting styles */
        .table .badge {
            display: inline-block;
            white-space: nowrap;
        }
        
        /* Dashboard specific styling */
        .dashboard-card {
            transition: all 0.3s ease;
            border: none;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }
        
        .dashboard-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.15);
        }
        
        /* Chart containers */
        .chart-container {
            position: relative;
            height: 200px;
        }
        
        /* Stats cards hover effects */
        .card.text-white:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.2);
        }
        
        /* Table hover effects */
        .table tbody tr:hover {
            background: linear-gradient(135deg, rgba(59, 130, 246, 0.05) 0%, rgba(37, 99, 235, 0.05) 100%) !important;
            transform: translateX(3px);
            transition: all 0.3s ease;
        }
        
        /* Badge styling */
        .badge {
            font-size: 0.75em;
            padding: 0.35em 0.65em;
        }
        
        /* Empty state styling */
        .empty-state {
            padding: 2rem;
            text-align: center;
            color: #6c757d;
        }
        
        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }
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
                                <div class="fs-1 opacity-50">
                                    <i class="bi bi-box-seam"></i>
                                </div>
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
                                <div class="fs-1 opacity-50">
                                    <i class="bi bi-exclamation-triangle"></i>
                                </div>
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
                                <div class="fs-1 opacity-50">
                                    <i class="bi bi-x-circle"></i>
                                </div>
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
                                <div class="fs-1 opacity-50">
                                    <i class="bi bi-currency-dollar"></i>
                                </div>
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
                                    <table class="table table-sm table-hover" id="recentSalesTable">
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
                                    <table class="table table-sm table-hover" id="lowStockTable">
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
        // Chart.js default configuration
        Chart.defaults.font.family = 'Inter, system-ui, -apple-system, sans-serif';
        Chart.defaults.color = '#6c757d';
        Chart.defaults.borderColor = '#dee2e6';

        // Sales Chart
        const salesCtx = document.getElementById('salesChart').getContext('2d');
        const salesChart = new Chart(salesCtx, {
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
                        ticks: {
                            callback: function(value) {
                                return '₱' + value.toLocaleString();
                            }
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    }
                }
            }
        });

        // Category Chart
        const categoryCtx = document.getElementById('categoryChart').getContext('2d');
        const categoryChart = new Chart(categoryCtx, {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode(array_column($categories, 'name')); ?>,
                datasets: [{
                    data: <?php echo json_encode(array_column($categories, 'count')); ?>,
                    backgroundColor: [
                        '#FF6384',
                        '#36A2EB',
                        '#FFCE56',
                        '#4BC0C0',
                        '#9966FF',
                        '#FF9F40'
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });

        // Monthly Chart
        const monthlyCtx = document.getElementById('monthlyChart').getContext('2d');
        const monthlyChart = new Chart(monthlyCtx, {
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
                    x: {
                        grid: {
                            display: false
                        }
                    },
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '₱' + value.toLocaleString();
                            }
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    }
                }
            }
        });
        
        // Export dashboard data
        function exportDashboard() {
            const data = {
                total_products: <?php echo $total_products; ?>,
                low_stock: <?php echo $low_stock; ?>,
                out_of_stock: <?php echo $out_of_stock; ?>,
                today_sales: <?php echo $today_sales; ?>,
                recent_sales: <?php echo json_encode($recent_sales); ?>,
                low_stock_products: <?php echo json_encode($low_stock_products); ?>
            };
            
            const csv = [
                'Dashboard Summary - ' + new Date().toLocaleDateString(),
                '',
                'Metrics',
                'Total Products,' + data.total_products,
                'Low Stock Items,' + data.low_stock,
                'Out of Stock Items,' + data.out_of_stock,
                'Today\'s Sales,₱' + data.today_sales,
                '',
                'Recent Sales',
                'Product,Quantity,Total,Date'
            ];
            
            data.recent_sales.forEach(sale => {
                csv.push(`"${sale.product_display}",${sale.total_quantity},₱${sale.total_amount},"${sale.created_at}"`);
            });
            
            const csvContent = csv.join('\n');
            const blob = new Blob([csvContent], { type: 'text/csv' });
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