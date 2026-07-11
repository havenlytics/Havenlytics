<?php
/**
 * Official theme recommendation admin notice.
 *
 * @package     Havenlytics
 * @subpackage  Admin
 * @copyright   Copyright (c) 2025, Havenlytics
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       3.0.8
 */

namespace HvnlyNab\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Recommends the official Havenlytics Realty theme across wp-admin.
 *
 * @since 3.0.8
 */
class ThemeRecommendationNotice {

	/**
	 * Official theme stylesheet / template slug.
	 *
	 * @var string
	 */
	private const THEME_SLUG = 'havenlytics-realty';

	/**
	 * Per-user dismissal meta key.
	 *
	 * @var string
	 */
	private const DISMISS_USER_META = 'hvnly_dismiss_theme_notice';

	/**
	 * AJAX action name.
	 *
	 * @var string
	 */
	private const AJAX_ACTION = 'hvnly_dismiss_theme_notice';

	/**
	 * Nonce action.
	 *
	 * @var string
	 */
	private const NONCE_ACTION = 'hvnly_dismiss_theme_notice';

	/**
	 * Admin pages where the notice must not appear.
	 *
	 * @var string[]
	 */
	private const BLOCKED_PAGENOW = array(
		'customize.php',
		'site-editor.php',
		'plugin-editor.php',
		'theme-editor.php',
	);

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_notices', array( $this, 'display_notice' ) );
		add_action( 'current_screen', array( $this, 'register_on_suppressed_screen' ), 20 );
		add_action( 'wp_ajax_' . self::AJAX_ACTION, array( $this, 'ajax_dismiss' ) );
	}

	/**
	 * Re-register after other Havenlytics admin screens remove third-party notices.
	 *
	 * @param \WP_Screen $screen Current admin screen.
	 * @return void
	 */
	public function register_on_suppressed_screen( \WP_Screen $screen ): void {
		if ( ! $this->is_notice_suppressed_screen( $screen->id ) ) {
			return;
		}

		add_action( 'admin_notices', array( $this, 'display_notice' ) );
	}

	/**
	 * Display the theme recommendation notice.
	 *
	 * @return void
	 */
	public function display_notice(): void {
		if ( ! $this->should_display_notice() ) {
			return;
		}

		$theme_url = admin_url( 'theme-install.php?search=havenlytics' );
		$nonce     = wp_create_nonce( self::NONCE_ACTION );
		?>
		<div class="notice notice-info is-dismissible hvnly-theme-recommendation-notice">
			<p>
				<strong><?php esc_html_e( '🏠 Build Your Real Estate Website Faster', 'havenlytics' ); ?></strong>
			</p>
			<p>
				<?php esc_html_e( 'Havenlytics Realty is the official lightweight theme built specifically for Havenlytics.', 'havenlytics' ); ?>
			</p>
			<ul>
				<li><?php esc_html_e( '✓ One-click demo import', 'havenlytics' ); ?></li>
				<li><?php esc_html_e( '✓ Property templates', 'havenlytics' ); ?></li>
				<li><?php esc_html_e( '✓ Agent layouts', 'havenlytics' ); ?></li>
				<li><?php esc_html_e( '✓ Better integration', 'havenlytics' ); ?></li>
				<li><?php esc_html_e( '✓ Faster setup', 'havenlytics' ); ?></li>
			</ul>
			<p>
				<a href="<?php echo esc_url( $theme_url ); ?>" class="button button-primary">
					<?php esc_html_e( 'View Theme', 'havenlytics' ); ?>
				</a>
				<button type="button" class="button button-secondary hvnly-theme-notice-dismiss" data-nonce="<?php echo esc_attr( $nonce ); ?>">
					<?php esc_html_e( 'Dismiss', 'havenlytics' ); ?>
				</button>
			</p>
		</div>
		<script>
			( function () {
				var button = document.querySelector( '.hvnly-theme-notice-dismiss' );
				if ( ! button || typeof ajaxurl === 'undefined' ) {
					return;
				}

				button.addEventListener( 'click', function ( event ) {
					event.preventDefault();

					var notice = button.closest( '.hvnly-theme-recommendation-notice' );
					var formData = new window.FormData();
					formData.append( 'action', '<?php echo esc_js( self::AJAX_ACTION ); ?>' );
					formData.append( 'nonce', button.getAttribute( 'data-nonce' ) || '' );

					window.fetch( ajaxurl, {
						method: 'POST',
						credentials: 'same-origin',
						body: formData
					} ).then( function () {
						if ( notice ) {
							notice.remove();
						}
					} );
				} );
			} )();
		</script>
		<?php
	}

	/**
	 * AJAX handler for per-user dismissal.
	 *
	 * @return void
	 */
	public function ajax_dismiss(): void {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( null, 403 );
		}

		update_user_meta( get_current_user_id(), self::DISMISS_USER_META, 1 );

		wp_send_json_success();
	}

	/**
	 * Whether the notice should render.
	 *
	 * @return bool
	 */
	private function should_display_notice(): bool {
		if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
			return false;
		}

		if ( $this->is_blocked_admin_page() ) {
			return false;
		}

		if ( $this->is_official_theme_active() ) {
			return false;
		}

		return ! $this->is_notice_dismissed();
	}

	/**
	 * @return bool
	 */
	private function is_notice_dismissed(): bool {
		return (bool) get_user_meta( get_current_user_id(), self::DISMISS_USER_META, true );
	}

	/**
	 * @return bool
	 */
	private function is_official_theme_active(): bool {
		$theme = wp_get_theme();

		return self::THEME_SLUG === $theme->get_template() || self::THEME_SLUG === $theme->get_stylesheet();
	}

	/**
	 * @return bool
	 */
	private function is_blocked_admin_page(): bool {
		global $pagenow;

		return is_string( $pagenow ) && in_array( $pagenow, self::BLOCKED_PAGENOW, true );
	}

	/**
	 * Screens where Havenlytics admin UI strips other admin_notices.
	 *
	 * @param string $screen_id Screen ID.
	 * @return bool
	 */
	private function is_notice_suppressed_screen( string $screen_id ): bool {
		$suppressed = array(
			'edit-hvnly_property',
			'hvnly_property',
			'edit-hvnly_agent',
			'hvnly_agent',
			'edit-hvnly_agent_agency',
			'hvnly_agent_agency',
			'hvnly_property_page_hvnly_property_settings',
			'hvnly_property_page_hvnly_property_documentation',
			'hvnly_property_page_hvnly_property_builder',
			'hvnly_property_page_hvnly_property_reports_analytics',
			'hvnly_property_page_hvnly_inquiries',
			'toplevel_page_hvnly_property_builder',
			'toplevel_page_hvnly_property_reports_analytics',
			'toplevel_page_hvnly_inquiries',
			'hvnly_property_page_hvnly_property_cache',
			'hvnly_property_page_hvnly-property-import',
		);

		foreach ( get_object_taxonomies( 'hvnly_property', 'names' ) as $taxonomy ) {
			$suppressed[] = 'edit-' . $taxonomy;
			$suppressed[] = $taxonomy;
		}

		return in_array( $screen_id, $suppressed, true );
	}
}
