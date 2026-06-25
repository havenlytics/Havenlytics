<?php
/**
 * Migration Interface
 *
 * Defines the contract for all version migrations.
 *
 * @package     Havenlytics
 * @subpackage  Core\Migration\Interfaces
 * @since       2.1.3
 */

namespace HvnlyNab\Core\Migration\Interfaces;

// Prevent direct access.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Interface MigrationInterface
 *
 * All migration handlers must implement this interface.
 *
 * @since 2.1.3
 */
interface MigrationInterface {

    /**
     * Get the target version for this migration.
     *
     * @since 2.1.3
     * @return string Version number (e.g., '2.1.3').
     */
    public function get_version(): string;

    /**
     * Get migration description for debugging.
     *
     * @since 2.1.3
     * @return string Human-readable description.
     */
    public function get_description(): string;

    /**
     * Check if this migration is needed.
     *
     * @since 2.1.3
     * @return bool True if migration should run.
     */
    public function is_needed(): bool;

    /**
     * Execute the migration.
     *
     * @since 2.1.3
     * @return bool True on success, false on failure.
     */
    public function up(): bool;

    /**
     * Rollback the migration (if needed).
     *
     * @since 2.1.3
     * @return bool True on success, false on failure.
     */
    public function down(): bool;
}
