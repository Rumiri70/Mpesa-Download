jQuery(document).ready(function($) {
    let currentPaymentId = null;
    let statusCheckInterval = null;
    
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
        
        // Validate phone number
        if (!phoneNumber.match(/^254[0-9]{9}$/)) {
            showStatus('error', 'Please enter a valid phone number (254XXXXXXXXX)');
            return;
        }
        
        if (!firstName) {
            showStatus('error', 'Please enter your first name');
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
                    showStatus('error', response.data);
                    resetPaymentForm();
                }
            },
            error: function() {
                showStatus('error', 'An error occurred. Please try again.');
                resetPaymentForm();
            }
        });
    });
    
    // Handle name verification form
    $('#mpesa-name-form').on('submit', function(e) {
        e.preventDefault();
        
        const realName = $('#real_name').val().trim();
        
        if (!realName) {
            alert('Please enter your real name');
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
                        alert('Payment verified! Your download will start now.');
                        window.location.href = response.data.download_url;
                        $('.mpesa-modal').hide();
                        resetForms();
                    }
                } else {
                    alert(response.data);
                    $('#mpesa-name-modal').hide();
                    resetForms();
                }
            },
            error: function() {
                alert('An error occurred during name verification. Please contact us at 254738207774 for help.');
                $('#mpesa-name-modal').hide();
                resetForms();
            }
        });
    });
    
    function startStatusCheck() {
        statusCheckInterval = setInterval(function() {
            checkPaymentStatus();
        }, 5000); // Check every 5 seconds
        
        // Stop checking after 5 minutes
        setTimeout(function() {
            clearInterval(statusCheckInterval);
            showStatus('warning', 'Payment verification timeout. Please contact support if payment was made.');
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
                    const mpesaName = response.data.mpesa_name;
                    const enteredName = response.data.entered_name;
                    
                    switch (status) {
                        case 'done':
                            clearInterval(statusCheckInterval);
                            
                            // Compare names
                            if (mpesaName && enteredName) {
                                if (mpesaName.toLowerCase().trim() === enteredName.toLowerCase().trim()) {
                                    // Names match - proceed to download
                                    showStatus('success', 'Payment successful! Your download will start now.');
                                    setTimeout(function() {
                                        window.location.href = '/wp-content/uploads/your-file.zip'; // Replace with actual file
                                        $('.mpesa-modal').hide();
                                        resetForms();
                                    }, 2000);
                                } else {
                                    // Names don't match - ask for real name
                                    showStatus('warning', 'Name verification required.');
                                    $('#mpesa-payment-modal').hide();
                                    $('#mpesa-name-modal').show();
                                }
                            } else {
                                // No name from M-Pesa - proceed to download
                                showStatus('success', 'Payment successful! Your download will start now.');
                                setTimeout(function() {
                                    window.location.href = '/wp-content/uploads/your-file.zip'; // Replace with actual file
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
                            clearInterval(statusCheckInterval);
                            showStatus('error', 'Payment failed due to invalid details. Please try again.');
                            resetPaymentForm();
                            break;
                            
                        case 'pending':
                            showStatus('info', 'Waiting for payment confirmation...');
                            break;
                            
                        default:
                            showStatus('info', 'Checking payment status...');
                    }
                } else {
                    showStatus('error', 'Failed to check payment status: ' + response.data);
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