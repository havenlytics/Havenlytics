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

            // Legacy / Gutenberg: never assume a single hard-coded id.
            const $byPrefix = $('[id^="hvnly-property-grid"]');
            if ($byPrefix.length === 1) {
                return $byPrefix.first();
            }

            return $('.hvnly-property-grid-view').first();
        },

        /**
         * Every property listing grid on the page (Archive / Search / Featured / Elementor).
         * Scoped so agent/agency archive grids are not restyled as property columns.
         *
         * @returns {jQuery}
         */
        resolveAllPropertyGrids() {
            return $(
                '.hvnly-property--grid--listings > .hvnly-property-grid-view, ' +
                '.hvnly-all-properties-widget .hvnly-property-grid-view, ' +
                '[id^="hvnly-property-grid"].hvnly-property-grid-view'
            );
        },

        /**
         * Grid inside a listings section (or nearest ancestor of a context node).
         *
         * @param {HTMLElement|jQuery|null|undefined} context
         * @returns {HTMLElement|null}
         */
        resolvePropertyGridNear(context) {
            if (!context) {
                return null;
            }

            const node = context.jquery ? context[0] : context;
            if (!node || !node.closest) {
                return null;
            }

            const listings = node.closest('.hvnly-property--grid--listings');
            if (listings) {
                const grid = listings.querySelector('.hvnly-property-grid-view');
                if (grid) {
                    return grid;
                }
            }

            const widget = node.closest('.hvnly-all-properties-widget');
            if (widget) {
                const grid = widget.querySelector('.hvnly-property-grid-view');
                if (grid) {
                    return grid;
                }
            }

            return null;
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
            if (contextBtn) {
                const $widget = $(contextBtn).closest('.hvnly-all-properties-widget');
                if ($widget.length) {
                    const instanceId = $widget.attr('data-widget-id') || null;
                    return {
                        widget: $widget,
                        propertyGrid: this.resolvePropertyGridElement(instanceId),
                        mapPlaceholder: this.resolveMapPlaceholder(instanceId),
                    };
                }

                const nearGrid = this.resolvePropertyGridNear(contextBtn);
                if (nearGrid) {
                    const listings = contextBtn.closest('.hvnly-property--grid--listings');
                    const mapPlaceholder = (listings && listings.querySelector('.hvnly-map-placeholder'))
                        || document.querySelector('.hvnly-map-placeholder')
                        || document.getElementById('hvnly-map-placeholder');
                    return {
                        widget: $(),
                        propertyGrid: nearGrid,
                        mapPlaceholder: mapPlaceholder || null,
                    };
                }
            }

            const $widget = $('.hvnly-all-properties-widget').first();
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
                propertyGrid: document.querySelector('.hvnly-property-grid-view'),
                mapPlaceholder: document.querySelector('.hvnly-map-placeholder') || document.getElementById('hvnly-map-placeholder'),
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
