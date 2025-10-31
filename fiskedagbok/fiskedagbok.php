<?php
/**
 * Plugin Name: Fiskedagbok
 * Plugin URI: https://example.com/fiskedagbok
 * Description: En fiskedagbok for registrerte brukere hvor de kan registrere sine fangster.
 * Version: 1.0.0
 * Author: Ditt Navn
 * License: GPL v2 or later
 * Text Domain: fiskedagbok
 */

// Sikkerhet: Hindre direkte tilgang
if (!defined('ABSPATH')) {
    exit;
}

// Plugin konstanter
define('FISKEDAGBOK_VERSION', '1.0.0');
define('FISKEDAGBOK_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('FISKEDAGBOK_PLUGIN_URL', plugin_dir_url(__FILE__));
if (!defined('ORKLA_PLUGIN_URL')) {
    define('ORKLA_PLUGIN_URL', plugin_dir_url(__FILE__));
}
if (!defined('ORKLA_PLUGIN_PATH')) {
    define('ORKLA_PLUGIN_PATH', plugin_dir_path(__FILE__));
}

/**
 * Hovedklasse for Fiskedagbok plugin
 */
class Fiskedagbok {
    private static $scripts_enqueued = false;
    public $orkla_water_level_plugin;
    
    public function __construct() {
        // Last inn tidewater data klasse
        require_once FISKEDAGBOK_PLUGIN_DIR . 'includes/class-tidewater-data.php';
        
        // Access the global instance of the Orkla Water Level plugin
        global $orkla_water_level_instance;
        if (isset($orkla_water_level_instance) && is_a($orkla_water_level_instance, 'OrklaWaterLevel')) {
            $this->orkla_water_level_plugin = $orkla_water_level_instance;
        } else {
            // Handle the case where the plugin is not active
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error"><p>Orkla Water Level plugin is not active. Please activate it for full functionality.</p></div>';
            });
        }
        
        add_action('init', array($this, 'init'));
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }
    
    /**
     * Initialiserer pluginet
     */
    public function init() {
        // Last inn oversettelser
        load_plugin_textdomain('fiskedagbok', false, dirname(plugin_basename(__FILE__)) . '/languages/');
        
        // Sjekk om vi trenger å kjøre database migrasjoner
        $this->maybe_update_database();
        
        // Initialiser komponenter
        $this->init_hooks();
    }
    
    /**
     * Initialiserer hooks
     */
    private function init_hooks() {
        // Admin hooks
        if (is_admin()) {
            add_action('admin_menu', array($this, 'add_admin_menu'));
            add_action('admin_enqueue_scripts', array($this, 'admin_scripts'));
        }
        
        // Frontend hooks
        add_action('wp_enqueue_scripts', array($this, 'register_scripts'));
        add_action('wp_ajax_submit_catch', array($this, 'handle_catch_submission'));
        add_action('wp_ajax_nopriv_submit_catch', array($this, 'handle_catch_submission'));
        add_action('wp_ajax_get_catch', array($this, 'handle_get_catch'));
        add_action('wp_ajax_update_catch', array($this, 'handle_update_catch'));
        add_action('wp_ajax_delete_catch', array($this, 'handle_delete_catch'));
        add_action('wp_ajax_search_catches_by_name', array($this, 'handle_search_catches_by_name'));
        add_action('wp_ajax_claim_catch', array($this, 'handle_claim_catch'));
        add_action('wp_ajax_get_catch_details', array($this, 'handle_get_catch_details'));
        add_action('wp_ajax_fetch_weather_data', array($this, 'handle_fetch_weather_data'));
        add_action('wp_ajax_refresh_weather_data', array($this, 'handle_refresh_weather_data'));
        add_action('wp_ajax_test_weather_api', array($this, 'handle_test_weather_api'));
        add_action('wp_ajax_filter_catches_by_year', array($this, 'handle_filter_catches_by_year'));
        add_action('wp_ajax_import_csv_to_archive', array($this, 'handle_import_csv_to_archive'));
        add_action('wp_ajax_admin_search_archive', array($this, 'handle_admin_search_archive'));
        add_action('wp_ajax_admin_claim_to_user', array($this, 'handle_admin_claim_to_user'));
        
        // Tidewater data hooks
        add_action('wp_ajax_get_catch_tidewater', array($this, 'handle_get_catch_tidewater'));
        add_action('wp_ajax_nopriv_get_catch_tidewater', array($this, 'handle_get_catch_tidewater'));
        add_action('wp_ajax_get_tide_hours', array($this, 'handle_get_tide_hours'));
        add_action('wp_ajax_nopriv_get_tide_hours', array($this, 'handle_get_tide_hours'));
		add_action('wp_ajax_batch_import_tidewater', array($this, 'handle_batch_import_tidewater'));
		add_action('fiskedagbok_fetch_tidewater_async', array('Fiskedagbok_Tidewater_Data', 'handle_async_tidewater_fetch'), 10, 5);
        
        // Water level data (async handler)
        add_action('wp_ajax_get_catch_water_level', array($this, 'handle_get_catch_water_level'));
        
        // Debug: ultra-fast endpoint for testing
        add_action('wp_ajax_get_catch_details_debug', array($this, 'handle_get_catch_details_debug'));
        
        // Shortcodes
        add_shortcode('fiskedagbok_form', array($this, 'render_catch_form'));
        add_shortcode('fiskedagbok_list', array($this, 'render_catch_list'));
        add_shortcode('fiskedagbok_search', array($this, 'render_catch_search'));
    }
    
    /**
     * Aktivering av plugin - opprett database tabeller
     */
    public function activate() {
        $this->create_database_tables();
        
        // Sett versjon
        update_option('fiskedagbok_version', FISKEDAGBOK_VERSION);
    }
    
    /**
     * Deaktivering av plugin
     */
    public function deactivate() {
        // Rydd opp hvis nødvendig
    }
    
    /**
     * Opprett database tabeller
     */
    private function create_database_tables() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'fiskedagbok_catches';
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id int(11) NOT NULL AUTO_INCREMENT,
            user_id int(11) DEFAULT NULL,
            catch_id varchar(255) DEFAULT NULL,
            date date NOT NULL,
            time_of_day time DEFAULT NULL,
            week int(2) DEFAULT NULL,
            river_id int(11) DEFAULT NULL,
            river_name varchar(255) DEFAULT NULL,
            beat_id int(11) DEFAULT NULL,
            beat_name varchar(255) DEFAULT NULL,
            fishing_spot varchar(255) DEFAULT NULL,
            fish_type varchar(100) NOT NULL,
            equipment varchar(100) DEFAULT NULL,
            fly_lure varchar(100) DEFAULT NULL,
            weight_kg decimal(5,2) DEFAULT NULL,
            length_cm decimal(5,1) DEFAULT NULL,
            released tinyint(1) DEFAULT 0,
            sex enum('male','female','unknown') DEFAULT 'unknown',
            boat varchar(255) DEFAULT NULL,
            fisher_name varchar(255) DEFAULT NULL,
            notes text DEFAULT NULL,
            claimed tinyint(1) DEFAULT 0,
            claimed_by_user_id int(11) DEFAULT NULL,
            claimed_at datetime DEFAULT NULL,
            weather_data text DEFAULT NULL,
            weather_fetched_at datetime DEFAULT NULL,
            water_station_override varchar(100) DEFAULT NULL,
            water_temperature decimal(5,2) DEFAULT NULL,
            water_temperature_source varchar(100) DEFAULT NULL,
            water_temperature_recorded_at datetime DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            platform_reported_from varchar(50) DEFAULT 'web',
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY claimed_by_user_id (claimed_by_user_id),
            KEY date (date),
            KEY fish_type (fish_type),
            KEY fisher_name (fisher_name)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        // Opprett tabell for navn mapping
        $mapping_table = $wpdb->prefix . 'fiskedagbok_name_mappings';
        
        $mapping_sql = "CREATE TABLE $mapping_table (
            id int(11) NOT NULL AUTO_INCREMENT,
            user_id int(11) NOT NULL,
            fisher_name varchar(255) NOT NULL,
            first_name varchar(100) DEFAULT NULL,
            last_name varchar(100) DEFAULT NULL,
            auto_claim tinyint(1) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_mapping (user_id, fisher_name),
            KEY user_id (user_id),
            KEY fisher_name (fisher_name)
        ) $charset_collate;";
        
        dbDelta($mapping_sql);
        
        // Opprett tabell for CSV arkiv (alle importerte fangster før de blir clamet)
        $archive_table = $wpdb->prefix . 'fiskedagbok_csv_archive';
        
        $archive_sql = "CREATE TABLE $archive_table (
            id int(11) NOT NULL AUTO_INCREMENT,
            original_catch_id varchar(255) DEFAULT NULL,
            date date NOT NULL,
            time_of_day time DEFAULT NULL,
            week int(2) DEFAULT NULL,
            river_id int(11) DEFAULT NULL,
            river_name varchar(255) DEFAULT NULL,
            beat_id int(11) DEFAULT NULL,
            beat_name varchar(255) DEFAULT NULL,
            fishing_spot varchar(255) DEFAULT NULL,
            fish_type varchar(100) NOT NULL,
            equipment varchar(100) DEFAULT NULL,
            weight_kg decimal(5,2) DEFAULT NULL,
            length_cm decimal(5,1) DEFAULT NULL,
            released tinyint(1) DEFAULT 0,
            sex enum('male','female','unknown') DEFAULT 'unknown',
            boat varchar(255) DEFAULT NULL,
            fisher_name varchar(255) DEFAULT NULL,
            notes text DEFAULT NULL,
            claimed tinyint(1) DEFAULT 0,
            claimed_by_user_id int(11) DEFAULT NULL,
            claimed_at datetime DEFAULT NULL,
            weather_data text DEFAULT NULL,
            weather_fetched_at datetime DEFAULT NULL,
            imported_at datetime DEFAULT CURRENT_TIMESTAMP,
            original_created_at datetime DEFAULT NULL,
            original_updated_at datetime DEFAULT NULL,
            platform_reported_from varchar(50) DEFAULT 'csv_import',
            PRIMARY KEY (id),
            KEY fisher_name (fisher_name),
            KEY date (date),
            KEY fish_type (fish_type),
            KEY claimed (claimed),
            KEY claimed_by_user_id (claimed_by_user_id),
            KEY original_catch_id (original_catch_id)
        ) $charset_collate;";
        
        dbDelta($archive_sql);
        
        // Opprett tabell for beat vannstand konfiguration
        $beat_config_table = $wpdb->prefix . 'fiskedagbok_beat_water_stations';
        
        $beat_config_sql = "CREATE TABLE $beat_config_table (
            id int(11) NOT NULL AUTO_INCREMENT,
            beat_name varchar(255) NOT NULL,
            water_station_id varchar(100) NOT NULL,
            water_station_name varchar(255) NOT NULL,
            description text DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_beat (beat_name),
            KEY water_station_id (water_station_id)
        ) $charset_collate;";
        
        dbDelta($beat_config_sql);
        
        // Opprett tabell for tidewater data
        $tidewater_table = $wpdb->prefix . 'fiskedagbok_tidewater_data';
        
        $tidewater_sql = "CREATE TABLE $tidewater_table (
            id int(11) NOT NULL AUTO_INCREMENT,
            catch_id int(11) NOT NULL,
            station_name varchar(255) DEFAULT NULL,
            station_code varchar(50) DEFAULT NULL,
            water_level decimal(10,3) DEFAULT NULL,
            is_prediction tinyint(1) DEFAULT 0,
            timestamp datetime DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY catch_id (catch_id),
            KEY station_name (station_name),
            KEY timestamp (timestamp),
            FOREIGN KEY (catch_id) REFERENCES " . $wpdb->prefix . "fiskedagbok_catches(id) ON DELETE CASCADE
        ) $charset_collate;";
        
        dbDelta($tidewater_sql);
        
        // Legg til water_station_override kolonne hvis den ikke eksisterer
        $catches_table = $wpdb->prefix . 'fiskedagbok_catches';
        $column_exists = $wpdb->get_results("SHOW COLUMNS FROM $catches_table LIKE 'water_station_override'");
        
        if (empty($column_exists)) {
            $wpdb->query("ALTER TABLE $catches_table ADD COLUMN water_station_override varchar(100) DEFAULT NULL AFTER weather_fetched_at");
        }
    }
    
    /**
     * Sjekk og kjør database migrasjoner hvis nødvendig
     */
    private function maybe_update_database() {
        global $wpdb;
        
        $catches_table = $wpdb->prefix . 'fiskedagbok_catches';
        
        // Sjekk om water_station_override kolonne eksisterer
        $column_exists = $wpdb->get_results("SHOW COLUMNS FROM $catches_table LIKE 'water_station_override'");
        
        if (empty($column_exists)) {
            // Legg til manglende kolonne
            $result = $wpdb->query("ALTER TABLE $catches_table ADD COLUMN water_station_override varchar(100) DEFAULT NULL AFTER weather_fetched_at");
            
            if ($result !== false) {
                error_log('Fiskedagbok: Added water_station_override column successfully');
            } else {
                error_log('Fiskedagbok: Failed to add water_station_override column: ' . $wpdb->last_error);
            }
        }
        
        // Sjekk om fly_lure kolonne eksisterer
        $fly_lure_exists = $wpdb->get_results("SHOW COLUMNS FROM $catches_table LIKE 'fly_lure'");
        
        if (empty($fly_lure_exists)) {
            // Legg til fly_lure kolonne
            $result = $wpdb->query("ALTER TABLE $catches_table ADD COLUMN fly_lure varchar(100) DEFAULT NULL AFTER equipment");
            
            if ($result !== false) {
                error_log('Fiskedagbok: Added fly_lure column successfully');
            } else {
                error_log('Fiskedagbok: Failed to add fly_lure column: ' . $wpdb->last_error);
            }
        }

        // Sjekk om water_temperature kolonner eksisterer og legg dem til ved behov
        $water_temperature_exists = $wpdb->get_results("SHOW COLUMNS FROM $catches_table LIKE 'water_temperature'");
        if (empty($water_temperature_exists)) {
            $result = $wpdb->query("ALTER TABLE $catches_table ADD COLUMN water_temperature decimal(5,2) DEFAULT NULL AFTER water_station_override");
            if ($result !== false) {
                error_log('Fiskedagbok: Added water_temperature column successfully');
            } else {
                error_log('Fiskedagbok: Failed to add water_temperature column: ' . $wpdb->last_error);
            }
        }

        $water_temperature_source_exists = $wpdb->get_results("SHOW COLUMNS FROM $catches_table LIKE 'water_temperature_source'");
        if (empty($water_temperature_source_exists)) {
            $result = $wpdb->query("ALTER TABLE $catches_table ADD COLUMN water_temperature_source varchar(100) DEFAULT NULL AFTER water_temperature");
            if ($result !== false) {
                error_log('Fiskedagbok: Added water_temperature_source column successfully');
            } else {
                error_log('Fiskedagbok: Failed to add water_temperature_source column: ' . $wpdb->last_error);
            }
        }

        $water_temperature_recorded_at_exists = $wpdb->get_results("SHOW COLUMNS FROM $catches_table LIKE 'water_temperature_recorded_at'");
        if (empty($water_temperature_recorded_at_exists)) {
            $result = $wpdb->query("ALTER TABLE $catches_table ADD COLUMN water_temperature_recorded_at datetime DEFAULT NULL AFTER water_temperature_source");
            if ($result !== false) {
                error_log('Fiskedagbok: Added water_temperature_recorded_at column successfully');
            } else {
                error_log('Fiskedagbok: Failed to add water_temperature_recorded_at column: ' . $wpdb->last_error);
            }
        }

        // Sjekk om tidewater_data tabell eksisterer
        $tidewater_table = $wpdb->prefix . 'fiskedagbok_tidewater_data';
        $tidewater_exists = $wpdb->get_var("SHOW TABLES LIKE '$tidewater_table'") === $tidewater_table;

        if (!$tidewater_exists) {
            $this->create_database_tables();
            error_log('Fiskedagbok: Created missing tidewater_data table');
        }
    }
    
    /**
     * Legg til admin meny
     */
    public function add_admin_menu() {
        add_menu_page(
            'Fiskedagbok',
            'Fiskedagbok',
            'manage_options',
            'fiskedagbok',
            array($this, 'admin_page'),
            'dashicons-location-alt',
            30
        );
        
        add_submenu_page(
            'fiskedagbok',
            'Mine Fangster',
            'Mine Fangster',
            'manage_options',
            'fiskedagbok',
            array($this, 'admin_page')
        );

        add_submenu_page(
            'fiskedagbok',
            'Alle Fangster',
            'Alle Fangster',
            'manage_options',
            'fiskedagbok-all-catches',
            array($this, 'admin_all_catches_page')
        );
        

        add_submenu_page(
            'fiskedagbok',
            'Importer tidevannsdata',
            'Importer tidevannsdata',
            'manage_options',
            'import-tide-file',
            function() {
                require_once FISKEDAGBOK_PLUGIN_DIR . 'admin/import-tide-file.php';
                fiskedagbok_admin_import_tide_file_page();
            }
        );

        add_submenu_page(
            'fiskedagbok',
            'Import CSV',
            'Import CSV',
            'manage_options',
            'fiskedagbok-import',
            array($this, 'import_page')
        );
        
        add_submenu_page(
            'fiskedagbok',
            'CSV Arkiv',
            'CSV Arkiv',
            'manage_options',
            'fiskedagbok-archive',
            array($this, 'archive_page')
        );
        
        add_submenu_page(
            'fiskedagbok',
            'Søk og Klaim',
            'Søk og Klaim',
            'manage_options',
            'fiskedagbok-admin-search',
            array($this, 'admin_search_page')
        );
        
        add_submenu_page(
            'fiskedagbok',
            'Database Reparasjon',
            'Database Reparasjon',
            'manage_options',
            'fiskedagbok-repair',
            array($this, 'repair_database_page')
        );
        
        add_submenu_page(
            'fiskedagbok',
            'Værdata API',
            'Værdata API',
            'manage_options',
            'fiskedagbok-weather-settings',
            array($this, 'weather_settings_page')
        );

        add_submenu_page(
            'fiskedagbok',
            'Beat Vannstand',
            'Beat Vannstand',
            'manage_options',
            'fiskedagbok-beat-config',
            array($this, 'beat_config_page')
        );

        add_submenu_page(
            'fiskedagbok',
            'Tidevannsdata',
            'Tidevannsdata',
            'manage_options',
            'fiskedagbok-tidewater',
            array($this, 'tidewater_admin_page')
        );
    }
    
    /**
     * Admin side - hovedside
     */
    public function admin_page() {
        $fiskedagbok_admin_scope = 'mine';
        include FISKEDAGBOK_PLUGIN_DIR . 'admin/admin-page.php';
    }

    public function admin_all_catches_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Du har ikke tilgang til å se alle fangstene.', 'fiskedagbok'));
        }

        $fiskedagbok_admin_scope = 'all';
        include FISKEDAGBOK_PLUGIN_DIR . 'admin/admin-page.php';
    }
    
    /**
     * Admin side - import
     */
    public function import_page() {
        include FISKEDAGBOK_PLUGIN_DIR . 'admin/import-page.php';
    }
    
    public function weather_settings_page() {
        include FISKEDAGBOK_PLUGIN_DIR . 'admin/weather-settings-page.php';
    }

    /**
     * Admin side - CSV arkiv
     */
    public function archive_page() {
        include FISKEDAGBOK_PLUGIN_DIR . 'admin/archive-page.php';
    }
    
    /**
     * Admin side - søk og klaim
     */
    public function admin_search_page() {
        include FISKEDAGBOK_PLUGIN_DIR . 'admin/admin-search-page.php';
    }
    
    /**
     * Admin side - database reparasjon
     */
    public function repair_database_page() {
        // Håndter repair request
        if (isset($_POST['repair_tables']) && check_admin_referer('fiskedagbok_repair', 'repair_nonce')) {
            $this->create_database_tables();
            echo '<div class="notice notice-success"><p>Database-tabeller ble opprettet/reparert!</p></div>';
        }
        
        global $wpdb;
        
        // Sjekk hvilke tabeller som eksisterer
        $tables_to_check = [
            'fiskedagbok_catches',
            'fiskedagbok_name_mappings', 
            'fiskedagbok_csv_archive',
            'fiskedagbok_beat_water_stations'
        ];
        
        $missing_tables = [];
        foreach ($tables_to_check as $table) {
            $full_table_name = $wpdb->prefix . $table;
            $exists = $wpdb->get_var("SHOW TABLES LIKE '$full_table_name'");
            if (!$exists) {
                $missing_tables[] = $table;
            }
        }
        
        ?>
        <div class="wrap">
            <h1>Database Reparasjon</h1>
            
            <?php if (empty($missing_tables)): ?>
                <div class="notice notice-success">
                    <p>✓ Alle nødvendige database-tabeller eksisterer!</p>
                </div>
            <?php else: ?>
                <div class="notice notice-warning">
                    <p>⚠️ Følgende tabeller mangler:</p>
                    <ul>
                        <?php foreach ($missing_tables as $table): ?>
                            <li><code><?php echo esc_html($wpdb->prefix . $table); ?></code></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            
            <form method="post">
                <?php wp_nonce_field('fiskedagbok_repair', 'repair_nonce'); ?>
                <p>
                    <input type="submit" name="repair_tables" class="button button-primary" 
                           value="Opprett/Reparer Database-tabeller" />
                </p>
                <p class="description">
                    Dette vil opprette alle nødvendige database-tabeller for Fiskedagbok-pluginet.
                </p>
            </form>
            
            <h2>Database Status</h2>
            <table class="widefat">
                <thead>
                    <tr>
                        <th>Tabell</th>
                        <th>Status</th>
                        <th>Antall rader</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tables_to_check as $table): ?>
                        <?php 
                        $full_table_name = $wpdb->prefix . $table;
                        $exists = $wpdb->get_var("SHOW TABLES LIKE '$full_table_name'");
                        $count = $exists ? $wpdb->get_var("SELECT COUNT(*) FROM $full_table_name") : 0;
                        ?>
                        <tr>
                            <td><code><?php echo esc_html($full_table_name); ?></code></td>
                            <td>
                                <?php if ($exists): ?>
                                    <span style="color: green;">✓ Eksisterer</span>
                                <?php else: ?>
                                    <span style="color: red;">✗ Mangler</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo number_format($count); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
    
    /**
     * Admin side - beat vannstand konfiguration
     */
    public function beat_config_page() {
        include FISKEDAGBOK_PLUGIN_DIR . 'admin/beat-config-page.php';
    }

    /**
     * Admin side - tidevannsdata
     */
    public function tidewater_admin_page() {
        global $wpdb;
        
        // Sjekk permissions
        if (!current_user_can('manage_options')) {
            wp_die('Ingen tilgang');
        }

        // Handle manual refresh
        $refresh_message = null;
        if (isset($_GET['refresh_tidewater']) && wp_verify_nonce($_GET['_wpnonce'], 'fiskedagbok_tidewater_refresh')) {
            $refresh_message = $this->refresh_all_tidewater_data();
        }

        // Get statistics
        $tidewater_table = $wpdb->prefix . 'fiskedagbok_tidewater_data';
        $catches_table = $wpdb->prefix . 'fiskedagbok_catches';
        $archive_table = $wpdb->prefix . 'fiskedagbok_csv_archive';

        // DEBUG: Check table structure
        $catches_count = $wpdb->get_var("SELECT COUNT(*) FROM $catches_table");
        $archive_count = $wpdb->get_var("SELECT COUNT(*) FROM $archive_table");
        $total_in_db = $catches_count + $archive_count;
        
        error_log('DEBUG Tidewater: Catches table: ' . $catches_count . ', Archive table: ' . $archive_count . ', Total: ' . $total_in_db);

        // Total records
        $total_records = (int) $wpdb->get_var("SELECT COUNT(*) FROM $tidewater_table");

        // Total catches in database - INCLUDE BOTH catches and archive
        $total_catches = (int) $total_in_db;

        // Latest record
        $latest_record = $wpdb->get_row("
            SELECT td.*, c.date as catch_date, c.river_name
            FROM $tidewater_table td
            LEFT JOIN $catches_table c ON td.catch_id = c.id
            ORDER BY td.timestamp DESC
            LIMIT 1
        ");

        // Records by station
        $by_station = $wpdb->get_results("
            SELECT station_name, COUNT(*) as count, MAX(timestamp) as last_update
            FROM $tidewater_table
            GROUP BY station_name
            ORDER BY count DESC
        ");

        // Catches with tidewater data
        $catches_with_data = (int) $wpdb->get_var("
            SELECT COUNT(DISTINCT catch_id) FROM $tidewater_table
        ");

        // Debug: Log the totals
        error_log('Fiskedagbok Tidewater Admin - Total catches: ' . $total_catches . ', With data: ' . $catches_with_data . ', Total records: ' . $total_records);

        // Today's catches with tidewater data
        $todays_catches = (int) $wpdb->get_var(
            $wpdb->prepare("
                SELECT COUNT(DISTINCT td.catch_id)
                FROM $tidewater_table td
                LEFT JOIN $catches_table c ON td.catch_id = c.id
                WHERE DATE(c.date) = %s
            ", current_time('Y-m-d'))
        );

        include FISKEDAGBOK_PLUGIN_DIR . 'admin/tidewater-page.php';
    }

    /**
     * Refresh tidewater data for all catches
     */
    private function refresh_all_tidewater_data() {
        global $wpdb;
        
        $catches_table = $wpdb->prefix . 'fiskedagbok_catches';
        $archive_table = $wpdb->prefix . 'fiskedagbok_csv_archive';
        $tidewater_table = $wpdb->prefix . 'fiskedagbok_tidewater_data';

        // Get last 7 days of catches without tidewater data from BOTH tables
        $catches = $wpdb->get_results("
            SELECT * FROM (
                SELECT id, date
                FROM $catches_table
                WHERE date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                AND id NOT IN (SELECT DISTINCT catch_id FROM $tidewater_table)
                UNION
                SELECT id, date
                FROM $archive_table
                WHERE date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                AND id NOT IN (SELECT DISTINCT catch_id FROM $tidewater_table)
            ) combined
            ORDER BY date DESC
        ");

        if (empty($catches)) {
            return array(
                'status' => 'info',
                'message' => 'Alle fangster fra siste 7 dager har allerede tidevannsdata.'
            );
        }

        $queued = 0;
        $default_station = Fiskedagbok_Tidewater_Data::get_default_station();

        foreach ($catches as $catch) {
            Fiskedagbok_Tidewater_Data::queue_tidewater_fetch(
                $catch->id,
                $default_station['latitude'],
                $default_station['longitude'],
                $catch->date,
                isset($default_station['station_code']) ? $default_station['station_code'] : null
            );
            $queued++;
        }

        return array(
            'status' => 'success',
            'message' => sprintf('Køet henting av tidevannsdata for %d fangster', $queued)
        );
    }
    
    /**
     * Last inn admin scripts
     */
    public function admin_scripts($hook) {
        if (strpos($hook, 'fiskedagbok') !== false) {
            wp_enqueue_script('fiskedagbok-admin', FISKEDAGBOK_PLUGIN_URL . 'assets/js/admin.js', array('jquery'), FISKEDAGBOK_VERSION, true);
            wp_enqueue_style('fiskedagbok-admin', FISKEDAGBOK_PLUGIN_URL . 'assets/css/admin.css', array(), FISKEDAGBOK_VERSION);
        }
    }

    public function register_scripts() {
        wp_register_script('fiskedagbok-frontend', FISKEDAGBOK_PLUGIN_URL . 'assets/js/frontend.js', array('jquery'), FISKEDAGBOK_VERSION, true);
        wp_register_style('fiskedagbok-frontend', FISKEDAGBOK_PLUGIN_URL . 'assets/css/frontend.css', array(), FISKEDAGBOK_VERSION);
    }
    
    /**
     * Last inn frontend scripts
     */
    public function frontend_scripts() {
        global $post;
        if ( is_a( $post, 'WP_Post' ) && ( has_shortcode( $post->post_content, 'fiskedagbok_form' ) || has_shortcode( $post->post_content, 'fiskedagbok_list' ) || has_shortcode( $post->post_content, 'fiskedagbok_search' ) ) ) {
            wp_enqueue_script('fiskedagbok-frontend', FISKEDAGBOK_PLUGIN_URL . 'assets/js/frontend.js', array('jquery'), FISKEDAGBOK_VERSION, true);
            wp_enqueue_style('fiskedagbok-frontend', FISKEDAGBOK_PLUGIN_URL . 'assets/css/frontend.css', array(), FISKEDAGBOK_VERSION);
            
            // Lokaliser script for AJAX
            wp_localize_script('fiskedagbok-frontend', 'fiskedagbok_ajax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('fiskedagbok_nonce')
            ));
        }
    }
    
    /**
     * Håndter fangst innsending via AJAX
     */
    public function handle_catch_submission() {
        // Verifiser nonce
        if (!wp_verify_nonce($_POST['nonce'], 'fiskedagbok_nonce')) {
            wp_die('Sikkerhetsfeil');
        }
        
        // Sjekk at bruker er logget inn
        if (!is_user_logged_in()) {
            wp_send_json_error('Du må være logget inn for å registrere fangster');
            return;
        }
        
        // Valider og lagre data
        $result = $this->save_catch($_POST);
        
        if ($result) {
            wp_send_json_success('Fangst registrert!');
        } else {
            wp_send_json_error('Feil ved lagring av fangst');
        }
    }
    
    /**
     * Lagre fangst til database
     */
    private function save_catch($data) {
        global $wpdb;
        
        $user_id = get_current_user_id();
        $current_user = wp_get_current_user();
        
        $catch_data = array(
            'user_id' => $user_id,
            'date' => sanitize_text_field($data['date']),
            'time_of_day' => sanitize_text_field($data['time_of_day']),
            'week' => intval($data['week']),
            'river_name' => sanitize_text_field($data['river_name']),
            'beat_name' => sanitize_text_field($data['beat_name']),
            'fishing_spot' => sanitize_text_field($data['fishing_spot']),
            'fish_type' => sanitize_text_field($data['fish_type']),
            'equipment' => sanitize_text_field($data['equipment']),
            'fly_lure' => sanitize_text_field($data['fly_lure']),
            'weight_kg' => floatval($data['weight_kg']),
            'length_cm' => floatval($data['length_cm']),
            'released' => isset($data['released']) ? 1 : 0,
            'sex' => sanitize_text_field($data['sex']),
            'fisher_name' => $current_user->display_name,
            'notes' => sanitize_textarea_field($data['notes']),
            'platform_reported_from' => 'web'
        );
        
        $table_name = $wpdb->prefix . 'fiskedagbok_catches';
        
        $result = $wpdb->insert($table_name, $catch_data);

        if ($result) {
            // Hent catch ID som ble opprettet
            $catch_id = $wpdb->insert_id;

            // Queue tidewater data fetch from nearest Kartverket station
            $default_station = Fiskedagbok_Tidewater_Data::get_default_station();
            $catch_date = sanitize_text_field($data['date']);

            Fiskedagbok_Tidewater_Data::queue_tidewater_fetch(
                $catch_id,
                $default_station['latitude'],
                $default_station['longitude'],
                $catch_date,
                isset($default_station['station_code']) ? $default_station['station_code'] : null
            );
            error_log('Fiskedagbok: Queued tidewater fetch for catch ' . $catch_id . ' from ' . $default_station['name']);
        }

        return $result;
    }
    
    /**
     * Shortcode for fangst skjema
     */
    public function render_catch_form($atts) {
        wp_enqueue_script('fiskedagbok-frontend');
        wp_enqueue_style('fiskedagbok-frontend');

        if (!self::$scripts_enqueued) {
            wp_localize_script('fiskedagbok-frontend', 'fiskedagbok_ajax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('fiskedagbok_nonce')
            ));
            self::$scripts_enqueued = true;
        }

        if (!is_user_logged_in()) {
            return '<p>Du må være logget inn for å registrere fangster. <a href="' . wp_login_url(get_permalink()) . '">Logg inn her</a>.</p>';
        }
        
        ob_start();
        include FISKEDAGBOK_PLUGIN_DIR . 'templates/catch-form.php';
        return ob_get_clean();
    }
    
    /**
     * Shortcode for fangst liste
     */
    public function render_catch_list($atts) {
        wp_enqueue_script('fiskedagbok-frontend');
        wp_enqueue_style('fiskedagbok-frontend');

        if (!self::$scripts_enqueued) {
            wp_localize_script('fiskedagbok-frontend', 'fiskedagbok_ajax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('fiskedagbok_nonce')
            ));
            self::$scripts_enqueued = true;
        }

        if (!is_user_logged_in()) {
            return '<p>Du må være logget inn for å se dine fangster. <a href="' . wp_login_url(get_permalink()) . '">Logg inn her</a>.</p>';
        }
        
        $user_id = get_current_user_id();
        $selected_year = isset($_GET['year']) ? intval($_GET['year']) : null;
        
        $catches = $this->get_user_catches($user_id, 0, $selected_year);
        $available_years = $this->get_user_catch_years($user_id);
        
        ob_start();
        include FISKEDAGBOK_PLUGIN_DIR . 'templates/catch-list.php';
        return ob_get_clean();
    }
    
    /**
     * Håndter henting av enkelt fangst for redigering
     */
    public function handle_get_catch() {
        if (!wp_verify_nonce($_POST['nonce'], 'fiskedagbok_nonce')) {
            wp_die('Sikkerhetsfeil');
        }
        
        if (!is_user_logged_in()) {
            wp_send_json_error('Du må være logget inn');
            return;
        }
        
        $catch_id = intval($_POST['catch_id']);
        $user_id = get_current_user_id();
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'fiskedagbok_catches';
        
        $catch = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE id = %d AND user_id = %d",
            $catch_id,
            $user_id
        ));
        
        if ($catch) {
            wp_send_json_success($catch);
        } else {
            wp_send_json_error('Fangst ikke funnet');
        }
    }
    
    /**
     * Håndter oppdatering av fangst
     */
    public function handle_update_catch() {
        if (!wp_verify_nonce($_POST['nonce'], 'fiskedagbok_nonce')) {
            wp_die('Sikkerhetsfeil');
        }
        
        if (!is_user_logged_in()) {
            wp_send_json_error('Du må være logget inn');
            return;
        }
        
        $catch_id = intval($_POST['catch_id']);
        $user_id = get_current_user_id();
        
        $catch_data = array(
            'date' => sanitize_text_field($_POST['date']),
            'time_of_day' => sanitize_text_field($_POST['time_of_day']),
            'fish_type' => sanitize_text_field($_POST['fish_type']),
            'weight_kg' => floatval($_POST['weight_kg']),
            'length_cm' => floatval($_POST['length_cm']),
            'released' => isset($_POST['released']) ? 1 : 0,
            'river_name' => sanitize_text_field($_POST['river_name']),
            'beat_name' => sanitize_text_field($_POST['beat_name']),
            'fishing_spot' => sanitize_text_field($_POST['fishing_spot']),
            'equipment' => sanitize_text_field($_POST['equipment']),
            'fly_lure' => sanitize_text_field($_POST['fly_lure']),
            'sex' => sanitize_text_field($_POST['sex']),
            'notes' => sanitize_textarea_field($_POST['notes']),
            'water_station_override' => !empty($_POST['water_station_override']) ? sanitize_text_field($_POST['water_station_override']) : null,
            'updated_at' => current_time('mysql')
        );
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'fiskedagbok_catches';
        
        $result = $wpdb->update(
            $table_name,
            $catch_data,
            array('id' => $catch_id, 'user_id' => $user_id)
        );
        
        if ($result !== false) {
            wp_send_json_success('Fangst oppdatert');
        } else {
            wp_send_json_error('Feil ved oppdatering');
        }
    }
    
    /**
     * Håndter sletting av fangst
     */
    public function handle_delete_catch() {
        if (!wp_verify_nonce($_POST['nonce'], 'fiskedagbok_nonce')) {
            wp_die('Sikkerhetsfeil');
        }
        
        if (!is_user_logged_in()) {
            wp_send_json_error('Du må være logget inn');
            return;
        }
        
        $catch_id = intval($_POST['catch_id']);
        $user_id = get_current_user_id();
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'fiskedagbok_catches';
        
        $result = $wpdb->delete(
            $table_name,
            array('id' => $catch_id, 'user_id' => $user_id)
        );
        
        if ($result) {
            wp_send_json_success('Fangst slettet');
        } else {
            wp_send_json_error('Feil ved sletting');
        }
    }
    
    /**
     * Shortcode for fangst søk
     */
    public function render_catch_search($atts) {
        wp_enqueue_script('fiskedagbok-frontend');
        wp_enqueue_style('fiskedagbok-frontend');

        if (!self::$scripts_enqueued) {
            wp_localize_script('fiskedagbok-frontend', 'fiskedagbok_ajax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('fiskedagbok_nonce')
            ));
            self::$scripts_enqueued = true;
        }

        if (!is_user_logged_in()) {
            return '<p>Du må være logget inn for å søke etter fangster. <a href="' . wp_login_url(get_permalink()) . '">Logg inn her</a>.</p>';
        }
        
        ob_start();
        include FISKEDAGBOK_PLUGIN_DIR . 'templates/catch-search.php';
        return ob_get_clean();
    }
    
    /**
     * Håndter søk etter fangster basert på navn
     */
    public function handle_search_catches_by_name() {
        if (!wp_verify_nonce($_POST['nonce'], 'fiskedagbok_nonce')) {
            wp_die('Sikkerhetsfeil');
        }
        
        if (!is_user_logged_in()) {
            wp_send_json_error('Du må være logget inn');
            return;
        }
        
        $search_name = sanitize_text_field($_POST['search_name']);
        $current_user_id = get_current_user_id();
        
        if (empty($search_name)) {
            wp_send_json_error('Navn er påkrevet');
            return;
        }
        
        global $wpdb;
        $archive_table = $wpdb->prefix . 'fiskedagbok_csv_archive';
        
        // Søk i CSV arkiv etter fangster som matcher navnet og som ikke allerede er clamet av brukeren
        $catches = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $archive_table 
             WHERE fisher_name LIKE %s 
             AND (claimed = 0 OR claimed_by_user_id != %d)
             ORDER BY date DESC 
             LIMIT 50",
            '%' . $wpdb->esc_like($search_name) . '%',
            $current_user_id
        ));
        
        if (empty($catches)) {
            wp_send_json_error('Ingen fangster funnet for dette navnet i arkivet');
            return;
        }
        
        wp_send_json_success($catches);
    }
    
    /**
     * Håndter klaiming av fangst
     */
    public function handle_claim_catch() {
        if (!wp_verify_nonce($_POST['nonce'], 'fiskedagbok_nonce')) {
            wp_die('Sikkerhetsfeil');
        }
        
        if (!is_user_logged_in()) {
            wp_send_json_error('Du må være logget inn');
            return;
        }
        
        $catch_id = intval($_POST['catch_id']);
        $user_id = get_current_user_id();
        $current_user = wp_get_current_user();
        
        global $wpdb;
        $archive_table = $wpdb->prefix . 'fiskedagbok_csv_archive';
        $catches_table = $wpdb->prefix . 'fiskedagbok_catches';
        
        // Hent fangsten fra arkivet
        $archive_catch = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $archive_table WHERE id = %d AND (claimed = 0 OR claimed_by_user_id != %d)",
            $catch_id,
            $user_id
        ));
        
        if (!$archive_catch) {
            wp_send_json_error('Fangst ikke funnet eller allerede clamet');
            return;
        }
        
        // Kopier fangsten til hovedtabellen
        $catch_data = array(
            'user_id' => $user_id,
            'catch_id' => $archive_catch->original_catch_id,
            'date' => $archive_catch->date,
            'time_of_day' => $archive_catch->time_of_day,
            'week' => $archive_catch->week,
            'river_id' => $archive_catch->river_id,
            'river_name' => $archive_catch->river_name,
            'beat_id' => $archive_catch->beat_id,
            'beat_name' => $archive_catch->beat_name,
            'fishing_spot' => $archive_catch->fishing_spot,
            'fish_type' => $archive_catch->fish_type,
            'equipment' => $archive_catch->equipment,
            'weight_kg' => $archive_catch->weight_kg,
            'length_cm' => $archive_catch->length_cm,
            'released' => $archive_catch->released,
            'sex' => $archive_catch->sex,
            'boat' => $archive_catch->boat,
            'fisher_name' => $archive_catch->fisher_name,
            'notes' => $archive_catch->notes,
            'weather_data' => $archive_catch->weather_data,
            'weather_fetched_at' => $archive_catch->weather_fetched_at,
            'created_at' => $archive_catch->original_created_at ? $archive_catch->original_created_at : current_time('mysql'),
            'updated_at' => current_time('mysql'),
            'platform_reported_from' => 'claimed_from_archive'
        );
        
        $result = $wpdb->insert($catches_table, $catch_data);
        
        if ($result) {
            // Marker som clamet i arkivet
            $wpdb->update(
                $archive_table,
                array(
                    'claimed' => 1,
                    'claimed_by_user_id' => $user_id,
                    'claimed_at' => current_time('mysql')
                ),
                array('id' => $catch_id)
            );
            
            // Lagre navnemapping
            $this->save_name_mapping($user_id, $archive_catch->fisher_name);
            
            wp_send_json_success('Fangst lagt til i din fiskedagbok');
        } else {
            wp_send_json_error('Feil ved claiming av fangst');
        }
    }
    
    /**
     * Lagre navn mapping for automatisk claiming
     */
    private function save_name_mapping($user_id, $fisher_name) {
        global $wpdb;
        
        $mapping_table = $wpdb->prefix . 'fiskedagbok_name_mappings';
        
        // Sjekk om mapping allerede eksisterer
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $mapping_table WHERE user_id = %d AND fisher_name = %s",
            $user_id,
            $fisher_name
        ));
        
        if (!$existing) {
            // Forsøk å parse fornavn og etternavn
            $name_parts = explode(' ', trim($fisher_name));
            $first_name = $name_parts[0];
            $last_name = isset($name_parts[1]) ? implode(' ', array_slice($name_parts, 1)) : '';
            
            $wpdb->insert(
                $mapping_table,
                array(
                    'user_id' => $user_id,
                    'fisher_name' => $fisher_name,
                    'first_name' => $first_name,
                    'last_name' => $last_name,
                    'auto_claim' => 0
                )
            );
        }
    }
    
    /**
     * Håndter henting av detaljert fangstinfo
     */
    /**
     * Hent tidevannsdata for en fangst
     */
    public function handle_get_catch_tidewater() {
        if (!wp_verify_nonce($_POST['nonce'], 'fiskedagbok_nonce')) {
            wp_send_json_error('Sikkerhetsfeil');
        }

        $catch_id = intval($_POST['catch_id']);

        if (!$catch_id) {
            wp_send_json_error('Manglende catch_id');
            return;
        }

        global $wpdb;
        $catches_table = $wpdb->prefix . 'fiskedagbok_catches';
        $tidewater_table = 'wpjd_fiskedagbok_tidewater_data';

        // Get the date and time of the catch
        $catch = $wpdb->get_row($wpdb->prepare(
            "SELECT date, time_of_day FROM $catches_table WHERE id = %d",
            $catch_id
        ));

        if (!$catch) {
            wp_send_json_error('Kunne ikke finne fangstdato.');
            return;
        }

        $catch_time = $catch->date . ' ' . ($catch->time_of_day ?: '00:00:00');

        // Fetch the closest tidewater data to the catch time
        $closest_tidewater = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $tidewater_table 
             ORDER BY ABS(TIMESTAMPDIFF(SECOND, timestamp, %s)) ASC 
             LIMIT 1",
            $catch_time
        ));

        // Log the raw result of the query for debugging
        error_log("Raw Closest Tidewater Data: " . print_r($closest_tidewater, true));

        if (!$closest_tidewater) {
            wp_send_json_error('Ingen tidevannsdata funnet for denne fangsten.');
            return;
        }

        // Check if the found data is reasonably close to the catch time
        $catch_ts = strtotime($catch_time);
        $tide_ts = strtotime($closest_tidewater->timestamp);
        $max_diff_seconds = 30 * 24 * 60 * 60; // 30 days

        if (abs($catch_ts - $tide_ts) > $max_diff_seconds) {
            $error_message = sprintf(
                'Nærmeste tidevannsdata (%s) er mer enn 30 dager unna fangstdatoen (%s).',
                date('d.m.Y', $tide_ts),
                date('d.m.Y', $catch_ts)
            );
            wp_send_json_error($error_message);
            return;
        }

        // Format data for frontend
        $formatted_data = array(
            'timestamp' => $closest_tidewater->timestamp,
            'water_level' => (float) $closest_tidewater->water_level,
            'is_prediction' => (bool) $closest_tidewater->is_prediction,
            'station_name' => $closest_tidewater->station_name,
            'station_code' => $closest_tidewater->station_code,
        );

        // Log the fetched tidewater data for debugging
        error_log("Fetched Tidewater Data: " . print_r($formatted_data, true));

        wp_send_json_success($formatted_data);
    }

    /**
     * Get hours to next high/low tide for a catch
     */
    public function handle_get_tide_hours() {
        if (!wp_verify_nonce($_POST['nonce'], 'fiskedagbok_nonce')) {
            wp_send_json_error('Sikkerhetsfeil');
        }

        $catch_id = intval($_POST['catch_id']);

        if (!$catch_id) {
            wp_send_json_error('Manglende catch_id');
            return;
        }

        // Get catch data to use catch time
        global $wpdb;
        $catches_table = $wpdb->prefix . 'fiskedagbok_catches';
        $catch = $wpdb->get_row($wpdb->prepare(
            "SELECT date, time_of_day FROM $catches_table WHERE id = %d",
            $catch_id
        ));

        if (!$catch) {
            wp_send_json_error('Fangst ikke funnet');
            return;
        }

        // Use time_of_day if available, otherwise use midnight
        $reference_time = $catch->date . ' 00:00:00';
        if ($catch->time_of_day) {
            $reference_time = $catch->date . ' ' . $catch->time_of_day;
        }

        $tide_info = Fiskedagbok_Tidewater_Data::calculate_hours_to_extreme_tide($reference_time);

        if (is_wp_error($tide_info)) {
            wp_send_json_error($tide_info->get_error_message());
            return;
        }

        wp_send_json_success($tide_info);
    }

    /**
     * Batch import av tidevannsdata for alle gamle fangster
     */
    public function handle_batch_import_tidewater() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Ingen tilgang');
            return;
        }

        if (!wp_verify_nonce($_POST['nonce'], 'fiskedagbok_admin_nonce')) {
            wp_send_json_error('Sikkerhetsfeil');
            return;
        }

        global $wpdb;
        $catches_table = $wpdb->prefix . 'fiskedagbok_catches';
        $archive_table = $wpdb->prefix . 'fiskedagbok_csv_archive';
        $tidewater_table = $wpdb->prefix . 'fiskedagbok_tidewater_data';

        // Get limit for batch (default 50, max 100)
    $limit = isset($_POST['limit']) ? intval($_POST['limit']) : 50;
    $limit = min($limit, 200); // Allow larger batches for faster rebuilds, but keep an upper bound

        // Check if we should refresh all data or just missing
        $refresh_all = isset($_POST['refresh_all']) && $_POST['refresh_all'] === 'true';
        $job_token = isset($_POST['job_token']) ? sanitize_text_field($_POST['job_token']) : '';

        error_log('Fiskedagbok: handle_batch_import_tidewater - refresh_all=' . ($refresh_all ? 'true' : 'false') . ', limit=' . $limit . ', token=' . ($job_token ?: 'new'));

        $total_to_process = null;
        $processed = null;
        $remaining = null;
        $job_started_at = null;
        $job_state = null;
        $job_state_option = 'fiskedagbok_refresh_all_job_state';

        if ($refresh_all) {
            $job_state = get_transient($job_state_option);

            if (empty($job_token) || !is_array($job_state) || empty($job_state['token']) || $job_state['token'] !== $job_token) {
                $job_token = wp_generate_uuid4();
                $job_state = array(
                    'token' => $job_token,
                    'started_at' => current_time('mysql'),
                    'total' => (int) $wpdb->get_var("SELECT COUNT(*) FROM $catches_table") + (int) $wpdb->get_var("SELECT COUNT(*) FROM $archive_table"),
                    'processed' => 0,
                );
                set_transient($job_state_option, $job_state, DAY_IN_SECONDS);
                error_log('Fiskedagbok: refresh_all - started new job token=' . $job_token . ', total=' . $job_state['total']);
            }

            $job_started_at = $job_state['started_at'];
            $total_to_process = (int) $job_state['total'];

            $catches = $wpdb->get_results($wpdb->prepare("
                SELECT * FROM (
                    SELECT id, date FROM $catches_table
                    UNION
                    SELECT id, date FROM $archive_table
                ) combined
                WHERE combined.id NOT IN (
                    SELECT DISTINCT catch_id 
                    FROM $tidewater_table 
                    WHERE created_at >= %s
                )
                ORDER BY date DESC, id DESC
                LIMIT %d
            ", $job_started_at, $limit));

            error_log('Fiskedagbok: refresh_all job=' . $job_token . ' - fetched ' . count($catches) . ' catches');
        } else {
            // Get catches WITHOUT tidewater data from BOTH tables
            // Order by ID ascending so we process them in a predictable order and move forward
            $catches = $wpdb->get_results($wpdb->prepare("
                SELECT * FROM (
                    SELECT id, date FROM $catches_table WHERE id NOT IN (SELECT DISTINCT catch_id FROM $tidewater_table)
                    UNION
                    SELECT id, date FROM $archive_table WHERE id NOT IN (SELECT DISTINCT catch_id FROM $tidewater_table)
                ) combined
                ORDER BY id ASC
                LIMIT %d
            ", $limit));
            $remaining_query = "
                SELECT 
                    (SELECT COUNT(*) FROM $catches_table WHERE id NOT IN (SELECT DISTINCT catch_id FROM $tidewater_table))
                    +
                    (SELECT COUNT(*) FROM $archive_table WHERE id NOT IN (SELECT DISTINCT catch_id FROM $tidewater_table))
            ";
            error_log('Fiskedagbok: missing - caught ' . count($catches) . ' catches, IDs: ' . implode(',', array_map(function($c) { return $c->id; }, $catches)));
        }

        if (empty($catches)) {
            error_log('Fiskedagbok: No catches to process, marking complete');

            if ($refresh_all) {
                $processed = (int) $wpdb->get_var($wpdb->prepare("
                    SELECT COUNT(DISTINCT catch_id)
                    FROM $tidewater_table
                    WHERE created_at >= %s
                ", $job_started_at));
                $remaining = max(0, $total_to_process - $processed);

                if ($remaining <= 0) {
                    delete_transient($job_state_option);
                } else {
                    $job_state['processed'] = $processed;
                    set_transient($job_state_option, $job_state, DAY_IN_SECONDS);
                }
            }

            wp_send_json_success(array(
                'status' => 'complete',
                'message' => $refresh_all ? 'Alle fangster oppdatert!' : 'Alle fangster har tidevannsdata!',
                'queued' => 0,
                'remaining' => $remaining ?? 0,
                'processed' => $refresh_all ? $processed : null,
                'total' => $refresh_all ? $total_to_process : null,
                'job_token' => $refresh_all ? $job_token : null,
            ));
            return;
        }

        // Queue tidewater fetch for all catches
        $queued = 0;
        $default_station = Fiskedagbok_Tidewater_Data::get_default_station();

        foreach ($catches as $catch) {
            // If refresh_all, delete old data first
            if ($refresh_all) {
                $wpdb->delete($tidewater_table, array('catch_id' => $catch->id), array('%d'));
                error_log('Fiskedagbok: Deleted old data for catch ' . $catch->id);
            }
            
            // IMMEDIATELY process the tidewater fetch (don't rely on WP-Cron)
            Fiskedagbok_Tidewater_Data::handle_async_tidewater_fetch(
                $catch->id,
                $default_station['latitude'],
                $default_station['longitude'],
                $catch->date
            );
            $queued++;
        }

        if ($refresh_all) {
            $processed = (int) $wpdb->get_var($wpdb->prepare("
                SELECT COUNT(DISTINCT catch_id)
                FROM $tidewater_table
                WHERE created_at >= %s
            ", $job_started_at));
            $remaining = max(0, $total_to_process - $processed);

            $job_state['processed'] = $processed;
            set_transient($job_state_option, $job_state, DAY_IN_SECONDS);

            if ($remaining <= 0) {
                delete_transient($job_state_option);
            }

            error_log('Fiskedagbok: refresh_all job=' . $job_token . ' - processed=' . $processed . ', remaining=' . $remaining);
        } else {
            // Get remaining count
            $remaining = (int) $wpdb->get_var($remaining_query);
        }

        error_log('Fiskedagbok: Processed ' . $queued . ' catches, remaining=' . $remaining);

        wp_send_json_success(array(
            'status' => $remaining > 0 ? 'partial' : 'complete',
            'queued' => $queued,
            'remaining' => $remaining,
            'processed' => $refresh_all ? $processed : null,
            'total' => $refresh_all ? $total_to_process : null,
            'job_token' => $refresh_all ? $job_token : null,
            'message' => $refresh_all
                ? sprintf('Oppdaterte %d fangster denne runden. %d av %d gjenstår.', $queued, $remaining, $total_to_process)
                : sprintf('Køet %d fangster. %d gjenstår.', $queued, $remaining)
        ));
    }

    public function handle_get_catch_details() {
        $start_time = microtime(true);
        
        if (!wp_verify_nonce($_POST['nonce'], 'fiskedagbok_nonce')) {
            wp_die('Sikkerhetsfeil');
        }
        
        if (!is_user_logged_in()) {
            wp_send_json_error('Du må være logget inn');
            return;
        }
        
        $catch_id = intval($_POST['catch_id']);
        error_log("CATCH DETAILS DEBUG: Starting handle_get_catch_details for catch_id: $catch_id");
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'fiskedagbok_catches';
        
        // OPTIMIZATION: SELECT ONLY needed columns to minimize data transfer and parsing
        $catch = $wpdb->get_row($wpdb->prepare(
            "SELECT id, fish_type, weight_kg, length_cm, sex, released, date, time_of_day, 
                    river_name, beat_name, fishing_spot, week, equipment, boat, notes, weather_data FROM $table_name WHERE id = %d",
            $catch_id
        ));
        
        $query_time = microtime(true) - $start_time;
        error_log("CATCH DETAILS DEBUG: Database query took " . round($query_time * 1000, 2) . "ms");
        
        // Parse weather data if it exists
        $catch->weather = !empty($catch->weather_data) ? json_decode($catch->weather_data, true) : null;
        
        $total_time = microtime(true) - $start_time;
        error_log("CATCH DETAILS DEBUG: Total time for catch $catch_id: " . round($total_time * 1000, 2) . "ms");
        error_log("CATCH DETAILS DEBUG: Returning catch details for id: $catch_id (fast mode - async handlers load water level and tidewater)");
        
        // OPTIMIZATION: Return minimal data quickly
        // Water level and tidewater will be loaded asynchronously by frontend
        wp_send_json_success($catch);
    }

    /**
     * ASYNC: Hent vannstand data for fangst (separate AJAX handler)
     * Called asynchronously after modal is displayed to avoid timeout
     * Uses fast_mode=true to skip database writes and reduce latency
     */
    public function handle_get_catch_water_level() {
        if (!wp_verify_nonce($_POST['nonce'], 'fiskedagbok_nonce')) {
            wp_send_json_error('Sikkerhetsfeil');
            return;
        }
        
        if (!is_user_logged_in()) {
            wp_send_json_error('Du må være logget inn');
            return;
        }
        
        $catch_id = intval($_POST['catch_id']);
        error_log("VANNSTAND DEBUG: Starting async handle_get_catch_water_level for catch_id: $catch_id");
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'fiskedagbok_catches';
        
        $catch = $wpdb->get_row($wpdb->prepare(
            "SELECT beat_name, date, id, time_of_day, water_station_override FROM $table_name WHERE id = %d",
            $catch_id
        ));
        
        if (!$catch) {
            wp_send_json_error('Fangst ikke funnet');
            return;
        }
        
        // Hent vannstand data for beat (fast_mode = skip database writes)
        if (!empty($catch->beat_name) && !empty($catch->date)) {
            $water_level = $this->get_water_level_for_catch($catch->beat_name, $catch->date, $catch->id, $catch->time_of_day, true);
            wp_send_json_success($water_level);
        } else {
            wp_send_json_error('Mangler beat_name eller date');
        }
    }
    
    /**
     * Hent værdata fra frost.met.no for en spesifikk dato
     */
    private function fetch_weather_for_date($date, $time_of_day = null) {
        $log_context = $time_of_day ? $date . ' ' . $time_of_day : $date;
        error_log("WEATHER DEBUG: Starting weather fetch for date/time: $log_context");

        $target_timestamp = $this->get_target_timestamp($date, $time_of_day);
        $current_timestamp = current_time('timestamp', true);
        $historical_threshold = defined('HOUR_IN_SECONDS') ? HOUR_IN_SECONDS * 6 : 21600;
        $is_historical = $target_timestamp < ($current_timestamp - $historical_threshold);

        $frost_attempted = false;

        try {
            if ($is_historical) {
                error_log('WEATHER DEBUG: Requested time is historical, trying Frost first');
                $weather_data = $this->fetch_weather_from_frost($date, $time_of_day);
                $frost_attempted = true;
                if ($weather_data) {
                    error_log("WEATHER DEBUG: Successfully got data from frost.met.no for date/time: $log_context");
                    return $weather_data;
                }
            }

            // Test med yr.no API (gir nåværende/prognose)
            $weather_data = $this->fetch_weather_from_yr($date, $time_of_day, $target_timestamp);
            if ($weather_data) {
                error_log("WEATHER DEBUG: Successfully got data from yr.no for date/time: $log_context");
                return $weather_data;
            }

            // Prøv Frost dersom det ikke ble forsøkt tidligere eller hvis første forsøk feilet
            if (!$frost_attempted) {
                $weather_data = $this->fetch_weather_from_frost($date, $time_of_day);
                if ($weather_data) {
                    error_log("WEATHER DEBUG: Successfully got data from frost.met.no for date/time: $log_context");
                    return $weather_data;
                }
            }

        } catch (Exception $e) {
            error_log('WEATHER DEBUG: Exception in weather fetch: ' . $e->getMessage());
        }

        error_log("WEATHER DEBUG: Using fallback data for date/time: $log_context");
        // Fallback til simulerte data hvis API feiler
        return $this->get_fallback_weather_data($date);
    }

    /**
     * Konverter ønsket fangstdato og tid til UTC timestamp.
     */
    private function get_target_timestamp($date, $time_of_day = null) {
        $time_part = $time_of_day ?: '12:00:00';

        // Forsøk å bruke WordPress sin tidsone hvis tilgjengelig
        $timezone = null;
        if (function_exists('wp_timezone')) {
            $timezone = wp_timezone();
        }

        if (!$timezone) {
            $timezone_string = get_option('timezone_string');
            if (!empty($timezone_string)) {
                try {
                    $timezone = new DateTimeZone($timezone_string);
                } catch (Exception $e) {
                    // Ignorer og fall tilbake senere
                }
            }
        }

        if (!$timezone) {
            $timezone = new DateTimeZone('UTC');
        }

        try {
            $date_time = new DateTime(trim($date . ' ' . $time_part), $timezone);
        } catch (Exception $e) {
            $date_time = new DateTime($date . ' 12:00:00', $timezone);
        }

        $date_time->setTimezone(new DateTimeZone('UTC'));

        return $date_time->getTimestamp();
    }
    
    /**
     * Hent værdata fra yr.no (enklere API)
     */
    private function fetch_weather_from_yr($date, $time_of_day = null, $target_timestamp = null) {
        // Yr.no API for historiske data (Meldal/Orkland område)
        $lat = 63.048;  // Meldal latitude
        $lon = 9.713;   // Meldal longitude
        
        // yr.no API for værdata
        $api_url = "https://api.met.no/weatherapi/locationforecast/2.0/compact";
        $url = "$api_url?lat=$lat&lon=$lon";
        
        error_log("WEATHER DEBUG: Trying yr.no API: $url");
        
        $response = wp_remote_get($url, array(
            'timeout' => 15,
            'headers' => array(
                'User-Agent' => 'FiskedagbokPlugin/1.0 (+https://example.com/contact)'
            )
        ));
        
        if (is_wp_error($response)) {
            error_log('WEATHER DEBUG: yr.no API error: ' . $response->get_error_message());
            return null;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            error_log('WEATHER DEBUG: yr.no API HTTP error: ' . $response_code);
            return null;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (!$data || !isset($data['properties']['timeseries'])) {
            error_log('WEATHER DEBUG: yr.no API returned invalid data');
            return null;
        }
        
        if ($target_timestamp === null) {
            $target_timestamp = $this->get_target_timestamp($date, $time_of_day);
        }

        $closest_entry = null;
        $closest_diff = PHP_INT_MAX;

        foreach ($data['properties']['timeseries'] as $entry) {
            if (empty($entry['time'])) {
                continue;
            }

            $entry_timestamp = strtotime($entry['time']);
            if ($entry_timestamp === false) {
                continue;
            }

            $diff = abs($entry_timestamp - $target_timestamp);
            if ($diff < $closest_diff) {
                $closest_diff = $diff;
                $closest_entry = $entry;
            }
        }

        if (!$closest_entry) {
            error_log('WEATHER DEBUG: No matching entry from yr.no timeseries');
            return null;
        }

        $max_diff_seconds = defined('HOUR_IN_SECONDS') ? HOUR_IN_SECONDS * 6 : 21600;
        if ($closest_diff > $max_diff_seconds) {
            error_log('WEATHER DEBUG: Closest yr.no entry is ' . round($closest_diff / 3600, 2) . ' hours away from requested time - skipping yr.no data');
            return null;
        }

        $instant_details = $closest_entry['data']['instant']['details'] ?? null;
        if (!$instant_details) {
            error_log('WEATHER DEBUG: Selected yr.no entry lacks instant details');
            return null;
        }

        // Prioriter nedbør i neste time, deretter neste 6/12 timer om tilgjengelig
        $precipitation = null;
        if (isset($closest_entry['data']['next_1_hours']['details']['precipitation_amount'])) {
            $precipitation = $closest_entry['data']['next_1_hours']['details']['precipitation_amount'];
        } elseif (isset($closest_entry['data']['next_6_hours']['details']['precipitation_amount'])) {
            $precipitation = $closest_entry['data']['next_6_hours']['details']['precipitation_amount'];
        } elseif (isset($closest_entry['data']['next_12_hours']['details']['precipitation_amount'])) {
            $precipitation = $closest_entry['data']['next_12_hours']['details']['precipitation_amount'];
        }

        return array(
            'temperature' => round($instant_details['air_temperature'] ?? 15, 1),
            'precipitation' => isset($precipitation) ? round($precipitation, 1) : null,
            'wind_speed' => isset($instant_details['wind_speed']) ? round($instant_details['wind_speed'], 1) : null,
            'wind_direction' => isset($instant_details['wind_from_direction']) ? round($instant_details['wind_from_direction']) : null,
            'source' => 'yr.no (met.no)',
            'station' => 'Meldal/Orkland (63.048°N, 9.713°E)',
            'date' => date('Y-m-d', strtotime($date)),
            'observation_time' => $closest_entry['time'],
            'note' => 'Forecast data - historic accuracy limited'
        );
    }
    
    /**
     * Hent værdata fra frost.met.no (krever registrering)
     */
    private function fetch_weather_from_frost($date, $time_of_day = null) {
        try {
            $credentials = $this->get_frost_credentials();
            if (!$credentials) {
                error_log('WEATHER DEBUG: Frost credentials missing - skipping Frost API');
                return null;
            }

            // Frost.met.no API endepunkt for værdata
            $api_url = 'https://frost.met.no/observations/v0.jsonld';
            
            // Flere værstasjoner nær Meldal/Orkland (Trøndelag) - prøv flere hvis første feiler
            $stations = array(
                'SN68860', // Orkdal (nærmest Meldal/Orkland)
                'SN68340', // Melhus 
                'SN68050', // Trondheim Voll
                'SN68560', // Rindal
                'SN69100'  // Oppdal
            );
            
            // Formater dato for API (fra og til samme dag)
            $date_formatted = date('Y-m-d', strtotime($date));
            $date_start = $date_formatted . 'T00:00:00.000Z';
            $date_end = date('Y-m-d', strtotime($date . ' +1 day')) . 'T00:00:00.000Z';
            
            foreach ($stations as $station_id) {
                // Bygg API-spørring
                $params = array(
                    'sources' => $station_id,
                    'referencetime' => $date_start . '/' . $date_end,
                    'elements' => 'air_temperature,precipitation_amount,wind_speed,wind_from_direction'
                );
                
                $url = $api_url . '?' . http_build_query($params);
                
                error_log("WEATHER DEBUG: Trying Frost API with station $station_id for date $date: $url");
                
                // Hent data fra API med WordPress HTTP API
                $auth_header = base64_encode($credentials['client_id'] . ':' . $credentials['client_secret']);

                $response = wp_remote_get($url, array(
                    'timeout' => 15,
                    'headers' => array(
                        'User-Agent' => 'FiskedagbokPlugin/1.0 wordpress-plugin (kontakt@example.com)',
                        'Authorization' => 'Basic ' . $auth_header
                    )
                ));
                
                if (is_wp_error($response)) {
                    error_log('WEATHER DEBUG: Frost API error for station ' . $station_id . ': ' . $response->get_error_message());
                    continue;
                }
                
                $response_code = wp_remote_retrieve_response_code($response);
                if ($response_code !== 200) {
                    error_log('WEATHER DEBUG: Frost API HTTP error for station ' . $station_id . ': ' . $response_code);
                    continue;
                }
                
                $body = wp_remote_retrieve_body($response);
                $data = json_decode($body, true);
                
                if (!$data) {
                    error_log('WEATHER DEBUG: Frost API invalid JSON for station ' . $station_id . ': ' . substr($body, 0, 200));
                    continue;
                }
                
                if (isset($data['error'])) {
                    error_log('WEATHER DEBUG: Frost API error response for station ' . $station_id . ': ' . print_r($data['error'], true));
                    continue;
                }
                
                if (!isset($data['data']) || empty($data['data'])) {
                    error_log('WEATHER DEBUG: Frost API returned no data for station ' . $station_id . ' and date: ' . $date);
                    continue;
                }
                
                // Parse værdata fra API
                $weather_data = $this->parse_frost_weather_data($data['data'], $date_formatted, $time_of_day);
                
                if ($weather_data) {
                    $weather_data['source'] = 'frost.met.no';
                    $weather_data['station'] = $this->get_station_name($station_id);
                    $weather_data['date'] = $date_formatted;
                    error_log("WEATHER DEBUG: Successfully got weather data from station $station_id for date $date");
                    return $weather_data;
                }
            }
            
            error_log("WEATHER DEBUG: No weather data found from any Frost station for date: $date");
            
        } catch (Exception $e) {
            error_log('WEATHER DEBUG: Frost API exception: ' . $e->getMessage());
        }
        
        return null;
    }
    
    /**
     * Parse værdata fra frost.met.no API
     */
    private function parse_frost_weather_data($api_data, $date, $time_of_day = null) {
        $target_timestamp = $this->get_target_timestamp($date, $time_of_day);

        $best_match = null;
        $best_diff = PHP_INT_MAX;

        foreach ($api_data as $observation_set) {
            if (empty($observation_set['observations']) || empty($observation_set['referenceTime'])) {
                continue;
            }

            $reference_time = $observation_set['referenceTime'];
            $reference_timestamp = strtotime($reference_time);
            if ($reference_timestamp === false) {
                continue;
            }

            $values = array();
            foreach ($observation_set['observations'] as $obs) {
                if (!isset($obs['elementId']) || !isset($obs['value'])) {
                    continue;
                }
                $values[$obs['elementId']] = floatval($obs['value']);
            }

            if (!isset($values['air_temperature'])) {
                continue;
            }

            $diff = abs($reference_timestamp - $target_timestamp);
            if ($diff < $best_diff) {
                $best_diff = $diff;
                $best_match = array(
                    'timestamp' => $reference_timestamp,
                    'referenceTime' => $reference_time,
                    'values' => $values
                );
            }
        }

        if (!$best_match) {
            return null;
        }

        $values = $best_match['values'];

        return array(
            'temperature' => round($values['air_temperature'], 1),
            'precipitation' => isset($values['precipitation_amount']) ? round($values['precipitation_amount'], 1) : null,
            'wind_speed' => isset($values['wind_speed']) ? round($values['wind_speed'], 1) : null,
            'wind_direction' => isset($values['wind_from_direction']) ? round($values['wind_from_direction']) : null,
            'observation_time' => $best_match['referenceTime']
        );
    }

    /**
     * Hent lagrede Frost API legitimasjon.
     */
    private function get_frost_credentials() {
        $client_id = get_option('fiskedagbok_frost_client_id');
        $client_secret = get_option('fiskedagbok_frost_client_secret');

        if (!empty($client_id) && !empty($client_secret)) {
            return array(
                'client_id' => $client_id,
                'client_secret' => $client_secret
            );
        }

        return null;
    }
    
    /**
     * Få stasjonsnavn fra ID
     */
    private function get_station_name($station_id) {
        $station_names = array(
            'SN68860' => 'Orkdal værstasjon',
            'SN68050' => 'Trondheim Voll',
            'SN69100' => 'Oppdal',
            'SN68560' => 'Rindal'
        );
        
        return isset($station_names[$station_id]) ? $station_names[$station_id] : 'Ukjent stasjon (' . $station_id . ')';
    }
    
    /**
     * Fallback værdata når API ikke er tilgjengelig
     */
    private function get_fallback_weather_data($date) {
        $timestamp = strtotime($date);
        $month = date('n', $timestamp);
        $day = date('j', $timestamp);
        
        // Simuler værdata basert på årstid og tilfeldig variasjon
        $base_temp = $this->get_seasonal_temperature($month);
        $temp_variation = (($day % 10) - 5) * 2;
        $temperature = $base_temp + $temp_variation;
        
        $precipitation = ($day % 7 == 0) ? rand(0, 20) : rand(0, 5);
        $wind_speed = rand(2, 15);
        $wind_direction = rand(0, 359);
        
        return array(
            'temperature' => round($temperature, 1),
            'precipitation' => $precipitation,
            'wind_speed' => round($wind_speed, 1),
            'wind_direction' => $wind_direction,
            'source' => 'Simulert data',
            'station' => 'Meldal/Orkland (simulert)',
            'note' => 'Frost.met.no API ikke tilgjengelig - bruker simulerte data'
        );
    }
    
    /**
     * Hjelpefunksjon for å få sesongbasert temperatur
     */
    private function get_seasonal_temperature($month) {
        // Typiske temperaturer for Norge per måned
        $seasonal_temps = array(
            1 => -5,   // Januar
            2 => -3,   // Februar  
            3 => 2,    // Mars
            4 => 8,    // April
            5 => 15,   // Mai
            6 => 20,   // Juni
            7 => 22,   // Juli
            8 => 21,   // August
            9 => 16,   // September
            10 => 10,  // Oktober
            11 => 3,   // November
            12 => -2   // Desember
        );
        
        return isset($seasonal_temps[$month]) ? $seasonal_temps[$month] : 10;
    }
    
    /**
     * Håndter manuell værdata henting
     */
    public function handle_fetch_weather_data() {
        if (!wp_verify_nonce($_POST['nonce'], 'fiskedagbok_nonce')) {
            wp_die('Sikkerhetsfeil');
        }
        
        if (!is_user_logged_in()) {
            wp_send_json_error('Du må være logget inn');
            return;
        }
        
        $catch_id = intval($_POST['catch_id']);
        $date = sanitize_text_field($_POST['date']);

        global $wpdb;
        $table_name = $wpdb->prefix . 'fiskedagbok_catches';

        $catch_row = $wpdb->get_row($wpdb->prepare(
            "SELECT time_of_day FROM $table_name WHERE id = %d",
            $catch_id
        ));

        $time_of_day = $catch_row ? $catch_row->time_of_day : null;

        $weather_data = $this->fetch_weather_for_date($date, $time_of_day);

        if ($weather_data) {
            $wpdb->update(
                $table_name,
                array(
                    'weather_data' => json_encode($weather_data),
                    'weather_fetched_at' => current_time('mysql')
                ),
                array('id' => $catch_id)
            );
            
            wp_send_json_success($weather_data);
        } else {
            wp_send_json_error('Kunne ikke hente værdata');
        }
    }
    
    /**
     * Håndter CSV import til arkiv
     */
    public function handle_import_csv_to_archive() {
        // Debug logging
        error_log('CSV Import started. POST data: ' . print_r($_POST, true));
        error_log('FILES data: ' . print_r($_FILES, true));
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Ikke tilgang');
            return;
        }
        
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'fiskedagbok_admin_nonce')) {
            wp_send_json_error('Sikkerhetsfeil - ugyldig nonce');
            return;
        }
        
        if (!isset($_FILES['csv_file']) || empty($_FILES['csv_file']['tmp_name'])) {
            wp_send_json_error('Ingen fil valgt eller fil ikke lastet opp');
            return;
        }
        
        $csv_file = $_FILES['csv_file'];
        
        // Sjekk for upload-feil
        if ($csv_file['error'] !== UPLOAD_ERR_OK) {
            wp_send_json_error('Feil ved fil-upload: ' . $csv_file['error']);
            return;
        }
        
        // Valider filtype
        $file_ext = pathinfo($csv_file['name'], PATHINFO_EXTENSION);
        if (strtolower($file_ext) !== 'csv') {
            wp_send_json_error('Kun CSV-filer er tillatt');
            return;
        }
        
        // Sjekk at filen er lesbar
        if (!is_readable($csv_file['tmp_name'])) {
            wp_send_json_error('Kan ikke lese opplastet fil');
            return;
        }
        
        // Les CSV-fil med feilhåndtering
        $csv_content = file($csv_file['tmp_name']);
        if ($csv_content === false) {
            wp_send_json_error('Kunne ikke lese CSV-fil');
            return;
        }
        
        $csv_data = array_map(function($line) {
            return str_getcsv($line, ',', '"', '\\');
        }, $csv_content);
        if (empty($csv_data)) {
            wp_send_json_error('CSV-fil er tom eller ugyldig format');
            return;
        }
        
        $header = array_shift($csv_data);
        if (empty($header)) {
            wp_send_json_error('CSV-fil mangler header');
            return;
        }
        
        error_log('CSV header: ' . print_r($header, true));
        error_log('CSV data rows: ' . count($csv_data));
        
        global $wpdb;
        $archive_table = $wpdb->prefix . 'fiskedagbok_csv_archive';
        
        $imported = 0;
        $errors = 0;
        $duplicates = 0;
        $error_details = array();
        
        foreach ($csv_data as $row_index => $row) {
            if (count($row) !== count($header)) {
                $errors++;
                $error_details[] = "Rad " . ($row_index + 2) . ": Feil antall kolonner";
                continue;
            }
            
            $data = array_combine($header, $row);
            
            // Sjekk for duplikater basert på original_catch_id
            if (!empty($data['catch_id'])) {
                $existing = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM $archive_table WHERE original_catch_id = %s",
                    $data['catch_id']
                ));
                
                if ($existing > 0) {
                    $duplicates++;
                    continue;
                }
            }
            
            // Forbered data for arkiv-tabell med bedre validering
            $archive_data = array(
                'original_catch_id' => isset($data['catch_id']) ? sanitize_text_field($data['catch_id']) : null,
                'date' => isset($data['date']) ? sanitize_text_field($data['date']) : null,
                'time_of_day' => isset($data['time_of_day']) && !empty($data['time_of_day']) ? sanitize_text_field($data['time_of_day']) : null,
                'week' => isset($data['week']) && is_numeric($data['week']) ? intval($data['week']) : null,
                'river_id' => isset($data['river_id']) && is_numeric($data['river_id']) ? intval($data['river_id']) : null,
                'river_name' => isset($data['river_name']) ? sanitize_text_field($data['river_name']) : null,
                'beat_id' => isset($data['beat_id']) && is_numeric($data['beat_id']) ? intval($data['beat_id']) : null,
                'beat_name' => isset($data['beat_name']) ? sanitize_text_field($data['beat_name']) : null,
                'fishing_spot' => isset($data['fishing_spot']) ? sanitize_text_field($data['fishing_spot']) : null,
                'fish_type' => isset($data['fish_type']) ? sanitize_text_field($data['fish_type']) : 'Ukjent',
                'equipment' => isset($data['equipment']) ? sanitize_text_field($data['equipment']) : null,
                'weight_kg' => isset($data['weight_kg']) && !empty($data['weight_kg']) && is_numeric($data['weight_kg']) ? floatval($data['weight_kg']) : null,
                'length_cm' => isset($data['length_cm']) && !empty($data['length_cm']) && is_numeric($data['length_cm']) ? floatval($data['length_cm']) : null,
                'released' => isset($data['released']) && ($data['released'] === 'True' || $data['released'] === '1' || $data['released'] === 'true') ? 1 : 0,
                'sex' => isset($data['sex']) && in_array($data['sex'], array('male', 'female', 'unknown')) ? $data['sex'] : 'unknown',
                'boat' => isset($data['boat']) ? sanitize_text_field($data['boat']) : null,
                'fisher_name' => isset($data['fisher_name']) ? sanitize_text_field($data['fisher_name']) : null,
                'original_created_at' => isset($data['created_at']) ? sanitize_text_field($data['created_at']) : null,
                'original_updated_at' => isset($data['updated_at']) ? sanitize_text_field($data['updated_at']) : null,
                'platform_reported_from' => isset($data['platform_reported_from']) ? sanitize_text_field($data['platform_reported_from']) : 'csv_import'
            );
            
            // Valider at påkrevde felter finnes
            if (empty($archive_data['date']) || empty($archive_data['fish_type'])) {
                $errors++;
                $error_details[] = "Rad " . ($row_index + 2) . ": Mangler påkrevde felter (dato eller fisketype)";
                continue;
            }
            
            $result = $wpdb->insert($archive_table, $archive_data);
            
            if ($result) {
                $imported++;
            } else {
                $errors++;
                $error_details[] = "Rad " . ($row_index + 2) . ": Database-feil - " . $wpdb->last_error;
            }
        }
        
        $message = array(
            'imported' => $imported,
            'errors' => $errors,
            'duplicates' => $duplicates,
            'total' => count($csv_data),
            'error_details' => array_slice($error_details, 0, 10) // Vis kun første 10 feil
        );
        
        wp_send_json_success($message);
    }
    
    /**
     * Håndter admin søk i arkiv
     */
    public function handle_admin_search_archive() {
        if (!current_user_can('manage_options')) {
            wp_die('Ikke tilgang');
        }
        
        if (!wp_verify_nonce($_POST['nonce'], 'fiskedagbok_admin_nonce')) {
            wp_die('Sikkerhetsfeil');
        }
        
        $search_name = sanitize_text_field($_POST['search_name']);
        
        if (empty($search_name)) {
            wp_send_json_error('Navn er påkrevet');
            return;
        }
        
        global $wpdb;
        $archive_table = $wpdb->prefix . 'fiskedagbok_csv_archive';
        
        $catches = $wpdb->get_results($wpdb->prepare(
            "SELECT a.*, u.display_name as claimed_by_name 
             FROM $archive_table a 
             LEFT JOIN {$wpdb->users} u ON a.claimed_by_user_id = u.ID
             WHERE a.fisher_name LIKE %s 
             ORDER BY a.date DESC",
            '%' . $wpdb->esc_like($search_name) . '%'
        ));
        
        if (empty($catches)) {
            wp_send_json_error('Ingen fangster funnet');
            return;
        }
        
        wp_send_json_success($catches);
    }
    
    /**
     * Håndter admin klaiming til bruker
     */
    public function handle_admin_claim_to_user() {
        if (!current_user_can('manage_options')) {
            wp_die('Ikke tilgang');
        }
        
        if (!wp_verify_nonce($_POST['nonce'], 'fiskedagbok_admin_nonce')) {
            wp_die('Sikkerhetsfeil');
        }
        
        $catch_id = intval($_POST['catch_id']);
        $user_id = intval($_POST['user_id']);
        
        if (!$catch_id || !$user_id) {
            wp_send_json_error('Manglende data');
            return;
        }
        
        // Sjekk at brukeren eksisterer
        $user = get_user_by('ID', $user_id);
        if (!$user) {
            wp_send_json_error('Bruker ikke funnet');
            return;
        }
        
        global $wpdb;
        $archive_table = $wpdb->prefix . 'fiskedagbok_csv_archive';
        $catches_table = $wpdb->prefix . 'fiskedagbok_catches';
        
        // Hent fangsten fra arkivet
        $archive_catch = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $archive_table WHERE id = %d",
            $catch_id
        ));
        
        if (!$archive_catch) {
            wp_send_json_error('Fangst ikke funnet');
            return;
        }
        
        // Kopier til hovedtabellen samme måte som vanlig claim
        $catch_data = array(
            'user_id' => $user_id,
            'catch_id' => $archive_catch->original_catch_id,
            'date' => $archive_catch->date,
            'time_of_day' => $archive_catch->time_of_day,
            'week' => $archive_catch->week,
            'river_id' => $archive_catch->river_id,
            'river_name' => $archive_catch->river_name,
            'beat_id' => $archive_catch->beat_id,
            'beat_name' => $archive_catch->beat_name,
            'fishing_spot' => $archive_catch->fishing_spot,
            'fish_type' => $archive_catch->fish_type,
            'equipment' => $archive_catch->equipment,
            'weight_kg' => $archive_catch->weight_kg,
            'length_cm' => $archive_catch->length_cm,
            'released' => $archive_catch->released,
            'sex' => $archive_catch->sex,
            'boat' => $archive_catch->boat,
            'fisher_name' => $archive_catch->fisher_name,
            'notes' => $archive_catch->notes,
            'weather_data' => $archive_catch->weather_data,
            'weather_fetched_at' => $archive_catch->weather_fetched_at,
            'created_at' => $archive_catch->original_created_at ? $archive_catch->original_created_at : current_time('mysql'),
            'updated_at' => current_time('mysql'),
            'platform_reported_from' => 'admin_claimed'
        );
        
        $result = $wpdb->insert($catches_table, $catch_data);
        
        if ($result) {
            // Marker som clamet i arkivet
            $wpdb->update(
                $archive_table,
                array(
                    'claimed' => 1,
                    'claimed_by_user_id' => $user_id,
                    'claimed_at' => current_time('mysql')
                ),
                array('id' => $catch_id)
            );
            
            // Lagre navnemapping
            $this->save_name_mapping($user_id, $archive_catch->fisher_name);
            
            wp_send_json_success('Fangst tildelt ' . $user->display_name);
        } else {
            wp_send_json_error('Feil ved tildeling av fangst');
        }
    }
    
    /**
     * Hent brukerens fangster
     */
    public function get_user_catches($user_id, $limit = 0, $year = null) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'fiskedagbok_catches';
        
        $where_clause = "WHERE user_id = %d";
        $params = array($user_id);
        
        if ($year) {
            $where_clause .= " AND YEAR(date) = %d";
            $params[] = $year;
        }
        
        $limit_clause = $limit > 0 ? "LIMIT %d" : "";
        if ($limit > 0) {
            $params[] = $limit;
        }
        
        $sql = "SELECT * FROM $table_name $where_clause ORDER BY date DESC, time_of_day DESC $limit_clause";
        
        $catches = $wpdb->get_results($wpdb->prepare($sql, $params));
        
        // Prosesser værdata for hver fangst
        foreach ($catches as $catch) {
            // Parse værdata fra JSON string til objekt
            $catch->weather = !empty($catch->weather_data) ? json_decode($catch->weather_data, true) : null;
        }
        
        return $catches;
    }
    
    /**
     * Hent tilgjengelige år for brukerens fangster
     */
    public function get_user_catch_years($user_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'fiskedagbok_catches';
        
        $years = $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT YEAR(date) as year FROM $table_name WHERE user_id = %d ORDER BY year DESC",
            $user_id
        ));
        
        return $years;
    }
    
    /**
     * Håndter oppdatering av værdata
     */
    public function handle_refresh_weather_data() {
        if (!wp_verify_nonce($_POST['nonce'], 'fiskedagbok_nonce')) {
            wp_send_json_error('Ugyldig nonce');
            return;
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Ingen tilgang');
            return;
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'fiskedagbok_catches';
        
        $catch_id = isset($_POST['catch_id']) ? intval($_POST['catch_id']) : null;
        $specific_date = isset($_POST['date']) ? sanitize_text_field($_POST['date']) : null;
        $force_update = isset($_POST['force_update']) && $_POST['force_update'] === 'true';
        $batch_size = isset($_POST['batch_size']) ? intval($_POST['batch_size']) : 50;
        $offset = isset($_POST['offset']) ? intval($_POST['offset']) : 0;
        $include_water = !isset($_POST['include_water']) || $_POST['include_water'] !== 'false';

        if ($batch_size <= 0) {
            $batch_size = 50;
        }

        if ($offset < 0) {
            $offset = 0;
        }
        
        if ($catch_id && $specific_date) {
            // Oppdater kun en spesifikk fangst
            error_log("WEATHER DEBUG: Updating weather for specific catch $catch_id on date $specific_date");
            
            $catch_row = $wpdb->get_row($wpdb->prepare(
                "SELECT time_of_day, beat_name FROM $table_name WHERE id = %d",
                $catch_id
            ));

            $time_of_day = $catch_row ? $catch_row->time_of_day : null;

            $weather_data = $this->fetch_weather_for_date($specific_date, $time_of_day);
            
            if ($weather_data) {
                $result = $wpdb->update(
                    $table_name,
                    array(
                        'weather_data' => json_encode($weather_data),
                        'weather_fetched_at' => current_time('mysql')
                    ),
                    array('id' => $catch_id),
                    array('%s', '%s'),
                    array('%d')
                );
                
                if ($result !== false) {
                    error_log("WEATHER DEBUG: Successfully updated database for catch $catch_id with new weather data");
                    $water_status = null;
                    $water_updated = 0;
                    $water_errors = 0;

                    if ($include_water && $catch_row && !empty($catch_row->beat_name)) {
                        $water_info = $this->get_water_level_for_catch($catch_row->beat_name, $specific_date, $catch_id, $time_of_day);
                        if (is_array($water_info) && isset($water_info['water_data']['temperature_persist_status'])) {
                            $water_status = $water_info['water_data']['temperature_persist_status'];
                            if ($water_status === 'updated') {
                                $water_updated = 1;
                            } elseif ($water_status === 'failed') {
                                $water_errors = 1;
                            }
                        }
                    }
                    
                    wp_send_json_success(array(
                        'updated' => 1,
                        'errors' => 0,
                        'total_processed' => 1,
                        'water_updated' => $water_updated,
                        'water_errors' => $water_errors,
                        'water_status' => $water_status,
                        'has_more' => false,
                        'total_candidates' => 1,
                        'next_offset' => $offset,
                        'message' => 'Værdata oppdatert for fangst'
                    ));
                } else {
                    error_log("WEATHER DEBUG: Database update failed for catch $catch_id. Error: " . $wpdb->last_error);
                    wp_send_json_error('Kunne ikke oppdatere værdata i database: ' . $wpdb->last_error);
                }
            } else {
                error_log("WEATHER DEBUG: Failed to fetch weather data for date $specific_date");
                wp_send_json_error('Kunne ikke hente værdata for datoen ' . $specific_date);
            }
            return;
        }
        
        $updated = 0;
        $errors = 0;
        $water_updated = 0;
        $water_errors = 0;
        $total_candidates = 0;
        $catches = array();

        if ($force_update) {
            $total_candidates = (int) $wpdb->get_var("SELECT COUNT(*) FROM $table_name");

            if ($total_candidates > 0) {
                $catches = $wpdb->get_results($wpdb->prepare(
                    "SELECT id, date, time_of_day, beat_name FROM $table_name ORDER BY date DESC LIMIT %d OFFSET %d",
                    $batch_size,
                    $offset
                ));
            }
        } else {
            $where_clause = "WHERE weather_data IS NULL OR weather_fetched_at < DATE_SUB(NOW(), INTERVAL 30 DAY)";
            $total_candidates = (int) $wpdb->get_var("SELECT COUNT(*) FROM $table_name $where_clause");

            if ($total_candidates > 0) {
                $catches = $wpdb->get_results($wpdb->prepare(
                    "SELECT id, date, time_of_day, beat_name FROM $table_name $where_clause ORDER BY date DESC LIMIT %d OFFSET %d",
                    $batch_size,
                    $offset
                ));
            }
        }

        if (empty($catches)) {
            $has_more = false;
            $next_offset = $offset;

            if ($force_update && $total_candidates > 0 && $offset < $total_candidates) {
                $next_offset = $total_candidates;
            }

            wp_send_json_success(array(
                'updated' => 0,
                'errors' => 0,
                'water_updated' => 0,
                'water_errors' => 0,
                'total_processed' => 0,
                'total_candidates' => $total_candidates,
                'has_more' => $has_more,
                'next_offset' => $next_offset,
                'message' => $total_candidates === 0 ? 'Ingen fangster trenger oppdatering' : 'Alle tilgjengelige fangster er behandlet'
            ));
        }

        foreach ($catches as $catch) {
            error_log("WEATHER DEBUG: Updating weather for catch {$catch->id} on date {$catch->date}");
            $time_of_day = isset($catch->time_of_day) ? $catch->time_of_day : null;
            $weather_data = !empty($catch->date) ? $this->fetch_weather_for_date($catch->date, $time_of_day) : null;
            
            if ($weather_data) {
                $result = $wpdb->update(
                    $table_name,
                    array(
                        'weather_data' => json_encode($weather_data),
                        'weather_fetched_at' => current_time('mysql')
                    ),
                    array('id' => $catch->id),
                    array('%s', '%s'),
                    array('%d')
                );
                
                if ($result !== false) {
                    $updated++;
                } else {
                    $errors++;
                }
            } else {
                $errors++;
            }

            if ($include_water && !empty($catch->beat_name) && !empty($catch->date)) {
                $water_info = $this->get_water_level_for_catch($catch->beat_name, $catch->date, $catch->id, $time_of_day);
                if (is_array($water_info) && isset($water_info['water_data']['temperature_persist_status'])) {
                    $status = $water_info['water_data']['temperature_persist_status'];
                    if ($status === 'updated') {
                        $water_updated++;
                    } elseif ($status === 'failed') {
                        $water_errors++;
                    }
                }
            }
            
            // Pause kort for å ikke overbelaste API
            usleep($force_update ? 50000 : 200000);
        }

        $next_offset = $offset + $batch_size;
        $has_more = ($total_candidates > 0) && ($next_offset < $total_candidates);

        if (!$has_more) {
            $next_offset = $total_candidates;
        }

        wp_send_json_success(array(
            'updated' => $updated,
            'errors' => $errors,
            'water_updated' => $water_updated,
            'water_errors' => $water_errors,
            'total_processed' => count($catches),
            'total_candidates' => $total_candidates,
            'has_more' => $has_more,
            'next_offset' => $next_offset,
            'batch_size' => $batch_size
        ));
    }
    
    /**
     * Håndter filtrering av fangster per år via AJAX
     */
    public function handle_filter_catches_by_year() {
        if (!wp_verify_nonce($_POST['nonce'], 'fiskedagbok_nonce')) {
            wp_send_json_error('Sikkerhetsfeil');
            return;
        }
        
        if (!is_user_logged_in()) {
            wp_send_json_error('Du må være logget inn');
            return;
        }
        
        $user_id = get_current_user_id();
        $year = isset($_POST['year']) && $_POST['year'] !== '' ? intval($_POST['year']) : null;
        
        $catches = $this->get_user_catches($user_id, 0, $year);
        $available_years = $this->get_user_catch_years($user_id);
        
        // Generer HTML for fangstlisten
        ob_start();
        ?>
        <!-- År filter -->
        <div class="year-filter-container">
            <label for="year-filter">Velg år:</label>
            <select id="year-filter" onchange="filterByYear(this.value)">
                <option value="">Alle år</option>
                <?php if (!empty($available_years)): ?>
                    <?php foreach ($available_years as $available_year): ?>
                        <option value="<?php echo esc_attr($available_year); ?>" <?php echo ($year == $available_year) ? 'selected' : ''; ?>>
                            <?php echo esc_html($available_year); ?>
                        </option>
                    <?php endforeach; ?>
                <?php endif; ?>
            </select>
        </div>
        
        <?php if (!empty($catches)): ?>
        <div class="catches-summary">
            <p>Du har registrert <strong><?php echo count($catches); ?></strong> fangster<?php echo $year ? ' i ' . esc_html($year) : ''; ?>.</p>
        </div>
        
        <div class="catches-list" id="catches-list">
            <?php foreach ($catches as $catch): ?>
            <div class="catch-item" data-catch-id="<?php echo esc_attr($catch->id); ?>">
                <div class="catch-header">
                    <span class="catch-date"><?php echo esc_html(date('d.m.Y', strtotime($catch->date))); ?></span>
                    <?php if ($catch->time_of_day): ?>
                    <span class="catch-time"><?php echo esc_html(date('H:i', strtotime($catch->time_of_day))); ?></span>
                    <?php endif; ?>
                    <span class="catch-fish-type"><?php echo esc_html($catch->fish_type); ?></span>
                    <?php if (!empty($catch->weather_data)): ?>
                    <span class="weather-icon" title="Værdata tilgjengelig">🌤️</span>
                    <?php endif; ?>
                </div>
                
                <div class="catch-details">
                    <div class="catch-location">
                        <?php if ($catch->river_name): ?>
                        <strong>Elv:</strong> <?php echo esc_html($catch->river_name); ?>
                        <?php endif; ?>
                        
                        <?php if ($catch->beat_name): ?>
                        | <strong>Beat:</strong> <?php echo esc_html($catch->beat_name); ?>
                        <?php endif; ?>
                        
                        <?php if ($catch->fishing_spot): ?>
                        | <strong>Sted:</strong> <?php echo esc_html($catch->fishing_spot); ?>
                        <?php endif; ?>
                    </div>
                    
                    <div class="catch-specs">
                        <?php if ($catch->weight_kg): ?>
                        <span class="weight"><strong>Vekt:</strong> <?php echo esc_html($catch->weight_kg); ?> kg</span>
                        <?php endif; ?>
                        
                        <?php if ($catch->length_cm): ?>
                        <span class="length"><strong>Lengde:</strong> <?php echo esc_html($catch->length_cm); ?> cm</span>
                        <?php endif; ?>
                        
                        <?php if ($catch->equipment): ?>
                        <span class="equipment"><strong>Utstyr:</strong> <?php echo esc_html($catch->equipment); ?></span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="catch-meta">
                        <?php if ($catch->sex && $catch->sex !== 'unknown'): ?>
                        <span class="sex"><strong>Kjønn:</strong> <?php echo $catch->sex === 'male' ? 'Hann' : 'Hunn'; ?></span>
                        <?php endif; ?>
                        
                        <span class="released <?php echo $catch->released ? 'yes' : 'no'; ?>">
                            <?php echo $catch->released ? '✓ Sluppet' : '✗ Tatt med'; ?>
                        </span>
                        
                    </div>
                    
                    <?php if ($catch->notes): ?>
                    <div class="catch-notes">
                        <strong>Notater:</strong> <?php echo esc_html($catch->notes); ?>
                    </div>
                    <?php endif; ?>
                </div>
                
                <div class="catch-actions">
                    <button class="view-details-btn" data-catch-id="<?php echo esc_attr($catch->id); ?>">Se detaljer</button>
                    <button class="edit-catch-btn" data-catch-id="<?php echo esc_attr($catch->id); ?>">Rediger</button>
                    <button class="delete-catch-btn" data-catch-id="<?php echo esc_attr($catch->id); ?>">Slett</button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <p>Ingen fangster funnet<?php echo $year ? ' for ' . esc_html($year) : ''; ?>.</p>
        <?php endif; ?>
        <?php
        
        $html = ob_get_clean();
        wp_send_json_success(array(
            'html' => $html,
            'count' => count($catches),
            'year' => $year
        ));
    }
    
    /**
     * Håndter testing av værdata API
     */
    public function handle_test_weather_api() {
        if (!wp_verify_nonce($_POST['nonce'], 'fiskedagbok_nonce')) {
            wp_send_json_error('Sikkerhetsfeil');
            return;
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Ingen tilgang');
            return;
        }
        
        // Test værdata for i dag
        $today = date('Y-m-d');
        $weather_data = $this->fetch_weather_for_date($today);
        
        if ($weather_data) {
            wp_send_json_success($weather_data);
        } else {
            wp_send_json_error('Kunne ikke hente værdata');
        }
    }
    
    /**
     * Hent vannstand data for en beat på en spesifikk dato og tid
     * @param string $beat_name - Name of the beat
     * @param string $date - Date in YYYY-MM-DD format
     * @param int $catch_id - Optional catch ID for station override lookup
     * @param string $time_of_day - Optional time in HH:MM:SS format
     * @param bool $fast_mode - If true, skip database writes (for async fetches). Defaults to false
     */
    public function get_water_level_for_catch($beat_name, $date, $catch_id = null, $time_of_day = null, $fast_mode = false) {
        if (!$beat_name || !$date) {
            return null;
        }
        
        error_log("VANNSTAND DEBUG: Getting water level for beat='$beat_name', date='$date', time='$time_of_day', catch_id='$catch_id', fast_mode=" . ($fast_mode ? 'true' : 'false'));
        
        global $wpdb;
        
        if (!function_exists('is_plugin_active')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        
        // Sjekk om Orkla Water Level plugin er aktiv
        if (!is_plugin_active('orkla-water-level/orkla-water-level.php')) {
            return array(
                'error' => 'Orkla Water Level plugin ikke aktiv',
                'beat_name' => $beat_name,
                'date' => $date
            );
        }
        
        $station_config = null;
        
        // Sjekk først om det er en override for denne fangsten
        if ($catch_id) {
            $catches_table = $wpdb->prefix . 'fiskedagbok_catches';
            $override_station = $wpdb->get_var($wpdb->prepare("
                SELECT water_station_override 
                FROM $catches_table 
                WHERE id = %d AND water_station_override IS NOT NULL
            ", $catch_id));
            
            if ($override_station) {
                // Opprett en mock station_config for override
                $water_stations = $this->get_water_stations();
                if (isset($water_stations[$override_station])) {
                    $station_config = (object) array(
                        'water_station_id' => $override_station,
                        'water_station_name' => $water_stations[$override_station]['name'],
                        'description' => $water_stations[$override_station]['description'] . ' (Manuelt valgt for denne fangsten)'
                    );
                }
            }
        }
        
        // Hvis ikke override, hent standard konfiguration for beat
        if (!$station_config) {
            $beat_config_table = $wpdb->prefix . 'fiskedagbok_beat_water_stations';
            $station_config = $wpdb->get_row($wpdb->prepare("
                SELECT water_station_id, water_station_name, description 
                FROM $beat_config_table 
                WHERE beat_name = %s
            ", $beat_name));
        }
        
        if (!$station_config) {
            return array(
                'error' => 'Ingen vannstand stasjon konfigurert for beat: ' . $beat_name,
                'beat_name' => $beat_name,
                'date' => $date
            );
        }
        
        // Prøv å hente vannstand data fra Orkla plugin
        $water_data = null;
        $temperature_status = 'skipped';
        
        try {
            if ($this->orkla_water_level_plugin && method_exists($this->orkla_water_level_plugin, 'get_water_data_by_datetime')) {
                $water_data = $this->orkla_water_level_plugin->get_water_data_by_datetime($station_config->water_station_id, $date, $time_of_day);
            } else {
                error_log('Fiskedagbok: OrklaWaterLevel plugin or get_water_data_by_datetime method not available.');
            }
        } catch (Exception $e) {
            error_log('Fiskedagbok: Error accessing Orkla water data: ' . $e->getMessage());
        }
        
        // OPTIMIZATION: Skip database writes in fast_mode (async requests)
        if (!$fast_mode && $catch_id && $station_config && is_array($water_data) && !isset($water_data['error'])) {
            $temperature_status = $this->persist_catch_water_temperature(
                $catch_id,
                $station_config->water_station_id,
                $water_data
            );
            $water_data['temperature_persist_status'] = $temperature_status;
        } elseif (is_array($water_data)) {
            $water_data['temperature_persist_status'] = $temperature_status;
        }
        
        error_log("VANNSTAND DEBUG: Using station '{$station_config->water_station_id}' ({$station_config->water_station_name}) for beat '$beat_name'");
        
        return array(
            'beat_name' => $beat_name,
            'date' => $date,
            'station_id' => $station_config->water_station_id,
            'station_name' => $station_config->water_station_name,
            'description' => $station_config->description,
            'water_data' => $water_data
        );
    }
    
    /**
     * Lagre vanntemperatur for en fangst dersom data er tilgjengelig
     */
    private function persist_catch_water_temperature($catch_id, $station_id, $water_data) {
        $measurement_status = isset($water_data['measurement_status']) ? $water_data['measurement_status'] : 'unknown';

        if ($measurement_status !== 'fresh') {
            return $this->clear_catch_water_temperature($catch_id);
        }

        if (!isset($water_data['temperature']) || $water_data['temperature'] === null) {
            return $this->clear_catch_water_temperature($catch_id);
        }

        $normalized_temperature = $this->normalize_water_temperature($water_data['temperature']);
        if ($normalized_temperature === null) {
            return $this->clear_catch_water_temperature($catch_id);
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'fiskedagbok_catches';

        $update_data = array(
            'water_temperature' => $normalized_temperature,
            'water_temperature_source' => isset($water_data['temperature_source']) ? $water_data['temperature_source'] : $station_id
        );
        $format = array('%f', '%s');

        if (!empty($water_data['temperature_timestamp'])) {
            $update_data['water_temperature_recorded_at'] = $water_data['temperature_timestamp'];
            $format[] = '%s';
        } elseif (!empty($water_data['timestamp'])) {
            $update_data['water_temperature_recorded_at'] = $water_data['timestamp'];
            $format[] = '%s';
        }

        $result = $wpdb->update(
            $table_name,
            $update_data,
            array('id' => $catch_id),
            $format,
            array('%d')
        );

        if ($result === false) {
            error_log('Fiskedagbok: Failed to persist water temperature for catch ' . $catch_id . ': ' . $wpdb->last_error);
            return 'failed';
        }

        return $result === 0 ? 'unchanged' : 'updated';
    }

    private function clear_catch_water_temperature($catch_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'fiskedagbok_catches';

        $result = $wpdb->query($wpdb->prepare(
            "UPDATE $table_name 
             SET water_temperature = NULL, 
                 water_temperature_source = NULL, 
                 water_temperature_recorded_at = NULL 
             WHERE id = %d",
            $catch_id
        ));

        if ($result === false) {
            error_log('Fiskedagbok: Failed to clear water temperature for catch ' . $catch_id . ': ' . $wpdb->last_error);
            return 'clear_failed';
        }

        return $result === 0 ? 'unchanged' : 'cleared';
    }

    private function normalize_water_temperature($value) {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_string($value)) {
            $value = str_replace(',', '.', $value);
        }

        $numeric = floatval($value);

        if ($numeric < -5 || $numeric > 35) {
            return null;
        }

        return round($numeric, 2);
    }
    
    /**
     * Hent alle tilgjengelige vannstand stasjoner
     */
    public function get_water_stations() {
        return array(
            'storsteinsholen' => array(
                'name' => 'Vannføring Storsteinshølen',
                'description' => 'For Elvadalen (Mellom Bjørset og Svorkmo kraftverk)'
            ),
            'rennebu_oppstroms' => array(
                'name' => 'Rennebu oppstrøms Grana',
                'description' => 'For Rennebu oppstrøms Grana området'
            ),
            'syrstad' => array(
                'name' => 'Vannføring Syrstad',
                'description' => 'For utløpet av Grana ned til Bjørset dammen'
            ),
            'nedstroms_svorkmo' => array(
                'name' => 'Nedstrøms Svorkmo kraftverk',
                'description' => 'For nedenfor Svorkmo kraftverk'
            ),
            'oppstroms_brattset' => array(
                'name' => 'Vannføring oppstrøms Brattset',
                'description' => 'For oppstrøms Brattset området'
            )
        );
    }
    
    /**
     * Hent vannstand data fra Orkla Water Level plugin

        // Orkla plugin lagrer data i orkla_water_data tabell
        $orkla_table = $wpdb->prefix . 'orkla_water_data';

        // Sjekk om tabellen eksisterer
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$orkla_table'");
        if (!$table_exists) {
            return array(
                'error' => 'Orkla vannstand tabell ikke funnet'
            );
        }

        // Hent data for spesifikk dato - mapping av station til kolonne
        $station_mapping = array(
            'storsteinsholen' => array('level' => 'water_level_3', 'flow' => 'flow_rate_3', 'temperature' => 'temperature_1'),
            'rennebu_oppstroms' => array('level' => 'water_level_2', 'flow' => 'flow_rate_2', 'temperature' => 'temperature_1'),
            'syrstad' => array('level' => 'water_level_2', 'flow' => 'flow_rate_2', 'temperature' => 'temperature_1'),
            'nedstroms_svorkmo' => array('level' => 'water_level_3', 'flow' => 'flow_rate_3', 'temperature' => 'temperature_1'),
            'oppstroms_brattset' => array('level' => 'water_level_1', 'flow' => 'flow_rate_1', 'temperature' => 'temperature_1')
        );

        if (!isset($station_mapping[$station_id])) {
            return array(
                'error' => 'Ukjent vannstand stasjon: ' . $station_id
            );
        }

    $columns = $station_mapping[$station_id];
    $temperature_column = isset($columns['temperature']) ? $columns['temperature'] : null;
    $level_column = isset($columns['level']) ? $columns['level'] : null;

        // Konstruer datetime string basert på dato og tid
        $target_date = $date;
        $target_datetime = $date;
        if ($time_of_day) {
            $target_datetime = $date . ' ' . $time_of_day;
        }

        $select_columns = array('timestamp', 'date_recorded');
        if (!empty($level_column)) {
            $select_columns[] = "{$level_column} as water_level";
        }
        if (!empty($columns['flow'])) {
            $select_columns[] = "{$columns['flow']} as flow";
        }
        if (!empty($temperature_column)) {
            $select_columns[] = "$temperature_column as temperature";
        }
        $select_clause = implode(', ', $select_columns);

        $not_null_conditions = array();
        if (!empty($level_column)) {
            $not_null_conditions[] = "{$level_column} IS NOT NULL";
        }
        if (!empty($columns['flow'])) {
            $not_null_conditions[] = "{$columns['flow']} IS NOT NULL";
        }
        if (!empty($temperature_column)) {
            $not_null_conditions[] = "$temperature_column IS NOT NULL";
        }
        $not_null_filter = !empty($not_null_conditions) ? ' AND (' . implode(' OR ', $not_null_conditions) . ')' : '';
        $availability_condition = !empty($not_null_conditions) ? '(' . implode(' OR ', $not_null_conditions) . ')' : '1=1';

        $extract_measurement_value = function($data_point) {
            if (!$data_point) {
                return null;
            }

            if (isset($data_point->water_level) && $data_point->water_level !== null && $data_point->water_level !== '') {
                return floatval($data_point->water_level);
            }

            if (isset($data_point->flow) && $data_point->flow !== null && $data_point->flow !== '') {
                return floatval($data_point->flow);
            }

            return null;
        };

        // Først: prøv å hente data fra samme dag som fangsten, men KUN FØR fangsttidspunktet
        $before_catch_query = "
            SELECT DISTINCT $select_clause
            FROM $orkla_table
            WHERE date_recorded = %s AND timestamp <= %s$not_null_filter
            ORDER BY timestamp DESC
        ";
        $before_catch_data = $wpdb->get_results($wpdb->prepare($before_catch_query, $target_date, $target_datetime));

        error_log("VANNSTAND DEBUG: Found " . count($before_catch_data) . " measurements BEFORE catch time ($target_datetime)");

        // Sjekk om vi har unike målinger før fangsttidspunktet
        if (count($before_catch_data) > 0) {
            $measurement_values_before = array_filter(array_map($extract_measurement_value, $before_catch_data), function($value) {
                return $value !== null;
            });
            $unique_measurements = array_unique($measurement_values_before, SORT_NUMERIC);
            $has_variation = count($unique_measurements) > 1;

            error_log("VANNSTAND DEBUG: Found " . count($unique_measurements) . " unique measurement values before catch, variation: " . ($has_variation ? 'yes' : 'no'));
        } else {
            $has_variation = false;
        }

        if (count($before_catch_data) >= 2 && $has_variation) {
            // Vi har nok data fra før fangsttidspunktet til å analysere trend
            $measurements_to_analyze = array_slice($before_catch_data, 0, 4); // Ta de 4 nærmeste før fangsten

            error_log("VANNSTAND DEBUG: Using " . count($measurements_to_analyze) . " measurements from before catch time for trend analysis");
        } else {
            // Ikke nok data fra samme dag før fangsten, hent historiske data FØR fangsttidspunktet
            $historical_query = "
                SELECT DISTINCT $select_clause
                FROM $orkla_table
                WHERE timestamp < %s$not_null_filter
                ORDER BY timestamp DESC
                LIMIT 10
            ";
            $historical_data = $wpdb->get_results($wpdb->prepare($historical_query, $target_datetime));

            // Filtrer ut duplikater og ta siste 4 unike målinger
            $unique_measurements = array();
            $seen_flows = array();

            foreach ($historical_data as $measurement) {
                $measurement_value = $extract_measurement_value($measurement);
                $flow_key = $measurement->date_recorded . '_' . ($measurement_value === null ? 'null' : round($measurement_value, 3));
                if (!in_array($flow_key, $seen_flows)) {
                    $unique_measurements[] = $measurement;
                    $seen_flows[] = $flow_key;

                    if (count($unique_measurements) >= 4) {
                        break;
                    }
                }
            }

            $measurements_to_analyze = $unique_measurements;
            error_log("VANNSTAND DEBUG: Using " . count($measurements_to_analyze) . " unique historical measurements BEFORE catch time for trend analysis");
        }

        // Debug logging for vannstand data
        error_log("VANNSTAND DEBUG: Station=$station_id, Target date=$target_date, Target datetime=$target_datetime");
        error_log("VANNSTAND DEBUG: Analyzing trend using measurements BEFORE catch time:");

        $measurement_status = 'missing';
        $stale_measurement = null;
        $note = null;
        $exact_time = false;

        if (count($measurements_to_analyze) >= 1) {
            foreach ($measurements_to_analyze as $i => $data_point) {
                $parts = array();
                if (isset($data_point->water_level)) {
                    $parts[] = 'water_level ' . ($data_point->water_level !== null ? $data_point->water_level : 'null') . ' m³/s';
                }
                if (isset($data_point->flow)) {
                    $parts[] = 'flow ' . ($data_point->flow !== null ? $data_point->flow : 'null') . ' m³/s';
                }
                if (isset($data_point->temperature)) {
                    $parts[] = 'temp ' . ($data_point->temperature !== null ? $data_point->temperature : 'null') . ' °C';
                }

                $log_message = "VANNSTAND DEBUG: Measurement $i: {$data_point->timestamp}";
                if (!empty($parts)) {
                    $log_message .= ' - ' . implode(', ', $parts);
                }

                error_log($log_message);
            }
        }

        if (count($measurements_to_analyze) >= 1) {
            $latest_measurement = $measurements_to_analyze[0];
            $latest_flow_raw = isset($latest_measurement->flow) ? $latest_measurement->flow : null;
            $latest_flow = ($latest_flow_raw !== null && $latest_flow_raw !== '') ? floatval($latest_flow_raw) : null;
            $latest_level_raw = isset($latest_measurement->water_level) ? $latest_measurement->water_level : null;
            $latest_water_level = ($latest_level_raw !== null && $latest_level_raw !== '') ? floatval($latest_level_raw) : null;
            $latest_primary_value = $extract_measurement_value($latest_measurement);
            $latest_temperature_raw = isset($latest_measurement->temperature) ? $latest_measurement->temperature : null;
            $latest_temperature = $this->normalize_water_temperature($latest_temperature_raw);
            $latest_timestamp = isset($latest_measurement->timestamp) ? $latest_measurement->timestamp : null;

            $latest_timestamp_unix = $latest_timestamp ? strtotime($latest_timestamp) : false;
            $target_timestamp_unix = strtotime($target_datetime);

            if ($latest_timestamp_unix !== false && $target_timestamp_unix !== false && $latest_timestamp_unix <= $target_timestamp_unix) {
                if (date('Y-m-d', $latest_timestamp_unix) === $target_date) {
                    $measurement_status = 'fresh';
                    $exact_time = true;
                } else {
                    $measurement_status = 'stale';
                }
            } else {
                $measurement_status = 'stale';
            }

            if ($measurement_status !== 'fresh') {
                $stale_measurement = array(
                    'flow' => $latest_primary_value,
                    'water_level' => $latest_water_level,
                    'flow_rate' => $latest_flow,
                    'temperature' => $this->normalize_water_temperature($latest_temperature_raw),
                    'timestamp' => $latest_timestamp
                );

                $latest_primary_value = null;
                $latest_flow = null;
                $latest_water_level = null;
                $latest_temperature = null;
                $latest_timestamp = null;

                if (!empty($stale_measurement['timestamp'])) {
                    $formatted = function_exists('date_i18n')
                        ? date_i18n('d.m.Y H:i', strtotime($stale_measurement['timestamp']))
                        : date('d.m.Y H:i', strtotime($stale_measurement['timestamp']));
                    $note = 'Siste registrerte måling er fra ' . $formatted;
                } else {
                    $note = 'Ingen fersk måling for valgt dato';
                }
            }

            $trend = null;
            $trend_description = null;

            if ($measurement_status === 'fresh' && $latest_primary_value !== null) {
                $trend = 'helt_stabil';
                $trend_description = 'Elven var helt stabil';

                $measurement_values = array_filter(array_map($extract_measurement_value, $measurements_to_analyze), function($value) {
                    return $value !== null;
                });
                $unique_recent_measurements = array_unique($measurement_values, SORT_NUMERIC);

                if (count($measurement_values) >= 2 && count($unique_recent_measurements) >= 2) {
                    if (count($measurement_values) >= 3) {
                        $first_avg = ($measurement_values[2] + (isset($measurement_values[3]) ? $measurement_values[3] : $measurement_values[2])) / 2;
                    } else {
                        $first_avg = $measurement_values[1];
                    }
                    $last_value = $measurement_values[0];

                    $changes = array();
                    for ($i = 0; $i < count($measurement_values) - 1; $i++) {
                        $changes[] = $measurement_values[$i] - $measurement_values[$i + 1];
                    }
                    $avg_change_per_measurement = count($changes) ? array_sum($changes) / count($changes) : 0;

                    $change_percent = $first_avg != 0 ? (($last_value - $first_avg) / $first_avg) * 100 : 0;

                    error_log("VANNSTAND DEBUG: Trend analysis - Earlier measurements avg: $first_avg, Closest to catch: $last_value, Change: " . round($change_percent, 2) . "%");
                    error_log("VANNSTAND DEBUG: Average change per measurement: " . round($avg_change_per_measurement, 2) . " m³/s");

                    $trend_detail = '';
                    if (abs($avg_change_per_measurement) >= 0.1) {
                        if ($avg_change_per_measurement > 0) {
                            $trend_detail = ' (ø. ' . round($avg_change_per_measurement, 1) . ' m³/s per måling)';
                        } else {
                            $trend_detail = ' (ned ' . round(abs($avg_change_per_measurement), 1) . ' m³/s per måling)';
                        }
                    }

                    if ($change_percent >= 10) {
                        $trend = 'kraftig_voksende';
                        $trend_description = 'Elven var kraftig voksende' . $trend_detail;
                    } elseif ($change_percent >= 3) {
                        $trend = 'stigende';
                        $trend_description = 'Elven var stigende' . $trend_detail;
                    } elseif ($change_percent >= 1) {
                        $trend = 'ganske_stabil';
                        $trend_description = 'Elven var ganske stabil' . $trend_detail;
                    } elseif ($change_percent <= -10) {
                        $trend = 'kraftig_synkende';
                        $trend_description = 'Elven var kraftig synkende' . $trend_detail;
                    } elseif ($change_percent <= -3) {
                        $trend = 'synkende';
                        $trend_description = 'Elven var synkende' . $trend_detail;
                    } elseif ($change_percent <= -1) {
                        $trend = 'ganske_stabil';
                        $trend_description = 'Elven var ganske stabil' . $trend_detail;
                    } else {
                        $trend = 'helt_stabil';
                        $trend_description = 'Elven var helt stabil' . $trend_detail;
                    }
                } else {
                    error_log("VANNSTAND DEBUG: Insufficient variation for trend analysis - treating as stable");
                }

                error_log("VANNSTAND DEBUG: Final trend determination: $trend ($trend_description)");
            } else {
                $trend = 'ingen_data';
                $trend_description = 'Ingen fersk måling denne datoen';
            }

            $result = array(
                'flow' => $latest_primary_value,
                'water_level' => $latest_water_level,
                'flow_rate' => $latest_flow,
                'temperature' => $latest_temperature,
                'temperature_timestamp' => $latest_temperature !== null ? $latest_timestamp : null,
                'temperature_source' => $temperature_column ? $station_id . '.' . $temperature_column : null,
                'temperature_unit' => $latest_temperature !== null ? '°C' : null,
                'timestamp' => $latest_timestamp,
                'source' => 'Orkla Water Level',
                'trend' => $trend,
                'trend_description' => $trend_description,
                'measurements_count' => count($measurements_to_analyze),
                'columns' => $columns,
                'exact_time' => $exact_time,
                'measurement_status' => $measurement_status
            );

            if ($note) {
                $result['note'] = $note;
            }
            if ($stale_measurement) {
                $result['stale_measurement'] = $stale_measurement;
            }

            return $result;
        }

        // Hvis ingen data for eksakt dato, hent nærmeste data fra andre dager
        $nearest_query = "
            SELECT $select_clause
            FROM $orkla_table
            WHERE $availability_condition
            ORDER BY ABS(DATEDIFF(date_recorded, %s))
            LIMIT 1
        ";
        $nearest_data = $wpdb->get_row($wpdb->prepare($nearest_query, $date));

        if ($nearest_data) {
            $nearest_flow_value = (isset($nearest_data->flow) && $nearest_data->flow !== null && $nearest_data->flow !== '') ? floatval($nearest_data->flow) : null;
            $nearest_water_level = (isset($nearest_data->water_level) && $nearest_data->water_level !== null && $nearest_data->water_level !== '') ? floatval($nearest_data->water_level) : null;
            $nearest_primary_value = $nearest_water_level !== null ? $nearest_water_level : $nearest_flow_value;
            $nearest_temperature_value = isset($nearest_data->temperature) ? $this->normalize_water_temperature($nearest_data->temperature) : null;
            $nearest_timestamp = isset($nearest_data->timestamp) ? $nearest_data->timestamp : null;
            $nearest_note_date = isset($nearest_data->date_recorded) ? $nearest_data->date_recorded : null;

            $note_text = $nearest_note_date ? 'Data fra ' . $nearest_note_date . ' (nærmeste tilgjengelige dato)' : 'Bruker nærmeste tilgjengelige måling';

            $result = array(
                'flow' => null,
                'water_level' => null,
                'flow_rate' => null,
                'temperature' => null,
                'temperature_timestamp' => null,
                'temperature_source' => $temperature_column ? $station_id . '.' . $temperature_column : null,
                'temperature_unit' => null,
                'timestamp' => null,
                'source' => 'Orkla Water Level (nærmeste data)',
                'note' => $note_text,
                'exact_time' => false,
                'measurement_status' => 'approximate',
                'columns' => $columns
            );

            if ($nearest_primary_value !== null || $nearest_temperature_value !== null) {
                $result['stale_measurement'] = array(
                    'flow' => $nearest_primary_value,
                    'water_level' => $nearest_water_level,
                    'flow_rate' => $nearest_flow_value,
                    'temperature' => $nearest_temperature_value,
                    'timestamp' => $nearest_timestamp
                );
            }

            return $result;
        }

        return array(
            'error' => 'Ingen vannstand data funnet for ' . $station_id . ' på ' . $date
        );
    }
    
    /**
     * DEBUG: Ultra-fast endpoint returning dummy data (< 50ms)
     * Used to test if bottleneck is in the main handle_get_catch_details()
     */
    public function handle_get_catch_details_debug() {
        wp_send_json_success((object) array(
            'id' => intval($_POST['catch_id']),
            'fish_type' => 'Laks',
            'weight_kg' => '4.5',
            'length_cm' => '65',
            'sex' => 'male',
            'released' => 0,
            'date' => date('Y-m-d'),
            'time_of_day' => '14:30:00',
            'river_name' => 'Orkla',
            'beat_name' => 'Holstad',
            'fishing_spot' => 'Hovedfossen',
            'week' => (int)date('W'),
            'equipment' => 'Flue',
            'boat' => '',
            'notes' => 'DEBUG DATA - test endpoint',
            'weather' => null
        ));
    }
}

// Initialiser plugin
new Fiskedagbok();