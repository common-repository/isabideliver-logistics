<?php

/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * This shipping plugin is designed to communicate with isabiDeliver API and was designed by isabiDeliver team and a property of isabiDeliver
 * https://isabideliver.com
 * https://isabideliver.com/terms-and-condition/
 *
 * @link              http://isabideliver.com/
 * @since             1.0.0
 * @package           isabideliver
 *
 * @wordpress-plugin
 * Plugin Name:       isabiDeliver for WooCommerce
 * Plugin URI:        https://wordpress.org/plugins/isabideliver-logistics
 * Description:       Delight your customers with convenient, cheaper and quicker delivery via isabiDeliver.
 * Version:           1.0.0
 * Author:            isabiDeliver
 * Author URI:        http://isabideliver.com/
 * 
 * Woo: 12345:342928dfsfhsf8429842374wdf4234sfd
 * WC requires at least: 2.2
 * WC tested up to: 2.3
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       isabideliver
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

define('WC_ISABI_DELIVER_MAIN_FILE',__FILE__);

// Installation Section
// - Check if woocommerce and wordpress are installed on wordpress site
class WC_Isabi_Deliver_Loader{
	/** minimum PHP version required by this plugin */
    const MINIMUM_PHP_VERSION = '5.4.0';

    /** minimum WordPress version required by this plugin */
    const MINIMUM_WP_VERSION = '5.0';

    /** minimum WooCommerce version required by this plugin */
    const MINIMUM_WC_VERSION = '4.0';

    /** the plugin name, for displaying notices */
    const PLUGIN_NAME = 'isabiDeliver for WooCommerce';

    /** the plugin slug, for action links */
    const PLUGIN_SLUG = 'isabi-deliver-for-woocommerce';

    /** @var array the admin notices to add */
    private $notices = array();

    /** @var \WC_IsabiDeliver_Loader single instance of this class */
    private static $instance;

    private static $active_plugins;

    /**
     * Sets up the loader.
     *
     */
    protected function __construct()
    {
        self::$active_plugins = (array) get_option('active_plugins', array());

        if (is_multisite()) {
            self::$active_plugins = array_merge(self::$active_plugins, get_site_option('active_sitewide_plugins', array()));
        }

        if (!$this->wc_active_check()) {
            return;
        }

        register_activation_hook(__FILE__, array($this, 'activation_check'));

        add_action('admin_init', array($this, 'check_environment'));
        add_action('admin_init', array($this, 'add_plugin_notices'));
        add_action('admin_notices', array($this, 'admin_notices'), 15);

        // if the environment check fails, initialize the plugin
        if ($this->is_environment_compatible()) {
            add_action('plugins_loaded', array($this, 'init_plugin'));

            add_filter('plugin_action_links_' . plugin_basename(__FILE__), array($this, 'plugin_action_links'));
        }
        
    }

    /**
     * Initializes the plugin.
     *
     */
    public function init_plugin()
    {
        if (!$this->plugins_compatible()) {
            return;
        }

        // load the main plugin class
        require_once(plugin_dir_path(__FILE__) . 'class-wc-isabi-deliver.php');

        wc_isabi_deliver();
    }

    public function plugin_action_links($links)
    {
        $links[] = '<a href="' . admin_url('admin.php?page=wc-settings&tab=shipping&section=isabi_deliver') . '">' . __('Settings') . '</a>';
        return $links;
    }

	// check if woocommerce is active
    public function wc_active_check()
    {
        return in_array('woocommerce/woocommerce.php', self::$active_plugins) || array_key_exists('woocommerce/woocommerce.php', self::$active_plugins);
    }


    /**
     * Checks the server environment and other factors and deactivates plugins as necessary.
     *
     * Based on http://wptavern.com/how-to-prevent-wordpress-plugins-from-activating-on-sites-with-incompatible-hosting-environments
     *
     */
    public function activation_check()
    {
        if (!$this->is_environment_compatible()) {

            $this->deactivate_plugin();

            wp_die(self::PLUGIN_NAME . ' could not be activated. ' . $this->get_environment_message());
        }
    }

    /**
     * Checks the environment on loading WordPress, just in case the environment changes after activation.
     *
     */
    public function check_environment()
    {
        if (!$this->is_environment_compatible() && is_plugin_active(plugin_basename(__FILE__))) {

            $this->deactivate_plugin();

            $this->add_admin_notice('bad_environment', 'error', self::PLUGIN_NAME . ' has been deactivated. ' . $this->get_environment_message());
        }
    }

    /**
     * Check the version of Wordpress being used and/or add
	 * notices for out-of-date WordPress and/or WooCommerce versions.
     *
     */
    public function add_plugin_notices()
    {
        if (!$this->is_wp_compatible()) {

            $this->add_admin_notice('update_wordpress', 'error', sprintf(
                '%s requires WordPress version %s or higher. Please %supdate WordPress &raquo;%s',
                '<strong>' . self::PLUGIN_NAME . '</strong>',
                self::MINIMUM_WP_VERSION,
                '<a href="' . esc_url(admin_url('update-core.php')) . '">',
                '</a>'
            ));
        }

        if (!$this->is_wc_compatible()) {
            $this->add_admin_notice('update_woocommerce', 'error', sprintf(
                '%1$s requires WooCommerce version %2$s or higher. Please %3$supdate WooCommerce%4$s to the latest version, or %5$sdownload the minimum required version &raquo;%6$s',
                '<strong>' . self::PLUGIN_NAME . '</strong>',
                self::MINIMUM_WC_VERSION,
                '<a href="' . esc_url(admin_url('update-core.php')) . '">',
                '</a>',
                '<a href="' . esc_url('https://downloads.wordpress.org/plugin/woocommerce.' . self::MINIMUM_WC_VERSION . '.zip') . '">',
                '</a>'
            ));
        }
    }
 


    /**
     * Determines if wordpress and woocommerce are compatible.
     *
     * @return bool
     */
    protected function plugins_compatible()
    {
        return $this->is_wp_compatible() && $this->is_wc_compatible();
    }

    /**
     * Determines if the WordPress compatible.
     */
    protected function is_wp_compatible()
    {
        return version_compare(get_bloginfo('version'), self::MINIMUM_WP_VERSION, '>=');
    }

    /**
     * Determines if the WooCommerce compatible.
     */
    protected function is_wc_compatible()
    {
        return defined('WC_VERSION') && version_compare(WC_VERSION, self::MINIMUM_WC_VERSION, '>=');
    }

    /**
     * Deactivates the plugin.
     *
     */
    protected function deactivate_plugin()
    {
        deactivate_plugins(plugin_basename(__FILE__));

        if (isset($_GET['activate'])) {
            unset($_GET['activate']);
        }
    }

    /**
     * Adds an admin notice to be displayed.
     *
     * @param string $slug the slug for the notice
     * @param string $class the css class for the notice
     * @param string $message the notice message
     */
    public function add_admin_notice($slug, $class, $message)
    {
        $this->notices[$slug] = array(
            'class'   => $class,
            'message' => $message
        );
    }

    /**
     * Displays any admin notices set.
     *
     * @see \WC_IsabiDeliver_Loader_Loader::add_admin_notice()
     *
     */
    public function admin_notices()
    {
        foreach ($this->notices as $notice_key => $notice) :

?>
            <div class="<?php echo esc_attr($notice['class']); ?>">
                <p><?php echo wp_kses($notice['message'], array('a' => array('href' => array()))); ?></p>
            </div>
<?php

        endforeach;
    }

    /**
     * Determines if the server environment is compatible with this plugin.
     * Override this method to add checks for more than just the PHP version.
     */
    protected function is_environment_compatible()
    {
        return version_compare(PHP_VERSION, self::MINIMUM_PHP_VERSION, '>=');
    }

    /**
     * Gets the message for display when the environment is incompatible with this plugin.
     */
    protected function get_environment_message()
    {
        return sprintf('The minimum PHP version required for this plugin is %1$s. You are running %2$s.', self::MINIMUM_PHP_VERSION, PHP_VERSION);;
    }

    /**
     * Cloning instances is forbidden due to singleton pattern.
     *
     */
    public function __clone()
    {
        _doing_it_wrong(__FUNCTION__, sprintf('You cannot clone instances of %s.', get_class($this)), '1.0.0');
    }

    /**
     * Unserializing instances is forbidden due to singleton pattern.
     *
     */
    public function __wakeup()
    {
        _doing_it_wrong(__FUNCTION__, sprintf('You cannot unserialize instances of %s.', get_class($this)), '1.0.0');
    }
    /**
     * Gets the main loader instance.
     * Ensures only one instance can be loaded.
     * @return \WC_Isabi_Deliver_Loader instance
     */
    public static function instance()
    {
        if (self::$instance === null) {
            self::$instance = new self;
        }

        return self::$instance;
    }
}

WC_Isabi_Deliver_Loader::instance();
