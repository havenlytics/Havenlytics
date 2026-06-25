<?php

/**

 * Breadcrumb for single agent profile pages.

 *

 * Uses the same markup/classes as single property breadcrumbs.

 *

 * @package     Havenlytics

 * @subpackage  Templates/single-agent/

 * @since       3.0.2

 */



if ( ! defined( 'ABSPATH' ) ) {

	exit;

}



$hvnly_agent_id    = get_the_ID();

$hvnly_agent       = function_exists( 'hvnly_get_agent' ) ? hvnly_get_agent( (int) $hvnly_agent_id ) : array();

$hvnly_agent_name  = ! empty( $hvnly_agent['name'] ) ? (string) $hvnly_agent['name'] : get_the_title( $hvnly_agent_id );

$hvnly_home_url    = esc_url( home_url( '/' ) );

$hvnly_agents_url  = esc_url( get_post_type_archive_link( 'hvnly_agent' ) ?: home_url( '/' ) );

$hvnly_current     = esc_html( wp_trim_words( $hvnly_agent_name, 6, '...' ) );

?>

<div class="hvnly-property-breadcrumb">

	<div class="hvnly-property-breadcrumb__container">

		<a href="<?php echo esc_url( $hvnly_home_url ); ?>" class="hvnly-property-breadcrumb__item"

			aria-label="<?php esc_attr_e( 'Go to homepage', 'havenlytics' ); ?>">

			<i class="fas fa-home" aria-hidden="true"></i>

			<span><?php esc_html_e( 'Home', 'havenlytics' ); ?></span>

		</a>



		<i class="fas fa-chevron-right hvnly-property-breadcrumb__separator" aria-hidden="true"></i>



		<a href="<?php echo esc_url( $hvnly_agents_url ); ?>" class="hvnly-property-breadcrumb__item"

			aria-label="<?php esc_attr_e( 'View all agents', 'havenlytics' ); ?>">

			<i class="fas fa-user-tie" aria-hidden="true"></i>

			<span><?php esc_html_e( 'Agents', 'havenlytics' ); ?></span>

		</a>



		<i class="fas fa-chevron-right hvnly-property-breadcrumb__separator" aria-hidden="true"></i>



		<span class="hvnly-property-breadcrumb__current" aria-current="page">

			<?php echo esc_html( $hvnly_current ); ?>

		</span>

	</div>

</div>

