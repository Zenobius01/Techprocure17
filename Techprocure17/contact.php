<?php
/**
 * TechProcure Tanzania - Contact Us Page
 * File: contact.php
 * Description: Contact form and company information
 */

// Start session if not started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include configuration files
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';

$page_title = 'Contact Us - TechProcure Tanzania';
$meta_description = 'Get in touch with TechProcure Tanzania. Our team is here to help with your IT procurement needs. Contact us for support, inquiries, or partnership opportunities.';

// Get database connection
$db = getDB();

// Initialize variables
$error = '';
$success = '';
$name = '';
$email = '';
$phone = '';
$subject = '';
$message = '';

// Process contact form
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        $error = 'Security token validation failed. Please try again.';
    } else {
        // Get form data
        $name = sanitizeInput($_POST['name'] ?? '');
        $email = sanitizeInput($_POST['email'] ?? '');
        $phone = sanitizeInput($_POST['phone'] ?? '');
        $subject = sanitizeInput($_POST['subject'] ?? '');
        $message = sanitizeInput($_POST['message'] ?? '');
        
        // Validation
        if (empty($name) || empty($email) || empty($subject) || empty($message)) {
            $error = 'Please fill in all required fields.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Invalid email address.';
        } elseif (strlen($message) < 10) {
            $error = 'Message must be at least 10 characters long.';
        } else {
            try {
                // Check if contact_messages table exists
                $table_check = $db->query("SHOW TABLES LIKE 'contact_messages'");
                if ($table_check->rowCount() > 0) {
                    // Insert into database
                    $sql = "INSERT INTO contact_messages (name, email, phone, subject, message, is_read, created_at) 
                            VALUES (?, ?, ?, ?, ?, 0, NOW())";
                    $stmt = $db->prepare($sql);
                    $stmt->execute([$name, $email, $phone, $subject, $message]);
                } else {
                    // Create table if not exists
                    $db->exec("
                        CREATE TABLE IF NOT EXISTS contact_messages (
                            id INT PRIMARY KEY AUTO_INCREMENT,
                            name VARCHAR(200) NOT NULL,
                            email VARCHAR(255) NOT NULL,
                            phone VARCHAR(20),
                            subject VARCHAR(255) NOT NULL,
                            message TEXT NOT NULL,
                            is_read BOOLEAN DEFAULT FALSE,
                            replied BOOLEAN DEFAULT FALSE,
                            ip_address VARCHAR(45),
                            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                        )
                    ");
                    
                    // Insert after table creation
                    $sql = "INSERT INTO contact_messages (name, email, phone, subject, message, is_read, created_at) 
                            VALUES (?, ?, ?, ?, ?, 0, NOW())";
                    $stmt = $db->prepare($sql);
                    $stmt->execute([$name, $email, $phone, $subject, $message]);
                }
                
                // Send email notification (optional)
                try {
                    $admin_email = ADMIN_EMAIL ?? 'info@techprocure.co.tz';
                    $email_subject = "New Contact Message: " . $subject;
                    $email_message = "
                        <h2>New Contact Message</h2>
                        <p><strong>Name:</strong> " . htmlspecialchars($name) . "</p>
                        <p><strong>Email:</strong> " . htmlspecialchars($email) . "</p>
                        <p><strong>Phone:</strong> " . htmlspecialchars($phone) . "</p>
                        <p><strong>Subject:</strong> " . htmlspecialchars($subject) . "</p>
                        <p><strong>Message:</strong></p>
                        <p>" . nl2br(htmlspecialchars($message)) . "</p>
                        <p><small>Sent from TechProcure Contact Form</small></p>
                    ";
                    
                    // Uncomment to enable email sending
                    // sendEmail($admin_email, $email_subject, $email_message);
                } catch (Exception $e) {
                    // Email sending failed, but form submission succeeded
                }
                
                $success = 'Thank you for contacting us! We will get back to you within 24-48 hours.';
                
                // Clear form data
                $name = $email = $phone = $subject = $message = '';
                
            } catch (PDOException $e) {
                $error = 'Failed to send message. Please try again later.';
            }
        }
    }
}

// Generate CSRF token
$csrf_token = generateCSRFToken();

// Get cart count
$cart_count = getCartCount();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?php echo $meta_description; ?>">
    <meta name="keywords" content="Contact TechProcure, B2B procurement Tanzania, IT equipment supplier, support">
    <title><?php echo $page_title; ?></title>
    
    <!-- CSS Dependencies -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/aos/2.3.4/aos.css">
    
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f8f9fa;
        }
        
        /* Navbar */
        .navbar {
            background: linear-gradient(135deg, #1a2a6c, #b21f1f, #fdbb4d);
            padding: 15px 0;
        }
        
        .navbar-brand {
            color: white !important;
            font-weight: 800;
            font-size: 1.5rem;
        }
        
        .navbar-brand i {
            margin-right: 8px;
        }
        
        .nav-link {
            color: rgba(255,255,255,0.9) !important;
            font-weight: 500;
        }
        
        .nav-link:hover {
            color: white !important;
        }
        
        .cart-count {
            position: absolute;
            top: -8px;
            right: -12px;
            background: #dc3545;
            color: white;
            border-radius: 50%;
            padding: 2px 6px;
            font-size: 10px;
            font-weight: bold;
            min-width: 18px;
            text-align: center;
        }
        
        .btn-login {
            background: rgba(255,255,255,0.2);
            border: 1px solid rgba(255,255,255,0.3);
            color: white !important;
            border-radius: 50px;
            padding: 5px 15px;
            text-decoration: none;
        }
        
        .btn-login:hover {
            background: rgba(255,255,255,0.3);
            color: white !important;
        }
        
        .btn-register {
            background: #fdbb4d;
            color: #1a2a6c !important;
            border-radius: 50px;
            padding: 5px 15px;
            font-weight: 600;
            text-decoration: none;
        }
        
        .btn-register:hover {
            background: #ffc107;
            color: #1a2a6c !important;
        }
        
        /* Page Header */
        .page-header {
            background: linear-gradient(135deg, #1a2a6c, #b21f1f, #fdbb4d);
            color: white;
            padding: 60px 0;
        }
        
        .page-header h1 {
            font-size: 2.5rem;
            font-weight: 800;
        }
        
        /* Contact Section */
        .contact-section {
            padding: 60px 0;
        }
        
        .contact-info-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
            transition: all 0.3s;
            height: 100%;
            text-align: center;
        }
        
        .contact-info-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        
        .contact-info-card .icon {
            font-size: 2.5rem;
            margin-bottom: 15px;
        }
        
        .contact-info-card .icon.primary { color: #0d6efd; }
        .contact-info-card .icon.success { color: #198754; }
        .contact-info-card .icon.warning { color: #ffc107; }
        .contact-info-card .icon.danger { color: #dc3545; }
        
        .contact-form-card {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
        }
        
        .form-control {
            border-radius: 10px;
            padding: 12px 15px;
            border: 1px solid #e0e0e0;
            transition: all 0.3s;
        }
        
        .form-control:focus {
            border-color: #0d6efd;
            box-shadow: 0 0 0 3px rgba(13,110,253,0.1);
            outline: none;
        }
        
        .form-label {
            font-weight: 500;
            font-size: 0.9rem;
        }
        
        .btn-submit {
            background: linear-gradient(135deg, #0d6efd, #0b5ed7);
            border: none;
            border-radius: 10px;
            padding: 12px;
            font-weight: 600;
            font-size: 1rem;
            width: 100%;
            color: white;
            transition: all 0.3s;
        }
        
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(13,110,253,0.3);
        }
        
        .btn-submit:disabled {
            opacity: 0.7;
            cursor: not-allowed;
            transform: none;
        }
        
        .loading-spinner {
            display: none;
        }
        
        .btn-submit .loading-spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 2px solid rgba(255,255,255,0.3);
            border-radius: 50%;
            border-top-color: #fff;
            animation: spin 0.8s ease-in-out infinite;
            margin-right: 10px;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        .alert {
            border-radius: 10px;
            margin-bottom: 20px;
        }
        
        .alert-success {
            border-left: 4px solid #198754;
        }
        
        .alert-danger {
            border-left: 4px solid #dc3545;
        }
        
        /* Map Section */
        .map-container {
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
        }
        
        .map-container iframe {
            width: 100%;
            height: 350px;
            border: none;
        }
        
        /* Footer */
        .footer {
            background: #1a1a2e;
            color: white;
            padding: 60px 0 30px;
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
            .page-header h1 {
                font-size: 1.8rem;
            }
            .contact-info-card {
                margin-bottom: 20px;
            }
            .map-container iframe {
                height: 200px;
            }
        }
    </style>
</head>
<body>

<!-- Navbar -->

<!-- Page Header -->
<section class="page-header">
    <div class="container">
        <h1><i class="fas fa-envelope me-3"></i>Contact Us</h1>
        <p class="lead mb-0">We'd love to hear from you. Get in touch with our team.</p>
    </div>
</section>

<!-- Contact Section -->
<section class="contact-section">
    <div class="container">
        <!-- Success/Error Messages -->
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
        
        <div class="row">
            <!-- Contact Information -->
           
            
            <!-- Contact Form -->
            <div class="col-lg-8">
                <div class="contact-form-card">
                    <h3 class="mb-4"><i class="fas fa-paper-plane me-2 text-primary"></i>Send Us a Message</h3>
                    
                    <?php if(!$success): ?>
                    <form method="POST" action="" id="contactForm">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label required">Full Name</label>
                                <input type="text" name="name" class="form-control" 
                                       placeholder="Enter your full name" 
                                       value="<?php echo htmlspecialchars($name); ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label required">Email Address</label>
                                <input type="email" name="email" class="form-control" 
                                       placeholder="Enter your email address" 
                                       value="<?php echo htmlspecialchars($email); ?>" required>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Phone Number</label>
                                <input type="tel" name="phone" class="form-control" 
                                       placeholder="Enter your phone number" 
                                       value="<?php echo htmlspecialchars($phone); ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label required">Subject</label>
                                <select name="subject" class="form-select" required>
                                    <option value="">Select Subject</option>
                                    <option value="General Inquiry" <?php echo $subject == 'General Inquiry' ? 'selected' : ''; ?>>General Inquiry</option>
                                    <option value="Sales Inquiry" <?php echo $subject == 'Sales Inquiry' ? 'selected' : ''; ?>>Sales Inquiry</option>
                                    <option value="Supplier Partnership" <?php echo $subject == 'Supplier Partnership' ? 'selected' : ''; ?>>Supplier Partnership</option>
                                    <option value="Technical Support" <?php echo $subject == 'Technical Support' ? 'selected' : ''; ?>>Technical Support</option>
                                    <option value="Billing Question" <?php echo $subject == 'Billing Question' ? 'selected' : ''; ?>>Billing Question</option>
                                    <option value="Feedback" <?php echo $subject == 'Feedback' ? 'selected' : ''; ?>>Feedback</option>
                                    <option value="Other" <?php echo $subject == 'Other' ? 'selected' : ''; ?>>Other</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label required">Message</label>
                            <textarea name="message" class="form-control" rows="5" 
                                      placeholder="Write your message here..." required><?php echo htmlspecialchars($message); ?></textarea>
                            <small class="text-muted">Minimum 10 characters</small>
                        </div>
                        
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="agreeTerms" required>
                            <label class="form-check-label" for="agreeTerms">
                                I agree to the <a href="terms.php" target="_blank">Terms & Conditions</a> and 
                                <a href="privacy.php" target="_blank">Privacy Policy</a>
                            </label>
                        </div>
                        
                        <button type="submit" class="btn-submit" id="submitBtn">
                            <span class="loading-spinner" id="loadingSpinner"></span>
                            <i class="fas fa-paper-plane me-2" id="submitIcon"></i> Send Message
                        </button>
                    </form>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fas fa-check-circle text-success fa-4x mb-3"></i>
                            <h4>Message Sent Successfully!</h4>
                            <p class="text-muted">Thank you for contacting us. We will get back to you within 24-48 hours.</p>
                            <a href="contact.php" class="btn btn-primary mt-3">
                                <i class="fas fa-undo me-1"></i>Send Another Message
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Map Section -->


<!-- FAQ Section -->
<section class="py-5">
    <div class="container">
        <div class="text-center mb-5" data-aos="fade-up">
            <h2 class="display-5 fw-bold">Frequently Asked Questions</h2>
            <p class="text-muted">Quick answers to common questions</p>
        </div>
        <div class="row g-4">
            <div class="col-md-6" data-aos="fade-up" data-aos-delay="100">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <h5><i class="fas fa-question-circle text-primary me-2"></i>How do I register as a buyer?</h5>
                        <p class="text-muted">Click on the "Register" button in the top right corner and fill in the registration form. Choose "Customer" as your account type.</p>
                    </div>
                </div>
            </div>
            <div class="col-md-6" data-aos="fade-up" data-aos-delay="200">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <h5><i class="fas fa-question-circle text-primary me-2"></i>How do I become a supplier?</h5>
                        <p class="text-muted">Register as a supplier through our registration page. Our team will review your application and verify your business before approval.</p>
                    </div>
                </div>
            </div>
            <div class="col-md-6" data-aos="fade-up" data-aos-delay="300">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <h5><i class="fas fa-question-circle text-primary me-2"></i>What payment methods are accepted?</h5>
                        <p class="text-muted">We accept M-Pesa, Airtel Money, Tigo Pesa, Halopesa, Bank Transfer, and Credit/Debit Cards.</p>
                    </div>
                </div>
            </div>
            <div class="col-md-6" data-aos="fade-up" data-aos-delay="400">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <h5><i class="fas fa-question-circle text-primary me-2"></i>How does escrow payment work?</h5>
                        <p class="text-muted">Your payment is held securely in escrow until you confirm delivery of the products. This protects both buyers and sellers.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

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
                    <li><a href="index.php">Home</a></li>
                    <li><a href="products.php">Products</a></li>
                    <li><a href="categories.php">Categories</a></li>
                    <li><a href="suppliers.php">Suppliers</a></li>
                </ul>
            </div>
            <div class="col-md-3 mb-4">
                <h6>Customer Service</h6>
                <ul class="list-unstyled">
                    <li><a href="about.php">About Us</a></li>
                    <li><a href="contact.php">Contact Us</a></li>
                    <li><a href="faq.php">FAQ</a></li>
                    <li><a href="terms.php">Terms & Conditions</a></li>
                </ul>
            </div>
            <div class="col-md-3 mb-4">
                <h6>Contact Us</h6>
                <ul class="list-unstyled">
                    <li><i class="fas fa-envelope me-2"></i> support@techprocure.co.tz</li>
                    <li><i class="fas fa-phone me-2"></i> +255 123 456 789</li>
                    <li><i class="fas fa-map-marker-alt me-2"></i> Dar es Salaam, Tanzania</li>
                </ul>
            </div>
        </div>
        <hr class="mt-4 mb-3" style="border-color: rgba(255,255,255,0.1);">
        <div class="text-center">
            <small>&copy; <?php echo date('Y'); ?> TechProcure Tanzania. All rights reserved.</small>
        </div>
    </div>
</footer>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/aos/2.3.4/aos.js"></script>

<script>
    // Initialize AOS
    AOS.init({
        duration: 800,
        once: true,
        offset: 100
    });
    
    // Form submission loading state
    document.getElementById('contactForm')?.addEventListener('submit', function(e) {
        const btn = document.getElementById('submitBtn');
        const icon = document.getElementById('submitIcon');
        const spinner = document.getElementById('loadingSpinner');
        
        btn.disabled = true;
        spinner.style.display = 'inline-block';
        icon.style.display = 'none';
        btn.innerHTML = '<span class="loading-spinner" style="display:inline-block;"></span> Sending Message...';
    });
    
    // Auto-hide alerts after 5 seconds
    setTimeout(function() {
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(function(alert) {
            const bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        });
    }, 5000);
    
    // Update cart count
    function updateCartCount() {
        $.ajax({
            url: 'ajax/cart.php',
            type: 'POST',
            data: { action: 'get_count' },
            success: function(response) {
                try {
                    var data = JSON.parse(response);
                    $('.cart-count').text(data.count || 0);
                } catch(e) {
                    console.log('Error updating cart count');
                }
            }
        });
    }
    
    updateCartCount();
</script>

</body>
</html>