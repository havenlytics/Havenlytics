(function($) {
    'use strict';

    class HvnlyPropertyGlobalShareManager {
        constructor() {
            this.isOpen = false;
            this.currentPropertyId = null;
            this.currentShareData = null;
            this.autoCloseTimeout = null;
            this.countdownInterval = null;
            this.secondsLeft = 15;
            this.i18n = window.hvnlyShareModal?.i18n || {};
            this.init();
        }

        init() {
            if (window.HvnlyElementorPreview && window.HvnlyElementorPreview.shouldDisableInteractiveUi()) {
                return;
            }

            this.bindEvents();
        }

        bindEvents() {
            const self = this;

            $(document).on('click', '.hvnly-property-share-trigger', function(e) {
                e.preventDefault();
                e.stopPropagation();

                const $trigger = $(this);
                const shareData = self.resolveShareData($trigger);

                if (!shareData) {
                    return;
                }

                self.openPopup($trigger.data('property-id'), shareData);
            });

            $(document).on('click', '.hvnly-property-popup-close', function(e) {
                e.preventDefault();
                self.closePopup();
            });

            $(document).on('click', '.hvnly-property-share-popup-overlay', function(e) {
                if (e.target === this) {
                    self.closePopup();
                }
            });

            $(document).on('click', '.hvnly-property-copy-btn', function(e) {
                e.preventDefault();
                self.copyToClipboard(this);
            });

            $(document).on('click', '.hvnly-property-share-platform', function(e) {
                const platform = $(this).data('platform');
                self.handlePlatformClick(e, platform, this);
            });

            $(document).on('keyup', function(e) {
                if (e.key === 'Escape' && self.isOpen) {
                    self.closePopup();
                }
            });
        }

        resolveShareData($trigger) {
            const $field = $trigger.closest('.hvnly-property-share-field');
            let rawConfig = $field.attr('data-share-config') || $trigger.attr('data-share-config') || '';

            if (!rawConfig) {
                return null;
            }

            try {
                return typeof rawConfig === 'object' ? rawConfig : JSON.parse(rawConfig);
            } catch (error) {
                return null;
            }
        }

        openPopup(propertyId, shareData) {
            if (window.HvnlyElementorPreview && window.HvnlyElementorPreview.shouldDisableInteractiveUi()) {
                return;
            }

            const $popup = $('#hvnly-property-global-share-popup');
            if (!$popup.length) {
                return;
            }

            if (this.isOpen) {
                return;
            }

            this.isOpen = true;
            this.currentPropertyId = propertyId;
            this.currentShareData = shareData;

            $('.hvnly-property-share-trigger').addClass('disabled');
            $('body').addClass('hvnly-property-share-popup-open');

            $popup
                .addClass('active hvnly-share-user-opened')
                .attr('aria-hidden', 'false');

            this.populatePopupContent(shareData);
            this.startAutoCloseTimer();
            this.showCopySection();

            $popup.find('.hvnly-property-popup-close').trigger('focus');
        }

        closePopup() {
            if (!this.isOpen) {
                return;
            }

            this.isOpen = false;
            this.currentPropertyId = null;
            this.currentShareData = null;

            $('.hvnly-property-share-trigger').removeClass('disabled');
            $('body').removeClass('hvnly-property-share-popup-open');

            $('#hvnly-property-global-share-popup')
                .removeClass('active hvnly-share-user-opened')
                .attr('aria-hidden', 'true');

            this.clearAutoCloseTimer();
            this.resetCopyButton();
        }

        populatePopupContent(shareData) {
            $('.hvnly-property-link-input').val(shareData.url || '');
            this.populatePlatforms(shareData);
        }

        getPlatformConfigs() {
            return window.hvnlyShareModal?.platformConfigs || {};
        }

        populatePlatforms(shareData) {
            const platformsContainer = $('#hvnly-property-share-platforms-container');
            platformsContainer.empty();

            const platformConfigs = this.getPlatformConfigs();
            const platforms = Array.isArray(shareData.platforms) ? shareData.platforms : [];
            const shareOnLabel = this.i18n.shareOn || 'Share on';

            platforms.forEach((platform) => {
                let config = platformConfigs[platform];
                if (!config) {
                    return;
                }

                if (platform === 'email') {
                    config = {
                        ...config,
                        url: shareData.mailto_link || '#'
                    };
                }

                let shareUrl = config.url || '#';
                if (platform !== 'email' && platform !== 'instagram' && platform !== 'copy_link') {
                    shareUrl = shareUrl
                        .replace('{url}', encodeURIComponent(shareData.url || ''))
                        .replace('{title}', encodeURIComponent(shareData.title || ''))
                        .replace('{image}', encodeURIComponent(shareData.image || ''));
                }

                const isActionLink = platform === 'copy_link' || platform === 'instagram';
                const target = isActionLink ? '' : 'target="_blank" rel="noopener noreferrer"';
                const href = isActionLink ? '#' : shareUrl;

                const platformHtml = `
                    <a href="${href}"
                       class="hvnly-property-share-platform hvnly-property-share-${platform}"
                       data-platform="${platform}"
                       ${target}
                       aria-label="${shareOnLabel} ${config.name}">
                        <div class="hvnly-property-platform-icon" style="background-color: ${config.color}">
                            <i class="${config.icon}" aria-hidden="true"></i>
                        </div>
                        <span class="hvnly-property-platform-name">${config.name}</span>
                    </a>
                `;

                platformsContainer.append(platformHtml);
            });
        }

        startAutoCloseTimer() {
            this.secondsLeft = 15;
            this.updateTimerText();

            this.countdownInterval = setInterval(() => {
                this.secondsLeft -= 1;
                this.updateTimerText();

                if (this.secondsLeft <= 5) {
                    $('.hvnly-property-auto-close-timer').addClass('timer-warning');
                }

                if (this.secondsLeft <= 0) {
                    this.closePopup();
                }
            }, 1000);

            this.autoCloseTimeout = setTimeout(() => this.closePopup(), 15000);
        }

        clearAutoCloseTimer() {
            if (this.autoCloseTimeout) {
                clearTimeout(this.autoCloseTimeout);
                this.autoCloseTimeout = null;
            }
            if (this.countdownInterval) {
                clearInterval(this.countdownInterval);
                this.countdownInterval = null;
            }
            $('.hvnly-property-auto-close-timer').removeClass('timer-warning');
        }

        updateTimerText() {
            const prefix = this.i18n.autoCloseIn || 'Auto-close in';
            $('.hvnly-property-timer-text').text(prefix + ' ' + this.secondsLeft + 's');
        }

        copyToClipboard(copyBtn) {
            const input = $('.hvnly-property-link-input')[0];
            if (!input) {
                return;
            }

            const textToCopy = input.value;
            const originalReadonly = input.readOnly;

            input.readOnly = false;
            input.focus();
            input.select();
            input.setSelectionRange(0, 99999);

            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(textToCopy).then(() => {
                    this.showCopySuccess(copyBtn);
                }).catch(() => {
                    this.fallbackCopyTextToClipboard(textToCopy, copyBtn);
                }).finally(() => {
                    input.readOnly = originalReadonly;
                });
            } else {
                this.fallbackCopyTextToClipboard(textToCopy, copyBtn);
                input.readOnly = originalReadonly;
            }
        }

        fallbackCopyTextToClipboard(text, copyBtn) {
            const textArea = document.createElement('textarea');
            textArea.value = text;
            textArea.style.top = '0';
            textArea.style.left = '0';
            textArea.style.position = 'fixed';

            document.body.appendChild(textArea);
            textArea.focus();
            textArea.select();

            try {
                if (document.execCommand('copy')) {
                    this.showCopySuccess(copyBtn);
                }
            } catch (err) {
                // Silent fail.
            }

            document.body.removeChild(textArea);
        }

        showCopySuccess(copyBtn) {
            $('.hvnly-property-copy-success').addClass('show');
            $(copyBtn).addClass('copied');

            setTimeout(() => {
                $('.hvnly-property-copy-success').removeClass('show');
                this.resetCopyButton();
            }, 2000);

            this.trackShare('copy');
        }

        resetCopyButton() {
            $('.hvnly-property-copy-btn').removeClass('copied');
        }

        handlePlatformClick(e, platform) {
            if (platform === 'instagram') {
                e.preventDefault();
                alert(this.i18n.instagramHint || 'Please open Instagram app to share this property.');
                return;
            }

            if (platform === 'copy_link') {
                e.preventDefault();
                const copyBtn = $('.hvnly-property-copy-btn')[0];
                if (copyBtn) {
                    this.copyToClipboard(copyBtn);
                }
                return;
            }

            this.trackShare(platform);
            this.clearAutoCloseTimer();
            this.startAutoCloseTimer();
        }

        showCopySection() {
            $('.hvnly-property-copy-link-section').addClass('hvnly-property-active-section');
        }

        trackShare(platform) {
            if (typeof gtag !== 'undefined') {
                gtag('event', 'share', {
                    method: platform,
                    content_type: 'property',
                    content_id: this.currentPropertyId
                });
            }

            $(document).trigger('hvnly_property_shared', {
                property_id: this.currentPropertyId,
                platform: platform,
                url: this.currentShareData?.url
            });
        }
    }

    $(document).ready(function() {
        if (window.HvnlyElementorPreview && window.HvnlyElementorPreview.shouldDisableInteractiveUi()) {
            return;
        }

        if (!$('#hvnly-property-global-share-popup').length) {
            return;
        }

        window.hvnlyPropertyShareManager = new HvnlyPropertyGlobalShareManager();
    });

})(jQuery);
