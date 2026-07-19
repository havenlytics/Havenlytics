<?php
/**
 * Workspace module constants.
 *
 * @package HvnlyNab\Workspace
 * @since   3.2.0
 */

namespace HvnlyNab\Workspace;

defined( 'ABSPATH' ) || exit;

/**
 * Shared constants for the Havenlytics Workspace.
 *
 * @since 3.2.0
 */
final class WorkspaceConstants {

	/**
	 * Module semantic version (asset cache busting + localize).
	 *
	 * @var string
	 */
	public const MODULE_VERSION = '3.3.1';

	/**
	 * Future Workspace REST namespace (no routes in Sprint 1).
	 *
	 * @var string
	 */
	public const REST_NAMESPACE = 'hvnly/v1';

	/**
	 * Settings option key.
	 *
	 * @var string
	 */
	public const OPTION_KEY = 'hvnly_workspace_settings';

	/**
	 * Shortcode tag (matches product docs).
	 *
	 * @var string
	 */
	public const SHORTCODE = 'hvnly_agent_dashboard';

	/**
	 * Default page slug for /agent-dashboard/.
	 *
	 * @var string
	 */
	public const PAGE_SLUG = 'agent-dashboard';

	/**
	 * Post meta key storing the Workspace page ID.
	 *
	 * @var string
	 */
	public const PAGE_ID_OPTION = 'hvnly_workspace_page_id';

	/**
	 * Theme override subdirectory under havenlytics/.
	 *
	 * @var string
	 */
	public const TEMPLATE_SUBDIR = 'agent-portal';

	/**
	 * React mount element id.
	 *
	 * @var string
	 */
	public const MOUNT_ID = 'hvnly-ws-root';

	/**
	 * Script handle for the Workspace SPA bundle.
	 *
	 * @var string
	 */
	public const SCRIPT_HANDLE = 'hvnly-ws-app';

	/**
	 * Style handle for the Workspace SPA bundle CSS.
	 *
	 * @var string
	 */
	public const STYLE_HANDLE = 'hvnly-ws-app';

	/**
	 * Shared UI primitives style handle (future).
	 *
	 * @var string
	 */
	public const UI_STYLE_HANDLE = 'hvnly-ui-base';

	/**
	 * Localized JS object name.
	 *
	 * @var string
	 */
	public const LOCALIZE_OBJECT = 'HvnlyWorkspaceData';

	/**
	 * Build folder under HVNLYNAB_BUILD_*.
	 *
	 * @var string
	 */
	public const BUILD_FOLDER = 'workspace';

	/**
	 * Webpack asset PHP filename.
	 *
	 * @var string
	 */
	public const ASSET_FILE = 'workspace.asset.php';

	/**
	 * Body class when Workspace shell is active.
	 *
	 * @var string
	 */
	public const BODY_CLASS = 'hvnly-ws';
}
