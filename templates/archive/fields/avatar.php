<?php
/**
 * Avatar Field Template
 * 
 * Shows property author avatar
 * Works for both default and preset modes
 * 
 * HOW TO OVERRIDE THIS TEMPLATE:
 * 1. Copy this file from: wp-content/plugins/havenlytics/templates/archive/fields/avatar.php
 * 2. Paste it to your theme at: wp-content/themes/your-theme/havenlytics/archive/fields/avatar.php
 * 3. Modify the copied file to customize the avatar display
 * 
 * AVAILABLE VARIABLES:
 * $property_id - Current property ID
 * $property_data - Property data array
 * $mode - Render mode: 'preset' (from builder) or 'default'
 * 
 * EXAMPLE OVERRIDE:
 * You can change avatar size, add agent info, or link to author page.
 * 
 * @package     Havenlytics
 * @subpackage  Templates/archive/Fields
 * @since       2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Prefix all variables with hvnly_ to avoid global namespace conflicts
$hvnly_property_id = $property_id ?? get_the_ID();
$hvnly_property_data = $property_data ?? hvnly_get_property_data($hvnly_property_id);
$hvnly_mode = $mode ?? 'default';
$hvnly_author_id = $hvnly_property_data['author_id'] ?? get_the_author_meta('ID');

$hvnly_avatar_size = 40;
// Single avatar chain (uploaded → Gravatar → Havenlytics placeholder). Never blank.
$hvnly_avatar_url = function_exists( 'hvnly_get_user_avatar_url' )
	? hvnly_get_user_avatar_url( (int) $hvnly_author_id, $hvnly_avatar_size )
	: ( class_exists( '\HvnlyNab\Common\AvatarService' )
		? \HvnlyNab\Common\AvatarService::resolve_for_user( (int) $hvnly_author_id, $hvnly_avatar_size )
		: '' );
$hvnly_placeholder = class_exists( '\HvnlyNab\Common\AvatarService' )
	? \HvnlyNab\Common\AvatarService::placeholder_url()
	: '';
?>
<div class="hvnly-field-avatar hvnly-property-field-mode-<?php echo esc_attr( $hvnly_mode ); ?>">
    <div class="hvnly-property--grid-list--avatar">
        <?php if ( class_exists( '\HvnlyNab\Common\AvatarService' ) && $hvnly_avatar_url ) : ?>
			<?php
			echo \HvnlyNab\Common\AvatarService::img_html( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- helper escapes.
				$hvnly_avatar_url,
				array(
					'class'  => 'hvnly-property-avatar-image',
					'width'  => absint( $hvnly_avatar_size ),
					'height' => absint( $hvnly_avatar_size ),
					'alt'    => esc_attr__( 'Property Author', 'havenlytics' ),
				)
			);
			?>
        <?php elseif ( $hvnly_avatar_url ) : ?>
            <img src="<?php echo esc_url( $hvnly_avatar_url ); ?>"
                 alt="<?php echo esc_attr__( 'Property Author', 'havenlytics' ); ?>"
                 width="<?php echo esc_attr( (string) $hvnly_avatar_size ); ?>"
                 height="<?php echo esc_attr( (string) $hvnly_avatar_size ); ?>"
                 class="hvnly-property-avatar-image"
                 <?php if ( $hvnly_placeholder && $hvnly_avatar_url !== $hvnly_placeholder ) : ?>
                 onerror="this.onerror=null;this.src=<?php echo esc_attr( wp_json_encode( $hvnly_placeholder ) ); ?>;"
                 <?php endif; ?>
                 >
        <?php else : ?>
            <div class="hvnly-property-avatar-placeholder">
                <i class="fas fa-user"></i>
            </div>
        <?php endif; ?>
    </div>
</div>