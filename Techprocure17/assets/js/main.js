// =====================================================
// GLOBAL VARIABLES
// =====================================================
let cartCount = 0;
let currentPage = 1;
let isLoading = false;

// =====================================================
// DOCUMENT READY
// =====================================================
$(document).ready(function() {
    // Initialize tooltips
    $('[data-bs-toggle="tooltip"]').tooltip();
    
    // Initialize popovers
    $('[data-bs-toggle="popover"]').popover();
    
    // Update cart count
    updateCartCount();
    
    // Load notifications if on dashboard
    if ($('#notifications-list').length) {
        loadNotifications();
        setInterval(loadNotifications, 30000);
    }
    
    // Search functionality
    $('#search-input').on('keyup', function() {
        searchProducts($(this).val());
    });
    
    // Close search results on click outside
    $(document).on('click', function(e) {
        if (!$(e.target).closest('#search-container').length) {
            $('#search-results').hide();
        }
    });
    
    // Auto-hide alerts
    setTimeout(function() {
        $('.alert').fadeOut('slow');
    }, 5000);
    
    // Smooth scroll
    $('a[href*="#"]').on('click', function(e) {
        if (this.hash !== '') {
            e.preventDefault();
            const hash = this.hash;
            $('html, body').animate({
                scrollTop: $(hash).offset().top - 70
            }, 800);
        }
    });
    
    // Quantity input handlers
    $('.quantity-input').on('change', function() {
        const cartId = $(this).data('cart-id');
        const quantity = $(this).val();
        updateCartQuantity(cartId, quantity);
    });
    
    // Payment method selection
    $('.payment-method').on('click', function() {
        $('.payment-method').removeClass('selected');
        $(this).addClass('selected');
        $('#selected-payment-method').val($(this).data('method'));
    });
});

// =====================================================
// CART FUNCTIONS
// =====================================================
function addToCart(productId, quantity = 1) {
    $.ajax({
        url: SITE_URL + 'ajax/cart.php',
        type: 'POST',
        data: {
            action: 'add',
            product_id: productId,
            quantity: quantity,
            csrf_token: $('meta[name="csrf-token"]').attr('content')
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showToast('Success', 'Product added to cart', 'success');
                updateCartCount();
                
                // Animate cart icon
                $('.fa-shopping-cart').addClass('fa-bounce');
                setTimeout(function() {
                    $('.fa-shopping-cart').removeClass('fa-bounce');
                }, 1000);
            } else {
                showToast('Error', response.message, 'error');
            }
        },
        error: function() {
            showToast('Error', 'Something went wrong', 'error');
        }
    });
}

function updateCartCount() {
    $.ajax({
        url: SITE_URL + 'ajax/cart.php',
        type: 'GET',
        data: { action: 'count' },
        dataType: 'json',
        success: function(response) {
            cartCount = response.count;
            $('#cartCount').text(cartCount);
            
            if (cartCount === 0) {
                $('#cartCount').hide();
            } else {
                $('#cartCount').show();
            }
        }
    });
}

function updateCartQuantity(cartId, quantity) {
    if (quantity < 1) {
        removeFromCart(cartId);
        return;
    }
    
    $.ajax({
        url: SITE_URL + 'ajax/cart.php',
        type: 'POST',
        data: {
            action: 'update',
            cart_id: cartId,
            quantity: quantity
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                $(`#item-${cartId} .item-total`).text(formatPrice(response.item_total));
                $('#cart-subtotal').text(formatPrice(response.subtotal));
                $('#cart-total').text(formatPrice(response.total));
                
                if (response.discount) {
                    $('#cart-discount').text(formatPrice(response.discount));
                }
            }
        }
    });
}

function removeFromCart(cartId) {
    if (confirm('Remove this item from cart?')) {
        $.ajax({
            url: SITE_URL + 'ajax/cart.php',
            type: 'POST',
            data: {
                action: 'remove',
                cart_id: cartId
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    $(`#item-${cartId}`).remove();
                    $('#cart-subtotal').text(formatPrice(response.subtotal));
                    $('#cart-total').text(formatPrice(response.total));
                    updateCartCount();
                    
                    if ($('.cart-item').length === 0) {
                        location.reload();
                    }
                }
            }
        });
    }
}

// =====================================================
// WISHLIST FUNCTIONS
// =====================================================
function toggleWishlist(productId) {
    $.ajax({
        url: SITE_URL + 'ajax/wishlist.php',
        type: 'POST',
        data: {
            action: 'toggle',
            product_id: productId
        },
        dataType: 'json',
        success: function(response) {
            if (response.added) {
                $(`#wishlist-btn-${productId}`).addClass('text-danger');
                showToast('Success', 'Added to wishlist', 'success');
            } else {
                $(`#wishlist-btn-${productId}`).removeClass('text-danger');
                showToast('Info', 'Removed from wishlist', 'info');
            }
        }
    });
}

// =====================================================
// PRODUCT COMPARISON
// =====================================================
let compareProducts = JSON.parse(localStorage.getItem('compare_products') || '[]');

function addToCompare(productId) {
    if (compareProducts.includes(productId)) {
        showToast('Info', 'Product already in comparison', 'info');
        return;
    }
    
    if (compareProducts.length >= 4) {
        showToast('Warning', 'You can compare up to 4 products', 'warning');
        return;
    }
    
    compareProducts.push(productId);
    localStorage.setItem('compare_products', JSON.stringify(compareProducts));
    showToast('Success', 'Product added to comparison', 'success');
    updateCompareCount();
}

function updateCompareCount() {
    $('#compare-count').text(compareProducts.length);
}

// =====================================================
// SEARCH FUNCTIONS
// =====================================================
let searchTimeout;

function searchProducts(keyword) {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        if (keyword.length >= 2) {
            $.ajax({
                url: SITE_URL + 'ajax/search.php',
                type: 'GET',
                data: { q: keyword },
                dataType: 'json',
                success: function(response) {
                    if (response.html) {
                        $('#search-results').html(response.html).show();
                    } else {
                        $('#search-results').hide();
                    }
                }
            });
        } else {
            $('#search-results').hide();
        }
    }, 300);
}

// =====================================================
// FILTER FUNCTIONS
// =====================================================
function filterProducts() {
    const filters = {
        category: $('#category-filter').val(),
        min_price: $('#min-price').val(),
        max_price: $('#max-price').val(),
        brand: $('#brand-filter').val(),
        sort: $('#sort-by').val()
    };
    
    $.ajax({
        url: SITE_URL + 'ajax/products.php',
        type: 'GET',
        data: filters,
        dataType: 'json',
        success: function(response) {
            $('#products-container').html(response.html);
            $('#pagination').html(response.pagination);
        }
    });
}

// =====================================================
// NOTIFICATION FUNCTIONS
// =====================================================
function loadNotifications() {
    $.ajax({
        url: SITE_URL + 'ajax/notifications.php',
        type: 'GET',
        dataType: 'json',
        success: function(response) {
            if (response.html) {
                $('#notifications-dropdown').html(response.html);
                $('#notification-count').text(response.unread_count);
                
                if (response.unread_count > 0) {
                    $('#notification-count').show();
                } else {
                    $('#notification-count').hide();
                }
            }
        }
    });
}

function markNotificationRead(notificationId) {
    $.ajax({
        url: SITE_URL + 'ajax/notifications.php',
        type: 'POST',
        data: {
            action: 'read',
            id: notificationId
        },
        success: function() {
            loadNotifications();
        }
    });
}

// =====================================================
// PAYMENT FUNCTIONS
// =====================================================
function processPayment(orderId, method) {
    const btn = $('#payment-btn');
    btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Processing...');
    
    let phoneNumber = null;
    if (['mpesa', 'airtel_money', 'tigo_pesa', 'halopesa', 'azam_pesa'].includes(method)) {
        phoneNumber = $('#phone-number').val();
        if (!phoneNumber) {
            showToast('Error', 'Please enter your mobile number', 'error');
            btn.prop('disabled', false).html('Pay Now');
            return;
        }
    }
    
    $.ajax({
        url: SITE_URL + 'ajax/payment.php',
        type: 'POST',
        data: {
            order_id: orderId,
            payment_method: method,
            phone_number: phoneNumber
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                if (response.redirect_url) {
                    window.location.href = response.redirect_url;
                } else if (response.checkout_request_id) {
                    showToast('M-Pesa', 'STK Push sent to your phone. Please enter PIN.', 'info');
                    checkPaymentStatus(response.checkout_request_id, orderId);
                } else {
                    window.location.href = SITE_URL + 'payment/success.php?id=' + orderId;
                }
            } else {
                showToast('Payment Failed', response.message, 'error');
                btn.prop('disabled', false).html('Pay Now');
            }
        },
        error: function() {
            showToast('Error', 'Payment processing failed', 'error');
            btn.prop('disabled', false).html('Pay Now');
        }
    });
}

function checkPaymentStatus(checkoutRequestId, orderId) {
    let interval = setInterval(function() {
        $.ajax({
            url: SITE_URL + 'ajax/mpesa.php',
            type: 'POST',
            data: {
                action: 'check_status',
                checkout_request_id: checkoutRequestId
            },
            dataType: 'json',
            success: function(response) {
                if (response.completed) {
                    clearInterval(interval);
                    if (response.success) {
                        window.location.href = SITE_URL + 'payment/success.php?id=' + orderId;
                    } else {
                        showToast('Payment Failed', response.message, 'error');
                        $('#payment-btn').prop('disabled', false).html('Pay Now');
                    }
                }
            }
        });
    }, 3000);
}

// =====================================================
// REVIEW FUNCTIONS
// =====================================================
function submitRating(productId, rating, comment) {
    $.ajax({
        url: SITE_URL + 'ajax/review.php',
        type: 'POST',
        data: {
            product_id: productId,
            rating: rating,
            comment: comment
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showToast('Thank You', 'Your review has been submitted', 'success');
                setTimeout(function() {
                    location.reload();
                }, 1500);
            } else {
                showToast('Error', response.message, 'error');
            }
        }
    });
}

// =====================================================
// UTILITY FUNCTIONS
// =====================================================
function showToast(title, message, type = 'info') {
    const bgColor = type === 'success' ? 'bg-success' : (type === 'error' ? 'bg-danger' : 'bg-info');
    const icon = type === 'success' ? 'fa-check-circle' : (type === 'error' ? 'fa-exclamation-circle' : 'fa-info-circle');
    
    const toastHtml = `
        <div class="toast align-items-center text-white ${bgColor} border-0" role="alert" data-bs-autohide="true">
            <div class="d-flex">
                <div class="toast-body">
                    <i class="fas ${icon} me-2"></i>
                    <strong>${title}</strong><br>
                    ${message}
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        </div>
    `;
    
    $('.toast-container').append(toastHtml);
    const toastElement = $('.toast').last();
    const toast = new bootstrap.Toast(toastElement, { delay: 3000 });
    toast.show();
    
    setTimeout(function() {
        toastElement.remove();
    }, 3500);
}

function formatPrice(price) {
    return CURRENCY_SYMBOL + ' ' + parseFloat(price).toLocaleString('en-TZ', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });
}

function loadMoreProducts() {
    if (isLoading) return;
    isLoading = true;
    
    currentPage++;
    $.ajax({
        url: SITE_URL + 'ajax/products.php',
        type: 'GET',
        data: { page: currentPage, load_more: true },
        dataType: 'json',
        success: function(response) {
            if (response.html) {
                $('#products-container').append(response.html);
            }
            if (!response.has_more) {
                $('#load-more-btn').hide();
            }
            isLoading = false;
        },
        error: function() {
            isLoading = false;
        }
    });
}

// Initialize star rating inputs
function initStarRating() {
    $('.star-rating i').on('click', function() {
        const rating = $(this).data('rating');
        const container = $(this).closest('.rating-input');
        
        container.find('i').each(function(index) {
            if (index < rating) {
                $(this).removeClass('far').addClass('fas text-warning');
            } else {
                $(this).removeClass('fas text-warning').addClass('far');
            }
        });
        
        container.find('input.rating-value').val(rating);
    });
}

// Set currency symbol from PHP
const CURRENCY_SYMBOL = 'TSh';
const SITE_URL = window.location.origin + '/TechProcure/';

// Initialize on load
$(document).ready(function() {
    initStarRating();
    updateCompareCount();
});