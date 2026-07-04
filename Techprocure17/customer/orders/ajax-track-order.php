<?php
/**
 * TechProcure Tanzania - AJAX Order Tracking
 * File: customer/orders/ajax-track-order.php
 * Description: Fetches updated tracking information
 */

// Start session
session_start();

// Include configuration
require_once '../../includes/config.php';
require_once '../../includes/db.php';
require_once '../../includes/functions.php';


// Check if user is logged in
if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

// Get order ID
$order_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($order_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid order ID']);
    exit();
}

$db = getDB();
$user_id = $_SESSION['user_id'];

try {
    // Verify order belongs to user
    $check_sql = "SELECT id FROM orders WHERE id = ? AND user_id = ?";
    $check_stmt = $db->prepare($check_sql);
    $check_stmt->execute([$order_id, $user_id]);
    
    if ($check_stmt->rowCount() == 0) {
        echo json_encode(['success' => false, 'message' => 'Order not found']);
        exit();
    }
    
    // Get latest tracking updates
    $track_sql = "SELECT * FROM order_tracking 
                  WHERE order_id = ? 
                  ORDER BY created_at DESC 
                  LIMIT 5";
    $track_stmt = $db->prepare($track_sql);
    $track_stmt->execute([$order_id]);
    $updates = $track_stmt->fetchAll();
    
    // Get order status
    $status_sql = "SELECT order_status FROM orders WHERE id = ?";
    $status_stmt = $db->prepare($status_sql);
    $status_stmt->execute([$order_id]);
    $status = $status_stmt->fetch();
    
    echo json_encode([
        'success' => true,
        'order_status' => $status['order_status'] ?? 'unknown',
        'updates' => $updates,
        'timestamp' => time()
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}