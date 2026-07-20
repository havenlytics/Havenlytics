<?php
/**
 * Centralized Workspace Availability Guard.
 *
 * Single gate for the “Enable Workspace” feature flag. Every Workspace surface
 * (SPA mount, REST under hvnly/v1, future modules) must defer to this class —
 * do not scatter ad-hoc is_enabled() bypasses.
 *
 * @package HvnlyNab\Workspace
 * @since   3.3.0
 */

namespace HvnlyNab\Workspace;

use WP_Error;
use WP_REST_Request;

defined( 'ABSPATH' ) || exit;

/**
 * Global feature toggle for the Agent Workspace.
 *
 * @since 3.3.0
 */
final class WorkspaceAvailability {

	/**
	 * REST error code when Workspace is offline.
	 *
	 * @var string
	 */
	public const REST_ERROR_CODE = 'hvnly_workspace_disabled';

	/**
	 * Whether hooks are registered.
	 *
	 * @var bool
	 */
	private static $hooks_registered = false;

	/**
	 * Whether the Agent Workspace feature is online.
	 *
	 * @return bool
	 */
	public static function is_available(): bool {
		return WorkspaceSettings::is_enabled();
	}

	/**
	 * Register global enforcement hooks (idempotent).
	 *
	 * @return void
	 */
	public static function register_hooks(): void {
		if ( self::$hooks_registered ) {
			return;
		}
		self::$hooks_registered = true;

		// Kill every Workspace REST request before controllers run.
		add_filter( 'rest_pre_dispatch', array( __CLASS__, 'filter_rest_pre_dispatch' ), 5, 3 );

		// Hard front-end gate: every Workspace page request (including deep
		// History routes) must render the unavailable UI when offline. The
		// shortcode is the renderer; this hook only ensures we never skip
		// detection if theme/content quirks omit the shortcode output path.
		add_action( 'template_redirect', array( __CLASS__, 'guard_front_end' ), 0 );
	}

	/**
	 * Front-end guard for every Workspace page request.
	 *
	 * Deep links (`/agent-dashboard/profile`, etc.) resolve to the same WP page.
	 * When Workspace is offline we force a full-bleed canvas + ensure the
	 * unavailable shortcode path runs (never a theme blank or stale SPA shell).
	 *
	 * @return void
	 */
	public static function guard_front_end(): void {
		if ( self::is_available() ) {
			return;
		}

		$bootstrap = WorkspaceBootstrap::get_instance();
		$page      = $bootstrap->get_page();
		if ( ! $page || ! $page->is_workspace_page() ) {
			return;
		}

		// Mark request so assets / shortcode share one unavailable mode.
		if ( ! defined( 'HVNLY_WORKSPACE_UNAVAILABLE_REQUEST' ) ) {
			define( 'HVNLY_WORKSPACE_UNAVAILABLE_REQUEST', true );
		}

		status_header( 200 );
		nocache_headers();
	}

	/**
	 * Short-circuit Workspace REST when the feature flag is off.
	 *
	 * Scope: only {@see WorkspaceConstants::REST_NAMESPACE} (`hvnly/v1`).
	 * Admin APIs under `hvnlynab/v1` are untouched.
	 *
	 * @param mixed           $result  Response to replace the request (null = continue).
	 * @param \WP_REST_Server $server  Server instance.
	 * @param WP_REST_Request $request Request.
	 * @return mixed|WP_Error
	 */
	public static function filter_rest_pre_dispatch( $result, $server, $request ) {
		unset( $server );

		if ( self::is_available() ) {
			return $result;
		}

		if ( ! $request instanceof WP_REST_Request ) {
			return $result;
		}

		if ( ! self::is_workspace_rest_request( $request ) ) {
			return $result;
		}

		return self::unavailable_error();
	}

	/**
	 * Whether a REST request targets the Workspace API namespace.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return bool
	 */
	public static function is_workspace_rest_request( WP_REST_Request $request ): bool {
		$route = (string) $request->get_route();
		$ns    = '/' . trim( WorkspaceConstants::REST_NAMESPACE, '/' );

		/**
		 * Filter whether a route inside the Workspace namespace is exempt from
		 * the availability gate.
		 *
		 * Some features share `hvnly/v1` but are not Workspace surfaces — for
		 * example saved properties, which must keep working on the public
		 * archive after an administrator disables the Agent Workspace.
		 *
		 * @since 3.4.0
		 *
		 * @param bool   $exempt Whether the route bypasses the kill switch.
		 * @param string $route  REST route being dispatched.
		 */
		if ( (bool) apply_filters( 'hvnly_workspace_rest_exempt_route', false, $route ) ) {
			return false;
		}

		if ( $route === $ns || 0 === strpos( $route, $ns . '/' ) ) {
			return true;
		}

		// Fallback: attribute namespace when route string is atypical.
		$attrs = $request->get_attributes();
		if ( isset( $attrs['namespace'] ) && WorkspaceConstants::REST_NAMESPACE === $attrs['namespace'] ) {
			return true;
		}

		return false;
	}

	/**
	 * Standard WP_Error for disabled Workspace.
	 *
	 * @return WP_Error
	 */
	public static function unavailable_error(): WP_Error {
		return new WP_Error(
			self::REST_ERROR_CODE,
			__( 'The Agent Workspace is currently unavailable. The site administrator has disabled this feature.', 'havenlytics' ),
			array( 'status' => 403 )
		);
	}

	/**
	 * Shared copy for the unavailable UI (PHP + SPA).
	 *
	 * @return array{title: string, description: string, home_label: string, contact_label: string, logout_label: string, support_label: string}
	 */
	public static function unavailable_copy(): array {
		return array(
			'title'         => __( 'Agent Workspace is currently unavailable', 'havenlytics' ),
			'description'   => __( 'The site administrator has temporarily disabled the Agent Workspace. If you believe this is an error, please contact the site administrator.', 'havenlytics' ),
			'home_label'    => __( 'Return to Homepage', 'havenlytics' ),
			'contact_label' => __( 'Contact Administrator', 'havenlytics' ),
			'logout_label'  => __( 'Sign Out', 'havenlytics' ),
			'support_label' => __( 'Need help?', 'havenlytics' ),
		);
	}

	/**
	 * Support email for the unavailable page (WordPress admin email).
	 *
	 * @return string
	 */
	public static function support_email(): string {
		$email = sanitize_email( (string) get_option( 'admin_email', '' ) );
		return is_email( $email ) ? $email : '';
	}
}
