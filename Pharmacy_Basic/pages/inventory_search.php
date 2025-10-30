<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

// Accept query and filters
$queryParam = isset($_GET['query']) ? sanitizeInput($_GET['query']) : '';
$manufacturer = isset($_GET['manufacturer']) ? sanitizeInput($_GET['manufacturer']) : '';
$expiry_status = isset($_GET['expiry_status']) ? sanitizeInput($_GET['expiry_status']) : '';
$price_range = isset($_GET['price_range']) ? sanitizeInput($_GET['price_range']) : '';
$non_selling = isset($_GET['non_selling']) ? (int)$_GET['non_selling'] : 0;
$show_disabled = isset($_GET['show_disabled']) && $_GET['show_disabled'] == '1' ? 1 : 0;

$whereParts = [];
$params = [];
$types = '';

// Active/disabled filter
if ($show_disabled) {
    $whereParts[] = "p.is_active = 0";
} else {
    $whereParts[] = "p.is_active = 1";
}

// Query search
if ($queryParam !== '') {
    $search = "%" . $queryParam . "%";
    $whereParts[] = "(p.name LIKE ? OR p.barcode LIKE ? OR c.name LIKE ?)";
    $params[] = $search; $params[] = $search; $params[] = $search;
    $types .= 'sss';
}

// Manufacturer
if (!empty($manufacturer)) {
    $whereParts[] = "p.manufacturer = ?";
    $params[] = $manufacturer;
    $types .= 's';
}

// Expiry status
if (!empty($expiry_status)) {
    $today = date('Y-m-d');
    $thirtyDaysLater = date('Y-m-d', strtotime('+30 days'));
    if ($expiry_status == 'expired') {
        $whereParts[] = "p.expiry_date IS NOT NULL AND p.expiry_date < ?";
        $params[] = $today; $types .= 's';
    } elseif ($expiry_status == 'expiring_soon') {
        $whereParts[] = "p.expiry_date IS NOT NULL AND p.expiry_date >= ? AND p.expiry_date <= ?";
        $params[] = $today; $params[] = $thirtyDaysLater; $types .= 'ss';
    } elseif ($expiry_status == 'valid') {
        $whereParts[] = "p.expiry_date IS NOT NULL AND p.expiry_date > ?";
        $params[] = $thirtyDaysLater; $types .= 's';
    } elseif ($expiry_status == 'no_expiry') {
        $whereParts[] = "(p.expiry_date IS NULL OR p.expiry_date = '')";
    }
}

// Price range
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

// Non-selling join
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
    $whereClause = 'WHERE ' . implode(' AND ', $whereParts);
}

$products = executeQuery("SELECT p.*, c.name as category_name 
    FROM products p 
    LEFT JOIN categories c ON p.category_id = c.id 
    $nonSellingJoin
    $whereClause
    ORDER BY p.name ASC", $types, $params);

if (empty($products)) {
    echo '<tr><td colspan="6" class="text-center py-4 text-muted">No products found</td></tr>';
    exit;
}

foreach ($products as $product) {
    $rowClass = ($product['stock_quantity'] <= 0)
        ? 'table-danger'
        : (($product['stock_quantity'] <= $product['min_stock_level']) ? 'table-warning' : '');
    ?>
    <tr class="<?php echo $rowClass; ?>">
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
            <button type="button" class="btn btn-outline-primary btn-sm update-stock-btn" 
                    data-product-id="<?php echo $product['id']; ?>"
                    data-product-name="<?php echo htmlspecialchars($product['name']); ?>"
                    data-current-stock="<?php echo $product['stock_quantity']; ?>">
                <i class="fas fa-edit"></i> Update Stock
            </button>
        </td>
    </tr>
<?php
}
?>