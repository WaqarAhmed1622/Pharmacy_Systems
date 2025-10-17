<?php
// Start output buffering to prevent headers already sent error
ob_start();

/**
 * Point of Sale (POS) Page with Live Product Search (AJAX)
 */

require_once '../includes/header.php';
require_once '../config/database.php'; // Ensure DB connection for search

// Initialize cart session
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

$error = '';
$success = '';

// Handle barcode scan/search
if (isset($_POST['scan_barcode'])) {
    $barcode = sanitizeInput($_POST['barcode']);
    
    if (!empty($barcode)) {
        $product = getProductByBarcode($barcode);
        
        if ($product) {
            if ($product['stock_quantity'] > 0) {
                // Add to cart or increase quantity
                if (isset($_SESSION['cart'][$product['id']])) {
                    if ($_SESSION['cart'][$product['id']]['quantity'] < $product['stock_quantity']) {
                        $_SESSION['cart'][$product['id']]['quantity']++;
                        $success = 'Product quantity updated in cart.';
                    } else {
                        $error = 'Not enough stock available.';
                    }
                } else {
                    $_SESSION['cart'][$product['id']] = [
                        'id' => $product['id'],
                        'name' => $product['name'],
                        'barcode' => $product['barcode'],
                        'price' => $product['price'],
                        'quantity' => 1,
                        'stock_available' => $product['stock_quantity'],
                        'expiry_date' => $product['expiry_date'],
                        'manufacturer' => $product['manufacturer']
                    ];
                    $success = 'Product added to cart.';
                }
            } else {
                $error = 'Product is out of stock.';
            }
        } else {
            $error = 'Product not found.';
        }
    }
}

// Handle quantity updates
if (isset($_POST['update_quantity'])) {
    $productId = (int)$_POST['product_id'];
    $quantity = (int)$_POST['quantity'];
    
    if (isset($_SESSION['cart'][$productId])) {
        if ($quantity > 0 && $quantity <= $_SESSION['cart'][$productId]['stock_available']) {
            $_SESSION['cart'][$productId]['quantity'] = $quantity;
            $success = 'Quantity updated.';
        } elseif ($quantity <= 0) {
            unset($_SESSION['cart'][$productId]);
            $success = 'Product removed from cart.';
        } else {
            $error = 'Not enough stock available.';
        }
    }
}

// Handle remove item
if (isset($_POST['remove_item'])) {
    $productId = (int)$_POST['product_id'];
    unset($_SESSION['cart'][$productId]);
    $success = 'Product removed from cart.';
}

// Handle clear cart
if (isset($_POST['clear_cart'])) {
    $_SESSION['cart'] = [];
    $success = 'Cart cleared.';
}

// Handle checkout
if (isset($_POST['checkout'])) {
    if (empty($_SESSION['cart'])) {
        $error = 'Cart is empty.';
    } else {
        $subtotal = 0;
        foreach ($_SESSION['cart'] as $item) {
            $subtotal += $item['price'] * $item['quantity'];
        }
        $taxRate = 0.10; 
        $taxAmount = calculateTax($subtotal, $taxRate);
        $total = $subtotal + $taxAmount;
        
        $orderNumber = generateOrderNumber();
        $paymentMethod = sanitizeInput($_POST['payment_method']);
        
        $conn = getConnection();
        $conn->autocommit(false);
        
        try {
            $orderQuery = "INSERT INTO orders (order_number, cashier_id, subtotal, tax_amount, total_amount, payment_method) 
                          VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($orderQuery);
            $stmt->bind_param('siddds', $orderNumber, $_SESSION['user_id'], $subtotal, $taxAmount, $total, $paymentMethod);
            $stmt->execute();
            $orderId = $conn->insert_id;
            
            foreach ($_SESSION['cart'] as $item) {
                $itemQuery = "INSERT INTO order_items (order_id, product_id, quantity, unit_price, total_price) 
                             VALUES (?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($itemQuery);
                $itemTotal = $item['price'] * $item['quantity'];
                $stmt->bind_param('iiidd', $orderId, $item['id'], $item['quantity'], $item['price'], $itemTotal);
                $stmt->execute();
                
                $updateStockQuery = "UPDATE products SET stock_quantity = stock_quantity - ? WHERE id = ?";
                $stmt = $conn->prepare($updateStockQuery);
                $stmt->bind_param('ii', $item['quantity'], $item['id']);
                $stmt->execute();
            }
            
            $conn->commit();
            
            $_SESSION['cart'] = [];
            $_SESSION['last_order_id'] = $orderId;
            
            ob_end_clean();
            header('Location: receipt.php?order_id=' . $orderId);
            exit;
            
        } catch (Exception $e) {
            $conn->rollback();
            $error = 'Failed to process order. Please try again.';
        }
        
        $conn->close();
    }
}

// Calculate cart totals
$subtotal = 0;
$totalItems = 0;
foreach ($_SESSION['cart'] as $item) {
    $subtotal += $item['price'] * $item['quantity'];
    $totalItems += $item['quantity'];
}
$taxAmount = calculateTax($subtotal);
$total = $subtotal + $taxAmount;
?>

<div class="row">
    <!-- Barcode Scanner / Search -->
    <div class="col-md-6 mb-4">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-barcode"></i> Scan or Search Product
                </h5>
            </div>
            <div class="card-body">
               <!-- Barcode Form -->
                <form method="POST" class="mb-3" id="barcodeForm">
    <div class="input-group">
        <input type="text" name="barcode" class="form-control barcode-input" placeholder="Scan barcode or enter product name..." autocomplete="off" autofocus>
        <input type="hidden" name="scan_barcode" value="1"> <!-- Add this line -->
    </div>
    <small class="text-muted">Scan barcode or type product name and press Enter</small>
</form>
                <!-- Live AJAX Search -->
                <div class="mb-3">
                    <input type="text" id="liveSearch" class="form-control" placeholder="Search product by name or barcode...">
                    <div id="searchResults" class="mt-3"></div>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-danger mt-2">
                        <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="alert alert-success mt-2">
                        <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Cart Summary -->
    <div class="col-md-6 mb-4">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0"><i class="fas fa-shopping-cart"></i> Cart Summary</h5>
                <span class="badge bg-primary"><?php echo $totalItems; ?> items</span>
            </div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-4">
                        <h6>Subtotal</h6>
                        <strong><?php echo formatCurrency($subtotal); ?></strong>
                    </div>
                    <div class="col-4">
                        <h6>Tax (10%)</h6>
                        <strong><?php echo formatCurrency($taxAmount); ?></strong>
                    </div>
                    <div class="col-4">
                        <h6>Total</h6>
                        <strong class="text-primary"><?php echo formatCurrency($total); ?></strong>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Cart Items -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">
                    <i class="fas fa-list"></i> Cart Items
                </h5>
                <?php if (!empty($_SESSION['cart'])): ?>
                    <form method="POST" style="display: inline;">
                        <button type="submit" name="clear_cart" class="btn btn-outline-danger btn-sm" 
                                onclick="return confirm('Clear all items from cart?')">
                            <i class="fas fa-trash"></i> Clear Cart
                        </button>
                    </form>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <?php if (empty($_SESSION['cart'])): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-shopping-cart fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">Cart is empty</h5>
                        <p class="text-muted">Scan or search for products to add them to cart</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th>Barcode</th>
                                    <th>Expiry Date</th>
                                    <th>Manufacturer</th>
                                    <th>Price</th>
                                    <th>Quantity</th>
                                    <th>Total</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($_SESSION['cart'] as $item): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo sanitizeInput($item['name']); ?></strong>
                                            <br>
                                            <small class="text-muted">Stock: <?php echo $item['stock_available']; ?></small>
                                        </td>
                                        <td>
                                            <code><?php echo sanitizeInput($item['barcode']); ?></code>
                                        </td>
                                        <td>
                                            <?php echo !empty($item['expiry_date']) ? date('Y-m-d', strtotime($item['expiry_date'])) : '<span class="text-muted">N/A</span>'; ?>
                                        </td>
                                        <td>
                                            <?php echo !empty($item['manufacturer']) ? sanitizeInput($item['manufacturer']) : '<span class="text-muted">N/A</span>'; ?>
                                        </td>
                                        <td><?php echo formatCurrency($item['price']); ?></td>
                                        <td>
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="product_id" value="<?php echo $item['id']; ?>">
                                                <input 
                                                    type="number" 
                                                    name="quantity" 
                                                    value="<?php echo $item['quantity']; ?>"
                                                    min="0" 
                                                    max="<?php echo $item['stock_available']; ?>"
                                                    class="form-control form-control-sm d-inline-block"
                                                    style="width: 80px;"
                                                    onchange="this.form.submit()"
                                                >
                                                <input type="hidden" name="update_quantity" value="1">
                                            </form>
                                        </td>
                                        <td>
                                            <strong><?php echo formatCurrency($item['price'] * $item['quantity']); ?></strong>
                                        </td>
                                        <td>
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="product_id" value="<?php echo $item['id']; ?>">
                                                <button type="submit" name="remove_item" class="btn btn-outline-danger btn-sm" 
                                                        onclick="return confirm('Remove this item?')">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr class="table-light">
                                    <td colspan="4"><strong>Subtotal:</strong></td>
                                    <td><strong><?php echo formatCurrency($subtotal); ?></strong></td>
                                    <td></td>
                                </tr>
                                <tr class="table-light">
                                    <td colspan="4"><strong>Tax (10%):</strong></td>
                                    <td><strong><?php echo formatCurrency($taxAmount); ?></strong></td>
                                    <td></td>
                                </tr>
                                <tr class="table-success">
                                    <td colspan="4"><strong>Total:</strong></td>
                                    <td><strong><?php echo formatCurrency($total); ?></strong></td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                    
                    <!-- Checkout Section -->
                    <div class="row mt-4">
                        <div class="col-md-6 offset-md-6">
                            <div class="card">
                                <div class="card-header">
                                    <h6 class="card-title mb-0">
                                        <i class="fas fa-credit-card"></i> Checkout
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <form method="POST">
                                        <div class="mb-3">
                                            <label class="form-label">Payment Method</label>
                                            <select name="payment_method" class="form-select" required>
                                                <option value="cash">Cash</option>
                                                <option value="card">Card</option>
                                                <option value="other">Other</option>
                                            </select>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <div class="row text-center">
                                                <div class="col-12">
                                                    <h4 class="text-primary">Total: <?php echo formatCurrency($total); ?></h4>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="d-grid">
                                            <button type="submit" name="checkout" class="btn btn-success btn-lg">
                                                <i class="fas fa-check"></i> Complete Sale
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>


<script>
// Live Search (AJAX with debounce)
let debounceTimer;

document.getElementById('liveSearch').addEventListener('keyup', function() {
    clearTimeout(debounceTimer); // Clear any previous timer
    const query = this.value.trim();

    debounceTimer = setTimeout(function() {
        if (query.length === 0) {
            document.getElementById('searchResults').innerHTML = '';
            return;
        }

        const xhr = new XMLHttpRequest();
        xhr.open('GET', '../includes/search_product.php?query=' + encodeURIComponent(query), true);
        xhr.onload = function() {
            if (this.status === 200) {
                document.getElementById('searchResults').innerHTML = this.responseText;
            }
        };
        xhr.send();
    }, 300); // Wait 300ms after typing stops
});

// Auto-submit barcode form on Enter (for barcode scanners)
document.querySelector('.barcode-input').addEventListener('keydown', function(e) {
    if (e.key === 'Enter') {
        e.preventDefault();
        if (this.value.trim().length > 0) {
            this.form.submit();
        }
    }
});

// Optionally, auto-submit if input length reaches typical barcode length (e.g., 8-13 digits)
document.querySelector('.barcode-input').addEventListener('input', function(e) {
    const val = this.value.trim();
    if (/^\d{8,13}$/.test(val)) { // Typical barcode length
        this.form.submit();
    }
});

// Auto-focus barcode input after form submissions
$(document).ready(function() {
    $('.barcode-input').focus();
    $('form').on('submit', function(e) {
        if ($(this).find('input[name="scan_barcode"]').length) {
            setTimeout(function() {
                $('.barcode-input').val('').focus();
            }, 100);
        }
    });
});

// Keyboard shortcuts 
$(document).on('keydown', function(e) {
    // F1 - Focus barcode input
    if (e.which === 112) {
        e.preventDefault();
        $('.barcode-input').focus();
    }
    
    // F2 - Clear cart
    if (e.which === 113 && <?php echo !empty($_SESSION['cart']) ? 'true' : 'false'; ?>) {
        e.preventDefault();
        if (confirm('Clear all items from cart?')) {
            $('button[name="clear_cart"]').click();
        }
    }
    
    // F3 - Checkout
    if (e.which === 114 && <?php echo !empty($_SESSION['cart']) ? 'true' : 'false'; ?>) {
        e.preventDefault();
        $('button[name="checkout"]').click();
    }
});

</script>

<?php 
require_once '../includes/footer.php'; 
ob_end_flush();
?>
