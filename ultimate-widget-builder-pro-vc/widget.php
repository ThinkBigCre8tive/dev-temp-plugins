<?php
class WidgetBuilderVC extends WP_Widget
{
    public function __construct()
    {
        $widget_ops = array(
            'classname' => 'widget_builder_vc widget',
            'description' => __('Widget Builder With Visual Composer', NJT_WG_B_I18N),
        );
        parent::__construct('widgetbuildervc', __('Widget Builder With Visual Composer', NJT_WG_B_I18N), $widget_ops);
    }
    public function form($instance)
    {
        $instance = wp_parse_args((array)$instance, array('title' => '', 'content' => ''));
        $title = $instance['title'];
        $content = $instance['content'];
        ?>
        <p>
            <label for="<?php echo esc_attr($this->get_field_id('title')); ?>"><?php _e('Title', NJT_WG_B_I18N); ?>: </label>
            <input class="widefat" id="<?php echo esc_attr($this->get_field_id('title')); ?>" name="<?php echo esc_attr($this->get_field_name('title')); ?>" type="text" value="<?php echo esc_attr($title); ?>" />            
        </p>
        <p>
            <a data-number="<?php echo $this->number; ?>" href="javascript:void(0)" class="button button-primary njt-widget-builder-start-editing"><?php _e('Start Editing', NJT_WG_B_I18N); ?></a>
            <textarea name="<?php echo esc_attr($this->get_field_name('content')); ?>" id="<?php echo esc_attr($this->get_field_id('content')); ?>" cols="30" rows="10" class="hidden"><?php echo esc_attr($content); ?></textarea>
        </p>
    <?php
    }
    public function update($new_instance, $old_instance)
    {
        $instance = $old_instance;
        $instance['title'] = $new_instance['title'];
        $instance['content'] = $new_instance['content'];
        return $instance;
    }
    public function widget($args, $instance)
    {
        extract($args, EXTR_SKIP);
        echo $before_widget;

        $title = empty($instance['title']) ? ' ' : apply_filters('widget_title', $instance['title']);
        $title = trim($title);
        $content = $instance['content'];
        if (!empty($title)) {
            echo $before_title . $title . $after_title;
        }

        $shortcodes_custom_css = visual_composer()->parseShortcodesCustomCss($content);
        echo sprintf('<style>%1$s</style>', $shortcodes_custom_css);
        echo do_shortcode($content);
        echo $after_widget;
    }
}
add_action('widgets_init', 'wg_init_njt_wg_builder');
function wg_init_njt_wg_builder() {
    return register_widget("widgetbuildervc");
}
