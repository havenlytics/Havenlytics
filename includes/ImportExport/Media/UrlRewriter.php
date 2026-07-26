<?php
/**
 * Resolves portable media stubs to local attachment IDs / URLs.
 *
 * @package HvnlyNab\ImportExport\Media
 * @since   3.6.0
 */

namespace HvnlyNab\ImportExport\Media;

use HvnlyNab\ImportExport\Support\PortableFieldDecoder;

defined( 'ABSPATH' ) || exit;

/**
 * UrlRewriter — never uses source attachment IDs; export_key only.
 *
 * @since 3.6.0
 */
final class UrlRewriter {

	/**
	 * @var array<string, int>
	 */
	private $map;

	/**
	 * @param array<string, int> $export_key_map export_key => local attachment_id.
	 */
	public function __construct( array $export_key_map ) {
		$this->map = array();
		foreach ( $export_key_map as $key => $id ) {
			$key = (string) $key;
			$id  = absint( $id );
			if ( '' !== $key && $id > 0 ) {
				$this->map[ $key ] = $id;
			}
		}
	}

	/**
	 * @param string $export_key Export key.
	 * @return int
	 */
	public function attachment_id( string $export_key ): int {
		$export_key = (string) $export_key;
		return isset( $this->map[ $export_key ] ) ? (int) $this->map[ $export_key ] : 0;
	}

	/**
	 * Resolve a media stub (or export_key string) to a local attachment ID.
	 *
	 * @param mixed $stub Stub or value.
	 * @return int
	 */
	public function resolve_id( $stub ): int {
		if ( is_string( $stub ) && isset( $this->map[ $stub ] ) ) {
			return (int) $this->map[ $stub ];
		}
		if ( ! PortableFieldDecoder::is_media_stub( $stub ) ) {
			return 0;
		}
		$key = (string) ( $stub['export_key'] ?? '' );
		return $this->attachment_id( $key );
	}

	/**
	 * Resolve stub to attachment URL (empty if unresolved).
	 *
	 * @param mixed $stub Stub.
	 * @return string
	 */
	public function resolve_url( $stub ): string {
		$id = $this->resolve_id( $stub );
		if ( $id <= 0 ) {
			return '';
		}
		$url = wp_get_attachment_url( $id );
		return is_string( $url ) ? $url : '';
	}

	/**
	 * Resolve a list of stubs to attachment IDs (skips missing).
	 *
	 * @param mixed $list Stub list.
	 * @return int[]
	 */
	public function resolve_id_list( $list ): array {
		if ( ! is_array( $list ) ) {
			return array();
		}
		$ids = array();
		foreach ( $list as $item ) {
			$id = $this->resolve_id( $item );
			if ( $id > 0 ) {
				$ids[] = $id;
			}
		}
		return array_values( array_unique( $ids ) );
	}

	/**
	 * Rewrite document rows: replace embedded media stubs with local URLs.
	 *
	 * @param array<int, array<string, mixed>> $rows Document rows.
	 * @param array|null                       $pending_docs Pending stub map by index (optional).
	 * @return array{rows: array, missing: array<int, string>}
	 */
	public function rewrite_documents( array $rows, ?array $pending_docs = null ): array {
		$missing = array();
		$out     = array();

		foreach ( $rows as $index => $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$item = array(
				'icon'     => isset( $row['icon'] ) ? (string) $row['icon'] : '',
				'label'    => isset( $row['label'] ) ? (string) $row['label'] : '',
				'url_type' => isset( $row['url_type'] ) ? (string) $row['url_type'] : '',
				'url'      => isset( $row['url'] ) ? (string) $row['url'] : '',
			);

			$stub = null;
			if ( isset( $row['media'] ) && PortableFieldDecoder::is_media_stub( $row['media'] ) ) {
				$stub = $row['media'];
			} elseif ( is_array( $pending_docs ) && isset( $pending_docs[ (string) $index ] ) ) {
				$stub = $pending_docs[ (string) $index ];
			} elseif ( is_array( $pending_docs ) && isset( $pending_docs[ $index ] ) ) {
				$stub = $pending_docs[ $index ];
			}

			if ( null !== $stub ) {
				$url = $this->resolve_url( $stub );
				if ( '' === $url ) {
					$key = is_array( $stub ) ? (string) ( $stub['export_key'] ?? '' ) : '';
					$missing[] = $key;
				} else {
					$item['url'] = $url;
				}
			}

			$out[] = $item;
		}

		return array(
			'rows'    => $out,
			'missing' => $missing,
		);
	}

	/**
	 * Full export_key map.
	 *
	 * @return array<string, int>
	 */
	public function map(): array {
		return $this->map;
	}

	/**
	 * Prevent cloning.
	 */
	private function __clone() {}
}
