<?php
/**
 * TechProcure Tanzania - Wishlist Page
 * File: customer/wishlist.php
 * Description: Customer wishlist management with add/remove functionality
 */

// Start session if not started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include configuration files
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';


// Check if user is logged in and is customer
requireCustomer();

// Get database connection
$db = getDB();
$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'] ?? 'Customer';

// =============================================
// HANDLE WISHLIST ACTIONS
// =============================================

// Remove from wishlist
if (isset($_GET['remove']) && is_numeric($_GET['remove'])) {
    $wishlist_id = (int)$_GET['remove'];
    try {
        $stmt = $db->prepare("DELETE FROM wishlist WHERE id = ? AND user_id = ?");
        $stmt->execute([$wishlist_id, $user_id]);
        $_SESSION['success'] = 'Item removed from wishlist!';
    } catch (PDOException $e) {
        $_SESSION['error'] = 'Failed to remove item.';
    }
    header("Location: wishlist.php");
    exit();
}

// Clear wishlist
if (isset($_GET['clear'])) {
    try {
        $stmt = $db->prepare("DELETE FROM wishlist WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $_SESSION['success'] = 'Wishlist cleared successfully!';
    } catch (PDOException $e) {
        $_SESSION['error'] = 'Failed to clear wishlist.';
    }
    header("Location: wishlist.php");
    exit();
}

// Add to cart from wishlist
if (isset($_GET['add_to_cart']) && is_numeric($_GET['add_to_cart'])) {
    $product_id = (int)$_GET['add_to_cart'];
    $quantity = isset($_GET['quantity']) ? (int)$_GET['quantity'] : 1;
    
    // Get product details
    try {
        $stmt = $db->prepare("SELECT * FROM products WHERE id = ? AND status = 'active'");
        $stmt->execute([$product_id]);
        $product = $stmt->fetch();
        
        if ($product) {
            if (function_exists('addToCart')) {
                addToCart($product_id, $quantity);
                $_SESSION['success'] = 'Product added to cart!';
            } else {
                // Manual add to cart
                if (!isset($_SESSION['cart'])) {
                    $_SESSION['cart'] = [];
                }
                if (isset($_SESSION['cart'][$product_id])) {
                    $_SESSION['cart'][$product_id]['quantity'] += $quantity;
                } else {
                    $_SESSION['cart'][$product_id] = [
                        'id' => $product_id,
                        'quantity' => $quantity,
                        'price' => $product['price_tsh']
                    ];
                }
                $_SESSION['success'] = 'Product added to cart!';
            }
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = 'Failed to add to cart.';
    }
    header("Location: wishlist.php");
    exit();
}

// =============================================
// GET WISHLIST ITEMS
// =============================================

$wishlist_items = [];
$total_items = 0;

try {
    $sql = "SELECT w.*, p.id as product_id, p.product_name, p.slug, p.price_tsh, p.compare_price_tsh, 
                   p.bulk_price_tsh, p.stock_quantity, p.brand, p.rating, p.total_reviews,
                   (SELECT image_path FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) as primary_image
            FROM wishlist w
            JOIN products p ON w.product_id = p.id
            WHERE w.user_id = ? AND p.status = 'active'
            ORDER BY w.created_at DESC";
    $stmt = $db->prepare($sql);
    $stmt->execute([$user_id]);
    
    if ($stmt->rowCount() > 0) {
        $wishlist_items = $stmt->fetchAll();
        $total_items = count($wishlist_items);
    }
} catch (PDOException $e) {
    $wishlist_items = [];
    $total_items = 0;
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

if (!function_exists('getStarRating')) {
    function getStarRating($rating) {
        $html = '';
        $fullStars = floor($rating);
        $halfStar = $rating - $fullStars >= 0.5;
        
        for ($i = 1; $i <= 5; $i++) {
            if ($i <= $fullStars) {
                $html .= '<i class="fas fa-star text-warning"></i>';
            } elseif ($i == $fullStars + 1 && $halfStar) {
                $html .= '<i class="fas fa-star-half-alt text-warning"></i>';
            } else {
                $html .= '<i class="far fa-star text-secondary"></i>';
            }
        }
        return $html;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Wishlist - TechProcure Tanzania</title>
    
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
        
        /* Wishlist Content */
        .wishlist-content {
            flex: 1;
        }
        
        /* Wishlist Stats */
        .wishlist-stats {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }
        
        .wishlist-stats .stat-item {
            background: white;
            padding: 15px 25px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            flex: 1;
            min-width: 150px;
            text-align: center;
        }
        
        .wishlist-stats .stat-item .stat-number {
            font-size: 1.8rem;
            font-weight: 700;
            color: #dc3545;
        }
        
        .wishlist-stats .stat-item .stat-label {
            font-size: 0.8rem;
            color: #6c757d;
        }
        
        /* Wishlist Item */
        .wishlist-item {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            transition: all 0.3s;
            display: flex;
            gap: 20px;
            align-items: center;
        }
        
        .wishlist-item:hover {
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            transform: translateY(-2px);
        }
        
        .wishlist-item .item-image {
            width: 120px;
            height: 120px;
            flex-shrink: 0;
            border-radius: 10px;
            overflow: hidden;
            background: #f8f9fa;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .wishlist-item .item-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .wishlist-item .item-image .no-image {
            font-size: 2.5rem;
            color: #dee2e6;
        }
        
        .wishlist-item .item-details {
            flex: 1;
        }
        
        .wishlist-item .item-details .item-name {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .wishlist-item .item-details .item-name a {
            color: #333;
            text-decoration: none;
        }
        
        .wishlist-item .item-details .item-name a:hover {
            color: #0d6efd;
        }
        
        .wishlist-item .item-details .item-brand {
            font-size: 0.85rem;
            color: #6c757d;
        }
        
        .wishlist-item .item-details .item-rating {
            margin: 5px 0;
        }
        
        .wishlist-item .item-details .item-price {
            font-size: 1.2rem;
            font-weight: 700;
            color: #0d6efd;
        }
        
        .wishlist-item .item-details .item-old-price {
            font-size: 0.9rem;
            text-decoration: line-through;
            color: #999;
            margin-left: 8px;
        }
        
        .wishlist-item .item-details .item-stock {
            font-size: 0.85rem;
        }
        
        .wishlist-item .item-details .item-stock.in-stock {
            color: #198754;
        }
        
        .wishlist-item .item-details .item-stock.out-of-stock {
            color: #dc3545;
        }
        
        .wishlist-item .item-actions {
            display: flex;
            flex-direction: column;
            gap: 8px;
            min-width: 120px;
        }
        
        .wishlist-item .item-actions .btn {
            border-radius: 8px;
            padding: 6px 12px;
            font-size: 0.85rem;
        }
        
        .wishlist-item .item-added {
            font-size: 0.75rem;
            color: #6c757d;
            margin-top: 5px;
        }
        
        /* Empty Wishlist */
        .empty-wishlist {
            text-align: center;
            padding: 80px 20px;
            background: white;
            border-radius: 15px;
        }
        
        .empty-wishlist i {
            font-size: 4rem;
            color: #dee2e6;
            margin-bottom: 20px;
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
            .wishlist-item {
                flex-direction: column;
                text-align: center;
            }
            .wishlist-item .item-image {
                width: 100px;
                height: 100px;
                margin: 0 auto;
            }
            .wishlist-item .item-actions {
                flex-direction: row;
                flex-wrap: wrap;
                justify-content: center;
            }
            .wishlist-stats {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>

<!-- Navbar -->
<nav class="navbar navbar-expand-lg">
    <div class="container">
        <a class="navbar-brand" href="../index.php">
            <i class="fas fa-microchip"></i>
            TechProcure <span class="text-warning">Tanzania</span>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav mx-auto">
                <li class="nav-item">
                    <a class="nav-link" href="../index.php"><i class="fas fa-home me-1"></i>Home</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="../products.php"><i class="fas fa-box me-1"></i>Products</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="../suppliers.php"><i class="fas fa-truck me-1"></i>Suppliers</a>
                </li>
            </ul>
            <div class="d-flex align-items-center gap-2">
                <a href="../cart.php" class="nav-link position-relative">
                    <i class="fas fa-shopping-cart fa-lg"></i>
                    <span class="cart-count"><?php echo $cart_count; ?></span>
                </a>
                <a href="../auth/logout.php" class="btn btn-outline-light btn-sm">
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
                    <a class="nav-link" href="dashboard.php">
                        <i class="fas fa-tachometer-alt"></i> Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="profile.php">
                        <i class="fas fa-user"></i> My Profile
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="orders/my-orders.php">
                        <i class="fas fa-shopping-bag"></i> My Orders
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="wishlist.php">
                        <i class="fas fa-heart"></i> Wishlist
                        <?php if($total_items > 0): ?>
                            <span class="badge bg-danger"><?php echo $total_items; ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="quotations/request-quotation.php">
                        <i class="fas fa-file-alt"></i> Quotations
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="invoices/my-invoices.php">
                        <i class="fas fa-file-invoice"></i> Invoices
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="settings.php">
                        <i class="fas fa-cog"></i> Settings
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link text-danger" href="../auth/logout.php">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </li>
            </ul>
        </div>
        
        <!-- Wishlist Content -->
        <div class="wishlist-content">
            <!-- Page Title -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h4 class="mb-0"><i class="fas fa-heart me-2 text-danger"></i>My Wishlist</h4>
                    <p class="text-muted">Your saved items for future purchase</p>
                </div>
                <?php if($total_items > 0): ?>
                    <a href="?clear=1" class="btn btn-outline-danger btn-sm" onclick="return confirm('Are you sure you want to clear your wishlist?')">
                        <i class="fas fa-trash me-1"></i> Clear All
                    </a>
                <?php endif; ?>
            </div>
            
            <!-- Success/Error Messages -->
            <?php if(isset($_SESSION['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <i class="fas fa-check-circle me-2"></i> <?php echo $_SESSION['success']; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php unset($_SESSION['success']); ?>
            <?php endif; ?>
            
            <?php if(isset($_SESSION['error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <i class="fas fa-exclamation-triangle me-2"></i> <?php echo $_SESSION['error']; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>
            
            <!-- Wishlist Stats -->
            <div class="wishlist-stats">
                <div class="stat-item">
                    <div class="stat-number"><?php echo $total_items; ?></div>
                    <div class="stat-label">Items in Wishlist</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number">
                        <?php 
                        $total_value = 0;
                        foreach($wishlist_items as $item) {
                            $total_value += $item['price_tsh'] ?? 0;
                        }
                        echo formatPrice($total_value);
                        ?>
                    </div>
                    <div class="stat-label">Total Value</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number">
                        <?php echo number_format(count(array_filter($wishlist_items, function($item) {
                            return ($item['stock_quantity'] ?? 0) > 0;
                        }))); ?>
                    </div>
                    <div class="stat-label">In Stock</div>
                </div>
            </div>
            
            <!-- Wishlist Items -->
            <?php if (!empty($wishlist_items)): ?>
                <?php foreach($wishlist_items as $item): ?>
                <div class="wishlist-item">
                    <!-- Product Image -->
                    <div class="item-image">
                        <?php if (!empty($item['primary_image']) && file_exists('../uploads/products/' . $item['product_id'] . '/' . $item['primary_image'])): ?>
                            <img src="../uploads/products/<?php echo $item['product_id'] . '/' . $item['primary_image']; ?>" alt="<?php echo htmlspecialchars($item['product_name']); ?>">
                        <?php else: ?>
                            <div class="no-image">
                                <i class="fas fa-image"></i>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Product Details -->
                    <div class="item-details">
                        <div class="item-name">
                            <a href="../product-details.php?id=<?php echo $item['product_id']; ?>">
                                <?php echo htmlspecialchars($item['product_name']); ?>
                            </a>
                        </div>
                        <?php if($item['brand']): ?>
                            <div class="item-brand">
                                <i class="fas fa-tag me-1"></i> <?php echo htmlspecialchars($item['brand']); ?>
                            </div>
                        <?php endif; ?>
                        
                        <div class="item-rating">
                            <?php echo getStarRating($item['rating'] ?? 0); ?>
                            <span class="text-muted small">(<?php echo number_format($item['total_reviews'] ?? 0); ?> reviews)</span>
                        </div>
                        
                        <div class="item-price">
                            <?php echo formatPrice($item['price_tsh']); ?>
                            <?php if($item['compare_price_tsh'] && $item['compare_price_tsh'] > $item['price_tsh']): ?>
                                <span class="item-old-price"><?php echo formatPrice($item['compare_price_tsh']); ?></span>
                            <?php endif; ?>
                        </div>
                        
                        <?php if($item['bulk_price_tsh']): ?>
                            <div class="text-success small">
                                <i class="fas fa-tags me-1"></i> Bulk: <?php echo formatPrice($item['bulk_price_tsh']); ?>
                            </div>
                        <?php endif; ?>
                        
                        <div class="item-stock <?php echo ($item['stock_quantity'] ?? 0) > 0 ? 'in-stock' : 'out-of-stock'; ?>">
                            <i class="fas <?php echo ($item['stock_quantity'] ?? 0) > 0 ? 'fa-check-circle' : 'fa-times-circle'; ?> me-1"></i>
                            <?php if(($item['stock_quantity'] ?? 0) > 0): ?>
                                In Stock (<?php echo $item['stock_quantity']; ?> available)
                            <?php else: ?>
                                Out of Stock
                            <?php endif; ?>
                        </div>
                        
                        <div class="item-added">
                            <i class="fas fa-clock me-1"></i> Added on <?php echo formatDate($item['created_at']); ?>
                        </div>
                    </div>
                    
                    <!-- Actions -->
                    <div class="item-actions">
                        <?php if(($item['stock_quantity'] ?? 0) > 0): ?>
                            <a href="?add_to_cart=<?php echo $item['product_id']; ?>" class="btn btn-primary">
                                <i class="fas fa-cart-plus me-1"></i> Add to Cart
                            </a>
                        <?php else: ?>
                            <button class="btn btn-secondary" disabled>
                                <i class="fas fa-times me-1"></i> Out of Stock
                            </button>
                        <?php endif; ?>
                        <a href="../product-details.php?id=<?php echo $item['product_id']; ?>" class="btn btn-outline-primary">
                            <i class="fas fa-eye me-1"></i> View Product
                        </a>
                        <a href="?remove=<?php echo $item['id']; ?>" class="btn btn-outline-danger" onclick="return confirm('Remove this item from wishlist?')">
                            <i class="fas fa-trash me-1"></i> Remove
                        </a>
                    </div>
                </div>
                <?php endforeach; ?>
                
                <!-- Recommendation -->
                <div class="alert alert-info mt-4">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-lightbulb fa-2x me-3"></i>
                        <div>
                            <h6 class="mb-0">Shopping Tip</h6>
                            <p class="mb-0 small">Add items to cart for bulk discounts. Contact suppliers for custom quotes on large orders.</p>
                        </div>
                        <a href="../products.php" class="btn btn-primary ms-auto">Browse More Products</a>
                    </div>
                </div>
                
            <?php else: ?>
            <!-- Empty Wishlist -->
            <div class="empty-wishlist">
                <i class="fas fa-heart"></i>
                <h4>Your wishlist is empty</h4>
                <p class="text-muted">Start adding your favorite products to your wishlist!</p>
                <a href="../products.php" class="btn btn-primary btn-lg">
                    <i class="fas fa-shopping-cart me-2"></i> Browse Products
                </a>
                <div class="mt-3">
                    <small class="text-muted">Save items you love and purchase them later.</small>
                </div>
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
                    <li><a href="../index.php">Home</a></li>
                    <li><a href="../products.php">Products</a></li>
                    <li><a href="../suppliers.php">Suppliers</a></li>
                </ul>
            </div>
            <div class="col-md-3 mb-4">
                <h6>My Account</h6>
                <ul class="list-unstyled">
                    <li><a href="dashboard.php">Dashboard</a></li>
                    <li><a href="profile.php">Profile</a></li>
                    <li><a href="orders/my-orders.php">My Orders</a></li>
                    <li><a href="wishlist.php">Wishlist</a></li>
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
    // Auto-hide alerts after 5 seconds
    setTimeout(function() {
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(function(alert) {
            const bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        });
    }, 5000);
    
    // Update cart count after adding to cart
    function updateCartCount() {
        $.ajax({
            url: '../ajax/cart.php',
            type: 'POST',
            data: { action: 'get_count' },
            success: function(response) {
                try {
                    var data = JSON.parse(response);
                    $('.cart-count').text(data.count || 0);
                } catch(e) {
                    console.log('Error updating cart count');
                }
            }
        });
    }
</script>

</body>
</html>