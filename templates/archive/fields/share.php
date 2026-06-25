<?php
/**
 * Share Field Template
 *
 * @package     Havenlytics
 * @subpackage  Templates/archive/Fields
 * @since       2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$hvnly_field         = $args['field'] ?? array();
$hvnly_value         = $args['value'] ?? array();
$hvnly_property_id   = $args['property_id'] ?? 0;
$hvnly_property_data = $args['property_data'] ?? array();

$hvnly_field_id    = $hvnly_field['id'] ?? 'share';
$hvnly_share_style = $hvnly_field['settings']['style'] ?? 'popup';

if ( ! function_exists( 'hvnly_is_social_sharing_enabled' ) || ! hvnly_is_social_sharing_enabled() ) {
	return;
}

if ( ! function_exists( 'hvnly_is_share_button_enabled' ) || ! hvnly_is_share_button_enabled() ) {
	return;
}

$hvnly_enabled_platforms = function_exists( 'hvnly_get_enabled_share_platforms' )
	? hvnly_get_enabled_share_platforms()
	: array();

if ( ! empty( $hvnly_field['settings']['platforms'] ) ) {
	$hvnly_share_platforms = $hvnly_field['settings']['platforms'];
} else {
	$hvnly_share_platforms = $hvnly_enabled_platforms;
}

if ( empty( $hvnly_share_platforms ) ) {
	return;
}

$hvnly_share_url   = $hvnly_value['url'] ?? $hvnly_property_data['permalink'] ?? get_permalink( $hvnly_property_id );
$hvnly_share_title = $hvnly_value['title'] ?? $hvnly_property_data['title'] ?? get_the_title( $hvnly_property_id );
$hvnly_share_image = $hvnly_value['image'] ?? $hvnly_property_data['thumbnail']['large'] ?? '';

$hvnly_email_subject = sprintf(
	/* translators: %s: property title */
	__( 'Check out this property: %s', 'havenlytics' ),
	$hvnly_share_title
);

$hvnly_email_body = sprintf(
	/* translators: 1: property title, 2: property URL, 3: sender name */
	__( "Hi,\n\nI found this property and thought you might be interested:\n\n%1\$s\n\n%2\$s\n\nRegards,\n%3\$s", 'havenlytics' ),
	$hvnly_share_title,
	$hvnly_share_url,
	wp_get_current_user()->display_name ?: 'Visitor'
);

$hvnly_share_data = array(
	'url'         => $hvnly_share_url,
	'title'       => $hvnly_share_title,
	'image'       => $hvnly_share_image,
	'property_id' => $hvnly_property_id,
	'platforms'   => array_values( $hvnly_share_platforms ),
	'mailto_link' => 'mailto:?subject=' . rawurlencode( $hvnly_email_subject ) . '&body=' . rawurlencode( $hvnly_email_body ),
);

if ( function_exists( 'hvnly_boot_share_popup' ) ) {
	hvnly_boot_share_popup();
}
?>
<div class="hvnly-property-share-field hvnly-property-share-style-<?php echo esc_attr( $hvnly_share_style ); ?>"
	data-share-config="<?php echo esc_attr( wp_json_encode( $hvnly_share_data ) ); ?>">

	<button class="hvnly-property-grid-list-share hvnly-property-share-trigger" type="button"
		aria-label="<?php echo esc_attr( sprintf(
			/* translators: %s: property title */
			__( 'Share this property: %s', 'havenlytics' ),
			$hvnly_share_title
		) ); ?>"
		data-property-id="<?php echo esc_attr( $hvnly_property_id ); ?>">
		<i class="fas fa-share-alt" aria-hidden="true"></i>
	</button>
</div>
