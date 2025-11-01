<?php
/**
 * Orkla Water Level Plugin - Fix and Test Script
 *
 * This script fixes common issues and tests CSV import and graph functionality
 *
 * Usage: Upload to plugin directory and access via browser
 * URL: /wp-content/plugins/orkla-water-level/fix-and-test.php
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
    <title>Orkla Water Level - Fix and Test</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; padding: 20px; background: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h1 { color: #1e40af; margin-top: 0; }
        h2 { color: #3b82f6; border-bottom: 2px solid #e5e7eb; padding-bottom: 10px; margin-top: 30px; }
        .step { padding: 15px; margin: 10px 0; background: #f9fafb; border-left: 4px solid #3b82f6; }
        .step.success { border-left-color: #10b981; background: #d1fae5; }
        .step.error { border-left-color: #ef4444; background: #fee2e2; }
        .step.warning { border-left-color: #f59e0b; background: #fef3c7; }
        .detail { color: #6b7280; font-size: 14px; margin-top: 5px; font-family: monospace; }
        .code { background: #1f2937; color: #f3f4f6; padding: 15px; border-radius: 4px; overflow-x: auto; font-family: monospace; font-size: 13px; margin: 10px 0; }
        .button { background: #3b82f6; color: white; padding: 12px 24px; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; text-decoration: none; display: inline-block; }
        .button:hover { background: #2563eb; }
        pre { background: #f3f4f6; padding: 10px; border-radius: 4px; overflow-x: auto; }
    </style>
</head>
<body>
<div class="container">
    <h1>🔧 Orkla Water Level - Fix and Test</h1>
    <p>This script will attempt to fix common issues and test the CSV import and graph display functionality.</p>

    <?php
    $fixes_applied = array();
    $tests_passed = array();
    $tests_failed = array();

    // Get plugin instance
    global $orkla_water_level_instance;
    if (!isset($orkla_water_level_instance) || !is_object($orkla_water_level_instance)) {
        // Try to initialize it
        if (class_exists('OrklaWaterLevel')) {
            $orkla_water_level_instance = new OrklaWaterLevel();
        }
    }

    // Fix 1: Ensure cache directory exists
    echo '<h2>Step 1: Fix Cache Directory</h2>';
    $uploads = wp_upload_dir();
    $cache_dir = trailingslashit($uploads['basedir']) . 'orkla-water-level';

    if (!is_dir($cache_dir)) {
        $created = wp_mkdir_p($cache_dir);
        if ($created) {
            echo '<div class="step success">✅ Created cache directory: ' . $cache_dir . '</div>';
            $fixes_applied[] = 'Created cache directory';
        } else {
            echo '<div class="step error">❌ Failed to create cache directory: ' . $cache_dir . '</div>';
            echo '<div class="detail">Try manually: mkdir -p ' . $cache_dir . ' && chmod 755 ' . $cache_dir . '</div>';
        }
    } else {
        echo '<div class="step success">✅ Cache directory exists: ' . $cache_dir . '</div>';

        // Check if writable
        if (!is_writable($cache_dir)) {
            $fixed = @chmod($cache_dir, 0755);
            if ($fixed) {
                echo '<div class="step success">✅ Fixed cache directory permissions</div>';
                $fixes_applied[] = 'Fixed cache directory permissions';
            } else {
                echo '<div class="step error">❌ Cache directory is not writable</div>';
                echo '<div class="detail">Try manually: chmod 755 ' . $cache_dir . '</div>';
            }
        }
    }

    // Fix 2: Reschedule cron job
    echo '<h2>Step 2: Fix Cron Schedule</h2>';
    wp_clear_scheduled_hook('orkla_fetch_data_hourly');
    $next_run = time() + 300; // 5 minutes from now
    $scheduled = wp_schedule_event($next_run, 'hourly', 'orkla_fetch_data_hourly');

    if ($scheduled !== false) {
        echo '<div class="step success">✅ Rescheduled cron job</div>';
        echo '<div class="detail">Next run: ' . date('Y-m-d H:i:s', $next_run) . '</div>';
        $fixes_applied[] = 'Rescheduled cron job';
    } else {
        echo '<div class="step error">❌ Failed to schedule cron job</div>';
    }

    // Verify cron schedule
    $cron_scheduled = wp_next_scheduled('orkla_fetch_data_hourly');
    if ($cron_scheduled) {
        echo '<div class="step success">✅ Cron job is scheduled</div>';
        echo '<div class="detail">Next run: ' . date('Y-m-d H:i:s', $cron_scheduled) . ' (' . round(($cron_scheduled - time()) / 60) . ' minutes)</div>';
        $tests_passed[] = 'Cron job is scheduled';
    } else {
        echo '<div class="step error">❌ Cron job is NOT scheduled</div>';
        $tests_failed[] = 'Cron job not scheduled';
    }

    // Fix 3: Verify database table
    echo '<h2>Step 3: Verify Database Table</h2>';
    global $wpdb;
    $table_name = $wpdb->prefix . 'orkla_water_data';
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;

    if ($table_exists) {
        echo '<div class="step success">✅ Database table exists: ' . $table_name . '</div>';
        $tests_passed[] = 'Database table exists';

        $record_count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
        echo '<div class="detail">Records in database: ' . number_format($record_count) . '</div>';

        if ($record_count > 0) {
            $latest = $wpdb->get_row("SELECT * FROM $table_name ORDER BY timestamp DESC LIMIT 1", ARRAY_A);
            echo '<div class="detail">Latest record: ' . $latest['timestamp'] . '</div>';
        }
    } else {
        echo '<div class="step error">❌ Database table does NOT exist</div>';
        echo '<div class="detail">Run plugin activation to create the table</div>';
        $tests_failed[] = 'Database table missing';
    }

    // Test 1: Test CSV URL accessibility
    echo '<h2>Step 4: Test CSV Data Source</h2>';
    $csv_url = 'https://orklavannstand.online/VannforingOrkla.csv';

    echo '<div class="step">Testing CSV URL: ' . $csv_url . '</div>';

    $response = wp_remote_get($csv_url, array('timeout' => 10));

    if (is_wp_error($response)) {
        echo '<div class="step error">❌ Cannot reach CSV URL</div>';
        echo '<div class="detail">Error: ' . $response->get_error_message() . '</div>';
        $tests_failed[] = 'CSV URL unreachable';
    } else {
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        if ($status_code === 200) {
            echo '<div class="step success">✅ CSV URL is accessible (HTTP 200)</div>';
            $lines = explode("\n", trim($body));
            $line_count = count($lines);
            echo '<div class="detail">CSV contains ' . number_format($line_count) . ' lines</div>';

            if ($line_count > 2) {
                echo '<div class="detail">First line: ' . esc_html(substr($lines[0], 0, 100)) . '</div>';
                echo '<div class="detail">Second line: ' . esc_html(substr($lines[1], 0, 100)) . '</div>';
            }

            $tests_passed[] = 'CSV URL accessible';
        } else {
            echo '<div class="step error">❌ CSV URL returned HTTP ' . $status_code . '</div>';
            $tests_failed[] = 'CSV URL error ' . $status_code;
        }
    }

    // Test 2: Manual CSV import
    echo '<h2>Step 5: Run Manual CSV Import</h2>';

    if (isset($orkla_water_level_instance) && method_exists($orkla_water_level_instance, 'fetch_csv_data')) {
        echo '<div class="step">Starting CSV import...</div>';

        $start_time = microtime(true);
        $summary = $orkla_water_level_instance->fetch_csv_data(true, false);
        $duration = round(microtime(true) - $start_time, 2);

        if (is_array($summary)) {
            $imported = isset($summary['imported']) ? $summary['imported'] : 0;
            $updated = isset($summary['updated']) ? $summary['updated'] : 0;
            $skipped = isset($summary['skipped']) ? $summary['skipped'] : 0;
            $errors = isset($summary['errors']) ? $summary['errors'] : array();
            $warnings = isset($summary['warnings']) ? $summary['warnings'] : array();

            if (count($errors) > 0) {
                echo '<div class="step error">❌ CSV import completed with errors</div>';
                foreach ($errors as $error) {
                    echo '<div class="detail">Error: ' . esc_html($error) . '</div>';
                }
                $tests_failed[] = 'CSV import had errors';
            } else {
                echo '<div class="step success">✅ CSV import completed successfully</div>';
                echo '<div class="detail">Imported: ' . $imported . ', Updated: ' . $updated . ', Skipped: ' . $skipped . '</div>';
                echo '<div class="detail">Duration: ' . $duration . ' seconds</div>';
                $tests_passed[] = 'CSV import successful';
            }

            if (count($warnings) > 0) {
                echo '<div class="step warning">⚠️ Import had warnings:</div>';
                foreach (array_slice($warnings, 0, 5) as $warning) {
                    echo '<div class="detail">' . esc_html($warning) . '</div>';
                }
                if (count($warnings) > 5) {
                    echo '<div class="detail">... and ' . (count($warnings) - 5) . ' more warnings</div>';
                }
            }

            // Show import summary
            if (isset($summary['sources']) && is_array($summary['sources'])) {
                echo '<h3>Import Sources:</h3>';
                foreach ($summary['sources'] as $field => $source) {
                    $status = isset($source['status']) ? $source['status'] : 'unknown';
                    $label = isset($source['label']) ? $source['label'] : $field;
                    $class = $status === 'ok' ? 'success' : 'error';

                    echo '<div class="step ' . $class . '">';
                    echo '<strong>' . esc_html($label) . ':</strong> ' . strtoupper($status);

                    if (isset($source['rows_imported'])) {
                        echo '<div class="detail">Rows imported: ' . $source['rows_imported'] . '</div>';
                    }

                    echo '</div>';
                }
            }
        } else {
            echo '<div class="step error">❌ CSV import returned invalid result</div>';
            echo '<div class="detail">Result type: ' . gettype($summary) . '</div>';
            $tests_failed[] = 'CSV import returned invalid result';
        }
    } else {
        echo '<div class="step error">❌ Cannot access plugin instance or fetch_csv_data method</div>';
        $tests_failed[] = 'Plugin instance not accessible';
    }

    // Test 3: Test AJAX endpoint
    echo '<h2>Step 6: Test AJAX Data Retrieval</h2>';

    if ($table_exists) {
        $record_count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");

        if ($record_count > 0) {
            // Simulate AJAX request
            $_POST['nonce'] = wp_create_nonce('orkla_nonce');
            $_POST['period'] = 'today';

            ob_start();
            if (isset($orkla_water_level_instance) && method_exists($orkla_water_level_instance, 'ajax_get_water_data')) {
                $orkla_water_level_instance->ajax_get_water_data();
            }
            $ajax_output = ob_get_clean();

            if (!empty($ajax_output)) {
                $ajax_data = json_decode($ajax_output, true);

                if (isset($ajax_data['success']) && $ajax_data['success'] === true) {
                    echo '<div class="step success">✅ AJAX endpoint is working</div>';

                    if (isset($ajax_data['data']) && is_array($ajax_data['data'])) {
                        $data_count = count($ajax_data['data']);
                        echo '<div class="detail">Returned ' . $data_count . ' data points</div>';

                        if ($data_count > 0) {
                            $first_point = $ajax_data['data'][0];
                            echo '<div class="detail">First data point timestamp: ' . (isset($first_point['timestamp']) ? $first_point['timestamp'] : 'N/A') . '</div>';
                        }
                    }

                    $tests_passed[] = 'AJAX endpoint working';
                } else {
                    echo '<div class="step error">❌ AJAX endpoint returned error</div>';
                    if (isset($ajax_data['data'])) {
                        echo '<div class="detail">Error: ' . esc_html($ajax_data['data']) . '</div>';
                    }
                    $tests_failed[] = 'AJAX endpoint error';
                }
            } else {
                echo '<div class="step error">❌ AJAX endpoint returned no output</div>';
                $tests_failed[] = 'AJAX endpoint no output';
            }
        } else {
            echo '<div class="step warning">⚠️ Database is empty, cannot test AJAX endpoint</div>';
            echo '<div class="detail">Run CSV import first</div>';
        }
    } else {
        echo '<div class="step error">❌ Cannot test AJAX endpoint - database table missing</div>';
    }

    // Test 4: Check Chart.js files
    echo '<h2>Step 7: Verify Frontend Assets</h2>';

    $chart_js_path = ORKLA_PLUGIN_PATH . 'assets/js/vendor/chart.min.js';
    $adapter_path = ORKLA_PLUGIN_PATH . 'assets/js/vendor/chartjs-adapter-date-fns.bundle.min.js';

    if (file_exists($chart_js_path)) {
        $size = filesize($chart_js_path);
        echo '<div class="step success">✅ Chart.js found (' . number_format($size) . ' bytes)</div>';
        $tests_passed[] = 'Chart.js file exists';
    } else {
        echo '<div class="step error">❌ Chart.js NOT found</div>';
        echo '<div class="detail">Path: ' . $chart_js_path . '</div>';
        $tests_failed[] = 'Chart.js missing';
    }

    if (file_exists($adapter_path)) {
        $size = filesize($adapter_path);
        echo '<div class="step success">✅ Chart.js date adapter found (' . number_format($size) . ' bytes)</div>';
        $tests_passed[] = 'Date adapter exists';
    } else {
        echo '<div class="step error">❌ Chart.js date adapter NOT found</div>';
        echo '<div class="detail">Path: ' . $adapter_path . '</div>';
        $tests_failed[] = 'Date adapter missing';
    }

    // Summary
    echo '<h2>Summary</h2>';

    echo '<div class="step success">';
    echo '<h3>✅ Fixes Applied (' . count($fixes_applied) . ')</h3>';
    if (count($fixes_applied) > 0) {
        echo '<ul>';
        foreach ($fixes_applied as $fix) {
            echo '<li>' . esc_html($fix) . '</li>';
        }
        echo '</ul>';
    } else {
        echo '<p>No fixes were needed</p>';
    }
    echo '</div>';

    echo '<div class="step success">';
    echo '<h3>✅ Tests Passed (' . count($tests_passed) . ')</h3>';
    if (count($tests_passed) > 0) {
        echo '<ul>';
        foreach ($tests_passed as $test) {
            echo '<li>' . esc_html($test) . '</li>';
        }
        echo '</ul>';
    }
    echo '</div>';

    if (count($tests_failed) > 0) {
        echo '<div class="step error">';
        echo '<h3>❌ Tests Failed (' . count($tests_failed) . ')</h3>';
        echo '<ul>';
        foreach ($tests_failed as $test) {
            echo '<li>' . esc_html($test) . '</li>';
        }
        echo '</ul>';
        echo '</div>';
    }

    // Recommendations
    echo '<h2>Next Steps</h2>';

    if (count($tests_failed) === 0) {
        echo '<div class="step success">';
        echo '<h3>🎉 All tests passed!</h3>';
        echo '<p>Your system is configured correctly and should be working properly.</p>';
        echo '<p><strong>What to do next:</strong></p>';
        echo '<ul>';
        echo '<li>Add the shortcode <code>[orkla_water_display]</code> to a WordPress page</li>';
        echo '<li>Visit that page to see the water level graph and meters</li>';
        echo '<li>The cron job will automatically import new data every hour</li>';
        echo '<li>Check the <a href="' . admin_url('admin.php?page=orkla-health-monitor') . '">Health Monitor</a> for ongoing status</li>';
        echo '</ul>';
        echo '</div>';
    } else {
        echo '<div class="step warning">';
        echo '<h3>⚠️ Some issues need attention</h3>';
        echo '<p><strong>Recommended actions:</strong></p>';
        echo '<ul>';

        if (in_array('Database table missing', $tests_failed)) {
            echo '<li><strong>Reactivate the plugin</strong> to create the database table</li>';
        }

        if (in_array('CSV URL unreachable', $tests_failed)) {
            echo '<li><strong>Check your server\'s internet connectivity</strong> - it may be blocked by firewall</li>';
            echo '<li><strong>Verify the CSV URL</strong> is still valid: <code>' . $csv_url . '</code></li>';
        }

        if (in_array('Chart.js missing', $tests_failed) || in_array('Date adapter missing', $tests_failed)) {
            echo '<li><strong>Re-upload the plugin files</strong> - some vendor libraries are missing</li>';
        }

        if (in_array('Cron job not scheduled', $tests_failed)) {
            echo '<li><strong>Reactivate the plugin</strong> to reschedule the cron job</li>';
        }

        echo '</ul>';
        echo '</div>';
    }

    // Actions
    echo '<h2>Quick Actions</h2>';
    echo '<div style="margin: 20px 0;">';
    echo '<a href="' . admin_url('admin.php?page=orkla-water-level') . '" class="button">Go to Admin Dashboard</a> ';
    echo '<a href="' . admin_url('plugins.php') . '" class="button">Manage Plugins</a> ';
    echo '<a href="' . admin_url('admin.php?page=orkla-health-monitor') . '" class="button">Health Monitor</a> ';
    echo '<button onclick="location.reload()" class="button">Re-run Tests</button>';
    echo '</div>';
    ?>

    <div style="margin-top: 40px; padding: 15px; background: #f3f4f6; border-radius: 4px; font-size: 13px; color: #6b7280;">
        <strong>Fix and Test Complete</strong><br>
        Generated: <?php echo date('Y-m-d H:i:s'); ?><br>
        WordPress Version: <?php echo get_bloginfo('version'); ?><br>
        PHP Version: <?php echo phpversion(); ?><br>
        <br>
        <em>Note: This script can be safely deleted after use.</em>
    </div>
</div>
</body>
</html>
