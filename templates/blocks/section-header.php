<?php
/**
 * Shared premium section header for Havenlytics blocks.
 *
 * Expects $args:
 *   show (bool), subtitle, title, description, align (left|center|right),
 *   button_show (bool), button_text, button_url, button_target (_self|_blank).
 *
 * @package     Havenlytics
 * @subpackage  Templates/blocks
 * @since       3.5.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$hvnly_a = ( isset( $args ) && is_array( $args ) ) ? $args : array();

if ( isset( $hvnly_a['show'] ) && ! $hvnly_a['show'] ) {
	return;
}

$hvnly_subtitle    = isset( $hvnly_a['subtitle'] ) ? (string) $hvnly_a['subtitle'] : '';
$title       = isset( $hvnly_a['title'] ) ? (string) $hvnly_a['title'] : '';
$hvnly_description = isset( $hvnly_a['description'] ) ? (string) $hvnly_a['description'] : '';
$hvnly_align       = isset( $hvnly_a['align'] ) ? sanitize_key( (string) $hvnly_a['align'] ) : 'left';

$hvnly_button_show   = ! empty( $hvnly_a['button_show'] );
$hvnly_button_text   = isset( $hvnly_a['button_text'] ) ? (string) $hvnly_a['button_text'] : '';
$hvnly_button_url    = isset( $hvnly_a['button_url'] ) ? (string) $hvnly_a['button_url'] : '';
$hvnly_button_target = isset( $hvnly_a['button_target'] ) ? (string) $hvnly_a['button_target'] : '_self';

if ( ! in_array( $hvnly_align, array( 'left', 'center', 'right' ), true ) ) {
	$hvnly_align = 'left';
}

if ( ! in_array( $hvnly_button_target, array( '_self', '_blank' ), true ) ) {
	$hvnly_button_target = '_self';
}

$hvnly_has_button = $hvnly_button_show && '' !== $hvnly_button_text && '' !== $hvnly_button_url;

if ( '' === $hvnly_subtitle && '' === $title && '' === $hvnly_description && ! $hvnly_has_button ) {
	return;
}
?>
<div class="hvnly-block-section-header hvnly-block-section-header--<?php echo esc_attr( $hvnly_align ); ?>">
	<div class="hvnly-block-section-header__copy">
		<?php if ( '' !== $hvnly_subtitle ) : ?>
			<span class="hvnly-block-section-header__subtitle"><?php echo esc_html( $hvnly_subtitle ); ?></span>
		<?php endif; ?>
		<?php if ( '' !== $title ) : ?>
			<h2 class="hvnly-block-section-header__title"><?php echo esc_html( $title ); ?></h2>
		<?php endif; ?>
		<?php if ( '' !== $hvnly_description ) : ?>
			<p class="hvnly-block-section-header__desc"><?php echo esc_html( $hvnly_description ); ?></p>
		<?php endif; ?>
	</div>
	<?php if ( $hvnly_has_button ) : ?>
		<div class="hvnly-block-section-header__actions">
			<a
				class="hvnly-block-section-header__button"
				href="<?php echo esc_url( $hvnly_button_url ); ?>"
				<?php echo '_blank' === $hvnly_button_target ? 'target="_blank" rel="noopener noreferrer"' : ''; ?>
			><?php echo esc_html( $hvnly_button_text ); ?></a>
		</div>
	<?php endif; ?>
</div>
