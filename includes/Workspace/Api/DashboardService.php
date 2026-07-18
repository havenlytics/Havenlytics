<?php
/**
 * Builds Workspace Dashboard Home payload for the current user.
 *
 * @package HvnlyNab\Workspace\Api
 * @since   3.2.0
 */

namespace HvnlyNab\Workspace\Api;

use HvnlyNab\Frontend\PropertyViewTracker;
use HvnlyNab\Workspace\Auth\AgentIdentityService;
use HvnlyNab\Workspace\Auth\PortalAuthorization;
use HvnlyNab\Workspace\Auth\WorkspaceRegistrationStatus;
use HvnlyNab\Workspace\WorkspaceDateTime;

defined( 'ABSPATH' ) || exit;

/**
 * Real dashboard data (no placeholders).
 *
 * @since 3.2.0
 */
final class DashboardService {

	/**
	 * @var AgentIdentityService
	 */
	private $identity;

	/**
	 * @var PortalAuthorization
	 */
	private $auth;

	/**
	 * @var PropertyWorkflowService
	 */
	private $workflow;

	/**
	 * @param AgentIdentityService|null    $identity Identity.
	 * @param PortalAuthorization|null     $auth     Authz.
	 * @param PropertyWorkflowService|null $workflow Workflow (history).
	 */
	public function __construct(
		?AgentIdentityService $identity = null,
		?PortalAuthorization $auth = null,
		?PropertyWorkflowService $workflow = null
	) {
		$this->identity = $identity ? $identity : AgentIdentityService::get_instance();
		$this->auth     = $auth ? $auth : new PortalAuthorization( $this->identity );
		$this->workflow = $workflow ? $workflow : new PropertyWorkflowService( new PropertyAccessGate( $this->auth ) );
	}

	/**
	 * Full dashboard home for the current logged-in user (author-scoped).
	 *
	 * @param int $user_id User ID.
	 * @return array<string, mixed>
	 */
	public function get_home( int $user_id ): array {
		$user_id = absint( $user_id );
		$resolved = $this->identity->resolve( $user_id );
		$agent    = isset( $resolved['agent'] ) && is_array( $resolved['agent'] ) ? $resolved['agent'] : null;
		$user     = isset( $resolved['user'] ) && is_array( $resolved['user'] ) ? $resolved['user'] : array();
		$role     = isset( $resolved['role'] ) ? (string) $resolved['role'] : '';

		$counts   = $this->property_counts( $user_id );
		$recent   = $this->recent_properties( $user_id, 5 );
		$activity = $this->recent_activity( $user_id, 8 );
		$views    = $this->listing_views_series( $user_id, 7 );
		$inquiries = $this->inquiry_summary( $user_id );

		$registration = '';
		if ( class_exists( WorkspaceRegistrationStatus::class ) ) {
			$registration = (string) WorkspaceRegistrationStatus::get_for_user( $user_id );
		}

		$agency = null;
		if ( $agent && ! empty( $agent['agency'] ) && is_array( $agent['agency'] ) ) {
			$agency = array(
				'id'   => isset( $agent['agency']['id'] ) ? (int) $agent['agency']['id'] : 0,
				'name' => isset( $agent['agency']['name'] ) ? (string) $agent['agency']['name'] : '',
			);
		}

		return array(
			'profile'           => array(
				'name'               => (string) ( $agent['title'] ?? ( $user['display_name'] ?? '' ) ),
				'avatarUrl'          => '' !== (string) ( $agent['avatar_url'] ?? '' )
					? (string) $agent['avatar_url']
					: \HvnlyNab\Common\AvatarService::resolve_for_user( (int) ( $user['id'] ?? 0 ), 96 ),
				'agency'             => $agency,
				'role'               => $role,
				'registrationStatus' => $registration,
				'userDisplayName'    => (string) ( $user['display_name'] ?? '' ),
				'agentId'            => isset( $agent['id'] ) ? (int) $agent['id'] : 0,
			),
			'propertySummary'   => $counts,
			'recentProperties'  => $recent,
			'recentActivity'    => $activity,
			'listingViews'      => $views,
			'inquirySummary'    => $inquiries,
			'quickActions'      => array(
				array(
					'id'    => 'create_property',
					'label' => 'Create Property',
					'route' => 'property/new',
				),
				array(
					'id'    => 'my_properties',
					'label' => 'My Properties',
					'route' => 'properties',
				),
				array(
					'id'    => 'my_profile',
					'label' => 'My Profile',
					'route' => 'profile',
				),
				array(
					'id'    => 'inquiries',
					'label' => 'Inquiries',
					'route' => 'inquiries',
				),
			),
			'permissions'       => array(
				'canCreateListings' => $this->auth->can_create_listings( $user_id ),
				'canEditProfile'    => $this->auth->can_edit_own_profile( $user_id ),
			),
		);
	}

	/**
	 * Unread / new inquiry count for the dashboard tile.
	 *
	 * @param int $user_id User ID.
	 * @return array{new:int}
	 */
	public function inquiry_summary( int $user_id ): array {
		$new = 0;
		if ( ! class_exists( InquiryService::class ) ) {
			return array( 'new' => 0 );
		}
		if ( ! $this->auth->can_manage_inquiries( $user_id ) ) {
			return array( 'new' => 0 );
		}

		$service = new InquiryService( $this->identity, $this->auth );
		$result  = $service->list_inquiries(
			$user_id,
			array(
				'page'    => 1,
				'perPage' => 1,
				'status'  => 'all',
			)
		);
		if ( ! is_wp_error( $result ) && is_array( $result ) ) {
			$new = absint( $result['unread'] ?? 0 );
		}

		return array( 'new' => $new );
	}

	/**
	 * Optimized per-status counts for the current author.
	 *
	 * @param int $user_id User ID.
	 * @return array{published:int,pending:int,draft:int,rejected:int,trash:int,total:int}
	 */
	public function property_counts( int $user_id ): array {
		$published = $this->count_posts( $user_id, array( 'publish' ) );
		$pending   = $this->count_posts( $user_id, array( 'pending' ) );
		$draft     = $this->count_posts( $user_id, array( 'draft' ) );
		$trash     = $this->count_posts( $user_id, array( 'trash' ) );
		$rejected  = $this->count_rejected( $user_id );

		return array(
			'published' => $published,
			'pending'   => $pending,
			'draft'     => $draft,
			'rejected'  => $rejected,
			'trash'     => $trash,
			'total'     => $published + $pending + $draft,
		);
	}

	/**
	 * @param int      $user_id  User ID.
	 * @param string[] $statuses Post statuses.
	 * @return int
	 */
	private function count_posts( int $user_id, array $statuses ): int {
		$query = new \WP_Query(
			array(
				'post_type'              => PropertyFormMapper::POST_TYPE,
				'post_status'            => $statuses,
				'author'                 => $user_id,
				'posts_per_page'         => 1,
				'fields'                 => 'ids',
				'no_found_rows'          => false,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);
		return (int) $query->found_posts;
	}

	/**
	 * Rejected listings (listing meta), authored by user.
	 *
	 * @param int $user_id User ID.
	 * @return int
	 */
	private function count_rejected( int $user_id ): int {
		$query = new \WP_Query(
			array(
				'post_type'              => PropertyFormMapper::POST_TYPE,
				'post_status'            => array( 'draft', 'pending', 'publish' ),
				'author'                 => $user_id,
				'posts_per_page'         => 1,
				'fields'                 => 'ids',
				'no_found_rows'          => false,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'meta_query'             => array(
					array(
						'key'   => PropertyFormMapper::META_LISTING_STATUS,
						'value' => 'Rejected',
					),
				),
			)
		);
		return (int) $query->found_posts;
	}

	/**
	 * @param int $user_id User ID.
	 * @param int $limit   Max items.
	 * @return array<int, array<string, mixed>>
	 */
	public function recent_properties( int $user_id, int $limit ): array {
		$query = new \WP_Query(
			array(
				'post_type'              => PropertyFormMapper::POST_TYPE,
				'post_status'            => array( 'publish', 'pending', 'draft' ),
				'author'                 => $user_id,
				'posts_per_page'         => $limit,
				'orderby'                => 'modified',
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
			$item = PropertyFormMapper::list_item_from_post( $post );
			$items[] = array(
				'id'           => (int) $item['id'],
				'title'        => (string) $item['title'],
				'status'       => (string) $item['status'],
				'updatedAt'    => (string) $item['updatedAt'],
				'modifiedGmt'  => (string) $item['modifiedGmt'],
				'thumbnailUrl' => (string) $item['thumbnailUrl'],
				'price'        => (string) ( $item['price'] ?? '' ),
				'views'        => $this->property_total_views( (int) $post->ID ),
				'editUrl'      => '#/property/' . (int) $item['id'] . '/edit',
			);
		}
		return $items;
	}

	/**
	 * Last N days of listing views for the author's properties.
	 * Reuses PropertyViewTracker daily analytics meta (no duplicate tracking).
	 *
	 * @param int $user_id User ID.
	 * @param int $days    Number of days (inclusive of today). Max 366.
	 * @return array{days: array<int, array{date:string,label:string,views:int}>, total:int, max:int}
	 */
	public function listing_views_series( int $user_id, int $days = 7 ): array {
		$days = max( 1, min( 366, absint( $days ) ) );
		$ids  = $this->author_property_ids( $user_id );

		if ( ! empty( $ids ) ) {
			update_meta_cache( 'post', $ids );
		}

		$buckets = array();
		for ( $i = $days - 1; $i >= 0; $i-- ) {
			$ts   = strtotime( '-' . $i . ' days', current_time( 'timestamp' ) );
			$date = wp_date( 'Y-m-d', $ts );
			$buckets[ $date ] = 0;
		}

		foreach ( $ids as $property_id ) {
			$analytics = get_post_meta( $property_id, PropertyViewTracker::VIEW_ANALYTICS_META, true );
			if ( ! is_array( $analytics ) ) {
				continue;
			}
			foreach ( $buckets as $date => $current ) {
				if ( empty( $analytics[ $date ] ) || ! is_array( $analytics[ $date ] ) ) {
					continue;
				}
				$buckets[ $date ] = $current + absint( $analytics[ $date ]['total'] ?? 0 );
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
				'views' => $count,
			);
		}

		return array(
			'days'  => $series,
			'total' => $total,
			'max'   => $max,
		);
	}

	/**
	 * @param int $user_id User ID.
	 * @return int[]
	 */
	public function author_property_ids( int $user_id ): array {
		$query = new \WP_Query(
			array(
				'post_type'              => PropertyFormMapper::POST_TYPE,
				'post_status'            => array( 'publish', 'pending', 'draft' ),
				'author'                 => $user_id,
				'posts_per_page'         => -1,
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);

		return array_map( 'intval', $query->posts );
	}

	/**
	 * Total views for a property from existing tracker meta.
	 *
	 * @param int $property_id Property ID.
	 * @return int
	 */
	public function property_total_views( int $property_id ): int {
		$data = get_post_meta( $property_id, PropertyViewTracker::VIEW_COUNT_META, true );
		if ( is_array( $data ) && isset( $data['total'] ) ) {
			return absint( $data['total'] );
		}
		if ( is_numeric( $data ) ) {
			return absint( $data );
		}
		return 0;
	}

	/**
	 * Real activity from workflow history + create/trash events only.
	 * Empty array when nothing exists (no fake rows).
	 *
	 * @param int $user_id User ID.
	 * @param int $limit   Max items.
	 * @return array<int, array<string, mixed>>
	 */
	private function recent_activity( int $user_id, int $limit ): array {
		$query = new \WP_Query(
			array(
				'post_type'              => PropertyFormMapper::POST_TYPE,
				'post_status'            => array( 'publish', 'pending', 'draft', 'trash' ),
				'author'                 => $user_id,
				'posts_per_page'         => 12,
				'orderby'                => 'modified',
				'order'                  => 'DESC',
				'no_found_rows'          => true,
				'update_post_meta_cache' => true,
				'update_post_term_cache' => false,
			)
		);

		$events = array();
		foreach ( $query->posts as $post ) {
			if ( ! $post instanceof \WP_Post ) {
				continue;
			}
			$title = (string) $post->post_title;
			if ( '' === $title ) {
				$title = sprintf( 'Property #%d', (int) $post->ID );
			}

			$created_ts = WorkspaceDateTime::from_post_created( $post );
			if ( $created_ts > 0 ) {
				$events[] = array(
					'id'          => 'created-' . (int) $post->ID,
					'type'        => 'created',
					'title'       => 'Property created',
					'description' => $title,
					'time'        => WorkspaceDateTime::relative_ago( $created_ts ),
					'at'          => WorkspaceDateTime::to_iso_gmt( $created_ts ),
					'propertyId'  => (int) $post->ID,
				);
			}

			if ( 'trash' === $post->post_status ) {
				$mod = WorkspaceDateTime::from_post_modified( $post );
				if ( $mod > 0 ) {
					$events[] = array(
						'id'          => 'deleted-' . (int) $post->ID . '-' . $mod,
						'type'        => 'deleted',
						'title'       => 'Property deleted',
						'description' => $title,
						'time'        => WorkspaceDateTime::relative_ago( $mod ),
						'at'          => WorkspaceDateTime::to_iso_gmt( $mod ),
						'propertyId'  => (int) $post->ID,
					);
				}
			}

			foreach ( $this->workflow->get_history( (int) $post->ID ) as $index => $entry ) {
				if ( ! is_array( $entry ) ) {
					continue;
				}
				$mapped = $this->map_history_entry( $entry, (int) $post->ID, $title, $index );
				if ( $mapped ) {
					$events[] = $mapped;
				}
			}
		}

		usort(
			$events,
			static function ( $a, $b ) {
				return strcmp( (string) ( $b['at'] ?? '' ), (string) ( $a['at'] ?? '' ) );
			}
		);

		$out = array();
		foreach ( array_slice( $events, 0, $limit ) as $row ) {
			unset( $row['at'] );
			$out[] = $row;
		}
		return $out;
	}

	/**
	 * @param array<string, mixed> $entry History entry.
	 * @param int                  $property_id Property ID.
	 * @param string               $title Property title.
	 * @param int                  $index History index.
	 * @return array<string, mixed>|null
	 */
	private function map_history_entry( array $entry, int $property_id, string $title, int $index ): ?array {
		$to     = isset( $entry['to'] ) ? (string) $entry['to'] : '';
		$action = isset( $entry['action'] ) ? (string) $entry['action'] : '';
		$at = isset( $entry['at'] ) ? (string) $entry['at'] : '';
		$ts = WorkspaceDateTime::parse_datetime( $at );
		if ( $ts <= 0 ) {
			return null;
		}

		$type  = 'updated';
		$label = 'Property updated';

		if ( 'pending' === $to || false !== stripos( $action, 'submit' ) ) {
			$type  = 'submitted';
			$label = 'Submitted for review';
		} elseif ( 'publish' === $to || false !== stripos( $action, 'publish' ) ) {
			$type  = 'published';
			$label = 'Property published';
		} elseif ( 'rejected' === $to || false !== stripos( $action, 'reject' ) ) {
			$type  = 'rejected';
			$label = 'Property rejected';
		} elseif ( 'draft' === $to ) {
			$type  = 'updated';
			$label = 'Returned to draft';
		}

		return array(
			'id'          => 'hist-' . $property_id . '-' . $index . '-' . $ts,
			'type'        => $type,
			'title'       => $label,
			'description' => $title,
			'time'        => WorkspaceDateTime::relative_ago( $ts ),
			'at'          => WorkspaceDateTime::to_iso_gmt( $ts ),
			'propertyId'  => $property_id,
		);
	}
}
