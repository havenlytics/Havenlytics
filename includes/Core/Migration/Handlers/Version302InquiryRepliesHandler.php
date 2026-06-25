<?php
/**
 * Version 3.0.2 — Contact Agent inquiry replies table.
 *
 * @package     Havenlytics
 * @subpackage  Core\Migration\Handlers
 * @since       3.0.2
 */

namespace HvnlyNab\Core\Migration\Handlers;

use HvnlyNab\ContactAgent\Database\InquiryReplySchema;
use HvnlyNab\Core\Migration\Interfaces\MigrationInterface;
use HvnlyNab\Core\Migration\Traits\MigrationTrait;

defined( 'ABSPATH' ) || exit;

/**
 * Creates the inquiry replies table for admin conversation threading.
 *
 * @since 3.0.2
 */
class Version302InquiryRepliesHandler implements MigrationInterface {

	use MigrationTrait;

	/**
	 * {@inheritdoc}
	 */
	public function get_version(): string {
		return '3.0.2-inquiry-replies';
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_description(): string {
		return 'Creates Contact Agent inquiry replies table (hvnly_inquiry_replies)';
	}

	/**
	 * {@inheritdoc}
	 */
	public function is_needed(): bool {
		return ! InquiryReplySchema::table_exists();
	}

	/**
	 * {@inheritdoc}
	 */
	public function up(): bool {
		$this->log( 'Creating Contact Agent inquiry replies table', 'info', '3.0.2-inquiry-replies' );

		$created = InquiryReplySchema::create_table();

		if ( ! $created ) {
			$this->log( 'Failed to create inquiry replies table', 'error', '3.0.2-inquiry-replies' );

			return false;
		}

		$this->log( 'Inquiry replies table ready', 'info', '3.0.2-inquiry-replies' );

		return true;
	}

	/**
	 * {@inheritdoc}
	 */
	public function down(): bool {
		return true;
	}
}
