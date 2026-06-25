<?php
/**
 * Meta Cleanup API Endpoint - COMPLETE FINAL VERSION
 * 
 * Handles cleanup of ALL orphaned meta data with full control
 *
 * @package HvnlyNab\Api\Type\Builders
 * @since 2.0.0
 */

namespace HvnlyNab\Api\Type\Builders;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}
class MetaCleanup
{
    /**
     * Storage key for property builder sections
     *
     * @var string
     */
    private $storage_key = 'hvnly_property_builder.sections';

    /**
     * API namespace
     *
     * @var string
     */
    private $namespace = 'hvnlynab/v1';

    /**
     * Route base
     *
     * @var string
     */
    private $route_base = 'meta-cleanup';

    /**
     * Constructor
     */
    public function __construct()
    {
        add_action('rest_api_init', [$this, 'routes']);
    }

    /**
     * Register REST API routes
     */
    public function routes()
    {
        // GET orphaned meta keys
        register_rest_route($this->namespace, '/' . $this->route_base . '/orphaned', [
            'methods' => 'GET',
            'callback' => [$this, 'get_orphaned_meta'],
            'permission_callback' => [$this, 'get_per'],
            'args' => [
                'property_id' => [
                    'required' => false,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint'
                ]
            ]
        ]);

        // POST cleanup orphaned meta
        register_rest_route($this->namespace, '/' . $this->route_base . '/cleanup', [
            'methods' => 'POST',
            'callback' => [$this, 'cleanup_orphaned_meta'],
            'permission_callback' => [$this, 'create_per'],
            'args' => [
                'fields' => [
                    'required' => true,
                    'type' => 'array',
                    'validate_callback' => function($param) {
                        return is_array($param) && !empty($param);
                    }
                ],
                'property_id' => [
                    'required' => false,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint'
                ]
            ]
        ]);

        // POST reset and cleanup combined
        register_rest_route($this->namespace, '/' . $this->route_base . '/reset-and-cleanup', [
            'methods' => 'POST',
            'callback' => [$this, 'reset_and_cleanup_aggressive'],
            'permission_callback' => [$this, 'create_per'],
        ]);

        // GET cleanup stats
        register_rest_route($this->namespace, '/' . $this->route_base . '/stats', [
            'methods' => 'GET',
            'callback' => [$this, 'get_cleanup_stats'],
            'permission_callback' => [$this, 'get_per'],
        ]);

        // POST complete database cleanup
        register_rest_route($this->namespace, '/' . $this->route_base . '/cleanup-all', [
            'methods' => 'POST',
            'callback' => [$this, 'cleanup_all_orphaned'],
            'permission_callback' => [$this, 'create_per'],
        ]);

        // POST inventory export (scan-only report)
        register_rest_route($this->namespace, '/' . $this->route_base . '/inventory', [
            'methods' => 'GET',
            'callback' => [$this, 'get_data_inventory'],
            'permission_callback' => [$this, 'get_per'],
        ]);

        // POST final cleanup — scan-only unless confirm=true
        register_rest_route($this->namespace, '/' . $this->route_base . '/final-cleanup', [
            'methods' => 'POST',
            'callback' => [$this, 'final_cleanup'],
            'permission_callback' => [$this, 'create_per'],
        ]);
    }

    /**
     * Permission callback for GET requests
     */
    public function get_per()
    {
        return current_user_can('manage_options');
    }

    /**
     * Permission callback for POST requests
     */
    public function create_per()
    {
        return current_user_can('manage_options');
    }

    /**
     * Data preservation inventory (Phase 1 report) — read-only.
     */
    public function get_data_inventory( $request ) {
        if ( ! class_exists( '\HvnlyNab\Core\DataPreservation\DataKeyInventory' ) ) {
            return new \WP_Error( 'unavailable', __( 'Inventory module unavailable', 'havenlytics' ), array( 'status' => 500 ) );
        }
        return rest_ensure_response( array(
            'success' => true,
            'data'    => \HvnlyNab\Core\DataPreservation\DataKeyInventory::generate_report(),
        ) );
    }

    /**
     * Reset + cleanup — scan-only; protected meta is never deleted without wp-config override.
     */
    public function reset_and_cleanup_aggressive($request)
    {
        try {
            $nonce = $request->get_header('X-WP-Nonce');
            if (!wp_verify_nonce($nonce, 'wp_rest')) {
                return new WP_Error('rest_forbidden', __('Invalid nonce', 'havenlytics'), ['status' => 403]);
            }

            $confirm         = rest_sanitize_boolean( $request->get_param( 'confirm' ) );
            $force_protected = rest_sanitize_boolean( $request->get_param( 'force_protected' ) );
            $scan            = $this->scan_all_orphaned_meta();

            if ( ! $confirm || ! function_exists( 'hvnly_rest_deletion_confirmed' ) || ! hvnly_rest_deletion_confirmed( $confirm ) ) {
                return rest_ensure_response( array(
                    'success'        => true,
                    'scan_only'      => true,
                    'message'        => __( 'Scan complete. Protected property meta is report-only. Non-protected orphans require confirm=true to delete.', 'havenlytics' ),
                    'scan'           => $scan,
                    'builder_reset'  => false,
                ) );
            }

            $deleted_count = $this->delete_scan_candidates( $scan, $force_protected );

            return rest_ensure_response( array(
                'success'       => true,
                'scan_only'     => false,
                'deleted_count' => $deleted_count,
                'scan'          => $scan,
            ) );

        } catch (\Exception $e) {
            return new WP_Error(
                'reset_cleanup_failed',
                __('Failed to reset and cleanup', 'havenlytics'),
                ['status' => 500]
            );
        }
    }

    /**
     * Scan orphaned meta across properties (no deletion).
     *
     * @return array
     */
    private function scan_all_orphaned_meta(): array {
        $current_fields  = $this->get_current_field_names();
        $candidates      = array();
        $protected_meta  = array();
        $by_type         = array(
            'gallery' => 0,
            'video'   => 0,
            'map'     => 0,
            'legacy'  => 0,
            'other'   => 0,
        );
        $protected_types = array(
            'gallery' => 0,
            'video'   => 0,
            'map'     => 0,
            'legacy'  => 0,
            'other'   => 0,
        );

        $batch_key = 'hvnly_cleanup_scan_offset';
        $offset    = (int) get_option( $batch_key, 0 );
        $batch     = defined( 'HVNLY_MIGRATION_BATCH_SIZE' ) ? (int) HVNLY_MIGRATION_BATCH_SIZE : 100;

        $query = new \WP_Query( array(
            'post_type'      => 'hvnly_property',
            'post_status'    => 'any',
            'posts_per_page' => $batch,
            'offset'         => $offset,
            'orderby'        => 'ID',
            'order'          => 'ASC',
            'fields'         => 'ids',
        ) );

        foreach ( $query->posts as $property_id ) {
            $property_id = (int) $property_id;
            $all_meta    = get_post_meta( $property_id );
            foreach ( $all_meta as $meta_key => $meta_values ) {
                if ( $this->is_system_key( $meta_key ) ) {
                    continue;
                }
                if ( in_array( $meta_key, $current_fields, true ) ) {
                    continue;
                }

                $type_bucket = $this->classify_meta_key_type( $meta_key );
                $row         = array(
                    'post_id'  => $property_id,
                    'meta_key' => $meta_key,
                );

                $is_protected = function_exists( 'hvnly_meta_cleanup_is_protected' )
                    ? hvnly_meta_cleanup_is_protected( $property_id, $meta_key, $current_fields )
                    : ( function_exists( 'hvnly_is_protected_meta_key' ) && hvnly_is_protected_meta_key( $meta_key ) );

                if ( $is_protected ) {
                    $row['protected']   = true;
                    $row['deletable']   = function_exists( 'hvnly_allow_protected_meta_delete' ) && hvnly_allow_protected_meta_delete();
                    $protected_meta[]   = $row;
                    ++$protected_types[ $type_bucket ];
                    continue;
                }

                $row['protected'] = false;
                $row['deletable'] = true;
                $candidates[]     = $row;
                ++$by_type[ $type_bucket ];
            }
        }

        $total    = (int) $query->found_posts;
        $new_off  = $offset + count( $query->posts );
        $complete = $new_off >= $total || count( $query->posts ) === 0;
        if ( $complete ) {
            delete_option( $batch_key );
        } else {
            update_option( $batch_key, $new_off, false );
        }

        return array(
            'properties_scanned'  => count( $query->posts ),
            'total_properties'    => $total,
            'scan_complete'       => $complete,
            'candidates'          => $candidates,
            'protected_meta'      => $protected_meta,
            'by_type'             => $by_type,
            'protected_by_type'   => $protected_types,
            'candidate_count'     => count( $candidates ),
            'protected_count'     => count( $protected_meta ),
            'protected_read_only' => true,
        );
    }

    /**
     * Delete only non-protected scan candidates (protected keys require wp-config override).
     *
     * @param array $scan            Scan result from scan_all_orphaned_meta().
     * @param bool  $force_protected Developer override flag from REST request.
     * @return int
     */
    private function delete_scan_candidates( array $scan, bool $force_protected ): int {
        $deleted_count  = 0;
        $current_fields = $this->get_current_field_names();

        foreach ( $scan['candidates'] as $row ) {
            if ( $this->attempt_meta_cleanup_delete( (int) $row['post_id'], $row['meta_key'], $current_fields, $force_protected ) ) {
                ++$deleted_count;
            }
        }

        if ( $force_protected && ! empty( $scan['protected_meta'] ) ) {
            foreach ( $scan['protected_meta'] as $row ) {
                if ( $this->attempt_meta_cleanup_delete( (int) $row['post_id'], $row['meta_key'], $current_fields, true ) ) {
                    ++$deleted_count;
                }
            }
        }

        return $deleted_count;
    }

    /**
     * @param int      $post_id         Property post ID.
     * @param string   $meta_key        Meta key.
     * @param string[] $current_fields  Builder field names.
     * @param bool     $force_protected Developer override.
     * @return bool
     */
    private function attempt_meta_cleanup_delete( int $post_id, string $meta_key, array $current_fields, bool $force_protected ): bool {
        if ( function_exists( 'hvnly_meta_cleanup_is_deletable' ) ) {
            if ( ! hvnly_meta_cleanup_is_deletable( $post_id, $meta_key, $current_fields, $force_protected ) ) {
                return false;
            }
        } elseif ( function_exists( 'hvnly_is_protected_meta_key' ) && hvnly_is_protected_meta_key( $meta_key ) ) {
            return false;
        }

        if ( ! function_exists( 'hvnly_safe_delete_post_meta' ) ) {
            return false;
        }

        $context = ( $force_protected && function_exists( 'hvnly_allow_protected_meta_delete' ) && hvnly_allow_protected_meta_delete() )
            ? 'developer_override_delete'
            : 'admin_confirmed_delete';

        return (bool) hvnly_safe_delete_post_meta( $post_id, $meta_key, $context );
    }

    /**
     * @param string $meta_key Meta key.
     * @return string
     */
    private function classify_meta_key_type( string $meta_key ): string {
        if ( strpos( $meta_key, 'gallery_' ) === 0 ) {
            return 'gallery';
        }
        if ( strpos( $meta_key, 'video_' ) === 0 ) {
            return 'video';
        }
        if ( strpos( $meta_key, 'map_' ) === 0 ) {
            return 'map';
        }
        if ( strpos( $meta_key, '_hvnly_' ) === 0 || strpos( $meta_key, '_havenlytics_' ) === 0 ) {
            return 'legacy';
        }
        return 'other';
    }

    /**
     * Cleanup ALL orphaned meta — scan-only unless confirm=true.
     */
    public function cleanup_all_orphaned($request)
    {
        try {
            $nonce = $request->get_header('X-WP-Nonce');
            if (!wp_verify_nonce($nonce, 'wp_rest')) {
                return new WP_Error('rest_forbidden', __('Invalid nonce', 'havenlytics'), ['status' => 403]);
            }

            $confirm         = rest_sanitize_boolean( $request->get_param( 'confirm' ) );
            $force_protected = rest_sanitize_boolean( $request->get_param( 'force_protected' ) );
            $scan            = $this->scan_all_orphaned_meta();

            if ( ! $confirm || ! function_exists( 'hvnly_rest_deletion_confirmed' ) || ! hvnly_rest_deletion_confirmed( $confirm ) ) {
                return rest_ensure_response( array(
                    'success'   => true,
                    'scan_only' => true,
                    'message'   => __( 'Orphan scan report (no data deleted). Protected meta is report-only.', 'havenlytics' ),
                    'scan'      => $scan,
                ) );
            }

            $deleted = $this->delete_scan_candidates( $scan, $force_protected );

            return rest_ensure_response( array(
                'success'       => true,
                'scan_only'     => false,
                'deleted_count' => $deleted,
                'scan'          => $scan,
            ) );

        } catch (\Exception $e) {
            return new WP_Error(
                'cleanup_failed',
                __('Failed to cleanup meta', 'havenlytics'),
                ['status' => 500]
            );
        }
    }

    /**
     * FINAL cleanup — scan legacy pattern matches; delete only with confirm=true.
     */
    public function final_cleanup($request)
    {
        try {
            $nonce = $request->get_header('X-WP-Nonce');
            if (!wp_verify_nonce($nonce, 'wp_rest')) {
                return new WP_Error('rest_forbidden', __('Invalid nonce', 'havenlytics'), ['status' => 403]);
            }

            $confirm         = rest_sanitize_boolean( $request->get_param( 'confirm' ) );
            $force_protected = rest_sanitize_boolean( $request->get_param( 'force_protected' ) );
            $scan            = $this->scan_all_orphaned_meta();

            if ( ! $confirm || ! function_exists( 'hvnly_rest_deletion_confirmed' ) || ! hvnly_rest_deletion_confirmed( $confirm ) ) {
                return rest_ensure_response( array(
                    'success'   => true,
                    'scan_only' => true,
                    'message'   => __( 'Legacy pattern scan (no deletion). Protected meta is report-only.', 'havenlytics' ),
                    'scan'      => $scan,
                ) );
            }

            $deleted = $this->delete_scan_candidates( $scan, $force_protected );

            return rest_ensure_response( array(
                'success'       => true,
                'scan_only'     => false,
                'deleted_count' => $deleted,
                'scan'          => $scan,
            ) );

        } catch (\Exception $e) {
            return new WP_Error(
                'cleanup_failed',
                __('Failed to cleanup meta', 'havenlytics'),
                ['status' => 500]
            );
        }
    }

    /**
     * Get orphaned meta keys 
     */
    public function get_orphaned_meta($request)
    {
        try {
            $nonce = $request->get_header('X-WP-Nonce');
            if (!wp_verify_nonce($nonce, 'wp_rest')) {
                return new WP_Error('rest_forbidden', __('Invalid nonce', 'havenlytics'), ['status' => 403]);
            }

            global $wpdb;
            $property_id = $request->get_param('property_id');

            // Get current field names from builder configuration
            $current_fields = $this->get_current_field_names();

            // Build query for meta keys with proper LIKE placeholders
            $like_pattern = '%_hvnly_%';
            
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            if ($property_id) {
                // For single property - directly use prepare in get_col
                $meta_keys = $wpdb->get_col(
                    $wpdb->prepare(
                        "SELECT DISTINCT meta_key FROM {$wpdb->postmeta} 
                        WHERE post_id = %d AND meta_key LIKE %s",
                        $property_id,
                        $wpdb->esc_like($like_pattern)
                    )
                );
                
                // Get gallery keys for this property
                $gallery_keys = $wpdb->get_col(
                    $wpdb->prepare(
                        "SELECT DISTINCT meta_key FROM {$wpdb->postmeta} 
                        WHERE post_id = %d AND meta_key LIKE %s",
                        $property_id,
                        $wpdb->esc_like('gallery_') . '%'
                    )
                );
                
                // Get video keys for this property
                $video_keys = $wpdb->get_col(
                    $wpdb->prepare(
                        "SELECT DISTINCT meta_key FROM {$wpdb->postmeta} 
                        WHERE post_id = %d AND meta_key LIKE %s",
                        $property_id,
                        $wpdb->esc_like('video_') . '%'
                    )
                );
                
                // Get map keys for this property
                $map_keys = $wpdb->get_col(
                    $wpdb->prepare(
                        "SELECT DISTINCT meta_key FROM {$wpdb->postmeta} 
                        WHERE post_id = %d AND meta_key LIKE %s",
                        $property_id,
                        $wpdb->esc_like('map_') . '%'
                    )
                );
                
                // Get havenlytics keys for this property
                $havenlytics_keys = $wpdb->get_col(
                    $wpdb->prepare(
                        "SELECT DISTINCT meta_key FROM {$wpdb->postmeta} 
                        WHERE post_id = %d AND meta_key LIKE %s",
                        $property_id,
                        $wpdb->esc_like('_havenlytics_') . '%'
                    )
                );
            } else {
                // For all properties
                $meta_keys = $wpdb->get_col(
                    $wpdb->prepare(
                        "SELECT DISTINCT meta_key FROM {$wpdb->postmeta} 
                        WHERE meta_key LIKE %s",
                        $wpdb->esc_like($like_pattern)
                    )
                );
                
                // Get gallery keys
                $gallery_keys = $wpdb->get_col(
                    $wpdb->prepare(
                        "SELECT DISTINCT meta_key FROM {$wpdb->postmeta} 
                        WHERE meta_key LIKE %s",
                        $wpdb->esc_like('gallery_') . '%'
                    )
                );
                
                // Get video keys
                $video_keys = $wpdb->get_col(
                    $wpdb->prepare(
                        "SELECT DISTINCT meta_key FROM {$wpdb->postmeta} 
                        WHERE meta_key LIKE %s",
                        $wpdb->esc_like('video_') . '%'
                    )
                );
                
                // Get map keys
                $map_keys = $wpdb->get_col(
                    $wpdb->prepare(
                        "SELECT DISTINCT meta_key FROM {$wpdb->postmeta} 
                        WHERE meta_key LIKE %s",
                        $wpdb->esc_like('map_') . '%'
                    )
                );
                
                // Get havenlytics keys
                $havenlytics_keys = $wpdb->get_col(
                    $wpdb->prepare(
                        "SELECT DISTINCT meta_key FROM {$wpdb->postmeta} 
                        WHERE meta_key LIKE %s",
                        $wpdb->esc_like('_havenlytics_') . '%'
                    )
                );
            }
            // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            
            // Merge all keys
            $meta_keys = array_merge($meta_keys, $gallery_keys, $video_keys, $map_keys, $havenlytics_keys);
            $meta_keys = array_unique($meta_keys);

            if (empty($meta_keys)) {
                $meta_keys = [];
            }

            // Find orphaned keys (non-protected only; protected keys are report-only).
            $orphaned = array_diff( $meta_keys, $current_fields );
            $orphaned = array_filter( $orphaned, [ $this, 'is_not_system_key' ] );

            $deletable_orphans = array();
            $protected_orphans = array();

            if ( $property_id ) {
                $pid = (int) $property_id;
                foreach ( $orphaned as $key ) {
                    $is_protected = function_exists( 'hvnly_meta_cleanup_is_protected' )
                        ? hvnly_meta_cleanup_is_protected( $pid, $key, $current_fields )
                        : ( function_exists( 'hvnly_is_protected_meta_key' ) && hvnly_is_protected_meta_key( $key ) );
                    if ( $is_protected ) {
                        $protected_orphans[] = $key;
                    } else {
                        $deletable_orphans[] = $key;
                    }
                }
            } else {
                foreach ( $orphaned as $key ) {
                    if ( function_exists( 'hvnly_is_protected_meta_key' ) && hvnly_is_protected_meta_key( $key ) ) {
                        $protected_orphans[] = $key;
                    } else {
                        $deletable_orphans[] = $key;
                    }
                }
            }

            // Group deletable orphans by type.
            $grouped = [
                'gallery' => [],
                'video' => [],
                'map' => [],
                'legacy' => [],
                'other' => []
            ];

            foreach ( $deletable_orphans as $key ) {
                $grouped[ $this->classify_meta_key_type( $key ) ][] = $key;
            }

            $protected_grouped = [
                'gallery' => [],
                'video' => [],
                'map' => [],
                'legacy' => [],
                'other' => []
            ];

            foreach ( $protected_orphans as $key ) {
                $protected_grouped[ $this->classify_meta_key_type( $key ) ][] = $key;
            }

            return rest_ensure_response([
                'success' => true,
                'data' => $grouped,
                'protected_meta' => $protected_grouped,
                'total' => count( $deletable_orphans ),
                'protected_total' => count( $protected_orphans ),
                'protected_read_only' => true,
                'property_id' => $property_id
            ]);

        } catch (\Exception $e) {
            return new WP_Error(
                'fetch_failed',
                __('Failed to fetch orphaned meta', 'havenlytics'),
                ['status' => 500]
            );
        }
    }

    /**
     * Cleanup orphaned meta data for specific fields
     */
    public function cleanup_orphaned_meta($request)
    {
        try {
            $nonce = $request->get_header('X-WP-Nonce');
            if (!wp_verify_nonce($nonce, 'wp_rest')) {
                return new WP_Error('rest_forbidden', __('Invalid nonce', 'havenlytics'), ['status' => 403]);
            }

            global $wpdb;
            $fields = $request->get_param('fields');
            $property_id = $request->get_param('property_id');

            if (empty($fields) || !is_array($fields)) {
                return new WP_Error(
                    'invalid_data',
                    __('Fields array is required', 'havenlytics'),
                    ['status' => 400]
                );
            }

            $confirm         = rest_sanitize_boolean( $request->get_param( 'confirm' ) );
            $force_protected = rest_sanitize_boolean( $request->get_param( 'force_protected' ) );

            if ( ! $confirm || ! function_exists( 'hvnly_rest_deletion_confirmed' ) || ! hvnly_rest_deletion_confirmed( $confirm ) ) {
                return new WP_Error(
                    'confirm_required',
                    __( 'Deletion requires confirm=true. Scan orphaned keys via GET /orphaned first.', 'havenlytics' ),
                    array( 'status' => 400 )
                );
            }

            if ( ! $property_id ) {
                return new WP_Error(
                    'property_required',
                    __( 'property_id is required for targeted cleanup.', 'havenlytics' ),
                    array( 'status' => 400 )
                );
            }

            $deleted_count  = 0;
            $deleted_keys   = array();
            $skipped_keys   = array();
            $current_fields = $this->get_current_field_names();
            $post_id        = (int) $property_id;

            foreach ( $fields as $field_name ) {
                if ( empty( $field_name ) || ! is_string( $field_name ) ) {
                    continue;
                }

                if ( ! $this->attempt_meta_cleanup_delete( $post_id, $field_name, $current_fields, $force_protected ) ) {
                    $skipped_keys[] = $field_name;
                    continue;
                }

                ++$deleted_count;
                $deleted_keys[] = $field_name;
            }

            return rest_ensure_response([
                'success' => true,
                'message' => sprintf(
                    /* translators: %d: number of deleted entries */
                    __('Successfully cleaned up %d orphaned meta entries', 'havenlytics'),
                    $deleted_count
                ),
                'deleted_count' => $deleted_count,
                'deleted_keys' => $deleted_keys,
                'skipped_protected' => $skipped_keys,
            ]);

        } catch (\Exception $e) {
            return new WP_Error(
                'cleanup_failed',
                __('Failed to cleanup meta', 'havenlytics'),
                ['status' => 500]
            );
        }
    }

    /**
     * Get cleanup statistics 
     */
    public function get_cleanup_stats($request)
    {
        try {
            $nonce = $request->get_header('X-WP-Nonce');
            if (!wp_verify_nonce($nonce, 'wp_rest')) {
                return new WP_Error('rest_forbidden', __('Invalid nonce', 'havenlytics'), ['status' => 403]);
            }

            global $wpdb;

            $total_properties = wp_count_posts('hvnly_property')->publish ?? 0;
            
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            // Get main _hvnly_ count
            $total_meta = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->postmeta} pm
                    INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
                    WHERE p.post_type = 'hvnly_property'
                    AND pm.meta_key LIKE %s",
                    $wpdb->esc_like('%_hvnly_%')
                )
            );
            
            // Get gallery count
            $gallery_count = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->postmeta} pm
                    INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
                    WHERE p.post_type = 'hvnly_property'
                    AND pm.meta_key LIKE %s",
                    $wpdb->esc_like('gallery_') . '%'
                )
            );
            
            // Get video count
            $video_count = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->postmeta} pm
                    INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
                    WHERE p.post_type = 'hvnly_property'
                    AND pm.meta_key LIKE %s",
                    $wpdb->esc_like('video_') . '%'
                )
            );
            
            // Get map count
            $map_count = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->postmeta} pm
                    INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
                    WHERE p.post_type = 'hvnly_property'
                    AND pm.meta_key LIKE %s",
                    $wpdb->esc_like('map_') . '%'
                )
            );
            
            // Get havenlytics count
            $havenlytics_count = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->postmeta} pm
                    INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
                    WHERE p.post_type = 'hvnly_property'
                    AND pm.meta_key LIKE %s",
                    $wpdb->esc_like('_havenlytics_') . '%'
                )
            );
            // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            
            // Sum all counts
            $total_meta = (int) $total_meta + (int) $gallery_count + (int) $video_count + (int) $map_count + (int) $havenlytics_count;

            return rest_ensure_response([
                'success' => true,
                'data' => [
                    'total_properties' => (int) $total_properties,
                    'total_meta_entries' => (int) $total_meta,
                    'storage_key' => $this->storage_key
                ]
            ]);

        } catch (\Exception $e) {
            return new WP_Error(
                'stats_failed',
                __('Failed to get stats', 'havenlytics'),
                ['status' => 500]
            );
        }
    }

    /**
     * Get all current field names from builder configuration
     */
    private function get_current_field_names()
    {
        $sections = get_option($this->storage_key, []);
        $field_names = [];

        if (!is_array($sections)) {
            return $field_names;
        }

        foreach ($sections as $section) {
            if (empty($section['fields']) || !is_array($section['fields'])) {
                continue;
            }

            foreach ($section['fields'] as $field) {
                if (!empty($field['name'])) {
                    $field_names[] = $field['name'];
                } elseif (!empty($field['id'])) {
                    $field_names[] = $field['id'];
                }
            }
        }

        // Add system fields that should never be deleted
        $system_fields = [
            '_hvnly_field_map',
            '_hvnly_orphan_candidates',
            '_hvnly_property_featured',
            '_hvnly_unique_property_id',
            '_hvnly_property_views',
            '_thumbnail_id',
            '_wp_attached_file',
            '_wp_attachment_metadata',
            '_edit_lock',
            '_edit_last'
        ];

        return array_unique(array_merge($field_names, $system_fields));
    }

    /**
     * Check if key is a system key (should not be deleted)
     */
    private function is_system_key($key)
    {
        $system_patterns = [
            '_wp_',
            '_edit_',
            '_encloseme',
            '_pingme',
            '_oembed_',
            '_thumbnail_id'
        ];

        foreach ($system_patterns as $pattern) {
            if (strpos($key, $pattern) === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if key is not a system key (for array filtering)
     */
    private function is_not_system_key($key)
    {
        return !$this->is_system_key($key);
    }
}