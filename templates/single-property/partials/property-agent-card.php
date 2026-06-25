<?php
/**
 * Single property agent card — unified premium card component.
 *
 * @package     Havenlytics
 * @subpackage  Templates/single-property/partials
 * @since       3.0.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$hvnly_card_args = wp_parse_args(
	isset( $args ) && is_array( $args ) ? $args : array(),
	array(
		'context' => 'property',
	)
);

hvnly_get_template_part( 'partials/cards/agent', 'card', $hvnly_card_args );
