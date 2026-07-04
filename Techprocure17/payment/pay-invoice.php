<?php
/**
 * TechProcure Tanzania - Pay Invoice Page
 * File: payment/pay-invoice.php
 * Description: Payment processing for invoices/orders
 */

// Start session if not started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include configuration files
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';


// Check if user is logged in
requireLogin();

// Get database connection
$db = getDB();
$user_id = $_SESSION['user_id'];

// Get order ID from URL
$order_id = isset($_GET['order']) ? (int)$_GET['order'] : 0;

if ($order_id <= 0) {
    $_SESSION['error'] = "Invalid order ID.";
    header("Location: ../customer/orders/my-orders.php");
    exit();
}

// =============================================
// FETCH ORDER DETAILS
// =============================================

$order = null;
$error = '';

try {
    $sql = "SELECT o.*, u.full_name as customer_name, u.email as customer_email, u.phone as customer_phone
            FROM orders o
            JOIN users u ON o.user_id = u.id
            WHERE o.id = ? AND o.user_id = ?";
    $stmt = $db->prepare($sql);
    $stmt->execute([$order_id, $user_id]);
    $order = $stmt->fetch();

    if (!$order) {
        $error = "Order not found or you don't have permission to view it.";
    } elseif ($order['payment_status'] == 'paid') {
        $error = "This order has already been paid.";
    }
} catch (PDOException $e) {
    $error = "Failed to load order details.";
}

// =============================================
// FETCH PAYMENT METHODS
// =============================================

$payment_methods = [
    'mpesa' => [
        'name' => 'M-Pesa',
        'icon' => 'fas fa-mobile-alt',
        'color' => '#4CAF50',
        'description' => 'Pay using M-Pesa mobile money'
    ],
    'airtel_money' => [
        'name' => 'Airtel Money',
        'icon' => 'fas fa-mobile-alt',
        'color' => '#FF6B00',
        'description' => 'Pay using Airtel Money'
    ],
    'tigo_pesa' => [
        'name' => 'Tigo Pesa',
        'icon' => 'fas fa-mobile-alt',
        'color' => '#E91E63',
        'description' => 'Pay using Tigo Pesa'
    ],
    'halopesa' => [
        'name' => 'Halopesa',
        'icon' => 'fas fa-mobile-alt',
        'color' => '#2196F3',
        'description' => 'Pay using Halopesa'
    ],
    'bank_transfer' => [
        'name' => 'Bank Transfer',
        'icon' => 'fas fa-university',
        'color' => '#9C27B0',
        'description' => 'Transfer directly to our bank account'
    ],
    'card' => [
        'name' => 'Credit/Debit Card',
        'icon' => 'fas fa-credit-card',
        'color' => '#607D8B',
        'description' => 'Pay using Visa, Mastercard'
    ]
];

// =============================================
// PROCESS PAYMENT
// =============================================

$payment_success = false;
$payment_error = '';
$transaction_id = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && $order) {
    $payment_method = $_POST['payment_method'] ?? '';
    $phone_number = $_POST['phone_number'] ?? '';
   
    
    // Validate payment method
    if (empty($payment_method)) {
        $payment_error = 'Please select a payment method.';
    } elseif (empty($phone_number) && in_array($payment_method, ['mpesa', 'airtel_money', 'tigo_pesa', 'halopesa'])) {
        $payment_error = 'Please enter your phone number.';
    } else {
        try {
            // Generate transaction ID
            $transaction_id = 'TXN-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
            
            // Start transaction
            $db->beginTransaction();
            
            // Update order payment status
            $sql = "UPDATE orders SET 
                    payment_status = 'paid',
                    payment_method = ?,
                    transaction_id = ?,
                    updated_at = NOW()
                    WHERE id = ?";
            $stmt = $db->prepare($sql);
            $stmt->execute([$payment_method, $transaction_id, $order_id]);
            
            // Create payment record
            $pay_sql = "INSERT INTO payments (
                            payment_number,
                            order_id,
                            user_id,
                            payment_method_id,
                            amount,
                            transaction_id,
                            payment_status,
                            processed_at,
                            created_at
                        ) VALUES (?, ?, ?, ?, ?, ?, 'completed', NOW(), NOW())";
            
            // Get payment method ID
            $method_id = 0;
            switch ($payment_method) {
                case 'mpesa': $method_id = 1; break;
                case 'airtel_money': $method_id = 2; break;
                case 'tigo_pesa': $method_id = 3; break;
                case 'halopesa': $method_id = 4; break;
                case 'bank_transfer': $method_id = 5; break;
                case 'card': $method_id = 6; break;
            }
            
            $pay_stmt = $db->prepare($pay_sql);
            $pay_stmt->execute([
                'PAY-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT),
                $order_id,
                $user_id,
                $method_id,
                $order['total_amount'],
                $transaction_id
            ]);
            
            // Update escrow payment status
            $escrow_sql = "UPDATE escrow_payments SET status = 'pending' WHERE order_id = ?";
            $escrow_stmt = $db->prepare($escrow_sql);
            $escrow_stmt->execute([$order_id]);
            
            // Log payment
            logActivity($user_id, 'Payment Made', 'payment', $order_id);
            
            $db->commit();
            
            $payment_success = true;
            
            // Send notification (optional)
            try {
                $notif_sql = "INSERT INTO notifications (user_id, type, title, message, link, created_at) 
                              VALUES (?, 'payment', ?, ?, ?, NOW())";
                $notif_stmt = $db->prepare($notif_sql);
                $notif_stmt->execute([
                    $user_id,
                    'Payment Successful',
                    'Your payment of ' . formatPrice($order['total_amount']) . ' for order ' . $order['order_number'] . ' has been processed successfully.',
                    '../customer/orders/order-details.php?id=' . $order_id
                ]);
            } catch (Exception $e) {
                // Continue even if notification fails
            }
            
        } catch (Exception $e) {
            $db->rollBack();
            $payment_error = 'Payment failed: ' . $e->getMessage();
        }
    }
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

// Get cart count for navbar
$cart_count = isset($_SESSION['cart']) ? array_sum(array_column($_SESSION['cart'], 'quantity')) : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pay Invoice - TechProcure Tanzania</title>
    
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
        
        .payment-header {
            background: linear-gradient(135deg, #1a2a6c, #b21f1f, #fdbb4d);
            color: white;
            padding: 30px 0;
            margin-bottom: 30px;
        }
        
        .back-link {
            text-decoration: none;
            color: rgba(255,255,255,0.8);
            transition: all 0.3s;
        }
        
        .back-link:hover {
            color: white;
        }
        
        .payment-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 24px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        
        .payment-card .card-title {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #e9ecef;
        }
        
        .payment-card .card-title i {
            margin-right: 8px;
            color: #0d6efd;
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
        
        .form-label {
            font-weight: 500;
            font-size: 0.85rem;
            margin-bottom: 6px;
        }
        
        .required:after {
            content: " *";
            color: red;
        }
        
        .payment-method-item {
            border: 2px solid #e9ecef;
            border-radius: 12px;
            padding: 15px;
            cursor: pointer;
            transition: all 0.3s;
            margin-bottom: 10px;
        }
        
        .payment-method-item:hover {
            border-color: #0d6efd;
        }
        
        .payment-method-item.active {
            border-color: #0d6efd;
            background: #f0f7ff;
        }
        
        .payment-method-item .method-icon {
            font-size: 1.5rem;
            width: 45px;
            height: 45px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            background: #f8f9fa;
        }
        
        .btn-pay {
            background: linear-gradient(135deg, #198754, #157347);
            border: none;
            border-radius: 10px;
            padding: 14px;
            font-weight: 600;
            font-size: 1.1rem;
            width: 100%;
            color: white;
            transition: all 0.3s;
        }
        
        .btn-pay:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(25,135,84,0.3);
        }
        
        .btn-pay:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        
        .btn-pay-secondary {
            background: linear-gradient(135deg, #0d6efd, #0b5ed7);
            border: none;
            border-radius: 10px;
            padding: 14px;
            font-weight: 600;
            font-size: 1.1rem;
            width: 100%;
            color: white;
            transition: all 0.3s;
        }
        
        .btn-pay-secondary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(13,110,253,0.3);
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
            .payment-header .d-flex {
                flex-direction: column;
                gap: 10px;
            }
        }
    </style>
</head>
<body>

<!-- Navbar -->
<nav class="navbar navbar-expand-lg">
    <div class="container">
        <a class="navbar-brand" href="../index.php">
            <i class="fas fa-microchip"></i>
            TechProcure <span class="text-warning">Tanzania</span>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav mx-auto">
                <li class="nav-item">
                    <a class="nav-link" href="../index.php"><i class="fas fa-home me-1"></i>Home</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="../products.php"><i class="fas fa-box me-1"></i>Products</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="../suppliers.php"><i class="fas fa-truck me-1"></i>Suppliers</a>
                </li>
            </ul>
            <div class="d-flex align-items-center gap-2">
                <a href="../cart.php" class="nav-link position-relative">
                    <i class="fas fa-shopping-cart fa-lg"></i>
                    <span class="cart-count"><?php echo $cart_count; ?></span>
                </a>
                <a href="../auth/logout.php" class="btn btn-outline-light btn-sm">
                    <i class="fas fa-sign-out-alt me-1"></i>Logout
                </a>
            </div>
        </div>
    </div>
</nav>

<!-- Payment Header -->
<div class="payment-header">
    <div class="container">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <a href="../customer/orders/my-orders.php" class="back-link">
                    <i class="fas fa-arrow-left me-2"></i>Back to Orders
                </a>
                <h1 class="display-5 fw-bold mt-2"><i class="fas fa-credit-card me-3"></i>Pay Invoice</h1>
                <p class="mb-0 opacity-75">Complete your payment securely</p>
            </div>
            <div>
                <span class="badge bg-light text-dark fs-6">
                    <i class="fas fa-receipt me-2"></i>Order #<?php echo $order ? htmlspecialchars($order['order_number']) : 'N/A'; ?>
                </span>
            </div>
        </div>
    </div>
</div>

<!-- Main Content -->
<div class="container">
    <?php if($error): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-triangle me-2"></i> <?php echo $error; ?>
            <a href="../customer/orders/my-orders.php" class="btn btn-primary btn-sm mt-2">Back to Orders</a>
        </div>
    <?php elseif($payment_success): ?>
        <!-- Payment Success -->
        <div class="success-box">
            <i class="fas fa-check-circle"></i>
            <h4>Payment Successful!</h4>
            <p>Your payment of <?php echo formatPrice($order['total_amount']); ?> for order <strong><?php echo htmlspecialchars($order['order_number']); ?></strong> has been processed successfully.</p>
            <p class="small">Transaction ID: <code><?php echo $transaction_id; ?></code></p>
            <div class="mt-3">
                <a href="../customer/orders/order-details.php?id=<?php echo $order_id; ?>" class="btn btn-primary">
                    <i class="fas fa-eye me-2"></i> View Order Details
                </a>
                <a href="../customer/orders/my-orders.php" class="btn btn-outline-primary">
                    <i class="fas fa-list me-2"></i> My Orders
                </a>
            </div>
        </div>
    <?php elseif($order): ?>
    
    <div class="row">
        <!-- Payment Form -->
        <div class="col-lg-8">
            <form method="POST" action="" id="paymentForm">
                <div class="payment-card">
                    <div class="card-title">
                        <i class="fas fa-wallet"></i> Payment Method
                    </div>
                    
                    <?php foreach($payment_methods as $key => $method): ?>
                    <div class="payment-method-item" onclick="selectPayment('<?php echo $key; ?>')">
                        <div class="d-flex align-items-center gap-3">
                            <div class="method-icon" style="background: <?php echo $method['color']; ?>20; color: <?php echo $method['color']; ?>;">
                                <i class="<?php echo $method['icon']; ?>"></i>
                            </div>
                            <div class="flex-grow-1">
                                <h6 class="mb-0"><?php echo $method['name']; ?></h6>
                                <small class="text-muted"><?php echo $method['description']; ?></small>
                            </div>
                            <div class="form-check">
                                <input type="radio" name="payment_method" value="<?php echo $key; ?>" 
                                       class="form-check-input" required>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- Phone Number (for mobile money) -->
                <div class="payment-card" id="phoneNumberSection">
                    <div class="card-title">
                        <i class="fas fa-phone"></i> Phone Number
                    </div>
                    <div class="mb-3">
                        <label class="form-label required">Phone Number</label>
                        <input type="tel" name="phone_number" class="form-control" 
                               placeholder="+255 XXX XXX XXX" 
                               value="<?php echo htmlspecialchars($order['customer_phone'] ?? ''); ?>">
                        <small class="text-muted">Required for M-Pesa, Airtel Money, Tigo Pesa, and Halopesa payments.</small>
                    </div>
                </div>
                
                <div class="payment-card">
                    <div class="card-title">
                        <i class="fas fa-lock"></i> Secure Payment
                    </div>
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" id="terms" required>
                        <label class="form-check-label" for="terms">
                            I confirm that the payment information is correct and I authorize this transaction.
                        </label>
                    </div>
                    
                    <hr>
                    
                    <button type="submit" class="btn-pay" id="payBtn">
                        <i class="fas fa-lock me-2"></i> Pay <?php echo formatPrice($order['total_amount']); ?>
                    </button>
                    
                    <div class="text-center mt-3">
                        <small class="text-muted">
                            <i class="fas fa-shield-alt me-1"></i> Your payment is secured by escrow protection
                        </small>
                    </div>
                </div>
            </form>
        </div>
        
        <!-- Order Summary -->
        <div class="col-lg-4">
            <div class="payment-card sticky-top" style="top: 20px;">
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
                        <span><?php echo formatDate($order['created_at']); ?></span>
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
                
                <div class="mt-3">
                    <div class="d-flex justify-content-between text-muted small">
                        <span><i class="fas fa-lock text-success me-1"></i> Secure payment</span>
                        <span><i class="fas fa-undo text-primary me-1"></i> 30-day return</span>
                    </div>
                    <div class="d-flex justify-content-between text-muted small mt-1">
                        <span><i class="fas fa-clock text-info me-1"></i> 48hr delivery</span>
                        <span><i class="fas fa-headset text-warning me-1"></i> 24/7 support</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php endif; ?>
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
                    <li><a href="../index.php">Home</a></li>
                    <li><a href="../products.php">Products</a></li>
                    <li><a href="../suppliers.php">Suppliers</a></li>
                </ul>
            </div>
            <div class="col-md-3 mb-4">
                <h6>Customer Service</h6>
                <ul class="list-unstyled">
                    <li><a href="../about.php">About Us</a></li>
                    <li><a href="../contact.php">Contact</a></li>
                    <li><a href="../faq.php">FAQ</a></li>
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

<script>
    // Select payment method
    function selectPayment(method) {
        document.querySelector(`input[name="payment_method"][value="${method}"]`).checked = true;
        document.querySelectorAll('.payment-method-item').forEach(el => {
            el.classList.remove('active');
        });
        document.querySelector(`.payment-method-item:has(input[value="${method}"])`).classList.add('active');
        
        // Show/hide phone number section
        const phoneSection = document.getElementById('phoneNumberSection');
        const mobileMethods = ['mpesa', 'airtel_money', 'tigo_pesa', 'halopesa'];
        if (mobileMethods.includes(method)) {
            phoneSection.style.display = 'block';
        } else {
            phoneSection.style.display = 'none';
        }
    }
    
    // Form validation
    document.getElementById('paymentForm').addEventListener('submit', function(e) {
        const terms = document.getElementById('terms');
        if (!terms.checked) {
            e.preventDefault();
            alert('Please confirm that you authorize this transaction.');
            return false;
        }
        
        const paymentMethod = document.querySelector('input[name="payment_method"]:checked');
        if (!paymentMethod) {
            e.preventDefault();
            alert('Please select a payment method.');
            return false;
        }
        
        const mobileMethods = ['mpesa', 'airtel_money', 'tigo_pesa', 'halopesa'];
        if (mobileMethods.includes(paymentMethod.value)) {
            const phone = document.querySelector('input[name="phone_number"]');
            if (!phone || phone.value.trim() === '') {
                e.preventDefault();
                alert('Please enter your phone number.');
                return false;
            }
        }
        
        const btn = document.getElementById('payBtn');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status"></span> Processing Payment...';
    });
    
    // Hide phone section initially if no mobile method selected
    document.addEventListener('DOMContentLoaded', function() {
        const checked = document.querySelector('input[name="payment_method"]:checked');
        if (checked) {
            selectPayment(checked.value);
        } else {
            document.getElementById('phoneNumberSection').style.display = 'none';
        }
    });
</script>

</body>
</html>