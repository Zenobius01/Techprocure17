<?php
/**
 * TechProcure Tanzania - Admin Edit Product
 * File: admin/products/edit-product.php
 * Description: Admin can edit existing products with full form
 */

// Start session FIRST
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

// Get product ID from URL
$product_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($product_id <= 0) {
    $_SESSION['error'] = "Invalid product ID.";
    header("Location: manage-products.php");
    exit();
}

// =============================================
// FETCH PRODUCT DATA
// =============================================

$product = null;
try {
    $sql = "SELECT p.*, c.name as category_name, s.company_name as supplier_name 
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.id
            LEFT JOIN suppliers s ON p.supplier_id = s.id
            WHERE p.id = ?";
    $stmt = $db->prepare($sql);
    $stmt->execute([$product_id]);
    $product = $stmt->fetch();
    
    if (!$product) {
        $_SESSION['error'] = "Product not found.";
        header("Location: manage-products.php");
        exit();
    }
} catch (PDOException $e) {
    $_SESSION['error'] = "Failed to load product.";
    header("Location: manage-products.php");
    exit();
}

// =============================================
// GET CATEGORIES AND SUPPLIERS FOR DROPDOWN
// =============================================

$categories = [];
try {
    $stmt = $db->query("SELECT id, name FROM categories WHERE status = 'active' ORDER BY name");
    if ($stmt->rowCount() > 0) {
        $categories = $stmt->fetchAll();
    }
} catch (PDOException $e) {
    $categories = [];
}

$suppliers = [];
try {
    $stmt = $db->query("SELECT id, company_name FROM suppliers WHERE approval_status = 'approved' AND status = 'active' ORDER BY company_name");
    if ($stmt->rowCount() > 0) {
        $suppliers = $stmt->fetchAll();
    }
} catch (PDOException $e) {
    $suppliers = [];
}

// =============================================
// FETCH PRODUCT IMAGES
// =============================================

$product_images = [];
try {
    $sql = "SELECT * FROM product_images WHERE product_id = ? ORDER BY is_primary DESC, sort_order ASC";
    $stmt = $db->prepare($sql);
    $stmt->execute([$product_id]);
    if ($stmt->rowCount() > 0) {
        $product_images = $stmt->fetchAll();
    }
} catch (PDOException $e) {
    $product_images = [];
}

// =============================================
// PROCESS EDIT PRODUCT FORM
// =============================================

$error = '';
$success = '';

// REGENERATE CSRF TOKEN ON EACH PAGE LOAD
$csrf_token = generateCSRFToken();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Get the token from POST
    $submitted_token = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
    
    // Verify CSRF token
    if (empty($submitted_token) || !verifyCSRFToken($submitted_token)) {
        $error = 'Security token validation failed. Please refresh the page and try again.';
    } else {
        // Get form data
        $product_name = trim($_POST['product_name']);
        $slug = strtolower(trim(str_replace(' ', '-', $product_name)));
        $description = trim($_POST['description']);
        $short_description = trim($_POST['short_description']);
        $sku = trim($_POST['sku']);
        $brand = trim($_POST['brand']);
        $category_id = (int)$_POST['category_id'];
        $supplier_id = (int)$_POST['supplier_id'];
        $price_tsh = (float)$_POST['price_tsh'];
        $compare_price_tsh = (float)($_POST['compare_price_tsh'] ?? 0);
        $bulk_price_tsh = (float)($_POST['bulk_price_tsh'] ?? 0);
        $bulk_min_quantity = (int)($_POST['bulk_min_quantity'] ?? 10);
        $min_order_quantity = (int)($_POST['min_order_quantity'] ?? 1);
        $stock_quantity = (int)($_POST['stock_quantity'] ?? 0);
        $weight = (float)($_POST['weight'] ?? 0);
        $dimensions = trim($_POST['dimensions']);
        $warranty_months = (int)($_POST['warranty_months'] ?? 12);
        $is_featured = isset($_POST['is_featured']) ? 1 : 0;
        $status = $_POST['status'] ?? 'draft';
        
        // Validation
        if (empty($product_name) || empty($sku) || $price_tsh <= 0 || $category_id <= 0 || $supplier_id <= 0) {
            $error = "Please fill in all required fields.";
        } else {
            try {
                // Check if SKU already exists (excluding current product)
                $check_stmt = $db->prepare("SELECT id FROM products WHERE sku = ? AND id != ?");
                $check_stmt->execute([$sku, $product_id]);
                if ($check_stmt->rowCount() > 0) {
                    $error = "SKU already exists. Please use a unique SKU.";
                } else {
                    // Update product
                    $sql = "UPDATE products SET 
                        product_name = ?,
                        slug = ?,
                        description = ?,
                        short_description = ?,
                        sku = ?,
                        brand = ?,
                        category_id = ?,
                        supplier_id = ?,
                        price_tsh = ?,
                        compare_price_tsh = ?,
                        bulk_price_tsh = ?,
                        bulk_min_quantity = ?,
                        min_order_quantity = ?,
                        stock_quantity = ?,
                        weight = ?,
                        dimensions = ?,
                        warranty_months = ?,
                        is_featured = ?,
                        status = ?,
                        updated_at = NOW()
                    WHERE id = ?";
                    
                    $stmt = $db->prepare($sql);
                    $stmt->execute([
                        $product_name,
                        $slug,
                        $description,
                        $short_description,
                        $sku,
                        $brand,
                        $category_id,
                        $supplier_id,
                        $price_tsh,
                        $compare_price_tsh,
                        $bulk_price_tsh,
                        $bulk_min_quantity,
                        $min_order_quantity,
                        $stock_quantity,
                        $weight,
                        $dimensions,
                        $warranty_months,
                        $is_featured,
                        $status,
                        $product_id
                    ]);
                    
                    // Handle new image uploads
                    if (isset($_FILES['product_images']) && !empty($_FILES['product_images']['name'][0])) {
                        $product_upload_dir = '../../uploads/products/' . $product_id . '/';
                        if (!is_dir($product_upload_dir)) {
                            mkdir($product_upload_dir, 0777, true);
                        }
                        
                        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                        $max_size = 2097152; // 2MB
                        $uploaded_count = 0;
                        
                        foreach ($_FILES['product_images']['tmp_name'] as $key => $tmp_name) {
                            if ($_FILES['product_images']['error'][$key] == 0 && !empty($tmp_name)) {
                                $file_type = mime_content_type($tmp_name);
                                $file_size = $_FILES['product_images']['size'][$key];
                                
                                if (in_array($file_type, $allowed_types) && $file_size <= $max_size) {
                                    $ext = pathinfo($_FILES['product_images']['name'][$key], PATHINFO_EXTENSION);
                                    $filename = 'product_' . time() . '_' . $key . '.' . $ext;
                                    $filepath = $product_upload_dir . $filename;
                                    
                                    if (move_uploaded_file($tmp_name, $filepath)) {
                                        $is_primary = ($key == 0 && empty($product_images)) ? 1 : 0;
                                        $img_stmt = $db->prepare("INSERT INTO product_images (product_id, image_path, is_primary, sort_order) VALUES (?, ?, ?, ?)");
                                        $img_stmt->execute([$product_id, 'uploads/products/' . $product_id . '/' . $filename, $is_primary, $key]);
                                        $uploaded_count++;
                                    }
                                }
                            }
                        }
                    }
                    
                    // Log activity
                    logActivity($user_id, 'Updated Product', 'product', $product_id);
                    
                    $_SESSION['success'] = "Product updated successfully!";
                    header("Location: manage-products.php");
                    exit();
                }
            } catch (PDOException $e) {
                $error = "Failed to update product: " . $e->getMessage();
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Product - TechProcure Tanzania</title>
    
    <!-- CSS Dependencies -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f8f9fa;
        }
        
        /* Sidebar */
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
        
        /* Main Content */
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
        
        .required:after {
            content: " *";
            color: red;
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
        
        .image-preview {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 10px;
        }
        
        .image-preview .preview-item {
            position: relative;
            width: 100px;
            height: 100px;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            overflow: hidden;
        }
        
        .image-preview .preview-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .image-preview .preview-item .remove-btn {
            position: absolute;
            top: 5px;
            right: 5px;
            background: #dc3545;
            color: white;
            border: none;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            font-size: 10px;
            cursor: pointer;
        }
        
        .existing-images {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin: 10px 0;
        }
        
        .existing-images .image-item {
            position: relative;
            width: 100px;
            height: 100px;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            overflow: hidden;
        }
        
        .existing-images .image-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .existing-images .image-item .delete-image {
            position: absolute;
            top: 5px;
            right: 5px;
            background: #dc3545;
            color: white;
            border: none;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            font-size: 10px;
            cursor: pointer;
            z-index: 10;
        }
        
        .existing-images .image-item .primary-badge {
            position: absolute;
            bottom: 5px;
            left: 5px;
            background: #0d6efd;
            color: white;
            border: none;
            border-radius: 3px;
            padding: 2px 8px;
            font-size: 10px;
            z-index: 10;
        }
        
        @media (max-width: 992px) {
            .sidebar {
                margin-left: -280px;
            }
            .sidebar.active {
                margin-left: 0;
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
                <i class="fas fa-cog"></i> Settings
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
            <i class="fas fa-edit me-2 text-primary"></i> Edit Product
        </div>
        <div class="d-flex align-items-center gap-3">
            <a href="manage-products.php" class="btn btn-secondary btn-sm">
                <i class="fas fa-arrow-left me-1"></i> Back
            </a>
            <div class="admin-avatar">
                <?php echo strtoupper(substr($_SESSION['user_name'] ?? 'A', 0, 1)); ?>
            </div>
        </div>
    </div>
    
    <!-- Error/Success Messages -->
    <?php if($error): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-triangle me-2"></i> <?php echo $error; ?>
        </div>
    <?php endif; ?>
    
    <!-- Edit Product Form -->
    <div class="form-card">
        <div class="card-title">
            <i class="fas fa-edit"></i> Edit Product: <?php echo htmlspecialchars($product['product_name']); ?>
        </div>
        
        <form method="POST" action="" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            
            <div class="row">
                <!-- Left Column -->
                <div class="col-lg-8">
                    <!-- Basic Information -->
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label required">Product Name</label>
                            <input type="text" name="product_name" class="form-control" 
                                   value="<?php echo htmlspecialchars($product['product_name']); ?>" 
                                   placeholder="Enter product name" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label required">SKU</label>
                            <input type="text" name="sku" class="form-control" 
                                   value="<?php echo htmlspecialchars($product['sku']); ?>" 
                                   placeholder="Unique SKU" required>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Brand</label>
                            <input type="text" name="brand" class="form-control" 
                                   value="<?php echo htmlspecialchars($product['brand'] ?? ''); ?>" 
                                   placeholder="e.g., HP, Dell, Cisco">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label required">Category</label>
                            <select name="category_id" class="form-select" required>
                                <option value="">Select Category</option>
                                <?php foreach($categories as $cat): ?>
                                    <option value="<?php echo $cat['id']; ?>" <?php echo $product['category_id'] == $cat['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($cat['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Short Description</label>
                        <input type="text" name="short_description" class="form-control" 
                               value="<?php echo htmlspecialchars($product['short_description'] ?? ''); ?>" 
                               placeholder="Brief description (max 200 characters)">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Full Description</label>
                        <textarea name="description" class="form-control" rows="5" 
                                  placeholder="Detailed product description"><?php echo htmlspecialchars($product['description'] ?? ''); ?></textarea>
                    </div>
                    
                    <!-- Pricing -->
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label required">Price (TSh)</label>
                            <input type="number" name="price_tsh" class="form-control" step="0.01" 
                                   value="<?php echo $product['price_tsh']; ?>" 
                                   placeholder="0.00" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Compare Price (TSh)</label>
                            <input type="number" name="compare_price_tsh" class="form-control" step="0.01" 
                                   value="<?php echo $product['compare_price_tsh'] ?? ''; ?>" 
                                   placeholder="0.00">
                            <small class="text-muted">Original/Market price</small>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Bulk Price (TSh)</label>
                            <input type="number" name="bulk_price_tsh" class="form-control" step="0.01" 
                                   value="<?php echo $product['bulk_price_tsh'] ?? ''; ?>" 
                                   placeholder="0.00">
                            <small class="text-muted">Price for bulk orders</small>
                        </div>
                    </div>
                    
                    <!-- Inventory -->
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label required">Stock Quantity</label>
                            <input type="number" name="stock_quantity" class="form-control" 
                                   value="<?php echo $product['stock_quantity']; ?>" 
                                   min="0" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Bulk Min Quantity</label>
                            <input type="number" name="bulk_min_quantity" class="form-control" 
                                   value="<?php echo $product['bulk_min_quantity'] ?? 10; ?>" 
                                   min="1">
                            <small class="text-muted">Min units for bulk price</small>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Min Order Quantity</label>
                            <input type="number" name="min_order_quantity" class="form-control" 
                                   value="<?php echo $product['min_order_quantity'] ?? 1; ?>" 
                                   min="1">
                        </div>
                    </div>
                </div>
                
                <!-- Right Column -->
                <div class="col-lg-4">
                    <!-- Supplier -->
                    <div class="mb-3">
                        <label class="form-label required">Supplier</label>
                        <select name="supplier_id" class="form-select" required>
                            <option value="">Select Supplier</option>
                            <?php foreach($suppliers as $sup): ?>
                                <option value="<?php echo $sup['id']; ?>" <?php echo $product['supplier_id'] == $sup['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($sup['company_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <!-- Shipping -->
                    <div class="row">
                        <div class="col-6 mb-3">
                            <label class="form-label">Weight (kg)</label>
                            <input type="number" name="weight" class="form-control" step="0.01" 
                                   value="<?php echo $product['weight'] ?? ''; ?>" 
                                   placeholder="0.00">
                        </div>
                        <div class="col-6 mb-3">
                            <label class="form-label">Warranty (months)</label>
                            <input type="number" name="warranty_months" class="form-control" 
                                   value="<?php echo $product['warranty_months'] ?? 12; ?>" 
                                   min="0">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Dimensions</label>
                        <input type="text" name="dimensions" class="form-control" 
                               value="<?php echo htmlspecialchars($product['dimensions'] ?? ''); ?>" 
                               placeholder="L x W x H (cm)">
                    </div>
                    
                    <!-- Status & Featured -->
                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <option value="draft" <?php echo $product['status'] == 'draft' ? 'selected' : ''; ?>>Draft</option>
                            <option value="active" <?php echo $product['status'] == 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php echo $product['status'] == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                        <small class="text-muted">Product will need approval if set to active</small>
                    </div>
                    
                    <div class="form-check mb-3">
                        <input type="checkbox" name="is_featured" class="form-check-input" id="is_featured" value="1" <?php echo $product['is_featured'] ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="is_featured">
                            <i class="fas fa-star text-warning me-1"></i> Feature this product
                        </label>
                    </div>
                    
                    <!-- Product Images -->
                    <div class="mb-3">
                        <label class="form-label">Existing Images</label>
                        <?php if (!empty($product_images)): ?>
                        <div class="existing-images">
                            <?php foreach($product_images as $img): ?>
                            <div class="image-item">
                                <?php 
                                // Fix image path for display
                                if (strpos($img['image_path'], 'uploads/') === 0) {
                                    $img_path = '../../' . $img['image_path'];
                                } else {
                                    $img_path = '../../uploads/products/' . $product_id . '/' . $img['image_path'];
                                }
                                ?>
                                <img src="<?php echo $img_path; ?>" alt="Product image" onerror="this.src='../../assets/images/placeholder-product.jpg'">
                                <?php if($img['is_primary']): ?>
                                    <span class="primary-badge">Primary</span>
                                <?php endif; ?>
                                <a href="delete-image.php?id=<?php echo $img['id']; ?>&product_id=<?php echo $product_id; ?>" class="delete-image" onclick="return confirm('Delete this image?')">×</a>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php else: ?>
                        <p class="text-muted small">No images uploaded yet.</p>
                        <?php endif; ?>
                        
                        <label class="form-label mt-2">Upload New Images</label>
                        <input type="file" name="product_images[]" class="form-control" accept="image/*" multiple>
                        <small class="text-muted">Max 2MB per image. JPG, PNG, GIF, WEBP</small>
                        <div class="image-preview" id="imagePreview"></div>
                    </div>
                </div>
            </div>
            
            <!-- Submit Buttons -->
            <div class="d-flex gap-2 mt-3">
                <button type="submit" class="btn-submit">
                    <i class="fas fa-save me-2"></i> Update Product
                </button>
                <a href="manage-products.php" class="btn btn-secondary">
                    <i class="fas fa-times me-2"></i> Cancel
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<script>
    // Image preview for new uploads
    document.querySelector('input[name="product_images[]"]').addEventListener('change', function(e) {
        const preview = document.getElementById('imagePreview');
        preview.innerHTML = '';
        
        for (let i = 0; i < this.files.length; i++) {
            const file = this.files[i];
            const reader = new FileReader();
            
            reader.onload = function(e) {
                const div = document.createElement('div');
                div.className = 'preview-item';
                div.innerHTML = `
                    <img src="${e.target.result}" alt="Preview">
                    <button type="button" class="remove-btn" onclick="this.parentElement.remove()">×</button>
                `;
                preview.appendChild(div);
            };
            
            reader.readAsDataURL(file);
        }
    });
</script>

</body>
</html>