<?php
/**
 * Video Card Template
 * 
 * This template displays the video card for single property pages.
 * 
 * HOW TO OVERRIDE THIS TEMPLATE:
 * 1. Copy this file from: wp-content/plugins/havenlytics/templates/single-property/video-card.php
 * 2. Paste it to your theme at: wp-content/themes/your-theme/havenlytics/single-property/video-card.php
 * 3. Modify the copied file to customize the video card
 * 
 * @package     Havenlytics
 * @subpackage  Templates/single-property/
 * @since       2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Get data from renderer
$hvnly_property_id = $args['property_id'] ?? get_the_ID();
$hvnly_fields = $args['fields'] ?? array();
$hvnly_values = $args['values'] ?? array();
$hvnly_group_base_id = $args['group_base_id'] ?? '';
$hvnly_group_id = $args['group_id'] ?? '';
$hvnly_title_key = $args['title_key'] ?? '';
$hvnly_url_key = $args['url_key'] ?? '';
$hvnly_thumbnail_key = $args['thumbnail_key'] ?? '';

// Get values - use the exact keys passed from renderer
$hvnly_title = $hvnly_values['title'] ?? '';
$hvnly_url = $hvnly_values['url'] ?? '';
$hvnly_thumbnail = $hvnly_values['thumbnail'] ?? '';

// If no URL found, don't render
if ( empty( $hvnly_url ) ) {
    return;
}

// Determine video type and extract ID
$hvnly_video_source = '';
$hvnly_video_id = '';
$hvnly_is_valid_video = false;

// Check if it's a YouTube URL
if ( strpos( $hvnly_url, 'youtube.com' ) !== false || strpos( $hvnly_url, 'youtu.be' ) !== false ) {
    $hvnly_video_source = 'youtube';
    $hvnly_is_valid_video = true;
    
    if ( preg_match( '/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/', $hvnly_url, $hvnly_matches ) ) {
        $hvnly_video_id = $hvnly_matches[1];
    }
}
// Check if it's a Vimeo URL
elseif ( strpos( $hvnly_url, 'vimeo.com' ) !== false ) {
    $hvnly_video_source = 'vimeo';
    $hvnly_is_valid_video = true;
    
    if ( preg_match( '/(?:vimeo\.com\/)([0-9]+)/', $hvnly_url, $hvnly_matches ) ) {
        $hvnly_video_id = $hvnly_matches[1];
    }
}

if ( ! $hvnly_is_valid_video ) {
    return;
}

// If no thumbnail, use a placeholder
if ( empty( $hvnly_thumbnail ) ) {
    $hvnly_thumbnail = HVNLYNAB_ASSETS_URL . 'images/video-placeholder.jpg';
}

// Generate a unique ID for this video card
$hvnly_video_card_id = 'hvnly-video-' . ( $hvnly_group_base_id ?: uniqid() ) . '-' . $hvnly_property_id;
?>
<!-- Property Video Card -->
<div class="hvnly-property-single__video-card" id="<?php echo esc_attr( $hvnly_video_card_id ); ?>"
    data-video-source="<?php echo esc_attr( $hvnly_video_source ); ?>"
    data-video-url="<?php echo esc_attr( $hvnly_url ); ?>" data-video-id="<?php echo esc_attr( $hvnly_video_id ); ?>"
    data-video-title="<?php echo esc_attr( $hvnly_title ); ?>"
    data-group-base-id="<?php echo esc_attr( $hvnly_group_base_id ); ?>"
    data-title-key="<?php echo esc_attr( $hvnly_title_key ); ?>"
    data-url-key="<?php echo esc_attr( $hvnly_url_key ); ?>">

    <div class="hvnly-property-single__video-container">
        <?php if ( $hvnly_thumbnail ) : ?>
        <img src="<?php echo esc_url( $hvnly_thumbnail ); ?>"
            alt="<?php echo esc_attr( $hvnly_title ?: __( 'Property Video', 'havenlytics' ) ); ?>"
            class="hvnly-property-single__video-placeholder" loading="lazy">
        <?php endif; ?>

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