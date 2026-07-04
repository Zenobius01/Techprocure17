<?php
/**
 * TechProcure Tanzania - Admin Invoices Management
 * File: admin/invoices/invoices.php
 * Description: Complete self-contained invoices management page
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

if (!function_exists('csrf_field')) {
    function csrf_field() {
        return '<input type="hidden" name="csrf_token" value="' . ($_SESSION['csrf_token'] ?? '') . '">';
    }
}

if (!function_exists('verifyCSRFToken')) {
    function verifyCSRFToken($token) {
        if (empty($token) || empty($_SESSION['csrf_token'])) {
            return false;
        }
        return hash_equals($_SESSION['csrf_token'], $token);
    }
}

if (!function_exists('sanitize')) {
    function sanitize($input) {
        if (is_array($input)) {
            return array_map('sanitize', $input);
        }
        return htmlspecialchars(strip_tags(trim($input)));
    }
}

if (!function_exists('log_activity')) {
    function log_activity($user_type, $user_id, $action, $details = null) {
        error_log("Activity: $user_type | $user_id | $action | $details");
        return true;
    }
}

// =============================================
// GET DATABASE CONNECTION
// =============================================
// Check if $db is set from db.php, otherwise create connection
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
// ADMIN AUTHENTICATION CHECK
// =============================================
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: ../../auth/login.php');
    exit();
}

$page_title = 'Invoices Management - Admin Panel';
$admin_name = $_SESSION['user_name'] ?? 'Admin';

// =============================================
// GET FILTER PARAMETERS
// =============================================
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$status = isset($_GET['status']) ? sanitize($_GET['status']) : '';
$date_from = isset($_GET['date_from']) ? sanitize($_GET['date_from']) : '';
$date_to = isset($_GET['date_to']) ? sanitize($_GET['date_to']) : '';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'newest';
$export = isset($_GET['export']) ? $_GET['export'] : '';

$items_per_page = ADMIN_ITEMS_PER_PAGE;
$offset = ($page - 1) * $items_per_page;

// =============================================
// BUILD QUERY CONDITIONS
// =============================================
$where_conditions = ["1=1"];
$params = [];

// Search filter
if ($search) {
    $where_conditions[] = "(i.invoice_number LIKE ? OR o.order_number LIKE ? OR b.company_name LIKE ? OR b.email LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

// Status filter
if ($status) {
    $where_conditions[] = "i.status = ?";
    $params[] = $status;
}

// Date range filter
if ($date_from) {
    $where_conditions[] = "DATE(i.generated_at) >= ?";
    $params[] = $date_from;
}
if ($date_to) {
    $where_conditions[] = "DATE(i.generated_at) <= ?";
    $params[] = $date_to;
}

$where_clause = implode(" AND ", $where_conditions);

// =============================================
// BUILD ORDER BY CLAUSE
// =============================================
switch($sort) {
    case 'oldest':
        $order_by = "i.generated_at ASC";
        break;
    case 'amount_high':
        $order_by = "o.total_amount DESC";
        break;
    case 'amount_low':
        $order_by = "o.total_amount ASC";
        break;
    default:
        $order_by = "i.generated_at DESC";
}

// =============================================
// FETCH INVOICES
// =============================================
try {
    // Check customer column
    $customer_column = 'customer_id';
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
    
    // Check amount column
    $amount_column = 'total_amount';
    $check_stmt = $conn->query("SHOW COLUMNS FROM orders LIKE 'total_amount_tsh'");
    if ($check_stmt->rowCount() > 0) {
        $amount_column = 'total_amount_tsh';
    }
    
    // Build query
    $sql = "SELECT i.*, 
            o.order_number,
            o.$amount_column as total_amount,
            b.company_name as customer_name,
            b.email as customer_email,
            s.company_name as supplier_name
            FROM invoices i
            JOIN orders o ON i.order_id = o.id
            JOIN customers b ON o.$customer_column = b.id
            LEFT JOIN suppliers s ON o.supplier_id = s.id
            WHERE $where_clause
            ORDER BY $order_by
            LIMIT :offset, :limit";
    
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindParam(':limit', $items_per_page, PDO::PARAM_INT);
    
    $param_index = 1;
    foreach ($params as $param) {
        $stmt->bindValue($param_index, $param, PDO::PARAM_STR);
        $param_index++;
    }
    
    $stmt->execute();
    $invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    // If there's an error, try a simpler query
    try {
        $sql = "SELECT i.*, 
                o.order_number,
                b.company_name as customer_name,
                b.email as customer_email,
                s.company_name as supplier_name
                FROM invoices i
                JOIN orders o ON i.order_id = o.id
                JOIN customers b ON o.$customer_column = b.id
                LEFT JOIN suppliers s ON o.supplier_id = s.id
                WHERE $where_clause
                ORDER BY $order_by
                LIMIT :offset, :limit";
        
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
        $stmt->bindParam(':limit', $items_per_page, PDO::PARAM_INT);
        
        $param_index = 1;
        foreach ($params as $param) {
            $stmt->bindValue($param_index, $param, PDO::PARAM_STR);
            $param_index++;
        }
        
        $stmt->execute();
        $invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e2) {
        $invoices = [];
    }
}

// =============================================
// GET TOTAL COUNT FOR PAGINATION
// =============================================
try {
    $count_sql = "SELECT COUNT(*) as total 
                  FROM invoices i
                  JOIN orders o ON i.order_id = o.id
                  JOIN customers b ON o.$customer_column = b.id
                  LEFT JOIN suppliers s ON o.supplier_id = s.id
                  WHERE $where_clause";
    $count_stmt = $conn->prepare($count_sql);
    
    $param_index = 1;
    foreach ($params as $param) {
        $count_stmt->bindValue($param_index, $param, PDO::PARAM_STR);
        $param_index++;
    }
    
    $count_stmt->execute();
    $total_invoices = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
} catch (PDOException $e) {
    $total_invoices = 0;
}
$total_pages = ceil($total_invoices / $items_per_page);

// =============================================
// GET STATISTICS
// =============================================
$stats = [];

// Total invoices
$result = $conn->query("SELECT COUNT(*) as total FROM invoices");
$stats['total'] = $result ? $result->fetch(PDO::FETCH_ASSOC)['total'] : 0;

// Total amount
$stats['total_amount'] = 0;
try {
    $sql = "SELECT SUM(o.$amount_column) as total_amount 
            FROM invoices i 
            JOIN orders o ON i.order_id = o.id 
            WHERE i.status = 'paid'";
    $result = $conn->query($sql);
    if ($result) {
        $row = $result->fetch(PDO::FETCH_ASSOC);
        $stats['total_amount'] = $row['total_amount'] ?? 0;
    }
} catch (PDOException $e) {
    $stats['total_amount'] = 0;
}

// Invoices by status
$result = $conn->query("SELECT status, COUNT(*) as count FROM invoices GROUP BY status");
$stats['by_status'] = [];
if ($result) {
    while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
        $stats['by_status'][$row['status']] = $row['count'];
    }
}

// Today's invoices
try {
    $sql = "SELECT COUNT(*) as count, SUM(o.$amount_column) as total 
            FROM invoices i 
            JOIN orders o ON i.order_id = o.id 
            WHERE DATE(i.generated_at) = CURDATE()";
    $result = $conn->query($sql);
    if ($result) {
        $row = $result->fetch(PDO::FETCH_ASSOC);
        $stats['today'] = $row ?: ['count' => 0, 'total' => 0];
    } else {
        $stats['today'] = ['count' => 0, 'total' => 0];
    }
} catch (PDOException $e) {
    $stats['today'] = ['count' => 0, 'total' => 0];
}

// =============================================
// HANDLE EXPORT
// =============================================
if ($export == 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="invoices_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, [
        'Invoice Number',
        'Order Number',
        'Customer',
        'Supplier',
        'Amount (TSh)',
        'Status',
        'Date'
    ]);
    
    foreach ($invoices as $inv) {
        $amount = $inv['total_amount'] ?? 0;
        fputcsv($output, [
            $inv['invoice_number'],
            $inv['order_number'],
            $inv['customer_name'],
            $inv['supplier_name'] ?? 'N/A',
            number_format($amount, 2),
            ucfirst($inv['status']),
            date('Y-m-d H:i', strtotime($inv['generated_at']))
        ]);
    }
    fclose($output);
    exit();
}

// =============================================
// HANDLE ACTIONS
// =============================================
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    verifyCSRFToken($_POST['csrf_token'] ?? '');
    
    $action = $_POST['action'] ?? '';
    $invoice_id = (int)($_POST['invoice_id'] ?? 0);
    
    if ($action == 'delete' && $invoice_id > 0) {
        $sql = "DELETE FROM invoices WHERE id = ? AND status IN ('draft', 'cancelled')";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(1, $invoice_id, PDO::PARAM_INT);
        if ($stmt->execute() && $stmt->rowCount() > 0) {
            log_activity('admin', $_SESSION['user_id'], 'delete_invoice', "Deleted invoice ID: $invoice_id");
            $_SESSION['success'] = 'Invoice deleted successfully.';
        } else {
            $_SESSION['error'] = 'Delete failed. Only draft or cancelled invoices can be deleted.';
        }
        header('Location: invoices.php');
        exit();
    }
    
    if ($action == 'generate' && $invoice_id > 0) {
        $sql = "SELECT * FROM invoices WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(1, $invoice_id, PDO::PARAM_INT);
        $stmt->execute();
        $invoice = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($invoice && $invoice['status'] == 'draft') {
            $update_sql = "UPDATE invoices SET status = 'sent', sent_at = NOW() WHERE id = ?";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bindParam(1, $invoice_id, PDO::PARAM_INT);
            if ($update_stmt->execute()) {
                log_activity('admin', $_SESSION['user_id'], 'generate_invoice', "Generated invoice: {$invoice['invoice_number']}");
                $_SESSION['success'] = 'Invoice generated and sent successfully.';
            }
        }
        header('Location: invoices.php');
        exit();
    }
}
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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f0f2f5;
        }
        
        /* Top Navigation */
        .top-navbar {
            background: linear-gradient(135deg, #1a2a6c, #b21f1f, #fdbb4d);
            padding: 15px 0;
            color: white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .top-navbar .brand {
            font-size: 1.4rem;
            font-weight: 700;
        }
        .top-navbar .brand i { margin-right: 8px; }
        .top-navbar .nav-link {
            color: rgba(255,255,255,0.9) !important;
            font-weight: 500;
            padding: 8px 16px;
        }
        .top-navbar .nav-link:hover { color: white !important; }
        .top-navbar .nav-link.active {
            background: rgba(255,255,255,0.15);
            border-radius: 8px;
            color: white !important;
        }
        .top-navbar .dropdown-menu {
            background: #1a1a2e;
            border: none;
        }
        .top-navbar .dropdown-item {
            color: rgba(255,255,255,0.8);
        }
        .top-navbar .dropdown-item:hover {
            background: rgba(255,255,255,0.1);
            color: white;
        }
        .admin-avatar {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            background: rgba(255,255,255,0.2);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 1rem;
        }
        
        /* Sidebar */
        .sidebar {
            position: fixed;
            top: 80px;
            left: 0;
            height: calc(100vh - 80px);
            width: 250px;
            background: white;
            box-shadow: 2px 0 10px rgba(0,0,0,0.05);
            padding: 20px 0;
            overflow-y: auto;
            z-index: 100;
        }
        .sidebar .nav-link {
            color: #333 !important;
            padding: 12px 25px;
            border-radius: 0;
            border-left: 3px solid transparent;
            transition: all 0.3s;
        }
        .sidebar .nav-link:hover {
            background: #f8f9fa;
            color: #0d6efd !important;
        }
        .sidebar .nav-link.active {
            background: #f0f7ff;
            color: #0d6efd !important;
            border-left-color: #0d6efd;
        }
        .sidebar .nav-link i { width: 22px; margin-right: 10px; }
        .sidebar .nav-link .badge { float: right; margin-top: 2px; }
        .sidebar .sidebar-footer {
            position: absolute;
            bottom: 0;
            width: 100%;
            padding: 15px;
            border-top: 1px solid #eee;
        }
        .sidebar .sidebar-footer a {
            color: #6c757d;
            text-decoration: none;
        }
        .sidebar .sidebar-footer a:hover { color: #0d6efd; }
        
        /* Main Content */
        .main-content {
            margin-left: 250px;
            padding: 30px;
            margin-top: 80px;
            min-height: calc(100vh - 80px);
        }
        
        /* Stats Cards */
        .stats-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            transition: all 0.3s ease;
        }
        .stats-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        .stats-card .stats-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
        }
        .stats-card .stats-number {
            font-size: 2rem;
            font-weight: 700;
        }
        
        /* Filter Section */
        .filter-section {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        /* Invoice Table */
        .invoice-table td { vertical-align: middle; }
        .invoice-table .badge { font-size: 12px; padding: 6px 12px; }
        .status-draft { background-color: #6c757d; color: #fff; }
        .status-sent { background-color: #0dcaf0; color: #000; }
        .status-paid { background-color: #198754; color: #fff; }
        .status-overdue { background-color: #dc3545; color: #fff; }
        .status-cancelled { background-color: #6c757d; color: #fff; }
        .action-dropdown .dropdown-item i { width: 20px; }
        
        /* Toast */
        .toast-container {
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 9999;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s;
            }
            .sidebar.show { transform: translateX(0); }
            .main-content { margin-left: 0; padding: 15px; }
            .filter-section .row > div { margin-bottom: 10px; }
            .stats-card .stats-number { font-size: 1.4rem; }
        }
        
        /* Mobile Toggle Button */
        .sidebar-toggle {
            display: none;
            background: none;
            border: none;
            color: white;
            font-size: 1.5rem;
            padding: 0 10px;
        }
        @media (max-width: 768px) {
            .sidebar-toggle { display: block; }
        }
        
        /* Scrollbar */
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: #f1f1f1; }
        ::-webkit-scrollbar-thumb { background: #888; border-radius: 3px; }
        ::-webkit-scrollbar-thumb:hover { background: #555; }
    </style>
</head>
<body>

<!-- ===================================================== -->
<!-- TOP NAVBAR -->
<!-- ===================================================== -->
<nav class="top-navbar">
    <div class="container-fluid px-4">
        <div class="d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center">
                <button class="sidebar-toggle me-3" onclick="toggleSidebar()">
                    <i class="fas fa-bars"></i>
                </button>
                <span class="brand">
                    <i class="fas fa-microchip"></i> TechProcure
                    <small class="opacity-75 ms-2">Admin Panel</small>
                </span>
            </div>
            
            <div class="d-flex align-items-center gap-3">
                <a href="../../index.php" class="text-white text-decoration-none small">
                    <i class="fas fa-globe me-1"></i> View Site
                </a>
                <div class="dropdown">
                    <button class="btn btn-link text-white text-decoration-none dropdown-toggle" data-bs-toggle="dropdown">
                        <span class="admin-avatar">
                            <?php echo strtoupper(substr($admin_name, 0, 1)); ?>
                        </span>
                        <span class="ms-2 d-none d-sm-inline"><?php echo htmlspecialchars($admin_name); ?></span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="../profile.php"><i class="fas fa-user me-2"></i>Profile</a></li>
                        <li><a class="dropdown-item" href="../settings.php"><i class="fas fa-cog me-2"></i>Settings</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-danger" href="../../auth/logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</nav>

<!-- ===================================================== -->
<!-- SIDEBAR -->
<!-- ===================================================== -->
<div class="sidebar" id="sidebar">
    <ul class="nav flex-column">
        <li class="nav-item">
            <a class="nav-link" href="../dashboard.php">
                <i class="fas fa-tachometer-alt"></i> Dashboard
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="../users/manage-users.php">
                <i class="fas fa-users"></i> Users
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="../suppliers/manage-suppliers.php">
                <i class="fas fa-truck"></i> Suppliers
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="../products/manage-products.php">
                <i class="fas fa-box"></i> Products
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="../orders/manage-orders.php">
                <i class="fas fa-shopping-cart"></i> Orders
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link active" href="invoices.php">
                <i class="fas fa-file-invoice"></i> Invoices
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="../payments/transactions.php">
                <i class="fas fa-credit-card"></i> Payments
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="../reports/sales-report.php">
                <i class="fas fa-chart-line"></i> Reports
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="../settings/general-settings.php">
                <i class="fas fa-cog"></i> System Settings
            </a>
        </li>
    </ul>
    
    <div class="sidebar-footer">
        <a href="../../auth/logout.php">
            <i class="fas fa-sign-out-alt me-2"></i> Logout
        </a>
    </div>
</div>

<!-- ===================================================== -->
<!-- MAIN CONTENT -->
<!-- ===================================================== -->
<div class="main-content" id="mainContent">
    
    <!-- Page Header -->
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">
            <i class="fas fa-file-invoice me-2 text-primary"></i>Invoices Management
        </h1>
        <div class="btn-toolbar mb-2 mb-md-0">
            <button class="btn btn-sm btn-outline-secondary me-2" onclick="window.print()">
                <i class="fas fa-print me-1"></i>Print
            </button>
            <button class="btn btn-sm btn-outline-success me-2" onclick="exportCSV()">
                <i class="fas fa-file-csv me-1"></i>Export CSV
            </button>
            <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#filterModal">
                <i class="fas fa-filter me-1"></i>Filter
            </button>
            <a href="generate-invoice.php" class="btn btn-sm btn-primary">
                <i class="fas fa-plus me-1"></i>Generate Invoice
            </a>
        </div>
    </div>
    
    <!-- Alert Messages -->
    <?php if (isset($_SESSION['success'])): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <i class="fas fa-check-circle me-2"></i><?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['error'])): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <i class="fas fa-exclamation-circle me-2"></i><?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>
    
    <!-- Statistics Cards -->
    <div class="row g-4 mb-4">
        <div class="col-md-3 col-sm-6">
            <div class="stats-card">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <span class="text-muted">Total Invoices</span>
                        <div class="stats-number"><?php echo number_format($stats['total']); ?></div>
                    </div>
                    <div class="stats-icon bg-primary bg-opacity-10 text-primary">
                        <i class="fas fa-file-invoice"></i>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3 col-sm-6">
            <div class="stats-card">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <span class="text-muted">Total Revenue</span>
                        <div class="stats-number"><?php echo format_price($stats['total_amount'] ?? 0); ?></div>
                    </div>
                    <div class="stats-icon bg-success bg-opacity-10 text-success">
                        <i class="fas fa-money-bill-wave"></i>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3 col-sm-6">
            <div class="stats-card">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <span class="text-muted">Today's Invoices</span>
                        <div class="stats-number"><?php echo number_format($stats['today']['count']); ?></div>
                        <small class="text-muted"><?php echo format_price($stats['today']['total'] ?? 0); ?></small>
                    </div>
                    <div class="stats-icon bg-info bg-opacity-10 text-info">
                        <i class="fas fa-calendar-day"></i>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3 col-sm-6">
            <div class="stats-card">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <span class="text-muted">Status Breakdown</span>
                        <div class="stats-number" style="font-size: 1.2rem;">
                            <?php 
                            $status_labels = ['draft' => 'secondary', 'sent' => 'info', 'paid' => 'success', 'overdue' => 'danger', 'cancelled' => 'secondary'];
                            foreach ($stats['by_status'] as $s => $count): 
                            ?>
                            <span class="badge bg-<?php echo $status_labels[$s] ?? 'secondary'; ?> me-1"><?php echo ucfirst($s); ?>: <?php echo $count; ?></span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="stats-icon bg-warning bg-opacity-10 text-warning">
                        <i class="fas fa-chart-pie"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Active Filters -->
    <?php if($search || $status || $date_from || $date_to): ?>
    <div class="filter-section">
        <div class="d-flex flex-wrap align-items-center gap-2">
            <span class="fw-bold me-2">Active Filters:</span>
            <?php if($search): ?>
            <span class="badge bg-primary">
                <i class="fas fa-search me-1"></i><?php echo htmlspecialchars($search); ?>
                <a href="?<?php echo http_build_query(array_merge($_GET, ['search' => ''])); ?>" class="text-white ms-1">&times;</a>
            </span>
            <?php endif; ?>
            <?php if($status): ?>
            <span class="badge bg-secondary">
                Status: <?php echo ucfirst($status); ?>
                <a href="?<?php echo http_build_query(array_merge($_GET, ['status' => ''])); ?>" class="text-white ms-1">&times;</a>
            </span>
            <?php endif; ?>
            <?php if($date_from || $date_to): ?>
            <span class="badge bg-secondary">
                Date: <?php echo $date_from ?: 'Any'; ?> to <?php echo $date_to ?: 'Any'; ?>
                <a href="?<?php echo http_build_query(array_merge($_GET, ['date_from' => '', 'date_to' => ''])); ?>" class="text-white ms-1">&times;</a>
            </span>
            <?php endif; ?>
            <a href="invoices.php" class="btn btn-sm btn-link text-danger">Clear All</a>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Invoices Table -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">
                <i class="fas fa-list me-2"></i>Invoice List
                <span class="badge bg-secondary ms-2"><?php echo number_format($total_invoices); ?></span>
            </h5>
            <div>
                <span class="text-muted small">Showing <?php echo count($invoices); ?> of <?php echo number_format($total_invoices); ?></span>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover invoice-table mb-0">
                    <thead class="table-light">
                        <tr>
                            <th width="50">#</th>
                            <th>Invoice Number</th>
                            <th>Order</th>
                            <th>Customer</th>
                            <th>Supplier</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th width="150">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($invoices)): ?>
                        <tr>
                            <td colspan="9" class="text-center py-4">
                                <i class="fas fa-inbox fa-3x text-muted mb-3 d-block"></i>
                                <p class="text-muted">No invoices found</p>
                            </td>
                        </tr>
                        <?php else: ?>
                            <?php foreach($invoices as $index => $inv): ?>
                            <tr>
                                <td><?php echo $offset + $index + 1; ?></td>
                                <td>
                                    <code class="fw-bold"><?php echo htmlspecialchars($inv['invoice_number']); ?></code>
                                </td>
                                <td>
                                    <a href="../orders/order-details.php?id=<?php echo $inv['order_id']; ?>" class="text-decoration-none">
                                        <?php echo htmlspecialchars($inv['order_number']); ?>
                                    </a>
                                </td>
                                <td>
                                    <strong><?php echo htmlspecialchars($inv['customer_name']); ?></strong>
                                    <br>
                                    <small class="text-muted"><?php echo htmlspecialchars($inv['customer_email']); ?></small>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($inv['supplier_name'] ?? 'N/A'); ?>
                                </td>
                                <td>
                                    <?php 
                                    $amount = isset($inv['total_amount']) ? $inv['total_amount'] : 0;
                                    ?>
                                    <strong class="text-success"><?php echo format_price($amount); ?></strong>
                                </td>
                                <td>
                                    <?php
                                    $status_colors = [
                                        'draft' => 'secondary',
                                        'sent' => 'info',
                                        'paid' => 'success',
                                        'overdue' => 'danger',
                                        'cancelled' => 'secondary'
                                    ];
                                    $color = $status_colors[$inv['status']] ?? 'secondary';
                                    ?>
                                    <span class="badge bg-<?php echo $color; ?>">
                                        <?php echo ucfirst($inv['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div title="<?php echo date('Y-m-d H:i:s', strtotime($inv['generated_at'])); ?>">
                                        <?php echo time_ago($inv['generated_at']); ?>
                                    </div>
                                    <small class="text-muted"><?php echo date('M d, Y', strtotime($inv['generated_at'])); ?></small>
                                    <?php if(isset($inv['sent_at']) && $inv['sent_at']): ?>
                                    <br><small class="text-info">Sent: <?php echo date('M d, Y', strtotime($inv['sent_at'])); ?></small>
                                    <?php endif; ?>
                                    <?php if(isset($inv['paid_at']) && $inv['paid_at']): ?>
                                    <br><small class="text-success">Paid: <?php echo date('M d, Y', strtotime($inv['paid_at'])); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="btn-group action-dropdown">
                                        <button class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                                            <i class="fas fa-cog"></i>
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end">
                                            <li>
                                                <a class="dropdown-item" href="invoice-details.php?id=<?php echo $inv['id']; ?>">
                                                    <i class="fas fa-eye text-info me-2"></i>View Details
                                                </a>
                                            </li>
                                            <li>
                                                <a class="dropdown-item" href="download-invoice.php?id=<?php echo $inv['id']; ?>">
                                                    <i class="fas fa-download text-primary me-2"></i>Download PDF
                                                </a>
                                            </li>
                                            <?php if($inv['status'] == 'draft'): ?>
                                            <li><hr class="dropdown-divider"></li>
                                            <li>
                                                <form method="POST" action="" class="d-inline">
                                                    <?php echo csrf_field(); ?>
                                                    <input type="hidden" name="action" value="generate">
                                                    <input type="hidden" name="invoice_id" value="<?php echo $inv['id']; ?>">
                                                    <button type="submit" class="dropdown-item text-success">
                                                        <i class="fas fa-paper-plane me-2"></i>Generate & Send
                                                    </button>
                                                </form>
                                            </li>
                                            <?php endif; ?>
                                            <?php if($inv['status'] == 'draft' || $inv['status'] == 'cancelled'): ?>
                                            <li><hr class="dropdown-divider"></li>
                                            <li>
                                                <form method="POST" action="" class="d-inline" onsubmit="return confirmDelete()">
                                                    <?php echo csrf_field(); ?>
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="invoice_id" value="<?php echo $inv['id']; ?>">
                                                    <button type="submit" class="dropdown-item text-danger">
                                                        <i class="fas fa-trash me-2"></i>Delete
                                                    </button>
                                                </form>
                                            </li>
                                            <?php endif; ?>
                                            <?php if($inv['status'] != 'paid' && $inv['status'] != 'cancelled'): ?>
                                            <li><hr class="dropdown-divider"></li>
                                            <li>
                                                <a class="dropdown-item" href="#" onclick="markPaid(<?php echo $inv['id']; ?>)">
                                                    <i class="fas fa-check-circle text-success me-2"></i>Mark as Paid
                                                </a>
                                            </li>
                                            <?php endif; ?>
                                        </ul>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Pagination -->
        <div class="card-footer d-flex flex-wrap justify-content-between align-items-center">
            <div>
                <span class="text-muted small">
                    Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $items_per_page, $total_invoices); ?> of <?php echo number_format($total_invoices); ?> invoices
                </span>
            </div>
            
            <?php if($total_pages > 1): ?>
            <nav>
                <ul class="pagination pagination-sm mb-0">
                    <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page-1])); ?>">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                    </li>
                    
                    <?php
                    $start_page = max(1, $page - 2);
                    $end_page = min($total_pages, $page + 2);
                    
                    if ($start_page > 1) {
                        echo '<li class="page-item"><a class="page-link" href="?' . http_build_query(array_merge($_GET, ['page' => 1])) . '">1</a></li>';
                        if ($start_page > 2) echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                    }
                    
                    for ($i = $start_page; $i <= $end_page; $i++):
                    ?>
                    <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>"><?php echo $i; ?></a>
                    </li>
                    <?php endfor; ?>
                    
                    <?php
                    if ($end_page < $total_pages) {
                        if ($end_page < $total_pages - 1) echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                        echo '<li class="page-item"><a class="page-link" href="?' . http_build_query(array_merge($_GET, ['page' => $total_pages])) . '">' . $total_pages . '</a></li>';
                    }
                    ?>
                    
                    <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page+1])); ?>">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    </li>
                </ul>
            </nav>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- ===================================================== -->
<!-- FILTER MODAL -->
<!-- ===================================================== -->
<div class="modal fade" id="filterModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="GET" action="">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-filter me-2"></i>Filter Invoices
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Search</label>
                            <input type="text" name="search" class="form-control" placeholder="Search by invoice #, order #, customer..." value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select">
                                <option value="">All Statuses</option>
                                <option value="draft" <?php echo $status == 'draft' ? 'selected' : ''; ?>>Draft</option>
                                <option value="sent" <?php echo $status == 'sent' ? 'selected' : ''; ?>>Sent</option>
                                <option value="paid" <?php echo $status == 'paid' ? 'selected' : ''; ?>>Paid</option>
                                <option value="overdue" <?php echo $status == 'overdue' ? 'selected' : ''; ?>>Overdue</option>
                                <option value="cancelled" <?php echo $status == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                            </select>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">Date From</label>
                            <input type="date" name="date_from" class="form-control" value="<?php echo $date_from; ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Date To</label>
                            <input type="date" name="date_to" class="form-control" value="<?php echo $date_to; ?>">
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">Sort By</label>
                            <select name="sort" class="form-select">
                                <option value="newest" <?php echo $sort == 'newest' ? 'selected' : ''; ?>>Newest First</option>
                                <option value="oldest" <?php echo $sort == 'oldest' ? 'selected' : ''; ?>>Oldest First</option>
                                <option value="amount_high" <?php echo $sort == 'amount_high' ? 'selected' : ''; ?>>Amount: High to Low</option>
                                <option value="amount_low" <?php echo $sort == 'amount_low' ? 'selected' : ''; ?>>Amount: Low to High</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <a href="invoices.php" class="btn btn-outline-danger">Clear Filters</a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search me-1"></i>Apply Filters
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

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
// Toggle sidebar on mobile
function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('show');
}

// Close sidebar when clicking outside on mobile
document.addEventListener('click', function(event) {
    const sidebar = document.getElementById('sidebar');
    const toggleBtn = document.querySelector('.sidebar-toggle');
    if (window.innerWidth <= 768) {
        if (!sidebar.contains(event.target) && !toggleBtn.contains(event.target)) {
            sidebar.classList.remove('show');
        }
    }
});

// Confirm delete
function confirmDelete() {
    return confirm('Are you sure you want to delete this invoice? This action cannot be undone.');
}

// Export CSV
function exportCSV() {
    const url = new URL(window.location.href);
    url.searchParams.set('export', 'csv');
    window.location.href = url.toString();
}

// Mark as paid
function markPaid(invoiceId) {
    if (confirm('Mark this invoice as paid?')) {
        $.ajax({
            url: 'ajax/update-invoice.php',
            type: 'POST',
            data: {
                action: 'mark_paid',
                invoice_id: invoiceId,
                csrf_token: $('meta[name="csrf-token"]').attr('content')
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    showToast('Success', 'Invoice marked as paid', 'success');
                    setTimeout(function() { location.reload(); }, 1000);
                } else {
                    showToast('Error', response.message || 'Update failed', 'error');
                }
            },
            error: function() {
                showToast('Error', 'Failed to update invoice', 'error');
            }
        });
    }
}

// Toast notification
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

// Auto-close alerts
setTimeout(function() {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(function(alert) {
        const bsAlert = new bootstrap.Alert(alert);
        setTimeout(function() { bsAlert.close(); }, 500);
    });
}, 5000);
</script>

</body>
</html>