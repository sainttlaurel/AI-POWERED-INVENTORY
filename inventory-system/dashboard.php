<?php
// Include database and session files
require_once 'config/database.php';
require_once 'config/session.php';

// Make sure user is logged in
requireLogin();

// Connect to database
$database = new Database();
$db = $database->getConnection();

// Count total products
$total_products = $db->query("SELECT COUNT(*) FROM products")->fetchColumn();

// Count products that need restocking
$low_stock = $db->query("SELECT COUNT(*) FROM products WHERE stock_quantity <= reorder_level AND stock_quantity > 0")->fetchColumn();

// Count products that are completely out of stock
$out_of_stock = $db->query("SELECT COUNT(*) FROM products WHERE stock_quantity = 0")->fetchColumn();

// Calculate today's sales total
$today_sales = $db->query("SELECT COALESCE(SUM(total_price), 0) FROM sales WHERE DATE(sale_date) = CURDATE()")->fetchColumn();

// Get the 5 most recent sales for the dashboard
$recent_sales = $db->query("SELECT s.*, p.product_name FROM sales s 
    JOIN products p ON s.product_id = p.id 
    ORDER BY s.sale_date DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);

// Get products that are running low on stock
$low_stock_products = $db->query("SELECT * FROM products 
    WHERE stock_quantity <= reorder_level 
    ORDER BY stock_quantity ASC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);

// Get sales data for the last week to show in chart
$weekly_sales = $db->query("SELECT DATE(sale_date) as date, SUM(total_price) as total 
    FROM sales 
    WHERE sale_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) 
    GROUP BY DATE(sale_date) 
    ORDER BY date")->fetchAll(PDO::FETCH_ASSOC);

// Get category breakdown for pie chart
$categories = $db->query("SELECT c.name, COUNT(p.id) as count, SUM(p.price * p.stock_quantity) as value
    FROM categories c 
    LEFT JOIN products p ON c.id = p.category_id 
    GROUP BY c.id 
    ORDER BY count DESC")->fetchAll(PDO::FETCH_ASSOC);

// Find which products make the most money (last 30 days)
$top_products = $db->query("SELECT p.product_name, SUM(s.total_price) as revenue, SUM(s.quantity) as units_sold
    FROM sales s 
    JOIN products p ON s.product_id = p.id 
    WHERE s.sale_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    GROUP BY s.product_id 
    ORDER BY revenue DESC 
    LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);

// Get monthly sales for trend analysis
$monthly_data = $db->query("SELECT 
    DATE_FORMAT(sale_date, '%Y-%m') as month,
    SUM(total_price) as revenue,
    COUNT(*) as transactions
    FROM sales 
    WHERE sale_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(sale_date, '%Y-%m')
    ORDER BY month")->fetchAll(PDO::FETCH_ASSOC);

// Check which suppliers have the most valuable inventory
$supplier_data = $db->query("SELECT s.name, SUM(p.price * p.stock_quantity) as inventory_value, COUNT(p.id) as product_count
    FROM suppliers s 
    LEFT JOIN products p ON s.id = p.supplier_id 
    GROUP BY s.id 
    ORDER BY inventory_value DESC 
    LIMIT 8")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Inventory System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="css/style.css?v=<?php echo time(); ?>">
</head>
<body>
    <?php include 'includes/navbar.php'; ?>
    
    <div class="container-fluid">
        <div class="sidebar-overlay" onclick="toggleSidebar()"></div>
        <?php include 'includes/sidebar.php'; ?>
        
        <main>
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2"><i class="bi bi-house-door"></i> Dashboard</h1>
                    <div>
                        <a href="reports.php?type=sales" class="btn btn-outline-primary btn-sm">
                            <i class="bi bi-file-earmark-text"></i> View Reports
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
                                        <h2 class="mb-0 mt-2">₱<?php echo number_format($today_sales, 2); ?></h2>
                                    </div>
                                    <i class="bi bi-currency-dollar" style="font-size: 2.5rem; opacity: 0.3;"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">Sales Analytics (Last 7 Days)</div>
                            <div class="card-body" style="height: 300px;">
                                <canvas id="salesChart"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">Low Stock Alerts</div>
                            <div class="card-body" style="height: 300px; overflow-y: auto;">
                                <?php if (empty($low_stock_products)): ?>
                                    <div class="text-center text-muted mt-5">
                                        <i class="bi bi-check-circle" style="font-size: 3rem;"></i>
                                        <p class="mt-2">All products are well stocked!</p>
                                    </div>
                                <?php else: ?>
                                    <ul class="list-group">
                                        <?php foreach ($low_stock_products as $product): ?>
                                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                                <span class="text-truncate" style="max-width: 200px;" title="<?php echo htmlspecialchars($product['product_name']); ?>">
                                                    <?php echo htmlspecialchars($product['product_name']); ?>
                                                </span>
                                                <span class="badge bg-warning"><?php echo $product['stock_quantity']; ?> left</span>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Analytics Row 1 -->
                <div class="row mb-4">
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header">Categories Distribution</div>
                            <div class="card-body" style="height: 280px;">
                                <canvas id="categoryChart"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-header">Top Revenue Products (30 Days)</div>
                            <div class="card-body" style="height: 280px;">
                                <canvas id="revenueChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Analytics Row 2 -->
                <div class="row mb-4">
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-header">Monthly Sales Trend (6 Months)</div>
                            <div class="card-body" style="height: 320px;">
                                <canvas id="monthlyChart"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header">Supplier Inventory Value</div>
                            <div class="card-body" style="height: 320px;">
                                <canvas id="supplierChart"></canvas>
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
                                        <td>₱<?php echo number_format($sale['total_price'], 2); ?></td>
                                        <td><?php echo date('M d, Y H:i', strtotime($sale['sale_date'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
        </main>
    </div>

    <?php include 'includes/chatbot.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script src="js/animations.js?v=<?php echo time(); ?>"></script>
    <script>
        // Chart.js default configuration
        Chart.defaults.font.size = 12;
        Chart.defaults.plugins.legend.labels.usePointStyle = true;
        Chart.defaults.plugins.legend.labels.padding = 15;

        // Sales chart
        const salesData = <?php echo json_encode($sales_data); ?>;
        const ctx = document.getElementById('salesChart').getContext('2d');
        
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: salesData.map(d => d.date),
                datasets: [{
                    label: 'Sales (₱)',
                    data: salesData.map(d => d.total),
                    borderColor: 'rgb(75, 192, 192)',
                    backgroundColor: 'rgba(75, 192, 192, 0.1)',
                    tension: 0.4,
                    fill: true,
                    borderWidth: 3
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0,0,0,0.1)'
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });

        // Category Distribution Chart
        const categories = <?php echo json_encode($categories); ?>;
        new Chart(document.getElementById('categoryChart'), {
            type: 'doughnut',
            data: {
                labels: categories.map(c => c.name),
                datasets: [{
                    data: categories.map(c => c.count),
                    backgroundColor: [
                        '#FF6384',
                        '#36A2EB',
                        '#FFCE56',
                        '#4BC0C0',
                        '#9966FF',
                        '#FF9F40'
                    ],
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 10,
                            font: {
                                size: 11
                            }
                        }
                    }
                },
                cutout: '60%'
            }
        });

        // Top Revenue Products Chart
        const topProducts = <?php echo json_encode($top_products); ?>;
        new Chart(document.getElementById('revenueChart'), {
            type: 'bar',
            data: {
                labels: topProducts.map(p => {
                    const name = p.product_name;
                    return name.length > 25 ? name.substring(0, 25) + '...' : name;
                }),
                datasets: [{
                    label: 'Revenue (₱)',
                    data: topProducts.map(p => p.revenue),
                    backgroundColor: 'rgba(54, 162, 235, 0.8)',
                    borderColor: 'rgba(54, 162, 235, 1)',
                    borderWidth: 1,
                    borderRadius: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0,0,0,0.1)'
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        },
                        ticks: {
                            maxRotation: 45,
                            font: {
                                size: 10
                            }
                        }
                    }
                }
            }
        });

        // Monthly Sales Trend Chart
        const monthlyData = <?php echo json_encode($monthly_sales); ?>;
        new Chart(document.getElementById('monthlyChart'), {
            type: 'line',
            data: {
                labels: monthlyData.map(m => m.month),
                datasets: [{
                    label: 'Revenue (₱)',
                    data: monthlyData.map(m => m.revenue),
                    borderColor: 'rgb(255, 99, 132)',
                    backgroundColor: 'rgba(255, 99, 132, 0.1)',
                    tension: 0.4,
                    fill: true,
                    borderWidth: 3
                }, {
                    label: 'Transactions',
                    data: monthlyData.map(m => m.transactions),
                    borderColor: 'rgb(75, 192, 192)',
                    backgroundColor: 'rgba(75, 192, 192, 0.1)',
                    tension: 0.4,
                    borderWidth: 3,
                    yAxisID: 'y1'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                        labels: {
                            padding: 15,
                            font: {
                                size: 11
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0,0,0,0.1)'
                        }
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        beginAtZero: true,
                        grid: {
                            drawOnChartArea: false,
                        },
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });

        // Supplier Inventory Value Chart
        const supplierData = <?php echo json_encode($supplier_inventory); ?>;
        new Chart(document.getElementById('supplierChart'), {
            type: 'pie',
            data: {
                labels: supplierData.map(s => s.name),
                datasets: [{
                    data: supplierData.map(s => s.inventory_value),
                    backgroundColor: [
                        '#FF6384',
                        '#36A2EB',
                        '#FFCE56',
                        '#4BC0C0',
                        '#9966FF',
                        '#FF9F40',
                        '#FF6B6B',
                        '#4ECDC4'
                    ],
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 8,
                            font: {
                                size: 10
                            },
                            generateLabels: function(chart) {
                                const data = chart.data;
                                if (data.labels.length && data.datasets.length) {
                                    return data.labels.map((label, i) => {
                                        const value = data.datasets[0].data[i];
                                        const shortLabel = label.length > 12 ? label.substring(0, 12) + '...' : label;
                                        return {
                                            text: shortLabel,
                                            fillStyle: data.datasets[0].backgroundColor[i],
                                            strokeStyle: data.datasets[0].borderColor,
                                            lineWidth: data.datasets[0].borderWidth,
                                            hidden: false,
                                            index: i
                                        };
                                    });
                                }
                                return [];
                            }
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const label = context.label || '';
                                const value = context.parsed;
                                return label + ': ₱' + value.toLocaleString();
                            }
                        }
                    }
                }
            }
        });
    </script>
    <script src="js/chatbot.js?v=<?php echo time(); ?>"></script>
    <script src="js/mobile.js?v=<?php echo time(); ?>"></script>
</body>
</html>
