<?php
/**
 * Agent profile field registry and helpers.
 *
 * @package HvnlyNab\Agent
 * @since   3.0.2
 */

namespace HvnlyNab\Agent;

defined( 'ABSPATH' ) || exit;

/**
 * Central definition of agent post meta fields.
 *
 * @since 3.0.2
 */
final class AgentFields {

	/**
	 * Field groups for admin UI.
	 *
	 * @return array<string, array<int, array<string, mixed>>>
	 */
	public static function field_groups(): array {
		return array(
			'professional' => array(
				array(
					'key'         => AgentConstants::META_POSITION,
					'label'       => __( 'Position / Title', 'havenlytics' ),
					'type'        => 'text',
					'placeholder' => __( 'Real Estate Agent', 'havenlytics' ),
				),
				array(
					'key'         => AgentConstants::META_COMPANY,
					'label'       => __( 'Company / Brokerage', 'havenlytics' ),
					'type'        => 'text',
					'placeholder' => __( 'Brokerage or agency name', 'havenlytics' ),
				),
				array(
					'key'   => AgentConstants::META_LICENSE,
					'label' => __( 'License Number', 'havenlytics' ),
					'type'  => 'text',
				),
				array(
					'key'         => AgentConstants::META_ADDRESS,
					'label'       => __( 'Address', 'havenlytics' ),
					'type'        => 'textarea',
					'placeholder' => __( 'Street, city, state, postal code', 'havenlytics' ),
					'full_width'  => true,
				),
			),
			'contact'      => array(
				array(
					'key'         => AgentConstants::META_EMAIL,
					'label'       => __( 'Email', 'havenlytics' ),
					'type'        => 'email',
					'placeholder' => 'agent@example.com',
				),
				array(
					'key'         => AgentConstants::META_PHONE,
					'label'       => __( 'Phone', 'havenlytics' ),
					'type'        => 'tel',
					'placeholder' => '+1 (555) 123-4567',
				),
				array(
					'key'         => AgentConstants::META_MOBILE,
					'label'       => __( 'Mobile Number', 'havenlytics' ),
					'type'        => 'tel',
					'placeholder' => '+1 (555) 987-6543',
				),
				array(
					'key'         => AgentConstants::META_FAX,
					'label'       => __( 'Fax Number', 'havenlytics' ),
					'type'        => 'tel',
				),
				array(
					'key'         => AgentConstants::META_OFFICE,
					'label'       => __( 'Office Number', 'havenlytics' ),
					'type'        => 'tel',
				),
				array(
					'key'         => AgentConstants::META_WHATSAPP,
					'label'       => __( 'WhatsApp', 'havenlytics' ),
					'type'        => 'tel',
					'placeholder' => '+1234567890',
				),
				array(
					'key'         => AgentConstants::META_WEBSITE,
					'label'       => __( 'Website', 'havenlytics' ),
					'type'        => 'url',
					'placeholder' => 'https://example.com',
				),
			),
			'social'       => array(
				array( 'key' => AgentConstants::META_VIMEO, 'label' => __( 'Vimeo URL', 'havenlytics' ), 'type' => 'url' ),
				array( 'key' => AgentConstants::META_FACEBOOK, 'label' => __( 'Facebook URL', 'havenlytics' ), 'type' => 'url' ),
				array( 'key' => AgentConstants::META_TWITTER, 'label' => __( 'Twitter / X URL', 'havenlytics' ), 'type' => 'url' ),
				array( 'key' => AgentConstants::META_PINTEREST, 'label' => __( 'Pinterest URL', 'havenlytics' ), 'type' => 'url' ),
				array( 'key' => AgentConstants::META_INSTAGRAM, 'label' => __( 'Instagram URL', 'havenlytics' ), 'type' => 'url' ),
				array( 'key' => AgentConstants::META_YOUTUBE, 'label' => __( 'YouTube URL', 'havenlytics' ), 'type' => 'url' ),
				array( 'key' => AgentConstants::META_LINKEDIN, 'label' => __( 'LinkedIn URL', 'havenlytics' ), 'type' => 'url' ),
				array( 'key' => AgentConstants::META_TIKTOK, 'label' => __( 'TikTok URL', 'havenlytics' ), 'type' => 'url' ),
			),
		);
	}

	/**
	 * Group headings for metabox sections.
	 *
	 * @return array<string, string>
	 */
	public static function group_labels(): array {
		return array(
			'professional' => __( 'Professional Details', 'havenlytics' ),
			'contact'        => __( 'Contact Information', 'havenlytics' ),
			'social'         => __( 'Social Profiles', 'havenlytics' ),
		);
	}

	/**
	 * @param int $post_id Agent post ID.
	 * @return array<string, mixed>
	 */
	public static function get_profile( int $post_id ): array {
		if ( $post_id <= 0 || AgentConstants::POST_TYPE !== get_post_type( $post_id ) ) {
			return array();
		}

		$profile = array(
			'email'    => sanitize_email( (string) get_post_meta( $post_id, AgentConstants::META_EMAIL, true ) ),
			'phone'    => (string) get_post_meta( $post_id, AgentConstants::META_PHONE, true ),
			'mobile'   => (string) get_post_meta( $post_id, AgentConstants::META_MOBILE, true ),
			'fax'      => (string) get_post_meta( $post_id, AgentConstants::META_FAX, true ),
			'office'   => (string) get_post_meta( $post_id, AgentConstants::META_OFFICE, true ),
			'whatsapp' => (string) get_post_meta( $post_id, AgentConstants::META_WHATSAPP, true ),
			'position' => (string) get_post_meta( $post_id, AgentConstants::META_POSITION, true ),
			'company'  => (string) get_post_meta( $post_id, AgentConstants::META_COMPANY, true ),
			'license'  => (string) get_post_meta( $post_id, AgentConstants::META_LICENSE, true ),
			'address'  => (string) get_post_meta( $post_id, AgentConstants::META_ADDRESS, true ),
			'website'  => esc_url_raw( (string) get_post_meta( $post_id, AgentConstants::META_WEBSITE, true ) ),
			'vimeo'    => esc_url_raw( (string) get_post_meta( $post_id, AgentConstants::META_VIMEO, true ) ),
			'facebook' => esc_url_raw( (string) get_post_meta( $post_id, AgentConstants::META_FACEBOOK, true ) ),
			'twitter'  => esc_url_raw( (string) get_post_meta( $post_id, AgentConstants::META_TWITTER, true ) ),
			'pinterest'=> esc_url_raw( (string) get_post_meta( $post_id, AgentConstants::META_PINTEREST, true ) ),
			'instagram'=> esc_url_raw( (string) get_post_meta( $post_id, AgentConstants::META_INSTAGRAM, true ) ),
			'youtube'  => esc_url_raw( (string) get_post_meta( $post_id, AgentConstants::META_YOUTUBE, true ) ),
			'linkedin' => esc_url_raw( (string) get_post_meta( $post_id, AgentConstants::META_LINKEDIN, true ) ),
			'tiktok'   => esc_url_raw( (string) get_post_meta( $post_id, AgentConstants::META_TIKTOK, true ) ),
		);

		if ( '' === trim( $profile['phone'] ) ) {
			$profile['phone'] = $profile['mobile'] ?: $profile['office'];
		}

		return $profile;
	}

	/**
	 * @param int $post_id Agent post ID.
	 * @return string Availability slug.
	 */
	public static function get_availability( int $post_id ): string {
		$extensions = get_post_meta( $post_id, AgentConstants::META_EXTENSIONS, true );
		$status     = AgentConstants::AVAILABILITY_AVAILABLE;

		if ( is_array( $extensions ) && ! empty( $extensions['availability'] ) ) {
			$status = sanitize_key( (string) $extensions['availability'] );
		}

		if ( ! in_array( $status, AgentConstants::availability_statuses(), true ) ) {
			$status = AgentConstants::AVAILABILITY_AVAILABLE;
		}

		return $status;
	}

	/**
	 * @param int    $post_id Agent post ID.
	 * @param string $status  Availability slug.
	 * @return void
	 */
	public static function save_availability( int $post_id, string $status ): void {
		$status = sanitize_key( $status );
		if ( ! in_array( $status, AgentConstants::availability_statuses(), true ) ) {
			$status = AgentConstants::AVAILABILITY_AVAILABLE;
		}

		$extensions = get_post_meta( $post_id, AgentConstants::META_EXTENSIONS, true );
		if ( ! is_array( $extensions ) ) {
			$extensions = array();
		}

		$extensions['availability'] = $status;
		update_post_meta( $post_id, AgentConstants::META_EXTENSIONS, $extensions );
	}

	/**
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public static function save_availability_from_request( int $post_id ): void {
		if ( ! isset( $_POST['hvnly_agent_availability'] ) ) {
			return;
		}

		$status = sanitize_key( (string) wp_unslash( $_POST['hvnly_agent_availability'] ) );
		self::save_availability( $post_id, $status );
	}

	/**
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public static function save_from_request( int $post_id ): void {
		if ( ! function_exists( 'hvnly_current_user_can_edit_post_type' ) || ! hvnly_current_user_can_edit_post_type( $post_id, AgentConstants::POST_TYPE ) ) {
			return;
		}

		foreach ( self::field_groups() as $fields ) {
			foreach ( $fields as $field ) {
				$key  = (string) $field['key'];
				$type = (string) $field['type'];

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

				update_post_meta( $post_id, $key, $value );
			}
		}

		$linked_user = isset( $_POST[ AgentConstants::META_LINKED_USER_ID ] )
			? absint( wp_unslash( $_POST[ AgentConstants::META_LINKED_USER_ID ] ) )
			: 0;
		update_post_meta( $post_id, AgentConstants::META_LINKED_USER_ID, $linked_user );
	}

	/**
	 * Prevent instantiation.
	 */
	private function __construct() {}
}
