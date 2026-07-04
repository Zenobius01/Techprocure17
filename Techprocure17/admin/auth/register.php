<?php
/**
 * TechProcure Tanzania - Admin Registration Page
 * File: admin/auth/register.php
 * Description: Allows new admin users to register
 */

// Start session if not started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include configuration files
require_once '../../includes/config.php';
require_once '../../includes/db.php';
require_once '../../includes/functions.php';

// If already logged in as admin, redirect to dashboard
if (isset($_SESSION['user_id']) && isset($_SESSION['user_role']) && $_SESSION['user_role'] == 'admin') {
    header("Location: ../dashboard.php");
    exit();
}

// If logged in as other user, redirect to home
if (isset($_SESSION['user_id'])) {
    header("Location: ../../index.php");
    exit();
}

$error = '';
$success = '';

// Generate CSRF token
$csrf_token = generateCSRFToken();

// Check if this is first admin registration
$db = getDB();
$admin_count = 0;
try {
    $stmt = $db->query("SELECT COUNT(*) as total FROM users WHERE user_type = 'admin'");
    $admin_count = (int)$stmt->fetchColumn();
} catch (PDOException $e) {
    $admin_count = 0;
}

$is_first_admin = ($admin_count == 0);

// Process registration form
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        $error = 'Security token validation failed. Please try again.';
    } else {
        $username = trim($_POST['username']);
        $email = trim($_POST['email']);
        $full_name = trim($_POST['full_name']);
        $phone = trim($_POST['phone']);
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];
        $admin_key = trim($_POST['admin_key'] ?? '');
        $terms = isset($_POST['terms']) ? true : false;
        
        // Validation
        if (empty($username) || empty($email) || empty($full_name) || empty($password)) {
            $error = "Please fill in all required fields.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Invalid email address.";
        } elseif (strlen($password) < 6) {
            $error = "Password must be at least 6 characters long.";
        } elseif ($password !== $confirm_password) {
            $error = "Passwords do not match.";
        } elseif (!$terms) {
            $error = "You must agree to the Terms & Conditions.";
        } elseif (!$is_first_admin && $admin_key !== 'ADMIN_SECRET_KEY_2024') {
            $error = "Invalid admin registration key. Only authorized personnel can create admin accounts.";
        } else {
            try {
                // Check if username exists
                $check_stmt = $db->prepare("SELECT id FROM users WHERE username = ?");
                $check_stmt->execute([$username]);
                if ($check_stmt->rowCount() > 0) {
                    $error = "Username already taken. Please choose another.";
                }
                
                // Check if email exists
                $check_stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
                $check_stmt->execute([$email]);
                if ($check_stmt->rowCount() > 0) {
                    $error = "Email already registered.";
                }
                
                if (empty($error)) {
                    // Get admin role ID
                    $role_stmt = $db->prepare("SELECT id FROM roles WHERE role_name = 'admin'");
                    $role_stmt->execute();
                    $role = $role_stmt->fetch();
                    
                    if (!$role) {
                        // Insert admin role if not exists
                        $db->exec("INSERT INTO roles (role_name, role_description) VALUES ('admin', 'System Administrator')");
                        $role_id = $db->lastInsertId();
                    } else {
                        $role_id = $role['id'];
                    }
                    
                    // Hash password
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    
                    // Insert user
                    $insert_sql = "INSERT INTO users (
                        username, 
                        email, 
                        password_hash, 
                        full_name, 
                        phone, 
                        role_id, 
                        user_type, 
                        status, 
                        email_verified, 
                        created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, 'admin', 'active', 1, NOW())";
                    
                    $insert_stmt = $db->prepare($insert_sql);
                    $insert_stmt->execute([
                        $username,
                        $email,
                        $hashed_password,
                        $full_name,
                        $phone,
                        $role_id
                    ]);
                    
                    $user_id = $db->lastInsertId();
                    
                    // Log activity
                    try {
                        $log_sql = "INSERT INTO activity_logs (user_id, action, entity_type, ip_address, user_agent, created_at) 
                                    VALUES (?, 'Admin Registered', 'user', ?, ?, NOW())";
                        $log_stmt = $db->prepare($log_sql);
                        $log_stmt->execute([$user_id, $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '']);
                    } catch (PDOException $e) {
                        // Continue even if logging fails
                    }
                    
                    $success = "Admin account created successfully! You can now login.";
                    
                    // Auto redirect to login after 2 seconds
                    header("refresh:2;url=login.php");
                }
            } catch (PDOException $e) {
                $error = "Registration failed: " . $e->getMessage();
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Registration - TechProcure Tanzania</title>
    
    <!-- CSS Dependencies -->
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
            padding: 40px 20px;
        }
        
        .register-container {
            width: 100%;
            max-width: 500px;
        }
        
        .register-card {
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
        
        .register-header {
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            padding: 30px;
            text-align: center;
            color: white;
        }
        
        .register-header .logo-icon {
            font-size: 2.5rem;
            margin-bottom: 10px;
        }
        
        .register-header .admin-badge {
            display: inline-block;
            background: rgba(255,255,255,0.15);
            padding: 4px 12px;
            border-radius: 50px;
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 10px;
        }
        
        .register-header h2 {
            font-size: 1.6rem;
            font-weight: 700;
            margin-bottom: 5px;
        }
        
        .register-header p {
            opacity: 0.8;
            font-size: 0.85rem;
            margin-bottom: 0;
        }
        
        .register-header .back-home {
            display: inline-block;
            color: rgba(255,255,255,0.7);
            text-decoration: none;
            font-size: 0.85rem;
            margin-top: 12px;
            transition: all 0.3s;
        }
        
        .register-header .back-home:hover {
            color: white;
        }
        
        .register-body {
            padding: 35px;
        }
        
        .form-group {
            margin-bottom: 18px;
        }
        
        .form-group label {
            font-weight: 500;
            margin-bottom: 6px;
            color: #333;
            display: block;
            font-size: 0.85rem;
        }
        
        .form-group label i {
            margin-right: 6px;
            color: #0d6efd;
        }
        
        .required:after {
            content: " *";
            color: red;
        }
        
        .form-control {
            border-radius: 10px;
            padding: 10px 15px;
            border: 1px solid #e0e0e0;
            transition: all 0.3s;
            font-size: 0.9rem;
        }
        
        .form-control:focus {
            border-color: #0d6efd;
            box-shadow: 0 0 0 3px rgba(13,110,253,0.1);
            outline: none;
        }
        
        .btn-register {
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
        
        .btn-register:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(13,110,253,0.3);
        }
        
        .btn-register:disabled {
            opacity: 0.7;
            cursor: not-allowed;
            transform: none;
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
        
        .alert-info {
            background: #d1ecf1;
            color: #0c5460;
            border-left: 4px solid #17a2b8;
        }
        
        .password-strength {
            height: 4px;
            margin-top: 8px;
            border-radius: 2px;
            transition: all 0.3s;
        }
        
        .weak { background: #dc3545; width: 33%; }
        .medium { background: #ffc107; width: 66%; }
        .strong { background: #28a745; width: 100%; }
        
        .login-link {
            text-align: center;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #e0e0e0;
        }
        
        .login-link a {
            color: #0d6efd;
            text-decoration: none;
            font-weight: 500;
        }
        
        .login-link a:hover {
            text-decoration: underline;
        }
        
        .row {
            display: flex;
            gap: 15px;
        }
        
        .col {
            flex: 1;
        }
        
        .info-box {
            background: #f0f7ff;
            border-radius: 10px;
            padding: 12px 15px;
            margin-bottom: 15px;
            border-left: 4px solid #0d6efd;
            font-size: 0.85rem;
        }
        
        .info-box i {
            color: #0d6efd;
            margin-right: 8px;
        }
        
        @media (max-width: 576px) {
            .register-body {
                padding: 25px;
            }
            .row {
                flex-direction: column;
                gap: 0;
            }
        }
    </style>
</head>
<body>
    <div class="register-container">
        <div class="register-card">
            <!-- Header -->
            <div class="register-header">
                <div class="logo-icon">
                    <i class="fas fa-microchip"></i>
                </div>
                <div class="admin-badge">
                    <i class="fas fa-shield-alt me-1"></i> Admin Registration
                </div>
                <h2><?php echo $is_first_admin ? 'Create Admin Account' : 'Admin Registration'; ?></h2>
                <p><?php echo $is_first_admin ? 'Set up the first administrator account' : 'Register as a system administrator'; ?></p>
                <a href="../../index.php" class="back-home">
                    <i class="fas fa-arrow-left me-1"></i> Back to Home
                </a>
            </div>
            
            <!-- Body -->
            <div class="register-body">
                <?php if($success): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle me-2"></i> <?php echo $success; ?>
                        <div class="mt-2">
                            <a href="login.php" class="btn btn-success btn-sm">
                                <i class="fas fa-sign-in-alt me-1"></i> Login Now
                            </a>
                        </div>
                    </div>
                <?php endif; ?>
                
                <?php if($error): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i> <?php echo $error; ?>
                    </div>
                <?php endif; ?>
                
                <?php if(!$success): ?>
                
                <?php if($is_first_admin): ?>
                    <div class="info-box">
                        <i class="fas fa-info-circle"></i>
                        <strong>First Admin Setup:</strong> This is the first admin account. No registration key required.
                    </div>
                <?php else: ?>
                    <div class="info-box">
                        <i class="fas fa-shield-alt"></i>
                        <strong>Authorization Required:</strong> Admin registration requires a valid registration key.
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    
                    <div class="row">
                        <div class="col">
                            <div class="form-group">
                                <label class="required"><i class="fas fa-user-circle"></i> Username</label>
                                <input type="text" name="username" class="form-control" 
                                       value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" 
                                       placeholder="Choose a username" required>
                            </div>
                        </div>
                        <div class="col">
                            <div class="form-group">
                                <label class="required"><i class="fas fa-envelope"></i> Email</label>
                                <input type="email" name="email" class="form-control" 
                                       value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" 
                                       placeholder="admin@example.com" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="required"><i class="fas fa-user"></i> Full Name</label>
                        <input type="text" name="full_name" class="form-control" 
                               value="<?php echo htmlspecialchars($_POST['full_name'] ?? ''); ?>" 
                               placeholder="Enter your full name" required>
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-phone"></i> Phone Number</label>
                        <input type="tel" name="phone" class="form-control" 
                               value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>" 
                               placeholder="+255 XXX XXX XXX">
                    </div>
                    
                    <div class="row">
                        <div class="col">
                            <div class="form-group">
                                <label class="required"><i class="fas fa-lock"></i> Password</label>
                                <input type="password" name="password" id="password" class="form-control" 
                                       placeholder="Minimum 6 characters" required>
                                <div class="password-strength" id="passwordStrength"></div>
                                <small class="text-muted">At least 6 characters with letters and numbers</small>
                            </div>
                        </div>
                        <div class="col">
                            <div class="form-group">
                                <label class="required"><i class="fas fa-lock"></i> Confirm Password</label>
                                <input type="password" name="confirm_password" class="form-control" 
                                       placeholder="Confirm your password" required>
                            </div>
                        </div>
                    </div>
                    
                    <?php if(!$is_first_admin): ?>
                        <div class="form-group">
                            <label class="required"><i class="fas fa-key"></i> Admin Registration Key</label>
                            <input type="password" name="admin_key" class="form-control" 
                                   placeholder="Enter admin registration key" required>
                            <small class="text-muted">Contact the system administrator for the registration key</small>
                        </div>
                    <?php endif; ?>
                    
                    <div class="form-check mb-3">
                        <input type="checkbox" class="form-check-input" id="terms" name="terms" required>
                        <label class="form-check-label" for="terms">
                            I agree to the <a href="#" data-bs-toggle="modal" data-bs-target="#termsModal">Terms & Conditions</a>
                        </label>
                    </div>
                    
                    <button type="submit" class="btn-register">
                        <i class="fas fa-user-plus me-2"></i> <?php echo $is_first_admin ? 'Create Admin Account' : 'Register as Admin'; ?>
                    </button>
                </form>
                
                <div class="login-link">
                    <p class="mb-0">Already have an admin account? <a href="login.php">Login here</a></p>
                </div>
                
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Terms Modal -->
    <div class="modal fade" id="termsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">Terms & Conditions for Administrators</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" style="max-height: 400px; overflow-y: auto;">
                    <h6>1. Administrator Responsibilities</h6>
                    <p>As an administrator, you are responsible for managing the platform, users, and content in accordance with company policies.</p>
                    
                    <h6>2. Access Control</h6>
                    <p>Admin accounts provide full access to the system. You are responsible for maintaining the security of your admin credentials.</p>
                    
                    <h6>3. Data Privacy</h6>
                    <p>You must handle user data with care and in compliance with data protection regulations.</p>
                    
                    <h6>4. System Integrity</h6>
                    <p>You agree to use your admin access responsibly and not to compromise the system's security or integrity.</p>
                    
                    <h6>5. Confidentiality</h6>
                    <p>You must maintain confidentiality of sensitive business information and user data.</p>
                    
                    <h6>6. Account Suspension</h6>
                    <p>The system administrator reserves the right to suspend or revoke admin accounts that violate these terms.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary" data-bs-dismiss="modal">I Agree</button>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Password strength checker
        const passwordInput = document.getElementById('password');
        const strengthDiv = document.getElementById('passwordStrength');
        
        passwordInput.addEventListener('keyup', function() {
            const password = this.value;
            let strength = 0;
            
            if (password.length >= 6) strength++;
            if (password.match(/[a-z]+/)) strength++;
            if (password.match(/[A-Z]+/)) strength++;
            if (password.match(/[0-9]+/)) strength++;
            
            strengthDiv.classList.remove('weak', 'medium', 'strong');
            
            if (strength <= 2) {
                strengthDiv.classList.add('weak');
            } else if (strength <= 3) {
                strengthDiv.classList.add('medium');
            } else {
                strengthDiv.classList.add('strong');
            }
        });
        
        // Auto-hide alerts after 5 seconds
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);
    </script>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>