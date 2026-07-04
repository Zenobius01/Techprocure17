<?php
/**
 * TechProcure Tanzania - Admin Manage Products
 * File: admin/products/manage-products.php
 * Description: Admin can view, filter, approve, and manage all products
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
// HANDLE PRODUCT ACTIONS
// =============================================

// Toggle product status (active/inactive)
if (isset($_GET['toggle_status']) && is_numeric($_GET['toggle_status'])) {
    $product_id = (int)$_GET['toggle_status'];
    try {
        $stmt = $db->prepare("UPDATE products SET status = IF(status = 'active', 'inactive', 'active') WHERE id = ?");
        $stmt->execute([$product_id]);
        $_SESSION['success'] = "Product status updated successfully!";
        logActivity($user_id, 'Toggled Product Status', 'product', $product_id);
    } catch (PDOException $e) {
        $_SESSION['error'] = "Failed to update product status.";
    }
    header("Location: manage-products.php");
    exit();
}

// Approve product
if (isset($_GET['approve']) && is_numeric($_GET['approve'])) {
    $product_id = (int)$_GET['approve'];
    try {
        $stmt = $db->prepare("UPDATE products SET approval_status = 'approved', approved_by = ?, approved_at = NOW() WHERE id = ?");
        $stmt->execute([$user_id, $product_id]);
        $_SESSION['success'] = "Product approved successfully!";
        logActivity($user_id, 'Approved Product', 'product', $product_id);
    } catch (PDOException $e) {
        $_SESSION['error'] = "Failed to approve product.";
    }
    header("Location: manage-products.php");
    exit();
}

// Reject product
if (isset($_GET['reject']) && is_numeric($_GET['reject'])) {
    $product_id = (int)$_GET['reject'];
    try {
        $stmt = $db->prepare("UPDATE products SET approval_status = 'rejected', approved_by = ?, approved_at = NOW() WHERE id = ?");
        $stmt->execute([$user_id, $product_id]);
        $_SESSION['success'] = "Product rejected.";
        logActivity($user_id, 'Rejected Product', 'product', $product_id);
    } catch (PDOException $e) {
        $_SESSION['error'] = "Failed to reject product.";
    }
    header("Location: manage-products.php");
    exit();
}

// Delete product
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $product_id = (int)$_GET['delete'];
    try {
        // Delete product images first
        $stmt = $db->prepare("SELECT image_path FROM product_images WHERE product_id = ?");
        $stmt->execute([$product_id]);
        $images = $stmt->fetchAll();
        
        foreach ($images as $image) {
            $file_path = '../../' . $image['image_path'];
            if (file_exists($file_path)) {
                unlink($file_path);
            }
        }
        
        // Delete product
        $stmt = $db->prepare("DELETE FROM products WHERE id = ?");
        $stmt->execute([$product_id]);
        
        $_SESSION['success'] = "Product deleted successfully!";
        logActivity($user_id, 'Deleted Product', 'product', $product_id);
    } catch (PDOException $e) {
        $_SESSION['error'] = "Failed to delete product.";
    }
    header("Location: manage-products.php");
    exit();
}

// =============================================
// GET FILTER PARAMETERS
// =============================================

$filter = isset($_GET['filter']) ? trim($_GET['filter']) : 'all';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$category = isset($_GET['category']) ? (int)$_GET['category'] : 0;
$supplier = isset($_GET['supplier']) ? (int)$_GET['supplier'] : 0;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// =============================================
// BUILD PRODUCT QUERY
// =============================================

$where_conditions = ["1=1"];
$params = [];

// Status filter
if ($filter == 'pending') {
    $where_conditions[] = "p.approval_status = 'pending'";
} elseif ($filter == 'approved') {
    $where_conditions[] = "p.approval_status = 'approved'";
} elseif ($filter == 'rejected') {
    $where_conditions[] = "p.approval_status = 'rejected'";
} elseif ($filter == 'active') {
    $where_conditions[] = "p.status = 'active'";
} elseif ($filter == 'inactive') {
    $where_conditions[] = "p.status = 'inactive'";
}

// Search filter
if (!empty($search)) {
    $where_conditions[] = "(p.product_name LIKE ? OR p.sku LIKE ? OR p.brand LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

// Category filter
if ($category > 0) {
    $where_conditions[] = "p.category_id = ?";
    $params[] = $category;
}

// Supplier filter
if ($supplier > 0) {
    $where_conditions[] = "p.supplier_id = ?";
    $params[] = $supplier;
}

$where_clause = implode(" AND ", $where_conditions);

// =============================================
// COUNT TOTAL PRODUCTS
// =============================================

$total_products = 0;
$total_pages = 1;

try {
    $count_sql = "SELECT COUNT(*) as total FROM products p WHERE $where_clause";
    $stmt = $db->prepare($count_sql);
    $stmt->execute($params);
    $total_products = (int)$stmt->fetchColumn();
    $total_pages = ceil($total_products / $limit);
} catch (PDOException $e) {
    $total_products = 0;
    $total_pages = 1;
}

// =============================================
// GET PRODUCTS
// =============================================

$products = [];
try {
    $sql = "SELECT p.*, 
                   c.name as category_name, 
                   s.company_name as supplier_name,
                   (SELECT image_path FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) as primary_image
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.id
            LEFT JOIN suppliers s ON p.supplier_id = s.id
            WHERE $where_clause
            ORDER BY p.created_at DESC
            LIMIT ? OFFSET ?";
    
    $params[] = $limit;
    $params[] = $offset;
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    
    if ($stmt->rowCount() > 0) {
        $products = $stmt->fetchAll();
    }
} catch (PDOException $e) {
    $products = [];
}

// =============================================
// GET CATEGORIES AND SUPPLIERS FOR FILTER
// =============================================

$categories = [];
try {
    $stmt = $db->query("SELECT id, name FROM categories WHERE status = 'active' ORDER BY name");
    if ($stmt->rowCount() > 0) {
        $categories = $stmt->fetchAll();
    }
} catch (PDOException $e) {
    $categories = [];
}

$suppliers = [];
try {
    $stmt = $db->query("SELECT id, company_name FROM suppliers WHERE approval_status = 'approved' AND status = 'active' ORDER BY company_name");
    if ($stmt->rowCount() > 0) {
        $suppliers = $stmt->fetchAll();
    }
} catch (PDOException $e) {
    $suppliers = [];
}

// =============================================
// GET COUNTS FOR STATS
// =============================================

$stats = [];
try {
    $stats['total'] = $db->query("SELECT COUNT(*) FROM products")->fetchColumn();
    $stats['pending'] = $db->query("SELECT COUNT(*) FROM products WHERE approval_status = 'pending'")->fetchColumn();
    $stats['approved'] = $db->query("SELECT COUNT(*) FROM products WHERE approval_status = 'approved'")->fetchColumn();
    $stats['rejected'] = $db->query("SELECT COUNT(*) FROM products WHERE approval_status = 'rejected'")->fetchColumn();
    $stats['active'] = $db->query("SELECT COUNT(*) FROM products WHERE status = 'active'")->fetchColumn();
    $stats['inactive'] = $db->query("SELECT COUNT(*) FROM products WHERE status = 'inactive'")->fetchColumn();
} catch (PDOException $e) {
    $stats = ['total' => 0, 'pending' => 0, 'approved' => 0, 'rejected' => 0, 'active' => 0, 'inactive' => 0];
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

if (!function_exists('getStatusBadge')) {
    function getStatusBadge($status) {
        $colors = [
            'pending' => 'warning',
            'approved' => 'success',
            'rejected' => 'danger',
            'active' => 'success',
            'inactive' => 'secondary'
        ];
        return $colors[$status] ?? 'secondary';
    }
}

// Function to get product image path
function getProductImagePath($product) {
    if (!empty($product['primary_image'])) {
        // If image path already starts with 'uploads/', use as is
        if (strpos($product['primary_image'], 'uploads/') === 0) {
            return '../../' . $product['primary_image'];
        }
        // If it's just a filename, construct the full path
        return '../../uploads/products/' . $product['id'] . '/' . $product['primary_image'];
    }
    // No image - return placeholder
    return '../../assets/images/placeholder-product.jpg';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Products - TechProcure Tanzania</title>
    
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
        
        .product-image-thumb {
            width: 50px;
            height: 50px;
            object-fit: cover;
            border-radius: 8px;
            background: #f8f9fa;
        }
        
        .status-badge {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 500;
        }
        
        .status-badge.pending { background: #fff3cd; color: #856404; }
        .status-badge.approved { background: #d4edda; color: #155724; }
        .status-badge.rejected { background: #f8d7da; color: #721c24; }
        .status-badge.active { background: #d4edda; color: #155724; }
        .status-badge.inactive { background: #e2e3e5; color: #383d41; }
        
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
            <a href="manage-products.php" class="nav-link active">
                <i class="fas fa-box"></i> Products
            </a>
        </div>
        <div class="nav-item">
            <a href="../orders/manage-orders.php" class="nav-link">
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
            <i class="fas fa-box me-2 text-primary"></i> Manage Products
        </div>
        <div class="d-flex align-items-center gap-3">
            <a href="add-product.php" class="btn btn-primary btn-sm">
                <i class="fas fa-plus me-1"></i> Add Product
            </a>
            <div class="admin-avatar">
                <?php echo strtoupper(substr($_SESSION['user_name'] ?? 'A', 0, 1)); ?>
            </div>
        </div>
    </div>
    
    <!-- Stats Cards -->
    <div class="row">
        <div class="col-md-3 col-sm-6">
            <div class="stat-card" style="border-left: 4px solid #0d6efd;">
                <div class="stat-label">Total Products</div>
                <div class="stat-number"><?php echo number_format($stats['total']); ?></div>
                <div class="stat-icon"><i class="fas fa-box"></i></div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="stat-card" style="border-left: 4px solid #ffc107;">
                <div class="stat-label">Pending Approval</div>
                <div class="stat-number"><?php echo number_format($stats['pending']); ?></div>
                <div class="stat-icon"><i class="fas fa-clock"></i></div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="stat-card" style="border-left: 4px solid #198754;">
                <div class="stat-label">Active Products</div>
                <div class="stat-number"><?php echo number_format($stats['active']); ?></div>
                <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="stat-card" style="border-left: 4px solid #dc3545;">
                <div class="stat-label">Inactive Products</div>
                <div class="stat-number"><?php echo number_format($stats['inactive']); ?></div>
                <div class="stat-icon"><i class="fas fa-times-circle"></i></div>
            </div>
        </div>
    </div>
    
    <!-- Filter Section -->
    <div class="data-card">
        <div class="card-title">
            <i class="fas fa-filter"></i> Filter Products
        </div>
        <form method="GET" action="" class="row g-3">
            <div class="col-md-3">
                <input type="text" name="search" class="form-control" placeholder="Search by name, SKU, brand..." value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <div class="col-md-2">
                <select name="category" class="form-select">
                    <option value="0">All Categories</option>
                    <?php foreach($categories as $cat): ?>
                        <option value="<?php echo $cat['id']; ?>" <?php echo $category == $cat['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($cat['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <select name="supplier" class="form-select">
                    <option value="0">All Suppliers</option>
                    <?php foreach($suppliers as $sup): ?>
                        <option value="<?php echo $sup['id']; ?>" <?php echo $supplier == $sup['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($sup['company_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <select name="filter" class="form-select">
                    <option value="all" <?php echo $filter == 'all' ? 'selected' : ''; ?>>All Products</option>
                    <option value="pending" <?php echo $filter == 'pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="approved" <?php echo $filter == 'approved' ? 'selected' : ''; ?>>Approved</option>
                    <option value="rejected" <?php echo $filter == 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                    <option value="active" <?php echo $filter == 'active' ? 'selected' : ''; ?>>Active</option>
                    <option value="inactive" <?php echo $filter == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                </select>
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-primary"><i class="fas fa-search me-1"></i> Filter</button>
                <a href="manage-products.php" class="btn btn-secondary"><i class="fas fa-undo me-1"></i> Reset</a>
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
    
    <!-- Products Table -->
    <div class="data-card">
        <div class="card-title">
            <i class="fas fa-list"></i> Products List
            <span class="badge bg-primary ms-2"><?php echo number_format($total_products); ?></span>
        </div>
        
        <div class="table-responsive">
            <table class="table table-hover" id="productsTable">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Image</th>
                        <th>Product</th>
                        <th>SKU</th>
                        <th>Category</th>
                        <th>Supplier</th>
                        <th>Price</th>
                        <th>Stock</th>
                        <th>Approval</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($products)): ?>
                        <?php foreach($products as $product): ?>
                        <tr>
                            <td><?php echo $product['id']; ?></td>
                            <td>
                                <?php if (!empty($product['primary_image'])): ?>
                                    <img src="<?php echo getProductImagePath($product); ?>" 
                                         class="product-image-thumb" 
                                         alt="<?php echo htmlspecialchars($product['product_name']); ?>"
                                         onerror="this.src='../../assets/images/placeholder-product.jpg'">
                                <?php else: ?>
                                    <img src="../../assets/images/placeholder-product.jpg" 
                                         class="product-image-thumb" 
                                         alt="No Image">
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong><?php echo htmlspecialchars($product['product_name']); ?></strong>
                                <br><small class="text-muted"><?php echo htmlspecialchars($product['brand'] ?? 'N/A'); ?></small>
                            </td>
                            <td><code><?php echo htmlspecialchars($product['sku']); ?></code></td>
                            <td><?php echo htmlspecialchars($product['category_name'] ?? 'Uncategorized'); ?></td>
                            <td><?php echo htmlspecialchars($product['supplier_name'] ?? 'N/A'); ?></td>
                            <td><?php echo formatPrice($product['price_tsh']); ?></td>
                            <td>
                                <?php if($product['stock_quantity'] > 10): ?>
                                    <span class="badge bg-success"><?php echo $product['stock_quantity']; ?></span>
                                <?php elseif($product['stock_quantity'] > 0): ?>
                                    <span class="badge bg-warning"><?php echo $product['stock_quantity']; ?></span>
                                <?php else: ?>
                                    <span class="badge bg-danger">0</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php $badge = getStatusBadge($product['approval_status']); ?>
                                <span class="badge bg-<?php echo $badge; ?>">
                                    <?php echo ucfirst($product['approval_status']); ?>
                                </span>
                            </td>
                            <td>
                                <?php $badge = getStatusBadge($product['status']); ?>
                                <span class="badge bg-<?php echo $badge; ?>">
                                    <?php echo ucfirst($product['status']); ?>
                                </span>
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <a href="edit-product.php?id=<?php echo $product['id']; ?>" class="btn btn-outline-primary" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <?php if($product['approval_status'] == 'pending'): ?>
                                        <a href="?approve=<?php echo $product['id']; ?>" class="btn btn-outline-success" title="Approve" onclick="return confirm('Approve this product?')">
                                            <i class="fas fa-check"></i>
                                        </a>
                                        <a href="?reject=<?php echo $product['id']; ?>" class="btn btn-outline-danger" title="Reject" onclick="return confirm('Reject this product?')">
                                            <i class="fas fa-times"></i>
                                        </a>
                                    <?php endif; ?>
                                    <a href="?toggle_status=<?php echo $product['id']; ?>" class="btn btn-outline-warning" title="Toggle Status" onclick="return confirm('Toggle product status?')">
                                        <i class="fas fa-toggle-<?php echo $product['status'] == 'active' ? 'off' : 'on'; ?>"></i>
                                    </a>
                                    <a href="?delete=<?php echo $product['id']; ?>" class="btn btn-outline-danger" title="Delete" onclick="return confirm('Delete this product permanently?')">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="11" class="text-center text-muted py-4">
                                <i class="fas fa-box-open fa-2x mb-2"></i>
                                <p>No products found</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
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
    </div>
</div>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<script>
    $(document).ready(function() {
        // Check if there are any data rows (excluding the empty state row)
        var hasData = $('#productsTable tbody tr').filter(function() {
            return $(this).find('td').length > 1 && $(this).find('td:first').text() !== '';
        }).length > 0;
        
        if (hasData) {
            $('#productsTable').DataTable({
                responsive: true,
                pageLength: 20,
                ordering: true,
                columnDefs: [
                    { orderable: false, targets: [10] } // Disable sorting on Actions column
                ],
                language: {
                    search: "Search:",
                    lengthMenu: "Show _MENU_ entries",
                    info: "Showing _START_ to _END_ of _TOTAL_ entries",
                    infoEmpty: "No products found",
                    infoFiltered: "(filtered from _MAX_ total entries)"
                }
            });
        } else {
            // No data, just show the table without DataTables
            $('#productsTable').addClass('table-hover');
        }
    });
</script>

</body>
</html>