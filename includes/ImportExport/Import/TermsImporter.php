<?php
/**
 * Imports property taxonomy terms from an HPTP package.
 *
 * @package HvnlyNab\ImportExport\Import
 * @since   3.6.0
 */

namespace HvnlyNab\ImportExport\Import;

use HvnlyNab\ImportExport\Export\TermsExporter;
use HvnlyNab\ImportExport\Package\PackageResult;

defined( 'ABSPATH' ) || exit;

/**
 * TermsImporter — hierarchy by parent_slug; no media attachments in Phase 5A.
 *
 * @since 3.6.0
 */
final class TermsImporter {

	/**
	 * @param EntityReader      $reader   Entity reader.
	 * @param DuplicateDetector $detector Duplicate detector.
	 * @param IdRemapper        $remapper ID remapper.
	 * @param string            $policy   Duplicate policy.
	 * @return PackageResult data={created,updated,skipped,failed,warnings}
	 */
	public static function import(
		EntityReader $reader,
		DuplicateDetector $detector,
		IdRemapper $remapper,
		string $policy
	): PackageResult {
		$policy   = DuplicateDetector::normalize_policy( $policy );
		$terms    = $reader->read_section( 'terms' );
		$created  = 0;
		$updated  = 0;
		$skipped  = 0;
		$failed   = 0;
		$warnings = array();

		// Pass 1: create/update without parents.
		foreach ( $terms as $row ) {
			$result = self::upsert_term( $row, $detector, $remapper, $policy );
			$created += $result['created'];
			$updated += $result['updated'];
			$skipped += $result['skipped'];
			$failed  += $result['failed'];
			foreach ( $result['warnings'] as $warning ) {
				$warnings[] = $warning;
			}
		}

		// Pass 2: restore parent relationships using remapped IDs.
		foreach ( $terms as $row ) {
			self::apply_parent( $row, $remapper, $warnings );
		}

		return PackageResult::success(
			array(
				'created' => $created,
				'updated' => $updated,
				'skipped' => $skipped,
				'failed'  => $failed,
			),
			$warnings
		);
	}

	/**
	 * @param array<string, mixed> $row      Term row.
	 * @param DuplicateDetector    $detector Detector.
	 * @param IdRemapper           $remapper Remapper.
	 * @param string               $policy   Policy.
	 * @return array{created:int,updated:int,skipped:int,failed:int,warnings:array}
	 */
	private static function upsert_term(
		array $row,
		DuplicateDetector $detector,
		IdRemapper $remapper,
		string $policy
	): array {
		$out = array(
			'created'  => 0,
			'updated'  => 0,
			'skipped'  => 0,
			'failed'   => 0,
			'warnings' => array(),
		);

		$taxonomy = sanitize_key( (string) ( $row['taxonomy'] ?? '' ) );
		$slug     = sanitize_title( (string) ( $row['slug'] ?? '' ) );
		$name     = sanitize_text_field( (string) ( $row['name'] ?? '' ) );

		if ( '' === $taxonomy || '' === $slug || '' === $name ) {
			$out['failed']     = 1;
			$out['warnings'][] = array(
				'code'    => 'hvnly_ie_term_invalid',
				'message' => 'Term row missing taxonomy, slug, or name.',
				'context' => array( 'row' => $row ),
			);
			return $out;
		}

		if ( ! taxonomy_exists( $taxonomy ) ) {
			$out['failed']     = 1;
			$out['warnings'][] = array(
				'code'    => 'hvnly_ie_term_taxonomy_missing',
				'message' => 'Taxonomy is not registered on this site.',
				'context' => array( 'taxonomy' => $taxonomy, 'slug' => $slug ),
			);
			return $out;
		}

		if ( ! in_array( $taxonomy, TermsExporter::TAXONOMIES, true ) ) {
			$out['skipped']    = 1;
			$out['warnings'][] = array(
				'code'    => 'hvnly_ie_term_taxonomy_unsupported',
				'message' => 'Term taxonomy is outside the approved property set and was skipped.',
				'context' => array( 'taxonomy' => $taxonomy, 'slug' => $slug ),
			);
			return $out;
		}

		$existing_id = $detector->find_term( $taxonomy, $slug );

		if ( $existing_id > 0 && DuplicateDetector::POLICY_CREATE_NEW !== $policy ) {
			$remapper->set_term( $taxonomy, $slug, $existing_id );
			if ( DuplicateDetector::POLICY_SKIP === $policy ) {
				$out['skipped'] = 1;
				return $out;
			}

			$update = self::update_term( $existing_id, $taxonomy, $row );
			if ( ! $update ) {
				$out['failed'] = 1;
				return $out;
			}
			$out['updated'] = 1;
			return $out;
		}

		$insert_slug = $slug;
		if ( $existing_id > 0 && DuplicateDetector::POLICY_CREATE_NEW === $policy ) {
			$insert_slug = self::unique_term_slug( $taxonomy, $slug );
		}

		$result = wp_insert_term(
			$name,
			$taxonomy,
			array(
				'slug'        => $insert_slug,
				'description' => wp_kses_post( (string) ( $row['description'] ?? '' ) ),
			)
		);

		if ( is_wp_error( $result ) ) {
			// Race / exists — reuse if possible.
			$fallback = $detector->find_term( $taxonomy, $slug );
			if ( $fallback > 0 && DuplicateDetector::POLICY_CREATE_NEW !== $policy ) {
				$remapper->set_term( $taxonomy, $slug, $fallback );
				$out['skipped'] = 1;
				return $out;
			}
			$out['failed']     = 1;
			$out['warnings'][] = array(
				'code'    => 'hvnly_ie_term_insert_failed',
				'message' => $result->get_error_message(),
				'context' => array( 'taxonomy' => $taxonomy, 'slug' => $slug ),
			);
			return $out;
		}

		$term_id = absint( $result['term_id'] ?? 0 );
		if ( $term_id <= 0 ) {
			$out['failed'] = 1;
			return $out;
		}

		self::update_term_meta_portable( $term_id, $row );
		// Always map package slug → local ID for relationships (even if slug was uniquified).
		$remapper->set_term( $taxonomy, $slug, $term_id );
		$out['created'] = 1;
		return $out;
	}

	/**
	 * @param int                  $term_id Term ID.
	 * @param string               $taxonomy Taxonomy.
	 * @param array<string, mixed> $row Term row.
	 * @return bool
	 */
	private static function update_term( int $term_id, string $taxonomy, array $row ): bool {
		$result = wp_update_term(
			$term_id,
			$taxonomy,
			array(
				'name'        => sanitize_text_field( (string) ( $row['name'] ?? '' ) ),
				'description' => wp_kses_post( (string) ( $row['description'] ?? '' ) ),
			)
		);
		if ( is_wp_error( $result ) ) {
			return false;
		}
		self::update_term_meta_portable( $term_id, $row );
		return true;
	}

	/**
	 * Portable term meta only (no attachment creation in 5A).
	 *
	 * @param int                  $term_id Term ID.
	 * @param array<string, mixed> $row Term row.
	 * @return void
	 */
	private static function update_term_meta_portable( int $term_id, array $row ): void {
		$meta = isset( $row['meta'] ) && is_array( $row['meta'] ) ? $row['meta'] : array();

		if ( ! empty( $meta['icon'] ) && is_array( $meta['icon'] ) ) {
			$icon = array(
				'class'   => sanitize_text_field( (string) ( $meta['icon']['class'] ?? '' ) ),
				'library' => sanitize_text_field( (string) ( $meta['icon']['library'] ?? '' ) ),
			);
			update_term_meta( $term_id, '_hvnly_advanced_icon_data', $icon );
		}

		if ( isset( $meta['badge_background_color'] ) && '' !== (string) $meta['badge_background_color'] ) {
			update_term_meta( $term_id, 'hvnly_badge_background_color', sanitize_hex_color( (string) $meta['badge_background_color'] ) ?: sanitize_text_field( (string) $meta['badge_background_color'] ) );
		}

		if ( isset( $meta['badge_display_option'] ) && '' !== (string) $meta['badge_display_option'] ) {
			update_term_meta( $term_id, 'hvnly_badge_display_option', sanitize_text_field( (string) $meta['badge_display_option'] ) );
		}

		// Image stubs intentionally ignored until Phase 6 media import.
	}

	/**
	 * @param array<string, mixed> $row Term row.
	 * @param IdRemapper           $remapper Remapper.
	 * @param array                $warnings Warnings (by ref).
	 * @return void
	 */
	private static function apply_parent( array $row, IdRemapper $remapper, array &$warnings ): void {
		$taxonomy    = sanitize_key( (string) ( $row['taxonomy'] ?? '' ) );
		$slug        = sanitize_title( (string) ( $row['slug'] ?? '' ) );
		$parent_slug = sanitize_title( (string) ( $row['parent_slug'] ?? '' ) );
		$term_id     = $remapper->get_term( $taxonomy, $slug );

		if ( $term_id <= 0 ) {
			return;
		}

		$parent_id = 0;
		if ( '' !== $parent_slug ) {
			$parent_id = $remapper->get_term( $taxonomy, $parent_slug );
			if ( $parent_id <= 0 ) {
				$warnings[] = array(
					'code'    => 'hvnly_ie_term_parent_missing',
					'message' => 'Parent term could not be resolved; term imported without parent.',
					'context' => array(
						'taxonomy'    => $taxonomy,
						'slug'        => $slug,
						'parent_slug' => $parent_slug,
					),
				);
			}
		}

		$current = get_term( $term_id, $taxonomy );
		if ( ! $current instanceof \WP_Term ) {
			return;
		}
		if ( (int) $current->parent === $parent_id ) {
			return;
		}

		$result = wp_update_term( $term_id, $taxonomy, array( 'parent' => $parent_id ) );
		if ( is_wp_error( $result ) ) {
			$warnings[] = array(
				'code'    => 'hvnly_ie_term_parent_failed',
				'message' => $result->get_error_message(),
				'context' => array( 'taxonomy' => $taxonomy, 'slug' => $slug ),
			);
		}
	}

	/**
	 * @param string $taxonomy Taxonomy.
	 * @param string $slug     Base slug.
	 * @return string
	 */
	private static function unique_term_slug( string $taxonomy, string $slug ): string {
		$candidate = $slug;
		$i         = 2;
		while ( $i < 1000 ) {
			$exists = get_term_by( 'slug', $candidate, $taxonomy );
			if ( ! ( $exists instanceof \WP_Term ) ) {
				return $candidate;
			}
			$candidate = $slug . '-' . $i;
			++$i;
		}
		return $slug . '-' . wp_generate_password( 6, false, false );
	}

	/**
	 * Prevent instantiation.
	 */
	private function __construct() {}
}
