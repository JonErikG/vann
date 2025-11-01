<?php
/**
 * Orkla Water Level Plugin - Diagnostic Check
 *
 * This script checks the current state of the plugin to identify any issues
 * with CSV auto-import and graph display functionality.
 *
 * Usage: Upload to plugin directory and access via browser
 * URL: /wp-content/plugins/orkla-water-level/diagnostic-check.php
 */

// Load WordPress
$wp_load_path = '../../../wp-load.php';
if (file_exists($wp_load_path)) {
    require_once($wp_load_path);
} else {
    die('Error: Cannot find WordPress installation');
}

// Security check
if (!current_user_can('manage_options')) {
    die('Error: You do not have permission to view this page');
}

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Orkla Water Level - Diagnostic Check</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; padding: 20px; background: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h1 { color: #1e40af; margin-top: 0; }
        h2 { color: #3b82f6; border-bottom: 2px solid #e5e7eb; padding-bottom: 10px; margin-top: 30px; }
        .status { display: inline-block; padding: 4px 12px; border-radius: 4px; font-weight: 600; font-size: 14px; }
        .status.pass { background: #d1fae5; color: #065f46; }
        .status.fail { background: #fee2e2; color: #991b1b; }
        .status.warning { background: #fef3c7; color: #92400e; }
        .check-item { padding: 12px; margin: 8px 0; background: #f9fafb; border-left: 4px solid #e5e7eb; }
        .check-item.pass { border-left-color: #10b981; }
        .check-item.fail { border-left-color: #ef4444; }
        .check-item.warning { border-left-color: #f59e0b; }
        .detail { color: #6b7280; font-size: 14px; margin-top: 4px; }
        .code { background: #1f2937; color: #f3f4f6; padding: 15px; border-radius: 4px; overflow-x: auto; font-family: monospace; font-size: 13px; margin: 10px 0; }
        .action-btn { background: #3b82f6; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; font-size: 14px; margin: 5px; }
        .action-btn:hover { background: #2563eb; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #e5e7eb; }
        th { background: #f3f4f6; font-weight: 600; }
        .summary { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin: 20px 0; }
        .summary-card { background: #f9fafb; padding: 20px; border-radius: 8px; border: 1px solid #e5e7eb; }
        .summary-card h3 { margin: 0 0 10px 0; color: #374151; font-size: 14px; }
        .summary-card .value { font-size: 32px; font-weight: 700; color: #1f2937; }
    </style>
</head>
<body>
<div class="container">
    <h1>🔍 Orkla Water Level - Diagnostic Check</h1>
    <p>Running comprehensive diagnostics... This will check database tables, cron jobs, CSV data sources, and frontend configuration.</p>

    <?php
    global $wpdb;
    $table_name = $wpdb->prefix . 'orkla_water_data';
    $results = array();
    $pass_count = 0;
    $fail_count = 0;
    $warning_count = 0;

    // Check 1: Database table exists
    echo '<h2>1. Database Checks</h2>';

    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
    $status = $table_exists ? 'pass' : 'fail';
    if ($status === 'pass') $pass_count++; else $fail_count++;

    echo '<div class="check-item ' . $status . '">';
    echo '<span class="status ' . $status . '">' . strtoupper($status) . '</span> ';
    echo '<strong>Database Table Exists:</strong> ' . $table_name;
    if (!$table_exists) {
        echo '<div class="detail">❌ Table does not exist. Run plugin activation to create it.</div>';
    }
    echo '</div>';

    // Check 2: Record count
    if ($table_exists) {
        $record_count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
        $status = $record_count > 0 ? 'pass' : 'warning';
        if ($status === 'pass') $pass_count++; else $warning_count++;

        echo '<div class="check-item ' . $status . '">';
        echo '<span class="status ' . $status . '">' . strtoupper($status) . '</span> ';
        echo '<strong>Database Records:</strong> ' . number_format($record_count) . ' records';
        if ($record_count === 0) {
            echo '<div class="detail">⚠️ Database is empty. Run CSV import to populate data.</div>';
        }
        echo '</div>';

        // Check 3: Latest data timestamp
        $latest_timestamp = $wpdb->get_var("SELECT MAX(timestamp) FROM $table_name");
        if ($latest_timestamp) {
            $latest_time = strtotime($latest_timestamp);
            $hours_old = (time() - $latest_time) / 3600;
            $status = $hours_old < 3 ? 'pass' : ($hours_old < 24 ? 'warning' : 'fail');
            if ($status === 'pass') $pass_count++;
            else if ($status === 'warning') $warning_count++;
            else $fail_count++;

            echo '<div class="check-item ' . $status . '">';
            echo '<span class="status ' . $status . '">' . strtoupper($status) . '</span> ';
            echo '<strong>Latest Data:</strong> ' . $latest_timestamp . ' (' . round($hours_old, 1) . ' hours old)';
            if ($hours_old >= 24) {
                echo '<div class="detail">❌ Data is very old. CSV auto-import may not be working.</div>';
            } else if ($hours_old >= 3) {
                echo '<div class="detail">⚠️ Data is slightly old. Check cron schedule.</div>';
            }
            echo '</div>';

            // Data sample
            $sample_data = $wpdb->get_row("SELECT * FROM $table_name ORDER BY timestamp DESC LIMIT 1", ARRAY_A);
            if ($sample_data) {
                echo '<div class="summary">';
                echo '<div class="summary-card"><h3>Water Level 1</h3><div class="value">' . ($sample_data['water_level_1'] ?: 'NULL') . '</div></div>';
                echo '<div class="summary-card"><h3>Water Level 2</h3><div class="value">' . ($sample_data['water_level_2'] ?: 'NULL') . '</div></div>';
                echo '<div class="summary-card"><h3>Water Level 3</h3><div class="value">' . ($sample_data['water_level_3'] ?: 'NULL') . '</div></div>';
                echo '<div class="summary-card"><h3>Flow Rate 1</h3><div class="value">' . ($sample_data['flow_rate_1'] ?: 'NULL') . '</div></div>';
                echo '</div>';
            }
        } else {
            $fail_count++;
            echo '<div class="check-item fail">';
            echo '<span class="status fail">FAIL</span> ';
            echo '<strong>Latest Data:</strong> No data found';
            echo '</div>';
        }
    }

    // Check 4: Cron schedule
    echo '<h2>2. Cron Job Checks</h2>';

    $cron_scheduled = wp_next_scheduled('orkla_fetch_data_hourly');
    $status = $cron_scheduled ? 'pass' : 'fail';
    if ($status === 'pass') $pass_count++; else $fail_count++;

    echo '<div class="check-item ' . $status . '">';
    echo '<span class="status ' . $status . '">' . strtoupper($status) . '</span> ';
    echo '<strong>Cron Job Scheduled:</strong> orkla_fetch_data_hourly';
    if ($cron_scheduled) {
        $next_run = date('Y-m-d H:i:s', $cron_scheduled);
        $minutes_until = round(($cron_scheduled - time()) / 60);
        echo '<div class="detail">✓ Next run: ' . $next_run . ' (in ' . $minutes_until . ' minutes)</div>';
    } else {
        echo '<div class="detail">❌ Cron job not scheduled. Plugin may need reactivation.</div>';
    }
    echo '</div>';

    // Check 5: CSV source URL
    echo '<h2>3. CSV Data Source Checks</h2>';

    $csv_url = get_option('orkla_shared_remote_csv_url', 'https://orklavannstand.online/VannforingOrkla.csv');
    if (empty($csv_url)) {
        $csv_url = 'https://orklavannstand.online/VannforingOrkla.csv';
    }

    echo '<div class="check-item">';
    echo '<strong>CSV URL:</strong> ' . esc_html($csv_url);
    echo '</div>';

    // Test CSV accessibility
    $response = wp_remote_get($csv_url, array('timeout' => 10));
    if (is_wp_error($response)) {
        $fail_count++;
        echo '<div class="check-item fail">';
        echo '<span class="status fail">FAIL</span> ';
        echo '<strong>CSV Accessibility:</strong> Cannot reach CSV URL';
        echo '<div class="detail">❌ Error: ' . $response->get_error_message() . '</div>';
        echo '</div>';
    } else {
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $status = $status_code === 200 ? 'pass' : 'fail';
        if ($status === 'pass') $pass_count++; else $fail_count++;

        echo '<div class="check-item ' . $status . '">';
        echo '<span class="status ' . $status . '">' . strtoupper($status) . '</span> ';
        echo '<strong>CSV Accessibility:</strong> HTTP ' . $status_code;
        if ($status_code === 200) {
            $lines = explode("\n", trim($body));
            $line_count = count($lines);
            echo '<div class="detail">✓ CSV file accessible. Contains ' . number_format($line_count) . ' lines.</div>';

            if ($line_count > 0) {
                echo '<div class="detail"><strong>First 3 lines:</strong></div>';
                echo '<div class="code">';
                for ($i = 0; $i < min(3, $line_count); $i++) {
                    echo esc_html($lines[$i]) . "\n";
                }
                echo '</div>';
            }
        } else {
            echo '<div class="detail">❌ HTTP error ' . $status_code . '</div>';
        }
        echo '</div>';
    }

    // Check 6: CSV cache directory
    $uploads = wp_upload_dir();
    $cache_dir = trailingslashit($uploads['basedir']) . 'orkla-water-level';

    $cache_exists = is_dir($cache_dir);
    $cache_writable = is_writable($cache_dir);
    $status = ($cache_exists && $cache_writable) ? 'pass' : 'warning';
    if ($status === 'pass') $pass_count++; else $warning_count++;

    echo '<div class="check-item ' . $status . '">';
    echo '<span class="status ' . $status . '">' . strtoupper($status) . '</span> ';
    echo '<strong>CSV Cache Directory:</strong> ' . $cache_dir;
    if (!$cache_exists) {
        echo '<div class="detail">⚠️ Directory does not exist. Will be created on first import.</div>';
    } else if (!$cache_writable) {
        echo '<div class="detail">⚠️ Directory exists but is not writable. Check permissions.</div>';
    } else {
        $cache_file = $cache_dir . '/VannforingOrkla.csv';
        if (file_exists($cache_file)) {
            $cache_age = time() - filemtime($cache_file);
            $cache_minutes = round($cache_age / 60);
            echo '<div class="detail">✓ Directory writable. Cache file age: ' . $cache_minutes . ' minutes</div>';
        } else {
            echo '<div class="detail">✓ Directory writable. No cache file yet.</div>';
        }
    }
    echo '</div>';

    // Check 7: Frontend scripts
    echo '<h2>4. Frontend Configuration Checks</h2>';

    $chart_js_path = ORKLA_PLUGIN_PATH . 'assets/js/vendor/chart.min.js';
    $adapter_path = ORKLA_PLUGIN_PATH . 'assets/js/vendor/chartjs-adapter-date-fns.bundle.min.js';

    $chart_exists = file_exists($chart_js_path);
    $adapter_exists = file_exists($adapter_path);
    $status = ($chart_exists && $adapter_exists) ? 'pass' : 'fail';
    if ($status === 'pass') $pass_count++; else $fail_count++;

    echo '<div class="check-item ' . $status . '">';
    echo '<span class="status ' . $status . '">' . strtoupper($status) . '</span> ';
    echo '<strong>Chart.js Libraries:</strong> ';
    if ($chart_exists && $adapter_exists) {
        echo 'Both files present';
        echo '<div class="detail">✓ chart.min.js: ' . number_format(filesize($chart_js_path)) . ' bytes</div>';
        echo '<div class="detail">✓ chartjs-adapter-date-fns.bundle.min.js: ' . number_format(filesize($adapter_path)) . ' bytes</div>';
    } else {
        echo 'Missing files';
        if (!$chart_exists) echo '<div class="detail">❌ chart.min.js not found</div>';
        if (!$adapter_exists) echo '<div class="detail">❌ chartjs-adapter-date-fns.bundle.min.js not found</div>';
    }
    echo '</div>';

    // Check 8: Plugin activation
    $plugin_active = is_plugin_active('orkla-water-level/orkla-water-level.php');
    $status = $plugin_active ? 'pass' : 'fail';
    if ($status === 'pass') $pass_count++; else $fail_count++;

    echo '<div class="check-item ' . $status . '">';
    echo '<span class="status ' . $status . '">' . strtoupper($status) . '</span> ';
    echo '<strong>Plugin Status:</strong> ' . ($plugin_active ? 'Active' : 'Inactive');
    if (!$plugin_active) {
        echo '<div class="detail">❌ Plugin is not active. Activate it in WordPress admin.</div>';
    }
    echo '</div>';

    // Summary
    echo '<h2>Summary</h2>';
    echo '<div class="summary">';
    echo '<div class="summary-card"><h3>Passed</h3><div class="value" style="color: #10b981;">' . $pass_count . '</div></div>';
    echo '<div class="summary-card"><h3>Warnings</h3><div class="value" style="color: #f59e0b;">' . $warning_count . '</div></div>';
    echo '<div class="summary-card"><h3>Failed</h3><div class="value" style="color: #ef4444;">' . $fail_count . '</div></div>';
    echo '</div>';

    // Recommendations
    echo '<h2>Recommendations</h2>';

    if ($fail_count === 0 && $warning_count === 0) {
        echo '<div class="check-item pass">';
        echo '✅ <strong>All checks passed!</strong> The system appears to be configured correctly.';
        echo '</div>';
    } else {
        echo '<div class="check-item">';
        echo '<strong>Action Items:</strong>';
        echo '<ul>';

        if (!$table_exists) {
            echo '<li>Deactivate and reactivate the plugin to create database tables</li>';
        }
        if ($record_count === 0) {
            echo '<li>Run a manual CSV import from the admin dashboard</li>';
        }
        if (!$cron_scheduled) {
            echo '<li>Reactivate the plugin to reschedule the cron job</li>';
        }
        if ($hours_old > 24) {
            echo '<li>Check WordPress error logs for import failures</li>';
            echo '<li>Verify CSV URL is accessible from your server</li>';
        }

        echo '</ul>';
        echo '</div>';
    }

    // Actions
    echo '<h2>Quick Actions</h2>';
    echo '<div style="margin: 20px 0;">';
    echo '<a href="' . admin_url('admin.php?page=orkla-water-level') . '" class="action-btn">Go to Admin Dashboard</a>';
    echo '<a href="' . admin_url('admin.php?page=orkla-water-level&csv_fetch_now=1') . '" class="action-btn">Run Manual CSV Import</a>';
    echo '<a href="' . admin_url('admin.php?page=orkla-health-monitor') . '" class="action-btn">Health Monitor</a>';
    echo '<button onclick="location.reload()" class="action-btn">Refresh Diagnostics</button>';
    echo '</div>';
    ?>

    <div style="margin-top: 40px; padding: 15px; background: #f3f4f6; border-radius: 4px; font-size: 13px; color: #6b7280;">
        <strong>Diagnostic Check Complete</strong><br>
        Generated: <?php echo date('Y-m-d H:i:s'); ?><br>
        WordPress Version: <?php echo get_bloginfo('version'); ?><br>
        PHP Version: <?php echo phpversion(); ?>
    </div>
</div>
</body>
</html>
