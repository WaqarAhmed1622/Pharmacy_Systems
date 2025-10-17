<?php
/**
 * Entry Point for Mart Management System
 * Redirects users to appropriate page based on login status
 */

// Start session
session_start();

// Check if user is logged in
if (isset($_SESSION['user_id']) && isset($_SESSION['username'])) {
    // User is logged in, redirect to dashboard
    header('Location: pages/dashboard.php');
} else {
    // User is not logged in, redirect to login page
    header('Location: login.php');
}

exit;
?>