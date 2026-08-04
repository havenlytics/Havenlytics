/**
 * Havenlytics Video Field Handler
 * Thumbnail-first UX. Media / save contracts unchanged.
 * @package Havenlytics
 * @version 3.1.0
 */

(function($) {
    'use strict';

    const i18n = (window.HvnlyVideoField && window.HvnlyVideoField.i18n) || {};
    const t = (key, fallback) => (i18n[key] && String(i18n[key])) || fallback;

    class HavenlyticsVideoField {
        constructor(containerId, $container) {
            this.containerId = containerId;
            this.$container = $container;
            this.$inputs = {
                title:     $container.find('input[name*="_title"]'),
                // The URL input is rendered with type="url", so we must NOT
                // filter on data-field-type="text" — that selector finds nothing.
                url:       $container.find('input[type="url"][name*="_url"], input[data-field-type="url"][name*="_url"]'),
                thumbnail: $container.find('input[type="text"][name*="_thumbnail"]')
            };
            this.initialized = false;
            this.mediaFrame = null;
            this.isSelecting = false;
            this.previewOpen = false;

            this.init();
        }

        init() {
            if (this.initialized) return;

            this.bindEvents();
            this.refreshHero();
            this.syncActionStates();
            this.initialized = true;
        }

        bindEvents() {
            const self = this;

            // Upload / Replace thumbnail — same media contract.
            this.$container.find('.hvnly-upload-button').on('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                self.openFileUploader($(this));
                return false;
            });

            // Remove thumbnail — same class contract.
            this.$container.on('click', '.hvnly-remove-preview', function(e) {
                e.preventDefault();
                e.stopPropagation();
                self.removeThumbnail();
                return false;
            });

            this.$container.on('click', '.hvnly-video-preview-toggle, .hvnly-video-play-overlay', function(e) {
                e.preventDefault();
                e.stopPropagation();
                if ($(this).is(':disabled')) {
                    return false;
                }
                self.openInlinePreview();
                return false;
            });

            this.$container.on('click', '.hvnly-video-inline-preview-close', function(e) {
                e.preventDefault();
                self.closeInlinePreview();
                return false;
            });

            if (this.$inputs.url.length) {
                this.$inputs.url.on('input change', function() {
                    self.refreshHero();
                    self.syncActionStates();
                    if (self.previewOpen) {
                        self.mountEmbed();
                    }
                });
            }

            if (this.$inputs.thumbnail.length) {
                this.$inputs.thumbnail.on('input change', function() {
                    self.refreshHero();
                    self.syncActionStates();
                });
            }

            if (this.$inputs.title.length) {
                this.$inputs.title.on('input change', function() {
                    if (self.previewOpen) {
                        self.mountEmbed();
                    }
                });
            }
        }

        openFileUploader($button) {
            const self = this;
            const targetSelector = $button.data('target');
            const $targetInput = $(targetSelector);

            if (!$targetInput.length) {
                return;
            }

            if (typeof wp === 'undefined' || typeof wp.media === 'undefined') {
                alert(t('mediaUnavailable', 'Media uploader is not available. Please check your WordPress installation.'));
                return;
            }

            if (this.isSelecting) {
                return;
            }

            if (this.mediaFrame && this.mediaFrame.el && this.mediaFrame.el.parentNode) {
                this.mediaFrame.open();
                return;
            }

            this.isSelecting = true;

            this.mediaFrame = wp.media({
                title: t('selectImage', 'Select or Upload Image'),
                button: {
                    text: t('useThisImage', 'Use this image')
                },
                multiple: false,
                library: {
                    type: 'image'
                }
            });

            this.mediaFrame.on('select', function() {
                if (!self.mediaFrame || !self.mediaFrame.state) {
                    self.isSelecting = false;
                    return;
                }

                const selection = self.mediaFrame.state().get('selection');
                if (!selection || selection.length === 0) {
                    self.isSelecting = false;
                    return;
                }

                const attachment = selection.first().toJSON();
                if (!attachment || !attachment.url) {
                    self.isSelecting = false;
                    return;
                }

                const fileUrl = attachment.url;
                const isValid = /\.(jpe?g|png|gif|webp|bmp)$/i.test(fileUrl) || (attachment.mime && attachment.mime.startsWith('image/'));
                if (!isValid) {
                    alert(t('invalidImage', 'Please select a valid image file (JPG, PNG, GIF, WEBP, BMP).'));
                    self.isSelecting = false;
                    return;
                }

                $targetInput.val(fileUrl);
                $targetInput.trigger('change');
                self.refreshHero();
                self.syncActionStates();

                if (self.mediaFrame && typeof self.mediaFrame.close === 'function') {
                    self.mediaFrame.close();
                }

                setTimeout(function() {
                    if (self.mediaFrame) {
                        self.mediaFrame = null;
                    }
                    self.isSelecting = false;
                }, 100);
            });

            this.mediaFrame.on('close', function() {
                self.isSelecting = false;
                setTimeout(function() {
                    if (self.mediaFrame) {
                        self.mediaFrame = null;
                    }
                }, 100);
            });

            this.mediaFrame.on('escape', function() {
                self.isSelecting = false;
                setTimeout(function() {
                    if (self.mediaFrame) {
                        self.mediaFrame = null;
                    }
                }, 100);
            });

            this.mediaFrame.open();
        }

        extractYoutubeId(url) {
            if (!url) {
                return '';
            }

            const patterns = [
                /(?:youtube\.com\/watch\?v=)([^&]+)/,
                /(?:youtu\.be\/)([^?]+)/,
                /(?:youtube\.com\/embed\/)([^?]+)/,
                /(?:youtube\.com\/v\/)([^?]+)/
            ];

            for (const pattern of patterns) {
                const match = String(url).match(pattern);
                if (match) {
                    return match[1];
                }
            }

            return '';
        }

        resolveHeroSrc() {
            const thumbVal = this.$inputs.thumbnail.length ? String(this.$inputs.thumbnail.val() || '').trim() : '';
            const urlVal = this.$inputs.url.length ? String(this.$inputs.url.val() || '').trim() : '';
            const youtubeId = this.extractYoutubeId(urlVal);

            if (thumbVal) {
                // Custom thumb URL stored in meta — prefer it always.
                if (/\.(jpe?g|png|gif|webp|bmp)(\?.*)?$/i.test(thumbVal) || thumbVal.indexOf('http') === 0) {
                    return { src: thumbVal, source: 'custom' };
                }
            }

            if (youtubeId) {
                return {
                    src: 'https://img.youtube.com/vi/' + youtubeId + '/hqdefault.jpg',
                    source: 'youtube'
                };
            }

            return { src: '', source: 'empty' };
        }

        refreshHero() {
            const $media = this.$container.find('.hvnly-preview-container');
            const $hero = this.$container.find('.hvnly-video-hero');
            if (!$media.length) {
                return;
            }

            const hero = this.resolveHeroSrc();
            this.$container.attr('data-hero-source', hero.source);
            this.$container.toggleClass('is-empty', hero.source === 'empty');
            this.$container.toggleClass('has-custom-thumb', hero.source === 'custom');
            this.$container.toggleClass('has-video-url', !!this.extractYoutubeId(this.$inputs.url.val()));

            if (!hero.src) {
                $media.html(
                    '<div class="hvnly-video-hero-empty">' +
                    '<span class="dashicons dashicons-format-image" aria-hidden="true"></span>' +
                    '<p class="hvnly-video-hero-empty-title">' + this.escapeHtml(t('noThumbnail', 'No thumbnail yet')) + '</p>' +
                    '<p class="hvnly-video-hero-empty-subtitle">' + this.escapeHtml(t('noThumbnailHelp', 'Upload a poster image, or paste a YouTube URL to use its default frame.')) + '</p>' +
                    '</div>'
                );
                $hero.addClass('is-empty');
                return;
            }

            $hero.removeClass('is-empty');
            $media.html(
                '<div class="hvnly-preview-wrapper hvnly-video-hero-frame">' +
                '<img src="' + this.escapeHtml(hero.src) + '" alt="' + this.escapeHtml(t('videoThumbnail', 'Video thumbnail')) + '" ' +
                'class="hvnly-video-hero-image" data-hero-source="' + this.escapeHtml(hero.source) + '">' +
                '</div>'
            );
        }

        syncActionStates() {
            const hasCustom = !!(this.$inputs.thumbnail.length && String(this.$inputs.thumbnail.val() || '').trim());
            const canPreview = !!this.extractYoutubeId(this.$inputs.url.length ? this.$inputs.url.val() : '');

            this.$container.find('.hvnly-video-remove-thumb').prop('disabled', !hasCustom);
            this.$container.find('.hvnly-video-preview-toggle').prop('disabled', !canPreview);

            const $play = this.$container.find('.hvnly-video-play-overlay');
            $play.prop('disabled', !canPreview);
            if (canPreview) {
                $play.removeAttr('hidden');
            } else {
                $play.attr('hidden', 'hidden');
            }

            const $label = this.$container.find('.hvnly-video-action-label--replace');
            if ($label.length) {
                $label.text(hasCustom ? t('replaceThumbnail', 'Replace Thumbnail') : t('uploadThumbnail', 'Upload Thumbnail'));
            }
        }

        openInlinePreview() {
            const videoId = this.extractYoutubeId(this.$inputs.url.length ? this.$inputs.url.val() : '');
            if (!videoId) {
                return;
            }

            const $panel = this.$container.find('.hvnly-video-inline-preview');
            $panel.prop('hidden', false);
            this.$container.find('.hvnly-video-preview-toggle').attr('aria-expanded', 'true');
            this.previewOpen = true;
            this.mountEmbed();
        }

        closeInlinePreview() {
            const $panel = this.$container.find('.hvnly-video-inline-preview');
            const $embed = this.$container.find('.hvnly-video-preview[data-role="embed"]');
            $embed.empty();
            $panel.prop('hidden', true);
            this.$container.find('.hvnly-video-preview-toggle').attr('aria-expanded', 'false');
            this.previewOpen = false;
        }

        mountEmbed() {
            const $embed = this.$container.find('.hvnly-video-preview[data-role="embed"]');
            if (!$embed.length) {
                return;
            }

            const videoId = this.extractYoutubeId(this.$inputs.url.length ? this.$inputs.url.val() : '');
            const titleVal = this.$inputs.title.length ? this.$inputs.title.val() : '';
            const title = titleVal || t('propertyVideo', 'Property Video');

            if (!videoId) {
                $embed.empty();
                this.closeInlinePreview();
                return;
            }

            $embed.html(
                '<iframe src="https://www.youtube.com/embed/' + this.escapeHtml(videoId) + '?autoplay=1" ' +
                'title="' + this.escapeHtml(title) + '" frameborder="0" allowfullscreen loading="lazy" ' +
                'allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" ' +
                'referrerpolicy="strict-origin-when-cross-origin"></iframe>'
            );
        }

        // Kept name for compatibility with older call sites / mental model.
        updateThumbnailPreview() {
            this.refreshHero();
            this.syncActionStates();
        }

        removeThumbnail() {
            if (this.$inputs.thumbnail.length) {
                this.$inputs.thumbnail.val('');
                this.refreshHero();
                this.syncActionStates();
                this.$inputs.thumbnail.trigger('change');
            }
        }

        escapeHtml(str) {
            if (!str) return '';
            return String(str)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
        }
    }

    $(document).ready(function() {
        $('.hvnly-video-field-container').each(function(index, element) {
            const $container = $(element);
            const containerId = $container.data('field-id') || `video-container-${index}`;

            if (!$container.attr('id')) {
                $container.attr('id', containerId);
            }

            if (!$container.data('hvnlyVideoField')) {
                $container.data('hvnlyVideoField', new HavenlyticsVideoField(containerId, $container));
            }
        });
    });

    $(document).on('click', '.hvnly__dyamic_metabox_tab__nav a', function() {
        setTimeout(function() {
            $('.hvnly-video-field-container').each(function(index, element) {
                const $container = $(element);
                if (!$container.data('hvnlyVideoField')) {
                    const containerId = $container.data('field-id') || $container.attr('id') || `video-container-${index}`;
                    $container.data('hvnlyVideoField', new HavenlyticsVideoField(containerId, $container));
                }
            });
        }, 300);
    });

})(jQuery);
