<?php
/**
 * Editor-only REST enrichment for Property Inquiry pickers.
 *
 * Adds rich `hvnly_picker` fields to the existing wp/v2 property/agent
 * endpoints and expands search to address / MLS / reference meta when
 * available. Does not touch Inquiry AJAX, validation, emails, or leads.
 *
 * @package     Havenlytics
 * @subpackage  Integrations\Blocks
 * @since       3.5.0
 */

namespace HvnlyNab\Integrations\Blocks;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @since 3.5.0
 */
final class InquiryBlockEditorSupport {

	/**
	 * Meta keys searched alongside title for property picker queries.
	 *
	 * @var array<int, string>
	 */
	private const PROPERTY_SEARCH_META = array(
		'_hvnly_property_address_line_1',
		'_hvnly_property_address_line_2',
		'_hvnly_property_street',
		'_hvnly_property_mls_number',
		'_hvnly_property_reference_number',
		'_hvnly_unique_property_id',
	);

	/**
	 * Register additive hooks (idempotent).
	 *
	 * @return void
	 */
	public static function register(): void {
		static $done = false;
		if ( $done ) {
			return;
		}
		$done = true;

		add_action( 'rest_api_init', array( self::class, 'register_rest_fields' ) );
		add_filter( 'rest_hvnly_property_query', array( self::class, 'filter_property_query' ), 10, 2 );
		add_filter( 'posts_search', array( self::class, 'expand_property_posts_search' ), 10, 2 );
	}

	/**
	 * Register picker payload fields on the existing CPT REST controllers.
	 *
	 * @return void
	 */
	public static function register_rest_fields(): void {
		register_rest_field(
			'hvnly_property',
			'hvnly_picker',
			array(
				'get_callback' => array( self::class, 'get_property_picker_field' ),
				'schema'       => array(
					'description' => 'Rich property picker payload for the block editor.',
					'type'        => 'object',
					'context'     => array( 'view', 'edit', 'embed' ),
					'readonly'    => true,
				),
			)
		);

		register_rest_field(
			'hvnly_agent',
			'hvnly_picker',
			array(
				'get_callback' => array( self::class, 'get_agent_picker_field' ),
				'schema'       => array(
					'description' => 'Rich agent picker payload for the block editor.',
					'type'        => 'object',
					'context'     => array( 'view', 'edit', 'embed' ),
					'readonly'    => true,
				),
			)
		);
	}

	/**
	 * @param array<string, mixed> $object REST object.
	 * @return array<string, mixed>
	 */
	public static function get_property_picker_field( array $object ): array {
		$id = isset( $object['id'] ) ? (int) $object['id'] : 0;
		if ( $id <= 0 ) {
			return array();
		}

		$card = BlockCardData::get( $id );

		$mls = (string) get_post_meta( $id, '_hvnly_property_mls_number', true );
		if ( '' === $mls ) {
			$mls = (string) get_post_meta( $id, '_hvnly_property_reference_number', true );
		}
		if ( '' === $mls ) {
			$mls = (string) get_post_meta( $id, '_hvnly_unique_property_id', true );
		}

		$location = (string) ( $card['address'] ?? '' );
		if ( '' === $location ) {
			$terms = wp_get_post_terms( $id, 'hvnly_prop_locations', array( 'fields' => 'names' ) );
			if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
				$location = (string) $terms[0];
			}
		}

		return array(
			'title'     => (string) ( $card['title'] ?? get_the_title( $id ) ),
			'thumbnail' => (string) ( $card['thumbnail'] ?? '' ),
			'price'     => (string) ( $card['price'] ?? '' ),
			'status'    => (string) ( $card['status'] ?? '' ),
			'location'  => $location,
			'mls'       => $mls,
		);
	}

	/**
	 * @param array<string, mixed> $object REST object.
	 * @return array<string, mixed>
	 */
	public static function get_agent_picker_field( array $object ): array {
		$id = isset( $object['id'] ) ? (int) $object['id'] : 0;
		if ( $id <= 0 ) {
			return array();
		}

		$agent  = function_exists( 'hvnly_get_agent' ) ? hvnly_get_agent( $id ) : array();
		$name   = ! empty( $agent['name'] ) ? (string) $agent['name'] : (string) get_the_title( $id );
		$avatar = ! empty( $agent['avatar'] ) ? (string) $agent['avatar'] : '';
		$email  = ! empty( $agent['email'] ) ? (string) $agent['email'] : '';
		$agency = '';
		$count  = 0;

		if ( ! empty( $agent['agency']['name'] ) ) {
			$agency = (string) $agent['agency']['name'];
		} elseif ( ! empty( $agent['company'] ) ) {
			$agency = (string) $agent['company'];
		}

		if ( function_exists( 'hvnly_get_agent_property_count' ) ) {
			$count = (int) hvnly_get_agent_property_count( $id );
		}

		if ( '' === $avatar && function_exists( 'hvnly_get_agent_avatar_url' ) ) {
			$avatar = (string) hvnly_get_agent_avatar_url( $id, 0, 96, 'thumbnail' );
		}

		return array(
			'name'           => $name,
			'avatar'         => $avatar,
			'agency'         => $agency,
			'property_count' => $count,
			'email'          => $email,
		);
	}

	/**
	 * Mark property REST queries so posts_search can OR meta matches.
	 *
	 * @param array<string, mixed> $args    WP_Query args.
	 * @param \WP_REST_Request     $request Request.
	 * @return array<string, mixed>
	 */
	public static function filter_property_query( array $args, $request ): array {
		$search = '';
		if ( $request instanceof \WP_REST_Request ) {
			$search = trim( (string) $request->get_param( 'search' ) );
		}

		if ( '' === $search ) {
			return $args;
		}

		$args['hvnly_block_picker_search'] = $search;

		return $args;
	}

	/**
	 * Expand core title/content search with address / MLS / reference meta.
	 *
	 * Uses EXISTS so we do not need JOIN / GROUP BY side-effects.
	 *
	 * @param string    $search Search SQL.
	 * @param \WP_Query $query  Query.
	 * @return string
	 */
	public static function expand_property_posts_search( $search, $query ) {
		if ( ! ( $query instanceof \WP_Query ) ) {
			return $search;
		}

		$term = $query->get( 'hvnly_block_picker_search' );
		if ( ! is_string( $term ) || '' === trim( $term ) ) {
			return $search;
		}

		global $wpdb;

		$like    = '%' . $wpdb->esc_like( trim( $term ) ) . '%';
		$meta_in = implode(
			',',
			array_map(
				static function ( $key ) use ( $wpdb ) {
					return $wpdb->prepare( '%s', $key );
				},
				self::PROPERTY_SEARCH_META
			)
		);
		$exists  = $wpdb->prepare(
			" EXISTS (
				SELECT 1 FROM {$wpdb->postmeta} hvnly_picker_pm
				WHERE hvnly_picker_pm.post_id = {$wpdb->posts}.ID
				AND hvnly_picker_pm.meta_key IN ( {$meta_in} )
				AND hvnly_picker_pm.meta_value LIKE %s
			) ",
			$like
		);

		$search = (string) $search;

		if ( '' === trim( $search ) ) {
			return " AND ( {$exists} ) ";
		}

		// Core search ends with "))" — OR the meta match into that group.
		$injected = preg_replace(
			'/\)\s*\)\s*$/',
			' OR ' . $exists . ') )',
			$search,
			1
		);

		return is_string( $injected ) && '' !== $injected ? $injected : $search;
	}
}
