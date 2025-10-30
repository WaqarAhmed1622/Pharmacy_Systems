<?php
/**
 * Sales Reports Page
 * Admin only - View sales analytics and reports
 */

require_once '../config/database.php';
require_once '../includes/functions.php';

$period = isset($_GET['period']) ? $_GET['period'] : 'today';
$customStart = isset($_GET['start']) ? $_GET['start'] : date('Y-m-d');
$customEnd = isset($_GET['end']) ? $_GET['end'] : date('Y-m-d');

require_once '../includes/header.php';
requireAdmin();

$salesData = getSalesDataWithRefunds($period, $customStart, $customEnd);
$topProducts = getTopProducts($period, $customStart, $customEnd);

// Get daily sales for chart (last 30 days)
// Get daily sales for chart (last 30 days) - Net sales (sales - refunds)
$chartData = [];
$chartLabels = [];
for ($i = 29; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $dateLabel = date('M j', strtotime("-$i days"));
    
    $dayStats = executeQuery("SELECT 
                                COALESCE(SUM(total_amount), 0) as gross_sales,
                                COALESCE(SUM(refund_amount), 0) as refunds
                              FROM orders 
                              WHERE DATE(order_date) = ?", 's', [$date]);
    
    if ($dayStats && isset($dayStats[0])) {
        $salesAmount = $dayStats[0]['gross_sales'] - $dayStats[0]['refunds'];
    } else {
        $salesAmount = 0;
    }
    
    $chartData[] = $salesAmount;
    $chartLabels[] = $dateLabel;
}
?>

<!-- Period Selection -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-chart-line"></i> Sales Reports
                </h5>
            </div>
            <div class="card-body">
                <form method="GET" id="reportForm" class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Report Period</label>
                        <select name="period" id="period" class="form-select">
                            <option value="today" <?php echo $period == 'today' ? 'selected' : ''; ?>>Today</option>
                            <option value="yesterday" <?php echo $period == 'yesterday' ? 'selected' : ''; ?>>Yesterday</option>
                            <option value="week" <?php echo $period == 'week' ? 'selected' : ''; ?>>This Week</option>
                            <option value="month" <?php echo $period == 'month' ? 'selected' : ''; ?>>This Month</option>
                            <option value="year" <?php echo $period == 'year' ? 'selected' : ''; ?>>This Year</option>
                            <option value="custom" <?php echo $period == 'custom' ? 'selected' : ''; ?>>Custom Range</option>
                        </select>
                    </div>
                    
                    <div class="col-md-3" id="customDateRange" style="display: <?php echo $period == 'custom' ? 'block' : 'none'; ?>">
                        <label class="form-label">Start Date</label>
                        <input type="date" name="start" id="start_date" class="form-control" value="<?php echo $customStart; ?>">
                    </div>
                    
                    <div class="col-md-3" id="customDateRangeEnd" style="display: <?php echo $period == 'custom' ? 'block' : 'none'; ?>">
                        <label class="form-label">End Date</label>
                        <input type="date" name="end" id="end_date" class="form-control" value="<?php echo $customEnd; ?>">
                    </div>
                    
                    <?php if ($period == 'custom'): ?>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-search"></i> Apply
                        </button>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($period != 'today'): ?>
                    <div class="col-md-12">
                        <a href="sales_reports.php" class="btn btn-outline-secondary btn-sm">
                            <i class="fas fa-times"></i> Clear Filters
                        </a>
                    </div>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>
</div>


<!-- Sales Statistics -->
<div class="row mb-4">
    <div class="col-md-3 mb-3">
        <div class="card stats-card h-100">
            <div class="card-body text-center">
                <i class="fas fa-dollar-sign fa-2x mb-2"></i>
                <h4 class="card-title"><?php echo formatCurrency($salesData['net_sales']); ?></h4>            <p class="card-text">Total Sales</p>
            </div>
        </div>
    </div>

    <?php if ($salesData['total_refunds'] > 0): ?>
<div class="col-md-3 mb-3">
    <div class="card stats-card-danger h-100">
        <div class="card-body text-center">
            <i class="fas fa-undo fa-2x mb-2"></i>
            <h4 class="card-title"><?php echo formatCurrency($salesData['total_refunds']); ?></h4>
            <p class="card-text">Total Refunds</p>
        </div>
    </div>
</div>
<?php endif; ?>
    
    <div class="col-md-3 mb-3">
        <div class="card stats-card-success h-100">
            <div class="card-body text-center">
                <i class="fas fa-receipt fa-2x mb-2"></i>
                <h4 class="card-title"><?php echo $salesData['total_orders']; ?></h4>
                <p class="card-text">Total Orders</p>
            </div>
        </div>
    </div>
    
    <div class="col-md-3 mb-3">
        <div class="card stats-card-info h-100">
            <div class="card-body text-center">
                <i class="fas fa-chart-line fa-2x mb-2"></i>
                <h4 class="card-title"><?php echo formatCurrency($salesData['avg_order_value']); ?></h4>
                <p class="card-text">Average Order</p>
            </div>
        </div>
    </div>
    
    <div class="col-md-3 mb-3">
        <div class="card stats-card-warning h-100">
            <div class="card-body text-center">
                <i class="fas fa-percentage fa-2x mb-2"></i>
                <h4 class="card-title"><?php echo formatCurrency($salesData['total_tax']); ?></h4>
                <p class="card-text">Total Tax</p>
            </div>
        </div>
    </div>

</div>

<div class="row">
    <!-- Sales Chart -->
    <div class="col-md-8 mb-4">
        <div class="card">
            <div class="card-header">
                <h6 class="card-title mb-0">
                    <i class="fas fa-chart-area"></i> Sales Trend (Last 30 Days)
                </h6>
            </div>
            <div class="card-body">
                <canvas id="salesChart" height="100"></canvas>
            </div>
        </div>
    </div>
    
    <!-- Top Products -->
    <div class="col-md-4 mb-4">
        <div class="card h-100">
            <div class="card-header">
                <h6 class="card-title mb-0">
                    <i class="fas fa-star"></i> Top Products
                </h6>
            </div>
            <div class="card-body">
                <?php if (empty($topProducts)): ?>
                    <p class="text-muted text-center">No sales data for selected period</p>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($topProducts as $index => $product): ?>
                            <div class="list-group-item d-flex justify-content-between align-items-center px-0">
                                <div class="flex-grow-1">
                                    <div class="fw-bold"><?php echo ($index + 1) . '. ' . sanitizeInput($product['name']); ?></div>
                                    <small class="text-muted"><?php echo sanitizeInput($product['barcode']); ?></small>
                                    <div class="small text-success">
                                        <?php echo formatCurrency($product['total_revenue']); ?> revenue
                                    </div>
                                </div>
                                <span class="badge bg-primary rounded-pill">
                                    <?php echo $product['total_sold']; ?> sold
                                </span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Payment Methods Breakdown -->
<div class="row">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h6 class="card-title mb-0">
                    <i class="fas fa-credit-card"></i> Payment Methods
                </h6>
            </div>
            <div class="card-body">
                <?php
                $whereClause = '';
                $params = [];
                
                switch ($period) {
                    case 'today':
                        $whereClause = "WHERE DATE(order_date) = CURDATE() AND status != 'returned'";
                        break;
                    case 'yesterday':
                        $whereClause = "WHERE DATE(order_date) = DATE_SUB(CURDATE(), INTERVAL 1 DAY) AND status != 'returned'";
                        break;
                    case 'week':
                        $whereClause = "WHERE YEARWEEK(order_date) = YEARWEEK(CURDATE()) AND status != 'returned'";
                        break;
                    case 'month':
                        $whereClause = "WHERE MONTH(order_date) = MONTH(CURDATE()) AND YEAR(order_date) = YEAR(CURDATE()) AND status != 'returned'";
                        break;
                    case 'year':
                        $whereClause = "WHERE YEAR(order_date) = YEAR(CURDATE()) AND status != 'returned'";
                        break;
                    case 'custom':
                        if ($customStart && $customEnd) {
                            $whereClause = "WHERE DATE(order_date) BETWEEN ? AND ? AND status != 'returned'";
                            $params = [$customStart, $customEnd];
                        }
                        break;
                }
                
                $paymentMethods = getPaymentMethodsBreakdown($period, $customStart, $customEnd);
                ?>
                
                <?php if (empty($paymentMethods)): ?>
                    <p class="text-muted text-center">No payment data for selected period</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Method</th>
                                    <th>Orders</th>
                                    <th>Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($paymentMethods as $method): ?>
                                    <tr>
                                        <td>
                                            <span class="badge bg-<?php echo $method['payment_method'] == 'cash' ? 'success' : ($method['payment_method'] == 'card' ? 'primary' : 'secondary'); ?>">
                                                <?php echo ucfirst($method['payment_method']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo $method['count']; ?></td>
                                        <td><?php echo formatCurrency($method['total']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h6 class="card-title mb-0">
                    <i class="fas fa-info-circle"></i> Report Summary
                </h6>
            </div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-6 mb-3">
                        <h6 class="text-muted">Period</h6>
                        <strong>
                            <?php 
                            switch($period) {
                                case 'today': echo 'Today'; break;
                                case 'yesterday': echo 'Yesterday'; break;
                                case 'week': echo 'This Week'; break;
                                case 'month': echo 'This Month'; break;
                                case 'year': echo 'This Year'; break;
                                case 'custom': echo date('M j', strtotime($customStart)) . ' - ' . date('M j', strtotime($customEnd)); break;
                            }
                            ?>
                        </strong>
                    </div>
                    <div class="col-6 mb-3">
                        <h6 class="text-muted">Generated</h6>
                        <strong><?php echo date('M j, Y H:i'); ?></strong>
                    </div>

                    <div class="col-6">
                        <h6 class="text-muted">Items Sold</h6>
                        <strong>
                            <?php 
                            $whereClause = '';
                            $params = [];
                            
                            switch ($period) {
                                case 'today':
                                    $whereClause = "WHERE DATE(o.order_date) = CURDATE() AND o.status != 'returned'";
                                    break;
                                case 'yesterday':
                                    $whereClause = "WHERE DATE(o.order_date) = DATE_SUB(CURDATE(), INTERVAL 1 DAY) AND o.status != 'returned'";
                                    break;
                                case 'week':
                                    $whereClause = "WHERE YEARWEEK(o.order_date) = YEARWEEK(CURDATE()) AND o.status != 'returned'";
                                    break;
                                case 'month':
                                    $whereClause = "WHERE MONTH(o.order_date) = MONTH(CURDATE()) AND YEAR(o.order_date) = YEAR(CURDATE()) AND o.status != 'returned'";
                                    break;
                                case 'year':
                                    $whereClause = "WHERE YEAR(o.order_date) = YEAR(CURDATE()) AND o.status != 'returned'";
                                    break;
                                case 'custom':
                                    if ($customStart && $customEnd) {
                                        $whereClause = "WHERE DATE(o.order_date) BETWEEN ? AND ? AND o.status != 'returned'";
                                        $params = [$customStart, $customEnd];
                                    }
                                    break;
                            }
                            
                            $itemsQuery = "SELECT COALESCE(SUM(oi.quantity - COALESCE(oi.quantity_returned, 0)), 0) as total_items
                            FROM order_items oi
                            JOIN orders o ON oi.order_id = o.id
                            $whereClause";
                            $itemsResult = executeQuery($itemsQuery, str_repeat('s', count($params)), $params);
                            echo $itemsResult ? $itemsResult[0]['total_items'] : 0;
                            ?>
                        </strong>
                    </div>
                    <?php if ($salesData['total_refunds'] > 0): ?>
<div class="col-12 mt-3">
    <div class="alert alert-warning">
        <i class="fas fa-info-circle"></i> 
        <strong>Note:</strong> Total refunds of <?php echo formatCurrency($salesData['total_refunds']); ?> 
        have been deducted from gross sales to show net sales of <?php echo formatCurrency($salesData['net_sales']); ?>
    </div>
</div>  
<?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
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
            // Auto-submit the form
            document.getElementById('reportForm').submit();
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
            document.getElementById('reportForm').submit();
        }
    });
}

// Create sales chart
const ctx = document.getElementById('salesChart');
if (ctx) {
    new Chart(ctx.getContext('2d'), {
        type: 'line',
        data: {
            labels: <?php echo json_encode($chartLabels); ?>,
            datasets: [{
                label: 'Daily Sales ($)',
                data: <?php echo json_encode($chartData); ?>,
                borderColor: 'rgb(102, 126, 234)',
                backgroundColor: 'rgba(102, 126, 234, 0.1)',
                tension: 0.4,
                fill: true
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    display: true
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return '$' + value.toFixed(2);
                        }
                    }
                }
            }
        }
    });
}
</script>

/* Additional icon styles */
.fa-percentage::before { content: "%"; }
</script>

<style>
/* Additional custom styles for reports */
.stats-card-danger {
    background: linear-gradient(135deg, #ff6b6b 0%, #ee5a52 100%);
    color: white;
}

.list-group-item {
    border-left: none;
    border-right: none;
}

.list-group-item:first-child {
    border-top: none;
}

.list-group-item:last-child {
    border-bottom: none;
}


</style>

<?php require_once '../includes/footer.php'; ?>