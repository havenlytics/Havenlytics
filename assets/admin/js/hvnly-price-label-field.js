/**
 * Havenlytics Price Label Field JavaScript
 * Handles toggle between numeric price and custom price label
 * 
 * @package Havenlytics
 * @since 2.1.3
 */

(function($) {
    'use strict';

    /**
     * Initialize price label field
     * 
     * @param {jQuery} $wrapper The field wrapper element
     */
    function initPriceLabelField($wrapper) {
        var $toggle = $wrapper.find('.hvnly-price-label-mode-toggle');
        var $standardMode = $wrapper.find('.hvnly-price-label-standard-mode');
        var $customMode = $wrapper.find('.hvnly-price-label-custom-mode');
        var $priceInput = $wrapper.find('.hvnly-price-label-price-input');
        var $labelSelect = $wrapper.find('.hvnly-price-label-select');
        var $hiddenField = $wrapper.find('.hvnly-price-label-value');
        var $previewSpan = $wrapper.find('.hvnly-price-label-preview-value');
        var $modeText = $wrapper.find('.hvnly-price-label-mode-text');
        var $hintText = $wrapper.find('.hvnly-price-label-hint');
        var $fieldWrapper = $wrapper.closest('.hvnly__dyamic_metabox_tab__field');
        var currencySymbol = $wrapper.data('currency-symbol') || '$';
        var currencySettings = window.HvnlyPriceLabelField?.currency || {};
        var isRequired = $wrapper.data('is-required') === 'true';
        var currentMode = $wrapper.data('current-mode');
        var currentValue = $wrapper.data('current-value');
        var strings = window.HvnlyPriceLabelField?.strings || {};

        if (currencySettings.symbol) {
            currencySymbol = currencySettings.symbol;
            $wrapper.attr('data-currency-symbol', currencySymbol);
            $wrapper.find('.hvnly-price-label-currency').text(currencySymbol);
        }

        // Set initial states based on stored data
        if (currentMode === 'custom') {
            $toggle.prop('checked', true);
            if (currentValue) {
                $labelSelect.val(currentValue);
                // Clear error class if value exists
                $fieldWrapper.removeClass('hvnly-field-error');
            }
        } else {
            $toggle.prop('checked', false);
            if (currentValue && currentValue !== '') {
                $priceInput.val(currentValue);
                // Clear error class if value exists
                $fieldWrapper.removeClass('hvnly-field-error');
            }
        }

        // Restrict price input to numbers only
        $priceInput.on('keypress', function(e) {
            var charCode = e.which ? e.which : e.keyCode;
            // Allow numbers, decimal point, backspace, delete, tab, enter, escape, etc.
            if (charCode !== 46 && charCode > 31 && (charCode < 48 || charCode > 57)) {
                e.preventDefault();
            }
        });

        /**
         * Check if field has valid value
         * 
         * @returns {boolean}
         */
        function hasValidValue() {
            var isCustom = $toggle.is(':checked');
            
            if (isCustom) {
                var selectedValue = $labelSelect.val();
                return selectedValue && selectedValue !== '';
            } else {
                var priceValue = $priceInput.val();
                return priceValue && priceValue.trim() !== '';
            }
        }

        /**
         * Update field error state and trigger parent validation
         */
        function updateErrorStateAndValidate() {
            var isValid = hasValidValue();
            
            if (isValid) {
                $fieldWrapper.removeClass('hvnly-field-error');
                // Also remove error class from any parent required indicators
                $fieldWrapper.find('.hvnly-field-error').removeClass('hvnly-field-error');
            } else if (isRequired) {
                $fieldWrapper.addClass('hvnly-field-error');
            }
            
            // Trigger the parent metabox's validation update
            if (window.HavenlyticsAdminMetabox && window.HavenlyticsAdminMetabox.updateTabRequiredIndicators) {
                window.HavenlyticsAdminMetabox.updateTabRequiredIndicators();
            } else if (window.HavenlyticsAdminMetabox && typeof window.HavenlyticsAdminMetabox.updateTabRequiredIndicators === 'function') {
                window.HavenlyticsAdminMetabox.updateTabRequiredIndicators();
            } else {
                // Fallback: trigger change event on the field wrapper
                $fieldWrapper.trigger('hvnly-field-change');
                $('.hvnly__dyamic_metabox_tab__field input, .hvnly__dyamic_metabox_tab__field textarea, .hvnly__dyamic_metabox_tab__field select').trigger('change');
            }
        }

        /**
         * Format numeric price using global currency settings from plugin settings.
         *
         * @param {number} numValue
         * @returns {string}
         */
        function formatNumericPrice(numValue) {
            var symbol = currencySettings.symbol || currencySymbol || '$';
            var position = currencySettings.position || 'LEFT';
            var thousandSep = currencySettings.thousandSeparator || ',';
            var decimalSep = currencySettings.decimalSeparator || '.';
            var decimals = parseInt(currencySettings.decimals, 10);
            if (isNaN(decimals)) {
                decimals = 0;
            }

            var enableLargeFormat = !!currencySettings.enableLargeFormat;
            var thousandText = currencySettings.thousandText || 'K';
            var millionText = currencySettings.millionText || 'M';
            var billionText = currencySettings.billionText || 'B';

            var absValue = Math.abs(numValue);
            var formattedNumber = '';

            if (enableLargeFormat && absValue >= 1000) {
                if (absValue >= 1000000000) {
                    formattedNumber = (numValue / 1000000000).toFixed(1).replace(/\.0$/, '') + billionText;
                } else if (absValue >= 1000000) {
                    formattedNumber = (numValue / 1000000).toFixed(1).replace(/\.0$/, '') + millionText;
                } else if (absValue >= 1000) {
                    formattedNumber = (numValue / 1000).toFixed(1).replace(/\.0$/, '') + thousandText;
                }
            }

            if (!formattedNumber) {
                var fixed = numValue.toFixed(decimals);
                var parts = fixed.split('.');
                parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, thousandSep);
                formattedNumber = parts.length > 1 ? parts.join(decimalSep) : parts[0];
            }

            if (position === 'RIGHT') {
                return formattedNumber + symbol;
            }

            return symbol + formattedNumber;
        }

        /**
         * Update preview text
         */
        function updatePreview() {
            var isCustom = $toggle.is(':checked');
            var previewText = '';

            if (isCustom) {
                var selectedOption = $labelSelect.find('option:selected');
                previewText = selectedOption.text();
                if (!previewText || previewText === strings.selectLabel || previewText === '-- Select price label --') {
                    previewText = strings.notSet || 'Not set';
                }
            } else {
                var priceValue = $priceInput.val();
                if (priceValue && priceValue.trim() !== '' && !isNaN(parseFloat(priceValue))) {
                    previewText = formatNumericPrice(parseFloat(priceValue));
                } else if (priceValue && priceValue.trim() !== '') {
                    previewText = priceValue;
                } else {
                    previewText = strings.notSet || 'Not set';
                }
            }

            $previewSpan.text(previewText);
        }

        /**
         * Update hidden field with current value
         */
        function updateHiddenField() {
            var isCustom = $toggle.is(':checked');
            var newValue = '';

            if (isCustom) {
                var selectedValue = $labelSelect.val();
                var selectedLabel = $labelSelect.find('option:selected').text();
                
                // Clean up label - remove extra whitespace and newlines
                if (selectedLabel) {
                    selectedLabel = selectedLabel.replace(/\s+/g, ' ').trim();
                }
                
                if (selectedValue && selectedValue !== '') {
                    newValue = JSON.stringify({
                        __type: 'custom_label',
                        value: selectedValue,
                        label: selectedLabel
                    });
                }
            } else {
                var priceValue = $priceInput.val();
                if (priceValue && priceValue.trim() !== '') {
                    newValue = priceValue;
                }
            }

            $hiddenField.val(newValue);
            updatePreview();
            
            // Update error state after value change
            updateErrorStateAndValidate();
            
            // Trigger change event for WordPress validation
            $hiddenField.trigger('change');
            $fieldWrapper.trigger('hvnly-field-updated');
        }

        /**
         * Update UI based on mode
         */
        function updateModeUI() {
            var isCustom = $toggle.is(':checked');

            if (isCustom) {
                $standardMode.hide();
                $customMode.show();
                $modeText.html('<i class="fas fa-tag"></i> ' + (strings.customMode || 'Custom Price Label Mode'));
                $hintText.html(strings.toggleOff || 'Toggle OFF to enter a numeric price');
                
                // Remove required attribute from price input - FIXED: Use prop() instead of removeAttr()
                $priceInput.prop('required', false);
            } else {
                $standardMode.show();
                $customMode.hide();
                $modeText.html('<span class="hvnly-price-label-currency-icon">' + (currencySettings.symbol || currencySymbol) + '</span> ' + (strings.standardMode || 'Standard Price Mode'));
                $hintText.html(strings.toggleOn || 'Toggle ON to select a custom price label');
                
                // Add required attribute back if needed
                if (isRequired) {
                    $priceInput.prop('required', true);
                }
            }

            updateHiddenField();
        }

        // Bind events
        $toggle.on('change', function() {
            updateModeUI();
            // Additional validation trigger for toggle
            setTimeout(function() {
                updateErrorStateAndValidate();
            }, 50);
        });
        
        $priceInput.on('input', function() {
            updateHiddenField();
            // Immediate validation on input
            updateErrorStateAndValidate();
        });
        
        $priceInput.on('blur', function() {
            // Validate on blur as well
            updateErrorStateAndValidate();
        });
        
        $labelSelect.on('change', function() {
            updateHiddenField();
            // Immediate validation on select change
            updateErrorStateAndValidate();
        });

        // Initialize UI
        updateModeUI();
        
        // Mark as initialized
        $wrapper.data('initialized', true);
    }

    // Initialize on document ready
    $(document).ready(function() {
        $('.hvnly-price-label-field-wrapper').each(function() {
            var $wrapper = $(this);
            if (!$wrapper.data('initialized')) {
                initPriceLabelField($wrapper);
            }
        });
    });

    // Re-initialize after AJAX (for dynamic content)
    $(document).ajaxComplete(function() {
        $('.hvnly-price-label-field-wrapper').each(function() {
            var $wrapper = $(this);
            if (!$wrapper.data('initialized')) {
                initPriceLabelField($wrapper);
            }
        });
    });

    // Also listen for any field updates from the metabox
    $(document).on('hvnly-field-updated', '.hvnly-price-label-field-wrapper', function() {
        var $wrapper = $(this);
        // Re-validate
        var $fieldWrapper = $wrapper.closest('.hvnly__dyamic_metabox_tab__field');
        var isCustom = $wrapper.find('.hvnly-price-label-mode-toggle').is(':checked');
        var hasValue = false;
        
        if (isCustom) {
            hasValue = $wrapper.find('.hvnly-price-label-select').val() && $wrapper.find('.hvnly-price-label-select').val() !== '';
        } else {
            hasValue = $wrapper.find('.hvnly-price-label-price-input').val() && $wrapper.find('.hvnly-price-label-price-input').val().trim() !== '';
        }
        
        if (hasValue) {
            $fieldWrapper.removeClass('hvnly-field-error');
        }
    });

})(jQuery);