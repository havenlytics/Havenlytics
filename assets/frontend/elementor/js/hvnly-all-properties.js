/**
 * HVN: All Properties Elementor Widget
 * Uses the same archive search JS stack in editor preview and on the live frontend.
 *
 * @package Havenlytics
 * @since   3.1.0
 */

(function ($) {
    'use strict';

    window.__hvnlyElementorBooted = window.__hvnlyElementorBooted || false;

    const HvnlyElementorWidgets = {
        patchedSetView: false,
        eventsBound: false,
        editorHooksBound: false,

        config() {
            return window.hvnlyElementor || {};
        },

        isElementorEditMode() {
            if (this.config().isEditMode) {
                return true;
            }

            return !!(
                window.elementorFrontend &&
                typeof elementorFrontend.isEditMode === 'function' &&
                elementorFrontend.isEditMode()
            );
        },

        getInitDelay() {
            const delay = parseInt(this.config().initDelay, 10);
            return !isNaN(delay) && delay >= 0 ? delay : 0;
        },

        hasElementorHooks() {
            return !!(
                window.elementorFrontend &&
                elementorFrontend.hooks &&
                typeof elementorFrontend.hooks.addAction === 'function'
            );
        },

        bindElementorHooks(onReady) {
            if (this.editorHooksBound || typeof onReady !== 'function') {
                return true;
            }

            if (!this.hasElementorHooks()) {
                return false;
            }

            this.editorHooksBound = true;

            elementorFrontend.hooks.addAction(
                'frontend/element_ready/hvnly_all_properties.default',
                function ($scope) {
                    onReady($scope);
                }
            );

            return true;
        },

        waitForElementorHooks(onReady) {
            const self = this;

            if (this.bindElementorHooks(onReady)) {
                return;
            }

            $(window).on('elementor/frontend/init', function () {
                self.bindElementorHooks(onReady);
            });

            let attempts = 0;
            const retry = function () {
                if (self.bindElementorHooks(onReady) || attempts >= 30) {
                    return;
                }

                attempts += 1;
                window.setTimeout(retry, 100);
            };

            retry();
        },

        boot() {
            if (window.__hvnlyElementorBooted) {
                return;
            }

            window.__hvnlyElementorBooted = true;
            this.bootLiveFrontend();
        },

        bootLiveFrontend() {
            const self = this;

            this.bindEvents();
            this.patchSetView();

            const initAll = function () {
                $('.hvnly-all-properties-widget').each(function () {
                    self.initWidget($(this));
                });
            };

            const runInit = function ($scope) {
                const $widget = self.resolveWidget($scope);
                if ($widget.length) {
                    self.initWidget($widget);
                }
            };

            if (this.isElementorEditMode()) {
                this.waitForElementorHooks(runInit);
                $(document).ready(function () {
                    window.setTimeout(initAll, self.getInitDelay());
                });
            } else {
                $(document).ready(initAll);
                this.waitForElementorHooks(runInit);
            }
        },

        resolveWidget($scope) {
            if ($scope && $scope.hasClass && $scope.hasClass('hvnly-all-properties-widget')) {
                return $scope;
            }

            return $scope.find('.hvnly-all-properties-widget').first();
        },

        initWidget($widget) {
            if (!$widget.length) {
                return;
            }

            this.syncArchiveUi($widget);
            this.applyGridColumns($widget);
            this.syncViewState($widget);
            this.initCarousels($widget);
        },

        initCarousels($widget) {
            if (
                !window.havenlyticsFrontend ||
                typeof window.havenlyticsFrontend.initPropertyGalleryCarousels !== 'function'
            ) {
                return;
            }

            const elementorMode = this.isElementorEditMode();

            window.havenlyticsFrontend.initPropertyGalleryCarousels({
                root: $widget[0],
                force: elementorMode,
                elementorMode: elementorMode,
            });
        },

        /**
         * Elementor injects widget markup after archive JS bootstraps.
         * Re-run filter collapse and layout inside the widget scope.
         */
        syncArchiveUi($widget) {
            const $groups = $widget.find('.hvnly-property-filter-group');

            if ($groups.length) {
                $groups.each(function () {
                    const $group = $(this);
                    const $content = $group.find('.hvnly-property-filter-group-content');
                    const $header = $group.find('.hvnly-property-filter-group-header');

                    $group.addClass('collapsed');
                    $content.hide();
                    $header.find('.hvnly-property-filter-group-toggle i')
                        .removeClass('fa-chevron-up')
                        .addClass('fa-chevron-down');
                });
            } else if (
                window.havenlyticsFrontend &&
                typeof window.havenlyticsFrontend.initializeFilterGroups === 'function'
            ) {
                window.havenlyticsFrontend.initializeFilterGroups();
            }

            if (window.HvnlyElementorPreview && window.HvnlyElementorPreview.shouldDisableInteractiveUi()) {
                window.HvnlyElementorPreview.forceCleanUiState();
                this.initCarousels($widget);
                return;
            }

            this.applyGridColumns($widget);
            this.initCarousels($widget);
            $(document).trigger('hvnly-elementor-widget-ready', [$widget]);
        },

        bindEvents() {
            if (this.eventsBound) {
                return;
            }

            this.eventsBound = true;

            $(document).on(
                'click.hvnlyElementor',
                '.hvnly-all-properties-widget .hvnly-property-view-btn',
                function () {
                    const $widget = $(this).closest('.hvnly-all-properties-widget');
                    const viewType = $(this).data('view');

                    $widget.attr('data-view-type', viewType);

                    if (viewType === 'map') {
                        HvnlyElementorWidgets.initMapForWidget($widget);
                    } else {
                        HvnlyElementorWidgets.hideMapPlaceholder($widget);
                    }

                    window.setTimeout(function () {
                        HvnlyElementorWidgets.applyGridColumns($widget);
                    }, 0);
                }
            );

            $(document).on('hvnly-properties-updated', function () {
                $('.hvnly-all-properties-widget').each(function () {
                    const $widget = $(this);
                    HvnlyElementorWidgets.applyGridColumns($widget);
                    HvnlyElementorWidgets.initCarousels($widget);
                });
            });
        },

        patchSetView() {
            if (this.patchedSetView || !window.havenlyticsFrontend || typeof window.havenlyticsFrontend.setView !== 'function') {
                return;
            }

            const original = window.havenlyticsFrontend.setView.bind(window.havenlyticsFrontend);

            window.havenlyticsFrontend.setView = function (viewType, contextBtn) {
                original(viewType, contextBtn);

                const $widget = contextBtn
                    ? $(contextBtn).closest('.hvnly-all-properties-widget')
                    : $('.hvnly-all-properties-widget').first();

                if (!$widget.length) {
                    return;
                }

                $widget.attr('data-view-type', viewType);
                HvnlyElementorWidgets.applyGridColumns($widget);

                if (viewType === 'map') {
                    HvnlyElementorWidgets.initMapForWidget($widget);
                } else {
                    HvnlyElementorWidgets.hideMapPlaceholder($widget);
                }
            };

            this.patchedSetView = true;
        },

        getColumns($widget) {
            const fromWidget = parseInt($widget.attr('data-columns'), 10);
            if (!isNaN(fromWidget) && fromWidget > 0) {
                return fromWidget;
            }

            const fromGrid = parseInt($widget.find('.hvnly-property-grid-view').first().attr('data-columns'), 10);
            if (!isNaN(fromGrid) && fromGrid > 0) {
                return fromGrid;
            }

            return 2;
        },

        applyGridColumns($widget) {
            const $grid = $widget.find('.hvnly-property-grid-view').first();
            if (!$grid.length) {
                return;
            }

            const activeView =
                $widget.find('.hvnly-property-view-btn.active').data('view') ||
                $widget.data('view-type') ||
                'grid';

            if (activeView !== 'grid') {
                return;
            }

            const cols = this.getColumns($widget);

            $grid.attr('data-columns', cols);
            $grid.css({
                display: 'grid',
                gap: '20px',
                width: '100%',
                minWidth: '0',
                gridTemplateColumns: 'repeat(' + cols + ', minmax(0, 1fr))',
            });
        },

        syncViewState($widget) {
            const viewType = String($widget.data('view-type') || 'grid');

            $widget.find('.hvnly-property-view-btn').removeClass('active');
            const $activeBtn = $widget.find('.hvnly-property-view-btn[data-view="' + viewType + '"]');
            if ($activeBtn.length) {
                $activeBtn.addClass('active');
            }

            if (viewType === 'map') {
                this.initMapForWidget($widget);
                return;
            }

            this.hideMapPlaceholder($widget);
            this.applyGridColumns($widget);
        },

        hideMapPlaceholder($widget) {
            const widgetId = $widget.data('widget-id');
            const mapPlaceholder =
                window.HvnlyDom && typeof window.HvnlyDom.resolveMapPlaceholder === 'function'
                    ? window.HvnlyDom.resolveMapPlaceholder(widgetId)
                    : null;

            if (mapPlaceholder) {
                mapPlaceholder.classList.remove('is-visible');
                mapPlaceholder.style.display = 'none';
            }

            $widget.find('.hvnly-map-placeholder').removeClass('is-visible').hide();
        },

        initMapForWidget($widget) {
            const widgetId = $widget.data('widget-id');

            $widget.attr('data-view-type', 'map');

            const mapPlaceholder =
                window.HvnlyDom && typeof window.HvnlyDom.resolveMapPlaceholder === 'function'
                    ? window.HvnlyDom.resolveMapPlaceholder(widgetId)
                    : null;

            const $grid = $widget.find('.hvnly-property-grid-view').first();

            if ($grid.length) {
                $grid.css('display', 'none');
            }

            if (mapPlaceholder) {
                mapPlaceholder.classList.add('is-visible');
                mapPlaceholder.style.display = 'block';
                mapPlaceholder.style.minHeight = '500px';
            }

            const mapInstance = window.HavenlyticsPropertyMap || window.HvnlyPropertyMap;
            if (!mapInstance) {
                return;
            }

            mapInstance.currentInstanceId = widgetId;

            window.setTimeout(function () {
                if (typeof mapInstance.loadPropertyMap === 'function') {
                    mapInstance.loadPropertyMap();
                }
            }, 150);
        },
    };

    HvnlyElementorWidgets.boot();
})(jQuery);
