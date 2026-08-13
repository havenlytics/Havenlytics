<?php
/**
 * Map Container Template
 * 
 * This template displays the container for the map.
 * 
 * HOW TO OVERRIDE THIS TEMPLATE:
 * 1. Copy this file from: wp-content/plugins/havenlytics/templates/map/map-container.php
 * 2. Paste it to your theme at: wp-content/themes/your-theme/havenlytics/map/map-container.php
 * 3. Modify the copied file to customize the map container display
 * 
 * @package     Havenlytics
 * @subpackage  Templates/map
 * @since       2.2.0
 */

if (!defined('ABSPATH')) exit;

// Get provider from args or use function
$hvnly_provider = isset($args['provider']) ? $args['provider'] : (function_exists('hvnly_get_map_provider') ? hvnly_get_map_provider() : 'leaflet');
$hvnly_provider_class = 'hvnly-' . $hvnly_provider . '-map';

// Get settings using helper functions if available
$hvnly_show_fullscreen = isset($args['show_fullscreen']) ? $args['show_fullscreen'] : (function_exists('hvnly_is_fullscreen_enabled') ? hvnly_is_fullscreen_enabled() : true);
$hvnly_show_zoom_control = isset($args['show_zoom_control']) ? $args['show_zoom_control'] : (function_exists('hvnly_is_zoom_control_enabled') ? hvnly_is_zoom_control_enabled() : true);
$hvnly_show_scroll_wheel = isset($args['show_scroll_wheel']) ? $args['show_scroll_wheel'] : (function_exists('hvnly_is_scroll_wheel_enabled') ? hvnly_is_scroll_wheel_enabled() : true);
$hvnly_map_zoom = isset($args['zoom_level']) ? $args['zoom_level'] : (function_exists('hvnly_get_map_zoom') ? hvnly_get_map_zoom() : 12);

// Debug disabled for production
// $hvnly_debug = (defined('WP_DEBUG') && WP_DEBUG);

$hvnly_map_id = 'hvnly-properties-map';
$hvnly_fullscreen_btn_id = 'hvnly-' . $hvnly_provider . '-map-fullscreen-btn';
$hvnly_fullscreen_btn_class = 'hvnly-ui-control hvnly-map-fullscreen-btn hvnly-' . $hvnly_provider . '-map-fullscreen-btn';

// Debug output disabled for production
// if ($hvnly_debug) {
//     echo sprintf('<!-- Havenlytics Map Debug: Provider=%s, Zoom=%d, Fullscreen=%s, ZoomControl=%s, ScrollWheel=%s -->' . PHP_EOL,
//         esc_attr($hvnly_provider),
//         esc_attr($hvnly_map_zoom),
//         $hvnly_show_fullscreen ? 'enabled' : 'disabled',
//         $hvnly_show_zoom_control ? 'enabled' : 'disabled',
//         $hvnly_show_scroll_wheel ? 'enabled' : 'disabled'
//     );
// }
?>

<div class="hvnly-map-view <?php echo esc_attr($hvnly_provider_class); ?>"
    id="hvnly-map-view-<?php echo esc_attr($hvnly_provider); ?>"
    data-provider="<?php echo esc_attr($hvnly_provider); ?>" data-zoom="<?php echo esc_attr($hvnly_map_zoom); ?>"
    data-scroll-wheel="<?php echo esc_attr($hvnly_show_scroll_wheel ? 'true' : 'false'); ?>">

    <div class="hvnly-map-container">
        <!-- Map Canvas -->
        <div id="<?php echo esc_attr($hvnly_map_id); ?>" class="hvnly-properties-map"
            data-zoom-control="<?php echo esc_attr($hvnly_show_zoom_control ? 'true' : 'false'); ?>">
        </div>

        <!-- Fullscreen Button -->
        <?php if ($hvnly_show_fullscreen) : ?>
        <button id="<?php echo esc_attr($hvnly_fullscreen_btn_id); ?>"
            class="<?php echo esc_attr($hvnly_fullscreen_btn_class); ?>"
            title="<?php esc_attr_e('Toggle Fullscreen', 'havenlytics'); ?>"
            data-fullscreen-target="<?php echo esc_attr($hvnly_map_id); ?>">
            <i class="fas fa-expand"></i>
        </button>
        <?php endif; ?>

        <!-- Loading Overlay -->
        <div class="hvnly-map-loading" style="display: none;">
            <div class="hvnly-map-loader">
                <div class="hvnly-map-loader-animation">
                    <div class="hvnly-map-loader-pulse"></div>
                    <div class="hvnly-map-loader-pin">
                        <i class="fas fa-map-marker-alt"></i>
                    </div>
                    <div class="hvnly-map-loader-ripple"></div>
                    <div class="hvnly-map-loader-ripple ripple-delay-1"></div>
                    <div class="hvnly-map-loader-ripple ripple-delay-2"></div>
                </div>
                <div class="hvnly-map-loader-text">
                    <span
                        class="hvnly-map-loader-title"><?php esc_html_e('Loading Properties Map', 'havenlytics'); ?></span>
                    <span
                        class="hvnly-map-loader-subtitle"><?php esc_html_e('Finding the perfect locations for you...', 'havenlytics'); ?></span>
                </div>
                <div class="hvnly-map-loader-progress">
                    <div class="hvnly-map-loader-progress-bar"></div>
                </div>
            </div>
        </div>

    </div>
</div>