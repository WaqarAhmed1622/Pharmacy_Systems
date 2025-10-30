<?php
/**
 * Dashboard Page
 * Main dashboard with statistics and overview
 */

require_once '../includes/header.php';

// Get statistics
$todayStats = getSalesStats('today');
$monthStats = getSalesStats('month');
$topProducts = getTopSellingProducts(5);

// Get product count
$productCount = executeQuery("SELECT COUNT(*) as count FROM products");
$productCount = $productCount[0]['count'];

// Get user count (admin only)
$userCount = 0;
if (isAdmin()) {
    $userCountResult = executeQuery("SELECT COUNT(*) as count FROM users WHERE is_active = 1");

    $userCount = $userCountResult[0]['count'];
}

// Initialize expiringProducts to avoid undefined variable warning
$expiringProducts = [];
?>

<!-- Statistics Cards -->
<div class="row mb-4">
    <div class="col-md-3 mb-3">
        <div class="card stats-card h-100">
            <div class="card-body text-center">
                <i class="fas fa-dollar-sign fa-2x mb-2"></i>
                <h5 class="card-title"><?php echo formatCurrency($todayStats['total_sales']); ?></h5>
                <p class="card-text">Today's Sales</p>
                <small><?php echo $todayStats['total_orders']; ?> orders</small>
            </div>
        </div>
    </div>
    
    <div class="col-md-3 mb-3">
        <div class="card stats-card-success h-100">
            <div class="card-body text-center">
                <i class="fas fa-chart-line fa-2x mb-2"></i>
                <h5 class="card-title"><?php echo formatCurrency($monthStats['total_sales']); ?></h5>
                <p class="card-text">This Month</p>
                <small><?php echo $monthStats['total_orders']; ?> orders</small>
            </div>
        </div>
    </div>
    
    <div class="col-md-3 mb-3">
        <div class="card stats-card-warning h-100">
            <div class="card-body text-center">
                <i class="fas fa-box fa-2x mb-2"></i>
                <h5 class="card-title"><?php echo $productCount; ?></h5>
                <p class="card-text">Total Products</p>
            </div>
        </div>
    </div>
    
    <?php if (isAdmin()): ?>
    <div class="col-md-3 mb-3">
        <div class="card stats-card-info h-100">
            <div class="card-body text-center">
                <i class="fas fa-users fa-2x mb-2"></i>
                <h5 class="card-title"><?php echo $userCount; ?></h5>
                <p class="card-text">Active Users</p>
                <small>System users</small>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<div class="row">
    <!-- Quick Actions -->
    <div class="col-md-3 mb-4">
        <div class="card h-100">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-bolt"></i> Quick Actions
                </h5>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <a href="pos.php" class="btn btn-primary">
                        <i class="fas fa-cash-register"></i> New Sale
                    </a>
                    
                    <?php if (isAdmin()): ?>
                    <a href="products.php" class="btn btn-success">
                        <i class="fas fa-plus"></i> Add Product
                    </a>
                    
                    <a href="inventory.php" class="btn btn-warning">
                        <i class="fas fa-warehouse"></i> Manage Inventory
                    </a>
                    
                    <a href="sales_report.php" class="btn btn-info">
                        <i class="fas fa-chart-bar"></i> Sales Report
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Top Selling Products -->
    <div class="col-md-4 mb-4">
        <div class="card h-100">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-star"></i> Top Selling Products
                </h5>
            </div>
            <div class="card-body">
                <?php if (empty($topProducts)): ?>
                    <p class="text-muted">No sales data available</p>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($topProducts as $product): ?>
                            <div class="list-group-item d-flex justify-content-between align-items-center px-0">
                                <div>
                                    <strong><?php echo sanitizeInput($product['name']); ?></strong>
                                    <br>
                                    <small class="text-muted"><?php echo sanitizeInput($product['barcode']); ?></small>
                                </div>
                                <div class="text-end">
                                    <span class="badge bg-primary"><?php echo $product['total_sold']; ?> sold</span>
                                    <br>
                                    <small class="text-success"><?php echo formatCurrency($product['total_revenue']); ?></small>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    


<?php if (isAdmin()): ?>
<!-- Sales Chart -->
<div class="card mb-4">
    <div class="card-header">
        <h5 class="card-title mb-0"><i class="fas fa-chart-line"></i> Sales (Last 7 Days)</h5>
    </div>
    <div class="card-body">
        <canvas id="salesChart" height="120"></canvas>
    </div>
</div>
<?php
$salesChartData = [];
$labels = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $dateLabel = date('M d', strtotime("-$i days"));
    $dayStats = executeQuery("SELECT COALESCE(SUM(total_amount), 0) as sales FROM orders WHERE DATE(order_date) = ?", 's', [$date]);
    $salesAmount = $dayStats ? $dayStats[0]['sales'] : 0;
    $salesChartData[] = $salesAmount;
    $labels[] = $dateLabel;
}
?>
<script>
const ctx = document.getElementById('salesChart').getContext('2d');
const salesChart = new Chart(ctx, {
    type: 'line',
    data: {
        labels: <?php echo json_encode($labels); ?>,
        datasets: [{
            label: 'Daily Sales ($)',
            data: <?php echo json_encode($salesChartData); ?>,
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
</script>
<?php endif; ?>

<?php require_once '../includes/footer.php'; ?>