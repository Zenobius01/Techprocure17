<?php
/**
 * TechProcure Tanzania - AJAX Cart Handler
 * File: ajax/cart.php
 * Description: Handles all cart operations via AJAX
 */

session_start();

// Include configuration
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Set JSON response header
header('Content-Type: application/json');

// Initialize cart if not exists
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

$action = isset($_POST['action']) ? $_POST['action'] : '';
$product_id = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
$quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 1;

switch ($action) {
    case 'add':
        if ($product_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid product ID']);
            exit;
        }
        
        try {
            $db = getDB();
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
            
            echo json_encode([
                'success' => true,
                'message' => 'Product added to cart',
                'count' => $count,
                'total' => $total
            ]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Database error']);
        }
        break;
        
    case 'get_count':
        $count = 0;
        foreach ($_SESSION['cart'] as $item) {
            $count += $item['quantity'];
        }
        echo json_encode(['count' => $count]);
        break;
        
    case 'remove':
        if (isset($_SESSION['cart'][$product_id])) {
            unset($_SESSION['cart'][$product_id]);
        }
        $count = 0;
        $total = 0;
        foreach ($_SESSION['cart'] as $item) {
            $count += $item['quantity'];
            $total += $item['price'] * $item['quantity'];
        }
        echo json_encode(['success' => true, 'count' => $count, 'total' => $total]);
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}
?>