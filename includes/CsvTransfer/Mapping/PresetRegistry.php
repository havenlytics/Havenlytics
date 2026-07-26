<?php
/**
 * Bundled CSV mapping presets (platform → Havenlytics field maps).
 *
 * Presets are data only — never plugin-specific import engines.
 *
 * @package HvnlyNab\CsvTransfer\Mapping
 * @since   3.7.0
 */

namespace HvnlyNab\CsvTransfer\Mapping;

defined( 'ABSPATH' ) || exit;

/**
 * PresetRegistry — load / filter / lookup JSON mapping presets.
 *
 * @since 3.7.0
 */
final class PresetRegistry {

	/**
	 * Absolute path to the bundled Presets directory.
	 *
	 * @return string
	 */
	public static function directory(): string {
		return __DIR__ . '/Presets';
	}

	/**
	 * All available presets (bundled JSON + filter), sorted by `order` then name.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function all(): array {
		$presets = array();
		$dir     = self::directory();

		foreach ( glob( $dir . '/*.json' ) ?: array() as $file ) {
			$decoded = json_decode( (string) file_get_contents( $file ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			if ( ! is_array( $decoded ) || empty( $decoded['id'] ) ) {
				continue;
			}
			$presets[] = self::normalize( $decoded );
		}

		/**
		 * Filter CSV mapping presets (add / remove / reorder without touching the engine).
		 *
		 * @since 3.7.0
		 *
		 * @param array<int, array<string, mixed>> $presets Preset profiles.
		 */
		$presets = (array) apply_filters( 'hvnly_csv_mapping_presets', $presets );

		$clean = array();
		foreach ( $presets as $preset ) {
			if ( ! is_array( $preset ) || empty( $preset['id'] ) ) {
				continue;
			}
			$clean[] = self::normalize( $preset );
		}

		usort(
			$clean,
			static function ( array $a, array $b ): int {
				$order_a = isset( $a['order'] ) ? (int) $a['order'] : 100;
				$order_b = isset( $b['order'] ) ? (int) $b['order'] : 100;
				if ( $order_a !== $order_b ) {
					return $order_a <=> $order_b;
				}
				return strcasecmp( (string) ( $a['name'] ?? '' ), (string) ( $b['name'] ?? '' ) );
			}
		);

		return array_values( $clean );
	}

	/**
	 * @param string $id Preset id.
	 * @return array<string, mixed>|null
	 */
	public static function get( string $id ): ?array {
		$id = sanitize_key( $id );
		if ( '' === $id ) {
			return null;
		}
		foreach ( self::all() as $preset ) {
			if ( (string) ( $preset['id'] ?? '' ) === $id ) {
				return $preset;
			}
		}
		return null;
	}

	/**
	 * Public list for the Settings UI (no raw mapping payload required).
	 *
	 * @return array<int, array{id:string,name:string,description:string,source:string,order:int}>
	 */
	public static function public_list(): array {
		$out = array();
		foreach ( self::all() as $preset ) {
			$out[] = array(
				'id'          => (string) ( $preset['id'] ?? '' ),
				'name'        => (string) ( $preset['name'] ?? '' ),
				'description' => (string) ( $preset['description'] ?? '' ),
				'source'      => (string) ( $preset['source'] ?? '' ),
				'order'       => isset( $preset['order'] ) ? (int) $preset['order'] : 100,
			);
		}
		return $out;
	}

	/**
	 * @param array<string, mixed> $preset Raw preset.
	 * @return array<string, mixed>
	 */
	private static function normalize( array $preset ): array {
		$id = sanitize_key( (string) ( $preset['id'] ?? '' ) );
		$mapping = array();
		if ( isset( $preset['mapping'] ) && is_array( $preset['mapping'] ) ) {
			foreach ( $preset['mapping'] as $header => $field_id ) {
				$mapping[ (string) $header ] = null === $field_id || '' === $field_id
					? null
					: sanitize_key( (string) $field_id );
			}
		}

		return array(
			'id'          => $id,
			'name'        => isset( $preset['name'] ) ? sanitize_text_field( (string) $preset['name'] ) : $id,
			'description' => isset( $preset['description'] ) ? sanitize_text_field( (string) $preset['description'] ) : '',
			'source'      => isset( $preset['source'] ) ? sanitize_key( (string) $preset['source'] ) : $id,
			'version'     => isset( $preset['version'] ) ? sanitize_text_field( (string) $preset['version'] ) : '1',
			'delimiter'   => isset( $preset['delimiter'] ) ? (string) $preset['delimiter'] : ',',
			'order'       => isset( $preset['order'] ) ? (int) $preset['order'] : 100,
			'mapping'     => $mapping,
		);
	}

	/**
	 * Prevent instantiation.
	 */
	private function __construct() {}
}
