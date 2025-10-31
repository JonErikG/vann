<?php
if (!defined('ABSPATH')) {
    exit;
}

class Orkla_Supabase_Client {
    private $supabase_url;
    private $supabase_key;

    public function __construct() {
        // Try multiple methods to get credentials
        $this->supabase_url = defined('SUPABASE_URL') ? SUPABASE_URL : (getenv('VITE_SUPABASE_URL') ?: $_ENV['VITE_SUPABASE_URL'] ?? '');
        $this->supabase_key = defined('SUPABASE_ANON_KEY') ? SUPABASE_ANON_KEY : (getenv('VITE_SUPABASE_ANON_KEY') ?: $_ENV['VITE_SUPABASE_ANON_KEY'] ?? '');

        if (empty($this->supabase_url) || empty($this->supabase_key)) {
            error_log('Orkla Supabase Client: Missing credentials - URL: ' . (empty($this->supabase_url) ? 'missing' : 'set') . ', Key: ' . (empty($this->supabase_key) ? 'missing' : 'set'));
        } else {
            error_log('Orkla Supabase Client: Configured successfully');
        }
    }

    public function is_configured() {
        return !empty($this->supabase_url) && !empty($this->supabase_key);
    }

    public function query($table, $params = array()) {
        if (!$this->is_configured()) {
            error_log('Orkla Supabase Client: Not configured');
            return array('error' => 'Supabase not configured');
        }

        $url = trailingslashit($this->supabase_url) . 'rest/v1/' . $table;

        if (!empty($params['select'])) {
            $url = add_query_arg('select', $params['select'], $url);
        }

        if (!empty($params['order'])) {
            $url = add_query_arg('order', $params['order'], $url);
        }

        if (!empty($params['limit'])) {
            $url = add_query_arg('limit', $params['limit'], $url);
        }

        if (!empty($params['gte'])) {
            foreach ($params['gte'] as $field => $value) {
                $url = add_query_arg($field . '.gte', $value, $url);
            }
        }

        if (!empty($params['lte'])) {
            foreach ($params['lte'] as $field => $value) {
                $url = add_query_arg($field . '.lte', $value, $url);
            }
        }

        if (!empty($params['eq'])) {
            foreach ($params['eq'] as $field => $value) {
                $url = add_query_arg($field . '.eq', $value, $url);
            }
        }

        $args = array(
            'headers' => array(
                'apikey' => $this->supabase_key,
                'Authorization' => 'Bearer ' . $this->supabase_key,
                'Content-Type' => 'application/json',
            ),
            'timeout' => 15,
        );

        $response = wp_remote_get($url, $args);

        if (is_wp_error($response)) {
            error_log('Orkla Supabase Client: Request failed - ' . $response->get_error_message());
            return array('error' => $response->get_error_message());
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('Orkla Supabase Client: JSON decode error - ' . json_last_error_msg());
            return array('error' => 'Invalid JSON response');
        }

        return $data;
    }

    public function insert($table, $data) {
        if (!$this->is_configured()) {
            return array('error' => 'Supabase not configured');
        }

        $url = trailingslashit($this->supabase_url) . 'rest/v1/' . $table;

        $args = array(
            'headers' => array(
                'apikey' => $this->supabase_key,
                'Authorization' => 'Bearer ' . $this->supabase_key,
                'Content-Type' => 'application/json',
                'Prefer' => 'return=representation',
            ),
            'body' => json_encode($data),
            'method' => 'POST',
            'timeout' => 15,
        );

        $response = wp_remote_post($url, $args);

        if (is_wp_error($response)) {
            error_log('Orkla Supabase Client: Insert failed - ' . $response->get_error_message());
            return array('error' => $response->get_error_message());
        }

        $body = wp_remote_retrieve_body($response);
        return json_decode($body, true);
    }

    public function upsert($table, $data) {
        if (!$this->is_configured()) {
            return array('error' => 'Supabase not configured');
        }

        $url = trailingslashit($this->supabase_url) . 'rest/v1/' . $table;

        $args = array(
            'headers' => array(
                'apikey' => $this->supabase_key,
                'Authorization' => 'Bearer ' . $this->supabase_key,
                'Content-Type' => 'application/json',
                'Prefer' => 'resolution=merge-duplicates,return=representation',
            ),
            'body' => json_encode($data),
            'method' => 'POST',
            'timeout' => 15,
        );

        $response = wp_remote_post($url, $args);

        if (is_wp_error($response)) {
            error_log('Orkla Supabase Client: Upsert failed - ' . $response->get_error_message());
            return array('error' => $response->get_error_message());
        }

        $body = wp_remote_retrieve_body($response);
        return json_decode($body, true);
    }

    public function get_water_data($period = 'today') {
        $params = array(
            'select' => '*',
            'order' => 'measured_at.asc',
        );

        $now = current_time('mysql');

        if (preg_match('/^year:(\d{4})$/', $period, $matches)) {
            $year = (int) $matches[1];
            $params['gte'] = array('measured_at' => sprintf('%04d-01-01 00:00:00', $year));
            $params['lte'] = array('measured_at' => sprintf('%04d-12-31 23:59:59', $year));
        } else {
            switch ($period) {
                case 'today':
                    $latest = $this->get_latest_timestamp();
                    if ($latest) {
                        $date = date('Y-m-d', strtotime($latest));
                        $params['gte'] = array('date_recorded' => $date);
                        $params['lte'] = array('date_recorded' => $date);
                    } else {
                        $params['eq'] = array('date_recorded' => date('Y-m-d'));
                    }
                    break;

                case 'week':
                    $params['gte'] = array('measured_at' => date('Y-m-d H:i:s', strtotime('-7 days')));
                    break;

                case 'month':
                    $params['gte'] = array('measured_at' => date('Y-m-d H:i:s', strtotime('-1 month')));
                    break;

                case 'year':
                    $params['gte'] = array('measured_at' => date('Y-m-d H:i:s', strtotime('-1 year')));
                    break;

                default:
                    break;
            }
        }

        return $this->query('water_level_data', $params);
    }

    public function get_latest_measurement() {
        $params = array(
            'select' => '*',
            'order' => 'measured_at.desc',
            'limit' => 1,
        );

        $result = $this->query('water_level_data', $params);

        if (!empty($result) && is_array($result) && !isset($result['error'])) {
            return $result[0];
        }

        return null;
    }

    public function get_latest_timestamp() {
        $latest = $this->get_latest_measurement();
        return $latest ? $latest['measured_at'] : null;
    }

    public function get_available_years() {
        $params = array(
            'select' => 'date_recorded',
            'order' => 'date_recorded.desc',
        );

        $result = $this->query('water_level_data', $params);

        if (isset($result['error']) || empty($result)) {
            return array();
        }

        $years = array();
        foreach ($result as $row) {
            if (!empty($row['date_recorded'])) {
                $year = date('Y', strtotime($row['date_recorded']));
                if (!in_array($year, $years)) {
                    $years[] = $year;
                }
            }
        }

        rsort($years);
        return $years;
    }
}
