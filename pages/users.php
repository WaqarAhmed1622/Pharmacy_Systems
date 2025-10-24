<?php
/**
 * User Management Page
 * Admin only - Manage users (CRUD operations)
 */

require_once '../includes/header.php';
requireAdmin();

$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$userId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$error = '';
$success = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add_user']) || isset($_POST['edit_user'])) {
        $username = sanitizeInput($_POST['username']);
        $email = sanitizeInput($_POST['email']);
        $fullName = sanitizeInput($_POST['full_name']);
        $role = sanitizeInput($_POST['role']);
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        
        // Validation - Email is now optional
        if (empty($username) || empty($fullName)) {
            $error = 'Please fill all required fields.';
        } elseif (!empty($email) && !isValidEmail($email)) {
            $error = 'Please enter a valid email address.';
        } elseif (!isUsernameUnique($username, isset($_POST['edit_user']) ? $userId : null)) {
            $error = 'Username already exists. Please choose a different username.';
        } else {
            if (isset($_POST['add_user'])) {
                $password = $_POST['password'];
                $confirmPassword = $_POST['confirm_password'];
                
                if (empty($password)) {
                    $error = 'Password is required.';
                } elseif ($password !== $confirmPassword) {
                    $error = 'Passwords do not match.';
                } else {
                    $passwordValidation = validatePassword($password);
                    if (!$passwordValidation['valid']) {
                        $error = $passwordValidation['message'];
                    } else {
                        // Add new user
                        $hashedPassword = hashPassword($password);
                        $query = "INSERT INTO users (username, email, password, role, full_name, is_active) 
                                 VALUES (?, ?, ?, ?, ?, ?)";
                        $params = [$username, $email, $hashedPassword, $role, $fullName, $isActive];
                        $types = 'sssssi';
                        
                        if (executeNonQuery($query, $types, $params)) {
                            $success = 'User added successfully.';
                            logActivity('Added user', $_SESSION['user_id'], "Username: $username");
                        } else {
                            $error = 'Failed to add user.';
                        }
                    }
                }
            } else {
                // Update existing user
                $query = "UPDATE users SET username = ?, email = ?, role = ?, full_name = ?, is_active = ? WHERE id = ?";
                $params = [$username, $email, $role, $fullName, $isActive, $userId];
                $types = 'ssssii';
                
                // Handle password update if provided
                if (!empty($_POST['password'])) {
                    $password = $_POST['password'];
                    $confirmPassword = $_POST['confirm_password'];
                    
                    if ($password !== $confirmPassword) {
                        $error = 'Passwords do not match.';
                    } else {
                        $passwordValidation = validatePassword($password);
                        if (!$passwordValidation['valid']) {
                            $error = $passwordValidation['message'];
                        } else {
                            $hashedPassword = hashPassword($password);
                            $query = "UPDATE users SET username = ?, email = ?, password = ?, role = ?, full_name = ?, is_active = ? WHERE id = ?";
                            $params = [$username, $email, $hashedPassword, $role, $fullName, $isActive, $userId];
                            $types = 'sssssii';
                        }
                    }
                }
                
                if (empty($error)) {
                    if (executeNonQuery($query, $types, $params)) {
                        $success = 'User updated successfully.';
                        logActivity('Updated user', $_SESSION['user_id'], "User ID: $userId");
                        $action = 'list';
                    } else {
                        $error = 'Failed to update user.';
                    }
                }
            }
        }
    } elseif (isset($_POST['delete_user'])) {
        // Prevent deleting current user
        if ($userId == $_SESSION['user_id']) {
            $error = 'You cannot delete your own account.';
        } else {
            // Check if user has orders
            $checkOrdersQuery = "SELECT COUNT(*) as count FROM orders WHERE cashier_id = ?";
            $ordersCheck = executeQuery($checkOrdersQuery, 'i', [$userId]);
            
            if ($ordersCheck[0]['count'] > 0) {
                // Don't delete, just deactivate
                $query = "UPDATE users SET is_active = 0 WHERE id = ?";
                if (executeNonQuery($query, 'i', [$userId])) {
                    $success = 'User deactivated successfully (has order history).';
                    logActivity('Deactivated user', $_SESSION['user_id'], "User ID: $userId");
                } else {
                    $error = 'Failed to deactivate user.';
                }
            } else {
                $query = "DELETE FROM users WHERE id = ?";
                if (executeNonQuery($query, 'i', [$userId])) {
                    $success = 'User deleted successfully.';
                    logActivity('Deleted user', $_SESSION['user_id'], "User ID: $userId");
                } else {
                    $error = 'Failed to delete user.';
                }
            }
            $action = 'list';
        }
    } elseif (isset($_POST['toggle_status'])) {
        $currentStatus = (int)$_POST['current_status'];
        $newStatus = $currentStatus ? 0 : 1;
        
        if ($userId == $_SESSION['user_id'] && $newStatus == 0) {
            $error = 'You cannot deactivate your own account.';
        } else {
            $query = "UPDATE users SET is_active = ? WHERE id = ?";
            if (executeNonQuery($query, 'ii', [$newStatus, $userId])) {
                $success = 'User status updated successfully.';
                logActivity('Changed user status', $_SESSION['user_id'], "User ID: $userId, Active: $newStatus");
            } else {
                $error = 'Failed to update user status.';
            }
        }
        $action = 'list';
    }
}

// Get user for editing
if ($action == 'edit' && $userId) {
    $userQuery = "SELECT * FROM users WHERE id = ?";
    $userResult = executeQuery($userQuery, 'i', [$userId]);
    if (empty($userResult)) {
        $action = 'list';
    } else {
        $user = $userResult[0];
    }
}

// Get all users for listing
if ($action == 'list') {
    $users = executeQuery("SELECT * FROM users ORDER BY created_at DESC");
}
?>

<?php if ($action == 'list'): ?>
    <!-- Users List -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3><i class="fas fa-users"></i> User Management</h3>
        <a href="?action=add" class="btn btn-primary">
            <i class="fas fa-plus"></i> Add User
        </a>
    </div>
    
    <?php if ($error): ?>
        <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?php echo $error; ?></div>
    <?php endif; ?>
    
    <?php if ($success): ?>
        <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo $success; ?></div>
    <?php endif; ?>
    
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>User Details</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th>Last Updated</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($users)): ?>
                            <tr>
                                <td colspan="6" class="text-center py-4">
                                    <i class="fas fa-users fa-3x text-muted mb-3"></i>
                                    <h5 class="text-muted">No users found</h5>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($users as $usr): ?>
                                <tr class="<?php echo !$usr['is_active'] ? 'table-secondary' : ''; ?>">
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="me-3">
                                                <i class="fas fa-user-circle fa-2x text-muted"></i>
                                            </div>
                                            <div>
                                                <strong><?php echo sanitizeInput($usr['full_name']); ?></strong>
                                                <?php if ($usr['id'] == $_SESSION['user_id']): ?>
                                                    <span class="badge bg-info ms-1">You</span>
                                                <?php endif; ?>
                                                <br>
                                                <small class="text-muted">
                                                    @<?php echo sanitizeInput($usr['username']); ?><br>
                                                    <?php echo sanitizeInput($usr['email']); ?>
                                                </small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php echo $usr['role'] == 'admin' ? 'danger' : 'primary'; ?>">
                                            <i class="fas fa-<?php echo $usr['role'] == 'admin' ? 'crown' : 'user'; ?>"></i>
                                            <?php echo ucfirst($usr['role']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="user_id" value="<?php echo $usr['id']; ?>">
                                            <input type="hidden" name="current_status" value="<?php echo $usr['is_active']; ?>">
                                            <button type="submit" name="toggle_status" 
                                                    class="btn btn-sm btn-outline-<?php echo $usr['is_active'] ? 'success' : 'danger'; ?>"
                                                    <?php echo $usr['id'] == $_SESSION['user_id'] ? 'disabled title="Cannot change your own status"' : ''; ?>>
                                                <i class="fas fa-<?php echo $usr['is_active'] ? 'check' : 'times'; ?>"></i>
                                                <?php echo $usr['is_active'] ? 'Active' : 'Inactive'; ?>
                                            </button>
                                        </form>
                                    </td>
                                    <td><?php echo formatDate($usr['created_at']); ?></td>
                                    <td><?php echo formatDate($usr['updated_at']); ?></td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="?action=edit&id=<?php echo $usr['id']; ?>" class="btn btn-outline-primary">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <?php if ($usr['id'] != $_SESSION['user_id']): ?>
                                                <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure?')">
                                                    <input type="hidden" name="user_id" value="<?php echo $usr['id']; ?>">
                                                    <button type="submit" name="delete_user" class="btn btn-outline-danger">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

<?php else: ?>
    <!-- Add/Edit User Form -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3>
            <i class="fas fa-<?php echo $action == 'add' ? 'plus' : 'edit'; ?>"></i> 
            <?php echo ucfirst($action); ?> User
        </h3>
        <a href="?action=list" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back to List
        </a>
    </div>
    
    <?php if ($error): ?>
        <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?php echo $error; ?></div>
    <?php endif; ?>
    
    <div class="row">
        <div class="col-md-8">
            <div class="card">
                <div class="card-body">
                    <form method="POST">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="full_name" class="form-label">Full Name *</label>
                                <input 
                                    type="text" 
                                    class="form-control" 
                                    id="full_name" 
                                    name="full_name" 
                                    value="<?php echo isset($user) ? sanitizeInput($user['full_name']) : ''; ?>" 
                                    required
                                >
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="username" class="form-label">Username *</label>
                                <input 
                                    type="text" 
                                    class="form-control" 
                                    id="username" 
                                    name="username" 
                                    value="<?php echo isset($user) ? sanitizeInput($user['username']) : ''; ?>" 
                                    required
                                >
                                <small class="text-muted">Must be unique</small>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="email" class="form-label">Email Address (Optional)</label>
                                <input 
                                    type="email" 
                                    class="form-control" 
                                    id="email" 
                                    name="email" 
                                    value="<?php echo isset($user) ? sanitizeInput($user['email']) : ''; ?>"
                                >
                                <small class="text-muted">Email is optional</small>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="role" class="form-label">Role *</label>
                                <select class="form-select" id="role" name="role" required>
                                    <option value="">Select Role</option>
                                    <option value="admin" <?php echo (isset($user) && $user['role'] == 'admin') ? 'selected' : ''; ?>>
                                        Admin
                                    </option>
                                    <option value="cashier" <?php echo (isset($user) && $user['role'] == 'cashier') ? 'selected' : ''; ?>>
                                        Cashier
                                    </option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="password" class="form-label">
                                    Password <?php echo $action == 'add' ? '*' : '(leave blank to keep current)'; ?>
                                </label>
                                <input 
                                    type="password" 
                                    class="form-control" 
                                    id="password" 
                                    name="password" 
                                    <?php echo $action == 'add' ? 'required' : ''; ?>
                                >
                                <small class="text-muted">Minimum 6 characters, include letters and numbers</small>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="confirm_password" class="form-label">
                                    Confirm Password <?php echo $action == 'add' ? '*' : ''; ?>
                                </label>
                                <input 
                                    type="password" 
                                    class="form-control" 
                                    id="confirm_password" 
                                    name="confirm_password"
                                >
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <div class="form-check">
                                <input 
                                    type="checkbox" 
                                    class="form-check-input" 
                                    id="is_active" 
                                    name="is_active" 
                                    <?php echo (!isset($user) || $user['is_active']) ? 'checked' : ''; ?>
                                >
                                <label class="form-check-label" for="is_active">
                                    Account is active
                                </label>
                            </div>
                        </div>
                        
                        <div class="d-flex justify-content-between">
                            <a href="?action=list" class="btn btn-secondary">
                                <i class="fas fa-times"></i> Cancel
                            </a>
                            <button type="submit" name="<?php echo $action == 'add' ? 'add_user' : 'edit_user'; ?>" class="btn btn-primary">
                                <i class="fas fa-save"></i> <?php echo $action == 'add' ? 'Add User' : 'Update User'; ?>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h6 class="card-title mb-0">
                        <i class="fas fa-info-circle"></i> User Roles
                    </h6>
                </div>
                <div class="card-body">
                    <h6><i class="fas fa-crown text-danger"></i> Admin</h6>
                    <ul class="small">
                        <li>Full system access</li>
                        <li>Manage users and products</li>
                        <li>View reports and analytics</li>
                        <li>Manage inventory and settings</li>
                    </ul>
                    
                    <h6><i class="fas fa-user text-primary"></i> Cashier</h6>
                    <ul class="small">
                        <li>Process sales and orders</li>
                        <li>View basic dashboard</li>
                        <li>Limited to POS functions</li>
                        <li>Cannot manage users or settings</li>
                    </ul>
                    
                    <?php if (isset($user)): ?>
                        <hr>
                        <h6>Current User:</h6>
                        <small>
                            <strong>Created:</strong> <?php echo formatDate($user['created_at']); ?><br>
                            <strong>Last Updated:</strong> <?php echo formatDate($user['updated_at']); ?>
                        </small>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="card mt-3">
                <div class="card-header">
                    <h6 class="card-title mb-0">
                        <i class="fas fa-shield-alt"></i> Security Notes
                    </h6>
                </div>
                <div class="card-body">
                    <ul class="small">
                        <li>Passwords are encrypted</li>
                        <li>Usernames must be unique</li>
                        <li>Inactive users cannot login</li>
                        <li>Users with orders cannot be deleted</li>
                        <li>Admin users have full access</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Password confirmation validation
        $('#confirm_password').on('input', function() {
            const password = $('#password').val();
            const confirmPassword = $(this).val();
            
            if (password !== confirmPassword) {
                this.setCustomValidity('Passwords do not match');
            } else {
                this.setCustomValidity('');
            }
        });
        
        // Password strength indicator
        $('#password').on('input', function() {
            const password = $(this).val();
            let strength = 0;
            
            if (password.length >= 6) strength++;
            if (/[a-zA-Z]/.test(password)) strength++;
            if (/[0-9]/.test(password)) strength++;
            if (/[^a-zA-Z0-9]/.test(password)) strength++;
            
            // You can add visual password strength indicator here
        });
    </script>
<?php endif; ?>

<?php require_once '../includes/footer.php'; ?>