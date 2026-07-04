<?php
/**
 * TechProcure Tanzania - Complete Functions
 * File: includes/functions.php
 * Description: ALL helper functions in one file - NO REDECLARATIONS
 */

// Start session if not started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Prevent direct access
if (!defined('BASE_PATH')) {
    define('BASE_PATH', __DIR__ . '/../');
}

// =============================================
// M-PESA SIMULATION MODE - FOR LEARNING ONLY
// =============================================
// Set to true to bypass real API calls for testing
// Set to false when you have real credentials from Safaricom
define('MPESA_SIMULATION_MODE', true);

// Simulation configuration
define('MPESA_SIMULATION_PHONE', '255760211221');
define('MPESA_SIMULATION_PIN', '12345');

// =============================================
// DATABASE CONNECTION (DEFINED FIRST - Check if already exists)
// =============================================

if (!function_exists('getDB')) {
    /**
     * Get database connection
     * @return PDO Database connection
     */
    function getDB() {
        static $db = null;
        if ($db === null) {
            try {
                // Load environment variables if not already loaded
                if (!getenv('DB_HOST')) {
                    if (file_exists(__DIR__ . '/../.env')) {
                        $lines = file(__DIR__ . '/../.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                        foreach ($lines as $line) {
                            if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
                                list($key, $value) = explode('=', $line, 2);
                                $key = trim($key);
                                $value = trim($value);
                                putenv("$key=$value");
                                $_ENV[$key] = $value;
                            }
                        }
                    }
                }
                
                $host = getenv('DB_HOST') ?: 'localhost';
                $dbname = getenv('DB_NAME') ?: 'techprocure17';
                $username = getenv('DB_USER') ?: 'root';
                $password = getenv('DB_PASS') ?: '';
                
                $dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";
                $db = new PDO($dsn, $username, $password, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
                ]);
            } catch (PDOException $e) {
                error_log("Database connection failed: " . $e->getMessage());
                throw new Exception("Database connection failed. Please try again later.");
            }
        }
        return $db;
    }
}

// =============================================
// CSRF TOKEN FUNCTIONS
// =============================================

if (!function_exists('generateCSRFToken')) {
    /**
     * Generate CSRF token
     */
    function generateCSRFToken() {
        if (empty($_SESSION['csrf_token'])) {
            try {
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            } catch (Exception $e) {
                $_SESSION['csrf_token'] = md5(uniqid(mt_rand(), true));
            }
        }
        return $_SESSION['csrf_token'];
    }
}

if (!function_exists('verifyCSRFToken')) {
    /**
     * Verify CSRF token
     */
    function verifyCSRFToken($token) {
        if (!isset($_SESSION['csrf_token'])) {
            return false;
        }
        return hash_equals($_SESSION['csrf_token'], $token);
    }
}

if (!function_exists('regenerateCSRFToken')) {
    /**
     * Regenerate CSRF token
     */
    function regenerateCSRFToken() {
        try {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        } catch (Exception $e) {
            $_SESSION['csrf_token'] = md5(uniqid(mt_rand(), true));
        }
    }
}

// =============================================
// SESSION MANAGEMENT
// =============================================

if (!function_exists('setUserSession')) {
    /**
     * Set user session
     */
    function setUserSession($user) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_name'] = $user['full_name'] ?? $user['username'];
        $_SESSION['user_role'] = $user['role_name'] ?? 'customer';
        $_SESSION['user_type'] = $user['user_type'] ?? 'customer';
        $_SESSION['logged_in'] = true;
        $_SESSION['login_time'] = time();
        $_SESSION['ip_address'] = $_SERVER['REMOTE_ADDR'] ?? '';
        $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? '';
    }
}

if (!function_exists('destroySession')) {
    /**
     * Destroy session
     */
    function destroySession() {
        $_SESSION = [];
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        session_destroy();
    }
}

if (!function_exists('isLoggedIn')) {
    /**
     * Check if user is logged in
     */
    function isLoggedIn() {
        return isset($_SESSION['user_id']) && isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
    }
}

if (!function_exists('isAdmin')) {
    /**
     * Check if user is admin
     */
    function isAdmin() {
        return isset($_SESSION['user_role']) && in_array(strtolower($_SESSION['user_role']), ['admin', 'superadmin', 'administrator']);
    }
}

if (!function_exists('isSupplier')) {
    /**
     * Check if user is supplier
     */
    function isSupplier() {
        return isset($_SESSION['user_type']) && strtolower($_SESSION['user_type']) === 'supplier';
    }
}

if (!function_exists('isCustomer')) {
    /**
     * Check if user is customer
     */
    function isCustomer() {
        return isset($_SESSION['user_type']) && strtolower($_SESSION['user_type']) === 'customer';
    }
}

if (!function_exists('getCurrentUserId')) {
    /**
     * Get current user ID
     */
    function getCurrentUserId() {
        return $_SESSION['user_id'] ?? null;
    }
}

if (!function_exists('getCurrentRole')) {
    /**
     * Get current user role
     */
    function getCurrentRole() {
        return $_SESSION['user_role'] ?? 'guest';
    }
}

if (!function_exists('getCurrentUserType')) {
    /**
     * Get current user type
     */
    function getCurrentUserType() {
        return $_SESSION['user_type'] ?? 'guest';
    }
}

// =============================================
// USER DATA FUNCTIONS
// =============================================

if (!function_exists('getCurrentUser')) {
    /**
     * Get current user data
     */
    function getCurrentUser() {
        if (!isset($_SESSION['user_id'])) {
            return null;
        }
        
        try {
            $db = getDB();
            $sql = "SELECT u.*, r.role_name 
                    FROM users u 
                    LEFT JOIN roles r ON u.role_id = r.id 
                    WHERE u.id = ?";
            $stmt = $db->prepare($sql);
            $stmt->execute([$_SESSION['user_id']]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            return null;
        }
    }
}

if (!function_exists('getUserById')) {
    /**
     * Get user by ID
     */
    function getUserById($userId) {
        try {
            $db = getDB();
            $sql = "SELECT u.*, r.role_name 
                    FROM users u 
                    LEFT JOIN roles r ON u.role_id = r.id 
                    WHERE u.id = ?";
            $stmt = $db->prepare($sql);
            $stmt->execute([$userId]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            return null;
        }
    }
}

if (!function_exists('getUserByEmail')) {
    /**
     * Get user by email
     */
    function getUserByEmail($email) {
        try {
            $db = getDB();
            $sql = "SELECT u.*, r.role_name 
                    FROM users u 
                    LEFT JOIN roles r ON u.role_id = r.id 
                    WHERE u.email = ?";
            $stmt = $db->prepare($sql);
            $stmt->execute([$email]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            return null;
        }
    }
}

if (!function_exists('getUserByUsername')) {
    /**
     * Get user by username
     */
    function getUserByUsername($username) {
        try {
            $db = getDB();
            $sql = "SELECT u.*, r.role_name 
                    FROM users u 
                    LEFT JOIN roles r ON u.role_id = r.id 
                    WHERE u.username = ?";
            $stmt = $db->prepare($sql);
            $stmt->execute([$username]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            return null;
        }
    }
}

// =============================================
// REQUIRE FUNCTIONS (Middleware)
// =============================================

if (!function_exists('requireLogin')) {
    /**
     * Require login
     */
    function requireLogin() {
        if (!isLoggedIn()) {
            $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
            redirect('auth/login.php');
            exit();
        }
    }
}

if (!function_exists('requireAdmin')) {
    /**
     * Require admin
     */
    function requireAdmin() {
        requireLogin();
        if (!isAdmin()) {
            redirect('index.php?error=access_denied');
            exit();
        }
    }
}

if (!function_exists('requireSupplier')) {
    /**
     * Require supplier
     */
    function requireSupplier() {
        requireLogin();
        if (!isSupplier()) {
            redirect('index.php?error=access_denied');
            exit();
        }
    }
}

if (!function_exists('requireCustomer')) {
    /**
     * Require customer
     */
    function requireCustomer() {
        requireLogin();
        if (!isCustomer()) {
            redirect('index.php?error=access_denied');
            exit();
        }
    }
}

// =============================================
// REDIRECT FUNCTIONS
// =============================================

if (!function_exists('redirect')) {
    /**
     * Redirect
     */
    function redirect($url) {
        $baseUrl = getenv('APP_URL') ?: '/TechProcure17';
        $url = rtrim($baseUrl, '/') . '/' . ltrim($url, '/');
        header("Location: " . $url);
        exit();
    }
}

if (!function_exists('redirectBack')) {
    /**
     * Redirect back
     */
    function redirectBack() {
        if (isset($_SERVER['HTTP_REFERER'])) {
            header("Location: " . $_SERVER['HTTP_REFERER']);
        } else {
            redirect('index.php');
        }
        exit();
    }
}

if (!function_exists('redirectToDashboard')) {
    /**
     * Redirect to dashboard based on user role
     */
    function redirectToDashboard() {
        if (!isLoggedIn()) {
            redirect('auth/login.php');
            exit();
        }
        
        $userType = $_SESSION['user_type'] ?? '';
        $role = $_SESSION['user_role'] ?? '';
        
        // Check admin roles
        if (in_array(strtolower($role), ['admin', 'superadmin', 'administrator']) || strtolower($userType) === 'admin') {
            redirect('admin/dashboard.php');
        }
        // Check supplier roles
        elseif (in_array(strtolower($role), ['supplier', 'vendor', 'merchant']) || strtolower($userType) === 'supplier') {
            redirect('supplier/dashboard.php');
        }
        // Check customer roles
        elseif (in_array(strtolower($role), ['customer', 'user', 'client']) || strtolower($userType) === 'customer') {
            redirect('customer/dashboard.php');
        }
        // Fallback
        else {
            redirect('index.php');
        }
        exit();
    }
}

// =============================================
// SECURITY FUNCTIONS
// =============================================

if (!function_exists('sanitizeInput')) {
    /**
     * Sanitize input
     */
    function sanitizeInput($data) {
        if (is_array($data)) {
            return array_map('sanitizeInput', $data);
        }
        $data = trim($data);
        $data = stripslashes($data);
        $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
        return $data;
    }
}

if (!function_exists('sanitize')) {
    /**
     * Alias for sanitizeInput
     */
    function sanitize($data) {
        return sanitizeInput($data);
    }
}

if (!function_exists('hashPassword')) {
    /**
     * Hash password
     */
    function hashPassword($password) {
        return password_hash($password, PASSWORD_DEFAULT, ['cost' => 12]);
    }
}

if (!function_exists('verifyPassword')) {
    /**
     * Verify password
     */
    function verifyPassword($password, $hash) {
        return password_verify($password, $hash);
    }
}

if (!function_exists('generateToken')) {
    /**
     * Generate secure token
     */
    function generateToken($length = 64) {
        try {
            return bin2hex(random_bytes($length / 2));
        } catch (Exception $e) {
            return md5(uniqid(mt_rand(), true) . microtime(true));
        }
    }
}

if (!function_exists('validateEmail')) {
    /**
     * Validate email
     */
    function validateEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
}

if (!function_exists('validatePhone')) {
    /**
     * Validate phone number (Tanzanian format)
     */
    function validatePhone($phone) {
        $phone = preg_replace('/[^0-9]/', '', $phone);
        return preg_match('/^(?:(?:255|0)[67][0-9]{8}|[67][0-9]{8})$/', $phone) === 1;
    }
}

if (!function_exists('hasRole')) {
    /**
     * Check if user has specific role
     */
    function hasRole($roles) {
        if (!isLoggedIn()) {
            return false;
        }
        
        if (!is_array($roles)) {
            $roles = [$roles];
        }
        
        $userRole = $_SESSION['user_role'] ?? '';
        $userType = $_SESSION['user_type'] ?? '';
        
        foreach ($roles as $role) {
            if (strtolower($userRole) === strtolower($role) || 
                strtolower($userType) === strtolower($role)) {
                return true;
            }
        }
        return false;
    }
}

// =============================================
// FORMATTING FUNCTIONS
// =============================================

if (!function_exists('formatPrice')) {
    /**
     * Format price
     */
    function formatPrice($price) {
        if (empty($price) && $price !== 0) {
            return 'TSh 0.00';
        }
        return 'TSh ' . number_format((float)$price, 2);
    }
}

if (!function_exists('formatCurrency')) {
    /**
     * Format currency (alias)
     */
    function formatCurrency($amount, $showCurrency = true) {
        $formatted = number_format($amount, 0, '.', ',');
        return $showCurrency ? 'TSh ' . $formatted : $formatted;
    }
}

if (!function_exists('formatDate')) {
    /**
     * Format date
     */
    function formatDate($date, $format = 'M d, Y') {
        if (empty($date)) {
            return '-';
        }
        try {
            $datetime = new DateTime($date);
            return $datetime->format($format);
        } catch (Exception $e) {
            return $date;
        }
    }
}

if (!function_exists('formatDateTime')) {
    /**
     * Format date time
     */
    function formatDateTime($date) {
        if (empty($date)) {
            return '-';
        }
        return date('M d, Y H:i', strtotime($date));
    }
}

if (!function_exists('timeAgo')) {
    /**
     * Format time ago
     */
    function timeAgo($date) {
        if (empty($date)) {
            return 'Never';
        }
        try {
            $datetime = new DateTime($date);
            $now = new DateTime();
            $diff = $now->diff($datetime);
            
            if ($diff->y > 0) {
                return $diff->y . ' year' . ($diff->y > 1 ? 's' : '') . ' ago';
            }
            if ($diff->m > 0) {
                return $diff->m . ' month' . ($diff->m > 1 ? 's' : '') . ' ago';
            }
            if ($diff->d > 0) {
                return $diff->d . ' day' . ($diff->d > 1 ? 's' : '') . ' ago';
            }
            if ($diff->h > 0) {
                return $diff->h . ' hour' . ($diff->h > 1 ? 's' : '') . ' ago';
            }
            if ($diff->i > 0) {
                return $diff->i . ' minute' . ($diff->i > 1 ? 's' : '') . ' ago';
            }
            return 'Just now';
        } catch (Exception $e) {
            return $date;
        }
    }
}

if (!function_exists('getStatusBadge')) {
    /**
     * Format status badge
     */
    function getStatusBadge($status) {
        $colors = [
            'pending' => 'warning',
            'approved' => 'success',
            'rejected' => 'danger',
            'active' => 'success',
            'inactive' => 'secondary',
            'paid' => 'success',
            'unpaid' => 'danger',
            'shipped' => 'info',
            'delivered' => 'primary',
            'completed' => 'success',
            'cancelled' => 'danger',
            'processing' => 'info',
            'confirmed' => 'primary',
            'suspended' => 'danger'
        ];
        return $colors[$status] ?? 'secondary';
    }
}

if (!function_exists('getStatusLabel')) {
    /**
     * Get status label
     */
    function getStatusLabel($status) {
        $labels = [
            'pending' => 'Pending',
            'approved' => 'Approved',
            'rejected' => 'Rejected',
            'active' => 'Active',
            'inactive' => 'Inactive',
            'paid' => 'Paid',
            'unpaid' => 'Unpaid',
            'shipped' => 'Shipped',
            'delivered' => 'Delivered',
            'completed' => 'Completed',
            'cancelled' => 'Cancelled',
            'processing' => 'Processing',
            'confirmed' => 'Confirmed',
            'suspended' => 'Suspended'
        ];
        return $labels[$status] ?? ucfirst($status);
    }
}

if (!function_exists('truncateText')) {
    /**
     * Truncate text
     */
    function truncateText($text, $length = 100, $suffix = '...') {
        if (empty($text)) return '';
        if (strlen($text) <= $length) return $text;
        return substr($text, 0, $length) . $suffix;
    }
}

if (!function_exists('getStarRating')) {
    /**
     * Get star rating HTML
     */
    function getStarRating($rating) {
        $html = '';
        $fullStars = floor($rating);
        $halfStar = $rating - $fullStars >= 0.5;
        
        for ($i = 1; $i <= 5; $i++) {
            if ($i <= $fullStars) {
                $html .= '<i class="fas fa-star text-warning"></i>';
            } elseif ($i == $fullStars + 1 && $halfStar) {
                $html .= '<i class="fas fa-star-half-alt text-warning"></i>';
            } else {
                $html .= '<i class="far fa-star text-secondary"></i>';
            }
        }
        return $html;
    }
}

if (!function_exists('slugify')) {
    /**
     * Generate slug
     */
    function slugify($string) {
        $string = iconv('UTF-8', 'ASCII//TRANSLIT', $string);
        $string = preg_replace('/[^a-zA-Z0-9\s-]/', '', $string);
        $string = strtolower(trim($string));
        $string = preg_replace('/[\s-]+/', '-', $string);
        return $string;
    }
}

if (!function_exists('randomString')) {
    /**
     * Generate random string
     */
    function randomString($length = 32, $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ') {
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }
        return $randomString;
    }
}

// =============================================
// CART FUNCTIONS
// =============================================

if (!function_exists('getCartCount')) {
    /**
     * Get cart count
     */
    function getCartCount() {
        if (!isset($_SESSION['cart'])) {
            return 0;
        }
        $count = 0;
        foreach ($_SESSION['cart'] as $item) {
            $count += $item['quantity'] ?? 1;
        }
        return $count;
    }
}

if (!function_exists('getCartTotal')) {
    /**
     * Get cart total
     */
    function getCartTotal() {
        if (!isset($_SESSION['cart'])) {
            return 0;
        }
        $total = 0;
        foreach ($_SESSION['cart'] as $item) {
            $total += ($item['price'] ?? 0) * ($item['quantity'] ?? 0);
        }
        return $total;
    }
}

if (!function_exists('addToCart')) {
    /**
     * Add to cart
     */
    function addToCart($productId, $quantity = 1) {
        if (!isset($_SESSION['cart'])) {
            $_SESSION['cart'] = [];
        }
        if (isset($_SESSION['cart'][$productId])) {
            $_SESSION['cart'][$productId]['quantity'] += $quantity;
        } else {
            $_SESSION['cart'][$productId] = ['id' => $productId, 'quantity' => $quantity];
        }
        return true;
    }
}

if (!function_exists('removeFromCart')) {
    /**
     * Remove from cart
     */
    function removeFromCart($productId) {
        if (isset($_SESSION['cart'][$productId])) {
            unset($_SESSION['cart'][$productId]);
            return true;
        }
        return false;
    }
}

if (!function_exists('clearCart')) {
    /**
     * Clear cart
     */
    function clearCart() {
        $_SESSION['cart'] = [];
        return true;
    }
}

// =============================================
// LOGGING FUNCTIONS
// =============================================

if (!function_exists('logActivity')) {
    /**
     * Log activity
     */
    function logActivity($userId, $action, $entityType = null, $entityId = null, $description = null) {
        try {
            $db = getDB();
            
            // Check if table exists
            try {
                $db->query("SELECT 1 FROM activity_logs LIMIT 1");
            } catch (PDOException $e) {
                // Create table if not exists
                $createTable = "
                    CREATE TABLE IF NOT EXISTS activity_logs (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        user_id INT NULL,
                        action VARCHAR(255) NOT NULL,
                        entity_type VARCHAR(50) NULL,
                        entity_id INT NULL,
                        description TEXT NULL,
                        ip_address VARCHAR(45) NULL,
                        user_agent TEXT NULL,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        INDEX idx_user_id (user_id),
                        INDEX idx_action (action),
                        INDEX idx_created_at (created_at)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
                ";
                $db->exec($createTable);
            }
            
            $sql = "INSERT INTO activity_logs (user_id, action, entity_type, entity_id, description, ip_address, user_agent, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
            $stmt = $db->prepare($sql);
            $stmt->execute([
                $userId,
                $action,
                $entityType,
                $entityId,
                $description,
                $_SERVER['REMOTE_ADDR'] ?? null,
                $_SERVER['HTTP_USER_AGENT'] ?? null
            ]);
            return true;
        } catch (Exception $e) {
            error_log("Activity logging failed: " . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('logError')) {
    /**
     * Log error
     */
    function logError($message, $context = []) {
        $logMessage = date('Y-m-d H:i:s') . ' - ' . $message;
        if (!empty($context)) {
            $logMessage .= ' - Context: ' . json_encode($context);
        }
        error_log($logMessage);
    }
}

// =============================================
// NOTIFICATION FUNCTIONS
// =============================================

if (!function_exists('addNotification')) {
    /**
     * Add notification for user
     */
    function addNotification($user_id, $type, $title, $message, $link = null) {
        try {
            $db = getDB();
            
            // Check if table exists
            try {
                $db->query("SELECT 1 FROM notifications LIMIT 1");
            } catch (PDOException $e) {
                // Create table if not exists
                $createTable = "
                    CREATE TABLE IF NOT EXISTS notifications (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        user_id INT NOT NULL,
                        type VARCHAR(50) DEFAULT 'info',
                        title VARCHAR(255) NOT NULL,
                        message TEXT NOT NULL,
                        link VARCHAR(255) NULL,
                        is_read TINYINT(1) DEFAULT 0,
                        read_at TIMESTAMP NULL,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        INDEX idx_user_id (user_id),
                        INDEX idx_is_read (is_read),
                        INDEX idx_created_at (created_at)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
                ";
                $db->exec($createTable);
            }
            
            $sql = "INSERT INTO notifications (user_id, type, title, message, link, created_at) VALUES (?, ?, ?, ?, ?, NOW())";
            $stmt = $db->prepare($sql);
            return $stmt->execute([$user_id, $type, $title, $message, $link]);
        } catch (Exception $e) {
            error_log("Failed to add notification: " . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('getNotifications')) {
    /**
     * Get user notifications
     */
    function getNotifications($user_id, $limit = 10) {
        try {
            $db = getDB();
            $sql = "SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT ?";
            $stmt = $db->prepare($sql);
            $stmt->execute([$user_id, $limit]);
            return $stmt->fetchAll();
        } catch (Exception $e) {
            return [];
        }
    }
}

if (!function_exists('markNotificationRead')) {
    /**
     * Mark notification as read
     */
    function markNotificationRead($notification_id, $user_id) {
        try {
            $db = getDB();
            $sql = "UPDATE notifications SET is_read = 1, read_at = NOW() WHERE id = ? AND user_id = ?";
            $stmt = $db->prepare($sql);
            return $stmt->execute([$notification_id, $user_id]);
        } catch (Exception $e) {
            return false;
        }
    }
}

if (!function_exists('getUnreadNotificationCount')) {
    /**
     * Get unread notification count
     */
    function getUnreadNotificationCount($user_id) {
        try {
            $db = getDB();
            $sql = "SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0";
            $stmt = $db->prepare($sql);
            $stmt->execute([$user_id]);
            return (int)$stmt->fetchColumn();
        } catch (Exception $e) {
            return 0;
        }
    }
}

// =============================================
// JSON RESPONSES
// =============================================

if (!function_exists('jsonResponse')) {
    /**
     * Return JSON response
     */
    function jsonResponse($data, $statusCode = 200) {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit();
    }
}

if (!function_exists('jsonSuccess')) {
    /**
     * Return JSON success
     */
    function jsonSuccess($data = null, $message = 'Success') {
        jsonResponse(['success' => true, 'message' => $message, 'data' => $data]);
    }
}

if (!function_exists('jsonError')) {
    /**
     * Return JSON error
     */
    function jsonError($message = 'Error', $data = null) {
        jsonResponse(['success' => false, 'message' => $message, 'data' => $data], 400);
    }
}

// =============================================
// ORDER FUNCTIONS
// =============================================

if (!function_exists('generateOrderNumber')) {
    /**
     * Generate order number
     */
    function generateOrderNumber() {
        return 'ORD-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
    }
}

if (!function_exists('generateInvoiceNumber')) {
    /**
     * Generate invoice number
     */
    function generateInvoiceNumber() {
        return 'INV-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
    }
}

if (!function_exists('generateQuotationNumber')) {
    /**
     * Generate quotation number
     */
    function generateQuotationNumber() {
        return 'QTN-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
    }
}

// =============================================
// PRODUCT FUNCTIONS
// =============================================

if (!function_exists('getProductImage')) {
    /**
     * Get product image URL
     */
    function getProductImage($productId, $isPrimary = true) {
        try {
            $db = getDB();
            $sql = "SELECT image_path FROM product_images WHERE product_id = ? AND is_primary = ? LIMIT 1";
            $stmt = $db->prepare($sql);
            $stmt->execute([$productId, $isPrimary ? 1 : 0]);
            $result = $stmt->fetch();
            
            if ($result) {
                $baseUrl = getenv('APP_URL') ?: '/TechProcure17';
                if (strpos($result['image_path'], 'uploads/') === 0) {
                    return $baseUrl . '/' . $result['image_path'];
                } else {
                    return $baseUrl . '/uploads/products/' . $productId . '/' . $result['image_path'];
                }
            }
        } catch (Exception $e) {
            // Fall through to placeholder
        }
        return getenv('APP_URL') ?: '/TechProcure17' . '/assets/images/placeholder-product.jpg';
    }
}

// =============================================
// ESCROW FUNCTIONS
// =============================================

if (!function_exists('createEscrow')) {
    /**
     * Create escrow payment
     */
    function createEscrow($orderId, $amount) {
        try {
            $db = getDB();
            
            // Check if table exists
            try {
                $db->query("SELECT 1 FROM escrow_payments LIMIT 1");
            } catch (PDOException $e) {
                // Create table if not exists
                $createTable = "
                    CREATE TABLE IF NOT EXISTS escrow_payments (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        order_id INT NOT NULL,
                        amount DECIMAL(10,2) NOT NULL,
                        status ENUM('pending','released','refunded','disputed') DEFAULT 'pending',
                        release_date TIMESTAMP NULL,
                        refund_date TIMESTAMP NULL,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        INDEX idx_order_id (order_id),
                        INDEX idx_status (status)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
                ";
                $db->exec($createTable);
            }
            
            $sql = "INSERT INTO escrow_payments (order_id, amount, status, created_at) VALUES (?, ?, 'pending', NOW())";
            $stmt = $db->prepare($sql);
            $stmt->execute([$orderId, $amount]);
            return $db->lastInsertId();
        } catch (Exception $e) {
            error_log("Create escrow failed: " . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('releaseEscrow')) {
    /**
     * Release escrow payment
     */
    function releaseEscrow($escrowId) {
        try {
            $db = getDB();
            $sql = "UPDATE escrow_payments SET status = 'released', release_date = NOW() WHERE id = ?";
            $stmt = $db->prepare($sql);
            $stmt->execute([$escrowId]);
            return true;
        } catch (Exception $e) {
            error_log("Release escrow failed: " . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('refundEscrow')) {
    /**
     * Refund escrow payment
     */
    function refundEscrow($escrowId) {
        try {
            $db = getDB();
            $sql = "UPDATE escrow_payments SET status = 'refunded', refund_date = NOW() WHERE id = ?";
            $stmt = $db->prepare($sql);
            $stmt->execute([$escrowId]);
            return true;
        } catch (Exception $e) {
            error_log("Refund escrow failed: " . $e->getMessage());
            return false;
        }
    }
}

// =============================================
// FLASH MESSAGES
// =============================================

if (!function_exists('setFlash')) {
    /**
     * Set flash message
     */
    function setFlash($type, $message) {
        $_SESSION['flash'] = [
            'type' => $type,
            'message' => $message
        ];
    }
}

if (!function_exists('getFlash')) {
    /**
     * Get flash message
     */
    function getFlash() {
        if (isset($_SESSION['flash'])) {
            $flash = $_SESSION['flash'];
            unset($_SESSION['flash']);
            return $flash;
        }
        return null;
    }
}

if (!function_exists('displayFlash')) {
    /**
     * Display flash message
     */
    function displayFlash() {
        $flash = getFlash();
        if (!$flash) {
            return '';
        }
        
        $type = $flash['type'];
        $message = htmlspecialchars($flash['message']);
        
        $alertClass = match($type) {
            'success' => 'alert-success',
            'error' => 'alert-danger',
            'warning' => 'alert-warning',
            'info' => 'alert-info',
            default => 'alert-info'
        };
        
        $icon = match($type) {
            'success' => 'fa-check-circle',
            'error' => 'fa-exclamation-circle',
            'warning' => 'fa-exclamation-triangle',
            'info' => 'fa-info-circle',
            default => 'fa-info-circle'
        };
        
        return '<div class="alert ' . $alertClass . ' alert-dismissible fade show" role="alert">
                    <i class="fas ' . $icon . ' me-2"></i>
                    ' . $message . '
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>';
    }
}

// =============================================
// FILE FUNCTIONS
// =============================================

if (!function_exists('uploadFile')) {
    /**
     * Upload file
     */
    function uploadFile($file, $targetDir, $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf'], $maxSize = 5242880) {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return false;
        }
        
        if ($file['size'] > $maxSize) {
            return false;
        }
        
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        if (!in_array($mimeType, $allowedTypes)) {
            return false;
        }
        
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = time() . '_' . randomString(16) . '.' . $extension;
        $targetPath = rtrim($targetDir, '/') . '/' . $filename;
        
        if (!is_dir(dirname($targetPath))) {
            mkdir(dirname($targetPath), 0755, true);
        }
        
        if (move_uploaded_file($file['tmp_name'], $targetPath)) {
            return $filename;
        }
        
        return false;
    }
}

if (!function_exists('deleteFile')) {
    /**
     * Delete file
     */
    function deleteFile($filePath) {
        if (file_exists($filePath) && is_file($filePath)) {
            return unlink($filePath);
        }
        return false;
    }
}

// =============================================
// EMAIL FUNCTIONS
// =============================================

if (!function_exists('sendEmail')) {
    /**
     * Send email
     */
    function sendEmail($to, $subject, $body, $from = null) {
        try {
            $from = $from ?: getenv('MAIL_FROM') ?: 'no-reply@techprocure.com';
            
            $headers = [
                'MIME-Version: 1.0',
                'Content-type: text/html; charset=UTF-8',
                'From: ' . $from,
                'Reply-To: ' . $from,
                'X-Mailer: PHP/' . phpversion()
            ];
            
            return mail($to, $subject, $body, implode("\r\n", $headers));
        } catch (Exception $e) {
            error_log("Email sending failed: " . $e->getMessage());
            return false;
        }
    }
}

// =============================================
// PERMISSION FUNCTIONS
// =============================================

if (!function_exists('hasPermission')) {
    /**
     * Check if user has permission
     */
    function hasPermission($permission) {
        if (!isLoggedIn()) {
            return false;
        }
        
        if (isAdmin()) {
            return true;
        }
        
        if (isset($_SESSION['permissions']) && is_array($_SESSION['permissions'])) {
            return in_array($permission, $_SESSION['permissions']);
        }
        
        try {
            $db = getDB();
            $stmt = $db->prepare("
                SELECT COUNT(*) as count
                FROM user_permissions up
                JOIN permissions p ON up.permission_id = p.id
                WHERE up.user_id = ? AND p.permission_name = ?
            ");
            $stmt->execute([$_SESSION['user_id'], $permission]);
            $result = $stmt->fetch();
            return ($result['count'] ?? 0) > 0;
        } catch (Exception $e) {
            return false;
        }
    }
}

if (!function_exists('getCategoryIcon')) {
    /**
     * Get category icon
     */
    function getCategoryIcon($categoryName) {
        $icons = [
            'Computers' => 'fa-desktop',
            'Laptops' => 'fa-laptop',
            'Servers' => 'fa-server',
            'Networking' => 'fa-network-wired',
            'Software' => 'fa-code',
            'Storage' => 'fa-database',
            'Accessories' => 'fa-keyboard',
            'Printers' => 'fa-print',
            'Phones' => 'fa-mobile-alt',
            'Tablets' => 'fa-tablet-alt',
            'Monitors' => 'fa-tv',
            'Security' => 'fa-shield-alt'
        ];
        return $icons[$categoryName] ?? 'fa-box';
    }
}

// =============================================
// M-PESA DARAJA API FUNCTIONS (WITH SIMULATION)
// =============================================

if (!function_exists('getMpesaAccessToken')) {
    /**
     * Get M-Pesa OAuth 2.0 Access Token
     * @return string Access token
     * @throws Exception
     */
    function getMpesaAccessToken() {
        // =============================================
        // SIMULATION MODE - FOR LEARNING ONLY
        // =============================================
        if (MPESA_SIMULATION_MODE) {
            // Simulate token generation
            return 'SIM_TOKEN_' . date('YmdHis') . '_' . rand(1000, 9999);
        }
        
        // Real implementation
        $consumerKey = getenv('MPESA_CONSUMER_KEY') ?: 'YOUR_CONSUMER_KEY';
        $consumerSecret = getenv('MPESA_CONSUMER_SECRET') ?: 'YOUR_CONSUMER_SECRET';
        $baseUrl = getenv('MPESA_ENVIRONMENT') === 'production' 
            ? 'https://api.safaricom.co.ke' 
            : 'https://sandbox.safaricom.co.ke';
        
        $url = $baseUrl . '/oauth/v1/generate?grant_type=client_credentials';
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Basic ' . base64_encode($consumerKey . ':' . $consumerSecret)
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            throw new Exception('cURL Error: ' . $curlError);
        }

        if ($httpCode !== 200) {
            throw new Exception('Failed to get access token. HTTP Code: ' . $httpCode);
        }

        $data = json_decode($response, true);
        
        if (!isset($data['access_token'])) {
            throw new Exception('Access token not found in response');
        }
        
        return $data['access_token'];
    }
}

if (!function_exists('mpesaStkPush')) {
    /**
     * Send M-Pesa STK Push payment request
     * @param string $phoneNumber Phone number (format: 2547XXXXXXXX)
     * @param float $amount Amount to charge
     * @param string $accountReference Account reference (Order number)
     * @param string $transactionDesc Transaction description
     * @return array Response from M-Pesa API
     */
    function mpesaStkPush($phoneNumber, $amount, $accountReference, $transactionDesc = 'Payment') {
        // =============================================
        // SIMULATION MODE - FOR LEARNING ONLY
        // =============================================
        if (MPESA_SIMULATION_MODE) {
            // Simulate processing delay
            usleep(500000); // 0.5 second delay
            
            // Format phone number
            $phoneNumber = formatMpesaPhone($phoneNumber);
            
            // Generate a fake checkout ID
            $checkoutID = 'SIM_' . date('YmdHis') . '_' . rand(10000, 99999);
            
            // Simulate success (90% success rate for learning)
            $success = rand(1, 100) <= 90;
            
            if ($success) {
                return [
                    'status' => 'success',
                    'message' => '✅ SIMULATION: STK Push sent successfully!',
                    'checkoutRequestID' => $checkoutID,
                    'merchantRequestID' => 'MERCH_' . date('YmdHis'),
                    'responseCode' => '0',
                    'responseDescription' => 'Simulation Success',
                    'is_simulation' => true,
                    'simulated_phone' => $phoneNumber,
                    'simulated_amount' => $amount,
                    'simulated_reference' => $accountReference,
                    'next_step' => 'Enter PIN: ' . MPESA_SIMULATION_PIN . ' on your phone',
                    'note' => '🔬 This is a simulation for learning purposes only.'
                ];
            } else {
                return [
                    'status' => 'failed',
                    'message' => '❌ SIMULATION: Payment failed - Insufficient funds',
                    'responseCode' => '01',
                    'errorMessage' => 'Insufficient funds in the account',
                    'is_simulation' => true,
                    'simulated_phone' => $phoneNumber,
                    'simulated_amount' => $amount
                ];
            }
        }
        
        // =============================================
        // REAL M-PESA IMPLEMENTATION
        // =============================================
        try {
            // Get credentials from environment or use defaults
            $shortcode = getenv('MPESA_SHORTCODE') ?: '174379';
            $passkey = getenv('MPESA_PASSKEY') ?: 'YOUR_PASSKEY';
            $callbackUrl = getenv('MPESA_CALLBACK_URL') ?: 'https://techprocure.co.tz/payment/callback/mpesa.php';
            $baseUrl = getenv('MPESA_ENVIRONMENT') === 'production' 
                ? 'https://api.safaricom.co.ke' 
                : 'https://sandbox.safaricom.co.ke';
            
            // Format phone number
            $phoneNumber = formatMpesaPhone($phoneNumber);
            
            // Get access token
            $token = getMpesaAccessToken();
            
            // Generate timestamp and password
            $timestamp = date('YmdHis');
            $password = base64_encode($shortcode . $passkey . $timestamp);
            
            // Build request data
            $data = [
                'BusinessShortCode' => $shortcode,
                'Password' => $password,
                'Timestamp' => $timestamp,
                'TransactionType' => 'CustomerPayBillOnline',
                'Amount' => (int)$amount,
                'PartyA' => $phoneNumber,
                'PartyB' => $shortcode,
                'PhoneNumber' => $phoneNumber,
                'CallBackURL' => $callbackUrl,
                'AccountReference' => substr($accountReference, 0, 12),
                'TransactionDesc' => substr($transactionDesc, 0, 13)
            ];
            
            // Send request
            $url = $baseUrl . '/mpesa/stkpush/v1/processrequest';
            
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $token,
                'Content-Type: application/json'
            ]);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);
            
            if ($curlError) {
                throw new Exception('cURL Error: ' . $curlError);
            }
            
            $result = json_decode($response, true);
            
            if ($httpCode !== 200) {
                throw new Exception('STK Push failed: ' . ($result['errorMessage'] ?? $response));
            }
            
            // Check if request was successful
            if (isset($result['ResponseCode']) && $result['ResponseCode'] == '0') {
                return [
                    'status' => 'success',
                    'message' => 'STK Push sent successfully',
                    'checkoutRequestID' => $result['CheckoutRequestID'] ?? null,
                    'merchantRequestID' => $result['MerchantRequestID'] ?? null,
                    'responseCode' => $result['ResponseCode'],
                    'responseDescription' => $result['ResponseDescription'] ?? 'Success'
                ];
            } else {
                return [
                    'status' => 'failed',
                    'message' => $result['ResponseDescription'] ?? 'Payment request failed',
                    'responseCode' => $result['ResponseCode'] ?? '01',
                    'errorMessage' => $result['errorMessage'] ?? 'Unknown error'
                ];
            }
            
        } catch (Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Payment processing error: ' . $e->getMessage()
            ];
        }
    }
}

if (!function_exists('mpesaQueryStatus')) {
    /**
     * Query M-Pesa STK Push transaction status
     * @param string $checkoutRequestID The checkout request ID from STK Push
     * @return array Transaction status
     */
    function mpesaQueryStatus($checkoutRequestID) {
        // =============================================
        // SIMULATION MODE - FOR LEARNING ONLY
        // =============================================
        if (MPESA_SIMULATION_MODE) {
            // Simulate status check
            usleep(300000); // 0.3 second delay
            
            $isComplete = rand(1, 100) <= 85;
            
            if ($isComplete) {
                return [
                    'ResultCode' => '0',
                    'ResultDesc' => 'The transaction was successful',
                    'TransactionID' => 'SIM_TXN_' . date('YmdHis'),
                    'Amount' => rand(1000, 50000),
                    'PhoneNumber' => MPESA_SIMULATION_PHONE,
                    'is_simulation' => true
                ];
            } else {
                return [
                    'ResultCode' => '1',
                    'ResultDesc' => 'Transaction is still being processed',
                    'is_simulation' => true
                ];
            }
        }
        
        // Real implementation
        try {
            $shortcode = getenv('MPESA_SHORTCODE') ?: '174379';
            $passkey = getenv('MPESA_PASSKEY') ?: 'YOUR_PASSKEY';
            $baseUrl = getenv('MPESA_ENVIRONMENT') === 'production' 
                ? 'https://api.safaricom.co.ke' 
                : 'https://sandbox.safaricom.co.ke';
            
            $token = getMpesaAccessToken();
            $timestamp = date('YmdHis');
            $password = base64_encode($shortcode . $passkey . $timestamp);
            
            $data = [
                'BusinessShortCode' => $shortcode,
                'Password' => $password,
                'Timestamp' => $timestamp,
                'CheckoutRequestID' => $checkoutRequestID
            ];
            
            $url = $baseUrl . '/mpesa/stkpushquery/v1/query';
            
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $token,
                'Content-Type: application/json'
            ]);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode !== 200) {
                throw new Exception('Query failed');
            }
            
            return json_decode($response, true);
            
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }
}

if (!function_exists('formatMpesaPhone')) {
    /**
     * Format phone number for M-Pesa (2547XXXXXXXX)
     * @param string $phone Phone number
     * @return string Formatted phone number
     */
    function formatMpesaPhone($phone) {
        // Remove all non-numeric characters
        $phone = preg_replace('/[^0-9]/', '', $phone);
        
        // Remove leading 0
        if (strlen($phone) == 10 && substr($phone, 0, 1) == '0') {
            $phone = '254' . substr($phone, 1);
        }
        // Remove leading 255 if present
        elseif (strlen($phone) == 12 && substr($phone, 0, 3) == '255') {
            $phone = '254' . substr($phone, 3);
        }
        // Remove leading +255 if present
        elseif (strlen($phone) == 13 && substr($phone, 0, 4) == '255') {
            $phone = '254' . substr($phone, 4);
        }
        // If it's a 9-digit number starting with 7 or 6
        elseif (strlen($phone) == 9 && in_array(substr($phone, 0, 1), ['7', '6'])) {
            $phone = '254' . $phone;
        }
        // If it's a 10-digit number starting with 7 or 6 (without 0)
        elseif (strlen($phone) == 10 && in_array(substr($phone, 0, 1), ['7', '6'])) {
            $phone = '254' . $phone;
        }
        
        return $phone;
    }
}

if (!function_exists('validateMpesaPhone')) {
    /**
     * Validate M-Pesa phone number format
     * @param string $phone Phone number
     * @return bool True if valid
     */
    function validateMpesaPhone($phone) {
        $formatted = formatMpesaPhone($phone);
        // Check if it matches Tanzanian M-Pesa format (2547XXXXXXXX or 2546XXXXXXXX)
        return preg_match('/^254[67][0-9]{8}$/', $formatted) === 1;
    }
}

if (!function_exists('getMpesaConfig')) {
    /**
     * Get M-Pesa configuration
     * @return array M-Pesa configuration
     */
    function getMpesaConfig() {
        if (MPESA_SIMULATION_MODE) {
            return [
                'consumer_key' => 'SIMULATION_MODE',
                'consumer_secret' => 'SIMULATION_MODE',
                'shortcode' => '174379',
                'passkey' => 'SIMULATION_MODE',
                'callback_url' => 'http://localhost/Techprocure17/payment/callback/mpesa.php',
                'environment' => 'simulation',
                'base_url' => 'https://sandbox.safaricom.co.ke',
                'test_phone' => MPESA_SIMULATION_PHONE,
                'is_simulation' => true
            ];
        }
        
        return [
            'consumer_key' => getenv('MPESA_CONSUMER_KEY') ?: 'YOUR_CONSUMER_KEY',
            'consumer_secret' => getenv('MPESA_CONSUMER_SECRET') ?: 'YOUR_CONSUMER_SECRET',
            'shortcode' => getenv('MPESA_SHORTCODE') ?: '174379',
            'passkey' => getenv('MPESA_PASSKEY') ?: 'YOUR_PASSKEY',
            'callback_url' => getenv('MPESA_CALLBACK_URL') ?: 'https://techprocure.co.tz/payment/callback/mpesa.php',
            'environment' => getenv('MPESA_ENVIRONMENT') ?: 'sandbox',
            'base_url' => getenv('MPESA_ENVIRONMENT') === 'production' 
                ? 'https://api.safaricom.co.ke' 
                : 'https://sandbox.safaricom.co.ke',
            'test_phone' => '255760211221'
        ];
    }
}

if (!function_exists('isMpesaConfigured')) {
    /**
     * Check if M-Pesa is properly configured
     * @return bool True if configured
     */
    function isMpesaConfigured() {
        if (MPESA_SIMULATION_MODE) {
            return true; // Simulation is always "configured"
        }
        $config = getMpesaConfig();
        return $config['consumer_key'] !== 'YOUR_CONSUMER_KEY' 
            && $config['consumer_secret'] !== 'YOUR_CONSUMER_SECRET'
            && $config['passkey'] !== 'YOUR_PASSKEY';
    }
}

if (!function_exists('getMpesaTestPhone')) {
    /**
     * Get the test phone number for M-Pesa
     * @return string Test phone number
     */
    function getMpesaTestPhone() {
        return MPESA_SIMULATION_PHONE;
    }
}

// =============================================
// END OF FILE
// =============================================