<?php
/**
 * Filter Sidebar Global Helper Functions
 *
 * These functions provide easy access to the Helpers class methods
 * with the proper hvnly_filter_sidebar_ prefix.
 *
 * @package HvnlyNab
 * @since 2.1.0
 */

if ( ! defined('ABSPATH')) {
    exit;
}

/**
 * Get dynamic search filter fields
 *
 * @since 2.1.0
 * @return array
 */
function hvnly_filter_sidebar_get_fields() {
    return HvnlyNab\Helpers::get_instance()->hvnly_filter_sidebar_get_fields();
}

/**
 * Get specific search field configuration by ID
 *
 * @since 2.1.0
 * @param string $field_id
 * @return array|null
 */
function hvnly_filter_sidebar_get_field_config( $field_id ) {
    return HvnlyNab\Helpers::get_instance()->hvnly_filter_sidebar_get_field_config($field_id);
}

/**
 * Get min price options for filter sidebar
 *
 * @since 2.1.0
 * @return array
 */
function hvnly_filter_sidebar_get_min_price_options() {
    return HvnlyNab\Helpers::get_instance()->hvnly_filter_sidebar_get_min_price_options();
}

/**
 * Get max price options for filter sidebar
 *
 * @since 2.1.0
 * @return array
 */
function hvnly_filter_sidebar_get_max_price_options() {
    return HvnlyNab\Helpers::get_instance()->hvnly_filter_sidebar_get_max_price_options();
}

/**
 * Get bedroom options for filter sidebar
 *
 * @since 2.1.0
 * @return array
 */
function hvnly_filter_sidebar_get_bedroom_options() {
    return HvnlyNab\Helpers::get_instance()->hvnly_filter_sidebar_get_bedroom_options();
}

/**
 * Get bathroom options for filter sidebar
 *
 * @since 2.1.0
 * @return array
 */
function hvnly_filter_sidebar_get_bathroom_options() {
    return HvnlyNab\Helpers::get_instance()->hvnly_filter_sidebar_get_bathroom_options();
}

/**
 * Get reception rooms options for filter sidebar
 *
 * @since 2.1.0
 * @return array
 */
function hvnly_filter_sidebar_get_reception_rooms_options() {
    return HvnlyNab\Helpers::get_instance()->hvnly_filter_sidebar_get_reception_rooms_options();
}

/**
 * Get custom dropdown options for search filter sidebar
 *
 * @since 2.1.0
 * @param string $field_id
 * @return array
 */
function hvnly_filter_sidebar_get_custom_dropdown_options( $field_id ) {
    return HvnlyNab\Helpers::get_instance()->hvnly_filter_sidebar_get_custom_dropdown_options($field_id);
}

/**
 * Get custom checkbox options for search filter sidebar
 *
 * @since 2.1.0
 * @param string $field_id
 * @return array
 */
function hvnly_filter_sidebar_get_custom_checkbox_options( $field_id ) {
    return HvnlyNab\Helpers::get_instance()->hvnly_filter_sidebar_get_custom_checkbox_options($field_id);
}

/**
 * Get taxonomy terms for filter sidebar with caching
 *
 * @since 2.1.0
 * @param string $taxonomy
 * @return array
 */
function hvnly_filter_sidebar_get_taxonomy_terms( $taxonomy ) {
    return HvnlyNab\Helpers::get_instance()->hvnly_filter_sidebar_get_taxonomy_terms($taxonomy);
}

/**
 * Get current filter values from request
 *
 * @since 2.1.0
 * @return array
 */
function hvnly_filter_sidebar_get_current_values() {
    return HvnlyNab\Helpers::get_instance()->hvnly_filter_sidebar_get_current_values();
}

/**
 * Check if filter sidebar should be shown
 *
 * @since 2.1.0
 * @return bool
 */
function hvnly_filter_sidebar_should_show() {
    return HvnlyNab\Helpers::get_instance()->hvnly_filter_sidebar_should_show();
}

/**
 * Get unique property IDs for filter sidebar
 *
 * @since 2.1.0
 * @return array
 */
function hvnly_filter_sidebar_get_unique_property_ids() {
    return HvnlyNab\Helpers::get_instance()->hvnly_filter_sidebar_get_unique_property_ids();
}

/**
 * Check if field uses taxonomy
 *
 * @since 2.1.0
 * @param string $field_id
 * @return bool
 */
function hvnly_filter_sidebar_field_uses_taxonomy( $field_id ) {
    return HvnlyNab\Helpers::get_instance()->hvnly_filter_sidebar_field_uses_taxonomy($field_id);
}

/**
 * Get field taxonomy
 *
 * @since 2.1.0
 * @param string $field_id
 * @return string|null
 */
function hvnly_filter_sidebar_get_field_taxonomy( $field_id ) {
    return HvnlyNab\Helpers::get_instance()->hvnly_filter_sidebar_get_field_taxonomy($field_id);
}

/**
 * Get field placeholder
 *
 * @since 2.1.0
 * @param string $field_id
 * @param string $default
 * @return string
 */
function hvnly_filter_sidebar_get_field_placeholder( $field_id, $default = '' ) {
    return HvnlyNab\Helpers::get_instance()->hvnly_filter_sidebar_get_field_placeholder($field_id, $default);
}

/**
 * Check if select all option is enabled
 *
 * @since 2.1.0
 * @param string $field_id
 * @return bool
 */
function hvnly_filter_sidebar_has_select_all_option( $field_id ) {
    return HvnlyNab\Helpers::get_instance()->hvnly_filter_sidebar_has_select_all_option($field_id);
}
