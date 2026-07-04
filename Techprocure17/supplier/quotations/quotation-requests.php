<?php
/**
 * TechProcure Tanzania - Supplier Quotation Requests
 * File: supplier/quotations/quotation-requests.php
 * Description: Suppliers can view and respond to quotation requests from customers
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

if (!function_exists('verifyCSRFToken')) {
    function verifyCSRFToken($token) {
        if (empty($token) || empty($_SESSION['csrf_token'])) {
            return false;
        }
        if (isset($_SESSION['csrf_token_time']) && (time() - $_SESSION['csrf_token_time']) > 3600) {
            unset($_SESSION['csrf_token']);
            unset($_SESSION['csrf_token_time']);
            return false;
        }
        return hash_equals($_SESSION['csrf_token'], $token);
    }
}

$csrf_token = generateCSRFToken();

// =============================================
// PROCESS QUOTATION RESPONSE (AJAX)
// =============================================
if (isset($_POST['action']) && $_POST['action'] === 'respond_quotation') {
    header('Content-Type: application/json');
    
    // Check authentication
    if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'supplier') {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit();
    }
    
    // Get POST data
    $quotation_id = isset($_POST['quotation_id']) ? (int)$_POST['quotation_id'] : 0;
    $response_type = isset($_POST['response_type']) ? sanitizeInput($_POST['response_type']) : '';
    $quote_price = isset($_POST['quote_price']) ? (float)$_POST['quote_price'] : 0;
    $delivery_time = isset($_POST['delivery_time']) ? sanitizeInput($_POST['delivery_time']) : '';
    $notes = isset($_POST['notes']) ? sanitizeInput($_POST['notes']) : '';
    $csrf_token_post = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
    
    // Validate CSRF token
    if (empty($csrf_token_post) || empty($_SESSION['csrf_token'])) {
        echo json_encode(['success' => false, 'message' => 'Missing security token']);
        exit();
    }
    
    if (!hash_equals($_SESSION['csrf_token'], $csrf_token_post)) {
        echo json_encode(['success' => false, 'message' => 'Invalid security token']);
        exit();
    }
    
    // Validate inputs
    if ($quotation_id <= 0 || empty($response_type)) {
        echo json_encode(['success' => false, 'message' => 'Invalid input']);
        exit();
    }
    
    if (!in_array($response_type, ['accepted', 'rejected', 'counter'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid response type']);
        exit();
    }
    
    if ($response_type === 'accepted' && $quote_price <= 0) {
        echo json_encode(['success' => false, 'message' => 'Please enter a valid price']);
        exit();
    }
    
    try {
        // Check if quotation belongs to this supplier
        $check_stmt = $conn->prepare("
            SELECT q.id, q.status, q.customer_id, q.products, q.quantity, q.notes as customer_notes
            FROM quotation_requests q
            WHERE q.id = ? AND q.supplier_id = ?
        ");
        $check_stmt->execute([$quotation_id, $supplier_id]);
        $quotation = $check_stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$quotation) {
            echo json_encode(['success' => false, 'message' => 'Quotation not found or unauthorized']);
            exit();
        }
        
        if ($quotation['status'] !== 'pending') {
            echo json_encode(['success' => false, 'message' => 'This quotation has already been responded to']);
            exit();
        }
        
        // Update quotation
        $update_stmt = $conn->prepare("
            UPDATE quotation_requests 
            SET 
                status = ?,
                supplier_quote = ?,
                delivery_time = ?,
                supplier_notes = ?,
                responded_at = NOW(),
                updated_at = NOW()
            WHERE id = ?
        ");
        
        // Set status based on response
        $status = $response_type === 'accepted' ? 'accepted' : ($response_type === 'counter' ? 'countered' : 'rejected');
        
        $update_stmt->execute([
            $status,
            $quote_price ?: null,
            $delivery_time,
            $notes,
            $quotation_id
        ]);
        
        // Create notification for customer
        try {
            $notification_sql = "INSERT INTO notifications (user_id, type, title, message, link, created_at) 
                                 VALUES (?, 'quotation', ?, ?, ?, NOW())";
            $notif_stmt = $conn->prepare($notification_sql);
            
            $status_labels = [
                'accepted' => 'Accepted',
                'rejected' => 'Rejected',
                'countered' => 'Countered'
            ];
            
            $title = "Quotation Response";
            $message = "Your quotation request #{$quotation_id} has been {$status_labels[$status]} by the supplier";
            $link = "../../customer/quotations/view.php?id=" . $quotation_id;
            
            $notif_stmt->execute([$quotation['customer_id'], $title, $message, $link]);
        } catch (Exception $e) {
            // Skip if notification fails
        }
        
        echo json_encode(['success' => true, 'message' => 'Quotation response sent successfully']);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit();
}

// =============================================
// GET QUOTATION STATISTICS
// =============================================
$total_requests = 0;
$pending_requests = 0;
$accepted_requests = 0;
$rejected_requests = 0;
$countered_requests = 0;

try {
    // Get total quotation requests for this supplier
    $count_stmt = $conn->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN status = 'accepted' THEN 1 ELSE 0 END) as accepted,
            SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected,
            SUM(CASE WHEN status = 'countered' THEN 1 ELSE 0 END) as countered
        FROM quotation_requests
        WHERE supplier_id = ?
    ");
    $count_stmt->execute([$supplier_id]);
    $stats = $count_stmt->fetch(PDO::FETCH_ASSOC);
    
    $total_requests = $stats['total'] ?? 0;
    $pending_requests = $stats['pending'] ?? 0;
    $accepted_requests = $stats['accepted'] ?? 0;
    $rejected_requests = $stats['rejected'] ?? 0;
    $countered_requests = $stats['countered'] ?? 0;
    
} catch (PDOException $e) {
    // Continue with zeros
    error_log("Quotation stats error: " . $e->getMessage());
}

// =============================================
// GET FILTER PARAMETERS
// =============================================
$status_filter = isset($_GET['status']) ? sanitizeInput($_GET['status']) : 'all';
$search = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// =============================================
// FETCH QUOTATION REQUESTS
// =============================================
$quotations = [];
$total_pages = 0;
$current_page = $page;

try {
    // Base query for quotation requests
    $sql = "
        SELECT 
            q.*,
            u.full_name as customer_name,
            u.email as customer_email,
            u.phone as customer_phone,
            p.name as product_name,
            p.sku as product_sku,
            p.price as product_price,
            (SELECT COUNT(*) FROM quotation_requests q2 WHERE q2.supplier_id = q.supplier_id AND q2.status = 'pending') as pending_count
        FROM quotation_requests q
        LEFT JOIN users u ON q.customer_id = u.id
        LEFT JOIN products p ON q.product_id = p.id
        WHERE q.supplier_id = ?
    ";
    
    $params = [$supplier_id];
    
    // Apply status filter
    if ($status_filter !== 'all') {
        $sql .= " AND q.status = ?";
        $params[] = $status_filter;
    }
    
    // Apply search
    if (!empty($search)) {
        $sql .= " AND (q.id LIKE ? OR u.full_name LIKE ? OR u.email LIKE ? OR p.name LIKE ?)";
        $search_param = "%$search%";
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
    }
    
    // Get total count for pagination
    $count_sql = str_replace(
        "SELECT 
            q.*,
            u.full_name as customer_name,
            u.email as customer_email,
            u.phone as customer_phone,
            p.name as product_name,
            p.sku as product_sku,
            p.price as product_price,
            (SELECT COUNT(*) FROM quotation_requests q2 WHERE q2.supplier_id = q.supplier_id AND q2.status = 'pending') as pending_count",
        "SELECT COUNT(*) as total",
        $sql
    );
    
    $count_stmt = $conn->prepare($count_sql);
    $count_stmt->execute($params);
    $total_records = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    $total_pages = ceil($total_records / $limit);
    
    // Add order and pagination
    $sql .= " ORDER BY q.created_at DESC LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $quotations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Decode product details if stored as JSON
    foreach ($quotations as &$quotation) {
        if (!empty($quotation['products']) && is_string($quotation['products'])) {
            $quotation['products_data'] = json_decode($quotation['products'], true);
        }
    }
    
} catch (PDOException $e) {
    $error = "Failed to load quotation requests: " . $e->getMessage();
    error_log("Quotation fetch error: " . $e->getMessage());
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
$page_title = 'Quotation Requests - Supplier Panel';
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
        
        .status-badge.pending {
            background: #fff3cd;
            color: #856404;
        }
        
        .status-badge.accepted {
            background: #d4edda;
            color: #155724;
        }
        
        .status-badge.rejected {
            background: #f8d7da;
            color: #721c24;
        }
        
        .status-badge.countered {
            background: #cce5ff;
            color: #004085;
        }
        
        .quotation-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            border: 1px solid #e9ecef;
            transition: all 0.3s;
        }
        
        .quotation-card:hover {
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .quotation-card .quotation-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
            padding-bottom: 10px;
            border-bottom: 1px solid #e9ecef;
            margin-bottom: 10px;
        }
        
        .quotation-card .quotation-body {
            margin: 10px 0;
        }
        
        .quotation-card .quotation-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
            padding-top: 10px;
            border-top: 1px solid #e9ecef;
            margin-top: 10px;
        }
        
        .btn-action {
            padding: 5px 12px;
            font-size: 0.8rem;
            border-radius: 8px;
        }
        
        .btn-action:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        .response-form {
            display: none;
            margin-top: 15px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 10px;
            border: 1px solid #e9ecef;
        }
        
        .response-form.show {
            display: block;
            animation: fadeIn 0.3s ease;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
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
        
        .product-details {
            background: #f8f9fa;
            padding: 10px 15px;
            border-radius: 8px;
            margin: 5px 0;
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
            .quotation-card .quotation-header {
                flex-direction: column;
                align-items: stretch;
            }
            .quotation-card .quotation-footer {
                flex-direction: column;
                align-items: stretch;
            }
            .response-form {
                padding: 10px;
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
                    <a class="nav-link" href="../earnings/earnings.php">
                        <i class="fas fa-money-bill-wave"></i> Earnings
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="quotation-requests.php">
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
                    <h4 class="mb-0"><i class="fas fa-file-alt me-2 text-success"></i>Quotation Requests</h4>
                    <p class="text-muted">Manage quotation requests from customers</p>
                </div>
                <div>
                    <span class="badge bg-success">Supplier</span>
                </div>
            </div>
            
            <!-- Statistics Cards -->
            <div class="row g-3 mb-4">
                <div class="col-6 col-md-2">
                    <div class="stats-card">
                        <div class="stat-icon text-primary"><i class="fas fa-file-alt"></i></div>
                        <div class="stat-number"><?php echo number_format($total_requests); ?></div>
                        <div class="stat-label">Total Requests</div>
                    </div>
                </div>
                <div class="col-6 col-md-2">
                    <div class="stats-card">
                        <div class="stat-icon text-warning"><i class="fas fa-clock"></i></div>
                        <div class="stat-number"><?php echo number_format($pending_requests); ?></div>
                        <div class="stat-label">Pending</div>
                    </div>
                </div>
                <div class="col-6 col-md-2">
                    <div class="stats-card">
                        <div class="stat-icon text-success"><i class="fas fa-check-circle"></i></div>
                        <div class="stat-number"><?php echo number_format($accepted_requests); ?></div>
                        <div class="stat-label">Accepted</div>
                    </div>
                </div>
                <div class="col-6 col-md-2">
                    <div class="stats-card">
                        <div class="stat-icon text-danger"><i class="fas fa-times-circle"></i></div>
                        <div class="stat-number"><?php echo number_format($rejected_requests); ?></div>
                        <div class="stat-label">Rejected</div>
                    </div>
                </div>
                <div class="col-6 col-md-2">
                    <div class="stats-card">
                        <div class="stat-icon text-info"><i class="fas fa-exchange-alt"></i></div>
                        <div class="stat-number"><?php echo number_format($countered_requests); ?></div>
                        <div class="stat-label">Countered</div>
                    </div>
                </div>
            </div>
            
            <!-- Quotations Table -->
            <div class="table-container">
                <div class="table-header">
                    <div class="d-flex gap-2 flex-wrap">
                        <div class="btn-group">
                            <a href="?status=all" class="btn btn-sm <?php echo $status_filter == 'all' ? 'btn-primary' : 'btn-outline-primary'; ?>">All</a>
                            <a href="?status=pending" class="btn btn-sm <?php echo $status_filter == 'pending' ? 'btn-warning' : 'btn-outline-warning'; ?>">Pending</a>
                            <a href="?status=accepted" class="btn btn-sm <?php echo $status_filter == 'accepted' ? 'btn-success' : 'btn-outline-success'; ?>">Accepted</a>
                            <a href="?status=rejected" class="btn btn-sm <?php echo $status_filter == 'rejected' ? 'btn-danger' : 'btn-outline-danger'; ?>">Rejected</a>
                            <a href="?status=countered" class="btn btn-sm <?php echo $status_filter == 'countered' ? 'btn-info' : 'btn-outline-info'; ?>">Countered</a>
                        </div>
                    </div>
                    
                    <form method="GET" action="" class="d-flex gap-2">
                        <input type="hidden" name="status" value="<?php echo $status_filter; ?>">
                        <input type="text" name="search" class="form-control form-control-sm" placeholder="Search..." 
                               value="<?php echo htmlspecialchars($search); ?>" style="width: 200px;">
                        <button type="submit" class="btn btn-sm btn-primary"><i class="fas fa-search"></i></button>
                        <?php if (!empty($search)): ?>
                            <a href="?status=<?php echo $status_filter; ?>" class="btn btn-sm btn-secondary"><i class="fas fa-times"></i></a>
                        <?php endif; ?>
                    </form>
                </div>
                
                <?php if (empty($quotations)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-file-alt fa-3x text-muted mb-3"></i>
                        <h5>No quotation requests found</h5>
                        <p class="text-muted">You haven't received any quotation requests yet.</p>
                    </div>
                <?php else: ?>
                    <!-- Quotation Cards -->
                    <?php foreach ($quotations as $quotation): ?>
                    <div class="quotation-card" id="quotation-<?php echo $quotation['id']; ?>">
                        <div class="quotation-header">
                            <div>
                                <h6 class="mb-1">
                                    <span class="fw-bold">#<?php echo str_pad($quotation['id'], 6, '0', STR_PAD_LEFT); ?></span>
                                </h6>
                                <small class="text-muted">
                                    <i class="far fa-calendar-alt me-1"></i>
                                    <?php echo date('M d, Y H:i', strtotime($quotation['created_at'])); ?>
                                </small>
                            </div>
                            <div class="d-flex gap-2 align-items-center flex-wrap">
                                <span class="status-badge <?php echo $quotation['status']; ?>">
                                    <?php echo ucfirst($quotation['status']); ?>
                                </span>
                                <small class="text-muted">
                                    <i class="fas fa-user me-1"></i>
                                    <?php echo htmlspecialchars($quotation['customer_name'] ?? 'Guest'); ?>
                                </small>
                            </div>
                        </div>
                        
                        <div class="quotation-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <strong>Product:</strong>
                                    <?php if (!empty($quotation['product_name'])): ?>
                                        <span><?php echo htmlspecialchars($quotation['product_name']); ?></span>
                                        <br>
                                        <small class="text-muted">SKU: <?php echo htmlspecialchars($quotation['product_sku']); ?></small>
                                    <?php else: ?>
                                        <span>Multiple Products</span>
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-3">
                                    <strong>Quantity:</strong>
                                    <span><?php echo number_format($quotation['quantity'] ?? 1); ?></span>
                                </div>
                                <div class="col-md-3">
                                    <strong>Customer Price:</strong>
                                    <span class="fw-bold">TSh <?php echo number_format($quotation['customer_price'] ?? 0, 2); ?></span>
                                </div>
                            </div>
                            
                            <?php if (!empty($quotation['customer_notes'])): ?>
                            <div class="product-details mt-2">
                                <strong>Customer Notes:</strong>
                                <p class="mb-0"><?php echo nl2br(htmlspecialchars($quotation['customer_notes'])); ?></p>
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($quotation['status'] !== 'pending'): ?>
                                <div class="product-details mt-2">
                                    <strong>Your Response:</strong>
                                    <?php if (!empty($quotation['supplier_quote'])): ?>
                                        <p class="mb-0">Price: TSh <?php echo number_format($quotation['supplier_quote'], 2); ?></p>
                                    <?php endif; ?>
                                    <?php if (!empty($quotation['delivery_time'])): ?>
                                        <p class="mb-0">Delivery: <?php echo htmlspecialchars($quotation['delivery_time']); ?></p>
                                    <?php endif; ?>
                                    <?php if (!empty($quotation['supplier_notes'])): ?>
                                        <p class="mb-0">Notes: <?php echo nl2br(htmlspecialchars($quotation['supplier_notes'])); ?></p>
                                    <?php endif; ?>
                                    <small class="text-muted">
                                        Responded: <?php echo date('M d, Y H:i', strtotime($quotation['responded_at'] ?? $quotation['updated_at'])); ?>
                                    </small>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="quotation-footer">
                            <div>
                                <span class="text-muted">Customer: <?php echo htmlspecialchars($quotation['customer_email'] ?? ''); ?></span>
                                <?php if (!empty($quotation['customer_phone'])): ?>
                                    <span class="text-muted ms-2">| <?php echo htmlspecialchars($quotation['customer_phone']); ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="d-flex gap-2 flex-wrap">
                                <?php if ($quotation['status'] == 'pending'): ?>
                                    <button onclick="showResponseForm(<?php echo $quotation['id']; ?>)" class="btn btn-sm btn-success btn-action">
                                        <i class="fas fa-reply me-1"></i> Respond
                                    </button>
                                <?php else: ?>
                                    <button onclick="viewQuotation(<?php echo $quotation['id']; ?>)" class="btn btn-sm btn-outline-primary btn-action">
                                        <i class="fas fa-eye me-1"></i> View
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Response Form -->
                        <div class="response-form" id="response-form-<?php echo $quotation['id']; ?>">
                            <h6 class="mb-3"><i class="fas fa-reply me-2"></i>Respond to Quotation</h6>
                            <form onsubmit="respondQuotation(event, <?php echo $quotation['id']; ?>)">
                                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                
                                <div class="row">
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Your Price (TSh) *</label>
                                        <input type="number" name="quote_price" class="form-control" 
                                               placeholder="0.00" step="0.01" min="0" required
                                               value="<?php echo $quotation['customer_price'] ?? ''; ?>">
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Delivery Time</label>
                                        <input type="text" name="delivery_time" class="form-control" 
                                               placeholder="e.g., 3-5 business days">
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Response Type *</label>
                                        <select name="response_type" class="form-select" required>
                                            <option value="accepted">Accept</option>
                                            <option value="counter">Counter Offer</option>
                                            <option value="rejected">Reject</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Notes</label>
                                    <textarea name="notes" class="form-control" rows="2" 
                                              placeholder="Add any additional notes for the customer"></textarea>
                                </div>
                                
                                <div class="d-flex gap-2">
                                    <button type="submit" class="btn btn-success">
                                        <i class="fas fa-paper-plane me-1"></i> Send Response
                                    </button>
                                    <button type="button" class="btn btn-secondary" onclick="hideResponseForm(<?php echo $quotation['id']; ?>)">
                                        <i class="fas fa-times me-1"></i> Cancel
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    
                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                    <nav aria-label="Page navigation" class="mt-3">
                        <ul class="pagination justify-content-center">
                            <?php if ($current_page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $current_page - 1; ?>&status=<?php echo $status_filter; ?>&search=<?php echo urlencode($search); ?>">
                                        <i class="fas fa-chevron-left"></i>
                                    </a>
                                </li>
                            <?php endif; ?>
                            
                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <li class="page-item <?php echo $i == $current_page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?>&status=<?php echo $status_filter; ?>&search=<?php echo urlencode($search); ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                            <?php endfor; ?>
                            
                            <?php if ($current_page < $total_pages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $current_page + 1; ?>&status=<?php echo $status_filter; ?>&search=<?php echo urlencode($search); ?>">
                                        <i class="fas fa-chevron-right"></i>
                                    </a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                    <?php endif; ?>
                <?php endif; ?>
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
                    <li><a href="quotation-requests.php">Quotations</a></li>
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
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
// =============================================
// SHOW RESPONSE FORM
// =============================================
function showResponseForm(quotationId) {
    // Hide all forms first
    document.querySelectorAll('.response-form').forEach(function(form) {
        form.classList.remove('show');
    });
    
    // Show the selected form
    var form = document.getElementById('response-form-' + quotationId);
    if (form) {
        form.classList.add('show');
        form.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
}

// =============================================
// HIDE RESPONSE FORM
// =============================================
function hideResponseForm(quotationId) {
    var form = document.getElementById('response-form-' + quotationId);
    if (form) {
        form.classList.remove('show');
    }
}

// =============================================
// VIEW QUOTATION
// =============================================
function viewQuotation(quotationId) {
    // Redirect to quotation details page
    window.location.href = 'view-quotation.php?id=' + quotationId;
}

// =============================================
// RESPOND TO QUOTATION - AJAX
// =============================================
function respondQuotation(event, quotationId) {
    event.preventDefault();
    
    // Get form data
    var form = event.target;
    var formData = new FormData(form);
    
    // Get CSRF token from meta tag
    var csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    
    // Add action and quotation id
    formData.append('action', 'respond_quotation');
    formData.append('quotation_id', quotationId);
    formData.append('csrf_token', csrfToken || '');
    
    // Show confirmation
    Swal.fire({
        title: 'Send Response?',
        text: 'Are you sure you want to send this response to the customer?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#198754',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Yes, Send!',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            // Show loading
            Swal.fire({
                title: 'Sending...',
                text: 'Please wait while we send your response.',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            
            // Send AJAX request
            $.ajax({
                url: window.location.href,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Sent!',
                            text: response.message,
                            timer: 2000,
                            showConfirmButton: false
                        }).then(() => {
                            location.reload();
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: response.message || 'Failed to send response.'
                        });
                    }
                },
                error: function(xhr, status, error) {
                    console.log('Error:', error);
                    console.log('Response:', xhr.responseText);
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'An error occurred. Please try again.'
                    });
                }
            });
        }
    });
}

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
// CONSOLE LOG FOR DEBUGGING
// =============================================
console.log('CSRF Token from meta:', document.querySelector('meta[name="csrf-token"]')?.getAttribute('content'));
console.log('Page loaded: <?php echo $page_title; ?>');
</script>

</body>
</html>