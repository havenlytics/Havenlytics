/**
 * Havenlytics Single Property Video Module
 * Handles video popup functionality for multiple videos
 * 
 * @package     Havenlytics
 * @version     2.0.0
 */
(function() {
    'use strict';

    // Prevent multiple initializations
    if (window.hvnlyVideoModuleInitialized) {
        return;
    }
    window.hvnlyVideoModuleInitialized = true;

    // console.log('🎥 Havenlytics Video Module: Loading...');

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initVideoModule);
    } else {
        initVideoModule();
    }

    function initVideoModule() {
        // console.log('🎥 Havenlytics Video Module: Initializing...');
        
        const videoPopup = document.getElementById('hvnlyPropertySingleFancyboxVideo');
        const videoPlayer = document.getElementById('hvnlyPropertySingleVideoPlayer');
        
        if (!videoPopup) {
            // console.error('❌ Video Module: Popup not found!');
            return;
        }
        
        if (!videoPlayer) {
            // console.error('❌ Video Module: Player not found!');
            return;
        }
        
        addVideoStyles();
        
        const videoCards = document.querySelectorAll('.hvnly-property-single__video-card');
        // console.log(`🔍 Video Module: Found ${videoCards.length} video cards`);
        
        if (videoCards.length === 0) {
            // console.warn('⚠️ Video Module: No video cards found');
            return;
        }
        
        videoCards.forEach((card) => {
            card.style.cursor = 'pointer';
            card.addEventListener('click', handleVideoClick);
        });
        
        const closeBtn = videoPopup.querySelector('.hvnly-property-single__fancybox-close');
        if (closeBtn) {
            closeBtn.addEventListener('click', (e) => {
                e.preventDefault();
                closeVideoPopup(videoPopup, videoPlayer);
            });
        }

        // Same CSS-class fullscreen contract as the gallery popup (not browser FS API).
        const fullscreenBtn = videoPopup.querySelector('.hvnly-property-single__fancybox-fullscreen');
        if (fullscreenBtn) {
            fullscreenBtn.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                videoPopup.classList.toggle('hvnly-property-single__fancybox-popup--fullscreen');
                updateFullscreenIcons(videoPopup);
            });
        }
        
        videoPopup.addEventListener('click', (e) => {
            if (e.target === videoPopup) {
                closeVideoPopup(videoPopup, videoPlayer);
            }
        });
        
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && videoPopup.classList.contains('hvnly-property-single__fancybox-popup--active')) {
                closeVideoPopup(videoPopup, videoPlayer);
            }
        });
        
        // console.log('✅ Video Module: Ready');
    }
    
    function addVideoStyles() {
        const styleId = 'hvnly-video-styles';
        if (document.getElementById(styleId)) return;
        
        const style = document.createElement('style');
        style.id = styleId;
        // Scope the 90vw/90vh active size so it cannot override gallery/video
        // CSS-class "fullscreen" (equal specificity otherwise wins via late inject).
        style.textContent = `
            .hvnly-property-single__fancybox-popup--active:not(.hvnly-property-single__fancybox-popup--fullscreen) .hvnly-property-single__fancybox-content {
                width: 90vw;
                height: 90vh;
                max-width: 1400px;
            }

            .hvnly-property-single__fancybox-popup--active.hvnly-property-single__fancybox-popup--fullscreen .hvnly-property-single__fancybox-content {
                width: 100vw;
                height: 100vh;
                max-width: none;
                max-height: none;
            }
            
            .hvnly-property-single__fancybox-popup--active #hvnlyPropertySingleVideoPlayer {
                width: 100%;
                height: 100%;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            
            .hvnly-property-single__fancybox-popup--active #hvnlyPropertySingleVideoPlayer iframe {
                width: 100%;
                height: 100%;
                border: none;
            }
        `;
        document.head.appendChild(style);
    }

    /**
     * Keep expand/compress as a single visible state (matches gallery controller).
     * Inline display beats the shared `.hvnly-ui-control svg { display:inline-block }` rule.
     */
    function updateFullscreenIcons(popup) {
        const fullscreenBtn = popup && popup.querySelector('.hvnly-property-single__fancybox-fullscreen');
        if (!fullscreenBtn) {
            return;
        }

        const isFullscreen = popup.classList.contains('hvnly-property-single__fancybox-popup--fullscreen');
        const expandIcon = fullscreenBtn.querySelector('.hvnly-expand');
        const compressIcon = fullscreenBtn.querySelector('.hvnly-compress');

        if (expandIcon && compressIcon) {
            expandIcon.style.display = isFullscreen ? 'none' : 'inline-block';
            compressIcon.style.display = isFullscreen ? 'inline-block' : 'none';
        }
    }
    
    function handleVideoClick(e) {
        e.preventDefault();
        e.stopPropagation();
        
        const card = e.currentTarget;
        const videoSource = card.getAttribute('data-video-source');
        const videoId = card.getAttribute('data-video-id');
        
        if (!videoSource || !videoId) {
            alert((window.hvnly_property_data && window.hvnly_property_data.i18n && window.hvnly_property_data.i18n.videoCannotPlay) || (window.hvnly_map_params && window.hvnly_map_params.i18n && window.hvnly_map_params.i18n.videoCannotPlay) || '');
            return;
        }
        
        openVideoPopup(videoSource, videoId);
    }
    
    function openVideoPopup(source, videoId) {
        const videoPopup = document.getElementById('hvnlyPropertySingleFancyboxVideo');
        const videoPlayer = document.getElementById('hvnlyPropertySingleVideoPlayer');
        
        if (!videoPopup || !videoPlayer) return;
        
        videoPlayer.innerHTML = '';
        
        const container = document.createElement('div');
        container.style.width = '100%';
        container.style.height = '100%';
        container.style.display = 'flex';
        container.style.alignItems = 'center';
        container.style.justifyContent = 'center';
        
        const iframe = document.createElement('iframe');
        iframe.setAttribute('frameborder', '0');
        iframe.setAttribute('allow', 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture');
        iframe.setAttribute('allowfullscreen', 'true');
        iframe.style.width = '100%';
        iframe.style.height = '100%';
        iframe.style.border = 'none';
        
        iframe.src = source === 'youtube' 
            ? `https://www.youtube-nocookie.com/embed/${videoId}?autoplay=1&rel=0&modestbranding=1&controls=1`
            : `https://player.vimeo.com/video/${videoId}?autoplay=1&title=0&byline=0&portrait=0`;
        
        container.appendChild(iframe);
        videoPlayer.appendChild(container);
        
        videoPopup.classList.remove('hvnly-property-single__fancybox-popup--fullscreen');
        videoPopup.classList.add('hvnly-property-single__fancybox-popup--active');
        updateFullscreenIcons(videoPopup);
        document.body.style.overflow = 'hidden';
    }
    
    function closeVideoPopup(popup, player) {
        popup.classList.remove('hvnly-property-single__fancybox-popup--active');
        popup.classList.remove('hvnly-property-single__fancybox-popup--fullscreen');
        updateFullscreenIcons(popup);
        document.body.style.overflow = '';
        if (player) player.innerHTML = '';
    }

})();