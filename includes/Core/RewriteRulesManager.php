<?php
/**
 * Deferred rewrite rule flushing for Havenlytics custom post types.
 *
 * @package     Havenlytics
 * @subpackage  Core
 * @since       3.0.0
 */

namespace HvnlyNab\Core;

use HvnlyNab\Agent\AgentAgencyTaxonomy;
use HvnlyNab\Agent\AgentConstants;
use HvnlyNab\Agent\AgentPostType;

defined( 'ABSPATH' ) || exit;

/**
 * Schedules and performs one-time rewrite flushes when CPT rules must be rebuilt.
 */
class RewriteRulesManager {

	/**
	 * Option flag indicating a deferred flush is needed.
	 *
	 * @var string
	 */
	const FLUSH_OPTION = 'hvnly_flush_rewrite_once';

	/**
	 * Prevent duplicate flushes within the same request.
	 *
	 * @var bool
	 */
	private static $flushed_this_request = false;

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function register() {
		PermalinkSettings::register();
		PermalinkRedirectHandler::register();

		// Priority 11: after CPT (init:0), taxonomies (init:9), and custom rules (init:10).
		add_action( 'init', array( __CLASS__, 'maybe_upgrade_rewrite_rules' ), 11 );
		add_action( 'init', array( __CLASS__, 'maybe_flush' ), 12 );
		add_action( 'admin_init', array( __CLASS__, 'maybe_flush' ), 20 );
		add_action( 'wp_loaded', array( __CLASS__, 'maybe_flush' ), 20 );
		add_action( 'shutdown', array( __CLASS__, 'maybe_flush' ), 999 );
		add_action( 'transition_post_status', array( __CLASS__, 'maybe_schedule_flush_on_publish' ), 10, 3 );
	}

	/**
	 * Register Havenlytics CPTs and taxonomies before rewrite rules are built.
	 *
	 * Safe to call during activation (before init) or after init.
	 *
	 * @return void
	 */
	public static function register_content_types() {
		if ( class_exists( '\HvnlyNab\Database\Custom_Posts\HavenlyticsNab' ) && ! post_type_exists( 'hvnly_property' ) ) {
			$cpt = new \HvnlyNab\Database\Custom_Posts\HavenlyticsNab();
			$cpt->register_custom_post_type();
		}

		if ( class_exists( AgentPostType::class ) && ! post_type_exists( AgentConstants::POST_TYPE ) ) {
			$agent = new AgentPostType();
			if ( method_exists( $agent, 'register_custom_post_type' ) ) {
				$agent->register_custom_post_type();
			}
		}

		if ( class_exists( AgentAgencyTaxonomy::class ) && ! taxonomy_exists( AgentConstants::TAXONOMY_AGENCY ) ) {
			$agency = new AgentAgencyTaxonomy();
			if ( method_exists( $agency, 'register_taxonomy' ) ) {
				$agency->register_taxonomy();
			}
		}

		$taxonomy_classes = array(
			'\HvnlyNab\Database\Custom_Taxonomy\Property_Departments',
			'\HvnlyNab\Database\Custom_Taxonomy\Property_Types',
			'\HvnlyNab\Database\Custom_Taxonomy\Property_Features',
			'\HvnlyNab\Database\Custom_Taxonomy\Property_Locations',
			'\HvnlyNab\Database\Custom_Taxonomy\Property_Status',
			'\HvnlyNab\Database\Custom_Taxonomy\Property_Badges',
			'\HvnlyNab\Database\Custom_Taxonomy\Property_Tags',
		);

		foreach ( $taxonomy_classes as $class ) {
			if ( ! class_exists( $class ) ) {
				continue;
			}

			$instance = new $class();
			if ( method_exists( $instance, 'register_custom_taxonomy' ) ) {
				$instance->register_custom_taxonomy();
			}
		}

		if ( did_action( 'init' ) ) {
			PermalinkSettings::register_agency_archive_rewrite();
		}
	}

	/**
	 * Target permalink structure for Havenlytics CPT/taxonomy routes (Settings → Post name).
	 */
	public const POSTNAME_STRUCTURE = '/%postname%/';

	/**
	 * Whether the site uses plain permalinks (?p=123), which break pretty CPT URLs.
	 *
	 * @return bool
	 */
	public static function is_plain_permalink_structure() {
		return '' === (string) get_option( 'permalink_structure', '' );
	}

	/**
	 * Whether the site permalink structure is Post name (/%postname%/).
	 *
	 * @return bool
	 */
	public static function permalink_structure_is_postname() {
		return self::POSTNAME_STRUCTURE === (string) get_option( 'permalink_structure', '' );
	}

	/**
	 * Apply the Post name permalink structure to options and the live WP_Rewrite instance.
	 *
	 * Mirrors clicking Save on Settings → Permalinks → Post name.
	 *
	 * @return bool True when structure was updated.
	 */
	public static function apply_postname_permalink_structure() {
		$target = self::POSTNAME_STRUCTURE;

		global $wp_rewrite;

		if ( ! isset( $wp_rewrite ) || ! $wp_rewrite instanceof \WP_Rewrite ) {
			require_once ABSPATH . 'wp-includes/class-wp-rewrite.php';
			$wp_rewrite = new \WP_Rewrite();
		}

		$stored = (string) get_option( 'permalink_structure', '' );

		if ( $stored === $target && $wp_rewrite->permalink_structure === $target ) {
			return false;
		}

		update_option( 'permalink_structure', $target );
		$wp_rewrite->set_permalink_structure( $target );

		return true;
	}

	/**
	 * Ensure a pretty permalink structure exists on first plugin activation.
	 *
	 * @return void
	 */
	public static function ensure_pretty_permalink_structure() {
		if ( ! self::is_plain_permalink_structure() ) {
			return;
		}

		self::apply_postname_permalink_structure();
	}

	/**
	 * Set Post name permalinks (when requested/plain) and rebuild rewrite rules.
	 *
	 * Used after setup import, activation, and whenever CPT routes return 404 until permalinks are saved.
	 *
	 * @param bool $ensure_postname Force Post name structure (setup wizard / first import).
	 * @return void
	 */
	public static function finalize_permalinks( $ensure_postname = false ) {
		if ( $ensure_postname || self::is_plain_permalink_structure() ) {
			self::apply_postname_permalink_structure();
		}

		if ( ! did_action( 'init' ) ) {
			self::schedule_flush();
			return;
		}

		self::regenerate_rewrite_rules();
		PermalinkSettings::mark_rewrite_current();
		delete_option( self::FLUSH_OPTION );
		self::$flushed_this_request = true;

		// Admin AJAX can return before rules fully persist — rebuild again on shutdown.
		add_action(
			'shutdown',
			static function () {
				if ( ! did_action( 'init' ) ) {
					return;
				}

				self::$flushed_this_request = false;
				update_option( self::FLUSH_OPTION, 1, false );
				self::maybe_flush();
			},
			999
		);
	}

	/**
	 * Activation handler: register CPT/taxonomies, then flush rewrite rules.
	 *
	 * @return void
	 */
	public static function activation_setup() {
		self::finalize_permalinks( true );
	}

	/**
	 * Mark rewrite rules for a one-time flush on the next safe hook.
	 *
	 * @return void
	 */
	public static function schedule_flush() {
		update_option( self::FLUSH_OPTION, 1, false );
	}

	/**
	 * Flush rewrite rules immediately when init has already registered CPTs.
	 *
	 * @return void
	 */
	public static function flush_now() {
		if ( ! did_action( 'init' ) ) {
			self::schedule_flush();
			return;
		}

		self::regenerate_rewrite_rules();
		PermalinkSettings::mark_rewrite_current();
		delete_option( self::FLUSH_OPTION );
		self::$flushed_this_request = true;
	}

	/**
	 * Schedule a flush when slug signature changes.
	 *
	 * @return void
	 */
	public static function maybe_upgrade_rewrite_rules() {
		if ( self::$flushed_this_request ) {
			return;
		}

		if ( PermalinkSettings::rewrite_is_current() ) {
			return;
		}

		self::schedule_flush();
	}

	/**
	 * Perform a deferred flush when the flag is set and init has completed.
	 *
	 * @return void
	 */
	public static function maybe_flush() {
		if ( self::$flushed_this_request ) {
			return;
		}

		$needs_flush = (bool) get_option( self::FLUSH_OPTION );
		$needs_upgrade = ! PermalinkSettings::rewrite_is_current();

		if ( ! $needs_flush && ! $needs_upgrade ) {
			return;
		}

		if ( ! did_action( 'init' ) ) {
			return;
		}

		$success = self::regenerate_rewrite_rules();

		if ( $success ) {
			delete_option( self::FLUSH_OPTION );
			PermalinkSettings::mark_rewrite_current();
			self::$flushed_this_request = true;
		}
	}

	/**
	 * Force WordPress to rebuild rewrite rules from all registered post types.
	 *
	 * @return bool
	 */
	private static function regenerate_rewrite_rules() {
		delete_option( 'rewrite_rules' );

		if ( function_exists( 'wp_cache_delete' ) ) {
			wp_cache_delete( 'rewrite_rules', 'options' );
		}

		self::register_content_types();

		global $wp_rewrite;
		if ( isset( $wp_rewrite ) && $wp_rewrite instanceof \WP_Rewrite ) {
			$wp_rewrite->init();
		}

		flush_rewrite_rules( true );

		return true;
	}

	/**
	 * Schedule a flush when a property is first published manually.
	 *
	 * @param string   $new_status New post status.
	 * @param string   $old_status Old post status.
	 * @param \WP_Post $post       Post object.
	 * @return void
	 */
	public static function maybe_schedule_flush_on_publish( $new_status, $old_status, $post ) {
		if ( ! is_object( $post ) || 'hvnly_property' !== $post->post_type ) {
			return;
		}

		if ( 'publish' !== $new_status || 'publish' === $old_status ) {
			return;
		}

		if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		self::schedule_flush();
	}
}

