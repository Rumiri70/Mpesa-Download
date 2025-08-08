# M-Pesa Integration WordPress Plugin

A comprehensive WordPress plugin for M-Pesa payment integration with download functionality, admin dashboard, and payment status tracking.

## Features

- **Admin Configuration Panel**: Easy setup of M-Pesa API credentials
- **Payment Dashboard**: View all payments with status tracking (done, pending, invalid name, stk canceled)
- **Download Protection**: Users must pay before downloading files
- **Name Verification**: Compares entered name with M-Pesa account name
- **STK Push Integration**: Automatic mobile payment prompts
- **Real-time Status Updates**: Live payment status checking
- **Responsive Design**: Works on all devices

## Installation

1. **Upload Plugin Files**:
   - Extract the zip file
   - Upload the `mpesa-integration` folder to `/wp-content/plugins/`
   - Create the `assets` folder structure as shown below

2. **File Structure**:
```
mpesa-integration/
├── mpesa-integration.php (main plugin file)
├── README.md
└── assets/
    ├── mpesa-frontend.js
    ├── mpesa-frontend.css
    ├── mpesa-admin.js
    └── mpesa-admin.css
```

3. **Activate Plugin**:
   - Go to WordPress Admin → Plugins
   - Find "M-Pesa Integration Plugin"
   - Click "Activate"

## Configuration

### 1. M-Pesa API Setup

1. Go to **M-Pesa → Settings** in WordPress admin
2. Configure the following:
   - **Environment**: Sandbox (testing) or Production
   - **Consumer Key**: Your M-Pesa app consumer key
   - **Consumer Secret**: Your M-Pesa app consumer secret
   - **Business Shortcode**: Your M-Pesa business shortcode
   - **Passkey**: Your M-Pesa passkey
   - **Callback URL**: URL for M-Pesa callbacks (e.g., `https://yoursite.com/mpesa-callback`)

### 2. Getting M-Pesa Credentials

**For Sandbox (Testing)**:
1. Visit [Safaricom Developer Portal](https://developer.safaricom.co.ke/)
2. Create an account and login
3. Create a new app
4. Use test credentials provided

**For Production**:
1. Contact Safaricom to get production credentials
2. Complete M-Pesa integration certification
3. Get live credentials and shortcode

## Usage

### Adding Download Button

Use the shortcode to add a download button to any page or post:

```php
[mpesa_download recipient="David" amount="200"]
```

**Parameters**:
- `recipient`: Name of the payment recipient (default: "David")
- `amount`: Payment amount in KSH (default: "200")

### Payment Flow

1. User clicks "Download" button
2. Modal opens asking for phone number and first name
3. STK Push sent to user's phone
4. User enters M-Pesa PIN to complete payment
5. System verifies payment status
6. If names match, download starts automatically
7. If names don't match, user is asked to enter real name
8. After verification, download proceeds or user gets error message

### Admin Dashboard

Access **M-Pesa → Dashboard** to view:
- All payment transactions
- Payment statuses
- User details
- Transaction timestamps

## File Structure Details

### Main Plugin File (mpesa-integration.php)
- Plugin initialization
- Database table creation
- Admin menu setup
- AJAX handlers
- M-Pesa API integration
- Shortcode functionality

### Frontend Assets
- **mpesa-frontend.js**: Handles payment modals, form submissions, status checking
- **mpesa-frontend.css**: Styles for payment interface and modals

### Admin Assets
- **mpesa-admin.js**: Dashboard functionality, form validation
- **mpesa-admin.css**: Admin interface styling

## Database Schema

The plugin creates a `wp_mpesa_payments` table with:

| Column | Type | Description |
|--------|------|-------------|
| id | INT | Primary key |
| phone_number | VARCHAR(15) | User's phone number |
| first_name | VARCHAR(100) | Name entered by user |
| mpesa_name | VARCHAR(100) | Name from M-Pesa account |
| amount | DECIMAL(10,2) | Payment amount |
| checkout_request_id | VARCHAR(100) | M-Pesa transaction ID |
| merchant_request_id | VARCHAR(100) | M-Pesa merchant ID |
| status | VARCHAR(20) | Payment status |
| created_at | DATETIME | Transaction timestamp |
| updated_at | DATETIME | Last update timestamp |

## Status Types

- **pending**: Payment initiated, waiting for completion
- **done**: Payment completed successfully
- **stk_canceled**: User canceled the STK push
- **invalid_name**: Payment failed due to name mismatch

## Security Features

- AJAX nonce verification
- Input sanitization
- SQL injection prevention
- Phone number validation
- Name verification process

## Troubleshooting

### Common Issues

1. **STK Push not received**:
   - Check phone number format (254XXXXXXXXX)
   - Verify M-Pesa API credentials
   - Ensure phone has network coverage

2. **Payment status not updating**:
   - Check callback URL configuration
   - Verify M-Pesa API endpoints
   - Check server logs for errors

3. **Name verification failing**:
   - M-Pesa names may have different formatting
   - Check for extra spaces or characters
   - Contact support number provided in error

### Support Contact

For payment verification issues, users are directed to contact: **254738207774**

## Customization

### Changing Download File

In the main plugin file, modify the `generate_download_url()` method:

```php
private function generate_download_url() {
    return home_url('/wp-content/uploads/your-custom-file.zip');
}
```

### Styling Customization

Edit the CSS files in the `assets` folder to match your theme's design.

### Payment Amount

Change default amount in shortcode or modify the plugin settings.

## License

This plugin is provided as-is for educational and commercial use. Ensure compliance with M-Pesa terms of service and local regulations.

## Changelog

### Version 1.0.0
- Initial release
- M-Pesa STK Push integration
- Admin dashboard
- Payment verification
- Name matching system
- Download protection

## Support

For technical support, please check:
1. WordPress error logs
2. M-Pesa API documentation
3. Plugin configuration settings
4. Database table structure