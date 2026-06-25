<?php

namespace HvnlyNab\Admin\Data;

use HvnlyNab\Core\SectionIdentity;

if (! defined('ABSPATH')) {
    exit; // Exit if accessed directly
}
/**
 * TabData handlers class
 */
class TabData
{

    /**
     * Generate a fallback section ID for TabData-only defaults (never rewrites stored builder tabs).
     *
     * @param string $canonical_id Stable canonical section ID when known.
     * @return string
     */
    private static function fallback_section_id( string $canonical_id = '' ): string {
        if ( '' !== $canonical_id ) {
            return $canonical_id;
        }

        return SectionIdentity::generate_custom_section_id();
    }


    /**
     * Define the tab structure
     */
    public static function hvnly_metabox_tabs_builder()
    {
        return array(
            array(
                'hvnly__sectiontitle' => __('Basic Info', 'havenlytics'),
                'id'           => self::fallback_section_id( SectionIdentity::SEC_PROPERTY_OVERVIEW ),
                'icon'         => 'fas fa-home',
                'fields'       => array(
                    array(
                        'fieldid'     => '_hvnly_property_price',
                        'input_type'  => 'price_label',
                        'label'       => __('Property Price', 'havenlytics'),
                        'name'        => '_hvnly_property_price',
                        'placeholder' => __('Enter property price', 'havenlytics'),
                        'is_required' => true,
                    ),
                    array(
                        'fieldid'     => '_hvnly_property_reception_rooms',
                        'input_type'  => 'number',
                        'label'       => __('Property Reception Rooms', 'havenlytics'),
                        'name'        => '_hvnly_property_reception_rooms',
                        'placeholder' => __('Enter reception rooms', 'havenlytics'),
                        'is_required' => true,
                    ),
                    array(
                        'fieldid'     => '_hvnly_property_bedrooms',
                        'input_type'  => 'number',
                        'label'       => __('Property Bedrooms', 'havenlytics'),
                        'name'        => '_hvnly_property_bedrooms',
                        'placeholder' => __('Enter property bedrooms', 'havenlytics'),
                        'is_required' => true,
                    ),
                    array(
                        'fieldid'     => '_hvnly_property_bathrooms',
                        'input_type'  => 'number',
                        'label'       => __('Property Bathrooms', 'havenlytics'),
                        'name'        => '_hvnly_property_bathrooms',
                        'placeholder' => __('Enter property bathrooms', 'havenlytics'),
                        'is_required' => true,
                    ),
                    array(
                        'fieldid'     => '_hvnly_property_half_bathrooms',
                        'input_type'  => 'number',
                        'label'       => __('Property Half Baths', 'havenlytics'),
                        'name'        => '_hvnly_property_half_bathrooms',
                        'placeholder' => __('Enter property half baths', 'havenlytics'),
                        'is_required' => true,
                    ),
                    array(
                        'fieldid'     => '_hvnly_property_kitchens',
                        'input_type'  => 'number',
                        'label'       => __('Property Kitchen', 'havenlytics'),
                        'name'        => '_hvnly_property_kitchens',
                        'placeholder' => __('Enter property kitchen', 'havenlytics'),
                    ),
                    array(
                        'fieldid'     => '_hvnly_property_total_rooms',
                        'input_type'  => 'number',
                        'label'       => __('Property Total Rooms', 'havenlytics'),
                        'name'        => '_hvnly_property_total_rooms',
                        'placeholder' => __('Enter property total rooms', 'havenlytics'),
                        'is_required' => true,
                    ),
                    array(
                        'fieldid'     => '_hvnly_property_floors',
                        'input_type'  => 'number',
                        'label'       => __('Property Floors', 'havenlytics'),
                        'name'        => '_hvnly_property_floors',
                        'placeholder' => __('Enter property floors', 'havenlytics'),

                    ),
                    array(
                        'fieldid'     => '_hvnly_property_year_built',
                        'input_type'  => 'number',
                        'label'       => __('Property Year Built', 'havenlytics'),
                        'name'        => '_hvnly_property_year_built',
                        'placeholder' => __('Enter property year built', 'havenlytics'),
                    ),
                    array(
                        'fieldid'     => '_hvnly_property_mls_number',
                        'input_type'  => 'text',
                        'label'       => __('Property MLS Number', 'havenlytics'),
                        'name'        => '_hvnly_property_mls_number',
                        'placeholder' => __('Enter property MLS number', 'havenlytics'),
                    ),

                    array(
                        'fieldid'     => '_hvnly_property_garage_sqft',
                        'input_type'  => 'number',
                        'label'       => __('Property Garage Square Footage', 'havenlytics'),
                        'name'        => '_hvnly_property_garage_sqft',
                        'placeholder' => __('Enter property garage square footage', 'havenlytics'),
                        'is_required' => true,
                    ),



                ),
            ),
            array(
                'hvnly__sectiontitle' => __('Additional Information', 'havenlytics'),
                'id'           => self::fallback_section_id( SectionIdentity::SEC_PROPERTY_DETAILS ),
                'icon'         => 'fas fa-info-circle',
                'fields'       => array(
                    array(
                        'fieldid'     => '_hvnly_property_sqft',
                        'input_type'  => 'number',
                        'label'       => __('Property Area, sq ft', 'havenlytics'),
                        'name'        => '_hvnly_property_sqft',
                        'placeholder' => __('Enter property area in square feet', 'havenlytics'),
                        'is_required' => true,
                    ),
                    array(
                        'fieldid'     => '_hvnly_property_lot_size',
                        'input_type'  => 'text',
                        'label'       => __('Property Lot size, sq ft', 'havenlytics'),
                        'name'        => '_hvnly_property_lot_size',
                        'placeholder' => __('Enter property lot size', 'havenlytics'),
                        'is_required' => true,
                    ),
                    array(
                        'fieldid'     => '_hvnly_property_hoa_fee',
                        'input_type'  => 'text',
                        'label'       => __('Property HOA Fee', 'havenlytics'),
                        'name'        => '_hvnly_property_hoa_fee',
                        'placeholder' => __('Enter property HOA fee', 'havenlytics'),
                    ),
                    array(
                        'fieldid'     => '_hvnly_property_annual_tax_amount',
                        'input_type'  => 'number',
                        'label'       => __('Property Annual Tax Amount', 'havenlytics'),
                        'name'        => '_hvnly_property_annual_tax_amount',
                        'placeholder' => __('Enter property annual tax amount', 'havenlytics'),
                    ),
                    array(
                        'fieldid'     => '_hvnly_property_heating',
                        'input_type'  => 'select',
                        'label'       => __('Heating', 'havenlytics'),
                        'name'        => '_hvnly_property_heating',
                        'options'     => array(
                            'forced_air' => __('Forced Air', 'havenlytics'),
                            'radiator' => __('Radiator', 'havenlytics'),
                            'heat_pump' => __('Heat Pump', 'havenlytics'),
                            'baseboard' => __('Baseboard', 'havenlytics'),
                            'none' => __('None', 'havenlytics'),
                        ),
                    ),
                    array(
                        'fieldid'     => '_hvnly_property_cooling',
                        'input_type'  => 'select',
                        'label'       => __('Cooling', 'havenlytics'),
                        'name'        => '_hvnly_property_cooling',
                        'options'     => array(
                            'central' => __('Central Air', 'havenlytics'),
                            'window' => __('Window Units', 'havenlytics'),
                            'heat_pump' => __('Heat Pump', 'havenlytics'),
                            'baseboard' => __('Baseboard', 'havenlytics'),
                            'none' => __('None', 'havenlytics'),
                        ),
                    ),
                    array(
                        'fieldid'     => '_hvnly_property_water',
                        'input_type'  => 'select',
                        'label'       => __('Water Source', 'havenlytics'),
                        'name'        => '_hvnly_property_water',
                        'options'     => array(
                            'city' => __('City', 'havenlytics'),
                            'well' => __('Well', 'havenlytics'),
                            'shared_well' => __('Shared Well', 'havenlytics'),
                            'none' => __('None', 'havenlytics'),
                        ),
                    ),


                ),
            ),
            array(
                'hvnly__sectiontitle' => __('Address & Neighborhood', 'havenlytics'),
                'id'           => self::fallback_section_id( SectionIdentity::SEC_ADDRESS_NEIGHBORHOOD ),
                'icon'         => 'fas fa-building',
                'fields'       => array(
                    array(
                        'fieldid'     => '_hvnly_property_reference_number',
                        'input_type'  => 'text',
                        'label'       => __('Property Reference Number', 'havenlytics'),
                        'name'        => '_hvnly_property_reference_number',
                        'placeholder' => __('Enter property reference number', 'havenlytics'),
                    ),
                    array(
                        'fieldid'     => '_hvnly_property_building_number',
                        'input_type'  => 'text',
                        'label'       => __('Property Building Number', 'havenlytics'),
                        'name'        => '_hvnly_property_building_number',
                        'placeholder' => __('Enter property building number', 'havenlytics'),
                    ),
                    array(
                        'fieldid'     => '_hvnly_property_street',
                        'input_type'  => 'text',
                        'label'       => __('Property Street', 'havenlytics'),
                        'name'        => '_hvnly_property_street',
                        'placeholder' => __('Enter property street', 'havenlytics'),
                    ),
                    array(
                        'fieldid'     => '_hvnly_property_address_line_1',
                        'input_type'  => 'text',
                        'label'       => __('Property Address Line 1', 'havenlytics'),
                        'name'        => '_hvnly_property_address_line_1',
                        'placeholder' => __('Enter property address line 1', 'havenlytics'),
                    ),
                    array(
                        'fieldid'     => '_hvnly_property_address_line_2',
                        'input_type'  => 'text',
                        'label'       => __('Property Address Line 2', 'havenlytics'),
                        'name'        => '_hvnly_property_address_line_2',
                        'placeholder' => __('Enter property address line 2', 'havenlytics'),
                    ),
                    array(
                        'fieldid'     => '_hvnly_property_town_city',
                        'input_type'  => 'text',
                        'label'       => __('Property Town/City', 'havenlytics'),
                        'name'        => '_hvnly_property_town_city',
                        'placeholder' => __('Enter property town/city', 'havenlytics'),
                    ),
                    array(
                        'fieldid'     => '_hvnly_property_country_state',
                        'input_type'  => 'text',
                        'label'       => __('Property Country/State', 'havenlytics'),
                        'name'        => '_hvnly_property_country_state',
                        'placeholder' => __('Enter property country/state', 'havenlytics'),
                    ),
                    array(
                        'fieldid'     => '_hvnly_property_zip_code',
                        'input_type'  => 'text',
                        'label'       => __('Property Zip Code', 'havenlytics'),
                        'name'        => '_hvnly_property_zip_code',
                        'placeholder' => __('Enter property zip code', 'havenlytics'),
                    ),
                    array(
                        'fieldid'     => '_hvnly_property_location',
                        'input_type'  => 'select',
                        'label'       => __('Property Location', 'havenlytics'),
                        'name'        => '_hvnly_property_location',
                        'options'     => self::hvnly_get_property_locations(),
                    ),
                    array(
                        'fieldid'     => '_hvnly_property_country_location',
                        'input_type'  => 'select',
                        'label'       => __('Property Country', 'havenlytics'),
                        'name'        => '_hvnly_property_country_location',
                        'options'     => self::hvnly_get_property_countries(),
                    ),
                ),
            ),

            array(
                'hvnly__sectiontitle' => __('Property Video', 'havenlytics'),
                'id'           => self::fallback_section_id( SectionIdentity::SEC_PROPERTY_VIDEO ),
                'icon'         => 'fas fa-video',
                'fields'       => array(
                    // Video Type Selector
                    // array(
                    //     'fieldid'    => '_hvnly_property_video_type',
                    //     'input_type' => 'select',
                    //     'label'      => __('Select Video Type', 'havenlytics'),
                    //     'name'       => '_hvnly_property_video_type',
                    //     'options'    => array(
                    //         'proprety_youtube' => __('YouTube', 'havenlytics'),
                    //         'proprety_vimeo'   => __('Vimeo', 'havenlytics'),

                    //     ),
                    // ),

                    // YouTube Fields
                    array(
                        'fieldid'     => '_hvnly_property_youtube_video_title',
                        'input_type'  => 'text',
                        'label'       => __('YouTube Video Title', 'havenlytics'),
                        'name'        => '_hvnly_property_youtube_video_title',
                        'placeholder' => __('Video Title', 'havenlytics'),
                        'condition'   => array(
                            'field' => '_hvnly_property_video_type',
                            'value' => 'proprety_youtube',
                        ),
                    ),
                    array(
                        'fieldid'     => '_hvnly_property_youtube_video_url',
                        'input_type'  => 'text',
                        'label'       => __('YouTube Video URL', 'havenlytics'),
                        'name'        => '_hvnly_property_youtube_video_url',
                        'placeholder' => __('Enter full YouTube URL or ID', 'havenlytics'),
                        'condition'   => array(
                            'field' => '_hvnly_property_video_type',
                            'value' => 'proprety_youtube',
                        ),
                    ),
                    array(
                        'fieldid'     => '_hvnly_property_youtube_video_thumbnail',
                        'input_type'  => 'file',
                        'label'       => __('YouTube Video Thumbnail', 'havenlytics'),
                        'name'        => '_hvnly_property_youtube_video_thumbnail',
                        'placeholder' => __('Upload full YouTube thumbnail Image', 'havenlytics'),
                        'file_type'   => 'image',
                        'condition'   => array(
                            'field' => '_hvnly_property_video_type',
                            'value' => 'proprety_youtube',
                        ),
                    ),


                    // Vimeo Fields
                    // array(
                    //     'fieldid'     => '_hvnly_property_vimeo_video_url',
                    //     'input_type'  => 'text',
                    //     'label'       => __('Vimeo Video URL', 'havenlytics'),
                    //     'name'        => '_hvnly_property_vimeo_video_url',
                    //     'placeholder' => __('Enter full Vimeo URL or ID', 'havenlytics'),
                    //     'condition'   => array(
                    //         'field' => '_hvnly_property_video_type',
                    //         'value' => 'proprety_vimeo',
                    //     ),
                    // ),
                    // array(
                    //     'fieldid'     => '_hvnly_property_vimeo_video_thumbnail',
                    //     'input_type'  => 'file',
                    //     'label'       => __('Vimeo Video Thumbnail', 'havenlytics'),
                    //     'name'        => '_hvnly_property_vimeo_video_thumbnail',
                    //     'placeholder' => __('Upload full Vimeo thumbnail Image', 'havenlytics'),
                    //     'file_type'   => 'image',
                    //     'condition'   => array(
                    //         'field' => '_hvnly_property_video_type',
                    //         'value' => 'proprety_vimeo',
                    //     ),
                    // ),



                ),
            ),


            array(
                'hvnly__sectiontitle' => __('Property Gallery', 'havenlytics'),
                'id'           => self::fallback_section_id( SectionIdentity::SEC_PROPERTY_GALLERY ),
                'icon'         => 'fas fa-images',
                'fields'       => array(
                    array(
                        'fieldid'     => '_hvnly_property_gallery_title',
                        'input_type'  => 'text',
                        'label'       => __('Property Gallery Title', 'havenlytics'),
                        'name'        => '_hvnly_property_gallery_title',
                        'placeholder' => __('Enter property gallery title', 'havenlytics'),
                    ),
                    array(
                        'fieldid'     => '_hvnly_property_gallery_images',
                        'input_type'  => 'gallery',
                        'label'       => __('Property Gallery Images', 'havenlytics'),
                        'name'        => '_hvnly_property_gallery_images',
                        // 'description'        => __('Add images to your property gallery. Drag to reorder.'),
                        // 'description'        => __('Add images to your property gallery. Drag to reorder.'),
                        'wrapper_id'  => 'hvnly_gallery_' . uniqid(), // Unique wrapper
                    ),


                ),
            ),
            array(
                'hvnly__sectiontitle' => __('Property Location', 'havenlytics'),
                'id'           => self::fallback_section_id( SectionIdentity::SEC_PROPERTY_LOCATION ),
                'icon'         => 'fas fa-map-marker-alt',
                'fields'       => array(
                    array(
                        'fieldid'     => '_hvnly_property_map_location_address',
                        'input_type'  => 'text',
                        'label'       => __('Property Map Address', 'havenlytics'),
                        'name'        => '_hvnly_property_map_location_address',
                        'placeholder' => __('Enter property map Address', 'havenlytics'),
                        'autocomplete'     => '_hvnly_property_address-suggestions',
                        'is_required' => true,
                    ),
                    array(
                        'fieldid'     => '_hvnly_property_location_Latitude',
                        'input_type'  => 'text',
                        'label'       => __('Property Latitude', 'havenlytics'),
                        'name'        => '_hvnly_property_location_Latitude',
                        'placeholder' => __('Enter property latitude', 'havenlytics'),
                        'userguide' => __('Get the property latitude by clicking on Google Maps or visiting https://www.maps.ie/coordinates.html', 'havenlytics'),

                    ),
                    array(
                        'fieldid'     => '_hvnly_property_location_Longitude',
                        'input_type'  => 'text',
                        'label'       => __('Property Longitude', 'havenlytics'),
                        'name'        => '_hvnly_property_location_Longitude',
                        'placeholder' => __('Enter property longitude', 'havenlytics'),
                        'userguide' => __('Get the property longitude by clicking on Google Maps or visiting https://www.maps.ie/coordinates.html', 'havenlytics'),

                    ),

                    array(
                        'fieldid'     => '_hvnly_property_location_leaflet_map',
                        'input_type'  => 'map', // custom type for live Leaflet map
                        'label'       => __('Property Location Map', 'havenlytics'),
                        'name'        => '_hvnly_property_location_leaflet_map',
                    ),



                ),
            ),



        );
    }

    // Function to fetch taxonomy terms
    public static function hvnly_get_property_locations()
    {
        $locations = array();

        $terms = get_terms(array(
            'taxonomy'   => 'hvnly_prop_location',
            'hide_empty' => false,
        ));

        if (!is_wp_error($terms) && !empty($terms)) {
            foreach ($terms as $term) {
                $locations[$term->slug] = $term->name;
            }
        }

        // Fallback dummy locations if no taxonomy terms exist
        if (empty($locations)) {
            $locations = array(
                'new-york'      => __('New York', 'havenlytics'),
                'los-angeles'   => __('Los Angeles', 'havenlytics'),
                'chicago'       => __('Chicago', 'havenlytics'),
                'miami'         => __('Miami', 'havenlytics'),
                'san-francisco' => __('San Francisco', 'havenlytics'),
            );
        }

        return $locations;
    }


    public static function hvnly_get_property_countries()
    {
        return array(
            'AF' => __('Afghanistan', 'havenlytics'),
            'AL' => __('Albania', 'havenlytics'),
            'DZ' => __('Algeria', 'havenlytics'),
            'AS' => __('American Samoa', 'havenlytics'),
            'AD' => __('Andorra', 'havenlytics'),
            'AO' => __('Angola', 'havenlytics'),
            'AI' => __('Anguilla', 'havenlytics'),
            'AQ' => __('Antarctica', 'havenlytics'),
            'AG' => __('Antigua and Barbuda', 'havenlytics'),
            'AR' => __('Argentina', 'havenlytics'),
            'AM' => __('Armenia', 'havenlytics'),
            'AW' => __('Aruba', 'havenlytics'),
            'AU' => __('Australia', 'havenlytics'),
            'AT' => __('Austria', 'havenlytics'),
            'AZ' => __('Azerbaijan', 'havenlytics'),
            'BS' => __('Bahamas', 'havenlytics'),
            'BH' => __('Bahrain', 'havenlytics'),
            'BD' => __('Bangladesh', 'havenlytics'),
            'BB' => __('Barbados', 'havenlytics'),
            'BY' => __('Belarus', 'havenlytics'),
            'BE' => __('Belgium', 'havenlytics'),
            'BZ' => __('Belize', 'havenlytics'),
            'BJ' => __('Benin', 'havenlytics'),
            'BM' => __('Bermuda', 'havenlytics'),
            'BT' => __('Bhutan', 'havenlytics'),
            'BO' => __('Bolivia', 'havenlytics'),
            'BA' => __('Bosnia and Herzegovina', 'havenlytics'),
            'BW' => __('Botswana', 'havenlytics'),
            'BR' => __('Brazil', 'havenlytics'),
            'BN' => __('Brunei Darussalam', 'havenlytics'),
            'BG' => __('Bulgaria', 'havenlytics'),
            'BF' => __('Burkina Faso', 'havenlytics'),
            'BI' => __('Burundi', 'havenlytics'),
            'KH' => __('Cambodia', 'havenlytics'),
            'CM' => __('Cameroon', 'havenlytics'),
            'CA' => __('Canada', 'havenlytics'),
            'CV' => __('Cape Verde', 'havenlytics'),
            'KY' => __('Cayman Islands', 'havenlytics'),
            'CF' => __('Central African Republic', 'havenlytics'),
            'TD' => __('Chad', 'havenlytics'),
            'CL' => __('Chile', 'havenlytics'),
            'CN' => __('China', 'havenlytics'),
            'CO' => __('Colombia', 'havenlytics'),
            'KM' => __('Comoros', 'havenlytics'),
            'CG' => __('Congo', 'havenlytics'),
            'CD' => __('Congo, Democratic Republic of the', 'havenlytics'),
            'CR' => __('Costa Rica', 'havenlytics'),
            'HR' => __('Croatia', 'havenlytics'),
            'CU' => __('Cuba', 'havenlytics'),
            'CY' => __('Cyprus', 'havenlytics'),
            'CZ' => __('Czech Republic', 'havenlytics'),
            'DK' => __('Denmark', 'havenlytics'),
            'DJ' => __('Djibouti', 'havenlytics'),
            'DM' => __('Dominica', 'havenlytics'),
            'DO' => __('Dominican Republic', 'havenlytics'),
            'EC' => __('Ecuador', 'havenlytics'),
            'EG' => __('Egypt', 'havenlytics'),
            'SV' => __('El Salvador', 'havenlytics'),
            'GQ' => __('Equatorial Guinea', 'havenlytics'),
            'ER' => __('Eritrea', 'havenlytics'),
            'EE' => __('Estonia', 'havenlytics'),
            'ET' => __('Ethiopia', 'havenlytics'),
            'FJ' => __('Fiji', 'havenlytics'),
            'FI' => __('Finland', 'havenlytics'),
            'FR' => __('France', 'havenlytics'),
            'GA' => __('Gabon', 'havenlytics'),
            'GM' => __('Gambia', 'havenlytics'),
            'GE' => __('Georgia', 'havenlytics'),
            'DE' => __('Germany', 'havenlytics'),
            'GH' => __('Ghana', 'havenlytics'),
            'GR' => __('Greece', 'havenlytics'),
            'GD' => __('Grenada', 'havenlytics'),
            'GU' => __('Guam', 'havenlytics'),
            'GT' => __('Guatemala', 'havenlytics'),
            'GN' => __('Guinea', 'havenlytics'),
            'GW' => __('Guinea-Bissau', 'havenlytics'),
            'GY' => __('Guyana', 'havenlytics'),
            'HT' => __('Haiti', 'havenlytics'),
            'HN' => __('Honduras', 'havenlytics'),
            'HK' => __('Hong Kong', 'havenlytics'),
            'HU' => __('Hungary', 'havenlytics'),
            'IS' => __('Iceland', 'havenlytics'),
            'IN' => __('India', 'havenlytics'),
            'ID' => __('Indonesia', 'havenlytics'),
            'IR' => __('Iran', 'havenlytics'),
            'IQ' => __('Iraq', 'havenlytics'),
            'IE' => __('Ireland', 'havenlytics'),
            'IL' => __('Israel', 'havenlytics'),
            'IT' => __('Italy', 'havenlytics'),
            'JM' => __('Jamaica', 'havenlytics'),
            'JP' => __('Japan', 'havenlytics'),
            'JO' => __('Jordan', 'havenlytics'),
            'KZ' => __('Kazakhstan', 'havenlytics'),
            'KE' => __('Kenya', 'havenlytics'),
            'KI' => __('Kiribati', 'havenlytics'),
            'KR' => __('Korea, Republic of', 'havenlytics'),
            'KW' => __('Kuwait', 'havenlytics'),
            'KG' => __('Kyrgyzstan', 'havenlytics'),
            'LA' => __('Lao People\'s Democratic Republic', 'havenlytics'),
            'LV' => __('Latvia', 'havenlytics'),
            'LB' => __('Lebanon', 'havenlytics'),
            'LS' => __('Lesotho', 'havenlytics'),
            'LR' => __('Liberia', 'havenlytics'),
            'LY' => __('Libya', 'havenlytics'),
            'LI' => __('Liechtenstein', 'havenlytics'),
            'LT' => __('Lithuania', 'havenlytics'),
            'LU' => __('Luxembourg', 'havenlytics'),
            'MO' => __('Macao', 'havenlytics'),
            'MK' => __('Macedonia', 'havenlytics'),
            'MG' => __('Madagascar', 'havenlytics'),
            'MW' => __('Malawi', 'havenlytics'),
            'MY' => __('Malaysia', 'havenlytics'),
            'MV' => __('Maldives', 'havenlytics'),
            'ML' => __('Mali', 'havenlytics'),
            'MT' => __('Malta', 'havenlytics'),
            'MH' => __('Marshall Islands', 'havenlytics'),
            'MQ' => __('Martinique', 'havenlytics'),
            'MR' => __('Mauritania', 'havenlytics'),
            'MU' => __('Mauritius', 'havenlytics'),
            'MX' => __('Mexico', 'havenlytics'),
            'FM' => __('Micronesia', 'havenlytics'),
            'MD' => __('Moldova', 'havenlytics'),
            'MC' => __('Monaco', 'havenlytics'),
            'MN' => __('Mongolia', 'havenlytics'),
            'ME' => __('Montenegro', 'havenlytics'),
            'MA' => __('Morocco', 'havenlytics'),
            'MZ' => __('Mozambique', 'havenlytics'),
            'MM' => __('Myanmar', 'havenlytics'),
            'NA' => __('Namibia', 'havenlytics'),
            'NR' => __('Nauru', 'havenlytics'),
            'NP' => __('Nepal', 'havenlytics'),
            'NL' => __('Netherlands', 'havenlytics'),
            'NZ' => __('New Zealand', 'havenlytics'),
            'NI' => __('Nicaragua', 'havenlytics'),
            'NE' => __('Niger', 'havenlytics'),
            'NG' => __('Nigeria', 'havenlytics'),
            'NO' => __('Norway', 'havenlytics'),
            'OM' => __('Oman', 'havenlytics'),
            'PK' => __('Pakistan', 'havenlytics'),
            'PW' => __('Palau', 'havenlytics'),
            'PS' => __('Palestine', 'havenlytics'),
            'PA' => __('Panama', 'havenlytics'),
            'PG' => __('Papua New Guinea', 'havenlytics'),
            'PY' => __('Paraguay', 'havenlytics'),
            'PE' => __('Peru', 'havenlytics'),
            'PH' => __('Philippines', 'havenlytics'),
            'PL' => __('Poland', 'havenlytics'),
            'PT' => __('Portugal', 'havenlytics'),
            'QA' => __('Qatar', 'havenlytics'),
            'RO' => __('Romania', 'havenlytics'),
            'RU' => __('Russian Federation', 'havenlytics'),
            'RW' => __('Rwanda', 'havenlytics'),
            'KN' => __('Saint Kitts and Nevis', 'havenlytics'),
            'LC' => __('Saint Lucia', 'havenlytics'),
            'VC' => __('Saint Vincent and the Grenadines', 'havenlytics'),
            'WS' => __('Samoa', 'havenlytics'),
            'SM' => __('San Marino', 'havenlytics'),
            'ST' => __('Sao Tome and Principe', 'havenlytics'),
            'SA' => __('Saudi Arabia', 'havenlytics'),
            'SN' => __('Senegal', 'havenlytics'),
            'RS' => __('Serbia', 'havenlytics'),
            'SC' => __('Seychelles', 'havenlytics'),
            'SL' => __('Sierra Leone', 'havenlytics'),
            'SG' => __('Singapore', 'havenlytics'),
            'SK' => __('Slovakia', 'havenlytics'),
            'SI' => __('Slovenia', 'havenlytics'),
            'SB' => __('Solomon Islands', 'havenlytics'),
            'SO' => __('Somalia', 'havenlytics'),
            'ZA' => __('South Africa', 'havenlytics'),
            'ES' => __('Spain', 'havenlytics'),
            'LK' => __('Sri Lanka', 'havenlytics'),
            'SD' => __('Sudan', 'havenlytics'),
            'SR' => __('Suriname', 'havenlytics'),
            'SZ' => __('Swaziland', 'havenlytics'),
            'SE' => __('Sweden', 'havenlytics'),
            'CH' => __('Switzerland', 'havenlytics'),
            'SY' => __('Syrian Arab Republic', 'havenlytics'),
            'TW' => __('Taiwan', 'havenlytics'),
            'TJ' => __('Tajikistan', 'havenlytics'),
            'TZ' => __('Tanzania', 'havenlytics'),
            'TH' => __('Thailand', 'havenlytics'),
            'TL' => __('Timor-Leste', 'havenlytics'),
            'TG' => __('Togo', 'havenlytics'),
            'TO' => __('Tonga', 'havenlytics'),
            'TT' => __('Trinidad and Tobago', 'havenlytics'),
            'TN' => __('Tunisia', 'havenlytics'),
            'TR' => __('Turkey', 'havenlytics'),
            'TM' => __('Turkmenistan', 'havenlytics'),
            'TV' => __('Tuvalu', 'havenlytics'),
            'UG' => __('Uganda', 'havenlytics'),
            'UA' => __('Ukraine', 'havenlytics'),
            'AE' => __('United Arab Emirates', 'havenlytics'),
            'GB' => __('United Kingdom', 'havenlytics'),
            'US' => __('United States', 'havenlytics'),
            'UY' => __('Uruguay', 'havenlytics'),
            'UZ' => __('Uzbekistan', 'havenlytics'),
            'VU' => __('Vanuatu', 'havenlytics'),
            'VE' => __('Venezuela', 'havenlytics'),
            'VN' => __('Viet Nam', 'havenlytics'),
            'YE' => __('Yemen', 'havenlytics'),
            'ZM' => __('Zambia', 'havenlytics'),
            'ZW' => __('Zimbabwe', 'havenlytics'),
        );
    }

    public static function hvnly_get_property_country_currency()
    {
        return array(
            "AED" => "United Arab Emirates dirham (د.إ)",
            "AFN" => "Afghan afghani (؋)",
            "ALL" => "Albanian lek (L)",
            "AMD" => "Armenian dram (AMD)",
            "ANG" => "Netherlands Antillean guilder (ƒ)",
            "AOA" => "Angolan kwanza (Kz)",
            "ARS" => "Argentine peso ($)",
            "AUD" => "Australian dollar ($)",
            "AWG" => "Aruban florin (Afl.)",
            "AZN" => "Azerbaijani manat (AZN)",
            "BAM" => "Bosnia and Herzegovina convertible mark (KM)",
            "BBD" => "Barbadian dollar ($)",
            "BDT" => "Bangladeshi taka (৳)",
            "BGN" => "Bulgarian lev (лв.)",
            "BHD" => "Bahraini dinar (.د.ب)",
            "BIF" => "Burundian franc (Fr)",
            "BMD" => "Bermudian dollar ($)",
            "BND" => "Brunei dollar ($)",
            "BOB" => "Bolivian boliviano (Bs.)",
            "BRL" => "Brazilian real (R$)",
            "BSD" => "Bahamian dollar ($)",
            "BTC" => "Bitcoin (฿)",
            "BTN" => "Bhutanese ngultrum (Nu.)",
            "BWP" => "Botswana pula (P)",
            "BYR" => "Belarusian ruble (old) (Br)",
            "BYN" => "Belarusian ruble (Br)",
            "BZD" => "Belize dollar ($)",
            "CAD" => "Canadian dollar ($)",
            "CDF" => "Congolese franc (Fr)",
            "CHF" => "Swiss franc (CHF)",
            "CLP" => "Chilean peso ($)",
            "CNY" => "Chinese yuan (¥)",
            "COP" => "Colombian peso ($)",
            "CRC" => "Costa Rican colón (₡)",
            "CUC" => "Cuban convertible peso ($)",
            "CUP" => "Cuban peso ($)",
            "CVE" => "Cape Verdean escudo ($)",
            "CZK" => "Czech koruna (Kč)",
            "DJF" => "Djiboutian franc (Fr)",
            "DKK" => "Danish krone (DKK)",
            "DOP" => "Dominican peso (RD$)",
            "DZD" => "Algerian dinar (د.ج)",
            "EGP" => "Egyptian pound (EGP)",
            "ERN" => "Eritrean nakfa (Nfk)",
            "ETB" => "Ethiopian birr (Br)",
            "EUR" => "Euro (€)",
            "FJD" => "Fijian dollar ($)",
            "FKP" => "Falkland Islands pound (£)",
            "GBP" => "Pound sterling (£)",
            "GEL" => "Georgian lari (ლ)",
            "GGP" => "Guernsey pound (£)",
            "GHS" => "Ghana cedi (₵)",
            "GIP" => "Gibraltar pound (£)",
            "GMD" => "Gambian dalasi (D)",
            "GNF" => "Guinean franc (Fr)",
            "GTQ" => "Guatemalan quetzal (Q)",
            "GYD" => "Guyanese dollar ($)",
            "HKD" => "Hong Kong dollar (HK$)",
            "HNL" => "Honduran lempira (L)",
            "HRK" => "Croatian kuna (Kn)",
            "HTG" => "Haitian gourde (G)",
            "HUF" => "Hungarian forint (Ft)",
            "IDR" => "Indonesian rupiah (Rp)",
            "ILS" => "Israeli new shekel (₪)",
            "IMP" => "Manx pound (£)",
            "INR" => "Indian rupee (₹)",
            "IQD" => "Iraqi dinar (ع.د)",
            "IRR" => "Iranian rial (﷼)",
            "IRT" => "Iranian toman (تومان)",
            "ISK" => "Icelandic króna (kr.)",
            "JEP" => "Jersey pound (£)",
            "JMD" => "Jamaican dollar ($)",
            "JOD" => "Jordanian dinar (د.ا)",
            "JPY" => "Japanese yen (¥)",
            "KES" => "Kenyan shilling (KSh)",
            "KGS" => "Kyrgyzstani som (сом)",
            "KHR" => "Cambodian riel (៛)",
            "KMF" => "Comorian franc (Fr)",
            "KPW" => "North Korean won (₩)",
            "KRW" => "South Korean won (₩)",
            "KWD" => "Kuwaiti dinar (د.ك)",
            "KYD" => "Cayman Islands dollar ($)",
            "KZT" => "Kazakhstani tenge (KZT)",
            "LAK" => "Lao kip (₭)",
            "LBP" => "Lebanese pound (ل.ل)",
            "LKR" => "Sri Lankan rupee (රු)",
            "LRD" => "Liberian dollar ($)",
            "LSL" => "Lesotho loti (L)",
            "LYD" => "Libyan dinar (ل.د)",
            "MAD" => "Moroccan dirham (د.م.)",
            "MDL" => "Moldovan leu (MDL)",
            "MGA" => "Malagasy ariary (Ar)",
            "MKD" => "Macedonian denar (ден)",
            "MMK" => "Burmese kyat (Ks)",
            "MNT" => "Mongolian tögrög (₮)",
            "MOP" => "Macanese pataca (MOP$)",
            "MRO" => "Mauritanian ouguiya (UM)",
            "MUR" => "Mauritian rupee (₨)",
            "MVR" => "Maldivian rufiyaa (.ރ)",
            "MWK" => "Malawian kwacha (MK)",
            "MXN" => "Mexican peso ($)",
            "MYR" => "Malaysian ringgit (RM)",
            "MZN" => "Mozambican metical (MT)",
            "NAD" => "Namibian dollar (N$)",
            "NGN" => "Nigerian naira (₦)",
            "NIO" => "Nicaraguan córdoba (C$)",
            "NOK" => "Norwegian krone (kr)",
            "NPR" => "Nepalese rupee (₨)",
            "NZD" => "New Zealand dollar ($)",
            "OMR" => "Omani rial (ر.ع.)",
            "PAB" => "Panamanian balboa (B/.)",
            "PEN" => "Peruvian nuevo sol (S/.)",
            "PGK" => "Papua New Guinean kina (K)",
            "PHP" => "Philippine peso (₱)",
            "PKR" => "Pakistani rupee (₨)",
            "PLN" => "Polish złoty (zł)",
            "PRB" => "Transnistrian ruble (р.)",
            "PYG" => "Paraguayan guaraní (₲)",
            "QAR" => "Qatari riyal (ر.ق)",
            "RON" => "Romanian leu (lei)",
            "RSD" => "Serbian dinar (дин.)",
            "RUB" => "Russian ruble (₽)",
            "RWF" => "Rwandan franc (Fr)",
            "SAR" => "Saudi riyal (ر.س)",
            "SBD" => "Solomon Islands dollar ($)",
            "SCR" => "Seychellois rupee (₨)",
            "SDG" => "Sudanese pound (ج.س.)",
            "SEK" => "Swedish krona (kr)",
            "SGD" => "Singapore dollar ($)",
            "SHP" => "Saint Helena pound (£)",
            "SLL" => "Sierra Leonean leone (Le)",
            "SOS" => "Somali shilling (Sh)",
            "SRD" => "Surinamese dollar ($)",
            "SSP" => "South Sudanese pound (£)",
            "STD" => "São Tomé and Príncipe dobra (Db)",
            "SYP" => "Syrian pound (ل.س)",
            "SZL" => "Swazi lilangeni (L)",
            "THB" => "Thai baht (฿)",
            "TJS" => "Tajikistani somoni (ЅМ)",
            "TMT" => "Turkmenistan manat (m)",
            "TND" => "Tunisian dinar (د.ت)",
            "TOP" => "Tongan paʻanga (T$)",
            "TRY" => "Turkish lira (₺)",
            "TTD" => "Trinidad and Tobago dollar ($)",
            "TWD" => "New Taiwan dollar (NT$)",
            "TZS" => "Tanzanian shilling (Sh)",
            "UAH" => "Ukrainian hryvnia (₴)",
            "UGX" => "Ugandan shilling (UGX)",
            "USD" => "United States dollar ($)",
            "UYU" => "Uruguayan peso ($)",
            "UZS" => "Uzbekistani som (UZS)",
            "VEF" => "Venezuelan bolívar (Bs F)",
            "VND" => "Vietnamese đồng (₫)",
            "VUV" => "Vanuatu vatu (Vt)",
            "WST" => "Samoan tālā (T)",
            "XAF" => "Central African CFA franc (CFA)",
            "XCD" => "East Caribbean dollar ($)",
            "XOF" => "West African CFA franc (CFA)",
            "XPF" => "CFP franc (Fr)",
            "YER" => "Yemeni rial (﷼)",
            "ZAR" => "South African rand (R)",
            "ZMW" => "Zambian kwacha (ZK)"
        );
    }


    public static function get_cf7_forms()
    {
        $options = array(
            '' => __('Select a Contact Form…', 'havenlytics'),
        );

        if (post_type_exists('wpcf7_contact_form')) {
            $forms = get_posts(array(
                'post_type'      => 'wpcf7_contact_form',
                'posts_per_page' => -1,
            ));

            if ($forms) {
                foreach ($forms as $form) {
                    $shortcode = '[contact-form-7 id="' . esc_attr($form->ID) . '"]';
                    $options[$shortcode] = $form->post_title;
                }
            }
        }

        return $options;
    }
}