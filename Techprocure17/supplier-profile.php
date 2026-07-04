<?php
/**
 * TechProcure Tanzania - Supplier Profile Page
 * File: supplier-profile.php
 * Description: Display supplier details, products, reviews, and information
 */

// Start session if not started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include configuration files
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';

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

if (!function_exists('truncate_text')) {
    function truncate_text($text, $length = 100, $suffix = '...') {
        if (strlen($text) <= $length) return $text;
        return substr($text, 0, $length) . $suffix;
    }
}

// =============================================
// GET DATABASE CONNECTION
// =============================================
// Check if $db is set from db.php, otherwise create connection
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
// GET SUPPLIER ID
// =============================================
$supplier_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($supplier_id <= 0) {
    header('Location: suppliers.php');
    exit();
}

// =============================================
// FETCH SUPPLIER DETAILS
// =============================================
$supplier = null;
$products = [];
$reviews = [];
$stats = [];

try {
    // Get supplier details
    $sql = "SELECT s.*, 
            u.full_name as user_full_name,
            u.email as user_email,
            u.phone as user_phone
            FROM suppliers s
            LEFT JOIN users u ON s.user_id = u.id
            WHERE s.id = ? AND s.approval_status = 'approved' AND s.status = 'active'";
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(1, $supplier_id, PDO::PARAM_INT);
    $stmt->execute();
    $supplier = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$supplier) {
        header('Location: suppliers.php');
        exit();
    }

    // Get supplier products
    $sql = "SELECT p.*, 
            c.name as category_name,
            (SELECT image_path FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) as primary_image
            FROM products p
            JOIN categories c ON p.category_id = c.id
            WHERE p.supplier_id = ? 
            AND p.approval_status = 'approved' 
            AND p.status = 'active'
            ORDER BY p.created_at DESC
            LIMIT 12";
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(1, $supplier_id, PDO::PARAM_INT);
    $stmt->execute();
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get supplier reviews
    $sql = "SELECT sr.*, 
            u.full_name as reviewer_name,
            u.email as reviewer_email
            FROM supplier_reviews sr
            JOIN users u ON sr.customer_id = u.id
            WHERE sr.supplier_id = ? 
            AND sr.status = 'approved'
            ORDER BY sr.created_at DESC
            LIMIT 10";
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(1, $supplier_id, PDO::PARAM_INT);
    $stmt->execute();
    $reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get statistics
    // Total products
    $sql = "SELECT COUNT(*) as total FROM products WHERE supplier_id = ? AND approval_status = 'approved' AND status = 'active'";
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(1, $supplier_id, PDO::PARAM_INT);
    $stmt->execute();
    $stats['total_products'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

    // Total sales (using total_amount_tsh or total_amount)
    $total_amount_column = 'total_amount';
    try {
        $check_sql = "SHOW COLUMNS FROM orders LIKE 'total_amount_tsh'";
        $check_stmt = $conn->query($check_sql);
        if ($check_stmt->rowCount() > 0) {
            $total_amount_column = 'total_amount_tsh';
        }
    } catch (PDOException $e) {
        $total_amount_column = 'total_amount';
    }
    
    $sql = "SELECT COUNT(*) as total, SUM($total_amount_column) as total_amount 
            FROM orders 
            WHERE supplier_id = ? AND payment_status = 'paid'";
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(1, $supplier_id, PDO::PARAM_INT);
    $stmt->execute();
    $order_stats = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['total_orders'] = $order_stats['total'] ?? 0;
    $stats['total_revenue'] = $order_stats['total_amount'] ?? 0;

    // Total reviews
    $sql = "SELECT COUNT(*) as total, AVG(rating) as avg_rating FROM supplier_reviews WHERE supplier_id = ? AND status = 'approved'";
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(1, $supplier_id, PDO::PARAM_INT);
    $stmt->execute();
    $review_stats = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['total_reviews'] = $review_stats['total'] ?? 0;
    $stats['avg_rating'] = round($review_stats['avg_rating'] ?? 0, 1);

} catch (PDOException $e) {
    $error = "Failed to load supplier data: " . $e->getMessage();
}

// =============================================
// PAGE TITLE
// =============================================
$page_title = htmlspecialchars($supplier['company_name'] ?? 'Supplier Profile') . ' - TechProcure Tanzania';
$meta_description = 'View supplier profile for ' . htmlspecialchars($supplier['company_name'] ?? 'Supplier') . ' on TechProcure Tanzania.';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?php echo $meta_description; ?>">
    <title><?php echo $page_title; ?></title>
    
    <!-- CSS Dependencies -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/style.css">
    
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f8f9fa;
        }
        
        /* Supplier Header */
        .supplier-header {
            background: linear-gradient(135deg, #1a2a6c, #b21f1f, #fdbb4d);
            padding: 60px 0 40px;
            color: white;
        }
        
        .supplier-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            border: 4px solid rgba(255,255,255,0.3);
            object-fit: cover;
            background: rgba(255,255,255,0.1);
        }
        
        .supplier-header .company-name {
            font-size: 2rem;
            font-weight: 700;
        }
        
        .supplier-header .company-location {
            opacity: 0.8;
        }
        
        .supplier-header .rating-stars {
            color: #ffc107;
        }
        
        .stat-box {
            background: rgba(255,255,255,0.15);
            border-radius: 10px;
            padding: 15px 20px;
            text-align: center;
            backdrop-filter: blur(10px);
        }
        
        .stat-box .stat-number {
            font-size: 1.5rem;
            font-weight: 700;
        }
        
        .stat-box .stat-label {
            font-size: 0.75rem;
            opacity: 0.8;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        /* Supplier Info Tabs */
        .nav-tabs-custom {
            background: white;
            border-radius: 12px;
            padding: 5px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-top: -25px;
            position: relative;
            z-index: 10;
        }
        
        .nav-tabs-custom .nav-link {
            border: none;
            border-radius: 8px;
            padding: 12px 24px;
            color: #6c757d;
            font-weight: 500;
            transition: all 0.3s;
        }
        
        .nav-tabs-custom .nav-link:hover {
            background: #f8f9fa;
            color: #0d6efd;
        }
        
        .nav-tabs-custom .nav-link.active {
            background: #0d6efd;
            color: white;
        }
        
        .nav-tabs-custom .nav-link i {
            margin-right: 8px;
        }
        
        /* Product Cards */
        .product-card {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            transition: all 0.3s;
            height: 100%;
        }
        
        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        
        .product-card .product-image {
            height: 200px;
            object-fit: cover;
            width: 100%;
        }
        
        .product-card .product-body {
            padding: 15px;
        }
        
        .product-card .product-name {
            font-weight: 600;
            font-size: 0.95rem;
            margin-bottom: 5px;
        }
        
        .product-card .product-price {
            font-weight: 700;
            color: #0d6efd;
            font-size: 1.1rem;
        }
        
        /* Review Cards */
        .review-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 15px;
        }
        
        .review-card .review-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .review-card .reviewer-name {
            font-weight: 600;
        }
        
        .review-card .review-date {
            color: #6c757d;
            font-size: 0.85rem;
        }
        
        .review-card .review-rating {
            color: #ffc107;
        }
        
        .review-card .review-comment {
            color: #555;
            margin-bottom: 0;
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 12px;
        }
        
        .empty-state i {
            font-size: 3rem;
            color: #dee2e6;
            margin-bottom: 20px;
        }
        
        .empty-state h5 {
            color: #333;
        }
        
        .empty-state p {
            color: #6c757d;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .supplier-header {
                padding: 40px 0 30px;
                text-align: center;
            }
            .supplier-header .company-name {
                font-size: 1.5rem;
            }
            .stat-box {
                padding: 10px 15px;
            }
            .stat-box .stat-number {
                font-size: 1.2rem;
            }
            .nav-tabs-custom .nav-link {
                padding: 10px 16px;
                font-size: 0.85rem;
            }
            .supplier-avatar {
                width: 80px;
                height: 80px;
            }
        }
    </style>
</head>
<body>



<!-- ===================================================== -->
<!-- SUPPLIER HEADER -->
<!-- ===================================================== -->
<section class="supplier-header">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-md-8">
                <div class="d-flex align-items-center gap-4">
                    <img src="<?php echo !empty($supplier['company_logo']) ? 'uploads/suppliers/' . $supplier['company_logo'] : 'assets/images/company-placeholder.png'; ?>" 
                         alt="<?php echo htmlspecialchars($supplier['company_name']); ?>" 
                         class="supplier-avatar">
                    <div>
                        <h1 class="company-name">
                            <?php echo htmlspecialchars($supplier['company_name']); ?>
                            <?php if(isset($supplier['verification_badge']) && $supplier['verification_badge']): ?>
                                <i class="fas fa-check-circle text-success ms-2" title="Verified Supplier"></i>
                            <?php endif; ?>
                        </h1>
                        <p class="company-location">
                            <i class="fas fa-map-marker-alt me-1"></i>
                            <?php echo htmlspecialchars($supplier['city'] ?? 'Tanzania'); ?>,
                            <?php echo htmlspecialchars($supplier['region'] ?? ''); ?>
                        </p>
                        <div class="rating-stars">
                            <?php 
                            $rating = round($stats['avg_rating'] ?? 0);
                            for($i = 1; $i <= 5; $i++): 
                            ?>
                                <i class="fas fa-star <?php echo $i <= $rating ? 'text-warning' : 'text-white-50'; ?>"></i>
                            <?php endfor; ?>
                            <span class="ms-2"><?php echo number_format($stats['total_reviews'] ?? 0); ?> reviews</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mt-3 mt-md-0">
                <div class="row g-2">
                    <div class="col-4">
                        <div class="stat-box">
                            <div class="stat-number"><?php echo number_format($stats['total_products'] ?? 0); ?></div>
                            <div class="stat-label">Products</div>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="stat-box">
                            <div class="stat-number"><?php echo number_format($stats['total_orders'] ?? 0); ?></div>
                            <div class="stat-label">Sales</div>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="stat-box">
                            <div class="stat-number"><?php echo format_price($stats['total_revenue'] ?? 0); ?></div>
                            <div class="stat-label">Revenue</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ===================================================== -->
<!-- TABS NAVIGATION -->
<!-- ===================================================== -->
<div class="container">
    <div class="nav-tabs-custom">
        <ul class="nav nav-pills" id="supplierTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="products-tab" data-bs-toggle="tab" data-bs-target="#products" type="button" role="tab">
                    <i class="fas fa-box"></i> Products
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="about-tab" data-bs-toggle="tab" data-bs-target="#about" type="button" role="tab">
                    <i class="fas fa-info-circle"></i> About
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="reviews-tab" data-bs-toggle="tab" data-bs-target="#reviews" type="button" role="tab">
                    <i class="fas fa-star"></i> Reviews (<?php echo number_format($stats['total_reviews'] ?? 0); ?>)
                </button>
            </li>
        </ul>
    </div>
</div>

<!-- ===================================================== -->
<!-- TAB CONTENT -->
<!-- ===================================================== -->
<section class="py-4">
    <div class="container">
        <div class="tab-content">
            
            <!-- ===================================================== -->
            <!-- PRODUCTS TAB -->
            <!-- ===================================================== -->
            <div class="tab-pane fade show active" id="products" role="tabpanel">
                <?php if (!empty($products)): ?>
                    <div class="row g-4">
                        <?php foreach($products as $product): ?>
                            <div class="col-lg-3 col-md-4 col-sm-6">
                                <div class="product-card">
                                    <img src="<?php echo !empty($product['primary_image']) ? 'uploads/products/' . $product['primary_image'] : 'assets/images/placeholder-product.jpg'; ?>" 
                                         class="product-image" 
                                         alt="<?php echo htmlspecialchars($product['product_name']); ?>">
                                    <div class="product-body">
                                        <span class="badge bg-secondary mb-2"><?php echo htmlspecialchars($product['category_name']); ?></span>
                                        <h6 class="product-name"><?php echo truncate_text($product['product_name'], 40); ?></h6>
                                        <p class="text-muted small"><?php echo htmlspecialchars($product['brand']); ?></p>
                                        <div class="product-price"><?php echo format_price($product['price_tsh']); ?></div>
                                        <?php if(isset($product['bulk_price_tsh']) && $product['bulk_price_tsh']): ?>
                                            <small class="text-success">Bulk: <?php echo format_price($product['bulk_price_tsh']); ?></small>
                                        <?php endif; ?>
                                        <div class="mt-2">
                                            <a href="product-details.php?id=<?php echo $product['id']; ?>" class="btn btn-sm btn-outline-primary w-100">
                                                <i class="fas fa-eye me-1"></i> View Details
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <?php if (($stats['total_products'] ?? 0) > 12): ?>
                        <div class="text-center mt-4">
                            <a href="products.php?supplier=<?php echo $supplier_id; ?>" class="btn btn-primary">
                                View All Products <i class="fas fa-arrow-right ms-2"></i>
                            </a>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-box-open"></i>
                        <h5>No Products Yet</h5>
                        <p>This supplier hasn't added any products yet.</p>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- ===================================================== -->
            <!-- ABOUT TAB -->
            <!-- ===================================================== -->
            <div class="tab-pane fade" id="about" role="tabpanel">
                <div class="row">
                    <div class="col-lg-8">
                        <div class="card border-0 shadow-sm">
                            <div class="card-body">
                                <h5 class="card-title"><i class="fas fa-building text-primary me-2"></i>About <?php echo htmlspecialchars($supplier['company_name']); ?></h5>
                                
                                <?php if(!empty($supplier['business_description'])): ?>
                                    <p><?php echo nl2br(htmlspecialchars($supplier['business_description'])); ?></p>
                                <?php else: ?>
                                    <p class="text-muted">No description provided.</p>
                                <?php endif; ?>
                                
                                <hr>
                                
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <h6><i class="fas fa-info-circle text-primary me-2"></i>Company Information</h6>
                                        <ul class="list-unstyled">
                                            <li class="mb-2">
                                                <i class="fas fa-building me-2 text-muted"></i>
                                                <strong>Company:</strong> <?php echo htmlspecialchars($supplier['company_name']); ?>
                                            </li>
                                            <?php if(!empty($supplier['registration_number'])): ?>
                                            <li class="mb-2">
                                                <i class="fas fa-id-card me-2 text-muted"></i>
                                                <strong>Reg. Number:</strong> <?php echo htmlspecialchars($supplier['registration_number']); ?>
                                            </li>
                                            <?php endif; ?>
                                            <?php if(!empty($supplier['tax_id'])): ?>
                                            <li class="mb-2">
                                                <i class="fas fa-file-invoice me-2 text-muted"></i>
                                                <strong>Tax ID:</strong> <?php echo htmlspecialchars($supplier['tax_id']); ?>
                                            </li>
                                            <?php endif; ?>
                                            <li class="mb-2">
                                                <i class="fas fa-calendar-alt me-2 text-muted"></i>
                                                <strong>Member Since:</strong> <?php echo date('F Y', strtotime($supplier['created_at'])); ?>
                                            </li>
                                        </ul>
                                    </div>
                                    <div class="col-md-6">
                                        <h6><i class="fas fa-address-card text-primary me-2"></i>Contact Information</h6>
                                        <ul class="list-unstyled">
                                            <li class="mb-2">
                                                <i class="fas fa-user me-2 text-muted"></i>
                                                <strong>Contact:</strong> <?php echo htmlspecialchars($supplier['contact_person'] ?? 'N/A'); ?>
                                            </li>
                                            <li class="mb-2">
                                                <i class="fas fa-envelope me-2 text-muted"></i>
                                                <strong>Email:</strong> <a href="mailto:<?php echo htmlspecialchars($supplier['email']); ?>"><?php echo htmlspecialchars($supplier['email']); ?></a>
                                            </li>
                                            <li class="mb-2">
                                                <i class="fas fa-phone me-2 text-muted"></i>
                                                <strong>Phone:</strong> <a href="tel:<?php echo htmlspecialchars($supplier['phone']); ?>"><?php echo htmlspecialchars($supplier['phone']); ?></a>
                                            </li>
                                            <?php if(!empty($supplier['address'])): ?>
                                            <li class="mb-2">
                                                <i class="fas fa-map-marker-alt me-2 text-muted"></i>
                                                <strong>Address:</strong> <?php echo htmlspecialchars($supplier['address']); ?>
                                            </li>
                                            <?php endif; ?>
                                            <?php if(!empty($supplier['website'])): ?>
                                            <li class="mb-2">
                                                <i class="fas fa-globe me-2 text-muted"></i>
                                                <strong>Website:</strong> <a href="<?php echo htmlspecialchars($supplier['website']); ?>" target="_blank"><?php echo htmlspecialchars($supplier['website']); ?></a>
                                            </li>
                                            <?php endif; ?>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-4">
                        <div class="card border-0 shadow-sm">
                            <div class="card-body text-center">
                                <h6><i class="fas fa-star text-warning me-2"></i>Supplier Rating</h6>
                                <div class="display-1 text-warning"><?php echo number_format($stats['avg_rating'] ?? 0, 1); ?></div>
                                <div class="rating-stars mb-2">
                                    <?php 
                                    $rating = round($stats['avg_rating'] ?? 0);
                                    for($i = 1; $i <= 5; $i++): 
                                    ?>
                                        <i class="fas fa-star <?php echo $i <= $rating ? 'text-warning' : 'text-secondary'; ?> fa-2x"></i>
                                    <?php endfor; ?>
                                </div>
                                <p class="text-muted">Based on <?php echo number_format($stats['total_reviews'] ?? 0); ?> reviews</p>
                                
                                <?php if(isset($supplier['verification_badge']) && $supplier['verification_badge']): ?>
                                    <div class="mt-3">
                                        <span class="badge bg-success p-2">
                                            <i class="fas fa-check-circle me-1"></i> Verified Supplier
                                        </span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- ===================================================== -->
            <!-- REVIEWS TAB -->
            <!-- ===================================================== -->
            <div class="tab-pane fade" id="reviews" role="tabpanel">
                <?php if (!empty($reviews)): ?>
                    <?php foreach($reviews as $review): ?>
                        <div class="review-card">
                            <div class="review-header">
                                <div>
                                    <span class="reviewer-name"><?php echo htmlspecialchars($review['reviewer_name'] ?? 'Anonymous'); ?></span>
                                    <?php if(isset($review['verified_purchase']) && $review['verified_purchase']): ?>
                                        <span class="badge bg-success ms-2">
                                            <i class="fas fa-check-circle me-1"></i> Verified Purchase
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <span class="review-date"><?php echo time_ago($review['created_at']); ?></span>
                            </div>
                            <div class="review-rating">
                                <?php 
                                $rating = round($review['rating']);
                                for($i = 1; $i <= 5; $i++): 
                                ?>
                                    <i class="fas fa-star <?php echo $i <= $rating ? 'text-warning' : 'text-secondary'; ?>"></i>
                                <?php endfor; ?>
                                <span class="ms-1 text-muted"><?php echo number_format($review['rating'], 1); ?></span>
                            </div>
                            <?php if(!empty($review['comment'])): ?>
                                <p class="review-comment mt-2"><?php echo nl2br(htmlspecialchars($review['comment'])); ?></p>
                            <?php endif; ?>
                            <?php if(isset($review['communication_rating']) || isset($review['delivery_rating']) || isset($review['quality_rating'])): ?>
                                <div class="mt-2 d-flex gap-3 flex-wrap">
                                    <?php if(isset($review['communication_rating'])): ?>
                                        <small class="text-muted">
                                            <i class="fas fa-comment me-1"></i> Communication: 
                                            <?php for($i = 1; $i <= 5; $i++): ?>
                                                <i class="fas fa-star <?php echo $i <= $review['communication_rating'] ? 'text-warning' : 'text-secondary'; ?>" style="font-size: 10px;"></i>
                                            <?php endfor; ?>
                                        </small>
                                    <?php endif; ?>
                                    <?php if(isset($review['delivery_rating'])): ?>
                                        <small class="text-muted">
                                            <i class="fas fa-truck me-1"></i> Delivery: 
                                            <?php for($i = 1; $i <= 5; $i++): ?>
                                                <i class="fas fa-star <?php echo $i <= $review['delivery_rating'] ? 'text-warning' : 'text-secondary'; ?>" style="font-size: 10px;"></i>
                                            <?php endfor; ?>
                                        </small>
                                    <?php endif; ?>
                                    <?php if(isset($review['quality_rating'])): ?>
                                        <small class="text-muted">
                                            <i class="fas fa-check-circle me-1"></i> Quality: 
                                            <?php for($i = 1; $i <= 5; $i++): ?>
                                                <i class="fas fa-star <?php echo $i <= $review['quality_rating'] ? 'text-warning' : 'text-secondary'; ?>" style="font-size: 10px;"></i>
                                            <?php endfor; ?>
                                        </small>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                    
                    <?php if (($stats['total_reviews'] ?? 0) > 10): ?>
                        <div class="text-center mt-3">
                            <a href="#" class="btn btn-outline-primary">View All Reviews</a>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-star"></i>
                        <h5>No Reviews Yet</h5>
                        <p>This supplier hasn't received any reviews yet. Be the first to review!</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<!-- ===================================================== -->
<!-- FOOTER -->
<!-- ===================================================== -->


<!-- ===================================================== -->
<!-- SCRIPTS -->
<!-- ===================================================== -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="assets/js/app.js"></script>

<script>
// Show toast notification
function showToast(title, message, type) {
    const toastHtml = `
        <div class="toast align-items-center text-white bg-${type === 'success' ? 'success' : 'danger'} border-0" role="alert">
            <div class="d-flex">
                <div class="toast-body">
                    <strong>${title}</strong><br>
                    ${message}
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        </div>
    `;
    
    $('.toast-container').remove();
    $('body').append('<div class="toast-container position-fixed bottom-0 end-0 p-3"></div>');
    $('.toast-container').append(toastHtml);
    const toast = new bootstrap.Toast($('.toast').last(), { delay: 3000 });
    toast.show();
    
    setTimeout(function() { $('.toast').last().remove(); }, 3500);
}

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
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showToast('Success', 'Product added to cart', 'success');
                updateCartCount();
            } else {
                showToast('Error', response.message, 'error');
            }
        },
        error: function() {
            showToast('Error', 'Please login to add items to cart', 'error');
            setTimeout(function() {
                window.location.href = 'auth/login.php';
            }, 1500);
        }
    });
}

// Update cart count
function updateCartCount() {
    $.ajax({
        url: 'ajax/cart.php',
        type: 'GET',
        data: { action: 'count' },
        dataType: 'json',
        success: function(response) {
            $('#cartCount').text(response.count);
            if (response.count > 0) {
                $('#cartCount').show();
            } else {
                $('#cartCount').hide();
            }
        }
    });
}

// Initialize on load
$(document).ready(function() {
    updateCartCount();
});
</script>

</body>
</html>