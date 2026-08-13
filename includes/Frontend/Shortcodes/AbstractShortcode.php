<?php
/**
 * Abstract Base Shortcode Class
 *
 * Provides common functionality for all Havenlytics shortcodes.
 * Ensures consistent attribute handling, caching, and template loading.
 *
 * @package     Havenlytics
 * @subpackage  Frontend\Shortcodes
 * @copyright   Copyright (c) 2025, Havenlytics
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       2.0.0
 */

namespace HvnlyNab\Frontend\Shortcodes;

// Exit if accessed directly.
if ( ! defined('ABSPATH')) {
    exit;
}

/**
 * Abstract Shortcode class
 *
 * @since 2.0.0
 */
abstract class AbstractShortcode {

    /**
     * Shortcode tag
     *
     * @var string
     */
    protected $tag = '';

    /**
     * Default attributes
     *
     * @var array
     */
    protected $default_atts = array();

    /**
     * Cache group for this shortcode
     *
     * @var string
     */
    protected $cache_group = 'hvnly_shortcodes';

    /**
     * Whether to cache output
     *
     * @var bool
     */
    protected $enable_cache = true;

    /**
     * Cache expiration in seconds (default: 1 hour)
     *
     * @var int
     */
    protected $cache_expiration = HOUR_IN_SECONDS;

    /**
     * Static registry of active shortcodes on current page
     *
     * @var array
     */
    private static $active_shortcodes = array();

    /**
     * Whether body class filter has been added
     *
     * @var bool
     */
    private static $body_class_filter_added = false;

    /**
     * Constructor
     */
    public function __construct() {
        if ( ! empty($this->tag)) {
            add_shortcode($this->tag, array( $this, 'render' ));
        }

        // Add body class filter only once, but with very late priority
        if ( ! self::$body_class_filter_added) {
            add_filter('body_class', array( __CLASS__, 'add_shortcode_body_classes' ), PHP_INT_MAX);
            self::$body_class_filter_added = true;
        }

        // Also try to catch via template_redirect (even later)
        add_action('template_redirect', array( $this, 'capture_shortcodes_in_content' ), 0);
    }

    /**
     * Capture shortcodes in content by scanning the post
     */
    public function capture_shortcodes_in_content() {
        global $post;

        if (is_a($post, 'WP_Post') && ! empty($post->post_content)) {
            // Get all registered shortcode tags from child classes
            $pattern = get_shortcode_regex();
            if (preg_match_all('/' . $pattern . '/s', $post->post_content, $matches)
                && ! empty($matches[2])) {
                foreach ($matches[2] as $tag) {
                    if (strpos($tag, 'hvnly_') === 0) {
                        self::register_active_shortcode($tag);
                    }
                }
            }
        }
    }

    /**
     * Add shortcode-specific classes to body
     *
     * @param array $classes Body classes
     * @return array
     */
    public static function add_shortcode_body_classes( $classes ) {
        foreach (self::$active_shortcodes as $shortcode_tag) {
            // Convert tag to class-friendly format
            $class_name = str_replace('_', '-', $shortcode_tag);
            $classes[]  = $class_name . '-shortcode';

            // Also add a more specific class without prefix
            $clean_tag = str_replace('hvnly_', '', $shortcode_tag);
            $classes[] = 'hvnly-shortcode-' . $clean_tag;
        }

        // Add general shortcode class if any are active
        if ( ! empty(self::$active_shortcodes)) {
            $classes[] = 'hvnly-shortcode-page';
        }

        return $classes;
    }

    /**
     * Register a shortcode as active on the current page
     *
     * @param string $tag Shortcode tag
     */
    protected static function register_active_shortcode( $tag ) {
        if ( ! in_array($tag, self::$active_shortcodes, true)) {
            self::$active_shortcodes[] = $tag;
        }
    }

    /**
     * Get all active shortcodes
     *
     * @return array
     */
    public static function get_active_shortcodes() {
        return self::$active_shortcodes;
    }

    /**
     * Before render - sets up shortcode environment
     * Can be overridden by child classes
     */
    protected function before_render() {
        global $hvnly_in_shortcode, $hvnly_has_shortcode;

        // Register this shortcode as active
        self::register_active_shortcode($this->tag);

        // Store previous values
        if ( ! isset($GLOBALS['hvnly_shortcode_stack'])) {
            $GLOBALS['hvnly_shortcode_stack'] = array();
        }

        // Push current state to stack
        array_push($GLOBALS['hvnly_shortcode_stack'], array(
            'hvnly_in_shortcode' => $hvnly_in_shortcode ?? false,
            'hvnly_has_shortcode' => $hvnly_has_shortcode ?? false,
        ));

        // Set new state
        $hvnly_in_shortcode  = true;
        $hvnly_has_shortcode = true;
    }

    /**
     * After render - restores previous environment
     * Can be overridden by child classes
     */
    protected function after_render() {
        global $hvnly_in_shortcode, $hvnly_has_shortcode;

        // Restore previous state from stack
        if ( ! empty($GLOBALS['hvnly_shortcode_stack'])) {
            $previous            = array_pop($GLOBALS['hvnly_shortcode_stack']);
            $hvnly_in_shortcode  = $previous['hvnly_in_shortcode'];
            $hvnly_has_shortcode = $previous['hvnly_has_shortcode'];
        } else {
            $hvnly_in_shortcode = false;
            // Don't reset hvnly_has_shortcode as it might be needed elsewhere
        }
    }

    /**
     * Render shortcode - must be implemented by child classes
     *
     * @param array  $atts    Shortcode attributes
     * @param string $content Shortcode content
     * @return string
     */
    abstract public function render( $atts, $content = '' );

    /**
     * Parse attributes with validation and sanitization
     *
     * @param array $atts User defined attributes
     * @return array
     */
    protected function parse_attributes( $atts ) {
        $atts = shortcode_atts($this->default_atts, $atts, $this->tag);
        return $this->validate_attributes($atts);
    }

    /**
     * Validate and sanitize attributes - override in child classes if needed
     *
     * @param array $atts Attributes to validate
     * @return array
     */
    protected function validate_attributes( $atts ) {
        // Basic sanitization for all attributes
        foreach ($atts as $key => $value) {
            if (is_string($value)) {
                $atts[ $key ] = sanitize_text_field($value);
            } elseif (is_numeric($value)) {
                $atts[ $key ] = absint($value);
            } elseif (is_array($value)) {
                $atts[ $key ] = array_map('sanitize_text_field', $value);
            }
        }
        return $atts;
    }

    /**
     * Get cache key for this shortcode instance
     *
     * @param array $atts   Shortcode attributes
     * @param int   $page   Current page
     * @return string
     */
    protected function get_cache_key( $atts, $page = 1 ) {
        $key_data = array(
            'atts'  => $atts,
            'page'  => $page,
            'lang'  => determine_locale(),
            'tag'   => $this->tag,
        );

        return 'hvnly_' . $this->tag . '_' . md5(serialize($key_data));
    }

    /**
     * Get cached output
     *
     * @param string $key Cache key
     * @return string|false
     */
    protected function get_cached( $key ) {
        if ( ! $this->enable_cache) {
            return false;
        }

        $cached = get_transient($key);

        if (false !== $cached) {
            $this->maybe_track_cache_hit();
        } else {
            $this->maybe_track_cache_miss();
        }

        return $cached;
    }

    /**
     * Set cached output
     *
     * @param string $key    Cache key
     * @param string $output Output to cache
     * @return bool
     */
    protected function set_cached( $key, $output ) {
        if ( ! $this->enable_cache || empty($output)) {
            return false;
        }

        return set_transient($key, $output, $this->cache_expiration);
    }

    /**
     * Track cache hit if HvnlyEngine is available
     */
    protected function maybe_track_cache_hit() {
        if (function_exists('HVNLY_NAB') && HVNLY_NAB()->engine() && method_exists(HVNLY_NAB()->engine(), 'track_cache_hit')) {
            HVNLY_NAB()->engine()->track_cache_hit();
        }
    }

    /**
     * Track cache miss if HvnlyEngine is available
     */
    protected function maybe_track_cache_miss() {
        if (function_exists('HVNLY_NAB') && HVNLY_NAB()->engine() && method_exists(HVNLY_NAB()->engine(), 'track_cache_miss')) {
            HVNLY_NAB()->engine()->track_cache_miss();
        }
    }

    /**
     * Get template arguments
     *
     * @param array $atts       Shortcode attributes
     * @param array $additional Additional args
     * @return array
     */
    protected function get_template_args( $atts, $additional = array() ) {
        return array_merge(array(
            'shortcode'   => $this->tag,
            'atts'        => $atts,
            'is_shortcode' => true,
        ), $additional);
    }

    /**
     * Load a template part
     *
     * @param string $template Template name
     * @param array  $args     Template arguments
     * @return string
     */
    protected function load_template( $template, $args = array() ) {
        ob_start();

        // Use existing template loader
        if (function_exists('hvnly_get_template')) {
            hvnly_get_template($template . '.php', $args);
        } else {
            // Fallback if template function doesn't exist
            $template_path = function_exists( 'hvnly_locate_template' )
                ? hvnly_locate_template( $template . '.php' )
                : false;
            if ( $template_path ) {
                extract( $args );
                include $template_path;
            }
        }

        return ob_get_clean();
    }

    /**
     * Locate a template file (fallback method).
     *
     * @deprecated 2.3.0 Use hvnly_locate_template() instead.
     *
     * @param string $template_name Template name
     * @return string|false
     */
    protected function locate_template( $template_name ) {
        if ( function_exists( 'hvnly_locate_template' ) ) {
            return hvnly_locate_template( $template_name );
        }

        $template_paths = array(
            get_stylesheet_directory() . '/havenlytics/' . $template_name,
            get_template_directory() . '/havenlytics/' . $template_name,
            HVNLYNAB_PATH . 'templates/' . $template_name,
        );

        foreach ($template_paths as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }

        return false;
    }

    /**
     * Clear cache for this shortcode
     *
     * @param string $specific_key Optional specific key to clear
     */
    public function clear_cache( $specific_key = '' ) {
        if ( ! empty($specific_key)) {
            delete_transient($specific_key);
            return;
        }

        // Clear all caches for this shortcode tag
        global $wpdb;
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                '_transient_hvnly_' . $this->tag . '_%'
            )
        );
    }
}
