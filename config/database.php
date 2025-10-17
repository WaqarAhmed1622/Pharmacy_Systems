<?php
/**
 * Database Configuration File
 * Contains database connection settings and connection function
 */

// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'pharmacy_system');

/**
 * Create database connection
 * @return mysqli Database connection object
 */
function getConnection() {
    $connection = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    // Check connection
    if ($connection->connect_error) {
        die("Connection failed: " . $connection->connect_error);
    }
    
    // Set charset to utf8
    $connection->set_charset("utf8");
    
    return $connection;
}

/**
 * Execute a prepared statement and return results
 * @param string $query SQL query with placeholders
 * @param string $types Parameter types (e.g., 'ssi' for string, string, int)
 * @param array $params Parameters to bind
 * @return array|bool Query results or false on failure
 */
function executeQuery($query, $types = '', $params = []) {
    $conn = getConnection();
    $stmt = $conn->prepare($query);
    
    if (!$stmt) {
        return false;
    }
    
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result) {
        $data = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        $conn->close();
        return $data;
    }
    
    $stmt->close();
    $conn->close();
    return false;
}

/**
 * Execute insert/update/delete query
 * @param string $query SQL query
 * @param string $types Parameter types
 * @param array $params Parameters to bind
 * @return bool|int Success status or insert ID for INSERT queries
 */
function executeNonQuery($query, $types = '', $params = []) {
    $conn = getConnection();
    $stmt = $conn->prepare($query);
    
    if (!$stmt) {
        return false;
    }
    
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    $success = $stmt->execute();
    
    if ($success) {
        $insertId = $conn->insert_id;
        $stmt->close();
        $conn->close();
        return $insertId > 0 ? $insertId : true;
    }
    
    $stmt->close();
    $conn->close();
    return false;
}
?>