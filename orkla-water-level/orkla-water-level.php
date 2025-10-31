<?php
/**
 * Plugin Name: Orkla Water Level Monitor
 * Description: Fetches water level data from CSV every hour and displays interactive graphs with archive functionality
 * Version: 1.0.0
 * Author: Your Name
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define a reliable path constant for this plugin.
if (!defined('ORKLA_WATER_LEVEL_PATH')) {
    define('ORKLA_WATER_LEVEL_PATH', plugin_dir_path(__FILE__));
}

// Define plugin constants
if (!defined('ORKLA_PLUGIN_URL')) {
    define('ORKLA_PLUGIN_URL', plugin_dir_url(__FILE__));
}
if (!defined('ORKLA_PLUGIN_PATH')) {
    define('ORKLA_PLUGIN_PATH', plugin_dir_path(__FILE__));
}

class OrklaWaterLevel {
    private static $scripts_enqueued = false;
    const ADMIN_FLASH_KEY = 'orkla_dataset_flash';
    const DEBUG_MODE = true; // Enable debug mode
    protected $admin_messages = array(
        'errors'  => array(),
        'notices' => array(),
    );
    protected $source_warnings = array();
    protected $remote_cache_state = array();
    
    public function __construct() {
        // Register activation/deactivation hooks
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        
        // Initialize plugin
        add_action('plugins_loaded', array($this, 'init'));
    }
    
    public function init() {
        // Include required files
        require_once(ORKLA_WATER_LEVEL_PATH . 'includes/class-orkla-hydapi-client.php');

        // Add AJAX handlers - these must be registered early
        add_action('wp_ajax_get_water_data', array($this, 'ajax_get_water_data'));
        add_action('wp_ajax_nopriv_get_water_data', array($this, 'ajax_get_water_data'));
        add_action('wp_ajax_fetch_csv_data_now', array($this, 'ajax_fetch_csv_data_now'));
        
        // Enqueue scripts and styles globally for frontend
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        
    // Add shortcodes
    add_shortcode('orkla_water_level', array($this, 'water_level_shortcode'));
    add_shortcode('orkla_water_meter', array($this, 'water_meter_shortcode'));
        
    // Add admin menu
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // Schedule cron
    add_action('orkla_fetch_data_hourly', array($this, 'fetch_csv_data'));
        
        // Ensure we have some data to display
        add_action('wp_loaded', array($this, 'ensure_test_data'));
    }
    
    public function activate() {
        error_log('Orkla Plugin: Activating plugin');
        $this->create_tables();
        $this->schedule_cron();
    }
    
    public function deactivate() {
        wp_clear_scheduled_hook('orkla_fetch_data_hourly');
        error_log('Orkla Plugin: Cleared scheduled cron job');
    }
    
    public function create_tables() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'orkla_water_data';
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id int(11) NOT NULL AUTO_INCREMENT,
            timestamp datetime DEFAULT CURRENT_TIMESTAMP,
            date_recorded date NOT NULL,
            time_recorded time NOT NULL,
            water_level_1 decimal(10,2) DEFAULT NULL,
            water_level_2 decimal(10,2) DEFAULT NULL,
            water_level_3 decimal(10,2) DEFAULT NULL,
            flow_rate_1 decimal(10,2) DEFAULT NULL,
            flow_rate_2 decimal(10,2) DEFAULT NULL,
            flow_rate_3 decimal(10,2) DEFAULT NULL,
            temperature_1 decimal(10,2) DEFAULT NULL,
            temperature_2 decimal(10,2) DEFAULT NULL,
            created_at timestamp DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_timestamp (timestamp),
            KEY date_recorded (date_recorded),
            KEY timestamp (timestamp)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        error_log('Orkla Plugin: Database table created/updated');
    }
    
    public function create_test_data() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'orkla_water_data';
        
        // Check if we have any data
        $count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
        
        if ($count == 0) {
            error_log('Orkla Plugin: Creating test data');
            
            // Add test data for the last 48 hours
            $base_time = time() - (48 * 60 * 60); // 48 hours ago
            
            for ($i = 0; $i < 48; $i++) {
                $timestamp = $base_time + ($i * 60 * 60); // Every hour
                
                // Create realistic vannføring data
                $brattset = 15 + (sin($i * 0.3) * 5) + rand(-2, 2);
                $syrstad = 25 + (sin($i * 0.2) * 8) + rand(-3, 3);
                $storsteinsholen = 35 + (sin($i * 0.4) * 10) + rand(-4, 4);
                $prod_brattset = 5 + (sin($i * 0.1) * 2) + rand(-1, 1);
                $prod_grana = 8 + (sin($i * 0.15) * 3) + rand(-1, 1);
                $prod_svorkmo = 12 + (sin($i * 0.25) * 4) + rand(-2, 2);
                
                // Generate temperature profiles with small fluctuations
                $temperature_syrstad = max(0, 6 + (sin($i * 0.18) * 1.8) + rand(-10, 10) / 10);
                
                $wpdb->insert(
                    $table_name,
                    array(
                        'timestamp' => date('Y-m-d H:i:s', $timestamp),
                        'date_recorded' => date('Y-m-d', $timestamp),
                        'time_recorded' => date('H:i:s', $timestamp),
                        'water_level_1' => round($brattset, 2),
                        'water_level_2' => round($syrstad, 2),
                        'water_level_3' => round($storsteinsholen, 2),
                        'flow_rate_1' => round($prod_brattset, 2),
                        'flow_rate_2' => round($prod_grana, 2),
                        'flow_rate_3' => round($prod_svorkmo, 2),
                        'temperature_1' => round($temperature_syrstad, 2)
                    ),
                    array('%s', '%s', '%s', '%f', '%f', '%f', '%f', '%f', '%f', '%f')
                );
            }
            
            error_log('Orkla Plugin: Test data created - ' . $wpdb->rows_affected . ' rows');
        }
    }
    
    public function ensure_test_data() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'orkla_water_data';
        
        // Check if table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            $this->create_tables();
        }
    }

    protected function get_available_years() {
        static $years_cache = null;

        if ($years_cache !== null) {
            return $years_cache;
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'orkla_water_data';

        // Bail out quickly if the data table does not exist yet
        $escaped_table = $wpdb->esc_like($table_name);
        $table_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $escaped_table));

        if ($table_exists !== $table_name) {
            $years_cache = array();
            return $years_cache;
        }

        $results = $wpdb->get_col("SELECT DISTINCT YEAR(timestamp) FROM $table_name WHERE timestamp IS NOT NULL ORDER BY YEAR(timestamp) DESC");

        if (!is_array($results) || empty($results)) {
            $years_cache = array();
            return $years_cache;
        }

        $years_cache = array_values(array_filter(array_map('intval', $results))); // Ensure integers and remove empties

        return $years_cache;
    }
    
    public function schedule_cron() {
        // Clear any existing scheduled events
        wp_clear_scheduled_hook('orkla_fetch_data_hourly');
        
        // Schedule to run every hour starting now
        $current_time = time();
        $next_run = $current_time + 300; // Start in 5 minutes for testing
        
        // Schedule the event
        wp_schedule_event($next_run, 'hourly', 'orkla_fetch_data_hourly');
        
        error_log('Orkla Plugin: Scheduled cron job for ' . date('Y-m-d H:i:s', $next_run) . ' (every hour)');
        
        // Also add a more frequent check for testing
        wp_schedule_event($current_time + 60, 'hourly', 'orkla_test_cron');
        add_action('orkla_test_cron', array($this, 'test_cron_function'));
    }
    
    public function test_cron_function() {
        error_log('Orkla Plugin: Test cron function executed at ' . date('Y-m-d H:i:s'));
        $result = $this->fetch_csv_data();
        if (is_array($result) && isset($result['imported'])) {
            error_log(sprintf(
                'Orkla Plugin: CSV cron summary – inserted: %d, updated: %d, skipped: %d',
                (int) $result['imported'],
                (int) $result['updated'],
                (int) $result['skipped']
            ));
        }
    }
    
    public function enqueue_scripts() {
        wp_enqueue_script('chart-js', 'https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js', array(), '3.9.1', true);
        wp_enqueue_script('date-adapter', 'https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns@2.0.0/dist/chartjs-adapter-date-fns.bundle.min.js', array('chart-js'), '2.0.0', true);
        wp_enqueue_script('orkla-frontend', ORKLA_PLUGIN_URL . 'assets/js/frontend.js', array('jquery', 'chart-js'), '1.0.7', true);
        wp_enqueue_style('orkla-frontend', ORKLA_PLUGIN_URL . 'assets/css/frontend.css', array(), '1.0.7');
        wp_localize_script('orkla-frontend', 'orkla_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('orkla_nonce')
        ));
    }
    
    public function enqueue_admin_scripts($hook) {
        if (strpos($hook, 'orkla') === false) {
            return;
        }
        
        wp_enqueue_script('chart-js', 'https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js', array(), '3.9.1', true);
        wp_enqueue_script('date-adapter', 'https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns@2.0.0/dist/chartjs-adapter-date-fns.bundle.min.js', array('chart-js'), '2.0.0', true);
    wp_enqueue_script('orkla-admin', ORKLA_PLUGIN_URL . 'assets/js/admin.js', array('jquery', 'chart-js'), '1.0.7', true);
    wp_enqueue_style('orkla-admin', ORKLA_PLUGIN_URL . 'assets/css/admin.css', array(), '1.0.7');

        $available_years = $this->get_available_years();
        $default_period = 'today';

        wp_localize_script('orkla-admin', 'orkla_admin_ajax', array(
            'ajax_url'        => admin_url('admin-ajax.php'),
            'nonce'           => wp_create_nonce('orkla_nonce'),
            'availableYears'  => $available_years,
            'defaultPeriod'   => $default_period,
        ));
    }
    
    public function add_admin_menu() {
        $hook = add_menu_page(
            'Orkla Water Level',
            'Water Level',
            'manage_options',
            'orkla-water-level',
            array($this, 'admin_page'),
            'dashicons-chart-line',
            30
        );

        add_submenu_page(
            'orkla-water-level',
            __('Dashboard', 'orkla-water-level'),
            __('Dashboard', 'orkla-water-level'),
            'manage_options',
            'orkla-water-level',
            array($this, 'admin_page')
        );

        add_submenu_page(
            'orkla-water-level',
            __('Shortcodes', 'orkla-water-level'),
            __('Shortcodes', 'orkla-water-level'),
            'manage_options',
            'orkla-water-level-shortcodes',
            array($this, 'admin_shortcodes_page')
        );

        add_submenu_page(
            'orkla-water-level',
            __('Debug Status', 'orkla-water-level'),
            __('Debug Status', 'orkla-water-level'),
            'manage_options',
            'orkla-water-level-debug',
            array($this, 'admin_debug_page')
        );

        return $hook;
    }

    public function admin_shortcodes_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission to access this page.', 'orkla-water-level'));
        }

        $shortcodes = $this->get_shortcode_definitions();

        include(ORKLA_WATER_LEVEL_PATH . 'templates/admin-shortcodes.php');
    }

    public function admin_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission to access this page.', 'orkla-water-level'));
        }

        $this->consume_admin_flash_messages();

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['orkla_dataset_action'])) {
            $this->handle_dataset_post_request();
            return;
        }

        if (isset($_GET['reschedule']) && $_GET['reschedule'] === '1') {
            $this->schedule_cron();
            $this->add_admin_message('notices', __('Cron job rescheduled.', 'orkla-water-level'));
        }

        if (isset($_GET['csv_fetch_now']) && $_GET['csv_fetch_now'] === '1') {
            $full_import = isset($_GET['full_import']) && $_GET['full_import'] === '1';
            $summary = $this->fetch_csv_data(true, $full_import);

            if (is_array($summary)) {
                $message = sprintf(
                    __('CSV import completed. Inserted: %d, updated: %d, skipped: %d.', 'orkla-water-level'),
                    (int) $summary['imported'],
                    (int) $summary['updated'],
                    (int) $summary['skipped']
                );
                $this->add_admin_message('notices', $message);

                if (!empty($summary['warnings'])) {
                    foreach ((array) $summary['warnings'] as $warning) {
                        $this->add_admin_message('notices', $warning);
                    }
                }

                if (!empty($summary['errors'])) {
                    foreach ((array) $summary['errors'] as $error) {
                        $this->add_admin_message('errors', $error);
                    }
                }
            } else {
                $this->add_admin_message('errors', __('CSV import failed. See logs for details.', 'orkla-water-level'));
            }
        }

        if (isset($_GET['test_csv']) && $_GET['test_csv'] === '1') {
            $this->test_csv_fetch();
            return;
        }

        if (isset($_GET['test_single']) && $_GET['test_single'] === '1') {
            $this->test_single_line_parse();
            return;
        }

        if (isset($_GET['test_remote_download']) && $_GET['test_remote_download'] === '1') {
            $this->test_remote_download_ui();
            return;
        }

        if (isset($_GET['test_full_import']) && $_GET['test_full_import'] === '1') {
            $this->test_full_import_ui();
            return;
        }

        if (isset($_GET['test_import_detail']) && $_GET['test_import_detail'] === '1') {
            $this->test_import_detail_ui();
            return;
        }

        if (isset($_GET['test_data_fetch']) && $_GET['test_data_fetch'] === '1') {
            $this->test_data_fetch_ui();
            return;
        }

        $available_years = $this->get_available_years();
        $admin_messages = $this->admin_messages;
        $csv_sources = $this->describe_csv_sources();
        $csv_summary = $this->get_last_import_summary();
        $csv_base_path = $this->get_csv_base_path();

        include(ORKLA_WATER_LEVEL_PATH . 'templates/admin-page.php');
    }

    public function admin_debug_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission to access this page.', 'orkla-water-level'));
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'orkla_water_data';

        echo '<div class="wrap">';
        echo '<h1>Orkla Water Level Debug Status</h1>';

        echo '<div class="orkla-debug-section">';
        echo '<h2>Database Status</h2>';

        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name;
        echo '<p><strong>Table exists:</strong> ' . ($table_exists ? '✓ Yes' : '✗ No') . '</p>';

        if ($table_exists) {
            $total_rows = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
            echo '<p><strong>Total records:</strong> ' . esc_html($total_rows) . '</p>';

            $latest_record = $wpdb->get_row("SELECT * FROM $table_name ORDER BY timestamp DESC LIMIT 1");
            if ($latest_record) {
                echo '<p><strong>Latest timestamp:</strong> ' . esc_html($latest_record->timestamp) . '</p>';
                echo '<p><strong>Latest data sample:</strong></p>';
                echo '<pre>' . esc_html(print_r($latest_record, true)) . '</pre>';
            } else {
                echo '<p style="color: red;"><strong>⚠ No data in database!</strong></p>';
                echo '<p><strong>Action Required:</strong> Click the button below to import all historical data from the CSV file.</p>';
                echo '<p><a href="' . admin_url('admin.php?page=orkla-water-level&csv_fetch_now=1&full_import=1') . '" class="button button-primary" onclick="return confirm(\'Import all historical data from CSV?\')">Run Full Historical Import</a></p>';
                echo '<p style="font-size: 12px; color: #666;">Note: The regular "Run CSV Import" button only imports NEW data. Use "Full Historical Import" to populate the database initially.</p>';
            }

            $date_range = $wpdb->get_row("SELECT MIN(timestamp) as earliest, MAX(timestamp) as latest FROM $table_name");
            if ($date_range) {
                echo '<p><strong>Data range:</strong> ' . esc_html($date_range->earliest) . ' to ' . esc_html($date_range->latest) . '</p>';
            }
        } else {
            echo '<p style="color: red;"><strong>⚠ Database table does not exist!</strong></p>';
            echo '<p>Try deactivating and reactivating the plugin.</p>';
        }

        echo '</div>';

        echo '<div class="orkla-debug-section" style="margin-top: 30px;">';
        echo '<h2>WordPress Environment</h2>';
        echo '<p><strong>AJAX URL:</strong> ' . esc_html(admin_url('admin-ajax.php')) . '</p>';
        echo '<p><strong>Plugin URL:</strong> ' . esc_html(ORKLA_PLUGIN_URL) . '</p>';
        echo '<p><strong>Plugin Path:</strong> ' . esc_html(ORKLA_PLUGIN_PATH) . '</p>';
        echo '<p><strong>Debug Mode:</strong> ' . (self::DEBUG_MODE ? 'Enabled' : 'Disabled') . '</p>';
        echo '</div>';

        echo '<div class="orkla-debug-section" style="margin-top: 30px;">';
        echo '<h2>Registered Actions</h2>';
        echo '<p><strong>AJAX Handler (ajax):</strong> ' . (has_action('wp_ajax_get_water_data') ? '✓ Registered' : '✗ Not registered') . '</p>';
        echo '<p><strong>AJAX Handler (nopriv):</strong> ' . (has_action('wp_ajax_nopriv_get_water_data') ? '✓ Registered' : '✗ Not registered') . '</p>';
        echo '<p><strong>Cron scheduled:</strong> ' . (wp_next_scheduled('orkla_fetch_data_hourly') ? '✓ Yes (Next: ' . date('Y-m-d H:i:s', wp_next_scheduled('orkla_fetch_data_hourly')) . ')' : '✗ No') . '</p>';
        echo '</div>';

        echo '<div class="orkla-debug-section" style="margin-top: 30px;">';
        echo '<h2>Test AJAX Request</h2>';
        echo '<button id="test-ajax-button" class="button button-primary">Test AJAX Data Fetch</button>';
        echo '<div id="test-ajax-result" style="margin-top: 10px;"></div>';
        echo '</div>';

        echo '<script>
        jQuery(document).ready(function($) {
            $("#test-ajax-button").on("click", function() {
                $("#test-ajax-result").html("<p>Loading...</p>");
                $.ajax({
                    url: "' . admin_url('admin-ajax.php') . '",
                    method: "POST",
                    data: {
                        action: "get_water_data",
                        period: "today",
                        nonce: "' . wp_create_nonce('orkla_nonce') . '"
                    },
                    success: function(response) {
                        $("#test-ajax-result").html("<pre>" + JSON.stringify(response, null, 2) + "</pre>");
                    },
                    error: function(xhr, status, error) {
                        $("#test-ajax-result").html("<p style=\"color: red;\">Error: " + error + "</p><pre>" + xhr.responseText + "</pre>");
                    }
                });
            });
        });
        </script>';

        echo '</div>';
    }

    public function test_csv_fetch() {
        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('CSV Source Diagnostics', 'orkla-water-level') . '</h1>';

        $sources = $this->describe_csv_sources();

        if (empty($sources)) {
            echo '<p>' . esc_html__('No CSV sources configured.', 'orkla-water-level') . '</p>';
            echo '</div>';
            return;
        }

        foreach ($sources as $source) {
            echo '<h2>' . esc_html($source['source_label']) . '</h2>';

            if (!$source['configured']) {
                echo '<p>' . esc_html__('Source is not configured yet.', 'orkla-water-level') . '</p>';
                continue;
            }

            $file_label = $source['file'] ? $source['file'] : __('(not set)', 'orkla-water-level');
            echo '<p>' . esc_html(sprintf(__('Configured file: %s', 'orkla-water-level'), $file_label)) . '</p>';

            if (!empty($source['remote_url'])) {
                echo '<p>' . esc_html(sprintf(__('Remote URL: %s', 'orkla-water-level'), $source['remote_url'])) . '</p>';
            }

            if (!$source['exists']) {
                echo '<p style="color:red;">' . esc_html__('File not found or unreadable.', 'orkla-water-level') . '</p>';

                if (!empty($source['errors'])) {
                    echo '<p style="color:red;">' . esc_html(implode(' | ', (array) $source['errors'])) . '</p>';
                }

                if (!empty($source['warnings'])) {
                    echo '<p style="color:#d9822b;">' . esc_html(implode(' | ', (array) $source['warnings'])) . '</p>';
                }

                continue;
            }

            echo '<p>' . esc_html(sprintf(__('Absolute path: %s', 'orkla-water-level'), $source['path'])) . '</p>';

            if (!empty($source['filesize'])) {
                echo '<p>' . esc_html(sprintf(__('File size: %s', 'orkla-water-level'), size_format($source['filesize'], 2))) . '</p>';
            }

            if (!empty($source['modified'])) {
                echo '<p>' . esc_html(sprintf(__('Last modified: %s', 'orkla-water-level'), $this->format_timestamp_output($source['modified']))) . '</p>';
            }

            if (!empty($source['warnings'])) {
                echo '<p style="color:#d9822b;">' . esc_html(implode(' | ', (array) $source['warnings'])) . '</p>';
            }

            if (!empty($source['errors'])) {
                echo '<p style="color:red;">' . esc_html(implode(' | ', (array) $source['errors'])) . '</p>';
            }

            if (!empty($source['last_import'])) {
                $last = $source['last_import'];
                $details = array();

                if (isset($last['rows_imported'])) {
                    $details[] = sprintf(__('rows imported: %d', 'orkla-water-level'), (int) $last['rows_imported']);
                }
                if (isset($last['rows_skipped'])) {
                    $details[] = sprintf(__('rows skipped: %d', 'orkla-water-level'), (int) $last['rows_skipped']);
                }
                if (!empty($last['first_timestamp'])) {
                    $details[] = sprintf(__('first timestamp: %s', 'orkla-water-level'), $last['first_timestamp']);
                }
                if (!empty($last['last_timestamp'])) {
                    $details[] = sprintf(__('last timestamp: %s', 'orkla-water-level'), $last['last_timestamp']);
                }

                if (!empty($details)) {
                    echo '<p>' . esc_html__('Last import details:', 'orkla-water-level') . ' ' . esc_html(implode(' · ', $details)) . '</p>';
                }
            }

            $preview_lines = $this->get_csv_preview_lines($source['path'], 8);
            if (!empty($preview_lines)) {
                echo '<h4>' . esc_html__('Preview (first 8 lines):', 'orkla-water-level') . '</h4>';
                echo '<pre style="background:#f5f5f5;padding:12px;">';
                foreach ($preview_lines as $idx => $line) {
                    echo esc_html(sprintf('%02d: %s', $idx + 1, $line)) . "\n";
                }
                echo '</pre>';
            }
        }

        echo '</div>';
    }
    
    public function test_single_line_parse() {
        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Single Line Parse Test', 'orkla-water-level') . '</h1>';

        $sources = $this->get_csv_sources();
        if (!isset($sources['water_level_2'])) {
            echo '<p>' . esc_html__('The Syrstad water flow CSV source is not configured.', 'orkla-water-level') . '</p>';
            echo '</div>';
            return;
        }

        $config = $sources['water_level_2'];
        $path = $this->resolve_csv_path($config, false, 'water_level_2');
        if (!$path || !is_readable($path)) {
            $display_path = $path ? $path : __('(unknown path)', 'orkla-water-level');
            echo '<p>' . esc_html(sprintf(__('CSV file not available at %s.', 'orkla-water-level'), $display_path)) . '</p>';
            echo '</div>';
            return;
        }

        $handle = @fopen($path, 'r');
        if (!$handle) {
            echo '<p>' . esc_html__('Unable to open CSV file.', 'orkla-water-level') . '</p>';
            echo '</div>';
            return;
        }

        $row = null;
        while (($data = fgetcsv($handle, 0, ';')) !== false) {
            if (empty($data)) {
                continue;
            }

            $first = isset($data[0]) ? trim($data[0]) : '';
            if ($first === '' || strpos($first, '#') === 0 || stripos($first, 'Tidspunkt') === 0) {
                continue;
            }

            $row = $data;
            break;
        }
        fclose($handle);

        if ($row === null) {
            echo '<p>' . esc_html__('No data rows found in the CSV file.', 'orkla-water-level') . '</p>';
            echo '</div>';
            return;
        }

        echo '<h2>' . esc_html__('Raw CSV Columns', 'orkla-water-level') . '</h2>';
        echo '<pre style="background:#f5f5f5;padding:12px;">';
        foreach ($row as $index => $value) {
            echo esc_html(sprintf('[%d] %s', $index, $value)) . "\n";
        }
        echo '</pre>';

        $timestamp_raw = isset($row[0]) ? $row[0] : '';
        $value_column = isset($config['value_column']) ? (int) $config['value_column'] : 1;
        $value_raw = isset($row[$value_column]) ? $row[$value_column] : '';

        $timezone = $this->get_wp_timezone();
        $datetime = $this->create_datetime_from_csv($timestamp_raw, $timezone);
        $value = $this->parse_number($value_raw);

        echo '<h2>' . esc_html__('Parsed Result', 'orkla-water-level') . '</h2>';
        echo '<ul>';
        if ($datetime) {
            echo '<li>' . esc_html(sprintf(__('Timestamp (raw): %s', 'orkla-water-level'), $timestamp_raw)) . '</li>';
            echo '<li>' . esc_html(sprintf(__('Timestamp (localized): %s', 'orkla-water-level'), $datetime->format('Y-m-d H:i:s'))) . '</li>';
        } else {
            echo '<li>' . esc_html(sprintf(__('Failed to parse timestamp: %s', 'orkla-water-level'), $timestamp_raw)) . '</li>';
        }
        echo '<li>' . esc_html(sprintf(__('Numeric value: %s', 'orkla-water-level'), $value !== null ? $value : __('null', 'orkla-water-level'))) . '</li>';
        echo '</ul>';

        echo '<p>' . esc_html__('This line will be stored as water_level_2 (Vannføring Syrstad) when the importer runs.', 'orkla-water-level') . '</p>';
        echo '</div>';
    }

    public function test_remote_download_ui() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission to access this page.', 'orkla-water-level'));
        }

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Remote CSV Download Test', 'orkla-water-level') . '</h1>';

        $url = $this->get_shared_remote_csv_url();
        
        echo '<p><strong>Testing URL:</strong> ' . esc_html($url) . '</p>';
        
        if (empty($url)) {
            echo '<p style="color:red;"><strong>ERROR:</strong> No remote URL configured!</p>';
            echo '</div>';
            return;
        }

        echo '<h2>Step 1: Using wp_remote_get()</h2>';
        
        $response = wp_remote_get($url, array(
            'timeout'     => 20,
            'redirection' => 3,
            'headers'     => array(
                'Accept' => 'text/csv,text/plain,*/*;q=0.8',
            ),
        ));

        if (is_wp_error($response)) {
            echo '<p style="color:red;"><strong>FAILED:</strong> ' . esc_html($response->get_error_message()) . '</p>';
            echo '</div>';
            return;
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        echo '<p><strong>HTTP Response Code:</strong> ' . esc_html($code) . '</p>';

        if ($code !== 200) {
            echo '<p style="color:red;"><strong>ERROR:</strong> Expected 200, got ' . esc_html($code) . '</p>';
            echo '</div>';
            return;
        }

        $body = wp_remote_retrieve_body($response);
        echo '<p style="color:green;"><strong>SUCCESS:</strong> Downloaded ' . esc_html(number_format(strlen($body))) . ' bytes</p>';

        $lines = explode("\n", $body);
        echo '<p><strong>Lines in response:</strong> ' . esc_html(count($lines)) . '</p>';

        echo '<h2>First 5 Lines of Response:</h2>';
        echo '<pre style="background:#f5f5f5;padding:12px;overflow-x:auto;">';
        for ($i = 0; $i < min(5, count($lines)); $i++) {
            echo esc_html(trim(substr($lines[$i], 0, 150))) . "\n";
        }
        echo '</pre>';

        echo '<p><a href="' . esc_url(admin_url('admin.php?page=orkla-water-level')) . '" class="button button-primary">Back to Dashboard</a></p>';
        echo '</div>';
    }

    public function test_full_import_ui() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission to access this page.', 'orkla-water-level'));
        }

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Full CSV Import Test', 'orkla-water-level') . '</h1>';

        error_log('=== FULL IMPORT TEST STARTED ===');
        
        $summary = $this->fetch_csv_data(true);
        
        error_log('=== FULL IMPORT TEST ENDED ===');

        if (!is_array($summary)) {
            echo '<p style="color:red;"><strong>ERROR:</strong> fetch_csv_data did not return an array</p>';
            echo '</div>';
            return;
        }

        echo '<h2>Import Summary</h2>';
        echo '<ul>';
        echo '<li><strong>Imported:</strong> ' . esc_html((int)$summary['imported']) . '</li>';
        echo '<li><strong>Updated:</strong> ' . esc_html((int)$summary['updated']) . '</li>';
        echo '<li><strong>Skipped:</strong> ' . esc_html((int)$summary['skipped']) . '</li>';
        echo '</ul>';

        if (!empty($summary['errors'])) {
            echo '<h3 style="color:red;">Errors:</h3>';
            echo '<ul>';
            foreach ((array)$summary['errors'] as $error) {
                echo '<li style="color:red;">' . esc_html($error) . '</li>';
            }
            echo '</ul>';
        }

        if (!empty($summary['warnings'])) {
            echo '<h3 style="color:#d98222;">Warnings:</h3>';
            echo '<ul>';
            foreach ((array)$summary['warnings'] as $warning) {
                echo '<li style="color:#d98222;">' . esc_html($warning) . '</li>';
            }
            echo '</ul>';
        }

        if (!empty($summary['sources'])) {
            echo '<h3>Source Details:</h3>';
            foreach ((array)$summary['sources'] as $key => $source_info) {
                echo '<h4>' . esc_html($source_info['label'] ?? $key) . '</h4>';
                echo '<ul>';
                echo '<li><strong>Status:</strong> ' . esc_html($source_info['status'] ?? 'unknown') . '</li>';
                if (!empty($source_info['rows_imported'])) {
                    echo '<li><strong>Rows Imported:</strong> ' . esc_html($source_info['rows_imported']) . '</li>';
                }
                if (!empty($source_info['rows_skipped'])) {
                    echo '<li><strong>Rows Skipped:</strong> ' . esc_html($source_info['rows_skipped']) . '</li>';
                }
                if (!empty($source_info['first_timestamp'])) {
                    echo '<li><strong>First Timestamp:</strong> ' . esc_html($source_info['first_timestamp']) . '</li>';
                }
                if (!empty($source_info['last_timestamp'])) {
                    echo '<li><strong>Last Timestamp:</strong> ' . esc_html($source_info['last_timestamp']) . '</li>';
                }
                if (!empty($source_info['warnings'])) {
                    echo '<li><strong>Warnings:</strong> ' . esc_html(implode('; ', (array)$source_info['warnings'])) . '</li>';
                }
                echo '</ul>';
            }
        }

        echo '<p><strong>Check wp-content/debug.log for detailed logging.</strong></p>';
        echo '<p><a href="' . esc_url(admin_url('admin.php?page=orkla-water-level')) . '" class="button button-primary">Back to Dashboard</a></p>';
        echo '</div>';
    }
    
    public function water_level_shortcode($atts) {
        wp_enqueue_script('orkla-frontend');
        wp_enqueue_style('orkla-frontend');

        if (!self::$scripts_enqueued) {
            $available_years = $this->get_available_years();
            $default_period = !empty($available_years) ? 'year:' . $available_years[0] : 'month';

            wp_localize_script('orkla-frontend', 'orkla_ajax', array(
                'ajax_url'        => admin_url('admin-ajax.php'),
                'nonce'           => wp_create_nonce('orkla_nonce'),
                'availableYears'  => $available_years,
                'defaultPeriod'   => $default_period,
            ));
            self::$scripts_enqueued = true;
        }

        $atts = shortcode_atts(array(
            'period' => 'today',
            'height' => '400px',
            'show_controls' => 'true'
        ), $atts);

        $available_years = $this->get_available_years();
        
        ob_start();
        include(ORKLA_WATER_LEVEL_PATH . 'templates/water-level-widget.php');
        return ob_get_clean();
    }

    public function water_meter_shortcode($atts) {
        wp_enqueue_script('orkla-frontend');
        wp_enqueue_style('orkla-frontend');

        if (!self::$scripts_enqueued) {
            $available_years = $this->get_available_years();
            $default_period = !empty($available_years) ? 'year:' . $available_years[0] : 'month';

            wp_localize_script('orkla-frontend', 'orkla_ajax', array(
                'ajax_url'        => admin_url('admin-ajax.php'),
                'nonce'           => wp_create_nonce('orkla_nonce'),
                'availableYears'  => $available_years,
                'defaultPeriod'   => $default_period,
            ));
            self::$scripts_enqueued = true;
        }

        $atts = shortcode_atts(array(
            'stations' => 'water_level_1,water_level_2,water_level_3,flow_rate_1,flow_rate_2,flow_rate_3',
            'show_temperature' => 'true',
            'reference_max' => ''
        ), $atts, 'orkla_water_meter');

        $snapshot = $this->get_latest_measurement_snapshot();
        if (!$snapshot) {
            return '<div class="orkla-meter-wrapper orkla-meter-wrapper--empty"><p>' . esc_html__('Ingen vannstandsmålinger er tilgjengelige akkurat nå.', 'orkla-water-level') . '</p></div>';
        }

        $station_definitions = $this->get_station_definitions();
        $station_keys = array_filter(array_map('trim', explode(',', $atts['stations'])));
        if (empty($station_keys)) {
            $station_keys = array_keys($station_definitions);
        }

        $cards = array();
        $max_reference = 0.0;

        foreach ($station_keys as $key) {
            if (!isset($station_definitions[$key])) {
                continue;
            }

            $definition = $station_definitions[$key];
            $raw_value = isset($snapshot[$key]) ? $snapshot[$key] : null;
            $value = ($raw_value !== null && $raw_value !== '') ? floatval($raw_value) : null;

            if ($value !== null && is_finite($value)) {
                $max_reference = max($max_reference, $value);
            }

            $cards[] = array(
                'key'             => $key,
                'label'           => $definition['label'],
                'color'           => $definition['color'],
                'description'     => isset($definition['description']) ? $definition['description'] : '',
                'slug'            => $definition['slug'],
                'value'           => $value,
                'value_formatted' => $value !== null ? number_format_i18n($value, 1) : null,
            );
        }

        if (empty($cards)) {
            return '<div class="orkla-meter-wrapper orkla-meter-wrapper--empty"><p>' . esc_html__('Ingen målestasjoner er konfigurert for denne visningen.', 'orkla-water-level') . '</p></div>';
        }

        if (!empty($atts['reference_max']) && is_numeric($atts['reference_max'])) {
            $max_reference = max($max_reference, floatval($atts['reference_max']));
        }

        if ($max_reference <= 0) {
            $max_reference = 1;
        }

        foreach ($cards as &$card) {
            if ($card['value'] !== null) {
                $percent = ($card['value'] / $max_reference) * 100;
                $percent = max(0, min(100, $percent));
                $card['percent'] = round($percent, 1);
                $card['percent_label'] = round($percent);
                $card['percent_style'] = rtrim(rtrim(number_format($percent, 2, '.', ''), '0'), '.');
            } else {
                $card['percent'] = 0;
                $card['percent_label'] = 0;
                $card['percent_style'] = '0';
            }
        }
        unset($card);

        $timestamp = isset($snapshot['timestamp']) ? $snapshot['timestamp'] : null;
        $updated_at = null;
        $updated_relative = null;

        if ($timestamp) {
            $timestamp_unix = strtotime($timestamp);
            if ($timestamp_unix) {
                $updated_at = date_i18n('d.m.Y H:i', $timestamp_unix);
                $current_timestamp = current_time('timestamp');
                if ($current_timestamp && $timestamp_unix <= $current_timestamp) {
                    $updated_relative = human_time_diff($timestamp_unix, $current_timestamp);
                }
            }
        }

        $show_temperature = filter_var($atts['show_temperature'], FILTER_VALIDATE_BOOLEAN);
        $temperature_card = null;

        if ($show_temperature) {
            $temperature_raw = isset($snapshot['temperature_1']) ? $snapshot['temperature_1'] : null;
            $temperature_value = $temperature_raw !== null ? $this->normalize_temperature_value($temperature_raw) : null;

            $min_temp = apply_filters('orkla_water_meter_temperature_min', 0);
            $max_temp = apply_filters('orkla_water_meter_temperature_max', 20);

            if (!is_numeric($min_temp)) {
                $min_temp = 0;
            }
            if (!is_numeric($max_temp)) {
                $max_temp = 20;
            }

            $min_temp = floatval($min_temp);
            $max_temp = floatval($max_temp);

            if ($max_temp <= $min_temp) {
                $max_temp = $min_temp + 1;
            }

            $temperature_card = array(
                'label'           => __('Vanntemperatur Syrstad', 'orkla-water-level'),
                'icon'            => '🌡️',
                'unit'            => '°C',
                'value'           => $temperature_value,
                'value_formatted' => $temperature_value !== null ? number_format_i18n($temperature_value, 1) : null,
                'color'           => '#f97316',
                'min_value'       => $min_temp,
                'max_value'       => $max_temp,
                'percent'         => 0,
                'percent_style'   => '0',
                'percent_label'   => 0,
            );

            if ($temperature_value !== null) {
                $normalized = ($temperature_value - $min_temp) / ($max_temp - $min_temp);
                $normalized = max(0, min(1, $normalized));
                $percent = $normalized * 100;
                $temperature_card['percent'] = round($percent, 1);
                $temperature_card['percent_label'] = round($percent);
                $temperature_card['percent_style'] = rtrim(rtrim(number_format($percent, 2, '.', ''), '0'), '.');

                if ($temperature_value <= 4) {
                    $temperature_card['color'] = '#0ea5e9';
                } elseif ($temperature_value <= 8) {
                    $temperature_card['color'] = '#38bdf8';
                } elseif ($temperature_value <= 12) {
                    $temperature_card['color'] = '#f97316';
                } else {
                    $temperature_card['color'] = '#ef4444';
                }
            }
        }

        $context = array(
            'station_cards'    => $cards,
            'temperature_card' => $temperature_card,
            'show_temperature' => $show_temperature,
            'updated_at'       => $updated_at,
            'updated_relative' => $updated_relative,
            'timestamp'        => $timestamp,
            'max_reference'    => $max_reference,
            'snapshot'         => $snapshot,
        );

        $context = apply_filters('orkla_water_meter_context', $context, $atts);

        $station_cards = isset($context['station_cards']) ? $context['station_cards'] : $cards;
        $temperature_card = isset($context['temperature_card']) ? $context['temperature_card'] : $temperature_card;
        $show_temperature = isset($context['show_temperature']) ? $context['show_temperature'] : $show_temperature;
        $updated_at = isset($context['updated_at']) ? $context['updated_at'] : $updated_at;
        $updated_relative = isset($context['updated_relative']) ? $context['updated_relative'] : $updated_relative;
        $timestamp = isset($context['timestamp']) ? $context['timestamp'] : $timestamp;
        $max_reference = isset($context['max_reference']) ? $context['max_reference'] : $max_reference;
        $snapshot_data = isset($context['snapshot']) ? $context['snapshot'] : $snapshot;

        ob_start();
        include(ORKLA_WATER_LEVEL_PATH . 'templates/water-meter.php');
        return ob_get_clean();
    }
    
    public function ajax_get_water_data() {
        error_log('Orkla Plugin: AJAX get_water_data called');
        
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'orkla_nonce')) {
            error_log('Orkla Plugin: Nonce verification failed');
            wp_send_json_error('Security check failed');
            return;
        }
        
        $period = isset($_POST['period']) ? sanitize_text_field($_POST['period']) : 'today';
        error_log('Orkla Plugin: Getting data for period: ' . $period);
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'orkla_water_data';
        
        // Ensure table exists and has data
        $this->ensure_test_data();

        $total_rows = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
        error_log('Orkla Plugin: Total rows in database: ' . $total_rows);

        $where_clause = '';
        $group_by = '';

        $latest_timestamp = $wpdb->get_var("SELECT MAX(timestamp) FROM $table_name");
        error_log('Orkla Plugin: Latest timestamp in database: ' . ($latest_timestamp ? $latest_timestamp : 'none'));
        if ($latest_timestamp) {
            try {
                $latest_dt = new DateTime($latest_timestamp);
            } catch (Exception $e) {
                $latest_dt = null;
            }
        } else {
            $latest_dt = null;
        }
        
        if (preg_match('/^year:(\d{4})$/', $period, $matches)) {
            $year = (int) $matches[1];
            if ($year >= 1900 && $year <= 2100) {
                $start = sprintf('%04d-01-01 00:00:00', $year);
                $end = sprintf('%04d-12-31 23:59:59', $year);
                $where_clause = $wpdb->prepare('timestamp BETWEEN %s AND %s', $start, $end);
            } else {
                $where_clause = '1=0';
            }
        } else {
            switch ($period) {
                case 'today':
                    // Get the most recent date in our data
                    $latest_date = $wpdb->get_var("SELECT DATE(timestamp) FROM $table_name ORDER BY timestamp DESC LIMIT 1");
                    if ($latest_date) {
                        $where_clause = "DATE(timestamp) = '$latest_date'";
                    } else {
                        $where_clause = "DATE(timestamp) = CURDATE()";
                    }
                    $group_by = "";
                    break;
                case 'week':
                    if ($latest_dt) {
                        $threshold = clone $latest_dt;
                        $threshold->modify('-7 days');
                        $where_clause = $wpdb->prepare('timestamp >= %s', $threshold->format('Y-m-d H:i:s'));
                    } else {
                        $where_clause = "timestamp >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
                    }
                    $group_by = "";
                    break;
                case 'month':
                    if ($latest_dt) {
                        $threshold = clone $latest_dt;
                        $threshold->modify('-1 month');
                        $where_clause = $wpdb->prepare('timestamp >= %s', $threshold->format('Y-m-d H:i:s'));
                    } else {
                        $where_clause = "timestamp >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
                    }
                    $group_by = "";
                    break;
                case 'year':
                    if ($latest_dt) {
                        $threshold = clone $latest_dt;
                        $threshold->modify('-1 year');
                        $where_clause = $wpdb->prepare('timestamp >= %s', $threshold->format('Y-m-d H:i:s'));
                    } else {
                        $where_clause = "timestamp >= DATE_SUB(NOW(), INTERVAL 1 YEAR)";
                    }
                    $group_by = "";
                    break;
                default:
                    $where_clause = "1=1"; // Show all data by default
                    $group_by = "";
                    break;
            }
        }
        
     $query = "SELECT timestamp, 
                water_level_1 as vannforing_brattset,
                water_level_2 as vannforing_syrstad,
                water_level_3 as vannforing_storsteinsholen,
                flow_rate_1 as produksjon_brattset,
                flow_rate_2 as produksjon_grana,
                flow_rate_3 as produksjon_svorkmo,
                temperature_1 as vanntemperatur_syrstad,
                COALESCE(water_level_1, 0) + COALESCE(flow_rate_1, 0) as rennebu_oppstroms,
                COALESCE(water_level_3, 0) + COALESCE(flow_rate_3, 0) as nedstroms_svorkmo
            FROM $table_name 
            WHERE $where_clause 
            ORDER BY timestamp ASC";
        
        $results = $wpdb->get_results($query);
        
        if ($wpdb->last_error) {
            error_log('Orkla Plugin: Database error: ' . $wpdb->last_error);
            wp_send_json_error('Database error: ' . $wpdb->last_error);
            return;
        }
        
        if (empty($results)) {
            error_log('Orkla Plugin: No data found, getting recent data');
            // Get all data instead
         $query = "SELECT timestamp, 
                 water_level_1 as vannforing_brattset,
                 water_level_2 as vannforing_syrstad,
                 water_level_3 as vannforing_storsteinshølen,
                 flow_rate_1 as produksjon_brattset,
                 flow_rate_2 as produksjon_grana,
                 flow_rate_3 as produksjon_svorkmo,
                 temperature_1 as vanntemperatur_syrstad,
                 COALESCE(water_level_1, 0) + COALESCE(flow_rate_1, 0) as rennebu_oppstroms,
                 COALESCE(water_level_3, 0) + COALESCE(flow_rate_3, 0) as nedstroms_svorkmo
             FROM $table_name 
             ORDER BY timestamp ASC";
            $results = $wpdb->get_results($query);
        }
        
        error_log('Orkla Plugin: Returning ' . count($results) . ' records for period: ' . $period);
        if (!empty($results)) {
            error_log('Orkla Plugin: First timestamp: ' . $results[0]->timestamp);
            error_log('Orkla Plugin: Last timestamp: ' . end($results)->timestamp);
            error_log('Orkla Plugin: Sample record: ' . json_encode($results[0]));
        } else {
            error_log('Orkla Plugin: WARNING - No results to return!');
        }
        wp_send_json_success($results);
    }
    
    public function ajax_fetch_csv_data_now() {
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'orkla_nonce')) {
            wp_send_json_error(__('Security check failed.', 'orkla-water-level'));
            return;
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions.', 'orkla-water-level'));
            return;
        }

        try {
            $summary = $this->fetch_csv_data();

            if (!is_array($summary)) {
                wp_send_json_error(__('CSV import failed. See logs for details.', 'orkla-water-level'));
                return;
            }

            global $wpdb;
            $table_name = $wpdb->prefix . 'orkla_water_data';
            $count = (int) $wpdb->get_var("SELECT COUNT(*) FROM $table_name");

            $payload = array(
                'message'       => __('CSV data processed.', 'orkla-water-level'),
                'total_records' => $count,
                'summary'       => $summary,
            );

            if (!empty($summary['errors'])) {
                $payload['errors'] = $summary['errors'];
            }

            if (!empty($summary['warnings'])) {
                $payload['warnings'] = $summary['warnings'];
            }

            wp_send_json_success($payload);
        } catch (Exception $e) {
            wp_send_json_error(__('Error during fetch: ', 'orkla-water-level') . $e->getMessage());
        }
    }

    public function fetch_csv_data($force_refresh = true, $full_import = false) {
        error_log('Orkla Plugin: fetch_csv_data called (force_refresh=' . ($force_refresh ? 'true' : 'false') . ', full_import=' . ($full_import ? 'true' : 'false') . ')');

        $sources = $this->get_csv_sources();
        error_log('Orkla Plugin: CSV sources found: ' . count($sources));
        
        $timezone = $this->get_wp_timezone();
        $field_defaults = $this->get_data_field_defaults();

        $source_values = array();
        $source_summaries = array();
        $warnings = array();
        $errors = array();
        $this->source_warnings = array();

        foreach ($sources as $field => $config) {
            $resolved_path = $this->resolve_csv_path($config, $force_refresh, $field);

            if (is_wp_error($resolved_path)) {
                $message = $resolved_path->get_error_message();
                $warnings[] = $message;

                $source_summaries[$field] = array(
                    'label'   => isset($config['label']) ? $config['label'] : $field,
                    'status'  => 'error',
                    'file'    => isset($config['file']) ? $config['file'] : null,
                    'path'    => null,
                    'details' => array(),
                    'warnings'=> array($message),
                );
                continue;
            }

            $path = $resolved_path;
            if (!$path || !is_readable($path)) {
                $warnings[] = sprintf(
                    __('Source file missing or unreadable for %1$s (%2$s).', 'orkla-water-level'),
                    isset($config['label']) ? $config['label'] : $field,
                    isset($config['file']) ? $config['file'] : __('unknown path', 'orkla-water-level')
                );

                $source_summaries[$field] = array(
                    'label'   => isset($config['label']) ? $config['label'] : $field,
                    'status'  => 'missing',
                    'file'    => isset($config['file']) ? $config['file'] : null,
                    'path'    => $path,
                    'details' => array(),
                    'warnings'=> array(
                        sprintf(
                            __('Source file missing or unreadable for %1$s (%2$s).', 'orkla-water-level'),
                            isset($config['label']) ? $config['label'] : $field,
                            isset($config['file']) ? $config['file'] : __('unknown path', 'orkla-water-level')
                        )
                    ),
                );
                continue;
            }

            error_log('Orkla Plugin: Parsing CSV for field: ' . $field);
            $parsed = $this->parse_csv_source($path, $config, $timezone);
            error_log('Orkla Plugin: Parsed ' . count($parsed['values']) . ' values for field: ' . $field);
            $source_values[$field] = $parsed['values'];

            $source_summaries[$field] = array(
                'label'          => isset($config['label']) ? $config['label'] : $field,
                'status'         => 'ok',
                'file'           => isset($config['file']) ? $config['file'] : basename($path),
                'path'           => $path,
                'rows_imported'  => (int) $parsed['meta']['rows_imported'],
                'rows_skipped'   => (int) $parsed['meta']['rows_skipped'],
                'lines_parsed'   => (int) $parsed['meta']['lines_parsed'],
                'first_timestamp'=> $parsed['meta']['first_timestamp'] ? $this->format_timestamp_output($parsed['meta']['first_timestamp']) : null,
                'last_timestamp' => $parsed['meta']['last_timestamp'] ? $this->format_timestamp_output($parsed['meta']['last_timestamp']) : null,
                'warnings'       => !empty($parsed['meta']['warnings']) ? array_values(array_unique((array) $parsed['meta']['warnings'])) : array(),
            );

            if (!empty($parsed['meta']['error'])) {
                $errors[] = sprintf(
                    __('%1$s: %2$s', 'orkla-water-level'),
                    isset($config['label']) ? $config['label'] : $field,
                    $parsed['meta']['error']
                );
            }

            if (!empty($parsed['meta']['warnings'])) {
                $warnings = array_merge($warnings, (array) $parsed['meta']['warnings']);
            }

            if (!empty($this->source_warnings[$field])) {
                $source_summaries[$field]['warnings'] = array_values(array_unique(array_merge(
                    $source_summaries[$field]['warnings'],
                    (array) $this->source_warnings[$field]
                )));
                $warnings = array_merge($warnings, (array) $this->source_warnings[$field]);
            }
        }

        if (!empty($warnings)) {
            $warnings = array_values(array_unique($warnings));
        }

        if (empty($source_values)) {
            $summary = array(
                'imported'     => 0,
                'updated'      => 0,
                'skipped'      => 0,
                'errors'       => $errors ?: array(__('No CSV sources available for import.', 'orkla-water-level')),
                'warnings'     => $warnings,
                'sources'      => $source_summaries,
                'record_count' => 0,
            );
            $this->store_import_summary($summary);
            return $summary;
        }

        $cutoff = $full_import ? null : $this->determine_import_cutoff_timestamp();
        error_log('Orkla Plugin: Import cutoff timestamp: ' . ($cutoff ? date('Y-m-d H:i:s', $cutoff) : 'none (full import)'));
        $records = $this->combine_source_records($source_values, $cutoff, $field_defaults, $timezone);
        error_log('Orkla Plugin: Combined ' . count($records) . ' records for import');

        if (empty($records)) {
            $summary = array(
                'imported'     => 0,
                'updated'      => 0,
                'skipped'      => 0,
                'errors'       => $errors,
                'warnings'     => $warnings,
                'sources'      => $source_summaries,
                'cutoff'       => $cutoff ? $this->format_timestamp_output($cutoff) : null,
                'record_count' => 0,
            );
            $this->store_import_summary($summary);
            return $summary;
        }

        error_log('Orkla Plugin: Starting database import of ' . count($records) . ' records');
        $import_result = $this->import_combined_records($records);
        error_log('Orkla Plugin: Import completed - Imported: ' . $import_result['imported'] . ', Updated: ' . $import_result['updated'] . ', Skipped: ' . $import_result['skipped']);

        $summary = array(
            'imported'     => $import_result['imported'],
            'updated'      => $import_result['updated'],
            'skipped'      => $import_result['skipped'],
            'errors'       => array_merge($errors, $import_result['errors']),
            'warnings'     => $warnings,
            'sources'      => $source_summaries,
            'record_count' => count($records),
            'first_record' => $import_result['first_record'],
            'last_record'  => $import_result['last_record'],
            'cutoff'       => $cutoff ? $this->format_timestamp_output($cutoff) : null,
        );

        $this->store_import_summary($summary);

        return $summary;
    }

    private function determine_import_cutoff_timestamp() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'orkla_water_data';
        $latest = $wpdb->get_var("SELECT MAX(timestamp) FROM $table_name");

        if (empty($latest)) {
            // No existing data, import everything
            return null;
        }

        try {
            $timezone = $this->get_wp_timezone();
            $date = new DateTime($latest, $timezone);
            // Only import data that is strictly newer than what's already in the database
            // This ensures all future data (including t10, t11, etc) gets imported
            return $date->getTimestamp();
        } catch (Exception $e) {
            return null;
        }
    }

    private function combine_source_records(array $source_values, $cutoff, array $field_defaults, DateTimeZone $timezone) {
        $combined = array();

        foreach ($source_values as $field => $series) {
            foreach ($series as $unix => $value) {
                // Only skip data older than cutoff (not equal to)
                // This allows updating existing records with new values from CSV
                if ($cutoff !== null && $unix < $cutoff) {
                    continue;
                }

                if (!isset($combined[$unix])) {
                    $combined[$unix] = $field_defaults;
                }

                $combined[$unix][$field] = $value;
            }
        }

        if (empty($combined)) {
            return array();
        }

        ksort($combined);

        $records = array();
        foreach ($combined as $unix => $values) {
            $date = new DateTime('@' . $unix);
            $date->setTimezone($timezone);

            $record = array(
                'timestamp'     => $date->format('Y-m-d H:i:s'),
                'date_recorded' => $date->format('Y-m-d'),
                'time_recorded' => $date->format('H:i:s'),
            );

            foreach ($values as $field => $value) {
                $record[$field] = $value;
            }

            $records[] = $record;
        }

        // Debug: log combined records for T10 and T11
        foreach ($records as $record) {
            if (strpos($record['timestamp'], '10:') !== false || strpos($record['timestamp'], '11:') !== false) {
                error_log('DEBUG combine_source_records: ' . $record['timestamp'] . ' => water_level_2=' . ($record['water_level_2'] ?? 'NULL'));
            }
        }

        return $records;
    }

    private function import_combined_records(array $records) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'orkla_water_data';
        $measurement_fields = array_keys($this->get_data_field_defaults());
    $formats = array('%s', '%s', '%s', '%f', '%f', '%f', '%f', '%f', '%f', '%f');

        $inserted = 0;
        $updated = 0;
        $skipped = 0;
        $errors  = array();

        $timezone = $this->get_wp_timezone();
        $first_timestamp = null;
        $last_timestamp = null;

        foreach ($records as $record) {
            $has_measurement = false;
            foreach ($measurement_fields as $field) {
                if ($record[$field] !== null) {
                    $has_measurement = true;
                    break;
                }
            }

            if (!$has_measurement) {
                $skipped++;
                continue;
            }

            $result = $wpdb->replace($table_name, $record, $formats);

            if ($result === false) {
                $errors[] = $wpdb->last_error ? $wpdb->last_error : __('Database error during replace.', 'orkla-water-level');
                continue;
            }

            if ($result === 1) {
                $inserted++;
            } elseif ($result === 2) {
                $updated++;
            }

            try {
                $date = new DateTime($record['timestamp'], $timezone);
                $timestamp = $date->getTimestamp();
            } catch (Exception $e) {
                $timestamp = strtotime($record['timestamp']);
            }

            if ($timestamp) {
                if ($first_timestamp === null || $timestamp < $first_timestamp) {
                    $first_timestamp = $timestamp;
                }
                if ($last_timestamp === null || $timestamp > $last_timestamp) {
                    $last_timestamp = $timestamp;
                }
            }
        }

        return array(
            'imported'     => $inserted,
            'updated'      => $updated,
            'skipped'      => $skipped,
            'errors'       => $errors,
            'first_record' => $first_timestamp ? $this->format_timestamp_output($first_timestamp) : null,
            'last_record'  => $last_timestamp ? $this->format_timestamp_output($last_timestamp) : null,
        );
    }

    private function get_data_field_defaults() {
        return array(
            'water_level_1' => null,
            'water_level_2' => null,
            'water_level_3' => null,
            'flow_rate_1'   => null,
            'flow_rate_2'   => null,
            'flow_rate_3'   => null,
            'temperature_1' => null,
        );
    }

    private function get_field_labels() {
        return array(
            'water_level_1' => __('Vannføring Oppstrøms Brattset', 'orkla-water-level'),
            'water_level_2' => __('Vannføring Syrstad', 'orkla-water-level'),
            'water_level_3' => __('Vannføring Storsteinshølen', 'orkla-water-level'),
            'flow_rate_1'   => __('Produksjonsvannføring Brattset', 'orkla-water-level'),
            'flow_rate_2'   => __('Produksjonsvannføring Grana', 'orkla-water-level'),
            'flow_rate_3'   => __('Produksjon Svorkmo', 'orkla-water-level'),
            'temperature_1' => __('Vanntemperatur Syrstad', 'orkla-water-level'),
        );
    }

    private function get_station_definitions() {
        $labels = $this->get_field_labels();

        return array(
            'water_level_1' => array(
                'label'       => isset($labels['water_level_1']) ? $labels['water_level_1'] : __('Vannføring Oppstrøms Brattset', 'orkla-water-level'),
                'slug'        => 'oppstroms-brattset',
                'color'       => '#2563eb',
                'description' => __('Måler ved oppstrøms Brattset.', 'orkla-water-level'),
            ),
            'water_level_2' => array(
                'label'       => isset($labels['water_level_2']) ? $labels['water_level_2'] : __('Vannføring Syrstad', 'orkla-water-level'),
                'slug'        => 'syrstad',
                'color'       => '#0ea5e9',
                'description' => __('Måler vannføring ved Syrstad.', 'orkla-water-level'),
            ),
            'water_level_3' => array(
                'label'       => isset($labels['water_level_3']) ? $labels['water_level_3'] : __('Vannføring Storsteinshølen', 'orkla-water-level'),
                'slug'        => 'storsteinsholen',
                'color'       => '#22c55e',
                'description' => __('Måler vannføring ved Storsteinshølen.', 'orkla-water-level'),
            ),
            'flow_rate_1' => array(
                'label'       => isset($labels['flow_rate_1']) ? $labels['flow_rate_1'] : __('Produksjonsvannføring Brattset', 'orkla-water-level'),
                'slug'        => 'produksjon-brattset',
                'color'       => '#f97316',
                'description' => __('Produsert vannføring ved Brattset.', 'orkla-water-level'),
            ),
            'flow_rate_2' => array(
                'label'       => isset($labels['flow_rate_2']) ? $labels['flow_rate_2'] : __('Produksjonsvannføring Grana', 'orkla-water-level'),
                'slug'        => 'produksjon-grana',
                'color'       => '#facc15',
                'description' => __('Produsert vannføring ved Grana.', 'orkla-water-level'),
            ),
            'flow_rate_3' => array(
                'label'       => isset($labels['flow_rate_3']) ? $labels['flow_rate_3'] : __('Produksjon Svorkmo', 'orkla-water-level'),
                'slug'        => 'produksjon-svorkmo',
                'color'       => '#8b5cf6',
                'description' => __('Produsert vannføring ved Svorkmo.', 'orkla-water-level'),
            ),
        );
    }

    private function get_shortcode_definitions() {
        return array(
            array(
                'tag'         => 'orkla_water_level',
                'description' => __('Viser den interaktive vannføringsgrafen med periodemuligheter.', 'orkla-water-level'),
                'attributes'  => array(
                    'period'        => __('Forvalgt periode (today, week, month, year eller year:YYYY). Standard: today.', 'orkla-water-level'),
                    'height'        => __('Høyden på grafen, f.eks. 400px.', 'orkla-water-level'),
                    'show_controls' => __('Vis eller skjul periodevelger og knapper (true/false).', 'orkla-water-level'),
                ),
                'example'     => '[orkla_water_level period="week" height="420px" show_controls="true"]',
            ),
            array(
                'tag'         => 'orkla_water_meter',
                'description' => __('Viser en animert oversikt over siste målinger for utvalgte stasjoner og vanntemperatur.', 'orkla-water-level'),
                'attributes'  => array(
                    'stations'         => __('Kommaseparert liste med felt (water_level_1, water_level_2, water_level_3). Standard: alle.', 'orkla-water-level'),
                    'show_temperature' => __('Vis termometer for vanntemperatur (true/false). Standard: true.', 'orkla-water-level'),
                    'reference_max'    => __('Sett en manuell referanse i m³/s som brukes for 100 %.', 'orkla-water-level'),
                ),
                'example'     => '[orkla_water_meter stations="water_level_2" reference_max="60"]',
            ),
        );
    }

    private function get_latest_measurement_snapshot() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'orkla_water_data';
        $escaped_table = $wpdb->esc_like($table_name);
        $table_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $escaped_table));

        if ($table_exists !== $table_name) {
            return null;
        }

        $row = $wpdb->get_row(
            "SELECT *
             FROM $table_name
             WHERE water_level_1 IS NOT NULL
                OR water_level_2 IS NOT NULL
                OR water_level_3 IS NOT NULL
                OR temperature_1 IS NOT NULL
             ORDER BY timestamp DESC
             LIMIT 1",
            ARRAY_A
        );

        if ($wpdb->last_error) {
            error_log('Orkla Plugin: Database error while fetching latest water snapshot - ' . $wpdb->last_error);
        }

        return $row ?: null;
    }

    /**
     * Get water data for a specific station, date, and time.
     *
     * @param string $station_key The key of the station (e.g., 'water_level_2').
     * @param string $date The date in 'YYYY-MM-DD' format.
     * @param string $time The time in 'HH:MM:SS' format.
     * @return array|null The water data record, or null if not found.
     */
    public function get_water_data_by_datetime($station_key, $date, $time) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'orkla_water_data';

        // Map user-friendly keys to database column names
        $station_to_column_map = array(
            'storsteinsholen' => 'water_level_1',
            'rennebu_oppstroms' => 'water_level_2',
            'syrstad' => 'water_level_3',
            'nedstroms_svorkmo' => 'flow_rate_1',
            'oppstroms_brattset' => 'flow_rate_2',
            'default' => 'water_level_1' // Fallback column
        );

        $column_name = isset($station_to_column_map[$station_key]) 
            ? $station_to_column_map[$station_key] 
            : $station_to_column_map['default'];

        // Whitelist of allowed column names for security
        $allowed_columns = array(
            'water_level_1', 'water_level_2', 'water_level_3',
            'flow_rate_1', 'flow_rate_2', 'flow_rate_3'
        );

        if (!in_array($column_name, $allowed_columns)) {
            error_log('Orkla Plugin: Invalid station key provided: ' . $station_key);
            return null;
        }

        $datetime_str = $date . ' ' . $time;

        // Construct the query safely with the whitelisted column name
        $query = $wpdb->prepare(
            "SELECT timestamp, `$column_name` as water_level, temperature_1 as water_temperature
             FROM $table_name
             WHERE timestamp <= %s
             ORDER BY ABS(TIMESTAMPDIFF(SECOND, timestamp, %s)) ASC
             LIMIT 1",
            $datetime_str,
            $datetime_str
        );

        $result = $wpdb->get_row($query, ARRAY_A);

        if ($wpdb->last_error) {
            error_log('Orkla Plugin: Database error in get_water_data_by_datetime: ' . $wpdb->last_error);
        }

        return $result;
    }

    private function get_csv_sources() {
        $shared_file = 'VannforingOrkla.csv';
        $shared_remote_url = $this->get_shared_remote_csv_url();
        $shared_cache_file = 'VannforingOrkla.csv';

        // Only the shared remote URL should be fetched automatically every hour
        // Other sources (temperature, local files) are skipped in auto-fetch
        
        return array(
            'water_level_1' => array(
                'label'           => __('Vannføring Oppstrøms Brattset', 'orkla-water-level'),
                'file'            => $shared_file,
                'value_column'    => 2,
                'fallback_columns'=> array(1),
                'remote_url'      => $shared_remote_url,
                'cache_filename'  => $shared_cache_file,
                'value_type'      => 'flow',
            ),
            'water_level_2' => array(
                'label'           => __('Vannføring Syrstad', 'orkla-water-level'),
                'file'            => $shared_file,
                'value_column'    => 4,
                'fallback_columns'=> array(1),
                'remote_url'      => $shared_remote_url,
                'cache_filename'  => $shared_cache_file,
                'value_type'      => 'flow',
            ),
            'water_level_3' => array(
                'label'           => __('Vannføring Storsteinshølen', 'orkla-water-level'),
                'file'            => $shared_file,
                'value_column'    => 5,
                'fallback_columns'=> array(1),
                'remote_url'      => $shared_remote_url,
                'cache_filename'  => $shared_cache_file,
                'value_type'      => 'flow',
            ),
            'flow_rate_1' => array(
                'label'           => __('Produksjonsvannføring Brattset', 'orkla-water-level'),
                'file'            => $shared_file,
                'value_column'    => 8,
                'remote_url'      => $shared_remote_url,
                'cache_filename'  => $shared_cache_file,
                'value_type'      => 'flow',
            ),
            'flow_rate_2' => array(
                'label'           => __('Produksjonsvannføring Grana', 'orkla-water-level'),
                'file'            => $shared_file,
                'value_column'    => 9,
                'remote_url'      => $shared_remote_url,
                'cache_filename'  => $shared_cache_file,
                'value_type'      => 'flow',
            ),
            'flow_rate_3' => array(
                'label'           => __('Produksjonsvannføring Svorkmo', 'orkla-water-level'),
                'file'            => $shared_file,
                'value_column'    => 10,
                'remote_url'      => $shared_remote_url,
                'cache_filename'  => $shared_cache_file,
                'value_type'      => 'flow',
            ),
        );
    }

    private function get_shared_remote_csv_url() {
        $default_url = 'https://orklavannstand.online/VannforingOrkla.csv';
        $url = get_option('orkla_shared_remote_csv_url', '');

        $url = apply_filters('orkla_shared_remote_csv_url', $url);

        if (!is_string($url)) {
            $url = $default_url;
        }

        $url = trim($url);

        if ($url === '') {
            $url = $default_url;
        }

        return esc_url_raw($url);
    }

    private function get_csv_cache_directory() {
        $uploads = wp_upload_dir();

        if (is_array($uploads) && empty($uploads['error']) && !empty($uploads['basedir'])) {
            return trailingslashit($uploads['basedir']) . 'orkla-water-level';
        }

        return $this->get_csv_base_path();
    }

    private function get_csv_cache_path($filename) {
        $directory = $this->get_csv_cache_directory();
        $sanitized = function_exists('sanitize_file_name') ? sanitize_file_name($filename) : preg_replace('/[^A-Za-z0-9_\.\-]/', '', $filename);

        if ($sanitized === '') {
            $sanitized = 'orkla-csv-cache-' . md5($filename);
        }

        if (function_exists('wp_normalize_path')) {
            $directory = wp_normalize_path($directory);
        }

        if (function_exists('trailingslashit')) {
            return trailingslashit($directory) . $sanitized;
        }

        return rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $sanitized;
    }

    private function ensure_remote_csv($url, $cache_path, $force_refresh = false, $dataset_key = '') {
        error_log('Orkla Plugin: ensure_remote_csv - url=' . $url . ', dataset=' . $dataset_key . ', force_refresh=' . ($force_refresh ? 'true' : 'false'));
        
        $cache_key = md5($url . '|' . $cache_path);

        if (isset($this->remote_cache_state[$cache_key])) {
            $state = $this->remote_cache_state[$cache_key];
            if ($state instanceof WP_Error) {
                error_log('Orkla Plugin: Remote CSV cached error: ' . $state->get_error_message());
                return $state;
            }

            if ($state === 'ok') {
                if (file_exists($cache_path)) {
                    error_log('Orkla Plugin: Using cached remote CSV: ' . $cache_path);
                    return true;
                }
            }
        }

        $needs_download = $force_refresh || !file_exists($cache_path);

        if (!$needs_download) {
            $max_age = (int) apply_filters('orkla_remote_csv_max_age', 15 * MINUTE_IN_SECONDS, $url, $dataset_key);
            $mtime = @filemtime($cache_path);

            if ($mtime === false) {
                $needs_download = true;
            } else {
                $age = time() - $mtime;
                if ($age >= $max_age) {
                    $needs_download = true;
                }
            }
        }

        if (!$needs_download) {
            $this->remote_cache_state[$cache_key] = 'ok';
            return true;
        }

        $response = $this->download_remote_csv($url);

        if (is_wp_error($response)) {
            $this->remote_cache_state[$cache_key] = $response;
            return $response;
        }

        $directory = dirname($cache_path);
        if (!file_exists($directory)) {
            if (!wp_mkdir_p($directory)) {
                $error = new WP_Error('orkla_csv_cache_dir_failed', sprintf(__('Unable to create CSV cache directory: %s', 'orkla-water-level'), $directory));
                $this->remote_cache_state[$cache_key] = $error;
                return $error;
            }
        }

        $bytes = @file_put_contents($cache_path, $response);

        if ($bytes === false) {
            $error = new WP_Error('orkla_csv_cache_write_failed', sprintf(__('Failed to write cached CSV file for %s.', 'orkla-water-level'), $dataset_key ? $dataset_key : $url));
            $this->remote_cache_state[$cache_key] = $error;
            return $error;
        }

        @touch($cache_path);

        $this->remote_cache_state[$cache_key] = 'ok';
        return true;
    }

    private function download_remote_csv($url) {
        error_log('Orkla Plugin: Downloading remote CSV from: ' . $url);
        
        $args = array(
            'timeout'     => 20,
            'redirection' => 3,
            'headers'     => array(
                'Accept' => 'text/csv,text/plain,*/*;q=0.8',
            ),
        );

        $response = wp_remote_get($url, $args);

        if (is_wp_error($response)) {
            error_log('Orkla Plugin: wp_remote_get error: ' . $response->get_error_message());
            return $response;
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        error_log('Orkla Plugin: Remote CSV HTTP response code: ' . $code);
        
        if ($code !== 200) {
            $error = new WP_Error(
                'orkla_csv_http_error',
                sprintf(__('Remote CSV request failed with status %1$s for %2$s.', 'orkla-water-level'), $code, $url)
            );
            error_log('Orkla Plugin: Remote CSV HTTP error: ' . $error->get_error_message());
            return $error;
        }

        $body = wp_remote_retrieve_body($response);
        error_log('Orkla Plugin: Remote CSV response body length: ' . strlen($body));
        
        if ($body === '' || $body === null) {
            $error = new WP_Error('orkla_csv_empty_body', sprintf(__('Remote CSV response was empty for %s.', 'orkla-water-level'), $url));
            error_log('Orkla Plugin: Remote CSV empty body');
            return $error;
        }

        error_log('Orkla Plugin: Remote CSV downloaded successfully');
        return $body;
    }

    private function add_source_warning($dataset_key, $message) {
        if ($dataset_key === null || $dataset_key === '') {
            return;
        }

        $message = is_string($message) ? trim($message) : (string) $message;

        if ($message === '') {
            return;
        }

        if (!isset($this->source_warnings[$dataset_key]) || !is_array($this->source_warnings[$dataset_key])) {
            $this->source_warnings[$dataset_key] = array();
        }

        if (!in_array($message, $this->source_warnings[$dataset_key], true)) {
            $this->source_warnings[$dataset_key][] = $message;
        }
    }

    protected function describe_csv_sources() {
        $field_labels = $this->get_field_labels();
        $sources = $this->get_csv_sources();
        $overview = array();
        $last_summary = $this->get_last_import_summary();
        $last_sources = array();

        if (is_array($last_summary) && isset($last_summary['summary']['sources']) && is_array($last_summary['summary']['sources'])) {
            $last_sources = $last_summary['summary']['sources'];
        }

        foreach ($field_labels as $field => $label) {
            $config = isset($sources[$field]) ? $sources[$field] : null;
            $path = null;
            $exists = false;
            $filesize = null;
            $modified = null;
            $errors = array();
            $warnings = array();

            if ($config) {
                $resolved = $this->resolve_csv_path($config, false, $field);

                if (is_wp_error($resolved)) {
                    $errors[] = $resolved->get_error_message();
                } else {
                    $path = $resolved;
                    $exists = $path && is_readable($path);

                    if ($exists) {
                        $size = @filesize($path);
                        $mtime = @filemtime($path);
                        $filesize = $size !== false ? (int) $size : null;
                        $modified = $mtime !== false ? (int) $mtime : null;
                    }
                }

                if (isset($this->source_warnings[$field])) {
                    $warnings = array_merge($warnings, (array) $this->source_warnings[$field]);
                }
            }

            if (!empty($warnings)) {
                $warnings = array_values(array_unique(array_map('strval', $warnings)));
            }

            if (!empty($errors)) {
                $errors = array_values(array_unique(array_map('strval', $errors)));
            }

            $overview[$field] = array(
                'field'        => $field,
                'label'        => $label,
                'configured'   => $config !== null,
                'source_label' => $config && isset($config['label']) ? $config['label'] : $label,
                'file'         => $config && isset($config['file']) ? $config['file'] : null,
                'path'         => $path,
                'exists'       => $exists,
                'filesize'     => $filesize,
                'modified'     => $modified,
                'remote_url'   => $config && !empty($config['remote_url']) ? $config['remote_url'] : null,
                'warnings'     => $warnings,
                'errors'       => $errors,
                'last_import'  => isset($last_sources[$field]) ? $last_sources[$field] : array(),
            );
        }

        return $overview;
    }

    private function resolve_csv_path(array $config, $force_refresh = false, $dataset_key = null) {
        $file = isset($config['file']) ? trim($config['file']) : '';
        $remote_url = isset($config['remote_url']) ? trim($config['remote_url']) : '';
        $dataset_key = $dataset_key ? $dataset_key : $file;

        $base = $this->get_csv_base_path();
        $local_path = null;

        if ($file !== '') {
            $local_path = rtrim($base, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $file;
            if (function_exists('wp_normalize_path')) {
                $local_path = wp_normalize_path($local_path);
            }
        }

        if ($remote_url !== '') {
            $cache_filename = !empty($config['cache_filename']) ? $config['cache_filename'] : ($file !== '' ? $file : basename(parse_url($remote_url, PHP_URL_PATH)));
            $cache_path = $this->get_csv_cache_path($cache_filename);

            $result = $this->ensure_remote_csv($remote_url, $cache_path, $force_refresh, $dataset_key);

            if (is_wp_error($result)) {
                $this->add_source_warning($dataset_key, $result->get_error_message());

                if ($local_path && is_readable($local_path)) {
                    $fallback_message = __('Falling back to existing local CSV file.', 'orkla-water-level');
                    $this->add_source_warning($dataset_key, $fallback_message);
                    return $local_path;
                }

                return $result;
            }

            return $cache_path;
        }

        if ($local_path && $dataset_key && $remote_url === '') {
            $this->add_source_warning($dataset_key, __('Remote CSV URL not configured; using local file.', 'orkla-water-level'));
        }

        return $local_path;
    }

    private function get_csv_base_path() {
        $default = dirname(ORKLA_PLUGIN_PATH);
        $filtered = apply_filters('orkla_csv_base_path', $default);

        if (!is_string($filtered) || $filtered === '') {
            return $default;
        }

        return rtrim($filtered, DIRECTORY_SEPARATOR);
    }

    private function parse_csv_source($path, array $config, DateTimeZone $timezone) {
        $values = array();
        $meta = array(
            'rows_imported'   => 0,
            'rows_skipped'    => 0,
            'lines_parsed'    => 0,
            'first_timestamp' => null,
            'last_timestamp'  => null,
            'error'           => null,
            'warnings'        => array(),
        );

        $handle = @fopen($path, 'r');

        if (!$handle) {
            $meta['error'] = sprintf(
                __('Unable to open CSV file: %s', 'orkla-water-level'),
                basename($path)
            );
            return array('values' => $values, 'meta' => $meta);
        }

        $fallback_reported = false;

        while (($row = fgetcsv($handle, 0, ";", "\"", "\\")) !== false) {
            $meta['lines_parsed']++;

            if (empty($row)) {
                continue;
            }

            $raw_timestamp = isset($row[0]) ? trim($row[0]) : '';
            if ($raw_timestamp === '' || strpos($raw_timestamp, '#') === 0 || stripos($raw_timestamp, 'Tidspunkt') === 0) {
                continue;
            }

            $datetime = $this->create_datetime_from_csv($raw_timestamp, $timezone);
            if (!$datetime) {
                $meta['rows_skipped']++;
                continue;
            }

            $column_index = isset($config['value_column']) ? (int) $config['value_column'] : 1;
            $raw_value = array_key_exists($column_index, $row) ? $row[$column_index] : null;

            if (!array_key_exists($column_index, $row)) {
                $fallback_candidates = array();

                if (!empty($config['fallback_columns'])) {
                    $fallback_candidates = (array) $config['fallback_columns'];
                } else {
                    $total_columns = count($row);
                    if ($total_columns > 1) {
                        // Prefer the last column that is not the timestamp column
                        for ($idx = $total_columns - 1; $idx >= 1; $idx--) {
                            if (array_key_exists($idx, $row)) {
                                $fallback_candidates[] = $idx;
                                break;
                            }
                        }

                        if ($total_columns > 1 && !in_array(1, $fallback_candidates, true)) {
                            $fallback_candidates[] = 1;
                        }
                    }
                }

                foreach ($fallback_candidates as $candidate_index) {
                    if (array_key_exists($candidate_index, $row)) {
                        $raw_value = $row[$candidate_index];
                        if (!$fallback_reported && $candidate_index !== $column_index) {
                            $label = isset($config['label']) ? $config['label'] : $column_index;
                            $meta['warnings'][] = sprintf(
                                __('Configured column %1$d missing for %2$s. Fallback column %3$d was used for parsing.', 'orkla-water-level'),
                                $column_index,
                                $label,
                                $candidate_index
                            );
                            $fallback_reported = true;
                        }
                        break;
                    }
                }
            }

            // Allow empty/missing values - they represent data that hasn't been recorded yet
            if ($raw_value === null || trim($raw_value) === '') {
                // Store NULL for this timestamp+field to preserve the timestamp entry
                $timestamp = $datetime->getTimestamp();
                if (!isset($values[$timestamp])) {
                    $values[$timestamp] = null;
                }
                // Continue to next field for this row
                continue;
            }

            $value = $this->parse_number($raw_value);
            if (isset($config['value_type']) && $config['value_type'] === 'temperature') {
                $value = $this->normalize_temperature_value($value);
            }

            // If value parsing failed, skip this field but continue with the row
            if ($value === null) {
                // Store NULL for this timestamp+field to preserve the timestamp entry
                $timestamp = $datetime->getTimestamp();
                if (!isset($values[$timestamp])) {
                    $values[$timestamp] = null;
                }
                continue;
            }

            $timestamp = $datetime->getTimestamp();
            $values[$timestamp] = $value;
            $meta['rows_imported']++;

            if ($meta['first_timestamp'] === null || $timestamp < $meta['first_timestamp']) {
                $meta['first_timestamp'] = $timestamp;
            }
            if ($meta['last_timestamp'] === null || $timestamp > $meta['last_timestamp']) {
                $meta['last_timestamp'] = $timestamp;
            }
        }

        fclose($handle);

        // Count rows - rows with valid timestamps are imported (even if some values are empty)
        // Only skip rows that had parse errors or completely invalid timestamps
        foreach ($values as $timestamp => $val) {
            if ($meta['first_timestamp'] === null || $timestamp < $meta['first_timestamp']) {
                $meta['first_timestamp'] = $timestamp;
            }
            if ($meta['last_timestamp'] === null || $timestamp > $meta['last_timestamp']) {
                $meta['last_timestamp'] = $timestamp;
            }
        }
        
        $meta['rows_imported'] = count($values);

        return array('values' => $values, 'meta' => $meta);
    }

    private function create_datetime_from_csv($raw, DateTimeZone $timezone) {
        $raw = trim($raw);

        if ($raw === '') {
            return null;
        }

        // Strip UTF-8 BOM if present
        $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw);

        // Handle Orkla formatted timestamps such as "26/10/2025 T01"
        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})\s*T(\d{1,2})$/', $raw, $matches)) {
            $day = (int) $matches[1];
            $month = (int) $matches[2];
            $year = (int) $matches[3];
            $hour = (int) $matches[4];

            $base = DateTime::createFromFormat('Y-m-d H:i:s', sprintf('%04d-%02d-%02d 00:00:00', $year, $month, $day), $timezone);
            if ($base instanceof DateTime) {
                $hour_index = max(0, min(23, $hour - 1));
                if ($hour_index > 0) {
                    $base->modify(sprintf('+%d hour', $hour_index));
                }
                return $base;
            }
        }

        // Handle "26/10/2025 01:00" and similar day-first formats
        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})\s+(\d{1,2})(?::(\d{2}))?$/', $raw, $matches)) {
            $day = (int) $matches[1];
            $month = (int) $matches[2];
            $year = (int) $matches[3];
            $hour = (int) $matches[4];
            $minute = isset($matches[5]) ? (int) $matches[5] : 0;

            $candidate = DateTime::createFromFormat('Y-m-d H:i:s', sprintf('%04d-%02d-%02d %02d:%02d:00', $year, $month, $day, $hour, $minute), $timezone);
            if ($candidate instanceof DateTime) {
                return $candidate;
            }
        }

        $fallback_formats = array(
            DATE_ATOM,
            'Y-m-d H:i:s',
            'Y-m-d\TH:i:s',
            'Y-m-d H:i',
            'Y-m-d\TH:i',
        );

        foreach ($fallback_formats as $format) {
            $datetime = DateTime::createFromFormat($format, $raw, new DateTimeZone('UTC'));
            if ($datetime instanceof DateTime) {
                $datetime->setTimezone($timezone);
                return $datetime;
            }
        }

        try {
            $datetime = new DateTime($raw, new DateTimeZone('UTC'));
            $datetime->setTimezone($timezone);
            return $datetime;
        } catch (Exception $e) {
            return null;
        }
    }

    private function store_import_summary(array $summary) {
        $payload = array(
            'completed_at' => current_time('mysql'),
            'summary'      => $summary,
        );

        update_option('orkla_csv_last_import', $payload, false);
    }

    protected function get_last_import_summary() {
        $option = get_option('orkla_csv_last_import');

        if (!is_array($option)) {
            return null;
        }

        if (!isset($option['summary']) || !is_array($option['summary'])) {
            $option['summary'] = array();
        }

        return $option;
    }

    private function format_timestamp_output($timestamp) {
        if (empty($timestamp)) {
            return null;
        }

        if (function_exists('wp_date')) {
            return wp_date('Y-m-d H:i:s', $timestamp);
        }

        return date('Y-m-d H:i:s', $timestamp);
    }

    private function get_wp_timezone() {
        if (function_exists('wp_timezone')) {
            return wp_timezone();
        }

        $tz_string = get_option('timezone_string');
        if ($tz_string) {
            try {
                return new DateTimeZone($tz_string);
            } catch (Exception $e) {
                // Fall through
            }
        }

        $offset = get_option('gmt_offset');
        if ($offset) {
            $hours = (int) $offset;
            $minutes = abs($offset - $hours) * 60;
            $sign = $offset >= 0 ? '+' : '-';
            $tz = sprintf('%s%02d:%02d', $sign, abs($hours), $minutes);
            try {
                return new DateTimeZone($tz);
            } catch (Exception $e) {
                // Fall through
            }
        }

        return new DateTimeZone('UTC');
    }

    private function get_csv_preview_lines($path, $limit = 5) {
        $lines = array();
        $handle = @fopen($path, 'r');

        if (!$handle) {
            return $lines;
        }

        while (($line = fgets($handle)) !== false && count($lines) < $limit) {
            $lines[] = rtrim($line, "\r\n");
        }

        fclose($handle);

        return $lines;
    }

    private function add_admin_message($type, $message) {
        if (!is_string($type) || $type === '' || $message === '' || $message === null) {
            return;
        }

        if (!isset($this->admin_messages[$type]) || !is_array($this->admin_messages[$type])) {
            $this->admin_messages[$type] = array();
        }

        $this->admin_messages[$type][] = $message;
    }

    private function consume_admin_flash_messages() {
        $flash = get_transient(self::ADMIN_FLASH_KEY);

        if (!is_array($flash)) {
            return;
        }

        foreach (array('errors', 'notices') as $type) {
            if (empty($flash[$type])) {
                continue;
            }

            if (!isset($this->admin_messages[$type]) || !is_array($this->admin_messages[$type])) {
                $this->admin_messages[$type] = array();
            }

            $this->admin_messages[$type] = array_merge($this->admin_messages[$type], (array) $flash[$type]);
        }

        delete_transient(self::ADMIN_FLASH_KEY);
    }

    private function persist_admin_flash_messages() {
        $payload = array(
            'errors'  => array(),
            'notices' => array(),
        );

        foreach (array('errors', 'notices') as $type) {
            if (!empty($this->admin_messages[$type]) && is_array($this->admin_messages[$type])) {
                $payload[$type] = array_values(array_unique(array_map('strval', $this->admin_messages[$type])));
            }
        }

        set_transient(self::ADMIN_FLASH_KEY, $payload, MINUTE_IN_SECONDS);
    }

    private function handle_dataset_post_request() {
        if (!current_user_can('manage_options')) {
            return;
        }

        check_admin_referer('orkla_dataset_action');

        $action = isset($_POST['orkla_dataset_action']) ? sanitize_key(wp_unslash($_POST['orkla_dataset_action'])) : '';
        $dataset_key = isset($_POST['dataset_key']) ? sanitize_key(wp_unslash($_POST['dataset_key'])) : '';

        if ($dataset_key === '') {
            $this->add_admin_message('errors', __('Dataset was not specified.', 'orkla-water-level'));
            $this->persist_admin_flash_messages();
            $this->redirect_to_admin_page();
        }

        $field_defaults = $this->get_data_field_defaults();

        switch ($action) {
            case 'delete':
                if (!array_key_exists($dataset_key, $field_defaults)) {
                    $this->add_admin_message('errors', __('Unknown dataset selected for deletion.', 'orkla-water-level'));
                    break;
                }

                $result = $this->delete_dataset_records($dataset_key);

                if (is_wp_error($result)) {
                    $this->add_admin_message('errors', $result->get_error_message());
                } else {
                    $label = $this->get_dataset_label($dataset_key);
                    $message = sprintf(
                        __('All values for %1$s were cleared. Rows updated: %2$d. Empty rows removed: %3$d.', 'orkla-water-level'),
                        $label,
                        (int) $result['rows_cleared'],
                        (int) $result['rows_removed']
                    );
                    $this->add_admin_message('notices', $message);
                    $this->store_dataset_clear_summary($dataset_key, $label);
                }
                break;

            case 'upload':
                $sources = $this->get_csv_sources();

                if (!isset($sources[$dataset_key])) {
                    $this->add_admin_message('errors', __('Dataset is not configured for CSV upload.', 'orkla-water-level'));
                    break;
                }

                $file = isset($_FILES['dataset_file']) ? $_FILES['dataset_file'] : null;

                if (!$file || !is_array($file)) {
                    $this->add_admin_message('errors', __('CSV file was not provided.', 'orkla-water-level'));
                    break;
                }

                $upload = $this->process_dataset_upload($dataset_key, $sources[$dataset_key], $file);

                if (is_wp_error($upload)) {
                    $this->add_admin_message('errors', $upload->get_error_message());
                } else {
                    $label = $this->get_dataset_label($dataset_key);
                    $result = isset($upload['result']) ? $upload['result'] : array();
                    $message = sprintf(
                        __('CSV import for %1$s completed. Inserted: %2$d, updated: %3$d, skipped: %4$d.', 'orkla-water-level'),
                        $label,
                        isset($result['inserted']) ? (int) $result['inserted'] : 0,
                        isset($result['updated']) ? (int) $result['updated'] : 0,
                        isset($result['skipped']) ? (int) $result['skipped'] : 0
                    );
                    $this->add_admin_message('notices', $message);

                    if (!empty($upload['summary']['errors'])) {
                        foreach ((array) $upload['summary']['errors'] as $error_message) {
                            $this->add_admin_message('errors', $error_message);
                        }
                    }
                }
                break;

            default:
                $this->add_admin_message('errors', __('Unknown dataset action requested.', 'orkla-water-level'));
                break;
        }

        $this->persist_admin_flash_messages();
        $this->redirect_to_admin_page();
    }

    private function redirect_to_admin_page() {
        $url = admin_url('admin.php?page=orkla-water-level');
        wp_safe_redirect($url);
        exit;
    }

    private function delete_dataset_records($dataset_key) {
        global $wpdb;

        $table = $wpdb->prefix . 'orkla_water_data';
        $column = $dataset_key;

        $sql = sprintf('UPDATE `%1$s` SET `%2$s` = NULL', $table, $column);
        $updated = $wpdb->query($sql);

        if ($updated === false) {
            $error = $wpdb->last_error ? $wpdb->last_error : __('Database error while clearing dataset.', 'orkla-water-level');
            return new WP_Error('dataset_clear_failed', $error);
        }

        $removed = $this->cleanup_empty_rows();

        return array(
            'rows_cleared' => (int) $updated,
            'rows_removed' => (int) $removed,
        );
    }

    private function cleanup_empty_rows() {
        global $wpdb;

        $fields = array_keys($this->get_data_field_defaults());
        if (empty($fields)) {
            return 0;
        }

        $conditions = array();
        foreach ($fields as $field) {
            $conditions[] = sprintf('`%s` IS NULL', $field);
        }

        $table = $wpdb->prefix . 'orkla_water_data';
        $sql = sprintf('DELETE FROM `%1$s` WHERE %2$s', $table, implode(' AND ', $conditions));
        $deleted = $wpdb->query($sql);

        if ($deleted === false) {
            return 0;
        }

        return (int) $deleted;
    }

    private function process_dataset_upload($dataset_key, array $config, array $uploaded_file) {
        if (!isset($uploaded_file['tmp_name']) || !isset($uploaded_file['error'])) {
            return new WP_Error('upload_invalid', __('Invalid upload request.', 'orkla-water-level'));
        }

        if ((int) $uploaded_file['error'] !== UPLOAD_ERR_OK) {
            $message = isset($uploaded_file['error']) ? $uploaded_file['error'] : __('Upload failed.', 'orkla-water-level');
            return new WP_Error('upload_failed', is_string($message) ? $message : __('Upload failed.', 'orkla-water-level'));
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';

        $handled = wp_handle_upload($uploaded_file, array('test_form' => false));

        if (isset($handled['error'])) {
            return new WP_Error('upload_failed', $handled['error']);
        }

        $path = isset($handled['file']) ? $handled['file'] : '';

        if ($path === '' || !file_exists($path)) {
            return new WP_Error('upload_missing_file', __('Uploaded file could not be located.', 'orkla-water-level'));
        }

        $timezone = $this->get_wp_timezone();
        $parsed = $this->parse_csv_source($path, $config, $timezone);
        $values = isset($parsed['values']) ? $parsed['values'] : array();
        $meta = isset($parsed['meta']) ? $parsed['meta'] : array();

        if (function_exists('wp_delete_file')) {
            wp_delete_file($path);
        } else {
            @unlink($path);
        }

        if (empty($values)) {
            return new WP_Error('upload_empty', __('No valid measurements were found in the uploaded CSV file.', 'orkla-water-level'));
        }

        $import_result = $this->import_dataset_values($dataset_key, $values, $timezone);

        $errors = array();
        if (!empty($meta['error'])) {
            $errors[] = $meta['error'];
        }
        if (!empty($import_result['errors'])) {
            $errors = array_merge($errors, $import_result['errors']);
        }

        $meta_warnings = !empty($meta['warnings']) ? array_values(array_unique((array) $meta['warnings'])) : array();

        $summary = array(
            'imported'     => $import_result['inserted'],
            'updated'      => $import_result['updated'],
            'skipped'      => $import_result['skipped'],
            'errors'       => $errors,
            'warnings'     => $meta_warnings,
            'record_count' => $import_result['processed'],
            'sources'      => array(
                $dataset_key => array(
                    'label'          => isset($config['label']) ? $config['label'] : $dataset_key,
                    'status'         => 'manual-upload',
                    'rows_imported'  => isset($meta['rows_imported']) ? (int) $meta['rows_imported'] : 0,
                    'rows_skipped'   => isset($meta['rows_skipped']) ? (int) $meta['rows_skipped'] : 0,
                    'lines_parsed'   => isset($meta['lines_parsed']) ? (int) $meta['lines_parsed'] : 0,
                    'first_timestamp'=> !empty($meta['first_timestamp']) ? $this->format_timestamp_output($meta['first_timestamp']) : null,
                    'last_timestamp' => !empty($meta['last_timestamp']) ? $this->format_timestamp_output($meta['last_timestamp']) : null,
                    'warnings'       => $meta_warnings,
                ),
            ),
        );

        $this->store_import_summary($summary);

        return array(
            'summary' => $summary,
            'result'  => $import_result,
        );
    }

    private function import_dataset_values($dataset_key, array $values, DateTimeZone $timezone) {
        global $wpdb;

        $table = $wpdb->prefix . 'orkla_water_data';
        $inserted = 0;
        $updated = 0;
        $skipped = 0;
        $errors = array();
        $processed = 0;

        foreach ($values as $unix => $value) {
            $processed++;

            if ($value === null) {
                $skipped++;
                continue;
            }

            $date = new DateTime('@' . $unix);
            $date->setTimezone($timezone);

            $timestamp = $date->format('Y-m-d H:i:s');
            $date_recorded = $date->format('Y-m-d');
            $time_recorded = $date->format('H:i:s');

            $row = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT id, `{$dataset_key}` AS current_value FROM `{$table}` WHERE timestamp = %s LIMIT 1",
                    $timestamp
                )
            );

            if ($row) {
                $current_value = $row->current_value;
                if ($current_value !== null && $this->float_values_equal((float) $current_value, (float) $value)) {
                    $skipped++;
                    continue;
                }

                $result = $wpdb->update(
                    $table,
                    array($dataset_key => $value),
                    array('id' => (int) $row->id),
                    array('%f'),
                    array('%d')
                );

                if ($result === false) {
                    $errors[] = $wpdb->last_error ? $wpdb->last_error : sprintf(__('Failed updating %s at %s.', 'orkla-water-level'), $dataset_key, $timestamp);
                } elseif ($result === 0) {
                    $skipped++;
                } else {
                    $updated++;
                }

                continue;
            }

            $data = array(
                'timestamp'     => $timestamp,
                'date_recorded' => $date_recorded,
                'time_recorded' => $time_recorded,
                $dataset_key    => $value,
            );

            $result = $wpdb->insert(
                $table,
                $data,
                array('%s', '%s', '%s', '%f')
            );

            if ($result === false) {
                $errors[] = $wpdb->last_error ? $wpdb->last_error : sprintf(__('Failed inserting %s at %s.', 'orkla-water-level'), $dataset_key, $timestamp);
            } else {
                $inserted++;
            }
        }

        return array(
            'inserted'  => $inserted,
            'updated'   => $updated,
            'skipped'   => $skipped,
            'errors'    => $errors,
            'processed' => $processed,
        );
    }

    private function float_values_equal($a, $b, $epsilon = 0.0001) {
        return abs((float) $a - (float) $b) <= $epsilon;
    }

    private function get_dataset_label($dataset_key) {
        $labels = $this->get_field_labels();
        if (isset($labels[$dataset_key])) {
            return $labels[$dataset_key];
        }

        return $dataset_key;
    }

    private function store_dataset_clear_summary($dataset_key, $label) {
        $summary = array(
            'imported'     => 0,
            'updated'      => 0,
            'skipped'      => 0,
            'errors'       => array(),
            'warnings'     => array(),
            'record_count' => 0,
            'sources'      => array(
                $dataset_key => array(
                    'label'          => $label,
                    'status'         => 'cleared',
                    'rows_imported'  => 0,
                    'rows_skipped'   => 0,
                    'lines_parsed'   => 0,
                    'first_timestamp'=> null,
                    'last_timestamp' => null,
                ),
            ),
        );

        $this->store_import_summary($summary);
    }

    private function normalize_temperature_value($value) {
        if ($value === null) {
            return null;
        }

        // Ensure scalar float
        if (is_string($value)) {
            $value = str_replace(',', '.', $value);
        }

        $numeric = floatval($value);

        // Filter out unrealistic values
        if ($numeric < -5 || $numeric > 35) {
            return null;
        }

        return round($numeric, 2);
    }
    
    private function parse_number($value) {
        if (empty($value) || $value === '' || $value === '-' || trim($value) === '') return null;
        
        // Convert to string if not already
        $value = (string)$value;
        
        // Convert European decimal format (comma to dot)
        $value = str_replace(',', '.', $value);
        
        // Remove spaces and other whitespace
        $value = trim($value);
        
        // Remove any non-numeric characters except decimal point, minus, and scientific notation
        $value = preg_replace('/[^0-9.\-eE]/', '', $value);
        
        if (empty($value) || $value === '') return null;
        
        $num = floatval($value);
        
        // Filter out obviously wrong values
        if ($num > 999999 || $num < -999999) {
            return null;
        }
        
        // Return the parsed number, including legitimate zeros
        return $num;
    }

    public function test_import_detail_ui() {
        echo '<div class="wrap">';
        echo '<h1>Import Detail Test</h1>';
        
        // Get the CSV path and download it fresh
        $url = $this->get_shared_remote_csv_url();
        echo '<h2>CSV Source</h2>';
        echo '<p>URL: ' . esc_html($url) . '</p>';
        
        $cache_path = $this->get_csv_base_path() . 'VannforingOrkla.csv';
        $response = wp_remote_get($url, array(
            'timeout'   => 20,
            'sslverify' => false,
            'headers'   => array('Accept' => 'text/csv; charset=utf-8'),
        ));
        
        if (is_wp_error($response)) {
            echo '<p style="color:red;"><strong>Download Error:</strong> ' . esc_html($response->get_error_message()) . '</p>';
            echo '</div>';
            return;
        }
        
        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        echo '<p>HTTP Code: ' . intval($code) . ', Body size: ' . strlen($body) . ' bytes</p>';
        
        // Parse the body
        $lines = explode("\n", $body);
        echo '<p>Total lines: ' . count($lines) . '</p>';
        
        // Show last 5 lines from CSV
        echo '<h3>Last 5 data lines in CSV:</h3>';
        echo '<pre style="background:#f5f5f5; padding:10px; overflow-x:auto; max-height:200px;">';
        $data_lines = array_filter($lines, function($line) {
            return !empty(trim($line)) && strpos($line, 'Tidspunkt') === false && strpos($line, '#') === false;
        });
        $last_5 = array_slice($data_lines, -5);
        foreach ($last_5 as $line) {
            echo esc_html($line) . "\n";
        }
        echo '</pre>';
        
        // Parse using the same logic as the import
        echo '<h3>Parsed values for T10/T11 rows:</h3>';
        echo '<pre style="background:#f5f5f5; padding:10px; overflow-x:auto; max-height:300px;">';
        
        $timezone = $this->get_wp_timezone();
        $matches = array();
        foreach ($lines as $line) {
            if (preg_match('/T10|T11/', $line)) {
                $matches[] = $line;
            }
        }
        
        foreach ($matches as $line) {
            $parts = str_getcsv($line, ';');
            if (!empty($parts[0])) {
                $timestamp_str = trim($parts[0]);
                $dt = $this->create_datetime_from_csv($timestamp_str, $timezone);
                if ($dt) {
                    echo "Timestamp: " . esc_html($timestamp_str) . " => " . $dt->format('Y-m-d H:i:s') . "\n";
                    
                    // Show values for each column we care about
                    $cols = array(
                        2 => 'Brattset flow',
                        4 => 'Syrstad flow', 
                        5 => 'Storsteinshølen flow',
                        8 => 'Produksjon Brattset',
                        9 => 'Produksjon Grana',
                        10 => 'Produksjon Svorkmo',
                    );
                    
                    foreach ($cols as $col => $label) {
                        $val = isset($parts[$col]) ? trim($parts[$col]) : '[MISSING]';
                        $parsed = $this->parse_number($val);
                        echo "  Col $col ($label): '$val' => " . ($parsed === null ? 'NULL' : $parsed) . "\n";
                    }
                    echo "\n";
                }
            }
        }
        
        echo '</pre>';
        
        // Now check what's in the database
        global $wpdb;
        $table_name = $wpdb->prefix . 'orkla_water_data';
        echo '<h3>Latest records in database (BEFORE import):</h3>';
        $latest = $wpdb->get_results("SELECT * FROM $table_name ORDER BY timestamp DESC LIMIT 10", ARRAY_A);
        echo '<pre style="background:#f5f5f5; padding:10px; overflow-x:auto;">';
        foreach ($latest as $row) {
            echo 'Timestamp: ' . esc_html($row['timestamp']) . ' | ';
            echo 'water_level_1: ' . ($row['water_level_1'] ?? 'NULL') . ' | ';
            echo 'water_level_2: ' . ($row['water_level_2'] ?? 'NULL') . ' | ';
            echo 'flow_rate_1: ' . ($row['flow_rate_1'] ?? 'NULL') . "\n";
        }
        echo '</pre>';
        
        // Now run actual import
        echo '<h3>Running actual import...</h3>';
        $import_result = $this->fetch_csv_data(true);
        if (is_array($import_result)) {
            echo '<p><strong>Import Summary:</strong> Inserted: ' . (int)$import_result['imported'] . ', Updated: ' . (int)$import_result['updated'] . ', Skipped: ' . (int)$import_result['skipped'] . '</p>';
        }
        
        // Show what's in database AFTER import
        echo '<h3>Latest records in database (AFTER import):</h3>';
        $latest_after = $wpdb->get_results("SELECT * FROM $table_name ORDER BY timestamp DESC LIMIT 15", ARRAY_A);
        echo '<pre style="background:#f5f5f5; padding:10px; overflow-x:auto;">';
        foreach ($latest_after as $row) {
            echo 'Timestamp: ' . esc_html($row['timestamp']) . ' | ';
            echo 'water_level_1: ' . ($row['water_level_1'] ?? 'NULL') . ' | ';
            echo 'water_level_2: ' . ($row['water_level_2'] ?? 'NULL') . ' | ';
            echo 'flow_rate_1: ' . ($row['flow_rate_1'] ?? 'NULL') . "\n";
        }
        echo '</pre>';
        
        echo '</div>';
    }

}

// Initialize the plugin
// Global instance
$orkla_water_level_instance = new OrklaWaterLevel();
