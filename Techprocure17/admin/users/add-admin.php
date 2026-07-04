<?php
/**
 * TechProcure Tanzania - Admin Add Administrator
 * File: admin/users/add-admin.php
 * Description: Add new administrator accounts (Super Admin only)
 */

// Start session if not started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include configuration files
require_once '../../includes/config.php';
require_once '../../includes/db.php';
require_once '../../includes/functions.php';

// =============================================
// DEFINE MISSING FUNCTIONS IF NOT EXISTS
// =============================================
if (!function_exists('sanitize')) {
    function sanitize($input) {
        if (is_array($input)) {
            return array_map('sanitize', $input);
        }
        return htmlspecialchars(strip_tags(trim($input)));
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field() {
        return '<input type="hidden" name="csrf_token" value="' . ($_SESSION['csrf_token'] ?? '') . '">';
    }
}

if (!function_exists('verifyCSRFToken')) {
    function verifyCSRFToken($token) {
        if (empty($token) || empty($_SESSION['csrf_token'])) {
            return false;
        }
        return hash_equals($_SESSION['csrf_token'], $token);
    }
}

if (!function_exists('log_activity')) {
    function log_activity($user_type, $user_id, $action, $details = null) {
        error_log("Activity: $user_type | $user_id | $action | $details");
        return true;
    }
}

if (!function_exists('generate_token')) {
    function generate_token() {
        return bin2hex(random_bytes(32));
    }
}

// =============================================
// GET DATABASE CONNECTION
// =============================================
// Check if $db is set from db.php, otherwise create connection
if (!isset($db) || $db === null) {
    try {
        $db = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        die("Connection failed: " . $e->getMessage());
    }
}

$conn = $db;

// =============================================
// ADMIN AUTHENTICATION CHECK
// =============================================
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: ../../auth/login.php');
    exit();
}

// Check if user is super admin
$check_sql = "SELECT role FROM admins WHERE id = ?";
$check_stmt = $conn->prepare($check_sql);
$check_stmt->bindParam(1, $_SESSION['user_id'], PDO::PARAM_INT);
$check_stmt->execute();
$current_user = $check_stmt->fetch(PDO::FETCH_ASSOC);

if (!$current_user || $current_user['role'] !== 'super_admin') {
    $_SESSION['error'] = 'You do not have permission to add administrators. Only Super Admins can perform this action.';
    header('Location: ../dashboard.php');
    exit();
}

$page_title = 'Add Administrator - Admin Panel';
$admin_name = $_SESSION['user_name'] ?? 'Admin';

// =============================================
// PROCESS FORM SUBMISSION
// =============================================
$errors = [];
$success = false;
$form_data = [
    'full_name' => '',
    'username' => '',
    'email' => '',
    'role' => 'manager',
    'status' => 'active'
];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        $errors[] = 'Invalid security token. Please try again.';
    } else {
        // Sanitize input
        $form_data['full_name'] = sanitize($_POST['full_name'] ?? '');
        $form_data['username'] = sanitize($_POST['username'] ?? '');
        $form_data['email'] = sanitize($_POST['email'] ?? '');
        $form_data['role'] = sanitize($_POST['role'] ?? 'manager');
        $form_data['status'] = sanitize($_POST['status'] ?? 'active');
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        // Validation
        if (empty($form_data['full_name'])) {
            $errors[] = 'Full name is required';
        }
        
        if (empty($form_data['username'])) {
            $errors[] = 'Username is required';
        } elseif (!preg_match('/^[a-zA-Z0-9_]{3,20}$/', $form_data['username'])) {
            $errors[] = 'Username must be 3-20 characters and contain only letters, numbers, and underscores';
        } else {
            // Check if username exists
            $check_sql = "SELECT id FROM admins WHERE username = ?";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->bindParam(1, $form_data['username'], PDO::PARAM_STR);
            $check_stmt->execute();
            if ($check_stmt->rowCount() > 0) {
                $errors[] = 'Username already exists';
            }
        }
        
        if (empty($form_data['email'])) {
            $errors[] = 'Email is required';
        } elseif (!filter_var($form_data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Invalid email address';
        } else {
            // Check if email exists
            $check_sql = "SELECT id FROM admins WHERE email = ?";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->bindParam(1, $form_data['email'], PDO::PARAM_STR);
            $check_stmt->execute();
            if ($check_stmt->rowCount() > 0) {
                $errors[] = 'Email already exists';
            }
        }
        
        if (empty($password)) {
            $errors[] = 'Password is required';
        } elseif (strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters';
        } elseif (!preg_match('/[A-Z]/', $password)) {
            $errors[] = 'Password must contain at least one uppercase letter';
        } elseif (!preg_match('/[a-z]/', $password)) {
            $errors[] = 'Password must contain at least one lowercase letter';
        } elseif (!preg_match('/[0-9]/', $password)) {
            $errors[] = 'Password must contain at least one number';
        }
        
        if ($password !== $confirm_password) {
            $errors[] = 'Passwords do not match';
        }
        
        if (!in_array($form_data['role'], ['super_admin', 'manager', 'support'])) {
            $errors[] = 'Invalid role selected';
        }
        
        if (!in_array($form_data['status'], ['active', 'inactive'])) {
            $errors[] = 'Invalid status selected';
        }
        
        // If no errors, create admin
        if (empty($errors)) {
            try {
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                
                $insert_sql = "INSERT INTO admins (username, email, password_hash, full_name, role, status) 
                               VALUES (?, ?, ?, ?, ?, ?)";
                $insert_stmt = $conn->prepare($insert_sql);
                $insert_stmt->bindParam(1, $form_data['username'], PDO::PARAM_STR);
                $insert_stmt->bindParam(2, $form_data['email'], PDO::PARAM_STR);
                $insert_stmt->bindParam(3, $password_hash, PDO::PARAM_STR);
                $insert_stmt->bindParam(4, $form_data['full_name'], PDO::PARAM_STR);
                $insert_stmt->bindParam(5, $form_data['role'], PDO::PARAM_STR);
                $insert_stmt->bindParam(6, $form_data['status'], PDO::PARAM_STR);
                
                if ($insert_stmt->execute()) {
                    $admin_id = $conn->lastInsertId();
                    
                    // Log activity
                    log_activity('admin', $_SESSION['user_id'], 'add_admin', "Added new admin: {$form_data['username']} (ID: $admin_id)");
                    
                    // Send email notification
                    $email_subject = "Welcome to " . SITE_NAME . " Admin Panel";
                    $email_body = "
                        <h2>Welcome to " . SITE_NAME . " Admin Panel</h2>
                        <p>Hello {$form_data['full_name']},</p>
                        <p>An administrator account has been created for you on " . SITE_NAME . ".</p>
                        <p><strong>Login Credentials:</strong></p>
                        <ul>
                            <li><strong>Username:</strong> {$form_data['username']}</li>
                            <li><strong>Email:</strong> {$form_data['email']}</li>
                            <li><strong>Role:</strong> " . ucfirst(str_replace('_', ' ', $form_data['role'])) . "</li>
                        </ul>
                        <p>Please login at: <a href='" . SITE_URL . "admin/index.php'>" . SITE_URL . "admin/index.php</a></p>
                        <p>Best regards,<br>" . SITE_NAME . " Team</p>
                    ";
                    send_email($form_data['email'], $email_subject, $email_body);
                    
                    $_SESSION['success'] = 'Administrator added successfully! Email notification sent.';
                    header('Location: manage-users.php');
                    exit();
                } else {
                    $errors[] = 'Failed to add administrator. Please try again.';
                }
            } catch (PDOException $e) {
                $errors[] = 'Database error: ' . $e->getMessage();
            }
        }
    }
}

// Generate CSRF token if not set
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo $csrf_token; ?>">
    <title><?php echo $page_title; ?></title>
    
    <!-- CSS Dependencies -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../../assets/css/admin.css">
    <link rel="stylesheet" href="../../assets/css/style.css">
    
    <style>
        .form-section {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 20px;
        }
        
        .form-section .section-title {
            font-weight: 600;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f0f0f0;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: #0d6efd;
            box-shadow: 0 0 0 0.2rem rgba(13,110,253,0.25);
        }
        
        .password-strength {
            height: 4px;
            border-radius: 2px;
            margin-top: 5px;
            background: #ddd;
            transition: all 0.3s ease;
        }
        
        .password-strength.weak { background: #dc3545; width: 25%; }
        .password-strength.medium { background: #ffc107; width: 50%; }
        .password-strength.good { background: #0dcaf0; width: 75%; }
        .password-strength.strong { background: #198754; width: 100%; }
        
        .password-requirements {
            font-size: 12px;
            color: #6c757d;
            margin-top: 8px;
        }
        
        .password-requirements .requirement {
            padding: 2px 0;
        }
        
        .password-requirements .requirement.met {
            color: #198754;
        }
        
        .password-requirements .requirement i {
            margin-right: 5px;
        }
        
        .role-card {
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            padding: 15px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .role-card:hover {
            border-color: #0d6efd;
            background: #f8f9fa;
        }
        
        .role-card.selected {
            border-color: #0d6efd;
            background: #e7f1ff;
        }
        
        .role-card .role-icon {
            font-size: 2rem;
            margin-bottom: 10px;
        }
        
        .role-card .role-name {
            font-weight: 600;
        }
        
        .role-card .role-desc {
            font-size: 12px;
            color: #6c757d;
        }
        
        .form-label {
            font-weight: 500;
            font-size: 14px;
        }
        
        .required-star {
            color: #dc3545;
            margin-left: 3px;
        }
        
        @media (max-width: 768px) {
            .form-section {
                padding: 15px;
            }
        }
    </style>
</head>
<body>

<!-- Include admin navbar and sidebar -->
<?php include '../../includes/layouts/admin-navbar.php'; ?>

<div class="container-fluid">
    <div class="row">
        <?php include '../../includes/layouts/admin-sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
            
            <!-- Page Header -->
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">
                    <i class="fas fa-user-plus me-2 text-primary"></i>Add Administrator
                </h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <a href="manage-users.php" class="btn btn-sm btn-outline-secondary">
                        <i class="fas fa-arrow-left me-1"></i>Back to Users
                    </a>
                </div>
            </div>
            
            <!-- Error Messages -->
            <?php if (!empty($errors)): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="fas fa-exclamation-circle me-2"></i>
                <strong>Please fix the following errors:</strong>
                <ul class="mb-0 mt-2">
                    <?php foreach($errors as $error): ?>
                    <li><?php echo $error; ?></li>
                    <?php endforeach; ?>
                </ul>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>
            
            <!-- Success Message -->
            <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="fas fa-check-circle me-2"></i><?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>
            
            <!-- Main Form -->
            <form method="POST" action="" id="addAdminForm" novalidate>
                <?php echo csrf_field(); ?>
                
                <div class="row">
                    <!-- Personal Information -->
                    <div class="col-lg-8">
                        <div class="form-section">
                            <h5 class="section-title">
                                <i class="fas fa-user me-2 text-primary"></i>Personal Information
                            </h5>
                            
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="full_name" class="form-label">
                                        Full Name <span class="required-star">*</span>
                                    </label>
                                    <input type="text" 
                                           class="form-control" 
                                           id="full_name" 
                                           name="full_name" 
                                           value="<?php echo htmlspecialchars($form_data['full_name']); ?>" 
                                           placeholder="Enter full name" 
                                           required>
                                </div>
                                
                                <div class="col-md-6">
                                    <label for="username" class="form-label">
                                        Username <span class="required-star">*</span>
                                    </label>
                                    <input type="text" 
                                           class="form-control" 
                                           id="username" 
                                           name="username" 
                                           value="<?php echo htmlspecialchars($form_data['username']); ?>" 
                                           placeholder="Enter username (3-20 characters)" 
                                           pattern="[a-zA-Z0-9_]{3,20}" 
                                           required>
                                    <small class="text-muted">Username must be unique and 3-20 characters long</small>
                                </div>
                                
                                <div class="col-md-6">
                                    <label for="email" class="form-label">
                                        Email Address <span class="required-star">*</span>
                                    </label>
                                    <input type="email" 
                                           class="form-control" 
                                           id="email" 
                                           name="email" 
                                           value="<?php echo htmlspecialchars($form_data['email']); ?>" 
                                           placeholder="Enter email address" 
                                           required>
                                </div>
                                
                                <div class="col-md-6">
                                    <label for="role" class="form-label">
                                        Role <span class="required-star">*</span>
                                    </label>
                                    <select class="form-select" id="role" name="role">
                                        <option value="super_admin" <?php echo $form_data['role'] == 'super_admin' ? 'selected' : ''; ?>>Super Admin</option>
                                        <option value="manager" <?php echo $form_data['role'] == 'manager' ? 'selected' : ''; ?>>Manager</option>
                                        <option value="support" <?php echo $form_data['role'] == 'support' ? 'selected' : ''; ?>>Support Staff</option>
                                    </select>
                                    <small class="text-muted">Super Admin has full access, Manager has limited access, Support handles customer queries</small>
                                </div>
                                
                                <div class="col-md-6">
                                    <label for="status" class="form-label">Status</label>
                                    <select class="form-select" id="status" name="status">
                                        <option value="active" <?php echo $form_data['status'] == 'active' ? 'selected' : ''; ?>>Active</option>
                                        <option value="inactive" <?php echo $form_data['status'] == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Password Section -->
                        <div class="form-section">
                            <h5 class="section-title">
                                <i class="fas fa-lock me-2 text-primary"></i>Password
                            </h5>
                            
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="password" class="form-label">
                                        Password <span class="required-star">*</span>
                                    </label>
                                    <div class="input-group">
                                        <input type="password" 
                                               class="form-control" 
                                               id="password" 
                                               name="password" 
                                               placeholder="Enter password (min 8 characters)" 
                                               required>
                                        <button class="btn btn-outline-secondary toggle-password" type="button">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                    <div class="password-strength" id="passwordStrength"></div>
                                    
                                    <div class="password-requirements">
                                        <div class="requirement" id="req-length">
                                            <i class="fas fa-times text-danger"></i> At least 8 characters
                                        </div>
                                        <div class="requirement" id="req-uppercase">
                                            <i class="fas fa-times text-danger"></i> At least one uppercase letter
                                        </div>
                                        <div class="requirement" id="req-lowercase">
                                            <i class="fas fa-times text-danger"></i> At least one lowercase letter
                                        </div>
                                        <div class="requirement" id="req-number">
                                            <i class="fas fa-times text-danger"></i> At least one number
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <label for="confirm_password" class="form-label">
                                        Confirm Password <span class="required-star">*</span>
                                    </label>
                                    <div class="input-group">
                                        <input type="password" 
                                               class="form-control" 
                                               id="confirm_password" 
                                               name="confirm_password" 
                                               placeholder="Confirm password" 
                                               required>
                                        <button class="btn btn-outline-secondary toggle-password" type="button">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                    <div id="passwordMatch" class="mt-1"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Sidebar - Role Information -->
                    <div class="col-lg-4">
                        <div class="form-section">
                            <h5 class="section-title">
                                <i class="fas fa-info-circle me-2 text-primary"></i>Role Information
                            </h5>
                            
                            <div class="role-card selected" id="role-info">
                                <div class="text-center">
                                    <div class="role-icon" id="roleIcon">
                                        <i class="fas fa-user-cog text-primary"></i>
                                    </div>
                                    <div class="role-name" id="roleName">Manager</div>
                                    <div class="role-desc" id="roleDesc">Can manage users, products, and orders</div>
                                </div>
                                
                                <hr>
                                
                                <div id="rolePermissions">
                                    <small class="text-muted">Permissions:</small>
                                    <ul class="list-unstyled mt-2" id="permissionList">
                                        <li><i class="fas fa-check text-success me-2"></i>View Dashboard</li>
                                        <li><i class="fas fa-check text-success me-2"></i>Manage Users</li>
                                        <li><i class="fas fa-check text-success me-2"></i>Manage Products</li>
                                        <li><i class="fas fa-check text-success me-2"></i>Manage Orders</li>
                                        <li><i class="fas fa-times text-danger me-2"></i>Manage Payments</li>
                                        <li><i class="fas fa-times text-danger me-2"></i>Add Admins</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Quick Actions -->
                        <div class="form-section">
                            <h5 class="section-title">
                                <i class="fas fa-bolt me-2 text-primary"></i>Quick Actions
                            </h5>
                            
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="fas fa-user-plus me-2"></i>Add Administrator
                                </button>
                                <button type="reset" class="btn btn-outline-secondary">
                                    <i class="fas fa-undo me-2"></i>Reset Form
                                </button>
                            </div>
                            
                            <hr>
                            
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>
                                <small>The new administrator will receive an email with their login credentials.</small>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
            
        </main>
    </div>
</div>

<!-- ===================================================== -->
<!-- SCRIPTS -->
<!-- ===================================================== -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<script>
// Toggle password visibility
document.querySelectorAll('.toggle-password').forEach(function(btn) {
    btn.addEventListener('click', function() {
        const input = this.closest('.input-group').querySelector('input');
        const icon = this.querySelector('i');
        
        if (input.type === 'password') {
            input.type = 'text';
            icon.classList.remove('fa-eye');
            icon.classList.add('fa-eye-slash');
        } else {
            input.type = 'password';
            icon.classList.remove('fa-eye-slash');
            icon.classList.add('fa-eye');
        }
    });
});

// Password strength checker
document.getElementById('password').addEventListener('keyup', function() {
    const password = this.value;
    const strength = checkPasswordStrength(password);
    
    const strengthBar = document.getElementById('passwordStrength');
    strengthBar.className = 'password-strength';
    
    if (password.length > 0) {
        strengthBar.classList.add(strength.class);
    }
    
    // Update requirements
    updateRequirement('req-length', password.length >= 8);
    updateRequirement('req-uppercase', /[A-Z]/.test(password));
    updateRequirement('req-lowercase', /[a-z]/.test(password));
    updateRequirement('req-number', /[0-9]/.test(password));
    
    // Check password match
    checkPasswordMatch();
});

// Confirm password check
document.getElementById('confirm_password').addEventListener('keyup', function() {
    checkPasswordMatch();
});

// Role selection preview
document.getElementById('role').addEventListener('change', function() {
    const role = this.value;
    updateRoleInfo(role);
});

// Initial role display
updateRoleInfo(document.getElementById('role').value);

// =============================================
// HELPER FUNCTIONS
// =============================================

function checkPasswordStrength(password) {
    let score = 0;
    
    if (password.length >= 8) score++;
    if (/[A-Z]/.test(password)) score++;
    if (/[a-z]/.test(password)) score++;
    if (/[0-9]/.test(password)) score++;
    if (/[^A-Za-z0-9]/.test(password)) score++;
    
    if (score <= 2) return { class: 'weak', label: 'Weak' };
    if (score <= 3) return { class: 'medium', label: 'Medium' };
    if (score <= 4) return { class: 'good', label: 'Good' };
    return { class: 'strong', label: 'Strong' };
}

function updateRequirement(id, met) {
    const element = document.getElementById(id);
    if (!element) return;
    const icon = element.querySelector('i');
    
    if (met) {
        element.classList.add('met');
        icon.className = 'fas fa-check text-success';
    } else {
        element.classList.remove('met');
        icon.className = 'fas fa-times text-danger';
    }
}

function checkPasswordMatch() {
    const password = document.getElementById('password').value;
    const confirm = document.getElementById('confirm_password').value;
    const matchDiv = document.getElementById('passwordMatch');
    
    if (confirm.length > 0) {
        if (password === confirm) {
            matchDiv.innerHTML = '<i class="fas fa-check text-success"></i> Passwords match';
            document.getElementById('confirm_password').classList.remove('is-invalid');
            document.getElementById('confirm_password').classList.add('is-valid');
        } else {
            matchDiv.innerHTML = '<i class="fas fa-times text-danger"></i> Passwords do not match';
            document.getElementById('confirm_password').classList.remove('is-valid');
            document.getElementById('confirm_password').classList.add('is-invalid');
        }
    } else {
        matchDiv.innerHTML = '';
        document.getElementById('confirm_password').classList.remove('is-valid', 'is-invalid');
    }
}

function updateRoleInfo(role) {
    const roles = {
        'super_admin': {
            icon: 'fa-crown',
            name: 'Super Admin',
            desc: 'Full system access with all permissions',
            permissions: [
                'View Dashboard',
                'Manage Users',
                'Manage Products',
                'Manage Orders',
                'Manage Payments',
                'Add/Remove Admins',
                'System Settings',
                'View Reports'
            ]
        },
        'manager': {
            icon: 'fa-user-cog',
            name: 'Manager',
            desc: 'Can manage users, products, and orders',
            permissions: [
                'View Dashboard',
                'Manage Users',
                'Manage Products',
                'Manage Orders',
                'View Reports'
            ]
        },
        'support': {
            icon: 'fa-headset',
            name: 'Support Staff',
            desc: 'Handle customer queries and support tickets',
            permissions: [
                'View Dashboard',
                'View Orders',
                'Manage Support Tickets',
                'View User Details'
            ]
        }
    };
    
    const data = roles[role] || roles['manager'];
    
    document.getElementById('roleIcon').innerHTML = '<i class="fas ' + data.icon + ' text-primary"></i>';
    document.getElementById('roleName').textContent = data.name;
    document.getElementById('roleDesc').textContent = data.desc;
    
    let permissionHtml = '';
    const allPermissions = [
        'View Dashboard',
        'Manage Users',
        'Manage Products',
        'Manage Orders',
        'Manage Payments',
        'Add/Remove Admins',
        'System Settings',
        'View Reports',
        'Manage Support Tickets',
        'View User Details'
    ];
    
    allPermissions.forEach(function(perm) {
        const hasPerm = data.permissions.includes(perm);
        const icon = hasPerm ? 'fa-check text-success' : 'fa-times text-danger';
        permissionHtml += '<li><i class="fas ' + icon + ' me-2"></i>' + perm + '</li>';
    });
    
    document.getElementById('permissionList').innerHTML = permissionHtml;
}
</script>

</body>
</html>