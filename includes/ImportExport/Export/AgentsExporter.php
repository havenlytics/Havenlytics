<?php
/**
 * Exports agent CPT profiles.
 *
 * @package HvnlyNab\ImportExport\Export
 * @since   3.6.0
 */

namespace HvnlyNab\ImportExport\Export;

use HvnlyNab\Agent\AgentConstants;
use HvnlyNab\ImportExport\Support\ExportExclusions;
use HvnlyNab\ImportExport\Support\PortableFieldEncoder;

defined( 'ABSPATH' ) || exit;

/**
 * Agent exporter.
 *
 * @since 3.6.0
 */
final class AgentsExporter {

	/**
	 * @param PortableFieldEncoder $encoder Encoder.
	 * @return array<int, array<string, mixed>>
	 */
	public static function export( PortableFieldEncoder $encoder ): array {
		$query = new \WP_Query(
			array(
				'post_type'              => AgentConstants::POST_TYPE,
				'post_status'            => array( 'publish', 'draft', 'pending', 'private' ),
				'posts_per_page'         => -1,
				'orderby'                => 'name',
				'order'                  => 'ASC',
				'no_found_rows'          => true,
				'update_post_meta_cache' => true,
				'update_post_term_cache' => true,
			)
		);

		$out = array();
		foreach ( $query->posts as $post ) {
			if ( ! $post instanceof \WP_Post ) {
				continue;
			}

			$meta = array();
			foreach ( AgentConstants::profile_meta_keys() as $key ) {
				if ( in_array( $key, ExportExclusions::agent_meta_keys(), true ) ) {
					continue;
				}
				$val = get_post_meta( $post->ID, $key, true );
				if ( '' === $val || false === $val || null === $val ) {
					continue;
				}
				$short           = preg_replace( '/^_hvnly_agent_/', '', $key );
				$meta[ $short ] = $val;
			}

			$linked_id = (int) get_post_meta( $post->ID, AgentConstants::META_LINKED_USER_ID, true );
			$linked_email = '';
			if ( $linked_id > 0 ) {
				$user = get_userdata( $linked_id );
				if ( $user ) {
					$linked_email = (string) $user->user_email;
				}
			}

			$agency_slugs = wp_get_post_terms( $post->ID, AgentConstants::TAXONOMY_AGENCY, array( 'fields' => 'slugs' ) );
			if ( is_wp_error( $agency_slugs ) ) {
				$agency_slugs = array();
			}

			$thumb = get_post_thumbnail_id( $post->ID );
			$photo = $thumb ? $encoder->attachment_stub( (int) $thumb ) : null;

			$email = (string) get_post_meta( $post->ID, AgentConstants::META_EMAIL, true );
			if ( '' === $email && '' !== $linked_email ) {
				$email = $linked_email;
			}

			$portable_id = (string) get_post_meta( $post->ID, '_hvnly_agent_portable_id', true );
			if ( '' === $portable_id ) {
				if ( '' !== $email ) {
					$portable_id = 'agent:' . strtolower( $email );
				} else {
					$portable_id = 'agent:' . (string) $post->post_name;
				}
			}

			$out[] = array(
				'slug'              => (string) $post->post_name,
				'title'             => (string) $post->post_title,
				'content'           => (string) $post->post_content,
				'excerpt'           => (string) $post->post_excerpt,
				'status'            => (string) $post->post_status,
				'email'             => $email,
				'linked_user_email' => $linked_email,
				'portable_id'       => $portable_id,
				'agency_slugs'      => array_values( array_map( 'strval', (array) $agency_slugs ) ),
				'meta'              => $meta,
				'photo'             => $photo,
			);
		}

		usort(
			$out,
			static function ( $a, $b ) {
				$ae = (string) $a['email'];
				$be = (string) $b['email'];
				if ( '' !== $ae || '' !== $be ) {
					$c = strcmp( $ae, $be );
					if ( 0 !== $c ) {
						return $c;
					}
				}
				return strcmp( (string) $a['slug'], (string) $b['slug'] );
			}
		);

		return $out;
	}

	/**
	 * Prevent instantiation.
	 */
	private function __construct() {}
}
