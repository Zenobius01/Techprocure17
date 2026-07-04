<?php
/**
 * TechProcure Tanzania - Order Details Page
 * File: customer/orders/order-details.php
 * Description: View detailed order information with items, status, and tracking
 */

// Start session if not started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include configuration files
require_once '../../includes/config.php';
require_once '../../includes/db.php';
require_once '../../includes/functions.php';


// Check if user is logged in and is customer
requireCustomer();

// Get database connection
$db = getDB();
$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'] ?? 'Customer';

// Get order ID from URL
$order_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($order_id <= 0) {
    $_SESSION['error'] = "Invalid order ID.";
    header("Location: my-orders.php");
    exit();
}

// =============================================
// FETCH ORDER DETAILS
// =============================================

$order = null;
$order_items = [];
$order_tracking = [];
$error = '';

try {
    // Get order details
    $sql = "SELECT o.*, 
                   u.full_name as customer_name, u.email as customer_email, u.phone as customer_phone,
                   s.company_name as supplier_name, s.phone as supplier_phone,
                   (SELECT COUNT(*) FROM order_items WHERE order_id = o.id) as item_count
            FROM orders o
            LEFT JOIN users u ON o.user_id = u.id
            LEFT JOIN suppliers s ON o.supplier_id = s.id
            WHERE o.id = ? AND o.user_id = ?";
    $stmt = $db->prepare($sql);
    $stmt->execute([$order_id, $user_id]);
    $order = $stmt->fetch();

    if (!$order) {
        $error = "Order not found or you don't have permission to view it.";
    }
} catch (PDOException $e) {
    $error = "Failed to load order details.";
}

// =============================================
// FETCH ORDER ITEMS
// =============================================

if ($order) {
    try {
        $sql = "SELECT oi.*, p.product_name, p.sku, p.brand,
                       (SELECT image_path FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) as primary_image
                FROM order_items oi
                JOIN products p ON oi.product_id = p.id
                WHERE oi.order_id = ?
                ORDER BY oi.id ASC";
        $stmt = $db->prepare($sql);
        $stmt->execute([$order_id]);
        $order_items = $stmt->fetchAll();
    } catch (PDOException $e) {
        $order_items = [];
    }
}

// =============================================
// FETCH ORDER TRACKING
// =============================================

if ($order) {
    try {
        $sql = "SELECT * FROM order_tracking WHERE order_id = ? ORDER BY created_at ASC";
        $stmt = $db->prepare($sql);
        $stmt->execute([$order_id]);
        $order_tracking = $stmt->fetchAll();
    } catch (PDOException $e) {
        $order_tracking = [];
    }
}

// =============================================
// FETCH ESCROW PAYMENT
// =============================================

$escrow = null;
if ($order) {
    try {
        $sql = "SELECT * FROM escrow_payments WHERE order_id = ?";
        $stmt = $db->prepare($sql);
        $stmt->execute([$order_id]);
        $escrow = $stmt->fetch();
    } catch (PDOException $e) {
        $escrow = null;
    }
}

// =============================================
// HANDLE ORDER ACTIONS
// =============================================

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action == 'cancel_order') {
        try {
            $stmt = $db->prepare("UPDATE orders SET order_status = 'cancelled', cancelled_at = NOW() WHERE id = ? AND user_id = ? AND order_status = 'pending'");
            $stmt->execute([$order_id, $user_id]);
            
            if ($stmt->rowCount() > 0) {
                // Add tracking
                $tracking_sql = "INSERT INTO order_tracking (order_id, status, description, created_at) VALUES (?, 'cancelled', 'Order cancelled by customer', NOW())";
                $tracking_stmt = $db->prepare($tracking_sql);
                $tracking_stmt->execute([$order_id]);
                
                // Update escrow
                if ($escrow) {
                    $escrow_stmt = $db->prepare("UPDATE escrow_payments SET status = 'refunded', refund_date = NOW() WHERE id = ?");
                    $escrow_stmt->execute([$escrow['id']]);
                }
                
                $_SESSION['success'] = "Order cancelled successfully!";
                header("Location: order-details.php?id=" . $order_id);
                exit();
            } else {
                $_SESSION['error'] = "Failed to cancel order. It may already be processed.";
            }
        } catch (PDOException $e) {
            $_SESSION['error'] = "Failed to cancel order.";
        }
        header("Location: order-details.php?id=" . $order_id);
        exit();
    }
}

// =============================================
// GET CART COUNT
// =============================================

$cart_count = function_exists('getCartCount') ? getCartCount() : 0;

// =============================================
// HELPER FUNCTIONS
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

if (!function_exists('formatDateTime')) {
    function formatDateTime($date) {
        if (empty($date)) return '-';
        return date('M d, Y H:i', strtotime($date));
    }
}

if (!function_exists('getStatusBadge')) {
    function getStatusBadge($status) {
        $colors = [
            'pending' => 'warning',
            'processing' => 'info',
            'shipped' => 'primary',
            'delivered' => 'success',
            'completed' => 'success',
            'cancelled' => 'danger'
        ];
        return $colors[$status] ?? 'secondary';
    }
}

if (!function_exists('getStatusIcon')) {
    function getStatusIcon($status) {
        $icons = [
            'pending' => 'fa-clock',
            'processing' => 'fa-spinner',
            'shipped' => 'fa-truck',
            'delivered' => 'fa-check-circle',
            'completed' => 'fa-check-double',
            'cancelled' => 'fa-times-circle'
        ];
        return $icons[$status] ?? 'fa-circle';
    }
}

// =============================================
// FUNCTION TO GET PRODUCT IMAGE PATH
// =============================================
function getProductImagePath($item) {
    if (!empty($item['primary_image'])) {
        // If image path already starts with 'uploads/', use as is
        if (strpos($item['primary_image'], 'uploads/') === 0) {
            return '../../' . $item['primary_image'];
        }
        // If it's just a filename, construct the full path
        return '../../uploads/products/' . $item['product_id'] . '/' . $item['primary_image'];
    }
    // No image - return placeholder
    return '../../assets/images/placeholder-product.jpg';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Details - TechProcure Tanzania</title>
    
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
        
        /* Order Details Content */
        .order-content {
            flex: 1;
        }
        
        .order-details-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 24px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        
        .order-details-card .card-title {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #e9ecef;
        }
        
        .order-details-card .card-title i {
            margin-right: 8px;
            color: #0d6efd;
        }
        
        .order-info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        
        .order-info-item .label {
            font-size: 0.75rem;
            color: #6c757d;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .order-info-item .value {
            font-weight: 500;
            font-size: 1rem;
        }
        
        .status-timeline {
            position: relative;
            padding-left: 30px;
        }
        
        .status-timeline::before {
            content: '';
            position: absolute;
            left: 8px;
            top: 0;
            bottom: 0;
            width: 2px;
            background: #e9ecef;
        }
        
        .status-timeline .timeline-item {
            position: relative;
            padding-bottom: 25px;
        }
        
        .status-timeline .timeline-item:last-child {
            padding-bottom: 0;
        }
        
        .status-timeline .timeline-item .timeline-dot {
            position: absolute;
            left: -22px;
            top: 5px;
            width: 14px;
            height: 14px;
            border-radius: 50%;
            border: 2px solid #e9ecef;
            background: white;
        }
        
        .status-timeline .timeline-item.active .timeline-dot {
            border-color: #0d6efd;
            background: #0d6efd;
        }
        
        .status-timeline .timeline-item.completed .timeline-dot {
            border-color: #198754;
            background: #198754;
        }
        
        .status-timeline .timeline-item .timeline-content {
            padding-left: 10px;
        }
        
        .status-timeline .timeline-item .timeline-content .title {
            font-weight: 500;
        }
        
        .status-timeline .timeline-item .timeline-content .time {
            font-size: 0.75rem;
            color: #6c757d;
        }
        
        .order-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 12px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .order-item:last-child {
            border-bottom: none;
        }
        
        .order-item img {
            width: 60px;
            height: 60px;
            object-fit: cover;
            background: #f8f9fa;
            border-radius: 8px;
            padding: 5px;
        }
        
        .order-item .item-details {
            flex: 1;
        }
        
        .order-item .item-name {
            font-weight: 500;
            margin-bottom: 2px;
        }
        
        .order-item .item-meta {
            font-size: 0.8rem;
            color: #6c757d;
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
            .order-info-grid {
                grid-template-columns: 1fr;
            }
            .order-item {
                flex-wrap: wrap;
            }
            .order-item img {
                width: 50px;
                height: 50px;
            }
        }
    </style>
</head>
<body>

<!-- Navbar -->
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
                    <a class="nav-link" href="../dashboard.php">
                        <i class="fas fa-tachometer-alt"></i> Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="../profile.php">
                        <i class="fas fa-user"></i> My Profile
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="my-orders.php">
                        <i class="fas fa-shopping-bag"></i> My Orders
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="../wishlist.php">
                        <i class="fas fa-heart"></i> Wishlist
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="../quotations/request-quotation.php">
                        <i class="fas fa-file-alt"></i> Quotations
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="../invoices/my-invoices.php">
                        <i class="fas fa-file-invoice"></i> Invoices
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="../settings.php">
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
        
        <!-- Order Details Content -->
        <div class="order-content">
            <?php if($error): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle me-2"></i> <?php echo $error; ?>
                    <a href="my-orders.php" class="btn btn-primary btn-sm mt-2">Back to Orders</a>
                </div>
            <?php elseif($order): ?>
            
            <!-- Page Title -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h4 class="mb-0"><i class="fas fa-shopping-bag me-2 text-primary"></i>Order Details</h4>
                    <p class="text-muted">Order #<?php echo htmlspecialchars($order['order_number']); ?></p>
                </div>
                <div>
                    <?php $badge = getStatusBadge($order['order_status']); ?>
                    <span class="badge bg-<?php echo $badge; ?> fs-6 p-2">
                        <i class="fas <?php echo getStatusIcon($order['order_status']); ?> me-1"></i>
                        <?php echo ucfirst($order['order_status']); ?>
                    </span>
                    <a href="my-orders.php" class="btn btn-secondary btn-sm ms-2">
                        <i class="fas fa-arrow-left me-1"></i> Back
                    </a>
                </div>
            </div>
            
            <!-- Success/Error Messages -->
            <?php if(isset($_SESSION['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <i class="fas fa-check-circle me-2"></i> <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if(isset($_SESSION['error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <i class="fas fa-exclamation-triangle me-2"></i> <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <!-- Order Info -->
            <div class="order-details-card">
                <div class="card-title">
                    <i class="fas fa-info-circle"></i> Order Information
                </div>
                <div class="order-info-grid">
                    <div class="order-info-item">
                        <div class="label">Order Number</div>
                        <div class="value"><code><?php echo htmlspecialchars($order['order_number']); ?></code></div>
                    </div>
                    <div class="order-info-item">
                        <div class="label">Order Date</div>
                        <div class="value"><?php echo formatDateTime($order['created_at']); ?></div>
                    </div>
                    <div class="order-info-item">
                        <div class="label">Order Status</div>
                        <div class="value">
                            <span class="badge bg-<?php echo $badge; ?>">
                                <?php echo ucfirst($order['order_status']); ?>
                            </span>
                        </div>
                    </div>
                    <div class="order-info-item">
                        <div class="label">Payment Status</div>
                        <div class="value">
                            <?php if($order['payment_status'] == 'paid'): ?>
                                <span class="badge bg-success"><i class="fas fa-check-circle me-1"></i> Paid</span>
                            <?php elseif($order['payment_status'] == 'pending'): ?>
                                <span class="badge bg-warning"><i class="fas fa-clock me-1"></i> Pending</span>
                            <?php else: ?>
                                <span class="badge bg-danger"><?php echo ucfirst($order['payment_status']); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php if($order['payment_method']): ?>
                    <div class="order-info-item">
                        <div class="label">Payment Method</div>
                        <div class="value"><?php echo ucfirst(str_replace('_', ' ', $order['payment_method'])); ?></div>
                    </div>
                    <?php endif; ?>
                    <?php if($order['tracking_number']): ?>
                    <div class="order-info-item">
                        <div class="label">Tracking Number</div>
                        <div class="value"><code><?php echo htmlspecialchars($order['tracking_number']); ?></code></div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Order Items -->
            <div class="order-details-card">
                <div class="card-title">
                    <i class="fas fa-box"></i> Order Items (<?php echo count($order_items); ?> items)
                </div>
                
                <?php if(!empty($order_items)): ?>
                    <?php foreach($order_items as $item): ?>
                    <div class="order-item">
                        <img src="<?php echo getProductImagePath($item); ?>" 
                             alt="<?php echo htmlspecialchars($item['product_name']); ?>" 
                             onerror="this.src='../../assets/images/placeholder-product.jpg'">
                        <div class="item-details">
                            <div class="item-name"><?php echo htmlspecialchars($item['product_name']); ?></div>
                            <div class="item-meta">
                                SKU: <?php echo htmlspecialchars($item['sku'] ?? 'N/A'); ?>
                                <?php if($item['brand']): ?>
                                    | Brand: <?php echo htmlspecialchars($item['brand']); ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="text-center">
                            <div class="text-muted small">Qty: <?php echo $item['quantity']; ?></div>
                            <div class="fw-bold"><?php echo formatPrice($item['unit_price']); ?></div>
                        </div>
                        <div class="text-end">
                            <div class="text-muted small">Total</div>
                            <div class="fw-bold text-primary"><?php echo formatPrice($item['total_price']); ?></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    
                    <!-- Order Totals -->
                    <div class="mt-3 pt-3 border-top">
                        <div class="row">
                            <div class="col-md-6 offset-md-6">
                                <div class="d-flex justify-content-between py-1">
                                    <span>Subtotal</span>
                                    <span><?php echo formatPrice($order['subtotal']); ?></span>
                                </div>
                                <?php if($order['discount_amount'] > 0): ?>
                                <div class="d-flex justify-content-between py-1 text-success">
                                    <span>Discount</span>
                                    <span>-<?php echo formatPrice($order['discount_amount']); ?></span>
                                </div>
                                <?php endif; ?>
                                <div class="d-flex justify-content-between py-1">
                                    <span>Shipping</span>
                                    <span><?php echo $order['shipping_cost'] > 0 ? formatPrice($order['shipping_cost']) : 'Free'; ?></span>
                                </div>
                                <div class="d-flex justify-content-between py-1">
                                    <span>Tax (18%)</span>
                                    <span><?php echo formatPrice($order['tax_amount']); ?></span>
                                </div>
                                <div class="d-flex justify-content-between py-2 fw-bold fs-5 border-top">
                                    <span>Total</span>
                                    <span class="text-primary"><?php echo formatPrice($order['total_amount']); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <p class="text-muted">No items found for this order.</p>
                <?php endif; ?>
            </div>
            
            <!-- Shipping & Billing -->
            <div class="row">
                <div class="col-md-6">
                    <div class="order-details-card">
                        <div class="card-title">
                            <i class="fas fa-truck"></i> Shipping Address
                        </div>
                        <?php if($order['shipping_address']): ?>
                            <p class="mb-0"><?php echo nl2br(htmlspecialchars($order['shipping_address'])); ?></p>
                        <?php else: ?>
                            <p class="text-muted">No shipping address provided.</p>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="order-details-card">
                        <div class="card-title">
                            <i class="fas fa-file-invoice"></i> Billing Address
                        </div>
                        <?php if($order['billing_address']): ?>
                            <p class="mb-0"><?php echo nl2br(htmlspecialchars($order['billing_address'])); ?></p>
                        <?php else: ?>
                            <p class="text-muted">Same as shipping address.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Order Notes -->
            <?php if($order['notes']): ?>
            <div class="order-details-card">
                <div class="card-title">
                    <i class="fas fa-sticky-note"></i> Order Notes
                </div>
                <p class="mb-0"><?php echo nl2br(htmlspecialchars($order['notes'])); ?></p>
            </div>
            <?php endif; ?>
            
            <!-- Escrow Information -->
            <?php if($escrow): ?>
            <div class="order-details-card">
                <div class="card-title">
                    <i class="fas fa-lock"></i> Escrow Payment
                </div>
                <div class="order-info-grid">
                    <div class="order-info-item">
                        <div class="label">Amount</div>
                        <div class="value"><?php echo formatPrice($escrow['amount']); ?></div>
                    </div>
                    <div class="order-info-item">
                        <div class="label">Status</div>
                        <div class="value">
                            <?php if($escrow['status'] == 'pending'): ?>
                                <span class="badge bg-warning">Pending Release</span>
                            <?php elseif($escrow['status'] == 'released'): ?>
                                <span class="badge bg-success">Released</span>
                            <?php elseif($escrow['status'] == 'refunded'): ?>
                                <span class="badge bg-danger">Refunded</span>
                            <?php else: ?>
                                <span class="badge bg-secondary"><?php echo ucfirst($escrow['status']); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Order Actions -->
            <div class="order-details-card">
                <div class="card-title">
                    <i class="fas fa-cog"></i> Order Actions
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <?php if($order['order_status'] == 'pending'): ?>
                        <form method="POST" onsubmit="return confirm('Are you sure you want to cancel this order?')">
                            <input type="hidden" name="action" value="cancel_order">
                            <button type="submit" class="btn btn-danger">
                                <i class="fas fa-times me-1"></i> Cancel Order
                            </button>
                        </form>
                    <?php endif; ?>
                    
                    <?php if($order['order_status'] == 'shipped' && $order['tracking_number']): ?>
                        <button onclick="trackOrder('<?php echo $order['tracking_number']; ?>')" class="btn btn-outline-info">
                            <i class="fas fa-map-marker-alt me-1"></i> Track Order
                        </button>
                    <?php endif; ?>
                    
                    <?php if($order['order_status'] == 'delivered' || $order['order_status'] == 'completed'): ?>
                        <a href="#" class="btn btn-outline-warning">
                            <i class="fas fa-star me-1"></i> Write Review
                        </a>
                    <?php endif; ?>
                    
                    <?php if($order['payment_status'] == 'pending'): ?>
                        <a href="../../payment/pay-invoice.php?order=<?php echo $order['id']; ?>" class="btn btn-success">
                            <i class="fas fa-credit-card me-1"></i> Pay Now
                        </a>
                    <?php endif; ?>
                    
                    <a href="my-orders.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left me-1"></i> Back to Orders
                    </a>
                </div>
            </div>
            
            <?php endif; ?>
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
                    <li><a href="../../index.php">Home</a></li>
                    <li><a href="../../products.php">Products</a></li>
                    <li><a href="../../suppliers.php">Suppliers</a></li>
                </ul>
            </div>
            <div class="col-md-3 mb-4">
                <h6>My Account</h6>
                <ul class="list-unstyled">
                    <li><a href="../dashboard.php">Dashboard</a></li>
                    <li><a href="../profile.php">Profile</a></li>
                    <li><a href="my-orders.php">My Orders</a></li>
                    <li><a href="../wishlist.php">Wishlist</a></li>
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
    // Track order
    function trackOrder(trackingNumber) {
        window.open('track-order.php?tracking=' + trackingNumber, '_blank');
    }
    
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