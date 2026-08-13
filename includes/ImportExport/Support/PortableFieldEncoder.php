<?php
/**
 * Converts site-local field values into portable HPTP representations.
 *
 * @package HvnlyNab\ImportExport\Support
 * @since   3.6.0
 */

namespace HvnlyNab\ImportExport\Support;

use HvnlyNab\Core\DataPreservation\MetaResolver;
use HvnlyNab\Core\GroupFieldIdentity;

defined( 'ABSPATH' ) || exit;

/**
 * Portable field encoding for property export (no binary packaging).
 *
 * @since 3.6.0
 */
final class PortableFieldEncoder {

	/**
	 * @var array<int, array<string, mixed>>
	 */
	private $media_stubs = array();

	/**
	 * attachment_id => export_key (dedupe binaries / stubs).
	 *
	 * @var array<int, string>
	 */
	private $attachment_keys = array();

	/**
	 * @var int
	 */
	private $media_seq = 0;

	/**
	 * Reset media stub catalog for a new export run.
	 *
	 * @return void
	 */
	public function reset_media_catalog(): void {
		$this->media_stubs     = array();
		$this->attachment_keys = array();
		$this->media_seq       = 0;
	}

	/**
	 * Media metadata stubs collected during encoding.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function media_catalog(): array {
		return $this->media_stubs;
	}

	/**
	 * Export encoder media state for durable job batches.
	 *
	 * @return array{stubs:array,attachment_keys:array,seq:int}
	 */
	public function export_media_state(): array {
		return array(
			'stubs'           => $this->media_stubs,
			'attachment_keys' => $this->attachment_keys,
			'seq'             => $this->media_seq,
		);
	}

	/**
	 * Restore encoder media state from a prior batch.
	 *
	 * @param array<string, mixed> $state State from export_media_state().
	 * @return void
	 */
	public function import_media_state( array $state ): void {
		$stubs = isset( $state['stubs'] ) && is_array( $state['stubs'] ) ? $state['stubs'] : array();
		$keys  = isset( $state['attachment_keys'] ) && is_array( $state['attachment_keys'] ) ? $state['attachment_keys'] : array();
		$seq   = isset( $state['seq'] ) ? (int) $state['seq'] : count( $stubs );

		$this->media_stubs = array_values( $stubs );
		$cast              = array();
		foreach ( $keys as $k => $v ) {
			$cast[ (int) $k ] = (string) $v;
		}
		$this->attachment_keys = $cast;
		$this->media_seq       = max( 0, $seq );
	}

	/**
	 * Encode all builder-driven fields for a property.
	 *
	 * @param int                  $post_id  Property ID.
	 * @param array<string, mixed> $sections Builder sections.
	 * @return array{standalone: array<string, mixed>, groups: array<int, array<string, mixed>>}
	 */
	public function encode_property_fields( int $post_id, array $sections ): array {
		$standalone  = array();
		$groups      = array();
		$seen_groups = array();

		foreach ( $sections as $section ) {
			if ( empty( $section['fields'] ) || ! is_array( $section['fields'] ) ) {
				continue;
			}

			foreach ( $section['fields'] as $field ) {
				if ( ! is_array( $field ) ) {
					continue;
				}

				$name = (string) ( $field['name'] ?? $field['id'] ?? '' );
				if ( '' === $name || ExportExclusions::is_excluded_property_meta( $name ) ) {
					continue;
				}

				$group_id   = (string) ( $field['group_id'] ?? '' );
				$group_type = (string) ( $field['group_type'] ?? '' );
				$meta_key   = (string) ( $field['metaKey'] ?? '' );

				$value = MetaResolver::get_field_value( $post_id, $field, $name );
				$value = $this->make_portable_value( $value, $group_type, $meta_key, $name );

				if ( '' === $group_id || '' === $group_type ) {
					if ( $this->has_exportable_value( $value ) ) {
						$standalone[ $name ] = array(
							'name'  => $name,
							'type'  => (string) ( $field['type'] ?? $field['input_type'] ?? '' ),
							'value' => $value,
						);
					}
					continue;
				}

				if ( ! isset( $seen_groups[ $group_id ] ) ) {
					$group_base_id            = (string) ( $field['group_base_id'] ?? '' );
					$seen_groups[ $group_id ] = array(
						'group_id'      => $group_id,
						'group_type'    => $group_type,
						'group_base_id' => $group_base_id,
						'portable_key'  => PortableFieldDecoder::make_portable_key( $group_type, $group_base_id, $group_id ),
						'values'        => array(),
					);
				}

				$suffix = $meta_key !== '' ? $meta_key : GroupFieldIdentity::resolve_meta_key( $field );
				if ( '' === $suffix ) {
					continue;
				}

				if ( $this->has_exportable_value( $value ) ) {
					$seen_groups[ $group_id ]['values'][ $suffix ] = $value;
				}
			}
		}

		// Property Documents (and similar widgets) store the real payload on the
		// collapsed storage key `{base}_documents`, while Builder DnD members may
		// still be the legacy icon/label/url scalars. Without this pass, documents
		// never enter the package and import always lands empty rows.
		foreach ( $seen_groups as $group_id => $group ) {
			$seen_groups[ $group_id ] = $this->ensure_storage_widget_values(
				(int) $post_id,
				(string) $group_id,
				$group
			);
		}

		foreach ( $seen_groups as $group ) {
			if ( ! empty( $group['values'] ) ) {
				$groups[] = $group;
			}
		}

		usort(
			$groups,
			static function ( $a, $b ) {
				return strcmp( (string) $a['group_id'], (string) $b['group_id'] );
			}
		);
		ksort( $standalone, SORT_STRING );

		return array(
			'standalone' => $standalone,
			'groups'     => $groups,
		);
	}

	/**
	 * Build a media stub from an attachment (reuses export_key per attachment).
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return array<string, mixed>|null
	 */
	public function attachment_stub( int $attachment_id ): ?array {
		$attachment_id = absint( $attachment_id );
		if ( $attachment_id <= 0 ) {
			return null;
		}

		$post = get_post( $attachment_id );
		if ( ! $post || 'attachment' !== $post->post_type ) {
			return null;
		}

		$file     = get_attached_file( $attachment_id );
		$filename = $file ? wp_basename( $file ) : (string) $post->post_name;
		$mime     = (string) get_post_mime_type( $attachment_id );

		if ( isset( $this->attachment_keys[ $attachment_id ] ) ) {
			$export_key = $this->attachment_keys[ $attachment_id ];
			return array(
				'export_key' => $export_key,
				'filename'   => $filename,
				'mime_type'  => $mime,
				'alt'        => (string) get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ),
				'title'      => (string) $post->post_title,
				'caption'    => (string) $post->post_excerpt,
			);
		}

		++$this->media_seq;
		$export_key                              = sprintf( 'm_%05d', $this->media_seq );
		$this->attachment_keys[ $attachment_id ] = $export_key;

		$stub = array(
			'export_key' => $export_key,
			'filename'   => $filename,
			'mime_type'  => $mime,
			'alt'        => (string) get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ),
			'title'      => (string) $post->post_title,
			'caption'    => (string) $post->post_excerpt,
		);

		$this->media_stubs[] = array(
			'export_key'           => $export_key,
			'filename'             => $filename,
			'mime_type'            => $mime,
			'source_attachment_id' => $attachment_id,
			'bundled'              => false,
		);

		return $stub;
	}

	/**
	 * @param mixed  $value      Raw value.
	 * @param string $group_type Group type.
	 * @param string $meta_key   Suffix.
	 * @param string $field_name Field name.
	 * @return mixed
	 */
	private function make_portable_value( $value, string $group_type, string $meta_key, string $field_name ) {
		if ( ! $this->has_exportable_value( $value ) ) {
			return $value;
		}

		// Featured / single attachment-looking numeric IDs on known fields.
		if ( is_numeric( $value ) && ( '_thumbnail_id' === $field_name || 'thumbnail' === $meta_key ) ) {
			$stub = $this->attachment_stub( (int) $value );
			return null !== $stub ? $stub : '';
		}

		if ( 'gallery' === $group_type && 'images' === $meta_key ) {
			return $this->portable_gallery( $value );
		}

		if ( 'video' === $group_type && ( 'thumbnail' === $meta_key || 'url' === $meta_key ) ) {
			return $this->portable_local_media_value( $value );
		}

		if ( 'agents' === $group_type && 'agents' === $meta_key ) {
			return $this->portable_agent_refs( $value );
		}

		if ( 'property_docs' === $group_type && 'documents' === $meta_key ) {
			return $this->portable_documents( $value );
		}

		if ( is_string( $value ) ) {
			$trim = trim( $value );
			// JSON blobs.
			if ( ( '{' === $trim[0] || '[' === $trim[0] ) ) {
				$decoded = json_decode( $trim, true );
				if ( JSON_ERROR_NONE === json_last_error() ) {
					return $decoded;
				}
			}
		}

		return $value;
	}

	/**
	 * Resolve local attachment ID or URL into a media stub; leave remote URLs as-is.
	 *
	 * @param mixed $value Raw value.
	 * @return mixed
	 */
	private function portable_local_media_value( $value ) {
		if ( is_numeric( $value ) ) {
			$stub = $this->attachment_stub( (int) $value );
			return null !== $stub ? $stub : $value;
		}

		if ( ! is_string( $value ) || '' === trim( $value ) ) {
			return $value;
		}

		$url = trim( $value );
		if ( ! preg_match( '#^https?://#i', $url ) && ! preg_match( '#^/#', $url ) ) {
			return $value;
		}

		$attachment_id = attachment_url_to_postid( $url );
		if ( $attachment_id > 0 ) {
			$stub = $this->attachment_stub( $attachment_id );
			return null !== $stub ? $stub : $value;
		}

		// Remote / unresolved URL — never download; keep as external string.
		return $url;
	}

	/**
	 * @param mixed $value Gallery CSV or array.
	 * @return array<int, array<string, mixed>>
	 */
	private function portable_gallery( $value ): array {
		$ids = array();
		if ( is_array( $value ) ) {
			$ids = array_map( 'absint', $value );
		} elseif ( is_string( $value ) || is_numeric( $value ) ) {
			$parts = preg_split( '/\s*,\s*/', (string) $value );
			$ids   = array_map( 'absint', is_array( $parts ) ? $parts : array() );
		}

		$out = array();
		foreach ( $ids as $id ) {
			if ( $id <= 0 ) {
				continue;
			}
			$stub = $this->attachment_stub( $id );
			if ( null !== $stub ) {
				$out[] = $stub;
			}
		}

		return $out;
	}

	/**
	 * @param mixed $value Agent IDs JSON/array.
	 * @return array<int, array<string, string>>
	 */
	private function portable_agent_refs( $value ): array {
		$ids = array();
		if ( is_array( $value ) ) {
			$ids = array_map( 'absint', $value );
		} elseif ( is_string( $value ) ) {
			$decoded = json_decode( $value, true );
			if ( is_array( $decoded ) ) {
				$ids = array_map( 'absint', $decoded );
			}
		}

		$out = array();
		foreach ( $ids as $id ) {
			if ( $id <= 0 ) {
				continue;
			}
			$post = get_post( $id );
			if ( ! $post || 'hvnly_agent' !== $post->post_type ) {
				continue;
			}
			$email = (string) get_post_meta( $id, '_hvnly_agent_email', true );
			$out[] = array(
				'email' => $email,
				'slug'  => (string) $post->post_name,
			);
		}

		return $out;
	}

	/**
	 * @param mixed $value Documents JSON.
	 * @return array<int, array<string, mixed>>
	 */
	private function portable_documents( $value ): array {
		$items = array();
		if ( is_array( $value ) ) {
			$items = $value;
		} elseif ( is_string( $value ) ) {
			$decoded = json_decode( $value, true );
			if ( is_array( $decoded ) ) {
				$items = $decoded;
			}
		}

		$out = array();
		foreach ( $items as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$url = isset( $item['url'] ) ? (string) $item['url'] : '';
			$row = array(
				'icon'     => isset( $item['icon'] ) ? (string) $item['icon'] : '',
				'label'    => isset( $item['label'] ) ? (string) $item['label'] : '',
				'url_type' => isset( $item['url_type'] ) ? (string) $item['url_type'] : '',
				'bundled'  => false,
			);

			$attachment_id = attachment_url_to_postid( $url );
			if ( $attachment_id > 0 ) {
				$stub = $this->attachment_stub( $attachment_id );
				if ( null !== $stub ) {
					$row['media'] = $stub;
					$row['url']   = '';
					$out[]        = $row;
					continue;
				}
			}

			// Keep external URLs; strip obvious local upload paths to basename only.
			$row['url'] = $url;
			$out[]      = $row;
		}

		return $out;
	}

	/**
	 * Ensure collapsed storage-widget payloads are present on exported groups.
	 *
	 * Property Documents are the primary case: Builder members may be icon/label/url
	 * while Admin/Workspace persist the repeater JSON on `{base}_documents`.
	 *
	 * @param int                  $post_id  Property ID.
	 * @param string               $group_id Group id.
	 * @param array<string, mixed> $group    Group payload.
	 * @return array<string, mixed>
	 */
	private function ensure_storage_widget_values( int $post_id, string $group_id, array $group ): array {
		$group_type    = (string) ( $group['group_type'] ?? '' );
		$group_base_id = (string) ( $group['group_base_id'] ?? '' );
		$values        = isset( $group['values'] ) && is_array( $group['values'] ) ? $group['values'] : array();

		$suffix_map = array(
			'property_docs' => 'documents',
			'faq'           => 'faqs',
			'repeater'      => 'items',
			'gallery'       => 'images',
			'agents'        => 'agents',
			'features'      => 'features',
		);

		if ( ! isset( $suffix_map[ $group_type ] ) || '' === $group_base_id ) {
			$group['values'] = $values;
			return $group;
		}

		$suffix = $suffix_map[ $group_type ];
		if ( isset( $values[ $suffix ] ) && $this->has_exportable_value( $values[ $suffix ] ) ) {
			$group['values'] = $values;
			return $group;
		}

		$name  = $group_base_id . '_' . $suffix;
		$field = array(
			'name'          => $name,
			'id'            => $name,
			'metaKey'       => $suffix,
			'group_type'    => $group_type,
			'group_id'      => $group_id,
			'group_base_id' => $group_base_id,
		);

		$value = MetaResolver::get_field_value( $post_id, $field, $name );
		$value = $this->make_portable_value( $value, $group_type, $suffix, $name );
		if ( $this->has_exportable_value( $value ) ) {
			$values[ $suffix ] = $value;
		}

		$group['values'] = $values;
		return $group;
	}

	/**
	 * @param mixed $value Value.
	 * @return bool
	 */
	private function has_exportable_value( $value ): bool {
		if ( null === $value || false === $value ) {
			return false;
		}
		if ( is_string( $value ) && '' === $value ) {
			return false;
		}
		if ( is_array( $value ) && empty( $value ) ) {
			return false;
		}

		return true;
	}
}
