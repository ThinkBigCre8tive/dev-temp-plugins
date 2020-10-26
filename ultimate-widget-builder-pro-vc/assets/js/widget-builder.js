var NjtWidgetBuilder = function(){
    var self = this;
    var $ = jQuery;

    self.start_editing_name = '.njt-widget-builder-start-editing';
    self.start_editing_el = $(self.start_editing_name);

    self.main_frm_class_name = 'njt-widget-builder-iframe';

    self.clone_btn_name = 'njt-widget-builder-clone';
    self.init = function()
    {
        self.insertCloneBtn();

        $(document).on('click', self.start_editing_name, function(event) {
            event.preventDefault();
            var $this = $(this);
            var $number = $this.data('number');
            //$this.parent().append('<iframe src="' + njt_widget_builder.vc_elements_url + '&widget=' + $number + '" class="' + self.main_frm_class_name + ' njt-full-screen"></iframe>')
            $('body').append('<iframe src="' + njt_widget_builder.vc_elements_url + '&widget=' + $number + '" class="' + self.main_frm_class_name + ' njt-full-screen"></iframe>')
        });
        $(document).on('click', '.' + self.clone_btn_name, function(ev) {
            var $original = $(this).parents('.widget');
            var $widget = $original.clone();

            // Find this widget's ID base. Find its number, duplicate.
            var idbase = $widget.find('input[name="id_base"]').val();
            var number = $widget.find('input[name="widget_number"]').val();
            var mnumber = $widget.find('input[name="multi_number"]').val();
            var highest = 0;

            $('input.widget-id[value|="' + idbase + '"]').each(function() {
                var match = this.value.match(/-(\d+)$/);
                if(match && parseInt(match[1]) > highest)
                    highest = parseInt(match[1]);
            });

            var newnum = highest + 1;

            $widget.find('.widget-content').find('input,select,textarea').each(function() {
                if($(this).attr('name'))
                    $(this).attr('name', $(this).attr('name').replace(number, newnum));
            });

            // assign a unique id to this widget:
            var highest = 0;
            $('.widget').each(function() {
                var match = this.id.match(/^widget-(\d+)/);

                if(match && parseInt(match[1]) > highest)
                    highest = parseInt(match[1]);
            });
            var newid = highest + 1;

            // Figure out the value of add_new from the source widget:
            var add = $('#widget-list .id_base[value="' + idbase + '"]').siblings('.add_new').val();
            $widget[0].id = 'widget-' + newid + '_' + idbase + '-' + newnum;
            $widget.find('input.widget-id').val(idbase+'-'+newnum);
            $widget.find('input.widget_number').val(newnum);
            $widget.hide();
            $original.after($widget);
            $widget.fadeIn();

            // Not exactly sure what multi_number is used for.
            $widget.find('.multi_number').val(newnum);

            wpWidgets.save($widget, 0, 0, 1);

            ev.stopPropagation();
            ev.preventDefault();
        });
    }
    self.insertCloneBtn = function()
    {
        var widget_actions_btn = $('.widget-control-actions');
        widget_actions_btn.each(function(index, el) {
            var wrap = $(el);
            var clone_a = $('<a>');
            clone_a
            .addClass(self.clone_btn_name)
            .attr('href', '#')
            .text(njt_widget_builder.clone_text);

            clone_a.insertAfter(wrap.find('.widget-control-remove'));
            clone_a.before(' | ');
        });
        
    };
}
jQuery(document).ready(function($) {
    var njt_widget_builder_app = new NjtWidgetBuilder();
    njt_widget_builder_app.init();

});
