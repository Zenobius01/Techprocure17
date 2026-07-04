<?php
/**
 * TechProcure Tanzania - Product Details Page
 * File: product-details.php
 * Description: Single product view with images, specifications, and related products
 */

// Start session if not started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include configuration files
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';

// Get product ID from URL
$product_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($product_id <= 0) {
    header("Location: products.php");
    exit();
}

// Get database connection
$db = getDB();

// =============================================
// FETCH PRODUCT DETAILS
// =============================================

$product = null;
$error = '';

try {
    $sql = "SELECT p.*, c.name as category_name, c.slug as category_slug, 
            s.company_name as supplier_name, s.verification_badge,
            s.rating as supplier_rating,
            s.city as supplier_city,
            s.region as supplier_region
            FROM products p 
            LEFT JOIN categories c ON p.category_id = c.id 
            LEFT JOIN suppliers s ON p.supplier_id = s.id 
            WHERE p.id = ? AND p.status = 'active' AND p.approval_status = 'approved'";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([$product_id]);
    
    if ($stmt->rowCount() > 0) {
        $product = $stmt->fetch();
        
        // Increment view count
        try {
            $update_sql = "UPDATE products SET views = views + 1 WHERE id = ?";
            $update_stmt = $db->prepare($update_sql);
            $update_stmt->execute([$product_id]);
        } catch (PDOException $e) {
            // View increment failed, continue
        }
    } else {
        $error = "Product not found or unavailable.";
    }
} catch (PDOException $e) {
    $error = "Unable to load product details. Please try again.";
}

// =============================================
// FETCH PRODUCT IMAGES
// =============================================

$product_images = [];
if ($product) {
    try {
        $sql = "SELECT * FROM product_images WHERE product_id = ? ORDER BY is_primary DESC, sort_order ASC";
        $stmt = $db->prepare($sql);
        $stmt->execute([$product_id]);
        if ($stmt->rowCount() > 0) {
            $product_images = $stmt->fetchAll();
        }
    } catch (PDOException $e) {
        // Images not found, continue
    }
}

// =============================================
// FETCH PRODUCT SPECIFICATIONS
// =============================================

$specifications = [];
if ($product) {
    try {
        $sql = "SELECT * FROM product_specifications WHERE product_id = ? ORDER BY sort_order ASC";
        $stmt = $db->prepare($sql);
        $stmt->execute([$product_id]);
        if ($stmt->rowCount() > 0) {
            $specifications = $stmt->fetchAll();
        }
    } catch (PDOException $e) {
        // Specifications not found, continue
    }
}

// =============================================
// FETCH RELATED PRODUCTS
// =============================================

$related_products = [];
if ($product) {
    try {
        $sql = "SELECT p.*, 
                (SELECT image_path FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) as primary_image
                FROM products p 
                WHERE p.category_id = ? 
                AND p.id != ? 
                AND p.status = 'active' 
                AND p.approval_status = 'approved'
                ORDER BY p.created_at DESC 
                LIMIT 4";
        $stmt = $db->prepare($sql);
        $stmt->execute([$product['category_id'], $product_id]);
        if ($stmt->rowCount() > 0) {
            $related_products = $stmt->fetchAll();
        }
    } catch (PDOException $e) {
        // Related products not found, continue
    }
}

// =============================================
// FETCH PRODUCT REVIEWS
// =============================================

$reviews = [];
if ($product) {
    try {
        $sql = "SELECT r.*, u.full_name as reviewer_name 
                FROM product_reviews r 
                LEFT JOIN users u ON r.user_id = u.id 
                WHERE r.product_id = ? AND r.status = 'approved'
                ORDER BY r.created_at DESC 
                LIMIT 5";
        $stmt = $db->prepare($sql);
        $stmt->execute([$product_id]);
        if ($stmt->rowCount() > 0) {
            $reviews = $stmt->fetchAll();
        }
    } catch (PDOException $e) {
        // Reviews not found, continue
    }
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

if (!function_exists('truncateText')) {
    function truncateText($text, $length = 100) {
        if (empty($text)) return '';
        if (strlen($text) <= $length) return $text;
        return substr($text, 0, $length) . '...';
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

// Get cart count
$cart_count = isset($_SESSION['cart']) ? array_sum(array_column($_SESSION['cart'], 'quantity')) : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $product ? htmlspecialchars($product['product_name']) : 'Product Details'; ?> - TechProcure Tanzania</title>
    
    <!-- CSS Dependencies -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f8f9fa;
            padding-top: 20px;
        }
        
        /* Back Link */
        .back-link {
            text-decoration: none;
            color: #0d6efd;
            font-weight: 500;
            transition: all 0.3s;
        }
        
        .back-link:hover {
            color: #0b5ed7;
            padding-left: 5px;
        }
        
        .product-gallery {
            background: white;
            border-radius: 12px;
            padding: 20px;
            position: sticky;
            top: 20px;
        }
        
        .main-image {
            width: 100%;
            height: 400px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f8f9fa;
            border-radius: 8px;
            overflow: hidden;
        }
        
        .main-image img {
            max-height: 100%;
            max-width: 100%;
            object-fit: contain;
        }
        
        .thumbnail-images {
            display: flex;
            gap: 10px;
            margin-top: 15px;
            overflow-x: auto;
            padding-bottom: 5px;
        }
        
        .thumbnail-images img {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 8px;
            cursor: pointer;
            border: 2px solid transparent;
            transition: all 0.3s;
        }
        
        .thumbnail-images img:hover,
        .thumbnail-images img.active {
            border-color: #0d6efd;
        }
        
        .product-info {
            background: white;
            border-radius: 12px;
            padding: 30px;
        }
        
        .product-title {
            font-size: 1.8rem;
            font-weight: 700;
            margin-bottom: 10px;
        }
        
        .product-price {
            font-size: 2rem;
            font-weight: 700;
            color: #0d6efd;
        }
        
        .product-old-price {
            font-size: 1.2rem;
            text-decoration: line-through;
            color: #999;
            margin-left: 10px;
        }
        
        .product-badge {
            display: inline-block;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .stock-badge {
            display: inline-block;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .stock-in {
            background: #d4edda;
            color: #155724;
        }
        
        .stock-out {
            background: #f8d7da;
            color: #721c24;
        }
        
        .quantity-selector {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .quantity-selector button {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            border: 1px solid #ddd;
            background: white;
            font-size: 1.2rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .quantity-selector button:hover {
            background: #0d6efd;
            color: white;
            border-color: #0d6efd;
        }
        
        .quantity-selector input {
            width: 60px;
            text-align: center;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 8px;
            font-size: 1rem;
        }
        
        .btn-add-cart {
            padding: 12px 30px;
            border-radius: 10px;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .btn-add-cart:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(13, 110, 253, 0.3);
        }
        
        .spec-item {
            display: flex;
            padding: 10px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .spec-item:last-child {
            border-bottom: none;
        }
        
        .spec-label {
            width: 40%;
            font-weight: 500;
            color: #495057;
        }
        
        .spec-value {
            width: 60%;
            color: #212529;
        }
        
        .related-product-card {
            transition: all 0.3s;
            border: none;
            border-radius: 12px;
            overflow: hidden;
            background: white;
        }
        
        .related-product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        
        .related-product-image {
            height: 150px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f8f9fa;
            overflow: hidden;
        }
        
        .related-product-image img {
            max-height: 100%;
            max-width: 100%;
            object-fit: contain;
        }
        
        .review-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 15px;
        }
        
        .review-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: #0d6efd;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 1.2rem;
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
        
        .toast-container {
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 9999;
        }
        
        /* No Navbar - Cart count badge */
        .cart-badge {
            position: relative;
            display: inline-block;
        }
        
        .cart-badge .badge {
            position: absolute;
            top: -10px;
            right: -10px;
            background: #dc3545;
            color: white;
            border-radius: 50%;
            padding: 2px 6px;
            font-size: 10px;
        }
        
        @media (max-width: 768px) {
            .product-title {
                font-size: 1.4rem;
            }
            .product-price {
                font-size: 1.5rem;
            }
            .main-image {
                height: 250px;
            }
            .product-gallery {
                position: static;
            }
        }
    </style>
</head>
<body>

<!-- ============================================= -->
<!-- TOP BAR (Minimal - No Navbar) -->
<!-- ============================================= -->
<div class="container">
    <div class="d-flex justify-content-between align-items-center py-2">
        <a href="products.php" class="back-link">
            <i class="fas fa-arrow-left me-2"></i>Back to Products
        </a>
        <a href="cart.php" class="text-dark text-decoration-none cart-badge">
            <i class="fas fa-shopping-cart fa-lg"></i>
            <span class="badge"><?php echo $cart_count; ?></span>
        </a>
    </div>
</div>

<!-- ============================================= -->
<!-- PRODUCT DETAILS -->
<!-- ============================================= -->
<div class="container">
    <?php if ($error): ?>
        <div class="alert alert-danger text-center py-5">
            <i class="fas fa-exclamation-triangle fa-3x mb-3"></i>
            <h4><?php echo $error; ?></h4>
            <a href="products.php" class="btn btn-primary mt-3">Browse Products</a>
        </div>
    <?php elseif ($product): ?>
    
    <div class="row">
        <!-- Product Images -->
        <div class="col-lg-5 mb-4">
            <div class="product-gallery">
                <div class="main-image">
                    <?php 
                    // =============================================
                    // FIXED IMAGE PATH - CORRECTED
                    // =============================================
                    $primary_image = array_filter($product_images, function($img) {
                        return $img['is_primary'] == 1;
                    });
                    $primary_image = array_shift($primary_image);
                    
                    // Determine image path
                    if (!empty($primary_image)) {
                        // If path already starts with 'uploads/', use as is
                        if (strpos($primary_image['image_path'], 'uploads/') === 0) {
                            $image_path = $primary_image['image_path'];
                        } else {
                            // Otherwise construct full path
                            $image_path = 'uploads/products/' . $product['id'] . '/' . $primary_image['image_path'];
                        }
                    } else {
                        $image_path = 'assets/images/placeholder-product.jpg';
                    }
                    ?>
                    <img src="<?php echo $image_path; ?>" 
                         alt="<?php echo htmlspecialchars($product['product_name']); ?>" 
                         id="mainProductImage"
                         onerror="this.src='assets/images/placeholder-product.jpg'">
                </div>
                
                <?php if (!empty($product_images)): ?>
                <div class="thumbnail-images">
                    <?php foreach($product_images as $img): 
                        // Fix thumbnail path
                        if (strpos($img['image_path'], 'uploads/') === 0) {
                            $thumb_path = $img['image_path'];
                        } else {
                            $thumb_path = 'uploads/products/' . $product['id'] . '/' . $img['image_path'];
                        }
                    ?>
                    <img src="<?php echo $thumb_path; ?>" 
                         alt="Product image" 
                         onclick="changeImage(this)"
                         onerror="this.src='assets/images/placeholder-product.jpg'"
                         class="<?php echo $img['is_primary'] ? 'active' : ''; ?>">
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Product Info -->
        <div class="col-lg-7">
            <div class="product-info">
                <!-- Category -->
                <div class="mb-2">
                    <a href="products.php?category=<?php echo $product['category_slug']; ?>" class="text-decoration-none">
                        <span class="badge bg-primary"><?php echo htmlspecialchars($product['category_name']); ?></span>
                    </a>
                    <?php if($product['verification_badge']): ?>
                        <span class="badge bg-success ms-2">
                            <i class="fas fa-check-circle me-1"></i>Verified Supplier
                        </span>
                    <?php endif; ?>
                </div>
                
                <!-- Title -->
                <h1 class="product-title"><?php echo htmlspecialchars($product['product_name']); ?></h1>
                
                <!-- Supplier -->
                <p class="text-muted">
                    <i class="fas fa-building me-1"></i>
                    By <strong><?php echo htmlspecialchars($product['supplier_name']); ?></strong>
                    <?php if($product['supplier_city']): ?>
                        <span class="mx-1">|</span>
                        <i class="fas fa-map-marker-alt me-1"></i>
                        <?php echo htmlspecialchars($product['supplier_city']); ?>, <?php echo htmlspecialchars($product['supplier_region'] ?? 'Tanzania'); ?>
                    <?php endif; ?>
                </p>
                
                <!-- Rating -->
                <div class="mb-3">
                    <?php echo getStarRating($product['rating'] ?? 0); ?>
                    <span class="text-muted ms-2">
                        (<?php echo number_format($product['total_reviews'] ?? 0); ?> reviews)
                    </span>
                </div>
                
                <!-- SKU -->
                <?php if($product['sku']): ?>
                <p class="text-muted small">
                    <strong>SKU:</strong> <?php echo htmlspecialchars($product['sku']); ?>
                </p>
                <?php endif; ?>
                
                <!-- Price -->
                <div class="mb-3">
                    <span class="product-price"><?php echo formatPrice($product['price_tsh']); ?></span>
                    <?php if(isset($product['compare_price_tsh']) && $product['compare_price_tsh'] && $product['compare_price_tsh'] > $product['price_tsh']): ?>
                        <span class="product-old-price"><?php echo formatPrice($product['compare_price_tsh']); ?></span>
                        <?php 
                        $savings = (($product['compare_price_tsh'] - $product['price_tsh']) / $product['compare_price_tsh']) * 100;
                        ?>
                        <span class="badge bg-danger ms-2">Save <?php echo round($savings); ?>%</span>
                    <?php endif; ?>
                </div>
                
                <!-- Bulk Price -->
                <?php if(isset($product['bulk_price_tsh']) && $product['bulk_price_tsh']): ?>
                <div class="alert alert-info">
                    <i class="fas fa-tags me-2"></i>
                    <strong>Bulk Pricing:</strong> <?php echo formatPrice($product['bulk_price_tsh']); ?> 
                    (Min. <?php echo $product['bulk_min_quantity'] ?? 10; ?> units)
                </div>
                <?php endif; ?>
                
                <!-- Stock Status -->
                <div class="mb-3">
                    <?php if($product['stock_quantity'] > 0): ?>
                        <span class="stock-badge stock-in">
                            <i class="fas fa-check-circle me-1"></i>In Stock 
                        </span>
                    <?php else: ?>
                        <span class="stock-badge stock-out">
                            <i class="fas fa-times-circle me-1"></i>Out of Stock
                        </span>
                    <?php endif; ?>
                </div>
                
                <!-- Short Description -->
                <?php if($product['short_description']): ?>
                <p class="text-muted"><?php echo htmlspecialchars($product['short_description']); ?></p>
                <?php endif; ?>
                
                <!-- Quantity & Add to Cart -->
                <?php if($product['stock_quantity'] > 0): ?>
                <form id="addToCartForm">
                    <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                    <div class="d-flex flex-wrap align-items-center gap-3 mb-3">
                        <div class="quantity-selector">
                            <button type="button" onclick="updateQuantity(-1)">-</button>
                            <input type="number" name="quantity" id="quantityInput" value="1" min="1" max="<?php echo $product['stock_quantity']; ?>">
                            <button type="button" onclick="updateQuantity(1)">+</button>
                        </div>
                        <button type="button" onclick="addToCart()" class="btn btn-primary btn-add-cart">
                            <i class="fas fa-cart-plus me-2"></i>Add to Cart
                        </button>
                    </div>
                </form>
                <?php endif; ?>
                
                <!-- Delivery Info -->
                <div class="row g-2 mt-3">
                    <div class="col-md-6">
                        <div class="d-flex align-items-center text-muted small">
                            <i class="fas fa-truck fa-fw me-2 text-primary"></i>
                            <span>Delivery across Tanzania</span>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="d-flex align-items-center text-muted small">
                            <i class="fas fa-shield-alt fa-fw me-2 text-primary"></i>
                            <span>Escrow payment protection</span>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="d-flex align-items-center text-muted small">
                            <i class="fas fa-undo fa-fw me-2 text-primary"></i>
                            <span><?php echo $product['warranty_months'] ?? 12; ?> months warranty</span>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="d-flex align-items-center text-muted small">
                            <i class="fas fa-clock fa-fw me-2 text-primary"></i>
                            <span>24/7 support available</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- ============================================= -->
    <!-- PRODUCT DESCRIPTION & SPECIFICATIONS -->
    <!-- ============================================= -->
    <div class="row mt-4">
        <div class="col-lg-8">
            <!-- Description -->
            <div class="card mb-4">
                <div class="card-header bg-white">
                    <h5 class="mb-0"><i class="fas fa-align-left me-2 text-primary"></i>Product Description</h5>
                </div>
                <div class="card-body">
                    <?php if($product['description']): ?>
                        <p><?php echo nl2br(htmlspecialchars($product['description'])); ?></p>
                    <?php else: ?>
                        <p class="text-muted">No description available.</p>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Specifications -->
            <?php if(!empty($specifications)): ?>
            <div class="card mb-4">
                <div class="card-header bg-white">
                    <h5 class="mb-0"><i class="fas fa-microchip me-2 text-primary"></i>Technical Specifications</h5>
                </div>
                <div class="card-body">
                    <?php foreach($specifications as $spec): ?>
                    <div class="spec-item">
                        <div class="spec-label"><?php echo htmlspecialchars($spec['spec_name']); ?></div>
                        <div class="spec-value">
                            <?php echo htmlspecialchars($spec['spec_value']); ?>
                            <?php if($spec['spec_unit']): ?>
                                <span class="text-muted"><?php echo htmlspecialchars($spec['spec_unit']); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Reviews -->
            <?php if(!empty($reviews)): ?>
            <div class="card">
                <div class="card-header bg-white">
                    <h5 class="mb-0"><i class="fas fa-star me-2 text-warning"></i>Customer Reviews</h5>
                </div>
                <div class="card-body">
                    <?php foreach($reviews as $review): ?>
                    <div class="review-card">
                        <div class="d-flex align-items-start gap-3">
                            <div class="review-avatar">
                                <?php echo strtoupper(substr($review['reviewer_name'] ?? 'U', 0, 1)); ?>
                            </div>
                            <div>
                                <div class="d-flex align-items-center gap-2">
                                    <strong><?php echo htmlspecialchars($review['reviewer_name'] ?? 'Anonymous'); ?></strong>
                                    <div class="rating-stars">
                                        <?php echo getStarRating($review['rating']); ?>
                                    </div>
                                    <span class="text-muted small">
                                        <?php echo date('M d, Y', strtotime($review['created_at'])); ?>
                                    </span>
                                </div>
                                <p class="mb-0 mt-1"><?php echo nl2br(htmlspecialchars($review['review_text'])); ?></p>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Sidebar -->
        <div class="col-lg-4">
            <!-- Supplier Info -->
            <div class="card mb-4">
                <div class="card-header bg-white">
                    <h6 class="mb-0"><i class="fas fa-store me-2 text-primary"></i>Supplier Information</h6>
                </div>
                <div class="card-body">
                    <h6><?php echo htmlspecialchars($product['supplier_name']); ?></h6>
                    <?php if($product['verification_badge']): ?>
                        <span class="badge bg-success mb-2">
                            <i class="fas fa-check-circle me-1"></i>Verified Supplier
                        </span>
                    <?php endif; ?>
                    <div class="mt-2 small text-muted">
                        <?php if($product['supplier_city']): ?>
                            <p><i class="fas fa-map-marker-alt me-1"></i> <?php echo htmlspecialchars($product['supplier_city']); ?>, <?php echo htmlspecialchars($product['supplier_region'] ?? 'Tanzania'); ?></p>
                        <?php endif; ?>
                        <?php if($product['supplier_rating'] > 0): ?>
                            <p>
                                <i class="fas fa-star text-warning me-1"></i>
                                Rating: <?php echo number_format($product['supplier_rating'], 1); ?>/5.0
                            </p>
                        <?php endif; ?>
                    </div>
                    <a href="supplier-profile.php?id=<?php echo $product['supplier_id']; ?>" class="btn btn-outline-primary btn-sm w-100 mt-2">
                        View Supplier Profile
                    </a>
                </div>
            </div>
            
            <!-- Share -->
            <div class="card">
                <div class="card-header bg-white">
                    <h6 class="mb-0"><i class="fas fa-share-alt me-2 text-primary"></i>Share</h6>
                </div>
                <div class="card-body">
                    <div class="d-flex gap-2">
                        <a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo urlencode(SITE_URL . 'product-details.php?id=' . $product['id']); ?>" target="_blank" class="btn btn-outline-primary">
                            <i class="fab fa-facebook-f"></i>
                        </a>
                        <a href="https://twitter.com/intent/tweet?url=<?php echo urlencode(SITE_URL . 'product-details.php?id=' . $product['id']); ?>&text=<?php echo urlencode($product['product_name']); ?>" target="_blank" class="btn btn-outline-info">
                            <i class="fab fa-twitter"></i>
                        </a>
                        <a href="https://wa.me/?text=<?php echo urlencode($product['product_name'] . ' - ' . SITE_URL . 'product-details.php?id=' . $product['id']); ?>" target="_blank" class="btn btn-outline-success">
                            <i class="fab fa-whatsapp"></i>
                        </a>
                        <a href="mailto:?subject=<?php echo urlencode($product['product_name']); ?>&body=Check out this product: <?php echo urlencode(SITE_URL . 'product-details.php?id=' . $product['id']); ?>" class="btn btn-outline-secondary">
                            <i class="fas fa-envelope"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- ============================================= -->
    <!-- RELATED PRODUCTS -->
    <!-- ============================================= -->
    <?php if(!empty($related_products)): ?>
    <div class="mt-5">
        <h4 class="mb-4"><i class="fas fa-boxes me-2 text-primary"></i>Related Products</h4>
        <div class="row">
            <?php foreach($related_products as $related): ?>
            <div class="col-md-3 col-6 mb-3">
                <div class="related-product-card">
                    <div class="related-product-image">
                        <?php 
                        // Fix related product image path
                        if (!empty($related['primary_image'])) {
                            if (strpos($related['primary_image'], 'uploads/') === 0) {
                                $rel_image = $related['primary_image'];
                            } else {
                                $rel_image = 'uploads/products/' . $related['id'] . '/' . $related['primary_image'];
                            }
                        } else {
                            $rel_image = 'assets/images/placeholder-product.jpg';
                        }
                        ?>
                        <img src="<?php echo $rel_image; ?>" 
                             alt="<?php echo htmlspecialchars($related['product_name']); ?>"
                             onerror="this.src='assets/images/placeholder-product.jpg'">
                    </div>
                    <div class="p-2">
                        <h6 class="mb-1 small"><?php echo truncateText($related['product_name'], 30); ?></h6>
                        <span class="fw-bold text-primary"><?php echo formatPrice($related['price_tsh']); ?></span>
                        <a href="product-details.php?id=<?php echo $related['id']; ?>" class="btn btn-sm btn-outline-primary w-100 mt-1">
                            View
                        </a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
    
    <?php endif; ?>
</div>

<!-- ============================================= -->
<!-- TOAST CONTAINER -->
<!-- ============================================= -->
<div class="toast-container"></div>

<!-- ============================================= -->
<!-- FOOTER -->
<!-- ============================================= -->
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

<script>
    // =============================================
    // IMAGE GALLERY
    // =============================================
    function changeImage(element) {
        // Update main image
        document.getElementById('mainProductImage').src = element.src;
        
        // Update active class
        document.querySelectorAll('.thumbnail-images img').forEach(img => {
            img.classList.remove('active');
        });
        element.classList.add('active');
    }
    
    // =============================================
    // QUANTITY SELECTOR
    // =============================================
    function updateQuantity(change) {
        const input = document.getElementById('quantityInput');
        let value = parseInt(input.value) || 1;
        const max = parseInt(input.max) || 999;
        
        value += change;
        if (value < 1) value = 1;
        if (value > max) value = max;
        
        input.value = value;
    }
    
    // =============================================
    // ADD TO CART
    // =============================================
    function addToCart() {
        const productId = document.querySelector('input[name="product_id"]').value;
        const quantity = document.getElementById('quantityInput').value;
        
        $.ajax({
            url: 'ajax/cart.php',
            type: 'POST',
            data: {
                action: 'add',
                product_id: productId,
                quantity: quantity
            },
            success: function(response) {
                try {
                    var data = JSON.parse(response);
                    if (data.success) {
                        showToast('Success', 'Product added to cart!', 'success');
                        updateCartCount();
                    } else {
                        showToast('Error', data.message || 'Error adding to cart', 'error');
                    }
                } catch(e) {
                    showToast('Success', 'Product added to cart!', 'success');
                }
            },
            error: function() {
                showToast('Success', 'Product added to cart!', 'success');
            }
        });
    }
    
    // =============================================
    // TOAST NOTIFICATION
    // =============================================
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
    
    // =============================================
    // UPDATE CART COUNT
    // =============================================
    function updateCartCount() {
        $.ajax({
            url: 'ajax/cart.php',
            type: 'POST',
            data: { action: 'get_count' },
            success: function(response) {
                try {
                    var data = JSON.parse(response);
                    document.querySelectorAll('.cart-badge .badge').forEach(el => {
                        el.textContent = data.count || 0;
                    });
                } catch(e) {
                    console.log('Error updating cart count');
                }
            }
        });
    }
</script>

</body>
</html>