<?php
/**
 * Exports property CPT entities with portable meta and relationships.
 *
 * @package HvnlyNab\ImportExport\Export
 * @since   3.6.0
 */

namespace HvnlyNab\ImportExport\Export;

use HvnlyNab\Agent\AgentConstants;
use HvnlyNab\Core\GroupFieldIdentity;
use HvnlyNab\ImportExport\Support\PortableFieldEncoder;
use HvnlyNab\Workspace\Api\PropertyBuilderSchemaService;
use HvnlyNab\Workspace\Api\PropertyFormMapper;

defined( 'ABSPATH' ) || exit;

/**
 * Property exporter.
 *
 * @since 3.6.0
 */
final class PropertiesExporter {

	/**
	 * @param PortableFieldEncoder $encoder Encoder (shared media catalog).
	 * @param array<string, mixed> $options Export options.
	 * @return array<int, array<string, mixed>>
	 */
	public static function export( PortableFieldEncoder $encoder, array $options = array() ): array {
		$statuses = isset( $options['statuses'] ) && is_array( $options['statuses'] )
			? array_map( 'strval', $options['statuses'] )
			: array( 'publish', 'draft', 'pending', 'private', 'expired' );

		$statuses = array_values( array_filter( $statuses ) );
		if ( empty( $statuses ) ) {
			$statuses = array( 'publish' );
		}

		$args = array(
			'post_type'              => AgentConstants::PROPERTY_POST_TYPE,
			'post_status'            => $statuses,
			'posts_per_page'         => -1,
			'orderby'                => 'ID',
			'order'                  => 'ASC',
			'no_found_rows'          => true,
			'update_post_meta_cache' => true,
			'update_post_term_cache' => true,
		);

		$offset = isset( $options['offset'] ) ? max( 0, (int) $options['offset'] ) : 0;
		$limit  = isset( $options['limit'] ) ? max( 0, (int) $options['limit'] ) : 0;
		if ( $limit > 0 ) {
			$args['posts_per_page'] = $limit;
			$args['offset']         = $offset;
			$args['no_found_rows']  = false;
		}

		if ( ! empty( $options['date_from'] ) || ! empty( $options['date_to'] ) ) {
			$date_query = array( 'inclusive' => true );
			if ( ! empty( $options['date_from'] ) ) {
				$date_query['after'] = (string) $options['date_from'];
			}
			if ( ! empty( $options['date_to'] ) ) {
				$date_query['before'] = (string) $options['date_to'];
			}
			$args['date_query'] = array( $date_query );
		}

		$query    = new \WP_Query( $args );
		$sections = class_exists( PropertyBuilderSchemaService::class )
			? PropertyBuilderSchemaService::raw_sections()
			: array();
		if ( ! is_array( $sections ) ) {
			$sections = array();
		}

		$include_workflow = ! empty( $options['include_workflow_status'] );
		$out              = array();

		foreach ( $query->posts as $post ) {
			if ( ! $post instanceof \WP_Post ) {
				continue;
			}

			$fields    = $encoder->encode_property_fields( (int) $post->ID, $sections );
			$field_map = class_exists( GroupFieldIdentity::class )
				? GroupFieldIdentity::get_field_map( (int) $post->ID )
				: array();

			$terms = array();
			foreach ( TermsExporter::TAXONOMIES as $taxonomy ) {
				if ( ! taxonomy_exists( $taxonomy ) ) {
					continue;
				}
				$slugs = wp_get_post_terms( $post->ID, $taxonomy, array( 'fields' => 'slugs' ) );
				if ( is_wp_error( $slugs ) || empty( $slugs ) ) {
					continue;
				}
				sort( $slugs, SORT_STRING );
				$terms[ $taxonomy ] = array_values( array_map( 'strval', $slugs ) );
			}

			$agent_refs = self::portable_property_agents( (int) $post->ID );

			$author_email = '';
			$author       = get_userdata( (int) $post->post_author );
			if ( $author ) {
				$author_email = (string) $author->user_email;
			}

			$thumb    = get_post_thumbnail_id( $post->ID );
			$featured = $thumb ? $encoder->attachment_stub( (int) $thumb ) : null;

			$row = array(
				'slug'              => (string) $post->post_name,
				'title'             => (string) $post->post_title,
				'content'           => (string) $post->post_content,
				'excerpt'           => (string) $post->post_excerpt,
				'status'            => (string) $post->post_status,
				'post_date_gmt'     => (string) $post->post_date_gmt,
				'post_modified_gmt' => (string) $post->post_modified_gmt,
				'unique_property_id' => (string) get_post_meta( $post->ID, '_hvnly_unique_property_id', true ),
				'mls_number'        => (string) get_post_meta( $post->ID, '_hvnly_property_mls_number', true ),
				'reference_number'  => (string) get_post_meta( $post->ID, '_hvnly_property_reference_number', true ),
				'featured'          => ( '1' === (string) get_post_meta( $post->ID, '_hvnly_property_featured', true ) ),
				'author_email'      => $author_email,
				'terms'             => $terms,
				'agents'            => $agent_refs,
				'field_map'         => $field_map,
				'fields'            => $fields,
				'featured_image'    => $featured,
			);

			if ( $include_workflow && class_exists( PropertyFormMapper::class ) ) {
				$row['ws_listing_status'] = (string) get_post_meta(
					$post->ID,
					PropertyFormMapper::META_LISTING_STATUS,
					true
				);
			}

			$out[] = $row;
		}

		usort(
			$out,
			static function ( $a, $b ) {
				$c = strcmp( (string) $a['slug'], (string) $b['slug'] );
				if ( 0 !== $c ) {
					return $c;
				}
				return strcmp( (string) $a['unique_property_id'], (string) $b['unique_property_id'] );
			}
		);

		return $out;
	}

	/**
	 * @param int $property_id Property ID.
	 * @return array<int, array<string, string>>
	 */
	private static function portable_property_agents( int $property_id ): array {
		$raw = get_post_meta( $property_id, AgentConstants::META_PROPERTY_AGENTS, true );
		$ids = array();
		if ( is_array( $raw ) ) {
			$ids = array_map( 'absint', $raw );
		}

		$out = array();
		foreach ( $ids as $id ) {
			if ( $id <= 0 ) {
				continue;
			}
			$post = get_post( $id );
			if ( ! $post || AgentConstants::POST_TYPE !== $post->post_type ) {
				continue;
			}
			$out[] = array(
				'email' => (string) get_post_meta( $id, AgentConstants::META_EMAIL, true ),
				'slug'  => (string) $post->post_name,
			);
		}

		return $out;
	}

	/**
	 * Prevent instantiation.
	 */
	private function __construct() {}
}
