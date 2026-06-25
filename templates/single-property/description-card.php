<?php
/**
 * Description Card Template
 * 
 * This template displays the property description card.
 * 
 * HOW TO OVERRIDE THIS TEMPLATE:
 * 1. Copy this file from: wp-content/plugins/havenlytics/templates/single-property/description-card.php
 * 2. Paste it to your theme at: wp-content/themes/your-theme/havenlytics/single-property/description-card.php
 * 3. Modify the copied file to customize the description card
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
$hvnly_content     = get_the_content( null, false, $hvnly_property_id );
?>
<!-- Property Details -->

<?php if ( $hvnly_content ) : ?>
	<div class="hvnly-property-single__description">
		<?php 
		/**
		 * Apply WordPress core the_content filter to process property description.
		 * This is a standard WordPress core hook, not a custom plugin hook.
		 * @link https://developer.wordpress.org/reference/hooks/the_content/
		 */
		echo apply_filters( 'the_content', $hvnly_content ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
		?>
	</div>
<?php else : ?>
	<p class="hvnly-property-single__no-description">
		<?php esc_html_e( 'No description available for this property.', 'havenlytics' ); ?>
	</p>
<?php endif; ?>