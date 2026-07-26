<?php
/**
 * Imports agent CPT profiles from an HPTP package.
 *
 * @package HvnlyNab\ImportExport\Import
 * @since   3.6.0
 */

namespace HvnlyNab\ImportExport\Import;

use HvnlyNab\Agent\AgentConstants;
use HvnlyNab\ImportExport\Package\PackageResult;
use HvnlyNab\ImportExport\Support\ExportExclusions;

defined( 'ABSPATH' ) || exit;

/**
 * AgentsImporter — email then slug identity; agencies by remapped slugs.
 *
 * Photos are deferred to Phase 6. Missing linked WordPress users are created
 * (Option A) then linked to the agent CPT.
 *
 * @since 3.6.0
 */
final class AgentsImporter {

	/**
	 * Short portable meta keys that are URLs.
	 *
	 * @var string[]
	 */
	private const URL_SHORT_KEYS = array(
		'website',
		'vimeo',
		'facebook',
		'twitter',
		'pinterest',
		'instagram',
		'youtube',
		'linkedin',
		'tiktok',
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
		$rows     = $reader->read_section( 'agents' );
		$created  = 0;
		$updated  = 0;
		$skipped  = 0;
		$failed   = 0;
		$warnings = array();

		if ( ! post_type_exists( AgentConstants::POST_TYPE ) ) {
			return PackageResult::failure(
				'hvnly_ie_agent_post_type_missing',
				'Agent post type is not registered.',
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

		$slug  = sanitize_title( (string) ( $row['slug'] ?? '' ) );
		$title = sanitize_text_field( (string) ( $row['title'] ?? '' ) );
		$email = strtolower( sanitize_email( (string) ( $row['email'] ?? '' ) ) );
		if ( '' === $email ) {
			$email = strtolower( sanitize_email( (string) ( $row['linked_user_email'] ?? '' ) ) );
		}

		if ( '' === $slug || '' === $title ) {
			$out['failed']     = 1;
			$out['warnings'][] = array(
				'code'    => 'hvnly_ie_agent_invalid',
				'message' => 'Agent row missing slug or title.',
				'context' => array(),
			);
			return $out;
		}

		$existing_id = $detector->find_agent( $email, $slug, $row );

		if ( $existing_id > 0 && DuplicateDetector::POLICY_CREATE_NEW !== $policy ) {
			$remapper->set_agent( $slug, $email, $existing_id );
			if ( DuplicateDetector::POLICY_SKIP === $policy ) {
				// Still rebuild agency links so relationships stay consistent on re-import.
				self::link_agencies( $existing_id, $row, $remapper, $out['warnings'] );
				self::maybe_link_user( $existing_id, $row, $out['warnings'] );
				$out['skipped'] = 1;
				return $out;
			}

			if ( ! self::update_agent( $existing_id, $row, $remapper, $out['warnings'] ) ) {
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

		$status = sanitize_key( (string) ( $row['status'] ?? 'publish' ) );
		$allowed_status = array( 'publish', 'draft', 'pending', 'private' );
		if ( ! in_array( $status, $allowed_status, true ) ) {
			$status = 'publish';
		}

		$post_id = wp_insert_post(
			wp_slash(
				array(
					'post_title'   => $title,
					'post_name'    => $insert_slug,
					'post_content' => wp_kses_post( (string) ( $row['content'] ?? '' ) ),
					'post_excerpt' => sanitize_textarea_field( (string) ( $row['excerpt'] ?? '' ) ),
					'post_status'  => $status,
					'post_type'    => AgentConstants::POST_TYPE,
					'post_author'  => get_current_user_id() ?: 1,
				)
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			$fallback = $detector->find_agent( $email, $slug, $row );
			if ( $fallback > 0 && DuplicateDetector::POLICY_CREATE_NEW !== $policy ) {
				$remapper->set_agent( $slug, $email, $fallback );
				self::link_agencies( $fallback, $row, $remapper, $out['warnings'] );
				self::maybe_link_user( $fallback, $row, $out['warnings'] );
				$out['skipped'] = 1;
				return $out;
			}
			$out['failed']     = 1;
			$out['warnings'][] = array(
				'code'    => 'hvnly_ie_agent_insert_failed',
				'message' => $post_id->get_error_message(),
				'context' => array( 'slug' => $slug, 'email' => $email ),
			);
			return $out;
		}

		$post_id = absint( $post_id );
		if ( $post_id <= 0 ) {
			$out['failed'] = 1;
			return $out;
		}

		self::write_meta( $post_id, $row, $out['warnings'] );
		self::link_agencies( $post_id, $row, $remapper, $out['warnings'] );
		$remapper->set_agent( $slug, $email, $post_id );
		$out['created'] = 1;
		return $out;
	}

	/**
	 * @param int                  $post_id Post ID.
	 * @param array<string, mixed> $row Row.
	 * @param IdRemapper           $remapper Remapper.
	 * @param array                $warnings Warnings.
	 * @return bool
	 */
	private static function update_agent( int $post_id, array $row, IdRemapper $remapper, array &$warnings ): bool {
		$status = sanitize_key( (string) ( $row['status'] ?? 'publish' ) );
		$allowed_status = array( 'publish', 'draft', 'pending', 'private' );
		if ( ! in_array( $status, $allowed_status, true ) ) {
			$status = 'publish';
		}

		$result = wp_update_post(
			wp_slash(
				array(
					'ID'           => $post_id,
					'post_title'   => sanitize_text_field( (string) ( $row['title'] ?? '' ) ),
					'post_content' => wp_kses_post( (string) ( $row['content'] ?? '' ) ),
					'post_excerpt' => sanitize_textarea_field( (string) ( $row['excerpt'] ?? '' ) ),
					'post_status'  => $status,
				)
			),
			true
		);

		if ( is_wp_error( $result ) ) {
			$warnings[] = array(
				'code'    => 'hvnly_ie_agent_update_failed',
				'message' => $result->get_error_message(),
				'context' => array( 'post_id' => $post_id ),
			);
			return false;
		}

		self::write_meta( $post_id, $row, $warnings );
		self::link_agencies( $post_id, $row, $remapper, $warnings );
		return true;
	}

	/**
	 * @param int                  $post_id Post ID.
	 * @param array<string, mixed> $row Row.
	 * @param array                $warnings Warnings.
	 * @return void
	 */
	private static function write_meta( int $post_id, array $row, array &$warnings ): void {
		$email = strtolower( sanitize_email( (string) ( $row['email'] ?? '' ) ) );
		if ( '' === $email ) {
			$email = strtolower( sanitize_email( (string) ( $row['linked_user_email'] ?? '' ) ) );
		}
		if ( '' !== $email ) {
			update_post_meta( $post_id, AgentConstants::META_EMAIL, $email );
		}

		$portable_id = trim( (string) ( $row['portable_id'] ?? $row['portable_key'] ?? '' ) );
		if ( '' === $portable_id ) {
			$slug = sanitize_title( (string) ( $row['slug'] ?? '' ) );
			if ( '' !== $email ) {
				$portable_id = 'agent:' . $email;
			} elseif ( '' !== $slug ) {
				$portable_id = 'agent:' . $slug;
			}
		}
		if ( '' !== $portable_id ) {
			update_post_meta( $post_id, '_hvnly_agent_portable_id', sanitize_text_field( $portable_id ) );
		}

		$meta = isset( $row['meta'] ) && is_array( $row['meta'] ) ? $row['meta'] : array();
		foreach ( $meta as $short => $value ) {
			$short = (string) $short;
			if ( '' === $short || 'email' === $short ) {
				continue;
			}

			$full_key = '_hvnly_agent_' . $short;
			if ( in_array( $full_key, ExportExclusions::agent_meta_keys(), true ) ) {
				continue;
			}
			if ( ! in_array( $full_key, AgentConstants::profile_meta_keys(), true ) ) {
				continue;
			}

			if ( AgentConstants::META_EXTENSIONS === $full_key ) {
				if ( is_array( $value ) || is_object( $value ) ) {
					update_post_meta( $post_id, $full_key, $value );
				}
				continue;
			}

			if ( in_array( $short, self::URL_SHORT_KEYS, true ) ) {
				$sanitized = esc_url_raw( (string) $value );
			} elseif ( 'address' === $short ) {
				$sanitized = sanitize_textarea_field( (string) $value );
			} else {
				$sanitized = sanitize_text_field( (string) $value );
			}

			if ( '' === $sanitized ) {
				continue;
			}
			update_post_meta( $post_id, $full_key, $sanitized );
		}

		self::maybe_link_user( $post_id, $row, $warnings );
		// Photo attachment deferred to Phase 6.
	}

	/**
	 * Link an existing WP user by email, or create one when missing (Option A).
	 *
	 * Suppresses User→Agent auto-provision so wp_insert_user cannot create a
	 * duplicate hvnly_agent CPT. Links via AgentProvisioner (authorized writer).
	 *
	 * @param int                  $post_id Post ID.
	 * @param array<string, mixed> $row Row.
	 * @param array                $warnings Warnings.
	 * @return void
	 */
	private static function maybe_link_user( int $post_id, array $row, array &$warnings ): void {
		$linked_email = strtolower( sanitize_email( (string) ( $row['linked_user_email'] ?? '' ) ) );
		if ( '' === $linked_email ) {
			$linked_email = strtolower( sanitize_email( (string) ( $row['email'] ?? '' ) ) );
		}
		if ( '' === $linked_email ) {
			return;
		}

		$user = get_user_by( 'email', $linked_email );
		if ( ! $user instanceof \WP_User ) {
			if ( class_exists( '\HvnlyNab\Workspace\Auth\AgentProvisioner' ) ) {
				$user = \HvnlyNab\Workspace\Auth\AgentProvisioner::without_auto_agent_provision(
					static function () use ( $linked_email, $row, &$warnings ) {
						return self::create_linked_user( $linked_email, $row, $warnings );
					}
				);
			} else {
				$user = self::create_linked_user( $linked_email, $row, $warnings );
			}
		}
		if ( ! $user instanceof \WP_User ) {
			return;
		}

		if ( class_exists( '\HvnlyNab\Workspace\Auth\AgentProvisioner' ) ) {
			$result = \HvnlyNab\Workspace\Auth\AgentProvisioner::without_auto_agent_provision(
				static function () use ( $post_id, $user ) {
					self::ensure_agent_role( $user );
					return ( new \HvnlyNab\Workspace\Auth\AgentProvisioner() )->set_linked_user(
						$post_id,
						(int) $user->ID,
						array( 'confirm_relink' => true )
					);
				}
			);
			if ( is_wp_error( $result ) ) {
				$warnings[] = array(
					'code'    => 'hvnly_ie_agent_user_link_failed',
					'message' => $result->get_error_message(),
					'context' => array(
						'agent_id'          => $post_id,
						'linked_user_email' => $linked_email,
						'user_id'           => (int) $user->ID,
					),
				);
			}
			return;
		}

		self::ensure_agent_role( $user );
		update_post_meta( $post_id, AgentConstants::META_LINKED_USER_ID, (int) $user->ID );
	}

	/**
	 * Create a WordPress user for an imported agent.
	 *
	 * @param string               $email Email.
	 * @param array<string, mixed> $row Agent row.
	 * @param array                $warnings Warnings.
	 * @return \WP_User|null
	 */
	private static function create_linked_user( string $email, array $row, array &$warnings ): ?\WP_User {
		$slug  = sanitize_title( (string) ( $row['slug'] ?? '' ) );
		$title = sanitize_text_field( (string) ( $row['title'] ?? '' ) );
		$login = self::unique_user_login( $email, $slug );

		$userdata = array(
			'user_login'   => $login,
			'user_email'   => $email,
			'user_pass'    => wp_generate_password( 24, true, true ),
			'display_name' => '' !== $title ? $title : $login,
			'nickname'     => '' !== $title ? $title : $login,
			'role'         => self::default_agent_role(),
		);

		$user_id = wp_insert_user( $userdata );
		if ( is_wp_error( $user_id ) ) {
			$warnings[] = array(
				'code'    => 'hvnly_ie_agent_user_create_failed',
				'message' => 'Could not create WordPress user for imported agent: ' . $user_id->get_error_message(),
				'context' => array(
					'slug'              => $slug,
					'linked_user_email' => $email,
				),
			);
			return null;
		}

		$user = get_user_by( 'id', (int) $user_id );
		if ( ! $user instanceof \WP_User ) {
			$warnings[] = array(
				'code'    => 'hvnly_ie_agent_user_create_failed',
				'message' => 'WordPress user was created but could not be loaded for agent linkage.',
				'context' => array(
					'slug'              => $slug,
					'linked_user_email' => $email,
					'user_id'           => (int) $user_id,
				),
			);
			return null;
		}

		$warnings[] = array(
			'code'    => 'hvnly_ie_agent_user_created',
			'message' => 'Created WordPress user for imported agent and linked it.',
			'context' => array(
				'slug'              => $slug,
				'linked_user_email' => $email,
				'user_id'           => (int) $user->ID,
				'user_login'        => (string) $user->user_login,
			),
		);

		return $user;
	}

	/**
	 * @param string $email Email.
	 * @param string $slug Agent slug.
	 * @return string
	 */
	private static function unique_user_login( string $email, string $slug ): string {
		$local = strstr( $email, '@', true );
		$base  = sanitize_user( (string) ( $local !== false ? $local : $email ), true );
		if ( '' === $base && '' !== $slug ) {
			$base = sanitize_user( $slug, true );
		}
		if ( '' === $base ) {
			$base = 'hvnly_agent';
		}
		$base  = substr( $base, 0, 50 );
		$login = $base;
		$i     = 1;
		while ( username_exists( $login ) ) {
			$suffix = (string) $i;
			$login  = substr( $base, 0, max( 1, 60 - strlen( $suffix ) ) ) . $suffix;
			++$i;
			if ( $i > 1000 ) {
				$login = 'hvnly_agent_' . wp_generate_password( 8, false, false );
				break;
			}
		}
		return $login;
	}

	/**
	 * @return string
	 */
	private static function default_agent_role(): string {
		if ( class_exists( '\HvnlyNab\Workspace\Auth\PortalCapabilities' ) ) {
			$role = \HvnlyNab\Workspace\Auth\PortalCapabilities::WP_ROLE_AGENT;
			if ( is_string( $role ) && '' !== $role && get_role( $role ) ) {
				return $role;
			}
		}
		return 'subscriber';
	}

	/**
	 * @param \WP_User $user User.
	 * @return void
	 */
	private static function ensure_agent_role( \WP_User $user ): void {
		$role = self::default_agent_role();
		if ( 'subscriber' === $role ) {
			return;
		}
		if ( ! in_array( $role, (array) $user->roles, true ) ) {
			$user->add_role( $role );
		}
	}

	/**
	 * Rebuild agency relationships via remapped agency slugs.
	 *
	 * @param int                  $post_id Post ID.
	 * @param array<string, mixed> $row Row.
	 * @param IdRemapper           $remapper Remapper.
	 * @param array                $warnings Warnings.
	 * @return void
	 */
	private static function link_agencies( int $post_id, array $row, IdRemapper $remapper, array &$warnings ): void {
		if ( ! taxonomy_exists( AgentConstants::TAXONOMY_AGENCY ) ) {
			return;
		}

		$slugs = isset( $row['agency_slugs'] ) && is_array( $row['agency_slugs'] )
			? $row['agency_slugs']
			: array();

		$term_ids = array();
		foreach ( $slugs as $agency_slug ) {
			$agency_slug = sanitize_title( (string) $agency_slug );
			if ( '' === $agency_slug ) {
				continue;
			}
			$term_id = $remapper->get_agency( $agency_slug );
			if ( $term_id <= 0 ) {
				// Fall back to live lookup if remapper missed (e.g. agency section skipped).
				$term = get_term_by( 'slug', $agency_slug, AgentConstants::TAXONOMY_AGENCY );
				$term_id = ( $term instanceof \WP_Term ) ? (int) $term->term_id : 0;
			}
			if ( $term_id <= 0 ) {
				$warnings[] = array(
					'code'    => 'hvnly_ie_agent_agency_missing',
					'message' => 'Agency slug could not be resolved for agent.',
					'context' => array(
						'agent_slug'  => sanitize_title( (string) ( $row['slug'] ?? '' ) ),
						'agency_slug' => $agency_slug,
					),
				);
				continue;
			}
			$term_ids[] = $term_id;
		}

		$term_ids = array_values( array_unique( array_map( 'absint', $term_ids ) ) );
		$result   = wp_set_object_terms( $post_id, $term_ids, AgentConstants::TAXONOMY_AGENCY, false );
		if ( is_wp_error( $result ) ) {
			$warnings[] = array(
				'code'    => 'hvnly_ie_agent_agency_link_failed',
				'message' => $result->get_error_message(),
				'context' => array( 'post_id' => $post_id ),
			);
		}
	}

	/**
	 * @param string $slug Base slug.
	 * @return string
	 */
	private static function unique_slug( string $slug ): string {
		$candidate = $slug;
		$i         = 2;
		while ( $i < 1000 ) {
			$exists = get_page_by_path( $candidate, OBJECT, AgentConstants::POST_TYPE );
			if ( ! ( $exists instanceof \WP_Post ) ) {
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
