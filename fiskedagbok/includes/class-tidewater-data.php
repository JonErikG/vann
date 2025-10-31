<?php
/**
 * Tidewater Data Integration with Kartverket API
 * 
 * Handles fetching and storing tidewater data from Kartverket's API
 * Associated with catch records.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Fiskedagbok_Tidewater_Data {

    const API_BASE = 'https://vannstand.kartverket.no/api/';
    const LEGACY_API_ENDPOINT = 'https://vannstand.kartverket.no/tideapi.php';
    const CACHE_DURATION = 3600; // Cache for 1 hour
    const REQUEST_TIMEOUT = 15;
    
    /**
     * Default tidewater stations (latitude, longitude)
     */
    private static $default_stations = array(
        'Trondheim' => array(
            'latitude' => 63.436484,
            'longitude' => 10.391669,
            'station_code' => 'TRD'
        ),
    );

    /**
     * Permanent station catalogue (code => data)
     *
     * Source: https://vannstand.kartverket.no/tideapi.php?tide_request=stationlist&type=perm
     */
    private static $permanent_stations = array(
        'ANX' => array('name' => 'Andenes', 'latitude' => 69.326067, 'longitude' => 16.134848),
        'BGO' => array('name' => 'Bergen', 'latitude' => 60.398046, 'longitude' => 5.320487),
        'BOO' => array('name' => 'Bodø', 'latitude' => 67.29233, 'longitude' => 14.39977),
        'BRJ' => array('name' => 'Bruravik', 'latitude' => 60.492094, 'longitude' => 6.893949),
        'HFT' => array('name' => 'Hammerfest', 'latitude' => 70.66475, 'longitude' => 23.67869),
        'HAR' => array('name' => 'Harstad', 'latitude' => 68.801261, 'longitude' => 16.548236),
        'HEI' => array('name' => 'Heimsjøen', 'latitude' => 63.425224, 'longitude' => 9.101504),
        'HRO' => array('name' => 'Helgeroa', 'latitude' => 58.995212, 'longitude' => 9.856379),
        'HVG' => array('name' => 'Honningsvåg', 'latitude' => 70.980318, 'longitude' => 25.972697),
        'KAB' => array('name' => 'Kabelvåg', 'latitude' => 68.212639, 'longitude' => 14.482149),
        'KSU' => array('name' => 'Kristiansund', 'latitude' => 63.11392, 'longitude' => 7.73614),
        'LEH' => array('name' => 'Leirvik', 'latitude' => 59.766394, 'longitude' => 5.50367),
        'MSU' => array('name' => 'Mausund', 'latitude' => 63.869331, 'longitude' => 8.665231),
        'MAY' => array('name' => 'Måløy', 'latitude' => 61.933776, 'longitude' => 5.11331),
        'NVK' => array('name' => 'Narvik', 'latitude' => 68.428286, 'longitude' => 17.425759),
        'NYA' => array('name' => 'Ny-Ålesund', 'latitude' => 78.928545, 'longitude' => 11.938015),
        'OSC' => array('name' => 'Oscarsborg', 'latitude' => 59.678073, 'longitude' => 10.604861),
        'OSL' => array('name' => 'Oslo', 'latitude' => 59.908559, 'longitude' => 10.73451),
        'RVK' => array('name' => 'Rørvik', 'latitude' => 64.859456, 'longitude' => 11.230107),
        'SBG' => array('name' => 'Sandnes', 'latitude' => 58.868232, 'longitude' => 5.746613),
        'SIE' => array('name' => 'Sirevåg', 'latitude' => 58.5052, 'longitude' => 5.791602),
        'SOY' => array('name' => 'Solumstrand', 'latitude' => 59.710622, 'longitude' => 10.273018),
        'SVG' => array('name' => 'Stavanger', 'latitude' => 58.974339, 'longitude' => 5.730121),
        'TRG' => array('name' => 'Tregde', 'latitude' => 58.006377, 'longitude' => 7.554759),
        'TOS' => array('name' => 'Tromsø', 'latitude' => 69.64611, 'longitude' => 18.95479),
        'TRD' => array('name' => 'Trondheim', 'latitude' => 63.436484, 'longitude' => 10.391669),
        'TAZ' => array('name' => 'Træna', 'latitude' => 66.496624, 'longitude' => 12.088633),
        'VAW' => array('name' => 'Vardø', 'latitude' => 70.374978, 'longitude' => 31.104015),
        'VIK' => array('name' => 'Viker', 'latitude' => 59.036046, 'longitude' => 10.949769),
        'AES' => array('name' => 'Ålesund', 'latitude' => 62.469414, 'longitude' => 6.151946),
    );

    /**
     * Fetch tidewater data from Kartverket API for given coordinates and time
     * 
     * @param float $latitude
     * @param float $longitude
     * @param string $date_from (Y-m-d format)
     * @param string $date_to (Y-m-d format)
     * @param string $data_type 'all', 'observations', 'predictions'
     * 
     * @return array|WP_Error
     */
    public static function fetch_tidewater_data($latitude, $longitude, $date_from, $date_to, $data_type = 'all') {
        
        // Validate inputs
        if (!is_numeric($latitude) || !is_numeric($longitude)) {
            return new WP_Error(
                'invalid_coordinates',
                __('Ugyldig koordinater for tidevannsdata', 'fiskedagbok')
            );
        }

        // Build API URL
        $url = self::build_api_url(
            $latitude,
            $longitude,
            $date_from,
            $date_to,
            $data_type
        );

        // Check cache first
        $cache_key = 'fiskedagbok_tidewater_' . md5($url);
        $cached = wp_cache_get($cache_key);
        
        if ($cached !== false) {
            error_log('Fiskedagbok: Tidewater data from cache for ' . $latitude . ',' . $longitude);
            return $cached;
        }

        error_log('Fiskedagbok: Fetching tidewater data from ' . $url);

        // Make request
        $response = wp_remote_get($url, array(
            'timeout' => self::REQUEST_TIMEOUT,
            'sslverify' => true,
        ));

        if (is_wp_error($response)) {
            error_log('Fiskedagbok: Tidewater API error: ' . $response->get_error_message());
            return $response;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code !== 200) {
            return new WP_Error(
                'api_error',
                sprintf(
                    __('Kartverket API returnerte status %d', 'fiskedagbok'),
                    $status_code
                )
            );
        }

        $body = wp_remote_retrieve_body($response);
        
        // Parse XML response
        $data = self::parse_tidewater_xml($body);

        if (is_wp_error($data)) {
            return $data;
        }

        // Cache result
        wp_cache_set($cache_key, $data, '', self::CACHE_DURATION);

        error_log('Fiskedagbok: Tidewater data fetched successfully, ' . count($data) . ' points');
        
        return $data;
    }

    /**
     * Fetch tidewater data for a specific Kartverket station using the legacy tideapi.php endpoint.
     *
     * @param string $station_code Three-letter station code (e.g. TRD).
     * @param string $fromtime     ISO string (Y-m-d\TH:i) inclusive start time.
     * @param string $totime       ISO string (Y-m-d\TH:i) inclusive end time.
     * @param string $data_type    all|obs|pre (defaults to all).
     * @param int    $interval     Measurement interval in minutes (10 or 60 typically).
     *
     * @return array|WP_Error Normalised data points or error.
     */
    private static function fetch_station_tidewater_series($station_code, $fromtime, $totime, $data_type = 'all', $interval = 10) {
        $station_code = strtoupper(trim($station_code));

        if (empty($station_code) || strlen($station_code) !== 3) {
            return new WP_Error('invalid_station', __('Ugyldig stasjonskode for tidevannsdata', 'fiskedagbok'));
        }

        if (empty($fromtime) || empty($totime)) {
            return new WP_Error('missing_interval', __('Manglende fra- eller til-tid for tidevannsoppslag', 'fiskedagbok'));
        }

        $interval = (int) $interval;
        if ($interval !== 10 && $interval !== 60) {
            $interval = 10;
        }

        $datatype_param = strtolower($data_type);
        if (!in_array($datatype_param, array('all', 'obs', 'pre'), true)) {
            $datatype_param = 'all';
        }

        static $station_series_cache = array();

        $cache_key = 'fiskedagbok_station_series_' . md5(implode('|', array($station_code, $fromtime, $totime, $datatype_param, $interval)));

        if (isset($station_series_cache[$cache_key])) {
            return $station_series_cache[$cache_key];
        }

        $cached_series = wp_cache_get($cache_key, 'fiskedagbok_tidewater');
        if ($cached_series !== false) {
            $station_series_cache[$cache_key] = $cached_series;
            return $cached_series;
        }

        $params = array(
            'tide_request' => 'stationdata',
            'stationcode' => $station_code,
            'fromtime' => $fromtime,
            'totime' => $totime,
            'interval' => $interval,
            'datatype' => $datatype_param,
            'refcode' => 'cd',
            'lang' => 'nb'
        );

        $url = self::LEGACY_API_ENDPOINT . '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);

        error_log('Fiskedagbok: Fetching station tidewater data from ' . $url);

        $response = wp_remote_get($url, array(
            'timeout' => self::REQUEST_TIMEOUT,
            'sslverify' => true,
        ));

        if (is_wp_error($response)) {
            error_log('Fiskedagbok: Station tidewater API error: ' . $response->get_error_message());
            return $response;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code !== 200) {
            return new WP_Error(
                'api_error',
                sprintf(
                    __('Kartverket station API returnerte status %d', 'fiskedagbok'),
                    $status_code
                )
            );
        }

        $body = wp_remote_retrieve_body($response);

        if (empty($body)) {
            return array();
        }

        $dom = new DOMDocument();
        libxml_use_internal_errors(true);

        if (!$dom->loadXML($body)) {
            $errors = libxml_get_errors();
            libxml_clear_errors();

            return new WP_Error(
                'xml_parse_error',
                sprintf(
                    __('Kunne ikke tolke stationdata-respons: %s', 'fiskedagbok'),
                    !empty($errors[0]) ? $errors[0]->message : 'Ukjent feil'
                )
            );
        }

        $xpath = new DOMXPath($dom);
        $location_node = $xpath->query('//stationdata/location')->item(0);

        if (!$location_node) {
            error_log('Fiskedagbok: Station tidewater data mangler location-node for stasjon ' . $station_code);
            return array();
        }

        $station_name = $location_node->getAttribute('name');
        $station_code_actual = $location_node->getAttribute('code');

        $timezone = function_exists('wp_timezone') ? wp_timezone() : new DateTimeZone('Europe/Oslo');

        $data_points = array();
        $data_nodes = $xpath->query('//stationdata/location/data');

        foreach ($data_nodes as $data_node) {
            /** @var DOMElement $data_node */
            $type = strtolower($data_node->getAttribute('type'));
            $is_prediction = ($type !== 'observation');

            foreach ($data_node->getElementsByTagName('waterlevel') as $waterlevel_node) {
                /** @var DOMElement $waterlevel_node */
                $value_cm = $waterlevel_node->getAttribute('value');
                $time_string = $waterlevel_node->getAttribute('time');

                if ($time_string === '') {
                    continue;
                }

                $date = date_create($time_string);
                if (!$date) {
                    continue;
                }

                $date->setTimezone($timezone);
                $normalized_timestamp = $date->format('Y-m-d H:i:s');

                $value_cm_float = is_numeric($value_cm) ? (float) $value_cm : null;
                if ($value_cm_float === null) {
                    continue;
                }

                $data_points[] = array(
                    'timestamp' => $normalized_timestamp,
                    'water_level' => round($value_cm_float / 100, 3),
                    'is_prediction' => $is_prediction,
                    'station_name' => $station_name,
                    'station_code' => $station_code_actual ?: $station_code,
                );
            }
        }

    wp_cache_set($cache_key, $data_points, 'fiskedagbok_tidewater', HOUR_IN_SECONDS);
    $station_series_cache[$cache_key] = $data_points;

    return $data_points;
    }

    /**
     * Build Kartverket API URL
     * 
     * @param float $latitude
     * @param float $longitude
     * @param string $date_from
     * @param string $date_to
     * @param string $data_type
     * 
     * @return string
     */
    private static function build_api_url($latitude, $longitude, $date_from, $date_to, $data_type = 'all') {
        
        $params = array(
            'lat' => round($latitude, 6),
            'lon' => round($longitude, 6),
            'fromtime' => $date_from . 'T00:00:00',
            'totime' => $date_to . 'T23:59:59',
            'datatype' => $data_type, // 'all', 'observations', 'predictions'
            'referencelevel' => 'chart', // 'chart' or 'mean'
            'format' => 'xml',
            'lang' => 'no'
        );

        return self::API_BASE . 'leveldata?' . http_build_query($params);
    }

    /**
     * Parse XML response from Kartverket API
     * 
     * @param string $xml
     * 
     * @return array|WP_Error
     */
    private static function parse_tidewater_xml($xml) {
        
        $data_points = array();

        try {
            $dom = new DOMDocument();
            
            // Suppress warnings for malformed XML
            libxml_use_internal_errors(true);
            
            if (!$dom->loadXML($xml)) {
                $errors = libxml_get_errors();
                libxml_clear_errors();
                
                return new WP_Error(
                    'xml_parse_error',
                    sprintf(
                        __('Kunne ikke tolke API-respons: %s', 'fiskedagbok'),
                        !empty($errors[0]) ? $errors[0]->message : 'Ukjent feil'
                    )
                );
            }

            $xpath = new DOMXPath($dom);
            
            // Get station info
            $station_elem = $xpath->query('//station')->item(0);
            $station_name = $station_elem ? $station_elem->getAttribute('name') : 'Ukjent stasjon';
            $station_code = $station_elem ? $station_elem->getAttribute('code') : null;

            // Parse water level data points
            $level_nodes = $xpath->query('//location/waterlevel');

            if ($level_nodes->length === 0) {
                error_log('Fiskedagbok: No waterlevel nodes found in API response');
            }

            foreach ($level_nodes as $level_node) {
                $time = $level_node->getAttribute('time');
                $value = (float) $level_node->getAttribute('value');
                $prediction = $level_node->getAttribute('prediction') === 'true';

                $data_points[] = array(
                    'timestamp' => $time,
                    'water_level' => $value,
                    'is_prediction' => $prediction,
                    'station_name' => $station_name,
                    'station_code' => $station_code,
                );
            }

        } catch (Exception $e) {
            error_log('Fiskedagbok: XML parsing exception: ' . $e->getMessage());
            return new WP_Error(
                'xml_exception',
                sprintf(
                    __('Feil ved tolking av vannstandsdata: %s', 'fiskedagbok'),
                    $e->getMessage()
                )
            );
        }

        return $data_points;
    }

    /**
     * Get default tidewater station used when no specific mapping exists
     *
     * @return array Station data
     */
    public static function get_default_station() {
        $stations = self::$default_stations;
        reset($stations);
        $name = key($stations);
        
        return array_merge(
            array('name' => $name),
            $stations[$name]
        );
    }

    /**
     * Get nearest tidewater station for given coordinates
     * 
     * @param float $latitude
     * @param float $longitude
     * 
     * @return array|WP_Error Station data with distance in km
     */
    public static function get_nearest_station($latitude, $longitude) {
        
        if (!is_numeric($latitude) || !is_numeric($longitude)) {
            return new WP_Error('invalid_coordinates', __('Ugyldig koordinater', 'fiskedagbok'));
        }

        $nearest = null;
        $min_distance = PHP_INT_MAX;

        foreach (self::$default_stations as $name => $station) {
            $distance = self::haversine_distance(
                $latitude,
                $longitude,
                $station['latitude'],
                $station['longitude']
            );

            if ($distance < $min_distance) {
                $min_distance = $distance;
                $nearest = array_merge(
                    array('name' => $name, 'distance_km' => round($distance, 1)),
                    $station
                );
            }
        }

        return $nearest;
    }

    /**
     * Find nearest permanent tidewater station from Kartverket catalogue.
     *
     * @param float $latitude
     * @param float $longitude
     * @return array|null
     */
    public static function get_nearest_permanent_station($latitude, $longitude) {
        if (!is_numeric($latitude) || !is_numeric($longitude)) {
            return null;
        }

        $nearest = null;
        $min_distance = PHP_INT_MAX;

        foreach (self::$permanent_stations as $code => $station) {
            $distance = self::haversine_distance(
                $latitude,
                $longitude,
                $station['latitude'],
                $station['longitude']
            );

            if ($distance < $min_distance) {
                $min_distance = $distance;
                $nearest = array(
                    'code' => $code,
                    'name' => $station['name'],
                    'latitude' => $station['latitude'],
                    'longitude' => $station['longitude'],
                    'distance_km' => round($distance, 1)
                );
            }
        }

        return $nearest;
    }

    /**
     * Calculate distance between two coordinates using Haversine formula
     * 
     * @param float $lat1
     * @param float $lon1
     * @param float $lat2
     * @param float $lon2
     * 
     * @return float Distance in kilometers
     */
    private static function haversine_distance($lat1, $lon1, $lat2, $lon2) {
        $earth_radius_km = 6371;
        $lat_delta = deg2rad($lat2 - $lat1);
        $lon_delta = deg2rad($lon2 - $lon1);

        $a = sin($lat_delta / 2) * sin($lat_delta / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($lon_delta / 2) * sin($lon_delta / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earth_radius_km * $c;
    }

    /**
     * Save tidewater data to database for a catch
     *
     * @param int   $catch_id
     * @param array $tidewater_data
     * @return int|WP_Error
     */
    public static function save_catch_tidewater_data($catch_id, $tidewater_data) {
        global $wpdb;

        if (empty($catch_id) || !is_array($tidewater_data) || empty($tidewater_data)) {
            error_log('Fiskedagbok: save_catch_tidewater_data - empty input. catch_id=' . $catch_id . ', data count=' . count($tidewater_data));
            return 0;
        }

        $table_name = $wpdb->prefix . 'fiskedagbok_tidewater_data';
        $inserted = 0;

        $tidewater_data = self::select_relevant_tidewater_points($catch_id, $tidewater_data);

        $wpdb->delete($table_name, array('catch_id' => (int) $catch_id), array('%d'));

        error_log('Fiskedagbok: save_catch_tidewater_data - starting insert for catch ' . $catch_id . ' with ' . count($tidewater_data) . ' data points');

        foreach ($tidewater_data as $data_point) {
            $result = $wpdb->insert(
                $table_name,
                array(
                    'catch_id' => (int) $catch_id,
                    'station_name' => isset($data_point['station_name']) ? sanitize_text_field($data_point['station_name']) : null,
                    'station_code' => isset($data_point['station_code']) ? sanitize_text_field($data_point['station_code']) : null,
                    'water_level' => isset($data_point['water_level']) ? (float) $data_point['water_level'] : null,
                    'is_prediction' => isset($data_point['is_prediction']) ? (int) $data_point['is_prediction'] : 0,
                    'timestamp' => isset($data_point['timestamp']) ? sanitize_text_field($data_point['timestamp']) : null,
                    'created_at' => current_time('mysql'),
                ),
                array('%d', '%s', '%s', '%f', '%d', '%s', '%s')
            );

            if ($result !== false) {
                $inserted++;
            } else {
                error_log('Fiskedagbok: Failed to insert tidewater data for catch ' . $catch_id . ': ' . $wpdb->last_error);
            }
        }

        error_log('Fiskedagbok: save_catch_tidewater_data - completed, inserted ' . $inserted . ' records for catch ' . $catch_id);
        return $inserted;
    }

    /**
     * Reduce raw tidewater dataset to a minimal subset that still captures key information
     *
     * @param int   $catch_id
     * @param array $tidewater_data
     * @return array
     */
    private static function select_relevant_tidewater_points($catch_id, $tidewater_data) {
        if (!is_array($tidewater_data) || empty($tidewater_data)) {
            return $tidewater_data;
        }

        $parsed_points = array();

        foreach ($tidewater_data as $point) {
            if (!is_array($point)) {
                continue;
            }

            if (empty($point['timestamp'])) {
                continue;
            }

            $timestamp_string = str_replace('T', ' ', trim($point['timestamp']));
            $timestamp = strtotime($timestamp_string);

            if (!$timestamp) {
                continue;
            }

            if (!isset($point['water_level']) || !is_numeric($point['water_level'])) {
                continue;
            }

            $normalized = $point;
            $normalized['timestamp'] = date('Y-m-d H:i:s', $timestamp);

            $parsed_points[] = array(
                'timestamp' => $timestamp,
                'level' => (float) $point['water_level'],
                'data' => $normalized
            );
        }

        if (empty($parsed_points)) {
            return $tidewater_data;
        }

        usort($parsed_points, function($a, $b) {
            if ($a['timestamp'] === $b['timestamp']) {
                return 0;
            }
            return ($a['timestamp'] < $b['timestamp']) ? -1 : 1;
        });

        $catch_timestamp = self::get_catch_timestamp($catch_id);

        $nearest_point = null;
        if ($catch_timestamp !== null) {
            $nearest_diff = null;
            foreach ($parsed_points as $point) {
                $diff = abs($point['timestamp'] - $catch_timestamp);
                if ($nearest_diff === null || $diff < $nearest_diff) {
                    $nearest_diff = $diff;
                    $nearest_point = $point;
                }
            }
        }

        if ($nearest_point === null) {
            $nearest_point = $parsed_points[0];
        }

        $global_high = $parsed_points[0];
        $global_low = $parsed_points[0];

        foreach ($parsed_points as $point) {
            if ($point['level'] > $global_high['level']) {
                $global_high = $point;
            }
            if ($point['level'] < $global_low['level']) {
                $global_low = $point;
            }
        }

        $high_before_catch = null;

        if ($catch_timestamp !== null && count($parsed_points) >= 2) {
            for ($i = 1; $i < count($parsed_points) - 1; $i++) {
                $prev = $parsed_points[$i - 1];
                $curr = $parsed_points[$i];
                $next = $parsed_points[$i + 1];

                $is_peak = $curr['level'] >= $prev['level'] && $curr['level'] >= $next['level'];

                if ($is_peak && $curr['timestamp'] <= $catch_timestamp) {
                    if ($high_before_catch === null || $curr['timestamp'] > $high_before_catch['timestamp']) {
                        $high_before_catch = $curr;
                    }
                }
            }

            if ($high_before_catch === null && $global_high['timestamp'] <= $catch_timestamp) {
                $high_before_catch = $global_high;
            }
        }

        if ($high_before_catch === null) {
            $high_before_catch = $global_high;
        }

        $selected_points = array();

        $add_point = function($candidate, $role) use (&$selected_points) {
            if (!$candidate) {
                return;
            }

            $key = $candidate['data']['timestamp'];

            if (!isset($selected_points[$key])) {
                $selected_points[$key] = $candidate['data'];
                $selected_points[$key]['tide_role'] = $role;
            } else {
                $existing_role = isset($selected_points[$key]['tide_role']) ? $selected_points[$key]['tide_role'] : '';
                $selected_points[$key]['tide_role'] = $existing_role ? $existing_role . ',' . $role : $role;
            }

            $station_code_value = '';
            if (isset($selected_points[$key]['station_code']) && $selected_points[$key]['station_code'] !== null) {
                $station_code_value = (string) $selected_points[$key]['station_code'];
            }

            if ($station_code_value === '' && isset($candidate['data']['station_code']) && $candidate['data']['station_code'] !== null) {
                $selected_points[$key]['station_code'] = (string) $candidate['data']['station_code'];
            }
        };

        $add_point($nearest_point, 'nearest');
        $add_point($high_before_catch, 'high');
        $add_point($global_low, 'low');

        if (empty($selected_points)) {
            return $tidewater_data;
        }

        uasort($selected_points, function($a, $b) {
            $tsA = strtotime($a['timestamp']);
            $tsB = strtotime($b['timestamp']);

            if ($tsA === $tsB) {
                return 0;
            }

            return ($tsA < $tsB) ? -1 : 1;
        });

        return array_values($selected_points);
    }

    /**
     * Fetch catch timestamp as UNIX epoch
     *
     * @param int $catch_id
     * @return int|null
     */
    private static function get_catch_timestamp($catch_id) {
        if (empty($catch_id)) {
            return null;
        }

        global $wpdb;
        $catches_table = $wpdb->prefix . 'fiskedagbok_catches';

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT date, time_of_day FROM $catches_table WHERE id = %d",
                (int) $catch_id
            )
        );

        if (!$row || empty($row->date)) {
            return null;
        }

        $time = $row->time_of_day;

        if (empty($time)) {
            $time = '12:00:00';
        } elseif (strlen($time) === 5) {
            $time .= ':00';
        }

        $timestamp = strtotime($row->date . ' ' . $time);

        return $timestamp ?: null;
    }

    /**
     * Get tidewater data for a catch
     *
     * @param int $catch_id
     *
     * @return array
     */
    public static function get_catch_tidewater_data($catch_id, $reference_time = null, $window_hours = 48) {
        global $wpdb;
        $table_name = 'wpjd_fiskedagbok_tidewater_data'; // Use the correct table name

        $query = "SELECT * FROM $table_name WHERE catch_id = %d";
        $params = [(int) $catch_id];

        if ($reference_time) {
            $start_time = date('Y-m-d H:i:s', strtotime($reference_time . ' -' . ($window_hours / 2) . ' hours'));
            $end_time = date('Y-m-d H:i:s', strtotime($reference_time . ' +' . ($window_hours / 2) . ' hours'));
            $query .= " AND timestamp BETWEEN %s AND %s";
            $params[] = $start_time;
            $params[] = $end_time;
        }

        $query .= " ORDER BY timestamp ASC";

        $data = $wpdb->get_results(
            $wpdb->prepare($query, $params)
        );

        return $data ?: array();
    }

    /**
     * Get tidewater statistics for a date range
     *
     * @param string $date_from
     * @param string $date_to
     * @param string $station_name
     *
     * @return array
     */
    public static function get_tidewater_statistics($date_from, $date_to, $station_name = null) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'fiskedagbok_tidewater_data';

        $where = $wpdb->prepare(
            "DATE(timestamp) BETWEEN %s AND %s",
            $date_from,
            $date_to
        );

        if (!empty($station_name)) {
            $where .= $wpdb->prepare(" AND station_name = %s", $station_name);
        }

        $stats = $wpdb->get_row(
            "SELECT 
                COUNT(*) as total_points,
                MAX(water_level) as max_level,
                MIN(water_level) as min_level,
                AVG(water_level) as avg_level,
                COUNT(CASE WHEN is_prediction = 0 THEN 1 END) as observation_count,
                COUNT(CASE WHEN is_prediction = 1 THEN 1 END) as prediction_count
            FROM $table_name
            WHERE $where"
        );

        return $stats ?: array();
    }

    /**
     * Refresh/fetch tidewater data asynchronously for a catch
     */
    public static function queue_tidewater_fetch($catch_id, $latitude, $longitude, $catch_date, $station_code = null) {
        $station_hint = null;

        if (!empty($station_code) && isset(self::$permanent_stations[strtoupper($station_code)])) {
            $station_hint = strtoupper($station_code);
        } else {
            $nearest = self::get_nearest_permanent_station($latitude, $longitude);
            if ($nearest) {
                $station_hint = $nearest['code'];
            }
        }

        wp_schedule_single_event(
            time() + 5,
            'fiskedagbok_fetch_tidewater_async',
            array(
                (int) $catch_id,
                (float) $latitude,
                (float) $longitude,
                sanitize_text_field($catch_date),
                $station_hint
            )
        );

        error_log('Fiskedagbok: Scheduled tidewater fetch for catch ' . $catch_id . ' (station hint: ' . ($station_hint ?: 'none') . ')');

        return true;
    }

    /**
     * Async handler for fetching tidewater data
     */
    public static function handle_async_tidewater_fetch($catch_id, $latitude, $longitude, $catch_date, $station_code = null) {
        error_log('Fiskedagbok: Processing async tidewater fetch for catch ' . $catch_id . ', date=' . $catch_date . ', station_hint=' . ($station_code ?: 'none'));

        $timezone = function_exists('wp_timezone') ? wp_timezone() : new DateTimeZone('Europe/Oslo');

        try {
            $catch_date_obj = new DateTimeImmutable($catch_date, $timezone);
        } catch (Exception $exception) {
            error_log('Fiskedagbok: Invalid catch date provided to tide fetch (' . $catch_date . '): ' . $exception->getMessage());
            $catch_date_obj = new DateTimeImmutable('now', $timezone);
        }

        $from = $catch_date_obj->modify('-1 day')->setTime(0, 0);
        $to = $catch_date_obj->modify('+1 day')->setTime(23, 59);

        if (empty($station_code) || !isset(self::$permanent_stations[$station_code])) {
            $nearest = self::get_nearest_permanent_station($latitude, $longitude);
            if ($nearest) {
                $station_code = $nearest['code'];
            }
        }

        if (empty($station_code)) {
            $station_code = 'TRD';
        }

        $series = self::fetch_station_tidewater_series(
            $station_code,
            $from->format('Y-m-d\TH:i'),
            $to->format('Y-m-d\TH:i'),
            'all',
            10
        );

        if (is_wp_error($series)) {
            error_log('Fiskedagbok: Station tide fetch error (' . $station_code . '): ' . $series->get_error_message());
            $series = array();
        }

        if (empty($series)) {
            $fallback_series = self::fetch_station_tidewater_series(
                $station_code,
                $from->format('Y-m-d\TH:i'),
                $to->format('Y-m-d\TH:i'),
                'pre',
                10
            );

            if (!is_wp_error($fallback_series) && !empty($fallback_series)) {
                $series = $fallback_series;
            }
        }

        if (empty($series)) {
            $location_series = self::fetch_tidewater_data(
                $latitude,
                $longitude,
                $from->format('Y-m-d'),
                $to->format('Y-m-d'),
                'all'
            );

            if (!is_wp_error($location_series) && !empty($location_series)) {
                $series = $location_series;
            }
        }

        if (empty($series)) {
            $series = self::generate_estimated_tidewater_data($catch_date_obj->format('Y-m-d'));
        }

        if (!is_array($series) || empty($series)) {
            error_log('Fiskedagbok: No tidewater data generated for catch ' . $catch_id);
            return;
        }

        $inserted = self::save_catch_tidewater_data($catch_id, $series);
        error_log('Fiskedagbok: Inserted ' . $inserted . ' tidewater data points for catch ' . $catch_id . ' (station ' . $station_code . ')');
    }

    /**
     * Generate estimated tidewater data based on lunar cycles (fallback)
     *
     * @param string $date
     * @return array
     */
    private static function generate_estimated_tidewater_data($date) {
        $data = array();

        error_log('Fiskedagbok: generate_estimated_tidewater_data - input date=' . $date);

        $timestamp = strtotime($date . ' 00:00:00');
        if (!$timestamp) {
            error_log('Fiskedagbok: generate_estimated_tidewater_data - failed to parse date: ' . $date);
            return array();
        }

        error_log('Fiskedagbok: generate_estimated_tidewater_data - parsed timestamp=' . $timestamp);

        $reference_new_moon = 946800000; // Jan 6, 2000 00:00:00 UTC
        $lunar_cycle = 2551442.8571; // Lunar month in seconds (29.53 days)
        $days_into_cycle = fmod(($timestamp - $reference_new_moon), $lunar_cycle) / 86400;

        for ($hour = 0; $hour < 24; $hour++) {
            $hourly_timestamp = strtotime($date . ' ' . str_pad($hour, 2, '0', STR_PAD_LEFT) . ':00:00');

            $tidal_factor = 0.8 * sin((($hour + $days_into_cycle * 0.5) / 24) * 2 * pi());
            $base_level = 1.2; // Average level in meters
            $water_level = round($base_level + $tidal_factor, 3);

            $data[] = array(
                'station_name' => 'Orkdal havn (estimert)',
                'latitude' => 63.3128,
                'longitude' => 10.5056,
                'water_level' => $water_level,
                'water_level_reference' => 'chart',
                'is_prediction' => true,
                'timestamp' => gmdate('Y-m-d H:i:s', $hourly_timestamp),
                'source' => 'estimated'
            );
        }

        error_log('Fiskedagbok: generate_estimated_tidewater_data - generated ' . count($data) . ' data points');
        return $data;
    }

    /**
     * Calculate hours until next high tide or low tide from current time
     * 
     * @param string $reference_time (optional, Y-m-d H:i:s format, defaults to now)
     * 
     * @return array|WP_Error Array with 'current_level', 'next_extreme_type', 'next_extreme_hours', 'station_name', 'station_code'
     */
    public static function calculate_hours_to_extreme_tide($reference_time) {
        global $wpdb;
        $tidewater_table = 'wpjd_fiskedagbok_tidewater_data'; // Use the correct table name

        $reference_timestamp = strtotime($reference_time);
        if (!$reference_timestamp) {
            return new WP_Error('invalid_time', __('Ugyldig tidspunkt', 'fiskedagbok'));
        }

        // Fetch the closest tidewater data point to the reference time
        $closest_point = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $tidewater_table 
             ORDER BY ABS(TIMESTAMPDIFF(SECOND, timestamp, %s)) ASC 
             LIMIT 1",
            $reference_time
        ));

        if (!$closest_point) {
            return new WP_Error('no_tidewater_data', __('Ingen tidevannsdata funnet for dette tidspunktet', 'fiskedagbok'));
        }

        // Now, fetch a window of data around this closest point to find extremes
        $window_hours = 48; // 24 hours before and 24 hours after
        $start_time = date('Y-m-d H:i:s', strtotime($closest_point->timestamp . ' -' . ($window_hours / 2) . ' hours'));
        $end_time = date('Y-m-d H:i:s', strtotime($closest_point->timestamp . ' +' . ($window_hours / 2) . ' hours'));

        $data = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $tidewater_table WHERE timestamp BETWEEN %s AND %s ORDER BY timestamp ASC",
            $start_time, $end_time
        ));

        if (empty($data)) {
            return new WP_Error('no_tidewater_data_in_window', __('Ingen tidevannsdata funnet i nærliggende vindu', 'fiskedagbok'));
        }

        // Find data points around reference time
        $before_points = array();
        $after_points = array();

        foreach ($data as $point) {
            $point_timestamp = strtotime($point->timestamp);
            
            if ($point_timestamp <= $reference_timestamp) {
                $before_points[] = $point;
            } else {
                $after_points[] = $point;
            }
        }

        // Get closest points before and after
        $before = end($before_points) ?: null;
        $after = reset($after_points) ?: null;

        if (!$before && !$after) {
            return new WP_Error('no_data_range', __('Tidevannsdata ikke tilgjengelig for dette tidspunktet', 'fiskedagbok'));
        }

        // Current water level (interpolate if needed)
        $current_level = null;
        $hours_to_high = null;
        $hours_to_low = null;
        $next_extreme_type = null;
        $next_extreme_hours = null;

        if ($before && $after) {
            // Interpolate current level
            $before_ts = strtotime($before->timestamp);
            $after_ts = strtotime($after->timestamp);
            $before_level = $before->water_level;
            $after_level = $after->water_level;

            $total_seconds = $after_ts - $before_ts;
            $elapsed_seconds = $reference_timestamp - $before_ts;
            $ratio = $total_seconds > 0 ? $elapsed_seconds / $total_seconds : 0;

            $current_level = $before_level + ($after_level - $before_level) * $ratio;
        } elseif ($before) {
            $current_level = $before->water_level;
        } elseif ($after) {
            $current_level = $after->water_level;
        }

        // Find next extreme tide (high or low)
        $search_points = $after_points ? $after_points : $before_points;
        
        if (count($search_points) >= 3) {
            // Find local maxima and minima
            for ($i = 1; $i < count($search_points) - 1; $i++) {
                $prev = $search_points[$i - 1]->water_level;
                $curr = $search_points[$i]->water_level;
                $next = $search_points[$i + 1]->water_level;

                // Check for local maximum (high tide)
                if ($curr > $prev && $curr > $next && $hours_to_high === null) {
                    $hours_to_high = (strtotime($search_points[$i]->timestamp) - $reference_timestamp) / 3600;
                }

                // Check for local minimum (low tide)
                if ($curr < $prev && $curr < $next && $hours_to_low === null) {
                    $hours_to_low = (strtotime($search_points[$i]->timestamp) - $reference_timestamp) / 3600;
                }

                // Determine which is closer
                if ($hours_to_high !== null && $hours_to_low !== null) {
                    break;
                }
            }
        }

        // Determine next extreme
        if ($hours_to_high !== null && $hours_to_low !== null) {
            if ($hours_to_high > 0 && $hours_to_low > 0) {
                if ($hours_to_high < $hours_to_low) {
                    $next_extreme_type = 'high';
                    $next_extreme_hours = round($hours_to_high, 1);
                } else {
                    $next_extreme_type = 'low';
                    $next_extreme_hours = round($hours_to_low, 1);
                }
            } elseif ($hours_to_high > 0) {
                $next_extreme_type = 'high';
                $next_extreme_hours = round($hours_to_high, 1);
            } elseif ($hours_to_low > 0) {
                $next_extreme_type = 'low';
                $next_extreme_hours = round($hours_to_low, 1);
            }
        } elseif ($hours_to_high !== null) {
            $next_extreme_type = 'high';
            $next_extreme_hours = round($hours_to_high, 1);
        } elseif ($hours_to_low !== null) {
            $next_extreme_type = 'low';
            $next_extreme_hours = round($hours_to_low, 1);
        }

        return array(
            'current_level' => $current_level !== null ? round($current_level, 2) : null,
            'next_extreme_type' => $next_extreme_type,
            'next_extreme_hours' => $next_extreme_hours,
            'station_name' => $closest_point->station_name,
            'station_code' => $closest_point->station_code,
        );
    }
}
