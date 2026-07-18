<?php
/**
 * Property Actions Template
 *
 * This template displays action buttons (Print) for single property pages.
 *
 * HOW TO OVERRIDE THIS TEMPLATE:
 * 1. Copy this file from: wp-content/plugins/havenlytics/templates/single-property/actions.php
 * 2. Paste it to your theme at: wp-content/themes/your-theme/havenlytics/single-property/actions.php
 * 3. Modify the copied file to customize the action buttons
 *
 * @package     Havenlytics
 * @subpackage  Templates/single-property/
 * @since       2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get passed arguments
$hvnly_print_enabled = isset($args['print_enabled']) ? $args['print_enabled'] : true;
?>

<div class="hvnly-property-single__actions">
    <div class="hvnly-property-single__actions-wrapper">

        <?php if ($hvnly_print_enabled) : ?>
        <button type="button" class="hvnly-property-single__action-btn hvnly-property-single__print-btn"
            onclick="window.print();" aria-label="<?php esc_attr_e('Print this property', 'havenlytics'); ?>">
            <i class="fas fa-print" aria-hidden="true"></i>
            <span class="hvnly-action-text"><?php esc_html_e('Print', 'havenlytics'); ?></span>
        </button>
        <?php endif; ?>

    </div>
</div>
