<?php
/**
 * TechProcure Tanzania - Admin Login Page
 * File: admin/auth/login.php
 */

session_start();

// Include configuration
require_once '../../includes/config.php';
require_once '../../includes/db.php';
require_once '../../includes/functions.php';

// If already logged in as admin
if (isset($_SESSION['user_id']) && isset($_SESSION['user_role']) && $_SESSION['user_role'] == 'admin') {
    header("Location: ../dashboard.php");
    exit();
}

// If logged in as other user
if (isset($_SESSION['user_id'])) {
    header("Location: ../../index.php");
    exit();
}

$error = '';
$success = '';
$login_input = '';

// Check for logout message
if (isset($_GET['logout']) && $_GET['logout'] == 'success') {
    $success = 'You have been successfully logged out.';
}

// Process login
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $login_input = trim($_POST['login_input'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember_me = isset($_POST['remember_me']) ? true : false;
    
    if (empty($login_input) || empty($password)) {
        $error = 'Please enter your email/username and password.';
    } else {
        try {
            $db = getDB();
            
            // Check if input is email or username
            if (filter_var($login_input, FILTER_VALIDATE_EMAIL)) {
                $sql = "SELECT u.*, r.role_name 
                        FROM users u 
                        LEFT JOIN roles r ON u.role_id = r.id 
                        WHERE u.email = ? AND u.user_type = 'admin' AND u.status = 'active'";
            } else {
                $sql = "SELECT u.*, r.role_name 
                        FROM users u 
                        LEFT JOIN roles r ON u.role_id = r.id 
                        WHERE u.username = ? AND u.user_type = 'admin' AND u.status = 'active'";
            }
            
            $stmt = $db->prepare($sql);
            $stmt->execute([$login_input]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($password, $user['password_hash'])) {
                // Login successful
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_name'] = $user['full_name'] ?? $user['username'];
                $_SESSION['user_role'] = 'admin';
                $_SESSION['user_type'] = 'admin';
                $_SESSION['logged_in'] = true;
                $_SESSION['login_time'] = time();
                
                // Update last login
                try {
                    $update_sql = "UPDATE users SET last_login = NOW(), last_ip = ? WHERE id = ?";
                    $update_stmt = $db->prepare($update_sql);
                    $update_stmt->execute([$_SERVER['REMOTE_ADDR'] ?? '', $user['id']]);
                } catch (PDOException $e) {
                    // Continue
                }
                
                // Redirect to admin dashboard
                header("Location: ../dashboard.php");
                exit();
            } else {
                $error = 'Invalid email/username or password.';
            }
        } catch (PDOException $e) {
            $error = 'Login failed. Please try again.';
        }
    }
}

// Generate CSRF token
$csrf_token = generateCSRFToken();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - TechProcure Tanzania</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .login-container {
            width: 100%;
            max-width: 420px;
        }
        
        .login-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.5);
            overflow: hidden;
            animation: fadeInUp 0.6s ease;
        }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .login-header {
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            padding: 35px 30px;
            text-align: center;
            color: white;
        }
        
        .login-header .logo-icon {
            font-size: 3rem;
            margin-bottom: 15px;
        }
        
        .login-header .admin-badge {
            display: inline-block;
            background: rgba(255,255,255,0.15);
            padding: 5px 15px;
            border-radius: 50px;
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 10px;
        }
        
        .login-header .admin-badge i {
            margin-right: 5px;
        }
        
        .login-header h2 {
            font-size: 1.8rem;
            font-weight: 700;
            margin-bottom: 5px;
        }
        
        .login-header p {
            opacity: 0.8;
            font-size: 0.9rem;
            margin-bottom: 0;
        }
        
        .login-header .back-home {
            display: inline-block;
            color: rgba(255,255,255,0.7);
            text-decoration: none;
            font-size: 0.85rem;
            margin-top: 12px;
            transition: all 0.3s;
        }
        
        .login-header .back-home:hover {
            color: white;
        }
        
        .login-body {
            padding: 35px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            font-weight: 500;
            margin-bottom: 8px;
            color: #333;
            display: block;
            font-size: 0.9rem;
        }
        
        .form-group label i {
            margin-right: 8px;
            color: #0d6efd;
        }
        
        .form-control {
            border-radius: 10px;
            padding: 12px 15px;
            border: 1px solid #e0e0e0;
            transition: all 0.3s;
            font-size: 0.95rem;
        }
        
        .form-control:focus {
            border-color: #0d6efd;
            box-shadow: 0 0 0 3px rgba(13,110,253,0.1);
            outline: none;
        }
        
        .btn-login {
            background: linear-gradient(135deg, #0d6efd, #0b5ed7);
            border: none;
            border-radius: 10px;
            padding: 12px;
            font-weight: 600;
            font-size: 1rem;
            width: 100%;
            color: white;
            transition: all 0.3s;
            cursor: pointer;
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(13,110,253,0.3);
        }
        
        .btn-login:disabled {
            opacity: 0.7;
            cursor: not-allowed;
            transform: none;
        }
        
        .form-check {
            margin: 15px 0;
        }
        
        .form-check-input:checked {
            background-color: #0d6efd;
            border-color: #0d6efd;
        }
        
        .alert {
            border-radius: 10px;
            padding: 12px 15px;
            margin-bottom: 20px;
            border: none;
        }
        
        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border-left: 4px solid #dc3545;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border-left: 4px solid #28a745;
        }
        
        .forgot-link {
            text-align: right;
        }
        
        .forgot-link a {
            color: #6c757d;
            text-decoration: none;
            font-size: 0.85rem;
        }
        
        .forgot-link a:hover {
            color: #0d6efd;
            text-decoration: underline;
        }
        
        .password-toggle {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #999;
            background: white;
            padding-left: 10px;
        }
        
        .password-toggle:hover {
            color: #333;
        }
        
        .input-group {
            position: relative;
        }
        
        .security-badge {
            text-align: center;
            margin-top: 20px;
            font-size: 0.75rem;
            color: #6c757d;
        }
        
        .security-badge i {
            margin-right: 5px;
        }
        
        .security-badge .lock-icon {
            color: #28a745;
        }
        
        .admin-shield {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            margin-top: 15px;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 8px;
        }
        
        .admin-shield i {
            color: #0d6efd;
        }
        
        .admin-shield span {
            font-size: 0.8rem;
            color: #6c757d;
        }
        
        .loading-spinner {
            display: none;
        }
        
        .btn-login .loading-spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 2px solid rgba(255,255,255,0.3);
            border-radius: 50%;
            border-top-color: #fff;
            animation: spin 0.8s ease-in-out infinite;
            margin-right: 10px;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        /* NEW: Register link styles */
        .register-link {
            text-align: center;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #e0e0e0;
        }
        
        .register-link a {
            color: #0d6efd;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s;
        }
        
        .register-link a:hover {
            text-decoration: underline;
            color: #0b5ed7;
        }
        
        .register-link .btn-register {
            display: inline-block;
            background: #e7f3ff;
            color: #0d6efd;
            border: 1px solid #b6d4fe;
            padding: 8px 20px;
            border-radius: 10px;
            font-weight: 500;
            transition: all 0.3s;
            text-decoration: none;
            margin-top: 5px;
        }
        
        .register-link .btn-register:hover {
            background: #0d6efd;
            color: white;
            text-decoration: none;
            transform: translateY(-2px);
        }
        
        .demo-credentials {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 15px;
            margin-top: 15px;
            font-size: 13px;
        }
        
        @media (max-width: 576px) {
            .login-body {
                padding: 25px;
            }
            .login-header {
                padding: 25px 20px;
            }
            .login-header h2 {
                font-size: 1.4rem;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <!-- Header -->
            <div class="login-header">
                <div class="logo-icon">
                    <i class="fas fa-microchip"></i>
                </div>
                <div class="admin-badge">
                    <i class="fas fa-shield-alt me-1"></i> Admin Portal
                </div>
                <h2>Welcome Back!</h2>
                <p>Login to your admin dashboard</p>
                <a href="../../index.php" class="back-home">
                    <i class="fas fa-arrow-left me-1"></i> Back to Home
                </a>
            </div>
            
            <!-- Body -->
            <div class="login-body">
                <?php if($success): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <i class="fas fa-check-circle me-2"></i> <?php echo $success; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <?php if($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <i class="fas fa-exclamation-triangle me-2"></i> <?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="" id="loginForm">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    
                    <div class="form-group">
                        <label><i class="fas fa-user"></i> Email or Username</label>
                        <input type="text" name="login_input" class="form-control" 
                               placeholder="Enter your admin email or username" 
                               value="<?php echo htmlspecialchars($login_input); ?>" 
                               required autofocus>
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-lock"></i> Password</label>
                        <div class="input-group">
                            <input type="password" name="password" id="password" class="form-control" 
                                   placeholder="Enter your admin password" required>
                            <span class="password-toggle" onclick="togglePassword()">
                                <i class="fas fa-eye" id="toggleIcon"></i>
                            </span>
                        </div>
                    </div>
                    
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="form-check">
                            <input type="checkbox" name="remember_me" class="form-check-input" id="rememberMe">
                            <label class="form-check-label" for="rememberMe">Remember Me</label>
                        </div>
                        <div class="forgot-link">
                            <a href="../auth/forgot-password.php">Forgot Password?</a>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn-login mt-3" id="loginBtn">
                        <span class="loading-spinner" id="loadingSpinner"></span>
                        <i class="fas fa-sign-in-alt me-2" id="loginIcon"></i> Login to Dashboard
                    </button>
                </form>
                
                <!-- NEW: Register Link Section -->
                <div class="register-link">
                    <p class="mb-2">Don't have an admin account?</p>
                    <a href="register.php" class="btn-register">
                        <i class="fas fa-user-plus me-2"></i> Create Admin Account
                    </a>
                </div>
                
                <div class="demo-credentials">
                    <small class="text-muted">Demo Admin Credentials:</small><br>
                    <small><strong>Username:</strong> admin</small><br>
                    <small><strong>Email:</strong> admin@techprocure.co.tz</small><br>
                    <small><strong>Password:</strong> Admin123!</small>
                </div>
                
                <div class="admin-shield">
                    <i class="fas fa-shield-alt"></i>
                    <span>Secure admin access with 256-bit encryption</span>
                </div>
                
                <div class="security-badge">
                    <i class="fas fa-lock lock-icon"></i> 
                    Secure Login | Authorized Personnel Only
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Toggle password visibility
        function togglePassword() {
            const password = document.getElementById('password');
            const icon = document.getElementById('toggleIcon');
            if (password.type === 'password') {
                password.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                password.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }
        
        // Form submission loading state
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const btn = document.getElementById('loginBtn');
            const icon = document.getElementById('loginIcon');
            const spinner = document.getElementById('loadingSpinner');
            
            btn.disabled = true;
            spinner.style.display = 'inline-block';
            icon.style.display = 'none';
            btn.innerHTML = '<span class="loading-spinner" style="display:inline-block;"></span> Logging in...';
        });
        
        // Auto-hide alerts after 5 seconds
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);
        
        // Enter key support
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                const form = document.getElementById('loginForm');
                if (form) {
                    form.submit();
                }
            }
        });
    </script>
    
    <!-- Bootstrap JS for alerts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>