/**
 * Elementor preview/edit UI isolation — static rendering only, no auto-interactions.
 *
 * @package Havenlytics
 * @since   3.1.0
 */
(function (window, $) {
    'use strict';

    const HvnlyElementorPreview = {
        isElementorUrlContext() {
            try {
                const params = new URLSearchParams(window.location.search);
                if (params.has('elementor-preview')) {
                    return true;
                }
                if (params.get('action') === 'elementor') {
                    return true;
                }
            } catch (error) {
                return false;
            }

            return false;
        },

        isEditMode() {
            if (window.hvnlyElementor && window.hvnlyElementor.isEditMode) {
                return true;
            }

            try {
                return !!(
                    window.elementorFrontend &&
                    typeof window.elementorFrontend.isEditMode === 'function' &&
                    window.elementorFrontend.isEditMode()
                );
            } catch (error) {
                return false;
            }
        },

        isPreviewContext() {
            if (this.isElementorUrlContext() || this.isEditMode()) {
                return true;
            }

            return (
                document.body.classList.contains('elementor-editor-active') ||
                document.body.classList.contains('elementor-preview') ||
                !!document.querySelector('.elementor-editor-active')
            );
        },

        shouldDisableInteractiveUi() {
            return this.isPreviewContext();
        },

        initCarousels(root) {
            if (
                !window.havenlyticsFrontend ||
                typeof window.havenlyticsFrontend.initPropertyGalleryCarousels !== 'function'
            ) {
                return;
            }

            window.havenlyticsFrontend.initPropertyGalleryCarousels({
                root: root || document,
                force: true,
                elementorMode: true,
            });
        },

        forceCleanUiState() {
            $('.hvnly-property-grid-view').each(function () {
                const $grid = $(this);
                $grid.removeClass('hvnly-loading hvnly-original-content');

                if ($grid.hasClass('hvnly-list-view') || $grid.hasClass('list-view')) {
                    $grid.css({ display: 'flex', flexDirection: 'column', gap: '20px' });
                } else if ($grid.hasClass('map-view')) {
                    $grid.css('display', 'none');
                } else {
                    $grid.css('display', 'grid');
                }
            });

            $(
                '#hvnly-filter-sidebar, .hvnly-property--view--controls, ' +
                '#hvnly-property-pagination, [id^="hvnly-property-pagination"], ' +
                '#hvnly-load-more-container, #hvnly-load-more, .hvnly-load-more-wrapper, ' +
                '.hvnly-property-load-more-container, [id^="hvnly-load-more-"], .hvnly-property-search-form'
            ).each(function () {
                $(this).removeClass('hvnly-original-content').show();
            });

            $('.hvnly-property-load-more-btn')
                .removeClass('loading')
                .prop('disabled', false);

            if (window.havenlyticsAJAX) {
                window.havenlyticsAJAX.isLoading = false;
            }

            this.initCarousels(document);
        },

        boot() {
            if (!this.shouldDisableInteractiveUi()) {
                return;
            }

            const self = this;

            this.forceCleanUiState();

            $(window).on('elementor/frontend/init', () => {
                self.forceCleanUiState();
            });

            $(document).on('hvnly-elementor-widget-ready', function (_event, $widget) {
                self.forceCleanUiState();
                if ($widget && $widget.length) {
                    self.initCarousels($widget[0]);
                }
            });
        },
    };

    window.HvnlyElementorPreview = HvnlyElementorPreview;

    $(document).ready(function () {
        HvnlyElementorPreview.boot();
    });
})(window, jQuery);
