<?php
/**
 * Property Agent sidebar widget renderer.
 *
 * @package HvnlyNab\Agent
 * @since   3.0.2
 */

namespace HvnlyNab\Agent;

defined( 'ABSPATH' ) || exit;

/**
 * Resolves agent data and renders the property agent widget frontend.
 *
 * @since 3.0.2
 */
final class PropertyAgentWidgetRenderer {

	/**
	 * Default sidebar widget instance values.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_sidebar_defaults(): array {
		return array(
			'title'         => __( 'Contact Agent', 'havenlytics' ),
			'show_phone'    => '1',
			'show_email'    => '1',
			'show_whatsapp' => '1',
			'show_social'   => '1',
		);
	}

	/**
	 * @deprecated 3.0.2 Use get_sidebar_defaults().
	 * @return array<string, mixed>
	 */
	public static function get_defaults(): array {
		return self::get_sidebar_defaults();
	}

	/**
	 * Resolve agents for the sidebar widget (assigned CPT agents or site admin fallback).
	 *
	 * @param int $property_id Property ID.
	 * @return array<int, array<string, mixed>>
	 */
	public static function resolve_sidebar_agents( int $property_id ): array {
		$agents = function_exists( 'hvnly_get_sidebar_property_agents' )
			? hvnly_get_sidebar_property_agents( $property_id )
			: array();

		if ( ! empty( $agents ) ) {
			return $agents;
		}

		$fallback = function_exists( 'hvnly_get_default_sidebar_contact' )
			? hvnly_get_default_sidebar_contact()
			: array();

		return ! empty( $fallback ) ? array( $fallback ) : array();
	}

	/**
	 * Render sidebar agent card.
	 *
	 * @param int                  $property_id Property ID.
	 * @param array<string, mixed> $instance    Widget instance.
	 * @param string               $widget_id   Unique widget DOM id.
	 * @param array<int, array<string, mixed>> $agents Pre-resolved agents.
	 * @return void
	 */
	public static function render_sidebar( int $property_id, array $instance, string $widget_id, array $agents ): void {
		if ( empty( $agents ) ) {
			return;
		}

		self::enqueue_assets( 'sidebar', false );

		if ( function_exists( 'hvnly_get_template_part' ) ) {
			hvnly_get_template_part(
				'single-property/partials/property-agent-sidebar',
				null,
				array(
					'property_id' => $property_id,
					'instance'    => $instance,
					'agents'      => $agents,
					'widget_id'   => $widget_id,
				)
			);
		}
	}

	/**
	 * Resolve agents for widget display.
	 *
	 * @deprecated 3.0.2 Use resolve_sidebar_agents().
	 * @param int                  $property_id Property ID.
	 * @param array<string, mixed> $instance    Widget instance.
	 * @return array<int, array<string, mixed>>
	 */
	public static function resolve_agents( int $property_id, array $instance ): array {
		unset( $instance );

		return self::resolve_sidebar_agents( $property_id );
	}

	/**
	 * Render widget body.
	 *
	 * @deprecated 3.0.2 Use render_sidebar().
	 * @param int                  $property_id Property ID.
	 * @param array<string, mixed> $instance    Widget instance.
	 * @param string               $widget_id   Unique widget DOM id.
	 * @return void
	 */
	public static function render( int $property_id, array $instance, string $widget_id ): void {
		$agents = self::resolve_sidebar_agents( $property_id );
		self::render_sidebar( $property_id, $instance, $widget_id, $agents );
	}

	/**
	 * Whether the sidebar agent widget should be hidden for this property.
	 *
	 * @param int $property_id Property ID.
	 * @return bool
	 */
	public static function should_hide_sidebar_widget( int $property_id ): bool {
		if ( $property_id <= 0 ) {
			return false;
		}

		return '1' === (string) get_post_meta( $property_id, AgentConstants::META_HIDE_SIDEBAR_AGENT_WIDGET, true );
	}

	/**
	 * Enqueue assets for the main-content agents section.
	 *
	 * Styles live in hvnly-frontend-property-single.css (loaded on single property pages).
	 *
	 * @param bool $include_contact_form Whether to load contact form assets.
	 * @return void
	 */
	public static function enqueue_section_assets( bool $include_contact_form = true ): void {
		$script_handle = 'hvnly-frontend-agents-section-js';

		// Clear legacy style handle from earlier plugin versions (same handle name caused WP 6.9+ notices).
		wp_dequeue_style( 'hvnly-frontend-agents-section' );
		wp_deregister_style( 'hvnly-frontend-agents-section' );

		if ( ! wp_script_is( $script_handle, 'registered' ) ) {
			wp_register_script(
				$script_handle,
				HVNLYNAB_ASSETS_URL . '/frontend/js/hvnly-frontend-agents-section.js',
				array(),
				defined( 'HVNLYNAB_VERSION' ) ? HVNLYNAB_VERSION : '3.0.2',
				true
			);
		}

		wp_enqueue_script( $script_handle );

		if ( $include_contact_form && function_exists( 'hvnly_is_contact_agent_enabled' ) && hvnly_is_contact_agent_enabled() ) {
			self::ensure_contact_agent_script_registered();
			wp_enqueue_style( 'hvnly-frontend-contact-agent' );
			wp_enqueue_script( 'hvnly-frontend-contact-agent' );

			if ( function_exists( 'hvnly_localize_contact_agent_script' ) ) {
				$property_id = function_exists( 'get_the_ID' ) ? (int) get_the_ID() : 0;
				hvnly_localize_contact_agent_script( $property_id );
			}
		}
	}

	/**
	 * @param string $layout       Widget layout slug.
	 * @param bool   $needs_slider Whether classic slider JS is required.
	 * @return void
	 */
	public static function enqueue_assets( string $layout = 'sidebar', bool $needs_slider = false ): void {
		if ( ! wp_style_is( 'hvnly-frontend-property-agent-widget', 'registered' ) ) {
			wp_register_style(
				'hvnly-frontend-property-agent-widget',
				HVNLYNAB_ASSETS_URL . '/frontend/css/hvnly-frontend-property-agent-widget.css',
				array( 'hvnly-frontend-widgets' ),
				defined( 'HVNLYNAB_VERSION' ) ? HVNLYNAB_VERSION : '3.0.2'
			);
		}

		if ( ! wp_style_is( 'hvnly-frontend-widgets', 'registered' ) ) {
			wp_register_style(
				'hvnly-frontend-widgets',
				HVNLYNAB_ASSETS_URL . '/frontend/css/hvnly-frontend-widgets.css',
				array(),
				defined( 'HVNLYNAB_VERSION' ) ? HVNLYNAB_VERSION : '3.0.2'
			);
		}

		wp_enqueue_style( 'hvnly-frontend-widgets' );
		wp_enqueue_style( 'hvnly-frontend-property-agent-widget' );

		if ( ! wp_style_is( 'hvnly-frontend-cards', 'registered' ) ) {
			wp_register_style(
				'hvnly-frontend-cards',
				HVNLYNAB_ASSETS_URL . '/frontend/css/hvnly-frontend-cards.css',
				array( 'hvnly-frontend-default', 'hvnly-frontend-components' ),
				defined( 'HVNLYNAB_VERSION' ) ? HVNLYNAB_VERSION : '3.0.2'
			);
		}
		wp_enqueue_style( 'hvnly-frontend-cards' );

		if ( 'sidebar' === $layout ) {
			if ( ! wp_script_is( 'hvnly-frontend-property-agent-sidebar', 'registered' ) ) {
				wp_register_script(
					'hvnly-frontend-property-agent-sidebar',
					HVNLYNAB_ASSETS_URL . '/frontend/js/hvnly-frontend-property-agent-sidebar.js',
					array(),
					defined( 'HVNLYNAB_VERSION' ) ? HVNLYNAB_VERSION : '3.0.2',
					true
				);
			}
			wp_enqueue_script( 'hvnly-frontend-property-agent-sidebar' );

			if ( function_exists( 'hvnly_is_contact_agent_enabled' ) && hvnly_is_contact_agent_enabled() ) {
				self::ensure_contact_agent_script_registered();
				wp_enqueue_style( 'hvnly-frontend-contact-agent' );
				wp_enqueue_script( 'hvnly-frontend-contact-agent' );

				if ( function_exists( 'hvnly_localize_contact_agent_script' ) ) {
					$property_id = function_exists( 'get_the_ID' ) ? (int) get_the_ID() : 0;
					hvnly_localize_contact_agent_script( $property_id );
				}
			}
		}

		if ( $needs_slider ) {
			if ( ! wp_script_is( 'hvnly-frontend-property-agent-widget', 'registered' ) ) {
				wp_register_script(
					'hvnly-frontend-property-agent-widget',
					HVNLYNAB_ASSETS_URL . '/frontend/js/hvnly-frontend-property-agent-widget.js',
					array(),
					defined( 'HVNLYNAB_VERSION' ) ? HVNLYNAB_VERSION : '3.0.2',
					true
				);
			}

			wp_enqueue_script( 'hvnly-frontend-property-agent-widget' );
		}
	}

	/**
	 * Social URLs from an agent profile only (no widget hardcoded fallbacks).
	 *
	 * @param array<string, mixed> $agent Agent profile.
	 * @return array<string, array{url: string, icon: string, label: string}>
	 */
	public static function resolve_agent_social_links( array $agent ): array {
		$links     = array();
		$platforms = self::social_platforms();

		foreach ( $platforms as $platform => $meta ) {
			$url = '';

			if ( 'website' === $platform ) {
				$url = isset( $agent['website'] ) ? (string) $agent['website'] : '';
			} elseif ( ! empty( $agent[ $platform ] ) ) {
				$url = (string) $agent[ $platform ];
			}

			$url = esc_url_raw( trim( $url ) );
			if ( '' !== $url ) {
				$links[ $platform ] = array(
					'url'   => $url,
					'icon'  => $meta['icon'],
					'label' => $meta['label'],
				);
			}
		}

		return $links;
	}

	/**
	 * Flat URL map for agent-switcher data attributes.
	 *
	 * @param array<string, mixed> $agent Agent profile.
	 * @return array<string, string>
	 */
	public static function resolve_agent_social_url_map( array $agent ): array {
		$map   = array();
		$links = self::resolve_agent_social_links( $agent );

		foreach ( $links as $platform => $data ) {
			$map[ $platform ] = $data['url'];
		}

		return $map;
	}

	/**
	 * Resolve social URLs for an agent profile (legacy widget instance fallbacks removed).
	 *
	 * @param array<string, mixed> $agent    Agent profile.
	 * @param array<string, mixed> $instance Widget instance.
	 * @return array<string, array{url: string, icon: string, label: string}>
	 */
	public static function resolve_social_links( array $agent, array $instance ): array {
		unset( $instance );

		return self::resolve_agent_social_links( $agent );
	}

	/**
	 * Flat URL map for agent-switcher data attributes.
	 *
	 * @param array<string, mixed> $agent    Agent profile.
	 * @param array<string, mixed> $instance Widget instance.
	 * @return array<string, string>
	 */
	public static function resolve_social_url_map( array $agent, array $instance ): array {
		unset( $instance );

		return self::resolve_agent_social_url_map( $agent );
	}

	/**
	 * Social platform registry for sidebar display.
	 *
	 * @return array<string, array{icon: string, label: string}>
	 */
	public static function social_platforms(): array {
		return array(
			'facebook'  => array(
				'icon'  => 'fab fa-facebook-f',
				'label' => __( 'Facebook', 'havenlytics' ),
			),
			'twitter'   => array(
				'icon'  => 'fab fa-twitter',
				'label' => __( 'Twitter', 'havenlytics' ),
			),
			'linkedin'  => array(
				'icon'  => 'fab fa-linkedin-in',
				'label' => __( 'LinkedIn', 'havenlytics' ),
			),
			'instagram' => array(
				'icon'  => 'fab fa-instagram',
				'label' => __( 'Instagram', 'havenlytics' ),
			),
			'youtube'   => array(
				'icon'  => 'fab fa-youtube',
				'label' => __( 'YouTube', 'havenlytics' ),
			),
			'tiktok'    => array(
				'icon'  => 'fab fa-tiktok',
				'label' => __( 'TikTok', 'havenlytics' ),
			),
			'pinterest' => array(
				'icon'  => 'fab fa-pinterest-p',
				'label' => __( 'Pinterest', 'havenlytics' ),
			),
			'vimeo'     => array(
				'icon'  => 'fab fa-vimeo-v',
				'label' => __( 'Vimeo', 'havenlytics' ),
			),
			'website'   => array(
				'icon'  => 'fas fa-globe',
				'label' => __( 'Website', 'havenlytics' ),
			),
		);
	}

	/**
	 * Register Contact Agent assets when enqueued during template rendering.
	 *
	 * @return void
	 */
	private static function ensure_contact_agent_script_registered(): void {
		if ( ! wp_style_is( 'hvnly-frontend-contact-agent', 'registered' ) ) {
			wp_register_style(
				'hvnly-frontend-contact-agent',
				HVNLYNAB_ASSETS_URL . '/frontend/css/hvnly-frontend-contact-agent.css',
				array(),
				defined( 'HVNLYNAB_VERSION' ) ? HVNLYNAB_VERSION : '3.0.2'
			);
		}

		if ( ! wp_script_is( 'hvnly-frontend-contact-agent', 'registered' ) ) {
			wp_register_script(
				'hvnly-frontend-contact-agent',
				HVNLYNAB_ASSETS_URL . '/frontend/js/hvnly-frontend-contact-agent.js',
				array( 'jquery' ),
				defined( 'HVNLYNAB_VERSION' ) ? HVNLYNAB_VERSION : '3.0.2',
				true
			);
		}
	}

	/**
	 * Prevent instantiation.
	 */
	private function __construct() {}
}
