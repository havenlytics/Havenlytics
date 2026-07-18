<?php
/**
 * Workspace shortcode — mounts the React shell (or unavailable UI).
 *
 * Shortcode: [hvnly_agent_dashboard]
 *
 * @package HvnlyNab\Workspace
 * @since   3.2.0
 */

namespace HvnlyNab\Workspace;

defined( 'ABSPATH' ) || exit;

/**
 * Registers and renders the Agent Dashboard shortcode.
 *
 * @since 3.2.0
 */
final class WorkspaceShortcode {

	/**
	 * Whether the shortcode rendered on this request.
	 *
	 * @var bool
	 */
	private static $rendered = false;

	/**
	 * @var WorkspaceTemplateLoader
	 */
	private $templates;

	/**
	 * @param WorkspaceTemplateLoader $templates Template loader.
	 */
	public function __construct( WorkspaceTemplateLoader $templates ) {
		$this->templates = $templates;
	}

	/**
	 * Register the shortcode.
	 *
	 * @return void
	 */
	public function register(): void {
		add_shortcode( WorkspaceConstants::SHORTCODE, array( $this, 'render' ) );
	}

	/**
	 * Whether the shortcode has rendered this request.
	 *
	 * @return bool
	 */
	public static function has_rendered(): bool {
		return self::$rendered;
	}

	/**
	 * Render mount markup — always a real UI, never an empty string.
	 *
	 * When Workspace is disabled ({@see WorkspaceAvailability}), every URL under
	 * /agent-dashboard/* renders the same server-side unavailable page. The SPA
	 * script is NOT enqueued, so no React route can bypass the feature flag.
	 *
	 * @param array|string $atts Shortcode attributes.
	 * @return string
	 */
	public function render( $atts = array() ): string {
		unset( $atts );

		self::$rendered = true;

		/**
		 * Request asset enqueue as early as possible during content render.
		 *
		 * @since 3.2.0
		 */
		do_action( 'hvnly_workspace_enqueue_assets' );

		ob_start();

		if ( ! WorkspaceAvailability::is_available() ) {
			$this->render_unavailable();
		} else {
			$loaded = $this->templates->load(
				'dashboard',
				array(
					'mount_id' => WorkspaceConstants::MOUNT_ID,
				)
			);

			if ( ! $loaded ) {
				printf(
					'<div id="%1$s" class="hvnly-ws-mount" data-hvnly-ws-mount="1"></div>',
					esc_attr( WorkspaceConstants::MOUNT_ID )
				);
			}
		}

		/**
		 * Filter shortcode HTML output.
		 *
		 * @since 3.2.0
		 *
		 * @param string $html Output HTML.
		 */
		return (string) apply_filters( 'hvnly_workspace_shortcode_output', (string) ob_get_clean() );
	}

	/**
	 * Branded "Workspace unavailable" markup (server-side only — no SPA).
	 *
	 * @return void
	 */
	private function render_unavailable(): void {
		$copy = WorkspaceAvailability::unavailable_copy();
		$icon = defined( 'HVNLYNAB_ASSETS_URL' )
			? trailingslashit( HVNLYNAB_ASSETS_URL ) . 'admin/img/havenlytics-icon.svg'
			: '';

		$args = array(
			'mount_id'        => WorkspaceConstants::MOUNT_ID,
			'title'           => $copy['title'],
			'description'     => $copy['description'],
			'home_url'        => home_url( '/' ),
			'home_label'      => $copy['home_label'],
			'contact_label'   => $copy['contact_label'],
			'logout_label'    => $copy['logout_label'],
			'support_label'   => $copy['support_label'],
			'support_email'   => WorkspaceAvailability::support_email(),
			'logout_url'      => is_user_logged_in() ? wp_logout_url( home_url( '/' ) ) : '',
			'icon_url'        => $icon,
			'brand_name'      => __( 'Havenlytics', 'havenlytics' ),
			'workspace_label' => __( 'Workspace', 'havenlytics' ),
		);

		/**
		 * Filter args for the Workspace unavailable template.
		 *
		 * @since 3.3.0
		 *
		 * @param array<string, mixed> $args Template args.
		 */
		$args = (array) apply_filters( 'hvnly_workspace_unavailable_template_args', $args );

		$loaded = $this->templates->load( 'unavailable', $args );

		if ( ! $loaded ) {
			printf(
				'<div id="%1$s" class="hvnly-ws-mount" data-hvnly-ws-mount="1" data-hvnly-ws-unavailable="1"><p>%2$s</p><p><a href="%3$s">%4$s</a></p></div>',
				esc_attr( WorkspaceConstants::MOUNT_ID ),
				esc_html( (string) ( $args['title'] ?? $copy['title'] ) ),
				esc_url( (string) ( $args['home_url'] ?? home_url( '/' ) ) ),
				esc_html( (string) ( $args['home_label'] ?? $copy['home_label'] ) )
			);
		}
	}
}
