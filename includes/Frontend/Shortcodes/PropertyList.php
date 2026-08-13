<?php
/**
 * Property List Shortcode - Production Ready (All Attributes)
 *
 * @package     Havenlytics
 * @subpackage  Frontend\Shortcodes
 * @since       2.0.0
 */

namespace HvnlyNab\Frontend\Shortcodes;

use HvnlyNab\Frontend\Query\PropertyQueryArgsBuilder;

if ( ! defined('ABSPATH')) {
    exit;
}

class PropertyList extends AbstractShortcode {

    protected $tag = 'hvnly_property_list';

    protected $default_atts = array(
        'posts_per_page'       => 12,
        'orderby'              => 'date',
        'order'                => 'DESC',
        'category'             => '',
        'location'             => '',
        'featured_only'        => 'no',
        'show_pagination'      => 'yes',
        'show_results_count'   => 'yes',
        'results_bar_position' => 'both',
        'class'                => '',
        'id'                   => '',
        'ids'                  => '',
        'exclude'              => '',
        'columns'              => 1,
        // Taxonomy attributes
        'department'           => '',
        'departments'          => '',
        'dept'                 => '',
        'status'               => '',
        'property_type'        => '',
        'type'                 => '',
        'feature'              => '',
        'features'             => '',
        'tag'                  => '',
        'tags'                 => '',
        'badge'                => '',
        'badges'               => '',
        // Meta attributes
        'min_price'            => '',
        'max_price'            => '',
        'bedrooms'             => '',
        'beds'                 => '',
        'bathrooms'            => '',
        'baths'                => '',
        'reception_rooms'      => '',
        'receptions'           => '',
        'sqft'                 => '',
        'area'                 => '',
        'garage'               => '',
        'year_built'           => '',
    );

    public function __construct() {
        parent::__construct();
        $this->default_atts = apply_filters('hvnly_property_list_default_atts', $this->default_atts);
    }

    public function render( $atts, $content = '' ) {
        $atts = $this->parse_attributes($atts);

        $paged = get_query_var('paged') ? get_query_var('paged') : 1;

        // Build query arguments
        $args = array(
            'post_type'      => 'hvnly_property',
            'post_status'    => 'publish',
            'posts_per_page' => $atts['posts_per_page'],
            'paged'          => $paged,
            'orderby'        => $atts['orderby'],
            'order'          => $atts['order'],
        );

        // Build tax query
        $tax_query = array();

        // Department filter
        $department_value = '';
        if ( ! empty($atts['department'])) {
            $department_value = $atts['department'];
        } elseif ( ! empty($atts['departments'])) {
            $department_value = $atts['departments'];
        } elseif ( ! empty($atts['dept'])) {
            $department_value = $atts['dept'];
        }

        if ( ! empty($department_value)) {
            $tax_query[] = array(
                'taxonomy' => 'hvnly_prop_depts',
                'field'    => 'slug',
                'terms'    => $department_value,
            );
        }

        // Status filter
        if ( ! empty($atts['status'])) {
            $tax_query[] = array(
                'taxonomy' => 'hvnly_prop_status',
                'field'    => 'slug',
                'terms'    => $atts['status'],
            );
        }

        // Property type filter
        $type_value = '';
        if ( ! empty($atts['property_type'])) {
            $type_value = $atts['property_type'];
        } elseif ( ! empty($atts['type'])) {
            $type_value = $atts['type'];
        } elseif ( ! empty($atts['category'])) {
            $type_value = $atts['category'];
        }

        if ( ! empty($type_value)) {
            $tax_query[] = array(
                'taxonomy' => 'hvnly_prop_types',
                'field'    => 'slug',
                'terms'    => $type_value,
            );
        }

        // Location filter
        if ( ! empty($atts['location'])) {
            $tax_query[] = array(
                'taxonomy' => 'hvnly_prop_locations',
                'field'    => 'slug',
                'terms'    => $atts['location'],
            );
        }

        // Feature filter
        $feature_value = '';
        if ( ! empty($atts['feature'])) {
            $feature_value = $atts['feature'];
        } elseif ( ! empty($atts['features'])) {
            $feature_value = $atts['features'];
        }

        if ( ! empty($feature_value)) {
            $tax_query[] = array(
                'taxonomy' => 'hvnly_prop_features',
                'field'    => 'slug',
                'terms'    => $feature_value,
            );
        }

        // Tag filter
        $tag_value = '';
        if ( ! empty($atts['tag'])) {
            $tag_value = $atts['tag'];
        } elseif ( ! empty($atts['tags'])) {
            $tag_value = $atts['tags'];
        }

        if ( ! empty($tag_value)) {
            $tax_query[] = array(
                'taxonomy' => 'hvnly_prop_tags',
                'field'    => 'slug',
                'terms'    => $tag_value,
            );
        }

        // Badge filter
        $badge_value = '';
        if ( ! empty($atts['badge'])) {
            $badge_value = $atts['badge'];
        } elseif ( ! empty($atts['badges'])) {
            $badge_value = $atts['badges'];
        }

        if ( ! empty($badge_value)) {
            $tax_query[] = array(
                'taxonomy' => 'hvnly_prop_badges',
                'field'    => 'slug',
                'terms'    => $badge_value,
            );
        }

        // Apply tax query if not empty
        if ( ! empty($tax_query)) {
            if (count($tax_query) > 1) {
                $tax_query['relation'] = 'AND';
            }
            $args['tax_query'] = $tax_query;
        }

        // Featured only filter
        if ($atts['featured_only'] === 'yes') {
            $args['meta_query'][] = PropertyQueryArgsBuilder::get_featured_meta_query();
        }

        // Price range filter
        $min_price = ! empty($atts['min_price']) ? floatval($atts['min_price']) : null;
        $max_price = ! empty($atts['max_price']) ? floatval($atts['max_price']) : null;

        if ($min_price !== null || $max_price !== null) {
            if ($min_price !== null && $max_price !== null) {
                $args['meta_query'][] = array(
                    'key'     => '_hvnly_property_price',
                    'value'   => array( $min_price, $max_price ),
                    'type'    => 'NUMERIC',
                    'compare' => 'BETWEEN',
                );
            } elseif ($min_price !== null) {
                $args['meta_query'][] = array(
                    'key'     => '_hvnly_property_price',
                    'value'   => $min_price,
                    'type'    => 'NUMERIC',
                    'compare' => '>=',
                );
            } elseif ($max_price !== null) {
                $args['meta_query'][] = array(
                    'key'     => '_hvnly_property_price',
                    'value'   => $max_price,
                    'type'    => 'NUMERIC',
                    'compare' => '<=',
                );
            }
        }

        // Bedrooms filter
        $bedrooms = ! empty($atts['bedrooms']) ? $atts['bedrooms'] : ( ! empty($atts['beds']) ? $atts['beds'] : '' );
        if ( ! empty($bedrooms)) {
            $args['meta_query'][] = array(
                'key'     => '_hvnly_property_bedrooms',
                'value'   => absint($bedrooms),
                'type'    => 'NUMERIC',
                'compare' => '>=',
            );
        }

        // Bathrooms filter
        $bathrooms = ! empty($atts['bathrooms']) ? $atts['bathrooms'] : ( ! empty($atts['baths']) ? $atts['baths'] : '' );
        if ( ! empty($bathrooms)) {
            $args['meta_query'][] = array(
                'key'     => '_hvnly_property_bathrooms',
                'value'   => absint($bathrooms),
                'type'    => 'NUMERIC',
                'compare' => '>=',
            );
        }

        // Reception rooms filter
        $reception = ! empty($atts['reception_rooms']) ? $atts['reception_rooms'] : ( ! empty($atts['receptions']) ? $atts['receptions'] : '' );
        if ( ! empty($reception)) {
            $args['meta_query'][] = array(
                'key'     => '_hvnly_property_reception_rooms',
                'value'   => absint($reception),
                'type'    => 'NUMERIC',
                'compare' => '>=',
            );
        }

        // Area filter
        $area = ! empty($atts['sqft']) ? $atts['sqft'] : ( ! empty($atts['area']) ? $atts['area'] : '' );
        if ( ! empty($area)) {
            $args['meta_query'][] = array(
                'key'     => '_hvnly_property_sqft',
                'value'   => absint($area),
                'type'    => 'NUMERIC',
                'compare' => '>=',
            );
        }

        // Garage filter
        if ( ! empty($atts['garage'])) {
            $args['meta_query'][] = array(
                'key'     => '_hvnly_property_garage_sqft',
                'value'   => absint($atts['garage']),
                'type'    => 'NUMERIC',
                'compare' => '>=',
            );
        }

        // Year built filter
        if ( ! empty($atts['year_built'])) {
            $args['meta_query'][] = array(
                'key'     => '_hvnly_property_year_built',
                'value'   => absint($atts['year_built']),
                'type'    => 'NUMERIC',
                'compare' => '>=',
            );
        }

        // Set meta_query relation if multiple conditions
        if (isset($args['meta_query']) && count($args['meta_query']) > 1) {
            $args['meta_query']['relation'] = 'AND';
        }

        // Specific property IDs
        if ( ! empty($atts['ids'])) {
            $ids                  = array_map('trim', explode(',', $atts['ids']));
            $args['meta_query'][] = array(
                'key'     => '_hvnly_unique_property_id',
                'value'   => $ids,
                'compare' => 'IN',
            );
        }

        // Exclude property IDs
        if ( ! empty($atts['exclude'])) {
            $exclude_ids          = array_map('trim', explode(',', $atts['exclude']));
            $args['meta_query'][] = array(
                'key'     => '_hvnly_unique_property_id',
                'value'   => $exclude_ids,
                'compare' => 'NOT IN',
            );
        }

        // Run query
        $properties = new \WP_Query($args);

        // Prepare template arguments
        $template_args = array(
            'properties' => $properties,
            'atts'       => $atts,
            'paged'      => $paged,
            'shortcode'  => $this->tag,
            'is_shortcode' => true,
        );

        return $this->load_template('shortcodes/property-list', $template_args);
    }

    protected function validate_attributes( $atts ) {
        $atts = parent::validate_attributes($atts);

        $atts['posts_per_page'] = absint($atts['posts_per_page']);
        if ($atts['posts_per_page'] < 1) {
            $atts['posts_per_page'] = 12;
        }
        if ($atts['posts_per_page'] > 100) {
            $atts['posts_per_page'] = 100;
        }

        $atts['featured_only']      = $atts['featured_only'] === 'yes' ? 'yes' : 'no';
        $atts['show_pagination']    = $atts['show_pagination'] === 'yes' ? 'yes' : 'no';
        $atts['show_results_count'] = $atts['show_results_count'] === 'yes' ? 'yes' : 'no';

        $valid_positions = array( 'top', 'bottom', 'both' );
        if ( ! in_array($atts['results_bar_position'], $valid_positions, true)) {
            $atts['results_bar_position'] = 'both';
        }

        $atts['columns'] = 1;

        return $atts;
    }
}
