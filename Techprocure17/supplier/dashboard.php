<?php
/**
 * TechProcure Tanzania - Supplier Dashboard
 * File: supplier/dashboard.php
 * Description: Complete supplier dashboard with statistics, products, orders, and earnings
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
        if (!$datetime) return 'Never';
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
// CHECK SUPPLIER AUTHENTICATION
// =============================================
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type'])) {
    header('Location: ../auth/login.php');
    exit();
}

if ($_SESSION['user_type'] !== 'supplier') {
    header('Location: ../auth/login.php');
    exit();
}

$supplier_id = $_SESSION['user_id'];
$supplier_name = $_SESSION['company_name'] ?? $_SESSION['user_name'] ?? 'Supplier';
$user_email = $_SESSION['user_email'] ?? '';

// =============================================
// GET SUPPLIER DATA - FIXED WITH USER_ID
// =============================================
$supplier = null;
$error_message = '';

try {
    // First, check what columns exist in suppliers table
    $check_columns = $conn->query("SHOW COLUMNS FROM suppliers");
    $existing_columns = [];
    while ($col = $check_columns->fetch(PDO::FETCH_ASSOC)) {
        $existing_columns[] = $col['Field'];
    }
    
    // Check if supplier exists in suppliers table
    $sql = "SELECT * FROM suppliers WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$supplier_id]);
    $supplier = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // If supplier doesn't exist, create from user data
    if (!$supplier) {
        // Check if user exists in users table
        $user_sql = "SELECT * FROM users WHERE id = ?";
        $user_stmt = $conn->prepare($user_sql);
        $user_stmt->execute([$supplier_id]);
        $user = $user_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            // Build insert query with all possible columns
            $insert_fields = ['id', 'user_id'];
            $insert_values = [
                $supplier_id,
                $supplier_id  // user_id is same as id
            ];
            
            // Add company_name if exists
            if (in_array('company_name', $existing_columns)) {
                $insert_fields[] = 'company_name';
                $insert_values[] = $user['company_name'] ?? $user['full_name'] ?? 'Supplier Company';
            }
            
            // Add contact_person if exists
            if (in_array('contact_person', $existing_columns)) {
                $insert_fields[] = 'contact_person';
                $insert_values[] = $user['full_name'] ?? 'Supplier';
            }
            
            // Add email if exists
            if (in_array('email', $existing_columns)) {
                $insert_fields[] = 'email';
                $insert_values[] = $user['email'];
            }
            
            // Add phone if exists
            if (in_array('phone', $existing_columns)) {
                $insert_fields[] = 'phone';
                $insert_values[] = $user['phone'] ?? '';
            }
            
            // Add address if exists
            if (in_array('address', $existing_columns)) {
                $insert_fields[] = 'address';
                $insert_values[] = $user['address'] ?? '';
            }
            
            // Add city if exists
            if (in_array('city', $existing_columns)) {
                $insert_fields[] = 'city';
                $insert_values[] = $user['city'] ?? '';
            }
            
            // Add region if exists
            if (in_array('region', $existing_columns)) {
                $insert_fields[] = 'region';
                $insert_values[] = $user['region'] ?? '';
            }
            
            // Add country if exists
            if (in_array('country', $existing_columns)) {
                $insert_fields[] = 'country';
                $insert_values[] = $user['country'] ?? 'Tanzania';
            }
            
            // Add approval_status if exists
            if (in_array('approval_status', $existing_columns)) {
                $insert_fields[] = 'approval_status';
                $insert_values[] = 'approved';
            }
            
            // Add status if exists
            if (in_array('status', $existing_columns)) {
                $insert_fields[] = 'status';
                $insert_values[] = 'active';
            }
            
            // Add created_at if exists
            if (in_array('created_at', $existing_columns)) {
                $insert_fields[] = 'created_at';
                $insert_values[] = date('Y-m-d H:i:s');
            }
            
            $placeholders = implode(',', array_fill(0, count($insert_fields), '?'));
            $insert_sql = "INSERT INTO suppliers (" . implode(',', $insert_fields) . ") VALUES ($placeholders)";
            $insert_stmt = $conn->prepare($insert_sql);
            $insert_stmt->execute($insert_values);
            
            // Get the newly created supplier
            $stmt = $conn->prepare($sql);
            $stmt->execute([$supplier_id]);
            $supplier = $stmt->fetch(PDO::FETCH_ASSOC);
        }
    }
    
    if (!$supplier) {
        $error_message = 'Supplier account not found. Please contact support.';
    } elseif (isset($supplier['approval_status']) && $supplier['approval_status'] !== 'approved') {
        $status_messages = [
            'pending' => 'Your supplier account is pending admin approval. Please wait for confirmation.',
            'rejected' => 'Your supplier application has been rejected. Please contact support.',
            'suspended' => 'Your supplier account has been suspended. Please contact support.'
        ];
        $error_message = $status_messages[$supplier['approval_status']] ?? 'Account not active.';
    } elseif (isset($supplier['status']) && $supplier['status'] !== 'active') {
        $error_message = 'Your supplier account is inactive. Please contact support.';
    }
    
} catch (PDOException $e) {
    $error_message = 'Database error: ' . $e->getMessage();
}

// =============================================
// GET STATISTICS
// =============================================
$stats = [
    'total_products' => 0,
    'pending_products' => 0,
    'total_orders' => 0,
    'pending_orders' => 0,
    'total_revenue' => 0,
    'total_earnings' => 0,
    'total_reviews' => 0,
    'avg_rating' => 0
];

if (!$error_message && $supplier) {
    try {
        // Total products
        $sql = "SELECT COUNT(*) as total FROM products WHERE supplier_id = ? AND approval_status = 'approved'";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$supplier_id]);
        $stats['total_products'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

        // Pending products
        $sql = "SELECT COUNT(*) as total FROM products WHERE supplier_id = ? AND approval_status = 'pending'";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$supplier_id]);
        $stats['pending_products'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

        // Determine amount column
        $amount_column = 'total_amount_tsh';
        try {
            $check_stmt = $conn->query("SHOW COLUMNS FROM orders LIKE 'total_amount_tsh'");
            if ($check_stmt->rowCount() == 0) {
                $amount_column = 'total_amount';
            }
        } catch (PDOException $e) {
            $amount_column = 'total_amount';
        }

        // Total orders
        $sql = "SELECT COUNT(*) as total FROM orders WHERE supplier_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$supplier_id]);
        $stats['total_orders'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

        // Pending orders
        $sql = "SELECT COUNT(*) as total FROM orders WHERE supplier_id = ? AND order_status = 'pending'";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$supplier_id]);
        $stats['pending_orders'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

        // Total revenue
        $sql = "SELECT SUM($amount_column) as total FROM orders WHERE supplier_id = ? AND payment_status = 'paid' AND order_status IN ('delivered', 'completed')";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$supplier_id]);
        $stats['total_revenue'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

        $stats['total_earnings'] = $stats['total_revenue'];

        // Reviews and rating
        $sql = "SELECT COUNT(*) as total, AVG(rating) as avg_rating FROM supplier_reviews WHERE supplier_id = ? AND status = 'approved'";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$supplier_id]);
        $review_stats = $stmt->fetch(PDO::FETCH_ASSOC);
        $stats['total_reviews'] = $review_stats['total'] ?? 0;
        $stats['avg_rating'] = round($review_stats['avg_rating'] ?? 0, 1);

        // Update supplier rating
        if (in_array('rating', $existing_columns) && in_array('total_reviews', $existing_columns)) {
            $update_sql = "UPDATE suppliers SET rating = ?, total_reviews = ? WHERE id = ?";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->execute([$stats['avg_rating'], $stats['total_reviews'], $supplier_id]);
        }

    } catch (PDOException $e) {
        error_log("Supplier stats error: " . $e->getMessage());
    }
}

// =============================================
// GET RECENT ORDERS
// =============================================
$recent_orders = [];
if (!$error_message && $supplier) {
    try {
        // Determine customer column
        $customer_column = 'customer_id';
        try {
            $check_stmt = $conn->query("SHOW COLUMNS FROM orders LIKE 'customer_id'");
            if ($check_stmt->rowCount() == 0) {
                $check_stmt = $conn->query("SHOW COLUMNS FROM orders LIKE 'buyer_id'");
                if ($check_stmt->rowCount() > 0) {
                    $customer_column = 'buyer_id';
                } else {
                    $check_stmt = $conn->query("SHOW COLUMNS FROM orders LIKE 'user_id'");
                    if ($check_stmt->rowCount() > 0) {
                        $customer_column = 'user_id';
                    }
                }
            }
        } catch (PDOException $e) {
            $customer_column = 'customer_id';
        }

        $sql = "SELECT o.*, 
                       c.company_name as customer_name,
                       c.contact_person as customer_contact,
                       (SELECT COUNT(*) FROM order_items WHERE order_id = o.id) as item_count
                FROM orders o
                JOIN customers c ON o.$customer_column = c.id
                WHERE o.supplier_id = ?
                ORDER BY o.created_at DESC
                LIMIT 5";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$supplier_id]);
        $recent_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $recent_orders = [];
    }
}

// =============================================
// GET RECENT PRODUCTS
// =============================================
$recent_products = [];
if (!$error_message && $supplier) {
    try {
        $sql = "SELECT p.*, 
                       c.name as category_name,
                       (SELECT image_path FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) as primary_image,
                       (SELECT quantity_available FROM inventory WHERE product_id = p.id) as stock_quantity
                FROM products p
                LEFT JOIN categories c ON p.category_id = c.id
                WHERE p.supplier_id = ?
                ORDER BY p.created_at DESC
                LIMIT 5";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$supplier_id]);
        $recent_products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $recent_products = [];
    }
}

// =============================================
// GET NOTIFICATIONS
// =============================================
$notifications = [];
$unread_count = 0;
if (!$error_message && $supplier) {
    try {
        $sql = "SELECT * FROM notifications 
                WHERE user_type = 'supplier' AND user_id = ? 
                ORDER BY created_at DESC 
                LIMIT 5";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$supplier_id]);
        $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get unread count
        $sql = "SELECT COUNT(*) as total FROM notifications 
                WHERE user_type = 'supplier' AND user_id = ? AND is_read = 0";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$supplier_id]);
        $unread_count = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    } catch (PDOException $e) {
        $notifications = [];
        $unread_count = 0;
    }
}

// =============================================
// GET LATEST REVIEWS
// =============================================
$latest_reviews = [];
if (!$error_message && $supplier) {
    try {
        $sql = "SELECT sr.*, 
                       c.company_name as customer_name,
                       c.contact_person as customer_contact,
                       o.order_number
                FROM supplier_reviews sr
                JOIN customers c ON sr.customer_id = c.id
                JOIN orders o ON sr.order_id = o.id
                WHERE sr.supplier_id = ? AND sr.status = 'approved'
                ORDER BY sr.created_at DESC
                LIMIT 3";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$supplier_id]);
        $latest_reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $latest_reviews = [];
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
$page_title = 'Supplier Dashboard - TechProcure Tanzania';
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
            color: #198754;
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
            border-color: #198754;
            color: #198754;
        }
        
        .quick-action i {
            font-size: 2rem;
            display: block;
            margin-bottom: 10px;
        }
        
        .order-item {
            display: flex;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .order-item:last-child {
            border-bottom: none;
        }
        
        .order-item .order-info {
            flex: 1;
        }
        
        .product-mini {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .product-mini:last-child {
            border-bottom: none;
        }
        
        .product-mini img {
            width: 40px;
            height: 40px;
            object-fit: cover;
            border-radius: 8px;
            background: #f8f9fa;
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
        
        .review-item {
            padding: 10px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .review-item:last-child {
            border-bottom: none;
        }
        
        .rating-stars {
            color: #ffc107;
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
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border-left: 4px solid #dc3545;
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
                    <span class="cart-count"><?php echo $cart_count; ?></span>
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
                    <?php echo strtoupper(substr($supplier_name, 0, 1)); ?>
                </div>
                <h6><?php echo htmlspecialchars($supplier_name); ?></h6>
                <small><?php echo htmlspecialchars($user_email); ?></small>
                <?php if($supplier && isset($supplier['verification_badge']) && $supplier['verification_badge']): ?>
                    <span class="badge bg-success mt-2">
                        <i class="fas fa-check-circle me-1"></i>Verified
                    </span>
                <?php endif; ?>
            </div>
            
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link active" href="dashboard.php">
                        <i class="fas fa-tachometer-alt"></i> Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="products/my-products.php">
                        <i class="fas fa-box"></i> My Products
                        <?php if($stats['pending_products'] > 0): ?>
                            <span class="badge bg-warning"><?php echo $stats['pending_products']; ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="products/add-product.php">
                        <i class="fas fa-plus-circle"></i> Add Product
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="orders/supplier-orders.php">
                        <i class="fas fa-shopping-cart"></i> Orders
                        <?php if($stats['pending_orders'] > 0): ?>
                            <span class="badge bg-warning"><?php echo $stats['pending_orders']; ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="earnings/earnings.php">
                        <i class="fas fa-money-bill-wave"></i> Earnings
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="quotations/quotation-requests.php">
                        <i class="fas fa-file-alt"></i> Quotations
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="profile.php">
                        <i class="fas fa-user"></i> Profile
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
                    <h4 class="mb-0">Welcome back, <?php echo htmlspecialchars($supplier_name); ?>!</h4>
                    <p class="text-muted">Here's what's happening with your store</p>
                </div>
                <div>
                    <?php if($unread_count > 0): ?>
                        <span class="badge bg-danger me-2">
                            <i class="fas fa-bell me-1"></i><?php echo $unread_count; ?> notifications
                        </span>
                    <?php endif; ?>
                    <span class="badge bg-success">Supplier</span>
                </div>
            </div>
            
            <!-- Error Message -->
            <?php if($error_message): ?>
                <div class="alert alert-error alert-dismissible fade show">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong>Account Issue:</strong> <?php echo $error_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if(!$error_message && $supplier): ?>
            
            <!-- Statistics Cards -->
            <div class="row g-4 mb-4">
                <div class="col-md-3 col-6">
                    <div class="stat-card">
                        <div class="stat-label">Total Products</div>
                        <div class="stat-number"><?php echo number_format($stats['total_products']); ?></div>
                        <div class="stat-sub">
                            <i class="fas fa-clock text-warning"></i> <?php echo number_format($stats['pending_products']); ?> pending
                        </div>
                        <div class="stat-icon"><i class="fas fa-box"></i></div>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="stat-card">
                        <div class="stat-label">Orders</div>
                        <div class="stat-number"><?php echo number_format($stats['total_orders']); ?></div>
                        <div class="stat-sub">
                            <i class="fas fa-clock text-warning"></i> <?php echo number_format($stats['pending_orders']); ?> pending
                        </div>
                        <div class="stat-icon"><i class="fas fa-shopping-cart"></i></div>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="stat-card">
                        <div class="stat-label">Revenue</div>
                        <div class="stat-number text-success"><?php echo format_price($stats['total_revenue']); ?></div>
                        <div class="stat-sub">
                            <i class="fas fa-calendar-alt"></i> Lifetime earnings
                        </div>
                        <div class="stat-icon"><i class="fas fa-money-bill-wave"></i></div>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="stat-card">
                        <div class="stat-label">Rating</div>
                        <div class="stat-number text-warning"><?php echo number_format($stats['avg_rating'], 1); ?></div>
                        <div class="stat-sub">
                            <i class="fas fa-star"></i> <?php echo number_format($stats['total_reviews']); ?> reviews
                        </div>
                        <div class="stat-icon"><i class="fas fa-star"></i></div>
                    </div>
                </div>
            </div>
            
            <!-- Quick Actions -->
            <div class="row g-3 mb-4">
                <div class="col-md-3 col-6">
                    <a href="products/add-product.php" class="quick-action">
                        <i class="fas fa-plus-circle text-primary"></i>
                        <small>Add Product</small>
                    </a>
                </div>
                <div class="col-md-3 col-6">
                    <a href="products/my-products.php" class="quick-action">
                        <i class="fas fa-box text-success"></i>
                        <small>My Products</small>
                    </a>
                </div>
                <div class="col-md-3 col-6">
                    <a href="orders/supplier-orders.php" class="quick-action">
                        <i class="fas fa-shopping-cart text-warning"></i>
                        <small>View Orders</small>
                    </a>
                </div>
                <div class="col-md-3 col-6">
                    <a href="earnings/earnings.php" class="quick-action">
                        <i class="fas fa-money-bill-wave text-info"></i>
                        <small>My Earnings</small>
                    </a>
                </div>
            </div>
            
            <!-- Recent Orders & Products -->
            <div class="row g-4">
                
                <!-- Recent Orders -->
                <div class="col-lg-6">
                    <div class="data-card">
                        <div class="card-title">
                            <span><i class="fas fa-history"></i> Recent Orders</span>
                            <a href="orders/supplier-orders.php" class="btn btn-sm btn-success">View All</a>
                        </div>
                        <?php if (!empty($recent_orders)): ?>
                            <?php foreach($recent_orders as $order): ?>
                            <div class="order-item">
                                <div class="order-info">
                                    <div class="d-flex justify-content-between">
                                        <span class="fw-bold">
                                            <a href="orders/order-details.php?id=<?php echo $order['id']; ?>" class="text-dark text-decoration-none">
                                                <?php echo htmlspecialchars($order['order_number']); ?>
                                            </a>
                                        </span>
                                        <span class="text-success"><?php echo format_price($order['total_amount_tsh'] ?? $order['total_amount'] ?? 0); ?></span>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <small class="text-muted">
                                            <i class="fas fa-user me-1"></i><?php echo htmlspecialchars($order['customer_name']); ?>
                                            <span class="mx-2">|</span>
                                            <i class="fas fa-calendar-alt me-1"></i><?php echo date('M d, Y', strtotime($order['created_at'])); ?>
                                        </small>
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
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="text-center py-3">
                                <i class="fas fa-shopping-cart fa-3x text-muted mb-2"></i>
                                <p class="text-muted">No orders yet.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Recent Products -->
                <div class="col-lg-6">
                    <div class="data-card">
                        <div class="card-title">
                            <span><i class="fas fa-box"></i> Recent Products</span>
                            <a href="products/my-products.php" class="btn btn-sm btn-outline-success">View All</a>
                        </div>
                        <?php if (!empty($recent_products)): ?>
                            <?php foreach($recent_products as $product): ?>
                            <div class="product-mini">
                                <img src="<?php 
                                    if (!empty($product['primary_image'])) {
                                        if (strpos($product['primary_image'], 'uploads/') === 0) {
                                            echo '../' . $product['primary_image'];
                                        } else {
                                            echo '../uploads/products/' . $product['id'] . '/' . $product['primary_image'];
                                        }
                                    } else {
                                        echo '../assets/images/placeholder-product.jpg';
                                    }
                                ?>" alt="<?php echo htmlspecialchars($product['product_name']); ?>">
                                <div class="flex-grow-1">
                                    <div class="d-flex justify-content-between">
                                        <span class="fw-bold"><?php echo truncate_text($product['product_name'], 30); ?></span>
                                        <span class="text-success"><?php echo format_price($product['price_tsh']); ?></span>
                                    </div>
                                    <div class="d-flex justify-content-between">
                                        <small class="text-muted">
                                            <i class="fas fa-tag me-1"></i><?php echo htmlspecialchars($product['category_name'] ?? 'Uncategorized'); ?>
                                        </small>
                                        <?php
                                        $stock = $product['stock_quantity'] ?? 0;
                                        if($stock > 10):
                                        ?>
                                            <span class="badge bg-success">In Stock</span>
                                        <?php elseif($stock > 0): ?>
                                            <span class="badge bg-warning">Low Stock</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">Out of Stock</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="text-center py-3">
                                <i class="fas fa-box-open fa-3x text-muted mb-2"></i>
                                <p class="text-muted">No products added yet.</p>
                                <a href="products/add-product.php" class="btn btn-success btn-sm">
                                    <i class="fas fa-plus me-1"></i> Add Product
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Notifications & Reviews -->
            <div class="row g-4 mt-2">
                
                <!-- Notifications -->
                <div class="col-lg-6">
                    <div class="data-card">
                        <div class="card-title">
                            <span><i class="fas fa-bell text-warning"></i> Notifications</span>
                            <a href="notifications.php" class="btn btn-sm btn-outline-secondary">View All</a>
                        </div>
                        <?php if (!empty($notifications)): ?>
                            <?php foreach($notifications as $notification): ?>
                            <div class="notification-item <?php echo $notification['is_read'] ? '' : 'unread'; ?>">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <div class="notification-title">
                                            <?php if(!$notification['is_read']): ?>
                                                <span class="badge bg-primary me-1">New</span>
                                            <?php endif; ?>
                                            <?php echo htmlspecialchars($notification['title']); ?>
                                        </div>
                                        <div class="notification-message"><?php echo htmlspecialchars($notification['message']); ?></div>
                                        <div class="notification-time"><?php echo time_ago($notification['created_at']); ?></div>
                                    </div>
                                    <?php if(!$notification['is_read']): ?>
                                        <button onclick="markNotificationRead(<?php echo $notification['id']; ?>)" class="btn btn-sm btn-link text-primary">
                                            <i class="fas fa-check"></i>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="text-center py-3">
                                <i class="fas fa-bell fa-3x text-muted mb-2"></i>
                                <p class="text-muted">No notifications.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Latest Reviews -->
                <div class="col-lg-6">
                    <div class="data-card">
                        <div class="card-title">
                            <span><i class="fas fa-star text-warning"></i> Latest Reviews</span>
                            <a href="reviews.php" class="btn btn-sm btn-outline-secondary">View All</a>
                        </div>
                        <?php if (!empty($latest_reviews)): ?>
                            <?php foreach($latest_reviews as $review): ?>
                            <div class="review-item">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <span class="fw-bold"><?php echo htmlspecialchars($review['customer_name']); ?></span>
                                        <div class="rating-stars">
                                            <?php for($i = 1; $i <= 5; $i++): ?>
                                                <i class="fas fa-star <?php echo $i <= $review['rating'] ? 'text-warning' : 'text-secondary'; ?>"></i>
                                            <?php endfor; ?>
                                        </div>
                                        <?php if($review['comment']): ?>
                                            <p class="small mb-0 text-muted"><?php echo truncate_text($review['comment'], 80); ?></p>
                                        <?php endif; ?>
                                    </div>
                                    <small class="text-muted"><?php echo time_ago($review['created_at']); ?></small>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="text-center py-3">
                                <i class="fas fa-star fa-3x text-muted mb-2"></i>
                                <p class="text-muted">No reviews yet.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Quick Stats -->
            <div class="row g-4 mt-2">
                <div class="col-md-12">
                    <div class="data-card">
                        <div class="card-title">
                            <span><i class="fas fa-chart-bar text-primary"></i> Quick Stats</span>
                        </div>
                        <div class="row text-center">
                            <div class="col-3">
                                <div class="fw-bold text-success"><?php echo format_price($stats['total_revenue']); ?></div>
                                <small class="text-muted">Total Revenue</small>
                            </div>
                            <div class="col-3">
                                <div class="fw-bold text-primary"><?php echo number_format($stats['total_orders']); ?></div>
                                <small class="text-muted">Total Orders</small>
                            </div>
                            <div class="col-3">
                                <div class="fw-bold text-warning"><?php echo number_format($stats['total_reviews']); ?></div>
                                <small class="text-muted">Total Reviews</small>
                            </div>
                            <div class="col-3">
                                <div class="fw-bold text-danger"><?php echo number_format($stats['pending_orders']); ?></div>
                                <small class="text-muted">Pending Orders</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Help Section -->
            <div class="alert alert-success d-flex align-items-center mt-2">
                <i class="fas fa-headset fa-2x me-3"></i>
                <div>
                    <h6 class="mb-0">Need Help with your supplier account?</h6>
                    <p class="mb-0 small">Contact our support team for assistance with your products, orders, or account.</p>
                </div>
                <a href="../contact.php" class="btn btn-success ms-auto">Contact Support</a>
            </div>
            
            <?php endif; ?>
            
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
                <h6>Supplier</h6>
                <ul class="list-unstyled">
                    <li><a href="dashboard.php">Dashboard</a></li>
                    <li><a href="products/my-products.php">My Products</a></li>
                    <li><a href="orders/supplier-orders.php">Orders</a></li>
                    <li><a href="earnings/earnings.php">Earnings</a></li>
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