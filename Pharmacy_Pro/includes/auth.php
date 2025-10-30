<?php
/**
 * Authentication Helper Functions
 * Handles user authentication, session management, and authorization
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

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

/**
 * Check if user is logged in
 * @return bool True if logged in, false otherwise
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && isset($_SESSION['username']);
}

/**
 * Check if user has admin role
 * @return bool True if admin, false otherwise
 */
function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

/**
 * Require login - redirect to login page if not authenticated
 */
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit;
    }
}

/**
 * Require admin role - redirect with error if not admin
 */
function requireAdmin() {
    requireLogin();
    if (!isAdmin()) {
        $_SESSION['error'] = 'Access denied. Admin privileges required.';
        header('Location: dashboard.php');
        exit;
    }
}

/**
 * Authenticate user login
 * @param string $username Username
 * @param string $password Password
 * @return array|false User data on success, false on failure
 */
function authenticateUser($username, $password) {
    $query = "SELECT id, username, email, password, role, full_name FROM users 
              WHERE username = ? AND is_active = 1";
    $users = executeQuery($query, 's', [$username]);
    
    if ($users && count($users) > 0) {
        $user = $users[0];
        if (password_verify($password, $user['password'])) {
            return $user;
        }
    }
    
    return false;
}

/**
 * Login user and create session
 * @param array $user User data
 */
function loginUser($user) {
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['email'] = $user['email'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['full_name'] = $user['full_name'];
    $_SESSION['last_activity'] = time();
}

/**
 * Logout user and destroy session
 */
function logoutUser() {
    session_unset();
    session_destroy();
    header('Location: ../login.php');
    exit;
}

/**
 * Check session timeout (30 minutes)
 */
function checkSessionTimeout() {
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 1800)) {
        session_unset();
        session_destroy();
        header('Location: login.php?timeout=1');
        exit;
    }
    $_SESSION['last_activity'] = time();
}

/**
 * Generate CSRF token
 * @return string CSRF token
 */
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF token
 * @param string $token Token to verify
 * @return bool True if valid, false otherwise
 */
function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Hash password securely
 * @param string $password Plain text password
 * @return string Hashed password
 */
function hashPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

/**
 * Validate password strength
 * @param string $password Password to validate
 * @return array Array with 'valid' boolean and 'message' string
 */
function validatePassword($password) {
    $minLength = 6;
    
    if (strlen($password) < $minLength) {
        return ['valid' => false, 'message' => 'Password must be at least ' . $minLength . ' characters long'];
    }
    
    if (!preg_match('/[A-Za-z]/', $password) || !preg_match('/[0-9]/', $password)) {
        return ['valid' => false, 'message' => 'Password must contain both letters and numbers'];
    }
    
    return ['valid' => true, 'message' => 'Password is valid'];
}
?>