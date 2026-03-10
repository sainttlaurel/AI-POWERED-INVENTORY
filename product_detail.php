<?php
require_once 'config/database.php';
require_once 'config/session.php';
requireLogin();

$database = new Database();
$db = $database->getConnection();

$product_id = $_GET['id'] ?? 0;

// Get product details
$stmt = $db->prepare("SELECT p.*, c.name as category_name, s.name as supplier_name FROM products p LEFT JOIN categories c ON p.category_id = c.id LEFT JOIN suppliers s ON p.supplier_id = s.id WHERE p.id = :id");
$stmt->execute([':id' => $product_id]);
$product = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$product) {
    header("Location: products.php");
    exit();
}

// Get forecast data
$stmt = $db->prepare("SELECT * FROM forecast_data WHERE product_id = :id");
$stmt->execute([':id' => $product_id]);
$forecast = $stmt->fetch(PDO::FETCH_ASSOC);

// Get sales history
$stmt = $db->prepare("SELECT * FROM sales WHERE product_id = :id ORDER BY sale_date DESC LIMIT 10");
$stmt->execute([':id' => $product_id]);
$sales = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($product['product_name']); ?> - Details</title>
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
                    <h1 class="h2"><?php echo htmlspecialchars($product['product_name']); ?></h1>
                    <a href="products.php" class="btn btn-secondary">Back to Products</a>
                </div>

                <div class="row">
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-body text-center">
                                <?php if ($product['image']): ?>
                                    <img src="uploads/<?php echo htmlspecialchars($product['image']); ?>" class="img-fluid" alt="Product">
                                <?php else: ?>
                                    <div class="bg-secondary" style="height:300px;"></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-8">
                        <div class="card mb-3">
                            <div class="card-header">Product Information</div>
                            <div class="card-body">
                                <table class="table">
                                    <tr><th>Category:</th><td><?php echo htmlspecialchars($product['category_name']); ?></td></tr>
                                    <tr><th>Supplier:</th><td><?php echo htmlspecialchars($product['supplier_name']); ?></td></tr>
                                    <tr><th>Price:</th><td>$<?php echo number_format($product['price'], 2); ?></td></tr>
                                    <tr><th>Stock:</th><td><?php echo $product['stock_quantity']; ?></td></tr>
                                    <tr><th>Reorder Level:</th><td><?php echo $product['reorder_level']; ?></td></tr>
                                    <tr><th>Barcode:</th><td><?php echo htmlspecialchars($product['barcode']); ?></td></tr>
                                </table>
                            </div>
                        </div>

                        <?php if ($forecast): ?>
                        <div class="card mb-3">
                            <div class="card-header bg-info text-white">AI Forecast</div>
                            <div class="card-body">
                                <p><strong>Average Daily Sales:</strong> <?php echo number_format($forecast['avg_daily_sales'], 2); ?> units</p>
                                <p><strong>Weekly Forecast:</strong> <?php echo number_format($forecast['forecast_weekly'], 0); ?> units</p>
                                <p><strong>Monthly Forecast:</strong> <?php echo number_format($forecast['forecast_monthly'], 0); ?> units</p>
                                <p><strong>Predicted Depletion:</strong> <?php echo $forecast['predicted_depletion_days']; ?> days</p>
                                <p><strong>Reorder Suggestion:</strong> <?php echo $forecast['reorder_suggestion']; ?> units</p>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="card mt-3">
                    <div class="card-header">Sales History</div>
                    <div class="card-body">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Quantity</th>
                                    <th>Total Price</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($sales as $sale): ?>
                                    <tr>
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
    <script src="js/chatbot.js"></script>
</body>
</html>
