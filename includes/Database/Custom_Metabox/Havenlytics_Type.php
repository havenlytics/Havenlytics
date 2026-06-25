<?php
/**
 * Havenlytics Property Sections Metabox - Enhanced with Property Builder Integration
 *
 * @package HvnlyNab\Database\Custom_Metabox
 * @since 2.0.0
 */

namespace HvnlyNab\Database\Custom_Metabox;

use HvnlyNab\Database\Base\Custom_Metabox;
use HvnlyNab\Database\Database;
use HvnlyNab\Admin\Data\TabData;
use HvnlyNab\Api\Type\Builders\DnDSections;

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Havenlytics_Type
 *
 * Handles custom metabox for property sections with full property builder integration.
 */
class Havenlytics_Type extends Custom_Metabox {

    /**
     * Storage key for sections data.
     *
     * @var string
     */
    private $storage_key = 'hvnly_property_builder.sections';

    /**
     * Field registry instance
     *
     * @var object
     */
    private $field_registry;

    /**
     * Current post ID
     *
     * @var int
     */
    private $post_id;

    /**
     * Master base IDs cache
     *
     * @var array
     */
    private $master_base_ids = [];

    /**
     * Cache for existing meta keys for current post
     *
     * @var array
     */
    private $existing_meta_keys_cache = [];

    /**
     * MAPPING CACHE: Stores the actual stored base ID for each group type
     *
     * @var array
     */
    private $meta_key_mappings = [];

    /**
     * PER-RENDER FALLBACK GATE.
     *
     * Tracks the FIRST group of each type that has data during a page render.
     * Two protections are applied:
     *
     * 1. LEGACY FALLBACK GATE: only the first group may fall through to the
     *    _hvnly_field_map / scan steps.  All subsequent same-type groups that
     *    have no direct data render empty.
     *
     * 2. DUPLICATE-VALUE GATE: if a later group finds direct data at step 1
     *    but that value is IDENTICAL to what the first group returned, the value
     *    is suppressed.  This cleans up data that was incorrectly persisted to
     *    every group of a type during an earlier bug where the gate was not
     *    being claimed on step 1 (the "leakage-to-direct-keys" pattern).
     *    A group with a DIFFERENT value (user intentionally set it) is allowed.
     *
     * Structure: [ group_type => [ 'base' => group_base_id, 'value' => value ] ]
     *
     * @var array
     */
    private $type_fallback_claimed = [];

    /**
     * Constructor.
     */
    public function __construct() {
        parent::__construct();
        $this->field_registry = Database::get_field_registry();
        
        // Load master base IDs
        $this->load_master_base_ids();
        
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
        add_action( 'add_meta_boxes', array( $this, 'hvnly_register_dynamic_tabs_metabox' ) );
        add_action( 'add_meta_boxes', array( $this, 'hvnly_register_featured_property_metabox' ) );
        add_action( 'save_post', array( $this, 'hvnly_save_dynamic_tabs_data' ) );
        add_action( 'save_post', array( $this, 'hvnly_save_featured_property_data' ) );
        
        // Add admin notice for validation errors
        add_action( 'admin_notices', array( $this, 'display_validation_errors' ) );
    }
    
    /**
     * Load master base IDs from unified generator
     */
    private function load_master_base_ids() {
        if (class_exists('\HvnlyNab\Core\UnifiedFieldGenerator')) {
            $unified_generator = \HvnlyNab\Core\UnifiedFieldGenerator::get_instance();
            $this->master_base_ids = $unified_generator->get_or_create_master_base_ids();
        } else {
            $this->master_base_ids = get_option('hvnly_master_base_ids', []);
        }
    }
    
    /**
     * Get existing meta keys for a post (cached)
     */
    private function get_existing_meta_keys($post_id) {
        if (!isset($this->existing_meta_keys_cache[$post_id])) {
            $all_meta = get_post_meta($post_id);
            $this->existing_meta_keys_cache[$post_id] = array_keys($all_meta);
        }
        return $this->existing_meta_keys_cache[$post_id];
    }

    /**
     * Find the actual stored base ID for a group type.
     *
     * Look-up order (fastest to slowest):
     *   1. In-request PHP cache ($meta_key_mappings).
     *   2. Stored _hvnly_field_map post-meta (O(1) DB read – written by importer
     *      and by this method itself after a successful scan).
     *   3. Full post-meta key scan (O(n) – legacy / pre-2.3.2 properties).
     *      On a successful scan the result is persisted to _hvnly_field_map so
     *      future requests use path 2 instead.
     */
    private function find_stored_base_id( $post_id, $group_type, $meta_key ) {
        $cache_key = $post_id . '_' . $group_type . '_' . $meta_key;

        // 1. In-request cache.
        if ( isset( $this->meta_key_mappings[ $cache_key ] ) ) {
            return $this->meta_key_mappings[ $cache_key ];
        }

        // 2. Persisted field map (written by importer / previous scan).
        if ( class_exists( '\HvnlyNab\Core\GroupFieldIdentity' ) ) {
            $field_map = \HvnlyNab\Core\GroupFieldIdentity::get_field_map( $post_id );

            if ( ! empty( $field_map['legacy'][ $group_type ] ) ) {
                $stored_base = $field_map['legacy'][ $group_type ];
                $this->meta_key_mappings[ $cache_key ] = $stored_base;
                return $stored_base;
            }
        } else {
            $field_map_raw = get_post_meta( $post_id, '_hvnly_field_map', true );
            if ( !empty( $field_map_raw ) ) {
                $field_map = json_decode( $field_map_raw, true );
                if ( is_array( $field_map ) && !empty( $field_map[ $group_type ] ) ) {
                    $stored_base = $field_map[ $group_type ];
                    $this->meta_key_mappings[ $cache_key ] = $stored_base;
                    return $stored_base;
                }
            }
        }

        // 3. Full scan (legacy properties that pre-date _hvnly_field_map).
        $meta_keys = $this->get_existing_meta_keys( $post_id );
        $suffix    = '_' . $meta_key;

        foreach ( $meta_keys as $key ) {
            if ( substr( $key, -strlen( $suffix ) ) === $suffix && strpos( $key, $group_type ) === 0 ) {
                $stored_base = substr( $key, 0, -strlen( $suffix ) );

                // Persist the discovery so future requests use path 2.
                if ( class_exists( '\HvnlyNab\Core\GroupFieldIdentity' ) ) {
                    $existing_map = \HvnlyNab\Core\GroupFieldIdentity::get_field_map( $post_id );
                    if ( empty( $existing_map['legacy'][ $group_type ] ) ) {
                        $existing_map['legacy'][ $group_type ] = $stored_base;
                        update_post_meta( $post_id, '_hvnly_field_map', wp_json_encode( $existing_map ) );
                    }
                } else {
                    $field_map_raw = get_post_meta( $post_id, '_hvnly_field_map', true );
                    $existing_map  = ! empty( $field_map_raw ) ? json_decode( $field_map_raw, true ) : [];
                    if ( ! is_array( $existing_map ) ) {
                        $existing_map = [];
                    }
                    if ( empty( $existing_map[ $group_type ] ) ) {
                        $existing_map[ $group_type ] = $stored_base;
                        update_post_meta( $post_id, '_hvnly_field_map', wp_json_encode( $existing_map ) );
                    }
                }

                $this->meta_key_mappings[ $cache_key ] = $stored_base;
                return $stored_base;
            }
        }

        return null;
    }

    /**
     * Get the actual stored value for a field.
     *
     * Resolution order (stops at first non-empty result):
     *
     *  1. Direct lookup using the builder-config field name
     *     = field['name'] = "{group_base_id}_{metaKey}"
     *     This is the canonical path for all NEW properties and any property
     *     that has already been saved via the metabox at least once.
     *
     *  2. Legacy lookup via _hvnly_field_map (written by the importer and
     *     backfilled by the 2.3.2 migration).  Used for properties that were
     *     imported with a different base ID than the one in the builder config.
     *
     *  3. Full meta-key scan — absolute last resort for properties that
     *     pre-date both _hvnly_field_map and the current config.
     *
     * Each group field is resolved using ITS OWN group_base_id so two groups
     * of the same type never read each other's data.
     */
    private function get_field_value( $post_id, $field ) {
        if ( class_exists( '\HvnlyNab\Core\DataPreservation\MetaResolver' ) ) {
            $resolved = \HvnlyNab\Core\DataPreservation\MetaResolver::get_field_value(
                (int) $post_id,
                $field,
                (string) ( $field['name'] ?? $field['id'] ?? '' )
            );
            if ( $resolved !== '' && $resolved !== false && $resolved !== null ) {
                return $resolved;
            }
        }

        $group_type      = $field['group_type']      ?? '';
        $meta_key        = $field['metaKey']         ?? '';
        $configured_name = $field['name']            ?? $field['id'] ?? '';
        $group_base_id   = $field['group_base_id']   ?? '';

        // ── Non-group fields: one direct lookup, done. ────────────────────────
        if ( empty( $group_type ) || empty( $meta_key ) ) {
            return get_post_meta( $post_id, $configured_name, true );
        }

        // ── Step 1: builder-config field name (= {group_base_id}_{metaKey}) ──
        if ( !empty( $configured_name ) ) {
            $value = get_post_meta( $post_id, $configured_name, true );
            if ( $value !== '' && $value !== false && $value !== null ) {
                if ( !isset( $this->type_fallback_claimed[ $group_type ] ) ) {
                    // First group with direct data — claim the gate and record the value.
                    $this->type_fallback_claimed[ $group_type ] = [
                        'base'     => $group_base_id,
                        'value'    => $value,
                        'group_id' => $field['group_id'] ?? '',
                    ];
                } else {
                    // Gate already claimed by a different base.
                    $claimed = $this->type_fallback_claimed[ $group_type ];
                    if ( $claimed['base'] !== $group_base_id && $claimed['value'] === $value ) {
                        // Duplicate value from a different base — almost certainly the
                        // result of the pre-fix "leakage-to-direct-keys" bug where the
                        // canonical URL/images were written to every group of the same type.
                        // Suppress so the group renders empty; the user can type a new
                        // value to permanently override it.
                        return '';
                    }
                    // Different value — intentionally set by the user; allow it through.
                }
                return $value;
            }
        }

        // Isolated group instances: direct meta only (+ group_id-specific storage alias).
        // Never run type-level legacy or full meta-key scan — that caused cross-section leakage.
        if ( class_exists( '\HvnlyNab\Core\GroupFieldIdentity' )
            && \HvnlyNab\Core\GroupFieldIdentity::is_isolated_group_field( $field ) ) {

            $storage_base = \HvnlyNab\Core\GroupFieldIdentity::resolve_storage_base( $post_id, $field );
            if ( $storage_base && $storage_base !== $group_base_id && ! empty( $meta_key ) ) {
                $alt_key = $storage_base . '_' . $meta_key;
                if ( $alt_key !== $configured_name ) {
                    $value = get_post_meta( $post_id, $alt_key, true );
                    if ( $value !== '' && $value !== false && $value !== null ) {
                        return $value;
                    }
                }
            }

            return '';
        }

        // ── Steps 2 & 3: legacy fallback — FIRST-CLAIMED gate ───────────────
        //
        // These steps search for data stored under a DIFFERENT base ID (e.g.
        // data written by the importer before stable master IDs were set up).
        //
        // CRITICAL RULE: only the FIRST group field of each group_type that
        // fails a direct lookup may use the fallback during a single page
        // render.  All subsequent same-type group fields that also lack direct
        // data render EMPTY.
        //
        // This prevents newly created sections (e.g. a second "Map" added by
        // the admin) from inheriting an imported property's address/gallery/
        // video data.  The "first-claimed" gate is stored in
        // $this->type_fallback_claimed which is reset in
        // hvnly_render_dynamic_tabs_metabox() for every new post render.

        if ( isset( $this->type_fallback_claimed[ $group_type ] ) ) {
            // Another group of this type already claimed the legacy slot.
            // This group should render empty — it is a new, independent instance.
            return '';
        }

        // ── Step 2: _hvnly_field_map lookup (group_id first, then legacy type) ─
        $legacy_base = null;
        if ( class_exists( '\HvnlyNab\Core\GroupFieldIdentity' ) ) {
            $legacy_base = \HvnlyNab\Core\GroupFieldIdentity::resolve_legacy_base( $post_id, $field );
        } else {
            $field_map_raw = get_post_meta( $post_id, '_hvnly_field_map', true );
            if ( ! empty( $field_map_raw ) ) {
                $field_map = json_decode( $field_map_raw, true );
                if ( is_array( $field_map ) && ! empty( $field_map[ $group_type ] ) ) {
                    $legacy_base = $field_map[ $group_type ];
                }
            }
        }

        if ( ! empty( $legacy_base ) ) {
            $legacy_key = $legacy_base . '_' . $meta_key;
            if ( $legacy_key !== $configured_name ) {
                $value = get_post_meta( $post_id, $legacy_key, true );
                if ( $value !== '' && $value !== false && $value !== null ) {
                    $this->type_fallback_claimed[ $group_type ] = [
                        'base'     => $legacy_base,
                        'value'    => $value,
                        'group_id' => $field['group_id'] ?? '',
                    ];
                    return $value;
                }
            }
        }

        // ── Step 3: full scan (pre-2.3.2 properties without _hvnly_field_map) ─
        $suffix    = '_' . $meta_key;
        $meta_keys = $this->get_existing_meta_keys( $post_id );
        foreach ( $meta_keys as $key ) {
            if ( substr( $key, -strlen( $suffix ) ) === $suffix && strpos( $key, $group_type ) === 0 ) {
                if ( $key === $configured_name ) continue;
                $value = get_post_meta( $post_id, $key, true );
                if ( $value !== '' && $value !== false && $value !== null ) {
                    $legacy_base = substr( $key, 0, -strlen( $suffix ) );
                    $this->type_fallback_claimed[ $group_type ] = [
                        'base'     => $legacy_base,
                        'value'    => $value,
                        'group_id' => $field['group_id'] ?? '',
                    ];
                    return $value;
                }
            }
        }

        return '';
    }

    /**
     * Register the dynamic tabs metabox.
     */
    public function hvnly_register_dynamic_tabs_metabox() {
        add_meta_box(
            'hvnly_dynamic_metabox_tabs__main__wrapper',
            __( 'Havenlytics Property Sections', 'havenlytics' ),
            array( $this, 'hvnly_render_dynamic_tabs_metabox' ),
            'hvnly_property',
            'normal',
            'low'
        );
    }

    /**
     * Register featured property metabox.
     */
    public function hvnly_register_featured_property_metabox() {
        add_meta_box(
            'hvnly_featured_property_metabox',
            __( 'Featured Property', 'havenlytics' ),
            array( $this, 'hvnly_render_featured_property_metabox' ),
            'hvnly_property',
            'side',
            'high'
        );
    }

    /**
     * Render the featured property metabox.
     */
    public function hvnly_render_featured_property_metabox( $post ) {
        wp_nonce_field( 'hvnly_featured_property_save', 'hvnly_featured_property_nonce' );

        $is_featured = get_post_meta( $post->ID, '_hvnly_property_featured', true );
        ?>
<div class="hvnly-featured-toggle-container">
    <label class="hvnly-toggle-label">
        <input type="checkbox" name="_hvnly_property_featured" value="1" <?php checked( $is_featured, '1' ); ?> />
        <span class="hvnly-toggle-slider"></span>
        <span class="hvnly-toggle-text"><?php esc_html_e( 'Featured Property', 'havenlytics' ); ?></span>
    </label>
</div>
<?php
    }

    /**
     * Save featured property data.
     */
    public function hvnly_save_featured_property_data( $post_id ) {
        $nonce = filter_input( INPUT_POST, 'hvnly_featured_property_nonce', FILTER_UNSAFE_RAW );
        $nonce = is_string( $nonce ) ? sanitize_text_field( $nonce ) : '';
        
        if ( ! $nonce || ! wp_verify_nonce( $nonce, 'hvnly_featured_property_save' ) ) {
            return;
        }

        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }

        $is_featured = filter_input( INPUT_POST, '_hvnly_property_featured', FILTER_VALIDATE_BOOLEAN ) ? '1' : '0';
        update_post_meta( $post_id, '_hvnly_property_featured', $is_featured );
    }

    /**
     * Render the dynamic tabs metabox.
     */
    public function hvnly_render_dynamic_tabs_metabox( $post ) {
        wp_nonce_field( 'hvnly_dynamic_metabox_tabs_save', 'hvnly_dynamic_metabox_tabs_nonce' );
        $this->post_id = $post->ID;
        
        // Reset per-render caches for this post.
        $this->meta_key_mappings     = [];
        $this->type_fallback_claimed = [];
        
        $tabs = $this->get_tabs_data();
        
        echo $this->generate_dynamic_tabs_html( $tabs ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renders trusted admin metabox HTML from internal generator.
    }

    /**
     * Display validation errors from transient as admin notice
     */
    public function display_validation_errors() {
        $screen = get_current_screen();
        if ( ! $screen || $screen->post_type !== 'hvnly_property' ) {
            return;
        }
        
        $post_id = get_the_ID();
        if ( ! $post_id ) {
            return;
        }
        
        $errors = get_transient( 'hvnly_validation_errors_' . $post_id );
        if ( ! empty( $errors ) && is_array( $errors ) ) {
            delete_transient( 'hvnly_validation_errors_' . $post_id );
            ?>
<div class="notice notice-error is-dismissible">
    <p><strong><?php esc_html_e( 'Validation Error:', 'havenlytics' ); ?></strong></p>
    <ul>
        <?php foreach ( $errors as $error ) : ?>
        <li><?php echo esc_html( $error ); ?></li>
        <?php endforeach; ?>
    </ul>
    <p><?php esc_html_e( 'Please fill in all required fields before saving.', 'havenlytics' ); ?></p>
</div>
<?php
        }
    }

    /**
     * Get tabs data from storage with fallback to default
     */
    private function get_tabs_data() {
        $stored_tabs = get_option( $this->storage_key, [] );
        
        if ( empty( $stored_tabs ) || ! is_array( $stored_tabs ) ) {
            return $this->get_default_tabs();
        }
        
        return $this->normalize_tabs_data( $stored_tabs );
    }

    /**
     * Normalize and validate tabs data from the property builder
     */
    private function normalize_tabs_data( $stored_tabs ) {
        $tabs = [];
        
        foreach ( $stored_tabs as $tab_id => $tab ) {
            if ( ! is_array( $tab ) ) {
                continue;
            }

            $tab_fields = $tab['fields'] ?? [];
            if ( DnDSections::is_basic_info_tab( $tab ) ) {
                $tab_fields = DnDSections::ensure_basic_info_fields( $tab_fields );
            }
            
            $tabs[] = [
                'id'        => $tab['id'] ?? $tab_id,
                'title'     => $tab['title'] ?? 'Untitled Section',
                'icon'      => $this->normalize_icon( $tab['icon'] ?? 'fas fa-cog' ),
                'fields'    => $this->process_group_fields( $tab_fields, (string) ( $tab['id'] ?? $tab_id ) ),
                'order'     => $tab['order'] ?? 0,
                'collapsed' => $tab['collapsed'] ?? false,
                'required'  => DnDSections::is_basic_info_tab( $tab ) ? true : ( $tab['required'] ?? false ),
            ];
        }
        
        // Sort by order
        usort( $tabs, function( $a, $b ) {
            return $a['order'] <=> $b['order'];
        } );
        
        return $tabs;
    }

    /**
     * Normalize icon format for consistency
     */
    private function normalize_icon( $icon ) {
        if ( empty( $icon ) ) {
            return 'fas fa-cog';
        }
        
        if ( strpos( $icon, 'fas ' ) === 0 || strpos( $icon, 'far ' ) === 0 || strpos( $icon, 'fab ' ) === 0 ) {
            return $icon;
        }
        
        if ( strpos( $icon, 'fa-' ) === 0 ) {
            return 'fas ' . $icon;
        }
        
        if ( strpos( $icon, 'fa-' ) === false && strpos( $icon, ' ' ) === false ) {
            return 'fas fa-' . $icon;
        }
        
        return 'fas fa-cog';
    }

    /**
     * Process group fields - RENDER ONLY ONE FIELD PER GROUP
     * Also converts property_docs from 3 separate fields to 1 field
     *
     * @param array  $fields     Section field definitions.
     * @param string $section_id Parent section ID for instance isolation.
     */
    private function process_group_fields( $fields, $section_id = '' ) {
        if ( empty( $fields ) || ! is_array( $fields ) ) {
            return array();
        }

        usort(
            $fields,
            function ( $a, $b ) {
                return (int) ( $a['order'] ?? 0 ) - (int) ( $b['order'] ?? 0 );
            }
        );

        $processed_fields = array();
        $processed_groups = array();

        foreach ( $fields as $field ) {
            if ( ! is_array( $field ) ) {
                continue;
            }

            $group_id   = $field['group_id'] ?? '';
            $group_type = $field['group_type'] ?? '';
            $field_type = $field['type'] ?? '';
            $meta_key   = $field['metaKey'] ?? '';

            if ( ! empty( $group_id ) && ! empty( $group_type ) ) {
                if ( isset( $processed_groups[ $group_id ] ) ) {
                    continue;
                }

                if ( $group_type === 'property_docs' ) {
                    $processed_groups[ $group_id ] = true;

                    $group_base_id = $field['group_base_id'] ?? '';
                    if ( empty( $group_base_id ) ) {
                        $microtime     = microtime( true );
                        $timestamp     = (int) $microtime;
                        $micro_suffix  = substr( str_replace( '.', '', (string) $microtime ), -6 );
                        $random        = wp_rand( 1000, 9999 );
                        $unique_suffix = substr( uniqid(), -8 );
                        $group_base_id = "property_docs_{$timestamp}_{$micro_suffix}_{$unique_suffix}_{$random}";
                    }

                    $documents_field_name = $group_base_id . '_documents';
                    $docs_required        = $this->resolve_group_required_flag( $fields, $group_id )
                        || ! empty( $field['required'] )
                        || ! empty( $field['is_required'] );

                    $processed_fields[] = $this->attach_section_id(
                        array(
                        'id'              => $documents_field_name,
                        'fieldid'         => $documents_field_name,
                        'name'            => $documents_field_name,
                        'type'            => 'property_docs',
                        'label'           => $field['label'] ?? 'Property Documents',
                        'placeholder'     => '',
                        'is_required'     => $docs_required,
                        'admin_only'      => false,
                        'enabled'         => true,
                        'order'           => $field['order'] ?? count( $processed_fields ),
                        'group_id'        => $group_id,
                        'group_type'      => 'property_docs',
                        'group_name'      => $field['group_name'] ?? 'Property Documents',
                        'group_base_id'   => $group_base_id,
                        'show_in_sidebar' => $field['show_in_sidebar'] ?? true,
                        ),
                        $section_id
                    );
                    continue;
                }

                if ( $group_type === 'faq' ) {
                    $processed_groups[ $group_id ] = true;

                    $group_base_id = $field['group_base_id'] ?? '';
                    if ( empty( $group_base_id ) ) {
                        $microtime     = microtime( true );
                        $timestamp     = (int) $microtime;
                        $micro_suffix  = substr( str_replace( '.', '', (string) $microtime ), -6 );
                        $random        = wp_rand( 1000, 9999 );
                        $unique_suffix = substr( uniqid(), -8 );
                        $group_base_id = "faq_{$timestamp}_{$micro_suffix}_{$unique_suffix}_{$random}";
                    }

                    $faq_field_name = $group_base_id . '_faqs';
                    $faq_required   = $this->resolve_group_required_flag( $fields, $group_id )
                        || ! empty( $field['required'] )
                        || ! empty( $field['is_required'] );

                    $processed_fields[] = $this->attach_section_id(
                        array(
                        'id'            => $faq_field_name,
                        'fieldid'       => $faq_field_name,
                        'name'          => $faq_field_name,
                        'type'          => 'faq',
                        'label'         => $field['group_name'] ?? $field['label'] ?? __( 'FAQ', 'havenlytics' ),
                        'placeholder'   => '',
                        'is_required'   => $faq_required,
                        'admin_only'    => false,
                        'enabled'       => true,
                        'order'         => $field['order'] ?? count( $processed_fields ),
                        'group_id'      => $group_id,
                        'group_type'    => 'faq',
                        'group_name'    => $field['group_name'] ?? __( 'FAQ', 'havenlytics' ),
                        'group_base_id' => $group_base_id,
                        ),
                        $section_id
                    );
                    continue;
                }

                if ( $group_type === 'repeater' ) {
                    $processed_groups[ $group_id ] = true;

                    $group_base_id = $field['group_base_id'] ?? '';
                    if ( empty( $group_base_id ) ) {
                        $microtime     = microtime( true );
                        $timestamp     = (int) $microtime;
                        $micro_suffix  = substr( str_replace( '.', '', (string) $microtime ), -6 );
                        $random        = wp_rand( 1000, 9999 );
                        $unique_suffix = substr( uniqid(), -8 );
                        $group_base_id = "repeater_{$timestamp}_{$micro_suffix}_{$unique_suffix}_{$random}";
                    }

                    $repeater_field_name = $group_base_id . '_items';
                    $repeater_required   = $this->resolve_group_required_flag( $fields, $group_id )
                        || ! empty( $field['required'] )
                        || ! empty( $field['is_required'] );

                    $processed_fields[] = $this->attach_section_id(
                        array(
                        'id'            => $repeater_field_name,
                        'fieldid'       => $repeater_field_name,
                        'name'          => $repeater_field_name,
                        'type'          => 'repeater',
                        'label'         => $field['group_name'] ?? $field['label'] ?? __( 'Repeater', 'havenlytics' ),
                        'placeholder'   => '',
                        'is_required'   => $repeater_required,
                        'admin_only'    => false,
                        'enabled'       => true,
                        'order'         => $field['order'] ?? count( $processed_fields ),
                        'group_id'      => $group_id,
                        'group_type'    => 'repeater',
                        'group_name'    => $field['group_name'] ?? __( 'Repeater', 'havenlytics' ),
                        'group_base_id' => $group_base_id,
                        ),
                        $section_id
                    );
                    continue;
                }

                $is_main_field = false;

                switch ( $group_type ) {
                    case 'video':
                        $is_main_field = ( $field_type === 'video' || $meta_key === 'url' );
                        break;
                    case 'gallery':
                        $is_main_field = ( $field_type === 'gallery' || $meta_key === 'images' );
                        break;
                    case 'map':
                        $is_main_field = ( $field_type === 'map' || $meta_key === 'preview' );
                        break;
                    case 'agents':
                        $is_main_field = ( $field_type === 'agents' || $meta_key === 'agents' );
                        break;
                    case 'faq':
                        $is_main_field = ( $field_type === 'faq' || $meta_key === 'faqs' );
                        break;
                    case 'repeater':
                        $is_main_field = ( $field_type === 'repeater' || $meta_key === 'items' );
                        break;
                    default:
                        $is_main_field = true;
                }

                if ( $is_main_field ) {
                    $processed_groups[ $group_id ] = true;
                    $normalized                    = $this->normalize_field_for_render( $field, $section_id );
                    if ( 'map' === $group_type ) {
                        if ( empty( $normalized['metaKey'] ) ) {
                            $normalized['metaKey'] = 'preview';
                        }
                        $normalized['is_map_preview'] = true;
                    }
                    if ( $this->resolve_group_required_flag( $fields, $group_id ) ) {
                        $normalized['is_required'] = true;
                    }
                    $processed_fields[] = $normalized;
                }
            } else {
                $processed_fields[] = $this->normalize_field_for_render( $field, $section_id );
            }
        }

        return $processed_fields;
    }

    /**
     * Stamp section_id onto a processed field row.
     *
     * @param array  $field      Field config.
     * @param string $section_id Section ID.
     * @return array
     */
    private function attach_section_id( array $field, string $section_id ): array {
        if ( $section_id !== '' ) {
            $field['section_id'] = $section_id;
        }

        return $field;
    }

    /**
     * Normalize builder field keys for metabox rendering.
     *
     * @param array  $field      Field config.
     * @param string $section_id Parent section ID.
     * @return array
     */
    private function normalize_field_for_render( $field, $section_id = '' ) {
        if ( isset( $field['required'] ) && ! isset( $field['is_required'] ) ) {
            $field['is_required'] = ! empty( $field['required'] );
        }

        if ( empty( $field['type'] ) && ! empty( $field['input_type'] ) ) {
            $field['type'] = $field['input_type'];
        }

        if ( ( $field['type'] ?? '' ) === 'map' ) {
            if ( empty( $field['metaKey'] ) ) {
                $field['metaKey'] = 'preview';
            }
            $field['is_map_preview'] = true;
        }

        if ( ( $field['type'] ?? '' ) === 'image' ) {
            $field['type'] = 'file';
            if ( empty( $field['file_type'] ) && empty( $field['fileType'] ) ) {
                $field['file_type'] = 'image';
            }
        }

        if ( empty( $field['file_type'] ) && ! empty( $field['fileType'] ) ) {
            $field['file_type'] = $field['fileType'];
        }

        if ( empty( $field['name'] ) && ! empty( $field['id'] ) ) {
            if ( strpos( $field['id'], '_hvnly_' ) === 0 ) {
                $field['name'] = $field['id'];
            } elseif ( $field['id'] === 'property_price' ) {
                $field['name'] = '_hvnly_property_price';
            }
        }

        if ( function_exists( 'hvnly_hydrate_select_field_options' ) ) {
            $field = hvnly_hydrate_select_field_options( $field );
        }

        return $this->attach_section_id( $field, $section_id );
    }

    /**
     * Whether any field in a builder group is marked required.
     *
     * @param array<int, array<string, mixed>> $fields   Raw tab fields from builder config.
     * @param string                            $group_id Group instance ID.
     * @return bool
     */
    private function resolve_group_required_flag( array $fields, string $group_id ): bool {
        if ( '' === $group_id ) {
            return false;
        }

        foreach ( $fields as $field ) {
            if ( ! is_array( $field ) ) {
                continue;
            }
            if ( ( $field['group_id'] ?? '' ) !== $group_id ) {
                continue;
            }
            if ( ! empty( $field['required'] ) || ! empty( $field['is_required'] ) ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Collect validation error messages for required metabox fields before save.
     *
     * @return string[]
     */
    private function collect_validation_errors(): array {
        $errors = array();
        $tabs   = $this->get_tabs_data();

        foreach ( $tabs as $tab ) {
            if ( empty( $tab['fields'] ) || ! is_array( $tab['fields'] ) ) {
                continue;
            }

            foreach ( $tab['fields'] as $field ) {
                if ( ! is_array( $field ) || ! $this->should_display_field( $field ) ) {
                    continue;
                }

                if ( empty( $field['is_required'] ) ) {
                    if ( ! empty( $field['required'] ) ) {
                        $field['is_required'] = true;
                    } else {
                        continue;
                    }
                }

                $field_type = $field['type'] ?? 'text';
                $handler    = Database::get_field_handler( $field_type );

                if ( ! $handler ) {
                    $handler = $this->get_fallback_handler( $field_type );
                }

                if ( ! $handler || ! method_exists( $handler, 'validate' ) ) {
                    continue;
                }

                if ( 'map' === $field_type && ! empty( $field['is_map_preview'] ) ) {
                    continue;
                }

                $value  = $this->get_post_value_for_validation( $field );
                $result = $handler->validate( $value, $field );

                if ( is_wp_error( $result ) ) {
                    $errors[] = $result->get_error_message();
                }
            }
        }

        /**
         * Filter validation errors collected before dynamic metabox save.
         *
         * @since 3.0.4
         *
         * @param string[] $errors Validation messages.
         */
        return apply_filters( 'hvnly_metabox_validation_errors', $errors );
    }

    /**
     * Read POST value for a field prior to handler validation.
     *
     * @param array<string, mixed> $field Field config.
     * @return mixed
     */
    private function get_post_value_for_validation( array $field ) {
        $field_name = $field['name'] ?? $field['id'] ?? '';
        $field_type = $field['type'] ?? 'text';

        if ( 'property_docs' === $field_type ) {
            return null;
        }

        if ( 'agents' === $field_type ) {
            $input_name = $field_name;
            if ( false === strpos( $field_name, '_agents' ) && ! empty( $field['group_base_id'] ) ) {
                $input_name = $field['group_base_id'] . '_agents';
            }
            $raw = filter_input( INPUT_POST, $input_name, FILTER_UNSAFE_RAW, FILTER_REQUIRE_ARRAY );

            return is_array( $raw ) ? $raw : array();
        }

        if ( 'video' === $field_type ) {
            $base = $field['group_base_id'] ?? '';
            if ( '' !== $base ) {
                $url = filter_input( INPUT_POST, $base . '_url', FILTER_UNSAFE_RAW );

                return is_string( $url ) ? $url : '';
            }
        }

        if ( 'gallery' === $field_type ) {
            $raw = filter_input( INPUT_POST, $field_name, FILTER_UNSAFE_RAW );

            return is_string( $raw ) ? $raw : '';
        }

        if ( 'map' === $field_type ) {
            return null;
        }

        if ( '' === $field_name ) {
            return '';
        }

        $raw = filter_input( INPUT_POST, $field_name, FILTER_UNSAFE_RAW );

        return ( null !== $raw && false !== $raw ) ? $raw : '';
    }

    /**
     * Store validation errors and flag redirect after a blocked save.
     *
     * @param int      $post_id Post ID.
     * @param string[] $errors  Error messages.
     * @return void
     */
    private function abort_save_with_validation_errors( int $post_id, array $errors ): void {
        set_transient( 'hvnly_validation_errors_' . $post_id, $errors, MINUTE_IN_SECONDS );

        add_filter(
            'redirect_post_location',
            static function ( $location ) {
                return add_query_arg( 'hvnly_validation_error', '1', $location );
            }
        );
    }

    /**
     * Get default tabs structure
     */
    private function get_default_tabs() {
        $default_tabs = TabData::hvnly_metabox_tabs_builder();
        $tabs = [];
        
        foreach ( $default_tabs as $index => $tab ) {
            $tabs[] = [
                'id'        => $tab['id'] ?? 'tab_' . $index,
                'title'     => $tab['hvnly__sectiontitle'] ?? 'Untitled Section',
                'icon'      => $this->normalize_icon( $tab['icon'] ?? 'fas fa-cog' ),
                'fields'    => $this->process_group_fields( $tab['fields'] ?? [], (string) ( $tab['id'] ?? 'tab_' . $index ) ),
                'order'     => $index,
                'collapsed' => false,
                'required'  => false,
            ];
        }
        
        return $tabs;
    }

    /**
     * Generate dynamic tabs HTML
     */
    private function generate_dynamic_tabs_html( $tabs ) {
        if (empty($tabs)) {
            return '<p>' . esc_html__('No sections configured. Please use the Property Builder to add sections.', 'havenlytics') . '</p>';
        }
        
        ob_start();
        ?>
<div class="hvnly__dyamic_metabox_tab__wrapper">
    <div class="hvnly__dyamic_metabox_tab__nav">
        <ul>
            <?php foreach ( $tabs as $index => $tab ) : ?>
            <?php $nav_active_class = ( 0 === $index ) ? 'hvnly__dyamic_metabox_tab__active' : ''; ?>
            <li class="<?php echo esc_attr( $nav_active_class ); ?>">
                <a href="javascript:void(0)" data-target=".hvnly-tab-<?php echo esc_attr( $tab['id'] ); ?>"
                    class="<?php echo esc_attr( $nav_active_class ); ?>">
                    <i class="<?php echo esc_attr( $tab['icon'] ); ?>"></i>
                    <?php echo esc_html( $tab['title'] ); ?>
                </a>
            </li>
            <?php endforeach; ?>
        </ul>
    </div>

    <div class="hvnly__dyamic_metabox_tab__content">
        <?php foreach ( $tabs as $index => $tab ) : ?>
        <?php 
            $panel_active_class = ( 0 === $index ) ? 'hvnly__dyamic_metabox_tab__active' : ''; 
            ?>
        <div
            class="hvnly__dyamic_metabox_tab__tab-content hvnly-tab-<?php echo esc_attr( $tab['id'] ); ?> <?php echo esc_attr( $panel_active_class ); ?>">
            <h3><?php echo esc_html( $tab['title'] ); ?></h3>

            <?php if ( ! empty( $tab['fields'] ) ) : ?>
            <div class="hvnly__dyamic_metabox_tab__fields">
                <?php foreach ( $tab['fields'] as $field ) : ?>
                <?php if ( ! $this->should_display_field( $field ) ) continue; ?>
                <?php echo $this->render_field( $field ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renders trusted field HTML from registered handlers. ?>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <p class="description"><?php esc_html_e('No fields configured for this section.', 'havenlytics'); ?></p>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php
        return ob_get_clean();
    }

    /**
     * Check if field should be displayed
     */
    private function should_display_field( $field ) {
        if ( isset( $field['enabled'] ) && ! $field['enabled'] ) {
            return false;
        }
        
        if ( isset( $field['admin_only'] ) && $field['admin_only'] && ! current_user_can( 'manage_options' ) ) {
            return false;
        }
        
        return true;
    }

    /**
     * Render a single field.
     *
     * IMPORTANT: field['name'] is NEVER overridden here.
     *
     * The field name in the builder config is "{group_base_id}_{metaKey}".
     * The rendered <input name> MUST equal that name so the save handler
     * (hvnly_save_dynamic_tabs_data) can read POST[field_name] correctly.
     * Overriding it with a legacy/discovered key was the root cause of:
     *  – duplicate values across multiple same-type groups
     *  – saves silently failing (POST key ≠ builder config key)
     *
     * Values are loaded via get_field_value() which handles legacy fallback
     * internally (direct → _hvnly_field_map → scan) without touching the name.
     */
    private function render_field( $field ) {
        // Ensure fieldid exists for field handlers that rely on it.
        if ( !isset( $field['fieldid'] ) ) {
            $field['fieldid'] = $field['id'] ?? $field['name'] ?? '';
        }

        $field_type    = $field['type']       ?? '';
        $group_type    = $field['group_type'] ?? '';
        $group_base_id = $field['group_base_id'] ?? '';

        // ── Resolve the stored value using this group's own base ID ──────────
        $value = $this->get_field_value( $this->post_id, $field );

        // Map: input names must always match this group's base ID (never another section's keys).
        if ( $field_type === 'map' && ! empty( $group_base_id ) ) {
            $field['address_field_name'] = $group_base_id . '_address';
            $field['lat_field_name']     = $group_base_id . '_latitude';
            $field['lng_field_name']     = $group_base_id . '_longitude';
            $field['map_field_name']     = $group_base_id . '_preview';
        }

        // ── Resolve handler ──────────────────────────────────────────────────
        $handler = Database::get_field_handler( $field_type );
        if ( !$handler ) {
            $handler = $this->get_fallback_handler( $field_type );
        }
        if ( !$handler ) {
            /* translators: %s: field type slug. */
            $message = sprintf( __( 'Error: Field type "%s" not found.', 'havenlytics' ), $field_type );
            return '<p class="hvnly-field-error-message">' . esc_html( $message ) . '</p>';
        }

        $field_content = $handler->render( $field, $value, $this->post_id );
        $is_required   = isset( $field['is_required'] ) && $field['is_required'];

        ob_start();
        ?>
<div class="hvnly__dyamic_metabox_tab__field <?php echo esc_attr( $this->get_wrapper_class( $field_type ) ); ?> <?php echo $is_required ? 'hvnly-field-required' : ''; ?>"
    data-field-id="<?php echo esc_attr( $field['id'] ?? $field['fieldid'] ?? '' ); ?>"
    data-field-type="<?php echo esc_attr( $field_type ); ?>"
    data-group-id="<?php echo esc_attr( $field['group_id'] ?? '' ); ?>"
    data-group-type="<?php echo esc_attr( $group_type ); ?>"
    data-group-base-id="<?php echo esc_attr( $group_base_id ); ?>"
    data-is-required="<?php echo $is_required ? 'true' : 'false'; ?>"
    <?php echo $is_required ? 'data-required="true"' : ''; ?>>
    <div class="hvnly__dyamic_metabox_tab__field-input">
        <?php echo $field_content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML from registered field type renderers. ?>
    </div>
</div>
<?php
        return ob_get_clean();
    }
    
    /**
     * Get fallback handler if registry fails
     */
    private function get_fallback_handler( $type ) {
        $handlers = [
            'text' => '\HvnlyNab\Database\FieldTypes\TextField',
            'textarea' => '\HvnlyNab\Database\FieldTypes\TextareaField',
            'number' => '\HvnlyNab\Database\FieldTypes\NumberField',
            'select' => '\HvnlyNab\Database\FieldTypes\SelectField',
            'checkbox' => '\HvnlyNab\Database\FieldTypes\CheckboxField',
            'file' => '\HvnlyNab\Database\FieldTypes\FileField',
            'gallery' => '\HvnlyNab\Database\FieldTypes\GalleryField',
            'map' => '\HvnlyNab\Database\FieldTypes\MapField',
            'video' => '\HvnlyNab\Database\FieldTypes\VideoField',
            'property_docs' => '\HvnlyNab\Database\FieldTypes\DocumentField',
            'agents' => '\HvnlyNab\Database\FieldTypes\AgentsField',
            'price_label' => '\HvnlyNab\Database\FieldTypes\PriceLabelField',
            'url' => '\HvnlyNab\Database\FieldTypes\UrlField',
            'date' => '\HvnlyNab\Database\FieldTypes\DateField',
            'faq' => '\HvnlyNab\Database\FieldTypes\FAQField',
            'repeater' => '\HvnlyNab\Database\FieldTypes\RepeaterField',
        ];
        
        if ( isset( $handlers[ $type ] ) && class_exists( $handlers[ $type ] ) ) {
            return new $handlers[ $type ]();
        }
        
        return null;
    }

    /**
     * Get wrapper class for field
     */
    private function get_wrapper_class( $field_type ) {
        return 'checkbox' === $field_type ? 'hvnly__dyamic_metabox_tab__checkbox' : '';
    }

    /**
     * Save dynamic tabs data
     */
    public function hvnly_save_dynamic_tabs_data( $post_id ) {
        // Skip during programmatic bulk imports — the importer sets its own meta directly.
        if ( !empty( $GLOBALS['hvnly_bulk_importing'] ) ) {
            return;
        }

        $nonce = filter_input( INPUT_POST, 'hvnly_dynamic_metabox_tabs_nonce', FILTER_UNSAFE_RAW );
        $nonce = is_string( $nonce ) ? sanitize_text_field( $nonce ) : '';
        
        if ( ! $nonce || ! wp_verify_nonce( $nonce, 'hvnly_dynamic_metabox_tabs_save' ) ) {
            return;
        }
        
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }
        
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }

        $validation_errors = $this->collect_validation_errors();
        if ( ! empty( $validation_errors ) ) {
            $this->abort_save_with_validation_errors( (int) $post_id, $validation_errors );
            return;
        }
        
        $tabs = $this->get_tabs_data();
        
        // Save fields
        foreach ( $tabs as $tab ) {
            if ( ! empty( $tab['fields'] ) ) {
                foreach ( $tab['fields'] as $field ) {
                    if ( ! $this->should_display_field( $field ) ) {
                        continue;
                    }
                    
                    $field_name = $field['name'] ?? $field['id'] ?? '';
                    $field_type = $field['type'] ?? 'text';
                    $handler    = Database::get_field_handler( $field_type );
                    
                    if ( ! $handler ) {
                        $handler = $this->get_fallback_handler( $field_type );
                    }
                    
                    if ( empty( $field_name ) || ! $handler ) {
                        continue;
                    }
                    
                    // Skip map preview fields (they don't store data)
                    if ( $field_type === 'map' && isset( $field['is_map_preview'] ) && $field['is_map_preview'] ) {
                        continue;
                    }
                    
                    // ── Property Documents (repeater) ────────────────────────────────
                    // DocumentField::render() outputs individual array inputs:
                    //   {field_name}_icons[], _labels[], _urls[], _url_types[]
                    // The previous approach tried to read a JSON hidden field at
                    // $_POST[$field_name] which was never rendered → always deleted
                    // the documents.  Delegate to the handler's own save() method
                    // which knows how to read those array inputs correctly.
                    if ( $field_type === 'property_docs' ) {
                        $handler->save( $post_id, $field_name );

                        // Lazy migration: keep _hvnly_field_map up to date (group_id keyed).
                        $group_type_d    = $field['group_type']    ?? '';
                        $group_base_id_d = $field['group_base_id'] ?? '';
                        $group_id_d      = $field['group_id']      ?? '';
                        if ( ! empty( $group_type_d ) && ! empty( $group_base_id_d ) ) {
                            if ( class_exists( '\HvnlyNab\Core\GroupFieldIdentity' ) ) {
                                \HvnlyNab\Core\GroupFieldIdentity::record_group_in_field_map(
                                    $post_id,
                                    $group_id_d,
                                    $group_base_id_d,
                                    $group_type_d
                                );
                            } else {
                                $fm_raw = get_post_meta( $post_id, '_hvnly_field_map', true );
                                $fm     = ! empty( $fm_raw ) ? json_decode( $fm_raw, true ) : [];
                                if ( ! is_array( $fm ) ) {
                                    $fm = [];
                                }
                                if ( ( $fm[ $group_type_d ] ?? '' ) !== $group_base_id_d ) {
                                    $fm[ $group_type_d ] = $group_base_id_d;
                                    update_post_meta( $post_id, '_hvnly_field_map', wp_json_encode( $fm ) );
                                }
                            }
                        }
                        continue;
                    }

                    if ( $field_type === 'faq' || $field_type === 'repeater' ) {
                        $handler->save( $post_id, $field_name );

                        $group_type_d    = $field['group_type']    ?? '';
                        $group_base_id_d = $field['group_base_id'] ?? '';
                        $group_id_d      = $field['group_id']      ?? '';
                        if ( ! empty( $group_type_d ) && ! empty( $group_base_id_d ) && class_exists( '\HvnlyNab\Core\GroupFieldIdentity' ) ) {
                            \HvnlyNab\Core\GroupFieldIdentity::record_group_in_field_map(
                                $post_id,
                                $group_id_d,
                                $group_base_id_d,
                                $group_type_d
                            );
                        }
                        continue;
                    }

                    if ( $field_type === 'agents' ) {
                        $handler->save( $post_id, $field_name );

                        $group_type_d    = $field['group_type']    ?? '';
                        $group_base_id_d = $field['group_base_id'] ?? '';
                        $group_id_d      = $field['group_id']      ?? '';
                        if ( ! empty( $group_type_d ) && ! empty( $group_base_id_d ) && class_exists( '\HvnlyNab\Core\GroupFieldIdentity' ) ) {
                            \HvnlyNab\Core\GroupFieldIdentity::record_group_in_field_map(
                                $post_id,
                                $group_id_d,
                                $group_base_id_d,
                                $group_type_d
                            );
                        }
                        continue;
                    }

                    // Checkbox repeater posts array inputs ({name}[0], …) and/or a hidden JSON field.
                    // filter_input() cannot read array POST values — delegate like faq/repeater/agents.
                    if ( $field_type === 'checkbox' ) {
                        $raw_value = $this->get_checkbox_post_value( $field_name );

                        if ( $raw_value !== null ) {
                            $sanitized_value = $handler->sanitize( $raw_value );
                            $handler->save( $post_id, $field_name, $sanitized_value );

                            $group_type    = $field['group_type']    ?? '';
                            $group_base_id = $field['group_base_id'] ?? '';
                            $group_id      = $field['group_id']      ?? '';
                            if ( ! empty( $group_type ) && ! empty( $group_base_id ) ) {
                                if ( class_exists( '\HvnlyNab\Core\GroupFieldIdentity' ) ) {
                                    \HvnlyNab\Core\GroupFieldIdentity::record_group_in_field_map(
                                        $post_id,
                                        $group_id,
                                        $group_base_id,
                                        $group_type
                                    );
                                } else {
                                    $field_map_raw = get_post_meta( $post_id, '_hvnly_field_map', true );
                                    $field_map     = ! empty( $field_map_raw ) ? json_decode( $field_map_raw, true ) : [];
                                    if ( ! is_array( $field_map ) ) {
                                        $field_map = [];
                                    }
                                    if ( ( $field_map[ $group_type ] ?? '' ) !== $group_base_id ) {
                                        $field_map[ $group_type ] = $group_base_id;
                                        update_post_meta( $post_id, '_hvnly_field_map', wp_json_encode( $field_map ) );
                                    }
                                }
                            }
                        } else {
                            hvnly_safe_delete_post_meta( $post_id, $field_name, 'user_save_empty' );
                        }
                        continue;
                    }
                    
                    // Get raw value from POST
                    $raw_value = filter_input( INPUT_POST, $field_name, FILTER_UNSAFE_RAW );
                    
                    if ( $raw_value !== null && $raw_value !== false ) {
                        $sanitized_value = $handler->sanitize( $raw_value );
                        $handler->save( $post_id, $field_name, $sanitized_value );

                        // ── Lazy migration: update _hvnly_field_map (group_id keyed) ──
                        $group_type    = $field['group_type']    ?? '';
                        $group_base_id = $field['group_base_id'] ?? '';
                        $group_id      = $field['group_id']      ?? '';
                        if ( ! empty( $group_type ) && ! empty( $group_base_id ) ) {
                            if ( class_exists( '\HvnlyNab\Core\GroupFieldIdentity' ) ) {
                                \HvnlyNab\Core\GroupFieldIdentity::record_group_in_field_map(
                                    $post_id,
                                    $group_id,
                                    $group_base_id,
                                    $group_type
                                );
                            } else {
                                $field_map_raw = get_post_meta( $post_id, '_hvnly_field_map', true );
                                $field_map     = ! empty( $field_map_raw ) ? json_decode( $field_map_raw, true ) : [];
                                if ( ! is_array( $field_map ) ) {
                                    $field_map = [];
                                }
                                if ( ( $field_map[ $group_type ] ?? '' ) !== $group_base_id ) {
                                    $field_map[ $group_type ] = $group_base_id;
                                    update_post_meta( $post_id, '_hvnly_field_map', wp_json_encode( $field_map ) );
                                }
                            }
                        }
                    }
                }
            }
        }
        
        $this->save_gallery_metadata( $post_id );
        $this->save_map_subfields( $post_id );
    }

    /**
     * Read checkbox repeater POST payload (array rows and/or hidden JSON fallback).
     *
     * @param string $field_name Field name from builder config.
     * @return array<int, string>|string|null Null when field was not posted.
     */
    private function get_checkbox_post_value( string $field_name ) {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified in hvnly_save_dynamic_tabs_data().
        if ( ! isset( $_POST[ $field_name ] ) ) {
            return null;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $posted = wp_unslash( $_POST[ $field_name ] );

        if ( is_array( $posted ) ) {
            $items = [];
            foreach ( $posted as $item ) {
                if ( ! is_string( $item ) ) {
                    continue;
                }
                $item = trim( $item );
                if ( $item !== '' ) {
                    $items[] = $item;
                }
            }
            return $items;
        }

        if ( is_string( $posted ) && $posted !== '' ) {
            return $posted;
        }

        return null;
    }

    /**
     * Save map group sub-fields (address, latitude, longitude).
     *
     * The map renderer outputs individual inputs for address/lat/lng whose names
     * follow the pattern "{group_base_id}_{address|latitude|longitude}".
     * process_group_fields() only keeps the preview placeholder in the tab
     * field list, so the sub-fields are never reached by the main save loop.
     * This method scans the POST body for any keys matching those patterns and
     * saves them directly.
     */
    private function save_map_subfields( $post_id ) {
        $post_data = filter_input_array( INPUT_POST, FILTER_UNSAFE_RAW );
        if ( empty( $post_data ) || ! is_array( $post_data ) ) {
            return;
        }

        $map_suffixes = [ '_address', '_latitude', '_longitude' ];

        foreach ( $post_data as $key => $raw_value ) {
            if ( ! is_string( $key ) || ! is_string( $raw_value ) ) {
                continue;
            }
            // Only process keys that start with 'map_' and end with a known suffix.
            if ( strpos( $key, 'map_' ) !== 0 ) {
                continue;
            }
            $matched = false;
            foreach ( $map_suffixes as $suffix ) {
                if ( substr( $key, -strlen( $suffix ) ) === $suffix ) {
                    $matched = true;
                    break;
                }
            }
            if ( ! $matched ) {
                continue;
            }

            $sanitized = sanitize_text_field( $raw_value );
            if ( !empty( $sanitized ) ) {
                update_post_meta( $post_id, $key, $sanitized );
            } else {
                $suffix = '';
                foreach ( $map_suffixes as $map_suffix ) {
                    if ( substr( $key, -strlen( $map_suffix ) ) === $map_suffix ) {
                        $suffix = substr( $map_suffix, 1 );
                        break;
                    }
                }
                if ( $suffix !== '' && $this->map_subfield_has_compatibility_value( $post_id, $suffix, $key ) ) {
                    continue;
                }
                hvnly_safe_delete_post_meta( $post_id, $key, 'user_save_empty' );
            }
        }
    }

    /**
     * Whether map data exists via import/legacy resolution but not at the builder POST key.
     *
     * @param int    $post_id     Post ID.
     * @param string $meta_suffix address|latitude|longitude.
     * @param string $primary_key Builder meta key from POST.
     * @return bool
     */
    private function map_subfield_has_compatibility_value( $post_id, $meta_suffix, $primary_key ) {
        if ( ! class_exists( '\HvnlyNab\Core\DataPreservation\MetaResolver' ) ) {
            return false;
        }

        $existing = \HvnlyNab\Core\DataPreservation\MetaResolver::get_field_value(
            (int) $post_id,
            array(
                'group_type' => 'map',
                'metaKey'    => $meta_suffix,
            ),
            $primary_key
        );

        if ( '' === $existing || false === $existing || null === $existing ) {
            return false;
        }

        $direct = get_post_meta( $post_id, $primary_key, true );
        return '' === $direct || false === $direct || null === $direct;
    }

    /**
     * Save gallery metadata for multiple groups
     */
    private function save_gallery_metadata( $post_id ) {
        $post_data = filter_input_array( INPUT_POST );
        
        if ( empty( $post_data ) || ! is_array( $post_data ) ) {
            return;
        }
        
        foreach ( $post_data as $key => $value ) {
            if ( strpos( $key, 'hvnly_gallery_title_' ) === 0 ) {
                $gallery_id    = str_replace( 'hvnly_gallery_title_', '', $key );
                $ids_key       = 'hvnly_gallery_ids_' . $gallery_id;
                $captions_key  = 'hvnly_gallery_caption_' . $gallery_id;
                
                $ids_raw     = filter_input( INPUT_POST, $ids_key, FILTER_UNSAFE_RAW, FILTER_REQUIRE_ARRAY ) ?: [];
                $titles_raw  = filter_input( INPUT_POST, $key, FILTER_UNSAFE_RAW, FILTER_REQUIRE_ARRAY ) ?: [];
                $captions_raw = filter_input( INPUT_POST, $captions_key, FILTER_UNSAFE_RAW, FILTER_REQUIRE_ARRAY ) ?: [];
                
                $attachment_ids = array_map( 'intval', (array) $ids_raw );
                $titles = array_map( 'sanitize_text_field', (array) $titles_raw );
                $captions = array_map( 'sanitize_textarea_field', (array) $captions_raw );
                
                foreach ( $attachment_ids as $index => $attachment_id ) {
                    if ( ! empty( $attachment_id ) ) {
                        $title   = isset( $titles[ $index ] ) ? $titles[ $index ] : '';
                        $caption = isset( $captions[ $index ] ) ? $captions[ $index ] : '';
                        
                        wp_update_post([
                            'ID'           => intval( $attachment_id ),
                            'post_title'   => $title,
                            'post_excerpt' => $caption,
                        ]);
                    }
                }
            }
        }
    }

    /**
     * Enqueue admin assets
     */
    public static function enqueue_assets() {
        if ( ! is_admin() ) {
            return;
        }
        
        $screen = get_current_screen();
        if ( ! $screen ) {
            return;
        }
        
        $allowed_post_types = array( 'hvnly_property' );
        $default_version = defined( 'HVNLYNAB_VERSION' ) ? HVNLYNAB_VERSION : '1.0.0';
        
        if ( in_array( $screen->post_type ?? '', $allowed_post_types, true ) ) {
            $styles = [
                'hvnly-admin-fontawesome-all' => HVNLYNAB_ASSETS_URL . '/admin/css/fontawesome-all.min.css',
                'hvnly-admin-leaflet'         => HVNLYNAB_ASSETS_URL . '/admin/css/hvnly-admin-leaflet.css',
                'hvnly-admin-metabox'         => HVNLYNAB_ASSETS_URL . '/admin/css/hvnly-admin-metabox.css',
            ];

            foreach ( $styles as $handle => $src ) {
                $file_path = HVNLYNAB_PATH . str_replace( HVNLYNAB_ASSETS_URL, '', $src );
                $version   = file_exists( $file_path ) ? filemtime( $file_path ) : $default_version;
                wp_enqueue_style( $handle, $src, array(), $version );
            }
        }
        
        if ( in_array( $screen->base, array( 'post', 'post-new' ), true ) &&
             in_array( $screen->post_type ?? '', $allowed_post_types, true ) ) {
            
            wp_enqueue_script(
                'leaflet',
                HVNLYNAB_ASSETS_URL . '/admin/js/leaflet/core-leaflet.js',
                array(),
                '1.9.4',
                true
            );
        }
    }
}