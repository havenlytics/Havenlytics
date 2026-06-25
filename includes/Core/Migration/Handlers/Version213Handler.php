<?php
/**
 * Version 2.1.3 Migration Handler
 *
 * Converts Property Price field from 'text' to 'price_label' type.
 *
 * @package     Havenlytics
 * @subpackage  Core\Migration\Handlers
 * @since       2.1.3
 */

namespace HvnlyNab\Core\Migration\Handlers;

use HvnlyNab\Core\Migration\Interfaces\MigrationInterface;
use HvnlyNab\Core\Migration\Traits\MigrationTrait;

// Prevent direct access.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Version213Handler
 *
 * Handles the migration to version 2.1.3.
 * Enables price label toggle functionality.
 *
 * @since 2.1.3
 */
class Version213Handler implements MigrationInterface {

    use MigrationTrait;

    /**
     * Property builder storage key.
     *
     * @var string
     */
    const PROPERTY_BUILDER_KEY = 'hvnly_property_builder.sections';

    /**
     * Get the target version.
     *
     * @since 2.1.3
     * @return string
     */
    public function get_version(): string {
        return '2.1.3';
    }

    /**
     * Get migration description.
     *
     * @since 2.1.3
     * @return string
     */
    public function get_description(): string {
        return 'Converts Property Price field from text to price_label type for enhanced pricing options';
    }

    /**
     * Check if migration is needed.
     *
     * @since 2.1.3
     * @return bool
     */
    public function is_needed(): bool {
        $sections = get_option(self::PROPERTY_BUILDER_KEY, array());

        if (empty($sections) || !is_array($sections)) {
            return false;
        }

        foreach ($sections as $section) {
            if (!isset($section['fields']) || !is_array($section['fields'])) {
                continue;
            }

            foreach ($section['fields'] as $field) {
                if ($this->is_price_field($field) && $this->is_text_type($field)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Execute the migration.
     *
     * @since 2.1.3
     * @return bool
     */
    public function up(): bool {
        $this->log('Starting migration to version ' . $this->get_version());

        // Create backup before making changes.
        $backup_id = $this->backup_option(self::PROPERTY_BUILDER_KEY, '2.1.3');

        if ($backup_id) {
            $this->log('Backup created: ' . $backup_id);
        }

        $sections = get_option(self::PROPERTY_BUILDER_KEY, array());

        if (empty($sections) || !is_array($sections)) {
            $this->log('No sections found to migrate');
            return true;
        }

        $updated = false;
        $conversion_count = 0;

        foreach ($sections as &$section) {
            if (!isset($section['fields']) || !is_array($section['fields'])) {
                continue;
            }

            foreach ($section['fields'] as &$field) {
                if ($this->is_price_field($field) && $this->is_text_type($field)) {
                    $field['type'] = 'price_label';
                    $updated = true;
                    $conversion_count++;
                    $this->log(sprintf('Converted price field in section: %s', $section['id'] ?? 'unknown'));
                }
            }
        }

        if ($updated) {
            $result = update_option(self::PROPERTY_BUILDER_KEY, $sections);

            if ($result) {
                // Clear cache.
                wp_cache_delete(self::PROPERTY_BUILDER_KEY, 'options');

                $this->log(sprintf('Successfully migrated %d price field(s)', $conversion_count));
            } else {
                $this->log('Failed to save updated configuration', 'error');
                return false;
            }
        } else {
            $this->log('No price fields needed conversion');
        }

        // Clean up old backups.
        $this->cleanup_backups(5);

        $this->log('Migration completed successfully');
        return true;
    }

    /**
     * Rollback the migration.
     *
     * @since 2.1.3
     * @return bool
     */
    public function down(): bool {
        $this->log('Rolling back migration to version ' . $this->get_version());

        // Find most recent backup for this option using WordPress options API with caching.
        $backup_id = $this->find_latest_backup();

        if ($backup_id) {
            $restored = $this->restore_from_backup($backup_id);

            if ($restored) {
                $this->log('Successfully rolled back from backup: ' . $backup_id);
                return true;
            }
        }

        // If no backup, try to convert back manually.
        $sections = get_option(self::PROPERTY_BUILDER_KEY, array());

        if (!empty($sections) && is_array($sections)) {
            $updated = false;

            foreach ($sections as &$section) {
                if (!isset($section['fields']) || !is_array($section['fields'])) {
                    continue;
                }

                foreach ($section['fields'] as &$field) {
                    if ($this->is_price_field($field) && isset($field['type']) && $field['type'] === 'price_label') {
                        $field['type'] = 'text';
                        $updated = true;
                    }
                }
            }

            if ($updated) {
                update_option(self::PROPERTY_BUILDER_KEY, $sections);
                $this->log('Manually converted price fields back to text type');
            }
        }

        return true;
    }

    /**
     * Find the latest backup for this migration version.
     *
     * Uses WordPress options API with caching instead of direct database queries.
     *
     * @since 2.1.3
     * @return string|false Backup option name or false if not found.
     */
    private function find_latest_backup() {
        // Try to get cached backup list first.
        $cache_key = 'hvnly_backup_list_2.1.3';
        $backup_list = wp_cache_get($cache_key);

        if (false === $backup_list) {
            // Cache miss - load all options and filter for backups.
            $all_options = wp_load_alloptions();
            $backup_list = array();

            if (is_array($all_options)) {
                foreach ($all_options as $option_name => $option_value) {
                    if (strpos($option_name, '_hvnly_backup_2.1.3_hvnly_property_builder.sections_') === 0) {
                        $backup_data = maybe_unserialize($option_value);
                        if (is_array($backup_data) && isset($backup_data['timestamp'])) {
                            $backup_list[$option_name] = strtotime($backup_data['timestamp']);
                        } else {
                            $backup_list[$option_name] = 0;
                        }
                    }
                }
            }

            // Cache the backup list for 1 hour.
            wp_cache_set($cache_key, $backup_list, '', HOUR_IN_SECONDS);
        }

        if (empty($backup_list)) {
            return false;
        }

        // Sort by timestamp descending and return the most recent.
        arsort($backup_list);
        $latest_backups = array_keys($backup_list);

        return reset($latest_backups);
    }

    /**
     * Check if a field is the price field.
     *
     * @since 2.1.3
     * @param array $field Field configuration.
     * @return bool
     */
    private function is_price_field(array $field): bool {
        // Check by standard ID.
        if (isset($field['id']) && in_array($field['id'], array('_hvnly_property_price', 'property_price'), true)) {
            return true;
        }

        // Check by name (legacy).
        if (isset($field['name']) && $field['name'] === '_hvnly_property_price') {
            return true;
        }

        return false;
    }

    /**
     * Check if field type is 'text'.
     *
     * @since 2.1.3
     * @param array $field Field configuration.
     * @return bool
     */
    private function is_text_type(array $field): bool {
        return isset($field['type']) && $field['type'] === 'text';
    }
}