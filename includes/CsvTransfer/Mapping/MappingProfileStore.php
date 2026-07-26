<?php
/**
 * Options-backed storage for saved CSV mapping profiles.
 *
 * @package HvnlyNab\CsvTransfer\Mapping
 * @since   3.7.0
 */

namespace HvnlyNab\CsvTransfer\Mapping;

defined( 'ABSPATH' ) || exit;

/**
 * MappingProfileStore — CRUD for `hvnly_csv_mapping_profiles`.
 *
 * Profile shape: {id, name, source, version, delimiter, mapping:{header:fieldId}, updated_at}.
 *
 * @since 3.7.0
 */
final class MappingProfileStore {

	public const OPTION_KEY = 'hvnly_csv_mapping_profiles';

	/**
	 * @return array<int, array<string, mixed>> All saved profiles.
	 */
	public static function list(): array {
		$rows = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $rows ) ) {
			return array();
		}
		return array_values( array_filter( $rows, 'is_array' ) );
	}

	/**
	 * @param string $id Profile id.
	 * @return array<string, mixed>|null
	 */
	public static function get( string $id ): ?array {
		if ( '' === $id ) {
			return null;
		}
		foreach ( self::list() as $profile ) {
			if ( (string) ( $profile['id'] ?? '' ) === $id ) {
				return $profile;
			}
		}
		return null;
	}

	/**
	 * Create or update a profile.
	 *
	 * @param array<string, mixed> $profile Profile payload.
	 * @return array<string, mixed> Saved profile (with id/updated_at populated).
	 */
	public static function save( array $profile ): array {
		$id = isset( $profile['id'] ) ? sanitize_key( (string) $profile['id'] ) : '';
		if ( '' === $id ) {
			$id = 'csvmap_' . substr( md5( wp_generate_password( 12, false ) . microtime() ), 0, 12 );
		}

		$mapping = array();
		if ( isset( $profile['mapping'] ) && is_array( $profile['mapping'] ) ) {
			foreach ( $profile['mapping'] as $header => $field_id ) {
				$mapping[ (string) $header ] = null === $field_id ? null : sanitize_key( (string) $field_id );
			}
		}

		$clean = array(
			'id'         => $id,
			'name'       => isset( $profile['name'] ) ? sanitize_text_field( (string) $profile['name'] ) : __( 'Untitled mapping', 'havenlytics' ),
			'source'     => isset( $profile['source'] ) ? sanitize_key( (string) $profile['source'] ) : 'custom',
			'version'    => isset( $profile['version'] ) ? sanitize_text_field( (string) $profile['version'] ) : '1',
			'delimiter'  => isset( $profile['delimiter'] ) ? (string) $profile['delimiter'] : ',',
			'mapping'    => $mapping,
			'updated_at' => gmdate( 'c' ),
		);

		$rows  = self::list();
		$found = false;
		foreach ( $rows as $index => $row ) {
			if ( (string) ( $row['id'] ?? '' ) === $id ) {
				$rows[ $index ] = $clean;
				$found          = true;
				break;
			}
		}
		if ( ! $found ) {
			$rows[] = $clean;
		}

		update_option( self::OPTION_KEY, $rows, false );

		return $clean;
	}

	/**
	 * @param string $id Profile id.
	 * @return bool True when a profile was removed.
	 */
	public static function delete( string $id ): bool {
		if ( '' === $id ) {
			return false;
		}
		$rows      = self::list();
		$remaining = array_values(
			array_filter(
				$rows,
				static function ( $row ) use ( $id ) {
					return (string) ( $row['id'] ?? '' ) !== $id;
				}
			)
		);

		if ( count( $remaining ) === count( $rows ) ) {
			return false;
		}

		update_option( self::OPTION_KEY, $remaining, false );
		return true;
	}

	/**
	 * Prevent instantiation.
	 */
	private function __construct() {}
}
