<?php
/**
 * Repeater group card template.
 *
 * @package Havenlytics
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$hvnly_property_id = $args['property_id'] ?? get_the_ID();
$hvnly_title       = $args['values']['title'] ?? '';
$hvnly_items       = array();

if ( ! empty( $args['items'] ) && is_array( $args['items'] ) ) {
	$hvnly_items = $args['items'];
} elseif ( ! empty( $args['values']['items'] ) ) {
	$hvnly_repeater_raw = $args['values']['items'];
	if ( is_string( $hvnly_repeater_raw ) ) {
		$hvnly_repeater_decoded = json_decode( $hvnly_repeater_raw, true );
		$hvnly_items            = is_array( $hvnly_repeater_decoded ) ? $hvnly_repeater_decoded : array();
	} elseif ( is_array( $hvnly_repeater_raw ) ) {
		$hvnly_items = $hvnly_repeater_raw;
	}
}

if ( empty( $hvnly_items ) ) {
	return;
}

$hvnly_repeater_id = 'hvnly-repeater-' . absint( $hvnly_property_id ) . '-' . sanitize_html_class( (string) ( $args['group_base_id'] ?? 'repeater' ) );
?>
<div class="hvnly-property-single__repeater-field" id="<?php echo esc_attr( $hvnly_repeater_id ); ?>">
	<?php if ( ! empty( $hvnly_title ) ) : ?>
		<h3 class="hvnly-property-single__repeater-title"><?php echo esc_html( $hvnly_title ); ?></h3>
	<?php endif; ?>
	<div class="hvnly-property-single__repeater-table-wrap">
		<table class="hvnly-property-single__repeater-table">
			<thead>
				<tr>
					<th scope="col" class="hvnly-property-single__repeater-col-title"><?php esc_html_e( 'Title', 'havenlytics' ); ?></th>
					<th scope="col" class="hvnly-property-single__repeater-col-value"><?php esc_html_e( 'Value', 'havenlytics' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $hvnly_items as $hvnly_repeater_item ) : ?>
					<?php
					$hvnly_repeater_title = sanitize_text_field( (string) ( $hvnly_repeater_item['title'] ?? '' ) );
					$hvnly_repeater_value = sanitize_text_field( (string) ( $hvnly_repeater_item['value'] ?? '' ) );
					$hvnly_repeater_icon  = sanitize_text_field( (string) ( $hvnly_repeater_item['icon'] ?? '' ) );
					// Strip literal "fa-" prefix only — never ltrim(..., 'fa-') (character mask corrupts names).
					if ( 0 === strpos( $hvnly_repeater_icon, 'fa-' ) ) {
						$hvnly_repeater_icon = substr( $hvnly_repeater_icon, 3 );
					}
					if ( '' === $hvnly_repeater_title && '' === $hvnly_repeater_value ) {
						continue;
					}
					?>
					<tr class="hvnly-property-single__repeater-row">
						<td class="hvnly-property-single__repeater-cell-title" data-label="<?php esc_attr_e( 'Title', 'havenlytics' ); ?>">
							<span class="hvnly-property-single__repeater-title-wrap">
								<?php if ( $hvnly_repeater_icon ) : ?>
									<span class="hvnly-property-single__repeater-icon" aria-hidden="true"><i class="fas fa-<?php echo esc_attr( $hvnly_repeater_icon ); ?>"></i></span>
								<?php endif; ?>
								<span class="hvnly-property-single__repeater-title-text"><?php echo esc_html( $hvnly_repeater_title ); ?></span>
							</span>
						</td>
						<td class="hvnly-property-single__repeater-cell-value" data-label="<?php esc_attr_e( 'Value', 'havenlytics' ); ?>"><?php echo esc_html( $hvnly_repeater_value ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
</div>
