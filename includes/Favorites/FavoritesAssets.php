<?php
/**
 * Favorites front-end assets.
 *
 * @package HvnlyNab\Favorites
 * @since   3.4.0
 */

namespace HvnlyNab\Favorites;

use HvnlyNab\Workspace\WorkspaceConstants;

defined( 'ABSPATH' ) || exit;

/**
 * Registers and localizes the front-end favorites script.
 *
 * @since 3.4.0
 */
final class FavoritesAssets {

	/**
	 * Script handle.
	 *
	 * @var string
	 */
	public const SCRIPT_HANDLE = 'hvnly-favorites';

	/**
	 * Heart-state stylesheet handle (3.4.0).
	 *
	 * Carries the .is-favorited / busy / focus rules so the red saved state
	 * renders on every surface with a heart, independent of which page-level
	 * card stylesheet happens to load.
	 *
	 * @var string
	 */
	public const STYLE_HANDLE = 'hvnly-favorites';

	/**
	 * Shared toast manager handles.
	 *
	 * Registered here but deliberately generic: the toast engine is a
	 * plugin-wide UI primitive, not a favorites feature. Other modules
	 * enqueue the same handles rather than shipping their own.
	 *
	 * @var string
	 */
	public const TOAST_SCRIPT_HANDLE = 'hvnly-toast';

	/**
	 * @var string
	 */
	public const TOAST_STYLE_HANDLE = 'hvnly-toast';

	/**
	 * Localized JS object name.
	 *
	 * @var string
	 */
	public const LOCALIZE_OBJECT = 'hvnlyFavoritesData';

	/**
	 * Handles that imply property cards are on the page.
	 *
	 * @var string[]
	 */
	private const CARD_SCRIPT_HANDLES = array(
		'hvnly-frontend-property-ajax-filter-search',
		'hvnly-frontend-property-ajax-root',
		'hvnly-elementor-widgets',
	);

	/**
	 * @var FavoritesService
	 */
	private $service;

	/**
	 * @param FavoritesService|null $service Service.
	 */
	public function __construct( ?FavoritesService $service = null ) {
		$this->service = $service ? $service : new FavoritesService();
	}

	/**
	 * @return void
	 */
	public function register_hooks(): void {
		// Priority 25 so the plugin's own front-end registration (20) has
		// already run and its handles can be inspected.
		add_action( 'wp_enqueue_scripts', array( $this, 'maybe_enqueue' ), 25 );
	}

	/**
	 * Enqueue when the current request can render property cards.
	 *
	 * @return void
	 */
	public function maybe_enqueue(): void {
		if ( ! $this->should_enqueue() ) {
			return;
		}

		$this->enqueue();
	}

	/**
	 * @return bool
	 */
	public function should_enqueue(): bool {
		$should = false;

		// Only `enqueued`, never `registered`: the plugin registers its
		// front-end handles broadly, so testing registration would load
		// favorites on essentially every page.
		foreach ( self::CARD_SCRIPT_HANDLES as $handle ) {
			if ( wp_script_is( $handle, 'enqueued' ) ) {
				$should = true;
				break;
			}
		}

		if ( ! $should && is_singular( FavoritesService::POST_TYPE ) ) {
			$should = true;
		}

		if ( ! $should && is_post_type_archive( FavoritesService::POST_TYPE ) ) {
			$should = true;
		}

		if ( ! $should && is_tax( get_object_taxonomies( FavoritesService::POST_TYPE ) ) ) {
			$should = true;
		}

		/**
		 * Filter whether the favorites script loads on this request.
		 *
		 * @since 3.4.0
		 *
		 * @param bool $should Whether to enqueue.
		 */
		return (bool) apply_filters( 'hvnly_favorites_should_enqueue', $should );
	}

	/**
	 * Register, enqueue and localize (idempotent).
	 *
	 * @return void
	 */
	public function enqueue(): void {
		if ( wp_script_is( self::SCRIPT_HANDLE, 'enqueued' ) ) {
			return;
		}

		self::register_toast_assets();
		wp_enqueue_style( self::TOAST_STYLE_HANDLE );
		wp_enqueue_script( self::TOAST_SCRIPT_HANDLE );

		if ( ! wp_style_is( self::STYLE_HANDLE, 'registered' ) ) {
			wp_register_style(
				self::STYLE_HANDLE,
				HVNLYNAB_ASSETS_URL . '/frontend/css/favorites/hvnly-favorites.css',
				array(),
				HVNLYNAB_VERSION
			);
		}

		wp_enqueue_style( self::STYLE_HANDLE );

		if ( ! wp_script_is( self::SCRIPT_HANDLE, 'registered' ) ) {
			wp_register_script(
				self::SCRIPT_HANDLE,
				HVNLYNAB_ASSETS_URL . '/frontend/js/favorites/hvnly-favorites.js',
				// Hard dependency: the toast manager must define
				// window.HvnlyToast before favorites can call it.
				array( self::TOAST_SCRIPT_HANDLE ),
				HVNLYNAB_VERSION,
				true
			);
		}

		wp_enqueue_script( self::SCRIPT_HANDLE );

		wp_localize_script( self::SCRIPT_HANDLE, self::LOCALIZE_OBJECT, $this->get_localize_data() );
	}

	/**
	 * Register the shared toast manager (idempotent, no enqueue).
	 *
	 * Public and static so any module can depend on the same single engine:
	 *   FavoritesAssets::register_toast_assets();
	 *   wp_enqueue_script( FavoritesAssets::TOAST_SCRIPT_HANDLE );
	 *
	 * @since 3.4.0
	 *
	 * @return void
	 */
	public static function register_toast_assets(): void {
		if ( ! wp_style_is( self::TOAST_STYLE_HANDLE, 'registered' ) ) {
			wp_register_style(
				self::TOAST_STYLE_HANDLE,
				HVNLYNAB_ASSETS_URL . '/frontend/css/hvnly-toast.css',
				array(),
				HVNLYNAB_VERSION
			);
		}

		if ( ! wp_script_is( self::TOAST_SCRIPT_HANDLE, 'registered' ) ) {
			wp_register_script(
				self::TOAST_SCRIPT_HANDLE,
				HVNLYNAB_ASSETS_URL . '/frontend/js/toast/hvnly-toast.js',
				array(),
				HVNLYNAB_VERSION,
				true
			);
		}
	}

	/**
	 * Boot payload for the favorites script.
	 *
	 * @return array<string, mixed>
	 */
	public function get_localize_data(): array {
		$logged_in = is_user_logged_in();
		$user_id   = get_current_user_id();

		$data = array(
			'restUrl'    => esc_url_raw(
				rest_url( WorkspaceConstants::REST_NAMESPACE . '/' . FavoritesController::ROUTE_BASE )
			),
			// Only issued to signed-in users; anonymous visitors never make a
			// write request, so there is nothing to protect.
			'nonce'      => $logged_in ? wp_create_nonce( 'wp_rest' ) : '',
			'isLoggedIn' => $logged_in,
			'loginUrl'   => esc_url_raw( $this->login_url() ),
			'savedPropertiesUrl' => esc_url_raw( $this->saved_properties_url() ),
			'storageKey' => 'hvnly_guest_favorites',
			'maxGuest'   => 100,
			// Server-rendered heart state for signed-in users. One cached
			// query already ran while rendering the cards, so this is free.
			'ids'        => $logged_in ? $this->service->get_ids( $user_id ) : array(),
			// Toast copy is kept separate from the button i18n so the two can
			// be filtered independently.
			'toast'      => array(
				// The property thumbnail and name now carry the context, so
				// the headline stays short and the emoji is dropped.
				'addedTitle'    => __( 'Added to Favorites', 'havenlytics' ),
				'removedTitle'  => __( 'Removed from Favorites', 'havenlytics' ),
				'viewFavorites' => __( 'View Favorites', 'havenlytics' ),
				'login'         => __( 'Login', 'havenlytics' ),
				'undo'          => __( 'Undo', 'havenlytics' ),
				'errorTitle'    => __( 'Could not update favorites', 'havenlytics' ),
				'dismiss'       => __( 'Dismiss notification', 'havenlytics' ),
			),
			'i18n'       => array(
				'add'          => __( 'Add to favorites', 'havenlytics' ),
				'remove'       => __( 'Remove from favorites', 'havenlytics' ),
				'saved'        => __( 'Saved to your favorites.', 'havenlytics' ),
				'removed'      => __( 'Removed from your favorites.', 'havenlytics' ),
				'guestSaved'   => __( 'Saved. Sign in to keep your favorites.', 'havenlytics' ),
				'error'        => __( 'Something went wrong. Please try again.', 'havenlytics' ),
				'limitReached' => __( 'You have reached your saved properties limit.', 'havenlytics' ),
			),
		);

		/**
		 * Filter the favorites front-end boot payload.
		 *
		 * @since 3.4.0
		 *
		 * @param array<string, mixed> $data Localized data.
		 */
		return (array) apply_filters( 'hvnly_favorites_localize_data', $data );
	}

	/**
	 * Where an anonymous visitor should be sent to sign in.
	 *
	 * Prefers the Workspace login surface when the Workspace is available,
	 * falling back to wp-login.php.
	 *
	 * @return string
	 */
	private function login_url(): string {
		$redirect = '';

		if ( isset( $_SERVER['REQUEST_URI'] ) ) {
			$redirect = esc_url_raw(
				home_url( sanitize_text_field( wp_unslash( (string) $_SERVER['REQUEST_URI'] ) ) )
			);
		}

		if ( class_exists( '\HvnlyNab\Setup\PageInstaller' ) ) {
			$sign_in_id = \HvnlyNab\Setup\PageInstaller::get_page_id( 'sign_in' );
			if ( $sign_in_id > 0 ) {
				$url = get_permalink( $sign_in_id );
				if ( is_string( $url ) && '' !== $url ) {
					return $url;
				}
			}
		}

		if ( class_exists( '\HvnlyNab\Workspace\WorkspaceAvailability' )
			&& \HvnlyNab\Workspace\WorkspaceAvailability::is_available()
			&& class_exists( '\HvnlyNab\Workspace\WorkspaceSettings' )
		) {
			// get_base_url() is already absolute and subdirectory-aware — do
			// not wrap it in home_url() or subdir installs double the path.
			$base = \HvnlyNab\Workspace\WorkspaceSettings::get_base_url();
			if ( is_string( $base ) && '' !== $base ) {
				return $base;
			}
		}

		return wp_login_url( $redirect );
	}

	/**
	 * Deep link to Favorites page, or Agent Workspace → Saved Properties.
	 *
	 * @return string
	 */
	private function saved_properties_url(): string {
		if ( class_exists( '\HvnlyNab\Setup\PageInstaller' ) ) {
			$favorites_id = \HvnlyNab\Setup\PageInstaller::get_page_id( 'favorites' );
			if ( $favorites_id > 0 ) {
				$url = get_permalink( $favorites_id );
				if ( is_string( $url ) && '' !== $url ) {
					return $url;
				}
			}
		}

		if ( ! $this->workspace_available() ) {
			return '';
		}

		return \HvnlyNab\Workspace\WorkspaceSettings::route_url( 'saved-properties' );
	}

	/**
	 * @return bool
	 */
	private function workspace_available(): bool {
		return class_exists( '\HvnlyNab\Workspace\WorkspaceAvailability' )
			&& \HvnlyNab\Workspace\WorkspaceAvailability::is_available()
			&& class_exists( '\HvnlyNab\Workspace\WorkspaceSettings' );
	}
}
