<?php
/**
 * Centralized Havenlytics permalink slug settings.
 *
 * @package HvnlyNab\Core
 * @since   3.0.2
 */

namespace HvnlyNab\Core;

use HvnlyNab\Agent\AgentConstants;

defined( 'ABSPATH' ) || exit;

/**
 * Reads, validates, and persists CPT/taxonomy permalink slugs.
 *
 * @since 3.0.2
 */
final class PermalinkSettings {

	public const SETTINGS_GROUP       = 'permalinks';
	public const STORAGE_KEY          = 'hvnly_plugin_settings';
	public const REWRITE_VERSION_KEY  = 'hvnly_rewrite_rules_version';
	public const BOOTSTRAP_FLAG       = 'hvnly_permalink_settings_bootstrapped';
	public const LEGACY_REDIRECTS_KEY = 'hvnly_permalink_legacy_redirects';

	/** @var array<string, string> */
	public const DEFAULTS = array(
		'hvnly_property_archive_slug' => 'properties',
		'hvnly_property_single_slug'    => 'property',
		'hvnly_agent_archive_slug'      => 'agents',
		'hvnly_agent_single_slug'       => 'agent',
		'hvnly_agency_archive_slug'     => 'agencies',
		'hvnly_agency_single_slug'      => 'agency',
	);

	/** @var array<string, string> Pre-3.0.2 archive bases for 301 redirects. */
	public const LEGACY_ARCHIVE_SLUGS = array(
		'property_archive' => 'property',
		'agent_archive'    => 'agent',
		'agency_archive'   => 'agency',
	);

	/** @var bool */
	private static $hooks_registered = false;

	/**
	 * Register filters and rewrite helpers.
	 *
	 * @return void
	 */
	public static function register(): void {
		if ( self::$hooks_registered ) {
			return;
		}

		self::$hooks_registered = true;

		add_filter( 'hvnly_agent_rewrite_slug', array( __CLASS__, 'filter_agent_single_slug' ) );
		add_action( 'init', array( __CLASS__, 'register_agency_archive_rewrite' ), 10 );
		add_action( 'init', array( __CLASS__, 'maybe_bootstrap' ), 5 );
	}

	/**
	 * Enable legacy archive redirects when upgrading from pre-split permalink bases.
	 *
	 * @return void
	 */
	public static function prepare_upgrade_from_pre_split_archives(): void {
		if ( ! empty( self::get_stored_group() ) ) {
			return;
		}

		update_option( self::LEGACY_REDIRECTS_KEY, self::LEGACY_ARCHIVE_SLUGS, false );
		self::write_group( self::DEFAULTS );

		if ( class_exists( RewriteRulesManager::class ) ) {
			RewriteRulesManager::schedule_flush();
		}
	}

	/**
	 * One-time bootstrap for permalink defaults on new installs.
	 *
	 * @return void
	 */
	public static function maybe_bootstrap(): void {
		if ( get_option( self::BOOTSTRAP_FLAG ) ) {
			return;
		}

		if ( empty( self::get_stored_group() ) ) {
			self::write_group( self::DEFAULTS );

			if ( class_exists( RewriteRulesManager::class ) ) {
				RewriteRulesManager::schedule_flush();
			}
		}

		update_option( self::BOOTSTRAP_FLAG, HVNLYNAB_VERSION, false );
	}

	/**
	 * @return array<string, string>
	 */
	public static function get_defaults(): array {
		return self::DEFAULTS;
	}

	/**
	 * @return array<string, string>
	 */
	public static function get_all(): array {
		return wp_parse_args( self::get_stored_group(), self::DEFAULTS );
	}

	/**
	 * @param string $key Setting key.
	 * @return string
	 */
	public static function get( string $key ): string {
		$all = self::get_all();
		return isset( $all[ $key ] ) ? (string) $all[ $key ] : '';
	}

	/**
	 * @return string
	 */
	public static function get_property_single_slug(): string {
		return self::get( 'hvnly_property_single_slug' );
	}

	/**
	 * @return string
	 */
	public static function get_property_archive_slug(): string {
		return self::get( 'hvnly_property_archive_slug' );
	}

	/**
	 * @return string
	 */
	public static function get_agent_single_slug(): string {
		return self::get( 'hvnly_agent_single_slug' );
	}

	/**
	 * @return string
	 */
	public static function get_agent_archive_slug(): string {
		return self::get( 'hvnly_agent_archive_slug' );
	}

	/**
	 * @return string
	 */
	public static function get_agency_single_slug(): string {
		return self::get( 'hvnly_agency_single_slug' );
	}

	/**
	 * @return string
	 */
	public static function get_agency_archive_slug(): string {
		return self::get( 'hvnly_agency_archive_slug' );
	}

	/**
	 * @param string $slug Filter value.
	 * @return string
	 */
	public static function filter_agent_single_slug( $slug ): string {
		unset( $slug );
		return self::get_agent_single_slug();
	}

	/**
	 * Stable hash of current slug configuration.
	 *
	 * @return string
	 */
	public static function get_rewrite_signature(): string {
		return md5( wp_json_encode( self::get_all() ) );
	}

	/**
	 * Whether stored rewrite signature matches current slug settings.
	 *
	 * @return bool
	 */
	public static function rewrite_is_current(): bool {
		return self::get_rewrite_signature() === (string) get_option( self::REWRITE_VERSION_KEY, '' );
	}

	/**
	 * Persist rewrite signature after a successful flush.
	 *
	 * @return void
	 */
	public static function mark_rewrite_current(): void {
		update_option( self::REWRITE_VERSION_KEY, self::get_rewrite_signature(), false );
	}

	/**
	 * Validate and save permalink settings from the settings API.
	 *
	 * @param array<string, mixed> $input Raw settings payload.
	 * @return array<string, string>|\WP_Error
	 */
	public static function save_group( array $input ) {
		$previous  = self::get_all();
		$sanitized = array();

		foreach ( self::DEFAULTS as $key => $default ) {
			$raw  = isset( $input[ $key ] ) ? (string) $input[ $key ] : (string) ( $previous[ $key ] ?? $default );
			$slug = self::sanitize_slug( $raw, (string) $default );

			if ( is_wp_error( $slug ) ) {
				return $slug;
			}

			$sanitized[ $key ] = $slug;
		}

		$conflict = self::detect_slug_conflicts( $sanitized );
		if ( is_wp_error( $conflict ) ) {
			return $conflict;
		}

		self::write_group( $sanitized );

		if ( $sanitized !== $previous ) {
			self::maybe_store_legacy_redirects( $previous, $sanitized );

			if ( class_exists( RewriteRulesManager::class ) ) {
				RewriteRulesManager::schedule_flush();
			}
		}

		return $sanitized;
	}

	/**
	 * Register /agencies/ (configurable) as the agency taxonomy archive index.
	 *
	 * @return void
	 */
	public static function register_agency_archive_rewrite(): void {
		if ( ! taxonomy_exists( AgentConstants::TAXONOMY_AGENCY ) ) {
			return;
		}

		$archive_slug = self::get_agency_archive_slug();
		$single_slug  = self::get_agency_single_slug();

		if ( '' !== $archive_slug && $archive_slug !== $single_slug ) {
			$quoted_archive = preg_quote( $archive_slug, '/' );

			add_rewrite_rule(
				'^' . $quoted_archive . '/?$',
				'index.php?taxonomy=' . AgentConstants::TAXONOMY_AGENCY,
				'top'
			);

			add_rewrite_rule(
				'^' . $quoted_archive . '/page/([0-9]{1,})/?$',
				'index.php?taxonomy=' . AgentConstants::TAXONOMY_AGENCY . '&paged=$matches[1]',
				'top'
			);
		}

		self::register_agency_term_rewrite();
	}

	/**
	 * Register agency single term routes above conflicting page rules.
	 *
	 * @return void
	 */
	public static function register_agency_term_rewrite(): void {
		$single_slug = self::get_agency_single_slug();
		if ( '' === $single_slug ) {
			return;
		}

		$single_slug = preg_quote( sanitize_title( $single_slug ), '/' );
		$taxonomy    = AgentConstants::TAXONOMY_AGENCY;

		add_rewrite_rule(
			'^' . $single_slug . '/([^/]+)/?$',
			'index.php?' . $taxonomy . '=$matches[1]',
			'top'
		);

		add_rewrite_rule(
			'^' . $single_slug . '/(.+?)/?$',
			'index.php?' . $taxonomy . '=$matches[1]',
			'top'
		);

		if ( ! get_option( 'hvnly_agency_term_routes_registered' ) ) {
			update_option( 'hvnly_agency_term_routes_registered', '1', false );

			if ( class_exists( RewriteRulesManager::class ) ) {
				RewriteRulesManager::schedule_flush();
			}
		}
	}

	/**
	 * @param string      $raw     Raw slug.
	 * @param string|null $fallback Fallback when empty.
	 * @return string|\WP_Error
	 */
	public static function sanitize_slug( string $raw, ?string $fallback = null ) {
		$slug = sanitize_title( $raw );

		if ( '' === $slug && null !== $fallback ) {
			$slug = sanitize_title( $fallback );
		}

		if ( '' === $slug ) {
			return new \WP_Error(
				'hvnly_invalid_permalink_slug',
				__( 'Permalink slugs cannot be empty.', 'havenlytics' ),
				array( 'status' => 400 )
			);
		}

		$reserved = array( 'feed', 'page', 'embed', 'attachment', 'wp-json' );
		if ( in_array( $slug, $reserved, true ) ) {
			return new \WP_Error(
				'hvnly_reserved_permalink_slug',
				__( 'That permalink slug is reserved by WordPress.', 'havenlytics' ),
				array( 'status' => 400 )
			);
		}

		return $slug;
	}

	/**
	 * @return array<string, string>
	 */
	private static function get_stored_group(): array {
		$all = get_option( self::STORAGE_KEY, array() );
		if ( ! is_array( $all ) || empty( $all[ self::SETTINGS_GROUP ] ) || ! is_array( $all[ self::SETTINGS_GROUP ] ) ) {
			return array();
		}

		return $all[ self::SETTINGS_GROUP ];
	}

	/**
	 * @param array<string, string> $group Sanitized permalink settings.
	 * @return void
	 */
	private static function write_group( array $group ): void {
		$all = get_option( self::STORAGE_KEY, array() );
		if ( ! is_array( $all ) ) {
			$all = array();
		}

		$all[ self::SETTINGS_GROUP ] = $group;
		update_option( self::STORAGE_KEY, $all, false );
	}

	/**
	 * @param array<string, string> $slugs Slug set.
	 * @return true|\WP_Error
	 */
	private static function detect_slug_conflicts( array $slugs ) {
		$values = array_values( $slugs );
		if ( count( $values ) !== count( array_unique( $values ) ) ) {
			return new \WP_Error(
				'hvnly_duplicate_permalink_slug',
				__( 'Each permalink slug must be unique.', 'havenlytics' ),
				array( 'status' => 400 )
			);
		}

		return true;
	}

	/**
	 * Keep 301 sources when archive slugs change.
	 *
	 * @param array<string, string> $previous Previous slugs.
	 * @param array<string, string> $current  New slugs.
	 * @return void
	 */
	private static function maybe_store_legacy_redirects( array $previous, array $current ): void {
		$legacy = get_option( self::LEGACY_REDIRECTS_KEY, array() );
		if ( ! is_array( $legacy ) ) {
			$legacy = array();
		}

		$map = array(
			'property_archive' => array( 'hvnly_property_archive_slug', 'hvnly_property_single_slug' ),
			'agent_archive'    => array( 'hvnly_agent_archive_slug', 'hvnly_agent_single_slug' ),
			'agency_archive'   => array( 'hvnly_agency_archive_slug', 'hvnly_agency_single_slug' ),
		);

		foreach ( $map as $legacy_key => $keys ) {
			$archive_key = $keys[0];
			$single_key  = $keys[1];

			if ( ! isset( $previous[ $archive_key ], $current[ $archive_key ] ) ) {
				continue;
			}

			if ( $previous[ $archive_key ] !== $current[ $archive_key ] ) {
				$legacy[ $legacy_key ] = $previous[ $archive_key ];
			} elseif ( isset( $legacy[ $legacy_key ] ) && $legacy[ $legacy_key ] === $current[ $archive_key ] ) {
				unset( $legacy[ $legacy_key ] );
			}

			if (
				isset( $legacy[ $legacy_key ], $current[ $archive_key ] )
				&& $legacy[ $legacy_key ] === $current[ $archive_key ]
			) {
				unset( $legacy[ $legacy_key ] );
			}

			if (
				isset( $legacy[ $legacy_key ], $current[ $single_key ] )
				&& $legacy[ $legacy_key ] === $current[ $single_key ]
				&& $legacy_key !== 'property_archive'
			) {
				unset( $legacy[ $legacy_key ] );
			}
		}

		if ( empty( $legacy ) ) {
			delete_option( self::LEGACY_REDIRECTS_KEY );
		} else {
			update_option( self::LEGACY_REDIRECTS_KEY, $legacy, false );
		}
	}

	/**
	 * Prevent instantiation.
	 */
	private function __construct() {}
}
