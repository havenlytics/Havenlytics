<?php
/**
 * Agent Portal — dashboard mount shell.
 *
 * Theme override: your-theme/havenlytics/agent-portal/dashboard.php
 *
 * React mounts into #hvnly-ws-root. Do NOT put layout class `hvnly-ws` here —
 * that class belongs to WorkspaceLayout (logged-in) / is not used for auth.
 *
 * @package Havenlytics
 * @subpackage Templates/agent-portal
 * @since 3.2.0
 */

defined( 'ABSPATH' ) || exit;

$hvnly_mount_id = isset( $mount_id ) ? (string) $mount_id : 'hvnly-ws-root';
?>
<div
	id="<?php echo esc_attr( $hvnly_mount_id ); ?>"
	class="hvnly-ws-mount"
	data-hvnly-ws-mount="1"
	aria-live="polite"
></div>
