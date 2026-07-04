<?php
/**
 * TechProcure Tanzania - Admin Escrow Details
 * File: admin/payments/escrow-details.php
 * Description: View detailed escrow payment information with transaction history
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

// Get escrow ID from URL
$escrow_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($escrow_id <= 0) {
    $_SESSION['error'] = "Invalid escrow ID.";
    header("Location: escrow-payments.php");
    exit();
}

// =============================================
// FETCH ESCROW DETAILS
// =============================================

$escrow = null;
$error = '';

try {
    $sql = "SELECT e.*, 
                   o.order_number, o.total_amount as order_total,
                   u.full_name as customer_name, u.email as customer_email, u.phone as customer_phone,
                   s.company_name as supplier_name
            FROM escrow_payments e
            LEFT JOIN orders o ON e.order_id = o.id
            LEFT JOIN users u ON o.user_id = u.id
            LEFT JOIN suppliers s ON o.supplier_id = s.id
            WHERE e.id = ?";
    $stmt = $db->prepare($sql);
    $stmt->execute([$escrow_id]);
    $escrow = $stmt->fetch();

    if (!$escrow) {
        $error = "Escrow payment not found.";
    }
} catch (PDOException $e) {
    $error = "Failed to load escrow details.";
}

// =============================================
// FETCH ESCROW TRANSACTIONS
// =============================================

$transactions = [];
if ($escrow) {
    try {
        $sql = "SELECT * FROM escrow_transactions WHERE escrow_id = ? ORDER BY created_at DESC";
        $stmt = $db->prepare($sql);
        $stmt->execute([$escrow_id]);
        if ($stmt->rowCount() > 0) {
            $transactions = $stmt->fetchAll();
        }
    } catch (PDOException $e) {
        $transactions = [];
    }
}

// =============================================
// FETCH ORDER ITEMS
// =============================================

$order_items = [];
if ($escrow) {
    try {
        $sql = "SELECT oi.*, p.product_name, p.sku, p.brand,
                       (SELECT image_path FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) as primary_image
                FROM order_items oi
                JOIN products p ON oi.product_id = p.id
                WHERE oi.order_id = ?";
        $stmt = $db->prepare($sql);
        $stmt->execute([$escrow['order_id']]);
        if ($stmt->rowCount() > 0) {
            $order_items = $stmt->fetchAll();
        }
    } catch (PDOException $e) {
        $order_items = [];
    }
}

// =============================================
// HANDLE ACTIONS
// =============================================

// Release escrow
if (isset($_POST['release_escrow'])) {
    try {
        $db->beginTransaction();
        
        $stmt = $db->prepare("UPDATE escrow_payments SET status = 'released', release_date = NOW() WHERE id = ?");
        $stmt->execute([$escrow_id]);
        
        // Update order payment status
        $order_stmt = $db->prepare("UPDATE orders SET payment_status = 'paid' WHERE id = ?");
        $order_stmt->execute([$escrow['order_id']]);
        
        // Log transaction
        $trans_sql = "INSERT INTO escrow_transactions (escrow_id, action, amount, description, performed_by, created_at) 
                      VALUES (?, 'release', ?, ?, ?, NOW())";
        $trans_stmt = $db->prepare($trans_sql);
        $trans_stmt->execute([
            $escrow_id,
            $escrow['amount'],
            'Escrow released by admin',
            $user_id
        ]);
        
        // Create notification
        $notif_sql = "INSERT INTO notifications (user_id, type, title, message, link, created_at) 
                      VALUES ((SELECT user_id FROM orders WHERE id = ?), 'escrow', ?, ?, ?, NOW())";
        $notif_stmt = $db->prepare($notif_sql);
        $notif_stmt->execute([
            $escrow['order_id'],
            'Escrow Released',
            'Your escrow payment of ' . formatPrice($escrow['amount']) . ' has been released to the supplier.',
            '../customer/orders/order-details.php?id=' . $escrow['order_id']
        ]);
        
        $db->commit();
        
        logActivity($user_id, 'Released Escrow Payment', 'escrow', $escrow_id);
        $_SESSION['success'] = "Escrow payment released successfully!";
        header("Location: escrow-details.php?id=" . $escrow_id);
        exit();
        
    } catch (PDOException $e) {
        $db->rollBack();
        $_SESSION['error'] = "Failed to release escrow: " . $e->getMessage();
    }
    header("Location: escrow-details.php?id=" . $escrow_id);
    exit();
}

// Refund escrow
if (isset($_POST['refund_escrow'])) {
    $refund_reason = trim($_POST['refund_reason'] ?? '');
    
    try {
        $db->beginTransaction();
        
        $stmt = $db->prepare("UPDATE escrow_payments SET status = 'refunded', refund_date = NOW() WHERE id = ?");
        $stmt->execute([$escrow_id]);
        
        // Update order
        $order_stmt = $db->prepare("UPDATE orders SET payment_status = 'refunded', order_status = 'cancelled', cancelled_at = NOW() WHERE id = ?");
        $order_stmt->execute([$escrow['order_id']]);
        
        // Log transaction
        $trans_sql = "INSERT INTO escrow_transactions (escrow_id, action, amount, description, performed_by, created_at) 
                      VALUES (?, 'refund', ?, ?, ?, NOW())";
        $trans_stmt = $db->prepare($trans_sql);
        $trans_stmt->execute([
            $escrow_id,
            $escrow['amount'],
            'Escrow refunded: ' . $refund_reason,
            $user_id
        ]);
        
        // Create notification
        $notif_sql = "INSERT INTO notifications (user_id, type, title, message, link, created_at) 
                      VALUES ((SELECT user_id FROM orders WHERE id = ?), 'escrow', ?, ?, ?, NOW())";
        $notif_stmt = $db->prepare($notif_sql);
        $notif_stmt->execute([
            $escrow['order_id'],
            'Escrow Refunded',
            'Your escrow payment of ' . formatPrice($escrow['amount']) . ' has been refunded. Reason: ' . $refund_reason,
            '../customer/orders/order-details.php?id=' . $escrow['order_id']
        ]);
        
        $db->commit();
        
        logActivity($user_id, 'Refunded Escrow Payment', 'escrow', $escrow_id);
        $_SESSION['success'] = "Escrow payment refunded successfully!";
        header("Location: escrow-details.php?id=" . $escrow_id);
        exit();
        
    } catch (PDOException $e) {
        $db->rollBack();
        $_SESSION['error'] = "Failed to refund escrow: " . $e->getMessage();
    }
    header("Location: escrow-details.php?id=" . $escrow_id);
    exit();
}

// =============================================
// HELPER FUNCTIONS
// =============================================

if (!function_exists('formatPrice')) {
    function formatPrice($price) {
        if (empty($price) && $price !== 0) return 'TSh 0.00';
        return 'TSh ' . number_format((float)$price, 2);
    }
}

if (!function_exists('formatDate')) {
    function formatDate($date) {
        if (empty($date)) return '-';
        return date('M d, Y', strtotime($date));
    }
}

if (!function_exists('formatDateTime')) {
    function formatDateTime($date) {
        if (empty($date)) return '-';
        return date('M d, Y H:i', strtotime($date));
    }
}

if (!function_exists('getEscrowBadge')) {
    function getEscrowBadge($status) {
        $colors = [
            'pending' => 'warning',
            'released' => 'success',
            'refunded' => 'secondary',
            'disputed' => 'danger'
        ];
        return $colors[$status] ?? 'secondary';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Escrow Details - TechProcure Tanzania</title>
    
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
        
        .detail-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 24px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        
        .detail-card .card-title {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #e9ecef;
        }
        
        .detail-card .card-title i {
            margin-right: 8px;
            color: #0d6efd;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        
        .info-item .label {
            font-size: 0.75rem;
            color: #6c757d;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .info-item .value {
            font-weight: 500;
            font-size: 1rem;
        }
        
        .transaction-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 10px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .transaction-item:last-child {
            border-bottom: none;
        }
        
        .transaction-item .transaction-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
        }
        
        .order-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 10px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .order-item:last-child {
            border-bottom: none;
        }
        
        .order-item img {
            width: 50px;
            height: 50px;
            object-fit: contain;
            background: #f8f9fa;
            border-radius: 8px;
            padding: 5px;
        }
        
        .btn-release {
            background: linear-gradient(135deg, #198754, #157347);
            border: none;
            border-radius: 10px;
            padding: 10px 25px;
            font-weight: 600;
            color: white;
            transition: all 0.3s;
        }
        
        .btn-release:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(25,135,84,0.3);
        }
        
        .btn-refund {
            background: linear-gradient(135deg, #dc3545, #c82333);
            border: none;
            border-radius: 10px;
            padding: 10px 25px;
            font-weight: 600;
            color: white;
            transition: all 0.3s;
        }
        
        .btn-refund:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(220,53,69,0.3);
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
            .info-grid {
                grid-template-columns: 1fr;
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
            <a href="transactions.php" class="nav-link">
                <i class="fas fa-credit-card"></i> Payments
            </a>
        </div>
        <div class="nav-item">
            <a href="escrow-payments.php" class="nav-link active">
                <i class="fas fa-lock"></i> Escrow
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
            <i class="fas fa-lock me-2 text-primary"></i> Escrow Details
        </div>
        <div class="d-flex align-items-center gap-3">
            <a href="escrow-payments.php" class="btn btn-secondary btn-sm">
                <i class="fas fa-arrow-left me-1"></i> Back to Escrow
            </a>
            <div class="admin-avatar">
                <?php echo strtoupper(substr($_SESSION['user_name'] ?? 'A', 0, 1)); ?>
            </div>
        </div>
    </div>
    
    <?php if($error): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-triangle me-2"></i> <?php echo $error; ?>
            <a href="escrow-payments.php" class="btn btn-primary btn-sm mt-2">Back to Escrow</a>
        </div>
    <?php elseif($escrow): ?>
    
    <!-- Success/Error Messages -->
    <?php if(isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="fas fa-check-circle me-2"></i> <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <?php if(isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="fas fa-exclamation-triangle me-2"></i> <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <!-- Escrow Summary -->
    <div class="detail-card">
        <div class="card-title">
            <i class="fas fa-info-circle"></i> Escrow Summary
            <span class="badge bg-<?php echo getEscrowBadge($escrow['status']); ?> ms-2 fs-6">
                <?php echo ucfirst($escrow['status']); ?>
            </span>
        </div>
        <div class="info-grid">
            <div class="info-item">
                <div class="label">Escrow ID</div>
                <div class="value">#<?php echo $escrow['id']; ?></div>
            </div>
            <div class="info-item">
                <div class="label">Order Number</div>
                <div class="value"><code><?php echo htmlspecialchars($escrow['order_number']); ?></code></div>
            </div>
            <div class="info-item">
                <div class="label">Amount</div>
                <div class="value text-primary fw-bold"><?php echo formatPrice($escrow['amount']); ?></div>
            </div>
            <div class="info-item">
                <div class="label">Created</div>
                <div class="value"><?php echo formatDateTime($escrow['created_at']); ?></div>
            </div>
            <?php if($escrow['release_date']): ?>
            <div class="info-item">
                <div class="label">Released On</div>
                <div class="value"><?php echo formatDateTime($escrow['release_date']); ?></div>
            </div>
            <?php endif; ?>
            <?php if($escrow['refund_date']): ?>
            <div class="info-item">
                <div class="label">Refunded On</div>
                <div class="value"><?php echo formatDateTime($escrow['refund_date']); ?></div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Customer & Supplier Info -->
    <div class="row">
        <div class="col-md-6">
            <div class="detail-card">
                <div class="card-title">
                    <i class="fas fa-user"></i> Customer Information
                </div>
                <div class="info-grid">
                    <div class="info-item">
                        <div class="label">Name</div>
                        <div class="value"><?php echo htmlspecialchars($escrow['customer_name'] ?? 'N/A'); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="label">Email</div>
                        <div class="value"><?php echo htmlspecialchars($escrow['customer_email'] ?? 'N/A'); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="label">Phone</div>
                        <div class="value"><?php echo htmlspecialchars($escrow['customer_phone'] ?? 'N/A'); ?></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="detail-card">
                <div class="card-title">
                    <i class="fas fa-store"></i> Supplier Information
                </div>
                <div class="info-grid">
                    <div class="info-item">
                        <div class="label">Supplier</div>
                        <div class="value"><?php echo htmlspecialchars($escrow['supplier_name'] ?? 'N/A'); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="label">Order Total</div>
                        <div class="value"><?php echo formatPrice($escrow['order_total'] ?? 0); ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Order Items -->
    <div class="detail-card">
        <div class="card-title">
            <i class="fas fa-box"></i> Order Items
        </div>
        <?php if(!empty($order_items)): ?>
            <?php foreach($order_items as $item): ?>
            <div class="order-item">
                <?php 
                $image_path = !empty($item['primary_image']) ? $item['primary_image'] : '../../assets/images/placeholder-product.jpg';
                if (!empty($item['primary_image']) && strpos($item['primary_image'], 'uploads/') === false) {
                    $image_path = 'uploads/products/' . $item['product_id'] . '/' . $item['primary_image'];
                }
                ?>
                <img src="<?php echo $image_path; ?>" alt="<?php echo htmlspecialchars($item['product_name']); ?>" onerror="this.src='../../assets/images/placeholder-product.jpg'">
                <div class="flex-grow-1">
                    <div class="fw-bold"><?php echo htmlspecialchars($item['product_name']); ?></div>
                    <div class="text-muted small">SKU: <?php echo htmlspecialchars($item['sku'] ?? 'N/A'); ?> | Qty: <?php echo $item['quantity']; ?></div>
                </div>
                <div class="text-end">
                    <div class="text-muted small">Unit Price</div>
                    <div class="fw-bold"><?php echo formatPrice($item['unit_price']); ?></div>
                </div>
                <div class="text-end">
                    <div class="text-muted small">Total</div>
                    <div class="fw-bold text-primary"><?php echo formatPrice($item['total_price']); ?></div>
                </div>
            </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p class="text-muted">No order items found.</p>
        <?php endif; ?>
    </div>
    
    <!-- Transaction History -->
    <div class="detail-card">
        <div class="card-title">
            <i class="fas fa-history"></i> Transaction History
        </div>
        <?php if(!empty($transactions)): ?>
            <?php foreach($transactions as $trans): ?>
            <div class="transaction-item">
                <div class="transaction-icon bg-<?php echo $trans['action'] == 'release' ? 'success' : ($trans['action'] == 'refund' ? 'danger' : 'info'); ?> bg-opacity-10 text-<?php echo $trans['action'] == 'release' ? 'success' : ($trans['action'] == 'refund' ? 'danger' : 'info'); ?>">
                    <i class="fas fa-<?php echo $trans['action'] == 'release' ? 'check-circle' : ($trans['action'] == 'refund' ? 'undo' : 'info-circle'); ?>"></i>
                </div>
                <div class="flex-grow-1">
                    <div class="fw-bold"><?php echo ucfirst($trans['action']); ?></div>
                    <div class="text-muted small"><?php echo htmlspecialchars($trans['description']); ?></div>
                </div>
                <div class="text-end">
                    <div class="text-muted small"><?php echo formatDateTime($trans['created_at']); ?></div>
                    <div class="fw-bold"><?php echo formatPrice($trans['amount']); ?></div>
                </div>
            </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p class="text-muted">No transactions found.</p>
        <?php endif; ?>
    </div>
    
    <!-- Actions -->
    <?php if($escrow['status'] == 'pending'): ?>
    <div class="detail-card">
        <div class="card-title">
            <i class="fas fa-cog"></i> Actions
        </div>
        <div class="d-flex flex-wrap gap-3">
            <form method="POST" onsubmit="return confirm('Release this escrow payment? This will release funds to the supplier.')">
                <input type="hidden" name="release_escrow" value="1">
                <button type="submit" class="btn-release">
                    <i class="fas fa-check-circle me-2"></i> Release Escrow
                </button>
            </form>
            
            <button type="button" class="btn-refund" data-bs-toggle="modal" data-bs-target="#refundModal">
                <i class="fas fa-undo me-2"></i> Refund Escrow
            </button>
            
            <a href="escrow-payments.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left me-2"></i> Back to Escrow
            </a>
        </div>
    </div>
    <?php endif; ?>
    
    <?php if($escrow['status'] == 'disputed'): ?>
    <div class="detail-card">
        <div class="card-title">
            <i class="fas fa-gavel"></i> Dispute Information
        </div>
        <div class="alert alert-warning">
            <i class="fas fa-exclamation-triangle me-2"></i>
            This escrow payment is currently under dispute. Please review the dispute details.
        </div>
        <a href="../disputes/dispute-details.php?escrow=<?php echo $escrow['id']; ?>" class="btn btn-warning">
            <i class="fas fa-gavel me-2"></i> View Dispute
        </a>
    </div>
    <?php endif; ?>
    
    <?php endif; ?>
</div>

<!-- Refund Modal -->
<div class="modal fade" id="refundModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" onsubmit="return confirm('Refund this escrow payment? This will reverse the transaction.')">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title"><i class="fas fa-undo me-2"></i>Refund Escrow</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="refund_escrow" value="1">
                    
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>Warning:</strong> This action will refund the full amount to the customer and cancel the order.
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Amount to Refund</label>
                        <div class="fw-bold fs-4"><?php echo formatPrice($escrow['amount']); ?></div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Refund Reason</label>
                        <textarea name="refund_reason" class="form-control" rows="3" placeholder="Enter reason for refund..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-undo me-2"></i> Confirm Refund
                    </button>
                </div>
            </form>
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
                <h6>Admin</h6>
                <ul class="list-unstyled">
                    <li><a href="../dashboard.php">Dashboard</a></li>
                    <li><a href="escrow-payments.php">Escrow</a></li>
                    <li><a href="../orders/manage-orders.php">Orders</a></li>
                </ul>
            </div>
            <div class="col-md-3 mb-4">
                <h6>Contact Us</h6>
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

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

</body>
</html>