<?php
/**
 * Frontend Helper Functions for Havenlytics
 *
 * @package HvnlyNab
 * @since 2.1.0
 */

if ( ! defined('ABSPATH')) {
    exit;
}

// ========================================================
// TOP SEARCH FIELDS FUNCTIONS
// ========================================================

/**
 * Get top search fields
 *
 * @since 2.1.0
 * @return array
 */
function hvnly_get_top_search_fields() {
    return HvnlyNab\Helpers::get_instance()->hvnly_get_top_search_fields();
}

/**
 * Get bedroom options for top search
 *
 * @since 2.1.0
 * @return array
 */
function hvnly_top_search_get_bedroom_options() {
    return HvnlyNab\Helpers::get_instance()->hvnly_top_search_get_bedroom_options();
}

/**
 * Get bathroom options for top search
 *
 * @since 2.1.0
 * @return array
 */
function hvnly_top_search_get_bathroom_options() {
    return HvnlyNab\Helpers::get_instance()->hvnly_top_search_get_bathroom_options();
}

/**
 * Get min price options for top search
 *
 * @since 2.1.0
 * @return array
 */
function hvnly_top_search_get_min_price_options() {
    return HvnlyNab\Helpers::get_instance()->hvnly_top_search_get_min_price_options();
}

/**
 * Get max price options for top search
 *
 * @since 2.1.0
 * @return array
 */
function hvnly_top_search_get_max_price_options() {
    return HvnlyNab\Helpers::get_instance()->hvnly_top_search_get_max_price_options();
}
