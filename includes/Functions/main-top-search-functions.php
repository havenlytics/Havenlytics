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
// MAIN TOP SEARCH FIELDS FUNCTIONS
// ========================================================
/**
 * Get main search fields
 */
function hvnly_get_main_search_fields() {
    return HvnlyNab\Helpers::get_instance()->hvnly_get_main_search_fields();
}

/**
 * Get main search field config
 */
function hvnly_get_main_search_field_config( $field_id ) {
    return HvnlyNab\Helpers::get_instance()->hvnly_get_main_search_field_config($field_id);
}

/**
 * Get placeholder text for any configured search field.
 *
 * @param string $field_id Field identifier.
 * @param string $default  Fallback label.
 * @return string
 */
function hvnly_get_search_field_placeholder( $field_id, $default = '' ) {
    return HvnlyNab\Helpers::get_instance()->hvnly_get_search_field_placeholder($field_id, $default);
}
