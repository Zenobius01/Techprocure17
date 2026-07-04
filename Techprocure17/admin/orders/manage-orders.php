<?php
/**
 * TechProcure Tanzania - Admin Order Management
 * File: admin/orders/manage-orders.php
 * Description: Complete order management system with filtering, status updates, and order details
 */

// Start session if not started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include configuration files
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/functions.php';

// Check if user is admin
if (!isAdmin()) {
    header('Location: ../../auth/login.php');
    exit();
}

// Get database connection
try {
    $db = getDB();
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}

$user_id = $_SESSION['user_id'];

// =============================================
// PROCESS ORDER ACTIONS
// =============================================

$action = $_GET['action'] ?? '';
$order_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$error = '';
$success = '';

// Handle order status update
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action_post = $_POST['action'] ?? '';
    $order_id_post = isset($_POST['order_id']) ? (int)$_POST['order_id'] : 0;
    
    if ($action_post == 'update_status' && $order_id_post > 0) {
        $new_status = $_POST['status'] ?? '';
        $note = sanitize($_POST['note'] ?? '');
        
        $valid_statuses = ['pending', 'confirmed', 'processing', 'shipped', 'delivered', 'completed', 'cancelled'];
        
        if (in_array($new_status, $valid_statuses)) {
            try {
                $db->beginTransaction();
                
                // Update order status
                $stmt = $db->prepare("UPDATE orders SET order_status = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$new_status, $order_id_post]);
                
                // Add tracking entry - check if table exists
                try {
                    $db->query("SELECT 1 FROM order_tracking LIMIT 1");
                    $track_sql = "INSERT INTO order_tracking (order_id, status, description, created_at) VALUES (?, ?, ?, NOW())";
                    $track_stmt = $db->prepare($track_sql);
                    $track_stmt->execute([$order_id_post, $new_status, $note ?: "Order status updated to " . ucfirst($new_status)]);
                } catch (PDOException $e) {
                    // Create order_tracking table if it doesn't exist
                    $db->exec("
                        CREATE TABLE IF NOT EXISTS order_tracking (
                            id INT AUTO_INCREMENT PRIMARY KEY,
                            order_id INT NOT NULL,
                            status VARCHAR(50) NOT NULL,
                            description TEXT,
                            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            INDEX idx_order_id (order_id)
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                    ");
                    $track_sql = "INSERT INTO order_tracking (order_id, status, description, created_at) VALUES (?, ?, ?, NOW())";
                    $track_stmt = $db->prepare($track_sql);
                    $track_stmt->execute([$order_id_post, $new_status, $note ?: "Order status updated to " . ucfirst($new_status)]);
                }
                
                // Get order details for notification
                $order_stmt = $db->prepare("SELECT user_id, order_number FROM orders WHERE id = ?");
                $order_stmt->execute([$order_id_post]);
                $order_data = $order_stmt->fetch();
                
                if ($order_data) {
                    // Add notification for customer
                    try {
                        addNotification(
                            $order_data['user_id'],
                            'order_status',
                            'Order Status Updated',
                            'Your order #' . $order_data['order_number'] . ' has been updated to: ' . ucfirst($new_status),
                            '../customer/orders/order-details.php?id=' . $order_id_post
                        );
                    } catch (Exception $e) {
                        // Continue even if notification fails
                    }
                }
                
                logActivity($user_id, "Updated order #" . $order_id_post . " to " . $new_status, 'order', $order_id_post);
                
                $db->commit();
                $success = "Order status updated successfully!";
                
            } catch (Exception $e) {
                $db->rollBack();
                $error = "Failed to update order: " . $e->getMessage();
            }
        } else {
            $error = "Invalid order status.";
        }
    }
    
    if ($action_post == 'delete_order' && $order_id_post > 0) {
        try {
            $db->beginTransaction();
            
            // Check if order exists
            $check = $db->prepare("SELECT id, order_number FROM orders WHERE id = ?");
            $check->execute([$order_id_post]);
            if ($check->rowCount() == 0) {
                throw new Exception("Order not found.");
            }
            
            // Delete related records
            $db->exec("DELETE FROM order_tracking WHERE order_id = " . $order_id_post);
            $db->exec("DELETE FROM order_items WHERE order_id = " . $order_id_post);
            $db->exec("DELETE FROM payments WHERE order_id = " . $order_id_post);
            $db->exec("DELETE FROM orders WHERE id = " . $order_id_post);
            
            logActivity($user_id, "Deleted order #" . $order_id_post, 'order', $order_id_post);
            
            $db->commit();
            $success = "Order deleted successfully!";
            
        } catch (Exception $e) {
            $db->rollBack();
            $error = "Failed to delete order: " . $e->getMessage();
        }
    }
}

// Handle single order action via GET
if ($action == 'view' && $order_id > 0) {
    header("Location: order-details.php?id=" . $order_id);
    exit();
}

// =============================================
// FILTERS AND SEARCH
// =============================================

$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? sanitize($_GET['status']) : '';
$date_from = isset($_GET['date_from']) ? sanitize($_GET['date_from']) : '';
$date_to = isset($_GET['date_to']) ? sanitize($_GET['date_to']) : '';
$payment_filter = isset($_GET['payment']) ? sanitize($_GET['payment']) : '';
$sort = isset($_GET['sort']) ? sanitize($_GET['sort']) : 'newest';

// Pagination
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

// =============================================
// BUILD QUERY
// =============================================

$where_conditions = [];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(o.order_number LIKE ? OR u.full_name LIKE ? OR u.email LIKE ? OR o.shipping_address LIKE ?)";
    $search_term = '%' . $search . '%';
    $params = array_merge($params, [$search_term, $search_term, $search_term, $search_term]);
}

if (!empty($status_filter)) {
    $where_conditions[] = "o.order_status = ?";
    $params[] = $status_filter;
}

if (!empty($payment_filter)) {
    $where_conditions[] = "o.payment_status = ?";
    $params[] = $payment_filter;
}

if (!empty($date_from)) {
    $where_conditions[] = "DATE(o.created_at) >= ?";
    $params[] = $date_from;
}

if (!empty($date_to)) {
    $where_conditions[] = "DATE(o.created_at) <= ?";
    $params[] = $date_to;
}

$where_clause = count($where_conditions) > 0 ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Sort
$sort_clause = "ORDER BY o.created_at DESC";
switch ($sort) {
    case 'oldest':
        $sort_clause = "ORDER BY o.created_at ASC";
        break;
    case 'amount_high':
        $sort_clause = "ORDER BY o.total_amount DESC";
        break;
    case 'amount_low':
        $sort_clause = "ORDER BY o.total_amount ASC";
        break;
    case 'status':
        $sort_clause = "ORDER BY o.order_status ASC";
        break;
}

// =============================================
// FETCH ORDERS
// =============================================

$orders = [];
$total_orders = 0;

try {
    // Count total orders
    $count_sql = "SELECT COUNT(*) as total FROM orders o 
                  LEFT JOIN users u ON o.customer_id = u.id 
                  $where_clause";
    $count_stmt = $db->prepare($count_sql);
    $count_stmt->execute($params);
    $total_orders = $count_stmt->fetch()['total'];
    
    // Fetch orders
    $sql = "SELECT 
                o.*,
                u.full_name as customer_name,
                u.email as customer_email,
                u.phone as customer_phone,
                COALESCE((SELECT COUNT(*) FROM order_items WHERE order_id = o.id), 0) as item_count
            FROM orders o
            LEFT JOIN users u ON o.customer_id = u.id
            $where_clause
            $sort_clause
            LIMIT ? OFFSET ?";
    
    $params[] = $per_page;
    $params[] = $offset;
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $orders = $stmt->fetchAll();
    
} catch (PDOException $e) {
    $error = "Failed to load orders: " . $e->getMessage();
}

// =============================================
// GET ORDER STATUS STATISTICS
// =============================================

$stats = [
    'total' => 0,
    'pending' => 0,
    'confirmed' => 0,
    'processing' => 0,
    'shipped' => 0,
    'delivered' => 0,
    'completed' => 0,
    'cancelled' => 0,
    'paid' => 0,
    'unpaid' => 0
];

try {
    $status_stats = $db->query("SELECT order_status, COUNT(*) as count FROM orders GROUP BY order_status");
    while ($row = $status_stats->fetch()) {
        $stats[$row['order_status']] = $row['count'];
        $stats['total'] += $row['count'];
    }
    
    $payment_stats = $db->query("SELECT payment_status, COUNT(*) as count FROM orders GROUP BY payment_status");
    while ($row = $payment_stats->fetch()) {
        if ($row['payment_status'] == 'paid') {
            $stats['paid'] = $row['count'];
        } else {
            $stats['unpaid'] += $row['count'];
        }
    }
} catch (PDOException $e) {
    // Continue without stats
}

$total_pages = ceil($total_orders / $per_page);

// =============================================
// HELPER FUNCTIONS (if not in functions.php)
// =============================================

if (!function_exists('getStatusBadge')) {
    function getStatusBadge($status) {
        $colors = [
            'pending' => 'warning',
            'confirmed' => 'info',
            'processing' => 'primary',
            'shipped' => 'info',
            'delivered' => 'success',
            'completed' => 'success',
            'cancelled' => 'danger'
        ];
        $color = $colors[$status] ?? 'secondary';
        return '<span class="badge bg-' . $color . '">' . ucfirst($status) . '</span>';
    }
}

if (!function_exists('getPaymentBadge')) {
    function getPaymentBadge($status) {
        if ($status == 'paid') {
            return '<span class="badge bg-success"><i class="fas fa-check-circle me-1"></i>Paid</span>';
        } elseif ($status == 'pending') {
            return '<span class="badge bg-warning"><i class="fas fa-clock me-1"></i>Pending</span>';
        } elseif ($status == 'failed') {
            return '<span class="badge bg-danger"><i class="fas fa-times-circle me-1"></i>Failed</span>';
        } else {
            return '<span class="badge bg-secondary">' . ucfirst($status) . '</span>';
        }
    }
}

if (!function_exists('formatPrice')) {
    function formatPrice($price) {
        if (empty($price) && $price !== 0) {
            return 'TSh 0.00';
        }
        return 'TSh ' . number_format((float)$price, 2);
    }
}

if (!function_exists('addNotification')) {
    function addNotification($user_id, $type, $title, $message, $link = null) {
        try {
            $db = getDB();
            
            // Check if table exists
            try {
                $db->query("SELECT 1 FROM notifications LIMIT 1");
            } catch (PDOException $e) {
                $db->exec("
                    CREATE TABLE IF NOT EXISTS notifications (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        user_id INT NOT NULL,
                        type VARCHAR(50) DEFAULT 'info',
                        title VARCHAR(255) NOT NULL,
                        message TEXT NOT NULL,
                        link VARCHAR(255) NULL,
                        is_read TINYINT(1) DEFAULT 0,
                        read_at TIMESTAMP NULL,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        INDEX idx_user_id (user_id),
                        INDEX idx_is_read (is_read),
                        INDEX idx_created_at (created_at)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                ");
            }
            
            $sql = "INSERT INTO notifications (user_id, type, title, message, link, created_at) VALUES (?, ?, ?, ?, ?, NOW())";
            $stmt = $db->prepare($sql);
            return $stmt->execute([$user_id, $type, $title, $message, $link]);
        } catch (Exception $e) {
            return false;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Orders - TechProcure Tanzania</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f8f9fa;
        }
        
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            width: 280px;
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            color: white;
            z-index: 1030;
            overflow-y: auto;
            transition: all 0.3s;
        }
        
        .sidebar .brand {
            padding: 24px 20px;
            font-size: 1.4rem;
            font-weight: 700;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            text-align: center;
        }
        
        .sidebar .brand i {
            margin-right: 10px;
        }
        
        .sidebar .brand small {
            display: block;
            font-size: 0.7rem;
            opacity: 0.7;
            font-weight: 400;
        }
        
        .sidebar .nav-item {
            padding: 0 12px;
            margin: 4px 0;
        }
        
        .sidebar .nav-link {
            color: rgba(255,255,255,0.8);
            padding: 12px 15px;
            border-radius: 10px;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            text-decoration: none;
        }
        
        .sidebar .nav-link:hover {
            background: rgba(255,255,255,0.1);
            color: white;
        }
        
        .sidebar .nav-link.active {
            background: #0d6efd;
            color: white;
        }
        
        .sidebar .nav-link i {
            width: 24px;
            margin-right: 12px;
        }
        
        .sidebar .sidebar-footer {
            position: absolute;
            bottom: 0;
            width: 100%;
            padding: 15px;
            border-top: 1px solid rgba(255,255,255,0.1);
        }
        
        .sidebar .sidebar-footer a {
            color: rgba(255,255,255,0.7);
            text-decoration: none;
            display: block;
            text-align: center;
            padding: 5px;
        }
        
        .sidebar .sidebar-footer a:hover {
            color: white;
        }
        
        .main-content {
            margin-left: 280px;
            padding: 20px;
            min-height: 100vh;
        }
        
        .top-navbar {
            background: white;
            border-radius: 15px;
            padding: 15px 25px;
            margin-bottom: 24px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .top-navbar .admin-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #0d6efd;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 1.1rem;
        }
        
        .stats-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 24px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            text-align: center;
            transition: all 0.3s;
            position: relative;
        }
        
        .stats-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .stats-card .number {
            font-size: 1.8rem;
            font-weight: 700;
            display: block;
        }
        
        .stats-card .label {
            font-size: 0.85rem;
            color: #6c757d;
        }
        
        .stats-card .icon {
            font-size: 2rem;
            opacity: 0.3;
            position: absolute;
            right: 15px;
            top: 15px;
        }
        
        .filter-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 24px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        
        .table-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        
        .table-card .table-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .table-card .table-header h5 {
            font-weight: 600;
        }
        
        .table-card .table {
            margin-bottom: 0;
        }
        
        .table-card .table th {
            font-weight: 600;
            font-size: 0.8rem;
            text-transform: uppercase;
            color: #6c757d;
            border-top: none;
        }
        
        .table-card .table td {
            vertical-align: middle;
            font-size: 0.9rem;
        }
        
        .order-row {
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .order-row:hover {
            background: #f8f9fa;
        }
        
        .btn-action {
            padding: 4px 10px;
            font-size: 0.8rem;
            border-radius: 8px;
        }
        
        .pagination-info {
            font-size: 0.9rem;
            color: #6c757d;
        }
        
        .status-dropdown .dropdown-item.active {
            background: #0d6efd;
            color: white;
        }
        
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }
            .sidebar.show {
                transform: translateX(0);
            }
            .main-content {
                margin-left: 0;
            }
            .stats-card .number {
                font-size: 1.4rem;
            }
            .table-card .table-header {
                flex-direction: column;
                align-items: flex-start;
            }
        }
    </style>
</head>
<body>

<!-- Sidebar -->
<div class="sidebar" id="sidebar">
    <div class="brand">
        <i class="fas fa-microchip"></i> TechProcure
        <small>Admin Panel</small>
    </div>
    
    <div class="nav flex-column mt-3">
        <div class="nav-item">
            <a href="../dashboard.php" class="nav-link">
                <i class="fas fa-tachometer-alt"></i> Dashboard
            </a>
        </div>
        <div class="nav-item">
            <a href="../users/manage-users.php" class="nav-link">
                <i class="fas fa-users"></i> Users
            </a>
        </div>
        <div class="nav-item">
            <a href="../suppliers/manage-suppliers.php" class="nav-link">
                <i class="fas fa-truck"></i> Suppliers
            </a>
        </div>
        <div class="nav-item">
            <a href="../products/manage-products.php" class="nav-link">
                <i class="fas fa-box"></i> Products
            </a>
        </div>
        <div class="nav-item">
            <a href="manage-orders.php" class="nav-link active">
                <i class="fas fa-shopping-cart"></i> Orders
            </a>
        </div>
        <div class="nav-item">
            <a href="../payments/transactions.php" class="nav-link">
                <i class="fas fa-credit-card"></i> Payments
            </a>
        </div>
        <div class="nav-item">
            <a href="../reports/sales-report.php" class="nav-link">
                <i class="fas fa-chart-line"></i> Reports
            </a>
        </div>
        <div class="nav-item">
            <a href="../settings/general-settings.php" class="nav-link">
                <i class="fas fa-cog"></i> System Settings
            </a>
        </div>
    </div>
    
    <div class="sidebar-footer">
        <a href="../../auth/logout.php">
            <i class="fas fa-sign-out-alt me-2"></i> Logout
        </a>
    </div>
</div>

<!-- Main Content -->
<div class="main-content" id="mainContent">
    <!-- Top Navbar -->
    <div class="top-navbar">
        <div class="welcome-text">
            <i class="fas fa-shopping-cart me-2 text-primary"></i> Manage Orders
        </div>
        <div class="d-flex align-items-center gap-3">
            <span class="badge bg-primary">Total: <?php echo $total_orders; ?> orders</span>
            <div class="admin-avatar">
                <?php echo strtoupper(substr($_SESSION['user_name'] ?? 'A', 0, 1)); ?>
            </div>
        </div>
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
    
    <!-- Statistics -->
    <div class="row g-3">
        <div class="col-md-3 col-6">
            <div class="stats-card position-relative">
                <i class="fas fa-shopping-cart icon"></i>
                <span class="number text-primary"><?php echo $stats['total']; ?></span>
                <span class="label">Total Orders</span>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="stats-card position-relative">
                <i class="fas fa-clock icon"></i>
                <span class="number text-warning"><?php echo $stats['pending']; ?></span>
                <span class="label">Pending</span>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="stats-card position-relative">
                <i class="fas fa-check-circle icon"></i>
                <span class="number text-success"><?php echo $stats['delivered'] + $stats['completed']; ?></span>
                <span class="label">Delivered</span>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="stats-card position-relative">
                <i class="fas fa-times-circle icon"></i>
                <span class="number text-danger"><?php echo $stats['cancelled']; ?></span>
                <span class="label">Cancelled</span>
            </div>
        </div>
    </div>
    
    <!-- Filter -->
    <div class="filter-card">
        <form method="GET" action="" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label small text-muted">Search</label>
                <input type="text" name="search" class="form-control form-control-sm" placeholder="Order #, Customer, Email..." value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label small text-muted">Status</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">All Status</option>
                    <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="confirmed" <?php echo $status_filter == 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                    <option value="processing" <?php echo $status_filter == 'processing' ? 'selected' : ''; ?>>Processing</option>
                    <option value="shipped" <?php echo $status_filter == 'shipped' ? 'selected' : ''; ?>>Shipped</option>
                    <option value="delivered" <?php echo $status_filter == 'delivered' ? 'selected' : ''; ?>>Delivered</option>
                    <option value="completed" <?php echo $status_filter == 'completed' ? 'selected' : ''; ?>>Completed</option>
                    <option value="cancelled" <?php echo $status_filter == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small text-muted">Payment</label>
                <select name="payment" class="form-select form-select-sm">
                    <option value="">All</option>
                    <option value="paid" <?php echo $payment_filter == 'paid' ? 'selected' : ''; ?>>Paid</option>
                    <option value="pending" <?php echo $payment_filter == 'pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="failed" <?php echo $payment_filter == 'failed' ? 'selected' : ''; ?>>Failed</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small text-muted">Date From</label>
                <input type="date" name="date_from" class="form-control form-control-sm" value="<?php echo htmlspecialchars($date_from); ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label small text-muted">Date To</label>
                <input type="date" name="date_to" class="form-control form-control-sm" value="<?php echo htmlspecialchars($date_to); ?>">
            </div>
            <div class="col-md-1">
                <button type="submit" class="btn btn-primary btn-sm w-100">
                    <i class="fas fa-search"></i>
                </button>
            </div>
        </form>
    </div>
    
    <!-- Orders Table -->
    <div class="table-card">
        <div class="table-header">
            <h5><i class="fas fa-list me-2"></i>Orders</h5>
            <div class="d-flex align-items-center gap-2">
                <select class="form-select form-select-sm" style="width: auto;" onchange="window.location.href=this.value">
                    <option value="?sort=newest" <?php echo $sort == 'newest' ? 'selected' : ''; ?>>Newest</option>
                    <option value="?sort=oldest" <?php echo $sort == 'oldest' ? 'selected' : ''; ?>>Oldest</option>
                    <option value="?sort=amount_high" <?php echo $sort == 'amount_high' ? 'selected' : ''; ?>>Highest Amount</option>
                    <option value="?sort=amount_low" <?php echo $sort == 'amount_low' ? 'selected' : ''; ?>>Lowest Amount</option>
                    <option value="?sort=status" <?php echo $sort == 'status' ? 'selected' : ''; ?>>By Status</option>
                </select>
                <button class="btn btn-sm btn-outline-secondary" onclick="window.print()">
                    <i class="fas fa-print"></i>
                </button>
            </div>
        </div>
        
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Order #</th>
                        <th>Customer</th>
                        <th>Items</th>
                        <th>Total</th>
                        <th>Status</th>
                        <th>Payment</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(count($orders) > 0): ?>
                        <?php foreach($orders as $order): ?>
                            <tr class="order-row" data-order-id="<?php echo $order['id']; ?>">
                                <td>
                                    <strong>#<?php echo htmlspecialchars($order['order_number']); ?></strong>
                                </td>
                                <td>
                                    <div><?php echo htmlspecialchars($order['customer_name'] ?? 'N/A'); ?></div>
                                    <small class="text-muted"><?php echo htmlspecialchars($order['customer_email'] ?? ''); ?></small>
                                </td>
                                <td><?php echo $order['item_count'] ?? 0; ?></td>
                                <td><strong><?php echo formatPrice($order['total_amount']); ?></strong></td>
                                <td><?php echo getStatusBadge($order['order_status']); ?></td>
                                <td><?php echo getPaymentBadge($order['payment_status']); ?></td>
                                <td>
                                    <small><?php echo date('M d, Y', strtotime($order['created_at'])); ?></small>
                                    <br>
                                    <small class="text-muted"><?php echo date('h:i A', strtotime($order['created_at'])); ?></small>
                                </td>
                                <td>
                                    <div class="d-flex gap-1">
                                        <a href="order-details.php?id=<?php echo $order['id']; ?>" class="btn btn-sm btn-outline-primary btn-action">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <button type="button" class="btn btn-sm btn-outline-success btn-action" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#statusModal" 
                                                data-order-id="<?php echo $order['id']; ?>"
                                                data-order-number="<?php echo htmlspecialchars($order['order_number']); ?>"
                                                data-current-status="<?php echo $order['order_status']; ?>">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-danger btn-action" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#deleteModal" 
                                                data-order-id="<?php echo $order['id']; ?>"
                                                data-order-number="<?php echo htmlspecialchars($order['order_number']); ?>">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" class="text-center py-4">
                                <i class="fas fa-inbox fa-3x text-muted mb-3 d-block"></i>
                                <p class="text-muted">No orders found</p>
                                <?php if(!empty($search) || !empty($status_filter) || !empty($payment_filter)): ?>
                                    <a href="manage-orders.php" class="btn btn-sm btn-outline-primary">Clear Filters</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Pagination -->
        <?php if($total_pages > 1): ?>
            <div class="d-flex justify-content-between align-items-center mt-3">
                <div class="pagination-info">
                    Showing <?php echo $offset + 1; ?> - <?php echo min($offset + $per_page, $total_orders); ?> of <?php echo $total_orders; ?> orders
                </div>
                <nav>
                    <ul class="pagination pagination-sm mb-0">
                        <?php if($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $page-1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>&payment=<?php echo urlencode($payment_filter); ?>&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>&sort=<?php echo urlencode($sort); ?>">
                                    <i class="fas fa-chevron-left"></i>
                                </a>
                            </li>
                        <?php endif; ?>
                        
                        <?php for($i = max(1, $page - 2); $i <= min($page + 2, $total_pages); $i++): ?>
                            <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>&payment=<?php echo urlencode($payment_filter); ?>&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>&sort=<?php echo urlencode($sort); ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                        <?php endfor; ?>
                        
                        <?php if($page < $total_pages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $page+1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>&payment=<?php echo urlencode($payment_filter); ?>&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>&sort=<?php echo urlencode($sort); ?>">
                                    <i class="fas fa-chevron-right"></i>
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Status Update Modal -->
<div class="modal fade" id="statusModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Update Order Status</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="action" value="update_status">
                <input type="hidden" name="order_id" id="statusOrderId" value="">
                <div class="modal-body">
                    <p>Update status for order <strong id="statusOrderNumber"></strong></p>
                    <div class="mb-3">
                        <label class="form-label">New Status</label>
                        <select name="status" class="form-select" required>
                            <option value="pending">Pending</option>
                            <option value="confirmed">Confirmed</option>
                            <option value="processing">Processing</option>
                            <option value="shipped">Shipped</option>
                            <option value="delivered">Delivered</option>
                            <option value="completed">Completed</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Note (Optional)</label>
                        <textarea name="note" class="form-control" rows="2" placeholder="Add a note about this status update..."></textarea>
                    </div>
                    <div class="alert alert-info small">
                        <i class="fas fa-info-circle me-1"></i> Customer will be notified of this status change.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Status</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title"><i class="fas fa-exclamation-triangle me-2"></i>Delete Order</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="action" value="delete_order">
                <input type="hidden" name="order_id" id="deleteOrderId" value="">
                <div class="modal-body">
                    <p class="text-danger"><strong>Warning:</strong> This action cannot be undone!</p>
                    <p>Are you sure you want to delete order <strong id="deleteOrderNumber"></strong>?</p>
                    <p class="small text-muted">This will permanently remove all order data including items, tracking, and payments.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Delete Permanently</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Footer -->
<footer style="background: #1a1a2e; color: white; padding: 40px 0 20px; margin-top: 50px;">
    <div class="container">
        <div class="row">
            <div class="col-md-4 mb-4">
                <h5><i class="fas fa-microchip me-2"></i>TechProcure Tanzania</h5>
                <p style="color: rgba(255,255,255,0.6);">Enterprise B2B IT equipment procurement platform with transparent pricing and bulk discounts for corporate buyers across Tanzania.</p>
            </div>
            <div class="col-md-2 mb-4">
                <h6>Quick Links</h6>
                <ul class="list-unstyled">
                    <li><a href="../../index.php" style="color: rgba(255,255,255,0.6); text-decoration: none;">Home</a></li>
                    <li><a href="../../products.php" style="color: rgba(255,255,255,0.6); text-decoration: none;">Products</a></li>
                    <li><a href="../../suppliers.php" style="color: rgba(255,255,255,0.6); text-decoration: none;">Suppliers</a></li>
                </ul>
            </div>
            <div class="col-md-3 mb-4">
                <h6>Admin</h6>
                <ul class="list-unstyled">
                    <li><a href="../dashboard.php" style="color: rgba(255,255,255,0.6); text-decoration: none;">Dashboard</a></li>
                    <li><a href="../settings/general-settings.php" style="color: rgba(255,255,255,0.6); text-decoration: none;">Settings</a></li>
                    <li><a href="../reports/sales-report.php" style="color: rgba(255,255,255,0.6); text-decoration: none;">Reports</a></li>
                </ul>
            </div>
            <div class="col-md-3 mb-4">
                <h6>Contact Us</h6>
                <ul class="list-unstyled" style="color: rgba(255,255,255,0.6);">
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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // =============================================
    // STATUS MODAL - Populate data
    // =============================================
    $('#statusModal').on('show.bs.modal', function(event) {
        const button = $(event.relatedTarget);
        const orderId = button.data('order-id');
        const orderNumber = button.data('order-number');
        const currentStatus = button.data('current-status');
        
        const modal = $(this);
        modal.find('#statusOrderId').val(orderId);
        modal.find('#statusOrderNumber').text('#' + orderNumber);
        modal.find('select[name="status"]').val(currentStatus);
    });
    
    // =============================================
    // DELETE MODAL - Populate data
    // =============================================
    $('#deleteModal').on('show.bs.modal', function(event) {
        const button = $(event.relatedTarget);
        const orderId = button.data('order-id');
        const orderNumber = button.data('order-number');
        
        const modal = $(this);
        modal.find('#deleteOrderId').val(orderId);
        modal.find('#deleteOrderNumber').text('#' + orderNumber);
    });
    
    // =============================================
    // AUTO-HIDE ALERTS
    // =============================================
    setTimeout(function() {
        const alerts = document.querySelectorAll('.alert:not(.alert-permanent)');
        alerts.forEach(function(alert) {
            const bsAlert = new bootstrap.Alert(alert);
            setTimeout(function() {
                bsAlert.close();
            }, 500);
        });
    }, 5000);
    
    // =============================================
    // ROW CLICK - View Order Details
    // =============================================
    document.querySelectorAll('.order-row').forEach(function(row) {
        row.addEventListener('click', function(e) {
            // Don't navigate if clicking on action buttons
            if (e.target.closest('.btn')) {
                return;
            }
            const orderId = this.dataset.orderId;
            if (orderId) {
                window.location.href = 'order-details.php?id=' + orderId;
            }
        });
    });
</script>

</body>
</html>