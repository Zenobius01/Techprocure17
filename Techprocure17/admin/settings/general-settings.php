<?php
/**
 * TechProcure Tanzania - Admin General Settings
 * File: admin/settings/general-settings.php
 * Description: Complete system control panel - Every setting in one place
 */

// Start session if not started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include configuration files
require_once '../../includes/config.php';
require_once '../../includes/db.php';
require_once '../../includes/functions.php';

// Check if user is admin
requireAdmin();

// Get database connection
$db = getDB();
$user_id = $_SESSION['user_id'];

// =============================================
// FIX: UPDATE TABLE STRUCTURE IF NEEDED
// =============================================

function ensureSettingsTableStructure($db) {
    try {
        // Check if table exists
        $table_check = $db->query("SHOW TABLES LIKE 'system_settings'");
        
        if ($table_check->rowCount() == 0) {
            // Create table with full structure
            $db->exec("
                CREATE TABLE system_settings (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    setting_key VARCHAR(100) UNIQUE NOT NULL,
                    setting_value TEXT,
                    setting_type ENUM('text', 'number', 'boolean', 'json', 'file', 'array') DEFAULT 'text',
                    category VARCHAR(50) DEFAULT 'general',
                    description TEXT,
                    updated_by INT,
                    updated_at DATETIME ON UPDATE CURRENT_TIMESTAMP,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (updated_by) REFERENCES users(id)
                )
            ");
            return 'created';
        } else {
            // Check if category column exists
            try {
                $db->query("SELECT category FROM system_settings LIMIT 1");
            } catch (PDOException $e) {
                if (strpos($e->getMessage(), 'Unknown column') !== false) {
                    // Add missing columns
                    $db->exec("ALTER TABLE system_settings ADD COLUMN category VARCHAR(50) DEFAULT 'general'");
                    $db->exec("ALTER TABLE system_settings ADD COLUMN description TEXT");
                    $db->exec("ALTER TABLE system_settings ADD COLUMN updated_by INT");
                    $db->exec("ALTER TABLE system_settings ADD COLUMN updated_at DATETIME ON UPDATE CURRENT_TIMESTAMP");
                    $db->exec("ALTER TABLE system_settings ADD COLUMN created_at DATETIME DEFAULT CURRENT_TIMESTAMP");
                    
                    // Update existing settings with default category
                    $db->exec("UPDATE system_settings SET category = 'general' WHERE category IS NULL");
                    
                    return 'updated';
                }
            }
            return 'exists';
        }
    } catch (PDOException $e) {
        return 'error: ' . $e->getMessage();
    }
}

// Run the table structure check
$table_status = ensureSettingsTableStructure($db);

// =============================================
// FETCH ALL SYSTEM SETTINGS
// =============================================

$settings = [];
$error = '';
$success = '';

try {
    // Check if table has data
    $count_check = $db->query("SELECT COUNT(*) FROM system_settings")->fetchColumn();
    
    if ($count_check == 0) {
        // Insert all default settings
        $default_settings = [
            // Site Settings
            ['site_name', 'TechProcure Tanzania', 'text', 'general', 'Site name'],
            ['site_tagline', 'Enterprise IT Procurement Platform', 'text', 'general', 'Site tagline'],
            ['site_description', 'B2B IT equipment marketplace connecting buyers with verified suppliers in Tanzania.', 'text', 'general', 'Site meta description'],
            ['site_email', 'info@techprocure.co.tz', 'text', 'general', 'Default support email'],
            ['site_phone', '+255 123 456 789', 'text', 'general', 'Default contact phone'],
            ['site_address', 'Dar es Salaam, Tanzania', 'text', 'general', 'Business address'],
            ['site_facebook', 'https://facebook.com/techprocure', 'text', 'general', 'Facebook URL'],
            ['site_twitter', 'https://twitter.com/techprocure', 'text', 'general', 'Twitter URL'],
            ['site_instagram', 'https://instagram.com/techprocure', 'text', 'general', 'Instagram URL'],
            ['site_linkedin', 'https://linkedin.com/company/techprocure', 'text', 'general', 'LinkedIn URL'],
            ['site_youtube', 'https://youtube.com/techprocure', 'text', 'general', 'YouTube URL'],
            ['site_logo', '', 'file', 'general', 'Site logo image'],
            ['site_favicon', '', 'file', 'general', 'Site favicon'],
            
            // Currency & Tax
            ['currency', 'TSh', 'text', 'payment', 'Default currency'],
            ['currency_symbol', 'TSh', 'text', 'payment', 'Currency symbol'],
            ['tax_rate', '18.00', 'number', 'payment', 'Tax rate percentage'],
            ['tax_label', 'VAT', 'text', 'payment', 'Tax label'],
            ['decimal_places', '2', 'number', 'payment', 'Decimal places for prices'],
            ['thousand_separator', ',', 'text', 'payment', 'Thousand separator'],
            ['decimal_separator', '.', 'text', 'payment', 'Decimal separator'],
            
            // Business Settings
            ['commission_rate', '2.50', 'number', 'business', 'Commission rate for suppliers'],
            ['min_order_amount', '0', 'number', 'business', 'Minimum order amount'],
            ['free_shipping_threshold', '100000', 'number', 'business', 'Free shipping threshold'],
            ['delivery_days', '7', 'number', 'business', 'Default delivery days'],
            ['max_cart_items', '100', 'number', 'business', 'Maximum items per cart'],
            ['bulk_discount_tiers', '[{"min":5,"discount":3},{"min":10,"discount":8},{"min":50,"discount":15},{"min":200,"discount":25}]', 'json', 'business', 'Bulk discount tiers'],
            ['escrow_release_days', '7', 'number', 'business', 'Days after delivery to release escrow'],
            ['warranty_days', '30', 'number', 'business', 'Default warranty days'],
            ['return_days', '14', 'number', 'business', 'Return policy days'],
            
            // Payment Methods
            ['mpesa_enabled', 'true', 'boolean', 'payment', 'Enable M-Pesa'],
            ['airtel_money_enabled', 'true', 'boolean', 'payment', 'Enable Airtel Money'],
            ['tigo_pesa_enabled', 'true', 'boolean', 'payment', 'Enable Tigo Pesa'],
            ['halopesa_enabled', 'true', 'boolean', 'payment', 'Enable Halopesa'],
            ['bank_transfer_enabled', 'true', 'boolean', 'payment', 'Enable Bank Transfer'],
            ['card_payment_enabled', 'true', 'boolean', 'payment', 'Enable Card Payments'],
            ['bank_name', 'CRDB Bank', 'text', 'payment', 'Bank name'],
            ['bank_account_number', '1234567890', 'text', 'payment', 'Bank account number'],
            ['bank_account_name', 'TechProcure Tanzania', 'text', 'payment', 'Bank account name'],
            ['bank_swift_code', 'CORUTZTZ', 'text', 'payment', 'Bank SWIFT code'],
            
            // Feature Toggles
            ['escrow_enabled', 'true', 'boolean', 'features', 'Enable escrow payment system'],
            ['bulk_discount_enabled', 'true', 'boolean', 'features', 'Enable bulk discount system'],
            ['rfq_enabled', 'true', 'boolean', 'features', 'Enable RFQ system'],
            ['maintenance_mode', 'false', 'boolean', 'features', 'Put site in maintenance mode'],
            ['allow_registration', 'true', 'boolean', 'features', 'Allow new user registration'],
            ['email_verification_required', 'false', 'boolean', 'features', 'Require email verification'],
            ['supplier_registration', 'true', 'boolean', 'features', 'Allow supplier registration'],
            ['customer_reviews', 'true', 'boolean', 'features', 'Enable customer reviews'],
            ['wishlist_enabled', 'true', 'boolean', 'features', 'Enable wishlist feature'],
            ['compare_products', 'true', 'boolean', 'features', 'Enable product comparison'],
            ['live_chat', 'false', 'boolean', 'features', 'Enable live chat support'],
            
            // Security Settings
            ['session_timeout', '7200', 'number', 'security', 'Session timeout in seconds'],
            ['max_login_attempts', '5', 'number', 'security', 'Maximum login attempts'],
            ['password_min_length', '8', 'number', 'security', 'Minimum password length'],
            ['force_strong_password', 'true', 'boolean', 'security', 'Force strong passwords'],
            ['csrf_token_expiry', '3600', 'number', 'security', 'CSRF token expiry in seconds'],
            ['two_factor_auth', 'false', 'boolean', 'security', 'Enable two-factor authentication'],
            ['admin_ip_restriction', '', 'text', 'security', 'Restrict admin access to specific IPs'],
            ['recaptcha_enabled', 'false', 'boolean', 'security', 'Enable reCAPTCHA for forms'],
            ['recaptcha_site_key', '', 'text', 'security', 'reCAPTCHA site key'],
            ['recaptcha_secret_key', '', 'text', 'security', 'reCAPTCHA secret key'],
            
            // Email Settings
            ['smtp_host', 'smtp.gmail.com', 'text', 'email', 'SMTP server host'],
            ['smtp_port', '587', 'number', 'email', 'SMTP server port'],
            ['smtp_username', '', 'text', 'email', 'SMTP username'],
            ['smtp_password', '', 'text', 'email', 'SMTP password'],
            ['smtp_encryption', 'tls', 'text', 'email', 'SMTP encryption method'],
            ['mail_from', 'info@techprocure.co.tz', 'text', 'email', 'Default from email'],
            ['mail_from_name', 'TechProcure Tanzania', 'text', 'email', 'Default from name'],
            ['email_footer', 'Thank you for choosing TechProcure Tanzania.', 'text', 'email', 'Email footer text'],
            ['order_confirmation_subject', 'Order Confirmation - TechProcure', 'text', 'email', 'Order confirmation subject'],
            ['welcome_email_subject', 'Welcome to TechProcure Tanzania', 'text', 'email', 'Welcome email subject'],
            
            // API Settings
            ['api_enabled', 'true', 'boolean', 'api', 'Enable API access'],
            ['api_rate_limit', '100', 'number', 'api', 'API rate limit per minute'],
            ['api_key_length', '32', 'number', 'api', 'API key length'],
            ['api_debug_mode', 'false', 'boolean', 'api', 'API debug mode'],
            
            // SEO Settings
            ['seo_title', 'TechProcure - Enterprise IT Procurement', 'text', 'seo', 'Default SEO title'],
            ['seo_description', 'B2B IT equipment marketplace in Tanzania.', 'text', 'seo', 'Default SEO description'],
            ['seo_keywords', 'B2B, IT equipment, Tanzania, procurement', 'text', 'seo', 'Default SEO keywords'],
            ['google_analytics', '', 'text', 'seo', 'Google Analytics ID'],
            ['google_site_verification', '', 'text', 'seo', 'Google site verification code'],
            ['meta_author', 'TechProcure Tanzania', 'text', 'seo', 'Meta author'],
            
            // Content Settings
            ['blog_enabled', 'true', 'boolean', 'content', 'Enable blog'],
            ['comments_enabled', 'true', 'boolean', 'content', 'Enable comments'],
            ['blog_per_page', '10', 'number', 'content', 'Blog posts per page'],
            ['featured_products_limit', '8', 'number', 'content', 'Featured products limit'],
            ['products_per_page', '12', 'number', 'content', 'Products per page'],
            ['categories_limit', '6', 'number', 'content', 'Categories to display on homepage'],
            ['testimonials_enabled', 'true', 'boolean', 'content', 'Enable testimonials'],
            
            // Affiliate Settings
            ['affiliate_enabled', 'false', 'boolean', 'affiliate', 'Enable affiliate program'],
            ['affiliate_commission', '5.00', 'number', 'affiliate', 'Affiliate commission percentage'],
            ['affiliate_cookie_days', '30', 'number', 'affiliate', 'Affiliate cookie days'],
            
            // System Settings
            ['cron_job_key', '', 'text', 'system', 'Cron job security key'],
            ['auto_backup_enabled', 'false', 'boolean', 'system', 'Enable automatic backups'],
            ['auto_backup_frequency', 'weekly', 'text', 'system', 'Backup frequency'],
            ['log_retention_days', '30', 'number', 'system', 'Log retention days'],
            ['error_reporting', 'E_ALL', 'text', 'system', 'Error reporting level'],
            ['cache_enabled', 'false', 'boolean', 'system', 'Enable caching']
        ];
        
        foreach ($default_settings as $data) {
            $stmt = $db->prepare("INSERT INTO system_settings (setting_key, setting_value, setting_type, category, description) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$data[0], $data[1], $data[2], $data[3], $data[4]]);
        }
    }
    
    // Fetch all settings - FIXED: Use correct column name
    $stmt = $db->query("SELECT * FROM system_settings ORDER BY category, setting_key");
    if ($stmt->rowCount() > 0) {
        while ($row = $stmt->fetch()) {
            $settings[$row['setting_key']] = [
                'value' => $row['setting_value'],
                'type' => $row['setting_type'] ?? 'text',
                'category' => $row['category'] ?? 'general',
                'description' => $row['description'] ?? ''
            ];
        }
    }
    
} catch (PDOException $e) {
    $error = "Failed to load settings: " . $e->getMessage();
}

// =============================================
// GROUP SETTINGS BY CATEGORY
// =============================================

$grouped_settings = [];
foreach ($settings as $key => $data) {
    $category = isset($data['category']) ? $data['category'] : 'general';
    if (!isset($grouped_settings[$category])) {
        $grouped_settings[$category] = [];
    }
    $grouped_settings[$category][$key] = $data;
}

$category_labels = [
    'general' => ['icon' => 'fa-globe', 'label' => 'General Settings'],
    'payment' => ['icon' => 'fa-money-bill-wave', 'label' => 'Payment & Tax Settings'],
    'business' => ['icon' => 'fa-store', 'label' => 'Business Settings'],
    'features' => ['icon' => 'fa-toggle-on', 'label' => 'Feature Toggles'],
    'security' => ['icon' => 'fa-shield-alt', 'label' => 'Security Settings'],
    'email' => ['icon' => 'fa-envelope', 'label' => 'Email Settings'],
    'api' => ['icon' => 'fa-code', 'label' => 'API Settings'],
    'seo' => ['icon' => 'fa-google', 'label' => 'SEO Settings'],
    'content' => ['icon' => 'fa-newspaper', 'label' => 'Content Settings'],
    'affiliate' => ['icon' => 'fa-handshake', 'label' => 'Affiliate Settings'],
    'system' => ['icon' => 'fa-server', 'label' => 'System Settings']
];

// =============================================
// PROCESS SETTINGS UPDATE - FULL SYSTEM CONTROL
// =============================================

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action == 'update_settings') {
        $posted_settings = $_POST['settings'] ?? [];
        
        try {
            $db->beginTransaction();
            
            foreach ($posted_settings as $key => $value) {
                if (isset($settings[$key])) {
                    $type = $settings[$key]['type'] ?? 'text';
                    
                    // Process based on type
                    if ($type == 'boolean') {
                        $value = $value == 'true' ? 'true' : 'false';
                    } elseif ($type == 'number') {
                        $value = (float)$value;
                    } elseif ($type == 'json') {
                        $value = trim($value);
                    } elseif ($type == 'array') {
                        if (is_array($value)) {
                            $value = json_encode($value);
                        }
                    } else {
                        $value = trim($value);
                    }
                    
                    $stmt = $db->prepare("UPDATE system_settings SET setting_value = ?, updated_by = ?, updated_at = NOW() WHERE setting_key = ?");
                    $stmt->execute([$value, $user_id, $key]);
                }
            }
            
            $db->commit();
            
            logActivity($user_id, 'Updated System Settings', 'settings', 0);
            $success = "All settings updated successfully!";
            
            // Refresh settings
            $stmt = $db->query("SELECT * FROM system_settings");
            $settings = [];
            while ($row = $stmt->fetch()) {
                $settings[$row['setting_key']] = [
                    'value' => $row['setting_value'],
                    'type' => $row['setting_type'] ?? 'text',
                    'category' => $row['category'] ?? 'general',
                    'description' => $row['description'] ?? ''
                ];
            }
            
        } catch (PDOException $e) {
            $db->rollBack();
            $error = "Failed to update settings: " . $e->getMessage();
        }
    }
    
    // =============================================
    // SYSTEM ACTIONS
    // =============================================
    
    if ($action == 'clear_cache') {
        $cache_dir = '../../cache/';
        if (is_dir($cache_dir)) {
            $files = glob($cache_dir . '*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
        }
        
        logActivity($user_id, 'Cleared System Cache', 'system', 0);
        $_SESSION['success'] = "System cache cleared successfully!";
        header("Location: general-settings.php");
        exit();
    }
    
    if ($action == 'reset_settings') {
        try {
            $db->beginTransaction();
            
            // Delete all settings
            $db->exec("DELETE FROM system_settings");
            
            // Reinsert defaults
            $default_settings = [
                ['site_name', 'TechProcure Tanzania', 'text', 'general', 'Site name'],
                ['site_tagline', 'Enterprise IT Procurement Platform', 'text', 'general', 'Site tagline'],
                ['currency', 'TSh', 'text', 'payment', 'Default currency'],
                ['tax_rate', '18.00', 'number', 'payment', 'Tax rate percentage'],
                ['escrow_enabled', 'true', 'boolean', 'features', 'Enable escrow payment system'],
                ['bulk_discount_enabled', 'true', 'boolean', 'features', 'Enable bulk discount system'],
                ['rfq_enabled', 'true', 'boolean', 'features', 'Enable RFQ system'],
                ['maintenance_mode', 'false', 'boolean', 'features', 'Put site in maintenance mode'],
                ['allow_registration', 'true', 'boolean', 'features', 'Allow new user registration'],
                ['session_timeout', '7200', 'number', 'security', 'Session timeout in seconds'],
                ['commission_rate', '2.50', 'number', 'business', 'Commission rate for suppliers']
            ];
            
            foreach ($default_settings as $data) {
                $stmt = $db->prepare("INSERT INTO system_settings (setting_key, setting_value, setting_type, category, description) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$data[0], $data[1], $data[2], $data[3], $data[4]]);
            }
            
            $db->commit();
            
            logActivity($user_id, 'Reset All Settings', 'system', 0);
            $_SESSION['success'] = "All settings reset to defaults successfully!";
            header("Location: general-settings.php");
            exit();
            
        } catch (PDOException $e) {
            $db->rollBack();
            $_SESSION['error'] = "Failed to reset settings: " . $e->getMessage();
            header("Location: general-settings.php");
            exit();
        }
    }
    
    if ($action == 'backup_database') {
        try {
            $backup_dir = '../../database/backups/';
            if (!is_dir($backup_dir)) {
                mkdir($backup_dir, 0777, true);
            }
            
            $filename = 'backup_' . date('Y-m-d_H-i-s') . '.sql';
            $filepath = $backup_dir . $filename;
            
            $tables = $db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
            
            $output = "-- TechProcure Database Backup\n";
            $output .= "-- Generated: " . date('Y-m-d H:i:s') . "\n\n";
            $output .= "SET FOREIGN_KEY_CHECKS=0;\n\n";
            
            foreach ($tables as $table) {
                $create = $db->query("SHOW CREATE TABLE $table")->fetch();
                $output .= "DROP TABLE IF EXISTS $table;\n";
                $output .= $create['Create Table'] . ";\n\n";
                
                $data = $db->query("SELECT * FROM $table");
                if ($data->rowCount() > 0) {
                    $rows = $data->fetchAll(PDO::FETCH_ASSOC);
                    foreach ($rows as $row) {
                        $values = array_map(function($v) {
                            if ($v === null) return 'NULL';
                            return "'" . addslashes($v) . "'";
                        }, array_values($row));
                        $output .= "INSERT INTO $table VALUES (" . implode(',', $values) . ");\n";
                    }
                    $output .= "\n";
                }
            }
            
            $output .= "SET FOREIGN_KEY_CHECKS=1;\n";
            file_put_contents($filepath, $output);
            
            logActivity($user_id, 'Created Database Backup', 'system', 0);
            $_SESSION['success'] = "Database backup created successfully: " . $filename;
            
        } catch (Exception $e) {
            $_SESSION['error'] = "Failed to create backup: " . $e->getMessage();
        }
        header("Location: general-settings.php");
        exit();
    }
    
    if ($action == 'clear_all_data') {
        try {
            $db->beginTransaction();
            
            $db->exec("DELETE FROM order_tracking");
            $db->exec("DELETE FROM payments");
            $db->exec("DELETE FROM escrow_payments");
            $db->exec("DELETE FROM order_items");
            $db->exec("DELETE FROM orders");
            $db->exec("DELETE FROM quotations");
            $db->exec("DELETE FROM rfqs");
            $db->exec("DELETE FROM procurement_requests");
            $db->exec("DELETE FROM product_images");
            $db->exec("DELETE FROM product_specifications");
            $db->exec("DELETE FROM products");
            $db->exec("DELETE FROM suppliers WHERE user_id NOT IN (SELECT id FROM users WHERE role_id = 1)");
            $db->exec("DELETE FROM companies WHERE id NOT IN (SELECT company_id FROM users WHERE role_id = 1)");
            $db->exec("DELETE FROM users WHERE role_id != 1");
            
            $db->commit();
            
            logActivity($user_id, 'Cleared All Data', 'system', 0);
            $_SESSION['success'] = "All data cleared successfully! Admins preserved.";
            
        } catch (Exception $e) {
            $db->rollBack();
            $_SESSION['error'] = "Failed to clear data: " . $e->getMessage();
        }
        header("Location: general-settings.php");
        exit();
    }
    
    if ($action == 'toggle_maintenance') {
        try {
            $current = ($settings['maintenance_mode']['value'] ?? 'false') == 'true' ? 'false' : 'true';
            $stmt = $db->prepare("UPDATE system_settings SET setting_value = ?, updated_by = ?, updated_at = NOW() WHERE setting_key = 'maintenance_mode'");
            $stmt->execute([$current, $user_id]);
            
            logActivity($user_id, 'Toggled Maintenance Mode', 'system', 0);
            $_SESSION['success'] = "Maintenance mode " . ($current == 'true' ? 'enabled' : 'disabled');
            
        } catch (Exception $e) {
            $_SESSION['error'] = "Failed to toggle maintenance: " . $e->getMessage();
        }
        header("Location: general-settings.php");
        exit();
    }
}

// =============================================
// HELPER FUNCTIONS
// =============================================

function renderSettingField($key, $data) {
    $value = $data['value'] ?? '';
    $type = $data['type'] ?? 'text';
    $description = $data['description'] ?? '';
    
    $html = '<div class="col-md-6 mb-3">';
    $html .= '<label class="form-label">' . ucwords(str_replace('_', ' ', $key)) . '</label>';
    
    if ($type == 'boolean') {
        $checked = $value == 'true' ? 'checked' : '';
        $html .= '<div class="form-check form-switch">';
        $html .= '<input type="hidden" name="settings[' . $key . ']" value="false">';
        $html .= '<input type="checkbox" name="settings[' . $key . ']" value="true" class="form-check-input" id="' . $key . '" ' . $checked . '>';
        $html .= '<label class="form-check-label" for="' . $key . '">Enabled</label>';
        $html .= '</div>';
    } elseif ($type == 'json') {
        $html .= '<textarea name="settings[' . $key . ']" class="form-control" rows="3">' . htmlspecialchars($value) . '</textarea>';
    } elseif ($type == 'number') {
        $html .= '<input type="number" name="settings[' . $key . ']" class="form-control" step="0.01" value="' . htmlspecialchars($value) . '">';
    } elseif ($type == 'file') {
        $html .= '<input type="file" name="settings[' . $key . ']" class="form-control" accept="image/*">';
        if (!empty($value)) {
            $html .= '<small class="text-muted d-block mt-1">Current: ' . htmlspecialchars($value) . '</small>';
        }
    } else {
        $html .= '<input type="text" name="settings[' . $key . ']" class="form-control" value="' . htmlspecialchars($value) . '">';
    }
    
    if (!empty($description)) {
        $html .= '<small class="text-muted">' . htmlspecialchars($description) . '</small>';
    }
    
    $html .= '</div>';
    return $html;
}

function renderCategorySection($category, $settings) {
    global $category_labels;
    $label = $category_labels[$category] ?? ['icon' => 'fa-cog', 'label' => ucfirst($category)];
    
    $html = '<div class="settings-card">';
    $html .= '<div class="card-title">';
    $html .= '<i class="fas ' . $label['icon'] . '"></i> ' . $label['label'];
    $html .= '</div>';
    $html .= '<div class="row">';
    
    foreach ($settings as $key => $data) {
        $html .= renderSettingField($key, $data);
    }
    
    $html .= '</div>';
    $html .= '</div>';
    
    return $html;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Settings - TechProcure Tanzania</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f8f9fa;
        }
        
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            width: 280px;
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            color: white;
            z-index: 1030;
            overflow-y: auto;
            transition: all 0.3s;
        }
        
        .sidebar .brand {
            padding: 24px 20px;
            font-size: 1.4rem;
            font-weight: 700;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            text-align: center;
        }
        
        .sidebar .brand i {
            margin-right: 10px;
        }
        
        .sidebar .brand small {
            display: block;
            font-size: 0.7rem;
            opacity: 0.7;
            font-weight: 400;
        }
        
        .sidebar .nav-item {
            padding: 0 12px;
            margin: 4px 0;
        }
        
        .sidebar .nav-link {
            color: rgba(255,255,255,0.8);
            padding: 12px 15px;
            border-radius: 10px;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            text-decoration: none;
        }
        
        .sidebar .nav-link:hover {
            background: rgba(255,255,255,0.1);
            color: white;
        }
        
        .sidebar .nav-link.active {
            background: #0d6efd;
            color: white;
        }
        
        .sidebar .nav-link i {
            width: 24px;
            margin-right: 12px;
        }
        
        .sidebar .sidebar-footer {
            position: absolute;
            bottom: 0;
            width: 100%;
            padding: 15px;
            border-top: 1px solid rgba(255,255,255,0.1);
        }
        
        .sidebar .sidebar-footer a {
            color: rgba(255,255,255,0.7);
            text-decoration: none;
            display: block;
            text-align: center;
            padding: 5px;
        }
        
        .sidebar .sidebar-footer a:hover {
            color: white;
        }
        
        .main-content {
            margin-left: 280px;
            padding: 20px;
            min-height: 100vh;
        }
        
        .top-navbar {
            background: white;
            border-radius: 15px;
            padding: 15px 25px;
            margin-bottom: 24px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .top-navbar .admin-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #0d6efd;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 1.1rem;
        }
        
        .settings-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 24px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        
        .settings-card .card-title {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #e9ecef;
        }
        
        .settings-card .card-title i {
            margin-right: 8px;
            color: #0d6efd;
        }
        
        .settings-card .form-label {
            font-weight: 500;
            font-size: 0.85rem;
        }
        
        .settings-card .form-control,
        .settings-card .form-select {
            border-radius: 10px;
            padding: 10px 15px;
            border: 1px solid #e0e0e0;
            transition: all 0.3s;
        }
        
        .settings-card .form-control:focus,
        .settings-card .form-select:focus {
            border-color: #0d6efd;
            box-shadow: 0 0 0 3px rgba(13,110,253,0.1);
            outline: none;
        }
        
        .btn-save {
            background: linear-gradient(135deg, #0d6efd, #0b5ed7);
            border: none;
            border-radius: 10px;
            padding: 10px 25px;
            font-weight: 600;
            color: white;
            transition: all 0.3s;
        }
        
        .btn-save:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(13,110,253,0.3);
        }
        
        .btn-danger-zone {
            background: linear-gradient(135deg, #dc3545, #c82333);
            border: none;
            border-radius: 10px;
            padding: 10px 25px;
            font-weight: 600;
            color: white;
            transition: all 0.3s;
        }
        
        .btn-danger-zone:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(220,53,69,0.3);
        }
        
        .danger-zone {
            border: 2px solid #dc3545;
            border-radius: 15px;
            padding: 20px;
            background: #fff5f5;
        }
        
        .danger-zone .danger-title {
            color: #dc3545;
            font-weight: 600;
            margin-bottom: 10px;
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
            .sidebar {
                transform: translateX(-100%);
            }
            .sidebar.show {
                transform: translateX(0);
            }
            .main-content {
                margin-left: 0;
            }
            .settings-card {
                padding: 20px;
            }
        }
    </style>
</head>
<body>

<!-- Sidebar -->
<div class="sidebar" id="sidebar">
    <div class="brand">
        <i class="fas fa-microchip"></i> TechProcure
        <small>Admin Panel</small>
    </div>
    
    <div class="nav flex-column mt-3">
        <div class="nav-item">
            <a href="../dashboard.php" class="nav-link">
                <i class="fas fa-tachometer-alt"></i> Dashboard
            </a>
        </div>
        <div class="nav-item">
            <a href="../users/manage-users.php" class="nav-link">
                <i class="fas fa-users"></i> Users
            </a>
        </div>
        <div class="nav-item">
            <a href="../suppliers/manage-suppliers.php" class="nav-link">
                <i class="fas fa-truck"></i> Suppliers
            </a>
        </div>
        <div class="nav-item">
            <a href="../products/manage-products.php" class="nav-link">
                <i class="fas fa-box"></i> Products
            </a>
        </div>
        <div class="nav-item">
            <a href="../orders/manage-orders.php" class="nav-link">
                <i class="fas fa-shopping-cart"></i> Orders
            </a>
        </div>
        <div class="nav-item">
            <a href="../payments/transactions.php" class="nav-link">
                <i class="fas fa-credit-card"></i> Payments
            </a>
        </div>
        <div class="nav-item">
            <a href="../reports/sales-report.php" class="nav-link">
                <i class="fas fa-chart-line"></i> Reports
            </a>
        </div>
        <div class="nav-item">
            <a href="general-settings.php" class="nav-link active">
                <i class="fas fa-cog"></i> System Settings
            </a>
        </div>
    </div>
    
    <div class="sidebar-footer">
        <a href="../../auth/logout.php">
            <i class="fas fa-sign-out-alt me-2"></i> Logout
        </a>
    </div>
</div>

<!-- Main Content -->
<div class="main-content" id="mainContent">
    <div class="top-navbar">
        <div class="welcome-text">
            <i class="fas fa-cog me-2 text-primary"></i> System Settings
        </div>
        <div class="d-flex align-items-center gap-3">
            <span class="badge bg-primary">Full System Control</span>
            <div class="admin-avatar">
                <?php echo strtoupper(substr($_SESSION['user_name'] ?? 'A', 0, 1)); ?>
            </div>
        </div>
    </div>
    
    <?php if($success): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="fas fa-check-circle me-2"></i> <?php echo $success; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <?php if($error): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="fas fa-exclamation-triangle me-2"></i> <?php echo $error; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <?php if(isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="fas fa-check-circle me-2"></i> <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <?php if(isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="fas fa-exclamation-triangle me-2"></i> <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <?php if($table_status == 'updated' || $table_status == 'created'): ?>
        <div class="alert alert-info">
            <i class="fas fa-info-circle me-2"></i> 
            Database table structure has been automatically updated. 
            <?php if($table_status == 'created'): ?>
                Default settings have been installed.
            <?php else: ?>
                Missing columns have been added.
            <?php endif; ?>
        </div>
    <?php endif; ?>
    
    <!-- Settings Form -->
    <form method="POST" action="" enctype="multipart/form-data">
        <input type="hidden" name="action" value="update_settings">
        
        <?php
        $category_order = ['general', 'payment', 'business', 'features', 'security', 'email', 'api', 'seo', 'content', 'affiliate', 'system'];
        foreach ($category_order as $category) {
            if (isset($grouped_settings[$category])) {
                echo renderCategorySection($category, $grouped_settings[$category]);
            }
        }
        ?>
        
        <div class="d-flex gap-3">
            <button type="submit" class="btn-save">
                <i class="fas fa-save me-2"></i> Save All Settings
            </button>
        </div>
    </form>
    
    <!-- System Actions -->
    <div class="settings-card">
        <div class="card-title">
            <i class="fas fa-tools"></i> System Actions
        </div>
        <div class="row g-3">
            <div class="col-md-4">
                <form method="POST">
                    <input type="hidden" name="action" value="backup_database">
                    <button type="submit" class="btn btn-success w-100">
                        <i class="fas fa-database me-2"></i> Backup Database
                    </button>
                </form>
            </div>
            <div class="col-md-4">
                <form method="POST">
                    <input type="hidden" name="action" value="clear_cache">
                    <button type="submit" class="btn btn-warning w-100" onclick="return confirm('Clear all system cache?')">
                        <i class="fas fa-broom me-2"></i> Clear Cache
                    </button>
                </form>
            </div>
            <div class="col-md-4">
                <form method="POST">
                    <input type="hidden" name="action" value="reset_settings">
                    <button type="submit" class="btn btn-danger w-100" onclick="return confirm('Reset ALL settings to defaults? This cannot be undone!')">
                        <i class="fas fa-undo me-2"></i> Reset All Settings
                    </button>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Danger Zone -->
    <div class="danger-zone">
        <div class="danger-title">
            <i class="fas fa-exclamation-triangle me-2"></i> Danger Zone
        </div>
        <p class="text-muted small">These actions are irreversible. Please proceed with caution.</p>
        <div class="d-flex gap-3 flex-wrap">
            <button type="button" class="btn-danger-zone" data-bs-toggle="modal" data-bs-target="#clearAllModal">
                <i class="fas fa-trash me-2"></i> Clear All Data
            </button>
            <form method="POST" style="display: inline;">
                <input type="hidden" name="action" value="toggle_maintenance">
                <button type="submit" class="btn-danger-zone">
                    <i class="fas fa-power-off me-2"></i> Toggle Maintenance
                </button>
            </form>
        </div>
    </div>
</div>

<!-- Clear All Data Modal -->
<div class="modal fade" id="clearAllModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title"><i class="fas fa-exclamation-triangle me-2"></i>Clear All Data</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="text-danger"><strong>Warning:</strong> This will permanently delete ALL data!</p>
                <p>This action will remove:</p>
                <ul>
                    <li>All users (except admins)</li>
                    <li>All products</li>
                    <li>All orders</li>
                    <li>All payments</li>
                    <li>All supplier data</li>
                    <li>All reviews and ratings</li>
                </ul>
                <p>Type <strong>CONFIRM</strong> to proceed:</p>
                <input type="text" id="confirmInput" class="form-control" placeholder="Type CONFIRM">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <form method="POST">
                    <input type="hidden" name="action" value="clear_all_data">
                    <button type="submit" class="btn btn-danger" onclick="return validateConfirm()">Clear All Data</button>
                </form>
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
                <h6>Admin</h6>
                <ul class="list-unstyled">
                    <li><a href="../dashboard.php">Dashboard</a></li>
                    <li><a href="general-settings.php">Settings</a></li>
                    <li><a href="../reports/sales-report.php">Reports</a></li>
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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<script>
    function validateConfirm() {
        const input = document.getElementById('confirmInput');
        if (input && input.value === 'CONFIRM') {
            return true;
        } else {
            alert('Please type CONFIRM to proceed.');
            return false;
        }
    }
    
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