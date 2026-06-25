<?php
/**
 * Inquiry reply repository — custom table CRUD.
 *
 * @package HvnlyNab\ContactAgent
 * @since   3.0.2
 */

namespace HvnlyNab\ContactAgent;

use HvnlyNab\ContactAgent\Database\InquiryReplySchema;

defined( 'ABSPATH' ) || exit;

/**
 * @since 3.0.2
 */
class InquiryReplyRepository {

	/**
	 * Store a reply row.
	 *
	 * @param array<string, mixed> $data Reply data.
	 * @return int|\WP_Error Reply ID or error.
	 */
	public function create( array $data ) {
		if ( ! InquiryReplySchema::table_exists() ) {
			InquiryReplySchema::create_table();
		}

		if ( ! InquiryReplySchema::table_exists() ) {
			return new \WP_Error(
				'hvnly_inquiry_reply_table_missing',
				__( 'Reply storage is not available yet. Please contact the site administrator.', 'havenlytics' ),
				array( 'status' => 503 )
			);
		}

		$inquiry_id = isset( $data['inquiry_id'] ) ? absint( $data['inquiry_id'] ) : 0;
		if ( $inquiry_id <= 0 ) {
			return new \WP_Error(
				'hvnly_inquiry_reply_invalid_inquiry',
				__( 'Invalid inquiry ID.', 'havenlytics' ),
				array( 'status' => 400 )
			);
		}

		$direction = isset( $data['direction'] ) ? sanitize_key( (string) $data['direction'] ) : ContactAgentConstants::REPLY_DIRECTION_OUTBOUND;
		if ( ! in_array( $direction, ContactAgentConstants::REPLY_DIRECTIONS, true ) ) {
			$direction = ContactAgentConstants::REPLY_DIRECTION_OUTBOUND;
		}

		$row = array(
			'inquiry_id'      => $inquiry_id,
			'author_user_id'  => isset( $data['author_user_id'] ) ? absint( $data['author_user_id'] ) : 0,
			'direction'       => $direction,
			'message'         => isset( $data['message'] ) ? (string) $data['message'] : '',
			'email_sent'      => ! empty( $data['email_sent'] ) ? 1 : 0,
			'created_at'      => current_time( 'mysql', true ),
		);

		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$inserted = $wpdb->insert(
			InquiryReplySchema::table_name(),
			$row,
			array( '%d', '%d', '%s', '%s', '%d', '%s' )
		);

		if ( false === $inserted ) {
			return new \WP_Error(
				'hvnly_inquiry_reply_insert_failed',
				__( 'Unable to save your reply. Please try again.', 'havenlytics' ),
				array( 'status' => 500 )
			);
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * List replies for an inquiry ordered oldest first.
	 *
	 * @param int $inquiry_id Inquiry ID.
	 * @return array<int, array<string, mixed>>
	 */
	public function list_for_inquiry( int $inquiry_id ): array {
		if ( $inquiry_id <= 0 || ! InquiryReplySchema::table_exists() ) {
			return array();
		}

		global $wpdb;

		$table = function_exists( 'hvnly_get_validated_custom_table' )
			? hvnly_get_validated_custom_table( InquiryReplySchema::table_name() )
			: InquiryReplySchema::table_name();

		if ( '' === $table ) {
			return array();
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Validated hvnly_ custom table.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM `{$table}` WHERE inquiry_id = %d ORDER BY created_at ASC, id ASC",
				$inquiry_id
			),
			ARRAY_A
		);

		if ( ! is_array( $rows ) ) {
			return array();
		}

		return array_map( array( $this, 'normalize_row' ), $rows );
	}

	/**
	 * Delete all replies for an inquiry.
	 *
	 * @param int $inquiry_id Inquiry ID.
	 * @return bool
	 */
	public function delete_by_inquiry( int $inquiry_id ): bool {
		if ( $inquiry_id <= 0 || ! InquiryReplySchema::table_exists() ) {
			return false;
		}

		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$deleted = $wpdb->delete(
			InquiryReplySchema::table_name(),
			array( 'inquiry_id' => $inquiry_id ),
			array( '%d' )
		);

		return false !== $deleted;
	}

	/**
	 * @param array<string, mixed> $row Raw DB row.
	 * @return array<string, mixed>
	 */
	private function normalize_row( array $row ): array {
		$author_id = isset( $row['author_user_id'] ) ? (int) $row['author_user_id'] : 0;
		$author    = $author_id > 0 ? get_userdata( $author_id ) : false;

		return array(
			'id'             => isset( $row['id'] ) ? (int) $row['id'] : 0,
			'inquiry_id'     => isset( $row['inquiry_id'] ) ? (int) $row['inquiry_id'] : 0,
			'author_user_id' => $author_id,
			'author_name'    => $author instanceof \WP_User ? $author->display_name : '',
			'author_email'   => $author instanceof \WP_User ? $author->user_email : '',
			'direction'      => isset( $row['direction'] ) ? (string) $row['direction'] : '',
			'message'        => isset( $row['message'] ) ? (string) $row['message'] : '',
			'email_sent'     => ! empty( $row['email_sent'] ),
			'created_at'     => isset( $row['created_at'] ) ? (string) $row['created_at'] : '',
		);
	}
}
