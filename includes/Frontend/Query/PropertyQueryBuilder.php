<?php
/**
 * Property Query Builder - Production Ready
 *
 * @deprecated 2.3.0 Unused. PropertyQueryManager is the active query layer.
 *
 * @package     Havenlytics
 * @subpackage  Frontend\Query
 * @since       2.0.0
 */

namespace HvnlyNab\Frontend\Query;

if (!defined('ABSPATH')) {
    exit;
}

class PropertyQueryBuilder {

    private $args = [];
    private $tax_queries = [];
    private $meta_queries = [];

    private $defaults = [
        'post_type'              => 'hvnly_property',
        'post_status'            => 'publish',
        'posts_per_page'         => 12,
        'paged'                  => 1,
        'orderby'                => 'date',
        'order'                  => 'DESC',
        'no_found_rows'          => false,
        'ignore_sticky_posts'    => true,
        'update_post_meta_cache' => true,
        'update_post_term_cache' => true,
    ];

    public function __construct($initial_args = []) {
        $this->args = wp_parse_args($initial_args, $this->defaults);
        $this->reset();
    }

    private function reset() {
        $this->tax_queries = [];
        $this->meta_queries = [];
    }

    public function set_posts_per_page($per_page) {
        $this->args['posts_per_page'] = max(1, absint($per_page));
        return $this;
    }

    public function set_page($page) {
        $this->args['paged'] = max(1, absint($page));
        return $this;
    }

    public function set_featured_only($featured_only = true) {
        if ($featured_only) {
            $this->meta_queries[] = [
                'key'   => '_hvnly_property_featured',
                'value' => '1',
                'compare' => '=',
            ];
        }
        return $this;
    }

    public function set_order($orderby, $order = 'DESC') {
        $orderby = sanitize_key($orderby);
        $order   = strtoupper($order) === 'ASC' ? 'ASC' : 'DESC';

        switch ($orderby) {
            case 'price_low':
            case 'price_asc':
                $this->args['meta_key'] = '_hvnly_property_price';
                $this->args['orderby']  = 'meta_value_num';
                $this->args['order']    = 'ASC';
                break;
            case 'price_high':
            case 'price_desc':
                $this->args['meta_key'] = '_hvnly_property_price';
                $this->args['orderby']  = 'meta_value_num';
                $this->args['order']    = 'DESC';
                break;
            case 'title_asc':
                $this->args['orderby'] = 'title';
                $this->args['order']   = 'ASC';
                break;
            case 'title_desc':
                $this->args['orderby'] = 'title';
                $this->args['order']   = 'DESC';
                break;
            case 'date_asc':
                $this->args['orderby'] = 'date';
                $this->args['order']   = 'ASC';
                break;
            case 'rand':
            case 'random':
                $this->args['orderby'] = 'rand';
                break;
            case 'bedrooms':
                $this->args['meta_key'] = '_hvnly_property_bedrooms';
                $this->args['orderby']  = 'meta_value_num';
                $this->args['order']    = $order;
                break;
            case 'bathrooms':
                $this->args['meta_key'] = '_hvnly_property_bathrooms';
                $this->args['orderby']  = 'meta_value_num';
                $this->args['order']    = $order;
                break;
            case 'area':
                $this->args['meta_key'] = '_hvnly_property_sqft';
                $this->args['orderby']  = 'meta_value_num';
                $this->args['order']    = $order;
                break;
            default:
                $this->args['orderby'] = 'date';
                $this->args['order']   = 'DESC';
                break;
        }
        return $this;
    }

    public function add_taxonomy_filter($taxonomy, $terms, $field = 'slug', $operator = 'IN') {
        if (empty($terms)) {
            return $this;
        }

        if (!is_array($terms)) {
            $terms = explode(',', (string) $terms);
        }

        $terms = array_map('trim', $terms);
        $terms = array_filter($terms);

        if (empty($terms)) {
            return $this;
        }

        if (!taxonomy_exists($taxonomy)) {
            return $this;
        }

        $valid_terms = [];
        foreach ($terms as $term_value) {
            $term = get_term_by('slug', $term_value, $taxonomy);
            if (!$term || is_wp_error($term)) {
                $term = get_term_by('name', $term_value, $taxonomy);
            }
            if ($term && !is_wp_error($term)) {
                $valid_terms[] = $term->slug;
            } else {
                $valid_terms[] = $term_value;
            }
        }

        if (empty($valid_terms)) {
            return $this;
        }

        $this->tax_queries[] = [
            'taxonomy' => $taxonomy,
            'field'    => $field,
            'terms'    => $valid_terms,
            'operator' => $operator,
        ];

        return $this;
    }

    public function add_meta_query($query) {
        if (!empty($query)) {
            $this->meta_queries[] = $query;
        }
        return $this;
    }

    public function add_price_range($min, $max) {
        $min = is_numeric($min) ? floatval($min) : null;
        $max = is_numeric($max) ? floatval($max) : null;

        if ($min !== null && $max !== null && $min <= $max) {
            $this->meta_queries[] = [
                'key'     => '_hvnly_property_price',
                'value'   => [$min, $max],
                'type'    => 'NUMERIC',
                'compare' => 'BETWEEN',
            ];
        } elseif ($min !== null) {
            $this->meta_queries[] = [
                'key'     => '_hvnly_property_price',
                'value'   => $min,
                'type'    => 'NUMERIC',
                'compare' => '>=',
            ];
        } elseif ($max !== null) {
            $this->meta_queries[] = [
                'key'     => '_hvnly_property_price',
                'value'   => $max,
                'type'    => 'NUMERIC',
                'compare' => '<=',
            ];
        }
        return $this;
    }

    public function build() {
        $query_args = $this->args;
        
        if (!empty($this->tax_queries)) {
            if (count($this->tax_queries) === 1) {
                $query_args['tax_query'] = $this->tax_queries[0];
            } else {
                $query_args['tax_query'] = array_merge($this->tax_queries, ['relation' => 'AND']);
            }
        }

        if (!empty($this->meta_queries)) {
            if (count($this->meta_queries) === 1) {
                $query_args['meta_query'] = $this->meta_queries[0];
            } else {
                $query_args['meta_query'] = array_merge($this->meta_queries, ['relation' => 'AND']);
            }
        }

        if (empty($query_args['tax_query'])) {
            unset($query_args['tax_query']);
        }
        if (empty($query_args['meta_query'])) {
            unset($query_args['meta_query']);
        }

        return apply_filters('hvnly_property_query_args', $query_args);
    }

    public function run() {
        $query_args = $this->build();
        $query = new \WP_Query($query_args);
        $this->reset();
        return $query;
    }

    public function reset_builder() {
        $this->args = $this->defaults;
        $this->reset();
        return $this;
    }
}