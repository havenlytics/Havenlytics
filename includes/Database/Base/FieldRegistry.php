<?php
/**
 * Field Type Registry
 * 
 * Handles registration and management of field type handlers
 * with individual JS file loading for the property metabox.
 *
 * @package     HvnlyNab\Database\Base
 * @author      Havenlytics
 * @copyright   Copyright (c) 2025, Havenlytics
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       2.0.0
 * @version     2.1.3
 */

namespace HvnlyNab\Database\Base;

// Security check - prevent direct access.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class FieldRegistry
 * 
 * Handles registration and management of field type handlers
 * with individual JS file loading for each field type.
 * 
 * @since 2.0.0
 */
class FieldRegistry {
    
    /**
     * Registered field type handlers.
     *
     * @since 2.0.0
     * @var array
     */
    private $field_types = [];
    
    /**
     * JS assets to enqueue for each field type.
     *
     * @since 2.0.0
     * @var array
     */
    private $js_assets = [];
    
    /**
     * Constructor - registers field types and assets.
     *
     * Sets up WordPress hooks for asset enqueuing and field type registration.
     *
     * @since 2.0.0
     */
    public function __construct() {
        // Register assets early for proper enqueuing.
        add_action('admin_enqueue_scripts', [$this, 'enqueue_field_assets'], 5);
        
        // Register field types after WordPress is fully initialized.
        add_action('admin_init', [$this, 'register_default_field_types']);
    }

    /**
     * Register default field types.
     *
     * Dynamically loads all built-in field type classes.
     *
     * @since 2.0.0
     * @return void
     */
    public function register_default_field_types() {
        // Map of field type identifiers to their handler classes.
        $field_classes = [
            'text'           => '\HvnlyNab\Database\FieldTypes\TextField',
            'textarea'       => '\HvnlyNab\Database\FieldTypes\TextareaField',
            'number'         => '\HvnlyNab\Database\FieldTypes\NumberField',
            'select'         => '\HvnlyNab\Database\FieldTypes\SelectField',
            'checkbox'       => '\HvnlyNab\Database\FieldTypes\CheckboxField',
            'file'           => '\HvnlyNab\Database\FieldTypes\FileField',
            'gallery'        => '\HvnlyNab\Database\FieldTypes\GalleryField',
            'map'            => '\HvnlyNab\Database\FieldTypes\MapField',
            'video'          => '\HvnlyNab\Database\FieldTypes\VideoField',
            'property_docs'  => '\HvnlyNab\Database\FieldTypes\DocumentField',
            'agents'         => '\HvnlyNab\Database\FieldTypes\AgentsField',
            'price_label'    => '\HvnlyNab\Database\FieldTypes\PriceLabelField',
            'url'            => '\HvnlyNab\Database\FieldTypes\UrlField',
            'date'           => '\HvnlyNab\Database\FieldTypes\DateField',
            'faq'            => '\HvnlyNab\Database\FieldTypes\FAQField',
            'repeater'       => '\HvnlyNab\Database\FieldTypes\RepeaterField',
        ];
        
        // Register each field type if the class exists.
        foreach ($field_classes as $type => $class) {
            if (class_exists($class)) {
                $this->register($type, $class);
            }
        }
    }
    
    /**
     * Register a single field type with its assets.
     *
     * @since 2.0.0
     * @param string $type         Field type identifier.
     * @param string $handler_class Fully qualified handler class name.
     * @return bool True on success, false on failure.
     */
    public function register($type, $handler_class) {
        try {
            // Instantiate the field handler.
            $this->field_types[$type] = new $handler_class();
            
            // Register field-specific JS assets.
            $this->register_field_assets($type);
            
            return true;
        } catch (\Throwable $e) {
            // Silent fail in production to prevent fatal errors.
            return false;
        }
    }
    
    /**
     * Register field type specific JavaScript assets.
     *
     * Defines the JS files needed for each field type's functionality.
     *
     * @since 2.0.0
     * @param string $type Field type identifier.
     * @return void
     */
    private function register_field_assets($type) {
        $assets_dir = HVNLYNAB_ASSETS_URL . '/admin/js/';
        $base_deps = ['jquery'];
        
        switch ($type) {
            case 'gallery':
                $this->js_assets['hvnly-gallery-field'] = [
                    'src'       => $assets_dir . 'hvnly-gallery-field.js',
                    'deps'      => array_merge($base_deps, ['jquery-ui-sortable']),
                    'ver'       => $this->get_file_version($assets_dir . 'hvnly-gallery-field.js'),
                    'in_footer' => true
                ];
                break;
                
            case 'map':
                $this->js_assets['hvnly-map-field'] = [
                    'src'       => $assets_dir . 'hvnly-map-field.js',
                    'deps'      => array_merge($base_deps, ['leaflet']),
                    'ver'       => $this->get_file_version($assets_dir . 'hvnly-map-field.js'),
                    'in_footer' => true
                ];
                break;
                
            case 'video':
                $this->js_assets['hvnly-video-field'] = [
                    'src'       => $assets_dir . 'hvnly-video-field.js',
                    'deps'      => $base_deps,
                    'ver'       => $this->get_file_version($assets_dir . 'hvnly-video-field.js'),
                    'in_footer' => true
                ];
                break;
                
            case 'file':
                $this->js_assets['hvnly-file-field'] = [
                    'src'       => $assets_dir . 'hvnly-file-field.js',
                    'deps'      => array_merge( $base_deps, array( 'media-upload', 'media-views' ) ),
                    'ver'       => $this->get_file_version($assets_dir . 'hvnly-file-field.js'),
                    'in_footer' => true
                ];
                break;
                
            case 'checkbox':
                $this->js_assets['hvnly-checkbox-repeater-field'] = [
                    'src'       => $assets_dir . 'hvnly-checkbox-repeater-field.js',
                    'deps'      => array_merge($base_deps, ['jquery-ui-sortable']),
                    'ver'       => $this->get_file_version($assets_dir . 'hvnly-checkbox-repeater-field.js'),
                    'in_footer' => true
                ];
                break;
                
            case 'property_docs':
                $this->js_assets['hvnly-document-field'] = [
                    'src'       => $assets_dir . 'hvnly-document-field.js',
                    'deps'      => array_merge($base_deps, ['jquery-ui-sortable']),
                    'ver'       => $this->get_file_version($assets_dir . 'hvnly-document-field.js'),
                    'in_footer' => true
                ];
                break;

            case 'agents':
                $this->js_assets['hvnly-agents-section-field'] = [
                    'src'       => $assets_dir . 'hvnly-agents-section-field.js',
                    'deps'      => $base_deps,
                    'ver'       => $this->get_file_version($assets_dir . 'hvnly-agents-section-field.js'),
                    'in_footer' => true
                ];
                break;

            case 'faq':
                $this->js_assets['hvnly-faq-field'] = [
                    'src'       => $assets_dir . 'hvnly-faq-field.js',
                    'deps'      => array_merge( $base_deps, array( 'jquery-ui-sortable' ) ),
                    'ver'       => $this->get_file_version( $assets_dir . 'hvnly-faq-field.js' ),
                    'in_footer' => true,
                ];
                break;

            case 'repeater':
                $this->js_assets['hvnly-repeater-field'] = [
                    'src'       => $assets_dir . 'hvnly-repeater-field.js',
                    'deps'      => array_merge( $base_deps, array( 'jquery-ui-sortable', 'hvnly-document-field' ) ),
                    'ver'       => $this->get_file_version( $assets_dir . 'hvnly-repeater-field.js' ),
                    'in_footer' => true,
                ];
                break;
        }
    }
    
    /**
     * Get file version based on file modification time.
     *
     * Uses filemtime() for cache-busting during development.
     * Falls back to plugin version if file not found.
     *
     * @since 2.0.0
     * @param string $file_url URL of the file.
     * @return string Version string for cache busting.
     */
    private function get_file_version($file_url) {
        $file_path = str_replace(HVNLYNAB_ASSETS_URL, HVNLYNAB_PATH . 'assets', $file_url);
        return file_exists($file_path) ? filemtime($file_path) : HVNLYNAB_VERSION;
    }
    
    /**
     * Enqueue field specific assets on the property edit screen.
     *
     * @since 2.0.0
     * @return void
     */
    public function enqueue_field_assets() {
        $screen = get_current_screen();
        if (!$screen) {
            return;
        }
        
        // Only enqueue on our property post type.
        $allowed_post_types = ['hvnly_property'];
        if (!in_array($screen->post_type ?? '', $allowed_post_types, true)) {
            return;
        }
        
        // Only on add/edit property screens (not list table).
        if (!in_array($screen->base, ['post', 'post-new'], true)) {
            return;
        }
        
        // Enqueue jQuery UI Sortable for reorderable fields.
        wp_enqueue_script('jquery-ui-sortable');

        // Ensure the WordPress media library is available for file/gallery/video fields.
        wp_enqueue_media();
        
        // Enqueue core JS files first (dependencies).
        $this->enqueue_core_assets();
        
        // Ensure Leaflet is registered before map field scripts (FieldRegistry runs at priority 5).
        if ( isset( $this->js_assets['hvnly-map-field'] ) && ! wp_script_is( 'leaflet', 'registered' ) ) {
            wp_register_script(
                'leaflet',
                HVNLYNAB_ASSETS_URL . '/admin/js/leaflet/core-leaflet.js',
                array(),
                '1.9.4',
                true
            );
        }

        // Enqueue field-specific JS after core.
        foreach ($this->js_assets as $handle => $asset) {
            wp_enqueue_script(
                $handle,
                $asset['src'],
                $asset['deps'],
                $asset['ver'],
                $asset['in_footer']
            );
        }
        
        // Enqueue field-specific CSS.
        $this->enqueue_field_css();
        
        // Pass data from PHP to JavaScript.
        $this->localize_scripts();
    }
    
    /**
     * Enqueue core assets required for all field types.
     *
     * @since 2.0.0
     * @return void
     */
    private function enqueue_core_assets() {
        $assets_dir = HVNLYNAB_ASSETS_URL . '/admin/js/';
        
        // 1. Field Registry (loads first - core functionality).
        wp_enqueue_script(
            'hvnly-admin-field-registry',
            $assets_dir . 'hvnly-admin-field-registry.js',
            ['jquery'],
            $this->get_file_version($assets_dir . 'hvnly-admin-field-registry.js'),
            true
        );
        
        // 2. Main metabox controller (depends on registry).
        wp_enqueue_script(
            'hvnly-admin-metabox',
            $assets_dir . 'hvnly-admin-metabox.js',
            ['jquery', 'hvnly-admin-field-registry'],
            $this->get_file_version($assets_dir . 'hvnly-admin-metabox.js'),
            true
        );
    }
    
    /**
     * Enqueue field-specific CSS styles.
     *
     * @since 2.0.0
     * @return void
     */
    private function enqueue_field_css() {
        $assets_dir = HVNLYNAB_ASSETS_URL . '/admin/';
        
        // Document field CSS.
        if (isset($this->js_assets['hvnly-document-field'])) {
            wp_enqueue_style(
                'hvnly-document-field',
                $assets_dir . 'css/hvnly-document-field.css',
                [],
                $this->get_file_version($assets_dir . 'css/hvnly-document-field.css')
            );
        }

        if (isset($this->js_assets['hvnly-agents-section-field'])) {
            wp_enqueue_style(
                'hvnly-agents-section-field',
                $assets_dir . 'css/hvnly-agents-section-field.css',
                array( 'hvnly-property-agent-assignment' ),
                $this->get_file_version($assets_dir . 'css/hvnly-agents-section-field.css')
            );
            wp_enqueue_style(
                'hvnly-property-agent-assignment',
                $assets_dir . 'css/hvnly-property-agent-assignment.css',
                array(),
                $this->get_file_version($assets_dir . 'css/hvnly-property-agent-assignment.css')
            );
        }

        if (isset($this->js_assets['hvnly-faq-field'])) {
            wp_enqueue_style(
                'hvnly-faq-field',
                $assets_dir . 'css/hvnly-faq-field.css',
                array( 'hvnly-admin-metabox' ),
                $this->get_file_version($assets_dir . 'css/hvnly-faq-field.css')
            );
        }

        if (isset($this->js_assets['hvnly-repeater-field'])) {
            wp_enqueue_style(
                'hvnly-repeater-field',
                $assets_dir . 'css/hvnly-repeater-field.css',
                array( 'hvnly-admin-metabox', 'hvnly-document-field' ),
                $this->get_file_version($assets_dir . 'css/hvnly-repeater-field.css')
            );
        }
        
        // Additional field CSS can be added here.
    }
    
    /**
     * Localize scripts with PHP data for JavaScript.
     *
     * Passes nonces, URLs, and configuration data to JS.
     *
     * @since 2.0.0
     * @return void
     */
    private function localize_scripts() {
        // Localize main metabox script.
        wp_localize_script(
            'hvnly-admin-metabox',
            'HvnlyNabMetaBox',
            [
                'security' => wp_create_nonce('hvnly-metabox-security'),
                'postType' => get_current_screen()->post_type ?? 'hvnly_property',
                'ajaxUrl'  => admin_url('admin-ajax.php'),
                'i18n'     => [
                    'ok'                    => __('OK', 'havenlytics'),
                    'requiredMissingTitle'  => __('Required Fields Missing', 'havenlytics'),
                    'requiredFieldsIntro'   => __('The following required fields are empty or invalid:', 'havenlytics'),
                    'numberGreaterThanZero' => __('(Please enter a number greater than 0)', 'havenlytics'),
                    'requiredFieldsHint'    => __('Red border indicates empty or invalid field. Please fill in all highlighted fields before saving.', 'havenlytics'),
                    'phpRequiredTitle'      => __('Please Complete Required Fields', 'havenlytics'),
                    'phpRequiredIntro'      => __('The following required fields need attention:', 'havenlytics'),
                    'phpZeroHint'           => __('Fields with value "0" are not accepted. Enter a number greater than 0.', 'havenlytics'),
                    'alertBanner'           => __('PLEASE COMPLETE REQUIRED FIELDS', 'havenlytics'),
                ],
            ]
        );
        
        // Localize field registry script.
        wp_localize_script(
            'hvnly-admin-field-registry',
            'HvnlyFieldRegistry',
            [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce'   => wp_create_nonce('hvnly-field-registry-nonce'),
                'i18n'    => [
                    'selectFile'       => __('Select File', 'havenlytics'),
                    'selectImage'      => __('Select Image', 'havenlytics'),
                    'selectPdf'        => __('Select PDF', 'havenlytics'),
                    'useThisFile'      => __('Use this file', 'havenlytics'),
                    'invalidImage'     => __('Please select a valid image file (JPG, PNG, GIF, WebP, BMP)', 'havenlytics'),
                    'invalidPdf'       => __('Please select a PDF file', 'havenlytics'),
                    'invalidImageOrPdf'=> __('Please select an image (JPG, PNG, GIF, WebP, BMP) or PDF file', 'havenlytics'),
                    'invalidFile'      => __('Please select a valid file', 'havenlytics'),
                ],
            ]
        );

        if (isset($this->js_assets['hvnly-map-field'])) {
            wp_localize_script(
                'hvnly-map-field',
                'hvnlyMapFieldParams',
                [
                    'brandColor' => function_exists('hvnly_get_brand_color') ? hvnly_get_brand_color() : '#6C60FE',
                    'i18n'       => [
                        'noAddressSet'       => __('No address set', 'havenlytics'),
                        'locationPopup'      => __('Location', 'havenlytics'),
                        'latLabel'           => __('Lat:', 'havenlytics'),
                        'lngLabel'           => __('Lng:', 'havenlytics'),
                        'enterAddressFirst'  => __('Please enter an address first.', 'havenlytics'),
                        'searching'          => __('Searching…', 'havenlytics'),
                        'locationFound'      => __('Location found!', 'havenlytics'),
                        'addressNotFound'    => __('Address not found.', 'havenlytics'),
                        'searchError'        => __('Error searching for address.', 'havenlytics'),
                        'getCoordinates'     => __('Get Coordinates from Address', 'havenlytics'),
                        'noAddressesFound'   => __('No addresses found', 'havenlytics'),
                    ],
                ]
            );
        }

        if (isset($this->js_assets['hvnly-gallery-field'])) {
            wp_localize_script(
                'hvnly-gallery-field',
                'HvnlyGalleryField',
                [
                    'i18n' => [
                        'mediaUnavailable' => __('Media library is not available.', 'havenlytics'),
                        'manageTitle'      => __('Manage Gallery Images', 'havenlytics'),
                        'updateGallery'    => __('Update Gallery', 'havenlytics'),
                        'editImage'        => __('Edit Image', 'havenlytics'),
                        'updateImage'      => __('Update Image', 'havenlytics'),
                        'editRemoveImage'  => __('Edit/Remove Image', 'havenlytics'),
                        'confirmClearAll'  => __('Are you sure you want to remove all images from the gallery?', 'havenlytics'),
                    ],
                ]
            );
        }

        if (isset($this->js_assets['hvnly-file-field'])) {
            wp_localize_script(
                'hvnly-file-field',
                'HvnlyFileField',
                [
                    'i18n' => [
                        'mediaUnavailable' => __('Media library is not available.', 'havenlytics'),
                        'selectImage'      => __('Select or Upload Image', 'havenlytics'),
                        'selectFile'       => __('Select or Upload File', 'havenlytics'),
                        'selectButton'     => __('Select File', 'havenlytics'),
                        'invalidPdf'       => __('Please select a valid PDF file.', 'havenlytics'),
                        'invalidImage'     => __('Please select a valid image file (JPG, PNG, GIF, WEBP, BMP).', 'havenlytics'),
                    ],
                ]
            );
        }

        if (isset($this->js_assets['hvnly-video-field'])) {
            wp_localize_script(
                'hvnly-video-field',
                'HvnlyVideoField',
                [
                    'i18n' => [
                        'mediaUnavailable' => __('Media library is not available.', 'havenlytics'),
                        'selectImage'      => __('Select or Upload Image', 'havenlytics'),
                        'useThisImage'     => __('Use this image', 'havenlytics'),
                        'selectButton'     => __('Select File', 'havenlytics'),
                        'invalidImage'     => __('Please select a valid image file (JPG, PNG, GIF, WEBP, BMP).', 'havenlytics'),
                    ],
                ]
            );
        }

        if (isset($this->js_assets['hvnly-faq-field'])) {
            wp_localize_script(
                'hvnly-faq-field',
                'HvnlyFaqField',
                [
                    'i18n' => [
                        'newFaq' => __('New FAQ', 'havenlytics'),
                    ],
                ]
            );
        }

        if (isset($this->js_assets['hvnly-repeater-field'])) {
            wp_localize_script(
                'hvnly-repeater-field',
                'HvnlyRepeaterField',
                [
                    'i18n' => [
                        'newRow' => __('New Row', 'havenlytics'),
                    ],
                ]
            );
        }
        
        // Localize document field script with icons and strings.
        if (isset($this->js_assets['hvnly-document-field'])) {
            wp_localize_script(
                'hvnly-document-field',
                'HvnlyDocumentField',
                [
                    'ajaxUrl' => admin_url('admin-ajax.php'),
                    'nonce'   => wp_create_nonce('hvnly-document-field-nonce'),
                    'icons'   => $this->get_font_awesome_icons(),
                    'strings' => [
                        'confirmRemove'    => __('Remove this document?', 'havenlytics'),
                        'confirmRemoveAll' => __('Remove all documents? This will clear all data.', 'havenlytics'),
                        'uploadTitle'      => __('Select Document', 'havenlytics'),
                        'uploadButton'     => __('Use this document', 'havenlytics'),
                        'preview'          => __('Preview', 'havenlytics'),
                        'yes'              => __('Yes', 'havenlytics'),
                        'no'               => __('No', 'havenlytics'),
                        'newDocument'      => __('New Document', 'havenlytics'),
                        'selectIcon'       => __('Select Icon', 'havenlytics'),
                        'searchIcons'      => __('Search icons…', 'havenlytics'),
                        'default'          => __('Default', 'havenlytics'),
                        'showInSidebar'    => __('Show in sidebar', 'havenlytics'),
                        'hideFromSidebar'  => __('Hide from sidebar', 'havenlytics'),
                        'mediaUnavailable' => __('Media uploader is not available.', 'havenlytics'),
                    ],
                    'urlTypes' => [
                        'custom' => [
                            'label'       => __('Custom URL', 'havenlytics'),
                            'placeholder' => 'https://example.com/document.pdf',
                            'hint'        => __('Enter any valid URL', 'havenlytics'),
                        ],
                        'pdf' => [
                            'label'       => __('PDF Document', 'havenlytics'),
                            'placeholder' => 'https://example.com/document.pdf',
                            'hint'        => __('Upload a PDF or enter a URL to a PDF file', 'havenlytics'),
                        ],
                        'youtube' => [
                            'label'       => __('YouTube Video', 'havenlytics'),
                            'placeholder' => 'https://youtu.be/xxxx or https://youtube.com/watch?v=xxxx',
                            'hint'        => __('Enter YouTube video URL', 'havenlytics'),
                        ],
                        'vimeo' => [
                            'label'       => __('Vimeo Video', 'havenlytics'),
                            'placeholder' => 'https://vimeo.com/xxxx',
                            'hint'        => __('Enter Vimeo video URL', 'havenlytics'),
                        ],
                        'website' => [
                            'label'       => __('Website Link', 'havenlytics'),
                            'placeholder' => 'https://example.com',
                            'hint'        => __('Enter website URL', 'havenlytics'),
                        ],
                        'map' => [
                            'label'       => __('Google Maps', 'havenlytics'),
                            'placeholder' => 'https://maps.google.com/?q=...',
                            'hint'        => __('Enter Google Maps URL', 'havenlytics'),
                        ],
                        'virtual_tour' => [
                            'label'       => __('Virtual Tour', 'havenlytics'),
                            'placeholder' => 'https://example.com/tour',
                            'hint'        => __('Enter virtual tour URL', 'havenlytics'),
                        ],
                        'floor_plan' => [
                            'label'       => __('Floor Plan', 'havenlytics'),
                            'placeholder' => 'https://example.com/floor-plan.pdf',
                            'hint'        => __('Enter floor plan image URL or PDF', 'havenlytics'),
                        ],
                        'image' => [
                            'label'       => __('Image', 'havenlytics'),
                            'placeholder' => 'https://example.com/image.jpg',
                            'hint'        => __('Upload an image or enter image URL', 'havenlytics'),
                        ],
                        'video' => [
                            'label'       => __('Video File', 'havenlytics'),
                            'placeholder' => 'https://example.com/video.mp4',
                            'hint'        => __('Upload a video file or enter video URL', 'havenlytics'),
                        ],
                    ],
                ]
            );
        }

        if (isset($this->js_assets['hvnly-agents-section-field'])) {
            wp_localize_script(
                'hvnly-agents-section-field',
                'hvnlyAgentsSectionField',
                [
                    'i18n' => [
                        'remove'       => __('Remove', 'havenlytics'),
                        'primaryHint'  => __('Primary', 'havenlytics'),
                        'alreadyAdded' => __('This agent is already assigned.', 'havenlytics'),
                    ],
                ]
            );
        }
    }

    /**
     * Get Font Awesome icons list for document field.
     *
     * Returns an array of available Font Awesome icons with their labels.
     *
     * @since 2.0.0
     * @return array Associative array of icon names and labels.
     */
    private function get_font_awesome_icons() {
        return [
            'file-pdf'       => __('PDF', 'havenlytics'),
            'file-word'      => __('Word', 'havenlytics'),
            'file-excel'     => __('Excel', 'havenlytics'),
            'file-powerpoint'=> __('PowerPoint', 'havenlytics'),
            'file-image'     => __('Image', 'havenlytics'),
            'file-video'     => __('Video', 'havenlytics'),
            'file-audio'     => __('Audio', 'havenlytics'),
            'file-archive'   => __('Archive', 'havenlytics'),
            'file-code'      => __('Code', 'havenlytics'),
            'file-contract'  => __('Contract', 'havenlytics'),
            'file-invoice'   => __('Invoice', 'havenlytics'),
            'file-lines'     => __('Document', 'havenlytics'),
            'file'           => __('File', 'havenlytics'),
            'folder'         => __('Folder', 'havenlytics'),
            'folder-open'    => __('Folder Open', 'havenlytics'),
            'paperclip'      => __('Attachment', 'havenlytics'),
            'link'           => __('Link', 'havenlytics'),
            'download'       => __('Download', 'havenlytics'),
            'print'          => __('Print', 'havenlytics'),
            'copy'           => __('Copy', 'havenlytics'),
        ];
    }
    
    /**
     * Get field type handler instance.
     *
     * @since 2.0.0
     * @param string $type Field type identifier.
     * @return object|null Field handler instance or null if not found.
     */
    public function get_handler($type) {
        if ( $type === 'image' ) {
            $type = 'file';
        }

        return $this->field_types[$type] ?? $this->field_types['text'] ?? null;
    }
    
    /**
     * Get all registered field type identifiers.
     *
     * @since 2.0.0
     * @return array Array of field type strings.
     */
    public function get_field_types() {
        return array_keys($this->field_types);
    }
    
    /**
     * Get all registered field handlers.
     *
     * @since 2.0.0
     * @return array Array of field handler instances.
     */
    public function get_all_handlers() {
        return $this->field_types;
    }
    
    /**
     * Check if a field type is registered.
     *
     * @since 2.0.0
     * @param string $type Field type identifier.
     * @return bool True if type exists, false otherwise.
     */
    public function type_exists($type) {
        return isset($this->field_types[$type]);
    }
}