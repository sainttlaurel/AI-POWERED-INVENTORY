<?php
require_once '../config/database.php';
require_once '../config/session.php';

// No need to be logged in to see notifications

header('Content-Type: application/json');

$database = new Database();
$db = $database->getConnection();

$low_stock_count = 0;
$out_of_stock_count = 0;

try {
    $low_stock_stmt = $db->query("SELECT COUNT(*) FROM products WHERE stock_quantity <= reorder_level AND stock_quantity > 0");
    if ($low_stock_stmt) {
        $low_stock_count = $low_stock_stmt->fetchColumn();
    }

    $out_of_stock_stmt = $db->query("SELECT COUNT(*) FROM products WHERE stock_quantity = 0");
    if ($out_of_stock_stmt) {
        $out_of_stock_count = $out_of_stock_stmt->fetchColumn();
    }
} catch (Exception $e) {
    // If the table doesn't exist, we can just return 0 notifications
    // This prevents errors on a fresh install
}

$total_notifications = $low_stock_count + $out_of_stock_count;

echo json_encode([
    'low_stock_count' => $low_stock_count,
    'out_of_stock_count' => $out_of_stock_count,
    'total_notifications' => $total_notifications
]);
