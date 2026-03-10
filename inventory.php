<?php
require_once 'config/database.php';
require_once 'config/session.php';
requireLogin();

$database = new Database();
$db = $database->getConnection();

// Handle stock operations
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        die('Invalid CSRF token');
    }
    
    $product_id = $_POST['product_id'];
    $quantity = (int)$_POST['quantity'];
    $action = $_POST['action'];
    
    if ($action === 'stock_in') {
        $db->query("UPDATE products SET stock_quantity = stock_quantity + $quantity WHERE id = $product_id");
        $stmt = $db->prepare("INSERT INTO inventory_logs (product_id, action, quantity, user_id, notes) VALUES (:pid, 'stock_in', :qty, :uid, :notes)");
        $stmt->execute([':pid' => $product_id, ':qty' => $quantity, ':uid' => $_SESSION['user_id'], ':notes' => $_POST['notes'] ?? '']);
    } elseif ($action === 'stock_out') {
        $db->query("UPDATE products SET stock_quantity = stock_quantity - $quantity WHERE id = $product_id");
        $stmt = $db->prepare("INSERT INTO inventory_logs (product_id, action, quantity, user_id, notes) VALUES (:pid, 'stock_out', :qty, :uid, :notes)");
        $stmt->execute([':pid' => $product_id, ':qty' => $quantity, ':uid' => $_SESSION['user_id'], ':notes' => $_POST['notes'] ?? '']);
        
        // Record sale
        $price = $db->query("SELECT price FROM products WHERE id = $product_id")->fetchColumn();
        $stmt = $db->prepare("INSERT INTO sales (product_id, quantity, total_price) VALUES (:pid, :qty, :total)");
        $stmt->execute([':pid' => $product_id, ':qty' => $quantity, ':total' => $price * $quantity]);
    }
    
    header("Location: inventory.php?success=1");
    exit();
}

// Get inventory logs
$logs = $db->query("SELECT il.*, p.product_name, u.username FROM inventory_logs il JOIN products p ON il.product_id = p.id LEFT JOIN users u ON il.user_id = u.id ORDER BY il.created_at DESC LIMIT 50")->fetchAll(PDO::FETCH_ASSOC);

// Get products for dropdown
$products = $db->query("SELECT id, product_name, stock_quantity FROM products ORDER BY product_name")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory Management</title>
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
                    <h1 class="h2">Inventory Management</h1>
                    <div>
                        <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#stockInModal">Stock In</button>
                        <button class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#stockOutModal">Stock Out</button>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">Inventory Logs</div>
                    <div class="card-body">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th>Action</th>
                                    <th>Quantity</th>
                                    <th>User</th>
                                    <th>Notes</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($logs as $log): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($log['product_name']); ?></td>
                                        <td>
                                            <span class="badge <?php echo $log['action'] === 'stock_in' ? 'bg-success' : 'bg-warning'; ?>">
                                                <?php echo strtoupper(str_replace('_', ' ', $log['action'])); ?>
                                            </span>
                                        </td>
                                        <td><?php echo $log['quantity']; ?></td>
                                        <td><?php echo htmlspecialchars($log['username']); ?></td>
                                        <td><?php echo htmlspecialchars($log['notes']); ?></td>
                                        <td><?php echo date('M d, Y H:i', strtotime($log['created_at'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Stock In Modal -->
    <div class="modal fade" id="stockInModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="action" value="stock_in">
                    <div class="modal-header">
                        <h5 class="modal-title">Stock In</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Product</label>
                            <select name="product_id" class="form-select" required>
                                <?php foreach ($products as $p): ?>
                                    <option value="<?php echo $p['id']; ?>"><?php echo htmlspecialchars($p['product_name']); ?> (Stock: <?php echo $p['stock_quantity']; ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Quantity</label>
                            <input type="number" name="quantity" class="form-control" min="1" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Notes</label>
                            <textarea name="notes" class="form-control" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-success">Add Stock</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Stock Out Modal -->
    <div class="modal fade" id="stockOutModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="action" value="stock_out">
                    <div class="modal-header">
                        <h5 class="modal-title">Stock Out</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Product</label>
                            <select name="product_id" class="form-select" required>
                                <?php foreach ($products as $p): ?>
                                    <option value="<?php echo $p['id']; ?>"><?php echo htmlspecialchars($p['product_name']); ?> (Stock: <?php echo $p['stock_quantity']; ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Quantity</label>
                            <input type="number" name="quantity" class="form-control" min="1" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Notes</label>
                            <textarea name="notes" class="form-control" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-warning">Remove Stock</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php include 'includes/chatbot.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/chatbot.js"></script>
</body>
</html>
