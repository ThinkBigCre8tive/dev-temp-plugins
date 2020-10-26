<?php

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class ENDA_Woocommerce_Bundle_Shipping extends WC_Shipping_Method {

    /**
     * Class constructor
     */
    public function __construct( $instance_id = 0 ) { 

        $this->id                   = 'enda_bundle_rate';
        $this->instance_id          = absint( $instance_id );
        $this->method_title         = __( 'Bundle rate', 'woocommerce-bundle-rate-shipping' );
        $this->method_description   = __( 'Set bundled shipping rates for customers who purchase multiple items at a time.', 'woocommerce-bundle-rate-shipping' );
        $this->supports             = array(
            'shipping-zones',
            'instance-settings'
        );

        $this->init();

        add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options' ) );

    }   

    /**
     * init user set variables.
     */
    public function init() {
        $this->instance_form_fields = include( 'includes/settings-bundle-rate.php' );
        $this->title                = $this->get_option( 'title' );
        $this->tax_status           = $this->get_option( 'tax_status' );
        $this->fee                  = $this->get_option( 'fee' );
        $this->apply_base_rate_once = $this->get_option( 'apply_base_rate_once', '1' );        
    }

    /**
     * The bundle rates configuration setting.
     *
     * @param   string $key
     * @param   array $data
     * @return  void
     * @access  public
     * @since   2.0.0
     */
    public function generate_bundle_rates_html( $key, $data ) {
        $field_key = $this->get_field_key( $key );

        $defaults  = array(
            'title'             => '',
            'description'       => ''            
        );

        $data = wp_parse_args( $data, $defaults );

        $this->rates = maybe_unserialize( $this->get_option( 'rates' ) );

        if ( empty( $this->rates ) ) {
            $this->rates = array( array() );
        }

        ob_start();
        ?>
        <tr valign="top">        
            <td id="enda-bundle-rate-configurations" colspan="2">
                <fieldset>
                    <legend class="screen-reader-text"><span><?php echo wp_kses_post( $data['title'] ); ?></span></legend>
                    <?php foreach ( $this->rates as $key => $configuration ) :

                        $this->display_configuration_layer( $key, $configuration );

                    endforeach ?>
                    <p><a class="button add_bundle_configuration" href="#"><?php _e( 'Add Configuration', 'woocommerce-bundle-rate-shipping' ) ?></a></p>
                </fieldset>
            </td>
        </tr>
        <?php

        return ob_get_clean();
    }   

    /**
     * Return HTML for a new configuration layer. Also used in an Ajax hook.
     * 
     * @param   int $key
     * @param   array $configuration
     * @static
     * @return  void
     * @access  public
     * @since   2.0.0
     */
    public static function display_configuration_layer( $key = '', $configuration = array() ) {
        global $woocommerce;

        $is_ajax_post = false;

        if ( ! empty( $_POST ) && defined( 'DOING_AJAX' ) && DOING_AJAX ) {
            $is_ajax_post = true;
            $key = $_POST['index'];
        }

        ?>                                
        <div class="enda-configuration">
            <table class="enda-configuration-rates" cellspacing="0" class="widefat">
                <thead>
                    <tr>
                        <th><?php _e('Number of products', 'woocommerce-bundle-rate-shipping') ?></th>
                        <th colspan="2"><?php _e('Cost per product', 'woocommerce-bundle-rate-shipping') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $rates = array_key_exists( 'rates', $configuration ) 
                        ? $configuration[ 'rates' ] 
                        : array( 
                            0 => array( 'products', 'cost' ), 
                            1 => array( 'products', 'cost' ) 
                        );
                    
                    for ( $i = 0; $i < count( $rates ); $i++ ) :

                        if ($i == 0) : ?>

                            <tr class="rate_row">
                                <td>
                                    <?php _e('First', 'woocommerce-bundle-rate-shipping') ?>
                                    <input type="text" name="woocommerce_enda_bundle_rate_rates[<?php echo $key ?>][rates][<?php echo $i ?>][products]" value="<?php echo array_key_exists( 'products', $rates[0] ) ? htmlentities($rates[0]['products']) : '' ?>" class="bundle_rate_products" />
                                    <?php _e('products', 'woocommerce-bundle-rate-shipping') ?>
                                </td>
                                <td>
                                    <input type="text" name="woocommerce_enda_bundle_rate_rates[<?php echo $key ?>][rates][<?php echo $i ?>][cost]" value="<?php echo array_key_exists( 'cost', $rates[0] ) ? htmlentities($rates[0]['cost']) : '' ?>" />
                                </td>
                                <td></td>
                            </tr>            

                        <?php elseif ($i == count($rates) - 1) : ?>

                            <tr class="rate_row">
                                <td>
                                    <?php _e('All subsequent products', 'woocommerce-bundle-rate-shipping') ?>
                                    <input type="hidden" name="woocommerce_enda_bundle_rate_rates[<?php echo $key ?>][rates][<?php echo $i ?>][products]" value="+" />
                                </td>
                                <td>
                                    <input type="text" name="woocommerce_enda_bundle_rate_rates[<?php echo $key ?>][rates][<?php echo $i ?>][cost]" value="<?php echo array_key_exists( 'cost', $rates[$i] ) ? htmlentities($rates[$i]['cost']) : '' ?>" />
                                </td>     
                                <td></td>                                   
                            </tr>

                        <?php else : ?>

                            <tr class="rate_row">
                                <td>
                                    <?php _e('From', 'woocommerce-bundle-rate-shipping') ?> <span class="start_count"><?php echo ((int) $rates[$i-1]['products'] + 1) ?></span> 
                                    <?php _e('to', 'woocommerce-bundle-rate-shipping') ?> <input type="text" name="woocommerce_enda_bundle_rate_rates[<?php echo $key ?>][rates][<?php echo $i ?>][products]" value="<?php echo htmlentities($rates[$i]['products']) ?>" class="bundle_rate_products" /> 
                                    <?php _e('products', 'woocommerce-bundle-rate-shipping') ?>
                                </td>
                                <td>
                                    <input type="text" name="woocommerce_enda_bundle_rate_rates[<?php echo $key ?>][rates][<?php echo $i ?>][cost]" value="<?php echo array_key_exists( 'cost', $rates[$i] ) ? htmlentities($rates[$i]['cost']) : '' ?>" />
                                </td>
                                <td>
                                    <button class="remove_bundle_layer button"><?php _e('Remove', 'woocommerce-bundle-rate-shipping') ?></button>
                                </td>
                            </tr>             

                        <?php endif;

                    endfor ?>

                    <tr>
                        <td colspan="3">
                            <a class="add_layer button" href=""><?php _e('+ Add Layer', 'woocommerce-bundle-rate-shipping') ?></a>
                        </td>
                    </tr>
                </tbody>
            </table>    
            <table class="enda-configuration-settings widefat">
                <tr>
                    <td class="shipping_category">
                        <label for="woocommerce_enda_bundle_rate_rates_<?php echo $key ?>_category"><?php _e( 'Category', 'woocommerce-bundle-rate-shipping' ) ?></label>
                        <select name="woocommerce_enda_bundle_rate_rates[<?php echo $key ?>][category]" id="woocommerce_enda_bundle_rate_rates_<?php echo $key ?>_category">
                            <option value="all" <?php if ( array_key_exists('category', $configuration) ) selected( 'all', $configuration['category'] ) ?>><?php _e('Apply to all', 'woocommerce-bundle-rate-shipping') ?></option>
                            <?php foreach ( get_terms('product_cat', array('hide_empty' => 0)) as $category ) : ?>
                                <option value="<?php echo $category->term_id ?>" <?php if ( array_key_exists('category', $configuration) ) selected( $category->term_id, $configuration['category'] ) ?>><?php echo $category->name ?></option>
                            <?php endforeach ?>
                        </select>                        
                    </td>
                    <td class="shipping_class">
                        <label for="woocommerce_enda_bundle_rate_rates_<?php echo $key ?>_shipping_class"><?php _e( 'Shipping Class', 'woocommerce-bundle-rate-shipping' ) ?></label>
                        <select name="woocommerce_enda_bundle_rate_rates[<?php echo $key ?>][shipping_class]">                
                            <option value="all" <?php if ( array_key_exists( 'shipping_class', $configuration ) ) selected( 'all', $configuration['shipping_class'] ) ?>><?php _e('Apply to all', 'woocommerce-bundle-rate-shipping') ?></option>
                            <?php foreach ( get_terms('product_shipping_class', array('hide_empty' => 0)) as $category ) : ?>
                            <option value="<?php echo $category->term_id ?>" <?php if ( array_key_exists( 'shipping_class', $configuration ) ) selected( $category->term_id, $configuration['shipping_class'] ) ?>><?php echo $category->name ?></option>
                            <?php endforeach ?>
                        </select>                    
                    </td>
                    <td class="priority">
                        <label for="woocommerce_enda_bundle_rate_rates_<?php echo $key ?>_priority"><?php _e( 'Priority', 'woocommerce-bundle-rate-shipping' ) ?></label>
                        <input type="text" name="woocommerce_enda_bundle_rate_rates[<?php echo $key ?>][priority]" value="<?php echo array_key_exists('priority', $configuration) ? $configuration['priority'] : '0' ?>" />
                        
                    </td> 
                </tr>
                <?php if ( $key > 0 ) : ?>
                    <tr>
                        <td class="remove-configuration" colspan="3">
                            <a class="remove_bundle_configuration" href="#"><?php _e('x Remove Configuration', 'woocommerce-bundle-rate-shipping') ?></a>
                        </td>
                    </tr>
                <?php endif ?>
            </table><!-- .enda-configuration-settings -->         
        </div><!-- .enda-configuration -->        
        <?php
        
        if ( $is_ajax_post ) {
            die();
        }
    }    

    /**
     * Add a new layer. Used by ajax.     
     * 
     * @return  void
     * @static
     * @access  public
     * @since   2.0.0
     */ 
    public static function display_layer() {        
        ?>

        <tr class="rate_row">
            <td><?php _e('From', 'woocommerce-bundle-rate-shipping') ?> <span class="start_count"><?php echo $_POST['start_count'] ?></span> 
                <?php _e('to', 'woocommerce-bundle-rate-shipping') ?> <input type="text" name="<?php echo $_POST['products_input'] ?>" class="bundle_rate_products" /> 
                <?php _e('products', 'woocommerce-bundle-rate-shipping') ?>
            </td>
            <td><input type="text" name="<?php echo $_POST['cost_input'] ?>" value="" />
            <td><button class="remove_bundle_layer button"><?php _e('Remove', 'woocommerce-bundle-rate-shipping') ?></button></td>
        </tr>        

        <?php
        
        die;
    }

    /**
     * Validate the submitted rates. 
     *
     * @param   string $key
     * @param   mixed $value
     * @return  array
     * @access  public
     * @since   2.0.0
     */
    public function validate_rates_field( $key, $value ) {

        if ( ! is_array( $value ) ) {
            return serialize( array( array() ) );
        }

        $new_value = array();

        foreach ( $value as $configuration ) {

            if ( ! isset( $configuration[ 'rates' ] ) 
                || ! isset( $configuration[ 'category' ] ) 
                || ! isset( $configuration[ 'shipping_class' ] )
                || ! isset( $configuration[ 'priority' ] ) ) {

                continue;
            }

            $valid_rates = true;

            foreach ( $configuration[ 'rates' ] as $i => $rate ) {

                if ( empty( $rate[ 'products' ] ) ) {
                    $valid_rates = false;
                }

                if ( ! strlen( $rate[ 'cost' ] ) ) {
                    $configuration[ 'rates' ][ $i ][ 'cost' ] = 0;
                }

            }

            if ( $valid_rates ) {
                $new_value[] = $configuration;
            }
        }

        return serialize( $new_value );
    }

    /**
     * Called to calculate shipping rates for this method. Rates can be added using the add_rate() method.
     *
     * @param   array $package
     * @return  
     */
    public function calculate_shipping( $package = array() ) {

        $cost = 0;        

        // $configurations = maybe_unserialize( $this->get_option( 'rates' ) );

        $temp_rates = array();

        // Check whether each item in the cart has an applicable configuration
        foreach ( $package[ 'contents' ] as $item ) {

            if ( $item[ 'data' ]->needs_shipping() ) {
            
                $item_rates = $this->get_item_configurations( $item );

                if ( count( $item_rates ) == 0 ) {
                    return;
                }

                $rate_id = $this->get_pricing_configuration_id( $item, $item_rates );

                if ( array_key_exists( $rate_id, $temp_rates ) ) {
                    $temp_rates[ $rate_id ] += $item[ 'quantity' ];
                }
                else {
                    $temp_rates[ $rate_id ] = $item[ 'quantity' ];
                }            
            }
        }

        // Get applicable configurations
        $rates = array();
        
        foreach ( $temp_rates as $rate_id => $quantity ) {
            $rate = unserialize( base64_decode( $rate_id ) );
            $rate[ 'quantity' ] = $quantity;
            $rates[] = $rate;
        }        

        // If we are only applying the base rate once, sort configurations by base rate
        if ( $this->apply_base_rate_once ) {
            usort( $rates, array( &$this, 'sort_configurations_by_price' ) );
        }        

        // Allow themes/plugins to override the default configurations setup
        $rates = apply_filters( 'woocommerce_brs_shipping_configurations', $rates );

        // Start adding together cost of shipping, one configuration at a time
        $first = true;

        foreach ( $rates as $rate ) {

            if ( $first === true || ! $this->apply_base_rate_once ) {

                $cost += $this->get_configuration_subtotal( $rate[ 'rates' ], $rate[ 'quantity' ] );
                $first = false;

            } else {

                $cost += $this->get_configuration_subtotal( $rate[ 'rates' ], $rate[ 'quantity' ], 1 );

            }

        }

        // Add handling fee
        if ( $this->fee > 0 ) {
            $cost += $this->get_fee( $this->fee, $package[ 'contents_cost' ] );
        }

        // Allow themes/plugins to override the plugin's calculated shipping rates 
        $cost = apply_filters( 'woocommerce_brs_shipping_total', $cost, $this, $rates );
        
        $rate = array(
            'id'      => $this->get_rate_id(),
            'label'   => $this->title,
            'cost'    => $cost,
            'package' => $package,
        );

        $this->add_rate( $rate );
    }


    /**
     * Return configurations that can be applied to the item
     * 
     * @param   array $item
     * @return  array
     */
    protected function get_item_configurations( $item ) {
        $categories = wp_get_object_terms( $item[ 'product_id' ], 'product_cat' );
        $shipping_classes = $item[ 'data' ]->get_shipping_class_id();
        $shipping_classes = is_array( $shipping_classes ) ? $shipping_classes : array( $shipping_classes );

        $rates = array();        

        foreach ( maybe_unserialize( $this->get_option( 'rates' ) ) as $i => $rate ) {

            $secondary_priority = 2;

            $applicable = false;

            if ( $rate[ 'category' ] == 'all' ) {
                $applicable = true;
            }            
            else {
                foreach ( $categories as $category ) {
                    if ( $category->term_id == $rate[ 'category' ] ) {
                        $applicable = true;
                        $secondary_priority--;
                    }
                }                
            }

            if ( $applicable === true ) {
                if ( $rate[ 'shipping_class' ] == 'all' ) {
                    $rate[ 'secondary_priority' ] = $secondary_priority;
                    $rates[] = $rate;
                }            
                else {                    
                    foreach ( $shipping_classes as $shipping_class ) {
                        if ( $shipping_class == $rate[ 'shipping_class' ] ) {
                            $secondary_priority--;
                            $rate['secondary_priority'] = $secondary_priority;
                            $rates[] = $rate;
                        }
                    }                
                }
            }            
        }

        return $rates;
    }


    /**
     * Get pricing configuration to apply to product. 
     *
     * @param   array $item
     * @param   array $rates
     * @return  int
     */
    protected function get_pricing_configuration_id( $item, $rates ) {
        // If there is only one configuration available, return it
        if ( count( $rates ) == 1 ) {
            $rate = $rates[0];
        }

        // Order by priority, with highest priority first in the array (0 is higher priority than 1)
        // This also reverses the order of the configurations.
        usort( $rates, array( &$this, 'sort_configurations_by_priority' ) );        
        $top_priority = $rates[0]['priority'];
        $prioritized = array();

        // Send back the first top priority configuration.
        foreach ( $rates as $rate ) {
            if ( $rate[ 'priority' ] != $top_priority ) {
                continue;
            }

            // Unset the second priority
            unset( $rate[ 'secondary_priority' ] );

            $rate_id = base64_encode( serialize( $rate ) );
            
            return $rate_id;            
        }
    }

    /**
     * Sort configurations by priority.
     * 
     * @param   array $a
     * @param   array $b
     * @return  int
     * @access  protected
     * @since   2.0.0
     */
    protected function sort_configurations_by_priority( $a, $b ) {
        $priority_a = $a['priority'];
        $priority_b = $b['priority'];

        if ( $priority_a == $priority_b ) {

            $secondary_priority_a = $a['secondary_priority'];
            $secondary_priority_b = $b['secondary_priority'];

            if ( $secondary_priority_a == $secondary_priority_b )
                return 0;
            
            return $secondary_priority_a < $secondary_priority_b ? -1 : 1;
        }

        return $priority_a < $priority_b ? -1 : 1;
    }

    /**
     * Sort configurations by price
     * @param   array $a
     * @param   array $b
     * @return  int
     * @access  protected
     * @since   2.0.0
     */
    protected function sort_configurations_by_price( $a, $b ) {
        $a1 = $a['rates'][0]['cost'];
        $b1 = $b['rates'][0]['cost'];

        // Identical first rate, so look at the second rate. 
        if ( $a1 == $b1 ) {
            $a2 = $a['rates'][1]['cost'];
            $b2 = $b['rates'][1]['cost'];

            if ( $a2 == $b2 ) {
                return 0;
            }

            // This is the opposite of the first-rate tier, because we want 
            // the one with the more expensive secondary rate to be used as 
            // the second item in the calculation.
            return $a2 > $b2 ? 1 : -1;
        }

        return $a1 < $b1 ? 1 : -1;
    }

    /**
     * Return subtotal for configuration. 
     *
     * @param   array $rate
     * @param   int $count
     * @param   int $i
     * @param   float $subtotal
     * @param   int $counted
     * @return  string
     */
    protected function get_configuration_subtotal( $rate, $count, $i = 0, $subtotal = 0, $counted = 0 ) {

        // All remaining products will be counted in this round
        if ( $rate[ $i ][ 'products' ] == '+' ) {
            $to_count = $count - $counted;
        }
        else {
            $to_count = $rate[ $i ][ 'products' ] > $count 
                ? $count - $counted 
                : $rate[ $i ][ 'products' ] - $counted;
        }        

        $subtotal = $subtotal + ( $rate[ $i] [ 'cost' ] * $to_count );
        $counted = $counted + $to_count;

        if ( $count > $counted ) {
            $i += 1;               
            return $this->get_configuration_subtotal( $rate, $count, $i, $subtotal, $counted );
        }

        return $subtotal;   
    }    

}