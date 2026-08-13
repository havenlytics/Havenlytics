<?php
/**
 * Mobile floating contact dock for single property pages.
 *
 * Premium fixed-bottom agent contact chrome for phones and small tablets.
 * Settings are filter-backed so a future Customizer / Settings UI can land
 * without rewriting markup or contact URL logic.
 *
 * @package Havenlytics
 * @since   3.5.0
 */

namespace HvnlyNab\Frontend;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class MobileContactDock
 */
class MobileContactDock {

	/**
	 * Style handle.
	 *
	 * @var string
	 */
	public const STYLE_HANDLE = 'hvnly-frontend-mobile-contact-dock';

	/**
	 * Script handle.
	 *
	 * @var string
	 */
	public const SCRIPT_HANDLE = 'hvnly-frontend-mobile-contact-dock';

	/**
	 * Default (filterable) dock settings — Customizer-ready.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_settings(): array {
		$defaults = array(
			'enabled'         => true,
			'hide_on_tablets' => false,
			'show_avatar'     => true,
			'show_role'       => true,
			'show_verified'   => true,
			'show_whatsapp'   => true,
			'show_sms'        => false,
			'show_website'    => true,
			'show_directions' => true,
			'button_order'    => array( 'call', 'email', 'whatsapp', 'sms', 'website', 'directions' ),
			'accent_color'    => '',
			'sticky_offset'   => 12,
			'max_width'       => 991,
		);

		/**
		 * Filter mobile contact dock settings (future Customizer / Settings UI).
		 *
		 * @since 3.5.0
		 *
		 * @param array<string, mixed> $defaults Dock settings.
		 */
		$settings = apply_filters( 'hvnly_mobile_contact_dock_settings', $defaults );

		return is_array( $settings ) ? array_merge( $defaults, $settings ) : $defaults;
	}

	/**
	 * Whether the dock should render on the current request.
	 *
	 * @return bool
	 */
	public static function should_display(): bool {
		if ( is_admin() ) {
			return false;
		}

		if ( ! is_singular( 'hvnly_property' ) ) {
			return false;
		}

		// Gutenberg / block editor canvas (when previewed via admin).
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return false;
		}

		// Elementor edit mode only (preview may still show the dock for QA).
		if ( class_exists( '\Elementor\Plugin' ) ) {
			$elementor = \Elementor\Plugin::$instance;
			if ( isset( $elementor->editor ) && method_exists( $elementor->editor, 'is_edit_mode' ) && $elementor->editor->is_edit_mode() ) {
				return false;
			}
		}

		$settings = self::get_settings();
		if ( empty( $settings['enabled'] ) ) {
			return false;
		}

		$property_id = (int) get_the_ID();
		$agent       = self::resolve_agent( $property_id );
		if ( empty( $agent['name'] ) ) {
			return false;
		}

		$actions = self::build_actions( $agent, $property_id, $settings );
		if ( empty( $actions ) ) {
			return false;
		}

		/**
		 * Final gate for the mobile contact dock.
		 *
		 * @since 3.5.0
		 *
		 * @param bool                 $display     Whether to show.
		 * @param int                  $property_id Property ID.
		 * @param array<string, mixed> $agent       Agent profile.
		 * @param array<string, mixed> $settings    Dock settings.
		 */
		return (bool) apply_filters( 'hvnly_mobile_contact_dock_should_display', true, $property_id, $agent, $settings );
	}

	/**
	 * Resolve the primary listing agent for the dock.
	 *
	 * @param int $property_id Property post ID.
	 * @return array<string, mixed>
	 */
	public static function resolve_agent( int $property_id ): array {
		$agent = array();

		if ( function_exists( 'hvnly_get_primary_property_agent' ) ) {
			$agent = hvnly_get_primary_property_agent( $property_id );
		}

		if ( ( empty( $agent ) || empty( $agent['name'] ) ) && function_exists( 'hvnly_get_property_agents' ) ) {
			$agents = hvnly_get_property_agents( $property_id );
			if ( is_array( $agents ) && ! empty( $agents[0] ) && is_array( $agents[0] ) ) {
				$agent = $agents[0];
			}
		}

		/**
		 * Filter which agent powers the mobile contact dock.
		 *
		 * @since 3.5.0
		 *
		 * @param array<string, mixed> $agent       Agent profile.
		 * @param int                  $property_id Property ID.
		 */
		$filtered = apply_filters( 'hvnly_mobile_contact_dock_agent', $agent, $property_id );

		return is_array( $filtered ) ? $filtered : array();
	}

	/**
	 * Build contact action buttons using existing URL patterns.
	 *
	 * Reuses the same tel / mailto / wa.me / maps URL rules as agent cards
	 * and sidebar quick-actions. Missing methods are omitted (no placeholders).
	 *
	 * @param array<string, mixed> $agent       Agent profile.
	 * @param int                  $property_id Property ID.
	 * @param array<string, mixed> $settings    Dock settings.
	 * @return array<int, array<string, string>>
	 */
	public static function build_actions( array $agent, int $property_id, array $settings ): array {
		$phone    = self::first_non_empty( array( $agent['phone'] ?? '', $agent['mobile'] ?? '', $agent['office'] ?? '' ) );
		$mobile   = self::first_non_empty( array( $agent['mobile'] ?? '', $agent['phone'] ?? '' ) );
		$email    = isset( $agent['email'] ) ? trim( (string) $agent['email'] ) : '';
		$whatsapp = isset( $agent['whatsapp'] ) ? trim( (string) $agent['whatsapp'] ) : '';
		$website  = isset( $agent['website'] ) ? trim( (string) $agent['website'] ) : '';

		$catalog = array();

		if ( $phone ) {
			$catalog['call'] = array(
				'id'     => 'call',
				'label'  => __( 'Call', 'havenlytics' ),
				'href'   => 'tel:' . preg_replace( '/[^0-9+]/', '', $phone ),
				'icon'   => 'fas fa-phone-alt',
				'target' => '',
				'rel'    => '',
			);
		}

		if ( $email && is_email( $email ) ) {
			$catalog['email'] = array(
				'id'     => 'email',
				'label'  => __( 'Email', 'havenlytics' ),
				'href'   => 'mailto:' . $email,
				'icon'   => 'fas fa-envelope',
				'target' => '',
				'rel'    => '',
			);
		}

		if ( ! empty( $settings['show_whatsapp'] ) && $whatsapp ) {
			$catalog['whatsapp'] = array(
				'id'     => 'whatsapp',
				'label'  => __( 'WhatsApp', 'havenlytics' ),
				'href'   => 'https://wa.me/' . preg_replace( '/[^0-9]/', '', $whatsapp ),
				'icon'   => 'fab fa-whatsapp',
				'target' => '_blank',
				'rel'    => 'noopener noreferrer',
			);
		}

		// SMS is settings-gated (future Customizer). Reuses the mobile/phone number.
		if ( ! empty( $settings['show_sms'] ) && $mobile ) {
			$catalog['sms'] = array(
				'id'     => 'sms',
				'label'  => __( 'SMS', 'havenlytics' ),
				'href'   => 'sms:' . preg_replace( '/[^0-9+]/', '', $mobile ),
				'icon'   => 'fas fa-comment-dots',
				'target' => '',
				'rel'    => '',
			);
		}

		if ( ! empty( $settings['show_website'] ) && $website ) {
			$catalog['website'] = array(
				'id'     => 'website',
				'label'  => __( 'Website', 'havenlytics' ),
				'href'   => esc_url_raw( $website ),
				'icon'   => 'fas fa-globe',
				'target' => '_blank',
				'rel'    => 'noopener noreferrer',
			);
		}

		if ( ! empty( $settings['show_directions'] ) ) {
			$coords = self::resolve_coordinates( $property_id );
			if ( ! empty( $coords['lat'] ) && ! empty( $coords['lng'] ) ) {
				$query                 = rawurlencode( $coords['lat'] . ',' . $coords['lng'] );
				$catalog['directions'] = array(
					'id'     => 'directions',
					'label'  => __( 'Directions', 'havenlytics' ),
					'href'   => 'https://www.google.com/maps/search/?api=1&query=' . $query,
					'icon'   => 'fas fa-directions',
					'target' => '_blank',
					'rel'    => 'noopener noreferrer',
				);
			}
		}

		$order   = isset( $settings['button_order'] ) && is_array( $settings['button_order'] )
			? $settings['button_order']
			: array_keys( $catalog );
		$actions = array();

		foreach ( $order as $key ) {
			$key = sanitize_key( (string) $key );
			if ( isset( $catalog[ $key ] ) ) {
				$actions[] = $catalog[ $key ];
				unset( $catalog[ $key ] );
			}
		}

		// Append any remaining (filter-added) actions not listed in order.
		foreach ( $catalog as $action ) {
			$actions[] = $action;
		}

		/**
		 * Filter dock contact actions after availability gating.
		 *
		 * @since 3.5.0
		 *
		 * @param array<int, array<string, string>> $actions     Action list.
		 * @param array<string, mixed>              $agent       Agent profile.
		 * @param int                               $property_id Property ID.
		 * @param array<string, mixed>              $settings    Dock settings.
		 */
		$filtered = apply_filters( 'hvnly_mobile_contact_dock_actions', $actions, $agent, $property_id, $settings );

		return is_array( $filtered ) ? array_values( $filtered ) : array();
	}

	/**
	 * Resolve map coordinates via the same meta path as single-property maps.
	 *
	 * @param int $property_id Property ID.
	 * @return array{lat?: string, lng?: string}
	 */
	public static function resolve_coordinates( int $property_id ): array {
		$lat = '';
		$lng = '';

		if ( function_exists( 'hvnly_resolve_field_meta' ) ) {
			$raw_lat = hvnly_resolve_field_meta(
				$property_id,
				array(
					'group_type' => 'map',
					'metaKey'    => 'latitude',
				)
			);
			$raw_lng = hvnly_resolve_field_meta(
				$property_id,
				array(
					'group_type' => 'map',
					'metaKey'    => 'longitude',
				)
			);

			if ( ! empty( $raw_lat ) && is_numeric( $raw_lat ) && ! empty( $raw_lng ) && is_numeric( $raw_lng ) ) {
				$lat = (string) $raw_lat;
				$lng = (string) $raw_lng;
			}
		}

		if ( '' === $lat || '' === $lng ) {
			$legacy_lat = get_post_meta( $property_id, '_hvnly_property_latitude', true );
			$legacy_lng = get_post_meta( $property_id, '_hvnly_property_longitude', true );
			if ( ! empty( $legacy_lat ) && is_numeric( $legacy_lat ) && ! empty( $legacy_lng ) && is_numeric( $legacy_lng ) ) {
				$lat = (string) $legacy_lat;
				$lng = (string) $legacy_lng;
			}
		}

		return array(
			'lat' => $lat,
			'lng' => $lng,
		);
	}

	/**
	 * Template data payload for the dock partial.
	 *
	 * @param int $property_id Property ID.
	 * @return array<string, mixed>|null
	 */
	public static function get_template_args( int $property_id = 0 ): ?array {
		$property_id = $property_id > 0 ? $property_id : (int) get_the_ID();
		if ( $property_id <= 0 ) {
			return null;
		}

		$settings = self::get_settings();
		$agent    = self::resolve_agent( $property_id );
		if ( empty( $agent['name'] ) ) {
			return null;
		}

		$actions = self::build_actions( $agent, $property_id, $settings );
		if ( empty( $actions ) ) {
			return null;
		}

		$agent_id = absint( $agent['id'] ?? 0 );
		$badges   = ( $agent_id && function_exists( 'hvnly_get_agent_card_badges' ) )
			? hvnly_get_agent_card_badges( $agent_id, $agent )
			: array();

		$is_verified = false;
		if ( ! empty( $settings['show_verified'] ) && is_array( $badges ) ) {
			foreach ( $badges as $badge ) {
				if ( isset( $badge['type'] ) && 'verified' === $badge['type'] ) {
					$is_verified = true;
					break;
				}
			}
		}

		$avatar = isset( $agent['avatar'] ) ? (string) $agent['avatar'] : '';
		if ( '' === $avatar && $agent_id && function_exists( 'hvnly_get_agent_avatar_url' ) ) {
			$avatar = hvnly_get_agent_avatar_url( $agent_id, 0, 96, 'thumbnail' );
		}

		$profile = isset( $agent['profile_url'] ) ? (string) $agent['profile_url'] : '';
		if ( '' === $profile && $agent_id ) {
			$profile = (string) get_permalink( $agent_id );
		}

		$max_width = ! empty( $settings['hide_on_tablets'] ) ? 767 : absint( $settings['max_width'] ?? 991 );
		if ( $max_width < 480 ) {
			$max_width = 991;
		}

		return array(
			'property_id'   => $property_id,
			'agent'         => $agent,
			'actions'       => $actions,
			'settings'      => $settings,
			'name'          => (string) $agent['name'],
			'role'          => isset( $agent['position'] ) ? (string) $agent['position'] : '',
			'avatar'        => $avatar,
			'profile_url'   => $profile,
			'is_verified'   => $is_verified,
			'max_width'     => $max_width,
			'sticky_offset' => absint( $settings['sticky_offset'] ?? 12 ),
			'accent_color'  => is_string( $settings['accent_color'] ?? null ) ? $settings['accent_color'] : '',
		);
	}

	/**
	 * Render the dock into the footer.
	 *
	 * @return void
	 */
	public static function render(): void {
		if ( ! self::should_display() ) {
			return;
		}

		$args = self::get_template_args();
		if ( null === $args ) {
			return;
		}

		if ( ! function_exists( 'hvnly_get_template_part' ) ) {
			return;
		}

		hvnly_get_template_part( 'single-property/partials/mobile-contact-dock', null, $args );
	}

	/**
	 * Whether assets should enqueue for this request.
	 *
	 * @return bool
	 */
	public static function should_enqueue_assets(): bool {
		return self::should_display();
	}

	/**
	 * First non-empty trimmed string from a list.
	 *
	 * @param array<int, mixed> $values Candidate values.
	 * @return string
	 */
	private static function first_non_empty( array $values ): string {
		foreach ( $values as $value ) {
			$value = trim( (string) $value );
			if ( '' !== $value ) {
				return $value;
			}
		}

		return '';
	}
}
