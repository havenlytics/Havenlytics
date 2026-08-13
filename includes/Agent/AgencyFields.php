<?php
/**
 * Agency taxonomy field registry and helpers.
 *
 * @package HvnlyNab\Agent
 * @since   3.0.2
 */

namespace HvnlyNab\Agent;

defined( 'ABSPATH' ) || exit;

/**
 * Central definition of agency term meta fields.
 *
 * @since 3.0.2
 */
final class AgencyFields {

	public const META_LOGO_ID      = 'hvnly_agency_logo_id';
	public const META_ADDRESS      = 'hvnly_agency_address';
	public const META_LICENSE      = 'hvnly_agency_license';
	public const META_MAP_PROVIDER = 'hvnly_agency_map_provider';
	public const META_MAP_LAT      = 'hvnly_agency_map_lat';
	public const META_MAP_LNG      = 'hvnly_agency_map_lng';
	public const META_EMAIL        = 'hvnly_agency_email';
	public const META_MOBILE       = 'hvnly_agency_mobile';
	public const META_FAX          = 'hvnly_agency_fax';
	public const META_OFFICE       = 'hvnly_agency_office';
	public const META_WEBSITE      = 'hvnly_agency_website';
	public const META_VIMEO        = 'hvnly_agency_vimeo';
	public const META_FACEBOOK     = 'hvnly_agency_facebook';
	public const META_TWITTER      = 'hvnly_agency_twitter';
	public const META_PINTEREST    = 'hvnly_agency_pinterest';
	public const META_INSTAGRAM    = 'hvnly_agency_instagram';
	public const META_YOUTUBE      = 'hvnly_agency_youtube';
	public const META_LINKEDIN     = 'hvnly_agency_linkedin';
	public const META_TIKTOK       = 'hvnly_agency_tiktok';

	/** @var string Use site map settings automatically. */
	public const MAP_PROVIDER_AUTO = 'auto';

	/**
	 * Field groups for admin UI.
	 *
	 * @return array<string, array<int, array<string, mixed>>>
	 */
	public static function field_groups(): array {
		return array(
			'profile' => array(
				array(
					'key'   => self::META_LOGO_ID,
					'label' => __( 'Agency Logo', 'havenlytics' ),
					'type'  => 'media',
				),
				array(
					'key'         => self::META_ADDRESS,
					'label'       => __( 'Address', 'havenlytics' ),
					'type'        => 'textarea',
					'placeholder' => __( 'Street, city, state, postal code', 'havenlytics' ),
				),
				array(
					'key'   => self::META_LICENSE,
					'label' => __( 'License Number', 'havenlytics' ),
					'type'  => 'text',
				),
			),
			'map'     => array(
				array(
					'key'         => self::META_MAP_LAT,
					'label'       => __( 'Latitude', 'havenlytics' ),
					'type'        => 'text',
					'placeholder' => '51.5074',
				),
				array(
					'key'         => self::META_MAP_LNG,
					'label'       => __( 'Longitude', 'havenlytics' ),
					'type'        => 'text',
					'placeholder' => '-0.1278',
				),
			),
			'contact' => array(
				array(
					'key' => self::META_EMAIL,
					'label' => __( 'Email', 'havenlytics' ),
					'type' => 'email',
				),
				array(
					'key' => self::META_MOBILE,
					'label' => __( 'Mobile Number', 'havenlytics' ),
					'type' => 'tel',
				),
				array(
					'key' => self::META_FAX,
					'label' => __( 'Fax Number', 'havenlytics' ),
					'type' => 'tel',
				),
				array(
					'key' => self::META_OFFICE,
					'label' => __( 'Office Number', 'havenlytics' ),
					'type' => 'tel',
				),
				array(
					'key' => self::META_WEBSITE,
					'label' => __( 'Website', 'havenlytics' ),
					'type' => 'url',
				),
			),
			'social'  => array(
				array(
					'key' => self::META_VIMEO,
					'label' => __( 'Vimeo URL', 'havenlytics' ),
					'type' => 'url',
				),
				array(
					'key' => self::META_FACEBOOK,
					'label' => __( 'Facebook URL', 'havenlytics' ),
					'type' => 'url',
				),
				array(
					'key' => self::META_TWITTER,
					'label' => __( 'Twitter / X URL', 'havenlytics' ),
					'type' => 'url',
				),
				array(
					'key' => self::META_PINTEREST,
					'label' => __( 'Pinterest URL', 'havenlytics' ),
					'type' => 'url',
				),
				array(
					'key' => self::META_INSTAGRAM,
					'label' => __( 'Instagram URL', 'havenlytics' ),
					'type' => 'url',
				),
				array(
					'key' => self::META_YOUTUBE,
					'label' => __( 'YouTube URL', 'havenlytics' ),
					'type' => 'url',
				),
				array(
					'key' => self::META_LINKEDIN,
					'label' => __( 'LinkedIn URL', 'havenlytics' ),
					'type' => 'url',
				),
				array(
					'key' => self::META_TIKTOK,
					'label' => __( 'TikTok URL', 'havenlytics' ),
					'type' => 'url',
				),
			),
		);
	}

	/**
	 * @return array<string, string>
	 * @deprecated 3.0.2 Per-agency map provider selection removed; site settings are always used.
	 */
	public static function map_provider_options(): array {
		return array(
			self::MAP_PROVIDER_AUTO => __( 'Site default map settings', 'havenlytics' ),
		);
	}

	/**
	 * Resolve stored logo attachment ID for an agency term (with legacy key fallback).
	 *
	 * @param int $term_id Agency term ID.
	 * @return int
	 */
	public static function get_logo_id( int $term_id ): int {
		if ( $term_id <= 0 ) {
			return 0;
		}

		$logo_id = absint( get_term_meta( $term_id, self::META_LOGO_ID, true ) );
		if ( $logo_id > 0 ) {
			return $logo_id;
		}

		return absint( get_term_meta( $term_id, 'hvnly_agency_logo', true ) );
	}

	/**
	 * Local SVG placeholder used when an agency has no uploaded logo.
	 *
	 * @return string Absolute URL or empty string.
	 */
	public static function placeholder_logo_url(): string {
		$url = '';
		if ( defined( 'HVNLYNAB_ASSETS_URL' ) ) {
			$url = trailingslashit( HVNLYNAB_ASSETS_URL ) . 'images/placeholders/agency-placeholder.svg';
		}

		/**
		 * Filter the default agency logo placeholder URL.
		 *
		 * @since 3.7.3
		 *
		 * @param string $url Placeholder logo URL.
		 */
		$filtered = (string) apply_filters( 'hvnly_agency_logo_placeholder_url', $url );

		return '' !== $filtered ? $filtered : $url;
	}

	/**
	 * Resolve the display logo URL for an agency (uploaded logo or local placeholder).
	 *
	 * @param int    $term_id Agency term ID.
	 * @param string $size    Attachment image size when a logo exists.
	 * @return string
	 */
	public static function get_logo_url( int $term_id, string $size = 'medium' ): string {
		$logo_id = self::get_logo_id( $term_id );
		if ( $logo_id > 0 ) {
			$url = wp_get_attachment_image_url( $logo_id, $size );
			if ( is_string( $url ) && '' !== $url ) {
				return $url;
			}
		}

		return self::placeholder_logo_url();
	}

	/**
	 * Resolve agency profile for an agent (taxonomy assignment, then company-name match).
	 *
	 * @param int $agent_id Agent post ID.
	 * @return array<string, mixed>
	 */
	public static function resolve_for_agent( int $agent_id ): array {
		$agent_id = absint( $agent_id );
		if ( $agent_id <= 0 ) {
			return array();
		}

		$terms = wp_get_object_terms( $agent_id, AgentConstants::TAXONOMY_AGENCY, array( 'fields' => 'all' ) );
		if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
			$term = $terms[0];
			if ( $term instanceof \WP_Term ) {
				return self::get_profile( (int) $term->term_id );
			}
		}

		$company = trim( (string) get_post_meta( $agent_id, AgentConstants::META_COMPANY, true ) );
		if ( '' === $company ) {
			return array();
		}

		$matched = get_term_by( 'name', $company, AgentConstants::TAXONOMY_AGENCY );
		if ( ! $matched instanceof \WP_Term ) {
			$matched = get_term_by( 'slug', sanitize_title( $company ), AgentConstants::TAXONOMY_AGENCY );
		}

		if ( $matched instanceof \WP_Term ) {
			return self::get_profile( (int) $matched->term_id );
		}

		return array();
	}

	/**
	 * Admin list-table logo markup for an agency term.
	 *
	 * @param int $term_id Agency term ID.
	 * @return string
	 */
	public static function get_admin_list_logo_html( int $term_id ): string {
		$logo_url = self::get_logo_url( $term_id, 'thumbnail' );

		if ( '' === $logo_url ) {
			return '&mdash;';
		}

		return sprintf(
			'<img src="%s" alt="" class="hvnly-agency-list-logo" style="max-height:40px;width:auto;border-radius:4px;" />',
			esc_url( $logo_url )
		);
	}

	/**
	 * @param int $term_id Agency term ID.
	 * @return array<string, mixed>
	 */
	public static function get_profile( int $term_id ): array {
		if ( $term_id <= 0 ) {
			return array();
		}

		$term = get_term( $term_id, AgentConstants::TAXONOMY_AGENCY );
		if ( ! $term instanceof \WP_Term ) {
			return array();
		}

		$logo_id = self::get_logo_id( $term_id );
		$profile = array(
			'id'            => (int) $term->term_id,
			'name'          => (string) $term->name,
			'slug'          => (string) $term->slug,
			'logo_id'       => $logo_id,
			'logo_url'      => self::get_logo_url( $term_id, 'medium' ),
			'address'       => (string) get_term_meta( $term_id, self::META_ADDRESS, true ),
			'license'       => (string) get_term_meta( $term_id, self::META_LICENSE, true ),
			'map_provider'  => (string) get_term_meta( $term_id, self::META_MAP_PROVIDER, true ),
			'map_lat'       => (string) get_term_meta( $term_id, self::META_MAP_LAT, true ),
			'map_lng'       => (string) get_term_meta( $term_id, self::META_MAP_LNG, true ),
			'email'         => (string) get_term_meta( $term_id, self::META_EMAIL, true ),
			'mobile'        => (string) get_term_meta( $term_id, self::META_MOBILE, true ),
			'fax'           => (string) get_term_meta( $term_id, self::META_FAX, true ),
			'office'        => (string) get_term_meta( $term_id, self::META_OFFICE, true ),
			'website'       => (string) get_term_meta( $term_id, self::META_WEBSITE, true ),
			'vimeo'         => (string) get_term_meta( $term_id, self::META_VIMEO, true ),
			'facebook'      => (string) get_term_meta( $term_id, self::META_FACEBOOK, true ),
			'twitter'       => (string) get_term_meta( $term_id, self::META_TWITTER, true ),
			'pinterest'     => (string) get_term_meta( $term_id, self::META_PINTEREST, true ),
			'instagram'     => (string) get_term_meta( $term_id, self::META_INSTAGRAM, true ),
			'youtube'       => (string) get_term_meta( $term_id, self::META_YOUTUBE, true ),
			'linkedin'      => (string) get_term_meta( $term_id, self::META_LINKEDIN, true ),
			'tiktok'        => (string) get_term_meta( $term_id, self::META_TIKTOK, true ),
			'phone'         => '',
		);

		$profile['phone']                 = $profile['mobile'] ?: $profile['office'];
		$profile['profile_url']           = self::resolve_profile_url( $term_id );
		$profile['website']               = self::sanitize_external_website( $profile['website'], $profile['profile_url'], (string) $profile['slug'] );
		$profile['resolved_map_provider'] = self::resolve_map_provider();
		$profile['has_map']               = self::has_map_coordinates( $profile );

		/**
		 * Filter normalized agency profile data.
		 *
		 * @since 3.0.2
		 *
		 * @param array<string, mixed> $profile Agency profile.
		 * @param int                  $term_id Term ID.
		 */
		return apply_filters( 'hvnly_agency_profile', $profile, $term_id );
	}

	/**
	 * @param int $term_id Agency term ID.
	 * @return string
	 */
	private static function resolve_profile_url( int $term_id ): string {
		$link = get_term_link( $term_id, AgentConstants::TAXONOMY_AGENCY );

		return is_wp_error( $link ) ? '' : (string) $link;
	}

	/**
	 * Drop website values that duplicate the on-site agency profile URL.
	 *
	 * @param string $website     Stored website URL.
	 * @param string $profile_url Canonical agency profile URL.
	 * @param string $slug        Agency term slug.
	 * @return string
	 */
	private static function sanitize_external_website( string $website, string $profile_url, string $slug ): string {
		$website = trim( $website );
		if ( '' === $website ) {
			return '';
		}

		if ( $profile_url && untrailingslashit( $website ) === untrailingslashit( $profile_url ) ) {
			return '';
		}

		$single_slug = class_exists( '\HvnlyNab\Core\PermalinkSettings' )
			? \HvnlyNab\Core\PermalinkSettings::get_agency_single_slug()
			: 'agency';

		$path = wp_parse_url( $website, PHP_URL_PATH );
		$path = is_string( $path ) ? untrailingslashit( $path ) : '';

		if ( '' !== $slug && '' !== $single_slug && preg_match( '#/' . preg_quote( $single_slug, '#' ) . '/' . preg_quote( $slug, '#' ) . '$#', $path ) ) {
			$site_host = wp_parse_url( home_url(), PHP_URL_HOST );
			$url_host  = wp_parse_url( $website, PHP_URL_HOST );

			if ( ! $url_host || $url_host === $site_host ) {
				return '';
			}
		}

		return $website;
	}

	/**
	 * @param string $stored Stored provider or auto (ignored — site default is always used).
	 * @return string
	 */
	public static function resolve_map_provider( string $stored = '' ): string {
		unset( $stored );

		if ( class_exists( '\HvnlyNab\Core\SettingsManager' ) ) {
			return \HvnlyNab\Core\SettingsManager::get_instance()->get_map_provider();
		}

		return 'leaflet';
	}

	/**
	 * Whether an agency has enough data to render a map pin.
	 *
	 * @param array<string, mixed> $profile Agency profile.
	 * @return bool
	 */
	public static function has_map_coordinates( array $profile ): bool {
		$lat = isset( $profile['map_lat'] ) ? trim( (string) $profile['map_lat'] ) : '';
		$lng = isset( $profile['map_lng'] ) ? trim( (string) $profile['map_lng'] ) : '';

		return '' !== $lat && '' !== $lng && is_numeric( $lat ) && is_numeric( $lng );
	}

	/**
	 * @param int $term_id Term ID.
	 * @return void
	 */
	public static function save_from_request( int $term_id ): void {
		$term_id = absint( $term_id );
		if ( $term_id <= 0 ) {
			return;
		}

		$tax = get_taxonomy( AgentConstants::TAXONOMY_AGENCY );
		if ( ! $tax || ! current_user_can( $tax->cap->edit_terms ) ) {
			return;
		}

		if ( isset( $_POST['hvnly_agency_term_nonce'] ) ) {
			$nonce = sanitize_text_field( wp_unslash( (string) $_POST['hvnly_agency_term_nonce'] ) );
			if ( ! wp_verify_nonce( $nonce, 'hvnly_agency_term_save' ) ) {
				return;
			}
		}

		self::save_logo_from_request( $term_id );

		foreach ( self::field_groups() as $fields ) {
			foreach ( $fields as $field ) {
				$key  = (string) $field['key'];
				$type = (string) $field['type'];

				if ( 'media' === $type || self::META_LOGO_ID === $key ) {
					continue;
				}

				if ( ! isset( $_POST[ $key ] ) ) {
					continue;
				}

				$raw = wp_unslash( $_POST[ $key ] );

				switch ( $type ) {
					case 'email':
						$value = sanitize_email( (string) $raw );
						break;
					case 'url':
						$value = esc_url_raw( (string) $raw );
						break;
					case 'textarea':
						$value = sanitize_textarea_field( (string) $raw );
						break;
					default:
						$value = sanitize_text_field( (string) $raw );
				}

				update_term_meta( $term_id, $key, $value );
			}
		}
	}

	/**
	 * @param int $term_id Term ID.
	 * @return void
	 */
	private static function save_logo_from_request( int $term_id ): void {
		$key = self::META_LOGO_ID;

		if ( ! isset( $_POST[ $key ] ) ) {
			return;
		}

		$raw     = wp_unslash( $_POST[ $key ] );
		$logo_id = absint( $raw );

		if ( $logo_id > 0 ) {
			update_term_meta( $term_id, $key, $logo_id );
			delete_term_meta( $term_id, 'hvnly_agency_logo' );
			return;
		}

		if ( '' === (string) $raw || '0' === (string) $raw ) {
			delete_term_meta( $term_id, $key );
			delete_term_meta( $term_id, 'hvnly_agency_logo' );
		}
	}

	/**
	 * Prevent instantiation.
	 */
	private function __construct() {}
}
