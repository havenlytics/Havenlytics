<?php
/**
 * Resolves CSV headers to target field ids (profile match, then fuzzy alias match).
 *
 * @package HvnlyNab\CsvTransfer\Mapping
 * @since   3.7.0
 */

namespace HvnlyNab\CsvTransfer\Mapping;

defined( 'ABSPATH' ) || exit;

/**
 * MappingResolver — best-effort header → field id resolution.
 *
 * @since 3.7.0
 */
final class MappingResolver {

	/**
	 * Common column-name aliases, keyed by target field id.
	 *
	 * Note: "category" maps to Department (not Property Type). Havenlytics
	 * treats Departments as the primary listing classification.
	 *
	 * @var array<string, array<int, string>>
	 */
	private const ALIASES = array(
		'title'            => array(
			'title',
			'property title',
			'listing title',
			'post title',
			'listing name',
			'property name',
			'name',
			'headline',
		),
		'slug'             => array( 'slug', 'post name', 'permalink', 'post_name', 'url slug' ),
		'content'          => array(
			'description',
			'content',
			'listing description',
			'post content',
			'property description',
			'full description',
			'about',
			'body',
		),
		'excerpt'          => array( 'excerpt', 'summary', 'short description', 'tagline', 'property excerpt' ),
		'status'           => array( 'listing status', 'post status', 'publish status', 'wp status' ),
		'author'           => array( 'author', 'post author', 'author login', 'author username' ),
		'post_date'        => array( 'publish date', 'published', 'post date', 'date published', 'created date' ),
		'menu_order'       => array( 'menu order', 'order', 'sort order', 'position' ),
		'featured'         => array( 'featured', 'is featured', 'featured property', 'featured listing' ),
		'sticky'           => array( 'sticky', 'is sticky', 'pin' ),
		'price'            => array( 'price', 'listing price', 'property price', 'amount', 'cost', 'asking price' ),
		'hoa_fee'          => array( 'hoa fee', 'hoa', 'hoa amount' ),
		'annual_tax'       => array( 'annual tax', 'tax amount', 'property tax', 'annual tax amount' ),
		'mls'              => array( 'mls', 'mls#', 'mls number', 'mls id', 'mlsnumber', 'mls no' ),
		'reference'        => array(
			'reference',
			'reference id',
			'ref',
			'reference number',
			'reference no',
			'listing id',
			'internal id',
		),
		'reception_rooms'  => array( 'reception rooms', 'reception', 'living rooms' ),
		'bedrooms'         => array( 'bedrooms', 'beds', 'bedroom', 'bed' ),
		'bathrooms'        => array( 'bathrooms', 'baths', 'bathroom', 'bath' ),
		'half_bathrooms'   => array( 'half baths', 'half bathrooms', 'half bath' ),
		'kitchens'         => array( 'kitchen', 'kitchens' ),
		'rooms'            => array( 'rooms', 'total rooms', 'room count' ),
		'floors'           => array( 'floors', 'stories', 'storeys', 'levels' ),
		'year_built'       => array( 'year built', 'built', 'construction year' ),
		'garage_sqft'      => array( 'garage size', 'garage sqft', 'garage square footage' ),
		'area'             => array( 'area', 'sqft', 'square feet', 'square footage', 'size', 'living area', 'area sq ft' ),
		'lot_size'         => array( 'lot size', 'lot', 'land size', 'plot size' ),
		'heating'          => array( 'heating' ),
		'cooling'          => array( 'cooling', 'air conditioning', 'ac' ),
		'water'            => array( 'water', 'water source' ),
		'building_number'  => array( 'building number', 'building no', 'building #' ),
		'street'           => array( 'street', 'road', 'street name' ),
		'address_line_1'   => array( 'address line 1', 'address1', 'address line1' ),
		'address_line_2'   => array( 'address line 2', 'address2', 'address line2' ),
		'city'             => array( 'city', 'town', 'town city' ),
		'state'            => array( 'state', 'province', 'country state' ),
		'zip'              => array( 'zip', 'zip code', 'postal code', 'postcode', 'post code' ),
		'location_setting' => array( 'location setting', 'urban suburban', 'setting' ),
		'country'          => array( 'country', 'nation', 'country location' ),
		'address'          => array( 'address', 'map address', 'location address', 'full address', 'street address', 'property address' ),
		'latitude'         => array( 'latitude', 'lat' ),
		'longitude'        => array( 'longitude', 'lng', 'lon', 'long' ),
		'featured_image'   => array(
			'featured image',
			'main image',
			'photo',
			'thumbnail',
			'cover image',
			'primary photo',
			'featured_image',
			'image url',
			'image_url',
		),
		'gallery_title'    => array( 'gallery title', 'gallery heading', 'gallery section title' ),
		'gallery'          => array(
			'gallery',
			'images',
			'listing images',
			'image urls',
			'photos',
			'gallery images',
			'gallery_images',
			'photo gallery',
			'image gallery',
			'property images',
			'multiple image urls',
		),
		'video_title'      => array( 'video title', 'youtube title', 'property video title' ),
		'video_url'        => array(
			'video',
			'videos',
			'video url',
			'video link',
			'youtube',
			'youtube url',
			'property video',
		),
		'video_thumbnail'  => array( 'video thumbnail', 'video poster', 'youtube thumbnail', 'thumbnail url' ),
		'documents'        => array(
			'documents',
			'files',
			'attachments',
			'property documents',
			'brochure',
			'floor plans',
			'floor plan',
			'floor_plans',
			'pdf',
		),
		'listing_features' => array( 'listing features', 'feature checklist', 'property feature list' ),
		'faq_items'        => array( 'faq', 'faqs', 'faq items', 'questions' ),
		'highlights'       => array( 'highlights', 'property highlights', 'repeater' ),
		'agent_email'      => array(
			'agent',
			'agent name',
			'agent email',
			'agent e-mail',
			'listing agent email',
			'email agent',
			'negotiator email',
		),
		'agent_username'   => array( 'agent username', 'agent login', 'agent user', 'username' ),
		'co_agents'        => array( 'co agents', 'co-agents', 'secondary agents', 'additional agents' ),
		'department'       => array(
			'department',
			'departments',
			'category',
			'categories',
			'listing category',
			'property category',
			'listing_category',
		),
		'property_type'    => array(
			'property type',
			'property_type',
			'type',
			'listing type',
			'listing_type',
			'prop type',
		),
		'property_status'  => array(
			'status',
			'property status',
			'property_status',
			'sale status',
			'availability',
			'listing availability',
		),
		'location'         => array(
			'location',
			'locations',
			'property locations',
			'neighborhood',
			'neighbourhood',
			'district',
		),
		'tags'             => array( 'tags', 'property tags', 'listing tags', 'tag', 'post tags' ),
		'features'         => array( 'features', 'property features', 'facilities' ),
		'amenities'        => array( 'amenities', 'property amenities', 'amenity', 'listing amenities' ),
		'badges'           => array( 'badges', 'property badges', 'badge', 'labels', 'property labels' ),
		'categories'       => array( 'property categories', 'prop categories' ),
		'views'            => array(
			'views',
			'view counter',
			'property views',
			'listing views',
			'listing_views',
			'property view counter',
		),
		// Permanent preset fields (utils/presetFields.js) — storage ids unchanged.
		'preset_hvnly_property_field_fullname'           => array( 'full name', 'fullname', 'contact name' ),
		'preset_hvnly_property_field_email'           => array( 'email address', 'email', 'e-mail', 'contact email', 'property email' ),
		'preset_hvnly_property_field_phone'           => array( 'phone number', 'phone', 'telephone', 'tel', 'landline' ),
		'preset_hvnly_property_field_company'         => array( 'company name', 'company', 'brokerage' ),
		'preset_hvnly_property_field_website'         => array( 'website', 'web', 'homepage', 'web site', 'site url' ),
		'preset_hvnly_property_field_number_garage'   => array( 'garage spaces', 'garage space', 'parking spaces' ),
		'preset_hvnly_property_field_checkbox_pool'   => array( 'swimming pool', 'pool' ),
		'preset_hvnly_property_field_checkbox_garden' => array( 'garden', 'yard' ),
		'preset_hvnly_property_field_checkbox_garage' => array( 'has garage', 'garage amenity' ),
	);

	/**
	 * Resolve headers to target field ids.
	 *
	 * @param array<int, string>        $headers CSV header row.
	 * @param array<string, mixed>|null $profile Optional saved/preset mapping profile.
	 * @return array{mapping: array<string, string|null>, matched: array<int, string>, unmatched: array<int, string>}
	 */
	public static function resolve( array $headers, ?array $profile = null ): array {
		$known_ids   = FieldCatalog::resolvable_field_ids();
		$profile_map = array();
		if ( $profile && isset( $profile['mapping'] ) && is_array( $profile['mapping'] ) ) {
			foreach ( $profile['mapping'] as $header => $field_id ) {
				$candidate = $field_id ? (string) $field_id : null;
				if ( null !== $candidate ) {
					// Allow Builder storage names (underscores) — do not over-sanitize.
					$candidate = sanitize_text_field( $candidate );
					$candidate = preg_replace( '/[^a-zA-Z0-9_\-]/', '', $candidate );
				}
				$profile_map[ self::normalize( (string) $header ) ] = $candidate ? $candidate : null;
			}
		}

		$alias_lookup = self::alias_lookup();

		$mapping   = array();
		$matched   = array();
		$unmatched = array();
		$used      = array();

		foreach ( $headers as $header ) {
			$header = (string) $header;
			$norm   = self::normalize( $header );
			$field  = null;

			if ( array_key_exists( $norm, $profile_map ) ) {
				$candidate = $profile_map[ $norm ];
				// Trust saved profiles: supported ids, legacy import ids, or storage-style ids.
				if ( null === $candidate ) {
					$field = null;
				} elseif (
					in_array( $candidate, $known_ids, true )
					|| (bool) preg_match( '/^[a-zA-Z0-9_\-]+$/', $candidate )
				) {
					$field = $candidate;
				}
			}

			if ( null === $field && isset( $alias_lookup[ $norm ] ) ) {
				$candidate = $alias_lookup[ $norm ];
				if ( in_array( $candidate, $known_ids, true ) && ! isset( $used[ $candidate ] ) ) {
					$field = $candidate;
				}
			}

			if ( null === $field ) {
				// Exact catalog id / storage name match.
				foreach ( $known_ids as $known_id ) {
					if ( self::normalize( $known_id ) === $norm && ! isset( $used[ $known_id ] ) ) {
						$field = $known_id;
						break;
					}
				}
			}

			if ( null === $field ) {
				foreach ( FieldCatalog::get_fields() as $row ) {
					$label_norm = self::normalize( (string) ( $row['label'] ?? '' ) );
					if ( '' !== $label_norm && $label_norm === $norm && ! isset( $used[ $row['id'] ] ) ) {
						$field = $row['id'];
						break;
					}
				}
			}

			$mapping[ $header ] = $field;
			if ( $field ) {
				$used[ $field ] = true;
				$matched[]      = $header;
			} else {
				$unmatched[] = $header;
			}
		}

		return array(
			'mapping'   => $mapping,
			'matched'   => $matched,
			'unmatched' => $unmatched,
		);
	}

	/**
	 * Apply a header→field mapping to a CSV data row.
	 *
	 * @param array<string, string>      $row CSV row keyed by header.
	 * @param array<string, string|null> $mapping Header => field id.
	 * @return array<string, string> Field id => value.
	 */
	public static function map_row( array $row, array $mapping ): array {
		$out = array();
		foreach ( $mapping as $header => $field_id ) {
			if ( ! $field_id ) {
				continue;
			}
			$value = isset( $row[ $header ] ) ? (string) $row[ $header ] : '';
			if ( isset( $out[ $field_id ] ) && '' !== trim( $out[ $field_id ] ) ) {
				continue;
			}
			$out[ $field_id ] = $value;
		}
		return $out;
	}

	/**
	 * Flatten aliases into normalized header => field id.
	 *
	 * @return array<string, string>
	 */
	private static function alias_lookup(): array {
		static $lookup = null;
		if ( null !== $lookup ) {
			return $lookup;
		}

		/**
		 * Filter CSV header aliases (field id => list of column name aliases).
		 *
		 * @since 3.7.0
		 *
		 * @param array<string, array<int, string>> $aliases Alias map.
		 */
		$aliases = (array) apply_filters( 'hvnly_csv_field_aliases', self::ALIASES );

		$lookup = array();
		foreach ( $aliases as $field_id => $alias_list ) {
			foreach ( (array) $alias_list as $alias ) {
				$norm = self::normalize( (string) $alias );
				if ( '' === $norm || isset( $lookup[ $norm ] ) ) {
					continue;
				}
				$lookup[ $norm ] = (string) $field_id;
			}
		}
		return $lookup;
	}

	/**
	 * Normalize a header for fuzzy matching.
	 *
	 * @param string $value Header text.
	 * @return string
	 */
	private static function normalize( string $value ): string {
		$value = strtolower( trim( $value ) );
		$value = preg_replace( '/[_\-\.]+/', ' ', $value );
		$value = preg_replace( '/\s+/', ' ', (string) $value );
		return trim( (string) $value );
	}

	/**
	 * Prevent instantiation.
	 */
	private function __construct() {}
}
