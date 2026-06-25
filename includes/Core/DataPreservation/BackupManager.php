<?php
/**
 * Pre-migration backup snapshots for builder, settings, and field maps.
 *
 * @package HvnlyNab\Core\DataPreservation
 * @since   3.0.2
 */

namespace HvnlyNab\Core\DataPreservation;

defined( 'ABSPATH' ) || exit;

class BackupManager {

	const TIMESTAMP_KEY = 'hvnly_backup_timestamp';
	const VERSION_KEY   = 'hvnly_backup_version';
	const SNAPSHOT_PREFIX = 'hvnly_snapshot_';

	/**
	 * Options to snapshot before migrations.
	 *
	 * @return string[]
	 */
	public static function snapshot_option_keys(): array {
		return array(
			'hvnly_plugin_settings',
			'hvnly_property_builder.sections',
			'hvnly_property_card.sections',
			'hvnly_pb_card_builder_sections',
			'hvnly_card_builder_config',
			'hvnly_master_base_ids',
			'hvnly_unified_meta_index',
		);
	}

	/**
	 * Create a full pre-migration snapshot.
	 *
	 * @param string $reason Optional reason tag (e.g. migration version).
	 * @return string Snapshot ID on success, empty string on failure.
	 */
	public static function create_snapshot( string $reason = '' ): string {
		$snapshot_id = self::SNAPSHOT_PREFIX . gmdate( 'Ymd_His' ) . '_' . wp_generate_password( 6, false );
		$payload     = array(
			'id'        => $snapshot_id,
			'version'   => defined( 'HVNLYNAB_VERSION' ) ? HVNLYNAB_VERSION : '',
			'timestamp' => current_time( 'mysql', true ),
			'reason'    => $reason,
			'options'   => array(),
		);

		foreach ( self::snapshot_option_keys() as $key ) {
			$value = get_option( $key, null );
			if ( null !== $value ) {
				$payload['options'][ $key ] = $value;
			}
		}

		if ( ! update_option( $snapshot_id, $payload, false ) ) {
			return '';
		}

		update_option( self::TIMESTAMP_KEY, $payload['timestamp'], false );
		update_option( self::VERSION_KEY, $payload['version'], false );
		update_option( 'hvnly_latest_snapshot_id', $snapshot_id, false );

		if ( function_exists( 'hvnly_debug_log' ) ) {
			hvnly_debug_log( 'Snapshot created: ' . $snapshot_id . ( $reason ? " ($reason)" : '' ), 'BackupManager' );
		}

		return $snapshot_id;
	}

	/**
	 * Restore options from a snapshot ID.
	 *
	 * @param string $snapshot_id Snapshot option name.
	 * @return bool
	 */
	public static function restore_snapshot( string $snapshot_id ): bool {
		if ( strpos( $snapshot_id, self::SNAPSHOT_PREFIX ) !== 0 ) {
			return false;
		}

		$payload = get_option( $snapshot_id, null );
		if ( ! is_array( $payload ) || empty( $payload['options'] ) ) {
			return false;
		}

		foreach ( $payload['options'] as $key => $value ) {
			update_option( $key, $value, false );
		}

		if ( function_exists( 'hvnly_debug_log' ) ) {
			hvnly_debug_log( 'Restored snapshot: ' . $snapshot_id, 'BackupManager' );
		}

		return true;
	}

	/**
	 * Restore the most recent snapshot.
	 *
	 * @return bool
	 */
	public static function restore_latest(): bool {
		$id = get_option( 'hvnly_latest_snapshot_id', '' );
		return is_string( $id ) && $id !== '' && self::restore_snapshot( $id );
	}
}
