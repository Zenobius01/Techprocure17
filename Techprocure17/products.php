<?php
/**
 * TechProcure Tanzania - Products Page with AJAX Cart
 * File: products.php
 * Description: Display all products with images, filters, pagination, and AJAX add to cart
 */

// Start session if not started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include configuration files
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';

$page_title = 'Browse IT Products - TechProcure Tanzania';
$meta_description = 'Browse our wide range of IT equipment including laptops, servers, networking devices, and software from verified suppliers in Tanzania.';

// Get database connection
$db = getDB();

// =============================================
// HANDLE AJAX CART REQUESTS
// =============================================
if (isset($_POST['ajax_action'])) {
    header('Content-Type: application/json');
    
    $action = $_POST['ajax_action'];
    $product_id = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
    $quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 1;
    
    // Initialize cart if not exists
    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }
    
    switch ($action) {
        case 'add':
            if ($product_id <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid product ID']);
                exit;
            }
            
            try {
                $stmt = $db->prepare("SELECT id, product_name, price_tsh, stock_quantity FROM products WHERE id = ? AND status = 'active' AND approval_status = 'approved'");
                $stmt->execute([$product_id]);
                $product = $stmt->fetch();
                
                if (!$product) {
                    echo json_encode(['success' => false, 'message' => 'Product not found']);
                    exit;
                }
                
                if ($product['stock_quantity'] < $quantity) {
                    echo json_encode(['success' => false, 'message' => 'Not enough stock available']);
                    exit;
                }
                
                if (isset($_SESSION['cart'][$product_id])) {
                    $_SESSION['cart'][$product_id]['quantity'] += $quantity;
                } else {
                    $_SESSION['cart'][$product_id] = [
                        'id' => $product_id,
                        'name' => $product['product_name'],
                        'price' => $product['price_tsh'],
                        'quantity' => $quantity
                    ];
                }
                
                $count = 0;
                foreach ($_SESSION['cart'] as $item) {
                    $count += $item['quantity'];
                }
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Product added to cart',
                    'count' => $count
                ]);
                
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Database error']);
            }
            exit;
            
        case 'get_count':
            $count = 0;
            foreach ($_SESSION['cart'] as $item) {
                $count += $item['quantity'];
            }
            echo json_encode(['count' => $count]);
            exit;
            
        case 'remove':
            if (isset($_SESSION['cart'][$product_id])) {
                unset($_SESSION['cart'][$product_id]);
            }
            $count = 0;
            foreach ($_SESSION['cart'] as $item) {
                $count += $item['quantity'];
            }
            echo json_encode(['success' => true, 'count' => $count]);
            exit;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            exit;
    }
}

// =============================================
// GET FILTER PARAMETERS
// =============================================

$category_slug = isset($_GET['category']) ? trim($_GET['category']) : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$min_price = isset($_GET['min_price']) ? (int)$_GET['min_price'] : 0;
$max_price = isset($_GET['max_price']) ? (int)$_GET['max_price'] : 0;
$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'newest';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 12;
$offset = ($page - 1) * $limit;

// =============================================
// GET ALL CATEGORIES FOR FILTER
// =============================================

$all_categories = [];
try {
    $column_check = $db->query("SHOW COLUMNS FROM categories LIKE 'sort_order'");
    $has_sort_order = $column_check->rowCount() > 0;
    
    if ($has_sort_order) {
        $sql = "SELECT * FROM categories WHERE status = 'active' ORDER BY sort_order ASC, name ASC";
    } else {
        $sql = "SELECT * FROM categories WHERE status = 'active' ORDER BY name ASC";
    }
    $result = $db->query($sql);
    if ($result && $result->rowCount() > 0) {
        $all_categories = $result->fetchAll();
    }
} catch (PDOException $e) {
    try {
        $sql = "SELECT * FROM categories ORDER BY name ASC";
        $result = $db->query($sql);
        if ($result && $result->rowCount() > 0) {
            $all_categories = $result->fetchAll();
        }
    } catch (PDOException $e2) {
        $all_categories = [];
    }
}

// =============================================
// BUILD PRODUCT QUERY - Only show approved products
// =============================================

$where_conditions = ["p.approval_status = 'approved'", "p.status = 'active'"];
$params = [];

// Category filter
if (!empty($category_slug)) {
    try {
        $cat_stmt = $db->prepare("SELECT id FROM categories WHERE slug = ?");
        $cat_stmt->execute([$category_slug]);
        $category = $cat_stmt->fetch();
        if ($category) {
            $where_conditions[] = "p.category_id = ?";
            $params[] = $category['id'];
        }
    } catch (PDOException $e) {}
}

// Search filter
if (!empty($search)) {
    $where_conditions[] = "(p.product_name LIKE ? OR p.description LIKE ? OR p.brand LIKE ? OR p.sku LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

// Price filters
if ($min_price > 0) {
    $where_conditions[] = "p.price_tsh >= ?";
    $params[] = $min_price;
}
if ($max_price > 0) {
    $where_conditions[] = "p.price_tsh <= ?";
    $params[] = $max_price;
}

$where_clause = implode(" AND ", $where_conditions);

// =============================================
// COUNT TOTAL PRODUCTS
// =============================================

$total_products = 0;
$total_pages = 1;

try {
    $count_sql = "SELECT COUNT(*) as total FROM products p 
                  LEFT JOIN categories c ON p.category_id = c.id 
                  WHERE $where_clause";
    $stmt = $db->prepare($count_sql);
    if (!empty($params)) {
        $stmt->execute($params);
    } else {
        $stmt->execute();
    }
    $count_result = $stmt->fetch();
    $total_products = $count_result ? (int)$count_result['total'] : 0;
    $total_pages = ceil($total_products / $limit);
} catch (PDOException $e) {
    $total_products = 0;
    $total_pages = 1;
}

// =============================================
// GET PRODUCTS WITH IMAGES
// =============================================

$products = [];

try {
    $order_by = "p.created_at DESC";
    switch ($sort_by) {
        case 'price_low': $order_by = "p.price_tsh ASC"; break;
        case 'price_high': $order_by = "p.price_tsh DESC"; break;
        case 'popular': $order_by = "p.views DESC, p.total_reviews DESC"; break;
        case 'name_asc': $order_by = "p.product_name ASC"; break;
        case 'name_desc': $order_by = "p.product_name DESC"; break;
        default: $order_by = "p.created_at DESC"; break;
    }
    
    $sql = "SELECT p.*, c.name as category_name, c.slug as category_slug, 
            s.company_name as supplier_name, s.verification_badge,
            (SELECT image_path FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) as primary_image
            FROM products p 
            LEFT JOIN categories c ON p.category_id = c.id 
            LEFT JOIN suppliers s ON p.supplier_id = s.id 
            WHERE $where_clause
            ORDER BY $order_by
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
// GET PRICE RANGE FOR FILTER
// =============================================

$global_min_price = 0;
$global_max_price = 10000000;

try {
    $price_sql = "SELECT MIN(price_tsh) as min_price, MAX(price_tsh) as max_price 
                  FROM products WHERE approval_status = 'approved' AND status = 'active'";
    $price_result = $db->query($price_sql);
    if ($price_result && $price_result->rowCount() > 0) {
        $price_range = $price_result->fetch();
        $global_min_price = $price_range['min_price'] ?? 0;
        $global_max_price = $price_range['max_price'] ?? 10000000;
    }
} catch (PDOException $e) {
    $global_min_price = 0;
    $global_max_price = 10000000;
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

// Get cart count
$cart_count = isset($_SESSION['cart']) ? array_sum(array_column($_SESSION['cart'], 'quantity')) : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?php echo $meta_description; ?>">
    <meta name="keywords" content="IT products Tanzania, buy laptops, servers, networking equipment, B2B procurement">
    <title><?php echo $page_title; ?></title>
    
    <!-- CSS Dependencies -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/aos/2.3.4/aos.css">
    
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f8f9fa;
            padding-top: 20px;
        }
        
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
        
        .filter-sidebar {
            background: white;
            border-radius: 12px;
            padding: 20px;
            position: sticky;
            top: 20px;
        }
        
        .filter-section {
            border-bottom: 1px solid #e0e0e0;
            padding-bottom: 15px;
            margin-bottom: 15px;
        }
        
        .filter-section:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }
        
        .filter-title {
            font-weight: 600;
            margin-bottom: 12px;
            font-size: 1rem;
        }
        
        .price-input {
            width: 48%;
            display: inline-block;
        }
        
        .product-card {
            border: none;
            border-radius: 12px;
            transition: all 0.3s ease;
            background: white;
            margin-bottom: 24px;
            overflow: hidden;
        }
        
        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        
        .product-image {
            height: 200px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f8f9fa;
            overflow: hidden;
            position: relative;
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
        
        .sort-selector {
            background: white;
            border-radius: 12px;
            padding: 12px 20px;
            margin-bottom: 24px;
        }
        
        .no-results {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 12px;
        }
        
        .pagination .page-link {
            border-radius: 8px;
            margin: 0 3px;
            color: #0d6efd;
        }
        
        .pagination .active .page-link {
            background-color: #0d6efd;
            border-color: #0d6efd;
            color: white;
        }
        
        .footer {
            background: #1a1a2e;
            color: white;
            padding: 60px 0 30px;
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
        
        .toast {
            min-width: 300px;
        }
        
        @media (max-width: 768px) {
            .filter-sidebar {
                position: static;
                margin-bottom: 20px;
            }
            .product-image {
                height: 150px;
            }
            .toast {
                min-width: 250px;
            }
        }
    </style>
</head>
<body>

<!-- ============================================= -->
<!-- BACK BUTTON -->
<!-- ============================================= -->
<div class="container">
    <div class="mb-3">
        <a href="index.php" class="back-link">
            <i class="fas fa-arrow-left me-2"></i>Back to Home
        </a>
        <span class="float-end">
            <a href="cart.php" class="text-dark text-decoration-none position-relative">
                <i class="fas fa-shopping-cart fa-lg"></i>
                <span class="badge bg-danger rounded-pill ms-1" id="cartCount"><?php echo $cart_count; ?></span>
            </a>
        </span>
    </div>
</div>

<!-- ============================================= -->
<!-- PAGE HEADER -->
<!-- ============================================= -->
<section class="bg-primary text-white py-4">
    <div class="container">
        <h1 class="display-5 fw-bold mb-2"><i class="fas fa-box me-3"></i>Browse IT Products</h1>
        <p class="lead mb-0">Discover quality IT equipment from verified suppliers across Tanzania</p>
    </div>
</section>

<!-- ============================================= -->
<!-- MAIN CONTENT -->
<!-- ============================================= -->
<div class="container py-5">
    <div class="row">
        <!-- Filter Sidebar -->
        <div class="col-lg-3 mb-4 mb-lg-0">
            <div class="filter-sidebar">
                <h5 class="mb-3"><i class="fas fa-filter me-2"></i>Filter Products</h5>
                
                <form method="GET" action="products.php" id="filterForm">
                    <div class="filter-section">
                        <div class="filter-title">Search</div>
                        <div class="input-group">
                            <input type="text" name="search" class="form-control" placeholder="Search products..." value="<?php echo htmlspecialchars($search); ?>">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class="filter-section">
                        <div class="filter-title">Categories</div>
                        <div class="form-check mb-2">
                            <input type="radio" name="category" value="" class="form-check-input" id="cat_all" <?php echo empty($category_slug) ? 'checked' : ''; ?> onchange="this.form.submit()">
                            <label class="form-check-label" for="cat_all">All Categories</label>
                        </div>
                        <?php foreach($all_categories as $cat): ?>
                        <div class="form-check mb-2">
                            <input type="radio" name="category" value="<?php echo $cat['slug']; ?>" class="form-check-input" id="cat_<?php echo $cat['id']; ?>" <?php echo $category_slug == $cat['slug'] ? 'checked' : ''; ?> onchange="this.form.submit()">
                            <label class="form-check-label" for="cat_<?php echo $cat['id']; ?>">
                                <?php echo htmlspecialchars($cat['name']); ?>
                            </label>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="filter-section">
                        <div class="filter-title">Price Range (TSh)</div>
                        <div class="d-flex justify-content-between gap-2">
                            <input type="number" name="min_price" class="form-control price-input" placeholder="Min" value="<?php echo $min_price ?: ''; ?>">
                            <span class="align-self-center">-</span>
                            <input type="number" name="max_price" class="form-control price-input" placeholder="Max" value="<?php echo $max_price ?: ''; ?>">
                        </div>
                        <div class="mt-2">
                            <input type="range" class="form-range" id="priceRange" min="<?php echo $global_min_price; ?>" max="<?php echo $global_max_price; ?>" step="1000" value="<?php echo $max_price ?: $global_max_price; ?>">
                        </div>
                        <button type="submit" class="btn btn-sm btn-outline-primary mt-2 w-100">Apply Price</button>
                    </div>
                    
                    <?php if(!empty($category_slug) || !empty($search) || $min_price > 0 || $max_price > 0): ?>
                    <div class="mt-3">
                        <a href="products.php" class="btn btn-secondary w-100">
                            <i class="fas fa-undo me-1"></i>Reset All Filters
                        </a>
                    </div>
                    <?php endif; ?>
                </form>
            </div>
        </div>
        
        <!-- Products Grid -->
        <div class="col-lg-9">
            <div class="sort-selector d-flex justify-content-between align-items-center flex-wrap gap-3">
                <div>
                    <span class="text-muted">Showing <?php echo count($products); ?> of <?php echo $total_products; ?> products</span>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <label class="text-muted mb-0">Sort by:</label>
                    <select class="form-select form-select-sm" style="width: 150px;" onchange="window.location.href=this.value">
                        <option value="?<?php echo http_build_query(array_merge($_GET, ['sort' => 'newest', 'page' => 1])); ?>" <?php echo $sort_by == 'newest' ? 'selected' : ''; ?>>Newest First</option>
                        <option value="?<?php echo http_build_query(array_merge($_GET, ['sort' => 'price_low', 'page' => 1])); ?>" <?php echo $sort_by == 'price_low' ? 'selected' : ''; ?>>Price: Low to High</option>
                        <option value="?<?php echo http_build_query(array_merge($_GET, ['sort' => 'price_high', 'page' => 1])); ?>" <?php echo $sort_by == 'price_high' ? 'selected' : ''; ?>>Price: High to Low</option>
                        <option value="?<?php echo http_build_query(array_merge($_GET, ['sort' => 'popular', 'page' => 1])); ?>" <?php echo $sort_by == 'popular' ? 'selected' : ''; ?>>Most Popular</option>
                        <option value="?<?php echo http_build_query(array_merge($_GET, ['sort' => 'name_asc', 'page' => 1])); ?>" <?php echo $sort_by == 'name_asc' ? 'selected' : ''; ?>>Name: A to Z</option>
                        <option value="?<?php echo http_build_query(array_merge($_GET, ['sort' => 'name_desc', 'page' => 1])); ?>" <?php echo $sort_by == 'name_desc' ? 'selected' : ''; ?>>Name: Z to A</option>
                    </select>
                </div>
            </div>
            
            <?php if (!empty($products)): ?>
            <div class="row">
                <?php foreach($products as $product): ?>
                <div class="col-md-6 col-xl-4" data-aos="fade-up" data-aos-delay="100">
                    <div class="card product-card">
                        <div class="product-image position-relative">
                            <?php 
                            // Fix image path
                            $image_path = !empty($product['primary_image']) ? $product['primary_image'] : 'assets/images/placeholder-product.jpg';
                            
                            if (!empty($product['primary_image']) && strpos($product['primary_image'], 'uploads/') === false) {
                                $image_path = 'uploads/products/' . $product['id'] . '/' . $product['primary_image'];
                            }
                            
                            if (!empty($product['primary_image']) && strpos($product['primary_image'], 'uploads/') === 0) {
                                $image_path = $product['primary_image'];
                            }
                            ?>
                            <img src="<?php echo $image_path; ?>" 
                                 alt="<?php echo htmlspecialchars($product['product_name']); ?>"
                                 onerror="this.src='assets/images/placeholder-product.jpg'">
                            <?php if(isset($product['bulk_price_tsh']) && $product['bulk_price_tsh'] && $product['bulk_price_tsh'] < $product['price_tsh']): ?>
                            <div class="product-badge">
                                <span class="badge bg-success">
                                    <i class="fas fa-tags me-1"></i>Bulk Discount
                                </span>
                            </div>
                            <?php endif; ?>
                        </div>
                        <div class="p-3">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <span class="badge bg-secondary"><?php echo htmlspecialchars($product['brand'] ?? 'Generic'); ?></span>
                                <?php if($product['verification_badge']): ?>
                                <i class="fas fa-check-circle text-success" title="Verified Supplier"></i>
                                <?php endif; ?>
                            </div>
                            <a href="product-details.php?id=<?php echo $product['id']; ?>" class="text-decoration-none">
                                <h6 class="product-title"><?php echo htmlspecialchars($product['product_name']); ?></h6>
                            </a>
                            <div class="mb-2">
                                <span class="product-price"><?php echo formatPrice($product['price_tsh']); ?></span>
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
                                <button onclick="addToCart(<?php echo $product['id']; ?>, 1)" class="btn btn-sm btn-primary add-to-cart" data-product-id="<?php echo $product['id']; ?>">
                                    <i class="fas fa-cart-plus"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
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
                        <a class->page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">
                            Next <i class="fas fa-chevron-right"></i>
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>
            </nav>
            <?php endif; ?>
            
            <?php else: ?>
            <div class="no-results">
                <i class="fas fa-box-open fa-4x text-muted mb-3"></i>
                <h4>No products found</h4>
                <p class="text-muted">We couldn't find any products matching your criteria.</p>
                <a href="products.php" class="btn btn-primary mt-3">
                    <i class="fas fa-undo me-2"></i>Clear Filters
                </a>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Bulk Discount Banner -->
<section class="bg-info text-white py-4">
    <div class="container text-center">
        <i class="fas fa-tags fa-2x mb-2"></i>
        <h4 class="mb-2">Bulk Discounts Available!</h4>
        <p class="mb-0">Get special pricing on bulk orders. Contact us for quotes on 10+ units.</p>
    </div>
</section>

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

<!-- ============================================= -->
<!-- TOAST CONTAINER -->
<!-- ============================================= -->
<div class="toast-container"></div>

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
    
    // =============================================
    // PRICE RANGE SLIDER
    // =============================================
    const priceRange = document.getElementById('priceRange');
    const maxPriceInput = document.querySelector('input[name="max_price"]');
    
    if (priceRange && maxPriceInput) {
        priceRange.addEventListener('input', function() {
            maxPriceInput.value = this.value;
        });
    }
    
    // =============================================
    // AUTO-SUBMIT FORM ON CATEGORY CHANGE
    // =============================================
    document.querySelectorAll('input[name="category"]').forEach(radio => {
        radio.addEventListener('change', function() {
            document.getElementById('filterForm').submit();
        });
    });
    
    // =============================================
    // ADD TO CART - FULLY FUNCTIONAL
    // =============================================
    function addToCart(productId, quantity) {
        // Show loading state on button
        const buttons = document.querySelectorAll(`.add-to-cart[data-product-id="${productId}"]`);
        buttons.forEach(btn => {
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status"></span>';
        });
        
        // Send AJAX request to same file
        $.ajax({
            url: window.location.href,
            type: 'POST',
            data: {
                ajax_action: 'add',
                product_id: productId,
                quantity: quantity || 1
            },
            dataType: 'json',
            success: function(response) {
                // Reset button state
                buttons.forEach(btn => {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-cart-plus"></i>';
                });
                
                if (response.success) {
                    // Update cart count
                    document.getElementById('cartCount').textContent = response.count;
                    
                    // Show success toast
                    showToast('Success', 'Product added to cart successfully!', 'success');
                } else {
                    showToast('Error', response.message || 'Failed to add product to cart', 'error');
                }
            },
            error: function(xhr, status, error) {
                // Reset button state
                buttons.forEach(btn => {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-cart-plus"></i>';
                });
                
                // Try to parse error response
                try {
                    var response = JSON.parse(xhr.responseText);
                    showToast('Error', response.message || 'Failed to add product to cart', 'error');
                } catch(e) {
                    showToast('Error', 'Failed to add product to cart. Please try again.', 'error');
                }
            }
        });
    }
    
    // =============================================
    // SHOW TOAST NOTIFICATION
    // =============================================
    function showToast(title, message, type) {
        const container = document.querySelector('.toast-container');
        const toast = document.createElement('div');
        toast.className = `toast align-items-center text-white bg-${type === 'success' ? 'success' : 'danger'} border-0`;
        toast.setAttribute('role', 'alert');
        toast.setAttribute('aria-live', 'assertive');
        toast.setAttribute('aria-atomic', 'true');
        toast.style.display = 'block';
        toast.innerHTML = `
            <div class="d-flex">
                <div class="toast-body">
                    <strong>${title}</strong><br>
                    ${message}
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        `;
        container.appendChild(toast);
        
        const bsToast = new bootstrap.Toast(toast, { delay: 4000 });
        bsToast.show();
        
        toast.addEventListener('hidden.bs.toast', function() {
            toast.remove();
        });
    }
    
    // =============================================
    // KEYBOARD SHORTCUT: Add to Cart with Enter
    // =============================================
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Enter' && e.target.classList.contains('add-to-cart')) {
            const productId = e.target.dataset.productId;
            if (productId) {
                e.preventDefault();
                addToCart(productId, 1);
            }
        }
    });
</script>

</body>
</html>