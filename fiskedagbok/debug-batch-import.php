<?php
/**
 * Debug script for batch import tidewater
 * Access via: /wp-content/plugins/fiskedagbok/debug-batch-import.php
 */

require_once('../../../wp-load.php');

if (!current_user_can('manage_options')) {
    die('Access denied');
}

global $wpdb;

echo '<h1>🔍 Batch Import Debug</h1>';

$catches_table = $wpdb->prefix . 'fiskedagbok_catches';
$archive_table = $wpdb->prefix . 'fiskedagbok_csv_archive';
$tidewater_table = $wpdb->prefix . 'fiskedagbok_tidewater_data';

// Test the queries that are used in batch import

echo '<h2>Test 1: Query for "Hent data for fangster som mangler"</h2>';

$query_missing = "
    SELECT * FROM (
        SELECT id, date FROM $catches_table WHERE id NOT IN (SELECT DISTINCT catch_id FROM $tidewater_table)
        UNION
        SELECT id, date FROM $archive_table WHERE id NOT IN (SELECT DISTINCT catch_id FROM $tidewater_table)
    ) combined
    ORDER BY date DESC
    LIMIT 50
";

echo '<p><strong>Query:</strong></p>';
echo '<pre style="background: #f5f5f5; padding: 10px; overflow-x: auto;">' . htmlspecialchars($query_missing) . '</pre>';

$missing_results = $wpdb->get_results($query_missing);
echo '<p><strong>Results found:</strong> ' . count($missing_results) . '</p>';

if (!empty($missing_results)) {
    echo '<table border="1" cellpadding="8" style="border-collapse: collapse;">';
    echo '<tr><th>ID</th><th>Date</th></tr>';
    foreach (array_slice($missing_results, 0, 10) as $row) {
        echo '<tr><td>' . $row->id . '</td><td>' . $row->date . '</td></tr>';
    }
    echo '</table>';
    if (count($missing_results) > 10) {
        echo '<p><em>... and ' . (count($missing_results) - 10) . ' more</em></p>';
    }
}

// Test generating estimated data for first result
if (!empty($missing_results)) {
    echo '<h2>Test 2: Generate Estimated Tidewater Data</h2>';
    
    $first_catch = $missing_results[0];
    echo '<p>Testing with catch ID: <strong>' . $first_catch->id . '</strong>, Date: <strong>' . $first_catch->date . '</strong></p>';
    
    require_once('includes/class-tidewater-data.php');
    
    $catch_date = date('Y-m-d', strtotime($first_catch->date));
    echo '<p>Formatted date: <strong>' . $catch_date . '</strong></p>';
    
    // Try to generate data using reflection (since it's private)
    $reflection = new ReflectionMethod('Fiskedagbok_Tidewater_Data', 'generate_estimated_tidewater_data');
    $reflection->setAccessible(true);
    
    $generated_data = $reflection->invoke(null, $catch_date);
    
    echo '<p><strong>Generated data points:</strong> ' . count($generated_data) . '</p>';
    
    if (!empty($generated_data)) {
        echo '<table border="1" cellpadding="8" style="border-collapse: collapse;">';
        echo '<tr><th>Hour</th><th>Water Level</th><th>Is Prediction</th></tr>';
        foreach (array_slice($generated_data, 0, 5) as $data) {
            $hour = date('H', strtotime($data['timestamp']));
            echo '<tr><td>' . $hour . ':00</td><td>' . $data['water_level'] . '</td><td>' . ($data['is_prediction'] ? '1' : '0') . '</td></tr>';
        }
        echo '</table>';
    } else {
        echo '<p style="color: red;"><strong>ERROR:</strong> No data generated!</p>';
    }
    
    // Test saving the data
    echo '<h2>Test 3: Save Tidewater Data</h2>';
    
    $wpdb->query("DELETE FROM $tidewater_table WHERE catch_id = " . $first_catch->id);
    echo '<p>Cleared old data for catch ' . $first_catch->id . '</p>';
    
    $inserted = Fiskedagbok_Tidewater_Data::save_catch_tidewater_data($first_catch->id, $generated_data);
    echo '<p><strong>Inserted:</strong> ' . $inserted . ' records</p>';
    
    // Verify
    $verify = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $tidewater_table WHERE catch_id = %d", $first_catch->id));
    echo '<p><strong>Database verification:</strong> ' . $verify . ' records now in database</p>';
}

// Test the remaining count query
echo '<h2>Test 4: Remaining Count Query</h2>';

$remaining_query = "
    SELECT 
        (SELECT COUNT(*) FROM $catches_table WHERE id NOT IN (SELECT DISTINCT catch_id FROM $tidewater_table))
        +
        (SELECT COUNT(*) FROM $archive_table WHERE id NOT IN (SELECT DISTINCT catch_id FROM $tidewater_table))
";

echo '<p><strong>Query:</strong></p>';
echo '<pre style="background: #f5f5f5; padding: 10px; overflow-x: auto;">' . htmlspecialchars($remaining_query) . '</pre>';

$remaining = $wpdb->get_var($remaining_query);
echo '<p><strong>Remaining catches without data:</strong> ' . $remaining . '</p>';

// Test refresh_all query
echo '<h2>Test 5: Query for "Oppfrisk ALLE fangster"</h2>';

$query_refresh_all = "
    SELECT * FROM (
        SELECT id, date FROM $catches_table
        UNION
        SELECT id, date FROM $archive_table
    ) combined
    WHERE combined.id NOT IN (
        SELECT DISTINCT catch_id 
        FROM $tidewater_table 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 MINUTE)
    )
    ORDER BY date DESC
    LIMIT 50
";

echo '<p><strong>Query:</strong></p>';
echo '<pre style="background: #f5f5f5; padding: 10px; overflow-x: auto;">' . htmlspecialchars($query_refresh_all) . '</pre>';

$refresh_all_results = $wpdb->get_results($query_refresh_all);
echo '<p><strong>Results found:</strong> ' . count($refresh_all_results) . '</p>';

// Test the remaining count for refresh_all
$remaining_refresh = $wpdb->get_var("
    SELECT 
        ((SELECT COUNT(*) FROM $catches_table) + (SELECT COUNT(*) FROM $archive_table))
        -
        (SELECT COUNT(DISTINCT catch_id) FROM $tidewater_table WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 MINUTE))
");
echo '<p><strong>Remaining for refresh_all:</strong> ' . $remaining_refresh . '</p>';

// Check AJAX handler
echo '<h2>Test 6: Check AJAX Handler Registration</h2>';

global $wp_filter;
if (isset($wp_filter['wp_ajax_batch_import_tidewater'])) {
    echo '<p style="color: green;"><strong>✓</strong> AJAX handler is registered</p>';
    echo '<p>Callbacks: ' . count($wp_filter['wp_ajax_batch_import_tidewater']) . '</p>';
} else {
    echo '<p style="color: red;"><strong>✗</strong> AJAX handler NOT registered!</p>';
}

echo '<hr>';
echo '<p><em>Debug completed at ' . current_time('mysql') . '</em></p>';
?>
