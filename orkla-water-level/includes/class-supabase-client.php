<?php
if (!defined('ABSPATH')) {
    exit;
}

class Orkla_Supabase_Client {
    private $supabase_url;
    private $supabase_key;

    public function __construct() {
        $this->supabase_url = defined('SUPABASE_URL') ? SUPABASE_URL : getenv('VITE_SUPABASE_URL');
        $this->supabase_key = defined('SUPABASE_ANON_KEY') ? SUPABASE_ANON_KEY : getenv('VITE_SUPABASE_SUPABASE_ANON_KEY');

        if (empty($this->supabase_url) || empty($this->supabase_key)) {
            error_log('Orkla Plugin: Supabase credentials not configured');
        }
    }

    public function query($table, $params = array()) {
        if (empty($this->supabase_url) || empty($this->supabase_key)) {
            return array('error' => 'Supabase not configured');
        }

        $url = trailingslashit($this->supabase_url) . 'rest/v1/' . $table;

        $query_params = array();
        if (isset($params['select'])) {
            $query_params['select'] = $params['select'];
        }
        if (isset($params['order'])) {
            $query_params['order'] = $params['order'];
        }
        if (isset($params['limit'])) {
            $query_params['limit'] = $params['limit'];
        }
        if (isset($params['filters'])) {
            foreach ($params['filters'] as $key => $value) {
                $query_params[$key] = $value;
            }
        }

        if (!empty($query_params)) {
            $url .= '?' . http_build_query($query_params);
        }

        $response = wp_remote_get($url, array(
            'headers' => array(
                'apikey' => $this->supabase_key,
                'Authorization' => 'Bearer ' . $this->supabase_key,
                'Content-Type' => 'application/json'
            ),
            'timeout' => 15
        ));

        if (is_wp_error($response)) {
            error_log('Orkla Plugin: Supabase query error - ' . $response->get_error_message());
            return array('error' => $response->get_error_message());
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('Orkla Plugin: JSON decode error - ' . json_last_error_msg());
            return array('error' => 'Invalid JSON response');
        }

        return $data;
    }

    public function get_water_data_by_period($period = 'today') {
        $now = current_time('timestamp');

        switch ($period) {
            case 'today':
                $start = date('Y-m-d 00:00:00', $now);
                break;
            case 'week':
                $start = date('Y-m-d H:i:s', strtotime('-7 days', $now));
                break;
            case 'month':
                $start = date('Y-m-d H:i:s', strtotime('-30 days', $now));
                break;
            case 'year':
                $start = date('Y-m-d H:i:s', strtotime('-1 year', $now));
                break;
            default:
                if (strpos($period, 'year:') === 0) {
                    $year = intval(str_replace('year:', '', $period));
                    $start = $year . '-01-01 00:00:00';
                    $end = $year . '-12-31 23:59:59';
                } else {
                    $start = date('Y-m-d 00:00:00', $now);
                }
        }

        $filters = array(
            'measured_at' => 'gte.' . $start
        );

        if (isset($end)) {
            $filters['measured_at'] .= ',lte.' . $end;
        }

        $result = $this->query('water_level_data', array(
            'select' => '*',
            'filters' => $filters,
            'order' => 'measured_at.asc',
            'limit' => 10000
        ));

        if (isset($result['error'])) {
            return $result;
        }

        $formatted_data = array();
        foreach ($result as $row) {
            $formatted_data[] = array(
                'timestamp' => $row['measured_at'],
                'vannforing_storsteinsholen' => $row['vannforing_storsteinsholen'],
                'vannforing_brattset' => $row['vannforing_brattset'],
                'vannforing_syrstad' => $row['vannforing_syrstad'],
                'produksjon_brattset' => $row['produksjon_brattset'],
                'produksjon_grana' => $row['produksjon_grana'],
                'produksjon_svorkmo' => $row['produksjon_svorkmo'],
                'rennebu_oppstroms' => $row['rennebu_oppstroms'],
                'nedstroms_svorkmo' => $row['nedstroms_svorkmo'],
                'water_temperature' => $row['water_temperature']
            );
        }

        return $formatted_data;
    }

    public function insert_water_data($data) {
        if (empty($this->supabase_url) || empty($this->supabase_key)) {
            return array('error' => 'Supabase not configured');
        }

        $url = trailingslashit($this->supabase_url) . 'rest/v1/water_level_data';

        $response = wp_remote_post($url, array(
            'headers' => array(
                'apikey' => $this->supabase_key,
                'Authorization' => 'Bearer ' . $this->supabase_key,
                'Content-Type' => 'application/json',
                'Prefer' => 'return=minimal'
            ),
            'body' => json_encode($data),
            'timeout' => 30
        ));

        if (is_wp_error($response)) {
            error_log('Orkla Plugin: Supabase insert error - ' . $response->get_error_message());
            return array('error' => $response->get_error_message());
        }

        $status_code = wp_remote_retrieve_response_code($response);

        if ($status_code === 201 || $status_code === 200) {
            return array('success' => true);
        }

        $body = wp_remote_retrieve_body($response);
        error_log('Orkla Plugin: Supabase insert failed - Status: ' . $status_code . ', Body: ' . $body);

        return array('error' => 'Insert failed with status ' . $status_code);
    }

    public function batch_insert_water_data($data_array) {
        if (empty($data_array)) {
            return array('success' => true, 'inserted' => 0);
        }

        $batch_size = 100;
        $batches = array_chunk($data_array, $batch_size);
        $total_inserted = 0;
        $errors = array();

        foreach ($batches as $batch) {
            $result = $this->insert_water_data($batch);

            if (isset($result['error'])) {
                $errors[] = $result['error'];
            } else {
                $total_inserted += count($batch);
            }
        }

        if (!empty($errors)) {
            return array(
                'success' => false,
                'inserted' => $total_inserted,
                'errors' => $errors
            );
        }

        return array(
            'success' => true,
            'inserted' => $total_inserted
        );
    }
}
