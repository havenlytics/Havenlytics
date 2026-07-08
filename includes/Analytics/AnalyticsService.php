<?php
/**
 * Analytics data aggregation service.
 *
 * @package HvnlyNab\Analytics
 * @since   3.1.6
 */

namespace HvnlyNab\Analytics;

use HvnlyNab\Agent\AgentConstants;
use HvnlyNab\ContactAgent\Database\InquirySchema;
use HvnlyNab\ContactAgent\InquiryRepository;
use HvnlyNab\Frontend\PropertyViewTracker;

defined( 'ABSPATH' ) || exit;

/**
 * Aggregates property, view, inquiry, and agent metrics for the Analytics dashboard.
 *
 * @since 3.1.6
 */
class AnalyticsService {

	private const CACHE_GROUP = 'hvnly_analytics';
	private const CACHE_TTL   = 900; // 15 minutes.

	/** @var AnalyticsDateRange */
	private $range;

	/** @var InquiryRepository */
	private $inquiry_repository;

	/** @var array<int, array<string, int>>|null */
	private $view_counts_by_post = null;

	/** @var array<int, array<string, array>>|null */
	private $view_analytics_by_post = null;

	/**
	 * @param AnalyticsDateRange   $range              Active date range.
	 * @param InquiryRepository|null $inquiry_repository Optional repository.
	 */
	public function __construct( AnalyticsDateRange $range, ?InquiryRepository $inquiry_repository = null ) {
		$this->range              = $range;
		$this->inquiry_repository = $inquiry_repository ?? new InquiryRepository();
	}

	/**
	 * Summary cards for the dashboard header grid.
	 *
	 * @return array<string, mixed>
	 */
	public function get_overview(): array {
		$cache_key = 'overview_' . $this->range->cache_suffix();
		$cached    = $this->get_cache( $cache_key );
		$live      = $this->get_live_overview_metrics();

		if ( null !== $cached ) {
			return array_merge( $cached, $live );
		}

		$counts       = wp_count_posts( 'hvnly_property' );
		$agent_counts = wp_count_posts( AgentConstants::POST_TYPE );

		$view_totals = $this->aggregate_view_totals();

		$data = array(
			'totalProperties'     => $this->sum_post_counts( $counts ),
			'publishedProperties' => isset( $counts->publish ) ? (int) $counts->publish : 0,
			'pendingProperties'   => isset( $counts->pending ) ? (int) $counts->pending : 0,
			'draftProperties'     => isset( $counts->draft ) ? (int) $counts->draft : 0,
			'featuredProperties'  => $this->count_featured_properties(),
			'totalAgents'         => $this->sum_post_counts( $agent_counts ),
			'totalAgencies'       => $this->count_terms( AgentConstants::TAXONOMY_AGENCY ),
			'totalPropertyViews'  => $view_totals['total'],
			'totalInquiries'      => $this->count_inquiries(),
			'rangeViews'          => $this->count_views_in_range(),
			'rangeInquiries'      => $this->count_inquiries_in_range(
				$this->range->get_start(),
				$this->range->get_end()
			),
		);

		$data = array_merge( $data, $live );

		$this->set_cache( $cache_key, $data );
		return $data;
	}

	/**
	 * Metrics that must not be served from a stale transient.
	 *
	 * @return array<string, int>
	 */
	private function get_live_overview_metrics(): array {
		$view_totals = $this->aggregate_view_totals();

		return array(
			'todaysViews'        => $view_totals['today'],
			'thisMonthViews'     => $view_totals['this_month'],
			'thisMonthInquiries' => $this->count_inquiries_in_range(
				gmdate( 'Y-m-01', strtotime( current_time( 'Y-m-d' ) ) ),
				current_time( 'Y-m-d' )
			),
		);
	}

	/**
	 * Chart datasets for the dashboard.
	 *
	 * @return array<string, mixed>
	 */
	public function get_charts(): array {
		$cache_key = 'charts_' . $this->range->cache_suffix();
		$cached    = $this->get_cache( $cache_key );
		if ( null !== $cached ) {
			return $cached;
		}

		$data = array(
			'monthlyListings'    => $this->get_monthly_listings(),
			'monthlyViews'       => $this->get_monthly_views(),
			'monthlyInquiries'   => $this->get_monthly_inquiries(),
			'newAgents'          => $this->get_monthly_new_agents(),
			'propertyStatus'     => $this->get_property_status_distribution(),
			'department'         => $this->get_taxonomy_distribution( 'hvnly_prop_depts' ),
			'propertyTypes'      => $this->get_taxonomy_distribution( 'hvnly_prop_types' ),
			'agencyDistribution' => $this->get_taxonomy_distribution( AgentConstants::TAXONOMY_AGENCY ),
			'inquiryStatus'      => $this->get_inquiry_status_distribution(),
			'topLocations'       => $this->get_top_locations_by_views(),
			'topAgents'          => $this->get_top_agents_by_inquiries( 10 ),
			'topAgencies'        => $this->get_top_agencies( 10 ),
		);

		$this->set_cache( $cache_key, $data );
		return $data;
	}

	/**
	 * Table rows for the dashboard.
	 *
	 * @return array<string, mixed>
	 */
	public function get_tables(): array {
		$cache_key = 'tables_' . $this->range->cache_suffix();
		$cached    = $this->get_cache( $cache_key );
		if ( null !== $cached ) {
			return $cached;
		}

		$data = array(
			'topViewedProperties'   => $this->get_top_viewed_properties_table( 10 ),
			'latestProperties'      => $this->get_latest_properties_table( 10 ),
			'latestInquiries'       => $this->get_latest_inquiries_table( 10 ),
			'mostActiveAgents'      => $this->get_most_active_agents_table( 10 ),
			'topAgencies'           => $this->get_top_agencies_table( 10 ),
			'popularPropertyTypes'  => $this->get_popular_terms_table( 'hvnly_prop_types', 10 ),
			'popularLocations'      => $this->get_popular_terms_table( 'hvnly_prop_locations', 10 ),
		);

		$this->set_cache( $cache_key, $data );
		return $data;
	}

	/**
	 * Export rows for CSV download.
	 *
	 * @param string $type Export type slug.
	 * @return array{filename: string, headers: string[], rows: array<int, array<int, string|int>>}
	 */
	public function get_export( string $type ): array {
		$type = sanitize_key( $type );
		$date_suffix = $this->range->get_start() . '_to_' . $this->range->get_end();

		switch ( $type ) {
			case 'inquiries':
				return array(
					'filename' => 'havenlytics-inquiries-' . $date_suffix . '.csv',
					'headers'  => array( 'ID', 'Property', 'Agent', 'Sender', 'Email', 'Status', 'Date' ),
					'rows'     => $this->export_inquiry_rows(),
				);
			case 'agents':
				return array(
					'filename' => 'havenlytics-agents-' . $date_suffix . '.csv',
					'headers'  => array( 'Agent', 'Inquiries', 'Properties' ),
					'rows'     => $this->export_agent_rows(),
				);
			case 'views':
				return array(
					'filename' => 'havenlytics-views-' . $date_suffix . '.csv',
					'headers'  => array( 'Property', 'Total Views', 'Unique Views', 'Today', 'This Month' ),
					'rows'     => $this->export_view_rows(),
				);
			case 'agencies':
				return array(
					'filename' => 'havenlytics-agencies-' . $date_suffix . '.csv',
					'headers'  => array( 'Agency', 'Agents', 'Properties' ),
					'rows'     => $this->export_agency_rows(),
				);
			case 'properties':
			default:
				return array(
					'filename' => 'havenlytics-properties-' . $date_suffix . '.csv',
					'headers'  => array( 'ID', 'Title', 'Status', 'Views', 'Featured', 'Date' ),
					'rows'     => $this->export_property_rows(),
				);
		}
	}

	/**
	 * @param object|null $counts WP post counts object.
	 * @return int
	 */
	private function sum_post_counts( $counts ): int {
		if ( ! $counts || ! is_object( $counts ) ) {
			return 0;
		}

		$total = 0;
		foreach ( get_post_stati() as $status ) {
			if ( isset( $counts->$status ) ) {
				$total += (int) $counts->$status;
			}
		}
		return $total;
	}

	/**
	 * @return int
	 */
	private function count_featured_properties(): int {
		$query = new \WP_Query(
			array(
				'post_type'      => 'hvnly_property',
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'no_found_rows'  => false,
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'     => '_hvnly_property_featured',
						'value'   => array( '1', 1, 'yes', 'true' ),
						'compare' => 'IN',
					),
				),
			)
		);

		return (int) $query->found_posts;
	}

	/**
	 * @param string $taxonomy Taxonomy slug.
	 * @return int
	 */
	private function count_terms( string $taxonomy ): int {
		$result = wp_count_terms(
			array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => false,
			)
		);

		return is_wp_error( $result ) ? 0 : (int) $result;
	}

	/**
	 * @return array{total: int, today: int, this_month: int}
	 */
	private function aggregate_view_totals(): array {
		$totals = array(
			'total'      => 0,
			'today'      => 0,
			'this_month' => 0,
		);

		foreach ( $this->get_view_counts_by_post() as $views ) {
			$totals['total']      += isset( $views['total'] ) ? (int) $views['total'] : 0;
			$totals['today']      += isset( $views['today'] ) ? (int) $views['today'] : 0;
			$totals['this_month'] += isset( $views['this_month'] ) ? (int) $views['this_month'] : 0;
		}

		return $totals;
	}

	/**
	 * @return int
	 */
	private function count_views_in_range(): int {
		$total = 0;
		$start = $this->range->get_start();
		$end   = $this->range->get_end();

		foreach ( $this->get_view_analytics_by_post() as $analytics ) {
			foreach ( $analytics as $date => $stats ) {
				if ( $date >= $start && $date <= $end && is_array( $stats ) ) {
					$total += isset( $stats['total'] ) ? (int) $stats['total'] : 0;
				}
			}
		}

		return $total;
	}

	/**
	 * @return int
	 */
	private function count_inquiries(): int {
		return $this->inquiry_repository->count();
	}

	/**
	 * @param string $start_date Y-m-d.
	 * @param string $end_date   Y-m-d.
	 * @return int
	 */
	private function count_inquiries_in_range( string $start_date, string $end_date ): int {
		if ( ! InquirySchema::table_exists() ) {
			return 0;
		}

		global $wpdb;

		$table = InquirySchema::table_name();
		$start = $start_date . ' 00:00:00';
		$end   = $end_date . ' 23:59:59';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM `{$table}` WHERE created_at >= %s AND created_at <= %s",
				$start,
				$end
			)
		);

		return max( 0, (int) $count );
	}

	/**
	 * @return array{labels: string[], values: int[]}
	 */
	private function get_monthly_listings(): array {
		global $wpdb;

		$start = $this->range->get_start() . ' 00:00:00';
		$end   = $this->range->get_end() . ' 23:59:59';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DATE_FORMAT(post_date, '%%Y-%%m') AS month_key, COUNT(*) AS total
				FROM {$wpdb->posts}
				WHERE post_type = %s
				AND post_status NOT IN ('auto-draft', 'trash')
				AND post_date >= %s AND post_date <= %s
				GROUP BY month_key
				ORDER BY month_key ASC",
				'hvnly_property',
				$start,
				$end
			),
			ARRAY_A
		);

		return $this->format_series_from_rows( $rows, 'month_key', 'total' );
	}

	/**
	 * @return array{labels: string[], values: int[]}
	 */
	private function get_monthly_views(): array {
		$buckets = $this->init_date_buckets();

		foreach ( $this->get_view_analytics_by_post() as $analytics ) {
			foreach ( $analytics as $date => $stats ) {
				if ( ! isset( $buckets[ $date ] ) || ! is_array( $stats ) ) {
					continue;
				}
				$buckets[ $date ] += isset( $stats['total'] ) ? (int) $stats['total'] : 0;
			}
		}

		return $this->format_bucket_series( $buckets );
	}

	/**
	 * @return array{labels: string[], values: int[]}
	 */
	private function get_monthly_inquiries(): array {
		if ( ! InquirySchema::table_exists() ) {
			return array( 'labels' => array(), 'values' => array() );
		}

		global $wpdb;

		$table = InquirySchema::table_name();
		$start = $this->range->get_start() . ' 00:00:00';
		$end   = $this->range->get_end() . ' 23:59:59';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DATE(created_at) AS day_key, COUNT(*) AS total
				FROM `{$table}`
				WHERE created_at >= %s AND created_at <= %s
				GROUP BY day_key
				ORDER BY day_key ASC",
				$start,
				$end
			),
			ARRAY_A
		);

		return $this->format_series_from_rows( $rows, 'day_key', 'total' );
	}

	/**
	 * @return array{labels: string[], values: int[]}
	 */
	private function get_monthly_new_agents(): array {
		global $wpdb;

		$start = $this->range->get_start() . ' 00:00:00';
		$end   = $this->range->get_end() . ' 23:59:59';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DATE_FORMAT(post_date, '%%Y-%%m') AS month_key, COUNT(*) AS total
				FROM {$wpdb->posts}
				WHERE post_type = %s
				AND post_status NOT IN ('auto-draft', 'trash')
				AND post_date >= %s AND post_date <= %s
				GROUP BY month_key
				ORDER BY month_key ASC",
				AgentConstants::POST_TYPE,
				$start,
				$end
			),
			ARRAY_A
		);

		return $this->format_series_from_rows( $rows, 'month_key', 'total' );
	}

	/**
	 * @return array{labels: string[], values: int[]}
	 */
	private function get_property_status_distribution(): array {
		$counts = wp_count_posts( 'hvnly_property' );
		$labels = array();
		$values = array();

		$map = array(
			'publish' => __( 'Published', 'havenlytics' ),
			'pending' => __( 'Pending', 'havenlytics' ),
			'draft'   => __( 'Draft', 'havenlytics' ),
			'future'  => __( 'Scheduled', 'havenlytics' ),
			'private' => __( 'Private', 'havenlytics' ),
		);

		foreach ( $map as $status => $label ) {
			$count = isset( $counts->$status ) ? (int) $counts->$status : 0;
			if ( $count > 0 ) {
				$labels[] = $label;
				$values[] = $count;
			}
		}

		$featured = $this->count_featured_properties();
		if ( $featured > 0 ) {
			$labels[] = __( 'Featured', 'havenlytics' );
			$values[] = $featured;
		}

		return array(
			'labels' => $labels,
			'values' => $values,
		);
	}

	/**
	 * @param string $taxonomy Taxonomy slug.
	 * @return array{labels: string[], values: int[]}
	 */
	private function get_taxonomy_distribution( string $taxonomy ): array {
		$terms = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => true,
				'number'     => 20,
				'orderby'    => 'count',
				'order'      => 'DESC',
			)
		);

		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return array( 'labels' => array(), 'values' => array() );
		}

		$labels = array();
		$values = array();
		foreach ( $terms as $term ) {
			$labels[] = $term->name;
			$values[] = (int) $term->count;
		}

		return array(
			'labels' => $labels,
			'values' => $values,
		);
	}

	/**
	 * @return array{labels: string[], values: int[]}
	 */
	private function get_inquiry_status_distribution(): array {
		if ( ! InquirySchema::table_exists() ) {
			return array( 'labels' => array(), 'values' => array() );
		}

		global $wpdb;

		$table = InquirySchema::table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			"SELECT status, COUNT(*) AS total FROM `{$table}` GROUP BY status ORDER BY total DESC",
			ARRAY_A
		);

		$labels = array();
		$values = array();
		foreach ( $rows as $row ) {
			$labels[] = ucfirst( (string) $row['status'] );
			$values[] = (int) $row['total'];
		}

		return array(
			'labels' => $labels,
			'values' => $values,
		);
	}

	/**
	 * @param int $limit Max rows.
	 * @return array<int, array<string, mixed>>
	 */
	private function get_top_locations_by_views( int $limit = 10 ): array {
		$location_scores = array();

		foreach ( $this->get_view_counts_by_post() as $property_id => $views ) {
			$total = isset( $views['total'] ) ? (int) $views['total'] : 0;
			if ( $total <= 0 ) {
				continue;
			}

			$terms = get_the_terms( $property_id, 'hvnly_prop_locations' );
			if ( empty( $terms ) || is_wp_error( $terms ) ) {
				continue;
			}

			foreach ( $terms as $term ) {
				if ( ! isset( $location_scores[ $term->term_id ] ) ) {
					$location_scores[ $term->term_id ] = array(
						'label' => $term->name,
						'value' => 0,
					);
				}
				$location_scores[ $term->term_id ]['value'] += $total;
			}
		}

		usort(
			$location_scores,
			static function ( $a, $b ) {
				return $b['value'] <=> $a['value'];
			}
		);

		return array_slice( array_values( $location_scores ), 0, $limit );
	}

	/**
	 * @param int $limit Max rows.
	 * @return array<int, array<string, mixed>>
	 */
	private function get_top_agents_by_inquiries( int $limit = 10 ): array {
		if ( ! InquirySchema::table_exists() ) {
			return array();
		}

		global $wpdb;

		$table = InquirySchema::table_name();
		$start = $this->range->get_start() . ' 00:00:00';
		$end   = $this->range->get_end() . ' 23:59:59';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT agent_id, COUNT(*) AS total
				FROM `{$table}`
				WHERE agent_id > 0
				AND created_at >= %s AND created_at <= %s
				GROUP BY agent_id
				ORDER BY total DESC
				LIMIT %d",
				$start,
				$end,
				$limit
			),
			ARRAY_A
		);

		$result = array();
		foreach ( $rows as $row ) {
			$agent_id = (int) $row['agent_id'];
			$result[] = array(
				'label' => get_the_title( $agent_id ) ?: sprintf( __( 'Agent #%d', 'havenlytics' ), $agent_id ),
				'value' => (int) $row['total'],
			);
		}

		return $result;
	}

	/**
	 * @param int $limit Max rows.
	 * @return array<int, array<string, mixed>>
	 */
	private function get_top_agencies( int $limit = 10 ): array {
		$terms = get_terms(
			array(
				'taxonomy'   => AgentConstants::TAXONOMY_AGENCY,
				'hide_empty' => true,
				'number'     => $limit,
				'orderby'    => 'count',
				'order'      => 'DESC',
			)
		);

		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return array();
		}

		$result = array();
		foreach ( $terms as $term ) {
			$result[] = array(
				'label' => $term->name,
				'value' => (int) $term->count,
			);
		}

		return $result;
	}

	/**
	 * @param int $limit Max rows.
	 * @return array<int, array<string, mixed>>
	 */
	private function get_top_viewed_properties_table( int $limit ): array {
		$tracker = new PropertyViewTracker();
		$posts   = $tracker->get_top_viewed_properties( $limit, 'all' );
		$rows    = array();
		$view_meta = $this->get_view_counts_by_post();

		foreach ( $posts as $post ) {
			$views  = $view_meta[ $post->ID ] ?? array();
			$rows[] = array(
				'id'     => $post->ID,
				'title'  => get_the_title( $post->ID ),
				'views'  => isset( $views['total'] ) ? (int) $views['total'] : 0,
				'status' => get_post_status( $post->ID ),
				'link'   => get_edit_post_link( $post->ID, 'raw' ),
			);
		}

		return $rows;
	}

	/**
	 * @param int $limit Max rows.
	 * @return array<int, array<string, mixed>>
	 */
	private function get_latest_properties_table( int $limit ): array {
		$posts = get_posts(
			array(
				'post_type'      => 'hvnly_property',
				'post_status'    => array( 'publish', 'pending', 'draft' ),
				'posts_per_page' => $limit,
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);

		$rows = array();
		foreach ( $posts as $post ) {
			$rows[] = array(
				'id'     => $post->ID,
				'title'  => get_the_title( $post->ID ),
				'status' => $post->post_status,
				'date'   => get_the_date( 'Y-m-d', $post->ID ),
				'link'   => get_edit_post_link( $post->ID, 'raw' ),
			);
		}

		return $rows;
	}

	/**
	 * @param int $limit Max rows.
	 * @return array<int, array<string, mixed>>
	 */
	private function get_latest_inquiries_table( int $limit ): array {
		$inquiries = $this->inquiry_repository->list(
			array(
				'limit'  => $limit,
				'offset' => 0,
			)
		);

		$rows = array();
		foreach ( $inquiries as $inquiry ) {
			$rows[] = array(
				'id'       => $inquiry['id'],
				'property' => get_the_title( $inquiry['property_id'] ) ?: '—',
				'sender'   => $inquiry['sender_name'],
				'status'   => $inquiry['status'],
				'date'     => $inquiry['created_at'],
			);
		}

		return $rows;
	}

	/**
	 * @param int $limit Max rows.
	 * @return array<int, array<string, mixed>>
	 */
	private function get_most_active_agents_table( int $limit ): array {
		$agents = $this->get_top_agents_by_inquiries( $limit );
		$rows   = array();

		foreach ( $agents as $agent ) {
			$rows[] = array(
				'name'       => $agent['label'],
				'inquiries'  => $agent['value'],
			);
		}

		return $rows;
	}

	/**
	 * @param int $limit Max rows.
	 * @return array<int, array<string, mixed>>
	 */
	private function get_top_agencies_table( int $limit ): array {
		$agencies = $this->get_top_agencies( $limit );
		$rows     = array();

		foreach ( $agencies as $agency ) {
			$rows[] = array(
				'name'    => $agency['label'],
				'agents'  => $agency['value'],
			);
		}

		return $rows;
	}

	/**
	 * @param string $taxonomy Taxonomy slug.
	 * @param int    $limit    Max rows.
	 * @return array<int, array<string, mixed>>
	 */
	private function get_popular_terms_table( string $taxonomy, int $limit ): array {
		$terms = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => true,
				'number'     => $limit,
				'orderby'    => 'count',
				'order'      => 'DESC',
			)
		);

		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return array();
		}

		$rows = array();
		foreach ( $terms as $term ) {
			$rows[] = array(
				'name'  => $term->name,
				'count' => (int) $term->count,
			);
		}

		return $rows;
	}

	/**
	 * @return array<int, array<int, string|int>>
	 */
	private function export_property_rows(): array {
		$posts = get_posts(
			array(
				'post_type'      => 'hvnly_property',
				'post_status'    => 'any',
				'posts_per_page' => 500,
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);

		$rows = array();
		$view_meta = $this->get_view_counts_by_post();
		foreach ( $posts as $post ) {
			$views  = $view_meta[ $post->ID ] ?? array();
			$featured = get_post_meta( $post->ID, '_hvnly_property_featured', true );
			$rows[]   = array(
				$post->ID,
				$post->post_title,
				$post->post_status,
				isset( $views['total'] ) ? (int) $views['total'] : 0,
				in_array( (string) $featured, array( '1', 'yes', 'true' ), true ) ? 'Yes' : 'No',
				get_the_date( 'Y-m-d', $post->ID ),
			);
		}

		return $rows;
	}

	/**
	 * @return array<int, array<int, string|int>>
	 */
	private function export_inquiry_rows(): array {
		if ( ! InquirySchema::table_exists() ) {
			return array();
		}

		global $wpdb;

		$table = InquirySchema::table_name();
		$start = $this->range->get_start() . ' 00:00:00';
		$end   = $this->range->get_end() . ' 23:59:59';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM `{$table}` WHERE created_at >= %s AND created_at <= %s ORDER BY created_at DESC LIMIT 1000",
				$start,
				$end
			),
			ARRAY_A
		);

		$export = array();
		foreach ( $rows as $row ) {
			$export[] = array(
				(int) $row['id'],
				get_the_title( (int) $row['property_id'] ) ?: '—',
				get_the_title( (int) $row['agent_id'] ) ?: '—',
				(string) $row['sender_name'],
				(string) $row['sender_email'],
				(string) $row['status'],
				(string) $row['created_at'],
			);
		}

		return $export;
	}

	/**
	 * @return array<int, array<int, string|int>>
	 */
	private function export_agent_rows(): array {
		$agents = $this->get_top_agents_by_inquiries( 100 );
		$rows   = array();

		foreach ( $agents as $agent ) {
			$rows[] = array(
				$agent['label'],
				$agent['value'],
				'—',
			);
		}

		return $rows;
	}

	/**
	 * @return array<int, array<int, string|int>>
	 */
	private function export_view_rows(): array {
		$table     = $this->get_top_viewed_properties_table( 500 );
		$view_meta = $this->get_view_counts_by_post();
		$rows      = array();

		foreach ( $table as $item ) {
			$views  = $view_meta[ (int) $item['id'] ] ?? array();
			$rows[] = array(
				$item['title'],
				isset( $views['total'] ) ? (int) $views['total'] : 0,
				isset( $views['unique'] ) ? (int) $views['unique'] : 0,
				isset( $views['today'] ) ? (int) $views['today'] : 0,
				isset( $views['this_month'] ) ? (int) $views['this_month'] : 0,
			);
		}

		return $rows;
	}

	/**
	 * @return array<int, array<int, string|int>>
	 */
	private function export_agency_rows(): array {
		$terms = get_terms(
			array(
				'taxonomy'   => AgentConstants::TAXONOMY_AGENCY,
				'hide_empty' => false,
			)
		);

		if ( is_wp_error( $terms ) ) {
			return array();
		}

		$rows = array();
		foreach ( $terms as $term ) {
			$rows[] = array(
				$term->name,
				(int) $term->count,
				'—',
			);
		}

		return $rows;
	}

	/**
	 * Load all property view count meta in a single query (per request).
	 *
	 * @return array<int, array<string, int>>
	 */
	private function get_view_counts_by_post(): array {
		if ( null !== $this->view_counts_by_post ) {
			return $this->view_counts_by_post;
		}

		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT pm.post_id, pm.meta_value
				FROM {$wpdb->postmeta} pm
				INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
				WHERE pm.meta_key = %s AND p.post_type = %s",
				PropertyViewTracker::VIEW_COUNT_META,
				'hvnly_property'
			),
			ARRAY_A
		);

		$map = array();
		foreach ( $rows as $row ) {
			$value = maybe_unserialize( $row['meta_value'] );
			if ( is_array( $value ) ) {
				$map[ (int) $row['post_id'] ] = $value;
			}
		}

		$this->view_counts_by_post = $map;
		return $this->view_counts_by_post;
	}

	/**
	 * Load all per-day view analytics meta in a single query (per request).
	 *
	 * @return array<int, array<string, array<string, mixed>>>
	 */
	private function get_view_analytics_by_post(): array {
		if ( null !== $this->view_analytics_by_post ) {
			return $this->view_analytics_by_post;
		}

		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT pm.post_id, pm.meta_value
				FROM {$wpdb->postmeta} pm
				INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
				WHERE pm.meta_key = %s AND p.post_type = %s",
				PropertyViewTracker::VIEW_ANALYTICS_META,
				'hvnly_property'
			),
			ARRAY_A
		);

		$map = array();
		foreach ( $rows as $row ) {
			$value = maybe_unserialize( $row['meta_value'] );
			if ( is_array( $value ) ) {
				$map[ (int) $row['post_id'] ] = $value;
			}
		}

		$this->view_analytics_by_post = $map;
		return $this->view_analytics_by_post;
	}

	/**
	 * @return int[]
	 */
	private function get_property_ids(): array {
		$cache_key = 'property_ids';
		$cached    = $this->get_cache( $cache_key );
		if ( null !== $cached ) {
			return $cached;
		}

		$ids = get_posts(
			array(
				'post_type'      => 'hvnly_property',
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
			)
		);

		$ids = array_map( 'intval', $ids );
		$this->set_cache( $cache_key, $ids, 1800 );
		return $ids;
	}

	/**
	 * @return array<string, int>
	 */
	private function init_date_buckets(): array {
		$buckets = array();
		$start   = strtotime( $this->range->get_start() );
		$end     = strtotime( $this->range->get_end() );

		for ( $time = $start; $time <= $end; $time += DAY_IN_SECONDS ) {
			$buckets[ gmdate( 'Y-m-d', $time ) ] = 0;
		}

		return $buckets;
	}

	/**
	 * @param array<string, int> $buckets Date => value map.
	 * @return array{labels: string[], values: int[]}
	 */
	private function format_bucket_series( array $buckets ): array {
		return array(
			'labels' => array_keys( $buckets ),
			'values' => array_values( $buckets ),
		);
	}

	/**
	 * @param array<int, array<string, mixed>> $rows     SQL rows.
	 * @param string                           $key_col  Label column.
	 * @param string                           $val_col  Value column.
	 * @return array{labels: string[], values: int[]}
	 */
	private function format_series_from_rows( array $rows, string $key_col, string $val_col ): array {
		$labels = array();
		$values = array();

		foreach ( $rows as $row ) {
			$labels[] = (string) $row[ $key_col ];
			$values[] = (int) $row[ $val_col ];
		}

		return array(
			'labels' => $labels,
			'values' => $values,
		);
	}

	/**
	 * @param string $key Cache key suffix.
	 * @return mixed|null
	 */
	private function get_cache( string $key ) {
		$full_key = 'hvnly_analytics_' . md5( $key );
		$cached   = get_transient( $full_key );
		return false === $cached ? null : $cached;
	}

	/**
	 * @param string $key  Cache key suffix.
	 * @param mixed  $data Data to store.
	 * @param int    $ttl  Optional TTL override.
	 */
	private function set_cache( string $key, $data, int $ttl = 0 ): void {
		$full_key = 'hvnly_analytics_' . md5( $key );
		set_transient( $full_key, $data, $ttl > 0 ? $ttl : self::CACHE_TTL );
	}
}
