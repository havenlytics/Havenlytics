<?php
/**
 * Property Actions Template
 *
 * This template displays action buttons (Save, Print) for single property pages.
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

/*
 * 3.4.0: `save_enabled` has been passed in since 2.x but was never read, so
 * the "Allow users to save properties" setting controlled nothing on this
 * template. It now renders the same favorite control used on archive cards,
 * sharing its state, styling and delegated click handler.
 */
$hvnly_save_enabled = isset($args['save_enabled']) ? (bool) $args['save_enabled'] : false;
$hvnly_property_id  = (int) get_the_ID();

$hvnly_is_favorited = ($hvnly_save_enabled && function_exists('hvnly_is_property_favorited'))
    ? hvnly_is_property_favorited($hvnly_property_id)
    : false;

$hvnly_save_label = $hvnly_is_favorited
    ? __('Remove from favorites', 'havenlytics')
    : __('Add to favorites', 'havenlytics');

// 3.4.0: thumbnail + title for the Favorite toast (see archive/fields/favorite.php).
$hvnly_toast_data = function_exists('hvnly_get_favorite_toast_data')
    ? hvnly_get_favorite_toast_data($hvnly_property_id)
    : array('title' => '', 'thumb' => '');
?>

<div class="hvnly-property-single__actions">
    <div class="hvnly-property-single__actions-wrapper">

        <?php if ($hvnly_save_enabled && $hvnly_property_id > 0) : ?>
        <button type="button"
            class="hvnly-property-single__action-btn hvnly-property-single__save-btn hvnly-property--grid-list--favorite<?php echo $hvnly_is_favorited ? ' is-favorited' : ''; ?>"
            data-hvnly-favorite="1"
            data-property-id="<?php echo esc_attr($hvnly_property_id); ?>"
            data-property-title="<?php echo esc_attr($hvnly_toast_data['title']); ?>"
            data-property-thumb="<?php echo esc_url($hvnly_toast_data['thumb']); ?>"
            aria-pressed="<?php echo $hvnly_is_favorited ? 'true' : 'false'; ?>"
            aria-label="<?php echo esc_attr($hvnly_save_label); ?>">
            <i class="<?php echo $hvnly_is_favorited ? 'fas' : 'far'; ?> fa-heart" aria-hidden="true"></i>
            <span class="hvnly-action-text"><?php esc_html_e('Save', 'havenlytics'); ?></span>
        </button>
        <?php endif; ?>

        <?php
        /**
         * Extra single-property actions (Compare, Share, etc.) beside Favorite/Print.
         *
         * @param int $hvnly_property_id Property ID.
         */
        do_action( 'hvnly_single_property_actions', $hvnly_property_id );
        ?>

        <?php if ($hvnly_print_enabled) : ?>
        <button type="button" class="hvnly-property-single__action-btn hvnly-property-single__print-btn"
            onclick="window.print();" aria-label="<?php esc_attr_e('Print this property', 'havenlytics'); ?>">
            <svg class="hvnly-icon" aria-hidden="true"><use xlink:href="#hvnly-print"></use></svg>
            <span class="hvnly-action-text"><?php esc_html_e('Print', 'havenlytics'); ?></span>
        </button>
        <?php endif; ?>

    </div>
</div>
