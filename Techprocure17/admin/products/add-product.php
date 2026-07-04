<?php
/**
 * TechProcure Tanzania - Admin Add Product
 * File: admin/products/add-product.php
 * Description: Add new products with complete form and CSRF protection
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
$user_id = $_SESSION['user_id'];

// =============================================
// CSRF TOKEN FUNCTIONS
// =============================================

function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $_SESSION['csrf_token_time'] = time();
    }
    return $_SESSION['csrf_token'];
}

function verifyCSRFToken($token) {
    if (empty($token) || empty($_SESSION['csrf_token'])) {
        return false;
    }
    
    // Check if token is expired (1 hour)
    if (isset($_SESSION['csrf_token_time']) && (time() - $_SESSION['csrf_token_time']) > 3600) {
        unset($_SESSION['csrf_token']);
        unset($_SESSION['csrf_token_time']);
        return false;
    }
    
    return hash_equals($_SESSION['csrf_token'], $token);
}

// Generate new CSRF token
$csrf_token = generateCSRFToken();

// =============================================
// FETCH CATEGORIES, BRANDS, SUPPLIERS
// =============================================

$categories = [];
$brands = [];
$suppliers = [];
$error = '';

try {
    // Get categories
    $cat_stmt = $db->query("SELECT id, name FROM categories ORDER BY name");
    $categories = $cat_stmt->fetchAll();
    
    // Get brands
    $brand_stmt = $db->query("SELECT id, name FROM brands ORDER BY name");
    $brands = $brand_stmt->fetchAll();
    
    // =============================================
    // FETCH SUPPLIERS - FIXED VERSION
    // =============================================
    try {
        // Check if suppliers table exists
        $tableCheck = $db->query("SHOW TABLES LIKE 'suppliers'");
        $suppliersTableExists = $tableCheck->rowCount() > 0;
        
        if ($suppliersTableExists) {
            // Check what columns exist in suppliers table
            $checkColumns = $db->query("SHOW COLUMNS FROM suppliers");
            $existingColumns = [];
            while ($col = $checkColumns->fetch()) {
                $existingColumns[] = $col['Field'];
            }
            
            // Build select fields based on available columns
            $selectFields = "id, company_name";
            
            if (in_array('contact_person', $existingColumns)) {
                $selectFields .= ", contact_person";
            } else {
                $selectFields .= ", '' as contact_person";
            }
            
            if (in_array('email', $existingColumns)) {
                $selectFields .= ", email";
            } else {
                $selectFields .= ", '' as email";
            }
            
            if (in_array('phone', $existingColumns)) {
                $selectFields .= ", phone";
            } else {
                $selectFields .= ", '' as phone";
            }
            
            // Build WHERE clause based on available status columns
            $whereClause = "";
            if (in_array('verification_status', $existingColumns)) {
                $whereClause = "WHERE verification_status = 'verified'";
            } elseif (in_array('status', $existingColumns)) {
                $whereClause = "WHERE status IN ('active', 'verified')";
            }
            
            $sql = "SELECT $selectFields FROM suppliers $whereClause ORDER BY company_name";
            $supp_stmt = $db->query($sql);
            $suppliers = $supp_stmt->fetchAll();
            
            // If no suppliers found with filters, get all suppliers
            if (empty($suppliers)) {
                $supp_stmt = $db->query("SELECT $selectFields FROM suppliers ORDER BY company_name");
                $suppliers = $supp_stmt->fetchAll();
            }
            
        } else {
            // Suppliers table doesn't exist - try users table
            // Check if users table has supplier records
            $userCheck = $db->query("SELECT id FROM users WHERE user_type = 'supplier' OR role_id IN (SELECT id FROM roles WHERE role_name LIKE '%supplier%') LIMIT 1");
            if ($userCheck->rowCount() > 0) {
                $supp_stmt = $db->query("
                    SELECT u.id, u.full_name as company_name, u.email, u.phone,
                           '' as contact_person
                    FROM users u
                    WHERE u.user_type = 'supplier' OR u.role_id IN (SELECT id FROM roles WHERE role_name LIKE '%supplier%')
                    ORDER BY u.full_name
                ");
                $suppliers = $supp_stmt->fetchAll();
            }
        }
        
        // If still no suppliers, create a default option
        if (empty($suppliers)) {
            // Try to get any user with supplier role
            $supp_stmt = $db->query("
                SELECT u.id, u.full_name as company_name, u.email, u.phone,
                       '' as contact_person
                FROM users u
                JOIN roles r ON u.role_id = r.id
                WHERE LOWER(r.role_name) LIKE '%supplier%'
                ORDER BY u.full_name
            ");
            $suppliers = $supp_stmt->fetchAll();
        }
        
    } catch (PDOException $e) {
        // Fallback: get from users table
        try {
            $supp_stmt = $db->query("
                SELECT id, full_name as company_name, email, phone,
                       '' as contact_person
                FROM users 
                WHERE user_type = 'supplier'
                ORDER BY full_name
            ");
            $suppliers = $supp_stmt->fetchAll();
        } catch (PDOException $e2) {
            $suppliers = [];
        }
    }
    
} catch (PDOException $e) {
    $error = "Failed to load data: " . $e->getMessage();
}

// =============================================
// PROCESS PRODUCT ADDITION
// =============================================

$success = '';
$form_data = [];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Get and verify CSRF token
    $submitted_token = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
    
    if (!verifyCSRFToken($submitted_token)) {
        $error = 'Security token validation failed. Please refresh the page and try again.';
    } else {
        // Sanitize all inputs
        $name = sanitizeInput($_POST['name'] ?? '');
        $description = sanitizeInput($_POST['description'] ?? '');
        $short_description = sanitizeInput($_POST['short_description'] ?? '');
        $category_id = (int)($_POST['category_id'] ?? 0);
        $brand_id = (int)($_POST['brand_id'] ?? 0);
        $supplier_id = (int)($_POST['supplier_id'] ?? 0);
        $price = (float)($_POST['price'] ?? 0);
        $compare_price = (float)($_POST['compare_price'] ?? 0);
        $cost_price = (float)($_POST['cost_price'] ?? 0);
        $quantity = (int)($_POST['quantity'] ?? 0);
        $min_quantity = (int)($_POST['min_quantity'] ?? 1);
        $sku = sanitizeInput($_POST['sku'] ?? '');
        $weight = (float)($_POST['weight'] ?? 0);
        $status = sanitizeInput($_POST['status'] ?? 'draft');
        $is_featured = isset($_POST['is_featured']) ? 1 : 0;
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        $specifications = $_POST['specifications'] ?? [];
        $tags = sanitizeInput($_POST['tags'] ?? '');
        $meta_title = sanitizeInput($_POST['meta_title'] ?? '');
        $meta_description = sanitizeInput($_POST['meta_description'] ?? '');
        $meta_keywords = sanitizeInput($_POST['meta_keywords'] ?? '');
        
        // Store form data for repopulation
        $form_data = [
            'name' => $name,
            'description' => $description,
            'short_description' => $short_description,
            'category_id' => $category_id,
            'brand_id' => $brand_id,
            'supplier_id' => $supplier_id,
            'price' => $price,
            'compare_price' => $compare_price,
            'cost_price' => $cost_price,
            'quantity' => $quantity,
            'min_quantity' => $min_quantity,
            'sku' => $sku,
            'weight' => $weight,
            'status' => $status,
            'is_featured' => $is_featured,
            'is_active' => $is_active,
            'tags' => $tags
        ];
        
        // Validate required fields
        $errors = [];
        if (empty($name)) $errors[] = "Product name is required.";
        if (empty($category_id)) $errors[] = "Please select a category.";
        if (empty($supplier_id)) $errors[] = "Please select a supplier.";
        if ($price <= 0) $errors[] = "Please enter a valid price.";
        if (empty($sku)) $errors[] = "SKU is required.";
        
        if (empty($errors)) {
            try {
                $db->beginTransaction();
                
                // Generate product code
                $product_code = 'PRD-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
                
                // Insert product
                $sql = "INSERT INTO products (
                            product_code,
                            name,
                            description,
                            short_description,
                            category_id,
                            brand_id,
                            supplier_id,
                            price,
                            compare_price,
                            cost_price,
                            quantity,
                            min_quantity,
                            sku,
                            weight,
                            status,
                            is_featured,
                            is_active,
                            tags,
                            meta_title,
                            meta_description,
                            meta_keywords,
                            created_by,
                            created_at,
                            updated_at
                        ) VALUES (
                            ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW()
                        )";
                
                $stmt = $db->prepare($sql);
                $stmt->execute([
                    $product_code,
                    $name,
                    $description,
                    $short_description,
                    $category_id,
                    $brand_id ?: null,
                    $supplier_id,
                    $price,
                    $compare_price ?: null,
                    $cost_price ?: null,
                    $quantity,
                    $min_quantity,
                    $sku,
                    $weight ?: null,
                    $status,
                    $is_featured,
                    $is_active,
                    $tags,
                    $meta_title,
                    $meta_description,
                    $meta_keywords,
                    $user_id
                ]);
                
                $product_id = $db->lastInsertId();
                
                // Insert specifications
                if (!empty($specifications)) {
                    $spec_sql = "INSERT INTO product_specifications (product_id, spec_key, spec_value) VALUES (?, ?, ?)";
                    $spec_stmt = $db->prepare($spec_sql);
                    foreach ($specifications as $spec) {
                        if (!empty($spec['key']) && !empty($spec['value'])) {
                            $spec_stmt->execute([$product_id, $spec['key'], $spec['value']]);
                        }
                    }
                }
                
                // Handle image upload
                $uploaded_images = [];
                if (isset($_FILES['product_images']) && !empty($_FILES['product_images']['name'][0])) {
                    $upload_dir = '../../public/uploads/products/';
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0777, true);
                    }
                    
                    $image_sql = "INSERT INTO product_images (product_id, image_path, is_primary) VALUES (?, ?, ?)";
                    $image_stmt = $db->prepare($image_sql);
                    
                    foreach ($_FILES['product_images']['name'] as $key => $filename) {
                        if ($_FILES['product_images']['error'][$key] == 0) {
                            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                            $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                            
                            if (in_array($ext, $allowed)) {
                                $new_filename = $product_code . '-' . time() . '-' . ($key + 1) . '.' . $ext;
                                $destination = $upload_dir . $new_filename;
                                
                                if (move_uploaded_file($_FILES['product_images']['tmp_name'][$key], $destination)) {
                                    $is_primary = ($key == 0) ? 1 : 0;
                                    $image_stmt->execute([$product_id, 'uploads/products/' . $new_filename, $is_primary]);
                                    $uploaded_images[] = $new_filename;
                                }
                            }
                        }
                    }
                }
                
                // Log activity
                logActivity($user_id, "Added new product: " . $name . " (SKU: " . $sku . ")", 'product', $product_id);
                
                $db->commit();
                
                // Generate new CSRF token after successful submission
                unset($_SESSION['csrf_token']);
                unset($_SESSION['csrf_token_time']);
                $csrf_token = generateCSRFToken();
                
                $_SESSION['success'] = "Product '" . $name . "' added successfully!";
                header("Location: manage-products.php");
                exit();
                
            } catch (Exception $e) {
                $db->rollBack();
                $error = "Failed to add product: " . $e->getMessage();
            }
        } else {
            $error = implode("<br>", $errors);
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Product - TechProcure Tanzania</title>
    
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
        
        .form-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 24px;
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
        
        .form-label {
            font-weight: 500;
            font-size: 0.85rem;
        }
        
        .form-control, .form-select {
            border-radius: 10px;
            padding: 10px 15px;
            border: 1px solid #e0e0e0;
            transition: all 0.3s;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: #0d6efd;
            box-shadow: 0 0 0 3px rgba(13,110,253,0.1);
            outline: none;
        }
        
        .btn-save {
            background: linear-gradient(135deg, #0d6efd, #0b5ed7);
            border: none;
            border-radius: 10px;
            padding: 12px 30px;
            font-weight: 600;
            color: white;
            transition: all 0.3s;
            cursor: pointer;
        }
        
        .btn-save:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(13,110,253,0.3);
        }
        
        .btn-cancel {
            background: #6c757d;
            border: none;
            border-radius: 10px;
            padding: 12px 30px;
            font-weight: 600;
            color: white;
            transition: all 0.3s;
            text-decoration: none;
        }
        
        .btn-cancel:hover {
            background: #5a6268;
            color: white;
        }
        
        .image-preview {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 10px;
        }
        
        .image-preview .preview-item {
            width: 100px;
            height: 100px;
            border-radius: 10px;
            overflow: hidden;
            border: 2px solid #e9ecef;
            position: relative;
        }
        
        .image-preview .preview-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .image-preview .preview-item .remove-image {
            position: absolute;
            top: 5px;
            right: 5px;
            background: rgba(220,53,69,0.8);
            color: white;
            border: none;
            border-radius: 50%;
            width: 24px;
            height: 24px;
            font-size: 12px;
            cursor: pointer;
        }
        
        .spec-row {
            display: flex;
            gap: 10px;
            margin-bottom: 10px;
        }
        
        .spec-row .form-control {
            flex: 1;
        }
        
        .spec-row .btn-remove-spec {
            background: #dc3545;
            color: white;
            border: none;
            border-radius: 10px;
            padding: 0 15px;
        }
        
        .required:after {
            content: " *";
            color: red;
        }
        
        .no-suppliers-warning {
            background: #fff3cd;
            border: 1px solid #ffc107;
            border-radius: 10px;
            padding: 15px;
            margin-top: 10px;
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
            .form-card {
                padding: 20px;
            }
            .spec-row {
                flex-wrap: wrap;
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
            <a href="../suppliers/manage-suppliers.php" class="nav-link">
                <i class="fas fa-truck"></i> Suppliers
            </a>
        </div>
        <div class="nav-item">
            <a href="manage-products.php" class="nav-link active">
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
            <i class="fas fa-plus-circle me-2 text-primary"></i> Add New Product
        </div>
        <div class="d-flex align-items-center gap-3">
            <span class="badge bg-primary">New Product</span>
            <div class="admin-avatar">
                <?php echo strtoupper(substr($_SESSION['user_name'] ?? 'A', 0, 1)); ?>
            </div>
        </div>
    </div>
    
    <!-- Error/Success Messages -->
    <?php if($error): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="fas fa-exclamation-triangle me-2"></i> <?php echo $error; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <!-- Add Product Form -->
    <form method="POST" action="" enctype="multipart/form-data" id="productForm">
        <!-- CSRF Token -->
        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
        
        <!-- Basic Information -->
        <div class="form-card">
            <div class="card-title">
                <i class="fas fa-info-circle"></i> Basic Information
            </div>
            <div class="row">
                <div class="col-md-8 mb-3">
                    <label class="form-label required">Product Name</label>
                    <input type="text" name="name" class="form-control" placeholder="Enter product name" 
                           value="<?php echo htmlspecialchars($form_data['name'] ?? ''); ?>" required>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label required">SKU</label>
                    <input type="text" name="sku" class="form-control" placeholder="e.g., IT-001" 
                           value="<?php echo htmlspecialchars($form_data['sku'] ?? ''); ?>" required>
                    <small class="text-muted">Unique stock keeping unit</small>
                </div>
                <div class="col-md-12 mb-3">
                    <label class="form-label">Short Description</label>
                    <input type="text" name="short_description" class="form-control" placeholder="Brief product summary (max 150 characters)"
                           value="<?php echo htmlspecialchars($form_data['short_description'] ?? ''); ?>">
                </div>
                <div class="col-md-12 mb-3">
                    <label class="form-label">Full Description</label>
                    <textarea name="description" class="form-control" rows="5" placeholder="Detailed product description"><?php echo htmlspecialchars($form_data['description'] ?? ''); ?></textarea>
                </div>
            </div>
        </div>
        
        <!-- Category & Supplier -->
        <div class="form-card">
            <div class="card-title">
                <i class="fas fa-tags"></i> Category & Supplier
            </div>
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label class="form-label required">Category</label>
                    <select name="category_id" class="form-select" required>
                        <option value="">Select Category</option>
                        <?php foreach($categories as $cat): ?>
                            <option value="<?php echo $cat['id']; ?>" 
                                    <?php echo (isset($form_data['category_id']) && $form_data['category_id'] == $cat['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cat['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Brand</label>
                    <select name="brand_id" class="form-select">
                        <option value="">Select Brand</option>
                        <?php foreach($brands as $brand): ?>
                            <option value="<?php echo $brand['id']; ?>"
                                    <?php echo (isset($form_data['brand_id']) && $form_data['brand_id'] == $brand['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($brand['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label required">Supplier</label>
                    <select name="supplier_id" class="form-select" required>
                        <option value="">Select Supplier</option>
                        <?php if(!empty($suppliers)): ?>
                            <?php foreach($suppliers as $supp): ?>
                                <option value="<?php echo $supp['id']; ?>"
                                        <?php echo (isset($form_data['supplier_id']) && $form_data['supplier_id'] == $supp['id']) ? 'selected' : ''; ?>>
                                    <?php 
                                        $displayName = htmlspecialchars($supp['company_name']);
                                        if (!empty($supp['contact_person']) && isset($supp['contact_person'])) {
                                            $displayName .= ' (' . htmlspecialchars($supp['contact_person']) . ')';
                                        }
                                        echo $displayName;
                                    ?>
                                </option>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <option value="" disabled>No suppliers available</option>
                        <?php endif; ?>
                    </select>
                    <?php if(empty($suppliers)): ?>
                        <div class="no-suppliers-warning">
                            <i class="fas fa-exclamation-triangle text-warning me-2"></i>
                            <strong>No suppliers found!</strong>
                            <p class="mb-0 small">You need to <a href="../suppliers/manage-suppliers.php" class="text-primary">add a supplier</a> first before you can create products.</p>
                        </div>
                    <?php else: ?>
                        <small class="text-muted">Select the supplier for this product</small>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Pricing & Inventory -->
        <div class="form-card">
            <div class="card-title">
                <i class="fas fa-dollar-sign"></i> Pricing & Inventory
            </div>
            <div class="row">
                <div class="col-md-3 mb-3">
                    <label class="form-label required">Price (TSh)</label>
                    <input type="number" name="price" class="form-control" placeholder="0.00" step="0.01" min="0"
                           value="<?php echo htmlspecialchars($form_data['price'] ?? ''); ?>" required>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">Compare Price (TSh)</label>
                    <input type="number" name="compare_price" class="form-control" placeholder="0.00" step="0.01" min="0"
                           value="<?php echo htmlspecialchars($form_data['compare_price'] ?? ''); ?>">
                    <small class="text-muted">Original price for discount display</small>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">Cost Price (TSh)</label>
                    <input type="number" name="cost_price" class="form-control" placeholder="0.00" step="0.01" min="0"
                           value="<?php echo htmlspecialchars($form_data['cost_price'] ?? ''); ?>">
                    <small class="text-muted">Your cost for this product</small>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label required">Quantity</label>
                    <input type="number" name="quantity" class="form-control" placeholder="0" min="0"
                           value="<?php echo htmlspecialchars($form_data['quantity'] ?? ''); ?>" required>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">Minimum Order Quantity</label>
                    <input type="number" name="min_quantity" class="form-control" placeholder="1" min="1"
                           value="<?php echo htmlspecialchars($form_data['min_quantity'] ?? 1); ?>">
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">Weight (kg)</label>
                    <input type="number" name="weight" class="form-control" placeholder="0.00" step="0.01" min="0"
                           value="<?php echo htmlspecialchars($form_data['weight'] ?? ''); ?>">
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="draft" <?php echo (isset($form_data['status']) && $form_data['status'] == 'draft') ? 'selected' : ''; ?>>Draft</option>
                        <option value="active" <?php echo (isset($form_data['status']) && $form_data['status'] == 'active') ? 'selected' : ''; ?>>Active</option>
                        <option value="out_of_stock" <?php echo (isset($form_data['status']) && $form_data['status'] == 'out_of_stock') ? 'selected' : ''; ?>>Out of Stock</option>
                        <option value="discontinued" <?php echo (isset($form_data['status']) && $form_data['status'] == 'discontinued') ? 'selected' : ''; ?>>Discontinued</option>
                    </select>
                </div>
            </div>
        </div>
        
        <!-- Product Images -->
        <div class="form-card">
            <div class="card-title">
                <i class="fas fa-images"></i> Product Images
            </div>
            <div class="mb-3">
                <label class="form-label">Upload Images</label>
                <input type="file" name="product_images[]" class="form-control" accept="image/*" multiple id="productImages">
                <small class="text-muted">Upload multiple images (JPG, PNG, WEBP). First image will be primary.</small>
            </div>
            <div class="image-preview" id="imagePreview"></div>
        </div>
        
        <!-- Specifications -->
        <div class="form-card">
            <div class="card-title">
                <i class="fas fa-list"></i> Specifications
            </div>
            <div id="specificationsContainer">
                <div class="spec-row">
                    <input type="text" name="specifications[0][key]" class="form-control" placeholder="e.g., Processor">
                    <input type="text" name="specifications[0][value]" class="form-control" placeholder="e.g., Intel Core i7">
                    <button type="button" class="btn-remove-spec" onclick="removeSpec(this)"><i class="fas fa-times"></i></button>
                </div>
            </div>
            <button type="button" class="btn btn-sm btn-outline-primary mt-2" onclick="addSpec()">
                <i class="fas fa-plus me-1"></i> Add Specification
            </button>
        </div>
        
        <!-- SEO & Meta -->
        <div class="form-card">
            <div class="card-title">
                <i class="fas fa-google"></i> SEO & Meta Data
            </div>
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label class="form-label">Meta Title</label>
                    <input type="text" name="meta_title" class="form-control" placeholder="SEO Title"
                           value="<?php echo htmlspecialchars($form_data['meta_title'] ?? ''); ?>">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Meta Description</label>
                    <input type="text" name="meta_description" class="form-control" placeholder="SEO Description"
                           value="<?php echo htmlspecialchars($form_data['meta_description'] ?? ''); ?>">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Meta Keywords</label>
                    <input type="text" name="meta_keywords" class="form-control" placeholder="keyword1, keyword2"
                           value="<?php echo htmlspecialchars($form_data['meta_keywords'] ?? ''); ?>">
                </div>
            </div>
        </div>
        
        <!-- Additional Options -->
        <div class="form-card">
            <div class="card-title">
                <i class="fas fa-cog"></i> Additional Options
            </div>
            <div class="row">
                <div class="col-md-6">
                    <div class="form-check form-switch mb-2">
                        <input type="checkbox" name="is_featured" class="form-check-input" id="isFeatured" value="1"
                               <?php echo (isset($form_data['is_featured']) && $form_data['is_featured']) ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="isFeatured">Featured Product</label>
                    </div>
                    <div class="form-check form-switch">
                        <input type="checkbox" name="is_active" class="form-check-input" id="isActive" value="1" checked
                               <?php echo (isset($form_data['is_active']) && !$form_data['is_active']) ? '' : 'checked'; ?>>
                        <label class="form-check-label" for="isActive">Active (Visible to customers)</label>
                    </div>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Tags</label>
                    <input type="text" name="tags" class="form-control" placeholder="tag1, tag2, tag3"
                           value="<?php echo htmlspecialchars($form_data['tags'] ?? ''); ?>">
                    <small class="text-muted">Comma separated tags for better search</small>
                </div>
            </div>
        </div>
        
        <!-- Form Actions -->
        <div class="d-flex gap-3">
            <button type="submit" class="btn-save" <?php echo empty($suppliers) ? 'disabled' : ''; ?>>
                <i class="fas fa-save me-2"></i> Save Product
            </button>
            <a href="manage-products.php" class="btn-cancel">
                <i class="fas fa-times me-2"></i> Cancel
            </a>
        </div>
        <?php if(empty($suppliers)): ?>
            <div class="mt-2 text-danger small">
                <i class="fas fa-exclamation-circle me-1"></i>
                You need to add suppliers before you can create products.
                <a href="../suppliers/manage-suppliers.php" class="text-primary">Add a supplier now</a>
            </div>
        <?php endif; ?>
    </form>
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
                    <li><a href="manage-products.php">Products</a></li>
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
    // =============================================
    // IMAGE PREVIEW
    // =============================================
    document.getElementById('productImages')?.addEventListener('change', function(e) {
        const preview = document.getElementById('imagePreview');
        preview.innerHTML = '';
        
        const files = this.files;
        for (let i = 0; i < files.length; i++) {
            const file = files[i];
            if (file.type.startsWith('image/')) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const div = document.createElement('div');
                    div.className = 'preview-item';
                    div.innerHTML = `
                        <img src="${e.target.result}" alt="Preview">
                        <button type="button" class="remove-image" onclick="removeImage(this)">×</button>
                    `;
                    preview.appendChild(div);
                };
                reader.readAsDataURL(file);
            }
        }
    });
    
    function removeImage(btn) {
        btn.closest('.preview-item').remove();
    }

    // =============================================
    // SPECIFICATIONS
    // =============================================
    let specCounter = 1;
    
    function addSpec() {
        const container = document.getElementById('specificationsContainer');
        const row = document.createElement('div');
        row.className = 'spec-row';
        row.innerHTML = `
            <input type="text" name="specifications[${specCounter}][key]" class="form-control" placeholder="e.g., Processor">
            <input type="text" name="specifications[${specCounter}][value]" class="form-control" placeholder="e.g., Intel Core i7">
            <button type="button" class="btn-remove-spec" onclick="removeSpec(this)"><i class="fas fa-times"></i></button>
        `;
        container.appendChild(row);
        specCounter++;
    }
    
    function removeSpec(btn) {
        const row = btn.closest('.spec-row');
        if (document.querySelectorAll('.spec-row').length > 1) {
            row.remove();
        } else {
            alert('At least one specification is required.');
        }
    }

    // =============================================
    // FORM SUBMISSION VALIDATION
    // =============================================
    document.getElementById('productForm')?.addEventListener('submit', function(e) {
        const supplierSelect = document.querySelector('select[name="supplier_id"]');
        if (supplierSelect && supplierSelect.value === '') {
            e.preventDefault();
            alert('Please select a supplier for this product.');
            supplierSelect.focus();
            return false;
        }
        
        const submitBtn = this.querySelector('button[type="submit"]');
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Saving...';
        return true;
    });

    // =============================================
    // AUTO-HIDE ALERTS
    // =============================================
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