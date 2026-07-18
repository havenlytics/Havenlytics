<?php
/**
 * Workspace canvas page template — full-bleed SPA shell.
 *
 * Theme override: your-theme/havenlytics/agent-portal/canvas.php
 *
 * Used for the Agent Dashboard page (enabled SPA or disabled unavailable UI).
 * Does not replace property/agent/archive templates.
 *
 * @package Havenlytics
 * @subpackage Templates/agent-portal
 * @since 3.2.0
 */

defined( 'ABSPATH' ) || exit;

?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php
if ( function_exists( 'wp_body_open' ) ) {
	wp_body_open();
}

while ( have_posts() ) {
	the_post();
	the_content();
}

wp_footer();
?>
</body>
</html>
