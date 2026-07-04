<?php
/**
 * TechProcure Tanzania - Admin Dashboard
 * File: admin/dashboard.php
 * Description: Admin dashboard with statistics, charts, escrow management, and quick actions
 */

// Start session if not started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include configuration files
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';


// Check if user is admin
requireAdmin();

// Get database connection
$db = getDB();
$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'] ?? 'Admin';

// =============================================
// GET STATISTICS
// =============================================

$stats = [];

// Total users
try {
    $result = $db->query("SELECT COUNT(*) as total FROM users WHERE status = 'active'");
    $stats['total_users'] = $result ? (int)$result->fetchColumn() : 0;
} catch (PDOException $e) {
    $stats['total_users'] = 0;
}

// Total suppliers
try {
    $result = $db->query("SELECT COUNT(*) as total FROM suppliers WHERE approval_status = 'approved'");
    $stats['total_suppliers'] = $result ? (int)$result->fetchColumn() : 0;
} catch (PDOException $e) {
    $stats['total_suppliers'] = 0;
}

// Pending suppliers
try {
    $result = $db->query("SELECT COUNT(*) as total FROM suppliers WHERE approval_status = 'pending'");
    $stats['pending_suppliers'] = $result ? (int)$result->fetchColumn() : 0;
} catch (PDOException $e) {
    $stats['pending_suppliers'] = 0;
}

// Total products
try {
    $result = $db->query("SELECT COUNT(*) as total FROM products WHERE status = 'active' AND approval_status = 'approved'");
    $stats['total_products'] = $result ? (int)$result->fetchColumn() : 0;
} catch (PDOException $e) {
    $stats['total_products'] = 0;
}

// Pending products
try {
    $result = $db->query("SELECT COUNT(*) as total FROM products WHERE approval_status = 'pending'");
    $stats['pending_products'] = $result ? (int)$result->fetchColumn() : 0;
} catch (PDOException $e) {
    $stats['pending_products'] = 0;
}

// Total orders
try {
    $result = $db->query("SELECT COUNT(*) as total FROM orders");
    $stats['total_orders'] = $result ? (int)$result->fetchColumn() : 0;
} catch (PDOException $e) {
    $stats['total_orders'] = 0;
}

// Pending orders
try {
    $result = $db->query("SELECT COUNT(*) as total FROM orders WHERE order_status = 'pending'");
    $stats['pending_orders'] = $result ? (int)$result->fetchColumn() : 0;
} catch (PDOException $e) {
    $stats['pending_orders'] = 0;
}

// Total revenue
try {
    $result = $db->query("SELECT SUM(total_amount) as total FROM orders WHERE payment_status = 'paid'");
    $row = $result ? $result->fetch() : null;
    $stats['total_revenue'] = $row['total'] ?? 0;
} catch (PDOException $e) {
    $stats['total_revenue'] = 0;
}

// Escrow balance
try {
    $result = $db->query("SELECT SUM(amount) as total FROM escrow_payments WHERE status = 'pending'");
    $row = $result ? $result->fetch() : null;
    $stats['escrow_balance'] = $row['total'] ?? 0;
} catch (PDOException $e) {
    $stats['escrow_balance'] = 0;
}

// Total customers
try {
    $result = $db->query("SELECT COUNT(*) as total FROM users WHERE user_type = 'customer' AND status = 'active'");
    $stats['total_customers'] = $result ? (int)$result->fetchColumn() : 0;
} catch (PDOException $e) {
    $stats['total_customers'] = 0;
}

// Total disputes
try {
    $result = $db->query("SELECT COUNT(*) as total FROM disputes WHERE status != 'closed'");
    $stats['total_disputes'] = $result ? (int)$result->fetchColumn() : 0;
} catch (PDOException $e) {
    $stats['total_disputes'] = 0;
}

// Pending payouts
try {
    $result = $db->query("SELECT SUM(amount) as total FROM escrow_payments WHERE status = 'pending'");
    $row = $result ? $result->fetch() : null;
    $stats['pending_payouts'] = $row['total'] ?? 0;
} catch (PDOException $e) {
    $stats['pending_payouts'] = 0;
}

// Total invoices
try {
    $result = $db->query("SELECT COUNT(*) as total FROM invoices");
    $stats['total_invoices'] = $result ? (int)$result->fetchColumn() : 0;
} catch (PDOException $e) {
    $stats['total_invoices'] = 0;
}

// =============================================
// GET RECENT ORDERS
// =============================================

$recent_orders = [];
try {
    $sql = "SELECT o.*, u.full_name as customer_name 
            FROM orders o 
            JOIN users u ON o.user_id = u.id 
            ORDER BY o.created_at DESC 
            LIMIT 10";
    $result = $db->query($sql);
    if ($result && $result->rowCount() > 0) {
        $recent_orders = $result->fetchAll();
    }
} catch (PDOException $e) {
    $recent_orders = [];
}

// =============================================
// GET RECENT ESCROW TRANSACTIONS
// =============================================

$recent_escrow = [];
try {
    $sql = "SELECT e.*, o.order_number, u.full_name as customer_name 
            FROM escrow_payments e
            JOIN orders o ON e.order_id = o.id
            JOIN users u ON o.user_id = u.id
            ORDER BY e.created_at DESC 
            LIMIT 5";
    $result = $db->query($sql);
    if ($result && $result->rowCount() > 0) {
        $recent_escrow = $result->fetchAll();
    }
} catch (PDOException $e) {
    $recent_escrow = [];
}

// =============================================
// GET RECENT USERS
// =============================================

$recent_users = [];
try {
    $sql = "SELECT u.*, r.role_name 
            FROM users u 
            LEFT JOIN roles r ON u.role_id = r.id 
            ORDER BY u.created_at DESC 
            LIMIT 5";
    $result = $db->query($sql);
    if ($result && $result->rowCount() > 0) {
        $recent_users = $result->fetchAll();
    }
} catch (PDOException $e) {
    $recent_users = [];
}

// =============================================
// GET MONTHLY SALES FOR CHART
// =============================================

$months = [];
$revenues = [];
try {
    $sql = "SELECT 
                DATE_FORMAT(created_at, '%b') as month,
                SUM(total_amount) as revenue,
                COUNT(*) as order_count
            FROM orders 
            WHERE payment_status = 'paid' 
                AND created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
            GROUP BY MONTH(created_at)
            ORDER BY MONTH(created_at) ASC";
    $result = $db->query($sql);
    if ($result && $result->rowCount() > 0) {
        $monthly_sales = $result->fetchAll();
        foreach ($monthly_sales as $sale) {
            $months[] = $sale['month'];
            $revenues[] = $sale['revenue'];
        }
    }
} catch (PDOException $e) {
    $months = [];
    $revenues = [];
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

if (!function_exists('formatStatus')) {
    function formatStatus($status) {
        $colors = [
            'pending' => 'warning',
            'approved' => 'success',
            'rejected' => 'danger',
            'active' => 'success',
            'inactive' => 'secondary',
            'paid' => 'success',
            'unpaid' => 'danger',
            'shipped' => 'info',
            'delivered' => 'primary',
            'completed' => 'success',
            'cancelled' => 'danger',
            'processing' => 'info',
            'confirmed' => 'primary',
            'suspended' => 'danger',
            'open' => 'info',
            'closed' => 'secondary',
            'released' => 'success',
            'refunded' => 'warning'
        ];
        $labels = [
            'pending' => 'Pending',
            'approved' => 'Approved',
            'rejected' => 'Rejected',
            'active' => 'Active',
            'inactive' => 'Inactive',
            'paid' => 'Paid',
            'unpaid' => 'Unpaid',
            'shipped' => 'Shipped',
            'delivered' => 'Delivered',
            'completed' => 'Completed',
            'cancelled' => 'Cancelled',
            'processing' => 'Processing',
            'confirmed' => 'Confirmed',
            'suspended' => 'Suspended',
            'open' => 'Open',
            'closed' => 'Closed',
            'released' => 'Released',
            'refunded' => 'Refunded'
        ];
        return [
            'class' => $colors[$status] ?? 'secondary',
            'label' => $labels[$status] ?? ucfirst($status)
        ];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - TechProcure Tanzania</title>
    
    <!-- CSS Dependencies -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f0f2f5;
            overflow-x: hidden;
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
        
        /* No logout in sidebar - removed */
        
        /* Main Content */
        .main-content {
            margin-left: 280px;
            padding: 20px;
            min-height: 100vh;
        }
        
        /* Top Navbar with Profile */
        .top-navbar {
            background: white;
            border-radius: 15px;
            padding: 12px 25px;
            margin-bottom: 24px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .top-navbar .welcome-text {
            font-size: 1rem;
            font-weight: 500;
        }
        
        .top-navbar .welcome-text small {
            font-weight: 400;
            color: #6c757d;
        }
        
        .top-navbar .admin-profile {
            display: flex;
            align-items: center;
            gap: 15px;
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
        
        .top-navbar .profile-dropdown .dropdown-menu {
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
            border: none;
            padding: 8px 0;
            min-width: 200px;
        }
        
        .top-navbar .profile-dropdown .dropdown-item {
            padding: 10px 20px;
            transition: all 0.3s;
        }
        
        .top-navbar .profile-dropdown .dropdown-item:hover {
            background: #f0f7ff;
            padding-left: 25px;
        }
        
        .top-navbar .profile-dropdown .dropdown-item i {
            width: 20px;
            margin-right: 10px;
            color: #6c757d;
        }
        
        .top-navbar .profile-dropdown .dropdown-item.text-danger i {
            color: #dc3545;
        }
        
        .top-navbar .profile-dropdown .dropdown-divider {
            margin: 5px 0;
        }
        
        /* Stat Cards */
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
            font-size: 2.5rem;
            opacity: 0.15;
            position: absolute;
            right: 20px;
            bottom: 20px;
        }
        
        .stat-card .stat-label {
            font-size: 0.8rem;
            color: #6c757d;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .stat-card .stat-number {
            font-size: 2rem;
            font-weight: 700;
            margin: 5px 0;
        }
        
        .stat-card .stat-sub {
            font-size: 0.75rem;
            color: #6c757d;
        }
        
        .stat-card.primary { border-left: 4px solid #0d6efd; }
        .stat-card.success { border-left: 4px solid #198754; }
        .stat-card.warning { border-left: 4px solid #ffc107; }
        .stat-card.danger { border-left: 4px solid #dc3545; }
        .stat-card.info { border-left: 4px solid #0dcaf0; }
        .stat-card.purple { border-left: 4px solid #6f42c1; }
        .stat-card.teal { border-left: 4px solid #20c997; }
        .stat-card.orange { border-left: 4px solid #fd7e14; }
        
        .stat-card .stat-number.primary { color: #0d6efd; }
        .stat-card .stat-number.success { color: #198754; }
        .stat-card .stat-number.warning { color: #ffc107; }
        .stat-card .stat-number.danger { color: #dc3545; }
        .stat-card .stat-number.info { color: #0dcaf0; }
        .stat-card .stat-number.purple { color: #6f42c1; }
        .stat-card .stat-number.teal { color: #20c997; }
        .stat-card .stat-number.orange { color: #fd7e14; }
        
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
        
        /* Quick Actions */
        .quick-action-btn {
            padding: 15px;
            border-radius: 12px;
            text-align: center;
            transition: all 0.3s;
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            text-decoration: none;
            color: #333;
            display: block;
        }
        
        .quick-action-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            border-color: #0d6efd;
        }
        
        .quick-action-btn i {
            font-size: 1.8rem;
            display: block;
            margin-bottom: 8px;
        }
        
        /* Table */
        .table-custom th {
            font-weight: 600;
            color: #495057;
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .table-custom td {
            vertical-align: middle;
        }
        
        /* Mobile Toggle */
        .sidebar-toggle {
            display: none;
            background: white;
            border: none;
            border-radius: 10px;
            padding: 8px 12px;
            font-size: 1.2rem;
            cursor: pointer;
        }
        
        /* Responsive */
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
            .sidebar-toggle {
                display: block;
            }
            .main-content.active {
                margin-left: 280px;
            }
        }
        
        @media (max-width: 576px) {
            .stat-card .stat-number {
                font-size: 1.5rem;
            }
            .top-navbar {
                flex-wrap: wrap;
                gap: 10px;
            }
            .top-navbar .welcome-text {
                font-size: 0.9rem;
            }
        }
        
        /* Scrollbar */
        ::-webkit-scrollbar {
            width: 6px;
        }
        ::-webkit-scrollbar-track {
            background: #f1f1f1;
        }
        ::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 3px;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: #555;
        }
    </style>
</head>
<body>

<!-- ============================================= -->
<!-- SIDEBAR (No Logout) -->
<!-- ============================================= -->
<div class="sidebar" id="sidebar">
    <div class="brand">
        <i class="fas fa-microchip"></i> TechProcure
        <small>Admin Panel</small>
    </div>
    
    <div class="nav flex-column mt-3">
        <div class="nav-item">
            <a href="dashboard.php" class="nav-link active">
                <i class="fas fa-tachometer-alt"></i> Dashboard
            </a>
        </div>
        <div class="nav-item">
            <a href="users/manage-users.php" class="nav-link">
                <i class="fas fa-users"></i> Users
            </a>
        </div>
        <div class="nav-item">
            <a href="suppliers/manage-suppliers.php" class="nav-link">
                <i class="fas fa-truck"></i> Suppliers
                <?php if($stats['pending_suppliers'] > 0): ?>
                    <span class="badge bg-danger"><?php echo $stats['pending_suppliers']; ?></span>
                <?php endif; ?>
            </a>
        </div>
        <div class="nav-item">
            <a href="products/manage-products.php" class="nav-link">
                <i class="fas fa-box"></i> Products
                <?php if($stats['pending_products'] > 0): ?>
                    <span class="badge bg-warning"><?php echo $stats['pending_products']; ?></span>
                <?php endif; ?>
            </a>
        </div>
        <div class="nav-item">
            <a href="orders/manage-orders.php" class="nav-link">
                <i class="fas fa-shopping-cart"></i> Orders
                <?php if($stats['pending_orders'] > 0): ?>
                    <span class="badge bg-info"><?php echo $stats['pending_orders']; ?></span>
                <?php endif; ?>
            </a>
        </div>
        <div class="nav-item">
            <a href="payments/transactions.php" class="nav-link">
                <i class="fas fa-credit-card"></i> Payments
            </a>
        </div>
        <div class="nav-item">
            <a href="payments/escrow-payments.php" class="nav-link">
                <i class="fas fa-lock"></i> Escrow
                <?php if($stats['escrow_balance'] > 0): ?>
                    <span class="badge bg-success"><?php echo formatPrice($stats['escrow_balance']); ?></span>
                <?php endif; ?>
            </a>
        </div>
        <div class="nav-item">
            <a href="disputes/complaints.php" class="nav-link">
                <i class="fas fa-gavel"></i> Disputes
                <?php if($stats['total_disputes'] > 0): ?>
                    <span class="badge bg-danger"><?php echo $stats['total_disputes']; ?></span>
                <?php endif; ?>
            </a>
        </div>
        <div class="nav-item">
            <a href="invoices/invoices.php" class="nav-link">
                <i class="fas fa-file-invoice"></i> Invoices
                <?php if($stats['total_invoices'] > 0): ?>
                    <span class="badge bg-secondary"><?php echo $stats['total_invoices']; ?></span>
                <?php endif; ?>
            </a>
        </div>
        <div class="nav-item">
            <a href="reports/sales-report.php" class="nav-link">
                <i class="fas fa-chart-line"></i> Reports
            </a>
        </div>
        <div class="nav-item">
            <a href="security/activity-logs.php" class="nav-link">
                <i class="fas fa-shield-alt"></i> Security
            </a>
        </div>
        <div class="nav-item">
            <a href="settings/general-settings.php" class="nav-link">
                <i class="fas fa-cog"></i> Settings
            </a>
        </div>
    </div>
    
    <!-- No logout in sidebar - moved to profile dropdown -->
</div>

<!-- ============================================= -->
<!-- MAIN CONTENT ->
<!-- ============================================= -->
<!-- MAIN CONTENT -->
<!-- ============================================= -->
<div class="main-content" id="mainContent">
    <!-- Top Navbar with Profile Dropdown (Logout here) -->
    <div class="top-navbar">
        <div class="d-flex align-items-center gap-3">
            <button class="sidebar-toggle" id="sidebarToggle">
                <i class="fas fa-bars"></i>
            </button>
            <div class="welcome-text">
                Welcome back, <strong><?php echo htmlspecialchars($user_name); ?></strong>
                <small class="d-block d-sm-inline-block">| <?php echo date('l, F d, Y'); ?></small>
            </div>
        </div>
        <div class="admin-profile">
            <a href="../index.php" target="_blank" class="btn btn-sm btn-outline-primary">
                <i class="fas fa-external-link-alt me-1"></i> View Site
            </a>
            <div class="profile-dropdown dropdown">
                <button class="btn btn-link text-dark text-decoration-none dropdown-toggle" data-bs-toggle="dropdown">
                    <div class="admin-avatar d-inline-flex align-items-center justify-content-center">
                        <?php echo strtoupper(substr($user_name, 0, 1)); ?>
                    </div>
                    <span class="ms-2 d-none d-md-inline"><?php echo htmlspecialchars($user_name); ?></span>
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user"></i> My Profile</a></li>
                    <li><a class="dropdown-item" href="profile.php?tab=settings"><i class="fas fa-cog"></i> Account Settings</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="reports/sales-report.php"><i class="fas fa-chart-bar"></i> Reports</a></li>
                    <li><a class="dropdown-item" href="payments/escrow-payments.php"><i class="fas fa-lock"></i> Escrow Management</a></li>
                    <li><a class="dropdown-item" href="disputes/complaints.php"><i class="fas fa-gavel"></i> Disputes</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item text-danger" href="../auth/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                </ul>
            </div>
        </div>
    </div>
    
    <!-- ============================================= -->
    <!-- DASHBOARD CONTENT -->
    <!-- ============================================= -->
    
    <!-- Stats Row 1 -->
    <div class="row">
        <div class="col-md-3 col-sm-6">
            <div class="stat-card primary">
                <div class="stat-label">Total Users</div>
                <div class="stat-number primary"><?php echo number_format($stats['total_users']); ?></div>
                <div class="stat-sub">
                    <i class="fas fa-building me-1"></i> <?php echo number_format($stats['total_customers']); ?> customers
                </div>
                <div class="stat-icon"><i class="fas fa-users"></i></div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="stat-card success">
                <div class="stat-label">Suppliers</div>
                <div class="stat-number success"><?php echo number_format($stats['total_suppliers']); ?></div>
                <div class="stat-sub">
                    <i class="fas fa-clock me-1"></i> <?php echo $stats['pending_suppliers']; ?> pending
                </div>
                <div class="stat-icon"><i class="fas fa-truck"></i></div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="stat-card warning">
                <div class="stat-label">Products</div>
                <div class="stat-number warning"><?php echo number_format($stats['total_products']); ?></div>
                <div class="stat-sub">
                    <i class="fas fa-clock me-1"></i> <?php echo $stats['pending_products']; ?> pending
                </div>
                <div class="stat-icon"><i class="fas fa-box"></i></div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="stat-card danger">
                <div class="stat-label">Orders</div>
                <div class="stat-number danger"><?php echo number_format($stats['total_orders']); ?></div>
                <div class="stat-sub">
                    <i class="fas fa-clock me-1"></i> <?php echo $stats['pending_orders']; ?> pending
                </div>
                <div class="stat-icon"><i class="fas fa-shopping-cart"></i></div>
            </div>
        </div>
    </div>
    
    <!-- Stats Row 2 -->
    <div class="row">
        <div class="col-md-3 col-sm-6">
            <div class="stat-card info">
                <div class="stat-label">Total Revenue</div>
                <div class="stat-number info"><?php echo formatPrice($stats['total_revenue']); ?></div>
                <div class="stat-sub">Lifetime sales</div>
                <div class="stat-icon"><i class="fas fa-money-bill-wave"></i></div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="stat-card purple">
                <div class="stat-label">Escrow Balance</div>
                <div class="stat-number purple"><?php echo formatPrice($stats['escrow_balance']); ?></div>
                <div class="stat-sub">
                    <i class="fas fa-clock me-1"></i> Pending release
                    <a href="payments/escrow-payments.php" class="text-primary ms-1">Manage</a>
                </div>
                <div class="stat-icon"><i class="fas fa-lock"></i></div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="stat-card orange">
                <div class="stat-label">Disputes</div>
                <div class="stat-number orange"><?php echo number_format($stats['total_disputes']); ?></div>
                <div class="stat-sub">
                    <i class="fas fa-gavel me-1"></i> Open cases
                    <a href="disputes/complaints.php" class="text-primary ms-1">View</a>
                </div>
                <div class="stat-icon"><i class="fas fa-gavel"></i></div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="stat-card teal">
                <div class="stat-label">Invoices</div>
                <div class="stat-number teal"><?php echo number_format($stats['total_invoices']); ?></div>
                <div class="stat-sub">
                    <i class="fas fa-file-invoice me-1"></i> Total generated
                </div>
                <div class="stat-icon"><i class="fas fa-file-invoice"></i></div>
            </div>
        </div>
    </div>
    
    <!-- Escrow Balance Card -->
    <div class="row">
        <div class="col-12">
            <div class="stat-card" style="border-left: 4px solid #6f42c1; background: linear-gradient(135deg, #f8f9fa, #e9ecef);">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="stat-label">Escrow Summary</div>
                        <div class="stat-number purple"><?php echo formatPrice($stats['escrow_balance']); ?></div>
                        <div class="stat-sub">
                            <i class="fas fa-lock me-1"></i> Held in escrow 
                            <span class="mx-2">|</span>
                            <i class="fas fa-clock me-1"></i> Awaiting release
                            <span class="mx-2">|</span>
                            <a href="payments/escrow-payments.php" class="text-primary">View All Escrow Transactions</a>
                        </div>
                    </div>
                    <div class="d-flex gap-2">
                        <a href="payments/escrow-payments.php" class="btn btn-primary">
                            <i class="fas fa-lock me-1"></i> Manage Escrow
                        </a>
                        <a href="payments/release-funds.php" class="btn btn-success">
                            <i class="fas fa-check-circle me-1"></i> Release Funds
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Chart and Quick Actions -->
    <div class="row">
        <div class="col-lg-8">
            <div class="data-card">
                <div class="card-title">
                    <i class="fas fa-chart-line"></i> Revenue Trends (Last 6 Months)
                </div>
                <canvas id="revenueChart" height="250"></canvas>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="data-card">
                <div class="card-title">
                    <i class="fas fa-bolt"></i> Quick Actions
                </div>
                <div class="row g-2">
                    <div class="col-6">
                        <a href="users/add-admin.php" class="quick-action-btn">
                            <i class="fas fa-user-plus text-primary"></i>
                            <small>Add Admin</small>
                        </a>
                    </div>
                    <div class="col-6">
                        <a href="suppliers/manage-suppliers.php" class="quick-action-btn">
                            <i class="fas fa-truck text-success"></i>
                            <small>Suppliers</small>
                        </a>
                    </div>
                    <div class="col-6">
                        <a href="products/add-product.php" class="quick-action-btn">
                            <i class="fas fa-plus-circle text-info"></i>
                            <small>Add Product</small>
                        </a>
                    </div>
                    <div class="col-6">
                        <a href="reports/sales-report.php" class="quick-action-btn">
                            <i class="fas fa-file-alt text-warning"></i>
                            <small>Reports</small>
                        </a>
                    </div>
                    <div class="col-6">
                        <a href="payments/escrow-payments.php" class="quick-action-btn">
                            <i class="fas fa-lock text-purple"></i>
                            <small>Escrow</small>
                        </a>
                    </div>
                    <div class="col-6">
                        <a href="disputes/complaints.php" class="quick-action-btn">
                            <i class="fas fa-gavel text-danger"></i>
                            <small>Disputes</small>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Recent Escrow Transactions -->
    <?php if (!empty($recent_escrow)): ?>
    <div class="data-card">
        <div class="d-flex justify-content-between align-items-center">
            <div class="card-title mb-0">
                <i class="fas fa-lock text-purple"></i> Recent Escrow Transactions
            </div>
            <a href="payments/escrow-payments.php" class="btn btn-sm btn-primary">View All</a>
        </div>
        <div class="table-responsive mt-3">
            <table class="table table-hover table-custom">
                <thead>
                    <tr>
                        <th>Order #</th>
                        <th>Customer</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($recent_escrow as $escrow): ?>
                    <tr>
                        <td><code><?php echo htmlspecialchars($escrow['order_number']); ?></code></td>
                        <td><?php echo htmlspecialchars($escrow['customer_name'] ?? 'N/A'); ?></td>
                        <td><?php echo formatPrice($escrow['amount']); ?></td>
                        <td>
                            <?php $status = formatStatus($escrow['status']); ?>
                            <span class="badge bg-<?php echo $status['class']; ?>"><?php echo $status['label']; ?></span>
                        </td>
                        <td><?php echo formatDate($escrow['created_at']); ?></td>
                        <td>
                            <a href="payments/escrow-details.php?id=<?php echo $escrow['id']; ?>" class="btn btn-sm btn-outline-primary">
                                <i class="fas fa-eye"></i>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Recent Orders -->
    <div class="data-card">
        <div class="d-flex justify-content-between align-items-center">
            <div class="card-title mb-0">
                <i class="fas fa-shopping-cart"></i> Recent Orders
            </div>
            <a href="orders/manage-orders.php" class="btn btn-sm btn-primary">View All</a>
        </div>
        <div class="table-responsive mt-3">
            <table class="table table-hover table-custom">
                <thead>
                    <tr>
                        <th>Order #</th>
                        <th>Customer</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Payment</th>
                        <th>Date</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($recent_orders)): ?>
                        <?php foreach($recent_orders as $order): ?>
                        <tr>
                            <td><code><?php echo htmlspecialchars($order['order_number']); ?></code></td>
                            <td><?php echo htmlspecialchars($order['customer_name'] ?? 'N/A'); ?></td>
                            <td><?php echo formatPrice($order['total_amount']); ?></td>
                            <td>
                                <?php $status = formatStatus($order['order_status']); ?>
                                <span class="badge bg-<?php echo $status['class']; ?>"><?php echo $status['label']; ?></span>
                            </td>
                            <td>
                                <?php $payment = formatStatus($order['payment_status']); ?>
                                <span class="badge bg-<?php echo $payment['class']; ?>"><?php echo $payment['label']; ?></span>
                            </td>
                            <td><?php echo formatDate($order['created_at']); ?></td>
                            <td>
                                <a href="orders/order-details.php?id=<?php echo $order['id']; ?>" class="btn btn-sm btn-outline-primary">
                                    <i class="fas fa-eye"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center text-muted py-3">No orders found</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Recent Users -->
    <div class="data-card">
        <div class="d-flex justify-content-between align-items-center">
            <div class="card-title mb-0">
                <i class="fas fa-user-plus"></i> Recent Users
            </div>
            <a href="users/manage-users.php" class="btn btn-sm btn-primary">View All</a>
        </div>
        <div class="table-responsive mt-3">
            <table class="table table-hover table-custom">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Joined</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($recent_users)): ?>
                        <?php foreach($recent_users as $user): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($user['full_name'] ?? $user['username']); ?></td>
                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                            <td>
                                <span class="badge bg-<?php echo $user['role_name'] == 'admin' ? 'danger' : ($user['role_name'] == 'supplier' ? 'success' : 'primary'); ?>">
                                    <?php echo ucfirst($user['role_name'] ?? 'Customer'); ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge bg-<?php echo $user['status'] == 'active' ? 'success' : 'danger'; ?>">
                                    <?php echo ucfirst($user['status']); ?>
                                </span>
                            </td>
                            <td><?php echo formatDate($user['created_at']); ?></td>
                            <td>
                                <a href="users/user-details.php?id=<?php echo $user['id']; ?>" class="btn btn-sm btn-outline-primary">
                                    <i class="fas fa-eye"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-center text-muted py-3">No users found</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Footer -->
    <div class="text-center text-muted small py-3">
        &copy; <?php echo date('Y'); ?> TechProcure Tanzania. All rights reserved. v1.0.0
    </div>
</div>

<!-- ============================================= -->
<!-- SCRIPTS -->
<!-- ============================================= -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
    // =============================================
    // SIDEBAR TOGGLE
    // =============================================
    document.getElementById('sidebarToggle').addEventListener('click', function() {
        document.getElementById('sidebar').classList.toggle('active');
        document.getElementById('mainContent').classList.toggle('active');
    });
    
    // =============================================
    // REVENUE CHART
    // =============================================
    const ctx = document.getElementById('revenueChart').getContext('2d');
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: <?php echo json_encode($months); ?>,
            datasets: [{
                label: 'Revenue (TSh)',
                data: <?php echo json_encode($revenues); ?>,
                borderColor: '#0d6efd',
                backgroundColor: 'rgba(13, 110, 253, 0.1)',
                fill: true,
                tension: 0.4,
                pointBackgroundColor: '#0d6efd',
                pointBorderColor: '#fff',
                pointRadius: 5,
                pointHoverRadius: 7
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: {
                    position: 'top',
                    labels: {
                        font: { size: 12, family: 'Inter' }
                    }
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return 'TSh ' + context.raw.toLocaleString();
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return 'TSh ' + value.toLocaleString();
                        },
                        font: { size: 11 }
                    }
                },
                x: {
                    grid: { display: false },
                    ticks: { font: { size: 11 } }
                }
            },
            interaction: {
                intersect: false,
                mode: 'index'
            }
        }
    });
    
    // =============================================
    // AUTO-HIDE ALERTS (if any)
    // =============================================
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