<?php
/**
 * Thumbnail Top Left Badge Template
 * 
 * Displays property badges from hvnly_prop_badges taxonomy with dropdown option
 * 
 * HOW TO OVERRIDE THIS TEMPLATE:
 * 1. Copy this file from: wp-content/plugins/havenlytics/templates/archive/fields/badges.php
 * 2. Paste it to your theme at: wp-content/themes/your-theme/havenlytics/archive/fields/badges.php
 * 3. Modify the copied file to customize badge display
 * 
 * AVAILABLE VARIABLES:
 * $property_id - Current property ID
 * 
 * EXAMPLE OVERRIDE:
 * You can change badge layout, colors, icons, or implement carousel display.
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

// Get badge terms from taxonomy
$hvnly_badges = get_the_terms($hvnly_property_id, 'hvnly_prop_badges');

if (empty($hvnly_badges) || is_wp_error($hvnly_badges)) {
    return;
}

// Check if any badge has display all option enabled
$hvnly_display_all = false;
foreach ($hvnly_badges as $hvnly_badge) {
    $hvnly_badge_display_option = get_term_meta($hvnly_badge->term_id, 'hvnly_badge_display_option', true);
    if (!empty($hvnly_badge_display_option)) {
        $hvnly_display_all = true;
        break;
    }
}

// Separate badges based on display option
if ($hvnly_display_all) {
    // Display all badges
    $hvnly_display_badges = $hvnly_badges;
    $hvnly_remaining_badges = array();
} else {
    // Use dropdown behavior
    $hvnly_first_badge = $hvnly_badges[0];
    $hvnly_remaining_badges = array_slice($hvnly_badges, 1);
    $hvnly_display_badges = array($hvnly_first_badge);
}
?>

<div class="hvnly-property-thumbnail-badges--labels<?php echo $hvnly_display_all ? 'hvnly-badges-display-all' : ''; ?>">
    <?php foreach ($hvnly_display_badges as $hvnly_badge): 
        $hvnly_background_color = function_exists( 'hvnly_get_term_badge_background_color' )
            ? hvnly_get_term_badge_background_color( $hvnly_badge->term_id )
            : get_term_meta( $hvnly_badge->term_id, 'hvnly_badge_background_color', true );
        $hvnly_icon_data = get_term_meta($hvnly_badge->term_id, '_hvnly_advanced_icon_data', true);
        $hvnly_icon_class = is_array($hvnly_icon_data) && isset($hvnly_icon_data['class']) ? $hvnly_icon_data['class'] : '';
    ?>
        <div class="hvnly-property-thumbnail-badge hvnly-badge-<?php echo esc_attr($hvnly_badge->slug); ?>" 
             <?php if (!empty($hvnly_background_color)): ?>style="background-color: <?php echo esc_attr($hvnly_background_color); ?>"<?php endif; ?>>
            
            <?php if (!empty($hvnly_icon_class)): ?>
                <i class="<?php echo esc_attr($hvnly_icon_class); ?>"></i>
            <?php endif; ?>
            
            <span class="hvnly-badge-text"><?php echo esc_html($hvnly_badge->name); ?></span>
        </div>
    <?php endforeach; ?>

    <?php if (!$hvnly_display_all && !empty($hvnly_remaining_badges)): ?>
        <div class="hvnly-badge-dropdown-container">
            <div class="hvnly-badge-more-indicator" title="<?php echo esc_attr__('Show more badges', 'havenlytics'); ?>">
                +<?php echo count($hvnly_remaining_badges); ?>
            </div>
            
            <div class="hvnly-badges-dropdown">
                <?php foreach ($hvnly_remaining_badges as $hvnly_badge): 
                    $hvnly_background_color = function_exists( 'hvnly_get_term_badge_background_color' )
            ? hvnly_get_term_badge_background_color( $hvnly_badge->term_id )
            : get_term_meta( $hvnly_badge->term_id, 'hvnly_badge_background_color', true );
                    $hvnly_icon_data = get_term_meta($hvnly_badge->term_id, '_hvnly_advanced_icon_data', true);
                    $hvnly_icon_class = is_array($hvnly_icon_data) && isset($hvnly_icon_data['class']) ? $hvnly_icon_data['class'] : '';
                ?>
                    <div class="hvnly-property-thumbnail-badge hvnly-badge-<?php echo esc_attr($hvnly_badge->slug); ?>" 
                         <?php if (!empty($hvnly_background_color)): ?>style="background-color: <?php echo esc_attr($hvnly_background_color); ?>"<?php endif; ?>>
                        
                        <?php if (!empty($hvnly_icon_class)): ?>
                            <i class="<?php echo esc_attr($hvnly_icon_class); ?>"></i>
                        <?php endif; ?>
                        
                        <span class="hvnly-badge-text"><?php echo esc_html($hvnly_badge->name); ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
</div>