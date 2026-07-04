<?php
/**
 * TechProcure Tanzania - Homepage
 * File: index.php
 * Description: Main landing page displaying all approved products with category icons
 */

// Start session if not started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include configuration files
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';

$page_title = 'Enterprise IT Procurement Platform Tanzania - TechProcure';
$meta_description = 'TechProcure Tanzania is the leading B2B platform for IT equipment, enterprise technology, and procurement solutions. Shop laptops, servers, networking equipment from verified suppliers.';

// Get database connection
$db = getDB();

// =============================================
// FETCH DATA FOR HOMEPAGE
// =============================================

// Get products - Only show approved and active products
$products = [];
try {
    $sql = "SELECT p.*, c.name as category_name, c.slug as category_slug, 
            s.company_name as supplier_name, s.verification_badge,
            (SELECT image_path FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) as primary_image
            FROM products p 
            LEFT JOIN categories c ON p.category_id = c.id 
            LEFT JOIN suppliers s ON p.supplier_id = s.id 
            WHERE p.approval_status = 'approved' 
            AND p.status = 'active'
            ORDER BY p.created_at DESC 
            LIMIT 12";
    $result = $db->query($sql);
    if ($result && $result->rowCount() > 0) {
        $products = $result->fetchAll();
    }
} catch (PDOException $e) {
    $products = [];
}

// Get featured products
$featured_products = [];
try {
    // Check if is_featured column exists
    $column_check = $db->query("SHOW COLUMNS FROM products LIKE 'is_featured'");
    $has_featured_column = $column_check->rowCount() > 0;
    
    if ($has_featured_column) {
        $sql = "SELECT p.*, c.name as category_name, c.slug as category_slug, 
                s.company_name as supplier_name, s.verification_badge,
                (SELECT image_path FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) as primary_image
                FROM products p 
                LEFT JOIN categories c ON p.category_id = c.id 
                LEFT JOIN suppliers s ON p.supplier_id = s.id 
                WHERE p.approval_status = 'approved' 
                AND p.status = 'active' 
                AND p.is_featured = 1
                ORDER BY p.created_at DESC 
                LIMIT 8";
    } else {
        // If no is_featured column, get latest products
        $sql = "SELECT p.*, c.name as category_name, c.slug as category_slug, 
                s.company_name as supplier_name, s.verification_badge,
                (SELECT image_path FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) as primary_image
                FROM products p 
                LEFT JOIN categories c ON p.category_id = c.id 
                LEFT JOIN suppliers s ON p.supplier_id = s.id 
                WHERE p.approval_status = 'approved' AND p.status = 'active'
                ORDER BY p.created_at DESC 
                LIMIT 8";
    }
    $result = $db->query($sql);
    if ($result && $result->rowCount() > 0) {
        $featured_products = $result->fetchAll();
    }
} catch (PDOException $e) {
    $featured_products = [];
}

// If no featured products, get latest approved products
if (empty($featured_products)) {
    try {
        $sql = "SELECT p.*, c.name as category_name, c.slug as category_slug, 
                s.company_name as supplier_name, s.verification_badge,
                (SELECT image_path FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) as primary_image
                FROM products p 
                LEFT JOIN categories c ON p.category_id = c.id 
                LEFT JOIN suppliers s ON p.supplier_id = s.id 
                WHERE p.approval_status = 'approved' AND p.status = 'active'
                ORDER BY p.created_at DESC 
                LIMIT 8";
        $result = $db->query($sql);
        if ($result && $result->rowCount() > 0) {
            $featured_products = $result->fetchAll();
        }
    } catch (PDOException $e) {
        $featured_products = [];
    }
}

// Get categories for display with icons
$categories = [];
try {
    $column_check = $db->query("SHOW COLUMNS FROM categories LIKE 'sort_order'");
    $has_sort_order = $column_check->rowCount() > 0;
    
    if ($has_sort_order) {
        $sql = "SELECT c.*, 
                       (SELECT COUNT(*) FROM products WHERE category_id = c.id AND status = 'active' AND approval_status = 'approved') as product_count
                FROM categories c
                WHERE c.status = 'active' 
                ORDER BY c.sort_order ASC, c.name ASC 
                LIMIT 8";
    } else {
        $sql = "SELECT c.*, 
                       (SELECT COUNT(*) FROM products WHERE category_id = c.id AND status = 'active' AND approval_status = 'approved') as product_count
                FROM categories c
                WHERE c.status = 'active' 
                ORDER BY c.name ASC 
                LIMIT 8";
    }
    $result = $db->query($sql);
    if ($result && $result->rowCount() > 0) {
        $categories = $result->fetchAll();
    }
} catch (PDOException $e) {
    try {
        $sql = "SELECT c.*, 
                       (SELECT COUNT(*) FROM products WHERE category_id = c.id AND status = 'active' AND approval_status = 'approved') as product_count
                FROM categories c
                WHERE c.status = 'active' 
                ORDER BY c.name ASC 
                LIMIT 8";
        $result = $db->query($sql);
        if ($result && $result->rowCount() > 0) {
            $categories = $result->fetchAll();
        }
    } catch (PDOException $e2) {
        $categories = [];
    }
}

// Get category icon
function getCategoryIcon($categoryName) {
    $icons = [
        'Laptops' => 'fa-laptop',
        'Desktops' => 'fa-desktop',
        'Computers' => 'fa-desktop',
        'Servers' => 'fa-server',
        'Networking' => 'fa-network-wired',
        'Software' => 'fa-code',
        'Storage' => 'fa-hdd',
        'Printers' => 'fa-print',
        'Accessories' => 'fa-mouse',
        'Monitors' => 'fa-tv',
        'Tablets' => 'fa-tablet-alt',
        'Phones' => 'fa-phone-alt'
    ];
    return $icons[$categoryName] ?? 'fa-box';
}

// Get category color
function getCategoryColor($categoryName) {
    $colors = [
        'Computers' => '#0d6efd',
        'Laptops' => '#198754',
        'Servers' => '#dc3545',
        'Networking' => '#0dcaf0',
        'Software' => '#6f42c1',
        'Storage' => '#fd7e14',
        'Printers' => '#20c997',
        'Accessories' => '#ffc107',
        'Monitors' => '#6610f2',
        'Tablets' => '#d63384'
    ];
    return $colors[$categoryName] ?? '#0d6efd';
}

// Get top-rated suppliers
$top_suppliers = [];
try {
    $sql = "SELECT * FROM suppliers 
            WHERE approval_status = 'approved' AND status = 'active' 
            ORDER BY rating DESC, total_sales DESC 
            LIMIT 6";
    $result = $db->query($sql);
    if ($result && $result->rowCount() > 0) {
        $top_suppliers = $result->fetchAll();
    }
} catch (PDOException $e) {
    $top_suppliers = [];
}

// Get statistics
$stats = [];
try {
    // Total products count
    $sql = "SELECT COUNT(*) as total FROM products WHERE approval_status = 'approved' AND status = 'active'";
    $result = $db->query($sql);
    $stats['total_products'] = $result ? (int)$result->fetchColumn() : 0;
} catch (PDOException $e) {
    $stats['total_products'] = 0;
}

try {
    // Total suppliers count
    $sql = "SELECT COUNT(*) as total FROM suppliers WHERE approval_status = 'approved' AND status = 'active'";
    $result = $db->query($sql);
    $stats['total_suppliers'] = $result ? (int)$result->fetchColumn() : 0;
} catch (PDOException $e) {
    $stats['total_suppliers'] = 0;
}

try {
    // Total customers/buyers count
    $sql = "SELECT COUNT(*) as total FROM customers WHERE status = 'active'";
    $result = $db->query($sql);
    $stats['total_buyers'] = $result ? (int)$result->fetchColumn() : 0;
} catch (PDOException $e) {
    $stats['total_buyers'] = 0;
}

try {
    // Total orders completed
    $sql = "SELECT COUNT(*) as total FROM orders WHERE order_status IN ('delivered', 'completed') AND payment_status = 'paid'";
    $result = $db->query($sql);
    $stats['total_orders'] = $result ? (int)$result->fetchColumn() : 0;
} catch (PDOException $e) {
    $stats['total_orders'] = 0;
}

// Get cart count
$cart_count = isset($_SESSION['cart']) ? array_sum(array_column($_SESSION['cart'], 'quantity')) : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?php echo $meta_description; ?>">
    <meta name="keywords" content="B2B procurement, IT equipment Tanzania, enterprise technology, business laptops, servers, networking, TechProcure">
    <meta name="author" content="TechProcure Tanzania">
    <meta property="og:title" content="<?php echo $page_title; ?>">
    <meta property="og:description" content="<?php echo $meta_description; ?>">
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?php echo SITE_URL; ?>">
    <meta name="twitter:card" content="summary_large_image">
    
    <title><?php echo $page_title; ?></title>
    
    <!-- CSS Dependencies -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/aos/2.3.4/aos.css">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            overflow-x: hidden;
            background-color: #ffffff;
        }
        
        /* Navbar */
        .navbar {
            background: linear-gradient(135deg, #1a2a6c, #b21f1f, #fdbb4d);
            background-size: 200% 200%;
            padding: 15px 0;
            transition: all 0.3s ease;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .navbar-scrolled {
            padding: 10px 0;
            background: rgba(26, 42, 108, 0.95) !important;
            backdrop-filter: blur(10px);
        }
        
        .navbar-brand {
            font-size: 1.5rem;
            font-weight: 800;
            color: white !important;
        }
        
        .navbar-brand i {
            margin-right: 8px;
        }
        
        .nav-link {
            color: rgba(255,255,255,0.9) !important;
            font-weight: 500;
            margin: 0 5px;
            transition: all 0.3s ease;
        }
        
        .nav-link:hover {
            color: white !important;
            transform: translateY(-2px);
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
        
        .btn-login {
            background: rgba(255,255,255,0.2);
            border: 1px solid rgba(255,255,255,0.3);
            color: white !important;
            border-radius: 50px;
            padding: 5px 15px;
            transition: all 0.3s ease;
            text-decoration: none;
        }
        
        .btn-login:hover {
            background: rgba(255,255,255,0.3);
            transform: translateY(-2px);
        }
        
        .btn-register {
            background: #fdbb4d;
            color: #1a2a6c !important;
            border-radius: 50px;
            padding: 5px 15px;
            font-weight: 600;
            transition: all 0.3s ease;
            text-decoration: none;
        }
        
        .btn-register:hover {
            background: #ffc107;
            transform: translateY(-2px);
        }
        
        /* Hero Section */
        .hero-section {
            background: linear-gradient(135deg, #1a2a6c, #b21f1f, #fdbb4d);
            background-size: 200% 200%;
            animation: gradientBG 15s ease infinite;
            padding: 100px 0;
            color: white;
            
        }
        
        @keyframes gradientBG {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }
        
        .hero-title {
            font-size: 3.5rem;
            font-weight: 800;
            margin-bottom: 20px;
            animation: fadeInUp 1s ease;
        }
        
        .hero-subtitle {
            font-size: 1.2rem;
            opacity: 0.9;
            margin-bottom: 30px;
            animation: fadeInUp 1s ease 0.2s both;
        }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .trust-badge {
            background: rgba(255,255,255,0.15);
            backdrop-filter: blur(10px);
            border-radius: 50px;
            padding: 8px 20px;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 20px;
        }
        
        /* Stats Cards */
        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            text-align: center;
            transition: all 0.3s ease;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        
        .stat-number {
            font-size: 2.5rem;
            font-weight: 800;
            color: #0d6efd;
            margin-bottom: 5px;
        }
        
        /* Category Cards with Icons */
        .category-card {
            transition: all 0.3s ease;
            background: white;
            border-radius: 15px;
            padding: 30px 20px;
            text-align: center;
            text-decoration: none;
            display: block;
            margin-bottom: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            border: 1px solid #e9ecef;
            position: relative;
            overflow: hidden;
            min-height: 180px;
        }
        
        .category-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            opacity: 0.05;
            transition: all 0.3s ease;
        }
        
        .category-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 35px rgba(0,0,0,0.15);
            border-color: #0d6efd;
        }
        
        .category-card:hover::before {
            opacity: 0.1;
        }
        
        .category-card .category-icon {
            font-size: 3.5rem;
            margin-bottom: 15px;
            display: block;
            transition: all 0.3s ease;
        }
        
        .category-card:hover .category-icon {
            transform: scale(1.1);
        }
        
        .category-card .category-name {
            font-size: 1.1rem;
            font-weight: 600;
            color: #333;
            margin-bottom: 5px;
        }
        
        .category-card .category-count {
            font-size: 0.85rem;
            color: #6c757d;
        }
        
        .category-card .category-count i {
            margin-right: 4px;
        }
        
        /* Product Cards */
        .product-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            transition: all 0.3s;
            margin-bottom: 25px;
            overflow: hidden;
            background: white;
        }
        
        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0,0,0,0.15);
        }
        
        .product-image {
            height: 200px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f8f9fa;
            overflow: hidden;
        }
        
        .product-image img {
            max-height: 100%;
            max-width: 100%;
            object-fit: contain;
            padding: 10px;
        }
        
        .product-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            z-index: 10;
        }
        
        .product-title {
            font-size: 1rem;
            font-weight: 600;
            margin-bottom: 8px;
            line-height: 1.4;
            height: 2.8em;
            overflow: hidden;
        }
        
        .product-price {
            font-size: 1.2rem;
            font-weight: 700;
            color: #0d6efd;
        }
        
        .product-old-price {
            font-size: 0.8rem;
            text-decoration: line-through;
            color: #999;
            margin-left: 8px;
        }
        
        .rating-stars {
            color: #ffc107;
            font-size: 0.8rem;
        }
        
        /* Feature Box */
        .feature-box {
            text-align: center;
            padding: 25px;
            border-radius: 12px;
            background: white;
            transition: all 0.3s ease;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }
        
        .feature-box:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        
        /* Supplier Card */
        .supplier-card {
            transition: all 0.3s ease;
            border: none;
            border-radius: 12px;
            overflow: hidden;
        }
        
        .supplier-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        
        /* Testimonial Card */
        .testimonial-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
            transition: all 0.3s ease;
        }
        
        .testimonial-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
        }
        
        .quote-icon {
            font-size: 2rem;
            color: #0d6efd;
            opacity: 0.3;
            margin-bottom: 15px;
        }
        
        /* Newsletter Section */
        .newsletter-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        
        /* Footer */
        .footer {
            background: #1a1a2e;
            color: white;
            padding: 60px 0 30px;
        }
        
        .footer a {
            color: rgba(255,255,255,0.7);
            text-decoration: none;
            transition: all 0.3s;
        }
        
        .footer a:hover {
            color: white;
            padding-left: 5px;
        }
        
        .social-icons a {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 35px;
            height: 35px;
            background: rgba(255,255,255,0.1);
            border-radius: 50%;
            margin: 0 5px;
            transition: all 0.3s;
            color: white;
        }
        
        .social-icons a:hover {
            background: #0d6efd;
            transform: translateY(-3px);
        }
        
        /* Section Title */
        .section-title {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 15px;
            position: relative;
        }
        
        .section-title:after {
            content: '';
            display: block;
            width: 60px;
            height: 3px;
            background: #0d6efd;
            margin: 15px auto 0;
        }
        
        /* Payment Banner */
        .payment-banner {
            background: white;
            border-bottom: 1px solid #e0e0e0;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .hero-title {
                font-size: 2rem;
            }
            .hero-subtitle {
                font-size: 1rem;
            }
            .stat-number {
                font-size: 1.8rem;
            }
            .section-title {
                font-size: 1.5rem;
            }
            .category-card {
                padding: 20px 15px;
                min-height: 140px;
            }
            .category-card .category-icon {
                font-size: 2.5rem;
            }
        }
    </style>
</head>
<body>

<!-- Navbar -->
<nav class="navbar navbar-expand-lg fixed-top" id="mainNavbar">
    <div class="container">
        <a class="navbar-brand" href="index.php">
            <i class="fas fa-microchip"></i>
            TechProcure
        </a>
        
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarMain">
            <span class="navbar-toggler-icon"></span>
        </button>
        
        <div class="collapse navbar-collapse" id="navbarMain">
            <ul class="navbar-nav mx-auto">
                <li class="nav-item">
                    <a class="nav-link" href="index.php">Home</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="products.php">Products</a>
                </li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
                        Categories
                    </a>
                    <ul class="dropdown-menu">
                        <?php foreach($categories as $cat): ?>
                            <li><a class="dropdown-item" href="products.php?category=<?php echo $cat['slug']; ?>"><i class="fas fa-<?php echo getCategoryIcon($cat['name']); ?>"></i> <?php echo htmlspecialchars($cat['name']); ?></a></li>
                        <?php endforeach; ?>
                    </ul>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="suppliers.php">Suppliers</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="about.php">About Us</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="contact.php">Contact</a>
                </li>
            </ul>
            
            <div class="d-flex align-items-center gap-2 flex-wrap flex-lg-nowrap">
                <!-- Search -->
                <form class="d-flex" method="GET" action="products.php">
                    <input class="form-control form-control-sm" type="search" name="search" placeholder="Search..." aria-label="Search" style="border-radius: 50px 0 0 50px; border: none; width: 150px;">
                    <button class="btn btn-sm" type="submit" style="border-radius: 0 50px 50px 0; background: #fdbb4d; border: none; color: #1a2a6c;">
                        <i class="fas fa-search"></i>
                    </button>
                </form>
                
                <!-- Cart -->
                <a href="cart.php" class="nav-link position-relative">
                    <i class="fas fa-shopping-cart fa-lg"></i>
                    <span class="cart-count" id="cartCount"><?php echo $cart_count; ?></span>
                </a>
                
                <!-- User Section -->
                <?php if (isset($_SESSION['user_id']) && isset($_SESSION['user_type'])): ?>
                    <div class="dropdown">
                        <button class="btn btn-link nav-link dropdown-toggle d-flex align-items-center" data-bs-toggle="dropdown">
                            <i class="fas fa-user-circle fa-lg me-1"></i>
                            <?php echo htmlspecialchars(substr($_SESSION['user_name'] ?? 'Account', 0, 15)); ?>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <?php if ($_SESSION['user_type'] == 'customer'): ?>
                                <li><a class="dropdown-item" href="customer/dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                                <li><a class="dropdown-item" href="customer/profile.php"><i class="fas fa-user"></i> My Profile</a></li>
                                <li><a class="dropdown-item" href="customer/orders/my-orders.php"><i class="fas fa-shopping-bag"></i> My Orders</a></li>
                                <li><a class="dropdown-item" href="customer/wishlist.php"><i class="fas fa-heart"></i> Wishlist</a></li>
                                <li><a class="dropdown-item" href="customer/invoices/my-invoices.php"><i class="fas fa-file-invoice"></i> Invoices</a></li>
                            <?php elseif ($_SESSION['user_type'] == 'supplier'): ?>
                                <li><a class="dropdown-item" href="supplier/dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                                <li><a class="dropdown-item" href="supplier/products/my-products.php"><i class="fas fa-box"></i> My Products</a></li>
                                <li><a class="dropdown-item" href="supplier/orders/supplier-orders.php"><i class="fas fa-shopping-cart"></i> Orders</a></li>
                                <li><a class="dropdown-item" href="supplier/earnings/earnings.php"><i class="fas fa-money-bill-wave"></i> Earnings</a></li>
                            <?php elseif ($_SESSION['user_type'] == 'admin'): ?>
                                <li><a class="dropdown-item" href="admin/dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                                <li><a class="dropdown-item" href="admin/users/manage-users.php"><i class="fas fa-users"></i> Manage Users</a></li>
                                <li><a class="dropdown-item" href="admin/products/manage-products.php"><i class="fas fa-box"></i> Manage Products</a></li>
                                <li><a class="dropdown-item" href="admin/orders/manage-orders.php"><i class="fas fa-shopping-cart"></i> Manage Orders</a></li>
                            <?php endif; ?>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="auth/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                        </ul>
                    </div>
                <?php else: ?>
                    <a href="auth/login.php" class="btn-login"><i class="fas fa-sign-in-alt me-1"></i>Login</a>
                    <a href="auth/register.php" class="btn-register"><i class="fas fa-user-plus me-1"></i>Register</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>

<!-- Hero Section -->
<section class="hero-section">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-6" data-aos="fade-right">
                <div class="trust-badge">
                    <i class="fas fa-check-circle"></i>
                    <span>Trusted by 500+ Businesses in Tanzania</span>
                </div>
                <h1 class="hero-title">Enterprise IT Procurement<br><span class="text-warning">Made Easy in Tanzania</span></h1>
                <p class="hero-subtitle">
                    Source, compare, and purchase IT equipment from verified suppliers across Tanzania. 
                    Streamline your B2B procurement with secure payments and escrow protection.
                </p>
                <div class="d-flex flex-wrap gap-3 mb-4">
                    <a href="products.php" class="btn btn-light btn-lg px-4">
                        <i class="fas fa-shopping-cart me-2"></i>Start Shopping
                    </a>
                    <a href="auth/register.php" class="btn btn-outline-light btn-lg px-4">
                        <i class="fas fa-user-plus me-2"></i>Become a Buyer
                    </a>
                    <a href="auth/register.php?type=supplier" class="btn btn-outline-light btn-lg px-4">
                        <i class="fas fa-store me-2"></i>Sell on TechProcure
                    </a>
                </div>
                <div class="d-flex flex-wrap gap-4">
                    <div><i class="fas fa-truck fa-fw me-2"></i><small>Fast Delivery Across Tanzania</small></div>
                    <div><i class="fas fa-shield-alt fa-fw me-2"></i><small>Escrow Payment Protection</small></div>
                    <div><i class="fas fa-headset fa-fw me-2"></i><small>24/7 Local Support</small></div>
                </div>
            </div>
            <div class="col-lg-6 text-center d-none d-lg-block" data-aos="fade-left">
                <div class="bg-white rounded-circle mx-auto d-flex align-items-center justify-content-center" style="width: 300px; height: 300px; background: rgba(255,255,255,0.1) !important;">
                    <i class="fas fa-microchip fa-8x text-white"></i>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Statistics Section -->
<section class="py-5 bg-light">
    <div class="container">
        <div class="row g-4">
            <div class="col-md-3 col-6" data-aos="zoom-in" data-aos-delay="100">
                <div class="stat-card">
                    <div class="stat-number"><?php echo number_format($stats['total_products'] ?? 0); ?>+</div>
                    <div class="text-muted">IT Products</div>
                </div>
            </div>
            <div class="col-md-3 col-6" data-aos="zoom-in" data-aos-delay="200">
                <div class="stat-card">
                    <div class="stat-number"><?php echo number_format($stats['total_suppliers'] ?? 0); ?>+</div>
                    <div class="text-muted">Verified Suppliers</div>
                </div>
            </div>
            <div class="col-md-3 col-6" data-aos="zoom-in" data-aos-delay="300">
                <div class="stat-card">
                    <div class="stat-number"><?php echo number_format($stats['total_buyers'] ?? 0); ?>+</div>
                    <div class="text-muted">Active Buyers</div>
                </div>
            </div>
            <div class="col-md-3 col-6" data-aos="zoom-in" data-aos-delay="400">
                <div class="stat-card">
                    <div class="stat-number"><?php echo number_format($stats['total_orders'] ?? 0); ?>+</div>
                    <div class="text-muted">Orders Completed</div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Categories Section with Icons -->
<section class="py-5">
    <div class="container">
        <div class="text-center mb-5" data-aos="fade-up">
            <h2 class="section-title">Shop by Category</h2>
            <p class="text-muted">Browse our wide range of IT products across different categories</p>
        </div>
        <div class="row g-4">
            <?php if (!empty($categories)): ?>
                <?php foreach($categories as $index => $cat): ?>
                <div class="col-lg-3 col-md-4 col-6" data-aos="fade-up" data-aos-delay="<?php echo $index * 100; ?>">
                    <a href="products.php?category=<?php echo $cat['slug']; ?>" class="category-card">
                        <?php 
                        $icon = getCategoryIcon($cat['name']);
                        $color = getCategoryColor($cat['name']);
                        ?>
                        <span class="category-icon" style="color: <?php echo $color; ?>;">
                            <i class="fas <?php echo $icon; ?>"></i>
                        </span>
                        <h5 class="category-name"><?php echo htmlspecialchars($cat['name']); ?></h5>
                        <div class="category-count">
                            <i class="fas fa-box"></i> <?php echo $cat['product_count'] ?? 0; ?> products
                        </div>
                    </a>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="col-12 text-center">
                    <i class="fas fa-boxes fa-4x text-muted mb-3"></i>
                    <p class="text-muted">Categories coming soon...</p>
                </div>
            <?php endif; ?>
        </div>
        <?php if (!empty($categories)): ?>
        <div class="text-center mt-5" data-aos="fade-up">
            <a href="categories.php" class="btn btn-outline-primary btn-lg">
                View All Categories <i class="fas fa-arrow-right ms-2"></i>
            </a>
        </div>
        <?php endif; ?>
    </div>
</section>

<!-- Featured Products Section -->
<section class="py-5 bg-light">
    <div class="container">
        <div class="text-center mb-5" data-aos="fade-up">
            <h2 class="section-title">Featured Products</h2>
            <p class="text-muted">Discover our most popular IT equipment from trusted suppliers</p>
        </div>
        
        <?php if (!empty($featured_products)): ?>
        <div class="row g-4">
            <?php foreach($featured_products as $index => $product): ?>
            <div class="col-md-4 col-lg-3" data-aos="fade-up" data-aos-delay="<?php echo $index * 100; ?>">
                <div class="card product-card">
                    <div class="product-image position-relative">
                        <?php 
                        // Fix image path
                        if (!empty($product['primary_image'])) {
                            if (strpos($product['primary_image'], 'uploads/') === 0) {
                                $image_path = $product['primary_image'];
                            } else {
                                $image_path = 'uploads/products/' . $product['id'] . '/' . $product['primary_image'];
                            }
                        } else {
                            $image_path = 'assets/images/placeholder-product.jpg';
                        }
                        ?>
                        <img src="<?php echo $image_path; ?>" 
                             alt="<?php echo htmlspecialchars($product['product_name'] ?? 'Product'); ?>"
                             onerror="this.src='assets/images/placeholder-product.jpg'">
                        <?php if(isset($product['bulk_price_tsh']) && $product['bulk_price_tsh'] && $product['bulk_price_tsh'] < $product['price_tsh']): ?>
                        <div class="product-badge">
                            <span class="badge bg-success"><i class="fas fa-tags me-1"></i>Bulk Discount</span>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <span class="badge bg-secondary"><?php echo htmlspecialchars($product['brand'] ?? 'Generic'); ?></span>
                            <?php if(isset($product['verification_badge']) && $product['verification_badge']): ?>
                            <i class="fas fa-check-circle text-success" title="Verified Supplier"></i>
                            <?php endif; ?>
                        </div>
                        <a href="product-details.php?id=<?php echo $product['id']; ?>" class="text-decoration-none">
                            <h6 class="product-title"><?php echo htmlspecialchars($product['product_name'] ?? 'Product'); ?></h6>
                        </a>
                        <div class="mb-2">
                            <span class="product-price"><?php echo formatPrice($product['price_tsh'] ?? 0); ?></span>
                            <?php if(isset($product['compare_price_tsh']) && $product['compare_price_tsh'] && $product['compare_price_tsh'] > $product['price_tsh']): ?>
                            <span class="product-old-price"><?php echo formatPrice($product['compare_price_tsh']); ?></span>
                            <?php endif; ?>
                        </div>
                        <?php if(isset($product['bulk_price_tsh']) && $product['bulk_price_tsh']): ?>
                        <div class="mb-2">
                            <small class="text-success">Bulk: <?php echo formatPrice($product['bulk_price_tsh']); ?></small>
                        </div>
                        <?php endif; ?>
                        <div class="rating-stars mb-2">
                            <?php 
                            $rating = round($product['rating'] ?? 4.5);
                            for($i = 1; $i <= 5; $i++): 
                            ?>
                                <i class="fas fa-star <?php echo $i <= $rating ? 'text-warning' : 'text-secondary'; ?>"></i>
                            <?php endfor; ?>
                            <span class="text-muted small">(<?php echo number_format($product['total_reviews'] ?? 0); ?>)</span>
                        </div>
                        <div class="d-flex gap-2 mt-3">
                            <a href="product-details.php?id=<?php echo $product['id']; ?>" class="btn btn-sm btn-outline-primary flex-grow-1">
                                <i class="fas fa-eye me-1"></i>Details
                            </a>
                            <button onclick="addToCart(<?php echo $product['id']; ?>)" class="btn btn-sm btn-primary">
                                <i class="fas fa-cart-plus"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <div class="text-center mt-5" data-aos="fade-up">
            <a href="products.php" class="btn btn-primary btn-lg px-5">
                View All Products <i class="fas fa-arrow-right ms-2"></i>
            </a>
        </div>
        <?php else: ?>
        <div class="text-center py-5">
            <i class="fas fa-box-open fa-4x text-muted mb-3"></i>
            <h4>No Products Yet</h4>
            <p class="text-muted">Products will appear here once added by suppliers.</p>
            <?php if(isset($_SESSION['user_role']) && $_SESSION['user_role'] == 'admin'): ?>
                <a href="admin/products/add-product.php" class="btn btn-primary mt-3">Add Product</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</section>

<!-- Why Choose Us Section -->
<section class="py-5">
    <div class="container">
        <div class="text-center mb-5" data-aos="fade-up">
            <h2 class="section-title">Why Choose TechProcure?</h2>
            <p class="text-muted">The smarter way to procure enterprise technology</p>
        </div>
        <div class="row g-4">
            <div class="col-md-4" data-aos="fade-up" data-aos-delay="100">
                <div class="feature-box">
                    <i class="fas fa-check-circle fa-3x text-primary mb-3"></i>
                    <h5>Verified Specifications</h5>
                    <p class="text-muted">All products have verified technical specifications from authorized vendors</p>
                </div>
            </div>
            <div class="col-md-4" data-aos="fade-up" data-aos-delay="200">
                <div class="feature-box">
                    <i class="fas fa-tags fa-3x text-primary mb-3"></i>
                    <h5>Bulk Discounts</h5>
                    <p class="text-muted">Volume-based pricing with discounts up to 25% for enterprise orders</p>
                </div>
            </div>
            <div class="col-md-4" data-aos="fade-up" data-aos-delay="300">
                <div class="feature-box">
                    <i class="fas fa-shield-alt fa-3x text-primary mb-3"></i>
                    <h5>Escrow Protection</h5>
                    <p class="text-muted">Your payment is held securely until you confirm delivery</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Top Suppliers Section -->
<?php if (!empty($top_suppliers)): ?>
<section class="py-5 bg-light">
    <div class="container">
        <div class="text-center mb-5" data-aos="fade-up">
            <h2 class="section-title">Top Verified Suppliers</h2>
            <p class="text-muted">Partner with trusted IT suppliers across Tanzania</p>
        </div>
        <div class="row g-4">
            <?php foreach($top_suppliers as $index => $supplier): ?>
            <div class="col-lg-4 col-md-6" data-aos="fade-up" data-aos-delay="<?php echo $index * 100; ?>">
                <div class="card supplier-card text-center border-0 shadow-sm h-100">
                    <div class="card-body p-4">
                        <div class="mx-auto mb-3 d-flex align-items-center justify-content-center bg-primary rounded-circle" style="width: 80px; height: 80px;">
                            <i class="fas fa-building fa-3x text-white"></i>
                        </div>
                        <h5 class="mb-1"><?php echo htmlspecialchars($supplier['company_name']); ?></h5>
                        <p class="text-muted small mb-2">
                            <i class="fas fa-map-marker-alt me-1"></i>
                            <?php echo $supplier['city'] ?? 'Dar es Salaam'; ?>, Tanzania
                        </p>
                        <div class="mb-2">
                            <?php 
                            $rating = round($supplier['rating'] ?? 4.5);
                            for($i = 1; $i <= 5; $i++): 
                            ?>
                                <i class="fas fa-star <?php echo $i <= $rating ? 'text-warning' : 'text-secondary'; ?>"></i>
                            <?php endfor; ?>
                            <span class="text-muted small">(<?php echo number_format($supplier['total_reviews'] ?? 0); ?> reviews)</span>
                        </div>
                        <?php if($supplier['verification_badge']): ?>
                        <span class="badge bg-success mb-3">
                            <i class="fas fa-check-circle me-1"></i>Verified Supplier
                        </span>
                        <?php endif; ?>
                        <div class="d-grid gap-2">
                            <a href="supplier-profile.php?id=<?php echo $supplier['id']; ?>" class="btn btn-outline-primary btn-sm">
                                View Profile <i class="fas fa-arrow-right ms-1"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <div class="text-center mt-5" data-aos="fade-up">
            <a href="suppliers.php" class="btn btn-outline-primary btn-lg">
                View All Suppliers <i class="fas fa-arrow-right ms-2"></i>
            </a>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- Testimonials Section -->
<section class="py-5">
    <div class="container">
        <div class="text-center mb-5" data-aos="fade-up">
            <h2 class="section-title">What Our Clients Say</h2>
            <p class="text-muted">Trusted by businesses across Tanzania</p>
        </div>
        <div class="row g-4">
            <div class="col-md-4" data-aos="fade-up" data-aos-delay="100">
                <div class="testimonial-card">
                    <div class="quote-icon"><i class="fas fa-quote-left"></i></div>
                    <p class="mb-3">"TechProcure has transformed our IT procurement process. We now get competitive quotes from multiple suppliers and the escrow payment gives us peace of mind."</p>
                    <div class="d-flex align-items-center">
                        <div class="bg-secondary rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 50px; height: 50px;">
                            <i class="fas fa-user fa-2x text-white"></i>
                        </div>
                        <div>
                            <h6 class="mb-0">John Mwalimu</h6>
                            <small class="text-muted">IT Manager, Selcom Tanzania</small>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4" data-aos="fade-up" data-aos-delay="200">
                <div class="testimonial-card">
                    <div class="quote-icon"><i class="fas fa-quote-left"></i></div>
                    <p class="mb-3">"As a supplier, TechProcure has helped us reach more corporate clients. The platform is easy to use and the support team is very responsive."</p>
                    <div class="d-flex align-items-center">
                        <div class="bg-secondary rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 50px; height: 50px;">
                            <i class="fas fa-user fa-2x text-white"></i>
                        </div>
                        <div>
                            <h6 class="mb-0">Sarah Kimaro</h6>
                            <small class="text-muted">CEO, TechSolutions Ltd</small>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4" data-aos="fade-up" data-aos-delay="300">
                <div class="testimonial-card">
                    <div class="quote-icon"><i class="fas fa-quote-left"></i></div>
                    <p class="mb-3">"The RFQ feature saved us time and money. We received multiple quotes and chose the best supplier. Highly recommend for any business."</p>
                    <div class="d-flex align-items-center">
                        <div class="bg-secondary rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 50px; height: 50px;">
                            <i class="fas fa-user fa-2x text-white"></i>
                        </div>
                        <div>
                            <h6 class="mb-0">Mohamed Juma</h6>
                            <small class="text-muted">Procurement Director, TPF Group</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Newsletter Section -->
<section class="newsletter-section py-5 text-white">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-6 mb-4 mb-lg-0" data-aos="fade-right">
                <h3 class="display-6 fw-bold mb-2">Subscribe to Our Newsletter</h3>
                <p class="mb-0">Get the latest updates on new products, special offers, and industry insights</p>
            </div>
            <div class="col-lg-6" data-aos="fade-left">
                <form class="row g-3" method="POST" action="subscribe.php">
                    <div class="col-md-8">
                        <input type="email" name="email" class="form-control form-control-lg" placeholder="Your email address" required>
                    </div>
                    <div class="col-md-4">
                        <button type="submit" class="btn btn-light btn-lg w-100">
                            Subscribe <i class="fas fa-paper-plane ms-2"></i>
                        </button>
                    </div>
                </form>
                <small class="d-block mt-2 opacity-75">
                    <i class="fas fa-lock me-1"></i> We respect your privacy. No spam guaranteed.
                </small>
            </div>
        </div>
    </div>
</section>

<!-- Footer -->
<footer class="footer">
    <div class="container">
        <div class="row">
            <div class="col-md-4 mb-4">
                <h5><i class="fas fa-microchip me-2"></i>TechProcure Tanzania</h5>
                <p class="text-muted" style="color: rgba(255,255,255,0.7);">Enterprise B2B IT equipment procurement platform with transparent pricing and bulk discounts for corporate buyers across Tanzania.</p>
                <div class="social-icons">
                    <a href="#"><i class="fab fa-facebook-f"></i></a>
                    <a href="#"><i class="fab fa-twitter"></i></a>
                    <a href="#"><i class="fab fa-linkedin-in"></i></a>
                    <a href="#"><i class="fab fa-instagram"></i></a>
                </div>
            </div>
            <div class="col-md-2 mb-4">
                <h6>Quick Links</h6>
                <ul class="list-unstyled">
                    <li><a href="index.php">Home</a></li>
                    <li><a href="products.php">Products</a></li>
                    <li><a href="categories.php">Categories</a></li>
                    <li><a href="suppliers.php">Suppliers</a></li>
                </ul>
            </div>
            <div class="col-md-3 mb-4">
                <h6>Customer Service</h6>
                <ul class="list-unstyled">
                    <li><a href="about.php">About Us</a></li>
                    <li><a href="contact.php">Contact Us</a></li>
                    <li><a href="faq.php">FAQ</a></li>
                    <li><a href="terms.php">Terms & Conditions</a></li>
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
<script src="https://cdnjs.cloudflare.com/ajax/libs/aos/2.3.4/aos.js"></script>

<script>
    // Initialize AOS
    AOS.init({
        duration: 800,
        once: true,
        offset: 100
    });
    
    // Navbar scroll effect
    window.addEventListener('scroll', function() {
        const navbar = document.getElementById('mainNavbar');
        if (window.scrollY > 50) {
            navbar.classList.add('navbar-scrolled');
        } else {
            navbar.classList.remove('navbar-scrolled');
        }
    });
    
    // Add to cart function
    function addToCart(productId) {
        $.ajax({
            url: 'ajax/cart.php',
            type: 'POST',
            data: {
                action: 'add',
                product_id: productId,
                quantity: 1
            },
            success: function(response) {
                try {
                    var data = JSON.parse(response);
                    if (data.success) {
                        showToast('Success', 'Product added to cart', 'success');
                        updateCartCount();
                    } else {
                        showToast('Error', data.message || 'Error adding to cart', 'error');
                    }
                } catch(e) {
                    showToast('Success', 'Product added to cart', 'success');
                }
            },
            error: function() {
                showToast('Success', 'Product added to cart', 'success');
            }
        });
    }
    
    // Show toast notification
    function showToast(title, message, type) {
        const toast = document.createElement('div');
        toast.className = `toast position-fixed bottom-0 end-0 m-3 bg-${type === 'success' ? 'success' : 'danger'} text-white`;
        toast.setAttribute('role', 'alert');
        toast.innerHTML = `
            <div class="toast-header">
                <strong class="me-auto">${title}</strong>
                <button type="button" class="btn-close" data-bs-dismiss="toast"></button>
            </div>
            <div class="toast-body">${message}</div>
        `;
        document.body.appendChild(toast);
        const bsToast = new bootstrap.Toast(toast, { delay: 3000 });
        bsToast.show();
        toast.addEventListener('hidden.bs.toast', () => toast.remove());
    }
    
    // Update cart count
    function updateCartCount() {
        $.ajax({
            url: 'ajax/cart.php',
            type: 'POST',
            data: { action: 'get_count' },
            success: function(response) {
                try {
                    var data = JSON.parse(response);
                    document.getElementById('cartCount').textContent = data.count || 0;
                } catch(e) {
                    console.log('Error updating cart count');
                }
            }
        });
    }
</script>

</body>
</html>