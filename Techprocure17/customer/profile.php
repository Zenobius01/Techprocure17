<?php
/**
 * TechProcure Tanzania - Customer Profile Page
 * File: customer/profile.php
 * Description: Customer profile management with edit functionality
 */

// Start session if not started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include configuration files
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';


// Check if user is logged in and is customer
requireCustomer();

// Get database connection
$db = getDB();
$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'] ?? 'Customer';

// =============================================
// GET USER DATA
// =============================================

$user = null;
try {
    if (function_exists('getCurrentUser')) {
        $user = getCurrentUser();
    } else {
        $stmt = $db->prepare("SELECT u.*, r.role_name FROM users u LEFT JOIN roles r ON u.role_id = r.id WHERE u.id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
    }
} catch (Exception $e) {
    $user = null;
}

if (!$user) {
    $user = [
        'full_name' => $user_name,
        'email' => $_SESSION['user_email'] ?? '',
        'phone' => '',
        'company_name' => '',
        'company_address' => '',
        'city' => '',
        'region' => '',
        'tin_number' => '',
        'profile_image' => ''
    ];
}

// =============================================
// UPDATE PROFILE
// =============================================

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        $error = 'Security token validation failed. Please try again.';
    } else {
        $full_name = sanitizeInput($_POST['full_name'] ?? '');
        $phone = sanitizeInput($_POST['phone'] ?? '');
        $company_name = sanitizeInput($_POST['company_name'] ?? '');
        $company_address = sanitizeInput($_POST['company_address'] ?? '');
        $city = sanitizeInput($_POST['city'] ?? '');
        $region = sanitizeInput($_POST['region'] ?? '');
        $tin_number = sanitizeInput($_POST['tin_number'] ?? '');
        
        // Validation
        if (empty($full_name)) {
            $error = 'Full name is required.';
        } else {
            try {
                $sql = "UPDATE users SET 
                        full_name = ?,
                        phone = ?,
                        company_name = ?,
                        company_address = ?,
                        city = ?,
                        region = ?,
                        tin_number = ?,
                        updated_at = NOW()
                        WHERE id = ?";
                $stmt = $db->prepare($sql);
                $stmt->execute([
                    $full_name,
                    $phone,
                    $company_name,
                    $company_address,
                    $city,
                    $region,
                    $tin_number,
                    $user_id
                ]);
                
                // Update session name
                $_SESSION['user_name'] = $full_name;
                
                // Handle profile image upload
                if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] == 0) {
                    $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                    $maxSize = 2097152; // 2MB
                    
                    if ($_FILES['profile_image']['size'] > $maxSize) {
                        $error = 'Image size must be less than 2MB.';
                    } elseif (!in_array($_FILES['profile_image']['type'], $allowed)) {
                        $error = 'Only JPG, PNG, GIF, and WEBP images are allowed.';
                    } else {
                        $ext = pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION);
                        $filename = 'profile_' . $user_id . '_' . time() . '.' . $ext;
                        $target_dir = '../uploads/profiles/';
                        
                        if (!is_dir($target_dir)) {
                            mkdir($target_dir, 0777, true);
                        }
                        
                        $target_path = $target_dir . $filename;
                        
                        if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $target_path)) {
                            // Delete old image
                            if (!empty($user['profile_image']) && file_exists('../' . $user['profile_image'])) {
                                unlink('../' . $user['profile_image']);
                            }
                            
                            $img_sql = "UPDATE users SET profile_image = ? WHERE id = ?";
                            $img_stmt = $db->prepare($img_sql);
                            $img_stmt->execute(['uploads/profiles/' . $filename, $user_id]);
                        }
                    }
                }
                
                if (empty($error)) {
                    $success = 'Profile updated successfully!';
                    // Refresh user data
                    $stmt = $db->prepare("SELECT u.*, r.role_name FROM users u LEFT JOIN roles r ON u.role_id = r.id WHERE u.id = ?");
                    $stmt->execute([$user_id]);
                    $user = $stmt->fetch();
                }
            } catch (PDOException $e) {
                $error = 'Failed to update profile. Please try again.';
            }
        }
    }
}

// =============================================
// CHANGE PASSWORD
// =============================================

if (isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error = 'Please fill in all password fields.';
    } elseif (strlen($new_password) < 6) {
        $error = 'New password must be at least 6 characters long.';
    } elseif ($new_password !== $confirm_password) {
        $error = 'New passwords do not match.';
    } else {
        // Verify current password
        $stmt = $db->prepare("SELECT password_hash FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user_data = $stmt->fetch();
        
        if ($user_data && password_verify($current_password, $user_data['password_hash'])) {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $db->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
            $stmt->execute([$hashed_password, $user_id]);
            $success = 'Password changed successfully!';
        } else {
            $error = 'Current password is incorrect.';
        }
    }
}

// =============================================
// GET CART COUNT
// =============================================

$cart_count = function_exists('getCartCount') ? getCartCount() : 0;

// =============================================
// HELPER FUNCTIONS
// =============================================

if (!function_exists('formatDate')) {
    function formatDate($date) {
        if (empty($date)) return '-';
        return date('M d, Y', strtotime($date));
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - TechProcure Tanzania</title>
    
    <!-- CSS Dependencies -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f8f9fa;
        }
        
        /* Navbar */
        .navbar {
            background: linear-gradient(135deg, #1a2a6c, #b21f1f, #fdbb4d);
            padding: 15px 0;
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
        
        /* Dashboard Layout */
        .dashboard-wrapper {
            display: flex;
            margin-top: 20px;
            min-height: calc(100vh - 150px);
        }
        
        /* Sidebar */
        .dashboard-sidebar {
            width: 260px;
            background: white;
            border-radius: 15px;
            padding: 20px 0;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            flex-shrink: 0;
            margin-right: 24px;
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
            background: linear-gradient(135deg, #0d6efd, #0b5ed7);
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
            float: right;
            margin-top: 2px;
        }
        
        /* Profile Content */
        .profile-content {
            flex: 1;
        }
        
        .profile-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 24px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        
        .profile-card .card-title {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #e9ecef;
        }
        
        .profile-card .card-title i {
            margin-right: 8px;
            color: #0d6efd;
        }
        
        .profile-image-container {
            text-align: center;
            margin-bottom: 20px;
        }
        
        .profile-image-container .profile-img {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid #0d6efd;
            padding: 3px;
            background: white;
        }
        
        .profile-image-container .profile-img-placeholder {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            background: linear-gradient(135deg, #0d6efd, #0b5ed7);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 4rem;
            font-weight: 600;
            border: 4px solid #0d6efd;
            padding: 3px;
            margin: 0 auto;
        }
        
        .form-control {
            border-radius: 10px;
            padding: 10px 15px;
            border: 1px solid #e0e0e0;
            transition: all 0.3s;
        }
        
        .form-control:focus {
            border-color: #0d6efd;
            box-shadow: 0 0 0 3px rgba(13,110,253,0.1);
            outline: none;
        }
        
        .form-label {
            font-weight: 500;
            font-size: 0.85rem;
        }
        
        .btn-save {
            background: linear-gradient(135deg, #0d6efd, #0b5ed7);
            border: none;
            border-radius: 10px;
            padding: 10px 25px;
            font-weight: 600;
            color: white;
            transition: all 0.3s;
        }
        
        .btn-save:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(13,110,253,0.3);
        }
        
        .btn-change-password {
            background: linear-gradient(135deg, #198754, #157347);
            border: none;
            border-radius: 10px;
            padding: 10px 25px;
            font-weight: 600;
            color: white;
            transition: all 0.3s;
        }
        
        .btn-change-password:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(25,135,84,0.3);
        }
        
        .alert {
            border-radius: 10px;
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
            }
            .profile-image-container .profile-img,
            .profile-image-container .profile-img-placeholder {
                width: 100px;
                height: 100px;
                font-size: 2.5rem;
            }
        }
    </style>
</head>
<body>

<!-- Navbar -->
<nav class="navbar navbar-expand-lg">
    <div class="container">
        <a class="navbar-brand" href="../index.php">
            <i class="fas fa-microchip"></i>
            TechProcure <span class="text-warning">Tanzania</span>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav mx-auto">
                <li class="nav-item">
                    <a class="nav-link" href="../index.php"><i class="fas fa-home me-1"></i>Home</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="../products.php"><i class="fas fa-box me-1"></i>Products</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="../suppliers.php"><i class="fas fa-truck me-1"></i>Suppliers</a>
                </li>
            </ul>
            <div class="d-flex align-items-center gap-2">
                <a href="../cart.php" class="nav-link position-relative">
                    <i class="fas fa-shopping-cart fa-lg"></i>
                    <span class="cart-count"><?php echo $cart_count; ?></span>
                </a>
                <a href="../auth/logout.php" class="btn btn-outline-light btn-sm">
                    <i class="fas fa-sign-out-alt me-1"></i>Logout
                </a>
            </div>
        </div>
    </div>
</nav>

<!-- Dashboard -->
<div class="container">
    <div class="dashboard-wrapper">
        <!-- Sidebar -->
        <div class="dashboard-sidebar">
            <div class="user-avatar">
                <div class="avatar-circle">
                    <?php echo strtoupper(substr($user_name, 0, 1)); ?>
                </div>
                <h6><?php echo htmlspecialchars($user_name); ?></h6>
                <small><?php echo htmlspecialchars($_SESSION['user_email'] ?? ''); ?></small>
            </div>
            
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link" href="dashboard.php">
                        <i class="fas fa-tachometer-alt"></i> Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="profile.php">
                        <i class="fas fa-user"></i> My Profile
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="orders/my-orders.php">
                        <i class="fas fa-shopping-bag"></i> My Orders
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="wishlist.php">
                        <i class="fas fa-heart"></i> Wishlist
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="quotations/request-quotation.php">
                        <i class="fas fa-file-alt"></i> Quotations
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="invoices/my-invoices.php">
                        <i class="fas fa-file-invoice"></i> Invoices
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="settings.php">
                        <i class="fas fa-cog"></i> Settings
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link text-danger" href="../auth/logout.php">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </li>
            </ul>
        </div>
        
        <!-- Profile Content -->
        <div class="profile-content">
            <!-- Page Title -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h4 class="mb-0"><i class="fas fa-user me-2 text-primary"></i>My Profile</h4>
                    <p class="text-muted">Manage your personal information</p>
                </div>
                <span class="badge bg-primary">Customer</span>
            </div>
            
            <!-- Success/Error Messages -->
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
            
            <!-- Profile Information -->
            <div class="profile-card">
                <div class="card-title">
                    <i class="fas fa-user-edit"></i> Personal Information
                </div>
                
                <div class="profile-image-container">
                    <?php if (!empty($user['profile_image']) && file_exists('../' . $user['profile_image'])): ?>
                        <img src="../<?php echo $user['profile_image']; ?>" alt="Profile" class="profile-img">
                    <?php else: ?>
                        <div class="profile-img-placeholder">
                            <?php echo strtoupper(substr($user['full_name'] ?? $user_name, 0, 1)); ?>
                        </div>
                    <?php endif; ?>
                </div>
                
                <form method="POST" action="" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label required">Full Name</label>
                            <input type="text" name="full_name" class="form-control" 
                                   value="<?php echo htmlspecialchars($user['full_name'] ?? $user_name); ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Email Address</label>
                            <input type="email" class="form-control" 
                                   value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" disabled>
                            <small class="text-muted">Email cannot be changed</small>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Phone Number</label>
                            <input type="tel" name="phone" class="form-control" 
                                   value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Profile Image</label>
                            <input type="file" name="profile_image" class="form-control" accept="image/*">
                            <small class="text-muted">Max 2MB. JPG, PNG, GIF, WEBP</small>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Company Name</label>
                            <input type="text" name="company_name" class="form-control" 
                                   value="<?php echo htmlspecialchars($user['company_name'] ?? ''); ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">TIN Number</label>
                            <input type="text" name="tin_number" class="form-control" 
                                   value="<?php echo htmlspecialchars($user['tin_number'] ?? ''); ?>">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Company Address</label>
                        <textarea name="company_address" class="form-control" rows="2"><?php echo htmlspecialchars($user['company_address'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">City</label>
                            <select name="city" class="form-control">
                                <option value="">Select City</option>
                                <option value="Dar es Salaam" <?php echo ($user['city'] ?? '') == 'Dar es Salaam' ? 'selected' : ''; ?>>Dar es Salaam</option>
                                <option value="Arusha" <?php echo ($user['city'] ?? '') == 'Arusha' ? 'selected' : ''; ?>>Arusha</option>
                                <option value="Mwanza" <?php echo ($user['city'] ?? '') == 'Mwanza' ? 'selected' : ''; ?>>Mwanza</option>
                                <option value="Dodoma" <?php echo ($user['city'] ?? '') == 'Dodoma' ? 'selected' : ''; ?>>Dodoma</option>
                                <option value="Mbeya" <?php echo ($user['city'] ?? '') == 'Mbeya' ? 'selected' : ''; ?>>Mbeya</option>
                                <option value="Zanzibar" <?php echo ($user['city'] ?? '') == 'Zanzibar' ? 'selected' : ''; ?>>Zanzibar</option>
                                <option value="Tanga" <?php echo ($user['city'] ?? '') == 'Tanga' ? 'selected' : ''; ?>>Tanga</option>
                                <option value="Morogoro" <?php echo ($user['city'] ?? '') == 'Morogoro' ? 'selected' : ''; ?>>Morogoro</option>
                                <option value="Other" <?php echo ($user['city'] ?? '') == 'Other' ? 'selected' : ''; ?>>Other</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Region</label>
                            <select name="region" class="form-control">
                                <option value="">Select Region</option>
                                <option value="Dar es Salaam" <?php echo ($user['region'] ?? '') == 'Dar es Salaam' ? 'selected' : ''; ?>>Dar es Salaam</option>
                                <option value="Arusha" <?php echo ($user['region'] ?? '') == 'Arusha' ? 'selected' : ''; ?>>Arusha</option>
                                <option value="Mwanza" <?php echo ($user['region'] ?? '') == 'Mwanza' ? 'selected' : ''; ?>>Mwanza</option>
                                <option value="Dodoma" <?php echo ($user['region'] ?? '') == 'Dodoma' ? 'selected' : ''; ?>>Dodoma</option>
                                <option value="Mbeya" <?php echo ($user['region'] ?? '') == 'Mbeya' ? 'selected' : ''; ?>>Mbeya</option>
                                <option value="Zanzibar" <?php echo ($user['region'] ?? '') == 'Zanzibar' ? 'selected' : ''; ?>>Zanzibar</option>
                                <option value="Tanga" <?php echo ($user['region'] ?? '') == 'Tanga' ? 'selected' : ''; ?>>Tanga</option>
                                <option value="Morogoro" <?php echo ($user['region'] ?? '') == 'Morogoro' ? 'selected' : ''; ?>>Morogoro</option>
                                <option value="Other" <?php echo ($user['region'] ?? '') == 'Other' ? 'selected' : ''; ?>>Other</option>
                            </select>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn-save">
                        <i class="fas fa-save me-2"></i> Save Changes
                    </button>
                </form>
            </div>
            
            <!-- Change Password -->
            <div class="profile-card">
                <div class="card-title">
                    <i class="fas fa-key"></i> Change Password
                </div>
                
                <form method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="change_password" value="1">
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label required">Current Password</label>
                            <input type="password" name="current_password" class="form-control" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label required">New Password</label>
                            <input type="password" name="new_password" class="form-control" required>
                            <small class="text-muted">Minimum 6 characters</small>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label required">Confirm New Password</label>
                            <input type="password" name="confirm_password" class="form-control" required>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn-change-password">
                        <i class="fas fa-key me-2"></i> Change Password
                    </button>
                </form>
            </div>
            
            <!-- Account Information -->
            <div class="profile-card">
                <div class="card-title">
                    <i class="fas fa-info-circle"></i> Account Information
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>Username:</strong> <?php echo htmlspecialchars($user['username'] ?? ''); ?></p>
                        <p><strong>Account Type:</strong> <span class="badge bg-primary">Customer</span></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Member Since:</strong> <?php echo formatDate($user['created_at'] ?? ''); ?></p>
                        <p><strong>Status:</strong> 
                            <?php if(($user['status'] ?? 'active') == 'active'): ?>
                                <span class="badge bg-success">Active</span>
                            <?php else: ?>
                                <span class="badge bg-danger"><?php echo ucfirst($user['status'] ?? 'Inactive'); ?></span>
                            <?php endif; ?>
                        </p>
                    </div>
                </div>
            </div>
            
            <!-- Need Help -->
            <div class="alert alert-info">
                <div class="d-flex align-items-center">
                    <i class="fas fa-headset fa-2x me-3"></i>
                    <div>
                        <h6 class="mb-0">Need Help?</h6>
                        <p class="mb-0 small">Contact our support team for assistance with your profile or account.</p>
                    </div>
                    <a href="../contact.php" class="btn btn-primary ms-auto">Contact Support</a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Footer -->
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
                    <li><a href="../index.php">Home</a></li>
                    <li><a href="../products.php">Products</a></li>
                    <li><a href="../suppliers.php">Suppliers</a></li>
                </ul>
            </div>
            <div class="col-md-3 mb-4">
                <h6>My Account</h6>
                <ul class="list-unstyled">
                    <li><a href="dashboard.php">Dashboard</a></li>
                    <li><a href="profile.php">Profile</a></li>
                    <li><a href="orders/my-orders.php">My Orders</a></li>
                    <li><a href="wishlist.php">Wishlist</a></li>
                </ul>
            </div>
            <div class="col-md-3 mb-4">
                <h6>Contact Us</h6>
                <ul class="list-unstyled">
                    <li><i class="fas fa-envelope me-2"></i> support@techprocure.co.tz</li>
                    <li><i class="fas fa-phone me-2"></i> +255 123 456 789</li>
                    <li><i class="fas fa-map-marker-alt me-2"></i> Dar es Salaam, Tanzania</li>
                </ul>
            </div>
        </div>
        <hr class="mt-4 mb-3" style="border-color: rgba(255,255,255,0.1);">
        <div class="text-center">
            <small>&copy; <?php echo date('Y'); ?> TechProcure Tanzania. All rights reserved.</small>
        </div>
    </div>
</footer>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<script>
    // Auto-hide alerts after 5 seconds
    setTimeout(function() {
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(function(alert) {
            const bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        });
    }, 5000);
</script>

</body>
</html>