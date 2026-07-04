<?php
session_start();
require_once '../../includes/config.php';
require_once '../../includes/db.php';
require_once '../../includes/functions.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'supplier') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$product_id = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
$csrf_token = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';

if (!verifyCSRFToken($csrf_token)) {
    echo json_encode(['success' => false, 'message' => 'Invalid security token']);
    exit();
}

if ($product_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid product ID']);
    exit();
}

try {
    $db = getDB();
    
    // Check if product belongs to supplier
    $check_stmt = $db->prepare("SELECT id FROM products WHERE id = ? AND supplier_id = ?");
    $check_stmt->execute([$product_id, $_SESSION['user_id']]);
    
    if (!$check_stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Product not found or unauthorized']);
        exit();
    }
    
    // Delete product
    $stmt = $db->prepare("DELETE FROM products WHERE id = ? AND supplier_id = ?");
    $stmt->execute([$product_id, $_SESSION['user_id']]);
    
    echo json_encode(['success' => true, 'message' => 'Product deleted successfully']);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>