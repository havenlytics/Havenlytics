/**
 * Havenlytics unified DOM resolver — single source for grid/map/pagination targeting.
 *
 * @package Havenlytics
 * @since   3.1.0
 */
(function (window, $) {
    'use strict';

    const HvnlyDom = {
        /**
         * @param {string|null|undefined} instanceId
         * @returns {jQuery}
         */
        resolveWidget(instanceId) {
            if (instanceId) {
                const $widget = $(`.hvnly-all-properties-widget[data-widget-id="${instanceId}"]`);
                if ($widget.length) {
                    return $widget.first();
                }
            }

            return $();
        },

        /**
         * @param {string|null|undefined} instanceId
         * @returns {jQuery}
         */
        resolvePropertyGrid(instanceId) {
            const $widget = this.resolveWidget(instanceId);

            if ($widget.length) {
                const gridId = $widget.attr('data-grid-id');
                if (gridId) {
                    const $byId = $('#' + gridId);
                    if ($byId.length) {
                        return $byId;
                    }
                }

                const $scoped = $widget.find('.hvnly-property-grid-view').first();
                if ($scoped.length) {
                    return $scoped;
                }
            }

            const $legacy = $('#hvnly-property-grid');
            if ($legacy.length) {
                return $legacy;
            }

            return $('.hvnly-property-grid-view').first();
        },

        /**
         * @param {string|null|undefined} instanceId
         * @returns {HTMLElement|null}
         */
        resolvePropertyGridElement(instanceId) {
            const $grid = this.resolvePropertyGrid(instanceId);
            return $grid.length ? $grid[0] : null;
        },

        /**
         * @param {string|null|undefined} instanceId
         * @returns {HTMLElement|null}
         */
        resolveMapPlaceholder(instanceId) {
            const $widget = this.resolveWidget(instanceId);

            if ($widget.length) {
                const mapId = $widget.attr('data-map-id');
                if (mapId) {
                    const element = document.getElementById(mapId);
                    if (element) {
                        return element;
                    }
                }

                const $scoped = $widget.find('.hvnly-map-placeholder').first();
                if ($scoped.length) {
                    return $scoped[0];
                }
            }

            return document.getElementById('hvnly-map-placeholder');
        },

        /**
         * @param {string|null|undefined} instanceId
         * @returns {jQuery}
         */
        resolveLoadMoreContainer(instanceId) {
            if (instanceId) {
                const $byId = $(`#hvnly-load-more-${instanceId}`);
                if ($byId.length) {
                    return $byId;
                }

                const $byData = $(`.hvnly-property-load-more-container[data-instance-id="${instanceId}"]`);
                if ($byData.length) {
                    return $byData.first();
                }
            }

            const $all = this.resolveAllLoadMoreContainers();
            if ($all.length) {
                return $all.first();
            }

            return $();
        },

        /**
         * All load-more wrappers (archive + Elementor instances).
         *
         * @returns {jQuery}
         */
        resolveAllLoadMoreContainers() {
            const $containers = $('#hvnly-load-more-container, #hvnly-load-more, .hvnly-load-more-wrapper, .hvnly-property-load-more-container');
            return $containers.length ? $containers : $();
        },

        /**
         * @param {string|null|undefined} instanceId
         * @returns {jQuery}
         */
        resolvePaginationContainer(instanceId) {
            if (instanceId) {
                const $scoped = $(`#hvnly-property-pagination-${instanceId}`);
                if ($scoped.length) {
                    return $scoped;
                }
            }

            const $all = this.resolveAllPaginationContainers();
            if ($all.length) {
                return $all.first();
            }

            return $();
        },

        /**
         * All pagination wrappers (archive + Elementor instances).
         *
         * @returns {jQuery}
         */
        resolveAllPaginationContainers() {
            const $containers = $('[id^="hvnly-property-pagination"]');
            return $containers.length ? $containers : $();
        },

        /**
         * Results count header in view controls (archive + Elementor widget).
         *
         * @param {string|null|undefined} instanceId
         * @returns {jQuery}
         */
        resolveResultsCountHeader(instanceId) {
            const $widget = this.resolveWidget(instanceId);

            if ($widget.length) {
                const $header = $widget.find('.hvnly-property-results-header-left').first();
                if ($header.length) {
                    return $header;
                }

                const $count = $widget.find('#hvnly-results-count').first();
                if ($count.length) {
                    return $count.closest('.hvnly-property-results-header-left').length
                        ? $count.closest('.hvnly-property-results-header-left')
                        : $count;
                }
            }

            const $legacyHeader = $('.hvnly-property--view--controls .hvnly-property-results-header-left').first();
            if ($legacyHeader.length) {
                return $legacyHeader;
            }

            const $legacyCount = $('#hvnly-results-count').first();
            if ($legacyCount.length) {
                return $legacyCount.closest('.hvnly-property-results-header-left').length
                    ? $legacyCount.closest('.hvnly-property-results-header-left')
                    : $legacyCount;
            }

            return $();
        },

        /**
         * Apply AJAX pagination + results count fragments for the active archive instance.
         *
         * @param {Object} data AJAX response payload.
         */
        syncListingState(data) {
            if (!data || typeof data !== 'object') {
                return;
            }

            const instanceId = data.instance_id || null;

            if (data.current_page && window.havenlyticsAJAX) {
                window.havenlyticsAJAX.currentPage = parseInt(data.current_page, 10) || 1;
            }

            if (data.max_pages && window.havenlyticsAJAX) {
                window.havenlyticsAJAX.maxPages = parseInt(data.max_pages, 10) || 1;
            }

            const $pagination = this.resolvePaginationContainer(instanceId);
            if ($pagination.length && data.pagination_html) {
                $pagination.html(data.pagination_html);
                $pagination.show();
            }

            if (data.results_count_html) {
                const $resultsHeader = this.resolveResultsCountHeader(instanceId);
                if ($resultsHeader.length) {
                    const $parsed = $('<div>').html(data.results_count_html);
                    const $newHeader = $parsed.find('.hvnly-property-results-header-left').first();

                    if ($newHeader.length) {
                        $resultsHeader.replaceWith($newHeader);
                    } else if ($resultsHeader.is('#hvnly-results-count')) {
                        const $newCount = $parsed.find('#hvnly-results-count').first();
                        if ($newCount.length) {
                            $resultsHeader.replaceWith($newCount);
                        } else {
                            $resultsHeader.html($parsed.html());
                        }
                    } else {
                        $resultsHeader.replaceWith(data.results_count_html);
                    }
                }
            }
        },

        /**
         * Resolve view elements within a widget or archive context.
         *
         * @param {HTMLElement|null|undefined} contextBtn
         * @returns {{propertyGrid: HTMLElement|null, mapPlaceholder: HTMLElement|null, widget: jQuery}}
         */
        resolveViewElements(contextBtn) {
            const $widget = contextBtn
                ? $(contextBtn).closest('.hvnly-all-properties-widget')
                : $('.hvnly-all-properties-widget').first();

            if ($widget.length) {
                const instanceId = $widget.attr('data-widget-id') || null;
                return {
                    widget: $widget,
                    propertyGrid: this.resolvePropertyGridElement(instanceId),
                    mapPlaceholder: this.resolveMapPlaceholder(instanceId),
                };
            }

            return {
                widget: $(),
                propertyGrid: document.getElementById('hvnly-property-grid') || document.querySelector('.hvnly-property-grid-view'),
                mapPlaceholder: document.getElementById('hvnly-map-placeholder'),
            };
        },
    };

    window.HvnlyDom = HvnlyDom;

    // Backward-compatible aliases used by existing modules.
    window.hvnlyResolvePropertyGrid = function (instanceId) {
        return HvnlyDom.resolvePropertyGrid(instanceId);
    };

    window.hvnlyResolveMapPlaceholder = function (instanceId) {
        return HvnlyDom.resolveMapPlaceholder(instanceId);
    };

})(window, jQuery);
