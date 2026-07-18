<?php
/**
 * Seeds Property Type taxonomy term images during demo import only.
 *
 * @package     Havenlytics
 * @subpackage  Admin/Importer
 * @since       3.1.2
 */

namespace HvnlyNab\Admin\Importer;

use HvnlyNab\Admin\Data\PropertyTypeTermImages;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Assigns stock images to default demo hvnly_prop_types terms when empty.
 */
class PropertyTypeTermImageSeeder {

	public const TAXONOMY = 'hvnly_prop_types';
	public const META_KEY = '_hvnly_term_advanced_image_data';

	/**
	 * @var string[]
	 */
	private const ALLOWED_HOSTS = array(
		'images.pexels.com',
		'demo.havenlytics.com',
		'localhost',
		'127.0.0.1',
	);

	/**
	 * @var string[]
	 */
	private const ALLOWED_MIMES = array(
		'image/jpeg',
		'image/jpg',
		'image/png',
		'image/gif',
		'image/webp',
	);

	/**
	 * @var int
	 */
	private const MAX_FILE_SIZE = 5242880;

	/**
	 * @var int
	 */
	private const DOWNLOAD_TIMEOUT = 15;

	/** @var callable(string):void|null */
	private $logger = null;

	/**
	 * Assign images to up to $limit default demo type terms that still have none.
	 *
	 * @param int                        $limit  Max terms to attempt this call.
	 * @param callable(string):void|null $logger Optional logger.
	 * @return int Number of terms attempted (not necessarily succeeded).
	 */
	public function run( int $limit = 2, $logger = null ): int {
		$this->logger = is_callable( $logger ) ? $logger : null;

		if ( $limit <= 0 || ! taxonomy_exists( self::TAXONOMY ) ) {
			return 0;
		}

		$image_map = PropertyTypeTermImages::get_image_map();
		if ( empty( $image_map ) ) {
			return 0;
		}

		$attempted = 0;

		foreach ( array_keys( $image_map ) as $slug ) {
			if ( $attempted >= $limit ) {
				break;
			}

			if ( ! PropertyTypeTermImages::is_demo_type_slug( $slug ) ) {
				continue;
			}

			$term = get_term_by( 'slug', $slug, self::TAXONOMY );
			if ( ! $term instanceof \WP_Term ) {
				continue;
			}

			if ( $this->term_has_image( (int) $term->term_id ) ) {
				continue;
			}

			$this->maybe_assign_term_image( $slug, $image_map[ $slug ] ?? '' );
			++$attempted;
		}

		return $attempted;
	}

	/**
	 * Keep filling missing demo property type images until none remain or rounds are exhausted.
	 *
	 * @param callable(string):void|null $logger Optional logger.
	 * @return void
	 */
	public function finish_pending( $logger = null ): void {
		$this->logger = is_callable( $logger ) ? $logger : null;

		$rounds     = 0;
		$max_rounds = (int) ceil( count( PropertyTypeTermImages::get_demo_type_slugs() ) / 2 ) + 2;

		while ( $rounds < $max_rounds ) {
			$attempted = $this->run( 3 );
			if ( 0 === $attempted ) {
				break;
			}
			++$rounds;
		}
	}

	/**
	 * @param string $slug Term slug.
	 * @param string $url  Primary image URL.
	 * @return bool
	 */
	private function maybe_assign_term_image( string $slug, string $url ): bool {
		$slug = sanitize_title( $slug );
		$url  = esc_url_raw( $url );

		if ( '' === $slug || ! PropertyTypeTermImages::is_demo_type_slug( $slug ) ) {
			return false;
		}

		$term = get_term_by( 'slug', $slug, self::TAXONOMY );
		if ( ! $term instanceof \WP_Term ) {
			$this->log( 'Property type image skipped — term not found: ' . $slug );
			return false;
		}

		$term_id = (int) $term->term_id;
		if ( $this->term_has_image( $term_id ) ) {
			return false;
		}

		$attachment_id = '' !== $url ? $this->download_term_image( $url, 'type-' . $slug ) : 0;
		if ( $attachment_id <= 0 ) {
			$this->log( 'Property type image skipped for ' . $slug . ' (download failed).' );
			return false;
		}

		update_term_meta(
			$term_id,
			self::META_KEY,
			array(
				'id'          => $attachment_id,
				'selected_at' => current_time( 'mysql' ),
				'version'     => '1.0.0',
			)
		);

		$this->log( 'Property type image assigned for ' . $slug . ' (attachment ' . $attachment_id . ').' );

		return true;
	}

	/**
	 * @param string $url    Remote image URL.
	 * @param string $suffix Filename suffix.
	 * @return int Attachment ID or 0.
	 */
	private function download_term_image( string $url, string $suffix ): int {
		$url = esc_url_raw( $url );
		if ( '' === $url || ! $this->is_allowed_image_url( $url ) ) {
			return 0;
		}

		// Skip the remote fetch when there is no outbound HTTP (Playground/offline).
		if ( function_exists( 'hvnly_import_remote_media_available' ) && ! hvnly_import_remote_media_available() ) {
			return 0;
		}

		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$temp_file = download_url( $url, self::DOWNLOAD_TIMEOUT, true );
		if ( is_wp_error( $temp_file ) ) {
			$this->log( 'Property type image download error: ' . $temp_file->get_error_message() );
			return 0;
		}

		if ( ! is_string( $temp_file ) || ! file_exists( $temp_file ) ) {
			return 0;
		}

		$filesize = filesize( $temp_file );
		if ( false === $filesize || $filesize > self::MAX_FILE_SIZE ) {
			wp_delete_file( $temp_file );
			$this->log( 'Property type image too large or unreadable: ' . $url );
			return 0;
		}

		$path = (string) wp_parse_url( $url, PHP_URL_PATH );
		$ext  = strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );
		if ( ! in_array( $ext, array( 'jpg', 'jpeg', 'png', 'gif', 'webp' ), true ) ) {
			$ext = 'jpg';
		}

		$file_info = wp_check_filetype_and_ext( $temp_file, 'image.' . $ext );
		$mime_type = $file_info['type'] ?? '';

		if ( empty( $mime_type ) || ! in_array( $mime_type, self::ALLOWED_MIMES, true ) ) {
			wp_delete_file( $temp_file );
			$this->log( 'Property type image invalid mime for ' . $url . ': ' . $mime_type );
			return 0;
		}

		$file_array = array(
			'name'     => sanitize_file_name( 'hvnly-property-type-' . $suffix . '-' . uniqid() . '.' . $ext ),
			'tmp_name' => $temp_file,
		);

		$attachment_id = media_handle_sideload( $file_array, 0 );

		if ( file_exists( $temp_file ) ) {
			wp_delete_file( $temp_file );
		}

		if ( is_wp_error( $attachment_id ) ) {
			$this->log( 'Property type image sideload failed: ' . $attachment_id->get_error_message() );
			return 0;
		}

		return absint( $attachment_id );
	}

	/**
	 * @param string $url Image URL.
	 * @return bool
	 */
	private function is_allowed_image_url( string $url ): bool {
		$host = wp_parse_url( $url, PHP_URL_HOST );
		if ( ! is_string( $host ) || '' === $host ) {
			return false;
		}

		return in_array( strtolower( $host ), self::ALLOWED_HOSTS, true );
	}

	/**
	 * @param int $term_id Term ID.
	 * @return bool
	 */
	private function term_has_image( int $term_id ): bool {
		$raw = get_term_meta( $term_id, self::META_KEY, true );
		if ( ! is_array( $raw ) ) {
			return false;
		}

		$attachment_id = absint( $raw['id'] ?? 0 );
		if ( $attachment_id <= 0 ) {
			return false;
		}

		return (bool) wp_get_attachment_url( $attachment_id );
	}

	/**
	 * @param string $message Log message.
	 * @return void
	 */
	private function log( string $message ): void {
		if ( is_callable( $this->logger ) ) {
			call_user_func( $this->logger, $message );
		}
	}
}
