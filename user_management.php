<?php
require_once 'config/database.php';
require_once 'config/session.php';
require_once 'config/rate_limiter.php';
require_once 'config/error_handler.php';

// Only admins can access user management
if (!isAdmin()) {
    header("Location: dashboard.php?error=Access denied");
    exit();
}

$database = new Database();
$db = $database->getConnection();
$rateLimiter = new RateLimiter($db);

// Check if database is properly set up
try {
    $db_check = $db->query("SHOW TABLES LIKE 'users'");
    if ($db_check->rowCount() == 0) {
        die('<div class="alert alert-warning m-3">
            <h4>Database Setup Required</h4>
            <p>The database tables have not been created yet. Please run the setup script first:</p>
            <a href="setup_advanced.php" class="btn btn-primary">Run Database Setup</a>
        </div>');
    }
    
    // Check if users table has required columns
    $columns_check = $db->query("SHOW COLUMNS FROM users");
    $existing_columns = $columns_check->fetchAll(PDO::FETCH_COLUMN);
    $required_columns = ['email', 'first_name', 'last_name', 'status', 'created_by'];
    $missing_columns = array_diff($required_columns, $existing_columns);
    
    if (!empty($missing_columns)) {
        die('<div class="alert alert-warning m-3">
            <h4>Database Update Required</h4>
            <p>The users table is missing required columns: ' . implode(', ', $missing_columns) . '</p>
            <p>Please run the advanced setup to update your database:</p>
            <a href="setup_advanced.php" class="btn btn-primary">Run Advanced Setup</a>
        </div>');
    }
    
} catch (Exception $e) {
    die('<div class="alert alert-danger m-3">
        <h4>Database Connection Error</h4>
        <p>Could not connect to the database. Please check your database configuration.</p>
        <small>' . htmlspecialchars($e->getMessage()) . '</small>
    </div>');
}

// Rate limiting function
function checkRateLimit($action, $maxAttempts = 5, $timeWindow = 300) {
    global $rateLimiter;
    return $rateLimiter->checkLimit($action, $maxAttempts, $timeWindow);
}

// Simple table creation without foreign key constraints
try {
    // First, let's check if the users table exists and get its structure
    $table_exists = $db->query("SHOW TABLES LIKE 'users'")->rowCount() > 0;
    
    if ($table_exists) {
        // Check current ENUM values
        $columns = $db->query("SHOW COLUMNS FROM users WHERE Field = 'role'")->fetch(PDO::FETCH_ASSOC);
        if ($columns) {
            error_log("Current role column definition: " . $columns['Type']);
            
            // If the ENUM doesn't have the right values, fix it
            if (strpos($columns['Type'], 'manager') === false || strpos($columns['Type'], 'cashier') === false) {
                error_log("Fixing role ENUM values...");
                $db->exec("ALTER TABLE users MODIFY COLUMN role ENUM('admin', 'manager', 'cashier', 'viewer') DEFAULT 'cashier'");
                error_log("Role ENUM fixed");
            }
        }
    } else {
        // Create table with proper ENUM
        $db->exec("CREATE TABLE users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) UNIQUE NOT NULL,
            email VARCHAR(100) UNIQUE NOT NULL,
            password VARCHAR(255) NOT NULL,
            role ENUM('admin', 'manager', 'cashier', 'viewer') DEFAULT 'cashier',
            first_name VARCHAR(50),
            last_name VARCHAR(50),
            phone VARCHAR(20),
            status ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
            last_login TIMESTAMP NULL,
            failed_login_attempts INT DEFAULT 0,
            locked_until TIMESTAMP NULL,
            two_factor_enabled BOOLEAN DEFAULT FALSE,
            two_factor_secret VARCHAR(32) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            created_by INT NULL
        )");
        error_log("Users table created with proper ENUM");
    }
    
    $db->exec("CREATE TABLE IF NOT EXISTS user_permissions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        permission VARCHAR(50) NOT NULL,
        granted_by INT NULL,
        granted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_user_permission (user_id, permission)
    )");
    
    $db->exec("CREATE TABLE IF NOT EXISTS user_activity_log (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NULL,
        action VARCHAR(100) NOT NULL,
        details TEXT NULL,
        ip_address VARCHAR(45),
        user_agent TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_user_date (user_id, created_at),
        INDEX idx_action (action)
    )");
    
    $db->exec("CREATE TABLE IF NOT EXISTS rate_limits (
        id INT AUTO_INCREMENT PRIMARY KEY,
        ip_address VARCHAR(45) NOT NULL,
        action VARCHAR(50) NOT NULL,
        attempts INT DEFAULT 1,
        last_attempt TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        blocked_until TIMESTAMP NULL,
        INDEX idx_ip_action (ip_address, action),
        INDEX idx_blocked_until (blocked_until)
    )");
    
} catch (Exception $e) {
    error_log("User management tables creation error: " . $e->getMessage());
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!checkRateLimit('user_management', 20, 300)) {
        die('Too many requests. Please wait before trying again.');
    }
    
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        die('Invalid CSRF token');
    }
    
    try {
        if ($_POST['action'] === 'create_user') {
            // Debug: Log the incoming role value
            error_log("Creating user with role: '" . $_POST['role'] . "'");
            
            $validation_rules = [
                'username' => ['required' => true, 'min_length' => 3, 'max_length' => 50],
                'email' => ['required' => true, 'type' => 'email'],
                'password' => ['required' => true, 'min_length' => 8],
                'first_name' => ['required' => true, 'max_length' => 50],
                'last_name' => ['required' => true, 'max_length' => 50],
                'role' => ['required' => true]
            ];
            
            $errors = ErrorHandler::validateInput($_POST, $validation_rules);
            if (!empty($errors)) {
                throw new Exception("Validation failed: " . implode(', ', $errors));
            }
            
            // Validate role is one of the allowed values
            $allowed_roles = ['admin', 'manager', 'cashier', 'viewer'];
            if (!in_array($_POST['role'], $allowed_roles)) {
                throw new Exception("Invalid role: " . $_POST['role'] . ". Allowed roles: " . implode(', ', $allowed_roles));
            }
            
            // Check if username or email already exists
            $stmt = $db->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
            $stmt->execute([$_POST['username'], $_POST['email']]);
            if ($stmt->fetch()) {
                throw new Exception("Username or email already exists");
            }
            
            // Create user with explicit role validation
            $role_to_insert = trim($_POST['role']);
            error_log("Inserting role: '" . $role_to_insert . "'");
            
            $stmt = $db->prepare("INSERT INTO users (username, email, password, role, first_name, last_name, phone, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $result = $stmt->execute([
                trim($_POST['username']),
                trim($_POST['email']),
                password_hash($_POST['password'], PASSWORD_DEFAULT),
                $role_to_insert,
                trim($_POST['first_name']),
                trim($_POST['last_name']),
                trim($_POST['phone'] ?? ''),
                $_SESSION['user_id']
            ]);
            
            if (!$result) {
                $error_info = $stmt->errorInfo();
                throw new Exception("Failed to create user: " . implode(' - ', $error_info));
            }
            
            $user_id = $db->lastInsertId();
            
            // Verify the user was created with correct role
            $verify_stmt = $db->prepare("SELECT role FROM users WHERE id = ?");
            $verify_stmt->execute([$user_id]);
            $created_role = $verify_stmt->fetchColumn();
            
            error_log("Role verification - Expected: '" . $role_to_insert . "', Got: '" . $created_role . "'");
            
            if ($created_role !== $role_to_insert) {
                // Try to fix the role if it wasn't set correctly
                error_log("Role mismatch detected, attempting to fix...");
                $fix_stmt = $db->prepare("UPDATE users SET role = ? WHERE id = ?");
                $fix_result = $fix_stmt->execute([$role_to_insert, $user_id]);
                
                if ($fix_result) {
                    // Verify again
                    $verify_stmt->execute([$user_id]);
                    $fixed_role = $verify_stmt->fetchColumn();
                    error_log("Role fix result - Expected: '" . $role_to_insert . "', Got: '" . $fixed_role . "'");
                    
                    if ($fixed_role !== $role_to_insert) {
                        throw new Exception("Role could not be set correctly. Database ENUM may need updating.");
                    }
                } else {
                    throw new Exception("Failed to fix role after creation");
                }
            }
            
            // Add default permissions based on role
            $default_permissions = [
                'admin' => ['manage_users', 'manage_products', 'manage_inventory', 'view_reports', 'manage_settings'],
                'manager' => ['manage_products', 'manage_inventory', 'view_reports'],
                'cashier' => ['manage_inventory', 'view_products'],
                'viewer' => ['view_products', 'view_reports']
            ];
            
            if (isset($default_permissions[$_POST['role']])) {
                foreach ($default_permissions[$_POST['role']] as $permission) {
                    $stmt = $db->prepare("INSERT INTO user_permissions (user_id, permission, granted_by) VALUES (?, ?, ?)");
                    $stmt->execute([$user_id, $permission, $_SESSION['user_id']]);
                }
            }
            
            // Log activity
            $stmt = $db->prepare("INSERT INTO user_activity_log (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)");
            $stmt->execute([
                $_SESSION['user_id'],
                'create_user',
                "Created user: {$_POST['username']} ({$_POST['role']})",
                $_SERVER['REMOTE_ADDR']
            ]);
            
            header("Location: user_management.php?success=" . urlencode("User created successfully"));
            exit();
        }
        
        if ($_POST['action'] === 'update_user') {
            $user_id = (int)$_POST['user_id'];
            $updates = [];
            $params = [];
            
            if (!empty($_POST['first_name'])) {
                $updates[] = "first_name = ?";
                $params[] = trim($_POST['first_name']);
            }
            if (!empty($_POST['last_name'])) {
                $updates[] = "last_name = ?";
                $params[] = trim($_POST['last_name']);
            }
            if (!empty($_POST['email'])) {
                $updates[] = "email = ?";
                $params[] = trim($_POST['email']);
            }
            if (!empty($_POST['phone'])) {
                $updates[] = "phone = ?";
                $params[] = trim($_POST['phone']);
            }
            if (!empty($_POST['role'])) {
                $updates[] = "role = ?";
                $params[] = $_POST['role'];
            }
            if (!empty($_POST['status'])) {
                $updates[] = "status = ?";
                $params[] = $_POST['status'];
            }
            
            if (!empty($updates)) {
                $params[] = $user_id;
                $stmt = $db->prepare("UPDATE users SET " . implode(', ', $updates) . " WHERE id = ?");
                $stmt->execute($params);
            }
            
            header("Location: user_management.php?success=" . urlencode("User updated successfully"));
            exit();
        }
        
        if ($_POST['action'] === 'reset_password') {
            $user_id = (int)$_POST['user_id'];
            $new_password = bin2hex(random_bytes(8)); // Generate random password
            
            $stmt = $db->prepare("UPDATE users SET password = ?, failed_login_attempts = 0, locked_until = NULL WHERE id = ?");
            $stmt->execute([password_hash($new_password, PASSWORD_DEFAULT), $user_id]);
            
            // In a real system, you'd email this to the user
            $_SESSION['temp_password'] = $new_password;
            
            header("Location: user_management.php?success=" . urlencode("Password reset. New password: $new_password"));
            exit();
        }
        
    } catch (Exception $e) {
        error_log("User management error: " . $e->getMessage());
        header("Location: user_management.php?error=" . urlencode($e->getMessage()));
        exit();
    }
}

// Get users with activity info
try {
    $users = $db->query("
        SELECT 
            u.*,
            (SELECT COUNT(*) FROM user_activity_log WHERE user_id = u.id AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)) as recent_activity
        FROM users u
        ORDER BY u.created_at DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    // Debug: Log user data to see what's in the database
    error_log("User data debug: " . print_r($users, true));
    
} catch (Exception $e) {
    $users = [];
    error_log("Error fetching users: " . $e->getMessage());
}

// Get user permissions
$user_permissions = [];
foreach ($users as $user) {
    try {
        $stmt = $db->prepare("SELECT permission FROM user_permissions WHERE user_id = ?");
        $stmt->execute([$user['id']]);
        $user_permissions[$user['id']] = $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (Exception $e) {
        $user_permissions[$user['id']] = [];
    }
}

// Get recent activity
try {
    $recent_activity = $db->query("
        SELECT 
            ual.*,
            u.username,
            u.first_name,
            u.last_name
        FROM user_activity_log ual
        LEFT JOIN users u ON ual.user_id = u.id
        ORDER BY ual.created_at DESC
        LIMIT 50
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $recent_activity = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - Inventory System</title>
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
        
        /* Ensure proper column structure */
        .table-responsive {
            overflow-x: auto;
        }
        
        /* Reset any conflicting styles */
        .table .badge {
            display: inline-block;
            white-space: nowrap;
        }
        
        /* User management specific styling */
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            margin-right: 0.75rem;
        }
        
        .activity-row {
            transition: all 0.3s ease;
        }
        
        .activity-row:hover {
            background: linear-gradient(135deg, rgba(59, 130, 246, 0.05) 0%, rgba(37, 99, 235, 0.05) 100%) !important;
            transform: translateX(2px);
        }
        
        /* Badge animations */
        .badge {
            transition: all 0.2s ease;
        }
        
        .badge:hover {
            transform: scale(1.05);
        }
        
        /* Button hover effects */
        .btn-outline-primary:hover,
        .btn-outline-warning:hover {
            transform: translateY(-1px);
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        
        /* Auto-refresh indicator */
        .form-check-input:checked {
            background-color: #28a745;
            border-color: #28a745;
        }
        
        /* Connection status animation */
        .bi-circle-fill {
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.5; }
            100% { opacity: 1; }
        }

        /* ===== STICKY NOTES STYLING ===== */
        .sticky-notes-container {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 25px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            border: 1px solid #dee2e6;
        }

        .sticky-notes-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #dee2e6;
        }

        .sticky-notes-header h5 {
            margin: 0;
            color: #495057;
            font-weight: 600;
        }

        .notes-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
            min-height: 200px;
        }

        .sticky-note {
            background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%);
            border-radius: 12px;
            padding: 15px;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.15);
            position: relative;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            border: 1px solid #f1c40f;
            min-height: 150px;
            cursor: move;
        }

        .sticky-note:hover {
            transform: translateY(-3px) rotate(1deg);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.2);
        }

        .sticky-note.priority-high {
            background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
            border-color: #dc3545;
        }

        .sticky-note.priority-medium {
            background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%);
            border-color: #ffc107;
        }

        .sticky-note.priority-low {
            background: linear-gradient(135deg, #d1ecf1 0%, #bee5eb 100%);
            border-color: #17a2b8;
        }

        .note-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }

        .note-priority {
            font-size: 0.75rem;
            padding: 2px 8px;
            border-radius: 10px;
            font-weight: 600;
        }

        .note-actions {
            display: flex;
            gap: 5px;
        }

        .note-actions button {
            background: none;
            border: none;
            padding: 2px 6px;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .note-actions button:hover {
            background: rgba(0, 0, 0, 0.1);
        }

        .note-content {
            font-size: 0.9rem;
            line-height: 1.4;
            color: #495057;
            word-wrap: break-word;
        }

        .note-content textarea {
            width: 100%;
            border: none;
            background: transparent;
            resize: none;
            font-family: inherit;
            font-size: inherit;
            line-height: inherit;
            color: inherit;
            min-height: 60px;
        }

        .note-content textarea:focus {
            outline: none;
        }

        .note-footer {
            margin-top: 10px;
            padding-top: 8px;
            border-top: 1px solid rgba(0, 0, 0, 0.1);
            font-size: 0.75rem;
            color: #6c757d;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .note-reminder {
            background: linear-gradient(135deg, #e2e3e5 0%, #d6d8db 100%);
            border: 1px solid #6c757d;
        }

        .note-reminder::before {
            content: '⏰';
            position: absolute;
            top: -5px;
            right: -5px;
            background: #ffc107;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 10px;
            animation: reminderPulse 2s infinite;
        }

        @keyframes reminderPulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.1); }
        }

        .empty-notes {
            grid-column: 1 / -1;
            text-align: center;
            color: #6c757d;
            padding: 40px;
            border: 2px dashed #dee2e6;
            border-radius: 12px;
            background: rgba(255, 255, 255, 0.5);
        }

        .empty-notes i {
            font-size: 3rem;
            margin-bottom: 15px;
            opacity: 0.5;
        }

        /* Responsive design */
        @media (max-width: 768px) {
            .notes-grid {
                grid-template-columns: 1fr;
            }
            
            .sticky-notes-header {
                flex-direction: column;
                gap: 10px;
                align-items: stretch;
            }
        }
    </style>
</head>
<body>
    <?php include 'includes/navbar.php'; ?>
    
    <div class="container-fluid">
        <div class="sidebar-overlay"></div>
        <?php include 'includes/sidebar.php'; ?>
        
        <main>
            <?php if (isset($_GET['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bi bi-check-circle"></i> <?php echo htmlspecialchars($_GET['success']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_GET['error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle"></i> <?php echo htmlspecialchars($_GET['error']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2"><i class="bi bi-people"></i> User Management</h1>
                <div class="d-flex gap-2 align-items-center">
                    <button class="btn btn-warning rounded-pill px-4 py-2" onclick="toggleStickyNotes()" id="notesToggle">
                        <i class="bi bi-sticky me-2"></i> Notes
                    </button>
                    <button class="btn btn-primary rounded-pill px-4 py-2" data-bs-toggle="modal" data-bs-target="#createUserModal">
                        <i class="bi bi-person-plus me-2"></i> Add User
                    </button>
                </div>
            </div>

            <!-- Sticky Notes Container -->
            <div id="stickyNotesContainer" class="sticky-notes-container" style="display: none;">
                <div class="sticky-notes-header">
                    <h5><i class="bi bi-sticky-fill"></i> Quick Notes & Reminders</h5>
                    <div class="d-flex gap-2 align-items-center">
                        <button class="btn btn-sm btn-success rounded-pill px-3 py-1" onclick="addStickyNote()">
                            <i class="bi bi-plus me-1"></i> Add Note
                        </button>
                        <button class="btn btn-sm btn-outline-secondary rounded-pill px-3 py-1" onclick="toggleStickyNotes()">
                            <i class="bi bi-x"></i>
                        </button>
                    </div>
                </div>
                <div id="notesGrid" class="notes-grid">
                    <!-- Notes will be dynamically added here -->
                </div>
            </div>

            <!-- Profit Performance Dashboard -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card bg-success text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h6 class="card-title">Total Profit Potential</h6>
                                    <?php
                                    try {
                                        $profit_query = $db->query("
                                            SELECT 
                                                SUM((price - COALESCE(cost_price, 0)) * stock_quantity) as total_potential_profit,
                                                SUM(COALESCE(cost_price, 0) * stock_quantity) as total_investment,
                                                COUNT(*) as total_products
                                            FROM products 
                                            WHERE cost_price IS NOT NULL AND cost_price > 0
                                        ");
                                        $profit_data = $profit_query->fetch(PDO::FETCH_ASSOC);
                                        $total_potential_profit = $profit_data['total_potential_profit'] ?? 0;
                                        $total_investment = $profit_data['total_investment'] ?? 0;
                                        $total_products = $profit_data['total_products'] ?? 0;
                                    } catch (Exception $e) {
                                        $total_potential_profit = 0;
                                        $total_investment = 0;
                                        $total_products = 0;
                                    }
                                    ?>
                                    <h4>₱<?php echo number_format($total_potential_profit, 0); ?></h4>
                                </div>
                                <div class="align-self-center">
                                    <i class="bi bi-graph-up fs-2"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-info text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h6 class="card-title">Total Investment</h6>
                                    <h4>₱<?php echo number_format($total_investment, 0); ?></h4>
                                </div>
                                <div class="align-self-center">
                                    <i class="bi bi-cash-stack fs-2"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-warning text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h6 class="card-title">Avg Profit Margin</h6>
                                    <?php
                                    try {
                                        $margin_query = $db->query("
                                            SELECT AVG(
                                                CASE 
                                                    WHEN cost_price > 0 THEN ((price - cost_price) / cost_price) * 100 
                                                    ELSE 0 
                                                END
                                            ) as avg_margin
                                            FROM products 
                                            WHERE cost_price IS NOT NULL AND cost_price > 0
                                        ");
                                        $avg_margin = $margin_query->fetchColumn() ?? 0;
                                    } catch (Exception $e) {
                                        $avg_margin = 0;
                                    }
                                    ?>
                                    <h4><?php echo number_format($avg_margin, 1); ?>%</h4>
                                </div>
                                <div class="align-self-center">
                                    <i class="bi bi-percent fs-2"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-primary text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h6 class="card-title">Products Tracked</h6>
                                    <h4><?php echo number_format($total_products); ?></h4>
                                </div>
                                <div class="align-self-center">
                                    <i class="bi bi-box-seam fs-2"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Users Table -->
            <div class="card mb-4">
                <div class="card-header">System Users</div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover" id="usersTable">
                            <thead class="table-dark">
                                <tr>
                                    <th>User</th>
                                    <th>Email</th>
                                    <th>Role</th>
                                    <th>Status</th>
                                    <th>Last Login</th>
                                    <th>Activity (30d)</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $user): ?>
                                    <tr class="user-row">
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="user-avatar">
                                                    <?php echo strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)); ?>
                                                </div>
                                                <div>
                                                    <strong><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></strong>
                                                    <br><small class="text-muted">@<?php echo htmlspecialchars($user['username']); ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                                        <td>
                                            <?php 
                                            $role = trim(strtolower($user['role'] ?? ''));
                                            $role_colors = [
                                                'admin' => 'danger',
                                                'manager' => 'warning', 
                                                'cashier' => 'info',
                                                'viewer' => 'secondary'
                                            ];
                                            $role_color = $role_colors[$role] ?? 'dark';
                                            $role_display = ucfirst($role) ?: 'Unknown';
                                            ?>
                                            <span class="badge bg-<?php echo $role_color; ?>" title="Role: <?php echo htmlspecialchars($user['role']); ?>">
                                                <?php echo htmlspecialchars($role_display); ?>
                                            </span>
                                            <?php if (empty($user['role'])): ?>
                                                <small class="text-muted d-block">Role not set</small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php 
                                                echo $user['status'] === 'active' ? 'success' : 
                                                    ($user['status'] === 'inactive' ? 'secondary' : 'danger'); 
                                            ?>">
                                                <?php echo ucfirst($user['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php echo $user['last_login'] ? date('M d, Y H:i', strtotime($user['last_login'])) : 'Never'; ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-info"><?php echo $user['recent_activity']; ?> actions</span>
                                        </td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                                    <button class="btn btn-sm btn-outline-primary" onclick="editUser(<?php echo htmlspecialchars(json_encode($user)); ?>)" title="Edit User">
                                                        <i class="bi bi-pencil"></i>
                                                    </button>
                                                    <form method="POST" style="display: inline;">
                                                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                                        <input type="hidden" name="action" value="reset_password">
                                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                        <button type="submit" class="btn btn-sm btn-outline-warning" onclick="return confirm('Reset password for this user?')" title="Reset Password">
                                                            <i class="bi bi-key"></i>
                                                        </button>
                                                    </form>
                                                <?php else: ?>
                                                    <span class="badge bg-primary">Current User</span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Recent Activity -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>Recent User Activity</span>
                    <div class="d-flex gap-2 align-items-center">
                        <button class="btn btn-sm btn-outline-secondary rounded-pill px-3 py-1" onclick="refreshActivity()" id="refresh-btn">
                            <i class="bi bi-arrow-clockwise me-1"></i> Refresh
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover" id="activityTable">
                            <thead class="table-light">
                                <tr>
                                    <th>User</th>
                                    <th>Action</th>
                                    <th>Details</th>
                                    <th>Time</th>
                                </tr>
                            </thead>
                            <tbody id="activity-table-body">
                                <?php foreach (array_slice($recent_activity, 0, 10) as $activity): ?>
                                    <tr class="activity-row" data-id="<?php echo $activity['id']; ?>">
                                        <td><?php echo htmlspecialchars($activity['username'] ?? 'System'); ?></td>
                                        <td>
                                            <span class="badge bg-<?php 
                                                $action_colors = [
                                                    'login' => 'success',
                                                    'logout' => 'secondary',
                                                    'create_user' => 'primary',
                                                    'update_user' => 'info',
                                                    'delete_user' => 'danger',
                                                    'reset_password' => 'warning'
                                                ];
                                                echo $action_colors[$activity['action']] ?? 'secondary';
                                            ?>">
                                                <?php echo htmlspecialchars($activity['action']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($activity['details'] ?? ''); ?></td>
                                        <td>
                                            <small><?php echo date('M d, H:i', strtotime($activity['created_at'])); ?></small>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Create User Modal -->
    <div class="modal fade" id="createUserModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="action" value="create_user">
                    <div class="modal-header">
                        <h5 class="modal-title">Create New User</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">First Name</label>
                                <input type="text" name="first_name" class="form-control" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Last Name</label>
                                <input type="text" name="last_name" class="form-control" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Username</label>
                            <input type="text" name="username" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Phone</label>
                            <input type="tel" name="phone" class="form-control">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Role</label>
                            <select name="role" class="form-select" required>
                                <option value="cashier">Cashier</option>
                                <option value="manager">Manager</option>
                                <option value="admin">Admin</option>
                                <option value="viewer">Viewer</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Password</label>
                            <input type="password" name="password" class="form-control" required minlength="8">
                            <div class="form-text">Minimum 8 characters</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary rounded-pill px-4 py-2" data-bs-dismiss="modal">
                            <i class="bi bi-x-lg me-2"></i> Cancel
                        </button>
                        <button type="submit" class="btn btn-primary rounded-pill px-4 py-2">
                            <i class="bi bi-person-plus me-2"></i> Create User
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit User Modal -->
    <div class="modal fade" id="editUserModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="action" value="update_user">
                    <input type="hidden" name="user_id" id="edit_user_id">
                    <div class="modal-header">
                        <h5 class="modal-title">Edit User</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">First Name</label>
                                <input type="text" name="first_name" id="edit_first_name" class="form-control">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Last Name</label>
                                <input type="text" name="last_name" id="edit_last_name" class="form-control">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" id="edit_email" class="form-control">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Phone</label>
                            <input type="tel" name="phone" id="edit_phone" class="form-control">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Role</label>
                            <select name="role" id="edit_role" class="form-select">
                                <option value="cashier">Cashier</option>
                                <option value="manager">Manager</option>
                                <option value="admin">Admin</option>
                                <option value="viewer">Viewer</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Status</label>
                            <select name="status" id="edit_status" class="form-select">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                                <option value="suspended">Suspended</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary rounded-pill px-4 py-2" data-bs-dismiss="modal">
                            <i class="bi bi-x-lg me-2"></i> Cancel
                        </button>
                        <button type="submit" class="btn btn-primary rounded-pill px-4 py-2">
                            <i class="bi bi-pencil me-2"></i> Update User
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Sticky Note Modal -->
    <div class="modal fade" id="stickyNoteModal" tabindex="-1" aria-labelledby="stickyNoteModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="stickyNoteModalLabel">
                        <i class="bi bi-sticky-fill"></i> 
                        <span id="noteModalTitle">Add Note</span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="stickyNoteForm">
                        <input type="hidden" id="noteId" value="">
                        <div class="mb-3">
                            <label for="noteContent" class="form-label">Note Content</label>
                            <textarea id="noteContent" class="form-control" rows="4" placeholder="Enter your note or reminder..." required></textarea>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="notePriority" class="form-label">Priority</label>
                                <select id="notePriority" class="form-select">
                                    <option value="low">Low Priority</option>
                                    <option value="medium" selected>Medium Priority</option>
                                    <option value="high">High Priority</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="noteReminder" class="form-label">Reminder Date (Optional)</label>
                                <input type="datetime-local" id="noteReminder" class="form-control">
                            </div>
                        </div>
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="noteImportant">
                                <label class="form-check-label" for="noteImportant">
                                    Mark as Important
                                </label>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary rounded-pill px-4 py-2" data-bs-dismiss="modal">
                        <i class="bi bi-x-lg me-2"></i> Cancel
                    </button>
                    <button type="button" class="btn btn-primary rounded-pill px-4 py-2" onclick="saveNote()">
                        <i class="bi bi-save me-2"></i> Save Note
                    </button>
                </div>
            </div>
        </div>
    </div>

    <?php include 'includes/chatbot.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/chatbot.js"></script>
    <script>
        // ===== USER MANAGEMENT FUNCTIONS =====
        function editUser(user) {
            document.getElementById('edit_user_id').value = user.id;
            document.getElementById('edit_first_name').value = user.first_name;
            document.getElementById('edit_last_name').value = user.last_name;
            document.getElementById('edit_email').value = user.email;
            document.getElementById('edit_phone').value = user.phone;
            document.getElementById('edit_role').value = user.role;
            document.getElementById('edit_status').value = user.status;
            
            new bootstrap.Modal(document.getElementById('editUserModal')).show();
        }

        function refreshActivity() {
            const refreshBtn = document.getElementById('refresh-btn');
            const refreshIcon = refreshBtn.querySelector('i');
            
            refreshIcon.className = 'bi bi-arrow-clockwise spin';
            refreshBtn.disabled = true;
            
            // Simulate refresh - in a real app, this would fetch from API
            setTimeout(() => {
                refreshIcon.className = 'bi bi-arrow-clockwise';
                refreshBtn.disabled = false;
                
                // Add a simple visual feedback
                const tbody = document.getElementById('activity-table-body');
                tbody.style.opacity = '0.5';
                setTimeout(() => {
                    tbody.style.opacity = '1';
                }, 300);
            }, 1000);
        }

        // ===== STICKY NOTES FUNCTIONALITY =====
        let stickyNotes = JSON.parse(localStorage.getItem('userManagementNotes') || '[]');
        let noteIdCounter = parseInt(localStorage.getItem('noteIdCounter') || '1');

        function toggleStickyNotes() {
            const container = document.getElementById('stickyNotesContainer');
            const toggle = document.getElementById('notesToggle');
            
            if (container.style.display === 'none') {
                container.style.display = 'block';
                toggle.innerHTML = '<i class="bi bi-sticky-fill"></i> Hide Notes';
                renderNotes();
                checkReminders();
            } else {
                container.style.display = 'none';
                toggle.innerHTML = '<i class="bi bi-sticky"></i> Notes';
            }
        }

        function addStickyNote() {
            document.getElementById('noteModalTitle').textContent = 'Add Note';
            document.getElementById('noteId').value = '';
            document.getElementById('stickyNoteForm').reset();
            document.getElementById('notePriority').value = 'medium';
            
            new bootstrap.Modal(document.getElementById('stickyNoteModal')).show();
        }

        function editNote(id) {
            const note = stickyNotes.find(n => n.id == id);
            if (!note) return;

            document.getElementById('noteModalTitle').textContent = 'Edit Note';
            document.getElementById('noteId').value = note.id;
            document.getElementById('noteContent').value = note.content;
            document.getElementById('notePriority').value = note.priority;
            document.getElementById('noteImportant').checked = note.important || false;
            
            if (note.reminder) {
                const date = new Date(note.reminder);
                document.getElementById('noteReminder').value = date.toISOString().slice(0, 16);
            } else {
                document.getElementById('noteReminder').value = '';
            }

            const modal = new bootstrap.Modal(document.getElementById('stickyNoteModal'));
            modal.show();
        }

        function saveNote() {
            const id = document.getElementById('noteId').value;
            const content = document.getElementById('noteContent').value.trim();
            const priority = document.getElementById('notePriority').value;
            const reminder = document.getElementById('noteReminder').value;
            const important = document.getElementById('noteImportant').checked;

            if (!content) {
                alert('Please enter note content');
                return;
            }

            const noteData = {
                id: id || noteIdCounter++,
                content: content,
                priority: priority,
                reminder: reminder || null,
                important: important,
                created: id ? stickyNotes.find(n => n.id == id).created : new Date().toISOString(),
                updated: new Date().toISOString()
            };

            if (id) {
                const index = stickyNotes.findIndex(n => n.id == id);
                stickyNotes[index] = noteData;
            } else {
                stickyNotes.push(noteData);
            }

            localStorage.setItem('userManagementNotes', JSON.stringify(stickyNotes));
            localStorage.setItem('noteIdCounter', noteIdCounter.toString());

            bootstrap.Modal.getInstance(document.getElementById('stickyNoteModal')).hide();
            renderNotes();
            checkReminders();
        }

        function deleteNote(id) {
            if (confirm('Are you sure you want to delete this note?')) {
                stickyNotes = stickyNotes.filter(n => n.id != id);
                localStorage.setItem('userManagementNotes', JSON.stringify(stickyNotes));
                renderNotes();
            }
        }

        function renderNotes() {
            const grid = document.getElementById('notesGrid');
            
            if (stickyNotes.length === 0) {
                grid.innerHTML = `
                    <div class="empty-notes">
                        <i class="bi bi-sticky"></i>
                        <h5>No Notes Yet</h5>
                        <p>Click "Add Note" to create your first sticky note or reminder</p>
                    </div>
                `;
                return;
            }

            // Sort notes by priority and creation date
            const sortedNotes = stickyNotes.sort((a, b) => {
                const priorityOrder = { high: 3, medium: 2, low: 1 };
                if (priorityOrder[a.priority] !== priorityOrder[b.priority]) {
                    return priorityOrder[b.priority] - priorityOrder[a.priority];
                }
                return new Date(b.created) - new Date(a.created);
            });

            grid.innerHTML = sortedNotes.map(note => {
                const isReminder = note.reminder && new Date(note.reminder) > new Date();
                const isOverdue = note.reminder && new Date(note.reminder) <= new Date();
                
                return `
                    <div class="sticky-note priority-${note.priority} ${isReminder ? 'note-reminder' : ''}" 
                         data-id="${note.id}">
                        <div class="note-header">
                            <span class="note-priority badge bg-${getPriorityColor(note.priority)}">
                                ${note.priority.toUpperCase()}
                                ${note.important ? ' ⭐' : ''}
                            </span>
                            <div class="note-actions">
                                <button onclick="editNote('${note.id}')" title="Edit Note">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <button onclick="deleteNote('${note.id}')" title="Delete Note">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                        </div>
                        <div class="note-content">
                            ${escapeHtml(note.content)}
                        </div>
                        <div class="note-footer">
                            <small>${formatDate(note.created)}</small>
                            ${note.reminder ? `
                                <small class="text-${isOverdue ? 'danger' : 'warning'}">
                                    <i class="bi bi-alarm"></i> 
                                    ${formatDateTime(note.reminder)}
                                    ${isOverdue ? ' (Overdue)' : ''}
                                </small>
                            ` : ''}
                        </div>
                    </div>
                `;
            }).join('');
        }

        function getPriorityColor(priority) {
            const colors = { high: 'danger', medium: 'warning', low: 'info' };
            return colors[priority] || 'secondary';
        }

        function formatDate(dateString) {
            return new Date(dateString).toLocaleDateString();
        }

        function formatDateTime(dateString) {
            return new Date(dateString).toLocaleString();
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML.replace(/\n/g, '<br>');
        }

        function checkReminders() {
            const now = new Date();
            const upcomingReminders = stickyNotes.filter(note => {
                if (!note.reminder) return false;
                const reminderTime = new Date(note.reminder);
                const timeDiff = reminderTime - now;
                return timeDiff > 0 && timeDiff <= 15 * 60 * 1000; // 15 minutes
            });

            upcomingReminders.forEach(note => {
                if (!note.notified) {
                    showReminderNotification(note);
                    note.notified = true;
                    localStorage.setItem('userManagementNotes', JSON.stringify(stickyNotes));
                }
            });
        }

        function showReminderNotification(note) {
            if ('Notification' in window && Notification.permission === 'granted') {
                new Notification('Reminder: User Management', {
                    body: note.content,
                    icon: '/favicon.ico'
                });
            } else {
                // Fallback to alert
                alert(`Reminder: ${note.content}`);
            }
        }

        // Request notification permission
        function requestNotificationPermission() {
            if ('Notification' in window && Notification.permission === 'default') {
                Notification.requestPermission();
            }
        }

        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            requestNotificationPermission();
            
            // Check reminders every minute
            setInterval(checkReminders, 60000);
            
            // Add CSS for spinning animation
            const style = document.createElement('style');
            style.textContent = `
                .spin {
                    animation: spin 1s linear infinite;
                }
                @keyframes spin {
                    from { transform: rotate(0deg); }
                    to { transform: rotate(360deg); }
                }
            `;
            document.head.appendChild(style);
        });
    </script>
    <script src="js/chatbot.js?v=<?php echo time(); ?>"></script>
    <script src="js/mobile.js?v=<?php echo time(); ?>"></script>
</body>
</html>