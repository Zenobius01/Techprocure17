<?php
/**
 * TechProcure Tanzania - Admin Transactions Management
 * File: admin/payments/transactions.php
 * Description: Display all payment transactions from database
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
        if (!$datetime) return 'Never';
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

if (!function_exists('sanitize')) {
    function sanitize($input) {
        if (is_array($input)) {
            return array_map('sanitize', $input);
        }
        return htmlspecialchars(strip_tags(trim($input)));
    }
}

// =============================================
// GET DATABASE CONNECTION
// =============================================
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

$page_title = 'Payment Transactions - Admin Panel';

// =============================================
// GET FILTER PARAMETERS
// =============================================
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$status = isset($_GET['status']) ? sanitize($_GET['status']) : '';
$method = isset($_GET['method']) ? sanitize($_GET['method']) : '';
$date_from = isset($_GET['date_from']) ? sanitize($_GET['date_from']) : '';
$date_to = isset($_GET['date_to']) ? sanitize($_GET['date_to']) : '';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'newest';
$export = isset($_GET['export']) ? $_GET['export'] : '';

$items_per_page = ADMIN_ITEMS_PER_PAGE;
$offset = ($page - 1) * $items_per_page;

// =============================================
// BUILD QUERY CONDITIONS - USING BUYER_ID (since orders table uses buyer_id)
// =============================================
$where_conditions = ["1=1"];
$params = [];

// Search filter
if ($search) {
    $where_conditions[] = "(p.transaction_id LIKE ? OR p.mpesa_receipt LIKE ? OR o.order_number LIKE ? OR b.company_name LIKE ? OR b.email LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

// Status filter
if ($status) {
    $where_conditions[] = "p.payment_status = ?";
    $params[] = $status;
}

// Method filter
if ($method) {
    $where_conditions[] = "p.payment_method = ?";
    $params[] = $method;
}

// Date range filter
if ($date_from) {
    $where_conditions[] = "DATE(p.created_at) >= ?";
    $params[] = $date_from;
}
if ($date_to) {
    $where_conditions[] = "DATE(p.created_at) <= ?";
    $params[] = $date_to;
}

$where_clause = implode(" AND ", $where_conditions);

// =============================================
// BUILD ORDER BY CLAUSE
// =============================================
switch($sort) {
    case 'oldest': $order_by = "p.created_at ASC"; break;
    case 'amount_high': $order_by = "p.amount_tsh DESC"; break;
    case 'amount_low': $order_by = "p.amount_tsh ASC"; break;
    default: $order_by = "p.created_at DESC";
}

// =============================================
// FETCH ALL TRANSACTIONS
// =============================================
$transactions = [];
$total_transactions = 0;
$total_pages = 0;

try {
    // Get all transactions with proper joins - using buyer_id
    $sql = "SELECT p.*, 
            o.order_number,
            o.order_status,
            o.total_amount_tsh as order_total,
            b.company_name as buyer_name,
            b.email as buyer_email,
            b.contact_person as buyer_contact,
            s.company_name as supplier_name,
            s.contact_person as supplier_contact
            FROM payments p
            JOIN orders o ON p.order_id = o.id
            JOIN customers b ON o.buyer_id = b.id
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
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get total count
    $count_sql = "SELECT COUNT(*) as total 
                  FROM payments p
                  JOIN orders o ON p.order_id = o.id
                  JOIN customers b ON o.buyer_id = b.id
                  LEFT JOIN suppliers s ON o.supplier_id = s.id
                  WHERE $where_clause";
    $count_stmt = $conn->prepare($count_sql);
    
    $param_index = 1;
    foreach ($params as $param) {
        $count_stmt->bindValue($param_index, $param, PDO::PARAM_STR);
        $param_index++;
    }
    
    $count_stmt->execute();
    $total_transactions = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
    $total_pages = ceil($total_transactions / $items_per_page);
    
} catch (PDOException $e) {
    $error_message = "Database error: " . $e->getMessage();
    $transactions = [];
}

// =============================================
// GET STATISTICS
// =============================================
$stats = [];

// Total transactions
$result = $conn->query("SELECT COUNT(*) as total FROM payments");
$stats['total'] = $result ? $result->fetch(PDO::FETCH_ASSOC)['total'] : 0;

// Total revenue
try {
    $sql = "SELECT SUM(p.amount_tsh) as total_amount FROM payments p WHERE p.payment_status = 'completed'";
    $result = $conn->query($sql);
    $stats['total_amount'] = $result ? $result->fetch(PDO::FETCH_ASSOC)['total_amount'] : 0;
} catch (PDOException $e) {
    $stats['total_amount'] = 0;
}

// Today's transactions
try {
    $sql = "SELECT COUNT(*) as count, SUM(amount_tsh) as total FROM payments WHERE DATE(created_at) = CURDATE()";
    $result = $conn->query($sql);
    $stats['today'] = $result ? $result->fetch(PDO::FETCH_ASSOC) : ['count' => 0, 'total' => 0];
} catch (PDOException $e) {
    $stats['today'] = ['count' => 0, 'total' => 0];
}

// Average transaction
try {
    $sql = "SELECT AVG(amount_tsh) as avg_amount FROM payments WHERE payment_status = 'completed'";
    $result = $conn->query($sql);
    $stats['avg_amount'] = $result ? $result->fetch(PDO::FETCH_ASSOC)['avg_amount'] : 0;
} catch (PDOException $e) {
    $stats['avg_amount'] = 0;
}

// Transactions by status
try {
    $sql = "SELECT payment_status, COUNT(*) as count FROM payments GROUP BY payment_status";
    $result = $conn->query($sql);
    $stats['by_status'] = [];
    if ($result) {
        while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
            $stats['by_status'][$row['payment_status']] = $row['count'];
        }
    }
} catch (PDOException $e) {
    $stats['by_status'] = [];
}

// Transactions by method
try {
    $sql = "SELECT payment_method, COUNT(*) as count FROM payments GROUP BY payment_method";
    $result = $conn->query($sql);
    $stats['by_method'] = [];
    if ($result) {
        while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
            $stats['by_method'][$row['payment_method']] = $row['count'];
        }
    }
} catch (PDOException $e) {
    $stats['by_method'] = [];
}

// =============================================
// GET PAYMENT METHODS FOR FILTER
// =============================================
$payment_methods = [
    'mpesa' => 'M-Pesa',
    'airtel_money' => 'Airtel Money',
    'tigo_pesa' => 'Tigo Pesa',
    'halopesa' => 'Halopesa',
    'azam_pesa' => 'Azam Pesa',
    'bank_transfer' => 'Bank Transfer',
    'visa' => 'Visa',
    'mastercard' => 'Mastercard',
    'escrow' => 'Escrow'
];

// =============================================
// HELPER FUNCTIONS
// =============================================
function get_payment_status_label($status) {
    $labels = [
        'pending' => 'warning',
        'processing' => 'info',
        'completed' => 'success',
        'failed' => 'danger',
        'refunded' => 'secondary'
    ];
    return $labels[$status] ?? 'secondary';
}

function get_payment_method_icon($method) {
    $icons = [
        'mpesa' => 'fa-mobile-alt',
        'airtel_money' => 'fa-mobile-alt',
        'tigo_pesa' => 'fa-mobile-alt',
        'halopesa' => 'fa-mobile-alt',
        'azam_pesa' => 'fa-mobile-alt',
        'bank_transfer' => 'fa-university',
        'visa' => 'fa-cc-visa',
        'mastercard' => 'fa-cc-mastercard',
        'escrow' => 'fa-hand-holding-usd'
    ];
    return $icons[$method] ?? 'fa-credit-card';
}

// =============================================
// HANDLE EXPORT
// =============================================
if ($export == 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="transactions_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, [
        'Transaction ID',
        'Order Number',
        'Amount (TSh)',
        'Method',
        'Status',
        'Buyer',
        'Supplier',
        'Date'
    ]);
    
    foreach ($transactions as $t) {
        fputcsv($output, [
            $t['transaction_id'] ?? $t['id'],
            $t['order_number'],
            number_format($t['amount_tsh'], 2),
            $payment_methods[$t['payment_method']] ?? $t['payment_method'],
            ucfirst($t['payment_status']),
            $t['buyer_name'],
            $t['supplier_name'] ?? 'N/A',
            date('Y-m-d H:i', strtotime($t['created_at']))
        ]);
    }
    fclose($output);
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f5f7fb; color: #333; }
        
        .page-header {
            background: linear-gradient(135deg, #1a2a6c, #b21f1f, #fdbb4d);
            padding: 30px 0;
            color: white;
            margin-bottom: 30px;
        }
        .page-header h1 { font-weight: 700; }
        .page-header .breadcrumb { background: transparent; padding: 0; margin: 0; }
        .page-header .breadcrumb-item a { color: rgba(255,255,255,0.8); text-decoration: none; }
        .page-header .breadcrumb-item a:hover { color: white; }
        .page-header .breadcrumb-item.active { color: white; }
        
        .stats-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            transition: all 0.3s ease;
            height: 100%;
        }
        .stats-card:hover { transform: translateY(-5px); box-shadow: 0 10px 30px rgba(0,0,0,0.1); }
        .stats-card .stats-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
        }
        .stats-card .stats-number { font-size: 2rem; font-weight: 700; }
        .stats-card .stats-label { font-size: 0.85rem; color: #6c757d; }
        
        .filter-section {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .table-container {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        .transaction-table td { vertical-align: middle; padding: 12px 15px; }
        .transaction-table th { background: #f8f9fa; font-weight: 600; padding: 12px 15px; }
        .transaction-table .badge { font-size: 12px; padding: 6px 12px; }
        .method-badge { font-size: 12px; padding: 4px 10px; border-radius: 20px; background: #f0f0f0; }
        .amount-positive { color: #198754; font-weight: 600; }
        
        .toast-container { position: fixed; bottom: 20px; right: 20px; z-index: 1050; }
        
        .status-breakdown {
            display: flex;
            flex-wrap: wrap;
            gap: 5px;
        }
        .status-breakdown .badge { font-size: 12px; padding: 5px 10px; }
        
        @media (max-width: 768px) {
            .stats-card .stats-number { font-size: 1.5rem; }
        }
    </style>
</head>
<body>

<!-- ===================================================== -->
<!-- NAVBAR -->
<!-- ===================================================== -->
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container-fluid">
        <a class="navbar-brand" href="#"><i class="fas fa-microchip me-2"></i>TechProcure Admin</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item"><a class="nav-link" href="../dashboard.php">Dashboard</a></li>
                <li class="nav-item"><a class="nav-link" href="../users/manage-users.php">Users</a></li>
                <li class="nav-item"><a class="nav-link" href="../suppliers/manage-suppliers.php">Suppliers</a></li>
                <li class="nav-item"><a class="nav-link" href="../products/manage-products.php">Products</a></li>
                <li class="nav-item"><a class="nav-link" href="../orders/manage-orders.php">Orders</a></li>
                <li class="nav-item"><a class="nav-link active" href="transactions.php">Transactions</a></li>
            </ul>
            <ul class="navbar-nav">
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
                        <i class="fas fa-user-circle me-1"></i><?php echo $_SESSION['user_name'] ?? 'Admin'; ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="../profile.php">Profile</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-danger" href="../../auth/logout.php">Logout</a></li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>

<!-- ===================================================== -->
<!-- PAGE HEADER -->
<!-- ===================================================== -->
<div class="page-header">
    <div class="container-fluid px-4">
        <div class="row align-items-center">
            <div class="col-md-6">
                <h1><i class="fas fa-credit-card me-2"></i>Payment Transactions</h1>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="../dashboard.php">Dashboard</a></li>
                        <li class="breadcrumb-item active">Transactions</li>
                    </ol>
                </nav>
            </div>
            <div class="col-md-6 text-md-end mt-3 mt-md-0">
                <span class="text-white-50">
                    <i class="fas fa-clock me-1"></i><?php echo date('F d, Y - H:i'); ?>
                </span>
            </div>
        </div>
    </div>
</div>

<!-- ===================================================== -->
<!-- MAIN CONTENT -->
<!-- ===================================================== -->
<div class="container-fluid px-4">
    
    <!-- Statistics Cards -->
    <div class="row g-4 mb-4">
        <div class="col-xl-3 col-lg-6 col-md-6">
            <div class="stats-card">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="stats-number"><?php echo number_format($stats['total'] ?? 0); ?></div>
                        <div class="stats-label">Total Transactions</div>
                    </div>
                    <div class="stats-icon bg-primary bg-opacity-10 text-primary">
                        <i class="fas fa-receipt"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-lg-6 col-md-6">
            <div class="stats-card">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="stats-number"><?php echo format_price($stats['total_amount'] ?? 0); ?></div>
                        <div class="stats-label">Total Revenue</div>
                    </div>
                    <div class="stats-icon bg-success bg-opacity-10 text-success">
                        <i class="fas fa-money-bill-wave"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-lg-6 col-md-6">
            <div class="stats-card">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="stats-number"><?php echo number_format($stats['today']['count'] ?? 0); ?></div>
                        <div class="stats-label">Today's Transactions</div>
                        <small class="text-muted"><?php echo format_price($stats['today']['total'] ?? 0); ?></small>
                    </div>
                    <div class="stats-icon bg-info bg-opacity-10 text-info">
                        <i class="fas fa-calendar-day"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-lg-6 col-md-6">
            <div class="stats-card">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="stats-number"><?php echo format_price($stats['avg_amount'] ?? 0); ?></div>
                        <div class="stats-label">Average Transaction</div>
                    </div>
                    <div class="stats-icon bg-warning bg-opacity-10 text-warning">
                        <i class="fas fa-chart-line"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Status & Method Breakdown -->
    <div class="row g-4 mb-4">
        <div class="col-md-6">
            <div class="stats-card">
                <div class="stats-label">Status Breakdown</div>
                <div class="status-breakdown mt-2">
                    <?php 
                    $status_labels = [
                        'pending' => 'warning',
                        'processing' => 'info',
                        'completed' => 'success',
                        'failed' => 'danger',
                        'refunded' => 'secondary'
                    ];
                    foreach ($stats['by_status'] as $s => $count): 
                    ?>
                    <span class="badge bg-<?php echo $status_labels[$s] ?? 'secondary'; ?>">
                        <?php echo ucfirst($s); ?>: <?php echo $count; ?>
                    </span>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="stats-card">
                <div class="stats-label">Method Breakdown</div>
                <div class="d-flex flex-wrap gap-2 mt-2">
                    <?php foreach ($stats['by_method'] as $m => $count): 
                        $method_name = $payment_methods[$m] ?? ucfirst($m);
                    ?>
                    <span class="badge bg-secondary">
                        <?php echo $method_name; ?>: <?php echo $count; ?>
                    </span>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Filter Section -->
    <div class="filter-section">
        <form method="GET" action="" id="filterForm">
            <div class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label">Search</label>
                    <input type="text" name="search" class="form-control form-control-sm" 
                           placeholder="Search by ID, order, buyer..." 
                           value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select form-select-sm">
                        <option value="">All Statuses</option>
                        <option value="pending" <?php echo $status == 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="processing" <?php echo $status == 'processing' ? 'selected' : ''; ?>>Processing</option>
                        <option value="completed" <?php echo $status == 'completed' ? 'selected' : ''; ?>>Completed</option>
                        <option value="failed" <?php echo $status == 'failed' ? 'selected' : ''; ?>>Failed</option>
                        <option value="refunded" <?php echo $status == 'refunded' ? 'selected' : ''; ?>>Refunded</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Method</label>
                    <select name="method" class="form-select form-select-sm">
                        <option value="">All Methods</option>
                        <?php foreach($payment_methods as $key => $name): ?>
                        <option value="<?php echo $key; ?>" <?php echo $method == $key ? 'selected' : ''; ?>><?php echo $name; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Date Range</label>
                    <div class="row g-1">
                        <div class="col-6">
                            <input type="date" name="date_from" class="form-control form-control-sm" value="<?php echo $date_from; ?>">
                        </div>
                        <div class="col-6">
                            <input type="date" name="date_to" class="form-control form-control-sm" value="<?php echo $date_to; ?>">
                        </div>
                    </div>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Sort By</label>
                    <select name="sort" class="form-select form-select-sm">
                        <option value="newest" <?php echo $sort == 'newest' ? 'selected' : ''; ?>>Newest First</option>
                        <option value="oldest" <?php echo $sort == 'oldest' ? 'selected' : ''; ?>>Oldest First</option>
                        <option value="amount_high" <?php echo $sort == 'amount_high' ? 'selected' : ''; ?>>Amount: High to Low</option>
                        <option value="amount_low" <?php echo $sort == 'amount_low' ? 'selected' : ''; ?>>Amount: Low to High</option>
                    </select>
                </div>
            </div>
            <div class="row mt-3">
                <div class="col-12">
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="fas fa-search me-1"></i> Filter
                    </button>
                    <a href="transactions.php" class="btn btn-secondary btn-sm">
                        <i class="fas fa-undo me-1"></i> Reset
                    </a>
                    <button type="button" class="btn btn-success btn-sm" onclick="exportCSV()">
                        <i class="fas fa-file-csv me-1"></i> Export CSV
                    </button>
                </div>
            </div>
        </form>
    </div>
    
    <!-- Active Filters -->
    <?php if($search || $status || $method || $date_from || $date_to): ?>
    <div class="filter-section bg-white">
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
            <?php if($method): ?>
            <span class="badge bg-secondary">
                Method: <?php echo $payment_methods[$method] ?? $method; ?>
                <a href="?<?php echo http_build_query(array_merge($_GET, ['method' => ''])); ?>" class="text-white ms-1">&times;</a>
            </span>
            <?php endif; ?>
            <?php if($date_from || $date_to): ?>
            <span class="badge bg-secondary">
                Date: <?php echo $date_from ?: 'Any'; ?> to <?php echo $date_to ?: 'Any'; ?>
                <a href="?<?php echo http_build_query(array_merge($_GET, ['date_from' => '', 'date_to' => ''])); ?>" class="text-white ms-1">&times;</a>
            </span>
            <?php endif; ?>
            <a href="transactions.php" class="btn btn-sm btn-link text-danger">Clear All</a>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Transactions Table -->
    <div class="table-container">
        <div class="table-header p-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h5 class="mb-0">
                <i class="fas fa-list me-2 text-primary"></i>Transaction List
                <span class="badge bg-secondary ms-2"><?php echo number_format($total_transactions); ?></span>
            </h5>
            <div>
                <span class="text-muted small">Showing <?php echo count($transactions); ?> of <?php echo number_format($total_transactions); ?></span>
            </div>
        </div>
        
        <div class="table-responsive">
            <table class="table transaction-table mb-0">
                <thead>
                    <tr>
                        <th width="50">#</th>
                        <th>Transaction ID</th>
                        <th>Order</th>
                        <th>Buyer</th>
                        <th>Supplier</th>
                        <th>Amount</th>
                        <th>Method</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th width="100" class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($transactions)): ?>
                    <tr>
                        <td colspan="10" class="text-center py-4">
                            <i class="fas fa-inbox fa-3x text-muted mb-3 d-block"></i>
                            <p class="text-muted mb-0">No transactions found</p>
                        </td>
                    </tr>
                    <?php else: ?>
                        <?php foreach($transactions as $index => $t): ?>
                        <tr>
                            <td><?php echo $offset + $index + 1; ?></td>
                            <td>
                                <code class="small"><?php echo htmlspecialchars($t['transaction_id'] ?? 'N/A'); ?></code>
                                <?php if(!empty($t['mpesa_receipt'])): ?>
                                <br><small class="text-muted">Receipt: <?php echo htmlspecialchars($t['mpesa_receipt']); ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="../orders/order-details.php?id=<?php echo $t['order_id']; ?>" class="text-decoration-none">
                                    <strong><?php echo htmlspecialchars($t['order_number']); ?></strong>
                                </a>
                                <br>
                                <span class="badge bg-secondary"><?php echo ucfirst($t['order_status']); ?></span>
                            </td>
                            <td>
                                <strong><?php echo htmlspecialchars($t['buyer_name']); ?></strong>
                                <br>
                                <small class="text-muted"><?php echo htmlspecialchars($t['buyer_email']); ?></small>
                            </td>
                            <td>
                                <strong><?php echo htmlspecialchars($t['supplier_name'] ?? 'N/A'); ?></strong>
                            </td>
                            <td>
                                <strong class="amount-positive"><?php echo format_price($t['amount_tsh']); ?></strong>
                                <?php if(!empty($t['fees_tsh']) && $t['fees_tsh'] > 0): ?>
                                <br><small class="text-muted">Fee: <?php echo format_price($t['fees_tsh']); ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="method-badge">
                                    <i class="fas <?php echo get_payment_method_icon($t['payment_method']); ?> me-1"></i>
                                    <?php echo $payment_methods[$t['payment_method']] ?? ucfirst($t['payment_method']); ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge bg-<?php echo get_payment_status_label($t['payment_status']); ?>">
                                    <?php echo ucfirst($t['payment_status']); ?>
                                </span>
                            </td>
                            <td>
                                <div title="<?php echo date('Y-m-d H:i:s', strtotime($t['created_at'])); ?>">
                                    <?php echo time_ago($t['created_at']); ?>
                                </div>
                                <small class="text-muted"><?php echo date('M d, Y', strtotime($t['created_at'])); ?></small>
                                <?php if(!empty($t['paid_at'])): ?>
                                <br><small class="text-success">Paid: <?php echo date('M d, Y', strtotime($t['paid_at'])); ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="btn-group">
                                    <button class="btn btn-sm btn-outline-info" onclick="viewTransaction(<?php echo $t['id']; ?>)">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                                        <i class="fas fa-cog"></i>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end">
                                        <li><a class="dropdown-item" href="#" onclick="viewTransaction(<?php echo $t['id']; ?>)">
                                            <i class="fas fa-eye text-info me-2"></i>View Details
                                        </a></li>
                                        <li><a class="dropdown-item" href="../orders/order-details.php?id=<?php echo $t['order_id']; ?>">
                                            <i class="fas fa-shopping-cart text-primary me-2"></i>View Order
                                        </a></li>
                                        <?php if($t['payment_status'] == 'completed'): ?>
                                        <li><hr class="dropdown-divider"></li>
                                        <li><a class="dropdown-item text-danger" href="#" onclick="refundTransaction(<?php echo $t['id']; ?>)">
                                            <i class="fas fa-undo me-2"></i>Refund Payment
                                        </a></li>
                                        <?php endif; ?>
                                        <?php if($t['payment_status'] == 'pending'): ?>
                                        <li><hr class="dropdown-divider"></li>
                                        <li><a class="dropdown-item text-success" href="#" onclick="updateStatus(<?php echo $t['id']; ?>, 'completed')">
                                            <i class="fas fa-check me-2"></i>Mark Completed
                                        </a></li>
                                        <li><a class="dropdown-item text-danger" href="#" onclick="updateStatus(<?php echo $t['id']; ?>, 'failed')">
                                            <i class="fas fa-times me-2"></i>Mark Failed
                                        </a></li>
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
        
        <?php if($total_pages > 1): ?>
        <div class="card-footer d-flex flex-wrap justify-content-between align-items-center py-3 px-4 border-top">
            <div>
                <span class="text-muted small">
                    Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $items_per_page, $total_transactions); ?> of <?php echo number_format($total_transactions); ?> transactions
                </span>
            </div>
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
                    for ($i = $start_page; $i <= $end_page; $i++):
                    ?>
                    <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>"><?php echo $i; ?></a>
                    </li>
                    <?php endfor; ?>
                    <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page+1])); ?>">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    </li>
                </ul>
            </nav>
        </div>
        <?php endif; ?>
    </div>
    
    <div class="py-4"></div>
</div>

<!-- View Transaction Modal -->
<div class="modal fade" id="viewTransactionModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-credit-card me-2"></i>Transaction Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="transactionDetails">
                <div class="text-center py-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" onclick="window.print()">
                    <i class="fas fa-print me-1"></i>Print
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Refund Modal -->
<div class="modal fade" id="refundModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title"><i class="fas fa-undo me-2"></i>Refund Transaction</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="refund_transaction_id">
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong>Warning:</strong> This action will refund the full payment amount. This cannot be undone.
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Refund Reason</label>
                    <textarea id="refund_reason" class="form-control" rows="3" 
                              placeholder="Please provide a reason for this refund..." required></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" onclick="processRefund()">
                    <i class="fas fa-undo me-1"></i>Process Refund
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Toast Container -->
<div class="toast-container"></div>

<!-- ===================================================== -->
<!-- SCRIPTS -->
<!-- ===================================================== -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<script>
// View transaction
function viewTransaction(id) {
    $('#viewTransactionModal').modal('show');
    $('#transactionDetails').html(`
        <div class="text-center py-4">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
        </div>
    `);
    
    $.ajax({
        url: window.location.href,
        type: 'GET',
        data: { ajax: 1, action: 'get_transaction', id: id },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                const t = response.transaction;
                const methods = {
                    'mpesa': 'M-Pesa', 'airtel_money': 'Airtel Money', 'tigo_pesa': 'Tigo Pesa',
                    'halopesa': 'Halopesa', 'azam_pesa': 'Azam Pesa', 'bank_transfer': 'Bank Transfer',
                    'visa': 'Visa', 'mastercard': 'Mastercard', 'escrow': 'Escrow'
                };
                
                let html = `
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="text-muted small fw-bold">Transaction ID</label>
                                <div><code>${t.transaction_id || 'N/A'}</code></div>
                            </div>
                            <div class="mb-3">
                                <label class="text-muted small fw-bold">Order Number</label>
                                <div><strong>${t.order_number}</strong></div>
                            </div>
                            <div class="mb-3">
                                <label class="text-muted small fw-bold">Buyer</label>
                                <div><strong>${t.buyer_name}</strong></div>
                                <small class="text-muted">${t.buyer_email}</small>
                            </div>
                            <div class="mb-3">
                                <label class="text-muted small fw-bold">Supplier</label>
                                <div><strong>${t.supplier_name || 'N/A'}</strong></div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="text-muted small fw-bold">Amount</label>
                                <div class="h4 text-primary">${formatPrice(t.amount_tsh)}</div>
                            </div>
                            <div class="mb-3">
                                <label class="text-muted small fw-bold">Payment Method</label>
                                <div>${methods[t.payment_method] || t.payment_method}</div>
                            </div>
                            <div class="mb-3">
                                <label class="text-muted small fw-bold">Status</label>
                                <div><span class="badge bg-${getStatusColor(t.payment_status)}">${t.payment_status}</span></div>
                            </div>
                            <div class="mb-3">
                                <label class="text-muted small fw-bold">Date</label>
                                <div>${new Date(t.created_at).toLocaleString()}</div>
                            </div>
                            ${t.mpesa_receipt ? `
                            <div class="mb-3">
                                <label class="text-muted small fw-bold">M-Pesa Receipt</label>
                                <div><code>${t.mpesa_receipt}</code></div>
                            </div>
                            ` : ''}
                        </div>
                    </div>
                `;
                $('#transactionDetails').html(html);
            } else {
                $('#transactionDetails').html(`<div class="alert alert-danger">${response.message}</div>`);
            }
        },
        error: function() {
            $('#transactionDetails').html(`<div class="alert alert-danger">Failed to load transaction details</div>`);
        }
    });
}

// Refund transaction
function refundTransaction(id) {
    $('#refund_transaction_id').val(id);
    $('#refund_reason').val('');
    $('#refundModal').modal('show');
}

function processRefund() {
    const id = $('#refund_transaction_id').val();
    const reason = $('#refund_reason').val();
    
    if (!reason) {
        showToast('Error', 'Please provide a refund reason', 'danger');
        return;
    }
    
    $.ajax({
        url: window.location.href,
        type: 'POST',
        data: { ajax: 1, action: 'refund', id: id, reason: reason },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                $('#refundModal').modal('hide');
                showToast('Success', response.message, 'success');
                setTimeout(() => location.reload(), 1500);
            } else {
                showToast('Error', response.message, 'danger');
            }
        },
        error: function() {
            showToast('Error', 'Failed to process refund', 'danger');
        }
    });
}

// Update status
function updateStatus(id, status) {
    if (!confirm('Change status to ' + status + '?')) return;
    
    $.ajax({
        url: window.location.href,
        type: 'POST',
        data: { ajax: 1, action: 'update_status', id: id, status: status },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showToast('Success', response.message, 'success');
                setTimeout(() => location.reload(), 1500);
            } else {
                showToast('Error', response.message, 'danger');
            }
        },
        error: function() {
            showToast('Error', 'Failed to update status', 'danger');
        }
    });
}

// Export CSV
function exportCSV() {
    const url = new URL(window.location.href);
    url.searchParams.set('export', 'csv');
    window.location.href = url.toString();
}

// Show toast
function showToast(title, message, type) {
    const bgColor = type === 'success' ? 'bg-success' : 
                    type === 'danger' ? 'bg-danger' : 'bg-info';
    const icon = type === 'success' ? 'fa-check-circle' : 
                 type === 'danger' ? 'fa-exclamation-circle' : 'fa-info-circle';
    
    const toastHtml = `
        <div class="toast align-items-center text-white ${bgColor} border-0 show" role="alert">
            <div class="d-flex">
                <div class="toast-body">
                    <i class="fas ${icon} me-2"></i>
                    <strong>${title}</strong><br>
                    ${message}
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        </div>
    `;
    
    $('.toast-container').append(toastHtml);
    setTimeout(() => $('.toast').last().remove(), 5000);
}

// Utility functions
function getStatusColor(status) {
    const colors = {
        'pending': 'warning',
        'processing': 'info',
        'completed': 'success',
        'failed': 'danger',
        'refunded': 'secondary'
    };
    return colors[status] || 'secondary';
}

// Load statistics on page load
$(document).ready(function() {
    // Update stats via AJAX
    $.ajax({
        url: window.location.href,
        type: 'GET',
        data: { ajax: 1, action: 'get_stats' },
        dataType: 'json',
        success: function(response) {
            if (response.success && response.stats) {
                const s = response.stats;
                $('#totalRevenue').text(formatPrice(s.total_amount));
                $('#todayTransactions').text(s.today.count || 0);
                $('#todayAmount').text(formatPrice(s.today.total || 0));
                $('#avgTransaction').text(formatPrice(s.avg_amount));
            }
        }
    });
});
</script>

</body>
</html>