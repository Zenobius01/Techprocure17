<?php
/**
 * TechProcure Tanzania - Add Product
 * File: admin/products/add-product.php
 */

// Start session if not started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include configuration files
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/functions.php';

// Check if user is admin
if (!isAdmin()) {
    header('Location: ../../auth/login.php');
    exit();
}

// Get database connection
$db = getDB();
$error = '';
$success = '';

// =============================================
// GET CATEGORIES, BRANDS, AND SUPPLIERS
// =============================================

$categories = [];
$brands = [];
$suppliers = [];

try {
    // Get categories
    $stmt = $db->query("SELECT id, name, slug FROM categories WHERE status = 'active' ORDER BY name");
    $categories = $stmt->fetchAll();
} catch (PDOException $e) {
    // If categories table doesn't exist, continue
}

try {
    // Get brands
    $stmt = $db->query("SELECT id, name, slug FROM brands WHERE status = 'active' ORDER BY name");
    $brands = $stmt->fetchAll();
} catch (PDOException $e) {
    // If brands table doesn't exist, continue
}

try {
    // Get suppliers
    $stmt = $db->query("SELECT id, full_name, company_name FROM users WHERE user_type = 'supplier' AND status = 'active' ORDER BY full_name");
    $suppliers = $stmt->fetchAll();
} catch (PDOException $e) {
    // If users table doesn't exist, continue
}

// =============================================
// PROCESS FORM SUBMISSION
// =============================================

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Sanitize inputs
    $product_name = sanitize($_POST['product_name'] ?? '');
    $slug = slugify($product_name);
    $category_id = isset($_POST['category_id']) && is_numeric($_POST['category_id']) ? (int)$_POST['category_id'] : null;
    $brand_id = isset($_POST['brand_id']) && is_numeric($_POST['brand_id']) ? (int)$_POST['brand_id'] : null;
    $supplier_id = isset($_POST['supplier_id']) && is_numeric($_POST['supplier_id']) ? (int)$_POST['supplier_id'] : null;
    $sku = sanitize($_POST['sku'] ?? '');
    $description = sanitize($_POST['description'] ?? '');
    $price = isset($_POST['price']) && is_numeric($_POST['price']) ? (float)$_POST['price'] : 0;
    $cost_price = isset($_POST['cost_price']) && is_numeric($_POST['cost_price']) ? (float)$_POST['cost_price'] : 0;
    $quantity = isset($_POST['quantity']) && is_numeric($_POST['quantity']) ? (int)$_POST['quantity'] : 0;
    $min_order = isset($_POST['min_order']) && is_numeric($_POST['min_order']) ? (int)$_POST['min_order'] : 1;
    $weight = isset($_POST['weight']) && is_numeric($_POST['weight']) ? (float)$_POST['weight'] : null;
    $dimensions = sanitize($_POST['dimensions'] ?? '');
    $status = sanitize($_POST['status'] ?? 'pending');
    $featured = isset($_POST['featured']) ? 1 : 0;
    
    // Validate
    if (empty($product_name)) {
        $error = 'Product name is required.';
    } elseif (empty($category_id)) {
        $error = 'Please select a category.';
    } elseif (empty($supplier_id)) {
        $error = 'Please select a supplier.';
    } elseif ($price <= 0) {
        $error = 'Price must be greater than 0.';
    } else {
        try {
            // Check if slug exists
            $stmt = $db->prepare("SELECT id FROM products WHERE slug = ?");
            $stmt->execute([$slug]);
            if ($stmt->rowCount() > 0) {
                $slug = $slug . '-' . time();
            }
            
            // =============================================
            // INSERT PRODUCT - WITH COST_PRICE_TSH
            // =============================================
            $sql = "INSERT INTO products (
                product_name, 
                slug, 
                supplier_id, 
                category_id, 
                brand_id, 
                sku, 
                description, 
                price_tsh, 
                cost_price_tsh, 
                quantity, 
                min_order, 
                weight_kg, 
                dimensions, 
                status, 
                featured
            ) VALUES (
                ?, ?, ?, ?, ?, 
                ?, ?, ?, ?, ?, 
                ?, ?, ?, ?, ?
            )";
            
            $stmt = $db->prepare($sql);
            $result = $stmt->execute([
                $product_name,
                $slug,
                $supplier_id,
                $category_id,
                $brand_id,
                $sku,
                $description,
                $price,
                $cost_price,
                $quantity,
                $min_order,
                $weight,
                $dimensions,
                $status,
                $featured
            ]);
            
            if ($result) {
                $product_id = $db->lastInsertId();
                
                // Log activity
                logActivity($_SESSION['user_id'], "Added new product: $product_name", 'product', $product_id);
                
                $success = "Product added successfully!";
                
                // Redirect to edit page to add images
                header("Location: edit-product.php?id=$product_id&success=1");
                exit();
            } else {
                $error = "Failed to insert product.";
            }
            
        } catch (PDOException $e) {
            $error = 'Failed to add product: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Product - TechProcure Admin</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f0f2f5;
        }
        
        .navbar-custom {
            background: linear-gradient(135deg, #1a2a6c, #b21f1f, #fdbb4d);
            padding: 12px 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .navbar-custom .navbar-brand {
            color: white !important;
            font-weight: 800;
            font-size: 1.4rem;
        }
        
        .navbar-custom .navbar-brand i {
            margin-right: 8px;
        }
        
        .navbar-custom .nav-link {
            color: rgba(255,255,255,0.9) !important;
            font-weight: 500;
            transition: all 0.3s;
        }
        
        .navbar-custom .nav-link:hover {
            color: white !important;
        }
        
        .page-header {
            background: white;
            border-radius: 15px;
            padding: 20px 25px;
            margin-bottom: 24px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .page-header h4 {
            font-weight: 700;
            margin: 0;
        }
        
        .page-header .text-muted {
            margin-bottom: 0;
        }
        
        .form-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        
        .form-card .form-label {
            font-weight: 600;
            font-size: 0.85rem;
            color: #333;
        }
        
        .form-card .form-control,
        .form-card .form-select {
            border-radius: 10px;
            padding: 10px 15px;
            border: 2px solid #e9ecef;
            transition: all 0.3s;
        }
        
        .form-card .form-control:focus,
        .form-card .form-select:focus {
            border-color: #0d6efd;
            box-shadow: 0 0 0 4px rgba(13,110,253,0.1);
        }
        
        .form-card .required {
            color: #dc3545;
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
            box-shadow: 0 5px 20px rgba(13,110,253,0.3);
            color: white;
        }
        
        .btn-cancel {
            border-radius: 10px;
            padding: 12px 30px;
            font-weight: 600;
        }
        
        .alert {
            border-radius: 12px;
        }
        
        .footer {
            background: #1a1a2e;
            color: white;
            padding: 30px 0 15px;
            margin-top: 40px;
        }
        
        .footer a {
            color: rgba(255,255,255,0.7);
            text-decoration: none;
        }
        
        .footer a:hover {
            color: white;
        }
        
        .footer hr {
            border-color: rgba(255,255,255,0.08);
        }
        
        @media (max-width: 768px) {
            .page-header {
                flex-direction: column;
                text-align: center;
            }
        }
    </style>
</head>
<body>

<!-- Navbar -->
<nav class="navbar navbar-expand-lg navbar-custom">
    <div class="container">
        <a class="navbar-brand" href="../dashboard.php">
            <i class="fas fa-microchip"></i> TechProcure <span style="color:#fdbb4d;">Admin</span>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav mx-auto">
                <li class="nav-item">
                    <a class="nav-link" href="../dashboard.php"><i class="fas fa-tachometer-alt me-1"></i>Dashboard</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="manage-products.php"><i class="fas fa-box me-1"></i>Products</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="../orders/manage-orders.php"><i class="fas fa-shopping-cart me-1"></i>Orders</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="../payments/transactions.php"><i class="fas fa-credit-card me-1"></i>Payments</a>
                </li>
            </ul>
            <div class="d-flex align-items-center gap-2">
                <span class="text-white-50 me-2"><?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Admin'); ?></span>
                <a href="../../auth/logout.php" class="btn btn-outline-light btn-sm">
                    <i class="fas fa-sign-out-alt me-1"></i>Logout
                </a>
            </div>
        </div>
    </div>
</nav>

<!-- Main Content -->
<div class="container mt-4">

    <!-- Page Header -->
    <div class="page-header">
        <div>
            <h4><i class="fas fa-plus-circle text-primary me-2"></i>Add New Product</h4>
            <p class="text-muted">Create a new product listing</p>
        </div>
        <a href="manage-products.php" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-1"></i>Back to Products
        </a>
    </div>

    <!-- Success/Error Messages -->
    <?php if($error): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="fas fa-exclamation-triangle me-2"></i> <?php echo htmlspecialchars($error); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if($success): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="fas fa-check-circle me-2"></i> <?php echo htmlspecialchars($success); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Form -->
    <div class="form-card">
        <form method="POST" action="">
            <div class="row g-4">
                <!-- Left Column -->
                <div class="col-md-8">
                    <!-- Product Name -->
                    <div class="mb-3">
                        <label class="form-label">Product Name <span class="required">*</span></label>
                        <input type="text" name="product_name" class="form-control" 
                               placeholder="Enter product name" required
                               value="<?php echo htmlspecialchars($_POST['product_name'] ?? ''); ?>">
                    </div>

                    <!-- Description -->
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="5" 
                                  placeholder="Enter product description"><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                    </div>

                    <!-- SKU -->
                    <div class="mb-3">
                        <label class="form-label">SKU</label>
                        <input type="text" name="sku" class="form-control" 
                               placeholder="Enter SKU (optional)"
                               value="<?php echo htmlspecialchars($_POST['sku'] ?? ''); ?>">
                    </div>

                    <!-- Price, Cost Price, and Quantity -->
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Selling Price (TSh) <span class="required">*</span></label>
                            <input type="number" name="price" class="form-control" 
                                   placeholder="0.00" step="0.01" min="0" required
                                   value="<?php echo htmlspecialchars($_POST['price'] ?? ''); ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Cost Price (TSh)</label>
                            <input type="number" name="cost_price" class="form-control" 
                                   placeholder="0.00" step="0.01" min="0"
                                   value="<?php echo htmlspecialchars($_POST['cost_price'] ?? ''); ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Quantity <span class="required">*</span></label>
                            <input type="number" name="quantity" class="form-control" 
                                   placeholder="0" min="0" required
                                   value="<?php echo htmlspecialchars($_POST['quantity'] ?? 0); ?>">
                        </div>
                    </div>

                    <!-- Min Order and Weight -->
                    <div class="row g-3 mt-2">
                        <div class="col-md-6">
                            <label class="form-label">Minimum Order</label>
                            <input type="number" name="min_order" class="form-control" 
                                   placeholder="1" min="1"
                                   value="<?php echo htmlspecialchars($_POST['min_order'] ?? 1); ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Weight (kg)</label>
                            <input type="number" name="weight" class="form-control" 
                                   placeholder="0.00" step="0.01"
                                   value="<?php echo htmlspecialchars($_POST['weight'] ?? ''); ?>">
                        </div>
                    </div>

                    <!-- Dimensions -->
                    <div class="mb-3 mt-3">
                        <label class="form-label">Dimensions</label>
                        <input type="text" name="dimensions" class="form-control" 
                               placeholder="e.g., 30x20x10 cm"
                               value="<?php echo htmlspecialchars($_POST['dimensions'] ?? ''); ?>">
                    </div>
                </div>

                <!-- Right Column -->
                <div class="col-md-4">
                    <!-- Category -->
                    <div class="mb-3">
                        <label class="form-label">Category <span class="required">*</span></label>
                        <select name="category_id" class="form-select" required>
                            <option value="">Select Category</option>
                            <?php foreach($categories as $category): ?>
                                <option value="<?php echo $category['id']; ?>" 
                                    <?php echo (isset($_POST['category_id']) && $_POST['category_id'] == $category['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($category['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Brand -->
                    <div class="mb-3">
                        <label class="form-label">Brand</label>
                        <select name="brand_id" class="form-select">
                            <option value="">Select Brand</option>
                            <?php foreach($brands as $brand): ?>
                                <option value="<?php echo $brand['id']; ?>" 
                                    <?php echo (isset($_POST['brand_id']) && $_POST['brand_id'] == $brand['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($brand['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Supplier -->
                    <div class="mb-3">
                        <label class="form-label">Supplier <span class="required">*</span></label>
                        <select name="supplier_id" class="form-select" required>
                            <option value="">Select Supplier</option>
                            <?php foreach($suppliers as $supplier): ?>
                                <option value="<?php echo $supplier['id']; ?>" 
                                    <?php echo (isset($_POST['supplier_id']) && $_POST['supplier_id'] == $supplier['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($supplier['full_name']); ?>
                                    <?php echo !empty($supplier['company_name']) ? '(' . htmlspecialchars($supplier['company_name']) . ')' : ''; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Status -->
                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <option value="pending" <?php echo (isset($_POST['status']) && $_POST['status'] == 'pending') ? 'selected' : ''; ?>>Pending</option>
                            <option value="active" <?php echo (isset($_POST['status']) && $_POST['status'] == 'active') ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php echo (isset($_POST['status']) && $_POST['status'] == 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                    </div>

                    <!-- Featured -->
                    <div class="mb-3">
                        <div class="form-check form-switch">
                            <input type="checkbox" name="featured" class="form-check-input" id="featured" value="1"
                                <?php echo (isset($_POST['featured']) && $_POST['featured'] == 1) ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="featured">
                                <i class="fas fa-star text-warning me-1"></i> Featured Product
                            </label>
                        </div>
                    </div>

                    <!-- Submit Buttons -->
                    <div class="d-grid gap-2 mt-4">
                        <button type="submit" class="btn btn-submit">
                            <i class="fas fa-save me-2"></i>Save Product
                        </button>
                        <a href="manage-products.php" class="btn btn-cancel btn-outline-secondary">
                            <i class="fas fa-times me-2"></i>Cancel
                        </a>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Footer -->
<footer class="footer">
    <div class="container">
        <div class="row">
            <div class="col-md-4 mb-3">
                <h5><i class="fas fa-microchip me-2"></i>TechProcure Admin</h5>
                <p class="text-muted" style="color: rgba(255,255,255,0.6) !important;">Enterprise B2B IT equipment procurement platform.</p>
            </div>
            <div class="col-md-2 mb-3">
                <h6>Quick Links</h6>
                <ul class="list-unstyled">
                    <li><a href="../dashboard.php">Dashboard</a></li>
                    <li><a href="manage-products.php">Products</a></li>
                    <li><a href="../orders/manage-orders.php">Orders</a></li>
                </ul>
            </div>
            <div class="col-md-3 mb-3">
                <h6>Management</h6>
                <ul class="list-unstyled">
                    <li><a href="../users/manage-users.php">Users</a></li>
                    <li><a href="../suppliers/manage-suppliers.php">Suppliers</a></li>
                    <li><a href="../payments/transactions.php">Payments</a></li>
                </ul>
            </div>
            <div class="col-md-3 mb-3">
                <h6>Contact</h6>
                <ul class="list-unstyled">
                    <li><i class="fas fa-envelope me-2"></i> support@techprocure.co.tz</li>
                    <li><i class="fas fa-phone me-2"></i> +255 123 456 789</li>
                </ul>
            </div>
        </div>
        <hr>
        <div class="text-center">
            <small style="color: rgba(255,255,255,0.5);">&copy; <?php echo date('Y'); ?> TechProcure Tanzania. All rights reserved.</small>
        </div>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
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