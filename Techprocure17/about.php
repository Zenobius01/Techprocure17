<?php
/**
 * TechProcure Tanzania - About Us Page
 * File: about.php
 * Description: Company information, mission, vision, and team
 */

// Start session if not started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include configuration files
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';

$page_title = 'About Us - TechProcure Tanzania';
$meta_description = 'Learn about TechProcure Tanzania, the leading B2B IT equipment procurement platform connecting businesses with verified suppliers across Tanzania.';

// Get database connection
$db = getDB();

// Get stats for about page
$stats = [];

// Total products
try {
    $stmt = $db->query("SELECT COUNT(*) as total FROM products WHERE approval_status = 'approved' AND status = 'active'");
    $stats['total_products'] = $stmt->fetchColumn();
} catch (PDOException $e) {
    $stats['total_products'] = 0;
}

// Total suppliers
try {
    $stmt = $db->query("SELECT COUNT(*) as total FROM suppliers WHERE approval_status = 'approved' AND status = 'active'");
    $stats['total_suppliers'] = $stmt->fetchColumn();
} catch (PDOException $e) {
    $stats['total_suppliers'] = 0;
}

// Total customers
try {
    $stmt = $db->query("SELECT COUNT(*) as total FROM users WHERE user_type = 'customer' AND status = 'active'");
    $stats['total_customers'] = $stmt->fetchColumn();
} catch (PDOException $e) {
    $stats['total_customers'] = 0;
}

// Total orders
try {
    $stmt = $db->query("SELECT COUNT(*) as total FROM orders WHERE payment_status = 'paid'");
    $stats['total_orders'] = $stmt->fetchColumn();
} catch (PDOException $e) {
    $stats['total_orders'] = 0;
}

// Get team members (if team table exists)
$team_members = [];
try {
    $table_check = $db->query("SHOW TABLES LIKE 'team_members'");
    if ($table_check->rowCount() > 0) {
        $stmt = $db->query("SELECT * FROM team_members WHERE status = 'active' ORDER BY sort_order LIMIT 6");
        if ($stmt->rowCount() > 0) {
            $team_members = $stmt->fetchAll();
        }
    }
} catch (PDOException $e) {
    $team_members = [];
}

// Get testimonials
$testimonials = [];
try {
    $table_check = $db->query("SHOW TABLES LIKE 'testimonials'");
    if ($table_check->rowCount() > 0) {
        $stmt = $db->query("SELECT * FROM testimonials WHERE status = 'active' ORDER BY sort_order LIMIT 3");
        if ($stmt->rowCount() > 0) {
            $testimonials = $stmt->fetchAll();
        }
    }
} catch (PDOException $e) {
    $testimonials = [];
}

// Helper functions
if (!function_exists('formatDate')) {
    function formatDate($date) {
        if (empty($date)) return '-';
        return date('M d, Y', strtotime($date));
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?php echo $meta_description; ?>">
    <meta name="keywords" content="About TechProcure, B2B procurement Tanzania, IT equipment supplier, enterprise technology">
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
        
        /* About Section */
        .about-section {
            padding: 60px 0;
        }
        
        .about-image {
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        
        .about-image img {
            width: 100%;
            height: 350px;
            object-fit: cover;
        }
        
        /* Mission Vision */
        .mission-card {
            background: white;
            border-radius: 15px;
            padding: 30px;
            text-align: center;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
            transition: all 0.3s;
            height: 100%;
        }
        
        .mission-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        
        .mission-card .icon {
            font-size: 3rem;
            margin-bottom: 15px;
        }
        
        /* Stats Section */
        .stats-section {
            background: linear-gradient(135deg, #1a2a6c, #b21f1f, #fdbb4d);
            color: white;
            padding: 60px 0;
        }
        
        .stat-number {
            font-size: 2.5rem;
            font-weight: 800;
        }
        
        .stat-label {
            font-size: 1rem;
            opacity: 0.8;
        }
        
        /* Team Section */
        .team-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            text-align: center;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
            transition: all 0.3s;
        }
        
        .team-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        
        .team-card .team-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            margin: 0 auto 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            font-weight: 600;
            color: white;
        }
        
        .team-card .team-name {
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .team-card .team-role {
            color: #6c757d;
            font-size: 0.9rem;
        }
        
        /* Values Section */
        .value-card {
            text-align: center;
            padding: 25px;
            border-radius: 12px;
            background: white;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
            transition: all 0.3s;
            height: 100%;
        }
        
        .value-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        
        .value-card .value-icon {
            font-size: 2.5rem;
            margin-bottom: 15px;
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
            .stat-number {
                font-size: 2rem;
            }
            .about-image img {
                height: 200px;
            }
        }
    </style>
</head>
<body>


<!-- Page Header -->
<section class="page-header">
    <div class="container">
        <h1><i class="fas fa-info-circle me-3"></i>About TechProcure Tanzania</h1>
        <p class="lead mb-0">Transforming enterprise IT procurement in Tanzania</p>
    </div>
</section>

<!-- About Section -->
<section class="about-section">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-6" data-aos="fade-right">
                <h2 class="mb-3">Who We Are</h2>
                <p class="lead">TechProcure Tanzania is the leading B2B IT equipment procurement platform in Tanzania.</p>
                <p>We connect businesses with verified IT suppliers, providing a transparent, efficient, and cost-effective marketplace for enterprise technology procurement.</p>
                <p>Our platform streamlines the entire procurement process - from searching and comparing products to secure payments and delivery tracking.</p>
                <div class="row mt-4">
                    <div class="col-sm-6 mb-3">
                        <div class="d-flex align-items-center">
                            <i class="fas fa-check-circle text-success me-2"></i>
                            <span>Verified Suppliers</span>
                        </div>
                    </div>
                    <div class="col-sm-6 mb-3">
                        <div class="d-flex align-items-center">
                            <i class="fas fa-check-circle text-success me-2"></i>
                            <span>Secure Payments</span>
                        </div>
                    </div>
                    <div class="col-sm-6 mb-3">
                        <div class="d-flex align-items-center">
                            <i class="fas fa-check-circle text-success me-2"></i>
                            <span>Bulk Discounts</span>
                        </div>
                    </div>
                    <div class="col-sm-6 mb-3">
                        <div class="d-flex align-items-center">
                            <i class="fas fa-check-circle text-success me-2"></i>
                            <span>24/7 Support</span>
                        </div>
                    </div>
                </div>
                <a href="products.php" class="btn btn-primary btn-lg mt-3">
                    <i class="fas fa-shopping-cart me-2"></i>Start Shopping
                </a>
            </div>
            <div class="col-lg-6" data-aos="fade-left">
                <div class="about-image">
                    <div class="bg-primary d-flex align-items-center justify-content-center" style="height: 350px; border-radius: 15px;">
                        <i class="fas fa-microchip fa-8x text-white opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Mission & Vision -->
<section class="py-5 bg-light">
    <div class="container">
        <div class="row g-4">
            <div class="col-md-4" data-aos="fade-up" data-aos-delay="100">
                <div class="mission-card">
                    <div class="icon text-primary">
                        <i class="fas fa-bullseye"></i>
                    </div>
                    <h4>Our Mission</h4>
                    <p class="text-muted">To revolutionize enterprise technology procurement by providing a transparent, efficient, and cost-effective B2B marketplace that connects corporate IT departments with verified suppliers.</p>
                </div>
            </div>
            <div class="col-md-4" data-aos="fade-up" data-aos-delay="200">
                <div class="mission-card">
                    <div class="icon text-success">
                        <i class="fas fa-eye"></i>
                    </div>
                    <h4>Our Vision</h4>
                    <p class="text-muted">To become the leading B2B IT procurement platform in East Africa, enabling businesses of all sizes to acquire enterprise technology with confidence, transparency, and optimal pricing.</p>
                </div>
            </div>
            <div class="col-md-4" data-aos="fade-up" data-aos-delay="300">
                <div class="mission-card">
                    <div class="icon text-warning">
                        <i class="fas fa-handshake"></i>
                    </div>
                    <h4>Our Promise</h4>
                    <p class="text-muted">We are committed to providing a secure, reliable, and user-friendly platform that makes IT procurement simple, transparent, and cost-effective for businesses across Tanzania.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Core Values -->
<section class="py-5">
    <div class="container">
        <div class="text-center mb-5" data-aos="fade-up">
            <h2 class="display-5 fw-bold">Our Core Values</h2>
            <p class="lead text-muted">The principles that guide everything we do</p>
        </div>
        <div class="row g-4">
            <div class="col-md-4" data-aos="fade-up" data-aos-delay="100">
                <div class="value-card">
                    <div class="value-icon text-primary">
                        <i class="fas fa-shield-alt"></i>
                    </div>
                    <h5>Trust & Transparency</h5>
                    <p class="text-muted">We believe in complete transparency in pricing, specifications, and supplier verification to build lasting trust with our customers.</p>
                </div>
            </div>
            <div class="col-md-4" data-aos="fade-up" data-aos-delay="200">
                <div class="value-card">
                    <div class="value-icon text-success">
                        <i class="fas fa-people-arrows"></i>
                    </div>
                    <h5>Customer First</h5>
                    <p class="text-muted">Our customers are at the heart of everything we do. We are committed to delivering exceptional value and service.</p>
                </div>
            </div>
            <div class="col-md-4" data-aos="fade-up" data-aos-delay="300">
                <div class="value-card">
                    <div class="value-icon text-warning">
                        <i class="fas fa-lightbulb"></i>
                    </div>
                    <h5>Innovation</h5>
                    <p class="text-muted">We continuously innovate to provide better solutions, streamline processes, and enhance the procurement experience.</p>
                </div>
            </div>
            <div class="col-md-4" data-aos="fade-up" data-aos-delay="400">
                <div class="value-card">
                    <div class="value-icon text-info">
                        <i class="fas fa-handshake"></i>
                    </div>
                    <h5>Integrity</h5>
                    <p class="text-muted">We conduct our business with the highest ethical standards, honesty, and accountability.</p>
                </div>
            </div>
            <div class="col-md-4" data-aos="fade-up" data-aos-delay="500">
                <div class="value-card">
                    <div class="value-icon text-danger">
                        <i class="fas fa-rocket"></i>
                    </div>
                    <h5>Excellence</h5>
                    <p class="text-muted">We strive for excellence in everything we do, from our platform features to our customer support.</p>
                </div>
            </div>
            <div class="col-md-4" data-aos="fade-up" data-aos-delay="600">
                <div class="value-card">
                    <div class="value-icon text-secondary">
                        <i class="fas fa-globe-africa"></i>
                    </div>
                    <h5>Local Impact</h5>
                    <p class="text-muted">We are proud to support the Tanzanian business community and contribute to the growth of the local economy.</p>
                </div>
            </div>
        </div>
    </div>
</section>



<!-- Team Section -->
<?php if (!empty($team_members)): ?>
<section class="py-5">
    <div class="container">
        <div class="text-center mb-5" data-aos="fade-up">
            <h2 class="display-5 fw-bold">Meet Our Team</h2>
            <p class="lead text-muted">The passionate people behind TechProcure</p>
        </div>
        <div class="row g-4">
            <?php foreach($team_members as $index => $member): ?>
            <div class="col-md-4" data-aos="fade-up" data-aos-delay="<?php echo $index * 100; ?>">
                <div class="team-card">
                    <div class="team-avatar" style="background: <?php echo $member['avatar_color'] ?? '#0d6efd'; ?>">
                        <?php echo strtoupper(substr($member['name'], 0, 1)); ?>
                    </div>
                    <h5 class="team-name"><?php echo htmlspecialchars($member['name']); ?></h5>
                    <div class="team-role"><?php echo htmlspecialchars($member['role']); ?></div>
                    <p class="text-muted small mt-2"><?php echo htmlspecialchars($member['bio'] ?? ''); ?></p>
                    <?php if($member['email']): ?>
                        <a href="mailto:<?php echo htmlspecialchars($member['email']); ?>" class="text-muted small">
                            <i class="fas fa-envelope me-1"></i> <?php echo htmlspecialchars($member['email']); ?>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- Testimonials -->
<?php if (!empty($testimonials)): ?>
<section class="py-5 bg-light">
    <div class="container">
        <div class="text-center mb-5" data-aos="fade-up">
            <h2 class="display-5 fw-bold">What Our Clients Say</h2>
            <p class="lead text-muted">Feedback from businesses we've served</p>
        </div>
        <div class="row g-4">
            <?php foreach($testimonials as $index => $testimonial): ?>
            <div class="col-md-4" data-aos="fade-up" data-aos-delay="<?php echo $index * 100; ?>">
                <div class="testimonial-card bg-white p-4 rounded-3 shadow-sm h-100">
                    <div class="mb-3">
                        <i class="fas fa-quote-left fa-2x text-primary opacity-25"></i>
                    </div>
                    <p class="mb-3">"<?php echo htmlspecialchars($testimonial['content']); ?>"</p>
                    <div class="d-flex align-items-center">
                        <div class="bg-secondary rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 50px; height: 50px; color: white; font-weight: 600;">
                            <?php echo strtoupper(substr($testimonial['name'], 0, 1)); ?>
                        </div>
                        <div>
                            <h6 class="mb-0"><?php echo htmlspecialchars($testimonial['name']); ?></h6>
                            <small class="text-muted"><?php echo htmlspecialchars($testimonial['position']); ?></small>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- CTA Section -->
<section class="py-5" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
    <div class="container text-center" data-aos="fade-up">
        <h2 class="display-5 fw-bold text-white mb-3">Ready to Transform Your IT Procurement?</h2>
        <p class="lead text-white-50 mb-4">Join thousands of businesses already using TechProcure</p>
        <a href="auth/register.php" class="btn btn-light btn-lg px-5">
            <i class="fas fa-user-plus me-2"></i>Get Started Today
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
    
    // Initialize cart count
    updateCartCount();
</script>

</body>
</html>