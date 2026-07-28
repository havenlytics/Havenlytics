<?php
/**
 * Ensures the /agent-dashboard/ WordPress page exists.
 *
 * @package HvnlyNab\Workspace
 * @since   3.2.0
 */

namespace HvnlyNab\Workspace;

defined( 'ABSPATH' ) || exit;

/**
 * Workspace page registrar.
 *
 * @since 3.2.0
 */
final class WorkspacePage {

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'init', array( $this, 'register_rewrite_rules' ), 10 );
		add_action( 'init', array( $this, 'maybe_ensure_page' ), 30 );
		add_action( 'template_redirect', array( $this, 'maybe_redirect_admins_to_admin' ), 1 );
		add_filter( 'query_vars', array( $this, 'register_query_vars' ) );
		add_filter( 'redirect_canonical', array( $this, 'prevent_subpath_canonical_redirect' ), 10, 2 );
		add_filter( 'template_include', array( $this, 'maybe_use_canvas_template' ), 99 );
		add_filter( 'body_class', array( $this, 'body_class' ) );
	}

	/**
	 * Query var carrying the Workspace SPA sub-route (e.g. `dashboard`, `property/5/edit`).
	 *
	 * @var string
	 */
	private const ROUTE_QUERY_VAR = 'hvnly_ws_route';

	/**
	 * Option storing the slug the catch-all rewrite was last registered/flushed for.
	 *
	 * @var string
	 */
	private const ROUTES_OPTION = 'hvnly_workspace_routes_slug';

	/**
	 * Register the catch-all rewrite so every `/{slug}/<sub-path>` serves the
	 * Workspace page — the foundation for clean History-API deep links that never
	 * 404 on refresh / direct visit / bookmark.
	 *
	 * Registered even when Workspace is disabled so deep links still resolve to
	 * the page (which then shows the unavailable UI) instead of a 404.
	 *
	 * The bare `/{slug}/` is intentionally NOT matched here (WordPress's own page
	 * rule already resolves it); only non-empty sub-paths are captured. Uses
	 * `pagename` (not a page_id) so it is correct regardless of when the page row
	 * is created. A one-time deferred flush (reusing RewriteRulesManager's safe
	 * pattern) bakes the rule, re-flushing only when the slug changes.
	 *
	 * @return void
	 */
	public function register_rewrite_rules(): void {
		$slug = WorkspaceSettings::get_page_slug();
		if ( $slug === '' ) {
			return;
		}

		self::add_catch_all_rewrite_rule( $slug );

		// ROUTES_OPTION alone is not enough — a flush can bake CPT rules while
		// wiping this catch-all. Re-schedule when the rule is missing from options.
		if ( ! self::catch_all_rewrite_is_baked( $slug ) ) {
			update_option( self::ROUTES_OPTION, $slug, false );
			if ( class_exists( '\HvnlyNab\Core\RewriteRulesManager' ) ) {
				\HvnlyNab\Core\RewriteRulesManager::schedule_flush();
			}
		}
	}

	/**
	 * Register the Workspace History-API catch-all rewrite (in-memory).
	 *
	 * Safe to call after WP_Rewrite::init() during a deferred flush so the rule
	 * is included in the persisted rewrite_rules option.
	 *
	 * @param string|null $slug Page slug override; defaults to settings slug.
	 * @return void
	 */
	public static function add_catch_all_rewrite_rule( ?string $slug = null ): void {
		$slug = is_string( $slug ) && $slug !== '' ? $slug : WorkspaceSettings::get_page_slug();
		if ( $slug === '' ) {
			return;
		}

		add_rewrite_rule(
			'^' . $slug . '/(.+?)/?$',
			'index.php?pagename=' . $slug . '&' . self::ROUTE_QUERY_VAR . '=$matches[1]',
			'top'
		);
	}

	/**
	 * Whether the catch-all rule is present in the persisted rewrite_rules option.
	 *
	 * @param string $slug Workspace page slug.
	 * @return bool
	 */
	private static function catch_all_rewrite_is_baked( string $slug ): bool {
		if ( (string) get_option( self::ROUTES_OPTION, '' ) !== $slug ) {
			return false;
		}

		$rules = get_option( 'rewrite_rules' );
		if ( ! is_array( $rules ) ) {
			return false;
		}

		$pattern = '^' . $slug . '/(.+?)/?$';
		return isset( $rules[ $pattern ] );
	}

	/**
	 * Allow the Workspace sub-route query var.
	 *
	 * @param string[] $vars Registered public query vars.
	 * @return string[]
	 */
	public function register_query_vars( array $vars ): array {
		$vars[] = self::ROUTE_QUERY_VAR;
		return $vars;
	}

	/**
	 * Never canonical-redirect a Workspace sub-path back to the bare page URL.
	 *
	 * Core `redirect_canonical` would 301 `/{slug}/dashboard` to `/{slug}/` (the
	 * page's canonical permalink), destroying the deep link. Short-circuit only
	 * for our sub-path requests, identified by the presence of the route query var.
	 *
	 * @param string|false $redirect_url  Proposed canonical URL.
	 * @param string       $requested_url Requested URL.
	 * @return string|false
	 */
	public function prevent_subpath_canonical_redirect( $redirect_url, $requested_url ) {
		unset( $requested_url );

		if ( '' !== (string) get_query_var( self::ROUTE_QUERY_VAR ) ) {
			return false;
		}

		return $redirect_url;
	}

	/**
	 * Create the Workspace page when the feature is enabled.
	 *
	 * @return void
	 */
	public function maybe_ensure_page(): void {
		if ( ! WorkspaceSettings::is_enabled() ) {
			return;
		}

		/**
		 * Filter whether to auto-create the Workspace page.
		 *
		 * @since 3.2.0
		 *
		 * @param bool $create Default true when Workspace is enabled.
		 */
		if ( ! (bool) apply_filters( 'hvnly_workspace_ensure_page', true ) ) {
			return;
		}

		$this->ensure_page();
	}

	/**
	 * Ensure a published page with the Workspace shortcode exists.
	 *
	 * @return int Page ID or 0.
	 */
	public function ensure_page(): int {
		$existing_id = absint( get_option( WorkspaceConstants::PAGE_ID_OPTION, 0 ) );
		if ( $existing_id > 0 ) {
			$post = get_post( $existing_id );
			if ( $post instanceof \WP_Post && 'trash' !== $post->post_status ) {
				return $existing_id;
			}
		}

		$slug = WorkspaceSettings::get_page_slug();
		$by_path = get_page_by_path( $slug );
		if ( $by_path instanceof \WP_Post ) {
			update_option( WorkspaceConstants::PAGE_ID_OPTION, (int) $by_path->ID, false );
			return (int) $by_path->ID;
		}

		$page_id = wp_insert_post(
			array(
				'post_title'   => __( 'Agent Dashboard', 'havenlytics' ),
				'post_name'    => $slug,
				'post_status'  => 'publish',
				'post_type'    => 'page',
				'post_content' => '[' . WorkspaceConstants::SHORTCODE . ']',
				'post_author'  => get_current_user_id() ? get_current_user_id() : 1,
			),
			true
		);

		if ( is_wp_error( $page_id ) || ! $page_id ) {
			return 0;
		}

		update_option( WorkspaceConstants::PAGE_ID_OPTION, (int) $page_id, false );

		/**
		 * Fires after the Workspace page is created.
		 *
		 * @since 3.2.0
		 *
		 * @param int $page_id Page ID.
		 */
		do_action( 'hvnly_workspace_page_created', (int) $page_id );

		return (int) $page_id;
	}

	/**
	 * Stored Workspace page ID.
	 *
	 * @return int
	 */
	public function get_page_id(): int {
		return absint( get_option( WorkspaceConstants::PAGE_ID_OPTION, 0 ) );
	}

	/**
	 * Whether the current request is the Workspace page.
	 *
	 * @return bool
	 */
	public function is_workspace_page(): bool {
		if ( ! is_singular( 'page' ) ) {
			return false;
		}

		$page_id = $this->get_page_id();
		if ( $page_id > 0 && is_page( $page_id ) ) {
			return true;
		}

		return is_page( WorkspaceSettings::get_page_slug() );
	}

	/**
	 * Optionally send administrators from the Workspace page to wp-admin.
	 *
	 * As of 3.4.0 this is **off by default**: administrators use the Agent
	 * Workspace exactly like agents while keeping every WordPress capability,
	 * and they get the standard admin toolbar on top of it. The guard is kept
	 * (rather than deleted) so a site that wants the pre-3.4.0 behaviour can
	 * opt back in via `hvnly_workspace_redirect_admins_to_wpadmin`.
	 *
	 * Capability-based (manage_options), never a role-name check. Logged-out
	 * visitors still get the login screen; non-admin agents are untouched. Runs
	 * only on real front-end page loads (template_redirect), never REST / admin /
	 * AJAX / cron.
	 *
	 * @return void
	 */
	public function maybe_redirect_admins_to_admin(): void {
		// Policy lives in WorkspaceSettings so this guard and the Workspace
		// login handler can never drift apart.
		if ( ! WorkspaceSettings::should_redirect_admins_to_wpadmin() ) {
			return;
		}

		if ( ! WorkspaceSettings::is_enabled() || ! $this->is_workspace_page() ) {
			return;
		}

		if ( ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		/** This filter is documented in includes/Workspace/Auth/SessionAuthController.php */
		$url = (string) apply_filters( 'hvnly_workspace_admin_login_redirect', admin_url(), get_current_user_id() );

		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * Use a minimal canvas template so the SPA (or unavailable UI) is full-bleed.
	 *
	 * Applies on the Workspace page whether the feature is enabled or disabled
	 * (disabled still needs a clean canvas for the unavailable page).
	 * Does not alter other frontend templates.
	 *
	 * @param string $template Current template path.
	 * @return string
	 */
	public function maybe_use_canvas_template( string $template ): string {
		if ( ! $this->is_workspace_page() ) {
			return $template;
		}

		$bootstrap = WorkspaceBootstrap::get_instance();
		$loader    = $bootstrap->get_templates();
		if ( ! $loader ) {
			return $template;
		}

		$canvas = $loader->locate( 'canvas' );
		if ( $canvas !== '' && is_readable( $canvas ) ) {
			return $canvas;
		}

		return $template;
	}

	/**
	 * Body classes for Workspace surfaces.
	 *
	 * @param string[] $classes Classes.
	 * @return string[]
	 */
	public function body_class( array $classes ): array {
		if ( $this->is_workspace_page() ) {
			$classes[] = 'hvnly-ws-page';
			if ( ! WorkspaceSettings::is_enabled() ) {
				$classes[] = 'hvnly-ws-page--unavailable';
			}
			/* Do NOT add BODY_CLASS `hvnly-ws` to <body> — that class is reserved
			 * for the SPA mount / layout root. Applying it to body made global
			 * resets and shell rules fight Login + every form control. */
		}

		return $classes;
	}
}
