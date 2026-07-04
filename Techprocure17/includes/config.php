<?php
/**
 * TechProcure Tanzania - Main Configuration
 * File: includes/config.php
 * Description: Central configuration for the entire application
 */

// =============================================
// ERROR REPORTING
// =============================================

// Detect environment
$is_local = in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1', 'localhost']) || 
            strpos($_SERVER['HTTP_HOST'] ?? '', 'localhost') !== false;

if ($is_local) {
    // Development environment
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    define('ENVIRONMENT', 'development');
} else {
    // Production environment
    error_reporting(0);
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
    define('ENVIRONMENT', 'production');
}

// =============================================
// APPLICATION PATHS
// =============================================

// Base path - adjust if files are in a subdirectory
define('BASE_PATH', realpath(__DIR__ . '/../'));

// URL detection
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$script_name = $_SERVER['SCRIPT_NAME'] ?? '';
$dir = dirname($script_name);
$base_url = $protocol . $host . ($dir == '/' ? '' : $dir);

define('SITE_URL', rtrim($base_url, '/') . '/');
define('ADMIN_URL', SITE_URL . 'admin/');
define('ASSETS_URL', SITE_URL . 'assets/');
define('UPLOADS_URL', SITE_URL . 'uploads/');

// =============================================
// APPLICATION SETTINGS
// =============================================

define('SITE_NAME', 'TechProcure Tanzania');
define('SITE_TAGLINE', 'Enterprise IT Procurement Platform');
define('SITE_DESCRIPTION', 'B2B IT equipment marketplace connecting buyers with verified suppliers in Tanzania.');
define('SITE_EMAIL', 'info@techprocure.co.tz');
define('ADMIN_EMAIL', 'admin@techprocure.co.tz');
define('SUPPORT_EMAIL', 'support@techprocure.co.tz');
define('SITE_PHONE', '+255 123 456 789');
define('SITE_ADDRESS', 'Dar es Salaam, Tanzania');

// =============================================
// DATABASE SETTINGS
// =============================================

define('DB_HOST', 'localhost');
define('DB_NAME', 'techprocure17');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');
define('DB_PORT', 3306);
define('DB_PERSISTENT', false);

// =============================================
// SECURITY SETTINGS
// =============================================

// Password settings
define('PASSWORD_MIN_LENGTH', 8);
define('PASSWORD_BCRYPT_COST', 12);

// Session settings
define('SESSION_TIMEOUT', 7200); // 2 hours
define('SESSION_NAME', 'techprocure_session');
define('SESSION_HTTP_ONLY', true);
define('SESSION_USE_ONLY_COOKIES', true);
define('SESSION_COOKIE_SECURE', false); // Set to true in production with HTTPS

// CSRF Protection
define('CSRF_TOKEN_LENGTH', 32);
define('CSRF_TOKEN_EXPIRY', 3600); // 1 hour

// Rate Limiting
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_ATTEMPT_WINDOW', 900); // 15 minutes
define('MAX_REQUESTS_PER_MINUTE', 60);

// Password Reset
define('PASSWORD_RESET_EXPIRY', 3600); // 1 hour

// =============================================
// PAYMENT SETTINGS
// =============================================

define('DEFAULT_CURRENCY', 'TSh');
define('CURRENCY_SYMBOL', 'TSh');
define('CURRENCY_DECIMALS', 2);
define('CURRENCY_DECIMAL_POINT', '.');
define('CURRENCY_THOUSANDS_SEPARATOR', ',');

// Payment Methods
define('PAYMENT_METHODS', [
    'mpesa' => ['name' => 'M-Pesa', 'enabled' => true, 'icon' => 'fas fa-mobile-alt'],
    'airtel_money' => ['name' => 'Airtel Money', 'enabled' => true, 'icon' => 'fas fa-mobile-alt'],
    'tigo_pesa' => ['name' => 'Tigo Pesa', 'enabled' => true, 'icon' => 'fas fa-mobile-alt'],
    'halopesa' => ['name' => 'Halopesa', 'enabled' => true, 'icon' => 'fas fa-mobile-alt'],
    'bank_transfer' => ['name' => 'Bank Transfer', 'enabled' => true, 'icon' => 'fas fa-university'],
    'card' => ['name' => 'Credit/Debit Card', 'enabled' => true, 'icon' => 'fas fa-credit-card']
]);

// Escrow Settings
define('ESCROW_ENABLED', true);
define('ESCROW_MIN_AMOUNT', 10000);
define('ESCROW_MAX_AMOUNT', 100000000);
define('ESCROW_RELEASE_DAYS', 7);

// Commission Settings
define('COMMISSION_RATE', 2.5); // Percentage
define('COMMISSION_MIN', 1000);
define('COMMISSION_MAX', 1000000);

// M-Pesa API
define('MPESA_API_KEY', '');
define('MPESA_PUBLIC_KEY', '');
define('MPESA_ENVIRONMENT', 'sandbox'); // sandbox or production
define('MPESA_BASE_URL', 'https://sandbox.safaricom.co.ke');

// =============================================
// FILE UPLOAD SETTINGS
// =============================================

define('MAX_FILE_SIZE', 5242880); // 5MB
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/png', 'image/gif', 'image/webp']);
define('ALLOWED_FILE_TYPES', ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document']);
define('IMAGE_MAX_WIDTH', 2000);
define('IMAGE_MAX_HEIGHT', 2000);

// Upload Paths
define('UPLOAD_PATH', BASE_PATH . '/uploads/');
define('PRODUCT_UPLOAD_PATH', UPLOAD_PATH . 'products/');
define('PROFILE_UPLOAD_PATH', UPLOAD_PATH . 'profiles/');
define('INVOICE_UPLOAD_PATH', UPLOAD_PATH . 'invoices/');
define('QUOTATION_UPLOAD_PATH', UPLOAD_PATH . 'quotations/');
define('DISPUTE_UPLOAD_PATH', UPLOAD_PATH . 'disputes/');

// =============================================
// PAGINATION SETTINGS
// =============================================

define('ITEMS_PER_PAGE', 20);
define('ADMIN_ITEMS_PER_PAGE', 50);
define('PRODUCTS_PER_PAGE', 12);
define('ORDERS_PER_PAGE', 20);

// =============================================
// CART SETTINGS
// =============================================

define('CART_EXPIRY_HOURS', 72);
define('MAX_CART_ITEMS', 100);
define('MIN_ORDER_QUANTITY', 1);
define('MAX_ORDER_QUANTITY', 999);

// =============================================
// ORDER SETTINGS
// =============================================

define('ORDER_PENDING_HOURS', 48);
define('ORDER_CONFIRMATION_REQUIRED', true);
define('ORDER_PROCESSING_DAYS', 2);

// =============================================
// REVIEW SETTINGS
// =============================================

define('REVIEW_MODERATION_ENABLED', true);
define('REVIEW_AUTO_APPROVE', false);
define('MAX_REVIEW_LENGTH', 1000);
define('MIN_REVIEW_LENGTH', 10);

// =============================================
// LOGGING SETTINGS
// =============================================

define('LOG_ENABLED', true);
define('LOG_PATH', BASE_PATH . '/logs/');
define('LOG_LEVEL', 'info'); // debug, info, warning, error, critical
define('LOG_MAX_SIZE', 10485760); // 10MB

// =============================================
// TIMEZONE
// =============================================

date_default_timezone_set('Africa/Dar_es_Salaam');
setlocale(LC_TIME, 'sw_TZ', 'sw', 'en_US.utf8');

// =============================================
// CONSTANTS FOR STATUSES
// =============================================

// User types
define('USER_TYPE_CUSTOMER', 'customer');
define('USER_TYPE_SUPPLIER', 'supplier');
define('USER_TYPE_ADMIN', 'admin');

// Order statuses
define('ORDER_STATUS_PENDING', 'pending');
define('ORDER_STATUS_CONFIRMED', 'confirmed');
define('ORDER_STATUS_PROCESSING', 'processing');
define('ORDER_STATUS_SHIPPED', 'shipped');
define('ORDER_STATUS_DELIVERED', 'delivered');
define('ORDER_STATUS_COMPLETED', 'completed');
define('ORDER_STATUS_CANCELLED', 'cancelled');

// Payment statuses
define('PAYMENT_STATUS_PENDING', 'pending');
define('PAYMENT_STATUS_PAID', 'paid');
define('PAYMENT_STATUS_FAILED', 'failed');
define('PAYMENT_STATUS_REFUNDED', 'refunded');
define('PAYMENT_STATUS_PARTIAL', 'partial');

// =============================================
// SESSION START
// =============================================

if (session_status() === PHP_SESSION_NONE) {
    session_name(SESSION_NAME);
    session_set_cookie_params(
        SESSION_TIMEOUT,
        '/',
        '',
        SESSION_COOKIE_SECURE,
        SESSION_HTTP_ONLY
    );
    session_start();
    
    // Regenerate session ID periodically
    if (!isset($_SESSION['last_regeneration'])) {
        session_regenerate_id(true);
        $_SESSION['last_regeneration'] = time();
    } elseif (time() - $_SESSION['last_regeneration'] > 1800) {
        session_regenerate_id(true);
        $_SESSION['last_regeneration'] = time();
    }
}

// =============================================
// HELPER FUNCTIONS
// =============================================

function isDevelopment() {
    return ENVIRONMENT === 'development';
}

function isProduction() {
    return ENVIRONMENT === 'production';
}

function getSiteUrl() {
    return SITE_URL;
}

function getAssetUrl($path = '') {
    return ASSETS_URL . ltrim($path, '/');
}

function getUploadUrl($path = '') {
    return UPLOADS_URL . ltrim($path, '/');
}

function getUploadPath($path = '') {
    return UPLOAD_PATH . ltrim($path, '/');
}

// =============================================
// CREATE REQUIRED DIRECTORIES
// =============================================

if (isDevelopment()) {
    $directories = [
        LOG_PATH,
        UPLOAD_PATH,
        PRODUCT_UPLOAD_PATH,
        PROFILE_UPLOAD_PATH,
        INVOICE_UPLOAD_PATH,
        QUOTATION_UPLOAD_PATH,
        DISPUTE_UPLOAD_PATH
    ];
    
    foreach ($directories as $dir) {
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
    }
}

// Return configuration array
return [
    'site_name' => SITE_NAME,
    'site_url' => SITE_URL,
    'environment' => ENVIRONMENT,
    'db_host' => DB_HOST,
    'db_name' => DB_NAME,
    'db_user' => DB_USER,
    'db_pass' => DB_PASS,
    'session_timeout' => SESSION_TIMEOUT,
    'upload_path' => UPLOAD_PATH,
    'log_path' => LOG_PATH
];