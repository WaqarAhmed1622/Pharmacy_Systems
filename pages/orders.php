<?php
/**
 * Orders History Page
 * View order history and details with discount information
 */

require_once '../includes/header.php';
// Handle order status update (add this after the search params section, before the queries)

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_status'])) {
    $orderId = (int)$_POST['order_id'];
    $newStatus = sanitizeInput($_POST['order_status']);
    
    // Get order details
    $orderQuery = "SELECT * FROM orders WHERE id = ?";
    $orderResult = executeQuery($orderQuery, 'i', [$orderId]);
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
        // Regular status update (completed, pending, cancelled)
        $updateQuery = "UPDATE orders SET status = ? WHERE id = ?";
        if (executeNonQuery($updateQuery, 'si', [$newStatus, $orderId])) {
            logActivity('Order Status Updated', $_SESSION['user_id'], 
                "Order ID: $orderId, New Status: $newStatus");
            
            $success = "Order status updated successfully.";
        } else {
            $error = "Failed to update order status.";
        }
    }
}

$search = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// Build search query
$whereClause = "";
$searchParams = [];
$searchTypes = "";

if (!empty($search)) {
    $whereClause = "WHERE (o.order_number LIKE ? OR u.full_name LIKE ?)";
    $searchParams = ["%$search%", "%$search%"];
    $searchTypes = "ss";
}

// Get orders with pagination
$ordersQuery = "SELECT o.*, u.full_name as cashier_name 
                FROM orders o 
                JOIN users u ON o.cashier_id = u.id 
                $whereClause
                ORDER BY o.order_date DESC 
                LIMIT $limit OFFSET $offset";

$orders = executeQuery($ordersQuery, $searchTypes, $searchParams);

// Get total count for pagination
$countQuery = "SELECT COUNT(*) as total FROM orders o JOIN users u ON o.cashier_id = u.id $whereClause";
$countResult = executeQuery($countQuery, $searchTypes, $searchParams);
$totalOrders = $countResult[0]['total'];
$totalPages = ceil($totalOrders / $limit);

// Get order details if viewing specific order
$orderDetails = null;
$orderItems = [];
if (isset($_GET['view']) && is_numeric($_GET['view'])) {
    $orderId = (int)$_GET['view'];
    
    $detailQuery = "SELECT o.*, u.full_name as cashier_name 
                    FROM orders o 
                    JOIN users u ON o.cashier_id = u.id 
                    WHERE o.id = ?";
    $detailResult = executeQuery($detailQuery, 'i', [$orderId]);
    
    if (!empty($detailResult)) {
        $orderDetails = $detailResult[0];
        
        $itemsQuery = "SELECT oi.*, p.name as product_name, p.barcode 
                       FROM order_items oi 
                       JOIN products p ON oi.product_id = p.id 
                       WHERE oi.order_id = ?";
        $orderItems = executeQuery($itemsQuery, 'i', [$orderId]);
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

            <!-- Status Update Button - ADD THIS -->
            <button type="button" class="btn btn-warning me-2" 
                    data-bs-toggle="modal" 
                    data-bs-target="#statusModal">
                <i class="fas fa-sync"></i> Update Status
            </button>

            <!-- View Details Button -->
            <a href="?view=<?php echo $orderDetails['id']; ?>" class="btn btn-info me-2">
                <i class="fas fa-eye"></i> View
            </a>

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

    <!-- Status Update Modal - ADD THIS AFTER THE DIV ABOVE -->
    <div class="modal fade" id="statusModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title">Update Order Status</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
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
                            <select name="order_status" class="form-select" required>
                                <option value="">Select Status</option>
                                <option value="pending">Pending</option>
                                <option value="completed">Completed</option>
                                <option value="returned">Returned</option>
                                <option value="cancelled">Cancelled</option>
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
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="update_status" class="btn btn-primary">Update Status</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
    // Show warning when return is selected
    document.querySelector('[name="order_status"]').addEventListener('change', function() {
        const returnWarning = document.getElementById('returnWarning');
        if (this.value === 'returned') {
            returnWarning.style.display = 'block';
        } else {
            returnWarning.style.display = 'none';
        }
    });
    </script>   
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
                            <td><strong>Payment Method:</strong></td>
                            <td><?php echo ucfirst($orderDetails['payment_method']); ?></td>
                        </tr>
                        <tr>
                            <td><strong>Subtotal:</strong></td>
                            <td><?php echo formatCurrency($orderDetails['subtotal']); ?></td>
                        </tr>
                        <?php if (isset($orderDetails['discount_amount']) && $orderDetails['discount_amount'] > 0): ?>
                        <tr class="table-warning">
                            <td><strong>Discount:</strong></td>
                            <td class="text-danger">-<?php echo formatCurrency($orderDetails['discount_amount']); ?></td>
                        </tr>
                        <tr>
                            <td><strong>After Discount:</strong></td>
                            <td><?php echo formatCurrency($orderDetails['subtotal'] - $orderDetails['discount_amount']); ?></td>
                        </tr>
                        <?php endif; ?>
                        <tr>
                            <td><strong>Tax:</strong></td>
                            <td><?php echo formatCurrency($orderDetails['tax_amount']); ?></td>
                        </tr>
                        <tr class="table-success">
                            <td><strong>Total:</strong></td>
                            <td><strong><?php echo formatCurrency($orderDetails['total_amount']); ?></strong></td>
                        </tr>
                    </table>
                    
                    <?php if (isset($orderDetails['discount_amount']) && $orderDetails['discount_amount'] > 0): ?>
                    <div class="alert alert-success mt-3 mb-0">
                        <i class="fas fa-tag"></i> <strong>Customer Saved:</strong><br>
                        <?php echo formatCurrency($orderDetails['discount_amount']); ?>
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
    </div>
    
    <!-- Search Form -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-8">
                    <input 
                        type="text" 
                        name="search" 
                        class="form-control" 
                        placeholder="Search by order number or cashier name..."
                        value="<?php echo sanitizeInput($search); ?>"
                    >
                </div>
                <div class="col-md-4">
                    <button type="submit" class="btn btn-primary me-2">
                        <i class="fas fa-search"></i> Search
                    </button>
                    <?php if ($search): ?>
                        <a href="orders.php" class="btn btn-outline-secondary">Clear</a>
                    <?php endif; ?>
                </div>
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
                            <th>Discount</th>
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
                                    <td><?php echo formatCurrency($order['subtotal']); ?></td>
                                    <td>
                                        <?php if (isset($order['discount_amount']) && $order['discount_amount'] > 0): ?>
                                            <span class="badge bg-warning text-dark">
                                                -<?php echo formatCurrency($order['discount_amount']); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <strong class="text-success"><?php echo formatCurrency($order['total_amount']); ?></strong>
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
                                <a class="page-link" href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>">
                                    Previous
                                </a>
                            </li>
                        <?php endif; ?>
                        
                        <?php
                        $start = max(1, $page - 2);
                        $end = min($totalPages, $page + 2);
                        
                        for ($i = $start; $i <= $end; $i++): ?>
                            <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                        <?php endfor; ?>
                        
                        <?php if ($page < $totalPages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>">
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