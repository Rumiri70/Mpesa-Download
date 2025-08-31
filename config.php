<?php
/**
 * M-Pesa Configuration File
 * 
 * IMPORTANT: Keep this file secure and never commit sensitive credentials to version control
 * Place this file in the same directory as your main plugin file
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

return array(
    // M-Pesa Production Credentials
    'consumer_key' => 'jhPYEVb7S8s7GtXlGAH5mKjv0LWlLUrnxGnRDVZBEoUJmPcJ',
    'consumer_secret' => 'jhPYEVb7S8s7GtXlGAH5mKjv0LWlLUrnxGnRDVZBEoUJmPcJ',
    'business_shortcode' => '7275464', // e.g., 174379
    'passkey' => '2b70cccd38ef52377ababf31db475a69778b5aa290a8da103b7ddf5085ae4bc0',
    
    // Callback URL (must be HTTPS for production)
    'callback_url' => 'https://teacherdavidglobal.com/wp-json/download/callback.php',
    
    // Environment (forced to production)
    'environment' => 'production',
    
    // Default payment settings
    'default_amount' => 300,
    'default_recipient' => 'TEACHER DAVID GLOBAL',
    
    // API URLs (production only)
    'api_base_url' => 'https://api.safaricom.co.ke',
    'oauth_url' => '/oauth/v1/generate?grant_type=client_credentials',
    'stk_push_url' => '/mpesa/stkpush/v1/processrequest',
    'stk_query_url' => '/mpesa/stkpushquery/v1/query',
    
    // Till settings
    'till_number' => '6901880', // Default Till number for CustomerBuyGoodsOnline
    
    // Security settings
    'download_token_expiry' => 1800, // 30 minutes in seconds
    'max_verification_attempts' => 3,
    
    // Transaction settings
    'transaction_description' => 'Book Payment',
    'account_reference_prefix' => 'BOOK',
    
    // Name verification settings
    'minimum_name_length' => 3, // Minimum characters for name matching
    'enable_strict_name_verification' => true,
);
?>