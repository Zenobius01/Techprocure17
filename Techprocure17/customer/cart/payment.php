<?php
/**
 * TechProcure Tanzania - Payment Processing Page (Real Implementation)
 * File: customer/cart/payment.php
 * Description: Complete payment processing with M-Pesa Daraja API integration
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
$order_id = isset($_GET['order']) ? (int)$_GET['order'] : 0;

if ($order_id <= 0) {
    $_SESSION['error'] = "Invalid order ID.";
    header("Location: ../orders/my-orders.php");
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
        $error = "Order not found.";
    } elseif ($order['payment_status'] == 'paid') {
        $error = "This order has already been paid.";
    } elseif ($order['order_status'] == 'cancelled') {
        $error = "This order has been cancelled.";
    }
} catch (PDOException $e) {
    $error = "Failed to load order details.";
}

// =============================================
// PAYMENT GATEWAY CONFIGURATION
// =============================================

$payment_methods = [
    'mpesa' => [
        'name' => 'M-Pesa',
        'icon' => 'fas fa-mobile-alt',
        'color' => '#4CAF50',
        'description' => 'Pay using M-Pesa mobile money',
        'fields' => ['phone_number'],
        'real_api' => true
    ],
    'airtel_money' => [
        'name' => 'Airtel Money',
        'icon' => 'fas fa-mobile-alt',
        'color' => '#FF6B00',
        'description' => 'Pay using Airtel Money',
        'fields' => ['phone_number'],
        'real_api' => false
    ],
    'tigo_pesa' => [
        'name' => 'Tigo Pesa',
        'icon' => 'fas fa-mobile-alt',
        'color' => '#E91E63',
        'description' => 'Pay using Tigo Pesa',
        'fields' => ['phone_number'],
        'real_api' => false
    ],
    'halopesa' => [
        'name' => 'Halopesa',
        'icon' => 'fas fa-mobile-alt',
        'color' => '#2196F3',
        'description' => 'Pay using Halopesa',
        'fields' => ['phone_number'],
        'real_api' => false
    ],
    'bank_transfer' => [
        'name' => 'Bank Transfer',
        'icon' => 'fas fa-university',
        'color' => '#9C27B0',
        'description' => 'Transfer directly to our bank account',
        'fields' => ['bank_name', 'account_number', 'account_name', 'payment_reference'],
        'real_api' => false
    ],
    'card' => [
        'name' => 'Credit/Debit Card',
        'icon' => 'fas fa-credit-card',
        'color' => '#607D8B',
        'description' => 'Pay using Visa, Mastercard',
        'fields' => ['card_number', 'card_expiry', 'card_cvv'],
        'real_api' => false
    ]
];

// Bank details
$bank_details = [
    'bank_name' => 'CRDB Bank',
    'account_number' => '1234567890',
    'account_name' => 'TechProcure Tanzania',
    'swift_code' => 'CORUTZTZ',
    'branch' => 'Dar es Salaam Main Branch'
];

// =============================================
// PAYMENT PROCESSING
// =============================================

$payment_success = false;
$payment_error = '';
$transaction_id = '';
$payment_reference = '';
$phone_clean = '';
$payment_method_used = '';
$payment_status = 'pending';
$payment_response = null;
$checkoutRequestID = '';
$payment_method = ''; // Initialize payment_method variable

// Default test phone number
$default_phone = getMpesaTestPhone();

if ($_SERVER['REQUEST_METHOD'] == 'POST' && $order) {
    $submitted_token = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
    
    if (empty($submitted_token) || !verifyCSRFToken($submitted_token)) {
        $payment_error = 'Security token validation failed. Please refresh and try again.';
    } else {
        $payment_method = sanitizeInput($_POST['payment_method'] ?? '');
        $phone_number = sanitizeInput($_POST['phone_number'] ?? $default_phone);
        $card_number = sanitizeInput($_POST['card_number'] ?? '');
        $card_expiry = sanitizeInput($_POST['card_expiry'] ?? '');
        $card_cvv = sanitizeInput($_POST['card_cvv'] ?? '');
        $bank_name = sanitizeInput($_POST['bank_name'] ?? '');
        $account_number = sanitizeInput($_POST['account_number'] ?? '');
        $account_name = sanitizeInput($_POST['account_name'] ?? '');
        $payment_reference = sanitizeInput($_POST['payment_reference'] ?? '');
        
        $valid = true;
        
        switch ($payment_method) {
            case 'mpesa':
            case 'airtel_money':
            case 'tigo_pesa':
            case 'halopesa':
                $phone_clean = preg_replace('/[\s\-\(\)\.]/', '', $phone_number);
                $phone_clean = preg_replace('/[^0-9+]/', '', $phone_clean);
                
                if (empty($phone_clean)) {
                    $payment_error = 'Please enter your phone number.';
                    $valid = false;
                } elseif (strlen($phone_clean) < 10) {
                    $payment_error = 'Phone number is too short. Please enter at least 10 digits.';
                    $valid = false;
                } elseif (strlen($phone_clean) > 15) {
                    $payment_error = 'Phone number is too long.';
                    $valid = false;
                } elseif (!preg_match('/^(0|255|\+255)[0-9]{9,12}$/', $phone_clean) && !preg_match('/^[0-9]{10,12}$/', $phone_clean)) {
                    $payment_error = 'Please enter a valid Tanzanian phone number (e.g., 0712345678 or +255712345678).';
                    $valid = false;
                }
                break;
                
            case 'card':
                $card_clean = preg_replace('/\s+/', '', $card_number);
                if (empty($card_clean) || strlen($card_clean) < 16) {
                    $payment_error = 'Please enter a valid card number.';
                    $valid = false;
                } elseif (empty($card_expiry)) {
                    $payment_error = 'Please enter card expiry date.';
                    $valid = false;
                } elseif (empty($card_cvv) || strlen($card_cvv) < 3) {
                    $payment_error = 'Please enter a valid CVV code.';
                    $valid = false;
                } elseif (!preg_match('/^\d{2}\/\d{2}$/', $card_expiry)) {
                    $payment_error = 'Please enter expiry date in MM/YY format.';
                    $valid = false;
                }
                break;
                
            case 'bank_transfer':
                if (empty($bank_name) || empty($account_number) || empty($account_name)) {
                    $payment_error = 'Please fill in all bank details.';
                    $valid = false;
                }
                if (empty($payment_reference)) {
                    $payment_error = 'Please provide a payment reference.';
                    $valid = false;
                }
                break;
                
            default:
                $payment_error = 'Please select a valid payment method.';
                $valid = false;
        }
        
        if ($valid) {
            try {
                $transaction_id = 'TXN-' . date('YmdHis') . '-' . strtoupper(substr(uniqid(), -6));
                $payment_number = 'PAY-' . date('Ymd') . '-' . str_pad(rand(1, 99999), 5, '0', STR_PAD_LEFT);
                $payment_method_used = $payment_method;
                
                // =============================================
                // M-PESA DARAJA API INTEGRATION
                // =============================================
                
                if ($payment_method == 'mpesa') {
                    // Format the phone number for M-Pesa
                    $phone_clean = formatMpesaPhone($phone_clean);
                    
                    // Send STK Push using the functions from functions.php
                    $amount = (int)$order['total_amount'];
                    $reference = $order['order_number'];
                    $desc = 'TechProcure Order Payment';
                    
                    $payment_response = mpesaStkPush($phone_clean, $amount, $reference, $desc);
                    
                    $db->beginTransaction();
                    
                    if ($payment_response['status'] == 'success') {
                        $checkoutRequestID = $payment_response['checkoutRequestID'] ?? '';
                        
                        // Update order
                        $sql = "UPDATE orders SET 
                                payment_status = 'pending',
                                payment_method = ?,
                                transaction_id = ?,
                                payment_reference = ?,
                                payment_data = ?,
                                updated_at = NOW()
                                WHERE id = ? AND user_id = ?";
                        $stmt = $db->prepare($sql);
                        $stmt->execute([
                            $payment_method,
                            $transaction_id,
                            $payment_number,
                            json_encode($payment_response),
                            $order_id,
                            $user_id
                        ]);
                        
                        // Create payment record
                        $pay_sql = "INSERT INTO payments (
                                        payment_number,
                                        order_id,
                                        user_id,
                                        amount,
                                        transaction_id,
                                        payment_method,
                                        payment_status,
                                        gateway_response,
                                        checkout_request_id,
                                        processed_at,
                                        created_at
                                    ) VALUES (?, ?, ?, ?, ?, ?, 'pending', ?, ?, NOW(), NOW())";
                        $pay_stmt = $db->prepare($pay_sql);
                        $pay_stmt->execute([
                            $payment_number,
                            $order_id,
                            $user_id,
                            $order['total_amount'],
                            $transaction_id,
                            $payment_method,
                            json_encode($payment_response),
                            $checkoutRequestID
                        ]);
                        
                        $db->commit();
                        
                        // Store pending info
                        $_SESSION['payment_pending'] = true;
                        $_SESSION['checkout_request_id'] = $checkoutRequestID;
                        $_SESSION['order_id'] = $order_id;
                        $_SESSION['payment_data'] = [
                            'order_id' => $order_id,
                            'order_number' => $order['order_number'],
                            'amount' => $order['total_amount'],
                            'transaction_id' => $transaction_id,
                            'payment_reference' => $payment_number,
                            'payment_method' => $payment_method,
                            'checkout_request_id' => $checkoutRequestID,
                            'customer_name' => $order['customer_name'],
                            'phone_number' => $phone_clean
                        ];
                        
                        // Add notification
                        addNotification(
                            $user_id,
                            'payment',
                            'M-Pesa STK Push Sent',
                            'An M-Pesa payment request of ' . formatPrice($order['total_amount']) . ' has been sent to ' . $phone_clean . '. Please check your phone and enter your PIN to complete the payment.',
                            '../customer/orders/order-details.php?id=' . $order_id
                        );
                        
                        // Redirect to pending page
                        header("Location: payment-pending.php?order=" . $order_id . "&checkout=" . $checkoutRequestID);
                        exit();
                        
                    } else {
                        // Payment failed
                        $payment_error = $payment_response['message'] ?? 'Payment processing failed. Please try again.';
                        
                        $pay_sql = "INSERT INTO payments (
                                        payment_number,
                                        order_id,
                                        user_id,
                                        amount,
                                        transaction_id,
                                        payment_method,
                                        payment_status,
                                        gateway_response,
                                        processed_at,
                                        created_at
                                    ) VALUES (?, ?, ?, ?, ?, ?, 'failed', ?, NOW(), NOW())";
                        $pay_stmt = $db->prepare($pay_sql);
                        $pay_stmt->execute([
                            $payment_number,
                            $order_id,
                            $user_id,
                            $order['total_amount'],
                            $transaction_id,
                            $payment_method,
                            json_encode($payment_response)
                        ]);
                        
                        $db->commit();
                    }
                    
                } else {
                    // =============================================
                    // OTHER PAYMENT METHODS (SIMULATED)
                    // =============================================
                    
                    $db->beginTransaction();
                    
                    // Simulate successful payment for non-M-Pesa methods
                    $success_rate = 90;
                    $is_success = rand(1, 100) <= $success_rate;
                    
                    if ($is_success) {
                        $payment_response = [
                            'status' => 'success',
                            'message' => 'Payment processed successfully',
                            'gateway_reference' => 'GATEWAY-' . strtoupper(substr(uniqid(), -8)),
                            'processing_time' => rand(2, 8) . ' seconds'
                        ];
                        
                        $sql = "UPDATE orders SET 
                                payment_status = 'paid',
                                payment_method = ?,
                                transaction_id = ?,
                                payment_reference = ?,
                                payment_data = ?,
                                updated_at = NOW()
                                WHERE id = ? AND user_id = ?";
                        $stmt = $db->prepare($sql);
                        $stmt->execute([
                            $payment_method,
                            $transaction_id,
                            $payment_number,
                            json_encode($payment_response),
                            $order_id,
                            $user_id
                        ]);
                        
                        $pay_sql = "INSERT INTO payments (
                                        payment_number,
                                        order_id,
                                        user_id,
                                        amount,
                                        transaction_id,
                                        payment_method,
                                        payment_status,
                                        gateway_response,
                                        processed_at,
                                        created_at
                                    ) VALUES (?, ?, ?, ?, ?, ?, 'completed', ?, NOW(), NOW())";
                        $pay_stmt = $db->prepare($pay_sql);
                        $pay_stmt->execute([
                            $payment_number,
                            $order_id,
                            $user_id,
                            $order['total_amount'],
                            $transaction_id,
                            $payment_method,
                            json_encode($payment_response)
                        ]);
                        
                        // Update escrow
                        try {
                            $escrow_sql = "UPDATE escrow_payments SET 
                                            status = 'pending',
                                            transaction_id = ?,
                                            paid_at = NOW()
                                            WHERE order_id = ?";
                            $escrow_stmt = $db->prepare($escrow_sql);
                            $escrow_stmt->execute([$transaction_id, $order_id]);
                        } catch (Exception $e) {
                            // Escrow table might not exist
                        }
                        
                        $db->commit();
                        
                        // Success notification
                        addNotification(
                            $user_id,
                            'payment',
                            'Payment Successful',
                            'Your payment of ' . formatPrice($order['total_amount']) . ' for order ' . $order['order_number'] . ' has been processed successfully.',
                            '../customer/orders/order-details.php?id=' . $order_id
                        );
                        
                        $payment_success = true;
                        
                        // Redirect to success
                        $_SESSION['payment_success'] = true;
                        $_SESSION['payment_data'] = [
                            'order_id' => $order_id,
                            'order_number' => $order['order_number'],
                            'amount' => $order['total_amount'],
                            'transaction_id' => $transaction_id,
                            'payment_reference' => $payment_number,
                            'payment_method' => $payment_method,
                            'customer_name' => $order['customer_name']
                        ];
                        
                        header("Location: payment-success.php?order=" . $order_id . "&txn=" . $transaction_id);
                        exit();
                        
                    } else {
                        $error_messages = [
                            'Insufficient funds',
                            'Transaction declined by bank',
                            'Invalid account details',
                            'Payment timeout'
                        ];
                        $payment_response = [
                            'status' => 'failed',
                            'message' => $error_messages[array_rand($error_messages)]
                        ];
                        
                        $pay_sql = "INSERT INTO payments (
                                        payment_number,
                                        order_id,
                                        user_id,
                                        amount,
                                        transaction_id,
                                        payment_method,
                                        payment_status,
                                        gateway_response,
                                        processed_at,
                                        created_at
                                    ) VALUES (?, ?, ?, ?, ?, ?, 'failed', ?, NOW(), NOW())";
                        $pay_stmt = $db->prepare($pay_sql);
                        $pay_stmt->execute([
                            $payment_number,
                            $order_id,
                            $user_id,
                            $order['total_amount'],
                            $transaction_id,
                            $payment_method,
                            json_encode($payment_response)
                        ]);
                        
                        $db->commit();
                        $payment_error = $payment_response['message'];
                    }
                }
                
            } catch (Exception $e) {
                if (isset($db)) {
                    $db->rollBack();
                }
                $payment_error = 'Payment processing failed: ' . $e->getMessage();
                error_log("Payment Error: " . $e->getMessage());
            }
        }
    }
}

// =============================================
// REGENERATE CSRF TOKEN
// =============================================

unset($_SESSION['csrf_token']);
unset($_SESSION['csrf_token_time']);
$csrf_token = generateCSRFToken();

// Get cart count
$cart_count = isset($_SESSION['cart']) ? array_sum(array_column($_SESSION['cart'], 'quantity')) : 0;

// Check if there's a pending payment
$pending_payment = isset($_SESSION['payment_pending']) && $_SESSION['payment_pending'] === true;

// Get M-Pesa config for display
$mpesa_config = getMpesaConfig();
$test_phone = getMpesaTestPhone();
$is_mpesa_configured = isMpesaConfigured();
$is_simulation = defined('MPESA_SIMULATION_MODE') && MPESA_SIMULATION_MODE;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment - TechProcure Tanzania</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f8f9fa;
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
        
        .payment-method-item .badge-gateway {
            font-size: 0.6rem;
            background: #0d6efd;
            color: white;
            padding: 2px 8px;
            border-radius: 20px;
        }
        
        .badge-live {
            font-size: 0.6rem;
            background: #28a745;
            color: white;
            padding: 2px 8px;
            border-radius: 20px;
            animation: pulse 2s infinite;
        }
        
        .badge-sandbox {
            font-size: 0.6rem;
            background: #ffc107;
            color: #212529;
            padding: 2px 8px;
            border-radius: 20px;
        }
        
        .badge-simulation {
            font-size: 0.6rem;
            background: #17a2b8;
            color: white;
            padding: 2px 8px;
            border-radius: 20px;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.6; }
            100% { opacity: 1; }
        }
        
        .form-control {
            border-radius: 10px;
            padding: 12px 15px;
            border: 1px solid #e0e0e0;
            transition: all 0.3s;
        }
        
        .form-control:focus {
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
            cursor: pointer;
            position: relative;
        }
        
        .btn-pay:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(25,135,84,0.3);
        }
        
        .btn-pay:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none !important;
        }
        
        .btn-pay .spinner {
            display: none;
            width: 20px;
            height: 20px;
            border: 2px solid #ffffff;
            border-top-color: transparent;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
            margin: 0 auto;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        .btn-pay.loading .spinner {
            display: inline-block;
        }
        
        .btn-pay.loading .btn-text {
            display: none;
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
        
        .bank-details-box {
            background: #f0f7ff;
            border-radius: 10px;
            padding: 15px;
            border: 1px dashed #0d6efd;
        }
        
        .payment-fields {
            display: none;
        }
        
        .payment-fields.active {
            display: block;
            animation: fadeIn 0.3s ease;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .payment-processing {
            display: none;
            text-align: center;
            padding: 20px;
        }
        
        .payment-processing.show {
            display: block;
        }
        
        .payment-processing .loader {
            width: 60px;
            height: 60px;
            border: 4px solid #e9ecef;
            border-top-color: #0d6efd;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto 15px;
        }
        
        .security-badge {
            display: inline-block;
            background: #d4edda;
            color: #155724;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.8rem;
        }
        
        .notification-message {
            background: #e8f5e9;
            border-left: 4px solid #4CAF50;
            padding: 15px;
            border-radius: 8px;
            margin-top: 15px;
        }
        
        .mpesa-test-info {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 12px 15px;
            border-radius: 8px;
            margin-top: 10px;
        }
        
        .mpesa-test-info code {
            background: #f8f9fa;
            padding: 2px 6px;
            border-radius: 4px;
            font-weight: bold;
        }
        
        .simulation-banner {
            background: #d1ecf1;
            border-left: 4px solid #17a2b8;
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .simulation-banner i {
            color: #17a2b8;
            font-size: 1.2rem;
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
        
        .config-status {
            font-size: 0.8rem;
            padding: 4px 12px;
            border-radius: 20px;
        }
        
        .config-status.configured {
            background: #d4edda;
            color: #155724;
        }
        
        .config-status.not-configured {
            background: #f8d7da;
            color: #721c24;
        }
        
        .config-status.simulation {
            background: #d1ecf1;
            color: #0c5460;
        }
        
        @media (max-width: 768px) {
            .payment-card {
                padding: 20px;
            }
        }
    </style>
</head>
<body>

<!-- Payment Header -->
<div class="payment-header">
    <div class="container">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <a href="../orders/my-orders.php" class="back-link">
                    <i class="fas fa-arrow-left me-2"></i>Back to Orders
                </a>
                <h1 class="display-5 fw-bold mt-2"><i class="fas fa-credit-card me-3"></i>Complete Payment</h1>
                <p class="mb-0 opacity-75">Secure payment for your order</p>
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
            <a href="../orders/my-orders.php" class="btn btn-primary btn-sm mt-2">Back to Orders</a>
        </div>
    <?php elseif($order): ?>
    
    <!-- Simulation Mode Banner -->
    <?php if($is_simulation): ?>
    <div class="simulation-banner">
        <div class="d-flex align-items-center">
            <i class="fas fa-flask me-3 fa-2x"></i>
            <div>
                <h5 class="mb-0 text-info">🧪 Simulation Mode Active</h5>
                <p class="mb-0 text-muted">
                    This is a test environment for learning purposes. No real money will be charged.
                    <span class="badge bg-info ms-2">Learning Mode</span>
                </p>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- M-Pesa Configuration Status -->
    <div class="alert <?php echo $is_simulation ? 'alert-info' : ($is_mpesa_configured ? 'alert-success' : 'alert-warning'); ?>">
        <i class="fas <?php echo $is_simulation ? 'fa-flask' : ($is_mpesa_configured ? 'fa-check-circle' : 'fa-exclamation-triangle'); ?> me-2"></i>
        <strong>M-Pesa Status:</strong> 
        <?php if($is_simulation): ?>
            🔬 Simulation Mode - No credentials required
        <?php elseif($is_mpesa_configured): ?>
            Configured and ready. Environment: <strong><?php echo ucfirst($mpesa_config['environment']); ?></strong>
        <?php else: ?>
            Not configured. Please set your M-Pesa credentials in the .env file or functions.php.
        <?php endif; ?>
        <span class="float-end">
            <span class="config-status <?php echo $is_simulation ? 'simulation' : ($is_mpesa_configured ? 'configured' : 'not-configured'); ?>">
                <?php if($is_simulation): ?>
                    🧪 Simulation
                <?php elseif($is_mpesa_configured): ?>
                    ✅ Live
                <?php else: ?>
                    ⚠️ Setup Required
                <?php endif; ?>
            </span>
        </span>
    </div>
    
    <?php if($pending_payment): ?>
        <div class="alert alert-info">
            <i class="fas fa-clock me-2"></i>
            <strong>Payment Pending:</strong> You have a pending M-Pesa payment. 
            <a href="payment-pending.php?order=<?php echo $order_id; ?>" class="alert-link">Check status</a>
        </div>
    <?php endif; ?>
    
    <div class="row">
        <div class="col-lg-8">
            <!-- Payment Processing Overlay -->
            <div id="paymentProcessing" class="payment-processing">
                <div class="loader"></div>
                <h4>Processing Payment...</h4>
                <p class="text-muted">Please wait while we securely process your payment.</p>
                <div class="progress">
                    <div class="progress-bar progress-bar-striped progress-bar-animated" 
                         style="width: 0%" id="progressBar"></div>
                </div>
                <p class="small text-muted mt-2" id="statusText">Initializing payment...</p>
            </div>
            
            <form method="POST" action="" id="paymentForm" novalidate>
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                
                <?php if($payment_error): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <i class="fas fa-exclamation-triangle me-2"></i> <?php echo $payment_error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <!-- Payment Methods -->
                <div class="payment-card" id="paymentMethodsCard">
                    <div class="card-title">
                        <i class="fas fa-wallet"></i> Select Payment Method
                        <span class="security-badge float-end">
                            <i class="fas fa-lock me-1"></i> Secured
                        </span>
                    </div>
                    
                    <?php foreach($payment_methods as $key => $method): ?>
                    <div class="payment-method-item <?php echo isset($_POST['payment_method']) && $_POST['payment_method'] == $key ? 'active' : ''; ?>" 
                         onclick="selectPayment('<?php echo $key; ?>')">
                        <div class="d-flex align-items-center gap-3">
                            <div class="method-icon" style="background: <?php echo $method['color']; ?>20; color: <?php echo $method['color']; ?>;">
                                <i class="<?php echo $method['icon']; ?>"></i>
                            </div>
                            <div class="flex-grow-1">
                                <h6 class="mb-0">
                                    <?php echo $method['name']; ?>
                                    <?php if($is_simulation && $key == 'mpesa'): ?>
                                        <span class="badge-simulation"> Simulation</span>
                                    <?php elseif(isset($method['real_api']) && $method['real_api']): ?>
                                        <span class="badge-live">Live API</span>
                                        <span class="badge-sandbox">Sandbox</span>
                                    <?php else: ?>
                                        <span class="badge-gateway">Demo</span>
                                    <?php endif; ?>
                                </h6>
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
                
                <!-- Payment Fields -->
                <div class="payment-card" id="paymentFieldsContainer">
                    <div class="card-title">
                        <i class="fas fa-info-circle"></i> Payment Details
                    </div>
                    
                    <!-- M-Pesa Fields -->
                    <div class="payment-fields <?php echo (!isset($_POST['payment_method'])) ? 'active' : (isset($_POST['payment_method']) && $_POST['payment_method'] == 'mpesa' ? 'active' : ''); ?>" id="fields-mpesa">
                        <div class="mb-3">
                            <label class="form-label required">M-Pesa Phone Number</label>
                            <input type="tel" name="phone_number" class="form-control" 
                                   placeholder="0712 345 678 or +255 712 345 678" 
                                   value="<?php echo htmlspecialchars($_POST['phone_number'] ?? $test_phone); ?>"
                                   pattern="[0-9+\s\-\(\)]{10,20}"
                                   title="Enter a valid phone number (e.g., 0712345678 or +255712345678)">
                            <small class="text-muted">
                                <i class="fas fa-info-circle me-1"></i>
                                <?php if($is_simulation): ?>
                                    🧪 Simulation: Enter any phone number for testing
                                <?php else: ?>
                                    You will receive a payment prompt on your M-Pesa registered phone.
                                <?php endif; ?>
                            </small>
                        </div>
                        
                        <?php if($is_simulation): ?>
                        <div class="notification-message" style="background: #d1ecf1; border-left-color: #17a2b8;">
                            <i class="fas fa-flask text-info me-2"></i>
                            <strong>🧪 Simulation Mode:</strong> 
                            No real STK Push will be sent. The payment will be simulated for learning purposes.
                            <br>
                            <small>Test PIN: <code>12345</code> | Test Phone: <code><?php echo $test_phone; ?></code></small>
                        </div>
                        <?php else: ?>
                        <div class="notification-message">
                            <i class="fas fa-check-circle text-success me-2"></i>
                            <strong>M-Pesa Live Integration:</strong> This sends a real STK Push to your phone. 
                            Enter your M-Pesa PIN when prompted.
                        </div>
                        <?php endif; ?>
                        
                        <div class="mpesa-test-info">
                            <i class="fas fa-info-circle me-2"></i>
                            <?php if($is_simulation): ?>
                                <strong>🧪 Simulation:</strong> 
                                Default test phone: <code><?php echo $test_phone; ?></code>
                                <br>
                                <small>No real money will be charged. This is for learning purposes only.</small>
                            <?php else: ?>
                                <strong>Sandbox Mode:</strong> 
                                Default test phone: <code><?php echo $test_phone; ?></code> | 
                                Test PIN: <code>12345</code>
                                <br>
                                <small>No real money will be charged in sandbox mode.</small>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Airtel Money Fields -->
                    <div class="payment-fields <?php echo (isset($_POST['payment_method']) && $_POST['payment_method'] == 'airtel_money') ? 'active' : ''; ?>" id="fields-airtel_money">
                        <div class="mb-3">
                            <label class="form-label required">Phone Number</label>
                            <input type="tel" name="phone_number" class="form-control" 
                                   placeholder="0712 345 678 or +255 712 345 678" 
                                   value="<?php echo htmlspecialchars($_POST['phone_number'] ?? ''); ?>"
                                   pattern="[0-9+\s\-\(\)]{10,20}">
                            <small class="text-muted">You will receive a payment prompt on your phone</small>
                        </div>
                    </div>
                    
                    <!-- Tigo Pesa Fields -->
                    <div class="payment-fields <?php echo (isset($_POST['payment_method']) && $_POST['payment_method'] == 'tigo_pesa') ? 'active' : ''; ?>" id="fields-tigo_pesa">
                        <div class="mb-3">
                            <label class="form-label required">Phone Number</label>
                            <input type="tel" name="phone_number" class="form-control" 
                                   placeholder="0712 345 678 or +255 712 345 678" 
                                   value="<?php echo htmlspecialchars($_POST['phone_number'] ?? ''); ?>"
                                   pattern="[0-9+\s\-\(\)]{10,20}">
                            <small class="text-muted">You will receive a payment prompt on your phone</small>
                        </div>
                    </div>
                    
                    <!-- Halopesa Fields -->
                    <div class="payment-fields <?php echo (isset($_POST['payment_method']) && $_POST['payment_method'] == 'halopesa') ? 'active' : ''; ?>" id="fields-halopesa">
                        <div class="mb-3">
                            <label class="form-label required">Phone Number</label>
                            <input type="tel" name="phone_number" class="form-control" 
                                   placeholder="0712 345 678 or +255 712 345 678" 
                                   value="<?php echo htmlspecialchars($_POST['phone_number'] ?? ''); ?>"
                                   pattern="[0-9+\s\-\(\)]{10,20}">
                            <small class="text-muted">You will receive a payment prompt on your phone</small>
                        </div>
                    </div>
                    
                    <!-- Bank Transfer Fields -->
                    <div class="payment-fields <?php echo (isset($_POST['payment_method']) && $_POST['payment_method'] == 'bank_transfer') ? 'active' : ''; ?>" id="fields-bank_transfer">
                        <div class="mb-3">
                            <label class="form-label required">Bank Name</label>
                            <input type="text" name="bank_name" class="form-control" placeholder="e.g., CRDB Bank" value="<?php echo htmlspecialchars($_POST['bank_name'] ?? ''); ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label required">Account Number</label>
                            <input type="text" name="account_number" class="form-control" placeholder="Enter your account number" value="<?php echo htmlspecialchars($_POST['account_number'] ?? ''); ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label required">Account Holder Name</label>
                            <input type="text" name="account_name" class="form-control" placeholder="Enter the account holder name" value="<?php echo htmlspecialchars($_POST['account_name'] ?? ''); ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label required">Payment Reference</label>
                            <input type="text" name="payment_reference" class="form-control" placeholder="Use your order number as reference" value="<?php echo htmlspecialchars($_POST['payment_reference'] ?? $order['order_number']); ?>">
                            <small class="text-muted">Use your order number for faster processing</small>
                        </div>
                        <div class="bank-details-box mt-3">
                            <h6><i class="fas fa-university me-2"></i>Transfer to:</h6>
                            <p class="small mb-1"><strong>Bank:</strong> <?php echo $bank_details['bank_name']; ?></p>
                            <p class="small mb-1"><strong>Account Number:</strong> <?php echo $bank_details['account_number']; ?></p>
                            <p class="small mb-1"><strong>Account Name:</strong> <?php echo $bank_details['account_name']; ?></p>
                            <p class="small mb-0"><strong>SWIFT Code:</strong> <?php echo $bank_details['swift_code']; ?></p>
                            <small class="text-muted d-block mt-2">Use your order number as reference when making the transfer.</small>
                        </div>
                    </div>
                    
                    <!-- Card Fields -->
                    <div class="payment-fields <?php echo (isset($_POST['payment_method']) && $_POST['payment_method'] == 'card') ? 'active' : ''; ?>" id="fields-card">
                        <div class="mb-3">
                            <label class="form-label required">Card Number</label>
                            <input type="text" name="card_number" class="form-control" placeholder="1234 5678 9012 3456" value="<?php echo htmlspecialchars($_POST['card_number'] ?? ''); ?>" maxlength="19">
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label required">Expiry Date</label>
                                <input type="text" name="card_expiry" class="form-control" placeholder="MM/YY" value="<?php echo htmlspecialchars($_POST['card_expiry'] ?? ''); ?>" maxlength="5">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label required">CVV</label>
                                <input type="password" name="card_cvv" class="form-control" placeholder="123" maxlength="4" value="<?php echo htmlspecialchars($_POST['card_cvv'] ?? ''); ?>">
                            </div>
                        </div>
                        <div class="alert alert-info">
                            <i class="fas fa-shield-alt me-2"></i>
                            <small>Your card details are encrypted and secure. We never store your full card information.</small>
                        </div>
                    </div>
                </div>
                
                <!-- Confirm Payment -->
                <div class="payment-card">
                    <div class="card-title">
                        <i class="fas fa-lock"></i> Confirm Payment
                    </div>
                    
                    <div class="form-check mb-3">
                        <input type="checkbox" class="form-check-input" id="terms" required>
                        <label class="form-check-label" for="terms">
                            I confirm that the payment information is correct and I authorize this transaction.
                        </label>
                    </div>
                    
                    <div class="alert alert-info">
                        <i class="fas fa-shield-alt me-2"></i>
                        <small>Your payment is protected by escrow. Funds will only be released to the supplier after you confirm delivery.</small>
                    </div>
                    
                    <button type="submit" class="btn-pay" id="payBtn">
                        <span class="btn-text">
                            <i class="fas fa-lock me-2"></i> Pay <?php echo formatPrice($order['total_amount']); ?>
                        </span>
                        <span class="spinner"></span>
                    </button>
                    
                    <?php if($is_simulation): ?>
                    <div class="alert alert-info mt-2">
                        <i class="fas fa-flask me-2"></i>
                        <strong>🧪 Simulation Mode:</strong> This payment will be simulated. No real money will be charged.
                    </div>
                    <?php endif; ?>
                    
                    <div class="d-flex justify-content-between mt-3">
                        <small class="text-muted">
                            <i class="fas fa-clock me-1"></i> Secure processing takes 10-30 seconds
                        </small>
                        <small class="text-muted">
                            <i class="fas fa-check-circle me-1 text-success"></i> 256-bit encryption
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
                        <span><?php echo date('M d, Y', strtotime($order['created_at'])); ?></span>
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
                
                <?php if($is_simulation): ?>
                <hr>
                <div class="text-center">
                    <small class="text-info">
                        <i class="fas fa-flask me-1"></i>
                        🧪 Simulation Mode Active
                    </small>
                </div>
                <?php endif; ?>
                
                <hr>
                <div class="text-center">
                    <small class="text-muted">
                        <i class="fas fa-check-circle text-success me-1"></i>
                        Secured by SSL/TLS Encryption
                    </small>
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
    // =============================================
    // SELECT PAYMENT METHOD
    // =============================================
    function selectPayment(method) {
        const radio = document.querySelector(`input[name="payment_method"][value="${method}"]`);
        if (radio) radio.checked = true;
        
        document.querySelectorAll('.payment-method-item').forEach(el => {
            el.classList.remove('active');
        });
        const parent = radio?.closest('.payment-method-item');
        if (parent) parent.classList.add('active');
        
        document.querySelectorAll('.payment-fields').forEach(el => {
            el.classList.remove('active');
        });
        const fields = document.getElementById(`fields-${method}`);
        if (fields) fields.classList.add('active');
        
        const alertBox = document.querySelector('.alert-danger');
        if (alertBox) alertBox.style.display = 'none';
    }
    
    // =============================================
    // AUTO-SELECT PAYMENT METHOD ON LOAD
    // =============================================
    document.addEventListener('DOMContentLoaded', function() {
        const checkedRadio = document.querySelector('input[name="payment_method"]:checked');
        if (checkedRadio) {
            selectPayment(checkedRadio.value);
        } else {
            const firstRadio = document.querySelector('input[name="payment_method"]');
            if (firstRadio) {
                firstRadio.checked = true;
                selectPayment(firstRadio.value);
            }
        }
    });
    
    // =============================================
    // CARD INPUT FORMATTING
    // =============================================
    document.querySelector('input[name="card_number"]')?.addEventListener('input', function() {
        let value = this.value.replace(/\D/g, '');
        if (value.length > 16) value = value.slice(0, 16);
        let formatted = '';
        for (let i = 0; i < value.length; i++) {
            if (i > 0 && i % 4 === 0) formatted += ' ';
            formatted += value[i];
        }
        this.value = formatted;
    });
    
    document.querySelector('input[name="card_expiry"]')?.addEventListener('input', function() {
        let value = this.value.replace(/\D/g, '');
        if (value.length > 4) value = value.slice(0, 4);
        if (value.length >= 2) {
            this.value = value.slice(0, 2) + '/' + value.slice(2);
        } else {
            this.value = value;
        }
    });
    
    document.querySelector('input[name="card_cvv"]')?.addEventListener('input', function() {
        let value = this.value.replace(/\D/g, '');
        if (value.length > 4) value = value.slice(0, 4);
        this.value = value;
    });
    
    // =============================================
    // PHONE NUMBER FORMATTING
    // =============================================
    document.querySelectorAll('input[name="phone_number"]').forEach(function(input) {
        input.addEventListener('input', function() {
            this.value = this.value.replace(/[^0-9+\s\-]/g, '');
        });
    });
    
    // =============================================
    // FORM SUBMISSION WITH PROCESSING ANIMATION
    // =============================================
    document.getElementById('paymentForm')?.addEventListener('submit', function(e) {
        const termsCheckbox = document.getElementById('terms');
        if (!termsCheckbox.checked) {
            e.preventDefault();
            Swal.fire({
                icon: 'warning',
                title: 'Please Confirm',
                text: 'Please confirm that you authorize this transaction by checking the box.',
                confirmButtonColor: '#0d6efd'
            });
            return false;
        }
        
        const paymentMethod = document.querySelector('input[name="payment_method"]:checked');
        if (!paymentMethod) {
            e.preventDefault();
            Swal.fire({
                icon: 'warning',
                title: 'Payment Method Required',
                text: 'Please select a payment method to continue.',
                confirmButtonColor: '#0d6efd'
            });
            return false;
        }
        
        // Show payment processing overlay
        e.preventDefault();
        
        const payBtn = document.getElementById('payBtn');
        const paymentProcessing = document.getElementById('paymentProcessing');
        const paymentMethodsCard = document.getElementById('paymentMethodsCard');
        const progressBar = document.getElementById('progressBar');
        const statusText = document.getElementById('statusText');
        
        paymentMethodsCard.style.opacity = '0.5';
        paymentMethodsCard.style.pointerEvents = 'none';
        paymentProcessing.classList.add('show');
        
        payBtn.disabled = true;
        payBtn.classList.add('loading');
        
        let progress = 0;
        const statusMessages = [
            'Initializing payment gateway...',
            'Validating payment details...',
            'Processing transaction...',
            'Authorizing payment...',
            'Completing transaction...'
        ];
        let messageIndex = 0;
        
        const interval = setInterval(() => {
            progress += Math.random() * 15 + 5;
            if (progress > 100) progress = 100;
            
            progressBar.style.width = progress + '%';
            
            if (progress > 20 && messageIndex < statusMessages.length - 1) {
                messageIndex++;
                statusText.textContent = statusMessages[messageIndex];
            }
            
            if (progress >= 100) {
                clearInterval(interval);
                document.getElementById('paymentForm').submit();
            }
        }, 300);
        
        return false;
    });
</script>

</body>
</html>