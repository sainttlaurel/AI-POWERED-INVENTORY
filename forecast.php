<?php
require_once 'config/database.php';
require_once 'config/session.php';
require_once 'ai/forecasting.php';
requireLogin();

$database = new Database();
$db = $database->getConnection();

// Update forecasts
$forecasting = new Forecasting($db);
$forecasting->updateAllForecasts();

// Get forecast data
$forecasts = $db->query("SELECT f.*, p.product_name, p.stock_quantity, p.price FROM forecast_data f JOIN products p ON f.product_id = p.id ORDER BY f.predicted_depletion_days ASC")->fetchAll(PDO::FETCH_ASSOC);

// Get top selling products
$top_products = $db->query("SELECT p.product_name, SUM(s.quantity) as total_sold, SUM(s.total_price) as revenue FROM sales s JOIN products p ON s.product_id = p.id GROUP BY s.product_id ORDER BY total_sold DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Forecast Analytics</title>
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
                    <h1 class="h2"><i class="bi bi-graph-up"></i> AI Forecast Analytics</h1>
                </div>

                <!-- Top Selling Products -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">Top Selling Products</div>
                            <div class="card-body">
                                <canvas id="topProductsChart"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">Demand Forecast Overview</div>
                            <div class="card-body">
                                <canvas id="demandChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Forecast Table -->
                <div class="card">
                    <div class="card-header">Product Forecast Data</div>
                    <div class="card-body">
                        <table class="table table-striped">
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
                                        <td><?php echo $f['stock_quantity']; ?></td>
                                        <td><?php echo number_format($f['avg_daily_sales'], 2); ?></td>
                                        <td><?php echo number_format($f['forecast_weekly'], 0); ?></td>
                                        <td><?php echo number_format($f['forecast_monthly'], 0); ?></td>
                                        <td>
                                            <span class="badge <?php echo $f['predicted_depletion_days'] <= 7 ? 'bg-danger' : 'bg-success'; ?>">
                                                <?php echo $f['predicted_depletion_days']; ?> days
                                            </span>
                                        </td>
                                        <td><?php echo $f['reorder_suggestion']; ?> units</td>
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
        const topProducts = <?php echo json_encode($top_products); ?>;
        new Chart(document.getElementById('topProductsChart'), {
            type: 'bar',
            data: {
                labels: topProducts.map(p => p.product_name),
                datasets: [{
                    label: 'Units Sold',
                    data: topProducts.map(p => p.total_sold),
                    backgroundColor: 'rgba(54, 162, 235, 0.5)'
                }]
            }
        });

        const forecasts = <?php echo json_encode($forecasts); ?>;
        new Chart(document.getElementById('demandChart'), {
            type: 'line',
            data: {
                labels: forecasts.slice(0, 10).map(f => f.product_name),
                datasets: [{
                    label: 'Weekly Forecast',
                    data: forecasts.slice(0, 10).map(f => f.forecast_weekly),
                    borderColor: 'rgb(255, 99, 132)',
                    tension: 0.1
                }]
            }
        });
    </script>
    <script src="js/chatbot.js"></script>
</body>
</html>
