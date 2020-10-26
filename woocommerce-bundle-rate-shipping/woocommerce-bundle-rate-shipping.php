<?php
/*
Plugin Name: Bundle Rate Shipping Module for WooCommerce
Plugin URI: http://codecanyon.net/item/woocommerce-ecommerce-bundle-rate-shipping/1429243
Description: Adds a bundle rate shipping method to your WooCommerce store.
Version: 2.0.4
Author: Eric Daams
Author URI: http://164a.com
*/

if ( ! defined( 'ABSPATH' ) ) { exit; } // Exit if accessed directly

define( 'ENDA_WOOCOMMERCE_FILE', __FILE__ );
define( 'ENDA_WOOCOMMERCE_URL', trailingslashit( plugins_url( '/', __FILE__ ) ) );
define( 'ENDA_WOOCOMMERCE_PATH', trailingslashit( plugin_dir_path( __FILE__ ) ) );

/**
 * Class Enda_Woocommerce
 */
class ENDA_Woocommerce {

	private $version;

	/**
	 * Set up the class instance.
	 *
	 * @access  public
	 * @since   2.0.0
	 */
	public function __construct() {

		if ( ! doing_action( 'plugins_loaded' ) ) {
			return;
		}

		$this->version = mktime( 0, 0, 0, 1, 7, 2016 );

		$this->load_method_class();

		add_filter( 'woocommerce_shipping_methods', array( $this, 'register_method' ) );

		add_action( 'init', array( $this, 'setup_i18n' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'load_admin_scripts' ) );
		add_action( 'wp_ajax_get_new_layer', array( $this, 'add_layer' ) );
		add_action( 'wp_ajax_get_new_configuration_layer', array( $this, 'add_configuration' ) );

	}

	/**
	 * Load the shipping method class.
	 *
	 * @return  void
	 * @since   2.0.0
	 */
	public function load_method_class() {
		require_once( 'class-enda-shipping-bundle-rate.php' );
	}

	/**
	 * Register the shipping method.
	 *
	 * @param   string[] $methods
	 * @return  string[]
	 * @since   2.0.0
	 */
	public function register_method( $methods ) {
		$methods['enda_bundle_rate'] = 'ENDA_Woocommerce_Bundle_Shipping';
		return $methods;
	}

	/**
	 * Load the plugin text domain.
	 *
	 * @return  void
	 * @since   2.0.0
	 */
	public function setup_i18n() {
		load_plugin_textdomain( 'woocommerce-bundle-rate-shipping', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
	}

	/**
	 * Handle plugin upgrades.
	 *
	 * @return  void
	 * @access  public
	 * @since   2.0.0
	 */
	public function maybe_upgrade() {
		update_option( 'woocommerce_enda_bundle_rate_version', $this->version );
	}

	/**
	 * Load CSS & Javascript, but only on certain pages.
	 *
	 * @return  void
	 * @since   2.0.0
	 */
	public function load_admin_scripts( $hook ) {

		$script = SCRIPT_DEBUG
			? ENDA_WOOCOMMERCE_URL . 'assets/admin.js'
			: ENDA_WOOCOMMERCE_URL . 'assets/admin.min.js';

		wp_register_script(
			'woocommerce_bundle_rate_shipping_admin_js',
			$script,
			array( 'jquery' ),
			$this->version
		);

		wp_register_style(
			'woocommerce_bundle_rate_shipping_admin_css',
			ENDA_WOOCOMMERCE_URL . 'assets/admin.css',
			array(),
			$this->version,
			false
		);

		// Only load the Javascript and CSS on the wpsc settings page
		$possible_hooks = array(
			'toplevel_page_woocommerce',
			'woocommerce_page_woocommerce_settings',
			'woocommerce_page_wc-settings',
		);

		if ( class_exists( 'WC_Branding' ) ) {

			$brand = get_option( 'woocommerce_branding_name', get_bloginfo( 'name' ) );

			if ( 0 == strlen( $brand ) ) {
				$brand = get_bloginfo( 'name' );
			}

			$possible_hooks[] = sanitize_title( $brand ) . '_page_woocommerce_settings';

		}

		if ( in_array( $hook, $possible_hooks ) ) {

			wp_enqueue_script( 'woocommerce_bundle_rate_shipping_admin_js' );
			wp_enqueue_style( 'woocommerce_bundle_rate_shipping_admin_css' );

		}
	}

	/**
	 * Add a new layer to the configuration screen.
	 *
	 * @return  void
	 */
	public function add_layer() {
		ENDA_Woocommerce_Bundle_Shipping::display_layer();
		exit();
	}


	/**
	 * Add a new configuration to the shipping settings page.
	 *
	 * @return  void
	 */
	public function add_configuration() {
		ENDA_Woocommerce_Bundle_Shipping::display_configuration_layer();
		exit();
	}
}

/**
 * Load plugin.
 */
function enda_woocommerce_load() {
	if ( ! function_exists( 'wc' ) ) {
		return;
	}

	// The new class will not be used unless this is a fresh install or
	// `woocommerce_enda_force_load_new` is set to load true.
	$is_new = get_option( 'woocommerce_enda_bundle_rate_version' ) === false;
	$load_new = $is_new || apply_filters( 'woocommerce_enda_force_load_new', false );

	// Load old plugin if WooCommerce is < 2.6
	if ( ! $load_new || version_compare( wc()->version, '2.6', '<' ) ) {
		include 'deprecated/woocommerce-bundle-rate-shipping.php';
		return;
	}

	new ENDA_Woocommerce();

}

add_action( 'plugins_loaded', 'enda_woocommerce_load' );
