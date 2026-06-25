<?php
/**
 * Version 3.0.9 — Correct Status search filter taxonomy mapping.
 *
 * The Status sidebar field was incorrectly mapped to hvnly_prop_depts (Departments).
 * Property Status uses hvnly_prop_status.
 *
 * @package     Havenlytics
 * @subpackage  Core\Migration\Handlers
 * @since       3.0.9
 */

namespace HvnlyNab\Core\Migration\Handlers;

use HvnlyNab\Core\Migration\Interfaces\MigrationInterface;
use HvnlyNab\Core\Migration\Traits\MigrationTrait;

defined( 'ABSPATH' ) || exit;

class Version309Handler implements MigrationInterface {

    use MigrationTrait;

    const SETTINGS_KEY = 'hvnly_plugin_settings';

    public function get_version(): string {
        return '3.0.9';
    }

    public function get_description(): string {
        return 'Maps the Status search filter field to hvnly_prop_status instead of hvnly_prop_depts';
    }

    public function is_needed(): bool {
        $settings = get_option( self::SETTINGS_KEY, array() );
        if ( empty( $settings['search']['hvnly_search_fields'] ) || ! is_array( $settings['search']['hvnly_search_fields'] ) ) {
            return false;
        }

        return $this->fields_need_status_taxonomy_fix( $settings['search']['hvnly_search_fields'] );
    }

    public function up(): bool {
        $this->log( 'Starting search Status taxonomy migration 3.0.9', 'info', '3.0.9' );

        $settings = get_option( self::SETTINGS_KEY, array() );
        if ( empty( $settings['search']['hvnly_search_fields'] ) || ! is_array( $settings['search']['hvnly_search_fields'] ) ) {
            $this->log( 'No search fields to update', 'info', '3.0.9' );
            return true;
        }

        if ( ! $this->fields_need_status_taxonomy_fix( $settings['search']['hvnly_search_fields'] ) ) {
            $this->log( 'Status field taxonomy already correct', 'info', '3.0.9' );
            return true;
        }

        $this->backup_option( self::SETTINGS_KEY, '3.0.9' );

        $settings['search']['hvnly_search_fields'] = $this->fix_status_field_taxonomy(
            $settings['search']['hvnly_search_fields']
        );

        $this->safe_update_option( self::SETTINGS_KEY, $settings );
        wp_cache_delete( self::SETTINGS_KEY, 'options' );

        $this->log( 'Updated Status search field taxonomy to hvnly_prop_status', 'info', '3.0.9' );
        return true;
    }

    /** {@inheritdoc} */
    public function down(): bool {
        return true;
    }

    /**
     * @param array<int, array<string, mixed>> $fields Search filter fields.
     * @return bool
     */
    private function fields_need_status_taxonomy_fix( array $fields ): bool {
        foreach ( $fields as $field ) {
            if ( ! is_array( $field ) || ( $field['id'] ?? '' ) !== 'status' ) {
                continue;
            }

            $config   = isset( $field['config'] ) && is_array( $field['config'] ) ? $field['config'] : array();
            $taxonomy = isset( $config['taxonomy'] ) ? (string) $config['taxonomy'] : '';

            return 'hvnly_prop_depts' === $taxonomy;
        }

        return false;
    }

    /**
     * @param array<int, array<string, mixed>> $fields Search filter fields.
     * @return array<int, array<string, mixed>>
     */
    private function fix_status_field_taxonomy( array $fields ): array {
        foreach ( $fields as $index => $field ) {
            if ( ! is_array( $field ) || ( $field['id'] ?? '' ) !== 'status' ) {
                continue;
            }

            if ( ! isset( $field['config'] ) || ! is_array( $field['config'] ) ) {
                $field['config'] = array();
            }

            $field['config']['useTaxonomy'] = true;
            $field['config']['taxonomy']    = 'hvnly_prop_status';

            $fields[ $index ] = $field;
            break;
        }

        return $fields;
    }
}
