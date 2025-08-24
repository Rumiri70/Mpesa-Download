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
                        case 'success':
                            clearInterval(statusCheckInterval);
                            
                            // Compare names if both exist
                            if (mpesaName && enteredName) {
                                if (mpesaName.toLowerCase().trim() === enteredName.toLowerCase().trim()) {
                                    // Names match - proceed to download
                                    showStatus('success', 'Payment successful! Your download will start now.');
                                    setTimeout(function() {
                                        // Generate download URL or use a fixed one
                                        const downloadUrl = '/wp-content/uploads/your-file.zip'; // Replace with actual file
                                        window.location.href = downloadUrl;
                                        $('.mpesa-modal').hide();
                                        resetForms();
                                    }, 2000);
                                } else {
                                    // Names don't match - ask for real name
                                    showStatus('warning', 'Name verification required. The name on your M-Pesa account doesn\'t match what you entered.');
                                    $('#mpesa-payment-modal').hide();
                                    $('#mpesa-name-modal').show();
                                }
                            } else {
                                // No name comparison needed - proceed to download
                                showStatus('success', 'Payment successful! Your download will start now.');
                                setTimeout(function() {
                                    const downloadUrl = '/wp-content/uploads/your-file.zip'; // Replace with actual file
                                    window.location.href = downloadUrl;
                                    $('.mpesa-modal').hide();
                                    resetForms();
                                }, 2000);
                            }
                            break;
                            
                        case 'stk_canceled':
                            clearInterval(statusCheckInterval);
                            showStatus('error', 'STK Push was canceled. Please try again.');
                            resetPaymentForm();
                            break;
                            
                        case 'invalid_name':
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