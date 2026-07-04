<?php
/**
 * TechProcure Tanzania - Supplier Profile
 * File: supplier/profile.php
 * Description: Suppliers can view and update their profile information
 */

// Start session if not started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include configuration files
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

// =============================================
// GET DATABASE CONNECTION
// =============================================
$db = getDB();
$conn = $db;

// =============================================
// CHECK SUPPLIER AUTHENTICATION
// =============================================
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type'])) {
    header('Location: ../../auth/login.php');
    exit();
}

if ($_SESSION['user_type'] !== 'supplier') {
    header('Location: ../../index.php');
    exit();
}

$supplier_id = $_SESSION['user_id'];
$supplier_name = $_SESSION['company_name'] ?? $_SESSION['user_name'] ?? 'Supplier';

// =============================================
// GENERATE CSRF TOKEN
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
        if (isset($_SESSION['csrf_token_time']) && (time() - $_SESSION['csrf_token_time']) > 3600) {
            unset($_SESSION['csrf_token']);
            unset($_SESSION['csrf_token_time']);
            return false;
        }
        return hash_equals($_SESSION['csrf_token'], $token);
    }
}

$csrf_token = generateCSRFToken();

// =============================================
// FETCH SUPPLIER DATA
// =============================================
$supplier_data = [];
$error = '';
$success = '';

try {
    // Get supplier data from users table
    $stmt = $conn->prepare("
        SELECT u.*, 
               s.id as supplier_id,
               s.company_name,
               s.company_description,
               s.business_type,
               s.tin_number,
               s.vat_registered,
               s.verification_status,
               s.business_license,
               s.tax_clearance,
               s.verification_documents,
               s.website,
               s.facebook,
               s.twitter,
               s.instagram,
               s.linkedin,
               s.bank_name,
               s.bank_account_name,
               s.bank_account_number,
               s.swift_code,
               s.bank_branch,
               s.created_at as supplier_since,
               r.role_name
        FROM users u
        LEFT JOIN suppliers s ON u.id = s.user_id
        LEFT JOIN roles r ON u.role_id = r.id
        WHERE u.id = ?
    ");
    $stmt->execute([$supplier_id]);
    $supplier_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$supplier_data) {
        $error = "Supplier data not found.";
    }
    
} catch (PDOException $e) {
    $error = "Failed to load profile: " . $e->getMessage();
}

// =============================================
// PROCESS PROFILE UPDATE
// =============================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $action = sanitizeInput($_POST['action']);
    $submitted_token = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
    
    if (!verifyCSRFToken($submitted_token)) {
        $error = 'Security token validation failed. Please refresh and try again.';
    } else {
        if ($action === 'update_profile') {
            // Update user profile
            $full_name = sanitizeInput($_POST['full_name'] ?? '');
            $email = sanitizeInput($_POST['email'] ?? '');
            $phone = sanitizeInput($_POST['phone'] ?? '');
            $address = sanitizeInput($_POST['address'] ?? '');
            $city = sanitizeInput($_POST['city'] ?? '');
            $region = sanitizeInput($_POST['region'] ?? '');
            $postal_code = sanitizeInput($_POST['postal_code'] ?? '');
            $country = sanitizeInput($_POST['country'] ?? '');
            
            // Validate
            $errors = [];
            if (empty($full_name)) $errors[] = "Full name is required.";
            if (empty($email)) $errors[] = "Email is required.";
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Invalid email format.";
            
            if (empty($errors)) {
                try {
                    $conn->beginTransaction();
                    
                    // Update users table
                    $update_stmt = $conn->prepare("
                        UPDATE users 
                        SET full_name = ?, 
                            email = ?, 
                            phone = ?, 
                            address = ?, 
                            city = ?, 
                            region = ?, 
                            postal_code = ?, 
                            country = ?,
                            updated_at = NOW()
                        WHERE id = ?
                    ");
                    $update_stmt->execute([
                        $full_name,
                        $email,
                        $phone,
                        $address,
                        $city,
                        $region,
                        $postal_code,
                        $country,
                        $supplier_id
                    ]);
                    
                    // Update session data
                    $_SESSION['user_name'] = $full_name;
                    $_SESSION['user_email'] = $email;
                    
                    $conn->commit();
                    $success = "Profile updated successfully!";
                    
                    // Refresh data
                    $stmt = $conn->prepare("
                        SELECT u.*, 
                               s.id as supplier_id,
                               s.company_name,
                               s.company_description,
                               s.business_type,
                               s.tin_number,
                               s.vat_registered,
                               s.verification_status,
                               s.website,
                               s.facebook,
                               s.twitter,
                               s.instagram,
                               s.linkedin,
                               s.bank_name,
                               s.bank_account_name,
                               s.bank_account_number,
                               s.swift_code,
                               s.bank_branch,
                               s.created_at as supplier_since,
                               r.role_name
                        FROM users u
                        LEFT JOIN suppliers s ON u.id = s.user_id
                        LEFT JOIN roles r ON u.role_id = r.id
                        WHERE u.id = ?
                    ");
                    $stmt->execute([$supplier_id]);
                    $supplier_data = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                } catch (Exception $e) {
                    $conn->rollBack();
                    $error = "Failed to update profile: " . $e->getMessage();
                }
            } else {
                $error = implode("<br>", $errors);
            }
            
        } elseif ($action === 'update_company') {
            // Update company details
            $company_name = sanitizeInput($_POST['company_name'] ?? '');
            $company_description = sanitizeInput($_POST['company_description'] ?? '');
            $business_type = sanitizeInput($_POST['business_type'] ?? '');
            $tin_number = sanitizeInput($_POST['tin_number'] ?? '');
            $vat_registered = isset($_POST['vat_registered']) ? 1 : 0;
            $website = sanitizeInput($_POST['website'] ?? '');
            $facebook = sanitizeInput($_POST['facebook'] ?? '');
            $twitter = sanitizeInput($_POST['twitter'] ?? '');
            $instagram = sanitizeInput($_POST['instagram'] ?? '');
            $linkedin = sanitizeInput($_POST['linkedin'] ?? '');
            
            try {
                $conn->beginTransaction();
                
                // Check if supplier record exists
                $check_stmt = $conn->prepare("SELECT id FROM suppliers WHERE user_id = ?");
                $check_stmt->execute([$supplier_id]);
                $supplier_exists = $check_stmt->fetch();
                
                if ($supplier_exists) {
                    // Update existing supplier record
                    $update_stmt = $conn->prepare("
                        UPDATE suppliers 
                        SET company_name = ?,
                            company_description = ?,
                            business_type = ?,
                            tin_number = ?,
                            vat_registered = ?,
                            website = ?,
                            facebook = ?,
                            twitter = ?,
                            instagram = ?,
                            linkedin = ?,
                            updated_at = NOW()
                        WHERE user_id = ?
                    ");
                    $update_stmt->execute([
                        $company_name,
                        $company_description,
                        $business_type,
                        $tin_number,
                        $vat_registered,
                        $website,
                        $facebook,
                        $twitter,
                        $instagram,
                        $linkedin,
                        $supplier_id
                    ]);
                } else {
                    // Insert new supplier record
                    $insert_stmt = $conn->prepare("
                        INSERT INTO suppliers (
                            user_id, 
                            company_name, 
                            company_description,
                            business_type,
                            tin_number,
                            vat_registered,
                            website,
                            facebook,
                            twitter,
                            instagram,
                            linkedin,
                            created_at,
                            updated_at
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
                    ");
                    $insert_stmt->execute([
                        $supplier_id,
                        $company_name,
                        $company_description,
                        $business_type,
                        $tin_number,
                        $vat_registered,
                        $website,
                        $facebook,
                        $twitter,
                        $instagram,
                        $linkedin
                    ]);
                }
                
                // Update session
                $_SESSION['company_name'] = $company_name;
                
                $conn->commit();
                $success = "Company details updated successfully!";
                
                // Refresh data
                $stmt = $conn->prepare("
                    SELECT u.*, 
                           s.id as supplier_id,
                           s.company_name,
                           s.company_description,
                           s.business_type,
                           s.tin_number,
                           s.vat_registered,
                           s.verification_status,
                           s.website,
                           s.facebook,
                           s.twitter,
                           s.instagram,
                           s.linkedin,
                           s.bank_name,
                           s.bank_account_name,
                           s.bank_account_number,
                           s.swift_code,
                           s.bank_branch,
                           s.created_at as supplier_since,
                           r.role_name
                    FROM users u
                    LEFT JOIN suppliers s ON u.id = s.user_id
                    LEFT JOIN roles r ON u.role_id = r.id
                    WHERE u.id = ?
                ");
                $stmt->execute([$supplier_id]);
                $supplier_data = $stmt->fetch(PDO::FETCH_ASSOC);
                
            } catch (Exception $e) {
                $conn->rollBack();
                $error = "Failed to update company details: " . $e->getMessage();
            }
            
        } elseif ($action === 'update_bank') {
            // Update bank details
            $bank_name = sanitizeInput($_POST['bank_name'] ?? '');
            $bank_account_name = sanitizeInput($_POST['bank_account_name'] ?? '');
            $bank_account_number = sanitizeInput($_POST['bank_account_number'] ?? '');
            $swift_code = sanitizeInput($_POST['swift_code'] ?? '');
            $bank_branch = sanitizeInput($_POST['bank_branch'] ?? '');
            
            try {
                $conn->beginTransaction();
                
                // Check if supplier record exists
                $check_stmt = $conn->prepare("SELECT id FROM suppliers WHERE user_id = ?");
                $check_stmt->execute([$supplier_id]);
                $supplier_exists = $check_stmt->fetch();
                
                if ($supplier_exists) {
                    $update_stmt = $conn->prepare("
                        UPDATE suppliers 
                        SET bank_name = ?,
                            bank_account_name = ?,
                            bank_account_number = ?,
                            swift_code = ?,
                            bank_branch = ?,
                            updated_at = NOW()
                        WHERE user_id = ?
                    ");
                    $update_stmt->execute([
                        $bank_name,
                        $bank_account_name,
                        $bank_account_number,
                        $swift_code,
                        $bank_branch,
                        $supplier_id
                    ]);
                } else {
                    $insert_stmt = $conn->prepare("
                        INSERT INTO suppliers (
                            user_id, 
                            bank_name,
                            bank_account_name,
                            bank_account_number,
                            swift_code,
                            bank_branch,
                            created_at,
                            updated_at
                        ) VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())
                    ");
                    $insert_stmt->execute([
                        $supplier_id,
                        $bank_name,
                        $bank_account_name,
                        $bank_account_number,
                        $swift_code,
                        $bank_branch
                    ]);
                }
                
                $conn->commit();
                $success = "Bank details updated successfully!";
                
                // Refresh data
                $stmt = $conn->prepare("
                    SELECT u.*, 
                           s.id as supplier_id,
                           s.company_name,
                           s.company_description,
                           s.business_type,
                           s.tin_number,
                           s.vat_registered,
                           s.verification_status,
                           s.website,
                           s.facebook,
                           s.twitter,
                           s.instagram,
                           s.linkedin,
                           s.bank_name,
                           s.bank_account_name,
                           s.bank_account_number,
                           s.swift_code,
                           s.bank_branch,
                           s.created_at as supplier_since,
                           r.role_name
                    FROM users u
                    LEFT JOIN suppliers s ON u.id = s.user_id
                    LEFT JOIN roles r ON u.role_id = r.id
                    WHERE u.id = ?
                ");
                $stmt->execute([$supplier_id]);
                $supplier_data = $stmt->fetch(PDO::FETCH_ASSOC);
                
            } catch (Exception $e) {
                $conn->rollBack();
                $error = "Failed to update bank details: " . $e->getMessage();
            }
            
        } elseif ($action === 'change_password') {
            // Change password
            $current_password = $_POST['current_password'] ?? '';
            $new_password = $_POST['new_password'] ?? '';
            $confirm_password = $_POST['confirm_password'] ?? '';
            
            $errors = [];
            if (empty($current_password)) $errors[] = "Current password is required.";
            if (empty($new_password)) $errors[] = "New password is required.";
            if (strlen($new_password) < 6) $errors[] = "New password must be at least 6 characters.";
            if ($new_password !== $confirm_password) $errors[] = "Passwords do not match.";
            
            if (empty($errors)) {
                try {
                    // Verify current password
                    $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
                    $stmt->execute([$supplier_id]);
                    $user = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if (!password_verify($current_password, $user['password'])) {
                        $error = "Current password is incorrect.";
                    } else {
                        // Update password
                        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                        $update_stmt = $conn->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?");
                        $update_stmt->execute([$hashed_password, $supplier_id]);
                        
                        $success = "Password changed successfully!";
                    }
                } catch (Exception $e) {
                    $error = "Failed to change password: " . $e->getMessage();
                }
            } else {
                $error = implode("<br>", $errors);
            }
        }
    }
}

// =============================================
// GET CART COUNT FOR NAVBAR
// =============================================
$cart_count = 0;
try {
    if (isset($_SESSION['user_id'])) {
        $sql = "SELECT COUNT(*) as total FROM cart_items ci 
                JOIN carts c ON ci.cart_id = c.id 
                WHERE c.customer_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$_SESSION['user_id']]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $cart_count = $result ? (int)$result['total'] : 0;
    }
} catch (PDOException $e) {
    $cart_count = 0;
}

// =============================================
// PAGE TITLE
// =============================================
$page_title = 'Supplier Profile - TechProcure Tanzania';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo $csrf_token; ?>">
    <title><?php echo $page_title; ?></title>
    
    <!-- CSS Dependencies -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f8f9fa;
        }
        
        .navbar {
            background: linear-gradient(135deg, #1a2a6c, #b21f1f, #fdbb4d);
            padding: 15px 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .navbar-brand {
            color: white !important;
            font-weight: 800;
            font-size: 1.5rem;
        }
        
        .navbar-brand i {
            margin-right: 8px;
        }
        
        .nav-link {
            color: rgba(255,255,255,0.9) !important;
            font-weight: 500;
        }
        
        .nav-link:hover {
            color: white !important;
        }
        
        .cart-count {
            position: absolute;
            top: -8px;
            right: -12px;
            background: #dc3545;
            color: white;
            border-radius: 50%;
            padding: 2px 6px;
            font-size: 10px;
            font-weight: bold;
            min-width: 18px;
            text-align: center;
        }
        
        .dashboard-wrapper {
            display: flex;
            margin-top: 20px;
            min-height: calc(100vh - 150px);
        }
        
        .dashboard-sidebar {
            width: 260px;
            background: white;
            border-radius: 15px;
            padding: 20px 0;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            flex-shrink: 0;
            margin-right: 24px;
            position: sticky;
            top: 20px;
            height: fit-content;
        }
        
        .dashboard-sidebar .user-avatar {
            text-align: center;
            padding: 20px;
            border-bottom: 1px solid #e9ecef;
        }
        
        .dashboard-sidebar .user-avatar .avatar-circle {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: linear-gradient(135deg, #198754, #157347);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            font-weight: 600;
            margin: 0 auto 10px;
        }
        
        .dashboard-sidebar .user-avatar h6 {
            margin-bottom: 0;
            font-weight: 600;
        }
        
        .dashboard-sidebar .user-avatar small {
            color: #6c757d;
        }
        
        .dashboard-sidebar .nav-link {
            color: #333 !important;
            padding: 12px 25px;
            border-radius: 0;
            border-left: 3px solid transparent;
            transition: all 0.3s;
            display: flex;
            align-items: center;
        }
        
        .dashboard-sidebar .nav-link:hover {
            background: #f8f9fa;
            color: #0d6efd !important;
        }
        
        .dashboard-sidebar .nav-link.active {
            background: #f0f7ff;
            color: #0d6efd !important;
            border-left-color: #0d6efd;
        }
        
        .dashboard-sidebar .nav-link i {
            width: 22px;
            margin-right: 10px;
        }
        
        .dashboard-sidebar .nav-link .badge {
            margin-left: auto;
        }
        
        .dashboard-content {
            flex: 1;
        }
        
        .profile-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 24px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        
        .profile-card .card-title {
            font-weight: 600;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #e9ecef;
        }
        
        .profile-card .card-title i {
            margin-right: 8px;
            color: #198754;
        }
        
        .form-label {
            font-weight: 500;
            font-size: 0.85rem;
        }
        
        .form-control, .form-select {
            border-radius: 10px;
            padding: 10px 15px;
            border: 1px solid #e0e0e0;
            transition: all 0.3s;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: #198754;
            box-shadow: 0 0 0 3px rgba(25,135,84,0.1);
            outline: none;
        }
        
        .btn-save {
            background: linear-gradient(135deg, #198754, #157347);
            border: none;
            border-radius: 10px;
            padding: 10px 25px;
            font-weight: 600;
            color: white;
            transition: all 0.3s;
            cursor: pointer;
        }
        
        .btn-save:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(25,135,84,0.3);
        }
        
        .btn-save:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        
        .btn-cancel {
            background: #6c757d;
            border: none;
            border-radius: 10px;
            padding: 10px 25px;
            font-weight: 600;
            color: white;
            transition: all 0.3s;
            text-decoration: none;
        }
        
        .btn-cancel:hover {
            background: #5a6268;
            color: white;
        }
        
        .required:after {
            content: " *";
            color: red;
        }
        
        .verification-badge {
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: 500;
            display: inline-block;
        }
        
        .verification-badge.verified {
            background: #d4edda;
            color: #155724;
        }
        
        .verification-badge.pending {
            background: #fff3cd;
            color: #856404;
        }
        
        .verification-badge.unverified {
            background: #f8d7da;
            color: #721c24;
        }
        
        .footer {
            background: #1a1a2e;
            color: white;
            padding: 40px 0 20px;
            margin-top: 50px;
        }
        
        .footer a {
            color: rgba(255,255,255,0.7);
            text-decoration: none;
        }
        
        .footer a:hover {
            color: white;
        }
        
        @media (max-width: 768px) {
            .dashboard-wrapper {
                flex-direction: column;
            }
            .dashboard-sidebar {
                width: 100%;
                margin-right: 0;
                margin-bottom: 20px;
                position: static;
            }
            .profile-card {
                padding: 20px;
            }
        }
    </style>
</head>
<body>

<!-- ===================================================== -->
<!-- NAVBAR -->
<!-- ===================================================== -->
<nav class="navbar navbar-expand-lg">
    <div class="container">
        <a class="navbar-brand" href="../../index.php">
            <i class="fas fa-microchip"></i>
            TechProcure <span class="text-warning">Tanzania</span>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav mx-auto">
                <li class="nav-item">
                    <a class="nav-link" href="../../index.php"><i class="fas fa-home me-1"></i>Home</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="../../products.php"><i class="fas fa-box me-1"></i>Products</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="../../suppliers.php"><i class="fas fa-truck me-1"></i>Suppliers</a>
                </li>
            </ul>
            <div class="d-flex align-items-center gap-2">
                <a href="../../cart.php" class="nav-link position-relative">
                    <i class="fas fa-shopping-cart fa-lg"></i>
                    <span class="cart-count"><?php echo $cart_count; ?></span>
                </a>
                <a href="../../auth/logout.php" class="btn btn-outline-light btn-sm">
                    <i class="fas fa-sign-out-alt me-1"></i>Logout
                </a>
            </div>
        </div>
    </div>
</nav>

<!-- ===================================================== -->
<!-- DASHBOARD -->
<!-- ===================================================== -->
<div class="container">
    <div class="dashboard-wrapper">
        
        <!-- Sidebar -->
        <div class="dashboard-sidebar">
            <div class="user-avatar">
                <div class="avatar-circle">
                    <?php echo strtoupper(substr($supplier_name, 0, 1)); ?>
                </div>
                <h6><?php echo htmlspecialchars($supplier_name); ?></h6>
                <small><?php echo htmlspecialchars($_SESSION['user_email'] ?? ''); ?></small>
            </div>
            
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link" href="../dashboard.php">
                        <i class="fas fa-tachometer-alt"></i> Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="../products/my-products.php">
                        <i class="fas fa-box"></i> My Products
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="../products/add-product.php">
                        <i class="fas fa-plus-circle"></i> Add Product
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="../orders/supplier-orders.php">
                        <i class="fas fa-shopping-cart"></i> Orders
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="../earnings/earnings.php">
                        <i class="fas fa-money-bill-wave"></i> Earnings
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="../quotations/quotation-requests.php">
                        <i class="fas fa-file-alt"></i> Quotations
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="profile.php">
                        <i class="fas fa-user"></i> Profile
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="settings.php">
                        <i class="fas fa-cog"></i> Settings
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link text-danger" href="../../auth/logout.php">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </li>
            </ul>
        </div>
        
        <!-- Dashboard Content -->
        <div class="dashboard-content">
            
            <!-- Page Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h4 class="mb-0"><i class="fas fa-user me-2 text-success"></i>My Profile</h4>
                    <p class="text-muted">Manage your profile and company information</p>
                </div>
                <div>
                    <span class="verification-badge <?php echo $supplier_data['verification_status'] ?? 'unverified'; ?>">
                        <?php echo ucfirst($supplier_data['verification_status'] ?? 'Unverified'); ?>
                    </span>
                </div>
            </div>
            
            <!-- Error/Success Messages -->
            <?php if($error): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <i class="fas fa-exclamation-triangle me-2"></i> <?php echo $error; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if($success): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <i class="fas fa-check-circle me-2"></i> <?php echo $success; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <!-- Personal Information -->
            <div class="profile-card">
                <div class="card-title">
                    <i class="fas fa-user-circle"></i> Personal Information
                </div>
                <form method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="action" value="update_profile">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label required">Full Name</label>
                            <input type="text" name="full_name" class="form-control" 
                                   value="<?php echo htmlspecialchars($supplier_data['full_name'] ?? ''); ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label required">Email Address</label>
                            <input type="email" name="email" class="form-control" 
                                   value="<?php echo htmlspecialchars($supplier_data['email'] ?? ''); ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Phone Number</label>
                            <input type="text" name="phone" class="form-control" 
                                   value="<?php echo htmlspecialchars($supplier_data['phone'] ?? ''); ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Role</label>
                            <input type="text" class="form-control" 
                                   value="<?php echo htmlspecialchars($supplier_data['role_name'] ?? 'Supplier'); ?>" disabled>
                        </div>
                        <div class="col-md-12 mb-3">
                            <label class="form-label">Address</label>
                            <input type="text" name="address" class="form-control" 
                                   value="<?php echo htmlspecialchars($supplier_data['address'] ?? ''); ?>">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">City</label>
                            <input type="text" name="city" class="form-control" 
                                   value="<?php echo htmlspecialchars($supplier_data['city'] ?? ''); ?>">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Region</label>
                            <input type="text" name="region" class="form-control" 
                                   value="<?php echo htmlspecialchars($supplier_data['region'] ?? ''); ?>">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Postal Code</label>
                            <input type="text" name="postal_code" class="form-control" 
                                   value="<?php echo htmlspecialchars($supplier_data['postal_code'] ?? ''); ?>">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Country</label>
                            <input type="text" name="country" class="form-control" 
                                   value="<?php echo htmlspecialchars($supplier_data['country'] ?? 'Tanzania'); ?>">
                        </div>
                    </div>
                    
                    <button type="submit" class="btn-save">
                        <i class="fas fa-save me-2"></i> Update Profile
                    </button>
                </form>
            </div>
            
            <!-- Company Information -->
            <div class="profile-card">
                <div class="card-title">
                    <i class="fas fa-building"></i> Company Information
                </div>
                <form method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="action" value="update_company">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label required">Company Name</label>
                            <input type="text" name="company_name" class="form-control" 
                                   value="<?php echo htmlspecialchars($supplier_data['company_name'] ?? ''); ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Business Type</label>
                            <select name="business_type" class="form-select">
                                <option value="">Select Business Type</option>
                                <option value="sole_proprietorship" <?php echo ($supplier_data['business_type'] ?? '') == 'sole_proprietorship' ? 'selected' : ''; ?>>Sole Proprietorship</option>
                                <option value="partnership" <?php echo ($supplier_data['business_type'] ?? '') == 'partnership' ? 'selected' : ''; ?>>Partnership</option>
                                <option value="limited_company" <?php echo ($supplier_data['business_type'] ?? '') == 'limited_company' ? 'selected' : ''; ?>>Limited Company</option>
                                <option value="corporation" <?php echo ($supplier_data['business_type'] ?? '') == 'corporation' ? 'selected' : ''; ?>>Corporation</option>
                                <option value="ngo" <?php echo ($supplier_data['business_type'] ?? '') == 'ngo' ? 'selected' : ''; ?>>NGO</option>
                                <option value="other" <?php echo ($supplier_data['business_type'] ?? '') == 'other' ? 'selected' : ''; ?>>Other</option>
                            </select>
                        </div>
                        <div class="col-md-12 mb-3">
                            <label class="form-label">Company Description</label>
                            <textarea name="company_description" class="form-control" rows="3"><?php echo htmlspecialchars($supplier_data['company_description'] ?? ''); ?></textarea>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">TIN Number</label>
                            <input type="text" name="tin_number" class="form-control" 
                                   value="<?php echo htmlspecialchars($supplier_data['tin_number'] ?? ''); ?>">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Website</label>
                            <input type="url" name="website" class="form-control" 
                                   value="<?php echo htmlspecialchars($supplier_data['website'] ?? ''); ?>" placeholder="https://example.com">
                        </div>
                        <div class="col-md-4 mb-3">
                            <div class="form-check form-switch mt-4">
                                <input type="checkbox" name="vat_registered" class="form-check-input" id="vat_registered" value="1"
                                       <?php echo ($supplier_data['vat_registered'] ?? 0) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="vat_registered">VAT Registered</label>
                            </div>
                        </div>
                    </div>
                    
                    <h6 class="mt-3 mb-3">Social Media Links</h6>
                    <div class="row">
                        <div class="col-md-3 mb-3">
                            <label class="form-label"><i class="fab fa-facebook text-primary me-1"></i> Facebook</label>
                            <input type="url" name="facebook" class="form-control" 
                                   value="<?php echo htmlspecialchars($supplier_data['facebook'] ?? ''); ?>" placeholder="Facebook URL">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label"><i class="fab fa-twitter text-info me-1"></i> Twitter</label>
                            <input type="url" name="twitter" class="form-control" 
                                   value="<?php echo htmlspecialchars($supplier_data['twitter'] ?? ''); ?>" placeholder="Twitter URL">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label"><i class="fab fa-instagram text-danger me-1"></i> Instagram</label>
                            <input type="url" name="instagram" class="form-control" 
                                   value="<?php echo htmlspecialchars($supplier_data['instagram'] ?? ''); ?>" placeholder="Instagram URL">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label"><i class="fab fa-linkedin text-primary me-1"></i> LinkedIn</label>
                            <input type="url" name="linkedin" class="form-control" 
                                   value="<?php echo htmlspecialchars($supplier_data['linkedin'] ?? ''); ?>" placeholder="LinkedIn URL">
                        </div>
                    </div>
                    
                    <button type="submit" class="btn-save">
                        <i class="fas fa-save me-2"></i> Update Company
                    </button>
                </form>
            </div>
            
            <!-- Bank Details -->
            <div class="profile-card">
                <div class="card-title">
                    <i class="fas fa-university"></i> Bank Details
                </div>
                <form method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="action" value="update_bank">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Bank Name</label>
                            <input type="text" name="bank_name" class="form-control" 
                                   value="<?php echo htmlspecialchars($supplier_data['bank_name'] ?? ''); ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Bank Branch</label>
                            <input type="text" name="bank_branch" class="form-control" 
                                   value="<?php echo htmlspecialchars($supplier_data['bank_branch'] ?? ''); ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Account Holder Name</label>
                            <input type="text" name="bank_account_name" class="form-control" 
                                   value="<?php echo htmlspecialchars($supplier_data['bank_account_name'] ?? ''); ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Account Number</label>
                            <input type="text" name="bank_account_number" class="form-control" 
                                   value="<?php echo htmlspecialchars($supplier_data['bank_account_number'] ?? ''); ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">SWIFT Code</label>
                            <input type="text" name="swift_code" class="form-control" 
                                   value="<?php echo htmlspecialchars($supplier_data['swift_code'] ?? ''); ?>">
                        </div>
                    </div>
                    
                    <button type="submit" class="btn-save">
                        <i class="fas fa-save me-2"></i> Update Bank Details
                    </button>
                </form>
            </div>
            
            <!-- Change Password -->
            <div class="profile-card">
                <div class="card-title">
                    <i class="fas fa-key"></i> Change Password
                </div>
                <form method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="action" value="change_password">
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label required">Current Password</label>
                            <input type="password" name="current_password" class="form-control" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label required">New Password</label>
                            <input type="password" name="new_password" class="form-control" required minlength="6">
                            <small class="text-muted">Minimum 6 characters</small>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label required">Confirm Password</label>
                            <input type="password" name="confirm_password" class="form-control" required>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn-save">
                        <i class="fas fa-key me-2"></i> Change Password
                    </button>
                </form>
            </div>
            
            <!-- Account Statistics -->
            <div class="profile-card">
                <div class="card-title">
                    <i class="fas fa-chart-bar"></i> Account Statistics
                </div>
                <div class="row">
                    <div class="col-md-3">
                        <div class="text-muted small">Member Since</div>
                        <div class="fw-bold"><?php echo date('M d, Y', strtotime($supplier_data['created_at'] ?? 'now')); ?></div>
                    </div>
                    <div class="col-md-3">
                        <div class="text-muted small">Account Status</div>
                        <div class="fw-bold">
                            <?php if (($supplier_data['is_active'] ?? 1) == 1): ?>
                                <span class="text-success">Active</span>
                            <?php else: ?>
                                <span class="text-danger">Inactive</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="text-muted small">Verification Status</div>
                        <div class="fw-bold">
                            <span class="verification-badge <?php echo $supplier_data['verification_status'] ?? 'unverified'; ?>" style="font-size: 0.75rem;">
                                <?php echo ucfirst($supplier_data['verification_status'] ?? 'Unverified'); ?>
                            </span>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="text-muted small">User Type</div>
                        <div class="fw-bold">Supplier</div>
                    </div>
                </div>
            </div>
            
        </div>
    </div>
</div>

<!-- ===================================================== -->
<!-- FOOTER -->
<!-- ===================================================== -->
<footer class="footer">
    <div class="container">
        <div class="row">
            <div class="col-md-4 mb-4">
                <h5><i class="fas fa-microchip me-2"></i>TechProcure Tanzania</h5>
                <p class="text-muted">Enterprise B2B IT equipment procurement platform with transparent pricing and bulk discounts for corporate buyers across Tanzania.</p>
            </div>
            <div class="col-md-2 mb-4">
                <h6>Quick Links</h6>
                <ul class="list-unstyled">
                    <li><a href="../../index.php">Home</a></li>
                    <li><a href="../../products.php">Products</a></li>
                    <li><a href="../../suppliers.php">Suppliers</a></li>
                </ul>
            </div>
            <div class="col-md-3 mb-4">
                <h6>Supplier</h6>
                <ul class="list-unstyled">
                    <li><a href="../dashboard.php">Dashboard</a></li>
                    <li><a href="../products/my-products.php">My Products</a></li>
                    <li><a href="../orders/supplier-orders.php">Orders</a></li>
                </ul>
            </div>
            <div class="col-md-3 mb-4">
                <h6>Contact Us</h6>
                <ul class="list-unstyled">
                    <li><i class="fas fa-envelope me-2"></i> support@techprocure.co.tz</li>
                    <li><i class="fas fa-phone me-2"></i> +255 123 456 789</li>
                </ul>
            </div>
        </div>
        <hr style="border-color: rgba(255,255,255,0.1);">
        <div class="text-center">
            <small style="color: rgba(255,255,255,0.5);">&copy; <?php echo date('Y'); ?> TechProcure Tanzania. All rights reserved.</small>
        </div>
    </div>
</footer>

<!-- ===================================================== -->
<!-- SCRIPTS -->
<!-- ===================================================== -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<script>
// =============================================
// AUTO-HIDE ALERTS
// =============================================
setTimeout(function() {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(function(alert) {
        const bsAlert = new bootstrap.Alert(alert);
        bsAlert.close();
    });
}, 5000);

// =============================================
// PASSWORD MATCH VALIDATION
// =============================================
document.querySelector('form[action*="change_password"]')?.addEventListener('submit', function(e) {
    const newPassword = this.querySelector('input[name="new_password"]');
    const confirmPassword = this.querySelector('input[name="confirm_password"]');
    
    if (newPassword && confirmPassword) {
        if (newPassword.value !== confirmPassword.value) {
            e.preventDefault();
            alert('Passwords do not match. Please try again.');
            confirmPassword.focus();
            return false;
        }
        if (newPassword.value.length < 6) {
            e.preventDefault();
            alert('Password must be at least 6 characters long.');
            newPassword.focus();
            return false;
        }
    }
    return true;
});

// =============================================
// CONSOLE LOG FOR DEBUGGING
// =============================================
console.log('CSRF Token from meta:', document.querySelector('meta[name="csrf-token"]')?.getAttribute('content'));
console.log('Page loaded: <?php echo $page_title; ?>');
</script>

</body>
</html>