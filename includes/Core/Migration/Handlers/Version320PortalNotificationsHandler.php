<?php
/**
 * Version 3.2.0 — Portal notifications table.
 *
 * @package HvnlyNab\Core\Migration\Handlers
 * @since   3.2.0
 */

namespace HvnlyNab\Core\Migration\Handlers;

use HvnlyNab\Core\Migration\Interfaces\MigrationInterface;
use HvnlyNab\Core\Migration\Traits\MigrationTrait;
use HvnlyNab\Workspace\Notifications\NotificationSchema;

defined( 'ABSPATH' ) || exit;

/**
 * @since 3.2.0
 */
class Version320PortalNotificationsHandler implements MigrationInterface {

	use MigrationTrait;

	/**
	 * {@inheritdoc}
	 */
	public function get_version(): string {
		return '3.2.0-portal-notifications';
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_description(): string {
		return 'Creates Workspace portal notifications table (hvnly_portal_notifications)';
	}

	/**
	 * {@inheritdoc}
	 */
	public function is_needed(): bool {
		return ! NotificationSchema::table_exists();
	}

	/**
	 * {@inheritdoc}
	 */
	public function up(): bool {
		$this->log( 'Creating portal notifications table', 'info', '3.2.0-portal-notifications' );
		$created = NotificationSchema::create_table();
		if ( ! $created ) {
			$this->log( 'Failed to create portal notifications table', 'error', '3.2.0-portal-notifications' );
			return false;
		}
		$this->log( 'Portal notifications table ready', 'info', '3.2.0-portal-notifications' );
		return true;
	}

	/**
	 * {@inheritdoc}
	 */
	public function down(): bool {
		return true;
	}
}
