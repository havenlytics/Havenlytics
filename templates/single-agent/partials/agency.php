<?php
/**
 * Agency profile section on single agent pages.
 *
 * @package     Havenlytics
 * @subpackage  Templates/single-agent/partials
 * @since       3.0.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$hvnly_agent = isset( $args['agent'] ) && is_array( $args['agent'] ) ? $args['agent'] : array();

if ( empty( $hvnly_agent ) && function_exists( 'hvnly_get_agent' ) ) {
	$hvnly_agent = hvnly_get_agent( (int) get_the_ID() );
}

$hvnly_agency = isset( $hvnly_agent['agency'] ) && is_array( $hvnly_agent['agency'] ) ? $hvnly_agent['agency'] : array();

if ( empty( $hvnly_agency['name'] ) && function_exists( 'hvnly_get_agent_agency' ) ) {
	$hvnly_agency = hvnly_get_agent_agency( (int) ( $hvnly_agent['id'] ?? get_the_ID() ) );
}

if ( empty( $hvnly_agency['name'] ) ) {
	return;
}
?>
<div class="hvnly-agent-single__agency-card">
	<?php
	hvnly_get_template(
		'partials/agency-profile-card.php',
		array(
			'agency'  => $hvnly_agency,
			'context' => 'card',
		)
	);
	?>
</div>
