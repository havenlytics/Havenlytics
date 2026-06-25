<?php
/**
 * Checkbox Repeater Field Template
 * 
 * This template displays the checkbox repeater field values with icons.
 * 
 * HOW TO OVERRIDE THIS TEMPLATE:
 * 1. Copy this file from: wp-content/plugins/havenlytics/templates/single-property/fields/default/checkbox-repeater.php
 * 2. Paste it to your theme at: wp-content/themes/your-theme/havenlytics/single-property/fields/default/checkbox-repeater.php
 * 3. Modify the copied file to customize the checkbox repeater field display
 * 
 * @package     Havenlytics
 * @subpackage  Templates/single-property/fields/default
 * @since       2.0.0
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Prefix all variables with hvnly_ to avoid global namespace conflicts
$hvnly_field       = $args['field'] ?? [];
$hvnly_value       = $args['value'] ?? '';
$hvnly_label       = $args['field_label'] ?? $hvnly_field['label'] ?? '';
$hvnly_name        = $args['field_name'] ?? $hvnly_field['name'] ?? '';

// Decode the value if it's a JSON string
$hvnly_items = [];
if ( is_string( $hvnly_value ) ) {
    // Try to decode JSON
    $hvnly_decoded = json_decode( $hvnly_value, true );
    if ( is_array( $hvnly_decoded ) ) {
        $hvnly_items = $hvnly_decoded;
    } else {
        // Try to unserialize if it's serialized
        $hvnly_unserialized = maybe_unserialize( $hvnly_value );
        if ( is_array( $hvnly_unserialized ) ) {
            $hvnly_items = $hvnly_unserialized;
        } else {
            // Treat as single item
            $hvnly_items = [ $hvnly_value ];
        }
    }
} elseif ( is_array( $hvnly_value ) ) {
    $hvnly_items = $hvnly_value;
}

// Filter out empty values
$hvnly_items = array_filter( $hvnly_items, function( $item ) {
    return ! empty( trim( $item ) );
});

// Reset array keys
$hvnly_items = array_values( $hvnly_items );

if ( empty( $hvnly_items ) ) {
    return;
}

// Icon mapping based on keywords - Updated for Font Awesome
if ( ! function_exists( 'hvnly_get_feature_icon' ) ) {
    function hvnly_get_feature_icon( $feature ) {
        $feature_lower = strtolower( $feature );
        
        $icon_map = [
            // Air & Temperature
            'air' => 'fas fa-wind',
            'ac' => 'far fa-snowflake',
            'hvac' => 'fas fa-thermometer-half',
            'heating' => 'fas fa-temperature-high',
            'cooling' => 'far fa-snowflake',
            'temperature' => 'fas fa-thermometer-half',
            
            // Kitchen
            'kitchen' => 'fas fa-utensils',
            'oven' => 'fas fa-oven',
            'stove' => 'fas fa-cooktop',
            'microwave' => 'fas fa-microwave',
            'dishwasher' => 'fas fa-dishwasher',
            'refrigerator' => 'fas fa-refrigerator',
            'pantry' => 'fas fa-cupboard',
            
            // Bathroom
            'bath' => 'fas fa-bath',
            'shower' => 'fas fa-shower',
            'toilet' => 'fas fa-toilet',
            'jacuzzi' => 'fas fa-hot-tub',
            'spa' => 'fas fa-spa',
            
            // Bedroom
            'bed' => 'fas fa-bed',
            'master' => 'fas fa-bed',
            'closet' => 'fas fa-wardrobe',
            'walk-in' => 'fas fa-wardrobe',
            
            // Living Areas
            'living' => 'fas fa-couch',
            'family' => 'fas fa-couch',
            'dining' => 'fas fa-utensils',
            'fireplace' => 'fas fa-fire',
            
            // Flooring
            'floor' => 'fas fa-border-all',
            'hardwood' => 'fas fa-border-all',
            'tile' => 'fas fa-border-all',
            'carpet' => 'fas fa-border-all',
            
            // Windows & Doors
            'window' => 'fas fa-window-maximize',
            'door' => 'fas fa-door-open',
            'sliding' => 'fas fa-door-open',
            'french' => 'fas fa-door-open',
            
            // Outdoor
            'pool' => 'fas fa-swimming-pool',
            'garden' => 'fas fa-seedling',
            'yard' => 'fas fa-tree',
            'landscape' => 'fas fa-mountain',
            'patio' => 'fas fa-umbrella-beach',
            'deck' => 'fas fa-umbrella-beach',
            'balcony' => 'fas fa-umbrella-beach',
            'terrace' => 'fas fa-umbrella-beach',
            'porch' => 'fas fa-umbrella-beach',
            
            // Garage & Parking
            'garage' => 'fas fa-warehouse',
            'parking' => 'fas fa-parking',
            'driveway' => 'fas fa-parking',
            
            // Security
            'security' => 'fas fa-shield-alt',
            'alarm' => 'fas fa-shield-alt',
            'camera' => 'fas fa-video',
            'gated' => 'fas fa-shield-alt',
            
            // Technology
            'smart' => 'fas fa-microchip',
            'wifi' => 'fas fa-wifi',
            'internet' => 'fas fa-wifi',
            'automation' => 'fas fa-robot',
            
            // Storage
            'storage' => 'fas fa-box',
            'shed' => 'fas fa-box',
            'basement' => 'fas fa-box',
            'attic' => 'fas fa-box',
            
            // Energy
            'solar' => 'fas fa-solar-panel',
            'energy' => 'fas fa-bolt',
            'efficient' => 'fas fa-leaf',
            
            // Water
            'water' => 'fas fa-water',
            'fountain' => 'fas fa-water',
            'pond' => 'fas fa-water',
            
            // Sports & Recreation
            'tennis' => 'fas fa-table-tennis',
            'basketball' => 'fas fa-basketball-ball',
            'gym' => 'fas fa-dumbbell',
            'fitness' => 'fas fa-heartbeat',
            'game' => 'fas fa-gamepad',
            'theater' => 'fas fa-film',
            'media' => 'fas fa-film',
            
            // Pets
            'pet' => 'fas fa-paw',
            'dog' => 'fas fa-paw',
            'cat' => 'fas fa-paw',
            
            // Miscellaneous
            'furnished' => 'fas fa-couch',
            'view' => 'fas fa-eye',
            'mountain' => 'fas fa-mountain',
            'ocean' => 'fas fa-water',
            'lake' => 'fas fa-water',
            'city' => 'fas fa-city',
            'elevator' => 'fas fa-elevator',
            'laundry' => 'fas fa-tshirt',
            'washer' => 'fas fa-tshirt',
            'dryer' => 'fas fa-tshirt',
        ];
        
        foreach ( $icon_map as $keyword => $icon ) {
            if ( strpos( $feature_lower, $keyword ) !== false ) {
                return $icon;
            }
        }
        
        // Default icon
        return 'fas fa-check-circle';
    }
}
?>

<?php if ( ! empty( $hvnly_label ) ) : ?>
    <h3 class="hvnly-property-single__features-title"><?php echo esc_html( $hvnly_label ); ?></h3>
<?php endif; ?>

<div class="hvnly-property-single__features-grid hvnly-property-single__features-grid--icons">
    <?php foreach ( $hvnly_items as $hvnly_item ) : 
        $hvnly_icon = hvnly_get_feature_icon( $hvnly_item );
    ?>
    <div class="hvnly-property-single__feature-card">
        <div class="hvnly-property-single__feature-icon">
            <i class="<?php echo esc_attr( $hvnly_icon ); ?>" aria-hidden="true"></i>
        </div>
        <span class="hvnly-property-single__feature-text"><?php echo esc_html( $hvnly_item ); ?></span>
    </div>
    <?php endforeach; ?>
</div>

<style>
/* Features Grid with Icons - all your existing CSS remains exactly the same */
.hvnly-property-single__features-title {
    font-size: 1.3rem;
    font-weight: 600;
    margin: 30px 0 20px 0;
    color: var(--hvnly-text-primary, #2d3748);
    position: relative;
    padding-bottom: 10px;
}

.hvnly-property-single__features-title::after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 0;
    width: 60px;
    height: 2px;
    background: var(--hvnly-primary-color);
    border-radius: 2px;
}

.hvnly-property-single__features-grid--icons {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 16px;
    margin: 20px 0 30px;
}

@media (min-width: 1024px) {
    .hvnly-property-single__features-grid--icons {
        grid-template-columns: repeat(3, 1fr);
        gap: 20px;
    }
}

@media (max-width: 768px) {
    .hvnly-property-single__features-grid--icons {
        grid-template-columns: 1fr;
        gap: 12px;
    }
}

.hvnly-property-single__feature-card {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 16px 18px;
    background: #ffffff;
    border: 1px solid rgba(var(--hvnly-primary-rgb), 0.1);
    border-radius: 12px;
    transition: all 0.3s ease;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.02);
}

.hvnly-property-single__feature-card:hover {
    transform: translateY(-3px);
    border-color: var(--hvnly-primary-color);
    box-shadow: 0 10px 25px -5px rgba(var(--hvnly-primary-rgb), 0.15);
}

.hvnly-property-single__feature-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 40px;
    height: 40px;
    background: linear-gradient(135deg, rgba(var(--hvnly-primary-rgb), 0.1) 0%, rgba(var(--hvnly-primary-rgb), 0.05) 100%);
    border-radius: 10px;
    color: var(--hvnly-primary-color);
    flex-shrink: 0;
    transition: all 0.3s ease;
}

.hvnly-property-single__feature-card:hover .hvnly-property-single__feature-icon {
    background: var(--hvnly-primary-color);
    color: white;
    transform: scale(1.05);
}

/* Updated styles for Font Awesome icons */
.hvnly-property-single__feature-icon i {
    font-size: 20px;
    line-height: 1;
    width: 22px;
    height: 22px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.hvnly-property-single__feature-text {
    font-size: 0.95rem;
    font-weight: 500;
    color: var(--hvnly-text-primary, #2d3748);
    line-height: 1.4;
    word-break: break-word;
    flex: 1;
}

/* Optional: Different style with colored backgrounds */
.hvnly-property-single__feature-card--colored {
    background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
}

.hvnly-property-single__feature-card--colored .hvnly-property-single__feature-icon {
    background: var(--hvnly-primary-color);
    color: white;
}

/* Minimal style */
.hvnly-property-single__features-grid--minimal .hvnly-property-single__feature-card {
    background: transparent;
    border: none;
    border-bottom: 1px solid rgba(var(--hvnly-primary-rgb), 0.08);
    border-radius: 0;
    padding: 12px 0;
    box-shadow: none;
}

.hvnly-property-single__features-grid--minimal .hvnly-property-single__feature-card:hover {
    transform: translateX(5px);
    border-bottom-color: var(--hvnly-primary-color);
}

/* Dark mode support */
@media (prefers-color-scheme: dark) {
    .hvnly-property-single__feature-card {
        background: rgba(255, 255, 255, 0.03);
        border-color: rgba(255, 255, 255, 0.1);
    }
    
    .hvnly-property-single__feature-text {
        color: #e2e8f0;
    }
}

/* Font Awesome specific fixes */
.hvnly-property-single__feature-icon i.fas,
.hvnly-property-single__feature-icon i.far,
.hvnly-property-single__feature-icon i.fab {
    transition: all 0.3s ease;
}

.hvnly-property-single__feature-card:hover .hvnly-property-single__feature-icon i {
    color: white;
}
</style>