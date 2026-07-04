<?php
/**
 * TechProcure Tanzania - My Orders Page
 * File: customer/orders/my-orders.php
 * Description: Customer order history with filtering and order details
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

// =============================================
// GET FILTER PARAMETERS
// =============================================

$status_filter = isset($_GET['status']) ? trim($_GET['status']) : 'all';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// =============================================
// BUILD ORDER QUERY
// =============================================

$where_conditions = ["o.user_id = ?"];
$params = [$user_id];

// Status filter
if ($status_filter != 'all') {
    $where_conditions[] = "o.order_status = ?";
    $params[] = $status_filter;
}

// Search filter
if (!empty($search)) {
    $where_conditions[] = "(o.order_number LIKE ? OR o.invoice_number LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
}

$where_clause = implode(" AND ", $where_conditions);

// =============================================
// COUNT TOTAL ORDERS
// =============================================

$total_orders = 0;
$total_pages = 1;

try {
    $count_sql = "SELECT COUNT(*) as total FROM orders o WHERE $where_clause";
    $stmt = $db->prepare($count_sql);
    $stmt->execute($params);
    $total_orders = (int)$stmt->fetchColumn();
    $total_pages = ceil($total_orders / $limit);
} catch (PDOException $e) {
    $total_orders = 0;
    $total_pages = 1;
}

// =============================================
// GET ORDERS
// =============================================

$orders = [];
try {
    $sql = "SELECT o.*, 
                   (SELECT COUNT(*) FROM order_items WHERE order_id = o.id) as item_count,
                   (SELECT SUM(quantity) FROM order_items WHERE order_id = o.id) as total_items
            FROM orders o 
            WHERE $where_clause
            ORDER BY o.created_at DESC
            LIMIT ? OFFSET ?";
    
    $params[] = $limit;
    $params[] = $offset;
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    
    if ($stmt->rowCount() > 0) {
        $orders = $stmt->fetchAll();
    }
} catch (PDOException $e) {
    $orders = [];
}

// =============================================
// GET ORDER STATUS COUNTS
// =============================================

$status_counts = [];
$status_list = ['pending', 'processing', 'shipped', 'delivered', 'completed', 'cancelled'];
try {
    foreach ($status_list as $status) {
        $stmt = $db->prepare("SELECT COUNT(*) as total FROM orders WHERE user_id = ? AND order_status = ?");
        $stmt->execute([$user_id, $status]);
        $status_counts[$status] = (int)$stmt->fetchColumn();
    }
    $status_counts['all'] = array_sum($status_counts);
} catch (PDOException $e) {
    $status_counts = array_fill_keys(array_merge(['all'], $status_list), 0);
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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Orders - TechProcure Tanzania</title>
    
    <!-- CSS Dependencies -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    
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
        
        /* Orders Content */
        .orders-content {
            flex: 1;
        }
        
        /* Status Filter Buttons */
        .status-filter {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 20px;
        }
        
        .status-filter .btn {
            border-radius: 50px;
            padding: 6px 18px;
            font-size: 0.85rem;
            font-weight: 500;
            transition: all 0.3s;
        }
        
        .status-filter .btn:hover {
            transform: translateY(-2px);
        }
        
        .status-filter .btn .badge {
            margin-left: 6px;
        }
        
        /* Order Card */
        .order-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            transition: all 0.3s;
            border-left: 4px solid #0d6efd;
        }
        
        .order-card:hover {
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            transform: translateY(-2px);
        }
        
        .order-card .order-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            padding-bottom: 12px;
            border-bottom: 1px solid #e9ecef;
            margin-bottom: 12px;
        }
        
        .order-card .order-number {
            font-weight: 600;
            font-size: 1.1rem;
        }
        
        .order-card .order-number code {
            background: #f8f9fa;
            padding: 4px 10px;
            border-radius: 6px;
        }
        
        .order-card .order-date {
            color: #6c757d;
            font-size: 0.85rem;
        }
        
        .order-card .order-details {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
        }
        
        .order-card .order-details .detail-item {
            flex: 1;
            min-width: 120px;
        }
        
        .order-card .order-details .detail-item .label {
            font-size: 0.75rem;
            color: #6c757d;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .order-card .order-details .detail-item .value {
            font-weight: 500;
        }
        
        .order-card .order-actions {
            margin-top: 12px;
            padding-top: 12px;
            border-top: 1px solid #e9ecef;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        /* Status Timeline */
        .order-timeline {
            display: flex;
            align-items: center;
            gap: 5px;
            margin-top: 8px;
            flex-wrap: wrap;
        }
        
        .order-timeline .step {
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 0.75rem;
            color: #6c757d;
        }
        
        .order-timeline .step.active {
            color: #0d6efd;
            font-weight: 600;
        }
        
        .order-timeline .step.completed {
            color: #198754;
        }
        
        .order-timeline .step-line {
            width: 30px;
            height: 2px;
            background: #dee2e6;
        }
        
        .order-timeline .step-line.active {
            background: #0d6efd;
        }
        
        .order-timeline .step-line.completed {
            background: #198754;
        }
        
        /* No Orders */
        .no-orders {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 15px;
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
            .order-card .order-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 5px;
            }
            .order-card .order-details {
                flex-direction: column;
                gap: 8px;
            }
            .status-filter {
                gap: 5px;
            }
            .status-filter .btn {
                font-size: 0.75rem;
                padding: 4px 12px;
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
                    <a class="nav-link active" href="my-orders.php">
                        <i class="fas fa-shopping-bag"></i> My Orders
                        <?php if($status_counts['pending'] > 0): ?>
                            <span class="badge bg-warning"><?php echo $status_counts['pending']; ?></span>
                        <?php endif; ?>
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
        
        <!-- Orders Content -->
        <div class="orders-content">
            <!-- Page Title -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h4 class="mb-0"><i class="fas fa-shopping-bag me-2 text-primary"></i>My Orders</h4>
                    <p class="text-muted">View and track all your orders</p>
                </div>
                <span class="badge bg-primary"><?php echo number_format($total_orders); ?> Total Orders</span>
            </div>
            
            <!-- Status Filter -->
            <div class="status-filter">
                <a href="?status=all" class="btn <?php echo $status_filter == 'all' ? 'btn-primary' : 'btn-outline-primary'; ?>">
                    All <span class="badge bg-<?php echo $status_filter == 'all' ? 'light text-dark' : 'primary'; ?>"><?php echo $status_counts['all']; ?></span>
                </a>
                <a href="?status=pending" class="btn <?php echo $status_filter == 'pending' ? 'btn-warning' : 'btn-outline-warning'; ?>">
                    <i class="fas fa-clock me-1"></i> Pending
                    <?php if($status_counts['pending'] > 0): ?>
                        <span class="badge bg-<?php echo $status_filter == 'pending' ? 'light text-dark' : 'warning'; ?>"><?php echo $status_counts['pending']; ?></span>
                    <?php endif; ?>
                </a>
                <a href="?status=processing" class="btn <?php echo $status_filter == 'processing' ? 'btn-info' : 'btn-outline-info'; ?>">
                    <i class="fas fa-spinner me-1"></i> Processing
                    <?php if($status_counts['processing'] > 0): ?>
                        <span class="badge bg-<?php echo $status_filter == 'processing' ? 'light text-dark' : 'info'; ?>"><?php echo $status_counts['processing']; ?></span>
                    <?php endif; ?>
                </a>
                <a href="?status=shipped" class="btn <?php echo $status_filter == 'shipped' ? 'btn-primary' : 'btn-outline-primary'; ?>">
                    <i class="fas fa-truck me-1"></i> Shipped
                    <?php if($status_counts['shipped'] > 0): ?>
                        <span class="badge bg-<?php echo $status_filter == 'shipped' ? 'light text-dark' : 'primary'; ?>"><?php echo $status_counts['shipped']; ?></span>
                    <?php endif; ?>
                </a>
                <a href="?status=delivered" class="btn <?php echo $status_filter == 'delivered' ? 'btn-success' : 'btn-outline-success'; ?>">
                    <i class="fas fa-check-circle me-1"></i> Delivered
                    <?php if($status_counts['delivered'] > 0): ?>
                        <span class="badge bg-<?php echo $status_filter == 'delivered' ? 'light text-dark' : 'success'; ?>"><?php echo $status_counts['delivered']; ?></span>
                    <?php endif; ?>
                </a>
                <a href="?status=completed" class="btn <?php echo $status_filter == 'completed' ? 'btn-success' : 'btn-outline-success'; ?>">
                    <i class="fas fa-check-double me-1"></i> Completed
                    <?php if($status_counts['completed'] > 0): ?>
                        <span class="badge bg-<?php echo $status_filter == 'completed' ? 'light text-dark' : 'success'; ?>"><?php echo $status_counts['completed']; ?></span>
                    <?php endif; ?>
                </a>
                <a href="?status=cancelled" class="btn <?php echo $status_filter == 'cancelled' ? 'btn-danger' : 'btn-outline-danger'; ?>">
                    <i class="fas fa-times-circle me-1"></i> Cancelled
                    <?php if($status_counts['cancelled'] > 0): ?>
                        <span class="badge bg-<?php echo $status_filter == 'cancelled' ? 'light text-dark' : 'danger'; ?>"><?php echo $status_counts['cancelled']; ?></span>
                    <?php endif; ?>
                </a>
            </div>
            
            <!-- Search -->
            <div class="mb-4">
                <form method="GET" action="" class="row g-2">
                    <input type="hidden" name="status" value="<?php echo $status_filter; ?>">
                    <div class="col-md-8">
                        <input type="text" name="search" class="form-control" placeholder="Search by order number..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="col-md-4">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-search me-1"></i> Search
                        </button>
                    </div>
                </form>
            </div>
            
            <!-- Orders List -->
            <?php if (!empty($orders)): ?>
                <?php foreach($orders as $order): ?>
                <div class="order-card" style="border-left-color: <?php echo $order['order_status'] == 'cancelled' ? '#dc3545' : ($order['order_status'] == 'completed' ? '#198754' : '#0d6efd'); ?>;">
                    <!-- Order Header -->
                    <div class="order-header">
                        <div class="order-number">
                            <code><?php echo htmlspecialchars($order['order_number']); ?></code>
                            <?php if(isset($order['invoice_number']) && !empty($order['invoice_number'])): ?>
                                <span class="text-muted ms-2">| Invoice: <?php echo htmlspecialchars($order['invoice_number']); ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="order-date">
                            <i class="fas fa-calendar-alt me-1"></i>
                            <?php echo formatDateTime($order['created_at']); ?>
                        </div>
                    </div>
                    
                    <!-- Order Details -->
                    <div class="order-details">
                        <div class="detail-item">
                            <div class="label">Total Items</div>
                            <div class="value"><?php echo $order['total_items'] ?? $order['item_count']; ?> items</div>
                        </div>
                        <div class="detail-item">
                            <div class="label">Total Amount</div>
                            <div class="value text-primary fw-bold"><?php echo formatPrice($order['total_amount']); ?></div>
                        </div>
                        <div class="detail-item">
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
                        <div class="detail-item">
                            <div class="label">Order Status</div>
                            <div class="value">
                                <?php $badge = getStatusBadge($order['order_status']); ?>
                                <span class="badge bg-<?php echo $badge; ?>">
                                    <i class="fas <?php echo getStatusIcon($order['order_status']); ?> me-1"></i>
                                    <?php echo ucfirst($order['order_status']); ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Status Timeline -->
                    <div class="order-timeline">
                        <?php
                        $status_order = ['pending', 'processing', 'shipped', 'delivered', 'completed'];
                        $current_status = $order['order_status'];
                        $current_index = array_search($current_status, $status_order);
                        if ($current_index === false) $current_index = 0;
                        ?>
                        <?php foreach($status_order as $index => $status): ?>
                            <?php if($index <= $current_index): ?>
                                <span class="step completed">
                                    <i class="fas fa-check-circle"></i> <?php echo ucfirst($status); ?>
                                </span>
                            <?php else: ?>
                                <span class="step">
                                    <i class="far fa-circle"></i> <?php echo ucfirst($status); ?>
                                </span>
                            <?php endif; ?>
                            <?php if($index < count($status_order) - 1): ?>
                                <span class="step-line <?php echo $index < $current_index ? 'completed' : ''; ?>"></span>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- Order Actions -->
                    <div class="order-actions">
                        <a href="order-details.php?id=<?php echo $order['id']; ?>" class="btn btn-sm btn-primary">
                            <i class="fas fa-eye me-1"></i> View Details
                        </a>
                        <?php if($order['order_status'] == 'pending'): ?>
                            <button onclick="cancelOrder(<?php echo $order['id']; ?>)" class="btn btn-sm btn-danger">
                                <i class="fas fa-times me-1"></i> Cancel Order
                            </button>
                        <?php endif; ?>
                        <?php if($order['order_status'] == 'delivered' || $order['order_status'] == 'completed'): ?>
                            <?php if(isset($order['invoice_number']) && !empty($order['invoice_number'])): ?>
                                <a href="../../invoices/download-invoice.php?id=<?php echo $order['id']; ?>" class="btn btn-sm btn-outline-secondary">
                                    <i class="fas fa-file-pdf me-1"></i> Download Invoice
                                </a>
                            <?php endif; ?>
                            <button onclick="writeReview(<?php echo $order['id']; ?>)" class="btn btn-sm btn-outline-warning">
                                <i class="fas fa-star me-1"></i> Write Review
                            </button>
                        <?php endif; ?>
                        <?php if($order['order_status'] == 'shipped' && !empty($order['tracking_number'])): ?>
                            <button onclick="trackOrder('<?php echo $order['tracking_number']; ?>')" class="btn btn-sm btn-outline-info">
                                <i class="fas fa-map-marker-alt me-1"></i> Track Order
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
                
                <!-- Pagination -->
                <?php if($total_pages > 1): ?>
                <nav class="mt-4">
                    <ul class="pagination justify-content-center">
                        <?php if($page > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">
                                <i class="fas fa-chevron-left"></i> Previous
                            </a>
                        </li>
                        <?php endif; ?>
                        
                        <?php for($i = 1; $i <= $total_pages; $i++): ?>
                            <?php if($i == $page): ?>
                            <li class="page-item active"><span class="page-link"><?php echo $i; ?></span></li>
                            <?php elseif($i <= 3 || $i > $total_pages - 3 || ($i >= $page - 1 && $i <= $page + 1)): ?>
                            <li class="page-item">
                                <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>"><?php echo $i; ?></a>
                            </li>
                            <?php elseif($i == 4 || $i == $total_pages - 3): ?>
                            <li class="page-item disabled"><span class="page-link">...</span></li>
                            <?php endif; ?>
                        <?php endfor; ?>
                        
                        <?php if($page < $total_pages): ?>
                        <li class="page-item">
                            <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">
                                Next <i class="fas fa-chevron-right"></i>
                            </a>
                        </li>
                        <?php endif; ?>
                    </ul>
                </nav>
                <?php endif; ?>
                
            <?php else: ?>
            <!-- No Orders -->
            <div class="no-orders">
                <i class="fas fa-shopping-bag fa-4x text-muted mb-3"></i>
                <h4>No Orders Found</h4>
                <p class="text-muted">
                    <?php if(!empty($search) || $status_filter != 'all'): ?>
                        No orders match your search criteria.
                    <?php else: ?>
                        You haven't placed any orders yet.
                    <?php endif; ?>
                </p>
                <?php if(!empty($search) || $status_filter != 'all'): ?>
                    <a href="my-orders.php" class="btn btn-primary">
                        <i class="fas fa-undo me-2"></i> Clear Filters
                    </a>
                <?php else: ?>
                    <a href="../../products.php" class="btn btn-primary">
                        <i class="fas fa-shopping-cart me-2"></i> Start Shopping
                    </a>
                <?php endif; ?>
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
    // Cancel order
    function cancelOrder(orderId) {
        if (confirm('Are you sure you want to cancel this order?')) {
            $.ajax({
                url: 'ajax/cancel-order.php',
                type: 'POST',
                data: { order_id: orderId },
                success: function(response) {
                    try {
                        var data = JSON.parse(response);
                        if (data.success) {
                            showToast('Success', 'Order cancelled successfully!', 'success');
                            setTimeout(function() {
                                location.reload();
                            }, 1500);
                        } else {
                            showToast('Error', data.message || 'Error cancelling order', 'error');
                        }
                    } catch(e) {
                        location.reload();
                    }
                },
                error: function() {
                    location.reload();
                }
            });
        }
    }
    
    // Write review
    function writeReview(orderId) {
        window.location.href = '../reviews/add-review.php?order_id=' + orderId;
    }
    
    // Track order
    function trackOrder(trackingNumber) {
        window.open('track-order.php?tracking=' + trackingNumber, '_blank');
    }
    
    // Show toast notification
    function showToast(title, message, type) {
        const toast = document.createElement('div');
        toast.className = `toast position-fixed bottom-0 end-0 m-3 bg-${type === 'success' ? 'success' : 'danger'} text-white`;
        toast.setAttribute('role', 'alert');
        toast.innerHTML = `
            <div class="toast-header">
                <strong class="me-auto">${title}</strong>
                <button type="button" class="btn-close" data-bs-dismiss="toast"></button>
            </div>
            <div class="toast-body">${message}</div>
        `;
        document.body.appendChild(toast);
        const bsToast = new bootstrap.Toast(toast, { delay: 3000 });
        bsToast.show();
        toast.addEventListener('hidden.bs.toast', () => toast.remove());
    }
</script>

</body>
</html>