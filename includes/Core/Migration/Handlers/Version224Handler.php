<?php
/**
 * Version 2.2.4 Migration Handler
 *
 * Adds missing Additional Information and Address & Neighborhood sections.
 *
 * @package     Havenlytics
 * @subpackage  Core\Migration\Handlers
 * @since       2.2.4
 */

namespace HvnlyNab\Core\Migration\Handlers;

use HvnlyNab\Core\Migration\Interfaces\MigrationInterface;
use HvnlyNab\Core\Migration\Traits\MigrationTrait;
use HvnlyNab\Core\SectionIdentity;

// Prevent direct access.
if ( ! defined('ABSPATH')) {
    exit;
}

/**
 * Class Version224Handler
 *
 * Handles adding missing sections to property builder configuration.
 *
 * @since 2.2.4
 */
class Version224Handler implements MigrationInterface {

    use MigrationTrait;

    /**
     * Property builder storage key.
     *
     * @var string
     */
    const PROPERTY_BUILDER_KEY = 'hvnly_property_builder.sections';

    /**
     * Get the target version.
     *
     * @since 2.2.4
     * @return string
     */
    public function get_version(): string {
        return '2.2.4';
    }

    /**
     * Get migration description.
     *
     * @since 2.2.4
     * @return string
     */
    public function get_description(): string {
        return 'Adds missing Additional Information and Address & Neighborhood sections to property builder';
    }

    /**
     * Check if migration is needed.
     *
     * @since 2.2.4
     * @return bool
     */
    public function is_needed(): bool {
        $sections = get_option(self::PROPERTY_BUILDER_KEY, array());

        if (empty($sections)) {
            return true;
        }

        // Check if missing sections via SectionIdentity (sole alias source of truth).
        $missing = array();
        if ( ! SectionIdentity::has_equivalent_section( $sections, SectionIdentity::SEC_PROPERTY_DETAILS ) ) {
            $missing[] = SectionIdentity::SEC_PROPERTY_DETAILS;
        }
        if ( ! isset($sections['sec_address_neighborhood']) && ! isset($sections['sec_address_neighborhood_legacy'])) {
            $missing[] = 'sec_address_neighborhood';
        }

        return ! empty($missing);
    }

    /**
     * Execute the migration.
     *
     * @since 2.2.4
     * @return bool
     */
    public function up(): bool {
        $this->log('Starting migration to version ' . $this->get_version());
        $this->log('Adding missing Additional Information and Address & Neighborhood sections');

        // Create backup
        $backup_id = $this->backup_option(self::PROPERTY_BUILDER_KEY, '2.2.4');
        if ($backup_id) {
            $this->log('Backup created: ' . $backup_id);
        }

        $sections = get_option(self::PROPERTY_BUILDER_KEY, array());

        if (empty($sections)) {
            // Get complete configuration from UnifiedFieldGenerator
            if (class_exists('\HvnlyNab\Core\UnifiedFieldGenerator')) {
                $unified  = \HvnlyNab\Core\UnifiedFieldGenerator::get_instance();
                $sections = $unified->get_unified_configuration();
                update_option(self::PROPERTY_BUILDER_KEY, $sections);
                $this->log('Created complete configuration with ALL 7 sections');
            }
            return true;
        }

        $updated = false;

        // Add Additional Information only when SectionIdentity finds no equivalent.
        if ( ! SectionIdentity::has_equivalent_section( $sections, SectionIdentity::SEC_PROPERTY_DETAILS ) ) {
            $section = $this->get_additional_info_section();
            $sections[ SectionIdentity::SEC_PROPERTY_DETAILS ] = $section;
            $updated = true;
            $this->log('Added Additional Information section');
        }

        // Add Address & Neighborhood section if missing
        if ( ! isset($sections['sec_address_neighborhood']) && ! isset($sections['sec_address_neighborhood_legacy'])) {
            $sections['sec_address_neighborhood'] = $this->get_address_neighborhood_section();
            $updated                              = true;
            $this->log('Added Address & Neighborhood section');
        }

        // Reorder sections
        if ($updated) {
            $sections = $this->reorder_sections($sections);
            update_option(self::PROPERTY_BUILDER_KEY, $sections);
            wp_cache_delete(self::PROPERTY_BUILDER_KEY, 'options');
            $this->log('Sections reordered and saved successfully');
        } else {
            $this->log('No missing sections found, migration not needed');
        }

        // Clean up old backups
        $this->cleanup_backups(5);

        $this->log('Migration completed');
        return true;
    }

    /**
     * Get Additional Information section.
     *
     * @since 2.2.4
     * @return array
     */
    private function get_additional_info_section(): array {
        return array(
            'id' => SectionIdentity::SEC_PROPERTY_DETAILS,
            'title' => 'Additional Information',
            'icon' => 'fas fa-info-circle',
            'required' => false,
            'order' => 1,
            'collapsed' => false,
            'fields' => array(
                array(
                    'id' => '_hvnly_property_sqft',
                    'name' => '_hvnly_property_sqft',
                    'type' => 'number',
                    'label' => 'Property Area, sq ft',
                    'placeholder' => 'Enter property area in square feet',
                    'required' => true,
                    'locked' => false,
                    'order' => 0,
                ),
                array(
                    'id' => '_hvnly_property_lot_size',
                    'name' => '_hvnly_property_lot_size',
                    'type' => 'text',
                    'label' => 'Property Lot size, sq ft',
                    'placeholder' => 'Enter property lot size',
                    'required' => true,
                    'locked' => false,
                    'order' => 1,
                ),
                array(
                    'id' => '_hvnly_property_hoa_fee',
                    'name' => '_hvnly_property_hoa_fee',
                    'type' => 'text',
                    'label' => 'Property HOA Fee',
                    'placeholder' => 'Enter property HOA fee',
                    'required' => false,
                    'locked' => false,
                    'order' => 2,
                ),
                array(
                    'id' => '_hvnly_property_annual_tax_amount',
                    'name' => '_hvnly_property_annual_tax_amount',
                    'type' => 'number',
                    'label' => 'Property Annual Tax Amount',
                    'placeholder' => 'Enter property annual tax amount',
                    'required' => false,
                    'locked' => false,
                    'order' => 3,
                ),
                array(
                    'id' => '_hvnly_property_heating',
                    'name' => '_hvnly_property_heating',
                    'type' => 'select',
                    'label' => 'Heating',
                    'required' => false,
                    'locked' => false,
                    'order' => 4,
                    'options' => array(
                        'forced_air' => 'Forced Air',
                        'radiator' => 'Radiator',
                        'heat_pump' => 'Heat Pump',
                        'baseboard' => 'Baseboard',
                        'none' => 'None',
                    ),
                ),
                array(
                    'id' => '_hvnly_property_cooling',
                    'name' => '_hvnly_property_cooling',
                    'type' => 'select',
                    'label' => 'Cooling',
                    'required' => false,
                    'locked' => false,
                    'order' => 5,
                    'options' => array(
                        'central' => 'Central Air',
                        'window' => 'Window Units',
                        'heat_pump' => 'Heat Pump',
                        'baseboard' => 'Baseboard',
                        'none' => 'None',
                    ),
                ),
                array(
                    'id' => '_hvnly_property_water',
                    'name' => '_hvnly_property_water',
                    'type' => 'select',
                    'label' => 'Water Source',
                    'required' => false,
                    'locked' => false,
                    'order' => 6,
                    'options' => array(
                        'city' => 'City',
                        'well' => 'Well',
                        'shared_well' => 'Shared Well',
                        'none' => 'None',
                    ),
                ),
            ),
        );
    }

    /**
     * Get Address & Neighborhood section.
     *
     * @since 2.2.4
     * @return array
     */
    private function get_address_neighborhood_section(): array {
        return array(
            'id' => 'sec_address_neighborhood',
            'title' => 'Address & Neighborhood',
            'icon' => 'fas fa-building',
            'required' => false,
            'order' => 2,
            'collapsed' => false,
            'fields' => array(
                array(
                    'id' => '_hvnly_property_reference_number',
                    'name' => '_hvnly_property_reference_number',
                    'type' => 'text',
                    'label' => 'Property Reference Number',
                    'placeholder' => 'Enter property reference number',
                    'required' => false,
                    'locked' => false,
                    'order' => 0,
                ),
                array(
                    'id' => '_hvnly_property_building_number',
                    'name' => '_hvnly_property_building_number',
                    'type' => 'text',
                    'label' => 'Property Building Number',
                    'placeholder' => 'Enter property building number',
                    'required' => false,
                    'locked' => false,
                    'order' => 1,
                ),
                array(
                    'id' => '_hvnly_property_street',
                    'name' => '_hvnly_property_street',
                    'type' => 'text',
                    'label' => 'Property Street',
                    'placeholder' => 'Enter property street',
                    'required' => false,
                    'locked' => false,
                    'order' => 2,
                ),
                array(
                    'id' => '_hvnly_property_address_line_1',
                    'name' => '_hvnly_property_address_line_1',
                    'type' => 'text',
                    'label' => 'Property Address Line 1',
                    'placeholder' => 'Enter property address line 1',
                    'required' => false,
                    'locked' => false,
                    'order' => 3,
                ),
                array(
                    'id' => '_hvnly_property_address_line_2',
                    'name' => '_hvnly_property_address_line_2',
                    'type' => 'text',
                    'label' => 'Property Address Line 2',
                    'placeholder' => 'Enter property address line 2',
                    'required' => false,
                    'locked' => false,
                    'order' => 4,
                ),
                array(
                    'id' => '_hvnly_property_town_city',
                    'name' => '_hvnly_property_town_city',
                    'type' => 'text',
                    'label' => 'Property Town/City',
                    'placeholder' => 'Enter property town/city',
                    'required' => false,
                    'locked' => false,
                    'order' => 5,
                ),
                array(
                    'id' => '_hvnly_property_country_state',
                    'name' => '_hvnly_property_country_state',
                    'type' => 'text',
                    'label' => 'Property Country/State',
                    'placeholder' => 'Enter property country/state',
                    'required' => false,
                    'locked' => false,
                    'order' => 6,
                ),
                array(
                    'id' => '_hvnly_property_zip_code',
                    'name' => '_hvnly_property_zip_code',
                    'type' => 'text',
                    'label' => 'Property Zip Code',
                    'placeholder' => 'Enter property zip code',
                    'required' => false,
                    'locked' => false,
                    'order' => 7,
                ),
                array(
                    'id' => '_hvnly_property_location',
                    'name' => '_hvnly_property_location',
                    'type' => 'select',
                    'label' => 'Property Location',
                    'required' => false,
                    'locked' => false,
                    'order' => 8,
                    'options' => $this->get_property_locations(),
                ),
                array(
                    'id' => '_hvnly_property_country_location',
                    'name' => '_hvnly_property_country_location',
                    'type' => 'select',
                    'label' => 'Property Country',
                    'required' => false,
                    'locked' => false,
                    'order' => 9,
                    'options' => $this->get_property_countries(),
                ),
            ),
        );
    }

    /**
     * Get property locations for select field.
     *
     * @since 2.2.4
     * @return array
     */
    private function get_property_locations(): array {
        return array(
            'new-york' => 'New York',
            'los-angeles' => 'Los Angeles',
            'chicago' => 'Chicago',
            'miami' => 'Miami',
            'san-francisco' => 'San Francisco',
            'austin' => 'Austin',
            'dallas' => 'Dallas',
            'seattle' => 'Seattle',
            'boston' => 'Boston',
        );
    }

    /**
     * Get property countries for select field.
     *
     * @since 2.2.4
     * @return array
     */
    private function get_property_countries(): array {
        return array(
            'US' => 'United States',
            'GB' => 'United Kingdom',
            'CA' => 'Canada',
            'AU' => 'Australia',
            'DE' => 'Germany',
            'FR' => 'France',
            'ES' => 'Spain',
            'IT' => 'Italy',
        );
    }

    /**
     * Reorder sections properly.
     *
     * @since 2.2.4
     * @param array $sections Sections to reorder.
     * @return array Reordered sections.
     */
    private function reorder_sections( array $sections ): array {
        $order = array();

        // Find sections by title, not by ID
        $titles_order = array(
            'Basic Info' => 0,
            'Additional Information' => 1,
            'Address & Neighborhood' => 2,
            'Property Video' => 3,
            'Property Gallery' => 4,
            'Property Location' => 5,
            'Property Documents' => 6,
        );

        foreach ($sections as $key => &$section) {
            $title = $section['title'] ?? '';
            if (isset($titles_order[ $title ])) {
                $section['order'] = $titles_order[ $title ];
            } else {
                $section['order'] = $section['order'] ?? 999;
            }
        }

        // Sort by order
        uasort($sections, function ( $a, $b ) {
            return ( $a['order'] ?? 999 ) - ( $b['order'] ?? 999 );
        });

        return $sections;
    }

    /**
     * Rollback the migration.
     *
     * @since 2.2.4
     * @return bool
     */
    public function down(): bool {
        $this->log('Rolling back migration to version ' . $this->get_version());

        // Find most recent backup
        $backup_id = $this->find_latest_backup();

        if ($backup_id) {
            $restored = $this->restore_from_backup($backup_id);
            if ($restored) {
                $this->log('Successfully rolled back from backup: ' . $backup_id);
                return true;
            }
        }

        $this->log('No backup found for rollback');
        return false;
    }

    /**
     * Find the latest backup for this migration version.
     *
     * @since 2.2.4
     * @return string|false
     */
    private function find_latest_backup() {
        global $wpdb;

        $backup_pattern = '_hvnly_backup_2.2.4_%';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Migration backup option lookup.
        $backups = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT option_name FROM {$wpdb->options} 
                 WHERE option_name LIKE %s 
                 ORDER BY option_id DESC LIMIT 1",
                $backup_pattern
            )
        );

        return ! empty($backups) ? $backups[0] : false;
    }
}
