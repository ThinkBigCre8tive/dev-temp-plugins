<?php
namespace MatthiasWeb\RealMediaLibrary\comp;
use MatthiasWeb\RealMediaLibrary\general;
use MatthiasWeb\RealMediaLibrary\base;

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

/**
 * This class handles the compatibility for general page builders. If a page builder
 * has more compatibility options, please see / create another compatibility class.
 */
class PageBuilders extends base\Base {
    
    private static $me = null;
    
    private function __construct($root = null) {
        // Silence is golden.
    }
    
    /**
     * Initialize the page builder handlers.
     */
    public function init() {
        $load_frontend = general\Options::load_frontend();
        
        if (class_exists("Tatsu_Builder")) {
            $this->oshine_tatsu_builder();
        }
        
        if (defined('ELEMENTOR_VERSION')) {
            $this->elementor();
        }
        
        if (class_exists("Cornerstone_Preview_Frame_Loader") && $load_frontend) {
            $this->cornerstone();
        }
        
        if (class_exists('Tailor')) {
            $this->tailor();
        }
        
        if (defined('TVE_IN_ARCHITECT') || class_exists('Thrive_Quiz_Builder')) {
            $this->thrive_architect();
        }
        
        if (class_exists('FLBuilder')) {
            $this->bbuilder();
        }
        
        if (class_exists("Fusion_App") && function_exists("Fusion_App") && method_exists(Fusion_App(), "get_builder_status")) {
            $this->fusionBuilderLive();
        }
    }
    
    /**
     * Fusion Builder Live (Avada)
     * 
     * @see https://themeforest.net/item/avada-responsive-multipurpose-theme/2833226
     */
    private function fusionBuilderLive() {
        $is_builder = Fusion_App()->get_builder_status();
        if ($is_builder) {
            add_filter('RML/Scripts/Skip', array($this, "fusionBuilderLive_skip"), 10, 2);
            add_action('wp_enqueue_scripts', array($this, 'fusionBuilderLive_enqueue_scripts'), 100);
        }
    }
    
    public function fusionBuilderLive_skip($skip, $type) {
        return $type === "fusion_builder_live" ? $skip : true;
    }
    
    public function fusionBuilderLive_enqueue_scripts($type) {
        $this->getCore()->getAssets()->enqueue_scripts_and_styles("fusion_builder_live");
    }
    
    /**
     * Beaver Builder
     * 
     * @see https://www.wpbeaverbuilder.com/
     */
    private function bbuilder() {
        add_action('fl_before_sortable_enqueue', array($this, 'fl_before_sortable_enqueue'));
    }
    
    public function fl_before_sortable_enqueue() {
        $this->getCore()->getAssets()->admin_enqueue_scripts();
        
        /* class-fl-builder.php#enqueue_ui_styles_scripts: We have a custom version of sortable that fixes a bug. */
		wp_deregister_script('jquery-ui-sortable');
    }
    
    /**
     * Tailor page builder.
     * 
     * @see https://de.wordpress.org/plugins/tailor/
     */
    private function tailor() {
        add_action('tailor_enqueue_sidebar_scripts', array($this->getCore()->getAssets(), 'admin_enqueue_scripts'));
    }
    
    /**
     * Cornerstone.
     * 
     * @see https://codecanyon.net/item/cornerstone-the-wordpress-page-builder/15518868
     */
    private function cornerstone() {
        add_filter("print_head_scripts", array($this, 'cornerstone_print_head_scripts'), 0);
    }
    
    public function cornerstone_print_head_scripts($res) {
        $this->getCore()->getAssets()->admin_enqueue_scripts();
        return $res;
    }
    
    /**
     * Elementor.
     * 
     * @see https://elementor.com/
     */
    private function elementor() {
        add_action('elementor/editor/before_enqueue_scripts', array($this->getCore()->getAssets(), 'admin_enqueue_scripts') );
    }
    
    /**
     * OSHINE TATSU PAGE BUILDER.
     * 
     * @see https://themeforest.net/item/oshine-creative-multipurpose-wordpress-theme/9545812
     */
    private function oshine_tatsu_builder() {
        add_action('tatsu_builder_head', array($this->getCore()->getAssets(), 'admin_enqueue_scripts') );
    }
    
    /**
     * Thrive Architect
     * 
     * @see https://thrivethemes.com
     */
    private function thrive_architect() {
        add_action('tcb_main_frame_enqueue', array($this->getCore()->getAssets(), 'admin_enqueue_scripts') );
        add_filter('tge_filter_edit_post', array($this, 'tge_filter_edit_post'));
    }
    
    /**
     * The Thrive Quiz Builder does not allow to enqueue custom scripts so I use the
     * tge_filter_edit_post filter as workaround.
     */
    public function tge_filter_edit_post($post) {
        $this->getCore()->getAssets()->admin_enqueue_scripts();
        return $post;
    }

    public static function getInstance() {
        if (self::$me == null) {
            self::$me = new PageBuilders();
        }
        return self::$me;
    }
}

?>