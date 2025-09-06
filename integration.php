<?php
/**
 * Plugin Name: Do-pay
 * Description: WordPress plugin for M-Pesa payment integration with download functionality (Production Till Mode)
 * Version: 1.0.2
 * Author: Rumiri
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('MPESA_PLUGIN_URL', plugin_dir_url(__FILE__));
define('MPESA_PLUGIN_PATH', plugin_dir_path(__FILE__));

class IntegrationPlugin {
    
    private $config;
    
    // Constructor 
    public function __construct() {
        // Load configuration
        $this->load_config();
        
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
        add_action('wp_ajax_check_mpesa_name', array($this, 'check_mpesa_name'));
        add_action('wp_ajax_nopriv_check_mpesa_name', array($this, 'check_mpesa_name'));
        
        register_activation_hook(__FILE__, array($this, 'create_tables'));
    }

    // Load configuration from config file
    private function load_config() {
        $config_file = MPESA_PLUGIN_PATH . 'config.php';
        
        if (file_exists($config_file)) {
            $this->config = include $config_file;
        } else {
            // Show admin notice if config file is missing
            add_action('admin_notices', array($this, 'config_missing_notice'));
            $this->config = array(); // Empty config to prevent errors
        }
    }
    
    // Admin notice for missing config file
    public function config_missing_notice() {
        ?>
        <div class="notice notice-error">
            <p><strong>M-Pesa Plugin Error:</strong> Configuration file 'config.php' not found. Please create the config file with your M-Pesa credentials.</p>
        </div>
        <?php
    }
    
    // Get configuration value
    private function get_config($key, $default = '') {
        return isset($this->config[$key]) ? $this->config[$key] : $default;
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
            amount decimal(10,2) DEFAULT '',
            checkout_request_id varchar(100) DEFAULT '',
            merchant_request_id varchar(100) DEFAULT '',
            mpesa_receipt_number varchar(100) DEFAULT '',
            status varchar(20) DEFAULT 'pending',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    // Add admin menu and pages (removed settings page)
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
            'Payment Dashboard',
            'Dashboard',
            'manage_options',
            'mpesa-dashboard',
            array($this, 'dashboard_page')
        );
    }
    
    // Enqueue frontend and admin scripts/styles
    public function enqueue_scripts() {
        wp_enqueue_script('jquery');
        wp_enqueue_script('mpesa-frontend', MPESA_PLUGIN_URL . 'assets/frontend.js', array('jquery'), '1.0.0', true);
        wp_enqueue_style('mpesa-frontend', MPESA_PLUGIN_URL . 'assets/frontend.css', array(), '1.0.0');
        
        wp_localize_script('mpesa-frontend', 'mpesa_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('mpesa_nonce')
        ));
    }
    
    public function admin_enqueue_scripts() {
        wp_enqueue_script('mpesa-admin', MPESA_PLUGIN_URL . 'assets/admin.js', array('jquery'), '1.0.0', true);
        wp_enqueue_style('mpesa-admin', MPESA_PLUGIN_URL . 'assets/admin.css', array(), '1.0.0');
    }
    
    // Admin main page (updated to show config status instead of settings link)
    public function admin_page() {
        $config_status = !empty($this->config) ? 'Loaded' : 'Missing';
        $config_class = !empty($this->config) ? 'notice-success' : 'notice-error';
        ?>
        <div class="wrap">
            <h1>M-Pesa STK Push Till Integration</h1>
            
            <div class="notice <?php echo $config_class; ?>">
                <p><strong>Configuration Status:</strong> <?php echo $config_status; ?></p>
                <?php if (empty($this->config)): ?>
                <p>Please create and configure the 'config.php' file with your M-Pesa credentials.</p>
                <?php endif; ?>
            </div>
            
            <div class="card">
                <h2>STK Push Till Integration (Production Mode)</h2>
                <p>This plugin integrates M-Pesa STK Push for Till payments in production mode, allowing customers to pay directly from their phones.</p>
                
                <p><strong>Configuration:</strong></p>
                <ul class="card">
                    <li class="card">Edit 'config.php' file to update M-Pesa credentials</li>
                    <li class="card"><a href="<?php echo admin_url('admin.php?page=mpesa-dashboard'); ?>">View Payment Dashboard</a></li>
                </ul>

                <p><strong>How STK Push Till works:</strong></p>
                <ol>
                    <li>Customer enters phone number and clicks pay</li>
                    <li>STK Push popup appears on customer's phone</li>
                    <li>Customer enters M-Pesa PIN to authorize payment to your Till number</li>
                    <li>Payment is processed automatically</li>
                    <li>Download access is granted upon successful payment</li>
                </ol>
                
                <div class="notice notice-warning">
                    <p><strong>Note:</strong> This plugin is configured for production mode. Make sure you have valid production credentials in your config file.</p>
                </div>
                
                <?php if (!empty($this->config)): ?>
                <div class="card">
                    <h3>Current Configuration Summary</h3>
                    <table class="form-table">
                        <tr>
                            <th scope="row">Environment:</th>
                            <td><strong><?php echo esc_html($this->get_config('environment')); ?></strong></td>
                        </tr>
                        <tr>
                            <th scope="row">Business Shortcode:</th>
                            <td><?php echo esc_html($this->get_config('business_shortcode')); ?></td>
                        </tr>
                        <tr>
                            <th scope="row">Till Number:</th>
                            <td><?php echo esc_html($this->get_config('till_number')); ?></td>
                        </tr>
                        <tr>
                            <th scope="row">Default Amount:</th>
                            <td>KSH <?php echo esc_html($this->get_config('default_amount')); ?></td>
                        </tr>
                        <tr>
                            <th scope="row">Callback URL:</th>
                            <td><?php echo esc_html($this->get_config('callback_url')); ?></td>
                        </tr>
                        <tr>
                            <th scope="row">Consumer Key:</th>
                            <td><?php echo !empty($this->get_config('consumer_key')) ? 'Configured' : 'Not Set'; ?></td>
                        </tr>
                        <tr>
                            <th scope="row">Consumer Secret:</th>
                            <td><?php echo !empty($this->get_config('consumer_secret')) ? 'Configured' : 'Not Set'; ?></td>
                        </tr>
                        <tr>
                            <th scope="row">Passkey:</th>
                            <td><?php echo !empty($this->get_config('passkey')) ? 'Configured' : 'Not Set'; ?></td>
                        </tr>
                    </table>
                </div>
                <?php endif; ?>
            </div>
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
        global $wpdb;

            $per_page = 20;
            $page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
            $offset = ($page - 1) * $per_page;

            $total_payments = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
            $total_pages = ceil($total_payments / $per_page);

            $payments = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM $table_name ORDER BY created_at DESC LIMIT %d OFFSET %d",
                    $per_page,
                    $offset
                )
            );

            

            echo '</div></div>';
        
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
                                    <option value="success" <?php selected($payment->status, 'success'); ?>>Success</option>
                                    <option value="stk_canceled" <?php selected($payment->status, 'stk_canceled'); ?>>STK Canceled</option>
                                    <option value="timeout" <?php selected($payment->status, 'timeout'); ?>>Timeout</option>
                                    <option value="failed" <?php selected($payment->status, 'failed'); ?>>Failed</option>
                                    <option value="insufficient_funds" <?php selected($payment->status, 'insufficient_funds'); ?>>Insufficient Funds</option>
                                    <option value="invalid_phone" <?php selected($payment->status, 'invalid_phone'); ?>>Invalid Phone</option>
                                    <option value="invalid_name" <?php selected($payment->status, 'invalid_name'); ?>>Invalid Name</option>
                                </select>
                                <button type="submit" name="edit_payment_status" class="button">Update</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <!-- Pagination -->
             <?php
             // your table rendering here (loop through $payments)

            echo '<div class="tablenav"><div class="tablenav-pages">';

            echo paginate_links(array(
                'base'      => add_query_arg('paged', '%#%'),
                'format'    => '',
                'prev_text' => __('&laquo; Prev'),
                'next_text' => __('Next &raquo;'),
                'total'     => $total_pages,
                'current'   => $page,
            ));
            ?>

        </div>
        <?php
    }
    
    // Shortcode for download button and payment modal
    public function download_shortcode($atts) {
        $atts = shortcode_atts(array(
            'recipient' => $this->get_config('default_recipient', 'TEACHER DAVID GLOBAL'),
            'amount' => $this->get_config('default_amount', '300')
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
                            <input type="tel" id="phone_number" name="phone_number" placeholder="254123456789" required>
                        </div>
                        <div class="mpesa-form-group">
                            <label for="first_name">First Mpesa Name:</label>
                            <input type="text" id="first_name" name="first_name" placeholder="Enter your first name" required>
                        </div>
                        <input type="hidden" name="amount" value="<?php echo esc_attr($atts['amount']); ?>">
                        <input type="hidden" name="recipient" value="<?php echo esc_attr($atts['recipient']); ?>">
                        
                        <p>For your safety: All data submitted is strictly confidential and protected!</p>
                        
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
    
    // If payment is already completed (successful or failed), return current status
    if (in_array($payment->status, ['done', 'success', 'stk_canceled', 'invalid_name', 'failed', 'timeout', 'insufficient_funds', 'invalid_phone'])) {
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
            $receipt_number = isset($stk_result['receipt_number']) ? $stk_result['receipt_number'] : '';
            
            // Update payment record with new status, M-Pesa name, and receipt number
            $update_data = array('status' => $new_status);
            $update_format = array('%s');
            
            if (!empty($mpesa_name)) {
                $update_data['mpesa_name'] = $mpesa_name;
                $update_format[] = '%s';
            }
            
            if (!empty($receipt_number)) {
                $update_data['mpesa_receipt_number'] = $receipt_number;
                $update_format[] = '%s';
            }
            
            $wpdb->update(
                $table_name,
                $update_data,
                array('id' => $payment_id),
                $update_format,
                array('%d')
            );
            
            // Log the status for debugging
            error_log("Payment ID {$payment_id} status updated to: {$new_status}");
            
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

// Get download URL method 
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
    $expires = time() + $this->get_config('download_token_expiry', 1800); // Default 30 minutes
    
    // Store the token in WordPress options
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
    
    // Get minimum name length from config
    $min_name_length = $this->get_config('minimum_name_length', 3);
    $strict_verification = $this->get_config('enable_strict_name_verification', true);
    
    // STRICT name verification
    $name_matches = false;
    $match_type = '';
    
    if ($strict_verification) {
        // 1. Exact full name match
        if ($entered_name === $mpesa_name) {
            $name_matches = true;
            $match_type = 'full_name';
        }
        // 2. First name match (minimum length check)
        elseif ($entered_first === $mpesa_first && strlen($entered_first) >= $min_name_length) {
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
    } else {
        // Less strict verification if configured
        $name_matches = ($entered_first === $mpesa_first && strlen($entered_first) >= $min_name_length);
        $match_type = 'first_name_relaxed';
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
                'message' => 'Name verification successful! download starting...'
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
    
// Method to check if M-Pesa name has been received
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
    
    private function initiate_stk_push($phone_number, $amount, $payment_id) {
        // Load credentials from config
        $consumer_key = $this->get_config('consumer_key');
        $consumer_secret = $this->get_config('consumer_secret');
        $business_shortcode = $this->get_config('business_shortcode');
        $passkey = $this->get_config('passkey');
        $callback_url = $this->get_config('callback_url');
        
        // Log for debugging
        error_log('M-Pesa STK Push Attempt - Phone: ' . $phone_number . ', Amount: ' . $amount);
        
        // Validate credentials
        if (empty($consumer_key) || empty($consumer_secret) || empty($business_shortcode) || empty($passkey)) {
            error_log('M-Pesa Error: Missing credentials in config file');
            return array('success' => false, 'message' => 'Missing M-Pesa credentials. Please check configuration file.');
        }
        
        // Get base URL from config
        $base_url = $this->get_config('api_base_url', 'https://api.safaricom.co.ke');
        
        // Get access token
        $access_token = $this->get_access_token($consumer_key, $consumer_secret, $base_url);
        
        if (!$access_token) {
            error_log('M-Pesa Error: Failed to get access token');
            return array('success' => false, 'message' => 'Failed to authenticate with M-Pesa. Check your Consumer Key and Secret.');
        }
        
        // Generate password
        $timestamp = date('YmdHis');
        $password = base64_encode($business_shortcode . $passkey . $timestamp);
        
        // STK Push request
        $stk_push_url = $base_url . $this->get_config('stk_push_url', '/mpesa/stkpush/v1/processrequest');
        
        $request_data = array(
            'BusinessShortCode' => $business_shortcode,
            'Password' => $password,
            'Timestamp' => $timestamp,
            'TransactionType' => 'CustomerBuyGoodsOnline',
            'Amount' => intval($amount),
            'PartyA' => $phone_number,
            'PartyB' => $this->get_config('till_number', '6901880'),
            'PhoneNumber' => $phone_number,
            'CallBackURL' => $callback_url,
            'AccountReference' => $this->get_config('account_reference_prefix', 'BOOK') . $payment_id,
            'TransactionDesc' => $this->get_config('transaction_description', 'Book Payment'),
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
        // Load credentials from config
        $consumer_key = $this->get_config('consumer_key');
        $consumer_secret = $this->get_config('consumer_secret');
        $business_shortcode = $this->get_config('business_shortcode');
        $passkey = $this->get_config('passkey');
        
        // Get base URL from config
        $base_url = $this->get_config('api_base_url', 'https://api.safaricom.co.ke');
        
        // Get access token
        $access_token = $this->get_access_token($consumer_key, $consumer_secret, $base_url);
        
        if (!$access_token) {
            return array('success' => false, 'message' => 'Failed to get access token');
        }
        
        // Generate password
        $timestamp = date('YmdHis');
        $password = base64_encode($business_shortcode . $passkey . $timestamp);
        
        // Query STK status
        $query_url = $base_url . $this->get_config('stk_query_url', '/mpesa/stkpushquery/v1/query');
        
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
        
        error_log('STK Query Response: ' . $response_body);
        
        if (isset($response_data['ResponseCode']) && $response_data['ResponseCode'] === '0') {
            $result_code = $response_data['ResultCode'];
            
            // Handle all possible result codes properly
            switch ($result_code) {
                case '0':
                    // Payment successful
                    $mpesa_name = '';
                    $receipt_number = '';
                    
                    if (isset($response_data['CallbackMetadata']['Item'])) {
                        foreach ($response_data['CallbackMetadata']['Item'] as $item) {
                            if ($item['Name'] === 'FirstName') {
                                $mpesa_name = $item['Value'];
                            } elseif ($item['Name'] === 'MpesaReceiptNumber') {
                                $receipt_number = $item['Value'];
                            }
                        }
                    }
                    
                    return array(
                        'success' => true,
                        'status' => 'done',
                        'mpesa_name' => $mpesa_name,
                        'receipt_number' => $receipt_number
                    );
                    
                case '1032':
                    // User canceled the STK push
                    return array('success' => true, 'status' => 'stk_canceled');
                    
                case '1037':
                    // Timeout - user didn't respond to STK push
                    return array('success' => true, 'status' => 'timeout');
                    
                case '1001':
                    // Insufficient funds
                    return array('success' => true, 'status' => 'insufficient_funds');
                    
                case '2001':
                    // Invalid phone number
                    return array('success' => true, 'status' => 'invalid_phone');
                    
                default:
                    // Other error codes - treat as failed
                    error_log('M-Pesa STK Query - Unknown result code: ' . $result_code);
                    return array('success' => true, 'status' => 'failed');
            }
        } elseif (isset($response_data['ResponseCode']) && $response_data['ResponseCode'] === '1') {
            // Request is still being processed
            return array('success' => true, 'status' => 'pending');
        } else {
            // API error or unexpected response
            $error_msg = isset($response_data['ResponseDescription']) ? $response_data['ResponseDescription'] : 'Unknown API error';
            error_log('M-Pesa STK Query Error: ' . $error_msg);
            return array('success' => false, 'message' => $error_msg);
        }
    }
    
    private function get_access_token($consumer_key, $consumer_secret, $base_url) {
        $auth_url = $base_url . $this->get_config('oauth_url', '/oauth/v1/generate?grant_type=client_credentials');
        
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
}

// Initialize the plugin
new IntegrationPlugin();
?>