<?php
/**
 * TechProcure Tanzania - Admin Order Details
 * File: admin/orders/order-details.php
 * Description: Admin can view detailed order information with items, tracking, and status management
 */

// Start session if not started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include configuration files
require_once '../../includes/config.php';
require_once '../../includes/db.php';
require_once '../../includes/functions.php';


// Check if user is admin
requireAdmin();

// Get database connection
$db = getDB();
$user_id = $_SESSION['user_id'];

// Get order ID from URL
$order_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($order_id <= 0) {
    $_SESSION['error'] = "Invalid order ID.";
    header("Location: manage-orders.php");
    exit();
}

// =============================================
// FETCH ORDER DETAILS
// =============================================

$order = null;
$error = '';

try {
    $sql = "SELECT o.*, 
                   u.full_name as customer_name, u.email as customer_email, u.phone as customer_phone,
                   u.company_name as customer_company,
                   s.company_name as supplier_name, s.phone as supplier_phone,
                   (SELECT COUNT(*) FROM order_items WHERE order_id = o.id) as item_count
            FROM orders o
            LEFT JOIN users u ON o.user_id = u.id
            LEFT JOIN suppliers s ON o.supplier_id = s.id
            WHERE o.id = ?";
    $stmt = $db->prepare($sql);
    $stmt->execute([$order_id]);
    $order = $stmt->fetch();

    if (!$order) {
        $error = "Order not found.";
    }
} catch (PDOException $e) {
    $error = "Failed to load order details.";
}

// =============================================
// FETCH ORDER ITEMS
// =============================================

$order_items = [];
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
        if ($stmt->rowCount() > 0) {
            $order_items = $stmt->fetchAll();
        }
    } catch (PDOException $e) {
        $order_items = [];
    }
}

// =============================================
// FETCH ORDER TRACKING
// =============================================

$order_tracking = [];
if ($order) {
    try {
        $sql = "SELECT * FROM order_tracking WHERE order_id = ? ORDER BY created_at ASC";
        $stmt = $db->prepare($sql);
        $stmt->execute([$order_id]);
        if ($stmt->rowCount() > 0) {
            $order_tracking = $stmt->fetchAll();
        }
    } catch (PDOException $e) {
        $order_tracking = [];
    }
}

// =============================================
// FETCH PAYMENTS
// =============================================

$payments = [];
if ($order) {
    try {
        $sql = "SELECT * FROM payments WHERE order_id = ? ORDER BY created_at DESC";
        $stmt = $db->prepare($sql);
        $stmt->execute([$order_id]);
        if ($stmt->rowCount() > 0) {
            $payments = $stmt->fetchAll();
        }
    } catch (PDOException $e) {
        $payments = [];
    }
}

// =============================================
// FETCH ESCROW
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
// FETCH INVOICES
// =============================================

$invoices = [];
if ($order) {
    try {
        $sql = "SELECT * FROM invoices WHERE order_id = ? ORDER BY created_at DESC";
        $stmt = $db->prepare($sql);
        $stmt->execute([$order_id]);
        if ($stmt->rowCount() > 0) {
            $invoices = $stmt->fetchAll();
        }
    } catch (PDOException $e) {
        $invoices = [];
    }
}

// =============================================
// HANDLE ORDER ACTIONS
// =============================================

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action == 'update_status') {
        $status = $_POST['status'] ?? '';
        $tracking = trim($_POST['tracking_number'] ?? '');
        $notes = trim($_POST['internal_notes'] ?? '');
        
        if (!empty($status)) {
            try {
                $db->beginTransaction();
                
                // Update order
                $stmt = $db->prepare("UPDATE orders SET order_status = ?, tracking_number = ?, internal_notes = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$status, $tracking, $notes, $order_id]);
                
                // Add tracking entry
                $track_sql = "INSERT INTO order_tracking (order_id, status, description, created_at) VALUES (?, ?, ?, NOW())";
                $track_stmt = $db->prepare($track_sql);
                $track_stmt->execute([$order_id, $status, 'Order status updated to ' . ucfirst($status)]);
                
                // If order is delivered or completed, update escrow
                if ($status == 'delivered' || $status == 'completed') {
                    $escrow_sql = "UPDATE escrow_payments SET status = 'released', release_date = NOW() WHERE order_id = ?";
                    $escrow_stmt = $db->prepare($escrow_sql);
                    $escrow_stmt->execute([$order_id]);
                }
                
                // If order is cancelled, update escrow
                if ($status == 'cancelled') {
                    $escrow_sql = "UPDATE escrow_payments SET status = 'refunded', refund_date = NOW() WHERE order_id = ?";
                    $escrow_stmt = $db->prepare($escrow_sql);
                    $escrow_stmt->execute([$order_id]);
                }
                
                // Create notification for customer
                $notif_sql = "INSERT INTO notifications (user_id, type, title, message, link, created_at) 
                              VALUES (?, 'order', ?, ?, ?, NOW())";
                $notif_stmt = $db->prepare($notif_sql);
                $notif_stmt->execute([
                    $order['user_id'],
                    'Order Status Updated',
                    'Your order ' . $order['order_number'] . ' has been updated to ' . ucfirst($status),
                    '../customer/orders/order-details.php?id=' . $order_id
                ]);
                
                $db->commit();
                
                logActivity($user_id, 'Updated Order Status', 'order', $order_id);
                $_SESSION['success'] = "Order status updated successfully!";
                header("Location: order-details.php?id=" . $order_id);
                exit();
                
            } catch (PDOException $e) {
                $db->rollBack();
                $_SESSION['error'] = "Failed to update order: " . $e->getMessage();
            }
        }
        header("Location: order-details.php?id=" . $order_id);
        exit();
    }
    
    if ($action == 'delete_order') {
        try {
            $stmt = $db->prepare("DELETE FROM orders WHERE id = ?");
            $stmt->execute([$order_id]);
            logActivity($user_id, 'Deleted Order', 'order', $order_id);
            $_SESSION['success'] = "Order deleted successfully!";
            header("Location: manage-orders.php");
            exit();
        } catch (PDOException $e) {
            $_SESSION['error'] = "Failed to delete order.";
        }
        header("Location: order-details.php?id=" . $order_id);
        exit();
    }
}

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

if (!function_exists('getPaymentBadge')) {
    function getPaymentBadge($status) {
        $colors = [
            'pending' => 'warning',
            'paid' => 'success',
            'failed' => 'danger',
            'refunded' => 'secondary',
            'partial' => 'info'
        ];
        return $colors[$status] ?? 'secondary';
    }
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
        
        /* Sidebar */
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
        
        /* Main Content */
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
        
        .detail-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 24px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        
        .detail-card .card-title {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #e9ecef;
        }
        
        .detail-card .card-title i {
            margin-right: 8px;
            color: #0d6efd;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        
        .info-item .label {
            font-size: 0.75rem;
            color: #6c757d;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .info-item .value {
            font-weight: 500;
            font-size: 1rem;
        }
        
        .order-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 10px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .order-item:last-child {
            border-bottom: none;
        }
        
        .order-item img {
            width: 50px;
            height: 50px;
            object-fit: contain;
            background: #f8f9fa;
            border-radius: 8px;
            padding: 5px;
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
        
        .btn-update {
            background: linear-gradient(135deg, #0d6efd, #0b5ed7);
            border: none;
            border-radius: 10px;
            padding: 10px 25px;
            font-weight: 600;
            color: white;
            transition: all 0.3s;
        }
        
        .btn-update:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(13,110,253,0.3);
        }
        
        .btn-delete {
            background: linear-gradient(135deg, #dc3545, #c82333);
            border: none;
            border-radius: 10px;
            padding: 10px 25px;
            font-weight: 600;
            color: white;
            transition: all 0.3s;
        }
        
        .btn-delete:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(220,53,69,0.3);
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
            .info-grid {
                grid-template-columns: 1fr;
            }
            .order-item {
                flex-wrap: wrap;
            }
            .order-item img {
                width: 40px;
                height: 40px;
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
            <a href="manage-orders.php" class="nav-link">
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
                <i class="fas fa-cog"></i> Settings
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
            <i class="fas fa-shopping-cart me-2 text-primary"></i> Order Details
        </div>
        <div class="d-flex align-items-center gap-3">
            <a href="manage-orders.php" class="btn btn-secondary btn-sm">
                <i class="fas fa-arrow-left me-1"></i> Back to Orders
            </a>
            <div class="admin-avatar">
                <?php echo strtoupper(substr($_SESSION['user_name'] ?? 'A', 0, 1)); ?>
            </div>
        </div>
    </div>
    
    <?php if($error): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-triangle me-2"></i> <?php echo $error; ?>
            <a href="manage-orders.php" class="btn btn-primary btn-sm mt-2">Back to Orders</a>
        </div>
    <?php elseif($order): ?>
    
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
    
    <!-- Order Summary -->
    <div class="detail-card">
        <div class="card-title">
            <i class="fas fa-info-circle"></i> Order Summary
            <span class="badge bg-<?php echo getStatusBadge($order['order_status']); ?> ms-2 fs-6">
                <?php echo ucfirst($order['order_status']); ?>
            </span>
            <span class="badge bg-<?php echo getPaymentBadge($order['payment_status']); ?> ms-1 fs-6">
                <?php echo ucfirst($order['payment_status']); ?>
            </span>
        </div>
        <div class="info-grid">
            <div class="info-item">
                <div class="label">Order Number</div>
                <div class="value"><code><?php echo htmlspecialchars($order['order_number']); ?></code></div>
            </div>
            <div class="info-item">
                <div class="label">Invoice Number</div>
                <div class="value"><?php echo htmlspecialchars($order['invoice_number'] ?? 'N/A'); ?></div>
            </div>
            <div class="info-item">
                <div class="label">Order Date</div>
                <div class="value"><?php echo formatDateTime($order['created_at']); ?></div>
            </div>
            <div class="info-item">
                <div class="label">Total Amount</div>
                <div class="value text-primary fw-bold"><?php echo formatPrice($order['total_amount']); ?></div>
            </div>
            <div class="info-item">
                <div class="label">Payment Method</div>
                <div class="value"><?php echo ucfirst(str_replace('_', ' ', $order['payment_method'] ?? 'N/A')); ?></div>
            </div>
            <div class="info-item">
                <div class="label">Tracking Number</div>
                <div class="value"><?php echo htmlspecialchars($order['tracking_number'] ?? 'N/A'); ?></div>
            </div>
        </div>
    </div>
    
    <!-- Customer & Supplier Info -->
    <div class="row">
        <div class="col-md-6">
            <div class="detail-card">
                <div class="card-title">
                    <i class="fas fa-user"></i> Customer Information
                </div>
                <div class="info-grid">
                    <div class="info-item">
                        <div class="label">Name</div>
                        <div class="value"><?php echo htmlspecialchars($order['customer_name'] ?? 'N/A'); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="label">Email</div>
                        <div class="value"><?php echo htmlspecialchars($order['customer_email'] ?? 'N/A'); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="label">Phone</div>
                        <div class="value"><?php echo htmlspecialchars($order['customer_phone'] ?? 'N/A'); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="label">Company</div>
                        <div class="value"><?php echo htmlspecialchars($order['customer_company'] ?? 'N/A'); ?></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="detail-card">
                <div class="card-title">
                    <i class="fas fa-store"></i> Supplier Information
                </div>
                <div class="info-grid">
                    <div class="info-item">
                        <div class="label">Supplier</div>
                        <div class="value"><?php echo htmlspecialchars($order['supplier_name'] ?? 'N/A'); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="label">Phone</div>
                        <div class="value"><?php echo htmlspecialchars($order['supplier_phone'] ?? 'N/A'); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="label">Items</div>
                        <div class="value"><?php echo $order['item_count']; ?> products</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Order Items -->
    <div class="detail-card">
        <div class="card-title">
            <i class="fas fa-box"></i> Order Items (<?php echo count($order_items); ?> items)
        </div>
        <?php if(!empty($order_items)): ?>
            <?php foreach($order_items as $item): ?>
            <div class="order-item">
                <?php 
                $image_path = !empty($item['primary_image']) ? $item['primary_image'] : '../../assets/images/placeholder-product.jpg';
                if (!empty($item['primary_image']) && strpos($item['primary_image'], 'uploads/') === false) {
                    $image_path = '../../uploads/products/' . $item['product_id'] . '/' . $item['primary_image'];
                }
                ?>
                <img src="<?php echo $image_path; ?>" alt="<?php echo htmlspecialchars($item['product_name']); ?>" onerror="this.src='../../assets/images/placeholder-product.jpg'">
                <div class="flex-grow-1">
                    <div class="fw-bold"><?php echo htmlspecialchars($item['product_name']); ?></div>
                    <div class="text-muted small">SKU: <?php echo htmlspecialchars($item['sku'] ?? 'N/A'); ?> | Qty: <?php echo $item['quantity']; ?></div>
                </div>
                <div class="text-end">
                    <div class="text-muted small">Unit Price</div>
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
            <div class="detail-card">
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
            <div class="detail-card">
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
    <div class="detail-card">
        <div class="card-title">
            <i class="fas fa-sticky-note"></i> Order Notes
        </div>
        <p class="mb-0"><?php echo nl2br(htmlspecialchars($order['notes'])); ?></p>
    </div>
    <?php endif; ?>
    
    <!-- Internal Notes -->
    <?php if($order['internal_notes']): ?>
    <div class="detail-card" style="border-left: 4px solid #ffc107;">
        <div class="card-title">
            <i class="fas fa-user-secret"></i> Internal Notes (Admin Only)
        </div>
        <p class="mb-0"><?php echo nl2br(htmlspecialchars($order['internal_notes'])); ?></p>
    </div>
    <?php endif; ?>
    
    <!-- Order Tracking -->
    <div class="detail-card">
        <div class="card-title">
            <i class="fas fa-history"></i> Order Tracking
        </div>
        <?php if(!empty($order_tracking)): ?>
            <div class="status-timeline">
                <?php foreach($order_tracking as $track): ?>
                <div class="timeline-item completed">
                    <div class="timeline-dot"></div>
                    <div class="timeline-content">
                        <div class="title"><?php echo ucfirst($track['status']); ?></div>
                        <div class="text-muted small"><?php echo htmlspecialchars($track['description']); ?></div>
                        <div class="time"><?php echo formatDateTime($track['created_at']); ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p class="text-muted">No tracking information available.</p>
        <?php endif; ?>
    </div>
    
    <!-- Payments -->
    <?php if(!empty($payments)): ?>
    <div class="detail-card">
        <div class="card-title">
            <i class="fas fa-credit-card"></i> Payments
        </div>
        <?php foreach($payments as $payment): ?>
        <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
            <div>
                <div class="fw-bold"><?php echo htmlspecialchars($payment['payment_number']); ?></div>
                <div class="text-muted small"><?php echo formatDateTime($payment['created_at']); ?></div>
            </div>
            <div>
                <span class="badge bg-<?php echo getPaymentBadge($payment['payment_status']); ?>">
                    <?php echo ucfirst($payment['payment_status']); ?>
                </span>
                <span class="fw-bold ms-2"><?php echo formatPrice($payment['amount']); ?></span>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
    
    <!-- Escrow -->
    <?php if($escrow): ?>
    <div class="detail-card">
        <div class="card-title">
            <i class="fas fa-lock"></i> Escrow Payment
        </div>
        <div class="info-grid">
            <div class="info-item">
                <div class="label">Amount</div>
                <div class="value"><?php echo formatPrice($escrow['amount']); ?></div>
            </div>
            <div class="info-item">
                <div class="label">Status</div>
                <div class="value">
                    <?php if($escrow['status'] == 'pending'): ?>
                        <span class="badge bg-warning">Pending Release</span>
                    <?php elseif($escrow['status'] == 'released'): ?>
                        <span class="badge bg-success">Released</span>
                    <?php elseif($escrow['status'] == 'refunded'): ?>
                        <span class="badge bg-secondary">Refunded</span>
                    <?php else: ?>
                        <span class="badge bg-danger"><?php echo ucfirst($escrow['status']); ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="info-item">
                <div class="label">Release Date</div>
                <div class="value"><?php echo $escrow['release_date'] ? formatDateTime($escrow['release_date']) : 'Not released'; ?></div>
            </div>
            <div class="info-item">
                <div class="label">Refund Date</div>
                <div class="value"><?php echo $escrow['refund_date'] ? formatDateTime($escrow['refund_date']) : 'N/A'; ?></div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Invoices -->
    <?php if(!empty($invoices)): ?>
    <div class="detail-card">
        <div class="card-title">
            <i class="fas fa-file-invoice"></i> Invoices
        </div>
        <?php foreach($invoices as $invoice): ?>
        <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
            <div>
                <div class="fw-bold"><?php echo htmlspecialchars($invoice['invoice_number']); ?></div>
                <div class="text-muted small">Issued: <?php echo formatDate($invoice['invoice_date']); ?></div>
            </div>
            <div>
                <span class="badge bg-<?php echo $invoice['status'] == 'paid' ? 'success' : 'secondary'; ?>">
                    <?php echo ucfirst($invoice['status']); ?>
                </span>
                <span class="fw-bold ms-2"><?php echo formatPrice($invoice['total_amount']); ?></span>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
    
    <!-- Actions -->
    <div class="detail-card">
        <div class="card-title">
            <i class="fas fa-cog"></i> Actions
        </div>
        <div class="d-flex flex-wrap gap-3">
            <button type="button" class="btn-update" data-bs-toggle="modal" data-bs-target="#statusModal">
                <i class="fas fa-edit me-2"></i> Update Status
            </button>
            
            <?php if($order['payment_status'] == 'pending'): ?>
                <a href="../../payment/pay-invoice.php?order=<?php echo $order['id']; ?>" class="btn btn-success">
                    <i class="fas fa-credit-card me-2"></i> Process Payment
                </a>
            <?php endif; ?>
            
            <?php if($order['order_status'] == 'pending'): ?>
                <a href="?action=cancel&id=<?php echo $order['id']; ?>" class="btn btn-warning" onclick="return confirm('Cancel this order?')">
                    <i class="fas fa-times me-2"></i> Cancel Order
                </a>
            <?php endif; ?>
            
            <button type="button" class="btn-delete" onclick="confirmDelete()">
                <i class="fas fa-trash me-2"></i> Delete Order
            </button>
            
            <a href="manage-orders.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left me-2"></i> Back to Orders
            </a>
        </div>
    </div>
    
    <?php endif; ?>
</div>

<!-- Status Update Modal -->
<div class="modal fade" id="statusModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Update Order Status</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="update_status">
                    <input type="hidden" name="order_id" value="<?php echo $order_id; ?>">
                    
                    <div class="mb-3">
                        <label class="form-label">Order Status</label>
                        <select name="status" class="form-select">
                            <option value="pending" <?php echo $order['order_status'] == 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="processing" <?php echo $order['order_status'] == 'processing' ? 'selected' : ''; ?>>Processing</option>
                            <option value="shipped" <?php echo $order['order_status'] == 'shipped' ? 'selected' : ''; ?>>Shipped</option>
                            <option value="delivered" <?php echo $order['order_status'] == 'delivered' ? 'selected' : ''; ?>>Delivered</option>
                            <option value="completed" <?php echo $order['order_status'] == 'completed' ? 'selected' : ''; ?>>Completed</option>
                            <option value="cancelled" <?php echo $order['order_status'] == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Tracking Number</label>
                        <input type="text" name="tracking_number" class="form-control" placeholder="Enter tracking number" value="<?php echo htmlspecialchars($order['tracking_number'] ?? ''); ?>">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Internal Notes</label>
                        <textarea name="internal_notes" class="form-control" rows="3" placeholder="Add internal notes..."><?php echo htmlspecialchars($order['internal_notes'] ?? ''); ?></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i> Update Status
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Confirmation Form -->
<form method="POST" id="deleteForm" style="display:none;">
    <input type="hidden" name="action" value="delete_order">
</form>

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
                <h6>Admin</h6>
                <ul class="list-unstyled">
                    <li><a href="../dashboard.php">Dashboard</a></li>
                    <li><a href="manage-orders.php">Orders</a></li>
                    <li><a href="../payments/transactions.php">Payments</a></li>
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

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<script>
    function confirmDelete() {
        if (confirm('Are you sure you want to delete this order permanently?')) {
            document.getElementById('deleteForm').submit();
        }
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