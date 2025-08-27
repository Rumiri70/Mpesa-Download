<?php
/**
 * Plugin Name: Do-pay
 * Description: WordPress plugin for M-Pesa payment integration with download functionality (Production Till Mode)
 * Version: 1.0.0
 * Author: Rumiri
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('MPESA_PLUGIN_URL', plugin_dir_url(__FILE__));
define('MPESA_PLUGIN_PATH', plugin_dir_path(__FILE__));

class MpesaIntegrationPlugin {
    
    // Constructor 
    public function __construct() {
        add_action('init', array($this, 'init'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));
        add_action('wp_ajax_process_mpesa_payment', array($this, 'process_mpesa_payment'));
        add_action('wp_ajax_nopriv_process_mpesa_payment', array($this, 'process_mpesa_payment'));
        add_action('wp_ajax_verify_payment_status', array($this, 'verify_payment_status'));
        add_action('wp_ajax_nopriv_verify_payment_status', array($this, 'verify_payment_status'));
        add_action('wp_ajax_verify_name', array($this, 'verify_name'));
        add_action('wp_ajax_nopriv_verify_name', array($this, 'verify_name'));
        add_action('wp_ajax_get_download_url', array($this, 'get_download_url'));
        add_action('wp_ajax_nopriv_get_download_url', array($this, 'get_download_url'));
        add_action('wp_ajax_test_mpesa_connection', array($this, 'test_mpesa_connection'));
        add_action('wp_ajax_check_mpesa_name', array($this, 'check_mpesa_name'));
        add_action('wp_ajax_nopriv_check_mpesa_name', array($this, 'check_mpesa_name'));
        
        register_activation_hook(__FILE__, array($this, 'create_tables'));
    }

    // Initialize plugin
    public function init() {
        // Add shortcode for download button
        add_shortcode('mpesa_download', array($this, 'download_shortcode'));
    }
    
    // Create database tables
    public function create_tables() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'mpesa_payments';
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            phone_number varchar(15) NOT NULL,
            first_name varchar(100) NOT NULL,
            mpesa_name varchar(100) DEFAULT '',
            amount decimal(10,2) NOT NULL DEFAULT 1.00,
            checkout_request_id varchar(100) DEFAULT '',
            merchant_request_id varchar(100) DEFAULT '',
            status varchar(20) DEFAULT 'pending',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    // Add admin menu and pages
    public function add_admin_menu() {
        add_menu_page(
            'M-Pesa Integration',
            'M-Pesa',
            'manage_options',
            'mpesa-integration',
            array($this, 'admin_page'),
            'dashicons-money-alt',
            30
        );
        
        add_submenu_page(
            'mpesa-integration',
            'M-Pesa Settings',
            'Settings',
            'manage_options',
            'mpesa-settings',
            array($this, 'settings_page')
        );
        
        add_submenu_page(
            'mpesa-integration',
            'Payment Dashboard',
            'Dashboard',
            'manage_options',
            'mpesa-dashboard',
            array($this, 'dashboard_page')
        );
        
        add_submenu_page(
            'mpesa-integration',
            'Test Connection',
            'Test Connection',
            'manage_options',
            'mpesa-test',
            array($this, 'test_page')
        );
    }
    
    // Enqueue frontend and admin scripts/styles
    public function enqueue_scripts() {
        wp_enqueue_script('jquery');
        wp_enqueue_script('mpesa-frontend', MPESA_PLUGIN_URL . 'assets/mpesa-frontend.js', array('jquery'), '1.0.0', true);
        wp_enqueue_style('mpesa-frontend', MPESA_PLUGIN_URL . 'assets/mpesa-frontend.css', array(), '1.0.0');
        
        wp_localize_script('mpesa-frontend', 'mpesa_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('mpesa_nonce')
        ));
    }
    
    public function admin_enqueue_scripts() {
        wp_enqueue_script('mpesa-admin', MPESA_PLUGIN_URL . 'assets/mpesa-admin.js', array('jquery'), '1.0.0', true);
        wp_enqueue_style('mpesa-admin', MPESA_PLUGIN_URL . 'assets/mpesa-admin.css', array(), '1.0.0');
    }
    
    // Admin main page
    public function admin_page() {
        ?>
        <div class="wrap">
            <h1>M-Pesa STK Push Till Integration</h1>
            <div class="card">
                <h2>STK Push Till Integration (Production Mode)</h2>
                <p>This plugin integrates M-Pesa STK Push for Till payments in production mode, allowing customers to pay directly from their phones.</p>
                <p><strong>How STK Push Till works:</strong></p>
                <ol>
                    <li>Customer enters phone number and clicks pay</li>
                    <li>STK Push popup appears on customer's phone</li>
                    <li>Customer enters M-Pesa PIN to authorize payment to your Till number</li>
                    <li>Payment is processed automatically</li>
                    <li>Download access is granted upon successful payment</li>
                </ol>
                <p><strong>Quick Setup:</strong></p>
                <ul>
                    <li><a href="<?php echo admin_url('admin.php?page=mpesa-settings'); ?>">Configure STK Push Till Settings</a></li>
                    <li><a href="<?php echo admin_url('admin.php?page=mpesa-dashboard'); ?>">View Payment Dashboard</a></li>
                    <li><a href="<?php echo admin_url('admin.php?page=mpesa-test'); ?>">Test Connection</a></li>
                </ul>
                
                <div class="notice notice-warning">
                    <p><strong>Note:</strong> This plugin is configured for production mode. Make sure you have valid production credentials from Safaricom.</p>
                </div>
            </div>
        </div>
        <?php
    }
    
    // Settings page
    public function settings_page() {
        if (isset($_POST['submit'])) {
            update_option('mpesa_consumer_key', sanitize_text_field($_POST['consumer_key']));
            update_option('mpesa_consumer_secret', sanitize_text_field($_POST['consumer_secret']));
            update_option('mpesa_business_shortcode', sanitize_text_field($_POST['business_shortcode']));
            update_option('mpesa_passkey', sanitize_text_field($_POST['passkey']));
            update_option('mpesa_callback_url', sanitize_url($_POST['callback_url']));
            update_option('mpesa_environment', 'production'); // Force production mode
            
            echo '<div class="notice notice-success"><p>Settings saved!</p></div>';
        }
        
        $consumer_key = get_option('mpesa_consumer_key', '');
        $consumer_secret = get_option('mpesa_consumer_secret', '');
        $business_shortcode = get_option('mpesa_business_shortcode', '');
        $passkey = get_option('mpesa_passkey', '');
        $callback_url = get_option('mpesa_callback_url', '');
        ?>
        
        <div class="wrap">
            <h1>M-Pesa Settings (Production Mode)</h1>
            <div class="notice notice-info">
                <p><strong>Production Mode:</strong> This plugin is configured for production use with Till numbers.</p>
            </div>
            
            <form method="post" action="">
                <table class="form-table">
                    <tr>
                        <th scope="row">Environment</th>
                        <td>
                            <strong>Production</strong> (Fixed)
                            <input type="hidden" name="environment" value="production" />
                            <p class="description">This plugin is set to production mode only.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Consumer Key</th>
                        <td>
                            <input type="text" name="consumer_key" value="<?php echo esc_attr($consumer_key); ?>" class="regular-text" required />
                            <p class="description">Your production Consumer Key from Safaricom Developer Portal</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Consumer Secret</th>
                        <td>
                            <input type="password" name="consumer_secret" value="<?php echo esc_attr($consumer_secret); ?>" class="regular-text" required />
                            <p class="description">Your production Consumer Secret from Safaricom Developer Portal</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"> Business Shortcode</th>
                        <td>
                            <input type="text" name="business_shortcode" value="<?php echo esc_attr($business_shortcode); ?>" class="regular-text" required />
                            <p class="description">Your business shortcode (e.g., 174379)</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Passkey</th>
                        <td>
                            <input type="password" name="passkey" value="<?php echo esc_attr($passkey); ?>" class="regular-text" required />
                            <p class="description">Your production Passkey from Safaricom</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Callback URL</th>
                        <td>
                            <input type="url" name="callback_url" value="<?php echo esc_attr($callback_url); ?>" class="regular-text" required />
                            <p class="description">URL where M-Pesa will send payment notifications (must be HTTPS)</p>
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
    
    // Dashboard page
    public function dashboard_page() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'mpesa_payments';

        // Handle status update
        if (isset($_POST['edit_payment_status']) && current_user_can('manage_options')) {
            $edit_id = intval($_POST['edit_id']);
            $new_status = sanitize_text_field($_POST['new_status']);
            $wpdb->update(
                $table_name,
                array('status' => $new_status),
                array('id' => $edit_id),
                array('%s'),
                array('%d')
            );
            echo '<div class="notice notice-success"><p>Status updated!</p></div>';
        }

        // Pagination
        $per_page = 20;
        $page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $offset = ($page - 1) * $per_page;
        
        $total_payments = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
        $total_pages = ceil($total_payments / $per_page);
        
        $payments = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name ORDER BY created_at DESC LIMIT %d OFFSET %d",
            $per_page,
            $offset
        ));
        
        // Statistics
        $stats = $wpdb->get_row("
            SELECT 
                COUNT(*) as total_payments,
                SUM(CASE WHEN status = 'success' THEN amount ELSE 0 END) as total_revenue,
                COUNT(CASE WHEN status = 'success' THEN 1 END) as successful_payments,
                COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_payments
            FROM $table_name
        ");
        ?>
      

        <div class="wrap">
            <h1>Payment Dashboard</h1>

            
            <!-- Statistics -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 20px;">
                <div style="background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 4px;">
                    <h3>Total Payments</h3>
                    <p style="font-size: 24px; font-weight: bold; color: #0073aa;"><?php echo number_format($stats->total_payments); ?></p>
                </div>
                <div style="background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 4px;">
                    <h3>Total Revenue</h3>
                    <p style="font-size: 24px; font-weight: bold; color: #00a32a;">KSH <?php echo number_format($stats->total_revenue, 2); ?></p>
                </div>
                <div style="background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 4px;">
                    <h3>Successful</h3>
                    <p style="font-size: 24px; font-weight: bold; color: #00a32a;"><?php echo number_format($stats->successful_payments); ?></p>
                </div>
                <div style="background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 4px;">
                    <h3>Pending</h3>
                    <p style="font-size: 24px; font-weight: bold; color: #dba617;"><?php echo number_format($stats->pending_payments); ?></p>
                </div>
            </div>

            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Phone Number</th>
                        <th>First Name</th>
                        <th>M-Pesa Name</th>
                        <th>Amount</th>
                        <th>Mpesa code</th>
                        <th>Status</th>
                        <th>Created At</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($payments as $payment): ?>
                    <tr>
                        <td><?php echo $payment->id; ?></td>
                        <td><?php echo esc_html($payment->phone_number); ?></td>
                        <td><?php echo esc_html($payment->first_name); ?></td>
                        <td><?php echo esc_html($payment->mpesa_name); ?></td>
                        <td>KSH <?php echo number_format($payment->amount, 2); ?></td>
                        <td><?php echo esc_html($payment->mpesa_receipt_number); ?> </td>
                        <td>
                            <span class="status-<?php echo $payment->status; ?>">
                                <?php echo ucfirst($payment->status); ?>
                            </span>
                        </td>
                         <td>
                            <?php
                                $utc = new DateTimeZone('UTC');
                                $eat = new DateTimeZone('Africa/Nairobi');
                                $date = new DateTime($payment->created_at, $utc);
                                $date->setTimezone($eat);
                                echo $date->format('Y-m-d H:i:s');
                            ?>
                        </td>
                        <td>
                            <form method="post" style="display:inline;">
                                <input type="hidden" name="edit_id" value="<?php echo $payment->id; ?>">
                                <select name="new_status">
                                    <option value="pending" <?php selected($payment->status, 'pending'); ?>>Pending</option>
                                    <option value="done" <?php selected($payment->status, 'done'); ?>>Done</option>
                                    <option value="stk_canceled" <?php selected($payment->status, 'stk_canceled'); ?>>STK Canceled</option>
                                    <option value="invalid_name" <?php selected($payment->status, 'invalid_name'); ?>>Invalid Name</option>
                                    <option value="success" <?php selected($payment->status, 'success'); ?>>Success</option>
                                </select>
                                <button type="submit" name="edit_payment_status" class="button">Update</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
    
    // Test Connection page
    public function test_page() {
        ?>
        <div class="wrap">
            <h1>M-Pesa Connection Test</h1>
            <div class="card">
                <h2>Test Your M-Pesa Configuration</h2>
                <p>Use this tool to test your M-Pesa credentials and connection before going live.</p>
                
                <div id="test-results" style="margin: 20px 0;"></div>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">Test Phone Number</th>
                        <td>
                            <input type="tel" id="test-phone" placeholder="254712345678" class="regular-text" />
                            <p class="description">Enter a test phone number (your own) to receive STK push</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Test Amount</th>
                        <td>
                            <input type="number" id="test-amount" value="1" min="1" class="regular-text" />
                            <p class="description">Test amount (minimum 1 KSH)</p>
                        </td>
                    </tr>
                </table>
                
                <div style="margin: 20px 0;">
                    <button id="test-credentials" class="button button-secondary">1. Test Credentials</button>
                    <button id="test-stk-push" class="button button-primary">2. Test STK Push</button>
                    <button id="clear-logs" class="button">Clear Results</button>
                </div>
                
                <div style="margin-top: 20px;">
                    <h3>Test Results:</h3>
                    <div id="test-log" style="background: #f1f1f1; padding: 15px; border: 1px solid #ccc; height: 400px; overflow-y: auto; font-family: monospace; white-space: pre-wrap;"></div>
                </div>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            function logMessage(message, type = 'info') {
                var timestamp = new Date().toLocaleTimeString();
                var logDiv = $('#test-log');
                var prefix = type === 'error' ? '❌ ERROR' : type === 'success' ? '✅ SUCCESS' : 'ℹ️ INFO';
                logDiv.append('[' + timestamp + '] ' + prefix + ': ' + message + '\n');
                logDiv.scrollTop(logDiv[0].scrollHeight);
            }
            
            $('#clear-logs').click(function() {
                $('#test-log').empty();
                $('#test-results').empty();
            });
            
            $('#test-credentials').click(function() {
                var btn = $(this);
                btn.prop('disabled', true).text('Testing...');
                
                logMessage('Starting credential test...');
                
                $.post(ajaxurl, {
                    action: 'test_mpesa_connection',
                    test_type: 'credentials',
                    nonce: '<?php echo wp_create_nonce('mpesa_test_nonce'); ?>'
                }, function(response) {
                    if (response.success) {
                        logMessage('Credentials test passed!', 'success');
                        logMessage('Access token obtained successfully');
                        $('#test-results').html('<div class="notice notice-success"><p>✅ Credentials are valid!</p></div>');
                    } else {
                        logMessage('Credentials test failed: ' + response.data, 'error');
                        $('#test-results').html('<div class="notice notice-error"><p>❌ Credentials test failed: ' + response.data + '</p></div>');
                    }
                }).fail(function(xhr, status, error) {
                    logMessage('Request failed: ' + error, 'error');
                    $('#test-results').html('<div class="notice notice-error"><p>❌ Request failed: ' + error + '</p></div>');
                }).always(function() {
                    btn.prop('disabled', false).text('1. Test Credentials');
                });
            });
            
            $('#test-stk-push').click(function() {
                var phone = $('#test-phone').val();
                var amount = $('#test-amount').val();
                
                if (!phone || !amount) {
                    alert('Please enter phone number and amount');
                    return;
                }
                
                var btn = $(this);
                btn.prop('disabled', true).text('Sending STK Push...');
                
                logMessage('Starting STK Push test...');
                logMessage('Phone: ' + phone + ', Amount: KSH ' + amount);
                
                $.post(ajaxurl, {
                    action: 'test_mpesa_connection',
                    test_type: 'stk_push',
                    phone: phone,
                    amount: amount,
                    nonce: '<?php echo wp_create_nonce('mpesa_test_nonce'); ?>'
                }, function(response) {
                    if (response.success) {
                        logMessage('STK Push sent successfully!', 'success');
                        logMessage('Check your phone for the payment prompt');
                        $('#test-results').html('<div class="notice notice-success"><p>✅ STK Push sent! Check your phone.</p></div>');
                    } else {
                        logMessage('STK Push failed: ' + response.data, 'error');
                        $('#test-results').html('<div class="notice notice-error"><p>❌ STK Push failed: ' + response.data + '</p></div>');
                    }
                }).fail(function(xhr, status, error) {
                    logMessage('Request failed: ' + error, 'error');
                    $('#test-results').html('<div class="notice notice-error"><p>❌ Request failed: ' + error + '</p></div>');
                }).always(function() {
                    btn.prop('disabled', false).text('2. Test STK Push');
                });
            });
        });
        </script>
        
        <style>
        .notice {
            padding: 10px 15px;
            margin: 10px 0;
            border-left: 4px solid;
        }
        .notice-success {
            background-color: #d4edda;
            border-color: #28a745;
            color: #155724;
        }
        .notice-error {
            background-color: #f8d7da;
            border-color: #dc3545;
            color: #721c24;
        }
        </style>
        </div>
        <?php
    }
    
    // Shortcode for download button and payment modal
    public function download_shortcode($atts) {
        $atts = shortcode_atts(array(
            'recipient' => 'TEACHER DAVID GLOBAL',
            'amount' => '1'
        ), $atts);
        
        ob_start();
        ?>
        <div id="mpesa-download-container">
            <button id="mpesa-download-btn" class="mpesa-download-button">Download</button>
            
            <!-- Payment Modal -->
            <div id="mpesa-payment-modal" class="mpesa-modal" style="display: none;">
                <div class="mpesa-modal-content">
                    <span class="mpesa-close">&times;</span>
                    <h3>Payment Required</h3>
                    <p>Please enter details to send KSH <?php echo esc_html($atts['amount']); ?> to <?php echo esc_html($atts['recipient']); ?></p>
                    
                    <form id="mpesa-payment-form">
                        <div class="mpesa-form-group">
                            <label for="phone_number">Phone Number:</label>
                            <input type="tel" id="phone_number" name="phone_number" placeholder="254712345678" required>
                        </div>
                        <div class="mpesa-form-group">
                            <label for="first_name">First Mpesa Name:</label>
                            <input type="text" id="first_name" name="first_name" placeholder="Enter your first name" required>
                        </div>
                        <input type="hidden" name="amount" value="<?php echo esc_attr($atts['amount']); ?>">
                        <input type="hidden" name="recipient" value="<?php echo esc_attr($atts['recipient']); ?>">
                        
                        <p>For your safety: All data submitted is strictly confidential and protected.</p>
                        
                        <div class="mpesa-form-actions">
                            <button type="submit" id="mpesa-pay-btn">Pay Now</button>
                            <button type="button" class="mpesa-cancel">Cancel</button>
                        </div>
                    </form>
                    
                    <div id="mpesa-status" style="display: none;"></div>
                </div>
            </div>
            
            <!-- Name Verification Modal -->
            <div id="mpesa-name-modal" class="mpesa-modal" style="display: none;">
                <div class="mpesa-modal-content">
                    <h3>Name Verification</h3>
                    <p>Please enter your real name as it appears on M-Pesa:</p>
                    
                    <form id="mpesa-name-form">
                        <div class="mpesa-form-group">
                            <label for="real_name">Real Name:</label>
                            <input type="text" id="real_name" name="real_name" placeholder="Enter your real name" required>
                        </div>
                        
                        <div class="mpesa-form-actions">
                            <button type="submit">Verify Name</button>
                            <button type="button" class="mpesa-cancel">Cancel</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    // Process M-Pesa payment
    public function process_mpesa_payment() {
        check_ajax_referer('mpesa_nonce', 'nonce');
        
        $phone_number = sanitize_text_field($_POST['phone_number']);
        $first_name = sanitize_text_field($_POST['first_name']);
        $amount = floatval($_POST['amount']);
        
        // Validate phone number
        if (!preg_match('/^254[0-9]{9}$/', $phone_number)) {
            wp_send_json_error('Invalid phone number format. Use 254XXXXXXXXX');
            return;
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'mpesa_payments';
        
        // Insert payment record
        $payment_id = $wpdb->insert(
            $table_name,
            array(
                'phone_number' => $phone_number,
                'first_name' => $first_name,
                'amount' => $amount,
                'status' => 'pending'
            ),
            array('%s', '%s', '%f', '%s')
        );
        
        if (!$payment_id) {
            wp_send_json_error('Failed to create payment record');
            return;
        }
        
        $payment_id = $wpdb->insert_id;
        
        // Process STK Push
        $response = $this->initiate_stk_push($phone_number, $amount, $payment_id);
        
        if ($response['success']) {
            // Update payment record with M-Pesa response
            $wpdb->update(
                $table_name,
                array(
                    'checkout_request_id' => $response['checkout_request_id'],
                    'merchant_request_id' => $response['merchant_request_id']
                ),
                array('id' => $payment_id),
                array('%s', '%s'),
                array('%d')
            );
            
            wp_send_json_success(array(
                'payment_id' => $payment_id,
                'message' => 'STK Push sent successfully. Please check your phone.'
            ));
        } else {
            wp_send_json_error($response['message']);
        }
    }
    
public function verify_payment_status() {
    check_ajax_referer('mpesa_nonce', 'nonce');
    
    $payment_id = intval($_POST['payment_id']);
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'mpesa_payments';
    
    $payment = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $payment_id));
    
    if (!$payment) {
        wp_send_json_error('Payment not found');
        return;
    }
    
    // If payment is already completed, return current status
    if (in_array($payment->status, ['done', 'success', 'stk_canceled', 'invalid_name'])) {
        wp_send_json_success(array(
            'status' => $payment->status,
            'mpesa_name' => $payment->mpesa_name,
            'entered_name' => $payment->first_name
        ));
        return;
    }
    
    // Query M-Pesa API for payment status
    if (!empty($payment->checkout_request_id)) {
        $stk_result = $this->query_stk_status($payment->checkout_request_id);
        
        if ($stk_result['success']) {
            $new_status = $stk_result['status'];
            $mpesa_name = isset($stk_result['mpesa_name']) ? $stk_result['mpesa_name'] : '';
            
            // Update payment record with new status and M-Pesa name
            $update_data = array('status' => $new_status);
            if (!empty($mpesa_name)) {
                $update_data['mpesa_name'] = $mpesa_name;
            }
            
            $wpdb->update(
                $table_name,
                $update_data,
                array('id' => $payment_id),
                array('%s', '%s'),
                array('%d')
            );
            
            wp_send_json_success(array(
                'status' => $new_status,
                'mpesa_name' => $mpesa_name,
                'entered_name' => $payment->first_name
            ));
        } else {
            // If API query fails, keep status as pending
            wp_send_json_success(array(
                'status' => 'pending',
                'mpesa_name' => $payment->mpesa_name,
                'entered_name' => $payment->first_name
            ));
        }
    } else {
        // No checkout request ID, keep as pending
        wp_send_json_success(array(
            'status' => 'pending',
            'mpesa_name' => $payment->mpesa_name,
            'entered_name' => $payment->first_name
        ));
    }
}

//  verify_name method 
public function get_download_url() {
    check_ajax_referer('mpesa_nonce', 'nonce');
    
    $payment_id = intval($_POST['payment_id']);
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'mpesa_payments';
    
    $payment = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $payment_id));
    
    if (!$payment) {
        wp_send_json_error('Payment not found');
        return;
    }
    
    // CRITICAL: Only allow download if status is 'success' (verified)
    if ($payment->status !== 'success') {
        wp_send_json_error('Payment not verified. Download not allowed.');
        return;
    }
    
    // CRITICAL: Ensure M-Pesa name exists (additional safety check)
    if (empty($payment->mpesa_name)) {
        wp_send_json_error('Name verification incomplete. Download not allowed.');
        return;
    }
    
    // Generate secure, time-limited download URL
    $download_url = $this->generate_secure_download_url($payment_id);
    
    if ($download_url) {
        wp_send_json_success(array(
            'download_url' => $download_url,
            'message' => 'Download link generated successfully'
        ));
    } else {
        wp_send_json_error('Failed to generate download link');
    }
}

// Generate a secure, time-limited download URL
private function generate_secure_download_url($payment_id) {
    // Create a secure token
    $token = wp_generate_password(32, false);
    $expires = time() + (30 * 60); // 30 minutes from now
    
    // Store the token in WordPress options or database
    update_option("download_token_{$payment_id}", array(
        'token' => $token,
        'expires' => $expires,
        'used' => false
    ), false);
    
    // Return secure URL
    return home_url("/secure-download.php?payment={$payment_id}&token={$token}");
}

// Enhanced verify_name method with stricter checks
public function verify_name() {
    check_ajax_referer('mpesa_nonce', 'nonce');
    
    $payment_id = intval($_POST['payment_id']);
    $real_name = sanitize_text_field($_POST['real_name']);
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'mpesa_payments';
    
    $payment = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $payment_id));
    
    if (!$payment) {
        wp_send_json_error('Payment not found');
        return;
    }
    
    // CRITICAL: Payment must be 'done' to proceed with verification
    if ($payment->status !== 'done') {
        wp_send_json_error('Payment not completed. Verification not allowed.');
        return;
    }
    
    // CRITICAL: M-Pesa name must exist
    if (empty($payment->mpesa_name) || $payment->mpesa_name === '') {
        wp_send_json_error('M-Pesa name not yet received. Please wait a moment and try again.');
        return;
    }
    
    // Clean and normalize names for comparison
    $entered_name = strtolower(trim(preg_replace('/\s+/', ' ', $real_name)));
    $mpesa_name = strtolower(trim(preg_replace('/\s+/', ' ', $payment->mpesa_name)));
    
    // Extract first names for comparison
    $entered_first = strtolower(trim(explode(' ', $entered_name)[0]));
    $mpesa_first = strtolower(trim(explode(' ', $mpesa_name)[0]));
    
    // STRICT name verification
    $name_matches = false;
    $match_type = '';
    
    // 1. Exact full name match
    if ($entered_name === $mpesa_name) {
        $name_matches = true;
        $match_type = 'full_name';
    }
    // 2. First name match (minimum 3 characters to avoid false positives)
    elseif ($entered_first === $mpesa_first && strlen($entered_first) >= 3) {
        $name_matches = true;
        $match_type = 'first_name';
    }
    // 3. Partial match (more conservative)
    elseif (strlen($entered_name) >= 5 && strlen($mpesa_name) >= 5) {
        if (strpos($entered_name, $mpesa_name) !== false || strpos($mpesa_name, $entered_name) !== false) {
            $name_matches = true;
            $match_type = 'partial';
        }
    }
    
    if ($name_matches) {
        // Names match - ONLY NOW set status to 'success'
        $updated = $wpdb->update(
            $table_name,
            array(
                'status' => 'success',
                'updated_at' => current_time('mysql')
            ),
            array('id' => $payment_id),
            array('%s', '%s'),
            array('%d')
        );
        
        if ($updated) {
            error_log("Name verification successful for payment ID {$payment_id}. Match type: {$match_type}");
            
            wp_send_json_success(array(
                'verified' => true,
                'message' => 'Name verification successful! You can now download.'
            ));
        } else {
            wp_send_json_error('Failed to update payment status.');
        }
    } else {
        // Names don't match - set invalid_name status
        $wpdb->update(
            $table_name,
            array(
                'status' => 'invalid_name',
                'updated_at' => current_time('mysql')
            ),
            array('id' => $payment_id),
            array('%s', '%s'),
            array('%d')
        );
        
        error_log("Name verification failed for payment ID {$payment_id}. Entered: '{$real_name}', M-Pesa: '{$payment->mpesa_name}'");
        
        wp_send_json_error("Name verification failed. M-Pesa name: '{$payment->mpesa_name}', You entered: '{$real_name}'. Please contact support for help.");
    }
}

    
    public function test_mpesa_connection() {
        check_ajax_referer('mpesa_test_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized access');
            return;
        }
        
        $test_type = sanitize_text_field($_POST['test_type']);
        
        if ($test_type === 'credentials') {
            $this->test_credentials();
        } elseif ($test_type === 'stk_push') {
            $this->test_stk_push();
        } else {
            wp_send_json_error('Invalid test type');
        }
    }

    
//  method to check if M-Pesa name has been received
public function check_mpesa_name() {
    check_ajax_referer('mpesa_nonce', 'nonce');
    
    $payment_id = intval($_POST['payment_id']);
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'mpesa_payments';
    
    $payment = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $payment_id));
    
    if (!$payment) {
        wp_send_json_error('Payment not found');
        return;
    }
    
    wp_send_json_success(array(
        'status' => $payment->status,
        'has_mpesa_name' => !empty($payment->mpesa_name),
        'mpesa_name' => $payment->mpesa_name,
        'entered_name' => $payment->first_name
    ));
}
    
    private function test_credentials() {
        $consumer_key = get_option('mpesa_consumer_key');
        $consumer_secret = get_option('mpesa_consumer_secret');
        $business_shortcode = get_option('mpesa_business_shortcode');
        $passkey = get_option('mpesa_passkey');
        $callback_url = get_option('mpesa_callback_url');
        
        // Check if credentials are configured
        if (empty($consumer_key)) {
            wp_send_json_error('Consumer Key is not configured');
            return;
        }
        
        if (empty($consumer_secret)) {
            wp_send_json_error('Consumer Secret is not configured');
            return;
        }
        
        if (empty($business_shortcode)) {
            wp_send_json_error('Business Shortcode (Till Number) is not configured');
            return;
        }
        
        if (empty($passkey)) {
            wp_send_json_error('Passkey is not configured');
            return;
        }
        
        if (empty($callback_url)) {
            wp_send_json_error('Callback URL is not configured');
            return;
        }
        
        // Test authentication
        $base_url = 'https://api.safaricom.co.ke';
        $access_token = $this->get_access_token($consumer_key, $consumer_secret, $base_url);
        
        if (!$access_token) {
            wp_send_json_error('Failed to obtain access token. Check your Consumer Key and Secret.');
            return;
        }
        
        wp_send_json_success('All credentials are valid and authentication successful!');
    }
    
    private function test_stk_push() {
        $phone_number = sanitize_text_field($_POST['phone']);
        $amount = floatval($_POST['amount']);
        
        // Validate phone number
        if (!preg_match('/^254[0-9]{9}$/', $phone_number)) {
            wp_send_json_error('Invalid phone number format. Use 254XXXXXXXXX');
            return;
        }
        
        if ($amount < 1) {
            wp_send_json_error('Amount must be at least 1 KSH');
            return;
        }
        
        // Create a test payment record
        global $wpdb;
        $table_name = $wpdb->prefix . 'mpesa_payments';
        
        $payment_id = $wpdb->insert(
            $table_name,
            array(
                'phone_number' => $phone_number,
                'first_name' => 'TEST',
                'amount' => $amount,
                'status' => 'test'
            ),
            array('%s', '%s', '%f', '%s')
        );
        
        if (!$payment_id) {
            wp_send_json_error('Failed to create test payment record');
            return;
        }
        
        $payment_id = $wpdb->insert_id;
        
        // Test STK Push
        $response = $this->initiate_stk_push($phone_number, $amount, $payment_id);
        
        if ($response['success']) {
            // Update test record
            $wpdb->update(
                $table_name,
                array(
                    'checkout_request_id' => $response['checkout_request_id'],
                    'merchant_request_id' => $response['merchant_request_id']
                ),
                array('id' => $payment_id),
                array('%s', '%s'),
                array('%d')
            );
            
            wp_send_json_success('STK Push sent successfully! Check your phone.');
        } else {
            wp_send_json_error($response['message']);
        }
    }
    
    private function initiate_stk_push($phone_number, $amount, $payment_id) {
        $consumer_key = get_option('mpesa_consumer_key');
        $consumer_secret = get_option('mpesa_consumer_secret');
        $business_shortcode = get_option('mpesa_business_shortcode');
        $passkey = get_option('mpesa_passkey');
        $callback_url = get_option('mpesa_callback_url');
        
        // Log for debugging
        error_log('M-Pesa STK Push Attempt - Phone: ' . $phone_number . ', Amount: ' . $amount);
        
        // Validate credentials
        if (empty($consumer_key) || empty($consumer_secret) || empty($business_shortcode) || empty($passkey)) {
            error_log('M-Pesa Error: Missing credentials');
            return array('success' => false, 'message' => 'Missing M-Pesa credentials. Please check settings.');
        }
        
        // Production mode only
        $base_url = 'https://api.safaricom.co.ke';
        
        // Get access token
        $access_token = $this->get_access_token($consumer_key, $consumer_secret, $base_url);
        
        if (!$access_token) {
            error_log('M-Pesa Error: Failed to get access token');
            return array('success' => false, 'message' => 'Failed to authenticate with M-Pesa. Check your Consumer Key and Secret.');
        }
        
        // Generate password
        $timestamp = date('YmdHis');
        $password = base64_encode($business_shortcode . $passkey . $timestamp);
        
        // STK Push request - Try both transaction types
        $stk_push_url = $base_url . '/mpesa/stkpush/v1/processrequest';
        
        // First try CustomerBuyGoodsOnline (Till)
        $request_data = array(
            'BusinessShortCode' => $business_shortcode,
            'Password' => $password,
            'Timestamp' => $timestamp,
            'TransactionType' => 'CustomerBuyGoodsOnline',
            'Amount' => intval($amount), // Ensure integer
            'PartyA' => $phone_number,
            'PartyB' => '6901880', // Till number
            'PhoneNumber' => $phone_number,
            'CallBackURL' => $callback_url,
            'AccountReference' => 'BOOK' . $payment_id,
            'TransactionDesc' => 'Book Payment',
        );
        
        error_log('M-Pesa Request Data: ' . json_encode($request_data));
        
        $response = wp_remote_post($stk_push_url, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode($request_data),
            'timeout' => 60,
            'sslverify' => true
        ));
        
        if (is_wp_error($response)) {
            error_log('M-Pesa WP Error: ' . $response->get_error_message());
            return array('success' => false, 'message' => 'Connection failed: ' . $response->get_error_message());
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        
        error_log('M-Pesa Response Code: ' . $response_code);
        error_log('M-Pesa Response Body: ' . $response_body);
        
        $response_data = json_decode($response_body, true);
        
        if (is_null($response_data)) {
            error_log('M-Pesa Error: Invalid JSON response');
            return array('success' => false, 'message' => 'Invalid response from M-Pesa API');
        }
        
        // Check if Till transaction failed, try Paybill instead
        if (isset($response_data['errorCode']) && $response_data['errorCode'] === '400.002.02') {
            error_log('Till failed, trying Paybill transaction type...');
            
            // Try CustomerPayBillOnline (Paybill)
            $request_data['TransactionType'] = 'CustomerPayBillOnline';
            $request_data['AccountReference'] = $business_shortcode; // For paybill, use shortcode as account reference
            
            $response = wp_remote_post($stk_push_url, array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $access_token,
                    'Content-Type' => 'application/json'
                ),
                'body' => json_encode($request_data),
                'timeout' => 60,
                'sslverify' => true
            ));
            
            if (is_wp_error($response)) {
                return array('success' => false, 'message' => 'Connection failed: ' . $response->get_error_message());
            }
            
            $response_body = wp_remote_retrieve_body($response);
            $response_data = json_decode($response_body, true);
            
            error_log('M-Pesa Paybill Response: ' . $response_body);
        }
        
        if (isset($response_data['ResponseCode']) && $response_data['ResponseCode'] === '0') {
            error_log('M-Pesa Success: STK Push sent successfully');
            return array(
                'success' => true,
                'checkout_request_id' => $response_data['CheckoutRequestID'],
                'merchant_request_id' => $response_data['MerchantRequestID']
            );
        } else {
            $error_message = isset($response_data['ResponseDescription']) ? $response_data['ResponseDescription'] : 'Unknown error occurred';
            if (isset($response_data['errorMessage'])) {
                $error_message = $response_data['errorMessage'];
            }
            
            error_log('M-Pesa Error: ' . $error_message);
            return array('success' => false, 'message' => 'M-Pesa Error: ' . $error_message);
        }
    }
    
    private function query_stk_status($checkout_request_id) {
        $consumer_key = get_option('mpesa_consumer_key');
        $consumer_secret = get_option('mpesa_consumer_secret');
        $business_shortcode = get_option('mpesa_business_shortcode');
        $passkey = get_option('mpesa_passkey');
        
        // Production mode only
        $base_url = 'https://api.safaricom.co.ke';
        
        // Get access token
        $access_token = $this->get_access_token($consumer_key, $consumer_secret, $base_url);
        
        if (!$access_token) {
            return array('success' => false, 'message' => 'Failed to get access token');
        }
        
        // Generate password
        $timestamp = date('YmdHis');
        $password = base64_encode($business_shortcode . $passkey . $timestamp);
        
        // Query STK status
        $query_url = $base_url . '/mpesa/stkpushquery/v1/query';
        
        $request_data = array(
            'BusinessShortCode' => $business_shortcode,
            'Password' => $password,
            'Timestamp' => $timestamp,
            'CheckoutRequestID' => $checkout_request_id
        );
        
        $response = wp_remote_post($query_url, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode($request_data),
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            return array('success' => false, 'message' => 'Query failed: ' . $response->get_error_message());
        }
        
        $response_body = wp_remote_retrieve_body($response);
        $response_data = json_decode($response_body, true);
        
        if ($response_data['ResponseCode'] === '0') {
            $result_code = $response_data['ResultCode'];
            
            if ($result_code === '0') {
                // Payment successful
                $mpesa_name = '';
                if (isset($response_data['CallbackMetadata']['Item'])) {
                    foreach ($response_data['CallbackMetadata']['Item'] as $item) {
                        if ($item['Name'] === 'FirstName') {
                            $mpesa_name = $item['Value'];
                            break;
                        }
                    }
                }
                
                return array(
                    'success' => true,
                    'status' => 'done',
                    'mpesa_name' => $mpesa_name
                );
            } elseif ($result_code === '1032') {
                return array('success' => true, 'status' => 'stk_canceled');
            } else {
                return array('success' => true, 'status' => 'invalid_name');
            }
        } else {
            return array('success' => true, 'status' => 'pending');
        }
    }
    
    private function get_access_token($consumer_key, $consumer_secret, $base_url) {
        $auth_url = $base_url . '/oauth/v1/generate?grant_type=client_credentials';
        
        error_log('M-Pesa: Requesting access token from ' . $auth_url);
        
        $credentials = base64_encode($consumer_key . ':' . $consumer_secret);
        
        $response = wp_remote_get($auth_url, array(
            'headers' => array(
                'Authorization' => 'Basic ' . $credentials,
                'Content-Type' => 'application/json'
            ),
            'timeout' => 60,
            'sslverify' => true
        ));
        
        if (is_wp_error($response)) {
            error_log('M-Pesa Auth Error: ' . $response->get_error_message());
            return false;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        
        error_log('M-Pesa Auth Response Code: ' . $response_code);
        error_log('M-Pesa Auth Response: ' . $response_body);
        
        $response_data = json_decode($response_body, true);
        
        if (isset($response_data['access_token'])) {
            error_log('M-Pesa: Access token obtained successfully');
            return $response_data['access_token'];
        } else {
            error_log('M-Pesa Auth Error: No access token in response');
            return false;
        }
    }
    
    private function generate_download_url() {
        // Use your actual file URL
        return '/wp-content/plugins/mpesa-integration/download.php';
    }
}

// Initialize the plugin
new MpesaIntegrationPlugin();
?>