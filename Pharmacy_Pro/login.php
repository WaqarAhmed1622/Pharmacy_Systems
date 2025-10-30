<?php
/**
 * Login Page
 * Handles user authentication and login form
 */

require_once 'config/database.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

// Redirect if already logged in
if (isLoggedIn()) {
    header('Location: pages/dashboard.php');
    exit;
}

$error = '';

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = sanitizeInput($_POST['username']);
    $password = $_POST['password'];
    
    if (empty($username) || empty($password)) {
        $error = 'Please enter both username and password.';
    } else {
        $user = authenticateUser($username, $password);
        
        if ($user) {
            loginUser($user);
            header('Location: pages/dashboard.php');
            exit;
        } else {
            $error = 'Invalid username or password.';
        }
    }
}

// Check for timeout message
if (isset($_GET['timeout'])) {
    $error = 'Your session has expired. Please login again.';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Pharmacy Management System</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .login-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            padding: 2rem;
            width: 100%;
            max-width: 400px;
        }
        
        .login-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .login-header h2 {
            color: #333;
            font-weight: 600;
        }
        
        .login-header p {
            color: #666;
            margin-bottom: 0;
        }
        
        .form-control {
            border-radius: 10px;
            border: 1px solid #ddd;
            padding: 12px 15px;
        }
        
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        
        .btn-login {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 10px;
            padding: 12px;
            font-weight: 600;
            width: 100%;
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        
        .demo-credentials {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 1rem;
            margin-top: 1.5rem;
        }
        
        .demo-credentials h6 {
            color: #495057;
            margin-bottom: 0.5rem;
        }
        
        .demo-credentials small {
            color: #6c757d;
        }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="login-header">
            <i class="fas fa-pills fa-3x text-primary mb-3"></i>
            <h2>Pharmacy Management</h2>
            <p>Please sign in to continue</p>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="mb-3">
                <label for="username" class="form-label">Username</label>
                <div class="input-group">
                    <span class="input-group-text">
                        <i class="fas fa-user"></i>
                    </span>
                    <input 
                        type="text" 
                        class="form-control" 
                        id="username" 
                        name="username" 
                        value="<?php echo isset($_POST['username']) ? sanitizeInput($_POST['username']) : ''; ?>"
                        required 
                        autofocus
                    >
                </div>
            </div>
            
            <div class="mb-3">
                <label for="password" class="form-label">Password</label>
                <div class="input-group">
                    <span class="input-group-text">
                        <i class="fas fa-lock"></i>
                    </span>
                    <input 
                        type="password" 
                        class="form-control" 
                        id="password" 
                        name="password" 
                        required
                    >
                </div>
            </div>
            
            <button type="submit" class="btn btn-primary btn-login">
                <i class="fas fa-sign-in-alt"></i> Sign In
            </button>
        </form>
        
        <div class="demo-credentials">
            <h6><i class="fas fa-info-circle"></i> Demo Credentials</h6>
            <small>
                <strong>Admin:</strong> admin / admin123<br>
                <strong>Note:</strong> Default admin account for testing
            </small>
        </div>
    </div>
    
    <!-- Simple JavaScript (Offline) -->
    <script>
        // Auto-focus username field
        document.getElementById('username').focus();
        
        // Enter key navigation
        document.getElementById('username').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                document.getElementById('password').focus();
            }
        });
    </script>
</body>
</html>