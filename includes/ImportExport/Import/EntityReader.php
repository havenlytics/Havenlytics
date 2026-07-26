<?php
/**
 * Reads portable entity sections from a decoded HPTP entities payload.
 *
 * @package HvnlyNab\ImportExport\Import
 * @since   3.6.0
 */

namespace HvnlyNab\ImportExport\Import;

use HvnlyNab\ImportExport\Contracts\EntityReaderInterface;

defined( 'ABSPATH' ) || exit;

/**
 * EntityReader — list sections as arrays of entity rows.
 *
 * @since 3.6.0
 */
final class EntityReader implements EntityReaderInterface {

	/**
	 * @var array<string, mixed>
	 */
	private $entities;

	/**
	 * @param array<string, mixed> $entities Decoded entities.json.
	 */
	public function __construct( array $entities ) {
		$this->entities = $entities;
	}

	/**
	 * {@inheritdoc}
	 */
	public function read_section( string $section, array $args = array() ): array {
		if ( ! isset( $this->entities[ $section ] ) ) {
			return array();
		}

		$data = $this->entities[ $section ];

		// Builders / maps are associative objects — wrap as a single row when requested.
		if ( 'builders' === $section ) {
			return is_array( $data ) ? array( $data ) : array();
		}

		if ( ! is_array( $data ) ) {
			return array();
		}

		// List of entities.
		if ( $this->is_list( $data ) ) {
			$out = array();
			foreach ( $data as $row ) {
				if ( is_array( $row ) ) {
					$out[] = $row;
				}
			}
			return $out;
		}

		return array();
	}

	/**
	 * Full entities payload.
	 *
	 * @return array<string, mixed>
	 */
	public function all(): array {
		return $this->entities;
	}

	/**
	 * Schema version string from entities payload.
	 *
	 * @return string
	 */
	public function schema_version(): string {
		return isset( $this->entities['schema_version'] )
			? (string) $this->entities['schema_version']
			: '';
	}

	/**
	 * @param array $data Array.
	 * @return bool
	 */
	private function is_list( array $data ): bool {
		if ( array() === $data ) {
			return true;
		}
		return array_keys( $data ) === range( 0, count( $data ) - 1 );
	}
}
