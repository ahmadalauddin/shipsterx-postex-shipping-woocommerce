<?php
// Simple test script to identify plugin issues
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Mock WordPress functions for testing
function get_option($key, $default = '') {
    return $default;
}

function wp_json_encode($data) {
    return json_encode($data);
}

function wp_remote_post($url, $args) {
    return [
        'response' => ['code' => 200],
        'body' => '{"trackingNumber": "TEST123"}'
    ];
}

// Load the plugin
include 'postex-woocommerce.php';

// Test PostEx_Client
try {
    $client = new PostEx_Client();
    echo "✅ PostEx_Client class created successfully\n";
    
    $test_payload = [
        'orderRefNumber' => 1000,
        'codAmount' => 500.00,
        'weight' => 1.0,
        'dimensions' => ['length' => 10, 'width' => 10, 'height' => 10],
        'recipient' => [
            'name' => 'Test User',
            'phone' => '123456789',
            'address' => 'Test Address',
            'city' => 'Test City'
        ]
    ];
    
    $response = $client->create_order($test_payload);
    echo "✅ create_order() method works\n";
    
    echo "✅ All basic tests passed!\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "❌ Stack trace: " . $e->getTraceAsString() . "\n";
}