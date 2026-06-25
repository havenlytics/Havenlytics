jQuery(document).ready(function($) {
    'use strict';

    const AJAX_TIMEOUT = 30000;
    const $actionButtons = $('.hvnly-cache-actions .button[data-default-label]');
    const $refreshStatsBtn = $('#hvnly-refresh-stats');

    // -------------------------------------------------
    // Shared UI containers
    // -------------------------------------------------
    if ($('#hvnly-toast-container').length === 0) {
        $('body').append('<div class="hvnly-toast-container" id="hvnly-toast-container"></div>');
    }

    if ($('#hvnly-modal-overlay').length === 0) {
        $('body').append(`
            <div class="hvnly-modal-overlay" id="hvnly-modal-overlay">
                <div class="hvnly-modal" id="hvnly-modal">
                    <div class="hvnly-modal-header">
                        <h3 class="hvnly-modal-title" id="hvnly-modal-title"></h3>
                        <button type="button" class="hvnly-modal-close" id="hvnly-modal-close" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="hvnly-modal-body" id="hvnly-modal-body"></div>
                    <div class="hvnly-modal-footer" id="hvnly-modal-footer"></div>
                </div>
            </div>
        `);
    }

    let pendingConfirmCallback = null;

    // -------------------------------------------------
    // Button loading helpers
    // -------------------------------------------------
    function getDefaultLabel($btn) {
        return $btn.data('default-label') || $btn.text().trim();
    }

    function setButtonLoading($btn, isLoading) {
        if (!$btn || !$btn.length) {
            return;
        }

        const defaultLabel = getDefaultLabel($btn);

        if (isLoading) {
            if (!$btn.data('original-html')) {
                $btn.data('original-html', $btn.html());
            }
            $btn.prop('disabled', true)
                .addClass('is-loading')
                .html('<span class="spinner is-active" style="float:none;margin:0 6px 0 0;"></span>' + (hvnlyCacheAdmin.clearingText || 'Clearing...'));
            return;
        }

        const originalHtml = $btn.data('original-html');
        $btn.prop('disabled', false)
            .removeClass('is-loading')
            .html(originalHtml || defaultLabel)
            .removeData('original-html');
    }

    function restoreAllButtons() {
        $actionButtons.each(function() {
            setButtonLoading($(this), false);
        });
        updateButtonStates();
    }

    // -------------------------------------------------
    // Toast notifications
    // -------------------------------------------------
    function showToast(title, message, type, duration) {
        type = type || 'success';
        duration = typeof duration === 'number' ? duration : 5000;

        const toastId = 'toast-' + Date.now();
        const icons = {
            success: '&#10003;',
            error: '&#10007;',
            warning: '!',
            info: 'i'
        };

        const $toast = $(`
            <div class="hvnly-toast ${type}" id="${toastId}">
                <div class="hvnly-toast-icon"><span>${icons[type] || icons.info}</span></div>
                <div class="hvnly-toast-content">
                    <h4 class="hvnly-toast-title"></h4>
                    <p class="hvnly-toast-message"></p>
                </div>
                <button type="button" class="hvnly-toast-close" aria-label="Dismiss">&times;</button>
            </div>
        `);

        $toast.find('.hvnly-toast-title').text(title);
        $toast.find('.hvnly-toast-message').text(message);

        $('#hvnly-toast-container').prepend($toast);

        setTimeout(function() {
            $toast.addClass('show');
        }, 50);

        $toast.find('.hvnly-toast-close').on('click', function() {
            hideToast($toast);
        });

        if (duration > 0) {
            setTimeout(function() {
                hideToast($toast);
            }, duration);
        }
    }

    function hideToast($toast) {
        $toast.removeClass('show').addClass('hide');
        setTimeout(function() {
            $toast.remove();
        }, 350);
    }

    // -------------------------------------------------
    // Reusable confirmation modal
    // -------------------------------------------------
    function showConfirmDialog(title, message, onConfirm) {
        pendingConfirmCallback = typeof onConfirm === 'function' ? onConfirm : null;

        $('#hvnly-modal-title').text(title);
        $('#hvnly-modal-body').html(`
            <div class="hvnly-confirm-icon">
                <div class="hvnly-modal-icon warning"><span>!</span></div>
            </div>
            <p class="hvnly-modal-message">${message}</p>
        `);

        $('#hvnly-modal-footer').html(`
            <button type="button" class="hvnly-modal-button secondary" data-modal-action="cancel">Cancel</button>
            <button type="button" class="hvnly-modal-button primary" data-modal-action="confirm">Confirm</button>
        `);

        $('#hvnly-modal-overlay').addClass('show');
    }

    function hideModal() {
        $('#hvnly-modal-overlay').removeClass('show');
        pendingConfirmCallback = null;
    }

    function escapeHtml(text) {
        return $('<div>').text(text).html();
    }

    $('#hvnly-modal-close, #hvnly-modal-overlay').on('click', function(e) {
        if (e.target === this) {
            hideModal();
        }
    });

    $(document).on('click', '[data-modal-action="cancel"]', function(e) {
        e.preventDefault();
        hideModal();
    });

    $(document).on('click', '[data-modal-action="confirm"]', function(e) {
        e.preventDefault();
        const callback = pendingConfirmCallback;
        hideModal();
        if (callback) {
            callback();
        }
    });

    // -------------------------------------------------
    // Unified AJAX helper
    // -------------------------------------------------
    function getResponseMessage(res) {
        if (!res || typeof res.data === 'undefined') {
            return '';
        }
        if (typeof res.data === 'string') {
            return res.data;
        }
        return res.data.message || '';
    }

    function updateLastClearedLabels(lastCleared) {
        if (!lastCleared) {
            return;
        }
        if (lastCleared.all) {
            $('#hvnly-last-cleared-all').text(lastCleared.all);
        }
        if (lastCleared.shortcode) {
            $('#hvnly-last-cleared-shortcode').text(lastCleared.shortcode);
        }
        if (lastCleared.css) {
            $('#hvnly-last-cleared-css').text(lastCleared.css);
        }
    }

    function performAjaxAction(options) {
        const $btn = options.$btn;
        const requestData = $.extend({
            nonce: hvnlyCacheAdmin.nonce
        }, options.data || {});

        setButtonLoading($btn, true);

        return $.ajax({
            url: hvnlyCacheAdmin.ajaxurl,
            type: 'POST',
            data: requestData,
            timeout: AJAX_TIMEOUT
        }).done(function(res) {
            if (res && res.success) {
                showToast(
                    options.successTitle || 'Success',
                    getResponseMessage(res) || options.successFallback || hvnlyCacheAdmin.clearedText,
                    'success',
                    4000
                );

                if (res.data && res.data.last_cleared) {
                    updateLastClearedLabels(res.data.last_cleared);
                }

                if (options.onSuccess) {
                    options.onSuccess(res);
                }

                refreshStats();
            } else {
                showToast(
                    'Failed',
                    getResponseMessage(res) || options.errorFallback || hvnlyCacheAdmin.errorText,
                    'error',
                    5000
                );
            }
        }).fail(function(xhr, status) {
            const message = status === 'timeout'
                ? (hvnlyCacheAdmin.timeoutText || 'Request timed out.')
                : (hvnlyCacheAdmin.networkErrorText || 'Network error. Please try again.');
            showToast('Error', message, 'error', 5000);
        }).always(function() {
            setButtonLoading($btn, false);
            restoreAllButtons();
        });
    }

    // -------------------------------------------------
    // Stats helpers
    // -------------------------------------------------
    function getStatValue(statKey) {
        const raw = $('.stat-card[data-stat="' + statKey + '"] .stat-value').text() || '0';
        return parseInt(String(raw).replace(/[^0-9]/g, ''), 10) || 0;
    }

    function formatNumber(number) {
        return Number(number || 0).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    }

    function updateStatsDisplay(stats, perf) {
        stats = stats || {};
        perf = perf || {};

        let hitRate = parseFloat(stats.cache_hit_rate) || 0;
        hitRate = Math.max(0, Math.min(100, hitRate));

        const statMap = {
            cache_size_human: stats.cache_size_human || '0 Bytes',
            search_cache_count: formatNumber(stats.search_cache_count || 0),
            term_cache_count: formatNumber(stats.term_cache_count || 0),
            cache_hit_rate: hitRate.toFixed(1) + '%',
            sidebar_cache_count: formatNumber(stats.sidebar_cache_count || 0),
            total_cached_items: formatNumber(stats.total_cached_items || 0)
        };

        $.each(statMap, function(key, value) {
            $('.stat-card[data-stat="' + key + '"] .stat-value').text(value);
        });

        const perfMap = {
            cache_hits: formatNumber(perf.cache_hits || 0),
            cache_misses: formatNumber(perf.cache_misses || 0),
            queries_executed: formatNumber(perf.queries_executed || 0),
            average_query_time: (parseFloat(perf.average_query_time) || 0).toFixed(4) + 's',
            cache_efficiency: (parseFloat(perf.cache_efficiency) || 0) + '%',
            memory_usage: formatBytes(perf.memory_usage || 0),
            total_queries_saved: formatNumber(perf.total_queries_saved || 0)
        };

        $.each(perfMap, function(key, value) {
            $('.stat-card[data-perf="' + key + '"] .stat-value').text(value);
        });
    }

    function formatBytes(bytes) {
        bytes = parseInt(bytes, 10) || 0;
        if (bytes === 0) {
            return '0 B';
        }
        const units = ['B', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(1024));
        return (bytes / Math.pow(1024, i)).toFixed(2) + ' ' + units[i];
    }

    function updateButtonStates() {
        const totalItems = getStatValue('total_cached_items');
        const searchItems = getStatValue('search_cache_count');
        const sidebarItems = getStatValue('sidebar_cache_count');
        const termsItems = getStatValue('term_cache_count');

        toggleActionButton($('#hvnly-clear-cache'), totalItems > 0);
        toggleActionButton($('#hvnly-clear-search-cache'), searchItems > 0);
        toggleActionButton($('#hvnly-clear-sidebar-cache'), sidebarItems > 0);
        toggleActionButton($('#hvnly-clear-terms-cache'), termsItems > 0);

        const hasAnyCache = totalItems > 0;
        toggleActionButton($('#hvnly-clear-shortcode-cache'), hasAnyCache);
        toggleActionButton($('#hvnly-clear-grid-shortcode'), hasAnyCache);
        toggleActionButton($('#hvnly-clear-list-shortcode'), hasAnyCache);
        toggleActionButton($('#hvnly-clear-search-shortcode'), hasAnyCache);

        if (totalItems === 0) {
            if ($('.cache-empty-notice').length === 0) {
                $('.hvnly-cache-action-cards').prepend(`
                    <div class="cache-empty-notice" style="grid-column:1/-1;margin-bottom:4px;padding:12px;background:rgba(108,96,254,.08);border-left:4px solid var(--hvnly-brand-primary,#6c60fe);border-radius:8px;">
                        <strong>${escapeHtml('All caches are empty')}</strong>
                        <span style="margin-left:6px;color:var(--hvnly-text-secondary,#646970);">No cache data to clear.</span>
                    </div>
                `);
            }
        } else {
            $('.cache-empty-notice').remove();
        }
    }

    function toggleActionButton($btn, enabled) {
        if (!$btn.length) {
            return;
        }
        $btn.prop('disabled', !enabled).toggleClass('is-disabled', !enabled);
    }

    function refreshStats($btn) {
        if ($btn && $btn.length) {
            setButtonLoading($btn, true);
        }

        return $.ajax({
            url: hvnlyCacheAdmin.ajaxurl,
            type: 'POST',
            data: {
                action: 'hvnly_get_cache_stats',
                nonce: hvnlyCacheAdmin.nonce
            },
            timeout: AJAX_TIMEOUT
        }).done(function(res) {
            if (res && res.success) {
                updateStatsDisplay(res.data.stats, res.data.performance);
                updateLastClearedLabels(res.data.last_cleared);
                updateButtonStates();
                if ($btn && $btn.length) {
                    showToast('Stats Updated', 'Cache statistics have been refreshed.', 'success', 2500);
                }
            } else {
                showToast('Error', 'Failed to refresh statistics.', 'error', 4000);
            }
        }).fail(function(xhr, status) {
            const message = status === 'timeout'
                ? (hvnlyCacheAdmin.timeoutText || 'Request timed out.')
                : (hvnlyCacheAdmin.networkErrorText || 'Network error. Please try again.');
            showToast('Error', message, 'error', 4000);
        }).always(function() {
            if ($btn && $btn.length) {
                setButtonLoading($btn, false);
            }
            restoreAllButtons();
        });
    }

    // -------------------------------------------------
    // Cache clear actions
    // -------------------------------------------------
    function clearCacheType(cacheType, $btn) {
        performAjaxAction({
            $btn: $btn,
            data: {
                action: 'hvnly_clear_cache',
                cache_type: cacheType
            },
            successTitle: 'Cache Cleared'
        });
    }

    function clearShortcodeCache(shortcodeType, $btn) {
        performAjaxAction({
            $btn: $btn,
            data: {
                action: 'hvnly_clear_shortcode_cache',
                shortcode_type: shortcodeType
            },
            successTitle: 'Shortcode Cache Cleared'
        });
    }

    function clearDynamicCss($btn) {
        performAjaxAction({
            $btn: $btn,
            data: {
                action: 'hvnly_clear_dynamic_css'
            },
            successTitle: 'CSS Cache Cleared'
        });
    }

    function bindClearButton(selector, options) {
        $(document).on('click', selector, function(e) {
            e.preventDefault();
            const $btn = $(this);

            if ($btn.prop('disabled')) {
                return;
            }

            const runAction = function() {
                options.run($btn);
            };

            if (options.confirm) {
                showConfirmDialog(options.confirm.title, options.confirm.message, runAction);
                return;
            }

            runAction();
        });
    }

    bindClearButton('#hvnly-clear-cache', {
        confirm: {
            title: 'Clear All Cache',
            message: 'Are you sure you want to clear <strong>all</strong> Havenlytics cache? This removes search results, sidebar filters, terms, and shortcode data.'
        },
        run: function($btn) {
            if (getStatValue('total_cached_items') === 0) {
                showToast('No Cache to Clear', 'All cache is already empty.', 'info', 3000);
                return;
            }
            clearCacheType('all', $btn);
        }
    });

    bindClearButton('#hvnly-clear-search-cache', {
        confirm: {
            title: 'Clear Search Cache',
            message: 'This will clear all cached property search results. Recent searches may be slower until the cache rebuilds.'
        },
        run: function($btn) {
            if (getStatValue('search_cache_count') === 0) {
                showToast('No Search Cache', 'Search cache is already empty.', 'info', 3000);
                return;
            }
            clearCacheType('search', $btn);
        }
    });

    bindClearButton('#hvnly-clear-sidebar-cache', {
        confirm: {
            title: 'Clear Sidebar Cache',
            message: 'This will clear all cached sidebar filter data.'
        },
        run: function($btn) {
            if (getStatValue('sidebar_cache_count') === 0) {
                showToast('No Sidebar Cache', 'Sidebar cache is already empty.', 'info', 3000);
                return;
            }
            clearCacheType('sidebar', $btn);
        }
    });

    bindClearButton('#hvnly-clear-terms-cache', {
        confirm: {
            title: 'Clear Terms Cache',
            message: 'This will clear all cached taxonomy terms used in filters.'
        },
        run: function($btn) {
            if (getStatValue('term_cache_count') === 0) {
                showToast('No Terms Cache', 'Terms cache is already empty.', 'info', 3000);
                return;
            }
            clearCacheType('terms', $btn);
        }
    });

    bindClearButton('#hvnly-clear-shortcode-cache', {
        confirm: {
            title: 'Clear All Shortcode Cache',
            message: 'This will clear all cached shortcode outputs (grid, list, search). Fresh content will render on the next page load.'
        },
        run: function($btn) {
            clearShortcodeCache('all', $btn);
        }
    });

    bindClearButton('#hvnly-clear-grid-shortcode', {
        run: function($btn) {
            clearShortcodeCache('hvnly_property_grid', $btn);
        }
    });

    bindClearButton('#hvnly-clear-list-shortcode', {
        run: function($btn) {
            clearShortcodeCache('hvnly_property_list', $btn);
        }
    });

    bindClearButton('#hvnly-clear-search-shortcode', {
        run: function($btn) {
            clearShortcodeCache('hvnly_property_search', $btn);
        }
    });

    bindClearButton('#hvnly-clear-dynamic-css', {
        confirm: {
            title: 'Clear Dynamic CSS Cache',
            message: 'This will clear generated dynamic CSS. Styles will regenerate on the next frontend request.'
        },
        run: function($btn) {
            clearDynamicCss($btn);
        }
    });

    $refreshStatsBtn.on('click', function(e) {
        e.preventDefault();
        refreshStats($(this));
    });

    // Settings saved toast
    if (sessionStorage.getItem('hvnly_settings_saved') === 'true') {
        showToast('Settings Saved', 'Your cache settings have been updated successfully.', 'success', 4000);
        sessionStorage.removeItem('hvnly_settings_saved');
    }

    $('#hvnly-cache-settings-form').on('submit', function() {
        sessionStorage.setItem('hvnly_settings_saved', 'true');
    });

    // Initial stats load — no global button lock
    refreshStats();
    setInterval(function() {
        refreshStats();
    }, 30000);
});
