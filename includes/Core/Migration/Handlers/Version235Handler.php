<?php

/**

 * Version 2.3.5 — Soft-flag duplicate group meta (never hard-delete property data).

 *

 * @package     Havenlytics

 * @subpackage  Core\Migration\Handlers

 * @since       2.3.5

 */



namespace HvnlyNab\Core\Migration\Handlers;



use HvnlyNab\Core\DataPreservation\BatchProcessor;

use HvnlyNab\Core\GroupFieldIdentity;

use HvnlyNab\Core\Migration\Interfaces\MigrationInterface;

use HvnlyNab\Core\Migration\Traits\MigrationTrait;



defined( 'ABSPATH' ) || exit;



class Version235Handler implements MigrationInterface {



    use MigrationTrait;



    const BATCH_KEY = 'hvnly_migration_235_offset';

    const DONE_KEY = 'hvnly_migration_235_done';



    /** @var array<string, array<string>> */

    private const TYPE_SUFFIXES = array(

        'video'         => array( 'url', 'title', 'thumbnail' ),

        'gallery'       => array( 'images', 'title' ),

        'map'           => array( 'address', 'latitude', 'longitude', 'preview' ),

        'property_docs' => array( 'documents', 'show_in_sidebar' ),

    );



    public function get_version(): string {

        return '2.3.5';
    }



    public function get_description(): string {

        return 'Soft-flags duplicate group field meta (orphan candidates) — no automatic deletion';
    }



    public function is_needed(): bool {

        return ! get_option( self::DONE_KEY, false );
    }



    public function up(): bool {

        $this->log( 'Starting soft orphan scan migration 2.3.5', 'info', '2.3.5' );

        $flagged = 0;

        $result = BatchProcessor::each_property(

            function ( int $post_id ) use ( &$flagged ) {

                $flagged += $this->flag_property_leaked_meta( $post_id );
            },

            self::BATCH_KEY

        );

        $this->log(

            sprintf(

                '235 batch: flagged %d keys on %d properties (progress %d/%d)',

                $flagged,

                $result['processed'],

                $result['offset'],

                $result['total']

            ),

            'info',

            '2.3.5'

        );

        if ( ! $result['complete'] ) {

            return false;

        }

        update_option( self::DONE_KEY, 1, false );

        $this->log( '235 soft orphan scan complete — no meta deleted', 'info', '2.3.5' );

        return true;
    }



    public function down(): bool {

        return true;
    }



    /**

     * @param int $post_id Property post ID.

     * @return int Number of keys soft-flagged.

     */

    private function flag_property_leaked_meta( int $post_id ): int {

        $map = GroupFieldIdentity::get_field_map( $post_id );

        if ( empty( $map['legacy'] ) && empty( $map['groups'] ) ) {

            return 0;

        }

        $flagged = 0;

        $all_meta = get_post_meta( $post_id );

        $canonical = array();

        foreach ( self::TYPE_SUFFIXES as $type => $suffixes ) {

            $bases = array();

            if ( ! empty( $map['legacy'][ $type ] ) ) {

                $bases[] = (string) $map['legacy'][ $type ];

            }

            foreach ( $map['groups'] as $base ) {

                if ( is_string( $base ) && strpos( $base, $type . '_' ) === 0 ) {

                    $bases[] = $base;

                }

            }

            $bases = array_unique( array_filter( $bases ) );

            if ( empty( $bases ) ) {

                continue;

            }

            foreach ( $bases as $base ) {

                foreach ( $suffixes as $suffix ) {

                    $key = $base . '_' . $suffix;

                    if ( isset( $all_meta[ $key ] ) ) {

                        $val = maybe_unserialize( $all_meta[ $key ][0] ?? '' );

                        if ( $val !== '' && $val !== false && $val !== null ) {

                            $canonical[ $suffix ] = $canonical[ $suffix ] ?? array();

                            $canonical[ $suffix ][ $type ] = $canonical[ $suffix ][ $type ] ?? array();

                            $canonical[ $suffix ][ $type ][] = array(

                                'base'  => $base,

                                'value' => is_scalar( $val ) ? (string) $val : wp_json_encode( $val ),

                            );

                        }

                    }

                }

            }

        }

        if ( empty( $canonical ) ) {

            return 0;

        }

        foreach ( $all_meta as $meta_key => $rows ) {

            if ( ! is_string( $meta_key ) ) {

                continue;

            }

            foreach ( self::TYPE_SUFFIXES as $type => $suffixes ) {

                foreach ( $suffixes as $suffix ) {

                    $needle = '_' . $suffix;

                    $len = strlen( $needle );

                    if ( strlen( $meta_key ) <= $len || substr( $meta_key, -$len ) !== $needle ) {

                        continue;

                    }

                    if ( strpos( $meta_key, $type . '_' ) !== 0 ) {

                        continue;

                    }

                    $base = substr( $meta_key, 0, -$len );

                    $val = maybe_unserialize( $rows[0] ?? '' );

                    if ( $val === '' || $val === false || $val === null ) {

                        continue;

                    }

                    $str_val = is_scalar( $val ) ? (string) $val : wp_json_encode( $val );

                    if ( empty( $canonical[ $suffix ][ $type ] ) ) {

                        continue;

                    }

                    foreach ( $canonical[ $suffix ][ $type ] as $canon ) {

                        if ( $canon['base'] === $base ) {

                            continue 2;

                        }

                        if ( $canon['value'] === $str_val ) {

                            if ( function_exists( 'hvnly_mark_orphan_candidate' ) ) {

                                hvnly_mark_orphan_candidate( $post_id, $meta_key );

                            }

                            ++$flagged;

                            continue 2;

                        }

                    }

                }

            }

        }

        return $flagged;
    }
}
