<?php
/**
 * Property Template Functions for Havenlytics
 *
 * @package     Havenlytics
 * @subpackage  Functions
 * @copyright   Copyright (c) 2025, Havenlytics
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       2.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Default archive card field type → template slug mapping.
 *
 * @return array
 */
function hvnly_get_default_field_template_mapping() {
	return array(
		'title'          => 'title',
		'price'          => 'price',
		'excerpt'        => 'excerpt',
		'location'       => 'location',
		'badge'          => 'badge',
		'favorite'       => 'favorite',
		'views'          => 'views',
		'status'         => 'status',
		'time'           => 'time',
		'rating'         => 'rating',
		'phone'          => 'phone',
		'email'          => 'email',
		'website'        => 'website',
		'datetime'       => 'datetime',
		'address'        => 'address',
		'beds'           => 'beds',
		'baths'          => 'baths',
		'sqft'           => 'sqft',
		'receptions'     => 'receptions',
		'button'         => 'button',
		'feature'        => 'feature',
		'schedule-tour'  => 'schedule-tour',
		'price-per-sqft' => 'price-per-sqft',
		'image'          => 'image',
		'carousel'       => 'carousel',
		'comments'       => 'comments',
		'avatar'         => 'avatar',
		'tags'           => 'tags',
		'garage'         => 'garage-sqft',
		'kitchens'       => 'kitchens',
		'rooms'          => 'rooms',
		'property_type'  => 'types',
		'url'            => 'url',
		'date'           => 'date',
		'faq-count'      => 'faq-count',
		'repeater-count' => 'repeater-count',
	);
}

/**
 * Filterable archive card field type → template slug mapping.
 *
 * @return array
 */
function hvnly_get_field_template_mapping() {
	return apply_filters( 'hvnly_field_template_mapping', hvnly_get_default_field_template_mapping() );
}

/**
 * Shared PropertyCardRenderer instance (service-aware).
 *
 * @return \HvnlyNab\Frontend\PropertyCardRenderer
 */
function hvnly_get_property_card_renderer() {
	global $hvnly_property_card_renderer;

	if ( ! $hvnly_property_card_renderer ) {
		if ( class_exists( '\HvnlyNab\Frontend' ) ) {
			$service = \HvnlyNab\Frontend::get_instance()->get_service( \HvnlyNab\Frontend\PropertyCardRenderer::class );
			if ( $service ) {
				$hvnly_property_card_renderer = $service;
			}
		}

		if ( ! $hvnly_property_card_renderer ) {
			$hvnly_property_card_renderer = new \HvnlyNab\Frontend\PropertyCardRenderer();
		}
	}

	return $hvnly_property_card_renderer;
}

/**
 * Shared PropertySingleRenderer instance (service-aware).
 *
 * @return \HvnlyNab\Frontend\PropertySingleRenderer
 */
function hvnly_get_property_single_renderer() {
	global $hvnly_property_single_renderer;

	if ( ! $hvnly_property_single_renderer ) {
		if ( class_exists( '\HvnlyNab\Frontend' ) ) {
			$service = \HvnlyNab\Frontend::get_instance()->get_service( \HvnlyNab\Frontend\PropertySingleRenderer::class );
			if ( $service ) {
				$hvnly_property_single_renderer = $service;
			}
		}

		if ( ! $hvnly_property_single_renderer ) {
			$hvnly_property_single_renderer = new \HvnlyNab\Frontend\PropertySingleRenderer();
		}
	}

	return $hvnly_property_single_renderer;
}

/**
 * Check if a field is configured in admin sections
 *
 * @param array  $sections   Sections data.
 * @param string $field_type Field type to check.
 * @return bool
 */
function hvnly_is_field_configured( $sections, $field_type ) {
	if ( empty( $sections ) || ! is_array( $sections ) ) {
		return false;
	}
	
	// Special case: Check for avatar section
	if ( 'avatar' === $field_type ) {
		return hvnly_get_property_card_renderer()->has_avatar_section( $sections );
	}
	
	foreach ( $sections as $section ) {
		if ( ! empty( $section['fields'] ) && is_array( $section['fields'] ) ) {
			foreach ( $section['fields'] as $field ) {
				if ( isset( $field['type'] ) && $field['type'] === $field_type ) {
					return true;
				}
			}
		}
	}
	return false;
}

/**
 * Check if avatar section exists and has fields
 *
 * @param array $sections Sections data.
 * @return bool
 */
function hvnly_has_avatar_section( $sections ) {
	if ( empty( $sections ) || ! is_array( $sections ) ) {
		return false;
	}
	
	foreach ( $sections as $section ) {
		if ( isset( $section['id'] ) && 'avatar' === $section['id'] && ! empty( $section['fields'] ) ) {
			return true;
		}
	}
	return false;
}

/**
 * Render a field template with proper data
 *
 * @param string $field_type   Field type.
 * @param int    $property_id  Property ID.
 * @param array  $property_data Property data.
 * @param string $mode         Render mode (preset/default).
 * @param array  $field        Field configuration (for preset mode).
 * @return void
 */
function hvnly_render_field( $field_type, $property_id, $property_data = array(), $mode = 'default', $field = array() ) {
	$field_template_mapping = hvnly_get_field_template_mapping();

	// Get the actual template name from mapping or use the field type
	$template_name = isset( $field_template_mapping[ $field_type ] ) ? $field_template_mapping[ $field_type ] : $field_type;
	// Check if this is a schedule tour button
	if ( $field_type === 'button' && ! empty( $field['value']['type'] ) && $field['value']['type'] === 'schedule-tour' ) {
		$template_name = 'schedule-tour';
	}
	// Build the template path
	$template_path = 'archive/fields/' . $template_name;
	
	// Load the template
	hvnly_get_template(
		$template_path,
		array(
			'property_id'   => $property_id,
			'property_data' => $property_data,
			'mode'          => $mode,
			'field'         => $field,
		)
	);
}

/**
 * Get all configured field types from sections
 *
 * @param array $sections Sections data.
 * @return array
 */
function hvnly_get_configured_field_types( $sections ) {
	$configured_fields = array();

	if ( empty( $sections ) || ! is_array( $sections ) ) {
		return $configured_fields;
	}

	foreach ( $sections as $section ) {
		if ( ! empty( $section['fields'] ) && is_array( $section['fields'] ) ) {
			foreach ( $section['fields'] as $field ) {
				if ( isset( $field['type'] ) && ! in_array( $field['type'], $configured_fields, true ) ) {
					$configured_fields[] = $field['type'];
				}
			}
		}
	}

	return $configured_fields;
}

/**
 * Get property view count
 *
 * @param int    $property_id Property ID.
 * @param string $type View type (total, unique, today, etc.).
 * @return int
 * @since 2.0.0
 */
if ( ! function_exists( 'hvnly_get_property_views' ) ) {
	function hvnly_get_property_views( $property_id = null, $type = 'total' ) {
		$property_id = $property_id ?: get_the_ID();
		
		if ( ! $property_id ) {
			return 0;
		}

		// Use the PropertyViewTracker if available
		if ( class_exists( 'HvnlyNab\Frontend\PropertyViewTracker' ) ) {
			$tracker = new HvnlyNab\Frontend\PropertyViewTracker();
			return $tracker->get_view_count( $property_id, $type );
		}

		// Fallback to direct meta query
		$views_data = get_post_meta( $property_id, '_hvnly_property_views', true );

		if ( ! $views_data || ! is_array( $views_data ) ) {
			return 0;
		}

		return isset( $views_data[ $type ] ) ? absint( $views_data[ $type ] ) : absint( $views_data['total'] );
	}
}

/**
 * Display property view count
 *
 * @param int    $property_id Property ID.
 * @param string $type View type.
 * @since 2.0.0
 */
if ( ! function_exists( 'hvnly_property_views' ) ) {
	function hvnly_property_views( $property_id = null, $type = 'total' ) {
		echo esc_html( hvnly_get_property_views( $property_id, $type ) );
	}
}

/**
 * Check if property has views
 *
 * @param int $property_id Property ID.
 * @return bool
 * @since 2.0.0
 */
if ( ! function_exists( 'hvnly_has_property_views' ) ) {
	function hvnly_has_property_views( $property_id = null ) {
		return hvnly_get_property_views( $property_id ) > 0;
	}
}

/**
 * Get formatted property view count
 *
 * @param int    $property_id Property ID.
 * @param string $format_type Format type (compact, readable, full).
 * @param string $view_type View type (total, unique, today, etc.).
 * @param array  $format_args Additional format arguments.
 * @return string
 * @since 2.0.0
 */
if ( ! function_exists( 'hvnly_get_formatted_property_views' ) ) {
	function hvnly_get_formatted_property_views( $property_id = null, $format_type = 'compact', $view_type = 'total', $format_args = array() ) {
		$property_id = $property_id ?: get_the_ID();
		
		if ( ! $property_id ) {
			return '0';
		}

		$view_count = hvnly_get_property_views( $property_id, $view_type );

		// Use advanced formatter if available
		if ( class_exists( 'HvnlyNab\Frontend\ViewCountFormatter' ) ) {
			return HvnlyNab\Frontend\ViewCountFormatter::format( $view_count, $format_type, $format_args );
		}

		// Fallback to basic number formatting
		return number_format_i18n( $view_count );
	}
}

/**
 * Display formatted property view count
 *
 * @param int    $property_id Property ID.
 * @param string $format_type Format type.
 * @param string $view_type View type.
 * @param array  $format_args Format arguments.
 * @since 2.0.0
 */
if ( ! function_exists( 'hvnly_the_formatted_property_views' ) ) {
	function hvnly_the_formatted_property_views( $property_id = null, $format_type = 'compact', $view_type = 'total', $format_args = array() ) {
		echo esc_html( hvnly_get_formatted_property_views( $property_id, $format_type, $view_type, $format_args ) );
	}
}

/**
 * Get property views with smart formatting (auto-detects best format)
 *
 * @since 2.0.0
 *
 * @param int    $property_id Property ID.
 * @param string $view_type   View type.
 * @return string
 */
if ( ! function_exists( 'hvnly_get_smart_property_views' ) ) {
	function hvnly_get_smart_property_views( $property_id = null, $view_type = 'total' ) {
		$property_id = $property_id ? absint( $property_id ) : get_the_ID();
		$view_count  = hvnly_get_property_views( $property_id, $view_type );

		// Auto-detect best format based on count.
		if ( $view_count >= 1000000 ) {
			$format_type = 'compact';
			$format_args = array(
				'precision' => 1,
			);
		} elseif ( $view_count >= 10000 ) {
			$format_type = 'compact';
			$format_args = array(
				'precision'    => 1,
				'force_floor'  => true,
			);
		} elseif ( $view_count >= 1000 ) {
			$format_type = 'compact';
			$format_args = array(
				'precision' => 1,
			);
		} else {
			$format_type = 'full';
			$format_args = array();
		}

		return hvnly_get_formatted_property_views( $property_id, $format_type, $view_type, $format_args );
	}
}

/**
 * Get property views data for advanced display
 *
 * @param int    $property_id Property ID.
 * @return array
 * @since 2.0.0
 */
if ( ! function_exists( 'hvnly_get_property_views_data' ) ) {
	function hvnly_get_property_views_data( $property_id = null ) {
		$property_id = $property_id ?: get_the_ID();
		
		if ( ! $property_id ) {
			return array();
		}

		$views_data = array(
			'total'      => hvnly_get_property_views( $property_id, 'total' ),
			'unique'     => hvnly_get_property_views( $property_id, 'unique' ),
			'today'      => hvnly_get_property_views( $property_id, 'today' ),
			'this_week'  => hvnly_get_property_views( $property_id, 'this_week' ),
			'this_month' => hvnly_get_property_views( $property_id, 'this_month' ),
			'formatted'  => array(
				'compact'  => hvnly_get_formatted_property_views( $property_id, 'compact' ),
				'readable' => hvnly_get_formatted_property_views( $property_id, 'readable' ),
				'full'     => hvnly_get_formatted_property_views( $property_id, 'full' ),
				'smart'    => hvnly_get_smart_property_views( $property_id ),
			),
		);

		return apply_filters( 'hvnly_property_views_data', $views_data, $property_id );
	}
}

/**
 * Get property post time in human readable format.
 *
 * @since 2.0.0
 *
 * @param int    $property_id     Property ID.
 * @param string $format          Format type (relative, full, short, custom).
 * @param string $custom_format   Custom date format (if format = 'custom').
 * @return string Formatted time string.
 */
if ( ! function_exists( 'hvnly_get_property_time' ) ) {
	function hvnly_get_property_time( $property_id = null, $format = 'relative', $custom_format = 'F j, Y' ) {
		$property_id = $property_id ? absint( $property_id ) : get_the_ID();

		if ( ! $property_id ) {
			return '';
		}

		$post_date = get_the_date( 'Y-m-d H:i:s', $property_id );
		$post_time = strtotime( $post_date );

		if ( ! $post_time ) {
			return '';
		}

		switch ( $format ) {
			case 'relative':
				return hvnly_get_relative_time( $post_time );

			case 'full':
				return date_i18n( 'F j, Y \a\t g:i A', $post_time );

			case 'short':
				return date_i18n( 'M j, Y', $post_time );

			case 'custom':
				return date_i18n( $custom_format, $post_time );

			case 'time_ago':
				return hvnly_get_time_ago( $post_time );

			default:
				return hvnly_get_smart_time_format( $post_time );
		}
	}
}

/**
 * Get relative time string (smart formatting).
 *
 * @since 2.0.0
 *
 * @param int|string $timestamp Post timestamp or date string.
 * @return string Relative time string.
 */
if ( ! function_exists( 'hvnly_get_relative_time' ) ) {
	function hvnly_get_relative_time( $timestamp ) {
		if ( is_string( $timestamp ) ) {
			$timestamp = strtotime( $timestamp );
		}

		if ( ! $timestamp ) {
			return '';
		}

		$current_time = current_time( 'timestamp' );
		$time_diff    = $current_time - $timestamp;

		// Less than 1 minute.
		if ( $time_diff < 60 ) {
			return esc_html__( 'just now', 'havenlytics' );
		}

		// Less than 1 hour.
		if ( $time_diff < 3600 ) {
			$minutes = floor( $time_diff / 60 );
			/* translators: %d: Number of minutes */
			return sprintf( _n( '%d minute ago', '%d minutes ago', $minutes, 'havenlytics' ), $minutes );
		}

		// Less than 1 day.
		if ( $time_diff < 86400 ) {
			$hours = floor( $time_diff / 3600 );
			/* translators: %d: Number of hours */
			return sprintf( _n( '%d hour ago', '%d hours ago', $hours, 'havenlytics' ), $hours );
		}

		// Less than 1 week.
		if ( $time_diff < 604800 ) {
			$days = floor( $time_diff / 86400 );
			/* translators: %d: Number of days */
			return sprintf( _n( '%d day ago', '%d days ago', $days, 'havenlytics' ), $days );
		}

		// Less than 1 month.
		if ( $time_diff < 2592000 ) {
			$weeks = floor( $time_diff / 604800 );
			/* translators: %d: Number of weeks */
			return sprintf( _n( '%d week ago', '%d weeks ago', $weeks, 'havenlytics' ), $weeks );
		}

		// Less than 1 year.
		if ( $time_diff < 31536000 ) {
			$months = floor( $time_diff / 2592000 );
			/* translators: %d: Number of months */
			return sprintf( _n( '%d month ago', '%d months ago', $months, 'havenlytics' ), $months );
		}

		// Over 1 year.
		$years = floor( $time_diff / 31536000 );
		/* translators: %d: Number of years */
		return sprintf( _n( '%d year ago', '%d years ago', $years, 'havenlytics' ), $years );
	}
}

/**
 * Get smart time format (auto-detects best format).
 *
 * @since 2.0.0
 *
 * @param int|string $timestamp Post timestamp or date string.
 * @return string Smart formatted time string.
 */
if ( ! function_exists( 'hvnly_get_smart_time_format' ) ) {
	function hvnly_get_smart_time_format( $timestamp ) {
		if ( is_string( $timestamp ) ) {
			$timestamp = strtotime( $timestamp );
		}

		if ( ! $timestamp ) {
			return '';
		}

		$current_time = current_time( 'timestamp' );
		$time_diff    = $current_time - $timestamp;

		// Today (within 24 hours).
		if ( $time_diff < 86400 ) {
			return hvnly_get_relative_time( $timestamp );
		}

		// Within this week.
		if ( $time_diff < 604800 ) {
			return date_i18n( 'l', $timestamp );
		}

		// Within this year.
		if ( $time_diff < 31536000 ) {
			return date_i18n( 'M j', $timestamp );
		}

		// Over 1 year ago.
		return date_i18n( 'M j, Y', $timestamp );
	}
}

/**
 * Get time ago string (alternative to relative time).
 *
 * @since 2.0.0
 *
 * @param int|string $timestamp      Post timestamp or date string.
 * @param bool       $include_suffix Include "ago" suffix.
 * @return string Time ago string.
 */
if ( ! function_exists( 'hvnly_get_time_ago' ) ) {
	function hvnly_get_time_ago( $timestamp, $include_suffix = true ) {
		if ( is_string( $timestamp ) ) {
			$timestamp = strtotime( $timestamp );
		}

		if ( ! $timestamp ) {
			return '';
		}

		$current_time = current_time( 'timestamp' );
		$time_diff    = $current_time - $timestamp;

		// Define time periods.
		$periods = array(
			array(
				31536000,
				esc_html__( 'year', 'havenlytics' ),
			),
			array(
				2592000,
				esc_html__( 'month', 'havenlytics' ),
			),
			array(
				604800,
				esc_html__( 'week', 'havenlytics' ),
			),
			array(
				86400,
				esc_html__( 'day', 'havenlytics' ),
			),
			array(
				3600,
				esc_html__( 'hour', 'havenlytics' ),
			),
			array(
				60,
				esc_html__( 'minute', 'havenlytics' ),
			),
			array(
				1,
				esc_html__( 'second', 'havenlytics' ),
			),
		);

		// Find the appropriate period.
		$count   = 0;
		$name    = '';
		$seconds = 0;

		for ( $i = 0, $j = count( $periods ); $i < $j; $i++ ) {
			$seconds = $periods[ $i ][0];
			$name    = $periods[ $i ][1];

			if ( ( $count = floor( $time_diff / $seconds ) ) !== 0 ) {
				break;
			}
		}

		// Format the output.
		if ( 1 === $count ) {
			/* translators: %s: Time period name (year, month, week, etc.) */
			$output = sprintf( esc_html__( '1 %s', 'havenlytics' ), $name );
		} else {
			/* translators: 1: Number of time periods, 2: Time period name (years, months, weeks, etc.) */
			$output = sprintf( esc_html__( '%1$d %2$s', 'havenlytics' ), $count, $name . 's' );
		}

		// Add suffix if requested.
		if ( $include_suffix ) {
			$output .= ' ' . esc_html__( 'ago', 'havenlytics' );
		}

		return $output;
	}
}

/**
 * Display property time.
 *
 * @since 2.0.0
 *
 * @param int    $property_id     Property ID.
 * @param string $format          Format type.
 * @param string $custom_format   Custom format.
 */
if ( ! function_exists( 'hvnly_the_property_time' ) ) {
	function hvnly_the_property_time( $property_id = null, $format = 'relative', $custom_format = 'F j, Y' ) {
		echo esc_html( hvnly_get_property_time( $property_id, $format, $custom_format ) );
	}
}

/**
 * Check if property is new (posted within X days).
 *
 * @since 2.0.0
 *
 * @param int $property_id Property ID.
 * @param int $days        Number of days to consider as "new".
 * @return bool Whether property is new.
 */
if ( ! function_exists( 'hvnly_is_new_property' ) ) {
	function hvnly_is_new_property( $property_id = null, $days = 7 ) {
		$property_id = $property_id ? absint( $property_id ) : get_the_ID();

		if ( ! $property_id ) {
			return false;
		}

		$post_date    = get_the_date( 'Y-m-d H:i:s', $property_id );
		$post_time    = strtotime( $post_date );
		$current_time = current_time( 'timestamp' );

		$days_in_seconds = $days * 86400;

		return ( $current_time - $post_time ) < $days_in_seconds;
	}
}

/**
 * Get property age in days.
 *
 * @since 2.0.0
 *
 * @param int $property_id Property ID.
 * @return int Property age in days.
 */
if ( ! function_exists( 'hvnly_get_property_age_days' ) ) {
	function hvnly_get_property_age_days( $property_id = null ) {
		$property_id = $property_id ? absint( $property_id ) : get_the_ID();

		if ( ! $property_id ) {
			return 0;
		}

		$post_date    = get_the_date( 'Y-m-d H:i:s', $property_id );
		$post_time    = strtotime( $post_date );
		$current_time = current_time( 'timestamp' );

		if ( ! $post_time ) {
			return 0;
		}

		$time_diff = $current_time - $post_time;
		$days      = floor( $time_diff / 86400 );

		return max( 0, $days );
	}
}

if ( ! function_exists( 'hvnly_get_property_image' ) ) {
	function hvnly_get_property_image( $property_id = null, $size = 'full', $attr = array() ) {
		$property_id = $property_id ?: get_the_ID();
		$image_id    = get_post_thumbnail_id( $property_id );

		if ( $image_id ) {
			return wp_get_attachment_image( $image_id, $size, false, $attr );
		}

		return '<img src="' . HVNLYNAB_ASSETS_URL . 'images/placeholder.jpg" alt="' . get_the_title( $property_id ) . '" />';
	}
}

if ( ! function_exists( 'hvnly_get_property_status' ) ) {
	function hvnly_get_property_status( $property_id = null ) {
		$property_id = $property_id ?: get_the_ID();
		$status      = get_the_terms( $property_id, 'property-status' );

		if ( $status && ! is_wp_error( $status ) ) {
			return $status[0];
		}

		return false;
	}
}

if ( ! function_exists( 'hvnly_get_property_types' ) ) {
	function hvnly_get_property_types( $property_id = null ) {
		$property_id = $property_id ?: get_the_ID();
		return get_the_terms( $property_id, 'property-type' );
	}
}

/**
 * Get property meta data
 */
if (!function_exists('hvnly_get_property_meta')) {
    function hvnly_get_property_meta($property_id = null) {
        $property_id = $property_id ?: get_the_ID();
        
        return array(
            'price' => get_post_meta($property_id, '_hvnly_property_price', true),
            'bedrooms' => get_post_meta($property_id, '_hvnly_property_bedrooms', true),
            'bathrooms' => get_post_meta($property_id, '_hvnly_property_bathrooms', true),
            'reception_rooms' => get_post_meta($property_id, '_hvnly_property_reception_rooms', true),
            'area' => get_post_meta($property_id, '_hvnly_property_sqft', true),
            'garage' => get_post_meta($property_id, '_hvnly_property_garage_sqft', true),
            'address' => get_post_meta($property_id, '_hvnly_property_street', true),
            'is_featured' => get_post_meta($property_id, '_hvnly_property_action_tool_is_featured', true),
            'year_built' => get_post_meta($property_id, '_hvnly_property_year_built', true),
        );
    }
}

/**
 * Get property features list
 */
if (!function_exists('hvnly_get_property_features_list')) {
    function hvnly_get_property_features_list($property_id = null) {
        $property_id = $property_id ?: get_the_ID();
        $features_terms = get_the_terms($property_id, 'property-feature');
        
        if (!$features_terms || is_wp_error($features_terms)) {
            return array();
        }
        
        $features = array();
        foreach ($features_terms as $term) {
            $icon = get_term_meta($term->term_id, 'hvnly_term_icon', true);
            $features[] = array(
                'name' => $term->name,
                'icon' => $icon,
                'slug' => $term->slug
            );
        }
        
        return $features;
    }
}

/**
 * Get property amenities list
 */
if (!function_exists('hvnly_get_property_amenities_list')) {
    function hvnly_get_property_amenities_list($property_id = null) {
        $property_id = $property_id ?: get_the_ID();
        
        // Get from meta or use features as fallback
        $amenities = get_post_meta($property_id, '_hvnly_property_amenities', true);
        
        if (empty($amenities)) {
            return hvnly_get_property_features_list($property_id);
        }
        
        return is_array($amenities) ? $amenities : array();
    }
}

/**
 * Get property documents
 */
if (!function_exists('hvnly_get_property_documents')) {
    function hvnly_get_property_documents($property_id = null) {
        $property_id = $property_id ?: get_the_ID();
        $documents = array();
        
        // Add your document meta fields here
        $document_fields = array(
            '_hvnly_property_floor_plan' => __('Floor Plan', 'havenlytics'),
            '_hvnly_property_virtual_tour' => __('Virtual Tour', 'havenlytics'),
            '_hvnly_property_brochure' => __('View Brochure', 'havenlytics'),
            '_hvnly_property_epc' => __('View EPC', 'havenlytics'),
            '_hvnly_property_map' => __('Property Map', 'havenlytics'),
            '_hvnly_property_inspection_report' => __('Inspection Report', 'havenlytics'),
        );
        
        foreach ($document_fields as $meta_key => $title) {
            $url = get_post_meta($property_id, $meta_key, true);
            if ($url) {
                $documents[] = array(
                    'title' => $title,
                    'url' => $url
                );
            }
        }
        
        return $documents;
    }
}

/**
 * Get property agent data
 */
if ( ! function_exists( 'hvnly_get_author_property_ratings' ) ) {
    /**
     * Average property rating for an author/agent.
     *
     * @param int $author_id User ID.
     * @return float
     */
    function hvnly_get_author_property_ratings( $author_id = 0 ) {
        $author_id = absint( $author_id );
        if ( ! $author_id || ! function_exists( 'HVNLY_NAB' ) ) {
            return 0.0;
        }

        $helper = HVNLY_NAB()->Helper ?? null;
        if ( $helper && method_exists( $helper, 'get_author_property_ratings' ) ) {
            return (float) $helper->get_author_property_ratings( $author_id );
        }

        return 0.0;
    }
}

if ( ! function_exists( 'hvnly_get_author_review_count' ) ) {
    /**
     * Approved review count for an author's published properties.
     *
     * @param int $author_id User ID.
     * @return int
     */
    function hvnly_get_author_review_count( $author_id = 0 ) {
        $author_id = absint( $author_id );
        if ( ! $author_id || ! function_exists( 'HVNLY_NAB' ) ) {
            return 0;
        }

        $helper = HVNLY_NAB()->Helper ?? null;
        if ( $helper && method_exists( $helper, 'get_author_review_count' ) ) {
            return (int) $helper->get_author_review_count( $author_id );
        }

        return 0;
    }
}

if (!function_exists('hvnly_get_property_agent')) {
    /**
     * Get the primary property agent profile.
     *
     * Uses Agent CPT assignments when present, otherwise legacy user meta / post author.
     *
     * @param int|null $property_id Property post ID.
     * @return array<string, mixed>
     */
    function hvnly_get_property_agent($property_id = null) {
        $property_id = $property_id ?: get_the_ID();
        $property_id = absint($property_id);

        if ($property_id > 0 && function_exists('hvnly_get_primary_property_agent')) {
            $agent = hvnly_get_primary_property_agent($property_id);
            if (!empty($agent)) {
                return $agent;
            }
        }

        $agent_id = get_post_meta($property_id, '_hvnly_property_agent', true);
        
        if (!$agent_id) {
            $agent_id = get_post_field('post_author', $property_id);
        }

        $agent_id = absint($agent_id);
        
        $agent_data = array(
            'id' => $agent_id,
            'type' => 'user',
            'source' => 'user',
            'user_id' => $agent_id,
            'name' => get_the_author_meta('display_name', $agent_id),
            'email' => get_the_author_meta('email', $agent_id),
            'phone' => get_user_meta($agent_id, '_hvnly_agent_phone', true),
            'avatar' => \HvnlyNab\Common\AvatarService::resolve_for_user((int) $agent_id, 200),
            'position' => get_user_meta($agent_id, '_hvnly_agent_position', true) ?: __('Real Estate Agent', 'havenlytics'),
            'rating' => hvnly_get_author_property_ratings($agent_id),
            'review_count' => hvnly_get_author_review_count($agent_id)
        );
        
        return $agent_data;
    }
}

// ========================================================
// CURRENCY AND PRICE FORMATTING WRAPPER FUNCTIONS
// These call the Helper class methods for backward compatibility
// ========================================================

/**
 * Get currency settings from database
 *
 * @return array
 */
function hvnly_get_currency_settings() {
    return HVNLY_NAB()->Helper->get_currency_settings();
}

/**
 * Get currency symbol by currency code
 *
 * @param string $currency_code
 * @return string
 */
function hvnly_get_currency_symbol($currency_code = 'USD') {
    return HVNLY_NAB()->Helper->get_currency_symbol($currency_code);
}

/**
 * Get current currency symbol
 *
 * @return string
 */
function hvnly_get_current_currency_symbol() {
    return HVNLY_NAB()->Helper->get_current_currency_symbol();
}

/**
 * Get current currency code
 *
 * @return string
 */
function hvnly_get_current_currency_code() {
    return HVNLY_NAB()->Helper->get_current_currency_code();
}

/**
 * Format number with custom separators
 *
 * @param float $number
 * @param string $thousand_separator
 * @param string $decimal_separator
 * @param int $decimals
 * @return string
 */
function hvnly_format_number_with_separators($number, $thousand_separator = ',', $decimal_separator = '.', $decimals = 0) {
    return HVNLY_NAB()->Helper->format_number_with_separators($number, $thousand_separator, $decimal_separator, $decimals);
}

/**
 * Format large numbers with K, M, B suffixes
 *
 * @param float $number
 * @param string $thousand_text
 * @param string $million_text
 * @param string $billion_text
 * @param bool $enabled
 * @return string
 */
function hvnly_format_large_number_with_suffix($number, $thousand_text = 'K', $million_text = 'M', $billion_text = 'B', $enabled = true) {
    return HVNLY_NAB()->Helper->format_large_number_with_suffix($number, $thousand_text, $million_text, $billion_text, $enabled);
}

/**
 * Convert price to float safely with comprehensive cleaning.
 *
 * @param mixed $price Price value to convert.
 * @return float Cleaned price as float.
 */
function hvnly_safe_price_to_float($price) {
    return HVNLY_NAB()->Helper->safe_price_to_float($price);
}

/**
 * Format property price with WordPress.org standards and safe type handling.
 * This is the main function used in frontend templates.
 *
 * @param mixed $price Price value to format.
 * @return string Formatted price string.
 */
function hvnly_format_price($price) {
    return HVNLY_NAB()->Helper->format_price($price);
}

/**
 * Resolve property price for display and calculation eligibility.
 *
 * @param int $property_id Property ID.
 * @return array{
 *     is_calculable: bool,
 *     numeric_price: float|null,
 *     display_price: string,
 *     placeholder_label: string|null
 * }
 */
function hvnly_resolve_property_price( $property_id = null ) {
	$property_id = $property_id ? absint( $property_id ) : get_the_ID();

	return \HvnlyNab\Services\Hvnly_Price_Resolver::resolve( $property_id );
}

/**
 * Format a numeric price using global currency settings (no custom label processing).
 *
 * @param mixed $price Price value.
 * @return string
 */
function hvnly_format_numeric_price( $price ) {
    return HVNLY_NAB()->Helper->format_numeric_price_for_filter( $price );
}

