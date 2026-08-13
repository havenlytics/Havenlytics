<?php
/**
 * Legacy Shortcode Compatibility - Enhanced with More Aliases
 *
 * @package     Havenlytics
 * @subpackage  Frontend\Shortcodes
 * @since       2.0.0
 */

namespace HvnlyNab\Frontend\Shortcodes;

// Exit if accessed directly.
if ( ! defined('ABSPATH')) {
    exit;
}

/**
 * Legacy Compatibility class - Enhanced version
 *
 * @since 2.0.0
 */
class LegacyCompatibility {

    /**
     * Constructor
     */
    public function __construct() {
        // Register legacy shortcode aliases
        add_action('init', array( $this, 'register_legacy_shortcodes' ), 20);

        // Handle legacy attribute conversions
        add_filter('hvnly_shortcode_legacy_atts', array( $this, 'convert_legacy_attributes' ), 10, 2);
    }

    /**
     * Register legacy shortcode names
     */
    public function register_legacy_shortcodes() {
        // Map old shortcode names to new ones
        $legacy_map = array(
            'hvnly_properties'           => 'hvnly_property_grid',
            'hvnly_featured'              => 'hvnly_featured_properties',
            'hvnly_property_search_form'  => 'hvnly_property_search',
            'hvnly_property_lists'        => 'hvnly_property_list',
            'hvnly_property_listings'     => 'hvnly_property_grid',
            'hvnly_properties_grid'       => 'hvnly_property_grid',
            'hvnly_properties_list'       => 'hvnly_property_list',
            'hvnly_prop_grid'             => 'hvnly_property_grid',
            'hvnly_prop_list'             => 'hvnly_property_list',
            'hvnly_agents'                => 'hvnly_property_agents',
            'hvnly_agencies'              => 'hvnly_property_agencies',
        );

        foreach ($legacy_map as $old_name => $new_name) {
            add_shortcode($old_name, function ( $atts, $content ) use ( $old_name, $new_name ) {
                // Convert legacy attributes
                $atts = $this->convert_legacy_attributes($atts, $old_name);

                // Build shortcode string
                $shortcode = '[' . $new_name;

                if ( ! empty($atts) && is_array($atts)) {
                    foreach ($atts as $key => $value) {
                        if (is_numeric($key)) {
                            $shortcode .= ' ' . $value;
                        } else {
                            $shortcode .= ' ' . $key . '="' . esc_attr($value) . '"';
                        }
                    }
                }

                if ( ! empty($content)) {
                    $shortcode .= ']' . $content . '[/' . $new_name . ']';
                } else {
                    $shortcode .= ']';
                }

                return do_shortcode($shortcode);
            });
        }
    }

    /**
     * Convert legacy attribute names to new format
     *
     * @param array  $atts       Original attributes
     * @param string $shortcode  Original shortcode name
     * @return array
     */
    public function convert_legacy_attributes( $atts, $shortcode ) {
        if ( ! is_array($atts)) {
            return array();
        }

        // Legacy attribute mappings
        $attribute_map = array(
            'per_page' => 'posts_per_page',
            'num'      => 'posts_per_page',
            'limit'    => 'posts_per_page',
            'type'     => 'property_type',
            'city'     => 'location',
            'view'     => 'view_type',
            'grid'     => 'columns',
            'sort'     => 'orderby',
            'order_by' => 'orderby',
            'featured' => 'featured_only',
            'bed'      => 'bedrooms',
            'bath'     => 'bathrooms',
            'reception' => 'reception_rooms',
            'price_min' => 'min_price',
            'price_max' => 'max_price',
            'minprice' => 'min_price',
            'maxprice' => 'max_price',
            'propertyid' => 'property_id',
            'prop_id'  => 'property_id',
        );

        $converted = array();

        foreach ($atts as $key => $value) {
            // Convert attribute names
            $new_key = $attribute_map[ $key ] ?? $key;

            // Handle value conversions
            if ($new_key === 'featured_only') {
                // Convert 'true', '1', 'yes' to 'yes'
                $value = in_array(strtolower($value), array( 'true', '1', 'yes' ), true) ? 'yes' : 'no';
            }

            $converted[ $new_key ] = $value;
        }

        // Special handling for specific shortcodes
        if ($shortcode === 'hvnly_properties') {
            // hvnly_properties was an alias for grid view
            if ( ! isset($converted['columns'])) {
                $converted['columns'] = 3;
            }
        }

        return apply_filters('hvnly_legacy_attribute_conversion', $converted, $shortcode);
    }
}
