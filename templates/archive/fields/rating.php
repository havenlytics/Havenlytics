<?php
/**
 * Rating Field Template
 *
 * This template displays the rating for a property.
 *
 * HOW TO OVERRIDE THIS TEMPLATE:
 * 1. Copy this file from: wp-content/plugins/havenlytics/templates/archive/fields/rating.php
 * 2. Paste it to your theme at: wp-content/themes/your-theme/havenlytics/archive/fields/rating.php
 * 3. Modify the copied file to customize the rating display
 *
 * @package     Havenlytics
 * @subpackage  Templates/archive/Fields
 * @since       2.0.0
 */


if (!defined('ABSPATH')) {
    exit;
}

// Sprint 31D: guard against render contexts that do not pass $mode
// (every other field template carries the same fallback).
$mode = isset( $mode ) ? $mode : 'default';

?>

<div class="hvnly-property-field-rating-star hvnly-property-field-mode-<?php echo esc_attr( $mode ); ?>">
	<div class="hvnly-property-rating-star">
		<i class="fas fa-star" aria-hidden="true"></i>
		<?php // Sprint 31D: removed the "will be soon" placeholder stub that shipped as visible text. ?>
		<span class="hvnly-property-rating-star-text"><span class="screen-reader-text"><?php esc_html_e( 'Rating', 'havenlytics' ); ?></span></span>
	</div>
</div>
