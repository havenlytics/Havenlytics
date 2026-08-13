<?php

/**

 * Version 2.3.6 — Resume builder meta remap after 2.3.4 base ID changes (batched).

 *

 * @package     Havenlytics

 * @subpackage  Core\Migration\Handlers

 * @since       2.3.6

 */



namespace HvnlyNab\Core\Migration\Handlers;



use HvnlyNab\Core\DataPreservation\BuilderMetaMigrator;

use HvnlyNab\Core\Migration\Interfaces\MigrationInterface;

use HvnlyNab\Core\Migration\Traits\MigrationTrait;



defined( 'ABSPATH' ) || exit;



class Version236Handler implements MigrationInterface {



    use MigrationTrait;



    const BUILDER_KEY = 'hvnly_property_builder.sections';

    const REMAP_OPTION = 'hvnly_migration_236_remap';

    const BATCH_KEY = 'hvnly_migration_236_offset';

    const DONE_KEY = 'hvnly_migration_236_done';



    public function get_version(): string {

        return '2.3.6';
    }



    public function get_description(): string {

        return 'Batched property meta remap after builder group_base_id changes (preserves all legacy keys)';
    }



    public function is_needed(): bool {

        if ( get_option( self::DONE_KEY, false ) ) {

            return false;

        }

        $remap = get_option( self::REMAP_OPTION, array() );

        return is_array( $remap ) && ! empty( $remap );
    }



    public function up(): bool {

        $remap = get_option( self::REMAP_OPTION, array() );

        if ( ! is_array( $remap ) || empty( $remap ) ) {

            update_option( self::DONE_KEY, 1, false );

            return true;

        }

        $copied = 0;

        $result = \HvnlyNab\Core\DataPreservation\BatchProcessor::each_property(

            function ( int $post_id ) use ( $remap, &$copied ) {

                $copied += BuilderMetaMigrator::remap_property_meta( $post_id, $remap );
            },

            self::BATCH_KEY

        );

        $this->log(

            sprintf(

                '236 batch: processed %d properties (offset %d/%d), keys copied this batch context',

                $result['processed'],

                $result['offset'],

                $result['total']

            ),

            'info',

            '2.3.6'

        );

        if ( $result['complete'] ) {

            delete_option( self::REMAP_OPTION );

            update_option( self::DONE_KEY, 1, false );

            $this->log( '236 builder meta remap complete', 'info', '2.3.6' );

            return true;

        }

        return false;
    }



    public function down(): bool {

        return true;
    }



    /**

     * Store a remap table for batched processing (called from 2.3.4).

     *

     * @param array<string,string> $old_to_new old_base => new_base.

     * @return void

     */

    public static function schedule_remap( array $old_to_new ): void {

        if ( empty( $old_to_new ) ) {

            return;

        }

        $existing = get_option( self::REMAP_OPTION, array() );
        if ( is_array( $existing ) && ! empty( $existing ) ) {
            $old_to_new = array_merge( $existing, $old_to_new );
        }

        update_option( self::REMAP_OPTION, $old_to_new, false );

        delete_option( self::DONE_KEY );

        delete_option( self::BATCH_KEY );

        $completed = get_option( \HvnlyNab\Core\Migration\Migrator::COMPLETED_KEY, array() );
        if ( is_array( $completed ) && isset( $completed['2.3.6'] ) ) {
            unset( $completed['2.3.6'] );
            update_option( \HvnlyNab\Core\Migration\Migrator::COMPLETED_KEY, $completed, false );
        }
    }
}
