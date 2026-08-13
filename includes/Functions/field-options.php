<?php
/**
 * Central registry for predefined select-field options.
 *
 * @package Havenlytics
 * @since   2.3.6
 */

defined( 'ABSPATH' ) || exit;

/**
 * Default heating options.
 *
 * @return array<string, string>
 */
function hvnly_get_heating_field_options() {
    return array(
        'forced_air'  => __( 'Forced Air', 'havenlytics' ),
        'radiator'    => __( 'Radiator', 'havenlytics' ),
        'radiant'     => __( 'Radiant Heat', 'havenlytics' ),
        'heat_pump'   => __( 'Heat Pump', 'havenlytics' ),
        'baseboard'   => __( 'Baseboard', 'havenlytics' ),
        'central'     => __( 'Central', 'havenlytics' ),
        'geothermal'  => __( 'Geothermal', 'havenlytics' ),
        'none'        => __( 'None', 'havenlytics' ),
    );
}

/**
 * Default cooling options.
 *
 * @return array<string, string>
 */
function hvnly_get_cooling_field_options() {
    return array(
        'central'   => __( 'Central Air', 'havenlytics' ),
        'window'    => __( 'Window Units', 'havenlytics' ),
        'heat_pump' => __( 'Heat Pump', 'havenlytics' ),
        'baseboard' => __( 'Baseboard', 'havenlytics' ),
        'none'      => __( 'None', 'havenlytics' ),
    );
}

/**
 * Default water source options.
 *
 * @return array<string, string>
 */
function hvnly_get_water_field_options() {
    return array(
        'city'        => __( 'City Water', 'havenlytics' ),
        'well'        => __( 'Well Water', 'havenlytics' ),
        'shared_well' => __( 'Shared Well', 'havenlytics' ),
        'shared'      => __( 'Shared Well', 'havenlytics' ),
        'none'        => __( 'None', 'havenlytics' ),
    );
}

/**
 * Property location options (taxonomy terms + known fallbacks).
 *
 * @return array<string, string>
 */
function hvnly_get_location_field_options() {
    $locations = array();

    if ( class_exists( '\HvnlyNab\Admin\Data\TabData' ) ) {
        $locations = \HvnlyNab\Admin\Data\TabData::hvnly_get_property_locations();
    }

    if ( class_exists( '\HvnlyNab\Api\Type\Builders\DefaultTabSectionsData' ) ) {
        $locations = array_merge(
            $locations,
            \HvnlyNab\Api\Type\Builders\DefaultTabSectionsData::hvnly_get_property_locations()
        );
    }

    if ( empty( $locations ) ) {
        $locations = array(
            'new-york'      => __( 'New York', 'havenlytics' ),
            'los-angeles'   => __( 'Los Angeles', 'havenlytics' ),
            'chicago'       => __( 'Chicago', 'havenlytics' ),
            'miami'         => __( 'Miami', 'havenlytics' ),
            'san-francisco' => __( 'San Francisco', 'havenlytics' ),
            'urban'         => __( 'Urban', 'havenlytics' ),
            'suburban'      => __( 'Suburban', 'havenlytics' ),
            'rural'         => __( 'Rural', 'havenlytics' ),
            'coastal'       => __( 'Coastal', 'havenlytics' ),
        );
    }

    return $locations;
}

/**
 * Property country options.
 *
 * @return array<string, string>
 */
function hvnly_get_country_field_options() {
    $countries = array();

    if ( class_exists( '\HvnlyNab\Admin\Data\TabData' ) ) {
        $countries = \HvnlyNab\Admin\Data\TabData::hvnly_get_property_countries();
    }

    if ( class_exists( '\HvnlyNab\Api\Type\Builders\DefaultTabSectionsData' ) ) {
        $legacy = \HvnlyNab\Api\Type\Builders\DefaultTabSectionsData::hvnly_get_property_countries();
        foreach ( $legacy as $code => $label ) {
            $countries[ $code ]               = $label;
            $countries[ strtoupper( $code ) ] = $label;
        }
    }

    if ( empty( $countries ) ) {
        $countries = array(
            'US' => __( 'United States', 'havenlytics' ),
            'GB' => __( 'United Kingdom', 'havenlytics' ),
            'CA' => __( 'Canada', 'havenlytics' ),
            'AU' => __( 'Australia', 'havenlytics' ),
        );
    }

    return $countries;
}

/**
 * Registry of predefined select-field options keyed by field name.
 *
 * @return array<string, array<string, string>>
 */
function hvnly_get_registered_field_options() {
    static $registry = null;

    if ( null !== $registry ) {
        return $registry;
    }

    $registry = array(
        '_hvnly_property_heating'          => hvnly_get_heating_field_options(),
        '_hvnly_property_cooling'          => hvnly_get_cooling_field_options(),
        '_hvnly_property_water'            => hvnly_get_water_field_options(),
        '_hvnly_property_location'         => hvnly_get_location_field_options(),
        '_hvnly_property_country_location' => hvnly_get_country_field_options(),
    );

    /**
     * Filter the registry of predefined select-field options.
     *
     * @param array<string, array<string, string>> $registry Field name => options map.
     */
    return apply_filters( 'hvnly_field_options', $registry );
}

/**
 * Get predefined options for a field name.
 *
 * @param string $field_name Field meta key / name.
 * @return array<string, string>
 */
function hvnly_get_field_options( $field_name ) {
    $field_name = (string) $field_name;
    $registry   = hvnly_get_registered_field_options();

    return $registry[ $field_name ] ?? array();
}

/**
 * Merge predefined options into a select field when options are missing.
 *
 * @param array<string, mixed> $field Field configuration.
 * @return array<string, mixed>
 */
function hvnly_hydrate_select_field_options( array $field ) {
    $field_type = $field['type'] ?? $field['input_type'] ?? '';
    if ( 'select' !== $field_type ) {
        return $field;
    }

    if ( ! empty( $field['options'] ) && is_array( $field['options'] ) ) {
        return $field;
    }

    $field_name = $field['name'] ?? $field['id'] ?? $field['fieldid'] ?? '';
    if ( '' === $field_name ) {
        return $field;
    }

    $defaults = hvnly_get_field_options( $field_name );
    if ( ! empty( $defaults ) ) {
        $field['options'] = $defaults;
    }

    return $field;
}

/**
 * Hydrate select options for an array of fields.
 *
 * @param array<int, array<string, mixed>> $fields Field list.
 * @return array<int, array<string, mixed>>
 */
function hvnly_hydrate_fields_select_options( array $fields ) {
    foreach ( $fields as $index => $field ) {
        if ( is_array( $field ) ) {
            $fields[ $index ] = hvnly_hydrate_select_field_options( $field );
        }
    }

    return $fields;
}
