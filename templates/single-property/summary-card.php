<?php
/**
 * Summary Card Template
 * 
 * This template displays the summary card for single property pages.
 * 
 * HOW TO OVERRIDE THIS TEMPLATE:
 * 1. Copy this file from: wp-content/plugins/havenlytics/templates/single-property/summary-card.php
 * 2. Paste it to your theme at: wp-content/themes/your-theme/havenlytics/single-property/summary-card.php
 * 3. Modify the copied file to customize the summary card
 * 
 * @package     Havenlytics
 * @subpackage  Templates/single-property/
 * @since       2.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Prefix all variables with hvnly_ to avoid global namespace conflicts
$hvnly_property_id = get_the_ID();

// Get the raw excerpt from post (not auto-generated).
$hvnly_excerpt = get_post_field( 'post_excerpt', $hvnly_property_id );

// Remove all HTML tags, trim whitespace, and check if truly empty.
// Using wp_strip_all_tags() instead of strip_tags()
$hvnly_excerpt_clean = trim( wp_strip_all_tags( $hvnly_excerpt ) );

// If excerpt is empty after stripping HTML and trimming, don't show anything.
if ( empty( $hvnly_excerpt_clean ) ) {
	return;
}

// Get the original excerpt for display (with HTML if any).
$hvnly_excerpt = get_post_field( 'post_excerpt', $hvnly_property_id );
?>
<!-- Property Summary Details -->
<div class="hvnly-property-single__summary-description">
	<?php echo wp_kses_post( $hvnly_excerpt ); ?>
</div>