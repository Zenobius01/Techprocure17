<?php
/**
 * TechProcure Tanzania - Admin Escrow Payments
 * File: admin/payments/escrow-payments.php
 * Description: Admin can view, manage, and release escrow payments
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

// =============================================
// HANDLE ESCROW ACTIONS
// =============================================

// Release escrow payment
if (isset($_GET['release']) && is_numeric($_GET['release'])) {
    $escrow_id = (int)$_GET['release'];
    try {
        $db->beginTransaction();
        
        // Get escrow details
        $escrow_stmt = $db->prepare("SELECT * FROM escrow_payments WHERE id = ?");
        $escrow_stmt->execute([$escrow_id]);
        $escrow = $escrow_stmt->fetch();
        
        if ($escrow && $escrow['status'] == 'pending') {
            // Update escrow
            $stmt = $db->prepare("UPDATE escrow_payments SET status = 'released', release_date = NOW() WHERE id = ?");
            $stmt->execute([$escrow_id]);
            
            // Update order payment status
            $order_stmt = $db->prepare("UPDATE orders SET payment_status = 'paid' WHERE id = ?");
            $order_stmt->execute([$escrow['order_id']]);
            
            // Log activity
            logActivity($user_id, 'Released Escrow Payment', 'escrow', $escrow_id);
            
            // Create notification for customer
            $notif_sql = "INSERT INTO notifications (user_id, type, title, message, link, created_at) 
                          VALUES ((SELECT user_id FROM orders WHERE id = ?), 'escrow', ?, ?, ?, NOW())";
            $notif_stmt = $db->prepare($notif_sql);
            $notif_stmt->execute([
                $escrow['order_id'],
                'Escrow Released',
                'Your escrow payment of ' . formatPrice($escrow['amount']) . ' has been released to the supplier.',
                '../customer/orders/order-details.php?id=' . $escrow['order_id']
            ]);
            
            $_SESSION['success'] = "Escrow payment released successfully!";
        } else {
            $_SESSION['error'] = "Escrow payment not found or already released.";
        }
        
        $db->commit();
    } catch (PDOException $e) {
        $db->rollBack();
        $_SESSION['error'] = "Failed to release escrow: " . $e->getMessage();
    }
    header("Location: escrow-payments.php");
    exit();
}

// Refund escrow payment
if (isset($_GET['refund']) && is_numeric($_GET['refund'])) {
    $escrow_id = (int)$_GET['refund'];
    try {
        $db->beginTransaction();
        
        // Get escrow details
        $escrow_stmt = $db->prepare("SELECT * FROM escrow_payments WHERE id = ?");
        $escrow_stmt->execute([$escrow_id]);
        $escrow = $escrow_stmt->fetch();
        
        if ($escrow && $escrow['status'] == 'pending') {
            // Update escrow
            $stmt = $db->prepare("UPDATE escrow_payments SET status = 'refunded', refund_date = NOW() WHERE id = ?");
            $stmt->execute([$escrow_id]);
            
            // Update order payment status
            $order_stmt = $db->prepare("UPDATE orders SET payment_status = 'refunded' WHERE id = ?");
            $order_stmt->execute([$escrow['order_id']]);
            
            // Update order status
            $order_stmt = $db->prepare("UPDATE orders SET order_status = 'cancelled', cancelled_at = NOW() WHERE id = ?");
            $order_stmt->execute([$escrow['order_id']]);
            
            // Log activity
            logActivity($user_id, 'Refunded Escrow Payment', 'escrow', $escrow_id);
            
            // Create notification for customer
            $notif_sql = "INSERT INTO notifications (user_id, type, title, message, link, created_at) 
                          VALUES ((SELECT user_id FROM orders WHERE id = ?), 'escrow', ?, ?, ?, NOW())";
            $notif_stmt = $db->prepare($notif_sql);
            $notif_stmt->execute([
                $escrow['order_id'],
                'Escrow Refunded',
                'Your escrow payment of ' . formatPrice($escrow['amount']) . ' has been refunded.',
                '../customer/orders/order-details.php?id=' . $escrow['order_id']
            ]);
            
            $_SESSION['success'] = "Escrow payment refunded successfully!";
        } else {
            $_SESSION['error'] = "Escrow payment not found or already processed.";
        }
        
        $db->commit();
    } catch (PDOException $e) {
        $db->rollBack();
        $_SESSION['error'] = "Failed to refund escrow: " . $e->getMessage();
    }
    header("Location: escrow-payments.php");
    exit();
}

// =============================================
// GET FILTER PARAMETERS
// =============================================

$filter = isset($_GET['filter']) ? trim($_GET['filter']) : 'all';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// =============================================
// BUILD ESCROW QUERY
// =============================================

$where_conditions = ["1=1"];
$params = [];

// Status filter
if ($filter != 'all') {
    $where_conditions[] = "e.status = ?";
    $params[] = $filter;
}

// Search filter
if (!empty($search)) {
    $where_conditions[] = "(o.order_number LIKE ? OR u.full_name LIKE ? OR u.email LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

$where_clause = implode(" AND ", $where_conditions);

// =============================================
// COUNT TOTAL ESCROW PAYMENTS
// =============================================

$total_escrow = 0;
$total_pages = 1;

try {
    $count_sql = "SELECT COUNT(*) as total FROM escrow_payments e 
                  LEFT JOIN orders o ON e.order_id = o.id
                  LEFT JOIN users u ON o.user_id = u.id
                  WHERE $where_clause";
    $stmt = $db->prepare($count_sql);
    $stmt->execute($params);
    $total_escrow = (int)$stmt->fetchColumn();
    $total_pages = ceil($total_escrow / $limit);
} catch (PDOException $e) {
    $total_escrow = 0;
    $total_pages = 1;
}

// =============================================
// GET ESCROW PAYMENTS
// =============================================

$escrow_payments = [];
try {
    $sql = "SELECT e.*, 
                   o.order_number, 
                   u.full_name as customer_name, u.email as customer_email,
                   (SELECT COUNT(*) FROM escrow_transactions WHERE escrow_id = e.id) as transaction_count
            FROM escrow_payments e
            LEFT JOIN orders o ON e.order_id = o.id
            LEFT JOIN users u ON o.user_id = u.id
            WHERE $where_clause
            ORDER BY e.created_at DESC
            LIMIT ? OFFSET ?";
    
    $params[] = $limit;
    $params[] = $offset;
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    
    if ($stmt->rowCount() > 0) {
        $escrow_payments = $stmt->fetchAll();
    }
} catch (PDOException $e) {
    $escrow_payments = [];
}

// =============================================
// GET STATISTICS
// =============================================

$stats = [];
try {
    // Total escrow balance
    $stmt = $db->query("SELECT SUM(amount) as total FROM escrow_payments WHERE status = 'pending'");
    $stats['total_balance'] = $stmt->fetchColumn() ?? 0;
    
    // Total escrow payments
    $stats['total'] = $db->query("SELECT COUNT(*) FROM escrow_payments")->fetchColumn();
    
    // Pending escrow
    $stats['pending'] = $db->query("SELECT COUNT(*) FROM escrow_payments WHERE status = 'pending'")->fetchColumn();
    
    // Released escrow
    $stats['released'] = $db->query("SELECT COUNT(*) FROM escrow_payments WHERE status = 'released'")->fetchColumn();
    
    // Refunded escrow
    $stats['refunded'] = $db->query("SELECT COUNT(*) FROM escrow_payments WHERE status = 'refunded'")->fetchColumn();
    
    // Disputed escrow
    $stats['disputed'] = $db->query("SELECT COUNT(*) FROM escrow_payments WHERE status = 'disputed'")->fetchColumn();
} catch (PDOException $e) {
    $stats = [
        'total_balance' => 0,
        'total' => 0,
        'pending' => 0,
        'released' => 0,
        'refunded' => 0,
        'disputed' => 0
    ];
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

if (!function_exists('getEscrowBadge')) {
    function getEscrowBadge($status) {
        $colors = [
            'pending' => 'warning',
            'released' => 'success',
            'refunded' => 'secondary',
            'disputed' => 'danger'
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
    <title>Escrow Payments - TechProcure Tanzania</title>
    
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
        
        .sidebar .nav-link .badge {
            margin-left: auto;
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
        
        /* Stats Cards */
        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            transition: all 0.3s;
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
            right: 20px;
            bottom: 20px;
        }
        
        .stat-card .stat-number {
            font-size: 1.8rem;
            font-weight: 700;
        }
        
        .stat-card .stat-label {
            font-size: 0.8rem;
            color: #6c757d;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        /* Data Card */
        .data-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 24px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        
        .data-card .card-title {
            font-size: 1rem;
            font-weight: 600;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #e9ecef;
        }
        
        .data-card .card-title i {
            margin-right: 8px;
            color: #0d6efd;
        }
        
        .escrow-card {
            background: white;
            border-radius: 12px;
            padding: 15px;
            margin-bottom: 16px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            transition: all 0.3s;
            border-left: 4px solid #0d6efd;
        }
        
        .escrow-card:hover {
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
        
        .escrow-card .escrow-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            margin-bottom: 10px;
        }
        
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
        
        /* Escrow Balance Banner */
        .balance-banner {
            background: linear-gradient(135deg, #6f42c1, #0d6efd);
            color: white;
            border-radius: 15px;
            padding: 20px 25px;
            margin-bottom: 24px;
        }
        
        .balance-banner .amount {
            font-size: 2.5rem;
            font-weight: 800;
        }
        
        @media (max-width: 992px) {
            .sidebar {
                margin-left: -280px;
            }
            .sidebar.active {
                margin-left: 0;
            }
            .main-content {
                margin-left: 0;
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
            <a href="../orders/manage-orders.php" class="nav-link">
                <i class="fas fa-shopping-cart"></i> Orders
            </a>
        </div>
        <div class="nav-item">
            <a href="transactions.php" class="nav-link">
                <i class="fas fa-credit-card"></i> Payments
            </a>
        </div>
        <div class="nav-item">
            <a href="escrow-payments.php" class="nav-link active">
                <i class="fas fa-lock"></i> Escrow
                <?php if($stats['pending'] > 0): ?>
                    <span class="badge bg-success"><?php echo $stats['pending']; ?></span>
                <?php endif; ?>
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
            <i class="fas fa-lock me-2 text-primary"></i> Escrow Payments
        </div>
        <div class="d-flex align-items-center gap-3">
            <a href="release-funds.php" class="btn btn-success btn-sm">
                <i class="fas fa-check-circle me-1"></i> Release Funds
            </a>
            <div class="admin-avatar">
                <?php echo strtoupper(substr($_SESSION['user_name'] ?? 'A', 0, 1)); ?>
            </div>
        </div>
    </div>
    
    <!-- Balance Banner -->
    <div class="balance-banner">
        <div class="row align-items-center">
            <div class="col-md-6">
                <div class="text-muted opacity-75">Total Escrow Balance</div>
                <div class="amount"><?php echo formatPrice($stats['total_balance']); ?></div>
                <div class="text-muted opacity-75">Held in escrow pending release</div>
            </div>
            <div class="col-md-6 text-md-end">
                <div class="d-flex flex-wrap justify-content-md-end gap-3">
                    <div>
                        <div class="text-muted opacity-75">Pending</div>
                        <div class="h5 mb-0"><?php echo number_format($stats['pending']); ?></div>
                    </div>
                    <div>
                        <div class="text-muted opacity-75">Released</div>
                        <div class="h5 mb-0 text-success"><?php echo number_format($stats['released']); ?></div>
                    </div>
                    <div>
                        <div class="text-muted opacity-75">Refunded</div>
                        <div class="h5 mb-0 text-secondary"><?php echo number_format($stats['refunded']); ?></div>
                    </div>
                    <div>
                        <div class="text-muted opacity-75">Disputed</div>
                        <div class="h5 mb-0 text-danger"><?php echo number_format($stats['disputed']); ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Filter Section -->
    <div class="data-card">
        <div class="card-title">
            <i class="fas fa-filter"></i> Filter Escrow Payments
        </div>
        <form method="GET" action="" class="row g-3">
            <div class="col-md-5">
                <input type="text" name="search" class="form-control" placeholder="Search by order #, customer..." value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <div class="col-md-3">
                <select name="filter" class="form-select">
                    <option value="all" <?php echo $filter == 'all' ? 'selected' : ''; ?>>All Status</option>
                    <option value="pending" <?php echo $filter == 'pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="released" <?php echo $filter == 'released' ? 'selected' : ''; ?>>Released</option>
                    <option value="refunded" <?php echo $filter == 'refunded' ? 'selected' : ''; ?>>Refunded</option>
                    <option value="disputed" <?php echo $filter == 'disputed' ? 'selected' : ''; ?>>Disputed</option>
                </select>
            </div>
            <div class="col-md-4">
                <button type="submit" class="btn btn-primary"><i class="fas fa-search me-1"></i> Filter</button>
                <a href="escrow-payments.php" class="btn btn-secondary"><i class="fas fa-undo me-1"></i> Reset</a>
            </div>
        </form>
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
    
    <!-- Escrow Payments List -->
    <div class="data-card">
        <div class="card-title">
            <i class="fas fa-list"></i> Escrow Payments
            <span class="badge bg-primary ms-2"><?php echo number_format($total_escrow); ?></span>
        </div>
        
        <?php if (!empty($escrow_payments)): ?>
            <?php foreach($escrow_payments as $escrow): ?>
            <div class="escrow-card" style="border-left-color: <?php echo $escrow['status'] == 'released' ? '#198754' : ($escrow['status'] == 'refunded' ? '#6c757d' : ($escrow['status'] == 'disputed' ? '#dc3545' : '#ffc107')); ?>;">
                <div class="escrow-header">
                    <div>
                        <span class="badge bg-<?php echo getEscrowBadge($escrow['status']); ?> fs-6 p-2">
                            <?php echo ucfirst($escrow['status']); ?>
                        </span>
                        <span class="text-muted ms-2">| Order: <code><?php echo htmlspecialchars($escrow['order_number'] ?? 'N/A'); ?></code></span>
                    </div>
                    <div class="escrow-date">
                        <i class="fas fa-calendar-alt me-1"></i>
                        <?php echo formatDateTime($escrow['created_at']); ?>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-3">
                        <div class="text-muted small">Customer</div>
                        <div class="fw-bold"><?php echo htmlspecialchars($escrow['customer_name'] ?? 'N/A'); ?></div>
                        <div class="small text-muted"><?php echo htmlspecialchars($escrow['customer_email'] ?? ''); ?></div>
                    </div>
                    <div class="col-md-2">
                        <div class="text-muted small">Amount</div>
                        <div class="fw-bold text-primary"><?php echo formatPrice($escrow['amount']); ?></div>
                    </div>
                    <div class="col-md-3">
                        <div class="text-muted small">Release Date</div>
                        <div><?php echo $escrow['release_date'] ? formatDateTime($escrow['release_date']) : 'Not released'; ?></div>
                    </div>
                    <div class="col-md-4 text-end">
                        <?php if($escrow['status'] == 'pending'): ?>
                            <a href="?release=<?php echo $escrow['id']; ?>" class="btn btn-sm btn-success" onclick="return confirm('Release this escrow payment?')">
                                <i class="fas fa-check-circle me-1"></i> Release
                            </a>
                            <a href="?refund=<?php echo $escrow['id']; ?>" class="btn btn-sm btn-warning" onclick="return confirm('Refund this escrow payment?')">
                                <i class="fas fa-undo me-1"></i> Refund
                            </a>
                        <?php endif; ?>
                        <?php if($escrow['status'] == 'disputed'): ?>
                            <a href="dispute-details.php?id=<?php echo $escrow['id']; ?>" class="btn btn-sm btn-outline-danger">
                                <i class="fas fa-gavel me-1"></i> View Dispute
                            </a>
                        <?php endif; ?>
                        <a href="escrow-details.php?id=<?php echo $escrow['id']; ?>" class="btn btn-sm btn-outline-primary">
                            <i class="fas fa-eye me-1"></i> Details
                        </a>
                    </div>
                </div>
                
                <?php if($escrow['transaction_count'] > 0): ?>
                <div class="mt-2">
                    <small class="text-muted"><i class="fas fa-history me-1"></i> <?php echo $escrow['transaction_count']; ?> transactions</small>
                </div>
                <?php endif; ?>
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
        <div class="text-center py-5">
            <i class="fas fa-lock fa-3x text-muted mb-3"></i>
            <p class="text-muted">No escrow payments found.</p>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

</body>
</html>