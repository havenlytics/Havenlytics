<?php
/**
 * Unified admin preloader markup (Settings + Property Builder).
 *
 * @package HvnlyNab\Admin
 * @since   3.0.4
 */

namespace HvnlyNab\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Renders the static boot preloader shown before React loads.
 *
 * @since 3.0.4
 */
final class AdminPreloader {

	public const STATIC_ID = 'hvnly-admin-static-preloader';

	/**
	 * Output shared preloader HTML.
	 *
	 * @param string $context `settings` or `builder`.
	 * @return void
	 */
	public static function render( string $context = 'admin' ): void {
		$titles = array(
			'settings'  => __( 'Settings Dashboard', 'havenlytics' ),
			'builder'   => __( 'Property Builder', 'havenlytics' ),
			'analytics' => __( 'Analytics', 'havenlytics' ),
		);

		$stages = array(
			'settings'  => __( 'Loading settings…', 'havenlytics' ),
			'builder'   => __( 'Loading configuration…', 'havenlytics' ),
			'analytics' => __( 'Loading analytics…', 'havenlytics' ),
		);

		$title = $titles[ $context ] ?? __( 'Havenlytics', 'havenlytics' );
		$stage = $stages[ $context ] ?? __( 'Loading…', 'havenlytics' );
		?>
		<div id="<?php echo esc_attr( self::STATIC_ID ); ?>" class="hvnly-admin-preloader" aria-live="polite" aria-busy="true">
			<div class="hvnly-admin-preloader__panel">
				<div class="hvnly-admin-preloader__spinner" aria-hidden="true"></div>
				<h2 class="hvnly-admin-preloader__title"><?php echo esc_html( $title ); ?></h2>
				<p class="hvnly-admin-preloader__stage"><?php echo esc_html( $stage ); ?></p>
				<div class="hvnly-admin-preloader__progress" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="38">
					<div class="hvnly-admin-preloader__progress-track">
						<div class="hvnly-admin-preloader__progress-bar" style="width: 38%;"></div>
					</div>
					<span class="hvnly-admin-preloader__progress-value">38%</span>
				</div>
			</div>
		</div>
		<?php
	}
}
