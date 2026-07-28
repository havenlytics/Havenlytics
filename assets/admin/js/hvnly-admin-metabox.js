/**
 * Havenlytics Admin Metabox - Main Controller
 * @package Havenlytics
 * @version 2.0.0
 */

(function($) {
    'use strict';

    class HavenlyticsAdminMetabox {
        constructor() {
            this.initialized = false;
            this.config = window.HvnlyNabMetaBox || {};
            this.i18n = this.config.i18n || {};
            
            $(() => {
                this.init();
            });
        }

        t(key, fallback) {
            return (this.i18n && this.i18n[key]) ? String(this.i18n[key]) : fallback;
        }

        /**
         * Initialize validation modal container if not exists
         * Using UNIQUE class names to avoid conflict with document field modal
         */
        initValidationModal() {
            if ($('#hvnly-validation-popup-overlay').length > 0) {
                return;
            }
            
            // Create modal HTML with UNIQUE IDs and classes
            const modalHTML = `
                <div id="hvnly-validation-popup-overlay" style="display: none;">
                    <div id="hvnly-validation-popup-modal">
                        <div id="hvnly-validation-popup-header">
                            <h3 id="hvnly-validation-popup-title"></h3>
                            <button type="button" id="hvnly-validation-popup-close">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                        <div id="hvnly-validation-popup-body"></div>
                        <div id="hvnly-validation-popup-footer"></div>
                    </div>
                </div>
            `;
            
            $('body').append(modalHTML);
            
            // Add inline CSS for validation popup with UNIQUE class names
            if (!$('#hvnly-validation-popup-inline-css').length) {
                const inlineCSS = `
                    <style id="hvnly-validation-popup-inline-css">
                        #hvnly-validation-popup-overlay {
                            position: fixed;
                            top: 0;
                            left: 0;
                            right: 0;
                            bottom: 0;
                            background: rgba(0, 0, 0, 0.7);
                            backdrop-filter: blur(4px);
                            display: flex;
                            align-items: center;
                            justify-content: center;
                            z-index: 100001;
                        }
                        #hvnly-validation-popup-modal {
                            background: #fff;
                            border-radius: 12px;
                            max-width: 500px;
                            width: 90%;
                            box-shadow: 0 20px 40px rgba(0,0,0,0.2);
                            overflow: hidden;
                            animation: hvnlyValidationFadeIn 0.3s ease;
                        }
                        @keyframes hvnlyValidationFadeIn {
                            from {
                                opacity: 0;
                                transform: scale(0.95) translateY(-20px);
                            }
                            to {
                                opacity: 1;
                                transform: scale(1) translateY(0);
                            }
                        }
                        #hvnly-validation-popup-header {
                            padding: 16px 20px;
                            background: linear-gradient(135deg, #6c60fe, #764ba2);
                            display: flex;
                            justify-content: space-between;
                            align-items: center;
                        }
                        #hvnly-validation-popup-title {
                            color: #fff;
                            margin: 0;
                            font-size: 18px;
                            font-family: 'Inter', sans-serif;
                        }
                        #hvnly-validation-popup-close {
                            background: rgba(255,255,255,0.2);
                            border: none;
                            color: #fff;
                            cursor: pointer;
                            width: 30px;
                            height: 30px;
                            border-radius: 50%;
                            display: flex;
                            align-items: center;
                            justify-content: center;
                            transition: all 0.2s ease;
                            font-size: 14px;
                        }
                        #hvnly-validation-popup-close:hover {
                            background: rgba(255,255,255,0.3);
                            transform: rotate(90deg);
                        }
                        #hvnly-validation-popup-body {
                            padding: 20px;
                            max-height: 60vh;
                            overflow-y: auto;
                        }
                        #hvnly-validation-popup-footer {
                            padding: 16px 20px;
                            border-top: 1px solid #eee;
                            display: flex;
                            justify-content: flex-end;
                            gap: 10px;
                            background: #f8f9fa;
                        }
                        .hvnly-validation-popup-button {
                            padding: 8px 20px;
                            border-radius: 6px;
                            border: none;
                            cursor: pointer;
                            font-size: 14px;
                            font-weight: 500;
                            transition: all 0.2s ease;
                        }
                        .hvnly-validation-popup-button.primary {
                            background: #6c60fe;
                            color: #fff;
                        }
                        .hvnly-validation-popup-button.primary:hover {
                            background: #5a4ee0;
                            transform: translateY(-1px);
                        }
                        .hvnly-validation-popup-icon {
                            text-align: center;
                            margin-bottom: 20px;
                        }
                        .hvnly-validation-popup-icon .warning-icon {
                            width: 60px;
                            height: 60px;
                            border-radius: 50%;
                            display: inline-flex;
                            align-items: center;
                            justify-content: center;
                            background: rgba(255,106,0,0.1);
                            color: #FF6A00;
                        }
                        .hvnly-validation-popup-icon .warning-icon i {
                            font-size: 30px;
                        }
                        .hvnly-validation-popup-message {
                            text-align: center;
                            margin: 0;
                            line-height: 1.6;
                        }
                        .hvnly-validation-popup-message ul {
                            text-align: left;
                            margin: 15px 0;
                            padding-left: 20px;
                        }
                        .hvnly-validation-popup-message li {
                            margin-bottom: 8px;
                        }
                        @media (prefers-color-scheme: dark) {
                            #hvnly-validation-popup-modal { background: #1E1E2F; }
                            #hvnly-validation-popup-footer { background: #2d2d3a; border-top-color: #444; }
                            .hvnly-validation-popup-message { color: #e0e0e0; }
                        }
                        @media (max-width: 782px) {
                            #hvnly-validation-popup-footer { flex-direction: column-reverse; }
                            .hvnly-validation-popup-button { width: 100%; justify-content: center; }
                        }
                    </style>
                `;
                $('head').append(inlineCSS);
            }
    
            // Close modal events
            $('#hvnly-validation-popup-close').off('click').on('click', () => this.hideValidationModal());
            $('#hvnly-validation-popup-overlay').off('click').on('click', (e) => {
                if ($(e.target).is('#hvnly-validation-popup-overlay')) {
                    this.hideValidationModal();
                }
            });
            
            // Close on ESC key
            $(document).off('keyup.hvnlyValidationPopup').on('keyup.hvnlyValidationPopup', (e) => {
                if (e.key === 'Escape' && this.isValidationModalVisible()) {
                    this.hideValidationModal();
                }
            });
        }
    
        /**
         * Check if validation modal is visible
         */
        isValidationModalVisible() {
            const $overlay = $('#hvnly-validation-popup-overlay');
            return $overlay.length && $overlay.is(':visible');
        }
    
        /**
         * Show validation modal - ONLY for validation errors
         */
        showValidationModal(title, body) {
            // Ensure modal exists
            this.initValidationModal();
            
            const $overlay = $('#hvnly-validation-popup-overlay');
            
            if (!$overlay.length) {
                // Fallback to alert
                const plainText = body.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
                alert(title + '\n\n' + plainText);
                return;
            }
            
            const $title = $('#hvnly-validation-popup-title');
            const $body = $('#hvnly-validation-popup-body');
            const $footer = $('#hvnly-validation-popup-footer');
    
            // Icon for validation warning
            const iconHtml = `<div class="hvnly-validation-popup-icon">
                                <div class="warning-icon">
                                    <i class="fas fa-exclamation-triangle"></i>
                                </div>
                            </div>`;
    
            $title.html(title);
            $body.html(iconHtml + body);
            $footer.empty();
    
            // Add OK button only
            const $btn = $(`
                <button type="button" class="hvnly-validation-popup-button primary">
                    <i class="fas fa-check"></i>
                    ${this.t('ok', 'OK')}
                </button>
            `);
            
            $footer.append($btn);
    
            // Show overlay
            $overlay.css('display', 'flex').hide().fadeIn(200);
    
            // Bind button event
            const self = this;
            $footer.find('.hvnly-validation-popup-button').off('click').on('click', function() {
                self.hideValidationModal();
            });
        }
    
        /**
         * Hide validation modal
         */
        hideValidationModal() {
            const $overlay = $('#hvnly-validation-popup-overlay');
            if ($overlay.length) {
                $overlay.fadeOut(200);
            }
        }
    
        /**
         * Show validation error modal with formatted content
         */
        showValidationErrorModal(emptyFields) {
            let body = '<p class="hvnly-validation-popup-message" style="margin-bottom: 15px;">' + this.t('requiredFieldsIntro', 'The following required fields are empty or invalid:') + '</p>';
            body += '<ul style="margin: 15px 0; padding-left: 20px; list-style: disc; color: var(--hvnly-text-secondary, #555);">';
            
            emptyFields.forEach((field) => {
                body += `<li style="margin-bottom: 8px;"><strong>${this.escapeHtml(field.label)}</strong>`;
                if (field.type === 'number') {
                    body += ` <span style="color: var(--hvnly-brand-accent, #FF6A00); font-size: 12px;">${this.t('numberGreaterThanZero', '(Please enter a number greater than 0)')}</span>`;
                }
                body += `</li>`;
            });
            
            body += '</ul>';
            body += '<p class="hvnly-validation-popup-message" style="margin-top: 15px; padding-top: 15px; border-top: 1px solid var(--hvnly-border-color, #E4E4ED);">⚠️ ' + this.t('requiredFieldsHint', 'Red border indicates empty or invalid field. Please fill in all highlighted fields before saving.') + '</p>';
            
            this.showValidationModal('⚠️ ' + this.t('requiredMissingTitle', 'Required Fields Missing'), body);
        }
    
        /**
         * Show PHP validation error modal
         */
        showPHPValidationErrorModal(errorMessages) {
            let body = '<p class="hvnly-validation-popup-message" style="margin-bottom: 15px;">' + this.t('phpRequiredIntro', 'The following required fields need attention:') + '</p>';
            body += '<ul style="margin: 15px 0; padding-left: 20px; list-style: disc; color: var(--hvnly-text-secondary, #555);">';
            
            errorMessages.forEach((msg) => {
                body += `<li style="margin-bottom: 5px;">${this.escapeHtml(msg)}</li>`;
            });
            
            body += '</ul>';
            body += '<p class="hvnly-validation-popup-message" style="margin-top: 15px; padding-top: 15px; border-top: 1px solid var(--hvnly-border-color, #E4E4ED);">💡 ' + this.t('phpZeroHint', 'Fields with value "0" are not accepted. Enter a number greater than 0.') + '</p>';
            
            this.showValidationModal('⚠️ ' + this.t('phpRequiredTitle', 'Please Complete Required Fields'), body);
        }
    
        /**
         * Helper to escape HTML
         */
        escapeHtml(text) {
            if (!text) return '';
            return String(text)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
        }
    
        /**
         * Check for PHP validation errors from URL parameter and show modal
         */
        checkForPHPValidationErrors() {
            // Check if URL has validation error parameter
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.has('hvnly_validation_error')) {
                // Get error messages from the admin notice element
                setTimeout(() => {
                    const $errorNotice = $('.notice.notice-error');
                    if ($errorNotice.length) {
                        const errorMessages = [];
                        $errorNotice.find('li').each((index, li) => {
                            errorMessages.push($(li).text());
                        });
                        
                        if (errorMessages.length > 0) {
                            try {
                                this.showPHPValidationErrorModal(errorMessages);
                            } catch(e) {
                                let alertMessage = '╔════════════════════════════════════════════════════╗\n';
                                alertMessage += '║           ' + this.t('alertBanner', 'PLEASE COMPLETE REQUIRED FIELDS') + '            ║\n';
                                alertMessage += '╚════════════════════════════════════════════════════╝\n\n';
                                alertMessage += 'The following required fields need attention:\n\n';
                                
                                errorMessages.forEach((msg, i) => {
                                    alertMessage += `${i + 1}. ${msg}\n`;
                                });
                                
                                alertMessage += '\n────────────────────────────────────────────────────────\n';
                                alertMessage += '💡  Fields with value "0" are not accepted. Enter a number greater than 0.\n';
                                alert(alertMessage);
                            }
                        }
                    }
                }, 500);
            }
        }

        init() {
            if (this.initialized) return;

            try {
                // Initialize validation modal (only for validation errors)
                this.initValidationModal();
                
                this.initializeTabs();
                this.bindEvents();
                this.markRequiredFields();
                this.updateTabRequiredIndicators();
                this.clearAgentsPickerValidationStates();
                
                // Check for PHP validation errors
                this.checkForPHPValidationErrors();
                
                this.initialized = true;
            } catch (error) {
               // console.error('[Havenlytics] Init error:', error);
            }
        }

        initializeTabs() {
            const tabLinks = $('.hvnly__dyamic_metabox_tab__nav a');
            const savedIndex = localStorage.getItem('hvnly_active_tab_index');
            
            tabLinks.removeClass('hvnly__dyamic_metabox_tab__active')
                    .parent().removeClass('hvnly__dyamic_metabox_tab__active');
            $('.hvnly__dyamic_metabox_tab__tab-content').removeClass('hvnly__dyamic_metabox_tab__active');
            
            let activeIndex = 0;
            if (savedIndex !== null && tabLinks.eq(savedIndex).length) {
                activeIndex = parseInt(savedIndex, 10);
            }
            
            this.activateTab(activeIndex);
        }

        activateTab(index) {
            const tabLinks = $('.hvnly__dyamic_metabox_tab__nav a');
            const $link = tabLinks.eq(index);
            const target = $link.data('target');
            
            if (!$link.length || !target) return;
            
            tabLinks.removeClass('hvnly__dyamic_metabox_tab__active')
                    .parent().removeClass('hvnly__dyamic_metabox_tab__active');
            $('.hvnly__dyamic_metabox_tab__tab-content').removeClass('hvnly__dyamic_metabox_tab__active');
            
            $link.addClass('hvnly__dyamic_metabox_tab__active')
                 .parent().addClass('hvnly__dyamic_metabox_tab__active');
            $(target).addClass('hvnly__dyamic_metabox_tab__active');
            
            localStorage.setItem('hvnly_active_tab_index', index);
            
            setTimeout(() => {
                if (typeof L !== 'undefined') {
                    $(target).find('.hvnly-leaflet-map-container').each((index, element) => {
                        const map = $(element).data('leaflet-map');
                        if (map) {
                            map.invalidateSize();
                        }
                    });
                }
            }, 100);
        }

        markRequiredFields() {
            $('.hvnly__dyamic_metabox_tab__field').each((index, field) => {
                const $field = $(field);
                const $requiredIndicator = $field.find('.required');
                const $requiredInput = $field.find('[required]');
                
                if ($field.hasClass('hvnly-field-required')) {
                    $field.attr('data-required', 'true');
                } else if ($requiredIndicator.length > 0 || $requiredInput.length > 0) {
                    $field.addClass('hvnly-field-required');
                    $field.attr('data-required', 'true');
                } else if ($field.data('is-required') === true || $field.attr('data-is-required') === 'true') {
                    $field.addClass('hvnly-field-required');
                    $field.attr('data-required', 'true');
                }
                
                const fieldType = $field.data('field-type');
                if (fieldType === 'price_label') {
                    const $wrapper = $field.find('.hvnly-price-label-field-wrapper');
                    const hasValidValue = $wrapper.data('has-valid-value') === true;
                    if (hasValidValue) {
                        $field.removeClass('hvnly-field-error');
                    }
                }
            });
        }

        /**
         * Check if a number field has a valid (non-empty) value.
         * Zero is a valid number and must match PHP NumberField::validate().
         */
        isValidNumberValue(value) {
            if (value === null || value === undefined || value === '') {
                return false;
            }
            const num = parseFloat(value);
            return !isNaN(num);
        }

        /**
         * Evaluate whether a required metabox field is empty (group-aware).
         *
         * @param {jQuery} $field Field wrapper.
         * @return {{isEmpty: boolean, input: jQuery}}
         */
        evaluateRequiredField($field) {
            const fieldType = $field.data('field-type');
            let isEmpty = false;
            let $input = $field.find('input:not([type="hidden"]), textarea, select').first();

            if (fieldType === 'number') {
                $input = $field.find('input[type="number"]').first();
                isEmpty = !this.isValidNumberValue($input.val());
            } else if (fieldType === 'text' || fieldType === 'textarea') {
                const value = $input.val();
                isEmpty = !value || value.trim() === '';
            } else if (fieldType === 'select') {
                $input = $field.find('select').first();
                const value = $input.val();
                isEmpty = value === null || value === '' || value === undefined;
            } else if (fieldType === 'price_label') {
                const $wrapper = $field.find('.hvnly-price-label-field-wrapper');
                const isCustomMode = $wrapper.find('.hvnly-price-label-mode-toggle').is(':checked');
                if (isCustomMode) {
                    $input = $wrapper.find('.hvnly-price-label-select');
                    isEmpty = !$input.val();
                } else {
                    $input = $wrapper.find('.hvnly-price-label-price-input');
                    const priceValue = $input.val();
                    isEmpty = !priceValue || priceValue.trim() === '';
                }
            } else if (fieldType === 'property_docs') {
                let hasValidDocument = false;
                $field.find('.hvnly-document-repeater-item').each((itemIndex, item) => {
                    const $item = $(item);
                    const label = $item.find('.hvnly-document-label-input').val();
                    const url = $item.find('.hvnly-document-url-input').val();
                    if (label && label.trim() !== '' && url && url.trim() !== '') {
                        hasValidDocument = true;
                    }
                });
                isEmpty = !hasValidDocument;
            } else if (fieldType === 'faq') {
                let hasValidFaq = false;
                $field.find('.hvnly-faq-question-input').each((itemIndex, input) => {
                    if (($(input).val() || '').trim() !== '') {
                        hasValidFaq = true;
                    }
                });
                isEmpty = !hasValidFaq;
                $input = $field.find('.hvnly-faq-question-input').first();
            } else if (fieldType === 'repeater') {
                let hasValidRow = false;
                $field.find('.hvnly-repeater-title-input').each((itemIndex, input) => {
                    if (($(input).val() || '').trim() !== '') {
                        hasValidRow = true;
                    }
                });
                isEmpty = !hasValidRow;
                $input = $field.find('.hvnly-repeater-title-input').first();
            } else if (fieldType === 'video') {
                $input = $field.find('input[name$="_url"], input[type="url"]').first();
                const urlVal = $input.val();
                isEmpty = !urlVal || urlVal.trim() === '';
            } else if (fieldType === 'gallery') {
                const hiddenVal = ($field.find('input.hvnly-gallery-hidden').val() || '').trim();
                const imageCount = $field.find('li.hvnly-gallery-item').length;
                isEmpty = !hiddenVal && imageCount === 0;
                $input = $field.find('input.hvnly-gallery-hidden').first();
            } else if (fieldType === 'map') {
                const $mapRoot = $field.find('[data-address-field-name]').first();
                const addressName = $mapRoot.data('address-field-name');
                if (addressName) {
                    $input = $field.find('input[name="' + addressName + '"]');
                } else {
                    $input = $field.find('.hvnly-map-address-field input').first();
                }
                const addressVal = $input.val();
                isEmpty = !addressVal || addressVal.trim() === '';
            } else if (fieldType === 'agents') {
                const agentCount = $field.find('.hvnly-agents-section-field input[type="hidden"], input[name$="_agents[]"]').length;
                isEmpty = agentCount === 0;
                $input = $field.find('.hvnly-agents-section-field input[type="hidden"], input[name$="_agents[]"]').first();
            } else if (fieldType === 'file') {
                $input = $field.find('input[type="text"], input[type="url"]').not('[type="hidden"]').first();
                const fileVal = $input.val();
                isEmpty = !fileVal || fileVal.trim() === '';
            } else if ($input.length) {
                let value = $input.val();
                const inputType = $input.attr('type');

                if (inputType === 'checkbox') {
                    value = $input.is(':checked') ? 'on' : '';
                } else if (inputType === 'radio') {
                    value = $field.find('input[type="radio"]:checked').val() || '';
                }

                isEmpty = !value || (typeof value === 'string' && value.trim() === '');
            }

            return { isEmpty, input: $input };
        }

        /**
         * The agents picker select is intentionally blank after choosing an agent.
         * Validation uses hidden assignment inputs — never style the picker as empty/invalid.
         *
         * @param {jQuery} $field Agents field wrapper.
         * @return {void}
         */
        clearAgentsPickerValidationState($field) {
            if (!$field || !$field.length || $field.data('field-type') !== 'agents') {
                return;
            }

            $field.find('.hvnly-agents-section-field__select').css('border-color', '');

            const isRequired = $field.hasClass('hvnly-field-required')
                || $field.attr('data-is-required') === 'true'
                || $field.attr('data-required') === 'true';
            const { isEmpty } = this.evaluateRequiredField($field);

            if (!isRequired || !isEmpty) {
                $field.removeClass('hvnly-field-error');
            }
        }

        clearAgentsPickerValidationStates() {
            $('.hvnly__dyamic_metabox_tab__field[data-field-type="agents"]').each((index, field) => {
                this.clearAgentsPickerValidationState($(field));
            });
        }

        getEmptyRequiredFields() {
            const emptyFields = [];
            
            $('.hvnly__dyamic_metabox_tab__field.hvnly-field-required').each((index, field) => {
                const $field = $(field);
                const fieldType = $field.data('field-type');
                let fieldLabel = $field.find('label').first().text().replace('*', '').trim();
                
                if (!fieldLabel) {
                    fieldLabel = $field.find('.hvnly-document-field-label label').text().replace('*', '').trim();
                }

                const { isEmpty, input } = this.evaluateRequiredField($field);
                
                if (isEmpty) {
                    emptyFields.push({
                        field: $field,
                        label: fieldLabel || fieldType,
                        type: fieldType,
                        input: input
                    });
                    $field.addClass('hvnly-field-error');
                    if (fieldType === 'agents') {
                        this.clearAgentsPickerValidationState($field);
                    } else if (input && input.length) {
                        input.css('border-color', '#dc3232');
                    }
                } else {
                    $field.removeClass('hvnly-field-error');
                    if (fieldType === 'agents') {
                        this.clearAgentsPickerValidationState($field);
                    } else if (input && input.length) {
                        input.css('border-color', '');
                    }
                }
            });
            
            return emptyFields;
        }

        updateTabRequiredIndicators() {
            $('.hvnly__dyamic_metabox_tab__nav li').removeClass('hvnly-tab-has-error');
            
            $('.hvnly__dyamic_metabox_tab__tab-content').each((index, tabContent) => {
                const $tabContent = $(tabContent);
                let tabClass = $tabContent.attr('class');
                let tabIdMatch = tabClass.match(/hvnly-tab-([^\s]+)/);
                
                if (!tabIdMatch) return;
                
                let hasMissingRequired = false;
                
                $tabContent.find('.hvnly__dyamic_metabox_tab__field').each((fieldIndex, field) => {
                    const $field = $(field);
                    const isRequired = $field.hasClass('hvnly-field-required') || $field.find('.required').length > 0 || $field.find('[required]').length > 0;
                    
                    if (isRequired) {
                        const fieldType = $field.data('field-type');
                        const { isEmpty } = this.evaluateRequiredField($field);
                        if (isEmpty) {
                            hasMissingRequired = true;
                            $field.addClass('hvnly-field-error');
                            if (fieldType === 'agents') {
                                this.clearAgentsPickerValidationState($field);
                            }
                        } else {
                            $field.removeClass('hvnly-field-error');
                            if (fieldType === 'agents') {
                                this.clearAgentsPickerValidationState($field);
                            }
                        }
                    } else if ($field.data('field-type') === 'agents') {
                        this.clearAgentsPickerValidationState($field);
                    }
                });
                
                if (hasMissingRequired) {
                    const tabSelector = '.' + tabIdMatch[0];
                    const $tabLink = $(`.hvnly__dyamic_metabox_tab__nav a[data-target="${tabSelector}"]`);
                    $tabLink.closest('li').addClass('hvnly-tab-has-error');
                }
            });
        }

        bindEvents() {
            $('.hvnly__dyamic_metabox_tab__nav').on('click', 'a', (e) => {
                e.preventDefault();
                const $link = $(e.currentTarget);
                const index = $('.hvnly__dyamic_metabox_tab__nav a').index($link);
                this.activateTab(index);
            });
            
            $('#post').on('submit', (e) => {
                const isValid = this.validateRequiredFields();
                if (!isValid) {
                    e.preventDefault();
                    return false;
                }
            });
            
            // Enhanced input handler to remove red border when user types valid value
            $(document).on('input change', '.hvnly__dyamic_metabox_tab__field input, .hvnly__dyamic_metabox_tab__field textarea, .hvnly__dyamic_metabox_tab__field select', function() {
                const $input = $(this);
                const $field = $input.closest('.hvnly__dyamic_metabox_tab__field');
                const fieldType = $field.data('field-type');

                // Agents picker resets to blank after each selection; hidden inputs hold the value.
                if ($input.hasClass('hvnly-agents-section-field__select')) {
                    setTimeout(() => {
                        if (window.HavenlyticsAdminMetabox) {
                            window.HavenlyticsAdminMetabox.clearAgentsPickerValidationState($field);
                            window.HavenlyticsAdminMetabox.updateTabRequiredIndicators();
                        }
                    }, 0);
                    return;
                }

                const isRequired = $field.hasClass('hvnly-field-required')
                    || $field.attr('data-is-required') === 'true'
                    || $field.attr('data-required') === 'true'
                    || $field.find('.required').length > 0
                    || $field.find('[required]').length > 0;

                if (!isRequired) {
                    $input.css('border-color', '');
                    return;
                }
                
                // For number fields, check if value is valid (not empty and not zero)
                if (fieldType === 'number') {
                    const value = $input.val();
                    const isValid = window.HavenlyticsAdminMetabox && 
                                   typeof window.HavenlyticsAdminMetabox.isValidNumberValue === 'function' ?
                                   window.HavenlyticsAdminMetabox.isValidNumberValue(value) :
                                   (value !== null && value !== undefined && value !== '' && value !== '0' && value !== 0);
                    
                    if (isValid) {
                        $field.removeClass('hvnly-field-error');
                        $input.css('border-color', '');
                    } else {
                        $field.addClass('hvnly-field-error');
                        $input.css('border-color', '#dc3232');
                    }
                } else {
                    // For other fields, check if value is empty
                    const value = $input.val();
                    const isEmpty = !value || (typeof value === 'string' && value.trim() === '');
                    
                    if (!isEmpty) {
                        $field.removeClass('hvnly-field-error');
                        $input.css('border-color', '');
                    } else {
                        $field.addClass('hvnly-field-error');
                        $input.css('border-color', '#dc3232');
                    }
                }
                
                // Trigger tab indicators update
                if (window.HavenlyticsAdminMetabox && window.HavenlyticsAdminMetabox.updateTabRequiredIndicators) {
                    window.HavenlyticsAdminMetabox.updateTabRequiredIndicators();
                }
            });
            
            $(document).on('change', '.hvnly-price-label-select', () => {
                this.updateTabRequiredIndicators();
                $(this).css('border-color', '');
                $(this).closest('.hvnly__dyamic_metabox_tab__field').removeClass('hvnly-field-error');
            });
            
            $(document).on('input', '.hvnly-price-label-price-input', () => {
                this.updateTabRequiredIndicators();
                $(this).css('border-color', '');
                $(this).closest('.hvnly__dyamic_metabox_tab__field').removeClass('hvnly-field-error');
            });
            
            $(document).on('change', '.hvnly-price-label-mode-toggle', () => {
                setTimeout(() => {
                    this.updateTabRequiredIndicators();
                }, 50);
            });
            
            $(document).on('input change', '.hvnly-document-label-input, .hvnly-document-url-input', () => {
                this.updateTabRequiredIndicators();
                const $field = $(this).closest('.hvnly__dyamic_metabox_tab__field');
                $field.removeClass('hvnly-field-error');
            });
            
            $(document).ajaxComplete(() => {
                this.markRequiredFields();
                this.updateTabRequiredIndicators();
                this.clearAgentsPickerValidationStates();
            });
        }

        validateRequiredFields() {
            const emptyFields = this.getEmptyRequiredFields();
            
            if (emptyFields.length === 0) {
                return true;
            }
            
            // Show validation modal ONLY for validation errors
            this.showValidationErrorModal(emptyFields);
            
            const firstEmpty = emptyFields[0];
            if (firstEmpty && firstEmpty.input && firstEmpty.input.length) {
                firstEmpty.input.trigger('focus');
                $('html, body').animate({
                    scrollTop: firstEmpty.input.offset().top - 100
                }, 300);
            }
            
            return false;
        }
        
        reinitialize() {
            this.initialized = false;
            this.init();
        }
    }

    // Make isValidNumberValue available globally for event handler
    HavenlyticsAdminMetabox.prototype.isValidNumberValue = function(value) {
        if (value === null || value === undefined || value === '') {
            return false;
        }
        const num = parseFloat(value);
        return !isNaN(num);
    };

    $(() => {
        window.HavenlyticsAdminMetabox = new HavenlyticsAdminMetabox();
    });

})(jQuery);

