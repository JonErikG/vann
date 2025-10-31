<?php
if (!defined('ABSPATH')) {
    exit;
}

class Orkla_Health_Monitor {
    private $wpdb;
    private $table_name;

    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table_name = $wpdb->prefix . 'orkla_water_data';
    }

    public function run_health_check() {
        $results = array(
            'timestamp' => current_time('mysql'),
            'status' => 'healthy',
            'checks' => array(),
            'warnings' => array(),
            'errors' => array(),
        );

        $results['checks']['database'] = $this->check_database_health();
        $results['checks']['data_freshness'] = $this->check_data_freshness();
        $results['checks']['data_quality'] = $this->check_data_quality();
        $results['checks']['import_status'] = $this->check_import_status();
        $results['checks']['cron_status'] = $this->check_cron_status();

        foreach ($results['checks'] as $check_name => $check_result) {
            if ($check_result['status'] === 'error') {
                $results['errors'][] = $check_name . ': ' . $check_result['message'];
                $results['status'] = 'critical';
            } elseif ($check_result['status'] === 'warning') {
                $results['warnings'][] = $check_name . ': ' . $check_result['message'];
                if ($results['status'] === 'healthy') {
                    $results['status'] = 'warning';
                }
            }
        }

        return $results;
    }

    private function check_database_health() {
        $escaped_table = $this->wpdb->esc_like($this->table_name);
        $table_exists = $this->wpdb->get_var($this->wpdb->prepare('SHOW TABLES LIKE %s', $escaped_table));

        if ($table_exists !== $this->table_name) {
            return array(
                'status' => 'error',
                'message' => 'Database table does not exist',
            );
        }

        $count = $this->wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name}");

        if ($count === null || $count === false) {
            return array(
                'status' => 'error',
                'message' => 'Unable to query database table',
            );
        }

        if ($count == 0) {
            return array(
                'status' => 'warning',
                'message' => 'No data in database',
                'record_count' => 0,
            );
        }

        return array(
            'status' => 'ok',
            'message' => 'Database healthy',
            'record_count' => (int) $count,
        );
    }

    private function check_data_freshness() {
        $latest = $this->wpdb->get_var("SELECT MAX(timestamp) FROM {$this->table_name}");

        if (empty($latest)) {
            return array(
                'status' => 'warning',
                'message' => 'No data available to check freshness',
            );
        }

        $latest_timestamp = strtotime($latest);
        $current_timestamp = current_time('timestamp');
        $age_hours = ($current_timestamp - $latest_timestamp) / 3600;

        if ($age_hours > 24) {
            return array(
                'status' => 'error',
                'message' => sprintf('Data is stale (%.1f hours old)', $age_hours),
                'latest_timestamp' => $latest,
                'age_hours' => round($age_hours, 1),
            );
        }

        if ($age_hours > 3) {
            return array(
                'status' => 'warning',
                'message' => sprintf('Data may be stale (%.1f hours old)', $age_hours),
                'latest_timestamp' => $latest,
                'age_hours' => round($age_hours, 1),
            );
        }

        return array(
            'status' => 'ok',
            'message' => 'Data is fresh',
            'latest_timestamp' => $latest,
            'age_hours' => round($age_hours, 1),
        );
    }

    private function check_data_quality() {
        $issues = array();

        $null_count = $this->wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->table_name}
             WHERE water_level_1 IS NULL
             AND water_level_2 IS NULL
             AND water_level_3 IS NULL
             AND flow_rate_1 IS NULL
             AND flow_rate_2 IS NULL
             AND flow_rate_3 IS NULL"
        );

        if ($null_count > 0) {
            $issues[] = sprintf('%d records with all NULL values', $null_count);
        }

        $recent_records = $this->wpdb->get_results(
            "SELECT * FROM {$this->table_name}
             ORDER BY timestamp DESC
             LIMIT 10",
            ARRAY_A
        );

        if (!empty($recent_records)) {
            $outliers = 0;
            foreach ($recent_records as $record) {
                if (isset($record['water_level_2']) && $record['water_level_2'] !== null) {
                    $value = (float) $record['water_level_2'];
                    if ($value < 0 || $value > 200) {
                        $outliers++;
                    }
                }
            }

            if ($outliers > 0) {
                $issues[] = sprintf('%d records with potential outlier values', $outliers);
            }
        }

        $duplicate_count = $this->wpdb->get_var(
            "SELECT COUNT(*) - COUNT(DISTINCT timestamp) FROM {$this->table_name}"
        );

        if ($duplicate_count > 0) {
            $issues[] = sprintf('%d duplicate timestamp entries', $duplicate_count);
        }

        if (!empty($issues)) {
            return array(
                'status' => 'warning',
                'message' => 'Data quality issues detected',
                'issues' => $issues,
            );
        }

        return array(
            'status' => 'ok',
            'message' => 'Data quality is good',
        );
    }

    private function check_import_status() {
        $last_summary = get_option('orkla_last_import_summary', null);

        if (empty($last_summary)) {
            return array(
                'status' => 'warning',
                'message' => 'No import history available',
            );
        }

        if (!isset($last_summary['timestamp'])) {
            return array(
                'status' => 'warning',
                'message' => 'Import summary incomplete',
            );
        }

        $import_timestamp = strtotime($last_summary['timestamp']);
        $age_hours = (current_time('timestamp') - $import_timestamp) / 3600;

        if ($age_hours > 2) {
            return array(
                'status' => 'warning',
                'message' => sprintf('Last import was %.1f hours ago', $age_hours),
                'last_import' => $last_summary['timestamp'],
            );
        }

        $summary = isset($last_summary['summary']) ? $last_summary['summary'] : array();

        if (!empty($summary['errors'])) {
            return array(
                'status' => 'error',
                'message' => 'Last import had errors',
                'errors' => $summary['errors'],
            );
        }

        return array(
            'status' => 'ok',
            'message' => 'Import status healthy',
            'last_import' => $last_summary['timestamp'],
            'imported' => isset($summary['imported']) ? $summary['imported'] : 0,
            'updated' => isset($summary['updated']) ? $summary['updated'] : 0,
        );
    }

    private function check_cron_status() {
        $next_scheduled = wp_next_scheduled('orkla_fetch_data_hourly');

        if ($next_scheduled === false) {
            return array(
                'status' => 'error',
                'message' => 'Cron job not scheduled',
            );
        }

        $time_until = $next_scheduled - current_time('timestamp');

        return array(
            'status' => 'ok',
            'message' => 'Cron job scheduled',
            'next_run' => date('Y-m-d H:i:s', $next_scheduled),
            'minutes_until' => round($time_until / 60, 1),
        );
    }

    public function get_data_statistics() {
        $stats = array();

        $stats['total_records'] = $this->wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name}");

        $stats['date_range'] = $this->wpdb->get_row(
            "SELECT MIN(timestamp) as earliest, MAX(timestamp) as latest FROM {$this->table_name}",
            ARRAY_A
        );

        $stats['field_coverage'] = array();
        $fields = array('water_level_1', 'water_level_2', 'water_level_3', 'flow_rate_1', 'flow_rate_2', 'flow_rate_3', 'temperature_1');

        foreach ($fields as $field) {
            $non_null = $this->wpdb->get_var(
                "SELECT COUNT(*) FROM {$this->table_name} WHERE {$field} IS NOT NULL"
            );
            $stats['field_coverage'][$field] = array(
                'count' => (int) $non_null,
                'percentage' => $stats['total_records'] > 0 ? round(($non_null / $stats['total_records']) * 100, 1) : 0,
            );
        }

        $stats['records_last_24h'] = $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table_name} WHERE timestamp >= %s",
                date('Y-m-d H:i:s', current_time('timestamp') - 86400)
            )
        );

        $stats['average_values'] = $this->wpdb->get_row(
            "SELECT
                AVG(water_level_2) as avg_water_level,
                MAX(water_level_2) as max_water_level,
                MIN(water_level_2) as min_water_level
             FROM {$this->table_name}
             WHERE timestamp >= DATE_SUB(NOW(), INTERVAL 7 DAY)
             AND water_level_2 IS NOT NULL",
            ARRAY_A
        );

        return $stats;
    }

    public function detect_data_gaps() {
        $gaps = array();

        $records = $this->wpdb->get_results(
            "SELECT timestamp FROM {$this->table_name}
             WHERE timestamp >= DATE_SUB(NOW(), INTERVAL 7 DAY)
             ORDER BY timestamp ASC",
            ARRAY_A
        );

        if (count($records) < 2) {
            return $gaps;
        }

        for ($i = 1; $i < count($records); $i++) {
            $prev_time = strtotime($records[$i - 1]['timestamp']);
            $curr_time = strtotime($records[$i]['timestamp']);
            $gap_hours = ($curr_time - $prev_time) / 3600;

            if ($gap_hours > 2) {
                $gaps[] = array(
                    'start' => $records[$i - 1]['timestamp'],
                    'end' => $records[$i]['timestamp'],
                    'gap_hours' => round($gap_hours, 1),
                );
            }
        }

        return $gaps;
    }
}
