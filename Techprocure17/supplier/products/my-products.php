<?php
/**
 * TechProcure Tanzania - Supplier My Products
 * File: supplier/products/my-products.php
 * Description: Suppliers can view and manage their products
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
// GET PRODUCTS COUNT
// =============================================
$total_products = 0;
$pending_products = 0;
$active_products = 0;
$out_of_stock_products = 0;

try {
    // Get total products
    $count_stmt = $conn->prepare("SELECT COUNT(*) as total FROM products WHERE supplier_id = ?");
    $count_stmt->execute([$supplier_id]);
    $total_products = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
    // Get pending products
    $pending_stmt = $conn->prepare("SELECT COUNT(*) as total FROM products WHERE supplier_id = ? AND approval_status = 'pending'");
    $pending_stmt->execute([$supplier_id]);
    $pending_products = $pending_stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
    // Get active products
    $active_stmt = $conn->prepare("SELECT COUNT(*) as total FROM products WHERE supplier_id = ? AND status = 'active' AND approval_status = 'approved'");
    $active_stmt->execute([$supplier_id]);
    $active_products = $active_stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
    // Get out of stock products
    $out_stmt = $conn->prepare("SELECT COUNT(*) as total FROM products WHERE supplier_id = ? AND quantity <= 0");
    $out_stmt->execute([$supplier_id]);
    $out_of_stock_products = $out_stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
} catch (PDOException $e) {
    // Continue with zeros
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
// FETCH PRODUCTS
// =============================================
$products = [];
$total_pages = 0;
$current_page = $page;

try {
    // Build query
    $sql = "SELECT p.*, 
            c.name as category_name,
            b.name as brand_name,
            (SELECT COUNT(*) FROM order_items oi WHERE oi.product_id = p.id) as order_count,
            (SELECT AVG(rating) FROM product_reviews pr WHERE pr.product_id = p.id) as avg_rating
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.id
            LEFT JOIN brands b ON p.brand_id = b.id
            WHERE p.supplier_id = ?";
    
    $params = [$supplier_id];
    
    // Apply status filter
    if ($status_filter !== 'all') {
        if ($status_filter === 'pending') {
            $sql .= " AND p.approval_status = 'pending'";
        } elseif ($status_filter === 'approved') {
            $sql .= " AND p.approval_status = 'approved'";
        } elseif ($status_filter === 'rejected') {
            $sql .= " AND p.approval_status = 'rejected'";
        } elseif ($status_filter === 'active') {
            $sql .= " AND p.status = 'active' AND p.approval_status = 'approved'";
        } elseif ($status_filter === 'inactive') {
            $sql .= " AND p.status = 'inactive'";
        } elseif ($status_filter === 'out_of_stock') {
            $sql .= " AND p.quantity <= 0 AND p.approval_status = 'approved'";
        }
    }
    
    // Apply search
    if (!empty($search)) {
        $sql .= " AND (p.name LIKE ? OR p.sku LIKE ? OR p.description LIKE ?)";
        $search_param = "%$search%";
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
    }
    
    // Get total count for pagination
    $count_sql = str_replace("SELECT p.*, c.name as category_name, b.name as brand_name, 
            (SELECT COUNT(*) FROM order_items oi WHERE oi.product_id = p.id) as order_count,
            (SELECT AVG(rating) FROM product_reviews pr WHERE pr.product_id = p.id) as avg_rating", 
            "SELECT COUNT(*) as total", $sql);
    
    $count_stmt = $conn->prepare($count_sql);
    $count_stmt->execute($params);
    $total_records = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    $total_pages = ceil($total_records / $limit);
    
    // Add order and pagination
    $sql .= " ORDER BY p.created_at DESC LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $error = "Failed to load products: " . $e->getMessage();
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
$page_title = 'My Products - Supplier Panel';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
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
        
        .product-image {
            width: 50px;
            height: 50px;
            border-radius: 8px;
            object-fit: cover;
            border: 1px solid #e9ecef;
        }
        
        .status-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 500;
        }
        
        .status-badge.active {
            background: #d4edda;
            color: #155724;
        }
        
        .status-badge.inactive {
            background: #f8d7da;
            color: #721c24;
        }
        
        .status-badge.pending {
            background: #fff3cd;
            color: #856404;
        }
        
        .status-badge.approved {
            background: #d4edda;
            color: #155724;
        }
        
        .status-badge.rejected {
            background: #f8d7da;
            color: #721c24;
        }
        
        .status-badge.out_of_stock {
            background: #f8d7da;
            color: #721c24;
        }
        
        .status-badge.draft {
            background: #e2e3e5;
            color: #383d41;
        }
        
        .status-badge.discontinued {
            background: #6c757d;
            color: white;
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
                    <a class="nav-link active" href="my-products.php">
                        <i class="fas fa-box"></i> My Products
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="add-product.php">
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
                    <a class="nav-link" href="../quotations/quotation-requests.php">
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
                    <h4 class="mb-0"><i class="fas fa-box me-2 text-success"></i>My Products</h4>
                    <p class="text-muted">Manage all your products in one place</p>
                </div>
                <a href="add-product.php" class="btn btn-success">
                    <i class="fas fa-plus-circle me-2"></i> Add New Product
                </a>
            </div>
            
            <!-- Statistics Cards -->
            <div class="row g-3 mb-4">
                <div class="col-6 col-md-3">
                    <div class="stats-card">
                        <div class="stat-icon text-primary"><i class="fas fa-boxes"></i></div>
                        <div class="stat-number"><?php echo number_format($total_products); ?></div>
                        <div class="stat-label">Total Products</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="stats-card">
                        <div class="stat-icon text-warning"><i class="fas fa-clock"></i></div>
                        <div class="stat-number"><?php echo number_format($pending_products); ?></div>
                        <div class="stat-label">Pending Approval</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="stats-card">
                        <div class="stat-icon text-success"><i class="fas fa-check-circle"></i></div>
                        <div class="stat-number"><?php echo number_format($active_products); ?></div>
                        <div class="stat-label">Active Products</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="stats-card">
                        <div class="stat-icon text-danger"><i class="fas fa-exclamation-triangle"></i></div>
                        <div class="stat-number"><?php echo number_format($out_of_stock_products); ?></div>
                        <div class="stat-label">Out of Stock</div>
                    </div>
                </div>
            </div>
            
            <!-- Products Table -->
            <div class="table-container">
                <div class="table-header">
                    <div class="d-flex gap-2">
                        <div class="btn-group">
                            <a href="?status=all" class="btn btn-sm <?php echo $status_filter == 'all' ? 'btn-primary' : 'btn-outline-primary'; ?>">All</a>
                            <a href="?status=active" class="btn btn-sm <?php echo $status_filter == 'active' ? 'btn-success' : 'btn-outline-success'; ?>">Active</a>
                            <a href="?status=pending" class="btn btn-sm <?php echo $status_filter == 'pending' ? 'btn-warning' : 'btn-outline-warning'; ?>">Pending</a>
                            <a href="?status=out_of_stock" class="btn btn-sm <?php echo $status_filter == 'out_of_stock' ? 'btn-danger' : 'btn-outline-danger'; ?>">Out of Stock</a>
                        </div>
                    </div>
                    
                    <form method="GET" action="" class="d-flex gap-2">
                        <input type="hidden" name="status" value="<?php echo $status_filter; ?>">
                        <input type="text" name="search" class="form-control form-control-sm" placeholder="Search products..." 
                               value="<?php echo htmlspecialchars($search); ?>" style="width: 200px;">
                        <button type="submit" class="btn btn-sm btn-primary"><i class="fas fa-search"></i></button>
                        <?php if (!empty($search)): ?>
                            <a href="?status=<?php echo $status_filter; ?>" class="btn btn-sm btn-secondary"><i class="fas fa-times"></i></a>
                        <?php endif; ?>
                    </form>
                </div>
                
                <?php if (empty($products)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-box-open fa-3x text-muted mb-3"></i>
                        <h5>No products found</h5>
                        <p class="text-muted">You haven't added any products yet. Start by adding your first product.</p>
                        <a href="add-product.php" class="btn btn-success">
                            <i class="fas fa-plus-circle me-2"></i> Add Product
                        </a>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead>
                                <tr>
                                    <th>Image</th>
                                    <th>Product</th>
                                    <th>SKU</th>
                                    <th>Price</th>
                                    <th>Quantity</th>
                                    <th>Status</th>
                                    <th>Approval</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($products as $product): ?>
                                <tr>
                                    <td>
                                        <?php if (!empty($product['image_path'])): ?>
                                            <img src="../../uploads/<?php echo $product['image_path']; ?>" class="product-image" alt="<?php echo htmlspecialchars($product['name']); ?>">
                                        <?php else: ?>
                                            <div class="product-image d-flex align-items-center justify-content-center bg-light">
                                                <i class="fas fa-image text-muted"></i>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="fw-semibold"><?php echo htmlspecialchars($product['name']); ?></div>
                                        <small class="text-muted">
                                            <?php echo htmlspecialchars($product['category_name'] ?? 'Uncategorized'); ?>
                                            <?php if (!empty($product['brand_name'])): ?>
                                                • <?php echo htmlspecialchars($product['brand_name']); ?>
                                            <?php endif; ?>
                                        </small>
                                    </td>
                                    <td><code><?php echo htmlspecialchars($product['sku']); ?></code></td>
                                    <td class="fw-bold">TSh <?php echo number_format($product['price'], 2); ?></td>
                                    <td>
                                        <?php if ($product['quantity'] <= 0): ?>
                                            <span class="text-danger">0</span>
                                        <?php else: ?>
                                            <?php echo number_format($product['quantity']); ?>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php
                                        $status_class = 'draft';
                                        $status_text = 'Draft';
                                        
                                        if ($product['status'] == 'active') {
                                            $status_class = 'active';
                                            $status_text = 'Active';
                                        } elseif ($product['status'] == 'inactive') {
                                            $status_class = 'inactive';
                                            $status_text = 'Inactive';
                                        } elseif ($product['status'] == 'out_of_stock') {
                                            $status_class = 'out_of_stock';
                                            $status_text = 'Out of Stock';
                                        } elseif ($product['status'] == 'discontinued') {
                                            $status_class = 'discontinued';
                                            $status_text = 'Discontinued';
                                        }
                                        
                                        if ($product['quantity'] <= 0 && $product['status'] == 'active') {
                                            $status_class = 'out_of_stock';
                                            $status_text = 'Out of Stock';
                                        }
                                        ?>
                                        <span class="status-badge <?php echo $status_class; ?>">
                                            <?php echo $status_text; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php
                                        $approval_status = $product['approval_status'] ?? 'pending';
                                        $approval_class = 'pending';
                                        $approval_text = 'Pending';
                                        
                                        if ($approval_status == 'approved') {
                                            $approval_class = 'approved';
                                            $approval_text = 'Approved';
                                        } elseif ($approval_status == 'rejected') {
                                            $approval_class = 'rejected';
                                            $approval_text = 'Rejected';
                                        }
                                        ?>
                                        <span class="status-badge <?php echo $approval_class; ?>">
                                            <?php echo $approval_text; ?>
                                        </span>
                                    </td>
                                    <td class="text-end">
                                        <div class="btn-group btn-group-sm">
                                            <a href="edit-product.php?id=<?php echo $product['id']; ?>" class="btn btn-outline-primary" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="view-product.php?id=<?php echo $product['id']; ?>" class="btn btn-outline-info" title="View">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <button type="button" class="btn btn-outline-danger" title="Delete" 
                                                    onclick="deleteProduct(<?php echo $product['id']; ?>, '<?php echo addslashes($product['name']); ?>')">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
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
                    <li><a href="my-products.php">My Products</a></li>
                    <li><a href="../orders/supplier-orders.php">Orders</a></li>
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
// DELETE PRODUCT
// =============================================
function deleteProduct(productId, productName) {
    Swal.fire({
        title: 'Delete Product?',
        text: `Are you sure you want to delete "${productName}"? This action cannot be undone.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Yes, delete it!',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            // Show loading
            Swal.fire({
                title: 'Deleting...',
                text: 'Please wait while we delete the product.',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            
            // Send delete request
            $.ajax({
                url: 'delete-product.php',
                type: 'POST',
                data: {
                    product_id: productId,
                    csrf_token: '<?php echo $_SESSION['csrf_token'] ?? ''; ?>'
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Deleted!',
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
                            text: response.message || 'Failed to delete product.'
                        });
                    }
                },
                error: function() {
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
// TOGGLE PRODUCT STATUS
// =============================================
function toggleStatus(productId, currentStatus) {
    const newStatus = currentStatus === 'active' ? 'inactive' : 'active';
    const action = newStatus === 'active' ? 'activate' : 'deactivate';
    
    Swal.fire({
        title: `${action.charAt(0).toUpperCase() + action.slice(1)} Product?`,
        text: `Are you sure you want to ${action} this product?`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#198754',
        cancelButtonColor: '#6c757d',
        confirmButtonText: `Yes, ${action} it!`,
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                url: 'toggle-product-status.php',
                type: 'POST',
                data: {
                    product_id: productId,
                    status: newStatus,
                    csrf_token: '<?php echo $_SESSION['csrf_token'] ?? ''; ?>'
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Updated!',
                            text: response.message,
                            timer: 1500,
                            showConfirmButton: false
                        }).then(() => {
                            location.reload();
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: response.message || 'Failed to update status.'
                        });
                    }
                },
                error: function() {
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
</script>

</body>
</html>