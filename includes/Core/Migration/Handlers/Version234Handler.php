<?php
/**
 * Version 2.3.4 — Group field data isolation
 *
 * Fixes builder config where multiple group instances share the same
 * group_base_id (typically from master-ID fallback). Each group_id gets
 * a unique base and field names are rewritten in-place.
 *
 * @package     Havenlytics
 * @subpackage  Core\Migration\Handlers
 * @since       2.3.4
 */

namespace HvnlyNab\Core\Migration\Handlers;

use HvnlyNab\Core\GroupFieldIdentity;
use HvnlyNab\Core\Migration\Interfaces\MigrationInterface;
use HvnlyNab\Core\Migration\Traits\MigrationTrait;

defined( 'ABSPATH' ) || exit;

class Version234Handler implements MigrationInterface {

    use MigrationTrait;

    const PROPERTY_BUILDER_KEY = 'hvnly_property_builder.sections';

    public function get_version(): string {
        return '2.3.4';
    }

    public function get_description(): string {
        return 'Ensures each group field instance has a unique group_base_id to prevent cross-group data leakage';
    }

    public function is_needed(): bool {
        $config = get_option( self::PROPERTY_BUILDER_KEY, [] );
        if ( empty( $config ) || ! is_array( $config ) ) {
            return false;
        }

        return $this->config_has_duplicate_group_bases( $config );
    }

    public function up(): bool {
        $this->log( 'Starting group field isolation migration 2.3.4', 'info', '2.3.4' );

        $config = get_option( self::PROPERTY_BUILDER_KEY, [] );
        if ( empty( $config ) || ! is_array( $config ) ) {
            $this->log( 'No builder config to update', 'info', '2.3.4' );
            return true;
        }

        $this->backup_option( self::PROPERTY_BUILDER_KEY, '2.3.4' );

        $fixed = GroupFieldIdentity::deduplicate_sections( $config );

        $remap = \HvnlyNab\Core\DataPreservation\BuilderMetaMigrator::build_remap_between_configs( $config, $fixed );

        $this->safe_update_option( self::PROPERTY_BUILDER_KEY, $fixed );
        wp_cache_delete( self::PROPERTY_BUILDER_KEY, 'options' );
        wp_cache_delete( 'hvnly_standardized_field_names' );

        if ( ! empty( $remap ) ) {
            \HvnlyNab\Core\DataPreservation\BuilderMetaMigrator::schedule_property_remap( $remap );
            $this->log(
                'Scheduled batched property meta remap for ' . count( $remap ) . ' base ID change(s) (migration 2.3.6)',
                'info',
                '2.3.4'
            );
        }

        $this->log( 'Builder config deduplicated — each group instance now has a unique base ID', 'info', '2.3.4' );
        return true;
    }

    /** {@inheritdoc} */
    public function down(): bool {
        return true;
    }

    /**
     * Detect duplicate group_base_id values assigned to different group_ids.
     *
     * @param array $config Builder sections.
     * @return bool
     */
    private function config_has_duplicate_group_bases( array $config ): bool {
        $base_to_group = array();

        foreach ( $config as $section ) {
            foreach ( $section['fields'] ?? [] as $field ) {
                $base = $field['group_base_id'] ?? '';
                $gid  = $field['group_id'] ?? '';

                if ( $base === '' || $gid === '' ) {
                    continue;
                }

                if ( isset( $base_to_group[ $base ] ) && $base_to_group[ $base ] !== $gid ) {
                    return true;
                }

                $base_to_group[ $base ] = $gid;
            }
        }

        return false;
    }
}
