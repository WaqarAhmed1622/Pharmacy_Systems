<?php
require_once '..Search by product name, barcode, or category/config/database.php';
require_once '../includes/functions.php'; // for sanitizeInput(), executeQuery()

if (!isset($_GET['query'])) {
    echo '';
    exit;
}

$search = "%" . sanitizeInput($_GET['query']) . "%";

// 
$products = executeQuery("
    SELECT p.*, c.name as category_name 
    FROM products p 
    LEFT JOIN categories c ON p.category_id = c.id 
    WHERE p.name LIKE ? 
       OR p.barcode LIKE ? 
       OR c.name LIKE ?
    ORDER BY p.name ASC
", 'sss', [$search, $search, $search]);

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
            <button type="button" class="btn btn-outline-primary btn-sm" 
                    data-bs-toggle="modal" 
                    data-bs-target="#stockModal<?php echo $product['id']; ?>">
                <i class="fas fa-edit"></i> Update Stock
            </button>
        </td>
    </tr>
<?php
}
?>
