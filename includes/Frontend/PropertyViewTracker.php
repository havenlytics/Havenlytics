<?php
/**
 * Property View Tracker for Havenlytics
 *
 * @package     Havenlytics
 * @subpackage  Frontend
 * @copyright   Copyright (c) 2025, Havenlytics
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       2.0.0
 */

namespace HvnlyNab\Frontend;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Property View Tracker class
 *
 * @since 2.0.0
 */
class PropertyViewTracker {

	/**
	 * Meta key for storing view counts
	 *
	 * @var string
	 */
	const VIEW_COUNT_META = '_hvnly_property_views';

	/**
	 * Meta key for storing detailed view analytics
	 *
	 * @var string
	 */
	const VIEW_ANALYTICS_META = '_hvnly_property_view_analytics';

	/**
	 * Cookie name for tracking unique views
	 *
	 * @var string
	 */
	const VIEW_COOKIE = 'hvnly_property_views';

	/**
	 * Cookie name for per-property view rate limiting
	 *
	 * @var string
	 */
	const VIEW_RATE_COOKIE = 'hvnly_property_view_rate';

	/**
	 * Minimum seconds between counted views for the same property in one browser.
	 *
	 * @var int
	 */
	const VIEW_COOLDOWN_SECONDS = 60;

	/**
	 * Cache group for view counts
	 *
	 * @var string
	 */
	const CACHE_GROUP = 'hvnly_property_views';

	/**
	 * Tracked properties in current request
	 *
	 * @var array
	 */
	private $tracked_properties = array();

	/**
	 * Constructor
	 *
	 * @since 2.0.0
	 */
	public function __construct() {
		add_action( 'wp', array( $this, 'track_property_view' ) );
		add_action( 'hvnly_daily_analytics_cleanup', array( $this, 'cleanup_old_analytics' ) );
	}

	/**
	 * Track property view
	 *
	 * @since 2.0.0
	 */
	public function track_property_view() {
		if ( ! is_singular( 'hvnly_property' ) ) {
			return;
		}

		$property_id = get_the_ID();

		if ( ! $property_id || $this->should_skip_tracking( $property_id ) ) {
			return;
		}

		// Prevent duplicate tracking in same request
		if ( in_array( $property_id, $this->tracked_properties, true ) ) {
			return;
		}

		$this->tracked_properties[] = $property_id;
		$user_id                    = get_current_user_id();
		$is_unique                  = $this->is_unique_view( $property_id );

		$this->increment_view_count( $property_id, $is_unique );
		$this->record_view_analytics( $property_id, $user_id, $is_unique );
		$this->mark_view_cooldown( $property_id );

		// Clear cache for this property
		$this->clear_property_view_cache( $property_id );
	}

	/**
	 * Determine whether the current request should be excluded from view tracking.
	 *
	 * @param int $property_id Property ID.
	 * @return bool
	 */
	private function should_skip_tracking( $property_id ) {
		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
			return true;
		}

		if ( is_preview() || is_customize_preview() ) {
			return true;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return true;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only preview context detection.
		if ( isset( $_GET['preview'] ) || isset( $_GET['elementor-preview'] ) ) {
			return true;
		}

		if ( $this->is_bot_user_agent() ) {
			return true;
		}

		return $this->is_within_view_cooldown( $property_id );
	}

	/**
	 * Detect common crawler and bot user agents.
	 *
	 * @return bool
	 */
	private function is_bot_user_agent() {
		if ( empty( $_SERVER['HTTP_USER_AGENT'] ) ) {
			return false;
		}

		$user_agent = strtolower( sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) );
		$patterns   = array(
			'bot',
			'spider',
			'crawl',
			'slurp',
			'mediapartners',
			'facebookexternalhit',
			'whatsapp',
			'preview',
			'curl/',
			'wget/',
			'python-requests',
			'headless',
			'googlebot',
			'bingbot',
			'yandex',
			'baiduspider',
			'duckduckbot',
			'semrush',
			'ahrefs',
			'mj12bot',
			'petalbot',
		);

		foreach ( $patterns as $pattern ) {
			if ( false !== strpos( $user_agent, $pattern ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check whether the same browser recently counted a view for this property.
	 *
	 * @param int $property_id Property ID.
	 * @return bool
	 */
	private function is_within_view_cooldown( $property_id ) {
		$cookie_value = isset( $_COOKIE[ self::VIEW_RATE_COOKIE ] )
			? sanitize_text_field( wp_unslash( $_COOKIE[ self::VIEW_RATE_COOKIE ] ) )
			: '';

		$rate_map = $cookie_value ? json_decode( $cookie_value, true ) : array();
		if ( ! is_array( $rate_map ) ) {
			return false;
		}

		$property_id = absint( $property_id );
		$last_view   = isset( $rate_map[ $property_id ] ) ? absint( $rate_map[ $property_id ] ) : 0;

		return $last_view > 0 && ( time() - $last_view ) < self::VIEW_COOLDOWN_SECONDS;
	}

	/**
	 * Record the latest counted view timestamp for cooldown enforcement.
	 *
	 * @param int $property_id Property ID.
	 * @return void
	 */
	private function mark_view_cooldown( $property_id ) {
		$cookie_value = isset( $_COOKIE[ self::VIEW_RATE_COOKIE ] )
			? sanitize_text_field( wp_unslash( $_COOKIE[ self::VIEW_RATE_COOKIE ] ) )
			: '';

		$rate_map = $cookie_value ? json_decode( $cookie_value, true ) : array();
		if ( ! is_array( $rate_map ) ) {
			$rate_map = array();
		}

		$property_id              = absint( $property_id );
		$rate_map[ $property_id ] = time();

		if ( count( $rate_map ) > 50 ) {
			$rate_map = array_slice( $rate_map, -50, 50, true );
		}

		setcookie(
			self::VIEW_RATE_COOKIE,
			wp_json_encode( $rate_map ),
			time() + ( 30 * DAY_IN_SECONDS ),
			COOKIEPATH,
			COOKIE_DOMAIN,
			is_ssl(),
			true
		);
	}

	/**
	 * Check if this is a unique view
	 *
	 * @param int $property_id Property ID.
	 * @return bool
	 * @since 2.0.0
	 */
	private function is_unique_view( $property_id ) {
		// Properly unslash the cookie value first
		$cookie_value = isset( $_COOKIE[ self::VIEW_COOKIE ] )
		? sanitize_text_field( wp_unslash( $_COOKIE[ self::VIEW_COOKIE ] ) )
		: '';

		$viewed_properties = $cookie_value ? json_decode( $cookie_value, true ) : array();

		if ( ! is_array( $viewed_properties ) ) {
			$viewed_properties = array();
		}

		if ( in_array( $property_id, $viewed_properties, true ) ) {
			return false;
		}

		// Add to viewed properties
		$viewed_properties[] = $property_id;

		// Keep only last 50 properties to avoid cookie size issues
		if ( count( $viewed_properties ) > 50 ) {
			$viewed_properties = array_slice( $viewed_properties, -50 );
		}

		setcookie(
			self::VIEW_COOKIE,
			wp_json_encode( $viewed_properties ),
			time() + ( 30 * DAY_IN_SECONDS ),
			COOKIEPATH,
			COOKIE_DOMAIN,
			is_ssl(),
			true
		);

		return true;
	}

	/**
	 * Increment view count with caching
	 *
	 * @param int  $property_id Property ID.
	 * @param bool $is_unique Whether this is a unique view.
	 * @since 2.0.0
	 */
	private function increment_view_count( $property_id, $is_unique = true ) {
		// Get current views with proper default structure
		$current_views = get_post_meta( $property_id, self::VIEW_COUNT_META, true );

		// Ensure we have a proper array structure
		if ( empty( $current_views ) || ! is_array( $current_views ) ) {
			$current_views = array(
				'total'      => 0,
				'unique'     => 0,
				'today'      => 0,
				'this_week'  => 0,
				'this_month' => 0,
			);
		}

		// Ensure all keys exist with proper defaults
		$defaults = array(
			'total'      => 0,
			'unique'     => 0,
			'today'      => 0,
			'this_week'  => 0,
			'this_month' => 0,
		);

		$current_views = wp_parse_args( $current_views, $defaults );

		// Update counts - ensure we're working with integers
		$current_views['total']      = absint( $current_views['total'] ) + 1;
		$current_views['today']      = absint( $current_views['today'] ) + 1;
		$current_views['this_week']  = absint( $current_views['this_week'] ) + 1;
		$current_views['this_month'] = absint( $current_views['this_month'] ) + 1;

		if ( $is_unique ) {
			$current_views['unique'] = absint( $current_views['unique'] ) + 1;
		}

		// Update the meta - use update_post_meta to ensure it's created if doesn't exist
		$updated = update_post_meta( $property_id, self::VIEW_COUNT_META, $current_views );
	}

	/**
	 * Record detailed view analytics
	 *
	 * @param int  $property_id Property ID.
	 * @param int  $user_id User ID.
	 * @param bool $is_unique Whether this is a unique view.
	 * @since 2.0.0
	 */
	private function record_view_analytics( $property_id, $user_id, $is_unique ) {
		$current_date = current_time( 'Y-m-d' );
		$analytics    = get_post_meta( $property_id, self::VIEW_ANALYTICS_META, true );

		// Ensure we have a proper array
		if ( empty( $analytics ) || ! is_array( $analytics ) ) {
			$analytics = array();
		}

		// Initialize today's stats if not exists
		if ( ! isset( $analytics[ $current_date ] ) ) {
			$analytics[ $current_date ] = array(
				'total'  => 0,
				'unique' => 0,
				'users'  => array(),
			);
		}

		// Ensure today's stats have proper structure
		$daily_defaults = array(
			'total'  => 0,
			'unique' => 0,
			'users'  => array(),
		);

		$analytics[ $current_date ] = wp_parse_args( $analytics[ $current_date ], $daily_defaults );

		// Update counts
		$analytics[ $current_date ]['total'] = absint( $analytics[ $current_date ]['total'] ) + 1;

		if ( $is_unique ) {
			$analytics[ $current_date ]['unique'] = absint( $analytics[ $current_date ]['unique'] ) + 1;
		}

		// Track user if logged in
		if ( $user_id ) {
			$analytics[ $current_date ]['users'][ $user_id ] = current_time( 'timestamp' );
		}

		// Keep only last 365 days of data
		if ( count( $analytics ) > 365 ) {
			$analytics = array_slice( $analytics, -365, 365, true );
		}

		update_post_meta( $property_id, self::VIEW_ANALYTICS_META, $analytics );
	}

	/**
	 * Get view count for property with caching
	 *
	 * @param int    $property_id Property ID.
	 * @param string $type View type (total, unique, today, etc.).
	 * @return int
	 * @since 2.0.0
	 */
	public function get_view_count( $property_id = null, $type = 'total' ) {
		$property_id = $property_id ?: get_the_ID();

		if ( ! $property_id ) {
			return 0;
		}

		$cache_key = "property_views_{$property_id}_{$type}";
		$views     = wp_cache_get( $cache_key, self::CACHE_GROUP );

		if ( false === $views ) {
			$views_data = get_post_meta( $property_id, self::VIEW_COUNT_META, true );

			// If no data exists, return 0
			if ( empty( $views_data ) || ! is_array( $views_data ) ) {
				$views = 0;
			} else {
				// Ensure we have the requested type, fallback to total
				$views = isset( $views_data[ $type ] ) ? absint( $views_data[ $type ] ) : absint( $views_data['total'] );
			}

			wp_cache_set( $cache_key, $views, self::CACHE_GROUP, HOUR_IN_SECONDS );
		}

		return $views;
	}

	/**
	 * Get view analytics for property with caching
	 *
	 * @param int    $property_id Property ID.
	 * @param string $period Period for analytics.
	 * @return array
	 * @since 2.0.0
	 */
	public function get_view_analytics( $property_id = null, $period = '30days' ) {
		$property_id = $property_id ?: get_the_ID();

		if ( ! $property_id ) {
			return array();
		}

		$cache_key = "property_analytics_{$property_id}_{$period}";
		$analytics = wp_cache_get( $cache_key, self::CACHE_GROUP );

		if ( false === $analytics ) {
			$all_analytics = get_post_meta( $property_id, self::VIEW_ANALYTICS_META, true );

			if ( ! $all_analytics || ! is_array( $all_analytics ) ) {
				return array();
			}

			// Use gmdate() for consistent UTC date handling
			$end_date   = current_time( 'Y-m-d', true ); // true = GMT
			$start_date = gmdate( 'Y-m-d', strtotime( "-{$period}", strtotime( $end_date ) ) );

			$analytics = array();
			foreach ( $all_analytics as $date => $stats ) {
				if ( $date >= $start_date && $date <= $end_date ) {
					$analytics[ $date ] = $stats;
				}
			}

			wp_cache_set( $cache_key, $analytics, self::CACHE_GROUP, HOUR_IN_SECONDS );
		}

		return $analytics;
	}

	/**
	 * Get top viewed properties with caching
	 *
	 * @param int    $limit Number of properties to return.
	 * @param string $period Period for ranking.
	 * @return array
	 * @since 2.0.0
	 */
	public function get_top_viewed_properties( $limit = 10, $period = 'all' ) {
		$cache_key  = "top_viewed_properties_{$limit}_{$period}";
		$properties = wp_cache_get( $cache_key, self::CACHE_GROUP );

		if ( false === $properties ) {
			$property_ids = get_posts(
				array(
					'post_type'      => 'hvnly_property',
					'post_status'    => 'any',
					'posts_per_page' => -1,
					'fields'         => 'ids',
					'no_found_rows'  => true,
				)
			);

			$scores = array();
			foreach ( $property_ids as $property_id ) {
				$views_data = get_post_meta( $property_id, self::VIEW_COUNT_META, true );
				if ( ! is_array( $views_data ) ) {
					continue;
				}

				$total = isset( $views_data['total'] ) ? absint( $views_data['total'] ) : 0;
				if ( $total > 0 ) {
					$scores[ (int) $property_id ] = $total;
				}
			}

			if ( empty( $scores ) ) {
				$properties = array();
			} else {
				arsort( $scores );
				$top_ids = array_slice( array_keys( $scores ), 0, absint( $limit ) );

				$properties = get_posts(
					array(
						'post_type'      => 'hvnly_property',
						'post_status'    => 'any',
						'posts_per_page' => count( $top_ids ),
						'post__in'       => $top_ids,
						'orderby'        => 'post__in',
					)
				);
			}

			wp_cache_set( $cache_key, $properties, self::CACHE_GROUP, HOUR_IN_SECONDS );
		}

		return $properties;
	}

	/**
	 * Clear cache for property views
	 *
	 * @param int $property_id Property ID.
	 * @since 2.0.0
	 */
	private function clear_property_view_cache( $property_id ) {
		$cache_keys = array(
			"property_views_{$property_id}_total",
			"property_views_{$property_id}_unique",
			"property_views_{$property_id}_today",
			"property_views_{$property_id}_this_week",
			"property_views_{$property_id}_this_month",
		);

		foreach ( $cache_keys as $cache_key ) {
			wp_cache_delete( $cache_key, self::CACHE_GROUP );
		}

		// Clear top properties cache.
		wp_cache_delete( 'top_viewed_properties_10_all', self::CACHE_GROUP );
		wp_cache_delete( 'top_viewed_properties_10_30days', self::CACHE_GROUP );
	}

	/**
	 * Cleanup old analytics data
	 *
	 * @since 2.0.0
	 */
	public function cleanup_old_analytics() {
		$properties = get_posts(
			array(
				'post_type'      => 'hvnly_property',
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		);

		if ( empty( $properties ) ) {
			return;
		}

		// Cutoff date (2 years ago in UTC).
		$cutoff_date = gmdate( 'Y-m-d', time() - ( 2 * YEAR_IN_SECONDS ) );

		foreach ( $properties as $property_id ) {
			$analytics = get_post_meta( $property_id, self::VIEW_ANALYTICS_META, true );

			if ( ! empty( $analytics ) && is_array( $analytics ) ) {
				$updated = false;

				foreach ( $analytics as $date => $stats ) {
					if ( $date < $cutoff_date ) {
						unset( $analytics[ $date ] );
						$updated = true;
					}
				}

				// Update only if something changed.
				if ( $updated ) {
					update_post_meta( $property_id, self::VIEW_ANALYTICS_META, $analytics );
				}
			}
		}

		// Clear all view caches after cleanup (wp_cache_flush_group requires WP 6.1+).
		if ( function_exists( 'wp_cache_flush_group' ) ) {
			wp_cache_flush_group( self::CACHE_GROUP );
		}
	}
}
