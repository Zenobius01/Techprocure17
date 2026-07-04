<?php
/**
 * TechProcure Tanzania - Login Page
 * File: auth/login.php
 * Description: Unified login for Admin, Customer, and Supplier
 */

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include configuration
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

// If already logged in, redirect
if (isLoggedIn()) {
    redirectToDashboard();
    exit();
}

$error = '';
$success = '';
$email = '';
$selectedRole = '';

// Check for logout message
if (isset($_GET['logout']) && $_GET['logout'] === 'success') {
    $success = 'You have been successfully logged out.';
}

// Check for registration success
if (isset($_GET['registered']) && $_GET['registered'] === 'success') {
    $success = 'Registration successful! Please login to your account.';
}

// Generate CSRF token
$csrf_token = generateCSRFToken();

// Process login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        $error = 'Invalid security token. Please refresh the page.';
    } else {
        $email = sanitize(trim($_POST['email'] ?? ''));
        $password = $_POST['password'] ?? '';
        $remember = isset($_POST['remember']) ? true : false;
        $selectedRole = $_POST['user_type'] ?? '';
        
        if (empty($email) || empty($password)) {
            $error = 'Please enter both email and password.';
        } else {
            try {
                $db = getDB();
                
                // Check if roles table exists
                try {
                    $db->query("SELECT 1 FROM roles LIMIT 1");
                } catch (PDOException $e) {
                    // Create roles table
                    $db->exec("
                        CREATE TABLE IF NOT EXISTS roles (
                            id INT AUTO_INCREMENT PRIMARY KEY,
                            role_name VARCHAR(50) UNIQUE NOT NULL,
                            permissions JSON NULL,
                            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                    ");
                    $roles = ['admin', 'superadmin', 'supplier', 'vendor', 'customer', 'user'];
                    foreach ($roles as $role) {
                        $stmt = $db->prepare("INSERT IGNORE INTO roles (role_name) VALUES (?)");
                        $stmt->execute([$role]);
                    }
                }
                
                // Check if users table exists
                try {
                    $db->query("SELECT 1 FROM users LIMIT 1");
                } catch (PDOException $e) {
                    // Create users table
                    $db->exec("
                        CREATE TABLE IF NOT EXISTS users (
                            id INT AUTO_INCREMENT PRIMARY KEY,
                            username VARCHAR(50) UNIQUE NOT NULL,
                            email VARCHAR(100) UNIQUE NOT NULL,
                            password_hash VARCHAR(255) NOT NULL,
                            full_name VARCHAR(100) NOT NULL,
                            user_type ENUM('admin', 'supplier', 'customer') DEFAULT 'customer',
                            role_id INT NULL,
                            status ENUM('active', 'pending', 'suspended', 'inactive') DEFAULT 'active',
                            profile_image VARCHAR(255) NULL,
                            email_verified TINYINT(1) DEFAULT 0,
                            remember_token VARCHAR(100) NULL,
                            token_expires DATETIME NULL,
                            last_login DATETIME NULL,
                            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                            FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE SET NULL,
                            INDEX idx_email (email),
                            INDEX idx_username (username),
                            INDEX idx_user_type (user_type),
                            INDEX idx_status (status)
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                    ");
                }
                
                // Get user by email or username (all user types)
                $sql = "SELECT 
                            u.id, 
                            u.username, 
                            u.full_name, 
                            u.email, 
                            u.password_hash, 
                            u.user_type, 
                            u.status, 
                            u.profile_image,
                            u.role_id,
                            r.role_name
                        FROM users u
                        LEFT JOIN roles r ON u.role_id = r.id
                        WHERE (u.email = ? OR u.username = ?)";
                $stmt = $db->prepare($sql);
                $stmt->execute([$email, $email]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($user && verifyPassword($password, $user['password_hash'])) {
                    $userType = $user['user_type'];
                    $roleName = $user['role_name'] ?? $user['user_type'];
                    
                    // Check if selected role matches user's actual role
                    if (!empty($selectedRole)) {
                        // Check if user is admin and selected admin
                        if ($selectedRole === 'admin' && $userType === 'admin') {
                            // Admin login - allowed
                        } 
                        // Check if user is supplier and selected supplier
                        elseif ($selectedRole === 'supplier' && $userType === 'supplier') {
                            // Supplier login - allowed
                        }
                        // Check if user is customer and selected customer
                        elseif ($selectedRole === 'customer' && $userType === 'customer') {
                            // Customer login - allowed
                        }
                        // If none of the above, it's a mismatch
                        else {
                            $error = 'Invalid login attempt. You are registered as a ' . ucfirst($userType) . '. Please select the correct role.';
                        }
                    }
                    
                    // Check if user is active
                    if (empty($error) && $user['status'] !== 'active') {
                        if ($user['status'] === 'pending') {
                            $error = 'Your account is pending approval. Please wait for admin verification.';
                        } elseif ($user['status'] === 'suspended') {
                            $error = 'Your account has been suspended. Please contact support.';
                        } else {
                            $error = 'Your account is not active. Please contact support.';
                        }
                    }
                    
                    // If no errors, login successful
                    if (empty($error)) {
                        session_regenerate_id(true);
                        regenerateCSRFToken();
                        
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['username'] = $user['username'];
                        $_SESSION['user_name'] = $user['full_name'] ?? 'User';
                        $_SESSION['user_email'] = $user['email'];
                        $_SESSION['user_type'] = $user['user_type'];
                        $_SESSION['user_role'] = $roleName;
                        $_SESSION['role_id'] = $user['role_id'];
                        $_SESSION['user_image'] = $user['profile_image'] ?? 'default-avatar.png';
                        $_SESSION['login_time'] = time();
                        $_SESSION['logged_in'] = true;
                        
                        // Remember me
                        if ($remember) {
                            $token = generateToken(64);
                            $expires = date('Y-m-d H:i:s', strtotime('+30 days'));
                            
                            $token_sql = "UPDATE users SET remember_token = ?, token_expires = ? WHERE id = ?";
                            $token_stmt = $db->prepare($token_sql);
                            $token_stmt->execute([$token, $expires, $user['id']]);
                            
                            setcookie('remember_token', $token, [
                                'expires' => strtotime('+30 days'),
                                'path' => '/',
                                'httponly' => true,
                                'samesite' => 'Lax'
                            ]);
                        }
                        
                        // Update last login
                        $update_sql = "UPDATE users SET last_login = NOW() WHERE id = ?";
                        $update_stmt = $db->prepare($update_sql);
                        $update_stmt->execute([$user['id']]);
                        
                        // Log activity
                        logActivity($user['id'], 'User Logged In', 'auth', null, "User logged in as " . $userType);
                        
                        // Redirect based on user type
                        if ($user['user_type'] === 'admin') {
                            header("Location: ../admin/dashboard.php");
                        } elseif ($user['user_type'] === 'supplier') {
                            header("Location: ../supplier/dashboard.php");
                        } else {
                            header("Location: ../customer/dashboard.php");
                        }
                        exit();
                    }
                } else {
                    $error = 'Invalid email/username or password.';
                }
            } catch (PDOException $e) {
                $error = 'Login failed: ' . $e->getMessage();
            } catch (Exception $e) {
                $error = 'Login failed: ' . $e->getMessage();
            }
        }
    }
}

// Remember me cookie
if (!isset($_SESSION['user_id']) && isset($_COOKIE['remember_token'])) {
    try {
        $db = getDB();
        $token = $_COOKIE['remember_token'];
        
        $sql = "SELECT 
                    u.id, 
                    u.username,
                    u.full_name, 
                    u.email, 
                    u.user_type, 
                    u.status, 
                    u.profile_image,
                    u.role_id,
                    r.role_name
                FROM users u
                LEFT JOIN roles r ON u.role_id = r.id
                WHERE u.remember_token = ? 
                AND u.token_expires > NOW() 
                AND u.status = 'active'";
        $stmt = $db->prepare($sql);
        $stmt->execute([$token]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            session_regenerate_id(true);
            regenerateCSRFToken();
            
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['user_name'] = $user['full_name'] ?? 'User';
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_type'] = $user['user_type'];
            $_SESSION['user_role'] = $user['role_name'] ?? $user['user_type'];
            $_SESSION['role_id'] = $user['role_id'];
            $_SESSION['user_image'] = $user['profile_image'] ?? 'default-avatar.png';
            $_SESSION['login_time'] = time();
            $_SESSION['logged_in'] = true;
            
            if ($user['user_type'] === 'admin') {
                header("Location: ../admin/dashboard.php");
            } elseif ($user['user_type'] === 'supplier') {
                header("Location: ../supplier/dashboard.php");
            } else {
                header("Location: ../customer/dashboard.php");
            }
            exit();
        } else {
            setcookie('remember_token', '', time() - 3600, '/');
        }
    } catch (Exception $e) {
        setcookie('remember_token', '', time() - 3600, '/');
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - TechProcure Tanzania</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .login-container { max-width: 420px; width: 100%; }
        .login-card {
            background: #fff;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
        }
        .login-header {
            background: linear-gradient(135deg, #1a2a6c, #b21f1f, #fdbb4d);
            padding: 30px;
            text-align: center;
            color: #fff;
        }
        .login-header .logo { font-size: 3rem; margin-bottom: 10px; }
        .login-header h2 { font-size: 1.6rem; font-weight: 800; }
        .login-header p { opacity: 0.9; font-size: 0.9rem; }
        .login-header .badges {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-top: 12px;
            flex-wrap: wrap;
        }
        .login-header .badges .badge {
            padding: 5px 14px;
            font-weight: 600;
            font-size: 0.7rem;
            background: rgba(255,255,255,0.2);
            border: 1px solid rgba(255,255,255,0.3);
            border-radius: 20px;
        }
        .login-header .badges .badge.admin { border-color: #ff6b6b; }
        .login-header .badges .badge.supplier { border-color: #51cf66; }
        .login-header .badges .badge.customer { border-color: #4dabf7; }
        .login-body { padding: 35px 30px; }
        .form-group { margin-bottom: 18px; }
        .form-group label {
            font-weight: 600;
            font-size: 0.85rem;
            color: #333;
            display: block;
            margin-bottom: 6px;
        }
        .form-group label i { margin-right: 8px; color: #0d6efd; }
        .form-control {
            border-radius: 10px;
            padding: 11px 15px;
            border: 2px solid #e9ecef;
            font-size: 0.95rem;
            width: 100%;
            transition: all 0.3s;
            background: #f8f9fa;
        }
        .form-control:focus {
            border-color: #0d6efd;
            box-shadow: 0 0 0 4px rgba(13,110,253,0.1);
            outline: none;
            background: #fff;
        }
        .form-control.error { border-color: #dc3545; box-shadow: 0 0 0 4px rgba(220,53,69,0.1); }
        .input-group { position: relative; }
        .input-group .form-control { padding-right: 45px; }
        .password-toggle {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #6c757d;
            padding: 5px;
            cursor: pointer;
        }
        .role-selector {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
        }
        .role-option { cursor: pointer; }
        .role-option input[type="radio"] { display: none; }
        .role-option .role-card {
            padding: 12px 8px;
            text-align: center;
            border: 2px solid #e9ecef;
            border-radius: 10px;
            transition: all 0.3s;
            background: #f8f9fa;
        }
        .role-option input[type="radio"]:checked + .role-card {
            border-color: #0d6efd;
            background: #fff;
            box-shadow: 0 0 0 4px rgba(13,110,253,0.1);
            transform: scale(1.02);
        }
        .role-option .role-card i { font-size: 1.5rem; display: block; margin-bottom: 4px; }
        .role-option .role-card span { font-size: 0.75rem; font-weight: 600; }
        .role-option .role-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        }
        .role-option .role-card.admin-card i { color: #dc3545; }
        .role-option .role-card.supplier-card i { color: #198754; }
        .role-option .role-card.customer-card i { color: #0d6efd; }
        .role-option input[type="radio"]:checked + .role-card.admin-card { border-color: #dc3545; }
        .role-option input[type="radio"]:checked + .role-card.supplier-card { border-color: #198754; }
        .role-option input[type="radio"]:checked + .role-card.customer-card { border-color: #0d6efd; }
        .btn-login {
            background: linear-gradient(135deg, #0d6efd, #0b5ed7);
            border: none;
            border-radius: 10px;
            padding: 13px;
            font-weight: 700;
            font-size: 1rem;
            width: 100%;
            color: #fff;
            transition: all 0.3s;
            cursor: pointer;
        }
        .btn-login:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(13,110,253,0.4); }
        .btn-login:disabled { opacity: 0.7; cursor: not-allowed; transform: none; }
        .btn-login .spinner {
            display: none;
            width: 20px;
            height: 20px;
            border: 2px solid rgba(255,255,255,0.3);
            border-radius: 50%;
            border-top-color: #fff;
            animation: spin 0.8s linear infinite;
            margin-right: 10px;
            vertical-align: middle;
        }
        .btn-login.loading .spinner { display: inline-block; }
        .btn-login.loading .btn-text { display: none; }
        @keyframes spin { to { transform: rotate(360deg); } }
        .form-options {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin: 15px 0 20px;
        }
        .form-options .forgot a {
            color: #0d6efd;
            text-decoration: none;
            font-size: 0.85rem;
            font-weight: 500;
        }
        .form-options .forgot a:hover { text-decoration: underline; }
        .alert {
            border-radius: 10px;
            padding: 12px 15px;
            margin-bottom: 20px;
            border: none;
            display: flex;
            align-items: center;
            font-weight: 500;
        }
        .alert i { margin-right: 10px; font-size: 1.1rem; }
        .alert-danger { background: #fde8e8; color: #721c24; border-left: 4px solid #dc3545; }
        .alert-success { background: #e8f5e9; color: #1e7e34; border-left: 4px solid #28a745; }
        .divider {
            display: flex;
            align-items: center;
            margin: 20px 0;
        }
        .divider::before, .divider::after {
            content: '';
            flex: 1;
            border-bottom: 1px solid #e9ecef;
        }
        .divider span { padding: 0 15px; color: #6c757d; font-size: 0.85rem; }
        .social-buttons {
            display: flex;
            gap: 10px;
        }
        .social-buttons .btn-social {
            flex: 1;
            padding: 10px;
            border-radius: 10px;
            border: 2px solid #e9ecef;
            background: #fff;
            font-weight: 600;
            font-size: 0.85rem;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        .social-buttons .btn-social:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.08); }
        .social-buttons .btn-google { color: #ea4335; }
        .social-buttons .btn-google:hover { background: #ea4335; color: #fff; border-color: #ea4335; }
        .social-buttons .btn-facebook { color: #1877f2; }
        .social-buttons .btn-facebook:hover { background: #1877f2; color: #fff; border-color: #1877f2; }
        .register-section {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #e9ecef;
        }
        .register-section .title {
            text-align: center;
            color: #6c757d;
            font-weight: 600;
            font-size: 0.9rem;
            margin-bottom: 12px;
        }
        .register-buttons {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }
        .register-buttons .btn-register {
            padding: 10px;
            border-radius: 10px;
            border: 2px solid #e9ecef;
            background: #fff;
            font-weight: 600;
            font-size: 0.85rem;
            text-decoration: none;
            color: #333;
            text-align: center;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        .register-buttons .btn-register:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.08); }
        .register-buttons .btn-register-customer { border-color: #0d6efd; color: #0d6efd; }
        .register-buttons .btn-register-customer:hover { background: #0d6efd; color: #fff; }
        .register-buttons .btn-register-supplier { border-color: #198754; color: #198754; }
        .register-buttons .btn-register-supplier:hover { background: #198754; color: #fff; }
        .register-note {
            text-align: center;
            margin-top: 10px;
            font-size: 0.8rem;
            color: #6c757d;
        }
        .register-note a { color: #0d6efd; font-weight: 600; text-decoration: none; }
        .register-note a:hover { text-decoration: underline; }
        .security {
            text-align: center;
            margin-top: 15px;
            font-size: 0.8rem;
            color: #6c757d;
        }
        .security i { color: #22c55e; }
        .back-home {
            display: inline-block;
            color: rgba(255,255,255,0.8);
            text-decoration: none;
            font-size: 0.85rem;
            margin-top: 10px;
            transition: color 0.3s;
        }
        .back-home:hover { color: #fff; }
        @media (max-width: 576px) {
            .login-body { padding: 25px 20px; }
            .login-header { padding: 25px 20px; }
            .login-header h2 { font-size: 1.3rem; }
            .role-selector { gap: 6px; }
            .role-option .role-card { padding: 8px 4px; }
            .role-option .role-card i { font-size: 1.2rem; }
            .role-option .role-card span { font-size: 0.65rem; }
            .register-buttons { grid-template-columns: 1fr; }
            .social-buttons .btn-social { font-size: 0.75rem; padding: 8px; }
        }
    </style>
</head>
<body>
<div class="login-container">
    <div class="login-card">
        <!-- Header -->
        <div class="login-header">
            <div>
                <div class="logo"><i class="fas fa-microchip"></i></div>
                <h2>Welcome Back</h2>
                <p>Sign in to your TechProcure account</p>
                <div class="badges">
                    <span class="badge admin"><i class="fas fa-user-shield me-1"></i>Admin</span>
                    <span class="badge supplier"><i class="fas fa-store me-1"></i>Supplier</span>
                    <span class="badge customer"><i class="fas fa-user me-1"></i>Customer</span>
                </div>
                <a href="../index.php" class="back-home"><i class="fas fa-arrow-left me-1"></i>Back to Home</a>
            </div>
        </div>
        
        <!-- Body -->
        <div class="login-body">
            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="" id="loginForm">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                
                <div class="form-group">
                    <label><i class="fas fa-users"></i> I am a</label>
                    <div class="role-selector">
                        <label class="role-option">
                            <input type="radio" name="user_type" value="admin" <?php echo $selectedRole === 'admin' ? 'checked' : ''; ?>>
                            <div class="role-card admin-card">
                                <i class="fas fa-user-shield"></i>
                                <span>Admin</span>
                            </div>
                        </label>
                        <label class="role-option">
                            <input type="radio" name="user_type" value="supplier" <?php echo $selectedRole === 'supplier' ? 'checked' : ''; ?>>
                            <div class="role-card supplier-card">
                                <i class="fas fa-store"></i>
                                <span>Supplier</span>
                            </div>
                        </label>
                        <label class="role-option">
                            <input type="radio" name="user_type" value="customer" <?php echo $selectedRole === 'customer' ? 'checked' : ''; ?>>
                            <div class="role-card customer-card">
                                <i class="fas fa-user"></i>
                                <span>Customer</span>
                            </div>
                        </label>
                    </div>
                    <small class="text-muted" style="display:block;margin-top:5px;font-size:0.75rem;">
                        <i class="fas fa-info-circle"></i> Select your role to login
                    </small>
                </div>
                
                <div class="form-group">
                    <label for="email"><i class="fas fa-envelope"></i> Email or Username</label>
                    <input type="text" class="form-control" id="email" name="email" 
                           value="<?php echo htmlspecialchars($email); ?>" 
                           placeholder="Enter your email or username" required autofocus>
                </div>
                
                <div class="form-group">
                    <label for="password"><i class="fas fa-lock"></i> Password</label>
                    <div class="input-group">
                        <input type="password" class="form-control" id="password" name="password" 
                               placeholder="Enter your password" required>
                        <button type="button" class="password-toggle" onclick="togglePassword()">
                            <i class="fas fa-eye" id="toggleIcon"></i>
                        </button>
                    </div>
                </div>
                
                <div class="form-options">
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" id="remember" name="remember">
                        <label class="form-check-label" for="remember">Remember Me</label>
                    </div>
                    <div class="forgot">
                        <a href="forgot-password.php"><i class="fas fa-key me-1"></i>Forgot Password?</a>
                    </div>
                </div>
                
                <button type="submit" class="btn-login" id="loginBtn">
                    <span class="spinner" id="loginSpinner"></span>
                    <span class="btn-text"><i class="fas fa-sign-in-alt me-2"></i>Sign In</span>
                </button>
            </form>
            
            <div class="divider"><span>Or continue with</span></div>
            
            <div class="social-buttons">
                <button type="button" class="btn-social btn-google" onclick="alert('Google login coming soon!')">
                    <i class="fab fa-google"></i> Google
                </button>
                <button type="button" class="btn-social btn-facebook" onclick="alert('Facebook login coming soon!')">
                    <i class="fab fa-facebook"></i> Facebook
                </button>
            </div>
            
            <div class="register-section">
                <div class="title"><i class="fas fa-user-plus me-2"></i>New to TechProcure?</div>
                <div class="register-buttons">
                    <a href="register.php?type=customer" class="btn-register btn-register-customer">
                        <i class="fas fa-user"></i> Customer
                    </a>
                    <a href="register.php?type=supplier" class="btn-register btn-register-supplier">
                        <i class="fas fa-store"></i> Supplier
                    </a>
                </div>
                <div class="register-note">
                    Already have an account? <a href="#" onclick="document.getElementById('loginForm').scrollIntoView({behavior:'smooth'});return false;">Sign in above</a>
                </div>
            </div>
            
            <div class="security">
                <i class="fas fa-shield-alt me-1"></i> Your data is secure with us
                <span class="mx-2">|</span>
                <i class="fas fa-lock me-1"></i> SSL Encrypted
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    function togglePassword() {
        const input = document.getElementById('password');
        const icon = document.getElementById('toggleIcon');
        if (input.type === 'password') {
            input.type = 'text';
            icon.className = 'fas fa-eye-slash';
        } else {
            input.type = 'password';
            icon.className = 'fas fa-eye';
        }
    }
    
    document.getElementById('loginForm').addEventListener('submit', function(e) {
        const email = document.getElementById('email');
        const password = document.getElementById('password');
        const role = document.querySelector('input[name="user_type"]:checked');
        let valid = true;
        
        email.classList.remove('error');
        password.classList.remove('error');
        
        if (!email.value.trim()) {
            email.classList.add('error');
            valid = false;
        }
        if (!password.value) {
            password.classList.add('error');
            valid = false;
        }
        if (!role) {
            valid = false;
            alert('Please select your role (Admin, Supplier, or Customer)');
        }
        
        if (!valid) {
            e.preventDefault();
            return false;
        }
        
        const btn = document.getElementById('loginBtn');
        btn.classList.add('loading');
        btn.disabled = true;
    });
    
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('.alert').forEach(function(alert) {
            setTimeout(function() {
                alert.style.display = 'none';
            }, 5000);
        });
    });
    
    document.getElementById('password').addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            document.getElementById('loginForm').submit();
        }
    });
    
    document.querySelectorAll('input[name="user_type"]').forEach(function(radio) {
        radio.addEventListener('change', function() {
            document.querySelectorAll('.role-option .role-card').forEach(function(card) {
                card.style.borderColor = '#e9ecef';
                card.style.background = '#f8f9fa';
                card.style.transform = 'scale(1)';
            });
            const card = this.closest('.role-option').querySelector('.role-card');
            card.style.borderColor = '#0d6efd';
            card.style.background = '#fff';
            card.style.transform = 'scale(1.02)';
        });
    });
    
    // Demo credentials - double click email field
    document.getElementById('email').addEventListener('dblclick', function() {
        if (this.value === '') {
            const role = document.querySelector('input[name="user_type"]:checked');
            if (role) {
                const credentials = {
                    'admin': { email: 'admin@techprocure.com', username: 'admin' },
                    'supplier': { email: 'supplier@techprocure.com', username: 'supplier' },
                    'customer': { email: 'customer@techprocure.com', username: 'customer' }
                };
                this.value = credentials[role.value].email;
                document.getElementById('password').value = 'demo123';
            } else {
                alert('Please select your role first (Admin, Supplier, or Customer)');
            }
        }
    });
</script>
</body>
</html>