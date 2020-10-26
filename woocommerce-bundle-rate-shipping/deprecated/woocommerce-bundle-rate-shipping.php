<?php 

// Load bundle rate shipping
function woocommerce_bundle_rate_shipping_load() {
    if ( ! class_exists('enda_woocommerce_bundlerate_shipping' ) ) {
        require_once('bundle_rate.class.php');
    }
}

add_action('woocommerce_shipping_init', 'woocommerce_bundle_rate_shipping_load', 10); 
    
// Actions to run on woocommerce_init hook
function woocommerce_bundle_rate_shipping_init() {

    // Load plugin text domain
    load_plugin_textdomain('woocommerce-bundle-rate-shipping', false, dirname( plugin_basename( __FILE__ ) ) . '/languages');

    // Check whether we are updated to the most recent version
    $version = mktime(0,0,0,3,07,2013);
    $db_version = get_option('woocommerce_enda_bundle_rate_version');
    if ( $db_version === false || $db_version < $version ) {                
        require_once('upgrade.class.php');        

        enda_woocommerce_bundlerate_upgrade::do_upgrade($version, $db_version);
        update_option('woocommerce_enda_bundle_rate_version', $version);
    }
}

add_action('woocommerce_init', 'woocommerce_bundle_rate_shipping_init'); 

// Register shipping module
function woocommerce_bundle_rate_shipping_register_method($methods) {

    woocommerce_bundle_rate_shipping_load();

    $methods[ 'enda_bundle_rate' ] = 'enda_woocommerce_bundlerate_shipping';
    return $methods;
}

add_filter('woocommerce_shipping_methods', 'woocommerce_bundle_rate_shipping_register_method' );    

// Load CSS and Javascript
function woocommerce_bundle_rate_shipping_scripts($hook) {
    // Only load the Javascript and CSS on the wpsc settings page    
    $possible_hooks = array( 'toplevel_page_woocommerce', 'woocommerce_page_woocommerce_settings', 'woocommerce_page_wc-settings' );

    if ( class_exists("WC_Branding")) {
        $brand = get_option('woocommerce_branding_name', get_bloginfo('name')); 
        if (strlen($brand) == 0)
            $brand = get_bloginfo('name');
        $possible_hooks[] = sanitize_title($brand) . '_page_woocommerce_settings';
    }

    if ( in_array( $hook, $possible_hooks ) ) {

        $url_base = ENDA_WOOCOMMERCE_URL . 'deprecated/';

        $script = WP_DEBUG ? $url_base. 'admin.js' : $url_base. 'admin.min.js';

        wp_enqueue_script( 'woocommerce_bundle_rate_shipping_admin_js', $script, array('jquery') );
        wp_register_style( 'woocommerce_bundle_rate_shipping_admin_css', $url_base. 'admin.css', false, '2.0' );
        wp_enqueue_style( 'woocommerce_bundle_rate_shipping_admin_css' );
    }
}    

add_action('admin_enqueue_scripts', 'woocommerce_bundle_rate_shipping_scripts');

// Add layer
function woocommerce_bundle_rate_shipping_add_layer() {
    // Load bundle rate shipping
    woocommerce_bundle_rate_shipping_load();

    enda_woocommerce_bundlerate_shipping::display_layer();
}

add_action('wp_ajax_get_new_layer', 'woocommerce_bundle_rate_shipping_add_layer' );

// Add configuration layer
function woocommerce_bundle_rate_shipping_add_configuration() {
    // Load bundle rate shipping
    woocommerce_bundle_rate_shipping_load();

    enda_woocommerce_bundlerate_shipping::display_configuration_layer();
}

add_action('wp_ajax_get_new_configuration_layer', 'woocommerce_bundle_rate_shipping_add_configuration' );