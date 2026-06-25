<?php

/**

 * Agents archive content template.

 *

 * @package     Havenlytics

 * @subpackage  Templates/

 * @since       3.0.2

 */



if ( ! defined( 'ABSPATH' ) ) {

	exit;

}



do_action( 'hvnly_before_archive_agent' );



hvnly_get_template_part(

	'property-archive/partials/agents-archive',

	null,

	array(

		'instance_id'  => 'agent-archive',

		'card_context' => 'agents_archive',

	)

);



do_action( 'hvnly_after_archive_agent' );

