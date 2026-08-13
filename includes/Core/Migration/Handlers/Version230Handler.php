<?php
/**
 * Version 2.3.0 Migration Handler
 *
 * @package     Havenlytics
 * @subpackage  Core\Migration\Handlers
 * @since       2.3.0
 */

namespace HvnlyNab\Core\Migration\Handlers;

use HvnlyNab\Core\Migration\Interfaces\MigrationInterface;
use HvnlyNab\Core\Migration\Traits\MigrationTrait;

// Prevent direct access
if ( ! defined('ABSPATH')) {
    exit;
}

class Version230Handler implements MigrationInterface {

    use MigrationTrait;

    const PROPERTY_BUILDER_KEY = 'hvnly_property_builder.sections';

    public function get_version(): string {
        return '2.3.0';
    }

    public function get_description(): string {
        return 'Adds enterprise database tables and ensures complete property builder configuration';
    }

    public function is_needed(): bool {
        global $wpdb;

        $table = $wpdb->prefix . 'hvnly_section_cache';
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Migration table existence check.
        $exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table;

        // If table doesn't exist, migration is needed
        return ! $exists;
    }

    public function up(): bool {
        global $wpdb;

        $this->log('Starting 2.3.0 migration');

        // Create backup
        $this->backup_option(self::PROPERTY_BUILDER_KEY, '2.3.0');

        // ============================================
        // STEP 1: ENSURE COMPLETE BUILDER CONFIGURATION
        // ============================================
        $this->ensure_complete_builder_configuration();

        // ============================================
        // STEP 2: CREATE ENTERPRISE DATABASE TABLES
        // ============================================
        $charset_collate = $wpdb->get_charset_collate();

        // Create tables one by one with error checking
        $tables_created = 0;

        // 1. Section Cache Table
        $sql1 = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}hvnly_section_cache (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            section_key varchar(100) NOT NULL,
            cache_data longtext NOT NULL,
            cache_hash varchar(64) NOT NULL,
            expires_at datetime DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY idx_section_key (section_key),
            KEY idx_expires (expires_at)
        ) $charset_collate;";

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Static CREATE TABLE schema for plugin migration.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Plugin migration creates custom tables.
        $result1 = $wpdb->query($sql1);
        if ($result1 !== false) {
			$tables_created++;
        }

        // 2. Field Index Table
        $sql2 = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}hvnly_field_index (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            property_id bigint(20) NOT NULL,
            field_key varchar(100) NOT NULL,
            field_value_index varchar(255) DEFAULT NULL,
            numeric_value decimal(20,6) DEFAULT NULL,
            date_value datetime DEFAULT NULL,
            PRIMARY KEY (id),
            KEY idx_property (property_id),
            KEY idx_field_key (field_key),
            KEY idx_numeric (numeric_value)
        ) $charset_collate;";

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Static CREATE TABLE schema for plugin migration.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Plugin migration creates custom tables.
        $result2 = $wpdb->query($sql2);
        if ($result2 !== false) {
			$tables_created++;
        }

        // 3. Audit Log Table
        $sql3 = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}hvnly_audit_log (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) DEFAULT NULL,
            action varchar(100) NOT NULL,
            entity_type varchar(50) NOT NULL,
            entity_id bigint(20) DEFAULT NULL,
            old_value longtext,
            new_value longtext,
            ip_address varchar(45) DEFAULT NULL,
            user_agent text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_action (action),
            KEY idx_entity (entity_type, entity_id),
            KEY idx_created (created_at)
        ) $charset_collate;";

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Static CREATE TABLE schema for plugin migration.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Plugin migration creates custom tables.
        $result3 = $wpdb->query($sql3);
        if ($result3 !== false) {
			$tables_created++;
        }

        // 4. Webhook Queue Table
        $sql4 = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}hvnly_webhook_queue (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            webhook_url varchar(500) NOT NULL,
            payload longtext NOT NULL,
            attempts int DEFAULT 0,
            max_attempts int DEFAULT 3,
            last_error text,
            status varchar(20) DEFAULT 'pending',
            scheduled_at datetime DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            processed_at datetime DEFAULT NULL,
            PRIMARY KEY (id),
            KEY idx_status (status),
            KEY idx_scheduled (scheduled_at)
        ) $charset_collate;";

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Static CREATE TABLE schema for plugin migration.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Plugin migration creates custom tables.
        $result4 = $wpdb->query($sql4);
        if ($result4 !== false) {
			$tables_created++;
        }

        $this->log("Created $tables_created out of 4 tables");

        // ============================================
        // STEP 3: SYNC EXISTING SECTIONS TO CACHE
        // ============================================
        $this->sync_existing_sections();

        // ============================================
        // STEP 4: CLEAR CACHE
        // ============================================
        wp_cache_delete(self::PROPERTY_BUILDER_KEY, 'options');

        $this->log('Migration 2.3.0 completed successfully');
        return true;
    }

    /**
     * Ensure property builder has complete configuration with ALL 7 sections.
     *
     * @since 2.3.0
     * @return void
     */
    private function ensure_complete_builder_configuration(): void {
        $sections = get_option(self::PROPERTY_BUILDER_KEY, array());

        // Required section titles
        $required_titles = array(
            'Basic Info' => true,
            'Additional Information' => true,
            'Address & Neighborhood' => true,
            'Property Video' => false,      // Optional - can be created dynamically
            'Property Gallery' => false,    // Optional - can be created dynamically
            'Property Location' => false,   // Optional - can be created dynamically
            'Property Documents' => false,  // Optional - can be created dynamically
        );

        $existing_titles  = array_column($sections, 'title');
        $missing_sections = array();

        foreach ($required_titles as $title => $is_required) {
            if ( ! in_array($title, $existing_titles)) {
                $missing_sections[] = $title;
            }
        }

        if ( ! empty($missing_sections)) {
            $this->log('Missing sections found: ' . implode(', ', $missing_sections));

            // Get complete configuration from DefaultTabSectionsData
            if (class_exists('\HvnlyNab\Api\Type\Builders\DefaultTabSectionsData')) {
                $complete_config = \HvnlyNab\Api\Type\Builders\DefaultTabSectionsData::get_default_sections_for_dnd();

                // Add missing sections
                $sections_updated = false;
                foreach ($complete_config as $key => $section) {
                    $section_title = $section['title'] ?? '';
                    if (in_array($section_title, $missing_sections) && ! isset($sections[ $key ])) {
                        $sections[ $key ] = $section;
                        $sections_updated = true;
                        $this->log('Added missing section: ' . $section_title);
                    }
                }

                if ($sections_updated) {
                    // Reorder sections by title
                    $sections = $this->reorder_sections_by_title($sections);
                    update_option(self::PROPERTY_BUILDER_KEY, $sections);
                    $this->log('Builder configuration updated with all sections');
                }
            }
        } else {
            $this->log('All required sections are present');
        }
    }

    /**
     * Reorder sections by title for consistent display.
     *
     * @since 2.3.0
     * @param array $sections Sections to reorder.
     * @return array Reordered sections.
     */
    private function reorder_sections_by_title( array $sections ): array {
        $title_order = array(
            'Basic Info' => 0,
            'Additional Information' => 1,
            'Address & Neighborhood' => 2,
            'Property Video' => 3,
            'Property Gallery' => 4,
            'Property Location' => 5,
            'Property Documents' => 6,
        );

        $dynamic_index = 7;

        foreach ($sections as $key => &$section) {
            $title = $section['title'] ?? '';
            if (isset($title_order[ $title ])) {
                $section['order'] = $title_order[ $title ];
            } else {
                $section['order'] = $dynamic_index++;
            }
        }

        // Sort by order
        uasort($sections, function ( $a, $b ) {
            return ( $a['order'] ?? 999 ) - ( $b['order'] ?? 999 );
        });

        return $sections;
    }

    private function sync_existing_sections(): void {
        global $wpdb;

        $sections = get_option(self::PROPERTY_BUILDER_KEY, array());

        if ( ! empty($sections)) {
            $cache_table = $wpdb->prefix . 'hvnly_section_cache';

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Migration sync to plugin cache table.
            $wpdb->replace($cache_table, array(
                'section_key' => 'all_sections',
                'cache_data' => json_encode($sections),
                'cache_hash' => md5(json_encode($sections)),
                'expires_at' => gmdate( 'Y-m-d H:i:s', time() + DAY_IN_SECONDS ),
            ));

            $this->log('Synced sections to cache');
        }
    }

    public function down(): bool {
        global $wpdb;

        $tables = array(
            $wpdb->prefix . 'hvnly_section_cache',
            $wpdb->prefix . 'hvnly_field_index',
            $wpdb->prefix . 'hvnly_audit_log',
            $wpdb->prefix . 'hvnly_webhook_queue',
        );

        foreach ($tables as $table) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Dropping plugin-owned tables during rollback.
            $wpdb->query( "DROP TABLE IF EXISTS `{$table}`" );
        }

        return true;
    }
}
