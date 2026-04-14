<?php
require_once 'config/database.php';
require_once 'config/session.php';
requireLogin();

$database = new Database();
$db = $database->getConnection();

$user_id = $_SESSION['user_id'];
$success = '';
$error = '';

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request token. Please try again.';
    } else {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'update_profile') {
            $first_name = trim($_POST['first_name'] ?? '');
            $last_name = trim($_POST['last_name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            
            if (empty($first_name) || empty($last_name) || empty($email)) {
                $error = 'Please fill in all required profile fields.';
            } else {
                try {
                    $stmt = $db->prepare("UPDATE users SET first_name = ?, last_name = ?, email = ? WHERE id = ?");
                    $stmt->execute([$first_name, $last_name, $email, $user_id]);
                    $success = 'Profile updated successfully.';
                } catch (Exception $e) {
                    $error = 'Failed to update profile. Email might already be perfectly matching another user.';
                }
            }
        } elseif ($action === 'update_password') {
            $current_password = $_POST['current_password'] ?? '';
            $new_password = $_POST['new_password'] ?? '';
            $confirm_password = $_POST['confirm_password'] ?? '';
            
            if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
                $error = 'All password fields are required.';
            } elseif ($new_password !== $confirm_password) {
                $error = 'New passwords do not match.';
            } elseif (strlen($new_password) < 8) {
                $error = 'New password must be at least 8 characters.';
            } else {
                // Verify current password
                $stmt = $db->prepare("SELECT password FROM users WHERE id = ?");
                $stmt->execute([$user_id]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (password_verify($current_password, $user['password'])) {
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    $stmt = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
                    $stmt->execute([$hashed_password, $user_id]);
                    $success = 'Password changed successfully.';
                } else {
                    $error = 'Incorrect current password.';
                }
            }
        }
    }
}

// Fetch current user details
$stmt = $db->prepare("SELECT username, email, first_name, last_name, role FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user_data = $stmt->fetch(PDO::FETCH_ASSOC);

$csrf_token = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php 
$page_title = 'Account Settings — InvenAI';
include 'includes/head.php'; 
?>
<style>
    .settings-card { border-radius: 12px; border: 1px solid var(--border-default); box-shadow: var(--shadow-sm); overflow: hidden; }
    .settings-header { background: rgba(99,102,241,0.05); border-bottom: 1px solid var(--border-subtle); padding: 1.25rem 1.5rem; }
    .settings-header h5 { margin: 0; color: var(--text-primary); font-weight: 700; display: flex; align-items: center; gap: 0.5rem; }
</style>
</head>
<body>
    <?php include 'includes/navbar.php'; ?>

    <div class="container-fluid">
        <?php include 'includes/sidebar.php'; ?>

        <main id="mainContent">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center mb-4">
                <div>
                    <h1 style="font-size:1.5rem;font-weight:700;color:var(--text-primary);margin:0;">
                        <i class="bi bi-gear"></i> Account Settings
                    </h1>
                    <p style="color:var(--text-muted);margin:0;font-size:0.85rem;">Manage your individual profile identity and security</p>
                </div>
            </div>

            <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bi bi-check-circle me-2"></i><?php echo htmlspecialchars($success); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="row g-4">
                <!-- Profile Settings -->
                <div class="col-lg-6">
                    <div class="card settings-card h-100">
                        <div class="settings-header">
                            <h5><i class="bi bi-person-lines-fill"></i> Public Profile</h5>
                        </div>
                        <div class="card-body p-4">
                            <form method="POST">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                <input type="hidden" name="action" value="update_profile">
                                
                                <div class="mb-3">
                                    <label class="form-label">Username</label>
                                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($user_data['username']); ?>" disabled>
                                    <small class="text-muted">Username cannot be changed.</small>
                                </div>

                                <div class="row g-3 mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label">First Name</label>
                                        <input type="text" class="form-control" name="first_name" value="<?php echo htmlspecialchars($user_data['first_name']); ?>" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Last Name</label>
                                        <input type="text" class="form-control" name="last_name" value="<?php echo htmlspecialchars($user_data['last_name']); ?>" required>
                                    </div>
                                </div>

                                <div class="mb-4">
                                    <label class="form-label">Email Address</label>
                                    <input type="email" class="form-control" name="email" value="<?php echo htmlspecialchars($user_data['email']); ?>" required>
                                </div>

                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="bi bi-save me-2"></i> Update Profile
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Password Settings -->
                <div class="col-lg-6">
                    <div class="card settings-card h-100">
                        <div class="settings-header">
                            <h5><i class="bi bi-shield-lock"></i> Security Details</h5>
                        </div>
                        <div class="card-body p-4">
                            <form method="POST">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                <input type="hidden" name="action" value="update_password">

                                <div class="mb-3">
                                    <label class="form-label">Current Password</label>
                                    <input type="password" class="form-control" name="current_password" required>
                                </div>
                                <hr class="my-4" style="border-top:1px solid var(--border-subtle);">
                                <div class="mb-3">
                                    <label class="form-label">New Password</label>
                                    <input type="password" class="form-control" name="new_password" required minlength="8">
                                </div>
                                <div class="mb-4">
                                    <label class="form-label">Confirm New Password</label>
                                    <input type="password" class="form-control" name="confirm_password" required minlength="8">
                                </div>

                                <button type="submit" class="btn btn-outline-danger w-100">
                                    <i class="bi bi-key me-2"></i> Change Password
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/ui-enhancements.js"></script>
</body>
</html>
