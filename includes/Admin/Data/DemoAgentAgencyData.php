<?php
/**
 * Demo Agent & Agency data for the Property Import Wizard.
 *
 * @package     Havenlytics
 * @subpackage  Admin/Data
 * @since       3.0.2
 */

namespace HvnlyNab\Admin\Data;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Static demo profiles for agencies, agents, and property assignments.
 *
 * @since 3.0.2
 */
final class DemoAgentAgencyData {

	/**
	 * Demo agency definitions.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_demo_agencies(): array {
		return array(
			array(
				'slug'        => 'haven-realty-group',
				'name'        => __( 'Haven Realty Group', 'havenlytics' ),
				'description' => __( 'Full-service residential brokerage specializing in family homes, new construction, and investment properties across Texas.', 'havenlytics' ),
				'email'       => 'contact@havenrealty.demo',
				'phone'       => '+1 (512) 555-0101',
				'website'     => 'https://demo.havenlytics.com/',
				'address'     => "1200 Congress Avenue, Suite 400\nAustin, TX 78701",
				'license'     => 'TX-BRK-10234',
				'map_lat'     => '30.2672',
				'map_lng'     => '-97.7431',
				'logo_image'  => 1,
			),
			array(
				'slug'        => 'urban-living-properties',
				'name'        => __( 'Urban Living Properties', 'havenlytics' ),
				'description' => __( 'Boutique agency focused on downtown lofts, condos, and walkable urban neighborhoods for young professionals.', 'havenlytics' ),
				'email'       => 'hello@urbanliving.demo',
				'phone'       => '+1 (512) 555-0202',
				'website'     => 'https://urbanliving.demo',
				'address'     => "500 West 2nd Street\nAustin, TX 78701",
				'license'     => 'TX-BRK-20456',
				'map_lat'     => '30.2650',
				'map_lng'     => '-97.7490',
				'logo_image'  => 2,
			),
			array(
				'slug'        => 'prime-estates',
				'name'        => __( 'Prime Estates', 'havenlytics' ),
				'description' => __( 'Trusted advisors for suburban estates, golf communities, and executive relocations with white-glove service.', 'havenlytics' ),
				'email'       => 'info@primeestates.demo',
				'phone'       => '+1 (512) 555-0303',
				'website'     => 'https://primeestates.demo',
				'address'     => "8800 North Mopac Expressway\nAustin, TX 78759",
				'license'     => 'TX-BRK-30789',
				'map_lat'     => '30.3850',
				'map_lng'     => '-97.7370',
				'logo_image'  => 3,
			),
			array(
				'slug'        => 'luxury-homes-agency',
				'name'        => __( 'Luxury Homes Agency', 'havenlytics' ),
				'description' => __( 'Premier luxury brokerage representing waterfront estates, penthouses, and architect-designed residences nationwide.', 'havenlytics' ),
				'email'       => 'concierge@luxuryhomes.demo',
				'phone'       => '+1 (310) 555-0404',
				'website'     => 'https://luxuryhomes.demo',
				'address'     => "2100 Ocean Avenue\nMalibu, CA 90265",
				'license'     => 'CA-BRK-44521',
				'map_lat'     => '34.0259',
				'map_lng'     => '-118.7798',
				'logo_image'  => 4,
			),
			array(
				'slug'        => 'city-property-experts',
				'name'        => __( 'City Property Experts', 'havenlytics' ),
				'description' => __( 'Multi-market team covering rentals, commercial conversions, and mixed-use investments in major metro areas.', 'havenlytics' ),
				'email'       => 'team@cityproperty.demo',
				'phone'       => '+1 (312) 555-0505',
				'website'     => 'https://citypropertyexperts.demo',
				'address'     => "233 South Wacker Drive\nChicago, IL 60606",
				'license'     => 'IL-BRK-55678',
				'map_lat'     => '41.8789',
				'map_lng'     => '-87.6359',
				'logo_image'  => 5,
			),
		);
	}

	/**
	 * Demo agent definitions.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_demo_agents(): array {
		return array(
			array(
				'slug'         => 'sarah-mitchell',
				'name'         => __( 'Sarah Mitchell', 'havenlytics' ),
				'agency_slug'  => 'haven-realty-group',
				'position'     => __( 'Senior Listing Agent', 'havenlytics' ),
				'email'        => 'sarah.mitchell@havenrealty.demo',
				'phone'        => '+1 (512) 555-1101',
				'whatsapp'     => '+15125551101',
				'license'      => 'TX-SA-88201',
				'photo_image'  => 1,
				'availability' => 'available',
				'experience'   => '12',
				'biography'    => __( 'Sarah specializes in family homes and pool properties across Greater Austin. With twelve years in residential sales, she is known for staging advice and smooth closings.', 'havenlytics' ),
				'facebook'     => 'https://facebook.com/demo.sarah.mitchell',
				'linkedin'     => 'https://linkedin.com/in/demo-sarah-mitchell',
				'instagram'    => 'https://instagram.com/demo_sarah_mitchell',
			),
			array(
				'slug'         => 'james-cole',
				'name'         => __( 'James Cole', 'havenlytics' ),
				'agency_slug'  => 'haven-realty-group',
				'position'     => __( 'Buyers Agent', 'havenlytics' ),
				'email'        => 'james.cole@havenrealty.demo',
				'phone'        => '+1 (512) 555-1102',
				'whatsapp'     => '+15125551102',
				'license'      => 'TX-SA-88202',
				'photo_image'  => 2,
				'availability' => 'busy',
				'experience'   => '8',
				'biography'    => __( 'James helps first-time buyers navigate inspections, financing, and negotiations. He covers suburban ranch and new construction inventory.', 'havenlytics' ),
				'facebook'     => 'https://facebook.com/demo.james.cole',
				'linkedin'     => 'https://linkedin.com/in/demo-james-cole',
				'twitter'      => 'https://twitter.com/demo_james_cole',
			),
			array(
				'slug'         => 'elena-vasquez',
				'name'         => __( 'Elena Vasquez', 'havenlytics' ),
				'agency_slug'  => 'urban-living-properties',
				'position'     => __( 'Urban Condo Specialist', 'havenlytics' ),
				'email'        => 'elena.vasquez@urbanliving.demo',
				'phone'        => '+1 (512) 555-2201',
				'whatsapp'     => '+15125552201',
				'license'      => 'TX-SA-77301',
				'photo_image'  => 3,
				'availability' => 'available',
				'experience'   => '10',
				'biography'    => __( 'Elena focuses on downtown lofts, high-rise condos, and walk-score neighborhoods. Bilingual service in English and Spanish.', 'havenlytics' ),
				'instagram'    => 'https://instagram.com/demo_elena_vasquez',
				'linkedin'     => 'https://linkedin.com/in/demo-elena-vasquez',
				'youtube'      => 'https://youtube.com/@demoelenavasquez',
			),
			array(
				'slug'         => 'marcus-turner',
				'name'         => __( 'Marcus Turner', 'havenlytics' ),
				'agency_slug'  => 'urban-living-properties',
				'position'     => __( 'Rental & Leasing Advisor', 'havenlytics' ),
				'email'        => 'marcus.turner@urbanliving.demo',
				'phone'        => '+1 (512) 555-2202',
				'whatsapp'     => '+15125552202',
				'license'      => 'TX-SA-77302',
				'photo_image'  => 4,
				'availability' => 'away',
				'experience'   => '6',
				'biography'    => __( 'Marcus manages urban rental portfolios and helps investors evaluate cap rates on multi-family and apartment conversions.', 'havenlytics' ),
				'linkedin'     => 'https://linkedin.com/in/demo-marcus-turner',
				'instagram'    => 'https://instagram.com/demo_marcus_turner',
			),
			array(
				'slug'         => 'olivia-chen',
				'name'         => __( 'Olivia Chen', 'havenlytics' ),
				'agency_slug'  => 'prime-estates',
				'position'     => __( 'Luxury Suburban Advisor', 'havenlytics' ),
				'email'        => 'olivia.chen@primeestates.demo',
				'phone'        => '+1 (512) 555-3301',
				'whatsapp'     => '+15125553301',
				'license'      => 'TX-SA-66401',
				'photo_image'  => 5,
				'availability' => 'available',
				'experience'   => '14',
				'biography'    => __( 'Olivia represents golf-course communities, gated neighborhoods, and executive estates with discreet, data-driven marketing.', 'havenlytics' ),
				'facebook'     => 'https://facebook.com/demo.olivia.chen',
				'linkedin'     => 'https://linkedin.com/in/demo-olivia-chen',
				'instagram'    => 'https://instagram.com/demo_olivia_chen',
			),
			array(
				'slug'         => 'david-park',
				'name'         => __( 'David Park', 'havenlytics' ),
				'agency_slug'  => 'prime-estates',
				'position'     => __( 'Investment Property Broker', 'havenlytics' ),
				'email'        => 'david.park@primeestates.demo',
				'phone'        => '+1 (512) 555-3302',
				'whatsapp'     => '+15125553302',
				'license'      => 'TX-SA-66402',
				'photo_image'  => 6,
				'availability' => 'offline',
				'experience'   => '11',
				'biography'    => __( 'David advises on duplexes, land acquisitions, and cash-flow properties. He prepares ROI summaries for investor clients.', 'havenlytics' ),
				'linkedin'     => 'https://linkedin.com/in/demo-david-park',
				'twitter'      => 'https://twitter.com/demo_david_park',
			),
			array(
				'slug'         => 'victoria-sterling',
				'name'         => __( 'Victoria Sterling', 'havenlytics' ),
				'agency_slug'  => 'luxury-homes-agency',
				'position'     => __( 'Luxury Portfolio Director', 'havenlytics' ),
				'email'        => 'victoria.sterling@luxuryhomes.demo',
				'phone'        => '+1 (310) 555-4401',
				'whatsapp'     => '+13105554401',
				'license'      => 'CA-SA-55101',
				'photo_image'  => 7,
				'availability' => 'available',
				'experience'   => '18',
				'biography'    => __( 'Victoria leads the coastal luxury division, representing beachfront estates, Mediterranean villas, and architect-designed residences.', 'havenlytics' ),
				'instagram'    => 'https://instagram.com/demo_victoria_sterling',
				'linkedin'     => 'https://linkedin.com/in/demo-victoria-sterling',
				'facebook'     => 'https://facebook.com/demo.victoria.sterling',
			),
			array(
				'slug'         => 'robert-langford',
				'name'         => __( 'Robert Langford', 'havenlytics' ),
				'agency_slug'  => 'luxury-homes-agency',
				'position'     => __( 'Penthouse & High-Rise Specialist', 'havenlytics' ),
				'email'        => 'robert.langford@luxuryhomes.demo',
				'phone'        => '+1 (305) 555-4402',
				'whatsapp'     => '+13055554402',
				'license'      => 'FL-SA-55102',
				'photo_image'  => 8,
				'availability' => 'busy',
				'experience'   => '15',
				'biography'    => __( 'Robert markets penthouses and trophy condos in Miami, Chicago, and San Francisco with concierge-level buyer representation.', 'havenlytics' ),
				'linkedin'     => 'https://linkedin.com/in/demo-robert-langford',
				'youtube'      => 'https://youtube.com/@demorobertlangford',
			),
			array(
				'slug'         => 'aisha-rahman',
				'name'         => __( 'Aisha Rahman', 'havenlytics' ),
				'agency_slug'  => 'city-property-experts',
				'position'     => __( 'Commercial & Mixed-Use Agent', 'havenlytics' ),
				'email'        => 'aisha.rahman@cityproperty.demo',
				'phone'        => '+1 (312) 555-5501',
				'whatsapp'     => '+13125555501',
				'license'      => 'IL-SA-66201',
				'photo_image'  => 9,
				'availability' => 'away',
				'experience'   => '9',
				'biography'    => __( 'Aisha handles retail, office, and mixed-use listings. She coordinates site tours and lease negotiations for business owners.', 'havenlytics' ),
				'linkedin'     => 'https://linkedin.com/in/demo-aisha-rahman',
				'instagram'    => 'https://instagram.com/demo_aisha_rahman',
			),
			array(
				'slug'         => 'michael-brooks',
				'name'         => __( 'Michael Brooks', 'havenlytics' ),
				'agency_slug'  => 'city-property-experts',
				'position'     => __( 'Regional Sales Manager', 'havenlytics' ),
				'email'        => 'michael.brooks@cityproperty.demo',
				'phone'        => '+1 (312) 555-5502',
				'whatsapp'     => '+13125555502',
				'license'      => 'IL-SA-66202',
				'photo_image'  => 10,
				'availability' => 'available',
				'experience'   => '13',
				'biography'    => __( 'Michael oversees multi-market residential sales and mentors agent teams. He excels at cross-agency co-listings on high-value properties.', 'havenlytics' ),
				'facebook'     => 'https://facebook.com/demo.michael.brooks',
				'linkedin'     => 'https://linkedin.com/in/demo-michael-brooks',
				'twitter'      => 'https://twitter.com/demo_michael_brooks',
			),
		);
	}

	/**
	 * Agent slug sets per demo property template index (0–21).
	 *
	 * Mix of single-agent, multi-agent, and cross-agency assignments.
	 *
	 * @return array<int, array<int, string>>
	 */
	public static function get_property_agent_assignments(): array {
		return array(
			array( 'sarah-mitchell' ),
			array( 'elena-vasquez', 'marcus-turner' ),
			array( 'james-cole' ),
			array( 'victoria-sterling' ),
			array( 'elena-vasquez', 'olivia-chen' ),
			array( 'marcus-turner' ),
			array( 'olivia-chen', 'david-park' ),
			array( 'sarah-mitchell', 'james-cole' ),
			array( 'david-park' ),
			array( 'victoria-sterling', 'robert-langford' ),
			array( 'aisha-rahman' ),
			array( 'elena-vasquez' ),
			array( 'olivia-chen' ),
			array( 'victoria-sterling' ),
			array( 'michael-brooks', 'aisha-rahman' ),
			array( 'robert-langford' ),
			array( 'sarah-mitchell', 'olivia-chen' ),
			array( 'david-park', 'michael-brooks' ),
			array( 'james-cole', 'marcus-turner' ),
			array( 'victoria-sterling', 'aisha-rahman' ),
			array( 'michael-brooks' ),
			array( 'robert-langford', 'elena-vasquez' ),
		);
	}

	/**
	 * Resolve agent slugs for a property template index.
	 *
	 * @param int $property_index Demo property data index.
	 * @return string[]
	 */
	public static function get_agent_slugs_for_property_index( int $property_index ): array {
		$patterns = self::get_property_agent_assignments();
		if ( empty( $patterns ) ) {
			return array();
		}

		$index = absint( $property_index ) % count( $patterns );

		return $patterns[ $index ];
	}

	/**
	 * Demo agent photo URLs.
	 *
	 * Intentionally empty: demo agents use the local AvatarService placeholder
	 * (no remote CDN sideloads, no media library attachments).
	 *
	 * @return string[]
	 */
	public static function get_agent_photo_urls(): array {
		return array();
	}

	/**
	 * Demo agency logo URLs.
	 *
	 * Intentionally empty: demo agencies use the local agency placeholder SVG
	 * (no remote CDN sideloads, no media library attachments).
	 *
	 * @return string[]
	 */
	public static function get_agency_logo_urls(): array {
		return array();
	}

	/**
	 * Demo agent portrait URL by 1-based index.
	 *
	 * Always empty — callers skip sideload; AvatarService supplies the local placeholder.
	 *
	 * @param int $image_index 1-based image number.
	 * @return string
	 */
	public static function get_agent_photo_url( int $image_index ): string {
		unset( $image_index );
		return '';
	}

	/**
	 * Demo agency logo URL by 1-based index.
	 *
	 * Always empty — callers skip sideload; AgencyFields supplies the local placeholder.
	 *
	 * @param int $image_index 1-based image number.
	 * @return string
	 */
	public static function get_agency_logo_url( int $image_index ): string {
		unset( $image_index );
		return '';
	}

	/**
	 * Demo image URL by index (legacy — property gallery CDN).
	 *
	 * @deprecated 3.0.3 Use get_agent_photo_url() or get_agency_logo_url().
	 * @param int $image_index 1-based image number.
	 * @return string
	 */
	public static function get_demo_image_url( int $image_index ): string {
		return self::get_agent_photo_url( $image_index );
	}

	/**
	 * Prevent instantiation.
	 */
	private function __construct() {}
}