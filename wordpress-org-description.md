# PostEx WooCommerce Integration

## Description

Seamlessly integrate PostEx Pakistan's logistics services with your WooCommerce store. Create shipments, manage airway bills, and track orders without leaving your WordPress admin.

### 🚚 One-Click Shipment Creation
Create PostEx orders directly from WooCommerce order pages with an intuitive modal interface. Edit customer details, shipping addresses, and order information before submission.

### 📋 Centralized Order Management  
View and manage all unbooked PostEx orders in a dedicated dashboard. Filter by date range and download multiple airway bill PDFs with just a few clicks.

### 🔄 Automatic Status Synchronization
Keep your WooCommerce orders in sync with PostEx delivery status. The plugin automatically checks for status updates every 12 hours and adds order notes when status changes.

### 🏙️ Smart City Validation
Dynamic city learning system that improves delivery success rates. The plugin learns from successful and failed deliveries to better validate customer cities.

### 🛠️ Professional Admin Experience
Clean, intuitive interface integrated directly into WordPress admin. Comprehensive settings page, error logging, and status monitoring.

## Features

* **Direct API Integration** - Communicates directly with PostEx API v3
* **Bulk Operations** - Download multiple airway bills at once (up to 10)
* **Error Handling** - Comprehensive logging and user-friendly error messages  
* **Security** - CSRF protection, input sanitization, and secure API communication
* **Compatibility** - Works with WordPress 5.0+ and WooCommerce 6.0+
* **Background Processing** - Automatic status sync via WordPress Cron
* **City Learning** - Reduces failed deliveries through intelligent city mapping
* **PDF Generation** - Direct download of PostEx airway bill PDFs

## Installation

1. Upload the plugin files to the `/wp-content/plugins/postex-woocommerce` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Navigate to PostEx > Settings to configure your API credentials.
4. Enter your PostEx API key and pickup address code.
5. Test the connection and start creating orders!

## Configuration

### PostEx API Credentials
You'll need the following from PostEx Pakistan:
* **API Key** - Your unique authentication token
* **Pickup Address Code** - Your pickup location identifier (e.g., "003")

### Plugin Settings
Configure default values for:
* Package weight and dimensions
* Reference number starting point
* Background sync frequency
* City learning preferences

## Usage

### Creating Orders
1. Go to WooCommerce > Orders
2. Open any order and click "Create PostEx Order"  
3. Review/edit details in the modal
4. Click "Create Order" to generate tracking number

### Managing Airway Bills
1. Navigate to PostEx > Airway Bills
2. Use date filters to find orders
3. Select multiple orders for bulk PDF download
4. Track order status changes automatically

### Monitoring Status
* View PostEx status in WooCommerce orders list
* Check detailed logs in PostEx Settings
* Manual sync available for immediate updates

## Screenshots

1. **Order Creation Modal** - Edit customer details before creating PostEx order
2. **Airway Bills Dashboard** - Manage all unbooked orders in one place  
3. **Settings Page** - Configure API credentials and test connection
4. **WooCommerce Integration** - PostEx status column in orders list
5. **City Management** - Track successful and failed city deliveries
6. **Error Logs** - Comprehensive logging with real-time viewer

## Frequently Asked Questions

### How do I get PostEx API credentials?
Contact PostEx Pakistan through their website or customer service. You'll need a merchant account to receive API access.

### Can I test the plugin before going live?
Yes! Use PostEx's sandbox API credentials for testing. Switch to production credentials when ready to go live.

### What happens if my internet connection fails during order creation?
The plugin includes comprehensive error handling. Failed requests are logged and you can retry order creation.

### How often does status sync run?
Automatic sync runs every 12 hours via WordPress Cron. You can also trigger manual sync from the settings page.

### Can I customize the order creation modal?
The modal includes all essential PostEx fields. Contact support if you need additional customization.

### What versions of WordPress and WooCommerce are supported?
- WordPress 5.0 or higher
- WooCommerce 6.0 or higher  
- PHP 7.4 or higher

### Is the plugin secure?
Yes! The plugin includes CSRF protection, input sanitization, secure API communication, and protected log files.

### Where are error logs stored?
Logs are stored in `wp-content/postex-log.php` with automatic rotation. You can view recent logs in the settings page.

### Can I bulk download airway bills?
Yes! Select up to 10 orders and download their PDFs simultaneously from the Airway Bills page.

### What if a city name fails validation?
The plugin learns from failed cities and helps you map them correctly for future orders. Check the Cities management page.

## Support

For plugin support, please use the WordPress.org support forum. For PostEx API issues, contact PostEx Pakistan directly.

## Changelog

### 1.0.0
* Initial release
* PostEx API v3 integration
* One-click order creation from WooCommerce
* Airway bills management dashboard  
* Automatic status synchronization
* Smart city learning system
* Comprehensive error logging
* Security hardening and input validation
* Professional admin interface
* Bulk PDF download functionality

## Privacy Policy

This plugin communicates with PostEx Pakistan's API to create and manage shipments. Order data including customer names, addresses, and phone numbers are transmitted to PostEx for shipping purposes. No data is stored on external servers beyond what's necessary for PostEx integration.

## License

This plugin is licensed under the GPL v2 or later.

---

**PostEx WooCommerce Integration** - Streamline your e-commerce logistics with Pakistan's leading courier service.