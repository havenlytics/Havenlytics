<?php
/**
 * Workspace / Agent Portal template loader (architecture preparation).
 *
 * Supports theme overrides without altering existing property/agent templates.
 *
 * Theme path:
 *   your-theme/havenlytics/agent-portal/{template}.php
 *
 * Plugin default:
 *   plugins/havenlytics/templates/agent-portal/{template}.php
 *
 * @package HvnlyNab\Workspace
 * @since   3.2.0
 */

namespace HvnlyNab\Workspace;

defined( 'ABSPATH' ) || exit;

/**
 * Locate and load Agent Portal shell templates.
 *
 * Phase 1: API only — no automatic template_include hijacking.
 *
 * @since 3.2.0
 */
final class WorkspaceTemplateLoader {

	/**
	 * Allowed template basenames (no path traversal).
	 *
	 * @var string[]
	 */
	private const ALLOWED_TEMPLATES = array(
		'canvas',
		'dashboard',
		'unavailable',
		'login',
		'register',
		'forgot-password',
		'reset-password',
	);

	/**
	 * Theme subdirectory under havenlytics/.
	 *
	 * @var string
	 */
	private $theme_subdir;

	/**
	 * Plugin default directory (absolute).
	 *
	 * @var string
	 */
	private $default_path;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->theme_subdir = WorkspaceConstants::TEMPLATE_SUBDIR;
		$this->default_path = HVNLYNAB_PATH . 'templates/' . WorkspaceConstants::TEMPLATE_SUBDIR . '/';
	}

	/**
	 * Theme-relative path prefix (e.g. havenlytics/agent-portal/).
	 *
	 * @return string
	 */
	public function get_theme_template_path(): string {
		$base = 'havenlytics/';

		/**
		 * Filter the theme template base path (shared with Frontend\TemplateLoader).
		 *
		 * @since 2.0.0
		 *
		 * @param string $path Theme-relative base.
		 */
		$base = (string) apply_filters( 'hvnly_template_path', $base );

		$path = trailingslashit( $base ) . trailingslashit( $this->theme_subdir );

		/**
		 * Filter Workspace theme template subdirectory path.
		 *
		 * @since 3.2.0
		 *
		 * @param string $path Theme-relative path including agent-portal/.
		 */
		return (string) apply_filters( 'hvnly_workspace_template_path', $path );
	}

	/**
	 * Absolute plugin default template directory.
	 *
	 * @return string
	 */
	public function get_default_path(): string {
		/**
		 * Filter Workspace plugin default template directory.
		 *
		 * @since 3.2.0
		 *
		 * @param string $path Absolute path with trailing slash.
		 */
		return (string) apply_filters( 'hvnly_workspace_default_path', $this->default_path );
	}

	/**
	 * Locate a Workspace template file.
	 *
	 * @param string $template Template basename without .php (e.g. dashboard).
	 * @return string Absolute path or empty string.
	 */
	public function locate( string $template ): string {
		$basename = $this->sanitize_template_name( $template );
		if ( $basename === '' ) {
			return '';
		}

		$file = $basename . '.php';
		$theme_path = $this->get_theme_template_path();
		$default    = $this->get_default_path();

		$located = locate_template(
			array(
				trailingslashit( $theme_path ) . $file,
				'havenlytics/' . WorkspaceConstants::TEMPLATE_SUBDIR . '/' . $file,
			)
		);

		if ( ! $located && is_readable( $default . $file ) ) {
			$located = $default . $file;
		}

		/**
		 * Filter located Workspace template path.
		 *
		 * @since 3.2.0
		 *
		 * @param string $located  Absolute path or empty.
		 * @param string $basename Sanitized template basename.
		 * @param string $theme_path Theme-relative search path.
		 * @param string $default  Plugin default directory.
		 */
		return (string) apply_filters(
			'hvnly_workspace_locate_template',
			$located ? $located : '',
			$basename,
			$theme_path,
			$default
		);
	}

	/**
	 * Load a template into the current buffer (include).
	 *
	 * Does not print a full page chrome unless the template itself does.
	 * Phase 1 placeholders are intentionally empty shells.
	 *
	 * @param string               $template Template basename.
	 * @param array<string, mixed> $args     Optional extract() args for the template.
	 * @return bool True when a file was included.
	 */
	public function load( string $template, array $args = array() ): bool {
		$path = $this->locate( $template );
		if ( $path === '' || ! is_readable( $path ) ) {
			return false;
		}

		/**
		 * Fires before a Workspace template is included.
		 *
		 * @since 3.2.0
		 *
		 * @param string               $path     Absolute template path.
		 * @param string               $template Basename.
		 * @param array<string, mixed> $args     Template args.
		 */
		do_action( 'hvnly_workspace_before_template', $path, $template, $args );

		if ( ! empty( $args ) ) {
			// phpcs:ignore WordPress.PHP.DontExtract.extract_extract -- intentional template args.
			extract( $args, EXTR_SKIP );
		}

		include $path;

		/**
		 * Fires after a Workspace template is included.
		 *
		 * @since 3.2.0
		 *
		 * @param string $path     Absolute template path.
		 * @param string $template Basename.
		 */
		do_action( 'hvnly_workspace_after_template', $path, $template );

		return true;
	}

	/**
	 * List allowed template basenames.
	 *
	 * @return string[]
	 */
	public function get_allowed_templates(): array {
		/**
		 * Filter allowed Workspace template basenames.
		 *
		 * @since 3.2.0
		 *
		 * @param string[] $templates Allowed basenames.
		 */
		return array_values(
			array_filter(
				(array) apply_filters( 'hvnly_workspace_allowed_templates', self::ALLOWED_TEMPLATES ),
				'is_string'
			)
		);
	}

	/**
	 * Sanitize template basename against allow-list.
	 *
	 * @param string $template Raw name.
	 * @return string Empty when invalid.
	 */
	private function sanitize_template_name( string $template ): string {
		$template = strtolower( basename( $template ) );
		$template = preg_replace( '/\.php$/', '', $template );
		$template = is_string( $template ) ? $template : '';

		if ( $template === '' || ! in_array( $template, $this->get_allowed_templates(), true ) ) {
			return '';
		}

		return $template;
	}
}
