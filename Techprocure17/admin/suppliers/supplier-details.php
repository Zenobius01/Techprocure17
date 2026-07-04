<?php
/**
 * TechProcure Tanzania - Admin Supplier Details
 * File: admin/suppliers/supplier-details.php
 * Description: View detailed information about a specific supplier
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

// Get supplier ID from URL
$supplier_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($supplier_id <= 0) {
    $_SESSION['error'] = "Invalid supplier ID.";
    header("Location: manage-suppliers.php");
    exit();
}

// =============================================
// FETCH SUPPLIER DATA
// =============================================

$supplier = null;
$error = '';

try {
    // Get supplier details with user info
    $sql = "SELECT 
                s.*,
                u.full_name as contact_person,
                u.email,
                u.phone,
                u.address,
                u.city,
                u.region,
                u.postal_code,
                u.country,
                u.created_at as user_created_at,
                u.is_active as user_active,
                r.role_name
            FROM suppliers s
            LEFT JOIN users u ON s.user_id = u.id
            LEFT JOIN roles r ON u.role_id = r.id
            WHERE s.id = ?";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([$supplier_id]);
    $supplier = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$supplier) {
        $error = "Supplier not found.";
    }
    
} catch (PDOException $e) {
    $error = "Failed to load supplier details: " . $e->getMessage();
}

// =============================================
// FETCH SUPPLEMENTARY DATA
// =============================================

$products = [];
$recent_orders = [];
$total_products = 0;
$total_orders = 0;
$total_revenue = 0;
$average_rating = 0;
$total_reviews = 0;
$pending_products = 0;

if ($supplier) {
    try {
        // Get product statistics
        $product_stats = $db->prepare("
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) as inactive
            FROM products 
            WHERE supplier_id = ?
        ");
        $product_stats->execute([$supplier_id]);
        $stats = $product_stats->fetch(PDO::FETCH_ASSOC);
        
        $total_products = $stats['total'] ?? 0;
        $pending_products = $stats['pending'] ?? 0;
        
        // Get products
        $product_stmt = $db->prepare("
            SELECT 
                id,
                name,
                price,
                quantity,
                status,
                created_at,
                sku
            FROM products 
            WHERE supplier_id = ?
            ORDER BY created_at DESC
            LIMIT 10
        ");
        $product_stmt->execute([$supplier_id]);
        $products = $product_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get order statistics
        $order_stats = $db->prepare("
            SELECT 
                COUNT(DISTINCT o.id) as total_orders,
                SUM(oi.quantity * oi.price) as total_revenue
            FROM orders o
            JOIN order_items oi ON o.id = oi.order_id
            JOIN products p ON oi.product_id = p.id
            WHERE p.supplier_id = ?
        ");
        $order_stats->execute([$supplier_id]);
        $order_data = $order_stats->fetch(PDO::FETCH_ASSOC);
        
        $total_orders = $order_data['total_orders'] ?? 0;
        $total_revenue = $order_data['total_revenue'] ?? 0;
        
        // Get recent orders
        $order_stmt = $db->prepare("
            SELECT 
                DISTINCT o.id,
                o.order_number,
                o.total_amount,
                o.order_status,
                o.payment_status,
                o.created_at,
                u.full_name as customer_name
            FROM orders o
            JOIN order_items oi ON o.id = oi.order_id
            JOIN products p ON oi.product_id = p.id
            LEFT JOIN users u ON o.user_id = u.id
            WHERE p.supplier_id = ?
            ORDER BY o.created_at DESC
            LIMIT 10
        ");
        $order_stmt->execute([$supplier_id]);
        $recent_orders = $order_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get review statistics - FIXED: Use COALESCE to handle NULL values
        $review_stmt = $db->prepare("
            SELECT 
                COUNT(*) as total_reviews,
                COALESCE(AVG(rating), 0) as avg_rating
            FROM product_reviews pr
            JOIN products p ON pr.product_id = p.id
            WHERE p.supplier_id = ?
        ");
        $review_stmt->execute([$supplier_id]);
        $review_data = $review_stmt->fetch(PDO::FETCH_ASSOC);
        
        // Set values with defaults to prevent undefined array key warnings
        $total_reviews = $review_data['total_reviews'] ?? 0;
        $average_rating = $review_data['avg_rating'] ?? 0;
        
    } catch (PDOException $e) {
        $error = "Failed to load supplementary data: " . $e->getMessage();
    }
}

// =============================================
// PROCESS VERIFICATION ACTION
// =============================================

$verification_message = '';
$verification_error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $action = sanitizeInput($_POST['action']);
    $submitted_token = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
    
    if (empty($submitted_token) || !verifyCSRFToken($submitted_token)) {
        $verification_error = 'Security token validation failed. Please refresh and try again.';
    } else {
        if ($action === 'verify_supplier') {
            try {
                $update_stmt = $db->prepare("
                    UPDATE suppliers 
                    SET verification_status = 'verified',
                        verified_at = NOW(),
                        updated_at = NOW()
                    WHERE id = ?
                ");
                $update_stmt->execute([$supplier_id]);
                
                // Add notification for supplier
                try {
                    addNotification(
                        $supplier['user_id'],
                        'verification',
                        'Supplier Account Verified',
                        'Your supplier account has been verified. You can now list products and start selling.',
                        '../supplier/dashboard.php'
                    );
                } catch (Exception $e) {
                    // Skip notification if fails
                }
                
                $verification_message = "Supplier verified successfully!";
                
                // Refresh data
                $stmt = $db->prepare($sql);
                $stmt->execute([$supplier_id]);
                $supplier = $stmt->fetch(PDO::FETCH_ASSOC);
                
            } catch (Exception $e) {
                $verification_error = "Failed to verify supplier: " . $e->getMessage();
            }
            
        } elseif ($action === 'reject_supplier') {
            try {
                $update_stmt = $db->prepare("
                    UPDATE suppliers 
                    SET verification_status = 'rejected',
                        updated_at = NOW()
                    WHERE id = ?
                ");
                $update_stmt->execute([$supplier_id]);
                
                // Add notification for supplier
                try {
                    addNotification(
                        $supplier['user_id'],
                        'verification',
                        'Supplier Account Rejected',
                        'Your supplier account verification has been rejected. Please contact support for more information.',
                        '../supplier/dashboard.php'
                    );
                } catch (Exception $e) {
                    // Skip notification if fails
                }
                
                $verification_message = "Supplier rejected.";
                
                // Refresh data
                $stmt = $db->prepare($sql);
                $stmt->execute([$supplier_id]);
                $supplier = $stmt->fetch(PDO::FETCH_ASSOC);
                
            } catch (Exception $e) {
                $verification_error = "Failed to reject supplier: " . $e->getMessage();
            }
            
        } elseif ($action === 'toggle_status') {
            $new_status = sanitizeInput($_POST['status'] ?? '');
            
            if (in_array($new_status, ['active', 'inactive'])) {
                try {
                    $update_stmt = $db->prepare("
                        UPDATE users 
                        SET is_active = ?,
                            updated_at = NOW()
                        WHERE id = ?
                    ");
                    $update_stmt->execute([$new_status === 'active' ? 1 : 0, $supplier['user_id']]);
                    
                    $verification_message = "Supplier status updated to " . ucfirst($new_status);
                    
                    // Refresh data
                    $stmt = $db->prepare($sql);
                    $stmt->execute([$supplier_id]);
                    $supplier = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                } catch (Exception $e) {
                    $verification_error = "Failed to update status: " . $e->getMessage();
                }
            }
        }
    }
}

// Generate CSRF token
$csrf_token = generateCSRFToken();

// =============================================
// PAGE TITLE
// =============================================
$page_title = 'Supplier Details - TechProcure Tanzania';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo $csrf_token; ?>">
    <title><?php echo $page_title; ?></title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f8f9fa;
        }
        
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
        
        .details-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 24px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        
        .details-card .card-title {
            font-weight: 600;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #e9ecef;
        }
        
        .details-card .card-title i {
            margin-right: 8px;
            color: #0d6efd;
        }
        
        .stat-box {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 15px;
            text-align: center;
            transition: all 0.3s;
        }
        
        .stat-box:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .stat-box .stat-number {
            font-size: 1.8rem;
            font-weight: 700;
            color: #0d6efd;
        }
        
        .stat-box .stat-label {
            color: #6c757d;
            font-size: 0.85rem;
        }
        
        .verification-badge {
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: 500;
            display: inline-block;
        }
        
        .verification-badge.verified {
            background: #d4edda;
            color: #155724;
        }
        
        .verification-badge.pending {
            background: #fff3cd;
            color: #856404;
        }
        
        .verification-badge.unverified {
            background: #f8d7da;
            color: #721c24;
        }
        
        .verification-badge.rejected {
            background: #f8d7da;
            color: #721c24;
        }
        
        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .status-badge.active {
            background: #d4edda;
            color: #155724;
        }
        
        .status-badge.inactive {
            background: #f8d7da;
            color: #721c24;
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
        
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }
            .sidebar.show {
                transform: translateX(0);
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
            <a href="manage-suppliers.php" class="nav-link active">
                <i class="fas fa-truck"></i> Suppliers
            </a>
        </div>
        <div class="nav-item">
            <a href="../products/manage-products.php" class="nav-link">
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
                <i class="fas fa-cog"></i> System Settings
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
            <i class="fas fa-truck me-2 text-primary"></i> Supplier Details
        </div>
        <div class="d-flex align-items-center gap-3">
            <a href="manage-suppliers.php" class="btn btn-sm btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i> Back
            </a>
            <div class="admin-avatar">
                <?php echo strtoupper(substr($_SESSION['user_name'] ?? 'A', 0, 1)); ?>
            </div>
        </div>
    </div>
    
    <?php if($error): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-triangle me-2"></i> <?php echo $error; ?>
            <a href="manage-suppliers.php" class="btn btn-primary btn-sm mt-2">Back to Suppliers</a>
        </div>
    <?php elseif($supplier): ?>
    
    <!-- Verification Messages -->
    <?php if($verification_message): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="fas fa-check-circle me-2"></i> <?php echo $verification_message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <?php if($verification_error): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="fas fa-exclamation-triangle me-2"></i> <?php echo $verification_error; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <!-- Supplier Header -->
    <div class="details-card">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h4 class="mb-1"><?php echo htmlspecialchars($supplier['company_name'] ?? 'Unknown Company'); ?></h4>
                <p class="text-muted mb-0">
                    <i class="fas fa-user me-1"></i> <?php echo htmlspecialchars($supplier['contact_person'] ?? 'No contact person'); ?>
                    <span class="mx-2">|</span>
                    <i class="fas fa-envelope me-1"></i> <?php echo htmlspecialchars($supplier['email'] ?? 'No email'); ?>
                    <span class="mx-2">|</span>
                    <i class="fas fa-phone me-1"></i> <?php echo htmlspecialchars($supplier['phone'] ?? 'No phone'); ?>
                </p>
            </div>
            <div class="col-md-4 text-md-end">
                <span class="verification-badge <?php echo $supplier['verification_status'] ?? 'unverified'; ?>">
                    <?php echo ucfirst($supplier['verification_status'] ?? 'Unverified'); ?>
                </span>
                <span class="status-badge <?php echo ($supplier['user_active'] ?? 1) == 1 ? 'active' : 'inactive'; ?> ms-2">
                    <?php echo ($supplier['user_active'] ?? 1) == 1 ? 'Active' : 'Inactive'; ?>
                </span>
            </div>
        </div>
    </div>
    
    <!-- Statistics -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="stat-box">
                <div class="stat-number"><?php echo number_format($total_products); ?></div>
                <div class="stat-label">Total Products</div>
                <small class="text-muted"><?php echo number_format($pending_products); ?> pending</small>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-box">
                <div class="stat-number"><?php echo number_format($total_orders); ?></div>
                <div class="stat-label">Total Orders</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-box">
                <div class="stat-number">TSh <?php echo number_format($total_revenue, 0); ?></div>
                <div class="stat-label">Total Revenue</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-box">
                <div class="stat-number"><?php echo number_format($total_reviews); ?></div>
                <div class="stat-label">Reviews</div>
                <small class="text-muted">
                    Avg rating: <?php echo number_format($average_rating, 1); ?> / 5
                    <?php if ($average_rating > 0): ?>
                        <i class="fas fa-star text-warning ms-1"></i>
                    <?php endif; ?>
                </small>
            </div>
        </div>
    </div>
    
    <!-- Actions -->
    <div class="details-card">
        <div class="card-title">
            <i class="fas fa-cog"></i> Actions
        </div>
        <div class="row g-2">
            <div class="col-md-3">
                <form method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="action" value="verify_supplier">
                    <button type="submit" class="btn btn-success w-100" <?php echo ($supplier['verification_status'] ?? '') == 'verified' ? 'disabled' : ''; ?>>
                        <i class="fas fa-check-circle me-1"></i> Verify Supplier
                    </button>
                </form>
            </div>
            <div class="col-md-3">
                <form method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="action" value="reject_supplier">
                    <button type="submit" class="btn btn-danger w-100" <?php echo ($supplier['verification_status'] ?? '') == 'rejected' ? 'disabled' : ''; ?>>
                        <i class="fas fa-times-circle me-1"></i> Reject Supplier
                    </button>
                </form>
            </div>
            <div class="col-md-3">
                <form method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="action" value="toggle_status">
                    <input type="hidden" name="status" value="<?php echo ($supplier['user_active'] ?? 1) == 1 ? 'inactive' : 'active'; ?>">
                    <button type="submit" class="btn <?php echo ($supplier['user_active'] ?? 1) == 1 ? 'btn-warning' : 'btn-success'; ?> w-100">
                        <i class="fas <?php echo ($supplier['user_active'] ?? 1) == 1 ? 'fa-pause' : 'fa-play'; ?> me-1"></i>
                        <?php echo ($supplier['user_active'] ?? 1) == 1 ? 'Deactivate' : 'Activate'; ?>
                    </button>
                </form>
            </div>
            <div class="col-md-3">
                <a href="mailto:<?php echo htmlspecialchars($supplier['email'] ?? ''); ?>" class="btn btn-info w-100">
                    <i class="fas fa-envelope me-1"></i> Send Email
                </a>
            </div>
        </div>
    </div>
    
    <!-- Supplier Information -->
    <div class="row">
        <div class="col-md-6">
            <div class="details-card">
                <div class="card-title">
                    <i class="fas fa-info-circle"></i> Company Information
                </div>
                <div class="row mb-2">
                    <div class="col-4 text-muted">Company Name</div>
                    <div class="col-8 fw-semibold"><?php echo htmlspecialchars($supplier['company_name'] ?? 'N/A'); ?></div>
                </div>
                <div class="row mb-2">
                    <div class="col-4 text-muted">Business Type</div>
                    <div class="col-8"><?php echo ucwords(str_replace('_', ' ', $supplier['business_type'] ?? 'N/A')); ?></div>
                </div>
                <div class="row mb-2">
                    <div class="col-4 text-muted">TIN Number</div>
                    <div class="col-8"><?php echo htmlspecialchars($supplier['tin_number'] ?? 'N/A'); ?></div>
                </div>
                <div class="row mb-2">
                    <div class="col-4 text-muted">VAT Registered</div>
                    <div class="col-8">
                        <?php echo ($supplier['vat_registered'] ?? 0) ? '<span class="text-success">Yes</span>' : '<span class="text-muted">No</span>'; ?>
                    </div>
                </div>
                <div class="row mb-2">
                    <div class="col-4 text-muted">Joined</div>
                    <div class="col-8"><?php echo date('M d, Y', strtotime($supplier['user_created_at'] ?? 'now')); ?></div>
                </div>
                <?php if (!empty($supplier['website'])): ?>
                <div class="row mb-2">
                    <div class="col-4 text-muted">Website</div>
                    <div class="col-8">
                        <a href="<?php echo htmlspecialchars($supplier['website']); ?>" target="_blank">
                            <?php echo htmlspecialchars($supplier['website']); ?>
                        </a>
                    </div>
                </div>
                <?php endif; ?>
                <?php if (!empty($supplier['company_description'])): ?>
                <div class="row">
                    <div class="col-12">
                        <label class="text-muted">Company Description</label>
                        <p class="mt-1"><?php echo nl2br(htmlspecialchars($supplier['company_description'])); ?></p>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="details-card">
                <div class="card-title">
                    <i class="fas fa-address-book"></i> Contact Information
                </div>
                <div class="row mb-2">
                    <div class="col-4 text-muted">Contact Person</div>
                    <div class="col-8"><?php echo htmlspecialchars($supplier['contact_person'] ?? 'N/A'); ?></div>
                </div>
                <div class="row mb-2">
                    <div class="col-4 text-muted">Email</div>
                    <div class="col-8">
                        <a href="mailto:<?php echo htmlspecialchars($supplier['email'] ?? ''); ?>">
                            <?php echo htmlspecialchars($supplier['email'] ?? 'N/A'); ?>
                        </a>
                    </div>
                </div>
                <div class="row mb-2">
                    <div class="col-4 text-muted">Phone</div>
                    <div class="col-8"><?php echo htmlspecialchars($supplier['phone'] ?? 'N/A'); ?></div>
                </div>
                <div class="row mb-2">
                    <div class="col-4 text-muted">Address</div>
                    <div class="col-8"><?php echo htmlspecialchars($supplier['address'] ?? 'N/A'); ?></div>
                </div>
                <div class="row mb-2">
                    <div class="col-4 text-muted">City</div>
                    <div class="col-8"><?php echo htmlspecialchars($supplier['city'] ?? 'N/A'); ?></div>
                </div>
                <div class="row mb-2">
                    <div class="col-4 text-muted">Region</div>
                    <div class="col-8"><?php echo htmlspecialchars($supplier['region'] ?? 'N/A'); ?></div>
                </div>
                <div class="row mb-2">
                    <div class="col-4 text-muted">Country</div>
                    <div class="col-8"><?php echo htmlspecialchars($supplier['country'] ?? 'N/A'); ?></div>
                </div>
                <div class="row mb-2">
                    <div class="col-4 text-muted">Postal Code</div>
                    <div class="col-8"><?php echo htmlspecialchars($supplier['postal_code'] ?? 'N/A'); ?></div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Bank Details -->
    <?php if (!empty($supplier['bank_name']) || !empty($supplier['bank_account_name'])): ?>
    <div class="details-card">
        <div class="card-title">
            <i class="fas fa-university"></i> Bank Details
        </div>
        <div class="row">
            <?php if (!empty($supplier['bank_name'])): ?>
            <div class="col-md-3">
                <label class="text-muted">Bank Name</label>
                <p class="fw-semibold"><?php echo htmlspecialchars($supplier['bank_name']); ?></p>
            </div>
            <?php endif; ?>
            <?php if (!empty($supplier['bank_branch'])): ?>
            <div class="col-md-3">
                <label class="text-muted">Branch</label>
                <p class="fw-semibold"><?php echo htmlspecialchars($supplier['bank_branch']); ?></p>
            </div>
            <?php endif; ?>
            <?php if (!empty($supplier['bank_account_name'])): ?>
            <div class="col-md-3">
                <label class="text-muted">Account Name</label>
                <p class="fw-semibold"><?php echo htmlspecialchars($supplier['bank_account_name']); ?></p>
            </div>
            <?php endif; ?>
            <?php if (!empty($supplier['bank_account_number'])): ?>
            <div class="col-md-3">
                <label class="text-muted">Account Number</label>
                <p class="fw-semibold"><?php echo htmlspecialchars($supplier['bank_account_number']); ?></p>
            </div>
            <?php endif; ?>
            <?php if (!empty($supplier['swift_code'])): ?>
            <div class="col-md-3">
                <label class="text-muted">SWIFT Code</label>
                <p class="fw-semibold"><?php echo htmlspecialchars($supplier['swift_code']); ?></p>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Products -->
    <div class="details-card">
        <div class="card-title">
            <i class="fas fa-box"></i> Recent Products
            <a href="../products/manage-products.php?supplier=<?php echo $supplier_id; ?>" class="btn btn-sm btn-outline-primary float-end">
                View All
            </a>
        </div>
        <?php if (empty($products)): ?>
            <p class="text-muted">No products added yet.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Product Name</th>
                            <th>SKU</th>
                            <th>Price</th>
                            <th>Quantity</th>
                            <th>Status</th>
                            <th>Added</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($products as $product): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($product['name']); ?></td>
                            <td><code><?php echo htmlspecialchars($product['sku']); ?></code></td>
                            <td>TSh <?php echo number_format($product['price'], 2); ?></td>
                            <td><?php echo number_format($product['quantity']); ?></td>
                            <td>
                                <span class="status-badge <?php echo $product['status']; ?>">
                                    <?php echo ucfirst($product['status']); ?>
                                </span>
                            </td>
                            <td><?php echo date('M d, Y', strtotime($product['created_at'])); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Recent Orders -->
    <div class="details-card">
        <div class="card-title">
            <i class="fas fa-shopping-cart"></i> Recent Orders
            <a href="../orders/manage-orders.php?supplier=<?php echo $supplier_id; ?>" class="btn btn-sm btn-outline-primary float-end">
                View All
            </a>
        </div>
        <?php if (empty($recent_orders)): ?>
            <p class="text-muted">No orders yet.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Order #</th>
                            <th>Customer</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Payment</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_orders as $order): ?>
                        <tr>
                            <td>
                                <a href="../orders/order-details.php?id=<?php echo $order['id']; ?>">
                                    #<?php echo htmlspecialchars($order['order_number']); ?>
                                </a>
                            </td>
                            <td><?php echo htmlspecialchars($order['customer_name'] ?? 'Guest'); ?></td>
                            <td>TSh <?php echo number_format($order['total_amount'], 2); ?></td>
                            <td>
                                <span class="status-badge <?php echo $order['order_status']; ?>">
                                    <?php echo ucfirst($order['order_status']); ?>
                                </span>
                            </td>
                            <td>
                                <span class="status-badge <?php echo $order['payment_status']; ?>">
                                    <?php echo ucfirst($order['payment_status']); ?>
                                </span>
                            </td>
                            <td><?php echo date('M d, Y', strtotime($order['created_at'])); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
    
    <?php endif; ?>
</div>

<!-- Footer -->
<footer class="footer">
    <div class="container">
        <div class="row">
            <div class="col-md-4 mb-4">
                <h5><i class="fas fa-microchip me-2"></i>TechProcure Tanzania</h5>
                <p class="text-muted">Enterprise B2B IT equipment procurement platform.</p>
            </div>
            <div class="col-md-2 mb-4">
                <h6>Quick Links</h6>
                <ul class="list-unstyled">
                    <li><a href="../../index.php">Home</a></li>
                    <li><a href="../../products.php">Products</a></li>
                </ul>
            </div>
            <div class="col-md-3 mb-4">
                <h6>Admin</h6>
                <ul class="list-unstyled">
                    <li><a href="../dashboard.php">Dashboard</a></li>
                    <li><a href="manage-suppliers.php">Suppliers</a></li>
                </ul>
            </div>
            <div class="col-md-3 mb-4">
                <h6>Contact</h6>
                <ul class="list-unstyled">
                    <li><i class="fas fa-envelope me-2"></i> support@techprocure.co.tz</li>
                    <li><i class="fas fa-phone me-2"></i> +255 123 456 789</li>
                </ul>
            </div>
        </div>
        <hr style="border-color: rgba(255,255,255,0.1);">
        <div class="text-center">
            <small style="color: rgba(255,255,255,0.5);">&copy; <?php echo date('Y'); ?> TechProcure Tanzania. All rights reserved.</small>
        </div>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<script>
// Auto-hide alerts
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