<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Havenlytics Template Hooks
 *
 * Action/filter hooks used for Havenlytics /templates.
 */

// ==============================================
// GLOBAL WRAPPER HOOKS 
// ==============================================

// Content Wrappers.
add_action('hvnly_before_main_content', 'hvnly_output_content_wrapper_start', 10);
add_action('hvnly_after_main_content', 'hvnly_output_content_wrapper_end', 10);

// ==============================================
// SINGLE PROPERTY HOOKS 
// ==============================================

// Scroll Progress Indicator
add_action('wp_body_open', 'hvnly_single_property_scroll_progress', 5);

// SVG Icons in Footer
add_action('wp_footer', 'hvnly_single_property_svg_icons', 5);

// Modal Popups in Footer
add_action('wp_footer', 'hvnly_single_property_gallery_popup', 10);
add_action('wp_footer', 'hvnly_single_property_contact_agent_modal', 12);
add_action('wp_footer', 'hvnly_single_property_video_popup', 15);
add_action('wp_footer', 'hvnly_single_property_mobile_contact_dock', 18);
add_action('wp_footer', 'hvnly_single_property_scroll_top', 20);
add_filter('body_class', 'hvnly_single_property_mobile_contact_dock_body_class');

// Main Single Property Structure
add_action('hvnly_single_property_elements', 'hvnly_single_property_header', 10);
add_action('hvnly_single_property_elements', 'hvnly_single_property_main_container_start', 20);
add_action('hvnly_single_property_elements', 'hvnly_single_property_layout_grid', 30);
add_action('hvnly_single_property_elements', 'hvnly_single_property_main_container_end', 40);
add_action('hvnly_single_property_elements', 'hvnly_single_property_similar_properties', 50);

// Header Components
add_action('hvnly_single_property_header', 'hvnly_single_property_breadcrumb', 10);
add_action('hvnly_single_property_header', 'hvnly_single_property_title_section', 20);
add_action('hvnly_single_property_header', 'hvnly_single_property_meta', 30);
add_action('hvnly_single_property_header', 'hvnly_single_property_actions', 40);

// Layout Grid Components
add_action('hvnly_single_property_layout_grid', 'hvnly_single_property_main_content_area', 10);
add_action('hvnly_single_property_layout_grid', 'hvnly_single_property_sidebar_area', 20);

// Sidebar Area Widgets
add_action('hvnly_single_property_sidebar', 'hvnly_display_sidebar_widgets', 10);

// Similar Properties Carousel
add_action('hvnly_single_property_similar_properties', 'hvnly_single_property_similar_header', 10);
add_action('hvnly_single_property_similar_properties', 'hvnly_single_property_similar_carousel', 20);
add_action('hvnly_single_property_similar_properties', 'hvnly_single_property_similar_carousel_dots', 30);

// ==============================================
// SEARCH HOOKS 
// ==============================================

// Search Hooks
add_action('hvnly_before_search_loop', 'hvnly_search_loop_start', 10);
add_action('hvnly_after_search_loop', 'hvnly_search_loop_end', 10);
add_action('hvnly_after_search_loop', 'hvnly_search_pagination', 20);

// ==============================================
// ARCHIVE LAYOUT HOOKS 
// ==============================================

// Archive layout styles - Add to wp_head
add_action('wp_head', 'hvnly_add_archive_layout_styles', 20);

// ==============================================
// SINGLE AGENT HOOKS
// ==============================================

add_action('hvnly_single_agent_elements', 'hvnly_single_agent_header', 10);
add_action('hvnly_single_agent_elements', 'hvnly_single_agent_main_container_start', 20);
add_action('hvnly_single_agent_elements', 'hvnly_single_agent_profile_row', 30);
add_action('hvnly_single_agent_elements', 'hvnly_single_agent_listings_section', 40);
add_action('hvnly_single_agent_elements', 'hvnly_single_agent_main_container_end', 50);

add_action('hvnly_single_agent_header', 'hvnly_single_agent_breadcrumb', 10);

add_action('hvnly_single_agent_profile_row', 'hvnly_single_agent_layout_grid', 10);

add_action('hvnly_single_agent_layout_grid', 'hvnly_single_agent_main_content_area', 10);
add_action('hvnly_single_agent_layout_grid', 'hvnly_single_agent_sidebar_area', 20);

add_action('hvnly_single_agent_main_content', 'hvnly_single_agent_profile_header', 10);
add_action('hvnly_single_agent_main_content', 'hvnly_single_agent_agency', 15);
add_action('hvnly_single_agent_main_content', 'hvnly_single_agent_about', 20);

add_action('hvnly_single_agent_listings_section', 'hvnly_single_agent_properties', 10);

add_action('hvnly_single_agent_sidebar', 'hvnly_single_agent_sidebar_card', 10);