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

// Helper function to get expiry status
function getExpiryStatus($expiryDate) {
    if (empty($expiryDate)) {
        return ['status' => 'none', 'class' => '', 'text' => ''];
    }
    
    $expiry = strtotime($expiryDate);
    $today = strtotime(date('Y-m-d'));
    $daysUntilExpiry = floor(($expiry - $today) / (60 * 60 * 24));
    
    if ($daysUntilExpiry < 0) {
        return ['status' => 'expired', 'class' => 'bg-danger', 'text' => 'Expired'];
    } elseif ($daysUntilExpiry <= 30) {
        return ['status' => 'expiring', 'class' => 'bg-warning', 'text' => $daysUntilExpiry . ' days'];
    } else {
        return ['status' => 'valid', 'class' => 'bg-success', 'text' => 'Valid'];
    }
}

if (isset($_POST['ajax_search'])) {
    require_once '../includes/auth.php';
    require_once '../config/database.php';
    require_once '../includes/functions.php';

    $search = isset($_POST['search']) ? sanitizeInput($_POST['search']) : '';
    $category_filter = isset($_POST['category']) ? (int)$_POST['category'] : 0;
    $show_disabled = isset($_POST['show_disabled']) && $_POST['show_disabled'] == '1' ? 1 : 0;
    $stock_status = isset($_POST['stock_status']) ? sanitizeInput($_POST['stock_status']) : '';
    $manufacturer = isset($_POST['manufacturer']) ? sanitizeInput($_POST['manufacturer']) : '';
    $expiry_status = isset($_POST['expiry_status']) ? sanitizeInput($_POST['expiry_status']) : '';
    $price_range = isset($_POST['price_range']) ? sanitizeInput($_POST['price_range']) : '';
    $non_selling = isset($_POST['non_selling']) ? (int)$_POST['non_selling'] : 0;
    
    // PAGINATION FOR AJAX
    $page = isset($_POST['page']) ? max(1, (int)$_POST['page']) : 1;
    $limit = 20;
    $offset = ($page - 1) * $limit;
    $whereParts = [];
    $params = [];
    $types = '';

    // status filter
    if ($show_disabled) {
        $whereParts[] = "p.is_active = 0";
    } else {
        $whereParts[] = "p.is_active = 1";
    }

    // Search filter
    if (!empty($search)) {
        $whereParts[] = "(p.name LIKE ? OR p.barcode LIKE ? OR p.description LIKE ? OR p.manufacturer LIKE ?)";
        $searchTerm = "%$search%";
        $params[] = $searchTerm; 
        $params[] = $searchTerm; 
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $types .= 'ssss';
    }

    // Category filter
    if ($category_filter > 0) {
        $whereParts[] = "p.category_id = ?";
        $params[] = $category_filter;
        $types .= 'i';
    }

    // Stock status filter
    if (!empty($stock_status)) {
        if ($stock_status == 'out_of_stock') {
            $whereParts[] = "p.stock_quantity <= 0";
        } elseif ($stock_status == 'low_stock') {
            $whereParts[] = "p.stock_quantity > 0 AND p.stock_quantity <= p.min_stock_level";
        } elseif ($stock_status == 'in_stock') {
            $whereParts[] = "p.stock_quantity > p.min_stock_level";
        }
    }

    // Manufacturer filter
    if (!empty($manufacturer)) {
        $whereParts[] = "p.manufacturer = ?";
        $params[] = $manufacturer;
        $types .= 's';
    }

    // Expiry status filter
    if (!empty($expiry_status)) {
        $today = date('Y-m-d');
        $thirtyDaysLater = date('Y-m-d', strtotime('+30 days'));
        
        if ($expiry_status == 'expired') {
            $whereParts[] = "p.expiry_date IS NOT NULL AND p.expiry_date < ?";
            $params[] = $today;
            $types .= 's';
        } elseif ($expiry_status == 'expiring_soon') {
            $whereParts[] = "p.expiry_date IS NOT NULL AND p.expiry_date >= ? AND p.expiry_date <= ?";
            $params[] = $today;
            $params[] = $thirtyDaysLater;
            $types .= 'ss';
        } elseif ($expiry_status == 'valid') {
            $whereParts[] = "p.expiry_date IS NOT NULL AND p.expiry_date > ?";
            $params[] = $thirtyDaysLater;
            $types .= 's';
        } elseif ($expiry_status == 'no_expiry') {
            $whereParts[] = "(p.expiry_date IS NULL OR p.expiry_date = '')";
        }
    }

    // Price range filter
    if (!empty($price_range)) {
        if ($price_range == '0-50') {
            $whereParts[] = "p.price >= 0 AND p.price <= 50";
        } elseif ($price_range == '50-100') {
            $whereParts[] = "p.price > 50 AND p.price <= 100";
        } elseif ($price_range == '100-500') {
            $whereParts[] = "p.price > 100 AND p.price <= 500";
        } elseif ($price_range == '500-1000') {
            $whereParts[] = "p.price > 500 AND p.price <= 1000";
        } elseif ($price_range == '1000+') {
            $whereParts[] = "p.price > 1000";
        }
    }

    // Non-selling items filter
    $nonSellingJoin = '';
    if ($non_selling > 0) {
        $daysAgo = date('Y-m-d', strtotime("-$non_selling days"));
        $nonSellingJoin = "LEFT JOIN (
            SELECT oi.product_id, MAX(o.order_date) as last_sale_date
            FROM order_items oi
            JOIN orders o ON oi.order_id = o.id
            WHERE o.order_date >= '$daysAgo'
            GROUP BY oi.product_id
        ) recent_sales ON p.id = recent_sales.product_id";
        $whereParts[] = "recent_sales.product_id IS NULL";
    }

    $whereClause = '';
    if (!empty($whereParts)) {
        $whereClause = "WHERE " . implode(" AND ", $whereParts);
    }

    // Get total count
    $countQuery = "SELECT COUNT(*) as total 
                   FROM products p 
                   LEFT JOIN categories c ON p.category_id = c.id 
                   $nonSellingJoin
                   $whereClause";
    $countResult = executeQuery($countQuery, $types, $params);
    $totalProducts = (!empty($countResult) && isset($countResult[0])) ? $countResult[0]['total'] : 0;
    $totalPages = ceil($totalProducts / $limit);

    // Get products with pagination
    $query = "SELECT p.*, c.name as category_name 
              FROM products p 
              LEFT JOIN categories c ON p.category_id = c.id 
              $nonSellingJoin
              $whereClause
              ORDER BY p.name
              LIMIT $limit OFFSET $offset";

    $searchResults = executeQuery($query, $types, $params);

    // ensure $searchResults is iterable array
    if ($searchResults === false || $searchResults === null) {
        $searchResults = [];
    }

    // Return table body + pagination
    ob_start();
    
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
                echo '<br>';
                
                $expiryStatus = getExpiryStatus($prod['expiry_date']);
                if ($expiryStatus['status'] !== 'none') {
                    echo '<span class="badge ' . $expiryStatus['class'] . ' mt-1">';
                    echo '<i class="fas fa-calendar-alt"></i> ' . $expiryStatus['text'];
                    echo '</span><br>';
                }
                
                echo '<small class="text-muted">Active</small>';
            } else {
                echo '<span class="badge bg-secondary">Disabled</span>';
            }
            echo '</td>';
            echo '<td>';
            echo '<div class="btn-group btn-group-sm">';
            echo '<a href="?action=edit&id=' . $prod['id'] . '" class="btn btn-outline-primary">';
            echo '<i class="fas fa-edit"></i>';
            echo '</a>';

            if ($prod['is_active']) {
                echo '<form method="POST" class="d-inline">';
                echo '<input type="hidden" name="product_id" value="' . $prod['id'] . '">';
                echo '<button type="submit" name="toggle_product" value="disable" class="btn btn-outline-warning" onclick="return confirm(\'Disable this product?\')" title="Disable Product">';
                echo '<i class="fas fa-ban"></i>';
                echo '</button>';
                echo '</form>';
            } else {
                echo '<form method="POST" class="d-inline">';
                echo '<input type="hidden" name="product_id" value="' . $prod['id'] . '">';
                echo '<button type="submit" name="toggle_product" value="enable" class="btn btn-outline-success" onclick="return confirm(\'Enable this product?\')" title="Enable Product">';
                echo '<i class="fas fa-check"></i>';
                echo '</button>';
                echo '</form>';
                
                echo '<form method="POST" class="d-inline" onsubmit="return confirm(\'Permanently delete?\')">';
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
    
    $tableBody = ob_get_clean();
    
    // Build pagination HTML
    $paginationHtml = '';
if ($totalPages > 1) {
    $paginationHtml .= '<nav aria-label="Products pagination" class="mt-4">';
    $paginationHtml .= '<ul class="pagination justify-content-center">';
    
    if ($page > 1) {
        $paginationHtml .= '<li class="page-item">';
        $paginationHtml .= '<a class="page-link ajax-page-link" data-page="' . ($page - 1) . '" href="#">Previous</a>';
        $paginationHtml .= '</li>';
    }
    
    $start = max(1, $page - 2);
    $end = min($totalPages, $page + 2);
    
    for ($i = $start; $i <= $end; $i++) {
        $active = $i == $page ? 'active' : '';
        $paginationHtml .= '<li class="page-item ' . $active . '">';
        $paginationHtml .= '<a class="page-link ajax-page-link" data-page="' . $i . '" href="#">' . $i . '</a>';
        $paginationHtml .= '</li>';
    }
    
    if ($page < $totalPages) {
        $paginationHtml .= '<li class="page-item">';
        $paginationHtml .= '<a class="page-link ajax-page-link" data-page="' . ($page + 1) . '" href="#">Next</a>';
        $paginationHtml .= '</li>';
    }
    
    $paginationHtml .= '</ul>';
    $paginationHtml .= '</nav>';
    
    $paginationHtml .= '<div class="text-center mt-3">';
    $paginationHtml .= '<small class="text-muted">';
    $paginationHtml .= 'Showing page ' . $page . ' of ' . $totalPages . ' (' . $totalProducts . ' total products)';
    $paginationHtml .= '</small>';
    $paginationHtml .= '</div>';
}
    
    // Return JSON response
    echo json_encode([
        'tableBody' => $tableBody,
        'pagination' => $paginationHtml,
        'totalProducts' => $totalProducts,
        'currentPage' => $page,
        'totalPages' => $totalPages
    ]);
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
// Get all products for listing with advanced search functionality
// Get all products for listing with advanced search functionality
if ($action == 'list') {
    $search = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';
    $category_filter = isset($_GET['category']) ? (int)$_GET['category'] : 0;
    $show_disabled = isset($_GET['show_disabled']) && $_GET['show_disabled'] == '1' ? 1 : 0;
    $stock_status = isset($_GET['stock_status']) ? sanitizeInput($_GET['stock_status']) : '';
    $manufacturer = isset($_GET['manufacturer']) ? sanitizeInput($_GET['manufacturer']) : '';
    $expiry_status = isset($_GET['expiry_status']) ? sanitizeInput($_GET['expiry_status']) : '';
    $price_range = isset($_GET['price_range']) ? sanitizeInput($_GET['price_range']) : '';
    $non_selling = isset($_GET['non_selling']) ? (int)$_GET['non_selling'] : 0;

    

    // ===== PAGINATION SETTINGS =====
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $limit = 20;
    $offset = ($page - 1) * $limit;

    $whereParts = [];
    $params = [];
    $types = '';

    // Active/Disabled filter
    if ($show_disabled) {
        $whereParts[] = "p.is_active = 0";
    } else {
        $whereParts[] = "p.is_active = 1";
    }

    // Search filter
    if (!empty($search)) {
        $whereParts[] = "(p.name LIKE ? OR p.barcode LIKE ? OR p.description LIKE ? OR p.manufacturer LIKE ?)";
        $searchTerm = "%$search%";
        $params[] = $searchTerm; 
        $params[] = $searchTerm; 
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $types .= 'ssss';
    }

    // Category filter
    if ($category_filter > 0) {
        $whereParts[] = "p.category_id = ?";
        $params[] = $category_filter;
        $types .= 'i';
    }

    // Stock status filter
    if (!empty($stock_status)) {
        if ($stock_status == 'out_of_stock') {
            $whereParts[] = "p.stock_quantity <= 0";
        } elseif ($stock_status == 'low_stock') {
            $whereParts[] = "p.stock_quantity > 0 AND p.stock_quantity <= p.min_stock_level";
        } elseif ($stock_status == 'in_stock') {
            $whereParts[] = "p.stock_quantity > p.min_stock_level";
        }
    }

    // Manufacturer filter
    if (!empty($manufacturer)) {
        $whereParts[] = "p.manufacturer = ?";
        $params[] = $manufacturer;
        $types .= 's';
    }

    // Expiry status filter
    if (!empty($expiry_status)) {
        $today = date('Y-m-d');
        $thirtyDaysLater = date('Y-m-d', strtotime('+30 days'));
        
        if ($expiry_status == 'expired') {
            $whereParts[] = "p.expiry_date IS NOT NULL AND p.expiry_date < ?";
            $params[] = $today;
            $types .= 's';
        } elseif ($expiry_status == 'expiring_soon') {
            $whereParts[] = "p.expiry_date IS NOT NULL AND p.expiry_date >= ? AND p.expiry_date <= ?";
            $params[] = $today;
            $params[] = $thirtyDaysLater;
            $types .= 'ss';
        } elseif ($expiry_status == 'valid') {
            $whereParts[] = "p.expiry_date IS NOT NULL AND p.expiry_date > ?";
            $params[] = $thirtyDaysLater;
            $types .= 's';
        } elseif ($expiry_status == 'no_expiry') {
            $whereParts[] = "(p.expiry_date IS NULL OR p.expiry_date = '')";
        }
    }

    // Price range filter
    if (!empty($price_range)) {
        if ($price_range == '0-50') {
            $whereParts[] = "p.price >= 0 AND p.price <= 50";
        } elseif ($price_range == '50-100') {
            $whereParts[] = "p.price > 50 AND p.price <= 100";
        } elseif ($price_range == '100-500') {
            $whereParts[] = "p.price > 100 AND p.price <= 500";
        } elseif ($price_range == '500-1000') {
            $whereParts[] = "p.price > 500 AND p.price <= 1000";
        } elseif ($price_range == '1000+') {
            $whereParts[] = "p.price > 1000";
        }
    }

    // Non-selling items filter (requires subquery)
    $nonSellingJoin = '';
    if ($non_selling > 0) {
        $daysAgo = date('Y-m-d', strtotime("-$non_selling days"));
        $nonSellingJoin = "LEFT JOIN (
            SELECT oi.product_id, MAX(o.order_date) as last_sale_date
            FROM order_items oi
            JOIN orders o ON oi.order_id = o.id
            WHERE o.order_date >= '$daysAgo'
            GROUP BY oi.product_id
        ) recent_sales ON p.id = recent_sales.product_id";
        $whereParts[] = "recent_sales.product_id IS NULL";
    }

    $whereClause = '';
    if (!empty($whereParts)) {
        $whereClause = "WHERE " . implode(" AND ", $whereParts);
    }

    // ===== GET TOTAL COUNT FIRST =====
    $countQuery = "SELECT COUNT(*) as total 
                   FROM products p 
                   LEFT JOIN categories c ON p.category_id = c.id 
                   $nonSellingJoin
                   $whereClause";
    $countResult = executeQuery($countQuery, $types, $params);
    $totalProducts = (!empty($countResult) && isset($countResult[0])) ? $countResult[0]['total'] : 0;
    $totalPages = ceil($totalProducts / $limit);

    // ===== GET PAGINATED PRODUCTS =====
    $query = "SELECT p.*, c.name as category_name 
              FROM products p 
              LEFT JOIN categories c ON p.category_id = c.id 
              $nonSellingJoin
              $whereClause
              ORDER BY p.name
              LIMIT $limit OFFSET $offset";

    $products = executeQuery($query, $types, $params);

    // Normalize $products to array to avoid count() issues
    if ($products === false || $products === null) {
        $products = [];
    } elseif ($products instanceof mysqli_result) {
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

<?php if ($action == 'list'): ?>
    <!-- Products List View -->
     <style>
    /* Pagination Styles - Applied to both server and AJAX pagination */
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
    
    /* Filter styles */
    .filter-select {
        font-size: 0.9rem;
    }
    
    #filtersSection {
        transition: all 0.3s ease;
    }
    
    .form-label {
        font-weight: 500;
        font-size: 0.875rem;
        margin-bottom: 0.25rem;
    }
    
    .badge {
        font-size: 0.75rem;
        padding: 0.25rem 0.5rem;
    }
    
    #resultsCount strong {
        color: #667eea;
    }
    </style>
    <!-- Search and Filter Section -->
    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h6 class="card-title mb-0">
                <i class="fas fa-filter"></i> Advanced Filters
            </h6>
            <button type="button" class="btn btn-sm btn-outline-secondary" id="toggleFilters">
                <i class="fas fa-chevron-down"></i> Show/Hide Filters
            </button>
        </div>
        <div class="card-body" id="filtersSection">
            <form id="searchForm">
                <input type="hidden" name="action" value="list">
                
                <!-- Row 1: Basic Search -->
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Search Products</label>
                        <input 
                            type="text" 
                            name="search" 
                            id="searchInput"
                            class="form-control" 
                            placeholder="Product name, barcode, generic name, brand..."
                            value="<?php echo isset($_GET['search']) ? sanitizeInput($_GET['search']) : ''; ?>"
                            autocomplete="off"
                        >
                        <small class="text-muted">Search starts automatically as you type</small>
                    </div>
                    
                    <div class="col-md-3">
                        <label class="form-label">Category</label>
                        <select name="category" class="form-select filter-select" id="categoryFilter">
                            <option value="">All Categories</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo $cat['id']; ?>" 
                                        <?php echo (isset($_GET['category']) && $_GET['category'] == $cat['id']) ? 'selected' : ''; ?>>
                                    <?php echo sanitizeInput($cat['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Stock Status</label>
                        <select name="stock_status" class="form-select filter-select" id="stockStatusFilter">
                            <option value="">All Stock Levels</option>
                            <option value="in_stock" <?php echo (isset($_GET['stock_status']) && $_GET['stock_status'] == 'in_stock') ? 'selected' : ''; ?>>In Stock</option>
                            <option value="low_stock" <?php echo (isset($_GET['stock_status']) && $_GET['stock_status'] == 'low_stock') ? 'selected' : ''; ?>>Low Stock</option>
                            <option value="out_of_stock" <?php echo (isset($_GET['stock_status']) && $_GET['stock_status'] == 'out_of_stock') ? 'selected' : ''; ?>>Out of Stock</option>
                        </select>
                    </div>
                </div>

                <!-- Row 2: Advanced Filters -->
                <div class="row g-3 mb-3">
                    <div class="col-md-3">
                        <label class="form-label">Manufacturer</label>
                        <select name="manufacturer" class="form-select filter-select" id="manufacturerFilter">
                            <option value="">All Manufacturers</option>
                            <?php 
                            // Get unique manufacturers
                            $manufacturers = executeQuery("SELECT DISTINCT manufacturer FROM products WHERE manufacturer != '' AND manufacturer IS NOT NULL ORDER BY manufacturer");
                            if ($manufacturers):
                                foreach ($manufacturers as $mfr): 
                            ?>
                                <option value="<?php echo sanitizeInput($mfr['manufacturer']); ?>" 
                                        <?php echo (isset($_GET['manufacturer']) && $_GET['manufacturer'] == $mfr['manufacturer']) ? 'selected' : ''; ?>>
                                    <?php echo sanitizeInput($mfr['manufacturer']); ?>
                                </option>
                            <?php 
                                endforeach;
                            endif;
                            ?>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Expiry Status</label>
                        <select name="expiry_status" class="form-select filter-select" id="expiryStatusFilter">
                            <option value="">All Products</option>
                            <option value="expired" <?php echo (isset($_GET['expiry_status']) && $_GET['expiry_status'] == 'expired') ? 'selected' : ''; ?>>Expired</option>
                            <option value="expiring_soon" <?php echo (isset($_GET['expiry_status']) && $_GET['expiry_status'] == 'expiring_soon') ? 'selected' : ''; ?>>Expiring Soon (30 days)</option>
                            <option value="valid" <?php echo (isset($_GET['expiry_status']) && $_GET['expiry_status'] == 'valid') ? 'selected' : ''; ?>>Valid (>30 days)</option>
                            <option value="no_expiry" <?php echo (isset($_GET['expiry_status']) && $_GET['expiry_status'] == 'no_expiry') ? 'selected' : ''; ?>>No Expiry Date</option>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Price Range</label>
                        <select name="price_range" class="form-select filter-select" id="priceRangeFilter">
                            <option value="">All Prices</option>
                            <option value="0-50" <?php echo (isset($_GET['price_range']) && $_GET['price_range'] == '0-50') ? 'selected' : ''; ?>>Rs 0 - 50</option>
                            <option value="50-100" <?php echo (isset($_GET['price_range']) && $_GET['price_range'] == '50-100') ? 'selected' : ''; ?>>Rs 50 - 100</option>
                            <option value="100-500" <?php echo (isset($_GET['price_range']) && $_GET['price_range'] == '100-500') ? 'selected' : ''; ?>>Rs 100 - 500</option>
                            <option value="500-1000" <?php echo (isset($_GET['price_range']) && $_GET['price_range'] == '500-1000') ? 'selected' : ''; ?>>Rs 500 - 1000</option>
                            <option value="1000+" <?php echo (isset($_GET['price_range']) && $_GET['price_range'] == '1000+') ? 'selected' : ''; ?>>Rs 1000+</option>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Non-Selling Items</label>
                        <select name="non_selling" class="form-select filter-select" id="nonSellingFilter">
                            <option value="">All Products</option>
                            <option value="30" <?php echo (isset($_GET['non_selling']) && $_GET['non_selling'] == '30') ? 'selected' : ''; ?>>No sales (30 days)</option>
                            <option value="60" <?php echo (isset($_GET['non_selling']) && $_GET['non_selling'] == '60') ? 'selected' : ''; ?>>No sales (60 days)</option>
                            <option value="90" <?php echo (isset($_GET['non_selling']) && $_GET['non_selling'] == '90') ? 'selected' : ''; ?>>No sales (90 days)</option>
                        </select>
                    </div>
                </div>

                <!-- Row 3: Checkboxes and Actions -->
                <div class="row g-3">
                    <div class="col-md-3">
                        <div class="form-check mt-4">
                            <input class="form-check-input" type="checkbox" value="1" id="showDisabled" name="show_disabled"
                                <?php echo isset($_GET['show_disabled']) && $_GET['show_disabled'] == '1' ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="showDisabled">
                                Show Disabled Products
                            </label>
                        </div>
                    </div>

                    <div class="col-md-9 d-flex align-items-end justify-content-end gap-2">
                        <button type="button" class="btn btn-outline-secondary" id="clearFilters">
                            <i class="fas fa-times"></i> Clear Filters
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i> Apply Filters
                        </button>
                    </div>
                </div>
            </form>

            <!-- Results Counter -->
            <div class="mt-3 pt-3 border-top">
                <small class="text-muted" id="resultsCount">
                    <?php 
                    $activeFilters = [];
                    
                    if (isset($_GET['search']) && !empty($_GET['search'])) {
                        $activeFilters[] = "search: \"" . sanitizeInput($_GET['search']) . "\"";
                    }
                    if (isset($_GET['category']) && $_GET['category'] > 0) {
                        foreach ($categories as $cat) {
                            if ($cat['id'] == $_GET['category']) {
                                $activeFilters[] = "category: " . $cat['name'];
                                break;
                            }
                        }
                    }
                    if (isset($_GET['stock_status']) && !empty($_GET['stock_status'])) {
                        $activeFilters[] = "stock: " . str_replace('_', ' ', $_GET['stock_status']);
                    }
                    if (isset($_GET['manufacturer']) && !empty($_GET['manufacturer'])) {
                        $activeFilters[] = "manufacturer: " . sanitizeInput($_GET['manufacturer']);
                    }
                    if (isset($_GET['expiry_status']) && !empty($_GET['expiry_status'])) {
                        $activeFilters[] = "expiry: " . str_replace('_', ' ', $_GET['expiry_status']);
                    }
                    if (isset($_GET['price_range']) && !empty($_GET['price_range'])) {
                        $activeFilters[] = "price: Rs " . $_GET['price_range'];
                    }
                    if (isset($_GET['non_selling']) && !empty($_GET['non_selling'])) {
                        $activeFilters[] = "non-selling: " . $_GET['non_selling'] . " days";
                    }
                    if (isset($_GET['show_disabled']) && $_GET['show_disabled'] == '1') {
                        $activeFilters[] = "showing disabled";
                    }
                    
                    echo "Found <strong>$totalProducts</strong> products";
                    if (!empty($activeFilters)) {
                        echo " | Filters: " . implode(', ', $activeFilters);
                    }
                    ?>
                </small>
            </div>
        </div>
    </div>
         
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
                            <style>
        .filter-select {
            font-size: 0.9rem;
        }
        
        #filtersSection {
            transition: all 0.3s ease;
        }
        
        .form-label {
            font-weight: 500;
            font-size: 0.875rem;
            margin-bottom: 0.25rem;
        }
        
        .badge {
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
        }
        
        #resultsCount strong {
            color: #667eea;
        }
        
        .list-group-item {
            border-left: none;
            border-right: none;
        }
        
        .list-group-item:first-child {
            border-top: none;
        }
    </style>
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
                                            <br>
                                            
                                            <?php 
                                            $expiryStatus = getExpiryStatus($prod['expiry_date']);
                                            if ($expiryStatus['status'] !== 'none'): 
                                            ?>
                                                <span class="badge <?php echo $expiryStatus['class']; ?> mt-1">
                                                    <i class="fas fa-calendar-alt"></i> <?php echo $expiryStatus['text']; ?>
                                                </span>
                                                <br>
                                            <?php endif; ?>
                                            
                                            <small class="text-muted">Active</small>
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
            <div id="serverPagination">
                <?php if ($action == 'list' && $totalPages > 1): ?>
                    <nav aria-label="Products pagination" class="mt-4">
                        <ul class="pagination justify-content-center">
                            <?php if ($page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?action=list&page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo $category_filter; ?>&stock_status=<?php echo $stock_status; ?>&manufacturer=<?php echo urlencode($manufacturer); ?>&expiry_status=<?php echo $expiry_status; ?>&price_range=<?php echo $price_range; ?>&non_selling=<?php echo $non_selling; ?>&show_disabled=<?php echo $show_disabled; ?>">
                                        Previous
                                    </a>
                                </li>
                            <?php endif; ?>

                            <?php
                            $start = max(1, $page - 2);
                            $end = min($totalPages, $page + 2);

                            for ($i = $start; $i <= $end; $i++): ?>
                                <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?action=list&page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo $category_filter; ?>&stock_status=<?php echo $stock_status; ?>&manufacturer=<?php echo urlencode($manufacturer); ?>&expiry_status=<?php echo $expiry_status; ?>&price_range=<?php echo $price_range; ?>&non_selling=<?php echo $non_selling; ?>&show_disabled=<?php echo $show_disabled; ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                            <?php endfor; ?>

                            <?php if ($page < $totalPages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?action=list&page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo $category_filter; ?>&stock_status=<?php echo $stock_status; ?>&manufacturer=<?php echo urlencode($manufacturer); ?>&expiry_status=<?php echo $expiry_status; ?>&price_range=<?php echo $price_range; ?>&non_selling=<?php echo $non_selling; ?>&show_disabled=<?php echo $show_disabled; ?>">
                                        Next
                                    </a>
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
            
            <!-- AJAX Pagination Container (shown during live search) -->
            <div id="ajaxPagination" style="display: none;"></div>
        </div>
    </div>
<script>
// Live search with pagination
let searchTimeout;
let currentPage = 1;

function performSearch(page = 1) {
    const searchTerm = document.getElementById('searchInput').value;
    const categoryId = document.getElementById('categoryFilter').value;
    const showDisabled = document.getElementById('showDisabled').checked ? '1' : '0';
    const stockStatus = document.getElementById('stockStatusFilter').value;
    const manufacturer = document.getElementById('manufacturerFilter').value;
    const expiryStatus = document.getElementById('expiryStatusFilter').value;
    const priceRange = document.getElementById('priceRangeFilter').value;
    const nonSelling = document.getElementById('nonSellingFilter').value;

    clearTimeout(searchTimeout);
    currentPage = page;

    // Hide server pagination, show AJAX pagination
    const serverPagination = document.getElementById('serverPagination');
    const ajaxPagination = document.getElementById('ajaxPagination');
    if (serverPagination) serverPagination.style.display = 'none';
    if (ajaxPagination) ajaxPagination.style.display = 'block';

    searchTimeout = setTimeout(function() {
        // Show loading
        document.getElementById('productsTableBody').innerHTML = 
            '<tr><td colspan="11" class="text-center py-4"><i class="fas fa-spinner fa-spin fa-2x text-primary"></i><br><small class="text-muted mt-2">Loading products...</small></td></tr>';
        
        if (ajaxPagination) {
            ajaxPagination.innerHTML = '';
        }

        // Create form data
        const formData = new FormData();
        formData.append('ajax_search', '1');
        formData.append('search', searchTerm);
        formData.append('category', categoryId);
        formData.append('show_disabled', showDisabled);
        formData.append('stock_status', stockStatus);
        formData.append('manufacturer', manufacturer);
        formData.append('expiry_status', expiryStatus);
        formData.append('price_range', priceRange);
        formData.append('non_selling', nonSelling);
        formData.append('page', page);

        // Send request
        fetch(window.location.pathname, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            document.getElementById('productsTableBody').innerHTML = data.tableBody;
            
            // Update pagination
            if (ajaxPagination) {
                ajaxPagination.innerHTML = data.pagination;
                
                // Add click handlers to pagination links
                document.querySelectorAll('.ajax-page-link').forEach(link => {
                    link.addEventListener('click', function(e) {
                        e.preventDefault();
                        const page = parseInt(this.getAttribute('data-page'));
                        performSearch(page);
                        window.scrollTo({ top: 0, behavior: 'smooth' });
                    });
                });
            }

            // Update counter
            let activeFilters = [];
            if (searchTerm) activeFilters.push('search: "' + searchTerm + '"');
            if (categoryId) {
                const catSelect = document.getElementById('categoryFilter');
                activeFilters.push('category: ' + catSelect.options[catSelect.selectedIndex].text);
            }
            if (stockStatus) activeFilters.push('stock: ' + stockStatus.replace(/_/g, ' '));
            if (manufacturer) activeFilters.push('manufacturer: ' + manufacturer);
            if (expiryStatus) activeFilters.push('expiry: ' + expiryStatus.replace(/_/g, ' '));
            if (priceRange) activeFilters.push('price: Rs ' + priceRange);
            if (nonSelling) activeFilters.push('non-selling: ' + nonSelling + ' days');
            if (showDisabled === '1') activeFilters.push('showing disabled');
            
            let countText = 'Found <strong>' + data.totalProducts + '</strong> products';
            if (activeFilters.length > 0) {
                countText += ' | Filters: ' + activeFilters.join(', ');
            }
            
            document.getElementById('resultsCount').innerHTML = countText;
        })
        .catch(error => {
            console.error('Search error:', error);
            document.getElementById('productsTableBody').innerHTML = 
                '<tr><td colspan="11" class="text-center py-4 text-danger"><i class="fas fa-exclamation-triangle fa-2x"></i><br><strong>Error loading products</strong><br><small>Please try again</small></td></tr>';
        });
    }, 300);
}

// Check if filters are active on page load
function checkFiltersOnLoad() {
    const urlParams = new URLSearchParams(window.location.search);
    const hasFilters = urlParams.get('search') || 
                      urlParams.get('category') || 
                      urlParams.get('stock_status') || 
                      urlParams.get('manufacturer') || 
                      urlParams.get('expiry_status') || 
                      urlParams.get('price_range') || 
                      urlParams.get('non_selling') || 
                      urlParams.get('show_disabled');
    
    // If filters are active, trigger AJAX search immediately
    if (hasFilters) {
        const page = parseInt(urlParams.get('page')) || 1;
        performSearch(page);
    }
}

// Wait for DOM to be ready
document.addEventListener('DOMContentLoaded', function() {
    // Add event listeners for live search
    document.getElementById('searchInput').addEventListener('input', () => performSearch(1));
    document.getElementById('categoryFilter').addEventListener('change', () => performSearch(1));
    document.getElementById('stockStatusFilter').addEventListener('change', () => performSearch(1));
    document.getElementById('manufacturerFilter').addEventListener('change', () => performSearch(1));
    document.getElementById('expiryStatusFilter').addEventListener('change', () => performSearch(1));
    document.getElementById('priceRangeFilter').addEventListener('change', () => performSearch(1));
    document.getElementById('nonSellingFilter').addEventListener('change', () => performSearch(1));
    document.getElementById('showDisabled').addEventListener('change', () => performSearch(1));

    // Prevent form submission, use AJAX instead
    document.getElementById('searchForm').addEventListener('submit', function(e) {
        e.preventDefault();
        performSearch(1);
    });

    // Clear filters button
    document.getElementById('clearFilters').addEventListener('click', function() {
        window.location.href = '?action=list';
    });

    // Toggle filters button
    document.getElementById('toggleFilters').addEventListener('click', function() {
        const filtersSection = document.getElementById('filtersSection');
        const icon = this.querySelector('i');
        
        if (filtersSection.style.display === 'none') {
            filtersSection.style.display = 'block';
            icon.classList.remove('fa-chevron-down');
            icon.classList.add('fa-chevron-up');
        } else {
            filtersSection.style.display = 'none';
            icon.classList.remove('fa-chevron-up');
            icon.classList.add('fa-chevron-down');
        }
    });

    // Check if we need to load filters on page load
    checkFiltersOnLoad();
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

<?php elseif ($action == 'add' || $action == 'edit'): ?>
    <!-- Add/Edit Product Form View -->
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
                            <!-- Live Profit Display -->
<div class="row">
    <div class="col-12">
        <div class="alert alert-info" id="profitDisplay" style="display: none;">
            <h6 class="mb-2"><i class="fas fa-chart-line"></i> Profit Analysis</h6>
            <div class="row text-center">
                <div class="col-md-4">
                    <small class="text-muted">Profit per Unit</small>
                    <h5 class="mb-0" id="profitAmount">Rs 0.00</h5>
                </div>
                <div class="col-md-4">
                    <small class="text-muted">Profit Margin</small>
                    <h5 class="mb-0" id="profitMargin">0%</h5>
                </div>
                <div class="col-md-4">
                    <small class="text-muted">Potential Profit (Stock)</small>
                    <h5 class="mb-0" id="totalProfit">Rs 0.00</h5>
                </div>
            </div>
        </div>
    </div>
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
                    <h6>Pricing & Profit:</h6>
<ul class="small">
    <li>Cost: Your purchase price (Rs)</li>
    <li>Price: Customer selling price (Rs)</li>
    <li>Profit: Price - Cost (Rs)</li>
    <li>Margin %: (Profit / Price) × 100</li>
</ul>
<div class="alert alert-info alert-sm p-2">
    <small>
        <strong>Margin Guide:</strong><br>
        🟢 High: 30%+ profit margin<br>
        🟡 Medium: 15-30% profit margin<br>
        🔴 Low: Below 15% profit margin
    </small>
</div>
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

// Live profit calculation
function calculateProfit() {
    const price = parseFloat(document.getElementById('price').value) || 0;
    const cost = parseFloat(document.getElementById('cost').value) || 0;
    const stock = parseFloat(document.getElementById('stock_quantity').value) || 0;
    
    if (price > 0 && cost >= 0) {
        const profitPerUnit = price - cost;
        const profitMargin = (profitPerUnit / price) * 100;
        const totalPotentialProfit = profitPerUnit * stock;
        
        // Show the profit display
        document.getElementById('profitDisplay').style.display = 'block';
        
        // Update values
        document.getElementById('profitAmount').textContent = 'Rs ' + profitPerUnit.toFixed(2);
        document.getElementById('profitMargin').textContent = profitMargin.toFixed(1) + '%';
        document.getElementById('totalProfit').textContent = 'Rs ' + totalPotentialProfit.toFixed(2);
        
        // Color code based on margin
        const marginElement = document.getElementById('profitMargin');
        if (profitMargin >= 30) {
            marginElement.className = 'mb-0 text-success';
        } else if (profitMargin >= 15) {
            marginElement.className = 'mb-0 text-warning';
        } else if (profitMargin >= 0) {
            marginElement.className = 'mb-0 text-danger';
        } else {
            marginElement.className = 'mb-0 text-danger';
        }
        
        // Color code profit amount
        const amountElement = document.getElementById('profitAmount');
        if (profitPerUnit >= 0) {
            amountElement.className = 'mb-0 text-success';
        } else {
            amountElement.className = 'mb-0 text-danger';
        }
    } else {
        document.getElementById('profitDisplay').style.display = 'none';
    }
}

// Add event listeners for real-time calculation
document.addEventListener('DOMContentLoaded', function() {
    const priceInput = document.getElementById('price');
    const costInput = document.getElementById('cost');
    const stockInput = document.getElementById('stock_quantity');
    
    if (priceInput && costInput) {
        priceInput.addEventListener('input', calculateProfit);
        costInput.addEventListener('input', calculateProfit);
        stockInput.addEventListener('input', calculateProfit);
        
        // Calculate on page load if editing
        calculateProfit();
    }
});
    </script>
<?php endif; ?>

<?php require_once '../includes/footer.php'; ?>