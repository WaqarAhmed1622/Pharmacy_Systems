<?php
/**
 * Products Management Page
 * Admin only - Manage products (CRUD operations)
 *
 * Changes:
 * - Adds is_active soft-disable/enable flag
 * - "Show Disabled Products" filter
 * - Soft-delete (disable) instead of hard delete
 * - Toggle enable/disable button
 * - Normalizes $products variable to an array to avoid count() errors
 */

if (isset($_POST['ajax_search'])) {
    require_once '../includes/auth.php';
    require_once '../config/database.php';
    require_once '../includes/functions.php';

    $search = isset($_POST['search']) ? sanitizeInput($_POST['search']) : '';
    $category_filter = isset($_POST['category']) ? (int)$_POST['category'] : 0;
    $show_disabled = isset($_POST['show_disabled']) && $_POST['show_disabled'] == '1' ? 1 : 0;

    $whereParts = [];
    $params = [];
    $types = '';

    // status filter
    if ($show_disabled) {
        $whereParts[] = "p.is_active = 0";
    } else {
        $whereParts[] = "p.is_active = 1";
    }

    if (!empty($search)) {
        $whereParts[] = "(p.name LIKE ? OR p.barcode LIKE ? OR p.description LIKE ?)";
        $searchTerm = "%$search%";
        $params[] = $searchTerm; $params[] = $searchTerm; $params[] = $searchTerm;
        $types .= 'sss';
    }

    if ($category_filter > 0) {
        $whereParts[] = "p.category_id = ?";
        $params[] = $category_filter;
        $types .= 'i';
    }

    $whereClause = '';
    if (!empty($whereParts)) {
        $whereClause = "WHERE " . implode(" AND ", $whereParts);
    }

    $query = "SELECT p.*, c.name as category_name 
              FROM products p 
              LEFT JOIN categories c ON p.category_id = c.id 
              $whereClause
              ORDER BY p.name";

    $searchResults = executeQuery($query, $types, $params);

    // ensure $searchResults is iterable array
    if ($searchResults === false || $searchResults === null) {
        $searchResults = [];
    }

    // Return only the table body HTML
    if (empty($searchResults)) {
        echo '<tr>
                <td colspan="11" class="text-center py-4">
                    <i class="fas fa-search fa-3x text-muted mb-3"></i>
                    <h5 class="text-muted">No products found</h5>
                    <p class="text-muted">Try adjusting your search terms</p>
                </td>
              </tr>';
    } else {
        foreach ($searchResults as $prod) {
            $stockClass = '';
            if ($prod['stock_quantity'] <= 0) {
                $stockClass = 'table-danger';
            } elseif ($prod['stock_quantity'] <= $prod['min_stock_level']) {
                $stockClass = 'table-warning';
            }

            echo '<tr class="product-row ' . $stockClass . '">';
            echo '<td><strong>' . sanitizeInput($prod['name']) . '</strong>';
            if ($prod['description']) {
                echo '<br><small class="text-muted">' . sanitizeInput(substr($prod['description'], 0, 50)) . '...</small>';
            }
            echo '</td>';
            echo '<td><code>' . sanitizeInput($prod['barcode']) . '</code></td>';
            echo '<td>' . ($prod['category_name'] ? sanitizeInput($prod['category_name']) : '<span class="text-muted">Uncategorized</span>') . '</td>';
            echo '<td>' . formatCurrency($prod['price']) . '</td>';
            echo '<td>' . formatCurrency($prod['cost']) . '</td>';
            echo '<td>' . sanitizeInput($prod['manufacturer']) . '</td>';
            echo '<td>' . (!empty($prod['manufacturing_date']) ? date('Y-m-d', strtotime($prod['manufacturing_date'])) : '<span class="text-muted">N/A</span>') . '</td>';
            echo '<td>' . (!empty($prod['expiry_date']) ? date('Y-m-d', strtotime($prod['expiry_date'])) : '<span class="text-muted">N/A</span>') . '</td>';
            echo '<td>';
            echo '<span class="stock-quantity" data-min-stock="' . $prod['min_stock_level'] . '">' . $prod['stock_quantity'] . '</span>';
            echo '<small class="text-muted">(Min: ' . $prod['min_stock_level'] . ')</small>';
            echo '</td>';
            echo '<td>';
            if ($prod['is_active']) {
                if ($prod['stock_quantity'] <= 0) {
                    echo '<span class="badge bg-danger">Out of Stock</span>';
                } elseif ($prod['stock_quantity'] <= $prod['min_stock_level']) {
                    echo '<span class="badge bg-warning">Low Stock</span>';
                } else {
                    echo '<span class="badge bg-success">In Stock</span>';
                }
                echo '<br><small class="text-muted">Active</small>';
            } else {
                echo '<span class="badge bg-secondary">Disabled</span>';
            }
            echo '<td>';
            echo '<div class="btn-group btn-group-sm">';
            echo '<a href="?action=edit&id=' . $prod['id'] . '" class="btn btn-outline-primary">';
            echo '<i class="fas fa-edit"></i>';
            echo '</a>';

            if ($prod['is_active']) {
                // Disable button for active products
                echo '<form method="POST" class="d-inline">';
                echo '<input type="hidden" name="product_id" value="' . $prod['id'] . '">';
                echo '<button type="submit" name="toggle_product" value="disable" class="btn btn-outline-warning" onclick="return confirm(\'Disable this product? It will be hidden from active lists but history remains.\')" title="Disable Product">';
                echo '<i class="fas fa-ban"></i>';
                echo '</button>';
                echo '</form>';
            } else {
                // Enable button for disabled products
                echo '<form method="POST" class="d-inline">';
                echo '<input type="hidden" name="product_id" value="' . $prod['id'] . '">';
                echo '<button type="submit" name="toggle_product" value="enable" class="btn btn-outline-success" onclick="return confirm(\'Enable this product?\')" title="Enable Product">';
                echo '<i class="fas fa-check"></i>';
                echo '</button>';
                echo '</form>';
                
                // Delete button (hard delete) - only for disabled products
                echo '<form method="POST" class="d-inline" onsubmit="return confirm(\'Are you sure you want to permanently delete this product? This cannot be undone!\')">';
                echo '<input type="hidden" name="product_id" value="' . $prod['id'] . '">';
                echo '<button type="submit" name="hard_delete_product" class="btn btn-outline-danger" title="Permanently Delete">';
                echo '<i class="fas fa-trash"></i>';
                echo '</button>';
                echo '</form>';
            }

            echo '</div>';
            echo '</td>';
            echo '</tr>';
        }
    }
    exit;
}

require_once '../includes/header.php';
requireAdmin();

$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$productId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$error = '';
$success = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST' && !isset($_POST['ajax_search'])) {
    // Add or Edit
    if (isset($_POST['add_product']) || isset($_POST['edit_product'])) {
        $name = sanitizeInput($_POST['name']);
    $barcode = sanitizeInput($_POST['barcode']);
    $categoryId = isset($_POST['category_id']) ? (int)$_POST['category_id'] : 0;
    $price = isset($_POST['price']) && is_numeric($_POST['price']) ? (float)$_POST['price'] : 0.0;
    $cost = isset($_POST['cost']) && is_numeric($_POST['cost']) ? (float)$_POST['cost'] : 0.0;
    $stockQuantity = isset($_POST['stock_quantity']) && is_numeric($_POST['stock_quantity']) ? (int)$_POST['stock_quantity'] : 0;
    $minStockLevel = isset($_POST['min_stock_level']) && is_numeric($_POST['min_stock_level']) ? (int)$_POST['min_stock_level'] : 0;
    $description = sanitizeInput($_POST['description']);
    $manufacturer = isset($_POST['manufacturer']) ? sanitizeInput($_POST['manufacturer']) : '';
    $expiryDate = isset($_POST['expiry_date']) && !empty($_POST['expiry_date']) ? sanitizeInput($_POST['expiry_date']) : null;
    $manufacturingDate = isset($_POST['manufacturing_date']) && !empty($_POST['manufacturing_date']) ? sanitizeInput($_POST['manufacturing_date']) : null;

        // Validation
        if (empty($name) || $price <= 0 || $cost <= 0) {
    $error = 'Please fill all required fields with valid values.';
} elseif (!empty($barcode) && !isBarcodeUnique($barcode, isset($_POST['edit_product']) ? $productId : null)) {
    $error = 'Barcode already exists. Please use a unique barcode.';
} else {
            if (isset($_POST['add_product'])) {
                // Insert (is_active defaults to 1)
                $query = "INSERT INTO products (name, barcode, category_id, price, cost, stock_quantity, min_stock_level, description, manufacturer, expiry_date, manufacturing_date) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $params = [$name, $barcode, $categoryId, $price, $cost, $stockQuantity, $minStockLevel, $description, $manufacturer, $expiryDate, $manufacturingDate];
                $types = 'ssiddiissss';


                if (executeNonQuery($query, $types, $params)) {
                    $success = 'Product added successfully.';
                    logActivity('Added product', $_SESSION['user_id'], "Product: $name");
                    $action='list';
                } else {
                    $error = 'Failed to add product.';
                }
            } else {
                // Update
                $query = "UPDATE products SET name = ?, barcode = ?, category_id = ?, price = ?, cost = ?, 
stock_quantity = ?, min_stock_level = ?, description = ?, manufacturer = ?, expiry_date = ?, manufacturing_date = ? WHERE id = ?";
$params = [$name, $barcode, $categoryId, $price, $cost, $stockQuantity, $minStockLevel, $description, $manufacturer, $expiryDate, $manufacturingDate, $productId];
$types = 'ssiddiissssi';

                if (executeNonQuery($query, $types, $params)) {
                    $success = 'Product updated successfully.';
                    logActivity('Updated product', $_SESSION['user_id'], "Product ID: $productId");
                    $action = 'list';
                } else {
                    $error = 'Failed to update product.';
                }
            }
        }
    }

    // Toggle enable/disable
    if (isset($_POST['toggle_product']) && isset($_POST['product_id'])) {
        $pid = (int)$_POST['product_id'];
        $toggle = sanitizeInput($_POST['toggle_product']);
        if ($toggle === 'disable') {
            $q = "UPDATE products SET is_active = 0 WHERE id = ?";
            if (executeNonQuery($q, 'i', [$pid])) {
                $success = 'Product disabled successfully.';
                logActivity('Disabled product', $_SESSION['user_id'], "Product ID: $pid");
            } else {
                $error = 'Failed to disable product.';
            }
        } elseif ($toggle === 'enable') {
            $q = "UPDATE products SET is_active = 1 WHERE id = ?";
            if (executeNonQuery($q, 'i', [$pid])) {
                $success = 'Product enabled successfully.';
                logActivity('Enabled product', $_SESSION['user_id'], "Product ID: $pid");
            } else {
                $error = 'Failed to enable product.';
            }
        } else {
            $error = 'Invalid action.';
        }
    }

    // Hard delete (permanent delete) - only for disabled products
if (isset($_POST['hard_delete_product']) && isset($_POST['product_id'])) {
    $pid = (int)$_POST['product_id'];

    // Check if product is disabled first
    $checkQuery = "SELECT is_active, name FROM products WHERE id = ?";
    $checkResult = executeQuery($checkQuery, 'i', [$pid]);
    
    if (!empty($checkResult) && $checkResult[0]['is_active'] == 0) {
        // Product is disabled, proceed with hard delete
        $productName = $checkResult[0]['name'];
        $q = "DELETE FROM products WHERE id = ?";
        if (executeNonQuery($q, 'i', [$pid])) {
            $success = 'Product permanently deleted successfully.';
            logActivity('Hard deleted product', $_SESSION['user_id'], "Product: $productName (ID: $pid)");
        } else {
            $error = 'Failed to delete product.';
        }
    } else {
        $error = 'Cannot delete active products. Please disable first.';
    }
}
}

// Get categories for dropdown
$categories = getAllCategories();

// Get product for editing
if ($action == 'edit' && $productId) {
    $productQuery = "SELECT * FROM products WHERE id = ?";
    $productResult = executeQuery($productQuery, 'i', [$productId]);
    if (empty($productResult)) {
        $action = 'list';
    } else {
        $product = $productResult[0];
    }
}

// Get all products for listing with search functionality
if ($action == 'list') {
    $search = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';
    $category_filter = isset($_GET['category']) ? (int)$_GET['category'] : 0;
    $show_disabled = isset($_GET['show_disabled']) && $_GET['show_disabled'] == '1' ? 1 : 0;

    $whereParts = [];
    $params = [];
    $types = '';

    if ($show_disabled) {
        $whereParts[] = "p.is_active = 0";
    } else {
        $whereParts[] = "p.is_active = 1";
    }

    if (!empty($search)) {
        $whereParts[] = "(p.name LIKE ? OR p.barcode LIKE ? OR p.description LIKE ?)";
        $searchTerm = "%$search%";
        $params[] = $searchTerm; $params[] = $searchTerm; $params[] = $searchTerm;
        $types .= 'sss';
    }

    if ($category_filter > 0) {
        $whereParts[] = "p.category_id = ?";
        $params[] = $category_filter;
        $types .= 'i';
    }

    $whereClause = '';
    if (!empty($whereParts)) {
        $whereClause = "WHERE " . implode(" AND ", $whereParts);
    }

    $query = "SELECT p.*, c.name as category_name 
              FROM products p 
              LEFT JOIN categories c ON p.category_id = c.id 
              $whereClause
              ORDER BY p.name";

    $products = executeQuery($query, $types, $params);

    // Normalize $products to array to avoid count() issues
    if ($products === false || $products === null) {
        $products = [];
    } elseif ($products instanceof mysqli_result) {
        // in case executeQuery returns mysqli_result (unlikely with helper), convert
        $tmp = [];
        while ($row = $products->fetch_assoc()) {
            $tmp[] = $row;
        }
        $products = $tmp;
    } elseif (!is_array($products)) {
        $products = [];
    }
}
?>

<?php if ($action == 'list'): ?>
    <!-- Search and Filter Section (Moved to Top) -->
    <div class="card mb-4">
        <div class="card-header">
            <h6 class="card-title mb-0">
                <i class="fas fa-search"></i> Search & Filter Products
            </h6>
        </div>
        <div class="card-body">
            <form method="GET" class="row g-3" id="searchForm">
                <input type="hidden" name="action" value="list">
                <div class="col-md-6">
                    <label class="form-label">Search Products</label>
                    <input 
                        type="text" 
                        name="search" 
                        id="searchInput"
                        class="form-control" 
                        placeholder="Start typing product name, barcode..."
                        value="<?php echo isset($_GET['search']) ? sanitizeInput($_GET['search']) : ''; ?>"
                        autocomplete="off"
                    >
                    <small class="text-muted">Search starts automatically as you type</small>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Filter by Category</label>
                    <select name="category" class="form-select" id="categoryFilter">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo $cat['id']; ?>" 
                                    <?php echo (isset($_GET['category']) && $_GET['category'] == $cat['id']) ? 'selected' : ''; ?>>
                                <?php echo sanitizeInput($cat['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-2">
                    <label class="form-label">&nbsp;</label>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" value="1" id="showDisabled" name="show_disabled"
                            <?php echo isset($_GET['show_disabled']) && $_GET['show_disabled'] == '1' ? 'checked' : ''; ?>>
                        <label class="form-check-label small" for="showDisabled">
                            Show Disabled Products
                        </label>
                    </div>
                </div>

                <div class="col-md-1 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary me-2">
                        <i class="fas fa-search"></i>
                    </button>
                </div>
            </form>

            <!-- Results Counter -->
            <div class="mt-2">
                <small class="text-muted" id="resultsCount">
                    <?php 
                    $totalProducts = is_array($products) ? count($products) : 0;
                    if (isset($_GET['search']) || isset($_GET['category']) || isset($_GET['show_disabled'])) {
                        $filterText = isset($_GET['show_disabled']) && $_GET['show_disabled'] == '1' ? 'disabled' : 'active';
                        echo "Found $totalProducts $filterText products";
                        if (isset($_GET['search']) && !empty($_GET['search'])) {
                            echo " matching \"" . sanitizeInput($_GET['search']) . "\"";
                        }
                        if (isset($_GET['category']) && $_GET['category'] > 0) {
                            $catName = '';
                            foreach ($categories as $cat) {
                                if ($cat['id'] == $_GET['category']) {
                                    $catName = $cat['name'];
                                    break;
                                }
                            }
                            echo " in category \"$catName\"";
                        }
                    } else {
                        echo "Showing all $totalProducts active products";
                    }
                    ?>
                </small>
            </div>
        </div>
    </div>
     <!-- Success/Error Messages -->
    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle"></i> <?php echo $success; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>               
    <!-- Products List -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3><i class="fas fa-box"></i> Products Management</h3>
        <a href="?action=add" class="btn btn-primary">
            <i class="fas fa-plus"></i> Add Product
        </a>
    </div>
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped" id="productsTable">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Barcode</th>
                            <th>Category</th>
                            <th>Price (Rs)</th>
                            <th>Cost (Rs)</th>
                            <th>Manufacturer</th>
                            <th>Mfg. Date</th>
                            <th>Expiry Date</th>
                            <th>Stock</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="productsTableBody">
                        <?php if (empty($products)): ?>
                            <tr>
                                <td colspan="11" class="text-center py-4">
                                    <i class="fas fa-box fa-3x text-muted mb-3"></i>
                                    <h5 class="text-muted">
                                        <?php echo (isset($_GET['search']) || isset($_GET['category']) || isset($_GET['show_disabled'])) ? 'No products found' : 'No products added yet'; ?>
                                    </h5>
                                    <?php if (isset($_GET['search']) || isset($_GET['category']) || isset($_GET['show_disabled'])): ?>
                                        <p class="text-muted">Try adjusting your search terms or filters</p>
                                        <a href="?action=list" class="btn btn-outline-primary me-2">Clear Filters</a>
                                    <?php endif; ?>
                                    <a href="?action=add" class="btn btn-primary">Add Product</a>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($products as $prod): ?>
                                <tr class="product-row <?php echo $prod['stock_quantity'] <= 0 ? 'table-danger' : ($prod['stock_quantity'] <= $prod['min_stock_level'] ? 'table-warning' : ''); ?>">
                                    <td>
                                        <strong><?php echo sanitizeInput($prod['name']); ?></strong>
                                        <?php if ($prod['description']): ?>
                                            <br><small class="text-muted"><?php echo sanitizeInput(substr($prod['description'], 0, 50)); ?>...</small>
                                        <?php endif; ?>
                                    </td>
                                    <td><code><?php echo sanitizeInput($prod['barcode']); ?></code></td>
                                    <td><?php echo $prod['category_name'] ? sanitizeInput($prod['category_name']) : '<span class="text-muted">Uncategorized</span>'; ?></td>
                                    <td><?php echo formatCurrency($prod['price']); ?></td>
                                    <td><?php echo formatCurrency($prod['cost']); ?></td>
                                    <td><?php echo sanitizeInput($prod['manufacturer']); ?></td>
                                    <td><?php echo !empty($prod['manufacturing_date']) ? date('Y-m-d', strtotime($prod['manufacturing_date'])) : '<span class="text-muted">N/A</span>'; ?></td>
                                    <td><?php echo !empty($prod['expiry_date']) ? date('Y-m-d', strtotime($prod['expiry_date'])) : '<span class="text-muted">N/A</span>'; ?></td>
                                    <td>
                                        <span class="stock-quantity" data-min-stock="<?php echo $prod['min_stock_level']; ?>">
                                            <?php echo $prod['stock_quantity']; ?>
                                        </span>
                                        <small class="text-muted">(Min: <?php echo $prod['min_stock_level']; ?>)</small>
                                    </td>
                                    <td>
                                        <?php if ($prod['is_active']): ?>
                                            <?php if ($prod['stock_quantity'] <= 0): ?>
                                                <span class="badge bg-danger">Out of Stock</span>
                                            <?php elseif ($prod['stock_quantity'] <= $prod['min_stock_level']): ?>
                                                <span class="badge bg-warning">Low Stock</span>
                                            <?php else: ?>
                                                <span class="badge bg-success">In Stock</span>
                                            <?php endif; ?>
                                            <br><small class="text-muted">Active</small>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Disabled</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="?action=edit&id=<?php echo $prod['id']; ?>" class="btn btn-outline-primary">
                                                <i class="fas fa-edit"></i>
                                            </a>

                                            <?php if ($prod['is_active']): ?>
                                                <!-- Disable button for active products -->
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="product_id" value="<?php echo $prod['id']; ?>">
                                                    <button type="submit" name="toggle_product" value="disable" class="btn btn-outline-warning" onclick="return confirm('Disable this product? It will be hidden from active lists but history remains.')" title="Disable Product">
                                                        <i class="fas fa-ban"></i>
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <!-- Enable button for disabled products -->
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="product_id" value="<?php echo $prod['id']; ?>">
                                                    <button type="submit" name="toggle_product" value="enable" class="btn btn-outline-success" onclick="return confirm('Enable this product?')" title="Enable Product">
                                                        <i class="fas fa-check"></i>
                                                    </button>
                                                </form>
                                            <?php endif; ?>

                                            <!-- Delete button (hard delete) - only shows for disabled products -->
                                            <?php if (!$prod['is_active']): ?>
                                                <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to permanently delete this product? This cannot be undone!')">
                                                    <input type="hidden" name="product_id" value="<?php echo $prod['id']; ?>">
                                                    <button type="submit" name="hard_delete_product" class="btn btn-outline-danger" title="Permanently Delete">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
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
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle"></i> <?php echo $success; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <script>
    // Simple live search functionality with show_disabled support
    let searchTimeout;

    function performSearch() {
        const searchTerm = document.getElementById('searchInput').value;
        const categoryId = document.getElementById('categoryFilter').value;
        const showDisabled = document.getElementById('showDisabled').checked ? '1' : '0';

        clearTimeout(searchTimeout);

        searchTimeout = setTimeout(function() {
            if (searchTerm.length >= 2 || categoryId || searchTerm.length === 0 || showDisabled === '1') {
                // Show loading
                document.getElementById('productsTableBody').innerHTML = 
                    '<tr><td colspan="11" class="text-center py-4">Searching...</td></tr>';

                // Create form data
                const formData = new FormData();
                formData.append('ajax_search', '1');
                formData.append('search', searchTerm);
                formData.append('category', categoryId);
                formData.append('show_disabled', showDisabled);

                // Send request
                fetch(window.location.pathname + window.location.search, {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.text())
                .then(data => {
                    document.getElementById('productsTableBody').innerHTML = data;

                    // Update counter
                    const rows = document.querySelectorAll('.product-row');
                    let countText = 'Found ' + rows.length + ' products';
                    if (searchTerm) countText += ' matching "' + searchTerm + '"';
                    document.getElementById('resultsCount').textContent = countText;
                })
                .catch(error => {
                    document.getElementById('productsTableBody').innerHTML = 
                        '<tr><td colspan="11" class="text-center py-4 text-danger">Search error. Please try again.</td></tr>';
                });
            }
        }, 300);
    }

    // Add event listeners
    document.getElementById('searchInput').addEventListener('input', performSearch);
    document.getElementById('categoryFilter').addEventListener('change', performSearch);
    document.getElementById('showDisabled').addEventListener('change', function() {
        // submit the GET form to persist show_disabled in URL
        document.getElementById('searchForm').submit();
    });
   </script>

    <?php if ($success && strpos($success, 'added successfully') !== false): ?>
    <script>
        // Auto-scroll to top to show success message
        window.scrollTo({ top: 0, behavior: 'smooth' });
        
        // Auto-dismiss success message after 5 seconds
        setTimeout(function() {
            const alert = document.querySelector('.alert-success');
            if (alert) {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            }
        }, 5000);
    </script>
    <?php endif; ?>

    <?php else: ?>
    <!-- Add/Edit Product Form (unchanged, left intact) -->
    <!-- Add/Edit Product Form (unchanged, left intact) -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3>
            <i class="fas fa-<?php echo $action == 'add' ? 'plus' : 'edit'; ?>"></i> 
            <?php echo ucfirst($action); ?> Product
        </h3>
        <a href="?action=list" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back to List
        </a>
    </div>
    <div class="row">
        <div class="col-md-8">
            <div class="card">
                <div class="card-body">
                    <form method="POST">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="name" class="form-label">Product Name *</label>
                                <input 
                                    type="text" 
                                    class="form-control" 
                                    id="name" 
                                    name="name" 
                                    value="<?php echo isset($product) ? sanitizeInput($product['name']) : ''; ?>" 
                                    required
                                >
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="barcode" class="form-label">Barcode</label>
                                <input 
                                    type="text" 
                                    class="form-control barcode-input" 
                                    id="barcode" 
                                    name="barcode" 
                                    value="<?php echo isset($product) ? sanitizeInput($product['barcode']) : ''; ?>" 
                                >
                                <small class="text-muted">Must be unique</small>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="category_id" class="form-label">Category</label>
                                <select class="form-select" id="category_id" name="category_id">
                                    <option value="">Select Category</option>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?php echo $cat['id']; ?>" 
                                                <?php echo (isset($product) && $product['category_id'] == $cat['id']) ? 'selected' : ''; ?>>
                                            <?php echo sanitizeInput($cat['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label for="price" class="form-label">Selling Price * (Rs)</label>
                                <input 
                                    type="number" 
                                    class="form-control currency-input" 
                                    id="price" 
                                    name="price" 
                                    step="0.01" 
                                    min="0" 
                                    value="<?php echo isset($product) ? $product['price'] : ''; ?>" 
                                    required
                                >
                            </div>
                            <div class="col-md-3 mb-3">
                                <label for="cost" class="form-label">Cost Price * (Rs)</label>
                                <input 
                                    type="number" 
                                    class="form-control currency-input" 
                                    id="cost" 
                                    name="cost" 
                                    step="0.01" 
                                    min="0" 
                                    value="<?php echo isset($product) ? $product['cost'] : ''; ?>" 
                                    required
                                >
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="stock_quantity" class="form-label">Stock Quantity *</label>
                                <input 
                                    type="number" 
                                    class="form-control" 
                                    id="stock_quantity" 
                                    name="stock_quantity" 
                                    min="0" 
                                    value="<?php echo isset($product) ? $product['stock_quantity'] : '0'; ?>" 
                                    required
                                >
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="min_stock_level" class="form-label">Minimum Stock Level *</label>
                                <input 
                                    type="number" 
                                    class="form-control" 
                                    id="min_stock_level" 
                                    name="min_stock_level" 
                                    min="0" 
                                    value="<?php echo isset($product) ? $product['min_stock_level'] : '5'; ?>" 
                                    required
                                >
                                <small class="text-muted">Alert when stock reaches this level</small>
                            </div>
                        </div>

       <div class="row">
    <!-- Manufacturer -->
    <div class="col-md-6 mb-3">
        <label for="manufacturer" class="form-label">Manufacturer</label>
        <input 
            type="text" 
            class="form-control" 
            id="manufacturer" 
            name="manufacturer" 
            placeholder="Enter manufacturer name"
            value="<?php echo isset($product) ? sanitizeInput($product['manufacturer']) : ''; ?>"
        >
    </div>

    <!-- Manufacturing Date -->
    <div class="col-md-4 mb-3">
        <label for="manufacturing_date" class="form-label">Manufacturing Date</label>
        <input 
            type="date" 
            class="form-control" 
            id="manufacturing_date" 
            name="manufacturing_date" 
            value="<?php echo isset($product) && !empty($product['manufacturing_date']) ? date('Y-m-d', strtotime($product['manufacturing_date'])) : ''; ?>"
        >
    </div>

    <!-- Expiry Date -->
    <div class="col-md-6 mb-3">
        <label for="expiry_date" class="form-label">Expiry Date</label>
        <input 
            type="date" 
            class="form-control" 
            id="expiry_date" 
            name="expiry_date" 
            value="<?php echo isset($product) && !empty($product['expiry_date']) ? date('Y-m-d', strtotime($product['expiry_date'])) : ''; ?>"
        >
    </div>
</div>

                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea 
                                class="form-control" 
                                id="description" 
                                name="description" 
                                rows="3"
                            ><?php echo isset($product) ? sanitizeInput($product['description']) : ''; ?></textarea>
                        </div>
                        <div class="d-flex justify-content-between">
                            <a href="?action=list" class="btn btn-secondary">
                                <i class="fas fa-times"></i> Cancel
                            </a>
                            <button type="submit" name="<?php echo $action == 'add' ? 'add_product' : 'edit_product'; ?>" class="btn btn-primary">
                                <i class="fas fa-save"></i> <?php echo $action == 'add' ? 'Add Product' : 'Update Product'; ?>
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
                        <i class="fas fa-info-circle"></i> Product Information
                    </h6>
                </div>
                <div class="card-body">
                    <h6>Barcode Guidelines:</h6>
                    <ul class="small">
                        <li>Must be unique across all products</li>
                        <li>Can be scanned or entered manually</li>
                        <li>Common formats: UPC, EAN, Code 128</li>
                    </ul>
                    <h6>Stock Management:</h6>
                    <ul class="small">
                        <li>Stock updates automatically on sales</li>
                        <li>Set appropriate minimum levels</li>
                        <li>System alerts when stock is low</li>
                    </ul>
                    <h6>Pricing:</h6>
                    <ul class="small">
                        <li>Cost: Your purchase price (Rs)</li>
                        <li>Price: Customer selling price (Rs)</li>
                        <li>Margin: Price - Cost (Rs)</li>
                    </ul>
                    <?php if (isset($product)): ?>
                        <hr>
                        <h6>Current Product:</h6>
                        <small>
                            <strong>Created:</strong> <?php echo formatDate($product['created_at']); ?><br>
                            <strong>Updated:</strong> <?php echo formatDate($product['updated_at']); ?><br>
                            <strong>Profit Margin:</strong> <?php echo formatCurrency($product['price'] - $product['cost']); ?>
                        </small>
                    <?php endif; ?>
                </div>
            </div>
            <?php if ($action == 'add'): ?>
            <div class="card mt-3">
                <div class="card-header">
                    <h6 class="card-title mb-0">
                        <i class="fas fa-magic"></i> Quick Actions
                    </h6>
                </div>
                <div class="card-body">
                    <button type="button" class="btn btn-outline-primary btn-sm mb-2 w-100" onclick="generateBarcode()">
                        Generate Random Barcode
                    </button>
                    <button type="button" class="btn btn-outline-secondary btn-sm w-100" onclick="fillSampleData()">
                        Fill Sample Data
                    </button>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <script>
        // Generate random barcode
        function generateBarcode() {
            const barcode = Math.random().toString().substring(2, 15);
            document.getElementById('barcode').value = barcode;
        }
        // Fill sample data for testing
        // Fill sample data for testing
function fillSampleData() {
    document.getElementById('name').value = 'Sample Product';
    document.getElementById('barcode').value = Math.random().toString().substring(2, 15);
    
    const categorySelect = document.getElementById('category_id');
    if (categorySelect.options.length > 1) {
        categorySelect.selectedIndex = 1;
    }
    
    document.getElementById('price').value = '99.99';
    document.getElementById('cost').value = '59.99';
    document.getElementById('stock_quantity').value = '50';
    document.getElementById('min_stock_level').value = '10';
    
    // Set manufacturing date to 6 months ago
    const mfgDate = new Date();
    mfgDate.setMonth(mfgDate.getMonth() - 6);
    const formattedMfgDate = mfgDate.toISOString().split('T')[0];
    document.getElementById('manufacturing_date').value = formattedMfgDate;
    
    // Set expiry date to 1 year from now
    const expiryDate = new Date();
    expiryDate.setFullYear(expiryDate.getFullYear() + 1);
    const formattedExpDate = expiryDate.toISOString().split('T')[0];
    document.getElementById('expiry_date').value = formattedExpDate;
    
    document.getElementById('manufacturer').value = 'Sample Manufacturer';
    document.getElementById('description').value = 'Sample product description for testing.';
}
    </script>
<?php endif; ?>

<?php require_once '../includes/footer.php'; ?>
