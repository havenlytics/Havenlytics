<?php
/**
 * Status Field Template
 * 
 * This template displays the status for a property.
 * 
 * HOW TO OVERRIDE THIS TEMPLATE:
 * 1. Copy this file from: wp-content/plugins/havenlytics/templates/archive/fields/status.php
 * 2. Paste it to your theme at: wp-content/themes/your-theme/havenlytics/archive/fields/status.php
 * 3. Modify the copied file to customize the status display
 * 
 * @package     Havenlytics
 * @subpackage  Templates/archive/Fields
 * @since       2.0.0
 */


if (!defined('ABSPATH')) {
    exit;
}

// Prefix all variables with hvnly_ to avoid global namespace conflicts
$hvnly_property_id = $property_id ?? get_the_ID();

// Get status terms from taxonomy
$hvnly_statuses = get_the_terms($hvnly_property_id, 'hvnly_prop_status');

if (empty($hvnly_statuses) || is_wp_error($hvnly_statuses)) {
    return;
}

// Check if any status has display all option enabled
$hvnly_display_all = false;
foreach ($hvnly_statuses as $hvnly_status) {
    $hvnly_status_display_option = get_term_meta($hvnly_status->term_id, 'hvnly_badge_display_option', true);
    if (!empty($hvnly_status_display_option)) {
        $hvnly_display_all = true;
        break;
    }
}

// Separate statuses based on display option
if ($hvnly_display_all) {
    // Display all statuses
    $hvnly_display_statuses = $hvnly_statuses;
    $hvnly_remaining_statuses = array();
} else {
    // Use dropdown behavior
    $hvnly_first_status = $hvnly_statuses[0];
    $hvnly_remaining_statuses = array_slice($hvnly_statuses, 1);
    $hvnly_display_statuses = array($hvnly_first_status);
}
?>


<div class="hvnly-thumbnail-status--labels <?php echo $hvnly_display_all ? 'hvnly-status-display-all' : ''; ?>">
    <?php foreach ($hvnly_display_statuses as $hvnly_status): 
        $hvnly_background_color = function_exists( 'hvnly_get_term_badge_background_color' )
            ? hvnly_get_term_badge_background_color( $hvnly_status->term_id )
            : get_term_meta( $hvnly_status->term_id, 'hvnly_badge_background_color', true );
        $hvnly_icon_data = get_term_meta($hvnly_status->term_id, '_hvnly_advanced_icon_data', true);
        $hvnly_icon_class = is_array($hvnly_icon_data) && isset($hvnly_icon_data['class']) ? $hvnly_icon_data['class'] : '';
    ?>
        <div class="hvnly-thumbnail-status hvnly-status-<?php echo esc_attr($hvnly_status->slug); ?>" 
             <?php if (!empty($hvnly_background_color)): ?>style="background-color: <?php echo esc_attr($hvnly_background_color); ?>"<?php endif; ?>>
            
            <?php if (!empty($hvnly_icon_class)): ?>
                <i class="<?php echo esc_attr($hvnly_icon_class); ?>"></i>
            <?php endif; ?>
            
            <span class="hvnly-status-text"><?php echo function_exists( 'hvnly_translate_ui' ) ? esc_html( hvnly_translate_ui( $hvnly_status->name ) ) : esc_html( $hvnly_status->name ); ?></span>
        </div>
    <?php endforeach; ?>

    <?php if (!$hvnly_display_all && !empty($hvnly_remaining_statuses)): ?>
        <div class="hvnly-status-dropdown-container">
            <div class="hvnly-status-more-indicator" title="<?php echo esc_attr__('Show more statuses', 'havenlytics'); ?>">
                +<?php echo count($hvnly_remaining_statuses); ?>
            </div>
            
            <div class="hvnly-status-dropdown">
                <?php foreach ($hvnly_remaining_statuses as $hvnly_status): 
                    $hvnly_background_color = function_exists( 'hvnly_get_term_badge_background_color' )
            ? hvnly_get_term_badge_background_color( $hvnly_status->term_id )
            : get_term_meta( $hvnly_status->term_id, 'hvnly_badge_background_color', true );
                    $hvnly_icon_data = get_term_meta($hvnly_status->term_id, '_hvnly_advanced_icon_data', true);
                    $hvnly_icon_class = is_array($hvnly_icon_data) && isset($hvnly_icon_data['class']) ? $hvnly_icon_data['class'] : '';
                ?>
                    <div class="hvnly-thumbnail-status hvnly-status-<?php echo esc_attr($hvnly_status->slug); ?>" 
                         <?php if (!empty($hvnly_background_color)): ?>style="background-color: <?php echo esc_attr($hvnly_background_color); ?>"<?php endif; ?>>
                        
                        <?php if (!empty($hvnly_icon_class)): ?>
                            <i class="<?php echo esc_attr($hvnly_icon_class); ?>"></i>
                        <?php endif; ?>
                        
                        <span class="hvnly-status-text"><?php echo function_exists( 'hvnly_translate_ui' ) ? esc_html( hvnly_translate_ui( $hvnly_status->name ) ) : esc_html( $hvnly_status->name ); ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
</div>