<?php
require_once '../config/database.php';
require_once '../config/session.php';

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

// Set JSON header
header('Content-Type: application/json');

$database = new Database();
$db = $database->getConnection();

$type = $_GET['type'] ?? 'sales';
$range = $_GET['range'] ?? 'today';
$from_date = $_GET['from_date'] ?? '';
$to_date = $_GET['to_date'] ?? '';

try {
    // Determine date range
    switch ($range) {
        case 'today':
            $start_date = date('Y-m-d');
            $end_date = date('Y-m-d');
            break;
        case 'yesterday':
            $start_date = date('Y-m-d', strtotime('-1 day'));
            $end_date = date('Y-m-d', strtotime('-1 day'));
            break;
        case 'week':
            $start_date = date('Y-m-d', strtotime('monday this week'));
            $end_date = date('Y-m-d');
            break;
        case 'month':
            $start_date = date('Y-m-01');
            $end_date = date('Y-m-d');
            break;
        case 'custom':
            $start_date = $from_date;
            $end_date = $to_date;
            break;
        default:
            $start_date = date('Y-m-d');
            $end_date = date('Y-m-d');
    }
    
    $response = ['success' => true, 'data' => []];
    
    switch ($type) {
        case 'sales':
            // Sales Summary
            $query = "SELECT i.* 
                     FROM invoices i
                     WHERE i.payment_status = 'paid' AND DATE(i.created_at) BETWEEN ? AND ?
                     ORDER BY i.created_at DESC";
            
            $stmt = $db->prepare($query);
            $stmt->execute([$start_date, $end_date]);
            $invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Calculate totals
            $total_sales = count($invoices);
            $total_revenue = array_sum(array_column($invoices, 'total_amount'));
            
            // Group by payment method
            $payment_methods = [];
            foreach ($invoices as $inv) {
                $method = $inv['payment_method'] ?? 'cash';
                if (!isset($payment_methods[$method])) {
                    $payment_methods[$method] = ['count' => 0, 'total' => 0];
                }
                $payment_methods[$method]['count']++;
                $payment_methods[$method]['total'] += $inv['total_amount'];
            }
            
            // Get invoice items for item-level metrics
            $total_items = 0;
            $product_sales = [];
            
            if ($total_sales > 0) {
                $invoice_ids = array_column($invoices, 'id');
                $placeholders = str_repeat('?,', count($invoice_ids) - 1) . '?';
                
                $items_query = "SELECT ii.*, p.product_name, c.name as category_name
                                FROM invoice_items ii
                                JOIN products p ON ii.product_id = p.id
                                LEFT JOIN categories c ON p.category_id = c.id
                                WHERE ii.invoice_id IN ($placeholders)";
                
                $items_stmt = $db->prepare($items_query);
                $items_stmt->execute($invoice_ids);
                $invoice_items = $items_stmt->fetchAll(PDO::FETCH_ASSOC);
                
                foreach ($invoice_items as $item) {
                    $total_items += $item['quantity'];
                    $product_id = $item['product_id'];
                    
                    if (!isset($product_sales[$product_id])) {
                        $product_sales[$product_id] = [
                            'name' => $item['product_name'] ?? 'Unknown',
                            'category' => $item['category_name'] ?? 'Uncategorized',
                            'quantity' => 0,
                            'revenue' => 0
                        ];
                    }
                    $product_sales[$product_id]['quantity'] += $item['quantity'];
                    $product_sales[$product_id]['revenue'] += $item['subtotal'];
                }
            }
            
            uasort($product_sales, function($a, $b) {
                return $b['quantity'] - $a['quantity'];
            });
            
            $response['data'] = [
                'transactions' => $invoices,
                'summary' => [
                    'total_sales' => $total_sales,
                    'total_revenue' => $total_revenue,
                    'total_items' => $total_items,
                    'average_sale' => $total_sales > 0 ? $total_revenue / $total_sales : 0
                ],
                'payment_methods' => $payment_methods,
                'top_products' => array_slice($product_sales, 0, 10, true),
                'date_range' => "$start_date to $end_date"
            ];
            break;
            
        case 'stock_in':
            // Stock In Summary
            $query = "SELECT il.*, p.product_name, p.barcode, c.name as category_name, sup.name as supplier_name, u.username
                     FROM inventory_logs il
                     JOIN products p ON il.product_id = p.id 
                     LEFT JOIN categories c ON p.category_id = c.id 
                     LEFT JOIN suppliers sup ON p.supplier_id = sup.id 
                     LEFT JOIN users u ON il.user_id = u.id
                     WHERE il.action = 'stock_in' AND DATE(il.created_at) BETWEEN ? AND ?
                     ORDER BY il.created_at DESC";
            
            $stmt = $db->prepare($query);
            $stmt->execute([$start_date, $end_date]);
            $stock_ins = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $total_operations = count($stock_ins);
            $total_quantity = array_sum(array_column($stock_ins, 'quantity'));
            
            // Group by category
            $categories = [];
            foreach ($stock_ins as $stock) {
                $cat = $stock['category_name'] ?? 'Uncategorized';
                if (!isset($categories[$cat])) {
                    $categories[$cat] = ['count' => 0, 'quantity' => 0];
                }
                $categories[$cat]['count']++;
                $categories[$cat]['quantity'] += $stock['quantity'];
            }
            
            $response['data'] = [
                'operations' => $stock_ins,
                'summary' => [
                    'total_operations' => $total_operations,
                    'total_quantity' => $total_quantity,
                    'average_quantity' => $total_operations > 0 ? $total_quantity / $total_operations : 0
                ],
                'categories' => $categories,
                'date_range' => "$start_date to $end_date"
            ];
            break;
            
        case 'stock_out':
            // Stock Out Summary (excluding sales)
            $query = "SELECT il.*, p.product_name, p.barcode, c.name as category_name, sup.name as supplier_name, u.username
                     FROM inventory_logs il
                     JOIN products p ON il.product_id = p.id 
                     LEFT JOIN categories c ON p.category_id = c.id 
                     LEFT JOIN suppliers sup ON p.supplier_id = sup.id 
                     LEFT JOIN users u ON il.user_id = u.id
                     WHERE il.action = 'stock_out' AND DATE(il.created_at) BETWEEN ? AND ?
                     ORDER BY il.created_at DESC";
            
            $stmt = $db->prepare($query);
            $stmt->execute([$start_date, $end_date]);
            $stock_outs = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $total_operations = count($stock_outs);
            $total_quantity = array_sum(array_column($stock_outs, 'quantity'));
            
            $response['data'] = [
                'operations' => $stock_outs,
                'summary' => [
                    'total_operations' => $total_operations,
                    'total_quantity' => $total_quantity,
                    'average_quantity' => $total_operations > 0 ? $total_quantity / $total_operations : 0
                ],
                'date_range' => "$start_date to $end_date"
            ];
            break;
            
        case 'daily':
            // Daily Overview
            $query = "SELECT 
                        DATE(i.created_at) as date,
                        COUNT(DISTINCT i.id) as sales_count,
                        SUM(ii.subtotal) as revenue,
                        SUM(ii.quantity) as items_sold
                      FROM invoices i
                      JOIN invoice_items ii ON i.id = ii.invoice_id
                      WHERE i.payment_status = 'paid' AND DATE(i.created_at) BETWEEN ? AND ?
                      GROUP BY DATE(i.created_at)
                      ORDER BY date DESC";
            
            $stmt = $db->prepare($query);
            $stmt->execute([$start_date, $end_date]);
            $daily_sales = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Stock movements
            $stock_query = "SELECT 
                              DATE(created_at) as date,
                              action,
                              COUNT(*) as operations,
                              SUM(quantity) as total_quantity
                            FROM inventory_logs 
                            WHERE DATE(created_at) BETWEEN ? AND ?
                            GROUP BY DATE(created_at), action
                            ORDER BY date DESC";
            
            $stmt = $db->prepare($stock_query);
            $stmt->execute([$start_date, $end_date]);
            $stock_movements = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $response['data'] = [
                'daily_sales' => $daily_sales,
                'stock_movements' => $stock_movements,
                'date_range' => "$start_date to $end_date"
            ];
            break;
            
        case 'weekly':
            // Weekly Overview
            $query = "SELECT 
                        YEARWEEK(i.created_at) as week,
                        MIN(DATE(i.created_at)) as week_start,
                        MAX(DATE(i.created_at)) as week_end,
                        COUNT(DISTINCT i.id) as sales_count,
                        SUM(ii.subtotal) as revenue,
                        SUM(ii.quantity) as items_sold
                      FROM invoices i
                      JOIN invoice_items ii ON i.id = ii.invoice_id
                      WHERE i.payment_status = 'paid' AND DATE(i.created_at) BETWEEN ? AND ?
                      GROUP BY YEARWEEK(i.created_at)
                      ORDER BY week DESC";
            
            $stmt = $db->prepare($query);
            $stmt->execute([$start_date, $end_date]);
            $weekly_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $response['data'] = [
                'weekly_data' => $weekly_data,
                'date_range' => "$start_date to $end_date"
            ];
            break;
            
        case 'monthly':
            // Monthly Overview
            $query = "SELECT 
                        DATE_FORMAT(i.created_at, '%Y-%m') as month,
                        COUNT(DISTINCT i.id) as sales_count,
                        SUM(ii.subtotal) as revenue,
                        SUM(ii.quantity) as items_sold
                      FROM invoices i
                      JOIN invoice_items ii ON i.id = ii.invoice_id
                      WHERE i.payment_status = 'paid' AND DATE(i.created_at) BETWEEN ? AND ?
                      GROUP BY DATE_FORMAT(i.created_at, '%Y-%m')
                      ORDER BY month DESC";
            
            $stmt = $db->prepare($query);
            $stmt->execute([$start_date, $end_date]);
            $monthly_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $response['data'] = [
                'monthly_data' => $monthly_data,
                'date_range' => "$start_date to $end_date"
            ];
            break;
    }
    
    echo json_encode($response);
    
} catch (Exception $e) {
    error_log("Inventory summary API error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>