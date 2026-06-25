<?php
/**
 * AJAX Error Message Template
 * 
 * This template displays an error message for AJAX requests.
 * 
 * HOW TO OVERRIDE THIS TEMPLATE:
 * 1. Copy this file from: wp-content/plugins/havenlytics/templates/ajax/error-message.php
 * 2. Paste it to your theme at: wp-content/themes/your-theme/havenlytics/ajax/error-message.php
 * 3. Modify the copied file to customize the error message display
 * 
 * @package     Havenlytics
 * @subpackage  Templates/ajax
 * @since       2.0.0
 */

if (!defined('ABSPATH')) exit;

// Prefix all variables with hvnly_ to avoid global namespace conflicts
$hvnly_message = $args['message'] ?? __('An error occurred. Please try again.', 'havenlytics');
$hvnly_type = $args['type'] ?? 'general'; // 'general', 'search', 'map'
?>
<div class="hvnly-ajax-error hvnly-ajax-error-<?php echo esc_attr($hvnly_type); ?>">
    <div class="hvnly-ajax-error-icon">
        <i class="fas fa-exclamation-circle"></i>
    </div>
    <div class="hvnly-ajax-error-content">
        <h4><?php esc_html_e('Error', 'havenlytics'); ?></h4>
        <p><?php echo esc_html($hvnly_message); ?></p>
    </div>
    <?php if ($hvnly_type === 'search'): ?>
        <button class="hvnly-ajax-retry-btn" data-action="retry-search">
            <i class="fas fa-redo"></i> <?php esc_html_e('Try Again', 'havenlytics'); ?>
        </button>
    <?php endif; ?>
</div>