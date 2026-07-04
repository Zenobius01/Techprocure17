<?php
/**
 * TechProcure Tanzania - My Invoices Page
 * File: customer/invoices/my-invoices.php
 * Description: Customer can view all their invoices
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
// BUILD INVOICE QUERY
// =============================================

$where_conditions = ["i.user_id = ?"];
$params = [$user_id];

// Status filter
if ($status_filter != 'all') {
    $where_conditions[] = "i.status = ?";
    $params[] = $status_filter;
}

// Search filter
if (!empty($search)) {
    $where_conditions[] = "(i.invoice_number LIKE ? OR o.order_number LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
}

$where_clause = implode(" AND ", $where_conditions);

// =============================================
// COUNT TOTAL INVOICES
// =============================================

$total_invoices = 0;
$total_pages = 1;

try {
    $count_sql = "SELECT COUNT(*) as total FROM invoices i WHERE $where_clause";
    $stmt = $db->prepare($count_sql);
    $stmt->execute($params);
    $total_invoices = (int)$stmt->fetchColumn();
    $total_pages = ceil($total_invoices / $limit);
} catch (PDOException $e) {
    $total_invoices = 0;
    $total_pages = 1;
}

// =============================================
// GET INVOICES
// =============================================

$invoices = [];
try {
    $sql = "SELECT i.*, o.order_number, o.total_amount as order_total,
                   s.company_name as supplier_name,
                   (SELECT COUNT(*) FROM order_items WHERE order_id = o.id) as item_count
            FROM invoices i
            JOIN orders o ON i.order_id = o.id
            JOIN suppliers s ON i.supplier_id = s.id
            WHERE $where_clause
            ORDER BY i.created_at DESC
            LIMIT ? OFFSET ?";
    
    $params[] = $limit;
    $params[] = $offset;
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    
    if ($stmt->rowCount() > 0) {
        $invoices = $stmt->fetchAll();
    }
} catch (PDOException $e) {
    $invoices = [];
}

// =============================================
// GET INVOICE STATUS COUNTS
// =============================================

$status_counts = [];
$status_list = ['draft', 'sent', 'paid', 'overdue', 'cancelled'];
try {
    foreach ($status_list as $status) {
        $stmt = $db->prepare("SELECT COUNT(*) as total FROM invoices WHERE user_id = ? AND status = ?");
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
            'draft' => 'secondary',
            'sent' => 'info',
            'paid' => 'success',
            'overdue' => 'danger',
            'cancelled' => 'danger'
        ];
        return $colors[$status] ?? 'secondary';
    }
}

if (!function_exists('getStatusIcon')) {
    function getStatusIcon($status) {
        $icons = [
            'draft' => 'fa-file',
            'sent' => 'fa-paper-plane',
            'paid' => 'fa-check-circle',
            'overdue' => 'fa-exclamation-triangle',
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
    <title>My Invoices - TechProcure Tanzania</title>
    
    <!-- CSS Dependencies -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
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
        
        /* Invoices Content */
        .invoices-content {
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
        
        /* Invoice Card */
        .invoice-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            transition: all 0.3s;
            border-left: 4px solid #0d6efd;
        }
        
        .invoice-card:hover {
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            transform: translateY(-2px);
        }
        
        .invoice-card .invoice-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            padding-bottom: 12px;
            border-bottom: 1px solid #e9ecef;
            margin-bottom: 12px;
        }
        
        .invoice-card .invoice-number {
            font-weight: 600;
            font-size: 1.1rem;
        }
        
        .invoice-card .invoice-number code {
            background: #f8f9fa;
            padding: 4px 10px;
            border-radius: 6px;
        }
        
        .invoice-card .invoice-date {
            color: #6c757d;
            font-size: 0.85rem;
        }
        
        .invoice-card .invoice-details {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
        }
        
        .invoice-card .invoice-details .detail-item {
            flex: 1;
            min-width: 120px;
        }
        
        .invoice-card .invoice-details .detail-item .label {
            font-size: 0.75rem;
            color: #6c757d;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .invoice-card .invoice-details .detail-item .value {
            font-weight: 500;
        }
        
        .invoice-card .invoice-actions {
            margin-top: 12px;
            padding-top: 12px;
            border-top: 1px solid #e9ecef;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        /* Invoice Summary */
        .invoice-summary {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        
        .invoice-summary .summary-item {
            text-align: center;
        }
        
        .invoice-summary .summary-item .number {
            font-size: 1.5rem;
            font-weight: 700;
        }
        
        .invoice-summary .summary-item .label {
            font-size: 0.8rem;
            color: #6c757d;
        }
        
        /* No Invoices */
        .no-invoices {
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
            .invoice-card .invoice-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 5px;
            }
            .invoice-card .invoice-details {
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
            .invoice-summary .summary-item {
                margin-bottom: 10px;
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
                    <a class="nav-link" href="../orders/my-orders.php">
                        <i class="fas fa-shopping-bag"></i> My Orders
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
                    <a class="nav-link active" href="my-invoices.php">
                        <i class="fas fa-file-invoice"></i> Invoices
                        <?php if($status_counts['overdue'] > 0): ?>
                            <span class="badge bg-danger"><?php echo $status_counts['overdue']; ?></span>
                        <?php endif; ?>
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
        
        <!-- Invoices Content -->
        <div class="invoices-content">
            <!-- Page Title -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h4 class="mb-0"><i class="fas fa-file-invoice me-2 text-primary"></i>My Invoices</h4>
                    <p class="text-muted">View and manage all your invoices</p>
                </div>
                <span class="badge bg-primary"><?php echo number_format($total_invoices); ?> Total</span>
            </div>
            
            <!-- Invoice Summary -->
            <div class="invoice-summary">
                <div class="row">
                    <div class="col-md-3 summary-item">
                        <div class="number text-primary"><?php echo $status_counts['paid']; ?></div>
                        <div class="label">Paid</div>
                    </div>
                    <div class="col-md-3 summary-item">
                        <div class="number text-warning"><?php echo $status_counts['sent']; ?></div>
                        <div class="label">Sent</div>
                    </div>
                    <div class="col-md-3 summary-item">
                        <div class="number text-danger"><?php echo $status_counts['overdue']; ?></div>
                        <div class="label">Overdue</div>
                    </div>
                    <div class="col-md-3 summary-item">
                        <div class="number text-secondary"><?php echo $status_counts['draft'] + $status_counts['cancelled']; ?></div>
                        <div class="label">Draft / Cancelled</div>
                    </div>
                </div>
            </div>
            
            <!-- Status Filter -->
            <div class="status-filter">
                <a href="?status=all" class="btn <?php echo $status_filter == 'all' ? 'btn-primary' : 'btn-outline-primary'; ?>">
                    All <span class="badge bg-<?php echo $status_filter == 'all' ? 'light text-dark' : 'primary'; ?>"><?php echo $status_counts['all']; ?></span>
                </a>
                <a href="?status=paid" class="btn <?php echo $status_filter == 'paid' ? 'btn-success' : 'btn-outline-success'; ?>">
                    <i class="fas fa-check-circle me-1"></i> Paid
                    <?php if($status_counts['paid'] > 0): ?>
                        <span class="badge bg-<?php echo $status_filter == 'paid' ? 'light text-dark' : 'success'; ?>"><?php echo $status_counts['paid']; ?></span>
                    <?php endif; ?>
                </a>
                <a href="?status=sent" class="btn <?php echo $status_filter == 'sent' ? 'btn-info' : 'btn-outline-info'; ?>">
                    <i class="fas fa-paper-plane me-1"></i> Sent
                    <?php if($status_counts['sent'] > 0): ?>
                        <span class="badge bg-<?php echo $status_filter == 'sent' ? 'light text-dark' : 'info'; ?>"><?php echo $status_counts['sent']; ?></span>
                    <?php endif; ?>
                </a>
                <a href="?status=overdue" class="btn <?php echo $status_filter == 'overdue' ? 'btn-danger' : 'btn-outline-danger'; ?>">
                    <i class="fas fa-exclamation-triangle me-1"></i> Overdue
                    <?php if($status_counts['overdue'] > 0): ?>
                        <span class="badge bg-<?php echo $status_filter == 'overdue' ? 'light text-dark' : 'danger'; ?>"><?php echo $status_counts['overdue']; ?></span>
                    <?php endif; ?>
                </a>
                <a href="?status=draft" class="btn <?php echo $status_filter == 'draft' ? 'btn-secondary' : 'btn-outline-secondary'; ?>">
                    <i class="fas fa-file me-1"></i> Draft
                    <?php if($status_counts['draft'] > 0): ?>
                        <span class="badge bg-<?php echo $status_filter == 'draft' ? 'light text-dark' : 'secondary'; ?>"><?php echo $status_counts['draft']; ?></span>
                    <?php endif; ?>
                </a>
            </div>
            
            <!-- Search -->
            <div class="mb-4">
                <form method="GET" action="" class="row g-2">
                    <input type="hidden" name="status" value="<?php echo $status_filter; ?>">
                    <div class="col-md-8">
                        <input type="text" name="search" class="form-control" placeholder="Search by invoice number or order number..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="col-md-4">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-search me-1"></i> Search
                        </button>
                    </div>
                </form>
            </div>
            
            <!-- Invoices List -->
            <?php if (!empty($invoices)): ?>
                <?php foreach($invoices as $invoice): ?>
                <div class="invoice-card" style="border-left-color: <?php echo $invoice['status'] == 'overdue' ? '#dc3545' : ($invoice['status'] == 'paid' ? '#198754' : ($invoice['status'] == 'sent' ? '#0dcaf0' : '#6c757d')); ?>;">
                    <!-- Invoice Header -->
                    <div class="invoice-header">
                        <div class="invoice-number">
                            <code><?php echo htmlspecialchars($invoice['invoice_number']); ?></code>
                            <?php $badge = getStatusBadge($invoice['status']); ?>
                            <span class="badge bg-<?php echo $badge; ?> ms-2">
                                <i class="fas <?php echo getStatusIcon($invoice['status']); ?> me-1"></i>
                                <?php echo ucfirst($invoice['status']); ?>
                            </span>
                            <?php if($invoice['status'] == 'overdue'): ?>
                                <span class="badge bg-danger ms-1">
                                    <i class="fas fa-clock me-1"></i> Overdue
                                </span>
                            <?php endif; ?>
                        </div>
                        <div class="invoice-date">
                            <i class="fas fa-calendar-alt me-1"></i>
                            Issued: <?php echo formatDate($invoice['issue_date'] ?? $invoice['created_at']); ?>
                            <?php if($invoice['due_date']): ?>
                                <span class="ms-2">
                                    <i class="fas fa-hourglass-end me-1"></i>
                                    Due: <?php echo formatDate($invoice['due_date']); ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Invoice Details -->
                    <div class="invoice-details">
                        <div class="detail-item">
                            <div class="label">Order</div>
                            <div class="value">
                                <code><?php echo htmlspecialchars($invoice['order_number']); ?></code>
                            </div>
                        </div>
                        <div class="detail-item">
                            <div class="label">Supplier</div>
                            <div class="value"><?php echo htmlspecialchars($invoice['supplier_name']); ?></div>
                        </div>
                        <div class="detail-item">
                            <div class="label">Items</div>
                            <div class="value"><?php echo $invoice['item_count']; ?> items</div>
                        </div>
                        <div class="detail-item">
                            <div class="label">Total Amount</div>
                            <div class="value text-primary fw-bold"><?php echo formatPrice($invoice['total_amount']); ?></div>
                        </div>
                        <div class="detail-item">
                            <div class="label">Paid Amount</div>
                            <div class="value text-success"><?php echo formatPrice($invoice['paid_amount'] ?? 0); ?></div>
                        </div>
                    </div>
                    
                    <!-- Invoice Actions -->
                    <div class="invoice-actions">
                        <a href="invoice-details.php?id=<?php echo $invoice['id']; ?>" class="btn btn-sm btn-primary">
                            <i class="fas fa-eye me-1"></i> View Details
                        </a>
                        <a href="download-invoice.php?id=<?php echo $invoice['id']; ?>" class="btn btn-sm btn-success" target="_blank">
                            <i class="fas fa-file-pdf me-1"></i> Download PDF
                        </a>
                        <?php if($invoice['status'] == 'sent' || $invoice['status'] == 'overdue'): ?>
                            <button onclick="payInvoice(<?php echo $invoice['id']; ?>)" class="btn btn-sm btn-warning">
                                <i class="fas fa-credit-card me-1"></i> Pay Now
                            </button>
                        <?php endif; ?>
                        <?php if($invoice['status'] == 'draft'): ?>
                            <a href="edit-invoice.php?id=<?php echo $invoice['id']; ?>" class="btn btn-sm btn-outline-secondary">
                                <i class="fas fa-edit me-1"></i> Edit
                            </a>
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
            <!-- No Invoices -->
            <div class="no-invoices">
                <i class="fas fa-file-invoice fa-4x text-muted mb-3"></i>
                <h4>No Invoices Found</h4>
                <p class="text-muted">
                    <?php if(!empty($search) || $status_filter != 'all'): ?>
                        No invoices match your search criteria.
                    <?php else: ?>
                        You don't have any invoices yet. Invoices are generated after orders are placed.
                    <?php endif; ?>
                </p>
                <?php if(!empty($search) || $status_filter != 'all'): ?>
                    <a href="my-invoices.php" class="btn btn-primary">
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
                    <li><a href="../orders/my-orders.php">My Orders</a></li>
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
    // Pay invoice
    function payInvoice(invoiceId) {
        if (confirm('Proceed to pay this invoice?')) {
            window.location.href = '../../payment/pay-invoice.php?id=' + invoiceId;
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