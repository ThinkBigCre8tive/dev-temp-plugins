jQuery(document).ready(function($) {

    var parentWindow = window.parent || window.top;

    jQuery('.njt-widget-builder-loading').hide();

    $('#vc_navbar .vc_navbar-nav').prepend('<li class="vc_pull-right"><a id="njt-widget-builder-cancel-editing" href="javascript:;" class="vc_icon-btn" title="'+njt_widget_builder_obj.close_title_text+'">'+njt_widget_builder_obj.close_text+'</a></li>');
    $('#vc_navbar .vc_navbar-nav').prepend('<li class="vc_pull-right"><a id="njt-widget-builder-save-editing" href="javascript:;" class="vc_icon-btn" title="'+njt_widget_builder_obj.save_title_text+'">'+njt_widget_builder_obj.save_text+'</a></li>');
    //$('#vc_navbar .vc_navbar-nav').prepend('<li class="vc_pull-right"><a id="njt-widget-builder-save-and-close-editing" href="javascript:;" class="vc_icon-btn" title="'+njt_widget_builder_obj.save_close_title_text+'">'+njt_widget_builder_obj.save_close_text+'</a></li>');

    $(document).on('click', '#njt-widget-builder-save-editing', function(event) {
        event.preventDefault();
        //jQuery('.njt-widget-builder-loading').show();
        var $this = $(this);
        var form = $(this).closest('#post');
        var content = form.find('#content').val();

        jQuery('textarea#widget-widgetbuildervc-' + njt_widget_builder_obj.widget_id + '-content', parentWindow.document).val(content);
        // Save Form
        $this.text(njt_widget_builder_obj.saving_text);

        var save_btn = jQuery('#widget-widgetbuildervc-' + njt_widget_builder_obj.widget_id + '-savewidget', parentWindow.document);
        save_btn.removeAttr('disabled').click();
        $(save_btn.closest('form').find('.widget-content')).bind("DOMSubtreeModified",function(){
            $this.text(njt_widget_builder_obj.save_text);
        });
        //jQuery('.njt-widget-builder-loading').hide();
    });
    $(document).on('click', '#njt-widget-builder-save-and-close-editing', function(event) {
        event.preventDefault();
        jQuery('.njt-widget-builder-loading').show();
        
        var form = $(this).closest('#post');
        var content = form.find('#content').val();

        jQuery('textarea#widget-widgetbuildervc-' + njt_widget_builder_obj.widget_id + '-content', parentWindow.document).val(content);
        // Save Form
        jQuery('.njt-widget-builder-loading').hide();
        jQuery('.njt-widget-builder-iframe', parentWindow.document).remove();

        jQuery('#widget-widgetbuildervc-' + njt_widget_builder_obj.widget_id + '-savewidget', parentWindow.document).trigger('click');
    });
    $(document).on('click', '#njt-widget-builder-cancel-editing', function(event) {
        event.preventDefault();
        jQuery('.njt-widget-builder-iframe', parentWindow.document).remove();
    });
});