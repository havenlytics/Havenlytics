<?php
/**
 * File Field Template
 * 
 * This template displays the file field values.
 * 
 * HOW TO OVERRIDE THIS TEMPLATE:
 * 1. Copy this file from: wp-content/plugins/havenlytics/templates/single-property/fields/file-field.php
 * 2. Paste it to your theme at: wp-content/themes/your-theme/havenlytics/single-property/fields/file-field.php
 * 3. Modify the copied file to customize the file field display
 * 
 * @package     Havenlytics
 * @subpackage  Templates/single-property/fields/
 * @since       2.0.0
 * @deprecated  2.3.0 Legacy fallback template. Prefer single-property/fields/{category}/{type}.php.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Prefix all variables with hvnly_ to avoid global namespace conflicts
$hvnly_field        = $args['field'] ?? array();
$hvnly_value        = $args['field_value'] ?? $args['value'] ?? '';
$hvnly_label        = $args['field_label'] ?? $hvnly_field['label'] ?? '';
$hvnly_property_id  = $args['property_id'] ?? get_the_ID();
$hvnly_field_name   = $args['field_name'] ?? $hvnly_field['name'] ?? '';

if ( empty( $hvnly_value ) ) {
    return;
}

$hvnly_file_url = '';
$hvnly_file_name = '';
$hvnly_file_size = '';
$hvnly_file_type = '';

// Handle different value formats
if ( is_numeric( $hvnly_value ) ) {
    // Attachment ID
    $hvnly_file_url = wp_get_attachment_url( $hvnly_value );
    $hvnly_file_name = get_the_title( $hvnly_value );
    $hvnly_file_size = size_format( filesize( get_attached_file( $hvnly_value ) ) );
    $hvnly_file_type = get_post_mime_type( $hvnly_value );
} elseif ( is_string( $hvnly_value ) ) {
    // URL or path
    $hvnly_file_url = $hvnly_value;
    $hvnly_file_name = basename( $hvnly_value );
    
    // Try to get file size if it's a local file
    $hvnly_upload_dir = wp_upload_dir();
    if ( strpos( $hvnly_value, $hvnly_upload_dir['baseurl'] ) === 0 ) {
        $hvnly_file_path = str_replace( $hvnly_upload_dir['baseurl'], $hvnly_upload_dir['basedir'], $hvnly_value );
        if ( file_exists( $hvnly_file_path ) ) {
            $hvnly_file_size = size_format( filesize( $hvnly_file_path ) );
        }
    }
}

if ( empty( $hvnly_file_url ) ) {
    return;
}

// Determine file type icon
$hvnly_file_extension = pathinfo( $hvnly_file_name, PATHINFO_EXTENSION );
$hvnly_icon_class = 'hvnly-icon-file';
$hvnly_icon_map = array(
    'pdf'  => 'hvnly-icon-file-pdf',
    'doc'  => 'hvnly-icon-file-word',
    'docx' => 'hvnly-icon-file-word',
    'xls'  => 'hvnly-icon-file-excel',
    'xlsx' => 'hvnly-icon-file-excel',
    'jpg'  => 'hvnly-icon-file-image',
    'jpeg' => 'hvnly-icon-file-image',
    'png'  => 'hvnly-icon-file-image',
    'gif'  => 'hvnly-icon-file-image',
    'mp4'  => 'hvnly-icon-file-video',
    'mp3'  => 'hvnly-icon-file-audio',
);

if ( isset( $hvnly_icon_map[ $hvnly_file_extension ] ) ) {
    $hvnly_icon_class = $hvnly_icon_map[ $hvnly_file_extension ];
}
?>
<div class="hvnly-field hvnly-field--file hvnly-field--<?php echo esc_attr( sanitize_title( $hvnly_field_name ) ); ?>">
    <?php if ( ! empty( $hvnly_label ) ) : ?>
        <strong class="hvnly-field__label"><?php echo function_exists( 'hvnly_translate_ui' ) ? esc_html( hvnly_translate_ui( $hvnly_label ) ) : esc_html( $hvnly_label ); ?>:</strong>
    <?php endif; ?>
    
    <div class="hvnly-field__file-wrapper">
        <a href="<?php echo esc_url( $hvnly_file_url ); ?>"
           target="_blank"
           rel="noopener noreferrer"
           class="hvnly-field__file-link"
           download>
            <span class="hvnly-field__file-icon <?php echo esc_attr( $hvnly_icon_class ); ?>"></span>
            <span class="hvnly-field__file-info">
                <span class="hvnly-field__file-name"><?php echo esc_html( $hvnly_file_name ); ?></span>
                <?php if ( $hvnly_file_size ) : ?>
                    <span class="hvnly-field__file-size">(<?php echo esc_html( $hvnly_file_size ); ?>)</span>
                <?php endif; ?>
            </span>
            <span class="hvnly-field__file-download"><?php esc_html_e( 'Download', 'havenlytics' ); ?></span>
        </a>
    </div>
</div>