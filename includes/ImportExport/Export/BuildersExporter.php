<?php
/**
 * Exports Property / Card / Search builder configs.
 *
 * @package HvnlyNab\ImportExport\Export
 * @since   3.6.0
 */

namespace HvnlyNab\ImportExport\Export;

use HvnlyNab\Workspace\Api\PropertyBuilderSchemaService;

defined( 'ABSPATH' ) || exit;

/**
 * Builder-related option export.
 *
 * @since 3.6.0
 */
final class BuildersExporter {

	/**
	 * @return array<string, mixed>
	 */
	public static function export(): array {
		$property_sections = class_exists( PropertyBuilderSchemaService::class )
			? PropertyBuilderSchemaService::raw_sections()
			: get_option( 'hvnly_property_builder.sections', array() );

		if ( ! is_array( $property_sections ) ) {
			$property_sections = array();
		}

		$card_sections = get_option( 'hvnly_property_card.sections', array() );
		if ( ! is_array( $card_sections ) || empty( $card_sections ) ) {
			$legacy = get_option( 'hvnly_pb_card_builder_sections', array() );
			$card_sections = is_array( $legacy ) ? $legacy : array();
		}

		$master_base_ids = get_option( 'hvnly_master_base_ids', array() );
		if ( ! is_array( $master_base_ids ) ) {
			$master_base_ids = array();
		}

		$plugin_settings = get_option( 'hvnly_plugin_settings', array() );
		if ( ! is_array( $plugin_settings ) ) {
			$plugin_settings = array();
		}

		$search = isset( $plugin_settings['search'] ) && is_array( $plugin_settings['search'] )
			? $plugin_settings['search']
			: array();

		$search_export = array(
			'hvnly_search_fields'      => isset( $search['hvnly_search_fields'] ) ? $search['hvnly_search_fields'] : array(),
			'hvnly_top_search_fields'  => isset( $search['hvnly_top_search_fields'] ) ? $search['hvnly_top_search_fields'] : array(),
			'hvnly_main_search_fields' => isset( $search['hvnly_main_search_fields'] ) ? $search['hvnly_main_search_fields'] : array(),
		);

		$general = isset( $plugin_settings['general'] ) && is_array( $plugin_settings['general'] )
			? $plugin_settings['general']
			: array();

		$listing_display = array();
		$display_keys    = array(
			'hvnly_currencyType',
			'hvnly_currencyPositionType',
			'hvnly_EnabledCurrencyFormat',
			'hvnly_thousandSeparator',
			'hvnly_decimalSeparator',
			'hvnly_numberOfDecimals',
			'hvnly_thousandText',
			'hvnly_millionText',
			'hvnly_billionText',
			'hvnly_priceFormat',
			'hvnly_priceOnCallText',
		);
		foreach ( $display_keys as $key ) {
			if ( array_key_exists( $key, $general ) ) {
				$listing_display[ $key ] = $general[ $key ];
			}
		}

		$price_on_call = get_option( 'hvnly_price_on_call_custom_options', array() );
		if ( ! is_array( $price_on_call ) ) {
			$price_on_call = array();
		}

		return array(
			'property' => array(
				'sections'           => $property_sections,
				'master_base_ids'    => $master_base_ids,
				'schema_fingerprint' => self::fingerprint( $property_sections ),
			),
			'card'     => array(
				'sections' => $card_sections,
			),
			'search'   => $search_export,
			'listing_display' => $listing_display,
			'price_on_call'   => $price_on_call,
		);
	}

	/**
	 * @param array $sections Sections.
	 * @return string
	 */
	private static function fingerprint( array $sections ): string {
		$json = wp_json_encode( $sections );
		return is_string( $json ) ? hash( 'sha256', $json ) : '';
	}

	/**
	 * Prevent instantiation.
	 */
	private function __construct() {}
}
