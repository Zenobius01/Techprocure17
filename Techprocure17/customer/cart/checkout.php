<?php
/**
 * TechProcure Tanzania - Checkout Page
 * File: customer/cart/checkout.php
 * Description: Complete checkout process with payment selection
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

// =============================================
// CHECK IF CART IS EMPTY
// =============================================

if (empty($_SESSION['cart'])) {
    $_SESSION['error'] = 'Your cart is empty. Please add items before checkout.';
    header("Location: ../cart.php");
    exit();
}

// =============================================
// GET CART ITEMS
// =============================================

$cart_items = [];
$cart_total = 0;
$cart_count = 0;

$product_ids = array_keys($_SESSION['cart']);
$placeholders = implode(',', array_fill(0, count($product_ids), '?'));

try {
    $sql = "SELECT p.*, 
                   (SELECT image_path FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) as primary_image
            FROM products p
            WHERE p.id IN ($placeholders) AND p.status = 'active' AND p.approval_status = 'approved'";
    $stmt = $db->prepare($sql);
    $stmt->execute($product_ids);
    $products = $stmt->fetchAll();
    
    foreach ($products as $product) {
        $product_id = $product['id'];
        if (isset($_SESSION['cart'][$product_id])) {
            $item = $_SESSION['cart'][$product_id];
            $item['product'] = $product;
            $item['line_total'] = $item['price'] * $item['quantity'];
            $cart_items[] = $item;
            $cart_total += $item['line_total'];
            $cart_count += $item['quantity'];
        }
    }
} catch (PDOException $e) {
    $_SESSION['error'] = 'Failed to load cart items.';
    header("Location: ../cart.php");
    exit();
}

if (empty($cart_items)) {
    $_SESSION['error'] = 'Your cart is empty.';
    header("Location: ../cart.php");
    exit();
}

// =============================================
// GET USER DATA
// =============================================

$user = getCurrentUser();

// =============================================
// PROCESS CHECKOUT
// =============================================

$error = '';
$success = '';
$order_number = '';
$payment_methods = [];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Get form data
    $shipping_address = trim($_POST['shipping_address']);
    $billing_address = trim($_POST['billing_address']);
    $payment_method = $_POST['payment_method'] ?? '';
    $notes = trim($_POST['notes'] ?? '');
    $terms = isset($_POST['terms']) ? true : false;
    
    // Validation
    if (empty($shipping_address)) {
        $error = 'Please enter shipping address.';
    } elseif (empty($payment_method)) {
        $error = 'Please select a payment method.';
    } elseif (!$terms) {
        $error = 'You must agree to the Terms & Conditions.';
    } else {
        try {
            // Calculate totals
            $subtotal = $cart_total;
            $discount_percent = 0;
            
            // Apply bulk discount
            if ($cart_count >= 200) $discount_percent = 25;
            elseif ($cart_count >= 50) $discount_percent = 15;
            elseif ($cart_count >= 10) $discount_percent = 8;
            elseif ($cart_count >= 5) $discount_percent = 3;
            
            $discount_amount = $subtotal * ($discount_percent / 100);
            $tax_amount = ($subtotal - $discount_amount) * 0.18; // 18% VAT
            $shipping_cost = ($cart_total > 100000) ? 0 : 5000;
            $total_amount = $subtotal - $discount_amount + $tax_amount + $shipping_cost;
            
            // Generate order number
            $order_number = 'ORD-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
            
            // Start transaction
            $db->beginTransaction();
            
            // Insert order
            $sql = "INSERT INTO orders (
                        order_number, 
                        user_id, 
                        order_status, 
                        payment_status,
                        subtotal, 
                        discount_amount, 
                        tax_amount, 
                        shipping_cost, 
                        total_amount,
                        shipping_address, 
                        billing_address, 
                        payment_method, 
                        notes,
                        created_at
                    ) VALUES (
                        ?, ?, ?, ?,
                        ?, ?, ?, ?, ?,
                        ?, ?, ?, ?,
                        NOW()
                    )";
            
            $stmt = $db->prepare($sql);
            $stmt->execute([
                $order_number,
                $user_id,
                'pending',
                'pending',
                $subtotal,
                $discount_amount,
                $tax_amount,
                $shipping_cost,
                $total_amount,
                $shipping_address,
                $billing_address ?: $shipping_address,
                $payment_method,
                $notes
            ]);
            
            $order_id = $db->lastInsertId();
            
            // Insert order items
            foreach ($cart_items as $item) {
                $product = $item['product'];
                $sql = "INSERT INTO order_items (
                            order_id, 
                            product_id, 
                            quantity, 
                            unit_price, 
                            total_price
                        ) VALUES (?, ?, ?, ?, ?)";
                $stmt = $db->prepare($sql);
                $stmt->execute([
                    $order_id,
                    $product['id'],
                    $item['quantity'],
                    $item['price'],
                    $item['line_total']
                ]);
                
                // Update product stock
                $stmt = $db->prepare("UPDATE products SET stock_quantity = stock_quantity - ? WHERE id = ?");
                $stmt->execute([$item['quantity'], $product['id']]);
            }
            
            // Create escrow payment
            $escrow_sql = "INSERT INTO escrow_payments (order_id, amount, status, created_at) VALUES (?, ?, 'pending', NOW())";
            $escrow_stmt = $db->prepare($escrow_sql);
            $escrow_stmt->execute([$order_id, $total_amount]);
            
            // Create order tracking
            $track_sql = "INSERT INTO order_tracking (order_id, status, description, created_at) VALUES (?, 'pending', 'Order placed successfully', NOW())";
            $track_stmt = $db->prepare($track_sql);
            $track_stmt->execute([$order_id]);
            
            // Create notification - FIXED: Using function from functions.php
            try {
                addNotification($user_id, 'order', 'Order Placed', 'Your order ' . $order_number . ' has been placed successfully.', 'customer/orders/order-details.php?id=' . $order_id);
            } catch (Exception $e) {
                // Continue even if notification fails
            }
            
            // Log activity
            logActivity($user_id, 'Placed Order', 'order', $order_id);
            
            $db->commit();
            
            // Clear cart
            $_SESSION['cart'] = [];
            
            // Redirect to payment page
            $_SESSION['order_id'] = $order_id;
            $_SESSION['order_number'] = $order_number;
            $_SESSION['order_total'] = $total_amount;
            
            header("Location: payment.php?order=" . $order_id);
            exit();
            
        } catch (Exception $e) {
            $db->rollBack();
            $error = 'Failed to place order: ' . $e->getMessage();
        }
    }
}

// =============================================
// GET PAYMENT METHODS
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
// HELPER FUNCTIONS
// =============================================

if (!function_exists('formatPrice')) {
    function formatPrice($price) {
        if (empty($price) && $price !== 0) return 'TSh 0.00';
        return 'TSh ' . number_format((float)$price, 2);
    }
}

// =============================================
// FUNCTION TO GET PRODUCT IMAGE PATH
// =============================================
function getProductImagePath($product) {
    if (!empty($product['primary_image'])) {
        // If image path already starts with 'uploads/', use as is
        if (strpos($product['primary_image'], 'uploads/') === 0) {
            return '../../' . $product['primary_image'];
        }
        // If it's just a filename, construct the full path
        return '../../uploads/products/' . $product['id'] . '/' . $product['primary_image'];
    }
    // No image - return placeholder
    return '../../assets/images/placeholder-product.jpg';
}

// Calculate discounts
$discount_percent = 0;
if ($cart_count >= 200) $discount_percent = 25;
elseif ($cart_count >= 50) $discount_percent = 15;
elseif ($cart_count >= 10) $discount_percent = 8;
elseif ($cart_count >= 5) $discount_percent = 3;

$discount_amount = $cart_total * ($discount_percent / 100);
$tax_amount = ($cart_total - $discount_amount) * 0.18;
$shipping_cost = ($cart_total > 100000) ? 0 : 5000;
$grand_total = $cart_total - $discount_amount + $tax_amount + $shipping_cost;

// Get cart count for navbar
$cart_count_badge = $cart_count;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - TechProcure Tanzania</title>
    
    <!-- CSS Dependencies -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f8f9fa;
        }
        
        .checkout-header {
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
        
        .checkout-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 24px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        
        .checkout-card .card-title {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #e9ecef;
        }
        
        .checkout-card .card-title i {
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
        
        .btn-place-order {
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
        
        .btn-place-order:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(13,110,253,0.3);
        }
        
        .btn-place-order:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        
        .cart-item-small {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 8px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .cart-item-small:last-child {
            border-bottom: none;
        }
        
        .cart-item-small img {
            width: 50px;
            height: 50px;
            object-fit: cover;
            background: #f8f9fa;
            border-radius: 6px;
            padding: 5px;
        }
        
        .cart-item-small .item-name {
            font-size: 0.9rem;
            font-weight: 500;
        }
        
        .cart-item-small .item-qty {
            font-size: 0.8rem;
            color: #6c757d;
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
            .checkout-card {
                padding: 20px;
            }
            .payment-method-item {
                padding: 12px;
            }
        }
    </style>
</head>
<body>

<!-- Header -->
<div class="checkout-header">
    <div class="container">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <a href="../cart.php" class="back-link">
                    <i class="fas fa-arrow-left me-2"></i>Back to Cart
                </a>
                <h1 class="display-5 fw-bold mt-2"><i class="fas fa-credit-card me-3"></i>Checkout</h1>
                <p class="mb-0 opacity-75">Complete your purchase securely</p>
            </div>
            <div>
                <span class="badge bg-light text-dark fs-6">
                    <i class="fas fa-box me-2"></i><?php echo $cart_count; ?> items
                </span>
            </div>
        </div>
    </div>
</div>

<!-- Main Content -->
<div class="container">
    <?php if($error): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="fas fa-exclamation-triangle me-2"></i> <?php echo $error; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <div class="row">
        <!-- Checkout Form -->
        <div class="col-lg-8">
            <form method="POST" action="" id="checkoutForm">
                <!-- Shipping Address -->
                <div class="checkout-card">
                    <div class="card-title">
                        <i class="fas fa-truck"></i> Shipping Address
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label required">Shipping Address</label>
                        <textarea name="shipping_address" class="form-control" rows="3" 
                                  placeholder="Enter your shipping address" required><?php echo htmlspecialchars($_POST['shipping_address'] ?? $user['company_address'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Billing Address</label>
                        <textarea name="billing_address" class="form-control" rows="2" 
                                  placeholder="Enter billing address (same as shipping if left blank)"><?php echo htmlspecialchars($_POST['billing_address'] ?? ''); ?></textarea>
                        <small class="text-muted">Leave blank if same as shipping address</small>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Phone Number</label>
                            <input type="tel" name="phone" class="form-control" 
                                   value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" 
                                   placeholder="+255 XXX XXX XXX">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" 
                                   value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" 
                                   disabled>
                            <small class="text-muted">We'll send order confirmation here</small>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Order Notes (Optional)</label>
                        <textarea name="notes" class="form-control" rows="2" 
                                  placeholder="Any special instructions for delivery"><?php echo htmlspecialchars($_POST['notes'] ?? ''); ?></textarea>
                    </div>
                </div>
                
                <!-- Payment Method -->
                <div class="checkout-card">
                    <div class="card-title">
                        <i class="fas fa-wallet"></i> Payment Method
                    </div>
                    
                    <?php foreach($payment_methods as $key => $method): ?>
                    <div class="payment-method-item <?php echo (isset($_POST['payment_method']) && $_POST['payment_method'] == $key) ? 'active' : ''; ?>" 
                         onclick="selectPayment('<?php echo $key; ?>')">
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
                                       class="form-check-input" 
                                       <?php echo (isset($_POST['payment_method']) && $_POST['payment_method'] == $key) ? 'checked' : ''; ?> 
                                       required>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- Terms -->
                <div class="checkout-card">
                    <div class="form-check">
                        <input type="checkbox" name="terms" class="form-check-input" id="terms" required>
                        <label class="form-check-label" for="terms">
                            I agree to the <a href="#" data-bs-toggle="modal" data-bs-target="#termsModal">Terms & Conditions</a> 
                            and confirm that all information provided is correct
                        </label>
                    </div>
                    
                    <hr>
                    
                    <button type="submit" class="btn-place-order" id="placeOrderBtn">
                        <i class="fas fa-lock me-2"></i> Place Order
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
            <div class="checkout-card sticky-top" style="top: 20px;">
                <div class="card-title">
                    <i class="fas fa-receipt"></i> Order Summary
                </div>
                
                <!-- Cart Items -->
                <div class="mb-3">
                    <?php foreach($cart_items as $item): ?>
                    <?php $product = $item['product']; ?>
                    <div class="cart-item-small">
                        <img src="<?php echo getProductImagePath($product); ?>" 
                             alt="<?php echo htmlspecialchars($product['product_name']); ?>" 
                             onerror="this.src='../../assets/images/placeholder-product.jpg'">
                        <div class="flex-grow-1">
                            <div class="item-name"><?php echo htmlspecialchars($product['product_name']); ?></div>
                            <div class="item-qty">Qty: <?php echo $item['quantity']; ?> x <?php echo formatPrice($item['price']); ?></div>
                        </div>
                        <div class="fw-bold"><?php echo formatPrice($item['line_total']); ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="order-summary">
                    <div class="summary-row">
                        <span>Subtotal</span>
                        <span><?php echo formatPrice($cart_total); ?></span>
                    </div>
                    
                    <?php if($discount_percent > 0): ?>
                    <div class="summary-row text-success">
                        <span>Bulk Discount (<?php echo $discount_percent; ?>%)</span>
                        <span>-<?php echo formatPrice($discount_amount); ?></span>
                    </div>
                    <?php endif; ?>
                    
                    <div class="summary-row">
                        <span>Tax (18%)</span>
                        <span><?php echo formatPrice($tax_amount); ?></span>
                    </div>
                    
                    <div class="summary-row">
                        <span>Shipping</span>
                        <span><?php echo $shipping_cost == 0 ? 'Free' : formatPrice($shipping_cost); ?></span>
                    </div>
                    
                    <div class="summary-row total-row">
                        <span>Total</span>
                        <span><?php echo formatPrice($grand_total); ?></span>
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
</div>

<!-- Terms Modal -->
<div class="modal fade" id="termsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">Terms & Conditions</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" style="max-height: 400px; overflow-y: auto;">
                <h6>1. Order Acceptance</h6>
                <p>All orders are subject to acceptance and availability. We reserve the right to cancel any order.</p>
                
                <h6>2. Pricing</h6>
                <p>Prices are in Tanzanian Shillings (TSh) and include VAT. Bulk discounts apply automatically.</p>
                
                <h6>3. Payment</h6>
                <p>Payment is processed through secure payment gateways. Escrow protection ensures your money is safe until delivery.</p>
                
                <h6>4. Delivery</h6>
                <p>Delivery times are estimates. We are not liable for delays caused by third-party logistics.</p>
                
                <h6>5. Returns</h6>
                <p>Returns accepted within 14 days for defective products. Terms and conditions apply.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" data-bs-dismiss="modal">I Agree</button>
            </div>
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
                <h6>Customer Service</h6>
                <ul class="list-unstyled">
                    <li><a href="../../about.php">About Us</a></li>
                    <li><a href="../../contact.php">Contact</a></li>
                    <li><a href="../../faq.php">FAQ</a></li>
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
    }
    
    // Form validation before submit
    document.getElementById('checkoutForm').addEventListener('submit', function(e) {
        const terms = document.getElementById('terms');
        if (!terms.checked) {
            e.preventDefault();
            alert('Please agree to the Terms & Conditions.');
            return false;
        }
        
        const paymentMethod = document.querySelector('input[name="payment_method"]:checked');
        if (!paymentMethod) {
            e.preventDefault();
            alert('Please select a payment method.');
            return false;
        }
        
        const shippingAddress = document.querySelector('textarea[name="shipping_address"]');
        if (!shippingAddress || shippingAddress.value.trim() === '') {
            e.preventDefault();
            alert('Please enter shipping address.');
            return false;
        }
        
        const btn = document.getElementById('placeOrderBtn');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status"></span> Processing...';
    });
</script>

</body>
</html>