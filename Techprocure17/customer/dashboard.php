<?php
/**
 * TechProcure Tanzania - Customer Dashboard
 * File: customer/dashboard.php
 * Description: Customer dashboard with statistics, recent orders, wishlist, and account overview
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
// DEFINE MISSING FUNCTIONS IF NOT EXISTS
// =============================================
if (!function_exists('format_price')) {
    function format_price($price, $currency = CURRENCY_SYMBOL) {
        if ($price === null || $price === '') return $currency . ' 0.00';
        return $currency . ' ' . number_format((float)$price, 2);
    }
}

if (!function_exists('time_ago')) {
    function time_ago($datetime) {
        $timestamp = strtotime($datetime);
        $difference = time() - $timestamp;
        
        $periods = [
            31536000 => 'year',
            2592000 => 'month',
            604800 => 'week',
            86400 => 'day',
            3600 => 'hour',
            60 => 'minute',
            1 => 'second'
        ];
        
        foreach ($periods as $seconds => $period) {
            if ($difference >= $seconds) {
                $count = floor($difference / $seconds);
                return $count . ' ' . $period . ($count > 1 ? 's' : '') . ' ago';
            }
        }
        return 'just now';
    }
}

if (!function_exists('truncate_text')) {
    function truncate_text($text, $length = 100, $suffix = '...') {
        if (strlen($text) <= $length) return $text;
        return substr($text, 0, $length) . $suffix;
    }
}

// =============================================
// GET DATABASE CONNECTION
// =============================================
if (!isset($db) || $db === null) {
    try {
        $db = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        die("Connection failed: " . $e->getMessage());
    }
}

$conn = $db;

// =============================================
// CHECK USER AUTHENTICATION
// =============================================
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type'])) {
    header('Location: ../auth/login.php');
    exit();
}

// Only allow customers
if ($_SESSION['user_type'] !== 'customer' && $_SESSION['user_type'] !== 'buyer') {
    header('Location: ../index.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'] ?? 'Customer';
$user_email = $_SESSION['user_email'] ?? '';

// =============================================
// DETERMINE THE CORRECT CUSTOMER COLUMN
// =============================================
$customer_column = 'user_id';

// Check what columns exist in orders table
try {
    $check_stmt = $conn->query("SHOW COLUMNS FROM orders");
    $columns = [];
    while ($row = $check_stmt->fetch(PDO::FETCH_ASSOC)) {
        $columns[] = $row['Field'];
    }
    
    if (in_array('user_id', $columns)) {
        $customer_column = 'user_id';
    } elseif (in_array('customer_id', $columns)) {
        $customer_column = 'customer_id';
    } elseif (in_array('buyer_id', $columns)) {
        $customer_column = 'buyer_id';
    }
    
} catch (PDOException $e) {
    $customer_column = 'user_id';
}

// =============================================
// DETERMINE THE CORRECT AMOUNT COLUMN
// =============================================
$amount_column = 'total_amount';
try {
    if (in_array('total_amount_tsh', $columns ?? [])) {
        $amount_column = 'total_amount_tsh';
    }
} catch (PDOException $e) {
    $amount_column = 'total_amount';
}

// =============================================
// GET USER DATA
// =============================================
$user = null;
try {
    $sql = "SELECT * FROM customers WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(1, $user_id, PDO::PARAM_INT);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $user = null;
}

// =============================================
// GET STATISTICS
// =============================================
$stats = [
    'total_orders' => 0,
    'pending_orders' => 0,
    'completed_orders' => 0,
    'total_spent' => 0,
    'wishlist_count' => 0,
    'cart_count' => 0
];

try {
    // Total orders
    $sql = "SELECT COUNT(*) as total FROM orders WHERE $customer_column = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(1, $user_id, PDO::PARAM_INT);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['total_orders'] = $result ? (int)$result['total'] : 0;

    // Pending orders
    $sql = "SELECT COUNT(*) as total FROM orders WHERE $customer_column = ? AND order_status = 'pending'";
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(1, $user_id, PDO::PARAM_INT);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['pending_orders'] = $result ? (int)$result['total'] : 0;

    // Completed orders
    $sql = "SELECT COUNT(*) as total FROM orders WHERE $customer_column = ? AND order_status IN ('delivered', 'completed')";
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(1, $user_id, PDO::PARAM_INT);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['completed_orders'] = $result ? (int)$result['total'] : 0;

    // =============================================
    // TOTAL SPENT - FIXED
    // =============================================
    try {
        $sql = "SELECT SUM($amount_column) as total_spent FROM orders 
                WHERE $customer_column = ? AND payment_status = 'paid'";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(1, $user_id, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $stats['total_spent'] = $result && $result['total_spent'] ? (float)$result['total_spent'] : 0;
    } catch (PDOException $e) {
        // If column doesn't exist, try alternative
        try {
            $sql = "SELECT SUM(total_amount) as total_spent FROM orders 
                    WHERE $customer_column = ? AND payment_status = 'paid'";
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(1, $user_id, PDO::PARAM_INT);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $stats['total_spent'] = $result && $result['total_spent'] ? (float)$result['total_spent'] : 0;
        } catch (PDOException $e2) {
            $stats['total_spent'] = 0;
        }
    }

    // =============================================
    // WISHLIST COUNT - FIXED
    // =============================================
    try {
        // Check if wishlist table exists
        $check_table = $conn->query("SHOW TABLES LIKE 'wishlist'");
        if ($check_table && $check_table->rowCount() > 0) {
            
            // Determine the correct column name for wishlist
            $wishlist_column = 'customer_id';
            $check_col = $conn->query("SHOW COLUMNS FROM wishlist LIKE 'customer_id'");
            if ($check_col && $check_col->rowCount() == 0) {
                $check_col = $conn->query("SHOW COLUMNS FROM wishlist LIKE 'user_id'");
                if ($check_col && $check_col->rowCount() > 0) {
                    $wishlist_column = 'user_id';
                } else {
                    $check_col = $conn->query("SHOW COLUMNS FROM wishlist LIKE 'buyer_id'");
                    if ($check_col && $check_col->rowCount() > 0) {
                        $wishlist_column = 'buyer_id';
                    }
                }
            }
            
            $sql = "SELECT COUNT(*) as total FROM wishlist WHERE $wishlist_column = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(1, $user_id, PDO::PARAM_INT);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $stats['wishlist_count'] = $result ? (int)$result['total'] : 0;
        } else {
            $stats['wishlist_count'] = 0;
        }
    } catch (PDOException $e) {
        $stats['wishlist_count'] = 0;
    }

    // Cart count
    try {
        // Check if carts and cart_items tables exist
        $check_cart = $conn->query("SHOW TABLES LIKE 'carts'");
        if ($check_cart && $check_cart->rowCount() > 0) {
            $sql = "SELECT COUNT(*) as total FROM cart_items ci 
                    JOIN carts c ON ci.cart_id = c.id 
                    WHERE c.customer_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(1, $user_id, PDO::PARAM_INT);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $stats['cart_count'] = $result ? (int)$result['total'] : 0;
        } else {
            $stats['cart_count'] = 0;
        }
    } catch (PDOException $e) {
        $stats['cart_count'] = 0;
    }

} catch (PDOException $e) {
    error_log("Dashboard stats error: " . $e->getMessage());
}

// =============================================
// GET RECENT ORDERS
// =============================================
$recent_orders = [];
try {
    $sql = "SELECT o.*, 
                   (SELECT COUNT(*) FROM order_items WHERE order_id = o.id) as item_count
            FROM orders o 
            WHERE o.$customer_column = ? 
            ORDER BY o.created_at DESC 
            LIMIT 5";
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(1, $user_id, PDO::PARAM_INT);
    $stmt->execute();
    $recent_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $recent_orders = [];
}

// =============================================
// GET RECENT WISHLIST ITEMS - FIXED
// =============================================
$wishlist_items = [];
try {
    // Check if wishlist table exists
    $check_table = $conn->query("SHOW TABLES LIKE 'wishlist'");
    if ($check_table && $check_table->rowCount() > 0) {
        
        // Determine the correct column name for wishlist
        $wishlist_column = 'customer_id';
        $check_col = $conn->query("SHOW COLUMNS FROM wishlist LIKE 'customer_id'");
        if ($check_col && $check_col->rowCount() == 0) {
            $check_col = $conn->query("SHOW COLUMNS FROM wishlist LIKE 'user_id'");
            if ($check_col && $check_col->rowCount() > 0) {
                $wishlist_column = 'user_id';
            } else {
                $check_col = $conn->query("SHOW COLUMNS FROM wishlist LIKE 'buyer_id'");
                if ($check_col && $check_col->rowCount() > 0) {
                    $wishlist_column = 'buyer_id';
                }
            }
        }
        
        $sql = "SELECT w.*, 
                p.product_name, 
                p.price_tsh, 
                p.slug,
                (SELECT image_path FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) as primary_image
                FROM wishlist w
                JOIN products p ON w.product_id = p.id
                WHERE w.$wishlist_column = ? AND p.status = 'active'
                ORDER BY w.created_at DESC
                LIMIT 4";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(1, $user_id, PDO::PARAM_INT);
        $stmt->execute();
        $wishlist_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $wishlist_items = [];
    }
} catch (PDOException $e) {
    $wishlist_items = [];
}

// =============================================
// GET RECENT NOTIFICATIONS
// =============================================
$notifications = [];
try {
    $sql = "SELECT * FROM notifications 
            WHERE user_type = 'customer' AND user_id = ? 
            ORDER BY created_at DESC 
            LIMIT 5";
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(1, $user_id, PDO::PARAM_INT);
    $stmt->execute();
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $notifications = [];
}

// =============================================
// GET UNREAD NOTIFICATION COUNT
// =============================================
$unread_count = 0;
try {
    $sql = "SELECT COUNT(*) as total FROM notifications 
            WHERE user_type = 'customer' AND user_id = ? AND is_read = 0";
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(1, $user_id, PDO::PARAM_INT);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $unread_count = $result ? (int)$result['total'] : 0;
} catch (PDOException $e) {
    $unread_count = 0;
}

// =============================================
// PAGE TITLE
// =============================================
$page_title = 'Customer Dashboard - TechProcure Tanzania';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
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
        
        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            transition: all 0.3s;
            height: 100%;
            position: relative;
            overflow: hidden;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        
        .stat-card .stat-icon {
            font-size: 2rem;
            opacity: 0.15;
            position: absolute;
            right: 15px;
            bottom: 15px;
        }
        
        .stat-card .stat-label {
            font-size: 0.75rem;
            color: #6c757d;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 600;
        }
        
        .stat-card .stat-number {
            font-size: 1.8rem;
            font-weight: 700;
            margin: 5px 0;
        }
        
        .stat-card .stat-sub {
            font-size: 0.75rem;
            color: #6c757d;
        }
        
        .data-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 24px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        
        .data-card .card-title {
            font-weight: 600;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #e9ecef;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .data-card .card-title i {
            margin-right: 8px;
            color: #0d6efd;
        }
        
        .quick-action {
            background: white;
            border-radius: 12px;
            padding: 25px 15px;
            text-align: center;
            transition: all 0.3s;
            border: 1px solid #e9ecef;
            text-decoration: none;
            color: #333;
            display: block;
            height: 100%;
        }
        
        .quick-action:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            border-color: #0d6efd;
            color: #0d6efd;
        }
        
        .quick-action i {
            font-size: 2rem;
            display: block;
            margin-bottom: 10px;
        }
        
        .wishlist-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 10px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .wishlist-item:last-child {
            border-bottom: none;
        }
        
        .wishlist-item img {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 8px;
            background: #f8f9fa;
        }
        
        .wishlist-item .wishlist-name {
            font-weight: 500;
            font-size: 0.9rem;
        }
        
        .wishlist-item .wishlist-price {
            font-weight: 600;
            color: #0d6efd;
        }
        
        .notification-item {
            padding: 10px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .notification-item:last-child {
            border-bottom: none;
        }
        
        .notification-item.unread {
            background: #f0f7ff;
            margin: 0 -15px;
            padding: 10px 15px;
            border-radius: 8px;
        }
        
        .notification-item .notification-title {
            font-weight: 600;
            font-size: 0.9rem;
        }
        
        .notification-item .notification-message {
            font-size: 0.85rem;
            color: #6c757d;
        }
        
        .notification-item .notification-time {
            font-size: 0.7rem;
            color: #adb5bd;
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
        
        .toast-container {
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 9999;
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
            .stat-card .stat-number {
                font-size: 1.4rem;
            }
            .stat-card .stat-icon {
                font-size: 1.5rem;
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
                    <span class="cart-count"><?php echo $stats['cart_count']; ?></span>
                </a>
                <a href="../auth/logout.php" class="btn btn-outline-light btn-sm">
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
                    <?php echo strtoupper(substr($user_name, 0, 1)); ?>
                </div>
                <h6><?php echo htmlspecialchars($user_name); ?></h6>
                <small><?php echo htmlspecialchars($user_email); ?></small>
            </div>
            
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link active" href="dashboard.php">
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
                        <?php if($stats['pending_orders'] > 0): ?>
                            <span class="badge bg-warning"><?php echo $stats['pending_orders']; ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="wishlist.php">
                        <i class="fas fa-heart"></i> Wishlist
                        <?php if($stats['wishlist_count'] > 0): ?>
                            <span class="badge bg-danger"><?php echo $stats['wishlist_count']; ?></span>
                        <?php endif; ?>
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
        
        <!-- Dashboard Content -->
        <div class="dashboard-content">
            
            <!-- Welcome -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h4 class="mb-0">Welcome back, <?php echo htmlspecialchars($user_name); ?>!</h4>
                    <p class="text-muted">Here's what's happening with your account</p>
                </div>
                <div>
                    <?php if($unread_count > 0): ?>
                        <span class="badge bg-danger me-2">
                            <i class="fas fa-bell me-1"></i><?php echo $unread_count; ?> notifications
                        </span>
                    <?php endif; ?>
                    <span class="badge bg-primary">Customer</span>
                </div>
            </div>
            
            <!-- Statistics Cards -->
            <div class="row g-4 mb-4">
                <div class="col-md-3 col-6">
                    <div class="stat-card">
                        <div class="stat-label">Total Orders</div>
                        <div class="stat-number"><?php echo number_format($stats['total_orders']); ?></div>
                        <div class="stat-sub">
                            <i class="fas fa-check-circle text-success"></i> <?php echo number_format($stats['completed_orders']); ?> completed
                        </div>
                        <div class="stat-icon"><i class="fas fa-shopping-cart"></i></div>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="stat-card">
                        <div class="stat-label">Pending Orders</div>
                        <div class="stat-number text-warning"><?php echo number_format($stats['pending_orders']); ?></div>
                        <div class="stat-sub">
                            <i class="fas fa-clock"></i> Awaiting processing
                        </div>
                        <div class="stat-icon"><i class="fas fa-clock"></i></div>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="stat-card">
                        <div class="stat-label">Total Spent</div>
                        <div class="stat-number text-primary"><?php echo format_price($stats['total_spent']); ?></div>
                        <div class="stat-sub">
                            <i class="fas fa-calendar-alt"></i> Lifetime purchases
                        </div>
                        <div class="stat-icon"><i class="fas fa-money-bill-wave"></i></div>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="stat-card">
                        <div class="stat-label">Wishlist</div>
                        <div class="stat-number text-danger"><?php echo number_format($stats['wishlist_count']); ?></div>
                        <div class="stat-sub">
                            <i class="fas fa-heart"></i> Saved items
                        </div>
                        <div class="stat-icon"><i class="fas fa-heart"></i></div>
                    </div>
                </div>
            </div>
            
            <!-- Quick Actions -->
            <div class="row g-3 mb-4">
                <div class="col-md-3 col-6">
                    <a href="../products.php" class="quick-action">
                        <i class="fas fa-search text-primary"></i>
                        <small>Browse Products</small>
                    </a>
                </div>
                <div class="col-md-3 col-6">
                    <a href="orders/my-orders.php" class="quick-action">
                        <i class="fas fa-shopping-bag text-success"></i>
                        <small>My Orders</small>
                    </a>
                </div>
                <div class="col-md-3 col-6">
                    <a href="wishlist.php" class="quick-action">
                        <i class="fas fa-heart text-danger"></i>
                        <small>Wishlist</small>
                    </a>
                </div>
                <div class="col-md-3 col-6">
                    <a href="profile.php" class="quick-action">
                        <i class="fas fa-user text-info"></i>
                        <small>My Profile</small>
                    </a>
                </div>
            </div>
            
            <!-- Recent Orders & Wishlist -->
            <div class="row g-4">
                
                <!-- Recent Orders -->
                <div class="col-lg-7">
                    <div class="data-card">
                        <div class="card-title">
                            <span><i class="fas fa-history"></i> Recent Orders</span>
                            <a href="orders/my-orders.php" class="btn btn-sm btn-primary">View All</a>
                        </div>
                        <div class="table-responsive">
                            <?php if (!empty($recent_orders)): ?>
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Order #</th>
                                            <th>Date</th>
                                            <th>Amount</th>
                                            <th>Status</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($recent_orders as $order): ?>
                                        <tr>
                                            <td><code><?php echo htmlspecialchars($order['order_number']); ?></code></td>
                                            <td><?php echo date('M d, Y', strtotime($order['created_at'])); ?></td>
                                            <td><?php echo format_price($order[$amount_column] ?? 0); ?></td>
                                            <td>
                                                <?php
                                                $status_colors = [
                                                    'pending' => 'warning',
                                                    'processing' => 'info',
                                                    'shipped' => 'primary',
                                                    'delivered' => 'success',
                                                    'completed' => 'success',
                                                    'cancelled' => 'danger'
                                                ];
                                                $color = $status_colors[$order['order_status']] ?? 'secondary';
                                                ?>
                                                <span class="badge bg-<?php echo $color; ?>">
                                                    <?php echo ucfirst($order['order_status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <a href="orders/order-details.php?id=<?php echo $order['id']; ?>" class="btn btn-sm btn-outline-primary">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php else: ?>
                                <div class="text-center py-3">
                                    <i class="fas fa-shopping-bag fa-3x text-muted mb-2"></i>
                                    <p class="text-muted">You haven't placed any orders yet.</p>
                                    <a href="../products.php" class="btn btn-primary btn-sm">Start Shopping</a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Wishlist -->
                <div class="col-lg-5">
                    <div class="data-card">
                        <div class="card-title">
                            <span><i class="fas fa-heart text-danger"></i> Wishlist</span>
                            <a href="wishlist.php" class="btn btn-sm btn-outline-danger">View All</a>
                        </div>
                        <?php if (!empty($wishlist_items)): ?>
                            <?php foreach($wishlist_items as $item): ?>
                            <div class="wishlist-item">
                                <img src="<?php 
                                    if (!empty($item['primary_image'])) {
                                        if (strpos($item['primary_image'], 'uploads/') === 0) {
                                            echo '../' . $item['primary_image'];
                                        } else {
                                            echo '../uploads/products/' . $item['product_id'] . '/' . $item['primary_image'];
                                        }
                                    } else {
                                        echo '../assets/images/placeholder-product.jpg';
                                    }
                                ?>" 
                                     alt="<?php echo htmlspecialchars($item['product_name']); ?>">
                                <div class="flex-grow-1">
                                    <div class="wishlist-name"><?php echo truncate_text($item['product_name'], 50); ?></div>
                                    <div class="wishlist-price"><?php echo format_price($item['price_tsh']); ?></div>
                                </div>
                                <div>
                                    <a href="../product-details.php?id=<?php echo $item['product_id']; ?>" class="btn btn-sm btn-primary">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <button onclick="removeWishlistItem(<?php echo $item['id']; ?>)" class="btn btn-sm btn-outline-danger">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="text-center py-3">
                                <i class="fas fa-heart fa-3x text-muted mb-2"></i>
                                <p class="text-muted">Your wishlist is empty.</p>
                                <a href="../products.php" class="btn btn-primary btn-sm">Browse Products</a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Help Section -->
            <div class="alert alert-info d-flex align-items-center">
                <i class="fas fa-headset fa-2x me-3"></i>
                <div>
                    <h6 class="mb-0">Need Help?</h6>
                    <p class="mb-0 small">Contact our support team for assistance with your orders or account.</p>
                </div>
                <a href="../contact.php" class="btn btn-primary ms-auto">Contact Support</a>
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

<!-- ===================================================== -->
<!-- TOAST CONTAINER -->
<!-- ===================================================== -->
<div class="toast-container"></div>

<!-- ===================================================== -->
<!-- SCRIPTS -->
<!-- ===================================================== -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<script>
// =============================================
// MARK NOTIFICATION AS READ
// =============================================
function markNotificationRead(notificationId) {
    $.ajax({
        url: 'ajax/mark-notification-read.php',
        type: 'POST',
        data: { id: notificationId },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                location.reload();
            }
        }
    });
}

// =============================================
// REMOVE FROM WISHLIST
// =============================================
function removeWishlistItem(wishlistId) {
    if (!confirm('Remove this item from your wishlist?')) return;
    
    $.ajax({
        url: 'ajax/remove-wishlist.php',
        type: 'POST',
        data: { id: wishlistId },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showToast('Success', 'Item removed from wishlist', 'success');
                setTimeout(function() { location.reload(); }, 1500);
            } else {
                showToast('Error', response.message || 'Error removing item', 'error');
            }
        },
        error: function() {
            showToast('Error', 'Failed to remove item', 'error');
        }
    });
}

// =============================================
// TOAST NOTIFICATION
// =============================================
function showToast(title, message, type) {
    const toastHtml = `
        <div class="toast align-items-center text-white bg-${type === 'success' ? 'success' : 'danger'} border-0" role="alert">
            <div class="d-flex">
                <div class="toast-body">
                    <strong>${title}</strong><br>
                    ${message}
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        </div>
    `;
    
    $('.toast-container').append(toastHtml);
    const toast = new bootstrap.Toast($('.toast').last(), { delay: 3000 });
    toast.show();
    
    setTimeout(function() { $('.toast').last().remove(); }, 3500);
}
</script>

</body>
</html>