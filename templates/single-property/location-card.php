<?php

/**

 * Location Card Template

 *

 * This template displays the location card for single property pages.

 *

 * HOW TO OVERRIDE THIS TEMPLATE:

 * 1. Copy this file from: wp-content/plugins/havenlytics/templates/single-property/location-card.php

 * 2. Paste it to your theme at: wp-content/themes/your-theme/havenlytics/single-property/location-card.php

 * 3. Modify the copied file to customize the location card

 *

 * @package     Havenlytics

 * @subpackage  Templates/single-property/

 * @since       2.0.0

 */



if ( ! defined( 'ABSPATH' ) ) {

    exit;

}



// hvnly_get_template() extracts $args into local variables.

$hvnly_property_id   = isset( $property_id ) ? (int) $property_id : get_the_ID();

$hvnly_fields        = isset( $fields ) ? $fields : array();

$hvnly_values        = isset( $values ) ? $values : array();

$hvnly_group_base_id = isset( $group_base_id ) ? $group_base_id : '';

$hvnly_group_id      = isset( $group_id ) ? $group_id : '';



$hvnly_address   = '';

$hvnly_latitude  = '';

$hvnly_longitude = '';



/**

 * Resolve a map sub-field via MetaResolver.

 *

 * @param int    $post_id   Property ID.

 * @param string $meta_key  Logical metaKey (address|latitude|longitude).

 * @param array  $fields    Builder field rows for this map group.

 * @return mixed

 */

$hvnly_resolve_map_value = static function ( $post_id, $meta_key, $fields, $group_base_id, $group_id ) {

    if ( ! function_exists( 'hvnly_resolve_field_meta' ) ) {

        return '';

    }



    $field_config = array(

        'group_type'    => 'map',

        'group_base_id' => $group_base_id,

        'group_id'      => $group_id,

        'metaKey'       => $meta_key,

    );



    foreach ( $fields as $hvnly_field ) {

        if ( ! is_array( $hvnly_field ) ) {

            continue;

        }

        if ( ( $hvnly_field['metaKey'] ?? '' ) === $meta_key ) {

            $field_config = $hvnly_field;

            break;

        }

    }



    if ( empty( $field_config['name'] ) && ! empty( $group_base_id ) ) {

        $field_config['name'] = $group_base_id . '_' . $meta_key;

    }



    return hvnly_resolve_field_meta( (int) $post_id, $field_config );

};



if ( ! empty( $hvnly_values['address'] ) ) {

    $hvnly_address = $hvnly_values['address'];

} else {

    $hvnly_address = $hvnly_resolve_map_value( $hvnly_property_id, 'address', $hvnly_fields, $hvnly_group_base_id, $hvnly_group_id );

}



if ( ! empty( $hvnly_values['latitude'] ) ) {

    $hvnly_latitude = $hvnly_values['latitude'];

} else {

    $hvnly_latitude = $hvnly_resolve_map_value( $hvnly_property_id, 'latitude', $hvnly_fields, $hvnly_group_base_id, $hvnly_group_id );

}



if ( ! empty( $hvnly_values['longitude'] ) ) {

    $hvnly_longitude = $hvnly_values['longitude'];

} else {

    $hvnly_longitude = $hvnly_resolve_map_value( $hvnly_property_id, 'longitude', $hvnly_fields, $hvnly_group_base_id, $hvnly_group_id );

}



$hvnly_has_location = ! empty( $hvnly_latitude ) && ! empty( $hvnly_longitude ) && is_numeric( $hvnly_latitude ) && is_numeric( $hvnly_longitude );

$hvnly_map_suffix   = ! empty( $hvnly_group_base_id ) ? sanitize_title( $hvnly_group_base_id ) : wp_unique_id();

$hvnly_map_id       = 'hvnly-map-' . $hvnly_property_id . '-' . $hvnly_map_suffix;

?>

<div class="hvnly-property-single__location-card">

    <div class="hvnly-property-single__location-content">

        <?php if ( ! empty( $hvnly_address ) ) : ?>

        <div class="hvnly-property-single__address">

            <strong><?php esc_html_e( 'Address:', 'havenlytics' ); ?></strong>

            <span><?php echo esc_html( $hvnly_address ); ?></span>

        </div>

        <?php endif; ?>



        <div class="hvnly-property-single__map-container">

            <?php if ( $hvnly_has_location ) : ?>

            <div class="hvnly-property-single__map" id="<?php echo esc_attr( $hvnly_map_id ); ?>"

                data-lat="<?php echo esc_attr( $hvnly_latitude ); ?>"

                data-lng="<?php echo esc_attr( $hvnly_longitude ); ?>"

                data-address="<?php echo esc_attr( $hvnly_address ); ?>"

                data-title="<?php echo esc_attr( get_the_title( $hvnly_property_id ) ); ?>">

                <!-- Map will be initialized by JavaScript -->

            </div>

            <?php else : ?>

            <div class="hvnly-property-single__map-placeholder" style="

                    display: flex;

                    flex-direction: column;

                    align-items: center;

                    justify-content: center;

                    background: #f5f5f5;

                    border-radius: 8px;

                    padding: 20px;

                    text-align: center;

                    color: #666;

                ">

                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"

                    style="margin-bottom: 15px; opacity: 0.5;">

                    <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7z" stroke="#999"

                        stroke-width="1.5" fill="none" />

                    <circle cx="12" cy="9" r="2.5" stroke="#999" stroke-width="1.5" fill="none" />

                </svg>

                <p><?php esc_html_e( 'Map location not available for this property.', 'havenlytics' ); ?></p>

            </div>

            <?php endif; ?>

        </div>



        <?php if ( $hvnly_has_location ) : ?>

        <div class="hvnly-property-single__location-actions" style="margin-top: 15px; display: flex; gap: 10px;">

            <a href="https://www.google.com/maps/search/?api=1&query=<?php echo urlencode( $hvnly_latitude . ',' . $hvnly_longitude ); ?>"

                target="_blank" class="hvnly-button hvnly-button--small"

                style="display: inline-block; padding: 8px 16px;  border-radius: 4px; text-decoration: none;  font-size: 14px;">

                <?php esc_html_e( 'Google Maps', 'havenlytics' ); ?>

            </a>

            <a href="https://maps.apple.com/?q=<?php echo urlencode( $hvnly_latitude . ',' . $hvnly_longitude ); ?>"

                target="_blank" class="hvnly-button hvnly-button--small"

                style="display: inline-block; padding: 8px 16px; border-radius: 4px; text-decoration: none;  font-size: 14px;">

                <?php esc_html_e( 'Apple Maps', 'havenlytics' ); ?>

            </a>

        </div>

        <?php endif; ?>

    </div>

</div>


