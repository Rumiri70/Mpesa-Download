jQuery(document).ready(function($) {
    let currentPaymentId = null;
    let statusCheckInterval = null;
    let statusCheckTimeout = null;
    let statusCheckCount = 0;
    let maxStatusChecks = 45; // 7.5 minutes (45 × 10 seconds)
    let isProcessComplete = false;
    
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
        statusCheckCount = 0;
        
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
                    
                    // Start checking payment status with improved timing
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
                        showStatus('success', 'Payment verified! Your download will start now.');
                        setTimeout(function() {
                            startDownload();
                        }, 2000);
                    }
                } else {
                    const errorMsg = getErrorMessage(response.data);
                    showStatus('error', errorMsg + ' If this continues, please contact +254 727 054 097');
                    // Don't close modal immediately - give user chance to retry
                }
            },
            error: function() {
                showStatus('error', 'An error occurred during name verification. Please contact +254 727 054 097 for help.');
            }
        });
    });
    
    function startStatusCheck() {
        statusCheckCount = 0;
        
        // Set the main timeout (7.5 minutes total)
        statusCheckTimeout = setTimeout(function() {
            if (!isProcessComplete) {
                stopAllProcesses();
                showStatus('error', 'Payment verification timeout. If you completed the payment, please contact +254 727 054 097 with your M-Pesa message.');
                resetPaymentForm();
            }
        }, 450000); // 7.5 minutes
        
        // Start the interval checking with adaptive timing
        statusCheckInterval = setInterval(function() {
            if (!isProcessComplete && statusCheckCount < maxStatusChecks) {
                checkPaymentStatus();
                statusCheckCount++;
            } else if (statusCheckCount >= maxStatusChecks) {
                stopAllProcesses();
                showStatus('error', 'Payment verification timeout. Please contact support with your M-Pesa confirmation message.');
                resetPaymentForm();
            }
        }, 10000); // Check every 10 seconds
    }
    
    function checkPaymentStatus() {
        if (isProcessComplete) {
            return;
        }
        
        // Update user with current status
        if (statusCheckCount < 6) { // First minute
            showStatus('info', 'Waiting for payment confirmation... Please complete the payment on your phone.');
        } else if (statusCheckCount < 18) { // Next 2 minutes  
            showStatus('info', 'Checking payment status... This usually takes 1-3 minutes.');
        } else if (statusCheckCount < 30) { // Next 2 minutes
            showStatus('warning', 'Still checking... Some payments take longer to process.');
        } else {
            showStatus('warning', 'Payment verification taking longer than usual. Please wait...');
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
                    
                    console.log(`Status Check #${statusCheckCount}: ${status}, M-Pesa Name: ${mpesaName}`);
                    
                    switch (status) {
                        case 'success':
                            // Payment verified - proceed to download
                            stopAllProcesses();
                            showStatus('success', 'Payment verified! Your download will start now.');
                            setTimeout(function() {
                                startDownload();
                            }, 2000);
                            break;
                            
                        case 'done':
                            if (mpesaName && mpesaName.trim() !== '') {
                                // We have M-Pesa name - try auto-verification
                                showStatus('info', 'Payment received! Verifying your name...');
                                setTimeout(function() {
                                    if (!isProcessComplete) {
                                        autoVerifyName(enteredName, mpesaName);
                                    }
                                }, 1000);
                            } else {
                                // No M-Pesa name yet - continue checking for a bit more
                                if (statusCheckCount > 30) { // After 5 minutes
                                    stopAllProcesses();
                                    showStatus('warning', 'Payment received but name verification needed. Please enter your M-Pesa name.');
                                    $('#mpesa-payment-modal').hide();
                                    $('#mpesa-name-modal').show();
                                } else {
                                    showStatus('info', 'Payment confirmed! Waiting for account details...');
                                }
                            }
                            break;
                            
                        case 'stk_canceled':
                            stopAllProcesses();
                            showStatus('error', 'Payment was canceled. Please try again if you want to proceed.');
                            resetPaymentForm();
                            break;
                            
                        case 'invalid_name':
                            stopAllProcesses();
                            showStatus('error', 'Name verification failed. Please contact +254 727 054 097 for assistance.');
                            resetPaymentForm();
                            break;
                            
                        case 'failed':
                        case 'timeout':
                        case 'insufficient_funds':
                        case 'invalid_phone':
                            stopAllProcesses();
                            showStatus('error', `Payment ${status.replace('_', ' ')}. Please try again.`);
                            resetPaymentForm();
                            break;
                            
                        case 'pending':
                            // Continue checking - status message already updated above
                            break;
                            
                        default:
                            // Unknown status - continue but with warning after some time
                            if (statusCheckCount > 18) {
                                showStatus('warning', 'Payment status unclear. Continuing to check...');
                            }
                    }
                } else {
                    const errorMsg = getErrorMessage(response.data);
                    console.warn('Payment status check error:', errorMsg);
                    // Don't stop process for temporary API errors
                }
            },
            error: function(xhr, status, error) {
                console.warn('Network error during status check:', error);
                // Don't stop process for network errors - might be temporary
                if (statusCheckCount > 20) {
                    showStatus('warning', 'Connection issues detected. Retrying...');
                }
            }
        });
    }

    function autoVerifyName(enteredName, mpesaName) {
        if (isProcessComplete) {
            return;
        }
        
        const cleanEntered = enteredName.toLowerCase().trim();
        const cleanMpesa = mpesaName.toLowerCase().trim();
        const enteredFirst = cleanEntered.split(' ')[0];
        const mpesaFirst = cleanMpesa.split(' ')[0];
        
        console.log('Auto-verifying names:', cleanEntered, 'vs', cleanMpesa);
        
        let nameMatches = false;
        
        // More liberal matching for better UX
        if (cleanEntered === cleanMpesa) {
            nameMatches = true;
        } else if (enteredFirst === mpesaFirst && enteredFirst.length >= 2) {
            nameMatches = true;
        } else if (cleanEntered.length >= 3 && cleanMpesa.length >= 3) {
            // Partial matching
            if (cleanEntered.includes(cleanMpesa) || cleanMpesa.includes(cleanEntered) ||
                enteredFirst.includes(mpesaFirst) || mpesaFirst.includes(enteredFirst)) {
                nameMatches = true;
            }
        }
        
        if (nameMatches) {
            showStatus('info', 'Names match! Completing verification...');
            
            $.ajax({
                url: mpesa_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'verify_name',
                    nonce: mpesa_ajax.nonce,
                    payment_id: currentPaymentId,
                    real_name: mpesaName
                },
                success: function(response) {
                    if (response.success && response.data && response.data.verified) {
                        stopAllProcesses();
                        showStatus('success', 'Payment verified! Your download will start now.');
                        setTimeout(function() {
                            startDownload();
                        }, 2000);
                    } else {
                        console.log('Auto-verification failed on server, showing manual form');
                        showManualVerification(mpesaName);
                    }
                },
                error: function(xhr, status, error) {
                    console.log('Auto-verify AJAX error:', error);
                    showManualVerification(mpesaName);
                },
                timeout: 15000
            });
        } else {
            console.log('Names do not match - requiring manual verification');
            showManualVerification(mpesaName);
        }
    }

    function showManualVerification(suggestedName = '') {
        if (isProcessComplete) {
            return;
        }
        
        stopAllProcesses();
        showStatus('warning', 'Name verification required. Please enter your M-Pesa name exactly as it appears.');
        
        // Pre-fill with M-Pesa name if available
        if (suggestedName) {
            $('#real_name').val(suggestedName);
        }
        
        $('#mpesa-payment-modal').hide();
        $('#mpesa-name-modal').show();
    }

    function startDownload() {
        showStatus('info', 'Generating secure download link...');
        
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
                    window.open(response.data.download_url, '_blank');
                } else {
                    showStatus('error', 'Failed to generate download link. Please contact +254 727 054 097 with your payment details.');
                }
            },
            error: function() {
                showStatus('error', 'Failed to get download link. Please contact +254 727 054 097 for support.');
            },
            complete: function() {
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
        statusCheckCount = 0;
    }
});