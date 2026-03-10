<?php
require_once 'config/database.php';
require_once 'config/session.php';
requireLogin();

$database = new Database();
$db = $database->getConnection();

$success_message = '';
$error_message = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        die('Invalid CSRF token');
    }
    
    if ($_POST['action'] === 'add') {
        $name = trim($_POST['category_name']);
        $description = trim($_POST['description']);
        
        if (!empty($name)) {
            try {
                $stmt = $db->prepare("INSERT INTO categories (name, description) VALUES (:name, :description)");
                $stmt->execute([':name' => $name, ':description' => $description]);
                $success_message = "Category '{$name}' added successfully!";
            } catch (Exception $e) {
                $error_message = "Error adding category: " . $e->getMessage();
            }
        } else {
            $error_message = "Category name is required.";
        }
    }
    
    if ($_POST['action'] === 'delete' && isAdmin()) {
        $category_id = (int)$_POST['category_id'];
        $move_to_category = (int)$_POST['move_to_category'];
        
        try {
            $db->beginTransaction();
            
            // Get category name for confirmation
            $stmt = $db->prepare("SELECT name FROM categories WHERE id = :id");
            $stmt->execute([':id' => $category_id]);
            $category = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($category) {
                // Count products in this category
                $stmt = $db->prepare("SELECT COUNT(*) as count FROM products WHERE category_id = :id");
                $stmt->execute([':id' => $category_id]);
                $product_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
                
                if ($product_count > 0 && $move_to_category > 0) {
                    // Move products to selected category
                    $stmt = $db->prepare("UPDATE products SET category_id = :new_id WHERE category_id = :old_id");
                    $stmt->execute([':new_id' => $move_to_category, ':old_id' => $category_id]);
                    
                    // Get destination category name
                    $stmt = $db->prepare("SELECT name FROM categories WHERE id = :id");
                    $stmt->execute([':id' => $move_to_category]);
                    $dest_category = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    $success_message = "Moved {$product_count} products from '{$category['name']}' to '{$dest_category['name']}' and ";
                }
                
                // Delete the category
                $stmt = $db->prepare("DELETE FROM categories WHERE id = :id");
                $stmt->execute([':id' => $category_id]);
                
                $success_message .= "deleted category '{$category['name']}'.";
                $db->commit();
            } else {
                $error_message = "Category not found.";
            }
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollback();
            }
            $error_message = "Error deleting category: " . $e->getMessage();
        }
    }
    
    if ($_POST['action'] === 'edit' && isAdmin()) {
        $category_id = (int)$_POST['category_id'];
        $name = trim($_POST['category_name']);
        $description = trim($_POST['description']);
        
        if (!empty($name)) {
            try {
                $stmt = $db->prepare("UPDATE categories SET name = :name, description = :description WHERE id = :id");
                $stmt->execute([':name' => $name, ':description' => $description, ':id' => $category_id]);
                $success_message = "Category updated successfully!";
            } catch (Exception $e) {
                $error_message = "Error updating category: " . $e->getMessage();
            }
        } else {
            $error_message = "Category name is required.";
        }
    }
}

// Get all categories with product counts
$stmt = $db->query("SELECT c.*, COUNT(p.id) as product_count 
                    FROM categories c 
                    LEFT JOIN products p ON c.id = p.category_id 
                    GROUP BY c.id 
                    ORDER BY c.name");
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Categories - Inventory System</title>
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
            <?php if ($success_message): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bi bi-check-circle"></i> <?php echo $success_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if ($error_message): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle"></i> <?php echo $error_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2"><i class="bi bi-tags"></i> Categories</h1>
                <div>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
                        <i class="bi bi-plus-lg"></i> Add Category
                    </button>
                </div>
            </div>

            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Manage Categories</h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($categories)): ?>
                                <div class="text-center text-muted py-5">
                                    <i class="bi bi-tags" style="font-size: 3rem;"></i>
                                    <p class="mt-2">No categories found. Add your first category!</p>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-striped">
                                        <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>Name</th>
                                                <th>Description</th>
                                                <th>Products</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($categories as $category): ?>
                                                <tr>
                                                    <td><?php echo $category['id']; ?></td>
                                                    <td>
                                                        <strong><?php echo htmlspecialchars($category['name']); ?></strong>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($category['description'] ?? ''); ?></td>
                                                    <td>
                                                        <span class="badge bg-primary"><?php echo $category['product_count']; ?> products</span>
                                                    </td>
                                                    <td>
                                                        <?php if (isAdmin()): ?>
                                                            <button class="btn btn-sm btn-outline-primary" 
                                                                    onclick="editCategory(<?php echo $category['id']; ?>, '<?php echo htmlspecialchars($category['name']); ?>', '<?php echo htmlspecialchars($category['description'] ?? ''); ?>')">
                                                                <i class="bi bi-pencil"></i> Edit
                                                            </button>
                                                            
                                                            <?php if ($category['product_count'] == 0): ?>
                                                                <form method="POST" style="display: inline;">
                                                                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                                                    <input type="hidden" name="action" value="delete">
                                                                    <input type="hidden" name="category_id" value="<?php echo $category['id']; ?>">
                                                                    <input type="hidden" name="move_to_category" value="0">
                                                                    <button type="submit" class="btn btn-sm btn-danger" 
                                                                            onclick="return confirm('Delete this category?')">
                                                                        <i class="bi bi-trash"></i> Delete
                                                                    </button>
                                                                </form>
                                                            <?php else: ?>
                                                                <button class="btn btn-sm btn-warning" 
                                                                        onclick="showDeleteModal(<?php echo $category['id']; ?>, '<?php echo htmlspecialchars($category['name']); ?>', <?php echo $category['product_count']; ?>)">
                                                                    <i class="bi bi-trash"></i> Remove
                                                                </button>
                                                            <?php endif; ?>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Add Category Modal -->
    <div class="modal fade" id="addCategoryModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="action" value="add">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="bi bi-plus-circle"></i> Add New Category
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Category Name *</label>
                            <input type="text" name="category_name" class="form-control" required placeholder="Enter category name">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea name="description" class="form-control" rows="3" placeholder="Optional description"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg"></i> Add Category
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Category Modal -->
    <div class="modal fade" id="editCategoryModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="category_id" id="edit_category_id">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="bi bi-pencil"></i> Edit Category
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Category Name *</label>
                            <input type="text" name="category_name" id="edit_category_name" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea name="description" id="edit_description" class="form-control" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg"></i> Update Category
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Category Modal -->
    <div class="modal fade" id="deleteCategoryModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="category_id" id="delete_category_id">
                    <div class="modal-header">
                        <h5 class="modal-title text-danger">
                            <i class="bi bi-exclamation-triangle"></i> Remove Category
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-warning">
                            <i class="bi bi-exclamation-triangle"></i>
                            <strong>Warning:</strong> This category contains <span id="delete_product_count"></span> products.
                        </div>
                        
                        <p>Category: <strong id="delete_category_name"></strong></p>
                        <p>You must move the products to another category before deletion:</p>
                        
                        <div class="mb-3">
                            <label class="form-label">Move products to:</label>
                            <select name="move_to_category" class="form-select" required>
                                <option value="">Select destination category...</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">
                            <i class="bi bi-trash"></i> Move Products & Delete Category
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php include 'includes/chatbot.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/chatbot.js?v=<?php echo time(); ?>"></script>
    <script src="js/mobile.js?v=<?php echo time(); ?>"></script>
    
    <script>
        function editCategory(id, name, description) {
            document.getElementById('edit_category_id').value = id;
            document.getElementById('edit_category_name').value = name;
            document.getElementById('edit_description').value = description;
            
            const modal = new bootstrap.Modal(document.getElementById('editCategoryModal'));
            modal.show();
        }
        
        function showDeleteModal(id, name, productCount) {
            document.getElementById('delete_category_id').value = id;
            document.getElementById('delete_category_name').textContent = name;
            document.getElementById('delete_product_count').textContent = productCount;
            
            // Remove the category being deleted from the dropdown
            const select = document.querySelector('#deleteCategoryModal select[name="move_to_category"]');
            const options = select.querySelectorAll('option');
            options.forEach(option => {
                if (option.value == id) {
                    option.style.display = 'none';
                } else {
                    option.style.display = 'block';
                }
            });
            
            const modal = new bootstrap.Modal(document.getElementById('deleteCategoryModal'));
            modal.show();
        }
    </script>
</body>
</html>