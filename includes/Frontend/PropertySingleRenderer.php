<?php
/**
 * Enhanced Property Single Renderer
 *
 * @package     Havenlytics
 * @subpackage  Frontend
 * @since       2.0.0
 */

namespace HvnlyNab\Frontend;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Enhanced Property Single Renderer Class
 */
class PropertySingleRenderer {

    /**
     * Storage key for saved sections.
     *
     * @var string
     */
    private $storage_key = 'hvnly_property_builder.sections';

    /**
     * Cache for processed groups to avoid duplicates.
     *
     * @var array
     */
    private $processed_groups = array();

    /**
     * Debug mode flag
     *
     * @var bool
     */
    private $debug_mode = false;

    /**
     * Unified meta index cache
     *
     * @var array|null
     */
    private $unified_meta_index = null;

    /**
     * Master base IDs cache
     *
     * @var array|null
     */
    private $master_base_ids = null;

    /**
     * Constructor
     */
    public function __construct() {
        $this->debug_mode         = function_exists( 'hvnly_is_debug_logging_enabled' )
            && hvnly_is_debug_logging_enabled();
        $this->unified_meta_index = get_option( 'hvnly_unified_meta_index', array() );
        $this->master_base_ids    = get_option( 'hvnly_master_base_ids', array() );
    }

    /**
     * Log frontend render diagnostics (admins only, when WP_DEBUG_LOG is on).
     *
     * @param string $message Message.
     * @return void
     */
    private function log_debug( $message ) {
        if ( ! $this->debug_mode || ! current_user_can( 'manage_options' ) ) {
            return;
        }
        if ( function_exists( 'hvnly_debug_log' ) ) {
            hvnly_debug_log( $message, 'Havenlytics Frontend' );
        }
    }

    /**
     * Get master base IDs for consistent lookup
     *
     * @return array
     */
    private function get_master_base_ids() {
        if ( $this->master_base_ids !== null && ! empty( $this->master_base_ids ) ) {
            return $this->master_base_ids;
        }

        $master_ids = get_option( 'hvnly_master_base_ids', array() );

        // If no master IDs exist, try to extract from builder config
        if ( empty( $master_ids ) ) {
            $sections = get_option( $this->storage_key, array() );
            foreach ( $sections as $section ) {
                if ( isset( $section['fields'] ) && is_array( $section['fields'] ) ) {
                    foreach ( $section['fields'] as $field ) {
                        if ( ! empty( $field['group_base_id'] ) ) {
                            $group_type = $field['group_type'] ?? '';
                            if ( $group_type === 'property_docs' && empty( $master_ids['property_docs'] ) ) {
                                $master_ids['property_docs'] = $field['group_base_id'];
                            } elseif ( $group_type === 'video' && empty( $master_ids['video'] ) ) {
                                $master_ids['video'] = $field['group_base_id'];
                            } elseif ( $group_type === 'gallery' && empty( $master_ids['gallery'] ) ) {
                                $master_ids['gallery'] = $field['group_base_id'];
                            } elseif ( $group_type === 'map' && empty( $master_ids['map'] ) ) {
                                $master_ids['map'] = $field['group_base_id'];
                            } elseif ( $group_type === 'agents' && empty( $master_ids['agents'] ) ) {
                                $master_ids['agents'] = $field['group_base_id'];
                            }
                        }
                    }
                }
            }
        }

        $this->master_base_ids = $master_ids;
        return $master_ids;
    }

    /**
     * Main rendering method for single property content.
     *
     * @param int $property_id Property ID.
     * @return void
     */
    public function render_main_content( $property_id = null ) {
        $property_id = $property_id ?: get_the_ID();

        // Debug: Log property ID
        if ( $this->debug_mode && current_user_can( 'manage_options' ) ) {
            $this->log_debug( '========== HAVENLYTICS DEBUG ==========' );
            $this->log_debug( 'Rendering property ID: ' . $property_id );
        }

        // Default sections configuration (Sprint 31D: titles are visible
        // <h2> headings — they must be translatable).
        $default_sections = array(
            'gallery' => array(
                'title'    => __( 'Property Gallery', 'havenlytics' ),
                'template' => 'gallery-carousel',
                'order'    => 0,
                'enabled'  => true,
                'priority' => 10,
            ),
            'summary' => array(
                'title'    => __( 'Property Summary', 'havenlytics' ),
                'template' => 'summary-card',
                'order'    => 0,
                'enabled'  => true,
                'priority' => 20,
            ),
            'description' => array(
                'title'    => __( 'Property Description', 'havenlytics' ),
                'template' => 'description-card',
                'order'    => 0,
                'enabled'  => true,
                'priority' => 30,
            ),
        );

        // Get all sections from database (dynamic sections from property builder)
        $dynamic_sections = get_option( $this->storage_key, array() );

        // Debug: Log sections found
        if ( $this->debug_mode && current_user_can( 'manage_options' ) ) {
            $this->log_debug( 'Dynamic sections count: ' . count( $dynamic_sections ) );
        }

        // Start with default sections
        $all_sections = $default_sections;

        // Merge with dynamic sections (they will override defaults if same keys exist)
        if ( ! empty( $dynamic_sections ) && is_array( $dynamic_sections ) ) {
            foreach ( $dynamic_sections as $section_id => $section_data ) {
                if ( ! is_array( $section_data ) ) {
                    continue;
                }

                // Skip required sections (like sec_basic_info) from frontend display
                if ( isset( $section_data['required'] ) && $section_data['required'] ) {
                    continue;
                }

                // Skip if no fields
                if ( empty( $section_data['fields'] ) || ! is_array( $section_data['fields'] ) ) {
                    continue;
                }

                $canonical_section_id   = $section_data['id'] ?? $section_id;
                $section_data['fields'] = $this->stamp_section_on_fields(
                    $section_data['fields'],
                    (string) $canonical_section_id
                );

                $all_sections[ $section_id ] = $section_data;
            }
        }

        // Sort sections by order
        $all_sections = $this->sort_sections_by_order( $all_sections );

        // Reset processed groups for this property
        $this->processed_groups = array();

        // Debug: Count video groups in all sections
        if ( $this->debug_mode && current_user_can( 'manage_options' ) ) {
            $total_video_groups = 0;
            foreach ( $all_sections as $section_id => $section ) {
                if ( isset( $section['fields'] ) && is_array( $section['fields'] ) ) {
                    $video_groups_in_section = 0;
                    $processed_group_ids     = array();
                    foreach ( $section['fields'] as $field ) {
                        $group_id   = $field['group_id'] ?? null;
                        $group_type = $field['group_type'] ?? '';
                        if ( $group_type === 'video' && $group_id && ! in_array( $group_id, $processed_group_ids ) ) {
                            $processed_group_ids[] = $group_id;
                            $video_groups_in_section++;
                            $total_video_groups++;
                        }
                    }
                    if ( $video_groups_in_section > 0 ) {
                        $this->log_debug( 'Section "' . ( $section['title'] ?? $section_id ) . '" has ' . $video_groups_in_section . ' video group(s)' );
                    }
                }
            }
            $this->log_debug( 'TOTAL VIDEO GROUPS IN BUILDER: ' . $total_video_groups );
        }

        // Render each section
        foreach ( $all_sections as $section_id => $section ) {
            // Skip if section is explicitly disabled
            if ( isset( $section['enabled'] ) && ! $section['enabled'] ) {
                continue;
            }

            // Check if this is a default section (has template) or dynamic section (has fields)
            if ( isset( $section['template'] ) ) {
                // Default section - render directly
                $this->render_default_section( $section_id, $section, $property_id );
            } else {
                // Dynamic section - render with fields
                $this->render_section( $section_id, $section, $property_id );
            }
        }
    }

    /**
     * Render a default section using template.
     *
     * @param string $section_id  Section identifier.
     * @param array  $section     Section configuration.
     * @param int    $property_id Property ID.
     * @return void
     */
    private function render_default_section( $section_id, $section, $property_id ) {
        $template = isset( $section['template'] ) ? $section['template'] : '';
        $title    = isset( $section['title'] ) ? $section['title'] : '';

        if ( empty( $template ) ) {
            return;
        }

        $section_class = 'hvnly-property-single__section-' . sanitize_title( $section_id );

        // Prepare template arguments
        $template_args = array(
            'property_id' => $property_id,
            'section'     => $section,
            'section_id'  => $section_id,
        );

        // Sprint 31D: buffer the template first — if it produces nothing
        // (e.g. Property Summary with no excerpt), skip the card entirely
        // instead of emitting a wrapper containing only an <h2>. Mirrors
        // the empty-output suppression dynamic sections already had.
        ob_start();
        hvnly_get_template( 'single-property/' . $template . '.php', $template_args );
        $section_output = ob_get_clean();

        if ( '' === trim( (string) $section_output ) ) {
            return;
        }

        // Start section container
        echo '<div class="hvnly-property-single__details-card ' . esc_attr( $section_class ) . '">';

        // Section title
        if ( ! empty( $title ) ) {
            echo '<h2 class="hvnly-property-single__section-title">' . esc_html( $title ) . '</h2>';
        }

        echo $section_output; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Template output, escaped at source.

        echo '</div>'; // Close section container
    }

    /**
     * Render a single section.
     *
     * @param string $section_id
     * @param array  $section
     * @param int    $property_id
     */
    private function render_section( $section_id, $section, $property_id ) {
        if ( $this->is_basic_info_section( $section_id, $section ) ) {
            return;
        }

        $canonical_section_id = $section['id'] ?? $section_id;
        if ( ! empty( $section['fields'] ) && is_array( $section['fields'] ) ) {
            $section['fields'] = $this->stamp_section_on_fields(
                $section['fields'],
                (string) $canonical_section_id
            );
        }

        $section_title = isset( $section['title'] ) ? $section['title'] : '';
        $section_class = 'hvnly-property-single__section-' . sanitize_title( $section_id );

        // Debug: Log section being rendered
        if ( $this->debug_mode && current_user_can( 'manage_options' ) ) {
            $this->log_debug( '--- Rendering section: ' . $section_title . ' ---' );
        }

        ob_start();

        // Sort fields by order and render in saved sequence (groups + standalone interleaved).
        $fields             = $this->sort_fields_by_order( $section['fields'] );
        $rendered_group_ids = array();

        foreach ( $fields as $field ) {
            $group_id = $field['group_id'] ?? null;

            if ( ! empty( $group_id ) ) {
                if ( in_array( $group_id, $rendered_group_ids, true ) ) {
                    continue;
                }

                $group_type = $field['group_type'] ?? $this->detect_group_type( $field );

                $group_fields     = $this->get_group_fields( $fields, $group_id );
                $group_data_check = $this->get_group_field_values( $group_fields, $property_id );

                $has_data = false;

                switch ( $group_type ) {
                    case 'video':
                        $has_data = ! empty( $group_data_check['url'] );
                        break;
                    case 'map':
                        $has_data = ! empty( $group_data_check['latitude'] ) && ! empty( $group_data_check['longitude'] );
                        if ( ! $has_data ) {
                            $has_data = ! empty( $group_data_check['address'] );
                        }
                        break;
                    case 'gallery':
                        $has_data = ! empty( $group_data_check['images'] );
                        break;
                    case 'property_docs':
                        $has_data = true;
                        break;
                    case 'agents':
                        $has_data = $this->agents_group_has_data( $group_data_check );
                        break;
                    case 'faq':
                        $has_data = $this->faq_group_has_data( $group_data_check, $group_fields, $property_id );
                        break;
                    case 'repeater':
                        $has_data = $this->repeater_group_has_data( $group_data_check, $group_fields, $property_id );
                        break;
                    default:
                        $has_data = $this->group_has_data( $group_fields, $property_id );
                        break;
                }

                if ( $has_data ) {
                    $rendered_group_ids[] = $group_id;
                    $this->render_group( $group_fields, $property_id );
                }
            } else {
                $this->render_field( $field, $property_id );
            }
        }

        $section_fields_html = ob_get_clean();

        if ( '' === trim( $section_fields_html ) ) {
            return;
        }

        echo '<div class="hvnly-property-single__details-card ' . esc_attr( $section_class ) . '">';

        if ( ! empty( $section_title ) ) {
            echo '<h2 class="hvnly-property-single__section-title">' . ( function_exists( 'hvnly_translate_ui' ) ? esc_html( hvnly_translate_ui( $section_title ) ) : esc_html( $section_title ) ) . '</h2>';
        }

        echo '<div class="hvnly-property-single__section-fields">';
        echo $section_fields_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Trusted field/group template output.
        echo '</div>';
        echo '</div>';
    }

    /**
     * Get all values for a group's fields
     *
     * @param array $group_fields
     * @param int $property_id
     * @return array
     */
    private function get_group_field_values( $group_fields, $property_id ) {
        $values = array();
        foreach ( $group_fields as $field ) {
            $metaKey = $field['metaKey'] ?? '';
            if ( $metaKey !== '' ) {
                $values[ $metaKey ] = function_exists( 'hvnly_resolve_field_meta' )
                    ? hvnly_resolve_field_meta( (int) $property_id, $field )
                    : get_post_meta( $property_id, $field['name'] ?? $field['id'] ?? '', true );
            }
        }
        return $values;
    }

    /**
     * Get video field data using stored reference keys for a specific group
     *
     * @param int $property_id Property ID
     * @param string $group_base_id Optional group base ID to get specific video data
     * @return array
     */
    private function get_video_data( $property_id, $group_base_id = '' ) {
        $field_base = array( 'group_type' => 'video' );
        if ( ! empty( $group_base_id ) ) {
            $field_base['group_base_id'] = $group_base_id;
        } else {
            $master_ids = $this->get_master_base_ids();
            if ( ! empty( $master_ids['video'] ) ) {
                $field_base['group_base_id'] = $master_ids['video'];
            }
        }

        $url = function_exists( 'hvnly_resolve_field_meta' )
            ? hvnly_resolve_field_meta( (int) $property_id, array_merge( $field_base, array( 'metaKey' => 'url' ) ) )
            : '';

        return array(
            'title'     => function_exists( 'hvnly_resolve_field_meta' )
                ? hvnly_resolve_field_meta( (int) $property_id, array_merge( $field_base, array( 'metaKey' => 'title' ) ) )
                : '',
            'url'       => $url,
            'thumbnail' => function_exists( 'hvnly_resolve_field_meta' )
                ? hvnly_resolve_field_meta( (int) $property_id, array_merge( $field_base, array( 'metaKey' => 'thumbnail' ) ) )
                : '',
            'has_data'  => $this->has_value( $url ),
        );
    }

    /**
     * Get gallery field data using stored reference keys for a specific group
     *
     * @param int $property_id Property ID
     * @param string $group_base_id Optional group base ID to get specific gallery data
     * @return array
     */
    private function get_gallery_data( $property_id, $group_base_id = '' ) {
        $field_base = array( 'group_type' => 'gallery' );
        if ( ! empty( $group_base_id ) ) {
            $field_base['group_base_id'] = $group_base_id;
        } else {
            $master_ids = $this->get_master_base_ids();
            if ( ! empty( $master_ids['gallery'] ) ) {
                $field_base['group_base_id'] = $master_ids['gallery'];
            }
        }

        $images = function_exists( 'hvnly_resolve_field_meta' )
            ? hvnly_resolve_field_meta( (int) $property_id, array_merge( $field_base, array( 'metaKey' => 'images' ) ) )
            : '';

        return array(
            'title'    => function_exists( 'hvnly_resolve_field_meta' )
                ? hvnly_resolve_field_meta( (int) $property_id, array_merge( $field_base, array( 'metaKey' => 'title' ) ) )
                : '',
            'images'   => $images,
            'has_data' => $this->has_value( $images ),
        );
    }

    /**
     * Get map field data using stored reference keys for a specific group
     *
     * @param int $property_id Property ID
     * @param string $group_base_id Optional group base ID
     * @return array
     */
    private function get_map_data( $property_id, $group_base_id = '' ) {
        $field_base = array( 'group_type' => 'map' );
        if ( ! empty( $group_base_id ) ) {
            $field_base['group_base_id'] = $group_base_id;
        } else {
            $master_ids = $this->get_master_base_ids();
            if ( ! empty( $master_ids['map'] ) ) {
                $field_base['group_base_id'] = $master_ids['map'];
            }
        }

        $address   = function_exists( 'hvnly_resolve_field_meta' )
            ? hvnly_resolve_field_meta( (int) $property_id, array_merge( $field_base, array( 'metaKey' => 'address' ) ) )
            : '';
        $latitude  = function_exists( 'hvnly_resolve_field_meta' )
            ? hvnly_resolve_field_meta( (int) $property_id, array_merge( $field_base, array( 'metaKey' => 'latitude' ) ) )
            : '';
        $longitude = function_exists( 'hvnly_resolve_field_meta' )
            ? hvnly_resolve_field_meta( (int) $property_id, array_merge( $field_base, array( 'metaKey' => 'longitude' ) ) )
            : '';

        return array(
            'address'   => $address,
            'latitude'  => $latitude,
            'longitude' => $longitude,
            'has_data'  => $this->has_value( $address )
                || ( $this->has_value( $latitude ) && $this->has_value( $longitude ) ),
        );
    }

    /**
     * Get documents field data using the correct base from field names
     *
     * @param int $property_id Property ID
     * @param array $group_fields Group fields
     * @param string $group_base_id Group base ID
     * @return array
     */
    private function get_documents_data_from_group( $property_id, $group_fields, $group_base_id = '' ) {
        $result = array(
            'documents'  => array(),
            'has_data'   => false,
            'field_name' => '',
        );

        if ( empty( $group_fields ) ) {
            return $result;
        }

        $first_field = $group_fields[0];
        $field_base  = array(
            'group_type'    => 'property_docs',
            'group_base_id' => $group_base_id ?: ( $first_field['group_base_id'] ?? '' ),
            'group_id'      => $first_field['group_id'] ?? '',
        );

        if ( empty( $field_base['group_base_id'] ) ) {
            $master_ids = $this->get_master_base_ids();
            if ( ! empty( $master_ids['property_docs'] ) ) {
                $field_base['group_base_id'] = $master_ids['property_docs'];
            }
        }

        $documents_field = ( $field_base['group_base_id'] ?? '' ) . '_documents';
        $documents_json  = function_exists( 'hvnly_resolve_field_meta' )
            ? hvnly_resolve_field_meta( (int) $property_id, array_merge( $field_base, array(
				'metaKey' => 'documents',
				'name' => $documents_field,
			) ), $documents_field )
            : '';

        if ( ! empty( $documents_json ) ) {
            $decoded = json_decode( $documents_json, true );
            if ( is_array( $decoded ) && ! empty( $decoded ) ) {
                $result['documents']  = $decoded;
                $result['has_data']   = true;
                $result['field_name'] = $documents_field;
                return $result;
            }
        }

        if ( function_exists( 'hvnly_resolve_field_meta' ) ) {
            $labels = hvnly_resolve_field_meta( (int) $property_id, array_merge( $field_base, array( 'metaKey' => 'label' ) ) );
            $urls   = hvnly_resolve_field_meta( (int) $property_id, array_merge( $field_base, array( 'metaKey' => 'url' ) ) );
            $icons  = hvnly_resolve_field_meta( (int) $property_id, array_merge( $field_base, array( 'metaKey' => 'icon' ) ) );

            if ( ! empty( $labels ) && ! empty( $urls ) ) {
                $icons_array  = is_array( $icons ) ? $icons : ( $icons ? array( $icons ) : array() );
                $labels_array = is_array( $labels ) ? $labels : ( $labels ? array( $labels ) : array() );
                $urls_array   = is_array( $urls ) ? $urls : ( $urls ? array( $urls ) : array() );
                $count        = max( count( $labels_array ), count( $urls_array ) );

                for ( $i = 0; $i < $count; $i++ ) {
                    $label = isset( $labels_array[ $i ] ) ? $labels_array[ $i ] : '';
                    $url   = isset( $urls_array[ $i ] ) ? $urls_array[ $i ] : '';
                    if ( ! empty( $label ) && ! empty( $url ) ) {
                        $result['documents'][] = array(
                            'icon'     => isset( $icons_array[ $i ] ) ? $icons_array[ $i ] : 'file-pdf',
                            'label'    => function_exists( 'hvnly_translate_ui' ) ? hvnly_translate_ui( (string) $label ) : $label,
                            'url'      => $url,
                            'url_type' => 'custom',
                        );
                    }
                }

                if ( ! empty( $result['documents'] ) ) {
                    $result['has_data'] = true;
                }
            }
        }

        return $result;
    }

    /**
     * Check if map group has any data (coordinates or address)
     *
     * @param array $group_fields
     * @param int   $property_id
     * @return bool
     */
    private function map_group_has_data( $group_fields, $property_id ) {
        foreach ( $group_fields as $field ) {
            if ( $this->has_value( $this->get_group_field_value( $field, $property_id ) ) ) {
                $meta_key = $field['name'] ?? $field['id'] ?? '';
                if ( strpos( $meta_key, '_preview' ) !== false || strpos( $meta_key, 'preview' ) !== false ) {
                    continue;
                }
                return true;
            }
        }

        return false;
    }

    /**
     * Check if video group has any data
     *
     * @param array $group_fields
     * @param int   $property_id
     * @return bool
     */
    private function video_group_has_data( $group_fields, $property_id ) {
        foreach ( $group_fields as $field ) {
            if ( $this->has_value( $this->get_group_field_value( $field, $property_id ) ) ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if gallery group has any data
     *
     * @param array $group_fields
     * @param int   $property_id
     * @return bool
     */
    private function gallery_group_has_data( $group_fields, $property_id ) {
        foreach ( $group_fields as $field ) {
            $meta_key = $field['name'] ?? $field['id'] ?? '';
            if ( empty( $meta_key ) ) {
                continue;
            }

            if ( strpos( $meta_key, '_images' ) === false && ( $field['metaKey'] ?? '' ) !== 'images' ) {
                continue;
            }

            $value = $this->get_group_field_value( $field, $property_id );
            if ( ! $this->has_value( $value ) ) {
                continue;
            }

            $images = is_string( $value ) ? explode( ',', $value ) : (array) $value;
            $images = array_filter( array_map( 'absint', $images ) );
            if ( ! empty( $images ) ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Stamp section_id onto builder field rows for isolated group resolution.
     *
     * @param array  $fields     Section fields.
     * @param string $section_id Section ID.
     * @return array
     */
    private function stamp_section_on_fields( array $fields, string $section_id ): array {
        if ( $section_id === '' ) {
            return $fields;
        }

        foreach ( $fields as $index => $field ) {
            if ( ! is_array( $field ) ) {
                continue;
            }
            if ( empty( $field['section_id'] ) ) {
                $fields[ $index ]['section_id'] = $section_id;
            }
        }

        return $fields;
    }

    /**
     * Identify all unique groups in fields.
     *
     * @param array $fields
     * @return array
     */
    private function identify_groups( $fields ) {
        $groups = array();

        foreach ( $fields as $field ) {
            if ( ! empty( $field['group_id'] ) ) {
                $groups[ $field['group_id'] ] = array(
                    'id' => $field['group_id'],
                    'type' => $field['group_type'] ?? $this->detect_group_type( $field ),
                    'base_id' => $field['group_base_id'] ?? null,
                );
            }
        }

        return $groups;
    }

    /**
     * Detect group type from field.
     *
     * @param array $field
     * @return string
     */
    private function detect_group_type( $field ) {
        $type       = $field['type'] ?? '';
        $name       = $field['name'] ?? $field['id'] ?? '';
        $group_type = $field['group_type'] ?? '';

        if ( ! empty( $group_type ) ) {
            return $group_type;
        }

        if ( $type === 'map' || strpos( $name, 'map_' ) === 0 ) {
            return 'map';
        }
        if ( $type === 'video' || strpos( $name, 'video_' ) === 0 ) {
            return 'video';
        }
        if ( $type === 'gallery' || strpos( $name, 'gallery_' ) === 0 ) {
            return 'gallery';
        }
        if ( $type === 'property_docs' || strpos( $name, 'property_docs_' ) === 0 ) {
            return 'property_docs';
        }
        if ( $type === 'agents' || strpos( $name, 'agents_' ) === 0 ) {
            return 'agents';
        }
        if ( $type === 'faq' || strpos( $name, 'faq_' ) === 0 ) {
            return 'faq';
        }
        if ( $type === 'repeater' || strpos( $name, 'repeater_' ) === 0 ) {
            return 'repeater';
        }

        return 'unknown';
    }

    /**
     * Get all fields belonging to a group.
     *
     * @param array  $fields
     * @param string $group_id
     * @return array
     */
    private function get_group_fields( $fields, $group_id ) {
        $group_fields = array();

        foreach ( $fields as $field ) {
            if ( ( $field['group_id'] ?? '' ) === $group_id ) {
                $group_fields[] = $field;
            }
        }

        usort( $group_fields, function ( $a, $b ) {
            $a_pos = (int) ( $a['group_position'] ?? 0 );
            $b_pos = (int) ( $b['group_position'] ?? 0 );
            return $a_pos - $b_pos;
        });

        return $group_fields;
    }

    /**
     * Check if a group has any data.
     *
     * @param array $group_fields
     * @param int   $property_id
     * @return bool
     */
    private function group_has_data( $group_fields, $property_id ) {
        foreach ( $group_fields as $field ) {
            $value = $this->get_group_field_value( $field, $property_id );
            if ( $this->has_value( $value ) ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get group field value with backward compatibility.
     *
     * @param array $field
     * @param int   $property_id
     * @return mixed
     */
    private function get_group_field_value( $field, $property_id ) {
        $meta_key = $field['name'] ?? $field['id'] ?? '';

        if ( empty( $meta_key ) ) {
            return null;
        }

        if ( function_exists( 'hvnly_resolve_field_meta' ) ) {
            return hvnly_resolve_field_meta( (int) $property_id, $field, $meta_key );
        }

        return get_post_meta( $property_id, $meta_key, true );
    }

    /**
     * Render a group using existing templates.
     *
     * @param array $group_fields
     * @param int   $property_id
     */
    private function render_group( $group_fields, $property_id ) {
        if ( empty( $group_fields ) ) {
            return;
        }

        $first_field = $group_fields[0];
        $group_type  = $first_field['group_type'] ?? $this->detect_group_type( $first_field );
        $group_name  = $first_field['group_name'] ?? '';
        if ( $group_name !== '' && function_exists( 'hvnly_translate_ui' ) ) {
            $group_name = hvnly_translate_ui( $group_name );
        }
        $group_base_id = $first_field['group_base_id'] ?? '';
        $group_id      = $first_field['group_id'] ?? '';
        $section_id    = $first_field['section_id'] ?? '';

        if ( class_exists( '\HvnlyNab\Core\GroupFieldIdentity' ) ) {
            \HvnlyNab\Core\GroupFieldIdentity::log_group_identity( $first_field, 'render_group:' . $group_type );
        }

        // Extract ALL unique meta keys for this group
        $unique_keys = array();
        foreach ( $group_fields as $field ) {
            $meta_key = $field['name'] ?? $field['id'] ?? '';
            $metaKey  = $field['metaKey'] ?? '';
            if ( ! empty( $meta_key ) ) {
                $unique_keys[ $metaKey ] = $meta_key;
            }
        }

        // Get the actual stored values using the unique meta keys
        $values = array();
        foreach ( $group_fields as $field ) {
            $meta_key = $field['name'] ?? $field['id'] ?? '';
            $metaKey  = $field['metaKey'] ?? '';
            if ( ! empty( $meta_key ) ) {
                $values[ $metaKey ] = function_exists( 'hvnly_resolve_field_meta' )
                    ? hvnly_resolve_field_meta( (int) $property_id, $field, $meta_key )
                    : get_post_meta( $property_id, $meta_key, true );
            }
        }

        // Find the specific keys for this group
        $title_key     = '';
        $url_key       = '';
        $thumbnail_key = '';
        $images_key    = '';
        $address_key   = '';
        $lat_key       = '';
        $lng_key       = '';
        $icon_key      = '';
        $label_key     = '';

        foreach ( $group_fields as $field ) {
            $meta_key = $field['name'] ?? $field['id'] ?? '';
            $metaKey  = $field['metaKey'] ?? '';

            if ( $metaKey === 'title' || strpos( $meta_key, '_title' ) !== false ) {
                $title_key = $meta_key;
            }
            if ( $metaKey === 'url' || strpos( $meta_key, '_url' ) !== false ) {
                $url_key = $meta_key;
            }
            if ( $metaKey === 'thumbnail' || strpos( $meta_key, '_thumbnail' ) !== false ) {
                $thumbnail_key = $meta_key;
            }
            if ( $metaKey === 'images' || strpos( $meta_key, '_images' ) !== false ) {
                $images_key = $meta_key;
            }
            if ( $metaKey === 'address' || strpos( $meta_key, '_address' ) !== false ) {
                $address_key = $meta_key;
            }
            if ( $metaKey === 'latitude' || strpos( $meta_key, '_latitude' ) !== false ) {
                $lat_key = $meta_key;
            }
            if ( $metaKey === 'longitude' || strpos( $meta_key, '_longitude' ) !== false ) {
                $lng_key = $meta_key;
            }
            if ( $metaKey === 'icon' || strpos( $meta_key, '_icon' ) !== false ) {
                $icon_key = $meta_key;
            }
            if ( $metaKey === 'label' || strpos( $meta_key, '_label' ) !== false ) {
                $label_key = $meta_key;
            }
        }

        // Get show_in_sidebar setting
        $show_in_sidebar = true;
        foreach ( $group_fields as $field ) {
            if ( isset( $field['show_in_sidebar'] ) ) {
                $show_in_sidebar = (bool) $field['show_in_sidebar'];
                break;
            }
        }

        $group_data = array(
            'property_id' => $property_id,
            'fields' => $group_fields,
            'group_name' => $group_name,
            'group_type' => $group_type,
            'group_base_id' => $group_base_id,
            'group_id' => $group_id,
            'section_id' => $section_id,
            'values' => $values,
            'unique_keys' => $unique_keys,
            'title_key' => $title_key,
            'url_key' => $url_key,
            'thumbnail_key' => $thumbnail_key,
            'images_key' => $images_key,
            'address_key' => $address_key,
            'lat_key' => $lat_key,
            'lng_key' => $lng_key,
            'icon_key' => $icon_key,
            'label_key' => $label_key,
            'show_in_sidebar' => $show_in_sidebar,
        );

        // Debug for video groups
        if ( $this->debug_mode && current_user_can( 'manage_options' ) && $group_type === 'video' ) {
            $this->log_debug( 'RENDER_GROUP - Video: base_id=' . $group_base_id . ', url_key=' . $url_key . ', url_value=' . ( $values['url'] ?? 'empty' ) );
        }

        // Check if group has data and render appropriate template
        switch ( $group_type ) {
            case 'map':
                $has_location = ( ! empty( $values['latitude'] ) && ! empty( $values['longitude'] ) ) || ! empty( $values['address'] );
                if ( $has_location ) {
                    hvnly_get_template( 'single-property/location-card.php', $group_data );
                }
                break;

            case 'video':
                if ( ! empty( $values['url'] ) ) {
                    hvnly_get_template( 'single-property/video-card.php', $group_data );
                }
                break;

            case 'gallery':
                if ( ! empty( $values['images'] ) ) {
                    hvnly_get_template( 'single-property/gallery-card.php', $group_data );
                }
                break;

            case 'property_docs':
                // Get documents data for this specific group
                $documents         = array();
                $docs_field_name   = $group_base_id . '_documents';
                $docs_field_config = $this->build_group_field_cfg(
                    array(
                        'section_id'    => $section_id,
                        'group_type'    => 'property_docs',
                        'group_base_id' => $group_base_id,
                        'group_id'      => $group_id,
                    ),
                    $docs_field_name,
                    'documents'
                );
                $docs_json         = function_exists( 'hvnly_resolve_field_meta' )
                    ? hvnly_resolve_field_meta( (int) $property_id, $docs_field_config, $docs_field_name )
                    : get_post_meta( $property_id, $docs_field_name, true );

                if ( ! empty( $docs_json ) ) {
                    $decoded = json_decode( $docs_json, true );
                    if ( is_array( $decoded ) ) {
                        $documents = $decoded;
                    }
                }

                // Also check individual fields for backward compatibility
                if ( empty( $documents ) && ! empty( $icon_key ) && ! empty( $label_key ) && ! empty( $url_key ) ) {
                    $doc_identity = array(
                        'section_id'    => $section_id,
                        'group_type'    => 'property_docs',
                        'group_base_id' => $group_base_id,
                        'group_id'      => $group_id,
                    );
                    $icons        = function_exists( 'hvnly_resolve_field_meta' )
                        ? hvnly_resolve_field_meta( (int) $property_id, $this->build_group_field_cfg( $doc_identity, $icon_key, 'icon' ), $icon_key )
                        : get_post_meta( $property_id, $icon_key, true );
                    $labels       = function_exists( 'hvnly_resolve_field_meta' )
                        ? hvnly_resolve_field_meta( (int) $property_id, $this->build_group_field_cfg( $doc_identity, $label_key, 'label' ), $label_key )
                        : get_post_meta( $property_id, $label_key, true );
                    $urls         = function_exists( 'hvnly_resolve_field_meta' )
                        ? hvnly_resolve_field_meta( (int) $property_id, $this->build_group_field_cfg( $doc_identity, $url_key, 'url' ), $url_key )
                        : get_post_meta( $property_id, $url_key, true );

                    if ( ! empty( $labels ) && ! empty( $urls ) ) {
                        $icon_array  = is_array( $icons ) ? $icons : ( $icons ? array( $icons ) : array() );
                        $label_array = is_array( $labels ) ? $labels : ( $labels ? array( $labels ) : array() );
                        $url_array   = is_array( $urls ) ? $urls : ( $urls ? array( $urls ) : array() );

                        $count = max( count( $label_array ), count( $url_array ) );
                        for ( $i = 0; $i < $count; $i++ ) {
                            $label = isset( $label_array[ $i ] ) ? $label_array[ $i ] : '';
                            $url   = isset( $url_array[ $i ] ) ? $url_array[ $i ] : '';
                            if ( ! empty( $label ) && ! empty( $url ) ) {
                                $documents[] = array(
                                    'icon' => isset( $icon_array[ $i ] ) ? $icon_array[ $i ] : 'file-pdf',
                                    'label' => function_exists( 'hvnly_translate_ui' ) ? hvnly_translate_ui( (string) $label ) : $label,
                                    'url' => $url,
                                    'url_type' => 'custom',
                                );
                            }
                        }
                    }
                }

                if ( ! empty( $documents ) ) {
                    $group_data['documents'] = $documents;
                    hvnly_get_template( 'single-property/fields/group/property_docs.php', $group_data );
                }
                break;

            case 'agents':
                $agents_field_cfg = $this->build_group_field_cfg(
                    array(
                        'section_id'    => $section_id,
                        'group_type'    => 'agents',
                        'group_base_id' => $group_base_id,
                        'group_id'      => $group_id,
                    ),
                    $group_base_id . '_agents',
                    'agents'
                );
                $agent_ids        = $this->parse_agents_group_ids( $values['agents'] ?? '', $group_base_id, $property_id, $agents_field_cfg );
                $agents           = $this->resolve_agents_for_section( $agent_ids );

                if ( ! empty( $agents ) ) {
                    $raw_title = isset( $values['title'] ) ? $values['title'] : '';
                    if ( is_array( $raw_title ) || ( is_string( $raw_title ) && in_array( trim( $raw_title ), array( '[]', '{}', 'null' ), true ) ) ) {
                        $raw_title = '';
                    }
                    $section_title = $this->has_value( $raw_title )
                        ? (string) $raw_title
                        : ( $group_name ?: __( 'Agents', 'havenlytics' ) );

                    $group_data['section_title']     = $section_title;
                    $group_data['agents']            = $agents;
                    $group_data['agent_ids']         = $agent_ids;
                    $group_data['show_contact_form'] = function_exists( 'hvnly_is_contact_agent_enabled' ) && hvnly_is_contact_agent_enabled();
                    $group_data['property_title']    = get_the_title( $property_id );

                    if ( class_exists( '\HvnlyNab\Agent\PropertyAgentWidgetRenderer' ) ) {
                        \HvnlyNab\Agent\PropertyAgentWidgetRenderer::enqueue_section_assets( (bool) $group_data['show_contact_form'] );
                    }

                    hvnly_get_template( 'single-property/fields/group/agents-section.php', $group_data );
                }
                break;

            case 'faq':
                $faqs = $this->resolve_faq_group_items( $group_base_id, $group_id, $property_id, $values, $section_id );
                if ( ! empty( $faqs ) ) {
                    $group_data['faqs'] = $faqs;
                    hvnly_get_template( 'single-property/faq-card.php', $group_data );
                }
                break;

            case 'repeater':
                $items = $this->resolve_repeater_group_items( $group_base_id, $group_id, $property_id, $values, $section_id );
                if ( ! empty( $items ) ) {
                    $group_data['items'] = $items;
                    hvnly_get_template( 'single-property/repeater-card.php', $group_data );
                }
                break;

            default:
                // For other groups, render individual fields
                foreach ( $group_fields as $field ) {
                    $this->render_field( $field, $property_id );
                }
                break;
        }
    }

    /**
     * Extract metaKey from field
     *
     * @param array $field
     * @return string
     */
    private function extract_meta_key( $field ) {
        if ( ! empty( $field['metaKey'] ) ) {
            return $field['metaKey'];
        }

        $id    = $field['id'] ?? '';
        $parts = explode( '_', $id );
        return end( $parts );
    }

    /**
     * Get field category
     *
     * @param array $field
     * @return string
     */
    private function get_field_category( $field ) {
        if ( ! empty( $field['group_id'] ) || ! empty( $field['group_type'] ) ) {
            return 'group';
        }

        if ( ! empty( $field['id'] ) && strpos( $field['id'], 'preset_' ) === 0 ) {
            return 'preset';
        }

        return 'default';
    }

    /**
     * Get field template name
     *
     * @param array $field
     * @return string
     */
    private function get_field_template_name( $field ) {
        $field_type = $field['type'] ?? 'text';
        $category   = $this->get_field_category( $field );

        if ( $category === 'group' ) {
            return $field['group_type'] ?? $field_type;
        }

        if ( $category === 'preset' ) {
            $id   = $field['id'] ?? '';
            $name = str_replace( 'preset_hvnly_property_field_', '', $id );
            $name = str_replace( '_', '-', $name );

            $preset_map = array(
                'fullname' => 'fullname',
                'email' => 'email',
                'phone' => 'phone',
                'company' => 'company',
                'website' => 'website',
                'number-bedrooms' => 'bedrooms',
                'number-bathrooms' => 'bathrooms',
                'number-squarefeet' => 'squarefeet',
                'number-garage' => 'garage',
                'text-address' => 'address',
                'text-city' => 'city',
                'text-zipcode' => 'zipcode',
                'checkbox-pool' => 'pool',
                'checkbox-garden' => 'garden',
                'checkbox-garage' => 'garage-checkbox',
            );

            return $preset_map[ $name ] ?? $name;
        }

        return $field_type;
    }

    /**
     * Check if template exists
     *
     * @param string $template_path
     * @return bool
     */
    private function template_exists( $template_path ) {
        $theme_template  = get_stylesheet_directory() . '/havenlytics/' . $template_path;
        $plugin_template = HVNLYNAB_PATH . 'templates/' . $template_path;

        return file_exists( $theme_template ) || file_exists( $plugin_template );
    }

    /**
     * Render a single field using the enhanced template system
     *
     * @param array $field
     * @param int   $property_id
     */
    private function render_field( $field, $property_id ) {
        if ( isset( $field['enabled'] ) && ! $field['enabled'] ) {
            return;
        }

        $meta_key = $field['name'] ?? $field['id'] ?? '';
        if ( empty( $meta_key ) ) {
            return;
        }

        $value = $this->get_field_value( $field, $property_id );

        if ( ! $this->has_value( $value ) ) {
            return;
        }

        $category      = $this->get_field_category( $field );
        $template_name = $this->get_field_template_name( $field );

        $is_checkbox_repeater = false;

        if ( $field['type'] === 'checkbox' ) {
            if ( is_array( $value ) && ! empty( $value ) ) {
                $is_checkbox_repeater = true;
            } elseif ( is_string( $value ) ) {
                $decoded = json_decode( $value, true );
                if ( is_array( $decoded ) && ! empty( $decoded ) ) {
                    $is_checkbox_repeater = true;
                    $value                = $decoded;
                } elseif ( is_serialized( $value ) ) {
                    $unserialized = maybe_unserialize( $value );
                    if ( is_array( $unserialized ) && ! empty( $unserialized ) ) {
                        $is_checkbox_repeater = true;
                        $value                = $unserialized;
                    }
                } elseif ( strpos( $value, ',' ) !== false ) {
                    $items = array_map( 'trim', explode( ',', $value ) );
                    $items = array_filter( $items );
                    if ( count( $items ) > 1 ) {
                        $is_checkbox_repeater = true;
                        $value                = $items;
                    }
                }
            }
        }

        if ( $is_checkbox_repeater ) {
            $template_name = 'checkbox-repeater';
            $category      = 'preset';
        }

        if ( function_exists( 'hvnly_hydrate_select_field_options' ) ) {
            $field = hvnly_hydrate_select_field_options( $field );
        }

        $field_data = array(
            'field'       => $field,
            'value'       => $value,
            'field_value' => $value,
            'property_id' => $property_id,
            'field_name'  => $meta_key,
            'field_label' => function_exists( 'hvnly_translate_ui' )
                ? hvnly_translate_ui( (string) ( $field['label'] ?? '' ) )
                : ( $field['label'] ?? '' ),
            'field_type'  => $field['type'] ?? 'text',
            'category'    => $category,
            'field_options' => isset( $field['options'] ) ? $field['options'] : array(),
        );

        $template_path = 'single-property/fields/' . $category . '/' . $template_name . '.php';

        if ( $this->template_exists( $template_path ) ) {
            hvnly_get_template( $template_path, $field_data );
            return;
        }

        $old_template_map = array(
            'text'     => 'text-field',
            'textarea' => 'textarea-field',
            'number'   => 'number-field',
            'email'    => 'email-field',
            'url'      => 'url-field',
            'checkbox' => 'checkbox-field',
            'select'   => 'select-field',
            'image'    => 'image-field',
            'file'     => 'file-field',
            'date'     => 'date-field',
        );

        $old_template = $old_template_map[ $field['type'] ?? 'text' ] ?? 'fallback-field';
        hvnly_get_template( "single-property/fields/{$old_template}.php", $field_data );
    }

    /**
     * Get field value with backward compatibility
     *
     * @param array $field
     * @param int   $property_id
     * @return mixed
     */
    private function get_field_value( $field, $property_id ) {
        $meta_key = $field['name'] ?? $field['id'] ?? '';

        if ( empty( $meta_key ) ) {
            return null;
        }

        if ( function_exists( 'hvnly_resolve_field_meta' ) ) {
            $value = hvnly_resolve_field_meta( (int) $property_id, $field, $meta_key );
            if ( $value !== '' && $value !== false && $value !== null ) {
                return $value;
            }
            return null;
        }

        return get_post_meta( $property_id, $meta_key, true );
    }

    /**
     * Check if field is a document field
     *
     * @param array $field
     * @return bool
     */
    private function is_document_field( $field ) {
        return ( $field['group_type'] ?? '' ) === 'property_docs' ||
                ( $field['type'] ?? '' ) === 'property_docs' ||
                strpos( $field['name'] ?? '', 'property_docs_' ) === 0;
    }

    /**
     * Check if field is a gallery field
     *
     * @param array $field
     * @return bool
     */
    private function is_gallery_field( $field ) {
        return ( $field['group_type'] ?? '' ) === 'gallery' ||
                ( $field['type'] ?? '' ) === 'gallery' ||
                strpos( $field['name'] ?? '', 'gallery_' ) === 0;
    }

    /**
     * Check if field is a video field
     *
     * @param array $field
     * @return bool
     */
    private function is_video_field( $field ) {
        return ( $field['group_type'] ?? '' ) === 'video' ||
                ( $field['type'] ?? '' ) === 'video' ||
                strpos( $field['name'] ?? '', 'video_' ) === 0;
    }

    /**
     * Check if FAQ group has items.
     *
     * @param array $group_values  Group field values keyed by metaKey.
     * @param array $group_fields  Group field definitions.
     * @param int   $property_id   Property ID.
     * @return bool
     */
    private function faq_group_has_data( $group_values, $group_fields, $property_id ) {
        $first_field   = $group_fields[0] ?? array();
        $group_base_id = $first_field['group_base_id'] ?? '';
        $group_id      = $first_field['group_id'] ?? '';
        $section_id    = $first_field['section_id'] ?? '';

        return ! empty( $this->resolve_faq_group_items( $group_base_id, $group_id, $property_id, $group_values, $section_id ) );
    }

    /**
     * Check if repeater group has items.
     *
     * @param array $group_values  Group field values keyed by metaKey.
     * @param array $group_fields  Group field definitions.
     * @param int   $property_id   Property ID.
     * @return bool
     */
    private function repeater_group_has_data( $group_values, $group_fields, $property_id ) {
        $first_field   = $group_fields[0] ?? array();
        $group_base_id = $first_field['group_base_id'] ?? '';
        $group_id      = $first_field['group_id'] ?? '';
        $section_id    = $first_field['section_id'] ?? '';

        return ! empty( $this->resolve_repeater_group_items( $group_base_id, $group_id, $property_id, $group_values, $section_id ) );
    }

    /**
     * Build a scoped field config row for MetaResolver.
     *
     * @param array  $identity   section_id, group_type, group_base_id, group_id.
     * @param string $field_name Meta key.
     * @param string $meta_key   metaKey suffix.
     * @return array
     */
    private function build_group_field_cfg( array $identity, string $field_name, string $meta_key ): array {
        $field = array_merge(
            $identity,
            array(
                'metaKey' => $meta_key,
                'name'    => $field_name,
            )
        );

        if ( class_exists( '\HvnlyNab\Core\GroupFieldIdentity' ) ) {
            return \HvnlyNab\Core\GroupFieldIdentity::hydrate_field_identity( $field );
        }

        return $field;
    }

    /**
     * Resolve FAQ items for a group.
     *
     * @param string $group_base_id Group base ID.
     * @param string $group_id      Group ID.
     * @param int    $property_id   Property ID.
     * @param array  $values        Resolved group values.
     * @return array<int, array<string, string>>
     */
    private function resolve_faq_group_items( $group_base_id, $group_id, $property_id, $values, $section_id = '' ) {
        $field_name = $group_base_id . '_faqs';
        $field_cfg  = $this->build_group_field_cfg(
            array(
                'section_id'    => $section_id,
                'group_type'    => 'faq',
                'group_base_id' => $group_base_id,
                'group_id'      => $group_id,
            ),
            $field_name,
            'faqs'
        );

        $raw = $values['faqs'] ?? '';
        if ( empty( $raw ) ) {
            $raw = function_exists( 'hvnly_resolve_field_meta' )
                ? hvnly_resolve_field_meta( (int) $property_id, $field_cfg, $field_name )
                : get_post_meta( $property_id, $field_name, true );
        }

        return $this->decode_json_items( $raw );
    }

    /**
     * Resolve repeater items for a group.
     *
     * @param string $group_base_id Group base ID.
     * @param string $group_id      Group ID.
     * @param int    $property_id   Property ID.
     * @param array  $values        Resolved group values.
     * @return array<int, array<string, string>>
     */
    private function resolve_repeater_group_items( $group_base_id, $group_id, $property_id, $values, $section_id = '' ) {
        $field_name = $group_base_id . '_items';
        $field_cfg  = $this->build_group_field_cfg(
            array(
                'section_id'    => $section_id,
                'group_type'    => 'repeater',
                'group_base_id' => $group_base_id,
                'group_id'      => $group_id,
            ),
            $field_name,
            'items'
        );

        $raw = $values['items'] ?? '';
        if ( empty( $raw ) ) {
            $raw = function_exists( 'hvnly_resolve_field_meta' )
                ? hvnly_resolve_field_meta( (int) $property_id, $field_cfg, $field_name )
                : get_post_meta( $property_id, $field_name, true );
        }

        return $this->decode_json_items( $raw );
    }

    /**
     * Decode JSON meta into a non-empty item list.
     *
     * @param mixed $raw Raw meta value.
     * @return array
     */
    private function decode_json_items( $raw ) {
        if ( is_array( $raw ) ) {
            return array_values( array_filter( $raw, 'is_array' ) );
        }

        if ( is_string( $raw ) && '' !== $raw ) {
            $decoded = json_decode( $raw, true );
            if ( is_array( $decoded ) ) {
                return array_values( array_filter( $decoded, 'is_array' ) );
            }
        }

        return array();
    }

    /**
     * Check if agents group has assigned agents.
     *
     * @param array $group_values Group field values keyed by metaKey.
     * @return bool
     */
    private function agents_group_has_data( $group_values ) {
        if ( empty( $group_values['agents'] ) ) {
            return false;
        }

        $decoded = json_decode( (string) $group_values['agents'], true );
        if ( is_array( $decoded ) ) {
            return ! empty( array_filter( array_map( 'absint', $decoded ) ) );
        }

        return $this->has_value( $group_values['agents'] );
    }

    /**
     * Parse stored agent IDs for an agents group.
     *
     * @param mixed  $stored_value  Raw meta value.
     * @param string $group_base_id Group base ID.
     * @param int    $property_id   Property ID.
     * @return int[]
     */
    private function parse_agents_group_ids( $stored_value, $group_base_id, $property_id, $field_cfg = array() ) {
        if ( empty( $stored_value ) && ! empty( $group_base_id ) ) {
            $field_name = $group_base_id . '_agents';
            if ( ! empty( $field_cfg ) && function_exists( 'hvnly_resolve_field_meta' ) ) {
                $stored_value = hvnly_resolve_field_meta( (int) $property_id, $field_cfg, $field_name );
            } else {
                $stored_value = get_post_meta( $property_id, $field_name, true );
            }
        }

        if ( empty( $stored_value ) ) {
            if (
                ! empty( $field_cfg )
                && class_exists( '\HvnlyNab\Core\GroupFieldIdentity' )
                && \HvnlyNab\Core\GroupFieldIdentity::is_strictly_scoped_field( $field_cfg )
            ) {
                return array();
            }
            return array();
        }

        if ( is_array( $stored_value ) ) {
            return array_values( array_filter( array_map( 'absint', $stored_value ) ) );
        }

        $decoded = json_decode( (string) $stored_value, true );
        if ( is_array( $decoded ) ) {
            return array_values( array_filter( array_map( 'absint', $decoded ) ) );
        }

        return array();
    }

    /**
     * Load normalized agent profiles for section display.
     *
     * @param int[] $agent_ids Agent post IDs.
     * @return array<int, array<string, mixed>>
     */
    private function resolve_agents_for_section( array $agent_ids ) {
        $agents = array();

        foreach ( $agent_ids as $agent_id ) {
            if ( ! function_exists( 'hvnly_get_agent' ) ) {
                continue;
            }

            $profile = hvnly_get_agent( (int) $agent_id );
            if ( ! empty( $profile ) && ! empty( $profile['name'] ) ) {
                $profile['source'] = 'cpt';
                $agents[]          = $profile;
            }
        }

        return $agents;
    }

    /**
     * Check if field is a map field
     *
     * @param array $field
     * @return bool
     */
    private function is_map_field( $field ) {
        return ( $field['group_type'] ?? '' ) === 'map' ||
                ( $field['type'] ?? '' ) === 'map' ||
                strpos( $field['name'] ?? '', 'map_' ) === 0;
    }

    /**
     * Check if value has meaningful data.
     *
     * @param mixed $value
     * @return bool
     */
    private function has_value( $value ) {
        if ( is_null( $value ) || false === $value ) {
            return false;
        }

        if ( is_string( $value ) ) {
            $trim = trim( $value );
            if ( '' === $trim ) {
                return false;
            }
            // Empty JSON payloads must never count as content (or print as "[]").
            if ( in_array( $trim, array( '[]', '{}', 'null', '""' ), true ) ) {
                return false;
            }
            $decoded = json_decode( $trim, true );
            if ( is_array( $decoded ) && array() === $decoded ) {
                return false;
            }
            return true;
        }

        if ( is_array( $value ) ) {
            return ! empty( $value );
        }

        if ( is_numeric( $value ) ) {
            return true;
        }

        return ! empty( $value );
    }

    /**
     * Whether a builder section is the locked Basic Info tab (header already shows these fields).
     *
     * @param string $section_id Section array key from hvnly_property_builder.sections.
     * @param array  $section    Section configuration.
     * @return bool
     */
    private function is_basic_info_section( $section_id, $section ) {
        if ( ! is_array( $section ) ) {
            return false;
        }

        $tab = $section;
        if ( empty( $tab['id'] ) ) {
            $tab['id'] = (string) $section_id;
        }

        if ( class_exists( '\HvnlyNab\Api\Type\Builders\DnDSections' ) ) {
            return \HvnlyNab\Api\Type\Builders\DnDSections::is_basic_info_tab( $tab );
        }

        $title = $tab['title'] ?? '';
        $id    = $tab['id'] ?? '';

        return ! empty( $tab['required'] )
            || 'Basic Info' === $title
            || 'basic_info' === $id
            || 'sec_basic_info' === $id
            || \HvnlyNab\Core\SectionIdentity::SEC_PROPERTY_OVERVIEW === $id
            || 'basic_info' === (string) $section_id
            || 'sec_basic_info' === (string) $section_id
            || \HvnlyNab\Core\SectionIdentity::SEC_PROPERTY_OVERVIEW === (string) $section_id;
    }

    /**
     * Sort sections by their order property.
     *
     * @param array $sections
     * @return array
     */
    private function sort_sections_by_order( $sections ) {
        uasort( $sections, function ( $a, $b ) {
            $a_order = (int) ( $a['order'] ?? 999 );
            $b_order = (int) ( $b['order'] ?? 999 );
            return $a_order - $b_order;
        });

        return $sections;
    }

    /**
     * Sort fields by their order property.
     *
     * @param array $fields
     * @return array
     */
    private function sort_fields_by_order( $fields ) {
        usort( $fields, function ( $a, $b ) {
            $a_order = (int) ( $a['order'] ?? 999 );
            $b_order = (int) ( $b['order'] ?? 999 );
            return $a_order - $b_order;
        });

        return $fields;
    }
}
