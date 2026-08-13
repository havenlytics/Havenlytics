<?php
/**
 * Layout Helper Functions
 *
 * @package     Havenlytics
 * @subpackage  Functions
 * @since       2.0.0
 */

if ( ! defined('ABSPATH')) {
    exit;
}

use HvnlyNab\Frontend\LayoutManager;

/**
 * Check if single property has sidebar
 *
 * @return bool
 */
function hvnly_single_property_has_sidebar() {
    $layout_manager = LayoutManager::instance();
    return $layout_manager->has_sidebar();
}

/**
 * Get current layout context
 *
 * @return string
 */
function hvnly_get_layout_context() {
    $layout_manager = LayoutManager::instance();
    return $layout_manager->get_context();
}

/**
 * Get layout configuration
 *
 * @return array
 */
function hvnly_get_layout_config() {
    $layout_manager = LayoutManager::instance();
    return $layout_manager->get_config();
}

/**
 * Get responsive breakpoints
 *
 * @return array
 */
function hvnly_get_breakpoints() {
    $layout_manager = LayoutManager::instance();
    return $layout_manager->get_breakpoints();
}

/**
 * Check if sidebar should be displayed
 *
 * @param string $context Layout context
 * @return bool
 */
function hvnly_should_display_sidebar( $context = 'single' ) {
    return apply_filters('hvnly_should_display_sidebar', true, $context);
}

/**
 * Get sidebar mobile position
 *
 * @return string
 */
function hvnly_get_sidebar_mobile_position() {
    $config = hvnly_get_layout_config();
    return $config['sidebar_position_mobile'] ?? 'bottom';
}
