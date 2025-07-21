<?php
/**
 * Plugin Name: PostEx Integration
 * Plugin URI: https://github.com/ahmadalauddin/postex-integration
 * Description: Seamlessly integrate PostEx Pakistan's logistics services with WooCommerce. Create shipments, manage airway bills, and track orders without leaving WordPress admin.
 * Version: 1.0.0
 * Author: ahmadalauddin
 * Author URI: https://ahmadalauddin.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: postex-integration
 * Requires at least: 5.0
 * Tested up to: 6.8
 * Requires PHP: 7.4
 * WC requires at least: 6.0
 * WC tested up to: 8.2
 *
 * @package PostEx_Integration
 * @version 1.0.0
 * @author ahmadalauddin
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Phase 3: PostEx API Client
class PostEx_Client {
    private $api_key;
    private $base_url;

    public function __construct() {
        // Priority order: PostEx main settings > WooCommerce settings > old settings
        $api_key1 = get_option('postex_api_key', '');
        $api_key2 = get_option('woocommerce_postex_api_key', '');
        $api_key3 = get_option('woocommerce_shipping_postex_api_key', '');

        $this->api_key = $api_key1 ?: $api_key2 ?: $api_key3;
        $this->base_url = 'https://api.postex.pk/';
    }

    public function create_order($payload) {
        $url = $this->base_url . 'services/integration/api/order/v3/create-order';
        $args = [
            'headers' => [
                'token' => $this->api_key,
                'Content-Type'  => 'application/json',
                'Accept' => 'application/json',
            ],
            'body'    => wp_json_encode($payload),
            'timeout' => 20,
        ];

        PostEx_Logger::info('Creating PostEx order', ['order_ref' => $payload['orderRefNumber'] ?? 'unknown']);

        $response = wp_remote_post($url, $args);

        if (is_wp_error($response)) {
            $error = PostEx_Error_Handler::handle_api_error($response, 'create_order');
            return $error;
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $json = json_decode($body, true);

        if ($code === 200 && isset($json['dist']['trackingNumber'])) {
            PostEx_Logger::info('Order created successfully', [
                'tracking_number' => $json['dist']['trackingNumber'],
                'status' => $json['dist']['orderStatus'] ?? 'Unknown'
            ]);
            return [
                'success' => true,
                'tracking_number' => $json['dist']['trackingNumber'],
                'order_status' => $json['dist']['orderStatus'],
                'order_date' => $json['dist']['orderDate'],
                'response' => $response
            ];
        } else {
            $error = PostEx_Error_Handler::handle_api_error($response, 'create_order');
            return $error;
        }
    }

    public function list_unbooked($start_date = null, $end_date = null) {
        // Use the correct v2 endpoint from documentation section 3.6 with required startDate parameter
        if (!$start_date) {
            $start_date = date('Y-m-d', strtotime('-30 days')); // Default: last 30 days
        }
        if (!$end_date) {
            $end_date = date('Y-m-d'); // Default: today
        }

        $url = $this->base_url . 'services/integration/api/order/v2/get-unbooked-orders';
        $url_with_params = $url . '?startDate=' . $start_date . '&endDate=' . $end_date;

        $args = [
            'headers' => [
                'token' => $this->api_key,
                'Content-Type'  => 'application/json',
                'Accept' => 'application/json',
            ],
            'timeout' => 20,
        ];
        $response = wp_remote_get($url_with_params, $args);

        if (is_wp_error($response)) {
            return ['error' => $response->get_error_message()];
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $json = json_decode($body, true);

        if ($code === 200) {
            return [
                'success' => true,
                'data' => $json,
                'orders' => isset($json['data']) ? $json['data'] : (isset($json['orders']) ? $json['orders'] : (isset($json['dist']) ? $json['dist'] : []))
            ];
        } else {
            return [
                'error' => isset($json['statusMessage']) ? $json['statusMessage'] : 'Failed to fetch un-booked orders',
                'code' => $code,
                'response_body' => substr($body, 0, 500) // Include response for debugging
            ];
        }
    }

    public function download_awb($tracking_numbers) {
        // Use the correct v1 endpoint as per documentation
        $url = $this->base_url . 'services/integration/api/order/v1/get-invoice';

        // Ensure tracking_numbers is an array and limit to 10 per documentation
        if (!is_array($tracking_numbers)) {
            $tracking_numbers = [$tracking_numbers];
        }
        $tracking_numbers = array_slice($tracking_numbers, 0, 10);

        // Use GET method with query parameters as shown in documentation
        $query_params = 'trackingNumbers=' . implode(',', $tracking_numbers);
        $url_with_params = $url . '?' . $query_params;

        $args = [
            'headers' => [
                'token' => $this->api_key,
                'Accept' => 'application/pdf',
            ],
            'timeout' => 30,
        ];

        $response = wp_remote_get($url_with_params, $args);

        if (is_wp_error($response)) {
            return ['error' => $response->get_error_message()];
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $content_type = wp_remote_retrieve_header($response, 'content-type');

        if ($code === 200 && (strpos($content_type, 'pdf') !== false || strpos($content_type, 'application/octet-stream') !== false)) {
            return [
                'success' => true,
                'pdf_data' => $body,
                'filename' => 'postex-airway-bills-' . date('Y-m-d-H-i-s') . '.pdf'
            ];
        } else {
            $json = json_decode($body, true);
            return [
                'error' => isset($json['statusMessage']) ? $json['statusMessage'] : 'Failed to download airway bills. Response: ' . substr($body, 0, 200),
                'code' => $code
            ];
        }
    }
}

// Activation/Deactivation Hooks
register_activation_hook(__FILE__, 'postex_wc_activate');
register_deactivation_hook(__FILE__, 'postex_wc_deactivate');

function postex_wc_activate() {
    // Create cities learning table
    postex_wc_create_cities_table();

    // Add default cities to the database
    postex_wc_seed_default_cities();

    // Set default options
    add_option('postex_learning_enabled', 'yes');
}

function postex_wc_deactivate() {
    // Deactivation logic - keep the cities data for reactivation
}

function postex_wc_create_cities_table() {
    global $wpdb;

    $table_name = $wpdb->prefix . 'postex_cities';

    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id int(11) NOT NULL AUTO_INCREMENT,
        city_name varchar(100) NOT NULL,
        normalized_name varchar(100) NOT NULL,
        postex_format varchar(100) NOT NULL,
        status enum('verified','failed','pending') DEFAULT 'pending',
        success_count int(11) DEFAULT 0,
        failure_count int(11) DEFAULT 0,
        last_used datetime DEFAULT NULL,
        date_added datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY normalized_name (normalized_name),
        KEY status (status),
        KEY last_used (last_used)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

function postex_wc_seed_default_cities() {
    global $wpdb;

    $table_name = $wpdb->prefix . 'postex_cities';

    // Check if already seeded
    $count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
    if ($count > 0) {
        return; // Already seeded
    }

    // Default verified cities
    $default_cities = [
        'karachi' => 'Karachi',
        'lahore' => 'Lahore',
        'islamabad' => 'Islamabad',
        'rawalpindi' => 'Rawalpindi',
        'faisalabad' => 'Faisalabad',
        'multan' => 'Multan',
        'peshawar' => 'Peshawar',
        'quetta' => 'Quetta',
        'gujranwala' => 'Gujranwala',
        'sialkot' => 'Sialkot',
        'hyderabad' => 'Hyderabad',
        'sargodha' => 'Sargodha',
        'bahawalpur' => 'Bahawalpur',
        'sukkur' => 'Sukkur',
        'larkana' => 'Larkana',
        'sheikhupura' => 'Sheikhupura',
        'jhang' => 'Jhang',
        'rahim yar khan' => 'Rahim Yar Khan',
        'gujrat' => 'Gujrat',
        'kasur' => 'Kasur',
        'mardan' => 'Mardan',
        'mingora' => 'Mingora',
        'sahiwal' => 'Sahiwal',
        'nawabshah' => 'Nawabshah',
        'okara' => 'Okara',
    ];

    foreach ($default_cities as $normalized => $formatted) {
        $wpdb->insert(
            $table_name,
            [
                'city_name' => $formatted,
                'normalized_name' => $normalized,
                'postex_format' => $formatted,
                'status' => 'verified',
                'success_count' => 1,
                'date_added' => current_time('mysql')
            ]
        );
    }
}

// City Learning Functions
function postex_wc_normalize_city($city_name) {
    $normalized = strtolower(trim($city_name));
    // Remove common suffixes
    $normalized = preg_replace('/\s+(city|district|tehsil|div)$/i', '', $normalized);
    return trim($normalized);
}

function postex_wc_get_city_from_db($raw_city) {
    global $wpdb;

    $table_name = $wpdb->prefix . 'postex_cities';
    $normalized = postex_wc_normalize_city($raw_city);

    $city = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table_name WHERE normalized_name = %s",
        $normalized
    ));

    return $city;
}

function postex_wc_learn_city_success($raw_city, $postex_format) {
    global $wpdb;

    $table_name = $wpdb->prefix . 'postex_cities';
    $normalized = postex_wc_normalize_city($raw_city);

    // Try to find existing entry
    $existing = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table_name WHERE normalized_name = %s",
        $normalized
    ));

    if ($existing) {
        // Update existing city with success
        $wpdb->update(
            $table_name,
            [
                'status' => 'verified',
                'postex_format' => $postex_format,
                'success_count' => $existing->success_count + 1,
                'last_used' => current_time('mysql')
            ],
            ['id' => $existing->id]
        );
    } else {
        // Add new verified city
        $wpdb->insert(
            $table_name,
            [
                'city_name' => $raw_city,
                'normalized_name' => $normalized,
                'postex_format' => $postex_format,
                'status' => 'verified',
                'success_count' => 1,
                'last_used' => current_time('mysql'),
                'date_added' => current_time('mysql')
            ]
        );
    }

    // Log the learning
    file_put_contents(WP_CONTENT_DIR . '/postex-api-debug.log',
        date('c') . " CITY LEARNED: '$raw_city' -> '$postex_format' (normalized: '$normalized')\n",
        FILE_APPEND
    );
}

function postex_wc_learn_city_failure($raw_city) {
    global $wpdb;

    $table_name = $wpdb->prefix . 'postex_cities';
    $normalized = postex_wc_normalize_city($raw_city);

    // Try to find existing entry
    $existing = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table_name WHERE normalized_name = %s",
        $normalized
    ));

    if ($existing) {
        // Update existing city with failure
        $wpdb->update(
            $table_name,
            [
                'status' => 'failed',
                'failure_count' => $existing->failure_count + 1,
                'last_used' => current_time('mysql')
            ],
            ['id' => $existing->id]
        );
    } else {
        // Add new failed city
        $wpdb->insert(
            $table_name,
            [
                'city_name' => $raw_city,
                'normalized_name' => $normalized,
                'postex_format' => $raw_city,
                'status' => 'failed',
                'failure_count' => 1,
                'last_used' => current_time('mysql'),
                'date_added' => current_time('mysql')
            ]
        );
    }
}

// Add admin menu for PostEx management
add_action('admin_menu', 'postex_wc_add_admin_menu');
function postex_wc_add_admin_menu() {
    // Main PostEx menu
    add_menu_page(
        'PostEx',
        'PostEx',
        'manage_woocommerce',
        'postex-main',
        'postex_wc_airway_bills_page',
        'dashicons-car',
        56
    );

    // Airway Bills submenu (rename first submenu to avoid duplication)
    add_submenu_page(
        'postex-main',
        'Airway Bills',
        'Airway Bills',
        'manage_woocommerce',
        'postex-main', // This keeps it as the default page
        'postex_wc_airway_bills_page'
    );

    // Cities management submenu
    add_submenu_page(
        'postex-main',
        'PostEx Cities',
        'Cities',
        'manage_woocommerce',
        'postex-cities',
        'postex_wc_cities_admin_page'
    );

    // Settings submenu
    add_submenu_page(
        'postex-main',
        'PostEx Settings',
        'Settings',
        'manage_woocommerce',
        'postex-settings',
        'postex_wc_settings_page'
    );
}

// Settings page for PostEx configuration
function postex_wc_settings_page() {
    // Handle form submission
    if (isset($_POST['submit']) && wp_verify_nonce($_POST['_wpnonce'], 'postex_settings')) {
        // Save settings
        update_option('postex_api_key', sanitize_text_field($_POST['postex_api_key']));
        update_option('postex_pickup_address_code', sanitize_text_field($_POST['postex_pickup_address_code']));
        update_option('postex_pickup_city', sanitize_text_field($_POST['postex_pickup_city']));
        update_option('postex_default_weight', floatval($_POST['postex_default_weight']));
        update_option('postex_default_dimensions', sanitize_text_field($_POST['postex_default_dimensions']));
        update_option('postex_next_ref_number', intval($_POST['postex_next_ref_number']));
        update_option('postex_auto_increment', isset($_POST['postex_auto_increment']) ? 'yes' : 'no');

        echo '<div class="notice notice-success"><p>Settings saved successfully!</p></div>';
    }

    // Test API connection
    if (isset($_POST['test_api']) && wp_verify_nonce($_POST['_wpnonce'], 'postex_settings')) {
        $test_api_key = sanitize_text_field($_POST['postex_api_key']);
        if (!empty($test_api_key)) {
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => 'https://api.postex.pk/services/integration/api/order/v2/get-unbooked-orders?startDate=' . date('Y-m-d', strtotime('-7 days')) . '&endDate=' . date('Y-m-d'),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_HTTPHEADER => [
                    'token: ' . $test_api_key,
                    'Content-Type: application/json',
                    'Accept: application/json',
                ],
            ]);

            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($http_code === 200) {
                echo '<div class="notice notice-success"><p>✅ API Connection Successful!</p></div>';
            } else {
                echo '<div class="notice notice-error"><p>❌ API Connection Failed (HTTP ' . $http_code . ')</p></div>';
            }
        } else {
            echo '<div class="notice notice-error"><p>Please enter an API key to test.</p></div>';
        }
    }

    // Get current settings
    $api_key = get_option('postex_api_key', '');
    $pickup_address_code = get_option('postex_pickup_address_code', '');
    $pickup_city = get_option('postex_pickup_city', 'Lahore');
    $default_weight = get_option('postex_default_weight', '0.5');
    $default_dimensions = get_option('postex_default_dimensions', '15x10x5');
    $next_ref_number = get_option('postex_next_ref_number', 1000);
    $auto_increment = get_option('postex_auto_increment', 'yes');

    ?>
    <div class="wrap">
        <h1>PostEx Settings</h1>

        <div class="notice notice-info">
            <p><strong>Note:</strong> These settings are also available in WooCommerce &gt; Settings &gt; Shipping &gt; PostEx for compatibility.</p>
        </div>

        <form method="post" action="">
            <?php wp_nonce_field('postex_settings'); ?>

            <table class="form-table" role="presentation">
                <tbody>
                    <tr>
                        <th scope="row">
                            <label for="postex_api_key">API Key</label>
                        </th>
                        <td>
                            <input type="text"
                                   name="postex_api_key"
                                   id="postex_api_key"
                                   value="<?php echo esc_attr($api_key); ?>"
                                   class="regular-text"
                                   placeholder="Enter your PostEx API key" />
                            <p class="description">Your PostEx API key for authentication.</p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="postex_pickup_address_code">Pickup Address Code</label>
                        </th>
                        <td>
                            <input type="text"
                                   name="postex_pickup_address_code"
                                   id="postex_pickup_address_code"
                                   value="<?php echo esc_attr($pickup_address_code); ?>"
                                   class="regular-text"
                                   placeholder="e.g., 003" />
                            <p class="description">Your pickup address code from PostEx (e.g., 003).</p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="postex_pickup_city">Pickup City</label>
                        </th>
                        <td>
                            <input type="text"
                                   name="postex_pickup_city"
                                   id="postex_pickup_city"
                                   value="<?php echo esc_attr($pickup_city); ?>"
                                   class="regular-text"
                                   placeholder="e.g., Lahore" />
                            <p class="description">City name for your pickup address.</p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="postex_default_weight">Default Weight (kg)</label>
                        </th>
                        <td>
                            <input type="number"
                                   name="postex_default_weight"
                                   id="postex_default_weight"
                                   value="<?php echo esc_attr($default_weight); ?>"
                                   class="small-text"
                                   step="0.1"
                                   min="0.1" />
                            <p class="description">Default weight for orders when not specified.</p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="postex_default_dimensions">Default Dimensions (cm)</label>
                        </th>
                        <td>
                            <input type="text"
                                   name="postex_default_dimensions"
                                   id="postex_default_dimensions"
                                   value="<?php echo esc_attr($default_dimensions); ?>"
                                   class="regular-text"
                                   placeholder="15x10x5" />
                            <p class="description">Default package dimensions: Length x Width x Height</p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="postex_next_ref_number">Next Reference Number</label>
                        </th>
                        <td>
                            <input type="number"
                                   name="postex_next_ref_number"
                                   id="postex_next_ref_number"
                                   value="<?php echo esc_attr($next_ref_number); ?>"
                                   class="small-text"
                                   min="1" />
                            <p class="description">Next reference number to use for new orders.</p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">Auto Increment Reference Number</th>
                        <td>
                            <fieldset>
                                <label for="postex_auto_increment">
                                    <input name="postex_auto_increment"
                                           type="checkbox"
                                           id="postex_auto_increment"
                                           value="yes"
                                           <?php checked($auto_increment, 'yes'); ?> />
                                    Automatically increment reference number for each order
                                </label>
                            </fieldset>
                        </td>
                    </tr>
                </tbody>
            </table>

            <p class="submit">
                <input type="submit" name="submit" id="submit" class="button-primary" value="Save Settings" />
                <input type="submit" name="test_api" id="test_api" class="button" value="Test API Connection" />
                <button type="button" id="manual_sync" class="button" onclick="postexManualSync()">🔄 Sync Order Statuses</button>
            </p>
        </form>

        <!-- Configuration Status -->
        <div class="postbox" style="margin-top: 20px;">
            <div class="inside">
                <h3>Configuration Status</h3>
                <table class="widefat striped">
                    <tbody>
                        <tr>
                            <td><strong>API Key</strong></td>
                            <td>
                                <?php if (!empty($api_key)): ?>
                                    <span style="color: green;">✅ Configured</span>
                                <?php else: ?>
                                    <span style="color: red;">❌ Missing</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <td><strong>Pickup Address Code</strong></td>
                            <td>
                                <?php if (!empty($pickup_address_code)): ?>
                                    <span style="color: green;">✅ Configured</span>
                                <?php else: ?>
                                    <span style="color: red;">❌ Missing</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <td><strong>PostEx Ready</strong></td>
                            <td>
                                <?php if (!empty($api_key) && !empty($pickup_address_code)): ?>
                                    <span style="color: green;">✅ Ready to use</span>
                                <?php else: ?>
                                    <span style="color: orange;">⚠️ Configuration incomplete</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Status Sync Information -->
        <div class="postbox" style="margin-top: 20px;">
            <div class="inside">
                <h3>Background Status Sync</h3>
                <table class="widefat striped">
                    <tbody>
                        <tr>
                            <td><strong>Sync Schedule</strong></td>
                            <td>
                                <?php
                                $next_sync = wp_next_scheduled('postex_wc_status_sync_cron');
                                if ($next_sync) {
                                    echo 'Next sync: ' . wp_date('Y-m-d H:i:s', $next_sync);
                                } else {
                                    echo 'Not scheduled';
                                }
                                ?>
                            </td>
                        </tr>
                        <tr>
                            <td><strong>Sync Frequency</strong></td>
                            <td>Every 12 hours (automatic)</td>
                        </tr>
                        <tr>
                            <td><strong>Manual Sync</strong></td>
                            <td>Use the "Sync Order Statuses" button above to run immediately</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Error Logs Viewer -->
        <div class="postbox" style="margin-top: 20px;">
            <div class="inside">
                <h3>Recent Logs <button type="button" class="button button-small" onclick="postexRefreshLogs()">🔄 Refresh</button></h3>
                <div id="postex-logs" style="max-height: 300px; overflow-y: auto; background: #f9f9f9; padding: 10px; font-family: monospace; font-size: 12px;">
                    <?php
                    $recent_logs = PostEx_Logger::get_recent_logs(20);
                    if (!empty($recent_logs)) {
                        foreach ($recent_logs as $log) {
                            echo '<div>' . esc_html($log) . '</div>';
                        }
                    } else {
                        echo '<div style="color: #666;">No logs found</div>';
                    }
                    ?>
                </div>
            </div>
        </div>
    </div>

    <script>
    function postexManualSync() {
        const button = document.getElementById('manual_sync');
        const originalText = button.innerHTML;

        button.disabled = true;
        button.innerHTML = '⏳ Syncing...';

        const data = new FormData();
        data.append('action', 'postex_manual_sync');
        data.append('nonce', '<?php echo wp_create_nonce('postex_manual_sync'); ?>');

        fetch(ajaxurl, {
            method: 'POST',
            body: data
        })
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                alert('✅ ' + result.data.message);
            } else {
                alert('❌ ' + (result.data.message || 'Sync failed'));
            }
        })
        .catch(error => {
            alert('❌ Network error: ' + error.message);
        })
        .finally(() => {
            button.disabled = false;
            button.innerHTML = originalText;
        });
    }

    function postexRefreshLogs() {
        const data = new FormData();
        data.append('action', 'postex_get_logs');
        data.append('nonce', '<?php echo wp_create_nonce('postex_get_logs'); ?>');

        fetch(ajaxurl, {
            method: 'POST',
            body: data
        })
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                document.getElementById('postex-logs').innerHTML = result.data.logs;
            } else {
                console.error('Failed to refresh logs');
            }
        })
        .catch(error => {
            console.error('Error refreshing logs:', error);
        });
    }
    </script>
    <?php
}

// PostEx Logger Class for enhanced error tracking
class PostEx_Logger {
    private static $log_file;
    private static $max_log_size = 10485760; // 10MB

    public static function init() {
        self::$log_file = WP_CONTENT_DIR . '/postex-log.php';

        // Create log file with PHP header for security
        if (!file_exists(self::$log_file)) {
            $header = "<?php\n// PostEx Plugin Log File - Access Denied\nif(!defined('ABSPATH')) exit;\n/*\n";
            file_put_contents(self::$log_file, $header, LOCK_EX);
        }

        // Rotate log if it's too large
        self::rotate_log_if_needed();
    }

    public static function log($level, $message, $context = []) {
        if (!self::$log_file) {
            self::init();
        }

        $timestamp = wp_date('Y-m-d H:i:s');
        $context_str = !empty($context) ? ' | Context: ' . wp_json_encode($context) : '';
        $log_entry = sprintf("[%s] %s: %s%s\n", $timestamp, strtoupper($level), $message, $context_str);

        file_put_contents(self::$log_file, $log_entry, FILE_APPEND | LOCK_EX);

        // Also log to WordPress error log for critical errors
        if (in_array($level, ['error', 'critical'])) {
            error_log('PostEx Plugin: ' . $message);
        }
    }

    public static function info($message, $context = []) {
        self::log('info', $message, $context);
    }

    public static function warning($message, $context = []) {
        self::log('warning', $message, $context);
    }

    public static function error($message, $context = []) {
        self::log('error', $message, $context);
    }

    public static function critical($message, $context = []) {
        self::log('critical', $message, $context);
    }

    private static function rotate_log_if_needed() {
        if (!file_exists(self::$log_file)) {
            return;
        }

        if (filesize(self::$log_file) > self::$max_log_size) {
            $backup_file = self::$log_file . '.' . wp_date('Y-m-d-H-i-s') . '.bak';
            rename(self::$log_file, $backup_file);

            // Keep only last 5 backup files
            $backup_files = glob(WP_CONTENT_DIR . '/postex-log.php.*.bak');
            if (count($backup_files) > 5) {
                usort($backup_files, function($a, $b) {
                    return filemtime($a) - filemtime($b);
                });

                // Delete oldest files
                for ($i = 0; $i < count($backup_files) - 5; $i++) {
                    unlink($backup_files[$i]);
                }
            }

            self::init(); // Create new log file
        }
    }

    public static function get_recent_logs($lines = 50) {
        if (!file_exists(self::$log_file)) {
            return [];
        }

        $content = file_get_contents(self::$log_file);
        $log_lines = explode("\n", $content);

        // Remove PHP header and empty lines
        $log_lines = array_filter($log_lines, function($line) {
            return !empty(trim($line)) && !str_starts_with(trim($line), '<?php') &&
                   !str_starts_with(trim($line), '//') && !str_starts_with(trim($line), '/*');
        });

        return array_slice(array_reverse($log_lines), 0, $lines);
    }
}

// Enhanced error handling for PostEx operations
class PostEx_Error_Handler {
    private static $error_codes = [
        '400' => 'Bad Request - Please check your order data',
        '401' => 'Unauthorized - Invalid API key',
        '403' => 'Forbidden - Access denied',
        '404' => 'Not Found - Endpoint or resource not available',
        '422' => 'Validation Error - Please check your input data',
        '429' => 'Rate Limit Exceeded - Please try again later',
        '500' => 'Server Error - PostEx service temporarily unavailable',
        '502' => 'Bad Gateway - PostEx service connectivity issue',
        '503' => 'Service Unavailable - PostEx service temporarily down',
        '504' => 'Gateway Timeout - Request took too long'
    ];

    public static function handle_api_error($response, $context = '') {
        $error_message = 'Unknown error occurred';
        $user_message = 'An error occurred while communicating with PostEx';
        $http_code = 0;

        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            $user_message = 'Network error: Unable to connect to PostEx service';
            PostEx_Logger::error('API Network Error', [
                'context' => $context,
                'error' => $error_message
            ]);
        } else {
            $http_code = wp_remote_retrieve_response_code($response);
            $body = wp_remote_retrieve_body($response);

            if (isset(self::$error_codes[$http_code])) {
                $user_message = self::$error_codes[$http_code];
            }

            // Try to extract more specific error from response
            $json = json_decode($body, true);
            if ($json && isset($json['statusMessage'])) {
                $error_message = $json['statusMessage'];
                if (!empty($json['statusMessage']) && $json['statusMessage'] !== 'SUCCESSFULLY OPERATED') {
                    $user_message = $json['statusMessage'];
                }
            }

            PostEx_Logger::error('API Error', [
                'context' => $context,
                'http_code' => $http_code,
                'response' => substr($body, 0, 500),
                'error_message' => $error_message
            ]);
        }

        return [
            'error' => $user_message,
            'technical_error' => $error_message,
            'code' => $http_code
        ];
    }

    public static function log_operation($operation, $success, $details = []) {
        if ($success) {
            PostEx_Logger::info("Operation Success: $operation", $details);
        } else {
            PostEx_Logger::warning("Operation Failed: $operation", $details);
        }
    }
}

// Initialize logger
PostEx_Logger::init();

// Background status sync functionality
add_action('wp', 'postex_wc_schedule_status_sync');
function postex_wc_schedule_status_sync() {
    if (!wp_next_scheduled('postex_wc_status_sync_cron')) {
        wp_schedule_event(time(), 'twicedaily', 'postex_wc_status_sync_cron');
    }
}

add_action('postex_wc_status_sync_cron', 'postex_wc_sync_order_statuses');
function postex_wc_sync_order_statuses() {
    if (!class_exists('WooCommerce')) {
        return;
    }

    $client = new PostEx_Client();

    // Get orders from last 30 days to check for status updates
    $start_date = date('Y-m-d', strtotime('-30 days'));
    $end_date = date('Y-m-d');

    // Don't use cache for status sync
    $postex_orders = $client->list_unbooked($start_date, $end_date);

    if (!isset($postex_orders['success']) || !$postex_orders['success']) {
        error_log('PostEx Status Sync: Failed to fetch orders - ' . ($postex_orders['error'] ?? 'Unknown error'));
        return;
    }

    $orders = $postex_orders['orders'] ?? [];
    if (empty($orders)) {
        return;
    }

    // Get tracking numbers from PostEx response
    $tracking_numbers = array_column($orders, 'trackingNumber');

    // Find WooCommerce orders with these tracking numbers
    global $wpdb;
    $placeholders = implode(',', array_fill(0, count($tracking_numbers), '%s'));

    $wc_orders_data = $wpdb->get_results($wpdb->prepare("
        SELECT post_id, meta_value as tracking_number
        FROM {$wpdb->postmeta}
        WHERE meta_key = '_postex_tracking'
        AND meta_value IN ($placeholders)
    ", $tracking_numbers));

    if (empty($wc_orders_data)) {
        return;
    }

    // Create mapping of tracking number to order ID
    $wc_orders = [];
    foreach ($wc_orders_data as $wc_order_data) {
        $wc_orders[$wc_order_data->tracking_number] = $wc_order_data->post_id;
    }

    $updated_count = 0;

    // Check each PostEx order for status changes
    foreach ($orders as $postex_order) {
        $tracking_number = $postex_order['trackingNumber'];
        $new_status = $postex_order['transactionStatus'] ?? 'Unknown';

        if (!isset($wc_orders[$tracking_number])) {
            continue;
        }

        $order_id = $wc_orders[$tracking_number];
        $order = wc_get_order($order_id);

        if (!$order) {
            continue;
        }

        // Get current PostEx status from order meta
        $current_status = $order->get_meta('_postex_status');

        // Only update if status has changed
        if ($current_status !== $new_status) {
            $order->update_meta_data('_postex_status', $new_status);
            $order->update_meta_data('_postex_last_sync', current_time('mysql'));
            $order->save();

            // Add order note about status change
            $order->add_order_note(sprintf(
                'PostEx status updated: %s → %s (Tracking: %s)',
                $current_status ?: 'Unknown',
                $new_status,
                $tracking_number
            ));

            $updated_count++;

            // Log status change
            error_log(sprintf(
                'PostEx Status Sync: Updated order #%d - %s → %s (TN: %s)',
                $order_id,
                $current_status ?: 'Unknown',
                $new_status,
                $tracking_number
            ));
        }
    }

    // Log sync summary
    error_log(sprintf(
        'PostEx Status Sync: Completed - %d orders updated out of %d checked',
        $updated_count,
        count($orders)
    ));
}

// Manual status sync for admin
add_action('wp_ajax_postex_manual_sync', 'postex_wc_manual_status_sync');
function postex_wc_manual_status_sync() {
    if (!current_user_can('manage_woocommerce') || !check_ajax_referer('postex_manual_sync', 'nonce', false)) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }

    // Run the sync function
    postex_wc_sync_order_statuses();

    wp_send_json_success(['message' => 'Status sync completed. Check order notes for updates.']);
}

// AJAX handler for log retrieval
add_action('wp_ajax_postex_get_logs', 'postex_wc_ajax_get_logs');
function postex_wc_ajax_get_logs() {
    if (!current_user_can('manage_woocommerce') || !check_ajax_referer('postex_get_logs', 'nonce', false)) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }

    $recent_logs = PostEx_Logger::get_recent_logs(20);
    $logs_html = '';

    if (!empty($recent_logs)) {
        foreach ($recent_logs as $log) {
            $logs_html .= '<div>' . esc_html($log) . '</div>';
        }
    } else {
        $logs_html = '<div style="color: #666;">No logs found</div>';
    }

    wp_send_json_success(['logs' => $logs_html]);
}

// Clean up scheduled events on deactivation
register_deactivation_hook(__FILE__, 'postex_wc_deactivation');
function postex_wc_deactivation() {
    wp_clear_scheduled_hook('postex_wc_status_sync_cron');
}

// Add PostEx status column to WooCommerce orders list
add_filter('manage_edit-shop_order_columns', 'postex_wc_add_order_column');
function postex_wc_add_order_column($columns) {
    $new_columns = [];

    foreach ($columns as $key => $value) {
        $new_columns[$key] = $value;

        // Add PostEx column after order status
        if ($key === 'order_status') {
            $new_columns['postex_status'] = 'PostEx Status';
        }
    }

    return $new_columns;
}

add_action('manage_shop_order_posts_custom_column', 'postex_wc_show_order_column');
function postex_wc_show_order_column($column) {
    global $post;

    if ($column === 'postex_status') {
        $order = wc_get_order($post->ID);
        if (!$order) {
            return;
        }

        $tracking = $order->get_meta('_postex_tracking');
        $status = $order->get_meta('_postex_status');
        $last_sync = $order->get_meta('_postex_last_sync');

        if ($tracking) {
            $status_color = 'gray';
            $status_text = $status ?: 'Unknown';

            // Color code different statuses
            switch (strtolower($status)) {
                case 'unbooked':
                    $status_color = 'orange';
                    break;
                case 'booked':
                    $status_color = 'blue';
                    break;
                case 'shipped':
                case 'in transit':
                    $status_color = 'purple';
                    break;
                case 'delivered':
                    $status_color = 'green';
                    break;
                case 'cancelled':
                case 'returned':
                    $status_color = 'red';
                    break;
            }

            echo '<div style="font-size: 11px;">';
            echo '<span style="color: ' . $status_color . '; font-weight: bold;">' . esc_html($status_text) . '</span><br>';
            echo '<small>' . esc_html($tracking) . '</small>';

            if ($last_sync) {
                echo '<br><small style="color: #666;">Synced: ' . wp_date('M j, H:i', strtotime($last_sync)) . '</small>';
            }
            echo '</div>';
        } else {
            echo '<span style="color: #ccc;">—</span>';
        }
    }
}

// AJAX handler for bulk PDF download
add_action('wp_ajax_postex_download_awb', 'postex_wc_ajax_download_awb');
function postex_wc_ajax_download_awb() {
    if (!current_user_can('manage_woocommerce') || !check_ajax_referer('postex_airway_bills_nonce', 'nonce', false)) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }

    $tracking_numbers = isset($_POST['tracking_numbers']) ? array_map('sanitize_text_field', $_POST['tracking_numbers']) : [];

    if (empty($tracking_numbers) || !is_array($tracking_numbers)) {
        wp_send_json_error(['message' => 'No tracking numbers provided']);
    }

    $client = new PostEx_Client();
    $result = $client->download_awb($tracking_numbers);

    if (isset($result['success']) && $result['success']) {
        // Return success with filename for download
        wp_send_json_success([
            'filename' => $result['filename'],
            'download_url' => admin_url('admin-ajax.php?action=postex_stream_pdf&nonce=' . wp_create_nonce('postex_stream_pdf') . '&tracking_numbers=' . implode(',', $tracking_numbers))
        ]);
    } else {
        $error_message = 'Failed to generate PDF: ' . (isset($result['error']) ? $result['error'] : 'Unknown error') .
                        (isset($result['code']) ? ' (HTTP ' . $result['code'] . ')' : '');
        wp_send_json_error(['message' => $error_message]);
    }
}

// PDF streaming handler
add_action('wp_ajax_postex_stream_pdf', 'postex_wc_ajax_stream_pdf');
function postex_wc_ajax_stream_pdf() {
    if (!current_user_can('manage_woocommerce') || !check_ajax_referer('postex_stream_pdf', 'nonce', false)) {
        wp_die('Unauthorized');
    }

    $tracking_numbers = isset($_GET['tracking_numbers']) ? array_map('sanitize_text_field', explode(',', $_GET['tracking_numbers'])) : [];

    if (empty($tracking_numbers)) {
        wp_die('No tracking numbers provided');
    }

    $client = new PostEx_Client();
    $result = $client->download_awb($tracking_numbers);

    if (isset($result['success']) && $result['success']) {
        // Clear any output buffers to prevent corruption
        if (ob_get_level()) {
            ob_end_clean();
        }

        // Set proper headers for PDF download
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $result['filename'] . '"');
        header('Content-Length: ' . strlen($result['pdf_data']));
        header('Cache-Control: no-cache, must-revalidate');
        header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');
        header('Pragma: no-cache');

        // Output PDF data and exit immediately
        echo $result['pdf_data'];
        exit;
    } else {
        wp_die('Failed to generate PDF: ' . (isset($result['error']) ? $result['error'] : 'Unknown error'));
    }
}

function postex_wc_airway_bills_page() {
    // Handle bulk actions
    if (isset($_POST['action']) && $_POST['action'] === 'download_selected' && wp_verify_nonce($_POST['_wpnonce'], 'postex_airway_bills_action')) {
        $tracking_numbers = isset($_POST['tracking_numbers']) ? array_map('sanitize_text_field', $_POST['tracking_numbers']) : [];

        if (!empty($tracking_numbers)) {
            $client = new PostEx_Client();
            $result = $client->download_awb($tracking_numbers);

            if (isset($result['success']) && $result['success']) {
                // Clear any output buffers to prevent corruption
                if (ob_get_level()) {
                    ob_end_clean();
                }

                // Set proper headers for PDF download
                header('Content-Type: application/pdf');
                header('Content-Disposition: attachment; filename="' . $result['filename'] . '"');
                header('Content-Length: ' . strlen($result['pdf_data']));
                header('Cache-Control: no-cache, must-revalidate');
                header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');
                header('Pragma: no-cache');

                // Output PDF data and exit immediately
                echo $result['pdf_data'];
                exit;
            } else {
                $error_message = 'Failed to download PDFs: ' . (isset($result['error']) ? $result['error'] : 'Unknown error') .
                                (isset($result['code']) ? ' (HTTP ' . $result['code'] . ')' : '');
            }
        } else {
            $error_message = 'No tracking numbers selected for download.';
        }
    }

    // Get date range from form or use defaults
    $start_date = isset($_POST['start_date']) ? sanitize_text_field($_POST['start_date']) : date('Y-m-d', strtotime('-30 days'));
    $end_date = isset($_POST['end_date']) ? sanitize_text_field($_POST['end_date']) : date('Y-m-d');

    // Get un-booked orders with caching (cache key includes date range)
    $cache_key = 'postex_unbooked_orders_' . $start_date . '_' . $end_date;
    $unbooked_data = get_transient($cache_key);

    if ($unbooked_data === false || isset($_POST['refresh_orders']) || isset($_POST['filter_orders'])) {
        $client = new PostEx_Client();
        $unbooked_data = $client->list_unbooked($start_date, $end_date);

        if (isset($unbooked_data['success']) && $unbooked_data['success']) {
            set_transient($cache_key, $unbooked_data, 5 * MINUTE_IN_SECONDS); // 5 minute cache
        }
    }

    ?>
    <div class="wrap">
        <h1>PostEx Airway Bills</h1>

        <!-- Configuration Check -->
        <?php
        $api_key = get_option('postex_api_key', '');
        $pickup_code = get_option('postex_pickup_address_code', '');
        if (empty($api_key) || empty($pickup_code)): ?>
            <div class="notice notice-warning">
                <p><strong>PostEx Configuration Required:</strong>
                    <a href="<?php echo admin_url('admin.php?page=postex-settings'); ?>">Configure API settings</a>
                    to use this feature.
                </p>
            </div>
        <?php endif; ?>

        <?php if (isset($error_message)): ?>
            <div class="notice notice-error"><p><?php echo esc_html($error_message); ?></p></div>
        <?php endif; ?>

        <?php if (isset($unbooked_data['error'])): ?>
            <div class="notice notice-error">
                <p><strong>API Error:</strong> <?php echo esc_html($unbooked_data['error']); ?></p>
                <p>Please check your PostEx API configuration and try again.</p>
            </div>
        <?php endif; ?>

        <!-- Date Range Filter -->
        <div class="tablenav top">
            <div class="alignleft actions">
                <form method="post" style="display: inline-flex; align-items: center; gap: 10px; margin-bottom: 10px;">
                    <?php wp_nonce_field('postex_airway_bills_action'); ?>

                    <label for="start_date" style="font-weight: bold;">From:</label>
                    <input type="date" name="start_date" id="start_date" value="<?php echo esc_attr($start_date); ?>" style="padding: 5px;">

                    <label for="end_date" style="font-weight: bold;">To:</label>
                    <input type="date" name="end_date" id="end_date" value="<?php echo esc_attr($end_date); ?>" style="padding: 5px;">

                    <input type="hidden" name="filter_orders" value="1">
                    <button type="submit" class="button button-primary">📅 Filter Orders</button>

                    <input type="hidden" name="refresh_orders" value="1">
                    <button type="submit" name="refresh_orders" class="button">🔄 Refresh</button>

                    <span style="color: #666; margin-left: 10px;">
                        <small>Showing orders from <?php echo esc_html($start_date); ?> to <?php echo esc_html($end_date); ?></small>
                    </span>
                </form>
            </div>
        </div>

        <?php if (isset($unbooked_data['success']) && $unbooked_data['success']): ?>
            <?php
            $orders = $unbooked_data['orders'];

            // Get related WooCommerce orders
            $wc_orders = [];
            if (!empty($orders)) {
                global $wpdb;
                $tracking_numbers = array_column($orders, 'trackingNumber');
                $placeholders = implode(',', array_fill(0, count($tracking_numbers), '%s'));

                $wc_orders_data = $wpdb->get_results($wpdb->prepare("
                    SELECT post_id, meta_value as tracking_number
                    FROM {$wpdb->postmeta}
                    WHERE meta_key = '_postex_tracking'
                    AND meta_value IN ($placeholders)
                ", $tracking_numbers));

                foreach ($wc_orders_data as $wc_order_data) {
                    $wc_orders[$wc_order_data->tracking_number] = $wc_order_data->post_id;
                }
            }
            ?>

            <form method="post" id="airway-bills-form">
                <?php wp_nonce_field('postex_airway_bills_action'); ?>
                <input type="hidden" name="action" value="download_selected">

                <div class="tablenav top">
                    <div class="alignleft actions bulkactions">
                        <button type="submit" class="button action" onclick="return postexValidateSelection()">📄 Download Selected PDFs</button>
                        <span id="selected-count" style="margin-left: 10px; font-weight: bold;"></span>
                    </div>
                </div>

                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <td class="manage-column column-cb check-column">
                                <input type="checkbox" id="cb-select-all">
                            </td>
                            <th>Tracking Number</th>
                            <th>WooCommerce Order</th>
                            <th>Customer</th>
                            <th>Status</th>
                            <th>Order Date</th>
                            <th>COD Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($orders)): ?>
                            <tr>
                                <td colspan="7" style="text-align: center; padding: 20px;">
                                    <p>No un-booked orders found.</p>
                                    <p><small>Orders will appear here after you create PostEx shipments that haven't been booked yet.</small></p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($orders as $order): ?>
                                <tr>
                                    <th scope="row" class="check-column">
                                        <input type="checkbox" name="tracking_numbers[]" value="<?php echo esc_attr($order['trackingNumber']); ?>" class="tracking-checkbox">
                                    </th>
                                    <td>
                                        <strong><?php echo esc_html($order['trackingNumber']); ?></strong>
                                    </td>
                                    <td>
                                        <?php if (isset($wc_orders[$order['trackingNumber']])): ?>
                                            <a href="<?php echo admin_url('post.php?post=' . $wc_orders[$order['trackingNumber']] . '&action=edit'); ?>" target="_blank">
                                                Order #<?php echo $wc_orders[$order['trackingNumber']]; ?>
                                            </a>
                                        <?php else: ?>
                                            <span style="color: #666;">Not found</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php echo esc_html($order['customerName'] ?? 'N/A'); ?>
                                        <?php if (isset($order['customerPhone'])): ?>
                                            <br><small><?php echo esc_html($order['customerPhone']); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="status-badge" style="padding: 3px 8px; border-radius: 3px; font-size: 11px; font-weight: bold; background: #fff3cd; color: #856404;">
                                            <?php echo esc_html($order['orderStatus'] ?? 'UnBooked'); ?>
                                        </span>
                                    </td>
                                    <td><?php echo isset($order['orderDate']) ? wp_date('Y-m-d H:i', strtotime($order['orderDate'])) : 'N/A'; ?></td>
                                    <td><?php echo isset($order['codAmount']) ? 'PKR ' . number_format($order['codAmount'], 2) : 'N/A'; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>

                <?php if (!empty($orders)): ?>
                <div class="tablenav bottom">
                    <div class="alignleft actions bulkactions">
                        <button type="submit" class="button action" onclick="return postexValidateSelection()">📄 Download Selected PDFs</button>
                        <span style="margin-left: 20px; color: #666;">
                            <strong><?php echo count($orders); ?></strong> un-booked orders found
                        </span>
                    </div>
                </div>
                <?php endif; ?>
            </form>

        <?php else: ?>
            <div class="notice notice-warning">
                <p>Unable to fetch un-booked orders. Please check your API configuration.</p>
            </div>
        <?php endif; ?>

    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const selectAll = document.getElementById('cb-select-all');
        const checkboxes = document.querySelectorAll('.tracking-checkbox');
        const selectedCount = document.getElementById('selected-count');

        // Select all functionality
        if (selectAll) {
            selectAll.addEventListener('change', function() {
                checkboxes.forEach(checkbox => {
                    checkbox.checked = this.checked;
                });
                updateSelectedCount();
            });
        }

        // Individual checkbox change
        checkboxes.forEach(checkbox => {
            checkbox.addEventListener('change', updateSelectedCount);
        });

        function updateSelectedCount() {
            const selected = document.querySelectorAll('.tracking-checkbox:checked').length;
            if (selectedCount) {
                selectedCount.textContent = selected > 0 ? `(${selected} selected)` : '';
            }
        }

        // Initial count
        updateSelectedCount();
    });

    function postexValidateSelection() {
        const selected = document.querySelectorAll('.tracking-checkbox:checked').length;
        if (selected === 0) {
            alert('Please select at least one order to download PDFs.');
            return false;
        }

        if (selected > 10) {
            alert('You can download a maximum of 10 PDFs at once. Please select fewer orders.');
            return false;
        }

        return confirm(`Download PDFs for ${selected} selected order(s)?`);
    }
    </script>
    <?php
}

function postex_wc_cities_admin_page() {
    global $wpdb;

    $table_name = $wpdb->prefix . 'postex_cities';

    // Handle actions
    if (isset($_POST['action']) && wp_verify_nonce($_POST['_wpnonce'], 'postex_cities_action')) {
        if ($_POST['action'] === 'delete' && isset($_POST['city_id'])) {
            $wpdb->delete($table_name, ['id' => intval($_POST['city_id'])]);
            echo '<div class="notice notice-success"><p>City deleted successfully.</p></div>';
        } elseif ($_POST['action'] === 'verify' && isset($_POST['city_id'])) {
            $wpdb->update($table_name, ['status' => 'verified'], ['id' => intval($_POST['city_id'])]);
            echo '<div class="notice notice-success"><p>City verified successfully.</p></div>';
        } elseif ($_POST['action'] === 'add_city') {
            $city_name = sanitize_text_field($_POST['city_name']);
            $normalized = postex_wc_normalize_city($city_name);

            $wpdb->insert($table_name, [
                'city_name' => $city_name,
                'normalized_name' => $normalized,
                'postex_format' => $city_name,
                'status' => 'verified',
                'success_count' => 1,
                'date_added' => current_time('mysql')
            ]);
            echo '<div class="notice notice-success"><p>City added successfully.</p></div>';
        }
    }

    // Get statistics
    $stats = $wpdb->get_row("
        SELECT
            COUNT(*) as total,
            SUM(CASE WHEN status = 'verified' THEN 1 ELSE 0 END) as verified,
            SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending
        FROM $table_name
    ");

    // Get cities with pagination
    $per_page = 20;
    $page = isset($_GET['paged']) ? intval($_GET['paged']) : 1;
    $offset = ($page - 1) * $per_page;

    $filter = isset($_GET['filter']) ? sanitize_text_field($_GET['filter']) : 'all';
    $where = $filter !== 'all' ? $wpdb->prepare(" WHERE status = %s", $filter) : '';

    $cities = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table_name $where ORDER BY last_used DESC, success_count DESC LIMIT %d OFFSET %d",
        $per_page, $offset
    ));

    $total_cities = $wpdb->get_var("SELECT COUNT(*) FROM $table_name $where");
    $total_pages = ceil($total_cities / $per_page);

    ?>
    <div class="wrap">
        <h1>PostEx Cities Management</h1>

        <!-- Statistics -->
        <div class="postex-stats" style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; margin: 20px 0;">
            <div class="stats-card" style="background: #f1f1f1; padding: 15px; border-radius: 4px; text-align: center;">
                <h3 style="margin: 0; color: #333;"><?php echo $stats->total; ?></h3>
                <p style="margin: 5px 0 0;">Total Cities</p>
            </div>
            <div class="stats-card" style="background: #d4edda; padding: 15px; border-radius: 4px; text-align: center;">
                <h3 style="margin: 0; color: #155724;"><?php echo $stats->verified; ?></h3>
                <p style="margin: 5px 0 0;">Verified</p>
            </div>
            <div class="stats-card" style="background: #f8d7da; padding: 15px; border-radius: 4px; text-align: center;">
                <h3 style="margin: 0; color: #721c24;"><?php echo $stats->failed; ?></h3>
                <p style="margin: 5px 0 0;">Failed</p>
            </div>
            <div class="stats-card" style="background: #fff3cd; padding: 15px; border-radius: 4px; text-align: center;">
                <h3 style="margin: 0; color: #856404;"><?php echo $stats->pending; ?></h3>
                <p style="margin: 5px 0 0;">Pending</p>
            </div>
        </div>

        <!-- Add New City Form -->
        <div class="add-city-form" style="background: #fff; padding: 15px; border: 1px solid #ddd; border-radius: 4px; margin: 20px 0;">
            <h3>Add New City</h3>
            <form method="post" style="display: flex; gap: 10px; align-items: end;">
                <?php wp_nonce_field('postex_cities_action'); ?>
                <input type="hidden" name="action" value="add_city">
                <div>
                    <label for="city_name">City Name:</label><br>
                    <input type="text" name="city_name" id="city_name" required style="width: 200px;">
                </div>
                <button type="submit" class="button button-primary">Add City</button>
            </form>
        </div>

        <!-- Filters -->
        <div class="tablenav top">
            <div class="alignleft actions">
                <select id="filter-status">
                    <option value="all" <?php selected($filter, 'all'); ?>>All Cities</option>
                    <option value="verified" <?php selected($filter, 'verified'); ?>>Verified</option>
                    <option value="failed" <?php selected($filter, 'failed'); ?>>Failed</option>
                    <option value="pending" <?php selected($filter, 'pending'); ?>>Pending</option>
                </select>
                <input type="button" id="filter-submit" class="button" value="Filter">
            </div>
        </div>

        <!-- Cities Table -->
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>City Name</th>
                    <th>PostEx Format</th>
                    <th>Status</th>
                    <th>Success Count</th>
                    <th>Failure Count</th>
                    <th>Last Used</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($cities as $city): ?>
                <tr>
                    <td><strong><?php echo esc_html($city->city_name); ?></strong><br>
                        <small style="color: #666;"><?php echo esc_html($city->normalized_name); ?></small>
                    </td>
                    <td><?php echo esc_html($city->postex_format); ?></td>
                    <td>
                        <span class="status-badge status-<?php echo $city->status; ?>" style="padding: 3px 8px; border-radius: 3px; font-size: 11px; font-weight: bold;
                            <?php if ($city->status === 'verified') echo 'background: #d4edda; color: #155724;';
                                  elseif ($city->status === 'failed') echo 'background: #f8d7da; color: #721c24;';
                                  else echo 'background: #fff3cd; color: #856404;'; ?>">
                            <?php echo ucfirst($city->status); ?>
                        </span>
                    </td>
                    <td><?php echo $city->success_count; ?></td>
                    <td><?php echo $city->failure_count; ?></td>
                    <td><?php echo $city->last_used ? wp_date('Y-m-d H:i', strtotime($city->last_used)) : '-'; ?></td>
                    <td>
                        <?php if ($city->status !== 'verified'): ?>
                        <form method="post" style="display: inline;">
                            <?php wp_nonce_field('postex_cities_action'); ?>
                            <input type="hidden" name="action" value="verify">
                            <input type="hidden" name="city_id" value="<?php echo $city->id; ?>">
                            <button type="submit" class="button button-small" onclick="return confirm('Verify this city?')">Verify</button>
                        </form>
                        <?php endif; ?>

                        <form method="post" style="display: inline;">
                            <?php wp_nonce_field('postex_cities_action'); ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="city_id" value="<?php echo $city->id; ?>">
                            <button type="submit" class="button button-small button-link-delete" onclick="return confirm('Delete this city?')" style="color: #a00;">Delete</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>

                <?php if (empty($cities)): ?>
                <tr><td colspan="7" style="text-align: center; padding: 20px;">No cities found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <div class="tablenav bottom">
            <div class="tablenav-pages">
                <?php
                $page_links = paginate_links([
                    'base' => add_query_arg('paged', '%#%'),
                    'format' => '',
                    'prev_text' => '&laquo;',
                    'next_text' => '&raquo;',
                    'current' => $page,
                    'total' => $total_pages
                ]);
                echo $page_links;
                ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <script>
    document.getElementById('filter-submit').addEventListener('click', function() {
        const status = document.getElementById('filter-status').value;
        const url = new URL(window.location);
        url.searchParams.set('filter', status);
        url.searchParams.delete('paged');
        window.location = url;
    });
    </script>
    <?php
}

// Add settings tab to WooCommerce Shipping
add_filter('woocommerce_get_sections_shipping', 'postex_wc_add_settings_section');
function postex_wc_add_settings_section($sections) {
    $sections['postex'] = __('PostEx', 'postex-wc');
    return $sections;
}

add_filter('woocommerce_get_settings_shipping', 'postex_wc_settings', 10, 2);
function postex_wc_settings($settings, $current_section) {
    if ($current_section == 'postex') {
        $settings = array(
            array(
                'title' => __('PostEx Settings', 'postex-wc'),
                'type'  => 'title',
                'desc'  => '',
                'id'    => 'postex_settings_title'
            ),
            array(
                'title'    => __('API Key', 'postex-wc'),
                'desc'     => __('Enter your PostEx API key.', 'postex-wc'),
                'id'       => 'postex_api_key',
                'type'     => 'text',
                'default'  => '',
                'desc_tip' => true,
            ),
            array(
                'title'    => __('Pickup Address Code', 'postex-wc'),
                'desc'     => __('Enter your PostEx pickup address code (e.g., 003).', 'postex-wc'),
                'id'       => 'postex_pickup_address_code',
                'type'     => 'text',
                'default'  => '',
                'desc_tip' => true,
            ),
            array(
                'title'    => __('Pickup City', 'postex-wc'),
                'desc'     => __('City name for your pickup address.', 'postex-wc'),
                'id'       => 'postex_pickup_city',
                'type'     => 'text',
                'default'  => 'Lahore',
                'desc_tip' => true,
            ),
            array(
                'title'    => __('Default Weight (kg)', 'postex-wc'),
                'desc'     => __('Default weight for orders when not specified.', 'postex-wc'),
                'id'       => 'postex_default_weight',
                'type'     => 'number',
                'default'  => '0.5',
                'custom_attributes' => array(
                    'step' => '0.1',
                    'min'  => '0.1'
                ),
                'desc_tip' => true,
            ),
            array(
                'title'    => __('Default Dimensions (cm)', 'postex-wc'),
                'desc'     => __('Default package dimensions: Length x Width x Height', 'postex-wc'),
                'id'       => 'postex_default_dimensions',
                'type'     => 'text',
                'default'  => '15x10x5',
                'desc_tip' => true,
            ),
            array(
                'title'    => __('Next Ref Number', 'postex-wc'),
                'id'       => 'postex_next_ref_number',
                'type'     => 'number',
                'default'  => 1000,
            ),
            array(
                'title'    => __('Auto Increment Ref #', 'postex-wc'),
                'id'       => 'postex_auto_increment',
                'type'     => 'checkbox',
                'default'  => 'yes',
            ),
            array(
                'type' => 'sectionend',
                'id'   => 'postex_settings_end'
            ),
        );
    }
    return $settings;
}

// Phase 2: Add order action button and enqueue modal assets

// Add a meta box with the Create PostEx Order button on the order edit screen
add_action('add_meta_boxes', function() {
    add_meta_box(
        'postex_wc_order_box',
        __('PostEx Shipment', 'postex-wc'),
        function($post) {
            $order = wc_get_order($post->ID);
            if ($order && !$order->get_meta('_postex_tracking')) {
                echo '<button type="button" class="button button-primary postex_create" style="margin-bottom:10px;">🚚 ' . esc_html__('Create PostEx Order', 'postex-wc') . '</button>';
            } else if ($order) {
                $tracking = $order->get_meta('_postex_tracking');
                $status = $order->get_meta('_postex_status');
                echo '<p style="color:green;">' . esc_html__('PostEx order created', 'postex-wc') . '</p>';
                echo '<p><strong>Tracking:</strong> ' . esc_html($tracking) . '</p>';
                if ($status) {
                    echo '<p><strong>Status:</strong> ' . esc_html($status) . '</p>';
                }
            }
        },
        'shop_order',
        'side',
        'high'
    );
});

// 2. Enqueue React/JS modal assets only on order admin screens
add_action('admin_enqueue_scripts', 'postex_wc_enqueue_admin_assets');
function postex_wc_enqueue_admin_assets($hook) {
    global $pagenow;
    if ($pagenow === 'post.php' && isset($_GET['post']) && get_post_type($_GET['post']) === 'shop_order') {
        wp_enqueue_script(
            'postex-wc-modal',
            plugins_url('assets/js/postex-modal.js', __FILE__),
            array('wp-element', 'jquery'),
            '1.0.0',
            true
        );
        // Get order data for the modal
        $order = wc_get_order(intval($_GET['post']));
        $order_items = [];
        if ($order) {
            foreach ($order->get_items() as $item) {
                $product = $item->get_product();
                $order_items[] = [
                    'name' => $item->get_name(),
                    'quantity' => $item->get_quantity(),
                    'price' => floatval($item->get_total()),
                    'sku' => $product ? $product->get_sku() : '',
                ];
            }
        }

        wp_localize_script('postex-wc-modal', 'PostExWC', array(
            'nonce' => wp_create_nonce('postex_wc_order_action'),
            'order_id' => intval($_GET['post']),
            'order_data' => $order ? [
                'total' => $order->get_total(),
                'customer_name' => trim($order->get_shipping_first_name() . ' ' . $order->get_shipping_last_name()),
                'customer_phone' => $order->get_billing_phone(),
                'delivery_address' => $order->get_shipping_address_1(),
                'city_name' => $order->get_shipping_city(),
                'items' => $order_items,
                'default_weight' => get_option('postex_default_weight', '0.5'),
                'default_dimensions' => get_option('postex_default_dimensions', '15x10x5')
            ] : null
        ));
    }
}


// 5. AJAX handler for creating PostEx order
add_action('wp_ajax_postex_wc_create_order', 'postex_wc_ajax_create_order');
function postex_wc_ajax_create_order() {
    // Correct nonce check; this also sanitizes $_POST['nonce']
    if (!check_ajax_referer('postex_wc_order_action', 'nonce', false)) {
        wp_send_json_error(['message' => 'Invalid nonce.']);
    }
    $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
    $weight = isset($_POST['weight']) ? floatval($_POST['weight']) : floatval(get_option('postex_default_weight', 0.5));

    // Get edited values from the modal (if provided)
    $edited_customer_name = isset($_POST['customer_name']) ? sanitize_text_field($_POST['customer_name']) : '';
    $edited_customer_phone = isset($_POST['customer_phone']) ? sanitize_text_field($_POST['customer_phone']) : '';
    $edited_delivery_address = isset($_POST['delivery_address']) ? sanitize_textarea_field($_POST['delivery_address']) : '';
    $edited_city_name = isset($_POST['city_name']) ? sanitize_text_field($_POST['city_name']) : '';
    $edited_invoice_payment = isset($_POST['invoice_payment']) ? floatval($_POST['invoice_payment']) : 0;
    $edited_dimensions = isset($_POST['dimensions']) ? sanitize_text_field($_POST['dimensions']) : '';

    file_put_contents(__DIR__ . '/postex-api-debug.log', 'Request received: ' . json_encode($_POST) . "\n", FILE_APPEND);

    if (!$order_id || !$weight) {
        wp_send_json_error(['message' => 'Invalid order or weight.']);
    }
    $order = wc_get_order($order_id);
    if (!$order) {
        wp_send_json_error(['message' => 'Order not found.']);
    }

    // Map Woo order data to PostEx v3 payload
    $ref_number = get_option('postex_next_ref_number', get_option('woocommerce_shipping_postex_next_ref_number', 1000));
    $pickup_address_code = get_option('postex_pickup_address_code', '');
    $default_dimensions = get_option('postex_default_dimensions', '15x10x5');

    // Use edited dimensions if provided, otherwise use default
    $dimensions_string = !empty($edited_dimensions) ? $edited_dimensions : $default_dimensions;
    $dimensions_parts = explode('x', $dimensions_string);

    if (empty($pickup_address_code)) {
        wp_send_json_error(['message' => 'Pickup Address Code not configured. Go to WooCommerce → Settings → Shipping → PostEx']);
    }

    // Dynamic city validation with learning
    $raw_city = !empty($edited_city_name) ? trim($edited_city_name) : trim($order->get_shipping_city());
    $city_data = postex_wc_get_city_from_db($raw_city);

    if ($city_data) {
        if ($city_data->status === 'failed') {
            wp_send_json_error([
                'message' => "City '$raw_city' has failed " . $city_data->failure_count . " time(s) in PostEx API. Please verify the city name or contact PostEx support.",
                'city_issue' => true
            ]);
        }

        // Use learned PostEx format
        $mapped_city = $city_data->postex_format;
        $city_supported = true;
    } else {
        // New city - will try to learn from API response
        $mapped_city = ucfirst(postex_wc_normalize_city($raw_city));
        $city_supported = false; // Will be determined by API response
    }

    file_put_contents(__DIR__ . '/postex-api-debug.log',
        "Dynamic City Validation:\n" .
        "Raw City: " . $raw_city . "\n" .
        "Mapped City: " . $mapped_city . "\n" .
        "In Database: " . ($city_data ? 'YES' : 'NO') . "\n" .
        "Status: " . ($city_data ? $city_data->status : 'new') . "\n" .
        "Will Learn: " . ($city_supported ? 'NO' : 'YES') . "\n\n",
        FILE_APPEND
    );

    // Get order items for PostEx
    $order_items = [];
    foreach ($order->get_items() as $item) {
        $product = $item->get_product();
        $order_items[] = [
            'name' => $item->get_name(),
            'quantity' => $item->get_quantity(),
            'price' => floatval($item->get_total()),
            'sku' => $product ? $product->get_sku() : '',
        ];
    }

    $payload = [
        'orderRefNumber' => (string)$ref_number,
        'orderType'      => 'Normal',
        'invoicePayment' => $edited_invoice_payment > 0 ? $edited_invoice_payment : floatval($order->get_total()),
        'weight'         => $weight,
        'customerName'   => !empty($edited_customer_name) ? $edited_customer_name : trim($order->get_shipping_first_name() . ' ' . $order->get_shipping_last_name()),
        'customerPhone'  => !empty($edited_customer_phone) ? $edited_customer_phone : $order->get_billing_phone(),
        'deliveryAddress' => !empty($edited_delivery_address) ? $edited_delivery_address : $order->get_shipping_address_1(),
        'cityName'       => $mapped_city,
        'pickupAddressCode' => $pickup_address_code,
        'dimensions'     => [
            'length' => isset($dimensions_parts[0]) ? intval($dimensions_parts[0]) : 15,
            'width'  => isset($dimensions_parts[1]) ? intval($dimensions_parts[1]) : 10,
            'height' => isset($dimensions_parts[2]) ? intval($dimensions_parts[2]) : 5
        ],
        'orderDetails' => implode(', ', array_map(function($item) {
            return $item['quantity'] . 'x ' . $item['name'];
        }, $order_items))
    ];

    $client = new PostEx_Client();

    // Debug API key - try multiple possible setting keys
    $api_key1 = get_option('woocommerce_shipping_postex_api_key', '');
    $api_key2 = get_option('woocommerce_postex_api_key', '');
    $api_key3 = get_option('postex_api_key', '');

    $api_key = $api_key1 ?: $api_key2 ?: $api_key3;

    file_put_contents(__DIR__ . '/postex-api-debug.log',
        "API Key attempts:\n" .
        "woocommerce_shipping_postex_api_key: " . ($api_key1 ? substr($api_key1, 0, 10) . "..." : 'EMPTY') . "\n" .
        "woocommerce_postex_api_key: " . ($api_key2 ? substr($api_key2, 0, 10) . "..." : 'EMPTY') . "\n" .
        "postex_api_key: " . ($api_key3 ? substr($api_key3, 0, 10) . "..." : 'EMPTY') . "\n" .
        "Final API Key: " . ($api_key ? substr($api_key, 0, 10) . "..." : 'EMPTY') . "\n",
        FILE_APPEND
    );

    if (empty($api_key)) {
        wp_send_json_error(['message' => 'PostEx API key not configured. Go to WooCommerce → Settings → Shipping → PostEx']);
    }

    $response = $client->create_order($payload);

    // Error check for WP_Error from API call
    if (is_wp_error($response)) {
        $error_message = $response->get_error_message();
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $log_path = WP_CONTENT_DIR . '/postex-api-debug.log';
            file_put_contents($log_path, date('c') . "\nAPI ERROR: $error_message\n\n", FILE_APPEND);
        }
        wp_send_json_error(['message' => 'API error: ' . $error_message]);
    }
    $code = wp_remote_retrieve_response_code($response);
    $body = wp_remote_retrieve_body($response);
    $json = json_decode($body, true);

    // Enhanced logging for diagnostics
    $log_path = WP_CONTENT_DIR . '/postex-api-debug.log';
    file_put_contents(
        $log_path,
        date('c') .
        "\nAPI Key: " . substr($api_key, 0, 10) . "..." .
        "\nRequest Payload: " . print_r($payload, true) .
        "\nResponse Code: " . $code .
        "\nRaw Body: " . $body .
        "\nParsed JSON: " . print_r($json, true) . "\n\n",
        FILE_APPEND
    );

    if ($code === 200 && isset($json['dist']['trackingNumber'])) {
        $tracking_number = $json['dist']['trackingNumber'];
        $order_status = $json['dist']['orderStatus'];
        $order_date = $json['dist']['orderDate'];

        // Learn from successful city
        if (!$city_supported && get_option('postex_learning_enabled', 'yes') === 'yes') {
            postex_wc_learn_city_success($raw_city, $mapped_city);
        }

        $order->update_meta_data('_postex_tracking', $tracking_number);
        $order->update_meta_data('_postex_status', $order_status);
        $order->update_meta_data('_postex_payload', $payload);
        $order->update_meta_data('_postex_order_date', $order_date);
        $order->update_meta_data('_postex_city_learned', $raw_city);
        $order->save();

        $order->add_order_note('PostEx order created – Tracking: ' . $tracking_number . ' Status: ' . $order_status);

        // Increment next_ref_number
        update_option('woocommerce_shipping_postex_next_ref_number', $ref_number + 1);

        wp_send_json_success([
            'tracking_number' => $tracking_number,
            'order_status' => $order_status,
            'order_date' => $order_date
        ]);
    } else {
        // Learn from failed city (if city-related error)
        $msg = isset($json['statusMessage']) ? $json['statusMessage'] : (isset($json['message']) ? $json['message'] : $body);

        // Check if error is city-related
        if (!$city_supported &&
            get_option('postex_learning_enabled', 'yes') === 'yes' &&
            stripos($msg, 'city') !== false || stripos($msg, 'delivery') !== false) {
            postex_wc_learn_city_failure($raw_city);
        }

        wp_send_json_error(['message' => $msg]);
    }
}
