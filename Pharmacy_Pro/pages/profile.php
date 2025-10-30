<?php
/**
 * User Profile Page
 * Users can view and update their profile information
 */

require_once '../includes/header.php';

$error = '';
$success = '';

// Get current user data
$userQuery = "SELECT * FROM users WHERE id = ?";
$userResult = executeQuery($userQuery, 'i', [$_SESSION['user_id']]);

if (empty($userResult)) {
    header('Location: logout.php');
    exit;
}

$currentUser = $userResult[0];

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['update_profile'])) {
        $fullName = sanitizeInput($_POST['full_name']);
        $email = sanitizeInput($_POST['email']);
        
        // Validation
        if (empty($fullName) || empty($email)) {
            $error = 'Full name and email are required.';
        } elseif (!isValidEmail($email)) {
            $error = 'Please enter a valid email address.';
        } else {
            $query = "UPDATE users SET full_name = ?, email = ? WHERE id = ?";
            if (executeNonQuery($query, 'ssi', [$fullName, $email, $_SESSION['user_id']])) {
                $_SESSION['full_name'] = $fullName;
                $_SESSION['email'] = $email;
                $success = 'Profile updated successfully.';
                $currentUser['full_name'] = $fullName;
                $currentUser['email'] = $email;
                logActivity('Updated profile', $_SESSION['user_id']);
            } else {
                $error = 'Failed to update profile.';
            }
        }
    } elseif (isset($_POST['change_password'])) {
        $currentPassword = $_POST['current_password'];
        $newPassword = $_POST['new_password'];
        $confirmPassword = $_POST['confirm_password'];
        
        // Validation
        if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
            $error = 'All password fields are required.';
        } elseif (!password_verify($currentPassword, $currentUser['password'])) {
            $error = 'Current password is incorrect.';
        } elseif ($newPassword !== $confirmPassword) {
            $error = 'New passwords do not match.';
        } else {
            $passwordValidation = validatePassword($newPassword);
            if (!$passwordValidation['valid']) {
                $error = $passwordValidation['message'];
            } else {
                $hashedPassword = hashPassword($newPassword);
                $query = "UPDATE users SET password = ? WHERE id = ?";
                if (executeNonQuery($query, 'si', [$hashedPassword, $_SESSION['user_id']])) {
                    $success = 'Password changed successfully.';
                    logActivity('Changed password', $_SESSION['user_id']);
                } else {
                    $error = 'Failed to change password.';
                }
            }
        }
    }
}

// Get user activity stats
$activityStats = [];
$statsQuery = "SELECT 
                COUNT(DISTINCT o.id) as orders_processed,
                COALESCE(SUM(o.total_amount), 0) as total_sales
               FROM orders o 
               WHERE o.cashier_id = ?";
$statsResult = executeQuery($statsQuery, 'i', [$_SESSION['user_id']]);
$activityStats = $statsResult ? $statsResult[0] : ['orders_processed' => 0, 'total_sales' => 0];

// Get recent orders for this user
$recentOrdersQuery = "SELECT * FROM orders WHERE cashier_id = ? ORDER BY order_date DESC LIMIT 5";
$recentOrders = executeQuery($recentOrdersQuery, 'i', [$_SESSION['user_id']]);
?>

<div class="row">
    <!-- Profile Information -->
    <div class="col-md-6 mb-4">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-user"></i> Profile Information
                </h5>
            </div>
            <div class="card-body">
                <?php if ($error): ?>
                    <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?php echo $error; ?></div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo $success; ?></div>
                <?php endif; ?>
                
                <form method="POST">
                    <div class="mb-3">
                        <label for="username" class="form-label">Username</label>
                        <input 
                            type="text" 
                            class="form-control" 
                            id="username" 
                            value="<?php echo sanitizeInput($currentUser['username']); ?>" 
                            readonly
                        >
                        <small class="text-muted">Username cannot be changed</small>
                    </div>
                    
                    <div class="mb-3">
                        <label for="full_name" class="form-label">Full Name *</label>
                        <input 
                            type="text" 
                            class="form-control" 
                            id="full_name" 
                            name="full_name" 
                            value="<?php echo sanitizeInput($currentUser['full_name']); ?>" 
                            required
                        >
                    </div>
                    
                    <div class="mb-3">
                        <label for="email" class="form-label">Email Address (Optional)</label>
                        <input 
                            type="email" 
                            class="form-control" 
                            id="email" 
                            name="email" 
                            value="<?php echo sanitizeInput($currentUser['email']); ?>"
                        >
                        <small class="text-muted">Email is optional</small>
                    </div>
                    
                    <div class="mb-3">
                        <label for="role" class="form-label">Role</label>
                        <input 
                            type="text" 
                            class="form-control" 
                            id="role" 
                            value="<?php echo ucfirst($currentUser['role']); ?>" 
                            readonly
                        >
                        <small class="text-muted">Contact an administrator to change your role</small>
                    </div>
                    
                    <button type="submit" name="update_profile" class="btn btn-primary">
                        <i class="fas fa-save"></i> Update Profile
                    </button>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Change Password -->
    <div class="col-md-6 mb-4">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-lock"></i> Change Password
                </h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <div class="mb-3">
                        <label for="current_password" class="form-label">Current Password *</label>
                        <input 
                            type="password" 
                            class="form-control" 
                            id="current_password" 
                            name="current_password"
                        >
                    </div>
                    
                    <div class="mb-3">
                        <label for="new_password" class="form-label">New Password *</label>
                        <input 
                            type="password" 
                            class="form-control" 
                            id="new_password" 
                            name="new_password"
                        >
                        <small class="text-muted">Minimum 6 characters, include letters and numbers</small>
                    </div>
                    
                    <div class="mb-3">
                        <label for="confirm_password" class="form-label">Confirm New Password *</label>
                        <input 
                            type="password" 
                            class="form-control" 
                            id="confirm_password" 
                            name="confirm_password"
                        >
                    </div>
                    
                    <button type="submit" name="change_password" class="btn btn-warning">
                        <i class="fas fa-key"></i> Change Password
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Account Statistics -->
    <div class="col-md-6 mb-4">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-chart-bar"></i> Your Statistics
                </h5>
            </div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-6 mb-3">
                        <h4 class="text-primary"><?php echo $activityStats['orders_processed']; ?></h4>
                        <small class="text-muted">Orders Processed</small>
                    </div>
                    <div class="col-6 mb-3">
                        <h4 class="text-success"><?php echo formatCurrency($activityStats['total_sales']); ?></h4>
                        <small class="text-muted">Total Sales</small>
                    </div>
                    <div class="col-6">
                        <h4 class="text-info"><?php echo formatDate($currentUser['created_at'], 'M Y'); ?></h4>
                        <small class="text-muted">Member Since</small>
                    </div>
                    <div class="col-6">
                        <h4 class="text-secondary"><?php echo formatDate($currentUser['updated_at'], 'M j'); ?></h4>
                        <small class="text-muted">Last Updated</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Recent Activity -->
    <div class="col-md-6 mb-4">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-history"></i> Recent Orders
                </h5>
            </div>
            <div class="card-body">
                <?php if (empty($recentOrders)): ?>
                    <p class="text-muted text-center">No recent orders</p>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($recentOrders as $order): ?>
                            <div class="list-group-item d-flex justify-content-between align-items-center px-0">
                                <div>
                                    <strong><?php echo sanitizeInput($order['order_number']); ?></strong>
                                    <br>
                                    <small class="text-muted"><?php echo formatDate($order['order_date']); ?></small>
                                </div>
                                <div class="text-end">
                                    <strong class="text-success"><?php echo formatCurrency($order['total_amount']); ?></strong>
                                    <br>
                                    <span class="badge bg-<?php echo $order['payment_method'] == 'cash' ? 'success' : 'primary'; ?>">
                                        <?php echo ucfirst($order['payment_method']); ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="text-center mt-3">
                        <a href="orders.php" class="btn btn-outline-primary btn-sm">
                            View All Orders
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Account Information -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-info-circle"></i> Account Information
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6>Account Details</h6>
                        <ul class="list-unstyled">
                            <li><strong>User ID:</strong> <?php echo $currentUser['id']; ?></li>
                            <li><strong>Username:</strong> <?php echo sanitizeInput($currentUser['username']); ?></li>
                            <li><strong>Email:</strong> <?php echo sanitizeInput($currentUser['email']); ?></li>
                            <li><strong>Role:</strong> 
                                <span class="badge bg-<?php echo $currentUser['role'] == 'admin' ? 'danger' : 'primary'; ?>">
                                    <?php echo ucfirst($currentUser['role']); ?>
                                </span>
                            </li>
                            <li><strong>Status:</strong> 
                                <span class="badge bg-<?php echo $currentUser['is_active'] ? 'success' : 'danger'; ?>">
                                    <?php echo $currentUser['is_active'] ? 'Active' : 'Inactive'; ?>
                                </span>
                            </li>
                        </ul>
                    </div>
                    <div class="col-md-6">
                        <h6>Account Activity</h6>
                        <ul class="list-unstyled">
                            <li><strong>Account Created:</strong> <?php echo formatDate($currentUser['created_at']); ?></li>
                            <li><strong>Last Profile Update:</strong> <?php echo formatDate($currentUser['updated_at']); ?></li>
                            <li><strong>Current Session:</strong> <?php echo formatDate(date('Y-m-d H:i:s'), 'M j, Y H:i'); ?></li>
                            <li><strong>Access Level:</strong> 
                                <?php echo $currentUser['role'] == 'admin' ? 'Full System Access' : 'POS Access Only'; ?>
                            </li>
                        </ul>
                    </div>
                </div>
                
                <?php if ($currentUser['role'] == 'admin'): ?>
                    <div class="alert alert-info mt-3">
                        <i class="fas fa-crown"></i> <strong>Administrator Account</strong><br>
                        You have full access to all system features including user management, inventory, and reports.
                    </div>
                <?php else: ?>
                    <div class="alert alert-primary mt-3">
                        <i class="fas fa-cash-register"></i> <strong>Cashier Account</strong><br>
                        You have access to POS functions and basic reporting. Contact an administrator for additional permissions.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
// Password confirmation validation
document.getElementById('confirm_password').addEventListener('input', function() {
    const newPassword = document.getElementById('new_password').value;
    const confirmPassword = this.value;
    
    if (newPassword !== confirmPassword) {
        this.setCustomValidity('Passwords do not match');
    } else {
        this.setCustomValidity('');
    }
});

// Password strength indicator
document.getElementById('new_password').addEventListener('input', function() {
    const password = this.value;
    let strength = 0;
    
    if (password.length >= 6) strength++;
    if (/[a-zA-Z]/.test(password)) strength++;
    if (/[0-9]/.test(password)) strength++;
    if (/[^a-zA-Z0-9]/.test(password)) strength++;
    
    // Add visual feedback for password strength
    const strengthText = ['Very Weak', 'Weak', 'Fair', 'Good', 'Strong'];
    const strengthColors = ['danger', 'warning', 'info', 'primary', 'success'];
    
    // You can add a strength indicator element here if desired
});

/* Additional icon styles */
.fa-history::before { content: "📜"; }
.fa-key::before { content: "🗝️"; }
</script>

<?php require_once '../includes/footer.php'; ?>