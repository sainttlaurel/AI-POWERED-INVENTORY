<?php
require_once 'config/database.php';
require_once 'config/session.php';

if (isLoggedIn()) {
    header("Location: dashboard.php");
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    // Debug logging
    error_log("Login attempt - Username: '$username', Password length: " . strlen($password));
    
    if (!empty($username) && !empty($password)) {
        $database = new Database();
        $db = $database->getConnection();
        
        if ($db) {
            $query = "SELECT id, username, password, role, status FROM users WHERE username = :username";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':username', $username);
            $stmt->execute();
            
            error_log("Database query executed for username: '$username'");
            
            if ($stmt->rowCount() > 0) {
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                error_log("User found - ID: {$user['id']}, Role: {$user['role']}, Status: " . ($user['status'] ?? 'NULL'));
                
                // Check if user is active
                if (isset($user['status']) && $user['status'] !== 'active') {
                    $error = 'Account is ' . $user['status'] . '. Please contact administrator.';
                    error_log("Login failed - Account status: " . $user['status']);
                } else {
                    // Verify password
                    $password_valid = password_verify($password, $user['password']);
                    error_log("Password verification result: " . ($password_valid ? 'VALID' : 'INVALID'));
                    
                    if ($password_valid) {
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['username'] = $user['username'];
                        $_SESSION['role'] = $user['role'];
                        
                        error_log("Login successful - User ID: {$user['id']}, Username: {$user['username']}, Role: {$user['role']}");
                        
                        // Update last login time
                        try {
                            $update_stmt = $db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
                            $update_stmt->execute([$user['id']]);
                        } catch (Exception $e) {
                            error_log("Failed to update last login: " . $e->getMessage());
                        }
                        
                        // Log successful login
                        try {
                            require_once 'config/session.php';
                            logUserActivity('login', "Successful login from " . $_SERVER['REMOTE_ADDR'], $user['id']);
                        } catch (Exception $e) {
                            error_log("Failed to log activity: " . $e->getMessage());
                        }
                        
                        header("Location: dashboard.php");
                        exit();
                    } else {
                        $error = 'Invalid username or password';
                        error_log("Login failed - Invalid password for user: '$username'");
                    }
                }
            } else {
                $error = 'Invalid username or password';
                error_log("Login failed - User not found: '$username'");
            }
        } else {
            $error = 'Database connection failed. Please run setup.php first.';
            error_log("Login failed - Database connection failed");
        }
    } else {
        $error = 'Please enter both username and password';
        error_log("Login failed - Empty username or password");
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Inventory System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="css/style.css">
    <style>
        .login-container {
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 50%, #f1f5f9 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        .login-header {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            color: #ffffff;
            text-align: center;
            padding: 2rem;
            margin: -1px -1px 0 -1px;
        }
        .login-body {
            padding: 2rem;
        }
        .login-title {
            font-size: 1.8rem;
            font-weight: 700;
            margin: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }
        .login-subtitle {
            color: rgba(255, 255, 255, 0.8);
            font-size: 0.9rem;
            margin-top: 0.5rem;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-md-5 col-lg-4">
                    <div class="card login-card">
                        <div class="login-header">
                            <h2 class="login-title">
                                <i class="bi bi-boxes"></i>
                                Inventory System
                            </h2>
                            <p class="login-subtitle">Modern Inventory Management</p>
                        </div>
                        <div class="login-body">
                            <?php if ($error): ?>
                                <div class="alert alert-danger">
                                    <i class="bi bi-exclamation-triangle"></i>
                                    <?php echo htmlspecialchars($error); ?>
                                </div>
                            <?php endif; ?>
                            <form method="POST">
                                <div class="mb-3">
                                    <label class="form-label">
                                        <i class="bi bi-person"></i> Username
                                    </label>
                                    <input type="text" name="username" class="form-control" required 
                                           placeholder="Enter your username">
                                </div>
                                <div class="mb-4">
                                    <label class="form-label">
                                        <i class="bi bi-lock"></i> Password
                                    </label>
                                    <input type="password" name="password" class="form-control" required 
                                           placeholder="Enter your password">
                                </div>
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="bi bi-box-arrow-in-right"></i> Sign In
                                </button>
                            </form>
                            <div class="text-center mt-4">
                                <small class="text-muted">
                                    <i class="bi bi-info-circle"></i>
                                    Default credentials: <strong>admin</strong> / <strong>admin123</strong>
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
