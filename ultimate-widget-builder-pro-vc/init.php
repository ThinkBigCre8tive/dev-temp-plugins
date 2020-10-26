<?php
if (!defined('ABSPATH')) {
    exit;
}

class NjtWGBuilder
{
    private static $_instance = null;
    private $njt_widget_builder = 'njt-widget-builder';

    public function __construct()
    {
        if (!$this->isActiveVC()) {
            /*
             * Admin notice
             */
            add_action('admin_notices', array($this, 'adminNotices'));
            return false;
        }

        /*
         * Language
         */
        add_action('plugins_loaded', array($this, 'loadTextDomain'));

        /*
         * Register Admin Enqueue
         */
        add_action('admin_enqueue_scripts', array($this, 'registerAdminEnqueue'));

        /*
         * Register WP Enqueue
         */
        add_action('wp_enqueue_scripts', array($this, 'registerWpEnqueue'));

        /*
         * Register Custom Post Type
         */
        add_action('init', array($this, 'registerCustomPostType'));

        /*
         * Add VC to our custom post type
         */
        add_action('admin_init', array($this, 'addCap'));
        add_action('edit_form_after_title', array($this, 'editFormAfterTitle'));
        add_filter('wpb_vc_js_status_filter', array($this, 'vcStatusFilter'));

        /*
         * Add js, css to our custom post type
         */
        add_action('admin_print_scripts-post-new.php', array($this, 'addCustomPostTypeJs'), 1000);
        add_action('admin_print_scripts-post.php', array($this, 'addCustomPostTypeJs'), 1000);

        add_action('admin_print_styles-post-new.php', array($this, 'addCustomPostTypeCss'));
        add_action('admin_print_styles-post.php', array($this, 'addCustomPostTypeCss'));

        /*
         * Add loading effect before loading VC
         */
        add_action('in_admin_header', array($this, 'vcLoading'));
    }
    public function registerAdminEnqueue($hook_suffix)
    {
        if($hook_suffix != 'widgets.php') {
            return;
        }
        wp_register_style('widget-builder', NJT_WG_B_URL . '/assets/css/widget-builder.css');
        wp_enqueue_style('widget-builder');

        wp_register_script('widget-builder', NJT_WG_B_URL . '/assets/js/widget-builder.js', array('jquery'));
        wp_enqueue_script('widget-builder');

        $vc_elements_query = array(
            'action' => 'edit',
            'post' => $this->getWGBuilderMainPost()
        );
        if (isset($_GET['widget'])) {
            $widget_id = (int)$_GET['widget'];
            if ($widget_id > 0) {
                $vc_elements_query['widget'] = (int)$_GET['widget'];
            }
        }
        $vc_elements_url = add_query_arg($vc_elements_query, esc_url(admin_url('post.php')));

        wp_localize_script('widget-builder', 'njt_widget_builder',
            array(
                'vc_elements_url' => $vc_elements_url,
                'clone_text' => __('Clone', NJT_WG_B_I18N)
            )
        );

    }
    public function registerWpEnqueue()
    {
        wp_register_style('widget-builder', NJT_WG_B_URL . '/assets/css/home-widget-builder.css');
        wp_enqueue_style('widget-builder');

    }
    private function getWGBuilderMainPost()
    {
        global $wpdb;
        $post_id = (int)get_option('njt_widget_builder_main_post', null);
        if (is_null($post_id) || !($post_id > 0) || !is_string(get_post_status($post_id)) || (get_post_type($post_id) != $this->njt_widget_builder)) {
            $wpdb->delete('posts', array('post_type' => $this->njt_widget_builder), array('%s'));
            $this->insertWGBuilderMainPost();
            $post_id = get_option('njt_widget_builder_main_post', null);
        }
        return $post_id;
    }
    private function insertWGBuilderMainPost()
    {
        $args = array(
            'post_name' => 'NinjaTeam Widget Builder',
            'post_type' => $this->njt_widget_builder,
        );
        $main_post = wp_insert_post($args);
        update_option('njt_widget_builder_main_post', $main_post);
    }
    public function registerCustomPostType()
    {
        $labels = array(
            'name'               => __('Njt Widget Builder Items', NJT_WG_B_I18N),
        );

        $args = array(
            'labels'             => $labels,
            'public'             => true,
            'publicly_queryable' => false,
            'show_ui'            => true,
            'show_in_menu'       => false,
            'query_var'          => false,
            'rewrite'            => array('slug' => 'njt-widget-builder'),
            'capability_type'    => 'post',
            'has_archive'        => false,
            'hierarchical'       => false,
            'menu_position'      => null,
            'can_export'         => false,
            'supports'           => array('title', 'editor')
        );

        register_post_type($this->njt_widget_builder, $args);
    }
    public function addCustomPostTypeJs()
    {
        global $post;
        if ($post->post_type == $this->njt_widget_builder) {
            wp_register_script('njt_widget_builder-cpt', NJT_WG_B_URL . '/assets/js/cpt.js', array('jquery'));
            wp_enqueue_script('njt_widget_builder-cpt');
            if (isset($_GET['widget'])) {
                wp_localize_script('njt_widget_builder-cpt', 'njt_widget_builder_obj',
                    array(
                        'widget_id' => $_GET['widget'],
                        'saving_text' => __('Saving...', NJT_WG_B_I18N),
                        'close_text' => __('Close', NJT_WG_B_I18N),
                        'close_title_text' => __('Close', NJT_WG_B_I18N),
                        'save_text' => __('Save', NJT_WG_B_I18N),
                        'save_title_text' => __('Save', NJT_WG_B_I18N),
                        'save_close_text' => __('Save & Close', NJT_WG_B_I18N),
                        'save_close_title_text' => __('Save & Close', NJT_WG_B_I18N),
                    )
                );
            }
        }
    }
    public function addCustomPostTypeCss()
    {
        global $post;
        if ($post->post_type == $this->njt_widget_builder) {
            wp_register_style('njt_widget_builder-cpt', NJT_WG_B_URL . '/assets/css/cpt.css');
            wp_enqueue_style('njt_widget_builder-cpt');

            wp_dequeue_script('autosave');
        }
    }
    public function adminNotices()
    {
        if (!$this->isActiveVC()) {
            ?>
            <div class="warning notice notice-warning is-dismissible">
                <p>
                    <?php _e('Widget Builder Warning: Please Active Visual Composer.', NJT_WG_B_I18N) ?>
                </p>
            </div>
            <?php
        }
    }
    public function vcStatusFilter($status)
    {
        global $post;
        if ($post->post_type == $this->njt_widget_builder) {
            return 'true';
        }
        return $status;
    }
    public function addCap()
    {
        $post_types = vc_editor_post_types();
        if (!in_array($this->njt_widget_builder, $post_types)) {
            $post_types[] = $this->njt_widget_builder;
            vc_editor_set_post_types($post_types);
        }        
    }
    public function vcLoading()
    {
        global $post;
        if (!$post || ($post->post_type != $this->njt_widget_builder)) {
            return false;
        }
        ?>
        <div class="njt-widget-builder-loading">
            <div class="njt-widget-builder-loading-inner">
                <div class="cssload-thecube">
                    <div class="cssload-cube cssload-c1"></div>
                    <div class="cssload-cube cssload-c2"></div>
                    <div class="cssload-cube cssload-c4"></div>
                    <div class="cssload-cube cssload-c3"></div>
                </div>
            </div>            
        </div>
        <?php
    }
    public function editFormAfterTitle()
    {
        global $post;
        if (is_admin() && ($post->post_type == $this->njt_widget_builder)) {
            if (isset($_GET['widget'])) {
                $widget_id = intval($_GET['widget']);
                if ($datas = get_option('widget_widgetbuildervc', false)) {
                    if (isset($datas[$widget_id])) {
                        $post->post_content = $datas[$widget_id]['content'];
                    }
                }
            }
        }
    }
    public function loadTextDomain()
    {
        load_plugin_textdomain(NJT_WG_B_I18N, false, plugin_basename(NJT_WG_B_DIR) . '/languages/');
    }

    public static function instance()
    {
        if (is_null(self::$_instance)) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }
    private function isActiveVC()
    {
        return function_exists('vc_map');
    }
}
