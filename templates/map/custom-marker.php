<?php
/**
 * Custom Marker Template
 * 
 * This template displays a custom marker for the map.
 * 
 * HOW TO OVERRIDE THIS TEMPLATE:
 * 1. Copy this file from: wp-content/plugins/havenlytics/templates/map/custom-marker.php
 * 2. Paste it to your theme at: wp-content/themes/your-theme/havenlytics/map/custom-marker.php
 * 3. Modify the copied file to customize the marker display
 * 
 * @package     Havenlytics
 * @subpackage  Templates/map
 * @since       2.0.0
 */

if (!defined('ABSPATH')) exit;

// Prefix all variables with hvnly_ to avoid global namespace conflicts
$hvnly_type = $args['type'] ?? 'leaflet'; // 'leaflet' or 'google'
$hvnly_property_id = $args['property_id'] ?? 0;
?>

<?php if ($hvnly_type === 'leaflet'): ?>
    <div class="custom-leaflet-marker">
        <div class="custom-marker-pin">
            <div class="marker-pin"></div>
            <div class="marker-shadow"></div>
        </div>
    </div>
<?php else: ?>
    <!-- Google Maps uses simple markers by default -->
    <div class="custom-google-marker"></div>
<?php endif; ?>