<?php
/**
 * Categories Management Page
 * Admin only - Manage product categories
 */

require_once '../includes/header.php';
requireAdmin();

$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$categoryId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$error = '';
$success = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add_category']) || isset($_POST['edit_category'])) {
        $name = sanitizeInput($_POST['name']);
        $description = sanitizeInput($_POST['description']);
        
        // Validation
        if (empty($name)) {
            $error = 'Category name is required.';
        } else {
            if (isset($_POST['add_category'])) {
                // Add new category
                $query = "INSERT INTO categories (name, description) VALUES (?, ?)";
                if (executeNonQuery($query, 'ss', [$name, $description])) {
                    $success = 'Category added successfully.';
                    logActivity('Added category', $_SESSION['user_id'], "Category: $name");
                } else {
                    $error = 'Failed to add category.';
                }
            } else {
                // Update existing category
                $query = "UPDATE categories SET name = ?, description = ? WHERE id = ?";
                if (executeNonQuery($query, 'ssi', [$name, $description, $categoryId])) {
                    $success = 'Category updated successfully.';
                    logActivity('Updated category', $_SESSION['user_id'], "Category ID: $categoryId");
                    $action = 'list';
                } else {
                    $error = 'Failed to update category.';
                }
            }
        }
    } elseif (isset($_POST['delete_category'])) {
        // Check if category has products
        $checkProductsQuery = "SELECT COUNT(*) as count FROM products WHERE category_id = ?";
        $productsCheck = executeQuery($checkProductsQuery, 'i', [$categoryId]);
        
        if ($productsCheck[0]['count'] > 0) {
            $error = 'Cannot delete category. It has associated products.';
        } else {
            $query = "DELETE FROM categories WHERE id = ?";
            if (executeNonQuery($query, 'i', [$categoryId])) {
                $success = 'Category deleted successfully.';
                logActivity('Deleted category', $_SESSION['user_id'], "Category ID: $categoryId");
                $action = 'list';
            } else {
                $error = 'Failed to delete category.';
            }
        }
    }
}

// Get category for editing
if ($action == 'edit' && $categoryId) {
    $categoryQuery = "SELECT * FROM categories WHERE id = ?";
    $categoryResult = executeQuery($categoryQuery, 'i', [$categoryId]);
    if (empty($categoryResult)) {
        $action = 'list';
    } else {
        $category = $categoryResult[0];
    }
}

// Get all categories for listing
// Get all categories for listing
if ($action == 'list') {
    // Pagination settings
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $limit = 20; // Categories per page
    $offset = ($page - 1) * $limit;
    
    // Get total count
    $countQuery = "SELECT COUNT(*) as total FROM categories";
    $countResult = executeQuery($countQuery);
    $totalCategories = (!empty($countResult) && isset($countResult[0])) ? $countResult[0]['total'] : 0;
    $totalPages = ceil($totalCategories / $limit);
    
    // Get categories with pagination
    $categoriesQuery = "SELECT c.*, COUNT(p.id) as product_count 
                       FROM categories c 
                       LEFT JOIN products p ON c.id = p.category_id 
                       GROUP BY c.id 
                       ORDER BY c.name
                       LIMIT $limit OFFSET $offset";
    $categories = executeQuery($categoriesQuery);
}
?>

<?php if ($action == 'list'): ?>
    <!-- Categories List -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3><i class="fas fa-tags"></i> Categories Management</h3>
        <a href="?action=add" class="btn btn-primary">
            <i class="fas fa-plus"></i> Add Category
        </a>
    </div>
    
    <?php if ($error): ?>
        <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?php echo $error; ?></div>
    <?php endif; ?>
    
    <?php if ($success): ?>
        <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo $success; ?></div>
    <?php endif; ?>
    
    <div class="row">
        <div class="col-md-8">
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Category Name</th>
                                    <th>Description</th>
                                    <th>Products</th>
                                    <th>Created</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($categories)): ?>
                                    <tr>
                                        <td colspan="5" class="text-center py-4">
                                            <i class="fas fa-tags fa-3x text-muted mb-3"></i>
                                            <h5 class="text-muted">No categories found</h5>
                                            <a href="?action=add" class="btn btn-primary">Add First Category</a>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($categories as $cat): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo sanitizeInput($cat['name']); ?></strong>
                                            </td>
                                            <td>
                                                <?php echo $cat['description'] ? sanitizeInput($cat['description']) : '<span class="text-muted">No description</span>'; ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-primary"><?php echo $cat['product_count']; ?> products</span>
                                            </td>
                                            <td><?php echo formatDate($cat['created_at']); ?></td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <a href="?action=edit&id=<?php echo $cat['id']; ?>" class="btn btn-outline-primary">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <?php if ($cat['product_count'] == 0): ?>
                                                        <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure?')">
                                                            <input type="hidden" name="category_id" value="<?php echo $cat['id']; ?>">
                                                            <button type="submit" name="delete_category" class="btn btn-outline-danger">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                        </form>
                                                    <?php else: ?>
                                                        <button class="btn btn-outline-danger" disabled title="Cannot delete category with products">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php if ($totalPages > 1): ?>
            <nav aria-label="Categories pagination" class="mt-4">
                <ul class="pagination justify-content-center">
                    <?php if ($page > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="?action=list&page=<?php echo $page - 1; ?>">
                                Previous
                            </a>
                        </li>
                    <?php endif; ?>

                    <?php
                    $start = max(1, $page - 2);
                    $end = min($totalPages, $page + 2);

                    for ($i = $start; $i <= $end; $i++): ?>
                        <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                            <a class="page-link" href="?action=list&page=<?php echo $i; ?>">
                                <?php echo $i; ?>
                            </a>
                        </li>
                    <?php endfor; ?>

                    <?php if ($page < $totalPages): ?>
                        <li class="page-item">
                            <a class="page-link" href="?action=list&page=<?php echo $page + 1; ?>">
                                Next
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </nav>
            
            <div class="text-center mt-3">
                <small class="text-muted">
                    Showing page <?php echo $page; ?> of <?php echo $totalPages; ?> 
                    (<?php echo $totalCategories; ?> total categories)
                </small>
            </div>
        <?php endif; ?>
    </div>
        
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h6 class="card-title mb-0">
                        <i class="fas fa-info-circle"></i> Category Information
                    </h6>
                </div>
                <div class="card-body">
                    <h6>Why Use Categories?</h6>
                    <ul class="small">
                        <li>Better product organization</li>
                        <li>Easier inventory management</li>
                        <li>Improved reporting capabilities</li>
                        <li>Enhanced customer experience</li>
                    </ul>
                    
                    <h6>Category Guidelines:</h6>
                    <ul class="small">
                        <li>Use clear, descriptive names</li>
                        <li>Keep categories broad but specific</li>
                        <li>Avoid too many subcategories</li>
                        <li>Consider your business needs</li>
                    </ul>
                </div>
            </div>
            
            <div class="card mt-3">
                <div class="card-header">
                    <h6 class="card-title mb-0">
                        <i class="fas fa-chart-pie"></i> Category Statistics
                    </h6>
                </div>
                <div class="card-body">
                    <?php if (!empty($categories)): ?>
                        <div class="row text-center">
                            <div class="col-6">
                                <h4 class="text-primary"><?php echo count($categories); ?></h4>
                                <small>Total Categories</small>
                            </div>
                            <div class="col-6">
                                <h4 class="text-success">
                                    <?php 
                                    $totalProducts = array_sum(array_column($categories, 'product_count'));
                                    echo $totalProducts;
                                    ?>
                                </h4>
                                <small>Categorized Products</small>
                            </div>
                        </div>
                        
                        <hr>
                        
                        <h6>Top Categories:</h6>
                        <?php 
                        $topCategories = array_slice($categories, 0, 3);
                        foreach ($topCategories as $topCat): 
                        ?>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <small><?php echo sanitizeInput($topCat['name']); ?></small>
                                <span class="badge bg-secondary"><?php echo $topCat['product_count']; ?></span>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="text-muted text-center">No categories created yet</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

<?php else: ?>
    <!-- Add/Edit Category Form -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3>
            <i class="fas fa-<?php echo $action == 'add' ? 'plus' : 'edit'; ?>"></i> 
            <?php echo ucfirst($action); ?> Category
        </h3>
        <a href="?action=list" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back to List
        </a>
    </div>
    
    <?php if ($error): ?>
        <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?php echo $error; ?></div>
    <?php endif; ?>
    
    <div class="row">
        <div class="col-md-8">
            <div class="card">
                <div class="card-body">
                    <form method="POST">
                        <div class="mb-3">
                            <label for="name" class="form-label">Category Name *</label>
                            <input 
                                type="text" 
                                class="form-control" 
                                id="name" 
                                name="name" 
                                value="<?php echo isset($category) ? sanitizeInput($category['name']) : ''; ?>" 
                                required
                                placeholder="Enter category name..."
                            >
                        </div>
                        
                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea 
                                class="form-control" 
                                id="description" 
                                name="description" 
                                rows="3"
                                placeholder="Enter category description..."
                            ><?php echo isset($category) ? sanitizeInput($category['description']) : ''; ?></textarea>
                            <small class="text-muted">Optional: Brief description of this category</small>
                        </div>
                        
                        <div class="d-flex justify-content-between">
                            <a href="?action=list" class="btn btn-secondary">
                                <i class="fas fa-times"></i> Cancel
                            </a>
                            <button type="submit" name="<?php echo $action == 'add' ? 'add_category' : 'edit_category'; ?>" class="btn btn-primary">
                                <i class="fas fa-save"></i> <?php echo $action == 'add' ? 'Add Category' : 'Update Category'; ?>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h6 class="card-title mb-0">
                        <i class="fas fa-lightbulb"></i> Category Examples
                    </h6>
                </div>
                <div class="card-body">
                    <h6>Common Categories:</h6>
                    <ul class="small">
                        <li>Food & Beverages</li>
                        <li>Electronics</li>
                        <li>Clothing & Apparel</li>
                        <li>Health & Beauty</li>
                        <li>Home & Garden</li>
                        <li>Sports & Recreation</li>
                        <li>Books & Media</li>
                        <li>Office Supplies</li>
                    </ul>
                    
                    <h6>Best Practices:</h6>
                    <ul class="small">
                        <li>Keep names short and clear</li>
                        <li>Use familiar terms customers understand</li>
                        <li>Group similar products logically</li>
                        <li>Consider seasonal categories</li>
                    </ul>
                </div>
            </div>
            
            <?php if (isset($category)): ?>
            <div class="card mt-3">
                <div class="card-header">
                    <h6 class="card-title mb-0">
                        <i class="fas fa-info"></i> Category Details
                    </h6>
                </div>
                <div class="card-body">
                    <small>
                        <strong>Created:</strong> <?php echo formatDate($category['created_at']); ?><br>
                        <strong>Last Updated:</strong> <?php echo formatDate($category['updated_at']); ?><br>
                        
                        <?php
                        $productCountQuery = "SELECT COUNT(*) as count FROM products WHERE category_id = ?";
                        $productCountResult = executeQuery($productCountQuery, 'i', [$category['id']]);
                        $productCount = $productCountResult[0]['count'];
                        ?>
                        
                        <strong>Associated Products:</strong> <?php echo $productCount; ?><br>
                        
                        <?php if ($productCount > 0): ?>
                            <div class="mt-2">
                                <small class="text-muted">Cannot delete this category while it has associated products.</small>
                            </div>
                        <?php endif; ?>
                    </small>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<style>
/* Pagination styles */
.pagination {
    display: flex;
    padding-left: 0;
    list-style: none;
    border-radius: 0.375rem;
}

.page-item:not(:first-child) .page-link {
    margin-left: -1px;
}

.page-link {
    position: relative;
    display: block;
    color: #667eea;
    text-decoration: none;
    background-color: #fff;
    border: 1px solid #dee2e6;
    padding: 0.5rem 0.75rem;
    transition: color 0.15s ease-in-out, background-color 0.15s ease-in-out;
    cursor: pointer;
}

.page-link:hover {
    z-index: 2;
    color: #5568d3;
    background-color: #e9ecef;
    border-color: #dee2e6;
}

.page-link:focus {
    z-index: 3;
    color: #5568d3;
    background-color: #e9ecef;
    outline: 0;
    box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
}

.page-item.active .page-link {
    z-index: 3;
    color: #fff;
    background-color: #667eea;
    border-color: #667eea;
}

.page-item.disabled .page-link {
    color: #6c757d;
    pointer-events: none;
    background-color: #fff;
    border-color: #dee2e6;
}

.page-item:first-child .page-link {
    border-top-left-radius: 0.375rem;
    border-bottom-left-radius: 0.375rem;
}

.page-item:last-child .page-link {
    border-top-right-radius: 0.375rem;
    border-bottom-right-radius: 0.375rem;
}

.pagination-sm .page-link {
    padding: 0.25rem 0.5rem;
    font-size: 0.875rem;
}
</style>

<?php require_once '../includes/footer.php'; ?>