<?php
/**
 * Export Functions
 * Add this to a new file: includes/export.php
 */

function exportOrdersToCSV($search = '', $format = 'csv') {
    $whereClause = "";
    $searchParams = [];
    $searchTypes = "";
    
    if (!empty($search)) {
        $whereClause = "WHERE (o.order_number LIKE ? OR u.full_name LIKE ?)";
        $searchParams = ["%$search%", "%$search%"];
        $searchTypes = "ss";
    }
    
    $query = "SELECT o.*, u.full_name as cashier_name 
              FROM orders o 
              JOIN users u ON o.cashier_id = u.id 
              $whereClause
              ORDER BY o.order_date DESC";
    
    $orders = executeQuery($query, $searchTypes, $searchParams);
    
    $filename = 'orders_' . date('Y-m-d_H-i-s') . '.csv';
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    $output = fopen('php://output', 'w');
    
    // Add BOM for Excel UTF-8 compatibility
    fwrite($output, "\xEF\xBB\xBF");
    
    // Header row
    fputcsv($output, [
        'Order Number',
        'Order Date',
        'Cashier Name',
        'Subtotal',
        'Discount Amount',
        'Tax Amount',
        'Total Amount',
        'Payment Method',
        'Status',
        'Refund Amount'
    ]);
    
    // Data rows
    foreach ($orders as $order) {
        fputcsv($output, [
            $order['order_number'],
            $order['order_date'],
            $order['cashier_name'],
            $order['subtotal'],
            $order['discount_amount'] ?? 0,
            $order['tax_amount'],
            $order['total_amount'],
            ucfirst($order['payment_method']),
            ucfirst($order['status']),
            $order['refund_amount'] ?? 0
        ]);
    }
    
    fclose($output);
    exit;
}

function exportSalesReportToCSV($period, $customStart = null, $customEnd = null) {
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
    
    // Get sales summary with cost calculation
    $summaryQuery = "SELECT 
                        COUNT(*) as total_orders,
                        COALESCE(SUM(o.total_amount), 0) as total_sales,
                        COALESCE(AVG(o.total_amount), 0) as avg_order_value,
                        COALESCE(SUM(o.subtotal), 0) as total_subtotal,
                        COALESCE(SUM(o.tax_amount), 0) as total_tax,
                        COALESCE(SUM(oi.quantity * p.cost), 0) as total_cost
                    FROM orders o 
                    LEFT JOIN order_items oi ON o.id = oi.order_id
                    LEFT JOIN products p ON oi.product_id = p.id
                    $whereClause";
    
    $summaryResult = executeQuery($summaryQuery, str_repeat('s', count($params)), $params);
    $summary = $summaryResult ? $summaryResult[0] : null;
    
    // Get top products
    $productQuery = "SELECT 
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
                    LIMIT 20";
    
    $topProducts = executeQuery($productQuery, str_repeat('s', count($params)), $params);
    
    // Get payment methods
    $paymentQuery = "SELECT 
                        payment_method,
                        COUNT(*) as count,
                        SUM(total_amount) as total
                    FROM orders o
                    $whereClause
                    GROUP BY payment_method
                    ORDER BY total DESC";
    
    $paymentMethods = executeQuery($paymentQuery, str_repeat('s', count($params)), $params);
    
    $filename = 'sales_report_' . date('Y-m-d_H-i-s') . '.csv';
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    $output = fopen('php://output', 'w');
    fwrite($output, "\xEF\xBB\xBF");
    
    // Sales Summary Section
    fputcsv($output, ['SALES REPORT']);
    fputcsv($output, ['Generated', date('Y-m-d H:i:s')]);
    fputcsv($output, ['Period', ucfirst($period)]);
    fputcsv($output, []);
    
    fputcsv($output, ['SUMMARY']);
    fputcsv($output, ['Metric', 'Value']);
    fputcsv($output, ['Total Sales', $summary['total_sales']]);
    fputcsv($output, ['Total Cost', $summary['total_cost']]);
    $profit = $summary['total_sales'] - $summary['total_cost'];
    $profitMargin = $summary['total_sales'] > 0 ? (($profit / $summary['total_sales']) * 100) : 0;
    fputcsv($output, ['Profit', $profit]);
    fputcsv($output, ['Profit Margin (%)', round($profitMargin, 2) . '%']);
    fputcsv($output, ['Total Orders', $summary['total_orders']]);
    fputcsv($output, ['Average Order Value', $summary['avg_order_value']]);
    fputcsv($output, ['Total Tax', $summary['total_tax']]);
    fputcsv($output, []);
    
    // Payment Methods Section
    fputcsv($output, ['PAYMENT METHODS']);
    fputcsv($output, ['Method', 'Orders', 'Total']);
    foreach ($paymentMethods as $method) {
        fputcsv($output, [
            ucfirst($method['payment_method']),
            $method['count'],
            $method['total']
        ]);
    }
    fputcsv($output, []);
    
    // Top Products Section
    fputcsv($output, ['TOP PRODUCTS']);
    fputcsv($output, ['Product Name', 'Barcode', 'Quantity Sold', 'Revenue', 'Avg Price']);
    foreach ($topProducts as $product) {
        fputcsv($output, [
            $product['name'],
            $product['barcode'],
            $product['total_sold'],
            $product['total_revenue'],
            $product['avg_price']
        ]);
    }
    
    fclose($output);
    exit;
}
?>