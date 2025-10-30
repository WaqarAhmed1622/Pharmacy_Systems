<?php
// Start output buffering to prevent headers already sent error
ob_start();

/**
 * Point of Sale (POS) Page with Live Product Search (AJAX), Discount
 * and dynamic editable price per item in cart.
 */

require_once '../includes/header.php';
require_once '../config/database.php'; // Ensure DB connection for search

// Initialize cart and order settings
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}


$error = '';
$success = '';

// ----------------------
// Handle price updates dynamically
// ----------------------
if (isset($_POST['update_price'])) {
    $productId = (int)$_POST['product_id'];
    $newPrice = (float)$_POST['price'];

    if (isset($_SESSION['cart'][$productId])) {
        if ($newPrice >= 0) {
            $_SESSION['cart'][$productId]['price'] = $newPrice;
            $success = 'Price updated successfully.';
        } else {
            $error = 'Invalid price entered.';
        }
    }
}

// ----------------------
// Handle item discount updates
// ----------------------
if (isset($_POST['update_item_discount'])) {
    $productId = (int)$_POST['product_id'];
    $itemDiscount = (float)$_POST['item_discount'];

    if (isset($_SESSION['cart'][$productId])) {
        if ($itemDiscount >= 0 && $itemDiscount <= 100) {
            $_SESSION['cart'][$productId]['item_discount'] = $itemDiscount;
            $success = 'Item discount updated successfully.';
        } else {
            $error = 'Invalid discount. Must be between 0 and 100%.';
        }
    }
}

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
                        'manufacturer' => $product['manufacturer'],
                        'item_discount' => 0  // Item discount can be implemented later

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

if (isset($_POST['checkout'])) {
    if (empty($_SESSION['cart'])) {
        $error = 'Cart is empty.';
    } else {
        // Calculate subtotal
        $subtotal = 0;
        foreach ($_SESSION['cart'] as $item) {
            $subtotal += $item['price'] * $item['quantity'];
        }

        // Retrieve inputs safely
        $itemDiscount    = isset($_POST['item_discount']) ? (float)$_POST['item_discount'] : 0;
        $discountAmount  = calculateDiscount($subtotal); // cart-level discount
        $taxAmount       = 0; // will calculate later
        $paymentMethod   = sanitizeInput($_POST['payment_method']);
        $orderNumber     = generateOrderNumber();

        // Apply discount and tax correctly
        $afterDiscount = $subtotal - $itemDiscount - $discountAmount;
        if ($afterDiscount < 0) $afterDiscount = 0; // guard

        $taxAmount = calculateTax($afterDiscount);

        // ✅ FINAL TOTAL CALCULATION
        $total = $afterDiscount + $taxAmount ;

        // Database operations
        $conn = getConnection();
        $conn->autocommit(false);

        try {
            // Insert order record
            $orderQuery = "INSERT INTO orders 
                (order_number, cashier_id, subtotal, discount_amount, item_discount, tax_amount, total_amount, payment_method)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

            $stmt = $conn->prepare($orderQuery);
            if ($stmt === false) {
                throw new Exception('Prepare failed (orders): ' . $conn->error);
            }

            // Types: s (order_number), i (cashier_id), d (subtotal), d (discount_amount),
            // d (item_discount), d (tax_amount), d (total_amount), s (payment_method)
            $stmt->bind_param(
                'siddddds',
                $orderNumber,
                $_SESSION['user_id'],
                $subtotal,
                $discountAmount,
                $itemDiscount,
                $taxAmount,
                $total,
                $paymentMethod
            );

            if (!$stmt->execute()) {
                throw new Exception('Execute failed (orders): ' . $stmt->error);
            }

            $orderId = $conn->insert_id;
            $stmt->close();

            // Insert order items
            foreach ($_SESSION['cart'] as $item) {
            // Get item discount percentage
            $itemDiscountPercent = isset($item['item_discount']) ? $item['item_discount'] : 0;
            
            $itemQuery = "INSERT INTO order_items (order_id, product_id, quantity, unit_price, total_price, item_discount) 
                        VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($itemQuery);
            if ($stmt === false) {
                throw new Exception('Prepare failed (order_items): ' . $conn->error);
            }

            $itemTotal = $item['price'] * $item['quantity'];
            $stmt->bind_param('iiiddd', $orderId, $item['id'], $item['quantity'], $item['price'], $itemTotal, $itemDiscountPercent);

            if (!$stmt->execute()) {
                throw new Exception('Execute failed (order_items): ' . $stmt->error);
            }
            $stmt->close();

            // Update product stock
            $updateStockQuery = "UPDATE products SET stock_quantity = stock_quantity - ? WHERE id = ?";
            $stmt = $conn->prepare($updateStockQuery);
            if ($stmt === false) {
                throw new Exception('Prepare failed (update stock): ' . $conn->error);
            }
            $stmt->bind_param('ii', $item['quantity'], $item['id']);
            if (!$stmt->execute()) {
                throw new Exception('Execute failed (update stock): ' . $stmt->error);
            }
            $stmt->close();
        }

            // Commit all
            $conn->commit();

            // Clear session and redirect
            $_SESSION['cart'] = [];
            $_SESSION['last_order_id'] = $orderId;

            ob_end_clean();
            header('Location: receipt.php?order_id=' . $orderId);
            exit;

        } catch (Exception $e) {
            $conn->rollback();
            $error = 'Failed to process order. Please try again. (' . htmlspecialchars($e->getMessage()) . ')';
        }

        $conn->close();
    }
}


// Calculate cart totals
$subtotal = 0;
$totalItems = 0;
$totalItemDiscounts = 0;
foreach ($_SESSION['cart'] as $item) {
    $lineTotal = $item['price'] * $item['quantity'];
    $itemDiscountPercent = isset($item['item_discount']) ? $item['item_discount'] : 0;
    $itemDiscountAmount = $lineTotal * ($itemDiscountPercent / 100);
    $totalItemDiscounts += $itemDiscountAmount;
    $subtotal += ($lineTotal - $itemDiscountAmount);
    $totalItems += $item['quantity'];
}
$discountAmount = calculateDiscount($subtotal);
$afterDiscount = $subtotal - $discountAmount;
$taxAmount = calculateTax($afterDiscount);
$total = $afterDiscount + $taxAmount;

$discountRate = getSetting('discount_rate', 0) * 100;
$taxRate = getSetting('tax_rate', 0.10) * 100;
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
                        <input type="hidden" name="scan_barcode" value="1">
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
                <div class="row text-center mb-2">
                    <div class="col-6">
                        <h6 class="text-muted mb-1">Subtotal (After Item Discounts)</h6>
                        <strong id="display-subtotal"><?php echo formatCurrency($subtotal); ?></strong>
                    </div>
                    <div class="col-6">
                        <h6 class="text-muted mb-1">Cart Discount (<?php echo number_format($discountRate, 1); ?>%)</h6>
                        <strong class="text-danger" id="display-discount">-<?php echo formatCurrency($discountAmount); ?></strong>
                    </div>
                    </div>
                    <?php if ($totalItemDiscounts > 0): ?>
                    <div class="row text-center mb-2">
                        <div class="col-12">
                            <small class="text-muted">Item Discounts Applied: <span class="text-success">-<?php echo formatCurrency($totalItemDiscounts); ?></span></small>
                        </div>
                </div>
                    <?php endif; ?>
                <hr>
                <div class="row text-center mb-2">
                    <div class="col-6">
                        <h6 class="text-muted mb-1">After Discount</h6>
                        <strong id="display-after-discount"><?php echo formatCurrency($afterDiscount); ?></strong>
                    </div>
                    <div class="col-6">
                        <h6 class="text-muted mb-1">Tax (<?php echo number_format($taxRate, 1); ?>%)</h6>
                        <strong id="display-tax"><?php echo formatCurrency($taxAmount); ?></strong>
                    </div>
                </div>
                <hr>
                <div class="text-center">
                    <h5 class="mb-1">Grand Total</h5>
                    <h3 class="text-primary mb-0" id="display-total"><?php echo formatCurrency($total); ?></h3>
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
                        <table class="table" id="cart-table">
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th>Barcode</th>
                                    <th>Expiry Date</th>
                                    <th>Manufacturer</th>
                                    <th>Price</th>
                                    <th>Discount %</th>
                                    <th>Quantity</th>
                                   <th>Total</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($_SESSION['cart'] as $item): ?>
                                    <tr data-product-id="<?php echo $item['id']; ?>">
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
                                        <!-- Editable Price -->
                                        <td>
                                            <form method="POST" class="d-inline price-form">
                                                <input type="hidden" name="product_id" value="<?php echo $item['id']; ?>">
                                                <input 
                                                    type="number" 
                                                    name="price" 
                                                    value="<?php echo $item['price']; ?>" 
                                                    min="0" 
                                                    step="0.01" 
                                                    class="form-control form-control-sm price-input"
                                                    style="width: 110px;"
                                                    onchange="this.form.submit()"
                                                >
                                                <input type="hidden" name="update_price" value="1">
                                            </form>
                                        </td>
                                        <!-- Item Discount -->
                                        <td>
                                            <form method="POST" class="d-inline discount-form">
                                                <input type="hidden" name="product_id" value="<?php echo $item['id']; ?>">
                                                <div class="input-group input-group-sm" style="width: 120px;">
                                                    <input 
                                                        type="number" 
                                                        name="item_discount" 
                                                        value="<?php echo isset($item['item_discount']) ? $item['item_discount'] : 0; ?>" 
                                                        min="0" 
                                                        max="100"
                                                        step="0.01" 
                                                        class="form-control form-control-sm item-discount-input"
                                                        onchange="this.form.submit()"
                                                    >
                                                    <span class="input-group-text">%</span>
                                                </div>
                                                <input type="hidden" name="update_item_discount" value="1">
                                            </form>
                                        </td>
                                        <td>
                                            <form method="POST" class="d-inline qty-form">
                                                <input type="hidden" name="product_id" value="<?php echo $item['id']; ?>">
                                                <input 
                                                    type="number" 
                                                    name="quantity" 
                                                    value="<?php echo $item['quantity']; ?>"
                                                    min="0" 
                                                    max="<?php echo $item['stock_available']; ?>"
                                                    class="form-control form-control-sm quantity-input"
                                                    style="width: 80px;"
                                                    onchange="this.form.submit()"
                                                >
                                                <input type="hidden" name="update_quantity" value="1">
                                            </form>
                                        </td>
                                        <td>
                                            <?php 
                                            $lineTotal = $item['price'] * $item['quantity'];
                                            $itemDiscountPercent = isset($item['item_discount']) ? $item['item_discount'] : 0;
                                            $itemDiscountAmount = $lineTotal * ($itemDiscountPercent / 100);
                                            $lineTotalAfterDiscount = $lineTotal - $itemDiscountAmount;
                                            ?>
                                            <?php if ($itemDiscountPercent > 0): ?>
                                                <small class="text-muted text-decoration-line-through d-block"><?php echo number_format($lineTotal, 2); ?></small>
                                                <strong class="line-total text-success"><?php echo number_format($lineTotalAfterDiscount, 2); ?></strong>
                                                <br><small class="text-muted">-<?php echo number_format($itemDiscountAmount, 2); ?></small>
                                            <?php else: ?>
                                                <strong class="line-total"><?php echo number_format($lineTotal, 2); ?></strong>
                                            <?php endif; ?>
                                        </td>
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
                                <?php if ($totalItemDiscounts > 0): ?>
                                <tr class="table-success">
                                    <td colspan="7"><strong>Item Discounts Applied:</strong></td>
                                    <td colspan="2"><strong class="text-success" id="tfoot-item-discounts">-<?php echo formatCurrency($totalItemDiscounts); ?></strong></td>
                                </tr>
                                <?php endif; ?>
                                <tr class="table-light">
                                    <td colspan="7"><strong>Subtotal (After Item Discounts):</strong></td>
                                    <td colspan="2"><strong id="tfoot-subtotal"><?php echo formatCurrency($subtotal); ?></strong></td>
                                </tr>
                                <tr class="table-warning">
                                    <td colspan="7"><strong>Discount (<?php echo number_format($discountRate, 1); ?>%):</strong></td>
                                    <td colspan="2"><strong class="text-danger" id="tfoot-discount">-<?php echo formatCurrency($discountAmount); ?></strong></td>
                                </tr>
                                <tr class="table-info">
                                    <td colspan="7"><strong>After Discount:</strong></td>
                                    <td colspan="2"><strong id="tfoot-after-discount"><?php echo formatCurrency($afterDiscount); ?></strong></td>
                                </tr>
                                <tr class="table-light">
                                    <td colspan="7"><strong>Tax (<?php echo number_format($taxRate, 1); ?>%):</strong></td>
                                    <td colspan="2"><strong id="tfoot-tax"><?php echo formatCurrency($taxAmount); ?></strong></td>
                                </tr>
                                <tr class="table-success">
                                    <td colspan="7"><strong>Grand Total:</strong></td>
                                    <td colspan="2"><strong class="text-success" id="tfoot-total"><?php echo formatCurrency($total); ?></strong></td>
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
                                    <form method="POST" id="checkout-form">
                                        <div class="mb-3">
                                            <label class="form-label">Payment Method</label>
                                            <select name="payment_method" class="form-select" required>
                                                <option value="cash">Cash</option>
                                                <option value="card">Card</option>
                                                <option value="other">Other</option>
                                            </select>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <div class="alert alert-info mb-2">
                                                <small>
                                                    <strong>Breakdown:</strong><br>
                                                    Subtotal: <span id="break-subtotal"><?php echo formatCurrency($subtotal); ?></span><br>
                                                    Discount: -<span id="break-discount"><?php echo formatCurrency($discountAmount); ?></span><br>
                                                    Tax: +<span id="break-tax"><?php echo formatCurrency($taxAmount); ?></span>
                                                </small>
                                            </div>
                                            <div class="text-center">
                                                <h4 class="text-primary mb-0">Total: <span id="break-total"><?php echo formatCurrency($total); ?></span></h4>
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
    clearTimeout(debounceTimer);
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
    }, 300);
});

// Auto-submit barcode form on Enter
document.querySelector('.barcode-input').addEventListener('keydown', function(e) {
    if (e.key === 'Enter') {
        e.preventDefault();
        if (this.value.trim().length > 0) {
            this.form.submit();
        }
    }
});

// Auto-submit if input length reaches typical barcode length
document.querySelector('.barcode-input').addEventListener('input', function(e) {
    const val = this.value.trim();
    if (/^\d{8,13}$/.test(val)) {
        this.form.submit();
    }
});

// Auto-focus barcode input
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
    if (e.which === 112) {
        e.preventDefault();
        $('.barcode-input').focus();
    }
    
    if (e.which === 113 && <?php echo !empty($_SESSION['cart']) ? 'true' : 'false'; ?>) {
        e.preventDefault();
        if (confirm('Clear all items from cart?')) {
            $('button[name="clear_cart"]').click();
        }
    }
    
    if (e.which === 114 && <?php echo !empty($_SESSION['cart']) ? 'true' : 'false'; ?>) {
        e.preventDefault();
        $('button[name="checkout"]').click();
    }
});

// ----------------------------
// Live totals recalculation (client-side)
// ----------------------------
function recalcTotalsClientSide() {
    // Sum line totals
    let subtotal = 0;
    let totalItemDiscounts = 0;
    
    document.querySelectorAll('#cart-table tbody tr').forEach(row => {
        const priceEl = row.querySelector('.price-input');
        const qtyEl = row.querySelector('.quantity-input');
        const discountEl = row.querySelector('.item-discount-input');
        
        const price = priceEl ? parseFloat(priceEl.value) : 0;
        const qty = qtyEl ? parseFloat(qtyEl.value) : 0;
        const itemDiscountPercent = discountEl ? parseFloat(discountEl.value) : 0;
        
        const lineTotal = price * qty;
        const itemDiscountAmount = lineTotal * (itemDiscountPercent / 100);
        const lineTotalAfterDiscount = lineTotal - itemDiscountAmount;
        
        const lineTotalEl = row.querySelector('.line-total');
        if (lineTotalEl) lineTotalEl.textContent = lineTotalAfterDiscount.toFixed(2);
        
        subtotal += lineTotalAfterDiscount;
        totalItemDiscounts += itemDiscountAmount;
    });

    // Calculate discount and tax using server-side rates exposed in JS via data attributes or embedded values
    // We'll use the same calculation functions logic as PHP: discountRate and taxRate values are embedded
    const discountRate = <?php echo json_encode(getSetting('discount_rate', 0)); ?>; // decimal like 0.05
    const taxRate = <?php echo json_encode(getSetting('tax_rate', 0.10)); ?>; // decimal like 0.10

    const discountAmount = subtotal * discountRate;
    const afterDiscount = subtotal - discountAmount;
    const taxAmount = afterDiscount * taxRate;
    // Add delivery charge if any
    const deliveryChargeInput = document.getElementById('deliveryCharge');
    const deliveryCharge = deliveryChargeInput ? parseFloat(deliveryChargeInput.value) || 0 : 0;
    const total = afterDiscount + taxAmount + deliveryCharge;

    // Update DOM (use formatCurrency via simple JS formatting — server handles symbol; we'll mimic numeric format)
    // If you prefer symbol, formatCurrency server-side prints symbol; here we will show numeric with two decimals
    function formatMoney(n) {
        return Number(n).toFixed(2);
    }

    document.getElementById('display-subtotal').textContent = formatMoney(subtotal);
    document.getElementById('tfoot-subtotal').textContent = formatMoney(subtotal);
    document.getElementById('display-discount').textContent = '-' + formatMoney(discountAmount);
    document.getElementById('tfoot-discount').textContent = '-' + formatMoney(discountAmount);
    document.getElementById('display-after-discount').textContent = formatMoney(afterDiscount);
    document.getElementById('tfoot-after-discount').textContent = formatMoney(afterDiscount);
    document.getElementById('display-tax').textContent = formatMoney(taxAmount);
    document.getElementById('tfoot-tax').textContent = formatMoney(taxAmount);
    document.getElementById('display-total').textContent = formatMoney(total);
    document.getElementById('tfoot-total').textContent = formatMoney(total);
    // Update item discounts if element exists
    const itemDiscEl = document.getElementById('tfoot-item-discounts');
    if (itemDiscEl) itemDiscEl.textContent = '-' + formatMoney(totalItemDiscounts);

    // Update small breakdown
    const breakSub = document.getElementById('break-subtotal');
    const breakDisc = document.getElementById('break-discount');
    const breakTax = document.getElementById('break-tax');
    const breakTotal = document.getElementById('break-total');
    if (breakSub) breakSub.textContent = formatMoney(subtotal);
    if (breakDisc) breakDisc.textContent = formatMoney(discountAmount);
    if (breakTax) breakTax.textContent = formatMoney(taxAmount);
    if (breakTotal) breakTotal.textContent = formatMoney(total);
}

// Recalculate on page load
document.addEventListener('DOMContentLoaded', function() {
    recalcTotalsClientSide();
});

// Recalculate while typing (live update)
document.addEventListener('input', function(e) {
    if (e.target.classList.contains('price-input') || 
        e.target.classList.contains('quantity-input') || 
        e.target.classList.contains('item-discount-input')) {
        recalcTotalsClientSide();
    }
});
</script>

<?php 
require_once '../includes/footer.php'; 
ob_end_flush();
?>
