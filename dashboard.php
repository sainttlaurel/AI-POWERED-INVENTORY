<?php
require_once 'config/database.php';
require_once 'config/session.php';

requireLogin();

$db = new Database();
$conn = $db->getConnection();

// ── Stats ─────────────────────────────────────────────────────────────────
$total_products = $conn->query("SELECT COUNT(*) FROM products")->fetchColumn();

$low_stock   = 0;
$out_of_stock = 0;
$total_value  = 0;

$all_products = $conn->query("SELECT stock_quantity, reorder_level, price FROM products")->fetchAll(PDO::FETCH_ASSOC);
foreach ($all_products as $p) {
    if ($p['stock_quantity'] == 0)                         $out_of_stock++;
    elseif ($p['stock_quantity'] <= $p['reorder_level'])   $low_stock++;
    $total_value += $p['price'] * $p['stock_quantity'];
}

// Today's sales
$today_sales  = 0;
$recent_sales = [];
try {
    $today_sales = $conn->query("SELECT COALESCE(SUM(total_amount),0) FROM invoices WHERE DATE(created_at)=CURDATE()")->fetchColumn();
    $recent_sales = $conn->query("SELECT i.id, i.total_amount, i.created_at, COUNT(ii.id) as item_count
        FROM invoices i
        LEFT JOIN invoice_items ii ON i.id = ii.invoice_id
        GROUP BY i.id
        ORDER BY i.created_at DESC LIMIT 8")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { /* invoices table may not exist yet */ }

// Low-stock products (top 6)
$low_stock_products = $conn->query("
    SELECT product_name, stock_quantity, reorder_level, price
    FROM products WHERE stock_quantity <= reorder_level
    ORDER BY stock_quantity ASC LIMIT 6
")->fetchAll(PDO::FETCH_ASSOC);

// Category distribution
$categories_data = $conn->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
$products_data   = $conn->query("SELECT category_id, price, stock_quantity FROM products")->fetchAll(PDO::FETCH_ASSOC);
$categories      = [];
foreach ($categories_data as $cat) {
    $count = $val = 0;
    foreach ($products_data as $p) {
        if ($p['category_id'] == $cat['id']) { $count++; $val += $p['price'] * $p['stock_quantity']; }
    }
    $categories[] = ['name' => $cat['name'], 'count' => $count, 'value' => $val];
}
usort($categories, fn($a,$b) => $b['count'] - $a['count']);

// Monthly sales (last 6 months)
$monthly_data = [];
for ($i = 5; $i >= 0; $i--) {
    $monthly_data[] = ['month' => date('M Y', strtotime("-$i months")), 'total' => 0, 'profit' => 0];
}
try {
    $monthly_result = $conn->query("
        SELECT 
            DATE_FORMAT(i.created_at,'%b %Y') as month, 
            SUM(ii.subtotal) as total,
            SUM(ii.subtotal - (COALESCE(p.cost_price, 0) * ii.quantity)) as profit
        FROM invoice_items ii
        JOIN invoices i ON ii.invoice_id = i.id
        LEFT JOIN products p ON ii.product_id = p.id
        WHERE i.created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH) AND i.payment_status = 'paid'
        GROUP BY DATE_FORMAT(i.created_at,'%b %Y')
        ORDER BY MIN(i.created_at)")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($monthly_result as $row) {
        foreach ($monthly_data as &$m) {
            if ($m['month'] === $row['month']) {
                $m['total'] = (float)$row['total'];
                $m['profit'] = (float)$row['profit'];
            }
        }
    }
} catch (Exception $e) {}

// Weekly sales (last 7 days)
$salesData = [];
for ($i = 6; $i >= 0; $i--) {
    $salesData[] = ['date' => date('D', strtotime("-$i days")), 'total' => 0];
}

// Total categories count
$total_categories = count($categories_data);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php
$page_title = 'Dashboard — InvenAI';
$extra_head = '<style>
    /* Dashboard-specific overrides */
    .chart-container { position: relative; height: 220px; }';
include 'includes/head.php';
?>

        /* Stats grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        @media (max-width: 1100px) { .stats-grid { grid-template-columns: repeat(2,1fr); } }
        @media (max-width: 576px)  { .stats-grid { grid-template-columns: 1fr; } }

        /* Quick actions grid */
        .quick-actions-grid {
            display: grid;
            grid-template-columns: repeat(4,1fr);
            gap: 0.75rem;
        }
        @media (max-width: 900px) { .quick-actions-grid { grid-template-columns: repeat(2,1fr); } }

        /* Low-stock progress bars */
        .stock-bar-wrap { flex:1; }
        .stock-bar {
            height: 6px;
            background: rgba(255,255,255,0.06);
            border-radius: 3px;
            overflow: hidden;
            margin-top: 4px;
        }
        .stock-bar-fill {
            height: 100%;
            border-radius: 3px;
            transition: width 1s cubic-bezier(0.4,0,0.2,1);
        }

        /* Welcome banner */
        .welcome-banner {
            background: linear-gradient(135deg, rgba(99,102,241,0.15) 0%, rgba(6,182,212,0.08) 100%);
            border: 1px solid rgba(99,102,241,0.2);
            border-radius: var(--border-radius-lg);
            padding: 1.5rem 2rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 1rem;
            position: relative;
            overflow: hidden;
        }
        .welcome-banner::before {
            content: '';
            position: absolute;
            right: -40px; top: -40px;
            width: 200px; height: 200px;
            background: radial-gradient(circle, rgba(99,102,241,0.12) 0%, transparent 60%);
            pointer-events: none;
        }
        .welcome-time {
            font-size: 0.8rem;
            color: var(--text-muted);
            font-variant-numeric: tabular-nums;
        }
        .welcome-title {
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--text-primary);
        }
        .welcome-sub {
            font-size: 0.875rem;
            color: var(--text-secondary);
            margin-top: 0.2rem;
        }

        /* Alert pulse for critical cards */
        .stat-card.rose:hover {
            box-shadow: var(--shadow-lg), 0 0 30px rgba(244,63,94,0.2);
        }

        /* Chart cards */
        .chart-card { background: var(--bg-card); border: 1px solid var(--border-subtle); border-radius: var(--border-radius-lg); overflow: hidden; }
        .chart-card .card-header {
            background: rgba(255,255,255,0.03);
            border-bottom: 1px solid var(--border-subtle);
            padding: 1rem 1.25rem;
            font-weight: 600;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .chart-card .card-body { padding: 1rem 1.25rem; }

        /* Stock item row */
        .stock-item {
            padding: 0.65rem 0;
            border-bottom: 1px solid var(--border-subtle);
            display: flex;
            align-items: center;
            gap: 0.75rem;
            transition: all 0.2s;
        }
        .stock-item:last-child { border-bottom: none; }
        .stock-item:hover { padding-left: 0.3rem; }
        .stock-dot {
            width: 8px; height: 8px;
            border-radius: 50%;
            flex-shrink: 0;
        }
        .stock-name { font-size: 0.875rem; font-weight: 500; color: var(--text-primary); flex:1; min-width:0; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
        .stock-qty  { font-size: 0.8rem; color: var(--text-muted); white-space:nowrap; }
    </style>
</head>
<body>
    <?php include 'includes/navbar.php'; ?>

    <div class="container-fluid">
        <?php include 'includes/sidebar.php'; ?>

        <main id="mainContent">

            <!-- Welcome Banner -->
            <div class="welcome-banner reveal">
                <div>
                    <div class="welcome-time" id="dashboard-time"><?php echo date('l, F j, Y'); ?></div>
                    <div class="welcome-title">
                        <?php
                        $hour = (int)date('H');
                        $greeting = $hour < 12 ? '🌅 Good Morning' : ($hour < 18 ? '☀️ Good Afternoon' : '🌙 Good Evening');
                        echo $greeting . ', ' . htmlspecialchars($_SESSION['username'] ?? 'Admin') . '!';
                        ?>
                    </div>
                    <div class="welcome-sub">Here's your inventory overview for today.</div>
                </div>
                <div class="d-flex gap-2 flex-wrap">
                    <button onclick="window.print()" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-printer"></i> Print
                    </button>
                    <button onclick="exportDashboard()" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-file-earmark-spreadsheet"></i> Export
                    </button>
                    <a href="reports.php" class="btn btn-primary btn-sm">
                        <i class="bi bi-graph-up"></i> View Reports
                    </a>
                </div>
            </div>

            <!-- ── Stat Cards ───────────────────────────────────────────── -->
            <div class="stats-grid">

                <!-- Today's Sales -->
                <div class="stat-card emerald reveal" style="--card-gradient:var(--grad-emerald);">
                    <div class="stat-icon" style="background:var(--grad-emerald);">
                        <i class="bi bi-cart-check" style="color:white;"></i>
                    </div>
                    <div class="stat-label">Today's Sales</div>
                    <div class="stat-value" style="font-size:1.5rem;" data-count-up
                         data-count="<?php echo $today_sales; ?>" data-prefix="₱" data-decimals="2">
                        ₱<?php echo number_format($today_sales, 2); ?>
                    </div>
                    <div class="stat-desc">
                        <span class="stat-trend up"><i class="bi bi-arrow-up"></i> Generated today</span>
                    </div>
                </div>

                <!-- Total Products -->
                <div class="stat-card primary reveal">
                    <div class="stat-icon">
                        <i class="bi bi-box-seam" style="color:white;"></i>
                    </div>
                    <div class="stat-label">Total Products</div>
                    <div class="stat-value" data-count-up data-count="<?php echo $total_products; ?>">
                        <?php echo number_format($total_products); ?>
                    </div>
                    <div class="stat-desc">
                        <span class="stat-trend up"><i class="bi bi-arrow-up"></i> Active SKUs</span>
                    </div>
                </div>

                <!-- Total Value -->
                <div class="stat-card cyan reveal" style="--card-gradient:var(--grad-cyan);">
                    <div class="stat-icon" style="background:var(--grad-cyan);">
                        <i class="bi bi-currency-exchange" style="color:white;"></i>
                    </div>
                    <div class="stat-label">Inventory Value</div>
                    <div class="stat-value" style="font-size:1.5rem;" data-count-up
                         data-count="<?php echo $total_value; ?>" data-prefix="₱" data-decimals="2">
                        ₱<?php echo number_format($total_value, 2); ?>
                    </div>
                    <div class="stat-desc">Total stock worth</div>
                </div>

                <!-- Low Stock -->
                <div class="stat-card amber reveal" style="--card-gradient:var(--grad-amber);">
                    <div class="stat-icon" style="background:var(--grad-amber);">
                        <i class="bi bi-exclamation-triangle" style="color:white;"></i>
                    </div>
                    <div class="stat-label">Low Stock</div>
                    <div class="stat-value" data-count-up data-count="<?php echo $low_stock; ?>">
                        <?php echo $low_stock; ?>
                    </div>
                    <div class="stat-desc">
                        <?php if ($low_stock > 0): ?>
                        <span class="stat-trend down"><i class="bi bi-exclamation-circle"></i> Need reorder</span>
                        <?php else: ?>
                        <span class="stat-trend up"><i class="bi bi-check-circle"></i> All good</span>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Out of Stock -->
                <div class="stat-card rose reveal" style="--card-gradient:var(--grad-rose); <?php echo $out_of_stock > 0 ? '' : ''; ?>">
                    <div class="stat-icon" style="background:var(--grad-rose);">
                        <i class="bi bi-x-circle" style="color:white;"></i>
                    </div>
                    <div class="stat-label">Out of Stock</div>
                    <div class="stat-value" data-count-up data-count="<?php echo $out_of_stock; ?>">
                        <?php echo $out_of_stock; ?>
                    </div>
                    <div class="stat-desc">
                        <?php if ($out_of_stock > 0): ?>
                        <span class="stat-trend down"><i class="bi bi-dash-circle"></i> Critical items</span>
                        <?php else: ?>
                        <span class="stat-trend up"><i class="bi bi-check-circle"></i> Fully stocked</span>
                        <?php endif; ?>
                    </div>
                </div>

            </div>

            <!-- ── Quick Actions ─────────────────────────────────────────── -->
            <div class="card mb-4 reveal">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-lightning-charge"></i> Quick Actions</h5>
                </div>
                <div class="card-body">
                    <div class="quick-actions-grid">
                        <a href="products.php" class="quick-action-btn">
                            <i class="bi bi-plus-circle"></i>
                            Add Product
                        </a>
                        <a href="create_invoice.php" class="quick-action-btn">
                            <i class="bi bi-receipt"></i>
                            New Invoice
                        </a>
                        <a href="inventory.php" class="quick-action-btn">
                            <i class="bi bi-clipboard-data"></i>
                            Manage Stock
                        </a>
                        <a href="reports.php" class="quick-action-btn">
                            <i class="bi bi-bar-chart-line"></i>
                            View Reports
                        </a>
                    </div>
                </div>
            </div>

            <!-- ── Charts Row ─────────────────────────────────────────────── -->
            <div class="row mb-4">
                <div class="col-lg-7 mb-3 mb-lg-0">
                    <div class="chart-card reveal h-100">
                        <div class="card-header">
                            <span><i class="bi bi-graph-up"></i> Sales Trend — Last 7 Days</span>
                            <span class="badge badge-soft-primary">Live</span>
                        </div>
                        <div class="card-body">
                            <div class="chart-container">
                                <canvas id="salesChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-5">
                    <div class="chart-card reveal h-100">
                        <div class="card-header">
                            <span><i class="bi bi-pie-chart"></i> Category Distribution</span>
                        </div>
                        <div class="card-body">
                            <div class="chart-container">
                                <canvas id="categoryChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ── Bottom Row ─────────────────────────────────────────────── -->
            <div class="row mb-4">
                <!-- Monthly Chart -->
                <div class="col-lg-6 mb-3 mb-lg-0">
                    <div class="chart-card reveal">
                        <div class="card-header">
                            <span><i class="bi bi-bar-chart-line"></i> Monthly Sales — Last 6 Months</span>
                        </div>
                        <div class="card-body">
                            <div class="chart-container">
                                <canvas id="monthlyChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Low Stock Alert -->
                <div class="col-lg-6">
                    <div class="chart-card reveal <?php echo $out_of_stock > 0 ? 'glow-danger' : ''; ?>">
                        <div class="card-header">
                            <span>
                                <i class="bi bi-exclamation-triangle" style="color:var(--accent-amber);"></i>
                                Low Stock Alert
                            </span>
                            <?php if (!empty($low_stock_products)): ?>
                            <a href="inventory.php" class="btn btn-outline-secondary btn-sm" style="font-size:0.75rem;">View All</a>
                            <?php endif; ?>
                        </div>
                        <div class="card-body" style="padding:1rem 1.25rem 0.5rem;">
                            <?php if (empty($low_stock_products)): ?>
                            <div class="empty-state" style="padding:1.5rem;">
                                <i class="bi bi-check-circle" style="color:var(--accent-emerald);opacity:1;"></i>
                                <p style="color:var(--accent-emerald);font-weight:600;">All products are well stocked!</p>
                                <small>No reorder needed at this time.</small>
                            </div>
                            <?php else: ?>
                            <?php foreach ($low_stock_products as $p): ?>
                                <?php
                                $pct   = $p['reorder_level'] > 0 ? min(100, round($p['stock_quantity'] / $p['reorder_level'] * 100)) : 0;
                                $color = $p['stock_quantity'] == 0 ? 'var(--accent-rose)' : ($pct < 50 ? 'var(--accent-amber)' : 'var(--accent-emerald)');
                                ?>
                                <div class="stock-item">
                                    <div class="stock-dot" style="background:<?php echo $color; ?>; box-shadow:0 0 6px <?php echo $color; ?>;"></div>
                                    <div class="stock-bar-wrap">
                                        <div style="display:flex;justify-content:space-between;align-items:baseline;">
                                            <span class="stock-name"><?php echo htmlspecialchars($p['product_name']); ?></span>
                                            <span class="stock-qty"><?php echo $p['stock_quantity']; ?> / <?php echo $p['reorder_level']; ?></span>
                                        </div>
                                        <div class="stock-bar">
                                            <div class="stock-bar-fill" style="width:<?php echo $pct; ?>%;background:<?php echo $color; ?>;"></div>
                                        </div>
                                    </div>
                                    <?php if ($p['stock_quantity'] == 0): ?>
                                    <span class="badge badge-soft-danger" style="white-space:nowrap;font-size:0.65rem;">Out</span>
                                    <?php else: ?>
                                    <span class="badge badge-soft-warning" style="white-space:nowrap;font-size:0.65rem;">Low</span>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ── Recent Sales ────────────────────────────────────────────── -->
            <div class="card reveal mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-clock-history"></i> Recent Invoices</h5>
                    <a href="invoices.php" class="btn btn-outline-secondary btn-sm" style="font-size:0.75rem;">View All</a>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($recent_sales)): ?>
                    <div class="empty-state">
                        <i class="bi bi-receipt"></i>
                        <p>No invoices found</p>
                        <small>Create your first invoice to see data here.</small>
                    </div>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-compact table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>#Invoice</th>
                                    <th>Items</th>
                                    <th>Total</th>
                                    <th>Date</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_sales as $sale): ?>
                                <tr>
                                    <td><span class="badge badge-soft-primary">#<?php echo str_pad($sale['id'], 4, '0', STR_PAD_LEFT); ?></span></td>
                                    <td><span style="color:var(--text-secondary);"><?php echo $sale['item_count']; ?> item<?php echo $sale['item_count'] != 1 ? 's' : ''; ?></span></td>
                                    <td><strong style="color:var(--accent-emerald);">₱<?php echo number_format($sale['total_amount'], 2); ?></strong></td>
                                    <td><small style="color:var(--text-muted);"><?php echo date('M d, H:i', strtotime($sale['created_at'])); ?></small></td>
                                    <td>
                                        <a href="view_invoice.php?id=<?php echo $sale['id']; ?>" class="btn btn-action btn-sm">
                                            <i class="bi bi-eye"></i> View
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
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
    // ── Chart.js dark theme defaults ─────────────────────────────────────
    Chart.defaults.font.family = "'Inter', system-ui, sans-serif";
    Chart.defaults.color       = '#64748b';
    Chart.defaults.borderColor = 'rgba(99,102,241,0.1)';

    const gradientPlugin = {
        id: 'customGradient',
        beforeDraw(chart) {}
    };

    // ── Sales Chart ─────────────────────────────────────────────────────
    const salesCtx = document.getElementById('salesChart').getContext('2d');
    const salesGradient = salesCtx.createLinearGradient(0, 0, 0, 220);
    salesGradient.addColorStop(0, 'rgba(99,102,241,0.35)');
    salesGradient.addColorStop(1, 'rgba(99,102,241,0)');

    new Chart(salesCtx, {
        type: 'line',
        data: {
            labels: <?php echo json_encode(array_column($salesData, 'date')); ?>,
            datasets: [{
                label: 'Sales (₱)',
                data:  <?php echo json_encode(array_column($salesData, 'total')); ?>,
                borderColor: '#6366f1',
                backgroundColor: salesGradient,
                tension: 0.45,
                fill: true,
                pointBackgroundColor: '#6366f1',
                pointBorderColor: '#0f1629',
                pointBorderWidth: 2,
                pointRadius: 5,
                pointHoverRadius: 8,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { intersect: false, mode: 'index' },
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: '#131b2e',
                    borderColor: 'rgba(99,102,241,0.3)',
                    borderWidth: 1,
                    padding: 10,
                    callbacks: { label: ctx => ' ₱' + ctx.parsed.y.toLocaleString() }
                }
            },
            scales: {
                x: { grid: { color: 'rgba(99,102,241,0.07)' }, ticks: { color: '#64748b' } },
                y: {
                    beginAtZero: true,
                    grid: { color: 'rgba(99,102,241,0.07)' },
                    ticks: { color: '#64748b', callback: v => '₱' + v.toLocaleString() }
                }
            }
        }
    });

    // ── Category Chart ──────────────────────────────────────────────────
    const catCtx = document.getElementById('categoryChart').getContext('2d');
    const catLabels = <?php echo json_encode(array_column($categories, 'name')); ?>;
    const catData   = <?php echo json_encode(array_column($categories, 'count')); ?>;

    new Chart(catCtx, {
        type: 'doughnut',
        data: {
            labels: catLabels.length ? catLabels : ['No categories'],
            datasets: [{
                data: catData.length ? catData : [1],
                backgroundColor: [
                    'rgba(99,102,241,0.85)', 'rgba(6,182,212,0.85)',
                    'rgba(16,185,129,0.85)', 'rgba(245,158,11,0.85)',
                    'rgba(244,63,94,0.85)',  'rgba(139,92,246,0.85)',
                ],
                borderColor: '#0f1629',
                borderWidth: 3,
                hoverOffset: 8,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '65%',
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: { padding: 14, color: '#94a3b8', font: { size: 11 }, boxWidth: 12, usePointStyle: true }
                },
                tooltip: {
                    backgroundColor: '#131b2e',
                    borderColor: 'rgba(99,102,241,0.3)',
                    borderWidth: 1,
                    padding: 10,
                }
            }
        }
    });

    // ── Monthly Chart ───────────────────────────────────────────────────
    const monCtx = document.getElementById('monthlyChart').getContext('2d');
    const monGradient = monCtx.createLinearGradient(0, 0, 0, 220);
    monGradient.addColorStop(0, 'rgba(6,182,212,0.8)');
    monGradient.addColorStop(1, 'rgba(6,182,212,0.2)');

    new Chart(monCtx, {
        data: {
            labels: <?php echo json_encode(array_column($monthly_data, 'month')); ?>,
            datasets: [
                {
                    type: 'line',
                    label: 'Net Profit (₱)',
                    data: <?php echo json_encode(array_column($monthly_data, 'profit')); ?>,
                    borderColor: '#10b981', // emerald-500
                    backgroundColor: 'rgba(16, 185, 129, 0.1)',
                    borderWidth: 2,
                    tension: 0.4,
                    fill: false,
                    pointBackgroundColor: '#10b981',
                    order: 0
                },
                {
                    type: 'bar',
                    label: 'Gross Revenue (₱)',
                    data: <?php echo json_encode(array_column($monthly_data, 'total')); ?>,
                    backgroundColor: monGradient,
                    borderColor: '#06b6d4',
                    borderWidth: 2,
                    borderRadius: 8,
                    borderSkipped: false,
                    order: 1
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: true, position: 'top', labels: { boxWidth: 12, usePointStyle: true } },
                tooltip: {
                    backgroundColor: '#131b2e',
                    borderColor: 'rgba(6,182,212,0.3)',
                    borderWidth: 1,
                    padding: 10,
                    callbacks: { label: ctx => ' ₱' + ctx.parsed.y.toLocaleString() }
                }
            },
            scales: {
                x: { grid: { display: false }, ticks: { color: '#64748b' } },
                y: {
                    beginAtZero: true,
                    grid: { color: 'rgba(99,102,241,0.07)' },
                    ticks: { color: '#64748b', callback: v => '₱' + v.toLocaleString() }
                }
            }
        }
    });

    // ── Export CSV ──────────────────────────────────────────────────────
    function exportDashboard() {
        const rows = [
            'InvenAI Dashboard Export — ' + new Date().toLocaleDateString(),
            '',
            'Metric,Value',
            `Total Products,<?php echo $total_products; ?>`,
            `Inventory Value,₱<?php echo number_format($total_value,2); ?>`,
            `Low Stock Items,<?php echo $low_stock; ?>`,
            `Out of Stock Items,<?php echo $out_of_stock; ?>`,
            `Today's Sales,₱<?php echo number_format($today_sales,2); ?>`,
        ];
        const blob = new Blob([rows.join('\n')], { type: 'text/csv' });
        const a    = Object.assign(document.createElement('a'), {
            href: URL.createObjectURL(blob),
            download: 'invenai_dashboard_' + new Date().toISOString().split('T')[0] + '.csv'
        });
        a.click();
        URL.revokeObjectURL(a.href);
        if (window.InvenAI) InvenAI.toast('Dashboard exported successfully!', 'success');
    }
    </script>
</body>
</html>