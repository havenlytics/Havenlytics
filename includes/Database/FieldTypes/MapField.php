<?php
/**
 * Map Field Handler - Dynamic Map Provider Support
 * 
 * @package HvnlyNab\Database\FieldTypes
 * @since 2.0.0
 */

namespace HvnlyNab\Database\FieldTypes;

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

class MapField extends BaseFieldType {
    
    /**
     * Debug flag - disable for production
     */
    private static $debug_enabled = false;
    
    public function __construct() {
        parent::__construct('map');
        $this->requires_assets = true;
    }
    
    public function render($field, $value, $post_id) {
        // Only render the main map widget (preview), not address/lat/lng sub-fields.
        $meta_key   = $field['metaKey'] ?? '';
        $field_type = $field['type'] ?? $field['input_type'] ?? '';
        $is_preview = ( 'preview' === $meta_key )
            || ( 'map' === $field_type )
            || ! empty( $field['is_map_preview'] );

        if ( ! $is_preview ) {
            return '';
        }

        $field = $this->prepare_group_field( $field, 'MapField' );
        
        $settings_manager = \HvnlyNab\Core\SettingsManager::get_instance();
        $map_provider = $settings_manager->get_map_provider();
        $google_api_key = $settings_manager->get_google_maps_api_key();
        $default_lat = $settings_manager->get_default_latitude();
        $default_lng = $settings_manager->get_default_longitude();
        $default_zoom = $settings_manager->get_map_zoom();
        
        $use_leaflet = in_array($map_provider, ['leaflet', 'openstreetmap']);
        $use_google = ($map_provider === 'google' && !empty($google_api_key));
        
        if ($map_provider === 'google' && empty($google_api_key)) {
            $use_leaflet = true;
            $use_google = false;
            $map_provider = 'leaflet';
        }
        
        $group_base_id = $field['group_base_id'] ?? '';
        if ( ! empty( $group_base_id ) ) {
            $address_field_name = $group_base_id . '_address';
            $lat_field_name     = $group_base_id . '_latitude';
            $lng_field_name     = $group_base_id . '_longitude';
        } else {
            $address_field_name = $field['address_field_name'] ?? '';
            $lat_field_name     = $field['lat_field_name'] ?? '';
            $lng_field_name     = $field['lng_field_name'] ?? '';
        }

        $address_value = $this->get_map_subfield_value( $post_id, $field, $address_field_name, 'address' );
        $lat_value     = $this->get_map_subfield_value( $post_id, $field, $lat_field_name, 'latitude' );
        $lng_value     = $this->get_map_subfield_value( $post_id, $field, $lng_field_name, 'longitude' );
        $is_required   = ! empty( $field['is_required'] );
        
        // Generate a clean map ID (replace hyphens with underscores for JavaScript)
        $raw_map_id = $group_base_id ?: $field['id'] ?? uniqid('map_');
        $map_id = 'hvnly_map_' . sanitize_html_class($raw_map_id);
        $js_callback = 'initGoogleMap_' . str_replace('-', '_', $map_id);
        
        $tile_url = 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png';
        $attribution = '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors';
        
        if ($map_provider === 'openstreetmap') {
            $tile_url = 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png';
            $attribution = '© OpenStreetMap contributors';
        }

        $brand_color              = function_exists( 'hvnly_get_brand_color' ) ? hvnly_get_brand_color() : '#6C60FE';
        $hvnly_map_brand_color_attr = esc_attr( $brand_color );
        $hvnly_map_brand_color_bg   = esc_attr( $brand_color . '15' );

        // phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- Map admin inline styles reuse esc_attr-assigned CSS variables.
        
        ob_start();
        ?>
<div data-field-id="<?php echo esc_attr($field['id'] ?? ''); ?>" data-field-type="map"
    data-map-group-id="<?php echo esc_attr($group_base_id); ?>"
    data-map-provider="<?php echo esc_attr($map_provider); ?>"
    data-address-field-name="<?php echo esc_attr($address_field_name); ?>"
    data-lat-field-name="<?php echo esc_attr($lat_field_name); ?>"
    data-lng-field-name="<?php echo esc_attr($lng_field_name); ?>"
    data-map-container-id="<?php echo esc_attr($map_id); ?>" data-js-callback="<?php echo esc_attr($js_callback); ?>">

    <div class="hvnly-map-field-wrapper" data-map-id="<?php echo esc_attr($map_id); ?>">
        <div class="hvnly-meta-field" style="display:block; margin:0;">

            <!-- ============================================ -->
            <!-- ADDRESS FIELD -->
            <!-- ============================================ -->
            <div class="hvnly-map-address-field" style="margin-bottom: 15px;">
                <label for="<?php echo esc_attr($address_field_name); ?>"
                    style="display: block; margin-bottom: 5px; font-weight: 600;">
                    <?php esc_html_e('Property Map Address', 'havenlytics'); ?>
                    <?php if ( $is_required ) : ?>
                    <span class="required">*</span>
                    <?php endif; ?>
                </label>
                <input type="text" id="<?php echo esc_attr($address_field_name); ?>"
                    name="<?php echo esc_attr($address_field_name); ?>" value="<?php echo esc_attr($address_value); ?>"
                    placeholder="<?php esc_attr_e('Enter property map address', 'havenlytics'); ?>" class="widefat"
                    style="margin-bottom: 5px;"<?php echo $is_required ? ' required' : ''; ?> />
                <p class="description"><?php esc_html_e('Enter the full address of the property', 'havenlytics'); ?></p>
            </div>

            <!-- ============================================ -->
            <!-- LATITUDE & LONGITUDE FIELDS - Now VISIBLE for manual edit -->
            <!-- ============================================ -->
            <div class="hvnly-map-coordinates-row"
                style="display: flex; gap: 15px; margin-bottom: 15px; flex-wrap: wrap;">
                <div class="hvnly-map-latitude-field" style="flex: 1;">
                    <label for="<?php echo esc_attr($lat_field_name); ?>"
                        style="display: block; margin-bottom: 5px; font-weight: 600;">
                        <?php esc_html_e('Latitude', 'havenlytics'); ?>
                    </label>
                    <input type="text" id="<?php echo esc_attr($lat_field_name); ?>"
                        name="<?php echo esc_attr($lat_field_name); ?>" value="<?php echo esc_attr($lat_value); ?>"
                        placeholder="<?php esc_attr_e('e.g., 51.5074', 'havenlytics'); ?>"
                        class="widefat hvnly-map-latitude" style="font-family: monospace;" />
                    <p class="description"><?php esc_html_e('Latitude coordinate (e.g., 51.5074)', 'havenlytics'); ?>
                    </p>
                </div>
                <div class="hvnly-map-longitude-field" style="flex: 1;">
                    <label for="<?php echo esc_attr($lng_field_name); ?>"
                        style="display: block; margin-bottom: 5px; font-weight: 600;">
                        <?php esc_html_e('Longitude', 'havenlytics'); ?>
                    </label>
                    <input type="text" id="<?php echo esc_attr($lng_field_name); ?>"
                        name="<?php echo esc_attr($lng_field_name); ?>" value="<?php echo esc_attr($lng_value); ?>"
                        placeholder="<?php esc_attr_e('e.g., -0.1278', 'havenlytics'); ?>"
                        class="widefat hvnly-map-longitude" style="font-family: monospace;" />
                    <p class="description"><?php esc_html_e('Longitude coordinate (e.g., -0.1278)', 'havenlytics'); ?>
                    </p>
                </div>
            </div>

            <!-- ============================================ -->
            <!-- MAP PROVIDER BADGE & CONTROLS -->
            <!-- ============================================ -->
            <div class="hvnly-meta-label"
                style="display: flex; width: 100%; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; margin-bottom: 15px;">
                <div class="hvnly-map-provider-badge"
                    style="display: inline-flex; align-items: center; gap: 6px; background: <?php echo $hvnly_map_brand_color_bg; ?>; padding: 4px 12px; border-radius: 20px; border-left: 3px solid <?php echo $hvnly_map_brand_color_attr; ?>;">
                    <i class="fas fa-map-marker-alt" style="color: <?php echo $hvnly_map_brand_color_attr; ?>; font-size: 12px;"></i>
                    <span style="font-size: 11px; font-weight: 500; color: <?php echo $hvnly_map_brand_color_attr; ?>;">
                        <?php 
                        if ($use_google) {
                            echo esc_html__('Google Maps', 'havenlytics');
                        } elseif ($map_provider === 'openstreetmap') {
                            echo esc_html__('OpenStreetMap (Direct)', 'havenlytics');
                        } else {
                            echo esc_html__('Leaflet (OpenStreetMap)', 'havenlytics');
                        }
                        ?> -
                        <?php echo !empty($lat_value) && !empty($lng_value) ? esc_html__('Coordinates Saved', 'havenlytics') : esc_html__('Set coordinates by searching or dragging marker', 'havenlytics'); ?>
                    </span>
                </div>

                <button type="button" class="button hvnly-geocode-address-btn"
                    data-map-id="<?php echo esc_attr($map_id); ?>"
                    data-address-field-name="<?php echo esc_attr($address_field_name); ?>"
                    data-lat-field-name="<?php echo esc_attr($lat_field_name); ?>"
                    data-lng-field-name="<?php echo esc_attr($lng_field_name); ?>"
                    data-map-provider="<?php echo esc_attr($map_provider); ?>"
                    style="display: inline-flex; align-items: center; gap: 6px;">
                    <i class="fas fa-map-pin"></i>
                    <?php esc_html_e('Get Coordinates from Address', 'havenlytics'); ?>
                </button>

                <div class="coordinates-info"
                    style="font-size: 11px; background: #f5f5f5; padding: 4px 10px; border-radius: 6px;">
                    <i class="fas fa-location-dot" style="color: <?php echo $hvnly_map_brand_color_attr; ?>; margin-right: 4px;"></i>
                    <strong><?php esc_html_e('Coordinates:', 'havenlytics'); ?></strong>
                    <span class="current-lat"
                        style="font-weight: 500;"><?php echo !empty($lat_value) ? esc_html(number_format((float)$lat_value, 6)) : '—'; ?></span>,
                    <span class="current-lng"
                        style="font-weight: 500;"><?php echo !empty($lng_value) ? esc_html(number_format((float)$lng_value, 6)) : '—'; ?></span>
                </div>
            </div>

            <!-- Live Address Display -->
            <div class="hvnly-map-live-address"
                style="margin-bottom: 15px; padding: 10px; background: #e8f0fe; border-radius: 6px; border-left: 3px solid <?php echo $hvnly_map_brand_color_attr; ?>;">
                <i class="fas fa-map-pin" style="color: <?php echo $hvnly_map_brand_color_attr; ?>; margin-right: 8px;"></i>
                <strong><?php esc_html_e('Current Address:', 'havenlytics'); ?></strong>
                <span class="hvnly-map-current-address" id="hvnly-current-address-<?php echo esc_attr($map_id); ?>">
                    <?php echo !empty($address_value) ? esc_html($address_value) : esc_html__('No address set', 'havenlytics'); ?>
                </span>
            </div>

            <div style="text-align: center; margin-bottom: 10px; font-size: 11px; color: #666;">
                <i class="fas fa-info-circle"></i>
                <?php esc_html_e('Enter address above, then click "Get Coordinates", drag the marker on the map, or manually enter coordinates', 'havenlytics'); ?>
            </div>

            <!-- ============================================ -->
            <!-- MAP PREVIEW -->
            <!-- ============================================ -->
            <div class="hvnly-meta-input hvnly-popup-meta-input">
                <?php if ($use_google) : ?>
                <div id="<?php echo esc_attr($map_id); ?>" class="hvnly-map-container hvnly-google-map-container"
                    data-map-id="<?php echo esc_attr($map_id); ?>" data-map-provider="google"
                    data-js-callback="<?php echo esc_attr($js_callback); ?>"
                    data-address-field-name="<?php echo esc_attr($address_field_name); ?>"
                    data-lat-field-name="<?php echo esc_attr($lat_field_name); ?>"
                    data-lng-field-name="<?php echo esc_attr($lng_field_name); ?>"
                    data-initial-lat="<?php echo esc_attr(!empty($lat_value) ? $lat_value : $default_lat); ?>"
                    data-initial-lng="<?php echo esc_attr(!empty($lng_value) ? $lng_value : $default_lng); ?>"
                    data-default-zoom="<?php echo esc_attr($default_zoom); ?>"
                    data-google-api-key="<?php echo esc_attr($google_api_key); ?>"
                    style="height: 400px; width: 100%; background: #f0f0f0; border: 1px solid #ddd; border-radius: 8px;">
                    <div class="hvnly-map-loading"
                        style="display: flex; align-items: center; justify-content: center; height: 100%; background: #f8f9fa; border-radius: 8px;">
                        <div style="text-align: center;">
                            <i class="fas fa-spinner fa-spin" style="font-size: 24px; color: <?php echo $hvnly_map_brand_color_attr; ?>;"></i>
                            <p><?php esc_html_e('Loading Google Map...', 'havenlytics'); ?></p>
                        </div>
                    </div>
                </div>
                <?php else : ?>
                <div id="<?php echo esc_attr($map_id); ?>" class="hvnly-map-container hvnly-leaflet-map-container"
                    data-map-id="<?php echo esc_attr($map_id); ?>"
                    data-map-provider="<?php echo esc_attr($map_provider); ?>"
                    data-tile-url="<?php echo esc_attr($tile_url); ?>"
                    data-tile-attribution="<?php echo esc_attr($attribution); ?>"
                    data-address-field-name="<?php echo esc_attr($address_field_name); ?>"
                    data-lat-field-name="<?php echo esc_attr($lat_field_name); ?>"
                    data-lng-field-name="<?php echo esc_attr($lng_field_name); ?>"
                    data-initial-lat="<?php echo esc_attr(!empty($lat_value) ? $lat_value : $default_lat); ?>"
                    data-initial-lng="<?php echo esc_attr(!empty($lng_value) ? $lng_value : $default_lng); ?>"
                    data-default-zoom="<?php echo esc_attr($default_zoom); ?>"
                    style="height: 400px; width: 100%; background: #f0f0f0; border: 1px solid #ddd; border-radius: 8px;">
                    <div class="hvnly-map-loading"
                        style="display: flex; align-items: center; justify-content: center; height: 100%; background: #f8f9fa; border-radius: 8px;">
                        <div style="text-align: center;">
                            <i class="fas fa-spinner fa-spin" style="font-size: 24px; color: <?php echo $hvnly_map_brand_color_attr; ?>;"></i>
                            <p><?php esc_html_e('Loading map...', 'havenlytics'); ?></p>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<style>
.hvnly-map-provider-badge {
    transition: all 0.2s ease;
}

.hvnly-map-address-field input,
.hvnly-map-latitude-field input,
.hvnly-map-longitude-field input {
    border: 1px solid #ddd;
    border-radius: 4px;
    padding: 8px 12px;
}

.hvnly-map-address-field input:focus,
.hvnly-map-latitude-field input:focus,
.hvnly-map-longitude-field input:focus {
    border-color: <?php echo $hvnly_map_brand_color_attr; ?>;
    box-shadow: 0 0 0 1px <?php echo $hvnly_map_brand_color_attr; ?>;
    outline: none;
}

.hvnly-map-live-address {
    transition: all 0.2s ease;
}

.hvnly-map-live-address:hover {
    background: #e0e8f8;
}

.hvnly-map-coordinates-row {
    background: #f8f9fa;
    padding: 12px;
    border-radius: 6px;
    border: 1px solid #e9ecef;
}
</style>
<?php
        // phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
        return ob_get_clean();
    }

    /**
     * Load a map sub-field value using the same resolution chain as the frontend.
     *
     * @param int    $post_id   Post ID.
     * @param array  $field     Builder field config for this map group.
     * @param string $meta_key  Full meta key for this sub-field (builder input name).
     * @param string $suffix    address|latitude|longitude.
     * @return string
     */
    private function get_map_subfield_value( $post_id, $field, $meta_key, $suffix ) {
        if ( empty( $meta_key ) ) {
            return '';
        }

        $value = get_post_meta( $post_id, $meta_key, true );
        if ( $value !== '' && $value !== false && $value !== null ) {
            return (string) $value;
        }

        $probe = array_merge(
            $field,
            array(
                'group_type' => $field['group_type'] ?? 'map',
                'metaKey'    => $suffix,
                'name'       => $meta_key,
            )
        );

        $resolved = $this->resolve_group_meta( (int) $post_id, $probe, $meta_key, $suffix );
        if ( $resolved !== '' && $resolved !== false && $resolved !== null ) {
            return (string) $resolved;
        }

        return '';
    }
    
    public function save($post_id, $field_name, $value, $extra = null) { 
        // Map fields are saved via their individual address/lat/lng fields
        return; 
    }
    
    public function sanitize($value) { 
        return sanitize_text_field($value); 
    }
    
    public function validate($value, $field) { 
        if (empty($field['is_required'])) {
            return true;
        }

        $address_field = $field['address_field_name'] ?? '';
        if ('' === $address_field && !empty($field['group_base_id'])) {
            $address_field = $field['group_base_id'] . '_address';
        }

        if ('' === $address_field) {
            return true;
        }

        $address_raw = filter_input(INPUT_POST, $address_field, FILTER_UNSAFE_RAW);
        $address     = is_string($address_raw) ? trim($address_raw) : '';

        if ('' === $address) {
            return new \WP_Error(
                'required_map_address',
                sprintf(
                    /* translators: %s: map field label. */
                    __('The map address for "%s" is required.', 'havenlytics'),
                    hvnly_esc_html_ui( (string) ( ! empty( $field['label'] ) ? $field['label'] : 'Map' ) )
                )
            );
        }

        return true;
    }
    
    public function enqueue_assets() {
        wp_enqueue_media();
        wp_enqueue_script('jquery-ui-sortable');
        
        wp_enqueue_style(
            'hvnly-map-field',
            HVNLYNAB_ASSETS_URL . '/admin/css/hvnly-map-field.css',
            array(),
            defined('HVNLYNAB_VERSION') ? HVNLYNAB_VERSION : '1.0.0'
        );

        if ( wp_script_is( 'hvnly-map-field', 'registered' ) ) {
            wp_enqueue_script( 'hvnly-map-field' );
            wp_localize_script(
                'hvnly-map-field',
                'hvnlyMapFieldParams',
                array(
                    'brandColor' => function_exists( 'hvnly_get_brand_color' ) ? hvnly_get_brand_color() : '#6C60FE',
                    'i18n'       => array(
                        'noAddressSet'      => __( 'No address set', 'havenlytics' ),
                        'locationPopup'     => __( 'Location', 'havenlytics' ),
                        'latLabel'          => __( 'Lat:', 'havenlytics' ),
                        'lngLabel'          => __( 'Lng:', 'havenlytics' ),
                        'enterAddressFirst' => __( 'Please enter an address first.', 'havenlytics' ),
                        'searching'         => __( 'Searching…', 'havenlytics' ),
                        'locationFound'     => __( 'Location found!', 'havenlytics' ),
                        'addressNotFound'   => __( 'Address not found.', 'havenlytics' ),
                        'searchError'       => __( 'Error searching for address.', 'havenlytics' ),
                        'getCoordinates'    => __( 'Get Coordinates from Address', 'havenlytics' ),
                        'noAddressesFound'  => __( 'No addresses found', 'havenlytics' ),
                    ),
                )
            );
        }
    }
}