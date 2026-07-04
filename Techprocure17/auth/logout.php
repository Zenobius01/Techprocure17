<?php
/**
 * TechProcure Tanzania - Logout Confirmation
 * File: auth/logout.php
 * Description: Logout confirmation page
 */

// Start session if not started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// If user is not logged in, redirect to login
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

// If logout is confirmed
if (isset($_GET['confirm']) && $_GET['confirm'] == 'yes') {
    // Include configuration
    require_once '../includes/config.php';
    require_once '../includes/db.php';
    require_once '../includes/functions.php';
    
    // Log activity
    $user_id = $_SESSION['user_id'];
    try {
        $db = getDB();
        $log_sql = "INSERT INTO activity_logs (user_id, action, entity_type, ip_address, user_agent, created_at) 
                    VALUES (?, 'User Logged Out', 'auth', ?, ?, NOW())";
        $log_stmt = $db->prepare($log_sql);
        $log_stmt->execute([
            $user_id,
            $_SERVER['REMOTE_ADDR'] ?? '',
            $_SERVER['HTTP_USER_AGENT'] ?? ''
        ]);
    } catch (Exception $e) {
        // Continue even if logging fails
    }
    
    // Clear cookies and session
    if (isset($_COOKIE['remember_token'])) {
        setcookie('remember_token', '', time() - 3600, '/', '', false, true);
    }
    
    $_SESSION = array();
    
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    session_destroy();
    
    header("Location: login.php?logout=success");
    exit();
}

$user_name = $_SESSION['user_name'] ?? 'User';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logout - TechProcure Tanzania</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .logout-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            padding: 40px;
            max-width: 450px;
            width: 100%;
            text-align: center;
        }
        .logout-card .icon {
            font-size: 4rem;
            color: #dc3545;
            margin-bottom: 20px;
        }
        .logout-card h3 {
            font-weight: 700;
            margin-bottom: 10px;
        }
        .logout-card p {
            color: #6c757d;
            margin-bottom: 25px;
        }
        .btn-logout {
            background: #dc3545;
            border: none;
            padding: 12px;
            font-weight: 600;
            color: white;
            border-radius: 10px;
            width: 100%;
            transition: all 0.3s;
        }
        .btn-logout:hover {
            background: #c82333;
            transform: translateY(-2px);
        }
        .btn-cancel {
            background: transparent;
            border: 2px solid #6c757d;
            padding: 12px;
            font-weight: 600;
            color: #6c757d;
            border-radius: 10px;
            width: 100%;
            transition: all 0.3s;
            margin-top: 10px;
            text-decoration: none;
            display: block;
        }
        .btn-cancel:hover {
            background: #6c757d;
            color: white;
        }
    </style>
</head>
<body>
    <div class="logout-card">
        <div class="icon">
            <i class="fas fa-sign-out-alt"></i>
        </div>
        <h3>Logout Confirmation</h3>
        <p>Are you sure you want to logout, <strong><?php echo htmlspecialchars($user_name); ?></strong>?</p>
        <a href="?confirm=yes" class="btn-logout">
            <i class="fas fa-sign-out-alt me-2"></i> Yes, Logout
        </a>
        <a href="../index.php" class="btn-cancel">
            <i class="fas fa-arrow-left me-2"></i> Cancel, Stay Logged In
        </a>
    </div>
</body>
</html>