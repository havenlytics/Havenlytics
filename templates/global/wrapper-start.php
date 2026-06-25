<?php
/**
 * Wrapper Start Template
 * 
 * This template displays the start of the wrapper for shortcodes.
 * 
 * HOW TO OVERRIDE THIS TEMPLATE:
 * 1. Copy this file from: wp-content/plugins/havenlytics/templates/global/wrapper-start.php
 * 2. Paste it to your theme at: wp-content/themes/your-theme/havenlytics/global/wrapper-start.php
 * 3. Modify the copied file to customize the wrapper start display
 * 
 * @package     Havenlytics
 * @subpackage  Templates/global
 * @since       2.0.0
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Prefix all variables with hvnly_ to avoid global namespace conflicts
// Main content wrapper classes
$hvnly_wrapper_classes = array('hvnly-content-wrapper');

if (is_singular('hvnly_property')) {
    $hvnly_wrapper_classes[] = 'hvnly-property-single__content-wrapper';
} elseif (is_post_type_archive('hvnly_property') || is_tax(get_object_taxonomies('hvnly_property'))) {
    $hvnly_wrapper_classes[] = 'hvnly-property-archive__content-wrapper';
}

// Apply filter for extensibility
$hvnly_wrapper_classes = apply_filters('hvnly_content_wrapper_classes', $hvnly_wrapper_classes);
$hvnly_wrapper_class = implode(' ', $hvnly_wrapper_classes);

// Sidebar wrapper classes
$hvnly_sidebar_wrapper_classes = array('hvnly-sidebar-wrapper');

if (is_singular('hvnly_property')) {
    $hvnly_sidebar_wrapper_classes[] = 'hvnly-sidebar-wrapper--single-property';
} elseif (is_post_type_archive('hvnly_property') || is_tax(get_object_taxonomies('hvnly_property'))) {
    $hvnly_sidebar_wrapper_classes[] = 'hvnly-sidebar-wrapper--property-archive';
}

// Apply filter for extensibility
$hvnly_sidebar_wrapper_classes = apply_filters('hvnly_sidebar_wrapper_classes', $hvnly_sidebar_wrapper_classes);
$hvnly_sidebar_wrapper_class = implode(' ', $hvnly_sidebar_wrapper_classes);

?>

<main class="<?php echo esc_attr($hvnly_wrapper_class); ?>" role="main">
    <div class="hvnly-container">
        <div class="<?php echo esc_attr($hvnly_sidebar_wrapper_class); ?>">