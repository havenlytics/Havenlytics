<?php
/**
 * Plugin Check Late Escaping check that recognizes Havenlytics escape wrappers.
 *
 * @package Havenlytics
 */

namespace HvnlyNab\Compatibility;

use WordPress\Plugin_Check\Checker\Check_Result;
use WordPress\Plugin_Check\Checker\Checks\Security\Late_Escaping_Check;

/**
 * Late escaping check using Havenlytics EscapeOutput ruleset.
 */
class Plugin_Check_Late_Escaping_Check extends Late_Escaping_Check {

	/**
	 * Point PHPCS at our EscapeOutput ruleset (custom escaping wrappers).
	 *
	 * @param Check_Result $result Check result / plugin context.
	 * @return array PHPCS CLI arguments.
	 */
	protected function get_args( Check_Result $result ) {
		$ruleset = dirname( __DIR__, 2 ) . '/phpcs-rulesets/havenlytics-plugin-check-escape.xml';

		/*
		 * Keep the same sniff surface as core Late_Escaping_Check, but load a
		 * ruleset that registers hvnly_esc_html_ui / hvnly_esc_attr_ui.
		 */
		return array(
			'extensions' => 'php',
			'standard'   => $ruleset,
		);
	}
}
