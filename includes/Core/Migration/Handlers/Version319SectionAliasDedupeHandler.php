<?php
/**
 * Version 3.1.9 — Collapse legacy/canonical duplicate builder sections.
 *
 * Sites that received both sec_additional_info (v2.2.4) and sec_property_details
 * (unified defaults) render two "Additional Information" tabs. This heals the
 * stored Builder option only — no metabox HTML filtering, no meta key renames.
 *
 * All equivalence / merge logic lives in SectionIdentity (sole source of truth).
 *
 * @package     Havenlytics
 * @subpackage  Core\Migration\Handlers
 * @since       3.1.9
 */

namespace HvnlyNab\Core\Migration\Handlers;

use HvnlyNab\Core\Migration\Interfaces\MigrationInterface;
use HvnlyNab\Core\Migration\Traits\MigrationTrait;
use HvnlyNab\Core\SectionIdentity;

defined( 'ABSPATH' ) || exit;

class Version319SectionAliasDedupeHandler implements MigrationInterface {

	use MigrationTrait;

	const PROPERTY_BUILDER_KEY = 'hvnly_property_builder.sections';

	/**
	 * {@inheritdoc}
	 */
	public function get_version(): string {
		return '3.1.9-section-alias-dedupe';
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_description(): string {
		return 'Removes duplicate builder sections that share a legacy/canonical section ID alias';
	}

	/**
	 * {@inheritdoc}
	 */
	public function is_needed(): bool {
		$sections = get_option( self::PROPERTY_BUILDER_KEY, array() );
		if ( empty( $sections ) || ! is_array( $sections ) ) {
			return false;
		}

		$deduped = SectionIdentity::deduplicate_equivalent_sections( $sections );

		return ! empty( $deduped['removed'] );
	}

	/**
	 * {@inheritdoc}
	 */
	public function up(): bool {
		$this->log( 'Starting builder section alias dedupe', 'info', $this->get_version() );

		$sections = get_option( self::PROPERTY_BUILDER_KEY, array() );
		if ( empty( $sections ) || ! is_array( $sections ) ) {
			$this->log( 'No builder sections to dedupe', 'info', $this->get_version() );
			return true;
		}

		$deduped = SectionIdentity::deduplicate_equivalent_sections( $sections );
		if ( empty( $deduped['removed'] ) ) {
			$this->log( 'No equivalent section duplicates found', 'info', $this->get_version() );
			return true;
		}

		$this->backup_option( self::PROPERTY_BUILDER_KEY, '3.1.9-section-alias-dedupe' );

		$updated = $deduped['sections'];
		uasort(
			$updated,
			static function ( $a, $b ) {
				return ( (int) ( $a['order'] ?? 999 ) ) <=> ( (int) ( $b['order'] ?? 999 ) );
			}
		);

		update_option( self::PROPERTY_BUILDER_KEY, $updated );
		wp_cache_delete( self::PROPERTY_BUILDER_KEY, 'options' );
		wp_cache_delete( 'hvnly_standardized_field_names' );

		$this->log(
			'Removed duplicate section keys: ' . implode( ', ', $deduped['removed'] ),
			'info',
			$this->get_version()
		);

		return true;
	}

	/**
	 * {@inheritdoc}
	 */
	public function down(): bool {
		return true;
	}
}
