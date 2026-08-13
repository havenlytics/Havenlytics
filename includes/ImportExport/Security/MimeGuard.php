<?php
/**
 * MIME / extension allowlists for HPTP packages and media entries.
 *
 * @package HvnlyNab\ImportExport\Security
 * @since   3.6.0
 */

namespace HvnlyNab\ImportExport\Security;

use HvnlyNab\ImportExport\Package\PackageResult;

defined( 'ABSPATH' ) || exit;

/**
 * Blocks executables and unexpected types inside packages.
 *
 * @since 3.6.0
 */
final class MimeGuard {

	/**
	 * Allowed package container extensions.
	 *
	 * @var string[]
	 */
	private const PACKAGE_EXTENSIONS = array( 'zip' );

	/**
	 * Allowed package MIME types.
	 *
	 * @var string[]
	 */
	private const PACKAGE_MIMES = array(
		'application/zip',
		'application/x-zip-compressed',
		'application/octet-stream',
	);

	/**
	 * Allowed media extensions inside media/.
	 *
	 * @var string[]
	 */
	private const MEDIA_EXTENSIONS = array(
		'jpg',
		'jpeg',
		'png',
		'webp',
		'gif',
		'pdf',
		'doc',
		'docx',
		'xls',
		'xlsx',
		'txt',
		'mp4',
		'webm',
		'mov',
		'm4v',
	);

	/**
	 * Allowed media MIME types.
	 *
	 * @var string[]
	 */
	private const MEDIA_MIMES = array(
		'image/jpeg',
		'image/png',
		'image/webp',
		'image/gif',
		'application/pdf',
		'application/msword',
		'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
		'application/vnd.ms-excel',
		'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
		'text/plain',
		'video/mp4',
		'video/webm',
		'video/quicktime',
		'video/x-m4v',
		'application/octet-stream',
	);

	/**
	 * Denied extensions (executables / scripts).
	 *
	 * @var string[]
	 */
	private const DENIED_EXTENSIONS = array(
		'php',
		'phtml',
		'phar',
		'php3',
		'php4',
		'php5',
		'php7',
		'php8',
		'exe',
		'bat',
		'cmd',
		'com',
		'sh',
		'bash',
		'cgi',
		'pl',
		'py',
		'rb',
		'js',
		'jar',
		'war',
		'scr',
		'vbs',
		'ps1',
		'htaccess',
		'htm',
		'html',
		'shtml',
	);

	/**
	 * Validate an outer HPTP .zip file on disk.
	 *
	 * @param string $path Absolute path to uploaded/exported ZIP.
	 * @return PackageResult
	 */
	public static function validate_package_file( string $path ): PackageResult {
		if ( ! is_file( $path ) || ! is_readable( $path ) ) {
			return PackageResult::failure(
				'hvnly_ie_package_unreadable',
				'Package file is missing or unreadable.',
				array( 'path' => $path )
			);
		}

		$ext = strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );
		if ( ! in_array( $ext, self::PACKAGE_EXTENSIONS, true ) ) {
			return PackageResult::failure(
				'hvnly_ie_package_extension',
				'Package must be a .zip file.',
				array( 'extension' => $ext )
			);
		}

		$mime = self::detect_mime( $path );
		if ( '' !== $mime && ! in_array( $mime, self::PACKAGE_MIMES, true ) ) {
			return PackageResult::failure(
				'hvnly_ie_package_mime',
				'Package MIME type is not an allowed ZIP type.',
				array( 'mime' => $mime )
			);
		}

		// ZIP local file header magic: PK\x03\x04 or empty archive PK\x05\x06.
		$fh = fopen( $path, 'rb' );
		if ( false === $fh ) {
			return PackageResult::failure(
				'hvnly_ie_package_unreadable',
				'Unable to open package for magic-byte validation.',
				array( 'path' => $path )
			);
		}
		$header = fread( $fh, 4 );
		fclose( $fh );

		if ( ! is_string( $header ) || strlen( $header ) < 2 || 'PK' !== substr( $header, 0, 2 ) ) {
			return PackageResult::failure(
				'hvnly_ie_package_not_zip',
				'File is not a valid ZIP archive.',
				array( 'path' => $path )
			);
		}

		return PackageResult::success(
			array(
				'mime'      => $mime,
				'extension' => $ext,
			)
		);
	}

	/**
	 * Validate a relative archive entry intended as package media.
	 *
	 * @param string      $relative_path Sanitized relative path.
	 * @param string|null $absolute_file Optional extracted file for MIME sniff.
	 * @return PackageResult
	 */
	public static function validate_media_entry( string $relative_path, ?string $absolute_file = null ): PackageResult {
		$ext = strtolower( pathinfo( $relative_path, PATHINFO_EXTENSION ) );
		if ( '' === $ext ) {
			return PackageResult::failure(
				'hvnly_ie_media_extension_missing',
				'Media entries must include a file extension.',
				array( 'path' => $relative_path )
			);
		}

		if ( in_array( $ext, self::DENIED_EXTENSIONS, true ) ) {
			return PackageResult::failure(
				'hvnly_ie_media_executable',
				'Executable or script files are not allowed in packages.',
				array(
					'path' => $relative_path,
					'extension' => $ext,
				)
			);
		}

		if ( ! in_array( $ext, self::MEDIA_EXTENSIONS, true ) ) {
			return PackageResult::failure(
				'hvnly_ie_media_extension',
				'Media file extension is not allowed.',
				array(
					'path' => $relative_path,
					'extension' => $ext,
				)
			);
		}

		// Nested archives inside media/ are rejected (bomb / smuggling).
		if ( 'zip' === $ext ) {
			return PackageResult::failure(
				'hvnly_ie_media_nested_zip',
				'Nested ZIP archives are not allowed inside packages.',
				array( 'path' => $relative_path )
			);
		}

		if ( null !== $absolute_file && is_file( $absolute_file ) ) {
			$mime = self::detect_mime( $absolute_file );
			if ( '' !== $mime && ! in_array( $mime, self::MEDIA_MIMES, true ) ) {
				return PackageResult::failure(
					'hvnly_ie_media_mime',
					'Media MIME type is not allowed.',
					array(
						'path' => $relative_path,
						'mime' => $mime,
					)
				);
			}
		}

		return PackageResult::success(
			array(
				'extension' => $ext,
			)
		);
	}

	/**
	 * Validate JSON payload filenames at package root.
	 *
	 * @param string $relative_path Sanitized path.
	 * @return PackageResult
	 */
	public static function validate_json_entry( string $relative_path ): PackageResult {
		$ext = strtolower( pathinfo( $relative_path, PATHINFO_EXTENSION ) );
		if ( 'json' !== $ext && 'txt' !== $ext ) {
			return PackageResult::failure(
				'hvnly_ie_json_extension',
				'Package metadata entries must be .json (or optional .txt report).',
				array( 'path' => $relative_path )
			);
		}

		return PackageResult::success( array( 'extension' => $ext ) );
	}

	/**
	 * @param string $path File path.
	 * @return string MIME or empty.
	 */
	private static function detect_mime( string $path ): string {
		if ( function_exists( 'finfo_open' ) ) {
			$finfo = finfo_open( FILEINFO_MIME_TYPE );
			if ( false !== $finfo ) {
				$mime = finfo_file( $finfo, $path );
				finfo_close( $finfo );
				if ( is_string( $mime ) && '' !== $mime ) {
					return strtolower( $mime );
				}
			}
		}

		if ( function_exists( 'mime_content_type' ) ) {
			$mime = @mime_content_type( $path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			if ( is_string( $mime ) && '' !== $mime ) {
				return strtolower( $mime );
			}
		}

		if ( function_exists( 'wp_check_filetype' ) ) {
			$check = wp_check_filetype( $path );
			if ( ! empty( $check['type'] ) ) {
				return strtolower( (string) $check['type'] );
			}
		}

		return '';
	}

	/**
	 * Prevent instantiation.
	 */
	private function __construct() {}
}
