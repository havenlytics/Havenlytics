<?php

/**

 * Version 2.3.2 — Field Map Backfill Migration (batched)

 *

 * @package     Havenlytics

 * @subpackage  Core\Migration\Handlers

 * @since       2.3.2

 */



namespace HvnlyNab\Core\Migration\Handlers;

use HvnlyNab\Core\Migration\Interfaces\MigrationInterface;
use HvnlyNab\Core\Migration\Traits\MigrationTrait;

defined( 'ABSPATH' ) || exit;



class Version232Handler implements MigrationInterface {



    use MigrationTrait;



    const BATCH_KEY = 'hvnly_migration_232_page';



    /** @var array<string, string> */

    private const GROUP_DETECT_SUFFIX = array(

        'video'         => '_url',

        'gallery'       => '_images',

        'map'           => '_address',

        'property_docs' => '_documents',

    );



    public function get_version(): string {

        return '2.3.2';

    }



    public function get_description(): string {

        return 'Backfills _hvnly_field_map meta for all existing properties (batched).';

    }



    public function is_needed(): bool {

        global $wpdb;

        $count = (int) $wpdb->get_var(

            $wpdb->prepare(

                "SELECT COUNT(p.ID)

                 FROM {$wpdb->posts} p

                 LEFT JOIN {$wpdb->postmeta} pm

                     ON pm.post_id = p.ID AND pm.meta_key = '_hvnly_field_map'

                 WHERE p.post_type = %s

                   AND p.post_status != 'trash'

                   AND pm.meta_id IS NULL",

                'hvnly_property'

            )

        );

        return $count > 0;

    }



    public function up(): bool {

        $page       = max( 1, (int) get_option( self::BATCH_KEY, 1 ) );

        $batch_size = (int) ( defined( 'HVNLY_MIGRATION_BATCH_SIZE' ) ? HVNLY_MIGRATION_BATCH_SIZE : 100 );



        $this->log(

            sprintf( 'Starting _hvnly_field_map backfill (page %d, batch %d)', $page, $batch_size ),

            'info',

            '2.3.2'

        );



        $query = new \WP_Query(

            array(

                'post_type'              => 'hvnly_property',

                'post_status'            => 'any',

                'posts_per_page'         => $batch_size,

                'paged'                  => $page,

                'orderby'                => 'ID',

                'order'                  => 'ASC',

                'fields'                 => 'ids',

                'no_found_rows'          => false,

                'update_post_meta_cache' => false,

                'update_post_term_cache' => false,

                'meta_query'             => array(

                    array(

                        'key'     => '_hvnly_field_map',

                        'compare' => 'NOT EXISTS',

                    ),

                ),

            )

        );



        if ( empty( $query->posts ) ) {

            delete_option( self::BATCH_KEY );

            $this->log( 'No properties need field map backfill', 'info', '2.3.2' );

            return true;

        }



        $success       = true;

        $written       = 0;

        $skipped_empty = 0;



        foreach ( $query->posts as $post_id ) {

            $post_id = (int) $post_id;

            if ( $post_id <= 0 ) {

                continue;

            }



            if ( get_post_meta( $post_id, '_hvnly_field_map', true ) !== '' ) {

                continue;

            }



            $field_map = $this->build_field_map( $post_id );



            if ( empty( $field_map ) ) {

                ++$skipped_empty;

                continue;

            }



            if ( ! $this->safe_add_post_meta( $post_id, '_hvnly_field_map', wp_json_encode( $field_map ) ) ) {

                $success = false;

                $this->log( 'Failed to write _hvnly_field_map for post ID ' . $post_id, 'error', '2.3.2' );

            } else {

                ++$written;

            }

        }



        $this->log(

            sprintf(

                '232 batch page %d/%d: %d written, %d skipped empty, %d processed',

                $page,

                max( 1, (int) $query->max_num_pages ),

                $written,

                $skipped_empty,

                count( $query->posts )

            ),

            'info',

            '2.3.2'

        );



        if ( $page < (int) $query->max_num_pages ) {

            update_option( self::BATCH_KEY, $page + 1, false );

            return false;

        }



        delete_option( self::BATCH_KEY );

        return $success;

    }



    public function down(): bool {

        global $wpdb;

        $wpdb->delete( $wpdb->postmeta, array( 'meta_key' => '_hvnly_field_map' ), array( '%s' ) );

        return true;

    }



    /**

     * @param int $post_id Post ID.

     * @return array<string, string>

     */

    private function build_field_map( int $post_id ): array {

        $all_meta  = get_post_meta( $post_id );

        $meta_keys = array_keys( $all_meta );

        $field_map = array();



        foreach ( self::GROUP_DETECT_SUFFIX as $group_type => $suffix ) {

            foreach ( $meta_keys as $key ) {

                if (

                    substr( $key, -strlen( $suffix ) ) === $suffix

                    && strpos( $key, $group_type ) === 0

                ) {

                    $base_id = substr( $key, 0, -strlen( $suffix ) );

                    if ( $base_id !== '' ) {

                        $field_map[ $group_type ] = $base_id;

                        break;

                    }

                }

            }

        }



        return $field_map;

    }

}


