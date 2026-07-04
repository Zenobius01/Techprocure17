<?php
/**
 * TechProcure Tanzania - Request Quotation Page
 * File: customer/quotations/request-quotation.php
 * Description: Customer can request quotations from suppliers for bulk orders
 */

// Start session if not started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include configuration files
require_once '../../includes/config.php';
require_once '../../includes/db.php';
require_once '../../includes/functions.php';


// Check if user is logged in and is customer
requireCustomer();

// Get database connection
$db = getDB();
$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'] ?? 'Customer';

// =============================================
// PROCESS QUOTATION REQUEST
// =============================================

$error = '';
$success = '';
$quotation_number = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        $error = 'Security token validation failed. Please try again.';
    } else {
        $title = sanitizeInput($_POST['title'] ?? '');
        $description = sanitizeInput($_POST['description'] ?? '');
        $product_name = sanitizeInput($_POST['product_name'] ?? '');
        $quantity = (int)($_POST['quantity'] ?? 0);
        $category_id = (int)($_POST['category_id'] ?? 0);
        $budget_min = (float)($_POST['budget_min'] ?? 0);
        $budget_max = (float)($_POST['budget_max'] ?? 0);
        $delivery_location = sanitizeInput($_POST['delivery_location'] ?? '');
        $delivery_deadline = sanitizeInput($_POST['delivery_deadline'] ?? '');
        $payment_terms = sanitizeInput($_POST['payment_terms'] ?? '');
        $special_requirements = sanitizeInput($_POST['special_requirements'] ?? '');
        
        // Validation
        if (empty($title) || empty($description) || empty($product_name) || $quantity <= 0) {
            $error = 'Please fill in all required fields.';
        } elseif ($category_id <= 0) {
            $error = 'Please select a category.';
        } elseif (!empty($budget_min) && !empty($budget_max) && $budget_min > $budget_max) {
            $error = 'Minimum budget cannot be greater than maximum budget.';
        } else {
            try {
                // Generate quotation number
                $quotation_number = 'QTN-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
                
                // Insert quotation
                $sql = "INSERT INTO quotations (
                            quotation_number, 
                            user_id, 
                            title, 
                            description, 
                            products,
                            delivery_location, 
                            delivery_deadline, 
                            budget_min, 
                            budget_max, 
                            payment_terms, 
                            special_requirements, 
                            status, 
                            created_at
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'open', NOW())";
                
                $products_json = json_encode([
                    'name' => $product_name,
                    'quantity' => $quantity,
                    'category_id' => $category_id
                ]);
                
                $stmt = $db->prepare($sql);
                $stmt->execute([
                    $quotation_number,
                    $user_id,
                    $title,
                    $description,
                    $products_json,
                    $delivery_location,
                    $delivery_deadline,
                    $budget_min,
                    $budget_max,
                    $payment_terms,
                    $special_requirements
                ]);
                
                $quotation_id = $db->lastInsertId();
                
                // Log activity
                try {
                    $log_sql = "INSERT INTO activity_logs (user_id, action, entity_type, entity_id, ip_address, user_agent, created_at) 
                                VALUES (?, 'Requested Quotation', 'quotation', ?, ?, ?, NOW())";
                    $log_stmt = $db->prepare($log_sql);
                    $log_stmt->execute([$user_id, $quotation_id, $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '']);
                } catch (PDOException $e) {
                    // Continue even if logging fails
                }
                
                $success = 'Your quotation request has been submitted successfully! Quotation number: ' . $quotation_number;
                
                // Clear form
                $_POST = array();
                
            } catch (PDOException $e) {
                $error = 'Failed to submit quotation request. Please try again.';
            }
        }
    }
}

// =============================================
// GET CATEGORIES FOR DROPDOWN
// =============================================

$categories = [];
try {
    $stmt = $db->query("SELECT * FROM categories WHERE status = 'active' ORDER BY name");
    if ($stmt->rowCount() > 0) {
        $categories = $stmt->fetchAll();
    }
} catch (PDOException $e) {
    $categories = [];
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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Request Quotation - TechProcure Tanzania</title>
    
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
        
        /* Quotation Content */
        .quotation-content {
            flex: 1;
        }
        
        /* Form Styles */
        .form-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        
        .form-card .card-title {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #e9ecef;
        }
        
        .form-card .card-title i {
            margin-right: 8px;
            color: #0d6efd;
        }
        
        .form-control {
            border-radius: 10px;
            padding: 10px 15px;
            border: 1px solid #e0e0e0;
            transition: all 0.3s;
        }
        
        .form-control:focus {
            border-color: #0d6efd;
            box-shadow: 0 0 0 3px rgba(13,110,253,0.1);
            outline: none;
        }
        
        .form-label {
            font-weight: 500;
            font-size: 0.85rem;
        }
        
        .required:after {
            content: " *";
            color: red;
        }
        
        .btn-submit {
            background: linear-gradient(135deg, #0d6efd, #0b5ed7);
            border: none;
            border-radius: 10px;
            padding: 12px 30px;
            font-weight: 600;
            color: white;
            transition: all 0.3s;
        }
        
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(13,110,253,0.3);
        }
        
        .btn-submit:disabled {
            opacity: 0.7;
            cursor: not-allowed;
            transform: none;
        }
        
        .alert {
            border-radius: 10px;
        }
        
        /* Info Box */
        .info-box {
            background: #f0f7ff;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            border-left: 4px solid #0d6efd;
        }
        
        .info-box h6 {
            color: #0d6efd;
            font-weight: 600;
        }
        
        .info-box ul {
            margin-bottom: 0;
            padding-left: 20px;
        }
        
        .info-box ul li {
            margin-bottom: 5px;
            font-size: 0.9rem;
        }
        
        /* Success Box */
        .success-box {
            background: #d4edda;
            border-radius: 12px;
            padding: 30px;
            text-align: center;
            border-left: 4px solid #198754;
        }
        
        .success-box i {
            font-size: 3rem;
            color: #198754;
            margin-bottom: 15px;
        }
        
        .success-box h4 {
            color: #155724;
        }
        
        .success-box p {
            color: #155724;
        }
        
        .quotation-number-box {
            background: white;
            border-radius: 8px;
            padding: 15px;
            display: inline-block;
            margin: 15px 0;
        }
        
        .quotation-number-box code {
            font-size: 1.2rem;
            font-weight: 600;
            color: #0d6efd;
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
        }
    </style>
</head>
<body>

<!-- Navbar -->
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
                    <a class="nav-link" href="../dashboard.php">
                        <i class="fas fa-tachometer-alt"></i> Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="../profile.php">
                        <i class="fas fa-user"></i> My Profile
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="../orders/my-orders.php">
                        <i class="fas fa-shopping-bag"></i> My Orders
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="../wishlist.php">
                        <i class="fas fa-heart"></i> Wishlist
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="request-quotation.php">
                        <i class="fas fa-file-alt"></i> Request Quotation
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="my-quotations.php">
                        <i class="fas fa-list"></i> My Quotations
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="../invoices/my-invoices.php">
                        <i class="fas fa-file-invoice"></i> Invoices
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
        
        <!-- Quotation Content -->
        <div class="quotation-content">
            <!-- Page Title -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h4 class="mb-0"><i class="fas fa-file-alt me-2 text-primary"></i>Request Quotation</h4>
                    <p class="text-muted">Get competitive quotes from multiple suppliers</p>
                </div>
                <span class="badge bg-primary">RFQ</span>
            </div>
            
            <!-- Success/Error Messages -->
            <?php if($success): ?>
                <div class="success-box">
                    <i class="fas fa-check-circle"></i>
                    <h4>Quotation Request Submitted!</h4>
                    <p><?php echo $success; ?></p>
                    <div class="quotation-number-box">
                        <code><?php echo $quotation_number; ?></code>
                    </div>
                    <div class="mt-3">
                        <a href="my-quotations.php" class="btn btn-primary">
                            <i class="fas fa-list me-2"></i> View My Quotations
                        </a>
                        <a href="request-quotation.php" class="btn btn-outline-primary">
                            <i class="fas fa-plus me-2"></i> New Request
                        </a>
                    </div>
                </div>
            <?php endif; ?>
            
            <?php if($error): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <i class="fas fa-exclamation-triangle me-2"></i> <?php echo $error; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if(!$success): ?>
            
            <!-- Info Box -->
            <div class="info-box">
                <h6><i class="fas fa-info-circle me-2"></i>How It Works</h6>
                <ul>
                    <li>Fill in the details of the product you need</li>
                    <li>Multiple suppliers will review and send you quotes</li>
                    <li>Compare quotes and choose the best offer</li>
                    <li>You can accept or reject quotes</li>
                </ul>
            </div>
            
            <!-- Quotation Form -->
            <div class="form-card">
                <div class="card-title">
                    <i class="fas fa-pencil-alt"></i> Quotation Request Form
                </div>
                
                <form method="POST" action="" id="quotationForm">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    
                    <!-- Title -->
                    <div class="mb-3">
                        <label class="form-label required">Request Title</label>
                        <input type="text" name="title" class="form-control" 
                               placeholder="e.g., Bulk Laptop Purchase for Office" 
                               value="<?php echo htmlspecialchars($_POST['title'] ?? ''); ?>" required>
                    </div>
                    
                    <!-- Description -->
                    <div class="mb-3">
                        <label class="form-label required">Description</label>
                        <textarea name="description" class="form-control" rows="3" 
                                  placeholder="Describe your requirements in detail..." required><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                    </div>
                    
                    <!-- Product Details -->
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label required">Product Name</label>
                            <input type="text" name="product_name" class="form-control" 
                                   placeholder="e.g., HP Laptop" 
                                   value="<?php echo htmlspecialchars($_POST['product_name'] ?? ''); ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label required">Quantity</label>
                            <input type="number" name="quantity" class="form-control" 
                                   placeholder="e.g., 50" 
                                   value="<?php echo htmlspecialchars($_POST['quantity'] ?? ''); ?>" required>
                        </div>
                    </div>
                    
                    <!-- Category -->
                    <div class="mb-3">
                        <label class="form-label required">Category</label>
                        <select name="category_id" class="form-select" required>
                            <option value="">Select Category</option>
                            <?php foreach($categories as $cat): ?>
                                <option value="<?php echo $cat['id']; ?>" <?php echo (($_POST['category_id'] ?? '') == $cat['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cat['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <!-- Budget Range -->
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Minimum Budget (TSh)</label>
                            <input type="number" name="budget_min" class="form-control" 
                                   placeholder="e.g., 1000000" 
                                   value="<?php echo htmlspecialchars($_POST['budget_min'] ?? ''); ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Maximum Budget (TSh)</label>
                            <input type="number" name="budget_max" class="form-control" 
                                   placeholder="e.g., 2000000" 
                                   value="<?php echo htmlspecialchars($_POST['budget_max'] ?? ''); ?>">
                        </div>
                    </div>
                    
                    <!-- Delivery Details -->
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Delivery Location</label>
                            <input type="text" name="delivery_location" class="form-control" 
                                   placeholder="e.g., Dar es Salaam" 
                                   value="<?php echo htmlspecialchars($_POST['delivery_location'] ?? ''); ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Delivery Deadline</label>
                            <input type="date" name="delivery_deadline" class="form-control" 
                                   value="<?php echo htmlspecialchars($_POST['delivery_deadline'] ?? ''); ?>">
                        </div>
                    </div>
                    
                    <!-- Payment Terms -->
                    <div class="mb-3">
                        <label class="form-label">Payment Terms</label>
                        <select name="payment_terms" class="form-select">
                            <option value="">Select Payment Terms</option>
                            <option value="NET30" <?php echo (($_POST['payment_terms'] ?? '') == 'NET30') ? 'selected' : ''; ?>>NET30 (30 days)</option>
                            <option value="NET45" <?php echo (($_POST['payment_terms'] ?? '') == 'NET45') ? 'selected' : ''; ?>>NET45 (45 days)</option>
                            <option value="NET60" <?php echo (($_POST['payment_terms'] ?? '') == 'NET60') ? 'selected' : ''; ?>>NET60 (60 days)</option>
                            <option value="50% Advance" <?php echo (($_POST['payment_terms'] ?? '') == '50% Advance') ? 'selected' : ''; ?>>50% Advance + 50% on Delivery</option>
                            <option value="100% Advance" <?php echo (($_POST['payment_terms'] ?? '') == '100% Advance') ? 'selected' : ''; ?>>100% Advance</option>
                            <option value="On Delivery" <?php echo (($_POST['payment_terms'] ?? '') == 'On Delivery') ? 'selected' : ''; ?>>Payment on Delivery</option>
                            <option value="Other" <?php echo (($_POST['payment_terms'] ?? '') == 'Other') ? 'selected' : ''; ?>>Other</option>
                        </select>
                    </div>
                    
                    <!-- Special Requirements -->
                    <div class="mb-3">
                        <label class="form-label">Special Requirements</label>
                        <textarea name="special_requirements" class="form-control" rows="2" 
                                  placeholder="Any special requirements or specifications..."><?php echo htmlspecialchars($_POST['special_requirements'] ?? ''); ?></textarea>
                    </div>
                    
                    <!-- Submit Button -->
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn-submit" id="submitBtn">
                            <i class="fas fa-paper-plane me-2"></i> Submit Request
                        </button>
                        <a href="my-quotations.php" class="btn btn-outline-secondary">
                            <i class="fas fa-list me-2"></i> View My Quotations
                        </a>
                    </div>
                </form>
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
                    <li><a href="../../index.php">Home</a></li>
                    <li><a href="../../products.php">Products</a></li>
                    <li><a href="../../suppliers.php">Suppliers</a></li>
                </ul>
            </div>
            <div class="col-md-3 mb-4">
                <h6>My Account</h6>
                <ul class="list-unstyled">
                    <li><a href="../dashboard.php">Dashboard</a></li>
                    <li><a href="../profile.php">Profile</a></li>
                    <li><a href="../orders/my-orders.php">My Orders</a></li>
                    <li><a href="../wishlist.php">Wishlist</a></li>
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
    // Form submission loading state
    document.getElementById('quotationForm')?.addEventListener('submit', function(e) {
        const btn = document.getElementById('submitBtn');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status"></span> Submitting...';
    });
    
    // Auto-hide alerts after 5 seconds
    setTimeout(function() {
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(function(alert) {
            const bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        });
    }, 5000);
</script>

</body>
</html>