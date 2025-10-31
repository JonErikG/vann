<?php
/**
 * Orkla Water Level Plugin - Health Check Script
 *
 * Upload this file to your WordPress root directory and access it via browser
 * to diagnose plugin issues.
 *
 * DELETE THIS FILE AFTER USE FOR SECURITY!
 */

require_once('wp-load.php');

header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Orkla Water Level Plugin - Health Check</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
            max-width: 1200px;
            margin: 40px auto;
            padding: 20px;
            background: #f0f0f1;
        }
        .header {
            background: #fff;
            padding: 30px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        h1 {
            margin: 0 0 10px 0;
            color: #1d2327;
        }
        .test-section {
            background: #fff;
            padding: 25px;
            margin-bottom: 20px;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .test-section h2 {
            margin-top: 0;
            color: #1d2327;
            border-bottom: 2px solid #2271b1;
            padding-bottom: 10px;
        }
        .status {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 4px;
            font-weight: 600;
            margin-left: 10px;
        }
        .status.pass {
            background: #00a32a;
            color: white;
        }
        .status.fail {
            background: #d63638;
            color: white;
        }
        .status.warning {
            background: #dba617;
            color: white;
        }
        .info-box {
            background: #f0f6fc;
            border-left: 4px solid #2271b1;
            padding: 15px;
            margin: 15px 0;
        }
        .error-box {
            background: #fcf0f1;
            border-left: 4px solid #d63638;
            padding: 15px;
            margin: 15px 0;
        }
        .warning-box {
            background: #fcf9e8;
            border-left: 4px solid #dba617;
            padding: 15px;
            margin: 15px 0;
        }
        .success-box {
            background: #edfaef;
            border-left: 4px solid #00a32a;
            padding: 15px;
            margin: 15px 0;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
        }
        table th {
            background: #f6f7f7;
            text-align: left;
            padding: 12px;
            border-bottom: 2px solid #c3c4c7;
        }
        table td {
            padding: 12px;
            border-bottom: 1px solid #e0e0e0;
        }
        code {
            background: #f6f7f7;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 13px;
        }
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background: #2271b1;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            margin-top: 15px;
        }
        .btn:hover {
            background: #135e96;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>🔍 Orkla Water Level Plugin - Health Check</h1>
        <p>This diagnostic tool checks all aspects of the plugin installation and configuration.</p>
        <p><strong>⚠️ DELETE THIS FILE AFTER USE FOR SECURITY!</strong></p>
    </div>

    <?php
    global $wpdb;
    $table_name = $wpdb->prefix . 'orkla_water_data';
    $all_tests_passed = true;
    ?>

    <!-- Test 1: Plugin Activation -->
    <div class="test-section">
        <h2>Test 1: Plugin Activation</h2>
        <?php
        $plugin_active = is_plugin_active('orkla-water-level/orkla-water-level.php');
        if ($plugin_active) {
            echo '<div class="success-box">✓ Plugin is active</div>';
        } else {
            echo '<div class="error-box">✗ Plugin is NOT active. Please activate it from the WordPress admin.</div>';
            $all_tests_passed = false;
        }
        ?>
    </div>

    <!-- Test 2: Database Table -->
    <div class="test-section">
        <h2>Test 2: Database Table</h2>
        <?php
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name;
        if ($table_exists) {
            echo '<div class="success-box">✓ Database table exists: <code>' . $table_name . '</code></div>';

            $record_count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
            echo '<div class="info-box">';
            echo '<strong>Total records:</strong> ' . number_format($record_count) . '<br>';

            if ($record_count > 0) {
                $latest = $wpdb->get_row("SELECT * FROM $table_name ORDER BY timestamp DESC LIMIT 1");
                $oldest = $wpdb->get_row("SELECT * FROM $table_name ORDER BY timestamp ASC LIMIT 1");

                echo '<strong>Latest record:</strong> ' . $latest->timestamp . '<br>';
                echo '<strong>Oldest record:</strong> ' . $oldest->timestamp . '<br>';
                echo '<strong>Date range:</strong> ' . $oldest->date_recorded . ' to ' . $latest->date_recorded;
            } else {
                echo '<strong>⚠️ No data in database!</strong> Run the CSV import from admin dashboard.';
                $all_tests_passed = false;
            }
            echo '</div>';
        } else {
            echo '<div class="error-box">✗ Database table does NOT exist. Try deactivating and reactivating the plugin.</div>';
            $all_tests_passed = false;
        }
        ?>
    </div>

    <!-- Test 3: File Permissions -->
    <div class="test-section">
        <h2>Test 3: Plugin Files</h2>
        <?php
        $plugin_path = WP_PLUGIN_DIR . '/orkla-water-level/';
        $files_to_check = array(
            'orkla-water-level.php' => 'Main plugin file',
            'assets/js/frontend.js' => 'Frontend JavaScript',
            'assets/js/admin.js' => 'Admin JavaScript',
            'assets/js/vendor/chart.min.js' => 'Chart.js library (local)',
            'assets/js/vendor/chartjs-adapter-date-fns.bundle.min.js' => 'Chart.js date adapter (local)',
            'assets/css/frontend.css' => 'Frontend CSS',
            'assets/css/admin.css' => 'Admin CSS',
        );

        echo '<table>';
        echo '<tr><th>File</th><th>Status</th><th>Size</th></tr>';

        foreach ($files_to_check as $file => $description) {
            $full_path = $plugin_path . $file;
            $exists = file_exists($full_path);
            $readable = $exists ? is_readable($full_path) : false;
            $size = $exists ? filesize($full_path) : 0;

            echo '<tr>';
            echo '<td>' . $description . '<br><code>' . $file . '</code></td>';

            if ($exists && $readable) {
                echo '<td><span class="status pass">✓ OK</span></td>';
                echo '<td>' . number_format($size) . ' bytes</td>';
            } else if ($exists) {
                echo '<td><span class="status fail">✗ Not Readable</span></td>';
                echo '<td>-</td>';
                $all_tests_passed = false;
            } else {
                echo '<td><span class="status fail">✗ Missing</span></td>';
                echo '<td>-</td>';
                $all_tests_passed = false;
            }
            echo '</tr>';
        }
        echo '</table>';
        ?>
    </div>

    <!-- Test 4: AJAX Endpoints -->
    <div class="test-section">
        <h2>Test 4: AJAX Endpoints</h2>
        <div class="info-box">
            <strong>AJAX URL:</strong> <code><?php echo admin_url('admin-ajax.php'); ?></code>
        </div>
        <p>Testing AJAX endpoint with actual request...</p>

        <button id="test-ajax-btn" class="btn">Test AJAX Connection</button>
        <div id="ajax-result" style="margin-top: 15px;"></div>
    </div>

    <!-- Test 5: Chart.js Loading -->
    <div class="test-section">
        <h2>Test 5: Chart.js Loading</h2>
        <div id="chartjs-status">Checking Chart.js...</div>
        <div id="chart-test-container" style="position: relative; height: 300px; margin-top: 20px;">
            <canvas id="test-chart"></canvas>
        </div>
    </div>

    <!-- Summary -->
    <div class="test-section">
        <h2>Summary</h2>
        <?php if ($all_tests_passed): ?>
            <div class="success-box">
                <h3 style="margin-top: 0;">✓ All Tests Passed!</h3>
                <p>The plugin appears to be correctly installed and configured.</p>
                <p>If you're still experiencing issues:</p>
                <ul>
                    <li>Check the browser console (F12) for JavaScript errors</li>
                    <li>Clear your browser cache (Ctrl+Shift+R)</li>
                    <li>Try the AJAX test button above</li>
                    <li>Check WordPress debug.log for PHP errors</li>
                </ul>
            </div>
        <?php else: ?>
            <div class="error-box">
                <h3 style="margin-top: 0;">✗ Some Tests Failed</h3>
                <p>Please review the failed tests above and fix the issues.</p>
            </div>
        <?php endif; ?>

        <a href="<?php echo admin_url('admin.php?page=orkla-water-level'); ?>" class="btn">Go to Plugin Dashboard</a>
    </div>

    <script src="<?php echo site_url('/wp-includes/js/jquery/jquery.min.js'); ?>"></script>
    <script src="<?php echo plugins_url('assets/js/vendor/chart.min.js', __FILE__); ?>"></script>
    <script src="<?php echo plugins_url('assets/js/vendor/chartjs-adapter-date-fns.bundle.min.js', __FILE__); ?>"></script>
    <script>
    jQuery(document).ready(function($) {
        // Test Chart.js Loading
        if (typeof Chart === 'undefined') {
            $('#chartjs-status').html('<div class="error-box">✗ Chart.js is NOT loaded! Check browser console for errors.</div>');
        } else {
            $('#chartjs-status').html('<div class="success-box">✓ Chart.js loaded successfully (version ' + Chart.version + ')</div>');

            // Create a simple test chart
            var ctx = document.getElementById('test-chart').getContext('2d');
            var testChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May'],
                    datasets: [{
                        label: 'Test Data',
                        data: [12, 19, 3, 5, 2],
                        borderColor: 'rgb(75, 192, 192)',
                        tension: 0.1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        title: {
                            display: true,
                            text: 'Chart.js Test Chart - If you see this, Chart.js is working!'
                        }
                    }
                }
            });
        }

        // Test AJAX
        $('#test-ajax-btn').on('click', function() {
            $('#ajax-result').html('<div class="info-box">Testing AJAX connection...</div>');

            $.ajax({
                url: '<?php echo admin_url('admin-ajax.php'); ?>',
                method: 'POST',
                data: {
                    action: 'get_water_data',
                    period: 'today',
                    nonce: '<?php echo wp_create_nonce('orkla_nonce'); ?>'
                },
                success: function(response) {
                    console.log('AJAX Test Response:', response);
                    if (response.success && response.data) {
                        $('#ajax-result').html('<div class="success-box"><strong>✓ AJAX works!</strong><br>Received ' + response.data.length + ' records<br>First timestamp: ' + (response.data[0] ? response.data[0].timestamp : 'N/A') + '</div>');
                    } else {
                        $('#ajax-result').html('<div class="warning-box"><strong>⚠ AJAX responded but with an error:</strong><br>' + (response.data || 'Unknown error') + '</div>');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX Test Failed:', xhr, status, error);
                    $('#ajax-result').html('<div class="error-box"><strong>✗ AJAX failed!</strong><br>Status: ' + xhr.status + '<br>Error: ' + error + '<br>Response: ' + xhr.responseText.substring(0, 200) + '</div>');
                }
            });
        });
    });
    </script>
</body>
</html>
