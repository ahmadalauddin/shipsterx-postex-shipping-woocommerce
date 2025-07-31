=== ShipsterX – PostEx Shipping for WooCommerce ===
Contributors: ahmadalauddin
Donate link: https://postex.pk
Tags: postex, woocommerce, shipping, logistics, pakistan, courier, airway-bills, cod
Requires at least: 5.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Hassle-free PostEx consignment creation from existing WooCommerce orders. Transform any received order into a PostEx shipment with one click - no need to re-enter customer details, addresses, or order information.

== Description ==

ShipsterX transforms your WooCommerce store with direct PostEx shipment creation from existing orders. Eliminate the hassle of re-entering customer information by creating PostEx consignments directly from your received WooCommerce orders with a single click.

= 🚚 One-Click Shipment Creation =
Create PostEx orders directly from WooCommerce order pages with an intuitive modal interface. Edit customer details, shipping addresses, and order information before submission.

= 📋 Centralized Order Management =
View and manage all unbooked PostEx orders in a dedicated dashboard. Filter by date range and download multiple airway bill PDFs with just a few clicks.

= 🔄 Automatic Status Synchronization =
Keep your WooCommerce orders in sync with PostEx delivery status. The plugin automatically checks for status updates every 12 hours and adds order notes when status changes.

= 🏙️ Smart City Validation =
Dynamic city learning system that improves delivery success rates. The plugin learns from successful and failed deliveries to better validate customer cities.

= Key Features =

* **Direct API Integration** - Communicates directly with PostEx API v3
* **Bulk Operations** - Download multiple airway bills at once (up to 10)
* **Error Handling** - Comprehensive logging and user-friendly error messages
* **Security** - CSRF protection, input sanitization, and secure API communication
* **Background Processing** - Automatic status sync via WordPress Cron
* **City Learning** - Reduces failed deliveries through intelligent city mapping
* **PDF Generation** - Direct download of PostEx airway bill PDFs
* **Professional Interface** - Clean, intuitive admin experience

== Installation ==

= Automatic Installation =
1. Log in to your WordPress admin panel
2. Navigate to Plugins > Add New
3. Search for "PostEx WooCommerce Integration"
4. Click "Install Now" and then "Activate"

= Manual Installation =
1. Upload the plugin files to `/wp-content/plugins/postex-woocommerce/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Navigate to PostEx > Settings to configure your API credentials

= After Installation =
1. Get your PostEx API credentials from PostEx Pakistan
2. Go to PostEx > Settings in WordPress admin
3. Enter your API key and pickup address code
4. Click "Test API Connection" to verify setup
5. Start creating PostEx orders from WooCommerce!

== Frequently Asked Questions ==

= How do I get PostEx API credentials? =
Contact PostEx Pakistan through their website or customer service. You'll need a merchant account to receive API access.

= Can I test the plugin before going live? =
Yes! Use PostEx's sandbox API credentials for testing. Switch to production credentials when ready to go live.

= What happens if my internet connection fails during order creation? =
The plugin includes comprehensive error handling. Failed requests are logged and you can retry order creation.

= How often does status sync run? =
Automatic sync runs every 12 hours via WordPress Cron. You can also trigger manual sync from the settings page.

= Can I bulk download airway bills? =
Yes! Select up to 10 orders and download their PDFs simultaneously from the Airway Bills page.

= What if a city name fails validation? =
The plugin learns from failed cities and helps you map them correctly for future orders. Check the Cities management page.

= Is the plugin secure? =
Yes! The plugin includes CSRF protection, input sanitization, secure API communication, and protected log files.

= Where can I get support? =
Use the WordPress.org support forum for plugin issues. Contact PostEx Pakistan directly for API-related questions.

== Screenshots ==

1. Order creation modal with editable customer details
2. Airway bills dashboard for managing unbooked orders
3. Settings page with API configuration and testing
4. WooCommerce integration showing PostEx status column
5. City management system for tracking delivery success
6. Error logs viewer with real-time updates

== Changelog ==

= 1.0.0 =
* Initial release
* PostEx API v3 integration
* One-click order creation from WooCommerce orders
* Airway bills management dashboard
* Automatic status synchronization every 12 hours
* Smart city learning and validation system
* Comprehensive error logging with rotation
* Security hardening and input validation
* Professional admin interface with settings page
* Bulk PDF download functionality (up to 10 orders)
* Background processing via WordPress Cron
* CSRF protection on all AJAX endpoints
* Dynamic city mapping to reduce failed deliveries
* Real-time log viewer in admin settings
* Manual sync option for immediate status updates

== Upgrade Notice ==

= 1.0.0 =
Initial release of PostEx WooCommerce Integration. Install to streamline your PostEx shipping workflow.

== Privacy Policy ==

This plugin communicates with PostEx Pakistan's API to create and manage shipments. Order data including customer names, addresses, and phone numbers are transmitted to PostEx for shipping purposes. No personal data is stored on external servers beyond what's necessary for PostEx integration.

Data transmitted to PostEx includes:
* Customer name and phone number
* Delivery address and city
* Order value and weight
* Package dimensions

All API communications use secure HTTPS encryption.

== Support ==

For plugin support, please use the WordPress.org support forum. 
For PostEx API issues, contact PostEx Pakistan directly at https://postex.pk

== Requirements ==

* WordPress 5.0 or higher
* WooCommerce 6.0 or higher
* PHP 7.4 or higher
* PostEx merchant account with API access
* Valid PostEx API credentials

== About PostEx ==

PostEx is Pakistan's leading courier and logistics company, providing reliable delivery services across the country. This plugin integrates with their official API to streamline e-commerce shipping for WooCommerce stores.