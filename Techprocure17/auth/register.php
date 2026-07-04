<?php
/**
 * TechProcure Tanzania - Registration Page
 * File: auth/register.php
 * Description: User registration for customers and suppliers
 */

// Start session if not started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include configuration files
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

// Get database connection using getDB() function from functions.php
try {
    $db = getDB();
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}

// =============================================
// DEFINE MISSING FUNCTIONS IF NOT EXISTS
// =============================================
if (!function_exists('generateCSRFToken')) {
    function generateCSRFToken() {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            $_SESSION['csrf_token_time'] = time();
        }
        return $_SESSION['csrf_token'];
    }
}

if (!function_exists('verifyCSRFToken')) {
    function verifyCSRFToken($token) {
        if (empty($token) || empty($_SESSION['csrf_token'])) {
            return false;
        }
        // Check if token is expired (1 hour)
        if (isset($_SESSION['csrf_token_time']) && (time() - $_SESSION['csrf_token_time']) > 3600) {
            unset($_SESSION['csrf_token']);
            unset($_SESSION['csrf_token_time']);
            return false;
        }
        return hash_equals($_SESSION['csrf_token'], $token);
    }
}

if (!function_exists('log_activity')) {
    function log_activity($user_type, $user_id, $action, $description) {
        try {
            $db = getDB();
            $sql = "INSERT INTO activity_logs (user_id, action, entity_type, description, ip_address, user_agent, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, NOW())";
            $stmt = $db->prepare($sql);
            $stmt->execute([
                $user_id,
                $action,
                $user_type,
                $description,
                $_SERVER['REMOTE_ADDR'] ?? '',
                $_SERVER['HTTP_USER_AGENT'] ?? ''
            ]);
        } catch (Exception $e) {
            // Silent fail for logging
        }
    }
}

// If already logged in, redirect to home
if (isset($_SESSION['user_id']) && isLoggedIn()) {
    redirectToDashboard();
    exit();
}

$error = '';
$success = '';
$user_type = isset($_GET['type']) && $_GET['type'] == 'supplier' ? 'supplier' : 'customer';
$active_tab = $user_type;

// Generate CSRF token
$csrf_token = generateCSRFToken();

// Process registration form
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        $error = 'Invalid security token. Please try again.';
    } else {
        $user_type = $_POST['user_type'] ?? 'customer';
        
        if ($user_type == 'customer') {
            // =============================================
            // CUSTOMER REGISTRATION
            // =============================================
            $full_name = sanitize(trim($_POST['full_name'] ?? ''));
            $username = sanitize(trim($_POST['username'] ?? ''));
            $email = sanitize(trim($_POST['email'] ?? ''));
            $phone = sanitize(trim($_POST['phone'] ?? ''));
            $company_name = sanitize(trim($_POST['company_name'] ?? ''));
            $company_address = sanitize(trim($_POST['company_address'] ?? ''));
            $city = sanitize(trim($_POST['city'] ?? ''));
            $password = $_POST['password'] ?? '';
            $confirm_password = $_POST['confirm_password'] ?? '';
            $terms = isset($_POST['terms']) ? true : false;
            
            // Validation
            if (empty($full_name) || empty($username) || empty($email) || empty($password)) {
                $error = "Please fill in all required fields.";
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = "Invalid email address.";
            } elseif (strlen($password) < 6) {
                $error = "Password must be at least 6 characters long.";
            } elseif ($password !== $confirm_password) {
                $error = "Passwords do not match.";
            } elseif (!$terms) {
                $error = "You must agree to the Terms & Conditions.";
            } else {
                try {
                    // Check if username exists
                    $check_sql = "SELECT id FROM users WHERE username = ?";
                    $check_stmt = $db->prepare($check_sql);
                    $check_stmt->execute([$username]);
                    if ($check_stmt->rowCount() > 0) {
                        $error = "Username already taken. Please choose another.";
                    }
                    
                    // Check if email exists
                    $check_sql = "SELECT id FROM users WHERE email = ?";
                    $check_stmt = $db->prepare($check_sql);
                    $check_stmt->execute([$email]);
                    if ($check_stmt->rowCount() > 0) {
                        $error = "Email already registered. Please <a href='login.php'>login here</a>";
                    }
                    
                    if (empty($error)) {
                        // Hash password
                        $hashed_password = hashPassword($password);
                        
                        // Insert user
                        $insert_sql = "INSERT INTO users (username, email, password_hash, full_name, phone, company_name, address, city, user_type, status, created_at) 
                                       VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'customer', 'active', NOW())";
                        $insert_stmt = $db->prepare($insert_sql);
                        $insert_stmt->execute([
                            $username,
                            $email,
                            $hashed_password,
                            $full_name,
                            $phone,
                            $company_name,
                            $company_address,
                            $city
                        ]);
                        
                        $user_id = $db->lastInsertId();
                        
                        // Log registration
                        log_activity('customer', $user_id, 'register', 'New customer registered');
                        
                        $success = "Registration successful! You can now login to your account.";
                        $_POST = array();
                        
                        // Generate new CSRF token after successful submission
                        unset($_SESSION['csrf_token']);
                        unset($_SESSION['csrf_token_time']);
                        $csrf_token = generateCSRFToken();
                    }
                } catch (PDOException $e) {
                    $error = "Registration failed: " . $e->getMessage();
                }
            }
        } 
        elseif ($user_type == 'supplier') {
            // =============================================
            // SUPPLIER REGISTRATION
            // =============================================
            $company_name = sanitize(trim($_POST['company_name'] ?? ''));
            $registration_number = sanitize(trim($_POST['registration_number'] ?? ''));
            $tax_id = sanitize(trim($_POST['tax_id'] ?? ''));
            $contact_person = sanitize(trim($_POST['contact_person'] ?? ''));
            $email = sanitize(trim($_POST['email'] ?? ''));
            $phone = sanitize(trim($_POST['phone'] ?? ''));
            $address = sanitize(trim($_POST['address'] ?? ''));
            $city = sanitize(trim($_POST['city'] ?? ''));
            $region = sanitize(trim($_POST['region'] ?? ''));
            $business_description = sanitize(trim($_POST['business_description'] ?? ''));
            $password = $_POST['password'] ?? '';
            $confirm_password = $_POST['confirm_password'] ?? '';
            $terms = isset($_POST['terms']) ? true : false;
            
            // Validation
            if (empty($company_name) || empty($contact_person) || empty($email) || empty($password)) {
                $error = "Please fill in all required fields.";
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = "Invalid email address.";
            } elseif (strlen($password) < 6) {
                $error = "Password must be at least 6 characters long.";
            } elseif ($password !== $confirm_password) {
                $error = "Passwords do not match.";
            } elseif (!$terms) {
                $error = "You must agree to the Terms & Conditions.";
            } else {
                try {
                    // Check if email exists
                    $check_sql = "SELECT id FROM users WHERE email = ?";
                    $check_stmt = $db->prepare($check_sql);
                    $check_stmt->execute([$email]);
                    if ($check_stmt->rowCount() > 0) {
                        $error = "Email already registered. Please <a href='login.php'>login here</a>";
                    }
                    
                    if (empty($error)) {
                        // Generate username from email
                        $username = explode('@', $email)[0] . rand(100, 999);
                        
                        // Hash password
                        $hashed_password = hashPassword($password);
                        
                        // Start transaction
                        $db->beginTransaction();
                        
                        // Insert user
                        $insert_sql = "INSERT INTO users (username, email, password_hash, full_name, phone, company_name, user_type, status, created_at) 
                                       VALUES (?, ?, ?, ?, ?, ?, 'supplier', 'pending', NOW())";
                        $insert_stmt = $db->prepare($insert_sql);
                        $insert_stmt->execute([
                            $username,
                            $email,
                            $hashed_password,
                            $contact_person,
                            $phone,
                            $company_name
                        ]);
                        $user_id = $db->lastInsertId();
                        
                        // Insert supplier profile
                        $supplier_sql = "INSERT INTO suppliers (user_id, company_name, registration_number, tax_id, contact_person, email, phone, address, city, region, business_description, approval_status, created_at) 
                                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())";
                        $supplier_stmt = $db->prepare($supplier_sql);
                        $supplier_stmt->execute([
                            $user_id,
                            $company_name,
                            $registration_number,
                            $tax_id,
                            $contact_person,
                            $email,
                            $phone,
                            $address,
                            $city,
                            $region,
                            $business_description
                        ]);
                        
                        $db->commit();
                        
                        log_activity('supplier', $user_id, 'register', 'New supplier registered - pending approval');
                        
                        $success = "Supplier registration submitted! Our team will review your application within 24-48 hours.";
                        $_POST = array();
                        
                        // Generate new CSRF token
                        unset($_SESSION['csrf_token']);
                        unset($_SESSION['csrf_token_time']);
                        $csrf_token = generateCSRFToken();
                    }
                } catch (PDOException $e) {
                    $db->rollBack();
                    $error = "Registration failed: " . $e->getMessage();
                }
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
    <meta name="csrf-token" content="<?php echo $csrf_token; ?>">
    <title>Register - TechProcure Tanzania</title>
    
    <!-- CSS Dependencies -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary-color: #0d6efd;
            --primary-dark: #0b5ed7;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%);
            min-height: 100vh;
            padding: 40px 0;
        }
        
        .register-container {
            max-width: 700px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .register-card {
            background: white;
            border-radius: 24px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.4);
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
            background: linear-gradient(135deg, #1a2a6c, #b21f1f, #fdbb4d);
            padding: 30px;
            text-align: center;
            color: white;
        }
        
        .register-header .logo-icon {
            font-size: 2.5rem;
            margin-bottom: 10px;
        }
        
        .register-header h2 {
            font-size: 1.6rem;
            font-weight: 700;
            margin-bottom: 5px;
        }
        
        .register-header p {
            opacity: 0.9;
            font-size: 0.85rem;
            margin-bottom: 0;
        }
        
        .register-header .back-home {
            display: inline-block;
            color: rgba(255,255,255,0.8);
            text-decoration: none;
            font-size: 0.85rem;
            margin-top: 10px;
            transition: all 0.3s;
        }
        
        .register-header .back-home:hover {
            color: white;
        }
        
        .register-body {
            padding: 35px;
        }
        
        .nav-tabs {
            border: none;
            margin-bottom: 25px;
            gap: 8px;
        }
        
        .nav-tabs .nav-link {
            border: none;
            border-radius: 10px;
            padding: 12px 20px;
            font-weight: 600;
            color: #666;
            transition: all 0.3s;
            background: #f8f9fa;
        }
        
        .nav-tabs .nav-link:hover {
            background: #e9ecef;
        }
        
        .nav-tabs .nav-link.active {
            background: linear-gradient(135deg, #0d6efd, #0b5ed7);
            color: white;
        }
        
        .nav-tabs .nav-link i {
            margin-right: 8px;
        }
        
        .form-group {
            margin-bottom: 16px;
        }
        
        .form-group label {
            font-weight: 500;
            margin-bottom: 5px;
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
            border: 2px solid #e9ecef;
            transition: all 0.3s;
            font-size: 0.9rem;
            width: 100%;
        }
        
        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 4px rgba(13,110,253,0.1);
            outline: none;
        }
        
        .form-control.is-invalid {
            border-color: #dc3545;
            box-shadow: 0 0 0 4px rgba(220,53,69,0.1);
        }
        
        .form-control.is-valid {
            border-color: #28a745;
            box-shadow: 0 0 0 4px rgba(40,167,69,0.1);
        }
        
        select.form-control {
            cursor: pointer;
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%23333' d='M6 8L1 3h10z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 12px center;
            padding-right: 35px;
        }
        
        .btn-register {
            background: linear-gradient(135deg, #0d6efd, #0b5ed7);
            border: none;
            border-radius: 10px;
            padding: 13px;
            font-weight: 600;
            font-size: 0.95rem;
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
        
        .btn-register-supplier {
            background: linear-gradient(135deg, #198754, #157347);
        }
        
        .btn-register-supplier:hover {
            box-shadow: 0 5px 15px rgba(25,135,84,0.3);
        }
        
        .password-strength {
            height: 4px;
            margin-top: 6px;
            border-radius: 2px;
            transition: all 0.3s;
        }
        
        .password-strength.weak { background: #dc3545; width: 33%; }
        .password-strength.medium { background: #ffc107; width: 66%; }
        .password-strength.strong { background: #28a745; width: 100%; }
        
        .password-strength-text {
            font-size: 0.7rem;
            margin-top: 3px;
        }
        
        .password-strength-text.weak { color: #dc3545; }
        .password-strength-text.medium { color: #856404; }
        .password-strength-text.strong { color: #28a745; }
        
        .alert {
            border-radius: 10px;
            padding: 12px 15px;
            margin-bottom: 20px;
            border: none;
        }
        
        .alert-danger {
            background: #fde8e8;
            color: #721c24;
            border-left: 4px solid #dc3545;
        }
        
        .alert-success {
            background: #e8f5e9;
            color: #1e7e34;
            border-left: 4px solid #28a745;
        }
        
        .alert-info {
            background: #d1ecf1;
            color: #0c5460;
            border-left: 4px solid #17a2b8;
        }
        
        .login-link {
            text-align: center;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #e9ecef;
        }
        
        .login-link p {
            margin-bottom: 0;
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
        
        .form-check {
            margin: 15px 0;
        }
        
        .form-check-input:checked {
            background-color: #0d6efd;
            border-color: #0d6efd;
        }
        
        .loading-spinner {
            display: none;
        }
        
        .btn-register .loading-spinner {
            display: inline-block;
            width: 18px;
            height: 18px;
            border: 2px solid rgba(255,255,255,0.3);
            border-radius: 50%;
            border-top-color: #fff;
            animation: spin 0.8s ease-in-out infinite;
            margin-right: 8px;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        .text-muted {
            font-size: 0.75rem;
        }
        
        @media (max-width: 576px) {
            .register-body {
                padding: 20px;
            }
            .row {
                flex-direction: column;
                gap: 0;
            }
            .nav-tabs .nav-link {
                padding: 10px 12px;
                font-size: 0.8rem;
            }
            .register-header h2 {
                font-size: 1.3rem;
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
                <h2>Create Account</h2>
                <p>Join TechProcure Tanzania</p>
                <a href="../index.php" class="back-home">
                    <i class="fas fa-arrow-left me-1"></i> Back to Home
                </a>
            </div>
            
            <!-- Body -->
            <div class="register-body">
                <?php if($success): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle me-2"></i> <?php echo $success; ?>
                        <?php if(strpos($success, 'successful') !== false): ?>
                            <div class="mt-2">
                                <a href="login.php" class="btn btn-success btn-sm">
                                    <i class="fas fa-sign-in-alt me-1"></i> Login Now
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                
                <?php if($error): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i> <?php echo $error; ?>
                    </div>
                <?php endif; ?>
                
                <?php if(!$success || ($success && strpos($success, 'submitted') !== false)): ?>
                
                <!-- Tab Navigation -->
                <ul class="nav nav-tabs" id="registerTab" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link <?php echo $active_tab == 'customer' ? 'active' : ''; ?>" id="customer-tab" data-bs-toggle="tab" data-bs-target="#customer" type="button" role="tab">
                            <i class="fas fa-user"></i> Customer
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link <?php echo $active_tab == 'supplier' ? 'active' : ''; ?>" id="supplier-tab" data-bs-toggle="tab" data-bs-target="#supplier" type="button" role="tab">
                            <i class="fas fa-store"></i> Supplier
                        </button>
                    </li>
                </ul>
                
                <!-- Tab Content -->
                <div class="tab-content">
                    <!-- Customer Registration -->
                    <div class="tab-pane fade <?php echo $active_tab == 'customer' ? 'show active' : ''; ?>" id="customer" role="tabpanel">
                        <form method="POST" action="" id="customerForm">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                            <input type="hidden" name="user_type" value="customer">
                            
                            <div class="row">
                                <div class="col">
                                    <div class="form-group">
                                        <label class="required"><i class="fas fa-user"></i> Full Name</label>
                                        <input type="text" name="full_name" class="form-control" 
                                               value="<?php echo htmlspecialchars($_POST['full_name'] ?? ''); ?>" 
                                               placeholder="Enter your full name" required>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col">
                                    <div class="form-group">
                                        <label class="required"><i class="fas fa-user-circle"></i> Username</label>
                                        <input type="text" name="username" class="form-control" 
                                               value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" 
                                               placeholder="Choose a username" required>
                                        <small class="text-muted">This will be your unique login ID</small>
                                    </div>
                                </div>
                                <div class="col">
                                    <div class="form-group">
                                        <label class="required"><i class="fas fa-envelope"></i> Email</label>
                                        <input type="email" name="email" class="form-control" 
                                               value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" 
                                               placeholder="your@email.com" required>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col">
                                    <div class="form-group">
                                        <label><i class="fas fa-phone"></i> Phone Number</label>
                                        <input type="tel" name="phone" class="form-control" 
                                               value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>" 
                                               placeholder="+255 XXX XXX XXX">
                                    </div>
                                </div>
                                <div class="col">
                                    <div class="form-group">
                                        <label><i class="fas fa-building"></i> Company Name</label>
                                        <input type="text" name="company_name" class="form-control" 
                                               value="<?php echo htmlspecialchars($_POST['company_name'] ?? ''); ?>" 
                                               placeholder="Your company name">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col">
                                    <div class="form-group">
                                        <label><i class="fas fa-map-marker-alt"></i> City</label>
                                        <select name="city" class="form-control">
                                            <option value="">Select City</option>
                                            <option value="Dar es Salaam" <?php echo (($_POST['city'] ?? '') == 'Dar es Salaam') ? 'selected' : ''; ?>>Dar es Salaam</option>
                                            <option value="Arusha" <?php echo (($_POST['city'] ?? '') == 'Arusha') ? 'selected' : ''; ?>>Arusha</option>
                                            <option value="Mwanza" <?php echo (($_POST['city'] ?? '') == 'Mwanza') ? 'selected' : ''; ?>>Mwanza</option>
                                            <option value="Dodoma" <?php echo (($_POST['city'] ?? '') == 'Dodoma') ? 'selected' : ''; ?>>Dodoma</option>
                                            <option value="Mbeya" <?php echo (($_POST['city'] ?? '') == 'Mbeya') ? 'selected' : ''; ?>>Mbeya</option>
                                            <option value="Zanzibar" <?php echo (($_POST['city'] ?? '') == 'Zanzibar') ? 'selected' : ''; ?>>Zanzibar</option>
                                            <option value="Tanga" <?php echo (($_POST['city'] ?? '') == 'Tanga') ? 'selected' : ''; ?>>Tanga</option>
                                            <option value="Morogoro" <?php echo (($_POST['city'] ?? '') == 'Morogoro') ? 'selected' : ''; ?>>Morogoro</option>
                                            <option value="Other" <?php echo (($_POST['city'] ?? '') == 'Other') ? 'selected' : ''; ?>>Other</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col">
                                    <div class="form-group">
                                        <label><i class="fas fa-address-card"></i> Company Address</label>
                                        <textarea name="company_address" class="form-control" rows="2" 
                                                  placeholder="Enter your company address"><?php echo htmlspecialchars($_POST['company_address'] ?? ''); ?></textarea>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col">
                                    <div class="form-group">
                                        <label class="required"><i class="fas fa-lock"></i> Password</label>
                                        <input type="password" name="password" id="customer_password" class="form-control" 
                                               placeholder="Minimum 6 characters" required>
                                        <div class="password-strength" id="customer_password_strength"></div>
                                        <div class="password-strength-text" id="customer_password_text"></div>
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
                            
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" id="terms_customer" name="terms" required>
                                <label class="form-check-label" for="terms_customer">
                                    I agree to the <a href="#" data-bs-toggle="modal" data-bs-target="#termsModal">Terms & Conditions</a> 
                                    and <a href="#" data-bs-toggle="modal" data-bs-target="#privacyModal">Privacy Policy</a>
                                </label>
                            </div>
                            
                            <button type="submit" class="btn-register mt-3" id="customerRegisterBtn">
                                <span class="loading-spinner" id="customerSpinner"></span>
                                <i class="fas fa-user-plus me-2"></i> Register as Customer
                            </button>
                        </form>
                    </div>
                    
                    <!-- Supplier Registration -->
                    <div class="tab-pane fade <?php echo $active_tab == 'supplier' ? 'show active' : ''; ?>" id="supplier" role="tabpanel">
                        <form method="POST" action="" id="supplierForm">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                            <input type="hidden" name="user_type" value="supplier">
                            
                            <div class="form-group">
                                <label class="required"><i class="fas fa-building"></i> Company Name</label>
                                <input type="text" name="company_name" class="form-control" 
                                       value="<?php echo htmlspecialchars($_POST['company_name'] ?? ''); ?>" 
                                       placeholder="Enter your company name" required>
                            </div>
                            
                            <div class="row">
                                <div class="col">
                                    <div class="form-group">
                                        <label>Registration Number</label>
                                        <input type="text" name="registration_number" class="form-control" 
                                               value="<?php echo htmlspecialchars($_POST['registration_number'] ?? ''); ?>" 
                                               placeholder="Company registration number">
                                    </div>
                                </div>
                                <div class="col">
                                    <div class="form-group">
                                        <label>Tax ID / VAT</label>
                                        <input type="text" name="tax_id" class="form-control" 
                                               value="<?php echo htmlspecialchars($_POST['tax_id'] ?? ''); ?>" 
                                               placeholder="Tax Identification Number">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label class="required"><i class="fas fa-user-tie"></i> Contact Person</label>
                                <input type="text" name="contact_person" class="form-control" 
                                       value="<?php echo htmlspecialchars($_POST['contact_person'] ?? ''); ?>" 
                                       placeholder="Full name of contact person" required>
                            </div>
                            
                            <div class="row">
                                <div class="col">
                                    <div class="form-group">
                                        <label class="required"><i class="fas fa-envelope"></i> Email</label>
                                        <input type="email" name="email" class="form-control" 
                                               value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" 
                                               placeholder="company@email.com" required>
                                    </div>
                                </div>
                                <div class="col">
                                    <div class="form-group">
                                        <label class="required"><i class="fas fa-phone"></i> Phone</label>
                                        <input type="tel" name="phone" class="form-control" 
                                               value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>" 
                                               placeholder="+255 XXX XXX XXX" required>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label><i class="fas fa-map-marker-alt"></i> Business Address</label>
                                <textarea name="address" class="form-control" rows="2" 
                                          placeholder="Enter your business address"><?php echo htmlspecialchars($_POST['address'] ?? ''); ?></textarea>
                            </div>
                            
                            <div class="row">
                                <div class="col">
                                    <div class="form-group">
                                        <label><i class="fas fa-city"></i> City</label>
                                        <select name="city" class="form-control">
                                            <option value="">Select City</option>
                                            <option value="Dar es Salaam" <?php echo (($_POST['city'] ?? '') == 'Dar es Salaam') ? 'selected' : ''; ?>>Dar es Salaam</option>
                                            <option value="Arusha" <?php echo (($_POST['city'] ?? '') == 'Arusha') ? 'selected' : ''; ?>>Arusha</option>
                                            <option value="Mwanza" <?php echo (($_POST['city'] ?? '') == 'Mwanza') ? 'selected' : ''; ?>>Mwanza</option>
                                            <option value="Dodoma" <?php echo (($_POST['city'] ?? '') == 'Dodoma') ? 'selected' : ''; ?>>Dodoma</option>
                                            <option value="Mbeya" <?php echo (($_POST['city'] ?? '') == 'Mbeya') ? 'selected' : ''; ?>>Mbeya</option>
                                            <option value="Zanzibar" <?php echo (($_POST['city'] ?? '') == 'Zanzibar') ? 'selected' : ''; ?>>Zanzibar</option>
                                            <option value="Tanga" <?php echo (($_POST['city'] ?? '') == 'Tanga') ? 'selected' : ''; ?>>Tanga</option>
                                            <option value="Morogoro" <?php echo (($_POST['city'] ?? '') == 'Morogoro') ? 'selected' : ''; ?>>Morogoro</option>
                                            <option value="Other" <?php echo (($_POST['city'] ?? '') == 'Other') ? 'selected' : ''; ?>>Other</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col">
                                    <div class="form-group">
                                        <label><i class="fas fa-map-pin"></i> Region</label>
                                        <select name="region" class="form-control">
                                            <option value="">Select Region</option>
                                            <option value="Dar es Salaam" <?php echo (($_POST['region'] ?? '') == 'Dar es Salaam') ? 'selected' : ''; ?>>Dar es Salaam</option>
                                            <option value="Arusha" <?php echo (($_POST['region'] ?? '') == 'Arusha') ? 'selected' : ''; ?>>Arusha</option>
                                            <option value="Mwanza" <?php echo (($_POST['region'] ?? '') == 'Mwanza') ? 'selected' : ''; ?>>Mwanza</option>
                                            <option value="Dodoma" <?php echo (($_POST['region'] ?? '') == 'Dodoma') ? 'selected' : ''; ?>>Dodoma</option>
                                            <option value="Mbeya" <?php echo (($_POST['region'] ?? '') == 'Mbeya') ? 'selected' : ''; ?>>Mbeya</option>
                                            <option value="Zanzibar" <?php echo (($_POST['region'] ?? '') == 'Zanzibar') ? 'selected' : ''; ?>>Zanzibar</option>
                                            <option value="Tanga" <?php echo (($_POST['region'] ?? '') == 'Tanga') ? 'selected' : ''; ?>>Tanga</option>
                                            <option value="Morogoro" <?php echo (($_POST['region'] ?? '') == 'Morogoro') ? 'selected' : ''; ?>>Morogoro</option>
                                            <option value="Other" <?php echo (($_POST['region'] ?? '') == 'Other') ? 'selected' : ''; ?>>Other</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label><i class="fas fa-info-circle"></i> Business Description</label>
                                <textarea name="business_description" class="form-control" rows="2" 
                                          placeholder="Brief description of your business"><?php echo htmlspecialchars($_POST['business_description'] ?? ''); ?></textarea>
                            </div>
                            
                            <div class="row">
                                <div class="col">
                                    <div class="form-group">
                                        <label class="required"><i class="fas fa-lock"></i> Password</label>
                                        <input type="password" name="password" id="supplier_password" class="form-control" 
                                               placeholder="Minimum 6 characters" required>
                                        <div class="password-strength" id="supplier_password_strength"></div>
                                        <div class="password-strength-text" id="supplier_password_text"></div>
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
                            
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>
                                <small>Supplier applications are reviewed by our team. You will receive an email notification once approved.</small>
                            </div>
                            
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" id="terms_supplier" name="terms" required>
                                <label class="form-check-label" for="terms_supplier">
                                    I agree to the <a href="#" data-bs-toggle="modal" data-bs-target="#termsModal">Terms & Conditions</a> 
                                    and <a href="#" data-bs-toggle="modal" data-bs-target="#privacyModal">Privacy Policy</a>
                                </label>
                            </div>
                            
                            <button type="submit" class="btn-register btn-register-supplier mt-3" id="supplierRegisterBtn">
                                <span class="loading-spinner" id="supplierSpinner"></span>
                                <i class="fas fa-paper-plane me-2"></i> Submit Supplier Application
                            </button>
                        </form>
                    </div>
                </div>
                
                <div class="login-link">
                    <p>Already have an account? <a href="login.php">Login here</a></p>
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
                    <h5 class="modal-title">Terms & Conditions</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" style="max-height: 400px; overflow-y: auto;">
                    <h6>1. Acceptance of Terms</h6>
                    <p>By registering on TechProcure Tanzania, you agree to comply with these terms and conditions.</p>
                    
                    <h6>2. Account Registration</h6>
                    <p>You must provide accurate and complete information during registration. You are responsible for maintaining the confidentiality of your account credentials.</p>
                    
                    <h6>3. Purchasing Rules</h6>
                    <p>All purchases made through TechProcure are binding. Bulk discounts apply based on quantity tiers as displayed on product pages.</p>
                    
                    <h6>4. Payment Terms</h6>
                    <p>Accepted payment methods include M-Pesa, Airtel Money, Tigo Pesa, Halopesa, Bank Transfer, and Credit/Debit Cards.</p>
                    
                    <h6>5. Delivery Policy</h6>
                    <p>Delivery times vary by location. Standard delivery is 2-7 business days across Tanzania mainland.</p>
                    
                    <h6>6. Returns & Refunds</h6>
                    <p>Defective products can be returned within 14 days of delivery. Refunds are processed within 5-7 business days.</p>
                    
                    <h6>7. Escrow Protection</h6>
                    <p>All payments are held in escrow until you confirm delivery. This protects both buyers and sellers.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary" data-bs-dismiss="modal">I Agree</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Privacy Modal -->
    <div class="modal fade" id="privacyModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title">Privacy Policy</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <h6>Information We Collect</h6>
                    <p>We collect personal information including name, email, phone number, and company details to provide our services.</p>
                    
                    <h6>How We Use Your Information</h6>
                    <p>Your information is used to process orders, communicate with you, and improve our services.</p>
                    
                    <h6>Data Security</h6>
                    <p>We implement industry-standard security measures to protect your personal information from unauthorized access.</p>
                    
                    <h6>Third Party Sharing</h6>
                    <p>We do not sell your personal information to third parties.</p>
                    
                    <h6>Your Rights</h6>
                    <p>You can request access, correction, or deletion of your personal data by contacting our support team.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary" data-bs-dismiss="modal">OK</button>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // =============================================
        // PASSWORD STRENGTH CHECKER
        // =============================================
        function checkPasswordStrength(password, strengthId, textId) {
            let strength = 0;
            let text = '';
            
            if (password.length >= 6) strength++;
            if (password.match(/[a-z]+/)) strength++;
            if (password.match(/[A-Z]+/)) strength++;
            if (password.match(/[0-9]+/)) strength++;
            if (password.match(/[$@#&!]+/)) strength++;
            
            const strengthElement = document.getElementById(strengthId);
            const textElement = document.getElementById(textId);
            
            if (!strengthElement || !textElement) return;
            
            strengthElement.classList.remove('weak', 'medium', 'strong');
            textElement.classList.remove('weak', 'medium', 'strong');
            
            if (strength <= 2) {
                strengthElement.classList.add('weak');
                textElement.classList.add('weak');
                text = 'Weak - Add uppercase, numbers, or special characters';
            } else if (strength <= 4) {
                strengthElement.classList.add('medium');
                textElement.classList.add('medium');
                text = 'Medium - Good, but could be stronger';
            } else {
                strengthElement.classList.add('strong');
                textElement.classList.add('strong');
                text = 'Strong - Excellent password!';
            }
            
            textElement.textContent = password.length > 0 ? text : '';
        }
        
        // Customer password strength
        const customerPassword = document.getElementById('customer_password');
        if (customerPassword) {
            customerPassword.addEventListener('keyup', function() {
                checkPasswordStrength(this.value, 'customer_password_strength', 'customer_password_text');
            });
        }
        
        // Supplier password strength
        const supplierPassword = document.getElementById('supplier_password');
        if (supplierPassword) {
            supplierPassword.addEventListener('keyup', function() {
                checkPasswordStrength(this.value, 'supplier_password_strength', 'supplier_password_text');
            });
        }
        
        // =============================================
        // FORM SUBMISSION LOADING STATE
        // =============================================
        document.getElementById('customerForm')?.addEventListener('submit', function(e) {
            const btn = document.getElementById('customerRegisterBtn');
            btn.disabled = true;
            btn.innerHTML = '<span class="loading-spinner" style="display:inline-block;"></span> Registering...';
        });
        
        document.getElementById('supplierForm')?.addEventListener('submit', function(e) {
            const btn = document.getElementById('supplierRegisterBtn');
            btn.disabled = true;
            btn.innerHTML = '<span class="loading-spinner" style="display:inline-block;"></span> Submitting Application...';
        });
        
        // =============================================
        // AUTO-HIDE ALERTS
        // =============================================
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                const bsAlert = new bootstrap.Alert(alert);
                setTimeout(function() { bsAlert.close(); }, 500);
            });
        }, 1000);
        
        // =============================================
        // TAB SWITCHING - Preserve selected tab on error
        // =============================================
        document.addEventListener('DOMContentLoaded', function() {
            const activeTab = '<?php echo $active_tab; ?>';
            if (activeTab === 'supplier') {
                const supplierTab = document.getElementById('supplier-tab');
                if (supplierTab) {
                    const tab = new bootstrap.Tab(supplierTab);
                    tab.show();
                }
            }
        });
    </script>
</body>
</html>