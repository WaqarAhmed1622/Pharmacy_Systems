<?php


require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/export.php';

$search = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';
$period = isset($_GET['period']) ? $_GET['period'] : 'all';
$customStart = isset($_GET['start']) ? $_GET['start'] : date('Y-m-d');
$customEnd = isset($_GET['end']) ? $_GET['end'] : date('Y-m-d');

if (isset($_GET['export'])) {
    $exportSearch = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';
    $exportPeriod = isset($_GET['period']) ? $_GET['period'] : 'all';
    $exportStart = isset($_GET['start']) ? $_GET['start'] : date('Y-m-d');
    $exportEnd = isset($_GET['end']) ? $_GET['end'] : date('Y-m-d');
    exportOrdersToCSV($exportSearch, $exportPeriod, $exportStart, $exportEnd);
}
// Handle order status update (add this after the search params section, before the queries)
ob_start();
require_once '../includes/header.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_status'])) {
    $orderId = (int)$_POST['order_id'];
    $newStatus = sanitizeInput($_POST['order_status']);
    
    // Get order details
    $orderQuery = "SELECT * FROM orders WHERE id = ?";
    $orderResult = executeQuery($orderQuery, 'i', [$orderId]);
    // FIX: Check if order exists before accessing
    if (empty($orderResult) || !isset($orderResult[0])) {
        $error = "Order not found.";
    } else {
        $order = $orderResult[0];
    
    if ($newStatus == 'returned' && $order['status'] != 'returned') {
        // Deduct the returned amount from the order
        $refundAmount = $order['total_amount'];
        
        // Update order status and mark as returned
        $updateQuery = "UPDATE orders SET status = ?, refund_amount = ? WHERE id = ?";
        if (executeNonQuery($updateQuery, 'sdi', [$newStatus, $refundAmount, $orderId])) {
            // Log the activity
            logActivity('Order Returned', $_SESSION['user_id'], 
            "Order ID: $orderId, Amount Refunded: " . formatCurrency($refundAmount));
            
            $success = "Order marked as returned. Refund amount: " . formatCurrency($refundAmount);
        } else {
            $error = "Failed to update order status.";
        }
    } else {
            // ✅ Normal status update
            $updateQuery = "UPDATE orders SET status = ? WHERE id = ?";
            if (executeNonQuery($updateQuery, 'si', [$newStatus, $orderId])) {
                logActivity('Order Status Updated', $_SESSION['user_id'], 
                "Order ID: $orderId, New Status: $newStatus");
                
                $success = "Order status updated successfully.";
                header("Location: ?view=" . $orderId);
                exit();
            } else {
                if ($newStatus == 'returned' && $order['status'] != 'returned') {
                    header("Location: ?view=" . $orderId);
                    exit();
                }
            }
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
                        oi.item_discount
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
    <!-- Order Details View -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3><i class="fas fa-receipt"></i> Order Details - <?php echo $orderDetails['order_number']; ?></h3>
        <div>
            <!-- Status Badge -->
            <span class="badge bg-<?php 
                echo $orderDetails['status'] == 'completed' ? 'success' : 
                    ($orderDetails['status'] == 'pending' ? 'warning' : 
                    ($orderDetails['status'] == 'returned' ? 'danger' : 'secondary'));
            ?> me-2" style="font-size: 0.9rem; padding: 0.5rem 0.75rem;">
                <?php echo ucfirst($orderDetails['status']); ?>
            </span>

            <!-- Print Receipt Button -->
            <a href="receipt.php?order_id=<?php echo $orderDetails['id']; ?>" class="btn btn-primary me-2" target="_blank">
                <i class="fas fa-print"></i> Print
            </a>

            <!-- Back Button -->
            <a href="orders.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>
    </div>

    <!-- Status Update Modal -->
    <div class="modal fade" id="statusModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <input type="hidden" name="order_id" id="modalOrderId">
                    <div class="modal-header">
                        <h5 class="modal-title">Update Order Status</h5>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="order_id" value="<?php echo $orderDetails['id']; ?>">
                        
                        <div class="mb-3">
                            <label class="form-label">Current Status</label>
                            <div class="alert alert-info">
                                <strong><?php echo ucfirst($orderDetails['status']); ?></strong>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">New Status *</label>
                            <select name="order_status" id="statusSelect" class="form-select" required>
                                <option value="completed" <?php echo $orderDetails['status'] == 'completed' ? 'selected' : ''; ?>>Completed</option>
                                <option value="returned" <?php echo $orderDetails['status'] == 'returned' ? 'selected' : ''; ?>>Returned</option>
                            </select>
                        </div>
                                                
                        <div class="alert alert-warning" id="returnWarning" style="display: none;">
                            <i class="fas fa-exclamation-triangle"></i>
                            <strong>Return Warning:</strong> Marking this order as returned will refund 
                            <strong><?php echo formatCurrency($orderDetails['total_amount']); ?></strong> 
                            and this amount will be deducted from today's sales report.
                        </div>
                        </div>
                        <div class="modal-footer">
                        <button type="submit" name="update_status" class="mb-3 btn btn-primary">Update Status</button>
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
                <div class="card-header">
                    <h6 class="card-title mb-0">Order Items</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th>Barcode</th>
                                    <th>Quantity</th>
                                    <th>Unit Price</th>
                                    <th>Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($orderItems as $item): ?>
                                    <tr>
                                        <td><?php echo sanitizeInput($item['product_name']); ?></td>
                                        <td><code><?php echo sanitizeInput($item['barcode']); ?></code></td>
                                        <td><?php echo $item['quantity']; ?></td>
                                        <td><?php echo formatCurrency($item['unit_price']); ?></td>
                                        <td><?php echo formatCurrency($item['total_price']); ?></td>
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
    <a href="?export=1&period=<?php echo $period; ?>&start=<?php echo $customStart; ?>&end=<?php echo $customEnd; ?>&search=<?php echo urlencode($search); ?>" class="btn btn-success">
        <i class="fas fa-download"></i> Export to CSV
    </a>
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

// Global function to open status modal from list or details view
function openStatusModal(orderId, currentStatus) {
    const modalOrderId = document.getElementById('modalOrderId');
    const statusSelect = document.getElementById('statusSelect');
    const returnWarning = document.getElementById('returnWarning');
    
    if (modalOrderId && statusSelect) {
        modalOrderId.value = orderId;
        statusSelect.value = currentStatus;
        
        // Hide warning initially
        if (returnWarning) {
            returnWarning.style.display = 'none';
        }
        
        const statusModal = new bootstrap.Modal(document.getElementById('statusModal'));
        statusModal.show();
    }
}

// Show warning when return is selected
const statusSelect = document.getElementById('statusSelect');
if (statusSelect) {
    statusSelect.addEventListener('change', function() {
        const returnWarning = document.getElementById('returnWarning');
        if (returnWarning) {
            if (this.value === 'returned') {
                returnWarning.style.display = 'block';
            } else {
                returnWarning.style.display = 'none';
            }
        }
    });
}

// Dynamic filtering - trigger on period change
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