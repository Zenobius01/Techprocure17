<?php
/**
 * TechProcure Tanzania - Customer Settings Page
 * File: customer/settings.php
 * Description: Customer account settings and preferences
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
// UPDATE SETTINGS
// =============================================

$error = '';
$success = '';

// Update notification settings
if (isset($_POST['update_notifications'])) {
    $email_notifications = isset($_POST['email_notifications']) ? 1 : 0;
    $sms_notifications = isset($_POST['sms_notifications']) ? 1 : 0;
    $order_updates = isset($_POST['order_updates']) ? 1 : 0;
    $promotional_emails = isset($_POST['promotional_emails']) ? 1 : 0;
    
    try {
        // Check if settings table exists
        $table_check = $db->query("SHOW TABLES LIKE 'user_settings'");
        if ($table_check->rowCount() > 0) {
            $stmt = $db->prepare("UPDATE user_settings SET 
                                email_notifications = ?,
                                sms_notifications = ?,
                                order_updates = ?,
                                promotional_emails = ?,
                                updated_at = NOW()
                                WHERE user_id = ?");
            $stmt->execute([$email_notifications, $sms_notifications, $order_updates, $promotional_emails, $user_id]);
            
            if ($stmt->rowCount() == 0) {
                $stmt = $db->prepare("INSERT INTO user_settings 
                                    (user_id, email_notifications, sms_notifications, order_updates, promotional_emails, created_at) 
                                    VALUES (?, ?, ?, ?, ?, NOW())");
                $stmt->execute([$user_id, $email_notifications, $sms_notifications, $order_updates, $promotional_emails]);
            }
        } else {
            // Create table if not exists
            $db->exec("
                CREATE TABLE IF NOT EXISTS user_settings (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    user_id INT NOT NULL,
                    email_notifications BOOLEAN DEFAULT TRUE,
                    sms_notifications BOOLEAN DEFAULT FALSE,
                    order_updates BOOLEAN DEFAULT TRUE,
                    promotional_emails BOOLEAN DEFAULT FALSE,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME ON UPDATE CURRENT_TIMESTAMP,
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
                )
            ");
            
            $stmt = $db->prepare("INSERT INTO user_settings 
                                (user_id, email_notifications, sms_notifications, order_updates, promotional_emails, created_at) 
                                VALUES (?, ?, ?, ?, ?, NOW())");
            $stmt->execute([$user_id, $email_notifications, $sms_notifications, $order_updates, $promotional_emails]);
        }
        
        $success = 'Notification settings updated successfully!';
    } catch (PDOException $e) {
        $error = 'Failed to update notification settings.';
    }
}

// Update preferences
if (isset($_POST['update_preferences'])) {
    $language = sanitizeInput($_POST['language'] ?? 'en');
    $currency = sanitizeInput($_POST['currency'] ?? 'TSh');
    $timezone = sanitizeInput($_POST['timezone'] ?? 'Africa/Dar_es_Salaam');
    
    try {
        $table_check = $db->query("SHOW TABLES LIKE 'user_settings'");
        if ($table_check->rowCount() > 0) {
            $stmt = $db->prepare("UPDATE user_settings SET 
                                language = ?,
                                currency = ?,
                                timezone = ?,
                                updated_at = NOW()
                                WHERE user_id = ?");
            $stmt->execute([$language, $currency, $timezone, $user_id]);
            
            if ($stmt->rowCount() == 0) {
                $stmt = $db->prepare("INSERT INTO user_settings 
                                    (user_id, language, currency, timezone, created_at) 
                                    VALUES (?, ?, ?, ?, NOW())");
                $stmt->execute([$user_id, $language, $currency, $timezone]);
            }
        }
        
        $success = 'Preferences updated successfully!';
    } catch (PDOException $e) {
        $error = 'Failed to update preferences.';
    }
}

// Delete account
if (isset($_POST['delete_account'])) {
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if (empty($confirm_password)) {
        $error = 'Please enter your password to confirm account deletion.';
    } else {
        // Verify password
        $stmt = $db->prepare("SELECT password_hash FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user_data = $stmt->fetch();
        
        if ($user_data && password_verify($confirm_password, $user_data['password_hash'])) {
            try {
                // Delete user (cascade will delete related records)
                $stmt = $db->prepare("UPDATE users SET status = 'inactive', email = CONCAT(email, '_deleted_', ?) WHERE id = ?");
                $stmt->execute([time(), $user_id]);
                
                // Log activity
                try {
                    $log_sql = "INSERT INTO activity_logs (user_id, action, entity_type, ip_address, user_agent, created_at) 
                                VALUES (?, 'Account Deactivated', 'user', ?, ?, NOW())";
                    $log_stmt = $db->prepare($log_sql);
                    $log_stmt->execute([$user_id, $user_id, $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '']);
                } catch (PDOException $e) {
                    // Continue
                }
                
                // Destroy session and logout
                destroySession();
                header("Location: ../index.php?account_deleted=1");
                exit();
                
            } catch (PDOException $e) {
                $error = 'Failed to delete account. Please contact support.';
            }
        } else {
            $error = 'Incorrect password. Please try again.';
        }
    }
}

// =============================================
// GET USER SETTINGS
// =============================================

$settings = [
    'email_notifications' => true,
    'sms_notifications' => false,
    'order_updates' => true,
    'promotional_emails' => false,
    'language' => 'en',
    'currency' => 'TSh',
    'timezone' => 'Africa/Dar_es_Salaam'
];

try {
    $table_check = $db->query("SHOW TABLES LIKE 'user_settings'");
    if ($table_check->rowCount() > 0) {
        $stmt = $db->prepare("SELECT * FROM user_settings WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $user_settings = $stmt->fetch();
        if ($user_settings) {
            $settings = array_merge($settings, $user_settings);
        }
    }
} catch (PDOException $e) {
    // Settings table might not exist yet
}

// =============================================
// GET CART COUNT
// =============================================

$cart_count = function_exists('getCartCount') ? getCartCount() : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - TechProcure Tanzania</title>
    
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
        
        /* Settings Content */
        .settings-content {
            flex: 1;
        }
        
        /* Settings Card */
        .settings-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 24px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        
        .settings-card .card-title {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #e9ecef;
        }
        
        .settings-card .card-title i {
            margin-right: 8px;
            color: #0d6efd;
        }
        
        .settings-card .form-label {
            font-weight: 500;
            font-size: 0.85rem;
        }
        
        .settings-card .form-control,
        .settings-card .form-select {
            border-radius: 10px;
            padding: 10px 15px;
            border: 1px solid #e0e0e0;
            transition: all 0.3s;
        }
        
        .settings-card .form-control:focus,
        .settings-card .form-select:focus {
            border-color: #0d6efd;
            box-shadow: 0 0 0 3px rgba(13,110,253,0.1);
            outline: none;
        }
        
        .settings-card .form-check-input:checked {
            background-color: #0d6efd;
            border-color: #0d6efd;
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
        
        .btn-danger-outline {
            border-radius: 10px;
            padding: 10px 25px;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .btn-danger-outline:hover {
            transform: translateY(-2px);
        }
        
        .alert {
            border-radius: 10px;
        }
        
        /* Danger Zone */
        .danger-zone {
            border: 2px solid #dc3545;
            border-radius: 15px;
            padding: 20px;
            background: #fff5f5;
        }
        
        .danger-zone .danger-title {
            color: #dc3545;
            font-weight: 600;
            margin-bottom: 10px;
        }
        
        /* Footer */
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
                    <a class="nav-link" href="profile.php">
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
                    <a class="nav-link active" href="settings.php">
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
        
        <!-- Settings Content -->
        <div class="settings-content">
            <!-- Page Title -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h4 class="mb-0"><i class="fas fa-cog me-2 text-primary"></i>Settings</h4>
                    <p class="text-muted">Manage your account preferences and notifications</p>
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
            
            <!-- Notification Settings -->
            <div class="settings-card">
                <div class="card-title">
                    <i class="fas fa-bell"></i> Notification Settings
                </div>
                
                <form method="POST" action="">
                    <input type="hidden" name="update_notifications" value="1">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <div class="form-check form-switch">
                                <input type="checkbox" name="email_notifications" class="form-check-input" id="emailNotifications" <?php echo $settings['email_notifications'] ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="emailNotifications">
                                    <i class="fas fa-envelope me-1"></i> Email Notifications
                                </label>
                            </div>
                            <small class="text-muted">Receive order confirmations and updates via email</small>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="form-check form-switch">
                                <input type="checkbox" name="sms_notifications" class="form-check-input" id="smsNotifications" <?php echo $settings['sms_notifications'] ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="smsNotifications">
                                    <i class="fas fa-sms me-1"></i> SMS Notifications
                                </label>
                            </div>
                            <small class="text-muted">Receive order updates via SMS on your phone</small>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <div class="form-check form-switch">
                                <input type="checkbox" name="order_updates" class="form-check-input" id="orderUpdates" <?php echo $settings['order_updates'] ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="orderUpdates">
                                    <i class="fas fa-shopping-cart me-1"></i> Order Updates
                                </label>
                            </div>
                            <small class="text-muted">Get notified about order status changes</small>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="form-check form-switch">
                                <input type="checkbox" name="promotional_emails" class="form-check-input" id="promotionalEmails" <?php echo $settings['promotional_emails'] ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="promotionalEmails">
                                    <i class="fas fa-bullhorn me-1"></i> Promotional Emails
                                </label>
                            </div>
                            <small class="text-muted">Receive special offers and product updates</small>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn-save">
                        <i class="fas fa-save me-2"></i> Save Notification Settings
                    </button>
                </form>
            </div>
            
            <!-- Preferences -->
            <div class="settings-card">
                <div class="card-title">
                    <i class="fas fa-sliders-h"></i> Preferences
                </div>
                
                <form method="POST" action="">
                    <input type="hidden" name="update_preferences" value="1">
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Language</label>
                            <select name="language" class="form-select">
                                <option value="en" <?php echo ($settings['language'] ?? 'en') == 'en' ? 'selected' : ''; ?>>English</option>
                                <option value="sw" <?php echo ($settings['language'] ?? 'en') == 'sw' ? 'selected' : ''; ?>>Swahili</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Currency</label>
                            <select name="currency" class="form-select">
                                <option value="TSh" <?php echo ($settings['currency'] ?? 'TSh') == 'TSh' ? 'selected' : ''; ?>>TSh - Tanzanian Shilling</option>
                                <option value="USD" <?php echo ($settings['currency'] ?? 'TSh') == 'USD' ? 'selected' : ''; ?>>USD - US Dollar</option>
                                <option value="EUR" <?php echo ($settings['currency'] ?? 'TSh') == 'EUR' ? 'selected' : ''; ?>>EUR - Euro</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Time Zone</label>
                            <select name="timezone" class="form-select">
                                <option value="Africa/Dar_es_Salaam" <?php echo ($settings['timezone'] ?? 'Africa/Dar_es_Salaam') == 'Africa/Dar_es_Salaam' ? 'selected' : ''; ?>>Dar es Salaam (UTC+3)</option>
                                <option value="Africa/Nairobi" <?php echo ($settings['timezone'] ?? 'Africa/Dar_es_Salaam') == 'Africa/Nairobi' ? 'selected' : ''; ?>>Nairobi (UTC+3)</option>
                                <option value="Africa/Kampala" <?php echo ($settings['timezone'] ?? 'Africa/Dar_es_Salaam') == 'Africa/Kampala' ? 'selected' : ''; ?>>Kampala (UTC+3)</option>
                                <option value="Africa/Addis_Ababa" <?php echo ($settings['timezone'] ?? 'Africa/Dar_es_Salaam') == 'Africa/Addis_Ababa' ? 'selected' : ''; ?>>Addis Ababa (UTC+3)</option>
                                <option value="UTC" <?php echo ($settings['timezone'] ?? 'Africa/Dar_es_Salaam') == 'UTC' ? 'selected' : ''; ?>>UTC</option>
                            </select>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn-save">
                        <i class="fas fa-save me-2"></i> Save Preferences
                    </button>
                </form>
            </div>
            
            <!-- Danger Zone -->
            <div class="danger-zone">
                <div class="danger-title">
                    <i class="fas fa-exclamation-triangle me-2"></i> Danger Zone
                </div>
                <p class="text-muted small">Once you delete your account, there is no going back. Please be certain.</p>
                
                <button type="button" class="btn btn-danger btn-danger-outline" data-bs-toggle="modal" data-bs-target="#deleteAccountModal">
                    <i class="fas fa-trash me-2"></i> Delete Account
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Delete Account Modal -->
<div class="modal fade" id="deleteAccountModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title"><i class="fas fa-exclamation-triangle me-2"></i>Delete Account</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="text-danger"><strong>Warning:</strong> This action cannot be undone.</p>
                <p>Deleting your account will permanently remove:</p>
                <ul>
                    <li>Your profile information</li>
                    <li>Your order history</li>
                    <li>Your wishlist items</li>
                    <li>Your saved preferences</li>
                </ul>
                <p>To confirm, please enter your password.</p>
                
                <form method="POST" action="">
                    <input type="hidden" name="delete_account" value="1">
                    <div class="mb-3">
                        <label class="form-label">Enter Password</label>
                        <input type="password" name="confirm_password" class="form-control" required>
                    </div>
                    <button type="submit" class="btn btn-danger w-100">
                        <i class="fas fa-trash me-2"></i> Permanently Delete Account
                    </button>
                </form>
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