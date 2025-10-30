<?php


require_once '../config/database.php';
require_once '../includes/functions.php';

$search = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';
$period = isset($_GET['period']) ? $_GET['period'] : 'all';
$customStart = isset($_GET['start']) ? $_GET['start'] : date('Y-m-d');
$customEnd = isset($_GET['end']) ? $_GET['end'] : date('Y-m-d');

// Handle order status update (add this after the search params section, before the queries)
ob_start();
require_once '../includes/header.php';

$error = '';
$success = '';

// Check for success message from redirect
if (isset($_GET['success'])) {
    $success = sanitizeInput($_GET['success']);
}

// Handle item-level returns
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['return_items'])) {
    $orderId = (int)$_POST['order_id'];
    $returnItemIds = isset($_POST['return_item_ids']) ? $_POST['return_item_ids'] : [];
    $returnQuantities = isset($_POST['return_quantities']) ? $_POST['return_quantities'] : [];
    $returnReason = isset($_POST['return_reason']) ? sanitizeInput($_POST['return_reason']) : '';
    
    if (empty($returnItemIds)) {
        $error = "Please select at least one item to return.";
    } else {
        $totalRefund = 0;
        $returnedItems = [];
        $discountRate = getSetting('discount_rate', 0);
        $taxRate = getSetting('tax_rate', 0.10);
        
        // Start transaction
        $conn = getConnection();
        mysqli_begin_transaction($conn);
        
        try {
            foreach ($returnItemIds as $itemId) {
                $itemId = (int)$itemId;
                $qtyToReturn = isset($returnQuantities[$itemId]) ? (int)$returnQuantities[$itemId] : 0;
                
                if ($qtyToReturn <= 0) continue;
                
                // Get item details
                $itemQuery = "SELECT oi.*, p.stock_quantity, p.name as product_name 
                             FROM order_items oi 
                             JOIN products p ON oi.product_id = p.id 
                             WHERE oi.id = ?";
                $itemResult = executeQuery($itemQuery, 'i', [$itemId]);
                
                if (empty($itemResult)) continue;
                
                $item = $itemResult[0];
                $qtyReturned = isset($item['quantity_returned']) ? $item['quantity_returned'] : 0;
                $qtyAvailable = $item['quantity'] - $qtyReturned;
                
                // Validate quantity
                if ($qtyToReturn > $qtyAvailable) {
                    throw new Exception("Cannot return more than available quantity for " . $item['product_name']);
                }
                
                // Calculate refund for this item (with item discount)
                $itemTotal = $item['unit_price'] * $qtyToReturn;
                $itemDiscountPercent = isset($item['item_discount']) ? $item['item_discount'] : 0;
                $itemDiscountAmount = $itemTotal * ($itemDiscountPercent / 100);
                $afterItemDiscount = $itemTotal - $itemDiscountAmount;
                
                // Apply cart discount proportionally
                $cartDiscountAmount = $afterItemDiscount * $discountRate;
                $afterCartDiscount = $afterItemDiscount - $cartDiscountAmount;
                
                // Apply tax
                $taxAmount = $afterCartDiscount * $taxRate;
                $refundAmount = $afterCartDiscount + $taxAmount;
                
                $totalRefund += $refundAmount;
                
                // Update order_items - increment quantity_returned
                $updateItemQuery = "UPDATE order_items 
                                   SET quantity_returned = quantity_returned + ? 
                                   WHERE id = ?";
                if (!executeNonQuery($updateItemQuery, 'ii', [$qtyToReturn, $itemId])) {
                    throw new Exception("Failed to update order item");
                }
                
                // Insert into order_returns table
                $insertReturnQuery = "INSERT INTO order_returns 
                                     (order_id, order_item_id, product_id, quantity_returned, original_quantity, refund_amount, reason, returned_by) 
                                     VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                $returnParams = [
                    $orderId, 
                    $itemId, 
                    $item['product_id'], 
                    $qtyToReturn, 
                    $item['quantity'], 
                    $refundAmount, 
                    $returnReason, 
                    $_SESSION['user_id']
                ];
                if (!executeNonQuery($insertReturnQuery, 'iiiidssi', $returnParams)) {
                    throw new Exception("Failed to record return");
                }
                
                // Update product stock
                $updateStockQuery = "UPDATE products 
                                    SET stock_quantity = stock_quantity + ? 
                                    WHERE id = ?";
                if (!executeNonQuery($updateStockQuery, 'ii', [$qtyToReturn, $item['product_id']])) {
                    throw new Exception("Failed to update product stock");
                }
                
                $returnedItems[] = $item['product_name'] . " (x" . $qtyToReturn . ")";
            }
            
            // Check if all items are fully returned
            $checkAllReturnedQuery = "SELECT COUNT(*) as total_items,
                                      SUM(CASE WHEN quantity = quantity_returned THEN 1 ELSE 0 END) as fully_returned
                                      FROM order_items 
                                      WHERE order_id = ?";
            $checkResult = executeQuery($checkAllReturnedQuery, 'i', [$orderId]);
            
            if (!empty($checkResult) && $checkResult[0]['total_items'] == $checkResult[0]['fully_returned']) {
                // All items fully returned - update order status
                $updateOrderQuery = "UPDATE orders SET status = 'returned', refund_amount = refund_amount + ? WHERE id = ?";
                executeNonQuery($updateOrderQuery, 'di', [$totalRefund, $orderId]);
            } else {
                // Partial return - update refund amount only
                $updateOrderQuery = "UPDATE orders SET refund_amount = refund_amount + ? WHERE id = ?";
                executeNonQuery($updateOrderQuery, 'di', [$totalRefund, $orderId]);
            }
            
            // Commit transaction
            mysqli_commit($conn);
            mysqli_close($conn);
            
            // Log activity
            $itemsList = implode(", ", $returnedItems);
            logActivity('Items Returned', $_SESSION['user_id'], 
                       "Order ID: $orderId, Items: $itemsList, Refund: " . formatCurrency($totalRefund));
            
            $success = "Items returned successfully. Total refund: " . formatCurrency($totalRefund);
            
            // Redirect to refresh the page
            header("Location: ?view=" . $orderId . "&success=" . urlencode($success));
            exit();
            
        } catch (Exception $e) {
            // Rollback on error
            mysqli_rollback($conn);
            mysqli_close($conn);
            $error = "Return failed: " . $e->getMessage();
        }
    }
}



$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// Build search query
// Build search and period query
$whereConditions = [];
$searchParams = [];
$searchTypes = "";

// Add search condition
if (!empty($search)) {
    $whereConditions[] = "(o.order_number LIKE ? OR u.full_name LIKE ?)";
    $searchParams[] = "%$search%";
    $searchParams[] = "%$search%";
    $searchTypes .= "ss";
}

// Add period condition
switch ($period) {
    case 'today':
        $whereConditions[] = "DATE(o.order_date) = CURDATE()";
        break;
    case 'yesterday':
        $whereConditions[] = "DATE(o.order_date) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)";
        break;
    case 'week':
        $whereConditions[] = "YEARWEEK(o.order_date) = YEARWEEK(CURDATE())";
        break;
    case 'month':
        $whereConditions[] = "MONTH(o.order_date) = MONTH(CURDATE()) AND YEAR(o.order_date) = YEAR(CURDATE())";
        break;
    case 'year':
        $whereConditions[] = "YEAR(o.order_date) = YEAR(CURDATE())";
        break;
    case 'custom':
        if ($customStart && $customEnd) {
            $whereConditions[] = "DATE(o.order_date) BETWEEN ? AND ?";
            $searchParams[] = $customStart;
            $searchParams[] = $customEnd;
            $searchTypes .= "ss";
        }
        break;
    case 'all':
    default:
        // No date filter
        break;
}

$whereClause = !empty($whereConditions) ? "WHERE " . implode(" AND ", $whereConditions) : "";

// Get orders with pagination
$ordersQuery = "SELECT o.*, u.full_name as cashier_name 
                FROM orders o 
                JOIN users u ON o.cashier_id = u.id 
                $whereClause
                ORDER BY o.order_date DESC 
                LIMIT $limit OFFSET $offset";

$orders = executeQuery($ordersQuery, $searchTypes, $searchParams);

// ✅ CALCULATE CORRECT DISCOUNT AND TOTAL FOR EACH ORDER IN LIST
if (!empty($orders)) {
    $discountRate = getSetting('discount_rate', 0);
    $taxRate = getSetting('tax_rate', 0.10);
    
    foreach ($orders as &$order) {
        // Get order items to calculate item discounts
        $itemsQuery = "SELECT oi.*, p.name as product_name 
                       FROM order_items oi 
                       JOIN products p ON oi.product_id = p.id 
                       WHERE oi.order_id = ?";
        $orderItems = executeQuery($itemsQuery, 'i', [$order['id']]);
        
        // Calculate totals
        $originalSubtotal = 0;
        $totalItemDiscounts = 0;
        $subtotalAfterItemDiscounts = 0;
        
        foreach ($orderItems as $item) {
            $lineTotal = $item['unit_price'] * $item['quantity'];
            $originalSubtotal += $lineTotal;
            
            $itemDiscountPercent = isset($item['item_discount']) ? $item['item_discount'] : 0;
            $itemDiscountAmount = $lineTotal * ($itemDiscountPercent / 100);
            $lineTotalAfterDiscount = $lineTotal - $itemDiscountAmount;
            
            $totalItemDiscounts += $itemDiscountAmount;
            $subtotalAfterItemDiscounts += $lineTotalAfterDiscount;
        }
        
        // Calculate cart discount
        $cartDiscountAmount = $subtotalAfterItemDiscounts * $discountRate;
        $afterCartDiscount = $subtotalAfterItemDiscounts - $cartDiscountAmount;
        
        // Calculate tax
        $taxAmount = $afterCartDiscount * $taxRate;
        
        // Calculate grand total
        $deliveryCharge = isset($order['delivery_charge']) ? $order['delivery_charge'] : 0;
        $grandTotal = $afterCartDiscount + $taxAmount + $deliveryCharge;
        
        // Store calculated values in order array
        $order['calculated_total_discount'] = $totalItemDiscounts + $cartDiscountAmount;
        $order['calculated_item_discount'] = $totalItemDiscounts;
        $order['calculated_cart_discount'] = $cartDiscountAmount;
        $order['calculated_grand_total'] = $grandTotal;
        $order['calculated_subtotal'] = $originalSubtotal;
    }
    unset($order); // Break reference
}

// Get total count for pagination
$countQuery = "SELECT COUNT(*) as total FROM orders o JOIN users u ON o.cashier_id = u.id $whereClause";
$countResult = executeQuery($countQuery, $searchTypes, $searchParams);
// FIX: Check if array exists and has index 0 before accessing
$totalOrders = (!empty($countResult) && isset($countResult[0])) ? $countResult[0]['total'] : 0;
$totalPages = ceil($totalOrders / $limit);

// Get order details if viewing specific order
$orderDetails = null;
$orderItems = [];
if (isset($_GET['view']) && is_numeric($_GET['view'])) {
    $orderId = (int)$_GET['view'];
    
    $detailQuery = "SELECT o.*, u.full_name as cashier_name,
                    o.order_type, o.delivery_charge
                    FROM orders o 
                    JOIN users u ON o.cashier_id = u.id 
                    WHERE o.id = ?";
    $detailResult = executeQuery($detailQuery, 'i', [$orderId]);
    
    // FIX: Check if array is not empty and has index 0
    if (!empty($detailResult) && isset($detailResult[0])) {
        $orderDetails = $detailResult[0];
        
        $itemsQuery = "SELECT oi.*, p.name as product_name, p.barcode,
                oi.item_discount, oi.quantity_returned
                FROM order_items oi 
                JOIN products p ON oi.product_id = p.id 
                WHERE oi.order_id = ?";
        $orderItems = executeQuery($itemsQuery, 'i', [$orderId]);
        
        // Calculate item discounts and totals (same as receipt.php)
        $originalSubtotal = 0;
        $totalItemDiscounts = 0;
        $subtotalAfterItemDiscounts = 0;

        foreach ($orderItems as &$item) {
            $lineTotal = $item['unit_price'] * $item['quantity'];
            $originalSubtotal += $lineTotal;
            
            $itemDiscountPercent = isset($item['item_discount']) ? $item['item_discount'] : 0;
            $item['item_discount_amount'] = $lineTotal * ($itemDiscountPercent / 100);
            $item['line_total_after_discount'] = $lineTotal - $item['item_discount_amount'];
            
            $totalItemDiscounts += $item['item_discount_amount'];
            $subtotalAfterItemDiscounts += $item['line_total_after_discount'];
        }
        unset($item); // break the reference

        // Recalculate correct discount and tax amounts for display
        $calculatedDiscountAmount = $subtotalAfterItemDiscounts * (getSetting('discount_rate', 0));
        $afterCartDiscount = $subtotalAfterItemDiscounts - $calculatedDiscountAmount;
        $calculatedTaxAmount = $afterCartDiscount * getSetting('tax_rate', 0.10);
        $calculatedGrandTotal = $afterCartDiscount + $calculatedTaxAmount + ($orderDetails['delivery_charge'] ?? 0);

        // Calculate total savings
        $totalSavings = $totalItemDiscounts + $calculatedDiscountAmount;
    }
}
$discountRate = getSetting('discount_rate', 0) * 100;
$taxRate = getSetting('tax_rate', 0.10) * 100;
?>


<?php if ($orderDetails): ?>

    <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="fas fa-check-circle"></i> <?php echo $success; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Order Details View -->
    <!-- Order Details View -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <h3>
        <i class="fas fa-receipt"></i> Order Details - <?php echo $orderDetails['order_number']; ?>
        <span class="badge bg-<?php 
            echo $orderDetails['status'] == 'completed' ? 'success' : 
                ($orderDetails['status'] == 'pending' ? 'warning' : 
                ($orderDetails['status'] == 'returned' ? 'danger' : 'secondary'));
        ?> ms-2" style="font-size: 0.8rem;">
            <?php echo ucfirst($orderDetails['status']); ?>
        </span>
    </h3>
    <div>
        <!-- Print Receipt Button -->
        <a href="receipt.php?order_id=<?php echo $orderDetails['id']; ?>" class="btn btn-primary me-2" target="_blank">
            <i class="fas fa-print"></i> Print Receipt
        </a>

        <!-- Back Button -->
        <a href="orders.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back to Orders
        </a>
    </div>
</div>

    <!-- Return Item Modal -->
    <div class="modal fade" id="returnItemModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST" id="returnItemForm">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="fas fa-undo"></i> Return Order Items</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="return_items" value="1">
                        <input type="hidden" name="order_id" value="<?php echo $orderDetails['id']; ?>">
                        
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i>
                            <strong>Select items to return:</strong> Choose the items and quantities you want to return.
                            The refund amount will be calculated automatically.
                        </div>
                        
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th width="50">Return</th>
                                        <th>Product</th>
                                        <th>Available</th>
                                        <th>Quantity to Return</th>
                                        <th>Unit Price</th>
                                        <th>Refund</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($orderItems as $item): ?>
                                        <?php 
                                        $qtyReturned = isset($item['quantity_returned']) ? $item['quantity_returned'] : 0;
                                        $qtyAvailable = $item['quantity'] - $qtyReturned;
                                        ?>
                                        <?php if ($qtyAvailable > 0): ?>
                                            <tr>
                                                <td>
                                                    <input type="checkbox" class="form-check-input return-checkbox" 
                                                           name="return_item_ids[]" 
                                                           value="<?php echo $item['id']; ?>"
                                                           data-item-id="<?php echo $item['id']; ?>"
                                                           data-max-qty="<?php echo $qtyAvailable; ?>"
                                                           data-unit-price="<?php echo $item['unit_price']; ?>"
                                                           data-item-discount="<?php echo isset($item['item_discount']) ? $item['item_discount'] : 0; ?>">
                                                </td>
                                                <td>
                                                    <strong><?php echo sanitizeInput($item['product_name']); ?></strong><br>
                                                    <small class="text-muted">Code: <?php echo sanitizeInput($item['barcode']); ?></small>
                                                </td>
                                                <td>
                                                    <span class="badge bg-primary"><?php echo $qtyAvailable; ?></span>
                                                    <?php if ($qtyReturned > 0): ?>
                                                        <br><small class="text-muted">(<?php echo $qtyReturned; ?> returned)</small>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <input type="number" class="form-control form-control-sm return-quantity" 
                                                           name="return_quantities[<?php echo $item['id']; ?>]" 
                                                           data-item-id="<?php echo $item['id']; ?>"
                                                           min="1" 
                                                           max="<?php echo $qtyAvailable; ?>" 
                                                           value="<?php echo $qtyAvailable; ?>"
                                                           style="width: 80px;"
                                                           disabled>
                                                </td>
                                                <td><?php echo formatCurrency($item['unit_price']); ?></td>
                                                <td>
                                                    <span class="text-danger refund-amount" data-item-id="<?php echo $item['id']; ?>">
                                                        <?php echo formatCurrency(0); ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot>
                                    <tr class="table-warning">
                                        <td colspan="5" class="text-end"><strong>Total Refund Amount:</strong></td>
                                        <td>
                                            <strong class="text-danger" id="totalRefund"><?php echo formatCurrency(0); ?></strong>
                                        </td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Return Reason (Optional)</label>
                            <textarea name="return_reason" class="form-control" rows="2" placeholder="e.g., Defective, Wrong item, Customer changed mind..."></textarea>
                        </div>
                        
                        <div class="alert alert-warning" id="returnWarningItems">
                            <i class="fas fa-exclamation-triangle"></i>
                            <strong>Warning:</strong> This action will:
                            <ul class="mb-0 mt-2">
                                <li>Refund the selected items</li>
                                <li>Update inventory stock</li>
                                <li>Deduct from today's sales report</li>
                                <li>This action cannot be undone</li>
                            </ul>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger" id="submitReturn" disabled>
                            <i class="fas fa-undo"></i> Process Return
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h6 class="card-title mb-0">Order Information</h6>
                </div>
                <div class="card-body">
                    <table class="table table-sm">
                        <tr>
                            <td><strong>Order Number:</strong></td>
                            <td><?php echo sanitizeInput($orderDetails['order_number']); ?></td>
                        </tr>
                        <tr>
                            <td><strong>Date:</strong></td>
                            <td><?php echo formatDate($orderDetails['order_date']); ?></td>
                        </tr>
                        <tr>
                            <td><strong>Cashier:</strong></td>
                            <td><?php echo sanitizeInput($orderDetails['cashier_name']); ?></td>
                        </tr>
                        <tr>
                            <td><strong>Order Type:</strong></td>
                            <td><?php echo ucfirst($orderDetails['order_type']); ?></td>
                        </tr>
                        <tr>
                            <td><strong>Payment Method:</strong></td>
                            <td><?php echo ucfirst($orderDetails['payment_method']); ?></td>
                        </tr>
                        <tr>
                            <td><strong>Original Subtotal:</strong></td>
                            <td><?php echo formatCurrency($originalSubtotal); ?></td>
                        </tr>
                        <?php if ($totalItemDiscounts > 0): ?>
                        <tr class="table-warning">
                            <td><strong>Item Discounts:</strong></td>
                            <td class="text-danger">-<?php echo formatCurrency($totalItemDiscounts); ?></td>
                        </tr>
                        <tr>
                            <td><strong>Subtotal After Item Discounts:</strong></td>
                            <td><?php echo formatCurrency($subtotalAfterItemDiscounts); ?></td>
                        </tr>
                        <?php endif; ?>
                        <?php if ($calculatedDiscountAmount > 0): ?>
                        <tr class="table-warning">
                            <td><strong>Cart Discount (<?php echo number_format($discountRate, 1); ?>%):</strong></td>
                            <td class="text-danger">-<?php echo formatCurrency($calculatedDiscountAmount); ?></td>
                        </tr>
                        <tr>
                            <td><strong>After Cart Discount:</strong></td>
                            <td><?php echo formatCurrency($afterCartDiscount); ?></td>
                        </tr>
                        <?php endif; ?>
                        <tr>
                            <td><strong>Tax (<?php echo number_format($taxRate, 1); ?>%):</strong></td>
                            <td><?php echo formatCurrency($calculatedTaxAmount); ?></td>
                        </tr>
                        <?php if (!empty($orderDetails['delivery_charge']) && $orderDetails['delivery_charge'] > 0): ?>
                        <tr>
                            <td><strong>Delivery Charge:</strong></td>
                            <td><?php echo formatCurrency($orderDetails['delivery_charge']); ?></td>
                        </tr>
                        <?php endif; ?>
                        <tr class="table-success">
                            <td><strong>Total:</strong></td>
                            <td><strong><?php echo formatCurrency($calculatedGrandTotal); ?></strong></td>
                        </tr>
                        <?php if ($orderDetails['refund_amount'] > 0): ?>
<tr class="table-danger">
    <td><strong>Total Refunded:</strong></td>
    <td class="text-danger"><strong>-<?php echo formatCurrency($orderDetails['refund_amount']); ?></strong></td>
</tr>
<?php endif; ?>
                    </table>
                    
                    <?php if ($totalSavings > 0): ?>
                    <div class="alert alert-success mt-3 mb-0">
                        <i class="fas fa-tag"></i> <strong>Customer Saved:</strong><br>
                        <?php echo formatCurrency($totalSavings); ?>
                        <?php if ($totalItemDiscounts > 0 && $calculatedDiscountAmount > 0): ?>
                            <br><small>(Item: <?php echo formatCurrency($totalItemDiscounts); ?> + Cart: <?php echo formatCurrency($calculatedDiscountAmount); ?>)</small>
                        <?php elseif ($totalItemDiscounts > 0): ?>
                            <br><small>(Item Discounts)</small>
                        <?php else: ?>
                            <br><small>(Cart Discount)</small>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="col-md-8">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
            <h6 class="card-title mb-0">Order Items</h6>
            <?php if ($orderDetails['status'] != 'returned'): ?>
                <button type="button" class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#returnItemModal">
                    <i class="fas fa-undo"></i> Return Item(s)
                </button>
            <?php endif; ?>
        </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
    <tr>
        <th>Product</th>
        <th>Barcode</th>
        <th>Quantity</th>
        <th>Returned</th>
        <th>Unit Price</th>
        <th>Total</th>
        <th>Status</th>
    </tr>
</thead>
<tbody>
    <?php foreach ($orderItems as $item): ?>
        <?php 
        $qtyReturned = isset($item['quantity_returned']) ? $item['quantity_returned'] : 0;
        $qtyRemaining = $item['quantity'] - $qtyReturned;
        $isFullyReturned = $qtyRemaining <= 0;
        ?>
        <tr class="<?php echo $isFullyReturned ? 'table-danger' : ''; ?>">
            <td><?php echo sanitizeInput($item['product_name']); ?></td>
            <td><code><?php echo sanitizeInput($item['barcode']); ?></code></td>
            <td><?php echo $item['quantity']; ?></td>
            <td>
                <?php if ($qtyReturned > 0): ?>
                    <span class="badge bg-danger"><?php echo $qtyReturned; ?></span>
                <?php else: ?>
                    <span class="text-muted">0</span>
                <?php endif; ?>
            </td>
            <td><?php echo formatCurrency($item['unit_price']); ?></td>
            <td><?php echo formatCurrency($item['total_price']); ?></td>
            <td>
                <?php if ($isFullyReturned): ?>
                    <span class="badge bg-danger">Fully Returned</span>
                <?php elseif ($qtyReturned > 0): ?>
                    <span class="badge bg-warning text-dark">Partially Returned</span>
                <?php else: ?>
                    <span class="badge bg-success">Active</span>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
</tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

<?php else: ?>
    
    <!-- Orders List View -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <h3><i class="fas fa-receipt"></i> Orders History</h3>
</div>
    
    <!-- Search and Filter Form -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" id="filterForm" class="row g-3">
            <div class="col-md-3">
                <label class="form-label">Period</label>
                <select name="period" id="period" class="form-select">
                    <option value="all" <?php echo $period == 'all' ? 'selected' : ''; ?>>All Time</option>
                    <option value="today" <?php echo $period == 'today' ? 'selected' : ''; ?>>Today</option>
                    <option value="yesterday" <?php echo $period == 'yesterday' ? 'selected' : ''; ?>>Yesterday</option>
                    <option value="week" <?php echo $period == 'week' ? 'selected' : ''; ?>>This Week</option>
                    <option value="month" <?php echo $period == 'month' ? 'selected' : ''; ?>>This Month</option>
                    <option value="year" <?php echo $period == 'year' ? 'selected' : ''; ?>>This Year</option>
                    <option value="custom" <?php echo $period == 'custom' ? 'selected' : ''; ?>>Custom Range</option>
                </select>
            </div>
            
            <div class="col-md-2" id="customDateRange" style="display: <?php echo $period == 'custom' ? 'block' : 'none'; ?>">
                <label class="form-label">Start Date</label>
                <input type="date" name="start" id="start_date" class="form-control" value="<?php echo $customStart; ?>">
            </div>
            
            <div class="col-md-2" id="customDateRangeEnd" style="display: <?php echo $period == 'custom' ? 'block' : 'none'; ?>">
                <label class="form-label">End Date</label>
                <input type="date" name="end" id="end_date" class="form-control" value="<?php echo $customEnd; ?>">
            </div>
            
            <div class="col-md-4">
                <label class="form-label">Search</label>
                <input 
                    type="text" 
                    name="search" 
                    id="search"
                    class="form-control" 
                    placeholder="Order number or cashier..."
                    value="<?php echo sanitizeInput($search); ?>"
                >
            </div>
            
            <?php if ($search || $period != 'all'): ?>
                <div class="col-md-12">
                    <a href="orders.php" class="btn btn-outline-secondary btn-sm">
                        <i class="fas fa-times"></i> Clear Filters
                    </a>
                </div>
            <?php endif; ?>
        </form>
        <!-- Show Returns History if exists -->
                    <!-- Show Returns History if exists -->
<?php
if (isset($orderDetails) && !empty($orderDetails)) {
    $returnsQuery = "SELECT r.*, p.name as product_name, u.full_name as returned_by_name 
                   FROM order_returns r 
                   JOIN products p ON r.product_id = p.id 
                   JOIN users u ON r.returned_by = u.id 
                   WHERE r.order_id = ? 
                   ORDER BY r.return_date DESC";
    $returns = executeQuery($returnsQuery, 'i', [$orderDetails['id']]);
} else {
    $returns = [];
}
?>
                    
                    <?php if (!empty($returns)): ?>
                        <hr>
                        <h6 class="mt-3"><i class="fas fa-history"></i> Return History</h6>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Product</th>
                                        <th>Quantity</th>
                                        <th>Refund</th>
                                        <th>Reason</th>
                                        <th>Processed By</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($returns as $return): ?>
                                        <tr>
                                            <td><?php echo formatDate($return['return_date']); ?></td>
                                            <td><?php echo sanitizeInput($return['product_name']); ?></td>
                                            <td><?php echo $return['quantity_returned']; ?></td>
                                            <td class="text-danger"><?php echo formatCurrency($return['refund_amount']); ?></td>
                                            <td><?php echo !empty($return['reason']) ? sanitizeInput($return['reason']) : '-'; ?></td>
                                            <td><?php echo sanitizeInput($return['returned_by_name']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
    </div>
</div>
    
    <!-- Orders Table -->
    <div class="card">
        <div class="card-body">
            <?php if ($search): ?>
    <p class="text-muted mb-3">
        <i class="fas fa-search"></i> Search results for: <strong>"<?php echo sanitizeInput($search); ?>"</strong>
        (<?php echo $totalOrders; ?> orders found)
    </p>
<?php endif; ?>
            
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Order Number</th>
                            <th>Date</th>
                            <th>Cashier</th>
                            <th>Subtotal</th>
                            <th>Total Discount</th>
                            <th>Total Amount</th>
                            <th>Payment</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($orders)): ?>
                            <tr>
                                <td colspan="8" class="text-center py-4">
                                    <i class="fas fa-receipt fa-3x text-muted mb-3"></i>
                                    <h5 class="text-muted">No orders found</h5>
                                    <?php if ($search): ?>
                                        <p class="text-muted">Try adjusting your search terms</p>
                                        <a href="orders.php" class="btn btn-outline-primary">View All Orders</a>
                                    <?php else: ?>
                                        <p class="text-muted">No sales have been made yet</p>
                                        <a href="pos.php" class="btn btn-primary">Make First Sale</a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($orders as $order): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo sanitizeInput($order['order_number']); ?></strong>
                                    </td>
                                    <td><?php echo formatDate($order['order_date']); ?></td>
                                    <td><?php echo sanitizeInput($order['cashier_name']); ?></td>
                                    <td><?php echo formatCurrency($order['calculated_subtotal']); ?></td>
                                    <td>
                                        <?php if ($order['calculated_total_discount'] > 0): ?>
                                            <span class="badge bg-warning text-dark" title="Item: <?php echo formatCurrency($order['calculated_item_discount']); ?> + Cart: <?php echo formatCurrency($order['calculated_cart_discount']); ?>">
                                                -<?php echo formatCurrency($order['calculated_total_discount']); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <strong class="text-success"><?php echo formatCurrency($order['calculated_grand_total']); ?></strong>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php echo $order['payment_method'] == 'cash' ? 'success' : ($order['payment_method'] == 'card' ? 'primary' : 'secondary'); ?>">
                                            <?php echo ucfirst($order['payment_method']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="?view=<?php echo $order['id']; ?>" class="btn btn-outline-primary" title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="receipt.php?order_id=<?php echo $order['id']; ?>" class="btn btn-outline-success" title="Print Receipt" target="_blank">
                                                <i class="fas fa-print"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <nav aria-label="Orders pagination" class="mt-4">
                    <ul class="pagination justify-content-center">
                        <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $page - 1; ?>&period=<?php echo $period; ?>&start=<?php echo $customStart; ?>&end=<?php echo $customEnd; ?>&search=<?php echo urlencode($search); ?>">
                                    Previous
                                </a>
                            </li>
                        <?php endif; ?>

                        <?php
                        $start = max(1, $page - 2);
                        $end = min($totalPages, $page + 2);

                        for ($i = $start; $i <= $end; $i++): ?>
                            <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?>&period=<?php echo $period; ?>&start=<?php echo $customStart; ?>&end=<?php echo $customEnd; ?>&search=<?php echo urlencode($search); ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                        <?php endfor; ?>

                        <?php if ($page < $totalPages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $page + 1; ?>&period=<?php echo $period; ?>&start=<?php echo $customStart; ?>&end=<?php echo $customEnd; ?>&search=<?php echo urlencode($search); ?>">
                                    Next
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
                
                <div class="text-center mt-3">
                    <small class="text-muted">
                        Showing page <?php echo $page; ?> of <?php echo $totalPages; ?> 
                        (<?php echo $totalOrders; ?> total orders)
                    </small>
                </div>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<script>
// This script should work on both order details and order list pages

<?php if ($orderDetails): ?>
// Return Item Modal Logic
document.addEventListener('DOMContentLoaded', function() {
    const returnCheckboxes = document.querySelectorAll('.return-checkbox');
    const returnQuantities = document.querySelectorAll('.return-quantity');
    const submitButton = document.getElementById('submitReturn');
    const totalRefundElement = document.getElementById('totalRefund');
    
    // Only run if we have return elements
    if (returnCheckboxes.length === 0) return;
    
    // Get discount and tax rates from PHP
    const discountRate = <?php echo getSetting('discount_rate', 0); ?>;
    const taxRate = <?php echo getSetting('tax_rate', 0.10); ?>;
    
    function calculateRefund() {
        let totalRefund = 0;
        let hasSelection = false;
        
        returnCheckboxes.forEach(checkbox => {
            if (checkbox.checked) {
                hasSelection = true;
                const itemId = checkbox.dataset.itemId;
                const qtyInput = document.querySelector(`.return-quantity[data-item-id="${itemId}"]`);
                const qty = parseInt(qtyInput.value) || 0;
                const unitPrice = parseFloat(checkbox.dataset.unitPrice);
                const itemDiscount = parseFloat(checkbox.dataset.itemDiscount) || 0;
                
                // Calculate with item discount
                const itemTotal = unitPrice * qty;
                const itemDiscountAmount = itemTotal * (itemDiscount / 100);
                const afterItemDiscount = itemTotal - itemDiscountAmount;
                
                // Apply cart discount
                const cartDiscountAmount = afterItemDiscount * discountRate;
                const afterCartDiscount = afterItemDiscount - cartDiscountAmount;
                
                // Apply tax
                const taxAmount = afterCartDiscount * taxRate;
                const refundAmount = afterCartDiscount + taxAmount;
                
                totalRefund += refundAmount;
                
                // Update individual refund display
                const refundSpan = document.querySelector(`.refund-amount[data-item-id="${itemId}"]`);
                if (refundSpan) {
                    refundSpan.textContent = formatCurrency(refundAmount);
                }
            }
        });
        
        // Update total
        if (totalRefundElement) {
            totalRefundElement.textContent = formatCurrency(totalRefund);
        }
        
        // Enable/disable submit button
        if (submitButton) {
            submitButton.disabled = !hasSelection;
        }
    }
    
    // Enable/disable quantity input based on checkbox
    returnCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            const itemId = this.dataset.itemId;
            const qtyInput = document.querySelector(`.return-quantity[data-item-id="${itemId}"]`);
            const refundSpan = document.querySelector(`.refund-amount[data-item-id="${itemId}"]`);
            
            if (qtyInput) {
                qtyInput.disabled = !this.checked;
                if (!this.checked) {
                    qtyInput.value = this.dataset.maxQty;
                    if (refundSpan) {
                        refundSpan.textContent = formatCurrency(0);
                    }
                }
            }
            
            calculateRefund();
        });
    });
    
    // Recalculate when quantity changes
    returnQuantities.forEach(input => {
        input.addEventListener('input', calculateRefund);
    });
    
    // Format currency helper
    function formatCurrency(amount) {
        return 'Rs ' + amount.toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
    }
    
    // Initialize on modal show
    const returnModal = document.getElementById('returnItemModal');
    if (returnModal) {
        returnModal.addEventListener('show.bs.modal', function() {
            calculateRefund();
        });
    }
});
<?php endif; ?>

// Dynamic filtering - trigger on period change (works on list page)
const periodSelect = document.getElementById('period');
if (periodSelect) {
    periodSelect.addEventListener('change', function() {
        const customDateRange = document.getElementById('customDateRange');
        const customDateRangeEnd = document.getElementById('customDateRangeEnd');
        
        if (this.value === 'custom') {
            // Show custom date inputs
            if (customDateRange) customDateRange.style.display = 'block';
            if (customDateRangeEnd) customDateRangeEnd.style.display = 'block';
        } else {
            // Hide custom date inputs and auto-submit
            if (customDateRange) customDateRange.style.display = 'none';
            if (customDateRangeEnd) customDateRangeEnd.style.display = 'none';
            document.getElementById('filterForm').submit();
        }
    });
}

// For custom range, submit when end date is selected
const endDateInput = document.getElementById('end_date');
if (endDateInput) {
    endDateInput.addEventListener('change', function() {
        const startDate = document.getElementById('start_date').value;
        const endDate = this.value;
        
        if (startDate && endDate) {
            document.getElementById('filterForm').submit();
        }
    });
}

// Optional: Also trigger on search input (with debounce)
const searchInput = document.getElementById('search');
if (searchInput) {
    let searchTimeout;
    searchInput.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            document.getElementById('filterForm').submit();
        }, 500); // Wait 500ms after user stops typing
    });
}
</script>

<style>
/* Pagination styles */
.pagination {
    display: flex;
    padding-left: 0;
    list-style: none;
}

.page-item:not(:first-child) .page-link {
    margin-left: -1px;
}

.page-link {
    position: relative;
    display: block;
    color: #0d6efd;
    text-decoration: none;
    background-color: #fff;
    border: 1px solid #dee2e6;
    padding: .375rem .75rem;
}

.page-link:hover {
    z-index: 2;
    color: #0a58ca;
    background-color: #e9ecef;
    border-color: #dee2e6;
}

.page-item.active .page-link {
    z-index: 3;
    color: #fff;
    background-color: #0d6efd;
    border-color: #0d6efd;
}

.page-item:first-child .page-link {
    border-top-left-radius: .25rem;
    border-bottom-left-radius: .25rem;
}

.page-item:last-child .page-link {
    border-top-right-radius: .25rem;
    border-bottom-right-radius: .25rem;
}
</style>

<?php require_once '../includes/footer.php'; ?>