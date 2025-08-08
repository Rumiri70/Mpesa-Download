<?php
/**
 * Plugin Name: M-Pesa Integration Plugin
 * Description: WordPress plugin for M-Pesa payment integration with download functionality
 * Version: 1.0.0
 * Author: Your Name
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('MPESA_PLUGIN_URL', plugin_dir_url(__FILE__));
define('MPESA_PLUGIN_PATH', plugin_dir_path(__FILE__));

class MpesaIntegrationPlugin {
    
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
        
        register_activation_hook(__FILE__, array($this, 'create_tables'));
    }
    
    public function init() {
        // Add shortcode for download button
        add_shortcode('mpesa_download', array($this, 'download_shortcode'));
    }
    
    public function create_tables() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'mpesa_payments';
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            phone_number varchar(15) NOT NULL,
            first_name varchar(100) NOT NULL,
            mpesa_name varchar(100) DEFAULT '',
            amount decimal(10,2) NOT NULL DEFAULT 2.00,
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
    }
    
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
    
    public function admin_page() {
        ?>
        <div class="wrap">
            <h1>M-Pesa STK Push Paybill</h1>
            <div class="card">
                <h2>STK Push Paybill Integration</h2>
                <p>This plugin integrates M-Pesa STK Push for Paybill payments, allowing customers to pay directly from their phones.</p>
                <p><strong>How STK Push Paybill works:</strong></p>
                <ol>
                    <li>Customer enters phone number and clicks pay</li>
                    <li>STK Push popup appears on customer's phone</li>
                    <li>Customer enters M-Pesa PIN to authorize payment to your Paybill</li>
                    <li>Payment is processed automatically</li>
                    <li>Download access is granted upon successful payment</li>
                </ol>
                <p><strong>Quick Setup:</strong></p>
                <ul>
                    <li><a href="<?php echo admin_url('admin.php?page=mpesa-settings'); ?>">Configure STK Push Settings</a></li>
                    <li><a href="<?php echo admin_url('admin.php?page=mpesa-dashboard'); ?>">View Payment Dashboard</a></li>
                </ul>
            </div>
        </div>
        <?php
    }
    
    public function settings_page() {
        if (isset($_POST['submit'])) {
            update_option('mpesa_consumer_key', sanitize_text_field($_POST['consumer_key']));
            update_option('mpesa_consumer_secret', sanitize_text_field($_POST['consumer_secret']));
            update_option('mpesa_business_shortcode', sanitize_text_field($_POST['business_shortcode']));
            update_option('account_reference', sanitize_text_field($_POST['account_reference']));
            update_option('mpesa_passkey', sanitize_text_field($_POST['passkey']));
            update_option('mpesa_callback_url', sanitize_url($_POST['callback_url']));
            update_option('mpesa_environment', sanitize_text_field($_POST['environment']));
            
            echo '<div class="notice notice-success"><p>Settings saved!</p></div>';
        }
        
        $consumer_key = get_option('mpesa_consumer_key', '');
        $consumer_secret = get_option('mpesa_consumer_secret', '');
        $business_shortcode = get_option('mpesa_business_shortcode', '');
        $account_reference = get_option('account_reference', '');
        $passkey = get_option('mpesa_passkey', '');
        $callback_url = get_option('mpesa_callback_url', '');
        $environment = get_option('mpesa_environment', 'sandbox');
        ?>
        
        <div class="wrap">
            <h1>M-Pesa Settings</h1>
            <form method="post" action="">
                <table class="form-table">
                    <tr>
                        <th scope="row">Environment</th>
                        <td>
                            <select name="environment">
                                <option value="sandbox" <?php selected($environment, 'sandbox'); ?>>Sandbox</option>
                                <option value="production" <?php selected($environment, 'production'); ?>>Production</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Consumer Key</th>
                        <td><input type="text" name="consumer_key" value="<?php echo esc_attr($consumer_key); ?>" class="regular-text" /></td>
                    </tr>
                    <tr>
                        <th scope="row">Consumer Secret</th>
                        <td><input type="password" name="consumer_secret" value="<?php echo esc_attr($consumer_secret); ?>" class="regular-text" /></td>
                    </tr>
                    <tr>
                        <th scope="row">Business Shortcode</th>
                        <td><input type="text" name="business_shortcode" value="<?php echo esc_attr($business_shortcode); ?>" class="regular-text" />
                        <p class="description">Your paybill </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Account Reference</th>
                        <td>
                            <input type="text" name="account_reference" value="<?php echo esc_attr($account_reference); ?>" class="regular-text" required />
                            <p class="description">Your Account Number (where money will be sent)</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Passkey</th>
                        <td><input type="password" name="passkey" value="<?php echo esc_attr($passkey); ?>" class="regular-text" /></td>
                    </tr>
                    <tr>
                        <th scope="row">Callback URL</th>
                        <td><input type="url" name="callback_url" value="<?php echo esc_attr($callback_url); ?>" class="regular-text" /></td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
    
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
    
    public function download_shortcode($atts) {
        $atts = shortcode_atts(array(
            'recipient' => 'David',
            'amount' => '2'
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
                    <p>Please enter your phone number to send KSH <?php echo esc_html($atts['amount']); ?> to <?php echo esc_html($atts['recipient']); ?></p>
                    
                    <form id="mpesa-payment-form">
                        <div class="mpesa-form-group">
                            <label for="phone_number">Phone Number:</label>
                            <input type="tel" id="phone_number" name="phone_number" placeholder="254712345678" required>
                        </div>
                        <div class="mpesa-form-group">
                            <label for="first_name">First Name:</label>
                            <input type="text" id="first_name" name="first_name" placeholder="Enter your first name" required>
                        </div>
                        <input type="hidden" name="amount" value="<?php echo esc_attr($atts['amount']); ?>">
                        <input type="hidden" name="recipient" value="<?php echo esc_attr($atts['recipient']); ?>">
                        
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
        
        // Check payment status with M-Pesa API
        $status_response = $this->query_stk_status($payment->checkout_request_id);
        $mpesa_name = 'John';
        if ($status_response['success']) {
            $status = $status_response['status'];
            
            // Update payment status
            $wpdb->update(
                $table_name,
                array(
                    'status' => $status,
                    'mpesa_name' => $mpesa_name
                ),
                array('id' => $payment_id),
                array('%s', '%s'),
                array('%d')
            );
            
            $response = array(
                'status' => $status,
                'mpesa_name' => $mpesa_name,
                'entered_name' => $payment->first_name
            );

            if ($status === 'done') {
                $response['download_url'] = $this->generate_download_url();
            }

            wp_send_json_success($response);
        } else {
            wp_send_json_error($status_response['message']);
        }
    }
    
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
        
        // Compare names (case-insensitive)
        if (strtolower(trim($real_name)) === strtolower(trim($payment->mpesa_name))) {
            // Names match - allow download
            $wpdb->update(
                $table_name,
                array('status' => 'done'),
                array('id' => $payment_id),
                array('%s'),
                array('%d')
            );
            
            wp_send_json_success(array(
                'verified' => true,
                'download_url' => $this->generate_download_url()
            ));
        } else {
            // Names don't match
            wp_send_json_error('Name verification failed. Please contact us at 254738207774 for help.');
        }
    }
    
    private function initiate_stk_push($phone_number, $amount, $payment_id) {
        $environment = get_option('mpesa_environment', 'sandbox');
        $consumer_key = get_option('mpesa_consumer_key');
        $consumer_secret = get_option('mpesa_consumer_secret');
        $business_shortcode = get_option('mpesa_business_shortcode');
        $account_reference = get_option('account_reference', '');
        $passkey = get_option('mpesa_passkey');
        $callback_url = get_option('mpesa_callback_url');
        
        if ($environment === 'sandbox') {
            $base_url = 'https://sandbox.safaricom.co.ke';
        } else {
            $base_url = 'https://api.safaricom.co.ke';
        }
        
        // Get access token
        $access_token = $this->get_access_token($consumer_key, $consumer_secret, $base_url);
        
        if (!$access_token) {
            return array('success' => false, 'message' => 'Failed to get access token');
        }
        
        // Generate password
        $timestamp = date('YmdHis');
        $password = base64_encode($business_shortcode . $passkey . $timestamp);
        
        // STK Push request
        $stk_push_url = $base_url . '/mpesa/stkpush/v1/processrequest';
        
        $request_data = array(
            'BusinessShortCode' => $business_shortcode,
            'Password' => $password,
            'Timestamp' => $timestamp,
            'TransactionType' => 'CustomerPayBillOnline',
            'Amount' => $amount,
            'PartyA' => $phone_number,
            'PartyB' => $business_shortcode,
            'PhoneNumber' => $phone_number,
            'CallBackURL' => $callback_url,
            'AccountReference' => $account_reference,
            'TransactionDesc' => 'book download payment',
        );
        
        $response = wp_remote_post($stk_push_url, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode($request_data),
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            return array('success' => false, 'message' => 'Request failed: ' . $response->get_error_message());
        }
        
        $response_body = wp_remote_retrieve_body($response);
        $response_data = json_decode($response_body, true);
        
        if ($response_data['ResponseCode'] === '0') {
            return array(
                'success' => true,
                'checkout_request_id' => $response_data['CheckoutRequestID'],
                'merchant_request_id' => $response_data['MerchantRequestID']
            );
        } else {
            return array('success' => false, 'message' => $response_data['ResponseDescription']);
        }
    }
    
    private function query_stk_status($checkout_request_id) {
        $environment = get_option('mpesa_environment', 'sandbox');
        $consumer_key = get_option('mpesa_consumer_key');
        $consumer_secret = get_option('mpesa_consumer_secret');
        $business_shortcode = get_option('mpesa_business_shortcode');
        $passkey = get_option('mpesa_passkey');
        
        if ($environment === 'sandbox') {
            $base_url = 'https://sandbox.safaricom.co.ke';
        } else {
            $base_url = 'https://api.safaricom.co.ke';
        }
        
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
        
        $response = wp_remote_get($auth_url, array(
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode($consumer_key . ':' . $consumer_secret)
            ),
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            return false;
        }
        
        $response_body = wp_remote_retrieve_body($response);
        $response_data = json_decode($response_body, true);
        
        return isset($response_data['access_token']) ? $response_data['access_token'] : false;
    }
    
    private function generate_download_url() {
        // Use your actual file URL
        return '/wp-content/plugins/mpesa-integration/download.php';
    }
}

// Initialize the plugin
new MpesaIntegrationPlugin();
?>