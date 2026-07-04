<?php
/**
 * TechProcure Tanzania - Customer Dashboard
 * File: customer/dashboard.php
 * Description: Customer account dashboard with order history, stats, and account management
 */

// Start session if not started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include configuration files
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

// Check if user is logged in
if (!isLoggedIn()) {
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    header("Location: ../auth/login.php");
    exit();
}

// Check if user is customer (not admin or supplier)
if (!isCustomer()) {
    header("Location: ../index.php?error=access_denied");
    exit();
}

// Get database connection
try {
    $db = getDB();
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'] ?? 'Customer';
$user_email = $_SESSION['user_email'] ?? '';

// =============================================
// GET USER DATA
// =============================================

$user = getCurrentUser();

// =============================================
// GET STATISTICS
// =============================================

// Total orders
try {
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM orders WHERE customer_id = ?");
    $stmt->execute([$user_id]);
    $total_orders = $stmt->fetchColumn();
} catch (PDOException $e) {
    $total_orders = 0;
}

// Pending orders
try {
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM orders WHERE customer_id = ? AND status = 'pending'");
    $stmt->execute([$user_id]);
    $pending_orders = $stmt->fetchColumn();
} catch (PDOException $e) {
    $pending_orders = 0;
}

// Processing orders
try {
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM orders WHERE customer_id = ? AND status IN ('processing', 'paid', 'shipped')");
    $stmt->execute([$user_id]);
    $processing_orders = $stmt->fetchColumn();
} catch (PDOException $e) {
    $processing_orders = 0;
}

// Completed orders
try {
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM orders WHERE customer_id = ? AND status IN ('delivered', 'completed')");
    $stmt->execute([$user_id]);
    $completed_orders = $stmt->fetchColumn();
} catch (PDOException $e) {
    $completed_orders = 0;
}

// Total spent
try {
    $stmt = $db->prepare("SELECT SUM(total_amount) as total FROM orders WHERE customer_id = ? AND payment_status = 'paid'");
    $stmt->execute([$user_id]);
    $total_spent = $stmt->fetchColumn() ?? 0;
} catch (PDOException $e) {
    $total_spent = 0;
}

// Wishlist count
try {
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM wishlist WHERE customer_id = ?");
    $stmt->execute([$user_id]);
    $wishlist_count = $stmt->fetchColumn();
} catch (PDOException $e) {
    $wishlist_count = 0;
}

// Cart count
$cart_count = getCartCount();

// =============================================
// GET RECENT ORDERS
// =============================================

$recent_orders = [];
try {
    $stmt = $db->prepare("
        SELECT o.*, 
               (SELECT COUNT(*) FROM order_items WHERE order_id = o.id) as item_count
        FROM orders o 
        WHERE o.customer_id = ? 
        ORDER BY o.created_at DESC 
        LIMIT 5
    ");
    $stmt->execute([$user_id]);
    $recent_orders = $stmt->fetchAll();
} catch (PDOException $e) {
    $recent_orders = [];
}

// =============================================
// GET RECENT WISHLIST ITEMS
// =============================================

$wishlist_items = [];
try {
    // Check if wishlist table exists
    $tables = $db->query("SHOW TABLES LIKE 'wishlist'");
    if ($tables->rowCount() > 0) {
        $stmt = $db->prepare("
            SELECT w.*, p.name as product_name, p.price, p.slug,
                   (SELECT image_url FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) as primary_image
            FROM wishlist w
            JOIN products p ON w.product_id = p.id
            WHERE w.customer_id = ? AND p.status = 'active'
            ORDER BY w.created_at DESC
            LIMIT 4
        ");
        $stmt->execute([$user_id]);
        $wishlist_items = $stmt->fetchAll();
    }
} catch (PDOException $e) {
    $wishlist_items = [];
}

// =============================================
// HELPER FUNCTIONS (if not in functions.php)
// =============================================

if (!function_exists('formatPrice')) {
    function formatPrice($price) {
        if (empty($price) && $price !== 0) return 'TSh 0.00';
        return 'TSh ' . number_format((float)$price, 2);
    }
}

if (!function_exists('formatDate')) {
    function formatDate($date) {
        if (empty($date)) return '-';
        return date('M d, Y', strtotime($date));
    }
}

if (!function_exists('getStatusBadge')) {
    function getStatusBadge($status) {
        $colors = [
            'pending' => 'warning',
            'processing' => 'info',
            'paid' => 'primary',
            'shipped' => 'primary',
            'delivered' => 'success',
            'completed' => 'success',
            'cancelled' => 'danger',
            'refunded' => 'secondary'
        ];
        return $colors[$status] ?? 'secondary';
    }
}

if (!function_exists('getStatusLabel')) {
    function getStatusLabel($status) {
        $labels = [
            'pending' => 'Pending',
            'processing' => 'Processing',
            'paid' => 'Paid',
            'shipped' => 'Shipped',
            'delivered' => 'Delivered',
            'completed' => 'Completed',
            'cancelled' => 'Cancelled',
            'refunded' => 'Refunded'
        ];
        return $labels[$status] ?? ucfirst($status);
    }
}

// Get user avatar initial
$avatarInitial = strtoupper(substr($user_name, 0, 1));

// Calculate order status counts for sidebar badge
$status_counts = [
    'pending' => $pending_orders,
    'processing' => $processing_orders,
    'completed' => $completed_orders
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Dashboard - TechProcure Tanzania</title>
    
    <!-- CSS Dependencies -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary-color: #0d6efd;
            --primary-dark: #0b5ed7;
            --sidebar-width: 270px;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f0f2f5;
        }
        
        /* Navbar */
        .navbar-custom {
            background: linear-gradient(135deg, #1a2a6c, #b21f1f, #fdbb4d);
            padding: 12px 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .navbar-custom .navbar-brand {
            color: white !important;
            font-weight: 800;
            font-size: 1.4rem;
        }
        
        .navbar-custom .navbar-brand i {
            margin-right: 8px;
        }
        
        .navbar-custom .nav-link {
            color: rgba(255,255,255,0.9) !important;
            font-weight: 500;
            transition: all 0.3s;
        }
        
        .navbar-custom .nav-link:hover {
            color: white !important;
            transform: translateY(-1px);
        }
        
        .navbar-custom .cart-badge {
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
            margin-top: 25px;
            min-height: calc(100vh - 180px);
            gap: 24px;
        }
        
        /* Sidebar */
        .dashboard-sidebar {
            width: var(--sidebar-width);
            background: white;
            border-radius: 16px;
            padding: 20px 0;
            box-shadow: 0 2px 12px rgba(0,0,0,0.06);
            flex-shrink: 0;
            height: fit-content;
            position: sticky;
            top: 25px;
        }
        
        .dashboard-sidebar .user-avatar {
            text-align: center;
            padding: 20px 20px 25px;
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
            font-size: 2.2rem;
            font-weight: 700;
            margin: 0 auto 12px;
            box-shadow: 0 4px 15px rgba(13,110,253,0.3);
        }
        
        .dashboard-sidebar .user-avatar h6 {
            margin-bottom: 2px;
            font-weight: 600;
            font-size: 1rem;
        }
        
        .dashboard-sidebar .user-avatar small {
            color: #6c757d;
            font-size: 0.8rem;
        }
        
        .dashboard-sidebar .user-avatar .badge-role {
            display: inline-block;
            margin-top: 8px;
            padding: 3px 14px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
            background: #e3f0ff;
            color: #0d6efd;
        }
        
        .dashboard-sidebar .nav-link {
            color: #495057 !important;
            padding: 11px 25px;
            border-radius: 0;
            border-left: 3px solid transparent;
            transition: all 0.3s;
            font-weight: 500;
            font-size: 0.9rem;
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
            font-size: 1rem;
        }
        
        .dashboard-sidebar .nav-link .badge-count {
            float: right;
            margin-top: 2px;
            font-size: 0.7rem;
            padding: 3px 8px;
        }
        
        .dashboard-sidebar .nav-link.text-danger:hover {
            color: #dc3545 !important;
            background: #fff5f5;
        }
        
        /* Main Content */
        .dashboard-content {
            flex: 1;
            min-width: 0;
        }
        
        /* Welcome Section */
        .welcome-section {
            background: white;
            border-radius: 16px;
            padding: 25px 30px;
            margin-bottom: 24px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.06);
        }
        
        .welcome-section h4 {
            font-weight: 700;
            margin-bottom: 4px;
        }
        
        .welcome-section .text-muted {
            margin-bottom: 0;
        }
        
        /* Stats Cards */
        .stat-card {
            background: white;
            border-radius: 16px;
            padding: 22px 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.06);
            transition: all 0.3s;
            position: relative;
            overflow: hidden;
            height: 100%;
        }
        
        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 30px rgba(0,0,0,0.1);
        }
        
        .stat-card .stat-icon {
            font-size: 2.2rem;
            opacity: 0.12;
            position: absolute;
            right: 15px;
            bottom: 15px;
        }
        
        .stat-card .stat-label {
            font-size: 0.75rem;
            color: #6c757d;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .stat-card .stat-number {
            font-size: 1.8rem;
            font-weight: 700;
            margin: 4px 0 2px;
        }
        
        .stat-card .stat-sub {
            font-size: 0.75rem;
            color: #6c757d;
        }
        
        .stat-card .stat-sub i {
            font-size: 0.7rem;
        }
        
        /* Quick Actions */
        .quick-action-btn {
            padding: 18px 15px;
            border-radius: 14px;
            text-align: center;
            transition: all 0.3s;
            background: white;
            border: 1px solid #e9ecef;
            text-decoration: none;
            color: #333;
            display: block;
            height: 100%;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        }
        
        .quick-action-btn:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
            border-color: var(--primary-color);
            color: var(--primary-color);
            text-decoration: none;
        }
        
        .quick-action-btn i {
            font-size: 1.8rem;
            display: block;
            margin-bottom: 8px;
        }
        
        .quick-action-btn small {
            font-weight: 500;
            font-size: 0.8rem;
        }
        
        /* Data Card */
        .data-card {
            background: white;
            border-radius: 16px;
            padding: 22px 24px;
            margin-bottom: 24px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.06);
        }
        
        .data-card .card-title {
            font-size: 1rem;
            font-weight: 600;
            margin-bottom: 0;
        }
        
        .data-card .card-title i {
            margin-right: 8px;
            color: var(--primary-color);
        }
        
        /* Wishlist Item */
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
        
        .wishlist-item .wishlist-img {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 10px;
            background: #f8f9fa;
            border: 1px solid #e9ecef;
        }
        
        .wishlist-item .wishlist-name {
            font-weight: 500;
            font-size: 0.9rem;
            margin-bottom: 2px;
        }
        
        .wishlist-item .wishlist-price {
            font-weight: 600;
            color: var(--primary-color);
            font-size: 0.9rem;
        }
        
        .wishlist-item .btn-group {
            display: flex;
            gap: 6px;
        }
        
        /* Help Alert */
        .help-alert {
            border-radius: 16px;
            border: none;
            background: linear-gradient(135deg, #e3f0ff, #f0f7ff);
            padding: 20px 25px;
        }
        
        .help-alert i {
            font-size: 2rem;
            color: var(--primary-color);
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
            transition: color 0.3s;
        }
        
        .footer a:hover {
            color: white;
        }
        
        .footer h5, .footer h6 {
            font-weight: 600;
        }
        
        .footer hr {
            border-color: rgba(255,255,255,0.08);
        }
        
        /* Toast */
        .toast-container {
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 9999;
        }
        
        /* Responsive */
        @media (max-width: 992px) {
            .dashboard-sidebar {
                width: 230px;
            }
        }
        
        @media (max-width: 768px) {
            .dashboard-wrapper {
                flex-direction: column;
            }
            
            .dashboard-sidebar {
                width: 100%;
                position: relative;
                top: 0;
            }
            
            .stat-card .stat-number {
                font-size: 1.4rem;
            }
            
            .welcome-section {
                padding: 20px;
            }
            
            .data-card {
                padding: 16px;
            }
            
            .wishlist-item {
                flex-wrap: wrap;
            }
        }
        
        @media (max-width: 576px) {
            .stat-card {
                padding: 16px;
            }
            
            .stat-card .stat-number {
                font-size: 1.2rem;
            }
            
            .quick-action-btn {
                padding: 12px 10px;
            }
            
            .quick-action-btn i {
                font-size: 1.4rem;
            }
        }
    </style>
</head>
<body>

<!-- Navbar -->
<nav class="navbar navbar-expand-lg navbar-custom">
    <div class="container">
        <a class="navbar-brand" href="../index.php">
            <i class="fas fa-microchip"></i>
            TechProcure <span style="color: #fdbb4d;">Tanzania</span>
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
                    <span class="cart-badge"><?php echo $cart_count; ?></span>
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
                    <?php echo $avatarInitial; ?>
                </div>
                <h6><?php echo htmlspecialchars($user_name); ?></h6>
                <small><?php echo htmlspecialchars($user_email); ?></small>
                <div>
                    <span class="badge-role"><i class="fas fa-user me-1"></i>Customer</span>
                </div>
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
                        <?php if($pending_orders > 0): ?>
                            <span class="badge bg-warning badge-count"><?php echo $pending_orders; ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="wishlist.php">
                        <i class="fas fa-heart"></i> Wishlist
                        <?php if($wishlist_count > 0): ?>
                            <span class="badge bg-danger badge-count"><?php echo $wishlist_count; ?></span>
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
        
        <!-- Main Content -->
        <div class="dashboard-content">
            <!-- Welcome -->
            <div class="welcome-section">
                <div class="d-flex justify-content-between align-items-center flex-wrap">
                    <div>
                        <h4>Welcome back, <?php echo htmlspecialchars($user_name); ?>! 👋</h4>
                        <p class="text-muted">Here's what's happening with your account</p>
                    </div>
                    <div>
                        <span class="badge bg-primary"><i class="fas fa-user me-1"></i>Customer</span>
                    </div>
                </div>
            </div>
            
            <!-- Stats Row -->
            <div class="row">
                <div class="col-xl-3 col-lg-6 col-md-6 col-6">
                    <div class="stat-card">
                        <div class="stat-label">Total Orders</div>
                        <div class="stat-number"><?php echo number_format($total_orders); ?></div>
                        <div class="stat-sub">
                            <i class="fas fa-check-circle text-success"></i> <?php echo number_format($completed_orders); ?> completed
                        </div>
                        <div class="stat-icon"><i class="fas fa-shopping-cart"></i></div>
                    </div>
                </div>
                <div class="col-xl-3 col-lg-6 col-md-6 col-6">
                    <div class="stat-card">
                        <div class="stat-label">Pending Orders</div>
                        <div class="stat-number text-warning"><?php echo number_format($pending_orders); ?></div>
                        <div class="stat-sub">
                            <i class="fas fa-clock"></i> Awaiting processing
                        </div>
                        <div class="stat-icon"><i class="fas fa-clock"></i></div>
                    </div>
                </div>
                <div class="col-xl-3 col-lg-6 col-md-6 col-6">
                    <div class="stat-card">
                        <div class="stat-label">Total Spent</div>
                        <div class="stat-number text-primary"><?php echo formatPrice($total_spent); ?></div>
                        <div class="stat-sub">
                            <i class="fas fa-calendar-alt"></i> Lifetime purchases
                        </div>
                        <div class="stat-icon"><i class="fas fa-money-bill-wave"></i></div>
                    </div>
                </div>
                <div class="col-xl-3 col-lg-6 col-md-6 col-6">
                    <div class="stat-card">
                        <div class="stat-label">Wishlist</div>
                        <div class="stat-number text-danger"><?php echo number_format($wishlist_count); ?></div>
                        <div class="stat-sub">
                            <i class="fas fa-heart"></i> Saved items
                        </div>
                        <div class="stat-icon"><i class="fas fa-heart"></i></div>
                    </div>
                </div>
            </div>
            
            <!-- Quick Actions -->
            <div class="row g-3 mb-4">
                <div class="col-xl-3 col-lg-6 col-6">
                    <a href="../products.php" class="quick-action-btn">
                        <i class="fas fa-search text-primary"></i>
                        <small>Browse Products</small>
                    </a>
                </div>
                <div class="col-xl-3 col-lg-6 col-6">
                    <a href="orders/my-orders.php" class="quick-action-btn">
                        <i class="fas fa-shopping-bag text-success"></i>
                        <small>My Orders</small>
                    </a>
                </div>
                <div class="col-xl-3 col-lg-6 col-6">
                    <a href="wishlist.php" class="quick-action-btn">
                        <i class="fas fa-heart text-danger"></i>
                        <small>Wishlist</small>
                    </a>
                </div>
                <div class="col-xl-3 col-lg-6 col-6">
                    <a href="profile.php" class="quick-action-btn">
                        <i class="fas fa-user text-info"></i>
                        <small>My Profile</small>
                    </a>
                </div>
            </div>
            
            <!-- Recent Orders -->
            <div class="data-card">
                <div class="d-flex justify-content-between align-items-center">
                    <div class="card-title">
                        <i class="fas fa-history"></i> Recent Orders
                    </div>
                    <a href="orders/my-orders.php" class="btn btn-sm btn-primary">View All <i class="fas fa-arrow-right ms-1"></i></a>
                </div>
                <div class="table-responsive mt-3">
                    <?php if (!empty($recent_orders)): ?>
                        <table class="table table-hover align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Order #</th>
                                    <th>Date</th>
                                    <th>Items</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($recent_orders as $order): ?>
                                <tr>
                                    <td><code><?php echo htmlspecialchars($order['order_number'] ?? 'ORD-' . $order['id']); ?></code></td>
                                    <td><?php echo formatDate($order['created_at']); ?></td>
                                    <td><?php echo $order['item_count'] ?? 0; ?></td>
                                    <td><strong><?php echo formatPrice($order['total_amount']); ?></strong></td>
                                    <td>
                                        <?php $badge = getStatusBadge($order['status']); ?>
                                        <span class="badge bg-<?php echo $badge; ?>">
                                            <?php echo getStatusLabel($order['status']); ?>
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
                        <div class="text-center py-5">
                            <i class="fas fa-shopping-bag fa-4x text-muted mb-3 d-block"></i>
                            <h5 class="text-muted">No orders yet</h5>
                            <p class="text-muted">Start shopping to see your orders here.</p>
                            <a href="../products.php" class="btn btn-primary">Start Shopping</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Wishlist -->
            <?php if (!empty($wishlist_items)): ?>
            <div class="data-card">
                <div class="d-flex justify-content-between align-items-center">
                    <div class="card-title">
                        <i class="fas fa-heart text-danger"></i> Recently Added to Wishlist
                    </div>
                    <a href="wishlist.php" class="btn btn-sm btn-outline-danger">View All <i class="fas fa-arrow-right ms-1"></i></a>
                </div>
                <div class="mt-2">
                    <?php foreach($wishlist_items as $item): ?>
                    <div class="wishlist-item">
                        <img class="wishlist-img" 
                             src="<?php echo !empty($item['primary_image']) ? '../uploads/products/' . $item['product_id'] . '/' . $item['primary_image'] : '../assets/images/placeholder-product.jpg'; ?>" 
                             alt="<?php echo htmlspecialchars($item['product_name']); ?>">
                        <div class="flex-grow-1">
                            <div class="wishlist-name"><?php echo htmlspecialchars($item['product_name']); ?></div>
                            <div class="wishlist-price"><?php echo formatPrice($item['price']); ?></div>
                        </div>
                        <div class="btn-group">
                            <a href="../product-details.php?id=<?php echo $item['product_id']; ?>" class="btn btn-sm btn-primary">
                                <i class="fas fa-eye"></i>
                            </a>
                            <button onclick="removeFromWishlist(<?php echo $item['id']; ?>)" class="btn btn-sm btn-outline-danger">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Need Help -->
            <div class="help-alert alert">
                <div class="d-flex align-items-center flex-wrap gap-3">
                    <i class="fas fa-headset"></i>
                    <div class="flex-grow-1">
                        <h6 class="mb-0 fw-bold">Need Help?</h6>
                        <p class="mb-0 small">Contact our support team for assistance with your orders or account.</p>
                    </div>
                    <a href="../contact.php" class="btn btn-primary btn-sm">Contact Support</a>
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
                <p class="text-muted" style="color: rgba(255,255,255,0.6) !important;">Enterprise B2B IT equipment procurement platform with transparent pricing and bulk discounts for corporate buyers across Tanzania.</p>
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
        <hr>
        <div class="text-center">
            <small style="color: rgba(255,255,255,0.5);">&copy; <?php echo date('Y'); ?> TechProcure Tanzania. All rights reserved.</small>
        </div>
    </div>
</footer>

<!-- Toast Container -->
<div class="toast-container"></div>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // =============================================
    // SHOW TOAST NOTIFICATION
    // =============================================
    function showToast(title, message, type) {
        const toastContainer = document.querySelector('.toast-container');
        if (!toastContainer) {
            const container = document.createElement('div');
            container.className = 'toast-container position-fixed bottom-0 end-0 p-3';
            container.style.zIndex = '9999';
            document.body.appendChild(container);
        }
        
        const toast = document.createElement('div');
        toast.className = `toast show bg-${type === 'success' ? 'success' : 'danger'} text-white`;
        toast.setAttribute('role', 'alert');
        toast.innerHTML = `
            <div class="toast-header bg-${type === 'success' ? 'success' : 'danger'} text-white">
                <strong class="me-auto">${title}</strong>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast"></button>
            </div>
            <div class="toast-body">${message}</div>
        `;
        document.querySelector('.toast-container').appendChild(toast);
        
        setTimeout(() => {
            toast.classList.remove('show');
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    }
    
    // =============================================
    // REMOVE FROM WISHLIST
    // =============================================
    function removeFromWishlist(wishlistId) {
        if (confirm('Remove this item from your wishlist?')) {
            fetch('ajax/remove-wishlist.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'id=' + wishlistId
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast('Success', 'Item removed from wishlist', 'success');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showToast('Error', data.message || 'Error removing item', 'error');
                }
            })
            .catch(() => location.reload());
        }
    }
</script>
</body>
</html>