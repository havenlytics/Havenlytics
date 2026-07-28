<?php
/**
 * Baths Field Template
 * 
 * This template displays the bathrooms count for a property.
 * 
 * HOW TO OVERRIDE THIS TEMPLATE:
 * 1. Copy this file from: wp-content/plugins/havenlytics/templates/archive/fields/baths.php
 * 2. Paste it to your theme at: wp-content/themes/your-theme/havenlytics/archive/fields/baths.php
 * 3. Modify the copied file to customize the bathrooms display
 * 
 * AVAILABLE VARIABLES:
 * $property_id - Current property ID
 * $mode - Render mode: 'preset' (from builder) or 'default'
 * $property_data - Property data array (available in some contexts)
 * $field - Field configuration array (available in preset mode)
 * 
 * EXAMPLE OVERRIDE:
 * You can change icons, add CSS classes, or completely redesign the layout.
 * 
 * @package     Havenlytics
 * @subpackage  Templates/archive/Fields
 * @since       2.0.0
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

// Prefix all variables with hvnly_ to avoid global namespace conflicts
// Get property ID (fallback to current post)
$hvnly_property_id = $property_id ?? get_the_ID();

// Get render mode (preset or default)
$hvnly_mode = $mode ?? 'default';

// Get bathrooms count from property meta
$hvnly_bathrooms = absint(get_post_meta($hvnly_property_id, '_hvnly_property_bathrooms', true));
$hvnly_field    = $field ?? array();

// Show in preset mode OR if there are bathrooms in default mode
if ($hvnly_bathrooms > 0 || $hvnly_mode === 'preset') :
?>
<div class="hvnly-property--grid-list--feature">
    <!-- Bath icon - You can change this to any FontAwesome icon -->
    <i class="fas fa-bath" aria-hidden="true"></i>
    
    <div class="hvnly-property--feature-lists">
        <!-- Display bathroom count if available -->
        <?php if ($hvnly_bathrooms > 0) : ?>
            <span class="hvnly-property-feature-number">
                <?php echo esc_html($hvnly_bathrooms); ?>
            </span>
        <?php endif; ?>
        
        <!-- Bath/Baths label -->
        <span class="hvnly-property-feature-label">
            <?php
            echo esc_html(
                function_exists( 'hvnly_archive_feature_label' )
                    ? hvnly_archive_feature_label( $hvnly_field, 'Bath', 'Baths', $hvnly_bathrooms )
                    : _n( 'Bath', 'Baths', $hvnly_bathrooms > 0 ? $hvnly_bathrooms : 1, 'havenlytics' )
            );
            ?>
        </span>
    </div>
</div>
<?php 
endif; // End if bathrooms or preset mode