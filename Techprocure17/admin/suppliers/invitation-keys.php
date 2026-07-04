<?php
/**
 * TechProcure Tanzania - Admin Invitation Keys
 * File: admin/suppliers/invitation-keys.php
 * Description: Generate and manage supplier invitation keys
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
// HANDLE ACTIONS
// =============================================

// Generate new invitation key
if (isset($_POST['generate_key'])) {
    $company_name = trim($_POST['company_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $expires_days = (int)($_POST['expires_days'] ?? 7);
    
    if (empty($company_name) || empty($email)) {
        $_SESSION['error'] = "Please fill in company name and email.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['error'] = "Invalid email address.";
    } else {
        try {
            // Generate unique key
            $invitation_key = strtoupper(bin2hex(random_bytes(8)));
            $expires_at = date('Y-m-d H:i:s', strtotime("+$expires_days days"));
            
            $stmt = $db->prepare("INSERT INTO supplier_invitation_keys (invitation_key, company_name, email, expires_at, created_by, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
            $stmt->execute([$invitation_key, $company_name, $email, $expires_at, $user_id]);
            
            $_SESSION['success'] = "Invitation key generated successfully!";
            logActivity($user_id, 'Generated Invitation Key', 'supplier_invitation', $db->lastInsertId());
        } catch (PDOException $e) {
            $_SESSION['error'] = "Failed to generate invitation key.";
        }
    }
    header("Location: invitation-keys.php");
    exit();
}

// Delete invitation key
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $key_id = (int)$_GET['delete'];
    try {
        $stmt = $db->prepare("DELETE FROM supplier_invitation_keys WHERE id = ?");
        $stmt->execute([$key_id]);
        $_SESSION['success'] = "Invitation key deleted successfully!";
        logActivity($user_id, 'Deleted Invitation Key', 'supplier_invitation', $key_id);
    } catch (PDOException $e) {
        $_SESSION['error'] = "Failed to delete invitation key.";
    }
    header("Location: invitation-keys.php");
    exit();
}

// =============================================
// GET INVITATION KEYS
// =============================================

$keys = [];
try {
    $sql = "SELECT k.*, u.full_name as created_by_name 
            FROM supplier_invitation_keys k
            LEFT JOIN users u ON k.created_by = u.id
            ORDER BY k.created_at DESC";
    $stmt = $db->query($sql);
    if ($stmt->rowCount() > 0) {
        $keys = $stmt->fetchAll();
    }
} catch (PDOException $e) {
    $keys = [];
}

// =============================================
// HELPER FUNCTIONS
// =============================================

if (!function_exists('formatDate')) {
    function formatDate($date) {
        if (empty($date)) return '-';
        return date('M d, Y', strtotime($date));
    }
}

if (!function_exists('formatDateTime')) {
    function formatDateTime($date) {
        if (empty($date)) return '-';
        return date('M d, Y H:i', strtotime($date));
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invitation Keys - TechProcure Tanzania</title>
    
    <!-- CSS Dependencies -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f8f9fa;
        }
        
        /* Sidebar */
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
        
        .sidebar .nav-link .badge {
            margin-left: auto;
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
        
        /* Main Content */
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
        
        .data-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 24px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        
        .data-card .card-title {
            font-size: 1rem;
            font-weight: 600;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #e9ecef;
        }
        
        .data-card .card-title i {
            margin-right: 8px;
            color: #0d6efd;
        }
        
        .key-code {
            font-family: 'Courier New', monospace;
            font-weight: 700;
            color: #0d6efd;
            background: #e7f3ff;
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 0.9rem;
        }
        
        .copy-btn {
            cursor: pointer;
            color: #6c757d;
            transition: all 0.3s;
        }
        
        .copy-btn:hover {
            color: #0d6efd;
        }
        
        .status-badge {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 500;
        }
        
        .status-badge.active { background: #d4edda; color: #155724; }
        .status-badge.expired { background: #f8d7da; color: #721c24; }
        .status-badge.used { background: #fff3cd; color: #856404; }
        
        .form-control, .form-select {
            border-radius: 10px;
            padding: 10px 15px;
            border: 1px solid #e0e0e0;
            transition: all 0.3s;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: #0d6efd;
            box-shadow: 0 0 0 3px rgba(13,110,253,0.1);
            outline: none;
        }
        
        .btn-primary-custom {
            background: linear-gradient(135deg, #0d6efd, #0b5ed7);
            border: none;
            border-radius: 10px;
            padding: 10px 20px;
            font-weight: 600;
            color: white;
            transition: all 0.3s;
        }
        
        .btn-primary-custom:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(13,110,253,0.3);
        }
        
        @media (max-width: 992px) {
            .sidebar {
                margin-left: -280px;
            }
            .sidebar.active {
                margin-left: 0;
            }
            .main-content {
                margin-left: 0;
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
            <a href="manage-suppliers.php" class="nav-link">
                <i class="fas fa-truck"></i> Suppliers
            </a>
        </div>
        <div class="nav-item">
            <a href="invitation-keys.php" class="nav-link active">
                <i class="fas fa-key"></i> Invitation Keys
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
            <a href="../settings/general-settings.php" class="nav-link">
                <i class="fas fa-cog"></i> Settings
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
    <!-- Top Navbar -->
    <div class="top-navbar">
        <div class="welcome-text">
            <i class="fas fa-key me-2 text-primary"></i> Supplier Invitation Keys
        </div>
        <div class="d-flex align-items-center gap-3">
            <a href="manage-suppliers.php" class="btn btn-secondary btn-sm">
                <i class="fas fa-arrow-left me-1"></i> Back
            </a>
            <div class="admin-avatar">
                <?php echo strtoupper(substr($_SESSION['user_name'] ?? 'A', 0, 1)); ?>
            </div>
        </div>
    </div>
    
    <!-- Success/Error Messages -->
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
    
    <!-- Generate Key Form -->
    <div class="data-card">
        <div class="card-title">
            <i class="fas fa-plus-circle"></i> Generate New Invitation Key
        </div>
        <form method="POST" action="" class="row g-3">
            <div class="col-md-4">
                <label class="form-label">Company Name</label>
                <input type="text" name="company_name" class="form-control" placeholder="Enter company name" required>
            </div>
            <div class="col-md-4">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-control" placeholder="Enter email address" required>
            </div>
            <div class="col-md-2">
                <label class="form-label">Expires In (Days)</label>
                <select name="expires_days" class="form-select">
                    <option value="1">1 Day</option>
                    <option value="3">3 Days</option>
                    <option value="7" selected>7 Days</option>
                    <option value="14">14 Days</option>
                    <option value="30">30 Days</option>
                </select>
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" name="generate_key" class="btn btn-primary-custom w-100">
                    <i class="fas fa-key me-1"></i> Generate
                </button>
            </div>
        </form>
    </div>
    
    <!-- Invitation Keys Table -->
    <div class="data-card">
        <div class="card-title">
            <i class="fas fa-list"></i> Invitation Keys
            <span class="badge bg-primary ms-2"><?php echo count($keys); ?></span>
        </div>
        
        <div class="table-responsive">
            <table class="table table-hover" id="keysTable">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Key</th>
                        <th>Company</th>
                        <th>Email</th>
                        <th>Created By</th>
                        <th>Created</th>
                        <th>Expires</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($keys)): ?>
                        <?php foreach($keys as $key): ?>
                        <?php 
                            $is_expired = strtotime($key['expires_at']) < time();
                            $is_used = !empty($key['used_by']);
                            $status = $is_used ? 'used' : ($is_expired ? 'expired' : 'active');
                        ?>
                        <tr>
                            <td><?php echo $key['id']; ?></td>
                            <td>
                                <span class="key-code"><?php echo htmlspecialchars($key['invitation_key']); ?></span>
                                <i class="fas fa-copy copy-btn ms-2" onclick="copyKey('<?php echo $key['invitation_key']; ?>')" title="Copy key"></i>
                            </td>
                            <td><?php echo htmlspecialchars($key['company_name']); ?></td>
                            <td><?php echo htmlspecialchars($key['email']); ?></td>
                            <td><?php echo htmlspecialchars($key['created_by_name'] ?? 'System'); ?></td>
                            <td><?php echo formatDateTime($key['created_at']); ?></td>
                            <td><?php echo formatDateTime($key['expires_at']); ?></td>
                            <td>
                                <span class="status-badge <?php echo $status; ?>">
                                    <?php echo ucfirst($status); ?>
                                </span>
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <a href="?delete=<?php echo $key['id']; ?>" class="btn btn-outline-danger" title="Delete" onclick="return confirm('Delete this invitation key?')">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="9" class="text-center text-muted py-4">
                                <i class="fas fa-key fa-2x mb-2"></i>
                                <p>No invitation keys found</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<script>
    $(document).ready(function() {
        // Check if there are any data rows (excluding the empty state row)
        var hasData = $('#keysTable tbody tr').filter(function() {
            return $(this).find('td').length > 1 && $(this).find('td:first').text() !== '';
        }).length > 0;
        
        if (hasData) {
            $('#keysTable').DataTable({
                responsive: true,
                pageLength: 20,
                ordering: true,
                columnDefs: [
                    { orderable: false, targets: [8] } // Disable sorting on Actions column
                ],
                language: {
                    search: "Search:",
                    lengthMenu: "Show _MENU_ entries",
                    info: "Showing _START_ to _END_ of _TOTAL_ entries",
                    infoEmpty: "No invitation keys found",
                    infoFiltered: "(filtered from _MAX_ total entries)"
                }
            });
        } else {
            // No data, just show the table without DataTables
            $('#keysTable').addClass('table-hover');
        }
    });
    
    // Copy key to clipboard
    function copyKey(key) {
        navigator.clipboard.writeText(key).then(function() {
            // Show temporary success message
            var toast = document.createElement('div');
            toast.className = 'alert alert-success alert-dismissible fade show position-fixed top-0 end-0 m-3';
            toast.style.zIndex = '9999';
            toast.innerHTML = '<i class="fas fa-check-circle me-2"></i> Key copied to clipboard!';
            document.body.appendChild(toast);
            setTimeout(function() {
                toast.remove();
            }, 3000);
        }).catch(function() {
            // Fallback for older browsers
            var input = document.createElement('input');
            input.value = key;
            document.body.appendChild(input);
            input.select();
            document.execCommand('copy');
            input.remove();
            
            var toast = document.createElement('div');
            toast.className = 'alert alert-success alert-dismissible fade show position-fixed top-0 end-0 m-3';
            toast.style.zIndex = '9999';
            toast.innerHTML = '<i class="fas fa-check-circle me-2"></i> Key copied to clipboard!';
            document.body.appendChild(toast);
            setTimeout(function() {
                toast.remove();
            }, 3000);
        });
    }
</script>

</body>
</html>