<?php
/**
 * TechProcure Tanzania - Supplier Add Product
 * File: supplier/products/add-product.php
 * Description: Suppliers can add new products
 */

// Start session if not started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include configuration files
require_once '../../includes/config.php';
require_once '../../includes/db.php';
require_once '../../includes/functions.php';

// =============================================
// DEFINE MISSING FUNCTIONS IF NOT EXISTS
// =============================================
if (!function_exists('sanitize')) {
    function sanitize($input) {
        if (is_array($input)) {
            return array_map('sanitize', $input);
        }
        return htmlspecialchars(strip_tags(trim($input)));
    }
}

if (!function_exists('generateCSRFToken')) {
    function generateCSRFToken() {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            $_SESSION['csrf_token_time'] = time();
        }
        return $_SESSION['csrf_token'];
    }
}

if (!function_exists('verifyCSRFToken')) {
    function verifyCSRFToken($token) {
        if (empty($token) || empty($_SESSION['csrf_token'])) {
            return false;
        }
        if (isset($_SESSION['csrf_token_time']) && (time() - $_SESSION['csrf_token_time']) > 3600) {
            unset($_SESSION['csrf_token']);
            unset($_SESSION['csrf_token_time']);
            return false;
        }
        return hash_equals($_SESSION['csrf_token'], $token);
    }
}

if (!function_exists('format_price')) {
    function format_price($price, $currency = 'TSh') {
        if ($price === null || $price === '') return $currency . ' 0.00';
        return $currency . ' ' . number_format((float)$price, 2);
    }
}

if (!function_exists('create_notification')) {
    function create_notification($type, $user_id, $title, $message, $entity_type = null, $link = null) {
        try {
            global $conn;
            if (!isset($conn) || $conn === null) {
                return false;
            }
            $sql = "INSERT INTO notifications (user_id, type, title, message, entity_type, link, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, NOW())";
            $stmt = $conn->prepare($sql);
            return $stmt->execute([$user_id, $type, $title, $message, $entity_type, $link]);
        } catch (Exception $e) {
            return false;
        }
    }
}

// =============================================
// GET DATABASE CONNECTION
// =============================================
$db = getDB();
$conn = $db;

// =============================================
// CHECK SUPPLIER AUTHENTICATION
// =============================================
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type'])) {
    header('Location: ../../auth/login.php');
    exit();
}

if ($_SESSION['user_type'] !== 'supplier') {
    header('Location: ../../index.php');
    exit();
}

$supplier_id = $_SESSION['user_id'];
$supplier_name = $_SESSION['company_name'] ?? $_SESSION['user_name'] ?? 'Supplier';

// Generate CSRF token
$csrf_token = generateCSRFToken();

// =============================================
// FETCH CATEGORIES AND BRANDS
// =============================================
$categories = [];
$brands = [];
$error = '';

try {
    // Get categories
    $cat_stmt = $conn->query("SELECT id, name FROM categories WHERE status = 'active' ORDER BY name");
    if ($cat_stmt) {
        $categories = $cat_stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Get brands
    $brand_stmt = $conn->query("SELECT id, name FROM brands WHERE status = 'active' ORDER BY name");
    if ($brand_stmt) {
        $brands = $brand_stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
} catch (PDOException $e) {
    $error = "Failed to load data: " . $e->getMessage();
}

// =============================================
// PROCESS PRODUCT ADDITION
// =============================================
$form_data = [];
$success_message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $submitted_token = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
    
    if (!verifyCSRFToken($submitted_token)) {
        $error = 'Security token validation failed. Please refresh the page and try again.';
    } else {
        // Sanitize all inputs
        $name = sanitize($_POST['name'] ?? '');
        $description = sanitize($_POST['description'] ?? '');
        $short_description = sanitize($_POST['short_description'] ?? '');
        $category_id = (int)($_POST['category_id'] ?? 0);
        $brand_id = (int)($_POST['brand_id'] ?? 0);
        $price_tsh = (float)($_POST['price'] ?? 0);
        $compare_price_tsh = (float)($_POST['compare_price'] ?? 0);
        $cost_price_tsh = (float)($_POST['cost_price'] ?? 0);
        $quantity = (int)($_POST['quantity'] ?? 0);
        $min_quantity = (int)($_POST['min_quantity'] ?? 1);
        $sku = sanitize($_POST['sku'] ?? '');
        $weight = (float)($_POST['weight'] ?? 0);
        $status = sanitize($_POST['status'] ?? 'active');
        $is_featured = isset($_POST['is_featured']) ? 1 : 0;
        $tags = sanitize($_POST['tags'] ?? '');
        $meta_title = sanitize($_POST['meta_title'] ?? '');
        $meta_description = sanitize($_POST['meta_description'] ?? '');
        $meta_keywords = sanitize($_POST['meta_keywords'] ?? '');
        
        // Store form data for repopulation
        $form_data = [
            'name' => $name,
            'description' => $description,
            'short_description' => $short_description,
            'category_id' => $category_id,
            'brand_id' => $brand_id,
            'price_tsh' => $price_tsh,
            'compare_price_tsh' => $compare_price_tsh,
            'cost_price_tsh' => $cost_price_tsh,
            'quantity' => $quantity,
            'min_quantity' => $min_quantity,
            'sku' => $sku,
            'weight' => $weight,
            'status' => $status,
            'is_featured' => $is_featured,
            'tags' => $tags
        ];
        
        // Validate required fields
        $errors = [];
        if (empty($name)) $errors[] = "Product name is required.";
        if (empty($category_id)) $errors[] = "Please select a category.";
        if ($price_tsh <= 0) $errors[] = "Please enter a valid price.";
        if (empty($sku)) $errors[] = "SKU is required.";
        
        if (empty($errors)) {
            try {
                $conn->beginTransaction();
                
                // Generate product code and slug
                $product_code = 'PRD-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
                $slug = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', $name), '-'));
                $slug = $slug . '-' . substr(uniqid(), -6);
                
                // Get brand name if brand selected
                $brand_name = '';
                if ($brand_id > 0) {
                    $brand_stmt = $conn->prepare("SELECT name FROM brands WHERE id = ?");
                    $brand_stmt->execute([$brand_id]);
                    $brand_result = $brand_stmt->fetch(PDO::FETCH_ASSOC);
                    $brand_name = $brand_result ? $brand_result['name'] : '';
                }
                
                // =============================================
                // CHECK EXISTING COLUMNS IN PRODUCTS TABLE
                // =============================================
                $colCheck = $conn->query("SHOW COLUMNS FROM products");
                $existingColumns = [];
                while ($col = $colCheck->fetch(PDO::FETCH_ASSOC)) {
                    $existingColumns[] = $col['Field'];
                }
                
                // Build dynamic insert based on existing columns
                $fields = [];
                $placeholders = [];
                $values = [];
                
                // Map form data to database fields (supplier version)
                $fieldMapping = [];
                
                // Check for supplier product columns (different from admin)
                if (in_array('product_code', $existingColumns)) {
                    $fieldMapping['product_code'] = $product_code;
                }
                
                if (in_array('name', $existingColumns)) {
                    $fieldMapping['name'] = $name;
                } elseif (in_array('product_name', $existingColumns)) {
                    $fieldMapping['product_name'] = $name;
                }
                
                if (in_array('description', $existingColumns)) {
                    $fieldMapping['description'] = $description;
                }
                
                if (in_array('short_description', $existingColumns)) {
                    $fieldMapping['short_description'] = $short_description;
                }
                
                if (in_array('category_id', $existingColumns)) {
                    $fieldMapping['category_id'] = $category_id;
                }
                
                if (in_array('brand_id', $existingColumns)) {
                    $fieldMapping['brand_id'] = $brand_id ?: null;
                }
                
                if (in_array('brand', $existingColumns)) {
                    $fieldMapping['brand'] = $brand_name;
                }
                
                if (in_array('supplier_id', $existingColumns)) {
                    $fieldMapping['supplier_id'] = $supplier_id;
                }
                
                if (in_array('price', $existingColumns)) {
                    $fieldMapping['price'] = $price_tsh;
                } elseif (in_array('price_tsh', $existingColumns)) {
                    $fieldMapping['price_tsh'] = $price_tsh;
                }
                
                if (in_array('compare_price', $existingColumns)) {
                    $fieldMapping['compare_price'] = $compare_price_tsh ?: null;
                } elseif (in_array('compare_price_tsh', $existingColumns)) {
                    $fieldMapping['compare_price_tsh'] = $compare_price_tsh ?: null;
                }
                
                if (in_array('cost_price', $existingColumns)) {
                    $fieldMapping['cost_price'] = $cost_price_tsh ?: null;
                } elseif (in_array('cost_price_tsh', $existingColumns)) {
                    $fieldMapping['cost_price_tsh'] = $cost_price_tsh ?: null;
                }
                
                if (in_array('quantity', $existingColumns)) {
                    $fieldMapping['quantity'] = $quantity;
                }
                
                if (in_array('quantity_available', $existingColumns)) {
                    $fieldMapping['quantity_available'] = $quantity;
                }
                
                if (in_array('min_quantity', $existingColumns)) {
                    $fieldMapping['min_quantity'] = $min_quantity;
                } elseif (in_array('min_order_quantity', $existingColumns)) {
                    $fieldMapping['min_order_quantity'] = $min_quantity;
                }
                
                if (in_array('sku', $existingColumns)) {
                    $fieldMapping['sku'] = $sku;
                }
                
                if (in_array('weight', $existingColumns)) {
                    $fieldMapping['weight'] = $weight ?: null;
                } elseif (in_array('weight_kg', $existingColumns)) {
                    $fieldMapping['weight_kg'] = $weight ?: null;
                }
                
                if (in_array('status', $existingColumns)) {
                    $fieldMapping['status'] = $status;
                }
                
                if (in_array('is_featured', $existingColumns)) {
                    $fieldMapping['is_featured'] = $is_featured;
                } elseif (in_array('featured', $existingColumns)) {
                    $fieldMapping['featured'] = $is_featured;
                }
                
                if (in_array('is_active', $existingColumns)) {
                    $fieldMapping['is_active'] = 1;
                }
                
                if (in_array('tags', $existingColumns)) {
                    $fieldMapping['tags'] = $tags;
                }
                
                if (in_array('slug', $existingColumns)) {
                    $fieldMapping['slug'] = $slug;
                }
                
                if (in_array('meta_title', $existingColumns)) {
                    $fieldMapping['meta_title'] = $meta_title;
                }
                
                if (in_array('meta_description', $existingColumns)) {
                    $fieldMapping['meta_description'] = $meta_description;
                }
                
                if (in_array('meta_keywords', $existingColumns)) {
                    $fieldMapping['meta_keywords'] = $meta_keywords;
                }
                
                if (in_array('approval_status', $existingColumns)) {
                    $fieldMapping['approval_status'] = 'pending';
                }
                
                if (in_array('created_by', $existingColumns)) {
                    $fieldMapping['created_by'] = $supplier_id;
                }
                
                if (in_array('created_at', $existingColumns)) {
                    $fieldMapping['created_at'] = date('Y-m-d H:i:s');
                }
                
                if (in_array('updated_at', $existingColumns)) {
                    $fieldMapping['updated_at'] = date('Y-m-d H:i:s');
                }
                
                // Build the query with only existing columns
                foreach ($fieldMapping as $field => $value) {
                    if (in_array($field, $existingColumns)) {
                        $fields[] = $field;
                        $placeholders[] = '?';
                        $values[] = $value;
                    }
                }
                
                if (empty($fields)) {
                    throw new Exception("No matching columns found in products table.");
                }
                
                $sql = "INSERT INTO products (" . implode(", ", $fields) . ") VALUES (" . implode(", ", $placeholders) . ")";
                $stmt = $conn->prepare($sql);
                $stmt->execute($values);
                
                $product_id = $conn->lastInsertId();
                
                // =============================================
                // INSERT INTO INVENTORY (if table exists)
                // =============================================
                try {
                    $invTableCheck = $conn->query("SHOW TABLES LIKE 'inventory'");
                    if ($invTableCheck->rowCount() > 0) {
                        $invColCheck = $conn->query("SHOW COLUMNS FROM inventory");
                        $invColumns = [];
                        while ($invCol = $invColCheck->fetch(PDO::FETCH_ASSOC)) {
                            $invColumns[] = $invCol['Field'];
                        }
                        
                        $invFields = [];
                        $invPlaceholders = [];
                        $invValues = [];
                        
                        if (in_array('product_id', $invColumns)) {
                            $invFields[] = 'product_id';
                            $invPlaceholders[] = '?';
                            $invValues[] = $product_id;
                        }
                        
                        if (in_array('quantity_available', $invColumns)) {
                            $invFields[] = 'quantity_available';
                            $invPlaceholders[] = '?';
                            $invValues[] = $quantity;
                        }
                        
                        if (in_array('reorder_level', $invColumns)) {
                            $invFields[] = 'reorder_level';
                            $invPlaceholders[] = '?';
                            $invValues[] = 5;
                        }
                        
                        if (!empty($invFields)) {
                            $inv_sql = "INSERT INTO inventory (" . implode(", ", $invFields) . ") VALUES (" . implode(", ", $invPlaceholders) . ")";
                            $inv_stmt = $conn->prepare($inv_sql);
                            $inv_stmt->execute($invValues);
                        }
                    }
                } catch (PDOException $e) {
                    // Inventory table might not exist - skip silently
                }
                
                // =============================================
                // HANDLE IMAGE UPLOAD
                // =============================================
                if (isset($_FILES['product_images']) && !empty($_FILES['product_images']['name'][0])) {
                    try {
                        $imageTableCheck = $conn->query("SHOW TABLES LIKE 'product_images'");
                        if ($imageTableCheck->rowCount() > 0) {
                            $base_upload_dir = '../../uploads/';
                            $product_upload_dir = $base_upload_dir . 'products/';
                            
                            if (!is_dir($product_upload_dir)) {
                                mkdir($product_upload_dir, 0777, true);
                            }
                            
                            $product_dir = $product_upload_dir . $product_id . '/';
                            if (!is_dir($product_dir)) {
                                mkdir($product_dir, 0777, true);
                            }
                            
                            $imageColCheck = $conn->query("SHOW COLUMNS FROM product_images");
                            $imageColumns = [];
                            while ($imgCol = $imageColCheck->fetch(PDO::FETCH_ASSOC)) {
                                $imageColumns[] = $imgCol['Field'];
                            }
                            
                            $imageFields = [];
                            $imagePlaceholders = [];
                            
                            if (in_array('product_id', $imageColumns)) {
                                $imageFields[] = 'product_id';
                                $imagePlaceholders[] = '?';
                            }
                            if (in_array('image_path', $imageColumns)) {
                                $imageFields[] = 'image_path';
                                $imagePlaceholders[] = '?';
                            }
                            if (in_array('is_primary', $imageColumns)) {
                                $imageFields[] = 'is_primary';
                                $imagePlaceholders[] = '?';
                            }
                            
                            if (!empty($imageFields)) {
                                $image_sql = "INSERT INTO product_images (" . implode(", ", $imageFields) . ") VALUES (" . implode(", ", $imagePlaceholders) . ")";
                                $image_stmt = $conn->prepare($image_sql);
                                
                                foreach ($_FILES['product_images']['name'] as $key => $filename) {
                                    if ($_FILES['product_images']['error'][$key] == 0) {
                                        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                                        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                                        
                                        if (in_array($ext, $allowed)) {
                                            $new_filename = uniqid() . '.' . $ext;
                                            $destination = $product_dir . $new_filename;
                                            
                                            if (move_uploaded_file($_FILES['product_images']['tmp_name'][$key], $destination)) {
                                                $is_primary = ($key == 0) ? 1 : 0;
                                                $image_path = 'products/' . $product_id . '/' . $new_filename;
                                                
                                                $imageValues = [];
                                                if (in_array('product_id', $imageColumns)) {
                                                    $imageValues[] = $product_id;
                                                }
                                                if (in_array('image_path', $imageColumns)) {
                                                    $imageValues[] = $image_path;
                                                }
                                                if (in_array('is_primary', $imageColumns)) {
                                                    $imageValues[] = $is_primary;
                                                }
                                                
                                                if (!empty($imageValues)) {
                                                    $image_stmt->execute($imageValues);
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    } catch (PDOException $e) {
                        // Images table might not exist - skip silently
                    }
                }
                
                // Create notification for admin
                try {
                    create_notification('admin', 1, 'New Product Added', 
                        'A new product "' . $name . '" has been added by ' . $supplier_name . ' and is pending approval.',
                        'product', '../../admin/products/manage-products.php');
                } catch (Exception $e) {
                    // Skip if notification function fails
                }
                
                $conn->commit();
                
                // Generate new CSRF token
                unset($_SESSION['csrf_token']);
                unset($_SESSION['csrf_token_time']);
                $csrf_token = generateCSRFToken();
                
                $success_message = "Product '" . $name . "' added successfully! It will be visible once approved by admin.";
                
                // Clear form data
                $form_data = [];
                
            } catch (Exception $e) {
                $conn->rollBack();
                $error = "Failed to add product: " . $e->getMessage();
            }
        } else {
            $error = implode("<br>", $errors);
        }
    }
}

// =============================================
// GET CART COUNT FOR NAVBAR
// =============================================
$cart_count = 0;
try {
    if (isset($_SESSION['user_id'])) {
        $sql = "SELECT COUNT(*) as total FROM cart_items ci 
                JOIN carts c ON ci.cart_id = c.id 
                WHERE c.customer_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$_SESSION['user_id']]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $cart_count = $result ? (int)$result['total'] : 0;
    }
} catch (PDOException $e) {
    $cart_count = 0;
}

// =============================================
// PAGE TITLE
// =============================================
$page_title = 'Add Product - Supplier Panel';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
    <title><?php echo $page_title; ?></title>
    
    <!-- CSS Dependencies -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f8f9fa;
        }
        
        .navbar {
            background: linear-gradient(135deg, #1a2a6c, #b21f1f, #fdbb4d);
            padding: 15px 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
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
        
        .dashboard-wrapper {
            display: flex;
            margin-top: 20px;
            min-height: calc(100vh - 150px);
        }
        
        .dashboard-sidebar {
            width: 260px;
            background: white;
            border-radius: 15px;
            padding: 20px 0;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            flex-shrink: 0;
            margin-right: 24px;
            position: sticky;
            top: 20px;
            height: fit-content;
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
            background: linear-gradient(135deg, #198754, #157347);
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
            display: flex;
            align-items: center;
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
            margin-left: auto;
        }
        
        .dashboard-content {
            flex: 1;
        }
        
        .form-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 24px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        
        .form-card .card-title {
            font-weight: 600;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #e9ecef;
        }
        
        .form-card .card-title i {
            margin-right: 8px;
            color: #198754;
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
            border-color: #198754;
            box-shadow: 0 0 0 3px rgba(25,135,84,0.1);
            outline: none;
        }
        
        .btn-save {
            background: linear-gradient(135deg, #198754, #157347);
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
            box-shadow: 0 5px 15px rgba(25,135,84,0.3);
        }
        
        .btn-save:disabled {
            opacity: 0.6;
            cursor: not-allowed;
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
        
        .required:after {
            content: " *";
            color: red;
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
            .dashboard-wrapper {
                flex-direction: column;
            }
            .dashboard-sidebar {
                width: 100%;
                margin-right: 0;
                margin-bottom: 20px;
                position: static;
            }
            .form-card {
                padding: 20px;
            }
        }
    </style>
</head>
<body>

<!-- ===================================================== -->
<!-- NAVBAR -->
<!-- ===================================================== -->
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

<!-- ===================================================== -->
<!-- DASHBOARD -->
<!-- ===================================================== -->
<div class="container">
    <div class="dashboard-wrapper">
        
        <!-- Sidebar -->
        <div class="dashboard-sidebar">
            <div class="user-avatar">
                <div class="avatar-circle">
                    <?php echo strtoupper(substr($supplier_name, 0, 1)); ?>
                </div>
                <h6><?php echo htmlspecialchars($supplier_name); ?></h6>
                <small><?php echo htmlspecialchars($_SESSION['user_email'] ?? ''); ?></small>
            </div>
            
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link" href="../dashboard.php">
                        <i class="fas fa-tachometer-alt"></i> Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="my-products.php">
                        <i class="fas fa-box"></i> My Products
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="add-product.php">
                        <i class="fas fa-plus-circle"></i> Add Product
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="../orders/supplier-orders.php">
                        <i class="fas fa-shopping-cart"></i> Orders
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="../earnings/earnings.php">
                        <i class="fas fa-money-bill-wave"></i> Earnings
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="../quotations/quotation-requests.php">
                        <i class="fas fa-file-alt"></i> Quotations
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="../profile.php">
                        <i class="fas fa-user"></i> Profile
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
        
        <!-- Dashboard Content -->
        <div class="dashboard-content">
            
            <!-- Page Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h4 class="mb-0"><i class="fas fa-plus-circle me-2 text-success"></i>Add New Product</h4>
                    <p class="text-muted">Add a new product to your catalog</p>
                </div>
                <div>
                    <span class="badge bg-success">Supplier</span>
                </div>
            </div>
            
            <!-- Success/Error Messages -->
            <?php if($success_message): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <i class="fas fa-check-circle me-2"></i> <?php echo $success_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
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
                
                <!-- Category & Brand -->
                <div class="form-card">
                    <div class="card-title">
                        <i class="fas fa-tags"></i> Category & Brand
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
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
                        <div class="col-md-6 mb-3">
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
                                   value="<?php echo htmlspecialchars($form_data['price_tsh'] ?? ''); ?>" required>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Compare Price (TSh)</label>
                            <input type="number" name="compare_price" class="form-control" placeholder="0.00" step="0.01" min="0"
                                   value="<?php echo htmlspecialchars($form_data['compare_price_tsh'] ?? ''); ?>">
                            <small class="text-muted">Original price for discount display</small>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Cost Price (TSh)</label>
                            <input type="number" name="cost_price" class="form-control" placeholder="0.00" step="0.01" min="0"
                                   value="<?php echo htmlspecialchars($form_data['cost_price_tsh'] ?? ''); ?>">
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
                                <option value="active" <?php echo (isset($form_data['status']) && $form_data['status'] == 'active') ? 'selected' : ''; ?>>Active</option>
                                <option value="inactive" <?php echo (isset($form_data['status']) && $form_data['status'] == 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                                <option value="out_of_stock" <?php echo (isset($form_data['status']) && $form_data['status'] == 'out_of_stock') ? 'selected' : ''; ?>>Out of Stock</option>
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
                    <button type="submit" class="btn-save" id="submitBtn">
                        <i class="fas fa-save me-2"></i> Add Product
                    </button>
                    <a href="my-products.php" class="btn-cancel">
                        <i class="fas fa-times me-2"></i> Cancel
                    </a>
                </div>
            </form>
            
        </div>
    </div>
</div>

<!-- ===================================================== -->
<!-- FOOTER -->
<!-- ===================================================== -->
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
                <h6>Supplier</h6>
                <ul class="list-unstyled">
                    <li><a href="../dashboard.php">Dashboard</a></li>
                    <li><a href="my-products.php">My Products</a></li>
                    <li><a href="../orders/supplier-orders.php">Orders</a></li>
                    <li><a href="../earnings/earnings.php">Earnings</a></li>
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

<!-- ===================================================== -->
<!-- SCRIPTS -->
<!-- ===================================================== -->
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
// FORM SUBMISSION
// =============================================
document.getElementById('productForm')?.addEventListener('submit', function(e) {
    const submitBtn = document.getElementById('submitBtn');
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Adding Product...';
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