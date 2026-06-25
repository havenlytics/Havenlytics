<?php
/**
 * Property archive empty state.
 *
 * @package     Havenlytics
 * @subpackage  Templates/property-archive/partials
 * @since       3.0.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$hvnly_args = ( isset( $args ) && is_array( $args ) ) ? $args : array();
$hvnly_message = isset( $hvnly_args['message'] ) ? (string) $hvnly_args['message'] : __( 'Nothing to display yet.', 'havenlytics' );
$hvnly_icon    = isset( $hvnly_args['icon'] ) ? (string) $hvnly_args['icon'] : 'fas fa-users';
?>
<div class="hvnly-property--archive__empty">
	<i class="<?php echo esc_attr( $hvnly_icon ); ?>" aria-hidden="true"></i>
	<p><?php echo esc_html( $hvnly_message ); ?></p>
</div>
