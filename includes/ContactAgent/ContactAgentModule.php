<?php
/**
 * Contact Agent dependency container.
 *
 * @package HvnlyNab\ContactAgent
 * @since   3.0.2
 */

namespace HvnlyNab\ContactAgent;

use HvnlyNab\ContactAgent\Admin\InquiryAdminPage;
use HvnlyNab\ContactAgent\Admin\InquiryListTable;
use HvnlyNab\ContactAgent\Contracts\InquiryNotifierInterface;
use HvnlyNab\ContactAgent\Contracts\InquiryRepositoryInterface;

defined( 'ABSPATH' ) || exit;

/**
 * Wires Contact Agent services for later phases without registering runtime behavior.
 *
 * @since 3.0.2
 */
final class ContactAgentModule {

	/**
	 * @var self|null
	 */
	private static $instance = null;

	/**
	 * @var InquiryRepositoryInterface
	 */
	private $repository;

	/**
	 * @var InquiryValidator
	 */
	private $validator;

	/**
	 * @var InquiryNotifierInterface
	 */
	private $notifier;

	/**
	 * @var RateLimiter
	 */
	private $rate_limiter;

	/**
	 * @var SpamGuard
	 */
	private $spam_guard;

	/**
	 * @var AjaxHandler
	 */
	private $ajax_handler;

	/**
	 * @var InquiryListTable
	 */
	private $admin_list_table;

	/**
	 * @var InquiryAdminPage
	 */
	private $admin_page;

	/**
	 * @var bool
	 */
	private $bootstrapped = false;

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
	 * Reset singleton (for tests only).
	 *
	 * @return void
	 */
	public static function reset_instance(): void {
		self::$instance = null;
	}

	/**
	 * Private constructor — use get_instance().
	 */
	private function __construct() {
		$this->wire_dependencies();
	}

	/**
	 * Bootstrap module services (idempotent).
	 *
	 * @return void
	 */
	public function bootstrap(): void {
		if ( $this->bootstrapped ) {
			return;
		}

		/**
		 * Allow extensions to replace Contact Agent service implementations before boot completes.
		 *
		 * @since 3.0.2
		 *
		 * @param ContactAgentModule $module Module instance.
		 */
		do_action( 'hvnly_contact_agent_before_bootstrap', $this );

		$this->bootstrapped = true;

		/**
		 * Fires after Contact Agent services are wired (Phase 1 scaffold).
		 *
		 * @since 3.0.2
		 *
		 * @param ContactAgentModule $module Module instance.
		 */
		do_action( 'hvnly_contact_agent_bootstrapped', $this );
	}

	/**
	 * Register default service implementations.
	 *
	 * @return void
	 */
	private function wire_dependencies(): void {
		$services = array(
			'repository'       => InquiryRepository::class,
			'validator'        => InquiryValidator::class,
			'notifier'         => InquiryNotifier::class,
			'rate_limiter'     => RateLimiter::class,
			'spam_guard'       => SpamGuard::class,
			'admin_list_table' => InquiryListTable::class,
			'admin_page'       => InquiryAdminPage::class,
		);

		/**
		 * Filter default Contact Agent service class map.
		 *
		 * @since 3.0.2
		 *
		 * @param array<string, string> $services Class map keyed by service id.
		 */
		$services = apply_filters( 'hvnly_contact_agent_service_classes', $services );

		$this->repository       = $this->resolve_service( $services['repository'] ?? InquiryRepository::class, InquiryRepositoryInterface::class );
		$this->validator        = $this->resolve_service( $services['validator'] ?? InquiryValidator::class, InquiryValidator::class );
		$this->notifier         = $this->resolve_service( $services['notifier'] ?? InquiryNotifier::class, InquiryNotifierInterface::class );
		$this->rate_limiter     = $this->resolve_service( $services['rate_limiter'] ?? RateLimiter::class, RateLimiter::class );
		$this->spam_guard       = $this->resolve_service( $services['spam_guard'] ?? SpamGuard::class, SpamGuard::class );
		$this->admin_list_table = $this->resolve_service( $services['admin_list_table'] ?? InquiryListTable::class, InquiryListTable::class );
		$this->admin_page       = $this->resolve_service( $services['admin_page'] ?? InquiryAdminPage::class, InquiryAdminPage::class );

		$this->ajax_handler = new AjaxHandler( $this );
	}

	/**
	 * Instantiate a service class if it exists.
	 *
	 * @param string $class_name  Class name.
	 * @param string $required_type Expected instance type or interface.
	 * @return object
	 */
	private function resolve_service( string $class_name, string $required_type ) {
		if ( class_exists( $class_name ) ) {
			$instance = new $class_name();
			if ( $instance instanceof $required_type ) {
				return $instance;
			}
		}

		if ( InquiryRepositoryInterface::class === $required_type ) {
			return new InquiryRepository();
		}
		if ( InquiryNotifierInterface::class === $required_type ) {
			return new InquiryNotifier();
		}
		if ( InquiryValidator::class === $required_type ) {
			return new InquiryValidator();
		}
		if ( RateLimiter::class === $required_type ) {
			return new RateLimiter();
		}
		if ( SpamGuard::class === $required_type ) {
			return new SpamGuard();
		}
		if ( InquiryListTable::class === $required_type ) {
			return new InquiryListTable();
		}

		return new InquiryAdminPage();
	}

	/**
	 * @return InquiryRepositoryInterface
	 */
	public function get_repository(): InquiryRepositoryInterface {
		return $this->repository;
	}

	/**
	 * @return InquiryValidator
	 */
	public function get_validator(): InquiryValidator {
		return $this->validator;
	}

	/**
	 * @return InquiryNotifierInterface
	 */
	public function get_notifier(): InquiryNotifierInterface {
		return $this->notifier;
	}

	/**
	 * @return RateLimiter
	 */
	public function get_rate_limiter(): RateLimiter {
		return $this->rate_limiter;
	}

	/**
	 * @return SpamGuard
	 */
	public function get_spam_guard(): SpamGuard {
		return $this->spam_guard;
	}

	/**
	 * @return AjaxHandler
	 */
	public function get_ajax_handler(): AjaxHandler {
		return $this->ajax_handler;
	}

	/**
	 * @return InquiryListTable
	 */
	public function get_admin_list_table(): InquiryListTable {
		return $this->admin_list_table;
	}

	/**
	 * @return InquiryAdminPage
	 */
	public function get_admin_page(): InquiryAdminPage {
		return $this->admin_page;
	}

	/**
	 * Whether the module finished bootstrap.
	 *
	 * @return bool
	 */
	public function is_bootstrapped(): bool {
		return $this->bootstrapped;
	}
}
