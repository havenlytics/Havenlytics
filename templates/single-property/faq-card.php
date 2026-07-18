<?php
/**
 * FAQ group card template.
 *
 * @package Havenlytics
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$hvnly_property_id = $args['property_id'] ?? get_the_ID();
$hvnly_title       = $args['values']['title'] ?? '';
$hvnly_items       = array();

if ( is_string( $hvnly_title ) && in_array( trim( $hvnly_title ), array( '[]', '{}', 'null' ), true ) ) {
	$hvnly_title = '';
} elseif ( is_array( $hvnly_title ) ) {
	$hvnly_title = '';
}

if ( ! empty( $args['faqs'] ) && is_array( $args['faqs'] ) ) {
	$hvnly_items = $args['faqs'];
} elseif ( ! empty( $args['values']['faqs'] ) ) {
	$hvnly_faq_raw = $args['values']['faqs'];
	if ( is_string( $hvnly_faq_raw ) ) {
		$hvnly_faq_decoded = json_decode( $hvnly_faq_raw, true );
		$hvnly_items       = is_array( $hvnly_faq_decoded ) ? $hvnly_faq_decoded : array();
	} elseif ( is_array( $hvnly_faq_raw ) ) {
		$hvnly_items = $hvnly_faq_raw;
	}
}

if ( empty( $hvnly_items ) ) {
	return;
}

$hvnly_faq_id = 'hvnly-faq-' . absint( $hvnly_property_id ) . '-' . sanitize_html_class( (string) ( $args['group_base_id'] ?? 'faq' ) );
?>
<div class="hvnly-property-single__faq-field" id="<?php echo esc_attr( $hvnly_faq_id ); ?>">
	<?php if ( ! empty( $hvnly_title ) ) : ?>
		<h3 class="hvnly-property-single__faq-title"><?php echo esc_html( $hvnly_title ); ?></h3>
	<?php endif; ?>
	<div class="hvnly-property-single__faq-accordion" role="region" aria-label="<?php echo esc_attr( $hvnly_title ?: __( 'Frequently Asked Questions', 'havenlytics' ) ); ?>">
		<?php foreach ( $hvnly_items as $hvnly_faq_index => $hvnly_faq_item ) : ?>
			<?php
			$hvnly_faq_question = sanitize_text_field( (string) ( $hvnly_faq_item['question'] ?? '' ) );
			$hvnly_faq_answer   = wp_kses_post( (string) ( $hvnly_faq_item['answer'] ?? '' ) );
			if ( '' === $hvnly_faq_question && '' === $hvnly_faq_answer ) {
				continue;
			}
			$hvnly_faq_item_id = $hvnly_faq_id . '-item-' . absint( $hvnly_faq_index );
			?>
			<details class="hvnly-property-single__faq-item" id="<?php echo esc_attr( $hvnly_faq_item_id ); ?>">
				<summary class="hvnly-property-single__faq-question">
					<span class="hvnly-property-single__faq-question-text"><?php echo esc_html( $hvnly_faq_question ); ?></span>
					<span class="hvnly-property-single__faq-chevron" aria-hidden="true"></span>
				</summary>
				<div class="hvnly-property-single__faq-answer"><?php echo $hvnly_faq_answer; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_kses_post above. ?></div>
			</details>
		<?php endforeach; ?>
	</div>
</div>
