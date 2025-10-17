<?php
/**
 * Inventory Management Page
 * Admin only - Manage stock levels and inventory
 */

require_once '../includes/header.php';
requireAdmin();

$error = '';
$success = '';

// Handle stock updates
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['update_stock'])) {
        $productId = (int)$_POST['product_id'];
        $newStock = (int)$_POST['new_stock'];
        $reason = sanitizeInput($_POST['reason']);
        
        if ($newStock < 0) {
            $error = 'Stock quantity cannot be negative.';
        } else {
            $query = "UPDATE products SET stock_quantity = ? WHERE id = ?";
            if (executeNonQuery($query, 'ii', [$newStock, $productId])) {
                $success = 'Stock updated successfully.';
                logActivity('Updated stock', $_SESSION['user_id'], "Product ID: $productId, New Stock: $newStock, Reason: $reason");
            } else {
                $error = 'Failed to update stock.';
            }
        }
    }
}

// Get all products with low stock first
$products = executeQuery("
    SELECT p.*, c.name as category_name 
    FROM products p 
    LEFT JOIN categories c ON p.category_id = c.id 
    ORDER BY 
        CASE WHEN p.stock_quantity <= p.min_stock_level THEN 0 ELSE 1 END,
        p.stock_quantity ASC,
        p.name ASC
");

// Separate low stock products
$lowStockProducts = array_filter($products, function($product) {
    return $product['stock_quantity'] <= $product['min_stock_level'];
});

$totalProducts = count($products);
$lowStockCount = count($lowStockProducts);
$outOfStockCount = count(array_filter($products, function($product) {
    return $product['stock_quantity'] <= 0;
}));
?>

<!-- Statistics Cards -->
<div class="row mb-4">
    <div class="col-md-4 mb-3">
        <div class="card stats-card h-100">
            <div class="card-body text-center">
                <i class="fas fa-box fa-2x mb-2"></i>
                <h5 class="card-title"><?php echo $totalProducts; ?></h5>
                <p class="card-text">Total Products</p>
            </div>
        </div>
    </div>
    
    <div class="col-md-4 mb-3">
        <div class="card stats-card-warning h-100">
            <div class="card-body text-center">
                <i class="fas fa-exclamation-triangle fa-2x mb-2"></i>
                <h5 class="card-title"><?php echo $lowStockCount; ?></h5>
                <p class="card-text">Low Stock Items</p>
            </div>
        </div>
    </div>
    
    <div class="col-md-4 mb-3">
        <div class="card stats-card-danger h-100">
            <div class="card-body text-center">
                <i class="fas fa-times-circle fa-2x mb-2"></i>
                <h5 class="card-title"><?php echo $outOfStockCount; ?></h5>
                <p class="card-text">Out of Stock</p>
            </div>
        </div>
    </div>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?php echo $error; ?></div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo $success; ?></div>
<?php endif; ?>

<!-- Inventory Table -->
<div class="card">
    <div class="card-header">
        <h5 class="card-title mb-0">
            <i class="fas fa-warehouse"></i> Inventory Management
        </h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Category</th>
                        <th>Current Stock</th>
                        <th>Min Level</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($products)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-4">
                                <i class="fas fa-box fa-3x text-muted mb-3"></i>
                                <h5 class="text-muted">No products found</h5>
                                <a href="products.php" class="btn btn-primary">Add Products</a>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($products as $product): ?>
                            <tr class="<?php echo $product['stock_quantity'] <= 0 ? 'table-danger' : ($product['stock_quantity'] <= $product['min_stock_level'] ? 'table-warning' : ''); ?>">
                                <td>
                                    <strong><?php echo sanitizeInput($product['name']); ?></strong>
                                    <br>
                                    <small class="text-muted"><?php echo sanitizeInput($product['barcode']); ?></small>
                                </td>
                                <td><?php echo $product['category_name'] ? sanitizeInput($product['category_name']) : '<span class="text-muted">Uncategorized</span>'; ?></td>
                                <td>
                                    <span class="stock-quantity" data-min-stock="<?php echo $product['min_stock_level']; ?>">
                                        <?php echo $product['stock_quantity']; ?>
                                    </span>
                                </td>
                                <td><?php echo $product['min_stock_level']; ?></td>
                                <td>
                                    <?php if ($product['stock_quantity'] <= 0): ?>
                                        <span class="badge bg-danger">Out of Stock</span>
                                    <?php elseif ($product['stock_quantity'] <= $product['min_stock_level']): ?>
                                        <span class="badge bg-warning">Low Stock</span>
                                    <?php else: ?>
                                        <span class="badge bg-success">In Stock</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <button type="button" class="btn btn-outline-primary btn-sm" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#stockModal<?php echo $product['id']; ?>">
                                        <i class="fas fa-edit"></i> Update Stock
                                    </button>
                                    
                                    <!-- Stock Update Modal -->
                                    <div class="modal fade" id="stockModal<?php echo $product['id']; ?>" tabindex="-1">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <form method="POST">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">Update Stock - <?php echo sanitizeInput($product['name']); ?></h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                                        
                                                        <div class="mb-3">
                                                            <label class="form-label">Current Stock</label>
                                                            <input type="text" class="form-control" value="<?php echo $product['stock_quantity']; ?>" readonly>
                                                        </div>
                                                        
                                                        <div class="mb-3">
                                                            <label class="form-label">New Stock Quantity *</label>
                                                            <input type="number" name="new_stock" class="form-control" min="0" 
                                                                   value="<?php echo $product['stock_quantity']; ?>" required>
                                                        </div>
                                                        
                                                        <div class="mb-3">
                                                            <label class="form-label">Reason for Change</label>
                                                            <select name="reason" class="form-select" required>
                                                                <option value="">Select Reason</option>
                                                                <option value="Stock Received">Stock Received</option>
                                                                <option value="Stock Adjustment">Stock Adjustment</option>
                                                                <option value="Damaged Items">Damaged Items</option>
                                                                <option value="Lost Items">Lost Items</option>
                                                                <option value="Expired Items">Expired Items</option>
                                                                <option value="Inventory Count">Inventory Count</option>
                                                                <option value="Other">Other</option>
                                                            </select>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                        <button type="submit" name="update_stock" class="btn btn-primary">Update Stock</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
// Simple modal functionality since we don't have Bootstrap JS
document.querySelectorAll('[data-bs-toggle="modal"]').forEach(function(button) {
    button.addEventListener('click', function() {
        var targetModal = document.querySelector(button.getAttribute('data-bs-target'));
        if (targetModal) {
            targetModal.style.display = 'block';
            targetModal.classList.add('show');
            document.body.classList.add('modal-open');
        }
    });
});

document.querySelectorAll('[data-bs-dismiss="modal"]').forEach(function(button) {
    button.addEventListener('click', function() {
        var modal = button.closest('.modal');
        if (modal) {
            modal.style.display = 'none';
            modal.classList.remove('show');
            document.body.classList.remove('modal-open');
        }
    });
});

// Close modal when clicking outside
window.addEventListener('click', function(event) {
    if (event.target.classList.contains('modal')) {
        event.target.style.display = 'none';
        event.target.classList.remove('show');
        document.body.classList.remove('modal-open');
    }
});
</script>

<style>
/* Simple Modal Styles */
.modal {
    display: none;
    position: fixed;
    z-index: 1050;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    overflow: hidden;
    outline: 0;
    background-color: rgba(0,0,0,0.5);
}

.modal.show {
    display: block !important;
}

.modal-dialog {
    position: relative;
    width: auto;
    margin: 1.75rem auto;
    max-width: 500px;
    pointer-events: none;
}

.modal-content {
    position: relative;
    display: flex;
    flex-direction: column;
    width: 100%;
    pointer-events: auto;
    background-color: #fff;
    background-clip: padding-box;
    border: 1px solid rgba(0,0,0,.2);
    border-radius: .3rem;
    outline: 0;
}

.modal-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    padding: 1rem;
    border-bottom: 1px solid #dee2e6;
    border-top-left-radius: calc(.3rem - 1px);
    border-top-right-radius: calc(.3rem - 1px);
}

.modal-title {
    margin-bottom: 0;
    line-height: 1.5;
}

.modal-body {
    position: relative;
    flex: 1 1 auto;
    padding: 1rem;
}

.modal-footer {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: flex-end;
    padding: .75rem;
    border-top: 1px solid #dee2e6;
    border-bottom-right-radius: calc(.3rem - 1px);
    border-bottom-left-radius: calc(.3rem - 1px);
}

.modal-footer > * {
    margin: .25rem;
}

.btn-close {
    padding: .5rem;
    margin: -.5rem -.5rem -.5rem auto;
    background: transparent;
    border: 0;
    font-size: 1.125rem;
    cursor: pointer;
}

.modal-open {
    overflow: hidden;
}

/* Additional icon styles */
.fa-times-circle::before { content: "❌"; }
</style>

<?php require_once '../includes/footer.php'; ?>