<?php
/**
 * TechProcure Tanzania - Admin Manage Suppliers
 * File: admin/suppliers/manage-suppliers.php
 * Description: Admin can view, approve, reject, and manage all suppliers
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
// HANDLE SUPPLIER ACTIONS
// =============================================

// Approve supplier
if (isset($_GET['approve']) && is_numeric($_GET['approve'])) {
    $supplier_id = (int)$_GET['approve'];
    try {
        $db->beginTransaction();
        
        $stmt = $db->prepare("UPDATE suppliers SET approval_status = 'approved', verification_badge = 1 WHERE id = ?");
        $stmt->execute([$supplier_id]);
        
        $stmt = $db->prepare("SELECT user_id FROM suppliers WHERE id = ?");
        $stmt->execute([$supplier_id]);
        $supplier = $stmt->fetch();
        
        if ($supplier) {
            $stmt = $db->prepare("UPDATE users SET status = 'active' WHERE id = ?");
            $stmt->execute([$supplier['user_id']]);
        }
        
        $db->commit();
        $_SESSION['success'] = "Supplier approved successfully!";
        logActivity($user_id, 'Approved Supplier', 'supplier', $supplier_id);
    } catch (PDOException $e) {
        $db->rollBack();
        $_SESSION['error'] = "Failed to approve supplier.";
    }
    header("Location: manage-suppliers.php");
    exit();
}

// Reject supplier
if (isset($_GET['reject']) && is_numeric($_GET['reject'])) {
    $supplier_id = (int)$_GET['reject'];
    try {
        $stmt = $db->prepare("UPDATE suppliers SET approval_status = 'rejected' WHERE id = ?");
        $stmt->execute([$supplier_id]);
        $_SESSION['success'] = "Supplier rejected.";
        logActivity($user_id, 'Rejected Supplier', 'supplier', $supplier_id);
    } catch (PDOException $e) {
        $_SESSION['error'] = "Failed to reject supplier.";
    }
    header("Location: manage-suppliers.php");
    exit();
}

// Block supplier
if (isset($_GET['block']) && is_numeric($_GET['block'])) {
    $supplier_id = (int)$_GET['block'];
    try {
        $db->beginTransaction();
        
        $stmt = $db->prepare("UPDATE suppliers SET approval_status = 'suspended' WHERE id = ?");
        $stmt->execute([$supplier_id]);
        
        $stmt = $db->prepare("SELECT user_id FROM suppliers WHERE id = ?");
        $stmt->execute([$supplier_id]);
        $supplier = $stmt->fetch();
        
        if ($supplier) {
            $stmt = $db->prepare("UPDATE users SET status = 'suspended' WHERE id = ?");
            $stmt->execute([$supplier['user_id']]);
        }
        
        $db->commit();
        $_SESSION['success'] = "Supplier blocked successfully!";
        logActivity($user_id, 'Blocked Supplier', 'supplier', $supplier_id);
    } catch (PDOException $e) {
        $db->rollBack();
        $_SESSION['error'] = "Failed to block supplier.";
    }
    header("Location: manage-suppliers.php");
    exit();
}

// Unblock supplier
if (isset($_GET['unblock']) && is_numeric($_GET['unblock'])) {
    $supplier_id = (int)$_GET['unblock'];
    try {
        $db->beginTransaction();
        
        $stmt = $db->prepare("UPDATE suppliers SET approval_status = 'approved', verification_badge = 1 WHERE id = ?");
        $stmt->execute([$supplier_id]);
        
        $stmt = $db->prepare("SELECT user_id FROM suppliers WHERE id = ?");
        $stmt->execute([$supplier_id]);
        $supplier = $stmt->fetch();
        
        if ($supplier) {
            $stmt = $db->prepare("UPDATE users SET status = 'active' WHERE id = ?");
            $stmt->execute([$supplier['user_id']]);
        }
        
        $db->commit();
        $_SESSION['success'] = "Supplier unblocked successfully!";
        logActivity($user_id, 'Unblocked Supplier', 'supplier', $supplier_id);
    } catch (PDOException $e) {
        $db->rollBack();
        $_SESSION['error'] = "Failed to unblock supplier.";
    }
    header("Location: manage-suppliers.php");
    exit();
}

// Delete supplier
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $supplier_id = (int)$_GET['delete'];
    try {
        $stmt = $db->prepare("SELECT user_id FROM suppliers WHERE id = ?");
        $stmt->execute([$supplier_id]);
        $supplier = $stmt->fetch();
        
        if ($supplier) {
            $stmt = $db->prepare("DELETE FROM suppliers WHERE id = ?");
            $stmt->execute([$supplier_id]);
            
            $stmt = $db->prepare("UPDATE users SET status = 'inactive' WHERE id = ?");
            $stmt->execute([$supplier['user_id']]);
        }
        
        $_SESSION['success'] = "Supplier deleted successfully!";
        logActivity($user_id, 'Deleted Supplier', 'supplier', $supplier_id);
    } catch (PDOException $e) {
        $_SESSION['error'] = "Failed to delete supplier.";
    }
    header("Location: manage-suppliers.php");
    exit();
}

// =============================================
// GET FILTER PARAMETERS
// =============================================

$filter = isset($_GET['filter']) ? trim($_GET['filter']) : 'all';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// =============================================
// BUILD SUPPLIER QUERY
// =============================================

$where_conditions = ["1=1"];
$params = [];

// Status filter
if ($filter == 'pending') {
    $where_conditions[] = "s.approval_status = 'pending'";
} elseif ($filter == 'approved') {
    $where_conditions[] = "s.approval_status = 'approved'";
} elseif ($filter == 'rejected') {
    $where_conditions[] = "s.approval_status = 'rejected'";
} elseif ($filter == 'suspended') {
    $where_conditions[] = "s.approval_status = 'suspended'";
}

// Search filter
if (!empty($search)) {
    $where_conditions[] = "(s.company_name LIKE ? OR s.contact_person LIKE ? OR s.email LIKE ? OR s.phone LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

$where_clause = implode(" AND ", $where_conditions);

// =============================================
// COUNT TOTAL SUPPLIERS
// =============================================

$total_suppliers = 0;
$total_pages = 1;

try {
    $count_sql = "SELECT COUNT(*) as total FROM suppliers s WHERE $where_clause";
    $stmt = $db->prepare($count_sql);
    $stmt->execute($params);
    $total_suppliers = (int)$stmt->fetchColumn();
    $total_pages = ceil($total_suppliers / $limit);
} catch (PDOException $e) {
    $total_suppliers = 0;
    $total_pages = 1;
}

// =============================================
// GET SUPPLIERS
// =============================================

$suppliers = [];
try {
    $sql = "SELECT s.*, u.email as user_email, u.status as user_status 
            FROM suppliers s
            LEFT JOIN users u ON s.user_id = u.id
            WHERE $where_clause
            ORDER BY s.created_at DESC
            LIMIT ? OFFSET ?";
    
    $params[] = $limit;
    $params[] = $offset;
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    
    if ($stmt->rowCount() > 0) {
        $suppliers = $stmt->fetchAll();
    }
} catch (PDOException $e) {
    $suppliers = [];
}

// =============================================
// GET COUNTS FOR STATS
// =============================================

$stats = [];
try {
    $stats['total'] = $db->query("SELECT COUNT(*) FROM suppliers")->fetchColumn();
    $stats['pending'] = $db->query("SELECT COUNT(*) FROM suppliers WHERE approval_status = 'pending'")->fetchColumn();
    $stats['approved'] = $db->query("SELECT COUNT(*) FROM suppliers WHERE approval_status = 'approved'")->fetchColumn();
    $stats['rejected'] = $db->query("SELECT COUNT(*) FROM suppliers WHERE approval_status = 'rejected'")->fetchColumn();
    $stats['suspended'] = $db->query("SELECT COUNT(*) FROM suppliers WHERE approval_status = 'suspended'")->fetchColumn();
} catch (PDOException $e) {
    $stats = ['total' => 0, 'pending' => 0, 'approved' => 0, 'rejected' => 0, 'suspended' => 0];
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
            'pending' => 'warning',
            'approved' => 'success',
            'rejected' => 'danger',
            'suspended' => 'danger',
            'active' => 'success',
            'inactive' => 'secondary'
        ];
        return $colors[$status] ?? 'secondary';
    }
}

if (!function_exists('getStarRating')) {
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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Suppliers - TechProcure Tanzania</title>
    
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
        
        .status-badge.pending { background: #fff3cd; color: #856404; }
        .status-badge.approved { background: #d4edda; color: #155724; }
        .status-badge.rejected { background: #f8d7da; color: #721c24; }
        .status-badge.suspended { background: #f8d7da; color: #721c24; }
        .status-badge.active { background: #d4edda; color: #155724; }
        .status-badge.inactive { background: #e2e3e5; color: #383d41; }
        
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
            <a href="manage-suppliers.php" class="nav-link active">
                <i class="fas fa-truck"></i> Suppliers
                <?php if($stats['pending'] > 0): ?>
                    <span class="badge bg-danger"><?php echo $stats['pending']; ?></span>
                <?php endif; ?>
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
            <i class="fas fa-truck me-2 text-primary"></i> Manage Suppliers
        </div>
        <div class="d-flex align-items-center gap-3">
            <a href="invitation-keys.php" class="btn btn-primary btn-sm">
                <i class="fas fa-envelope me-1"></i> Invite Supplier
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
                <div class="stat-label">Total Suppliers</div>
                <div class="stat-number"><?php echo number_format($stats['total']); ?></div>
                <div class="stat-icon"><i class="fas fa-truck"></i></div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="stat-card" style="border-left: 4px solid #ffc107;">
                <div class="stat-label">Pending Approval</div>
                <div class="stat-number"><?php echo number_format($stats['pending']); ?></div>
                <div class="stat-icon"><i class="fas fa-clock"></i></div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="stat-card" style="border-left: 4px solid #198754;">
                <div class="stat-label">Approved</div>
                <div class="stat-number"><?php echo number_format($stats['approved']); ?></div>
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
    </div>
    
    <!-- Filter Section -->
    <div class="data-card">
        <div class="card-title">
            <i class="fas fa-filter"></i> Filter Suppliers
        </div>
        <form method="GET" action="" class="row g-3">
            <div class="col-md-5">
                <input type="text" name="search" class="form-control" placeholder="Search by company, contact, email, phone..." value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <div class="col-md-3">
                <select name="filter" class="form-select">
                    <option value="all" <?php echo $filter == 'all' ? 'selected' : ''; ?>>All Suppliers</option>
                    <option value="pending" <?php echo $filter == 'pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="approved" <?php echo $filter == 'approved' ? 'selected' : ''; ?>>Approved</option>
                    <option value="rejected" <?php echo $filter == 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                    <option value="suspended" <?php echo $filter == 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                </select>
            </div>
            <div class="col-md-4">
                <button type="submit" class="btn btn-primary"><i class="fas fa-search me-1"></i> Filter</button>
                <a href="manage-suppliers.php" class="btn btn-secondary"><i class="fas fa-undo me-1"></i> Reset</a>
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
    
    <!-- Suppliers Table -->
    <div class="data-card">
        <div class="card-title">
            <i class="fas fa-list"></i> Suppliers List
            <span class="badge bg-primary ms-2"><?php echo number_format($total_suppliers); ?></span>
        </div>
        
        <div class="table-responsive">
            <table class="table table-hover" id="supplierTable">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Company</th>
                        <th>Contact</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>City</th>
                        <th>Approval</th>
                        <th>Rating</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($suppliers)): ?>
                        <?php foreach($suppliers as $supplier): ?>
                        <tr>
                            <td><?php echo $supplier['id']; ?></td>
                            <td>
                                <strong><?php echo htmlspecialchars($supplier['company_name']); ?></strong>
                                <?php if($supplier['verification_badge']): ?>
                                    <i class="fas fa-check-circle text-success ms-1" title="Verified"></i>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($supplier['contact_person'] ?? '-'); ?></td>
                            <td><?php echo htmlspecialchars($supplier['email'] ?? $supplier['user_email']); ?></td>
                            <td><?php echo htmlspecialchars($supplier['phone'] ?? '-'); ?></td>
                            <td><?php echo htmlspecialchars($supplier['city'] ?? '-'); ?></td>
                            <td>
                                <?php $badge = getStatusBadge($supplier['approval_status']); ?>
                                <span class="badge bg-<?php echo $badge; ?>">
                                    <?php echo ucfirst($supplier['approval_status']); ?>
                                </span>
                            </td>
                            <td>
                                <div class="text-nowrap">
                                    <?php echo getStarRating($supplier['rating'] ?? 0); ?>
                                    <span class="text-muted small">(<?php echo number_format($supplier['total_reviews'] ?? 0); ?>)</span>
                                </div>
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <a href="supplier-details.php?id=<?php echo $supplier['id']; ?>" class="btn btn-outline-primary" title="View">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <?php if($supplier['approval_status'] == 'pending'): ?>
                                        <a href="?approve=<?php echo $supplier['id']; ?>" class="btn btn-outline-success" title="Approve" onclick="return confirm('Approve this supplier?')">
                                            <i class="fas fa-check"></i>
                                        </a>
                                        <a href="?reject=<?php echo $supplier['id']; ?>" class="btn btn-outline-danger" title="Reject" onclick="return confirm('Reject this supplier?')">
                                            <i class="fas fa-times"></i>
                                        </a>
                                    <?php endif; ?>
                                    <?php if($supplier['approval_status'] == 'approved'): ?>
                                        <a href="?block=<?php echo $supplier['id']; ?>" class="btn btn-outline-warning" title="Block" onclick="return confirm('Block this supplier?')">
                                            <i class="fas fa-ban"></i>
                                        </a>
                                    <?php endif; ?>
                                    <?php if($supplier['approval_status'] == 'suspended'): ?>
                                        <a href="?unblock=<?php echo $supplier['id']; ?>" class="btn btn-outline-success" title="Unblock" onclick="return confirm('Unblock this supplier?')">
                                            <i class="fas fa-check"></i>
                                        </a>
                                    <?php endif; ?>
                                    <a href="?delete=<?php echo $supplier['id']; ?>" class="btn btn-outline-danger" title="Delete" onclick="return confirm('Delete this supplier permanently?')">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="9" class="text-center text-muted py-4">
                                <i class="fas fa-truck fa-2x mb-2"></i>
                                <p>No suppliers found</p>
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
                    <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">
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
        var hasData = $('#supplierTable tbody tr').filter(function() {
            return $(this).find('td').length > 1 && $(this).find('td:first').text() !== '';
        }).length > 0;
        
        if (hasData) {
            $('#supplierTable').DataTable({
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
                    infoEmpty: "No suppliers found",
                    infoFiltered: "(filtered from _MAX_ total entries)"
                }
            });
        } else {
            // No data, just show the table without DataTables
            $('#supplierTable').addClass('table-hover');
        }
    });
</script>

</body>
</html>