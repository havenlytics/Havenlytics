<?php
/**
 * Enhanced Template Loader for Havenlytics
 *
 * Extends the existing TemplateLoader to add folder-based field templates
 * Maintains full backward compatibility
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
 * Enhanced Template Loader Class
 *
 * Extends TemplateLoader for backward compatibility.
 * Note: get_field_template() is retained for API compatibility but is not called internally.
 */
class EnhancedTemplateLoader extends TemplateLoader {

    /**
     * Field type categories
     *
     * @var array
     */
    private $field_categories = [
        'default' => [ 'text', 'number', 'textarea', 'select', 'checkbox', 'file', 'image', 'url', 'email', 'date' ],
        'preset'  => [ 'fullname', 'email', 'phone', 'company', 'website', 'bedrooms', 'bathrooms', 'squarefeet', 'garage', 'address', 'city', 'zipcode', 'pool', 'garden', 'garage-checkbox' ],
        'group'   => [ 'video', 'gallery', 'map' ]
    ];

    /**
     * Constructor
     */
    public function __construct() {
        parent::__construct();
        
        // Hook into template location
        add_filter( 'hvnly_locate_template', [ $this, 'locate_field_template' ], 10, 3 );
    }

    /**
     * Get field category based on field data
     *
     * @param array $field Field configuration
     * @return string
     */
    public function get_field_category( $field ) {
        // Check if it's a group field
        if ( ! empty( $field['group_id'] ) || ! empty( $field['group_type'] ) ) {
            return 'group';
        }

        // Check if it's a preset field (by ID pattern)
        if ( ! empty( $field['id'] ) && strpos( $field['id'], 'preset_' ) === 0 ) {
            return 'preset';
        }

        // Check if field type is in preset list
        $field_type = $field['type'] ?? '';
        if ( in_array( $field_type, [ 'fullname', 'email', 'phone', 'company', 'website', 'bedrooms', 'bathrooms', 'squarefeet', 'garage', 'address', 'city', 'zipcode', 'pool', 'garden', 'garage-checkbox' ] ) ) {
            return 'preset';
        }

        // Default field
        return 'default';
    }

    /**
     * Get template name from field
     *
     * @param array $field Field configuration
     * @return string
     */
    public function get_template_name( $field ) {
        $field_type = $field['type'] ?? 'text';
        $field_id = $field['id'] ?? '';
        $category = $this->get_field_category( $field );

        // Group fields use their group_type as template name
        if ( $category === 'group' ) {
            return $field['group_type'] ?? $field_type;
        }

        // Preset fields use a sanitized version of their ID
        if ( $category === 'preset' ) {
            // Extract the part after 'preset_hvnly_property_field_'
            $template = str_replace( 'preset_hvnly_property_field_', '', $field_id );
            $template = str_replace( '_', '-', $template );
            
            // Map common preset names
            $preset_map = [
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
                'checkbox-garage' => 'garage-checkbox'
            ];
            
            return $preset_map[ $template ] ?? $template;
        }

        // Default fields use their type
        return $field_type;
    }

    /**
     * Locate field template with category fallback
     *
     * @param string $template      Current template path
     * @param mixed  $template_name Template name(s)
     * @param string $template_path Template path
     * @return string
     */
    public function locate_field_template( $template, $template_name, $template_path ) {
        // Only intercept single property field templates
        if ( ! is_array( $template_name ) ) {
            $template_name = [ $template_name ];
        }

        $first_template = reset( $template_name );
        
        // Check if this is a field template
        if ( strpos( $first_template, 'single-property/fields/' ) !== 0 ) {
            return $template;
        }

        // Extract field name from template path
        $field_file = basename( $first_template, '.php' );
        $field_name = str_replace( '-field', '', $field_file );

        // We need field data to determine category - this will be passed via args in hvnly_get_template
        // For now, return original template
        return $template;
    }

    /**
     * Get field template with category fallback.
     *
     * @deprecated 2.3.0 Not used internally. PropertySingleRenderer resolves field templates directly.
     *
     * @param string $field_type Field type
     * @param string $category   Field category
     * @param array  $args       Template args
     * @return void
     */
    public function get_field_template( $field_type, $category, $args = [] ) {
        $template_name = 'single-property/fields/' . $category . '/' . $field_type . '.php';
        
        // Try category-specific template first
        if ( file_exists( $this->get_default_path() . $template_name ) ) {
            hvnly_get_template( $template_name, $args );
            return;
        }

        // Fallback to root fields folder (backward compatibility)
        hvnly_get_template( 'single-property/fields/' . $field_type . '-field.php', $args );
    }
}