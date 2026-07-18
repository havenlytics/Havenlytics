<?php
/**
 * Workspace Analytics MVP — author-scoped metrics from existing data only.
 *
 * @package HvnlyNab\Workspace\Api
 * @since   3.2.0
 */

namespace HvnlyNab\Workspace\Api;

use HvnlyNab\Frontend\PropertyViewTracker;
use HvnlyNab\Workspace\Auth\AgentIdentityService;
use HvnlyNab\Workspace\Auth\PortalAuthorization;
use HvnlyNab\Workspace\WorkspaceDateTime;

defined( 'ABSPATH' ) || exit;

/**
 * Honest analytics (no placeholders). Reuses DashboardService aggregators.
 *
 * @since 3.2.0
 */
final class AnalyticsService {

	/**
	 * Transient TTL for expensive aggregates.
	 */
	private const CACHE_TTL = 300;

	/**
	 * @var AgentIdentityService
	 */
	private $identity;

	/**
	 * @var PortalAuthorization
	 */
	private $auth;

	/**
	 * @var DashboardService
	 */
	private $dashboard;

	/**
	 * @param AgentIdentityService|null $identity  Identity.
	 * @param PortalAuthorization|null  $auth      Authz.
	 * @param DashboardService|null     $dashboard Dashboard aggregators.
	 */
	public function __construct(
		?AgentIdentityService $identity = null,
		?PortalAuthorization $auth = null,
		?DashboardService $dashboard = null
	) {
		$this->identity  = $identity ? $identity : AgentIdentityService::get_instance();
		$this->auth      = $auth ? $auth : new PortalAuthorization( $this->identity );
		$this->dashboard = $dashboard ? $dashboard : new DashboardService( $this->identity, $this->auth );
	}

	/**
	 * Analytics overview for the current user.
	 *
	 * @param int    $user_id User ID.
	 * @param string $range   today|7d|30d|month|all.
	 * @return array<string, mixed>
	 */
	public function get_overview( int $user_id, string $range = '30d' ): array {
		$user_id = absint( $user_id );
		$range   = $this->normalize_range( $range );

		$cache_key = 'hvnly_ws_analytics_' . $user_id . '_' . $range;
		$cached    = get_transient( $cache_key );
		if ( is_array( $cached ) && isset( $cached['range'] ) && (string) $cached['range'] === $range ) {
			return $cached;
		}

		$window     = $this->range_window( $range );
		$counts     = $this->dashboard->property_counts( $user_id );
		$views_7    = $this->dashboard->listing_views_series( $user_id, 7 );
		$views_30   = $this->dashboard->listing_views_series( $user_id, 30 );
		$views_rng  = $this->dashboard->listing_views_series( $user_id, (int) $window['days'] );
		$inquiries  = $this->inquiry_metrics( $user_id, $window );
		$created_30 = $this->listings_created_series( $user_id, 30 );
		$created_rng = $range === '30d'
			? $created_30
			: $this->listings_created_series( $user_id, (int) $window['days'] );

		$payload = array(
			'range'            => $range,
			'rangeLabel'       => $window['label'],
			'metrics'          => $this->build_metrics( $counts, $views_7, $views_30, $views_rng, $inquiries ),
			'charts'           => array(
				'listingsCreated' => array(
					'period' => '30d',
					'label'  => 'Last 30 days',
					'days'   => $created_30['days'],
					'total'  => $created_30['total'],
					'max'    => $created_30['max'],
				),
				'propertyViews'   => array(
					'period' => '30d',
					'label'  => 'Last 30 days',
					'days'   => $views_30['days'],
					'total'  => $views_30['total'],
					'max'    => $views_30['max'],
				),
				'listingsByStatus' => array(
					'label'  => 'Current inventory',
					'slices' => $this->status_slices( $counts ),
					'total'  => $this->status_total( $counts ),
				),
				/* Range-scoped series for the filter (honest; may mirror 30d). */
				'rangeViews'    => array(
					'period' => $range,
					'label'  => $window['label'],
					'days'   => $views_rng['days'],
					'total'  => $views_rng['total'],
					'max'    => $views_rng['max'],
				),
				'rangeCreated'  => array(
					'period' => $range,
					'label'  => $window['label'],
					'days'   => $created_rng['days'],
					'total'  => $created_rng['total'],
					'max'    => $created_rng['max'],
				),
			),
			'tables'           => array(
				'topViewed'        => $this->top_viewed_listings( $user_id, $window, 8 ),
				'newest'           => $this->newest_listings( $user_id, 8 ),
				'recentlyUpdated'  => $this->dashboard->recent_properties( $user_id, 8 ),
			),
			'availableMetrics' => array_keys(
				array_filter(
					$this->build_metrics( $counts, $views_7, $views_30, $views_rng, $inquiries ),
					static function ( $v ) {
						return null !== $v;
					}
				)
			),
		);

		set_transient( $cache_key, $payload, self::CACHE_TTL );

		return $payload;
	}

	/**
	 * @param string $range Raw range.
	 * @return string
	 */
	private function normalize_range( string $range ): string {
		$range = sanitize_key( $range );
		$allowed = array( 'today', '7d', '30d', 'month', 'all' );
		return in_array( $range, $allowed, true ) ? $range : '30d';
	}

	/**
	 * @param string $range Normalized range.
	 * @return array{days:int,label:string,date_from:string,date_to:string,all:bool}
	 */
	private function range_window( string $range ): array {
		$now_ts = current_time( 'timestamp' );
		$end_gmt = gmdate( 'Y-m-d 23:59:59', time() );

		switch ( $range ) {
			case 'today':
				$days = 1;
				$label = 'Today';
				$start_local = wp_date( 'Y-m-d 00:00:00', $now_ts );
				break;
			case '7d':
				$days = 7;
				$label = 'Last 7 days';
				$start_local = wp_date( 'Y-m-d 00:00:00', strtotime( '-6 days', $now_ts ) );
				break;
			case 'month':
				$day_of_month = (int) wp_date( 'j', $now_ts );
				$days         = max( 1, $day_of_month );
				$label        = 'This month';
				$start_local  = wp_date( 'Y-m-01 00:00:00', $now_ts );
				break;
			case 'all':
				$days = 365;
				$label = 'All time';
				$start_local = '';
				break;
			case '30d':
			default:
				$days = 30;
				$label = 'Last 30 days';
				$start_local = wp_date( 'Y-m-d 00:00:00', strtotime( '-29 days', $now_ts ) );
				break;
		}

		$date_from = '';
		if ( '' !== $start_local ) {
			$gmt = get_gmt_from_date( $start_local );
			$date_from = is_string( $gmt ) && '' !== $gmt ? $gmt : $start_local;
		}

		return array(
			'days'      => $days,
			'label'     => $label,
			'date_from' => $date_from,
			'date_to'   => $end_gmt,
			'all'       => 'all' === $range,
		);
	}

	/**
	 * @param array<string,int>                                                                 $counts Counts.
	 * @param array{days:array,total:int,max:int}                                                $v7     Views 7d.
	 * @param array{days:array,total:int,max:int}                                                $v30    Views 30d.
	 * @param array{days:array,total:int,max:int}                                                $vrng   Views range.
	 * @param array{new:?int,unread:?int}                                                        $inq    Inquiries.
	 * @return array<string, int|null>
	 */
	private function build_metrics( array $counts, array $v7, array $v30, array $vrng, array $inq ): array {
		$total_listings = absint( $counts['published'] ?? 0 )
			+ absint( $counts['pending'] ?? 0 )
			+ absint( $counts['draft'] ?? 0 )
			+ absint( $counts['rejected'] ?? 0 )
			+ absint( $counts['trash'] ?? 0 );

		return array(
			'totalListings'    => $total_listings,
			'published'        => absint( $counts['published'] ?? 0 ),
			'pending'          => absint( $counts['pending'] ?? 0 ),
			'draft'            => absint( $counts['draft'] ?? 0 ),
			'rejected'         => absint( $counts['rejected'] ?? 0 ),
			'trash'            => absint( $counts['trash'] ?? 0 ),
			'views7d'          => absint( $v7['total'] ?? 0 ),
			'views30d'         => absint( $v30['total'] ?? 0 ),
			'viewsInRange'     => absint( $vrng['total'] ?? 0 ),
			'newInquiries'     => array_key_exists( 'new', $inq ) ? $inq['new'] : null,
			'unreadInquiries'  => array_key_exists( 'unread', $inq ) ? $inq['unread'] : null,
		);
	}

	/**
	 * @param int                  $user_id User ID.
	 * @param array<string, mixed> $window  Range window.
	 * @return array{new:?int,unread:?int}
	 */
	private function inquiry_metrics( int $user_id, array $window ): array {
		if ( ! class_exists( InquiryService::class ) || ! $this->auth->can_manage_inquiries( $user_id ) ) {
			return array();
		}

		$service = new InquiryService( $this->identity, $this->auth );
		$query   = array(
			'page'    => 1,
			'perPage' => 1,
			'status'  => 'all',
		);
		if ( empty( $window['all'] ) && ! empty( $window['date_from'] ) ) {
			$query['dateFrom'] = (string) $window['date_from'];
			$query['dateTo']   = (string) $window['date_to'];
		}

		$result = $service->list_inquiries( $user_id, $query );
		if ( is_wp_error( $result ) || ! is_array( $result ) ) {
			return array();
		}

		return array(
			'new'    => absint( $result['total'] ?? 0 ),
			'unread' => absint( $result['unread'] ?? 0 ),
		);
	}

	/**
	 * @param array<string,int> $counts Counts.
	 * @return array<int, array{id:string,label:string,value:int,color:string}>
	 */
	private function status_slices( array $counts ): array {
		$map = array(
			'published' => array( 'Published', 'var(--hvnly-brand-primary)' ),
			'pending'   => array( 'Pending', 'var(--hvnly-brand-warning, #d97706)' ),
			'draft'     => array( 'Draft', 'var(--hvnly-text-secondary)' ),
			'rejected'  => array( 'Rejected', 'var(--hvnly-brand-danger, #dc2626)' ),
			'trash'     => array( 'Trash', 'var(--hvnly-border-color, #9ca3af)' ),
		);
		$slices = array();
		foreach ( $map as $key => $meta ) {
			$value = absint( $counts[ $key ] ?? 0 );
			if ( $value <= 0 ) {
				continue;
			}
			$slices[] = array(
				'id'    => $key,
				'label' => $meta[0],
				'value' => $value,
				'color' => $meta[1],
			);
		}
		return $slices;
	}

	/**
	 * @param array<string,int> $counts Counts.
	 * @return int
	 */
	private function status_total( array $counts ): int {
		$sum = 0;
		foreach ( array( 'published', 'pending', 'draft', 'rejected', 'trash' ) as $key ) {
			$sum += absint( $counts[ $key ] ?? 0 );
		}
		return $sum;
	}

	/**
	 * Listings created per day for the author (single query, no N+1).
	 *
	 * @param int $user_id User ID.
	 * @param int $days    Day count.
	 * @return array{days: array<int, array{date:string,label:string,count:int}>, total:int, max:int}
	 */
	private function listings_created_series( int $user_id, int $days ): array {
		$days = max( 1, min( 366, absint( $days ) ) );

		$buckets = array();
		for ( $i = $days - 1; $i >= 0; $i-- ) {
			$ts   = strtotime( '-' . $i . ' days', current_time( 'timestamp' ) );
			$date = wp_date( 'Y-m-d', $ts );
			$buckets[ $date ] = 0;
		}

		$start_date = array_key_first( $buckets );
		$query      = new \WP_Query(
			array(
				'post_type'              => PropertyFormMapper::POST_TYPE,
				'post_status'            => array( 'publish', 'pending', 'draft', 'trash' ),
				'author'                 => $user_id,
				'posts_per_page'         => -1,
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'date_query'             => array(
					array(
						'after'     => $start_date . ' 00:00:00',
						'inclusive' => true,
						'column'    => 'post_date',
					),
				),
			)
		);

		foreach ( $query->posts as $post_id ) {
			$post = get_post( (int) $post_id );
			if ( ! $post instanceof \WP_Post ) {
				continue;
			}
			$date = wp_date( 'Y-m-d', strtotime( $post->post_date ) );
			if ( isset( $buckets[ $date ] ) ) {
				$buckets[ $date ]++;
			}
		}

		$label_format = $days <= 7 ? 'D' : 'M j';
		$series       = array();
		$total        = 0;
		$max          = 0;
		foreach ( $buckets as $date => $count ) {
			$total   += $count;
			$max      = max( $max, $count );
			$series[] = array(
				'date'  => $date,
				'label' => wp_date( $label_format, strtotime( $date . ' 12:00:00' ) ),
				'count' => $count,
			);
		}

		return array(
			'days'  => $series,
			'total' => $total,
			'max'   => $max,
		);
	}

	/**
	 * Top viewed listings — period views when daily meta exists; else all-time totals.
	 *
	 * @param int                  $user_id User ID.
	 * @param array<string, mixed> $window  Range.
	 * @param int                  $limit   Limit.
	 * @return array<int, array<string, mixed>>
	 */
	private function top_viewed_listings( int $user_id, array $window, int $limit ): array {
		$ids = $this->dashboard->author_property_ids( $user_id );
		if ( empty( $ids ) ) {
			return array();
		}

		update_meta_cache( 'post', $ids );

		$days   = max( 1, absint( $window['days'] ?? 30 ) );
		$dates  = array();
		for ( $i = $days - 1; $i >= 0; $i-- ) {
			$ts      = strtotime( '-' . $i . ' days', current_time( 'timestamp' ) );
			$dates[] = wp_date( 'Y-m-d', $ts );
		}

		$rows = array();
		foreach ( $ids as $property_id ) {
			$post = get_post( $property_id );
			if ( ! $post instanceof \WP_Post ) {
				continue;
			}

			$period_views = 0;
			$analytics    = get_post_meta( $property_id, PropertyViewTracker::VIEW_ANALYTICS_META, true );
			if ( is_array( $analytics ) && empty( $window['all'] ) ) {
				foreach ( $dates as $date ) {
					if ( ! empty( $analytics[ $date ] ) && is_array( $analytics[ $date ] ) ) {
						$period_views += absint( $analytics[ $date ]['total'] ?? 0 );
					}
				}
			} else {
				$period_views = $this->dashboard->property_total_views( $property_id );
			}

			if ( $period_views <= 0 ) {
				continue;
			}

			$item = PropertyFormMapper::list_item_from_post( $post );
			$rows[] = array(
				'id'           => (int) $item['id'],
				'title'        => (string) $item['title'],
				'status'       => (string) $item['status'],
				'updatedAt'    => (string) $item['updatedAt'],
				'views'        => $period_views,
				'thumbnailUrl' => (string) ( $item['thumbnailUrl'] ?? '' ),
				'editUrl'      => '#/property/' . (int) $item['id'] . '/edit',
			);
		}

		usort(
			$rows,
			static function ( $a, $b ) {
				return (int) ( $b['views'] ?? 0 ) <=> (int) ( $a['views'] ?? 0 );
			}
		);

		return array_slice( $rows, 0, max( 1, $limit ) );
	}

	/**
	 * Newest listings by post_date.
	 *
	 * @param int $user_id User ID.
	 * @param int $limit   Limit.
	 * @return array<int, array<string, mixed>>
	 */
	private function newest_listings( int $user_id, int $limit ): array {
		$query = new \WP_Query(
			array(
				'post_type'              => PropertyFormMapper::POST_TYPE,
				'post_status'            => array( 'publish', 'pending', 'draft' ),
				'author'                 => $user_id,
				'posts_per_page'         => $limit,
				'orderby'                => 'date',
				'order'                  => 'DESC',
				'no_found_rows'          => true,
				'update_post_meta_cache' => true,
				'update_post_term_cache' => false,
			)
		);

		$items = array();
		foreach ( $query->posts as $post ) {
			if ( ! $post instanceof \WP_Post ) {
				continue;
			}
			$item        = PropertyFormMapper::list_item_from_post( $post );
			$created_ts  = WorkspaceDateTime::from_post_created( $post );
			$items[]     = array(
				'id'           => (int) $item['id'],
				'title'        => (string) $item['title'],
				'status'       => (string) $item['status'],
				'updatedAt'    => (string) $item['updatedAt'],
				'createdAt'    => $created_ts > 0 ? WorkspaceDateTime::relative_ago( $created_ts ) : '',
				'views'        => $this->dashboard->property_total_views( (int) $post->ID ),
				'thumbnailUrl' => (string) ( $item['thumbnailUrl'] ?? '' ),
				'editUrl'      => '#/property/' . (int) $item['id'] . '/edit',
			);
		}
		return $items;
	}
}
