<?php
/**
 * Agent module bootstrap.
 *
 * @package HvnlyNab\Agent
 * @since   3.0.2
 */

namespace HvnlyNab\Agent;

use HvnlyNab\Agent\Contracts\AgentRepositoryInterface;
use HvnlyNab\Core\PluginGutenbergSupport;

defined( 'ABSPATH' ) || exit;

/**
 * Initializes Agent CPT, metaboxes, and helpers.
 *
 * @since 3.0.2
 */
final class AgentBootstrap {

	/** @var self|null */
	private static $instance = null;

	/** @var AgentRepositoryInterface|null */
	private $repository;

	/** @var bool */
	private $initialized = false;

	/**
	 * @return self
	 */
	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->init();
	}

	/**
	 * @return void
	 */
	public function init(): void {
		if ( $this->initialized ) {
			return;
		}

		$this->load_functions();

		// Ensure WordPress core avatar APIs use AvatarService (Users list, etc.).
		if ( class_exists( '\HvnlyNab\Common\AvatarService' ) ) {
			\HvnlyNab\Common\AvatarService::register_hooks();
		}

		// Identity write guard lives in Workspace; register early if available.
		if ( class_exists( '\HvnlyNab\Workspace\Auth\AgentProvisioner' ) ) {
			\HvnlyNab\Workspace\Auth\AgentProvisioner::register_write_guard();
		}

		PluginGutenbergSupport::register();

		new AgentPostType();
		new AgentAdminMenu();
		new AgentMetabox();
		new AgentAgencyTaxonomy();
		new PropertyAgentAssignment();

		if ( is_admin() && class_exists( '\HvnlyNab\Agent\Admin\AgentManagementAdminList' ) ) {
			( new \HvnlyNab\Agent\Admin\AgentManagementAdminList() )->register();
		}

		if ( is_admin() && class_exists( '\HvnlyNab\Agent\Admin\AgentDeleteWizardPage' ) ) {
			( new \HvnlyNab\Agent\Admin\AgentDeleteWizardPage() )->register();
		}

		AgentArchiveQuery::register();
		AssignedListingQueryScope::register();

		$this->repository = $this->resolve_repository();

		if ( is_admin() ) {
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		}

		$this->initialized = true;

		/**
		 * Fires when the Agent module bootstrap completes.
		 *
		 * @since 3.0.2
		 *
		 * @param AgentBootstrap $bootstrap Bootstrap instance.
		 */
		do_action( 'hvnly_agent_init', $this );
	}

	/**
	 * @return AgentRepositoryInterface
	 */
	public function get_repository(): AgentRepositoryInterface {
		if ( ! $this->repository ) {
			$this->repository = $this->resolve_repository();
		}

		return $this->repository;
	}

	/**
	 * @return void
	 */
	private function load_functions(): void {
		$file = HVNLYNAB_INCLUDES . '/Functions/agent-functions.php';
		if ( file_exists( $file ) ) {
			require_once $file;
		}
	}

	/**
	 * @return AgentRepositoryInterface
	 */
	private function resolve_repository(): AgentRepositoryInterface {
		$class = AgentRepository::class;

		/**
		 * Filter the Agent repository class.
		 *
		 * @since 3.0.2
		 *
		 * @param string $class Repository class name.
		 */
		$class = apply_filters( 'hvnly_agent_repository_class', $class );

		if ( class_exists( $class ) ) {
			$instance = new $class();
			if ( $instance instanceof AgentRepositoryInterface ) {
				return $instance;
			}
		}

		return new AgentRepository();
	}

	/**
	 * @param string $hook_suffix Admin hook suffix.
	 * @return void
	 */
	public function enqueue_admin_assets( string $hook_suffix ): void {
		unset( $hook_suffix );
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		if ( ! $screen ) {
			return;
		}

		$is_agent_screen = AgentConstants::POST_TYPE === $screen->post_type;
		$is_agency_tax   = isset( $screen->taxonomy ) && AgentConstants::TAXONOMY_AGENCY === $screen->taxonomy;

		if ( ! $is_agent_screen && ! $is_agency_tax ) {
			return;
		}

		$ver = defined( 'HVNLYNAB_VERSION' ) ? HVNLYNAB_VERSION : '3.0.2';
		wp_register_style(
			'hvnly-agent-admin',
			HVNLYNAB_ASSETS_URL . '/admin/css/hvnly-agent-admin.css',
			array(),
			$ver
		);
		wp_enqueue_style( 'hvnly-agent-admin' );
	}
}
