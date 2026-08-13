<?php
/**
 * Map Error Template
 * 
 * This template displays an error message when the map fails to load.
 * 
 * HOW TO OVERRIDE THIS TEMPLATE:
 * 1. Copy this file from: wp-content/plugins/havenlytics/templates/map/map-error.php
 * 2. Paste it to your theme at: wp-content/themes/your-theme/havenlytics/map/map-error.php
 * 3. Modify the copied file to customize the error message display
 * 
 * @package     Havenlytics
 * @subpackage  Templates/map
 * @since       2.0.0
 */

if (!defined('ABSPATH')) exit;

// Prefix all variables with hvnly_ to avoid global namespace conflicts
$hvnly_message = $args['message'] ?? __('Map loading failed. Please try again.', 'havenlytics');
$hvnly_show_retry = $args['show_retry'] ?? true;
?>
<div class="hvnly-map-error">
    <div class="hvnly-map-error-icon">
        <i class="fas fa-exclamation-triangle"></i>
    </div>
    <h3><?php esc_html_e('Map Unavailable', 'havenlytics'); ?></h3>
    <p><?php echo esc_html($hvnly_message); ?></p>
    <?php if ($hvnly_show_retry): ?>
        <button type="button" class="hvnly-ui-control hvnly-retry-map-btn" data-action="retry-map">
            <i class="fas fa-redo"></i> <?php esc_html_e('Retry', 'havenlytics'); ?>
        </button>
    <?php endif; ?>
</div>