<?php
/**
 * Supabase Integration Test
 * Upload this file to your WordPress root and access via browser to test Supabase connection
 */

// Define WordPress path
$wp_load_path = __DIR__ . '/wp-load.php';
if (!file_exists($wp_load_path)) {
    $wp_load_path = dirname(__DIR__) . '/wp-load.php';
}

if (file_exists($wp_load_path)) {
    require_once($wp_load_path);
} else {
    die('Cannot find wp-load.php. Please ensure this file is in your WordPress root directory.');
}

// Load the Supabase client
require_once(dirname(__FILE__) . '/includes/class-supabase-client.php');

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Orkla Supabase Integration Test</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            max-width: 1200px;
            margin: 40px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .section {
            background: white;
            padding: 20px;
            margin: 20px 0;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            border-bottom: 3px solid #007cba;
            padding-bottom: 10px;
        }
        h2 {
            color: #666;
            margin-top: 0;
        }
        .success {
            color: #46b450;
            font-weight: bold;
        }
        .error {
            color: #dc3232;
            font-weight: bold;
        }
        .info {
            color: #007cba;
        }
        pre {
            background: #f0f0f0;
            padding: 15px;
            border-radius: 4px;
            overflow-x: auto;
            max-height: 400px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        th, td {
            padding: 8px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background-color: #f5f5f5;
            font-weight: 600;
        }
        .stat {
            display: inline-block;
            padding: 10px 20px;
            background: #e3f2fd;
            border-radius: 6px;
            margin: 5px;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <h1>🔬 Orkla Water Level - Supabase Integration Test</h1>

    <?php
    // Test 1: Check Supabase configuration
    echo '<div class="section">';
    echo '<h2>Test 1: Supabase Configuration</h2>';

    $supabase_url = getenv('VITE_SUPABASE_URL');
    $supabase_key = getenv('VITE_SUPABASE_SUPABASE_ANON_KEY');

    if (empty($supabase_url)) {
        echo '<p class="error">✗ VITE_SUPABASE_URL not configured</p>';
    } else {
        echo '<p class="success">✓ VITE_SUPABASE_URL configured: ' . esc_html(substr($supabase_url, 0, 30)) . '...</p>';
    }

    if (empty($supabase_key)) {
        echo '<p class="error">✗ VITE_SUPABASE_SUPABASE_ANON_KEY not configured</p>';
    } else {
        echo '<p class="success">✓ VITE_SUPABASE_SUPABASE_ANON_KEY configured (length: ' . strlen($supabase_key) . ')</p>';
    }
    echo '</div>';

    // Test 2: Initialize Supabase client
    echo '<div class="section">';
    echo '<h2>Test 2: Supabase Client Initialization</h2>';
    try {
        $supabase_client = new Orkla_Supabase_Client();
        echo '<p class="success">✓ Supabase client initialized successfully</p>';
    } catch (Exception $e) {
        echo '<p class="error">✗ Failed to initialize Supabase client: ' . esc_html($e->getMessage()) . '</p>';
        echo '</div></body></html>';
        exit;
    }
    echo '</div>';

    // Test 3: Query water data
    echo '<div class="section">';
    echo '<h2>Test 3: Query Water Data from Supabase</h2>';

    $periods = array('today', 'week');
    foreach ($periods as $period) {
        echo '<h3>Period: ' . esc_html($period) . '</h3>';

        $start_time = microtime(true);
        $data = $supabase_client->get_water_data_by_period($period);
        $end_time = microtime(true);
        $duration = round(($end_time - $start_time) * 1000, 2);

        if (isset($data['error'])) {
            echo '<p class="error">✗ Error querying data: ' . esc_html($data['error']) . '</p>';
        } else {
            $count = count($data);
            echo '<p class="success">✓ Successfully retrieved ' . $count . ' records in ' . $duration . ' ms</p>';

            if ($count > 0) {
                echo '<div class="stat">First: ' . esc_html($data[0]['timestamp']) . '</div>';
                echo '<div class="stat">Last: ' . esc_html($data[$count - 1]['timestamp']) . '</div>';

                echo '<h4>Sample Records (first 5):</h4>';
                echo '<table>';
                echo '<tr><th>Timestamp</th><th>Brattset</th><th>Syrstad</th><th>Temperature</th></tr>';

                for ($i = 0; $i < min(5, $count); $i++) {
                    $row = $data[$i];
                    echo '<tr>';
                    echo '<td>' . esc_html($row['timestamp']) . '</td>';
                    echo '<td>' . esc_html($row['vannforing_brattset']) . '</td>';
                    echo '<td>' . esc_html($row['vannforing_syrstad']) . '</td>';
                    echo '<td>' . esc_html($row['water_temperature']) . '</td>';
                    echo '</tr>';
                }

                echo '</table>';
            }
        }
        echo '<hr style="margin: 20px 0;">';
    }
    echo '</div>';

    // Test 4: Test AJAX endpoint
    echo '<div class="section">';
    echo '<h2>Test 4: WordPress AJAX Endpoint</h2>';
    echo '<p class="info">AJAX URL: ' . admin_url('admin-ajax.php') . '</p>';
    echo '<p class="info">Action: get_water_data</p>';
    echo '<p>Use browser console or tools like Postman to test:</p>';
    echo '<pre>POST ' . admin_url('admin-ajax.php') . '
Data: {
    action: "get_water_data",
    period: "today",
    nonce: "' . wp_create_nonce('orkla_nonce') . '"
}</pre>';
    echo '</div>';

    // Test 5: Shortcode availability
    echo '<div class="section">';
    echo '<h2>Test 5: WordPress Shortcodes</h2>';
    if (shortcode_exists('orkla_water_level')) {
        echo '<p class="success">✓ [orkla_water_level] shortcode registered</p>';
        echo '<p>Add this to any WordPress page or post to display the water level chart:</p>';
        echo '<pre>[orkla_water_level]</pre>';
    } else {
        echo '<p class="error">✗ [orkla_water_level] shortcode not registered. Plugin may not be activated.</p>';
    }
    echo '</div>';

    // Test 6: Plugin files check
    echo '<div class="section">';
    echo '<h2>Test 6: Plugin Files</h2>';
    $files = array(
        'includes/class-supabase-client.php',
        'assets/js/frontend.js',
        'assets/js/vendor/chart.min.js',
        'assets/js/vendor/chartjs-adapter-date-fns.bundle.min.js',
        'assets/css/frontend.css',
        'templates/water-level-widget.php'
    );

    foreach ($files as $file) {
        $file_path = dirname(__FILE__) . '/' . $file;
        if (file_exists($file_path)) {
            $size = filesize($file_path);
            echo '<p class="success">✓ ' . esc_html($file) . ' (' . number_format($size) . ' bytes)</p>';
        } else {
            echo '<p class="error">✗ Missing: ' . esc_html($file) . '</p>';
        }
    }
    echo '</div>';

    // Summary
    echo '<div class="section">';
    echo '<h2>Summary & Next Steps</h2>';

    if (empty($supabase_url) || empty($supabase_key)) {
        echo '<p class="error"><strong>Action Required:</strong> Configure Supabase credentials in your .env file or wp-config.php</p>';
    } elseif (isset($data['error'])) {
        echo '<p class="error"><strong>Issue Detected:</strong> Supabase connection configured but queries are failing. Check error messages above.</p>';
    } elseif (isset($count) && $count > 0) {
        echo '<p class="success"><strong>✓ Everything is working!</strong></p>';
        echo '<p>Your Orkla Water Level plugin is properly configured and connected to Supabase.</p>';
        echo '<ol>';
        echo '<li>Create a new WordPress page</li>';
        echo '<li>Add the shortcode: <code>[orkla_water_level]</code></li>';
        echo '<li>Publish and view the page to see your water level graphs</li>';
        echo '</ol>';
        echo '<p><strong>Remember to delete this test file after verification!</strong></p>';
    } else {
        echo '<p class="info"><strong>Configuration OK, but no data available.</strong> Import water level data to see graphs.</p>';
    }
    echo '</div>';
    ?>

</body>
</html>
