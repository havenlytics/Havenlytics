<?php
/**
 * Imports demo agencies and agents during the Property Import Wizard.
 *
 * @package     Havenlytics
 * @subpackage  Admin
 * @since       3.0.2
 */

namespace HvnlyNab\Admin;

use HvnlyNab\Admin\Data\DemoAgentAgencyData;
use HvnlyNab\Agent\AgentAgencyTaxonomy;
use HvnlyNab\Agent\AgentConstants;
use HvnlyNab\Agent\AgentFields;
use HvnlyNab\Agent\AgentPostType;
use HvnlyNab\Agent\AgencyFields;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Creates demo agency taxonomy terms, agent CPT posts, and property assignments.
 *
 * @since 3.0.2
 */
final class DemoAgentAgencyImporter {

	public const ECOSYSTEM_VERSION_KEY = 'hvnly_demo_agent_ecosystem_version';
	public const AGENT_MAP_OPTION      = 'hvnly_demo_agent_slug_map';
	public const ECOSYSTEM_VERSION     = '3.0.5';

	/** @var callable|null */
	private $image_downloader;

	/** @var callable|null */
	private $log_callback;

	/** @var array<string, int>|null */
	private static $runtime_agent_map = null;

	/**
	 * @param callable|null $image_downloader Callback( string $url, int $parent_id, string $suffix ): int|false.
	 * @param callable|null $log_callback     Callback( string $message ): void.
	 */
	public function __construct( $image_downloader = null, $log_callback = null ) {
		$this->image_downloader = is_callable( $image_downloader ) ? $image_downloader : null;
		$this->log_callback     = is_callable( $log_callback ) ? $log_callback : null;
	}

	/**
	 * Import demo agencies and agents, and give every demo agent a matching
	 * Workspace user account — silently.
	 *
	 * Runs inside two suppression layers so the demo stays functional AND quiet:
	 *  1. {@see \HvnlyNab\Workspace\Auth\AgentProvisioner::without_auto_user_provision}
	 *     — the Agent-publish auto-provision (AgentIdentityAdminBridge FLOW A) must
	 *     not fire while agent CPTs are inserted, because their email meta is not
	 *     written until after wp_insert_post; that path would otherwise warn
	 *     "Agent published without a Workspace account". We provision explicitly
	 *     afterwards instead.
	 *  2. $GLOBALS['hvnly_suppress_workflow_notifications'] = true — so no
	 *     registration / activation / invitation email or in-app inbox row fires
	 *     (see hvnly_is_system_notification_context()).
	 *
	 * @param bool $include_images Whether to sideload profile/logo images.
	 * @return array<string, int> Agent slug => post ID map.
	 */
	public function import_ecosystem( bool $include_images = true ): array {
		$prev_suppress                                    = isset( $GLOBALS['hvnly_suppress_workflow_notifications'] )
			? $GLOBALS['hvnly_suppress_workflow_notifications']
			: null;
		$GLOBALS['hvnly_suppress_workflow_notifications'] = true;

		$run = function () use ( $include_images ) {
			$map = $this->run_import_ecosystem( $include_images );
			$this->provision_demo_agent_accounts();
			return $map;
		};

		try {
			if ( class_exists( '\HvnlyNab\Workspace\Auth\AgentProvisioner' ) ) {
				return \HvnlyNab\Workspace\Auth\AgentProvisioner::without_auto_user_provision( $run );
			}
			return $run();
		} finally {
			if ( null === $prev_suppress ) {
				unset( $GLOBALS['hvnly_suppress_workflow_notifications'] );
			} else {
				$GLOBALS['hvnly_suppress_workflow_notifications'] = $prev_suppress;
			}
		}
	}

	/**
	 * Create/backfill demo agencies and agents once per ecosystem version.
	 *
	 * @param bool $include_images Whether to sideload profile/logo images.
	 * @return array<string, int> Agent slug => post ID map.
	 */
	private function run_import_ecosystem( bool $include_images ): array {
		if ( null !== self::$runtime_agent_map ) {
			$this->backfill_demo_profiles( $include_images );
			return self::$runtime_agent_map;
		}

		$cached = get_option( self::AGENT_MAP_OPTION, array() );
		if (
			is_array( $cached )
			&& ! empty( $cached )
			&& self::ECOSYSTEM_VERSION === get_option( self::ECOSYSTEM_VERSION_KEY, '' )
		) {
			self::$runtime_agent_map = $this->sanitize_agent_map( $cached );
			$this->backfill_demo_profiles( $include_images );
			return self::$runtime_agent_map;
		}

		$this->ensure_content_types_registered();

		$agency_ids = $this->import_agencies( $include_images );
		$agent_map  = $this->import_agents( $agency_ids, $include_images );

		update_option( self::AGENT_MAP_OPTION, $agent_map, false );
		update_option( self::ECOSYSTEM_VERSION_KEY, self::ECOSYSTEM_VERSION, false );

		self::$runtime_agent_map = $agent_map;

		$this->backfill_demo_profiles( $include_images );

		return $agent_map;
	}

	/**
	 * Ensure every imported demo Agent CPT has a linked Workspace WP user so the
	 * demo is fully functional (login + dashboard) with no "missing account"
	 * warnings. Reuses {@see \HvnlyNab\Workspace\Auth\AgentProvisioner::ensure_user_for_agent}
	 * — it never duplicates user-creation logic and generates a unique username +
	 * secure temporary password itself.
	 *
	 * Idempotent: already-linked agents are skipped, so repeated import passes and
	 * cached re-runs never create duplicate users. Silent: send_reset=false and the
	 * enclosing suppression context (see import_ecosystem) keep it email-free.
	 *
	 * @return void
	 */
	private function provision_demo_agent_accounts(): void {
		if (
			! class_exists( '\HvnlyNab\Workspace\Auth\AgentProvisioner' )
			|| ! class_exists( '\HvnlyNab\Workspace\Auth\WorkspaceRegistrationStatus' )
		) {
			return;
		}

		$provisioner = new \HvnlyNab\Workspace\Auth\AgentProvisioner();
		// ACTIVE grants Workspace dashboard access immediately (PENDING would leave
		// demo agents stuck on the awaiting-approval gate).
		$status = \HvnlyNab\Workspace\Auth\WorkspaceRegistrationStatus::ACTIVE;

		foreach ( DemoAgentAgencyData::get_demo_agents() as $agent ) {
			$slug = sanitize_title( (string) ( $agent['slug'] ?? '' ) );
			if ( '' === $slug ) {
				continue;
			}

			$post = get_page_by_path( $slug, OBJECT, AgentConstants::POST_TYPE );
			if ( ! $post instanceof \WP_Post ) {
				continue;
			}
			$agent_id = (int) $post->ID;

			$linked = absint( get_post_meta( $agent_id, AgentConstants::META_LINKED_USER_ID, true ) );
			if ( $linked > 0 && get_userdata( $linked ) ) {
				continue; // Idempotent — already provisioned.
			}

			// Every demo agent ships a valid email (written to META_EMAIL on import);
			// fall back to a deterministic placeholder so provisioning never fails
			// for lack of one.
			$email = sanitize_email( (string) get_post_meta( $agent_id, AgentConstants::META_EMAIL, true ) );
			if ( '' === $email || ! is_email( $email ) ) {
				$email = $this->placeholder_agent_email( $slug );
				update_post_meta( $agent_id, AgentConstants::META_EMAIL, $email );
			}

			$result = $provisioner->ensure_user_for_agent(
				$agent_id,
				array(
					'send_reset'          => false,
					'registration_status' => $status,
				)
			);

			if ( is_wp_error( $result ) ) {
				$this->log( 'Demo agent Workspace account failed for ' . $slug . ': ' . $result->get_error_message() );
			}
		}
	}

	/**
	 * Deterministic placeholder email for a demo agent missing one.
	 *
	 * @param string $slug Agent slug.
	 * @return string
	 */
	private function placeholder_agent_email( string $slug ): string {
		$local = sanitize_key( str_replace( '-', '.', $slug ) );
		if ( '' === $local ) {
			$local = 'agent';
		}
		return $local . '@havenlytics.demo';
	}

	/**
	 * Sideload up to $limit missing agency logos and agent photos.
	 *
	 * @param int $limit Max image downloads this request.
	 * @return int Number of images sideloaded.
	 */
	public function backfill_missing_images( int $limit = 2 ): int {
		if ( $limit <= 0 ) {
			return 0;
		}

		$downloads = 0;

		foreach ( DemoAgentAgencyData::get_demo_agencies() as $agency ) {
			if ( $downloads >= $limit ) {
				break;
			}

			$slug = sanitize_title( (string) ( $agency['slug'] ?? '' ) );
			if ( '' === $slug || empty( $agency['logo_image'] ) ) {
				continue;
			}

			$term = get_term_by( 'slug', $slug, AgentConstants::TAXONOMY_AGENCY );
			if ( ! $term instanceof \WP_Term || AgencyFields::get_logo_id( (int) $term->term_id ) > 0 ) {
				continue;
			}

			$this->maybe_set_agency_logo( (int) $term->term_id, (int) $agency['logo_image'] );
			if ( AgencyFields::get_logo_id( (int) $term->term_id ) > 0 ) {
				++$downloads;
			}
		}

		foreach ( DemoAgentAgencyData::get_demo_agents() as $agent ) {
			if ( $downloads >= $limit ) {
				break;
			}

			$slug = sanitize_title( (string) ( $agent['slug'] ?? '' ) );
			if ( '' === $slug || empty( $agent['photo_image'] ) ) {
				continue;
			}

			$post = get_page_by_path( $slug, OBJECT, AgentConstants::POST_TYPE );
			if ( ! $post instanceof \WP_Post || has_post_thumbnail( (int) $post->ID ) ) {
				continue;
			}

			$this->maybe_set_agent_photo( (int) $post->ID, (int) $agent['photo_image'] );
			if ( has_post_thumbnail( (int) $post->ID ) ) {
				++$downloads;
			}
		}

		return $downloads;
	}

	/**
	 * Assign demo agents to a property by template index.
	 *
	 * @param int                $property_id    Property post ID.
	 * @param int                $property_index Demo property template index.
	 * @param array<string, int> $agent_map      Slug => post ID.
	 * @return void
	 */
	public function assign_agents_to_property( int $property_id, int $property_index, array $agent_map ): void {
		$property_id = absint( $property_id );
		if ( $property_id <= 0 || empty( $agent_map ) ) {
			return;
		}

		$slugs = DemoAgentAgencyData::get_agent_slugs_for_property_index( $property_index );
		if ( empty( $slugs ) ) {
			return;
		}

		$agent_ids = array();
		foreach ( $slugs as $slug ) {
			$slug = sanitize_title( (string) $slug );
			if ( isset( $agent_map[ $slug ] ) ) {
				$agent_ids[] = absint( $agent_map[ $slug ] );
			}
		}

		$agent_ids = array_values( array_unique( array_filter( $agent_ids ) ) );
		if ( empty( $agent_ids ) ) {
			return;
		}

		update_post_meta( $property_id, AgentConstants::META_PROPERTY_AGENTS, $agent_ids );
	}

	/**
	 * @return void
	 */
	private function ensure_content_types_registered(): void {
		if ( ! post_type_exists( AgentConstants::POST_TYPE ) && class_exists( AgentPostType::class ) ) {
			( new AgentPostType() )->register_custom_post_type();
		}

		if ( ! taxonomy_exists( AgentConstants::TAXONOMY_AGENCY ) && class_exists( AgentAgencyTaxonomy::class ) ) {
			( new AgentAgencyTaxonomy() )->register_taxonomy();
		}
	}

	/**
	 * @param bool $include_images Include logos.
	 * @return array<string, int> Agency slug => term ID.
	 */
	private function import_agencies( bool $include_images ): array {
		$map = array();

		foreach ( DemoAgentAgencyData::get_demo_agencies() as $agency ) {
			$slug = sanitize_title( (string) ( $agency['slug'] ?? '' ) );
			if ( '' === $slug ) {
				continue;
			}

			$term_id = $this->get_or_create_agency_term( $agency );
			if ( $term_id <= 0 ) {
				continue;
			}

			$this->update_agency_term_meta( $term_id, $agency );

			if ( $include_images && ! empty( $agency['logo_image'] ) ) {
				$this->maybe_set_agency_logo( $term_id, (int) $agency['logo_image'] );
			}

			$map[ $slug ] = $term_id;
		}

		return $map;
	}

	/**
	 * @param array<string, mixed> $agency Agency data.
	 * @return int Term ID or 0.
	 */
	private function get_or_create_agency_term( array $agency ): int {
		$slug = sanitize_title( (string) ( $agency['slug'] ?? '' ) );
		$name = sanitize_text_field( (string) ( $agency['name'] ?? '' ) );

		if ( '' === $slug || '' === $name ) {
			return 0;
		}

		$existing = get_term_by( 'slug', $slug, AgentConstants::TAXONOMY_AGENCY );
		if ( $existing instanceof \WP_Term ) {
			return (int) $existing->term_id;
		}

		$result = wp_insert_term(
			$name,
			AgentConstants::TAXONOMY_AGENCY,
			array(
				'slug'        => $slug,
				'description' => wp_kses_post( (string) ( $agency['description'] ?? '' ) ),
			)
		);

		if ( is_wp_error( $result ) ) {
			$this->log( 'Agency import failed for ' . $slug . ': ' . $result->get_error_message() );
			return 0;
		}

		return absint( $result['term_id'] ?? 0 );
	}

	/**
	 * @param int                  $term_id Term ID.
	 * @param array<string, mixed> $agency  Agency data.
	 * @return void
	 */
	private function update_agency_term_meta( int $term_id, array $agency ): void {
		$fields = array(
			AgencyFields::META_EMAIL   => sanitize_email( (string) ( $agency['email'] ?? '' ) ),
			AgencyFields::META_OFFICE  => sanitize_text_field( (string) ( $agency['phone'] ?? '' ) ),
			AgencyFields::META_MOBILE  => sanitize_text_field( (string) ( $agency['phone'] ?? '' ) ),
			AgencyFields::META_WEBSITE => esc_url_raw( (string) ( $agency['website'] ?? '' ) ),
			AgencyFields::META_ADDRESS => sanitize_textarea_field( (string) ( $agency['address'] ?? '' ) ),
			AgencyFields::META_LICENSE => sanitize_text_field( (string) ( $agency['license'] ?? '' ) ),
			AgencyFields::META_MAP_LAT => sanitize_text_field( (string) ( $agency['map_lat'] ?? '' ) ),
			AgencyFields::META_MAP_LNG => sanitize_text_field( (string) ( $agency['map_lng'] ?? '' ) ),
		);

		foreach ( $fields as $key => $value ) {
			if ( '' === $value ) {
				continue;
			}
			update_term_meta( $term_id, $key, $value );
		}
	}

	/**
	 * @param int $term_id     Agency term ID.
	 * @param int $image_index Demo image index.
	 * @return void
	 */
	private function maybe_set_agency_logo( int $term_id, int $image_index ): void {
		if ( AgencyFields::get_logo_id( $term_id ) > 0 ) {
			return;
		}

		$url = DemoAgentAgencyData::get_agency_logo_url( $image_index );
		if ( '' === $url ) {
			return;
		}

		$attachment_id = $this->download_image( $url, 0, 'agency-logo-' . $term_id );
		if ( $attachment_id > 0 ) {
			update_term_meta( $term_id, AgencyFields::META_LOGO_ID, $attachment_id );
		}
	}

	/**
	 * @param array<string, int> $agency_ids Agency slug => term ID.
	 * @param bool               $include_images Include photos.
	 * @return array<string, int> Agent slug => post ID.
	 */
	private function import_agents( array $agency_ids, bool $include_images ): array {
		$map = array();

		foreach ( DemoAgentAgencyData::get_demo_agents() as $agent ) {
			$slug = sanitize_title( (string) ( $agent['slug'] ?? '' ) );
			if ( '' === $slug ) {
				continue;
			}

			$post_id = $this->get_or_create_agent_post( $agent );
			if ( $post_id <= 0 ) {
				continue;
			}

			$this->update_agent_post_meta( $post_id, $agent );

			$agency_slug = sanitize_title( (string) ( $agent['agency_slug'] ?? '' ) );
			if ( '' !== $agency_slug && isset( $agency_ids[ $agency_slug ] ) ) {
				wp_set_object_terms( $post_id, array( (int) $agency_ids[ $agency_slug ] ), AgentConstants::TAXONOMY_AGENCY, false );
			}

			if ( $include_images && ! empty( $agent['photo_image'] ) ) {
				$this->maybe_set_agent_photo( $post_id, (int) $agent['photo_image'] );
			}

			$map[ $slug ] = $post_id;
		}

		return $map;
	}

	/**
	 * @param array<string, mixed> $agent Agent data.
	 * @return int Post ID or 0.
	 */
	private function get_or_create_agent_post( array $agent ): int {
		$slug = sanitize_title( (string) ( $agent['slug'] ?? '' ) );
		$name = sanitize_text_field( (string) ( $agent['name'] ?? '' ) );

		if ( '' === $slug || '' === $name ) {
			return 0;
		}

		$existing = get_page_by_path( $slug, OBJECT, AgentConstants::POST_TYPE );
		if ( $existing instanceof \WP_Post ) {
			return (int) $existing->ID;
		}

		$experience = absint( $agent['experience'] ?? 0 );
		$bio        = (string) ( $agent['biography'] ?? '' );
		if ( $experience > 0 && '' !== $bio ) {
			/* translators: %d: years of experience. */
			$intro   = sprintf( __( '%d+ years of experience.', 'havenlytics' ), $experience ) . ' ';
			$content = $intro . $bio;
		} else {
			$content = $bio;
		}

		$post_id = wp_insert_post(
			wp_slash(
				array(
					'post_title'   => $name,
					'post_name'    => $slug,
					'post_content' => wp_kses_post( $content ),
					'post_excerpt' => wp_trim_words( wp_strip_all_tags( $content ), 28, '…' ),
					'post_status'  => 'publish',
					'post_type'    => AgentConstants::POST_TYPE,
					'post_author'  => get_current_user_id() ?: 1,
				)
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			$this->log( 'Agent import failed for ' . $slug . ': ' . $post_id->get_error_message() );
			return 0;
		}

		if ( class_exists( '\HvnlyNab\Admin\Data\DemoContentLocalizer' ) ) {
			\HvnlyNab\Admin\Data\DemoContentLocalizer::mark_demo_post( absint( $post_id ) );
		} else {
			update_post_meta( absint( $post_id ), '_hvnly_is_demo', '1' );
		}

		return absint( $post_id );
	}

	/**
	 * @param int                  $post_id Post ID.
	 * @param array<string, mixed> $agent   Agent data.
	 * @return void
	 */
	private function update_agent_post_meta( int $post_id, array $agent ): void {
		$agency_name = '';
		$agency_slug = sanitize_title( (string) ( $agent['agency_slug'] ?? '' ) );
		if ( '' !== $agency_slug ) {
			foreach ( DemoAgentAgencyData::get_demo_agencies() as $agency ) {
				if ( sanitize_title( (string) ( $agency['slug'] ?? '' ) ) === $agency_slug ) {
					$agency_name = (string) ( $agency['name'] ?? '' );
					break;
				}
			}
		}

		$meta = array(
			AgentConstants::META_POSITION => sanitize_text_field( (string) ( $agent['position'] ?? '' ) ),
			AgentConstants::META_COMPANY  => sanitize_text_field( $agency_name ),
			AgentConstants::META_EMAIL    => sanitize_email( (string) ( $agent['email'] ?? '' ) ),
			AgentConstants::META_PHONE      => sanitize_text_field( (string) ( $agent['phone'] ?? '' ) ),
			AgentConstants::META_WHATSAPP   => sanitize_text_field( (string) ( $agent['whatsapp'] ?? '' ) ),
			AgentConstants::META_LICENSE    => sanitize_text_field( (string) ( $agent['license'] ?? '' ) ),
			AgentConstants::META_FACEBOOK   => esc_url_raw( (string) ( $agent['facebook'] ?? '' ) ),
			AgentConstants::META_LINKEDIN   => esc_url_raw( (string) ( $agent['linkedin'] ?? '' ) ),
			AgentConstants::META_INSTAGRAM  => esc_url_raw( (string) ( $agent['instagram'] ?? '' ) ),
			AgentConstants::META_TWITTER    => esc_url_raw( (string) ( $agent['twitter'] ?? '' ) ),
			AgentConstants::META_YOUTUBE    => esc_url_raw( (string) ( $agent['youtube'] ?? '' ) ),
		);

		foreach ( $meta as $key => $value ) {
			if ( '' === $value ) {
				continue;
			}
			update_post_meta( $post_id, $key, $value );
		}

		if ( ! empty( $agent['availability'] ) ) {
			AgentFields::save_availability( $post_id, (string) $agent['availability'] );
		}
	}

	/**
	 * @param int $post_id     Agent post ID.
	 * @param int $image_index Demo image index.
	 * @return void
	 */
	private function maybe_set_agent_photo( int $post_id, int $image_index ): void {
		if ( has_post_thumbnail( $post_id ) ) {
			return;
		}

		$url = DemoAgentAgencyData::get_agent_photo_url( $image_index );
		if ( '' === $url ) {
			return;
		}

		$attachment_id = $this->download_image( $url, $post_id, 'agent-photo' );
		if ( $attachment_id > 0 ) {
			set_post_thumbnail( $post_id, $attachment_id );
		}
	}

	/**
	 * Sync demo profile meta, availability, and optional media for existing records.
	 *
	 * @param bool $include_images Whether to sideload missing photos/logos.
	 * @return void
	 */
	private function backfill_demo_profiles( bool $include_images ): void {
		foreach ( DemoAgentAgencyData::get_demo_agencies() as $agency ) {
			$slug = sanitize_title( (string) ( $agency['slug'] ?? '' ) );
			if ( '' === $slug ) {
				continue;
			}

			$term = get_term_by( 'slug', $slug, AgentConstants::TAXONOMY_AGENCY );
			if ( ! $term instanceof \WP_Term ) {
				continue;
			}

			$this->update_agency_term_meta( (int) $term->term_id, $agency );

			if ( $include_images && ! empty( $agency['logo_image'] ) ) {
				$this->maybe_set_agency_logo( (int) $term->term_id, (int) $agency['logo_image'] );
			}
		}

		foreach ( DemoAgentAgencyData::get_demo_agents() as $agent ) {
			$slug = sanitize_title( (string) ( $agent['slug'] ?? '' ) );
			if ( '' === $slug ) {
				continue;
			}

			$post = get_page_by_path( $slug, OBJECT, AgentConstants::POST_TYPE );
			if ( ! $post instanceof \WP_Post ) {
				continue;
			}

			$this->update_agent_post_meta( (int) $post->ID, $agent );

			if ( $include_images && ! empty( $agent['photo_image'] ) ) {
				$this->maybe_set_agent_photo( (int) $post->ID, (int) $agent['photo_image'] );
			}
		}
	}

	/**
	 * @param string $url       Image URL.
	 * @param int    $parent_id Parent post ID (0 for agency logos).
	 * @param string $suffix    Filename suffix.
	 * @return int Attachment ID or 0.
	 */
	private function download_image( string $url, int $parent_id, string $suffix ): int {
		if ( $this->image_downloader ) {
			$result = call_user_func( $this->image_downloader, $url, $parent_id, $suffix );
			if ( is_numeric( $result ) && (int) $result > 0 ) {
				return (int) $result;
			}
		}

		return $this->download_image_direct( $url, $parent_id, $suffix );
	}

	/**
	 * Fallback sideload when the wizard downloader is unavailable or fails.
	 *
	 * @param string $url       Image URL.
	 * @param int    $parent_id Parent post ID (0 for agency logos).
	 * @param string $suffix    Filename suffix.
	 * @return int Attachment ID or 0.
	 */
	private function download_image_direct( string $url, int $parent_id, string $suffix ): int {
		$url = esc_url_raw( $url );
		if ( '' === $url ) {
			return 0;
		}

		// Skip the remote fetch when there is no outbound HTTP so an unreachable
		// host cannot stall the batch; agents/agencies import without a photo.
		if ( function_exists( 'hvnly_import_remote_media_available' ) && ! hvnly_import_remote_media_available() ) {
			return 0;
		}

		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		// 15s for consistency with the rest of the importer (was 20s).
		$temp_file = download_url( $url, 15, true );
		if ( is_wp_error( $temp_file ) || ! is_string( $temp_file ) || ! file_exists( $temp_file ) ) {
			if ( is_string( $temp_file ) && file_exists( $temp_file ) ) {
				wp_delete_file( $temp_file );
			}
			$this->log( 'Demo image download failed: ' . ( is_wp_error( $temp_file ) ? $temp_file->get_error_message() : $url ) );
			return 0;
		}

		$file_array = array(
			'name'     => sanitize_file_name( 'hvnly-demo-' . $suffix . '-' . uniqid() . '.jpg' ),
			'tmp_name' => $temp_file,
		);

		$attachment_id = media_handle_sideload( $file_array, $parent_id );

		if ( file_exists( $temp_file ) ) {
			wp_delete_file( $temp_file );
		}

		if ( is_wp_error( $attachment_id ) ) {
			$this->log( 'Demo image sideload failed: ' . $attachment_id->get_error_message() );
			return 0;
		}

		return absint( $attachment_id );
	}

	/**
	 * @param array<string, mixed> $map Raw map.
	 * @return array<string, int>
	 */
	private function sanitize_agent_map( array $map ): array {
		$clean = array();
		foreach ( $map as $slug => $id ) {
			$slug = sanitize_title( (string) $slug );
			$id   = absint( $id );
			if ( '' !== $slug && $id > 0 ) {
				$clean[ $slug ] = $id;
			}
		}
		return $clean;
	}

	/**
	 * @param string $message Log message.
	 * @return void
	 */
	private function log( string $message ): void {
		if ( $this->log_callback ) {
			call_user_func( $this->log_callback, $message );
		}
	}
}
