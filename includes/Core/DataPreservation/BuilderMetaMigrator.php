<?php
/**
 * Migrates property post meta when builder group_base_id values change.
 *
 * @package HvnlyNab\Core\DataPreservation
 * @since   3.0.2
 */

namespace HvnlyNab\Core\DataPreservation;

use HvnlyNab\Core\GroupFieldIdentity;

defined( 'ABSPATH' ) || exit;

class BuilderMetaMigrator {

	/** @var array<string, array<string>> */
	private const TYPE_SUFFIXES = array(
		'video'         => array( 'title', 'url', 'thumbnail' ),
		'gallery'       => array( 'title', 'images' ),
		'map'           => array( 'address', 'latitude', 'longitude', 'preview' ),
		'property_docs' => array( 'documents', 'show_in_sidebar', 'icon', 'label', 'url' ),
		'agents'        => array( 'title', 'agents' ),
	);

	/**
	 * Build group_id => group_base_id maps from builder sections.
	 *
	 * @param array $sections Builder option value.
	 * @return array<string, string>
	 */
	public static function extract_group_bases( array $sections ): array {
		$map = array();
		foreach ( $sections as $section ) {
			foreach ( $section['fields'] ?? array() as $field ) {
				$gid  = $field['group_id'] ?? '';
				$base = $field['group_base_id'] ?? '';
				if ( $gid !== '' && $base !== '' ) {
					$map[ $gid ] = $base;
				}
			}
		}
		return $map;
	}

	/**
	 * Build old_base => new_base remap when builder config changes.
	 *
	 * Only the same group_id may remap (e.g. group_base_id renamed in builder).
	 * New group instances are never paired by group_type position.
	 *
	 * @param array $before_sections Config before change.
	 * @param array $after_sections  Config after change.
	 * @return array<string, string>
	 */
	public static function build_remap_between_configs( array $before_sections, array $after_sections ): array {
		$before = self::extract_group_bases( $before_sections );
		$after  = self::extract_group_bases( $after_sections );
		$remap  = array();

		foreach ( $after as $group_id => $new_base ) {
			if ( ! isset( $before[ $group_id ] ) ) {
				self::log_skip_new_group( $group_id, $new_base );
				continue;
			}

			$old_base = $before[ $group_id ];
			if ( $old_base === $new_base ) {
				continue;
			}

			$remap[ $old_base ] = $new_base;
			self::log_group_remap( $group_id, $old_base, $new_base );
		}

		return $remap;
	}

	/**
	 * Debug log when a new group_id is added (no meta copy).
	 *
	 * @param string $group_id Group ID.
	 * @param string $new_base New group_base_id.
	 * @return void
	 */
	private static function log_skip_new_group( string $group_id, string $new_base ): void {
		if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
			return;
		}

		error_log(
			'[HVNLY MIGRATOR] SKIP NEW GROUP'
			. ' group_id=' . $group_id
			. ' base=' . $new_base
			. ' reason=no_matching_group_id_in_before_config'
		);
	}

	/**
	 * Debug log when group_id matched and base ID changed.
	 *
	 * @param string $group_id Group ID.
	 * @param string $old_base Previous group_base_id.
	 * @param string $new_base New group_base_id.
	 * @return void
	 */
	private static function log_group_remap( string $group_id, string $old_base, string $new_base ): void {
		if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
			return;
		}

		error_log(
			'[HVNLY MIGRATOR] REMAP'
			. ' group_id=' . $group_id
			. ' old=' . $old_base
			. ' new=' . $new_base
		);
	}

	/**
	 * Schedule batched property meta remap (delegates to Version236Handler).
	 *
	 * @param array<string, string> $remap old_base => new_base.
	 * @return void
	 */
	public static function schedule_property_remap( array $remap ): void {
		if ( empty( $remap ) ) {
			return;
		}
		if ( class_exists( '\HvnlyNab\Core\Migration\Handlers\Version236Handler' ) ) {
			\HvnlyNab\Core\Migration\Handlers\Version236Handler::schedule_remap( $remap );
		}
	}

	/**
	 * Migrate property meta after builder config change (old → new bases by group_id).
	 *
	 * @param array $before_sections Builder config before change.
	 * @param array $after_sections  Builder config after change.
	 * @param string $batch_state_key Resume state option key.
	 * @return array{complete:bool,copied:int,properties:int}
	 */
	public static function migrate_after_builder_change(
		array $before_sections,
		array $after_sections,
		string $batch_state_key = 'hvnly_builder_meta_migrate_offset'
	): array {
		$before = self::extract_group_bases( $before_sections );
		$after  = self::extract_group_bases( $after_sections );

		$remap = array();
		foreach ( $after as $group_id => $new_base ) {
			if ( isset( $before[ $group_id ] ) && $before[ $group_id ] !== $new_base ) {
				$remap[ $before[ $group_id ] ] = $new_base;
			}
		}

		if ( empty( $remap ) ) {
			delete_option( $batch_state_key );
			return array( 'complete' => true, 'copied' => 0, 'properties' => 0 );
		}

		$copied_total = 0;
		$props        = 0;

		$result = BatchProcessor::each_property(
			function ( int $post_id ) use ( $remap, &$copied_total, &$props ) {
				$copied = self::remap_property_meta( $post_id, $remap );
				if ( $copied > 0 ) {
					++$props;
					$copied_total += $copied;
				}
			},
			$batch_state_key
		);

		return array(
			'complete'   => $result['complete'],
			'copied'     => $copied_total,
			'properties' => $props,
		);
	}

	/**
	 * Copy meta from old bases to new bases; update _hvnly_field_map.
	 *
	 * @param int                  $post_id Post ID.
	 * @param array<string,string> $remap   old_base => new_base.
	 * @return int Keys copied.
	 */
	public static function remap_property_meta( int $post_id, array $remap ): int {
		$copied = 0;

		foreach ( $remap as $old_base => $new_base ) {
			foreach ( self::TYPE_SUFFIXES as $type => $suffixes ) {
				if ( strpos( $old_base, $type . '_' ) !== 0 ) {
					continue;
				}
				foreach ( $suffixes as $suffix ) {
					$old_key = $old_base . '_' . $suffix;
					$new_key = $new_base . '_' . $suffix;
					if ( function_exists( 'hvnly_copy_post_meta_if_empty' ) ) {
						if ( hvnly_copy_post_meta_if_empty( $post_id, $old_key, $new_key ) ) {
							$src = get_post_meta( $post_id, $old_key, true );
							$tgt = get_post_meta( $post_id, $new_key, true );
							if ( self::has_value( $src ) && self::has_value( $tgt ) && $src === $tgt ) {
								++$copied;
							}
						}
					}
				}
			}
		}

		if ( $copied > 0 && class_exists( GroupFieldIdentity::class ) ) {
			$map = GroupFieldIdentity::get_field_map( $post_id );
			foreach ( $remap as $old_base => $new_base ) {
				foreach ( $map['groups'] as $gid => $base ) {
					if ( $base === $old_base ) {
						$map['groups'][ $gid ] = $new_base;
					}
				}
				foreach ( $map['legacy'] as $type => $base ) {
					if ( $base === $old_base ) {
						$map['legacy'][ $type ] = $new_base;
					}
				}
			}
			update_post_meta( $post_id, GroupFieldIdentity::FIELD_MAP_META, wp_json_encode( $map ) );
		}

		return $copied;
	}

	/**
	 * @param mixed $value Value.
	 * @return bool
	 */
	private static function has_value( $value ): bool {
		return $value !== '' && $value !== false && $value !== null;
	}
}
