<?php
/**
 * Gallery Card Template
 * 
 * This template displays the gallery card for single property pages.
 * 
 * HOW TO OVERRIDE THIS TEMPLATE:
 * 1. Copy this file from: wp-content/plugins/havenlytics/templates/single-property/gallery-card.php
 * 2. Paste it to your theme at: wp-content/themes/your-theme/havenlytics/single-property/gallery-card.php
 * 3. Modify the copied file to customize the gallery card
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
$hvnly_group_fields = $args['fields'] ?? array();
$hvnly_values = $args['values'] ?? array();
$hvnly_group_base_id = $args['group_base_id'] ?? '';
$hvnly_group_id = $args['group_id'] ?? '';
$hvnly_title_key = $args['title_key'] ?? '';
$hvnly_images_key = $args['images_key'] ?? '';

// Get values - use the exact keys passed from renderer
$hvnly_title = $hvnly_values['title'] ?? '';
$hvnly_images_value = $hvnly_values['images'] ?? '';

// Process images - convert string to array if needed
$hvnly_images = array();
if ( is_string( $hvnly_images_value ) ) {
    if ( strpos( $hvnly_images_value, ',' ) !== false ) {
        $hvnly_images = array_map( 'trim', explode( ',', $hvnly_images_value ) );
    } elseif ( strpos( $hvnly_images_value, '[' ) === 0 ) {
        $hvnly_decoded = json_decode( $hvnly_images_value, true );
        if ( is_array( $hvnly_decoded ) ) {
            $hvnly_images = $hvnly_decoded;
        }
    } elseif ( is_numeric( $hvnly_images_value ) ) {
        $hvnly_images = array( $hvnly_images_value );
    }
} elseif ( is_array( $hvnly_images_value ) ) {
    $hvnly_images = $hvnly_images_value;
}

// Filter out empty values and convert to integers
$hvnly_images = array_filter( array_map( 'absint', $hvnly_images ) );

// Don't show gallery if no images
if ( empty( $hvnly_images ) ) {
    return;
}

// Generate a unique gallery ID
$hvnly_gallery_id = 'hvnly-gallery-' . $hvnly_property_id . '-' . ( $hvnly_group_base_id ?: uniqid() );
$hvnly_has_gallery = ! empty( $hvnly_images );
?>
<!-- Property Gallery Field Card -->
<div class="hvnly-property-single__gallery-field" id="<?php echo esc_attr( $hvnly_gallery_id ); ?>"
    data-gallery-id="<?php echo esc_attr( $hvnly_gallery_id ); ?>"
    data-property-id="<?php echo esc_attr( $hvnly_property_id ); ?>"
    data-group-base-id="<?php echo esc_attr( $hvnly_group_base_id ); ?>"
    data-images-key="<?php echo esc_attr( $hvnly_images_key ); ?>">

    <?php if ( ! empty( $hvnly_title ) ) : ?>
    <div class="hvnly-property-single__gallery-title"><?php echo function_exists( 'hvnly_translate_ui' ) ? esc_html( hvnly_translate_ui( $hvnly_title ?? $hvnly_label ) ) : esc_html( $hvnly_title ?? $hvnly_label ); ?></div>
    <?php endif; ?>

    <?php if ( $hvnly_has_gallery ) : ?>
    <div class="hvnly-property-single__gallery-grid">
        <?php 
            $hvnly_image_count = 0;
            foreach ( $hvnly_images as $hvnly_image_id ) : 
                if ( empty( $hvnly_image_id ) ) continue;
                
                $hvnly_full_image_url = wp_get_attachment_image_url( $hvnly_image_id, 'full' );
                $hvnly_medium_image_url = wp_get_attachment_image_url( $hvnly_image_id, 'medium' );
                $hvnly_image_title = get_the_title( $hvnly_image_id );
                $hvnly_image_alt = get_post_meta( $hvnly_image_id, '_wp_attachment_image_alt', true ) ?: $hvnly_image_title;
                
                if ( $hvnly_full_image_url ) : 
                    $hvnly_image_count++;
                    if ( $hvnly_image_count <= 50 ) :
        ?>
        <div class="hvnly-property-single__gallery-item" data-gallery-id="<?php echo esc_attr( $hvnly_gallery_id ); ?>"
            data-image-index="<?php echo esc_attr( $hvnly_image_count - 1 ); ?>"
            data-full-image="<?php echo esc_url( $hvnly_full_image_url ); ?>"
            data-image-title="<?php echo esc_attr( $hvnly_image_title ); ?>"
            data-image-alt="<?php echo esc_attr( $hvnly_image_alt ); ?>">

            <a href="<?php echo esc_url( $hvnly_full_image_url ); ?>"
                class="hvnly-property-single__gallery-link hvnly-gallery-popup-trigger"
                data-gallery-id="<?php echo esc_attr( $hvnly_gallery_id ); ?>"
                data-image-index="<?php echo esc_attr( $hvnly_image_count - 1 ); ?>">

                <img src="<?php echo esc_url( $hvnly_medium_image_url ); ?>"
                    alt="<?php echo esc_attr( $hvnly_image_alt ); ?>" class="hvnly-property-single__gallery-image"
                    loading="lazy">

                <?php if ( $hvnly_image_count === 6 && count( $hvnly_images ) > 6 ) : ?>
                <div class="hvnly-property-single__gallery-more">
                    <?php 
                                printf(
                                    '+%d %s',
                                    count( $hvnly_images ) - 6,
                                    esc_html__( 'more', 'havenlytics' )
                                ); 
                                ?>
                </div>
                <?php endif; ?>
            </a>
        </div>
        <?php 
                    endif;
                endif;
            endforeach; 
            ?>
    </div>

    <div class="hvnly-property-single__gallery-info">
        <p>
            <?php
                $hvnly_image_total = count( $hvnly_images );
                printf(
                    /* translators: %d: number of images in the gallery. */
                    esc_html( _n( '%d image', '%d images', $hvnly_image_total, 'havenlytics' ) ),
                    (int) $hvnly_image_total
                );
                ?>
        </p>
    </div>
    <?php endif; ?>
</div>