<?php
/**
 * Shared AJAX template fragment helpers.
 *
 * @package Havenlytics
 * @subpackage Functions
 * @since 2.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resolve a taxonomy filter value (slug, name, or term ID) to its display name.
 *
 * @param mixed  $value    Filter value from request or URL.
 * @param string $taxonomy Registered taxonomy slug.
 * @return string
 */
function hvnly_resolve_taxonomy_filter_term_name( $value, $taxonomy ) {
	if ( $value === '' || $value === null ) {
		return '';
	}

	$term = null;

	if ( is_numeric( $value ) ) {
		$term = get_term( (int) $value, $taxonomy );
	} else {
		$value = (string) $value;
		$term  = get_term_by( 'slug', sanitize_title( $value ), $taxonomy );
		if ( ! $term ) {
			$term = get_term_by( 'name', $value, $taxonomy );
		}
	}

	if ( ! $term || is_wp_error( $term ) ) {
		return '';
	}

	return (string) $term->name;
}

/**
 * Build human-readable filter labels for the results-count template.
 *
 * @param array $filters Raw request/search filters.
 * @return array {
 *     @type string[] $active_labels Combined, deduplicated term labels for display.
 * }
 */
function hvnly_build_ajax_result_count_filters( $filters = array() ) {
	if ( ! is_array( $filters ) ) {
		$filters = array();
	}

	$taxonomy_filter_keys = array(
		'hvnly_prop_depts'     => 'hvnly_prop_depts',
		'hvnly_prop_status'    => 'hvnly_prop_status',
		'hvnly_prop_types'     => 'hvnly_prop_types',
		'hvnly_prop_locations' => 'hvnly_prop_locations',
		'hvnly_prop_features'  => 'hvnly_prop_features',
		'hvnly_prop_tags'      => 'hvnly_prop_tags',
		'hvnly_prop_badges'    => 'hvnly_prop_badges',
	);

	$values_by_taxonomy = array();

	foreach ( $taxonomy_filter_keys as $filter_key => $taxonomy ) {
		if ( empty( $filters[ $filter_key ] ) ) {
			continue;
		}

		if ( ! isset( $values_by_taxonomy[ $taxonomy ] ) ) {
			$values_by_taxonomy[ $taxonomy ] = array();
		}

		$values_by_taxonomy[ $taxonomy ] = array_merge(
			$values_by_taxonomy[ $taxonomy ],
			(array) $filters[ $filter_key ]
		);
	}

	if ( ! empty( $filters['department'] ) ) {
		if ( ! isset( $values_by_taxonomy['hvnly_prop_depts'] ) ) {
			$values_by_taxonomy['hvnly_prop_depts'] = array();
		}

		$values_by_taxonomy['hvnly_prop_depts'][] = sanitize_text_field( (string) $filters['department'] );
	}

	$taxonomy_order = array(
		'hvnly_prop_depts',
		'hvnly_prop_status',
		'hvnly_prop_types',
		'hvnly_prop_locations',
		'hvnly_prop_features',
		'hvnly_prop_tags',
		'hvnly_prop_badges',
	);

	$active_labels = array();
	$seen_names    = array();

	foreach ( $taxonomy_order as $taxonomy ) {
		if ( empty( $values_by_taxonomy[ $taxonomy ] ) ) {
			continue;
		}

		$unique_values = array_unique( array_filter( (array) $values_by_taxonomy[ $taxonomy ] ) );

		foreach ( $unique_values as $value ) {
			$name = hvnly_resolve_taxonomy_filter_term_name( $value, $taxonomy );
			if ( $name === '' ) {
				continue;
			}

			$dedupe_key = strtolower( $name );
			if ( isset( $seen_names[ $dedupe_key ] ) ) {
				continue;
			}

			$seen_names[ $dedupe_key ] = true;
			$active_labels[]           = $name;
		}
	}

	return array(
		'active_labels' => $active_labels,
	);
}

/**
 * Render pagination markup for AJAX responses using the shared template.
 *
 * @param \WP_Query $query        Properties query.
 * @param int       $current_page Current page number.
 * @param array     $options      Optional flags.
 * @return void
 */
function hvnly_render_ajax_pagination_fragment( $query, $current_page = 1, $options = array() ) {
	global $wp_query;

	$original_query = $wp_query;
	$wp_query       = $query;
	$current_page   = max( 1, (int) $current_page );

	// Cached/hydrated queries may not expose paged — ensure the template sees the requested page.
	if ( $query instanceof \WP_Query ) {
		$query->set( 'paged', $current_page );
	}

	$instance_id = '';
	if ( ! empty( $options['instance_id'] ) ) {
		$instance_id = sanitize_text_field( (string) $options['instance_id'] );
	} elseif ( ! empty( $_POST['instance_id'] ) ) {
		$instance_id = sanitize_text_field( wp_unslash( $_POST['instance_id'] ) );
	} elseif ( ! empty( $_POST['widget_id'] ) ) {
		$instance_id = sanitize_text_field( wp_unslash( $_POST['widget_id'] ) );
	}

	if ( ! empty( $options['pass_pagination_type'] ) && function_exists( 'hvnly_get_pagination_type' ) ) {
		$hvnly_passed_pagination_type = hvnly_get_pagination_type();
	}

	if ( function_exists( 'hvnly_get_template' ) ) {
		hvnly_get_template(
			'search/pagination.php',
			array(
				'current_page' => $current_page,
				'instance_id'  => $instance_id,
			)
		);
	}

	$wp_query = $original_query;
}

/**
 * Render results count markup for AJAX responses using the shared template.
 *
 * @param \WP_Query $query        Properties query.
 * @param int       $current_page Current page number.
 * @param int       $per_page     Posts per page.
 * @param array     $filters      Raw request/search filters.
 * @return void
 */
function hvnly_render_ajax_results_count_fragment( $query, $current_page, $per_page, $filters = array() ) {
	global $wp_query;

	$original_query   = $wp_query;
	$wp_query         = $query;
	$total_properties = (int) $query->found_posts;
	$start            = ( ( (int) $current_page - 1 ) * (int) $per_page ) + 1;
	$end              = min( (int) $current_page * (int) $per_page, $total_properties );
	$current_filters      = hvnly_build_ajax_result_count_filters( $filters );
	$active_filter_labels = isset( $current_filters['active_labels'] ) ? (array) $current_filters['active_labels'] : array();

	$args = array(
		'total_properties'      => $total_properties,
		'start'                 => $start,
		'end'                   => $end,
		'active_filter_labels'  => $active_filter_labels,
		'current_filters'       => $current_filters,
		// Backward compatibility for older callers that used this key.
		'hvnly_current_filters' => $current_filters,
	);

	if ( function_exists( 'hvnly_get_template' ) ) {
		hvnly_get_template( 'search/result-count.php', $args );
	}

	$wp_query = $original_query;
}
