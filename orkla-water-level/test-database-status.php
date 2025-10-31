<?php
/**
 * Orkla Water Level - Database Status Test
 * Upload this file to your WordPress root and access it via browser
 * Example: https://yoursite.com/test-database-status.php
 */

// Load WordPress
require_once('wp-load.php');

// Check if user is admin
if (!current_user_can('manage_options')) {
    die('You must be an administrator to view this page.');
}

echo '<!DOCTYPE html><html><head><title>Orkla Database Test</title>';
echo '<style>
body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
.section { background: white; padding: 20px; margin: 20px 0; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
h1 { color: #333; }
h2 { color: #666; border-bottom: 2px solid #007cba; padding-bottom: 10px; }
.success { color: #46b450; font-weight: bold; }
.error { color: #dc3232; font-weight: bold; }
.info { color: #007cba; }
pre { background: #f0f0f0; padding: 10px; border-radius: 4px; overflow-x: auto; }
table { width: 100%; border-collapse: collapse; }
table td, table th { padding: 8px; border: 1px solid #ddd; text-align: left; }
table th { background: #f5f5f5; font-weight: bold; }
</style></head><body>';

echo '<h1>🔍 Orkla Water Level - Database Status Test</h1>';

global $wpdb;
$table_name = $wpdb->prefix . 'orkla_water_data';

// Test 1: Check if table exists
echo '<div class="section">';
echo '<h2>Test 1: Database Table</h2>';
$table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name;
if ($table_exists) {
    echo '<p class="success">✓ Table exists: ' . $table_name . '</p>';
} else {
    echo '<p class="error">✗ Table does not exist: ' . $table_name . '</p>';
    echo '<p>Please deactivate and reactivate the Orkla Water Level plugin.</p>';
    echo '</div></body></html>';
    exit;
}
echo '</div>';

// Test 2: Count records
echo '<div class="section">';
echo '<h2>Test 2: Record Count</h2>';
$total_rows = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
echo '<p><strong>Total records:</strong> ' . $total_rows . '</p>';
if ($total_rows == 0) {
    echo '<p class="error">⚠ Database is empty! No data to display.</p>';
    echo '<p>Go to <strong>WordPress Admin > Water Level > Dashboard</strong> and click "Fetch CSV Data Now"</p>';
} else {
    echo '<p class="success">✓ Database has data</p>';
}
echo '</div>';

// Test 3: Show latest records
if ($total_rows > 0) {
    echo '<div class="section">';
    echo '<h2>Test 3: Latest Records (Top 10)</h2>';
    $latest = $wpdb->get_results("SELECT * FROM $table_name ORDER BY timestamp DESC LIMIT 10", ARRAY_A);
    if ($latest) {
        echo '<table>';
        echo '<tr>';
        echo '<th>Timestamp</th>';
        echo '<th>Water Level 1</th>';
        echo '<th>Water Level 2</th>';
        echo '<th>Water Level 3</th>';
        echo '<th>Flow Rate 1</th>';
        echo '<th>Flow Rate 2</th>';
        echo '<th>Flow Rate 3</th>';
        echo '<th>Temp 1</th>';
        echo '</tr>';
        foreach ($latest as $row) {
            echo '<tr>';
            echo '<td>' . $row['timestamp'] . '</td>';
            echo '<td>' . ($row['water_level_1'] ?? 'NULL') . '</td>';
            echo '<td>' . ($row['water_level_2'] ?? 'NULL') . '</td>';
            echo '<td>' . ($row['water_level_3'] ?? 'NULL') . '</td>';
            echo '<td>' . ($row['flow_rate_1'] ?? 'NULL') . '</td>';
            echo '<td>' . ($row['flow_rate_2'] ?? 'NULL') . '</td>';
            echo '<td>' . ($row['flow_rate_3'] ?? 'NULL') . '</td>';
            echo '<td>' . ($row['temperature_1'] ?? 'NULL') . '</td>';
            echo '</tr>';
        }
        echo '</table>';
    }
    echo '</div>';

    // Test 4: Date range
    echo '<div class="section">';
    echo '<h2>Test 4: Data Range</h2>';
    $date_range = $wpdb->get_row("SELECT MIN(timestamp) as earliest, MAX(timestamp) as latest FROM $table_name");
    echo '<p><strong>Earliest:</strong> ' . $date_range->earliest . '</p>';
    echo '<p><strong>Latest:</strong> ' . $date_range->latest . '</p>';
    echo '</div>';
}

// Test 5: Check CSV sources
echo '<div class="section">';
echo '<h2>Test 5: CSV Source Configuration</h2>';
$csv_path = wp_upload_dir()['basedir'] . '/orkla-water-level';
echo '<p><strong>CSV directory:</strong> ' . $csv_path . '</p>';
if (is_dir($csv_path)) {
    echo '<p class="success">✓ CSV directory exists</p>';
    $files = scandir($csv_path);
    $csv_files = array_filter($files, function($file) {
        return pathinfo($file, PATHINFO_EXTENSION) === 'csv';
    });
    if (count($csv_files) > 0) {
        echo '<p class="success">✓ CSV files found:</p><ul>';
        foreach ($csv_files as $file) {
            $full_path = $csv_path . '/' . $file;
            $size = filesize($full_path);
            $modified = date('Y-m-d H:i:s', filemtime($full_path));
            echo '<li>' . $file . ' (' . number_format($size) . ' bytes, modified: ' . $modified . ')</li>';
        }
        echo '</ul>';
    } else {
        echo '<p class="error">⚠ No CSV files found in directory</p>';
    }
} else {
    echo '<p class="error">✗ CSV directory does not exist</p>';
}
echo '</div>';

// Test 6: AJAX endpoint test
echo '<div class="section">';
echo '<h2>Test 6: AJAX Endpoint Test</h2>';
echo '<p><button id="test-ajax" onclick="testAjax()" style="padding: 10px 20px; background: #007cba; color: white; border: none; border-radius: 4px; cursor: pointer;">Test AJAX Data Fetch</button></p>';
echo '<div id="ajax-result" style="margin-top: 10px;"></div>';
echo '</div>';

echo '<script>
function testAjax() {
    var resultDiv = document.getElementById("ajax-result");
    resultDiv.innerHTML = "<p class=\"info\">Loading...</p>";

    var xhr = new XMLHttpRequest();
    xhr.open("POST", "' . admin_url('admin-ajax.php') . '", true);
    xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
    xhr.onload = function() {
        if (xhr.status === 200) {
            var response = JSON.parse(xhr.responseText);
            if (response.success && response.data) {
                resultDiv.innerHTML = "<p class=\"success\">✓ AJAX request successful!</p>" +
                    "<p><strong>Records returned:</strong> " + response.data.length + "</p>" +
                    "<pre>" + JSON.stringify(response.data.slice(0, 3), null, 2) + "</pre>";
            } else {
                resultDiv.innerHTML = "<p class=\"error\">✗ AJAX request returned no data</p>" +
                    "<pre>" + JSON.stringify(response, null, 2) + "</pre>";
            }
        } else {
            resultDiv.innerHTML = "<p class=\"error\">✗ AJAX request failed (HTTP " + xhr.status + ")</p>" +
                "<pre>" + xhr.responseText + "</pre>";
        }
    };
    xhr.onerror = function() {
        resultDiv.innerHTML = "<p class=\"error\">✗ Network error</p>";
    };

    var nonce = "' . wp_create_nonce('orkla_nonce') . '";
    xhr.send("action=get_water_data&period=today&nonce=" + nonce);
}
</script>';

echo '<div class="section">';
echo '<h2>Summary & Next Steps</h2>';
if ($total_rows == 0) {
    echo '<p class="error"><strong>⚠ Action Required:</strong></p>';
    echo '<ol>';
    echo '<li>Go to WordPress Admin</li>';
    echo '<li>Navigate to <strong>Water Level > Dashboard</strong></li>';
    echo '<li>Click the <strong>"Fetch CSV Data Now"</strong> button</li>';
    echo '<li>Wait for the import to complete</li>';
    echo '<li>Refresh this page to verify data was imported</li>';
    echo '</ol>';
} else {
    echo '<p class="success"><strong>✓ Database looks good!</strong></p>';
    echo '<p>If graphs are still not showing on your page:</p>';
    echo '<ol>';
    echo '<li>Check browser console for JavaScript errors (F12)</li>';
    echo '<li>Verify the shortcode is correct: <code>[orkla_water_level]</code></li>';
    echo '<li>Check that Chart.js is loading properly</li>';
    echo '<li>Visit the Debug Status page in WordPress Admin</li>';
    echo '</ol>';
}
echo '</div>';

echo '<p style="text-align: center; color: #999; margin-top: 40px;">Delete this file after testing for security.</p>';
echo '</body></html>';
