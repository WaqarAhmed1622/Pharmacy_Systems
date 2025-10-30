<?php
/**
 * Logout Page
 * Handles user logout and session destruction
 */

require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Log the logout activity
if (isLoggedIn()) {
    logActivity('User logged out', $_SESSION['user_id']);
}

// Logout user
logoutUser();
?>