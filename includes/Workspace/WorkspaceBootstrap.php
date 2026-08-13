<?php
/**
 * Workspace module bootstrap.
 *
 * Wires settings, page, shortcode, assets, identity, and portal REST.
 * Does not register business modules (properties, CRM, analytics UI).
 *
 * @package HvnlyNab\Workspace
 * @since   3.2.0
 */

namespace HvnlyNab\Workspace;

use HvnlyNab\Workspace\Api\AnalyticsController;
use HvnlyNab\Workspace\Api\DashboardController;
use HvnlyNab\Workspace\Api\InquiriesController;
use HvnlyNab\Workspace\Api\MeController;
use HvnlyNab\Workspace\Api\NotificationsController;
use HvnlyNab\Workspace\Api\ProfileController;
use HvnlyNab\Workspace\Api\PropertiesController;
use HvnlyNab\Workspace\Api\PropertyMediaController;
use HvnlyNab\Workspace\Api\PropertyListingStatusBridge;
use HvnlyNab\Workspace\Api\PropertyPendingReviewCounter;
use HvnlyNab\Workspace\Api\PropertyWorkflowAdminActions;
use HvnlyNab\Workspace\Api\SettingsController;
use HvnlyNab\Workspace\Auth\AgentActivityTracker;
use HvnlyNab\Workspace\Auth\AgentAdminChrome;
use HvnlyNab\Workspace\Auth\AgentIdentityAdminBridge;
use HvnlyNab\Workspace\Auth\AgentIdentityService;
use HvnlyNab\Workspace\Auth\AgentProvisioner;
use HvnlyNab\Workspace\Auth\CapabilityRegistrar;
use HvnlyNab\Workspace\Auth\IdentityAdminColumns;
use HvnlyNab\Workspace\Auth\PortalAuthorization;
use HvnlyNab\Workspace\Auth\RegistrationAdminActions;
use HvnlyNab\Workspace\Auth\RegistrationEmailNotifier;
use HvnlyNab\Workspace\Auth\SessionAuthController;
use HvnlyNab\Workspace\Health\AgentIdentityHealthAdminPage;
use HvnlyNab\Workspace\Health\AgentIdentityIntegrityCli;
use HvnlyNab\Workspace\TaxonomyRequests\Admin\TaxonomyRequestsAdminPage;
use HvnlyNab\Workspace\TaxonomyRequests\Api\TaxonomyRequestsController;
use HvnlyNab\Workspace\TaxonomyRequests\TaxonomyRequestNotifier;
use HvnlyNab\Workspace\TaxonomyRequests\TaxonomyRequestPendingCounter;
use HvnlyNab\Workspace\TaxonomyRequests\TaxonomyRequestSchema;

defined( 'ABSPATH' ) || exit;

/**
 * Frontend service entry for Workspace.
 *
 * @since 3.2.0
 */
final class WorkspaceBootstrap {

	/**
	 * @var self|null
	 */
	private static $instance = null;

	/**
	 * @var WorkspaceAssets|null
	 */
	private $assets;

	/**
	 * @var WorkspaceTemplateLoader|null
	 */
	private $templates;

	/**
	 * @var WorkspacePage|null
	 */
	private $page;

	/**
	 * @var WorkspaceShortcode|null
	 */
	private $shortcode;

	/**
	 * @var AgentIdentityService|null
	 */
	private $identity;

	/**
	 * @var PortalAuthorization|null
	 */
	private $authorization;

	/**
	 * @var MeController|null
	 */
	private $me_controller;

	/**
	 * @var PropertiesController|null
	 */
	private $properties_controller;

	/**
	 * @var PropertyMediaController|null
	 */
	private $media_controller;

	/**
	 * @var DashboardController|null
	 */
	private $dashboard_controller;

	/**
	 * @var AnalyticsController|null
	 */
	private $analytics_controller;

	/**
	 * @var ProfileController|null
	 */
	private $profile_controller;

	/**
	 * @var InquiriesController|null
	 */
	private $inquiries_controller;

	/**
	 * @var SettingsController|null
	 */
	private $settings_controller;

	/**
	 * @var NotificationsController|null
	 */
	private $notifications_controller;

	/**
	 * Static so admin + Frontend construction cannot double-register hooks.
	 *
	 * @var bool
	 */
	private static $initialized = false;

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
	 * Constructor — invoked by Frontend service manager or admin Bootstrap.
	 */
	public function __construct() {
		if ( null === self::$instance ) {
			self::$instance = $this;
		}
		$this->init();
	}

	/**
	 * Initialize Workspace wiring.
	 *
	 * @return void
	 */
	public function init(): void {
		if ( self::$initialized ) {
			return;
		}
		self::$initialized = true;

		// Register Agent wp-admin redirect FIRST so an unrelated later failure cannot
		// leave Agents able to reach the standard Dashboard. This is the centralized
		// redirect policy (also hooks login_redirect).
		$agent_chrome = new AgentAdminChrome();
		$agent_chrome->register();

		// Global Enable Workspace kill switch — REST + feature availability SSOT.
		WorkspaceAvailability::register_hooks();

		// Block unauthorized writes to _hvnly_agent_linked_user_id (Sprint 19D).
		AgentProvisioner::register_write_guard();

		$this->templates                = new WorkspaceTemplateLoader();
		$this->page                     = new WorkspacePage();
		$this->assets                   = new WorkspaceAssets( $this->page );
		$this->shortcode                = new WorkspaceShortcode( $this->templates );
		$this->identity                 = AgentIdentityService::get_instance();
		$this->authorization            = new PortalAuthorization( $this->identity );
		$this->me_controller            = new MeController( $this->identity, $this->authorization );
		$this->properties_controller    = new PropertiesController( $this->identity, $this->authorization );
		$this->media_controller         = new PropertyMediaController( $this->identity, $this->authorization );
		$this->dashboard_controller     = new DashboardController( $this->identity, $this->authorization );
		$this->analytics_controller     = new AnalyticsController( $this->identity, $this->authorization );
		$this->profile_controller       = new ProfileController( $this->identity, $this->authorization );
		$this->inquiries_controller     = new InquiriesController( $this->identity, $this->authorization );
		$this->settings_controller      = new SettingsController( $this->identity, $this->authorization );
		$this->notifications_controller = new NotificationsController( $this->identity, $this->authorization );
		$taxonomy_requests_controller   = new TaxonomyRequestsController( $this->identity, $this->authorization );
		// Guest mode takes no identity/authorization: a guest is never a
		// WordPress user, so there is no identity to resolve.
		$guest_controller = new Api\GuestController();

		$this->page->register_hooks();
		$this->assets->register_hooks();
		$this->shortcode->register();
		$this->me_controller->register();
		$this->properties_controller->register();
		$this->media_controller->register();
		$this->dashboard_controller->register();
		$this->analytics_controller->register();
		$this->profile_controller->register();
		$this->inquiries_controller->register();
		$this->settings_controller->register();
		$this->notifications_controller->register();
		$taxonomy_requests_controller->register();
		$guest_controller->register();

		if ( ! TaxonomyRequestSchema::tables_exist() ) {
			TaxonomyRequestSchema::create_tables();
		}

		$taxonomy_requests_admin = new TaxonomyRequestsAdminPage();
		$taxonomy_requests_admin->register();

		$taxonomy_request_notifier = new TaxonomyRequestNotifier();
		$taxonomy_request_notifier->register();

		// Admin menu badge cache invalidation (3.4.0). Registered outside
		// is_admin() on purpose: submissions arrive over REST, so the bust
		// hook must be live on every request type.
		TaxonomyRequestPendingCounter::register();

		$caps = new CapabilityRegistrar();
		$caps->register_hooks();

		$session_auth = new SessionAuthController();
		$session_auth->register();

		$activity_tracker = new AgentActivityTracker();
		$activity_tracker->register();

		$reg_actions = new RegistrationAdminActions();
		$reg_actions->register();

		$email_notifier = new RegistrationEmailNotifier();
		$email_notifier->register();

		// 3.3.0 — Identity/email verification: audit trail + verification emails.
		$identity_audit = new \HvnlyNab\Workspace\Identity\IdentityVerificationAudit();
		$identity_audit->register();

		$email_verification_notifier = new \HvnlyNab\Workspace\Identity\EmailVerificationNotifier();
		$email_verification_notifier->register();

		$email_change_reverifier = new \HvnlyNab\Workspace\Identity\EmailChangeReverifier();
		$email_change_reverifier->register();

		$email_verification_admin = new \HvnlyNab\Workspace\Identity\EmailVerificationAdminActions();
		$email_verification_admin->register();

		// One-time email-verification back-fill on upgrade. Idempotent and self-guarded
		// via the OPTION_MIGRATED stamp, so this is a cheap option check after it runs.
		add_action(
			'admin_init',
			static function () {
				( new \HvnlyNab\Workspace\Identity\EmailVerificationFactor() )->backfill_existing( false );
			}
		);

		// Sprint 24C — password reset / password changed / invitation emails.
		$account_notifier = new \HvnlyNab\Workspace\Auth\WorkspaceAccountNotifier();
		$account_notifier->register();

		// Sprint 24C — listing lifecycle emails (submitted / approved / published / rejected).
		$property_email_notifier = new \HvnlyNab\Email\Notifiers\PropertyWorkflowNotifier();
		$property_email_notifier->register();

		// Sprint 28A — route classic/REST/Quick/Bulk status writes onto listing meta
		// so PropertyWorkflowNotifier + in-app listener stay the single dispatch path.
		$listing_status_bridge = new PropertyListingStatusBridge();
		$listing_status_bridge->register();

		$admin_cols = new IdentityAdminColumns();
		$admin_cols->register();

		$identity_bridge = new AgentIdentityAdminBridge();
		$identity_bridge->register();

		$prop_workflow_admin = new PropertyWorkflowAdminActions();
		$prop_workflow_admin->register();

		// Pending-review count on Havenlytics / Properties admin menu (Comments-style badge).
		$prop_pending_badge = new PropertyPendingReviewCounter();
		$prop_pending_badge->register();

		$health_page = new AgentIdentityHealthAdminPage();
		$health_page->register();

		AgentIdentityIntegrityCli::register();

		WorkspaceSettings::register_hooks();

		add_action( 'init', array( $this, 'register_assets_early' ), 20 );

		/**
		 * Fires when Workspace bootstrap completes.
		 *
		 * @since 3.2.0
		 *
		 * @param WorkspaceBootstrap $bootstrap Bootstrap instance.
		 */
		do_action( 'hvnly_workspace_init', $this );
	}

	/**
	 * Register asset handles on init.
	 *
	 * @return void
	 */
	public function register_assets_early(): void {
		if ( $this->assets ) {
			$this->assets->register();
		}
	}

	/**
	 * @return WorkspaceAssets|null
	 */
	public function get_assets(): ?WorkspaceAssets {
		return $this->assets;
	}

	/**
	 * @return WorkspaceTemplateLoader|null
	 */
	public function get_templates(): ?WorkspaceTemplateLoader {
		return $this->templates;
	}

	/**
	 * @return WorkspacePage|null
	 */
	public function get_page(): ?WorkspacePage {
		return $this->page;
	}

	/**
	 * @return AgentIdentityService|null
	 */
	public function get_identity(): ?AgentIdentityService {
		return $this->identity;
	}

	/**
	 * @return PortalAuthorization|null
	 */
	public function get_authorization(): ?PortalAuthorization {
		return $this->authorization;
	}

	/**
	 * Module readiness metadata.
	 *
	 * @return array<string, mixed>
	 */
	public function get_status(): array {
		return array(
			'initialized'   => self::$initialized,
			'enabled'       => WorkspaceSettings::is_enabled(),
			'moduleVersion' => WorkspaceConstants::MODULE_VERSION,
			'restNamespace' => WorkspaceConstants::REST_NAMESPACE,
			'mountId'       => WorkspaceConstants::MOUNT_ID,
			'pageId'        => $this->page ? $this->page->get_page_id() : 0,
			'buildReady'    => $this->assets ? $this->assets->is_build_available() : false,
			'loggedIn'      => $this->identity ? $this->identity->is_logged_in() : false,
		);
	}
}
