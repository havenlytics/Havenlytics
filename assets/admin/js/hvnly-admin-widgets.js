/**
 * Havenlytics Admin Widget JavaScript
 *
 * @package Havenlytics
 * @since 2.0.0
 */

(function($) {
    'use strict';

    $(document).ready(function() {
        // Initialize widget form enhancements
        initWidgetForms();
    });

    function initWidgetForms() {
        // Add preview functionality for display style selections
        $('.widget-content select[name$="[display_style]"]').on('change', function() {
            const $select = $(this);
            const $widget = $select.closest('.widget-content');
            const value = $select.val();
            
            // Remove existing preview
            $widget.find('.hvnly-widget-preview').remove();
            
            // Add new preview
            let previewHtml = '';
            switch(value) {
                case 'list':
                    previewHtml = '<div class="hvnly-widget-preview"><h4>List Preview</h4><ul><li>• Property 1</li><li>• Property 2</li><li>• Property 3</li></ul></div>';
                    break;
                case 'grid':
                    previewHtml = '<div class="hvnly-widget-preview"><h4>Grid Preview</h4><div style="display:flex; gap:5px;"><div style="flex:1; height:30px; background:#6C60FE20;"></div><div style="flex:1; height:30px; background:#6C60FE20;"></div></div></div>';
                    break;
                case 'carousel':
                    previewHtml = '<div class="hvnly-widget-preview"><h4>Carousel Preview</h4><div style="display:flex; align-items:center;"><span style="margin-right:5px;">◀</span><div style="flex:1; height:30px; background:#6C60FE20;"></div><span style="margin-left:5px;">▶</span></div></div>';
                    break;
            }
            
            if (previewHtml) {
                $select.closest('p').after(previewHtml);
            }
        }).trigger('change');
        
        // Toggle excerpt length field based on show_excerpt checkbox
        $('.widget-content input[name$="[show_excerpt]"]').on('change', function() {
            const $checkbox = $(this);
            const $widget = $checkbox.closest('.widget-content');
            const $excerptLength = $widget.find('input[name$="[excerpt_length]"]').closest('p');
            
            if ($checkbox.is(':checked')) {
                $excerptLength.show();
            } else {
                $excerptLength.hide();
            }
        }).trigger('change');
    }

})(jQuery);