<?php
/**
 * Version 3.3.0 taxonomy request workflow tables.
 *
 * @package HvnlyNab\Core\Migration\Handlers
 * @since   3.3.0
 */

namespace HvnlyNab\Core\Migration\Handlers;

use HvnlyNab\Core\Migration\Interfaces\MigrationInterface;
use HvnlyNab\Core\Migration\Traits\MigrationTrait;
use HvnlyNab\Workspace\TaxonomyRequests\TaxonomyRequestSchema;

defined( 'ABSPATH' ) || exit;

final class Version330TaxonomyRequestsHandler implements MigrationInterface {

	use MigrationTrait;

	public function get_version(): string {
		return '3.3.0-taxonomy-requests';
	}

	public function get_description(): string {
		return 'Creates taxonomy request and append-only audit log tables';
	}

	public function is_needed(): bool {
		return ! TaxonomyRequestSchema::tables_exist();
	}

	public function up(): bool {
		return TaxonomyRequestSchema::create_tables();
	}

	public function down(): bool {
		return true;
	}
}
