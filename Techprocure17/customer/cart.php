<?php
/**
 * TechProcure Tanzania - Customer Shopping Cart
 * File: customer/cart.php
 * Description: Complete shopping cart with AJAX functionality
 */

// Start session if not started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include configuration files
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Get database connection
$db = getDB();

// =============================================
// INITIALIZE CART
// =============================================

if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// =============================================
// HANDLE AJAX REQUESTS
// =============================================

if (isset($_POST['ajax_action'])) {
    header('Content-Type: application/json');
    
    $action = $_POST['ajax_action'];
    $product_id = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
    $quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 1;
    
    switch ($action) {
        case 'add':
            if ($product_id <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid product ID']);
                exit;
            }
            
            try {
                $stmt = $db->prepare("SELECT id, product_name, price_tsh, stock_quantity, status, approval_status FROM products WHERE id = ? AND status = 'active' AND approval_status = 'approved'");
                $stmt->execute([$product_id]);
                $product = $stmt->fetch();
                
                if (!$product) {
                    echo json_encode(['success' => false, 'message' => 'Product not found']);
                    exit;
                }
                
                if ($product['stock_quantity'] < $quantity) {
                    echo json_encode(['success' => false, 'message' => 'Not enough stock available']);
                    exit;
                }
                
                if (isset($_SESSION['cart'][$product_id])) {
                    $_SESSION['cart'][$product_id]['quantity'] += $quantity;
                } else {
                    $_SESSION['cart'][$product_id] = [
                        'id' => $product_id,
                        'name' => $product['product_name'],
                        'price' => $product['price_tsh'],
                        'quantity' => $quantity
                    ];
                }
                
                $count = 0;
                $total = 0;
                foreach ($_SESSION['cart'] as $item) {
                    $count += $item['quantity'];
                    $total += $item['price'] * $item['quantity'];
                }
                
                echo json_encode(['success' => true, 'message' => 'Product added to cart', 'count' => $count, 'total' => $total]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Database error']);
            }
            exit;
            
        case 'update':
            if ($product_id > 0) {
                if ($quantity <= 0) {
                    unset($_SESSION['cart'][$product_id]);
                } else {
                    $stmt = $db->prepare("SELECT stock_quantity FROM products WHERE id = ?");
                    $stmt->execute([$product_id]);
                    $product = $stmt->fetch();
                    if ($product && $quantity <= $product['stock_quantity']) {
                        $_SESSION['cart'][$product_id]['quantity'] = $quantity;
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Not enough stock available']);
                        exit;
                    }
                }
            }
            $count = 0;
            $total = 0;
            foreach ($_SESSION['cart'] as $item) {
                $count += $item['quantity'];
                $total += $item['price'] * $item['quantity'];
            }
            echo json_encode(['success' => true, 'count' => $count, 'total' => $total]);
            exit;
            
        case 'remove':
            if ($product_id > 0 && isset($_SESSION['cart'][$product_id])) {
                unset($_SESSION['cart'][$product_id]);
            }
            $count = 0;
            $total = 0;
            foreach ($_SESSION['cart'] as $item) {
                $count += $item['quantity'];
                $total += $item['price'] * $item['quantity'];
            }
            echo json_encode(['success' => true, 'count' => $count, 'total' => $total]);
            exit;
            
        case 'clear':
            $_SESSION['cart'] = [];
            echo json_encode(['success' => true, 'count' => 0, 'total' => 0]);
            exit;
            
        case 'get_count':
            $count = 0;
            foreach ($_SESSION['cart'] as $item) {
                $count += $item['quantity'];
            }
            echo json_encode(['count' => $count]);
            exit;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            exit;
    }
}

// =============================================
// HANDLE FORM SUBMISSIONS
// =============================================

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['update_cart'])) {
        foreach ($_POST['quantity'] as $product_id => $qty) {
            $qty = (int)$qty;
            $product_id = (int)$product_id;
            if ($qty <= 0) {
                unset($_SESSION['cart'][$product_id]);
            } else {
                $stmt = $db->prepare("SELECT stock_quantity FROM products WHERE id = ?");
                $stmt->execute([$product_id]);
                $product = $stmt->fetch();
                if ($product && $qty <= $product['stock_quantity']) {
                    $_SESSION['cart'][$product_id]['quantity'] = $qty;
                }
            }
        }
        $_SESSION['success'] = 'Cart updated successfully!';
        header("Location: cart.php");
        exit();
    }
    
    if (isset($_POST['clear_cart'])) {
        $_SESSION['cart'] = [];
        $_SESSION['success'] = 'Cart cleared successfully!';
        header("Location: cart.php");
        exit();
    }
}

// =============================================
// GET CART ITEMS WITH PRODUCT DETAILS
// =============================================

$cart_items = [];
$cart_total = 0;
$cart_count = 0;

if (!empty($_SESSION['cart'])) {
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
        $cart_items = [];
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

function getBulkDiscount($quantity) {
    if ($quantity >= 200) return 25;
    if ($quantity >= 50) return 15;
    if ($quantity >= 10) return 8;
    if ($quantity >= 5) return 3;
    return 0;
}

// Get cart count for badge
$cart_count_badge = isset($_SESSION['cart']) ? array_sum(array_column($_SESSION['cart'], 'quantity')) : 0;

// Get user info for checkout
$user = null;
if (isset($_SESSION['user_id'])) {
    try {
        $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();
    } catch (PDOException $e) {
        $user = null;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shopping Cart - TechProcure Tanzania</title>
    
    <!-- CSS Dependencies -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f8f9fa;
            padding-top: 20px;
        }
        
        /* Back Link */
        .back-link {
            text-decoration: none;
            color: #0d6efd;
            font-weight: 500;
            transition: all 0.3s;
        }
        
        .back-link:hover {
            color: #0b5ed7;
            padding-left: 5px;
        }
        
        /* Cart Header */
        .cart-header {
            background: linear-gradient(135deg, #1a2a6c, #b21f1f, #fdbb4d);
            color: white;
            padding: 40px 0;
            border-radius: 15px;
            margin-bottom: 30px;
        }
        
        /* Cart Items */
        .cart-item {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 16px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            transition: all 0.3s;
        }
        
        .cart-item:hover {
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
        
        .cart-item-image {
            width: 100px;
            height: 100px;
            object-fit: contain;
            background: #f8f9fa;
            border-radius: 8px;
            padding: 10px;
        }
        
        .cart-item-title {
            font-weight: 600;
            font-size: 1rem;
            margin-bottom: 4px;
        }
        
        .cart-item-price {
            font-weight: 700;
            color: #0d6efd;
            font-size: 1.1rem;
        }
        
        /* Quantity Selector */
        .quantity-selector {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .quantity-selector button {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            border: 1px solid #ddd;
            background: white;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .quantity-selector button:hover {
            background: #0d6efd;
            color: white;
            border-color: #0d6efd;
        }
        
        .quantity-selector input {
            width: 50px;
            text-align: center;
            border: 1px solid #ddd;
            border-radius: 6px;
            padding: 4px;
            font-size: 0.9rem;
        }
        
        /* Cart Summary */
        .cart-summary {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            position: sticky;
            top: 20px;
        }
        
        .cart-summary .summary-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #e9ecef;
        }
        
        .cart-summary .summary-row:last-child {
            border-bottom: none;
        }
        
        .cart-summary .total-row {
            font-size: 1.2rem;
            font-weight: 700;
            color: #0d6efd;
        }
        
        /* Buttons */
        .btn-checkout {
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
        
        .btn-checkout:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(25,135,84,0.3);
        }
        
        .btn-checkout:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        /* Empty Cart */
        .empty-cart {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 12px;
        }
        
        .empty-cart i {
            font-size: 4rem;
            color: #dee2e6;
            margin-bottom: 20px;
        }
        
        /* Toast */
        .toast-container {
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 9999;
        }
        
        .toast {
            min-width: 300px;
        }
        
        /* Footer */
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
            .cart-item .row {
                flex-direction: column;
                text-align: center;
            }
            .cart-item-image {
                width: 80px;
                height: 80px;
                margin: 0 auto;
            }
            .quantity-selector {
                justify-content: center;
            }
        }
    </style>
</head>
<body>

<!-- ============================================= -->
<!-- BACK BUTTON -->
<!-- ============================================= -->
<div class="container">
    <div class="mb-3">
        <a href="../products.php" class="back-link">
            <i class="fas fa-arrow-left me-2"></i>Continue Shopping
        </a>
        <span class="float-end">
            <a href="../index.php" class="text-dark text-decoration-none">
                <i class="fas fa-home"></i>
            </a>
        </span>
    </div>
</div>

<!-- ============================================= -->
<!-- CART HEADER -->
<!-- ============================================= -->
<div class="container">
    <div class="cart-header">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="display-5 fw-bold mb-0"><i class="fas fa-shopping-cart me-3"></i>Shopping Cart</h1>
                    <p class="mb-0 opacity-75">Review your items before checkout</p>
                </div>
                <div>
                    <span class="badge bg-light text-dark fs-6" id="cartBadge">
                        <i class="fas fa-box me-2"></i><?php echo $cart_count; ?> items
                    </span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ============================================= -->
<!-- MAIN CONTENT -->
<!-- ============================================= -->
<div class="container">
    <!-- Success/Error Messages -->
    <?php if(isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show" id="successAlert">
            <i class="fas fa-check-circle me-2"></i> <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <?php if(!empty($cart_items)): ?>
    <div class="row">
        <!-- Cart Items -->
        <div class="col-lg-8">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="mb-0">Cart Items (<?php echo count($cart_items); ?> products)</h5>
                <button onclick="clearCart()" class="btn btn-sm btn-outline-danger">
                    <i class="fas fa-trash me-1"></i> Clear Cart
                </button>
            </div>
            
            <div id="cartItemsContainer">
                <?php foreach($cart_items as $item): ?>
                <?php 
                $product = $item['product'];
                $image_path = !empty($item['primary_image']) ? $item['primary_image'] : '../assets/images/placeholder-product.jpg';
                if (!empty($item['primary_image']) && strpos($item['primary_image'], 'uploads/') === false) {
                    $image_path = '../uploads/products/' . $product['id'] . '/' . $item['primary_image'];
                }
                ?>
                <div class="cart-item" data-product-id="<?php echo $product['id']; ?>" id="cart-item-<?php echo $product['id']; ?>">
                    <div class="row align-items-center">
                        <div class="col-md-2 text-center">
                            <img src="<?php echo $image_path; ?>" alt="<?php echo htmlspecialchars($product['product_name']); ?>" class="cart-item-image" onerror="this.src='../assets/images/placeholder-product.jpg'">
                        </div>
                        <div class="col-md-4">
                            <h6 class="cart-item-title"><?php echo htmlspecialchars($product['product_name']); ?></h6>
                            <small class="text-muted"><?php echo htmlspecialchars($product['brand'] ?? 'Generic'); ?></small>
                            <?php if($product['sku']): ?>
                            <br><small class="text-muted">SKU: <?php echo htmlspecialchars($product['sku']); ?></small>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-2 text-center">
                            <div class="cart-item-price" id="price-<?php echo $product['id']; ?>"><?php echo formatPrice($item['price']); ?></div>
                        </div>
                        <div class="col-md-2 text-center">
                            <div class="quantity-selector">
                                <button onclick="updateQuantity(<?php echo $product['id']; ?>, -1)">-</button>
                                <input type="number" id="qty-<?php echo $product['id']; ?>" 
                                       value="<?php echo $item['quantity']; ?>" 
                                       min="1" max="<?php echo $product['stock_quantity']; ?>"
                                       data-product-id="<?php echo $product['id']; ?>"
                                       onchange="updateQuantityInput(this)">
                                <button onclick="updateQuantity(<?php echo $product['id']; ?>, 1)">+</button>
                            </div>
                            <small class="text-muted">Stock: <?php echo $product['stock_quantity']; ?></small>
                        </div>
                        <div class="col-md-2 text-center">
                            <div>
                                <strong id="line-total-<?php echo $product['id']; ?>"><?php echo formatPrice($item['line_total']); ?></strong>
                            </div>
                            <button onclick="removeItem(<?php echo $product['id']; ?>)" class="btn btn-sm btn-outline-danger mt-1">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Bulk Discount Info -->
            <div class="alert alert-info mt-3">
                <i class="fas fa-tags me-2"></i>
                <strong>Bulk Discounts Available:</strong>
                5-9 units: 3% | 10-49 units: 8% | 50-199 units: 15% | 200+ units: 25%
            </div>
        </div>
        
        <!-- Cart Summary -->
        <div class="col-lg-4">
            <div class="cart-summary" id="cartSummary">
                <h5 class="mb-3"><i class="fas fa-receipt me-2 text-primary"></i>Order Summary</h5>
                
                <div class="summary-row" id="summary-items">
                    <span>Items (<?php echo $cart_count; ?>)</span>
                    <span id="subtotal"><?php echo formatPrice($cart_total); ?></span>
                </div>
                
                <?php 
                $discount_percent = getBulkDiscount($cart_count);
                $discount_amount = 0;
                if ($discount_percent > 0) {
                    $discount_amount = $cart_total * ($discount_percent / 100);
                }
                $shipping_cost = ($cart_total > 100000) ? 0 : 5000;
                $tax = $cart_total * 0.18;
                $grand_total = $cart_total - $discount_amount + $shipping_cost + $tax;
                ?>
                
                <?php if($discount_amount > 0): ?>
                <div class="summary-row text-success" id="summary-discount">
                    <span>Bulk Discount (<?php echo $discount_percent; ?>%)</span>
                    <span>-<?php echo formatPrice($discount_amount); ?></span>
                </div>
                <?php endif; ?>
                
                <div class="summary-row" id="summary-shipping">
                    <span>Shipping</span>
                    <span><?php echo $shipping_cost == 0 ? 'Free' : formatPrice($shipping_cost); ?></span>
                </div>
                
                <div class="summary-row" id="summary-tax">
                    <span>Tax (18%)</span>
                    <span><?php echo formatPrice($tax); ?></span>
                </div>
                
                <div class="summary-row total-row" id="summary-total">
                    <span>Total</span>
                    <span id="grandTotal"><?php echo formatPrice($grand_total); ?></span>
                </div>
                
                <hr>
                
                <?php if(isset($_SESSION['user_id'])): ?>
                    <a href="cart/checkout.php" class="btn-checkout">
                        <i class="fas fa-lock me-2"></i> Proceed to Checkout
                    </a>
                <?php else: ?>
                    <a href="../auth/login.php" class="btn-checkout">
                        <i class="fas fa-sign-in-alt me-2"></i> Login to Checkout
                    </a>
                    <div class="text-center mt-2">
                        <small class="text-muted">Please login to complete your purchase</small>
                    </div>
                <?php endif; ?>
                
                <div class="text-center mt-3">
                    <small class="text-muted">
                        <i class="fas fa-shield-alt me-1"></i> Secure checkout with escrow protection
                    </small>
                </div>
            </div>
        </div>
    </div>
    
    <?php else: ?>
    <!-- Empty Cart -->
    <div class="empty-cart">
        <i class="fas fa-shopping-cart"></i>
        <h4>Your cart is empty</h4>
        <p class="text-muted">Looks like you haven't added any items to your cart yet.</p>
        <a href="../products.php" class="btn btn-primary btn-lg">
            <i class="fas fa-shopping-cart me-2"></i> Start Shopping
        </a>
    </div>
    <?php endif; ?>
</div>

<!-- ============================================= -->
<!-- TOAST CONTAINER -->
<!-- ============================================= -->
<div class="toast-container"></div>

<!-- ============================================= -->
<!-- FOOTER -->
<!-- ============================================= -->
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
                    <li><i class="fas fa-map-marker-alt me-2"></i> Dar es Salaam, Tanzania</li>
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
    // =============================================
    // UPDATE QUANTITY - AJAX
    // =============================================
    function updateQuantity(productId, change) {
        const input = document.getElementById(`qty-${productId}`);
        if (!input) return;
        
        let value = parseInt(input.value) || 0;
        const max = parseInt(input.max) || 999;
        
        value += change;
        if (value < 1) value = 1;
        if (value > max) value = max;
        
        input.value = value;
        updateCartItem(productId, value);
    }
    
    function updateQuantityInput(input) {
        const productId = parseInt(input.dataset.productId);
        let value = parseInt(input.value) || 1;
        const max = parseInt(input.max) || 999;
        
        if (value < 1) value = 1;
        if (value > max) value = max;
        input.value = value;
        
        updateCartItem(productId, value);
    }
    
    function updateCartItem(productId, quantity) {
        const item = document.getElementById(`cart-item-${productId}`);
        if (item) item.style.opacity = '0.5';
        
        $.ajax({
            url: window.location.href,
            type: 'POST',
            data: {
                ajax_action: 'update',
                product_id: productId,
                quantity: quantity
            },
            dataType: 'json',
            success: function(response) {
                if (item) item.style.opacity = '1';
                
                if (response.success) {
                    updateCartDisplay(response.count, response.total);
                    
                    // Update line total
                    const priceText = document.getElementById(`price-${productId}`).textContent.replace(/[^0-9.]/g, '');
                    const price = parseFloat(priceText);
                    const lineTotal = document.getElementById(`line-total-${productId}`);
                    if (lineTotal) {
                        lineTotal.textContent = formatPrice(price * quantity);
                    }
                    
                    showToast('Success', 'Cart updated!', 'success');
                } else {
                    showToast('Error', response.message || 'Failed to update cart', 'error');
                }
            },
            error: function() {
                if (item) item.style.opacity = '1';
                showToast('Error', 'Failed to update cart. Please try again.', 'error');
            }
        });
    }
    
    // =============================================
    // REMOVE ITEM - AJAX
    // =============================================
    function removeItem(productId) {
        if (!confirm('Remove this item from cart?')) return;
        
        const item = document.getElementById(`cart-item-${productId}`);
        if (item) item.style.opacity = '0.5';
        
        $.ajax({
            url: window.location.href,
            type: 'POST',
            data: {
                ajax_action: 'remove',
                product_id: productId
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    if (item) item.remove();
                    updateCartDisplay(response.count, response.total);
                    
                    if (response.count === 0) {
                        location.reload();
                    }
                    showToast('Success', 'Item removed from cart', 'success');
                } else {
                    showToast('Error', response.message || 'Failed to remove item', 'error');
                }
            },
            error: function() {
                if (item) item.style.opacity = '1';
                showToast('Error', 'Failed to remove item. Please try again.', 'error');
            }
        });
    }
    
    // =============================================
    // CLEAR CART - AJAX
    // =============================================
    function clearCart() {
        if (!confirm('Clear all items from cart?')) return;
        
        $.ajax({
            url: window.location.href,
            type: 'POST',
            data: {
                ajax_action: 'clear'
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    location.reload();
                }
            }
        });
    }
    
    // =============================================
    // UPDATE CART DISPLAY
    // =============================================
    function updateCartDisplay(count, total) {
        // Update badge
        const badge = document.getElementById('cartBadge');
        if (badge) {
            badge.innerHTML = `<i class="fas fa-box me-2"></i>${count} items`;
        }
        
        // Update subtotal
        const subtotal = document.getElementById('subtotal');
        if (subtotal) {
            subtotal.textContent = formatPrice(total);
        }
        
        // Update summary items
        const itemsRow = document.getElementById('summary-items');
        if (itemsRow) {
            itemsRow.innerHTML = `<span>Items (${count})</span><span>${formatPrice(total)}</span>`;
        }
        
        // Update grand total (with tax calculation)
        const tax = total * 0.18;
        const grandTotal = total + tax;
        const grandTotalEl = document.getElementById('grandTotal');
        if (grandTotalEl) {
            grandTotalEl.textContent = formatPrice(grandTotal);
        }
        
        // Update tax
        const taxRow = document.getElementById('summary-tax');
        if (taxRow) {
            taxRow.innerHTML = `<span>Tax (18%)</span><span>${formatPrice(tax)}</span>`;
        }
    }
    
    // =============================================
    // FORMAT PRICE
    // =============================================
    function formatPrice(price) {
        return 'TSh ' + Number(price).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    }
    
    // =============================================
    // SHOW TOAST
    // =============================================
    function showToast(title, message, type) {
        const container = document.querySelector('.toast-container');
        const toast = document.createElement('div');
        toast.className = `toast align-items-center text-white bg-${type === 'success' ? 'success' : 'danger'} border-0`;
        toast.setAttribute('role', 'alert');
        toast.setAttribute('aria-live', 'assertive');
        toast.setAttribute('aria-atomic', 'true');
        toast.style.display = 'block';
        toast.innerHTML = `
            <div class="d-flex">
                <div class="toast-body">
                    <strong>${title}</strong><br>
                    ${message}
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        `;
        container.appendChild(toast);
        
        const bsToast = new bootstrap.Toast(toast, { delay: 4000 });
        bsToast.show();
        
        toast.addEventListener('hidden.bs.toast', function() {
            toast.remove();
        });
    }
    
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