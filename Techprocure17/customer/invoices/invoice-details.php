<?php
/**
 * TechProcure Tanzania - Customer Invoice Details
 * File: customer/invoices/invoice-details.php
 * Description: View invoice details, download PDF, and make payment
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

if (!function_exists('time_ago')) {
    function time_ago($datetime) {
        $timestamp = strtotime($datetime);
        $difference = time() - $timestamp;
        
        $periods = [
            31536000 => 'year',
            2592000 => 'month',
            604800 => 'week',
            86400 => 'day',
            3600 => 'hour',
            60 => 'minute',
            1 => 'second'
        ];
        
        foreach ($periods as $seconds => $period) {
            if ($difference >= $seconds) {
                $count = floor($difference / $seconds);
                return $count . ' ' . $period . ($count > 1 ? 's' : '') . ' ago';
            }
        }
        return 'just now';
    }
}

// =============================================
// GET DATABASE CONNECTION
// =============================================
if (!isset($db) || $db === null) {
    try {
        $db = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        die("Connection failed: " . $e->getMessage());
    }
}

$conn = $db;

// =============================================
// CHECK USER AUTHENTICATION
// =============================================
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type'])) {
    header('Location: ../../auth/login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$user_type = $_SESSION['user_type'];
$user_name = $_SESSION['user_name'] ?? 'Customer';

// Only allow customers or admins
if ($user_type !== 'customer' && $user_type !== 'buyer' && $user_type !== 'admin') {
    header('Location: ../../index.php');
    exit();
}

// =============================================
// GET INVOICE ID
// =============================================
$invoice_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($invoice_id <= 0) {
    $_SESSION['error'] = 'Invalid invoice ID.';
    header('Location: my-invoices.php');
    exit();
}

// Determine customer column for orders
$customer_column = 'customer_id';
try {
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
} catch (PDOException $e) {
    $customer_column = 'customer_id';
}

// Determine amount column
$amount_column = 'total_amount';
try {
    $check_stmt = $conn->query("SHOW COLUMNS FROM orders LIKE 'total_amount_tsh'");
    if ($check_stmt->rowCount() > 0) {
        $amount_column = 'total_amount_tsh';
    }
} catch (PDOException $e) {
    $amount_column = 'total_amount';
}

// =============================================
// FETCH INVOICE DETAILS
// =============================================
$invoice = null;
$order = null;
$customer = null;
$supplier = null;
$items = [];
$payment = null;
$error = '';

try {
    // Get invoice with order and customer details
    $sql = "SELECT i.*, 
            o.id as order_id,
            o.order_number,
            o.$amount_column as order_total,
            o.order_status,
            o.payment_status as order_payment_status,
            o.shipping_address,
            o.tracking_number,
            o.created_at as order_date,
            c.id as customer_id,
            c.company_name as customer_company,
            c.contact_person as customer_name,
            c.email as customer_email,
            c.phone as customer_phone,
            c.address as customer_address,
            s.id as supplier_id,
            s.company_name as supplier_company,
            s.contact_person as supplier_contact,
            s.email as supplier_email,
            s.phone as supplier_phone
            FROM invoices i
            JOIN orders o ON i.order_id = o.id
            JOIN customers c ON o.$customer_column = c.id
            LEFT JOIN suppliers s ON o.supplier_id = s.id
            WHERE i.id = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(1, $invoice_id, PDO::PARAM_INT);
    $stmt->execute();
    $invoice = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$invoice) {
        $_SESSION['error'] = 'Invoice not found.';
        header('Location: my-invoices.php');
        exit();
    }
    
    // Check permission: customer can only view their own invoices
    if ($user_type !== 'admin' && $user_type !== 'super_admin') {
        if ($invoice['customer_id'] != $user_id) {
            $_SESSION['error'] = 'You do not have permission to view this invoice.';
            header('Location: my-invoices.php');
            exit();
        }
    }
    
    // Get order items
    $items_sql = "SELECT oi.*, 
                  p.product_name, 
                  p.sku,
                  p.brand,
                  (SELECT image_path FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) as product_image
                  FROM order_items oi
                  JOIN products p ON oi.product_id = p.id
                  WHERE oi.order_id = ?";
    $items_stmt = $conn->prepare($items_sql);
    $items_stmt->bindParam(1, $invoice['order_id'], PDO::PARAM_INT);
    $items_stmt->execute();
    $items = $items_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get payment details if paid
    if ($invoice['status'] == 'paid') {
        $payment_sql = "SELECT * FROM payments WHERE order_id = ? ORDER BY created_at DESC LIMIT 1";
        $payment_stmt = $conn->prepare($payment_sql);
        $payment_stmt->bindParam(1, $invoice['order_id'], PDO::PARAM_INT);
        $payment_stmt->execute();
        $payment = $payment_stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    // Parse invoice data if available
    $invoice_data = [];
    if (!empty($invoice['invoice_data'])) {
        $invoice_data = json_decode($invoice['invoice_data'], true);
    }
    
    // Calculate totals
    $subtotal = 0;
    foreach ($items as $item) {
        $subtotal += $item['unit_price_tsh'] * $item['quantity'];
    }
    $tax = $subtotal * 0.18;
    $total = $subtotal + $tax;
    
} catch (PDOException $e) {
    $error = 'Database error: ' . $e->getMessage();
}

// =============================================
// PAGE TITLE
// =============================================
$page_title = 'Invoice Details - TechProcure Tanzania';
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
        
        .back-link {
            color: #0d6efd;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s;
        }
        
        .back-link:hover {
            color: #0b5ed7;
        }
        
        .invoice-container {
            max-width: 1000px;
            margin: 30px auto;
            padding: 0 20px;
        }
        
        .invoice-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            overflow: hidden;
        }
        
        .invoice-header {
            background: linear-gradient(135deg, #1a2a6c, #b21f1f, #fdbb4d);
            padding: 30px 35px;
            color: white;
        }
        
        .invoice-header .invoice-title {
            font-size: 1.8rem;
            font-weight: 700;
        }
        
        .invoice-header .invoice-status {
            font-size: 0.9rem;
            padding: 4px 15px;
            border-radius: 20px;
            background: rgba(255,255,255,0.2);
        }
        
        .invoice-body {
            padding: 35px;
        }
        
        .invoice-info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 25px;
        }
        
        .invoice-info-grid .info-group {
            padding: 15px;
            background: #f8f9fa;
            border-radius: 10px;
        }
        
        .invoice-info-grid .info-group .label {
            font-size: 0.75rem;
            color: #6c757d;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 600;
        }
        
        .invoice-info-grid .info-group .value {
            font-weight: 500;
            margin-top: 3px;
        }
        
        .invoice-items-table {
            margin-top: 20px;
        }
        
        .invoice-items-table thead {
            background: #f8f9fa;
        }
        
        .invoice-items-table th {
            font-weight: 600;
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #6c757d;
            border: none;
        }
        
        .invoice-items-table td {
            vertical-align: middle;
            border-bottom: 1px solid #e9ecef;
        }
        
        .invoice-items-table .product-image {
            width: 50px;
            height: 50px;
            object-fit: cover;
            border-radius: 8px;
            background: #f8f9fa;
        }
        
        .invoice-summary {
            margin-top: 25px;
            padding-top: 20px;
            border-top: 2px solid #e9ecef;
        }
        
        .invoice-summary .summary-row {
            display: flex;
            justify-content: space-between;
            padding: 5px 0;
        }
        
        .invoice-summary .summary-row.total {
            font-size: 1.2rem;
            font-weight: 700;
            color: #0d6efd;
            padding-top: 10px;
            border-top: 2px solid #0d6efd;
        }
        
        .invoice-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 25px;
            padding-top: 20px;
            border-top: 1px solid #e9ecef;
        }
        
        .btn-download {
            background: #0d6efd;
            color: white;
            border: none;
            border-radius: 10px;
            padding: 10px 25px;
            font-weight: 500;
            transition: all 0.3s;
            cursor: pointer;
        }
        
        .btn-download:hover {
            background: #0b5ed7;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(13,110,253,0.3);
            color: white;
        }
        
        .btn-pay {
            background: #198754;
            color: white;
            border: none;
            border-radius: 10px;
            padding: 10px 25px;
            font-weight: 500;
            transition: all 0.3s;
            cursor: pointer;
        }
        
        .btn-pay:hover {
            background: #157347;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(25,135,84,0.3);
            color: white;
        }
        
        .btn-print {
            background: #6c757d;
            color: white;
            border: none;
            border-radius: 10px;
            padding: 10px 25px;
            font-weight: 500;
            transition: all 0.3s;
            cursor: pointer;
        }
        
        .btn-print:hover {
            background: #5a6268;
            color: white;
        }
        
        .status-badge {
            padding: 4px 15px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .status-paid { background: #d1e7dd; color: #0f5132; }
        .status-draft { background: #e2e3e5; color: #41464b; }
        .status-sent { background: #cfe2ff; color: #084298; }
        .status-overdue { background: #f8d7da; color: #842029; }
        .status-cancelled { background: #e2e3e5; color: #41464b; }
        
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
        
        @media print {
            .navbar, .footer, .invoice-actions, .back-link, .no-print {
                display: none !important;
            }
            .invoice-card {
                box-shadow: none !important;
                border: 1px solid #ddd !important;
            }
            .invoice-header {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
        }
        
        @media (max-width: 768px) {
            .invoice-info-grid {
                grid-template-columns: 1fr;
            }
            .invoice-header {
                padding: 20px;
            }
            .invoice-body {
                padding: 20px;
            }
            .invoice-actions {
                flex-direction: column;
            }
            .invoice-actions .btn {
                width: 100%;
                text-align: center;
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
                <a href="../../auth/logout.php" class="btn btn-outline-light btn-sm">
                    <i class="fas fa-sign-out-alt me-1"></i>Logout
                </a>
            </div>
        </div>
    </div>
</nav>

<!-- ===================================================== -->
<!-- INVOICE DETAILS -->
<!-- ===================================================== -->
<div class="invoice-container">
    
    <!-- Back Link -->
    <div class="mb-3">
        <a href="my-invoices.php" class="back-link">
            <i class="fas fa-arrow-left me-2"></i>Back to Invoices
        </a>
    </div>
    
    <?php if ($error): ?>
    <div class="alert alert-danger">
        <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
    </div>
    <?php endif; ?>
    
    <?php if ($invoice): ?>
    
    <!-- Invoice Card -->
    <div class="invoice-card" id="invoiceContent">
        
        <!-- Invoice Header -->
        <div class="invoice-header">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <div class="invoice-title">
                        <i class="fas fa-file-invoice me-2"></i>INVOICE
                    </div>
                    <div class="mt-1">
                        <span class="invoice-status">
                            <i class="fas fa-circle me-1" style="font-size: 8px;"></i>
                            <?php echo strtoupper($invoice['status']); ?>
                        </span>
                        <span class="ms-3 opacity-75">
                            <i class="fas fa-calendar-alt me-1"></i>
                            <?php echo date('F d, Y', strtotime($invoice['generated_at'])); ?>
                        </span>
                    </div>
                </div>
                <div class="col-md-4 text-md-end mt-3 mt-md-0">
                    <div class="fw-bold">Invoice #<?php echo htmlspecialchars($invoice['invoice_number']); ?></div>
                    <div class="opacity-75 small">Order: <?php echo htmlspecialchars($invoice['order_number']); ?></div>
                </div>
            </div>
        </div>
        
        <!-- Invoice Body -->
        <div class="invoice-body">
            
            <!-- Invoice Info Grid -->
            <div class="invoice-info-grid">
                <div class="info-group">
                    <div class="label"><i class="fas fa-building me-1"></i>Bill To</div>
                    <div class="value">
                        <strong><?php echo htmlspecialchars($invoice['customer_company'] ?? $invoice['customer_name']); ?></strong><br>
                        <?php echo htmlspecialchars($invoice['customer_name']); ?><br>
                        <?php echo htmlspecialchars($invoice['customer_email']); ?><br>
                        <?php echo htmlspecialchars($invoice['customer_phone']); ?><br>
                        <?php echo htmlspecialchars($invoice['customer_address']); ?>
                    </div>
                </div>
                <div class="info-group">
                    <div class="label"><i class="fas fa-store me-1"></i>From / Supplier</div>
                    <div class="value">
                        <strong><?php echo htmlspecialchars($invoice['supplier_company'] ?? 'N/A'); ?></strong><br>
                        <?php echo htmlspecialchars($invoice['supplier_contact'] ?? ''); ?><br>
                        <?php echo htmlspecialchars($invoice['supplier_email'] ?? ''); ?><br>
                        <?php echo htmlspecialchars($invoice['supplier_phone'] ?? ''); ?>
                    </div>
                </div>
                <div class="info-group">
                    <div class="label"><i class="fas fa-info-circle me-1"></i>Invoice Details</div>
                    <div class="value">
                        <div><strong>Invoice Date:</strong> <?php echo date('M d, Y', strtotime($invoice['generated_at'])); ?></div>
                        <div><strong>Due Date:</strong> <?php echo date('M d, Y', strtotime($invoice['due_date'])); ?></div>
                        <div><strong>Status:</strong> <span class="status-badge status-<?php echo $invoice['status']; ?>"><?php echo ucfirst($invoice['status']); ?></span></div>
                        <div><strong>Payment Status:</strong> <?php echo ucfirst($invoice['order_payment_status'] ?? 'Pending'); ?></div>
                    </div>
                </div>
                <div class="info-group">
                    <div class="label"><i class="fas fa-truck me-1"></i>Shipping Info</div>
                    <div class="value">
                        <div><strong>Order Status:</strong> <?php echo ucfirst($invoice['order_status']); ?></div>
                        <?php if ($invoice['tracking_number']): ?>
                        <div><strong>Tracking #:</strong> <?php echo htmlspecialchars($invoice['tracking_number']); ?></div>
                        <?php endif; ?>
                        <?php if ($invoice['shipping_address']): ?>
                        <div><strong>Address:</strong> <?php echo htmlspecialchars($invoice['shipping_address']); ?></div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Items Table -->
            <div class="invoice-items-table">
                <h6 class="mb-3"><i class="fas fa-list me-2 text-primary"></i>Items</h6>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th style="width: 60px;">#</th>
                                <th>Product</th>
                                <th>SKU</th>
                                <th class="text-center">Qty</th>
                                <th class="text-end">Unit Price</th>
                                <th class="text-end">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $counter = 1;
                            $subtotal = 0;
                            foreach ($items as $item): 
                                $item_total = $item['unit_price_tsh'] * $item['quantity'];
                                $subtotal += $item_total;
                            ?>
                            <tr>
                                <td><?php echo $counter++; ?></td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <?php if (!empty($item['product_image'])): ?>
                                        <img src="../../uploads/products/<?php echo $item['product_id']; ?>/<?php echo $item['product_image']; ?>" 
                                             alt="<?php echo htmlspecialchars($item['product_name']); ?>" 
                                             class="product-image me-2" 
                                             onerror="this.src='../../assets/images/placeholder-product.jpg'">
                                        <?php else: ?>
                                        <img src="../../assets/images/placeholder-product.jpg" 
                                             alt="<?php echo htmlspecialchars($item['product_name']); ?>" 
                                             class="product-image me-2">
                                        <?php endif; ?>
                                        <div>
                                            <div class="fw-bold"><?php echo htmlspecialchars($item['product_name']); ?></div>
                                            <small class="text-muted"><?php echo htmlspecialchars($item['brand'] ?? ''); ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($item['sku'] ?? 'N/A'); ?></td>
                                <td class="text-center"><?php echo $item['quantity']; ?></td>
                                <td class="text-end"><?php echo format_price($item['unit_price_tsh']); ?></td>
                                <td class="text-end fw-bold"><?php echo format_price($item_total); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Invoice Summary -->
            <div class="invoice-summary">
                <div class="row">
                    <div class="col-md-6 offset-md-6">
                        <div class="summary-row">
                            <span>Subtotal</span>
                            <span><?php echo format_price($subtotal); ?></span>
                        </div>
                        <div class="summary-row">
                            <span>Tax (18%)</span>
                            <span><?php echo format_price($subtotal * 0.18); ?></span>
                        </div>
                        <?php if (isset($invoice['shipping_cost_tsh']) && $invoice['shipping_cost_tsh'] > 0): ?>
                        <div class="summary-row">
                            <span>Shipping</span>
                            <span><?php echo format_price($invoice['shipping_cost_tsh']); ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if (isset($invoice['discount_amount_tsh']) && $invoice['discount_amount_tsh'] > 0): ?>
                        <div class="summary-row">
                            <span>Discount</span>
                            <span>-<?php echo format_price($invoice['discount_amount_tsh']); ?></span>
                        </div>
                        <?php endif; ?>
                        <div class="summary-row total">
                            <span>Total Amount</span>
                            <span><?php echo format_price($invoice['order_total'] ?? $total); ?></span>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Notes -->
            <?php if (!empty($invoice['notes'])): ?>
            <div class="mt-3 p-3 bg-light rounded">
                <strong><i class="fas fa-sticky-note me-2"></i>Notes:</strong>
                <p class="mb-0"><?php echo nl2br(htmlspecialchars($invoice['notes'])); ?></p>
            </div>
            <?php endif; ?>
            
            <!-- Payment Information -->
            <?php if ($payment): ?>
            <div class="mt-3 p-3 bg-success bg-opacity-10 rounded border border-success">
                <strong class="text-success"><i class="fas fa-check-circle me-2"></i>Payment Information</strong>
                <div class="row mt-2">
                    <div class="col-md-4">
                        <small class="text-muted">Transaction ID</small>
                        <div><?php echo htmlspecialchars($payment['transaction_id'] ?? 'N/A'); ?></div>
                    </div>
                    <div class="col-md-4">
                        <small class="text-muted">Payment Method</small>
                        <div><?php echo ucfirst(str_replace('_', ' ', $payment['payment_method'])); ?></div>
                    </div>
                    <div class="col-md-4">
                        <small class="text-muted">Paid On</small>
                        <div><?php echo date('M d, Y H:i', strtotime($payment['paid_at'])); ?></div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Invoice Actions -->
            <div class="invoice-actions no-print">
                <button onclick="printInvoice()" class="btn-print">
                    <i class="fas fa-print me-2"></i>Print
                </button>
                <a href="download-invoice.php?id=<?php echo $invoice_id; ?>" class="btn-download">
                    <i class="fas fa-file-pdf me-2"></i>Download PDF
                </a>
                <?php if ($invoice['status'] != 'paid' && $invoice['status'] != 'cancelled'): ?>
                <a href="../../payment/pay-invoice.php?id=<?php echo $invoice_id; ?>" class="btn-pay">
                    <i class="fas fa-credit-card me-2"></i>Pay Now
                </a>
                <?php endif; ?>
                <?php if ($user_type == 'admin' || $user_type == 'super_admin'): ?>
                <a href="../../admin/invoices/invoice-details.php?id=<?php echo $invoice_id; ?>" class="btn btn-outline-secondary">
                    <i class="fas fa-cog me-2"></i>Admin View
                </a>
                <?php endif; ?>
            </div>
            
        </div>
    </div>
    
    <?php else: ?>
    
    <!-- Invoice Not Found -->
    <div class="text-center py-5 bg-white rounded-3">
        <i class="fas fa-file-invoice fa-4x text-muted mb-3"></i>
        <h4>Invoice Not Found</h4>
        <p class="text-muted">The invoice you're looking for could not be found.</p>
        <a href="my-invoices.php" class="btn btn-primary">
            <i class="fas fa-arrow-left me-2"></i>Back to Invoices
        </a>
    </div>
    
    <?php endif; ?>
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
                <h6>My Account</h6>
                <ul class="list-unstyled">
                    <li><a href="../dashboard.php">Dashboard</a></li>
                    <li><a href="../profile.php">Profile</a></li>
                    <li><a href="../orders/my-orders.php">My Orders</a></li>
                    <li><a href="my-invoices.php">Invoices</a></li>
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
// Print invoice
function printInvoice() {
    window.print();
}

// Toast notification
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
    
    $('.toast-container').remove();
    $('body').append('<div class="toast-container position-fixed bottom-0 end-0 p-3"></div>');
    $('.toast-container').append(toastHtml);
    const toast = new bootstrap.Toast($('.toast').last(), { delay: 3000 });
    toast.show();
    
    setTimeout(function() { $('.toast').last().remove(); }, 3500);
}
</script>

</body>
</html>