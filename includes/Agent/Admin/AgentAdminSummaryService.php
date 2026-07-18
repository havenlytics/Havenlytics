<?php
/**
 * Batched Agent admin list summaries (properties, inquiries, health, completeness).
 *
 * @package HvnlyNab\Agent\Admin
 * @since   3.2.0
 */

namespace HvnlyNab\Agent\Admin;

use HvnlyNab\Agent\AgentConstants;
use HvnlyNab\Agent\AgentProfileCompletenessService;
use HvnlyNab\ContactAgent\ContactAgentConstants;
use HvnlyNab\ContactAgent\Database\InquirySchema;
use HvnlyNab\Workspace\Api\PropertyFormMapper;
use HvnlyNab\Workspace\Auth\AgentActivityTracker;
use HvnlyNab\Workspace\Auth\WorkspaceRegistrationStatus;
use HvnlyNab\Workspace\Health\AgentIdentityHealthAdminPage;

defined( 'ABSPATH' ) || exit;

/**
 * Request-scoped batch loader — avoids N+1 on Agents list.
 *
 * @since 3.2.0
 */
final class AgentAdminSummaryService {

	/**
	 * @var array<int, array<string, mixed>>
	 */
	private $cache = array();

	/**
	 * @var AgentProfileCompletenessService
	 */
	private $completeness;

	/**
	 * @param AgentProfileCompletenessService|null $completeness Completeness.
	 */
	public function __construct( ?AgentProfileCompletenessService $completeness = null ) {
		$this->completeness = $completeness ? $completeness : new AgentProfileCompletenessService();
	}

	/**
	 * Prime summaries for a page of agent IDs.
	 *
	 * @param int[] $agent_ids Agent CPT IDs.
	 * @return void
	 */
	public function prime( array $agent_ids ): void {
		$agent_ids = array_values( array_unique( array_filter( array_map( 'absint', $agent_ids ) ) ) );
		$missing   = array();
		foreach ( $agent_ids as $id ) {
			if ( $id > 0 && ! isset( $this->cache[ $id ] ) ) {
				$missing[] = $id;
			}
		}
		if ( empty( $missing ) ) {
			return;
		}

		$props     = $this->batch_property_counts( $missing );
		$inquiries = $this->batch_inquiry_counts( $missing );

		foreach ( $missing as $agent_id ) {
			$user_id = absint( get_post_meta( $agent_id, AgentConstants::META_LINKED_USER_ID, true ) );
			$user    = $user_id > 0 ? get_userdata( $user_id ) : false;
			$status  = WorkspaceRegistrationStatus::get_for_agent( $agent_id );
			$comp    = $this->completeness->evaluate( $agent_id );
			$health  = $this->detect_health_flags( $agent_id, $user_id, $user ? true : false );
			$activity = $user_id > 0 ? AgentActivityTracker::get_for_user( $user_id ) : array(
				'login'     => '',
				'activity'  => '',
				'workspace' => '',
			);

			$p = isset( $props[ $agent_id ] ) ? $props[ $agent_id ] : $this->empty_property_counts();
			$i = isset( $inquiries[ $agent_id ] ) ? $inquiries[ $agent_id ] : $this->empty_inquiry_counts();

			$this->cache[ $agent_id ] = array(
				'agent_id'      => $agent_id,
				'user_id'       => $user_id,
				'username'      => $user ? (string) $user->user_login : '',
				'user_email'    => $user ? (string) $user->user_email : '',
				'lifecycle'     => $status,
				'workspace'     => WorkspaceRegistrationStatus::workspace_state_label( $user_id > 0 ? $user_id : null ),
				'properties'    => $p,
				'inquiries'     => $i,
				'completeness'  => $comp,
				'health'        => $health,
				'activity'      => $activity,
				'agency_names'  => $this->agency_names( $agent_id ),
			);
		}
	}

	/**
	 * @param int $agent_id Agent ID.
	 * @return array<string, mixed>
	 */
	public function get( int $agent_id ): array {
		$agent_id = absint( $agent_id );
		if ( $agent_id <= 0 ) {
			return array();
		}
		if ( ! isset( $this->cache[ $agent_id ] ) ) {
			$this->prime( array( $agent_id ) );
		}
		return isset( $this->cache[ $agent_id ] ) ? $this->cache[ $agent_id ] : array();
	}

	/**
	 * @return array<string, int>
	 */
	private function empty_property_counts(): array {
		return array(
			'published' => 0,
			'pending'   => 0,
			'draft'     => 0,
			'rejected'  => 0,
			'total'     => 0,
		);
	}

	/**
	 * @return array<string, int>
	 */
	private function empty_inquiry_counts(): array {
		return array(
			'unread'   => 0,
			'open'     => 0,
			'replied'  => 0,
			'archived' => 0,
			'total'    => 0,
		);
	}

	/**
	 * @param int[] $agent_ids Agent IDs.
	 * @return array<int, array<string, int>>
	 */
	private function batch_property_counts( array $agent_ids ): array {
		global $wpdb;

		$out = array();
		foreach ( $agent_ids as $id ) {
			$out[ $id ] = $this->empty_property_counts();
		}

		if ( empty( $agent_ids ) ) {
			return $out;
		}

		$likes = array();
		$args  = array( AgentConstants::PROPERTY_POST_TYPE, AgentConstants::META_PROPERTY_AGENTS );
		foreach ( $agent_ids as $aid ) {
			$likes[] = 'pm.meta_value LIKE %s';
			$args[]  = '%i:' . $aid . ';%';
			$likes[] = 'pm.meta_value LIKE %s';
			$args[]  = '%[' . $aid . ']%';
			$likes[] = 'pm.meta_value LIKE %s';
			$args[]  = '%[' . $aid . ',%';
			$likes[] = 'pm.meta_value LIKE %s';
			$args[]  = '%,' . $aid . ']%';
			$likes[] = 'pm.meta_value LIKE %s';
			$args[]  = '%,' . $aid . ',%';
		}

		$sql = "SELECT p.ID, p.post_status, pm.meta_value
			FROM {$wpdb->posts} p
			INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID
			WHERE p.post_type = %s
			AND p.post_status IN ('publish','pending','draft','private')
			AND pm.meta_key = %s
			AND (" . implode( ' OR ', $likes ) . ')';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $args ), ARRAY_A );
		if ( ! is_array( $rows ) ) {
			return $out;
		}

		$listing_status_key = PropertyFormMapper::META_LISTING_STATUS;
		$post_ids           = array();
		foreach ( $rows as $row ) {
			$post_ids[] = absint( $row['ID'] ?? 0 );
		}
		$post_ids = array_filter( array_unique( $post_ids ) );
		$rejected_map = array();
		if ( ! empty( $post_ids ) ) {
			update_meta_cache( 'post', $post_ids );
			foreach ( $post_ids as $pid ) {
				$label = strtolower( (string) get_post_meta( $pid, $listing_status_key, true ) );
				if ( false !== strpos( $label, 'reject' ) ) {
					$rejected_map[ $pid ] = true;
				}
			}
		}

		foreach ( $rows as $row ) {
			$pid    = absint( $row['ID'] ?? 0 );
			$status = (string) ( $row['post_status'] ?? '' );
			$meta   = (string) ( $row['meta_value'] ?? '' );
			$matched = $this->agents_in_meta( $meta, $agent_ids );
			foreach ( $matched as $aid ) {
				if ( ! isset( $out[ $aid ] ) ) {
					continue;
				}
				++$out[ $aid ]['total'];
				if ( ! empty( $rejected_map[ $pid ] ) ) {
					++$out[ $aid ]['rejected'];
				} elseif ( 'publish' === $status ) {
					++$out[ $aid ]['published'];
				} elseif ( 'pending' === $status ) {
					++$out[ $aid ]['pending'];
				} else {
					++$out[ $aid ]['draft'];
				}
			}
		}

		return $out;
	}

	/**
	 * @param string $meta      Serialized/JSON agents meta.
	 * @param int[]  $agent_ids Candidate IDs.
	 * @return int[]
	 */
	private function agents_in_meta( string $meta, array $agent_ids ): array {
		$found = array();
		foreach ( $agent_ids as $aid ) {
			$aid = (int) $aid;
			if ( false !== strpos( $meta, 'i:' . $aid . ';' )
				|| false !== strpos( $meta, '[' . $aid . ']' )
				|| false !== strpos( $meta, '[' . $aid . ',' )
				|| false !== strpos( $meta, ',' . $aid . ']' )
				|| false !== strpos( $meta, ',' . $aid . ',' ) ) {
				$found[] = $aid;
			}
		}
		return $found;
	}

	/**
	 * @param int[] $agent_ids Agent IDs.
	 * @return array<int, array<string, int>>
	 */
	private function batch_inquiry_counts( array $agent_ids ): array {
		$out = array();
		foreach ( $agent_ids as $id ) {
			$out[ $id ] = $this->empty_inquiry_counts();
		}

		if ( empty( $agent_ids ) || ! InquirySchema::table_exists() ) {
			return $out;
		}

		global $wpdb;
		$table        = InquirySchema::table_name();
		$placeholders = implode( ',', array_fill( 0, count( $agent_ids ), '%d' ) );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT agent_id, status, COUNT(*) AS cnt
			FROM `{$table}`
			WHERE agent_id IN ({$placeholders})
			GROUP BY agent_id, status",
				...$agent_ids
			),
			ARRAY_A
		);
		// phpcs:enable
		if ( ! is_array( $rows ) ) {
			return $out;
		}

		foreach ( $rows as $row ) {
			$aid = absint( $row['agent_id'] ?? 0 );
			$st  = (string) ( $row['status'] ?? '' );
			$cnt = absint( $row['cnt'] ?? 0 );
			if ( ! isset( $out[ $aid ] ) ) {
				continue;
			}
			$out[ $aid ]['total'] += $cnt;
			if ( ContactAgentConstants::STATUS_NEW === $st ) {
				$out[ $aid ]['unread'] += $cnt;
				$out[ $aid ]['open']   += $cnt;
			} elseif ( ContactAgentConstants::STATUS_READ === $st ) {
				$out[ $aid ]['open'] += $cnt;
			} elseif ( ContactAgentConstants::STATUS_REPLIED === $st ) {
				$out[ $aid ]['replied'] += $cnt;
			} elseif ( ContactAgentConstants::STATUS_ARCHIVED === $st ) {
				$out[ $aid ]['archived'] += $cnt;
			}
		}

		return $out;
	}

	/**
	 * Lightweight health flags (not a full scanner run).
	 *
	 * @param int  $agent_id Agent ID.
	 * @param int  $user_id  Linked user ID.
	 * @param bool $user_ok  User exists.
	 * @return array{flags:string[],label:string,url:string}
	 */
	private function detect_health_flags( int $agent_id, int $user_id, bool $user_ok ): array {
		$flags = array();
		if ( $user_id <= 0 ) {
			$flags[] = 'no_user';
		} elseif ( ! $user_ok ) {
			$flags[] = 'broken_link';
			$flags[] = 'missing_user';
		}

		$email = (string) get_post_meta( $agent_id, AgentConstants::META_EMAIL, true );
		if ( is_email( $email ) ) {
			// Duplicate public email among agents — cheap same-request check only when primed.
			static $email_index = null;
			if ( null === $email_index ) {
				$email_index = array();
			}
			$key = strtolower( $email );
			if ( isset( $email_index[ $key ] ) && (int) $email_index[ $key ] !== $agent_id ) {
				$flags[] = 'duplicate_email';
			} else {
				$email_index[ $key ] = $agent_id;
			}
		}

		$comp = $this->completeness->percent( $agent_id );
		if ( $comp < 70 ) {
			$flags[] = 'profile_incomplete';
		}

		$labels = array(
			'broken_link'        => __( 'Broken Link', 'havenlytics' ),
			'missing_user'       => __( 'Missing User', 'havenlytics' ),
			'no_user'            => __( 'No User', 'havenlytics' ),
			'duplicate_email'    => __( 'Duplicate Email', 'havenlytics' ),
			'profile_incomplete' => __( 'Incomplete', 'havenlytics' ),
		);
		$parts = array();
		foreach ( $flags as $f ) {
			if ( isset( $labels[ $f ] ) ) {
				$parts[] = $labels[ $f ];
			}
		}

		return array(
			'flags' => $flags,
			'label' => implode( ', ', $parts ),
			'url'   => admin_url( 'tools.php?page=' . AgentIdentityHealthAdminPage::PAGE_SLUG ),
		);
	}

	/**
	 * @param int $agent_id Agent ID.
	 * @return string
	 */
	private function agency_names( int $agent_id ): string {
		$terms = get_the_terms( $agent_id, AgentConstants::TAXONOMY_AGENCY );
		if ( ! is_array( $terms ) || empty( $terms ) ) {
			return '';
		}
		return implode( ', ', wp_list_pluck( $terms, 'name' ) );
	}
}
