<?php
/**
 * Group field identity — per-instance isolation for video, gallery, map, property_docs.
 *
 * Each group instance is identified by a unique pair:
 *   group_id      — logical group (grp_{type}_{short})
 *   group_base_id — meta key prefix ({type}_{timestamp}_…)
 *
 * Stored values live at: {group_base_id}_{metaKey}
 *
 * @package Havenlytics
 * @since   3.0.0
 */

namespace HvnlyNab\Core;

defined( 'ABSPATH' ) || exit;

class GroupFieldIdentity {

    const FIELD_MAP_META = '_hvnly_field_map';

    /**
     * Generate a unique group ID.
     */
    public static function generate_group_id( string $group_type ): string {
        $short_id = substr( uniqid(), -8 );
        return 'grp_' . sanitize_key( $group_type ) . '_' . $short_id;
    }

    /**
     * Generate a unique group base ID.
     */
    public static function generate_base_id( string $group_type ): string {
        if ( class_exists( UnifiedFieldGenerator::class ) ) {
            return UnifiedFieldGenerator::get_instance()->generate_unique_base_id( $group_type );
        }

        $microtime    = microtime( true );
        $timestamp    = (int) $microtime;
        $micro_suffix = substr( str_replace( '.', '', (string) $microtime ), -6 );
        $random       = wp_rand( 10000, 99999 );
        $unique_id    = uniqid();

        return sanitize_key( $group_type ) . "_{$timestamp}_{$micro_suffix}_{$unique_id}_{$random}";
    }

    /**
     * Resolve metaKey suffix from a field row.
     */
    public static function resolve_meta_key( array $field ): string {
        if ( ! empty( $field['metaKey'] ) ) {
            return (string) $field['metaKey'];
        }

        $name = $field['name'] ?? $field['id'] ?? '';
        if ( preg_match( '/_(title|url|thumbnail|images|address|latitude|longitude|preview|icon|label|documents|faqs|items|features)$/', $name, $m ) ) {
            return $m[1];
        }

        $parts = explode( '_', $name );
        return (string) end( $parts );
    }

    /**
     * Apply a base ID to a single field row (updates name/id/map links).
     */
    public static function apply_base_to_field( array &$field, string $base_id ): void {
        $meta_key = self::resolve_meta_key( $field );
        if ( $meta_key !== '' ) {
            $field['metaKey'] = $meta_key;
            $field['id']      = $base_id . '_' . $meta_key;
            $field['name']    = $field['id'];
        }
        $field['group_base_id'] = $base_id;

        if ( ( $field['group_type'] ?? '' ) === 'map' ) {
            $field['address_field_name'] = $base_id . '_address';
            $field['lat_field_name']     = $base_id . '_latitude';
            $field['lng_field_name']     = $base_id . '_longitude';
            $field['map_field_name']     = $base_id . '_preview';
        }
    }

    /**
     * Ensure every group_id owns a unique group_base_id across all sections.
     *
     * When two group instances share the same base (e.g. master ID collision),
     * the later group gets a fresh base and all its field names are rewritten.
     *
     * @param array $sections Builder sections (id => section or numeric list).
     * @return array Fixed sections.
     */
    public static function deduplicate_sections( array $sections ): array {
        $seen_bases  = array();
        $seen_groups = array();

        foreach ( $sections as $section_key => &$section ) {
            if ( empty( $section['fields'] ) || ! is_array( $section['fields'] ) ) {
                continue;
            }

            foreach ( $section['fields'] as $field_index => &$field ) {
                $group_id   = $field['group_id'] ?? '';
                $group_type = $field['group_type'] ?? '';
                $base_id    = $field['group_base_id'] ?? '';

                if ( $group_id === '' || $group_type === '' ) {
                    continue;
                }

                if ( ! isset( $seen_groups[ $group_id ] ) ) {
                    $seen_groups[ $group_id ] = $base_id;
                } elseif ( $base_id !== '' && $seen_groups[ $group_id ] !== $base_id ) {
                    $base_id = $seen_groups[ $group_id ];
                }

                if ( $base_id === '' ) {
                    $base_id = self::generate_base_id( $group_type );
                    $seen_groups[ $group_id ] = $base_id;
                }

                if ( isset( $seen_bases[ $base_id ] ) && $seen_bases[ $base_id ] !== $group_id ) {
                    $base_id = self::generate_base_id( $group_type );
                    $seen_groups[ $group_id ] = $base_id;
                }

                $seen_bases[ $base_id ] = $group_id;

                if ( ( $field['group_base_id'] ?? '' ) !== $base_id ) {
                    self::apply_base_to_field( $field, $base_id );
                    $section['fields'][ $field_index ] = $field;
                }
            }
            unset( $field );

            // Second pass: sync all fields in each group to the canonical base.
            $group_bases = array();
            foreach ( $section['fields'] as $field ) {
                $gid = $field['group_id'] ?? '';
                if ( $gid !== '' && ! empty( $field['group_base_id'] ) ) {
                    $group_bases[ $gid ] = $field['group_base_id'];
                }
            }
            foreach ( $section['fields'] as $field_index => &$field ) {
                $gid = $field['group_id'] ?? '';
                if ( $gid === '' || empty( $group_bases[ $gid ] ) ) {
                    continue;
                }
                $canonical = $group_bases[ $gid ];
                if ( ( $field['group_base_id'] ?? '' ) !== $canonical ) {
                    self::apply_base_to_field( $field, $canonical );
                    $section['fields'][ $field_index ] = $field;
                }
            }
            unset( $field );
        }
        unset( $section );

        return $sections;
    }

    /**
     * Read normalized field map from post meta.
     *
     * Supports legacy flat format { "video": "video_…" } and new format:
     * { "groups": { "grp_video_abc": "video_…" }, "legacy": { "video": "video_…" } }
     */
    public static function get_field_map( int $post_id ): array {
        $raw = get_post_meta( $post_id, self::FIELD_MAP_META, true );
        if ( empty( $raw ) ) {
            return array( 'groups' => array(), 'legacy' => array() );
        }

        $decoded = json_decode( $raw, true );
        if ( ! is_array( $decoded ) ) {
            return array( 'groups' => array(), 'legacy' => array() );
        }

        if ( isset( $decoded['groups'] ) || isset( $decoded['legacy'] ) ) {
            $decoded['groups'] = is_array( $decoded['groups'] ?? null ) ? $decoded['groups'] : array();
            $decoded['legacy'] = is_array( $decoded['legacy'] ?? null ) ? $decoded['legacy'] : array();
            return $decoded;
        }

        // Flat legacy: { video: base, gallery: base, … }
        return array(
            'groups' => array(),
            'legacy' => $decoded,
        );
    }

    /**
     * Resolve where this group instance's data is stored (meta key prefix).
     *
     * Priority: group_id map entry → group_base_id → legacy type map.
     */
    public static function resolve_storage_base( int $post_id, array $field ): ?string {
        $group_id      = $field['group_id'] ?? '';
        $group_base_id = $field['group_base_id'] ?? '';
        $group_type    = $field['group_type'] ?? '';
        $map           = self::get_field_map( $post_id );

        if ( $group_id !== '' && ! empty( $map['groups'][ $group_id ] ) ) {
            return (string) $map['groups'][ $group_id ];
        }

        if ( $group_base_id !== '' ) {
            return $group_base_id;
        }

        $legacy = $map['legacy'][ $group_type ] ?? null;
        return $legacy ? (string) $legacy : null;
    }

    /**
     * Whether this field has a unique group instance identity (no cross-section/type reads).
     */
    public static function is_strictly_scoped_field( array $field ): bool {
        if ( empty( $field['group_id'] ) || empty( $field['group_base_id'] ) ) {
            return false;
        }

        $name = $field['name'] ?? $field['id'] ?? '';

        return $name !== '';
    }

    /**
     * Debug log for group field identity (WP_DEBUG only).
     */
    public static function log_group_identity( array $field, string $context = '' ): void {
        if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
            return;
        }

        $prefix = '[HVNLY GROUP]';
        if ( $context !== '' ) {
            $prefix .= ' ' . $context;
        }

        error_log(
            $prefix .
            ' section=' . ( $field['section_id'] ?? '' ) .
            ' group=' . ( $field['group_id'] ?? '' ) .
            ' base=' . ( $field['group_base_id'] ?? '' ) .
            ' field=' . ( $field['name'] ?? $field['id'] ?? '' )
        );
    }

    /**
     * @deprecated Use log_group_identity().
     */
    public static function log_section_identity( array $field, string $context = '' ): void {
        self::log_group_identity( $field, $context );
    }

    /**
     * Stamp section_id and rebuild group_base_id from field name when missing.
     */
    public static function hydrate_field_identity( array $field, string $section_id = '' ): array {
        if ( $section_id !== '' && empty( $field['section_id'] ) ) {
            $field['section_id'] = $section_id;
        }

        if ( empty( $field['group_base_id'] ) ) {
            $name     = (string) ( $field['name'] ?? $field['id'] ?? '' );
            $meta_key = (string) ( $field['metaKey'] ?? '' );

            if ( $name !== '' && $meta_key !== '' ) {
                $suffix = '_' . $meta_key;
                if ( substr( $name, -strlen( $suffix ) ) === $suffix ) {
                    $field['group_base_id'] = substr( $name, 0, -strlen( $suffix ) );
                }
            }

            if ( empty( $field['group_base_id'] ) && $name !== '' ) {
                $suffixes = array( '_documents', '_agents', '_faqs', '_items', '_images', '_url', '_title', '_thumbnail', '_address', '_latitude', '_longitude', '_preview' );
                foreach ( $suffixes as $suffix ) {
                    $len = strlen( $suffix );
                    if ( substr( $name, -$len ) === $suffix ) {
                        $field['group_base_id'] = substr( $name, 0, -$len );
                        break;
                    }
                }
            }
        }

        return $field;
    }

    /**
     * Resolve a scoped meta value (never crosses group instances when strictly scoped).
     *
     * @param int    $post_id     Post ID.
     * @param array  $field       Field config.
     * @param string $primary_key Meta key override.
     * @param string $meta_key    metaKey suffix override.
     * @return mixed
     */
    public static function resolve_value( int $post_id, array $field, string $primary_key = '', string $meta_key = '' ) {
        $field = self::hydrate_field_identity( $field );

        if ( $meta_key !== '' ) {
            $field['metaKey'] = $meta_key;
        }

        $primary = $primary_key !== '' ? $primary_key : (string) ( $field['name'] ?? $field['id'] ?? '' );

        if ( class_exists( '\HvnlyNab\Core\DataPreservation\MetaResolver' ) ) {
            return \HvnlyNab\Core\DataPreservation\MetaResolver::get_field_value( (int) $post_id, $field, $primary );
        }

        return $primary !== '' ? get_post_meta( $post_id, $primary, true ) : '';
    }

    /**
     * Whether this field instance owns the legacy type-level import base.
     */
    public static function owns_legacy_type_import( int $post_id, array $field, string $group_type ): bool {
        if ( ! self::is_strictly_scoped_field( $field ) ) {
            return true;
        }

        $map         = self::get_field_map( $post_id );
        $legacy_base = (string) ( $map['legacy'][ $group_type ] ?? '' );

        if ( $legacy_base === '' ) {
            return false;
        }

        $storage = self::resolve_storage_base( $post_id, $field );

        return $storage !== null && $storage === $legacy_base;
    }

    /**
     * Whether this field is an isolated group instance (unique base ID in builder).
     */
    public static function is_isolated_group_field( array $field ): bool {
        $group_type    = $field['group_type'] ?? '';
        $group_base_id = $field['group_base_id'] ?? '';

        if ( $group_base_id === '' ) {
            return false;
        }

        return in_array( $group_type, array( 'video', 'gallery', 'map', 'property_docs', 'faq', 'repeater', 'agents', 'features' ), true );
    }

    /**
     * Resolve a legacy import base for a field (never crosses group instances).
     */
    public static function resolve_legacy_base( int $post_id, array $field ): ?string {
        $group_type    = $field['group_type'] ?? '';
        $group_id      = $field['group_id'] ?? '';
        $group_base_id = $field['group_base_id'] ?? '';
        $map           = self::get_field_map( $post_id );

        if ( $group_id !== '' && ! empty( $map['groups'][ $group_id ] ) ) {
            return (string) $map['groups'][ $group_id ];
        }

        $legacy = $map['legacy'][ $group_type ] ?? null;

        if ( $legacy && $group_base_id && $legacy !== $group_base_id ) {
            return null;
        }

        return $legacy ? (string) $legacy : null;
    }

    /**
     * Record a group's base ID in post meta (group_id keyed; legacy type map for first import only).
     */
    public static function record_group_in_field_map(
        int $post_id,
        string $group_id,
        string $group_base_id,
        string $group_type = ''
    ): void {
        if ( $group_id === '' || $group_base_id === '' ) {
            return;
        }

        $map = self::get_field_map( $post_id );
        $map['groups'][ $group_id ] = $group_base_id;

        if ( $group_type !== '' && empty( $map['legacy'][ $group_type ] ) ) {
            $map['legacy'][ $group_type ] = $group_base_id;
        }

        update_post_meta( $post_id, self::FIELD_MAP_META, wp_json_encode( $map ) );
    }
}
