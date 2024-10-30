<?php

if (!defined('ABSPATH')) exit; // Exit if accessed directly

/**
 * Isabi Deliver Shipping Method Class
 *
 * Provides real-time shipping rates from Isabi deliver and handle order requests
 *
 * @since 1.0
 * 
 * @extends \WC_Shipping_Method
 */
class WC_Isabi_Deliver_Shipping_Method extends WC_Shipping_Method{
    /**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
    public function __construct($instance_id = 0) {
        $this->id                 = 'isabi_deliver';
        $this->instance_id 		  = absint($instance_id);
		$this->method_title       = __('isabiDeliver');
        $this->method_description = __( 'Delight your customers with convenient, cheaper and quicker delivery via isabiDeliver.' ); 
        $this->supports  = array(
			'settings',
			'shipping-zones',
		);

        $this->init();

		$this->title       = __( 'isabiDeliver Shipping' );

		$this->enabled = $this->get_option('enabled');
    }

    /**
	 * Init.
	 *
	 * Initialize isabi deliver shipping method.
	 *
	 */
	public function init()
	{
		$this->init_form_fields();
		$this->init_settings();

		// Save settings in admin if you have any defined
		add_action('woocommerce_update_options_shipping_' . $this->id, array($this, 'process_admin_options'));
	}

	/**
	 * Init fields.
	 *
	 * Add fields to the Isabi deliver settings page.
	 *
	 * @since 1.0.0
	 */
	public function init_form_fields()
	{
		$pickup_state_code = WC()->countries->get_base_state();
		$pickup_country_code = WC()->countries->get_base_country();

		$pickup_city = WC()->countries->get_base_city();
		$pickup_base_address = WC()->countries->get_base_address();
		

		$this->form_fields = array(
			'enabled' => array(
				'title' 	=> __('Enable/Disable'),
				'type' 		=> 'checkbox',
				'label' 	=> __('Enable this shipping method'),
				'default' 	=> 'no',
			),
			'mode' => array(
				'title'       => 	__('Mode'),
				'type'        => 	'select',
				'description' => 	__('Default is (Test), choose (Live) when you are ready to start processing orders via isabiDeliver'),
				'default'     => 	'test',
				'options'     => 	array('test' => 'Test', 'live' => 'Live'),
			),
			'test_api_key' => array(
				'title'       => 	__('Test API Key'),
				'type'        => 	'password',
				'description' => 	__('Your test api key', 'woocommerce-isabi-deliver'),
				'value'     => 	__('5143oo2fgfyukgh;oihvvyvee4a1a4040d9f206defe233e'),
				'readonly'	=> 1,
			),
			'live_api_key' => array(
				'title'       => 	__('Live API key'),
				'type'        => 	'password',
				'description' => 	__('Your live api key', 'woocommerce-isabi-deliver'),
				'default'     => 	__('')
			),
			'shipping_title' => array(
				'title'       => 	__('Shipping Title'),
				'type'        => 	'text',
				'description' => 	__('Enter the title of your shipping'),
				'default'     => 	__('isabiDeliver Shipping')
			),
			'shipping_is_scheduled_on' => array(
				'title'        =>	__('Schedule Shipping Task'),
				'type'         =>	'select',
				'description'  =>	__('Select when the shipment will be created.'),
				'default'      =>	__('order_submit'),
				'options'      =>	array(
					'order_submit' => 'Order Submit with Complete payment (Online payment)', 
					'on_processing' => 'When Order Status is changed to processing', 
					'manually_submit' => 'Manually create from Admin Dashboard'
				)
			),
			'shipping_handling_fee' => array(
				'title'       => 	__('Additional handling fee applied'),
				'type'        => 	'text',
				'description' => 	__("Additional handling fee applied"),
				'default'     => 	__('0')
			),
			'shipping_payment_method' => array(
				'title'        =>	__('Payment method for shipment'),
				'type'         =>	'select',
				'description'  =>	__('Select payment method.'),
				'default'      =>	__('8'),
				'options'      =>	array('Cash on pickup' => 'Cash on pickup', 'Wallet' => 'Wallet')
			),
			'pickup_delay' => array(
				'title'       => 	__('Enter pickup delay time (in minutes)'),
				'type'        => 	'text',
				'description' => 	__('Number of minutes to delay pickup time by. Defaults to 0.'),
				'default'     => 	__('0')
			),
			'scheduled_deliveries' => array(
				'title' 	=> __('Scheduled Deliveries'),
				'type' 		=> 'checkbox',
				'label' 	=> __('Set a fixed pickup time'),
				'default' 	=> 'no',
			),
			'fixed_pickup_time' => array(
				'title'        =>	__('Enter fixed daily pickup time'),
				'type'         =>	'select',
				'description'  =>	__('Parcel will be picked up only at this time daily.'),
				'default'      =>	__(' '),
				'options'      =>	array(' '=> 'Please Select', '12:00 AM' => '12:00 AM', '1:00 AM' => '1:00 AM', '2:00 AM' => '2:00 AM', '3:00 AM' => '3:00 AM',
				'4:00 AM' => '4:00 AM', '5:00 AM' => '5:00 AM', '6:00 AM' => '6:00 AM', '7:00 AM' => '7:00 AM', '8:00 AM' => '8:00 AM', '9:00 AM' => '9:00 AM',
				'10:00 AM' => '10:00 AM', '11:00 AM' => '11:00 AM', '12:00 PM' => '12noon', '1:00 PM' => '1:00 PM', '2:00 PM' => '2:00 PM', '3:00 PM' => '3:00 PM',
				'4:00 PM' => '4:00 PM', '5:00 PM' => '5:00 PM', '6:00 PM' => '6:00 PM', '7:00 PM' => '7:00 PM', '8:00 PM' => '8:00 PM', '9:00 PM' => '9:00 PM',
				'10:00 PM' => '10:00 PM', '11:00 PM' => '11:00 PM')
			),
			'pickup_country' => array(
				'title'       => 	__('Pickup Country'),
				'type'        => 	'select',
				'description' => 	__('isabiDeliver is only available for Nigeria.'),
				'default'     => 	'NG',
				'options'     => 	array("NG" => "Nigeria", "" => "Please Select"),
			),
			'pickup_state' => array(
				'title'       => 	__('Pickup State'),
				'type'        => 	'select',
				'description' => 	__('isabiDeliver is only available in Lagos & Abuja.'),
				'default'     =>    'LAG',
				'options'     => 	array("Lagos" => "Lagos", "Abuja" => "Abuja", "" => "Please Select"),
			),
			'pickup_city' => array(
				'title'       => 	__('Pickup City'),
				'type'        => 	'text',
				'description' => 	__('The local area where the parcel will be picked up.'),
				'default'     => 	__($pickup_city)
			),
			'pickup_base_address' => array(
				'title'       => 	__('Pickup Address'),
				'type'        => 	'text',
				'description' => 	__('The street address where the parcel will be picked up.'),
				'default'     => 	__($pickup_base_address)
			),
			'sender_name' => array(
				'title'       => 	__('Sender Name'),
				'type'        => 	'text',
				'description' => 	__("Sender Name"),
				'default'     => 	__('')
			),
			'sender_phone_number' => array(
				'title'       => 	__('Pickup Phone Number'),
				'type'        => 	'text',
				'description' => 	__('Used to coordinate pickup if the isabiDeliver rider is outside attempting delivery. Must be a valid phone number'),
				'default'     => 	__('')
			),
			'sender_email' => array(
				'title'       => 	__('User Email'),
				'type'        => 	'email',
				'description' => 	__('Enter your registered isabiDeliver Account Email.'),
				'default'     => 	__('')
			),
			'sales_description' => array(
				'title'       =>    __('Description of what is to be sent to customer'),
				'type'        =>    'text',
				'description' =>    __('Describe what will be delivered to your customer'),
				'default'     =>    __('')
			),
			'vehicle_type' => array(
				'title'       =>    __('Vehicle Type'),
				'type'        =>    'text',
				'description' =>    __('What type of vehicle is preferrable for your delivery?'),
				'default'     =>    __('')
			),
		);
	}

	/**
	 * calculate_shipping function.
	 *
	 * @access public
	 * @param mixed $package
	 * @return void
	 */
	public function calculate_shipping( $package=array() ) 
	{
		if ($this->enabled == 'no') {
			return;
		}

		if ($this->get_option('mode') == 'test'){
			$cost = 2000;

			$this->add_rate(array(
				'id'    	=> $this->id . $this->instance_id,
				'label' 	=> $this->get_option('shipping_title'),
				'cost'  	=> $cost,
			));
		}
		else{
			// country required for all shipments
			if (!$package['destination']['country'] && 'NG' !== $package['destination']['country']) {
				return;
			}
			$delivery_country_code = $package['destination']['country'];
			$delivery_state_code = $package['destination']['state'];
			$delivery_city = $package['destination']['city'];
			$delivery_base_address = $package['destination']['address'];

			$delivery_state = WC()->countries->get_states($delivery_country_code)[$delivery_state_code];
			$delivery_country = WC()->countries->get_countries()[$delivery_country_code];

			try {
				$api = wc_isabi_deliver()->get_api();
			} catch (\Exception $e) {
				wc_add_notice(__('IsabiDeliver shipping method could not be set up'), 'notice');
				wc_add_notice(__($e->getMessage()) . ' Please Contact Support' , 'error');

				return;
			}

			$pickup_city = $this->get_option('pickup_city');
			$pickup_state = $this->get_option('pickup_state');
			$pickup_base_address = $this->get_option('pickup_base_address');
			$pickup_country = WC()->countries->get_countries()[$this->get_option('pickup_country')];

			$delivery_address = trim("$delivery_base_address, $delivery_city, $delivery_state, $delivery_country");
			$job_pickup_address = trim("$pickup_base_address, $pickup_city, $pickup_state, $pickup_country");

			$delivery_coordinate = $api->get_lng_lat($delivery_address);

			if (!isset($delivery_coordinate['lat']) && !isset($delivery_coordinate['long'])) {
				$delivery_coordinate = $api->get_lng_lat("$delivery_base_address, $delivery_city, $delivery_state, $delivery_country");
			}

			$pickup_coordinate = $api->get_lng_lat($job_pickup_address);

			if (!isset($pickup_coordinate['lat']) && !isset($pickup_coordinate['long'])) {
				$pickup_coordinate = $api->get_lng_lat("$pickup_base_address, $pickup_city, $pickup_state, $pickup_country");
			}

			$params = array(
				'pickup_longitude' => $pickup_coordinate['long'],
				'pickup_latitude' => $pickup_coordinate['lat'],
				'delivery_longitude' => $delivery_coordinate['long'],
				'delivery_latitude' => $delivery_coordinate['lat'],
				'map_keys' => array(
					'map_plan_type' => 1,
					'google_api_key' => 'AIzaSyCHCjIZlZNyspO8uvpNFtDDEGc84BK_PFU'
				),
			);
			
			try {
				$res = $api->calculate_pricing($params);
			} catch (\Exception $e) {
				wc_add_notice(__('IsabiDeliver pricing calculation could not complete'), 'notice');
				wc_add_notice(__($e->getMessage()), 'error');

				return;
			}

			$data = $res['data'];
			$handling_fee = $this->get_option('shipping_handling_fee');

			if ($handling_fee < 0) {
				$handling_fee = 0;
			}

			$sum = 0;

			$base_fare = $data['formula_fields']['1'][0]['sum'];
			$duration_fare = $data['formula_fields']['1'][1]['sum'];
			$waiting_fare = $data['formula_fields']['1'][2]['sum'];
			$distance_fare = $data['formula_fields']['1'][3]['sum'];
			$deduction = $data['formula_fields']['1'][4]['sum'];
			$second_base_fare = $data['formula_fields']['2'][0]['sum'];
			$second_duration_fare = $data['formula_fields']['2'][1]['sum'];
			$second_waiting_fare = $data['formula_fields']['2'][2]['sum'];
			$second_distance_fare = $data['formula_fields']['2'][3]['sum'];
			$second_deduction = $data['formula_fields']['2'][4]['sum'];
			
			$sum = $base_fare + $duration_fare + $waiting_fare + $distance_fare + $deduction + $second_base_fare + $second_duration_fare + $second_waiting_fare + $second_distance_fare + $second_deduction;

			$cost = wc_format_decimal($sum) + wc_format_decimal($handling_fee);

			$this->add_rate(array(
				'id'    	=> $this->id . $this->instance_id,
				'label' 	=> $this->get_option('shipping_title'),
				'cost'  	=> $cost,
			));
		}
	}
}
