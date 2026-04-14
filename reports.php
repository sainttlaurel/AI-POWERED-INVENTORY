<?php
require_once 'config/database.php';
require_once 'config/session.php';
requireLogin();

$database = new Database();
$db = $database->getConnection();

$report_type = $_GET['type'] ?? 'sales';
$date_from = $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
$date_to = $_GET['date_to'] ?? date('Y-m-d');
$location_id = $_GET['location_id'] ?? '';

$report_data = [];
$report_title = '';
$report_summary = [];

try {
    switch ($report_type) {
        case 'sales':
            $report_title = 'Sales Report';

            // Get all invoices and process them properly
            $sales_query = "SELECT i.* 
                           FROM invoices i 
                           WHERE DATE(i.created_at) BETWEEN ? AND ?
                           ORDER BY i.created_at DESC";

            $stmt = $db->prepare($sales_query);
            $stmt->execute([$date_from, $date_to]);
            $invoices_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $report_data = [];
            $total_sales = 0;
            $total_quantity = 0;

            foreach ($invoices_data as $invoice) {
                // Get invoice items
                $sale_items_query = "SELECT ii.*, p.barcode, sup.name as supplier_name 
                                    FROM invoice_items ii 
                                    LEFT JOIN products p ON ii.product_id = p.id 
                                    LEFT JOIN suppliers sup ON p.supplier_id = sup.id
                                    WHERE ii.invoice_id = ?";
                $stmt = $db->prepare($sale_items_query);
                $stmt->execute([$invoice['id']]);
                $invoice_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

                $sale_amount = $invoice['total_amount'] ?? 0;
                $total_sales += $sale_amount;

                if (!empty($invoice_items)) {
                    if (count($invoice_items) > 1) {
                        $total_qty = array_sum(array_column($invoice_items, 'quantity'));
                        $total_quantity += $total_qty;

                        $report_data[] = [
                            'id' => $invoice['id'],
                            'created_at' => $invoice['created_at'],
                            'product_name' => 'Multi-item Invoice (' . count($invoice_items) . ' items)',
                            'barcode' => $invoice['invoice_number'],
                            'quantity' => $total_qty,
                            'total_price' => $sale_amount,
                            'customer_name' => $invoice['customer_name'],
                            'payment_method' => $invoice['payment_method'],
                            'sale_type' => 'multi'
                        ];
                    } else {
                        $item = $invoice_items[0];
                        $total_quantity += $item['quantity'];

                        $report_data[] = [
                            'id' => $invoice['id'],
                            'created_at' => $invoice['created_at'],
                            'product_name' => $item['product_name'] . ' (' . ($item['supplier_name'] ?? 'No Brand') . ')',
                            'barcode' => $item['barcode'] ?? 'N/A',
                            'quantity' => $item['quantity'],
                            'total_price' => $sale_amount,
                            'customer_name' => $invoice['customer_name'],
                            'payment_method' => $invoice['payment_method'],
                            'sale_type' => 'single'
                        ];
                    }
                } else {
                    $report_data[] = [
                        'id' => $invoice['id'],
                        'created_at' => $invoice['created_at'],
                        'product_name' => 'Empty Invoice #' . $invoice['invoice_number'],
                        'barcode' => $invoice['invoice_number'],
                        'quantity' => 0,
                        'total_price' => $sale_amount,
                        'customer_name' => $invoice['customer_name'],
                        'payment_method' => $invoice['payment_method'],
                        'sale_type' => 'empty'
                    ];
                }
            }

            $report_summary = [
                'Total Sales' => $total_sales,
                'Total Quantity' => $total_quantity,
                'Number of Transactions' => count($report_data),
                'Average Sale Value' => count($report_data) > 0 ? $total_sales / count($report_data) : 0
            ];
            break;

        case 'stock':
            $report_title = 'Stock Report';
            $query = "SELECT p.*, c.name as category_name, s.name as supplier_name 
                      FROM products p 
                      LEFT JOIN categories c ON p.category_id = c.id 
                      LEFT JOIN suppliers s ON p.supplier_id = s.id 
                      ORDER BY p.product_name";
            $report_data = $db->query($query)->fetchAll(PDO::FETCH_ASSOC);

            $total_stock_value = 0;
            $total_products = count($report_data);
            $low_stock_count = 0;

            foreach ($report_data as $item) {
                $total_stock_value += $item['price'] * $item['stock_quantity'];
                if ($item['stock_quantity'] <= $item['reorder_level']) {
                    $low_stock_count++;
                }
            }

            $report_summary = [
                'Total Products' => $total_products,
                'Total Stock Value' => $total_stock_value,
                'Low Stock Items' => $low_stock_count,
                'Average Product Value' => $total_products > 0 ? $total_stock_value / $total_products : 0
            ];
            break;

        case 'low_stock':
            $report_title = 'Low Stock Report';
            $query = "SELECT p.*, c.name as category_name, s.name as supplier_name 
                      FROM products p 
                      LEFT JOIN categories c ON p.category_id = c.id 
                      LEFT JOIN suppliers s ON p.supplier_id = s.id 
                      WHERE p.stock_quantity <= p.reorder_level 
                      ORDER BY p.stock_quantity ASC";
            $report_data = $db->query($query)->fetchAll(PDO::FETCH_ASSOC);

            $critical_count = 0;
            $total_shortage_value = 0;

            foreach ($report_data as $item) {
                if ($item['stock_quantity'] == 0) {
                    $critical_count++;
                }
                $shortage = max(0, $item['reorder_level'] - $item['stock_quantity']);
                $total_shortage_value += $shortage * $item['price'];
            }

            $report_summary = [
                'Low Stock Items' => count($report_data),
                'Out of Stock Items' => $critical_count,
                'Total Shortage Value' => $total_shortage_value,
                'Reorder Required' => count($report_data)
            ];
            break;

        case 'profit_loss':
            $report_title = 'Profit & Loss Statement';

            // Revenue from all sales
            $revenue_query = "SELECT SUM(COALESCE(total_price, 0)) as total_revenue 
                             FROM sales 
                             WHERE DATE(created_at) BETWEEN ? AND ?";
            $params = [$date_from, $date_to];

            $stmt = $db->prepare($revenue_query);
            $stmt->execute($params);
            $total_revenue = $stmt->fetchColumn() ?: 0;

            // Revenue from all sales
            $revenue_query = "SELECT SUM(total_amount) as total_revenue 
                             FROM invoices 
                             WHERE payment_status = 'paid' AND DATE(created_at) BETWEEN ? AND ?";
            $params = [$date_from, $date_to];

            $stmt = $db->prepare($revenue_query);
            $stmt->execute($params);
            $total_revenue = $stmt->fetchColumn() ?: 0;

            // Cost of Goods Sold
            $cogs_query = "
                SELECT SUM(ii.quantity * COALESCE(NULLIF(p.cost_price, 0), p.price * 0.7)) as total_cogs 
                FROM invoice_items ii
                JOIN invoices i ON ii.invoice_id = i.id
                LEFT JOIN products p ON ii.product_id = p.id
                WHERE i.payment_status = 'paid' AND DATE(i.created_at) BETWEEN ? AND ?";

            $stmt = $db->prepare($cogs_query);
            $stmt->execute([$date_from, $date_to]);
            $cogs = $stmt->fetchColumn() ?: 0;

            $gross_profit = $total_revenue - $cogs;
            $gross_margin = $total_revenue > 0 ? ($gross_profit / $total_revenue) * 100 : 0;

            $report_summary = [
                'Total Revenue' => $total_revenue,
                'Cost of Goods Sold' => $cogs,
                'Gross Profit' => $gross_profit,
                'Gross Margin %' => $gross_margin
            ];

            // Detailed breakdown by category
            $category_query = "
                SELECT c.name as category, 
                       SUM(ii.subtotal) as revenue, 
                       SUM(ii.quantity * COALESCE(NULLIF(p.cost_price, 0), p.price * 0.7)) as cogs
                FROM invoice_items ii
                JOIN invoices i ON ii.invoice_id = i.id
                JOIN products p ON ii.product_id = p.id
                JOIN categories c ON p.category_id = c.id
                WHERE i.payment_status = 'paid' AND DATE(i.created_at) BETWEEN ? AND ?
                GROUP BY c.id, c.name
                ORDER BY revenue DESC";

            $stmt = $db->prepare($category_query);
            $stmt->execute([$date_from, $date_to]);
            $report_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;

        case 'inventory_valuation':
            $report_title = 'Inventory Valuation Report';

            if ($location_id) {
                $query = "SELECT p.product_name, c.name as category, s.name as supplier, pl.stock_quantity, p.price, (pl.stock_quantity * p.price) as total_value FROM product_locations pl JOIN products p ON pl.product_id = p.id LEFT JOIN categories c ON p.category_id = c.id LEFT JOIN suppliers s ON p.supplier_id = s.id WHERE pl.location_id = ? ORDER BY total_value DESC";
                $stmt = $db->prepare($query);
                $stmt->execute([$location_id]);
            } else {
                $query = "SELECT p.product_name, c.name as category, s.name as supplier, p.stock_quantity, p.price, (p.stock_quantity * p.price) as total_value FROM products p LEFT JOIN categories c ON p.category_id = c.id LEFT JOIN suppliers s ON p.supplier_id = s.id ORDER BY total_value DESC";
                $stmt = $db->prepare($query);
                $stmt->execute();
            }

            $report_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $total_inventory_value = array_sum(array_column($report_data, 'total_value'));

            $report_summary = [
                'Total Products' => count($report_data),
                'Total Inventory Value' => $total_inventory_value,
                'Average Product Value' => count($report_data) > 0 ? $total_inventory_value / count($report_data) : 0
            ];
            break;

        case 'supplier_performance':
            $report_title = 'Supplier Performance Analysis';

            $query = "
                SELECT 
                    s.name as supplier, 
                    COUNT(DISTINCT p.id) as products_supplied, 
                    SUM(p.stock_quantity) as total_stock, 
                    SUM(p.price * p.stock_quantity) as inventory_value,
                    COALESCE(SUM(sales_revenue.revenue), 0) as sales_revenue,
                    COALESCE(SUM(sales_revenue.transactions), 0) as transactions
                FROM suppliers s 
                LEFT JOIN products p ON s.id = p.supplier_id 
                LEFT JOIN (
                    SELECT p.supplier_id, SUM(ii.subtotal) as revenue, COUNT(DISTINCT i.id) as transactions
                    FROM invoice_items ii
                    JOIN invoices i ON ii.invoice_id = i.id
                    JOIN products p ON ii.product_id = p.id
                    WHERE i.payment_status = 'paid' AND DATE(i.created_at) BETWEEN ? AND ?
                    GROUP BY p.supplier_id
                ) as sales_revenue ON s.id = sales_revenue.supplier_id
                GROUP BY s.id, s.name 
                ORDER BY sales_revenue DESC";

            $stmt = $db->prepare($query);
            $stmt->execute([$date_from, $date_to]);
            $report_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $total_suppliers = count($report_data);
            $total_sales = array_sum(array_column($report_data, 'sales_revenue'));

            $report_summary = [
                'Total Suppliers' => $total_suppliers,
                'Total Sales Revenue' => $total_sales,
                'Average Revenue per Supplier' => $total_suppliers > 0 ? $total_sales / $total_suppliers : 0
            ];
            break;

        case 'abc_analysis':
            $report_title = 'ABC Analysis Report';

            $query = "
                SELECT 
                    p.product_name, 
                    c.name as category, 
                    COALESCE(SUM(revenue_data.revenue), 0) as revenue, 
                    COALESCE(SUM(revenue_data.units_sold), 0) as units_sold, 
                    p.stock_quantity, 
                    (p.price * p.stock_quantity) as inventory_value 
                FROM products p 
                LEFT JOIN categories c ON p.category_id = c.id 
                LEFT JOIN (
                    SELECT ii.product_id, SUM(ii.subtotal) as revenue, SUM(ii.quantity) as units_sold
                    FROM invoice_items ii
                    JOIN invoices i ON ii.invoice_id = i.id
                    WHERE i.payment_status = 'paid' AND DATE(i.created_at) BETWEEN ? AND ?
                    GROUP BY ii.product_id
                ) as revenue_data ON p.id = revenue_data.product_id
                GROUP BY p.id, p.product_name, c.name, p.stock_quantity, p.price
                ORDER BY revenue DESC";

            $stmt = $db->prepare($query);
            $stmt->execute([$date_from, $date_to]);
            $abc_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Calculate ABC classification
            $total_revenue = array_sum(array_column($abc_data, 'revenue'));
            $cumulative_revenue = 0;

            foreach ($abc_data as &$item) {
                $cumulative_revenue += $item['revenue'];
                $cumulative_percent = $total_revenue > 0 ? ($cumulative_revenue / $total_revenue) * 100 : 0;

                if ($cumulative_percent <= 80) {
                    $item['abc_class'] = 'A';
                } elseif ($cumulative_percent <= 95) {
                    $item['abc_class'] = 'B';
                } else {
                    $item['abc_class'] = 'C';
                }
            }

            $report_data = $abc_data;

            $class_a = array_filter($abc_data, fn($item) => $item['abc_class'] === 'A');
            $class_b = array_filter($abc_data, fn($item) => $item['abc_class'] === 'B');
            $class_c = array_filter($abc_data, fn($item) => $item['abc_class'] === 'C');

            $report_summary = [
                'Class A Products' => count($class_a) . ' (' . round((count($class_a) / count($abc_data)) * 100, 1) . '%)',
                'Class B Products' => count($class_b) . ' (' . round((count($class_b) / count($abc_data)) * 100, 1) . '%)',
                'Class C Products' => count($class_c) . ' (' . round((count($class_c) / count($abc_data)) * 100, 1) . '%)',
                'Total Revenue' => $total_revenue
            ];
            break;

        case 'slow_moving':
            $report_title = 'Slow Moving Inventory Report';

            $query = "SELECT 
                p.product_name, 
                c.name as category, 
                p.stock_quantity, 
                p.price, 
                (p.stock_quantity * p.price) as inventory_value, 
                COALESCE(SUM(sales_data.units_sold), 0) as units_sold, 
                DATEDIFF(?, p.date_added) as days_in_inventory 
            FROM products p 
            LEFT JOIN categories c ON p.category_id = c.id 
            LEFT JOIN (
                SELECT ii.product_id, SUM(ii.quantity) as units_sold
                FROM invoice_items ii
                JOIN invoices i ON ii.invoice_id = i.id
                WHERE i.payment_status = 'paid' AND DATE(i.created_at) BETWEEN ? AND ?
                GROUP BY ii.product_id
            ) as sales_data ON p.id = sales_data.product_id
            GROUP BY p.id, p.product_name, c.name, p.stock_quantity, p.price, p.date_added
            HAVING units_sold = 0 OR (units_sold / GREATEST(days_in_inventory, 1)) < 0.1 
            ORDER BY inventory_value DESC";

            $stmt = $db->prepare($query);
            $stmt->execute([$date_to, $date_from, $date_to]);
            $report_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $total_slow_value = array_sum(array_column($report_data, 'inventory_value'));

            $report_summary = [
                'Slow Moving Products' => count($report_data),
                'Total Value Tied Up' => $total_slow_value,
                'Average Days in Inventory' => count($report_data) > 0 ? array_sum(array_column($report_data, 'days_in_inventory')) / count($report_data) : 0
            ];
            break;

        case 'tax_report':
            $report_title = 'Tax Report';

            $query = "SELECT 
                        DATE(created_at) as sale_date, 
                        SUM(total_amount) as daily_sales, 
                        SUM(tax_amount) as vat_amount 
                      FROM invoices 
                      WHERE payment_status = 'paid' AND DATE(created_at) BETWEEN ? AND ? 
                      GROUP BY DATE(created_at) 
                      ORDER BY sale_date";

            $stmt = $db->prepare($query);
            $stmt->execute([$date_from, $date_to]);
            $report_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $total_sales = array_sum(array_column($report_data, 'daily_sales'));
            $total_vat = $total_sales * 0.12;

            $report_summary = [
                'Total Sales (Gross)' => $total_sales,
                'VAT Amount (12%)' => $total_vat,
                'Net Sales' => $total_sales - $total_vat,
                'Number of Sale Days' => count($report_data)
            ];
            break;

        case 'turnover_analysis':
            $report_title = 'Inventory Turnover Analysis';

            $query = "SELECT 
                p.product_name,
                p.stock_quantity,
                p.reorder_level,
                p.price,
                COALESCE(SUM(sales_data.total_sold), 0) as total_sold,
                COALESCE(SUM(sales_data.revenue), 0) as revenue,
                CASE 
                    WHEN p.stock_quantity > 0 THEN COALESCE(SUM(sales_data.total_sold), 0) / p.stock_quantity
                    ELSE 0 
                END as turnover_ratio,
                DATEDIFF(?, ?) as days_period
            FROM products p
            LEFT JOIN (
                SELECT ii.product_id, SUM(ii.quantity) as total_sold, SUM(ii.subtotal) as revenue
                FROM invoice_items ii
                JOIN invoices i ON ii.invoice_id = i.id
                WHERE i.payment_status = 'paid' AND DATE(i.created_at) BETWEEN ? AND ?
                GROUP BY ii.product_id
            ) as sales_data ON p.id = sales_data.product_id
            GROUP BY p.id, p.product_name, p.stock_quantity, p.reorder_level, p.price
            ORDER BY turnover_ratio DESC
            LIMIT 50";

            $stmt = $db->prepare($query);
            $stmt->execute([$date_to, $date_from, $date_from, $date_to]);
            $report_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $avg_turnover = count($report_data) > 0 ? array_sum(array_column($report_data, 'turnover_ratio')) / count($report_data) : 0;
            $high_turnover = array_filter($report_data, fn($item) => $item['turnover_ratio'] > 2);
            $low_turnover = array_filter($report_data, fn($item) => $item['turnover_ratio'] < 0.5);

            $report_summary = [
                'Average Turnover Ratio' => round($avg_turnover, 2),
                'High Turnover Products' => count($high_turnover),
                'Low Turnover Products' => count($low_turnover),
                'Analysis Period (Days)' => (strtotime($date_to) - strtotime($date_from)) / (60 * 60 * 24)
            ];
            break;

        case 'profit_analysis':
            $report_title = 'Profit Analysis Report';

            $query = "SELECT 
                p.product_name,
                p.price as selling_price,
                COALESCE(NULLIF(p.cost_price, 0), p.price * 0.7) as estimated_cost,
                (p.price - COALESCE(NULLIF(p.cost_price, 0), p.price * 0.7)) as estimated_profit_per_unit,
                COALESCE(SUM(sales_data.units_sold), 0) as units_sold,
                COALESCE(SUM(sales_data.revenue), 0) as revenue,
                COALESCE(SUM(sales_data.units_sold * (p.price - COALESCE(NULLIF(p.cost_price, 0), p.price * 0.7))), 0) as estimated_profit,
                CASE 
                    WHEN SUM(sales_data.revenue) > 0 THEN (SUM(sales_data.units_sold * (p.price - COALESCE(NULLIF(p.cost_price, 0), p.price * 0.7))) / SUM(sales_data.revenue)) * 100
                    ELSE 0 
                END as profit_margin_percent
            FROM products p
            LEFT JOIN (
                SELECT ii.product_id, SUM(ii.quantity) as units_sold, SUM(ii.subtotal) as revenue
                FROM invoice_items ii
                JOIN invoices i ON ii.invoice_id = i.id
                WHERE i.payment_status = 'paid' AND DATE(i.created_at) BETWEEN ? AND ?
                GROUP BY ii.product_id
            ) as sales_data ON p.id = sales_data.product_id
            GROUP BY p.id, p.product_name, p.price, p.cost_price
            HAVING units_sold > 0
            ORDER BY estimated_profit DESC";

            $stmt = $db->prepare($query);
            $stmt->execute([$date_from, $date_to]);
            $report_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $total_revenue = array_sum(array_column($report_data, 'revenue'));
            $total_profit = array_sum(array_column($report_data, 'estimated_profit'));
            $avg_margin = $total_revenue > 0 ? ($total_profit / $total_revenue) * 100 : 0;

            $report_summary = [
                'Total Revenue' => $total_revenue,
                'Estimated Total Profit' => $total_profit,
                'Average Profit Margin %' => round($avg_margin, 1),
                'Profitable Products' => count($report_data)
            ];
            break;
    }

} catch (Exception $e) {
    $error_message = "Error generating report: " . $e->getMessage();
}

// Get locations for filter
$locations = [];
try {
    $locations = $db->query("SELECT * FROM locations ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Locations table might not exist yet
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>
        <?php echo $report_title; ?> - Inventory System
    </title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="css/style.css?v=<?php echo time(); ?>">
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

        /* Print styles */
        @media print {
            .no-print {
                display: none !important;
            }

            .card {
                border: none !important;
                box-shadow: none !important;
            }

            .table {
                font-size: 12px;
            }

            .table th,
            .table td {
                padding: 0.25rem !important;
            }
        }

        /* Report specific styling */
        .report-summary {
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            border-radius: 12px;
            padding: 1rem;
            margin-bottom: 1rem;
        }

        .summary-item {
            text-align: center;
            padding: 0.5rem;
        }

        .summary-value {
            font-size: 1.2rem;
            font-weight: 600;
            color: #3b82f6;
        }

        .summary-label {
            font-size: 0.8rem;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        /* Badge styling */
        .badge {
            font-size: 0.75em;
            padding: 0.35em 0.65em;
        }

        /* Table hover effects */
        .table tbody tr:hover {
            background: linear-gradient(135deg, rgba(59, 130, 246, 0.05) 0%, rgba(37, 99, 235, 0.05) 100%) !important;
            transform: translateX(2px);
            transition: all 0.3s ease;
        }
    </style>
</head>

<body>
    <div class="no-print">
        <?php include 'includes/navbar.php'; ?>
    </div>

    <div class="container-fluid">
        <div class="sidebar-overlay"></div>
        <div class="no-print">
            <?php include 'includes/sidebar.php'; ?>
        </div>

        <main>
            <?php if (isset($error_message)): ?>
                <div class="alert alert-danger no-print" role="alert">
                    <i class="bi bi-exclamation-triangle"></i>
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>

            <div
                class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom no-print">
                <h1 class="h2"><i class="bi bi-file-earmark-text"></i> Reports</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <div class="btn-group me-2">
                        <button onclick="window.print()" class="btn btn-outline-secondary">
                            <i class="bi bi-printer me-2"></i> Print Report
                        </button>
                        <button onclick="exportToCSV()" class="btn btn-success">
                            <i class="bi bi-file-earmark-spreadsheet me-2"></i> Export CSV
                        </button>
                        <button onclick="shareReport()" class="btn btn-info">
                            <i class="bi bi-share me-2"></i> Share
                        </button>
                    </div>
                </div>
            </div>

            <div class="card mb-3 no-print">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label">Report Type</label>
                            <select name="type" class="form-select" onchange="this.form.submit()">
                                <option value="sales" <?php echo $report_type === 'sales' ? 'selected' : ''; ?>>Sales
                                    Report</option>
                                <option value="stock" <?php echo $report_type === 'stock' ? 'selected' : ''; ?>>Stock
                                    Report</option>
                                <option value="low_stock" <?php echo $report_type === 'low_stock' ? 'selected' : ''; ?>
                                    >Low Stock Report</option>
                                <option value="profit_loss" <?php echo $report_type === 'profit_loss' ? 'selected' : ''; ?>>Profit & Loss</option>
                                <option value="inventory_valuation" <?php echo $report_type === 'inventory_valuation' ? 'selected' : ''; ?>>Inventory Valuation</option>
                                <option value="supplier_performance" <?php echo $report_type === 'supplier_performance' ? 'selected' : ''; ?>>Supplier Performance</option>
                                <option value="abc_analysis" <?php echo $report_type === 'abc_analysis' ? 'selected' : ''; ?>>ABC Analysis</option>
                                <option value="slow_moving" <?php echo $report_type === 'slow_moving' ? 'selected' : ''; ?>>Slow Moving Inventory</option>
                                <option value="tax_report" <?php echo $report_type === 'tax_report' ? 'selected' : ''; ?>
                                    >Tax Report</option>
                                <option value="turnover_analysis" <?php echo $report_type === 'turnover_analysis' ? 'selected' : ''; ?>>Inventory Turnover</option>
                                <option value="profit_analysis" <?php echo $report_type === 'profit_analysis' ? 'selected' : ''; ?>>Profit Analysis</option>
                            </select>
                        </div>
                        <?php if (in_array($report_type, ['sales', 'profit_loss', 'supplier_performance', 'abc_analysis', 'slow_moving', 'tax_report', 'turnover_analysis', 'profit_analysis'])): ?>
                            <div class="col-md-2">
                                <label class="form-label">From Date</label>
                                <input type="date" name="date_from" class="form-control" value="<?php echo $date_from; ?>">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">To Date</label>
                                <input type="date" name="date_to" class="form-control" value="<?php echo $date_to; ?>">
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($locations)): ?>
                            <div class="col-md-2">
                                <label class="form-label">Location</label>
                                <select name="location_id" class="form-select">
                                    <option value="">All Locations</option>
                                    <?php foreach ($locations as $location): ?>
                                        <option value="<?php echo $location['id']; ?>" <?php echo $location_id == $location['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($location['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        <?php endif; ?>
                        <div class="col-md-2">
                            <label class="form-label">&nbsp;</label>
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="bi bi-graph-up me-2"></i> Generate
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h3 class="mb-0">
                        <?php echo $report_title; ?>
                    </h3>
                    <small class="text-muted">Generated on
                        <?php echo date('F d, Y H:i:s'); ?>
                    </small>
                    <?php if (in_array($report_type, ['sales', 'profit_loss', 'supplier_performance', 'abc_analysis', 'slow_moving', 'tax_report', 'turnover_analysis', 'profit_analysis'])): ?>
                        <br><small class="text-muted">Period:
                            <?php echo date('M d, Y', strtotime($date_from)); ?> -
                            <?php echo date('M d, Y', strtotime($date_to)); ?>
                        </small>
                    <?php endif; ?>
                </div>

                <!-- Report Summary -->
                <?php if (!empty($report_summary)): ?>
                    <div class="card-body report-summary">
                        <div class="row">
                            <?php foreach ($report_summary as $label => $value): ?>
                                <div class="col-md-3 mb-2">
                                    <div class="summary-item border rounded p-2">
                                        <div class="summary-label">
                                            <?php echo $label; ?>
                                        </div>
                                        <div class="summary-value">
                                            <?php
                                            if (is_numeric($value) && strpos($label, '%') !== false) {
                                                echo number_format($value, 1) . '%';
                                            } elseif (is_numeric($value) && $value >= 1000) {
                                                echo '₱' . number_format($value, 2);
                                            } else {
                                                echo is_numeric($value) ? number_format($value, 0) : $value;
                                            }
                                            ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="card-body">
                    <?php if (empty($report_data)): ?>
                        <div class="text-center text-muted py-5">
                            <i class="bi bi-file-earmark-text" style="font-size: 3rem;"></i>
                            <p class="mt-2">No data available for the selected criteria.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover" id="reportTable">
                                <thead class="table-dark">
                                    <tr>
                                        <?php if ($report_type === 'sales'): ?>
                                            <th>Date</th>
                                            <th>Product</th>
                                            <th>Barcode</th>
                                            <th>Quantity</th>
                                            <th>Total Price</th>
                                            <th>Customer</th>
                                            <th>Payment</th>
                                        <?php elseif ($report_type === 'stock'): ?>
                                            <th>Product Name</th>
                                            <th>Category</th>
                                            <th>Supplier</th>
                                            <th>Price</th>
                                            <th>Stock Qty</th>
                                            <th>Reorder Level</th>
                                            <th>Stock Value</th>
                                            <th>Status</th>
                                        <?php elseif ($report_type === 'low_stock'): ?>
                                            <th>Product Name</th>
                                            <th>Category</th>
                                            <th>Supplier</th>
                                            <th>Current Stock</th>
                                            <th>Reorder Level</th>
                                            <th>Shortage</th>
                                        <?php elseif ($report_type === 'profit_loss'): ?>
                                            <th>Category</th>
                                            <th>Revenue</th>
                                            <th>Cost of Goods Sold</th>
                                            <th>Gross Profit</th>
                                            <th>Margin %</th>
                                        <?php elseif ($report_type === 'inventory_valuation'): ?>
                                            <th>Product</th>
                                            <th>Category</th>
                                            <th>Supplier</th>
                                            <th>Stock Quantity</th>
                                            <th>Unit Price</th>
                                            <th>Total Value</th>
                                        <?php elseif ($report_type === 'supplier_performance'): ?>
                                            <th>Supplier</th>
                                            <th>Products Supplied</th>
                                            <th>Total Stock</th>
                                            <th>Inventory Value</th>
                                            <th>Sales Revenue</th>
                                            <th>Transactions</th>
                                        <?php elseif ($report_type === 'abc_analysis'): ?>
                                            <th>Product</th>
                                            <th>Category</th>
                                            <th>Revenue</th>
                                            <th>Units Sold</th>
                                            <th>Stock Quantity</th>
                                            <th>Inventory Value</th>
                                            <th>ABC Class</th>
                                        <?php elseif ($report_type === 'slow_moving'): ?>
                                            <th>Product</th>
                                            <th>Category</th>
                                            <th>Stock Quantity</th>
                                            <th>Unit Price</th>
                                            <th>Inventory Value</th>
                                            <th>Units Sold</th>
                                            <th>Days in Inventory</th>
                                        <?php elseif ($report_type === 'tax_report'): ?>
                                            <th>Date</th>
                                            <th>Daily Sales</th>
                                            <th>VAT Amount (12%)</th>
                                            <th>Net Sales</th>
                                        <?php elseif ($report_type === 'turnover_analysis'): ?>
                                            <th>Product</th>
                                            <th>Current Stock</th>
                                            <th>Units Sold</th>
                                            <th>Revenue</th>
                                            <th>Turnover Ratio</th>
                                            <th>Performance</th>
                                        <?php elseif ($report_type === 'profit_analysis'): ?>
                                            <th>Product</th>
                                            <th>Selling Price</th>
                                            <th>Est. Cost</th>
                                            <th>Profit/Unit</th>
                                            <th>Units Sold</th>
                                            <th>Total Profit</th>
                                            <th>Margin %</th>
                                        <?php endif; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($report_data as $row): ?>
                                        <tr>
                                            <?php if ($report_type === 'sales'): ?>
                                                <td>
                                                    <?php echo date('M d, Y H:i', strtotime($row['created_at'])); ?>
                                                </td>
                                                <td>
                                                    <?php echo htmlspecialchars($row['product_name']); ?>
                                                </td>
                                                <td>
                                                    <?php echo htmlspecialchars($row['barcode']); ?>
                                                </td>
                                                <td>
                                                    <?php echo $row['quantity']; ?>
                                                </td>
                                                <td>₱
                                                    <?php echo number_format($row['total_price'], 2); ?>
                                                </td>
                                                <td>
                                                    <?php echo htmlspecialchars($row['customer_name'] ?? 'Walk-in'); ?>
                                                </td>
                                                <td>
                                                    <?php echo ucfirst(str_replace('_', ' ', $row['payment_method'] ?? 'cash')); ?>
                                                </td>
                                            <?php elseif ($report_type === 'stock'): ?>
                                                <td>
                                                    <?php echo htmlspecialchars($row['product_name']); ?>
                                                </td>
                                                <td>
                                                    <?php echo htmlspecialchars($row['category_name']); ?>
                                                </td>
                                                <td>
                                                    <?php echo htmlspecialchars($row['supplier_name']); ?>
                                                </td>
                                                <td>₱
                                                    <?php echo number_format($row['price'], 2); ?>
                                                </td>
                                                <td>
                                                    <?php echo $row['stock_quantity']; ?>
                                                </td>
                                                <td>
                                                    <?php echo $row['reorder_level']; ?>
                                                </td>
                                                <td>₱
                                                    <?php echo number_format($row['price'] * $row['stock_quantity'], 2); ?>
                                                </td>
                                                <td>
                                                    <?php if ($row['stock_quantity'] == 0): ?>
                                                        <span class="badge bg-danger">Out of Stock</span>
                                                    <?php elseif ($row['stock_quantity'] <= $row['reorder_level']): ?>
                                                        <span class="badge bg-warning">Low Stock</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-success">In Stock</span>
                                                    <?php endif; ?>
                                                </td>
                                            <?php elseif ($report_type === 'low_stock'): ?>
                                                <td>
                                                    <?php echo htmlspecialchars($row['product_name']); ?>
                                                </td>
                                                <td>
                                                    <?php echo htmlspecialchars($row['category_name']); ?>
                                                </td>
                                                <td>
                                                    <?php echo htmlspecialchars($row['supplier_name']); ?>
                                                </td>
                                                <td>
                                                    <?php echo $row['stock_quantity']; ?>
                                                </td>
                                                <td>
                                                    <?php echo $row['reorder_level']; ?>
                                                </td>
                                                <td class="text-danger">
                                                    <?php echo $row['reorder_level'] - $row['stock_quantity']; ?>
                                                </td>
                                            <?php elseif ($report_type === 'profit_loss'): ?>
                                                <td>
                                                    <?php echo htmlspecialchars($row['category']); ?>
                                                </td>
                                                <td>₱
                                                    <?php echo number_format($row['revenue'], 2); ?>
                                                </td>
                                                <td>₱
                                                    <?php echo number_format($row['cogs'], 2); ?>
                                                </td>
                                                <td>₱
                                                    <?php echo number_format($row['revenue'] - $row['cogs'], 2); ?>
                                                </td>
                                                <td>
                                                    <?php echo $row['revenue'] > 0 ? number_format((($row['revenue'] - $row['cogs']) / $row['revenue']) * 100, 1) : 0; ?>%
                                                </td>
                                            <?php elseif ($report_type === 'inventory_valuation'): ?>
                                                <td>
                                                    <?php echo htmlspecialchars($row['product_name']); ?>
                                                </td>
                                                <td>
                                                    <?php echo htmlspecialchars($row['category']); ?>
                                                </td>
                                                <td>
                                                    <?php echo htmlspecialchars($row['supplier']); ?>
                                                </td>
                                                <td>
                                                    <?php echo number_format($row['stock_quantity']); ?>
                                                </td>
                                                <td>₱
                                                    <?php echo number_format($row['price'], 2); ?>
                                                </td>
                                                <td>₱
                                                    <?php echo number_format($row['total_value'], 2); ?>
                                                </td>
                                            <?php elseif ($report_type === 'supplier_performance'): ?>
                                                <td>
                                                    <?php echo htmlspecialchars($row['supplier']); ?>
                                                </td>
                                                <td>
                                                    <?php echo number_format($row['products_supplied']); ?>
                                                </td>
                                                <td>
                                                    <?php echo number_format($row['total_stock']); ?>
                                                </td>
                                                <td>₱
                                                    <?php echo number_format($row['inventory_value'], 2); ?>
                                                </td>
                                                <td>₱
                                                    <?php echo number_format($row['sales_revenue'], 2); ?>
                                                </td>
                                                <td>
                                                    <?php echo number_format($row['transactions']); ?>
                                                </td>
                                            <?php elseif ($report_type === 'abc_analysis'): ?>
                                                <td>
                                                    <?php echo htmlspecialchars($row['product_name']); ?>
                                                </td>
                                                <td>
                                                    <?php echo htmlspecialchars($row['category']); ?>
                                                </td>
                                                <td>₱
                                                    <?php echo number_format($row['revenue'], 2); ?>
                                                </td>
                                                <td>
                                                    <?php echo number_format($row['units_sold']); ?>
                                                </td>
                                                <td>
                                                    <?php echo number_format($row['stock_quantity']); ?>
                                                </td>
                                                <td>₱
                                                    <?php echo number_format($row['inventory_value'], 2); ?>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?php
                                                    echo $row['abc_class'] === 'A' ? 'success' :
                                                        ($row['abc_class'] === 'B' ? 'warning' : 'secondary');
                                                    ?>">
                                                        Class
                                                        <?php echo $row['abc_class']; ?>
                                                    </span>
                                                </td>
                                            <?php elseif ($report_type === 'slow_moving'): ?>
                                                <td>
                                                    <?php echo htmlspecialchars($row['product_name']); ?>
                                                </td>
                                                <td>
                                                    <?php echo htmlspecialchars($row['category']); ?>
                                                </td>
                                                <td>
                                                    <?php echo number_format($row['stock_quantity']); ?>
                                                </td>
                                                <td>₱
                                                    <?php echo number_format($row['price'], 2); ?>
                                                </td>
                                                <td>₱
                                                    <?php echo number_format($row['inventory_value'], 2); ?>
                                                </td>
                                                <td>
                                                    <?php echo number_format($row['units_sold']); ?>
                                                </td>
                                                <td>
                                                    <?php echo number_format($row['days_in_inventory']); ?> days
                                                </td>
                                            <?php elseif ($report_type === 'tax_report'): ?>
                                                <td>
                                                    <?php echo date('M d, Y', strtotime($row['sale_date'])); ?>
                                                </td>
                                                <td>₱
                                                    <?php echo number_format($row['daily_sales'], 2); ?>
                                                </td>
                                                <td>₱
                                                    <?php echo number_format($row['vat_amount'], 2); ?>
                                                </td>
                                                <td>₱
                                                    <?php echo number_format($row['daily_sales'] - $row['vat_amount'], 2); ?>
                                                </td>
                                            <?php elseif ($report_type === 'turnover_analysis'): ?>
                                                <td>
                                                    <?php echo htmlspecialchars($row['product_name']); ?>
                                                </td>
                                                <td>
                                                    <?php echo number_format($row['stock_quantity']); ?>
                                                </td>
                                                <td>
                                                    <?php echo number_format($row['total_sold']); ?>
                                                </td>
                                                <td>₱
                                                    <?php echo number_format($row['revenue'], 2); ?>
                                                </td>
                                                <td>
                                                    <?php echo number_format($row['turnover_ratio'], 2); ?>
                                                </td>
                                                <td>
                                                    <?php if ($row['turnover_ratio'] > 2): ?>
                                                        <span class="badge bg-success">High</span>
                                                    <?php elseif ($row['turnover_ratio'] > 0.5): ?>
                                                        <span class="badge bg-warning">Medium</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-danger">Low</span>
                                                    <?php endif; ?>
                                                </td>
                                            <?php elseif ($report_type === 'profit_analysis'): ?>
                                                <td>
                                                    <?php echo htmlspecialchars($row['product_name']); ?>
                                                </td>
                                                <td>₱
                                                    <?php echo number_format($row['selling_price'], 2); ?>
                                                </td>
                                                <td>₱
                                                    <?php echo number_format($row['estimated_cost'], 2); ?>
                                                </td>
                                                <td>₱
                                                    <?php echo number_format($row['estimated_profit_per_unit'], 2); ?>
                                                </td>
                                                <td>
                                                    <?php echo number_format($row['units_sold']); ?>
                                                </td>
                                                <td>₱
                                                    <?php echo number_format($row['estimated_profit'], 2); ?>
                                                </td>
                                                <td>
                                                    <?php echo number_format($row['profit_margin_percent'], 1); ?>%
                                                </td>
                                            <?php endif; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="no-print">
                <?php include 'includes/chatbot.php'; ?>
            </div>

            <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
            <script src="js/chatbot.js"></script>
            <script>
                function exportToCSV() {
                    const table = document.getElementById('reportTable');
                    if (!table) return;

                    let csv = [];

                    // Add report title and metadata
                    csv.push('<?php echo addslashes($report_title); ?>');
                    csv.push('Generated on: <?php echo date('F d, Y H:i:s'); ?>');
            <?php if (in_array($report_type, ['sales', 'profit_loss', 'supplier_performance', 'abc_analysis', 'slow_moving', 'tax_report', 'turnover_analysis', 'profit_analysis'])): ?>
                            csv.push('Period: <?php echo date('M d, Y', strtotime($date_from)) . ' - ' . date('M d, Y', strtotime($date_to)); ?>');
            <?php endif; ?>
                        csv.push('');

            // Add summary data
            <?php if (!empty($report_summary)): ?>
                            csv.push('SUMMARY');
                <?php foreach ($report_summary as $label => $value): ?>
                                csv.push('<?php echo addslashes($label); ?>: <?php echo is_numeric($value) ? number_format($value, 2) : addslashes($value); ?>');
                <?php endforeach; ?>
                            csv.push('');
            <?php endif; ?>

            // Add table data
            for (let row of table.rows) {
                        let cols = [];
                        for (let cell of row.cells) {
                            cols.push('"' + cell.innerText.replace(/"/g, '""') + '"');
                        }
                        csv.push(cols.join(','));
                    }

                    const csvContent = csv.join('\n');
                    const blob = new Blob([csvContent], { type: 'text/csv' });
                    const url = window.URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = url;
                    a.download = '<?php echo strtolower(str_replace(' ', '_', $report_title)); ?>_<?php echo date('Y-m-d'); ?>.csv';
                    a.click();
                    window.URL.revokeObjectURL(url);
                }

                function shareReport() {
                    if (navigator.share) {
                        navigator.share({
                            title: '<?php echo addslashes($report_title); ?>',
                            text: 'Check out this report from our inventory system',
                            url: window.location.href
                        });
                    } else {
                        // Fallback - copy URL to clipboard
                        navigator.clipboard.writeText(window.location.href).then(() => {
                            alert('Report URL copied to clipboard!');
                        });
                    }
                }

                // Add table row hover effects
                document.addEventListener('DOMContentLoaded', function () {
                    const tableRows = document.querySelectorAll('#reportTable tbody tr');
                    tableRows.forEach((row, index) => {
                        row.style.animationDelay = `${index * 0.02}s`;

                        row.addEventListener('mouseenter', function () {
                            this.style.transform = 'translateX(3px)';
                            this.style.transition = 'all 0.3s ease';
                        });

                        row.addEventListener('mouseleave', function () {
                            this.style.transform = 'translateX(0)';
                        });
                    });
                });
            </script>
            <script src="js/chatbot.js?v=<?php echo time(); ?>"></script>
            <script src="js/mobile.js?v=<?php echo time(); ?>"></script>
</body>

</html>