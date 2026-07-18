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
	 * Constructor.
	 *
	 * Sprint 31G: the recommendation used to render on every admin screen
	 * (and even re-register itself on screens that strip other notices),
	 * which read as repetitive advertising. It is now a single, one-time
	 * nudge shown ONLY on the main WordPress Dashboard, and only while the
	 * official theme is not installed. The persistent, non-intrusive
	 * discovery surface is the integrated card in the Settings sidebar.
	 */
	public function __construct() {
		add_action( 'admin_notices', array( $this, 'display_notice' ) );
		add_action( 'wp_ajax_' . self::AJAX_ACTION, array( $this, 'ajax_dismiss' ) );
	}

	/**
	 * Official theme stylesheet slug (public accessor for the Settings card).
	 *
	 * @return string
	 */
	public static function get_theme_slug(): string {
		return self::THEME_SLUG;
	}

	/**
	 * Resolve the current state of the official theme.
	 *
	 * @return string One of 'active', 'installed', 'not_installed'.
	 */
	public static function get_theme_state(): string {
		$theme = wp_get_theme();

		if ( self::THEME_SLUG === $theme->get_template() || self::THEME_SLUG === $theme->get_stylesheet() ) {
			return 'active';
		}

		$installed = wp_get_theme( self::THEME_SLUG );
		if ( $installed instanceof \WP_Theme && $installed->exists() ) {
			return 'installed';
		}

		return 'not_installed';
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
	 * Whether the one-time dashboard notice should render.
	 *
	 * Display rules (Sprint 31G):
	 *  - Only on the main WordPress Dashboard (index.php).
	 *  - Only when the official theme is NOT installed. If it is installed
	 *    (active or inactive) the Settings-sidebar card handles discovery,
	 *    so the notice never nags.
	 *  - Only until dismissed; dismissal is permanent (per-user meta).
	 *
	 * @return bool
	 */
	private function should_display_notice(): bool {
		if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
			return false;
		}

		if ( ! $this->is_dashboard_screen() ) {
			return false;
		}

		if ( 'not_installed' !== self::get_theme_state() ) {
			return false;
		}

		return ! $this->is_notice_dismissed();
	}

	/**
	 * True only on the main WordPress Dashboard (index.php).
	 *
	 * @return bool
	 */
	private function is_dashboard_screen(): bool {
		global $pagenow;

		return is_string( $pagenow ) && 'index.php' === $pagenow;
	}

	/**
	 * @return bool
	 */
	private function is_notice_dismissed(): bool {
		return (bool) get_user_meta( get_current_user_id(), self::DISMISS_USER_META, true );
	}
}
