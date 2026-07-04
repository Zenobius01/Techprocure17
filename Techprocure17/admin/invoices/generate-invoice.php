<?php
/**
 * TechProcure Tanzania - Admin Generate Invoice
 * File: admin/invoices/generate-invoice.php
 * Description: Generate new invoices from orders (Self-contained with AJAX)
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
if (!function_exists('format_price')) {
    function format_price($price, $currency = CURRENCY_SYMBOL) {
        if ($price === null || $price === '') return $currency . ' 0.00';
        return $currency . ' ' . number_format((float)$price, 2);
    }
}

if (!function_exists('sanitize')) {
    function sanitize($input) {
        if (is_array($input)) {
            return array_map('sanitize', $input);
        }
        return htmlspecialchars(strip_tags(trim($input)));
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field() {
        return '<input type="hidden" name="csrf_token" value="' . ($_SESSION['csrf_token'] ?? '') . '">';
    }
}

if (!function_exists('verifyCSRFToken')) {
    function verifyCSRFToken($token) {
        if (empty($token) || empty($_SESSION['csrf_token'])) {
            return false;
        }
        return hash_equals($_SESSION['csrf_token'], $token);
    }
}

if (!function_exists('log_activity')) {
    function log_activity($user_type, $user_id, $action, $details = null) {
        error_log("Activity: $user_type | $user_id | $action | $details");
        return true;
    }
}

// =============================================
// ADMIN AUTHENTICATION CHECK
// =============================================
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: ../../auth/login.php');
    exit();
}

$page_title = 'Generate Invoice - Admin Panel';
$admin_name = $_SESSION['user_name'] ?? 'Admin';

// Use PDO connection
$conn = $db;

// =============================================
// HANDLE AJAX REQUESTS
// =============================================
if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
    header('Content-Type: application/json');
    
    $action = $_GET['action'] ?? '';
    $response = ['success' => false, 'message' => 'Invalid action'];
    
    if ($action == 'get_order_details') {
        $order_id = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;
        
        if ($order_id <= 0) {
            $response = ['success' => false, 'message' => 'Invalid order ID'];
            echo json_encode($response);
            exit();
        }
        
        try {
            // Check customer column
            $customer_column = 'customer_id';
            $check_stmt = $conn->query("SHOW COLUMNS FROM orders LIKE 'customer_id'");
            if ($check_stmt->rowCount() == 0) {
                $check_stmt = $conn->query("SHOW COLUMNS FROM orders LIKE 'buyer_id'");
                if ($check_stmt->rowCount() > 0) {
                    $customer_column = 'buyer_id';
                } else {
                    $check_stmt = $conn->query("SHOW COLUMNS FROM orders LIKE 'user_id'");
                    if ($check_stmt->rowCount() > 0) {
                        $customer_column = 'user_id';
                    }
                }
            }
            
            // Get order details
            $sql = "SELECT o.*, 
                    b.company_name as customer_name,
                    b.email as customer_email,
                    b.phone as customer_phone,
                    b.address as customer_address
                    FROM orders o
                    JOIN customers b ON o.$customer_column = b.id
                    WHERE o.id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(1, $order_id, PDO::PARAM_INT);
            $stmt->execute();
            $order = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$order) {
                $response = ['success' => false, 'message' => 'Order not found'];
                echo json_encode($response);
                exit();
            }
            
            // Get order items
            $items_sql = "SELECT oi.*, p.product_name 
                          FROM order_items oi
                          JOIN products p ON oi.product_id = p.id
                          WHERE oi.order_id = ?";
            $items_stmt = $conn->prepare($items_sql);
            $items_stmt->bindParam(1, $order_id, PDO::PARAM_INT);
            $items_stmt->execute();
            $items = $items_stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $order['items'] = $items;
            
            $response = [
                'success' => true,
                'order' => $order
            ];
            
        } catch (PDOException $e) {
            $response = ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
        }
        
        echo json_encode($response);
        exit();
    }
    
    echo json_encode($response);
    exit();
}

// =============================================
// GET ORDER LIST FOR DROPDOWN
// =============================================
$orders = [];
try {
    // Check customer column
    $customer_column = 'customer_id';
    $check_stmt = $conn->query("SHOW COLUMNS FROM orders LIKE 'customer_id'");
    if ($check_stmt->rowCount() == 0) {
        $check_stmt = $conn->query("SHOW COLUMNS FROM orders LIKE 'buyer_id'");
        if ($check_stmt->rowCount() > 0) {
            $customer_column = 'buyer_id';
        } else {
            $check_stmt = $conn->query("SHOW COLUMNS FROM orders LIKE 'user_id'");
            if ($check_stmt->rowCount() > 0) {
                $customer_column = 'user_id';
            }
        }
    }
    
    // Get orders that don't have invoices yet or have draft invoices
    $sql = "SELECT o.*, 
            b.company_name as customer_name,
            b.email as customer_email
            FROM orders o
            JOIN customers b ON o.$customer_column = b.id
            LEFT JOIN invoices i ON o.id = i.order_id
            WHERE (i.id IS NULL OR i.status = 'cancelled')
            AND o.order_status IN ('delivered', 'completed', 'processing', 'shipped')
            ORDER BY o.created_at DESC
            LIMIT 50";
    
    $stmt = $conn->query($sql);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $error = "Failed to load orders: " . $e->getMessage();
}

// =============================================
// PROCESS FORM SUBMISSION
// =============================================
$error = '';
$success = '';
$invoice_data = [];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    verifyCSRFToken($_POST['csrf_token'] ?? '');
    
    $order_id = (int)($_POST['order_id'] ?? 0);
    $invoice_number = trim($_POST['invoice_number'] ?? '');
    $due_date = $_POST['due_date'] ?? '';
    $notes = trim($_POST['notes'] ?? '');
    
    // Validate
    if ($order_id <= 0) {
        $error = 'Please select an order.';
    } elseif (empty($invoice_number)) {
        $error = 'Please enter an invoice number.';
    } elseif (empty($due_date)) {
        $error = 'Please select a due date.';
    } else {
        try {
            // Check if invoice number already exists
            $check_sql = "SELECT id FROM invoices WHERE invoice_number = ?";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->bindParam(1, $invoice_number, PDO::PARAM_STR);
            $check_stmt->execute();
            
            if ($check_stmt->rowCount() > 0) {
                $error = 'Invoice number already exists. Please use a unique number.';
            } else {
                // Get order details
                $order_sql = "SELECT o.*, 
                              b.company_name as customer_name,
                              b.email as customer_email,
                              b.phone as customer_phone,
                              b.address as customer_address,
                              s.company_name as supplier_name
                              FROM orders o
                              JOIN customers b ON o.$customer_column = b.id
                              LEFT JOIN suppliers s ON o.supplier_id = s.id
                              WHERE o.id = ?";
                $order_stmt = $conn->prepare($order_sql);
                $order_stmt->bindParam(1, $order_id, PDO::PARAM_INT);
                $order_stmt->execute();
                $order = $order_stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$order) {
                    $error = 'Order not found.';
                } else {
                    // Check if order already has an invoice
                    $check_inv_sql = "SELECT id FROM invoices WHERE order_id = ? AND status != 'cancelled'";
                    $check_inv_stmt = $conn->prepare($check_inv_sql);
                    $check_inv_stmt->bindParam(1, $order_id, PDO::PARAM_INT);
                    $check_inv_stmt->execute();
                    
                    if ($check_inv_stmt->rowCount() > 0) {
                        $error = 'This order already has an active invoice.';
                    } else {
                        // Get order items
                        $items_sql = "SELECT oi.*, p.product_name 
                                      FROM order_items oi
                                      JOIN products p ON oi.product_id = p.id
                                      WHERE oi.order_id = ?";
                        $items_stmt = $conn->prepare($items_sql);
                        $items_stmt->bindParam(1, $order_id, PDO::PARAM_INT);
                        $items_stmt->execute();
                        $items = $items_stmt->fetchAll(PDO::FETCH_ASSOC);
                        
                        // Calculate totals
                        $total_amount = $order['total_amount'] ?? 0;
                        $vat_amount = $order['vat_amount'] ?? 0;
                        $subtotal = $total_amount - $vat_amount;
                        
                        // Generate invoice data as JSON
                        $invoice_data = [
                            'order_number' => $order['order_number'],
                            'customer_name' => $order['customer_name'],
                            'customer_email' => $order['customer_email'],
                            'customer_phone' => $order['customer_phone'] ?? '',
                            'customer_address' => $order['customer_address'] ?? '',
                            'supplier_name' => $order['supplier_name'] ?? 'N/A',
                            'items' => $items,
                            'subtotal' => $subtotal,
                            'vat_amount' => $vat_amount,
                            'total_amount' => $total_amount,
                            'created_at' => date('Y-m-d H:i:s')
                        ];
                        
                        // Insert invoice
                        $insert_sql = "INSERT INTO invoices (
                            order_id, 
                            invoice_number, 
                            invoice_data, 
                            status, 
                            due_date, 
                            notes,
                            generated_at
                        ) VALUES (?, ?, ?, 'draft', ?, ?, NOW())";
                        
                        $insert_stmt = $conn->prepare($insert_sql);
                        $invoice_data_json = json_encode($invoice_data);
                        $insert_stmt->bindParam(1, $order_id, PDO::PARAM_INT);
                        $insert_stmt->bindParam(2, $invoice_number, PDO::PARAM_STR);
                        $insert_stmt->bindParam(3, $invoice_data_json, PDO::PARAM_STR);
                        $insert_stmt->bindParam(4, $due_date, PDO::PARAM_STR);
                        $insert_stmt->bindParam(5, $notes, PDO::PARAM_STR);
                        
                        if ($insert_stmt->execute()) {
                            $invoice_id = $conn->lastInsertId();
                            log_activity('admin', $_SESSION['user_id'], 'create_invoice', "Created invoice: $invoice_number for order: {$order['order_number']}");
                            $_SESSION['success'] = "Invoice <strong>$invoice_number</strong> created successfully!";
                            header('Location: invoice-details.php?id=' . $invoice_id);
                            exit();
                        } else {
                            $error = 'Failed to create invoice. Please try again.';
                        }
                    }
                }
            }
        } catch (PDOException $e) {
            $error = 'Database error: ' . $e->getMessage();
        }
    }
}
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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f0f2f5;
        }
        
        /* Top Navigation */
        .top-navbar {
            background: linear-gradient(135deg, #1a2a6c, #b21f1f, #fdbb4d);
            padding: 15px 0;
            color: white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .top-navbar .brand {
            font-size: 1.4rem;
            font-weight: 700;
        }
        .top-navbar .brand i { margin-right: 8px; }
        .top-navbar .nav-link {
            color: rgba(255,255,255,0.9) !important;
            font-weight: 500;
            padding: 8px 16px;
        }
        .top-navbar .nav-link:hover { color: white !important; }
        .top-navbar .nav-link.active {
            background: rgba(255,255,255,0.15);
            border-radius: 8px;
            color: white !important;
        }
        .top-navbar .dropdown-menu {
            background: #1a1a2e;
            border: none;
        }
        .top-navbar .dropdown-item {
            color: rgba(255,255,255,0.8);
        }
        .top-navbar .dropdown-item:hover {
            background: rgba(255,255,255,0.1);
            color: white;
        }
        .admin-avatar {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            background: rgba(255,255,255,0.2);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 1rem;
        }
        
        /* Sidebar */
        .sidebar {
            position: fixed;
            top: 80px;
            left: 0;
            height: calc(100vh - 80px);
            width: 250px;
            background: white;
            box-shadow: 2px 0 10px rgba(0,0,0,0.05);
            padding: 20px 0;
            overflow-y: auto;
            z-index: 100;
        }
        .sidebar .nav-link {
            color: #333 !important;
            padding: 12px 25px;
            border-radius: 0;
            border-left: 3px solid transparent;
            transition: all 0.3s;
        }
        .sidebar .nav-link:hover {
            background: #f8f9fa;
            color: #0d6efd !important;
        }
        .sidebar .nav-link.active {
            background: #f0f7ff;
            color: #0d6efd !important;
            border-left-color: #0d6efd;
        }
        .sidebar .nav-link i { width: 22px; margin-right: 10px; }
        .sidebar .sidebar-footer {
            position: absolute;
            bottom: 0;
            width: 100%;
            padding: 15px;
            border-top: 1px solid #eee;
        }
        .sidebar .sidebar-footer a {
            color: #6c757d;
            text-decoration: none;
        }
        
        /* Main Content */
        .main-content {
            margin-left: 250px;
            padding: 30px;
            margin-top: 80px;
            min-height: calc(100vh - 80px);
        }
        
        /* Form Card */
        .form-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 20px;
        }
        .form-card .card-title {
            font-weight: 600;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #e9ecef;
        }
        .form-card .card-title i {
            margin-right: 8px;
            color: #0d6efd;
        }
        
        .form-label { font-weight: 500; font-size: 0.85rem; }
        .form-control, .form-select {
            border-radius: 10px;
            padding: 10px 15px;
            border: 1px solid #e0e0e0;
        }
        .form-control:focus, .form-select:focus {
            border-color: #0d6efd;
            box-shadow: 0 0 0 3px rgba(13,110,253,0.1);
        }
        
        .btn-save {
            background: linear-gradient(135deg, #0d6efd, #0b5ed7);
            border: none;
            border-radius: 10px;
            padding: 12px 30px;
            font-weight: 600;
            color: white;
            transition: all 0.3s;
        }
        .btn-save:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(13,110,253,0.3);
            color: white;
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
        
        .required:after { content: " *"; color: red; }
        
        /* Mobile Toggle */
        .sidebar-toggle {
            display: none;
            background: none;
            border: none;
            color: white;
            font-size: 1.5rem;
            padding: 0 10px;
        }
        
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s;
            }
            .sidebar.show { transform: translateX(0); }
            .main-content { margin-left: 0; padding: 15px; }
            .sidebar-toggle { display: block; }
            .form-card { padding: 15px; }
        }
        
        /* Order Preview */
        .order-preview {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 15px;
            margin-top: 10px;
            display: none;
        }
        .order-preview.active { display: block; }
        
        .order-item { padding: 8px 0; border-bottom: 1px solid #e9ecef; }
        .order-item:last-child { border-bottom: none; }
        
        /* Toast */
        .toast-container {
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 9999;
        }
        
        /* Loading Spinner */
        .loading-spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255,255,255,0.3);
            border-radius: 50%;
            border-top-color: #fff;
            animation: spin 0.8s ease-in-out infinite;
        }
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        .loading-text { display: none; }
        .loading .loading-text { display: inline; }
        .loading .btn-text { display: none; }
    </style>
</head>
<body>

<!-- ===================================================== -->
<!-- TOP NAVBAR -->
<!-- ===================================================== -->
<nav class="top-navbar">
    <div class="container-fluid px-4">
        <div class="d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center">
                <button class="sidebar-toggle me-3" onclick="toggleSidebar()">
                    <i class="fas fa-bars"></i>
                </button>
                <span class="brand">
                    <i class="fas fa-microchip"></i> TechProcure
                    <small class="opacity-75 ms-2">Admin Panel</small>
                </span>
            </div>
            
            <div class="d-flex align-items-center gap-3">
                <a href="../../index.php" class="text-white text-decoration-none small">
                    <i class="fas fa-globe me-1"></i> View Site
                </a>
                <div class="dropdown">
                    <button class="btn btn-link text-white text-decoration-none dropdown-toggle" data-bs-toggle="dropdown">
                        <span class="admin-avatar">
                            <?php echo strtoupper(substr($admin_name, 0, 1)); ?>
                        </span>
                        <span class="ms-2 d-none d-sm-inline"><?php echo htmlspecialchars($admin_name); ?></span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="../profile.php"><i class="fas fa-user me-2"></i>Profile</a></li>
                        <li><a class="dropdown-item" href="../settings.php"><i class="fas fa-cog me-2"></i>Settings</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-danger" href="../../auth/logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</nav>

<!-- ===================================================== -->
<!-- SIDEBAR -->
<!-- ===================================================== -->
<div class="sidebar" id="sidebar">
    <ul class="nav flex-column">
        <li class="nav-item">
            <a class="nav-link" href="../dashboard.php">
                <i class="fas fa-tachometer-alt"></i> Dashboard
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="../users/manage-users.php">
                <i class="fas fa-users"></i> Users
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="../suppliers/manage-suppliers.php">
                <i class="fas fa-truck"></i> Suppliers
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="../products/manage-products.php">
                <i class="fas fa-box"></i> Products
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="../orders/manage-orders.php">
                <i class="fas fa-shopping-cart"></i> Orders
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link active" href="invoices.php">
                <i class="fas fa-file-invoice"></i> Invoices
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="../payments/transactions.php">
                <i class="fas fa-credit-card"></i> Payments
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="../reports/sales-report.php">
                <i class="fas fa-chart-line"></i> Reports
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="../settings/general-settings.php">
                <i class="fas fa-cog"></i> System Settings
            </a>
        </li>
    </ul>
    
    <div class="sidebar-footer">
        <a href="../../auth/logout.php">
            <i class="fas fa-sign-out-alt me-2"></i> Logout
        </a>
    </div>
</div>

<!-- ===================================================== -->
<!-- MAIN CONTENT -->
<!-- ===================================================== -->
<div class="main-content" id="mainContent">
    
    <!-- Page Header -->
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">
            <i class="fas fa-file-invoice-plus me-2 text-primary"></i>Generate Invoice
        </h1>
        <div>
            <a href="invoices.php" class="btn btn-sm btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i> Back to Invoices
            </a>
        </div>
    </div>
    
    <!-- Error/Success Messages -->
    <?php if (isset($_SESSION['success'])): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <i class="fas fa-check-circle me-2"></i><?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>
    
    <?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>
    
    <!-- Generate Invoice Form -->
    <div class="form-card">
        <div class="card-title">
            <i class="fas fa-info-circle"></i> Invoice Information
        </div>
        
        <form method="POST" action="" id="invoiceForm">
            <?php echo csrf_field(); ?>
            
            <div class="row g-3">
                <!-- Order Selection -->
                <div class="col-md-6">
                    <label class="form-label required">Select Order</label>
                    <select name="order_id" id="order_id" class="form-select" required>
                        <option value="">-- Select Order --</option>
                        <?php foreach ($orders as $order): ?>
                        <option value="<?php echo $order['id']; ?>">
                            <?php echo htmlspecialchars($order['order_number']); ?> - 
                            <?php echo htmlspecialchars($order['customer_name']); ?> - 
                            <?php echo format_price($order['total_amount'] ?? 0); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (empty($orders)): ?>
                    <small class="text-warning">
                        <i class="fas fa-info-circle me-1"></i> No eligible orders found. Orders must be delivered or completed.
                    </small>
                    <?php endif; ?>
                </div>
                
                <!-- Invoice Number -->
                <div class="col-md-6">
                    <label class="form-label required">Invoice Number</label>
                    <input type="text" name="invoice_number" class="form-control" 
                           placeholder="e.g., INV-2024-001" 
                           value="INV-<?php echo date('Ymd'); ?>-<?php echo str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT); ?>" 
                           required>
                    <small class="text-muted">Must be unique. Format: INV-YYYYMMDD-XXX</small>
                </div>
                
                <!-- Due Date -->
                <div class="col-md-6">
                    <label class="form-label required">Due Date</label>
                    <input type="date" name="due_date" class="form-control" 
                           value="<?php echo date('Y-m-d', strtotime('+30 days')); ?>" 
                           required>
                    <small class="text-muted">Payment due date (typically 30 days from invoice date)</small>
                </div>
                
                <!-- Notes -->
                <div class="col-md-12">
                    <label class="form-label">Notes</label>
                    <textarea name="notes" class="form-control" rows="3" placeholder="Additional notes or payment instructions..."></textarea>
                </div>
            </div>
            
            <!-- Order Preview -->
            <div class="order-preview" id="orderPreview">
                <div class="mt-3 p-3 bg-light rounded">
                    <h6 class="mb-3"><i class="fas fa-shopping-cart me-2"></i>Order Details</h6>
                    <div id="orderDetails">
                        <p class="text-muted">Select an order to preview details.</p>
                    </div>
                </div>
            </div>
            
            <!-- Form Actions -->
            <div class="mt-4 d-flex gap-3">
                <button type="submit" class="btn-save" id="submitBtn">
                    <span class="btn-text"><i class="fas fa-file-invoice me-2"></i> Generate Invoice</span>
                    <span class="loading-text"><span class="loading-spinner me-2"></span> Generating...</span>
                </button>
                <a href="invoices.php" class="btn-cancel">
                    <i class="fas fa-times me-2"></i> Cancel
                </a>
            </div>
        </form>
    </div>
    
    <!-- Quick Tips -->
    <div class="form-card">
        <div class="card-title">
            <i class="fas fa-lightbulb text-warning"></i> Quick Tips
        </div>
        <div class="row">
            <div class="col-md-4">
                <div class="d-flex align-items-start">
                    <i class="fas fa-check-circle text-success me-2 mt-1"></i>
                    <div>
                        <h6>Eligible Orders</h6>
                        <small class="text-muted">Only delivered or completed orders can have invoices.</small>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="d-flex align-items-start">
                    <i class="fas fa-check-circle text-success me-2 mt-1"></i>
                    <div>
                        <h6>Unique Invoice Number</h6>
                        <small class="text-muted">Each invoice must have a unique number for tracking.</small>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="d-flex align-items-start">
                    <i class="fas fa-check-circle text-success me-2 mt-1"></i>
                    <div>
                        <h6>Payment Terms</h6>
                        <small class="text-muted">Set clear payment terms and due dates for timely payments.</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ===================================================== -->
<!-- TOAST CONTAINER -->
<!-- ===================================================== -->
<div class="toast-container"></div>

<!-- ===================================================== -->
<!-- SCRIPTS -->
<!-- ===================================================== -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<script>
// Toggle sidebar on mobile
function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('show');
}

// Close sidebar when clicking outside on mobile
document.addEventListener('click', function(event) {
    const sidebar = document.getElementById('sidebar');
    const toggleBtn = document.querySelector('.sidebar-toggle');
    if (window.innerWidth <= 768) {
        if (!sidebar.contains(event.target) && !toggleBtn.contains(event.target)) {
            sidebar.classList.remove('show');
        }
    }
});

// =============================================
// AJAX - Get Order Details
// =============================================
$('#order_id').on('change', function() {
    const orderId = $(this).val();
    const preview = $('#orderPreview');
    const details = $('#orderDetails');
    
    if (orderId) {
        preview.addClass('active');
        details.html(`
            <div class="text-center py-3">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p class="mt-2 text-muted">Loading order details...</p>
            </div>
        `);
        
        // AJAX call to same file
        $.ajax({
            url: window.location.href,
            type: 'GET',
            data: {
                ajax: 1,
                action: 'get_order_details',
                order_id: orderId
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    const o = response.order;
                    let itemsHtml = '';
                    
                    if (o.items && o.items.length > 0) {
                        o.items.forEach(function(item) {
                            const price = parseFloat(item.unit_price) || 0;
                            const subtotal = price * parseInt(item.quantity);
                            itemsHtml += `
                                <div class="order-item d-flex justify-content-between align-items-center">
                                    <div>
                                        <span class="fw-medium">${item.product_name}</span>
                                        <br>
                                        <small class="text-muted">Qty: ${item.quantity} × ${formatPrice(price)}</small>
                                    </div>
                                    <span class="fw-bold">${formatPrice(subtotal)}</span>
                                </div>
                            `;
                        });
                    } else {
                        itemsHtml = `<p class="text-muted">No items found for this order.</p>`;
                    }
                    
                    const total = parseFloat(o.total_amount) || 0;
                    const vat = parseFloat(o.vat_amount) || 0;
                    const subtotal = total - vat;
                    
                    details.html(`
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="mb-2">
                                    <label class="text-muted small">Order Number</label>
                                    <p class="fw-bold">${o.order_number}</p>
                                </div>
                                <div class="mb-2">
                                    <label class="text-muted small">Customer</label>
                                    <p class="fw-bold">${o.customer_name}</p>
                                </div>
                                <div class="mb-2">
                                    <label class="text-muted small">Email</label>
                                    <p>${o.customer_email}</p>
                                </div>
                                <div class="mb-2">
                                    <label class="text-muted small">Phone</label>
                                    <p>${o.customer_phone || 'N/A'}</p>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-2">
                                    <label class="text-muted small">Order Date</label>
                                    <p>${new Date(o.created_at).toLocaleDateString()}</p>
                                </div>
                                <div class="mb-2">
                                    <label class="text-muted small">Status</label>
                                    <p><span class="badge bg-${o.order_status === 'delivered' ? 'success' : 'info'}">${o.order_status.toUpperCase()}</span></p>
                                </div>
                                <div class="mb-2">
                                    <label class="text-muted small">Total Amount</label>
                                    <p class="h5 text-success">${formatPrice(total)}</p>
                                </div>
                                ${o.shipping_address ? `
                                <div class="mb-2">
                                    <label class="text-muted small">Shipping Address</label>
                                    <p>${o.shipping_address}</p>
                                </div>
                                ` : ''}
                            </div>
                        </div>
                        <div class="mt-3">
                            <h6 class="border-bottom pb-2">Order Items</h6>
                            ${itemsHtml}
                            <div class="mt-3 border-top pt-2">
                                <div class="d-flex justify-content-end">
                                    <div class="text-end">
                                        <div><small class="text-muted">Subtotal:</small> ${formatPrice(subtotal)}</div>
                                        <div><small class="text-muted">VAT (${o.vat_rate || 18}%):</small> ${formatPrice(vat)}</div>
                                        <div class="h5 mt-1">Total: ${formatPrice(total)}</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    `);
                } else {
                    details.html(`
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-circle me-2"></i>
                            ${response.message || 'Failed to load order details.'}
                        </div>
                    `);
                }
            },
            error: function(xhr, status, error) {
                details.html(`
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        Failed to load order details. Please try again.
                        <br><small class="text-muted">Error: ${error}</small>
                    </div>
                `);
            }
        });
    } else {
        preview.removeClass('active');
    }
});

// =============================================
// FORMAT PRICE
// =============================================
function formatPrice(price) {
    if (isNaN(price) || price === null || price === undefined) {
        return 'TSh 0.00';
    }
    return 'TSh ' + parseFloat(price).toLocaleString('en-TZ', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });
}

// =============================================
// FORM SUBMISSION
// =============================================
document.getElementById('invoiceForm').addEventListener('submit', function(e) {
    const btn = document.getElementById('submitBtn');
    btn.disabled = true;
    btn.classList.add('loading');
});

// =============================================
// AUTO-CLOSE ALERTS
// =============================================
setTimeout(function() {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(function(alert) {
        const bsAlert = new bootstrap.Alert(alert);
        setTimeout(function() { bsAlert.close(); }, 3000);
    });
}, 1000);

// =============================================
// TOAST NOTIFICATION
// =============================================
function showToast(title, message, type) {
    const toastHtml = `
        <div class="toast align-items-center text-white bg-${type === 'success' ? 'success' : 'danger'} border-0" role="alert">
            <div class="d-flex">
                <div class="toast-body">
                    <strong>${title}</strong><br>
                    ${message}
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        </div>
    `;
    
    $('.toast-container').append(toastHtml);
    const toast = new bootstrap.Toast($('.toast').last(), { delay: 3000 });
    toast.show();
    
    setTimeout(function() { $('.toast').last().remove(); }, 3500);
}
</script>

</body>
</html>