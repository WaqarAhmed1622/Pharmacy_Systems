<?php
/**
 * Export Functions
 * Updated version with better date range display for all periods
 * File: includes/export.php
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';


function exportOrdersToCSV($search = '', $period = 'all', $start = null, $end = null) {
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

    // Add period condition with actual date ranges
    switch ($period) {
        case 'today':
            $whereConditions[] = "DATE(o.order_date) = CURDATE()";
            $dateRangeStart = date('Y-m-d');
            $dateRangeEnd = date('Y-m-d');
            break;
        case 'yesterday':
            $whereConditions[] = "DATE(o.order_date) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)";
            $dateRangeStart = date('Y-m-d', strtotime('-1 day'));
            $dateRangeEnd = date('Y-m-d', strtotime('-1 day'));
            break;
        case 'week':
            $whereConditions[] = "YEARWEEK(o.order_date, 1) = YEARWEEK(CURDATE(), 1)";
            $dateRangeStart = date('Y-m-d', strtotime('monday this week'));
            $dateRangeEnd = date('Y-m-d', strtotime('sunday this week'));
            break;
        case 'month':
            $whereConditions[] = "MONTH(o.order_date) = MONTH(CURDATE()) AND YEAR(o.order_date) = YEAR(CURDATE())";
            $dateRangeStart = date('Y-m-01');
            $dateRangeEnd = date('Y-m-t');
            break;
        case 'year':
            $whereConditions[] = "YEAR(o.order_date) = YEAR(CURDATE())";
            $dateRangeStart = date('Y-01-01');
            $dateRangeEnd = date('Y-12-31');
            break;
        case 'custom':
            if ($start && $end) {
                $whereConditions[] = "DATE(o.order_date) BETWEEN ? AND ?";
                $searchParams[] = $start;
                $searchParams[] = $end;
                $searchTypes .= "ss";
                $dateRangeStart = $start;
                $dateRangeEnd = $end;
            }
            break;
        case 'all':
        default:
            // No date filter
            $dateRangeStart = null;
            $dateRangeEnd = null;
            break;
    }

    $whereClause = !empty($whereConditions) ? "WHERE " . implode(" AND ", $whereConditions) : "";
    
    $query = "SELECT o.*, u.full_name as cashier_name 
              FROM orders o 
              JOIN users u ON o.cashier_id = u.id 
              $whereClause
              ORDER BY o.order_date DESC";
    
    $orders = executeQuery($query, $searchTypes, $searchParams);
    
    // Generate period description for filename
    $periodDesc = '';
    switch($period) {
        case 'today': 
            $periodDesc = 'Today_' . date('d-m-Y'); 
            break;
        case 'yesterday': 
            $periodDesc = 'Yesterday_' . date('d-m-Y', strtotime('-1 day')); 
            break;
        case 'week': 
            $periodDesc = 'Week_' . date('d-m-Y'); 
            break;
        case 'month': 
            $periodDesc = 'Month_' . date('F_Y'); 
            break;
        case 'year': 
            $periodDesc = 'Year_' . date('Y'); 
            break;
        case 'custom': 
            $periodDesc = date('d-m-Y', strtotime($start)) . '_to_' . date('d-m-Y', strtotime($end)); 
            break;
        default: 
            $periodDesc = 'All_Time'; 
            break;
    }
    
    // Set headers for file download
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=Orders_Report_' . $periodDesc . '.csv');
    
    // Create file pointer
    $output = fopen('php://output', 'w');
    
    // Add BOM for UTF-8
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Add report header
    fputcsv($output, ['Orders Report']);
    
    // Add period information with date range
    $periodLabel = '';
    switch($period) {
        case 'today': 
            $periodLabel = 'Period: Today (' . date('d/m/Y', strtotime($dateRangeStart)) . ')'; 
            break;
        case 'yesterday': 
            $periodLabel = 'Period: Yesterday (' . date('d/m/Y', strtotime($dateRangeStart)) . ')'; 
            break;
        case 'week': 
            $periodLabel = 'Period: This Week (' . date('d/m/Y', strtotime($dateRangeStart)) . ' to ' . date('d/m/Y', strtotime($dateRangeEnd)) . ')'; 
            break;
        case 'month': 
            $periodLabel = 'Period: ' . date('F Y') . ' (' . date('d/m/Y', strtotime($dateRangeStart)) . ' to ' . date('d/m/Y', strtotime($dateRangeEnd)) . ')'; 
            break;
        case 'year': 
            $periodLabel = 'Period: Year ' . date('Y') . ' (' . date('d/m/Y', strtotime($dateRangeStart)) . ' to ' . date('d/m/Y', strtotime($dateRangeEnd)) . ')'; 
            break;
        case 'custom': 
            $periodLabel = 'Period: Custom Range (' . date('d/m/Y', strtotime($start)) . ' to ' . date('d/m/Y', strtotime($end)) . ')'; 
            break;
        default: 
            $periodLabel = 'Period: All Time'; 
            break;
    }
    
    fputcsv($output, [$periodLabel]);
    fputcsv($output, ['Generated: ' . date('d/m/Y H:i:s')]);
    fputcsv($output, []); // Empty row
    
    // Column headers
    fputcsv($output, [
        'Order Number',
        'Date',
        'Cashier',
        'Subtotal',
        'Discount',
        'Tax',
        'Total Amount',
        'Payment Method',
        'Status'
    ]);
    
    // Data rows
    foreach ($orders as $order) {
        fputcsv($output, [
            $order['order_number'],
            date('d/m/Y H:i', strtotime($order['order_date'])),
            $order['cashier_name'],
            number_format($order['subtotal'], 2),
            number_format($order['discount_amount'] ?? 0, 2),
            number_format($order['tax_amount'], 2),
            number_format($order['total_amount'], 2),
            ucfirst($order['payment_method']),
            ucfirst($order['status'])
        ]);
    }
    
    // Add summary at the end
    fputcsv($output, []); // Empty row
    fputcsv($output, ['Summary']);
    fputcsv($output, ['Total Orders', count($orders)]);
    
    $totalSales = array_sum(array_column($orders, 'total_amount'));
    fputcsv($output, ['Total Sales', number_format($totalSales, 2)]);
    
    fclose($output);
    exit();
}

function exportSalesReportToCSV($period, $start = null, $end = null) {
    // Get sales data using the same logic as reports.php
    $salesData = getSalesData($period, $start, $end);
    
    // Calculate date ranges for all periods
    switch($period) {
        case 'today':
            $dateRangeStart = date('Y-m-d');
            $dateRangeEnd = date('Y-m-d');
            break;
        case 'yesterday':
            $dateRangeStart = date('Y-m-d', strtotime('-1 day'));
            $dateRangeEnd = date('Y-m-d', strtotime('-1 day'));
            break;
        case 'week':
            $dateRangeStart = date('Y-m-d', strtotime('monday this week'));
            $dateRangeEnd = date('Y-m-d', strtotime('sunday this week'));
            break;
        case 'month':
            $dateRangeStart = date('Y-m-01');
            $dateRangeEnd = date('Y-m-t');
            break;
        case 'year':
            $dateRangeStart = date('Y-01-01');
            $dateRangeEnd = date('Y-12-31');
            break;
        case 'custom':
            $dateRangeStart = $start;
            $dateRangeEnd = $end;
            break;
        default:
            $dateRangeStart = null;
            $dateRangeEnd = null;
            break;
    }
    
    // Generate period description
    $periodDesc = '';
    switch($period) {
        case 'today': 
            $periodDesc = 'Today_' . date('d-m-Y'); 
            break;
        case 'yesterday': 
            $periodDesc = 'Yesterday_' . date('d-m-Y', strtotime('-1 day')); 
            break;
        case 'week': 
            $periodDesc = 'Week_' . date('d-m-Y'); 
            break;
        case 'month': 
            $periodDesc = 'Month_' . date('F_Y'); 
            break;
        case 'year': 
            $periodDesc = 'Year_' . date('Y'); 
            break;
        case 'custom': 
            $periodDesc = date('d-m-Y', strtotime($start)) . '_to_' . date('d-m-Y', strtotime($end)); 
            break;
    }
    
    // Set headers
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=Sales_Report_' . $periodDesc . '.csv');
    
    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Report header
    fputcsv($output, ['Sales Report']);
    
    // Period information with date range
    $periodLabel = '';
    switch($period) {
        case 'today': 
            $periodLabel = 'Period: Today (' . date('d/m/Y', strtotime($dateRangeStart)) . ')'; 
            break;
        case 'yesterday': 
            $periodLabel = 'Period: Yesterday (' . date('d/m/Y', strtotime($dateRangeStart)) . ')'; 
            break;
        case 'week': 
            $periodLabel = 'Period: This Week (' . date('d/m/Y', strtotime($dateRangeStart)) . ' to ' . date('d/m/Y', strtotime($dateRangeEnd)) . ')'; 
            break;
        case 'month': 
            $periodLabel = 'Period: ' . date('F Y') . ' (' . date('d/m/Y', strtotime($dateRangeStart)) . ' to ' . date('d/m/Y', strtotime($dateRangeEnd)) . ')'; 
            break;
        case 'year': 
            $periodLabel = 'Period: Year ' . date('Y') . ' (' . date('d/m/Y', strtotime($dateRangeStart)) . ' to ' . date('d/m/Y', strtotime($dateRangeEnd)) . ')'; 
            break;
        case 'custom': 
            $periodLabel = 'Period: Custom Range (' . date('d/m/Y', strtotime($start)) . ' to ' . date('d/m/Y', strtotime($end)) . ')'; 
            break;
        default: 
            $periodLabel = 'Period: All Time'; 
            break;
    }
    
    fputcsv($output, [$periodLabel]);
    fputcsv($output, ['Generated: ' . date('d/m/Y H:i:s')]);
    fputcsv($output, []);
    
    // Sales Summary
    fputcsv($output, ['Sales Summary']);
    fputcsv($output, ['Metric', 'Value']);
    fputcsv($output, ['Total Orders', $salesData['total_orders']]);
    fputcsv($output, ['Total Sales', number_format($salesData['total_sales'], 2)]);
    fputcsv($output, ['Average Order Value', number_format($salesData['avg_order_value'], 2)]);
    fputcsv($output, ['Total Tax', number_format($salesData['total_tax'], 2)]);
    
    fputcsv($output, []);
    
    // Top Products
    $topProducts = getTopProducts($period, $start, $end, 10);
    fputcsv($output, ['Top Products']);
    fputcsv($output, ['Product Name', 'Barcode', 'Quantity Sold', 'Total Revenue']);
    
    foreach ($topProducts as $product) {
        fputcsv($output, [
            $product['name'],
            $product['barcode'],
            $product['total_sold'],
            number_format($product['total_revenue'], 2)
        ]);
    }
    
    fclose($output);
    exit();
}

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
?>