<?php
/**
 * Explicit CSV field allowlist — default property fields + three groups.
 *
 * @package HvnlyNab\CsvTransfer\Mapping
 * @since   3.6.0
 */

namespace HvnlyNab\CsvTransfer\Mapping;

defined( 'ABSPATH' ) || exit;

/**
 * CsvSupportedSchema — stable, explicit CSV contract.
 *
 * CSV export/import supports ONLY:
 * 1. Default Havenlytics property fields (post, meta, taxonomies, featured image)
 * 2. Property Gallery group
 * 3. Property Video group
 * 4. Property Location (map) group
 *
 * Everything else (documents, FAQ, highlights, agents, custom builder, …)
 * remains outside the CSV catalog. Import handlers for legacy columns stay
 * in place for backward compatibility via {@see self::legacy_import_field_ids()}.
 *
 * @since 3.6.0
 */
final class CsvSupportedSchema {

	/**
	 * Ordered field ids written / offered by default for CSV export.
	 *
	 * @return array<int, string>
	 */
	public static function field_ids(): array {
		return array(
			// Core post.
			'title',
			'slug',
			'content',
			'excerpt',
			'status',
			'featured',

			// Identifiers / pricing.
			'price',
			'hoa_fee',
			'annual_tax',
			'mls',
			'reference',

			// Property details.
			'bedrooms',
			'bathrooms',
			'half_bathrooms',
			'kitchens',
			'reception_rooms',
			'rooms',
			'floors',
			'area',
			'lot_size',
			'garage_sqft',
			'year_built',
			'heating',
			'cooling',
			'water',

			// Address meta (default location fields).
			'building_number',
			'street',
			'address_line_1',
			'address_line_2',
			'city',
			'state',
			'zip',
			'country',
			'location_setting',

			// Property Location group (map).
			'address',
			'latitude',
			'longitude',

			// Taxonomies + amenities.
			'department',
			'property_type',
			'property_status',
			'location',
			'tags',
			'features',
			'amenities',
			'badges',
			'categories',

			// Media — featured image.
			'featured_image',

			// Property Gallery group.
			'gallery_title',
			'gallery',

			// Property Video group.
			'video_title',
			'video_url',
			'video_thumbnail',
		);
	}

	/**
	 * Whether a field id is in the supported CSV contract.
	 *
	 * @param string $field_id Field id.
	 * @return bool
	 */
	public static function is_supported( string $field_id ): bool {
		static $flip = null;
		if ( null === $flip ) {
			$flip = array_flip( self::field_ids() );
		}
		return isset( $flip[ $field_id ] );
	}

	/**
	 * Field ids intentionally outside the CSV contract.
	 *
	 * Kept for documentation and for legacy import resolution only.
	 *
	 * @return array<int, string>
	 */
	public static function unsupported_field_ids(): array {
		return array(
			'documents',
			'faq_items',
			'highlights',
			'listing_features',
			'agent_email',
			'agent_username',
			'co_agents',
			'views',
			'author',
			'post_date',
			'menu_order',
			'sticky',
		);
	}

	/**
	 * Legacy / out-of-scope field ids that import may still resolve.
	 *
	 * Ensures existing CSVs and mapping profiles continue to work when they
	 * reference columns that are no longer offered in the export catalog.
	 *
	 * @return array<int, string>
	 */
	public static function legacy_import_field_ids(): array {
		return self::unsupported_field_ids();
	}

	/**
	 * Human-readable unsupported group labels (for audits / docs).
	 *
	 * @return array<int, string>
	 */
	public static function unsupported_groups(): array {
		return array(
			'Property Documents',
			'FAQ',
			'Property Highlights',
			'Listing Agents',
			'Listing Features (builder checklist)',
			'Custom Builder Repeaters',
			'Future Dynamic Groups',
			'Experimental Builder Types',
		);
	}

	/**
	 * Prevent instantiation.
	 */
	private function __construct() {}
}
