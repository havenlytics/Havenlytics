<?php
/**
 * Content Property Template
 * 
 * This template displays the content for individual property posts.
 * 
 * HOW TO OVERRIDE THIS TEMPLATE:
 * 1. Copy this file from: wp-content/plugins/havenlytics/templates/content-property.php
 * 2. Paste it to your theme at: wp-content/themes/your-theme/havenlytics/content-property.php
 * 3. Modify the copied file to customize the property page
 * 
 * @package     Havenlytics
 * @subpackage  Templates/
 * @since       2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Prefix all variables with hvnly_ to avoid global namespace conflicts
$hvnly_property_card_renderer = hvnly_get_property_card_renderer();

$hvnly_property_id = get_the_ID();
$hvnly_property_data = hvnly_get_property_data($hvnly_property_id);

// Always use the dynamic renderer
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
echo $hvnly_property_card_renderer->render_property_card_dynamic($hvnly_property_id, $hvnly_property_data);