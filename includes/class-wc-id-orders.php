<?php
	
	if (!defined('ABSPATH')) exit; // Exit if accessed directly
	
	/**
		* isabiDeliver Orders Class
		*
		* Adds order admin page customizations
		*
		* @since 1.0
	*/
	class WC_Isabi_Deliver_Orders
	{
		/** @var \WC_Isabi_Deliver_Orders single instance of this class */
		private static $instance;

		 /** @var array settings value for this plugin */
		 public $settings;
		
		/**
			* Add various admin hooks/filters
			*
			* @since  1.0
		*/
		public function __construct()
		{
			/** Order Hooks */
			// $this->settings = maybe_unserialize(get_option('woocommerce_isabi_deliver_settings'));

			// if ($this->settings['enabled'] == 'yes') {
			// add bulk action to update order status for multiple orders from isabiDeliver
			add_action('admin_footer-edit.php', array($this, 'create_order_bulk_actions'));
			add_action('admin_footer-edit.php', array($this, 'update_order_bulk_actions'));
			add_action('load-edit.php', array($this, 'process_order_bulk_actions'));
	
			// add 'isabiDeliver Information' order meta box
			add_action('add_meta_boxes', array($this, 'add_order_meta_box'));
	
			// process order update action
			add_action('woocommerce_order_action_wc_isabi_deliver_update_status', array($this, 'process_order_update_action'));
	
			// process order create action
			add_action('woocommerce_order_action_wc_isabi_deliver_create', array($this, 'process_order_create_action'));
	
			// add 'Update isabiDeliver Status' order meta box order actions
			add_action('woocommerce_order_actions', array($this, 'add_order_meta_box_actions'));
			// }
		}
		/**
		 * Add "Update Gokada Order Status" custom bulk action to the 'Orders' page bulk action drop-down
		 *
		 * @since 1.0
		 */
		public function create_order_bulk_actions()
		{
			global $post_type, $post_status;

			if ($post_type == 'shop_order' && $post_status != 'trash' && $this->settings['shipping_is_scheduled_on'] == 'manually_submit') {
				?>
					<script type="text/javascript">
						jQuery(document).ready(function($) {
							$('select[name^=action]').append(
								$('<option>').val('create_order').text('<?php _e('Create isabiDeliver Order'); ?>')
							);
						});
					</script>
				<?php
			}
		}
		
		/**
		 * Add "Update isabiDeliver Order Status" custom bulk action to the 'Orders' page bulk action drop-down
		 *
		 * @since 1.0
		 */
		public function update_order_bulk_actions()
		{
			global $post_type, $post_status;

			if ($post_type == 'shop_order' && $post_status != 'trash') {
				?>
					<script type="text/javascript">
						jQuery(document).ready(function($) {
							$('select[name^=action]').append(
								$('<option>').val('update_order_status').text('<?php _e('Update Order status via isabiDeliver'); ?>')
							);
						});
					</script>
				<?php
			}
		}
		
		/**
		 * Processes the "Export to isabiDeliver" & "Update Tracking" custom bulk actions on the 'Orders' page bulk action drop-down
		 *
		 * @since  1.0
		 */
		public function process_order_bulk_actions()
		{
			global $typenow;

			if ('shop_order' == $typenow) {
				// get the action
				$wp_list_table = _get_list_table('WP_Posts_List_Table');
				$action        = $wp_list_table->current_action();
				// return if not processing our actions
				if (!in_array($action, array('update_order_status'))) {
					return;
				}

				// security check
				check_admin_referer('bulk-posts');

				// make sure order IDs are submitted
				if (isset($_REQUEST['post'])) {
					$order_ids = array_map('absint', $_REQUEST['post']);
				}

				// return if there are no orders to export
				if (empty($order_ids)) {
					return;
				}

				// give ourselves an unlimited timeout if possible
				@set_time_limit(0);

				if (in_array($action, array('update_order_status'))) {
					foreach ($order_ids as $order_id) {
						try {
							$order = wc_get_order( $order_id );
							if ($order->get_meta('isabi_deliver_order_id')) {
								wc_isabi_deliver()->update_order_shipping_status($order_id);
							}
						} catch (\Exception $e) {
						}
					}
				}

				else if (in_array($action, array('create_order'))) {
					foreach ($order_ids as $order_id) {
						try {
							$order = wc_get_order( $order_id );
							if (!$order->get_meta('isabi_deliver_order_id')) {
								wc_isabi_deliver()->create_order_shipping_task($order_id);
							}
						} catch (\Exception $e) {
						}
					}
				}
				
			}
		}

		/**
		 * Add 'Update Shipping Status' order actions to the 'Edit Order' page
		 *
		 * @since 1.0
		 * @param array $actions
		 * @return array
		 */
		public function add_order_meta_box_actions($actions)
		{
			global $theorder;

			//create isabiDeliver order
			if (!$theorder->get_meta('isabi_deliver_order_id')){
				$actions['wc_isabi_deliver_create'] = __('Create isabiDeliver order', 'my-textdomain');
			}

			// add update shipping status action
			if ($theorder->get_meta('isabi_deliver_order_id')) {
				$actions['wc_isabi_deliver_update_status'] = __('Update order status (via isabiDeliver)');
			}

			//check for Isabi order retries after failure
			else if ($theorder->get_meta('isabi_deliver_failed')) {
				$actions['wc_isabi_deliver_create'] = __('Retry isabiDeliver order');
			}

			return $actions;
		}
		
		/**
		 * Handle actions from the 'Edit Order' order action select box
		 *
		 * @since 1.0
		 * @param \WC_Order $order object
		 */
		public function process_order_update_action($order)
		{
			wc_isabi_deliver()->update_order_shipping_status($order->get_id());
		}

		/**
		 * Handle actions from the 'Create Order' order action select box
		 *
		 * @since 1.0
		 * @param \WC_Order $order object
		 */
		public function process_order_create_action($order)
		{
			wc_isabi_deliver()->create_order_shipping_task($order->get_id());
		}


		/**
		 * Add 'isabiDeliver Information' meta-box to 'Edit Order' page
		 *
		 * @since 1.0
		 */
		public function add_order_meta_box()
		{
			add_meta_box(
				'wc_isabi_deliver_order_meta_box',
				__('isabiDeliver Delivery'),
				array($this, 'render_order_meta_box'),
				'shop_order',
				'side'
			);
		}
		
		/**
		 * Display the 'isabiDeliver Information' meta-box on the 'Edit Order' page
		 *
		 * @since 1.0
		 */
		public function render_order_meta_box()
		{
			global $post;

			$order = wc_get_order($post);

			$isabi_order_id = $order->get_meta('isabi_deliver_order_id');

			if ($isabi_order_id) {
				$this->show_isabi_deliver_shipment_status($order);
			} else {
				$this->shipment_order_send_form($order);
			}
		}

		public function show_isabi_deliver_shipment_status($order)
		{
			$isabi_order_id = $order->get_meta('isabi_deliver_order_id');
			?>
		
				<table id="wc_Isabi_deliver_order_meta_box">
					<tr>
						<th><strong><?php esc_html_e('Order Number') ?> : </strong></th>
						<td><?php echo esc_html((empty($isabi_order_id)) ? __('N/A') : $isabi_order_id); ?></td>
					</tr>

					<tr>
						<th><strong><?php esc_html_e('Pickup Status') ?> : </strong></th>
						<td>
							<?php echo $order->get_meta('isabi_deliver_pickup_status'); ?>
						</td>
					</tr>
					
					<tr>
						<th><strong><?php esc_html_e('Pickup Tracking Link') ?> : </strong></th>
						<td><a href=<?php echo $order->get_meta('isabi_deliver_pickup_tracking_url'); ?>><?php echo $order->get_meta('isabi_deliver_pickup_tracking_url'); ?></a></td>
					</tr>
					
					<tr>
						<th><strong><?php esc_html_e('Delivery Tracking Link') ?> : </strong></th>
						<td><a href=<?php echo $order->get_meta('isabi_deliver_delivery_tracking_url'); ?>><?php echo $order->get_meta('isabi_deliver_delivery_tracking_url'); ?></a></td>
					</tr>

				</table>
			<?php
		}

		public function shipment_order_send_form($order)
		{
			?> 
				<p> No scheduled task for this order</p>
			<?php
		}

		/**
		 * Gets the main loader instance.
		 *
		 * Ensures only one instance can be loaded.
		 *
		 *
		 * @return \WC_Isabi_Deliver_Loader
		 */
		public static function instance()
		{
			if (null === self::$instance) {
				self::$instance = new self();
			}

			return self::$instance;
		}

	}

	// fire it up!
	return WC_Isabi_Deliver_Orders::instance();
