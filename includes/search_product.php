<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

$conn = getConnection();

$query = isset($_GET['query']) ? trim($_GET['query']) : '';

if ($query !== '') {
    $term = mysqli_real_escape_string($conn, $query);
    $sql = "SELECT * FROM products 
            WHERE is_active = 1
            AND (name LIKE '%$term%' OR barcode LIKE '%$term%') 
            ORDER BY name ASC LIMIT 20";
} else {
    $sql = "SELECT * FROM products 
            WHERE is_active = 1
            ORDER BY id DESC LIMIT 10";
}

$result = mysqli_query($conn, $sql);

if (mysqli_num_rows($result) > 0) {
    echo '<div class="table-responsive">';
    echo '<table class="table table-sm table-bordered">';
    echo '<thead class="table-light">
            <tr>
                <th>Name</th>
                <th>Barcode</th>
                <th>Price</th>
                <th>Stock</th>
                <th>Action</th>
            </tr>
          </thead><tbody>';
    while ($row = mysqli_fetch_assoc($result)) {
        $inStock = $row['stock_quantity'] > 0;
        $stockClass = $inStock ? 'table-success' : 'table-danger';
        $disabledAttr = !$inStock ? 'disabled' : '';
        
        echo "<tr class='" . $stockClass . "'>
                <td>" . htmlspecialchars($row['name']) . "</td>
                <td><code>" . htmlspecialchars($row['barcode']) . "</code></td>
                <td>" . formatCurrency($row['price']) . "</td>
                <td>" . $row['stock_quantity'] . "</td>
                <td>
                    <form method='POST'>
                        <input type='hidden' name='barcode' value='" . htmlspecialchars($row['barcode']) . "'>
                        <button type='submit' name='scan_barcode' class='btn btn-sm btn-success' " . $disabledAttr . ">
                            <i class='fas fa-cart-plus'></i> Add
                        </button>
                    </form>
                </td>
              </tr>";
    }
    echo '</tbody></table></div>';
} else {
    echo '<p class="text-muted">No active products found.</p>';
}

mysqli_close($conn);
?>