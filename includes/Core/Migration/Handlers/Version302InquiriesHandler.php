<?php
/**
 * Version 3.0.2 — Contact Agent inquiries table.
 *
 * @package     Havenlytics
 * @subpackage  Core\Migration\Handlers
 * @since       3.0.2
 */

namespace HvnlyNab\Core\Migration\Handlers;

use HvnlyNab\ContactAgent\Database\InquirySchema;
use HvnlyNab\Core\Migration\Interfaces\MigrationInterface;
use HvnlyNab\Core\Migration\Traits\MigrationTrait;

defined( 'ABSPATH' ) || exit;

/**
 * Creates the {@see InquirySchema} custom table for Contact Agent inquiries.
 *
 * @since 3.0.2
 */
class Version302InquiriesHandler implements MigrationInterface {

	use MigrationTrait;

	/**
	 * {@inheritdoc}
	 */
	public function get_version(): string {
		return '3.0.2';
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_description(): string {
		return 'Creates Contact Agent inquiries table (hvnly_inquiries)';
	}

	/**
	 * {@inheritdoc}
	 */
	public function is_needed(): bool {
		return ! InquirySchema::table_exists();
	}

	/**
	 * {@inheritdoc}
	 */
	public function up(): bool {
		$this->log( 'Creating Contact Agent inquiries table', 'info', '3.0.2' );

		$created = InquirySchema::create_table();

		if ( ! $created ) {
			$this->log( 'Failed to create Contact Agent inquiries table', 'error', '3.0.2' );

			return false;
		}

		$this->log( 'Contact Agent inquiries table ready', 'info', '3.0.2' );

		return true;
	}

	/**
	 * {@inheritdoc}
	 */
	public function down(): bool {
		return true;
	}
}
