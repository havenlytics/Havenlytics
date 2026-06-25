<?php
/**
 * Early template function loader.
 *
 * Ensures hvnly_get_template_part() and related helpers exist before any
 * Havenlytics template file is rendered, even if Frontend bootstrap fails
 * partway through service registration.
 *
 * @package Havenlytics
 * @since   3.0.2
 */

defined( 'ABSPATH' ) || exit;

/**
 * Load Havenlytics template helper function files (idempotent).
 *
 * @return void
 */
function hvnly_load_template_functions(): void {
	static $loaded = false;

	if ( $loaded ) {
		return;
	}

	if ( ! defined( 'HVNLYNAB_INCLUDES' ) ) {
		return;
	}

	$base = HVNLYNAB_INCLUDES . '/Functions/';

	$files = array(
		'field-options.php',
		'template-functions.php',
		'template-hook-functions.php',
		'template-hooks.php',
		'property-functions.php',
		'agent-functions.php',
		'layout-functions.php',
		'utility-functions.php',
		'ajax-functions.php',
		'shortcode-functions.php',
		'filter-sidebar-functions.php',
		'top-search-functions.php',
		'main-top-search-functions.php',
		'map-functions.php',
	);

	foreach ( $files as $file ) {
		$path = $base . $file;
		if ( is_readable( $path ) ) {
			require_once $path;
		}
	}

	hvnly_register_template_function_fallbacks();

	$loaded = true;
}

/**
 * Alias for templates and legacy callers.
 *
 * @return void
 */
function hvnly_ensure_template_functions(): void {
	hvnly_load_template_functions();
}

/**
 * Minimal fallbacks when the full loader did not register helpers.
 *
 * @return void
 */
function hvnly_register_template_function_fallbacks(): void {
	if ( ! function_exists( 'hvnly_get_template_part' ) ) {
		/**
		 * @param string $slug Template slug.
		 * @param string $name Template name.
		 * @param array  $args Template arguments.
		 * @return void
		 */
		function hvnly_get_template_part( $slug, $name = '', $args = array() ) {
			$templates = array();
			$name      = (string) $name;

			if ( '' !== $name ) {
				$templates[] = "{$slug}-{$name}.php";
			}

			$templates[] = "{$slug}.php";

			if ( function_exists( 'hvnly_locate_template' ) ) {
				$located = hvnly_locate_template( $templates );
				if ( $located ) {
					load_template( $located, false, $args );
					return;
				}
			}

			if ( ! defined( 'HVNLYNAB_PATH' ) ) {
				return;
			}

			foreach ( $templates as $template ) {
				$path = HVNLYNAB_PATH . 'templates/' . $template;
				if ( file_exists( $path ) ) {
					load_template( $path, false, $args );
					return;
				}
			}
		}
	}

	if ( ! function_exists( 'hvnly_get_template' ) ) {
		/**
		 * @param string $template_name Template path relative to templates/.
		 * @param array  $args          Template arguments.
		 * @return void
		 */
		function hvnly_get_template( $template_name, $args = array() ) {
			if ( '.php' !== substr( (string) $template_name, -4 ) ) {
				$template_name .= '.php';
			}

			if ( function_exists( 'hvnly_locate_template' ) ) {
				$located = hvnly_locate_template( array( $template_name ) );
				if ( $located && file_exists( $located ) ) {
					if ( ! empty( $args ) && is_array( $args ) ) {
						// phpcs:ignore WordPress.PHP.DontExtract.extract_extract
						extract( $args );
					}
					include $located;
					return;
				}
			}

			if ( defined( 'HVNLYNAB_PATH' ) ) {
				$path = HVNLYNAB_PATH . 'templates/' . ltrim( (string) $template_name, '/' );
				if ( file_exists( $path ) ) {
					if ( ! empty( $args ) && is_array( $args ) ) {
						// phpcs:ignore WordPress.PHP.DontExtract.extract_extract
						extract( $args );
					}
					include $path;
				}
			}
		}
	}
}

/**
 * Guard capability checks until the relevant post type is registered.
 *
 * @param int    $post_id   Post ID.
 * @param string $post_type Expected post type slug.
 * @return bool
 */
function hvnly_current_user_can_edit_post_type( int $post_id, string $post_type = '' ): bool {
	if ( $post_id <= 0 ) {
		return false;
	}

	if ( '' === $post_type ) {
		$post_type = (string) get_post_type( $post_id );
	}

	if ( $post_type && ! post_type_exists( $post_type ) ) {
		return false;
	}

	return current_user_can( 'edit_post', $post_id );
}

add_action( 'plugins_loaded', 'hvnly_load_template_functions', 15 );

add_filter(
	'template_include',
	static function ( $template ) {
		hvnly_load_template_functions();
		return $template;
	},
	1
);
