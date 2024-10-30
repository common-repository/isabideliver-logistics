<?php

if (!defined('ABSPATH')) exit; // Exit if accessed directly

class WC_Isabi_Deliver_API
{
    protected $env;

    protected $api_key;

    protected $request_url;


    public function __construct($settings = array())
    {
        $this->env = isset($settings['mode']) ? $settings['mode'] : 'test';

        if ($this->env == 'live') {
            $this->api_key    = isset($settings['live_api_key']) ? $settings['live_api_key'] : '';
            $this->request_url = 'https://api.isabideliver.com/v2/';
        } else {
            $this->api_key    = isset($settings['test_api_key']) ? $settings['test_api_key'] : '';
        }
    }

    /**
     * Convert address to Longitude and Latitude
     */
    public function get_lng_lat($address)
    {
        $address = rawurlencode($address);
        $coord   = get_transient($address);

        if(empty($coord)) {
            $url = 'https://maps.googleapis.com/maps/api/geocode/json?address=' .$address. '&key=AIzaSyDH2AWyUryBoF57UsUBN-gajffYnbMTQfQ';
            $json = wp_remote_get($url);

            if (200 === (int) wp_remote_retrieve_response_code($json)) {
                $body = wp_remote_retrieve_body($json);
                $json = json_decode($body, true);
            }

            $coord['long'] = $json['results'][0]['geometry']['location']['lng'];
            $coord['lat'] = $json['results'][0]['geometry']['location']['lat'];

            set_transient($address, $coord);
        }

        return $coord;
    }

    /**
     * Create task 
     */
    public function create_task($params)
    {
        $params['api_key'] = $this->api_key;
        $params['team_id'] = '';
        $params['auto_assignment'] = '1';
        $params['has_pickup'] = '1';
        $params['has_delivery'] = '1';
        $params['layout_type'] = '0';
        $params['tracking_link'] = 1;
        $params['timezone'] = '-60';
        $params['custom_field_template'] = '';
        $params['fleet_id'] = '';
        $params['pickup_custom_field_template'] = 'WordpressPG';
        $params['notify'] = 1;
        $params['geofence'] = 0;
        $params['ride_type'] = 0;

        return $this->send_request('create_task', $params);
    }

    /**
     * Cancelling a task
     */
    public function cancel_task($params)
    {
        $params['api_key'] = $this->api_key;

        return $this->send_request('cancel_task', $params);
    }

    /**
     * Get Task Details
     */
    public function get_task_details($params)
    {
        $params['api_key'] = $this->api_key;
        $params['include_task_history'] = 0;

        return $this->send_request('get_job_details', $params);
    }

    /**
     * Get Task Statistics
     */
    public function get_task_statistics($params)
    {
        $params['api_key'] = $this->api_key;
        $params['job_id'] = '5145';
        $params['job_status'] = [
            2,
            3
        ];
        
        return $this->send_request('user_task_stats', $params);
    }

    /**
     * Calculate delivery estimate
     */
    public function calculate_pricing($params)
    {
        $params['template_name'] = 'Order_details';
        $params['api_key'] = $this->api_key;
        $params['formula_type'] = '3';

        return $this->send_request('get_fare_estimate', $params);
    }

    /**
     * Send HTTP Request
     * @param string $endpoint API request path
     * @param array $args API request arguments
     * @param string $method API request method
     * @return object|null JSON decoded transaction object. NULL on API error.
     */
    public function send_request(
        $endpoint,
        $args = array(),
        $method = 'post'
    ) {
        $uri = "{$this->request_url}{$endpoint}";

        $arg_array = array(
            'method'    => strtoupper($method),
            'body'      => $args,
            'headers'   => $this->get_headers()
        );

        $req = wp_remote_request($uri, $arg_array);

        if (is_wp_error($req)) {
            throw new \Exception(__('HTTP error connecting to isabiDeliver API. Try again'));
        } else {
            $res = wp_remote_retrieve_body($req);
            
            if (null !== ($json = json_decode($res, true))) {
                error_log( __METHOD__ . ' for ' . $uri . ' ' . print_r(compact('arg_array', 'json'), true));

                if (isset($json['error']) || $json['status'] != 200) {
                    throw new Exception("There was an issue connecting to isabiDeliver. Reason: {$json['message']}.");
                } 
                    
                return $json;
                } else {// Un-decipherable message
                    throw new Exception(__('There was an issue connecting to isabiDeliver. Try again later.'));
                }
        }

        return false;
    }

    /**
     * Generates the headers to pass to API request.
     */
    public function get_headers()
    {
        return array(
            'Accept' => 'application/json',
        );
    }
}
