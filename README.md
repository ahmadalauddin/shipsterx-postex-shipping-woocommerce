# ShipsterX – PostEx Shipping for WooCommerce

## 🚚 Seamless PostEx Integration for WooCommerce

Transform your WooCommerce store with direct PostEx shipment creation and management. This plugin eliminates the need to visit the PostEx merchant portal by bringing all essential features directly into your WordPress admin.

### ✨ Key Features

#### 🎯 **One-Click Order Creation**
- Create PostEx shipments directly from WooCommerce order pages
- Smart modal with editable customer and shipping details
- Automatic reference number generation with increment
- Real-time city validation with dynamic learning

#### 📋 **Airway Bills Management**
- View all unbooked orders in a centralized dashboard
- Bulk PDF download (up to 10 orders at once)
- Date range filtering for easy order management
- Direct links to WooCommerce orders

#### 🔄 **Automatic Status Sync**
- Background synchronization every 12 hours
- Real-time status updates in WooCommerce orders list
- Manual sync option for immediate updates
- Status change notifications via order notes

#### 🏙️ **Smart City Learning**
- Dynamic city validation system
- Learns from successful/failed deliveries
- Automatic city mapping and verification
- Reduces manual intervention over time

#### 🛠️ **Professional Admin Interface**
- Dedicated PostEx menu in WordPress admin
- Easy-to-use settings page with API testing
- Comprehensive error logging with log viewer
- Status indicators and configuration validation

### 🔧 Installation

#### Method 1: WordPress Admin (Recommended)
1. Download the plugin ZIP file
2. Go to **Plugins > Add New** in WordPress admin
3. Click **Upload Plugin** and select the ZIP file
4. Click **Install Now** and then **Activate**

#### Method 2: Manual Installation
1. Extract the plugin ZIP file
2. Upload the `postex-woocommerce` folder to `/wp-content/plugins/`
3. Activate the plugin through the **Plugins** menu in WordPress

### ⚙️ Configuration

#### Step 1: Basic Setup
1. Navigate to **PostEx > Settings** in WordPress admin
2. Enter your **PostEx API Key**
3. Set your **Pickup Address Code** (provided by PostEx)
4. Configure default weight and dimensions
5. Click **Test API Connection** to verify settings

#### Step 2: WooCommerce Integration
The plugin automatically integrates with WooCommerce orders. No additional configuration needed!

### 📖 Usage Guide

#### Creating PostEx Orders
1. Go to **WooCommerce > Orders**
2. Click on any order to open order details
3. Click the **🚚 Create PostEx Order** button
4. Review and edit order details in the modal
5. Click **Create Order** to generate tracking number

#### Managing Airway Bills
1. Navigate to **PostEx > Airway Bills**
2. Use date range filters to find specific orders
3. Select orders using checkboxes
4. Click **📄 Download Selected PDFs** for bulk download

#### Monitoring Order Status
- Check the **PostEx Status** column in WooCommerce orders list
- View detailed status in individual order pages
- Status automatically syncs every 12 hours
- Use **🔄 Sync Order Statuses** for manual sync

### 🔍 Troubleshooting

#### Common Issues

**"API Connection Failed"**
- Verify your API key is correct
- Check internet connectivity
- Ensure PostEx service is operational

**"Invalid Delivery City"**
- Use the city learning system to map cities
- Verify city names with PostEx
- Check the Cities management page for failed attempts

**"Pickup Address Code not configured"**
- Contact PostEx to get your pickup address code
- Enter the code in PostEx Settings page

#### Debug Information
- Check **PostEx > Settings** for recent logs
- Enable WordPress debug mode for detailed logging
- Log files are stored in `wp-content/postex-log.php`

### 🔒 Security Features

- **Secure API Communication**: All API calls use secure HTTPS
- **Input Sanitization**: All user inputs properly validated
- **CSRF Protection**: Nonce verification on all actions  
- **Secure Logging**: Log files protected from direct access
- **Permission Checks**: Only authorized users can access features

### 📊 System Requirements

- **WordPress**: 5.0 or higher
- **WooCommerce**: 6.0 or higher  
- **PHP**: 7.4 or higher
- **PostEx Account**: Valid API credentials required

### 🆘 Support

#### Getting Help
1. **Documentation**: Check this README for common solutions
2. **Logs**: Review error logs in PostEx Settings page
3. **PostEx Support**: Contact PostEx for API-related issues
4. **Community**: WordPress.org plugin support forum

#### Reporting Issues
When reporting issues, please include:
- WordPress and WooCommerce versions
- Plugin version
- Error messages from logs
- Steps to reproduce the issue

### 🔄 Changelog

#### Version 1.0.0
- ✨ Initial release
- 🚚 One-click PostEx order creation
- 📋 Airway bills management dashboard
- 🔄 Automatic status synchronization
- 🏙️ Dynamic city learning system
- 🛠️ Professional admin interface
- 🔒 Comprehensive security measures
- 📊 Advanced error logging

### 📝 Credits

Developed with ❤️ for the WooCommerce and PostEx communities.

### 📄 License

This plugin is licensed under the GPL v2 or later.

---

**Need PostEx API credentials?** Contact [PostEx Pakistan](https://postex.pk) to set up your merchant account.
