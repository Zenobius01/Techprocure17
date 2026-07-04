<?php
/**
 * TechProcure Tanzania - FAQ Page
 * File: faq.php
 * Description: Complete Frequently Asked Questions page with search, categories, admin features, and more
 * Version: 1.0
 */

// =====================================================
// INITIALIZATION
// =====================================================
session_start();

// =====================================================
// DATABASE CONFIGURATION
// =====================================================
$db_host = 'localhost';
$db_name = 'techprocure_db';
$db_user = 'root';
$db_pass = '';

// =====================================================
// SITE CONFIGURATION
// =====================================================
define('SITE_NAME', 'TechProcure Tanzania');
define('SITE_URL', 'http://localhost/Techprocure17/');
define('SITE_PHONE', '+255700000000');
define('SITE_EMAIL', 'info@techprocure.co.tz');
define('SITE_ADDRESS', 'Dar es Salaam, Tanzania');

// =====================================================
// DATABASE CONNECTION
// =====================================================
try {
    $db = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Database Connection Failed: " . $e->getMessage());
}

// =====================================================
// FUNCTIONS
// =====================================================
function sanitize($input) {
    return htmlspecialchars(strip_tags(trim($input)));
}

function slugify($text) {
    if (!$text) return '';
    $text = preg_replace('/[^A-Za-z0-9-]+/', '-', $text);
    return strtolower(trim($text, '-'));
}

function get_status_badge($status) {
    $badges = [
        'active' => 'success',
        'inactive' => 'secondary',
        'pending' => 'warning',
        'approved' => 'success',
        'rejected' => 'danger'
    ];
    $color = $badges[$status] ?? 'secondary';
    return '<span class="badge bg-' . $color . '">' . ucfirst($status) . '</span>';
}

function time_ago($datetime) {
    if (!$datetime) return 'Never';
    $timestamp = strtotime($datetime);
    $difference = time() - $timestamp;
    
    $periods = [
        31536000 => 'year',
        2592000 => 'month',
        604800 => 'week',
        86400 => 'day',
        3600 => 'hour',
        60 => 'minute',
        1 => 'second'
    ];
    
    foreach ($periods as $seconds => $period) {
        if ($difference >= $seconds) {
            $count = floor($difference / $seconds);
            return $count . ' ' . $period . ($count > 1 ? 's' : '') . ' ago';
        }
    }
    return 'just now';
}

// =====================================================
// GET FILTER PARAMETERS
// =====================================================
$page_title = 'Frequently Asked Questions - ' . SITE_NAME;
$meta_description = 'Find answers to common questions about TechProcure Tanzania. Learn about buying, selling, payments, delivery, and more.';
$category = isset($_GET['category']) ? sanitize($_GET['category']) : '';
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$items_per_page = 10;
$offset = ($page - 1) * $items_per_page;

// =====================================================
// FETCH FAQS FROM DATABASE
// =====================================================
try {
    // Build query for FAQs
    $sql = "SELECT * FROM faqs WHERE status = 'active'";
    $params = [];
    
    if ($category) {
        $sql .= " AND category = ?";
        $params[] = $category;
    }
    
    if ($search) {
        $sql .= " AND (question LIKE ? OR answer LIKE ?)";
        $search_param = "%$search%";
        $params[] = $search_param;
        $params[] = $search_param;
    }
    
    $sql .= " ORDER BY sort_order ASC, created_at DESC";
    
    // Execute query
    $stmt = $db->prepare($sql);
    if (!empty($params)) {
        $stmt->execute($params);
    } else {
        $stmt->execute();
    }
    $faqs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get total count for pagination
    $total_faqs = count($faqs);
    $total_pages = ceil($total_faqs / $items_per_page);
    
    // Apply pagination
    $faqs = array_slice($faqs, $offset, $items_per_page);
    
    // Fetch categories
    $sql = "SELECT DISTINCT category FROM faqs WHERE status = 'active' AND category IS NOT NULL AND category != '' ORDER BY category";
    $stmt = $db->query($sql);
    $categories = [];
    if ($stmt) {
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if (!empty($row['category'])) {
                $categories[] = $row['category'];
            }
        }
    }
    
    // Get category counts
    $category_counts = [];
    foreach ($categories as $cat) {
        $sql = "SELECT COUNT(*) as count FROM faqs WHERE status = 'active' AND category = ?";
        $stmt = $db->prepare($sql);
        $stmt->execute([$cat]);
        $category_counts[$cat] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    }
    
    // Group FAQs by category
    $faqs_by_category = [];
    foreach ($faqs as $faq) {
        $cat = !empty($faq['category']) ? $faq['category'] : 'General';
        if (!isset($faqs_by_category[$cat])) {
            $faqs_by_category[$cat] = [];
        }
        $faqs_by_category[$cat][] = $faq;
    }
    
} catch (PDOException $e) {
    $faqs = [];
    $categories = [];
    $faqs_by_category = [];
    $total_faqs = 0;
    $total_pages = 0;
    error_log("FAQ Query Error: " . $e->getMessage());
}

// =====================================================
// HANDLE ADMIN ACTIONS (if logged in as admin)
// =====================================================
$is_admin = isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'admin';
$message = '';
$message_type = '';

if ($is_admin && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add_faq') {
        $question = sanitize($_POST['question'] ?? '');
        $answer = sanitize($_POST['answer'] ?? '');
        $faq_category = sanitize($_POST['faq_category'] ?? 'General');
        $sort_order = (int)($_POST['sort_order'] ?? 0);
        
        if ($question && $answer) {
            try {
                $sql = "INSERT INTO faqs (category, question, answer, sort_order, status) VALUES (?, ?, ?, ?, 'active')";
                $stmt = $db->prepare($sql);
                $stmt->execute([$faq_category, $question, $answer, $sort_order]);
                $message = 'FAQ added successfully!';
                $message_type = 'success';
            } catch (PDOException $e) {
                $message = 'Error adding FAQ: ' . $e->getMessage();
                $message_type = 'danger';
            }
        } else {
            $message = 'Question and answer are required';
            $message_type = 'danger';
        }
    }
    
    if ($action === 'edit_faq') {
        $faq_id = (int)($_POST['faq_id'] ?? 0);
        $question = sanitize($_POST['question'] ?? '');
        $answer = sanitize($_POST['answer'] ?? '');
        $faq_category = sanitize($_POST['faq_category'] ?? 'General');
        $sort_order = (int)($_POST['sort_order'] ?? 0);
        $status = sanitize($_POST['status'] ?? 'active');
        
        if ($faq_id && $question && $answer) {
            try {
                $sql = "UPDATE faqs SET category = ?, question = ?, answer = ?, sort_order = ?, status = ? WHERE id = ?";
                $stmt = $db->prepare($sql);
                $stmt->execute([$faq_category, $question, $answer, $sort_order, $status, $faq_id]);
                $message = 'FAQ updated successfully!';
                $message_type = 'success';
            } catch (PDOException $e) {
                $message = 'Error updating FAQ: ' . $e->getMessage();
                $message_type = 'danger';
            }
        } else {
            $message = 'All fields are required';
            $message_type = 'danger';
        }
    }
    
    if ($action === 'delete_faq') {
        $faq_id = (int)($_POST['faq_id'] ?? 0);
        if ($faq_id) {
            try {
                $sql = "DELETE FROM faqs WHERE id = ?";
                $stmt = $db->prepare($sql);
                $stmt->execute([$faq_id]);
                $message = 'FAQ deleted successfully!';
                $message_type = 'success';
            } catch (PDOException $e) {
                $message = 'Error deleting FAQ: ' . $e->getMessage();
                $message_type = 'danger';
            }
        }
    }
    
    if ($action === 'toggle_status') {
        $faq_id = (int)($_POST['faq_id'] ?? 0);
        $current_status = sanitize($_POST['current_status'] ?? 'active');
        $new_status = $current_status === 'active' ? 'inactive' : 'active';
        
        if ($faq_id) {
            try {
                $sql = "UPDATE faqs SET status = ? WHERE id = ?";
                $stmt = $db->prepare($sql);
                $stmt->execute([$new_status, $faq_id]);
                $message = 'FAQ status updated successfully!';
                $message_type = 'success';
            } catch (PDOException $e) {
                $message = 'Error updating status: ' . $e->getMessage();
                $message_type = 'danger';
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?php echo $meta_description; ?>">
    <meta name="keywords" content="FAQ, frequently asked questions, TechProcure Tanzania, IT procurement, help, support">
    <meta name="robots" content="index, follow">
    <meta property="og:title" content="<?php echo $page_title; ?>">
    <meta property="og:description" content="<?php echo $meta_description; ?>">
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?php echo SITE_URL; ?>faq.php">
    <title><?php echo $page_title; ?></title>
    
    <!-- CSS Dependencies -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        /* =====================================================
           GLOBAL STYLES
           ===================================================== */
        :root {
            --primary-color: #0d6efd;
            --secondary-color: #6c757d;
            --dark-color: #212529;
            --light-color: #f8f9fa;
            --border-radius: 12px;
            --box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            --transition: all 0.3s ease;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f7fb;
            color: #333;
            line-height: 1.6;
        }
        
        a {
            text-decoration: none;
            transition: var(--transition);
        }
        
        /* =====================================================
           NAVBAR
           ===================================================== */
        .navbar-custom {
            background: var(--dark-color);
            padding: 1rem 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            position: sticky;
            top: 0;
            z-index: 1000;
        }
        
        .navbar-custom .navbar-brand {
            color: white;
            font-size: 1.5rem;
            font-weight: 700;
            text-decoration: none;
            transition: var(--transition);
        }
        
        .navbar-custom .navbar-brand:hover {
            color: #0d6efd;
        }
        
        .navbar-custom .navbar-brand i {
            color: #0d6efd;
        }
        
        .navbar-custom .nav-link {
            color: rgba(255,255,255,0.8);
            text-decoration: none;
            padding: 0.5rem 1rem;
            transition: var(--transition);
            font-weight: 500;
        }
        
        .navbar-custom .nav-link:hover {
            color: white;
        }
        
        .navbar-custom .nav-link.active {
            color: white;
            background: rgba(13, 110, 253, 0.2);
            border-radius: 8px;
        }
        
        .navbar-custom .btn-outline-light {
            color: white;
            border-color: rgba(255,255,255,0.3);
            transition: var(--transition);
        }
        
        .navbar-custom .btn-outline-light:hover {
            background: white;
            color: var(--dark-color);
        }
        
        .navbar-custom .btn-primary {
            background: #0d6efd;
            border: none;
            transition: var(--transition);
        }
        
        .navbar-custom .btn-primary:hover {
            background: #0b5ed7;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(13, 110, 253, 0.3);
        }
        
        .navbar-toggler-custom {
            background: transparent;
            border: 1px solid rgba(255,255,255,0.3);
            color: white;
            padding: 8px 12px;
            border-radius: 8px;
            cursor: pointer;
        }
        
        .navbar-toggler-custom:hover {
            background: rgba(255,255,255,0.1);
        }
        
        /* =====================================================
           PAGE HEADER
           ===================================================== */
        .page-header {
            background: linear-gradient(135deg, #1a2a6c, #b21f1f, #fdbb4d);
            background-size: 200% 200%;
            padding: 80px 0;
            animation: gradientBG 15s ease infinite;
            position: relative;
            overflow: hidden;
        }
        
        .page-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.2);
            z-index: 1;
        }
        
        .page-header .container {
            position: relative;
            z-index: 2;
        }
        
        @keyframes gradientBG {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }
        
        .page-header h1 {
            color: white;
            font-weight: 800;
        }
        
        .page-header p {
            color: rgba(255,255,255,0.9);
            font-size: 1.2rem;
        }
        
        .page-header .breadcrumb {
            background: transparent;
            padding: 0;
        }
        
        .page-header .breadcrumb-item a {
            color: rgba(255,255,255,0.8);
            text-decoration: none;
        }
        
        .page-header .breadcrumb-item a:hover {
            color: white;
        }
        
        .page-header .breadcrumb-item.active {
            color: white;
        }
        
        .page-header .breadcrumb-item + .breadcrumb-item::before {
            color: rgba(255,255,255,0.6);
        }
        
        /* =====================================================
           FAQ SEARCH
           ===================================================== */
        .faq-search-box {
            max-width: 600px;
            margin: 30px auto 0;
        }
        
        .faq-search-box .input-group {
            background: white;
            border-radius: 50px;
            overflow: hidden;
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
        }
        
        .faq-search-box .form-control {
            border: none;
            padding: 15px 25px;
            font-size: 16px;
            border-radius: 50px 0 0 50px;
        }
        
        .faq-search-box .form-control:focus {
            box-shadow: none;
        }
        
        .faq-search-box .btn {
            padding: 15px 30px;
            border-radius: 0 50px 50px 0;
            font-weight: 600;
        }
        
        .faq-search-box .btn-clear {
            border-radius: 0;
            background: #f8f9fa;
            color: #6c757d;
        }
        
        .faq-search-box .btn-clear:hover {
            background: #e9ecef;
        }
        
        /* =====================================================
           CATEGORY FILTER
           ===================================================== */
        .category-filter {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            justify-content: center;
            margin: 30px 0;
        }
        
        .category-filter .btn {
            border-radius: 50px;
            padding: 10px 24px;
            font-size: 14px;
            font-weight: 500;
            transition: var(--transition);
        }
        
        .category-filter .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(13, 110, 253, 0.2);
        }
        
        .category-filter .btn.active {
            background: #0d6efd;
            color: white;
        }
        
        .category-filter .btn .badge {
            margin-left: 8px;
            background: rgba(255,255,255,0.3);
        }
        
        .category-filter .btn.active .badge {
            background: rgba(255,255,255,0.3);
        }
        
        /* =====================================================
           FAQ STATS
           ===================================================== */
        .faq-stats {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            justify-content: center;
            margin-bottom: 30px;
        }
        
        .faq-stats .stat-item {
            background: white;
            padding: 15px 25px;
            border-radius: 12px;
            box-shadow: var(--box-shadow);
            text-align: center;
            min-width: 120px;
        }
        
        .faq-stats .stat-item .stat-number {
            font-size: 1.8rem;
            font-weight: 700;
            color: #0d6efd;
            display: block;
        }
        
        .faq-stats .stat-item .stat-label {
            font-size: 0.85rem;
            color: #6c757d;
        }
        
        /* =====================================================
           FAQ ITEMS
           ===================================================== */
        .faq-section {
            margin-bottom: 50px;
        }
        
        .faq-section .category-title {
            font-size: 1.8rem;
            font-weight: 700;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 3px solid #0d6efd;
            display: inline-block;
            color: #333;
            position: relative;
        }
        
        .faq-section .category-title .category-count {
            font-size: 0.9rem;
            color: #6c757d;
            font-weight: 400;
            margin-left: 10px;
        }
        
        .faq-item {
            border: 1px solid #e0e0e0;
            border-radius: var(--border-radius);
            margin-bottom: 15px;
            overflow: hidden;
            transition: var(--transition);
            background: white;
        }
        
        .faq-item:hover {
            box-shadow: var(--box-shadow);
        }
        
        .faq-item .faq-id {
            font-size: 11px;
            color: #6c757d;
            margin-right: 10px;
        }
        
        .faq-question {
            padding: 18px 25px;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-weight: 500;
            transition: var(--transition);
            background: white;
            border: none;
            width: 100%;
            text-align: left;
            font-size: 16px;
            color: #333;
        }
        
        .faq-question:hover {
            background: #f8f9fa;
        }
        
        .faq-question .faq-icon {
            flex-shrink: 0;
            margin-right: 15px;
            color: #0d6efd;
            font-size: 18px;
        }
        
        .faq-question .question-text {
            flex: 1;
        }
        
        .faq-question .toggle-icon {
            flex-shrink: 0;
            margin-left: 15px;
            transition: transform 0.3s ease;
            color: #6c757d;
            font-size: 14px;
        }
        
        .faq-question.active .toggle-icon {
            transform: rotate(180deg);
        }
        
        .faq-question .faq-status {
            margin-right: 15px;
            font-size: 12px;
        }
        
        .faq-answer {
            padding: 0 25px;
            max-height: 0;
            overflow: hidden;
            transition: all 0.4s ease;
            background: #f8f9fa;
            border-top: 1px solid #e0e0e0;
        }
        
        .faq-answer.show {
            padding: 20px 25px;
            max-height: 2000px;
        }
        
        .faq-answer p {
            margin-bottom: 0;
            line-height: 1.8;
            color: #555;
        }
        
        .faq-answer .faq-meta {
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #e0e0e0;
            font-size: 12px;
            color: #6c757d;
        }
        
        .faq-answer .faq-meta i {
            margin-right: 5px;
        }
        
        /* =====================================================
           ADMIN CONTROLS
           ===================================================== */
        .admin-controls {
            margin: 20px 0;
            padding: 20px;
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
        }
        
        .admin-controls .btn {
            margin: 5px;
        }
        
        .admin-modal .modal-header {
            background: linear-gradient(135deg, #0d6efd, #0dcaf0);
            color: white;
        }
        
        .admin-modal .modal-header .btn-close {
            filter: brightness(0) invert(1);
        }
        
        /* =====================================================
           PAGINATION
           ===================================================== */
        .pagination-container {
            margin-top: 40px;
        }
        
        .pagination .page-link {
            border-radius: 8px;
            margin: 0 3px;
            color: #0d6efd;
            border: 1px solid #dee2e6;
            transition: var(--transition);
        }
        
        .pagination .page-link:hover {
            background: #0d6efd;
            color: white;
            border-color: #0d6efd;
        }
        
        .pagination .page-item.active .page-link {
            background: #0d6efd;
            border-color: #0d6efd;
            color: white;
        }
        
        /* =====================================================
           NO RESULTS
           ===================================================== */
        .no-results {
            text-align: center;
            padding: 80px 20px;
        }
        
        .no-results i {
            font-size: 5rem;
            color: #dee2e6;
            margin-bottom: 20px;
        }
        
        .no-results h3 {
            color: #333;
            margin-bottom: 10px;
            font-weight: 600;
        }
        
        .no-results p {
            color: #6c757d;
            max-width: 400px;
            margin: 0 auto 20px;
        }
        
        /* =====================================================
           CONTACT SUPPORT
           ===================================================== */
        .contact-support {
            background: white;
            border-radius: var(--border-radius);
            padding: 50px;
            text-align: center;
            margin-top: 50px;
            box-shadow: var(--box-shadow);
        }
        
        .contact-support .support-icon {
            font-size: 3rem;
            color: #0d6efd;
            margin-bottom: 20px;
        }
        
        .contact-support h3 {
            color: #333;
            margin-bottom: 15px;
            font-weight: 700;
        }
        
        .contact-support p {
            color: #6c757d;
            max-width: 500px;
            margin: 0 auto 25px;
        }
        
        .contact-support .btn {
            margin: 5px 8px;
            padding: 12px 30px;
            border-radius: 50px;
            font-weight: 500;
            transition: var(--transition);
        }
        
        .contact-support .btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
        }
        
        .contact-support .support-hours {
            margin-top: 20px;
            font-size: 14px;
            color: #6c757d;
        }
        
        .contact-support .support-hours i {
            color: #0d6efd;
        }
        
        /* =====================================================
           FOOTER
           ===================================================== */
        .footer-custom {
            background: var(--dark-color);
            color: white;
            padding: 60px 0 30px;
            margin-top: 60px;
        }
        
        .footer-custom h5, .footer-custom h6 {
            font-weight: 600;
            margin-bottom: 20px;
        }
        
        .footer-custom a {
            color: rgba(255,255,255,0.7);
            text-decoration: none;
            transition: var(--transition);
        }
        
        .footer-custom a:hover {
            color: white;
        }
        
        .footer-custom .text-muted {
            color: rgba(255,255,255,0.5) !important;
        }
        
        .footer-custom .social-icons a {
            display: inline-block;
            width: 40px;
            height: 40px;
            line-height: 40px;
            text-align: center;
            border-radius: 50%;
            background: rgba(255,255,255,0.1);
            color: white;
            margin-right: 10px;
            transition: var(--transition);
        }
        
        .footer-custom .social-icons a:hover {
            background: #0d6efd;
            transform: translateY(-3px);
        }
        
        .footer-custom .payment-icons img {
            height: 30px;
            background: white;
            padding: 3px 8px;
            border-radius: 4px;
            margin-right: 5px;
        }
        
        .footer-custom .footer-divider {
            border-color: rgba(255,255,255,0.1);
            margin: 30px 0;
        }
        
        /* =====================================================
           SCROLL TO TOP
           ===================================================== */
        .scroll-top {
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: #0d6efd;
            color: white;
            border: none;
            font-size: 20px;
            cursor: pointer;
            box-shadow: 0 5px 20px rgba(13, 110, 253, 0.3);
            transition: var(--transition);
            opacity: 0;
            visibility: hidden;
            z-index: 999;
        }
        
        .scroll-top.visible {
            opacity: 1;
            visibility: visible;
        }
        
        .scroll-top:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(13, 110, 253, 0.4);
        }
        
        /* =====================================================
           RESPONSIVE
           ===================================================== */
        @media (max-width: 992px) {
            .page-header {
                padding: 50px 0;
            }
            
            .page-header h1 {
                font-size: 2.5rem;
            }
        }
        
        @media (max-width: 768px) {
            .page-header {
                padding: 40px 0;
            }
            
            .page-header h1 {
                font-size: 1.8rem;
            }
            
            .page-header p {
                font-size: 1rem;
            }
            
            .faq-search-box .form-control {
                padding: 12px 18px;
                font-size: 14px;
            }
            
            .faq-search-box .btn {
                padding: 12px 20px;
            }
            
            .faq-question {
                padding: 15px 18px;
                font-size: 14px;
            }
            
            .faq-answer.show {
                padding: 15px 18px;
            }
            
            .category-filter .btn {
                font-size: 12px;
                padding: 6px 15px;
            }
            
            .contact-support {
                padding: 25px;
            }
            
            .faq-stats .stat-item {
                min-width: 80px;
                padding: 10px 15px;
            }
            
            .faq-stats .stat-item .stat-number {
                font-size: 1.3rem;
            }
            
            .footer-custom {
                padding: 40px 0 20px;
            }
            
            .scroll-top {
                bottom: 20px;
                right: 20px;
                width: 40px;
                height: 40px;
                font-size: 16px;
            }
        }
        
        @media (max-width: 576px) {
            .category-filter .btn {
                font-size: 11px;
                padding: 5px 12px;
            }
            
            .contact-support .btn {
                padding: 10px 20px;
                font-size: 14px;
            }
        }
        
        /* =====================================================
           PRINT STYLES
           ===================================================== */
        @media print {
            .navbar-custom, .footer-custom, .category-filter, .faq-search-box, .contact-support, .scroll-top, .admin-controls {
                display: none !important;
            }
            
            .page-header {
                background: #333 !important;
                padding: 30px 0 !important;
                animation: none !important;
            }
            
            .faq-item {
                break-inside: avoid;
                border: 1px solid #ddd !important;
            }
            
            .faq-answer {
                max-height: none !important;
                padding: 15px 25px !important;
                display: block !important;
            }
            
            .faq-question .toggle-icon {
                display: none !important;
            }
        }
    </style>
</head>
<body>

<!-- ===================================================== -->
<!-- NAVBAR -->
<!-- ===================================================== -->
<nav class="navbar-custom">
    <div class="container">
        <div class="d-flex justify-content-between align-items-center">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-microchip me-2"></i>TechProcure
            </a>
            
            <button class="navbar-toggler-custom d-md-none" type="button" onclick="toggleNavbar()">
                <i class="fas fa-bars"></i>
            </button>
            
            <div class="d-none d-md-flex align-items-center gap-2" id="navbarNav">
                <a class="nav-link" href="index.php">Home</a>
                <a class="nav-link" href="products.php">Products</a>
                <a class="nav-link" href="suppliers.php">Suppliers</a>
                <a class="nav-link active" href="faq.php">FAQ</a>
                <a class="nav-link" href="contact.php">Contact</a>
                <?php if (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'admin'): ?>
                <a class="nav-link" href="admin/dashboard.php">
                    <i class="fas fa-user-shield me-1"></i>Admin
                </a>
                <a class="nav-link" href="auth/logout.php">
                    <i class="fas fa-sign-out-alt me-1"></i>Logout
                </a>
                <?php elseif (isset($_SESSION['user_type'])): ?>
                <a class="nav-link" href="dashboard.php">
                    <i class="fas fa-user me-1"></i>Dashboard
                </a>
                <a class="nav-link" href="auth/logout.php">
                    <i class="fas fa-sign-out-alt me-1"></i>Logout
                </a>
                <?php else: ?>
                <a href="auth/login.php" class="btn btn-outline-light btn-sm">Login</a>
                <a href="auth/register.php" class="btn btn-primary btn-sm">Register</a>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Mobile Nav -->
        <div class="d-md-none mt-2" id="mobileNav" style="display: none;">
            <div class="d-flex flex-column gap-2 py-2">
                <a class="nav-link" href="index.php"><i class="fas fa-home me-2"></i>Home</a>
                <a class="nav-link" href="products.php"><i class="fas fa-box me-2"></i>Products</a>
                <a class="nav-link" href="suppliers.php"><i class="fas fa-store me-2"></i>Suppliers</a>
                <a class="nav-link active" href="faq.php"><i class="fas fa-question-circle me-2"></i>FAQ</a>
                <a class="nav-link" href="contact.php"><i class="fas fa-envelope me-2"></i>Contact</a>
                <hr class="border-secondary my-2">
                <?php if (isset($_SESSION['user_type'])): ?>
                <a class="nav-link" href="dashboard.php"><i class="fas fa-user me-2"></i>Dashboard</a>
                <a class="nav-link" href="auth/logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a>
                <?php else: ?>
                <a href="auth/login.php" class="btn btn-outline-light btn-sm">Login</a>
                <a href="auth/register.php" class="btn btn-primary btn-sm">Register</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>

<!-- ===================================================== -->
<!-- PAGE HEADER -->
<!-- ===================================================== -->
<section class="page-header text-white">
    <div class="container">
        <div class="row">
            <div class="col-lg-8 mx-auto text-center">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb justify-content-center mb-3">
                        <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                        <li class="breadcrumb-item active" aria-current="page">FAQ</li>
                    </ol>
                </nav>
                <h1 class="display-4 fw-bold mb-3">Frequently Asked Questions</h1>
                <p class="lead mb-0">Find answers to the most common questions about TechProcure Tanzania</p>
            </div>
        </div>
        
        <!-- Search Box -->
        <div class="faq-search-box">
            <form method="GET" action="">
                <div class="input-group">
                    <input type="text" 
                           name="search" 
                           class="form-control" 
                           placeholder="Search for answers..." 
                           value="<?php echo htmlspecialchars($search); ?>"
                           aria-label="Search FAQs">
                    <button class="btn btn-primary" type="submit">
                        <i class="fas fa-search"></i> Search
                    </button>
                    <?php if ($search): ?>
                    <a href="faq.php" class="btn btn-light btn-clear">
                        <i class="fas fa-times"></i> Clear
                    </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>
</section>

<!-- ===================================================== -->
<!-- MAIN CONTENT -->
<!-- ===================================================== -->
<section class="faq-content py-5">
    <div class="container">
        
        <!-- FAQ Stats -->
        <div class="faq-stats">
            <div class="stat-item">
                <span class="stat-number"><?php echo count($faqs); ?></span>
                <span class="stat-label">Total FAQs</span>
            </div>
            <div class="stat-item">
                <span class="stat-number"><?php echo count($categories); ?></span>
                <span class="stat-label">Categories</span>
            </div>
            <?php if ($search): ?>
            <div class="stat-item">
                <span class="stat-number">
                    <i class="fas fa-search text-primary"></i>
                </span>
                <span class="stat-label">Search Results for "<?php echo htmlspecialchars($search); ?>"</span>
            </div>
            <?php endif; ?>
            <?php if ($category): ?>
            <div class="stat-item">
                <span class="stat-number">
                    <i class="fas fa-tag text-primary"></i>
                </span>
                <span class="stat-label">Category: <?php echo htmlspecialchars($category); ?></span>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Category Filter -->
        <?php if (!empty($categories) && !$search): ?>
        <div class="category-filter">
            <a href="faq.php" class="btn btn-outline-primary <?php echo !$category ? 'active' : ''; ?>">
                <i class="fas fa-th-list me-1"></i>All
                <span class="badge bg-light text-dark"><?php echo count($faqs); ?></span>
            </a>
            <?php foreach($categories as $cat): ?>
            <a href="?category=<?php echo urlencode($cat); ?>" 
               class="btn btn-outline-primary <?php echo $category == $cat ? 'active' : ''; ?>">
                <i class="fas fa-tag me-1"></i><?php echo htmlspecialchars($cat); ?>
                <span class="badge bg-light text-dark"><?php echo $category_counts[$cat] ?? 0; ?></span>
            </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        
        <!-- Admin Controls -->
        <?php if ($is_admin): ?>
        <div class="admin-controls">
            <div class="d-flex flex-wrap align-items-center gap-2">
                <i class="fas fa-user-shield text-primary me-2"></i>
                <span class="fw-bold">Admin Controls</span>
                <span class="text-muted">|</span>
                <button class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#addFaqModal">
                    <i class="fas fa-plus me-1"></i>Add FAQ
                </button>
                <button class="btn btn-sm btn-info" onclick="location.reload()">
                    <i class="fas fa-sync me-1"></i>Refresh
                </button>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Message Alerts -->
        <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
            <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-circle'; ?> me-2"></i>
            <?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
        
        <!-- FAQ Results -->
        <?php if (empty($faqs)): ?>
        <div class="no-results">
            <i class="fas fa-search"></i>
            <h3>No FAQs Found</h3>
            <p class="text-muted">
                <?php if ($search): ?>
                No results found for "<strong><?php echo htmlspecialchars($search); ?></strong>"
                <?php elseif ($category): ?>
                No FAQs found in category "<strong><?php echo htmlspecialchars($category); ?></strong>"
                <?php else: ?>
                No FAQs available at the moment. Please check back later.
                <?php endif; ?>
            </p>
            <a href="faq.php" class="btn btn-primary">
                <i class="fas fa-undo me-2"></i>View All FAQs
            </a>
        </div>
        <?php else: ?>
        
        <!-- Display FAQs by Category -->
        <?php foreach($faqs_by_category as $cat_name => $cat_faqs): ?>
        <div class="faq-section">
            <h2 class="category-title">
                <i class="fas fa-folder-open me-2 text-primary"></i><?php echo htmlspecialchars($cat_name); ?>
                <span class="category-count">(<?php echo count($cat_faqs); ?> questions)</span>
            </h2>
            
            <div class="faq-list" id="faq-<?php echo slugify($cat_name); ?>">
                <?php foreach($cat_faqs as $index => $faq): ?>
                <div class="faq-item" data-category="<?php echo htmlspecialchars($faq['category']); ?>" data-id="<?php echo $faq['id']; ?>">
                    <button class="faq-question" 
                            data-target="faq-answer-<?php echo $faq['id']; ?>"
                            aria-expanded="false">
                        <span class="d-flex align-items-center">
                            <i class="fas fa-question-circle faq-icon"></i>
                            <span class="question-text">
                                <span class="faq-id">#<?php echo $faq['id']; ?></span>
                                <?php echo htmlspecialchars($faq['question']); ?>
                            </span>
                        </span>
                        <div class="d-flex align-items-center">
                            <?php if ($is_admin): ?>
                            <span class="faq-status"><?php echo get_status_badge($faq['status']); ?></span>
                            <?php endif; ?>
                            <i class="fas fa-chevron-down toggle-icon"></i>
                        </div>
                    </button>
                    <div class="faq-answer" id="faq-answer-<?php echo $faq['id']; ?>">
                        <p><?php echo nl2br(htmlspecialchars_decode($faq['answer'])); ?></p>
                        <div class="faq-meta">
                            <i class="far fa-calendar-alt"></i> Updated: <?php echo time_ago($faq['updated_at'] ?? $faq['created_at']); ?>
                            <?php if ($is_admin): ?>
                            <span class="ms-3">
                                <button class="btn btn-sm btn-outline-primary" onclick="editFaq(<?php echo $faq['id']; ?>)">
                                    <i class="fas fa-edit"></i> Edit
                                </button>
                                <button class="btn btn-sm btn-outline-danger" onclick="deleteFaq(<?php echo $faq['id']; ?>)">
                                    <i class="fas fa-trash"></i> Delete
                                </button>
                                <button class="btn btn-sm btn-outline-secondary" onclick="toggleStatus(<?php echo $faq['id']; ?>, '<?php echo $faq['status']; ?>')">
                                    <i class="fas fa-<?php echo $faq['status'] === 'active' ? 'pause' : 'play'; ?>"></i>
                                    <?php echo $faq['status'] === 'active' ? 'Deactivate' : 'Activate'; ?>
                                </button>
                            </span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>
        
        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <div class="pagination-container">
            <nav>
                <ul class="pagination justify-content-center">
                    <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page-1])); ?>">
                            <i class="fas fa-chevron-left"></i> Previous
                        </a>
                    </li>
                    
                    <?php
                    $start_page = max(1, $page - 2);
                    $end_page = min($total_pages, $page + 2);
                    
                    if ($start_page > 1) {
                        echo '<li class="page-item"><a class="page-link" href="?' . http_build_query(array_merge($_GET, ['page' => 1])) . '">1</a></li>';
                        if ($start_page > 2) echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                    }
                    
                    for ($i = $start_page; $i <= $end_page; $i++):
                    ?>
                    <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>"><?php echo $i; ?></a>
                    </li>
                    <?php endfor; ?>
                    
                    <?php
                    if ($end_page < $total_pages) {
                        if ($end_page < $total_pages - 1) echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                        echo '<li class="page-item"><a class="page-link" href="?' . http_build_query(array_merge($_GET, ['page' => $total_pages])) . '">' . $total_pages . '</a></li>';
                    }
                    ?>
                    
                    <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page+1])); ?>">
                            Next <i class="fas fa-chevron-right"></i>
                        </a>
                    </li>
                </ul>
            </nav>
        </div>
        <?php endif; ?>
        
        <?php endif; ?>
        
        <!-- Contact Support Section -->
        <div class="contact-support">
            <div class="support-icon">
                <i class="fas fa-headset"></i>
            </div>
            <h3>Still Have Questions?</h3>
            <p class="text-muted">Can't find the answer you're looking for? Our support team is here to help you.</p>
            <div class="d-flex flex-wrap justify-content-center">
                <a href="contact.php" class="btn btn-primary">
                    <i class="fas fa-envelope me-2"></i>Contact Us
                </a>
                <a href="tel:<?php echo SITE_PHONE; ?>" class="btn btn-outline-primary">
                    <i class="fas fa-phone me-2"></i><?php echo SITE_PHONE; ?>
                </a>
                <a href="mailto:<?php echo SITE_EMAIL; ?>" class="btn btn-outline-primary">
                    <i class="fas fa-envelope me-2"></i><?php echo SITE_EMAIL; ?>
                </a>
            </div>
            <div class="support-hours">
                <i class="fas fa-clock me-1"></i>Available Mon-Fri: 8:00 AM - 6:00 PM EAT
            </div>
        </div>
        
    </div>
</section>

<!-- ===================================================== -->
<!-- FOOTER -->
<!-- ===================================================== -->
<footer class="footer-custom">
    <div class="container">
        <div class="row">
            <div class="col-lg-4 col-md-6 mb-4">
                <h5><i class="fas fa-microchip me-2"></i><?php echo SITE_NAME; ?></h5>
                <p class="text-muted">Tanzania's leading B2B IT equipment and enterprise technology procurement platform connecting buyers with verified suppliers.</p>
                <div class="social-icons mt-3">
                    <a href="#"><i class="fab fa-facebook-f"></i></a>
                    <a href="#"><i class="fab fa-twitter"></i></a>
                    <a href="#"><i class="fab fa-linkedin-in"></i></a>
                    <a href="#"><i class="fab fa-instagram"></i></a>
                    <a href="#"><i class="fab fa-youtube"></i></a>
                </div>
            </div>
            
            <div class="col-lg-2 col-md-6 mb-4">
                <h6>Quick Links</h6>
                <ul class="list-unstyled">
                    <li class="mb-2"><a href="index.php">Home</a></li>
                    <li class="mb-2"><a href="products.php">Products</a></li>
                    <li class="mb-2"><a href="suppliers.php">Suppliers</a></li>
                    <li class="mb-2"><a href="about.php">About Us</a></li>
                    <li class="mb-2"><a href="contact.php">Contact</a></li>
                </ul>
            </div>
            
            <div class="col-lg-3 col-md-6 mb-4">
                <h6>Support</h6>
                <ul class="list-unstyled">
                    <li class="mb-2"><a href="faq.php">FAQ</a></li>
                    <li class="mb-2"><a href="how-to-buy.php">How to Buy</a></li>
                    <li class="mb-2"><a href="payment-guide.php">Payment Guide</a></li>
                    <li class="mb-2"><a href="shipping-guide.php">Shipping Guide</a></li>
                    <li class="mb-2"><a href="returns-policy.php">Returns Policy</a></li>
                </ul>
            </div>
            
            <div class="col-lg-3 col-md-6 mb-4">
                <h6>Contact Info</h6>
                <ul class="list-unstyled">
                    <li class="mb-2">
                        <i class="fas fa-map-marker-alt me-2 text-muted"></i>
                        <span class="text-muted"><?php echo SITE_ADDRESS; ?></span>
                    </li>
                    <li class="mb-2">
                        <i class="fas fa-phone me-2 text-muted"></i>
                        <span class="text-muted"><?php echo SITE_PHONE; ?></span>
                    </li>
                    <li class="mb-2">
                        <i class="fas fa-envelope me-2 text-muted"></i>
                        <span class="text-muted"><?php echo SITE_EMAIL; ?></span>
                    </li>
                    <li class="mb-2">
                        <i class="fas fa-clock me-2 text-muted"></i>
                        <span class="text-muted">Mon-Fri: 8AM - 6PM EAT</span>
                    </li>
                </ul>
            </div>
        </div>
        
        <hr class="footer-divider">
        
        <div class="row">
            <div class="col-md-6 text-center text-md-start">
                <p class="text-muted mb-0">&copy; <?php echo date('Y'); ?> <?php echo SITE_NAME; ?>. All rights reserved.</p>
            </div>
            <div class="col-md-6 text-center text-md-end">
                <a href="#" class="text-muted text-decoration-none me-3">Privacy Policy</a>
                <a href="#" class="text-muted text-decoration-none me-3">Terms of Service</a>
                <a href="#" class="text-muted text-decoration-none">Cookie Policy</a>
            </div>
        </div>
    </div>
</footer>

<!-- ===================================================== -->
<!-- SCROLL TO TOP BUTTON -->
<!-- ===================================================== -->
<button class="scroll-top" id="scrollTopBtn" onclick="scrollToTop()">
    <i class="fas fa-arrow-up"></i>
</button>

<!-- ===================================================== -->
<!-- ADMIN MODALS -->
<!-- ===================================================== -->

<?php if ($is_admin): ?>

<!-- Add FAQ Modal -->
<div class="modal fade admin-modal" id="addFaqModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-plus-circle me-2"></i>Add New FAQ
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add_faq">
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Category <span class="text-danger">*</span></label>
                        <input type="text" name="faq_category" class="form-control" placeholder="e.g., General, Buying, Payment" value="General" required>
                        <small class="text-muted">Enter a category name or use an existing one</small>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Question <span class="text-danger">*</span></label>
                        <input type="text" name="question" class="form-control" placeholder="Enter the question" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Answer <span class="text-danger">*</span></label>
                        <textarea name="answer" class="form-control" rows="5" placeholder="Enter the answer" required></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Sort Order</label>
                        <input type="number" name="sort_order" class="form-control" value="0" placeholder="0">
                        <small class="text-muted">Lower numbers appear first</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-plus me-1"></i>Add FAQ
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit FAQ Modal -->
<div class="modal fade admin-modal" id="editFaqModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-edit me-2"></i>Edit FAQ
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit_faq">
                    <input type="hidden" name="faq_id" id="edit_faq_id">
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Category <span class="text-danger">*</span></label>
                        <input type="text" name="faq_category" id="edit_faq_category" class="form-control" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Question <span class="text-danger">*</span></label>
                        <input type="text" name="question" id="edit_faq_question" class="form-control" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Answer <span class="text-danger">*</span></label>
                        <textarea name="answer" id="edit_faq_answer" class="form-control" rows="5" required></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Sort Order</label>
                        <input type="number" name="sort_order" id="edit_faq_sort_order" class="form-control">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Status</label>
                        <select name="status" id="edit_faq_status" class="form-select">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i>Update FAQ
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete FAQ Modal -->
<div class="modal fade admin-modal" id="deleteFaqModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title text-danger">
                    <i class="fas fa-exclamation-triangle me-2"></i>Delete FAQ
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" name="action" value="delete_faq">
                    <input type="hidden" name="faq_id" id="delete_faq_id">
                    
                    <p>Are you sure you want to delete this FAQ?</p>
                    <p class="text-danger"><strong>Warning:</strong> This action cannot be undone!</p>
                    <div class="alert alert-warning">
                        <i class="fas fa-info-circle me-2"></i>
                        <span id="delete_faq_question">Loading...</span>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-trash me-1"></i>Delete FAQ
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Toggle Status Form (Hidden) -->
<form method="POST" id="toggleStatusForm">
    <input type="hidden" name="action" value="toggle_status">
    <input type="hidden" name="faq_id" id="toggle_faq_id">
    <input type="hidden" name="current_status" id="toggle_current_status">
</form>

<?php endif; ?>

<!-- ===================================================== -->
<!-- SCRIPTS -->
<!-- ===================================================== -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
// =====================================================
// NAVBAR TOGGLE
// =====================================================
function toggleNavbar() {
    const mobileNav = document.getElementById('mobileNav');
    if (mobileNav.style.display === 'none' || mobileNav.style.display === '') {
        mobileNav.style.display = 'block';
    } else {
        mobileNav.style.display = 'none';
    }
}

// =====================================================
// SCROLL TO TOP
// =====================================================
window.addEventListener('scroll', function() {
    const btn = document.getElementById('scrollTopBtn');
    if (window.pageYOffset > 300) {
        btn.classList.add('visible');
    } else {
        btn.classList.remove('visible');
    }
});

function scrollToTop() {
    window.scrollTo({
        top: 0,
        behavior: 'smooth'
    });
}

// =====================================================
// FAQ ACCORDION TOGGLE
// =====================================================
document.addEventListener('DOMContentLoaded', function() {
    const faqQuestions = document.querySelectorAll('.faq-question');
    
    faqQuestions.forEach(function(question) {
        question.addEventListener('click', function() {
            const targetId = this.getAttribute('data-target');
            const answer = document.getElementById(targetId);
            const isActive = this.classList.contains('active');
            
            // Close all other answers in the same category
            const parent = this.closest('.faq-list');
            if (!isActive && parent) {
                const siblings = parent.querySelectorAll('.faq-question');
                siblings.forEach(function(sibling) {
                    sibling.classList.remove('active');
                    const siblingTarget = sibling.getAttribute('data-target');
                    const siblingAnswer = document.getElementById(siblingTarget);
                    if (siblingAnswer) {
                        siblingAnswer.classList.remove('show');
                    }
                    sibling.setAttribute('aria-expanded', 'false');
                });
            }
            
            // Toggle this answer
            this.classList.toggle('active');
            answer.classList.toggle('show');
            
            // Update aria-expanded
            const expanded = this.classList.contains('active');
            this.setAttribute('aria-expanded', expanded);
        });
    });
    
    // =====================================================
    // OPEN FAQ FROM URL HASH
    // =====================================================
    if (window.location.hash) {
        const hash = window.location.hash.substring(1);
        const target = document.getElementById(hash);
        if (target) {
            const parent = target.closest('.faq-item');
            if (parent) {
                const question = parent.querySelector('.faq-question');
                if (question) {
                    setTimeout(function() {
                        question.click();
                        parent.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }, 500);
                }
            }
        }
    }
    
    // =====================================================
    // SEARCH HIGHLIGHTING
    // =====================================================
    <?php if ($search): ?>
    const searchTerm = '<?php echo addslashes($search); ?>';
    if (searchTerm) {
        const searchRegex = new RegExp('(' + searchTerm.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + ')', 'gi');
        document.querySelectorAll('.faq-question .question-text, .faq-answer p').forEach(function(element) {
            const text = element.innerHTML;
            if (text && text.toLowerCase().includes(searchTerm.toLowerCase())) {
                element.innerHTML = text.replace(searchRegex, '<mark class="bg-warning">$1</mark>');
            }
        });
    }
    <?php endif; ?>
    
    // =====================================================
    // LIVE SEARCH
    // =====================================================
    let searchTimeout;
    const searchInput = document.querySelector('.faq-search-box input[type="text"]');
    if (searchInput) {
        searchInput.addEventListener('keyup', function() {
            clearTimeout(searchTimeout);
            const query = this.value.toLowerCase();
            
            searchTimeout = setTimeout(function() {
                const faqItems = document.querySelectorAll('.faq-item');
                let hasVisible = false;
                
                faqItems.forEach(function(item) {
                    const question = item.querySelector('.faq-question .question-text');
                    const answer = item.querySelector('.faq-answer p');
                    const questionText = question ? question.textContent.toLowerCase() : '';
                    const answerText = answer ? answer.textContent.toLowerCase() : '';
                    
                    if (query.length < 2 && query.length > 0) {
                        item.style.display = 'block';
                        hasVisible = true;
                        return;
                    }
                    
                    if (query.length === 0) {
                        item.style.display = 'block';
                        hasVisible = true;
                        return;
                    }
                    
                    if (questionText.includes(query) || answerText.includes(query)) {
                        item.style.display = 'block';
                        hasVisible = true;
                    } else {
                        item.style.display = 'none';
                    }
                });
                
                // Show/hide categories
                document.querySelectorAll('.faq-section').forEach(function(section) {
                    const visibleItems = section.querySelectorAll('.faq-item[style*="display: block"]');
                    if (query.length >= 2 && visibleItems.length === 0) {
                        section.style.display = 'none';
                    } else {
                        section.style.display = 'block';
                    }
                });
            }, 300);
        });
    }
});

// =====================================================
// ADMIN FUNCTIONS (Only if admin is logged in)
// =====================================================
<?php if ($is_admin): ?>

function editFaq(id) {
    // Get FAQ data from the DOM
    const faqItem = document.querySelector(`.faq-item[data-id="${id}"]`);
    if (!faqItem) return;
    
    const question = faqItem.querySelector('.faq-question .question-text').textContent.trim();
    const answer = faqItem.querySelector('.faq-answer p').textContent.trim();
    const category = faqItem.getAttribute('data-category') || 'General';
    const status = faqItem.querySelector('.faq-status .badge')?.textContent.toLowerCase() || 'active';
    
    document.getElementById('edit_faq_id').value = id;
    document.getElementById('edit_faq_category').value = category;
    document.getElementById('edit_faq_question').value = question;
    document.getElementById('edit_faq_answer').value = answer;
    document.getElementById('edit_faq_sort_order').value = 0;
    document.getElementById('edit_faq_status').value = status;
    
    new bootstrap.Modal(document.getElementById('editFaqModal')).show();
}

function deleteFaq(id) {
    const faqItem = document.querySelector(`.faq-item[data-id="${id}"]`);
    if (!faqItem) return;
    
    const question = faqItem.querySelector('.faq-question .question-text').textContent.trim();
    
    document.getElementById('delete_faq_id').value = id;
    document.getElementById('delete_faq_question').textContent = question;
    
    new bootstrap.Modal(document.getElementById('deleteFaqModal')).show();
}

function toggleStatus(id, currentStatus) {
    if (confirm(`Are you sure you want to ${currentStatus === 'active' ? 'deactivate' : 'activate'} this FAQ?`)) {
        document.getElementById('toggle_faq_id').value = id;
        document.getElementById('toggle_current_status').value = currentStatus;
        document.getElementById('toggleStatusForm').submit();
    }
}

<?php endif; ?>

// =====================================================
// KEYBOARD SHORTCUTS
// =====================================================
document.addEventListener('keydown', function(e) {
    // ESC key closes modals
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal.show').forEach(function(modal) {
            bootstrap.Modal.getInstance(modal)?.hide();
        });
    }
    
    // Ctrl+F for search focus
    if ((e.ctrlKey || e.metaKey) && e.key === 'f') {
        e.preventDefault();
        const searchInput = document.querySelector('.faq-search-box input[type="text"]');
        if (searchInput) {
            searchInput.focus();
            searchInput.select();
        }
    }
});
</script>

</body>
</html>