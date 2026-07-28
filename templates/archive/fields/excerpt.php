<?php
/**
 * Grid Card footer top Template
 * 
 * Displays property excerpt field in the grid card footer top section.
 * 
 * HOW TO OVERRIDE THIS TEMPLATE:
 * 1. Copy this file from: wp-content/plugins/havenlytics/templates/archive/fields/excerpt.php
 * 2. Paste it to your theme at: wp-content/themes/your-theme/havenlytics/archive/fields/excerpt.php
 * 3. Modify the copied file to customize excerpt display
 * 
 * AVAILABLE VARIABLES:
 * $property_id - Current property ID
 * 
 * EXAMPLE OVERRIDE:
 * You can change excerpt layout, colors, icons, or implement carousel display.
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
// Builder field value.text is demo/preview copy only — never use it on the frontend.
$hvnly_excerpt_text = get_the_excerpt( $hvnly_property_id );

// Get settings
$hvnly_word_limit = absint(get_option('hvnly_property_excerpt_word_limit', 20));
$hvnly_enable_fallback = get_option('hvnly_property_enable_content_fallback', true);

// If excerpt is empty and fallback is enabled, use post content
if (empty($hvnly_excerpt_text) && $hvnly_enable_fallback) {
    $hvnly_property_post = get_post($hvnly_property_id);
    if ($hvnly_property_post && !empty($hvnly_property_post->post_content)) {
        $hvnly_excerpt_text = $hvnly_property_post->post_content;
    }
}

// Process the text
if (!empty($hvnly_excerpt_text)) {
    $hvnly_excerpt_text = wp_trim_words($hvnly_excerpt_text, $hvnly_word_limit, '...');
}
?>

<div class="hvnly-property-field-excerpt hvnly-default-field">
    <div class="hvnly-property--grid-list--excerpt">
        <?php echo wp_kses_post($hvnly_excerpt_text); ?>
    </div>
</div>