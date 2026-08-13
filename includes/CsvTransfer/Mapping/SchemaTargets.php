<?php
/**
 * Resolves live Property Builder storage keys for CSV import/export.
 *
 * @package HvnlyNab\CsvTransfer\Mapping
 * @since   3.7.3
 */

namespace HvnlyNab\CsvTransfer\Mapping;

use HvnlyNab\Workspace\Api\PropertyBuilderSchemaService;

defined( 'ABSPATH' ) || exit;

/**
 * SchemaTargets — maps logical CSV field ids to editor storage keys.
 *
 * Group fields use runtime `{group_base_id}_{suffix}` keys from the live
 * `hvnly_property_builder.sections` option. Sole-instance groups also dual-write
 * legacy flat keys so MetaResolver can still read them.
 *
 * @since 3.7.3
 */
final class SchemaTargets {

	/**
	 * Legacy flat keys (MetaResolver::LEGACY_ALIASES), keyed by group_type + suffix.
	 *
	 * @var array<string, array<string, string>>
	 */
	private const LEGACY = array(
		'gallery'       => array(
			'images' => '_hvnly_property_gallery_images',
			'title'  => '_hvnly_property_gallery_title',
		),
		'map'           => array(
			'address'   => '_hvnly_property_map_location_address',
			'latitude'  => '_hvnly_property_location_Latitude',
			'longitude' => '_hvnly_property_location_Longitude',
		),
		'video'         => array(
			'url'       => '_hvnly_property_youtube_video_url',
			'title'     => '_hvnly_property_youtube_video_title',
			'thumbnail' => '_hvnly_property_youtube_video_thumbnail',
		),
		'property_docs' => array(
			'documents' => '_hvnly_property_documents',
		),
	);

	/**
	 * Default standalone meta keys from DefaultTabSectionsData (stable keys).
	 *
	 * @return array<string, array{label:string,group:string,keywords?:string}>
	 */
	public static function default_standalone_defs(): array {
		return array(
			'_hvnly_property_price'               => array(
				'label' => __( 'Property Price', 'havenlytics' ),
				'group' => 'pricing',
				'keywords' => 'price',
			),
			'_hvnly_property_reception_rooms'     => array(
				'label' => __( 'Reception Rooms', 'havenlytics' ),
				'group' => 'details',
				'keywords' => 'reception rooms',
			),
			'_hvnly_property_bedrooms'            => array(
				'label' => __( 'Bedrooms', 'havenlytics' ),
				'group' => 'details',
				'keywords' => 'beds bedrooms',
			),
			'_hvnly_property_bathrooms'           => array(
				'label' => __( 'Bathrooms', 'havenlytics' ),
				'group' => 'details',
				'keywords' => 'baths bathrooms',
			),
			'_hvnly_property_half_bathrooms'      => array(
				'label' => __( 'Half Baths', 'havenlytics' ),
				'group' => 'details',
				'keywords' => 'half baths',
			),
			'_hvnly_property_kitchens'            => array(
				'label' => __( 'Kitchen', 'havenlytics' ),
				'group' => 'details',
				'keywords' => 'kitchen',
			),
			'_hvnly_property_total_rooms'         => array(
				'label' => __( 'Total Rooms', 'havenlytics' ),
				'group' => 'details',
				'keywords' => 'rooms',
			),
			'_hvnly_property_floors'              => array(
				'label' => __( 'Floors', 'havenlytics' ),
				'group' => 'details',
				'keywords' => 'floors stories',
			),
			'_hvnly_property_year_built'          => array(
				'label' => __( 'Year Built', 'havenlytics' ),
				'group' => 'details',
				'keywords' => 'year built',
			),
			'_hvnly_property_mls_number'          => array(
				'label' => __( 'MLS Number', 'havenlytics' ),
				'group' => 'property',
				'keywords' => 'mls',
			),
			'_hvnly_property_garage_sqft'         => array(
				'label' => __( 'Garage Square Footage', 'havenlytics' ),
				'group' => 'details',
				'keywords' => 'garage size',
			),
			'_hvnly_property_sqft'                => array(
				'label' => __( 'Area (sq ft)', 'havenlytics' ),
				'group' => 'details',
				'keywords' => 'area sqft size',
			),
			'_hvnly_property_lot_size'            => array(
				'label' => __( 'Lot Size', 'havenlytics' ),
				'group' => 'details',
				'keywords' => 'lot size',
			),
			'_hvnly_property_hoa_fee'             => array(
				'label' => __( 'HOA Fee', 'havenlytics' ),
				'group' => 'pricing',
				'keywords' => 'hoa fee',
			),
			'_hvnly_property_annual_tax_amount'   => array(
				'label' => __( 'Annual Tax Amount', 'havenlytics' ),
				'group' => 'pricing',
				'keywords' => 'tax',
			),
			'_hvnly_property_heating'             => array(
				'label' => __( 'Heating', 'havenlytics' ),
				'group' => 'details',
				'keywords' => 'heating',
			),
			'_hvnly_property_cooling'             => array(
				'label' => __( 'Cooling', 'havenlytics' ),
				'group' => 'details',
				'keywords' => 'cooling',
			),
			'_hvnly_property_water'               => array(
				'label' => __( 'Water Source', 'havenlytics' ),
				'group' => 'details',
				'keywords' => 'water',
			),
			'_hvnly_property_reference_number'    => array(
				'label' => __( 'Reference Number', 'havenlytics' ),
				'group' => 'property',
				'keywords' => 'reference',
			),
			'_hvnly_property_building_number'     => array(
				'label' => __( 'Building Number', 'havenlytics' ),
				'group' => 'location',
				'keywords' => 'building',
			),
			'_hvnly_property_street'              => array(
				'label' => __( 'Street', 'havenlytics' ),
				'group' => 'location',
				'keywords' => 'street',
			),
			'_hvnly_property_address_line_1'      => array(
				'label' => __( 'Address Line 1', 'havenlytics' ),
				'group' => 'location',
				'keywords' => 'address',
			),
			'_hvnly_property_address_line_2'      => array(
				'label' => __( 'Address Line 2', 'havenlytics' ),
				'group' => 'location',
				'keywords' => 'address',
			),
			'_hvnly_property_town_city'           => array(
				'label' => __( 'Town / City', 'havenlytics' ),
				'group' => 'location',
				'keywords' => 'city town',
			),
			'_hvnly_property_country_state'       => array(
				'label' => __( 'Country / State', 'havenlytics' ),
				'group' => 'location',
				'keywords' => 'state province',
			),
			'_hvnly_property_zip_code'            => array(
				'label' => __( 'ZIP / Postal Code', 'havenlytics' ),
				'group' => 'location',
				'keywords' => 'zip postal',
			),
			'_hvnly_property_location'            => array(
				'label' => __( 'Location Setting', 'havenlytics' ),
				'group' => 'location',
				'keywords' => 'urban suburban rural coastal',
			),
			'_hvnly_property_country_location'    => array(
				'label' => __( 'Country', 'havenlytics' ),
				'group' => 'location',
				'keywords' => 'country',
			),
		);
	}

	/**
	 * Logical short ids used in mapping UI for stable standalones / groups.
	 *
	 * @return array<string, array{meta?:string,group_type?:string,suffix?:string,kind:string,label:string,group:string,keywords?:string,required?:bool}>
	 */
	public static function logical_defs(): array {
		return array(
			// WP post.
			'title'            => array(
				'kind' => 'post',
				'label' => __( 'Property Title', 'havenlytics' ),
				'group' => 'property',
				'required' => true,
				'keywords' => 'title name',
			),
			'slug'             => array(
				'kind' => 'post',
				'label' => __( 'Slug', 'havenlytics' ),
				'group' => 'property',
				'keywords' => 'slug permalink',
			),
			'content'          => array(
				'kind' => 'post',
				'label' => __( 'Description', 'havenlytics' ),
				'group' => 'property',
				'keywords' => 'description content',
			),
			'excerpt'          => array(
				'kind' => 'post',
				'label' => __( 'Excerpt', 'havenlytics' ),
				'group' => 'property',
				'keywords' => 'excerpt summary',
			),
			'status'           => array(
				'kind' => 'post',
				'label' => __( 'Publish Status', 'havenlytics' ),
				'group' => 'property',
				'keywords' => 'status draft publish',
			),
			'author'           => array(
				'kind' => 'post',
				'label' => __( 'Author', 'havenlytics' ),
				'group' => 'property',
				'keywords' => 'author',
			),
			'post_date'        => array(
				'kind' => 'post',
				'label' => __( 'Publish Date', 'havenlytics' ),
				'group' => 'property',
				'keywords' => 'date published',
			),
			'menu_order'       => array(
				'kind' => 'post',
				'label' => __( 'Menu Order', 'havenlytics' ),
				'group' => 'property',
				'keywords' => 'order',
			),
			'featured'         => array(
				'kind' => 'meta',
				'meta' => '_hvnly_property_featured',
				'label' => __( 'Featured Property', 'havenlytics' ),
				'group' => 'property',
				'keywords' => 'featured',
			),
			'sticky'           => array(
				'kind' => 'post',
				'label' => __( 'Sticky', 'havenlytics' ),
				'group' => 'property',
				'keywords' => 'sticky',
			),

			// Default standalones (short ids; one option each — Property Editor SSOT).
			'price'               => array(
				'kind' => 'meta',
				'meta' => '_hvnly_property_price',
				'label' => __( 'Property Price', 'havenlytics' ),
				'group' => 'pricing',
				'keywords' => 'price',
			),
			'hoa_fee'             => array(
				'kind' => 'meta',
				'meta' => '_hvnly_property_hoa_fee',
				'label' => __( 'HOA Fee', 'havenlytics' ),
				'group' => 'pricing',
				'keywords' => 'hoa',
			),
			'annual_tax'          => array(
				'kind' => 'meta',
				'meta' => '_hvnly_property_annual_tax_amount',
				'label' => __( 'Annual Tax Amount', 'havenlytics' ),
				'group' => 'pricing',
				'keywords' => 'tax',
			),
			'mls'                 => array(
				'kind' => 'meta',
				'meta' => '_hvnly_property_mls_number',
				'label' => __( 'MLS Number', 'havenlytics' ),
				'group' => 'property',
				'keywords' => 'mls',
				'also' => array( '_hvnly_csv_mls_number' ),
			),
			'reference'           => array(
				'kind' => 'meta',
				'meta' => '_hvnly_property_reference_number',
				'label' => __( 'Reference Number', 'havenlytics' ),
				'group' => 'property',
				'keywords' => 'reference',
				'also' => array( '_hvnly_csv_reference_number' ),
			),
			'reception_rooms'     => array(
				'kind' => 'meta',
				'meta' => '_hvnly_property_reception_rooms',
				'label' => __( 'Reception Rooms', 'havenlytics' ),
				'group' => 'details',
				'keywords' => 'reception',
			),
			'bedrooms'            => array(
				'kind' => 'meta',
				'meta' => '_hvnly_property_bedrooms',
				'label' => __( 'Bedrooms', 'havenlytics' ),
				'group' => 'details',
				'keywords' => 'beds',
			),
			'bathrooms'           => array(
				'kind' => 'meta',
				'meta' => '_hvnly_property_bathrooms',
				'label' => __( 'Bathrooms', 'havenlytics' ),
				'group' => 'details',
				'keywords' => 'baths',
			),
			'half_bathrooms'      => array(
				'kind' => 'meta',
				'meta' => '_hvnly_property_half_bathrooms',
				'label' => __( 'Half Baths', 'havenlytics' ),
				'group' => 'details',
				'keywords' => 'half baths',
			),
			'kitchens'            => array(
				'kind' => 'meta',
				'meta' => '_hvnly_property_kitchens',
				'label' => __( 'Kitchen', 'havenlytics' ),
				'group' => 'details',
				'keywords' => 'kitchen',
			),
			'rooms'               => array(
				'kind' => 'meta',
				'meta' => '_hvnly_property_total_rooms',
				'label' => __( 'Total Rooms', 'havenlytics' ),
				'group' => 'details',
				'keywords' => 'rooms',
			),
			'floors'              => array(
				'kind' => 'meta',
				'meta' => '_hvnly_property_floors',
				'label' => __( 'Floors', 'havenlytics' ),
				'group' => 'details',
				'keywords' => 'floors',
			),
			'year_built'          => array(
				'kind' => 'meta',
				'meta' => '_hvnly_property_year_built',
				'label' => __( 'Year Built', 'havenlytics' ),
				'group' => 'details',
				'keywords' => 'year',
			),
			'garage_sqft'         => array(
				'kind' => 'meta',
				'meta' => '_hvnly_property_garage_sqft',
				'label' => __( 'Garage Square Footage', 'havenlytics' ),
				'group' => 'details',
				'keywords' => 'garage',
			),
			'area'                => array(
				'kind' => 'meta',
				'meta' => '_hvnly_property_sqft',
				'label' => __( 'Area (sq ft)', 'havenlytics' ),
				'group' => 'details',
				'keywords' => 'sqft area',
			),
			'lot_size'            => array(
				'kind' => 'meta',
				'meta' => '_hvnly_property_lot_size',
				'label' => __( 'Lot Size', 'havenlytics' ),
				'group' => 'details',
				'keywords' => 'lot',
			),
			'heating'             => array(
				'kind' => 'meta',
				'meta' => '_hvnly_property_heating',
				'label' => __( 'Heating', 'havenlytics' ),
				'group' => 'details',
				'keywords' => 'heating',
			),
			'cooling'             => array(
				'kind' => 'meta',
				'meta' => '_hvnly_property_cooling',
				'label' => __( 'Cooling', 'havenlytics' ),
				'group' => 'details',
				'keywords' => 'cooling',
			),
			'water'               => array(
				'kind' => 'meta',
				'meta' => '_hvnly_property_water',
				'label' => __( 'Water Source', 'havenlytics' ),
				'group' => 'details',
				'keywords' => 'water',
			),
			'building_number'     => array(
				'kind' => 'meta',
				'meta' => '_hvnly_property_building_number',
				'label' => __( 'Building Number', 'havenlytics' ),
				'group' => 'location',
				'keywords' => 'building',
			),
			'street'              => array(
				'kind' => 'meta',
				'meta' => '_hvnly_property_street',
				'label' => __( 'Street', 'havenlytics' ),
				'group' => 'location',
				'keywords' => 'street',
			),
			'address_line_1'      => array(
				'kind' => 'meta',
				'meta' => '_hvnly_property_address_line_1',
				'label' => __( 'Address Line 1', 'havenlytics' ),
				'group' => 'location',
				'keywords' => 'address line',
			),
			'address_line_2'      => array(
				'kind' => 'meta',
				'meta' => '_hvnly_property_address_line_2',
				'label' => __( 'Address Line 2', 'havenlytics' ),
				'group' => 'location',
				'keywords' => 'address line',
			),
			'city'                => array(
				'kind' => 'meta',
				'meta' => '_hvnly_property_town_city',
				'label' => __( 'Town / City', 'havenlytics' ),
				'group' => 'location',
				'keywords' => 'city',
			),
			'state'               => array(
				'kind' => 'meta',
				'meta' => '_hvnly_property_country_state',
				'label' => __( 'Country / State', 'havenlytics' ),
				'group' => 'location',
				'keywords' => 'state',
			),
			'zip'                 => array(
				'kind' => 'meta',
				'meta' => '_hvnly_property_zip_code',
				'label' => __( 'ZIP / Postal Code', 'havenlytics' ),
				'group' => 'location',
				'keywords' => 'zip',
			),
			'location_setting'    => array(
				'kind' => 'meta',
				'meta' => '_hvnly_property_location',
				'label' => __( 'Location Setting', 'havenlytics' ),
				'group' => 'location',
				'keywords' => 'urban suburban rural coastal',
			),
			'country'             => array(
				'kind' => 'meta',
				'meta' => '_hvnly_property_country_location',
				'label' => __( 'Country', 'havenlytics' ),
				'group' => 'location',
				'keywords' => 'country',
			),

			// Map group (builder key + legacy dual-write).
			'address'             => array(
				'kind' => 'group',
				'group_type' => 'map',
				'suffix' => 'address',
				'label' => __( 'Property Address', 'havenlytics' ),
				'group' => 'location',
				'keywords' => 'address map property address',
				'section' => 'property_location',
				'section_label' => __( 'Property Location', 'havenlytics' ),
			),
			'latitude'            => array(
				'kind' => 'group',
				'group_type' => 'map',
				'suffix' => 'latitude',
				'label' => __( 'Latitude', 'havenlytics' ),
				'group' => 'location',
				'keywords' => 'latitude lat',
				'section' => 'property_location',
				'section_label' => __( 'Property Location', 'havenlytics' ),
			),
			'longitude'           => array(
				'kind' => 'group',
				'group_type' => 'map',
				'suffix' => 'longitude',
				'label' => __( 'Longitude', 'havenlytics' ),
				'group' => 'location',
				'keywords' => 'longitude lng',
				'section' => 'property_location',
				'section_label' => __( 'Property Location', 'havenlytics' ),
			),

			// Gallery group.
			'gallery_title'       => array(
				'kind' => 'group',
				'group_type' => 'gallery',
				'suffix' => 'title',
				'label' => __( 'Gallery Title', 'havenlytics' ),
				'group' => 'media',
				'keywords' => 'gallery title section',
				'section' => 'property_gallery',
				'section_label' => __( 'Property Gallery', 'havenlytics' ),
			),
			'gallery'             => array(
				'kind' => 'gallery',
				'group_type' => 'gallery',
				'suffix' => 'images',
				'label' => __( 'Gallery Images', 'havenlytics' ),
				'group' => 'media',
				'keywords' => 'gallery images photos',
				'section' => 'property_gallery',
				'section_label' => __( 'Property Gallery', 'havenlytics' ),
			),

			// Video group.
			'video_title'         => array(
				'kind' => 'group',
				'group_type' => 'video',
				'suffix' => 'title',
				'label' => __( 'Video Title', 'havenlytics' ),
				'group' => 'media',
				'keywords' => 'video title',
				'section' => 'property_video',
				'section_label' => __( 'Property Video', 'havenlytics' ),
			),
			'video_url'           => array(
				'kind' => 'video',
				'group_type' => 'video',
				'suffix' => 'url',
				'label' => __( 'Video URL', 'havenlytics' ),
				'group' => 'media',
				'keywords' => 'video youtube url',
				'section' => 'property_video',
				'section_label' => __( 'Property Video', 'havenlytics' ),
			),
			'video_thumbnail'     => array(
				'kind' => 'video',
				'group_type' => 'video',
				'suffix' => 'thumbnail',
				'label' => __( 'Video Thumbnail', 'havenlytics' ),
				'group' => 'media',
				'keywords' => 'video thumbnail poster',
				'section' => 'property_video',
				'section_label' => __( 'Property Video', 'havenlytics' ),
			),

			'documents'           => array(
				'kind' => 'documents',
				'group_type' => 'property_docs',
				'suffix' => 'documents',
				'label' => __( 'Property Documents', 'havenlytics' ),
				'group' => 'media',
				'keywords' => 'documents files pdf brochure floor',
			),

			// Featured image (not a builder group widget).
			'featured_image'      => array(
				'kind' => 'featured_image',
				'label' => __( 'Featured Image', 'havenlytics' ),
				'group' => 'media',
				'keywords' => 'featured thumbnail image',
			),

			// Builder checkbox features (not taxonomy).
			'listing_features'    => array(
				'kind' => 'features_meta',
				'group_type' => 'features',
				'suffix' => 'features',
				'label' => __( 'Listing Features', 'havenlytics' ),
				'group' => 'details',
				'keywords' => 'features checklist',
			),

			// FAQ / Highlights (JSON widgets).
			'faq_items'           => array(
				'kind' => 'faq',
				'group_type' => 'faq',
				'suffix' => 'faqs',
				'label' => __( 'FAQ Items', 'havenlytics' ),
				'group' => 'advanced',
				'keywords' => 'faq questions',
			),
			'highlights'          => array(
				'kind' => 'repeater',
				'group_type' => 'repeater',
				'suffix' => 'items',
				'label' => __( 'Property Highlights', 'havenlytics' ),
				'group' => 'advanced',
				'keywords' => 'highlights repeater',
			),

			// Taxonomies.
			'department'          => array(
				'kind' => 'taxonomy',
				'taxonomy' => 'hvnly_prop_depts',
				'label' => __( 'Department', 'havenlytics' ),
				'group' => 'taxonomies',
				'keywords' => 'department category categories',
			),
			'property_type'       => array(
				'kind' => 'taxonomy',
				'taxonomy' => 'hvnly_prop_types',
				'label' => __( 'Property Type', 'havenlytics' ),
				'group' => 'taxonomies',
				'keywords' => 'type',
			),
			'location'            => array(
				'kind' => 'taxonomy',
				'taxonomy' => 'hvnly_prop_locations',
				'label' => __( 'Property Locations', 'havenlytics' ),
				'group' => 'taxonomies',
				'keywords' => 'location neighborhood',
			),
			'property_status'     => array(
				'kind' => 'taxonomy',
				'taxonomy' => 'hvnly_prop_status',
				'label' => __( 'Property Status', 'havenlytics' ),
				'group' => 'taxonomies',
				'keywords' => 'status availability',
			),
			'features'            => array(
				'kind' => 'taxonomy',
				'taxonomy' => 'hvnly_prop_features',
				'label' => __( 'Property Features', 'havenlytics' ),
				'group' => 'taxonomies',
				'keywords' => 'features taxonomy',
			),
			'tags'                => array(
				'kind' => 'taxonomy',
				'taxonomy' => 'hvnly_prop_tags',
				'label' => __( 'Property Tags', 'havenlytics' ),
				'group' => 'taxonomies',
				'keywords' => 'tags',
			),
			'badges'              => array(
				'kind' => 'taxonomy',
				'taxonomy' => 'hvnly_prop_badges',
				'label' => __( 'Property Badges', 'havenlytics' ),
				'group' => 'taxonomies',
				'keywords' => 'badges',
			),
			'categories'          => array(
				'kind' => 'taxonomy',
				'taxonomy' => 'hvnly_prop_categories',
				'label' => __( 'Property Categories', 'havenlytics' ),
				'group' => 'taxonomies',
				'keywords' => 'categories',
			),
			'amenities'           => array(
				'kind' => 'amenities',
				'meta' => '_hvnly_property_amenities',
				'label' => __( 'Amenities', 'havenlytics' ),
				'group' => 'taxonomies',
				'keywords' => 'amenities',
			),

			// Agents.
			'agent_email'         => array(
				'kind' => 'agent_email',
				'label' => __( 'Agent Email', 'havenlytics' ),
				'group' => 'agents',
				'keywords' => 'agent email',
			),
			'agent_username'      => array(
				'kind' => 'agent_username',
				'label' => __( 'Agent Username', 'havenlytics' ),
				'group' => 'agents',
				'keywords' => 'agent username',
			),
			'co_agents'           => array(
				'kind' => 'co_agents',
				'label' => __( 'Co-Agents', 'havenlytics' ),
				'group' => 'agents',
				'keywords' => 'co agents',
			),

			// Analytics meta used by Havenlytics UI.
			'views'               => array(
				'kind' => 'meta',
				'meta' => '_hvnly_property_views',
				'label' => __( 'Property View Counter', 'havenlytics' ),
				'group' => 'advanced',
				'keywords' => 'views',
			),
		);
	}

	/**
	 * First group_base_id for a group type from the live portal schema.
	 *
	 * @param string $group_type gallery|map|video|property_docs|faq|repeater|agents|features.
	 * @return string
	 */
	public static function group_base( string $group_type ): string {
		static $cache = array();
		$group_type   = sanitize_key( $group_type );
		if ( isset( $cache[ $group_type ] ) ) {
			return $cache[ $group_type ];
		}

		$base = '';

		// Prefer canonical Property Builder sections (same source as Onboarding Wizard).
		if ( class_exists( '\HvnlyNab\Core\SectionIdentity' ) ) {
			$sections = get_option( 'hvnly_property_builder.sections', array() );
			if ( is_array( $sections ) && ! empty( $sections ) ) {
				foreach ( \HvnlyNab\Core\SectionIdentity::canonical_section_ids_for_group_type( $group_type ) as $target_id ) {
					$match = \HvnlyNab\Core\SectionIdentity::find_equivalent_section( $sections, $target_id );
					if ( null === $match || empty( $match['section'] ) || ! is_array( $match['section'] ) ) {
						continue;
					}
					$base = \HvnlyNab\Core\SectionIdentity::extract_group_base_from_section( $match['section'], $group_type );
					if ( '' !== $base ) {
						break;
					}
				}
			}
		}

		// Fallback: first matching group in the live portal schema.
		if ( '' === $base && class_exists( PropertyBuilderSchemaService::class ) ) {
			$schema = PropertyBuilderSchemaService::get_portal_schema();
			foreach ( (array) ( $schema['tabs'] ?? array() ) as $tab ) {
				foreach ( (array) ( $tab['items'] ?? array() ) as $item ) {
					if ( ( $item['kind'] ?? '' ) !== 'group' ) {
						continue;
					}
					if ( (string) ( $item['groupType'] ?? '' ) !== $group_type ) {
						continue;
					}
					$base = (string) ( $item['groupBaseId'] ?? '' );
					if ( '' !== $base ) {
						break 2;
					}
				}
			}
		}

		// Last resort: master base IDs (Onboarding UnifiedFieldGenerator path).
		if ( '' === $base && class_exists( '\HvnlyNab\Core\UnifiedFieldGenerator' ) ) {
			$master = \HvnlyNab\Core\UnifiedFieldGenerator::get_instance()->get_or_create_master_base_ids();
			if ( ! empty( $master[ $group_type ] ) ) {
				$base = sanitize_key( (string) $master[ $group_type ] );
			}
		}

		$cache[ $group_type ] = $base;
		return $base;
	}

	/**
	 * Builder storage key for a group member suffix.
	 *
	 * @param string $group_type Group type.
	 * @param string $suffix Member suffix (images, url, address, …).
	 * @return string Empty when group is missing from schema.
	 */
	public static function builder_key( string $group_type, string $suffix ): string {
		$base = self::group_base( $group_type );
		if ( '' === $base || '' === $suffix ) {
			return '';
		}
		return $base . '_' . $suffix;
	}

	/**
	 * Legacy flat key for a group member, if any.
	 *
	 * @param string $group_type Group type.
	 * @param string $suffix Suffix.
	 * @return string
	 */
	public static function legacy_key( string $group_type, string $suffix ): string {
		return self::LEGACY[ $group_type ][ $suffix ] ?? '';
	}

	/**
	 * All meta keys to write for a logical field id (builder + legacy dual-write).
	 *
	 * @param string $field_id Logical catalog id.
	 * @return array{kind:string,keys:array<int,string>,taxonomy?:string,def?:array}
	 */
	public static function resolve( string $field_id ): array {
		$defs = self::logical_defs();
		if ( ! isset( $defs[ $field_id ] ) ) {
			// Dynamic builder field: id is the storage meta key itself.
			return array(
				'kind' => 'meta',
				'keys' => array( $field_id ),
				'def'  => array(
					'kind' => 'meta',
					'meta' => $field_id,
				),
			);
		}

		$def  = $defs[ $field_id ];
		$kind = (string) ( $def['kind'] ?? 'meta' );
		$keys = array();

		switch ( $kind ) {
			case 'meta':
			case 'amenities':
				if ( ! empty( $def['meta'] ) ) {
					$keys[] = (string) $def['meta'];
				}
				foreach ( (array) ( $def['also'] ?? array() ) as $also ) {
					$keys[] = (string) $also;
				}
				break;

			case 'group':
			case 'gallery':
			case 'video':
			case 'documents':
			case 'features_meta':
			case 'faq':
			case 'repeater':
				$gt = (string) ( $def['group_type'] ?? '' );
				$sf = (string) ( $def['suffix'] ?? '' );
				$bk = self::builder_key( $gt, $sf );
				if ( '' !== $bk ) {
					$keys[] = $bk;
				}
				$lk = self::legacy_key( $gt, $sf );
				if ( '' !== $lk ) {
					$keys[] = $lk;
				}
				break;

			default:
				break;
		}

		return array(
			'kind'     => $kind,
			'keys'     => array_values( array_unique( array_filter( $keys ) ) ),
			'taxonomy' => (string) ( $def['taxonomy'] ?? '' ),
			'def'      => $def,
		);
	}

	/**
	 * Storage names already claimed by default logical / standalone fields.
	 *
	 * @return array<string, true>
	 */
	public static function reserved_storage_names(): array {
		$reserved = array();
		foreach ( array_keys( self::default_standalone_defs() ) as $meta ) {
			$reserved[ $meta ] = true;
		}
		foreach ( self::logical_defs() as $id => $def ) {
			$reserved[ $id ] = true;
			if ( ! empty( $def['meta'] ) ) {
				$reserved[ (string) $def['meta'] ] = true;
			}
			foreach ( (array) ( $def['also'] ?? array() ) as $also ) {
				$reserved[ (string) $also ] = true;
			}
			if ( ! empty( $def['group_type'] ) && ! empty( $def['suffix'] ) ) {
				$bk = self::builder_key( (string) $def['group_type'], (string) $def['suffix'] );
				if ( '' !== $bk ) {
					$reserved[ $bk ] = true;
				}
				$lk = self::legacy_key( (string) $def['group_type'], (string) $def['suffix'] );
				if ( '' !== $lk ) {
					$reserved[ $lk ] = true;
				}
			}
		}

		// Internal group chrome / fragments never shown as custom targets.
		foreach ( array( 'gallery', 'video', 'map', 'property_docs', 'faq', 'repeater', 'agents', 'features' ) as $gt ) {
			$base = self::group_base( $gt );
			if ( '' === $base ) {
				continue;
			}
			foreach ( array( 'title', 'preview', 'thumbnail', 'icon', 'label', 'url', 'show_in_sidebar', 'images', 'documents', 'faqs', 'items', 'agents', 'features', 'address', 'latitude', 'longitude' ) as $sfx ) {
				$reserved[ $base . '_' . $sfx ] = true;
			}
		}

		$reserved['_hvnly_property_featured']  = true;
		$reserved['_hvnly_property_views']     = true;
		$reserved['_hvnly_property_amenities'] = true;
		$reserved['_hvnly_property_agents']    = true;
		$reserved['_thumbnail_id']             = true;

		return $reserved;
	}

	/**
	 * Whether a storage field row from collect_storage_fields should be excluded.
	 *
	 * @param array<string, mixed> $row Storage field row.
	 * @return bool
	 */
	public static function is_internal_storage_row( array $row ): bool {
		$meta_key   = (string) ( $row['metaKey'] ?? '' );
		$group_type = (string) ( $row['groupType'] ?? '' );
		$name       = (string) ( $row['name'] ?? '' );

		if ( in_array( $meta_key, array( 'preview', 'thumbnail', 'icon', 'label', 'show_in_sidebar' ), true ) ) {
			return true;
		}
		if ( 'title' === $meta_key && '' !== $group_type ) {
			return true; // Section titles — not import mapping targets.
		}
		if ( 'property_docs' === $group_type && in_array( $meta_key, array( 'url', 'icon', 'label' ), true ) ) {
			return true;
		}
		if ( false !== strpos( $name, '_preview' ) || false !== strpos( $name, '_show_in_sidebar' ) ) {
			return true;
		}
		return false;
	}

	/**
	 * Prevent instantiation.
	 */
	private function __construct() {}
}
