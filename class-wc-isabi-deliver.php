<?php

if (!defined('ABSPATH')) exit; // Exit if accessed directly

/**
 * Main Isabi Deliver Class.
 *
 * @class  WC_Isabi_Deliver
 */
class WC_Isabi_Deliver
{
    /** @var \WC_Isabi_Deliver_API api for this plugin */
    public $api;

    /** @var array settings value for this plugin */
    public $settings;

     /** @var array order status value for this plugin */
     public $statuses;

    /** @var \WC_Isabi_Deliver single instance of this plugin */
    protected static $instance;

    /**
     * Loads functionality/admin classes and add auto schedule order hook.
     *
     * @since 1.0
     */
    public function __construct()
    {
        // get settings
        $this->settings = maybe_unserialize(get_option('woocommerce_isabi_deliver_settings'));

        $this->statuses = [
            'Assigned',
            'Started',
            'Successful',
            'Failed',
            'InProgress',
            '',
            'Unassigned',
            'Accepted',
            'Decline',
            'Cancel',
            'Deleted',
        ];

        $this->init_plugin();

        $this->init_hooks();
    }

    /**
     * Initializes the plugin.
     *
     * @internal
     *
     * @since 2.4.0
     */
    public function init_plugin()
    {
        $this->includes();

        if (is_admin()) {
            $this->admin_includes();
        }
    }

    /**
     * Includes the necessary files.
     *
     * @since 1.0.0
     */
    public function includes()
    {
        $plugin_path = $this->get_plugin_path();

        require_once $plugin_path . 'includes/class-wc-id-api.php';
        require_once $plugin_path . 'includes/class-wc-id-shipping-method.php';
    }

    public function admin_includes()
    {
        $plugin_path = $this->get_plugin_path();
        require_once $plugin_path . 'includes/class-wc-id-orders.php';
    }
    /**
     * Initialize hooks.
     *
     * @since 1.0.0
     */
    public function init_hooks()
    {
        /**
         * Actions
         */
        $shipping_is_scheduled_on = $this->settings['shipping_is_scheduled_on'];
        if ($shipping_is_scheduled_on == 'order_submit') {
            // create order when \WC_Order::payment_complete() is called
            add_action('woocommerce_payment_complete', array($this, 'create_order_shipping_task'));
        }

        add_action('woocommerce_shipping_init', array($this, 'load_shipping_method'));
        
        // cancel an IsabiDeliver task when an order is cancelled in WC
        add_action('woocommerce_order_status_cancelled', array($this, 'cancel_order_shipping_task'));

        if ($shipping_is_scheduled_on == 'on_processing') {
            //Create an order when the status is changed to processing
            add_action('woocommerce_order_status_processing', array($this, 'create_order_shipping_task'));
        }

        // adds tracking button(s) to the View Order page
		add_action('woocommerce_order_details_after_order_table', array($this, 'add_view_order_tracking'));
        
        /**
         * Filters
         */
        // Add shipping icon to the shipping label
        add_filter('woocommerce_cart_shipping_method_full_label', array($this, 'add_shipping_icon'), PHP_INT_MAX, 2);

        add_filter('woocommerce_checkout_fields', array($this, 'remove_address_2_checkout_fields'));
        add_filter('woocommerce_shipping_methods', array($this, 'add_shipping_method'));

        add_filter('woocommerce_shipping_calculator_enable_city', '__return_true');
        add_filter('woocommerce_shipping_calculator_enable_address', '__return_true');
        add_filter('woocommerce_shipping_calculator_enable_postcode', '__return_false');
    }

        /**
     * shipping_icon.
     *
     * @since   1.0.0
     */
    function add_shipping_icon($label, $method)
    {
        if ($method->method_id == 'isabi_deliver') {
            $plugin_path = WC_ISABI_DELIVER_MAIN_FILE;
            $logo_title = 'isabiDeliver';
            $icon_url = plugins_url('assets/img/icon.png', $plugin_path);
            $img = '<img class="isabideliver-logo"' .
                ' alt="' . $logo_title . '"' .
                ' title="' . $logo_title . '"' .
                ' style="width:30px; height:25px; display:inline; object-fit:cover;"' .
                ' src="' . $icon_url . '"' .
                '>';
            $label = $img . ' ' . $label;
        }

        return $label;
    }

    public function create_order_shipping_task($order_id)
    {
        $order = wc_get_order($order_id);
        $order_status    = $order->get_status();
        $shipping_method = @array_shift($order->get_shipping_methods());

        if (strpos($shipping_method->get_method_id(), 'isabi_deliver') !== false) {

            if($this->settings['mode']=='test'){
                $order_id = $order->get_order_number();
                $customer_phone     = $order->get_billing_phone();
                $pickup_id = $order_id.''.$customer_phone;
                update_post_meta($order_id, 'isabi_deliver_order_id', $order_id);
                // update_post_meta($order_id, 'isabi_deliver_check_status_url', $data['job_status_check_link']);
                
                // For Pickup
                update_post_meta($order_id, 'isabi_deliver_pickup_id', $pickup_id);
                update_post_meta($order_id, 'isabi_deliver_pickup_status', $this->statuses[6]); // Unassigned
                update_post_meta($order_id, 'isabi_deliver_pickup_tracking_url', 'https://dummy_url.com/pickup_url');

                // For Delivery
                update_post_meta($order_id, 'isabi_deliver_delivery_id', $data['delivery_job_id']);
                update_post_meta($order_id, 'isabi_deliver_delivery_status', $this->statuses[6]); // Unassigned
                update_post_meta($order_id, 'isabi_deliver_delivery_tracking_url', 'https://dummy_url.com/delivery_url');

                update_post_meta($order_id, 'isabi_deliver_order_response', 'Order has been created on isabiDeliver Dashboard.');

                $note = sprintf(__('Shipment scheduled via isabiDeliver (Order Id: %s)'), $order_id);
                $order->add_order_note($note);
            }

            else{
                $customer_username      = $order->get_shipping_first_name() . " " . $order->get_shipping_last_name();
                $customer_email     = $order->get_billing_email();
                $customer_phone     = $order->get_billing_phone();
                $customer_address  = $order->get_shipping_address_1();
                $delivery_city      = $order->get_shipping_city();
                $delivery_state_code    = $order->get_shipping_state();
                $delivery_country_code  = $order->get_shipping_country();
                $delivery_state = WC()->countries->get_states($delivery_country_code)[$delivery_state_code];
                $delivery_country = WC()->countries->get_countries()[$delivery_country_code];

                $job_pickup_name         = $this->settings['sender_name'];
                $job_pickup_email        = $this->settings['sender_email'];
                $job_pickup_phone        = $this->settings['sender_phone_number'];
                $job_pickup_address = $this->settings['pickup_base_address'];
                $pickup_city         = $this->settings['pickup_city'];
                $pickup_state        = $this->settings['pickup_state'];
                $pickup_country      = $this->settings['pickup_country'];
                $job_description     = $this->settings['sales_description'];

                $order_id = $order->get_order_number();

                if (trim($pickup_country) == '') {
                    $pickup_country = 'NG';
                }

                $pickup_delay = $this->settings['pickup_delay'];

                $pickup_date = current_time('Y-m-d H:i:s');

                $job_pickup_datetime = null;

                $shipping_method_title = $shipping_method['total'];
            
                //Calculating job pickup date and time based on the different conditions vailable
                if ($this->settings['shipping_is_scheduled_on']=='order_submit'){
                    if ($this->settings['scheduled_deliveries']=='no'){
                        if ($this->settings['pickup_delay']==0){
                            $pickup_delay = 15;
                            $job_pickup_datetime = date('Y-m-d H:i:s', date(strtotime('+ '. $pickup_delay .' minutes', strtotime($pickup_date))));
                        }
                        else if ($this->settings['pickup_delay']>0){
                            $job_pickup_datetime = date('Y-m-d H:i:s', date(strtotime('+ '. $pickup_delay .' minutes', strtotime($pickup_date))));
                        }
                    }
                    else if ($this->settings['scheduled_deliveries']=='yes'){
                        $current_date = date('Y-m-d');
                            
                        $fixed_pickup_datetime = $current_date . ' ' . date('H:i:s', strtotime($this->settings['fixed_pickup_time']));
                        $present_datetime = date('Y-m-d H:i:s');
                    
                        if ($present_datetime < $fixed_pickup_datetime) {
                            $job_pickup_datetime = $fixed_pickup_datetime;
                        } else if ($present_datetime > $fixed_pickup_datetime) {
                            $job_pickup_datetime = date('Y-m-d H:i:s', date(strtotime('+ 24 hours', strtotime($fixed_pickup_datetime))));
                        } else if ($present_datetime == $fixed_pickup_datetime) {
                            $pickup_delay = 45;
                            $job_pickup_datetime = date('Y-m-d H:i:s', date(strtotime("+ " . $pickup_delay . " minutes", strtotime($present_datetime))));
                        }
                    }
                }
                
                else if ($this->settings['shipping_is_scheduled_on']=='on_processing'){
                    if ($this->settings['scheduled_deliveries']=='no'){
                        if ($this->settings['pickup_delay']==0){
                            $pickup_delay = 15;
                            $job_pickup_datetime = date('Y-m-d H:i:s', date(strtotime('+ '. $pickup_delay .' minutes', strtotime($pickup_date))));
                        }
                        else if ($this->settings['pickup_delay']>0){
                            $job_pickup_datetime = date('Y-m-d H:i:s', date(strtotime('+ '. $pickup_delay .' minutes', strtotime($pickup_date))));
                        }
                    }
                    else if ($this->settings['scheduled_deliveries']=='yes'){
                        $current_date = date('Y-m-d');
                            
                        $fixed_pickup_datetime = $current_date . ' ' . date('H:i:s', strtotime($this->settings['fixed_pickup_time']));
                        $present_datetime = date('Y-m-d H:i:s');
                    
                        if ($present_datetime < $fixed_pickup_datetime) {
                            $job_pickup_datetime = $fixed_pickup_datetime;
                        } else if ($present_datetime > $fixed_pickup_datetime) {
                            $job_pickup_datetime = date('Y-m-d H:i:s', date(strtotime('+ 24 hours', strtotime($fixed_pickup_datetime))));
                        } else if ($present_datetime == $fixed_pickup_datetime) {
                            $pickup_delay = 45;
                            $job_pickup_datetime = date('Y-m-d H:i:s', date(strtotime("+ " . $pickup_delay . " minutes", strtotime($present_datetime))));
                        }
                    }
                }
                
                else if ($this->settings['shipping_is_scheduled_on']=='manually_submit'){
                    if ($this->settings['scheduled_deliveries']=='no'){
                        if ($this->settings['pickup_delay']==0){
                            $pickup_delay = 15;
                            $job_pickup_datetime = date('Y-m-d H:i:s', date(strtotime('+ '. $pickup_delay .' minutes', strtotime($pickup_date))));
                        }
                        else if ($this->settings['pickup_delay']>0){
                            $job_pickup_datetime = date('Y-m-d H:i:s', date(strtotime('+ '. $pickup_delay .' minutes', strtotime($pickup_date))));
                        }
                    }
                    else if ($this->settings['scheduled_deliveries']=='yes'){
                        $current_date = date('Y-m-d');
                            
                        $fixed_pickup_datetime = $current_date . ' ' . date('H:i:s', strtotime($this->settings['fixed_pickup_time']));
                        $present_datetime = date('Y-m-d H:i:s');
                    
                        if ($present_datetime < $fixed_pickup_datetime) {
                            $job_pickup_datetime = $fixed_pickup_datetime;
                        } else if ($present_datetime > $fixed_pickup_datetime) {
                            $job_pickup_datetime = date('Y-m-d H:i:s', date(strtotime('+ 24 hours', strtotime($fixed_pickup_datetime))));
                        } else if ($present_datetime == $fixed_pickup_datetime) {
                            $pickup_delay = 45;
                            $job_pickup_datetime = date('Y-m-d H:i:s', date(strtotime("+ " . $pickup_delay . " minutes", strtotime($present_datetime))));
                        }
                    }
                }

                $job_delivery_datetime = date('Y-m-d H:i:s', date(strtotime('+ 45 minutes', strtotime($job_pickup_datetime))));

                $api = $this->get_api();

                $job_pickup_address = trim("$job_pickup_address, $pickup_city, $pickup_state, $pickup_country");
                $pickup_coordinate = $api->get_lng_lat($job_pickup_address);

                if (!isset($pickup_coordinate['lat']) && !isset($pickup_coordinate['long'])) {
                    $pickup_coordinate = $api->get_lng_lat("$pickup_city, $pickup_state, $pickup_country");
                }

                $delivery_address = trim("$customer_address, $delivery_city, $delivery_state, $delivery_country");
                $delivery_coordinate = $api->get_lng_lat($customer_address);
                
                if (!isset($delivery_coordinate['lat']) && !isset($delivery_coordinate['long'])) {
                    $delivery_coordinate = $api->get_lng_lat("$delivery_city, $delivery_state, $delivery_country");
                }
                
                $order_notes = $order->get_customer_note();

                $meta_data = array(
                    "label" => "",
                    "data" => ''
                );

                $pickup_meta_data = array(
                    array(
                        "label" => "Special_Instruction",
                        "data" => $order_notes
                    ),
                    array(
                        "label" => "Delivery_Fee",
                        "data" => $shipping_method_title
                    ),
                    array(
                        "label" => "Payment_Method",
                        "data" => $this->settings['shipping_payment_method']
                    )
                );

                $params = array(
                    "order_id" => $order_id,
                    "job_description" => $job_description,
                    "job_pickup_name" => $job_pickup_name,
                    "job_pickup_phone" => $job_pickup_phone,
                    "job_pickup_address" => $job_pickup_address,
                    "job_pickup_email"  => $job_pickup_email,
                    "job_pickup_latitude" => $pickup_coordinate['lat'],
                    "job_pickup_longitude" => $pickup_coordinate['long'],
                    "job_pickup_datetime" => $job_pickup_datetime,
                    "customer_username" => $customer_username,
                    "customer_phone" => $customer_phone,
                    "customer_email" => $customer_email,
                    "customer_address" => $customer_address,
                    "latitude" => $delivery_coordinate['lat'],
                    "longitude" => $delivery_coordinate['long'],
                    "job_delivery_datetime" => $job_delivery_datetime,
                    "tags" => $this->settings['vehicle_type'], //vehicle type
                    "meta_data" => $meta_data,
                    "pickup_meta_data" => $pickup_meta_data
                );

                error_log(print_r($params, true));

                $res = $api->create_task($params);
                
                error_log(print_r($res, true));

                $order->add_order_note("isabiDeliver Shipping: " . $res['message']);

                if ($res['status'] == 200) {
                    $data = $res['data'];
                    update_post_meta($order_id, 'isabi_deliver_order_id', $data['order_id']);
                    // update_post_meta($order_id, 'isabi_deliver_check_status_url', $data['job_status_check_link']);
                    
                    // For Pickup
                    update_post_meta($order_id, 'isabi_deliver_pickup_id', $data['pickup_job_id']);
                    update_post_meta($order_id, 'isabi_deliver_pickup_status', $this->statuses[6]); // Unassigned
                    update_post_meta($order_id, 'isabi_deliver_pickup_tracking_url', $data['pickup_tracking_link']);

                    // For Delivery
                    update_post_meta($order_id, 'isabi_deliver_delivery_id', $data['delivery_job_id']);
                    update_post_meta($order_id, 'isabi_deliver_delivery_status', $this->statuses[6]); // Unassigned
                    update_post_meta($order_id, 'isabi_deliver_delivery_tracking_url', $data['delivery_tracing_link']);

                    update_post_meta($order_id, 'isabi_deliver_order_response', $res);

                    $note = sprintf(__('Shipment scheduled via isabiDeliver (Order Id: %s)'), $data['order_id']);
                    $order->add_order_note($note);
                }
            }
        }
    }

        /**
     * Cancels an order in IsabiDeliver when it is cancelled in WooCommerce.
     *
     * @since 1.0.0
     *
     * @param int $order_id
     */
    public function cancel_order_shipping_task($order_id)
    {
        $order = wc_get_order($order_id);
        $isabi_order_id = $order->get_meta('isabi_deliver_order_id');
        $isabi_pickup_id = $order->get_meta('isabi_deliver_pickup_id');
        $isabi_pickup_status = $order->get_meta('isabi_deliver_pickup_status');

        if ($isabi_pickup_id) {

            if($this->settings['mode'] == 'test'){
                $order->update_status('Cancel');
    
                $order->add_order_note(__('Order has been cancelled on isabiDeliver Dashboard.'));
            }
            else{
                try {
                    $params = [
                        'job_id' => $isabi_pickup_id, // check if to cancel pickup task or delivery task
                        'job_status' => $isabi_pickup_status
                    ];
                    
                    $this->get_api()->cancel_task($params);
                    $order->update_status('Cancel');
    
                    $order->add_order_note(__('Order has been cancelled on isabiDeliver Dashboard.'));
                } catch (Exception $exception) {
    
                    $order->add_order_note(sprintf(
                        /* translators: Placeholder: %s - error message */
                        esc_html__('Unable to cancel order on isabiDeliver Dashboard: %s'),
                        $exception->getMessage()
                    ));
                }
            }
        }
    }

    /**
     *  Update Order status by fetching the Order Details from isabiDeliver Dashboard
     * 
     */
    public function update_order_shipping_status($order_id)
    {
        if ($this->settings['mode'] == 'test' && !strpos($this->settings['test_api_key'], 'test')) {
            $order = wc_get_order($order_id);
            $isabi_pickup_status = $order->get_meta('isabi_deliver_pickup_status');
            if ($isabi_pickup_status == 'Unassigned'){
                update_post_meta($order_id, 'isabi_deliver_pickup_status', 'Assigned');
                update_post_meta($order_id, 'isabi_deliver_delivery_status', 'Assigned');
                update_post_meta($order_id, 'isabi_deliver_order_response', 'Agent has been assigned to this order');
            }
			else if($isabi_pickup_status == 'Assigned') {
                update_post_meta($order_id, 'isabi_deliver_pickup_status', 'Accepted');
                update_post_meta($order_id, 'isabi_deliver_delivery_status', 'Accepted');
                update_post_meta($order_id, 'isabi_deliver_order_response', 'Agent has accepted this order');
            }
            else if($isabi_pickup_status == 'Accepted') {
                update_post_meta($order_id, 'isabi_deliver_pickup_status', 'InProgress');
                update_post_meta($order_id, 'isabi_deliver_delivery_status', 'InProgress');
                update_post_meta($order_id, 'isabi_deliver_order_response', 'Order pickup is in progress');
            }
            else if($isabi_pickup_status == 'InProgress') {
                update_post_meta($order_id, 'isabi_deliver_pickup_status', 'Successful');
                update_post_meta($order_id, 'isabi_deliver_delivery_status', 'Successful');
                update_post_meta($order_id, 'isabi_deliver_order_response', 'Order pickup was successful');
            }
        }

        else{
            $order = wc_get_order($order_id);

            $isabi_order_id = $order->get_meta('isabi_deliver_order_id');
            $isabi_pickup_id = $order->get_meta('isabi_deliver_pickup_id');

            if ($isabi_pickup_id) {
                $res = $this->get_api()->get_task_details(array(
                    'job_ids' => [$isabi_pickup_id]
                ));

                if ($res['status'] == 200){
                    $data = $res['data'];
                    $first_data_array = $data[0];
                    $pickup_status = $this->statuses[$first_data_array['job_status']];
                    $delivery_status = $this->statuses[$first_data_array['job_status']];

                    if($pickup_status == 'Accepted') {
                        $order->add_order_note("isabiDeliver: Agent $pickup_status order");
                    } else if ($pickup_status == 'Started') {
                        $order->add_order_note("isabiDeliver: Agent $pickup_status order");
                    } else if ($delivery_status == 'Successful') {
                        $order->add_order_note("isabiDeliver: Agent order delivery was  $pickup_status");
                    } else{
                        $order->add_order_note("isabiDeliver: Pickup status - $pickup_status");
                    }

                    update_post_meta($order_id, 'isabi_deliver_pickup_status', $pickup_status);
                    update_post_meta($order_id, 'isabi_deliver_delivery_status', $delivery_status);
                    update_post_meta($order_id, 'isabi_deliver_order_response', $res);
                }
            }
        }
    }

    /**
     * Adds the tracking information to the View Order Page.
     */
    public function add_view_order_tracking($order)
    {
        $order = wc_get_order($order);
        $pickup_tracking_url = $order->get_meta('isabi_deliver_pickup_tracking_url');
        $delivery_tracking_url = $order->get_meta('isabi_deliver_delivery_tracking_url');

        if (isset($pickup_tracking_url)) {
            ?>
            <p class='wc-isabi-deliver-track-pickup'>
                <a href="<?php echo esc_url($pickup_tracking_url); ?>" class="button" target="_blank">Track isabiDeliver Pickup</a>
            </p>

            <?php
        }

        if (isset($delivery_tracking_url)) {
            ?>
            <p class='wc-isabi-deliver-track-delivery'>
                <a href="<?php echo esc_url($delivery_tracking_url); ?>" class="button" target="_blank">Track isabiDeliver Delivery</a>
            </p>
            <?php
        }
    }

    public function remove_address_2_checkout_fields($fields)
    {
        unset($fields['billing']['billing_address_2']);
        unset($fields['shipping']['shipping_address_2']);

        return $fields;
    }

    /**
     * Load Shipping method.
     *
     * Load the WooCommerce shipping method class.
     *
     * @since 1.0.0
     */
    public function load_shipping_method()
    {
        $this->shipping_method = new WC_Isabi_Deliver_Shipping_Method;
    }

    /**
     * Add shipping method.
     *
     * Add shipping method to the list of available shipping method..
     *
     * @since 1.0.0
     */
    public function add_shipping_method($methods)
    {
        if (class_exists('WC_Isabi_Deliver_Shipping_Method')) :
            $methods['isabi_deliver'] = 'WC_Isabi_Deliver_Shipping_Method';
        endif;

        return $methods;
    }

    /**
     * Initializes the and returns IsabiDeliver API object.
     *
     * @since 1.0
     *
     * @return \WC_Isabi_Deliver_API instance
     */
    public function get_api()
    {
        // return API object if already instantiated
        if (is_object($this->api)) {
            return $this->api;
        }

        $isabi_deliver_settings = $this->settings;

        // instantiate API
        return $this->api = new \WC_Isabi_Deliver_API($isabi_deliver_settings);
    }
    
    public function get_plugin_path()
    {
        return plugin_dir_path(__FILE__);
    }

    /**
     * Returns the main Isabi Deliver Instance.
     *
     * Ensures only one instance is/can be loaded.
     *
     * @since 1.0.0
     *
     * @return \WC_Isabi_Deliver
     */
    public static function instance()
    {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }

        return self::$instance;
    }
}


/**
 * Returns the One True Instance of WooCommerce IsabiDeliver.
 *
 * @since 1.0.0
 *
 * @return \WC_Isabi_Deliver
 */
function wc_isabi_deliver()
{
    return \WC_Isabi_Deliver::instance();
}
