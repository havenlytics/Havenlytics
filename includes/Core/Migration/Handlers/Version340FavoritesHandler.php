<?php
/**
 * Version 3.4.0 — Favorites (saved properties) table.
 *
 * @package HvnlyNab\Core\Migration\Handlers
 * @since   3.4.0
 */

namespace HvnlyNab\Core\Migration\Handlers;

use HvnlyNab\Core\Migration\Interfaces\MigrationInterface;
use HvnlyNab\Core\Migration\Traits\MigrationTrait;
use HvnlyNab\Favorites\FavoritesSchema;

defined( 'ABSPATH' ) || exit;

/**
 * Creates `hvnly_favorites`.
 *
 * Purely additive — no existing data is read, moved or rewritten, so an
 * upgrade from 3.3.x cannot lose anything and a rollback simply leaves an
 * unused table behind.
 *
 * @since 3.4.0
 */
class Version340FavoritesHandler implements MigrationInterface {

	use MigrationTrait;

	/**
	 * {@inheritdoc}
	 */
	public function get_version(): string {
		return '3.4.0-favorites';
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_description(): string {
		return 'Creates saved properties table (hvnly_favorites)';
	}

	/**
	 * {@inheritdoc}
	 */
	public function is_needed(): bool {
		return ! FavoritesSchema::table_exists();
	}

	/**
	 * {@inheritdoc}
	 */
	public function up(): bool {
		$this->log( 'Creating favorites table', 'info', '3.4.0-favorites' );

		$created = FavoritesSchema::create_table();
		if ( ! $created ) {
			$this->log( 'Failed to create favorites table', 'error', '3.4.0-favorites' );
			return false;
		}

		$this->log( 'Favorites table ready', 'info', '3.4.0-favorites' );

		return true;
	}

	/**
	 * {@inheritdoc}
	 */
	public function down(): bool {
		// Favorites are user data. Never dropped by a rollback — removal is
		// the uninstaller's job, behind an explicit opt-in.
		return true;
	}
}
