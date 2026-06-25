<?php
/**
 * Havenlytics auto-created page helpers.
 *
 * @package     Havenlytics
 * @subpackage  Setup
 * @since       3.0.2
 */

namespace HvnlyNab\Setup;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resolves stored page IDs for plugin-generated pages.
 */
final class PageInstaller {

	/** @var array<string, string> */
	private const PAGE_OPTIONS = array(
		'property_search' => 'hvnly_property_search_page_id',
		'property_grid'   => 'hvnly_property_grid_page_id',
		'property_lists'  => 'hvnly_property_list_page_id',
		'property_agents'   => 'hvnly_property_agents_page_id',
		'property_agencies' => 'hvnly_property_agencies_page_id',
	);

	/**
	 * @param string $page_key Page key (e.g. property_grid, property_agents).
	 * @return int Page ID or 0.
	 */
	public static function get_page_id( string $page_key ): int {
		$option_key = self::PAGE_OPTIONS[ $page_key ] ?? '';

		if ( '' === $option_key ) {
			return 0;
		}

		$page_id = absint( get_option( $option_key, 0 ) );

		return ( $page_id > 0 && get_post( $page_id ) ) ? $page_id : 0;
	}

	private function __construct() {}
}
