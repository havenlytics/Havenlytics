<?php
/**
 * Downloads pending CSV media URLs into the media library.
 *
 * @package HvnlyNab\CsvTransfer\Import
 * @since   3.7.0
 */

namespace HvnlyNab\CsvTransfer\Import;

use HvnlyNab\Agent\AgentConstants;
use HvnlyNab\CsvTransfer\Mapping\SchemaTargets;
use HvnlyNab\CsvTransfer\Security\UrlGuard;
use WP_Error;
use WP_Query;

defined( 'ABSPATH' ) || exit;

/**
 * RemoteMediaFetcher — sideloads queued gallery/featured/document URLs.
 *
 * Writes gallery/document storage to Property Editor keys:
 * `{group_base_id}_images` / `{group_base_id}_documents` plus legacy dual-write.
 *
 * Pending queues are scoped to an import `job_id` so cancelled / abandoned
 * jobs cannot leak media work into a later import.
 *
 * @since 3.7.0
 */
final class RemoteMediaFetcher {

	/** @deprecated Use SchemaTargets::legacy_key( 'gallery', 'images' ). */
	public const META_GALLERY = '_hvnly_property_gallery_images';

	/** @deprecated Documents now write editor JSON keys, not this CSV-only list. */
	public const META_DOCUMENTS = '_hvnly_csv_documents';

	/** @var array<int, string> */
	private const IMAGE_EXTENSIONS = array( 'jpg', 'jpeg', 'png', 'gif', 'webp' );

	/** @var array<int, string> */
	private const DOCUMENT_EXTENSIONS = array( 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'txt' );

	/**
	 * Find property post ids that still have pending media queued for a job.
	 *
	 * @param int    $limit  Max ids to return.
	 * @param string $job_id Active import job id (required for processing).
	 * @return array<int, int>
	 */
	public static function find_pending_post_ids( int $limit = 20, string $job_id = '' ): array {
		$query = new WP_Query(
			array(
				'post_type'              => AgentConstants::PROPERTY_POST_TYPE,
				'post_status'            => array( 'publish', 'draft', 'pending', 'private', 'expired' ),
				'posts_per_page'         => max( 1, $limit * 3 ),
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'meta_key'               => RowImporter::META_PENDING_MEDIA, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			)
		);

		$out = array();
		foreach ( array_map( 'absint', $query->posts ) as $post_id ) {
			if ( count( $out ) >= $limit ) {
				break;
			}
			$pending = get_post_meta( $post_id, RowImporter::META_PENDING_MEDIA, true );
			if ( ! is_array( $pending ) || empty( $pending ) ) {
				continue;
			}

			$pending_job = isset( $pending['job_id'] ) ? (string) $pending['job_id'] : '';

			// Legacy queues without a job_id are discarded (never resume into a new import).
			if ( '' === $pending_job ) {
				delete_post_meta( $post_id, RowImporter::META_PENDING_MEDIA );
				continue;
			}

			if ( '' === $job_id || $pending_job !== $job_id ) {
				continue;
			}

			$out[] = $post_id;
		}

		return $out;
	}

	/**
	 * Remove pending media queues belonging to a specific import job.
	 *
	 * @param string $job_id Job id.
	 * @return int Number of posts cleared.
	 */
	public static function discard_job_queue( string $job_id ): int {
		$job_id = (string) $job_id;
		if ( '' === $job_id ) {
			return 0;
		}

		$query = new WP_Query(
			array(
				'post_type'              => AgentConstants::PROPERTY_POST_TYPE,
				'post_status'            => array( 'publish', 'draft', 'pending', 'private', 'expired' ),
				'posts_per_page'         => 200,
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'meta_key'               => RowImporter::META_PENDING_MEDIA, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			)
		);

		$cleared = 0;
		foreach ( array_map( 'absint', $query->posts ) as $post_id ) {
			$pending = get_post_meta( $post_id, RowImporter::META_PENDING_MEDIA, true );
			if ( ! is_array( $pending ) ) {
				continue;
			}
			$pending_job = isset( $pending['job_id'] ) ? (string) $pending['job_id'] : '';
			if ( $pending_job === $job_id || '' === $pending_job ) {
				delete_post_meta( $post_id, RowImporter::META_PENDING_MEDIA );
				++$cleared;
			}
		}

		return $cleared;
	}

	/**
	 * Process one property's pending media queue, bounded by $budget URLs.
	 *
	 * @param int    $post_id Post ID.
	 * @param int    $budget  Max URLs to sideload in this call.
	 * @param string $job_id  Active import job id.
	 * @return array{consumed:int, warnings:array<int,string>}
	 */
	public static function process_post( int $post_id, int $budget, string $job_id = '' ): array {
		$pending = get_post_meta( $post_id, RowImporter::META_PENDING_MEDIA, true );
		if ( ! is_array( $pending ) || empty( $pending ) || $budget <= 0 ) {
			return array(
				'consumed' => 0,
				'warnings' => array(),
			);
		}

		$pending_job = isset( $pending['job_id'] ) ? (string) $pending['job_id'] : '';
		if ( '' === $pending_job || ( '' !== $job_id && $pending_job !== $job_id ) ) {
			// Wrong job or legacy queue — do not process.
			if ( '' === $pending_job ) {
				delete_post_meta( $post_id, RowImporter::META_PENDING_MEDIA );
			}
			return array(
				'consumed' => 0,
				'warnings' => array(),
			);
		}

		self::require_media_includes();

		$consumed = 0;
		$warnings = array();

		if ( $budget > 0 && ! empty( $pending['featured_image'] ) ) {
			$attachment_id = self::sideload( (string) $pending['featured_image'], $post_id, 'image' );
			if ( is_wp_error( $attachment_id ) ) {
				$warnings[] = self::warning_message( $pending['featured_image'], $attachment_id );
			} else {
				set_post_thumbnail( $post_id, $attachment_id );
			}
			unset( $pending['featured_image'] );
			++$consumed;
			--$budget;
		}

		if ( $budget > 0 && ! empty( $pending['gallery'] ) && is_array( $pending['gallery'] ) ) {
			$gallery_ids    = self::read_gallery_ids( $post_id );
			$remaining_urls = array();
			foreach ( $pending['gallery'] as $url ) {
				if ( $budget <= 0 ) {
					$remaining_urls[] = $url;
					continue;
				}
				$attachment_id = self::sideload( (string) $url, $post_id, 'image' );
				if ( is_wp_error( $attachment_id ) ) {
					$warnings[] = self::warning_message( $url, $attachment_id );
				} else {
					$gallery_ids[] = $attachment_id;
				}
				++$consumed;
				--$budget;
			}
			self::write_gallery_ids( $post_id, array_values( array_unique( array_filter( array_map( 'absint', $gallery_ids ) ) ) ) );
			if ( ! empty( $remaining_urls ) ) {
				$pending['gallery'] = $remaining_urls;
			} else {
				unset( $pending['gallery'] );
			}
		}

		if ( $budget > 0 && ! empty( $pending['documents'] ) && is_array( $pending['documents'] ) ) {
			$documents      = self::read_documents( $post_id );
			$remaining_urls = array();
			foreach ( $pending['documents'] as $url ) {
				if ( $budget <= 0 ) {
					$remaining_urls[] = $url;
					continue;
				}
				$attachment_id = self::sideload( (string) $url, $post_id, 'document' );
				if ( is_wp_error( $attachment_id ) ) {
					// Fall back to storing the remote URL as a document entry when sideload fails.
					$remote = esc_url_raw( (string) $url );
					if ( '' !== $remote ) {
						$documents[] = self::document_entry( $remote, basename( (string) wp_parse_url( $remote, PHP_URL_PATH ) ) );
					}
					$warnings[] = self::warning_message( $url, $attachment_id );
				} else {
					$att_url = wp_get_attachment_url( $attachment_id );
					$label   = get_the_title( $attachment_id );
					if ( ! $att_url ) {
						$att_url = esc_url_raw( (string) $url );
					}
					$documents[] = self::document_entry( (string) $att_url, (string) $label );
				}
				++$consumed;
				--$budget;
			}
			self::write_documents( $post_id, $documents );
			if ( ! empty( $remaining_urls ) ) {
				$pending['documents'] = $remaining_urls;
			} else {
				unset( $pending['documents'] );
			}
		}

		// Preserve job_id while queue remains; drop meta when empty.
		$has_work = ! empty( $pending['featured_image'] )
			|| ( ! empty( $pending['gallery'] ) && is_array( $pending['gallery'] ) )
			|| ( ! empty( $pending['documents'] ) && is_array( $pending['documents'] ) );

		if ( ! $has_work ) {
			delete_post_meta( $post_id, RowImporter::META_PENDING_MEDIA );
		} else {
			$pending['job_id'] = $pending_job;
			update_post_meta( $post_id, RowImporter::META_PENDING_MEDIA, $pending );
		}

		return array(
			'consumed' => $consumed,
			'warnings' => $warnings,
		);
	}

	/**
	 * Request-scoped URL → attachment ID cache (avoids repeat lookups in a batch).
	 *
	 * @var array<string, int>
	 */
	private static $attachment_url_cache = array();

	/**
	 * @param string $url Source URL.
	 * @param int    $post_id Parent post ID.
	 * @param string $kind image|document.
	 * @return int|WP_Error Attachment ID.
	 */
	private static function sideload( string $url, int $post_id, string $kind ) {
		$url = esc_url_raw( trim( $url ) );
		if ( '' === $url ) {
			return new WP_Error( 'hvnly_csv_media_url', __( 'Invalid media URL.', 'havenlytics' ) );
		}

		// Same-site / library reuse: never re-download an attachment that already exists.
		$existing = self::find_attachment_by_url( $url );
		if ( $existing > 0 ) {
			return $existing;
		}

		$guard = UrlGuard::assert_public_http_url( $url );
		if ( is_wp_error( $guard ) ) {
			return $guard;
		}

		$tmp = self::download_to_temp( $url );
		if ( is_wp_error( $tmp ) ) {
			return $tmp;
		}

		$path     = (string) wp_parse_url( $url, PHP_URL_PATH );
		$filename = $path ? wp_basename( $path ) : '';
		if ( '' === $filename || false === strpos( $filename, '.' ) ) {
			$filename = 'image' === $kind
				? 'hvnly-csv-image-' . uniqid() . '.jpg'
				: 'hvnly-csv-file-' . uniqid() . '.pdf';
		}
		$filename = sanitize_file_name( $filename );
		$ext      = strtolower( (string) pathinfo( $filename, PATHINFO_EXTENSION ) );

		$allowed = 'image' === $kind ? self::IMAGE_EXTENSIONS : array_merge( self::DOCUMENT_EXTENSIONS, self::IMAGE_EXTENSIONS );
		if ( ! in_array( $ext, $allowed, true ) ) {
			@unlink( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.unlink_unlink
			return new WP_Error( 'hvnly_csv_media_mime', __( 'Unsupported file type was skipped.', 'havenlytics' ) );
		}

		$file_array = array(
			'name'     => $filename,
			'tmp_name' => $tmp,
		);

		$attachment_id = media_handle_sideload( $file_array, $post_id );
		if ( is_wp_error( $attachment_id ) ) {
			if ( file_exists( $tmp ) ) {
				@unlink( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.unlink_unlink
			}
			return $attachment_id;
		}

		return absint( $attachment_id );
	}

	/**
	 * Resolve an existing Media Library attachment for a URL without downloading.
	 *
	 * Prefer {@see attachment_url_to_postid()}, then uploads-relative `_wp_attached_file`
	 * lookup for same-site URLs (scheme / www mismatches).
	 *
	 * @param string $url Media URL from CSV.
	 * @return int Attachment ID or 0.
	 */
	private static function find_attachment_by_url( string $url ): int {
		$url = esc_url_raw( trim( $url ) );
		if ( '' === $url ) {
			return 0;
		}

		$cache_key = strtolower( $url );
		if ( isset( self::$attachment_url_cache[ $cache_key ] ) ) {
			return self::$attachment_url_cache[ $cache_key ];
		}

		$id = 0;
		foreach ( self::url_lookup_variants( $url ) as $candidate ) {
			if ( function_exists( 'attachment_url_to_postid' ) ) {
				$id = absint( attachment_url_to_postid( $candidate ) );
				if ( $id > 0 && 'attachment' === get_post_type( $id ) ) {
					break;
				}
				$id = 0;
			}

			$id = self::find_attachment_by_uploads_path( $candidate );
			if ( $id > 0 ) {
				break;
			}
		}

		self::$attachment_url_cache[ $cache_key ] = $id;
		return $id;
	}

	/**
	 * URL variants that commonly appear after CSV export on the same site.
	 *
	 * @param string $url Source URL.
	 * @return array<int, string>
	 */
	private static function url_lookup_variants( string $url ): array {
		$variants = array( $url );

		$parts = wp_parse_url( $url );
		if ( ! is_array( $parts ) || empty( $parts['host'] ) ) {
			return array_values( array_unique( $variants ) );
		}

		// Drop query / fragment.
		$path       = isset( $parts['path'] ) ? (string) $parts['path'] : '';
		$base       = ( isset( $parts['scheme'] ) ? $parts['scheme'] . '://' : 'https://' )
			. $parts['host']
			. ( isset( $parts['port'] ) ? ':' . $parts['port'] : '' )
			. $path;
		$variants[] = $base;

		if ( 0 === strpos( $base, 'http://' ) ) {
			$variants[] = 'https://' . substr( $base, 7 );
		} elseif ( 0 === strpos( $base, 'https://' ) ) {
			$variants[] = 'http://' . substr( $base, 8 );
		}

		return array_values( array_unique( array_filter( array_map( 'strval', $variants ) ) ) );
	}

	/**
	 * Match an uploads URL to `_wp_attached_file` (same-site reuse).
	 *
	 * @param string $url Absolute media URL.
	 * @return int Attachment ID or 0.
	 */
	private static function find_attachment_by_uploads_path( string $url ): int {
		$uploads = wp_upload_dir();
		if ( ! empty( $uploads['error'] ) || empty( $uploads['baseurl'] ) ) {
			return 0;
		}

		$baseurl    = untrailingslashit( (string) $uploads['baseurl'] );
		$candidates = array( $baseurl );
		if ( 0 === strpos( $baseurl, 'http://' ) ) {
			$candidates[] = 'https://' . substr( $baseurl, 7 );
		} elseif ( 0 === strpos( $baseurl, 'https://' ) ) {
			$candidates[] = 'http://' . substr( $baseurl, 8 );
		}

		$relative = '';
		foreach ( $candidates as $base ) {
			$base = untrailingslashit( $base );
			foreach ( array( $url, strtok( $url, '?#' ) ?: $url ) as $probe ) {
				$probe = (string) $probe;
				if ( 0 === stripos( $probe, $base . '/' ) ) {
					$relative = ltrim( substr( $probe, strlen( $base ) ), '/' );
					break 2;
				}
			}
		}

		if ( '' === $relative ) {
			return 0;
		}

		$relative = rawurldecode( $relative );
		$relative = ltrim( str_replace( '\\', '/', $relative ), '/' );

		global $wpdb;
		$attachment_id = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_wp_attached_file' AND meta_value = %s LIMIT 1",
				$relative
			)
		);

		if ( $attachment_id > 0 && 'attachment' === get_post_type( $attachment_id ) ) {
			return $attachment_id;
		}

		return 0;
	}

	/**
	 * Default max remote media download size (15 MiB).
	 *
	 * Filterable via {@see 'hvnly_csv_remote_media_max_bytes'}.
	 */
	public const DEFAULT_MAX_BYTES = 15728640;

	/**
	 * Maximum allowed bytes for a single remote media download.
	 *
	 * @return int
	 */
	public static function max_download_bytes(): int {
		$max = (int) apply_filters( 'hvnly_csv_remote_media_max_bytes', self::DEFAULT_MAX_BYTES );
		return max( 1024, $max );
	}

	/**
	 * Download a remote URL to a local temp file with size limits + SSRF checks.
	 *
	 * Streams to disk (avoids loading the full body into PHP memory), enforces a
	 * configurable byte cap, and deletes partial files on failure.
	 *
	 * @param string $url Public http(s) URL.
	 * @return string|WP_Error Temp file path.
	 */
	private static function download_to_temp( string $url ) {
		$max_bytes = self::max_download_bytes();

		// Best-effort Content-Length probe (many CDNs omit or block HEAD — non-fatal).
		$head = wp_safe_remote_head(
			$url,
			UrlGuard::safe_request_args(
				array(
					'timeout' => 10,
				)
			)
		);
		if ( ! is_wp_error( $head ) ) {
			$length = wp_remote_retrieve_header( $head, 'content-length' );
			if ( is_numeric( $length ) && (int) $length > $max_bytes ) {
				return new WP_Error(
					'hvnly_csv_media_too_large',
					__( 'Remote media exceeds the maximum allowed download size.', 'havenlytics' )
				);
			}
		}

		$tmp = wp_tempnam( 'hvnly-csv-media-' );
		if ( ! $tmp ) {
			return new WP_Error( 'hvnly_csv_media_tmp', __( 'Could not create a temporary file for media download.', 'havenlytics' ) );
		}

		$args = UrlGuard::safe_request_args(
			array(
				'timeout'             => 30,
				'stream'              => true,
				'filename'            => $tmp,
				'limit_response_size' => $max_bytes,
			)
		);

		$response = wp_safe_remote_get( $url, $args );
		if ( is_wp_error( $response ) ) {
			self::unlink_temp( $tmp );
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( $code < 200 || $code >= 300 ) {
			self::unlink_temp( $tmp );
			return new WP_Error(
				'hvnly_csv_media_http',
				sprintf(
					/* translators: %d: HTTP status code */
					__( 'Remote media request failed (HTTP %d).', 'havenlytics' ),
					$code
				)
			);
		}

		if ( ! is_file( $tmp ) ) {
			self::unlink_temp( $tmp );
			return new WP_Error( 'hvnly_csv_media_empty', __( 'Remote media response was empty.', 'havenlytics' ) );
		}

		$size = filesize( $tmp );
		if ( false === $size || 0 === $size ) {
			self::unlink_temp( $tmp );
			return new WP_Error( 'hvnly_csv_media_empty', __( 'Remote media response was empty.', 'havenlytics' ) );
		}

		if ( $size > $max_bytes ) {
			self::unlink_temp( $tmp );
			return new WP_Error(
				'hvnly_csv_media_too_large',
				__( 'Remote media exceeds the maximum allowed download size.', 'havenlytics' )
			);
		}

		return $tmp;
	}

	/**
	 * Delete a temp download path if it exists.
	 *
	 * @param string $path Temp file path.
	 * @return void
	 */
	private static function unlink_temp( string $path ): void {
		if ( '' !== $path && is_file( $path ) ) {
			@unlink( $path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.unlink_unlink
		}
	}

	/**
	 * @param mixed    $url Source url (for context).
	 * @param WP_Error $error Error.
	 * @return string
	 */
	private static function warning_message( $url, WP_Error $error ): string {
		return sprintf(
			/* translators: 1: media URL, 2: error message */
			__( 'Could not fetch media "%1$s": %2$s', 'havenlytics' ),
			is_string( $url ) ? $url : '',
			$error->get_error_message()
		);
	}

	/**
	 * @return void
	 */
	private static function require_media_includes(): void {
		if ( ! function_exists( 'media_handle_sideload' ) ) {
			require_once ABSPATH . 'wp-admin/includes/media.php';
		}
		if ( ! function_exists( 'wp_handle_sideload' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		if ( ! function_exists( 'wp_generate_attachment_metadata' ) ) {
			require_once ABSPATH . 'wp-admin/includes/image.php';
		}
	}

	/**
	 * Read existing gallery attachment IDs (builder key preferred, then legacy).
	 *
	 * @param int $post_id Post ID.
	 * @return array<int, int>
	 */
	private static function read_gallery_ids( int $post_id ): array {
		$keys = array_filter(
			array(
				SchemaTargets::builder_key( 'gallery', 'images' ),
				SchemaTargets::legacy_key( 'gallery', 'images' ),
			)
		);
		foreach ( $keys as $key ) {
			$raw = get_post_meta( $post_id, $key, true );
			$ids = self::parse_id_list( $raw );
			if ( ! empty( $ids ) ) {
				return $ids;
			}
		}
		return array();
	}

	/**
	 * Persist gallery IDs as comma-separated strings (editor format) to builder + legacy keys.
	 *
	 * @param int             $post_id Post ID.
	 * @param array<int, int> $ids Attachment IDs.
	 * @return void
	 */
	private static function write_gallery_ids( int $post_id, array $ids ): void {
		$csv  = implode( ',', array_map( 'absint', $ids ) );
		$keys = array_filter(
			array(
				SchemaTargets::builder_key( 'gallery', 'images' ),
				SchemaTargets::legacy_key( 'gallery', 'images' ),
			)
		);
		foreach ( $keys as $key ) {
			if ( '' === $csv ) {
				delete_post_meta( $post_id, $key );
			} else {
				update_post_meta( $post_id, $key, $csv );
			}
		}

		// Same field_map recording as PropertyFormMapper / Onboarding after gallery writes.
		if ( class_exists( '\HvnlyNab\Core\GroupFieldIdentity' ) ) {
			\HvnlyNab\Core\GroupFieldIdentity::record_schema_groups( $post_id, array( 'gallery' ) );
		}
	}

	/**
	 * @param int $post_id Post ID.
	 * @return array<int, array{icon:string,label:string,url:string,url_type:string}>
	 */
	private static function read_documents( int $post_id ): array {
		$keys = array_filter(
			array(
				SchemaTargets::builder_key( 'property_docs', 'documents' ),
				SchemaTargets::legacy_key( 'property_docs', 'documents' ),
			)
		);
		foreach ( $keys as $key ) {
			$raw = get_post_meta( $post_id, $key, true );
			if ( is_string( $raw ) && '' !== $raw ) {
				$decoded = json_decode( $raw, true );
				if ( is_array( $decoded ) ) {
					return $decoded;
				}
			}
			if ( is_array( $raw ) ) {
				return $raw;
			}
		}
		return array();
	}

	/**
	 * @param int                                                                 $post_id Post ID.
	 * @param array<int, array{icon:string,label:string,url:string,url_type:string}> $documents Documents.
	 * @return void
	 */
	private static function write_documents( int $post_id, array $documents ): void {
		// Deduplicate by URL.
		$seen  = array();
		$clean = array();
		foreach ( $documents as $doc ) {
			if ( ! is_array( $doc ) ) {
				continue;
			}
			$url = isset( $doc['url'] ) ? esc_url_raw( (string) $doc['url'] ) : '';
			if ( '' === $url || isset( $seen[ $url ] ) ) {
				continue;
			}
			$seen[ $url ] = true;
			$clean[]      = array(
				'icon'     => sanitize_text_field( (string) ( $doc['icon'] ?? '' ) ),
				'label'    => sanitize_text_field( (string) ( $doc['label'] ?? '' ) ),
				'url'      => $url,
				'url_type' => sanitize_text_field( (string) ( $doc['url_type'] ?? 'custom' ) ),
			);
		}

		$json = wp_json_encode( $clean );
		$keys = array_filter(
			array(
				SchemaTargets::builder_key( 'property_docs', 'documents' ),
				SchemaTargets::legacy_key( 'property_docs', 'documents' ),
			)
		);
		foreach ( $keys as $key ) {
			if ( empty( $clean ) || ! $json ) {
				delete_post_meta( $post_id, $key );
			} else {
				update_post_meta( $post_id, $key, $json );
			}
		}
	}

	/**
	 * @param string $url Document URL.
	 * @param string $label Label.
	 * @return array{icon:string,label:string,url:string,url_type:string}
	 */
	private static function document_entry( string $url, string $label ): array {
		$label = sanitize_text_field( $label );
		if ( '' === $label ) {
			$label = __( 'Document', 'havenlytics' );
		}
		return array(
			'icon'     => '',
			'label'    => $label,
			'url'      => esc_url_raw( $url ),
			'url_type' => 'custom',
		);
	}

	/**
	 * @param mixed $raw Meta value (CSV string, array of IDs, or JSON).
	 * @return array<int, int>
	 */
	private static function parse_id_list( $raw ): array {
		if ( is_array( $raw ) ) {
			return array_values( array_filter( array_map( 'absint', $raw ) ) );
		}
		if ( ! is_string( $raw ) || '' === trim( $raw ) ) {
			return array();
		}
		// Prefer comma-separated attachment IDs (editor format).
		if ( false !== strpos( $raw, ',' ) || ctype_digit( trim( $raw ) ) ) {
			return array_values( array_filter( array_map( 'absint', explode( ',', $raw ) ) ) );
		}
		$decoded = json_decode( $raw, true );
		if ( is_array( $decoded ) ) {
			return array_values( array_filter( array_map( 'absint', $decoded ) ) );
		}
		return array();
	}

	/**
	 * Prevent instantiation.
	 */
	private function __construct() {}
}
