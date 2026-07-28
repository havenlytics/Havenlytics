/**
 * Havenlytics Repeater Field — add, remove, sort rows + document icon picker reuse.
 *
 * @package Havenlytics
 */

(function ($) {
    'use strict';

    const i18n = (window.HvnlyRepeaterField && window.HvnlyRepeaterField.i18n) || {};
    const t = (key, fallback) => (i18n[key] && String(i18n[key])) || fallback;

    class HavenlyticsRepeaterField {
        constructor() {
            $(document).on('click', '.hvnly-repeater-add-item', this.addItem.bind(this));
            $(document).on('click', '.hvnly-repeater-remove-item', this.removeItem.bind(this));
            $(document).on('input', '.hvnly-repeater-title-input', this.updateTitles.bind(this));
            $(document).on('input', '.hvnly-repeater-field-container .hvnly-document-icon-input', this.syncIconPreview.bind(this));
            this.initSortable();
            this.initIconPreviews();
            this.initHeaderIcons();
        }

        initHeaderIcons() {
            $('.hvnly-repeater-field-container .hvnly-repeater-item').each((index, item) => {
                this.updateHeaderIcon($(item));
            });
        }

        getDocumentFieldInstance() {
            return window.HvnlyDocumentFieldInstance || null;
        }

        initIconPreviews() {
            const documentField = this.getDocumentFieldInstance();
            if (documentField && typeof documentField.initIconPreviews === 'function') {
                documentField.initIconPreviews();
                return;
            }

            $('.hvnly-repeater-field-container .hvnly-document-icon-input').each((index, input) => {
                this.renderIconPreview($(input));
            });
        }

        renderIconPreview($input) {
            const $preview = $input.siblings('.hvnly-document-icon-preview');
            const iconValue = String($input.val() || '').trim().replace(/^fa-/, '');

            $preview.empty();
            if (iconValue) {
                $preview.html(`<i class="fas fa-${iconValue}"></i>`);
            }
        }

        syncIconPreview(event) {
            this.renderIconPreview($(event.currentTarget));
            this.updateHeaderIcon($(event.currentTarget).closest('.hvnly-repeater-item'));
        }

        updateHeaderIcon($item) {
            const iconValue = String($item.find('.hvnly-document-icon-input').val() || '').trim().replace(/^fa-/, '');
            const $headerTitle = $item.find('.hvnly-repeater-item-title');
            const titleText = $.trim($item.find('.hvnly-repeater-title-input').val()) || t('newRow', 'New Row');

            $headerTitle.empty();
            if (iconValue) {
                $headerTitle.append(`<i class="fas fa-${iconValue}" aria-hidden="true"></i> `);
            }
            $headerTitle.append(document.createTextNode(titleText));
        }

        initSortable() {
            $('.hvnly-repeater-items').each(function () {
                const $container = $(this);
                if ($container.data('sortable-init')) {
                    return;
                }
                $container.sortable({
                    handle: '.hvnly-repeater-drag-handle',
                    axis: 'y',
                    opacity: 0.8,
                    placeholder: 'hvnly-repeater-item ui-sortable-placeholder',
                });
                $container.data('sortable-init', true);
            });
        }

        clearRow($item) {
            $item.find('.hvnly-repeater-title-input').val('');
            $item.find('.hvnly-repeater-value-input').val('');
            $item.find('.hvnly-document-icon-input').val('');
            $item.find('.hvnly-document-icon-preview').empty();
            this.updateHeaderIcon($item);
        }

        addItem(event) {
            event.preventDefault();
            const $container = $(event.currentTarget).closest('.hvnly-repeater-field-container');
            const $items = $container.find('.hvnly-repeater-items');
            const $template = $items.find('.hvnly-repeater-item:first').clone();
            const fieldName = $container.data('field-name');

            $template.find('.hvnly-repeater-title-input').attr('name', fieldName + '_titles[]').val('');
            $template.find('.hvnly-repeater-value-input').attr('name', fieldName + '_values[]').val('');
            $template.find('.hvnly-document-icon-input').attr('name', fieldName + '_icons[]').val('');
            $template.find('.hvnly-document-icon-preview').empty();
            this.updateHeaderIcon($template);

            $items.append($template);
            this.initSortable();
            this.initIconPreviews();
        }

        removeItem(event) {
            event.preventDefault();
            const $item = $(event.currentTarget).closest('.hvnly-repeater-item');
            const $items = $item.parent();
            if ($items.find('.hvnly-repeater-item').length <= 1) {
                this.clearRow($item);
                return;
            }
            $item.remove();
        }

        updateTitles(event) {
            const $input = $(event.currentTarget);
            this.updateHeaderIcon($input.closest('.hvnly-repeater-item'));
        }
    }

    $(function () {
        window.HvnlyRepeaterFieldInstance = new HavenlyticsRepeaterField();
    });

    $(document).on('click', '.hvnly__dyamic_metabox_tab__nav a', function () {
        setTimeout(function () {
            if (window.HvnlyRepeaterFieldInstance) {
                window.HvnlyRepeaterFieldInstance.initSortable();
                window.HvnlyRepeaterFieldInstance.initIconPreviews();
                window.HvnlyRepeaterFieldInstance.initHeaderIcons();
            }
        }, 300);
    });
})(jQuery);
