<?php

/**
 * Havenlytics Helper Functions
 *
 * @package     Havenlytics
 * @subpackage  Includes
 * @copyright   Copyright (c) 2025, Havenlytics
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       2.0.0
 */

namespace HvnlyNab;

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Helper class for Havenlytics plugin
 *
 * @since 2.0.0
 */
class Helpers
{

    /**
     * Hold the class instance
     *
     * @var null|Helpers
     */
    private static $instance = null;

    /**
     * Get the singleton instance
     *
     * @return Helpers
     */
    public static function get_instance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor - Initialize image sizes
     *
     * @since 2.0.0
     */
    public function __construct()
    {
        // Add custom image sizes for property thumbnails
        add_action('init', array($this, 'add_property_image_sizes'));
        
        // Add image size options to media settings
        add_filter('image_size_names_choose', array($this, 'add_custom_image_sizes_to_media'));
    }

    /**
     * Add custom property image sizes
     *
     * @since 2.0.0
     */
    public function add_property_image_sizes()
    {
        // Property Grid Size - Optimized for property listing grids
        add_image_size('hvnly_property_grid', 400, 300, true);
        
        // Property Grid Large - For featured properties or larger grid items
        add_image_size('hvnly_property_grid_large', 600, 450, true);
        
        // Property Details Main - Main image on single property page
        add_image_size('hvnly_property_details_main', 800, 600, true);
        
        // Property Details Full - Full width images in property gallery
        add_image_size('hvnly_property_details_full', 1200, 800, true);
        
        // Property Thumbnail - Small thumbnails for widgets, related properties
        add_image_size('hvnly_property_thumb', 200, 150, true);
        
        // Property Gallery - Square format for gallery thumbnails
        add_image_size('hvnly_property_gallery', 300, 300, true);
    }

    /**
     * Add custom image sizes to media selection dropdown
     *
     * @param array $sizes Existing image sizes
     * @return array Modified image sizes
     * @since 2.0.0
     */
    public function add_custom_image_sizes_to_media($sizes)
    {
        return array_merge($sizes, array(
            'hvnly_property_grid' => __('Property Grid', 'havenlytics'),
            'hvnly_property_grid_large' => __('Property Grid Large', 'havenlytics'),
            'hvnly_property_details_main' => __('Property Details Main', 'havenlytics'),
            'hvnly_property_details_full' => __('Property Details Full', 'havenlytics'),
            'hvnly_property_thumb' => __('Property Thumbnail', 'havenlytics'),
            'hvnly_property_gallery' => __('Property Gallery', 'havenlytics'),
        ));
    }

    /**
     * Get property image with automatic size selection
     *
     * @param int $property_id Property ID
     * @param string $context Usage context (grid, single, thumb, gallery)
     * @param array $attr Image attributes
     * @return string HTML image tag
     * @since 2.0.0
     */
    public function get_property_image($property_id = null, $context = 'grid', $attr = array())
    {
        if (!$property_id) {
            $property_id = get_the_ID();
        }

        if (!$property_id) {
            return '';
        }

        $image_id = get_post_thumbnail_id($property_id);
        
        if ($image_id) {
            $size  = $this->get_image_size_for_context($context);
            $image = wp_get_attachment_image($image_id, $size, false, $attr);
            if (is_string($image) && '' !== $image) {
                return apply_filters('hvnly_property_image', $image, $property_id, $context, $size);
            }
        }

        return $this->get_property_placeholder_image($property_id, $context, $attr);
    }

    /**
     * Get property image URL with automatic size selection
     *
     * @param int $property_id Property ID
     * @param string $context Usage context (grid, single, thumb, gallery)
     * @return string Image URL
     * @since 2.0.0
     */
    public function get_property_image_url($property_id = null, $context = 'grid')
    {
        if (!$property_id) {
            $property_id = get_the_ID();
        }

        if (!$property_id) {
            return '';
        }

        if (function_exists('hvnly_get_property_thumbnail_url')) {
            $size = $this->get_image_size_for_context($context);
            $url  = hvnly_get_property_thumbnail_url($property_id, $size, $context);
            return apply_filters('hvnly_property_image_url', $url, $property_id, $context, $size);
        }

        $image_id = get_post_thumbnail_id($property_id);
        
        if ($image_id) {
            $size      = $this->get_image_size_for_context($context);
            $image_url = wp_get_attachment_image_url($image_id, $size);
            if (is_string($image_url) && '' !== $image_url) {
                return apply_filters('hvnly_property_image_url', $image_url, $property_id, $context, $size);
            }
        }

        return $this->get_property_placeholder_url($context);
    }

    /**
     * Get property gallery images with automatic size selection
     *
     * @param int $property_id Property ID
     * @param string $context Usage context (grid, single, thumb, gallery)
     * @param array $attr Image attributes
     * @return array Array of image HTML tags
     * @since 2.0.0
     */
    public function get_property_gallery_images($property_id = null, $context = 'gallery', $attr = array())
    {
        if (!$property_id) {
            $property_id = get_the_ID();
        }

        if (!$property_id) {
            return array();
        }

        $image_ids = array();
        if ( function_exists( 'hvnly_get_property_gallery_ids' ) ) {
            $image_ids = hvnly_get_property_gallery_ids( (int) $property_id );
        }
        if ( empty( $image_ids ) ) {
            $legacy = get_post_meta( $property_id, '_hvnly_property_gallery_images', true );
            if ( ! empty( $legacy ) ) {
                $image_ids = array_filter( array_map( 'absint', explode( ',', (string) $legacy ) ) );
            }
        }
        
        if (empty($image_ids)) {
            return array();
        }

        $images = array();
        $size = $this->get_image_size_for_context($context);

        foreach ($image_ids as $image_id) {
            if ($image_id) {
                $images[] = wp_get_attachment_image($image_id, $size, false, $attr);
            }
        }

        return apply_filters('hvnly_property_gallery_images', $images, $property_id, $context, $size);
    }

    /**
     * Get property gallery image URLs with automatic size selection
     *
     * @param int $property_id Property ID
     * @param string $context Usage context (grid, single, thumb, gallery)
     * @return array Array of image URLs
     * @since 2.0.0
     */
    public function get_property_gallery_image_urls($property_id = null, $context = 'gallery')
    {
        if (!$property_id) {
            $property_id = get_the_ID();
        }

        if (!$property_id) {
            return array();
        }

        $image_ids = array();
        if ( function_exists( 'hvnly_get_property_gallery_ids' ) ) {
            $image_ids = hvnly_get_property_gallery_ids( (int) $property_id );
        }
        if ( empty( $image_ids ) ) {
            $legacy = get_post_meta( $property_id, '_hvnly_property_gallery_images', true );
            if ( ! empty( $legacy ) ) {
                $image_ids = array_filter( array_map( 'absint', explode( ',', (string) $legacy ) ) );
            }
        }
        
        if (empty($image_ids)) {
            return array();
        }

        $urls = array();
        $size = $this->get_image_size_for_context($context);

        foreach ($image_ids as $image_id) {
            if ($image_id) {
                $url = wp_get_attachment_image_url($image_id, $size);
                if ($url) {
                    $urls[] = $url;
                }
            }
        }

        return apply_filters('hvnly_property_gallery_image_urls', $urls, $property_id, $context, $size);
    }

    /**
     * Determine appropriate image size based on context
     *
     * @param string $context Usage context
     * @return string Image size name
     * @since 2.0.0
     */
    private function get_image_size_for_context($context)
    {
        $sizes = array(
            'grid' => 'hvnly_property_grid',
            'grid_large' => 'hvnly_property_grid_large',
            'single' => 'hvnly_property_details_main',
            'details' => 'hvnly_property_details_main',
            'full' => 'hvnly_property_details_full',
            'thumb' => 'hvnly_property_thumb',
            'thumbnail' => 'hvnly_property_thumb',
            'gallery' => 'hvnly_property_gallery',
            'gallery_thumb' => 'hvnly_property_thumb',
        );

        $size = isset($sizes[$context]) ? $sizes[$context] : 'hvnly_property_grid';
        
        return apply_filters('hvnly_property_image_size', $size, $context);
    }

    /**
     * Get property placeholder image
     *
     * @param int $property_id Property ID
     * @param string $context Usage context
     * @param array $attr Image attributes
     * @return string HTML image tag
     * @since 2.0.0
     */
    private function get_property_placeholder_image($property_id, $context, $attr)
    {
        $placeholder_url = $this->get_property_placeholder_url($context);
        $alt = get_the_title($property_id);
        
        $default_attr = array(
            'src' => $placeholder_url,
            'alt' => $alt,
            'class' => 'hvnly-property-placeholder-image',
        );
        
        $attr = wp_parse_args($attr, $default_attr);
        
        $html = '<img';
        foreach ($attr as $name => $value) {
            $html .= ' ' . $name . '="' . esc_attr($value) . '"';
        }
        $html .= ' />';
        
        return $html;
    }

    /**
     * Get property placeholder image URL
     *
     * @param string $context Usage context
     * @return string Placeholder image URL
     * @since 2.0.0
     */
    private function get_property_placeholder_url($context)
    {
        if (function_exists('hvnly_get_property_placeholder_url')) {
            return hvnly_get_property_placeholder_url($context);
        }

        $placeholder_url = (defined('HVNLYNAB_ASSETS_URL') ? HVNLYNAB_ASSETS_URL : '') . 'images/placeholders/property-placeholder.svg';

        return apply_filters('hvnly_property_placeholder_url', $placeholder_url, $context);
    }

    /**
     * Generate automatic Property ID with gap filling
     *
     * @param int $post_id Post ID
     * @return string
     */
    public function generate_property_id($post_id)
    {
        // Validate post ID
        if (! $post_id || ! is_numeric($post_id)) {
            return '';
        }

        $post_id = absint($post_id);

        // Check if Property ID already exists - NEVER regenerate if exists
        $existing_id = get_post_meta($post_id, '_hvnly_unique_property_id', true);
        if (! empty($existing_id)) {
            return sanitize_text_field($existing_id);
        }

        global $wpdb;

        // Fix for line 347: Use gmdate() instead of date()
        $current_year = absint(gmdate('Y'));

        // Get all existing Property ID numbers for this year
        $existing_numbers = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT CAST(SUBSTRING(meta_value, 12) AS UNSIGNED) as number
            FROM {$wpdb->postmeta} 
            WHERE meta_key = '_hvnly_unique_property_id' 
            AND meta_value LIKE %s
            ORDER BY number",
                'PR-' . $current_year . '-%'
            )
        );

        // Find the first available gap or next number
        $next_number = 1;
        foreach ($existing_numbers as $number) {
            if ($number > $next_number) {
                // Found a gap, use it
                break;
            }
            $next_number = $number + 1;
        }

        // Format: PR-2025-00000019 (fills gaps from deleted properties)
        $property_id = 'PR-' . $current_year . '-' . str_pad($next_number, 8, '0', STR_PAD_LEFT);

        // Sanitize and save permanently
        $sanitized_id = sanitize_text_field($property_id);
        update_post_meta($post_id, '_hvnly_unique_property_id', $sanitized_id);

        return $sanitized_id;
    }

    /**
     * Get Property ID for a post - generates if missing
     *
     * @param int $post_id
     * @return string
     */
    public function get_property_id($post_id = null)
    {
        if (! $post_id) {
            $post_id = get_the_ID();
        }

        if (! $post_id) {
            return '';
        }

        $post_id = absint($post_id);

        // Only retrieve existing ID
        $property_id = get_post_meta($post_id, '_hvnly_unique_property_id', true);

        // If property ID doesn't exist, generate it now
        if (empty($property_id)) {
            $property_id = $this->generate_property_id($post_id);
        }

        return ! empty($property_id) ? sanitize_text_field($property_id) : '';
    }

    /**
     * Check if data exists and is not empty
     *
     * @param array  $data Data array.
     * @param string $key  Key to check.
     * @return bool
     */
    public function data_exists($data, $key)
    {
        return isset($data[$key]) && !empty($data[$key]);
    }

    /**
     * Output required attribute
     *
     * @param array $data Field data.
     */
    public function required_attr($data)
    {
        if ($this->data_exists($data, 'is_required')) {
            echo 'required="required"';
        }
    }

    /**
     * Output required HTML mark
     *
     * @param array $data Field data.
     */
    public function required_mark($data)
    {
        if ($this->data_exists($data, 'is_required')) {
            echo '<span class="hvnly-required-mark"> *</span>';
        }
    }

    /**
     * Check if admin only view
     *
     * @param array $data Field data.
     * @return bool
     */
    public function is_admin_view($data)
    {
        return $this->data_exists($data, 'admin_view') ? current_user_can('manage_options') : true;
    }

    /**
     * Check if field is hidden
     *
     * @param array $data Field data.
     * @return bool
     */
    public function is_hidden_field($data)
    {
        return $this->data_exists($data, 'is_hidden') ? (bool) $data['is_hidden'] : false;
    }

    /**
     * Get data from array with default
     *
     * @param array  $data    Data array.
     * @param string $key     Key to get.
     * @param mixed  $default Default value.
     * @return mixed
     */
    public function get_array_value($data, $key, $default = '')
    {
        return $this->data_exists($data, $key) ? $data[$key] : $default;
    }

    /**
     * Get post meta value safely
     *
     * @param int    $post_id  Post ID.
     * @param string $meta_key Meta key.
     * @param mixed  $default  Default value.
     * @return mixed
     */
    public function get_meta_value($post_id, $meta_key, $default = '')
    {
        $value = get_post_meta($post_id, $meta_key, true);
        return !empty($value) ? $value : $default;
    }

    /**
     * Get post data safely
     *
     * @param array  $post_data Post data array.
     * @param string $key       Key to get.
     * @param mixed  $default   Default value.
     * @return mixed
     */
    public function get_post_value($post_data, $key, $default = '')
    {
        return isset($post_data[$key]) ? $post_data[$key] : $default;
    }

    /**
     * Get file names from folder
     *
     * @param string $path Folder path.
     * @param string $ext  File extension.
     * @return array
     */
    public function get_folder_files($path, $ext = 'php')
    {
        $filenames = glob($path);

        if (!is_array($filenames)) {
            return array();
        }

        return array_map(
            function ($file_path) use ($ext) {
                return basename($file_path, ".{$ext}");
            },
            $filenames
        );
    }

    /**
     * Convert text to slug
     *
     * @param string $text      Text to convert.
     * @param string $delimiter Delimiter.
     * @return string
     */
    public function text_to_slug($text, $delimiter = '-')
    {
        $slug = sanitize_title($text);
        return $slug;
    }

    /**
     * Determine video URL type
     *
     * @param string $url Video URL.
     * @return array
     */
    public function get_video_url_type($url)
    {
        if (empty($url)) {
            return array(
                'video_id'   => '',
                'video_type' => 'none',
            );
        }

        $youtube_pattern = '/^(?:https?:\/\/)?(?:www\.)?(?:youtube\.com\/(?:watch\?v=|embed\/)|youtu\.be\/)([^&?\n]+)/';
        $vimeo_pattern   = '/(https?:\/\/)?(www\.)?(player\.)?vimeo\.com\/([a-z]*\/)*([0-9]{6,11})[?]?.*/';

        $is_youtube = preg_match($youtube_pattern, $url, $youtube_matches);
        $is_vimeo   = preg_match($vimeo_pattern, $url, $vimeo_matches);

        if ($is_youtube) {
            return array(
                'video_id'   => sanitize_text_field($youtube_matches[1] ?? ''),
                'video_type' => 'youtube',
            );
        } elseif ($is_vimeo) {
            return array(
                'video_id'   => sanitize_text_field($vimeo_matches[5] ?? ''),
                'video_type' => 'vimeo',
            );
        }

        return array(
            'video_id'   => '',
            'video_type' => 'none',
        );
    }

    /**
     * Get property ratings average
     *
     * @param int $author_id Author ID.
     * @return float
     */
    public function get_author_property_ratings( $author_id = 0 ) {
        if ( empty( $author_id ) ) {
            return 0.0;
        }

        global $wpdb;

        $cache_key = "hvnly_author_ratings_{$author_id}";
        $ratings   = wp_cache_get( $cache_key, 'havenlytics' );

        if ( false === $ratings ) {
            $ratings = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT AVG(meta_value)
                    FROM {$wpdb->postmeta}
                    WHERE meta_key = '_property_rating'
                    AND post_id IN (
                        SELECT ID FROM {$wpdb->posts}
                        WHERE post_type = 'hvnly_property'
                        AND post_status = 'publish'
                        AND post_author = %d
                    )",
                    $author_id
                )
            );
            wp_cache_set( $cache_key, $ratings, 'havenlytics', 3600 );
        }

        return $ratings ? round( floatval( $ratings ), 1 ) : 0.0;
    }

    /**
     * Get property review count for author
     *
     * @param int $author_id Author ID.
     * @return int
     */
    public function get_author_review_count($author_id = 0)
    {
        if (empty($author_id)) {
            return 0;
        }

        global $wpdb;

        $cache_key = "hvnly_author_reviews_{$author_id}";
        $count     = wp_cache_get($cache_key, 'havenlytics');

        if (false === $count) {
            $count = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(comment_ID) 
                    FROM $wpdb->comments 
                    WHERE comment_approved = '1' 
                    AND comment_post_ID IN(
                        SELECT ID FROM $wpdb->posts 
                        WHERE post_type = 'hvnly_property' 
                        AND post_status = 'publish' 
                        AND post_author = %d
                    )",
                    $author_id
                )
            );

            wp_cache_set($cache_key, $count, 'havenlytics', 3600);
        }

        return absint($count);
    }

    /**
     * Get maximum property price
     *
     * @return float
     */
    public function get_max_property_price()
    {
        global $wpdb;

        $max_price = wp_cache_get('hvnly_max_price', 'havenlytics');

        if (false === $max_price) {
            $max_price = $wpdb->get_var(
                "SELECT MAX(CAST(meta_value AS DECIMAL(10, 2))) 
                FROM $wpdb->postmeta 
                WHERE meta_key = '_property_price' 
                AND meta_value REGEXP '^[0-9]+\.?[0-9]*$' 
                AND post_id IN (
                    SELECT ID FROM $wpdb->posts 
                    WHERE post_type = 'hvnly_property' 
                    AND post_status = 'publish'
                )"
            );

            wp_cache_set('hvnly_max_price', $max_price, 'havenlytics', 3600);
        }

        return $max_price ? floatval($max_price) : 0.0;
    }

    /**
     * Format property price with currency
     *
     * @param float  $price    Price value.
     * @param string $currency Currency symbol.
     * @param string $position Currency position.
     * @return string
     */
    public function format_property_price($price, $currency = '$', $position = 'left')
    {
        if (empty($price)) {
            return apply_filters('hvnly_empty_price_text', __('Price on request', 'havenlytics'));
        }

        $formatted = number_format_i18n(floatval($price));

        if ('left' === $position) {
            return esc_html($currency . $formatted);
        } else {
            return esc_html($formatted . $currency);
        }
    }

    /**
     * Generate rating stars HTML
     *
     * @param float $rating    Rating value.
     * @param int   $max_stars Maximum stars.
     * @return string
     */
    public function get_rating_stars($rating, $max_stars = 5)
    {
        $rating    = floatval($rating);
        $max_stars = absint($max_stars);
        $output    = '<div class="hvnly-rating-stars">';

        for ($i = 1; $i <= $max_stars; $i++) {
            if ($i <= $rating) {
                $output .= '<span class="dashicons dashicons-star-filled"></span>';
            } elseif ($i - $rating < 1) {
                $output .= '<span class="dashicons dashicons-star-half"></span>';
            } else {
                $output .= '<span class="dashicons dashicons-star-empty"></span>';
            }
        }

        $output .= '</div>';
        return $output;
    }

    /**
     * Get property view count
     *
     * @param int $property_id Property ID.
     * @return int
     */
    public function get_property_views($property_id)
    {
        return absint($this->get_meta_value($property_id, '_property_views', 0));
    }

    /**
     * Get plucked terms for a property
     *
     * @param int    $property_id Property ID.
     * @param string $taxonomy    Taxonomy name.
     * @param string $field       Field to pluck.
     * @return array
     */
    public function get_property_terms($property_id, $taxonomy, $field = 'name')
    {
        $terms = get_the_terms($property_id, $taxonomy);

        if (is_wp_error($terms) || empty($terms)) {
            return array();
        }

        return wp_list_pluck($terms, $field);
    }

    /**
     * Format large numbers (1K, 1M, etc.)
     *
     * @param int    $number Number to format.
     * @param string $suffix Suffix to append.
     * @return string
     */
    public function format_large_number($number, $suffix = '+')
    {
        $number = absint($number);

        if ($number < 1000) {
            return (string) $number;
        } elseif ($number < 1000000) {
            return floor($number / 1000) . 'K' . $suffix;
        } elseif ($number < 1000000000) {
            return floor($number / 1000000) . 'M' . $suffix;
        } else {
            return floor($number / 1000000000) . 'B' . $suffix;
        }
    }

    /**
     * Get plugin setting
     *
     * @param string $key     Setting key.
     * @param mixed  $default Default value.
     * @return mixed
     */
    public function get_plugin_setting($key, $default = '')
    {
        $settings = get_option('hvnly_settings', array());
        return isset($settings[$key]) ? $settings[$key] : $default;
    }

    /**
     * Sanitize array of data
     *
     * @param array $data Data to sanitize.
     * @return array
     */
    public function sanitize_array($data)
    {
        if (!is_array($data)) {
            return array();
        }

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = $this->sanitize_array($value);
            } else {
                $data[$key] = sanitize_text_field($value);
            }
        }

        return $data;
    }

    /**
     * Validate email address
     *
     * @param string $email Email to validate.
     * @return bool
     */
    public function is_valid_email($email)
    {
        return is_email($email) !== false;
    }

    /**
     * Check if current page is property archive
     *
     * @return bool
     */
    public function is_property_archive()
    {
        return is_post_type_archive('hvnly_property') || is_tax(get_object_taxonomies('hvnly_property'));
    }

    /**
     * Get current URL with query args
     *
     * @param array $args Query arguments.
     * @return string
     */
    public function get_current_url($args = array())
    {
        global $wp;
        $current_url = home_url($wp->request);

        if (!empty($args)) {
            $current_url = add_query_arg($args, $current_url);
        }

        return esc_url($current_url);
    }

    // ========================================================
    // CURRENCY AND PRICE FORMATTING FUNCTIONS
    // ========================================================

    /**
     * Get currency settings from database
     *
     * @return array
     */
    public function get_currency_settings()
    {
        $settings_manager = \HvnlyNab\Core\SettingsManager::get_instance();
        $general_settings = $settings_manager->get_general_settings();
        
        $defaults = [
            'hvnly_currencyType' => 'USD',
            'hvnly_currencyPositionType' => 'LEFT',
            'hvnly_thousandSeparator' => ',',
            'hvnly_decimalSeparator' => '.',
            'hvnly_numberOfDecimals' => '0',
            'hvnly_EnabledCurrencyFormat' => true,
            'hvnly_thousandText' => 'K',
            'hvnly_millionText' => 'M',
            'hvnly_billionText' => 'B',
            'hvnly_priceOnCallText' => 'priceOnCallNone',
            'hvnly_priceFormat' => 'comma',
        ];
        
        // Get currency settings from general group
        $currency_settings = [];
        foreach ($defaults as $key => $default) {
            $currency_settings[$key] = isset($general_settings[$key]) ? $general_settings[$key] : $default;
        }
        
        return $currency_settings;
    }

    /**
     * Full ISO currency code → symbol map (same source as Settings currencyData).
     *
     * @return array<string, string>
     */
    public function get_currency_symbols_map()
    {
        return array(
            'AED' => 'د.إ',
            'AFN' => '؋',
            'ALL' => 'L',
            'AMD' => 'AMD',
            'ANG' => 'ƒ',
            'AOA' => 'Kz',
            'ARS' => '$',
            'AUD' => '$',
            'AWG' => 'Afl.',
            'AZN' => 'AZN',
            'BAM' => 'KM',
            'BBD' => '$',
            'BDT' => '৳',
            'BGN' => 'лв.',
            'BHD' => '.د.ب',
            'BIF' => 'Fr',
            'BMD' => '$',
            'BND' => '$',
            'BOB' => 'Bs.',
            'BRL' => 'R$',
            'BSD' => '$',
            'BTC' => '₿',
            'BTN' => 'Nu.',
            'BWP' => 'P',
            'BYN' => 'Br',
            'BYR' => 'Br',
            'BZD' => '$',
            'CAD' => 'C$',
            'CDF' => 'Fr',
            'CHF' => 'CHF',
            'CLP' => '$',
            'CNY' => '¥',
            'COP' => '$',
            'CRC' => '₡',
            'CUC' => '$',
            'CUP' => '$',
            'CVE' => '$',
            'CZK' => 'Kč',
            'DJF' => 'Fr',
            'DKK' => 'DKK',
            'DOP' => 'RD$',
            'DZD' => 'د.ج',
            'EGP' => 'EGP',
            'ERN' => 'Nfk',
            'ETB' => 'Br',
            'EUR' => '€',
            'FJD' => '$',
            'FKP' => '£',
            'GBP' => '£',
            'GEL' => 'ლ',
            'GGP' => '£',
            'GHS' => '₵',
            'GIP' => '£',
            'GMD' => 'D',
            'GNF' => 'Fr',
            'GTQ' => 'Q',
            'GYD' => '$',
            'HKD' => 'HK$',
            'HNL' => 'L',
            'HRK' => 'Kn',
            'HTG' => 'G',
            'HUF' => 'Ft',
            'IDR' => 'Rp',
            'ILS' => '₪',
            'IMP' => '£',
            'INR' => '₹',
            'IQD' => 'ع.د',
            'IRR' => '﷼',
            'IRT' => 'تومان',
            'ISK' => 'kr.',
            'JEP' => '£',
            'JMD' => '$',
            'JOD' => 'د.ا',
            'JPY' => '¥',
            'KES' => 'KSh',
            'KGS' => 'сом',
            'KHR' => '៛',
            'KMF' => 'Fr',
            'KPW' => '₩',
            'KRW' => '₩',
            'KWD' => 'د.ك',
            'KYD' => '$',
            'KZT' => 'KZT',
            'LAK' => '₭',
            'LBP' => 'ل.ل',
            'LKR' => 'රු',
            'LRD' => '$',
            'LSL' => 'L',
            'LYD' => 'ل.د',
            'MAD' => 'د.م.',
            'MDL' => 'MDL',
            'MGA' => 'Ar',
            'MKD' => 'ден',
            'MMK' => 'Ks',
            'MNT' => '₮',
            'MOP' => 'MOP$',
            'MRO' => 'UM',
            'MUR' => '₨',
            'MVR' => '.ރ',
            'MWK' => 'MK',
            'MXN' => '$',
            'MYR' => 'RM',
            'MZN' => 'MT',
            'NAD' => 'N$',
            'NGN' => '₦',
            'NIO' => 'C$',
            'NOK' => 'kr',
            'NPR' => '₨',
            'NZD' => '$',
            'OMR' => 'ر.ع.',
            'PAB' => 'B/.',
            'PEN' => 'S/.',
            'PGK' => 'K',
            'PHP' => '₱',
            'PKR' => '₨',
            'PLN' => 'zł',
            'PRB' => 'р.',
            'PYG' => '₲',
            'QAR' => 'ر.ق',
            'RON' => 'lei',
            'RSD' => 'дин.',
            'RUB' => '₽',
            'RWF' => 'Fr',
            'SAR' => 'ر.س',
            'SBD' => '$',
            'SCR' => '₨',
            'SDG' => 'ج.س.',
            'SEK' => 'kr',
            'SGD' => '$',
            'SHP' => '£',
            'SLL' => 'Le',
            'SOS' => 'Sh',
            'SRD' => '$',
            'SSP' => '£',
            'STD' => 'Db',
            'SYP' => 'ل.س',
            'SZL' => 'L',
            'THB' => '฿',
            'TJS' => 'ЅМ',
            'TMT' => 'm',
            'TND' => 'د.ت',
            'TOP' => 'T$',
            'TRY' => '₺',
            'TTD' => '$',
            'TWD' => 'NT$',
            'TZS' => 'Sh',
            'UAH' => '₴',
            'UGX' => 'UGX',
            'USD' => '$',
            'UYU' => '$',
            'UZS' => 'UZS',
            'VEF' => 'Bs F',
            'VND' => '₫',
            'VUV' => 'Vt',
            'WST' => 'T',
            'XAF' => 'CFA',
            'XCD' => '$',
            'XOF' => 'CFA',
            'XPF' => 'Fr',
            'YER' => '﷼',
            'ZAR' => 'R',
            'ZMW' => 'ZK',
        );
    }

    /**
     * Get currency symbol by currency code
     * Matches all currency codes from the currencyData array
     *
     * @param string $currency_code
     * @return string
     */
    public function get_currency_symbol($currency_code = 'USD')
    {
        $currency_symbols = $this->get_currency_symbols_map();
        return isset($currency_symbols[$currency_code]) ? $currency_symbols[$currency_code] : $currency_code;
    }

    /**
     * Get current currency symbol
     *
     * @return string
     */
    public function get_current_currency_symbol()
    {
        $currency_settings = $this->get_currency_settings();
        $currency_code = $currency_settings['hvnly_currencyType'] ?? 'USD';
        return $this->get_currency_symbol($currency_code);
    }

    /**
     * Get current currency code
     *
     * @return string
     */
    public function get_current_currency_code()
    {
        $currency_settings = $this->get_currency_settings();
        return $currency_settings['hvnly_currencyType'] ?? 'USD';
    }

    /**
     * Format number with custom separators
     *
     * @param float $number
     * @param string $thousand_separator
     * @param string $decimal_separator
     * @param int $decimals
     * @return string
     */
    public function format_number_with_separators($number, $thousand_separator = ',', $decimal_separator = '.', $decimals = 0)
    {
        $number = floatval($number);
        $formatted = number_format($number, $decimals, $decimal_separator, $thousand_separator);
        return $formatted;
    }

    /**
     * Format large numbers with K, M, B suffixes
     *
     * @param float $number
     * @param string $thousand_text
     * @param string $million_text
     * @param string $billion_text
     * @param bool $enabled
     * @return string
     */
    public function format_large_number_with_suffix($number, $thousand_text = 'K', $million_text = 'M', $billion_text = 'B', $enabled = true)
    {
        if (!$enabled) {
            return number_format_i18n($number, 0);
        }
        
        $number = floatval($number);
        
        if ($number >= 1000000000) {
            return round($number / 1000000000, 1) . $billion_text;
        } elseif ($number >= 1000000) {
            return round($number / 1000000, 1) . $million_text;
        } elseif ($number >= 1000) {
            return round($number / 1000, 1) . $thousand_text;
        }
        
        return number_format_i18n($number, 0);
    }

    /**
     * Convert price to float safely with comprehensive cleaning.
     *
     * @param mixed $price Price value to convert.
     * @return float Cleaned price as float.
     */
    public function safe_price_to_float($price)
    {
        // Handle empty values
        if (empty($price) && $price !== '0' && $price !== 0) {
            return 0.0;
        }

        // Convert to string for processing
        $price_str = (string) $price;

        // Remove all non-numeric characters except decimal point and minus sign
        $price_str = preg_replace('/[^0-9\.\-]/', '', $price_str);

        // If empty after cleaning, return 0
        if ('' === $price_str || '-' === $price_str) {
            return 0.0;
        }

        // Convert to float
        $float_price = (float) $price_str;

        // Verify it's a valid finite number
        if (!is_finite($float_price)) {
            return 0.0;
        }

        return $float_price;
    }

    /**
     * Format property price with WordPress.org standards and safe type handling.
     * Now integrates with:
     * 1. Individual property custom price label (from price_label field switcher)
     * 2. Global Price on Call Text setting
     * 3. Numeric price formatting
     *
     * @param mixed $price Price value to format.
     * @param int $property_id Optional property ID for individual custom labels
     * @return string Formatted price string.
     */
    public function format_price($price, $property_id = null)
    {
        // If property_id not provided, try to get it from current post
        if (empty($property_id)) {
            $property_id = get_the_ID();
        }

        // STEP 1: Check for INDIVIDUAL custom price label (from price_label field)
        if (!empty($property_id)) {
            $stored_value = get_post_meta($property_id, '_hvnly_property_price', true);
            
            if (!empty($stored_value) && is_string($stored_value)) {
                // Check if it's JSON encoded custom label
                if (strlen($stored_value) > 0 && $stored_value[0] === '{') {
                    $decoded = json_decode($stored_value, true);
                    if (is_array($decoded) && isset($decoded['__type']) && $decoded['__type'] === 'custom_label') {
                        $label_value = isset($decoded['value']) ? $decoded['value'] : '';
                        $label_text = isset($decoded['label']) ? trim(preg_replace('/\s+/', ' ', $decoded['label'])) : '';
                        
                        if (!empty($label_value) && $label_value !== 'priceOnCallNone') {
                            // First try to get updated label from database options
                            $db_label = $this->get_price_on_call_label_by_value($label_value);
                            if (!empty($db_label)) {
                                return apply_filters('hvnly_price_on_call_text', $db_label);
                            }
                            // Fallback to stored label
                            if (!empty($label_text)) {
                                return apply_filters('hvnly_price_on_call_text', $label_text);
                            }
                        }
                    }
                }
                
                // Check if it's a regular string value
                if (!empty($stored_value) && $stored_value !== 'priceOnCallNone') {
                    $db_label = $this->get_price_on_call_label_by_value($stored_value);
                    if (!empty($db_label)) {
                        return apply_filters('hvnly_price_on_call_text', $db_label);
                    }
                }
            }
        }

        // STEP 2: Handle empty/null values
        if (empty($price) && $price !== '0' && $price !== 0) {
            return apply_filters('hvnly_empty_price_text', __('Price on Request', 'havenlytics'));
        }

        // STEP 3: Get currency settings
        $currency_settings = $this->get_currency_settings();
        
        // STEP 4: Get global price on call text setting
        $price_on_call_value = isset($currency_settings['hvnly_priceOnCallText']) ? $currency_settings['hvnly_priceOnCallText'] : 'priceOnCallNone';
        
        // STEP 5: Check if global price is "Price on Call"
        if ($price_on_call_value !== 'priceOnCallNone') {
            $custom_label = $this->get_price_on_call_label_by_value($price_on_call_value);
            
            if ($custom_label) {
                return apply_filters('hvnly_price_on_call_text', $custom_label);
            }
            
            $price_on_call_labels = [
                'priceOnCall' => __('Price on Call', 'havenlytics'),
                'fixedPrice' => __('Fixed Price', 'havenlytics'),
                'guidePrice' => __('Guide Price', 'havenlytics'),
                'offersOver' => __('Offers Over', 'havenlytics'),
            ];
            
            if (isset($price_on_call_labels[$price_on_call_value])) {
                return apply_filters('hvnly_price_on_call_text', $price_on_call_labels[$price_on_call_value]);
            }
            
            if (!empty($price_on_call_value) && $price_on_call_value !== 'priceOnCallNone') {
                $custom_text = ucwords(str_replace(['_', '-'], ' ', $price_on_call_value));
                return apply_filters('hvnly_price_on_call_text', $custom_text);
            }
        }

        // STEP 6: Convert to float safely for numeric price
        $numeric_price = $this->safe_price_to_float($price);

        if ($numeric_price <= 0) {
            return apply_filters('hvnly_empty_price_text', __('Price on Request', 'havenlytics'));
        }

        // STEP 7: Format numeric price
        $currency_code = isset($currency_settings['hvnly_currencyType']) ? $currency_settings['hvnly_currencyType'] : 'USD';
        $currency_position = isset($currency_settings['hvnly_currencyPositionType']) ? $currency_settings['hvnly_currencyPositionType'] : 'LEFT';
        $thousand_separator = isset($currency_settings['hvnly_thousandSeparator']) ? $currency_settings['hvnly_thousandSeparator'] : ',';
        $decimal_separator = isset($currency_settings['hvnly_decimalSeparator']) ? $currency_settings['hvnly_decimalSeparator'] : '.';
        $number_of_decimals = intval(isset($currency_settings['hvnly_numberOfDecimals']) ? $currency_settings['hvnly_numberOfDecimals'] : 0);
        $price_format = isset($currency_settings['hvnly_priceFormat']) ? $currency_settings['hvnly_priceFormat'] : 'comma';
        $enable_large_format = isset($currency_settings['hvnly_EnabledCurrencyFormat']) ? $currency_settings['hvnly_EnabledCurrencyFormat'] : true;
        $thousand_text = isset($currency_settings['hvnly_thousandText']) ? $currency_settings['hvnly_thousandText'] : 'K';
        $million_text = isset($currency_settings['hvnly_millionText']) ? $currency_settings['hvnly_millionText'] : 'M';
        $billion_text = isset($currency_settings['hvnly_billionText']) ? $currency_settings['hvnly_billionText'] : 'B';
        
        $currency_symbol = $this->get_currency_symbol($currency_code);
        
        if ($price_format === 'comma') {
            $formatted_number = $this->format_number_with_separators($numeric_price, ',', '.', $number_of_decimals);
        } elseif ($price_format === 'dot') {
            $formatted_number = $this->format_number_with_separators($numeric_price, '.', ',', $number_of_decimals);
        } else {
            $formatted_number = $this->format_number_with_separators($numeric_price, ' ', '.', $number_of_decimals);
        }
        
        $use_large_format = apply_filters('hvnly_use_large_number_format', $enable_large_format);
        
        if ($use_large_format && $numeric_price >= 1000) {
            $large_formatted = $this->format_large_number_with_suffix(
                $numeric_price, 
                $thousand_text, 
                $million_text, 
                $billion_text, 
                $enable_large_format
            );
            
            if ('LEFT' === $currency_position) {
                return $currency_symbol . $large_formatted;
            } else {
                return $large_formatted . $currency_symbol;
            }
        }
        
        if ('LEFT' === $currency_position) {
            return $currency_symbol . $formatted_number;
        } else {
            return $formatted_number . $currency_symbol;
        }
    }


    /**
     * Get price on call label by value (supports custom options)
     * UPDATED: Better label retrieval
     *
     * @since 2.1.2
     * @param string $value
     * @return string|null
     */
    public function get_price_on_call_label_by_value($value)
    {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'hvnly_price_on_call_texts';
        
        // First, try to get from the options table (custom options)
        $custom_options = get_option('hvnly_price_on_call_custom_options', []);
        
        if (!empty($custom_options) && is_array($custom_options)) {
            foreach ($custom_options as $option) {
                if (isset($option['value']) && $option['value'] === $value) {
                    $clean_label = isset($option['label']) ? trim(preg_replace('/\s+/', ' ', $option['label'])) : '';
                    if (!empty($clean_label)) {
                        return $clean_label;
                    }
                }
            }
        }
        
        // Check if table exists
        $table_exists = $wpdb->get_var(
            $wpdb->prepare("SHOW TABLES LIKE %s", $wpdb->esc_like($table_name))
        );
        
        if ($table_exists === $table_name) {
            $cache_key = 'hvnly_price_on_call_label_' . md5($value);
            $label = wp_cache_get($cache_key, 'havenlytics');
            
            if (false === $label) {
                $label = $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT label FROM {$wpdb->prefix}hvnly_price_on_call_texts WHERE value = %s",
                        $value
                    )
                );
                
                if ($label) {
                    $clean_label = trim(preg_replace('/\s+/', ' ', $label));
                    wp_cache_set($cache_key, $clean_label, 'havenlytics', HOUR_IN_SECONDS);
                    return $clean_label;
                }
            } else {
                return $label;
            }
        }
        
        // Fallback for default values
        $default_labels = [
            'priceOnCall' => __('Price on Call', 'havenlytics'),
            'fixedPrice' => __('Fixed Price', 'havenlytics'),
            'guidePrice' => __('Guide Price', 'havenlytics'),
            'offersOver' => __('Offers Over', 'havenlytics'),
        ];
        
        return isset($default_labels[$value]) ? $default_labels[$value] : null;
    }


    // ========================================================
    // NUMERIC PRICE FORMATTING FOR FILTERS (No custom labels)
    // ========================================================

    /**
     * Format numeric price for filter dropdowns (NO custom label processing)
     * Always returns formatted number with currency symbol
     *
     * @since 2.1.4
     * @param mixed $price Price value
     * @return string Formatted numeric price
     */
    public function format_numeric_price_for_filter($price)
    {
        // Convert to float safely
        $numeric_price = $this->safe_price_to_float($price);
        
        // Return empty if not a valid number
        if ($numeric_price <= 0) {
            return '';
        }
        
        // Get currency settings
        $currency_settings = $this->get_currency_settings();
        
        $currency_code = $currency_settings['hvnly_currencyType'] ?? 'USD';
        $currency_position = $currency_settings['hvnly_currencyPositionType'] ?? 'LEFT';
        $thousand_separator = $currency_settings['hvnly_thousandSeparator'] ?? ',';
        $decimal_separator = $currency_settings['hvnly_decimalSeparator'] ?? '.';
        $number_of_decimals = intval($currency_settings['hvnly_numberOfDecimals'] ?? 0);
        $price_format = $currency_settings['hvnly_priceFormat'] ?? 'comma';
        $enable_large_format = $currency_settings['hvnly_EnabledCurrencyFormat'] ?? true;
        $thousand_text = $currency_settings['hvnly_thousandText'] ?? 'K';
        $million_text = $currency_settings['hvnly_millionText'] ?? 'M';
        $billion_text = $currency_settings['hvnly_billionText'] ?? 'B';
        
        $currency_symbol = $this->get_currency_symbol($currency_code);
        
        // Format number with separators
        if ($price_format === 'comma') {
            $formatted_number = $this->format_number_with_separators($numeric_price, ',', '.', $number_of_decimals);
        } elseif ($price_format === 'dot') {
            $formatted_number = $this->format_number_with_separators($numeric_price, '.', ',', $number_of_decimals);
        } else {
            $formatted_number = $this->format_number_with_separators($numeric_price, ' ', '.', $number_of_decimals);
        }
        
        // Use large format if enabled
        if ($enable_large_format && $numeric_price >= 1000) {
            $large_formatted = $this->format_large_number_with_suffix(
                $numeric_price,
                $thousand_text,
                $million_text,
                $billion_text,
                $enable_large_format
            );
            
            if ('LEFT' === $currency_position) {
                return $currency_symbol . $large_formatted;
            } else {
                return $large_formatted . $currency_symbol;
            }
        }
        
        if ('LEFT' === $currency_position) {
            return $currency_symbol . $formatted_number;
        } else {
            return $formatted_number . $currency_symbol;
        }
    }

    /**
     * Format price option for filter dropdown with "plus" suffix for max price
     *
     * @since 2.1.4
     * @param mixed $price Price value
     * @param bool $is_plus Whether to add "+" suffix
     * @return string Formatted price
     */
    public function format_filter_price_option($price, $is_plus = false)
    {
        $formatted = $this->format_numeric_price_for_filter($price);
        
        if ($is_plus && !empty($formatted)) {
            return $formatted . '+';
        }
        
        return $formatted;
    }



    // ========================================================
    // DYNAMIC SEARCH FILTER SIDEBAR FUNCTIONS (Added for v2.1.0)
    // ========================================================

    /**
     * Get dynamic search filter fields from plugin settings
     *
     * @since 2.1.0
     * @return array
     */
    public function hvnly_filter_sidebar_get_fields()
    {
        $settings = get_option('hvnly_plugin_settings', []);
        $search_settings = isset($settings['search']) ? $settings['search'] : [];
        
        $fields = isset($search_settings['hvnly_search_fields']) ? $search_settings['hvnly_search_fields'] : [];
        
        // If no fields found, return defaults
        if (empty($fields)) {
            return $this->hvnly_get_default_search_filter_fields();
        }
        
        // Filter only enabled fields
        $enabled_fields = array_filter($fields, function($field) {
            return isset($field['enabled']) && $field['enabled'] === true;
        });
        
        // Sort by order
        usort($enabled_fields, function($a, $b) {
            return ($a['order'] ?? 999) - ($b['order'] ?? 999);
        });
        
        return apply_filters('hvnly_filter_sidebar_fields', $enabled_fields);
    }

    /**
     * Get specific search field configuration by ID
     *
     * @since 2.1.0
     * @param string $field_id
     * @return array|null
     */
    public function hvnly_filter_sidebar_get_field_config($field_id)
    {
        $fields = $this->hvnly_filter_sidebar_get_fields();
        
        foreach ($fields as $field) {
            if ($field['id'] === $field_id) {
                return isset($field['config']) ? $field['config'] : [];
            }
        }
        
        return null;
    }

    /**
     * Get min price options for search filter sidebar (FIXED: uses numeric formatting only)
     *
     * @since 2.1.0
     * @return array
     */
    public function hvnly_filter_sidebar_get_min_price_options()
    {
        $config = $this->hvnly_filter_sidebar_get_field_config('price');
        
        if ($config && isset($config['minOptions']) && !empty($config['minOptions'])) {
            $options = [];
            foreach ($config['minOptions'] as $value) {
                if (!empty($value)) {
                    // ALWAYS use numeric price formatting, NOT the custom label format
                    $options[$value] = $this->format_numeric_price_for_filter((float) $value);
                }
            }
            return $options;
        }
        
        // Default fallback with numeric formatting
        return [
            '100000' => $this->format_numeric_price_for_filter(100000),
            '200000' => $this->format_numeric_price_for_filter(200000),
            '300000' => $this->format_numeric_price_for_filter(300000),
            '400000' => $this->format_numeric_price_for_filter(400000),
            '500000' => $this->format_numeric_price_for_filter(500000),
            '600000' => $this->format_numeric_price_for_filter(600000),
            '700000' => $this->format_numeric_price_for_filter(700000),
            '800000' => $this->format_numeric_price_for_filter(800000),
            '900000' => $this->format_numeric_price_for_filter(900000),
            '1000000' => $this->format_numeric_price_for_filter(1000000)
        ];
    }

    /**
     * Get max price options for search filter sidebar (FIXED: uses numeric formatting only)
     *
     * @since 2.1.0
     * @return array
     */
    public function hvnly_filter_sidebar_get_max_price_options()
    {
        $config = $this->hvnly_filter_sidebar_get_field_config('price');
        
        if ($config && isset($config['maxOptions']) && !empty($config['maxOptions'])) {
            $options = [];
            foreach ($config['maxOptions'] as $value) {
                if (!empty($value)) {
                    $is_plus = (strpos($value, '+') !== false);
                    $clean_value = str_replace('+', '', $value);
                    $options[$value] = $this->format_filter_price_option($clean_value, $is_plus);
                }
            }
            return $options;
        }
        
        // Default fallback with numeric formatting
        return [
            '200000' => $this->format_numeric_price_for_filter(200000),
            '300000' => $this->format_numeric_price_for_filter(300000),
            '400000' => $this->format_numeric_price_for_filter(400000),
            '500000' => $this->format_numeric_price_for_filter(500000),
            '600000' => $this->format_numeric_price_for_filter(600000),
            '700000' => $this->format_numeric_price_for_filter(700000),
            '800000' => $this->format_numeric_price_for_filter(800000),
            '900000' => $this->format_numeric_price_for_filter(900000),
            '1000000' => $this->format_numeric_price_for_filter(1000000),
            '1500000' => $this->format_numeric_price_for_filter(1500000) . '+'
        ];
    }

    /**
     * Get bedroom options for search filter sidebar
     *
     * @since 2.1.0
     * @return array
     */
    public function hvnly_filter_sidebar_get_bedroom_options()
    {
        $config = $this->hvnly_filter_sidebar_get_field_config('bedrooms_bathrooms');
        
        if ($config && isset($config['subFields'])) {
            foreach ($config['subFields'] as $subField) {
                if ($subField['id'] === 'bedrooms' && isset($subField['options'])) {
                    return $subField['options'];
                }
            }
        }
        
        // Default fallback
        return ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '10+'];
    }

    /**
     * Get bathroom options for search filter sidebar
     *
     * @since 2.1.0
     * @return array
     */
    public function hvnly_filter_sidebar_get_bathroom_options()
    {
        $config = $this->hvnly_filter_sidebar_get_field_config('bedrooms_bathrooms');
        
        if ($config && isset($config['subFields'])) {
            foreach ($config['subFields'] as $subField) {
                if ($subField['id'] === 'bathrooms' && isset($subField['options'])) {
                    return $subField['options'];
                }
            }
        }
        
        // Default fallback
        return ['0', '1', '2', '3', '4', '5', '6', '7', '8+'];
    }

    /**
     * Get reception rooms options for search filter sidebar
     *
     * @since 2.1.0
     * @return array
     */
    public function hvnly_filter_sidebar_get_reception_rooms_options()
    {
        $config = $this->hvnly_filter_sidebar_get_field_config('reception_rooms');
        
        if ($config && isset($config['options']) && !empty($config['options'])) {
            return $config['options'];
        }
        
        // Default fallback
        return ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '10+'];
    }

    /**
     * Get custom dropdown options for search filter sidebar
     *
     * @since 2.1.0
     * @param string $field_id
     * @return array
     */
    public function hvnly_filter_sidebar_get_custom_dropdown_options($field_id)
    {
        $config = $this->hvnly_filter_sidebar_get_field_config($field_id);
        
        if ($config && isset($config['options']) && !empty($config['options'])) {
            return $config['options'];
        }
        
        return [];
    }

    /**
     * Get custom checkbox options for search filter sidebar
     *
     * @since 2.1.0
     * @param string $field_id
     * @return array
     */
    public function hvnly_filter_sidebar_get_custom_checkbox_options($field_id)
    {
        $config = $this->hvnly_filter_sidebar_get_field_config($field_id);
        
        if ($config && isset($config['options']) && !empty($config['options'])) {
            return $config['options'];
        }
        
        return [];
    }

    /**
     * Check if field uses taxonomy for search filter sidebar
     *
     * @since 2.1.0
     * @param string $field_id
     * @return bool
     */
    public function hvnly_filter_sidebar_field_uses_taxonomy($field_id)
    {
        $config = $this->hvnly_filter_sidebar_get_field_config($field_id);
        
        return isset($config['useTaxonomy']) && $config['useTaxonomy'] === true;
    }

    /**
     * Get taxonomy name for search filter field
     *
     * @since 2.1.0
     * @param string $field_id
     * @return string|null
     */
    public function hvnly_filter_sidebar_get_field_taxonomy($field_id)
    {
        $config = $this->hvnly_filter_sidebar_get_field_config($field_id);
        
        if ($config && isset($config['useTaxonomy']) && $config['useTaxonomy'] === true) {
            $taxonomy = isset($config['taxonomy']) ? $config['taxonomy'] : null;

            if ('status' === $field_id && 'hvnly_prop_depts' === $taxonomy) {
                return 'hvnly_prop_status';
            }

            return $taxonomy;
        }
        
        return null;
    }

    /**
     * Get taxonomy terms for search filter sidebar with caching
     *
     * @since 2.1.0
     * @param string $taxonomy
     * @return array
     */
    public function hvnly_filter_sidebar_get_taxonomy_terms($taxonomy)
    {
        $cache_key = 'hvnly_filter_sidebar_terms_' . $taxonomy;
        $terms = wp_cache_get($cache_key, 'havenlytics');
        
        if (false === $terms) {
            $terms = get_terms([
                'taxonomy' => $taxonomy,
                'hide_empty' => true,
            ]);
            
            if (!is_wp_error($terms)) {
                wp_cache_set($cache_key, $terms, 'havenlytics', HOUR_IN_SECONDS);
            } else {
                $terms = [];
            }
        }
        
        return $terms;
    }

    /**
     * Get current filter values from request for search filter sidebar
     *
     * @since 2.1.0
     * @return array
     */
    public function hvnly_filter_sidebar_get_current_values()
    {
        $filters = [];
        
        // Price range
        if (isset($_GET['min_price']) && !empty($_GET['min_price'])) {
            $filters['min_price'] = sanitize_text_field($_GET['min_price']);
        }
        if (isset($_GET['max_price']) && !empty($_GET['max_price'])) {
            $filters['max_price'] = sanitize_text_field($_GET['max_price']);
        }
        
        // Bedrooms & Bathrooms
        if (isset($_GET['bedrooms']) && !empty($_GET['bedrooms'])) {
            $filters['bedrooms'] = sanitize_text_field($_GET['bedrooms']);
        }
        if (isset($_GET['bathrooms']) && !empty($_GET['bathrooms'])) {
            $filters['bathrooms'] = sanitize_text_field($_GET['bathrooms']);
        }
        
        // Reception rooms
        if (isset($_GET['reception_rooms']) && !empty($_GET['reception_rooms'])) {
            $filters['reception_rooms'] = sanitize_text_field($_GET['reception_rooms']);
        }
        
        // Taxonomy filters
        $taxonomies = ['hvnly_prop_types', 'hvnly_prop_locations', 'hvnly_prop_features', 'hvnly_prop_tags', 'hvnly_prop_badges', 'hvnly_prop_status', 'hvnly_prop_depts'];
        foreach ($taxonomies as $tax) {
            if (isset($_GET[$tax]) && !empty($_GET[$tax])) {
                $values = $_GET[$tax];
                if (is_array($values)) {
                    $filters[$tax] = array_map('sanitize_text_field', $values);
                } else {
                    $filters[$tax] = [sanitize_text_field($values)];
                }
            }
        }
        
        // Property IDs
        if (isset($_GET['property_ids']) && !empty($_GET['property_ids'])) {
            $values = $_GET['property_ids'];
            if (is_array($values)) {
                $filters['property_ids'] = array_map('sanitize_text_field', $values);
            } else {
                $filters['property_ids'] = [sanitize_text_field($values)];
            }
        }
        
        return apply_filters('hvnly_filter_sidebar_current_values', $filters);
    }

	/**
	 * Check if search filter sidebar should be shown.
	 *
	 * Evaluates settings and determines if the filter sidebar should
	 * be displayed on the current page.
	 *
	 * @return bool True if sidebar should be shown.
	 * @since 2.1.0
	 */
	public function hvnly_filter_sidebar_should_show() {
		// Check if hide left search bar setting is enabled.
		$settings_manager = \HvnlyNab\Core\SettingsManager::get_instance();
		$search_settings  = $settings_manager->get_search_settings();

		$hide_left_search_bar = isset( $search_settings['hvnly_hideLeftSearchBar'] )
			? (bool) $search_settings['hvnly_hideLeftSearchBar']
			: false;

		if ( $hide_left_search_bar ) {
			return false;
		}

		// Get fields - this will now return defaults if empty.
		$fields = $this->hvnly_filter_sidebar_get_fields();

		// Check if there are any enabled fields.
		$has_enabled_fields = ! empty( $fields );

		return $has_enabled_fields;
	}


    /**
     * Check if a search filter field is locked (cannot be deleted/edited)
     *
     * @since 2.1.0
     * @param array $field
     * @return bool
     */
    public function hvnly_filter_sidebar_is_field_locked($field)
    {
        // Property ID is always locked
        if (isset($field['id']) && $field['id'] === 'property_id') {
            return true;
        }
        
        return isset($field['is_locked']) && $field['is_locked'] === true;
    }

    /**
     * Get unique property IDs for search filter sidebar
     *
     * @since 2.1.0
     * @return array
     */
    public function hvnly_filter_sidebar_get_unique_property_ids()
    {
        global $wpdb;
        
        $cache_key = 'hvnly_filter_sidebar_unique_property_ids';
        $property_ids = wp_cache_get($cache_key, 'havenlytics');
        
        if (false === $property_ids) {
            $property_ids = $wpdb->get_col("
                SELECT DISTINCT meta_value 
                FROM {$wpdb->postmeta} 
                WHERE meta_key = '_hvnly_unique_property_id' 
                AND meta_value != '' 
                ORDER BY meta_value ASC
                LIMIT 500
            ");
            
            wp_cache_set($cache_key, $property_ids, 'havenlytics', HOUR_IN_SECONDS);
        }
        
        return apply_filters('hvnly_filter_sidebar_unique_property_ids', $property_ids);
    }

    /**
     * Get placeholder text for search filter field
     *
     * @since 2.1.0
     * @param string $field_id
     * @param string $default
     * @return string
     */
    public function hvnly_filter_sidebar_get_field_placeholder($field_id, $default = '')
    {
        $config = $this->hvnly_filter_sidebar_get_field_config($field_id);
        
        if ($config && isset($config['placeholder']) && !empty($config['placeholder'])) {
            return $config['placeholder'];
        }
        
        return $default;
    }

    /**
     * Check if select all option is enabled for checkbox field
     *
     * @since 2.1.0
     * @param string $field_id
     * @return bool
     */
    public function hvnly_filter_sidebar_has_select_all_option($field_id)
    {
        $config = $this->hvnly_filter_sidebar_get_field_config($field_id);
        
        return isset($config['selectAllOption']) && $config['selectAllOption'] === true;
    }

    
    /**
     * Get default search filter fields (fallback when no settings exist)
     *
     * @since 2.1.0
     * @return array
     */
    public function hvnly_get_default_search_filter_fields()
    {
        return [
            [
                'id' => 'price',
                'title' => __('Price', 'havenlytics'),
                'type' => 'range',
                'enabled' => true,
                'order' => 1,
                'is_default' => true
            ],
            [
                'id' => 'status',
                'title' => __('Status', 'havenlytics'),
                'type' => 'dropdown',
                'enabled' => true,
                'order' => 2,
                'is_default' => true
            ],
            [
                'id' => 'bedrooms_bathrooms',
                'title' => __('Bedrooms & Bathrooms', 'havenlytics'),
                'type' => 'group',
                'enabled' => true,
                'order' => 3,
                'is_default' => true
            ],
            [
                'id' => 'reception_rooms',
                'title' => __('Reception Rooms', 'havenlytics'),
                'type' => 'number',
                'enabled' => true,
                'order' => 4,
                'is_default' => true
            ],
            [
                'id' => 'property_types',
                'title' => __('Property Types', 'havenlytics'),
                'type' => 'checkbox',
                'enabled' => true,
                'order' => 5,
                'is_default' => true
            ],
            [
                'id' => 'locations',
                'title' => __('Locations', 'havenlytics'),
                'type' => 'dropdown',
                'enabled' => true,
                'order' => 6,
                'is_default' => true
            ],
            [
                'id' => 'features',
                'title' => __('Features', 'havenlytics'),
                'type' => 'checkbox',
                'enabled' => true,
                'order' => 7,
                'is_default' => true
            ],
            [
                'id' => 'tags',
                'title' => __('Tags', 'havenlytics'),
                'type' => 'dropdown',
                'enabled' => true,
                'order' => 8,
                'is_default' => true
            ],
            [
                'id' => 'badges',
                'title' => __('Badges', 'havenlytics'),
                'type' => 'checkbox',
                'enabled' => true,
                'order' => 9,
                'is_default' => true
            ],
            [
                'id' => 'property_id',
                'title' => __('Property ID', 'havenlytics'),
                'type' => 'text',
                'enabled' => true,
                'order' => 10,
                'is_default' => true
            ]
        ];
    }





    /**
     * Get top search fields from settings
     *
     * @since 2.1.0
     * @return array
     */
    public function hvnly_get_top_search_fields()
    {
        $settings = get_option('hvnly_plugin_settings', []);
        $search_settings = isset($settings['search']) ? $settings['search'] : [];
        
        $fields = isset($search_settings['hvnly_top_search_fields']) ? $search_settings['hvnly_top_search_fields'] : [];
        
        // If no fields, return defaults
        if (empty($fields)) {
            return $this->hvnly_get_default_top_search_fields();
        }
        
        // Filter only enabled fields and sort by order
        $enabled_fields = array_filter($fields, function($field) {
            return isset($field['enabled']) && $field['enabled'] === true;
        });
        
        usort($enabled_fields, function($a, $b) {
            return ($a['order'] ?? 999) - ($b['order'] ?? 999);
        });
        
        return $enabled_fields;
    }

    /**
     * Get default top search fields
     *
     * @since 2.1.0
     * @return array
     */
    public function hvnly_get_default_top_search_fields()
    {
        return [
            ['id' => 'bedrooms', 'title' => __('Bedrooms', 'havenlytics'), 'type' => 'number', 'enabled' => true, 'order' => 1],
            ['id' => 'bathrooms', 'title' => __('Bathrooms', 'havenlytics'), 'type' => 'number', 'enabled' => true, 'order' => 2],
            ['id' => 'min_price', 'title' => __('Min Price', 'havenlytics'), 'type' => 'range', 'enabled' => true, 'order' => 3],
            ['id' => 'max_price', 'title' => __('Max Price', 'havenlytics'), 'type' => 'range', 'enabled' => true, 'order' => 4],
        ];
    }

    /**
     * Get top search field config by ID
     *
     * @since 2.1.0
     * @param string $field_id
     * @return array|null
     */
    public function hvnly_get_top_search_field_config($field_id)
    {
        $fields = $this->hvnly_get_top_search_fields();
        
        foreach ($fields as $field) {
            if ($field['id'] === $field_id) {
                return isset($field['config']) ? $field['config'] : [];
            }
        }
        
        return null;
    }

    /**
     * Get bedroom options for top search
     *
     * @since 2.1.0
     * @return array
     */
    public function hvnly_top_search_get_bedroom_options()
    {
        $config = $this->hvnly_get_top_search_field_config('bedrooms');
        
        if ($config && isset($config['options']) && !empty($config['options'])) {
            return $config['options'];
        }
        
        return ['1', '2', '3', '4', '5'];
    }

    /**
     * Get bathroom options for top search
     *
     * @since 2.1.0
     * @return array
     */
    public function hvnly_top_search_get_bathroom_options()
    {
        $config = $this->hvnly_get_top_search_field_config('bathrooms');
        
        if ($config && isset($config['options']) && !empty($config['options'])) {
            return $config['options'];
        }
        
        return ['1', '2', '3', '4', '5'];
    }

    /**
     * Get min price options for top search (FIXED: uses numeric formatting only)
     *
     * @since 2.1.0
     * @return array
     */
    public function hvnly_top_search_get_min_price_options()
    {
        $config = $this->hvnly_get_top_search_field_config('min_price');
        
        if ($config && isset($config['options']) && !empty($config['options'])) {
            $options = [];
            foreach ($config['options'] as $value) {
                if (!empty($value)) {
                    // ALWAYS use numeric price formatting, NOT the custom label format
                    $options[$value] = $this->format_numeric_price_for_filter($value);
                }
            }
            return $options;
        }
        
        // Default fallback with numeric formatting
        return [
            '50000' => $this->format_numeric_price_for_filter(50000),
            '75000' => $this->format_numeric_price_for_filter(75000),
            '100000' => $this->format_numeric_price_for_filter(100000),
            '200000' => $this->format_numeric_price_for_filter(200000),
            '300000' => $this->format_numeric_price_for_filter(300000),
            '400000' => $this->format_numeric_price_for_filter(400000),
            '500000' => $this->format_numeric_price_for_filter(500000),
        ];
    }

    /**
     * Get max price options for top search (FIXED: uses numeric formatting only)
     *
     * @since 2.1.0
     * @return array
     */
    public function hvnly_top_search_get_max_price_options()
    {
        $config = $this->hvnly_get_top_search_field_config('max_price');
        
        if ($config && isset($config['options']) && !empty($config['options'])) {
            $options = [];
            foreach ($config['options'] as $value) {
                if (!empty($value)) {
                    $is_plus = (strpos($value, '+') !== false);
                    $clean_value = str_replace('+', '', $value);
                    $options[$value] = $this->format_filter_price_option($clean_value, $is_plus);
                }
            }
            return $options;
        }
        
        // Default fallback with numeric formatting
        return [
            '500000' => $this->format_numeric_price_for_filter(500000),
            '600000' => $this->format_numeric_price_for_filter(600000),
            '700000' => $this->format_numeric_price_for_filter(700000),
            '800000' => $this->format_numeric_price_for_filter(800000),
            '900000' => $this->format_numeric_price_for_filter(900000),
            '1000000' => $this->format_numeric_price_for_filter(1000000) . '+',
        ];
    }





    /**
     * Get main search fields from settings
     *
     * @since 2.1.0
     * @return array
     */
    public function hvnly_get_main_search_fields()
    {
        $settings = get_option('hvnly_plugin_settings', []);
        $search_settings = isset($settings['search']) ? $settings['search'] : [];
        
        $fields = isset($search_settings['hvnly_main_search_fields']) ? $search_settings['hvnly_main_search_fields'] : [];
        
        if (empty($fields)) {
            return $this->hvnly_get_default_main_search_fields();
        }
        
        $enabled_fields = array_filter($fields, function($field) {
            return isset($field['enabled']) && $field['enabled'] === true;
        });
        
        usort($enabled_fields, function($a, $b) {
            return ($a['order'] ?? 999) - ($b['order'] ?? 999);
        });
        
        return $enabled_fields;
    }

    /**
     * Get default main search fields
     *
     * @since 2.1.0
     * @return array
     */
    public function hvnly_get_default_main_search_fields()
    {
        return [
            ['id' => 'keyword_search', 'title' => __('Keyword Search', 'havenlytics'), 'type' => 'text', 'enabled' => true, 'order' => 1],
            ['id' => 'property_type', 'title' => __('Property Type', 'havenlytics'), 'type' => 'taxonomy', 'enabled' => true, 'order' => 2],
            ['id' => 'location', 'title' => __('Location', 'havenlytics'), 'type' => 'taxonomy', 'enabled' => true, 'order' => 3],
        ];
    }

    /**
     * Get main search field config
     *
     * @since 2.1.0
     * @param string $field_id
     * @return array|null
     */
    public function hvnly_get_main_search_field_config($field_id)
    {
        $fields = $this->hvnly_get_main_search_fields();
        foreach ($fields as $field) {
            if ($field['id'] === $field_id) {
                return isset($field['config']) ? $field['config'] : [];
            }
        }
        return null;
    }

    /**
     * Unified placeholder resolver for main, top, and sidebar search fields.
     *
     * @since 3.2.0
     * @param string $field_id Field identifier.
     * @param string $default  Fallback label when no setting exists.
     * @return string
     */
    public function hvnly_get_search_field_placeholder($field_id, $default = '')
    {
        $field_id = (string) $field_id;

        $main_config = $this->hvnly_get_main_search_field_config($field_id);
        if (is_array($main_config) && !empty($main_config['placeholder'])) {
            $placeholder = (string) $main_config['placeholder'];
            return function_exists( 'hvnly_translate_ui' ) ? hvnly_translate_ui( $placeholder ) : $placeholder;
        }

        $top_config = $this->hvnly_get_top_search_field_config($field_id);
        if (is_array($top_config) && !empty($top_config['placeholder'])) {
            $placeholder = (string) $top_config['placeholder'];
            return function_exists( 'hvnly_translate_ui' ) ? hvnly_translate_ui( $placeholder ) : $placeholder;
        }

        $sidebar_placeholder = $this->hvnly_filter_sidebar_get_field_placeholder($field_id, '');
        if ($sidebar_placeholder !== '') {
            return function_exists( 'hvnly_translate_ui' ) ? hvnly_translate_ui( (string) $sidebar_placeholder ) : (string) $sidebar_placeholder;
        }

        foreach ($this->hvnly_get_default_main_search_fields() as $field) {
            if (($field['id'] ?? '') === $field_id && !empty($field['config']['placeholder'])) {
                $placeholder = (string) $field['config']['placeholder'];
                return function_exists( 'hvnly_translate_ui' ) ? hvnly_translate_ui( $placeholder ) : $placeholder;
            }
        }

        foreach ($this->hvnly_get_default_top_search_fields() as $field) {
            if (($field['id'] ?? '') === $field_id && !empty($field['config']['placeholder'])) {
                $placeholder = (string) $field['config']['placeholder'];
                return function_exists( 'hvnly_translate_ui' ) ? hvnly_translate_ui( $placeholder ) : $placeholder;
            }
        }

        if ($default !== '') {
            return function_exists( 'hvnly_translate_ui' ) ? hvnly_translate_ui( (string) $default ) : (string) $default;
        }

        return __('Any', 'havenlytics');
    }











}