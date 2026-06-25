<?php
/**
 * UK location dataset for Property Import Wizard demo diversity.
 *
 * @package     Havenlytics
 * @subpackage  Admin/Data
 * @since       3.0.5
 */

namespace HvnlyNab\Admin\Data;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Rotating UK cities with realistic addresses and coordinates for demo imports.
 */
class UkImportLocations {

	/**
	 * UK street names used to build varied address lines.
	 *
	 * @var string[]
	 */
	private const STREETS = array(
		'High Street',
		'Church Road',
		'Victoria Road',
		'Station Road',
		'Park Lane',
		'Queens Road',
		'London Road',
		'King Street',
		'Market Street',
		'Bridge Street',
		'Mill Lane',
		'Green Lane',
		'Manor Road',
		'Church Lane',
		'New Road',
	);

	/**
	 * @return array<int, array{slug: string, city: string, county: string, latitude: float, longitude: float, postcode: string}>
	 */
	public static function get_locations(): array {
		static $locations = null;

		if ( null !== $locations ) {
			return $locations;
		}

		$locations = array(
			array( 'slug' => 'london', 'city' => 'London', 'county' => 'Greater London', 'latitude' => 51.5074, 'longitude' => -0.1278, 'postcode' => 'SW1A 1AA' ),
			array( 'slug' => 'manchester', 'city' => 'Manchester', 'county' => 'Greater Manchester', 'latitude' => 53.4808, 'longitude' => -2.2426, 'postcode' => 'M1 1AE' ),
			array( 'slug' => 'birmingham', 'city' => 'Birmingham', 'county' => 'West Midlands', 'latitude' => 52.4862, 'longitude' => -1.8904, 'postcode' => 'B1 1BB' ),
			array( 'slug' => 'liverpool', 'city' => 'Liverpool', 'county' => 'Merseyside', 'latitude' => 53.4084, 'longitude' => -2.9916, 'postcode' => 'L1 8JQ' ),
			array( 'slug' => 'leeds', 'city' => 'Leeds', 'county' => 'West Yorkshire', 'latitude' => 53.8008, 'longitude' => -1.5491, 'postcode' => 'LS1 1UR' ),
			array( 'slug' => 'bristol', 'city' => 'Bristol', 'county' => 'Bristol', 'latitude' => 51.4545, 'longitude' => -2.5879, 'postcode' => 'BS1 4DJ' ),
			array( 'slug' => 'nottingham', 'city' => 'Nottingham', 'county' => 'Nottinghamshire', 'latitude' => 52.9548, 'longitude' => -1.1581, 'postcode' => 'NG1 5DT' ),
			array( 'slug' => 'sheffield', 'city' => 'Sheffield', 'county' => 'South Yorkshire', 'latitude' => 53.3811, 'longitude' => -1.4701, 'postcode' => 'S1 2HE' ),
			array( 'slug' => 'newcastle', 'city' => 'Newcastle upon Tyne', 'county' => 'Tyne and Wear', 'latitude' => 54.9783, 'longitude' => -1.6178, 'postcode' => 'NE1 7RU' ),
			array( 'slug' => 'leicester', 'city' => 'Leicester', 'county' => 'Leicestershire', 'latitude' => 52.6369, 'longitude' => -1.1398, 'postcode' => 'LE1 5WW' ),
			array( 'slug' => 'edinburgh', 'city' => 'Edinburgh', 'county' => 'City of Edinburgh', 'latitude' => 55.9533, 'longitude' => -3.1883, 'postcode' => 'EH1 1YZ' ),
			array( 'slug' => 'glasgow', 'city' => 'Glasgow', 'county' => 'Glasgow City', 'latitude' => 55.8642, 'longitude' => -4.2518, 'postcode' => 'G1 1XW' ),
			array( 'slug' => 'cardiff', 'city' => 'Cardiff', 'county' => 'Cardiff', 'latitude' => 51.4816, 'longitude' => -3.1791, 'postcode' => 'CF10 1EP' ),
			array( 'slug' => 'belfast', 'city' => 'Belfast', 'county' => 'County Antrim', 'latitude' => 54.5973, 'longitude' => -5.9301, 'postcode' => 'BT1 5GS' ),
			array( 'slug' => 'southampton', 'city' => 'Southampton', 'county' => 'Hampshire', 'latitude' => 50.9097, 'longitude' => -1.4044, 'postcode' => 'SO14 2AQ' ),
			array( 'slug' => 'brighton', 'city' => 'Brighton', 'county' => 'East Sussex', 'latitude' => 50.8225, 'longitude' => -0.1372, 'postcode' => 'BN1 1EE' ),
			array( 'slug' => 'plymouth', 'city' => 'Plymouth', 'county' => 'Devon', 'latitude' => 50.3755, 'longitude' => -4.1427, 'postcode' => 'PL1 1AA' ),
			array( 'slug' => 'reading', 'city' => 'Reading', 'county' => 'Berkshire', 'latitude' => 51.4543, 'longitude' => -0.9781, 'postcode' => 'RG1 1AZ' ),
			array( 'slug' => 'derby', 'city' => 'Derby', 'county' => 'Derbyshire', 'latitude' => 52.9225, 'longitude' => -1.4746, 'postcode' => 'DE1 2FS' ),
			array( 'slug' => 'stoke-on-trent', 'city' => 'Stoke-on-Trent', 'county' => 'Staffordshire', 'latitude' => 53.0027, 'longitude' => -2.1794, 'postcode' => 'ST1 1DA' ),
			array( 'slug' => 'wolverhampton', 'city' => 'Wolverhampton', 'county' => 'West Midlands', 'latitude' => 52.5862, 'longitude' => -2.1288, 'postcode' => 'WV1 1LY' ),
			array( 'slug' => 'coventry', 'city' => 'Coventry', 'county' => 'West Midlands', 'latitude' => 52.4068, 'longitude' => -1.5197, 'postcode' => 'CV1 1FN' ),
			array( 'slug' => 'hull', 'city' => 'Kingston upon Hull', 'county' => 'East Yorkshire', 'latitude' => 53.7457, 'longitude' => -0.3367, 'postcode' => 'HU1 3RA' ),
			array( 'slug' => 'sunderland', 'city' => 'Sunderland', 'county' => 'Tyne and Wear', 'latitude' => 54.9069, 'longitude' => -1.3838, 'postcode' => 'SR1 1RR' ),
			array( 'slug' => 'york', 'city' => 'York', 'county' => 'North Yorkshire', 'latitude' => 53.9590, 'longitude' => -1.0815, 'postcode' => 'YO1 7HH' ),
			array( 'slug' => 'oxford', 'city' => 'Oxford', 'county' => 'Oxfordshire', 'latitude' => 51.7520, 'longitude' => -1.2577, 'postcode' => 'OX1 1BP' ),
			array( 'slug' => 'cambridge', 'city' => 'Cambridge', 'county' => 'Cambridgeshire', 'latitude' => 52.2053, 'longitude' => 0.1218, 'postcode' => 'CB2 1TN' ),
			array( 'slug' => 'norwich', 'city' => 'Norwich', 'county' => 'Norfolk', 'latitude' => 52.6309, 'longitude' => 1.2974, 'postcode' => 'NR1 3JQ' ),
			array( 'slug' => 'exeter', 'city' => 'Exeter', 'county' => 'Devon', 'latitude' => 50.7184, 'longitude' => -3.5339, 'postcode' => 'EX1 1GA' ),
			array( 'slug' => 'bournemouth', 'city' => 'Bournemouth', 'county' => 'Dorset', 'latitude' => 50.7192, 'longitude' => -1.8808, 'postcode' => 'BH1 2BU' ),
			array( 'slug' => 'swindon', 'city' => 'Swindon', 'county' => 'Wiltshire', 'latitude' => 51.5558, 'longitude' => -1.7797, 'postcode' => 'SN1 1BD' ),
			array( 'slug' => 'milton-keynes', 'city' => 'Milton Keynes', 'county' => 'Buckinghamshire', 'latitude' => 52.0406, 'longitude' => -0.7594, 'postcode' => 'MK9 1LA' ),
			array( 'slug' => 'northampton', 'city' => 'Northampton', 'county' => 'Northamptonshire', 'latitude' => 52.2405, 'longitude' => -0.9027, 'postcode' => 'NN1 1DP' ),
			array( 'slug' => 'aberdeen', 'city' => 'Aberdeen', 'county' => 'Aberdeenshire', 'latitude' => 57.1497, 'longitude' => -2.0943, 'postcode' => 'AB10 1AN' ),
			array( 'slug' => 'dundee', 'city' => 'Dundee', 'county' => 'Angus', 'latitude' => 56.4620, 'longitude' => -2.9707, 'postcode' => 'DD1 1DA' ),
			array( 'slug' => 'swansea', 'city' => 'Swansea', 'county' => 'Swansea', 'latitude' => 51.6214, 'longitude' => -3.9436, 'postcode' => 'SA1 3SN' ),
			array( 'slug' => 'newport', 'city' => 'Newport', 'county' => 'Gwent', 'latitude' => 51.5842, 'longitude' => -2.9977, 'postcode' => 'NP20 1FX' ),
			array( 'slug' => 'preston', 'city' => 'Preston', 'county' => 'Lancashire', 'latitude' => 53.7632, 'longitude' => -2.7031, 'postcode' => 'PR1 2HE' ),
			array( 'slug' => 'blackpool', 'city' => 'Blackpool', 'county' => 'Lancashire', 'latitude' => 53.8175, 'longitude' => -3.0357, 'postcode' => 'FY1 1RA' ),
			array( 'slug' => 'middlesbrough', 'city' => 'Middlesbrough', 'county' => 'North Yorkshire', 'latitude' => 54.5742, 'longitude' => -1.2350, 'postcode' => 'TS1 2AZ' ),
			array( 'slug' => 'bolton', 'city' => 'Bolton', 'county' => 'Greater Manchester', 'latitude' => 53.5785, 'longitude' => -2.4299, 'postcode' => 'BL1 1SE' ),
			array( 'slug' => 'bradford', 'city' => 'Bradford', 'county' => 'West Yorkshire', 'latitude' => 53.7950, 'longitude' => -1.7594, 'postcode' => 'BD1 1HY' ),
			array( 'slug' => 'luton', 'city' => 'Luton', 'county' => 'Bedfordshire', 'latitude' => 51.8787, 'longitude' => -0.4200, 'postcode' => 'LU1 2TA' ),
			array( 'slug' => 'slough', 'city' => 'Slough', 'county' => 'Berkshire', 'latitude' => 51.5105, 'longitude' => -0.5950, 'postcode' => 'SL1 1EL' ),
			array( 'slug' => 'warrington', 'city' => 'Warrington', 'county' => 'Cheshire', 'latitude' => 53.3900, 'longitude' => -2.5970, 'postcode' => 'WA1 1LR' ),
			array( 'slug' => 'telford', 'city' => 'Telford', 'county' => 'Shropshire', 'latitude' => 52.6760, 'longitude' => -2.4460, 'postcode' => 'TF3 4JG' ),
			array( 'slug' => 'peterborough', 'city' => 'Peterborough', 'county' => 'Cambridgeshire', 'latitude' => 52.5695, 'longitude' => -0.2405, 'postcode' => 'PE1 1DA' ),
			array( 'slug' => 'colchester', 'city' => 'Colchester', 'county' => 'Essex', 'latitude' => 51.8959, 'longitude' => 0.8919, 'postcode' => 'CO1 1UG' ),
			array( 'slug' => 'cheltenham', 'city' => 'Cheltenham', 'county' => 'Gloucestershire', 'latitude' => 51.8994, 'longitude' => -2.0783, 'postcode' => 'GL50 3GA' ),
			array( 'slug' => 'bath', 'city' => 'Bath', 'county' => 'Somerset', 'latitude' => 51.3811, 'longitude' => -2.3590, 'postcode' => 'BA1 1AN' ),
			array( 'slug' => 'chester', 'city' => 'Chester', 'county' => 'Cheshire', 'latitude' => 53.1934, 'longitude' => -2.8931, 'postcode' => 'CH1 1NP' ),
			array( 'slug' => 'lancaster', 'city' => 'Lancaster', 'county' => 'Lancashire', 'latitude' => 54.0466, 'longitude' => -2.8007, 'postcode' => 'LA1 1PJ' ),
			array( 'slug' => 'canterbury', 'city' => 'Canterbury', 'county' => 'Kent', 'latitude' => 51.2802, 'longitude' => 1.0789, 'postcode' => 'CT1 2TF' ),
			array( 'slug' => 'ipswich', 'city' => 'Ipswich', 'county' => 'Suffolk', 'latitude' => 52.0594, 'longitude' => 1.1556, 'postcode' => 'IP1 1DH' ),
			array( 'slug' => 'hastings', 'city' => 'Hastings', 'county' => 'East Sussex', 'latitude' => 50.8543, 'longitude' => 0.5735, 'postcode' => 'TN34 1BA' ),
		);

		return $locations;
	}

	/**
	 * Default London map location when UK resolution is unavailable.
	 *
	 * @return array<string, string>
	 */
	public static function get_london_fallback(): array {
		return array(
			'map_address'   => 'London, United Kingdom',
			'map_latitude'  => '51.5074',
			'map_longitude' => '-0.1278',
			'latitude'      => '51.5074',
			'longitude'     => '-0.1278',
			'full_address'  => 'London, United Kingdom',
			'town_city'     => 'London',
			'country_state' => 'Greater London',
			'country_location' => 'GB',
		);
	}

	/**
	 * Resolve one UK location for a zero-based import index.
	 *
	 * @param int $import_index Zero-based property index in the import run.
	 * @return array<string, mixed>
	 */
	public static function resolve_for_index( int $import_index ): array {
		$locations = self::get_locations();
		if ( empty( $locations ) ) {
			return self::get_london_fallback();
		}
		$count     = count( $locations );
		$location  = $locations[ $import_index % $count ];
		$cycle     = (int) floor( $import_index / $count );

		$building_number = (string) ( ( $import_index % 199 ) + 1 );
		$street          = self::STREETS[ $import_index % count( self::STREETS ) ];
		$address_line_1  = $building_number . ' ' . $street;

		$jitter = self::coordinate_jitter( $import_index, $cycle );
		$lat    = round( $location['latitude'] + $jitter['lat'], 6 );
		$lng    = round( $location['longitude'] + $jitter['lng'], 6 );

		$full_address = sprintf(
			'%1$s, %2$s, %3$s, %4$s',
			$address_line_1,
			$location['city'],
			$location['county'],
			$location['postcode']
		);

		return array(
			'slug'            => $location['slug'],
			'city'            => $location['city'],
			'county'          => $location['county'],
			'postcode'        => $location['postcode'],
			'building_number' => $building_number,
			'street'          => $street,
			'address_line_1'  => $address_line_1,
			'address_line_2'  => '',
			'town_city'       => $location['city'],
			'country_state'   => $location['county'],
			'zip_code'        => $location['postcode'],
			'full_address'    => $full_address,
			'map_address'     => $full_address,
			'map_latitude'    => (string) $lat,
			'map_longitude'   => (string) $lng,
			'latitude'        => (string) $lat,
			'longitude'       => (string) $lng,
			'country_location'=> 'GB',
			'locations'       => array( $location['slug'] ),
		);
	}

	/**
	 * Merge a resolved UK location into property import payload.
	 *
	 * @param array<string, mixed> $property_data Property payload.
	 * @param int                  $import_index  Zero-based property index in the import run.
	 * @return array<string, mixed>
	 */
	public static function apply_to_property_data( array $property_data, int $import_index ): array {
		return array_merge( $property_data, self::resolve_for_index( $import_index ) );
	}

	/**
	 * Small coordinate offset so repeated city cycles do not stack on one pin.
	 *
	 * @param int $import_index Zero-based import index.
	 * @param int $cycle        How many full dataset rotations have occurred.
	 * @return array{lat: float, lng: float}
	 */
	private static function coordinate_jitter( int $import_index, int $cycle ): array {
		if ( 0 === $cycle ) {
			return array(
				'lat' => ( ( $import_index % 5 ) - 2 ) * 0.0015,
				'lng' => ( ( (int) floor( $import_index / 5 ) % 5 ) - 2 ) * 0.0015,
			);
		}

		return array(
			'lat' => ( ( $cycle % 7 ) - 3 ) * 0.004,
			'lng' => ( ( (int) floor( $cycle / 7 ) % 7 ) - 3 ) * 0.004,
		);
	}
}
