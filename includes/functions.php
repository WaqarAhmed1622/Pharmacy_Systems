<?php
/**
 * General Helper Functions
 * Contains utility functions used throughout the application
 */

// Determine the correct path to config based on current file location
$config_path = '';
if (strpos(__FILE__, 'includes') !== false) {
    // Called from includes directory
    $config_path = dirname(__DIR__) . '/config/database.php';
} else {
    // Called from root or other directory
    $config_path = 'config/database.php';
}

// Include database config if it exists
if (file_exists($config_path)) {
    require_once $config_path;
} elseif (file_exists('../config/database.php')) {
    require_once '../config/database.php';
} elseif (file_exists('config/database.php')) {
    require_once 'config/database.php';
} else {
    die('Database configuration file not found. Please ensure config/database.php exists.');
}

/**
 * Format currency value in Pakistani Rupees
 * @param float $amount Amount to format
 * @return string Formatted currency
 */
function formatCurrency($amount) {
    return 'Rs ' . number_format($amount, 2);
}

/**
 * Format date for display
 * @param string $date Date string
 * @param string $format Date format
 * @return string Formatted date
 */
function formatDate($date, $format = 'M d, Y H:i') {
    return date($format, strtotime($date));
}

/**
 * Generate unique order number
 * @return string Order number
 */
function generateOrderNumber() {
    return 'ORD-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
}

/**
 * Sanitize input data
 * @param string $data Input data
 * @return string Sanitized data
 */
function sanitizeInput($data) {
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

/**
 * Check if barcode is unique
 * @param string $barcode Barcode to check
 * @param int $excludeId Product ID to exclude (for updates)
 * @return bool True if unique, false otherwise
 */
function isBarcodeUnique($barcode, $excludeId = null) {
    $query = "SELECT id FROM products WHERE barcode = ?";
    $params = [$barcode];
    $types = 's';
    
    if ($excludeId) {
        $query .= " AND id != ?";
        $params[] = $excludeId;
        $types .= 'i';
    }
    
    $result = executeQuery($query, $types, $params);
    return empty($result);
}

/**
 * Get low stock products
 * @return array Array of products with low stock
 */
function getLowStockProducts() {
    $query = "SELECT p.*, c.name as category_name 
              FROM products p 
              LEFT JOIN categories c ON p.category_id = c.id 
              WHERE p.stock_quantity <= p.min_stock_level 
              ORDER BY p.stock_quantity ASC";
    
    return executeQuery($query);
}

/**
 * Get sales statistics
 * @param string $period Period for statistics (today, week, month, year)
 * @return array Sales statistics
 */
function getSalesStats($period = 'today') {
    $whereClause = '';
    
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
        default:
            $whereClause = "WHERE DATE(order_date) = CURDATE() AND status != 'returned'";
    }
    
    $query = "SELECT 
                COUNT(*) as total_orders,
                COALESCE(SUM(total_amount), 0) as total_sales,
                COALESCE(AVG(total_amount), 0) as avg_order_value
              FROM orders $whereClause";
    
    $result = executeQuery($query);
    return $result ? $result[0] : ['total_orders' => 0, 'total_sales' => 0, 'avg_order_value' => 0];
}

/**
 * Get top selling products
 * @param int $limit Number of products to return
 * @return array Top selling products
 */
function getTopSellingProducts($limit = 5) {
    $query = "SELECT 
                p.name,
                p.barcode,
                SUM(oi.quantity) as total_sold,
                SUM(oi.total_price) as total_revenue
              FROM order_items oi
              JOIN products p ON oi.product_id = p.id
              GROUP BY p.id, p.name, p.barcode
              ORDER BY total_sold DESC
              LIMIT ?";
    
    return executeQuery($query, 'i', [$limit]);
}

/**
 * Update product stock
 * @param int $productId Product ID
 * @param int $quantity Quantity to subtract
 * @return bool Success status
 */
function updateProductStock($productId, $quantity) {
    $query = "UPDATE products SET stock_quantity = stock_quantity - ? WHERE id = ?";
    return executeNonQuery($query, 'ii', [$quantity, $productId]);
}

/**
 * Get product by barcode
 * @param string $barcode Product barcode
 * @return array|false Product data or false
 */
function getProductByBarcode($barcode) {
    $query = "SELECT p.*, c.name as category_name 
              FROM products p 
              LEFT JOIN categories c ON p.category_id = c.id 
              WHERE p.barcode = ?";
    
    $result = executeQuery($query, 's', [$barcode]);
    return $result ? $result[0] : false;
}

/**
 * Get all categories
 * @return array Categories array
 */
function getAllCategories() {
    $query = "SELECT * FROM categories ORDER BY name";
    return executeQuery($query);
}

/**
 * Get all products with category info
 * @return array Products array
 */
function getAllProducts() {
    $query = "SELECT p.*, c.name as category_name 
              FROM products p 
              LEFT JOIN categories c ON p.category_id = c.id 
              ORDER BY p.name";
    
    return executeQuery($query);
}

/**
 * Validate email format
 * @param string $email Email to validate
 * @return bool True if valid, false otherwise
 */
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Check if username is unique
 * @param string $username Username to check
 * @param int $excludeId User ID to exclude (for updates)
 * @return bool True if unique, false otherwise
 */
function isUsernameUnique($username, $excludeId = null) {
    $query = "SELECT id FROM users WHERE username = ?";
    $params = [$username];
    $types = 's';
    
    if ($excludeId) {
        $query .= " AND id != ?";
        $params[] = $excludeId;
        $types .= 'i';
    }
    
    $result = executeQuery($query, $types, $params);
    return empty($result);
}

function getSetting(string $key, $default = null) {
    $conn = getConnection();
    $query = "SELECT setting_value FROM settings WHERE setting_key = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $key);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $conn->close();
    return $result ? $result['setting_value'] : $default;
}

function setSetting(string $key, $value) {
    $conn = getConnection();
    $query = "INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
              ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ss", $key, $value);
    $stmt->execute();
    $stmt->close();
    $conn->close();
}

/**
 * Calculate tax amount
 * @param float $subtotal Subtotal amount
 * @param float $taxRate Tax rate (default 10%)
 * @return float Tax amount
 */
function calculateTax(float $amount): float {
    $taxRate = (float) getSetting('tax_rate', 0.10); // default 10% if not set
    return $amount * $taxRate;
}

/**
 * Calculate discount amount
 * @param float $amount Amount to apply discount on
 * @return float Discount amount
 */
function calculateDiscount(float $amount): float {
    $discountRate = (float) getSetting('discount_rate', 0); // default 0% if not set
    return $amount * $discountRate;
}

/**
 * Log system activity (basic logging)
 * @param string $action Action performed
 * @param int $userId User ID
 * @param string $details Additional details
 */
function logActivity($action, $userId, $details = '') {
    // This is a basic implementation. In production, you might want to store logs in database
    $logEntry = date('Y-m-d H:i:s') . " - User $userId: $action - $details" . PHP_EOL;
    file_put_contents('../logs/activity.log', $logEntry, FILE_APPEND | LOCK_EX);
}
?>