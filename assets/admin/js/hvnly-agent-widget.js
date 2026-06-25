/**
 * Agent Widget Admin JavaScript 
 *
 * @package Havenlytics
 */

(function($) {
    'use strict';

    $(document).ready(function() {
        
        // Log that script is loaded
        // console.log('Hvnly Agent Widget JS loaded', hvnlyAgentWidget);
        
        // Initialize all widgets
        initAgentWidgets();
        
        // Handle when widget is added dynamically (in customizer)
        $(document).on('widget-added', function(event, widget) {
            // console.log('Widget added event');
            setTimeout(function() {
                initAgentWidget($(widget));
            }, 100);
        });
        
        // Handle when widget form is updated
        $(document).on('widget-updated', function(event, widget) {
            // console.log('Widget updated event');
            setTimeout(function() {
                initAgentWidget($(widget));
            }, 100);
        });

        /**
         * Initialize all agent widgets
         */
        function initAgentWidgets() {
            $('.hvnly-agent-widget-form').each(function() {
                initAgentWidget($(this));
            });
        }

        /**
         * Initialize a single agent widget
         */
        function initAgentWidget($form) {
            if (!$form.length) return;
            
            // Handle agent source toggle
            initSourceToggle($form);
            
            // Handle widget layout toggle
            initLayoutToggle($form);
            
            // Handle rating enabled toggle
            initRatingToggle($form);
            
            // Initialize property search
            initPropertySearch($form);
            
            // Initialize avatar uploader
            initAvatarUploader($form);
        }

        /**
         * Initialize source toggle
         */
        function initSourceToggle($form) {
            var $sourceSelect = $form.find('.hvnly-agent-source');
            var $customFields = $form.find('.hvnly-agent-custom-fields');
            
            if (!$sourceSelect.length) return;
            
            // Initial state
            $customFields.toggle($sourceSelect.val() === 'custom');
            
            // On change
            $sourceSelect.off('change').on('change', function() {
                $customFields.toggle($(this).val() === 'custom');
            });
        }

        /**
         * Initialize widget layout toggle
         */
        function initLayoutToggle($form) {
            var $layoutSelect = $form.find('.hvnly-agent-widget-layout');
            var $multiLayoutField = $form.find('.hvnly-agent-multi-layout-field');

            if (!$layoutSelect.length) return;

            $multiLayoutField.toggle($layoutSelect.val() === 'classic');

            $layoutSelect.off('change').on('change', function() {
                $multiLayoutField.toggle($(this).val() === 'classic');
            });
        }

        /**
         * Initialize rating toggle
         */
        function initRatingToggle($form) {
            var $ratingCheckbox = $form.find('.hvnly-agent-rating-enabled');
            var $ratingFields = $form.find('.hvnly-agent-rating-fields');
            
            if (!$ratingCheckbox.length) return;
            
            // Initial state
            $ratingFields.toggle($ratingCheckbox.is(':checked'));
            
            // On change
            $ratingCheckbox.off('change').on('change', function() {
                $ratingFields.toggle($(this).is(':checked'));
            });
        }

        /**
         * Initialize property search with autocomplete
         */
        function initPropertySearch($form) {
            var $searchInput = $form.find('.hvnly-property-search');
            var $selectedContainer = $form.find('.hvnly-selected-properties');
            
            if (!$searchInput.length) return;
            
            // Get the target name from data attribute
            var targetName = $searchInput.data('target');
            var widgetId = $searchInput.data('widget-id');
            
            // console.log('Initializing search for widget:', widgetId);
            
            // Destroy any existing autocomplete instance
            if ($searchInput.hasClass('ui-autocomplete-input')) {
                $searchInput.autocomplete('destroy');
            }
            
            // Initialize autocomplete
            $searchInput.autocomplete({
                source: function(request, response) {
                    // Don't search if less than 2 characters
                    if (request.term.length < 2) {
                        response([]);
                        return;
                    }
                    
                    // console.log('Searching for:', request.term);
                    
                    // Get current selected IDs
                    var selectedIds = [];
                    $selectedContainer.find('.hvnly-selected-property').each(function() {
                        var id = $(this).data('id');
                        if (id) selectedIds.push(parseInt(id));
                    });
                    
                    // Prepare data for AJAX
                    var postData = {
                        action: 'hvnly_search_properties',
                        search: request.term,
                        exclude: selectedIds
                    };
                    
                    // console.log('Sending AJAX data:', postData);
                    
                    $.ajax({
                        url: hvnlyAgentWidget.ajaxurl,
                        type: 'POST',
                        data: postData,
                        dataType: 'json',
                        success: function(response_data) {
                            // console.log('AJAX response received:', response_data);
                            
                            if (response_data && response_data.success === true) {
                                // console.log('Results found:', response_data.data ? response_data.data.length : 0);
                                response(response_data.data || []);
                            } 
                            else if (response_data && response_data.success === false) {
                                // console.log('Search error:', response_data.data);
                                if (response_data.data) {
                                    alert('Error: ' + response_data.data);
                                }
                                response([]);
                            }
                            else {
                                // console.log('Unexpected response format');
                                response([]);
                            }
                        },
                        error: function(xhr, status, error) {
                            // console.log('AJAX error:', error);
                            // console.log('Status:', status);
                            // console.log('Response:', xhr.responseText);
                            response([]);
                        }
                    });
                },
                minLength: 2,
                select: function(event, ui) {
                    // console.log('Selected item:', ui.item);
                    if (ui.item && ui.item.id) {
                        addSelectedProperty(ui.item.id, ui.item.title, $form, targetName);
                        $searchInput.val('');
                    }
                    return false;
                }
            });
            
            // Custom render for autocomplete items
            if ($searchInput.data('ui-autocomplete')) {
                $searchInput.data('ui-autocomplete')._renderItem = function(ul, item) {
                    return $('<li>')
                        .append('<div style="padding: 8px 12px; cursor: pointer;">' + 
                               (item.label || item.title) + '</div>')
                        .appendTo(ul);
                };
            }
            
            // Handle remove buttons
            $selectedContainer.off('click', '.hvnly-remove-property').on('click', '.hvnly-remove-property', function(e) {
                e.preventDefault();
                var $property = $(this).closest('.hvnly-selected-property');
                $property.remove();
            });
        }

        /**
         * Add selected property to the list
         */
        function addSelectedProperty(id, title, $form, targetName) {
            var $selectedContainer = $form.find('.hvnly-selected-properties');
            
            // Check if already exists
            if ($selectedContainer.find('.hvnly-selected-property[data-id="' + id + '"]').length) {
                alert('This property is already selected.');
                return;
            }
            
            // Clean the target name - ensure it has [] for array
            var inputName = targetName;
            if (inputName.indexOf('[]') === -1) {
                inputName = inputName + '[]';
            }
            
            // Create property element
            var $property = $('<div class="hvnly-selected-property" data-id="' + id + '">' +
                '<span>' + (title || 'Property') + ' (ID: ' + id + ')</span>' +
                '<button type="button" class="hvnly-remove-property">×</button>' +
                '<input type="hidden" name="' + inputName + '" value="' + id + '">' +
                '</div>');
            
            $selectedContainer.append($property);
        }

        /**
         * Initialize avatar uploader
         */
        function initAvatarUploader($form) {
            var $uploadBtn = $form.find('.hvnly-agent-avatar-upload-btn');
            var $removeBtn = $form.find('.hvnly-agent-avatar-remove-btn');
            var $avatarIdField = $form.find('.hvnly-agent-avatar-id-field');
            var $avatarUrlField = $form.find('.hvnly-agent-avatar-url-field');
            var $preview = $form.find('.hvnly-agent-avatar-preview');
            
            if (!$uploadBtn.length) return;
            
            // Upload button click
            $uploadBtn.off('click').on('click', function(e) {
                e.preventDefault();
                
                // Check if wp.media exists
                if (typeof wp === 'undefined' || typeof wp.media === 'undefined') {
                    alert('Media library is not available.');
                    return;
                }
                
                var frame = wp.media({
                    title: hvnlyAgentWidget.selectAvatar || 'Select Image',
                    multiple: false,
                    library: {
                        type: 'image'
                    },
                    button: {
                        text: hvnlyAgentWidget.useAsAvatar || 'Use Image'
                    }
                });
                
                frame.on('select', function() {
                    var attachment = frame.state().get('selection').first().toJSON();
                    
                    $avatarIdField.val(attachment.id);
                    $avatarUrlField.val(attachment.url);
                    
                    $preview.html('<img src="' + attachment.url + '" alt="" style="max-width: 100px; max-height: 100px; border-radius: 50%;">');
                    $removeBtn.show();
                });
                
                frame.open();
            });
            
            // Remove button click
            $removeBtn.off('click').on('click', function(e) {
                e.preventDefault();
                
                $avatarIdField.val('');
                $avatarUrlField.val('');
                
                $preview.html(
                    '<div class="hvnly-agent-avatar-placeholder" style="width: 100px; height: 100px; background: #f0f0f0; border-radius: 50%; display: flex; align-items: center; justify-content: center;">' +
                    '<i class="fas fa-user-circle fa-3x" style="color: #999;"></i>' +
                    '</div>'
                );
                
                $removeBtn.hide();
            });
        }
    });

})(jQuery);