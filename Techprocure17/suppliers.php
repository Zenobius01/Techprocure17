<?php
/**
 * TechProcure Tanzania - Suppliers Page
 * File: suppliers.php
 * Description: Display all verified suppliers with search and filtering
 */

// Start session if not started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include configuration files
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';

$page_title = 'Verified IT Suppliers - TechProcure Tanzania';
$meta_description = 'Browse verified IT equipment suppliers in Tanzania. Find trusted partners for your enterprise IT procurement needs.';

// Get database connection
$db = getDB();

// =============================================
// GET FILTER PARAMETERS
// =============================================

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$city = isset($_GET['city']) ? trim($_GET['city']) : '';
$rating = isset($_GET['rating']) ? (int)$_GET['rating'] : 0;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 12;
$offset = ($page - 1) * $limit;

// =============================================
// BUILD SUPPLIER QUERY
// =============================================

$where_conditions = ["s.approval_status = 'approved'", "s.status = 'active'"];
$params = [];

// Search filter
if (!empty($search)) {
    $where_conditions[] = "(s.company_name LIKE ? OR s.contact_person LIKE ? OR s.email LIKE ? OR s.phone LIKE ? OR s.business_description LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

// City filter
if (!empty($city)) {
    $where_conditions[] = "s.city = ?";
    $params[] = $city;
}

// Rating filter
if ($rating > 0) {
    $where_conditions[] = "s.rating >= ?";
    $params[] = $rating;
}

$where_clause = implode(" AND ", $where_conditions);

// =============================================
// COUNT TOTAL SUPPLIERS
// =============================================

$total_suppliers = 0;
$total_pages = 1;

try {
    $count_sql = "SELECT COUNT(*) as total FROM suppliers s WHERE $where_clause";
    $stmt = $db->prepare($count_sql);
    if (!empty($params)) {
        $stmt->execute($params);
    } else {
        $stmt->execute();
    }
    $count_result = $stmt->fetch();
    $total_suppliers = $count_result ? (int)$count_result['total'] : 0;
    $total_pages = ceil($total_suppliers / $limit);
} catch (PDOException $e) {
    $total_suppliers = 0;
    $total_pages = 1;
}

// =============================================
// GET SUPPLIERS
// =============================================

$suppliers = [];

try {
    $sql = "SELECT s.*, u.email as user_email 
            FROM suppliers s 
            LEFT JOIN users u ON s.user_id = u.id 
            WHERE $where_clause
            ORDER BY s.rating DESC, s.total_sales DESC, s.company_name ASC
            LIMIT ? OFFSET ?";
    
    $params[] = $limit;
    $params[] = $offset;
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    
    if ($stmt->rowCount() > 0) {
        $suppliers = $stmt->fetchAll();
    }
} catch (PDOException $e) {
    $suppliers = [];
}

// =============================================
// GET CITIES FOR FILTER
// =============================================

$cities = [];
try {
    $sql = "SELECT DISTINCT city FROM suppliers WHERE approval_status = 'approved' AND status = 'active' AND city IS NOT NULL AND city != '' ORDER BY city ASC";
    $result = $db->query($sql);
    if ($result && $result->rowCount() > 0) {
        $cities = $result->fetchAll();
    }
} catch (PDOException $e) {
    $cities = [];
}

// =============================================
// HELPER FUNCTIONS
// =============================================

if (!function_exists('formatDate')) {
    function formatDate($date) {
        if (empty($date)) return '-';
        return date('M d, Y', strtotime($date));
    }
}

if (!function_exists('truncateText')) {
    function truncateText($text, $length = 100) {
        if (empty($text)) return '';
        if (strlen($text) <= $length) return $text;
        return substr($text, 0, $length) . '...';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?php echo $meta_description; ?>">
    <meta name="keywords" content="IT suppliers Tanzania, verified suppliers, B2B procurement, IT equipment suppliers">
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
        
        /* Filter Sidebar */
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
        
        /* Supplier Card */
        .supplier-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            transition: all 0.3s ease;
            margin-bottom: 24px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            border: 1px solid #e9ecef;
        }
        
        .supplier-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            border-color: #0d6efd;
        }
        
        .supplier-logo {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: #e9ecef;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            color: #6c757d;
            flex-shrink: 0;
        }
        
        .supplier-rating {
            color: #ffc107;
        }
        
        .verification-badge {
            color: #198754;
        }
        
        .supplier-stats {
            font-size: 0.85rem;
        }
        
        .supplier-stats i {
            width: 18px;
        }
        
        /* No Results */
        .no-results {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 12px;
        }
        
        /* Pagination */
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
            .filter-sidebar {
                position: static;
                margin-bottom: 20px;
            }
            .page-header h1 {
                font-size: 1.8rem;
            }
            .supplier-logo {
                width: 60px;
                height: 60px;
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body>


<!-- Page Header -->
<section class="page-header">
    <div class="container">
        <h1><i class="fas fa-truck me-3"></i>Verified IT Suppliers</h1>
        <p class="lead mb-0">Find trusted IT equipment suppliers across Tanzania</p>
    </div>
</section>

<!-- Main Content -->
<div class="container py-5">
    <div class="row">
        <!-- Filter Sidebar -->
        <div class="col-lg-3 mb-4 mb-lg-0">
            <div class="filter-sidebar">
                <h5 class="mb-3"><i class="fas fa-filter me-2"></i>Filter Suppliers</h5>
                
                <form method="GET" action="suppliers.php" id="filterForm">
                    <!-- Search -->
                    <div class="filter-section">
                        <div class="filter-title">Search</div>
                        <div class="input-group">
                            <input type="text" name="search" class="form-control" placeholder="Search suppliers..." value="<?php echo htmlspecialchars($search); ?>">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search"></i>
                            </button>
                        </div>
                    </div>
                    
                    <!-- City -->
                    <div class="filter-section">
                        <div class="filter-title">City</div>
                        <select name="city" class="form-select" onchange="this.form.submit()">
                            <option value="">All Cities</option>
                            <?php foreach($cities as $c): ?>
                                <option value="<?php echo htmlspecialchars($c['city']); ?>" <?php echo $city == $c['city'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($c['city']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <!-- Rating -->
                    <div class="filter-section">
                        <div class="filter-title">Minimum Rating</div>
                        <select name="rating" class="form-select" onchange="this.form.submit()">
                            <option value="0">All Ratings</option>
                            <option value="1" <?php echo $rating == 1 ? 'selected' : ''; ?>>1 Star & Up</option>
                            <option value="2" <?php echo $rating == 2 ? 'selected' : ''; ?>>2 Stars & Up</option>
                            <option value="3" <?php echo $rating == 3 ? 'selected' : ''; ?>>3 Stars & Up</option>
                            <option value="4" <?php echo $rating == 4 ? 'selected' : ''; ?>>4 Stars & Up</option>
                            <option value="5" <?php echo $rating == 5 ? 'selected' : ''; ?>>5 Stars</option>
                        </select>
                    </div>
                    
                    <!-- Reset Filters -->
                    <?php if(!empty($search) || !empty($city) || $rating > 0): ?>
                    <div class="mt-3">
                        <a href="suppliers.php" class="btn btn-secondary w-100">
                            <i class="fas fa-undo me-1"></i>Reset Filters
                        </a>
                    </div>
                    <?php endif; ?>
                </form>
            </div>
            
            <!-- Why Partner -->
            <div class="card shadow-sm mt-4">
                <div class="card-body">
                    <h6><i class="fas fa-handshake me-2 text-primary"></i>Why Partner with Us?</h6>
                    <ul class="small text-muted ps-3">
                        <li class="mb-2">✓ Verified & vetted suppliers</li>
                        <li class="mb-2">✓ Escrow payment protection</li>
                        <li class="mb-2">✓ Competitive pricing</li>
                        <li class="mb-2">✓ Quality assurance</li>
                        <li>✓ Fast delivery across Tanzania</li>
                    </ul>
                </div>
            </div>
        </div>
        
        <!-- Suppliers Grid -->
        <div class="col-lg-9">
            <!-- Results Info -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <span class="text-muted">Showing <?php echo count($suppliers); ?> of <?php echo $total_suppliers; ?> suppliers</span>
                </div>
            </div>
            
            <!-- Suppliers -->
            <?php if (!empty($suppliers)): ?>
                <?php foreach($suppliers as $supplier): ?>
                <div class="supplier-card" data-aos="fade-up">
                    <div class="row align-items-center">
                        <div class="col-md-2 text-center">
                            <div class="supplier-logo mx-auto">
                                <i class="fas fa-building"></i>
                            </div>
                        </div>
                        <div class="col-md-7">
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <h5 class="mb-0"><?php echo htmlspecialchars($supplier['company_name']); ?></h5>
                                <?php if($supplier['verification_badge']): ?>
                                    <span class="verification-badge" title="Verified Supplier">
                                        <i class="fas fa-check-circle"></i>
                                    </span>
                                <?php endif; ?>
                            </div>
                            <p class="text-muted small mb-2">
                                <i class="fas fa-map-marker-alt me-1"></i>
                                <?php echo htmlspecialchars($supplier['city'] ?? 'N/A'); ?>, <?php echo htmlspecialchars($supplier['region'] ?? 'Tanzania'); ?>
                            </p>
                            <div class="supplier-stats">
                                <div class="mb-1">
                                    <span class="supplier-rating">
                                        <?php 
                                        $rating = round($supplier['rating'] ?? 0);
                                        for($i = 1; $i <= 5; $i++): 
                                        ?>
                                            <i class="fas fa-star <?php echo $i <= $rating ? 'text-warning' : 'text-secondary'; ?>"></i>
                                        <?php endfor; ?>
                                    </span>
                                    <span class="text-muted ms-2">(<?php echo number_format($supplier['total_reviews'] ?? 0); ?> reviews)</span>
                                </div>
                                <div>
                                    <span class="text-muted me-3">
                                        <i class="fas fa-shopping-cart me-1"></i>
                                        <?php echo number_format($supplier['total_sales'] ?? 0); ?> sales
                                    </span>
                                    <span class="text-muted">
                                        <i class="fas fa-calendar-alt me-1"></i>
                                        Joined <?php echo formatDate($supplier['created_at']); ?>
                                    </span>
                                </div>
                            </div>
                            <?php if($supplier['business_description']): ?>
                                <p class="text-muted small mb-0 mt-2">
                                    <?php echo truncateText($supplier['business_description'], 120); ?>
                                </p>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-3 text-md-end">
                            <a href="supplier-profile.php?id=<?php echo $supplier['id']; ?>" class="btn btn-primary w-100 mb-2">
                                <i class="fas fa-eye me-1"></i>View Profile
                            </a>
                            <a href="products.php?supplier=<?php echo $supplier['id']; ?>" class="btn btn-outline-secondary w-100">
                                <i class="fas fa-box me-1"></i>View Products
                            </a>
                        </div>
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
            <!-- No Results -->
            <div class="no-results">
                <i class="fas fa-truck fa-4x text-muted mb-3"></i>
                <h4>No suppliers found</h4>
                <p class="text-muted">We couldn't find any suppliers matching your criteria.</p>
                <a href="suppliers.php" class="btn btn-primary mt-3">
                    <i class="fas fa-undo me-2"></i>Clear Filters
                </a>
                <div class="mt-3">
                    <p class="text-muted small">Want to become a supplier?</p>
                    <a href="auth/register.php?type=supplier" class="btn btn-outline-success">
                        <i class="fas fa-store me-1"></i>Register as Supplier
                    </a>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- Become a Supplier CTA -->
<section class="bg-primary text-white py-5">
    <div class="container text-center">
        <h3 class="mb-3">Are You an IT Equipment Supplier?</h3>
        <p class="mb-4">Join TechProcure and connect with thousands of businesses in Tanzania</p>
        <a href="auth/register.php?type=supplier" class="btn btn-light btn-lg">
            <i class="fas fa-store me-2"></i>Register as Supplier
        </a>
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
</script>

</body>
</html>