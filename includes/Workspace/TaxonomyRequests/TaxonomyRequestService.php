<?php
/**
 * Taxonomy request domain service.
 *
 * @package HvnlyNab\Workspace\TaxonomyRequests
 * @since   3.3.0
 */

namespace HvnlyNab\Workspace\TaxonomyRequests;

use HvnlyNab\Workspace\Auth\AgentIdentityService;

defined( 'ABSPATH' ) || exit;

final class TaxonomyRequestService {

	/** @var TaxonomyRequestRepository */
	private $requests;

	/** @var TaxonomyRequestLogRepository */
	private $logs;

	/** @var TaxonomyRequestRegistry */
	private $registry;

	/** @var AgentIdentityService */
	private $identity;

	public function __construct(
		?TaxonomyRequestRepository $requests = null,
		?TaxonomyRequestLogRepository $logs = null,
		?TaxonomyRequestRegistry $registry = null,
		?AgentIdentityService $identity = null
	) {
		$this->requests = $requests ? $requests : new TaxonomyRequestRepository();
		$this->logs     = $logs ? $logs : new TaxonomyRequestLogRepository();
		$this->registry = $registry ? $registry : new TaxonomyRequestRegistry();
		$this->identity = $identity ? $identity : AgentIdentityService::get_instance();
	}

	/**
	 * @param int                  $user_id User.
	 * @param array<string, mixed> $data Input.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function submit( int $user_id, array $data ) {
		$user_id  = absint( $user_id );
		$taxonomy = $this->registry->resolve_taxonomy( (string) ( $data['taxonomy'] ?? '' ) );
		$policy   = $taxonomy ? $this->registry->get( $taxonomy ) : null;
		if ( ! $policy || ! taxonomy_exists( $taxonomy ) ) {
			return new \WP_Error( 'hvnly_taxonomy_not_requestable', __( 'This taxonomy does not accept requests.', 'havenlytics' ), array( 'status' => 400 ) );
		}
		if ( TaxonomyRequestConstants::POLICY_ADMIN_ONLY === $policy['policy'] ) {
			return new \WP_Error( 'hvnly_taxonomy_admin_only', __( 'Only an administrator may add terms to this taxonomy.', 'havenlytics' ), array( 'status' => 403 ) );
		}

		$display_name = $this->display_name( (string) ( $data['name'] ?? '' ) );
		$normalized   = $this->normalize_name( (string) ( $data['name'] ?? '' ) );
		if ( '' === $display_name || '' === $normalized ) {
			return new \WP_Error( 'hvnly_taxonomy_name_required', __( 'Request name is required.', 'havenlytics' ), array( 'status' => 400 ) );
		}
		if ( $this->length( $display_name ) > TaxonomyRequestConstants::MAX_NAME_LENGTH ) {
			return new \WP_Error( 'hvnly_taxonomy_name_too_long', __( 'Request name is too long.', 'havenlytics' ), array( 'status' => 400 ) );
		}

		$description = sanitize_textarea_field( (string) ( $data['description'] ?? '' ) );
		$reason      = sanitize_textarea_field( (string) ( $data['reason'] ?? '' ) );
		if ( $this->length( $description ) > TaxonomyRequestConstants::MAX_TEXT_LENGTH || $this->length( $reason ) > TaxonomyRequestConstants::MAX_TEXT_LENGTH ) {
			return new \WP_Error( 'hvnly_taxonomy_text_too_long', __( 'Description and reason must each be 2,000 characters or fewer.', 'havenlytics' ), array( 'status' => 400 ) );
		}

		$existing = $this->find_existing_term( $taxonomy, $display_name );
		if ( $existing ) {
			return new \WP_Error(
				'hvnly_taxonomy_term_exists',
				__( 'A matching term already exists. Select it instead.', 'havenlytics' ),
				array(
					'status'   => 409,
					'existing' => $existing,
				)
			);
		}

		$dedupe_key = hash( 'sha256', $taxonomy . "\0" . $normalized );
		$duplicate  = $this->requests->find_active_by_dedupe( $dedupe_key );
		if ( $duplicate ) {
			return new \WP_Error(
				'hvnly_taxonomy_request_duplicate',
				__( 'An active request for this term already exists.', 'havenlytics' ),
				array(
					'status'   => 409,
					'existing' => $duplicate,
				)
			);
		}

		$limit = max( 1, (int) apply_filters( 'hvnly_taxonomy_request_daily_limit', 10, $user_id ) );
		$since = gmdate( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS );
		if ( $this->requests->count_recent_for_user( $user_id, $since ) >= $limit ) {
			return new \WP_Error( 'hvnly_taxonomy_request_rate_limited', __( 'You have reached the taxonomy request limit. Please try again later.', 'havenlytics' ), array( 'status' => 429 ) );
		}

		$row = array(
			'user_id'         => $user_id,
			'agent_id'        => $this->identity->get_agent_id( $user_id ),
			'taxonomy'        => $taxonomy,
			'requested_name'  => $display_name,
			'normalized_name' => $normalized,
			'normalized_slug' => sanitize_title( $display_name ),
			'description'     => $description,
			'reason'          => $reason,
			'dedupe_key'      => $dedupe_key,
		);
		if ( ! $this->begin_transaction() ) {
			return $this->transaction_error();
		}
		$id = $this->requests->create( $row );
		if ( is_wp_error( $id ) ) {
			$this->rollback_transaction();
			return $id;
		}
		$request = $this->requests->find( $id );
		if ( ! $request ) {
			$this->rollback_transaction();
			return new \WP_Error( 'hvnly_taxonomy_request_missing', __( 'The request could not be loaded after saving.', 'havenlytics' ), array( 'status' => 500 ) );
		}
		$logged = $this->logs->append( $request, 'submitted', '', TaxonomyRequestConstants::STATUS_PENDING, $user_id, $reason );
		if ( is_wp_error( $logged ) ) {
			$this->rollback_transaction();
			return $logged;
		}
		if ( ! $this->commit_transaction() ) {
			$this->rollback_transaction();
			return $this->transaction_error();
		}

		$this->dispatch( $request, TaxonomyRequestConstants::STATUS_PENDING, '', $user_id );
		if ( TaxonomyRequestConstants::POLICY_AUTO === $policy['policy'] ) {
			do_action( 'hvnly_taxonomy_request_auto_queued', $id, $request );
		}
		return $request;
	}

	/**
	 * @return array<string, mixed>|\WP_Error
	 */
	public function approve( int $id, int $actor_id ) {
		$request = $this->requests->find( $id );
		if ( ! $request ) {
			return $this->not_found();
		}
		$auth = $this->authorize_admin( (string) $request['taxonomy'], false, $actor_id );
		if ( is_wp_error( $auth ) ) {
			return $auth;
		}
		$claimed = $this->requests->claim( $id, $actor_id );
		if ( is_wp_error( $claimed ) ) {
			return $claimed;
		}
		if ( ! $claimed ) {
			return new \WP_Error( 'hvnly_taxonomy_request_not_pending', __( 'This request is no longer pending.', 'havenlytics' ), array( 'status' => 409 ) );
		}
		$request = $this->requests->find( $id );
		if ( ! $request || TaxonomyRequestConstants::STATUS_PROCESSING !== $request['status'] ) {
			return new \WP_Error( 'hvnly_taxonomy_claim_lost', __( 'The request approval claim could not be verified.', 'havenlytics' ), array( 'status' => 409 ) );
		}

		$existing = $this->find_existing_term( (string) $request['taxonomy'], (string) $request['requested_name'] );
		if ( $existing ) {
			$result = $this->finish( $request, TaxonomyRequestConstants::STATUS_MERGED, $actor_id, 'merged', '', array( 'merged_term_id' => (int) $existing['termId'] ) );
			if ( is_wp_error( $result ) ) {
				$this->requests->restore_pending( $id );
			}
			return $result;
		}

		try {
			$created = wp_insert_term(
				(string) $request['requested_name'],
				(string) $request['taxonomy'],
				array( 'description' => (string) $request['description'] )
			);
		} catch ( \Throwable $exception ) {
			$this->requests->restore_pending( $id );
			return new \WP_Error( 'hvnly_taxonomy_term_create_exception', $exception->getMessage(), array( 'status' => 500 ) );
		}
		if ( is_wp_error( $created ) ) {
			if ( 'term_exists' === $created->get_error_code() ) {
				$term_id = absint( $created->get_error_data() );
				if ( $term_id > 0 ) {
					$result = $this->finish( $request, TaxonomyRequestConstants::STATUS_MERGED, $actor_id, 'merged', '', array( 'merged_term_id' => $term_id ) );
					if ( is_wp_error( $result ) ) {
						$this->requests->restore_pending( $id );
					}
					return $result;
				}
			}
			$this->requests->restore_pending( $id );
			return $created;
		}
		$created_term_id = absint( $created['term_id'] ?? 0 );
		$result          = $this->finish( $request, TaxonomyRequestConstants::STATUS_APPROVED, $actor_id, 'approved', '', array( 'created_term_id' => $created_term_id ) );
		if ( is_wp_error( $result ) ) {
			if ( $created_term_id > 0 ) {
				wp_delete_term( $created_term_id, (string) $request['taxonomy'] );
			}
			$this->requests->restore_pending( $id );
		}
		return $result;
	}

	/**
	 * @return array<string, mixed>|\WP_Error
	 */
	public function reject( int $id, int $actor_id, string $reason ) {
		$request = $this->requests->find( $id );
		if ( ! $request ) {
			return $this->not_found();
		}
		$auth = $this->authorize_admin( (string) $request['taxonomy'], false, $actor_id );
		if ( is_wp_error( $auth ) ) {
			return $auth;
		}
		$reason = sanitize_textarea_field( $reason );
		if ( '' === trim( $reason ) ) {
			return new \WP_Error( 'hvnly_taxonomy_rejection_reason_required', __( 'A rejection reason is required.', 'havenlytics' ), array( 'status' => 400 ) );
		}
		if ( $this->length( $reason ) > TaxonomyRequestConstants::MAX_TEXT_LENGTH ) {
			return new \WP_Error( 'hvnly_taxonomy_rejection_too_long', __( 'The rejection reason is too long.', 'havenlytics' ), array( 'status' => 400 ) );
		}
		return $this->finish( $request, TaxonomyRequestConstants::STATUS_REJECTED, $actor_id, 'rejected', $reason, array( 'rejection_reason' => $reason ) );
	}

	/**
	 * @return array<string, mixed>|\WP_Error
	 */
	public function merge( int $id, int $actor_id, int $term_id ) {
		$request = $this->requests->find( $id );
		if ( ! $request ) {
			return $this->not_found();
		}
		$auth = $this->authorize_admin( (string) $request['taxonomy'], false, $actor_id );
		if ( is_wp_error( $auth ) ) {
			return $auth;
		}
		$term = get_term( absint( $term_id ), (string) $request['taxonomy'] );
		if ( ! $term instanceof \WP_Term || is_wp_error( $term ) ) {
			return new \WP_Error( 'hvnly_taxonomy_merge_term_invalid', __( 'Select an existing term from the same taxonomy.', 'havenlytics' ), array( 'status' => 400 ) );
		}
		return $this->finish( $request, TaxonomyRequestConstants::STATUS_MERGED, $actor_id, 'merged', '', array( 'merged_term_id' => (int) $term->term_id ) );
	}

	/**
	 * Permanently delete a request while preserving its audit history.
	 *
	 * @return bool|\WP_Error
	 */
	public function delete( int $id, int $actor_id ) {
		$request = $this->requests->find( $id );
		if ( ! $request ) {
			return $this->not_found();
		}
		$auth = $this->authorize_admin( (string) $request['taxonomy'], true, $actor_id );
		if ( is_wp_error( $auth ) ) {
			return $auth;
		}
		if ( ! $this->begin_transaction() ) {
			return $this->transaction_error();
		}
		$logged = $this->logs->append( $request, 'deleted', (string) $request['status'], TaxonomyRequestConstants::STATUS_DELETED, $actor_id );
		if ( is_wp_error( $logged ) ) {
			$this->rollback_transaction();
			return $logged;
		}
		if ( ! $this->requests->delete( $id ) ) {
			$this->rollback_transaction();
			return new \WP_Error( 'hvnly_taxonomy_delete_failed', __( 'Unable to delete this request.', 'havenlytics' ), array( 'status' => 500 ) );
		}
		if ( ! $this->commit_transaction() ) {
			$this->rollback_transaction();
			return $this->transaction_error();
		}
		$deleted           = $request;
		$deleted['status'] = TaxonomyRequestConstants::STATUS_DELETED;
		$this->dispatch( $deleted, TaxonomyRequestConstants::STATUS_DELETED, (string) $request['status'], $actor_id );
		return true;
	}

	/**
	 * Search terms for suggestions.
	 *
	 * @return array<int, array<string, mixed>>|\WP_Error
	 */
	public function suggestions( string $taxonomy_value, string $query ): array {
		$taxonomy = $this->registry->resolve_taxonomy( $taxonomy_value );
		if ( '' === $taxonomy || ! taxonomy_exists( $taxonomy ) ) {
			return array();
		}
		$query = $this->display_name( $query );
		if ( '' === $query ) {
			return array();
		}
		$terms = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => false,
				'number'     => 5,
				'search'     => $query,
				'orderby'    => 'name',
				'order'      => 'ASC',
			)
		);
		if ( is_wp_error( $terms ) ) {
			return array();
		}
		return array_map(
			static function ( \WP_Term $term ): array {
				return array(
					'termId' => (int) $term->term_id,
					'name' => $term->name,
					'slug' => $term->slug,
				);
			},
			$terms
		);
	}

	/**
	 * @return array<string, mixed>|\WP_Error
	 */
	private function finish( array $request, string $to, int $actor_id, string $action, string $reason = '', array $extra = array() ) {
		$from = (string) $request['status'];
		if ( ! in_array( $from, array( TaxonomyRequestConstants::STATUS_PENDING, TaxonomyRequestConstants::STATUS_PROCESSING ), true ) ) {
			return new \WP_Error( 'hvnly_taxonomy_request_terminal', __( 'This request has already been resolved.', 'havenlytics' ), array( 'status' => 409 ) );
		}
		if ( ! $this->begin_transaction() ) {
			return $this->transaction_error();
		}
		$changed = $this->requests->transition( (int) $request['id'], $from, $to, $actor_id, $extra );
		if ( is_wp_error( $changed ) ) {
			$this->rollback_transaction();
			return $changed;
		}
		if ( ! $changed ) {
			$this->rollback_transaction();
			return new \WP_Error( 'hvnly_taxonomy_request_conflict', __( 'This request changed while it was being reviewed.', 'havenlytics' ), array( 'status' => 409 ) );
		}
		$updated = $this->requests->find( (int) $request['id'] );
		if ( ! $updated ) {
			$this->rollback_transaction();
			return $this->not_found();
		}
		$logged = $this->logs->append( $updated, $action, $from, $to, $actor_id, $reason, $extra );
		if ( is_wp_error( $logged ) ) {
			$this->rollback_transaction();
			return $logged;
		}
		if ( ! $this->commit_transaction() ) {
			$this->rollback_transaction();
			return $this->transaction_error();
		}
		$this->dispatch( $updated, $to, $from, $actor_id );
		return $updated;
	}

	private function dispatch( array $request, string $to, string $from, int $actor_id ): void {
		do_action( 'hvnly_taxonomy_request_status_changed', (int) $request['id'], $to, $from, $actor_id, $request );
	}

	/**
	 * @return true|\WP_Error
	 */
	private function authorize_admin( string $taxonomy, bool $delete, int $actor_id ) {
		if ( $actor_id <= 0 || get_current_user_id() !== $actor_id ) {
			return new \WP_Error( 'hvnly_taxonomy_review_forbidden', __( 'You are not authorized to review taxonomy requests.', 'havenlytics' ), array( 'status' => 403 ) );
		}
		if ( current_user_can( 'manage_options' ) ) {
			return true;
		}
		if ( $delete ) {
			return new \WP_Error( 'hvnly_taxonomy_delete_forbidden', __( 'Only an administrator may permanently delete requests.', 'havenlytics' ), array( 'status' => 403 ) );
		}
		$object = get_taxonomy( $taxonomy );
		$cap    = $object && isset( $object->cap->manage_terms ) ? (string) $object->cap->manage_terms : 'manage_categories';
		return current_user_can( $cap )
			? true
			: new \WP_Error( 'hvnly_taxonomy_review_forbidden', __( 'You are not authorized to manage this taxonomy.', 'havenlytics' ), array( 'status' => 403 ) );
	}

	/**
	 * @return array<string, mixed>|null
	 */
	private function find_existing_term( string $taxonomy, string $name ): ?array {
		$slug = sanitize_title( $name );
		$term = get_term_by( 'slug', $slug, $taxonomy );
		if ( ! $term ) {
			$term = get_term_by( 'name', $name, $taxonomy );
		}
		return $term instanceof \WP_Term
			? array(
				'termId' => (int) $term->term_id,
				'name' => $term->name,
				'slug' => $term->slug,
			)
			: null;
	}

	private function display_name( string $value ): string {
		$value = trim( $value );
		if ( class_exists( '\Normalizer' ) ) {
			$normalized = \Normalizer::normalize( $value, \Normalizer::FORM_C );
			if ( is_string( $normalized ) ) {
				$value = $normalized;
			}
		}
		$value = (string) preg_replace( '/\s+/u', ' ', $value );
		return sanitize_text_field( $value );
	}

	private function normalize_name( string $value ): string {
		$value = $this->display_name( $value );
		$value = function_exists( 'mb_strtolower' ) ? mb_strtolower( $value, 'UTF-8' ) : strtolower( $value );
		return remove_accents( $value );
	}

	private function length( string $value ): int {
		return function_exists( 'mb_strlen' ) ? mb_strlen( $value, 'UTF-8' ) : strlen( $value );
	}

	private function begin_transaction(): bool {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return false !== $wpdb->query( 'START TRANSACTION' );
	}

	private function commit_transaction(): bool {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return false !== $wpdb->query( 'COMMIT' );
	}

	private function rollback_transaction(): void {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query( 'ROLLBACK' );
	}

	private function transaction_error(): \WP_Error {
		return new \WP_Error( 'hvnly_taxonomy_transaction_failed', __( 'The taxonomy request transaction could not be completed.', 'havenlytics' ), array( 'status' => 500 ) );
	}

	private function not_found(): \WP_Error {
		return new \WP_Error( 'hvnly_taxonomy_request_not_found', __( 'Taxonomy request not found.', 'havenlytics' ), array( 'status' => 404 ) );
	}
}
