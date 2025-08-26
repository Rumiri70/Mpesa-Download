jQuery(document).ready(function($) {
    let currentPaymentId = null;
    let statusCheckInterval = null;
    
    // Helper function to extract error message
    function getErrorMessage(data) {
        if (typeof data === 'string') {
            return data;
        } else if (data && typeof data === 'object') {
            if (data.message) return data.message;
            if (data.error) return data.error;
            return 'An error occurred. Please try again.';
        }
        return 'An error occurred. Please try again.';
    }
    
    // Open payment modal
    $('#mpesa-download-btn').on('click', function() {
        $('#mpesa-payment-modal').show();
    });
    
    // Close modals
    $('.mpesa-close, .mpesa-cancel').on('click', function() {
        $('.mpesa-modal').hide();
        clearInterval(statusCheckInterval);
        resetForms();
    });
    
    // Close modal when clicking outside
    $(window).on('click', function(event) {
        if ($(event.target).hasClass('mpesa-modal')) {
            $('.mpesa-modal').hide();
            clearInterval(statusCheckInterval);
            resetForms();
        }
    });
    
    // Handle payment form submission
    $('#mpesa-payment-form').on('submit', function(e) {
        e.preventDefault();
        
        const phoneNumber = $('#phone_number').val().trim();
        const firstName = $('#first_name').val().trim();
        const amount = $('input[name="amount"]').val();
        
        if (!phoneNumber || !firstName || !amount) {
            showStatus('error', 'Please fill all required fields');
            return;
        }
        
        // Validate phone number
        if (!phoneNumber.match(/^254[0-9]{9}$/)) {
            showStatus('error', 'Please enter a valid phone number (254XXXXXXXXX)');
            return;
        }
        
        // Disable form and show loading
        $('#mpesa-pay-btn').prop('disabled', true).text('Processing...');
        showStatus('info', 'Initiating payment...');
        
        // Send payment request
        $.ajax({
            url: mpesa_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'process_mpesa_payment',
                nonce: mpesa_ajax.nonce,
                phone_number: phoneNumber,
                first_name: firstName,
                amount: amount
            },
            success: function(response) {
                if (response.success) {
                    currentPaymentId = response.data.payment_id;
                    showStatus('success', response.data.message);
                    
                    // Start checking payment status
                    startStatusCheck();
                } else {
                    // Fixed error message handling
                    const errorMsg = getErrorMessage(response.data);
                    showStatus('error', errorMsg);
                    resetPaymentForm();
                }
            },
            error: function(xhr, status, error) {
                let message = 'An error occurred. Please try again.';
                if (xhr && xhr.responseText) {
                    try {
                        const response = JSON.parse(xhr.responseText);
                        message = getErrorMessage(response.data);
                    } catch (e) {
                        message = 'Connection error. Please check your internet and try again.';
                    }
                }
                showStatus('error', message);
                resetPaymentForm();
            }
        });
    });
    
    // Handle name verification form
    $('#mpesa-name-form').on('submit', function(e) {
        e.preventDefault();
        
        const realName = $('#real_name').val().trim();
        
        if (!realName) {
            showStatus('error', 'Please enter your real name');
            return;
        }
        
        $.ajax({
            url: mpesa_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'verify_name',
                nonce: mpesa_ajax.nonce,
                payment_id: currentPaymentId,
                real_name: realName
            },
            success: function(response) {
                if (response.success) {
                    if (response.data.verified) {
                        // Name verified - proceed to download
                        showStatus('success', 'Payment verified! Your download will start now.');
                        setTimeout(function() {
                            if (response.data.download_url) {
                                const link = document.createElement('a');
                                link.href = response.data.download_url;
                                link.download = '';
                                document.body.appendChild(link);
                                link.click();
                                document.body.removeChild(link);
                            }
                            $('.mpesa-modal').hide();
                            resetForms();
                        }, 2000);
                    }
                } else {
                    const errorMsg = getErrorMessage(response.data);
                    showStatus('error', errorMsg);
                    $('#mpesa-name-modal').hide();
                    resetForms();
                }
            },
            error: function() {
                showStatus('error', 'An error occurred during name verification. Please contact us at +254 727 054 097 for help.');
                $('#mpesa-name-modal').hide();
                resetForms();
            }
        });
    });
    
    function startStatusCheck() {
        statusCheckInterval = setInterval(function() {
            checkPaymentStatus();
        }, 10000); // Check every 10 seconds
        
        // Stop checking after 5 minutes
        setTimeout(function() {
            clearInterval(statusCheckInterval);
            showStatus('warning', 'Payment verification timeout. Please contact "+254 727 054 097" for support if payment was made.');
            resetPaymentForm();
        }, 300000);
    }
    
// Fixed version - NEVER allow download without proper name verification
function checkPaymentStatus() {
    $.ajax({
        url: mpesa_ajax.ajax_url,
        type: 'POST',
        data: {
            action: 'verify_payment_status',
            nonce: mpesa_ajax.nonce,
            payment_id: currentPaymentId
        },
        success: function(response) {
            if (response.success) {
                const status = response.data.status;
                const mpesaName = response.data.mpesa_name || '';
                const enteredName = response.data.entered_name || '';
                
                console.log('Payment Status:', status);
                console.log('M-Pesa Name:', mpesaName);
                console.log('Entered Name:', enteredName);
                
                switch (status) {
                    case 'done':
                        clearInterval(statusCheckInterval);
                        
                        // CRITICAL: Only proceed if we have M-Pesa name for verification
                        if (mpesaName && mpesaName.trim() !== '') {
                            // We have M-Pesa name - do automatic verification
                            autoVerifyName(enteredName, mpesaName);
                        } else {
                            // NO M-PESA NAME YET - Keep waiting and checking
                            showStatus('info', 'Payment received. Waiting for name confirmation...');
                            
                            // Start a new interval to keep checking for M-Pesa name
                            setTimeout(function() {
                                // Check again in 10 seconds for M-Pesa name
                                if (currentPaymentId) {
                                    checkPaymentStatus();
                                }
                            }, 10000);
                            
                            // After 2 minutes of waiting, require manual verification
                            setTimeout(function() {
                                if (currentPaymentId) {
                                    showStatus('warning', 'Name verification required. Please enter your M-Pesa name manually.');
                                    $('#mpesa-payment-modal').hide();
                                    $('#mpesa-name-modal').show();
                                }
                            }, 120000); // 2 minutes
                        }
                        break;
                        
                    case 'success':
                        // Payment already verified - safe to download
                        clearInterval(statusCheckInterval);
                        showStatus('success', 'Payment verified! Your download will start now.');
                        setTimeout(function() {
                            startDownload();
                        }, 2000);
                        break;
                        
                    case 'stk_canceled':
                        clearInterval(statusCheckInterval);
                        showStatus('error', 'STK Push was canceled. Please try again.');
                        resetPaymentForm();
                        break;
                        
                    case 'invalid_name':
                        clearInterval(statusCheckInterval);
                        showStatus('error', 'Name verification failed. Please contact us for help.');
                        resetPaymentForm();
                        break;
                        
                    case 'failed':
                        clearInterval(statusCheckInterval);
                        showStatus('error', 'Payment failed. Please try again.');
                        resetPaymentForm();
                        break;
                        
                    case 'pending':
                        showStatus('info', 'Waiting for payment confirmation...');
                        break;
                        
                    default:
                        showStatus('info', 'Checking payment status...');
                }
            } else {
                const errorMsg = getErrorMessage(response.data);
                showStatus('error', 'Failed to check payment status: ' + errorMsg);
            }
        },
        error: function() {
            showStatus('warning', 'Connection error while checking status...');
        }
    });
}

// Enhanced automatic name verification
function autoVerifyName(enteredName, mpesaName) {
    // Clean names for comparison
    const cleanEntered = enteredName.toLowerCase().trim();
    const cleanMpesa = mpesaName.toLowerCase().trim();
    
    // Get first names
    const enteredFirst = cleanEntered.split(' ')[0];
    const mpesaFirst = cleanMpesa.split(' ')[0];
    
    console.log('Auto-verifying names:', cleanEntered, 'vs', cleanMpesa);
    
    // STRICT name matching - only allow if names clearly match
    let nameMatches = false;
    
    if (cleanEntered === cleanMpesa) {
        nameMatches = true;
        console.log('Full name match');
    } else if (enteredFirst === mpesaFirst && enteredFirst.length >= 3) {
        // Only allow first name match if it's at least 3 characters (avoid false positives)
        nameMatches = true;
        console.log('First name match');
    }
    
    if (nameMatches) {
        // Names match - call server verification to update database
        $.ajax({
            url: mpesa_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'verify_name',
                nonce: mpesa_ajax.nonce,
                payment_id: currentPaymentId,
                real_name: mpesaName // Use M-Pesa name for verification
            },
            success: function(response) {
                if (response.success && response.data.verified) {
                    showStatus('success', 'Payment verified! Your download will start now.');
                    setTimeout(function() {
                        startDownload();
                    }, 2000);
                } else {
                    // Auto-verification failed on server, show manual form
                    showManualVerification();
                }
            },
            error: function() {
                // Auto-verification failed, show manual form
                showManualVerification();
            }
        });
    } else {
        // Names don't match - REQUIRE manual verification
        console.log('Names do not match - requiring manual verification');
        showManualVerification();
    }
}

function showManualVerification() {
    showStatus('warning', 'Name verification required. Please enter your M-Pesa name exactly as it appears on your account.');
    $('#mpesa-payment-modal').hide();
    $('#mpesa-name-modal').show();
}

// SECURE download function - only called after verification
function startDownload() {
    // Get download URL from server (this should generate a secure, time-limited URL)
    $.ajax({
        url: mpesa_ajax.ajax_url,
        type: 'POST',
        data: {
            action: 'get_download_url',
            nonce: mpesa_ajax.nonce,
            payment_id: currentPaymentId
        },
        success: function(response) {
            if (response.success && response.data.download_url) {
                // Use server-provided download URL
                window.location.href = response.data.download_url;
            } else {
                showStatus('error', 'Failed to generate download link. Please contact support.');
            }
        },
        error: function() {
            showStatus('error', 'Failed to get download link. Please contact support.');
        },
        complete: function() {
            $('.mpesa-modal').hide();
            resetForms();
        }
    });
}
    
    function showStatus(type, message) {
        const statusDiv = $('#mpesa-status');
        statusDiv.removeClass('status-success status-error status-warning status-info')
                 .addClass('status-' + type)
                 .html('<p>' + message + '</p>')
                 .show();
    }
    
    function resetPaymentForm() {
        $('#mpesa-pay-btn').prop('disabled', false).text('Pay Now');
    }
    
    function resetForms() {
        $('#mpesa-payment-form')[0].reset();
        $('#mpesa-name-form')[0].reset();
        $('#mpesa-status').hide();
        resetPaymentForm();
        currentPaymentId = null;
    }
});