<?php
/**
 * TechProcure Tanzania - Categories Page
 * File: categories.php
 * Description: Display all product categories with product counts and descriptions
 */

// Start session if not started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include configuration files
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';

$page_title = 'Product Categories - TechProcure Tanzania';
$meta_description = 'Browse IT products by category. Find laptops, computers, servers, networking equipment, software, and storage solutions from verified suppliers in Tanzania.';

// Get database connection
$db = getDB();

// =============================================
// FETCH ALL CATEGORIES WITH PRODUCT COUNTS
// =============================================

$categories = [];
try {
    $sql = "SELECT c.*, 
                   COUNT(p.id) as product_count,
                   (SELECT COUNT(*) FROM products WHERE category_id = c.id AND status = 'active' AND approval_status = 'approved') as active_products
            FROM categories c
            LEFT JOIN products p ON c.id = p.category_id AND p.status = 'active' AND p.approval_status = 'approved'
            WHERE c.status = 'active'
            GROUP BY c.id
            ORDER BY c.sort_order ASC, c.name ASC";
    $result = $db->query($sql);
    if ($result && $result->rowCount() > 0) {
        $categories = $result->fetchAll();
    }
} catch (PDOException $e) {
    $categories = [];
}

// =============================================
// FETCH CATEGORIES WITH SUB-CATEGORIES
// =============================================

$parent_categories = [];
$sub_categories = [];

foreach ($categories as $cat) {
    if ($cat['parent_id'] == 0 || $cat['parent_id'] === null) {
        $parent_categories[] = $cat;
    } else {
        $sub_categories[$cat['parent_id']][] = $cat;
    }
}

// =============================================
// HELPER FUNCTIONS
// =============================================

if (!function_exists('getCategoryIcon')) {
    function getCategoryIcon($categoryName) {
        $icons = [
            'Computers' => 'fa-desktop',
            'Laptops' => 'fa-laptop',
            'Servers' => 'fa-server',
            'Networking' => 'fa-network-wired',
            'Software' => 'fa-code',
            'Storage' => 'fa-database',
            'Printers' => 'fa-print',
            'Accessories' => 'fa-keyboard',
            'Monitors' => 'fa-tv',
            'Tablets' => 'fa-tablet-alt',
            'Phones' => 'fa-phone',
            'Security' => 'fa-shield-alt'
        ];
        return $icons[$categoryName] ?? 'fa-box';
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
    <meta name="keywords" content="IT categories, product categories, B2B procurement, Tanzania IT, tech products">
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
        
        .btn-login {
            background: rgba(255,255,255,0.2);
            border: 1px solid rgba(255,255,255,0.3);
            color: white !important;
            border-radius: 50px;
            padding: 5px 15px;
            text-decoration: none;
        }
        
        .btn-login:hover {
            background: rgba(255,255,255,0.3);
            color: white !important;
        }
        
        .btn-register {
            background: #fdbb4d;
            color: #1a2a6c !important;
            border-radius: 50px;
            padding: 5px 15px;
            font-weight: 600;
            text-decoration: none;
        }
        
        .btn-register:hover {
            background: #ffc107;
            color: #1a2a6c !important;
        }
        
        /* Page Header */
        .page-header {
            background: linear-gradient(135deg, #1a2a6c, #b21f1f, #fdbb4d);
            color: white;
            padding: 60px 0;
        }
        
        .page-header h1 {
            font-size: 2.5rem;
            font-weight: 800;
        }
        
        /* Category Cards */
        .category-card {
            background: white;
            border-radius: 15px;
            padding: 30px 20px;
            text-align: center;
            transition: all 0.3s ease;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            border: 1px solid #e9ecef;
            height: 100%;
            text-decoration: none;
            display: block;
            color: #333;
        }
        
        .category-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
            border-color: #0d6efd;
        }
        
        .category-card .category-icon {
            font-size: 3.5rem;
            color: #0d6efd;
            margin-bottom: 15px;
            display: block;
        }
        
        .category-card .category-name {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .category-card .category-count {
            font-size: 0.85rem;
            color: #6c757d;
        }
        
        .category-card .category-description {
            font-size: 0.85rem;
            color: #6c757d;
            margin-top: 10px;
        }
        
        /* Sub Categories */
        .sub-category-section {
            margin-top: 30px;
        }
        
        .sub-category-title {
            font-size: 1.3rem;
            font-weight: 600;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #e9ecef;
        }
        
        .sub-category-card {
            background: white;
            border-radius: 12px;
            padding: 15px 20px;
            text-align: center;
            transition: all 0.3s ease;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            border: 1px solid #e9ecef;
            height: 100%;
            text-decoration: none;
            display: block;
            color: #333;
        }
        
        .sub-category-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            border-color: #0d6efd;
        }
        
        .sub-category-card .sub-icon {
            font-size: 2rem;
            color: #6c757d;
            margin-bottom: 10px;
            display: block;
        }
        
        .sub-category-card .sub-name {
            font-size: 0.95rem;
            font-weight: 500;
        }
        
        .sub-category-card .sub-count {
            font-size: 0.75rem;
            color: #6c757d;
        }
        
        /* Stats Section */
        .stats-section {
            background: linear-gradient(135deg, #1a2a6c, #b21f1f, #fdbb4d);
            color: white;
            padding: 50px 0;
        }
        
        .stat-number {
            font-size: 2.5rem;
            font-weight: 800;
        }
        
        .stat-label {
            font-size: 1rem;
            opacity: 0.8;
        }
        
        /* Footer */
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
        
        @media (max-width: 768px) {
            .page-header h1 {
                font-size: 1.8rem;
            }
            .category-card .category-icon {
                font-size: 2.5rem;
            }
            .stat-number {
                font-size: 1.8rem;
            }
        }
    </style>
</head>
<body>

<!-- Navbar -->
<nav class="navbar navbar-expand-lg">
    <div class="container">
        <a class="navbar-brand" href="index.php">
            <i class="fas fa-microchip"></i>
            TechProcure <span class="text-warning">Tanzania</span>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav mx-auto">
                <li class="nav-item">
                    <a class="nav-link" href="index.php"><i class="fas fa-home me-1"></i>Home</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="products.php"><i class="fas fa-box me-1"></i>Products</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="categories.php"><i class="fas fa-th-large me-1"></i>Categories</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="suppliers.php"><i class="fas fa-truck me-1"></i>Suppliers</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="about.php"><i class="fas fa-info-circle me-1"></i>About Us</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="contact.php"><i class="fas fa-envelope me-1"></i>Contact</a>
                </li>
            </ul>
            
            <div class="d-flex align-items-center gap-2">
                <a href="cart.php" class="nav-link position-relative">
                    <i class="fas fa-shopping-cart fa-lg"></i>
                    <span class="cart-count"><?php echo $cart_count; ?></span>
                </a>
                <?php if (isset($_SESSION['user_id'])): ?>
                    <a href="customer/dashboard.php" class="btn-login">
                        <i class="fas fa-user-circle me-1"></i>
                        <?php echo htmlspecialchars(substr($_SESSION['user_name'] ?? 'Account', 0, 10)); ?>
                    </a>
                    <a href="auth/logout.php" class="btn-login">
                        <i class="fas fa-sign-out-alt me-1"></i>Logout
                    </a>
                <?php else: ?>
                    <a href="auth/login.php" class="btn-login"><i class="fas fa-sign-in-alt me-1"></i>Login</a>
                    <a href="auth/register.php" class="btn-register"><i class="fas fa-user-plus me-1"></i>Register</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>

<!-- Page Header -->
<section class="page-header">
    <div class="container">
        <h1><i class="fas fa-th-large me-3"></i>Product Categories</h1>
        <p class="lead mb-0">Browse our wide range of IT equipment by category</p>
    </div>
</section>

<!-- Categories Grid -->
<section class="py-5">
    <div class="container">
        <?php if (!empty($parent_categories)): ?>
            <div class="row g-4">
                <?php foreach($parent_categories as $index => $category): ?>
                <div class="col-lg-3 col-md-4 col-6" data-aos="fade-up" data-aos-delay="<?php echo $index * 100; ?>">
                    <a href="products.php?category=<?php echo $category['slug']; ?>" class="category-card">
                        <span class="category-icon">
                            <i class="fas <?php echo getCategoryIcon($category['name']); ?>"></i>
                        </span>
                        <h5 class="category-name"><?php echo htmlspecialchars($category['name']); ?></h5>
                        <div class="category-count">
                            <?php echo number_format($category['active_products'] ?? 0); ?> products
                        </div>
                        <?php if($category['description']): ?>
                            <div class="category-description">
                                <?php echo truncateText($category['description'], 60); ?>
                            </div>
                        <?php endif; ?>
                    </a>
                </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Sub Categories -->
            <?php foreach($parent_categories as $parent): ?>
                <?php if(isset($sub_categories[$parent['id']]) && !empty($sub_categories[$parent['id']])): ?>
                <div class="sub-category-section">
                    <h5 class="sub-category-title">
                        <i class="fas <?php echo getCategoryIcon($parent['name']); ?> me-2"></i>
                        <?php echo htmlspecialchars($parent['name']); ?> - Sub Categories
                    </h5>
                    <div class="row g-3">
                        <?php foreach($sub_categories[$parent['id']] as $sub): ?>
                        <div class="col-lg-2 col-md-3 col-4" data-aos="fade-up">
                            <a href="products.php?category=<?php echo $sub['slug']; ?>" class="sub-category-card">
                                <span class="sub-icon">
                                    <i class="fas <?php echo getCategoryIcon($sub['name']); ?>"></i>
                                </span>
                                <div class="sub-name"><?php echo htmlspecialchars($sub['name']); ?></div>
                                <div class="sub-count"><?php echo $sub['active_products'] ?? 0; ?> products</div>
                            </a>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            <?php endforeach; ?>
            
        <?php else: ?>
            <div class="text-center py-5">
                <i class="fas fa-boxes fa-4x text-muted mb-3"></i>
                <h4>No Categories Found</h4>
                <p class="text-muted">Categories will appear here once added by the administrator.</p>
                <?php if(isAdmin()): ?>
                    <a href="admin/products/categories.php" class="btn btn-primary mt-3">
                        <i class="fas fa-plus me-2"></i> Add Categories
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- Statistics Section -->
<section class="stats-section">
    <div class="container">
        <div class="row text-center">
            <div class="col-md-4 mb-4 mb-md-0" data-aos="fade-up" data-aos-delay="100">
                <div class="stat-number"><?php echo number_format(count($categories)); ?></div>
                <div class="stat-label">Categories</div>
            </div>
            <div class="col-md-4 mb-4 mb-md-0" data-aos="fade-up" data-aos-delay="200">
                <div class="stat-number">
                    <?php 
                    $total_products = 0;
                    foreach($categories as $cat) {
                        $total_products += $cat['active_products'] ?? 0;
                    }
                    echo number_format($total_products);
                    ?>
                </div>
                <div class="stat-label">Total Products</div>
            </div>
            <div class="col-md-4" data-aos="fade-up" data-aos-delay="300">
                <div class="stat-number">
                    <?php 
                    $total_suppliers = $db->query("SELECT COUNT(*) FROM suppliers WHERE approval_status = 'approved' AND status = 'active'")->fetchColumn();
                    echo number_format($total_suppliers);
                    ?>
                </div>
                <div class="stat-label">Verified Suppliers</div>
            </div>
        </div>
    </div>
</section>

<!-- Call to Action -->
<section class="py-5" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
    <div class="container text-center" data-aos="fade-up">
        <h2 class="display-5 fw-bold text-white mb-3">Can't Find What You're Looking For?</h2>
        <p class="lead text-white-50 mb-4">Request a custom quotation from our suppliers</p>
        <div class="d-flex flex-wrap gap-3 justify-content-center">
            <a href="quotations/request-quotation.php" class="btn btn-light btn-lg px-5">
                <i class="fas fa-file-alt me-2"></i> Request RFQ
            </a>
            <a href="contact.php" class="btn btn-outline-light btn-lg px-5">
                <i class="fas fa-envelope me-2"></i> Contact Us
            </a>
        </div>
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
    
    // Update cart count
    function updateCartCount() {
        $.ajax({
            url: 'ajax/cart.php',
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
    
    updateCartCount();
</script>

</body>
</html>