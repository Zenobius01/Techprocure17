<?php
/**
 * TechProcure Tanzania - My Quotations Page
 * File: customer/quotations/my-quotations.php
 * Description: Customer can view all their quotation requests and responses
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
// BUILD QUOTATION QUERY
// =============================================

$where_conditions = ["q.user_id = ?"];
$params = [$user_id];

// Status filter
if ($status_filter != 'all') {
    $where_conditions[] = "q.status = ?";
    $params[] = $status_filter;
}

// Search filter
if (!empty($search)) {
    $where_conditions[] = "(q.quotation_number LIKE ? OR q.title LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
}

$where_clause = implode(" AND ", $where_conditions);

// =============================================
// COUNT TOTAL QUOTATIONS
// =============================================

$total_quotations = 0;
$total_pages = 1;

try {
    $count_sql = "SELECT COUNT(*) as total FROM quotations q WHERE $where_clause";
    $stmt = $db->prepare($count_sql);
    $stmt->execute($params);
    $total_quotations = (int)$stmt->fetchColumn();
    $total_pages = ceil($total_quotations / $limit);
} catch (PDOException $e) {
    $total_quotations = 0;
    $total_pages = 1;
}

// =============================================
// GET QUOTATIONS
// =============================================

$quotations = [];
try {
    $sql = "SELECT q.*,
                   (SELECT COUNT(*) FROM quotation_responses WHERE quotation_id = q.id) as response_count,
                   (SELECT COUNT(*) FROM quotation_responses WHERE quotation_id = q.id AND status = 'submitted') as pending_responses,
                   (SELECT COUNT(*) FROM quotation_responses WHERE quotation_id = q.id AND status = 'accepted') as accepted_responses
            FROM quotations q 
            WHERE $where_clause
            ORDER BY q.created_at DESC
            LIMIT ? OFFSET ?";
    
    $params[] = $limit;
    $params[] = $offset;
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    
    if ($stmt->rowCount() > 0) {
        $quotations = $stmt->fetchAll();
    }
} catch (PDOException $e) {
    $quotations = [];
}

// =============================================
// GET QUOTATION STATUS COUNTS
// =============================================

$status_counts = [];
$status_list = ['open', 'closed', 'cancelled', 'awarded'];
try {
    foreach ($status_list as $status) {
        $stmt = $db->prepare("SELECT COUNT(*) as total FROM quotations WHERE user_id = ? AND status = ?");
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
            'open' => 'success',
            'closed' => 'secondary',
            'cancelled' => 'danger',
            'awarded' => 'primary',
            'pending' => 'warning'
        ];
        return $colors[$status] ?? 'secondary';
    }
}

if (!function_exists('getStatusIcon')) {
    function getStatusIcon($status) {
        $icons = [
            'open' => 'fa-file-alt',
            'closed' => 'fa-check-circle',
            'cancelled' => 'fa-times-circle',
            'awarded' => 'fa-trophy',
            'pending' => 'fa-clock'
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
    <title>My Quotations - TechProcure Tanzania</title>
    
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
        
        /* Quotations Content */
        .quotations-content {
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
        
        /* Quotation Card */
        .quotation-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            transition: all 0.3s;
            border-left: 4px solid #0d6efd;
        }
        
        .quotation-card:hover {
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            transform: translateY(-2px);
        }
        
        .quotation-card .quotation-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            padding-bottom: 12px;
            border-bottom: 1px solid #e9ecef;
            margin-bottom: 12px;
        }
        
        .quotation-card .quotation-number {
            font-weight: 600;
            font-size: 1.1rem;
        }
        
        .quotation-card .quotation-number code {
            background: #f8f9fa;
            padding: 4px 10px;
            border-radius: 6px;
        }
        
        .quotation-card .quotation-date {
            color: #6c757d;
            font-size: 0.85rem;
        }
        
        .quotation-card .quotation-title {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 8px;
        }
        
        .quotation-card .quotation-details {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
        }
        
        .quotation-card .quotation-details .detail-item {
            flex: 1;
            min-width: 120px;
        }
        
        .quotation-card .quotation-details .detail-item .label {
            font-size: 0.75rem;
            color: #6c757d;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .quotation-card .quotation-details .detail-item .value {
            font-weight: 500;
        }
        
        .quotation-card .quotation-actions {
            margin-top: 12px;
            padding-top: 12px;
            border-top: 1px solid #e9ecef;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        /* No Quotations */
        .no-quotations {
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
            .quotation-card .quotation-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 5px;
            }
            .quotation-card .quotation-details {
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
                    <a class="nav-link" href="request-quotation.php">
                        <i class="fas fa-file-alt"></i> Request Quotation
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="my-quotations.php">
                        <i class="fas fa-list"></i> My Quotations
                        <?php if($status_counts['open'] > 0): ?>
                            <span class="badge bg-success"><?php echo $status_counts['open']; ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="../invoices/my-invoices.php">
                        <i class="fas fa-file-invoice"></i> Invoices
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
        
        <!-- Quotations Content -->
        <div class="quotations-content">
            <!-- Page Title -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h4 class="mb-0"><i class="fas fa-list me-2 text-primary"></i>My Quotations</h4>
                    <p class="text-muted">View all your quotation requests and responses</p>
                </div>
                <div>
                    <a href="request-quotation.php" class="btn btn-primary">
                        <i class="fas fa-plus me-2"></i> New Request
                    </a>
                </div>
            </div>
            
            <!-- Status Filter -->
            <div class="status-filter">
                <a href="?status=all" class="btn <?php echo $status_filter == 'all' ? 'btn-primary' : 'btn-outline-primary'; ?>">
                    All <span class="badge bg-<?php echo $status_filter == 'all' ? 'light text-dark' : 'primary'; ?>"><?php echo $status_counts['all']; ?></span>
                </a>
                <a href="?status=open" class="btn <?php echo $status_filter == 'open' ? 'btn-success' : 'btn-outline-success'; ?>">
                    <i class="fas fa-file-alt me-1"></i> Open
                    <?php if($status_counts['open'] > 0): ?>
                        <span class="badge bg-<?php echo $status_filter == 'open' ? 'light text-dark' : 'success'; ?>"><?php echo $status_counts['open']; ?></span>
                    <?php endif; ?>
                </a>
                <a href="?status=awarded" class="btn <?php echo $status_filter == 'awarded' ? 'btn-primary' : 'btn-outline-primary'; ?>">
                    <i class="fas fa-trophy me-1"></i> Awarded
                    <?php if($status_counts['awarded'] > 0): ?>
                        <span class="badge bg-<?php echo $status_filter == 'awarded' ? 'light text-dark' : 'primary'; ?>"><?php echo $status_counts['awarded']; ?></span>
                    <?php endif; ?>
                </a>
                <a href="?status=closed" class="btn <?php echo $status_filter == 'closed' ? 'btn-secondary' : 'btn-outline-secondary'; ?>">
                    <i class="fas fa-check-circle me-1"></i> Closed
                    <?php if($status_counts['closed'] > 0): ?>
                        <span class="badge bg-<?php echo $status_filter == 'closed' ? 'light text-dark' : 'secondary'; ?>"><?php echo $status_counts['closed']; ?></span>
                    <?php endif; ?>
                </a>
                <a href="?status=cancelled" class="btn <?php echo $status_filter == 'cancelled' ? 'btn-danger' : 'btn-outline-danger'; ?>">
                    <i class="fas fa-times-circle me-1"></i> Cancelled
                    <?php if($status_counts['cancelled'] > 0): ?>
                        <span class="badge bg-<?php echo $status_filter == 'cancelled' ? 'light text-dark' : 'danger'; ?>"><?php echo $status_counts['cancelled']; ?></span>
                    <?php endif; ?>
                </a>
            </div>
            
            <!-- Search -->
            <div class="mb-4">
                <form method="GET" action="" class="row g-2">
                    <input type="hidden" name="status" value="<?php echo $status_filter; ?>">
                    <div class="col-md-8">
                        <input type="text" name="search" class="form-control" placeholder="Search by quotation number or title..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="col-md-4">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-search me-1"></i> Search
                        </button>
                    </div>
                </form>
            </div>
            
            <!-- Quotations List -->
            <?php if (!empty($quotations)): ?>
                <?php foreach($quotations as $quotation): ?>
                <div class="quotation-card" style="border-left-color: <?php echo $quotation['status'] == 'cancelled' ? '#dc3545' : ($quotation['status'] == 'awarded' ? '#0d6efd' : ($quotation['status'] == 'closed' ? '#6c757d' : '#198754')); ?>;">
                    <!-- Quotation Header -->
                    <div class="quotation-header">
                        <div class="quotation-number">
                            <code><?php echo htmlspecialchars($quotation['quotation_number']); ?></code>
                            <?php $badge = getStatusBadge($quotation['status']); ?>
                            <span class="badge bg-<?php echo $badge; ?> ms-2">
                                <i class="fas <?php echo getStatusIcon($quotation['status']); ?> me-1"></i>
                                <?php echo ucfirst($quotation['status']); ?>
                            </span>
                        </div>
                        <div class="quotation-date">
                            <i class="fas fa-calendar-alt me-1"></i>
                            <?php echo formatDateTime($quotation['created_at']); ?>
                        </div>
                    </div>
                    
                    <!-- Quotation Title -->
                    <div class="quotation-title">
                        <?php echo htmlspecialchars($quotation['title']); ?>
                    </div>
                    
                    <!-- Quotation Details -->
                    <div class="quotation-details">
                        <div class="detail-item">
                            <div class="label">Description</div>
                            <div class="value small"><?php echo truncateText($quotation['description'] ?? 'No description', 100); ?></div>
                        </div>
                        <div class="detail-item">
                            <div class="label">Responses</div>
                            <div class="value">
                                <?php echo $quotation['response_count']; ?> total
                                <?php if($quotation['pending_responses'] > 0): ?>
                                    <span class="badge bg-warning ms-1"><?php echo $quotation['pending_responses']; ?> pending</span>
                                <?php endif; ?>
                                <?php if($quotation['accepted_responses'] > 0): ?>
                                    <span class="badge bg-success ms-1"><?php echo $quotation['accepted_responses']; ?> accepted</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="detail-item">
                            <div class="label">Budget Range</div>
                            <div class="value">
                                <?php if($quotation['budget_min'] && $quotation['budget_max']): ?>
                                    <?php echo formatPrice($quotation['budget_min']); ?> - <?php echo formatPrice($quotation['budget_max']); ?>
                                <?php elseif($quotation['budget_min']): ?>
                                    From <?php echo formatPrice($quotation['budget_min']); ?>
                                <?php elseif($quotation['budget_max']): ?>
                                    Up to <?php echo formatPrice($quotation['budget_max']); ?>
                                <?php else: ?>
                                    Not specified
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="detail-item">
                            <div class="label">Delivery</div>
                            <div class="value">
                                <?php if($quotation['delivery_location']): ?>
                                    <i class="fas fa-map-marker-alt me-1"></i> <?php echo htmlspecialchars($quotation['delivery_location']); ?>
                                <?php endif; ?>
                                <?php if($quotation['delivery_deadline']): ?>
                                    <br><i class="fas fa-calendar me-1"></i> <?php echo formatDate($quotation['delivery_deadline']); ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Quotation Actions -->
                    <div class="quotation-actions">
                        <a href="quotation-details.php?id=<?php echo $quotation['id']; ?>" class="btn btn-sm btn-primary">
                            <i class="fas fa-eye me-1"></i> View Details
                        </a>
                        <?php if($quotation['status'] == 'open'): ?>
                            <a href="?action=close&id=<?php echo $quotation['id']; ?>" class="btn btn-sm btn-warning" onclick="return confirm('Close this quotation request?')">
                                <i class="fas fa-check-circle me-1"></i> Close Request
                            </a>
                            <a href="?action=cancel&id=<?php echo $quotation['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Cancel this quotation request?')">
                                <i class="fas fa-times me-1"></i> Cancel
                            </a>
                        <?php endif; ?>
                        <?php if($quotation['status'] == 'awarded'): ?>
                            <a href="../../orders/place-order.php?quotation=<?php echo $quotation['id']; ?>" class="btn btn-sm btn-success">
                                <i class="fas fa-shopping-cart me-1"></i> Place Order
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
            <!-- No Quotations -->
            <div class="no-quotations">
                <i class="fas fa-file-alt fa-4x text-muted mb-3"></i>
                <h4>No Quotations Found</h4>
                <p class="text-muted">
                    <?php if(!empty($search) || $status_filter != 'all'): ?>
                        No quotations match your search criteria.
                    <?php else: ?>
                        You haven't submitted any quotation requests yet.
                    <?php endif; ?>
                </p>
                <?php if(!empty($search) || $status_filter != 'all'): ?>
                    <a href="my-quotations.php" class="btn btn-primary">
                        <i class="fas fa-undo me-2"></i> Clear Filters
                    </a>
                <?php else: ?>
                    <a href="request-quotation.php" class="btn btn-primary">
                        <i class="fas fa-plus me-2"></i> Request a Quotation
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
    // Close quotation
    function closeQuotation(id) {
        if (confirm('Are you sure you want to close this quotation request?')) {
            window.location.href = '?action=close&id=' + id;
        }
    }
    
    // Cancel quotation
    function cancelQuotation(id) {
        if (confirm('Are you sure you want to cancel this quotation request?')) {
            window.location.href = '?action=cancel&id=' + id;
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