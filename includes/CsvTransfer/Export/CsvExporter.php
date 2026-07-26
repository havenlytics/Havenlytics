<?php
/**
 * Writes property rows to a CSV file in batches.
 *
 * @package HvnlyNab\CsvTransfer\Export
 * @since   3.7.0
 */

namespace HvnlyNab\CsvTransfer\Export;

use HvnlyNab\Agent\AgentConstants;
use HvnlyNab\CsvTransfer\Import\DuplicateMatcher;
use HvnlyNab\CsvTransfer\Import\RemoteMediaFetcher;
use HvnlyNab\CsvTransfer\Import\RowImporter;
use HvnlyNab\CsvTransfer\Mapping\FieldCatalog;
use HvnlyNab\CsvTransfer\Mapping\SchemaTargets;
use HvnlyNab\CsvTransfer\Support\CsvSafeValue;

defined( 'ABSPATH' ) || exit;

/**
 * CsvExporter — batched CSV writer for hvnly_property posts.
 *
 * @since 3.7.0
 */
final class CsvExporter {

	/** @var array<string, string> CSV field id => taxonomy slug. */
	private const TAXONOMY_MAP = array(
		'department'      => 'hvnly_prop_depts',
		'property_type'   => 'hvnly_prop_types',
		'property_status' => 'hvnly_prop_status',
		'features'        => 'hvnly_prop_features',
		'location'        => 'hvnly_prop_locations',
		'tags'            => 'hvnly_prop_tags',
		'badges'          => 'hvnly_prop_badges',
		'categories'      => 'hvnly_prop_categories',
	);

	/**
	 * Total properties matching export filters.
	 *
	 * @param array<string, mixed> $filters Filters (statuses).
	 * @return int
	 */
	public static function count( array $filters ): int {
		$query = new \WP_Query( self::query_args( $filters, 0, 1, true ) );
		return (int) $query->found_posts;
	}

	/**
	 * Write one batch of rows to the CSV file.
	 *
	 * @param string              $path Destination file path.
	 * @param array<int, string>  $columns Field ids to export, in order.
	 * @param array<string, mixed> $filters Filters (statuses).
	 * @param int                 $offset Zero-based row offset.
	 * @param int                 $limit Max rows this batch.
	 * @param bool                $write_header Whether to (re)write the file with a header row.
	 * @return array{written:int, done:bool}
	 */
	public static function write_batch( string $path, array $columns, array $filters, int $offset, int $limit, bool $write_header ): array {
		$query    = new \WP_Query( self::query_args( $filters, $offset, $limit, false ) );
		$post_ids = $query->posts;

		$handle = fopen( $path, $write_header ? 'w' : 'a' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		if ( ! $handle ) {
			return array( 'written' => 0, 'done' => true );
		}

		if ( $write_header ) {
			$labels = array();
			foreach ( FieldCatalog::get_fields() as $field ) {
				$labels[ $field['id'] ] = $field['label'];
			}
			$header = array_map(
				static function ( $column ) use ( $labels ) {
					return CsvSafeValue::sanitize( $labels[ $column ] ?? $column );
				},
				$columns
			);
			fputcsv( $handle, $header );
		}

		foreach ( $post_ids as $post_id ) {
			$post_id = absint( $post_id );
			$row     = array();
			foreach ( $columns as $column ) {
				$row[] = CsvSafeValue::sanitize( self::field_value( $post_id, $column ) );
			}
			fputcsv( $handle, $row );
		}

		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose

		return array(
			'written' => count( $post_ids ),
			'done'    => count( $post_ids ) < $limit,
		);
	}

	/**
	 * @param array<string, mixed> $filters Filters.
	 * @param int                   $offset Offset.
	 * @param int                   $limit Limit.
	 * @param bool                  $count_only Whether this is a count-only query.
	 * @return array<string, mixed>
	 */
	private static function query_args( array $filters, int $offset, int $limit, bool $count_only ): array {
		$statuses = isset( $filters['statuses'] ) && is_array( $filters['statuses'] ) && ! empty( $filters['statuses'] )
			? array_map( 'sanitize_key', $filters['statuses'] )
			: array( 'publish', 'draft', 'pending', 'private', 'expired' );

		return array(
			'post_type'              => AgentConstants::PROPERTY_POST_TYPE,
			'post_status'            => $statuses,
			'posts_per_page'         => $count_only ? 1 : $limit,
			'offset'                 => $count_only ? 0 : $offset,
			'orderby'                => 'ID',
			'order'                  => 'ASC',
			'fields'                 => 'ids',
			'no_found_rows'          => ! $count_only,
			'update_post_meta_cache' => ! $count_only,
			'update_post_term_cache' => ! $count_only,
		);
	}

	/**
	 * @param int    $post_id Post ID.
	 * @param string $column Field id.
	 * @return string
	 */
	private static function field_value( int $post_id, string $column ): string {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return '';
		}

		switch ( $column ) {
			case 'title':
				return (string) $post->post_title;
			case 'slug':
				return (string) $post->post_name;
			case 'content':
				return (string) $post->post_content;
			case 'excerpt':
				return (string) $post->post_excerpt;
			case 'status':
				return (string) $post->post_status;
			case 'author':
				$user = get_user_by( 'id', (int) $post->post_author );
				return $user ? (string) $user->user_login : '';
			case 'post_date':
				return (string) $post->post_date;
			case 'menu_order':
				return (string) $post->menu_order;
			case 'featured':
				return ( '1' === (string) get_post_meta( $post_id, '_hvnly_property_featured', true ) ) ? '1' : '0';
			case 'sticky':
				return is_sticky( $post_id ) ? '1' : '0';
			case 'mls':
				$mls = (string) get_post_meta( $post_id, DuplicateMatcher::META_MLS, true );
				return '' !== $mls ? $mls : (string) get_post_meta( $post_id, '_hvnly_property_mls_number', true );
			case 'reference':
				$ref = (string) get_post_meta( $post_id, DuplicateMatcher::META_REFERENCE, true );
				return '' !== $ref ? $ref : (string) get_post_meta( $post_id, '_hvnly_property_reference_number', true );
			case 'featured_image':
				$thumb_id = get_post_thumbnail_id( $post_id );
				return $thumb_id ? (string) wp_get_attachment_url( $thumb_id ) : '';
			case 'gallery':
				return self::gallery_urls( $post_id );
			case 'gallery_title':
			case 'video_title':
				return self::first_meta( $post_id, SchemaTargets::resolve( $column )['keys'] );
			case 'video_thumbnail':
				return self::video_thumbnail_url( $post_id );
			case 'documents':
				return self::document_urls( $post_id );
			case 'video_url':
				return self::first_meta( $post_id, SchemaTargets::resolve( 'video_url' )['keys'] );
			case 'address':
			case 'latitude':
			case 'longitude':
				return self::first_meta( $post_id, SchemaTargets::resolve( $column )['keys'] );
			case 'agent_email':
				return self::primary_agent_email( $post_id );
			case 'amenities':
				$amenities = get_post_meta( $post_id, RowImporter::META_AMENITIES, true );
				return is_array( $amenities ) ? implode( '|', $amenities ) : (string) $amenities;
			case 'features':
			case 'property_type':
			case 'property_status':
			case 'location':
			case 'department':
			case 'tags':
			case 'badges':
			case 'categories':
				return self::term_names( $post_id, self::TAXONOMY_MAP[ $column ] );
			default:
				$resolved = SchemaTargets::resolve( $column );
				if ( ! empty( $resolved['keys'] ) ) {
					return self::first_meta( $post_id, $resolved['keys'] );
				}
				$value = get_post_meta( $post_id, $column, true );
				if ( is_array( $value ) ) {
					return wp_json_encode( $value ) ?: '';
				}
				return (string) $value;
		}
	}

	/**
	 * @param int                $post_id Post ID.
	 * @param array<int, string> $keys Meta keys to try.
	 * @return string
	 */
	private static function first_meta( int $post_id, array $keys ): string {
		foreach ( $keys as $key ) {
			$val = get_post_meta( $post_id, $key, true );
			if ( is_array( $val ) ) {
				$json = wp_json_encode( $val );
				if ( $json && '[]' !== $json && 'null' !== $json ) {
					return $json;
				}
				continue;
			}
			if ( '' !== (string) $val ) {
				return (string) $val;
			}
		}
		return '';
	}

	/**
	 * @param int $post_id Post ID.
	 * @return string
	 */
	private static function gallery_urls( int $post_id ): string {
		$raw = '';
		foreach ( array_filter( array( SchemaTargets::builder_key( 'gallery', 'images' ), SchemaTargets::legacy_key( 'gallery', 'images' ) ) ) as $key ) {
			$candidate = get_post_meta( $post_id, $key, true );
			if ( is_string( $candidate ) && '' !== $candidate ) {
				$raw = $candidate;
				break;
			}
			if ( is_array( $candidate ) && ! empty( $candidate ) ) {
				return self::attachment_urls( $candidate );
			}
		}
		if ( '' === $raw ) {
			return '';
		}
		$ids = array_filter( array_map( 'absint', explode( ',', $raw ) ) );
		return self::attachment_urls( $ids );
	}

	/**
	 * Export video thumbnail as a URL (attachment id → URL when needed).
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	private static function video_thumbnail_url( int $post_id ): string {
		$raw = self::first_meta( $post_id, SchemaTargets::resolve( 'video_thumbnail' )['keys'] );
		if ( '' === $raw ) {
			return '';
		}
		if ( is_numeric( $raw ) ) {
			$url = wp_get_attachment_url( absint( $raw ) );
			return $url ? (string) $url : $raw;
		}
		return $raw;
	}

	/**
	 * @param int $post_id Post ID.
	 * @return string
	 */
	private static function document_urls( int $post_id ): string {
		foreach ( array_filter( array( SchemaTargets::builder_key( 'property_docs', 'documents' ), SchemaTargets::legacy_key( 'property_docs', 'documents' ) ) ) as $key ) {
			$raw = get_post_meta( $post_id, $key, true );
			$docs = is_string( $raw ) ? json_decode( $raw, true ) : $raw;
			if ( ! is_array( $docs ) ) {
				continue;
			}
			$urls = array();
			foreach ( $docs as $doc ) {
				if ( is_array( $doc ) && ! empty( $doc['url'] ) ) {
					$urls[] = (string) $doc['url'];
				}
			}
			if ( ! empty( $urls ) ) {
				return implode( '|', $urls );
			}
		}
		// Legacy CSV-only attachment id list.
		return self::attachment_urls( get_post_meta( $post_id, RemoteMediaFetcher::META_DOCUMENTS, true ) );
	}

	/**
	 * @param mixed $attachment_ids Attachment ID list.
	 * @return string
	 */
	private static function attachment_urls( $attachment_ids ): string {
		if ( ! is_array( $attachment_ids ) || empty( $attachment_ids ) ) {
			return '';
		}
		$urls = array();
		foreach ( $attachment_ids as $attachment_id ) {
			$url = wp_get_attachment_url( absint( $attachment_id ) );
			if ( $url ) {
				$urls[] = $url;
			}
		}
		return implode( '|', $urls );
	}

	/**
	 * @param int $post_id Post ID.
	 * @return string
	 */
	private static function primary_agent_email( int $post_id ): string {
		$agent_ids = get_post_meta( $post_id, AgentConstants::META_PROPERTY_AGENTS, true );
		$agent_id  = is_array( $agent_ids ) && ! empty( $agent_ids ) ? absint( $agent_ids[0] ) : 0;
		if ( $agent_id <= 0 ) {
			return '';
		}
		return (string) get_post_meta( $agent_id, AgentConstants::META_EMAIL, true );
	}

	/**
	 * @param int    $post_id Post ID.
	 * @param string $taxonomy Taxonomy slug.
	 * @return string
	 */
	private static function term_names( int $post_id, string $taxonomy ): string {
		if ( ! taxonomy_exists( $taxonomy ) ) {
			return '';
		}
		$terms = wp_get_post_terms( $post_id, $taxonomy, array( 'fields' => 'names' ) );
		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return '';
		}
		return implode( '|', $terms );
	}

	/**
	 * Prevent instantiation.
	 */
	private function __construct() {}
}
