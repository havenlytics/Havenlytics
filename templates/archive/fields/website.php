<?php
/**
 * Website Field Template
 * 
 * This template displays the website URL for a property.
 * 
 * HOW TO OVERRIDE THIS TEMPLATE:
 * 1. Copy this file from: wp-content/plugins/havenlytics/templates/archive/fields/website.php
 * 2. Paste it to your theme at: wp-content/themes/your-theme/havenlytics/archive/fields/website.php
 * 3. Modify the copied file to customize the website URL display
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
$hvnly_property_data = $property_data ?? hvnly_get_property_data($hvnly_property_id);
$hvnly_mode = $mode ?? 'default';

$hvnly_website = get_post_meta($hvnly_property_id, 'preset_hvnly_property_field_website', true);
?>

<?php if ($hvnly_website): ?>
    <div class="hvnly-property-field-website hvnly-property-contact-info-mode-<?php echo esc_attr($hvnly_mode); ?>">
        <div class="hvnly-property-content-feature-icon">
            <i class="fas fa-globe"></i>
        </div>
        <a href="<?php echo esc_url($hvnly_website); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html($hvnly_website); ?></a>
    </div>
<?php endif; ?>