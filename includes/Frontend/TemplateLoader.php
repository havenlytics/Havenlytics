<?php
/**
 * Template Loader for Havenlytics 
 *
 * @package     Havenlytics
 * @subpackage  Frontend
 * @copyright   Copyright (c) 2025, Havenlytics
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       2.0.0
 */

namespace HvnlyNab\Frontend;

use HvnlyNab\Agent\AgentConstants;

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Template Loader class
 *
 * @since 2.0.0
 */
class TemplateLoader
{
    /**
     * Post type
     *
     * @var string
     */
    private $post_type = 'hvnly_property';

    /**
     * Slug
     *
     * @var string
     */
    private $slug = 'property';

    /**
     * Template path for theme overrides
     *
     * @var string
     */
    private $template_path = 'havenlytics/';

    /**
     * Default template path in plugin
     *
     * @var string
     */
    private $default_path = '';

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->default_path = HVNLYNAB_PATH . 'templates/';
        
        // Customize Frontend Template
        add_filter('template_include', array($this, 'template_loader'));
        
        // Custom Author Page
        add_filter('generate_rewrite_rules', array($this, 'author_page_rewrite_rules'));
        
        // Customize Review Comment Template
        add_filter('comments_template', array($this, 'comment_review_template_loader'));
        
        add_filter('body_class', array($this, 'body_class'));
        add_filter('admin_body_class', array($this, 'admin_body_class'));
        
        // Add template path filter
        add_filter('hvnly_template_path', array($this, 'set_template_path'));
    }

    /**
     * Get template path for theme overrides
     *
     * @return string
     */
    public function get_template_path() {
        return apply_filters('hvnly_template_path', $this->template_path);
    }

    /**
     * Get default template path
     *
     * @return string
     */
    public function get_default_path() {
        return apply_filters('hvnly_default_path', $this->default_path);
    }

    /**
     * Set template path filter
     *
     * @param string $path Template path
     * @return string
     */
    public function set_template_path($path) {
        return $this->template_path;
    }

    /**
     * Locate template file (WooCommerce/EDD style)
     *
     * @param string|array $template_names Template name(s)
     * @param string $template_path Template path
     * @param string $default_path Default path
     * @return string
     */
    public function locate_template($template_names, $template_path = '', $default_path = '') {
        if (!$template_path) {
            $template_path = $this->get_template_path();
        }
        
        if (!$default_path) {
            $default_path = $this->get_default_path();
        }
        
        // Convert to array if it's a string
        $template_names = (array) $template_names;
        $located = '';
        
        foreach ($template_names as $template_name) {
            // Skip if empty
            if (empty($template_name)) {
                continue;
            }
            
            // Ensure it's a string
            if (!is_string($template_name)) {
                continue;
            }
            
            // Look within passed path within the theme - this is theme override
            $template = locate_template(
                array(
                    trailingslashit($template_path) . $template_name,
                    $template_name,
                )
            );
            
            // Get default template from plugin
            if (!$template && file_exists($default_path . $template_name)) {
                $template = $default_path . $template_name;
            }
            
            if ($template) {
                $located = $template;
                break;
            }
        }
        
        // Return what we found
        return apply_filters('hvnly_locate_template', $located, $template_names, $template_path, $default_path);
    }

    /**
     * Template include for overwrite
     *
     * @param string $template Template path.
     * @return string
     */
    public function template_loader($template)
    {
        $file = false;

        if (is_singular($this->post_type)) {
            $file = "single-{$this->slug}.php";
        } elseif (is_singular(AgentConstants::POST_TYPE)) {
            $file = 'single-agent.php';
        } elseif (is_post_type_archive(AgentConstants::POST_TYPE)) {
            $file = 'archive-agent.php';
        } elseif ($this->is_agency_taxonomy_page()) {
            $file = 'taxonomy-' . AgentConstants::TAXONOMY_AGENCY . '.php';
        }

        if ($this->is_property_archive()) {
            $file = "archive-{$this->slug}.php";
        }

        // There is a file, create a new template path
        if ($file) {
            // Use locate_template with theme override support
            $located = $this->locate_template($file);
            
            if ($located && file_exists($located)) {
                $template = $located;
            }
        }

        return $template;
    }

    /**
     * Whether the current page is an agency taxonomy route.
     *
     * @return bool
     */
    private function is_agency_taxonomy_page()
    {
        return is_tax(AgentConstants::TAXONOMY_AGENCY);
    }

    /**
     * Author property Page rewrite rules
     *
     * @param object $wp_rewrite WP_Rewrite object.
     * @return object
     */
    public function author_page_rewrite_rules($wp_rewrite)
    {
        $key        = '^author/([a-zA-Z0-9\-_]+)/properties(?:/page/([0-9]+))?/?$';
        $rewrite    = 'index.php?post_type=hvnly_property&author_name=$matches[1]&paged=$matches[2]';
        $new_rules   = array($key => $rewrite);
        $wp_rewrite->rules = $new_rules + $wp_rewrite->rules;

        return $wp_rewrite;
    }

    /**
     * Template include for overwrite
     * filter hook for single property page
     *
     * @param string $template Template path.
     * @return string
     */
    public function comment_review_template_loader($template)
    {
        global $post;
        if (!(is_singular($this->post_type) && (have_comments() || ('open' == $post->comment_status)))) {
            return $template;
        }

        if ($post && $post->post_type == $this->post_type) {
            $template_name = 'single-property-reviews.php';
            $located = $this->locate_template($template_name);
            
            if ($located && file_exists($located)) {
                $template = $located;
            }
        }

        return $template;
    }

    /**
     * Check if it's a property archive
     *
     * @return bool
     */
    private function is_property_archive()
    {
        if (is_post_type_archive($this->post_type)) {
            return true;
        }

        if (is_tax()) {
            $queried_object = get_queried_object();
            if (isset($queried_object->taxonomy)) {
                $taxonomy = get_taxonomy($queried_object->taxonomy);
                $allowed_taxonomies = array('hvnly_prop_types', 'hvnly_prop_depts', 'hvnly_prop_locations', 'hvnly_prop_status', 'hvnly_prop_features', 'hvnly_prop_badges', 'hvnly_prop_tags');

                if (in_array($taxonomy->name, $allowed_taxonomies, true)) {
                    return true;
                }
            }
        }

        // Check if it's an author property archive
        if ($this->is_author_property_archive()) {
            return true;
        }

        return false;
    }

    /**
     * Check if it's an author property archive
     *
     * @return bool
     */
    private function is_author_property_archive() {
        $properties_raw = filter_input(INPUT_GET, 'properties', FILTER_UNSAFE_RAW);
        $properties = $properties_raw ? sanitize_text_field($properties_raw) : '';

        $nonce_raw = filter_input(INPUT_GET, 'nonce', FILTER_UNSAFE_RAW);
        $nonce = $nonce_raw ? sanitize_text_field($nonce_raw) : '';

        // Verify nonce
        if ( ! wp_verify_nonce( $nonce, 'hvnly_properties_check' ) ) {
            return false;
        }

        if ( is_author() && ( 'yes' === $properties || get_post_type() === 'hvnly_property' ) ) {
            return true;
        }

        return false;
    }

 
    /**
     * Add body classes
     *
     * @param array $classes Body classes.
     * @return array
     */
    public function body_class($classes)
    {
        // Check if this is a plugin-related page
        if (!$this->is_plugin_page()) {
            return $classes;
        }

        // Base plugin classes
        $plugin_classes = array(
            'hvnly-content-active',
            'hvnly-plugin-active',
            'hvnly-property-page'
        );

        // Add base plugin classes
        $classes = array_merge($classes, $plugin_classes);

        // Single property page
        if (is_singular($this->post_type)) {
            $single_classes = array(
                'hvnly-property-single',
                'hvnly-property',
                'hvnly-single-property'
            );
            $classes = array_merge($classes, $single_classes);
        }
        // Single agent profile page
        elseif (is_singular(AgentConstants::POST_TYPE)) {
            $classes = array_merge(
                $classes,
                array(
                    'hvnly-agent-single',
                    'hvnly-agent-page',
                    'hvnly-single-agent',
                )
            );
        }
        // Agent archive
        elseif (is_post_type_archive(AgentConstants::POST_TYPE)) {
            $classes = array_merge(
                $classes,
                array(
                    'hvnly-agent-archive',
                    'hvnly-property-archive-page',
                    'hvnly-archive-agent',
                )
            );
        }
        // Agency archive / single
        elseif ($this->is_agency_taxonomy_page()) {
            $agency_classes = array(
                'hvnly-agency-page',
                'hvnly-property-archive-page',
            );

            if (function_exists('hvnly_is_agency_archive_index') && hvnly_is_agency_archive_index()) {
                $agency_classes[] = 'hvnly-agency-archive';
                $agency_classes[] = 'hvnly-archive-agency';
            } else {
                $agency_classes[] = 'hvnly-agency-single';
                $agency_classes[] = 'hvnly-single-agency';
            }

            $classes = array_merge($classes, $agency_classes);
        }
        // Property archive pages
        elseif ($this->is_property_archive()) {
            $archive_classes = array(
                'hvnly-property-archive',
                'hvnly-property',
                'hvnly-archive-property'
            );
            $classes = array_merge($classes, $archive_classes);
        }

        // Remove duplicates (just in case)
        $classes = array_unique($classes);

        return $classes;
    }


    /**
     * Add admin body classes
     *
     * @param string $classes Admin body classes.
     * @return string
     */
    public function admin_body_class($classes)
    {
        // Add plugin class to admin body for plugin admin pages
        $screen = get_current_screen();

        if (
            $screen &&
            (
                strpos($screen->id, 'hvnly') !== false ||
                $screen->post_type === 'hvnly_property' ||
                ($screen->taxonomy && in_array($screen->taxonomy, array('hvnly_prop_types', 'hvnly_prop_depts', 'hvnly_prop_locations', 'hvnly_prop_status', 'hvnly_prop_features', 'hvnly_prop_badges', 'hvnly_prop_tags'), true))
            )
        ) {
            $classes .= ' hvnly-admin-active hvnly-content-active';
        }

        return $classes;
    }

    /**
     * Check if current page is plugin page
     *
     * @return bool
     */
    private function is_plugin_page()
    {
        // Check if current page is related to the plugin
        if (
            is_singular('hvnly_property') ||
            is_singular(AgentConstants::POST_TYPE) ||
            is_post_type_archive('hvnly_property') ||
            is_post_type_archive(AgentConstants::POST_TYPE) ||
            $this->is_agency_taxonomy_page() ||
            is_tax(array('hvnly_prop_types', 'hvnly_prop_depts', 'hvnly_prop_locations', 'hvnly_prop_status', 'hvnly_prop_features', 'hvnly_prop_badges', 'hvnly_prop_tags')) ||
            is_page_template('havenlytics')
        ) {
            return true;
        }

        return false;
    }
}