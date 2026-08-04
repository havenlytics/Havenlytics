<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Property Template Functions for Havenlytics 
 *
 * @package     Havenlytics
 * @subpackage  Functions
 * @copyright   Copyright (c) 2025, Havenlytics
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       2.0.0
 */


/**
 * Include a legacy template preserved under templates/_deprecated/.
 *
 * @param string $relative_path Path relative to templates/_deprecated/.
 * @return void
 */
function hvnly_include_deprecated_template( $relative_path ) {
	$path = HVNLYNAB_PATH . 'templates/_deprecated/' . ltrim( $relative_path, '/' );
	if ( file_exists( $path ) ) {
		include $path;
	}
}

/**
 * Get template instance
 *
 * @return HvnlyNab\Frontend\TemplateLoader
 */
function hvnly_get_template_loader() {
    static $template_loader = null;

    if ( null !== $template_loader ) {
        return $template_loader;
    }

    if ( class_exists( '\HvnlyNab\Frontend' ) ) {
        $frontend = \HvnlyNab\Frontend::get_instance();
        $service  = $frontend->get_service( \HvnlyNab\Frontend\EnhancedTemplateLoader::class );
        if ( ! $service ) {
            $service = $frontend->get_service( \HvnlyNab\Frontend\TemplateLoader::class );
        }
        if ( $service ) {
            $template_loader = $service;
            return $template_loader;
        }
    }

    $template_loader = new \HvnlyNab\Frontend\TemplateLoader();

    return $template_loader;
}

/**
 * Locate a template and return the path for inclusion.
 *
 * This is the load order:
 *
 * 1. yourtheme/havenlytics/$template_name
 * 2. yourtheme/$template_name
 * 3. $default_path (plugin) /$template_name
 *
 * @param string|array $template_names Template file(s) to search for, in order.
 * @param string $template_path Template path.
 * @param string $default_path Default path.
 * @return string
 */
if (!function_exists('hvnly_locate_template')) {
function hvnly_locate_template( $template_names, $template_path = '', $default_path = '' ) {
	$template_loader = hvnly_get_template_loader();
	return $template_loader->locate_template( $template_names, $template_path, $default_path );
}
}

/**
 * Load field template directly.
 *
 * @param string $template_name Template name.
 * @param int    $property_id   Property ID.
 * @param array  $property_data Property data.
 * @param string $mode          Render mode (preset/default).
 * @param array  $field         Field configuration.
 * @return void
 */
function hvnly_load_field_template( $template_name, $property_id, $property_data = array(), $mode = 'default', $field = array() ) {
	// Ensure template has .php extension
	if ( '.php' !== substr( $template_name, -4 ) ) {
		$template_name .= '.php';
	}
	
	$located = hvnly_locate_template( array( 'archive/fields/' . $template_name, $template_name ) );
	
	if ( $located && file_exists( $located ) ) {
		$property_id   = $property_id;
		$property_data = $property_data;
		$mode          = $mode;
		$field         = $field;
		include $located;
	}
}

/**
 * Get other templates (e.g., product attributes) passing attributes and including the file.
 *
 * @param string $template_name Template name.
 * @param array  $args Arguments (default: array()).
 * @param string $template_path Template path (default: '').
 * @param string $default_path Default path (default: '').
 */
if (!function_exists('hvnly_get_template')) {
    function hvnly_get_template( $template_name, $args = array(), $template_path = '', $default_path = '' ) {
        // Extract args if provided.
        if ( ! empty( $args ) && is_array( $args ) ) {
            extract( $args ); // phpcs:ignore WordPress.PHP.DontExtract.extract_extract
        }
        
        // Ensure template has .php extension
        if ( '.php' !== substr( $template_name, -4 ) ) {
            $template_name .= '.php';
        }
        
        // Locate the template.
        $located = hvnly_locate_template( $template_name, $template_path, $default_path );
        
        // Allow themes/plugins to filter the located template.
        $located = apply_filters( 'hvnly_get_template', $located, $template_name, $args, $template_path, $default_path );
        
        // If template doesn't exist, return silently (no debug output)
        if ( ! $located || ! file_exists( $located ) ) {
            return;
        }
        
        // Allow themes/plugins to filter before including.
        do_action( 'hvnly_before_template_part', $template_name, $template_path, $located, $args );
        
        // Include the template.
        include $located;
        
        // Allow themes/plugins to filter after including.
        do_action( 'hvnly_after_template_part', $template_name, $template_path, $located, $args );
    }
}

/**
 * Like hvnly_get_template, but returns the HTML instead of outputting.
 *
 * @param string $template_name Template name.
 * @param array  $args Arguments (default: array()).
 * @param string $template_path Template path (default: '').
 * @param string $default_path Default path (default: '').
 * @return string
 */
if (!function_exists('hvnly_get_template_html')) {
    function hvnly_get_template_html( $template_name, $args = array(), $template_path = '', $default_path = '' ) {
        ob_start();
        hvnly_get_template( $template_name, $args, $template_path, $default_path );
        return ob_get_clean();
    }
}

/**
 * Safely get and sanitize filter values from superglobals
 *
 * @param string $key The key to retrieve
 * @param mixed $default Default value if key doesn't exist
 * @param string $sanitize_type Type of sanitization to apply
 * @return mixed Sanitized value
 */
function hvnly_get_filter_input($key, $default = '', $sanitize_type = 'text') {
    // Check if key exists in $_GET
    if (!isset($_GET[$key])) {
        return $default;
    }
    
    // Unslash the value first
    $value = wp_unslash($_GET[$key]);
    
    // Apply appropriate sanitization
    switch ($sanitize_type) {
        case 'text':
            return sanitize_text_field($value);
        case 'textarea':
            return sanitize_textarea_field($value);
        case 'key':
            return sanitize_key($value);
        case 'title':
            return sanitize_title($value);
        case 'email':
            return sanitize_email($value);
        case 'url':
            return esc_url_raw($value);
        case 'int':
            return absint($value);
        case 'float':
            return floatval($value);
        case 'array':
            if (is_array($value)) {
                return array_map('sanitize_text_field', $value);
            }
            // Handle comma-separated strings
            $values = explode(',', $value);
            return array_map('sanitize_text_field', array_filter($values));
        default:
            return sanitize_text_field($value);
    }
}

/**
 * Get property data in a structured format
 *
 * @param int $property_id Property ID
 * @return array Property data
 */
function hvnly_get_property_data( $property_id ) {
	static $property_data_cache = array();

	$property_id = $property_id ?: get_the_ID();
	$property_id = absint( $property_id );

	if ( ! $property_id ) {
		return array();
	}

	if ( isset( $property_data_cache[ $property_id ] ) ) {
		return $property_data_cache[ $property_id ];
	}
	
	// Basic property data
	$property_data = array(
		'id'               => $property_id,
		'title'            => get_the_title( $property_id ),
		'permalink'        => get_permalink( $property_id ),
		'excerpt'          => get_the_excerpt( $property_id ),
		'price'            => get_post_meta( $property_id, '_hvnly_property_price', true ),
		'address'          => get_post_meta( $property_id, '_hvnly_property_address_line_1', true ),
		'phone'            => get_post_meta( $property_id, '_hvnly_property_phone', true ),
		'bedrooms'         => get_post_meta( $property_id, '_hvnly_property_bedrooms', true ),
		'bathrooms'        => get_post_meta( $property_id, '_hvnly_property_bathrooms', true ),
		'area'             => get_post_meta( $property_id, '_hvnly_property_sqft', true ),
		'garage'           => get_post_meta( $property_id, '_hvnly_property_garage_sqft', true ),
		'is_featured'      => get_post_meta( $property_id, '_hvnly_property_action_tool_is_featured', true ),
		'author_id'        => get_the_author_meta( 'ID' ),
		'featured_image'   => get_the_post_thumbnail_url( $property_id, 'large' ),
	);
	
	// Get gallery images
	$property_data['gallery_image_ids'] = hvnly_get_property_gallery_ids( $property_id );
	
	// Alternating gallery display order
	$property_id_int                    = absint( $property_id );
	$property_data['gallery_order']     = ( $property_id_int % 2 == 0 ) ? 'ASC' : 'DESC';
	
	// Apply the order to gallery images
	if ( ! empty( $property_data['gallery_image_ids'] ) && $property_data['gallery_order'] === 'DESC' ) {
		$property_data['gallery_image_ids'] = array_reverse( $property_data['gallery_image_ids'] );
	}
	
	// Get taxonomy terms
	$property_data['locations']   = get_the_terms( $property_id, 'property-location' );
	$property_data['categories']  = get_the_terms( $property_id, 'property_category' );
	
	// Get author URL
	$property_data['author_posts_url'] = hvnly_property_author_url( $property_data['author_id'], get_post_type() );
	
	// Get current filters
	$property_data['current_filters'] = hvnly_get_current_filters();
	$property_data['view_type']       = $property_data['current_filters']['view_type'];
	
	$property_data_cache[ $property_id ] = apply_filters( 'hvnly_property_data', $property_data, $property_id );

	return $property_data_cache[ $property_id ];
}

/**
 * Parse gallery meta values into attachment IDs.
 *
 * @param mixed $images Raw meta value.
 * @return array<int>
 */
function hvnly_parse_gallery_attachment_ids( $images ) {
	$gallery_image_ids = array();

	if ( empty( $images ) ) {
		return $gallery_image_ids;
	}

	if ( is_string( $images ) ) {
		if ( strpos( $images, ',' ) !== false ) {
			$ids = explode( ',', $images );
			foreach ( $ids as $id ) {
				$id = trim( $id );
				if ( is_numeric( $id ) ) {
					$gallery_image_ids[] = absint( $id );
				}
			}
		} elseif ( strpos( $images, '[' ) === 0 || strpos( $images, '{' ) === 0 ) {
			$decoded = json_decode( $images, true );
			if ( is_array( $decoded ) ) {
				foreach ( $decoded as $item ) {
					if ( is_numeric( $item ) ) {
						$gallery_image_ids[] = absint( $item );
					} elseif ( is_array( $item ) && isset( $item['id'] ) ) {
						$gallery_image_ids[] = absint( $item['id'] );
					}
				}
			}
		} elseif ( is_numeric( $images ) ) {
			$gallery_image_ids[] = absint( $images );
		} else {
			$clean_string = trim( $images, '[]"\'' );
			if ( strpos( $clean_string, ',' ) !== false ) {
				$ids = explode( ',', $clean_string );
				foreach ( $ids as $id ) {
					$id = trim( $id );
					if ( is_numeric( $id ) ) {
						$gallery_image_ids[] = absint( $id );
					}
				}
			} elseif ( is_numeric( $clean_string ) ) {
				$gallery_image_ids[] = absint( $clean_string );
			}
		}
	} elseif ( is_array( $images ) ) {
		foreach ( $images as $image ) {
			if ( is_numeric( $image ) ) {
				$gallery_image_ids[] = absint( $image );
			} elseif ( is_array( $image ) && isset( $image['id'] ) ) {
				$gallery_image_ids[] = absint( $image['id'] );
			} elseif ( is_string( $image ) && strpos( $image, ',' ) !== false ) {
				$gallery_image_ids = array_merge( $gallery_image_ids, hvnly_parse_gallery_attachment_ids( $image ) );
			}
		}
	}

	return $gallery_image_ids;
}

/**
 * Normalize gallery attachment IDs.
 *
 * @param array<int> $gallery_image_ids Attachment IDs.
 * @return array<int>
 */
function hvnly_normalize_gallery_attachment_ids( array $gallery_image_ids ) {
	$gallery_image_ids = array_unique( array_map( 'absint', $gallery_image_ids ) );
	$gallery_image_ids = array_filter(
		$gallery_image_ids,
		function ( $id ) {
			return $id > 0 && wp_attachment_is_image( $id );
		}
	);

	return array_values( $gallery_image_ids );
}

/**
 * Get property gallery image IDs
 *
 * @param int $property_id Property ID
 * @return array Gallery image IDs
 */
function hvnly_get_property_gallery_ids( $property_id ) {
	static $gallery_ids_cache      = array();
	static $builder_sections_cache  = null;
	static $builder_sections_loaded = false;

	$property_id = absint( $property_id );

	if ( ! $property_id ) {
		return array();
	}

	if ( isset( $gallery_ids_cache[ $property_id ] ) ) {
		return $gallery_ids_cache[ $property_id ];
	}

	$gallery_image_ids = array();

	$merge_gallery_value = function ( $value ) use ( &$gallery_image_ids ) {
		$parsed = hvnly_parse_gallery_attachment_ids( $value );
		if ( ! empty( $parsed ) ) {
			$gallery_image_ids = array_merge( $gallery_image_ids, $parsed );
		}
	};

	// Method 1: Gallery fields from the Add Property builder configuration.
	if ( ! $builder_sections_loaded ) {
		$builder_sections_cache  = get_option( 'hvnly_property_builder.sections', array() );
		$builder_sections_loaded = true;
	}

	$sections = $builder_sections_cache;

	if ( ! empty( $sections ) && is_array( $sections ) ) {
		foreach ( $sections as $section_key => $section ) {
			if ( empty( $section['fields'] ) || ! is_array( $section['fields'] ) ) {
				continue;
			}

			$section_id = $section['id'] ?? ( is_string( $section_key ) ? $section_key : '' );

			foreach ( $section['fields'] as $field ) {
				$is_gallery = false;

				if ( isset( $field['type'] ) && $field['type'] === 'gallery' ) {
					$is_gallery = true;
				}

				if ( isset( $field['group_type'] ) && $field['group_type'] === 'gallery' ) {
					$is_gallery = true;
				}

				if ( isset( $field['metaKey'] ) && $field['metaKey'] === 'images' ) {
					$is_gallery = true;
				}

				if ( ! $is_gallery || empty( $field['name'] ) ) {
					continue;
				}

				if ( ( $field['metaKey'] ?? '' ) !== 'images' && ( $field['type'] ?? '' ) !== 'gallery' ) {
					continue;
				}

				if ( $section_id !== '' && empty( $field['section_id'] ) ) {
					$field['section_id'] = $section_id;
				}

				$value = function_exists( 'hvnly_resolve_field_meta' )
					? hvnly_resolve_field_meta( $property_id, $field, $field['name'] )
					: get_post_meta( $property_id, $field['name'], true );

				$merge_gallery_value( $value );
			}
		}
	}

	if ( ! empty( $gallery_image_ids ) ) {
		return $gallery_ids_cache[ $property_id ] = hvnly_normalize_gallery_attachment_ids( $gallery_image_ids );
	}

	// Method 2: Legacy static gallery meta key (single global import only).
	$merge_gallery_value( get_post_meta( $property_id, '_hvnly_property_gallery_images', true ) );

	if ( ! empty( $gallery_image_ids ) ) {
		return $gallery_ids_cache[ $property_id ] = hvnly_normalize_gallery_attachment_ids( $gallery_image_ids );
	}

	// Method 3: Known gallery keys from the per-property field map (targeted lookups only).
	if ( class_exists( '\HvnlyNab\Core\GroupFieldIdentity' ) ) {
		$field_map = \HvnlyNab\Core\GroupFieldIdentity::get_field_map( $property_id );
		$known_bases = array();

		if ( ! empty( $field_map['groups'] ) && is_array( $field_map['groups'] ) ) {
			foreach ( $field_map['groups'] as $base ) {
				$known_bases[] = (string) $base;
			}
		}

		if ( ! empty( $field_map['legacy']['gallery'] ) ) {
			$known_bases[] = (string) $field_map['legacy']['gallery'];
		}

		$known_bases = array_unique( array_filter( $known_bases ) );

		foreach ( $known_bases as $base ) {
			$merge_gallery_value( get_post_meta( $property_id, $base . '_images', true ) );
		}
	}

	if ( ! empty( $gallery_image_ids ) ) {
		return $gallery_ids_cache[ $property_id ] = hvnly_normalize_gallery_attachment_ids( $gallery_image_ids );
	}

	// Method 5: Last-resort full meta scan.
	$all_meta = get_post_meta( $property_id );

	if ( ! empty( $all_meta ) && is_array( $all_meta ) ) {
		foreach ( $all_meta as $meta_key => $meta_values ) {
			if (
				( strpos( $meta_key, 'gallery_' ) !== 0 && strpos( $meta_key, '_images' ) === false )
				|| strpos( $meta_key, 'show_in_sidebar' ) !== false
				|| strpos( $meta_key, '_edit_' ) !== false
				|| strpos( $meta_key, '_wp_' ) !== false
			) {
				continue;
			}

			$value = is_array( $meta_values ) ? $meta_values[0] : $meta_values;
			$merge_gallery_value( $value );
		}
	}

	return $gallery_ids_cache[ $property_id ] = hvnly_normalize_gallery_attachment_ids( $gallery_image_ids );
}

/**
 * Method hvnly_render_property_card
 */
if (!function_exists('hvnly_render_property_card')) {
function hvnly_render_property_card( $property_id = null ) {
	$property_id   = $property_id ?: get_the_ID();
	$property_data = hvnly_get_property_data( $property_id );
	$renderer      = hvnly_get_property_card_renderer();

	// Always use the new dynamic renderer
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	echo $renderer->render_property_card_dynamic( $property_id, $property_data );
}
}

// Include all other template functions from the original file
if (!function_exists('hvnly_get_map_container')) {
    function hvnly_get_map_container($provider = 'google') {
        $args = [
            'provider' => $provider,
            'provider_class' => $provider === 'google' ? 'hvnly_google_map_property_search' : 'hvnly_leaflet_map_property_search',
            'fullscreen_btn_id' => $provider === 'google' ? 'hvnly-google-map-fullscreen-btn' : 'hvnly-leaflet-map-fullscreen-btn',
            'fullscreen_btn_class' => $provider === 'google' ? 'hvnly-google-map-fullscreen-btn' : 'hvnly-leaflet-map-fullscreen-btn'
        ];
        
        return hvnly_get_template_part('map/map-container', null, $args);
    }
}

/**
 * Method hvnly_get_map_error
 */
if (!function_exists('hvnly_get_map_error')) {
    function hvnly_get_map_error($message = '', $show_retry = true) {
        $args = [
            'message' => $message ?: __('Map loading failed. Please try again.', 'havenlytics'),
            'show_retry' => $show_retry
        ];
        
        return hvnly_get_template_part('map/map-error', null, $args);
    }
}

/**
 * Safe template loading with fallbacks
 */
if (!function_exists('hvnly_get_property_popup')) {
    function hvnly_get_property_popup($property_data = []) {
        $defaults = [
            'title' => __('Untitled Property', 'havenlytics'),
            'price' => '',
            'address' => '',
            'bedrooms' => '',
            'bathrooms' => '',
            'thumbnail' => '',
            'link' => '#'
        ];
        
        $args = [
            'property' => wp_parse_args($property_data, $defaults)
        ];
        
        // Use output buffering to capture template
        ob_start();
        $template_found = hvnly_get_template_path(['map/property-popup.php'], true, false, $args);
        
        if (!$template_found) {
            // Fallback simple popup HTML
            $property = $args['property'];
            ?>
<div class="hvnly-property-popup-content fallback">
    <div class="hvnly-popup-image">
        <?php if (!empty($property['thumbnail'])): ?>
        <img src="<?php echo esc_url($property['thumbnail']); ?>" alt="<?php echo esc_attr($property['title']); ?>">
        <?php endif; ?>
    </div>
    <div class="hvnly-popup-details">
        <h4><?php echo esc_html($property['title']); ?></h4>
        <?php if (!empty($property['price'])): ?>
        <p class="price"><?php echo esc_html($property['price']); ?></p>
        <?php endif; ?>
        <a href="<?php echo esc_url($property['link']); ?>"><?php esc_html_e('View Details', 'havenlytics'); ?></a>
    </div>
</div>
<?php
        }
        
        return ob_get_clean();
    }
}


/**
 * Whether the current request is Elementor editor/preview canvas (iframe).
 *
 * @return bool
 */
function hvnly_is_elementor_canvas_context()
{
    if (isset($_GET['elementor-preview'])) {
        return true;
    }

    if (isset($_GET['action']) && 'elementor' === sanitize_text_field(wp_unslash($_GET['action']))) {
        return true;
    }

    if (!empty($GLOBALS['hvnly_elementor_widget_active'])) {
        return true;
    }

    if (class_exists('\Elementor\Plugin')) {
        $plugin = \Elementor\Plugin::$instance;

        if (isset($plugin->editor) && method_exists($plugin->editor, 'is_edit_mode') && $plugin->editor->is_edit_mode()) {
            return true;
        }

        if (isset($plugin->preview) && method_exists($plugin->preview, 'is_preview_mode') && $plugin->preview->is_preview_mode()) {
            return true;
        }
    }

    return false;
}

/**
 * Method hvnly_get_custom_marker
 */
if (!function_exists('hvnly_get_custom_marker')) {
    function hvnly_get_custom_marker($type = 'leaflet', $property_id = 0) {
        $args = [
            'type' => $type,
            'property_id' => $property_id
        ];
        
        return hvnly_get_template_part('map/custom-marker', null, $args);
    }
}

/**
 * Universal function to get any taxonomy data
 *
 * @param string $taxonomy Taxonomy name
 * @param int $property_id Property ID
 * @param array $args Additional arguments
 * @return array
 * @since 2.0.0
 */
if (!function_exists('hvnly_get_taxonomy_data')) {
    function hvnly_get_taxonomy_data($taxonomy, $property_id = null, $args = []) {
        return hvnly_get_property_card_renderer()->get_taxonomy_terms_data($taxonomy, $property_id, $args);
    }
}

/**
 * Universal function to check if property has taxonomy terms
 *
 * @param string $taxonomy Taxonomy name
 * @param int $property_id Property ID
 * @return bool
 * @since 2.0.0
 */
if (!function_exists('hvnly_has_taxonomy_terms')) {
    function hvnly_has_taxonomy_terms($taxonomy, $property_id = null) {
        return hvnly_get_property_card_renderer()->has_taxonomy_terms($taxonomy, $property_id);
    }
}

/**
 * Universal function to get taxonomy terms count
 *
 * @param string $taxonomy Taxonomy name
 * @param int $property_id Property ID
 * @return int
 * @since 2.0.0
 */
if (!function_exists('hvnly_get_taxonomy_count')) {
    function hvnly_get_taxonomy_count($taxonomy, $property_id = null) {
        return hvnly_get_property_card_renderer()->get_taxonomy_terms_count($taxonomy, $property_id);
    }
}

/**
 * Universal function to check if should display all terms
 *
 * @param string $taxonomy Taxonomy name
 * @param int $property_id Property ID
 * @return bool
 * @since 2.0.0
 */
if (!function_exists('hvnly_should_display_all_terms')) {
    function hvnly_should_display_all_terms($taxonomy, $property_id = null) {
        return hvnly_get_property_card_renderer()->should_display_all_terms($taxonomy, $property_id);
    }
}

/**
 * Universal function to get primary term
 *
 * @param string $taxonomy Taxonomy name
 * @param int $property_id Property ID
 * @return array|false
 * @since 2.0.0
 */
if (!function_exists('hvnly_get_primary_taxonomy_term')) {
    function hvnly_get_primary_taxonomy_term($taxonomy, $property_id = null) {
        return hvnly_get_property_card_renderer()->get_primary_taxonomy_term($taxonomy, $property_id);
    }
}

/**
 * Universal function to get term meta data
 *
 * @param int $term_id Term ID
 * @param string $data_type Type of data to retrieve
 * @return mixed
 * @since 2.0.0
 */
if (!function_exists('hvnly_get_term_meta_data')) {
    function hvnly_get_term_meta_data($term_id, $data_type = 'all') {
        return hvnly_get_property_card_renderer()->get_term_meta_data($term_id, $data_type);
    }
}

/**
 * Method hvnly_get_ajax_error
 */
if (!function_exists('hvnly_get_ajax_error')) {
    function hvnly_get_ajax_error($message = '', $type = 'general') {
        $args = [
            'message' => $message ?: __('An error occurred. Please try again.', 'havenlytics'),
            'type' => $type
        ];
        
        return hvnly_get_template_part('ajax/error-message', null, $args);
    }
}

/**
 * Method hvnly_render_map_container
 */
if (!function_exists('hvnly_render_map_container')) {
    function hvnly_render_map_container($provider = 'google') {
        hvnly_get_map_container($provider);
    }
}

/**
 * Method hvnly_render_map_error
 */
if (!function_exists('hvnly_render_map_error')) {
    function hvnly_render_map_error($message = '', $show_retry = true) {
        hvnly_get_map_error($message, $show_retry);
    }
}

/**
 * Method hvnly_render_property_popup
 */
if (!function_exists('hvnly_render_property_popup')) {
    function hvnly_render_property_popup($property_data = []) {
        hvnly_get_property_popup($property_data);
    }
}

/**
 * Method hvnly_render_ajax_error
 */
if (!function_exists('hvnly_render_ajax_error')) {
    function hvnly_render_ajax_error($message = '', $type = 'general') {
        hvnly_get_ajax_error($message, $type);
    }
}

/**
 * Method hvnly_templates_location
 */
if (!function_exists('hvnly_templates_location')) {
    function hvnly_templates_location()
    {
        return apply_filters('hvnly_templates_location', HVNLYNAB_PATH . 'templates/');
    }
}

/**
 * Get template part (for templates like the archive loop).
 *
 * @param string $slug Template slug.
 * @param string $name Template name (default: '').
 * @param array  $args Template arguments.
 */
if (!function_exists('hvnly_get_template_part')) {
    function hvnly_get_template_part( $slug, $name = '', $args = array() ) {
        // Trigger action before loading template part.
        do_action( "hvnly_get_template_part_{$slug}", $slug, $name, $args );
        
        $templates = array();
        $name      = (string) $name;
        
        if ( '' !== $name ) {
            $templates[] = "{$slug}-{$name}.php";
        }
        
        $templates[] = "{$slug}.php";
        
        // Allow templates to be filtered.
        $templates = apply_filters( 'hvnly_get_template_part_templates', $templates, $slug, $name );
        
        // Locate the template.
        $template = hvnly_locate_template( $templates );
        
        // If template was found, load it.
        if ( $template ) {
            load_template( $template, false, $args );
        }
    }
}

/**
 * DEPRECATED: Get template path (for backward compatibility)
 * This needs to handle arrays properly
 *
 * @param string|array $template_names Template names.
 * @param bool $load Whether to load the template.
 * @param bool $require_once Whether to require once.
 * @param array $args Template arguments.
 * @return string|bool
 */
if (!function_exists('hvnly_get_template_path')) {
    function hvnly_get_template_path($template_names, $load = false, $require_once = true, $args = array()) {
        // For backward compatibility, handle both string and array
        $template_names = (array) $template_names;
        $located = '';
        
        foreach ($template_names as $template_name) {
            // Ensure template name is a string
            if (!is_string($template_name) || empty($template_name)) {
                continue;
            }
            
            $located = hvnly_locate_template($template_name);
            if ($located) {
                break;
            }
        }
        
        if ($load && $located) {
            load_template($located, $require_once, $args);
        }
        
        return $located;
    }
}

/**
 * Method hvnly_output_content_wrapper_start
 */
if (!function_exists('hvnly_output_content_wrapper_start')) {
    function hvnly_output_content_wrapper_start()
    {
        hvnly_get_template_part('global/wrapper-start');
    }
}

/**
 * Method hvnly_output_content_wrapper_end
 */
if (!function_exists('hvnly_output_content_wrapper_end')) {
    function hvnly_output_content_wrapper_end()
    {
        hvnly_get_template_part('global/wrapper-end');
    }
}

/**
 * Method hvnly_get_property_price
 */
if (!function_exists('hvnly_get_property_price')) {
    /**
     * Get property price with safe type handling.
     *
     * @param int|null $property_id Property ID.
     * @param bool     $format      Whether to format the price.
     * @return string|float Formatted price string or raw price float.
     */
    function hvnly_get_property_price($property_id = null, $format = true)
    {
        $property_id = $property_id ?: get_the_ID();
        $price = get_post_meta($property_id, '_hvnly_property_price', true);

        return $format ? hvnly_format_price($price) : hvnly_safe_price_to_float($price);
    }
}

// NOTE: hvnly_format_price, hvnly_safe_price_to_float, hvnly_get_currency_symbol,
// hvnly_get_currency_settings, hvnly_get_current_currency_symbol, 
// hvnly_get_current_currency_code, hvnly_format_number_with_separators,
// hvnly_format_large_number_with_suffix have been moved to Helpers class.
// They are now defined in property-functions.php as wrapper functions for backward compatibility.

/**
 * Method hvnly_get_property_meta
 */
if (!function_exists('hvnly_get_property_meta')) {
    function hvnly_get_property_meta($property_id = null)
    {
        $property_id = $property_id ?: get_the_ID();

        $meta = [
            'price' => get_post_meta($property_id, '_hvnly_property_price', true),
            'bedrooms' => get_post_meta($property_id, '_hvnly_property_bedrooms', true),
            'bathrooms' => get_post_meta($property_id, '_hvnly_property_bathrooms', true),
            'reception_rooms' => get_post_meta($property_id, '_hvnly_property_reception_rooms', true),
            'area' => get_post_meta($property_id, '_hvnly_property_sqft', true),
            'garage' => get_post_meta($property_id, '_hvnly_property_garage_sqft', true),
            'address' => get_post_meta($property_id, '_hvnly_property_street', true),
            'is_featured' => get_post_meta($property_id, '_hvnly_property_action_tool_is_featured', true),
            'year_built' => get_post_meta($property_id, '_hvnly_property_year_built', true),
        ];

        return apply_filters('hvnly_property_meta', $meta, $property_id);
    }
}

/**
 * Method hvnly_get_property_status
 */
if (!function_exists('hvnly_get_property_status')) {
    function hvnly_get_property_status($property_id = null)
    {
        $property_id = $property_id ?: get_the_ID();
        $status = get_the_terms($property_id, 'property-status');

        if ($status && !is_wp_error($status)) {
            return $status[0];
        }

        return false;
    }
}

/**
 * Method hvnly_property_author_url
 */
if (!function_exists('hvnly_property_author_url')) {
    function hvnly_property_author_url($author_id = 0, $post_type = '')
    {
        if (empty($post_type)) {
            return '';
        }

        // Get the sanitized author slug
        $author_slug = get_the_author_meta('user_nicename', $author_id);
        if (empty($author_slug)) {
            return '';
        }

        $author_posts_url = get_author_posts_url($author_id);

        if (preg_match('/author\/([a-zA-Z0-9\-_]+)/', $author_posts_url) && preg_match('/%postname%/', get_option('permalink_structure'))) {
            return !empty($author_posts_url) ? trailingslashit($author_posts_url) . 'properties' : '';
        } else {
            return !empty($author_posts_url) ? add_query_arg('properties', 'yes', $author_posts_url) : '';
        }
    }
}

/**
 * Method hvnly_post_paged
 */
if (!function_exists('hvnly_post_paged')) {
    function hvnly_post_paged()
    {
        global $paged;
        if (get_query_var('paged')) {
            $post_page = get_query_var('paged');
        } else {
            if (get_query_var('page')) {
                $post_page = get_query_var('page');
            } else {
                $post_page = 1;
            }
            set_query_var('paged', $post_page);
            $paged = $post_page;
        }
        return $post_page;
    }
}

/**
 * Method hvnly_post_pagination
 */
if (!function_exists('hvnly_post_pagination')) {
    function hvnly_post_pagination($query = null, $base_url = null, $current_num = null)
    {
        global $wp_query;
        $big            = 999999999;
        $base_url = !empty($base_url) ? trailingslashit($base_url) . '%_%' : str_replace($big, '%#%', get_pagenum_link($big));

        $current_num =  !empty($current_num) ? max(1, $current_num) : max(1, get_query_var('paged'));
        $formet =  !empty($current_num) ? 'page/%#%/' : '?paged=%#%';

        $paginate_links = paginate_links(
            array(
                'base'         => $base_url,
                'total'        => ($query != null) ? $query->max_num_pages : $wp_query->max_num_pages,
                'current'      => $current_num,
                'format'       => $formet,
                'show_all'     => false,
                'type'         => 'list',
                'end_size'     => 2,
                'mid_size'     => 1,
                'prev_next'    => true,
                'prev_text'    => '<i class="fa-solid fa-angle-left"></i>',
                'next_text'    => '<i class="fa-solid fa-angle-right"></i>',
                'add_args'     => false,
                'add_fragment' => '',
            )
        );

        if (!empty($paginate_links)) {
            echo wp_kses_post($paginate_links);
        } else {
            echo '';
        }
    }
}

/**
 * Method hvnly_get_current_page_url - FIXED with proper sanitization
 */
if (!function_exists('hvnly_get_current_page_url')) {
    function hvnly_get_current_page_url($query_arg = [], $remove = null)
    {
        global $wp;
        
        // Fix for Line 959: Unslash and sanitize $_SERVER['QUERY_STRING']
        $querystring = '';
        if (isset($_SERVER['QUERY_STRING'])) {
            // Unslash first
            $querystring = wp_unslash($_SERVER['QUERY_STRING']);
            // Then sanitize
            $querystring = sanitize_text_field($querystring);
        }
        
        $output = array();
        if (!empty($querystring)) {
            // Parse the sanitized query string
            wp_parse_str($querystring, $output);
            // Sanitize each parsed value
            array_walk_recursive($output, function(&$value) {
                $value = sanitize_text_field($value);
            });
        }
        
        // Ensure query_arg values are sanitized
        $sanitized_query_arg = array();
        foreach ($query_arg as $key => $value) {
            $sanitized_query_arg[sanitize_key($key)] = sanitize_text_field($value);
        }
        
        $queryArgs = wp_parse_args(array_filter($sanitized_query_arg), $output);

        if (!empty($remove)) {
            return remove_query_arg($remove, add_query_arg($output, home_url($wp->request)));
        }

        if (!empty($query_arg)) {
            return add_query_arg($queryArgs, home_url($wp->request));
        }

        return home_url($wp->request);
    }
}

/**
 * Method hvnly_get_base_page_url - FIXED
 */
if (!function_exists('hvnly_get_base_page_url')) {
    function hvnly_get_base_page_url($url = '')
    {
        // Fix for Line 1120: Use wp_parse_url() instead of parse_url()
        $parsed_url = wp_parse_url($url);

        // Get the path part of the URL
        $path = isset($parsed_url['path']) ? $parsed_url['path'] : '';

        // Remove the pagination part if it exists
        $base_path = preg_replace('#/page/[^/]+/?$#', '', $path);

        // Rebuild the URL without the pagination part
        $base_url = '';
        if (isset($parsed_url['scheme'])) {
            $base_url .= $parsed_url['scheme'] . '://';
        }
        if (isset($parsed_url['host'])) {
            $base_url .= $parsed_url['host'];
        }
        $base_url .= $base_path;

        // Append the query string if it exists
        if (isset($parsed_url['query'])) {
            $base_url .= '?' . $parsed_url['query'];
        }

        return trailingslashit($base_url);
    }
}

/**
 * Get current filter values with clean URL parameter names - FIXED
 */
if (!function_exists('hvnly_get_current_filters')) {
    function hvnly_get_current_filters()
    {
        // Using the helper function to fix all $_GET warnings
        $filters = array(
            'orderby' => hvnly_get_filter_input('sort', '') ?: hvnly_get_filter_input('orderby', ''),  // : Removed default 'date'
            'view_type' => hvnly_get_filter_input('view', '') ?: hvnly_get_filter_input('view_type', ''),
            'address_keyword' => hvnly_get_filter_input('search', '') ?: hvnly_get_filter_input('address_keyword', ''),
            'department' => hvnly_get_filter_input('type', '') ?: hvnly_get_filter_input('department', ''),
            'min_price' => hvnly_get_filter_input('min-price', '') ?: hvnly_get_filter_input('min_price', ''),
            'max_price' => hvnly_get_filter_input('max-price', '') ?: hvnly_get_filter_input('max_price', ''),
            'bedrooms' => hvnly_get_filter_input('beds', '') ?: hvnly_get_filter_input('bedrooms', ''),
            'bathrooms' => hvnly_get_filter_input('baths', '') ?: hvnly_get_filter_input('bathrooms', ''),
            'reception_rooms' => hvnly_get_filter_input('reception', '') ?: hvnly_get_filter_input('reception_rooms', ''),
            'garages' => hvnly_get_filter_input('garage', '') ?: hvnly_get_filter_input('garages', ''),
            'amenities' => array(),
            'paged' => max(1, absint(hvnly_get_filter_input('page', 1, 'int') ?: hvnly_get_filter_input('paged', 1, 'int')))
        );

        // Handle property_ids from URL
        $property_ids = hvnly_get_filter_input('property_ids', '', 'array');
        if (!empty($property_ids)) {
            $filters['property_ids'] = $property_ids;
        }

        // Handle property_type from URL
        $property_types = hvnly_get_filter_input('property_type', '', 'array');
        if (!empty($property_types)) {
            $filters['hvnly_prop_types'] = $property_types;
        }

        // Handle location from URL
        $locations = hvnly_get_filter_input('location', '', 'array');
        if (!empty($locations)) {
            $filters['hvnly_prop_locations'] = $locations;
        }

        // FIXED: Handle the new clean URL parameters for taxonomies
        $clean_taxonomy_params = [
            'in_tag' => 'hvnly_prop_tags',
            'in_status' => 'hvnly_prop_status',
            'in_type' => 'hvnly_prop_types',
            'in_location' => 'hvnly_prop_locations',
            'in_feature' => 'hvnly_prop_features',
            'in_review' => 'hvnly_prop_reviews',
            'in_badge' => 'hvnly_prop_badges',
        ];

        foreach ($clean_taxonomy_params as $clean_param => $taxonomy) {
            $param_value = hvnly_get_filter_input($clean_param, '');
            if (!empty($param_value)) {
                // Handle both regular and URL-encoded commas
                $param_value = str_replace('%2C', ',', $param_value);
                $term_ids = array_map('absint', explode(',', $param_value));
                $term_ids = array_filter($term_ids);

                if (!empty($term_ids)) {
                    $filters[$taxonomy] = array();
                    foreach ($term_ids as $term_id) {
                        $term = get_term($term_id, $taxonomy);
                        if ($term && !is_wp_error($term)) {
                            $filters[$taxonomy][] = $term->slug;
                        }
                    }
                }
            }
        }

        // Handle comma-separated array parameters for clean URL names
        $clean_array_params = [
            'department' => 'hvnly_prop_depts',
            'location' => 'hvnly_prop_locations',
            'feature' => 'hvnly_prop_features',
            'review' => 'hvnly_prop_reviews',
            'tag' => 'hvnly_prop_tags',
            'badge' => 'hvnly_prop_badges',
            'status' => 'hvnly_prop_status',
            'amenity' => 'amenities'
        ];

        foreach ($clean_array_params as $clean_param => $internal_param) {
            $value = hvnly_get_filter_input($clean_param, '', 'array');
            if (!empty($value)) {
                $filters[$internal_param] = $value;
            } elseif (!isset($filters[$internal_param])) {
                $filters[$internal_param] = array();
            }
        }

        // Also check for old parameter names for backward compatibility
        $legacy_array_params = [
            'hvnly_prop_depts',
            'hvnly_prop_locations',
            'hvnly_prop_features',
            'hvnly_prop_reviews',
            'hvnly_prop_tags',
            'hvnly_prop_badges',
            'hvnly_prop_status',
            'amenities'
        ];

        foreach ($legacy_array_params as $param) {
            $value = hvnly_get_filter_input($param, '', 'array');
            if (!empty($value) && empty($filters[$param])) {
                $filters[$param] = $value;
            }
        }

        return $filters;
    }
}

/**
 * Canonical filter keys for property search (executor / cache / saved searches).
 *
 * @return string[]
 */
if ( ! function_exists( 'hvnly_get_search_filter_keys' ) ) {
	function hvnly_get_search_filter_keys() {
		if ( class_exists( '\\HvnlyNab\\Frontend\\Query\\PropertyQueryCache' ) ) {
			return \HvnlyNab\Frontend\Query\PropertyQueryCache::relevant_filter_keys();
		}
		return array();
	}
}

/**
 * Normalize a raw client/URL/AJAX filter bag into PropertyQueryExecutor keys.
 *
 * Does not invent a parallel filter system — aliases map onto Free's internal keys.
 *
 * @param array<string, mixed> $raw Raw filters.
 * @return array<string, mixed>
 */
if ( ! function_exists( 'hvnly_canonicalize_search_filters' ) ) {
	function hvnly_canonicalize_search_filters( $raw ) {
		if ( ! is_array( $raw ) ) {
			return array();
		}

		$aliases = array(
			'search'           => 'address_keyword',
			'min-price'        => 'min_price',
			'max-price'        => 'max_price',
			'beds'             => 'bedrooms',
			'baths'            => 'bathrooms',
			'reception'        => 'reception_rooms',
			'garage'           => 'garages',
			'sort'             => 'orderby',
			'type'             => 'department',
			'view'             => 'view_type',
			'page'             => 'paged',
			'property_type'    => 'hvnly_prop_types',
			'location'         => 'hvnly_prop_locations',
			'status'           => 'hvnly_prop_status',
			'feature'          => 'hvnly_prop_features',
			'review'           => 'hvnly_prop_reviews',
			'tag'              => 'hvnly_prop_tags',
			'badge'            => 'hvnly_prop_badges',
			'amenity'          => 'amenities',
			'department'       => 'department',
			'in_tag'           => 'in_tag',
			'in_badge'         => 'in_badge',
			'in_status'        => 'in_status',
			'in_type'          => 'in_type',
			'in_location'      => 'in_location',
			'in_feature'       => 'in_feature',
			'in_review'        => 'in_review',
		);

		$out = array();
		foreach ( $raw as $key => $value ) {
			if ( ! is_string( $key ) || '' === $key ) {
				continue;
			}
			// Strip AJAX plumbing.
			if ( in_array( $key, array( 'action', 'nonce', '_wpnonce', 'page', 'paged', 'per_page', 'instance_id' ), true ) ) {
				continue;
			}
			$canonical = $aliases[ $key ] ?? $key;
			if ( array_key_exists( $canonical, $out ) && ( null === $value || '' === $value || array() === $value ) ) {
				continue;
			}
			$out[ $canonical ] = $value;
		}

		// Expand clean taxonomy ID lists into slug arrays when possible (same as URL restore path).
		$clean_taxonomy = array(
			'in_tag'      => 'hvnly_prop_tags',
			'in_status'   => 'hvnly_prop_status',
			'in_type'     => 'hvnly_prop_types',
			'in_location' => 'hvnly_prop_locations',
			'in_feature'  => 'hvnly_prop_features',
			'in_review'   => 'hvnly_prop_reviews',
			'in_badge'    => 'hvnly_prop_badges',
		);
		foreach ( $clean_taxonomy as $clean_key => $taxonomy ) {
			if ( empty( $out[ $clean_key ] ) || ! empty( $out[ $taxonomy ] ) ) {
				continue;
			}
			$raw_ids = $out[ $clean_key ];
			if ( is_string( $raw_ids ) ) {
				$raw_ids = explode( ',', str_replace( '%2C', ',', $raw_ids ) );
			}
			if ( ! is_array( $raw_ids ) ) {
				continue;
			}
			$slugs = array();
			foreach ( $raw_ids as $term_id ) {
				$term = get_term( absint( $term_id ), $taxonomy );
				if ( $term && ! is_wp_error( $term ) ) {
					$slugs[] = $term->slug;
				}
			}
			if ( ! empty( $slugs ) ) {
				$out[ $taxonomy ] = $slugs;
			}
		}

		$allowed = function_exists( 'hvnly_get_search_filter_keys' ) ? hvnly_get_search_filter_keys() : array();
		if ( empty( $allowed ) ) {
			return $out;
		}

		$filtered = array();
		foreach ( $allowed as $key ) {
			if ( ! array_key_exists( $key, $out ) ) {
				continue;
			}
			$filtered[ $key ] = $out[ $key ];
		}

		/**
		 * Allow Pro / custom fields to keep additional executor keys.
		 *
		 * @param array<string, mixed> $filtered Canonical subset.
		 * @param array<string, mixed> $out      Full aliased bag.
		 */
		$extra = apply_filters( 'hvnly_canonical_search_filters_result', $filtered, $out );
		return is_array( $extra ) ? $extra : $filtered;
	}
}

/**
 * Generate clean URL parameters from filter data
 */
if (!function_exists('hvnly_build_clean_url_params')) {
    function hvnly_build_clean_url_params($data)
    {
        $params = [];

        // Always include essential parameters
        if (isset($data['paged']) && $data['paged'] > 1) {
            $params['page'] = $data['paged'];
        }

        if (isset($data['view_type']) && $data['view_type'] !== 'grid') {
            $params['view'] = $data['view_type'];
        }

        if (isset($data['orderby']) && $data['orderby'] !== 'date') {
            $params['sort'] = $data['orderby'];
        }

        // Handle clean URL parameters for taxonomies
        $clean_url_mappings = [
            'hvnly_prop_types' => 'property_type',
            'hvnly_prop_locations' => 'location',
            'hvnly_prop_features' => 'feature',
            'hvnly_prop_reviews' => 'review',
            'hvnly_prop_tags' => 'tag',
            'hvnly_prop_badges' => 'badge',
            'hvnly_prop_status' => 'status',
            'amenities' => 'amenity',
            'hvnly_prop_depts' => 'department'
        ];

        foreach ($clean_url_mappings as $internal_key => $url_key) {
            if (!empty($data[$internal_key]) && is_array($data[$internal_key])) {
                $clean_value = implode(',', array_filter($data[$internal_key]));
                if (!empty($clean_value)) {
                    $params[$url_key] = $clean_value;
                }
            }
        }

        // Handle standard parameters
        $standard_params = [
            'address_keyword',
            'department',
            'min_price',
            'max_price',
            'bedrooms',
            'bathrooms',
            'reception_rooms',
            'garages'
        ];

        foreach ($standard_params as $param) {
            if (!empty($data[$param]) && $data[$param] !== '') {
                $params[$param] = $data[$param];
            }
        }

        return $params;
    }
}

/**
 * Render single property main content with dynamic sections
 */
if ( ! function_exists( 'hvnly_render_single_property_main_content' ) ) {
	/**
	 * Render single property main content with dynamic sections
	 *
	 * @return void
	 */
	function hvnly_render_single_property_main_content() {
		$property_id = get_the_ID();

		hvnly_get_property_single_renderer()->render_main_content( $property_id );
	}
}

// Add this to replace existing single property hooks
add_action( 'hvnly_single_property_main_content', 'hvnly_render_single_property_main_content', 10 );

// Remove individual section hooks (optional - for clean transition)
function hvnly_remove_old_single_property_hooks() {
	// Only remove if we're using the new renderer
	if ( apply_filters( 'hvnly_use_new_single_renderer', true ) ) {
		$removed_actions = array(
			'hvnly_single_property_gallery_carousel' => 10,
			'hvnly_single_property_summary_card' => 20,
			'hvnly_single_property_description_card' => 20,
			'hvnly_single_property_features_card' => 30,
			'hvnly_single_property_types_card' => 30,
			'hvnly_single_property_amenities_card' => 40,
			'hvnly_single_property_video_card' => 50,
			'hvnly_single_property_location_card' => 60,
			'hvnly_single_property_stats' => 70,
		);
		
		foreach ( $removed_actions as $action => $priority ) {
			remove_action( 'hvnly_single_property_main_content', $action, $priority );
		}
	}
}
add_action( 'init', 'hvnly_remove_old_single_property_hooks' );

/**
 * Get current pagination type
 * 
 * @param string $default Default pagination type
 * @return string
 */
if (!function_exists('hvnly_get_pagination_type')) {
    function hvnly_get_pagination_type($default = 'load-more') {
        if (function_exists('hvnly_is_ajax_load_more_enabled')) {
            $pagination_type = hvnly_is_ajax_load_more_enabled() ? 'load-more' : 'traditional';
        } else {
            $pagination_type = $default;
        }

        return apply_filters('hvnly_pagination_type', $pagination_type);
    }
}

/**
 * Render unified pagination or AJAX load-more for property listings.
 *
 * Used by archive template, shortcode, and Elementor widget for identical output.
 *
 * @param array $args {
 *     @type int    $current_page          Current page number.
 *     @type int    $max_pages             Total pages.
 *     @type int    $per_page              Posts per page.
 *     @type int    $found_posts           Total matching posts.
 *     @type string $instance_id           Listing instance ID.
 *     @type string $pagination_wrapper_id Outer pagination wrapper element ID.
 *     @type bool   $wrap_load_more        Whether to wrap load-more in an outer container.
 *     @type string $load_more_wrapper_id  Outer load-more wrapper element ID.
 * }
 * @return void
 */
if (!function_exists('hvnly_render_property_listing_pagination')) {
    function hvnly_render_property_listing_pagination($args = array()) {
        $defaults = array(
            'current_page'          => max(1, (int) get_query_var('paged')),
            'max_pages'             => 0,
            'per_page'              => 0,
            'found_posts'           => 0,
            'instance_id'           => 'main-archive',
            'pagination_wrapper_id' => 'hvnly-property-pagination',
            'wrap_load_more'        => true,
            'load_more_wrapper_id'  => 'hvnly-load-more-container',
            'agent_id'              => 0,
            'agency_term_id'        => 0,
        );

        $args = wp_parse_args($args, $defaults);

        $max_pages   = (int) $args['max_pages'];
        $per_page    = (int) $args['per_page'];
        $found_posts = (int) $args['found_posts'];

        if ($max_pages <= 1 && $found_posts > $per_page && $per_page > 0) {
            $max_pages = (int) ceil($found_posts / $per_page);
        }

        $pagination_type = function_exists('hvnly_get_pagination_type')
            ? hvnly_get_pagination_type()
            : 'load-more';

        if ($pagination_type === 'traditional') {
            if ($max_pages <= 1) {
                return;
            }

            printf(
                '<div id="%s">',
                esc_attr((string) $args['pagination_wrapper_id'])
            );

            if (function_exists('hvnly_get_template_part')) {
                hvnly_get_template_part(
                    'search/pagination',
                    null,
                    array(
                        'pagination_type' => 'traditional',
                        'current_page'    => (int) $args['current_page'],
                        'instance_id'     => (string) $args['instance_id'],
                        'max_pages'       => $max_pages,
                        'per_page'        => $per_page,
                        'found_posts'     => $found_posts,
                    )
                );
            }

            echo '</div>';
            return;
        }

        if ($args['wrap_load_more'] && !empty($args['load_more_wrapper_id'])) {
            printf(
                '<div id="%s" class="hvnly-load-more-wrapper">',
                esc_attr((string) $args['load_more_wrapper_id'])
            );
        }

        if (function_exists('hvnly_get_template_part')) {
            hvnly_get_template_part(
                'search/ajaxload',
                'more',
                array(
                    'pagination_type' => 'load-more',
                    'instance_id'     => (string) $args['instance_id'],
                    'current_page'    => (int) $args['current_page'],
                    'max_pages'       => $max_pages,
                    'per_page'        => $per_page,
                    'found_posts'     => $found_posts,
                    'agent_id'        => absint( $args['agent_id'] ),
                    'agency_term_id'  => absint( $args['agency_term_id'] ),
                )
            );
        }

        if ($args['wrap_load_more'] && !empty($args['load_more_wrapper_id'])) {
            echo '</div>';
        }
    }
}



/**
 * Render map container using template
 *
 * @param array $args Additional arguments
 * @return void
 */
if (!function_exists('hvnly_render_map_container')) {
    function hvnly_render_map_container($args = array()) {
        $defaults = array(
            'provider' => function_exists('hvnly_get_map_provider') ? hvnly_get_map_provider() : 'leaflet',
            'show_fullscreen' => function_exists('hvnly_is_fullscreen_enabled') ? hvnly_is_fullscreen_enabled() : true,
            'show_zoom_control' => function_exists('hvnly_is_zoom_control_enabled') ? hvnly_is_zoom_control_enabled() : true,
            'show_scroll_wheel' => function_exists('hvnly_is_scroll_wheel_enabled') ? hvnly_is_scroll_wheel_enabled() : true,
            'zoom_level' => function_exists('hvnly_get_map_zoom') ? hvnly_get_map_zoom() : 12,
        );
        $args = wp_parse_args($args, $defaults);
        hvnly_get_template_part('map/map-container', null, $args);
    }
}

/**
 * Get map container HTML as string (for AJAX)
 *
 * @param array $args Additional arguments
 * @return string
 */
if (!function_exists('hvnly_get_map_container_html')) {
    function hvnly_get_map_container_html($args = array()) {
        ob_start();
        hvnly_render_map_container($args);
        return ob_get_clean();
    }
}



/**
 * Archive page title 
 */
if (!function_exists('hvnly_page_title')) {

    /**
     * hvnly_page_title function.
     * Now supports dynamic title from settings and hide option.
     *
     * @param  boolean $echo
     * @return string
     */
    function hvnly_page_title($echo = true) {
        global $post, $hvnly_has_shortcode;

        // If this page is being rendered by our shortcode, skip the title
        if (!empty($hvnly_has_shortcode)) {
            if ($echo) {
                return;
            }
            return '';
        }

        // Check if title should be hidden on property archive
        if (is_post_type_archive('hvnly_property') && hvnly_is_top_search_title_hidden()) {
            if ($echo) {
                return;
            }
            return '';
        }

        // Determine page title
        if (is_search()) {

            $page_title = sprintf(
                /* translators: %s: Search query string */
                __('Search Results: &ldquo;%s&rdquo;', 'havenlytics'),
                get_search_query()
            );

            if (get_query_var('paged')) {
                $page_title .= sprintf(
                    /* translators: %s: Page number */
                    __(' &ndash; Page %s', 'havenlytics'),
                    get_query_var('paged')
                );
            }

        } elseif (is_tax()) {

            $page_title = single_term_title('', false);

        } elseif (is_post_type_archive('hvnly_property')) {

            // Get custom title from settings
            $custom_title = hvnly_get_search_bar_title();
            
            // Use custom title if set, otherwise fallback
            if (!empty($custom_title)) {
                $page_title = $custom_title;
            } else {
                $properties_page_id = get_option('hvnly_properties_page_id');
                $page_title = $properties_page_id
                    ? get_the_title($properties_page_id)
                    : esc_html__('Property Search', 'havenlytics');
            }

        } elseif (is_author()) {

            $page_title = sprintf(
                /* translators: %s: Author display name */
                __('Author: %s', 'havenlytics'),
                get_the_author()
            );

        } elseif (is_archive()) {

            $page_title = get_the_archive_title();

        } else {

            $page_title = get_the_title();

        }

        $page_title = apply_filters('hvnly_page_title', $page_title);

        if ($echo) {
            echo esc_html($page_title);
        } else {
            return $page_title;
        }
    }
}