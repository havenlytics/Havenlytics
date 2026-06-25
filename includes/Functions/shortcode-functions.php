<?php
/**
 * Shortcode Helper Functions
 *
 * @package Havenlytics/Functions
 * @since 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Check if a page contains any property shortcodes
 *
 * @param int|WP_Post $post Post ID or object
 * @return bool
 */
function hvnly_page_has_property_shortcodes($post = null) {
    $post = get_post($post);
    
    if (!$post || empty($post->post_content)) {
        return false;
    }
    
    $shortcodes = [
        'hvnly_property_grid',
        'hvnly_property_list',
        'hvnly_property_search',
        'hvnly_featured_properties',
        'hvnly_properties', // legacy
        'hvnly_property_lists', // legacy
   
    ];
    
    foreach ($shortcodes as $shortcode) {
        if (has_shortcode($post->post_content, $shortcode)) {
            return true;
        }
    }
    
    return false;
}

/**
 * Get all property shortcodes in a page
 *
 * @param int|WP_Post $post Post ID or object
 * @return array
 */
function hvnly_get_page_property_shortcodes($post = null) {
    $post = get_post($post);
    
    if (!$post || empty($post->post_content)) {
        return [];
    }
    
    $shortcodes = [];
    $pattern = get_shortcode_regex();
    
    if (preg_match_all('/' . $pattern . '/s', $post->post_content, $matches) && isset($matches[2])) {
        foreach ($matches[2] as $key => $tag) {
            if (strpos($tag, 'hvnly_property') === 0) {
                $shortcodes[] = [
                    'tag' => $tag,
                    'atts' => shortcode_parse_atts($matches[3][$key] ?? ''),
                    'content' => $matches[5][$key] ?? '',
                ];
            }
        }
    }
    
    return $shortcodes;
}

/**
 * Check if current page has any Havenlytics shortcode
 *
 * @param string|null $specific_tag Optional specific shortcode tag to check
 * @return bool
 */
function hvnly_page_has_shortcode($specific_tag = null) {
    global $hvnly_has_shortcode;
    
    if ($specific_tag) {
        return HvnlyNab\Frontend\Shortcodes\Assets::has_shortcode($specific_tag);
    }
    
    return !empty($hvnly_has_shortcode);
}