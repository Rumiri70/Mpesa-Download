jQuery(document).ready(function($) {
    let currentPaymentId = null;
    let statusCheckInterval = null;
    let statusCheckTimeout = null;
    let isProcessComplete = false; // Flag to track if process should continue
    
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
        stopAllProcesses();
        $('.mpesa-modal').hide();
        resetForms();
    });
    
    // Close modal when clicking outside
    $(window).on('click', function(event) {
        if ($(event.target).hasClass('mpesa-modal')) {
            stopAllProcesses();
            $('.mpesa-modal').hide();
            resetForms();
        }
    });
    
    // Stop all running processes
    function stopAllProcesses() {
        isProcessComplete = true;
        if (statusCheckInterval) {
            clearInterval(statusCheckInterval);
            statusCheckInterval = null;
        }
        if (statusCheckTimeout) {
            clearTimeout(statusCheckTimeout);
            statusCheckTimeout = null;
        }
    }
    
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
        
        // Reset process state
        isProcessComplete = false;
        
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
                    const errorMsg = getErrorMessage(response.data);
                    showStatus('error', errorMsg);
                    stopAllProcesses();
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
                stopAllProcesses();
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
        
        // Show loading state
        showStatus('info', 'Verifying name...');
        
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
                            startDownload();
                        }, 2000);
                    }
                } else {
                    const errorMsg = getErrorMessage(response.data);
                    showStatus('error', errorMsg);
                    stopAllProcesses();
                    $('#mpesa-name-modal').hide();
                    resetForms();
                }
            },
            error: function() {
                showStatus('error', 'An error occurred during name verification. Please contact us at +254 727 054 097 for help.');
                stopAllProcesses();
                $('#mpesa-name-modal').hide();
                resetForms();
            }
        });
    });
    
    function startStatusCheck() {
        // Set the main timeout first (5 minutes total)
        statusCheckTimeout = setTimeout(function() {
            if (!isProcessComplete) {
                stopAllProcesses();
                showStatus('error', 'Payment verification timeout. If you made the payment, please contact +254 727 054 097 for support.');
                resetPaymentForm();
            }
        }, 300000); // 5 minutes
        
        // Start the interval checking
        statusCheckInterval = setInterval(function() {
            if (!isProcessComplete) {
                checkPaymentStatus();
            }
        }, 10000); // Check every 10 seconds
    }
    
    // FIXED: Proper handling of all STK push outcomes with proper UI updates
    function checkPaymentStatus() {
        // Don't proceed if process is already complete
        if (isProcessComplete) {
            return;
        }
        
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
                            // Don't stop processes yet - we need to complete verification first
                            
                            // CRITICAL: Only proceed if we have M-Pesa name for verification
                            if (mpesaName && mpesaName.trim() !== '') {
                                // We have M-Pesa name - do automatic verification
                                showStatus('info', 'Payment received! Verifying your name...');
                                console.log('Calling autoVerifyName with:', enteredName, mpesaName);
                                
                                // Small delay to ensure UI updates, then verify
                                setTimeout(function() {
                                    autoVerifyName(enteredName, mpesaName);
                                }, 500);
                            } else {
                                // NO M-PESA NAME YET - Show manual verification immediately
                                stopAllProcesses(); // Only stop here if no name
                                showStatus('warning', 'Payment received but name verification required. Please enter your M-Pesa name.');
                                $('#mpesa-payment-modal').hide();
                                $('#mpesa-name-modal').show();
                            }
                            break;
                            
                        case 'success':
                            // Payment already verified - safe to download
                            stopAllProcesses();
                            showStatus('success', 'Payment verified! Your download will start now.');
                            setTimeout(function() {
                                startDownload();
                            }, 2000);
                            break;
                            
                        case 'stk_canceled':
                            // STK was canceled by user - STOP PROCESS
                            stopAllProcesses();
                            showStatus('error', 'STK Push was canceled. Please try again if you want to make the payment.');
                            resetPaymentForm();
                            break;
                            
                        case 'invalid_name':
                            // Name verification failed - STOP PROCESS
                            stopAllProcesses();
                            showStatus('error', 'Name verification failed. Please contact +254 727 054 097 for help.');
                            resetPaymentForm();
                            break;
                            
                        case 'failed':
                            // Payment failed - STOP PROCESS
                            stopAllProcesses();
                            showStatus('error', 'Payment failed. Please try again.');
                            resetPaymentForm();
                            break;
                            
                        case 'timeout':
                            // Payment timed out - STOP PROCESS
                            stopAllProcesses();
                            showStatus('error', 'Payment timed out. Please try again.');
                            resetPaymentForm();
                            break;
                            
                        case 'insufficient_funds':
                            // Insufficient funds - STOP PROCESS
                            stopAllProcesses();
                            showStatus('error', 'Insufficient funds. Please ensure you have enough balance and try again.');
                            resetPaymentForm();
                            break;
                            
                        case 'invalid_phone':
                            // Invalid phone number - STOP PROCESS
                            stopAllProcesses();
                            showStatus('error', 'Invalid phone number. Please check and try again.');
                            resetPaymentForm();
                            break;
                            
                        case 'pending':
                            // Still waiting - continue checking but update message
                            if (!isProcessComplete) {
                                showStatus('info', 'Waiting for payment confirmation... Please complete the payment on your phone.');
                            }
                            break;
                            
                        default:
                            // Unknown status - continue checking but with warning
                            if (!isProcessComplete) {
                                showStatus('warning', 'Checking payment status... Please wait.');
                            }
                    }
                } else {
                    const errorMsg = getErrorMessage(response.data);
                    showStatus('error', 'Failed to check payment status: ' + errorMsg);
                    // Don't stop process here - might be temporary server issue
                }
            },
            error: function() {
                // Network error - don't stop process, just show warning
                if (!isProcessComplete) {
                    showStatus('warning', 'Connection error while checking status. Retrying...');
                }
            }
        });
    }

    // Enhanced automatic name verification
    function autoVerifyName(enteredName, mpesaName) {
        // Don't proceed if process is complete
        if (isProcessComplete) {
            return;
        }
        
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
            showStatus('info', 'Names match! Completing verification...');
            
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
                    console.log('Auto-verify response:', response);
                    
                    if (response.success && response.data && response.data.verified) {
                        stopAllProcesses(); // Stop processes when verification succeeds
                        showStatus('success', 'Payment verified! Your download will start now.');
                        setTimeout(function() {
                            startDownload();
                        }, 2000);
                    } else {
                        // Auto-verification failed on server, show manual form
                        console.log('Auto-verification failed, showing manual form');
                        stopAllProcesses(); // Stop processes before showing manual form
                        showManualVerification();
                    }
                },
                error: function(xhr, status, error) {
                    console.log('Auto-verify AJAX error:', xhr, status, error);
                    // Auto-verification failed, show manual form
                    stopAllProcesses(); // Stop processes on error
                    showManualVerification();
                },
                timeout: 15000 // 15 second timeout
            });
        } else {
            // Names don't match - REQUIRE manual verification
            console.log('Names do not match - requiring manual verification');
            showManualVerification();
        }
    }

    function showManualVerification() {
        // Don't show manual verification if process is complete
        if (isProcessComplete) {
            return;
        }
        
        showStatus('warning', 'Name verification required. Please enter your M-Pesa name exactly as it appears on your account.');
        $('#mpesa-payment-modal').hide();
        $('#mpesa-name-modal').show();
    }

    // SECURE download function - only called after verification
    function startDownload() {
        showStatus('info', 'Generating download link...');
        
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
                    showStatus('success', 'Download starting...');
                    // Use server-provided download URL
                    window.location.href = response.data.download_url;
                } else {
                    showStatus('error', 'Failed to generate download link. Please contact support.');
                }
            },
            error: function() {
                showStatus('error', 'Failed to get download link. Please contact +254 727 054 097 support.');
            },
            complete: function() {
                // Small delay before cleaning up to allow download to start
                setTimeout(function() {
                    stopAllProcesses();
                    $('.mpesa-modal').hide();
                    resetForms();
                }, 3000);
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
        isProcessComplete = false;
    }
});