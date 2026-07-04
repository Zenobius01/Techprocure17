<?php
/**
 * TechProcure Tanzania - Supplier Orders
 * File: supplier/orders/supplier-orders.php
 * Description: Suppliers can view and manage orders for their products
 */

// Start session if not started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include configuration files
require_once '../../includes/config.php';
require_once '../../includes/db.php';
require_once '../../includes/functions.php';

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
// PROCESS ORDER STATUS UPDATE (AJAX)
// =============================================
if (isset($_POST['action']) && $_POST['action'] === 'update_order_status') {
    header('Content-Type: application/json');
    
    // Check authentication
    if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'supplier') {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit();
    }
    
    // Get POST data
    $order_id = isset($_POST['order_id']) ? (int)$_POST['order_id'] : 0;
    $status = isset($_POST['status']) ? sanitizeInput($_POST['status']) : '';
    $csrf_token_post = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
    
    // Validate CSRF token
    if (empty($csrf_token_post) || empty($_SESSION['csrf_token'])) {
        echo json_encode(['success' => false, 'message' => 'Missing security token']);
        exit();
    }
    
    if (!hash_equals($_SESSION['csrf_token'], $csrf_token_post)) {
        echo json_encode(['success' => false, 'message' => 'Invalid security token']);
        exit();
    }
    
    // Validate inputs
    if ($order_id <= 0 || empty($status)) {
        echo json_encode(['success' => false, 'message' => 'Invalid input']);
        exit();
    }
    
    // Validate status
    $allowed_statuses = ['pending', 'processing', 'shipped', 'delivered', 'cancelled'];
    if (!in_array($status, $allowed_statuses)) {
        echo json_encode(['success' => false, 'message' => 'Invalid status']);
        exit();
    }
    
    try {
        // Check if order contains supplier's products
        $check_stmt = $conn->prepare("
            SELECT COUNT(*) as count 
            FROM order_items oi
            JOIN products p ON oi.product_id = p.id
            WHERE oi.order_id = ? AND p.supplier_id = ?
        ");
        $check_stmt->execute([$order_id, $supplier_id]);
        $result = $check_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result['count'] == 0) {
            echo json_encode(['success' => false, 'message' => 'Order not found or unauthorized']);
            exit();
        }
        
        // Update order status
        $update_stmt = $conn->prepare("
            UPDATE orders 
            SET order_status = ?, updated_at = NOW() 
            WHERE id = ?
        ");
        $update_stmt->execute([$status, $order_id]);
        
        // Add order tracking entry
        try {
            // Check if order_tracking table exists
            $tableCheck = $conn->query("SHOW TABLES LIKE 'order_tracking'");
            if ($tableCheck->rowCount() > 0) {
                $track_sql = "INSERT INTO order_tracking (order_id, status, description, created_at) 
                              VALUES (?, ?, ?, NOW())";
                $track_stmt = $conn->prepare($track_sql);
                $status_labels = [
                    'pending' => 'Order is pending processing',
                    'processing' => 'Order is being processed',
                    'shipped' => 'Order has been shipped',
                    'delivered' => 'Order has been delivered',
                    'cancelled' => 'Order has been cancelled'
                ];
                $description = $status_labels[$status] ?? 'Order status updated to ' . $status;
                $track_stmt->execute([$order_id, $status, $description]);
            }
        } catch (Exception $e) {
            // Tracking table might not exist - skip
        }
        
        echo json_encode(['success' => true, 'message' => 'Order status updated successfully']);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit();
}

// =============================================
// GET ORDER STATISTICS
// =============================================
$total_orders = 0;
$pending_orders = 0;
$processing_orders = 0;
$shipped_orders = 0;
$delivered_orders = 0;
$cancelled_orders = 0;

try {
    // Get total orders for supplier's products
    $count_stmt = $conn->prepare("
        SELECT COUNT(DISTINCT o.id) as total 
        FROM orders o
        JOIN order_items oi ON o.id = oi.order_id
        JOIN products p ON oi.product_id = p.id
        WHERE p.supplier_id = ?
    ");
    $count_stmt->execute([$supplier_id]);
    $total_orders = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
    // Get orders by status
    $statuses = ['pending', 'processing', 'shipped', 'delivered', 'cancelled'];
    foreach ($statuses as $status) {
        $stmt = $conn->prepare("
            SELECT COUNT(DISTINCT o.id) as total 
            FROM orders o
            JOIN order_items oi ON o.id = oi.order_id
            JOIN products p ON oi.product_id = p.id
            WHERE p.supplier_id = ? AND o.order_status = ?
        ");
        $stmt->execute([$supplier_id, $status]);
        ${$status . '_orders'} = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    }
    
} catch (PDOException $e) {
    // Continue with zeros
}

// =============================================
// GET FILTER PARAMETERS
// =============================================
$status_filter = isset($_GET['status']) ? sanitizeInput($_GET['status']) : 'all';
$search = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// =============================================
// FETCH ORDERS - FIXED VERSION
// =============================================
$orders = [];
$total_pages = 0;
$current_page = $page;

try {
    // Build the base SELECT query
    $base_select = "
        SELECT DISTINCT 
            o.id,
            o.order_number,
            o.total_amount,
            o.subtotal,
            o.tax_amount,
            o.shipping_cost,
            o.discount_amount,
            o.order_status,
            o.payment_status,
            o.payment_method,
            o.shipping_address,
            o.created_at,
            o.updated_at,
            u.full_name as customer_name,
            u.email as customer_email,
            u.phone as customer_phone,
            (
                SELECT COUNT(*) 
                FROM order_items oi2 
                JOIN products p2 ON oi2.product_id = p2.id
                WHERE oi2.order_id = o.id AND p2.supplier_id = ?
            ) as supplier_items_count,
            (
                SELECT SUM(oi3.quantity * oi3.price) 
                FROM order_items oi3 
                JOIN products p3 ON oi3.product_id = p3.id
                WHERE oi3.order_id = o.id AND p3.supplier_id = ?
            ) as supplier_total
        FROM orders o
        JOIN order_items oi ON o.id = oi.order_id
        JOIN products p ON oi.product_id = p.id
        LEFT JOIN users u ON o.user_id = u.id
        WHERE p.supplier_id = ?
    ";
    
    $params = [$supplier_id, $supplier_id, $supplier_id];
    
    // Build WHERE conditions
    $where_conditions = [];
    
    // Apply status filter
    if ($status_filter !== 'all') {
        $where_conditions[] = "o.order_status = ?";
        $params[] = $status_filter;
    }
    
    // Apply search
    if (!empty($search)) {
        $where_conditions[] = "(o.order_number LIKE ? OR u.full_name LIKE ? OR u.email LIKE ?)";
        $search_param = "%$search%";
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
    }
    
    // Build the WHERE clause
    $where_clause = "";
    if (!empty($where_conditions)) {
        $where_clause = " AND " . implode(" AND ", $where_conditions);
    }
    
    // Complete SQL query with WHERE clause
    $sql = $base_select . $where_clause;
    
    // Get total count for pagination
    $count_sql = "SELECT COUNT(DISTINCT o.id) as total 
                   FROM orders o
                   JOIN order_items oi ON o.id = oi.order_id
                   JOIN products p ON oi.product_id = p.id
                   LEFT JOIN users u ON o.user_id = u.id
                   WHERE p.supplier_id = ?" . $where_clause;
    
    // Prepare count query with parameters (without LIMIT/OFFSET)
    $count_params = [$supplier_id];
    if ($status_filter !== 'all') {
        $count_params[] = $status_filter;
    }
    if (!empty($search)) {
        $search_param = "%$search%";
        $count_params[] = $search_param;
        $count_params[] = $search_param;
        $count_params[] = $search_param;
    }
    
    $count_stmt = $conn->prepare($count_sql);
    $count_stmt->execute($count_params);
    $total_records = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    $total_pages = ceil($total_records / $limit);
    
    // Add order and pagination
    $sql .= " ORDER BY o.created_at DESC LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get order items for each order
    foreach ($orders as &$order) {
        $items_stmt = $conn->prepare("
            SELECT oi.*, p.name as product_name, p.sku, p.supplier_id
            FROM order_items oi
            JOIN products p ON oi.product_id = p.id
            WHERE oi.order_id = ? AND p.supplier_id = ?
        ");
        $items_stmt->execute([$order['id'], $supplier_id]);
        $order['items'] = $items_stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
} catch (PDOException $e) {
    $error = "Failed to load orders: " . $e->getMessage();
    error_log("Order fetch error: " . $e->getMessage());
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
$page_title = 'Supplier Orders - TechProcure Tanzania';
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
        
        .stats-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            transition: all 0.3s;
            text-align: center;
        }
        
        .stats-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .stats-card .stat-icon {
            font-size: 2rem;
            margin-bottom: 10px;
        }
        
        .stats-card .stat-number {
            font-size: 1.8rem;
            font-weight: 700;
        }
        
        .stats-card .stat-label {
            color: #6c757d;
            font-size: 0.85rem;
        }
        
        .table-container {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        
        .table-container .table-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .status-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 500;
        }
        
        .status-badge.pending {
            background: #fff3cd;
            color: #856404;
        }
        
        .status-badge.processing {
            background: #cce5ff;
            color: #004085;
        }
        
        .status-badge.shipped {
            background: #d4edda;
            color: #155724;
        }
        
        .status-badge.delivered {
            background: #d4edda;
            color: #155724;
        }
        
        .status-badge.cancelled {
            background: #f8d7da;
            color: #721c24;
        }
        
        .status-badge.paid {
            background: #d4edda;
            color: #155724;
        }
        
        .status-badge.unpaid {
            background: #f8d7da;
            color: #721c24;
        }
        
        .status-badge.refunded {
            background: #e2e3e5;
            color: #383d41;
        }
        
        .order-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            border: 1px solid #e9ecef;
            transition: all 0.3s;
        }
        
        .order-card:hover {
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .order-card .order-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
            padding-bottom: 10px;
            border-bottom: 1px solid #e9ecef;
            margin-bottom: 10px;
        }
        
        .order-card .order-items {
            margin: 10px 0;
        }
        
        .order-card .order-items .item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 5px 0;
            border-bottom: 1px solid #f8f9fa;
        }
        
        .order-card .order-items .item:last-child {
            border-bottom: none;
        }
        
        .order-card .order-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
            padding-top: 10px;
            border-top: 1px solid #e9ecef;
            margin-top: 10px;
        }
        
        .btn-action {
            padding: 5px 12px;
            font-size: 0.8rem;
            border-radius: 8px;
        }
        
        .btn-action:disabled {
            opacity: 0.5;
            cursor: not-allowed;
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
        
        .pagination .page-link {
            border-radius: 8px;
            margin: 0 3px;
        }
        
        .pagination .page-item.active .page-link {
            background: #198754;
            border-color: #198754;
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
            .stats-card {
                margin-bottom: 15px;
            }
            .table-container {
                padding: 15px;
            }
            .table-container .table-header {
                flex-direction: column;
                align-items: stretch;
            }
            .order-card .order-header {
                flex-direction: column;
                align-items: stretch;
            }
            .order-card .order-footer {
                flex-direction: column;
                align-items: stretch;
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
                    <a class="nav-link active" href="supplier-orders.php">
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
                    <a class="nav-link" href="../profile.php">
                        <i class="fas fa-user"></i> Profile
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
        
        <!-- Dashboard Content -->
        <div class="dashboard-content">
            
            <!-- Page Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h4 class="mb-0"><i class="fas fa-shopping-cart me-2 text-success"></i>Orders</h4>
                    <p class="text-muted">Manage orders for your products</p>
                </div>
            </div>
            
            <!-- Statistics Cards -->
            <div class="row g-3 mb-4">
                <div class="col-6 col-md-2">
                    <div class="stats-card">
                        <div class="stat-icon text-primary"><i class="fas fa-boxes"></i></div>
                        <div class="stat-number"><?php echo number_format($total_orders); ?></div>
                        <div class="stat-label">Total Orders</div>
                    </div>
                </div>
                <div class="col-6 col-md-2">
                    <div class="stats-card">
                        <div class="stat-icon text-warning"><i class="fas fa-clock"></i></div>
                        <div class="stat-number"><?php echo number_format($pending_orders); ?></div>
                        <div class="stat-label">Pending</div>
                    </div>
                </div>
                <div class="col-6 col-md-2">
                    <div class="stats-card">
                        <div class="stat-icon text-info"><i class="fas fa-spinner"></i></div>
                        <div class="stat-number"><?php echo number_format($processing_orders); ?></div>
                        <div class="stat-label">Processing</div>
                    </div>
                </div>
                <div class="col-6 col-md-2">
                    <div class="stats-card">
                        <div class="stat-icon text-primary"><i class="fas fa-truck"></i></div>
                        <div class="stat-number"><?php echo number_format($shipped_orders); ?></div>
                        <div class="stat-label">Shipped</div>
                    </div>
                </div>
                <div class="col-6 col-md-2">
                    <div class="stats-card">
                        <div class="stat-icon text-success"><i class="fas fa-check-circle"></i></div>
                        <div class="stat-number"><?php echo number_format($delivered_orders); ?></div>
                        <div class="stat-label">Delivered</div>
                    </div>
                </div>
                <div class="col-6 col-md-2">
                    <div class="stats-card">
                        <div class="stat-icon text-danger"><i class="fas fa-times-circle"></i></div>
                        <div class="stat-number"><?php echo number_format($cancelled_orders); ?></div>
                        <div class="stat-label">Cancelled</div>
                    </div>
                </div>
            </div>
            
            <!-- Orders Table -->
            <div class="table-container">
                <div class="table-header">
                    <div class="d-flex gap-2 flex-wrap">
                        <div class="btn-group">
                            <a href="?status=all" class="btn btn-sm <?php echo $status_filter == 'all' ? 'btn-primary' : 'btn-outline-primary'; ?>">All</a>
                            <a href="?status=pending" class="btn btn-sm <?php echo $status_filter == 'pending' ? 'btn-warning' : 'btn-outline-warning'; ?>">Pending</a>
                            <a href="?status=processing" class="btn btn-sm <?php echo $status_filter == 'processing' ? 'btn-info' : 'btn-outline-info'; ?>">Processing</a>
                            <a href="?status=shipped" class="btn btn-sm <?php echo $status_filter == 'shipped' ? 'btn-primary' : 'btn-outline-primary'; ?>">Shipped</a>
                            <a href="?status=delivered" class="btn btn-sm <?php echo $status_filter == 'delivered' ? 'btn-success' : 'btn-outline-success'; ?>">Delivered</a>
                            <a href="?status=cancelled" class="btn btn-sm <?php echo $status_filter == 'cancelled' ? 'btn-danger' : 'btn-outline-danger'; ?>">Cancelled</a>
                        </div>
                    </div>
                    
                    <form method="GET" action="" class="d-flex gap-2">
                        <input type="hidden" name="status" value="<?php echo $status_filter; ?>">
                        <input type="text" name="search" class="form-control form-control-sm" placeholder="Search orders..." 
                               value="<?php echo htmlspecialchars($search); ?>" style="width: 200px;">
                        <button type="submit" class="btn btn-sm btn-primary"><i class="fas fa-search"></i></button>
                        <?php if (!empty($search)): ?>
                            <a href="?status=<?php echo $status_filter; ?>" class="btn btn-sm btn-secondary"><i class="fas fa-times"></i></a>
                        <?php endif; ?>
                    </form>
                </div>
                
                <?php if (empty($orders)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-shopping-cart fa-3x text-muted mb-3"></i>
                        <h5>No orders found</h5>
                        <p class="text-muted">You haven't received any orders yet.</p>
                    </div>
                <?php else: ?>
                    <!-- Order Cards -->
                    <?php foreach ($orders as $order): ?>
                    <div class="order-card" id="order-<?php echo $order['id']; ?>">
                        <div class="order-header">
                            <div>
                                <h6 class="mb-1">
                                    <a href="order-details.php?id=<?php echo $order['id']; ?>" class="text-decoration-none">
                                        #<?php echo htmlspecialchars($order['order_number']); ?>
                                    </a>
                                </h6>
                                <small class="text-muted">
                                    <i class="far fa-calendar-alt me-1"></i>
                                    <?php echo date('M d, Y H:i', strtotime($order['created_at'])); ?>
                                </small>
                            </div>
                            <div class="d-flex gap-2 align-items-center flex-wrap">
                                <span class="status-badge <?php echo $order['order_status']; ?>">
                                    <?php echo ucfirst($order['order_status']); ?>
                                </span>
                                <span class="status-badge <?php echo $order['payment_status'] == 'paid' ? 'paid' : 'unpaid'; ?>">
                                    <?php echo ucfirst($order['payment_status']); ?>
                                </span>
                                <small class="text-muted">
                                    <i class="fas fa-user me-1"></i>
                                    <?php echo htmlspecialchars($order['customer_name'] ?? 'Guest'); ?>
                                </small>
                            </div>
                        </div>
                        
                        <div class="order-items">
                            <?php foreach ($order['items'] as $item): ?>
                            <div class="item">
                                <div>
                                    <span class="fw-semibold"><?php echo htmlspecialchars($item['product_name']); ?></span>
                                    <small class="text-muted ms-2">(x<?php echo $item['quantity']; ?>)</small>
                                    <br>
                                    <small class="text-muted">SKU: <?php echo htmlspecialchars($item['sku']); ?></small>
                                </div>
                                <div>
                                    <span class="fw-bold">TSh <?php echo number_format($item['price'] * $item['quantity'], 2); ?></span>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <div class="order-footer">
                            <div>
                                <span class="fw-bold">Total: TSh <?php echo number_format($order['supplier_total'] ?? $order['total_amount'], 2); ?></span>
                                <small class="text-muted ms-2">
                                    (<?php echo count($order['items']); ?> items from you)
                                </small>
                            </div>
                            <div class="d-flex gap-2 flex-wrap">
                                <a href="order-details.php?id=<?php echo $order['id']; ?>" class="btn btn-sm btn-outline-primary btn-action">
                                    <i class="fas fa-eye me-1"></i> View Details
                                </a>
                                <?php if ($order['order_status'] == 'pending'): ?>
                                    <button onclick="updateOrderStatus(<?php echo $order['id']; ?>, 'processing')" class="btn btn-sm btn-info btn-action">
                                        <i class="fas fa-check me-1"></i> Process
                                    </button>
                                    <button onclick="updateOrderStatus(<?php echo $order['id']; ?>, 'cancelled')" class="btn btn-sm btn-danger btn-action">
                                        <i class="fas fa-times me-1"></i> Cancel
                                    </button>
                                <?php endif; ?>
                                <?php if ($order['order_status'] == 'processing'): ?>
                                    <button onclick="updateOrderStatus(<?php echo $order['id']; ?>, 'shipped')" class="btn btn-sm btn-primary btn-action">
                                        <i class="fas fa-truck me-1"></i> Ship
                                    </button>
                                    <button onclick="updateOrderStatus(<?php echo $order['id']; ?>, 'cancelled')" class="btn btn-sm btn-danger btn-action">
                                        <i class="fas fa-times me-1"></i> Cancel
                                    </button>
                                <?php endif; ?>
                                <?php if ($order['order_status'] == 'shipped'): ?>
                                    <button onclick="updateOrderStatus(<?php echo $order['id']; ?>, 'delivered')" class="btn btn-sm btn-success btn-action">
                                        <i class="fas fa-check-circle me-1"></i> Deliver
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    
                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                    <nav aria-label="Page navigation" class="mt-3">
                        <ul class="pagination justify-content-center">
                            <?php if ($current_page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $current_page - 1; ?>&status=<?php echo $status_filter; ?>&search=<?php echo urlencode($search); ?>">
                                        <i class="fas fa-chevron-left"></i>
                                    </a>
                                </li>
                            <?php endif; ?>
                            
                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <li class="page-item <?php echo $i == $current_page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?>&status=<?php echo $status_filter; ?>&search=<?php echo urlencode($search); ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                            <?php endfor; ?>
                            
                            <?php if ($current_page < $total_pages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $current_page + 1; ?>&status=<?php echo $status_filter; ?>&search=<?php echo urlencode($search); ?>">
                                        <i class="fas fa-chevron-right"></i>
                                    </a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                    <?php endif; ?>
                <?php endif; ?>
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
                    <li><a href="supplier-orders.php">Orders</a></li>
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
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
// =============================================
// UPDATE ORDER STATUS - ALL IN ONE FILE
// =============================================
function updateOrderStatus(orderId, status) {
    const statusLabels = {
        'pending': 'Pending',
        'processing': 'Processing',
        'shipped': 'Shipped',
        'delivered': 'Delivered',
        'cancelled': 'Cancelled'
    };
    
    const actionText = status === 'cancelled' ? 'Cancel' : `Mark as ${statusLabels[status]}`;
    const icon = status === 'cancelled' ? 'warning' : 'question';
    
    Swal.fire({
        title: `${actionText} Order?`,
        text: `Are you sure you want to ${actionText.toLowerCase()} this order?`,
        icon: icon,
        showCancelButton: true,
        confirmButtonColor: status === 'cancelled' ? '#d33' : '#198754',
        cancelButtonColor: '#6c757d',
        confirmButtonText: `Yes, ${actionText}!`,
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({
                title: 'Updating...',
                text: 'Please wait while we update the order status.',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            
            // Get CSRF token from meta tag
            var csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
            
            if (!csrfToken) {
                // Try hidden input
                var csrfInput = document.querySelector('input[name="csrf_token"]');
                if (csrfInput) {
                    csrfToken = csrfInput.value;
                }
            }
            
            // Send AJAX request to the same file
            $.ajax({
                url: window.location.href,
                type: 'POST',
                data: {
                    action: 'update_order_status',
                    order_id: orderId,
                    status: status,
                    csrf_token: csrfToken || ''
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Updated!',
                            text: response.message,
                            timer: 2000,
                            showConfirmButton: false
                        }).then(() => {
                            location.reload();
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: response.message || 'Failed to update order status.'
                        });
                    }
                },
                error: function(xhr, status, error) {
                    console.log('Error:', error);
                    console.log('Response:', xhr.responseText);
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'An error occurred. Please try again.'
                    });
                }
            });
        }
    });
}

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
// CONSOLE LOG FOR DEBUGGING
// =============================================
console.log('CSRF Token from meta:', document.querySelector('meta[name="csrf-token"]')?.getAttribute('content'));
console.log('Page loaded: <?php echo $page_title; ?>');
</script>

</body>
</html>