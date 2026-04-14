<?php
require_once 'config/database.php';
require_once 'config/session.php';
requireLogin();

$database = new Database();
$db = $database->getConnection();

// Initialize forecasting if the class exists
$forecasts = [];
$top_products = [];

try {
    if (file_exists('ai/forecasting.php')) {
        require_once 'ai/forecasting.php';
        if (class_exists('AdvancedForecasting')) {
            $forecasting = new AdvancedForecasting($db);
            
            // Auto-update if table is empty or manually requested
            $has_data = false;
            try { $has_data = $db->query("SELECT COUNT(*) FROM forecast_data_advanced")->fetchColumn() > 0; } catch (Exception $e) {}
            
            if (!$has_data || isset($_GET['refresh'])) {
                $forecasting->updateAllForecasts();
            }
        }
    }
    
    // Get advanced forecast data with error handling
    $forecast_query = "SELECT f.*, p.product_name, p.stock_quantity, p.price 
                       FROM forecast_data_advanced f 
                       JOIN products p ON f.product_id = p.id 
                       ORDER BY f.predicted_depletion_days ASC";
    $forecasts = $db->query($forecast_query)->fetchAll(PDO::FETCH_ASSOC);
    
    // Get top products with improved query for multi-item sales
    $top_products_query = "
        SELECT p.product_name, 
               COALESCE(SUM(ii.quantity), 0) as total_sold,
               COALESCE(SUM(ii.subtotal), 0) as revenue
        FROM products p
        LEFT JOIN invoice_items ii ON p.id = ii.product_id
        LEFT JOIN invoices i ON ii.invoice_id = i.id AND i.payment_status = 'paid'
        GROUP BY p.id, p.product_name
        HAVING total_sold > 0
        ORDER BY total_sold DESC 
        LIMIT 5";
    $top_products = $db->query($top_products_query)->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    error_log("Forecast page error: " . $e->getMessage());
    // Continue with empty arrays if there are errors
}

// Removed the fallback mock data generator so only true AI values render
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php
$page_title = 'AI Forecast Analytics — InvenAI';
$extra_head = '
<style>
.forecast-stats {
  background: linear-gradient(135deg, rgba(99,102,241,0.08) 0%, rgba(6,182,212,0.04) 100%);
  border: 1px solid var(--border-subtle);
  border-radius: var(--border-radius-lg);
  padding: 1.25rem;
  margin-bottom: 1.5rem;
}
.stat-item { text-align: center; padding: 0.5rem; }
.stat-number { font-size: 1.75rem; font-weight: 700; color: var(--accent-primary); }
.stat-number.text-danger  { color: var(--accent-rose) !important; }
.stat-number.text-warning { color: var(--accent-amber) !important; }
.stat-number.text-success { color: var(--accent-emerald) !important; }
.stat-label { font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; }
.forecast-card { transition: all 0.25s; }
.forecast-card:hover { transform: translateY(-2px); box-shadow: var(--shadow-md); }
.chart-container { position: relative; height: 300px; }
.badge-depletion { font-size: 0.8rem; padding: 0.4em 0.8em; }
</style>';
include 'includes/head.php';
?>
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
        
        /* Forecast specific styling */
        .forecast-card {
            transition: all 0.3s ease;
            border: none;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }
        
        .forecast-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.15);
        }
        
        .chart-container {
            position: relative;
            height: 300px;
        }
        
        .badge-depletion {
            font-size: 0.8rem;
            padding: 0.4em 0.8em;
        }
        
        .forecast-stats {
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            border-radius: 12px;
            padding: 1rem;
            margin-bottom: 1rem;
        }
        
        .stat-item {
            text-align: center;
            padding: 0.5rem;
        }
        
        .stat-number {
            font-size: 1.5rem;
            font-weight: 600;
            color: #3b82f6;
        }
        
        .stat-label {
            font-size: 0.8rem;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.05em;
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
                    <h1 class="h2"><i class="bi bi-graph-up-arrow"></i> AI Forecast Analytics</h1>
                    <div>
                        <button onclick="refreshForecasts()" class="btn btn-outline-primary me-2">
                            <i class="bi bi-arrow-clockwise me-2"></i> Refresh Data
                        </button>
                        <a href="reports.php?type=low_stock" class="btn btn-outline-secondary me-2">
                            <i class="bi bi-printer me-2"></i> Print Report
                        </a>
                        <button onclick="exportForecast()" class="btn btn-success">
                            <i class="bi bi-file-earmark-spreadsheet me-2"></i> Export
                        </button>
                    </div>
                </div>

                <!-- Forecast Statistics -->
                <?php if (!empty($forecasts)): ?>
                <div class="forecast-stats">
                    <div class="row">
                        <div class="col-md-3">
                            <div class="stat-item">
                                <div class="stat-number"><?php echo count($forecasts); ?></div>
                                <div class="stat-label">Products Tracked</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-item">
                                <div class="stat-number text-danger">
                                    <?php echo count(array_filter($forecasts, function($f) { return $f['predicted_depletion_days'] <= 7; })); ?>
                                </div>
                                <div class="stat-label">Critical Stock</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-item">
                                <div class="stat-number text-warning">
                                    <?php echo count(array_filter($forecasts, function($f) { return $f['predicted_depletion_days'] > 7 && $f['predicted_depletion_days'] <= 14; })); ?>
                                </div>
                                <div class="stat-label">Low Stock</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-item">
                                <div class="stat-number text-success">
                                    <?php echo count(array_filter($forecasts, function($f) { return $f['predicted_depletion_days'] > 14; })); ?>
                                </div>
                                <div class="stat-label">Good Stock</div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="card forecast-card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="bi bi-trophy"></i> Top Selling Products</h5>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($top_products)): ?>
                                    <div class="chart-container">
                                        <canvas id="topProductsChart"></canvas>
                                    </div>
                                <?php else: ?>
                                    <div class="text-center py-4 text-muted">
                                        <i class="bi bi-graph-up fs-1"></i>
                                        <p class="mt-2">No sales data available</p>
                                        <small>Sales data will appear here after transactions</small>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card forecast-card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="bi bi-graph-up"></i> Demand Forecast Overview</h5>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($forecasts)): ?>
                                    <div class="chart-container">
                                        <canvas id="demandChart"></canvas>
                                    </div>
                                <?php else: ?>
                                    <div class="text-center py-4 text-muted">
                                        <i class="bi bi-graph-down fs-1"></i>
                                        <p class="mt-2">No forecast data available</p>
                                        <small>Add products to generate forecasts</small>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card forecast-card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-table"></i> Product Forecast Data</h5>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($forecasts)): ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover" id="forecastTable">
                                    <thead>
                                        <tr>
                                            <th>Product</th>
                                            <th>Current Stock</th>
                                            <th>Avg Daily Sales</th>
                                            <th>Weekly Forecast</th>
                                            <th>Monthly Forecast</th>
                                            <th>Depletion (Days)</th>
                                            <th>Reorder Suggestion</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($forecasts as $f): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($f['product_name']); ?></td>
                                                <td><?php echo number_format($f['stock_quantity'] ?? 0); ?></td>
                                                <td><?php echo number_format($f['avg_daily_sales'] ?? 0, 2); ?></td>
                                                <td><?php echo number_format($f['forecast_weekly'] ?? 0, 0); ?></td>
                                                <td><?php echo number_format($f['forecast_monthly'] ?? 0, 0); ?></td>
                                                <td>
                                                    <?php 
                                                    $days = $f['predicted_depletion_days'] ?? 0;
                                                    $badgeClass = $days <= 7 ? 'bg-danger' : ($days <= 14 ? 'bg-warning' : 'bg-success');
                                                    ?>
                                                    <span class="badge badge-depletion <?php echo $badgeClass; ?>">
                                                        <?php echo $days; ?> days
                                                    </span>
                                                </td>
                                                <td><?php echo number_format($f['reorder_suggestion'] ?? 0); ?> units</td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-4 text-muted">
                                <i class="bi bi-table fs-1"></i>
                                <p class="mt-2">No forecast data available</p>
                                <p><small>Add products and sales data to generate AI forecasts</small></p>
                                <a href="products.php" class="btn btn-primary">
                                    <i class="bi bi-plus-circle me-2"></i> Add Products
                                </a>
                            </div>
                        <?php endif; ?>
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

        // Top Products Chart
        const topProducts = <?php echo json_encode($top_products); ?>;
        if (topProducts && topProducts.length > 0) {
            new Chart(document.getElementById('topProductsChart'), {
                type: 'bar',
                data: {
                    labels: topProducts.map(p => p.product_name),
                    datasets: [{
                        label: 'Units Sold',
                        data: topProducts.map(p => p.total_sold),
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
                            ticks: {
                                stepSize: 1
                            }
                        }
                    }
                }
            });
        }

        // Demand Forecast Chart
        const forecasts = <?php echo json_encode($forecasts); ?>;
        if (forecasts && forecasts.length > 0) {
            new Chart(document.getElementById('demandChart'), {
                type: 'line',
                data: {
                    labels: forecasts.slice(0, 10).map(f => f.product_name),
                    datasets: [{
                        label: 'Weekly Forecast',
                        data: forecasts.slice(0, 10).map(f => f.forecast_weekly || 0),
                        borderColor: 'rgb(255, 99, 132)',
                        backgroundColor: 'rgba(255, 99, 132, 0.1)',
                        tension: 0.4,
                        fill: true,
                        pointRadius: 4,
                        pointHoverRadius: 6
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
                            beginAtZero: true
                        }
                    }
                }
            });
        }

        // Refresh forecasts function
        function refreshForecasts() {
            const btn = event.target.closest('button');
            const originalText = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Refreshing...';
            
            // Make AJAX call to refresh forecasts
            fetch('ai/forecasting.php?update_forecasts=true')
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        // Reload page with refresh parameter
                        window.location.href = window.location.pathname + '?refresh=1';
                    } else {
                        throw new Error(data.message || 'Failed to refresh forecasts');
                    }
                })
                .catch(error => {
                    console.error('Error refreshing forecasts:', error);
                    btn.disabled = false;
                    btn.innerHTML = originalText;
                    alert('Failed to refresh forecasts. Please try again.');
                });
        }

        // Export forecast data
        function exportForecast() {
            const forecasts = <?php echo json_encode($forecasts); ?>;
            const csv = [
                'AI Forecast Analytics - ' + new Date().toLocaleDateString(),
                '',
                'Product,Current Stock,Avg Daily Sales,Weekly Forecast,Monthly Forecast,Depletion Days,Reorder Suggestion'
            ];
            
            forecasts.forEach(f => {
                csv.push(`"${f.product_name}",${f.stock_quantity || 0},${f.avg_daily_sales || 0},${f.forecast_weekly || 0},${f.forecast_monthly || 0},${f.predicted_depletion_days || 0},${f.reorder_suggestion || 0}`);
            });
            
            const csvContent = csv.join('\n');
            const blob = new Blob([csvContent], { type: 'text/csv' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'forecast_data_' + new Date().toISOString().split('T')[0] + '.csv';
            a.click();
            window.URL.revokeObjectURL(url);
        }

        // Add table row hover effects
        document.addEventListener('DOMContentLoaded', function() {
            const tableRows = document.querySelectorAll('#forecastTable tbody tr');
            tableRows.forEach((row, index) => {
                row.style.animationDelay = `${index * 0.05}s`;
                
                row.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateX(5px)';
                    this.style.transition = 'all 0.3s ease';
                    this.style.boxShadow = '0 2px 8px rgba(0, 0, 0, 0.1)';
                });
                
                row.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateX(0)';
                    this.style.boxShadow = 'none';
                });
            });
        });
    </script>
    <script src="js/mobile.js?v=<?php echo time(); ?>"></script>
</body>
</html>
