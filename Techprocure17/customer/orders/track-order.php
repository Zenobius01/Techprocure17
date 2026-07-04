<?php
/**
 * TechProcure Tanzania - Order Tracking Page
 * File: customer/orders/track-order.php
 * Description: Real-time order tracking with visual progress
 */

// Start session if not started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include configuration files
require_once '../../includes/config.php';
require_once '../../includes/db.php';
require_once '../../includes/functions.php';

// Check if user is logged in
requireLogin();

// Get database connection
$db = getDB();
$user_id = $_SESSION['user_id'];

// Get order ID from URL
$order_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($order_id <= 0) {
    $_SESSION['error'] = "Invalid order ID.";
    header("Location: index.php");
    exit();
}

// =============================================
// FETCH ORDER DETAILS
// =============================================

$order = null;
$order_items = [];
$tracking_updates = [];
$error = '';

try {
    // Fetch order details
    $sql = "SELECT o.*, 
                   u.full_name as customer_name, 
                   u.email as customer_email, 
                   u.phone as customer_phone
            FROM orders o
            JOIN users u ON o.user_id = u.id
            WHERE o.id = ? AND o.user_id = ?";
    $stmt = $db->prepare($sql);
    $stmt->execute([$order_id, $user_id]);
    $order = $stmt->fetch();

    if (!$order) {
        $error = "Order not found.";
    } elseif ($order['order_status'] == 'cancelled') {
        $error = "This order has been cancelled.";
    } else {
        // Fetch order items
        $items_sql = "SELECT oi.*, p.name as product_name, p.sku 
                      FROM order_items oi
                      LEFT JOIN products p ON oi.product_id = p.id
                      WHERE oi.order_id = ?";
        $items_stmt = $db->prepare($items_sql);
        $items_stmt->execute([$order_id]);
        $order_items = $items_stmt->fetchAll();

        // Fetch tracking updates
        $track_sql = "SELECT * FROM order_tracking 
                      WHERE order_id = ? 
                      ORDER BY created_at DESC";
        $track_stmt = $db->prepare($track_sql);
        $track_stmt->execute([$order_id]);
        $tracking_updates = $track_stmt->fetchAll();

        // If no tracking updates, create default ones
        if (empty($tracking_updates)) {
            // Add default tracking based on order status
            $default_tracks = [
                ['status' => 'pending', 'description' => 'Order placed successfully. Awaiting confirmation.'],
                ['status' => 'confirmed', 'description' => 'Order confirmed by supplier. Preparing for processing.'],
                ['status' => 'processing', 'description' => 'Order is being processed and packaged.'],
                ['status' => 'shipped', 'description' => 'Order has been shipped and is on its way.'],
                ['status' => 'delivered', 'description' => 'Order has been delivered successfully!'],
                ['status' => 'completed', 'description' => 'Order completed. Thank you for shopping with TechProcure!']
            ];

            $status_order = ['pending', 'confirmed', 'processing', 'shipped', 'delivered', 'completed'];
            $current_status = $order['order_status'];
            $status_index = array_search($current_status, $status_order);

            if ($status_index !== false) {
                for ($i = 0; $i <= $status_index; $i++) {
                    $tracking_updates[] = [
                        'status' => $status_order[$i],
                        'description' => $default_tracks[$i]['description'],
                        'created_at' => $order['created_at']
                    ];
                }
            }
        }
    }
} catch (PDOException $e) {
    $error = "Failed to load order details: " . $e->getMessage();
}

// =============================================
// HELPER FUNCTIONS
// =============================================



function getStatusIcon($status) {
    $icons = [
        'pending' => 'fa-clock',
        'confirmed' => 'fa-check-circle',
        'processing' => 'fa-spinner',
        'shipped' => 'fa-truck',
        'delivered' => 'fa-home',
        'completed' => 'fa-check-double',
        'cancelled' => 'fa-times-circle'
    ];
    return $icons[$status] ?? 'fa-circle';
}

function getStatusColor($status) {
    $colors = [
        'pending' => '#ffc107',
        'confirmed' => '#17a2b8',
        'processing' => '#0d6efd',
        'shipped' => '#17a2b8',
        'delivered' => '#28a745',
        'completed' => '#28a745',
        'cancelled' => '#dc3545'
    ];
    return $colors[$status] ?? '#6c757d';
}




// =============================================
// GET ORDER STATUS PROGRESS
// =============================================

$status_order = ['pending', 'confirmed', 'processing', 'shipped', 'delivered', 'completed'];
$current_status = $order ? $order['order_status'] : 'pending';
$current_index = array_search($current_status, $status_order);
$total_steps = count($status_order);

// Estimated delivery date (7 days from order date)
$estimated_delivery = date('M d, Y', strtotime('+7 days', strtotime($order['created_at'] ?? 'now')));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Track Order - TechProcure Tanzania</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f8f9fa;
        }
        
        .navbar-custom {
            background: linear-gradient(135deg, #1a2a6c, #b21f1f, #fdbb4d);
            padding: 15px 0;
        }
        
        .navbar-custom .navbar-brand {
            color: white;
            font-weight: 700;
            font-size: 1.5rem;
        }
        
        .navbar-custom .navbar-brand i {
            margin-right: 10px;
        }
        
        .page-header {
            background: white;
            padding: 30px 0;
            border-bottom: 1px solid #e9ecef;
            margin-bottom: 30px;
        }
        
        .page-header h1 {
            font-weight: 700;
            font-size: 1.8rem;
        }
        
        .tracking-card {
            background: white;
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 24px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        
        .tracking-card .card-title {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #e9ecef;
        }
        
        .tracking-card .card-title i {
            margin-right: 8px;
            color: #0d6efd;
        }
        
        /* Order Status Progress Bar */
        .progress-steps {
            display: flex;
            justify-content: space-between;
            position: relative;
            padding: 20px 0;
            margin: 20px 0;
        }
        
        .progress-steps::before {
            content: '';
            position: absolute;
            top: 45px;
            left: 20px;
            right: 20px;
            height: 4px;
            background: #e9ecef;
            z-index: 0;
        }
        
        .progress-steps .step {
            display: flex;
            flex-direction: column;
            align-items: center;
            position: relative;
            z-index: 1;
            flex: 1;
        }
        
        .progress-steps .step .step-circle {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: #e9ecef;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            color: #6c757d;
            transition: all 0.5s ease;
            border: 3px solid #e9ecef;
        }
        
        .progress-steps .step.active .step-circle {
            background: #0d6efd;
            border-color: #0d6efd;
            color: white;
            transform: scale(1.1);
            box-shadow: 0 0 20px rgba(13,110,253,0.3);
        }
        
        .progress-steps .step.completed .step-circle {
            background: #28a745;
            border-color: #28a745;
            color: white;
        }
        
        .progress-steps .step .step-label {
            margin-top: 10px;
            font-size: 0.7rem;
            font-weight: 500;
            color: #6c757d;
            text-align: center;
        }
        
        .progress-steps .step.active .step-label {
            color: #0d6efd;
            font-weight: 600;
        }
        
        .progress-steps .step.completed .step-label {
            color: #28a745;
        }
        
        /* Tracking Timeline */
        .timeline {
            position: relative;
            padding-left: 30px;
        }
        
        .timeline::before {
            content: '';
            position: absolute;
            left: 10px;
            top: 0;
            bottom: 0;
            width: 2px;
            background: #e9ecef;
        }
        
        .timeline-item {
            position: relative;
            padding: 15px 0 15px 30px;
            border-left: 3px solid transparent;
            transition: all 0.3s;
        }
        
        .timeline-item::before {
            content: '';
            position: absolute;
            left: -6px;
            top: 20px;
            width: 14px;
            height: 14px;
            border-radius: 50%;
            background: #e9ecef;
            border: 3px solid white;
            box-shadow: 0 0 0 2px #e9ecef;
        }
        
        .timeline-item.completed::before {
            background: #28a745;
            box-shadow: 0 0 0 2px #28a745;
        }
        
        .timeline-item.active::before {
            background: #0d6efd;
            box-shadow: 0 0 0 2px #0d6efd;
            animation: pulse 1.5s infinite;
        }
        
        @keyframes pulse {
            0% { box-shadow: 0 0 0 2px #0d6efd; }
            50% { box-shadow: 0 0 0 8px rgba(13,110,253,0.3); }
            100% { box-shadow: 0 0 0 2px #0d6efd; }
        }
        
        .timeline-item .timeline-time {
            font-size: 0.8rem;
            color: #6c757d;
        }
        
        .timeline-item .timeline-status {
            font-weight: 600;
            font-size: 1rem;
        }
        
        .timeline-item .timeline-description {
            color: #6c757d;
            font-size: 0.9rem;
        }
        
        .order-summary {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 15px;
        }
        
        .order-summary .summary-row {
            display: flex;
            justify-content: space-between;
            padding: 6px 0;
            border-bottom: 1px solid #e9ecef;
        }
        
        .order-summary .summary-row:last-child {
            border-bottom: none;
        }
        
        .order-summary .total-row {
            font-size: 1.2rem;
            font-weight: 700;
            color: #0d6efd;
        }
        
        .delivery-info {
            background: #f0f7ff;
            border-radius: 10px;
            padding: 15px;
            border-left: 4px solid #0d6efd;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px 20px;
        }
        
        .empty-state i {
            font-size: 3rem;
            color: #dee2e6;
            margin-bottom: 20px;
        }
        
        @media (max-width: 768px) {
            .progress-steps {
                flex-wrap: wrap;
                gap: 10px;
            }
            .progress-steps .step {
                flex: 0 0 33%;
            }
            .progress-steps .step .step-circle {
                width: 40px;
                height: 40px;
                font-size: 1rem;
            }
            .progress-steps .step .step-label {
                font-size: 0.6rem;
            }
            .timeline {
                padding-left: 15px;
            }
            .timeline-item {
                padding: 10px 0 10px 20px;
            }
        }
        
        .status-badge {
            font-size: 1rem;
            padding: 8px 20px;
        }
        
        .btn-refresh {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 10px;
            padding: 8px 20px;
            transition: all 0.3s;
        }
        
        .btn-refresh:hover {
            background: #e9ecef;
        }
        
        .btn-refresh .fa-spinner {
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
    </style>
</head>
<body>

<!-- Navigation -->
<nav class="navbar navbar-custom">
    <div class="container">
        <a class="navbar-brand" href="../../index.php">
            <i class="fas fa-microchip"></i> TechProcure Tanzania
        </a>
        <div class="d-flex align-items-center gap-3">
            <span class="text-white">
                <i class="fas fa-user me-1"></i> 
                <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Customer'); ?>
            </span>
            <a href="../cart/index.php" class="btn btn-light btn-sm">
                <i class="fas fa-shopping-cart"></i>
            </a>
            <a href="../../auth/logout.php" class="btn btn-outline-light btn-sm">
                <i class="fas fa-sign-out-alt"></i>
            </a>
        </div>
    </div>
</nav>

<!-- Page Header -->
<div class="page-header">
    <div class="container">
        <div class="d-flex justify-content-between align-items-center flex-wrap">
            <div>
                <h1><i class="fas fa-truck me-2 text-primary"></i>Track Order</h1>
                <p class="text-muted mb-0">Real-time tracking for your order</p>
            </div>
            <div>
                <a href="index.php" class="btn btn-outline-primary">
                    <i class="fas fa-arrow-left me-2"></i> Back to Orders
                </a>
                <button class="btn btn-refresh ms-2" onclick="window.location.reload()">
                    <i class="fas fa-sync-alt"></i> Refresh
                </button>
            </div>
        </div>
    </div>
</div>

<div class="container">
    <!-- Error Message -->
    <?php if($error): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-triangle me-2"></i> <?php echo $error; ?>
            <a href="index.php" class="btn btn-primary btn-sm mt-2">Back to Orders</a>
        </div>
    <?php elseif($order): ?>
    
    <!-- Order Header -->
    <div class="tracking-card">
        <div class="row align-items-center">
            <div class="col-md-6">
                <div class="d-flex align-items-center gap-3">
                    <h3 class="mb-0">Order #<?php echo htmlspecialchars($order['order_number']); ?></h3>
                    <?php echo getStatusBadge($order['order_status']); ?>
                </div>
                <p class="text-muted mb-0 mt-2">
                    <i class="far fa-calendar-alt me-1"></i> 
                    Placed on <?php echo formatDate($order['created_at']); ?>
                </p>
            </div>
            <div class="col-md-6 text-md-end mt-3 mt-md-0">
                <div class="d-flex flex-wrap gap-2 justify-content-md-end">
                    <?php if($order['payment_status'] == 'pending' && $order['order_status'] != 'cancelled'): ?>
                        <a href="../cart/payment.php?order=<?php echo $order['id']; ?>" class="btn btn-success">
                            <i class="fas fa-credit-card me-1"></i> Pay Now
                        </a>
                    <?php endif; ?>
                    <a href="order-details.php?id=<?php echo $order['id']; ?>" class="btn btn-outline-primary">
                        <i class="fas fa-eye me-1"></i> Full Details
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Progress Steps -->
    <div class="tracking-card">
        <div class="card-title">
            <i class="fas fa-chart-line"></i> Order Progress
        </div>
        
        <div class="progress-steps">
            <?php foreach($status_order as $index => $status): ?>
                <?php
                $is_completed = $index < $current_index;
                $is_active = $index == $current_index;
                $status_class = $is_completed ? 'completed' : ($is_active ? 'active' : '');
                ?>
                <div class="step <?php echo $status_class; ?>">
                    <div class="step-circle">
                        <i class="fas <?php echo $is_completed ? 'fa-check' : getStatusIcon($status); ?>"></i>
                    </div>
                    <div class="step-label"><?php echo getStatusLabel($status); ?></div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <div class="row mt-3">
            <div class="col-md-6">
                <div class="delivery-info">
                    <i class="fas fa-clock text-primary me-2"></i>
                    <strong>Estimated Delivery:</strong>
                    <span class="text-primary"><?php echo $estimated_delivery; ?></span>
                </div>
            </div>
            <div class="col-md-6">
                <div class="delivery-info" style="border-color: #28a745;">
                    <i class="fas fa-box text-success me-2"></i>
                    <strong>Current Status:</strong>
                    <span class="text-success"><?php echo ucfirst($order['order_status']); ?></span>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Tracking Timeline -->
    <div class="tracking-card">
        <div class="card-title">
            <i class="fas fa-history"></i> Tracking History
        </div>
        
        <?php if(count($tracking_updates) > 0): ?>
            <div class="timeline">
                <?php foreach($tracking_updates as $update): ?>
                    <?php
                    $is_completed = true;
                    $is_active = false;
                    // Check if this is the current status or has been completed
                    foreach($status_order as $index => $status) {
                        if ($status == $update['status']) {
                            if ($index <= $current_index) {
                                $is_completed = true;
                            }
                            if ($index == $current_index) {
                                $is_active = true;
                            }
                            break;
                        }
                    }
                    $item_class = $is_active ? 'active' : ($is_completed ? 'completed' : '');
                    ?>
                    <div class="timeline-item <?php echo $item_class; ?>">
                        <div class="timeline-time">
                            <i class="far fa-clock me-1"></i>
                            <?php echo formatDate($update['created_at']); ?>
                        </div>
                        <div class="timeline-status">
                            <?php echo getStatusBadge($update['status']); ?>
                            <?php echo getStatusLabel($update['status']); ?>
                        </div>
                        <div class="timeline-description">
                            <?php echo htmlspecialchars($update['description']); ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-box-open"></i>
                <p class="text-muted">No tracking updates available yet.</p>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Order Summary -->
    <div class="row">
        <div class="col-lg-6">
            <div class="tracking-card">
                <div class="card-title">
                    <i class="fas fa-receipt"></i> Order Summary
                </div>
                
                <div class="order-summary">
                    <div class="summary-row">
                        <span>Order Number</span>
                        <span><code><?php echo htmlspecialchars($order['order_number']); ?></code></span>
                    </div>
                    <div class="summary-row">
                        <span>Order Date</span>
                        <span><?php echo date('M d, Y', strtotime($order['created_at'])); ?></span>
                    </div>
                    <div class="summary-row">
                        <span>Items</span>
                        <span><?php echo count($order_items); ?> item(s)</span>
                    </div>
                    <div class="summary-row">
                        <span>Subtotal</span>
                        <span><?php echo formatPrice($order['subtotal']); ?></span>
                    </div>
                    <?php if($order['discount_amount'] > 0): ?>
                    <div class="summary-row text-success">
                        <span>Discount</span>
                        <span>-<?php echo formatPrice($order['discount_amount']); ?></span>
                    </div>
                    <?php endif; ?>
                    <div class="summary-row">
                        <span>Shipping</span>
                        <span><?php echo $order['shipping_cost'] > 0 ? formatPrice($order['shipping_cost']) : 'Free'; ?></span>
                    </div>
                    <div class="summary-row">
                        <span>Tax (18%)</span>
                        <span><?php echo formatPrice($order['tax_amount']); ?></span>
                    </div>
                    <div class="summary-row total-row">
                        <span>Total</span>
                        <span><?php echo formatPrice($order['total_amount']); ?></span>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-6">
            <div class="tracking-card">
                <div class="card-title">
                    <i class="fas fa-info-circle"></i> Order Information
                </div>
                
                <div class="mb-3">
                    <label class="form-label fw-bold">Payment Method</label>
                    <p><?php echo strtoupper($order['payment_method'] ?? 'N/A'); ?></p>
                </div>
                
                <div class="mb-3">
                    <label class="form-label fw-bold">Payment Status</label>
                    <p><?php echo getPaymentBadge($order['payment_status']); ?></p>
                </div>
                
                <div class="mb-3">
                    <label class="form-label fw-bold">Shipping Address</label>
                    <p><?php echo nl2br(htmlspecialchars($order['shipping_address'] ?? 'N/A')); ?></p>
                </div>
                
                <?php if($order['notes']): ?>
                <div class="mb-3">
                    <label class="form-label fw-bold">Order Notes</label>
                    <p><?php echo htmlspecialchars($order['notes']); ?></p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Order Items -->
    <div class="tracking-card">
        <div class="card-title">
            <i class="fas fa-boxes"></i> Order Items
        </div>
        
        <?php if(count($order_items) > 0): ?>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>SKU</th>
                            <th>Quantity</th>
                            <th>Price</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($order_items as $item): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($item['product_name'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($item['sku'] ?? 'N/A'); ?></td>
                                <td><?php echo $item['quantity']; ?></td>
                                <td><?php echo formatPrice($item['price']); ?></td>
                                <td><?php echo formatPrice($item['total']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p class="text-muted">No items found for this order.</p>
        <?php endif; ?>
    </div>
    
    <!-- Need Help -->
    <div class="tracking-card" style="background: #f8f9fa;">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h5 class="mb-0"><i class="fas fa-headset text-primary me-2"></i>Need Help?</h5>
                <p class="text-muted mb-0">If you have any questions about your order, please contact our support team.</p>
            </div>
            <div class="col-md-4 text-md-end mt-3 mt-md-0">
                <a href="../../contact.php" class="btn btn-primary">
                    <i class="fas fa-envelope me-2"></i> Contact Support
                </a>
            </div>
        </div>
    </div>
    
    <?php endif; ?>
</div>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<script>
    // =============================================
    // AUTO-REFRESH TRACKING EVERY 60 SECONDS
    // =============================================
    $(document).ready(function() {
        // Check if order is still in progress (not delivered or completed)
        <?php if($order && !in_array($order['order_status'], ['delivered', 'completed', 'cancelled'])): ?>
            // Auto-refresh tracking every 60 seconds
            setInterval(function() {
                // Show refresh indicator
                $('.btn-refresh i').addClass('fa-spinner');
                
                $.ajax({
                    url: 'ajax-track-order.php',
                    type: 'GET',
                    data: {
                        id: <?php echo $order_id; ?>,
                        _: new Date().getTime()
                    },
                    dataType: 'json',
                    success: function(data) {
                        if (data.success && data.html) {
                            // Update the tracking timeline without full page reload
                            // For simplicity, we'll reload the page
                            window.location.reload();
                        }
                    },
                    complete: function() {
                        $('.btn-refresh i').removeClass('fa-spinner');
                    }
                });
            }, 60000);
        <?php endif; ?>
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

    // =============================================
    // SMOOTH SCROLL TO UPDATES
    // =============================================
    document.addEventListener('DOMContentLoaded', function() {
        // If there's a new update, scroll to it
        const newUpdate = document.querySelector('.timeline-item.active');
        if (newUpdate) {
            newUpdate.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    });
</script>

</body>
</html>