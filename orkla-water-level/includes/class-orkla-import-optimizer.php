<?php
if (!defined('ABSPATH')) {
    exit;
}

class Orkla_Import_Optimizer {
    private $wpdb;
    private $table_name;

    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table_name = $wpdb->prefix . 'orkla_water_data';
    }

    public function optimize_import_cutoff($force_full = false) {
        if ($force_full) {
            return null;
        }

        $latest = $this->wpdb->get_var("SELECT MAX(timestamp) FROM {$this->table_name}");

        if (empty($latest)) {
            return null;
        }

        try {
            $timezone = $this->get_wp_timezone();
            $date = new DateTime($latest, $timezone);

            $lookback_hours = (int) apply_filters('orkla_import_lookback_hours', 2);
            $date->modify("-{$lookback_hours} hours");

            return $date->getTimestamp();
        } catch (Exception $e) {
            error_log('Orkla Plugin: Error calculating import cutoff: ' . $e->getMessage());
            return null;
        }
    }

    public function should_import_record($timestamp, $cutoff, $existing_data = null) {
        if ($cutoff === null) {
            return true;
        }

        if ($timestamp > $cutoff) {
            return true;
        }

        if ($existing_data !== null && $this->has_data_changed($existing_data, $timestamp)) {
            return true;
        }

        return false;
    }

    private function has_data_changed($new_data, $timestamp) {
        $existing = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table_name} WHERE timestamp = %s",
                date('Y-m-d H:i:s', $timestamp)
            ),
            ARRAY_A
        );

        if (!$existing) {
            return true;
        }

        $fields = array('water_level_1', 'water_level_2', 'water_level_3', 'flow_rate_1', 'flow_rate_2', 'flow_rate_3', 'temperature_1');

        foreach ($fields as $field) {
            $new_value = isset($new_data[$field]) ? $new_data[$field] : null;
            $old_value = isset($existing[$field]) ? $existing[$field] : null;

            if ($this->values_differ($new_value, $old_value)) {
                return true;
            }
        }

        return false;
    }

    private function values_differ($new_value, $old_value, $tolerance = 0.01) {
        if ($new_value === null && $old_value === null) {
            return false;
        }

        if ($new_value === null || $old_value === null) {
            return true;
        }

        $diff = abs((float) $new_value - (float) $old_value);
        return $diff > $tolerance;
    }

    public function batch_import_records($records, $batch_size = 50) {
        $total = count($records);
        $imported = 0;
        $updated = 0;
        $skipped = 0;
        $errors = array();

        for ($i = 0; $i < $total; $i += $batch_size) {
            $batch = array_slice($records, $i, $batch_size);
            $result = $this->import_batch($batch);

            $imported += $result['imported'];
            $updated += $result['updated'];
            $skipped += $result['skipped'];
            $errors = array_merge($errors, $result['errors']);

            if (($i + $batch_size) % 200 === 0) {
                usleep(100000);
            }
        }

        return array(
            'imported' => $imported,
            'updated' => $updated,
            'skipped' => $skipped,
            'errors' => $errors,
        );
    }

    private function import_batch($records) {
        $imported = 0;
        $updated = 0;
        $skipped = 0;
        $errors = array();

        $formats = array('%s', '%s', '%s', '%f', '%f', '%f', '%f', '%f', '%f', '%f');

        foreach ($records as $record) {
            $has_measurement = false;
            $measurement_fields = array('water_level_1', 'water_level_2', 'water_level_3', 'flow_rate_1', 'flow_rate_2', 'flow_rate_3', 'temperature_1');

            foreach ($measurement_fields as $field) {
                if (isset($record[$field]) && $record[$field] !== null) {
                    $has_measurement = true;
                    break;
                }
            }

            if (!$has_measurement) {
                $skipped++;
                continue;
            }

            $result = $this->wpdb->replace($this->table_name, $record, $formats);

            if ($result === false) {
                $errors[] = $this->wpdb->last_error ? $this->wpdb->last_error : 'Database error during replace';
                continue;
            }

            if ($result === 1) {
                $imported++;
            } elseif ($result === 2) {
                $updated++;
            }
        }

        return array(
            'imported' => $imported,
            'updated' => $updated,
            'skipped' => $skipped,
            'errors' => $errors,
        );
    }

    public function cleanup_old_data($days_to_keep = 365) {
        $cutoff_date = date('Y-m-d H:i:s', current_time('timestamp') - ($days_to_keep * 86400));

        $count = $this->wpdb->query(
            $this->wpdb->prepare(
                "DELETE FROM {$this->table_name} WHERE timestamp < %s",
                $cutoff_date
            )
        );

        if ($count > 0) {
            error_log("Orkla Plugin: Cleaned up {$count} old records (older than {$days_to_keep} days)");

            $this->wpdb->query("OPTIMIZE TABLE {$this->table_name}");
        }

        return $count;
    }

    public function optimize_database_indexes() {
        $queries = array(
            "CREATE INDEX IF NOT EXISTS idx_timestamp ON {$this->table_name}(timestamp)",
            "CREATE INDEX IF NOT EXISTS idx_date_recorded ON {$this->table_name}(date_recorded)",
            "CREATE INDEX IF NOT EXISTS idx_timestamp_water_level ON {$this->table_name}(timestamp, water_level_2)",
        );

        $results = array();

        foreach ($queries as $query) {
            $result = $this->wpdb->query($query);
            $results[] = array(
                'query' => $query,
                'success' => $result !== false,
            );
        }

        return $results;
    }

    private function get_wp_timezone() {
        $timezone_string = get_option('timezone_string');

        if (!empty($timezone_string)) {
            try {
                return new DateTimeZone($timezone_string);
            } catch (Exception $e) {
            }
        }

        $offset = get_option('gmt_offset', 0);
        $hours = (int) $offset;
        $minutes = abs(($offset - $hours) * 60);
        $offset_string = sprintf('%+03d:%02d', $hours, $minutes);

        try {
            return new DateTimeZone($offset_string);
        } catch (Exception $e) {
            return new DateTimeZone('UTC');
        }
    }

    public function validate_record_data($record) {
        $validation = array(
            'valid' => true,
            'errors' => array(),
            'warnings' => array(),
        );

        if (empty($record['timestamp'])) {
            $validation['valid'] = false;
            $validation['errors'][] = 'Missing timestamp';
            return $validation;
        }

        if (strtotime($record['timestamp']) === false) {
            $validation['valid'] = false;
            $validation['errors'][] = 'Invalid timestamp format';
            return $validation;
        }

        $timestamp = strtotime($record['timestamp']);
        $current = current_time('timestamp');

        if ($timestamp > ($current + 3600)) {
            $validation['warnings'][] = 'Timestamp is in the future';
        }

        if ($timestamp < ($current - (365 * 86400))) {
            $validation['warnings'][] = 'Timestamp is more than 1 year old';
        }

        $numeric_fields = array('water_level_1', 'water_level_2', 'water_level_3', 'flow_rate_1', 'flow_rate_2', 'flow_rate_3', 'temperature_1');

        foreach ($numeric_fields as $field) {
            if (!isset($record[$field]) || $record[$field] === null) {
                continue;
            }

            $value = (float) $record[$field];

            if (strpos($field, 'water_level') !== false || strpos($field, 'flow_rate') !== false) {
                if ($value < 0 || $value > 500) {
                    $validation['warnings'][] = "Unusual value for {$field}: {$value}";
                }
            }

            if (strpos($field, 'temperature') !== false) {
                if ($value < -10 || $value > 40) {
                    $validation['warnings'][] = "Unusual temperature value: {$value}";
                }
            }
        }

        return $validation;
    }

    public function get_import_performance_stats() {
        $last_summary = get_option('orkla_last_import_summary', null);

        if (empty($last_summary)) {
            return null;
        }

        $stats = array(
            'timestamp' => isset($last_summary['timestamp']) ? $last_summary['timestamp'] : null,
            'duration' => null,
            'records_processed' => 0,
            'import_rate' => null,
        );

        if (isset($last_summary['summary'])) {
            $summary = $last_summary['summary'];
            $stats['records_processed'] = (isset($summary['imported']) ? $summary['imported'] : 0) +
                                         (isset($summary['updated']) ? $summary['updated'] : 0) +
                                         (isset($summary['skipped']) ? $summary['skipped'] : 0);
        }

        return $stats;
    }
}
