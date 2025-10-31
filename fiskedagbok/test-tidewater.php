<?php
/**
 * Test script for tidewater data generation
 * Run from WordPress: Add to URL and access
 * Example: yoursite.com/wp-content/plugins/fiskedagbok/test-tidewater.php
 */

// Load WordPress
require_once('../../../wp-load.php');

if (!current_user_can('manage_options')) {
    die('Access denied. Must be admin.');
}

echo '<h1>🌊 Tidewater Data Test</h1>';

// Include tidewater class
require_once('includes/class-tidewater-data.php');

// Test 1: Generate estimated data for today
echo '<h2>Test 1: Generate Estimated Tidewater Data</h2>';
$today = date('Y-m-d');
$data = Fiskedagbok_Tidewater_Data::generate_estimated_tidewater_data($today);

echo '<p><strong>Generated data points:</strong> ' . count($data) . '</p>';
if (!empty($data)) {
    echo '<table border="1" cellpadding="8" style="border-collapse: collapse; width: 100%; margin: 20px 0;">';
    echo '<tr><th>Hour</th><th>Water Level</th><th>Is Prediction</th><th>Timestamp</th></tr>';
    foreach (array_slice($data, 0, 5) as $point) {
        echo '<tr>';
        echo '<td>' . date('H', strtotime($point['timestamp'])) . ':00</td>';
        echo '<td>' . $point['water_level'] . 'm</td>';
        echo '<td>' . ($point['is_prediction'] ? 'Yes' : 'No') . '</td>';
        echo '<td>' . $point['timestamp'] . '</td>';
        echo '</tr>';
    }
    echo '</table>';
    echo '<p><em>... showing first 5 of ' . count($data) . ' total points</em></p>';
}

// Test 2: Save data for a test catch
echo '<h2>Test 2: Save Tidewater Data</h2>';

// Find a catch from the database
global $wpdb;
$test_catch = $wpdb->get_row("
    SELECT id, date FROM {$wpdb->prefix}fiskedagbok_catches 
    LIMIT 1
");

if ($test_catch) {
    echo '<p>Testing with catch ID: <strong>' . $test_catch->id . '</strong> (Date: ' . $test_catch->date . ')</p>';
    
    // Delete existing data for this catch
    $wpdb->query($wpdb->prepare("
        DELETE FROM {$wpdb->prefix}fiskedagbok_tidewater_data 
        WHERE catch_id = %d
    ", $test_catch->id));
    echo '<p>✓ Cleared old data for this catch</p>';
    
    // Generate new data
    $catch_date = date('Y-m-d', strtotime($test_catch->date));
    $new_data = Fiskedagbok_Tidewater_Data::generate_estimated_tidewater_data($catch_date);
    
    // Save it
    $inserted = Fiskedagbok_Tidewater_Data::save_catch_tidewater_data($test_catch->id, $new_data);
    echo '<p><strong>✓ Inserted ' . $inserted . ' data points</strong></p>';
    
    // Verify
    $verification = $wpdb->get_var($wpdb->prepare("
        SELECT COUNT(*) FROM {$wpdb->prefix}fiskedagbok_tidewater_data 
        WHERE catch_id = %d
    ", $test_catch->id));
    echo '<p>Database verification: <strong>' . $verification . ' records</strong> now exist for this catch</p>';
} else {
    echo '<p style="color: red;">No catches found in database!</p>';
}

// Test 3: Check database structure
echo '<h2>Test 3: Database Table Structure</h2>';

$table_name = $wpdb->prefix . 'fiskedagbok_tidewater_data';
$columns = $wpdb->get_results("DESCRIBE " . $table_name);

echo '<table border="1" cellpadding="8" style="border-collapse: collapse; width: 100%; margin: 20px 0;">';
echo '<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>';
foreach ($columns as $col) {
    echo '<tr>';
    echo '<td><strong>' . $col->Field . '</strong></td>';
    echo '<td>' . $col->Type . '</td>';
    echo '<td>' . $col->Null . '</td>';
    echo '<td>' . $col->Key . '</td>';
    echo '<td>' . ($col->Default !== null ? $col->Default : '-') . '</td>';
    echo '</tr>';
}
echo '</table>';

// Test 4: Check WP-Cron
echo '<h2>Test 4: WP-Cron Status</h2>';

$scheduled_events = _get_cron_array();
$tidewater_events = 0;
$next_run = null;

foreach ($scheduled_events as $timestamp => $crons) {
    foreach ($crons as $hook => $details) {
        if (strpos($hook, 'tidewater') !== false) {
            $tidewater_events++;
            if ($next_run === null || $timestamp < $next_run) {
                $next_run = $timestamp;
            }
        }
    }
}

echo '<p>Scheduled tidewater events: <strong>' . $tidewater_events . '</strong></p>';
if ($next_run) {
    $time_until = $next_run - time();
    echo '<p>Next event in: <strong>' . ($time_until > 0 ? $time_until . ' seconds' : 'NOW (overdue)') . '</strong></p>';
} else {
    echo '<p>No tidewater events scheduled yet.</p>';
}

// Test 5: Statistics
echo '<h2>Test 5: Tidewater Statistics</h2>';

$stats = array(
    'total_catches' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}fiskedagbok_catches"),
    'total_archive' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}fiskedagbok_csv_archive"),
    'catches_with_data' => $wpdb->get_var("SELECT COUNT(DISTINCT catch_id) FROM {$wpdb->prefix}fiskedagbok_tidewater_data"),
    'total_data_points' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}fiskedagbok_tidewater_data"),
);

echo '<p>Catches in database: <strong>' . $stats['total_catches'] . '</strong></p>';
echo '<p>Archive in database: <strong>' . $stats['total_archive'] . '</strong></p>';
echo '<p>Catches with tidewater data: <strong>' . $stats['catches_with_data'] . '</strong></p>';
echo '<p>Total tidewater data points: <strong>' . $stats['total_data_points'] . '</strong></p>';

if ($stats['total_data_points'] == 0) {
    echo '<div style="background: #fff3cd; padding: 10px; border: 1px solid #ffc107; margin: 20px 0;">';
    echo '<p style="margin: 0; color: #856404;"><strong>⚠️ No tidewater data found!</strong></p>';
    echo '<p style="margin: 5px 0 0 0;">The batch import may not be running. Check that WP-Cron is working or trigger it manually.</p>';
    echo '</div>';
}

echo '<hr>';
echo '<p><em>Test completed at ' . current_time('mysql') . '</em></p>';
?>
