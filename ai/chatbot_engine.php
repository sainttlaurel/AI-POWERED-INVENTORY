<?php
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

$database = new Database();
$db = $database->getConnection();

if (!$db) {
    echo json_encode(['response' => 'Database connection error. Please try again later.']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
$query = strtolower($input['query'] ?? '');

$response = '';

// Low stock query
if (strpos($query, 'low stock') !== false || strpos($query, 'need restock') !== false) {
    $stmt = $db->query("SELECT product_name, stock_quantity FROM products WHERE stock_quantity <= reorder_level ORDER BY stock_quantity ASC LIMIT 5");
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($products) > 0) {
        $response = "The following products need restocking:\n";
        foreach ($products as $p) {
            $response .= "• " . $p['product_name'] . " – " . $p['stock_quantity'] . " left\n";
        }
    } else {
        $response = "All products are well stocked!";
    }
}
// Top selling product
elseif (strpos($query, 'top selling') !== false || strpos($query, 'best seller') !== false) {
    $stmt = $db->query("SELECT p.product_name, SUM(s.quantity) as total FROM sales s JOIN products p ON s.product_id = p.id GROUP BY s.product_id ORDER BY total DESC LIMIT 1");
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($product) {
        $response = "The top selling product is " . $product['product_name'] . " with " . $product['total'] . " units sold.";
    } else {
        $response = "No sales data available yet.";
    }
}
// Stock count
elseif (strpos($query, 'how many') !== false || strpos($query, 'total products') !== false) {
    $count = $db->query("SELECT COUNT(*) FROM products")->fetchColumn();
    $response = "There are currently " . $count . " products in the inventory.";
}
// Forecast query
elseif (strpos($query, 'forecast') !== false || strpos($query, 'predict') !== false) {
    $stmt = $db->query("SELECT p.product_name, f.predicted_depletion_days, f.reorder_suggestion FROM forecast_data f JOIN products p ON f.product_id = p.id WHERE f.predicted_depletion_days <= 7 ORDER BY f.predicted_depletion_days ASC LIMIT 3");
    $forecasts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($forecasts) > 0) {
        $response = "Products predicted to run out soon:\n";
        foreach ($forecasts as $f) {
            $response .= "• " . $f['product_name'] . " – " . $f['predicted_depletion_days'] . " days left. Reorder " . $f['reorder_suggestion'] . " units.\n";
        }
    } else {
        $response = "No urgent restocking needed based on forecasts.";
    }
}
// Out of stock
elseif (strpos($query, 'out of stock') !== false) {
    $stmt = $db->query("SELECT product_name FROM products WHERE stock_quantity = 0");
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($products) > 0) {
        $response = "Out of stock products:\n";
        foreach ($products as $p) {
            $response .= "• " . $p['product_name'] . "\n";
        }
    } else {
        $response = "No products are out of stock.";
    }
}
else {
    $response = "I can help you with:\n• Low stock products\n• Top selling items\n• Inventory forecasts\n• Stock counts\n• Out of stock alerts";
}

echo json_encode(['response' => $response]);
?>
