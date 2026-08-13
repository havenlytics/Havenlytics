<?php
/**
 * Migration Trait
 *
 * Provides common functionality for all migrations.
 *
 * @package     Havenlytics
 * @subpackage  Core\Migration\Traits
 * @since       2.1.3
 */

namespace HvnlyNab\Core\Migration\Traits;

// Prevent direct access.
if ( ! defined('ABSPATH')) {
    exit;
}

/**
 * Trait MigrationTrait
 *
 * Common methods used across all migration handlers.
 *
 * @since 2.1.3
 */
trait MigrationTrait {

    /**
     * Create a backup of an option before migration.
     *
     * @since 2.1.3
     * @param string $option_key Option key to backup.
     * @param string $version    Migration version.
     * @return string|false Backup ID or false on failure.
     */
    protected function backup_option( string $option_key, string $version ) {
        $data = get_option($option_key, array());

        if (empty($data)) {
            return false;
        }

        $backup_id = sprintf('_hvnly_backup_%s_%s_%s', $version, $option_key, current_time('timestamp'));

        $backup = array(
            'id'        => $backup_id,
            'version'   => $version,
            'option'    => $option_key,
            'timestamp' => current_time('mysql'),
            'data'      => $data,
        );

        if (update_option($backup_id, $backup, false)) {
            return $backup_id;
        }

        return false;
    }

    /**
     * Restore an option from backup.
     *
     * @since 2.1.3
     * @param string $backup_id Backup ID to restore.
     * @return bool True on success, false on failure.
     */
    protected function restore_from_backup( string $backup_id ): bool {
        $backup = get_option($backup_id, null);

        if ( ! $backup || ! is_array($backup) || ! isset($backup['option']) || ! isset($backup['data'])) {
            return false;
        }

        return update_option($backup['option'], $backup['data']);
    }

    /**
     * Clean up old backups.
     *
     * Uses WordPress options API with caching instead of direct database queries.
     *
     * @since 2.1.3
     * @param int $keep Number of backups to keep per option.
     * @return int Number of backups deleted.
     */
    protected function cleanup_backups( int $keep = 5 ): int {
        $deleted = 0;

        // Get all backup options using WordPress API with caching.
        $backups = $this->get_backup_options();

        if (empty($backups)) {
            return $deleted;
        }

        $backups_by_option = array();

        foreach ($backups as $backup_name) {
            // Remove the prefix to get the version and option key parts.
            $clean_name = str_replace('_hvnly_backup_', '', $backup_name);
            $parts      = explode('_', $clean_name);

            if (count($parts) >= 2) {
                // First part is version, rest is the option key.
                $version      = $parts[0];
                $option_parts = array_slice($parts, 1);
                $option_key   = implode('_', $option_parts);

                if ( ! isset($backups_by_option[ $option_key ])) {
                    $backups_by_option[ $option_key ] = array();
                }
                $backups_by_option[ $option_key ][] = $backup_name;
            }
        }

        foreach ($backups_by_option as $option_backups) {
            if (count($option_backups) > $keep) {
                // Sort by backup name (which contains timestamp) in descending order.
                rsort($option_backups);

                // Keep the newest backups, delete the rest.
                $to_delete = array_slice($option_backups, $keep);

                foreach ($to_delete as $backup_name) {
                    if (delete_option($backup_name)) {
                        $deleted++;
                    }
                }
            }
        }

        // Clear the backup cache after deletions.
        wp_cache_delete('hvnly_migration_backups', 'options');

        return $deleted;
    }

    /**
     * Get all backup option names using WordPress API with caching.
     *
     * @since 2.1.3
     * @return array Array of backup option names.
     */
    private function get_backup_options(): array {
        // Try to get cached backup list first.
        $cache_key   = 'hvnly_migration_backups';
        $backup_list = wp_cache_get($cache_key, 'options');

        if (false !== $backup_list) {
            return $backup_list;
        }

        // Cache miss - load all options and filter for backups.
        $all_options = wp_load_alloptions();
        $backup_list = array();

        if (is_array($all_options)) {
            foreach ($all_options as $option_name => $option_value) {
                if (strpos($option_name, '_hvnly_backup_') === 0) {
                    $backup_list[] = $option_name;
                }
            }
        }

        // Sort by option names in reverse order (newest backups first based on naming convention).
        rsort($backup_list);

        // Cache the backup list for 1 hour.
        wp_cache_set($cache_key, $backup_list, 'options', HOUR_IN_SECONDS);

        return $backup_list;
    }

    /**
     * Log migration message when WP_DEBUG_LOG is enabled.
     *
     * @since 2.1.3
     * @param string      $message Log message.
     * @param string      $type    Log type (info, error, warning).
     * @param string|null $version Optional migration version tag.
     * @return void
     */
    protected function log( string $message, string $type = 'info', ?string $version = null ): void {
        if ( function_exists( 'hvnly_debug_log' ) ) {
            $prefix = 'Migration';
            if ( $version ) {
                $prefix .= ' ' . $version;
            }
            hvnly_debug_log( sprintf( '[%s] %s', strtoupper( $type ), $message ), $prefix );
            return;
        }
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
            $prefix = '[Havenlytics Migration]';
            if ( $version ) {
                $prefix .= ' ' . $version;
            }
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            error_log( sprintf( '%s [%s] %s', $prefix, strtoupper( $type ), $message ) );
        }
    }

    /**
     * Add post meta only when the key does not already exist (never overwrite).
     *
     * @param int    $post_id  Post ID.
     * @param string $meta_key Meta key.
     * @param mixed  $value    Value to store.
     * @return bool True when meta was added or already present with same value.
     */
    protected function safe_add_post_meta( int $post_id, string $meta_key, $value ): bool {
        $existing = get_post_meta($post_id, $meta_key, true);
        if ($existing !== '' && $existing !== false && $existing !== null) {
            return true;
        }
        return (bool) add_post_meta($post_id, $meta_key, $value, true);
    }

    /**
     * Update an option only when serialized data actually differs.
     *
     * @param string $option_key Option key.
     * @param mixed  $value      New value.
     * @return bool True when the option was updated or unchanged.
     */
    protected function safe_update_option( string $option_key, $value ): bool {
        $current = get_option($option_key, null);
        if ($current === $value) {
            return true;
        }
        if (is_array($current) && is_array($value) && $current == $value) {
            return true;
        }
        return update_option($option_key, $value);
    }

    /**
     * Canonical property builder config from UnifiedFieldGenerator when available.
     *
     * @return array|null
     */
    protected function get_unified_builder_config(): ?array {
        if ( ! class_exists('\HvnlyNab\Core\UnifiedFieldGenerator')) {
            return null;
        }
        $unified = \HvnlyNab\Core\UnifiedFieldGenerator::get_instance();
        $config  = $unified->get_unified_configuration();
        return is_array($config) && ! empty($config) ? $config : null;
    }

    /**
     * Generate a unique group base ID via UnifiedFieldGenerator (fallback: local pattern).
     *
     * @param string $group_type Group type slug.
     * @return string
     */
    protected function generate_migration_base_id( string $group_type ): string {
        if (class_exists('\HvnlyNab\Core\UnifiedFieldGenerator')) {
            $unified = \HvnlyNab\Core\UnifiedFieldGenerator::get_instance();
            return $unified->generate_unique_base_id($group_type);
        }
        $microtime    = microtime(true);
        $timestamp    = (int) $microtime;
        $micro_suffix = substr(str_replace('.', '', (string) $microtime), -6);
        $random       = wp_rand(10000, 99999);
        $unique_id    = uniqid();
        return "{$group_type}_{$timestamp}_{$micro_suffix}_{$unique_id}_{$random}";
    }

    /**
     * Apply a group_base_id to a single field row (updates name/id/map sub-keys).
     *
     * @param array  $field    Field config (by reference).
     * @param string $base_id  New base ID.
     * @return void
     */
    protected function apply_group_base_id_to_field( array &$field, string $base_id ): void {
        $meta_key = $field['metaKey'] ?? '';
        if (empty($meta_key)) {
            $name_parts       = explode('_', $field['name'] ?? $field['id'] ?? '');
            $meta_key         = (string) end($name_parts);
            $field['metaKey'] = $meta_key;
        }
        $field['group_base_id'] = $base_id;
        $field['id']            = $base_id . '_' . $meta_key;
        $field['name']          = $base_id . '_' . $meta_key;
        $group_type             = $field['group_type'] ?? '';
        if ($group_type === 'map') {
            $field['address_field_name'] = $base_id . '_address';
            $field['lat_field_name']     = $base_id . '_latitude';
            $field['lng_field_name']     = $base_id . '_longitude';
            $field['map_field_name']     = $base_id . '_preview';
        }
    }

    /**
     * Fix duplicate group_base_id values in-place (first occurrence wins).
     *
     * @param array $config Builder sections option value.
     * @return array Fixed config.
     */
    protected function fix_duplicate_group_base_ids_in_config( array $config ): array {
        $seen_base_ids = array();

        foreach ($config as $section_key => &$section) {
            if (empty($section['fields']) || ! is_array($section['fields'])) {
                continue;
            }
            foreach ($section['fields'] as $field_index => &$field) {
                $base_id = $field['group_base_id'] ?? '';
                if ($base_id === '') {
                    continue;
                }
                if (isset($seen_base_ids[ $base_id ])) {
                    $group_type = $field['group_type'] ?? 'group';
                    $new_base   = $this->generate_migration_base_id($group_type);
                    $this->apply_group_base_id_to_field($field, $new_base);
                    $section['fields'][ $field_index ] = $field;
                    $this->log(
                        sprintf(
                            'Resolved duplicate group_base_id "%s" → "%s" in section "%s"',
                            $base_id,
                            $new_base,
                            $section['title'] ?? $section_key
                        ),
                        'warning'
                    );
                } else {
                    $seen_base_ids[ $base_id ] = true;
                }
            }
        }
        unset($section, $field);

        return $config;
    }

    /**
     * Merge missing standard sections from unified config without replacing existing ones.
     *
     * @param array $config Current builder config.
     * @return array
     */
    protected function merge_missing_standard_sections( array $config ): array {
        $unified = $this->get_unified_builder_config();
        if ($unified === null) {
            return $config;
        }

        $existing_titles = array();
        foreach ($config as $section) {
            if ( ! empty($section['title'])) {
                $existing_titles[ $section['title'] ] = true;
            }
        }

        foreach ($unified as $key => $section) {
            $title = $section['title'] ?? '';
            if ($title !== '' && ! isset($existing_titles[ $title ])) {
                $config[ $key ] = $section;
                $this->log('Merged missing standard section: ' . $title, 'info');
            }
        }

        uasort(
            $config,
            static function ( $a, $b ) {
                return ( $a['order'] ?? 999 ) - ( $b['order'] ?? 999 );
            }
        );

        return $config;
    }

    /**
     * Whether the builder config contains duplicate group_base_id values.
     *
     * @param array $config Builder sections option value.
     * @return bool
     */
    protected function has_duplicate_group_base_ids( array $config ): bool {
        $seen = array();

        foreach ($config as $section) {
            foreach ($section['fields'] ?? array() as $field) {
                $base_id = $field['group_base_id'] ?? '';
                if ($base_id === '') {
                    continue;
                }
                if (isset($seen[ $base_id ])) {
                    return true;
                }
                $seen[ $base_id ] = true;
            }
        }

        return false;
    }

    /**
     * Whether the site has published property listings.
     *
     * Used to block destructive builder migrations on existing sites.
     *
     * @since 3.0.2
     * @return bool
     */
    protected function site_has_published_properties(): bool {
        $counts = wp_count_posts( 'hvnly_property' );

        return isset( $counts->publish ) && (int) $counts->publish > 0;
    }

    /**
     * Whether the property builder option is empty or missing field data.
     *
     * @since 3.0.2
     * @param mixed $sections Builder sections option value.
     * @return bool
     */
    protected function builder_config_is_empty( $sections = null ): bool {
        if ( null === $sections ) {
            $sections = get_option( 'hvnly_property_builder.sections', array() );
        }

        if ( ! is_array( $sections ) || empty( $sections ) ) {
            return true;
        }

        foreach ( $sections as $section ) {
            if ( ! is_array( $section ) ) {
                continue;
            }
            if ( ! empty( $section['fields'] ) && is_array( $section['fields'] ) ) {
                return false;
            }
        }

        return true;
    }

    /**
     * Attempt to restore builder config from snapshots or legacy migration backups.
     *
     * @since 3.0.2
     * @return bool True when builder config was restored.
     */
    protected function attempt_builder_config_recovery(): bool {
        if ( class_exists( '\HvnlyNab\Core\DataPreservation\BackupManager' ) ) {
            if ( \HvnlyNab\Core\DataPreservation\BackupManager::restore_latest() ) {
                if ( ! $this->builder_config_is_empty() ) {
                    $this->log( 'Restored property builder from latest pre-migration snapshot', 'info' );
                    return true;
                }
            }
        }

        $backups = $this->get_backup_options();
        foreach ( $backups as $backup_name ) {
            if ( strpos( $backup_name, 'hvnly_property_builder.sections' ) === false ) {
                continue;
            }
            if ( $this->restore_from_backup( $backup_name ) && ! $this->builder_config_is_empty() ) {
                $this->log( 'Restored property builder from legacy migration backup: ' . $backup_name, 'info' );
                return true;
            }
        }

        return false;
    }
}
