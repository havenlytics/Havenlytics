/**
 * Havenlytics FAQ Field — add, remove, sort rows.
 *
 * @package Havenlytics
 */

(function ($) {
    'use strict';

    const i18n = (window.HvnlyFaqField && window.HvnlyFaqField.i18n) || {};
    const t = (key, fallback) => (i18n[key] && String(i18n[key])) || fallback;

    class HavenlyticsFaqField {
        constructor() {
            $(document).on('click', '.hvnly-faq-add-item', this.addItem.bind(this));
            $(document).on('click', '.hvnly-faq-remove-item', this.removeItem.bind(this));
            $(document).on('input', '.hvnly-faq-question-input', this.updateTitles.bind(this));
            this.initSortable();
        }

        initSortable() {
            $('.hvnly-faq-repeater-items').each(function () {
                const $container = $(this);
                if ($container.data('sortable-init')) {
                    return;
                }
                $container.sortable({
                    handle: '.hvnly-faq-drag-handle',
                    axis: 'y',
                    opacity: 0.8,
                    placeholder: 'hvnly-faq-repeater-item ui-sortable-placeholder',
                });
                $container.data('sortable-init', true);
            });
        }

        addItem(event) {
            event.preventDefault();
            const $container = $(event.currentTarget).closest('.hvnly-faq-field-container');
            const $items = $container.find('.hvnly-faq-repeater-items');
            const $template = $items.find('.hvnly-faq-repeater-item:first').clone();
            const fieldName = $container.data('field-name');

            $template.find('.hvnly-faq-question-input').attr('name', fieldName + '_questions[]').val('');
            $template.find('.hvnly-faq-answer-input').attr('name', fieldName + '_answers[]').val('');
            $template.find('.hvnly-faq-item-title').text(t('newFaq', 'New FAQ'));

            $items.append($template);
            this.initSortable();
        }

        removeItem(event) {
            event.preventDefault();
            const $item = $(event.currentTarget).closest('.hvnly-faq-repeater-item');
            const $items = $item.parent();
            if ($items.find('.hvnly-faq-repeater-item').length <= 1) {
                $item.find('input, textarea').val('');
                $item.find('.hvnly-faq-item-title').text(t('newFaq', 'New FAQ'));
                return;
            }
            $item.remove();
        }

        updateTitles(event) {
            const $input = $(event.currentTarget);
            const title = $.trim($input.val()) || t('newFaq', 'New FAQ');
            $input.closest('.hvnly-faq-repeater-item').find('.hvnly-faq-item-title').text(title);
        }
    }

    $(function () {
        new HavenlyticsFaqField();
    });
})(jQuery);
