<?php
require_once 'config/database.php';
require_once 'config/session.php';
requireLogin();

$database = new Database();
$db = $database->getConnection();

// Ensure reservations table exists
try {
    $db->exec("CREATE TABLE IF NOT EXISTS reservations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        reservation_id VARCHAR(50) UNIQUE,
        product_id INT NOT NULL,
        quantity INT NOT NULL,
        status ENUM('active', 'completed', 'cancelled') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
    )");
} catch (Exception $e) {
    // Table creation failed, redirect to fix script
    header("Location: fix_reservations_table.php");
    exit();
}

// Handle reservation actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        die('Invalid CSRF token');
    }
    
    if ($_POST['action'] === 'cancel' && isset($_POST['reservation_id'])) {
        $reservation_id = $_POST['reservation_id'];
        
        // Get reservation details
        $stmt = $db->prepare("SELECT * FROM reservations WHERE reservation_id = :res_id AND status = 'active'");
        $stmt->execute([':res_id' => $reservation_id]);
        $reservation = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($reservation) {
            // Return stock
            $stmt = $db->prepare("UPDATE products SET stock_quantity = stock_quantity + :qty WHERE id = :pid");
            $stmt->execute([':qty' => $reservation['quantity'], ':pid' => $reservation['product_id']]);
            
            // Cancel reservation
            $stmt = $db->prepare("UPDATE reservations SET status = 'cancelled' WHERE reservation_id = :res_id");
            $stmt->execute([':res_id' => $reservation_id]);
            
            // Log the cancellation
            $stmt = $db->prepare("INSERT INTO inventory_logs (product_id, action, quantity, user_id, notes) 
                                  VALUES (:pid, 'stock_in', :qty, :uid, :notes)");
            $stmt->execute([
                ':pid' => $reservation['product_id'],
                ':qty' => $reservation['quantity'],
                ':uid' => $_SESSION['user_id'],
                ':notes' => "Reservation cancelled: $reservation_id"
            ]);
            
            header("Location: reservations.php?success=cancelled");
            exit();
        }
    }
    
    if ($_POST['action'] === 'complete' && isset($_POST['reservation_id'])) {
        $reservation_id = $_POST['reservation_id'];
        
        // Mark as completed
        $stmt = $db->prepare("UPDATE reservations SET status = 'completed' WHERE reservation_id = :res_id");
        $stmt->execute([':res_id' => $reservation_id]);
        
        header("Location: reservations.php?success=completed");
        exit();
    }
}

// Get reservations
$filter = $_GET['filter'] ?? 'active';
$reservations = $db->prepare("SELECT r.*, p.product_name, p.price 
                              FROM reservations r 
                              JOIN products p ON r.product_id = p.id 
                              WHERE r.status = :status 
                              ORDER BY r.created_at DESC");
$reservations->execute([':status' => $filter]);
$reservations = $reservations->fetchAll(PDO::FETCH_ASSOC);

// Get statistics
$stats = $db->query("SELECT 
    COUNT(CASE WHEN status = 'active' THEN 1 END) as active,
    COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed,
    COUNT(CASE WHEN status = 'cancelled' THEN 1 END) as cancelled,
    SUM(CASE WHEN status = 'active' THEN r.quantity * p.price END) as active_value
    FROM reservations r 
    JOIN products p ON r.product_id = p.id")->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reservations - Inventory System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="css/style.css?v=<?php echo time(); ?>">
</head>
<body>
    <?php include 'includes/navbar.php'; ?>
    
    <div class="container-fluid">
        <div class="sidebar-overlay"></div>
        <?php include 'includes/sidebar.php'; ?>
        
        <main>
            <?php if (isset($_GET['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php if ($_GET['success'] === 'cancelled'): ?>
                        Reservation cancelled successfully and stock returned.
                    <?php elseif ($_GET['success'] === 'completed'): ?>
                        Reservation marked as completed.
                    <?php endif; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2"><i class="bi bi-bookmark"></i> Reservations</h1>
                <div>
                    <div class="btn-group" role="group">
                        <a href="?filter=active" class="btn btn-outline-secondary <?php echo $filter === 'active' ? 'active' : ''; ?>">
                            <i class="bi bi-bookmark-check me-1"></i> Active
                        </a>
                        <a href="?filter=completed" class="btn btn-outline-secondary <?php echo $filter === 'completed' ? 'active' : ''; ?>">
                            <i class="bi bi-check-circle me-1"></i> Completed
                        </a>
                        <a href="?filter=cancelled" class="btn btn-outline-secondary <?php echo $filter === 'cancelled' ? 'active' : ''; ?>">
                            <i class="bi bi-x-circle me-1"></i> Cancelled
                        </a>
                    </div>
                    <!-- Tertiary: Utility action -->
                    <button class="btn btn-ghost ms-2" onclick="exportReservations()" title="Export Reservations">
                        <i class="bi bi-file-earmark-spreadsheet"></i>
                    </button>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card text-white bg-primary">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-title mb-0">Active</h6>
                                    <h2 class="mb-0 mt-2"><?php echo $stats['active']; ?></h2>
                                </div>
                                <i class="bi bi-bookmark-check" style="font-size: 2.5rem; opacity: 0.3;"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-white bg-success">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-title mb-0">Completed</h6>
                                    <h2 class="mb-0 mt-2"><?php echo $stats['completed']; ?></h2>
                                </div>
                                <i class="bi bi-check-circle" style="font-size: 2.5rem; opacity: 0.3;"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-white bg-danger">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-title mb-0">Cancelled</h6>
                                    <h2 class="mb-0 mt-2"><?php echo $stats['cancelled']; ?></h2>
                                </div>
                                <i class="bi bi-x-circle" style="font-size: 2.5rem; opacity: 0.3;"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-white bg-warning">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-title mb-0">Active Value</h6>
                                    <h2 class="mb-0 mt-2">₱<?php echo number_format($stats['active_value'] ?? 0, 2); ?></h2>
                                </div>
                                <i class="bi bi-currency-exchange" style="font-size: 2.5rem; opacity: 0.3;"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><?php echo ucfirst($filter); ?> Reservations</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($reservations)): ?>
                        <div class="text-center text-muted py-5">
                            <i class="bi bi-bookmark" style="font-size: 3rem;"></i>
                            <p class="mt-2">No <?php echo $filter; ?> reservations found.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Reservation ID</th>
                                        <th>Product</th>
                                        <th>Quantity</th>
                                        <th>Total Value</th>
                                        <th>Created</th>
                                        <th>Status</th>
                                        <?php if ($filter === 'active'): ?>
                                            <th>Actions</th>
                                        <?php endif; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($reservations as $reservation): ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($reservation['reservation_id']); ?></strong></td>
                                            <td><?php echo htmlspecialchars($reservation['product_name']); ?></td>
                                            <td><?php echo $reservation['quantity']; ?> units</td>
                                            <td>₱<?php echo number_format($reservation['price'] * $reservation['quantity'], 2); ?></td>
                                            <td><?php echo date('M d, Y H:i', strtotime($reservation['created_at'])); ?></td>
                                            <td>
                                                <span class="badge bg-<?php 
                                                    echo $reservation['status'] === 'active' ? 'primary' : 
                                                        ($reservation['status'] === 'completed' ? 'success' : 'danger'); 
                                                ?>">
                                                    <?php echo ucfirst($reservation['status']); ?>
                                                </span>
                                            </td>
                                            <?php if ($filter === 'active'): ?>
                                                <td>
                                                    <div class="action-buttons">
                                                        <form method="POST" style="display: inline;">
                                                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                                            <input type="hidden" name="action" value="complete">
                                                            <input type="hidden" name="reservation_id" value="<?php echo $reservation['reservation_id']; ?>">
                                                            <button type="submit" class="btn btn-icon" title="Mark as completed">
                                                                <i class="bi bi-check"></i>
                                                            </button>
                                                        </form>
                                                        <form method="POST" style="display: inline;">
                                                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                                            <input type="hidden" name="action" value="cancel">
                                                            <input type="hidden" name="reservation_id" value="<?php echo $reservation['reservation_id']; ?>">
                                                            <button type="submit" class="btn btn-icon btn-danger-hover" 
                                                                    onclick="return confirm('Cancel this reservation? Stock will be returned.')" 
                                                                    title="Cancel reservation">
                                                                <i class="bi bi-x"></i>
                                                            </button>
                                                        </form>
                                                    </div>
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
        </main>
    </div>

    <?php include 'includes/chatbot.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/chatbot.js?v=<?php echo time(); ?>"></script>
    <script src="js/mobile.js?v=<?php echo time(); ?>"></script>
    <script>
        // Export reservations data
        function exportReservations() {
            const reservations = <?php echo json_encode($reservations); ?>;
            const csv = [
                'Reservations Export - ' + new Date().toLocaleDateString(),
                'Filter: <?php echo ucfirst($filter); ?>',
                '',
                'Reservation ID,Product,Quantity,Total Value,Created,Status'
            ];
            
            reservations.forEach(r => {
                const totalValue = (r.price * r.quantity).toFixed(2);
                csv.push(`"${r.reservation_id}","${r.product_name}",${r.quantity},₱${totalValue},"${r.created_at}","${r.status}"`);
            });
            
            const csvContent = csv.join('\n');
            const blob = new Blob([csvContent], { type: 'text/csv' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'reservations_<?php echo $filter; ?>_' + new Date().toISOString().split('T')[0] + '.csv';
            a.click();
            window.URL.revokeObjectURL(url);
        }
    </script>
</body>
</html>