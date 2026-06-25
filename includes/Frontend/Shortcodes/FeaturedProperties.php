<?php
/**
 * Featured Properties Shortcode - Enhanced with Complete Attribute Support
 *
 * @package     Havenlytics
 * @subpackage  Frontend\Shortcodes
 * @since       2.0.0
 */

namespace HvnlyNab\Frontend\Shortcodes;

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Featured Properties Shortcode class - Enhanced version
 *
 * @since 2.0.0
 */
class FeaturedProperties extends PropertyGrid {

    /**
     * Shortcode tag
     *
     * @var string
     */
    protected $tag = 'hvnly_featured_properties';

    /**
     * Default attributes
     *
     * @var array
     */
    protected $default_atts = [
        'posts_per_page'       => 6,
        'columns'              => 3,
        'orderby'              => 'date',
        'order'                => 'DESC',
        'category'             => '',
        'location'             => '',
        'status'               => '',
        'min_price'            => '',
        'max_price'            => '',
        'bedrooms'             => '',
        'bathrooms'            => '',
        'show_pagination'      => 'no',
        'show_results_count'   => 'no',
        'class'                => '',
        'id'                   => '',
        'cache'                => 'yes',
    ];

    /**
     * Render shortcode
     *
     * @param array  $atts    Shortcode attributes
     * @param string $content Shortcode content
     * @return string
     */
    public function render($atts, $content = '') {
        // Force featured_only to yes
        if (!is_array($atts)) {
            $atts = [];
        }
        $atts['featured_only'] = 'yes';
        
        // Use parent render method
        return parent::render($atts, $content);
    }
}