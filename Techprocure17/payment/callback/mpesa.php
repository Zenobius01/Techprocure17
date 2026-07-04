<?php
/**
 * TechProcure Tanzania - M-Pesa Daraja API Callback Handler
 * File: payment/callback/mpesa.php
 * Description: Handles M-Pesa STK Push callback responses
 */

// Start session if not started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include configuration files
require_once '../../includes/config.php';
require_once '../../includes/db.php';
require_once '../../includes/functions.php';

// Log the callback for debugging
function logMpesaCallback($data) {
    $logFile = __DIR__ . '/mpesa_callback_log.txt';
    $logEntry = date('Y-m-d H:i:s') . ' - ' . json_encode($data) . PHP_EOL;
    file_put_contents($logFile, $logEntry, FILE_APPEND);
}

// Get the callback data
$callbackData = file_get_contents('php://input');
logMpesaCallback(['raw_data' => $callbackData, 'method' => $_SERVER['REQUEST_METHOD']]);

// Decode JSON data
$data = json_decode($callbackData, true);

// Also check for POST data
if (empty($data) && !empty($_POST)) {
    $data = $_POST;
    logMpesaCallback(['post_data' => $_POST]);
}

// Check if we have valid data
if (empty($data)) {
    logMpesaCallback(['error' => 'No data received']);
    http_response_code(400);
    echo json_encode(['ResultCode' => 1, 'ResultDesc' => 'No data received']);
    exit();
}

// Extract the relevant data
$resultCode = null;
$resultDesc = null;
$checkoutRequestID = null;
$transactionID = null;
$amount = null;
$phoneNumber = null;

// Handle different callback formats
if (isset($data['Body']['stkCallback'])) {
    // Standard M-Pesa callback format
    $stkCallback = $data['Body']['stkCallback'];
    $resultCode = $stkCallback['ResultCode'] ?? null;
    $resultDesc = $stkCallback['ResultDesc'] ?? null;
    $checkoutRequestID = $stkCallback['CheckoutRequestID'] ?? null;
    
    if (isset($stkCallback['CallbackMetadata']['Item'])) {
        foreach ($stkCallback['CallbackMetadata']['Item'] as $item) {
            switch ($item['Name']) {
                case 'Amount':
                    $amount = $item['Value'] ?? null;
                    break;
                case 'MpesaReceiptNumber':
                    $transactionID = $item['Value'] ?? null;
                    break;
                case 'PhoneNumber':
                    $phoneNumber = $item['Value'] ?? null;
                    break;
                case 'TransactionDate':
                    $transactionDate = $item['Value'] ?? null;
                    break;
            }
        }
    }
} elseif (isset($data['ResultCode'])) {
    // Alternative format
    $resultCode = $data['ResultCode'] ?? null;
    $resultDesc = $data['ResultDesc'] ?? null;
    $checkoutRequestID = $data['CheckoutRequestID'] ?? null;
    $transactionID = $data['TransactionID'] ?? $data['MpesaReceiptNumber'] ?? null;
    $amount = $data['Amount'] ?? null;
    $phoneNumber = $data['PhoneNumber'] ?? null;
} else {
    // Unknown format
    logMpesaCallback(['error' => 'Unknown callback format', 'data' => $data]);
    http_response_code(400);
    echo json_encode(['ResultCode' => 1, 'ResultDesc' => 'Unknown format']);
    exit();
}

logMpesaCallback([
    'resultCode' => $resultCode,
    'resultDesc' => $resultDesc,
    'checkoutRequestID' => $checkoutRequestID,
    'transactionID' => $transactionID,
    'amount' => $amount,
    'phoneNumber' => $phoneNumber
]);

// Process the callback
$response = ['ResultCode' => 0, 'ResultDesc' => 'Success'];

if ($resultCode !== null && $checkoutRequestID) {
    try {
        $db = getDB();
        
        // Start transaction
        $db->beginTransaction();
        
        // Find the payment with this checkout request ID
        $sql = "SELECT id, order_id, user_id, payment_status FROM payments WHERE checkout_request_id = ? ORDER BY id DESC LIMIT 1";
        $stmt = $db->prepare($sql);
        $stmt->execute([$checkoutRequestID]);
        $payment = $stmt->fetch();
        
        if ($payment) {
            // Determine payment status based on result code
            if ($resultCode == 0) {
                // Payment successful
                $paymentStatus = 'completed';
                $orderStatus = 'paid';
                
                // Update payment record
                $updateSql = "UPDATE payments SET 
                                payment_status = ?,
                                transaction_id = ?,
                                gateway_response = ?,
                                processed_at = NOW()
                                WHERE id = ?";
                $updateStmt = $db->prepare($updateSql);
                $updateStmt->execute([
                    $paymentStatus,
                    $transactionID,
                    json_encode(['ResultCode' => $resultCode, 'ResultDesc' => $resultDesc, 'full_data' => $data]),
                    $payment['id']
                ]);
                
                // Update order
                $orderSql = "UPDATE orders SET 
                                payment_status = ?,
                                transaction_id = ?,
                                payment_data = ?,
                                updated_at = NOW()
                                WHERE id = ?";
                $orderStmt = $db->prepare($orderSql);
                $orderStmt->execute([
                    $orderStatus,
                    $transactionID,
                    json_encode(['transaction_id' => $transactionID, 'amount' => $amount, 'phone' => $phoneNumber]),
                    $payment['order_id']
                ]);
                
                // Update escrow
                try {
                    $escrowSql = "UPDATE escrow_payments SET 
                                    status = 'pending',
                                    transaction_id = ?,
                                    paid_at = NOW()
                                    WHERE order_id = ?";
                    $escrowStmt = $db->prepare($escrowSql);
                    $escrowStmt->execute([$transactionID, $payment['order_id']]);
                } catch (Exception $e) {
                    // Escrow table might not exist
                    logMpesaCallback(['escrow_error' => $e->getMessage()]);
                }
                
                // Add order tracking entry
                try {
                    $trackSql = "INSERT INTO order_tracking 
                                    (order_id, status, description, created_at) 
                                    VALUES (?, 'confirmed', ?, NOW())";
                    $trackStmt = $db->prepare($trackSql);
                    $trackStmt->execute([
                        $payment['order_id'],
                        'M-Pesa payment confirmed. Transaction ID: ' . ($transactionID ?? 'N/A')
                    ]);
                } catch (Exception $e) {
                    logMpesaCallback(['tracking_error' => $e->getMessage()]);
                }
                
                // Add notification for user
                try {
                    addNotification(
                        $payment['user_id'],
                        'payment',
                        'Payment Successful',
                        'Your M-Pesa payment of ' . formatPrice($amount ?? 0) . ' for order has been confirmed. Transaction ID: ' . ($transactionID ?? 'N/A'),
                        '../customer/orders/order-details.php?id=' . $payment['order_id']
                    );
                } catch (Exception $e) {
                    logMpesaCallback(['notification_error' => $e->getMessage()]);
                }
                
                logMpesaCallback(['action' => 'Payment completed', 'payment_id' => $payment['id'], 'order_id' => $payment['order_id']]);
                
            } else {
                // Payment failed
                $paymentStatus = 'failed';
                
                // Update payment record
                $updateSql = "UPDATE payments SET 
                                payment_status = ?,
                                gateway_response = ?,
                                processed_at = NOW()
                                WHERE id = ?";
                $updateStmt = $db->prepare($updateSql);
                $updateStmt->execute([
                    $paymentStatus,
                    json_encode(['ResultCode' => $resultCode, 'ResultDesc' => $resultDesc, 'full_data' => $data]),
                    $payment['id']
                ]);
                
                // Update order
                $orderSql = "UPDATE orders SET 
                                payment_status = 'failed',
                                payment_data = ?,
                                updated_at = NOW()
                                WHERE id = ?";
                $orderStmt = $db->prepare($orderSql);
                $orderStmt->execute([
                    json_encode(['error' => $resultDesc, 'code' => $resultCode]),
                    $payment['order_id']
                ]);
                
                // Add notification for user
                try {
                    addNotification(
                        $payment['user_id'],
                        'payment',
                        'Payment Failed',
                        'Your M-Pesa payment failed. Reason: ' . ($resultDesc ?? 'Unknown error'),
                        '../customer/orders/order-details.php?id=' . $payment['order_id']
                    );
                } catch (Exception $e) {
                    logMpesaCallback(['notification_error' => $e->getMessage()]);
                }
                
                logMpesaCallback(['action' => 'Payment failed', 'payment_id' => $payment['id'], 'reason' => $resultDesc]);
            }
            
            // Commit transaction
            $db->commit();
            
        } else {
            logMpesaCallback(['error' => 'Payment not found for checkout request: ' . $checkoutRequestID]);
            $response['ResultDesc'] = 'Payment not found';
            $response['ResultCode'] = 1;
        }
        
    } catch (Exception $e) {
        if (isset($db)) {
            $db->rollBack();
        }
        logMpesaCallback(['exception' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
        $response['ResultCode'] = 1;
        $response['ResultDesc'] = 'Error processing callback: ' . $e->getMessage();
    }
} else {
    logMpesaCallback(['error' => 'Invalid callback data', 'resultCode' => $resultCode, 'checkoutRequestID' => $checkoutRequestID]);
    $response['ResultCode'] = 1;
    $response['ResultDesc'] = 'Invalid callback data';
}

// Send response back to M-Pesa
header('Content-Type: application/json');
echo json_encode($response);
logMpesaCallback(['response' => $response]);

// Also redirect if it's a browser request (for testing)
if (isset($_GET['test']) || isset($_POST['test'])) {
    // Redirect to success or failure page
    if ($resultCode == 0) {
        header('Location: ../../customer/cart/payment-success.php');
    } else {
        header('Location: ../../customer/cart/payment-failed.php');
    }
    exit();
}