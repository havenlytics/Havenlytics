<?php
/**
 * Version 2.3.3 — Map group field-name isolation
 *
 * Ensures every map field in the property builder uses address/lat/lng/preview
 * keys derived from its own group_base_id (prevents custom sections from pointing
 * at another tab's map meta keys).
 *
 * @package     Havenlytics
 * @subpackage  Core\Migration\Handlers
 * @since       2.3.3
 */

namespace HvnlyNab\Core\Migration\Handlers;

use HvnlyNab\Core\Migration\Interfaces\MigrationInterface;
use HvnlyNab\Core\Migration\Traits\MigrationTrait;

defined( 'ABSPATH' ) || exit;

class Version233Handler implements MigrationInterface {

    use MigrationTrait;

    const PROPERTY_BUILDER_KEY = 'hvnly_property_builder.sections';

    public function get_version(): string {
        return '2.3.3';
    }

    public function get_description(): string {
        return 'Align map sub-field names with each group_base_id in the property builder config';
    }

    public function is_needed(): bool {
        $config = get_option( self::PROPERTY_BUILDER_KEY, [] );
        if ( empty( $config ) || ! is_array( $config ) ) {
            return false;
        }

        return $this->config_has_map_name_mismatch( $config );
    }

    public function up(): bool {
        $config = get_option( self::PROPERTY_BUILDER_KEY, [] );
        if ( empty( $config ) || ! is_array( $config ) ) {
            $this->log( 'No builder config to update', 'info', '2.3.3' );
            return true;
        }

        if ( ! $this->config_has_map_name_mismatch( $config ) ) {
            $this->log( 'Map field names already consistent', 'info', '2.3.3' );
            return true;
        }

        $fixed = $this->fix_map_field_consistency( $config );
        update_option( self::PROPERTY_BUILDER_KEY, $fixed, false );

        $this->log( 'Updated map sub-field names in property builder config', 'info', '2.3.3' );
        return true;
    }

    /** {@inheritdoc} */
    public function down(): bool {
        // Non-destructive metadata alignment; no rollback required.
        return true;
    }

    /**
     * @param array $config Builder sections.
     * @return bool
     */
    private function config_has_map_name_mismatch( array $config ): bool {
        foreach ( $config as $section ) {
            foreach ( $section['fields'] ?? [] as $field ) {
                if ( ( $field['group_type'] ?? '' ) !== 'map' ) {
                    continue;
                }
                $base_id = $field['group_base_id'] ?? '';
                if ( $base_id === '' ) {
                    continue;
                }
                $expected = $base_id . '_address';
                if ( ( $field['address_field_name'] ?? '' ) !== $expected ) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * @param array $config Builder sections.
     * @return array
     */
    private function fix_map_field_consistency( array $config ): array {
        foreach ( $config as $section_key => &$section ) {
            if ( empty( $section['fields'] ) || ! is_array( $section['fields'] ) ) {
                continue;
            }
            foreach ( $section['fields'] as $field_index => &$field ) {
                if ( ( $field['group_type'] ?? '' ) !== 'map' ) {
                    continue;
                }
                $base_id = $field['group_base_id'] ?? '';
                if ( $base_id === '' ) {
                    continue;
                }
                $field['address_field_name'] = $base_id . '_address';
                $field['lat_field_name']     = $base_id . '_latitude';
                $field['lng_field_name']     = $base_id . '_longitude';
                $field['map_field_name']     = $base_id . '_preview';
                $section['fields'][ $field_index ] = $field;
            }
        }
        unset( $section, $field );

        return $config;
    }
}
