<?php
/**
 * Property Builder keep / replace policies for HPTP import.
 *
 * @package HvnlyNab\ImportExport\Import
 * @since   3.6.0
 */

namespace HvnlyNab\ImportExport\Import;

use HvnlyNab\Core\DataPreservation\BackupManager;
use HvnlyNab\Core\EnterpriseCache;
use HvnlyNab\ImportExport\Package\PackageResult;

defined( 'ABSPATH' ) || exit;

/**
 * BuilderImportPolicy — never replaces without a successful BackupManager snapshot.
 *
 * @since 3.6.0
 */
final class BuilderImportPolicy {

	public const POLICY_KEEP    = 'keep';
	public const POLICY_REPLACE = 'replace';

	/**
	 * @param string $policy Policy string.
	 * @return string
	 */
	public static function normalize_policy( string $policy ): string {
		$policy = strtolower( trim( $policy ) );
		return in_array( $policy, array( self::POLICY_KEEP, self::POLICY_REPLACE ), true )
			? $policy
			: self::POLICY_KEEP;
	}

	/**
	 * Apply builder policy before property field writes.
	 *
	 * @param EntityReader $reader Entity reader.
	 * @param string       $policy keep|replace.
	 * @return PackageResult data={policy,action,snapshot_id}
	 */
	public static function apply( EntityReader $reader, string $policy ): PackageResult {
		$policy = self::normalize_policy( $policy );

		if ( self::POLICY_KEEP === $policy ) {
			return PackageResult::success(
				array(
					'policy'      => self::POLICY_KEEP,
					'action'      => 'kept_destination',
					'snapshot_id' => '',
				)
			);
		}

		$builders_rows = $reader->read_section( 'builders' );
		$builders      = isset( $builders_rows[0] ) && is_array( $builders_rows[0] ) ? $builders_rows[0] : array();

		if ( empty( $builders ) ) {
			return PackageResult::failure(
				'hvnly_ie_builder_replace_empty',
				'Replace Builder was requested but the package has no builders payload.',
				array()
			);
		}

		$snapshot_id = BackupManager::create_snapshot( 'hptp-import-builder-replace' );
		if ( '' === $snapshot_id ) {
			return PackageResult::failure(
				'hvnly_ie_builder_backup_failed',
				'Builder replace aborted: BackupManager could not create a snapshot.',
				array()
			);
		}

		$applied = self::write_builders( $builders );
		if ( ! $applied['ok'] ) {
			$restored = BackupManager::restore_snapshot( $snapshot_id );
			return PackageResult::failure(
				'hvnly_ie_builder_replace_failed',
				'Builder replace failed; destination snapshot restore attempted.',
				array(
					'snapshot_id'     => $snapshot_id,
					'restore_ok'      => $restored,
					'failure_message' => $applied['message'],
				)
			);
		}

		if ( class_exists( EnterpriseCache::class ) && function_exists( 'HVNLY_NAB' ) ) {
			try {
				$cache = EnterpriseCache::get_instance();
				if ( method_exists( $cache, 'invalidate_sections' ) ) {
					$cache->invalidate_sections();
				}
			} catch ( \Throwable $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
				// Cache invalidation is best-effort after a successful option write.
			}
		}

		return PackageResult::success(
			array(
				'policy'      => self::POLICY_REPLACE,
				'action'      => 'replaced_destination',
				'snapshot_id' => $snapshot_id,
			),
			$applied['warnings']
		);
	}

	/**
	 * Write portable builders payload onto destination options.
	 *
	 * @param array<string, mixed> $builders Builders section.
	 * @return array{ok:bool,message:string,warnings:array}
	 */
	private static function write_builders( array $builders ): array {
		$warnings = array();

		$property = isset( $builders['property'] ) && is_array( $builders['property'] )
			? $builders['property']
			: array();
		$sections = isset( $property['sections'] ) && is_array( $property['sections'] )
			? $property['sections']
			: null;

		if ( null === $sections ) {
			return array(
				'ok'       => false,
				'message'  => 'Package builders.property.sections is missing.',
				'warnings' => $warnings,
			);
		}

		if ( false === update_option( 'hvnly_property_builder.sections', $sections, false ) ) {
			// update_option returns false when value unchanged — treat identical write as success.
			$current = get_option( 'hvnly_property_builder.sections', null );
			if ( $current !== $sections ) {
				return array(
					'ok'       => false,
					'message'  => 'Failed to write hvnly_property_builder.sections.',
					'warnings' => $warnings,
				);
			}
		}

		if ( isset( $property['master_base_ids'] ) && is_array( $property['master_base_ids'] ) ) {
			update_option( 'hvnly_master_base_ids', $property['master_base_ids'], false );
		}

		$card = isset( $builders['card'] ) && is_array( $builders['card'] ) ? $builders['card'] : array();
		if ( isset( $card['sections'] ) && is_array( $card['sections'] ) ) {
			update_option( 'hvnly_property_card.sections', $card['sections'], false );
		}

		$settings = get_option( 'hvnly_plugin_settings', array() );
		if ( ! is_array( $settings ) ) {
			$settings = array();
		}

		$search = isset( $builders['search'] ) && is_array( $builders['search'] ) ? $builders['search'] : array();
		if ( ! empty( $search ) ) {
			if ( ! isset( $settings['search'] ) || ! is_array( $settings['search'] ) ) {
				$settings['search'] = array();
			}
			foreach ( array( 'hvnly_search_fields', 'hvnly_top_search_fields', 'hvnly_main_search_fields' ) as $key ) {
				if ( array_key_exists( $key, $search ) ) {
					$settings['search'][ $key ] = $search[ $key ];
				}
			}
		}

		$listing = isset( $builders['listing_display'] ) && is_array( $builders['listing_display'] )
			? $builders['listing_display']
			: array();
		if ( ! empty( $listing ) ) {
			if ( ! isset( $settings['general'] ) || ! is_array( $settings['general'] ) ) {
				$settings['general'] = array();
			}
			foreach ( $listing as $key => $value ) {
				$settings['general'][ (string) $key ] = $value;
			}
		}

		if ( ! empty( $search ) || ! empty( $listing ) ) {
			update_option( 'hvnly_plugin_settings', $settings, false );
		}

		if ( isset( $builders['price_on_call'] ) && is_array( $builders['price_on_call'] ) ) {
			update_option( 'hvnly_price_on_call_custom_options', $builders['price_on_call'], false );
		}

		return array(
			'ok'       => true,
			'message'  => '',
			'warnings' => $warnings,
		);
	}

	/**
	 * Prevent instantiation.
	 */
	private function __construct() {}
}
