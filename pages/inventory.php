<?php
/**
 * Inventory Management Page
 * Admin only - Manage stock levels and inventory
*/

require_once '../includes/header.php';
requireAdmin();

$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
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
            logActivity('Updated stock', $_SESSION['user_id'], "Product ID: $productId, New Stock: $newStock, Reason: $reason");
            $success = 'Stock updated successfully.';
        } else {
    $error = 'Failed to update stock.';
}
        }
    }
}

// Get all products with low stock first
// Pagination settings
$limit = 50; // Products per page
$offset = ($page - 1) * $limit;

// Get total count
$countQuery = "SELECT COUNT(*) as total FROM products p LEFT JOIN categories c ON p.category_id = c.id";
$countResult = executeQuery($countQuery);
$totalProducts = (!empty($countResult) && isset($countResult[0])) ? $countResult[0]['total'] : 0;
$totalPages = ceil($totalProducts / $limit);

// Get products with pagination and low stock first
$products = executeQuery("
    SELECT p.*, c.name as category_name 
    FROM products p 
    LEFT JOIN categories c ON p.category_id = c.id 
    ORDER BY 
        CASE WHEN p.stock_quantity <= p.min_stock_level THEN 0 ELSE 1 END,
        p.stock_quantity ASC,
        p.name ASC
    LIMIT $limit OFFSET $offset
");

// Separate low stock products
$lowStockProducts = array_filter($products, function($product) {
    return $product['stock_quantity'] <= $product['min_stock_level'];
});

$totalProductsPage = count($products);
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
        <div class="mb-3">
            <input type="text" id="liveSearch" class="form-control" placeholder="🔍 Search by name, barcode, or category...">
        </div>

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
                <tbody id="inventoryTable">
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
                                    <strong><?php echo sanitizeInput($product['name']); ?></strong><br>
                                    <small class="text-muted"><?php echo sanitizeInput($product['barcode']); ?></small>
                                </td>
                                <td><?php echo $product['category_name'] ? sanitizeInput($product['category_name']) : '<span class="text-muted">Uncategorized</span>'; ?></td>
                                <td><?php echo $product['stock_quantity']; ?></td>
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
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <!-- Add this single dynamic modal at the bottom of your page (after the foreach loop) -->
<div class="modal" id="dynamicStockModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="dynamicModalTitle">Update Stock</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close">×</button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" name="product_id" id="dynamic_product_id" value="">
                    
                    <div class="mb-3">
                        <label class="form-label">Current Stock: <strong id="dynamic_current_stock"></strong></label>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Adjustment Type</label>
                        <div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="adjustment_type" 
                                       id="dynamic_increase" value="increase" checked>
                                <label class="form-check-label" for="dynamic_increase">
                                    <i class="fas fa-plus-circle text-success"></i> Increase
                                </label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="adjustment_type" 
                                       id="dynamic_decrease" value="decrease">
                                <label class="form-check-label" for="dynamic_decrease">
                                    <i class="fas fa-minus-circle text-danger"></i> Decrease
                                </label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="dynamic_adjust_stock" class="form-label">Adjust By</label>
                        <input type="number" class="form-control" id="dynamic_adjust_stock" 
                               name="adjust_stock" value="0" min="0" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="dynamic_new_stock" class="form-label">New Stock Level</label>
                        <input type="number" class="form-control" id="dynamic_new_stock" 
                               name="new_stock" value="0" min="0" required readonly>
                    </div>
                    
                    <div class="mb-3">
                        <label for="dynamic_reason" class="form-label">Reason for Update</label>
                        <textarea class="form-control" id="dynamic_reason" 
                                  name="reason" rows="2" required></textarea>
                        <small class="text-muted">e.g., Stock received, Sales, Damaged items, etc.</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="update_stock" class="btn btn-primary">
                        <i class="fas fa-save"></i> Update Stock
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <?php if ($totalPages > 1): ?>
            <nav aria-label="Inventory pagination" class="mt-4">
                <ul class="pagination justify-content-center">
                    <?php if ($page > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?php echo $page - 1; ?>">Previous</a>
                        </li>
                    <?php endif; ?>

                    <?php
                    $start = max(1, $page - 2);
                    $end = min($totalPages, $page + 2);

                    for ($i = $start; $i <= $end; $i++): ?>
                        <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                        </li>
                    <?php endfor; ?>

                    <?php if ($page < $totalPages): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?php echo $page + 1; ?>">Next</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </nav>
            
            <div class="text-center mt-3">
                <small class="text-muted">
                    Showing page <?php echo $page; ?> of <?php echo $totalPages; ?> 
                    (<?php echo $totalProducts; ?> total products)
                </small>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Stock Update Modals -->
<?php foreach ($products as $product): ?>
<div class="modal" id="stockModal<?php echo $product['id']; ?>" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Update Stock - <?php echo sanitizeInput($product['name']); ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close">×</button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                    
                    <div class="mb-3">
                        <label class="form-label">Current Stock: <strong><?php echo $product['stock_quantity']; ?></strong></label>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Adjustment Type</label>
                        <div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="adjustment_type_<?php echo $product['id']; ?>" 
                                       id="increase_<?php echo $product['id']; ?>" value="increase" checked>
                                <label class="form-check-label" for="increase_<?php echo $product['id']; ?>">
                                    <i class="fas fa-plus-circle text-success"></i> Increase
                                </label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="adjustment_type_<?php echo $product['id']; ?>" 
                                       id="decrease_<?php echo $product['id']; ?>" value="decrease">
                                <label class="form-check-label" for="decrease_<?php echo $product['id']; ?>">
                                    <i class="fas fa-minus-circle text-danger"></i> Decrease
                                </label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="adjust_stock_<?php echo $product['id']; ?>" class="form-label">Adjust By</label>
                        <input type="number" class="form-control" id="adjust_stock_<?php echo $product['id']; ?>" 
                               name="adjust_stock" value="0" min="0" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="new_stock_<?php echo $product['id']; ?>" class="form-label">New Stock Level</label>
                        <input type="number" class="form-control" id="new_stock_<?php echo $product['id']; ?>" 
                               name="new_stock" value="<?php echo $product['stock_quantity']; ?>" 
                               data-current="<?php echo $product['stock_quantity']; ?>" min="0" required readonly>
                    </div>
                    
                    <div class="mb-3">
                        <label for="reason_<?php echo $product['id']; ?>" class="form-label">Reason for Update</label>
                        <textarea class="form-control" id="reason_<?php echo $product['id']; ?>" 
                                  name="reason" rows="2" required></textarea>
                        <small class="text-muted">e.g., Stock received, Sales, Damaged items, etc.</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="update_stock" class="btn btn-primary">
                        <i class="fas fa-save"></i> Update Stock
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endforeach; ?>

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
    font-size: 1.5rem;
    cursor: pointer;
    line-height: 1;
}

.modal-open {
    overflow: hidden;
}

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

.page-item.active .page-link {
    z-index: 3;
    color: #fff;
    background-color: #667eea;
    border-color: #667eea;
}

.page-item:first-child .page-link {
    border-top-left-radius: 0.375rem;
    border-bottom-left-radius: 0.375rem;
}

.page-item:last-child .page-link {
    border-top-right-radius: 0.375rem;
    border-bottom-right-radius: 0.375rem;
}
</style>

<script>
// Enhanced JavaScript with better event handling
document.addEventListener('DOMContentLoaded', function() {
    // Event delegation for update stock buttons (works for dynamically loaded content)
    document.addEventListener('click', function(e) {
        // Handle update stock buttons - both static and dynamic
        if (e.target.closest('.update-stock-btn') || e.target.closest('[data-bs-target^="#stockModal"]')) {
            e.preventDefault();
            const button = e.target.closest('.update-stock-btn') || e.target.closest('[data-bs-target^="#stockModal"]');
            
            // Get product data from data attributes or from existing modal approach
            if (button.classList.contains('update-stock-btn')) {
                // New approach for dynamic content
                openDynamicModal(
                    button.getAttribute('data-product-id'),
                    button.getAttribute('data-product-name'),
                    parseInt(button.getAttribute('data-current-stock'))
                );
            } else {
                // Original approach for static content
                const targetModalId = button.getAttribute('data-bs-target');
                const targetModal = document.querySelector(targetModalId);
                
                if (targetModal) {
                    const modalId = targetModal.id.replace('stockModal', '');
                    resetModalFields(modalId);
                    targetModal.style.display = 'block';
                    targetModal.classList.add('show');
                    document.body.classList.add('modal-open');
                }
            }
        }
        
        // Close modal handlers (unchanged)
        if (e.target.closest('[data-bs-dismiss="modal"]')) {
            const button = e.target.closest('[data-bs-dismiss="modal"]');
            const modal = button.closest('.modal');
            if (modal) {
                closeModal(modal);
            }
        }
        
        if (e.target.classList.contains('modal')) {
            closeModal(e.target);
        }
    });

    // Stock adjustment handlers
    document.addEventListener('input', handleStockAdjustment);
    document.addEventListener('change', handleStockAdjustment);
});

// Dynamic modal functions
function openDynamicModal(productId, productName, currentStock) {
    const modal = document.getElementById('dynamicStockModal');
    const title = document.getElementById('dynamicModalTitle');
    const productIdInput = document.getElementById('dynamic_product_id');
    const currentStockDisplay = document.getElementById('dynamic_current_stock');
    const newStockInput = document.getElementById('dynamic_new_stock');
    const adjustInput = document.getElementById('dynamic_adjust_stock');
    
    if (modal && title && productIdInput) {
        // Set modal content
        title.textContent = `Update Stock - ${productName}`;
        productIdInput.value = productId;
        currentStockDisplay.textContent = currentStock;
        newStockInput.value = currentStock;
        newStockInput.setAttribute('data-current', currentStock);
        adjustInput.value = 0;
        
        // Reset radio buttons
        document.getElementById('dynamic_increase').checked = true;
        document.getElementById('dynamic_reason').value = '';
        
        // Show modal
        modal.style.display = 'block';
        modal.classList.add('show');
        document.body.classList.add('modal-open');
    }
}

function closeModal(modal) {
    modal.style.display = 'none';
    modal.classList.remove('show');
    document.body.classList.remove('modal-open');
}

function handleStockAdjustment(e) {
    // Handle dynamic modal adjustments
    if (e.target.id === 'dynamic_adjust_stock' || e.target.name === 'adjustment_type') {
        updateDynamicStock();
        return;
    }
    
    // Handle original modal adjustments
    if (e.target.id && e.target.id.startsWith('adjust_stock_')) {
        const productId = e.target.id.replace('adjust_stock_', '');
        updateStockDisplay(productId);
    }
    
    if (e.target.name && e.target.name.startsWith('adjustment_type_')) {
        const productId = e.target.name.replace('adjustment_type_', '');
        updateStockDisplay(productId);
    }
}

function updateDynamicStock() {
    const adjustInput = document.getElementById('dynamic_adjust_stock');
    const newStockInput = document.getElementById('dynamic_new_stock');
    const typeIncrease = document.getElementById('dynamic_increase');
    
    if (!adjustInput || !newStockInput || !typeIncrease) return;
    
    const currentStock = parseInt(newStockInput.getAttribute('data-current')) || 0;
    const adjustValue = parseInt(adjustInput.value) || 0;
    
    if (typeIncrease.checked) {
        newStockInput.value = currentStock + adjustValue;
    } else {
        const newValue = currentStock - adjustValue;
        newStockInput.value = newValue < 0 ? 0 : newValue;
    }
}

// Original function for static modals
function updateStockDisplay(productId) {
    const adjustInput = document.getElementById('adjust_stock_' + productId);
    const newStockInput = document.getElementById('new_stock_' + productId);
    const typeIncrease = document.getElementById('increase_' + productId);
    
    if (!adjustInput || !newStockInput || !typeIncrease) return;
    
    const currentStock = parseInt(newStockInput.getAttribute('data-current')) || 0;
    const adjustValue = parseInt(adjustInput.value) || 0;
    
    if (typeIncrease.checked) {
        newStockInput.value = currentStock + adjustValue;
    } else {
        const newValue = currentStock - adjustValue;
        newStockInput.value = newValue < 0 ? 0 : newValue;
    }
}

function resetModalFields(modalId) {
    const adjustInput = document.getElementById('adjust_stock_' + modalId);
    const newStockInput = document.getElementById('new_stock_' + modalId);
    const increaseRadio = document.getElementById('increase_' + modalId);
    const currentStock = parseInt(newStockInput.getAttribute('data-current'));
    
    if (adjustInput && newStockInput) {
        adjustInput.value = 0;
        newStockInput.value = currentStock;
        if (increaseRadio) {
            increaseRadio.checked = true;
        }
    }
}

// Live Search Functionality (updated to use new button class)
const liveSearchInput = document.getElementById('liveSearch');
if (liveSearchInput) {
    liveSearchInput.addEventListener('keyup', function() {
        const query = this.value.trim();
        const tableBody = document.getElementById('inventoryTable');

        if (query.length === 0) {
            location.reload();
            return;
        }

        fetch('inventory_search.php?query=' + encodeURIComponent(query))
            .then(response => response.text())
            .then(html => {
                tableBody.innerHTML = html;
            })
            .catch(error => {
                console.error('Search error:', error);
            });
    });
}

// Prevent form resubmission on page refresh
if (window.history.replaceState) {
    window.history.replaceState(null, null, window.location.href);
}
</script>

<?php require_once '../includes/footer.php'; ?>