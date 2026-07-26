<?php
/**
 * Imports agency taxonomy terms from an HPTP package.
 *
 * @package HvnlyNab\ImportExport\Import
 * @since   3.6.0
 */

namespace HvnlyNab\ImportExport\Import;

use HvnlyNab\Agent\AgencyFields;
use HvnlyNab\Agent\AgentConstants;
use HvnlyNab\ImportExport\Package\PackageResult;

defined( 'ABSPATH' ) || exit;

/**
 * AgenciesImporter — slug identity; logos deferred to Phase 6.
 *
 * @since 3.6.0
 */
final class AgenciesImporter {

	/**
	 * Meta key map: portable short key => term meta key.
	 *
	 * @var array<string, string>
	 */
	private const META_MAP = array(
		'address'      => AgencyFields::META_ADDRESS,
		'license'      => AgencyFields::META_LICENSE,
		'map_provider' => AgencyFields::META_MAP_PROVIDER,
		'map_lat'      => AgencyFields::META_MAP_LAT,
		'map_lng'      => AgencyFields::META_MAP_LNG,
		'email'        => AgencyFields::META_EMAIL,
		'mobile'       => AgencyFields::META_MOBILE,
		'fax'          => AgencyFields::META_FAX,
		'office'       => AgencyFields::META_OFFICE,
		'website'      => AgencyFields::META_WEBSITE,
		'vimeo'        => AgencyFields::META_VIMEO,
		'facebook'     => AgencyFields::META_FACEBOOK,
		'twitter'      => AgencyFields::META_TWITTER,
		'pinterest'    => AgencyFields::META_PINTEREST,
		'instagram'    => AgencyFields::META_INSTAGRAM,
		'youtube'      => AgencyFields::META_YOUTUBE,
		'linkedin'     => AgencyFields::META_LINKEDIN,
		'tiktok'       => AgencyFields::META_TIKTOK,
	);

	/**
	 * @param EntityReader      $reader Reader.
	 * @param DuplicateDetector $detector Detector.
	 * @param IdRemapper        $remapper Remapper.
	 * @param string            $policy Policy.
	 * @return PackageResult
	 */
	public static function import(
		EntityReader $reader,
		DuplicateDetector $detector,
		IdRemapper $remapper,
		string $policy
	): PackageResult {
		$policy   = DuplicateDetector::normalize_policy( $policy );
		$rows     = $reader->read_section( 'agencies' );
		$created  = 0;
		$updated  = 0;
		$skipped  = 0;
		$failed   = 0;
		$warnings = array();

		if ( ! taxonomy_exists( AgentConstants::TAXONOMY_AGENCY ) ) {
			return PackageResult::failure(
				'hvnly_ie_agency_taxonomy_missing',
				'Agency taxonomy is not registered.',
				array()
			);
		}

		foreach ( $rows as $row ) {
			$result = self::upsert( $row, $detector, $remapper, $policy );
			$created += $result['created'];
			$updated += $result['updated'];
			$skipped += $result['skipped'];
			$failed  += $result['failed'];
			foreach ( $result['warnings'] as $warning ) {
				$warnings[] = $warning;
			}
		}

		foreach ( $rows as $row ) {
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
	 * @param array<string, mixed> $row Row.
	 * @param DuplicateDetector    $detector Detector.
	 * @param IdRemapper           $remapper Remapper.
	 * @param string               $policy Policy.
	 * @return array{created:int,updated:int,skipped:int,failed:int,warnings:array}
	 */
	private static function upsert(
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

		$slug = sanitize_title( (string) ( $row['slug'] ?? '' ) );
		$name = sanitize_text_field( (string) ( $row['name'] ?? '' ) );
		if ( '' === $slug || '' === $name ) {
			$out['failed']     = 1;
			$out['warnings'][] = array(
				'code'    => 'hvnly_ie_agency_invalid',
				'message' => 'Agency row missing slug or name.',
				'context' => array(),
			);
			return $out;
		}

		$existing_id = $detector->find_agency( $slug );

		if ( $existing_id > 0 && DuplicateDetector::POLICY_CREATE_NEW !== $policy ) {
			$remapper->set_agency( $slug, $existing_id );
			if ( DuplicateDetector::POLICY_SKIP === $policy ) {
				$out['skipped'] = 1;
				return $out;
			}
			if ( ! self::update_agency( $existing_id, $row ) ) {
				$out['failed'] = 1;
				return $out;
			}
			$out['updated'] = 1;
			return $out;
		}

		$insert_slug = $slug;
		if ( $existing_id > 0 && DuplicateDetector::POLICY_CREATE_NEW === $policy ) {
			$insert_slug = self::unique_slug( $slug );
		}

		$result = wp_insert_term(
			$name,
			AgentConstants::TAXONOMY_AGENCY,
			array(
				'slug'        => $insert_slug,
				'description' => wp_kses_post( (string) ( $row['description'] ?? '' ) ),
			)
		);

		if ( is_wp_error( $result ) ) {
			$fallback = $detector->find_agency( $slug );
			if ( $fallback > 0 && DuplicateDetector::POLICY_CREATE_NEW !== $policy ) {
				$remapper->set_agency( $slug, $fallback );
				$out['skipped'] = 1;
				return $out;
			}
			$out['failed']     = 1;
			$out['warnings'][] = array(
				'code'    => 'hvnly_ie_agency_insert_failed',
				'message' => $result->get_error_message(),
				'context' => array( 'slug' => $slug ),
			);
			return $out;
		}

		$term_id = absint( $result['term_id'] ?? 0 );
		if ( $term_id <= 0 ) {
			$out['failed'] = 1;
			return $out;
		}

		self::write_meta( $term_id, $row );
		$remapper->set_agency( $slug, $term_id );
		$out['created'] = 1;
		return $out;
	}

	/**
	 * @param int                  $term_id Term ID.
	 * @param array<string, mixed> $row Row.
	 * @return bool
	 */
	private static function update_agency( int $term_id, array $row ): bool {
		$result = wp_update_term(
			$term_id,
			AgentConstants::TAXONOMY_AGENCY,
			array(
				'name'        => sanitize_text_field( (string) ( $row['name'] ?? '' ) ),
				'description' => wp_kses_post( (string) ( $row['description'] ?? '' ) ),
			)
		);
		if ( is_wp_error( $result ) ) {
			return false;
		}
		self::write_meta( $term_id, $row );
		return true;
	}

	/**
	 * @param int                  $term_id Term ID.
	 * @param array<string, mixed> $row Row.
	 * @return void
	 */
	private static function write_meta( int $term_id, array $row ): void {
		$meta = isset( $row['meta'] ) && is_array( $row['meta'] ) ? $row['meta'] : array();
		foreach ( self::META_MAP as $short => $meta_key ) {
			if ( ! array_key_exists( $short, $meta ) ) {
				continue;
			}
			$value = $meta[ $short ];
			if ( 'email' === $short ) {
				$value = sanitize_email( (string) $value );
			} elseif ( in_array( $short, array( 'website', 'vimeo', 'facebook', 'twitter', 'pinterest', 'instagram', 'youtube', 'linkedin', 'tiktok' ), true ) ) {
				$value = esc_url_raw( (string) $value );
			} elseif ( 'address' === $short ) {
				$value = sanitize_textarea_field( (string) $value );
			} else {
				$value = sanitize_text_field( (string) $value );
			}
			if ( '' === $value ) {
				continue;
			}
			update_term_meta( $term_id, $meta_key, $value );
		}
		// Logo media deferred to Phase 6.
	}

	/**
	 * @param array<string, mixed> $row Row.
	 * @param IdRemapper           $remapper Remapper.
	 * @param array                $warnings Warnings.
	 * @return void
	 */
	private static function apply_parent( array $row, IdRemapper $remapper, array &$warnings ): void {
		$slug        = sanitize_title( (string) ( $row['slug'] ?? '' ) );
		$parent_slug = sanitize_title( (string) ( $row['parent_slug'] ?? '' ) );
		$term_id     = $remapper->get_agency( $slug );
		if ( $term_id <= 0 ) {
			return;
		}

		$parent_id = '' !== $parent_slug ? $remapper->get_agency( $parent_slug ) : 0;
		if ( '' !== $parent_slug && $parent_id <= 0 ) {
			$warnings[] = array(
				'code'    => 'hvnly_ie_agency_parent_missing',
				'message' => 'Parent agency could not be resolved.',
				'context' => array( 'slug' => $slug, 'parent_slug' => $parent_slug ),
			);
			return;
		}

		$current = get_term( $term_id, AgentConstants::TAXONOMY_AGENCY );
		if ( ! $current instanceof \WP_Term || (int) $current->parent === $parent_id ) {
			return;
		}

		$result = wp_update_term( $term_id, AgentConstants::TAXONOMY_AGENCY, array( 'parent' => $parent_id ) );
		if ( is_wp_error( $result ) ) {
			$warnings[] = array(
				'code'    => 'hvnly_ie_agency_parent_failed',
				'message' => $result->get_error_message(),
				'context' => array( 'slug' => $slug ),
			);
		}
	}

	/**
	 * @param string $slug Slug.
	 * @return string
	 */
	private static function unique_slug( string $slug ): string {
		$candidate = $slug;
		$i         = 2;
		while ( $i < 1000 ) {
			$exists = get_term_by( 'slug', $candidate, AgentConstants::TAXONOMY_AGENCY );
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
