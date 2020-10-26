<?php

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Settings for bundle rate shipping.
 */
$settings = array(
	
	'title' => array(
        'title'         => __( 'Method Title', 'woocommerce-bundle-rate-shipping' ), 
        'type'          => 'text', 
        'description'   => __( 'This controls the title which the user sees during checkout.', 'woocommerce-bundle-rate-shipping' ), 
        'default'       => __( 'Bundle Rate', 'woocommerce-bundle-rate-shipping' )
    ),

	'tax_status' => array(
        'title'         => __( 'Tax Status', 'woocommerce-bundle-rate-shipping' ), 
        'type'          => 'select', 
        'description'   => '', 
        'default'       => 'taxable',
        'options'       => array(
            'taxable'   => __('Taxable', 'woocommerce-bundle-rate-shipping'),
            'none'      => __('None', 'woocommerce-bundle-rate-shipping')
        )
    ), 

    'fee' => array(
        'title'         => __( 'Handling Fee', 'woocommerce-bundle-rate-shipping' ), 
        'type'          => 'text', 
        'description'   => __('Fee excluding tax. Enter an amount, e.g. 2.50, or a percentage, e.g. 5%. Leave blank to disable.', 'woocommerce-bundle-rate-shipping'),
        'default'       => ''
    ),

    'apply_base_rate_once' => array(
        'title'         => __( 'Only apply the base rate of most expensive configuration used', 'woocommerce-bundle-rate-shipping' ),
        'type'          => 'select',
        'description'   => __( 'If the shipping total for the cart is calculating using more than one of the shipping rate configurations below, only apply the base rate of the most expensive configuration.', 'woocommerce-bundle-rate-shippin' ), 
        'default'       => '1',
        'options'       => array(
            '1'         => __( 'Yes', 'woocommerce-bundle-rate-shipping' ),
            '0'         => __( 'No', 'woocommerce-bundle-rate-shipping' ) 
        ) 
    ), 

    'rates' => array(
        'title'         => __( 'Rates', 'woocommerce-bundle-rate-shipping' ),
        'type'          => 'bundle_rates', 
        'default'       => array(
            array()
        )
    )
);

return $settings;
