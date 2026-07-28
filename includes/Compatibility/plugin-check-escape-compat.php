<?php
/**
 * Plugin Check / WPCS escape-wrapper compatibility.
 *
 * Plugin Check's Late_Escaping_Check runs PHPCS with:
 *   standard=WordPress, sniffs=WordPress.Security.EscapeOutput
 * and never loads this plugin's phpcs.xml.dist, so customEscapingFunctions
 * registered there are invisible to Plugin Check — which is why reports show:
 *   found 'hvnly_esc_html_ui'
 *
 * This bridge swaps Late_Escaping_Check for a subclass that uses our dedicated
 * EscapeOutput ruleset (phpcs-rulesets/havenlytics-plugin-check-escape.xml)
 * so hvnly_esc_html_ui() / hvnly_esc_attr_ui() are recognized without rewriting
 * every template call site.
 *
 * @package Havenlytics
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register Havenlytics late-escaping check with Plugin Check when available.
 *
 * @param array $checks Check slug => instance map from Plugin Check.
 * @return array
 */
function hvnly_plugin_check_register_escape_compat( $checks ) {
	if ( ! is_array( $checks ) ) {
		return $checks;
	}

	// Parent must exist before we load our subclass (extends Late_Escaping_Check).
	if ( ! class_exists( '\WordPress\Plugin_Check\Checker\Checks\Security\Late_Escaping_Check' ) ) {
		return $checks;
	}

	// Avoid Composer autoload here: loading the subclass before Plugin Check is
	// present fatals on the missing parent. Require only after the check above.
	if ( ! class_exists( '\HvnlyNab\Compatibility\Plugin_Check_Late_Escaping_Check', false ) ) {
		require_once __DIR__ . '/Plugin_Check_Late_Escaping_Check.php';
	}

	$checks['late_escaping'] = new \HvnlyNab\Compatibility\Plugin_Check_Late_Escaping_Check();

	return $checks;
}

if ( function_exists( 'add_filter' ) ) {
	add_filter( 'wp_plugin_check_checks', 'hvnly_plugin_check_register_escape_compat' );
}
