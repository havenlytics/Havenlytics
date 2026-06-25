<?php
/**
 * Agent archive card — delegates to unified card component.
 *
 * @package     Havenlytics
 * @subpackage  Templates/property-archive/partials
 * @since       3.0.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$hvnly_args            = isset( $args ) && is_array( $args ) ? $args : array();
$hvnly_args['context'] = isset( $hvnly_args['context'] ) ? $hvnly_args['context'] : 'agents_archive';

hvnly_get_template_part( 'partials/cards/agent', 'card', $hvnly_args );
