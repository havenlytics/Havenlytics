<?php
/**
 * Favorites business logic.
 *
 * @package HvnlyNab\Favorites
 * @since   3.4.0
 */

namespace HvnlyNab\Favorites;

use WP_Error;
use WP_Post;
use WP_Query;

defined( 'ABSPATH' ) || exit;

/**
 * Saved-property operations for the current user.
 *
 * Every public method takes an explicit `$user_id`, but callers are expected
 * to pass `get_current_user_id()` — the REST layer never accepts a user id
 * from the client, which removes IDOR from the threat model entirely.
 *
 * @since 3.4.0
 */
final class FavoritesService {

	/**
	 * Property post type.
	 *
	 * @var string
	 */
	public const POST_TYPE = 'hvnly_property';

	/**
	 * Default per-user favorite ceiling.
	 *
	 * @var int
	 */
	private const DEFAULT_MAX_PER_USER = 5000;

	/**
	 * Largest guest batch accepted by a single merge call.
	 *
	 * @var int
	 */
	private const MAX_MERGE_BATCH = 200;

	/**
	 * @var FavoritesRepository
	 */
	private $repository;

	/**
	 * @param FavoritesRepository|null $repository Repository.
	 */
	public function __construct( ?FavoritesRepository $repository = null ) {
		$this->repository = $repository ? $repository : new FavoritesRepository();
	}

	/**
	 * @return FavoritesRepository
	 */
	public function repository(): FavoritesRepository {
		return $this->repository;
	}

	/**
	 * Maximum favorites a single user may hold.
	 *
	 * @return int
	 */
	public function max_per_user(): int {
		/**
		 * Filter the per-user favorites ceiling.
		 *
		 * @since 3.4.0
		 *
		 * @param int $max Maximum saved properties per user.
		 */
		$max = (int) apply_filters( 'hvnly_favorites_max_per_user', self::DEFAULT_MAX_PER_USER );

		return $max > 0 ? $max : self::DEFAULT_MAX_PER_USER;
	}

	/**
	 * Whether a property id refers to a favoritable listing.
	 *
	 * @param int $property_id Property id.
	 * @return bool
	 */
	public function is_favoritable( int $property_id ): bool {
		if ( $property_id <= 0 ) {
			return false;
		}

		$post = get_post( $property_id );
		if ( ! $post instanceof WP_Post ) {
			return false;
		}

		if ( self::POST_TYPE !== $post->post_type ) {
			return false;
		}

		// Only publicly visible listings can be saved. Without this a user
		// could probe for draft/private post ids by favoriting them.
		return 'publish' === $post->post_status;
	}

	/**
	 * Add a property to the user's favorites.
	 *
	 * @param int $user_id     User id.
	 * @param int $property_id Property id.
	 * @return array{property_id:int,favorited:bool,total:int}|WP_Error
	 */
	public function add( int $user_id, int $property_id ) {
		if ( $user_id <= 0 ) {
			return new WP_Error(
				'hvnly_favorites_unauthorized',
				__( 'You must be signed in to save properties.', 'havenlytics' ),
				array( 'status' => 401 )
			);
		}

		if ( ! $this->is_favoritable( $property_id ) ) {
			return new WP_Error(
				'hvnly_favorites_invalid_property',
				__( 'That property could not be saved.', 'havenlytics' ),
				array( 'status' => 404 )
			);
		}

		if ( ! $this->repository->exists( $user_id, $property_id ) ) {
			$max = $this->max_per_user();
			if ( $this->repository->count( $user_id ) >= $max ) {
				return new WP_Error(
					'hvnly_favorites_limit_reached',
					sprintf(
						/* translators: %d: maximum number of saved properties. */
						__( 'You have reached the limit of %d saved properties.', 'havenlytics' ),
						$max
					),
					array( 'status' => 409 )
				);
			}

			$this->repository->add( $user_id, $property_id );

			/**
			 * Fires after a property is added to a user's favorites.
			 *
			 * @since 3.4.0
			 *
			 * @param int $property_id Property id.
			 * @param int $user_id     User id.
			 */
			do_action( 'hvnly_favorite_added', $property_id, $user_id );
		}

		return array(
			'property_id' => $property_id,
			'favorited'   => true,
			'total'       => $this->repository->count( $user_id ),
		);
	}

	/**
	 * Remove a property from the user's favorites.
	 *
	 * @param int $user_id     User id.
	 * @param int $property_id Property id.
	 * @return array{property_id:int,favorited:bool,total:int}|WP_Error
	 */
	public function remove( int $user_id, int $property_id ) {
		if ( $user_id <= 0 ) {
			return new WP_Error(
				'hvnly_favorites_unauthorized',
				__( 'You must be signed in to manage saved properties.', 'havenlytics' ),
				array( 'status' => 401 )
			);
		}

		if ( $property_id <= 0 ) {
			return new WP_Error(
				'hvnly_favorites_invalid_property',
				__( 'That property could not be removed.', 'havenlytics' ),
				array( 'status' => 400 )
			);
		}

		// Deliberately not gated on is_favoritable(): a listing that was
		// unpublished after being saved must still be removable.
		$removed = $this->repository->remove( $user_id, $property_id );

		if ( $removed ) {
			/**
			 * Fires after a property is removed from a user's favorites.
			 *
			 * @since 3.4.0
			 *
			 * @param int $property_id Property id.
			 * @param int $user_id     User id.
			 */
			do_action( 'hvnly_favorite_removed', $property_id, $user_id );
		}

		return array(
			'property_id' => $property_id,
			'favorited'   => false,
			'total'       => $this->repository->count( $user_id ),
		);
	}

	/**
	 * Favorited property ids for a user.
	 *
	 * @param int $user_id User id.
	 * @return int[]
	 */
	public function get_ids( int $user_id ): array {
		return $this->repository->get_ids( $user_id );
	}

	/**
	 * Merge a guest's locally-held favorites into their account.
	 *
	 * Called once immediately after login. Unknown, unpublished and duplicate
	 * ids are dropped silently — a stale browser list must never fail the
	 * merge and strand the whole batch.
	 *
	 * @param int   $user_id      User id.
	 * @param int[] $property_ids Candidate property ids from the client.
	 * @return array{merged:int,skipped:int,total:int}|WP_Error
	 */
	public function merge( int $user_id, array $property_ids ) {
		if ( $user_id <= 0 ) {
			return new WP_Error(
				'hvnly_favorites_unauthorized',
				__( 'You must be signed in to merge saved properties.', 'havenlytics' ),
				array( 'status' => 401 )
			);
		}

		$candidates = array();
		foreach ( $property_ids as $raw ) {
			$id = absint( $raw );
			if ( $id > 0 ) {
				$candidates[ $id ] = $id;
			}
		}
		$candidates = array_values( $candidates );

		$requested = count( $candidates );
		if ( 0 === $requested ) {
			return array(
				'merged'  => 0,
				'skipped' => 0,
				'total'   => $this->repository->count( $user_id ),
			);
		}

		$candidates = array_slice( $candidates, 0, self::MAX_MERGE_BATCH );

		// One query validates the whole batch — never get_post() in a loop.
		$valid = $this->filter_published( $candidates );

		// Respect the ceiling; merge as many as will fit rather than failing.
		$existing = $this->repository->count( $user_id );
		$room     = max( 0, $this->max_per_user() - $existing );
		if ( count( $valid ) > $room ) {
			$valid = array_slice( $valid, 0, $room );
		}

		$merged = 0;
		if ( ! empty( $valid ) ) {
			$merged = $this->repository->add_many( $user_id, $valid );

			/**
			 * Fires after guest favorites are merged into an account.
			 *
			 * @since 3.4.0
			 *
			 * @param int[] $valid   Property ids merged.
			 * @param int   $user_id User id.
			 */
			do_action( 'hvnly_favorites_merged', $valid, $user_id );
		}

		return array(
			'merged'  => $merged,
			'skipped' => max( 0, $requested - $merged ),
			'total'   => $this->repository->count( $user_id ),
		);
	}

	/**
	 * Reduce a set of ids to those that are published properties.
	 *
	 * @param int[] $ids Candidate ids.
	 * @return int[]
	 */
	public function filter_published( array $ids ): array {
		global $wpdb;

		$ids = array_values( array_filter( array_map( 'absint', $ids ) ) );
		if ( empty( $ids ) ) {
			return array();
		}

		$placeholders = implode( ', ', array_fill( 0, count( $ids ), '%d' ) );
		$params       = array_merge( array( self::POST_TYPE ), $ids );

		$sql = "SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_status = 'publish' AND ID IN ({$placeholders})";

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$rows = $wpdb->get_col( $wpdb->prepare( $sql, $params ) );

		return array_map( 'intval', (array) $rows );
	}

	/**
	 * Paginated, sorted list of the user's saved properties.
	 *
	 * Cost is fixed per page: one COUNT, one id query, one WP_Query that
	 * primes post/meta/term caches for the page in bulk.
	 *
	 * @param int                  $user_id User id.
	 * @param array<string, mixed> $args    page, per_page, orderby, order.
	 * @return array<string, mixed>|WP_Error
	 */
	public function list_favorites( int $user_id, array $args = array() ) {
		if ( $user_id <= 0 ) {
			return new WP_Error(
				'hvnly_favorites_unauthorized',
				__( 'You must be signed in to view saved properties.', 'havenlytics' ),
				array( 'status' => 401 )
			);
		}

		$page     = max( 1, (int) ( $args['page'] ?? 1 ) );
		$per_page = (int) ( $args['per_page'] ?? 12 );
		$per_page = max( 1, min( 100, $per_page ) );

		$page_data = $this->repository->get_page(
			$user_id,
			array(
				'page'     => $page,
				'per_page' => $per_page,
				'orderby'  => (string) ( $args['orderby'] ?? 'date_added' ),
				'order'    => (string) ( $args['order'] ?? 'DESC' ),
			)
		);

		$total       = (int) $page_data['total'];
		$total_pages = $per_page > 0 ? (int) ceil( $total / $per_page ) : 0;
		$ids         = $page_data['ids'];
		$saved_at    = isset( $page_data['saved_at'] ) && is_array( $page_data['saved_at'] )
			? $page_data['saved_at']
			: array();

		$items = empty( $ids ) ? array() : $this->hydrate( $ids, $saved_at );

		return array(
			'items'      => $items,
			'page'       => $page,
			'perPage'    => $per_page,
			'total'      => $total,
			'totalPages' => $total_pages,
		);
	}

	/**
	 * Turn a page of ids into display payloads.
	 *
	 * @param int[]              $ids      Property ids in display order.
	 * @param array<int, string> $saved_at GMT datetime each favorite was created, keyed by property id.
	 * @return array<int, array<string, mixed>>
	 */
	private function hydrate( array $ids, array $saved_at = array() ): array {
		$query = new WP_Query(
			array(
				'post_type'              => self::POST_TYPE,
				'post_status'            => 'publish',
				'post__in'               => $ids,
				'orderby'                => 'post__in',
				'posts_per_page'         => count( $ids ),
				'ignore_sticky_posts'    => true,
				// The id query already counted; skip SQL_CALC_FOUND_ROWS.
				'no_found_rows'          => true,
				// Prime both caches in one pass so the loop below and any
				// thumbnail lookup are served from memory.
				'update_post_meta_cache' => true,
				'update_post_term_cache' => true,
			)
		);

		$items = array();
		foreach ( $query->posts as $post ) {
			if ( ! $post instanceof WP_Post ) {
				continue;
			}
			$items[] = $this->format_item( $post, (string) ( $saved_at[ $post->ID ] ?? '' ) );
		}

		wp_reset_postdata();

		return $items;
	}

	/**
	 * Shape a single property for the Saved Properties list.
	 *
	 * @param WP_Post $post         Property post.
	 * @param string  $saved_at_gmt GMT datetime the favorite was created ('' when unknown).
	 * @return array<string, mixed>
	 */
	private function format_item( WP_Post $post, string $saved_at_gmt = '' ): array {
		$thumbnail_id = (int) get_post_thumbnail_id( $post->ID );
		$thumbnail    = '';
		if ( $thumbnail_id > 0 ) {
			$src = wp_get_attachment_image_src( $thumbnail_id, 'medium' );
			if ( is_array( $src ) && ! empty( $src[0] ) ) {
				$thumbnail = esc_url_raw( (string) $src[0] );
			}
		}

		$price_raw = get_post_meta( $post->ID, '_hvnly_property_price', true );
		$price     = function_exists( 'hvnly_get_property_price' )
			? (string) hvnly_get_property_price( $post->ID )
			: (string) ( is_scalar( $price_raw ) ? $price_raw : '' );

		// Listing status (Sale / Rent / Sold…) — same hvnly_prop_status read
		// as the archive card badge (templates/archive/fields/status.php).
		// Term cache is primed by hydrate(), so this is a memory read.
		$listing_status = null;
		$status_terms   = get_the_terms( $post->ID, 'hvnly_prop_status' );
		if ( is_array( $status_terms ) && isset( $status_terms[0] ) && $status_terms[0] instanceof \WP_Term ) {
			$listing_status = array(
				'name' => $status_terms[0]->name,
				'slug' => $status_terms[0]->slug,
			);
		}

		// Assigned agent through the one existing resolver (CPT assignment →
		// legacy user meta → post author). Avatar comes from AvatarService.
		$agent         = null;
		$agent_profile = function_exists( 'hvnly_get_property_agent' )
			? hvnly_get_property_agent( $post->ID )
			: array();
		if ( is_array( $agent_profile ) && ! empty( $agent_profile['name'] ) ) {
			$agent = array(
				'id'         => (int) ( $agent_profile['id'] ?? 0 ),
				'name'       => (string) $agent_profile['name'],
				'avatar'     => esc_url_raw( (string) ( $agent_profile['avatar'] ?? '' ) ),
				'profileUrl' => esc_url_raw( (string) ( $agent_profile['profile_url'] ?? '' ) ),
				'position'   => (string) ( $agent_profile['position'] ?? '' ),
			);
		}

		// Favorite creation date, shipped both machine-readable and formatted
		// with the site's date_format ("Saved on July 22, 2026").
		$saved_iso     = '';
		$saved_display = '';
		if ( '' !== $saved_at_gmt ) {
			$saved_iso   = mysql_to_rfc3339( $saved_at_gmt );
			$saved_local = get_date_from_gmt( $saved_at_gmt, 'Y-m-d H:i:s' );
			$timestamp   = strtotime( $saved_local );
			if ( false !== $timestamp ) {
				$saved_display = date_i18n( (string) get_option( 'date_format', 'F j, Y' ), $timestamp );
			}
		}

		$item = array(
			'id'             => (int) $post->ID,
			'title'          => get_the_title( $post ),
			'permalink'      => esc_url_raw( (string) get_permalink( $post ) ),
			'thumbnail'      => $thumbnail,
			'price'          => $price,
			'datePublished'  => mysql2date( 'c', $post->post_date_gmt ?: $post->post_date, false ),
			'status'         => $post->post_status,
			'listingStatus'  => $listing_status,
			'agent'          => $agent,
			'savedAt'        => $saved_iso,
			'savedAtDisplay' => $saved_display,
		);

		/**
		 * Filter a saved-property list item before it reaches the SPA.
		 *
		 * @since 3.4.0
		 *
		 * @param array<string, mixed> $item Item payload.
		 * @param WP_Post              $post Property post.
		 */
		return (array) apply_filters( 'hvnly_favorites_list_item', $item, $post );
	}
}
