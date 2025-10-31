<?php
/**
 * Quick test script to debug CSV fetching
 * Access via: https://domain.com/wp-content/plugins/orkla-water-level/test-csv-fetch.php
 */

// Load WordPress
define('WP_USE_THEMES', false);
require(dirname(dirname(dirname(__FILE__))) . '/wp-load.php');

echo '<h1>Orkla CSV Fetch Test</h1>';
echo '<pre>';

// Test 1: Check if plugin is loaded
if (function_exists('get_option')) {
    echo "✓ WordPress loaded\n";
} else {
    echo "✗ WordPress NOT loaded\n";
    die;
}

// Test 2: Check URL
$test_url = 'https://orklavannstand.online/VannforingOrkla.csv';
echo "\nTesting URL: $test_url\n";

// Test 3: Try to download
$response = wp_remote_get($test_url, array(
    'timeout' => 20,
    'redirection' => 3,
    'headers' => array(
        'Accept' => 'text/csv,text/plain,*/*;q=0.8',
    ),
));

if (is_wp_error($response)) {
    echo "✗ Download failed: " . $response->get_error_message() . "\n";
} else {
    $code = wp_remote_retrieve_response_code($response);
    echo "✓ HTTP Code: $code\n";
    
    $body = wp_remote_retrieve_body($response);
    echo "✓ Response size: " . strlen($body) . " bytes\n";
    
    if (strlen($body) > 0) {
        $lines = explode("\n", $body);
        echo "✓ Lines in response: " . count($lines) . "\n";
        echo "\nFirst 3 lines:\n";
        for ($i = 0; $i < min(3, count($lines)); $i++) {
            echo "  Line " . ($i + 1) . ": " . substr($lines[$i], 0, 100) . "\n";
        }
    }
}

// Test 4: Check if plugin class exists
if (class_exists('OrklaWaterLevel')) {
    echo "\n✓ OrklaWaterLevel class found\n";
} else {
    echo "\n✗ OrklaWaterLevel class NOT found\n";
}

echo '</pre>';
?>
