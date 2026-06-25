<?php
/**
 * Unified property query args builder — single source of truth for SSR, AJAX, and load-more.
 *
 * @package     Havenlytics
 * @subpackage  Frontend\Query
 * @since       3.1.0
 */

namespace HvnlyNab\Frontend\Query;

if (!defined('ABSPATH')) {
    exit;
}

class PropertyQueryArgsBuilder {

    /**
     * Request-level memoization for built query args.
     *
     * @var array<string, array>
     */
    private static $hvnly_build_cache = [];

    /**
     * Request-level memoization for merged Elementor widget context.
     *
     * @var array<string, array>
     */
    private static $hvnly_widget_context_cache = [];

    /**
     * Build WP_Query args from filter/search data.
     *
     * @param array $data     Filter payload (URL, POST, or merged widget context).
     * @param int   $page     Page number.
     * @param int   $per_page Posts per page.
     * @param array $options  Optional: widget_id (string).
     * @return array
     */
    public static function build(array $data, int $page = 1, int $per_page = 12, array $options = []): array {
        $hvnly_memo_key = self::hvnly_build_memo_key($data, $page, $per_page, $options);
        if (isset(self::$hvnly_build_cache[$hvnly_memo_key])) {
            return self::$hvnly_build_cache[$hvnly_memo_key];
        }

        $widget_id = isset($options['widget_id']) ? (string) $options['widget_id'] : '';
        $resolved_page = self::resolve_paged($widget_id, $data, $page);

        $args = [
            'post_type'      => 'hvnly_property',
            'post_status'    => 'publish',
            'paged'          => $resolved_page,
            'posts_per_page' => max(1, absint($per_page)),
        ];

        self::apply_tax_queries($args, $data);
        self::apply_meta_queries($args, $data);

        if (!empty($data['address_keyword'])) {
            $args['s'] = sanitize_text_field((string) $data['address_keyword']);
        }

        if (!empty($data['orderby'])) {
            self::apply_orderby($args, (string) $data['orderby']);
        } else {
            self::apply_default_orderby($args, $data);
        }

        $args = apply_filters('hvnly_property_query_args', $args, $data);

        if (!empty($options['filter_context']) && $options['filter_context'] === 'elementor_load_more') {
            $args = apply_filters('hvnly_elementor_load_more_query_args', $args, $data);
        }

        self::$hvnly_build_cache[$hvnly_memo_key] = $args;

        return $args;
    }

    /**
     * Merge Elementor widget settings with current URL filters for SSR.
     *
     * @param array  $settings  Widget display/query settings.
     * @param string $widget_id Elementor widget instance ID.
     * @return array
     */
    public static function merge_elementor_widget_context(array $settings, string $widget_id): array {
        $hvnly_context_key = md5(wp_json_encode([$settings, $widget_id]));
        if (isset(self::$hvnly_widget_context_cache[$hvnly_context_key])) {
            return self::$hvnly_widget_context_cache[$hvnly_context_key];
        }

        $filters = function_exists('hvnly_get_current_filters') ? hvnly_get_current_filters() : [];
        $data    = is_array($filters) ? $filters : [];

        if (empty($data['orderby']) && !empty($settings['orderby'])) {
            $data['orderby'] = (string) $settings['orderby'];
        }

        if (empty($data['department']) && !empty($settings['default_department'])) {
            $data['department'] = (string) $settings['default_department'];
        }

        if (empty($data['min_price']) && isset($settings['default_min_price']) && $settings['default_min_price'] !== '') {
            $data['min_price'] = $settings['default_min_price'];
        }

        if (empty($data['max_price']) && isset($settings['default_max_price']) && $settings['default_max_price'] !== '') {
            $data['max_price'] = $settings['default_max_price'];
        }

        if (empty($data['bedrooms']) && !empty($settings['default_bedrooms'])) {
            $data['bedrooms'] = $settings['default_bedrooms'];
        }

        if (empty($data['bathrooms']) && !empty($settings['default_bathrooms'])) {
            $data['bathrooms'] = $settings['default_bathrooms'];
        }

        if (self::is_featured_only($settings)) {
            $data['featured_only'] = 'yes';
        }

        $data['widget_id'] = $widget_id;

        self::$hvnly_widget_context_cache[$hvnly_context_key] = $data;

        return $data;
    }

    /**
     * Resolve pagination for widget SSR, archive URL, or AJAX payload.
     *
     * @param string $widget_id Widget instance ID (empty for archive/AJAX-only).
     * @param array  $data      Filter data (may contain paged/page).
     * @param int    $fallback  Explicit page from caller (AJAX).
     * @return int
     */
    public static function resolve_paged(string $widget_id = '', array $data = [], int $fallback = 1): int {
        // Explicit AJAX `page` must win over the search form hidden `paged=1` field.
        if (!empty($data['page'])) {
            return max(1, absint($data['page']));
        }

        if ($widget_id !== '') {
            $key = 'hvnly_paged_' . sanitize_key($widget_id);
            if (isset($_GET[$key])) {
                return max(1, absint(wp_unslash($_GET[$key])));
            }

            if (!empty($data['paged'])) {
                return max(1, absint($data['paged']));
            }

            return max(1, absint($fallback));
        }

        if (!empty($data['paged'])) {
            return max(1, absint($data['paged']));
        }

        return max(1, absint($fallback));
    }

    /**
     * Resolve view type: URL filters → widget default → global default.
     *
     * @param array $settings Widget settings (optional).
     * @return string grid|list|map
     */
    public static function resolve_view_type(array $settings = []): string {
        $filters = function_exists('hvnly_get_current_filters') ? hvnly_get_current_filters() : [];
        $raw     = '';

        if (!empty($filters['view_type'])) {
            $raw = (string) $filters['view_type'];
        } elseif (isset($_GET['view'])) {
            $raw = sanitize_text_field(wp_unslash($_GET['view']));
        } elseif (isset($_GET['view_type'])) {
            $raw = sanitize_text_field(wp_unslash($_GET['view_type']));
        } elseif (!empty($settings['default_view'])) {
            $raw = (string) $settings['default_view'];
        } elseif (function_exists('hvnly_get_default_view_option')) {
            $raw = (string) hvnly_get_default_view_option();
        } else {
            $raw = 'grid';
        }

        $raw = strtolower(sanitize_text_field($raw));

        return in_array($raw, ['grid', 'list', 'map'], true) ? $raw : 'grid';
    }

    /**
     * Normalize featured-only flag from settings, POST, or filter payload.
     *
     * @param array $data
     * @return bool
     */
    public static function is_featured_only(array $data): bool {
        if (empty($data['featured_only'])) {
            return false;
        }

        $value = strtolower(trim((string) $data['featured_only']));

        return in_array($value, ['yes', '1', 'true'], true);
    }

    public static function get_featured_meta_query(): array {
        return [
            'relation' => 'OR',
            [
                'key'     => '_hvnly_property_featured',
                'value'   => '1',
                'compare' => '=',
            ],
            [
                'key'     => '_hvnly_property_action_tool_is_featured',
                'value'   => '1',
                'compare' => '=',
            ],
        ];
    }

    /**
     * Apply orderby slug to query args.
     *
     * @param array  $args
     * @param string $orderby
     * @return void
     */
    public static function apply_orderby(array &$args, string $orderby): void {
        $orderby = sanitize_text_field($orderby);

        switch ($orderby) {
            case 'title':
            case 'a-to-z-title':
                $args['orderby'] = 'title';
                $args['order']   = 'ASC';
                break;
            case 'z-to-a-title':
                $args['orderby'] = 'title';
                $args['order']   = 'DESC';
                break;
            case 'date':
                $args['orderby'] = 'date';
                $args['order']   = 'DESC';
                break;
            case 'date_oldest':
                $args['orderby'] = 'date';
                $args['order']   = 'ASC';
                break;
            case 'price_low':
                $args['meta_key'] = '_hvnly_property_price';
                $args['orderby']  = 'meta_value_num';
                $args['order']    = 'ASC';
                break;
            case 'price_high':
                $args['meta_key'] = '_hvnly_property_price';
                $args['orderby']  = 'meta_value_num';
                $args['order']    = 'DESC';
                break;
            case 'area':
                $args['meta_key'] = '_hvnly_property_sqft';
                $args['orderby']  = 'meta_value_num';
                $args['order']    = 'DESC';
                break;
            case 'bedrooms':
                $args['meta_key'] = '_hvnly_property_bedrooms';
                $args['orderby']  = 'meta_value_num';
                $args['order']    = 'DESC';
                break;
            case 'views':
                $args['meta_key'] = '_hvnly_property_views';
                $args['orderby']  = 'meta_value_num';
                $args['order']    = 'DESC';
                break;
            case 'rating':
                $args['meta_key'] = '_hvnly_property_rating';
                $args['orderby']  = 'meta_value_num';
                $args['order']    = 'DESC';
                break;
            case 'rand':
            case 'rand_property':
                $args['orderby'] = 'rand';
                unset($args['order']);
                break;
            default:
                $args['orderby'] = 'date';
                $args['order']   = 'DESC';
                break;
        }
    }

    /**
     * Apply plugin default sort when orderby is absent.
     *
     * @param array $args
     * @param array $data
     * @return void
     */
    public static function apply_default_orderby(array &$args, array $data): void {
        $default_sort = function_exists('hvnly_get_default_sort_option') ? hvnly_get_default_sort_option() : 'date';
        self::apply_orderby($args, (string) $default_sort);
    }

    /**
     * @param array $args
     * @param array $data
     * @return void
     */
    private static function apply_tax_queries(array &$args, array $data): void {
        $tax_query = [];

        if (!empty($data['department'])) {
            $department = sanitize_text_field((string) $data['department']);
            if ($department !== '') {
                $tax_query[] = [
                    'taxonomy' => 'hvnly_prop_depts',
                    'field'    => 'slug',
                    'terms'    => $department,
                ];
            }
        }

        if (!empty($data['property_type'])) {
            $property_types = is_array($data['property_type']) ? $data['property_type'] : [$data['property_type']];
            $tax_query[] = [
                'taxonomy' => 'hvnly_prop_types',
                'field'    => 'slug',
                'terms'    => array_map('sanitize_text_field', $property_types),
            ];
        }

        if (!empty($data['location'])) {
            $locations = is_array($data['location']) ? $data['location'] : [$data['location']];
            $tax_query[] = [
                'taxonomy' => 'hvnly_prop_locations',
                'field'    => 'slug',
                'terms'    => array_map('sanitize_text_field', $locations),
            ];
        }

        if (!empty($data['status'])) {
            $statuses = is_array($data['status']) ? $data['status'] : [$data['status']];
            $tax_query[] = [
                'taxonomy' => 'hvnly_prop_status',
                'field'    => 'slug',
                'terms'    => array_map('sanitize_text_field', $statuses),
            ];
        }

        $slug_taxonomies = [
            'hvnly_prop_depts',
            'hvnly_prop_types',
            'hvnly_prop_locations',
            'hvnly_prop_features',
            'hvnly_prop_reviews',
            'hvnly_prop_tags',
            'hvnly_prop_badges',
            'hvnly_prop_status',
            'amenities',
        ];

        foreach ($slug_taxonomies as $taxonomy) {
            if (empty($data[$taxonomy])) {
                continue;
            }

            $terms = is_array($data[$taxonomy]) ? $data[$taxonomy] : explode(',', (string) $data[$taxonomy]);
            $terms = array_filter(array_map('sanitize_text_field', $terms));

            if (!empty($terms)) {
                $tax_query[] = [
                    'taxonomy' => $taxonomy,
                    'field'    => 'slug',
                    'terms'    => $terms,
                ];
            }
        }

        $in_taxonomy_params = [
            'in_tag'      => 'hvnly_prop_tags',
            'in_badge'    => 'hvnly_prop_badges',
            'in_status'   => 'hvnly_prop_status',
            'in_type'     => 'hvnly_prop_types',
            'in_location' => 'hvnly_prop_locations',
            'in_feature'  => 'hvnly_prop_features',
            'in_review'   => 'hvnly_prop_reviews',
        ];

        foreach ($in_taxonomy_params as $param => $taxonomy) {
            if (empty($data[$param])) {
                continue;
            }

            $param_value = $data[$param];
            $term_ids    = is_array($param_value) ? $param_value : array_map('absint', explode(',', (string) $param_value));
            $term_ids    = array_filter($term_ids);

            if (!empty($term_ids)) {
                $tax_query[] = [
                    'taxonomy' => $taxonomy,
                    'field'    => 'term_id',
                    'terms'    => $term_ids,
                ];
            }
        }

        if (empty($tax_query)) {
            return;
        }

        $grouped = [];
        foreach ($tax_query as $tax_item) {
            $taxonomy = $tax_item['taxonomy'];
            if (!isset($grouped[$taxonomy])) {
                $grouped[$taxonomy] = $tax_item;
                continue;
            }

            if (is_array($grouped[$taxonomy]['terms']) && is_array($tax_item['terms'])) {
                $grouped[$taxonomy]['terms'] = array_merge($grouped[$taxonomy]['terms'], $tax_item['terms']);
            }
        }

        $tax_query = array_values($grouped);

        if (count($tax_query) > 1) {
            $tax_query['relation'] = 'OR';
        }

        $args['tax_query'] = $tax_query;
    }

    /**
     * @param array $args
     * @param array $data
     * @return void
     */
    private static function apply_meta_queries(array &$args, array $data): void {
        $min_price = !empty($data['min_price']) ? absint($data['min_price']) : 0;
        $max_price = !empty($data['max_price']) ? absint($data['max_price']) : 0;
        $has_price = $min_price > 0 || $max_price > 0;

        $has_filters = $has_price
            || !empty($data['bedrooms'])
            || !empty($data['bathrooms'])
            || !empty($data['reception_rooms'])
            || !empty($data['garages'])
            || self::is_featured_only($data)
            || !empty($data['property_ids']);

        if (!$has_filters) {
            return;
        }

        $meta_query = [];
        $property_ids_meta_query = [];

        if (!empty($data['property_ids'])) {
            $property_ids = is_array($data['property_ids']) ? $data['property_ids'] : [$data['property_ids']];
            $clean_ids    = array_map('sanitize_text_field', array_filter($property_ids));

            if (!empty($clean_ids)) {
                $property_ids_meta_query = ['relation' => 'OR'];
                foreach ($clean_ids as $property_id) {
                    $property_ids_meta_query[] = [
                        'key'     => '_hvnly_unique_property_id',
                        'value'   => $property_id,
                        'compare' => 'LIKE',
                    ];
                }
            }
        }

        if ($min_price > 0 || $max_price > 0) {
            $price_query = [];

            if ($min_price > 0) {
                $price_query[] = [
                    'key'     => '_hvnly_property_price',
                    'value'   => $min_price,
                    'type'    => 'NUMERIC',
                    'compare' => '>=',
                ];
            }

            if ($max_price > 0) {
                $price_query[] = [
                    'key'     => '_hvnly_property_price',
                    'value'   => $max_price,
                    'type'    => 'NUMERIC',
                    'compare' => '<=',
                ];
            }

            if (count($price_query) > 1) {
                $price_query['relation'] = 'AND';
                $meta_query[] = $price_query;
            } elseif (!empty($price_query)) {
                $meta_query[] = $price_query[0];
            }
        }

        if (!empty($data['bedrooms'])) {
            $meta_query[] = [
                'key'     => '_hvnly_property_bedrooms',
                'value'   => absint($data['bedrooms']),
                'type'    => 'NUMERIC',
                'compare' => '>=',
            ];
        }

        if (!empty($data['bathrooms'])) {
            $meta_query[] = [
                'key'     => '_hvnly_property_bathrooms',
                'value'   => absint($data['bathrooms']),
                'type'    => 'NUMERIC',
                'compare' => '>=',
            ];
        }

        if (!empty($data['reception_rooms'])) {
            $meta_query[] = [
                'key'     => '_hvnly_property_reception_rooms',
                'value'   => absint($data['reception_rooms']),
                'type'    => 'NUMERIC',
                'compare' => '>=',
            ];
        }

        if (!empty($data['garages'])) {
            $meta_query[] = [
                'key'     => '_hvnly_property_garage_sqft',
                'value'   => absint($data['garages']),
                'type'    => 'NUMERIC',
                'compare' => '>=',
            ];
        }

        if (self::is_featured_only($data)) {
            $meta_query[] = self::get_featured_meta_query();
        }

        if (!empty($property_ids_meta_query) && !empty($meta_query)) {
            if (count($meta_query) > 1) {
                $meta_query['relation'] = 'AND';
            }

            $args['meta_query'] = [
                'relation' => 'AND',
                $property_ids_meta_query,
                $meta_query,
            ];
        } elseif (!empty($property_ids_meta_query)) {
            $args['meta_query'] = $property_ids_meta_query;
        } elseif (!empty($meta_query)) {
            if (count($meta_query) > 1) {
                $meta_query['relation'] = 'AND';
            }
            $args['meta_query'] = $meta_query;
        }
    }

    /**
     * @param array $data
     * @param int   $page
     * @param int   $per_page
     * @param array $options
     * @return string
     */
    private static function hvnly_build_memo_key(array $data, int $page, int $per_page, array $options): string {
        return md5(wp_json_encode([
            $data,
            $page,
            $per_page,
            $options,
            self::resolve_paged((string) ($options['widget_id'] ?? ''), $data, $page),
        ]));
    }
}
