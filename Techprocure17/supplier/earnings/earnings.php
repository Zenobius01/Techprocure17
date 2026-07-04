<?php
/**
 * TechProcure Tanzania - Supplier Earnings
 * File: supplier/earnings/earnings.php
 * Description: Suppliers can view their earnings, commissions, and payment history
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

$csrf_token = generateCSRFToken();

// =============================================
// GET EARNINGS STATISTICS
// =============================================
$total_earnings = 0;
$pending_earnings = 0;
$paid_earnings = 0;
$total_orders = 0;
$total_products = 0;
$average_rating = 0;

try {
    // Get total earnings from completed orders
    $earnings_stmt = $conn->prepare("
        SELECT 
            SUM(oi.quantity * oi.price) as total_earnings,
            SUM(CASE WHEN o.payment_status = 'paid' THEN oi.quantity * oi.price ELSE 0 END) as paid_earnings,
            SUM(CASE WHEN o.payment_status = 'pending' THEN oi.quantity * oi.price ELSE 0 END) as pending_earnings
        FROM order_items oi
        JOIN orders o ON oi.order_id = o.id
        JOIN products p ON oi.product_id = p.id
        WHERE p.supplier_id = ? AND o.order_status = 'delivered'
    ");
    $earnings_stmt->execute([$supplier_id]);
    $earnings_data = $earnings_stmt->fetch(PDO::FETCH_ASSOC);
    
    $total_earnings = $earnings_data['total_earnings'] ?? 0;
    $paid_earnings = $earnings_data['paid_earnings'] ?? 0;
    $pending_earnings = $earnings_data['pending_earnings'] ?? 0;
    
    // Get total orders count
    $orders_stmt = $conn->prepare("
        SELECT COUNT(DISTINCT o.id) as total
        FROM orders o
        JOIN order_items oi ON o.id = oi.order_id
        JOIN products p ON oi.product_id = p.id
        WHERE p.supplier_id = ?
    ");
    $orders_stmt->execute([$supplier_id]);
    $total_orders = $orders_stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
    // Get total products count
    $products_stmt = $conn->prepare("
        SELECT COUNT(*) as total FROM products WHERE supplier_id = ?
    ");
    $products_stmt->execute([$supplier_id]);
    $total_products = $products_stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
    // Get average rating
    $rating_stmt = $conn->prepare("
        SELECT AVG(rating) as avg_rating 
        FROM product_reviews pr
        JOIN products p ON pr.product_id = p.id
        WHERE p.supplier_id = ?
    ");
    $rating_stmt->execute([$supplier_id]);
    $average_rating = round($rating_stmt->fetch(PDO::FETCH_ASSOC)['avg_rating'] ?? 0, 1);
    
} catch (PDOException $e) {
    // Continue with zeros
    error_log("Earnings stats error: " . $e->getMessage());
}

// =============================================
// GET FILTER PARAMETERS
// =============================================
$filter_type = isset($_GET['type']) ? sanitizeInput($_GET['type']) : 'all';
$filter_period = isset($_GET['period']) ? sanitizeInput($_GET['period']) : 'all';
$search = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// =============================================
// FETCH EARNINGS TRANSACTIONS
// =============================================
$transactions = [];
$total_pages = 0;
$current_page = $page;
$total_records = 0;

try {
    // Base query for earnings transactions
    $base_sql = "
        SELECT 
            o.id as order_id,
            o.order_number,
            o.created_at as order_date,
            o.order_status,
            o.payment_status,
            o.payment_method,
            o.total_amount,
            u.full_name as customer_name,
            u.email as customer_email,
            COUNT(DISTINCT oi.id) as item_count,
            SUM(oi.quantity * oi.price) as supplier_amount,
            (
                SELECT SUM(oi2.quantity * oi2.price) 
                FROM order_items oi2 
                JOIN products p2 ON oi2.product_id = p2.id
                WHERE oi2.order_id = o.id AND p2.supplier_id = ?
            ) as supplier_total
        FROM orders o
        JOIN order_items oi ON o.id = oi.order_id
        JOIN products p ON oi.product_id = p.id
        LEFT JOIN users u ON o.user_id = u.id
        WHERE p.supplier_id = ?
    ";
    
    $params = [$supplier_id, $supplier_id];
    
    // Apply filters
    if ($filter_type === 'paid') {
        $base_sql .= " AND o.payment_status = 'paid'";
    } elseif ($filter_type === 'pending') {
        $base_sql .= " AND o.payment_status = 'pending'";
    } elseif ($filter_type === 'completed') {
        $base_sql .= " AND o.order_status = 'delivered'";
    } elseif ($filter_type === 'cancelled') {
        $base_sql .= " AND o.order_status = 'cancelled'";
    }
    
    // Apply period filter
    if ($filter_period === 'today') {
        $base_sql .= " AND DATE(o.created_at) = CURDATE()";
    } elseif ($filter_period === 'week') {
        $base_sql .= " AND YEARWEEK(o.created_at) = YEARWEEK(CURDATE())";
    } elseif ($filter_period === 'month') {
        $base_sql .= " AND MONTH(o.created_at) = MONTH(CURDATE()) AND YEAR(o.created_at) = YEAR(CURDATE())";
    } elseif ($filter_period === 'year') {
        $base_sql .= " AND YEAR(o.created_at) = YEAR(CURDATE())";
    }
    
    // Apply search
    if (!empty($search)) {
        $base_sql .= " AND (o.order_number LIKE ? OR u.full_name LIKE ? OR u.email LIKE ?)";
        $search_param = "%$search%";
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
    }
    
    // Group by order
    $base_sql .= " GROUP BY o.id";
    
    // Get total count
    $count_sql = "SELECT COUNT(*) as total FROM (" . $base_sql . ") as subquery";
    $count_stmt = $conn->prepare($count_sql);
    $count_stmt->execute($params);
    $total_records = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    $total_pages = ceil($total_records / $limit);
    
    // Add order and pagination
    $base_sql .= " ORDER BY o.created_at DESC LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
    
    $stmt = $conn->prepare($base_sql);
    $stmt->execute($params);
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $error = "Failed to load earnings: " . $e->getMessage();
    error_log("Earnings fetch error: " . $e->getMessage());
}

// =============================================
// GET MONTHLY EARNINGS CHART DATA
// =============================================
$monthly_earnings = [];
try {
    $chart_stmt = $conn->prepare("
        SELECT 
            DATE_FORMAT(o.created_at, '%Y-%m') as month,
            SUM(oi.quantity * oi.price) as total
        FROM order_items oi
        JOIN orders o ON oi.order_id = o.id
        JOIN products p ON oi.product_id = p.id
        WHERE p.supplier_id = ? 
            AND o.order_status = 'delivered'
            AND o.payment_status = 'paid'
            AND o.created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
        GROUP BY DATE_FORMAT(o.created_at, '%Y-%m')
        ORDER BY month DESC
    ");
    $chart_stmt->execute([$supplier_id]);
    $monthly_earnings = $chart_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Skip chart data
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
$page_title = 'Earnings - Supplier Panel';
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
            height: 100%;
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
        
        .stats-card .stat-change {
            font-size: 0.75rem;
            font-weight: 500;
        }
        
        .stats-card .stat-change.positive {
            color: #28a745;
        }
        
        .stats-card .stat-change.negative {
            color: #dc3545;
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
        
        .status-badge.paid {
            background: #d4edda;
            color: #155724;
        }
        
        .status-badge.pending {
            background: #fff3cd;
            color: #856404;
        }
        
        .status-badge.unpaid {
            background: #f8d7da;
            color: #721c24;
        }
        
        .status-badge.completed {
            background: #d4edda;
            color: #155724;
        }
        
        .status-badge.cancelled {
            background: #f8d7da;
            color: #721c24;
        }
        
        .transaction-row {
            transition: all 0.2s;
        }
        
        .transaction-row:hover {
            background: #f8f9fa;
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
        
        .chart-container {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            margin-bottom: 24px;
        }
        
        .chart-container .chart-title {
            font-weight: 600;
            margin-bottom: 15px;
        }
        
        .bar-chart {
            display: flex;
            align-items: flex-end;
            height: 200px;
            gap: 8px;
            padding: 10px 0;
            overflow-x: auto;
        }
        
        .bar-item {
            flex: 1;
            min-width: 40px;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        
        .bar-item .bar {
            width: 30px;
            background: linear-gradient(180deg, #198754, #157347);
            border-radius: 4px 4px 0 0;
            min-height: 4px;
            transition: height 0.3s;
            position: relative;
        }
        
        .bar-item .bar:hover {
            opacity: 0.8;
        }
        
        .bar-item .bar-label {
            font-size: 0.65rem;
            color: #6c757d;
            margin-top: 5px;
            text-align: center;
        }
        
        .bar-item .bar-value {
            font-size: 0.7rem;
            font-weight: 600;
            color: #198754;
            margin-bottom: 2px;
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
            .bar-item .bar {
                width: 20px;
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
                    <a class="nav-link active" href="earnings.php">
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
                    <h4 class="mb-0"><i class="fas fa-money-bill-wave me-2 text-success"></i>Earnings</h4>
                    <p class="text-muted">Track your earnings and payment history</p>
                </div>
                <div>
                    <span class="badge bg-success">Supplier</span>
                </div>
            </div>
            
            <!-- Statistics Cards -->
            <div class="row g-3 mb-4">
                <div class="col-6 col-md-3">
                    <div class="stats-card">
                        <div class="stat-icon text-success"><i class="fas fa-total"></i></div>
                        <div class="stat-number">TSh <?php echo number_format($total_earnings, 0); ?></div>
                        <div class="stat-label">Total Earnings</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="stats-card">
                        <div class="stat-icon text-primary"><i class="fas fa-check-circle"></i></div>
                        <div class="stat-number">TSh <?php echo number_format($paid_earnings, 0); ?></div>
                        <div class="stat-label">Paid</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="stats-card">
                        <div class="stat-icon text-warning"><i class="fas fa-clock"></i></div>
                        <div class="stat-number">TSh <?php echo number_format($pending_earnings, 0); ?></div>
                        <div class="stat-label">Pending</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="stats-card">
                        <div class="stat-icon text-info"><i class="fas fa-shopping-cart"></i></div>
                        <div class="stat-number"><?php echo number_format($total_orders); ?></div>
                        <div class="stat-label">Total Orders</div>
                    </div>
                </div>
            </div>
            
            <!-- Monthly Earnings Chart -->
            <?php if (!empty($monthly_earnings)): ?>
            <div class="chart-container">
                <div class="chart-title">
                    <i class="fas fa-chart-bar me-2 text-success"></i>Monthly Earnings (Last 12 Months)
                </div>
                <div class="bar-chart">
                    <?php 
                    $max_value = max(array_column($monthly_earnings, 'total')) ?: 1;
                    foreach (array_reverse($monthly_earnings) as $data): 
                        $height = ($data['total'] / $max_value) * 180;
                    ?>
                    <div class="bar-item">
                        <div class="bar-value">TSh <?php echo number_format($data['total'], 0); ?></div>
                        <div class="bar" style="height: <?php echo max($height, 4); ?>px;"></div>
                        <div class="bar-label"><?php echo date('M Y', strtotime($data['month'] . '-01')); ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Transactions Table -->
            <div class="table-container">
                <div class="table-header">
                    <div class="d-flex gap-2 flex-wrap">
                        <div class="btn-group">
                            <a href="?type=all&period=<?php echo $filter_period; ?>" class="btn btn-sm <?php echo $filter_type == 'all' ? 'btn-primary' : 'btn-outline-primary'; ?>">All</a>
                            <a href="?type=paid&period=<?php echo $filter_period; ?>" class="btn btn-sm <?php echo $filter_type == 'paid' ? 'btn-success' : 'btn-outline-success'; ?>">Paid</a>
                            <a href="?type=pending&period=<?php echo $filter_period; ?>" class="btn btn-sm <?php echo $filter_type == 'pending' ? 'btn-warning' : 'btn-outline-warning'; ?>">Pending</a>
                            <a href="?type=completed&period=<?php echo $filter_period; ?>" class="btn btn-sm <?php echo $filter_type == 'completed' ? 'btn-info' : 'btn-outline-info'; ?>">Completed</a>
                            <a href="?type=cancelled&period=<?php echo $filter_period; ?>" class="btn btn-sm <?php echo $filter_type == 'cancelled' ? 'btn-danger' : 'btn-outline-danger'; ?>">Cancelled</a>
                        </div>
                        <div class="btn-group">
                            <a href="?type=<?php echo $filter_type; ?>&period=all" class="btn btn-sm <?php echo $filter_period == 'all' ? 'btn-secondary' : 'btn-outline-secondary'; ?>">All Time</a>
                            <a href="?type=<?php echo $filter_type; ?>&period=today" class="btn btn-sm <?php echo $filter_period == 'today' ? 'btn-secondary' : 'btn-outline-secondary'; ?>">Today</a>
                            <a href="?type=<?php echo $filter_type; ?>&period=week" class="btn btn-sm <?php echo $filter_period == 'week' ? 'btn-secondary' : 'btn-outline-secondary'; ?>">This Week</a>
                            <a href="?type=<?php echo $filter_type; ?>&period=month" class="btn btn-sm <?php echo $filter_period == 'month' ? 'btn-secondary' : 'btn-outline-secondary'; ?>">This Month</a>
                            <a href="?type=<?php echo $filter_type; ?>&period=year" class="btn btn-sm <?php echo $filter_period == 'year' ? 'btn-secondary' : 'btn-outline-secondary'; ?>">This Year</a>
                        </div>
                    </div>
                    
                    <form method="GET" action="" class="d-flex gap-2">
                        <input type="hidden" name="type" value="<?php echo $filter_type; ?>">
                        <input type="hidden" name="period" value="<?php echo $filter_period; ?>">
                        <input type="text" name="search" class="form-control form-control-sm" placeholder="Search orders..." 
                               value="<?php echo htmlspecialchars($search); ?>" style="width: 200px;">
                        <button type="submit" class="btn btn-sm btn-primary"><i class="fas fa-search"></i></button>
                        <?php if (!empty($search)): ?>
                            <a href="?type=<?php echo $filter_type; ?>&period=<?php echo $filter_period; ?>" class="btn btn-sm btn-secondary"><i class="fas fa-times"></i></a>
                        <?php endif; ?>
                    </form>
                </div>
                
                <?php if (empty($transactions)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-money-bill-wave fa-3x text-muted mb-3"></i>
                        <h5>No earnings found</h5>
                        <p class="text-muted">You haven't received any payments yet.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead>
                                <tr>
                                    <th>Order #</th>
                                    <th>Customer</th>
                                    <th>Date</th>
                                    <th>Items</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                    <th>Payment</th>
                                    <th class="text-end">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($transactions as $transaction): ?>
                                <tr class="transaction-row">
                                    <td>
                                        <a href="../orders/order-details.php?id=<?php echo $transaction['order_id']; ?>" class="text-decoration-none fw-semibold">
                                            #<?php echo htmlspecialchars($transaction['order_number']); ?>
                                        </a>
                                    </td>
                                    <td>
                                        <div class="fw-semibold"><?php echo htmlspecialchars($transaction['customer_name'] ?? 'Guest'); ?></div>
                                        <small class="text-muted"><?php echo htmlspecialchars($transaction['customer_email'] ?? ''); ?></small>
                                    </td>
                                    <td>
                                        <small><?php echo date('M d, Y', strtotime($transaction['order_date'])); ?></small>
                                        <br>
                                        <small class="text-muted"><?php echo date('H:i', strtotime($transaction['order_date'])); ?></small>
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary"><?php echo $transaction['item_count']; ?></span>
                                    </td>
                                    <td>
                                        <span class="fw-bold text-success">TSh <?php echo number_format($transaction['supplier_total'] ?? 0, 2); ?></span>
                                    </td>
                                    <td>
                                        <span class="status-badge <?php echo $transaction['order_status']; ?>">
                                            <?php echo ucfirst($transaction['order_status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="status-badge <?php echo $transaction['payment_status']; ?>">
                                            <?php echo ucfirst($transaction['payment_status']); ?>
                                        </span>
                                    </td>
                                    <td class="text-end">
                                        <a href="../orders/order-details.php?id=<?php echo $transaction['order_id']; ?>" class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Summary -->
                    <div class="row mt-3">
                        <div class="col-md-6">
                            <small class="text-muted">
                                Showing <?php echo count($transactions); ?> of <?php echo $total_records; ?> transactions
                            </small>
                        </div>
                        <div class="col-md-6 text-end">
                            <small class="text-muted">
                                Total: TSh <?php echo number_format(array_sum(array_column($transactions, 'supplier_total')), 2); ?>
                            </small>
                        </div>
                    </div>
                    
                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                    <nav aria-label="Page navigation" class="mt-3">
                        <ul class="pagination justify-content-center">
                            <?php if ($current_page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $current_page - 1; ?>&type=<?php echo $filter_type; ?>&period=<?php echo $filter_period; ?>&search=<?php echo urlencode($search); ?>">
                                        <i class="fas fa-chevron-left"></i>
                                    </a>
                                </li>
                            <?php endif; ?>
                            
                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <li class="page-item <?php echo $i == $current_page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?>&type=<?php echo $filter_type; ?>&period=<?php echo $filter_period; ?>&search=<?php echo urlencode($search); ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                            <?php endfor; ?>
                            
                            <?php if ($current_page < $total_pages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $current_page + 1; ?>&type=<?php echo $filter_type; ?>&period=<?php echo $filter_period; ?>&search=<?php echo urlencode($search); ?>">
                                        <i class="fas fa-chevron-right"></i>
                                    </a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
            
            <!-- Payment Summary -->
            <div class="row g-3 mt-2">
                <div class="col-md-6">
                    <div class="table-container">
                        <h6 class="mb-3"><i class="fas fa-chart-pie me-2 text-success"></i>Earnings Summary</h6>
                        <div class="row">
                            <div class="col-6">
                                <div class="text-muted small">Total Products</div>
                                <div class="fw-bold"><?php echo number_format($total_products); ?></div>
                            </div>
                            <div class="col-6">
                                <div class="text-muted small">Average Rating</div>
                                <div class="fw-bold">
                                    <?php if ($average_rating > 0): ?>
                                        <?php echo number_format($average_rating, 1); ?> / 5
                                        <i class="fas fa-star text-warning ms-1"></i>
                                    <?php else: ?>
                                        No ratings yet
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="table-container">
                        <h6 class="mb-3"><i class="fas fa-info-circle me-2 text-info"></i>Payment Information</h6>
                        <div class="row">
                            <div class="col-6">
                                <div class="text-muted small">Payment Method</div>
                                <div class="fw-bold">Bank Transfer / M-Pesa</div>
                            </div>
                            <div class="col-6">
                                <div class="text-muted small">Payment Cycle</div>
                                <div class="fw-bold">Monthly</div>
                            </div>
                        </div>
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
                    <li><a href="earnings.php">Earnings</a></li>
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
// TOOLTIP INITIALIZATION
// =============================================
document.addEventListener('DOMContentLoaded', function() {
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});
</script>

</body>
</html>