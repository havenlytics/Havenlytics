<?php
/**
 * Complete Property Card Renderer - With Intelligent Template Resolution
 *
 * @package     Havenlytics
 * @subpackage  Frontend
 * @copyright   Copyright (c) 2025, Havenlytics
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       2.0.0
 */

namespace HvnlyNab\Frontend;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Property Card Renderer Class
 *
 * Handles rendering of property cards with both preset and default templates.
 */
class PropertyCardRenderer {

	/**
	 * Storage key for saved sections.
	 *
	 * @var string
	 */
	private $storage_key = 'hvnly_property_card.sections';

	/**
	 * Section ID to template mapping for preset mode.
	 *
	 * @var array
	 */
	private $section_template_mapping = array(
		'card-title'        => 'header',
		'excerpt'           => 'excerpt',
		'features'          => 'features',
		'card-content'      => 'content',
		'card-footer-left'  => 'footer',
		'card-footer-right' => 'footer',
		'schedule-tour'     => 'schedule-tour',
		'location'          => 'location',
		'avatar'            => 'header', // Avatar is part of header.
	);

	/**
	 * Constructor.
	 */
	public function __construct() {
		// Initialize section template mapping.
		$this->section_template_mapping = apply_filters( 'hvnly_section_template_mapping', $this->section_template_mapping );
	}

	/**
	 * Resolve field template mapping (shared config after init).
	 *
	 * @return array
	 */
	private function get_field_template_mapping() {
		if ( function_exists( 'hvnly_get_field_template_mapping' ) ) {
			return hvnly_get_field_template_mapping();
		}

		$defaults = function_exists( 'hvnly_get_default_field_template_mapping' )
			? hvnly_get_default_field_template_mapping()
			: array();

		return apply_filters( 'hvnly_field_template_mapping', $defaults );
	}

	/**
	 * Request-level cache for card section option reads.
	 *
	 * @var array|null
	 */
	private static $sections_request_cache = null;

	/**
	 * Whether the request-level sections cache has been populated.
	 *
	 * @var bool
	 */
	private static $sections_request_loaded = false;

	/**
	 * Load card sections from storage once per request.
	 *
	 * @return array
	 */
	private function load_sections_from_storage() {
		if ( self::$sections_request_loaded ) {
			return self::$sections_request_cache;
		}

		self::$sections_request_loaded = true;
		$saved                         = get_option( $this->storage_key, array() );
		self::$sections_request_cache  = ( ! empty( $saved ) && is_array( $saved ) ) ? $saved : array();

		return self::$sections_request_cache;
	}

	/**
	 * Check if sections are configured.
	 *
	 * @return bool
	 */
	public function has_configured_sections() {
		$saved_sections = $this->load_sections_from_storage();
		return ! empty( $saved_sections );
	}

	/**
	 * Get saved sections.
	 *
	 * @return array|bool
	 */
	public function get_sections() {
		$saved_sections = $this->load_sections_from_storage();

		if ( empty( $saved_sections ) || ! is_array( $saved_sections ) ) {
			return false;
		}

		$has_valid_sections = false;
		foreach ( $saved_sections as $section ) {
			if ( is_array( $section ) && isset( $section['id'] ) && ! empty( $section['id'] ) ) {
				$has_valid_sections = true;
				break;
			}
		}

		if ( ! $has_valid_sections ) {
			return false;
		}

		uasort(
			$saved_sections,
			function ( $a, $b ) {
				return ( $a['order'] ?? 0 ) - ( $b['order'] ?? 0 );
			}
		);

		return $saved_sections;
	}
	
	/**
	 * Sort fields by their order value.
	 *
	 * @param array $fields Fields array.
	 * @return array Sorted fields.
	 */
	private function sort_fields_by_order( $fields ) {
		if ( empty( $fields ) || ! is_array( $fields ) ) {
			return $fields;
		}

		usort(
			$fields,
			function ( $a, $b ) {
				$a_order = $a['order'] ?? 999;
				$b_order = $b['order'] ?? 999;
				return $a_order - $b_order;
			}
		);

		return $fields;
	}
	
	/**
	 * Check if any section has fields configured.
	 *
	 * @param array $sections Sections array.
	 * @return bool
	 */
	private function has_sections_with_fields( $sections ) {
		if ( empty( $sections ) || ! is_array( $sections ) ) {
			return false;
		}

		foreach ( $sections as $section ) {
			if ( ! empty( $section['fields'] ) && is_array( $section['fields'] ) && count( $section['fields'] ) > 0 ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Main rendering method - handles both default fields and preset sections.
	 *
	 * @param int   $property_id Property ID.
	 * @param array $property_data Property data.
	 * @return string Rendered HTML.
	 */
	public function render_property_card_dynamic( $property_id = null, $property_data = array() ) {
		$property_id = $property_id ?: get_the_ID();

		if ( empty( $property_data ) ) {
			$property_data = hvnly_get_property_data( $property_id );
		}

		// Check if sections are configured.
		$has_configured_sections = $this->has_configured_sections();
		$sections                = $has_configured_sections ? $this->get_sections() : false;
		$has_fields_data         = $sections ? $this->has_sections_with_fields( $sections ) : false;

		ob_start();

		// Get gallery order for data attribute.
		$property_id_int = absint( $property_id );
		$gallery_order   = ( $property_id_int % 2 == 0 ) ? 'ASC' : 'DESC';
		?>
		<div class="hvnly-property-grid-list-item" data-property-id="<?php echo esc_attr( $property_id ); ?>" data-gallery-order="<?php echo esc_attr( $gallery_order ); ?>">
			<?php
			// Use PRESET SECTIONS only if sections exist AND have fields data.
			// Use DEFAULT FIELDS if no sections OR sections exist but have no fields.
			if ( $has_configured_sections && $has_fields_data ) {
				// Use PRESET SECTIONS when backend configuration exists AND has fields data.
				$this->render_preset_sections_structure( $sections, $property_id, $property_data );
			} else {
				// Use DEFAULT FIELD TEMPLATES when no backend configuration OR no fields data.
				$this->render_default_fields_structure( $property_id, $property_data );
			}
			?>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render using PRESET SECTIONS (backend configured).
	 *
	 * @param array $sections Sections data.
	 * @param int   $property_id Property ID.
	 * @param array $property_data Property data.
	 * @return void
	 */
	private function render_preset_sections_structure( $sections, $property_id, $property_data ) {
		// First, render the thumbnail section (outside content wrapper).
		foreach ( $sections as $section ) {
			$section_id = $section['id'];

			// Render thumbnail section first (outside content wrapper).
			if ( 'thumbnail' === $section_id ) {
				// Collect overlay sections data to pass to thumbnail template.
				$overlay_sections = array();
				foreach ( $sections as $overlay_section ) {
					$overlay_section_id = $overlay_section['id'];
					if ( strpos( $overlay_section_id, 'image-overlay-' ) === 0 ) {
						$overlay_sections[ $overlay_section_id ] = $overlay_section;
					}
				}

				$this->load_template(
					'archive/sections/thumbnail',
					array(
						'section'          => $section,
						'property_id'      => $property_id,
						'property_data'    => $property_data,
						'overlay_sections' => $overlay_sections,
						'mode'             => 'preset',
						'all_sections'     => $sections,
					)
				);
				break; // Stop after rendering thumbnail.
			}
		}

		// Start content wrapper.
		echo '<div class="hvnly-property--grid-list--single__content">';

		// Define the order of sections to render.
		$section_order = array(
			'card-title'       => 'header',
			'excerpt'          => 'excerpt',
			'features'         => 'features',
			'card-content'     => 'content',
			'card-footer-left' => 'footer',
		);

		// Now render sections in the specified order.
		foreach ( $section_order as $section_id => $template_name ) {
			// Find the section in the sections array.
			$section_found = false;
			foreach ( $sections as $section ) {
				if ( $section['id'] === $section_id && ! empty( $section['fields'] ) ) {
					$section_found = true;
					// Sort fields by order before passing to template.
					$section['fields'] = $this->sort_fields_by_order( $section['fields'] );
					
					// Special handling for footer - collect all footer sections
					if ( 'footer' === $template_name ) {
						$this->render_combined_footer_section( $sections, $property_id, $property_data );
					} else {
						$this->render_preset_section( $section, $property_id, $property_data, $template_name );
					}
					break;
				}
			}
		}

		// Close content wrapper.
		echo '</div>';
	}
	
	/**
	 * Render combined footer section with left and right footer sections.
	 *
	 * @param array $sections Sections data.
	 * @param int   $property_id Property ID.
	 * @param array $property_data Property data.
	 * @return void
	 */
	private function render_combined_footer_section( $sections, $property_id, $property_data ) {
		// Collect all footer sections
		$footer_sections = array();
		
		foreach ( $sections as $section ) {
			$section_id = $section['id'];
			if ( in_array( $section_id, array( 'card-footer-left', 'card-footer-right' ), true ) && ! empty( $section['fields'] ) ) {
				$footer_sections[] = $section;
			}
		}
		
		if ( empty( $footer_sections ) ) {
			return;
		}
		
		// Pass all footer sections to the footer template
		$template_args = array(
			'property_id'     => $property_id,
			'property_data'   => $property_data,
			'mode'            => 'preset',
			'all_sections'    => $this->get_sections(),
			'footer_sections' => $footer_sections, // Pass all footer sections
		);
		
		$this->load_template( 'archive/sections/footer', $template_args );
	}
	
	/**
	 * Render a preset section with its fields.
	 *
	 * @param array  $section Section data.
	 * @param int    $property_id Property ID.
	 * @param array  $property_data Property data.
	 * @param string $template_name Template name to use.
	 * @return void
	 */
	private function render_preset_section( $section, $property_id, $property_data = array(), $template_name = '' ) {
		$section_id = $section['id'];
		
		// Skip sections that are handled elsewhere.
		if ( in_array( $section_id, array( 'thumbnail', 'avatar' ), true ) ) {
			return;
		}
		
		// Skip overlay sections (handled within thumbnail template).
		if ( strpos( $section_id, 'image-overlay-' ) === 0 ) {
			return;
		}
		
		// If template name is provided, use it.
		if ( ! empty( $template_name ) ) {
			$template_args = array(
				'property_id'   => $property_id,
				'property_data' => $property_data,
				'mode'          => 'preset',
				'all_sections'  => $this->get_sections(),
				'section'       => $section, // Pass the section data for reference.
			);
			
			// Special handling for header to include avatar.
			if ( 'header' === $template_name ) {
				// Check if avatar section exists and has fields.
				$all_sections = $this->get_sections();
				if ( $this->has_avatar_section( $all_sections ) ) {
					$template_args['show_avatar'] = true;
				}
			}
			
			$this->load_template( 'archive/sections/' . $template_name, $template_args );
			return;
		}
		
		// For other sections, render fields individually.
		if ( ! empty( $section['fields'] ) && is_array( $section['fields'] ) ) {
			$section_class = 'hvnly-preset-section hvnly-preset-section-' . esc_attr( $section_id );
			?>
			<div class="<?php echo esc_attr( $section_class ); ?>">
				<?php
				// Sort fields by order before rendering.
				$sorted_fields = $this->sort_fields_by_order( $section['fields'] );
				foreach ( $sorted_fields as $field ) {
					$field_type = $field['type'] ?? '';
					
					$mapping = $this->get_field_template_mapping();
					if ( isset( $mapping[ $field_type ] ) ) {
						$field_template_name = $mapping[ $field_type ];
						
						// Pass the builder field config so labels/button text are used.
						hvnly_render_field(
							$field_template_name,
							$property_id,
							$property_data,
							'preset',
							$field
						);
					} else {
						// Try to load template directly.
						$template_loaded = $this->load_template(
							'archive/fields/' . $field_type,
							array(
								'property_id'   => $property_id,
								'property_data' => $property_data,
								'mode'          => 'preset',
								'field'         => $field,
							)
						);
						
						// if ( ! $template_loaded && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
						//     echo '<!-- ' . esc_html( sprintf( 'Missing template for field type: %s', $field_type ) ) . ' -->';
						// }
					}
				}
				?>
			</div>
			<?php
		}
	}

	/**
	 * Render using DEFAULT FIELD TEMPLATES (no backend configuration).
	 *
	 * @param int   $property_id Property ID.
	 * @param array $property_data Property data.
	 * @return void
	 */
	private function render_default_fields_structure( $property_id, $property_data ) {
		// Render thumbnail using default field template.
		$this->load_template(
			'archive/sections/thumbnail',
			array(
				'property_id'   => $property_id,
				'property_data' => $property_data,
				'mode'          => 'default',
				'all_sections'  => array(), // Empty array for default mode.
			)
		);
		?>

		<!-- Rest of your property content -->
		<div class="hvnly-property--grid-list--single__content">
			<?php
			// Render content sections using default field templates.
			$this->load_template(
				'archive/sections/header',
				array(
					'property_id'   => $property_id,
					'property_data' => $property_data,
					'mode'          => 'default',
				)
			);
			
			// Use section templates for excerpt and features.
			$this->load_template(
				'archive/sections/excerpt',
				array(
					'property_id'   => $property_id,
					'property_data' => $property_data,
					'mode'          => 'default',
				)
			);
			
			$this->load_template(
				'archive/sections/features',
				array(
					'property_id'   => $property_id,
					'property_data' => $property_data,
					'mode'          => 'default',
				)
			);
			
			$this->load_template(
				'archive/sections/footer',
				array(
					'property_id'   => $property_id,
					'property_data' => $property_data,
					'mode'          => 'default',
				)
			);
			?>
		</div>
		<?php
	}

	/**
	 * Load template with fallbacks.
	 *
	 * @param string $template Template slug/path.
	 * @param array  $args     Template arguments.
	 * @param bool   $require_once Whether to require once.
	 * @return bool Whether template was loaded.
	 */
	public function load_template( $template, $args = array(), $require_once = false ) {
		// Remove .php extension if present
		$template = preg_replace( '/\.php$/', '', $template );
		
		// Use the template function
		hvnly_get_template( $template . '.php', $args );
		return true;
	}

	/**
	 * Legacy method for compatibility.
	 *
	 * @param int $property_id Property ID.
	 * @return string Rendered HTML.
	 */
	public function render_property_card( $property_id = null ) {
		return $this->render_property_card_dynamic( $property_id );
	}

	/**
	 * Unified method to get taxonomy terms data.
	 *
	 * @param string $taxonomy Taxonomy name.
	 * @param int    $property_id Property ID.
	 * @param array  $args Additional arguments.
	 * @return array
	 * @since 2.0.0
	 */
	public function get_taxonomy_terms_data( $taxonomy, $property_id = null, $args = array() ) {
		$property_id = $property_id ?: get_the_ID();
		$terms       = get_the_terms( $property_id, $taxonomy );

		if ( empty( $terms ) || is_wp_error( $terms ) ) {
			return array();
		}

		$defaults = array(
			'include_meta' => true,
			'include_link' => false,
			'limit'        => 0,
		);
		$args     = wp_parse_args( $args, $defaults );

		$terms_data = array();

		foreach ( $terms as $term ) {
			if ( $args['limit'] > 0 && count( $terms_data ) >= $args['limit'] ) {
				break;
			}

			$term_data = array(
				'id'          => $term->term_id,
				'name'        => $term->name,
				'slug'        => $term->slug,
				'description' => $term->description,
			);

			if ( $args['include_meta'] ) {
				$icon_data   = get_term_meta( $term->term_id, '_hvnly_advanced_icon_data', true );
				$icon_class  = is_array( $icon_data ) && isset( $icon_data['class'] ) ? $icon_data['class'] : '';
				$image_url   = function_exists( 'hvnly_get_term_advanced_image_url' )
					? hvnly_get_term_advanced_image_url( $term->term_id )
					: '';
				$term_meta_color = get_term_meta( $term->term_id, 'hvnly_badge_background_color', true );
				$display_option = get_term_meta( $term->term_id, 'hvnly_badge_display_option', true );

				$term_data['color']        = $term_meta_color;
				$term_data['icon']         = $icon_class;
				$term_data['image']        = $image_url;
				$term_data['display_all']  = ! empty( $display_option );
			}

			if ( $args['include_link'] ) {
				$term_link = get_term_link( $term );
				if ( ! is_wp_error( $term_link ) ) {
					$term_data['link'] = $term_link;
				}
			}

			$terms_data[] = $term_data;
		}

		return $terms_data;
	}

	/**
	 * Unified method to check if property has taxonomy terms.
	 *
	 * @param string $taxonomy Taxonomy name.
	 * @param int    $property_id Property ID.
	 * @return bool
	 * @since 2.0.0
	 */
	public function has_taxonomy_terms( $taxonomy, $property_id = null ) {
		$property_id = $property_id ?: get_the_ID();
		$terms       = get_the_terms( $property_id, $taxonomy );
		return ! empty( $terms ) && ! is_wp_error( $terms );
	}

	/**
	 * Unified method to get taxonomy terms count.
	 *
	 * @param string $taxonomy Taxonomy name.
	 * @param int    $property_id Property ID.
	 * @return int
	 * @since 2.0.0
	 */
	public function get_taxonomy_terms_count( $taxonomy, $property_id = null ) {
		$property_id = $property_id ?: get_the_ID();
		$terms       = get_the_terms( $property_id, $taxonomy );

		if ( empty( $terms ) || is_wp_error( $terms ) ) {
			return 0;
		}

		return count( $terms );
	}

	/**
	 * Check if should display all terms (no dropdown).
	 *
	 * @param string $taxonomy Taxonomy name.
	 * @param int    $property_id Property ID.
	 * @return bool
	 * @since 2.0.0
	 */
	public function should_display_all_terms( $taxonomy, $property_id = null ) {
		$property_id = $property_id ?: get_the_ID();
		$terms       = get_the_terms( $property_id, $taxonomy );

		if ( empty( $terms ) || is_wp_error( $terms ) ) {
			return false;
		}

		foreach ( $terms as $term ) {
			$display_option = get_term_meta( $term->term_id, 'hvnly_badge_display_option', true );
			if ( ! empty( $display_option ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Get primary term (first term).
	 *
	 * @param string $taxonomy Taxonomy name.
	 * @param int    $property_id Property ID.
	 * @return array|false
	 * @since 2.0.0
	 */
	public function get_primary_taxonomy_term( $taxonomy, $property_id = null ) {
		$property_id = $property_id ?: get_the_ID();
		$terms       = get_the_terms( $property_id, $taxonomy );

		if ( empty( $terms ) || is_wp_error( $terms ) ) {
			return false;
		}

		$primary_term = $terms[0];
		$icon_data    = get_term_meta( $primary_term->term_id, '_hvnly_advanced_icon_data', true );
		$icon_class   = is_array( $icon_data ) && isset( $icon_data['class'] ) ? $icon_data['class'] : '';
		$image_url    = function_exists( 'hvnly_get_term_advanced_image_url' )
			? hvnly_get_term_advanced_image_url( $primary_term->term_id )
			: '';
		$term_color   = get_term_meta( $primary_term->term_id, 'hvnly_badge_background_color', true );

		return array(
			'id'    => $primary_term->term_id,
			'name'  => $primary_term->name,
			'slug'  => $primary_term->slug,
			'color' => $term_color,
			'icon'  => $icon_class,
			'image' => $image_url,
		);
	}

	/**
	 * Get term-specific data.
	 *
	 * @param int    $term_id Term ID.
	 * @param string $data_type Type of data to retrieve.
	 * @return mixed
	 * @since 2.0.0
	 */
	public function get_term_meta_data( $term_id, $data_type = 'all' ) {
		switch ( $data_type ) {
			case 'icon':
				$icon_data = get_term_meta( $term_id, '_hvnly_advanced_icon_data', true );
				return is_array( $icon_data ) && isset( $icon_data['class'] ) ? $icon_data['class'] : '';

			case 'color':
				return get_term_meta( $term_id, 'hvnly_badge_background_color', true );

			case 'display_option':
				$display_option = get_term_meta( $term_id, 'hvnly_badge_display_option', true );
				return ! empty( $display_option );

			case 'all':
			default:
				$icon_data = get_term_meta( $term_id, '_hvnly_advanced_icon_data', true );
				$icon_class = is_array( $icon_data ) && isset( $icon_data['class'] ) ? $icon_data['class'] : '';
				$term_color = get_term_meta( $term_id, 'hvnly_badge_background_color', true );
				$display_option = get_term_meta( $term_id, 'hvnly_badge_display_option', true );

				return array(
					'icon'           => $icon_class,
					'color'          => $term_color,
					'display_option' => ! empty( $display_option ),
				);
		}
	}

	/**
	 * Check if a specific field type is configured in sections.
	 *
	 * @param array  $sections Sections data.
	 * @param string $field_type Field type to check.
	 * @return bool
	 * @since 2.0.0
	 */
	public function is_field_configured( $sections, $field_type ) {
		if ( empty( $sections ) || ! is_array( $sections ) ) {
			return false;
		}

		foreach ( $sections as $section ) {
			if ( ! empty( $section['fields'] ) && is_array( $section['fields'] ) ) {
				foreach ( $section['fields'] as $field ) {
					if ( isset( $field['type'] ) && $field['type'] === $field_type ) {
						return true;
					}
				}
			}
		}

		return false;
	}

	/**
	 * Check if avatar section exists and has any field.
	 *
	 * @param array $sections Sections data.
	 * @return bool
	 * @since 2.0.0
	 */
	public function has_avatar_section( $sections ) {
		if ( empty( $sections ) || ! is_array( $sections ) ) {
			return false;
		}

		foreach ( $sections as $section ) {
			if ( isset( $section['id'] ) && 'avatar' === $section['id'] && ! empty( $section['fields'] ) ) {
				return true;
			}
		}

		return false;
	}
}