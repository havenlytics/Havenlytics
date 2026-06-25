<?php
/**
 * Beds Field Template
 * 
 * This template displays the bedrooms count for a property.
 * 
 * HOW TO OVERRIDE THIS TEMPLATE:
 * 1. Copy this file from: wp-content/plugins/havenlytics/templates/archive/fields/beds.php
 * 2. Paste it to your theme at: wp-content/themes/your-theme/havenlytics/archive/fields/beds.php
 * 3. Modify the copied file to customize the bedrooms display
 * 
 * AVAILABLE VARIABLES:
 * $property_id - Current property ID
 * $mode - Render mode: 'preset' (from builder) or 'default'
 * $property_data - Property data array (available in some contexts)
 * $field - Field configuration array (available in preset mode)
 * 
 * CUSTOMIZATION IDEAS:
 * - Change the bed icon (use any FontAwesome class)
 * - Add additional bedroom data (sizes, types, etc.)
 * - Change the HTML structure
 * - Add custom CSS classes
 * - Show different labels based on bedroom count
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

// Get bedrooms count from property meta
$hvnly_bedrooms = absint(get_post_meta($hvnly_property_id, '_hvnly_property_bedrooms', true));

// Show in preset mode OR if there are bedrooms in default mode
if ($hvnly_bedrooms > 0 || $hvnly_mode === 'preset') :
?>
<div class="hvnly-property--grid-list--feature">
    <!-- Bed icon - You can change this to any FontAwesome icon -->
    <i class="fas fa-bed" aria-hidden="true"></i>
    
    <div class="hvnly-property--feature-lists">
        <!-- Display bedroom count if available -->
        <?php if ($hvnly_bedrooms > 0) : ?>
            <span class="hvnly-property-feature-number">
                <?php echo esc_html($hvnly_bedrooms); ?>
            </span>
        <?php endif; ?>
        
        <!-- Bed/Beds label with proper pluralization -->
        <span class="hvnly-property-feature-label">
            <?php 
            /* translators: %s: number of bedrooms */
            echo esc_html( 
                _n( 
                    'Bed', 
                    'Beds', 
                    $hvnly_bedrooms > 0 ? $hvnly_bedrooms : 1, 
                    'havenlytics' 
                ) 
            ); 
            ?>
        </span>
    </div>
</div>
<?php 
endif; // End if bedrooms or preset mode