<?php
/**
 * Video Card Template
 *
 * Thumbnail priority: uploaded custom → YouTube/Vimeo → placeholder.
 *
 * @package     Havenlytics
 * @subpackage  Templates/single-property/
 * @since       2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$hvnly_property_id   = $args['property_id'] ?? get_the_ID();
$hvnly_fields        = $args['fields'] ?? array();
$hvnly_values        = $args['values'] ?? array();
$hvnly_group_base_id = $args['group_base_id'] ?? '';
$hvnly_group_id      = $args['group_id'] ?? '';
$hvnly_title_key     = $args['title_key'] ?? '';
$hvnly_url_key       = $args['url_key'] ?? '';
$hvnly_thumbnail_key = $args['thumbnail_key'] ?? '';

$hvnly_title     = isset( $hvnly_values['title'] ) ? (string) $hvnly_values['title'] : '';
$hvnly_url       = isset( $hvnly_values['url'] ) ? (string) $hvnly_values['url'] : '';
$hvnly_thumbnail = isset( $hvnly_values['thumbnail'] ) ? $hvnly_values['thumbnail'] : '';

// Never treat empty JSON / array junk as a real title.
if ( in_array( trim( $hvnly_title ), array( '[]', '{}', 'null' ), true ) ) {
	$hvnly_title = '';
}

if ( '' === trim( $hvnly_url ) ) {
	return;
}

$hvnly_video_source  = '';
$hvnly_video_id      = '';
$hvnly_is_valid_video = false;

if ( false !== strpos( $hvnly_url, 'youtube.com' ) || false !== strpos( $hvnly_url, 'youtu.be' ) ) {
	$hvnly_video_source   = 'youtube';
	$hvnly_is_valid_video = true;
	if ( preg_match( '/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/', $hvnly_url, $hvnly_matches ) ) {
		$hvnly_video_id = $hvnly_matches[1];
	}
} elseif ( false !== strpos( $hvnly_url, 'vimeo.com' ) ) {
	$hvnly_video_source   = 'vimeo';
	$hvnly_is_valid_video = true;
	if ( preg_match( '/(?:vimeo\.com\/)([0-9]+)/', $hvnly_url, $hvnly_matches ) ) {
		$hvnly_video_id = $hvnly_matches[1];
	}
}

if ( ! $hvnly_is_valid_video ) {
	return;
}

/**
 * Resolve display thumbnail URL.
 *
 * @param mixed  $raw        Stored thumbnail (URL or attachment ID).
 * @param string $source     youtube|vimeo.
 * @param string $video_id   Provider id.
 * @return string
 */
$hvnly_resolve_thumb = static function ( $raw, $source, $video_id ) {
	$candidate = '';

	if ( is_numeric( $raw ) ) {
		$att_id = absint( $raw );
		if ( $att_id > 0 ) {
			$url = wp_get_attachment_image_url( $att_id, 'large' );
			if ( ! $url ) {
				$url = wp_get_attachment_url( $att_id );
			}
			if ( is_string( $url ) && '' !== $url ) {
				$candidate = $url;
			}
		}
	} elseif ( is_string( $raw ) ) {
		$trim = trim( $raw );
		if ( '' !== $trim && ! in_array( $trim, array( '[]', '{}', 'null' ), true ) ) {
			if ( is_numeric( $trim ) ) {
				$att_id = absint( $trim );
				$url    = $att_id ? wp_get_attachment_image_url( $att_id, 'large' ) : false;
				if ( ! $url && $att_id ) {
					$url = wp_get_attachment_url( $att_id );
				}
				$candidate = is_string( $url ) ? $url : '';
			} elseif ( preg_match( '#^https?://#i', $trim ) ) {
				$candidate = $trim;
			}
		}
	}

	if ( '' !== $candidate ) {
		return $candidate;
	}

	if ( 'youtube' === $source && '' !== $video_id ) {
		return 'https://i.ytimg.com/vi/' . rawurlencode( $video_id ) . '/hqdefault.jpg';
	}

	if ( 'vimeo' === $source && '' !== $video_id ) {
		// No sync API call — leave provider-agnostic placeholder for Vimeo without custom thumb.
		return '';
	}

	return '';
};

$hvnly_thumbnail = $hvnly_resolve_thumb( $hvnly_thumbnail, $hvnly_video_source, $hvnly_video_id );

if ( '' === $hvnly_thumbnail ) {
	$hvnly_thumbnail = HVNLYNAB_ASSETS_URL . 'images/video-placeholder.jpg';
}

$hvnly_video_card_id = 'hvnly-video-' . ( $hvnly_group_base_id ?: uniqid() ) . '-' . $hvnly_property_id;
?>
<!-- Property Video Card -->
<div class="hvnly-property-single__video-card" id="<?php echo esc_attr( $hvnly_video_card_id ); ?>"
	data-video-source="<?php echo esc_attr( $hvnly_video_source ); ?>"
	data-video-url="<?php echo esc_attr( $hvnly_url ); ?>" data-video-id="<?php echo esc_attr( $hvnly_video_id ); ?>"
	data-video-title="<?php echo esc_attr( $hvnly_title ); ?>"
	data-group-base-id="<?php echo esc_attr( $hvnly_group_base_id ); ?>"
	data-title-key="<?php echo esc_attr( $hvnly_title_key ); ?>"
	data-url-key="<?php echo esc_attr( $hvnly_url_key ); ?>"
	data-thumbnail-key="<?php echo esc_attr( $hvnly_thumbnail_key ); ?>">

	<div class="hvnly-property-single__video-container">
		<img src="<?php echo esc_url( $hvnly_thumbnail ); ?>"
			alt="<?php echo esc_attr( $hvnly_title ?: __( 'Property Video', 'havenlytics' ) ); ?>"
			class="hvnly-property-single__video-thumb hvnly-property-single__video-placeholder" loading="lazy">

		<div class="hvnly-property-single__video-overlay">
			<div class="hvnly-property-single__video-play-btn">
				<svg class="hvnly-icon hvnly-icon-thin">
					<use xlink:href="#hvnly-play"></use>
				</svg>
			</div>

			<?php if ( $hvnly_title ) : ?>
			<h3 class="hvnly-property-single__video-title"><?php echo esc_html( $hvnly_title ); ?></h3>
			<?php else : ?>
			<h3 class="hvnly-property-single__video-title"><?php esc_html_e( 'Virtual Tour', 'havenlytics' ); ?></h3>
			<?php endif; ?>
		</div>
	</div>
</div>
