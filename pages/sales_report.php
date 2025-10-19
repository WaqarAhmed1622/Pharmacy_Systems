<?php
/**
 * Sales Reports Page
 * Admin only - View sales analytics and reports
 */

require_once '../config/database.php';
require_once '../includes/export.php';

function getSalesData($period, $start = null, $end = null) {
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
            if ($start && $end) {
                $whereClause = "WHERE DATE(order_date) BETWEEN ? AND ? AND status != 'returned'";
                $params = [$start, $end];
            }
            break;
    }
    
    $query = "SELECT 
                COUNT(*) as total_orders,
                COALESCE(SUM(total_amount), 0) as total_sales,
                COALESCE(AVG(total_amount), 0) as avg_order_value,
                COALESCE(SUM(subtotal), 0) as total_subtotal,
                COALESCE(SUM(tax_amount), 0) as total_tax
              FROM orders $whereClause";
    
    $result = executeQuery($query, str_repeat('s', count($params)), $params);
    return $result ? $result[0] : ['total_orders' => 0, 'total_sales' => 0, 'avg_order_value' => 0, 'total_subtotal' => 0, 'total_tax' => 0];
}

// Get top products for the period
function getTopProducts($period, $start = null, $end = null, $limit = 10) {
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
            if ($start && $end) {
                $whereClause = "WHERE DATE(o.order_date) BETWEEN ? AND ? AND o.status != 'returned'";
                $params = [$start, $end];
            }
            break;
    }
    
    $query = "SELECT 
                p.name,
                p.barcode,
                SUM(oi.quantity) as total_sold,
                SUM(oi.total_price) as total_revenue,
                AVG(oi.unit_price) as avg_price
              FROM order_items oi
              JOIN products p ON oi.product_id = p.id
              JOIN orders o ON oi.order_id = o.id
              $whereClause
              GROUP BY p.id, p.name, p.barcode
              ORDER BY total_sold DESC
              LIMIT $limit";
    
    return executeQuery($query, str_repeat('s', count($params)), $params);
}

$period = isset($_GET['period']) ? $_GET['period'] : 'today';
$customStart = isset($_GET['start']) ? $_GET['start'] : date('Y-m-d');
$customEnd = isset($_GET['end']) ? $_GET['end'] : date('Y-m-d');


// Handle export
if (isset($_GET['export'])) {
    exportSalesReportToCSV($period, $customStart, $customEnd);
}

require_once '../includes/header.php';
requireAdmin();


$salesData = getSalesData($period, $customStart, $customEnd);
$topProducts = getTopProducts($period, $customStart, $customEnd);

// Calculate actual profit using product cost prices
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

$costQuery = "SELECT COALESCE(SUM(oi.quantity * p.cost), 0) as total_cost
              FROM order_items oi
              JOIN products p ON oi.product_id = p.id
              JOIN orders o ON oi.order_id = o.id
              $whereClause";

$costResult = executeQuery($costQuery, str_repeat('s', count($params)), $params);
$totalCost = $costResult ? $costResult[0]['total_cost'] : 0;
$totalProfit = $salesData['total_sales'] - $totalCost;
$profitMargin = $salesData['total_sales'] > 0 ? (($totalProfit / $salesData['total_sales']) * 100) : 0;

// Get daily sales for chart (last 30 days)
$chartData = [];
$chartLabels = [];
for ($i = 29; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $dateLabel = date('M j', strtotime("-$i days"));
    
    $dayStats = executeQuery("SELECT COALESCE(SUM(total_amount), 0) as sales FROM orders WHERE DATE(order_date) = ?", 's', [$date]);
    $salesAmount = $dayStats ? $dayStats[0]['sales'] : 0;
    
    $chartData[] = $salesAmount;
    $chartLabels[] = $dateLabel;
}
?>

<!-- Period Selection -->
<!-- Period Selection -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">
                    <i class="fas fa-chart-line"></i> Sales Reports
                </h5>
                <a href="?period=<?php echo $period; ?>&start=<?php echo $customStart; ?>&end=<?php echo $customEnd; ?>&export=1" class="btn btn-success btn-sm">
                    <i class="fas fa-download"></i> Export Report
                </a>
            </div>
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Report Period</label>
                        <select name="period" class="form-select" onchange="toggleCustomDates(this.value)">
                            <option value="today" <?php echo $period == 'today' ? 'selected' : ''; ?>>Today</option>
                            <option value="yesterday" <?php echo $period == 'yesterday' ? 'selected' : ''; ?>>Yesterday</option>
                            <option value="week" <?php echo $period == 'week' ? 'selected' : ''; ?>>This Week</option>
                            <option value="month" <?php echo $period == 'month' ? 'selected' : ''; ?>>This Month</option>
                            <option value="year" <?php echo $period == 'year' ? 'selected' : ''; ?>>This Year</option>
                            <option value="custom" <?php echo $period == 'custom' ? 'selected' : ''; ?>>Custom Range</option>
                        </select>
                    </div>
                    
                    <div class="col-md-3" id="startDate" style="display: <?php echo $period == 'custom' ? 'block' : 'none'; ?>">
                        <label class="form-label">Start Date</label>
                        <input type="date" name="start" class="form-control" value="<?php echo $customStart; ?>">
                    </div>
                    
                    <div class="col-md-3" id="endDate" style="display: <?php echo $period == 'custom' ? 'block' : 'none'; ?>">
                        <label class="form-label">End Date</label>
                        <input type="date" name="end" class="form-control" value="<?php echo $customEnd; ?>">
                    </div>
                    
                    <div class="col-md-3 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i> Generate Report
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Sales Statistics -->
<!-- Sales Statistics -->
<div class="row mb-4">
    <div class="col-md-3 mb-3">
        <div class="card stats-card h-100">
            <div class="card-body text-center">
                <i class="fas fa-dollar-sign fa-2x mb-2"></i>
                <h4 class="card-title"><?php echo formatCurrency($salesData['total_sales']); ?></h4>
                <p class="card-text">Total Sales</p>
            </div>
        </div>
    </div>
    
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

    <div class="col-md-3 mb-3">
        <div class="card stats-card-profit h-100">
            <div class="card-body text-center">
                <i class="fas fa-money-bill-wave fa-2x mb-2"></i>
                <h4 class="card-title"><?php echo formatCurrency($totalProfit); ?></h4>
                <p class="card-text">Net Profit</p>
                <small class="text-white"><?php echo round($profitMargin, 2); ?>% margin</small>
            </div>
        </div>
    </div>

    <div class="col-md-3 mb-3">
        <div class="card stats-card-cost h-100">
            <div class="card-body text-center">
                <i class="fas fa-box fa-2x mb-2"></i>
                <h4 class="card-title"><?php echo formatCurrency($totalCost); ?></h4>
                <p class="card-text">Total Cost</p>
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
                        $whereClause = "WHERE DATE(order_date) = CURDATE()";
                        break;
                    case 'yesterday':
                        $whereClause = "WHERE DATE(order_date) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)";
                        break;
                    case 'week':
                        $whereClause = "WHERE YEARWEEK(order_date) = YEARWEEK(CURDATE())";
                        break;
                    case 'month':
                        $whereClause = "WHERE MONTH(order_date) = MONTH(CURDATE()) AND YEAR(order_date) = YEAR(CURDATE())";
                        break;
                    case 'year':
                        $whereClause = "WHERE YEAR(order_date) = YEAR(CURDATE())";
                        break;
                    case 'custom':
                        if ($customStart && $customEnd) {
                            $whereClause = "WHERE DATE(order_date) BETWEEN ? AND ?";
                            $params = [$customStart, $customEnd];
                        }
                        break;
                }
                
                $paymentQuery = "SELECT 
                                    payment_method,
                                    COUNT(*) as count,
                                    SUM(total_amount) as total
                                FROM orders 
                                $whereClause
                                GROUP BY payment_method
                                ORDER BY total DESC";
                
                $paymentMethods = executeQuery($paymentQuery, str_repeat('s', count($params)), $params);
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
                        <h6 class="text-muted">Gross Profit</h6>
                        <strong class="text-success">
                            <?php 
                            // Calculate estimated profit (this is a rough estimate)
                            $estimatedProfit = $salesData['total_subtotal'] * 0.3; // Assuming 30% margin
                            echo formatCurrency($estimatedProfit);
                            ?>
                        </strong>
                    </div>
                    <div class="col-6">
                        <h6 class="text-muted">Items Sold</h6>
                        <strong>
                            <?php 
                            $itemsQuery = "SELECT SUM(oi.quantity) as total_items
                                          FROM order_items oi
                                          JOIN orders o ON oi.order_id = o.id
                                          $whereClause";
                            $itemsResult = executeQuery($itemsQuery, str_repeat('s', count($params)), $params);
                            echo $itemsResult ? $itemsResult[0]['total_items'] : 0;
                            ?>
                        </strong>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Toggle custom date fields
function toggleCustomDates(period) {
    const startDate = document.getElementById('startDate');
    const endDate = document.getElementById('endDate');
    
    if (period === 'custom') {
        startDate.style.display = 'block';
        endDate.style.display = 'block';
    } else {
        startDate.style.display = 'none';
        endDate.style.display = 'none';
    }
}

// Create sales chart
const ctx = document.getElementById('salesChart').getContext('2d');
new Chart(ctx, {
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

.stats-card-profit {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    color: white;
}

.stats-card-profit .card-body i,
.stats-card-profit .card-title,
.stats-card-profit .card-text {
    color: white;
}

.stats-card-cost {
    background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
    color: white;
}

.stats-card-cost .card-body i,
.stats-card-cost .card-title,
.stats-card-cost .card-text {
    color: white;
}
</style>

<?php require_once '../includes/footer.php'; ?>