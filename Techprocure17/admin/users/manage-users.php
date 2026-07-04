<?php
/**
 * TechProcure Tanzania - Admin Manage Users
 * File: admin/users/manage-users.php
 * Description: Admin can view, edit, block, unblock, and delete users
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
// HANDLE USER ACTIONS
// =============================================

// Block user
if (isset($_GET['block']) && is_numeric($_GET['block'])) {
    $user_id_to_block = (int)$_GET['block'];
    if ($user_id_to_block != $user_id) {
        try {
            $stmt = $db->prepare("UPDATE users SET status = 'suspended' WHERE id = ?");
            $stmt->execute([$user_id_to_block]);
            $_SESSION['success'] = "User blocked successfully!";
            logActivity($user_id, 'Blocked User', 'user', $user_id_to_block);
        } catch (PDOException $e) {
            $_SESSION['error'] = "Failed to block user.";
        }
    } else {
        $_SESSION['error'] = "You cannot block yourself!";
    }
    header("Location: manage-users.php");
    exit();
}

// Unblock user
if (isset($_GET['unblock']) && is_numeric($_GET['unblock'])) {
    $user_id_to_unblock = (int)$_GET['unblock'];
    try {
        $stmt = $db->prepare("UPDATE users SET status = 'active' WHERE id = ?");
        $stmt->execute([$user_id_to_unblock]);
        $_SESSION['success'] = "User unblocked successfully!";
        logActivity($user_id, 'Unblocked User', 'user', $user_id_to_unblock);
    } catch (PDOException $e) {
        $_SESSION['error'] = "Failed to unblock user.";
    }
    header("Location: manage-users.php");
    exit();
}

// Delete user
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $user_id_to_delete = (int)$_GET['delete'];
    if ($user_id_to_delete != $user_id) {
        try {
            $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$user_id_to_delete]);
            $_SESSION['success'] = "User deleted successfully!";
            logActivity($user_id, 'Deleted User', 'user', $user_id_to_delete);
        } catch (PDOException $e) {
            $_SESSION['error'] = "Failed to delete user.";
        }
    } else {
        $_SESSION['error'] = "You cannot delete yourself!";
    }
    header("Location: manage-users.php");
    exit();
}

// Make admin (promote to admin)
if (isset($_GET['make_admin']) && is_numeric($_GET['make_admin'])) {
    $user_id_to_promote = (int)$_GET['make_admin'];
    try {
        $stmt = $db->prepare("SELECT role_id FROM roles WHERE role_name = 'admin'");
        $stmt->execute();
        $admin_role = $stmt->fetch();
        
        if ($admin_role) {
            $stmt = $db->prepare("UPDATE users SET role_id = ?, user_type = 'admin' WHERE id = ?");
            $stmt->execute([$admin_role['id'], $user_id_to_promote]);
            $_SESSION['success'] = "User promoted to admin successfully!";
            logActivity($user_id, 'Promoted User to Admin', 'user', $user_id_to_promote);
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = "Failed to promote user.";
    }
    header("Location: manage-users.php");
    exit();
}

// =============================================
// GET FILTER PARAMETERS
// =============================================

$filter = isset($_GET['filter']) ? trim($_GET['filter']) : 'all';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$user_type = isset($_GET['user_type']) ? trim($_GET['user_type']) : 'all';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// =============================================
// BUILD USER QUERY
// =============================================

$where_conditions = ["1=1"];
$params = [];

// Status filter
if ($filter == 'active') {
    $where_conditions[] = "u.status = 'active'";
} elseif ($filter == 'inactive') {
    $where_conditions[] = "u.status = 'inactive'";
} elseif ($filter == 'suspended') {
    $where_conditions[] = "u.status = 'suspended'";
} elseif ($filter == 'pending') {
    $where_conditions[] = "u.status = 'pending'";
}

// User type filter
if ($user_type == 'admin') {
    $where_conditions[] = "u.user_type = 'admin'";
} elseif ($user_type == 'supplier') {
    $where_conditions[] = "u.user_type = 'supplier'";
} elseif ($user_type == 'customer') {
    $where_conditions[] = "u.user_type = 'customer'";
}

// Search filter
if (!empty($search)) {
    $where_conditions[] = "(u.username LIKE ? OR u.email LIKE ? OR u.full_name LIKE ? OR u.phone LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

$where_clause = implode(" AND ", $where_conditions);

// =============================================
// COUNT TOTAL USERS
// =============================================

$total_users = 0;
$total_pages = 1;

try {
    $count_sql = "SELECT COUNT(*) as total FROM users u WHERE $where_clause";
    $stmt = $db->prepare($count_sql);
    $stmt->execute($params);
    $total_users = (int)$stmt->fetchColumn();
    $total_pages = ceil($total_users / $limit);
} catch (PDOException $e) {
    $total_users = 0;
    $total_pages = 1;
}

// =============================================
// GET USERS
// =============================================

$users = [];
try {
    $sql = "SELECT u.*, r.role_name 
            FROM users u
            LEFT JOIN roles r ON u.role_id = r.id
            WHERE $where_clause
            ORDER BY u.created_at DESC
            LIMIT ? OFFSET ?";
    
    $params[] = $limit;
    $params[] = $offset;
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    
    if ($stmt->rowCount() > 0) {
        $users = $stmt->fetchAll();
    }
} catch (PDOException $e) {
    $users = [];
}

// =============================================
// GET COUNTS FOR STATS
// =============================================

$stats = [];
try {
    $stats['total'] = $db->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $stats['active'] = $db->query("SELECT COUNT(*) FROM users WHERE status = 'active'")->fetchColumn();
    $stats['inactive'] = $db->query("SELECT COUNT(*) FROM users WHERE status = 'inactive'")->fetchColumn();
    $stats['suspended'] = $db->query("SELECT COUNT(*) FROM users WHERE status = 'suspended'")->fetchColumn();
    $stats['pending'] = $db->query("SELECT COUNT(*) FROM users WHERE status = 'pending'")->fetchColumn();
    $stats['admins'] = $db->query("SELECT COUNT(*) FROM users WHERE user_type = 'admin'")->fetchColumn();
    $stats['suppliers'] = $db->query("SELECT COUNT(*) FROM users WHERE user_type = 'supplier'")->fetchColumn();
    $stats['customers'] = $db->query("SELECT COUNT(*) FROM users WHERE user_type = 'customer'")->fetchColumn();
} catch (PDOException $e) {
    $stats = ['total' => 0, 'active' => 0, 'inactive' => 0, 'suspended' => 0, 'pending' => 0, 'admins' => 0, 'suppliers' => 0, 'customers' => 0];
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

if (!function_exists('getStatusBadge')) {
    function getStatusBadge($status) {
        $colors = [
            'active' => 'success',
            'inactive' => 'secondary',
            'suspended' => 'danger',
            'pending' => 'warning'
        ];
        return $colors[$status] ?? 'secondary';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - TechProcure Tanzania</title>
    
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
        
        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            transition: all 0.3s;
            position: relative;
            overflow: hidden;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        
        .stat-card .stat-icon {
            font-size: 2rem;
            opacity: 0.15;
            position: absolute;
            right: 20px;
            bottom: 20px;
        }
        
        .stat-card .stat-number {
            font-size: 1.8rem;
            font-weight: 700;
        }
        
        .stat-card .stat-label {
            font-size: 0.8rem;
            color: #6c757d;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
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
        
        .status-badge {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 500;
        }
        
        .status-badge.active { background: #d4edda; color: #155724; }
        .status-badge.inactive { background: #e2e3e5; color: #383d41; }
        .status-badge.suspended { background: #f8d7da; color: #721c24; }
        .status-badge.pending { background: #fff3cd; color: #856404; }
        
        .role-badge {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 500;
        }
        
        .role-badge.admin { background: #dc3545; color: white; }
        .role-badge.supplier { background: #198754; color: white; }
        .role-badge.customer { background: #0d6efd; color: white; }
        
        .table-responsive table {
            width: 100% !important;
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
            <a href="manage-users.php" class="nav-link active">
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
            <i class="fas fa-users me-2 text-primary"></i> Manage Users
        </div>
        <div class="d-flex align-items-center gap-3">
            <a href="../auth/register.php" class="btn btn-primary btn-sm" target="_blank">
                <i class="fas fa-user-plus me-1"></i> Add User
            </a>
            <div class="admin-avatar">
                <?php echo strtoupper(substr($_SESSION['user_name'] ?? 'A', 0, 1)); ?>
            </div>
        </div>
    </div>
    
    <!-- Stats Cards -->
    <div class="row">
        <div class="col-md-3 col-sm-6">
            <div class="stat-card" style="border-left: 4px solid #0d6efd;">
                <div class="stat-label">Total Users</div>
                <div class="stat-number"><?php echo number_format($stats['total']); ?></div>
                <div class="stat-icon"><i class="fas fa-users"></i></div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="stat-card" style="border-left: 4px solid #198754;">
                <div class="stat-label">Active</div>
                <div class="stat-number"><?php echo number_format($stats['active']); ?></div>
                <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="stat-card" style="border-left: 4px solid #dc3545;">
                <div class="stat-label">Suspended</div>
                <div class="stat-number"><?php echo number_format($stats['suspended']); ?></div>
                <div class="stat-icon"><i class="fas fa-ban"></i></div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="stat-card" style="border-left: 4px solid #ffc107;">
                <div class="stat-label">Pending</div>
                <div class="stat-number"><?php echo number_format($stats['pending']); ?></div>
                <div class="stat-icon"><i class="fas fa-clock"></i></div>
            </div>
        </div>
    </div>
    
    <!-- Filter Section -->
    <div class="data-card">
        <div class="card-title">
            <i class="fas fa-filter"></i> Filter Users
        </div>
        <form method="GET" action="" class="row g-3">
            <div class="col-md-3">
                <input type="text" name="search" class="form-control" placeholder="Search by name, email, phone..." value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <div class="col-md-2">
                <select name="filter" class="form-select">
                    <option value="all" <?php echo $filter == 'all' ? 'selected' : ''; ?>>All Status</option>
                    <option value="active" <?php echo $filter == 'active' ? 'selected' : ''; ?>>Active</option>
                    <option value="inactive" <?php echo $filter == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                    <option value="suspended" <?php echo $filter == 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                    <option value="pending" <?php echo $filter == 'pending' ? 'selected' : ''; ?>>Pending</option>
                </select>
            </div>
            <div class="col-md-2">
                <select name="user_type" class="form-select">
                    <option value="all" <?php echo $user_type == 'all' ? 'selected' : ''; ?>>All Types</option>
                    <option value="admin" <?php echo $user_type == 'admin' ? 'selected' : ''; ?>>Admins</option>
                    <option value="supplier" <?php echo $user_type == 'supplier' ? 'selected' : ''; ?>>Suppliers</option>
                    <option value="customer" <?php echo $user_type == 'customer' ? 'selected' : ''; ?>>Customers</option>
                </select>
            </div>
            <div class="col-md-5">
                <button type="submit" class="btn btn-primary"><i class="fas fa-search me-1"></i> Filter</button>
                <a href="manage-users.php" class="btn btn-secondary"><i class="fas fa-undo me-1"></i> Reset</a>
                <a href="user-details.php" class="btn btn-info"><i class="fas fa-eye me-1"></i> View Details</a>
            </div>
        </form>
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
    
    <!-- Users Table -->
    <div class="data-card">
        <div class="card-title">
            <i class="fas fa-list"></i> Users List
            <span class="badge bg-primary ms-2"><?php echo number_format($total_users); ?></span>
        </div>
        
        <div class="table-responsive">
            <table class="table table-hover" id="usersTable">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Username</th>
                        <th>Full Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Company</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Joined</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($users)): ?>
                        <?php foreach($users as $user): ?>
                        <tr>
                            <td><?php echo $user['id']; ?></td>
                            <td><?php echo htmlspecialchars($user['username']); ?></td>
                            <td><?php echo htmlspecialchars($user['full_name'] ?? '-'); ?></td>
                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                            <td><?php echo htmlspecialchars($user['phone'] ?? '-'); ?></td>
                            <td><?php echo htmlspecialchars($user['company_name'] ?? '-'); ?></td>
                            <td>
                                <span class="role-badge <?php echo $user['user_type'] ?? 'customer'; ?>">
                                    <?php echo ucfirst($user['user_type'] ?? 'Customer'); ?>
                                </span>
                            </td>
                            <td>
                                <?php $badge = getStatusBadge($user['status']); ?>
                                <span class="status-badge <?php echo $user['status']; ?>">
                                    <?php echo ucfirst($user['status']); ?>
                                </span>
                            </td>
                            <td><?php echo formatDate($user['created_at']); ?></td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <a href="user-details.php?id=<?php echo $user['id']; ?>" class="btn btn-outline-primary" title="View">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="edit-user.php?id=<?php echo $user['id']; ?>" class="btn btn-outline-secondary" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <?php if($user['user_type'] != 'admin'): ?>
                                        <a href="?make_admin=<?php echo $user['id']; ?>" class="btn btn-outline-danger" title="Make Admin" onclick="return confirm('Promote this user to admin?')">
                                            <i class="fas fa-user-shield"></i>
                                        </a>
                                    <?php endif; ?>
                                    <?php if($user['status'] == 'active'): ?>
                                        <a href="?block=<?php echo $user['id']; ?>" class="btn btn-outline-warning" title="Block" onclick="return confirm('Block this user?')">
                                            <i class="fas fa-ban"></i>
                                        </a>
                                    <?php elseif($user['status'] == 'suspended'): ?>
                                        <a href="?unblock=<?php echo $user['id']; ?>" class="btn btn-outline-success" title="Unblock" onclick="return confirm('Unblock this user?')">
                                            <i class="fas fa-check"></i>
                                        </a>
                                    <?php endif; ?>
                                    <?php if($user['id'] != $user_id): ?>
                                        <a href="?delete=<?php echo $user['id']; ?>" class="btn btn-outline-danger" title="Delete" onclick="return confirm('Delete this user permanently?')">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="10" class="text-center text-muted py-4">
                                <i class="fas fa-users fa-2x mb-2"></i>
                                <p>No users found</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Pagination -->
        <?php if($total_pages > 1): ?>
        <nav class="mt-4">
            <ul class="pagination justify-content-center">
                <?php if($page > 1): ?>
                <li class="page-item">
                    <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">
                        <i class="fas fa-chevron-left"></i> Previous
                    </a>
                </li>
                <?php endif; ?>
                
                <?php for($i = 1; $i <= $total_pages; $i++): ?>
                    <?php if($i == $page): ?>
                    <li class="page-item active"><span class="page-link"><?php echo $i; ?></span></li>
                    <?php elseif($i <= 3 || $i > $total_pages - 3 || ($i >= $page - 1 && $i <= $page + 1)): ?>
                    <li class="page-item">
                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>"><?php echo $i; ?></a>
                    </li>
                    <?php elseif($i == 4 || $i == $total_pages - 3): ?>
                    <li class="page-item disabled"><span class="page-link">...</span></li>
                    <?php endif; ?>
                <?php endfor; ?>
                
                <?php if($page < $total_pages): ?>
                <li class="page-item">
                    <a class->page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">
                        Next <i class="fas fa-chevron-right"></i>
                    </a>
                </li>
                <?php endif; ?>
            </ul>
        </nav>
        <?php endif; ?>
    </div>
</div>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<script>
    $(document).ready(function() {
        // Check if there are any data rows (excluding the empty state row)
        var hasData = $('#usersTable tbody tr').filter(function() {
            return $(this).find('td').length > 1 && $(this).find('td:first').text() !== '';
        }).length > 0;
        
        if (hasData) {
            $('#usersTable').DataTable({
                responsive: true,
                pageLength: 20,
                ordering: true,
                columnDefs: [
                    { orderable: false, targets: [9] } // Disable sorting on Actions column
                ],
                language: {
                    search: "Search:",
                    lengthMenu: "Show _MENU_ entries",
                    info: "Showing _START_ to _END_ of _TOTAL_ entries",
                    infoEmpty: "No users found",
                    infoFiltered: "(filtered from _MAX_ total entries)"
                }
            });
        } else {
            // No data, just show the table without DataTables
            $('#usersTable').addClass('table-hover');
        }
    });
</script>

</body>
</html>