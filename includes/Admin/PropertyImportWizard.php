<?php
/**
 * Property Import Engine
 *
 * Headless demo-import engine: AJAX handlers (`hvnly_import_properties`,
 * `hvnly_cancel_import`, map/API-key saves), the batch import pipeline, term
 * image seeding, and run-state management. The legacy Setup Wizard admin UI
 * that used to live here was retired in Sprint 26D — the React onboarding
 * (OnboardingWizard.php) is now the sole setup experience and drives this
 * engine through the same AJAX contract.
 *
 * Complete import with dynamic field matching - ALL 7 SECTIONS
 * with UNIQUE group_base_id per property and per group.
 *
 * @package     Havenlytics
 * @subpackage  Admin
 * @since       2.2.1
 */

namespace HvnlyNab\Admin;

use HvnlyNab\Admin\Data\DemoData;
use HvnlyNab\Admin\Data\UkImportLocations;
use HvnlyNab\Admin\Importer\PropertyLocationTermImageSeeder;
use HvnlyNab\Admin\Importer\PropertyTypeTermImageSeeder;
use HvnlyNab\Agent\PropertyAgentResolver;
use HvnlyNab\Core\SectionIdentity;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class PropertyImportWizard
 */
class PropertyImportWizard {

    /**
     * Post type slug
     *
     * @var string
     */
    private $post_type = 'hvnly_property';

    /**
     * Option name for tracking import
     *
     * @var string
     */
    private $import_option = 'hvnly_demo_properties_imported';

    /**
     * Option name for import count
     *
     * @var string
     */
    private $count_option = 'hvnly_demo_properties_count';

    /**
     * Temporary state for multi-batch import AJAX (user + email preference).
     *
     * @var string
     */
    private $import_run_option = 'hvnly_import_run_state';

    /**
     * Post meta: import session UUID for idempotent batch retries.
     *
     * @var string
     */
    private const IMPORT_SESSION_META = '_hvnly_import_session_id';

    /**
     * Post meta: zero-based property index within an import session.
     *
     * @var string
     */
    private const IMPORT_INDEX_META = '_hvnly_import_index';

    /**
     * Maximum file size for downloads (5MB)
     *
     * @var int
     */
    private $max_file_size = 5242880;

    /**
     * Allowed image mime types
     *
     * @var array
     */
    private $allowed_mime_types = [
        'image/jpeg',
        'image/jpg',
        'image/png',
        'image/gif',
        'image/webp',
    ];

    /**
     * Demo data instance
     *
     * @var DemoData
     */
    private $demo_data;

    /**
     * Allowed image domains for download
     *
     * @var array
     */
    private $allowed_image_domains = [
        'images.pexels.com',
        'img.youtube.com',
        'demo.havenlytics.com',
        'localhost',
        '127.0.0.1',
    ];

    /**
     * Consistent timeout (seconds) for every remote image download during import.
     *
     * Kept well under the request execution budget so a single stalled host cannot
     * exceed max_execution_time and 5xx the whole batch. Previously downloads ranged
     * from 15s to a dangerous 300s.
     */
    const IMAGE_DOWNLOAD_TIMEOUT = 15;

    /**
     * Debug mode flag
     *
     * @var bool
     */
    private $debug_mode = false;

    /**
     * Import running flag
     *
     * @var bool
     */
    private $import_running = false;

    /**
     * Original cache invalidation state
     *
     * @var bool
     */
    private $original_cache_state = false;

    /**
     * Original term counting state
     *
     * @var bool
     */
    private $original_term_state = false;

    /**
     * Original comment counting state
     *
     * @var bool
     */
    private $original_comment_state = false;

    /**
     * Active theme name
     *
     * @var string
     */
    private $active_theme = '';

    /**
     * Heavy themes list
     *
     * @var array
     */
    private $heavy_themes = [
        'Porto', 'Divi', 'Avada', 'Extra', 'Jupiter', 'The7', 'Enfold', 'Salient', 'Bridge', 'X Theme',
    ];

    /**
     * Property builder storage keys
     *
     * @var array
     */
    private $property_builder_keys = [
        'hvnly_property_builder.sections',
        'hvnly_pb_dnd_sections',
        'hvnly_property_builder_sections',
        'hvnly_property_builder_tabs',
    ];

    /**
     * Card builder storage keys
     *
     * @var array
     */
    private $card_builder_keys = [
        'hvnly_property_card.sections',
        'hvnly_pb_card_builder_sections',
        'hvnly_property_card_layout',
        'hvnly_card_builder_config',
    ];

    /**
     * All departments for import distribution
     *
     * @var array
     */
    private $all_departments = ['sale', 'rent', 'commercial', 'let'];

    /**
     * Demo agent slug => post ID map for the current import run.
     *
     * @var array<string, int>
     */
    private $demo_agent_map = array();

    /**
     * Whether import-time image size filters are active.
     *
     * @var bool
     */
    private $import_image_filters_active = false;

    /**
     * Constructor
     */
    public function __construct() {
        $this->demo_data = new DemoData();
        $this->debug_mode = function_exists( 'hvnly_is_debug_logging_enabled' ) && hvnly_is_debug_logging_enabled();
        $this->active_theme = wp_get_theme()->get( 'Name' );

        // Legacy Setup Wizard UI retired (Sprint 26D). The React onboarding
        // (OnboardingWizard.php) is now the sole setup experience. Only the
        // shared import ENGINE and its AJAX/term-seeding hooks remain here.
        add_action( 'wp_ajax_hvnly_import_properties', [ $this, 'ajax_import_properties' ] );
        add_action( 'wp_ajax_hvnly_cancel_import', [ $this, 'ajax_cancel_import' ] );
        add_action( 'wp_ajax_hvnly_save_google_api_key', [ $this, 'ajax_save_google_api_key' ] );
        add_action( 'wp_ajax_hvnly_save_map_provider', [ $this, 'ajax_save_map_provider' ] );
        add_action( 'load-edit-tags.php', [ $this, 'maybe_backfill_location_term_images_admin' ] );
    }

    /**
     * Generate a UNIQUE base ID for a group
     * Uses UnifiedFieldGenerator as SINGLE SOURCE OF TRUTH
     *
     * @param string $group_type Type of group (video, gallery, map, property_docs)
     * @return string Unique base ID
     */
    private function generate_unique_base_id($group_type) {
        // Use UnifiedFieldGenerator as the single source of truth
        if (class_exists('\HvnlyNab\Core\UnifiedFieldGenerator')) {
            $unified = \HvnlyNab\Core\UnifiedFieldGenerator::get_instance();
            return $unified->generate_unique_base_id($group_type);
        }
        
        // Fallback generation (should never happen)
        $microtime = microtime(true);
        $timestamp = (int)$microtime;
        $micro_suffix = substr(str_replace('.', '', (string)$microtime), -6);
        $random = wp_rand(10000, 99999);
        $unique_id = uniqid();
        
        switch ($group_type) {
            case 'video':
                return "video_{$timestamp}_{$micro_suffix}_{$unique_id}_{$random}";
            case 'gallery':
                return "gallery_{$timestamp}_{$micro_suffix}_{$unique_id}_{$random}";
            case 'map':
                return "map_{$timestamp}_{$micro_suffix}_{$unique_id}_{$random}";
            case 'property_docs':
                return "property_docs_{$timestamp}_{$micro_suffix}_{$unique_id}_{$random}";
            case 'faq':
                return "faq_{$timestamp}_{$micro_suffix}_{$unique_id}_{$random}";
            case 'repeater':
                return "repeater_{$timestamp}_{$micro_suffix}_{$unique_id}_{$random}";
            case 'agents':
                return "agents_{$timestamp}_{$micro_suffix}_{$unique_id}_{$random}";
            case 'features':
                return "features_{$timestamp}_{$micro_suffix}_{$unique_id}_{$random}";
            default:
                return "group_{$timestamp}_{$micro_suffix}_{$unique_id}_{$random}";
        }
    }

    /**
     * Register hooks
     */
    public function register(): void {}

    /**
     * Whether the import wizard should be inaccessible after a successful import.
     *
     * @return bool
     */
    private function is_import_wizard_locked(): bool {
        if ( get_option( $this->import_option, false ) ) {
            return true;
        }

        $property_count  = wp_count_posts( $this->post_type );
        $published_count = isset( $property_count->publish ) ? absint( $property_count->publish ) : 0;

        return $published_count > 0;
    }

    private function is_heavy_theme(): bool {
        foreach ( $this->heavy_themes as $theme ) {
            if ( stripos( $this->active_theme, $theme ) !== false ) {
                return true;
            }
        }
        return false;
    }

    /**
     * Verify AJAX request for import wizard map/settings actions.
     *
     * @return bool
     */
    private function verify_import_wizard_ajax_request() {
        if ( ! check_ajax_referer( 'hvnly_import_nonce', 'nonce', false ) ) {
            wp_send_json_error( array( 'message' => __( 'Security check failed.', 'havenlytics' ) ), 403 );
            return false;
        }

        if ( ! isset( $_POST['csrf_token'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['csrf_token'] ) ), 'hvnly_import_csrf' ) ) {
            wp_send_json_error( array( 'message' => __( 'CSRF validation failed.', 'havenlytics' ) ), 403 );
            return false;
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'havenlytics' ) ), 403 );
            return false;
        }

        return true;
    }

    /**
     * Save Google Maps API key into canonical map settings.
     */
    public function ajax_save_google_api_key() {
        if ( ! $this->verify_import_wizard_ajax_request() ) {
            return;
        }

        $api_key = isset( $_POST['google_api_key'] ) ? sanitize_text_field( wp_unslash( $_POST['google_api_key'] ) ) : '';
        $provider = isset( $_POST['map_provider'] ) ? sanitize_text_field( wp_unslash( $_POST['map_provider'] ) ) : '';

        if ( '' !== $provider && 'google' !== $provider ) {
            wp_send_json_error(
                array(
                    'message' => __( 'Google API key is only required when Google Maps is selected.', 'havenlytics' ),
                ),
                400
            );
            return;
        }

        if ( '' === $provider ) {
            $provider = function_exists( 'hvnly_get_map_provider' ) ? hvnly_get_map_provider() : 'leaflet';
        }

        if ( 'google' !== $provider ) {
            wp_send_json_error(
                array(
                    'message' => __( 'Google API key is only required when Google Maps is selected.', 'havenlytics' ),
                ),
                400
            );
            return;
        }

        if ( '' === $api_key ) {
            wp_send_json_error( array( 'message' => __( 'API key is required.', 'havenlytics' ) ), 400 );
            return;
        }

        $map_settings = $this->save_map_settings_partial(
            array(
                'hvnly_map_api_key' => $api_key,
            )
        );

        if ( null === $map_settings ) {
            wp_send_json_error( array( 'message' => __( 'Settings API unavailable.', 'havenlytics' ) ), 500 );
            return;
        }

        wp_send_json_success(
            array(
                'message' => __( 'Google API key saved.', 'havenlytics' ),
                'map'     => $map_settings,
            )
        );
    }

    /**
     * Save active map provider into canonical map settings.
     */
    public function ajax_save_map_provider() {
        if ( ! $this->verify_import_wizard_ajax_request() ) {
            return;
        }

        $provider = isset( $_POST['map_provider'] ) ? sanitize_text_field( wp_unslash( $_POST['map_provider'] ) ) : '';
        $allowed  = array( 'leaflet', 'openstreetmap', 'google' );

        if ( ! in_array( $provider, $allowed, true ) ) {
            wp_send_json_error( array( 'message' => __( 'Invalid map provider.', 'havenlytics' ) ), 400 );
            return;
        }

        $map_settings = $this->save_map_settings_partial(
            array(
                'hvnly_map_provider' => $provider,
            )
        );

        if ( null === $map_settings ) {
            wp_send_json_error( array( 'message' => __( 'Settings API unavailable.', 'havenlytics' ) ), 500 );
            return;
        }

        wp_send_json_success(
            array(
                'message'  => __( 'Map provider saved.', 'havenlytics' ),
                'provider' => $map_settings['hvnly_map_provider'] ?? 'leaflet',
                'map'      => $map_settings,
            )
        );
    }
    
    private function reset_add_property_form(): bool {
        if ( class_exists( '\HvnlyNab\Core\DataPreservation\BatchProcessor' ) ) {
            $property_count = \HvnlyNab\Core\DataPreservation\BatchProcessor::count_properties();
            if ( $property_count > 0 ) {
                $this->log_error(
                    sprintf(
                        'Blocked property builder reset: %d properties exist. Builder config preserved.',
                        $property_count
                    )
                );
                return false;
            }
        }

        $success = true;
        foreach ( $this->property_builder_keys as $key ) {
            if ( get_option( $key ) !== false ) {
                if ( ! hvnly_safe_delete_option( $key, 'import_wizard_reset' ) ) {
                    $success = false;
                    $this->log_error( 'Failed to delete option (blocked or error): ' . $key );
                }
            }
        }
        $this->clear_builder_transients( 'pb_' );
        $this->clear_builder_transients( 'property_builder_' );
        if ( function_exists( 'HVN' ) && method_exists( HVNLY_NAB(), 'engine' ) ) {
            HVNLY_NAB()->engine()->clear_transients_by_pattern( 'pb_' );
            HVNLY_NAB()->engine()->clear_transients_by_pattern( 'property_builder_' );
        }
        return $success;
    }

    private function reset_property_card_builder(): bool {
        if ( class_exists( '\HvnlyNab\Core\DataPreservation\BatchProcessor' ) ) {
            $property_count = \HvnlyNab\Core\DataPreservation\BatchProcessor::count_properties();
            if ( $property_count > 0 ) {
                $this->log_error(
                    sprintf(
                        'Blocked card builder reset: %d properties exist. Card layout preserved.',
                        $property_count
                    )
                );
                return false;
            }
        }

        $success = true;
        foreach ( $this->card_builder_keys as $key ) {
            if ( get_option( $key ) !== false ) {
                if ( ! hvnly_safe_delete_option( $key, 'import_wizard_reset' ) ) {
                    $success = false;
                    $this->log_error( 'Failed to delete option (blocked or error): ' . $key );
                }
            }
            wp_cache_delete( $key, 'options' );
        }
        $this->clear_builder_transients( 'card_' );
        $this->clear_builder_transients( 'card_builder_' );
        if ( function_exists( 'HVNLY_NAB' ) && method_exists( HVNLY_NAB(), 'engine' ) ) {
            HVNLY_NAB()->engine()->clear_transients_by_pattern( 'card_' );
            HVNLY_NAB()->engine()->clear_transients_by_pattern( 'card_builder_' );
        }
        return $success;
    }

    /**
     * Write the canonical default card layout after a reset.
     *
     * @return bool True when defaults were persisted.
     */
    private function initialize_property_card_builder_defaults(): bool {
        if ( ! class_exists( '\HvnlyNab\Api\Type\Builders\DnDCardBuilder' ) ) {
            return false;
        }

        if ( has_filter( 'hvnly_persist_card_builder_defaults' ) ) {
            return (bool) apply_filters( 'hvnly_persist_card_builder_defaults', false );
        }

        $builder = new \HvnlyNab\Api\Type\Builders\DnDCardBuilder();
        return $builder->seed_default_sections();
    }

    /**
     * On the first-ever property import, clear stale card builder config and
     * seed the bundled default layout so frontend cards match imported fields.
     *
     * Skipped when import has already completed (hvnly_demo_properties_imported)
     * or when resuming a multi-batch import (imported > 0).
     *
     * @param int $imported Number of properties already imported in this run.
     * @return void
     */
    /**
     * Import demo agencies/agents once per import run (idempotent by slug).
     *
     * @param array<string, mixed> $options  Sanitized import options.
     * @param int                  $imported Properties already imported in this run.
     * @return void
     */
    private function ensure_demo_agent_ecosystem( array $options, int $imported, ?bool $force_include_images = null ): void {
        if ( ! class_exists( DemoAgentAgencyImporter::class ) ) {
            return;
        }

        if ( ! empty( $this->demo_agent_map ) ) {
            return;
        }

        $include_images = null !== $force_include_images
            ? $force_include_images
            : ( ! isset( $options['include_images'] ) || ! empty( $options['include_images'] ) );

        $importer = new DemoAgentAgencyImporter(
            array( $this, 'download_and_attach_image_secure' ),
            array( $this, 'log_error' )
        );

        $this->demo_agent_map = $importer->import_ecosystem( $include_images );

        if ( 0 === $imported && ! empty( $this->demo_agent_map ) ) {
            $this->log_debug(
                'Demo agent ecosystem ready',
                array(
                    'agents' => count( $this->demo_agent_map ),
                )
            );
        }
    }

    /**
     * Sideload a few missing demo agent/agency images per batch to avoid gateway timeouts.
     *
     * @param array<string, mixed> $options Sanitized import options.
     * @param int                  $limit   Max downloads this request.
     * @return void
     */
    private function maybe_backfill_demo_agent_images_batch( array $options, int $limit = 2 ): void {
        if ( ! class_exists( DemoAgentAgencyImporter::class ) ) {
            return;
        }

        $include_images = ! isset( $options['include_images'] ) || ! empty( $options['include_images'] );
        if ( ! $include_images || $limit <= 0 ) {
            return;
        }

        $importer = new DemoAgentAgencyImporter(
            array( $this, 'download_and_attach_image_secure' ),
            array( $this, 'log_error' )
        );
        $importer->backfill_missing_images( $limit );
    }

    /**
     * Reuse sideloaded demo images within an import session.
     *
     * @param string $image_url Remote image URL.
     * @return int Attachment ID or 0.
     */
    private function get_cached_sideload_attachment( string $image_url ): int {
        $image_url = esc_url_raw( $image_url );
        if ( '' === $image_url ) {
            return 0;
        }

        $state = $this->load_import_run_state_raw();
        $cache = is_array( $state['media_cache'] ?? null ) ? $state['media_cache'] : array();
        $key   = md5( $image_url );

        if ( empty( $cache[ $key ] ) ) {
            return 0;
        }

        $attachment_id = absint( $cache[ $key ] );
        if ( $attachment_id <= 0 || 'attachment' !== get_post_type( $attachment_id ) ) {
            return 0;
        }

        return $attachment_id;
    }

    /**
     * Remember a sideloaded demo image for reuse across import batches.
     *
     * @param string $image_url     Remote image URL.
     * @param int    $attachment_id Attachment post ID.
     * @return void
     */
    private function cache_sideload_attachment( string $image_url, int $attachment_id ): void {
        $image_url = esc_url_raw( $image_url );
        $attachment_id = absint( $attachment_id );
        if ( '' === $image_url || $attachment_id <= 0 ) {
            return;
        }

        $state = $this->load_import_run_state_raw();
        $cache = is_array( $state['media_cache'] ?? null ) ? $state['media_cache'] : array();
        $cache[ md5( $image_url ) ] = $attachment_id;
        $this->update_import_run_state( array( 'media_cache' => $cache ) );
    }

    /**
     * Lightweight prep steps before the first property to stay under gateway timeouts.
     *
     * @param int                  $prep_step       Current prep step (0–1).
     * @param array<string, mixed> $options         Sanitized import options.
     * @param int                  $total_to_import Total target.
     * @return array<string, mixed>
     */
    private function process_import_prep_step( int $prep_step, array $options, int $total_to_import ): array {
        $this->ensure_taxonomies_exist();

        if ( 0 === $prep_step ) {
            $this->ensure_import_property_builder_sections();
            $this->ensure_import_property_features_section();
            $this->ensure_import_tail_section_order();
            $this->maybe_initialize_card_builder_for_first_import( 0 );
            $this->update_import_run_state( array( 'prep_step' => 1 ) );

            return array(
                'batch'      => 0,
                'next_batch' => 0,
                'imported'   => 0,
                'total'      => $total_to_import,
                'complete'   => false,
                'percent'    => 0,
                'message'    => __( 'Initializing property builder…', 'havenlytics' ),
                'errors'     => null,
            );
        }

        $this->ensure_demo_agent_ecosystem( $options, 0, false );
        $this->update_import_run_state( array( 'prep_step' => 2 ) );

        return array(
            'batch'      => 0,
            'next_batch' => 0,
            'imported'   => 0,
            'total'      => $total_to_import,
            'complete'   => false,
            'percent'    => 0,
            'message'    => __( 'Creating demo agents and agencies…', 'havenlytics' ),
            'errors'     => null,
        );
    }

    /**
     * Assign imported demo agents to a property when the ecosystem is available.
     *
     * @param int                  $post_id Property post ID.
     * @param array<string, mixed> $data    Property payload.
     * @return void
     */
    private function maybe_assign_demo_agents( int $post_id, array $data ): void {
        if ( empty( $this->demo_agent_map ) || ! class_exists( DemoAgentAgencyImporter::class ) ) {
            return;
        }

        $property_index = isset( $data['demo_property_index'] ) ? absint( $data['demo_property_index'] ) : 0;

        $importer = new DemoAgentAgencyImporter();
        $importer->assign_agents_to_property( $post_id, $property_index, $this->demo_agent_map );
    }

    /**
     * Assign demo agents to published properties that have no CPT assignments yet.
     *
     * @return void
     */
    private function maybe_backfill_demo_agent_assignments(): void {
        if ( empty( $this->demo_agent_map ) || ! class_exists( DemoAgentAgencyImporter::class ) ) {
            return;
        }

        $property_ids = get_posts(
            array(
                'post_type'              => $this->post_type,
                'post_status'            => 'publish',
                'posts_per_page'         => -1,
                'fields'                 => 'ids',
                'orderby'                => 'date',
                'order'                  => 'ASC',
                'no_found_rows'          => true,
                'update_post_meta_cache' => false,
                'update_post_term_cache' => false,
            )
        );

        if ( empty( $property_ids ) ) {
            return;
        }

        $importer = new DemoAgentAgencyImporter();
        $total    = count( DemoData::get_demo_properties_data() );

        foreach ( $property_ids as $index => $property_id ) {
            $property_id = absint( $property_id );
            if ( $property_id <= 0 ) {
                continue;
            }

            if ( class_exists( PropertyAgentResolver::class ) && PropertyAgentResolver::has_cpt_assignments( $property_id ) ) {
                continue;
            }

            $template_index = $total > 0 ? ( $index % $total ) : $index;
            $importer->assign_agents_to_property( $property_id, $template_index, $this->demo_agent_map );
        }
    }

    /**
     * Ensure FAQ, repeater, and agents builder sections exist before import.
     *
     * @return void
     */
    private function ensure_import_property_builder_sections(): void {
        $storage_key = 'hvnly_property_builder.sections';
        $sections    = get_option( $storage_key, array() );

        if ( ! is_array( $sections ) ) {
            $sections = array();
        }

        if ( ! class_exists( '\HvnlyNab\Core\UnifiedFieldGenerator' ) ) {
            return;
        }

        $generator = \HvnlyNab\Core\UnifiedFieldGenerator::get_instance();
        $unified   = $this->patch_import_default_builder_sections( $generator->get_unified_configuration() );
        $changed   = false;

        if ( empty( $sections ) || $this->is_minimal_installer_builder_config( $sections ) ) {
            update_option( $storage_key, $unified );
            wp_cache_delete( 'hvnly_standardized_field_names' );
            wp_cache_delete( $storage_key, 'options' );
            return;
        }

        $merged = $this->merge_missing_unified_builder_sections( $sections, $unified );
        if ( $merged !== $sections ) {
            $sections = $merged;
            $changed  = true;
        }

        $required_types = array( 'faq', 'repeater', 'agents' );
        $present        = array();

        foreach ( $sections as $section ) {
            foreach ( $section['fields'] ?? array() as $field ) {
                $group_type = sanitize_key( (string) ( $field['group_type'] ?? '' ) );
                if ( '' === $group_type ) {
                    $group_type = sanitize_key( (string) ( $field['type'] ?? '' ) );
                }
                if ( in_array( $group_type, $required_types, true ) ) {
                    $present[ $group_type ] = true;
                }
            }
        }

        if ( count( $present ) < count( $required_types ) ) {
            $master_ids = $generator->get_or_create_master_base_ids();
            $next_order = 0;

            foreach ( $sections as $section ) {
                $next_order = max( $next_order, (int) ( $section['order'] ?? 0 ) );
            }
            ++$next_order;

            $section_specs = array(
                'faq'      => array(
                    'id'     => SectionIdentity::SEC_PROPERTY_FAQ,
                    'title'  => __( 'Buyer Questions', 'havenlytics' ),
                    'icon'   => 'fas fa-question-circle',
                    'method' => 'create_faq_group_fields',
                ),
                'repeater' => array(
                    'id'     => SectionIdentity::SEC_PROPERTY_REPEATER,
                    'title'  => __( 'Neighborhood Highlights', 'havenlytics' ),
                    'icon'   => 'fas fa-list',
                    'method' => 'create_repeater_group_fields',
                ),
                'agents'   => array(
                    'id'     => SectionIdentity::SEC_PROPERTY_AGENTS,
                    'title'  => __( 'Property Agents', 'havenlytics' ),
                    'icon'   => 'fas fa-user-tie',
                    'method' => 'create_agents_group_fields',
                ),
            );

            foreach ( $required_types as $type ) {
                if ( ! empty( $present[ $type ] ) ) {
                    continue;
                }

                $spec = $section_specs[ $type ] ?? null;
                $base = sanitize_key( (string) ( $master_ids[ $type ] ?? '' ) );
                if ( empty( $spec ) || '' === $base ) {
                    continue;
                }

                if ( SectionIdentity::has_equivalent_section( $sections, $spec['id'] ) ) {
                    continue;
                }

                $method = $spec['method'];
                if ( ! method_exists( $generator, $method ) ) {
                    continue;
                }

                $sections[ $spec['id'] ] = array(
                    'id'        => $spec['id'],
                    'title'     => $spec['title'],
                    'icon'      => $spec['icon'],
                    'required'  => false,
                    'order'     => $next_order++,
                    'collapsed' => false,
                    'fields'    => $this->apply_import_default_section_labels( $generator->$method( $base ), $type ),
                );
                $changed = true;
            }
        }

        if ( $changed ) {
            update_option( $storage_key, $sections );
            wp_cache_delete( 'hvnly_standardized_field_names' );
            wp_cache_delete( $storage_key, 'options' );
        }
    }

    /**
     * Whether builder storage is the minimal installer stub (price-only) or missing core groups.
     *
     * @param array<string, array<string, mixed>> $sections Builder sections.
     * @return bool
     */
    private function is_minimal_installer_builder_config( array $sections ): bool {
        if ( empty( $sections ) ) {
            return true;
        }

        foreach ( array( 'video', 'gallery', 'map', 'property_docs' ) as $group_type ) {
            if ( ! $this->builder_has_group_type( $sections, $group_type ) ) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, array<string, mixed>> $sections Builder sections.
     * @param string                              $group_type Group type slug.
     * @return bool
     */
    private function builder_has_group_type( array $sections, string $group_type ): bool {
        foreach ( $sections as $section ) {
            if ( ! is_array( $section ) ) {
                continue;
            }
            if ( '' !== SectionIdentity::extract_group_base_from_section( $section, $group_type ) ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Add unified default sections that are missing from an existing builder config.
     *
     * @param array<string, array<string, mixed>> $existing Existing builder sections.
     * @param array<string, array<string, mixed>> $unified  Unified default sections.
     * @return array<string, array<string, mixed>>
     */
    private function merge_missing_unified_builder_sections( array $existing, array $unified ): array {
        foreach ( $unified as $section_id => $section ) {
            if ( ! is_array( $section ) ) {
                continue;
            }

            $target_id = (string) ( $section['id'] ?? $section_id );
            if ( SectionIdentity::has_equivalent_section( $existing, $target_id ) ) {
                continue;
            }

            $primary_type = $this->detect_primary_group_type( $section );
            if ( '' !== $primary_type && $this->builder_has_group_type( $existing, $primary_type ) ) {
                continue;
            }

            $existing[ (string) $section_id ] = $section;
        }

        return $existing;
    }

    /**
     * @param array<string, mixed> $section Builder section row.
     * @return string
     */
    private function detect_primary_group_type( array $section ): string {
        foreach ( $section['fields'] ?? array() as $field ) {
            if ( ! is_array( $field ) ) {
                continue;
            }

            $group_type = sanitize_key( (string) ( $field['group_type'] ?? '' ) );
            if ( '' === $group_type ) {
                $group_type = sanitize_key( (string) ( $field['type'] ?? '' ) );
            }

            if ( in_array( $group_type, array( 'video', 'gallery', 'map', 'property_docs', 'faq', 'repeater', 'agents', 'features' ), true ) ) {
                return $group_type;
            }
        }

        return '';
    }

    /**
     * Resolve canonical import group_base_id values from live builder config.
     *
     * @return array<string, string>
     */
    private function resolve_import_group_bases(): array {
        $sections = get_option( 'hvnly_property_builder.sections', array() );
        if ( ! is_array( $sections ) ) {
            $sections = array();
        }

        $group_types = array( 'video', 'gallery', 'map', 'property_docs', 'faq', 'repeater', 'agents', 'features' );
        $bases       = array();

        foreach ( $group_types as $group_type ) {
            $base = $this->resolve_canonical_group_base( $sections, $group_type );
            if ( '' !== $base ) {
                $bases[ $group_type ] = $base;
            }
        }

        if ( class_exists( '\HvnlyNab\Core\UnifiedFieldGenerator' ) ) {
            $master_ids = \HvnlyNab\Core\UnifiedFieldGenerator::get_instance()->get_or_create_master_base_ids();
            foreach ( $group_types as $group_type ) {
                if ( empty( $bases[ $group_type ] ) && ! empty( $master_ids[ $group_type ] ) ) {
                    $bases[ $group_type ] = sanitize_key( (string) $master_ids[ $group_type ] );
                }
            }
        }

        return $bases;
    }

    /**
     * @param array<string, array<string, mixed>> $sections   Builder sections.
     * @param string                              $group_type Group type slug.
     * @return string
     */
    private function resolve_canonical_group_base( array $sections, string $group_type ): string {
        foreach ( SectionIdentity::canonical_section_ids_for_group_type( $group_type ) as $target_id ) {
            $match = SectionIdentity::find_equivalent_section( $sections, $target_id );
            if ( null === $match ) {
                continue;
            }

            $base = SectionIdentity::extract_group_base_from_section( $match['section'], $group_type );
            if ( '' !== $base ) {
                return $base;
            }
        }

        $sorted = array_values( $sections );
        usort(
            $sorted,
            function ( $a, $b ) {
                return ( (int) ( $a['order'] ?? 99 ) ) - ( (int) ( $b['order'] ?? 99 ) );
            }
        );

        $best_base  = '';
        $best_score = PHP_INT_MAX;

        foreach ( $sorted as $section ) {
            if ( ! is_array( $section ) ) {
                continue;
            }

            $section_id = (string) ( $section['id'] ?? '' );
            $base       = SectionIdentity::extract_group_base_from_section( $section, $group_type );
            if ( '' === $base ) {
                continue;
            }

            $score = SectionIdentity::canonical_section_priority( $section_id, $group_type );
            $score = ( $score * 1000 ) + (int) ( $section['order'] ?? 99 );

            if ( $score < $best_score ) {
                $best_score = $score;
                $best_base  = $base;
            }
        }

        return $best_base;
    }

    /**
     * Ensure the Property Features builder section exists before import.
     *
     * @return void
     */
    private function ensure_import_property_features_section(): void {
        $storage_key = 'hvnly_property_builder.sections';
        $sections    = get_option( $storage_key, array() );

        if ( ! is_array( $sections ) ) {
            $sections = array();
        }

        foreach ( $sections as $section ) {
            foreach ( $section['fields'] ?? array() as $field ) {
                if ( 'features' === sanitize_key( (string) ( $field['group_type'] ?? '' ) ) ) {
                    $this->ensure_import_tail_section_order();
                    return;
                }
            }
        }

        if ( ! class_exists( '\HvnlyNab\Core\UnifiedFieldGenerator' ) ) {
            return;
        }

        $generator  = \HvnlyNab\Core\UnifiedFieldGenerator::get_instance();
        $master_ids = $generator->get_or_create_master_base_ids();
        $base       = sanitize_key( (string) ( $master_ids['features'] ?? '' ) );

        if ( '' === $base || ! method_exists( $generator, 'create_features_group_fields' ) ) {
            return;
        }

        $sections['sec_property_features'] = array(
            'id'        => 'sec_property_features',
            'title'     => __( 'Property Amenities', 'havenlytics' ),
            'icon'      => 'fas fa-check-square',
            'required'  => false,
            'order'     => 9,
            'collapsed' => false,
            'fields'    => $this->apply_import_default_section_labels( $generator->create_features_group_fields( $base ), 'features' ),
        );

        update_option( $storage_key, $sections );
        wp_cache_delete( 'hvnly_standardized_field_names' );
        wp_cache_delete( $storage_key, 'options' );
        $this->ensure_import_tail_section_order();
    }

    /**
     * Import-only display labels for newly added builder section fields.
     *
     * @param array<int, array<string, mixed>> $fields     Section fields from UnifiedFieldGenerator.
     * @param string                           $group_type faq|repeater|agents|features.
     * @return array<int, array<string, mixed>>
     */
    private function apply_import_default_section_labels( array $fields, string $group_type ): array {
        $label = $this->get_import_default_section_label( $group_type );
        if ( '' === $label ) {
            return $fields;
        }

        foreach ( $fields as $index => $field ) {
            if ( ! is_array( $field ) ) {
                continue;
            }
            $field['group_name'] = $label;
            $fields[ $index ]    = $field;
        }

        return $fields;
    }

    /**
     * Apply import default section titles when seeding an empty builder config.
     *
     * @param array<string, array<string, mixed>> $sections Builder sections keyed by section ID.
     * @return array<string, array<string, mixed>>
     */
    private function patch_import_default_builder_sections( array $sections ): array {
        $title_by_id = array(
            SectionIdentity::SEC_PROPERTY_FAQ      => __( 'Buyer Questions', 'havenlytics' ),
            SectionIdentity::SEC_PROPERTY_REPEATER => __( 'Neighborhood Highlights', 'havenlytics' ),
            SectionIdentity::SEC_PROPERTY_AGENTS   => __( 'Property Agents', 'havenlytics' ),
            SectionIdentity::SEC_PROPERTY_FEATURES => __( 'Property Amenities', 'havenlytics' ),
            'sec_faq'                              => __( 'Buyer Questions', 'havenlytics' ),
            'sec_repeater'                         => __( 'Neighborhood Highlights', 'havenlytics' ),
            'sec_agents'                           => __( 'Property Agents', 'havenlytics' ),
        );

        foreach ( $sections as $section_id => &$section ) {
            if ( isset( $title_by_id[ $section_id ] ) ) {
                $section['title'] = $title_by_id[ $section_id ];
            }

            foreach ( $section['fields'] ?? array() as $field_index => $field ) {
                if ( ! is_array( $field ) ) {
                    continue;
                }

                $group_type = sanitize_key( (string) ( $field['group_type'] ?? '' ) );
                if ( '' === $group_type ) {
                    $group_type = sanitize_key( (string) ( $field['type'] ?? '' ) );
                }

                $label = $this->get_import_default_section_label( $group_type );
                if ( '' === $label ) {
                    continue;
                }

                $section['fields'][ $field_index ]['group_name'] = $label;
            }
        }
        unset( $section );

        return $sections;
    }

    /**
     * @param string $group_type faq|repeater|agents|features.
     * @return string
     */
    private function get_import_default_section_label( string $group_type ): string {
        $labels = array(
            'faq'      => __( 'Buyer Questions', 'havenlytics' ),
            'repeater' => __( 'Neighborhood Highlights', 'havenlytics' ),
            'agents'   => __( 'Property Agents', 'havenlytics' ),
            'features' => __( 'Property Amenities', 'havenlytics' ),
        );

        return $labels[ $group_type ] ?? '';
    }

    /**
     * Keep Property Features (9) above Listing Agents (10) in builder/metabox order.
     *
     * @return void
     */
    private function ensure_import_tail_section_order(): void {
        $storage_key = 'hvnly_property_builder.sections';
        $sections    = get_option( $storage_key, array() );

        if ( ! is_array( $sections ) || empty( $sections ) ) {
            return;
        }

        $changed = false;

        foreach ( $sections as $section_key => &$section ) {
            $has_features = false;
            $has_agents   = false;

            foreach ( $section['fields'] ?? array() as $field ) {
                $group_type = sanitize_key( (string) ( $field['group_type'] ?? '' ) );
                if ( 'features' === $group_type ) {
                    $has_features = true;
                }
                if ( 'agents' === $group_type ) {
                    $has_agents = true;
                }
            }

            if ( $has_features && 9 !== (int) ( $section['order'] ?? 99 ) ) {
                $section['order'] = 9;
                $changed          = true;
            }

            if ( $has_agents && 10 !== (int) ( $section['order'] ?? 99 ) ) {
                $section['order'] = 10;
                $changed          = true;
            }
        }
        unset( $section );

        if ( ! $changed ) {
            return;
        }

        update_option( $storage_key, $sections );
        wp_cache_delete( 'hvnly_standardized_field_names' );
        wp_cache_delete( $storage_key, 'options' );
    }

    private function maybe_initialize_card_builder_for_first_import( int $imported ): void {
        if ( $imported > 0 ) {
            return;
        }

        if ( get_option( $this->import_option, false ) ) {
            return;
        }

        $this->reset_property_card_builder();

        if ( $this->initialize_property_card_builder_defaults() ) {
            $this->log_debug( 'Property card builder reset to default layout for first import.' );
        } else {
            $this->log_error( 'Failed to seed default property card builder on first import.' );
        }
    }

    private function clear_builder_transients( string $pattern ): void {
        global $wpdb;
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
                $wpdb->esc_like( '_transient_hvnly_' . $pattern ) . '%',
                $wpdb->esc_like( '_transient_timeout_hvnly_' . $pattern ) . '%'
            )
        );
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
                $wpdb->esc_like( '_transient_' . $pattern ) . '%',
                $wpdb->esc_like( '_transient_timeout_' . $pattern ) . '%'
            )
        );
    }

    /**
     * AJAX handler for import
     */
    public function ajax_import_properties() {
        try {
            if ( function_exists( 'ignore_user_abort' ) ) {
                ignore_user_abort( true );
            }

            if ( function_exists( 'set_time_limit' ) ) {
                @set_time_limit( 180 );
            }

            if ( function_exists( 'ini_set' ) ) {
                @ini_set( 'max_execution_time', '180' );
            }

            if ( function_exists( 'wp_raise_memory_limit' ) ) {
                wp_raise_memory_limit( 'admin' );
            }

            if ( $this->import_running ) {
                wp_send_json_error( 'Import already in progress.' );
                return;
            }

            if ( ! check_ajax_referer( 'hvnly_import_nonce', 'nonce', false ) ) {
                wp_send_json_error( 'Security check failed.' );
                return;
            }

            if ( ! isset( $_POST['csrf_token'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['csrf_token'] ) ), 'hvnly_import_csrf' ) ) {
                wp_send_json_error( 'CSRF validation failed.' );
                return;
            }

            if ( ! current_user_can( 'manage_options' ) ) {
                wp_send_json_error( 'Insufficient permissions.' );
                return;
            }

            $this->import_running = true;
            $this->optimize_for_bulk_import();

            $batch           = absint( filter_input( INPUT_POST, 'batch', FILTER_VALIDATE_INT ) ?: 0 );
            $imported        = absint( filter_input( INPUT_POST, 'imported', FILTER_VALIDATE_INT ) ?: 0 );
            $demo_import_limit = $this->get_demo_import_limit();
            $total_to_import   = min( absint( filter_input( INPUT_POST, 'total_to_import', FILTER_VALIDATE_INT ) ?: $demo_import_limit ), $demo_import_limit );

            $raw_options = $this->get_import_options_from_request();
            $options     = $this->sanitize_import_options( $raw_options );

            $new_session = ! empty( $_POST['new_session'] );
            $session_id  = isset( $_POST['session_id'] )
                ? sanitize_text_field( wp_unslash( (string) $_POST['session_id'] ) )
                : '';

            $run_state = $this->resolve_import_run_state(
                $batch,
                $imported,
                $total_to_import,
                $options,
                $session_id,
                $new_session
            );

            $session_id      = (string) ( $run_state['session_id'] ?? '' );
            $imported        = absint( $run_state['imported'] ?? 0 );
            $batch           = absint( $run_state['current_batch'] ?? $imported );
            $total_to_import = absint( $run_state['total_to_import'] ?? $total_to_import );
            $options         = ! empty( $run_state['import_options'] ) && is_array( $run_state['import_options'] )
                ? $run_state['import_options']
                : $options;

            if ( 0 === $imported && 0 === $batch ) {
                $this->persist_import_map_settings( $options );
            }

            $result = $this->process_import_batch( $batch, $imported, $total_to_import, $options, $session_id );

            $result['session_id'] = $session_id;

            $this->restore_wordpress_state();
            $this->import_running = false;

            wp_send_json_success( $result );

        } catch ( \Throwable $e ) {
            // Catch \Throwable (not just \Exception) so a TypeError / missing class /
            // OOM inside property creation or a seeder returns a clean JSON error the
            // client can handle, instead of an HTML fatal that hangs the wizard.
            $this->restore_wordpress_state();
            $this->import_running = false;
            $this->log_error( 'Import AJAX error: ' . $e->getMessage() );
            wp_send_json_error( [ 'message' => $e->getMessage(), 'can_retry' => true ] );
        }
    }

    private function optimize_for_bulk_import() {
        $this->original_cache_state  = wp_suspend_cache_invalidation( false );
        $this->original_term_state   = wp_defer_term_counting( false );
        $this->original_comment_state = wp_defer_comment_counting( false );
        wp_suspend_cache_invalidation( true );
        wp_defer_term_counting( true );
        wp_defer_comment_counting( true );
        if ( $this->is_heavy_theme() && function_exists( 'wp_raise_memory_limit' ) ) {
            wp_raise_memory_limit( 'admin' );
        }
        if ( ! $this->import_image_filters_active ) {
            add_filter( 'intermediate_image_sizes_advanced', array( $this, 'disable_import_image_subsizes' ), 999, 2 );
            add_filter( 'big_image_size_threshold', array( $this, 'disable_big_image_threshold' ), 999 );
            $this->import_image_filters_active = true;
        }
    }

    /**
     * Skip generating intermediate sizes during import sideloads (major speed win).
     *
     * @param array<string, mixed> $sizes    Registered sizes.
     * @param array<string, mixed> $metadata Attachment metadata.
     * @return array<string, mixed>
     */
    public function disable_import_image_subsizes( $sizes, $metadata ) {
        unset( $metadata );
        return array();
    }

    /**
     * Disable -scaled.jpg generation during import sideloads.
     *
     * @return false
     */
    public function disable_big_image_threshold() {
        return false;
    }

    private function restore_wordpress_state() {
        wp_suspend_cache_invalidation( $this->original_cache_state );
        wp_defer_term_counting( $this->original_term_state );
        wp_defer_comment_counting( $this->original_comment_state );
        if ( $this->import_image_filters_active ) {
            remove_filter( 'intermediate_image_sizes_advanced', array( $this, 'disable_import_image_subsizes' ), 999 );
            remove_filter( 'big_image_size_threshold', array( $this, 'disable_big_image_threshold' ), 999 );
            $this->import_image_filters_active = false;
        }
    }

    public function ajax_cancel_import() {
        try {
            if ( $this->import_running ) {
                $this->restore_wordpress_state();
                $this->import_running = false;
            }
            if ( ! check_ajax_referer( 'hvnly_import_nonce', 'nonce', false ) ) {
                wp_send_json_error( 'Security check failed.' );
                return;
            }
            if ( ! isset( $_POST['csrf_token'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['csrf_token'] ) ), 'hvnly_import_csrf' ) ) {
                wp_send_json_error( 'CSRF validation failed.' );
                return;
            }
            if ( ! current_user_can( 'manage_options' ) ) {
                wp_send_json_error( 'Insufficient permissions.' );
                return;
            }

            // Keep session data so Resume can continue; mark cancelled so in-flight
            // batches stop before creating the next property.
            $state = $this->load_import_run_state_raw();
            if ( ! empty( $state['session_id'] ) ) {
                $this->update_import_run_state(
                    array(
                        'status' => 'cancelled',
                    )
                );
            } else {
                $this->clear_import_run_state();
            }

            $this->log_error( 'Import cancelled by user at ' . current_time( 'mysql' ) );
            wp_send_json_success( [ 'message' => 'Import cancelled successfully.' ] );
        } catch ( \Exception $e ) {
            $this->log_error( 'Cancel import error: ' . $e->getMessage() );
            wp_send_json_error( $e->getMessage() );
        }
    }

    /**
     * Persist map provider and default coordinates from the import wizard (batch 0 only).
     *
     * @param array $options Sanitized import options.
     */
    /**
     * Save partial map settings via SettingsManager (canonical store).
     *
     * @param array $partial Settings to merge.
     * @return array|null Updated map settings or null if unavailable.
     */
    private function save_map_settings_partial( array $partial ) {
        if ( ! class_exists( '\HvnlyNab\Core\SettingsManager' ) ) {
            return null;
        }

        return \HvnlyNab\Core\SettingsManager::get_instance()->update_map_settings( $partial );
    }

    private function persist_import_map_settings( array $options ) {
        $partial = array();

        if ( ! empty( $options['map_provider'] ) ) {
            $partial['hvnly_map_provider'] = sanitize_text_field( (string) $options['map_provider'] );
        }

        if ( isset( $options['map_latitude'] ) && '' !== (string) $options['map_latitude'] ) {
            $partial['hvnly_default_lat'] = (string) floatval( $options['map_latitude'] );
        }

        if ( isset( $options['map_longitude'] ) && '' !== (string) $options['map_longitude'] ) {
            $partial['hvnly_default_lng'] = (string) floatval( $options['map_longitude'] );
        }

        if ( ! empty( $partial ) ) {
            $this->save_map_settings_partial( $partial );
        }
    }

    private function sanitize_import_options( $options ) {
        $sanitized = [];
        if ( ! is_array( $options ) ) {
            return $sanitized;
        }
        $boolean_keys = array( 'email_notifications', 'include_images', 'enable_cache_after_import', 'location_modified', 'coordinates_user_entered' );

        foreach ( $options as $key => $value ) {
            $safe_key = sanitize_key( $key );
            if ( is_array( $value ) ) {
                $sanitized[ $safe_key ] = $this->sanitize_import_options( $value );
            } elseif ( in_array( $safe_key, $boolean_keys, true ) ) {
                $sanitized[ $safe_key ] = rest_sanitize_boolean( $value );
            } elseif ( is_numeric( $value ) ) {
                $sanitized[ $safe_key ] = floatval( $value );
            } elseif ( is_bool( $value ) || 'true' === $value || 'false' === $value ) {
                $sanitized[ $safe_key ] = rest_sanitize_boolean( $value );
            } elseif ( filter_var( $value, FILTER_VALIDATE_URL ) ) {
                $sanitized[ $safe_key ] = esc_url_raw( $value );
            } elseif ( is_string( $value ) ) {
                $sanitized[ $safe_key ] = sanitize_text_field( $value );
            } else {
                $sanitized[ $safe_key ] = $value;
            }
        }
        return $sanitized;
    }

    /**
     * Read import wizard options from POST (filter_input can fail for nested jQuery payloads).
     *
     * @return array<string, mixed>
     */
    private function get_import_options_from_request(): array {
        $raw = filter_input( INPUT_POST, 'options', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY );

        if ( ! is_array( $raw ) ) {
            // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in ajax_import_properties().
            if ( isset( $_POST['options'] ) && is_array( $_POST['options'] ) ) {
                // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
                $raw = wp_unslash( $_POST['options'] );
            } else {
                $raw = array();
            }
        }

        if ( is_array( $raw ) ) {
            // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in ajax_import_properties().
            if ( ! array_key_exists( 'email_notifications', $raw ) && isset( $_POST['email_notifications'] ) ) {
                // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
                $raw['email_notifications'] = wp_unslash( $_POST['email_notifications'] );
            }
        }

        return is_array( $raw ) ? $raw : array();
    }

    /**
     * Load raw import run option row.
     *
     * @return array<string, mixed>
     */
    private function load_import_run_state_raw(): array {
        $state = get_option( $this->import_run_option, array() );

        return is_array( $state ) ? $state : array();
    }

    /**
     * Build a fresh running import session.
     *
     * @param string               $session_id      Session UUID.
     * @param int                  $total_to_import Target property count.
     * @param array<string, mixed> $options         Sanitized import options.
     * @return array<string, mixed>
     */
    private function build_fresh_import_run_state( string $session_id, int $total_to_import, array $options ): array {
        return array(
            'session_id'          => $session_id,
            'imported'            => 0,
            'current_batch'       => 0,
            'total_to_import'     => max( 1, min( $this->get_demo_import_limit(), $total_to_import ) ),
            'status'              => 'running',
            'prep_step'           => 0,
            'media_cache'         => array(),
            'user_id'             => get_current_user_id(),
            'email_notifications' => $this->import_wants_email_notification( $options ),
            'import_options'      => $options,
            'updated_at'          => time(),
        );
    }

    /**
     * Resolve the authoritative import session — server state wins over client hints.
     *
     * @param int                  $client_batch    Client batch index.
     * @param int                  $client_imported Client imported count.
     * @param int                  $total_to_import Requested total.
     * @param array<string, mixed> $options         Sanitized import options.
     * @param string               $client_session  Client session UUID.
     * @param bool                 $new_session     Whether the client is starting a new run.
     * @return array<string, mixed>
     */
    private function resolve_import_run_state(
        int $client_batch,
        int $client_imported,
        int $total_to_import,
        array $options,
        string $client_session,
        bool $new_session
    ): array {
        $stored = $this->load_import_run_state_raw();

        if ( $new_session && 0 === $client_batch && 0 === $client_imported ) {
            if ( $this->is_import_wizard_locked() ) {
                throw new \RuntimeException(
                    esc_html__( 'Demo import is already complete or properties already exist. Starting a new import is blocked to prevent duplicates.', 'havenlytics' )
                );
            }

            $stored_status   = (string) ( $stored['status'] ?? '' );
            $stored_imported = absint( $stored['imported'] ?? 0 );
            if (
                ! empty( $stored['session_id'] )
                && $stored_imported > 0
                && in_array( $stored_status, array( 'running', 'cancelled', 'paused' ), true )
            ) {
                throw new \RuntimeException(
                    esc_html__( 'An interrupted import session already exists. Please resume it instead of starting a new import.', 'havenlytics' )
                );
            }

            $state = $this->build_fresh_import_run_state( wp_generate_uuid4(), $total_to_import, $options );
            update_option( $this->import_run_option, $state, false );

            return $state;
        }

        if (
            ! empty( $stored['session_id'] )
            && in_array( (string) ( $stored['status'] ?? '' ), array( 'running', 'cancelled', 'paused' ), true )
            && ( '' === $client_session || hash_equals( (string) $stored['session_id'], $client_session ) )
        ) {
            // Resume after cancel/pause: mark running again for this request.
            if ( 'running' !== ( $stored['status'] ?? '' ) ) {
                $this->update_import_run_state( array( 'status' => 'running' ) );
                $stored['status'] = 'running';
            }

            return $stored;
        }

        if ( 0 === $client_batch && 0 === $client_imported ) {
            if ( $this->is_import_wizard_locked() ) {
                throw new \RuntimeException(
                    esc_html__( 'Demo import is already complete or properties already exist. Starting a new import is blocked to prevent duplicates.', 'havenlytics' )
                );
            }

            $state = $this->build_fresh_import_run_state( wp_generate_uuid4(), $total_to_import, $options );
            update_option( $this->import_run_option, $state, false );

            return $state;
        }

        throw new \RuntimeException( esc_html__( 'No active import session. Please start or resume the import.', 'havenlytics' ) );
    }

    /**
     * Merge partial updates into the active import session.
     *
     * @param array<string, mixed> $partial Partial state.
     * @return void
     */
    private function update_import_run_state( array $partial ): void {
        $state = $this->load_import_run_state_raw();

        if ( empty( $state['session_id'] ) ) {
            return;
        }

        $state = array_merge( $state, $partial );
        $state['updated_at'] = time();

        update_option( $this->import_run_option, $state, false );
    }

    /**
     * Resume payload for the import wizard admin screen.
     *
     * @return array<string, mixed>
     */
    private function get_client_resume_payload(): array {
        $state = $this->load_import_run_state_raw();

        $status = (string) ( $state['status'] ?? '' );
        if (
            empty( $state['session_id'] )
            || ! in_array( $status, array( 'running', 'cancelled', 'paused' ), true )
            || empty( $state['import_options'] )
            || ! is_array( $state['import_options'] )
        ) {
            return array( 'can_resume' => false );
        }

        $imported = absint( $state['imported'] ?? 0 );
        $total    = absint( $state['total_to_import'] ?? 0 );

        if ( $total <= 0 || $imported >= $total ) {
            return array( 'can_resume' => false );
        }

        return array(
            'can_resume'        => true,
            'session_id'        => (string) $state['session_id'],
            'imported'          => $imported,
            'current_batch'     => absint( $state['current_batch'] ?? $imported ),
            'total_to_import'   => $total,
            'import_options'    => $state['import_options'],
        );
    }

    /**
     * Find an already-imported property for a session/index pair (retry idempotency).
     *
     * @param string $session_id Import session UUID.
     * @param int    $index      Zero-based import index.
     * @return int Post ID or 0.
     */
    private function find_property_by_import_index( string $session_id, int $index ): int {
        if ( '' === $session_id || $index < 0 ) {
            return 0;
        }

        $query = new \WP_Query(
            array(
                'post_type'              => $this->post_type,
                'post_status'            => 'any',
                'posts_per_page'         => 1,
                'fields'                 => 'ids',
                'no_found_rows'          => true,
                'update_post_meta_cache' => false,
                'update_post_term_cache' => false,
                'meta_query'             => array(
                    'relation' => 'AND',
                    array(
                        'key'   => self::IMPORT_SESSION_META,
                        'value' => $session_id,
                    ),
                    array(
                        'key'   => self::IMPORT_INDEX_META,
                        'value' => (string) $index,
                    ),
                ),
            )
        );

        if ( ! $query->have_posts() ) {
            return 0;
        }

        return absint( $query->posts[0] );
    }

    /**
     * @return array<string, mixed>
     */
    private function get_import_run_state(): array {
        $state = $this->load_import_run_state_raw();

        return array(
            'user_id'             => isset( $state['user_id'] ) ? absint( $state['user_id'] ) : 0,
            'email_notifications' => ! empty( $state['email_notifications'] ),
            'session_id'          => isset( $state['session_id'] ) ? (string) $state['session_id'] : '',
            'imported'            => isset( $state['imported'] ) ? absint( $state['imported'] ) : 0,
            'current_batch'       => isset( $state['current_batch'] ) ? absint( $state['current_batch'] ) : 0,
            'total_to_import'     => isset( $state['total_to_import'] ) ? absint( $state['total_to_import'] ) : 0,
            'status'              => isset( $state['status'] ) ? (string) $state['status'] : '',
            'import_options'      => ( isset( $state['import_options'] ) && is_array( $state['import_options'] ) )
                ? $state['import_options']
                : array(),
        );
    }

    /**
     * @return void
     */
    private function clear_import_run_state(): void {
        delete_option( $this->import_run_option );
    }

    /**
     * Whether the active import session was cancelled.
     *
     * Reloads option state so concurrent cancel AJAX is visible mid-batch.
     *
     * @return bool
     */
    private function is_import_run_cancelled(): bool {
        $state = $this->load_import_run_state_raw();

        return ! empty( $state['session_id'] ) && 'cancelled' === ( $state['status'] ?? '' );
    }

    /**
     * Whether the import wizard requested a success email.
     *
     * @param array<string, mixed> $options Sanitized import options.
     * @return bool
     */
    private function import_wants_email_notification( array $options ): bool {
        if ( array_key_exists( 'email_notifications', $options ) ) {
            return (bool) rest_sanitize_boolean( $options['email_notifications'] );
        }

        if ( class_exists( '\HvnlyNab\Email\EmailSettings' ) ) {
            return \HvnlyNab\Email\EmailSettings::import_wizard_notify_default();
        }

        return true;
    }

    /**
     * Apply global cache toggle after a successful import completes.
     *
     * @param array $options Sanitized import options.
     */
    private function apply_import_cache_preference( array $options ) {
        $enabled = ! empty( $options['enable_cache_after_import'] );
        if ( function_exists( 'hvnly_sync_cache_enabled_setting' ) ) {
            hvnly_sync_cache_enabled_setting( $enabled );
        } else {
            update_option( 'hvnly_cache_enabled', $enabled ? 1 : 0, false );
        }
    }

    /**
     * Turn on Contact Agent after a successful demo import so inquiry forms appear on listings.
     *
     * @return void
     */
    private function enable_contact_agent_after_import(): void {
        $storage_key  = 'hvnly_plugin_settings';
        $all_settings = get_option( $storage_key, array() );

        if ( ! is_array( $all_settings ) ) {
            $all_settings = array();
        }

        if ( ! isset( $all_settings['contact-agent'] ) || ! is_array( $all_settings['contact-agent'] ) ) {
            $all_settings['contact-agent'] = array();
        }

        $all_settings['contact-agent']['hvnly_contact_agent_enabled'] = true;
        update_option( $storage_key, $all_settings );

        /**
         * Fires when Contact Agent is enabled after property import.
         *
         * @since 3.0.3
         *
         * @param array<string, mixed> $contact_settings Contact Agent settings group.
         */
        do_action( 'hvnly_settings_updated', 'contact-agent', $all_settings['contact-agent'] );

        if ( class_exists( '\HvnlyNab\ContactAgent\ContactAgentSettings' ) ) {
            \HvnlyNab\ContactAgent\ContactAgentSettings::clear_cache( 'contact-agent' );
        }
    }

    /**
     * Apply Post name permalinks and rebuild rewrite rules after import completes.
     *
     * Prevents 404s on property, agent, and agency URLs until permalinks are manually saved.
     *
     * @return void
     */
    private function finalize_permalinks_after_import(): void {
        if ( ! class_exists( '\HvnlyNab\Core\RewriteRulesManager' ) ) {
            return;
        }

        $needs_postname = ! \HvnlyNab\Core\RewriteRulesManager::permalink_structure_is_postname();

        \HvnlyNab\Core\RewriteRulesManager::finalize_permalinks( $needs_postname );
    }

    /**
     * Send import success email when the wizard option is enabled.
     *
     * @param int                  $import_count Imported property count.
     * @param array<string, mixed> $options      Sanitized import options.
     * @return void
     */
    private function maybe_send_import_success_email( int $import_count, array $options ): void {
        $run_state   = $this->get_import_run_state();
        $wants_email = $this->import_wants_email_notification( $options ) || ! empty( $run_state['email_notifications'] );

        if ( ! $wants_email ) {
            $this->clear_import_run_state();
            return;
        }

        $this->log_error( 'Import success email requested by wizard.' );

        if ( ! function_exists( 'hvnly_send_property_import_success_email' ) ) {
            $this->clear_import_run_state();
            return;
        }

        $user_id = absint( $run_state['user_id'] );
        if ( $user_id <= 0 ) {
            $user_id = get_current_user_id();
        }

        $recipient_id = $user_id > 0 ? $user_id : null;

        // Defer until after bulk-import optimizations are restored so wp_mail runs in a normal WP state.
        add_action(
            'shutdown',
            function () use ( $import_count, $recipient_id ) {
                if ( function_exists( 'hvnly_load_template_functions' ) ) {
                    hvnly_load_template_functions();
                }

                $result = hvnly_send_property_import_success_email( $import_count, $recipient_id, true );

                if ( is_wp_error( $result ) ) {
                    $this->log_error( 'Import success email failed: ' . $result->get_error_message() );
                }

                $this->clear_import_run_state();
            },
            20
        );
    }

    private function validate_numeric( $value, $min = 0, $max = PHP_INT_MAX ) {
        $value = floatval( $value );
        return max( $min, min( $max, $value ) );
    }

    /**
     * Properties per AJAX batch — kept small to avoid gateway timeouts during image sideloads.
     *
     * @param array<string, mixed> $options Sanitized import options.
     * @param int                  $batch   Current batch index.
     * @param int                  $imported Properties already imported in this run.
     * @return int
     */
    private function get_import_batch_size( array $options, int $batch, int $imported ): int {
        $include_images = ! isset( $options['include_images'] ) || ! empty( $options['include_images'] );

        // Batch 0 also resets card builder and imports the demo agent ecosystem.
        if ( 0 === $batch && 0 === $imported ) {
            return 1;
        }

        if ( $include_images ) {
            return 1;
        }

        return $this->is_heavy_theme() ? 2 : 2;
    }

    /**
     * Process import in batches
     *
     * @param int                  $batch           Current batch index (legacy; cursor is $imported).
     * @param int                  $imported        Authoritative imported count / next index.
     * @param int                  $total_to_import Total target.
     * @param array<string, mixed> $options         Sanitized import options.
     * @param string               $session_id      Import session UUID.
     * @return array<string, mixed>
     */
    private function process_import_batch( $batch = 0, $imported = 0, $total_to_import = 25, $options = [], $session_id = '' ) {
        try {
            $run_state = $this->load_import_run_state_raw();
            $prep_step = absint( $run_state['prep_step'] ?? 0 );

            if ( $imported > 0 ) {
                $prep_step = 2;
            }

            if ( 0 === (int) $imported && $prep_step < 2 ) {
                return $this->process_import_prep_step( $prep_step, $options, (int) $total_to_import );
            }

            if ( 0 === (int) $batch && 0 === (int) $imported ) {
                PropertyLocationTermImageSeeder::reset_progress();
            }
            $this->ensure_taxonomies_exist();
            $this->maybe_seed_location_term_images_batch( $options, (int) $imported );
            $this->maybe_seed_property_type_term_images_batch( $options, (int) $imported );
            $this->ensure_demo_agent_ecosystem( $options, (int) $imported, false );
            $this->maybe_backfill_demo_agent_images_batch( $options, 2 );
            $this->check_rate_limit();

            $batch_size = $this->get_import_batch_size( $options, (int) $batch, (int) $imported );
            $demo_properties = DemoData::get_demo_properties_data();
            $total_properties_available = count( $demo_properties );
            $total_to_import = min( (int) $total_to_import, $total_properties_available );
            $remaining = $total_to_import - $imported;

            if ( $remaining <= 0 ) {
                update_option( $this->import_option, true );
                update_option( $this->count_option, $total_to_import );
                $this->apply_import_cache_preference( $options );
                $this->enable_contact_agent_after_import();
                $this->finalize_permalinks_after_import();
                $this->maybe_send_import_success_email( $total_to_import, $options );

                $this->update_import_run_state(
                    array(
                        'imported'      => $total_to_import,
                        'current_batch' => $total_to_import,
                        'status'        => 'complete',
                    )
                );

                return [
                    'batch'      => $batch,
                    'next_batch' => $batch,
                    'imported'   => $total_to_import,
                    'total'      => $total_to_import,
                    'complete'   => true,
                    'percent'    => 100,
                    'message'    => sprintf( 'Import complete! Imported %d properties.', $total_to_import ),
                    'errors'     => null,
                ];
            }

            $start = max( 0, (int) $imported );
            $end   = min( $start + $batch_size, $total_to_import );

            $departments     = isset( $options['departments'] ) && is_array( $options['departments'] ) ? $options['departments'] : [];
            $department_keys = ! empty( $departments ) ? array_keys( $departments ) : [ 'sale' ];

            $imported_count = 0;
            $errors = [];

            for ( $i = $start; $i < $end; $i++ ) {
                if ( $this->is_import_run_cancelled() ) {
                    $this->update_import_run_state(
                        array(
                            'imported'      => $imported + $imported_count,
                            'current_batch' => $imported + $imported_count,
                            'status'        => 'cancelled',
                        )
                    );

                    return [
                        'batch'      => $imported + $imported_count,
                        'next_batch' => $imported + $imported_count,
                        'imported'   => $imported + $imported_count,
                        'total'      => $total_to_import,
                        'complete'   => false,
                        'cancelled'  => true,
                        'percent'    => $total_to_import > 0 ? round( ( ( $imported + $imported_count ) / $total_to_import ) * 100 ) : 0,
                        'message'    => sprintf( 'Import cancelled after %d of %d properties.', $imported + $imported_count, $total_to_import ),
                        'errors'     => ! empty( $errors ) ? $errors : null,
                    ];
                }

                try {
                    $existing_id = $this->find_property_by_import_index( $session_id, $i );
                    if ( $existing_id > 0 ) {
                        ++$imported_count;
                        continue;
                    }

                    $property_index = $i % $total_properties_available;
                    $dept_key       = $department_keys[ $i % count( $department_keys ) ];
                    $dept_data      = isset( $departments[ $dept_key ] ) && is_array( $departments[ $dept_key ] ) ? $departments[ $dept_key ] : [];

                    $property_data = $demo_properties[ $property_index ];

                    $property_data['department'] = sanitize_key( $dept_key );
                    $property_data['demo_property_index'] = $property_index;
                    $property_data['import_session_id'] = $session_id;
                    $property_data['import_index'] = $i;
                    $property_data = $this->merge_taxonomy_data( $property_data, $dept_data, $demo_properties, $property_index );
                    $property_data = $this->merge_common_options( $property_data, $options );
                    if ( ! $this->wizard_provided_custom_location( $options ) ) {
                        // No full custom location: give each property its own diverse
                        // demo location, then overlay only the individual fields the
                        // user explicitly changed. Empty inputs never replace valid
                        // demo coordinates (partial override is non-destructive).
                        $property_data = $this->apply_diverse_import_location( $property_data, $i );
                        $property_data = $this->apply_partial_location_override( $property_data, $options );
                    }

                    $this->log_import_location_trace( $i, $property_index, $property_data, $options );

                    $post_id = $this->create_demo_property( $property_data, $options );

                    if ( is_wp_error( $post_id ) ) {
                        throw new \Exception( esc_html( $post_id->get_error_message() ) );
                    }

                    ++$imported_count;

                } catch ( \Exception $e ) {
                    $errors[] = sprintf( 'Property %d failed: %s', $i + 1, $e->getMessage() );
                    $this->log_error( 'Failed to import property: ' . $e->getMessage() );
                    if ( count( $errors ) >= 3 ) {
                        throw new \Exception( esc_html( sprintf( 'Too many errors (%d). Last error: %s', count( $errors ), (string) end( $errors ) ) ) );
                    }
                    break;
                }
            }

            $new_total_imported = $imported + $imported_count;
            $complete = ( $new_total_imported >= $total_to_import );

            if ( $complete ) {
                if ( $new_total_imported > $total_to_import ) {
                    $new_total_imported = $total_to_import;
                }

                // Mark complete first so the client gets a reliable success response.
                // Heavy leftover image finishers are intentionally skipped here — they
                // already run incrementally during batches and can time out on Playground.
                update_option( $this->import_option, true );
                update_option( $this->count_option, $new_total_imported );
                $this->update_rate_limit();
                $this->apply_import_cache_preference( $options );
                $this->enable_contact_agent_after_import();
                $this->maybe_send_import_success_email( $new_total_imported, $options );
                $this->maybe_backfill_demo_agent_assignments();
                $this->finalize_permalinks_after_import();

                $this->update_import_run_state(
                    array(
                        'imported'      => $new_total_imported,
                        'current_batch' => $new_total_imported,
                        'status'        => 'complete',
                    )
                );

                return [
                    'batch'      => $new_total_imported,
                    'next_batch' => $new_total_imported,
                    'imported'   => $new_total_imported,
                    'total'      => $total_to_import,
                    'complete'   => true,
                    'percent'    => 100,
                    'message'    => sprintf( 'Import complete! Imported %d properties.', $new_total_imported ),
                    'errors'     => ! empty( $errors ) ? $errors : null,
                ];
            }

            $this->update_import_run_state(
                array(
                    'imported'      => $new_total_imported,
                    'current_batch' => $new_total_imported,
                    'status'        => 'running',
                    'prep_step'     => 2,
                )
            );

            return [
                'batch'      => $new_total_imported,
                'next_batch' => $new_total_imported,
                'imported'   => $new_total_imported,
                'total'      => $total_to_import,
                'complete'   => false,
                'percent'    => round( ( $new_total_imported / $total_to_import ) * 100 ),
                'message'    => sprintf( 'Imported %d of %d properties...', $new_total_imported, $total_to_import ),
                'errors'     => ! empty( $errors ) ? $errors : null,
            ];

        } catch ( \Throwable $e ) {
            // Re-thrown as \Throwable so the AJAX handler's catch converts any fatal
            // (not only \Exception) into a JSON error response.
            $this->log_error( 'Batch processing error: ' . $e->getMessage() );
            throw $e;
        }
    }

    private function log_debug( $message, $data = null ) {
        if ( ! $this->debug_mode ) {
            return;
        }
        $log = $message;
        if ( null !== $data ) {
            $log .= ': ' . wp_json_encode( $data );
        }
        if ( function_exists( 'hvnly_debug_log' ) ) {
            hvnly_debug_log( $log, 'Havenlytics Import' );
        }
    }

    private function check_memory_usage() {
        $memory_limit = $this->get_memory_limit_bytes();
        $current_usage = memory_get_usage( true );
        $available = $memory_limit - $current_usage;
        if ( $available < 10 * 1024 * 1024 && function_exists( 'gc_collect_cycles' ) ) {
            gc_collect_cycles();
        }
    }

    private function get_memory_limit_bytes(): int {
        $memory_limit = ini_get( 'memory_limit' );
        if ( preg_match( '/^(\d+)(.)$/', $memory_limit, $matches ) ) {
            if ( 'G' === $matches[2] ) {
                return (int) $matches[1] * 1024 * 1024 * 1024;
            } elseif ( 'M' === $matches[2] ) {
                return (int) $matches[1] * 1024 * 1024;
            } elseif ( 'K' === $matches[2] ) {
                return (int) $matches[1] * 1024;
            }
        }
        return (int) $memory_limit;
    }

    private function merge_taxonomy_data( $property_data, $dept_data, $demo_properties, $property_index ) {
        $taxonomy_map = [
            'property_types'   => [ 'types', 'property_types' ],
            'property_status'  => [ 'status', 'property_status' ],
            'badges'           => [ 'badges' ],
            'tags'             => [ 'tags' ],
            'locations'        => [ 'locations' ],
            'features'         => [ 'features' ],
        ];

        foreach ( $taxonomy_map as $target => $sources ) {
            $found = false;
            foreach ( $sources as $source ) {
                if ( ! empty( $dept_data[ $source ] ) ) {
                    $values = is_array( $dept_data[ $source ] ) ? $dept_data[ $source ] : [ $dept_data[ $source ] ];
                    $values = array_filter( $values );
                    if ( ! empty( $values ) ) {
                        $property_data[ $target ] = array_map( 'sanitize_text_field', $values );
                        $found = true;
                        break;
                    }
                }
            }
            if ( ! $found && isset( $demo_properties[ $property_index ][ $target ] ) ) {
                $values = $demo_properties[ $property_index ][ $target ];
                $property_data[ $target ] = is_array( $values ) ? array_map( 'sanitize_text_field', $values ) : [ $values ];
            }
            if ( ! isset( $property_data[ $target ] ) ) {
                $property_data[ $target ] = [];
            }
        }
        return $property_data;
    }

    /**
     * Whether a Quick Property Setup option value should override demo data.
     *
     * @param mixed $value Option value.
     * @return bool
     */
    private function import_option_has_value( $value ): bool {
        if ( null === $value || false === $value ) {
            return false;
        }

        if ( is_string( $value ) ) {
            return '' !== trim( $value );
        }

        if ( is_bool( $value ) ) {
            return true;
        }

        if ( is_numeric( $value ) ) {
            return true;
        }

        return ! empty( $value );
    }

    /**
     * Whether a map coordinate is valid for persistence.
     *
     * @param mixed $value Coordinate value.
     * @return bool
     */
    private function import_map_coordinate_has_value( $value ): bool {
        if ( null === $value || false === $value ) {
            return false;
        }

        $coordinate = trim( (string) $value );

        if ( '' === $coordinate || '0' === $coordinate || '0.0' === $coordinate ) {
            return false;
        }

        return is_numeric( $coordinate );
    }

    /**
     * Whether a complete map location is safe to persist.
     *
     * @param array<string, string> $location Normalized map location.
     * @return bool
     */
    private function is_valid_map_location( array $location ): bool {
        return $this->import_option_has_value( $location['map_address'] )
            && $this->import_map_coordinate_has_value( $location['map_latitude'] )
            && $this->import_map_coordinate_has_value( $location['map_longitude'] );
    }

    /**
     * Normalize map location fields for import persistence.
     *
     * @param string $map_address  Map address.
     * @param string $map_latitude Map latitude.
     * @param string $map_longitude Map longitude.
     * @return array<string, string>
     */
    private function normalize_map_location( string $map_address, string $map_latitude, string $map_longitude ): array {
        return array(
            'map_address'   => sanitize_text_field( $map_address ),
            'map_latitude'  => sanitize_text_field( $map_latitude ),
            'map_longitude' => sanitize_text_field( $map_longitude ),
        );
    }

    /**
     * Resolve import map location using custom, UK, then London fallback priority.
     *
     * @param array<string, mixed> $data    Property payload.
     * @param array<string, mixed> $options Sanitized import options.
     * @return array<string, string>
     */
    private function resolve_property_map_location( array $data, array $options ): array {
        if ( $this->wizard_provided_custom_location( $options ) ) {
            $custom = $this->normalize_map_location(
                (string) ( $options['map_address'] ?? '' ),
                (string) ( $options['map_latitude'] ?? $options['latitude'] ?? '' ),
                (string) ( $options['map_longitude'] ?? $options['longitude'] ?? '' )
            );

            if ( $this->is_valid_map_location( $custom ) ) {
                return $custom;
            }
        }

        $uk_candidate = $this->normalize_map_location(
            (string) ( $data['map_address'] ?? $data['full_address'] ?? '' ),
            (string) ( $data['map_latitude'] ?? $data['latitude'] ?? '' ),
            (string) ( $data['map_longitude'] ?? $data['longitude'] ?? '' )
        );

        if ( $this->is_valid_map_location( $uk_candidate ) ) {
            return $uk_candidate;
        }

        $import_index = absint( $data['import_index'] ?? 0 );
        $uk_resolved  = UkImportLocations::resolve_for_index( $import_index );
        $uk_location  = $this->normalize_map_location(
            (string) ( $uk_resolved['map_address'] ?? $uk_resolved['full_address'] ?? '' ),
            (string) ( $uk_resolved['map_latitude'] ?? $uk_resolved['latitude'] ?? '' ),
            (string) ( $uk_resolved['map_longitude'] ?? $uk_resolved['longitude'] ?? '' )
        );

        if ( $this->is_valid_map_location( $uk_location ) ) {
            return $uk_location;
        }

        $fallback = UkImportLocations::get_london_fallback();

        return $this->normalize_map_location(
            (string) ( $fallback['map_address'] ?? 'London, United Kingdom' ),
            (string) ( $fallback['map_latitude'] ?? '51.5074' ),
            (string) ( $fallback['map_longitude'] ?? '-0.1278' )
        );
    }

    /**
     * Whether the wizard location step was explicitly modified by the user.
     *
     * @param array<string, mixed> $options Sanitized import options.
     * @return bool
     */
    private function import_location_modified( array $options ): bool {
        if ( ! array_key_exists( 'location_modified', $options ) ) {
            return false;
        }

        return rest_sanitize_boolean( $options['location_modified'] );
    }

    /**
     * Whether the latitude/longitude were entered by the user rather than
     * auto-generated by geocoding a typed address.
     *
     * User-entered means: manual coordinate typing, marker drag, or map click.
     * Only user-entered coordinates may replace each property's own demo
     * coordinates (a full custom location). Geocoded coordinates that merely
     * accompany an address edit must not.
     *
     * @param array<string, mixed> $options Sanitized import options.
     * @return bool
     */
    private function import_coordinates_user_entered( array $options ): bool {
        if ( ! array_key_exists( 'coordinates_user_entered', $options ) ) {
            return false;
        }

        return rest_sanitize_boolean( $options['coordinates_user_entered'] );
    }

    /**
     * Whether the wizard supplied a custom map location for all properties.
     *
     * @param array<string, mixed> $options Sanitized import options.
     * @return bool
     */
    private function wizard_provided_custom_location( array $options ): bool {
        if ( ! $this->import_location_modified( $options ) ) {
            return false;
        }

        // A full override requires user-entered coordinates. Auto-geocoded
        // coordinates that merely accompany an address edit are an address-only
        // override and are handled by apply_partial_location_override().
        if ( ! $this->import_coordinates_user_entered( $options ) ) {
            return false;
        }

        $map_address = $options['map_address'] ?? '';
        $latitude    = $options['map_latitude'] ?? $options['latitude'] ?? null;
        $longitude   = $options['map_longitude'] ?? $options['longitude'] ?? null;

        return $this->import_option_has_value( $map_address )
            && $this->import_map_coordinate_has_value( $latitude )
            && $this->import_map_coordinate_has_value( $longitude );
    }

    /**
     * Apply a sanitized Quick Property Setup string to property data when provided.
     *
     * @param array<string, mixed> $property_data Property payload.
     * @param array<string, mixed> $options       Sanitized import options.
     * @param string               $field         Field key.
     * @param bool                 $is_url        Whether to sanitize as URL.
     * @return array<string, mixed>
     */
    private function apply_setup_string_override( array $property_data, array $options, string $field, bool $is_url = false ): array {
        if ( ! isset( $options[ $field ] ) || ! $this->import_option_has_value( $options[ $field ] ) ) {
            return $property_data;
        }

        $property_data[ $field ] = $is_url
            ? esc_url_raw( (string) $options[ $field ] )
            : sanitize_text_field( (string) $options[ $field ] );

        return $property_data;
    }

    private function merge_common_options( $property_data, $options ) {
        $setup_string_fields = array(
            'reference_number',
            'building_number',
            'street',
            'address_line_1',
            'address_line_2',
            'town_city',
            'country_state',
            'zip_code',
            'youtube_title',
            'youtube_url',
            'youtube_thumbnail',
            'gallery_title',
            'map_address',
            'map_latitude',
            'map_longitude',
            'full_address',
            'latitude',
            'longitude',
            'heating',
            'cooling',
            'water',
            'location',
            'country_location',
            'lot_size',
            'hoa_fee',
        );

        $location_setup_fields = array(
            'building_number',
            'street',
            'address_line_1',
            'address_line_2',
            'town_city',
            'country_state',
            'zip_code',
            'map_address',
            'map_latitude',
            'map_longitude',
            'full_address',
            'latitude',
            'longitude',
            'country_location',
        );

        $use_custom_location = $this->wizard_provided_custom_location( $options );

        foreach ( $setup_string_fields as $field ) {
            if ( in_array( $field, $location_setup_fields, true ) && ! $use_custom_location ) {
                continue;
            }

            $is_url = ( false !== strpos( $field, 'url' ) );
            $property_data = $this->apply_setup_string_override( $property_data, $options, $field, $is_url );
        }

        if ( isset( $options['property_title'] ) && $this->import_option_has_value( $options['property_title'] ) ) {
            $property_data['title'] = sanitize_text_field( (string) $options['property_title'] );
        }

        if ( isset( $options['include_images'] ) ) {
            $property_data['include_images'] = rest_sanitize_boolean( $options['include_images'] );
        } else {
            $property_data['include_images'] = true;
        }

        $setup_numeric_fields = array(
            'half_bathrooms',
            'garage_sqft',
            'bedrooms',
            'bathrooms',
            'reception_rooms',
            'sqft',
            'price',
            'year_built',
            'floors',
            'total_rooms',
            'kitchens',
            'tax_amount',
        );

        foreach ( $setup_numeric_fields as $field ) {
            if ( isset( $options[ $field ] ) && $this->import_option_has_value( $options[ $field ] ) ) {
                $property_data[ $field ] = floatval( $options[ $field ] );
            }
        }

        if ( empty( $property_data['full_address'] ) && ! empty( $property_data['address_line_1'] ) ) {
            $address_parts = array( $property_data['address_line_1'] );
            if ( ! empty( $property_data['town_city'] ) ) {
                $address_parts[] = $property_data['town_city'];
            }
            if ( ! empty( $property_data['country_state'] ) ) {
                $address_parts[] = $property_data['country_state'];
            }
            if ( ! empty( $property_data['zip_code'] ) ) {
                $address_parts[] = $property_data['zip_code'];
            }
            $property_data['full_address'] = implode( ', ', $address_parts );
        }

        if ( empty( $property_data['latitude'] ) && ! empty( $property_data['map_latitude'] ) ) {
            $property_data['latitude'] = $property_data['map_latitude'];
        }
        if ( empty( $property_data['longitude'] ) && ! empty( $property_data['map_longitude'] ) ) {
            $property_data['longitude'] = $property_data['map_longitude'];
        }

        return $property_data;
    }

    /**
     * Persist a per-property import location trace to hvnly_import_logs.
     *
     * @param int                  $import_index   Zero-based import index.
     * @param int                  $property_index Demo dataset index.
     * @param array<string, mixed> $property_data  Resolved property payload.
     * @param array<string, mixed> $options        Sanitized import options.
     * @return void
     */
    private function log_import_location_trace( int $import_index, int $property_index, array $property_data, array $options ): void {
        if ( $this->wizard_provided_custom_location( $options ) ) {
            $source = 'CUSTOM';
        } elseif ( $this->import_location_modified( $options ) ) {
            $source = 'ADDRESS_ONLY';
        } else {
            $source = 'DEMO';
        }

        $this->log_error(
            sprintf(
                'IMPORT_LOCATION_TRACE | property #%1$d | import_index=%2$d | property_index=%3$d | source=%4$s | map_address=%5$s | lat=%6$s | lng=%7$s',
                $import_index + 1,
                $import_index,
                $property_index,
                $source,
                (string) ( $property_data['map_address'] ?? '' ),
                (string) ( $property_data['map_latitude'] ?? $property_data['latitude'] ?? '' ),
                (string) ( $property_data['map_longitude'] ?? $property_data['longitude'] ?? '' )
            )
        );
    }

    /**
     * Assign a rotating UK address and map coordinates per imported property.
     *
     * Wizard map inputs remain the site default (batch 0); each property gets its own location.
     *
     * @param array<string, mixed> $property_data Property payload.
     * @param int                  $import_index    Zero-based index in the import run.
     * @return array<string, mixed>
     */
    private function apply_diverse_import_location( array $property_data, int $import_index ): array {
        $resolved = UkImportLocations::apply_to_property_data( $property_data, $import_index );

        $this->log_debug(
            'Assigned UK import location',
            array(
                'index'   => $import_index,
                'city'    => $resolved['town_city'] ?? '',
                'lat'     => $resolved['map_latitude'] ?? '',
                'lng'     => $resolved['map_longitude'] ?? '',
            )
        );

        return $resolved;
    }

    /**
     * Overlay only the location fields the user explicitly changed.
     *
     * Runs on top of a resolved diverse demo location. When the user leaves the
     * Location step untouched ( location_modified === false ) the demo data is
     * returned unchanged. When the user fills in some fields, only those
     * non-empty values overwrite the demo data; blank inputs are ignored so
     * valid demo coordinates are never replaced with empty strings.
     *
     * @param array<string, mixed> $property_data Property payload (already carries demo location).
     * @param array<string, mixed> $options       Sanitized import options.
     * @return array<string, mixed>
     */
    private function apply_partial_location_override( array $property_data, array $options ): array {
        if ( ! $this->import_location_modified( $options ) ) {
            return $property_data;
        }

        // Address-level fields are always safe to overlay from the user's input.
        // Hard-coded wizard defaults such as building_number / street /
        // reference_number are intentionally excluded so they never clobber demo
        // values during a partial override.
        $overlay_fields = array(
            'map_address',
            'full_address',
            'address_line_1',
            'address_line_2',
            'town_city',
            'country_state',
            'zip_code',
            'country_location',
        );

        // Coordinates are overlaid ONLY when the user actually entered them
        // (manual typing, marker drag, or map click). Auto-geocoded coordinates
        // that merely accompanied an address edit must never replace each
        // property's own demo latitude/longitude.
        $coordinates_user_entered = $this->import_coordinates_user_entered( $options );

        if ( $coordinates_user_entered ) {
            $overlay_fields[] = 'map_latitude';
            $overlay_fields[] = 'latitude';
            $overlay_fields[] = 'map_longitude';
            $overlay_fields[] = 'longitude';
        }

        foreach ( $overlay_fields as $field ) {
            $property_data = $this->apply_setup_string_override( $property_data, $options, $field, false );
        }

        // Keep the latitude/longitude mirrors aligned with the map coordinates,
        // but only when coordinates were user-entered (otherwise demo values stay).
        if ( $coordinates_user_entered ) {
            if ( ! empty( $property_data['map_latitude'] ) ) {
                $property_data['latitude'] = $property_data['map_latitude'];
            }
            if ( ! empty( $property_data['map_longitude'] ) ) {
                $property_data['longitude'] = $property_data['map_longitude'];
            }
        }

        return $property_data;
    }

    /**
     * Progressively assign stock images to UK location terms (empty meta only).
     *
     * @param array<string, mixed> $options  Sanitized import options.
     * @param int                  $imported Properties already imported in this run.
     * @return void
     */
    private function maybe_seed_location_term_images_batch( array $options = array(), int $imported = 0 ): void {
        if ( ! class_exists( PropertyLocationTermImageSeeder::class ) ) {
            return;
        }

        $include_images = ! isset( $options['include_images'] ) || ! empty( $options['include_images'] );
        $limit          = $include_images ? 2 : 4;

        // First batch already sideloads agent/property images — seed fewer location terms.
        if ( 0 === $imported ) {
            $limit = min( $limit, 1 );
        }

        $seeder = new PropertyLocationTermImageSeeder();
        $seeder->run( $limit, array( $this, 'log_error' ) );
    }

    /**
     * Assign any remaining location term images after the final import batch.
     *
     * @return void
     */
    private function finish_location_term_image_seeding(): void {
        if ( ! class_exists( PropertyLocationTermImageSeeder::class ) ) {
            return;
        }

        $seeder = new PropertyLocationTermImageSeeder();
        $seeder->finish_pending( array( $this, 'log_error' ) );
    }

    /**
     * Progressively assign stock images to default demo property type terms (empty meta only).
     *
     * @param array<string, mixed> $options  Sanitized import options.
     * @param int                  $imported Properties already imported in this run.
     * @return void
     */
    private function maybe_seed_property_type_term_images_batch( array $options = array(), int $imported = 0 ): void {
        if ( ! class_exists( PropertyTypeTermImageSeeder::class ) ) {
            return;
        }

        $include_images = ! isset( $options['include_images'] ) || ! empty( $options['include_images'] );
        $limit          = $include_images ? 1 : 2;

        if ( 0 === $imported ) {
            $limit = 1;
        }

        $seeder = new PropertyTypeTermImageSeeder();
        $seeder->run( $limit, array( $this, 'log_error' ) );
    }

    /**
     * Assign any remaining demo property type term images after the final import batch.
     *
     * @return void
     */
    private function finish_property_type_term_image_seeding(): void {
        if ( ! class_exists( PropertyTypeTermImageSeeder::class ) ) {
            return;
        }

        $seeder = new PropertyTypeTermImageSeeder();
        $seeder->finish_pending( array( $this, 'log_error' ) );
    }

    /**
     * Backfill missing location term images when viewing the Locations admin screen.
     *
     * @return void
     */
    public function maybe_backfill_location_term_images_admin(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $taxonomy = isset( $_GET['taxonomy'] ) ? sanitize_key( wp_unslash( $_GET['taxonomy'] ) ) : '';
        if ( 'hvnly_prop_locations' !== $taxonomy ) {
            return;
        }

        if ( ! get_option( $this->import_option, false ) ) {
            return;
        }

        if ( ! class_exists( PropertyLocationTermImageSeeder::class ) ) {
            return;
        }

        $seeder = new PropertyLocationTermImageSeeder();
        $seeder->run( 15, array( $this, 'log_error' ) );
    }

    private function check_rate_limit() {
        $rate_key = 'hvnly_import_rate_' . get_current_user_id();
        $attempts = get_transient( $rate_key );
        if ( $attempts && $attempts >= 5 ) {
            throw new \Exception( esc_html__( 'Rate limit exceeded. Please wait before trying again.', 'havenlytics' ) );
        }
    }

    private function update_rate_limit() {
        $rate_key = 'hvnly_import_rate_' . get_current_user_id();
        $attempts = get_transient( $rate_key );
        if ( $attempts ) {
            set_transient( $rate_key, $attempts + 1, HOUR_IN_SECONDS );
        } else {
            set_transient( $rate_key, 1, HOUR_IN_SECONDS );
        }
    }

    private function log_error( $message ) {
        $log_entry = [ 'time' => current_time( 'mysql' ), 'user' => get_current_user_id(), 'message' => $message ];
        $logs = get_option( 'hvnly_import_logs', [] );
        array_unshift( $logs, $log_entry );
        $logs = array_slice( $logs, 0, 100 );
        update_option( 'hvnly_import_logs', $logs );
        
        if ( $this->debug_mode && function_exists( 'hvnly_debug_log' ) ) {
            hvnly_debug_log( $message, 'Havenlytics Import Error' );
        }
    }

    private function ensure_taxonomies_exist() {
        $uk_location_terms = array();
        foreach ( UkImportLocations::get_locations() as $uk_location ) {
            if ( ! empty( $uk_location['slug'] ) && ! empty( $uk_location['city'] ) ) {
                $uk_location_terms[ $uk_location['slug'] ] = $uk_location['city'];
            }
        }

        $taxonomies = [
            'hvnly_prop_depts' => [ 'sale' => 'Sale', 'rent' => 'Rent', 'commercial' => 'Commercial', 'let' => 'Let' ],
            'hvnly_prop_types' => [ 'cottage' => 'Cottage', 'duplex' => 'Duplex', 'flat' => 'Flat', 'land' => 'Land', 'garage' => 'Garage', 'mews' => 'Mews', 'triplex' => 'Triplex' ],
            'hvnly_prop_features' => [ 'studio' => 'Studio', 'premium' => 'Premium', 'privacy' => 'Privacy', 'rooftop-terrace' => 'Rooftop Terrace', 'hardwood-floors' => 'Hardwood Floors', 'central-air' => 'Central Air', 'fireplace' => 'Fireplace', 'pool' => 'Pool', 'gym' => 'Gym', 'parking' => 'Parking', 'elevator' => 'Elevator', 'balcony' => 'Balcony', 'garden' => 'Garden', 'security' => 'Security' ],
            'hvnly_prop_locations' => array_merge(
                [
                    'abu-dhabi' => 'Abu Dhabi', 'berlin' => 'Berlin', 'boston' => 'Boston', 'chicago' => 'Chicago', 'coastal' => 'Coastal', 'dallas' => 'Dallas', 'dubai' => 'Dubai', 'hong-kong' => 'Hong Kong', 'london' => 'London', 'los-angeles' => 'Los Angeles', 'miami' => 'Miami', 'new-york' => 'New York', 'paris' => 'Paris', 'san-francisco' => 'San Francisco', 'seattle' => 'Seattle', 'singapore' => 'Singapore', 'sydney' => 'Sydney', 'tokyo' => 'Tokyo', 'toronto' => 'Toronto', 'vancouver' => 'Vancouver',
                ],
                $uk_location_terms
            ),
            'hvnly_prop_status' => [ 'for-sale' => 'For Sale', 'for-rent' => 'For Rent', 'pending' => 'Pending', 'sold' => 'Sold', 'under-offer' => 'Under Offer', 'exchanged' => 'Exchanged' ],
            'hvnly_prop_badges' => [ 'featured' => 'Featured', 'new' => 'New', 'popular' => 'Popular' ],
            'hvnly_prop_tags' => [ 'apartment' => 'Apartment', 'bungalow' => 'Bungalow', 'commercial' => 'Commercial', 'cottage' => 'Cottage', 'duplex' => 'Duplex', 'flat' => 'Flat', 'house' => 'House', 'land' => 'Land', 'office' => 'Office', 'retail' => 'Retail', 'studio' => 'Studio', 'townhouse' => 'Townhouse', 'warehouse' => 'Warehouse' ],
        ];

        foreach ( $taxonomies as $taxonomy => $terms ) {
            if ( ! taxonomy_exists( $taxonomy ) ) {
                $this->log_error( sprintf( 'Taxonomy %s does not exist!', $taxonomy ) );
                continue;
            }
            foreach ( $terms as $term_slug => $term_name ) {
                $term = term_exists( $term_slug, $taxonomy );
                if ( ! $term ) {
                    $result = wp_insert_term( $term_name, $taxonomy, [ 'slug' => $term_slug ] );
                    if ( is_wp_error( $result ) ) {
                        $this->log_error( sprintf( 'Failed to create term %s in taxonomy %s: %s', $term_slug, $taxonomy, $result->get_error_message() ) );
                    }
                }
            }
        }
    }

    /**
     * Extract YouTube video ID from URL
     */
    private function extract_youtube_id($url) {
        if (empty($url)) return null;
        
        $patterns = [
            '/(?:youtube\.com\/watch\?v=)([^&]+)/',
            '/(?:youtu\.be\/)([^?]+)/',
            '/(?:youtube\.com\/embed\/)([^?]+)/',
            '/(?:youtube\.com\/v\/)([^?]+)/'
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $url, $matches)) {
                return $matches[1];
            }
        }
        
        return null;
    }

    /**
     * Build Basic Info meta fields (Section 0)
     */
    private function build_basic_meta_fields( $data ) {
        $meta_input = [];
        
        $numeric_fields = [
            '_hvnly_property_price' => [ 0, 999999999 ],
            '_hvnly_property_reception_rooms' => [ 0, 20 ],
            '_hvnly_property_bedrooms' => [ 0, 50 ],
            '_hvnly_property_bathrooms' => [ 0, 30 ],
            '_hvnly_property_half_bathrooms' => [ 0, 10 ],
            '_hvnly_property_kitchens' => [ 0, 10 ],
            '_hvnly_property_total_rooms' => [ 0, 100 ],
            '_hvnly_property_floors' => [ 1, 10 ],
            '_hvnly_property_year_built' => [ 1800, (int) gmdate( 'Y' ) + 1 ],
            '_hvnly_property_garage_sqft' => [ 0, 10000 ],
            '_hvnly_property_sqft' => [ 0, 100000 ],
            '_hvnly_property_hoa_fee' => [ 0, 100000 ],
            '_hvnly_property_annual_tax_amount' => [ 0, 1000000 ],
        ];

        foreach ( $numeric_fields as $field => $range ) {
            $key = str_replace( '_hvnly_property_', '', $field );
            if ( isset( $data[ $key ] ) && ! empty( $data[ $key ] ) ) {
                if ( is_numeric( $data[ $key ] ) ) {
                    $meta_input[ $field ] = $this->validate_numeric( $data[ $key ], $range[0], $range[1] );
                } else {
                    $meta_input[ $field ] = sanitize_text_field( $data[ $key ] );
                }
            } else {
                // Set default values based on field type
                if ( $field === '_hvnly_property_price' ) {
                    $meta_input[ $field ] = wp_rand( 200000, 1500000 );
                } elseif ( $field === '_hvnly_property_reception_rooms' ) {
                    $meta_input[ $field ] = wp_rand( 1, 3 );
                } elseif ( $field === '_hvnly_property_bedrooms' ) {
                    $meta_input[ $field ] = wp_rand( 2, 5 );
                } elseif ( $field === '_hvnly_property_bathrooms' ) {
                    $meta_input[ $field ] = wp_rand( 2, 4 );
                } elseif ( $field === '_hvnly_property_half_bathrooms' ) {
                    $meta_input[ $field ] = wp_rand( 0, 1 );
                } elseif ( $field === '_hvnly_property_kitchens' ) {
                    $meta_input[ $field ] = 1;
                } elseif ( $field === '_hvnly_property_total_rooms' ) {
                    $total = ( $meta_input['_hvnly_property_bedrooms'] ?? 3 ) + 
                             ( $meta_input['_hvnly_property_reception_rooms'] ?? 2 ) + 
                             ( $meta_input['_hvnly_property_kitchens'] ?? 1 ) + 
                             ( $meta_input['_hvnly_property_bathrooms'] ?? 2 );
                    $meta_input[ $field ] = $total;
                } elseif ( $field === '_hvnly_property_floors' ) {
                    $meta_input[ $field ] = wp_rand( 1, 2 );
                } elseif ( $field === '_hvnly_property_year_built' ) {
                    $meta_input[ $field ] = wp_rand( 1990, 2023 );
                } elseif ( $field === '_hvnly_property_garage_sqft' ) {
                    $meta_input[ $field ] = wp_rand( 200, 800 );
                } elseif ( $field === '_hvnly_property_sqft' ) {
                    $meta_input[ $field ] = wp_rand( 1200, 5000 );
                } elseif ( $field === '_hvnly_property_hoa_fee' ) {
                    $meta_input[ $field ] = wp_rand( 0, 500 );
                } elseif ( $field === '_hvnly_property_annual_tax_amount' ) {
                    $meta_input[ $field ] = wp_rand( 5000, 15000 );
                } else {
                    $meta_input[ $field ] = 0;
                }
            }
        }

        $meta_input['_hvnly_property_mls_number'] = isset( $data['mls_number'] ) && !empty( $data['mls_number'] ) 
            ? sanitize_text_field( $data['mls_number'] ) 
            : 'MLS' . absint( wp_rand( 10000, 99999 ) );
        
        $meta_input['_hvnly_property_lot_size'] = isset( $data['lot_size'] ) && !empty( $data['lot_size'] ) 
            ? sanitize_text_field( $data['lot_size'] ) 
            : wp_rand( 4000, 15000 ) . ' sq ft';

        return $meta_input;
    }

    /**
     * Add Additional Information section meta (Section 1)
     */
    private function add_additional_information_meta( &$meta_input, $data ) {
        // Lot size
        if ( isset( $data['lot_size'] ) && ! empty( $data['lot_size'] ) ) {
            $meta_input['_hvnly_property_lot_size'] = sanitize_text_field( $data['lot_size'] );
        } elseif ( ! isset( $meta_input['_hvnly_property_lot_size'] ) ) {
            $meta_input['_hvnly_property_lot_size'] = wp_rand( 4000, 15000 ) . ' sq ft';
        }
        
        // HOA Fee
        if ( isset( $data['hoa_fee'] ) && ! empty( $data['hoa_fee'] ) ) {
            $meta_input['_hvnly_property_hoa_fee'] = sanitize_text_field( $data['hoa_fee'] );
        } elseif ( ! isset( $meta_input['_hvnly_property_hoa_fee'] ) ) {
            $meta_input['_hvnly_property_hoa_fee'] = (string) wp_rand( 0, 500 );
        }
        
        // Annual Tax Amount
        if ( isset( $data['tax_amount'] ) && ! empty( $data['tax_amount'] ) ) {
            $meta_input['_hvnly_property_annual_tax_amount'] = floatval( $data['tax_amount'] );
        } elseif ( ! isset( $meta_input['_hvnly_property_annual_tax_amount'] ) ) {
            $meta_input['_hvnly_property_annual_tax_amount'] = floatval( wp_rand( 5000, 15000 ) );
        }
        
        // Heating, Cooling, Water
        if ( isset( $data['heating'] ) && ! empty( $data['heating'] ) ) {
            $meta_input['_hvnly_property_heating'] = sanitize_text_field( $data['heating'] );
        } elseif ( ! isset( $meta_input['_hvnly_property_heating'] ) ) {
            $heating_options = [ 'forced_air', 'central', 'heat_pump', 'radiator', 'baseboard' ];
            $meta_input['_hvnly_property_heating'] = $heating_options[ array_rand( $heating_options ) ];
        }
        
        if ( isset( $data['cooling'] ) && ! empty( $data['cooling'] ) ) {
            $meta_input['_hvnly_property_cooling'] = sanitize_text_field( $data['cooling'] );
        } elseif ( ! isset( $meta_input['_hvnly_property_cooling'] ) ) {
            $cooling_options = [ 'central', 'window', 'heat_pump', 'none' ];
            $meta_input['_hvnly_property_cooling'] = $cooling_options[ array_rand( $cooling_options ) ];
        }
        
        if ( isset( $data['water'] ) && ! empty( $data['water'] ) ) {
            $meta_input['_hvnly_property_water'] = sanitize_text_field( $data['water'] );
        } elseif ( ! isset( $meta_input['_hvnly_property_water'] ) ) {
            $water_options = [ 'city', 'well', 'shared_well' ];
            $meta_input['_hvnly_property_water'] = $water_options[ array_rand( $water_options ) ];
        }
        
        $this->log_debug( 'Additional Information meta added' );
    }

    /**
     * Add Address & Neighborhood section meta (Section 2)
     */
    private function add_address_neighborhood_meta( &$meta_input, $data ) {
        // Reference number
        if ( empty( $meta_input['_hvnly_property_reference_number'] ?? '' ) ) {
            $meta_input['_hvnly_property_reference_number'] = isset( $data['reference_number'] ) && ! empty( $data['reference_number'] ) 
                ? sanitize_text_field( $data['reference_number'] ) 
                : 'REF-' . wp_rand( 10000, 99999 );
        }
        
        // Building number
        if ( empty( $meta_input['_hvnly_property_building_number'] ?? '' ) ) {
            $meta_input['_hvnly_property_building_number'] = isset( $data['building_number'] ) && ! empty( $data['building_number'] ) 
                ? sanitize_text_field( $data['building_number'] ) 
                : (string) wp_rand( 1, 999 );
        }
        
        // Street
        if ( empty( $meta_input['_hvnly_property_street'] ?? '' ) ) {
            $meta_input['_hvnly_property_street'] = isset( $data['street'] ) && ! empty( $data['street'] ) 
                ? sanitize_text_field( $data['street'] ) 
                : $this->get_random_street_name();
        }
        
        // Address Line 1
        if ( empty( $meta_input['_hvnly_property_address_line_1'] ?? '' ) ) {
            $meta_input['_hvnly_property_address_line_1'] = isset( $data['address_line_1'] ) && ! empty( $data['address_line_1'] ) 
                ? sanitize_text_field( $data['address_line_1'] ) 
                : $meta_input['_hvnly_property_building_number'] . ' ' . $meta_input['_hvnly_property_street'];
        }
        
        // Address Line 2
        if ( ! isset( $meta_input['_hvnly_property_address_line_2'] ) ) {
            $meta_input['_hvnly_property_address_line_2'] = isset( $data['address_line_2'] ) ? sanitize_text_field( $data['address_line_2'] ) : '';
        }
        
        // Town/City
        if ( empty( $meta_input['_hvnly_property_town_city'] ?? '' ) ) {
            $meta_input['_hvnly_property_town_city'] = isset( $data['town_city'] ) && ! empty( $data['town_city'] ) 
                ? sanitize_text_field( $data['town_city'] ) 
                : $this->get_random_city();
        }
        
        // Country/State
        if ( empty( $meta_input['_hvnly_property_country_state'] ?? '' ) ) {
            $meta_input['_hvnly_property_country_state'] = isset( $data['country_state'] ) && ! empty( $data['country_state'] ) 
                ? sanitize_text_field( $data['country_state'] ) 
                : $this->get_random_state();
        }
        
        // Zip Code
        if ( empty( $meta_input['_hvnly_property_zip_code'] ?? '' ) ) {
            $meta_input['_hvnly_property_zip_code'] = isset( $data['zip_code'] ) && ! empty( $data['zip_code'] ) 
                ? sanitize_text_field( $data['zip_code'] ) 
                : (string) wp_rand( 10000, 99999 );
        }
        
        // Location (from taxonomy)
        if ( isset( $data['location'] ) && ! empty( $data['location'] ) ) {
            $meta_input['_hvnly_property_location'] = sanitize_text_field( $data['location'] );
        } elseif ( ! isset( $meta_input['_hvnly_property_location'] ) ) {
            $locations = [ 'suburban', 'urban', 'rural', 'coastal' ];
            $meta_input['_hvnly_property_location'] = $locations[ array_rand( $locations ) ];
        }
        
        // Country Location
        if ( isset( $data['country_location'] ) && ! empty( $data['country_location'] ) ) {
            $meta_input['_hvnly_property_country_location'] = sanitize_text_field( $data['country_location'] );
        } elseif ( ! isset( $meta_input['_hvnly_property_country_location'] ) ) {
            $meta_input['_hvnly_property_country_location'] = 'US';
        }
        
        $this->log_debug( 'Address & Neighborhood meta added' );
    }

    /**
     * Maximum demo properties available for import (unique dataset size).
     *
     * @return int
     */
    private function get_demo_import_limit(): int {
        return max( 1, count( DemoData::get_demo_properties_data() ) );
    }

    /**
     * Populate builder preset contact fields on imported demo properties.
     *
     * Uses the canonical preset meta keys from the Property Builder preset
     * field system (preset_hvnly_property_field_*).
     *
     * @param array<string, mixed> $meta_input Meta input array (by reference).
     * @param array<string, mixed> $data       Property payload.
     * @return void
     */
    private function add_demo_contact_preset_meta( array &$meta_input, array $data ): void {
        $defaults = array(
            'email'   => 'contact@example.com',
            'phone'   => '+1 (555) 123-4567',
            'website' => 'https://havenlytics.com',
        );

        $email = ! empty( $data['contact_email'] )
            ? sanitize_email( (string) $data['contact_email'] )
            : $defaults['email'];

        $phone = ! empty( $data['contact_phone'] )
            ? sanitize_text_field( (string) $data['contact_phone'] )
            : $defaults['phone'];

        $website = ! empty( $data['contact_website'] )
            ? esc_url_raw( (string) $data['contact_website'] )
            : $defaults['website'];

        $meta_input['preset_hvnly_property_field_email']   = $email;
        $meta_input['preset_hvnly_property_field_phone']   = $phone;
        $meta_input['preset_hvnly_property_field_website'] = $website;
    }

    /**
     * Add video meta (Section 3) - GENERATES UNIQUE ID PER PROPERTY
     */
    private function add_video_meta( &$meta_input, $data, $options ) {
        // Generate UNIQUE video base ID for THIS SPECIFIC property
        $video_base = $this->generate_unique_base_id('video');
        
        // Get video URL from options or demo data
        $video_url = '';
        if ( isset( $options['youtube_url'] ) && ! empty( $options['youtube_url'] ) ) {
            $video_url = esc_url_raw( $options['youtube_url'] );
        } elseif ( isset( $data['youtube_url'] ) && ! empty( $data['youtube_url'] ) ) {
            $video_url = esc_url_raw( $data['youtube_url'] );
        }
        
        $video_title = isset( $options['youtube_title'] ) && ! empty( $options['youtube_title'] ) 
            ? sanitize_text_field( $options['youtube_title'] ) 
            : ( isset( $data['youtube_title'] ) ? sanitize_text_field( $data['youtube_title'] ) : __( 'Property Tour', 'havenlytics' ) );
        
        $video_thumbnail = isset( $options['youtube_thumbnail'] ) && ! empty( $options['youtube_thumbnail'] ) 
            ? esc_url_raw( $options['youtube_thumbnail'] ) 
            : ( isset( $data['youtube_thumbnail'] ) ? esc_url_raw( $data['youtube_thumbnail'] ) : '' );
        
        // Extract thumbnail from YouTube URL if needed
        if ( empty( $video_thumbnail ) && ! empty( $video_url ) ) {
            $video_id = $this->extract_youtube_id( $video_url );
            if ( $video_id ) {
                $video_thumbnail = "https://img.youtube.com/vi/{$video_id}/maxresdefault.jpg";
            }
        }
        
        // Save video data using UNIQUE base ID for THIS property
        if ( ! empty( $video_url ) ) {
            $meta_input[ $video_base . '_title' ] = $video_title;
            $meta_input[ $video_base . '_url' ] = $video_url;
            $meta_input[ $video_base . '_thumbnail' ] = $video_thumbnail;
            $this->log_debug( 'Saved video: ' . $video_base . '_url = ' . $video_url );
            $this->log_debug( 'Generated unique video base ID: ' . $video_base );
        } else {
            $this->log_debug( 'No video URL provided' );
        }
        
        // Legacy support (kept for backward compatibility)
        $meta_input['_hvnly_property_youtube_video_title'] = $video_title;
        $meta_input['_hvnly_property_youtube_video_url'] = $video_url;
        $meta_input['_hvnly_property_youtube_video_thumbnail'] = $video_thumbnail;
        
        return $video_base;
    }

    /**
     * Add gallery meta (Section 4) - GENERATES UNIQUE ID PER PROPERTY
     */
    private function add_gallery_meta( &$meta_input, $options ) {
        // Generate UNIQUE gallery base ID for THIS SPECIFIC property
        $gallery_base = $this->generate_unique_base_id('gallery');
        
        $gallery_title = isset( $options['gallery_title'] ) && ! empty( $options['gallery_title'] ) 
            ? sanitize_text_field( $options['gallery_title'] ) 
            : __( 'Property Gallery', 'havenlytics' );
        
        $meta_input[ $gallery_base . '_title' ] = $gallery_title;
        $meta_input['_hvnly_property_gallery_title'] = $gallery_title;
        
        $this->log_debug( 'Saved gallery title: ' . $gallery_base . '_title' );
        $this->log_debug( 'Generated unique gallery base ID: ' . $gallery_base );
        
        return $gallery_base;
    }

    /**
     * Add map meta (Section 5) - GENERATES UNIQUE ID PER PROPERTY
     */
    private function add_map_meta( &$meta_input, $options, $data = array() ) {
        // Generate UNIQUE map base ID for THIS SPECIFIC property
        $map_base = $this->generate_unique_base_id('map');
        
        $map_address = ! empty( $data['map_address'] )
            ? sanitize_text_field( $data['map_address'] )
            : ( isset( $options['map_address'] ) && ! empty( $options['map_address'] )
                ? sanitize_text_field( $options['map_address'] )
                : 'London, UK' );
        
        $map_latitude = ! empty( $data['map_latitude'] )
            ? sanitize_text_field( $data['map_latitude'] )
            : ( isset( $options['map_latitude'] ) && ! empty( $options['map_latitude'] )
                ? sanitize_text_field( $options['map_latitude'] )
                : '51.5074' );
        
        $map_longitude = ! empty( $data['map_longitude'] )
            ? sanitize_text_field( $data['map_longitude'] )
            : ( isset( $options['map_longitude'] ) && ! empty( $options['map_longitude'] )
                ? sanitize_text_field( $options['map_longitude'] )
                : '-0.1278' );
        
        $meta_input[ $map_base . '_address' ] = $map_address;
        $meta_input[ $map_base . '_latitude' ] = $map_latitude;
        $meta_input[ $map_base . '_longitude' ] = $map_longitude;
        $meta_input[ $map_base . '_preview' ] = '';
        
        $this->log_debug( 'Saved map: ' . $map_base . '_address = ' . $map_address );
        $this->log_debug( 'Generated unique map base ID: ' . $map_base );
        
        // Legacy map fields (kept for backward compatibility)
        $meta_input['_hvnly_property_map_location_address'] = $map_address;
        $meta_input['_hvnly_property_location_Latitude'] = $map_latitude;
        $meta_input['_hvnly_property_location_Longitude'] = $map_longitude;
        
        return $map_base;
    }

/**
 * Add documents meta (Section 6) - SINGLE JSON FIELD
 * Uses SINGLE field ({base}_documents) not 3 separate fields
 */
private function add_documents_meta( &$meta_input, $options = [] ) {
    // Generate UNIQUE documents base ID for THIS SPECIFIC property
    $docs_base = $this->generate_unique_base_id('property_docs');
    
    // The field name should be {base}_documents (SINGLE JSON field)
    $documents_field = $docs_base . '_documents';
    
    $sample_documents = $this->get_bundled_demo_documents();
    
    $documents_json = wp_json_encode( $sample_documents );
    
    // Save using SINGLE JSON field - THIS IS WHAT THE DOCUMENT FIELD HANDLER EXPECTS
    $meta_input[ $documents_field ] = $documents_json;
    
    // Sidebar visibility
    $meta_input[ $docs_base . '_show_in_sidebar' ] = '1';
    
    // Legacy support
    $meta_input['_hvnly_property_documents'] = $documents_json;
    
    $this->log_debug( 'Saved documents as JSON: ' . $documents_field );
    $this->log_debug( 'Generated unique documents base ID: ' . $docs_base );
    
    return $docs_base;
}

    /**
     * Demo property document samples (bundled plugin assets).
     *
     * @return array<int, array<string, string>>
     */
    private function get_bundled_demo_documents() {
        $base_url = trailingslashit( HVNLYNAB_ASSETS_URL ) . 'demo/documents/';

        return [
            [
                'icon'     => 'file-pdf',
                'label'    => __( 'Floor Plan', 'havenlytics' ),
                'url'      => $base_url . 'floor-plan.pdf',
                'url_type' => 'pdf',
            ],
            [
                'icon'     => 'file-image',
                'label'    => __( 'Brochure', 'havenlytics' ),
                'url'      => $base_url . 'brochure.pdf',
                'url_type' => 'pdf',
            ],
            [
                'icon'     => 'file-alt',
                'label'    => __( 'Energy Certificate', 'havenlytics' ),
                'url'      => $base_url . 'epc.pdf',
                'url_type' => 'pdf',
            ],
        ];
    }

    /**
     * Get random street name
     */
    private function get_random_street_name() {
        $streets = [ 'Main Street', 'Oak Avenue', 'Maple Drive', 'Cedar Lane', 'Pine Road', 'Elm Street', 'Washington Blvd', 'Park Avenue', 'Broadway', 'Sunset Blvd' ];
        return $streets[ array_rand( $streets ) ];
    }

    /**
     * Get random city
     */
    private function get_random_city() {
        $cities = [ 'Austin', 'New York', 'Los Angeles', 'Chicago', 'Miami', 'San Francisco', 'Seattle', 'Boston', 'Dallas', 'Denver', 'Portland', 'Atlanta' ];
        return $cities[ array_rand( $cities ) ];
    }

    /**
     * Get random state
     */
    private function get_random_state() {
        $states = [ 'TX', 'NY', 'CA', 'IL', 'FL', 'WA', 'MA', 'CO', 'OR', 'GA', 'NC', 'AZ', 'NV', 'TN' ];
        return $states[ array_rand( $states ) ];
    }

/**
 * Get standardized field names from builder configuration.
 *
 * Priority order:
 *  1. Live builder config (hvnly_property_builder.sections) – most authoritative.
 *  2. Master base IDs (hvnly_master_base_ids) – stable fallback when config is empty.
 *
 * Returns an associative array keyed by group_type → meta_key → full field name.
 * A key is present ONLY when a non-empty field name is available; callers MUST
 * use isset() / !empty() rather than ?? to avoid writing to empty meta keys.
 *
 * @return array<string, array<string, string>>
 */
private function get_standardized_field_names() {
    $cached = wp_cache_get( 'hvnly_standardized_field_names' );
    if ( false !== $cached ) {
        return $cached;
    }

    $standardized = [];

    // Priority 1 – live builder configuration.
    //
    // CRITICAL: sort sections by their `order` value so the canonical,
    // lowest-order default section always wins when multiple sections share
    // the same group_type+metaKey pair.  Without this, a user-added section
    // (e.g. "Test Section", order=7) would overwrite the canonical base IDs
    // (e.g. Property Video, order=3) because PHP iterates the option array
    // in insertion order, not display order.
    //
    // We also use an empty-check rather than an assignment so only the FIRST
    // (canonical) occurrence is kept — subsequent sections with the same
    // group_type+metaKey are ignored.
    $builder_config  = get_option( 'hvnly_property_builder.sections', [] );
    $sorted_sections = is_array( $builder_config ) ? array_values( $builder_config ) : [];
    usort( $sorted_sections, function ( $a, $b ) {
        return ( (int) ( $a['order'] ?? 99 ) ) - ( (int) ( $b['order'] ?? 99 ) );
    } );

    foreach ( $sorted_sections as $section ) {
        foreach ( $section['fields'] ?? [] as $field ) {
            $group_type = $field['group_type'] ?? '';
            $meta_key   = $field['metaKey']    ?? '';
            $field_name = $field['name']        ?? '';

            if ( !empty( $group_type ) && !empty( $meta_key ) && !empty( $field_name ) ) {
                // Only set once — canonical (lowest-order) section wins.
                if ( empty( $standardized[ $group_type ][ $meta_key ] ) ) {
                    $standardized[ $group_type ][ $meta_key ] = $field_name;
                }
            }
        }
    }

    // Priority 1.5 – single-JSON-field groups derive canonical keys from group_base_id.
    $json_group_suffixes = array(
        'property_docs' => 'documents',
        'faq'           => 'faqs',
        'repeater'      => 'items',
        'agents'        => 'agents',
        'features'      => 'features',
    );

    foreach ( $json_group_suffixes as $group_type => $meta_suffix ) {
        if ( ! empty( $standardized[ $group_type ][ $meta_suffix ] ) ) {
            continue;
        }

        foreach ( $sorted_sections as $section ) {
            foreach ( $section['fields'] ?? [] as $field ) {
                $field_group_type = $field['group_type'] ?? '';
                $field_type       = $field['type'] ?? '';
                if ( $field_group_type !== $group_type && $field_type !== $group_type ) {
                    continue;
                }

                $base = $field['group_base_id'] ?? '';
                if ( ! empty( $base ) ) {
                    $standardized[ $group_type ][ $meta_suffix ] = $base . '_' . $meta_suffix;
                    break 2;
                }

                $field_name = $field['name'] ?? '';
                $prefix     = $group_type . '_';
                if ( ! empty( $field_name ) && preg_match( '/^(' . preg_quote( $prefix, '/' ) . '[a-zA-Z0-9_]+)_/', $field_name, $m ) ) {
                    $standardized[ $group_type ][ $meta_suffix ] = $m[1] . '_' . $meta_suffix;
                    break 2;
                }
            }
        }
    }

    // Priority 2 – master base IDs for import groups (including 3.1 FAQ/repeater/agents).
    $group_meta_keys = [
        'video'         => [ 'title', 'url', 'thumbnail' ],
        'gallery'       => [ 'title', 'images' ],
        'map'           => [ 'address', 'latitude', 'longitude', 'preview' ],
        'property_docs' => [ 'documents' ],
        'faq'           => [ 'faqs' ],
        'repeater'      => [ 'items' ],
        'agents'        => [ 'agents' ],
        'features'      => [ 'features' ],
    ];

    if ( class_exists( '\HvnlyNab\Core\UnifiedFieldGenerator' ) ) {
        $unified    = \HvnlyNab\Core\UnifiedFieldGenerator::get_instance();
        $master_ids = $unified->get_or_create_master_base_ids();

        foreach ( $group_meta_keys as $group_type => $meta_keys ) {
            // Only fill in what's missing – don't overwrite builder-config values.
            $base = $master_ids[ $group_type ] ?? null;
            if ( empty( $base ) ) {
                continue;
            }
            foreach ( $meta_keys as $meta_key ) {
                if ( empty( $standardized[ $group_type ][ $meta_key ] ) ) {
                    $standardized[ $group_type ][ $meta_key ] = $base . '_' . $meta_key;
                }
            }
        }
    }

    wp_cache_set( 'hvnly_standardized_field_names', $standardized, '', HOUR_IN_SECONDS );

    return $standardized;
}

/**
 * Create demo property - UPDATED to use standardized field names
 * 
 * @param array $data Property data
 * @param array $options Import options
 * @return int|WP_Error
 */
private function create_demo_property( $data, $options = [] ) {
    $this->log_debug( 'Creating property: ' . ( $data['title'] ?? 'N/A' ) );

    $this->ensure_import_property_builder_sections();
    $this->ensure_import_property_features_section();
    $this->ensure_import_tail_section_order();
    wp_cache_delete( 'hvnly_standardized_field_names' );

    // Warm standardized field-name cache for legacy callers.
    $this->get_standardized_field_names();
    
    $title = '';
    if ( $this->import_option_has_value( $options['property_title'] ?? null ) ) {
        $title = sanitize_text_field( wp_strip_all_tags( (string) $options['property_title'] ) );
    } elseif ( isset( $data['title'] ) ) {
        $title = sanitize_text_field( wp_strip_all_tags( $data['title'] ) );
    }
    $content = isset( $data['content'] ) ? wp_kses_post( $data['content'] ) : __( 'Beautiful property with great features.', 'havenlytics' );
    $excerpt = isset( $data['excerpt'] ) ? sanitize_textarea_field( $data['excerpt'] ) : __( 'Amazing property with all amenities.', 'havenlytics' );

    // Avoid duplicate titles
    $existing_query = new \WP_Query( [
        'post_type' => $this->post_type,
        'title' => $title,
        'posts_per_page' => 1,
        'post_status' => 'any',
        'fields' => 'ids',
        'no_found_rows' => true,
        'update_post_meta_cache' => false,
        'update_post_term_cache' => false,
    ] );

    if ( $existing_query->have_posts() ) {
        $title .= ' ' . uniqid();
    }

    // Build meta input for ALL sections using standardized field names
    $meta_input = [];
    
    // SECTION 0: Basic Info
    $meta_input = $this->build_basic_meta_fields( $data );
    
    // SECTION 1: Additional Information
    $this->add_additional_information_meta( $meta_input, $data );
    
    // SECTION 2: Address & Neighborhood
    $this->add_address_neighborhood_meta( $meta_input, $data );

    // Preset contact fields (Email, Phone, Website) for card/footer display.
    $this->add_demo_contact_preset_meta( $meta_input, $data );
    
    // System fields
    $meta_input['_hvnly_property_views'] = $this->validate_numeric( wp_rand( 50, 500 ), 0, 10000 );
    $meta_input['_hvnly_property_featured'] = ! empty( $data['featured'] ) ? '1' : '0';
    $meta_input['_hvnly_importing'] = '1';

    // Get canonical group bases from live builder configuration (aligned with metabox field names).
    $import_bases = $this->resolve_import_group_bases();

    $video_base    = $import_bases['video'] ?? null;
    $gallery_base  = $import_bases['gallery'] ?? null;
    $map_base      = $import_bases['map'] ?? null;
    $docs_base     = $import_bases['property_docs'] ?? null;
    $faq_base      = $import_bases['faq'] ?? null;
    $repeater_base = $import_bases['repeater'] ?? null;
    $agents_base   = $import_bases['agents'] ?? null;
    $features_base = $import_bases['features'] ?? null;

    // Last-resort unique bases when builder and master IDs are unavailable.
    $video_base    = $video_base ?? $this->generate_unique_base_id( 'video' );
    $gallery_base  = $gallery_base ?? $this->generate_unique_base_id( 'gallery' );
    $map_base      = $map_base ?? $this->generate_unique_base_id( 'map' );
    $docs_base     = $docs_base ?? $this->generate_unique_base_id( 'property_docs' );
    $faq_base      = $faq_base ?? $this->generate_unique_base_id( 'faq' );
    $repeater_base = $repeater_base ?? $this->generate_unique_base_id( 'repeater' );
    $agents_base   = $agents_base ?? $this->generate_unique_base_id( 'agents' );
    $features_base = $features_base ?? $this->generate_unique_base_id( 'features' );

    // ========== SECTION 3: VIDEO DATA ==========
    $video_url = '';
    if ( $this->import_option_has_value( $options['youtube_url'] ?? null ) ) {
        $video_url = esc_url_raw( (string) $options['youtube_url'] );
    } elseif ( $this->import_option_has_value( $data['youtube_url'] ?? null ) ) {
        $video_url = esc_url_raw( (string) $data['youtube_url'] );
    }

    $video_title = __( 'Property Tour', 'havenlytics' );
    if ( $this->import_option_has_value( $options['youtube_title'] ?? null ) ) {
        $video_title = sanitize_text_field( (string) $options['youtube_title'] );
    } elseif ( $this->import_option_has_value( $data['youtube_title'] ?? null ) ) {
        $video_title = sanitize_text_field( (string) $data['youtube_title'] );
    }

    $video_thumbnail = '';
    if ( $this->import_option_has_value( $options['youtube_thumbnail'] ?? null ) ) {
        $video_thumbnail = esc_url_raw( (string) $options['youtube_thumbnail'] );
    } elseif ( $this->import_option_has_value( $data['youtube_thumbnail'] ?? null ) ) {
        $video_thumbnail = esc_url_raw( (string) $data['youtube_thumbnail'] );
    }

    if ( empty( $video_thumbnail ) && !empty( $video_url ) ) {
        $video_id_extracted = $this->extract_youtube_id( $video_url );
        if ( $video_id_extracted ) {
            $video_thumbnail = "https://img.youtube.com/vi/{$video_id_extracted}/maxresdefault.jpg";
        }
    }

    if ( '' === $video_url ) {
        $video_url = 'https://www.youtube.com/watch?v=M-j_LvEK2ZA';
        if ( '' === $video_title || __( 'Property Tour', 'havenlytics' ) === $video_title ) {
            $video_title = __( 'Property Tour', 'havenlytics' );
        }
        if ( '' === $video_thumbnail ) {
            $video_thumbnail = 'https://img.youtube.com/vi/M-j_LvEK2ZA/maxresdefault.jpg';
        }
    }

    $video_title_field     = $video_base . '_title';
    $video_url_field       = $video_base . '_url';
    $video_thumbnail_field = $video_base . '_thumbnail';

    if ( ! empty( $video_url ) ) {
        $meta_input[ $video_title_field ]     = $video_title;
        $meta_input[ $video_url_field ]       = $video_url;
        $meta_input[ $video_thumbnail_field ] = $video_thumbnail;
        $this->log_debug( 'Saved video using field: ' . $video_url_field );

        // Legacy support
        $meta_input['_hvnly_property_youtube_video_title']     = $video_title;
        $meta_input['_hvnly_property_youtube_video_url']       = $video_url;
        $meta_input['_hvnly_property_youtube_video_thumbnail'] = $video_thumbnail;
    }

    // ========== SECTION 4: GALLERY DATA ==========
    $gallery_title_field  = $gallery_base . '_title';
    $gallery_images_field = $gallery_base . '_images';

    $gallery_title = __( 'Property Gallery', 'havenlytics' );
    if ( $this->import_option_has_value( $options['gallery_title'] ?? null ) ) {
        $gallery_title = sanitize_text_field( (string) $options['gallery_title'] );
    } elseif ( $this->import_option_has_value( $data['gallery_title'] ?? null ) ) {
        $gallery_title = sanitize_text_field( (string) $data['gallery_title'] );
    }

    $meta_input[ $gallery_title_field ]           = $gallery_title;
    $meta_input['_hvnly_property_gallery_title']  = $gallery_title;
    $this->log_debug( 'Saved gallery using field: ' . $gallery_title_field );

    // ========== SECTION 5: MAP DATA ==========
    $map_address_field   = $map_base . '_address';
    $map_latitude_field  = $map_base . '_latitude';
    $map_longitude_field = $map_base . '_longitude';
    $map_preview_field   = $map_base . '_preview';

    $map_location = $this->resolve_property_map_location( $data, $options );
    $map_address  = $map_location['map_address'];
    $map_latitude = $map_location['map_latitude'];
    $map_longitude = $map_location['map_longitude'];

    $meta_input[ $map_address_field ]   = $map_address;
    $meta_input[ $map_latitude_field ]  = $map_latitude;
    $meta_input[ $map_longitude_field ] = $map_longitude;
    $meta_input[ $map_preview_field ]   = '';
    $this->log_debug( 'Saved map using field: ' . $map_address_field );

    // Legacy map fields
    $meta_input['_hvnly_property_map_location_address'] = $map_address;
    $meta_input['_hvnly_property_location_Latitude']    = $map_latitude;
    $meta_input['_hvnly_property_location_Longitude']   = $map_longitude;

    // ========== SECTION 6: PROPERTY DOCUMENTS DATA (single JSON field) ==========
    $docs_documents_field = $docs_base . '_documents';
    $docs_sidebar_field   = $docs_base . '_show_in_sidebar';

    $sample_documents = $this->get_bundled_demo_documents();

    $documents_json                        = wp_json_encode( $sample_documents );
    $meta_input[ $docs_documents_field ]   = $documents_json;
    $meta_input[ $docs_sidebar_field ]     = '1';
    $meta_input['_hvnly_property_documents'] = $documents_json;
    $this->log_debug( 'Saved documents using field: ' . $docs_documents_field );

    // ========== Store field-to-base-ID map (group_id keyed + legacy type map) ==========
    $field_map_groups = array();
    $field_map_legacy = array(
        'video'         => $video_base,
        'gallery'       => $gallery_base,
        'map'           => $map_base,
        'property_docs' => $docs_base,
        'faq'           => $faq_base,
        'repeater'      => $repeater_base,
        'agents'        => $agents_base,
        'features'      => $features_base,
    );
    $builder_sections = get_option( 'hvnly_property_builder.sections', array() );
    if ( is_array( $builder_sections ) ) {
        foreach ( $builder_sections as $section ) {
            foreach ( $section['fields'] ?? array() as $field ) {
                $gid  = $field['group_id'] ?? '';
                $base = $field['group_base_id'] ?? '';
                $gt   = sanitize_key( (string) ( $field['group_type'] ?? '' ) );
                if ( '' === $gt ) {
                    $ft = sanitize_key( (string) ( $field['type'] ?? '' ) );
                    if ( in_array( $ft, array( 'faq', 'repeater', 'agents' ), true ) ) {
                        $gt = $ft;
                    }
                }

                if ( $gid !== '' && $base !== '' && ! isset( $field_map_groups[ $gid ] ) ) {
                    $field_map_groups[ $gid ] = $base;
                }
            }
        }
    }

    if ( class_exists( DemoImportContentSeeder::class ) ) {
        $demo_content_seeder = new DemoImportContentSeeder();
        $demo_content_seeder->enrich_meta_input( $meta_input, $field_map_groups, $field_map_legacy, $data );
    }

    $meta_input['_hvnly_field_map'] = wp_json_encode( array(
        'groups' => $field_map_groups,
        'legacy' => $field_map_legacy,
    ) );

    // ========== INSERT POST ==========
    $post_data = [
        'post_title'     => $title,
        'post_name'      => sanitize_title( $title ),
        'post_content'   => $content,
        'post_excerpt'   => $excerpt,
        'post_status'    => 'publish',
        'post_type'      => $this->post_type,
        'post_author'    => get_current_user_id(),
        'meta_input'     => $meta_input,
        'ping_status'    => 'closed',
        'comment_status' => 'closed',
    ];

    // Signal to Havenlytics hooks that this is a bulk import insertion so they
    // can skip heavy processing.  We do NOT use remove_all_actions() because
    // that would destroy hooks registered by caching plugins, SEO plugins, etc.
    $GLOBALS['hvnly_bulk_importing'] = true;
    $post_id = wp_insert_post( wp_slash( $post_data ), true );
    unset( $GLOBALS['hvnly_bulk_importing'] );

    if ( is_wp_error( $post_id ) ) {
        throw new \Exception( esc_html( $post_id->get_error_message() ) );
    }

    hvnly_safe_delete_post_meta( (int) $post_id, '_hvnly_importing', 'import_transient' );

    if ( ! empty( $data['import_session_id'] ) && isset( $data['import_index'] ) ) {
        update_post_meta( (int) $post_id, self::IMPORT_SESSION_META, sanitize_text_field( (string) $data['import_session_id'] ) );
        update_post_meta( (int) $post_id, self::IMPORT_INDEX_META, (string) absint( $data['import_index'] ) );
    }

    // Set featured image and gallery images
    if ( !empty( $data['include_images'] ) ) {
        $this->set_featured_image( $post_id, $data );

        $gallery_ids = $this->add_gallery_images( $post_id, 1 );

        if ( !empty( $gallery_ids ) ) {
            $gallery_ids_string = implode( ',', $gallery_ids );
            update_post_meta( $post_id, $gallery_images_field, $gallery_ids_string );
            update_post_meta( $post_id, '_hvnly_property_gallery_images', $gallery_ids_string );
            $this->log_debug( 'Gallery images saved using field: ' . $gallery_images_field );
        }
    }

    // Assign taxonomies
    $this->assign_property_taxonomies( $post_id, $data );

    // Assign demo agents (Agent CPT + agency taxonomy ecosystem).
    $this->maybe_assign_demo_agents( (int) $post_id, $data );

    if ( class_exists( DemoImportContentSeeder::class ) ) {
        $demo_content_seeder = new DemoImportContentSeeder();
        $demo_content_seeder->seed_agents_group( (int) $post_id, $data, $agents_base );
    }

    // Generate property ID
    if ( function_exists( 'hvnlyMain' ) && method_exists( hvnlyMain(), 'Helper' ) && method_exists( hvnlyMain()->Helper, 'generate_property_id' ) ) {
        hvnlyMain()->Helper->generate_property_id( $post_id );
    }

    // Debug after creation (only in debug mode)
    if ($this->debug_mode) {
        $this->debug_property_meta( $post_id );
    }

    $this->log_debug( 'Property created successfully: ID=' . $post_id . ', Title=' . $title );
    
    return $post_id;
}

    /**
     * Debug all meta after property creation
     */
    private function debug_property_meta( $post_id ) {
        if ( ! $this->debug_mode ) return;
        
        $all_meta = get_post_meta( $post_id );
        $this->log_debug( '========== PROPERTY META (ID: ' . $post_id . ') ==========' );
        
        $sections = [
            'basic_info' => [],
            'additional_info' => [],
            'address_neighborhood' => [],
            'video' => [],
            'gallery' => [],
            'map' => [],
            'documents' => []
        ];
        
        foreach ( $all_meta as $key => $value ) {
            $val = is_array( $value ) ? ( is_array( $value[0] ) ? wp_json_encode( $value[0] ) : $value[0] ) : $value;
            $val = substr( $val, 0, 100 );
            
            if ( strpos( $key, '_hvnly_property_price' ) !== false || strpos( $key, '_hvnly_property_bedrooms' ) !== false || 
                 strpos( $key, '_hvnly_property_bathrooms' ) !== false || strpos( $key, '_hvnly_property_reception_rooms' ) !== false ||
                 strpos( $key, '_hvnly_property_half_bathrooms' ) !== false || strpos( $key, '_hvnly_property_kitchens' ) !== false ||
                 strpos( $key, '_hvnly_property_total_rooms' ) !== false || strpos( $key, '_hvnly_property_floors' ) !== false ||
                 strpos( $key, '_hvnly_property_year_built' ) !== false || strpos( $key, '_hvnly_property_garage_sqft' ) !== false ||
                 strpos( $key, '_hvnly_property_mls_number' ) !== false ) {
                $sections['basic_info'][] = $key . ' = ' . $val;
            } elseif ( strpos( $key, '_hvnly_property_sqft' ) !== false || strpos( $key, '_hvnly_property_lot_size' ) !== false ||
                       strpos( $key, '_hvnly_property_hoa_fee' ) !== false || strpos( $key, '_hvnly_property_annual_tax_amount' ) !== false ||
                       strpos( $key, '_hvnly_property_heating' ) !== false || strpos( $key, '_hvnly_property_cooling' ) !== false ||
                       strpos( $key, '_hvnly_property_water' ) !== false ) {
                $sections['additional_info'][] = $key . ' = ' . $val;
            } elseif ( strpos( $key, '_hvnly_property_reference_number' ) !== false || strpos( $key, '_hvnly_property_building_number' ) !== false ||
                       strpos( $key, '_hvnly_property_street' ) !== false || strpos( $key, '_hvnly_property_address_line_1' ) !== false ||
                       strpos( $key, '_hvnly_property_address_line_2' ) !== false || strpos( $key, '_hvnly_property_town_city' ) !== false ||
                       strpos( $key, '_hvnly_property_country_state' ) !== false || strpos( $key, '_hvnly_property_zip_code' ) !== false ||
                       strpos( $key, '_hvnly_property_location' ) !== false || strpos( $key, '_hvnly_property_country_location' ) !== false ) {
                $sections['address_neighborhood'][] = $key . ' = ' . $val;
            } elseif ( strpos( $key, 'video_' ) === 0 || strpos( $key, '_hvnly_property_youtube' ) !== false ) {
                $sections['video'][] = $key . ' = ' . $val;
            } elseif ( strpos( $key, 'gallery_' ) === 0 || strpos( $key, '_hvnly_property_gallery' ) !== false ) {
                $sections['gallery'][] = $key . ' = ' . $val;
            } elseif ( strpos( $key, 'map_' ) === 0 || strpos( $key, '_hvnly_property_map' ) !== false || 
                       strpos( $key, '_hvnly_property_location_Latitude' ) !== false || strpos( $key, '_hvnly_property_location_Longitude' ) !== false ) {
                $sections['map'][] = $key . ' = ' . $val;
            } elseif ( strpos( $key, 'property_docs' ) === 0 || strpos( $key, '_hvnly_property_documents' ) !== false ) {
                $sections['documents'][] = $key . ' = ' . $val;
            }
        }
        
        $this->log_debug( 'Basic Info fields: ' . ( empty( $sections['basic_info'] ) ? 'NONE' : implode(' | ', $sections['basic_info']) ) );
        $this->log_debug( 'Additional Info fields: ' . ( empty( $sections['additional_info'] ) ? 'NONE' : implode(' | ', $sections['additional_info']) ) );
        $this->log_debug( 'Address & Neighborhood fields: ' . ( empty( $sections['address_neighborhood'] ) ? 'NONE' : implode(' | ', $sections['address_neighborhood']) ) );
        $this->log_debug( 'Video fields: ' . ( empty( $sections['video'] ) ? 'NONE' : implode(' | ', $sections['video']) ) );
        $this->log_debug( 'Gallery fields: ' . ( empty( $sections['gallery'] ) ? 'NONE' : implode(' | ', $sections['gallery']) ) );
        $this->log_debug( 'Map fields: ' . ( empty( $sections['map'] ) ? 'NONE' : implode(' | ', $sections['map']) ) );
        $this->log_debug( 'Documents fields: ' . ( empty( $sections['documents'] ) ? 'NONE' : implode(' | ', $sections['documents']) ) );
        $this->log_debug( '=========================================================' );
    }

    private function set_featured_image( $post_id, $data ) {
        $property_images = DemoData::get_property_images();
        $image_index = $post_id % count( $property_images );
        $image_url = esc_url_raw( $property_images[ $image_index ] );

        if ( ! $this->validate_image_url( $image_url ) ) {
            return;
        }

        $attachment_id = $this->download_and_attach_image_secure( $image_url, $post_id, 'featured' );
        if ( $attachment_id && ! is_wp_error( $attachment_id ) ) {
            set_post_thumbnail( $post_id, $attachment_id );
        }
    }

    /**
     * Add gallery images
     */
    private function add_gallery_images( $post_id, $group_index = 1 ) {
        $gallery_ids = [];
        $gallery_images = DemoData::get_property_images();
        $total_images = count( $gallery_images );

        for ( $i = 1; $i <= 3; $i++ ) {
            $image_index = ( $post_id + $i * 7 + $group_index ) % $total_images;
            $image_url = esc_url_raw( $gallery_images[ $image_index ] );

            if ( ! $this->validate_image_url( $image_url ) ) {
                continue;
            }

            $attachment_id = $this->download_and_attach_image_secure( $image_url, $post_id, 'gallery-' . $i . '-group-' . $group_index );
            if ( $attachment_id && ! is_wp_error( $attachment_id ) ) {
                $gallery_ids[] = $attachment_id;
            }
        }

        if ( ! empty( $gallery_ids ) ) {
            update_post_meta( $post_id, '_hvnly_property_gallery_images', implode( ',', $gallery_ids ) );
        }
        
        return $gallery_ids;
    }

    private function validate_image_url( $url ) {
        $host = wp_parse_url( $url, PHP_URL_HOST );
        if ( ! in_array( $host, $this->allowed_image_domains, true ) ) {
            $this->log_error( "Blocked image download from unauthorized domain: $host" );
            return false;
        }
        return true;
    }

    private function download_and_attach_image_secure( $image_url, $post_id, $suffix = '' ) {
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $image_url = esc_url_raw( (string) $image_url );
        if ( '' === $image_url || ! $this->validate_image_url( $image_url ) ) {
            return false;
        }

        $cached_attachment = $this->get_cached_sideload_attachment( $image_url );
        if ( $cached_attachment > 0 ) {
            return $cached_attachment;
        }

        // Graceful media strategy: local bundled → remote → placeholder → continue.
        // When there is no outbound HTTP (Playground / offline / dead host) skip the
        // remote download entirely rather than stalling on its timeout, and fall back
        // to a placeholder if one is registered. Import never fails on a missing image.
        $local_attachment = $this->resolve_local_bundled_attachment( $image_url, $post_id );
        if ( $local_attachment > 0 ) {
            return $local_attachment;
        }

        if ( ! function_exists( 'hvnly_import_remote_media_available' ) || ! hvnly_import_remote_media_available() ) {
            return $this->resolve_placeholder_attachment( $post_id );
        }

        $temp_file = download_url( $image_url, self::IMAGE_DOWNLOAD_TIMEOUT, true );

        if ( is_wp_error( $temp_file ) ) {
            $this->log_error( 'Failed to download image: ' . $temp_file->get_error_message() );
            return $this->resolve_placeholder_attachment( $post_id );
        }

        if ( ! file_exists( $temp_file ) ) {
            $this->log_error( 'Downloaded file does not exist: ' . $temp_file );
            return false;
        }

        $filesize = filesize( $temp_file );
        if ( $filesize > $this->max_file_size ) {
            wp_delete_file( $temp_file );
            $this->log_error( 'Image file too large: ' . size_format( $filesize ) );
            return false;
        }

        $file_info = wp_check_filetype_and_ext( $temp_file, basename( $image_url ) );
        $mime_type = $file_info['type'] ?? '';

        if ( empty( $mime_type ) || ! in_array( $mime_type, $this->allowed_mime_types, true ) ) {
            wp_delete_file( $temp_file );
            $this->log_error( "Invalid image mime type: $mime_type" );
            return false;
        }

        if ( function_exists( 'finfo_open' ) ) {
            $finfo = finfo_open( FILEINFO_MIME_TYPE );
            $actual_mime = finfo_file( $finfo, $temp_file );
            finfo_close( $finfo );
            if ( ! in_array( $actual_mime, $this->allowed_mime_types, true ) ) {
                wp_delete_file( $temp_file );
                $this->log_error( "MIME type mismatch: $actual_mime" );
                return false;
            }
        }

        $file_array = [
            'name' => sanitize_file_name( 'property-' . $post_id . ( $suffix ? '-' . $suffix : '' ) . '-' . uniqid() . '.jpg' ),
            'tmp_name' => $temp_file,
        ];

        $attachment_id = media_handle_sideload( $file_array, $post_id );

        if ( file_exists( $temp_file ) ) {
            wp_delete_file( $temp_file );
        }

        if ( is_wp_error( $attachment_id ) ) {
            $this->log_error( 'Failed to attach image: ' . $attachment_id->get_error_message() );
            return false;
        }

        $this->cache_sideload_attachment( $image_url, (int) $attachment_id );

        return $attachment_id;
    }

    /**
     * Tier 1 of the media waterfall: a locally-bundled attachment for a demo image.
     *
     * No demo image binaries ship today, so this returns 0 by default — it is the
     * wiring point for a future bundled set. A filter may return a ready attachment
     * ID so offline/Playground imports can show real imagery without any network.
     *
     * @param string $image_url Remote image URL being resolved.
     * @param int    $post_id   Property post ID.
     * @return int Attachment ID, or 0 when none is available.
     */
    private function resolve_local_bundled_attachment( $image_url, $post_id ) {
        return (int) apply_filters( 'hvnly_import_local_bundled_attachment_id', 0, $image_url, $post_id );
    }

    /**
     * Tier 3 of the media waterfall: a placeholder attachment when local + remote fail.
     *
     * Returns false by default (property is created image-less — the import still
     * succeeds). A filter may supply a bundled placeholder attachment ID.
     *
     * @param int $post_id Property post ID.
     * @return int|false Attachment ID, or false to continue without an image.
     */
    private function resolve_placeholder_attachment( $post_id ) {
        $placeholder_id = (int) apply_filters( 'hvnly_import_placeholder_attachment_id', 0, $post_id );
        return $placeholder_id > 0 ? $placeholder_id : false;
    }

    private function assign_property_taxonomies( $post_id, $data ) {
        $taxonomy_map = [
            'department' => 'hvnly_prop_depts',
            'property_types' => 'hvnly_prop_types',
            'property_status' => 'hvnly_prop_status',
            'badges' => 'hvnly_prop_badges',
            'tags' => 'hvnly_prop_tags',
            'locations' => 'hvnly_prop_locations',
            'features' => 'hvnly_prop_features',
        ];

        foreach ( $taxonomy_map as $data_key => $taxonomy ) {
            if ( ! empty( $data[ $data_key ] ) ) {
                $terms = is_array( $data[ $data_key ] ) ? $data[ $data_key ] : [ $data[ $data_key ] ];
                $valid_terms = [];
                foreach ( $terms as $term ) {
                    $term = sanitize_text_field( trim( $term ) );
                    if ( ! empty( $term ) ) {
                        if ( ! term_exists( $term, $taxonomy ) ) {
                            $term_name = ucwords( str_replace( [ '-', '_' ], ' ', $term ) );
                            wp_insert_term( $term_name, $taxonomy, [ 'slug' => $term ] );
                        }
                        if ( term_exists( $term, $taxonomy ) ) {
                            $valid_terms[] = $term;
                        }
                    }
                }
                if ( ! empty( $valid_terms ) ) {
                    wp_set_object_terms( $post_id, $valid_terms, $taxonomy, false );
                }
            }
        }
    }
}