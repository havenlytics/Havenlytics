<?php
/**
 * Property loop start wrapper (shortcode / search contexts).
 *
 * Intentionally minimal: the grid/list markup lives in the parent template.
 * This file exists so theme overrides and hvnly_get_template_part() resolve correctly.
 *
 * @package Havenlytics
 * @subpackage Templates/search
 * @since 2.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Fires before the property loop markup in shortcode/search contexts.
 *
 * @since 2.3.0
 */
do_action( 'hvnly_property_loop_start' );
