<?php
namespace HvnlyNab\Database\Traits\Taxonomy_Fields;

// Exit if accessed directly.
if ( ! defined('ABSPATH')) {
    exit;
}
/**
 * Advanced Icon Manager Trait - Font Awesome Only
 */
trait Hvnly_Advanced_Icon_Manager {
    /**
     * Taxonomy slug for icon management
     *
     * @var string
     */
    protected $hvnly_taxonomy_slug;

    /**
     * Icon manager configuration - Font Awesome Only
     */
    private $hvnly_icon_config = array(
        'meta_key' => '_hvnly_advanced_icon_data',
        'field_name' => 'hvnly_advanced_icon_selection',
        'library' => 'font-awesome',
    );

    /**
     * Initialize the advanced icon manager
     */
    public function hvnly_initialize_icon_manager( $taxonomy_slug ) {
        $this->hvnly_taxonomy_slug = $taxonomy_slug;

        // Register all hooks
        $this->hvnly_register_icon_manager_hooks();

        // Add admin column for icons
        add_filter( "manage_edit-{$taxonomy_slug}_columns", array( $this, 'hvnly_add_icon_admin_column' ) );
        add_filter( "manage_{$taxonomy_slug}_custom_column", array( $this, 'hvnly_display_icon_admin_column' ), 10, 3 );
    }

    /**
     * Register all WordPress hooks for icon management
     */
    private function hvnly_register_icon_manager_hooks() {
        $slug = $this->hvnly_taxonomy_slug;

        // Form field hooks
        add_action( "{$slug}_add_form_fields", array( $this, 'hvnly_render_icon_selection_field' ) );
        add_action( "{$slug}_edit_form_fields", array( $this, 'hvnly_render_icon_editing_field' ) );

        // Data persistence hooks
        add_action( "created_{$slug}", array( $this, 'hvnly_persist_icon_selection' ) );
        add_action( "edited_{$slug}", array( $this, 'hvnly_update_icon_selection' ) );

        // Asset management hooks
        add_action( 'admin_enqueue_scripts', array( $this, 'hvnly_enqueue_icon_assets' ) );

        // AJAX handlers
        add_action( 'wp_ajax_hvnly_get_icon_library', array( $this, 'hvnly_handle_icon_library_request' ) );
        add_action( 'wp_ajax_hvnly_search_icons', array( $this, 'hvnly_handle_icon_search_request' ) );

        // Background color field hooks
        add_action( "{$slug}_add_form_fields", array( $this, 'hvnly_render_background_color_field' ) );
        add_action( "{$slug}_edit_form_fields", array( $this, 'hvnly_render_background_color_editing_field' ) );
        add_action( "created_{$slug}", array( $this, 'hvnly_persist_background_color' ) );
        add_action( "edited_{$slug}", array( $this, 'hvnly_update_background_color' ) );

        // Display option field hooks
        add_action( "{$slug}_add_form_fields", array( $this, 'hvnly_render_display_option_field' ) );
        add_action( "{$slug}_edit_form_fields", array( $this, 'hvnly_render_display_option_editing_field' ) );
        add_action( "created_{$slug}", array( $this, 'hvnly_persist_display_option' ) );
        add_action( "edited_{$slug}", array( $this, 'hvnly_update_display_option' ) );
    }

    /**
     * Render icon selection field for new terms
     */
    public function hvnly_render_icon_selection_field() {
        wp_nonce_field( 'hvnly_advanced_icon_nonce', 'hvnly_advanced_icon_nonce_field' );
        $field_name = esc_attr( $this->hvnly_icon_config['field_name'] );
        ?>
        <div class="form-field hvnly-term-advanced-icon-wrap">
            <label for="<?php echo esc_attr( $field_name ); ?>">
                <?php esc_html_e( 'Badge Icon', 'havenlytics' ); ?>
            </label>
            
            <div class="hvnly-advanced-icon-selector-container">
                <input type="hidden" 
                        id="<?php echo esc_attr( $field_name ); ?>" 
                        name="<?php echo esc_attr( $field_name ); ?>" 
                        value="">
                <input type="hidden" name="hvnly_icon_library" value="font-awesome">
                
                <div class="hvnly-icon-selection-interface">
                    <div class="hvnly-icon-preview-area" id="hvnly-icon-preview-area">
                        <div class="hvnly-icon-placeholder">
                            <i class="fas fa-icons"></i>
                            <span><?php esc_html_e( 'No icon selected', 'havenlytics' ); ?></span>
                        </div>
                    </div>
                    
                    <div class="hvnly-icon-controls">
                        <button type="button" 
                                class="button button-primary hvnly-icon-picker-trigger" 
                                data-target="<?php echo esc_attr( $field_name ); ?>">
                            <i class="fas fa-palette"></i>
                            <?php esc_html_e( 'Select Icon', 'havenlytics' ); ?>
                        </button>
                        
                        <button type="button" 
                                class="button button-secondary hvnly-icon-clear-trigger"
                                style="display: none;">
                            <i class="fas fa-times"></i>
                            <?php esc_html_e( 'Clear', 'havenlytics' ); ?>
                        </button>
                    </div>
                </div>
            </div>
            
            <p class="description">
                <?php esc_html_e( 'Choose a Font Awesome icon for this badge.', 'havenlytics' ); ?>
            </p>
        </div>
        <?php
    }

    /**
     * Render icon editing field for existing terms
     *
     * @param WP_Term $term Term object.
     */
    public function hvnly_render_icon_editing_field( $term ) {
        wp_nonce_field( 'hvnly_advanced_icon_nonce', 'hvnly_advanced_icon_nonce_field' );

        $icon_data    = $this->hvnly_retrieve_icon_data( $term->term_id );
        $icon_class   = $icon_data['class'] ?? '';
        $icon_library = $icon_data['library'] ?? 'font-awesome';
        $field_name   = esc_attr( $this->hvnly_icon_config['field_name'] );
        ?>
        <tr class="form-field hvnly-term-advanced-icon-wrap">
            <th scope="row">
                <label for="<?php echo esc_attr( $field_name ); ?>">
                    <?php esc_html_e( 'Badge Icon', 'havenlytics' ); ?>
                </label>
            </th>
            <td>
                <div class="hvnly-advanced-icon-selector-container">
                    <input type="hidden" 
                            id="<?php echo esc_attr( $field_name ); ?>" 
                            name="<?php echo esc_attr( $field_name ); ?>" 
                            value="<?php echo esc_attr( $icon_class ); ?>">
                    <input type="hidden" 
                            name="hvnly_icon_library" 
                            value="<?php echo esc_attr( $icon_library ); ?>">
                    
                    <div class="hvnly-icon-selection-interface">
                        <div class="hvnly-icon-preview-area" id="hvnly-icon-preview-area">
                            <?php if ( ! empty( $icon_class ) ) : ?>
                                <div class="hvnly-icon-selected">
                                    <i class="<?php echo esc_attr( $icon_class ); ?>"></i>
                                    <span class="hvnly-icon-name">
                                        <?php echo esc_html( $icon_class ); ?>
                                    </span>
                                </div>
                            <?php else : ?>
                                <div class="hvnly-icon-placeholder">
                                    <i class="fas fa-icons"></i>
                                    <span><?php esc_html_e( 'No icon selected', 'havenlytics' ); ?></span>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="hvnly-icon-controls">
                            <button type="button" 
                                    class="button button-primary hvnly-icon-picker-trigger" 
                                    data-target="<?php echo esc_attr( $field_name ); ?>">
                                <i class="fas fa-palette"></i>
                                <?php esc_html_e( 'Select Icon', 'havenlytics' ); ?>
                            </button>
                            
                            <button type="button" 
                                    class="button button-secondary hvnly-icon-clear-trigger"
                                    <?php echo empty( $icon_class ) ? 'style="display: none;"' : ''; ?>>
                                <i class="fas fa-times"></i>
                                <?php esc_html_e( 'Clear', 'havenlytics' ); ?>
                            </button>
                        </div>
                    </div>
                </div>
                
                <p class="description">
                    <?php esc_html_e( 'Choose a Font Awesome icon for this badge.', 'havenlytics' ); ?>
                </p>
            </td>
        </tr>
        <?php
    }

    /**
     * Default badge color from Global Colors.
     *
     * @return string
     */
    private function hvnly_get_badge_color_default() {
        return function_exists( 'hvnly_get_default_badge_color' )
            ? hvnly_get_default_badge_color()
            : ( function_exists( 'hvnly_get_brand_color' ) ? hvnly_get_brand_color() : '#6C60FE' );
    }

    /**
     * Render background color field for new terms
     */
    public function hvnly_render_background_color_field() {
        $default_color = $this->hvnly_get_badge_color_default();
        ?>
        <div class="form-field hvnly-term-background-color-wrap">
            <label for="hvnly_badge_background_color">
                <?php esc_html_e( 'Badge Background Color', 'havenlytics' ); ?>
            </label>
            
            <input type="text" 
                    id="hvnly_badge_background_color" 
                    name="hvnly_badge_background_color" 
                    value="<?php echo esc_attr( $default_color ); ?>" 
                    class="hvnly-color-picker-field" 
                    data-default-color="<?php echo esc_attr( $default_color ); ?>">
            
            <p class="description">
                <?php esc_html_e( 'Choose a background color for this badge.', 'havenlytics' ); ?>
            </p>
        </div>
        <?php
    }

    /**
     * Render background color editing field for existing terms
     *
     * @param WP_Term $term Term object.
     */
    public function hvnly_render_background_color_editing_field( $term ) {
        $background_color = get_term_meta( $term->term_id, 'hvnly_badge_background_color', true );
        $default_color    = $this->hvnly_get_badge_color_default();
        ?>
        <tr class="form-field hvnly-term-background-color-wrap">
            <th scope="row">
                <label for="hvnly_badge_background_color">
                    <?php esc_html_e( 'Badge Background Color', 'havenlytics' ); ?>
                </label>
            </th>
            <td>
                <input type="text" 
                        id="hvnly_badge_background_color" 
                        name="hvnly_badge_background_color" 
                        value="<?php echo esc_attr( $background_color ?: $default_color ); ?>" 
                        class="hvnly-color-picker-field" 
                        data-default-color="<?php echo esc_attr( $default_color ); ?>">
                
                <p class="description">
                    <?php esc_html_e( 'Choose a background color for this badge.', 'havenlytics' ); ?>
                </p>
            </td>
        </tr>
        <?php
    }

    /**
     * Render display option field for new terms
     */
    public function hvnly_render_display_option_field() {
        ?>
        <div class="form-field hvnly-term-display-option-wrap">
            <label for="hvnly_badge_display_option">
                <input type="checkbox" 
                        id="hvnly_badge_display_option" 
                        name="hvnly_badge_display_option" 
                        value="1" />
                <?php esc_html_e( 'Display all badges (disable dropdown)', 'havenlytics' ); ?>
            </label>
            
            <p class="description">
                <?php esc_html_e( 'Check this to display all badges instead of using dropdown for multiple badges.', 'havenlytics' ); ?>
            </p>
        </div>
        <?php
    }

    /**
     * Render display option editing field for existing terms
     *
     * @param WP_Term $term Term object.
     */
    public function hvnly_render_display_option_editing_field( $term ) {
        $display_option = get_term_meta( $term->term_id, 'hvnly_badge_display_option', true );
        $is_checked     = ! empty( $display_option ) ? 'checked="checked"' : '';
        ?>
        <tr class="form-field hvnly-term-display-option-wrap">
            <th scope="row">
                <label for="hvnly_badge_display_option">
                    <?php esc_html_e( 'Display Option', 'havenlytics' ); ?>
                </label>
            </th>
            <td>
                <label for="hvnly_badge_display_option">
                    <input type="checkbox" 
                            id="hvnly_badge_display_option" 
                            name="hvnly_badge_display_option" 
                            value="1" 
                            <?php echo esc_attr( $is_checked ); ?> />
                    <?php esc_html_e( 'Display all badges (disable dropdown)', 'havenlytics' ); ?>
                </label>
                
                <p class="description">
                    <?php esc_html_e( 'Check this to display all badges instead of using dropdown for multiple badges.', 'havenlytics' ); ?>
                </p>
            </td>
        </tr>
        <?php
    }

    /**
     * Save icon selection when creating new term
     *
     * @param int $term_id Term ID.
     */
    public function hvnly_persist_icon_selection( $term_id ) {
        if ( ! $this->hvnly_verify_icon_nonce() ) {
            return;
        }

        $this->hvnly_process_icon_submission( $term_id );
    }

    /**
     * Update icon selection when editing term
     *
     * @param int $term_id Term ID.
     */
    public function hvnly_update_icon_selection( $term_id ) {
        if ( ! $this->hvnly_verify_icon_nonce() ) {
            return;
        }

        $this->hvnly_process_icon_submission( $term_id );
    }

    /**
     * Save background color when creating new term
     *
     * @param int $term_id Term ID.
     */
    public function hvnly_persist_background_color( $term_id ) {
        $this->hvnly_process_background_color_submission( $term_id );
    }

    /**
     * Update background color when editing term
     *
     * @param int $term_id Term ID.
     */
    public function hvnly_update_background_color( $term_id ) {
        $this->hvnly_process_background_color_submission( $term_id );
    }

    /**
     * Save display option when creating new term
     *
     * @param int $term_id Term ID.
     */
    public function hvnly_persist_display_option( $term_id ) {
        $this->hvnly_process_display_option_submission( $term_id );
    }

    /**
     * Update display option when editing term
     *
     * @param int $term_id Term ID.
     */
    public function hvnly_update_display_option( $term_id ) {
        $this->hvnly_process_display_option_submission( $term_id );
    }

    /**
     * Process icon form submission
     *
     * @param int $term_id Term ID.
     */
    private function hvnly_process_icon_submission( $term_id ) {
        $field_name = $this->hvnly_icon_config['field_name'];

        // Use filter_input for all POST data
        $icon_class_raw = filter_input(INPUT_POST, $field_name, FILTER_UNSAFE_RAW);
        $icon_class     = $icon_class_raw ? sanitize_text_field($icon_class_raw) : '';

        $icon_library_raw = filter_input(INPUT_POST, 'hvnly_icon_library', FILTER_UNSAFE_RAW);
        $icon_library     = $icon_library_raw ? sanitize_text_field($icon_library_raw) : 'font-awesome';

        $icon_data = array(
            'class'       => $icon_class,
            'library'     => $icon_library,
            'selected_at' => current_time( 'mysql' ),
            'version'     => '2.0.0',
        );

        if ( ! empty( $icon_class ) ) {
            update_term_meta( $term_id, $this->hvnly_icon_config['meta_key'], $icon_data );
        } else {
            delete_term_meta( $term_id, $this->hvnly_icon_config['meta_key'] );
        }
    }

    /**
     * Process background color form submission
     *
     * @param int $term_id Term ID.
     */
    private function hvnly_process_background_color_submission( $term_id ) {
        $background_color_raw = filter_input(INPUT_POST, 'hvnly_badge_background_color', FILTER_UNSAFE_RAW);
        $background_color     = $background_color_raw ? sanitize_hex_color($background_color_raw) : '';

        if ( ! empty( $background_color ) ) {
            update_term_meta( $term_id, 'hvnly_badge_background_color', $background_color );
        } else {
            delete_term_meta( $term_id, 'hvnly_badge_background_color' );
        }
    }

    /**
     * Process display option form submission
     *
     * @param int $term_id Term ID.
     */
    private function hvnly_process_display_option_submission( $term_id ) {
        $display_option_raw = filter_input(INPUT_POST, 'hvnly_badge_display_option', FILTER_UNSAFE_RAW);
        $display_option     = $display_option_raw === '1' ? '1' : '0';
        update_term_meta( $term_id, 'hvnly_badge_display_option', $display_option );
    }

    /**
     * Verify nonce for security
     *
     * @return bool
     */
    private function hvnly_verify_icon_nonce() {
        $nonce_raw = filter_input(INPUT_POST, 'hvnly_advanced_icon_nonce_field', FILTER_UNSAFE_RAW);
        $nonce     = $nonce_raw ? sanitize_text_field($nonce_raw) : '';

        return $nonce && wp_verify_nonce( $nonce, 'hvnly_advanced_icon_nonce' );
    }

    /**
     * Retrieve icon data for a term
     *
     * @param int $term_id Term ID.
     * @return array
     */
    private function hvnly_retrieve_icon_data( $term_id ) {
        $icon_data = get_term_meta( $term_id, $this->hvnly_icon_config['meta_key'], true );
        return is_array( $icon_data ) ? $icon_data : array();
    }

    /**
     * Enqueue required assets - Local Font Awesome
     *
     * @param string $hook Current admin page hook.
     */
    public function hvnly_enqueue_icon_assets( $hook ) {
        if ( ! $this->hvnly_is_taxonomy_admin_page() ) {
            return;
        }

        // Enqueue core dependencies
        wp_enqueue_script( 'jquery' );
        wp_enqueue_style( 'wp-color-picker' );
        wp_enqueue_script( 'wp-color-picker' );

        // Enqueue custom assets
        wp_enqueue_style(
            'hvnly-advanced-icon-picker',
            $this->hvnly_get_asset_url( 'css/hvnly-advanced-icon-picker.css' ),
            array( 'hvnly-admin-fontawesome-all' ),
            defined( 'HVNLYNAB_VERSION' ) ? HVNLYNAB_VERSION : '1.0.0'
        );

        wp_enqueue_script(
            'hvnly-advanced-icon-picker',
            $this->hvnly_get_asset_url( 'js/hvnly-advanced-icon-picker.js' ),
            array( 'jquery', 'wp-util', 'wp-color-picker' ),
            defined( 'HVNLYNAB_VERSION' ) ? HVNLYNAB_VERSION : '1.0.0',
            true
        );

        // Localize script with configuration
        wp_localize_script(
            'hvnly-advanced-icon-picker',
            'hvnlyAdvancedIconPicker',
            array(
                'ajaxurl'   => admin_url( 'admin-ajax.php' ),
                'nonce'     => wp_create_nonce( 'hvnly_advanced_icon_picker_nonce' ),
                'library'   => 'font-awesome',
                'assetsUrl' => HVNLYNAB_ASSETS_URL . '/admin/icon-picker/',
                'i18n'      => array(
                    /* translators: Button text for selecting an icon */
                    'selectIcon'     => __( 'Select Icon', 'havenlytics' ),
                    /* translators: Placeholder text for icon search input */
                    'searchIcons'    => __( 'Search Font Awesome icons...', 'havenlytics' ),
                    /* translators: Message when no icons match search */
                    'noResults'      => __( 'No icons found', 'havenlytics' ),
                    /* translators: Loading message */
                    'loading'        => __( 'Loading...', 'havenlytics' ),
                    /* translators: Cancel button text */
                    'cancel'         => __( 'Cancel', 'havenlytics' ),
                    /* translators: Select button text */
                    'select'         => __( 'Select', 'havenlytics' ),
                    /* translators: Message when no icon is selected */
                    'noIconSelected' => __( 'No icon selected', 'havenlytics' ),
                ),
            )
        );

        // Initialize color picker for background color field
        wp_add_inline_script( 'wp-color-picker', '
            jQuery(document).ready(function($) {
                $(".hvnly-color-picker-field").wpColorPicker();
            });
        ' );
    }

    /**
     * Check if current page is taxonomy admin page
     *
     * @return bool
     */
    private function hvnly_is_taxonomy_admin_page() {
        $screen = get_current_screen();
        return $screen && $screen->taxonomy === $this->hvnly_taxonomy_slug;
    }

    /**
     * Get asset URL with fallback
     *
     * @param string $path Asset path.
     * @return string
     */
    private function hvnly_get_asset_url( $path ) {
        return HVNLYNAB_ASSETS_URL . '/admin/icon-picker/' . ltrim( $path, '/' );
    }

    /**
     * Add icon column to admin list
     *
     * @param array $columns Existing columns.
     * @return array
     */
    public function hvnly_add_icon_admin_column( $columns ) {
        $new_columns = array();

        foreach ( $columns as $key => $value ) {
            $new_columns[ $key ] = $value;
            if ( 'name' === $key ) {
                /* translators: Column header for icon display */
                $new_columns['hvnly_term_icon'] = __( 'Icon', 'havenlytics' );
            }
        }

        return $new_columns;
    }

    /**
     * Display icon in admin column
     *
     * @param string $content     Column content.
     * @param string $column_name Column name.
     * @param int    $term_id     Term ID.
     * @return string
     */
    public function hvnly_display_icon_admin_column( $content, $column_name, $term_id ) {
        if ( 'hvnly_term_icon' !== $column_name ) {
            return $content;
        }

        $icon_data  = $this->hvnly_retrieve_icon_data( $term_id );
        $icon_class = $icon_data['class'] ?? '';

        if ( ! empty( $icon_class ) ) {
            $icon_color = esc_attr( $this->hvnly_get_badge_color_default() );
            return sprintf(
                '<i class="%s" style="font-size: 20px; color: %s;" title="%s"></i>',
                esc_attr( $icon_class ),
                $icon_color,
                esc_attr( $icon_class )
            );
        }

        return '<span class="dashicons dashicons-minus" style="color: #ccc;"></span>';
    }

    /**
     * AJAX handler for icon library requests
     */
    public function hvnly_handle_icon_library_request() {
        check_ajax_referer( 'hvnly_advanced_icon_picker_nonce', 'nonce' );

        try {
            $library_data = $this->hvnly_load_font_awesome_icons();

            // Ensure we have valid data
            if ( empty( $library_data ) ) {
                throw new Exception( 'No icons loaded from JSON file' );
            }

            // Validate structure
            if ( ! is_array( $library_data ) ) {
                throw new Exception( 'Icon data is not an array' );
            }

            wp_send_json_success( $library_data );

        } catch ( Exception $e ) {
            if ( function_exists( 'hvnly_debug_log' ) ) {
                hvnly_debug_log( 'AJAX Error: ' . $e->getMessage(), 'Havenlytics Icon Picker' );
            }
            // Return fallback icons on error
            wp_send_json_success( $this->hvnly_get_fallback_font_awesome_icons() );
        }
    }

    /**
     * AJAX handler for icon search
     */
    public function hvnly_handle_icon_search_request() {
        check_ajax_referer( 'hvnly_advanced_icon_picker_nonce', 'nonce' );

        //  Use filter_input for search parameter
        $search_term_raw = filter_input(INPUT_POST, 'search', FILTER_UNSAFE_RAW);
        $search_term     = $search_term_raw ? sanitize_text_field($search_term_raw) : '';

        $library_data   = $this->hvnly_load_font_awesome_icons();
        $filtered_icons = $this->hvnly_filter_icons_by_search( $library_data, $search_term );

        wp_send_json_success( $filtered_icons );
    }

    /**
     * Load Font Awesome icons from local JSON file
     *
     * @return array
     */
    private function hvnly_load_font_awesome_icons() {
        $json_file_path = HVNLYNAB_ASSETS_PATH . '/admin/icon-picker/css/libraries/font-awesome.min.json';

        if ( ! file_exists( $json_file_path ) ) {
            if ( function_exists( 'hvnly_debug_log' ) ) {
                hvnly_debug_log( 'Font Awesome JSON file not found at: ' . $json_file_path, 'Havenlytics Icon Picker' );
            }
            return $this->hvnly_get_fallback_font_awesome_icons();
        }

        $json_data  = file_get_contents( $json_file_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
        $icons_data = json_decode( $json_data, true );

        if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $icons_data ) ) {
            if ( function_exists( 'hvnly_debug_log' ) ) {
                hvnly_debug_log( 'JSON decode error: ' . json_last_error_msg(), 'Havenlytics Icon Picker' );
            }
            return $this->hvnly_get_fallback_font_awesome_icons();
        }

        // Parse the Font Awesome JSON structure
        return $this->hvnly_parse_font_awesome_json( $icons_data );
    }

    /**
     * Parse Font Awesome JSON structure
     *
     * @param array $icons_data Raw icons data.
     * @return array
     */
    private function hvnly_parse_font_awesome_json( $icons_data ) {
        $formatted_icons = array();

        // Process each style (solid, regular, brands)
        foreach ( $icons_data as $style => $style_data ) {
            if ( ! isset( $style_data['icons'] ) || ! is_array( $style_data['icons'] ) ) {
                continue;
            }

            $prefix = $style_data['prefix'] ?? 'fa-';

            foreach ( $style_data['icons'] as $icon_name ) {
                $icon_class   = $prefix . $icon_name;
                $display_name = ucwords( str_replace( array( '-', '_' ), ' ', $icon_name ) );

                $formatted_icons[] = array(
                    'name'  => $display_name,
                    'class' => $icon_class,
                    'style' => $style,
                );
            }
        }

        // Sort by name for better organization
        usort( $formatted_icons, function ( $a, $b ) {
            return strcmp( $a['name'], $b['name'] );
        } );

        return $formatted_icons;
    }

    /**
     * Fallback Font Awesome icons if JSON file is not available
     *
     * @return array
     */
    private function hvnly_get_fallback_font_awesome_icons() {
        return array(
            array(
				'name' => 'Star',
				'class' => 'fas fa-star',
				'style' => 'solid',
			),
            array(
				'name' => 'Heart',
				'class' => 'fas fa-heart',
				'style' => 'solid',
			),
            array(
				'name' => 'Home',
				'class' => 'fas fa-home',
				'style' => 'solid',
			),
            array(
				'name' => 'Building',
				'class' => 'fas fa-building',
				'style' => 'solid',
			),
            array(
				'name' => 'Tag',
				'class' => 'fas fa-tag',
				'style' => 'solid',
			),
            array(
				'name' => 'Award',
				'class' => 'fas fa-award',
				'style' => 'solid',
			),
            array(
				'name' => 'Certificate',
				'class' => 'fas fa-certificate',
				'style' => 'solid',
			),
            array(
				'name' => 'Crown',
				'class' => 'fas fa-crown',
				'style' => 'solid',
			),
            array(
				'name' => 'Gem',
				'class' => 'fas fa-gem',
				'style' => 'solid',
			),
            array(
				'name' => 'Fire',
				'class' => 'fas fa-fire',
				'style' => 'solid',
			),
            array(
				'name' => 'Bolt',
				'class' => 'fas fa-bolt',
				'style' => 'solid',
			),
            array(
				'name' => 'Flag',
				'class' => 'fas fa-flag',
				'style' => 'solid',
			),
            array(
				'name' => 'Check Circle',
				'class' => 'fas fa-check-circle',
				'style' => 'solid',
			),
            array(
				'name' => 'Exclamation Circle',
				'class' => 'fas fa-exclamation-circle',
				'style' => 'solid',
			),
            array(
				'name' => 'Info Circle',
				'class' => 'fas fa-info-circle',
				'style' => 'solid',
			),
            array(
				'name' => 'Trophy',
				'class' => 'fas fa-trophy',
				'style' => 'solid',
			),
            array(
				'name' => 'Medal',
				'class' => 'fas fa-medal',
				'style' => 'solid',
			),
            array(
				'name' => 'Shield Alt',
				'class' => 'fas fa-shield-alt',
				'style' => 'solid',
			),
            array(
				'name' => 'Umbrella Beach',
				'class' => 'fas fa-umbrella-beach',
				'style' => 'solid',
			),
            array(
				'name' => 'Mountain',
				'class' => 'fas fa-mountain',
				'style' => 'solid',
			),
            array(
				'name' => 'Tree',
				'class' => 'fas fa-tree',
				'style' => 'solid',
			),
            array(
				'name' => 'Sun',
				'class' => 'fas fa-sun',
				'style' => 'solid',
			),
            array(
				'name' => 'Snowflake',
				'class' => 'fas fa-snowflake',
				'style' => 'solid',
			),
            array(
				'name' => 'Cloud',
				'class' => 'fas fa-cloud',
				'style' => 'solid',
			),
            array(
				'name' => 'Car',
				'class' => 'fas fa-car',
				'style' => 'solid',
			),
            array(
				'name' => 'Key',
				'class' => 'fas fa-key',
				'style' => 'solid',
			),
            array(
				'name' => 'Bed',
				'class' => 'fas fa-bed',
				'style' => 'solid',
			),
            array(
				'name' => 'Bath',
				'class' => 'fas fa-bath',
				'style' => 'solid',
			),
            array(
				'name' => 'Ruler Combined',
				'class' => 'fas fa-ruler-combined',
				'style' => 'solid',
			),
            array(
				'name' => 'Wifi',
				'class' => 'fas fa-wifi',
				'style' => 'solid',
			),
            array(
				'name' => 'Swimming Pool',
				'class' => 'fas fa-swimming-pool',
				'style' => 'solid',
			),
            array(
				'name' => 'TV',
				'class' => 'fas fa-tv',
				'style' => 'solid',
			),
            array(
				'name' => 'Utensils',
				'class' => 'fas fa-utensils',
				'style' => 'solid',
			),
            array(
				'name' => 'Parking',
				'class' => 'fas fa-parking',
				'style' => 'solid',
			),
            array(
				'name' => 'Dog',
				'class' => 'fas fa-dog',
				'style' => 'solid',
			),
            array(
				'name' => 'Cat',
				'class' => 'fas fa-cat',
				'style' => 'solid',
			),
            array(
				'name' => 'User',
				'class' => 'fas fa-user',
				'style' => 'solid',
			),
            array(
				'name' => 'Users',
				'class' => 'fas fa-users',
				'style' => 'solid',
			),
            array(
				'name' => 'Phone',
				'class' => 'fas fa-phone',
				'style' => 'solid',
			),
            array(
				'name' => 'Envelope',
				'class' => 'fas fa-envelope',
				'style' => 'solid',
			),
            array(
				'name' => 'Map Marker Alt',
				'class' => 'fas fa-map-marker-alt',
				'style' => 'solid',
			),
            array(
				'name' => 'Calendar Alt',
				'class' => 'fas fa-calendar-alt',
				'style' => 'solid',
			),
            array(
				'name' => 'Clock',
				'class' => 'fas fa-clock',
				'style' => 'solid',
			),
            array(
				'name' => 'Dollar Sign',
				'class' => 'fas fa-dollar-sign',
				'style' => 'solid',
			),
            array(
				'name' => 'Euro Sign',
				'class' => 'fas fa-euro-sign',
				'style' => 'solid',
			),
            array(
				'name' => 'Pound Sign',
				'class' => 'fas fa-pound-sign',
				'style' => 'solid',
			),
            array(
				'name' => 'Chart Line',
				'class' => 'fas fa-chart-line',
				'style' => 'solid',
			),
            array(
				'name' => 'Shopping Bag',
				'class' => 'fas fa-shopping-bag',
				'style' => 'solid',
			),
            array(
				'name' => 'Gift',
				'class' => 'fas fa-gift',
				'style' => 'solid',
			),
            array(
				'name' => 'Camera',
				'class' => 'fas fa-camera',
				'style' => 'solid',
			),
        );
    }

    /**
     * Filter icons by search term
     *
     * @param array  $icons       Icons array.
     * @param string $search_term Search term.
     * @return array
     */
    private function hvnly_filter_icons_by_search( $icons, $search_term ) {
        if ( empty( $search_term ) ) {
            return $icons;
        }

        $search_term = strtolower( $search_term );

        return array_filter( $icons, function ( $icon ) use ( $search_term ) {
            $searchable_text = strtolower( $icon['name'] . ' ' . $icon['class'] . ' ' . ( $icon['style'] ?? '' ) );
            return strpos( $searchable_text, $search_term ) !== false;
        } );
    }
}