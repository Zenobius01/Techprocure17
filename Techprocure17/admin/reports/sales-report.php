<?php
/**
 * TechProcure Tanzania - Admin Sales Report
 * File: admin/reports/sales-report.php
 * Description: Comprehensive sales reports with charts, filters, and data export
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
// GET FILTER PARAMETERS
// =============================================

$period = isset($_GET['period']) ? trim($_GET['period']) : 'month';
$date_from = isset($_GET['date_from']) ? trim($_GET['date_from']) : '';
$date_to = isset($_GET['date_to']) ? trim($_GET['date_to']) : '';
$status = isset($_GET['status']) ? trim($_GET['status']) : 'all';
$export = isset($_GET['export']) ? trim($_GET['export']) : '';

// Set date range based on period
if (empty($date_from) || empty($date_to)) {
    switch ($period) {
        case 'today':
            $date_from = date('Y-m-d');
            $date_to = date('Y-m-d');
            break;
        case 'week':
            $date_from = date('Y-m-d', strtotime('-7 days'));
            $date_to = date('Y-m-d');
            break;
        case 'month':
            $date_from = date('Y-m-d', strtotime('-30 days'));
            $date_to = date('Y-m-d');
            break;
        case 'quarter':
            $date_from = date('Y-m-d', strtotime('-90 days'));
            $date_to = date('Y-m-d');
            break;
        case 'year':
            $date_from = date('Y-m-d', strtotime('-365 days'));
            $date_to = date('Y-m-d');
            break;
        default:
            $date_from = date('Y-m-d', strtotime('-30 days'));
            $date_to = date('Y-m-d');
            break;
    }
}

// =============================================
// BUILD WHERE CLAUSE
// =============================================

$where_conditions = ["o.payment_status = 'paid'"];
$params = [];

if (!empty($date_from) && !empty($date_to)) {
    $where_conditions[] = "DATE(o.created_at) BETWEEN ? AND ?";
    $params[] = $date_from;
    $params[] = $date_to;
}

if ($status != 'all') {
    $where_conditions[] = "o.order_status = ?";
    $params[] = $status;
}

$where_clause = implode(" AND ", $where_conditions);

// =============================================
// GET SALES SUMMARY
// =============================================

$summary = [];
try {
    // Total revenue
    $stmt = $db->prepare("SELECT SUM(total_amount) as total FROM orders o WHERE $where_clause");
    $stmt->execute($params);
    $summary['total_revenue'] = $stmt->fetchColumn() ?? 0;
    
    // Total orders
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM orders o WHERE $where_clause");
    $stmt->execute($params);
    $summary['total_orders'] = $stmt->fetchColumn() ?? 0;
    
    // Average order value
    $summary['avg_order'] = $summary['total_orders'] > 0 ? $summary['total_revenue'] / $summary['total_orders'] : 0;
    
    // Total discount
    $stmt = $db->prepare("SELECT SUM(discount_amount) as total FROM orders o WHERE $where_clause");
    $stmt->execute($params);
    $summary['total_discount'] = $stmt->fetchColumn() ?? 0;
    
    // Total tax
    $stmt = $db->prepare("SELECT SUM(tax_amount) as total FROM orders o WHERE $where_clause");
    $stmt->execute($params);
    $summary['total_tax'] = $stmt->fetchColumn() ?? 0;
    
    // Total shipping
    $stmt = $db->prepare("SELECT SUM(shipping_cost) as total FROM orders o WHERE $where_clause");
    $stmt->execute($params);
    $summary['total_shipping'] = $stmt->fetchColumn() ?? 0;
    
    // Unique customers
    $stmt = $db->prepare("SELECT COUNT(DISTINCT user_id) as total FROM orders o WHERE $where_clause");
    $stmt->execute($params);
    $summary['unique_customers'] = $stmt->fetchColumn() ?? 0;
    
} catch (PDOException $e) {
    $summary = [
        'total_revenue' => 0,
        'total_orders' => 0,
        'avg_order' => 0,
        'total_discount' => 0,
        'total_tax' => 0,
        'total_shipping' => 0,
        'unique_customers' => 0
    ];
}

// =============================================
// GET DAILY SALES FOR CHART
// =============================================

$daily_sales = [];
try {
    $sql = "SELECT DATE(created_at) as date, 
                   COUNT(*) as order_count,
                   SUM(total_amount) as revenue,
                   SUM(discount_amount) as discounts
            FROM orders o
            WHERE $where_clause
            GROUP BY DATE(created_at)
            ORDER BY date ASC";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    if ($stmt->rowCount() > 0) {
        $daily_sales = $stmt->fetchAll();
    }
} catch (PDOException $e) {
    $daily_sales = [];
}

// =============================================
// GET TOP PRODUCTS
// =============================================

$top_products = [];
try {
    $sql = "SELECT p.id, p.product_name, p.sku, 
                   SUM(oi.quantity) as total_sold,
                   SUM(oi.total_price) as total_revenue,
                   COUNT(DISTINCT o.id) as order_count
            FROM order_items oi
            JOIN products p ON oi.product_id = p.id
            JOIN orders o ON oi.order_id = o.id
            WHERE $where_clause
            GROUP BY p.id
            ORDER BY total_revenue DESC
            LIMIT 10";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    if ($stmt->rowCount() > 0) {
        $top_products = $stmt->fetchAll();
    }
} catch (PDOException $e) {
    $top_products = [];
}

// =============================================
// GET TOP SUPPLIERS
// =============================================

$top_suppliers = [];
try {
    $sql = "SELECT s.id, s.company_name,
                   COUNT(DISTINCT o.id) as order_count,
                   SUM(o.total_amount) as total_revenue
            FROM orders o
            JOIN suppliers s ON o.supplier_id = s.id
            WHERE $where_clause
            GROUP BY s.id
            ORDER BY total_revenue DESC
            LIMIT 10";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    if ($stmt->rowCount() > 0) {
        $top_suppliers = $stmt->fetchAll();
    }
} catch (PDOException $e) {
    $top_suppliers = [];
}

// =============================================
// GET PAYMENT METHODS BREAKDOWN
// =============================================

$payment_methods = [];
try {
    $sql = "SELECT payment_method, 
                   COUNT(*) as count,
                   SUM(total_amount) as total
            FROM orders o
            WHERE $where_clause AND payment_method IS NOT NULL AND payment_method != ''
            GROUP BY payment_method
            ORDER BY total DESC";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    if ($stmt->rowCount() > 0) {
        $payment_methods = $stmt->fetchAll();
    }
} catch (PDOException $e) {
    $payment_methods = [];
}

// =============================================
// GET ORDER STATUS BREAKDOWN
// =============================================

$order_statuses = [];
try {
    $sql = "SELECT order_status, 
                   COUNT(*) as count,
                   SUM(total_amount) as total
            FROM orders o
            WHERE $where_clause
            GROUP BY order_status
            ORDER BY count DESC";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    if ($stmt->rowCount() > 0) {
        $order_statuses = $stmt->fetchAll();
    }
} catch (PDOException $e) {
    $order_statuses = [];
}

// =============================================
// GET MONTHLY REVENUE
// =============================================

$monthly_revenue = [];
try {
    $sql = "SELECT DATE_FORMAT(created_at, '%Y-%m') as month,
                   COUNT(*) as order_count,
                   SUM(total_amount) as revenue,
                   SUM(discount_amount) as discounts
            FROM orders o
            WHERE $where_clause
            GROUP BY DATE_FORMAT(created_at, '%Y-%m')
            ORDER BY month ASC
            LIMIT 12";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    if ($stmt->rowCount() > 0) {
        $monthly_revenue = $stmt->fetchAll();
    }
} catch (PDOException $e) {
    $monthly_revenue = [];
}

// =============================================
// EXPORT DATA
// =============================================

if ($export == 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="sales_report_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Date', 'Orders', 'Revenue', 'Discounts', 'Tax', 'Shipping']);
    
    foreach ($daily_sales as $row) {
        fputcsv($output, [
            $row['date'],
            $row['order_count'],
            $row['revenue'],
            $row['discounts'],
            $summary['total_tax'] / max(count($daily_sales), 1),
            $summary['total_shipping'] / max(count($daily_sales), 1)
        ]);
    }
    fclose($output);
    exit();
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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales Report - TechProcure Tanzania</title>
    
    <!-- CSS Dependencies -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.min.css" rel="stylesheet">
    
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
        
        .filter-section {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 24px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        
        .chart-container {
            position: relative;
            height: 300px;
        }
        
        .export-btn {
            border-radius: 10px;
            padding: 8px 20px;
            font-weight: 500;
            transition: all 0.3s;
        }
        
        .export-btn:hover {
            transform: translateY(-2px);
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
            <a href="sales-report.php" class="nav-link active">
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
            <i class="fas fa-chart-line me-2 text-primary"></i> Sales Report
        </div>
        <div class="d-flex align-items-center gap-3">
            <a href="?export=csv&<?php echo http_build_query(array_merge($_GET, ['export' => 'csv'])); ?>" class="btn btn-success btn-sm export-btn">
                <i class="fas fa-file-csv me-1"></i> Export CSV
            </a>
            <div class="admin-avatar">
                <?php echo strtoupper(substr($_SESSION['user_name'] ?? 'A', 0, 1)); ?>
            </div>
        </div>
    </div>
    
    <!-- Filter Section -->
    <div class="filter-section">
        <form method="GET" action="" class="row g-3">
            <div class="col-md-2">
                <label class="form-label">Period</label>
                <select name="period" class="form-select" onchange="this.form.submit()">
                    <option value="today" <?php echo $period == 'today' ? 'selected' : ''; ?>>Today</option>
                    <option value="week" <?php echo $period == 'week' ? 'selected' : ''; ?>>This Week</option>
                    <option value="month" <?php echo $period == 'month' ? 'selected' : ''; ?>>This Month</option>
                    <option value="quarter" <?php echo $period == 'quarter' ? 'selected' : ''; ?>>This Quarter</option>
                    <option value="year" <?php echo $period == 'year' ? 'selected' : ''; ?>>This Year</option>
                    <option value="custom" <?php echo $period == 'custom' ? 'selected' : ''; ?>>Custom</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">From</label>
                <input type="date" name="date_from" class="form-control" value="<?php echo $date_from; ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">To</label>
                <input type="date" name="date_to" class="form-control" value="<?php echo $date_to; ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Order Status</label>
                <select name="status" class="form-select">
                    <option value="all" <?php echo $status == 'all' ? 'selected' : ''; ?>>All Status</option>
                    <option value="pending" <?php echo $status == 'pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="processing" <?php echo $status == 'processing' ? 'selected' : ''; ?>>Processing</option>
                    <option value="shipped" <?php echo $status == 'shipped' ? 'selected' : ''; ?>>Shipped</option>
                    <option value="delivered" <?php echo $status == 'delivered' ? 'selected' : ''; ?>>Delivered</option>
                    <option value="completed" <?php echo $status == 'completed' ? 'selected' : ''; ?>>Completed</option>
                    <option value="cancelled" <?php echo $status == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                </select>
            </div>
            <div class="col-md-3 d-flex align-items-end">
                <button type="submit" class="btn btn-primary me-2"><i class="fas fa-search me-1"></i> Apply</button>
                <a href="sales-report.php" class="btn btn-secondary"><i class="fas fa-undo me-1"></i> Reset</a>
            </div>
        </form>
    </div>
    
    <!-- Stats Cards -->
    <div class="row">
        <div class="col-md-3 col-sm-6">
            <div class="stat-card" style="border-left: 4px solid #0d6efd;">
                <div class="stat-label">Total Revenue</div>
                <div class="stat-number"><?php echo formatPrice($summary['total_revenue']); ?></div>
                <div class="stat-icon"><i class="fas fa-money-bill-wave"></i></div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="stat-card" style="border-left: 4px solid #198754;">
                <div class="stat-label">Orders</div>
                <div class="stat-number"><?php echo number_format($summary['total_orders']); ?></div>
                <div class="stat-icon"><i class="fas fa-shopping-cart"></i></div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="stat-card" style="border-left: 4px solid #ffc107;">
                <div class="stat-label">Avg Order Value</div>
                <div class="stat-number"><?php echo formatPrice($summary['avg_order']); ?></div>
                <div class="stat-icon"><i class="fas fa-calculator"></i></div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="stat-card" style="border-left: 4px solid #dc3545;">
                <div class="stat-label">Unique Customers</div>
                <div class="stat-number"><?php echo number_format($summary['unique_customers']); ?></div>
                <div class="stat-icon"><i class="fas fa-users"></i></div>
            </div>
        </div>
    </div>
    
    <!-- Revenue Chart -->
    <div class="data-card">
        <div class="card-title">
            <i class="fas fa-chart-area"></i> Revenue Trends
        </div>
        <div class="chart-container">
            <canvas id="revenueChart"></canvas>
        </div>
    </div>
    
    <!-- Monthly Revenue -->
    <div class="data-card">
        <div class="card-title">
            <i class="fas fa-chart-bar"></i> Monthly Revenue
        </div>
        <div class="chart-container">
            <canvas id="monthlyChart"></canvas>
        </div>
    </div>
    
    <!-- Top Products & Suppliers -->
    <div class="row">
        <div class="col-md-6">
            <div class="data-card">
                <div class="card-title">
                    <i class="fas fa-trophy"></i> Top Products
                </div>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Sold</th>
                                <th>Revenue</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(!empty($top_products)): ?>
                                <?php foreach($top_products as $product): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($product['product_name']); ?></td>
                                    <td><?php echo $product['total_sold']; ?></td>
                                    <td><?php echo formatPrice($product['total_revenue']); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="3" class="text-center text-muted">No data available</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="data-card">
                <div class="card-title">
                    <i class="fas fa-trophy"></i> Top Suppliers
                </div>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Supplier</th>
                                <th>Orders</th>
                                <th>Revenue</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(!empty($top_suppliers)): ?>
                                <?php foreach($top_suppliers as $supplier): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($supplier['company_name']); ?></td>
                                    <td><?php echo $supplier['order_count']; ?></td>
                                    <td><?php echo formatPrice($supplier['total_revenue']); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="3" class="text-center text-muted">No data available</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Payment Methods & Order Status -->
    <div class="row">
        <div class="col-md-6">
            <div class="data-card">
                <div class="card-title">
                    <i class="fas fa-wallet"></i> Payment Methods
                </div>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Method</th>
                                <th>Orders</th>
                                <th>Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(!empty($payment_methods)): ?>
                                <?php foreach($payment_methods as $pm): ?>
                                <tr>
                                    <td><?php echo ucfirst(str_replace('_', ' ', $pm['payment_method'])); ?></td>
                                    <td><?php echo $pm['count']; ?></td>
                                    <td><?php echo formatPrice($pm['total']); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="3" class="text-center text-muted">No data available</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="data-card">
                <div class="card-title">
                    <i class="fas fa-circle"></i> Order Status
                </div>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Status</th>
                                <th>Orders</th>
                                <th>Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(!empty($order_statuses)): ?>
                                <?php foreach($order_statuses as $os): ?>
                                <tr>
                                    <td><span class="badge bg-<?php echo getStatusBadge($os['order_status']); ?>"><?php echo ucfirst($os['order_status']); ?></span></td>
                                    <td><?php echo $os['count']; ?></td>
                                    <td><?php echo formatPrice($os['total']); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="3" class="text-center text-muted">No data available</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

<script>
    // Revenue Chart
    const revenueCtx = document.getElementById('revenueChart').getContext('2d');
    new Chart(revenueCtx, {
        type: 'line',
        data: {
            labels: <?php echo json_encode(array_column($daily_sales, 'date')); ?>,
            datasets: [{
                label: 'Revenue',
                data: <?php echo json_encode(array_column($daily_sales, 'revenue')); ?>,
                borderColor: '#0d6efd',
                backgroundColor: 'rgba(13, 110, 253, 0.1)',
                fill: true,
                tension: 0.4
            }, {
                label: 'Discounts',
                data: <?php echo json_encode(array_column($daily_sales, 'discounts')); ?>,
                borderColor: '#ffc107',
                backgroundColor: 'rgba(255, 193, 7, 0.1)',
                fill: true,
                tension: 0.4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top'
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return 'TSh ' + value.toLocaleString();
                        }
                    }
                }
            }
        }
    });
    
    // Monthly Chart
    const monthlyCtx = document.getElementById('monthlyChart').getContext('2d');
    new Chart(monthlyCtx, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode(array_column($monthly_revenue, 'month')); ?>,
            datasets: [{
                label: 'Revenue',
                data: <?php echo json_encode(array_column($monthly_revenue, 'revenue')); ?>,
                backgroundColor: 'rgba(13, 110, 253, 0.8)',
                borderColor: '#0d6efd',
                borderWidth: 1
            }, {
                label: 'Orders',
                data: <?php echo json_encode(array_column($monthly_revenue, 'order_count')); ?>,
                backgroundColor: 'rgba(25, 135, 84, 0.8)',
                borderColor: '#198754',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top'
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return 'TSh ' + value.toLocaleString();
                        }
                    }
                }
            }
        }
    });
    
    // Sidebar toggle
    document.getElementById('sidebarCollapse')?.addEventListener('click', function() {
        document.getElementById('sidebar').classList.toggle('active');
        document.getElementById('mainContent').classList.toggle('active');
    });
</script>

</body>
</html>