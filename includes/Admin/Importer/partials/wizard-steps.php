<?php
/**
 * Property Import Wizard – server-rendered step panels.
 *
 * @package Havenlytics
 */

if (!defined('ABSPATH')) {
    exit;
}

$hvnly_max_import       = 50;
$hvnly_default_quantity = 10;
$hvnly_google_api_key   = function_exists('hvnly_get_google_maps_api_key') ? hvnly_get_google_maps_api_key() : '';
$hvnly_map_provider     = function_exists('hvnly_get_map_provider') ? hvnly_get_map_provider() : 'leaflet';
$hvnly_allowed_providers = ['leaflet', 'openstreetmap', 'google'];
if (!in_array($hvnly_map_provider, $hvnly_allowed_providers, true)) {
    $hvnly_map_provider = 'leaflet';
}
$hvnly_demo_gallery     = [
    'https://demo.havenlytics.com/wp-content/uploads/2026/03/1.jpg',
    'https://demo.havenlytics.com/wp-content/uploads/2026/03/2.jpg',
    'https://demo.havenlytics.com/wp-content/uploads/2026/03/3.jpg',
];
if ( ! isset( $hvnly_initial_step ) ) {
    $hvnly_initial_step = 1;
}
$hvnly_initial_step = max( 1, min( 4, (int) $hvnly_initial_step ) );
?>
<div class="hvnly--property--import-step-panels" id="step-panels">
    <!-- Step 1 -->
    <div class="hvnly-step-panel hvnly--property--import-step-panel" data-step="1" id="step-panel-1"<?php echo 1 !== $hvnly_initial_step ? ' hidden' : ''; ?>>
        <h2 class="hvnly--property--import-card-title"><?php esc_html_e('Select Property Department', 'havenlytics'); ?></h2>
        <p class="hvnly--property--import-card-description"><?php esc_html_e('Choose a department for your properties. Taxonomies will be automatically configured.', 'havenlytics'); ?></p>
        <div class="hvnly--property--import-divider"></div>

        <div class="hvnly-wizard-accordion" data-wizard-step="1">
            <details class="hvnly-wizard-accordion__panel" open data-accordion-id="department-taxonomies">
                <summary class="hvnly-wizard-accordion__trigger">
                    <span class="hvnly-wizard-accordion__trigger-leading">
                        <span class="hvnly-wizard-accordion__icon" aria-hidden="true"><i class="fas fa-layer-group"></i></span>
                        <span class="hvnly-wizard-accordion__trigger-main">
                            <span class="hvnly-wizard-accordion__title-row">
                                <span class="hvnly-wizard-accordion__title"><?php esc_html_e('Department & Taxonomies', 'havenlytics'); ?></span>
                                <span class="hvnly-wizard-accordion__summary" data-accordion-summary data-accordion-summary-for="department-taxonomies" aria-live="polite"></span>
                            </span>
                            <span class="hvnly-wizard-accordion__desc"><?php esc_html_e('Select your primary department. Status, type, and location taxonomies are configured automatically.', 'havenlytics'); ?></span>
                        </span>
                    </span>
                    <span class="hvnly-wizard-accordion__chevron" aria-hidden="true"><i class="fas fa-chevron-down"></i></span>
                </summary>
                <div class="hvnly-wizard-accordion__body">
                    <div class="hvnly--property--import-department-grid">
                        <div class="hvnly--property--import-department-card selected" data-department="sale" role="button" tabindex="0">
                            <div class="hvnly--property--import-department-icon"><i class="fas fa-tag" aria-hidden="true"></i></div>
                            <div class="hvnly--property--import-department-name"><?php esc_html_e('Sale', 'havenlytics'); ?></div>
                            <div class="hvnly--property--import-department-desc"><?php esc_html_e('Properties available for purchase', 'havenlytics'); ?></div>
                        </div>
                        <div class="hvnly--property--import-department-card" data-department="rent" role="button" tabindex="0">
                            <div class="hvnly--property--import-department-icon"><i class="fas fa-key" aria-hidden="true"></i></div>
                            <div class="hvnly--property--import-department-name"><?php esc_html_e('Rent', 'havenlytics'); ?></div>
                            <div class="hvnly--property--import-department-desc"><?php esc_html_e('Properties available for rent', 'havenlytics'); ?></div>
                        </div>
                        <div class="hvnly--property--import-department-card" data-department="commercial" role="button" tabindex="0">
                            <div class="hvnly--property--import-department-icon"><i class="fas fa-building" aria-hidden="true"></i></div>
                            <div class="hvnly--property--import-department-name"><?php esc_html_e('Commercial', 'havenlytics'); ?></div>
                            <div class="hvnly--property--import-department-desc"><?php esc_html_e('Commercial properties for business', 'havenlytics'); ?></div>
                        </div>
                        <div class="hvnly--property--import-department-card" data-department="let" role="button" tabindex="0">
                            <div class="hvnly--property--import-department-icon"><i class="fas fa-hand-holding-usd" aria-hidden="true"></i></div>
                            <div class="hvnly--property--import-department-name"><?php esc_html_e('Let', 'havenlytics'); ?></div>
                            <div class="hvnly--property--import-department-desc"><?php esc_html_e('Properties for long-term let', 'havenlytics'); ?></div>
                        </div>
                    </div>
                    <div class="hvnly--property--import-department-note">
                        <i class="fas fa-info-circle" aria-hidden="true"></i>
                        <?php esc_html_e('Properties will be imported across all departments automatically.', 'havenlytics'); ?>
                    </div>
                </div>
            </details>

            <details class="hvnly-wizard-accordion__panel" data-accordion-id="property-features">
                <summary class="hvnly-wizard-accordion__trigger">
                    <span class="hvnly-wizard-accordion__trigger-leading">
                        <span class="hvnly-wizard-accordion__icon" aria-hidden="true"><i class="fas fa-star"></i></span>
                        <span class="hvnly-wizard-accordion__trigger-main">
                            <span class="hvnly-wizard-accordion__title-row">
                                <span class="hvnly-wizard-accordion__title"><?php esc_html_e('Property Features', 'havenlytics'); ?></span>
                                <span class="hvnly-wizard-accordion__summary" data-accordion-summary data-accordion-summary-for="property-features" aria-live="polite"></span>
                            </span>
                            <span class="hvnly-wizard-accordion__desc"><?php esc_html_e('Feature terms are assigned to demo listings during import.', 'havenlytics'); ?></span>
                        </span>
                    </span>
                    <span class="hvnly-wizard-accordion__chevron" aria-hidden="true"><i class="fas fa-chevron-down"></i></span>
                </summary>
                <div class="hvnly-wizard-accordion__body">
                    <div class="hvnly--property--import-info-box hvnly-wizard-accordion__info">
                        <div class="hvnly--property--import-info-icon"><i class="fas fa-check-circle" aria-hidden="true"></i></div>
                        <div class="hvnly--property--import-info-text"><?php esc_html_e('Property feature taxonomies and demo feature terms are configured automatically. No action required on this step.', 'havenlytics'); ?></div>
                    </div>
                </div>
            </details>
        </div>
    </div>

    <!-- Step 2 -->
    <div class="hvnly-step-panel hvnly--property--import-step-panel hvnly-location-step" data-step="2" id="step-panel-2" data-active-map-provider="<?php echo esc_attr($hvnly_map_provider); ?>"<?php echo 2 !== $hvnly_initial_step ? ' hidden' : ''; ?>>
        <header class="hvnly-location-step__header">
            <h2 class="hvnly--property--import-card-title"><?php esc_html_e('Configure Map Location', 'havenlytics'); ?></h2>
            <p class="hvnly--property--import-card-description hvnly-location-step__lead"><?php esc_html_e('Choose how maps render on imported listings, then set a default property location. You can refine individual properties later.', 'havenlytics'); ?></p>
        </header>

        <div class="hvnly--property--import-divider"></div>

        <div class="hvnly-wizard-accordion" data-wizard-step="2">
            <details class="hvnly-wizard-accordion__panel" open data-accordion-id="location-settings">
                <summary class="hvnly-wizard-accordion__trigger">
                    <span class="hvnly-wizard-accordion__trigger-leading">
                        <span class="hvnly-wizard-accordion__icon" aria-hidden="true"><i class="fas fa-map"></i></span>
                        <span class="hvnly-wizard-accordion__trigger-main">
                            <span class="hvnly-wizard-accordion__title-row">
                                <span class="hvnly-wizard-accordion__title"><?php esc_html_e('Location Settings', 'havenlytics'); ?></span>
                                <span class="hvnly-wizard-accordion__summary" data-accordion-summary data-accordion-summary-for="location-settings" aria-live="polite"></span>
                            </span>
                            <span class="hvnly-wizard-accordion__desc"><?php esc_html_e('Select the map engine used for demo properties and your site defaults.', 'havenlytics'); ?></span>
                        </span>
                    </span>
                    <span class="hvnly-wizard-accordion__chevron" aria-hidden="true"><i class="fas fa-chevron-down"></i></span>
                </summary>
                <div class="hvnly-wizard-accordion__body">
                    <div class="hvnly-location-step__card hvnly-location-step__card--providers">
                        <div class="hvnly--property--import-map-providers" role="listbox" aria-label="<?php esc_attr_e('Map provider', 'havenlytics'); ?>">
                            <?php foreach ($hvnly_allowed_providers as $hvnly_provider_id) : ?>
                                <?php
                                $hvnly_provider_labels = [
                                    'leaflet'        => __('Leaflet', 'havenlytics'),
                                    'openstreetmap'  => __('OpenStreetMap', 'havenlytics'),
                                    'google'         => __('Google Maps', 'havenlytics'),
                                ];
                                $hvnly_provider_desc = [
                                    'leaflet'        => __('Lightweight OSM tiles — no API key required.', 'havenlytics'),
                                    'openstreetmap'  => __('Native OpenStreetMap tiles — no API key required.', 'havenlytics'),
                                    'google'         => __('Google Maps with Places autocomplete.', 'havenlytics'),
                                ];
                                $hvnly_provider_icons = [
                                    'leaflet'        => 'fa-map',
                                    'openstreetmap'  => 'fa-globe-americas',
                                    'google'         => 'fab fa-google',
                                ];
                                $hvnly_is_active = ($hvnly_map_provider === $hvnly_provider_id);
                                ?>
                                <div class="hvnly--property--import-map-provider<?php echo $hvnly_is_active ? ' active' : ''; ?>" data-provider="<?php echo esc_attr($hvnly_provider_id); ?>" role="option" aria-selected="<?php echo $hvnly_is_active ? 'true' : 'false'; ?>" tabindex="0">
                                    <span class="hvnly-location-step__provider-icon" aria-hidden="true">
                                        <i class="<?php echo esc_attr(strpos($hvnly_provider_icons[$hvnly_provider_id], 'fab ') === 0 ? $hvnly_provider_icons[$hvnly_provider_id] : 'fas ' . $hvnly_provider_icons[$hvnly_provider_id]); ?>"></i>
                                    </span>
                                    <span class="hvnly-location-step__provider-copy">
                                        <span class="hvnly-location-step__provider-name"><?php echo esc_html($hvnly_provider_labels[$hvnly_provider_id]); ?></span>
                                        <span class="hvnly-location-step__provider-desc"><?php echo esc_html($hvnly_provider_desc[$hvnly_provider_id]); ?></span>
                                    </span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </details>

            <details class="hvnly-wizard-accordion__panel" data-accordion-id="provider-details">
                <summary class="hvnly-wizard-accordion__trigger">
                    <span class="hvnly-wizard-accordion__trigger-leading">
                        <span class="hvnly-wizard-accordion__icon" aria-hidden="true"><i class="fas fa-cog"></i></span>
                        <span class="hvnly-wizard-accordion__trigger-main">
                            <span class="hvnly-wizard-accordion__title-row">
                                <span class="hvnly-wizard-accordion__title"><?php esc_html_e('Provider Details', 'havenlytics'); ?></span>
                                <span class="hvnly-wizard-accordion__summary" data-accordion-summary data-accordion-summary-for="provider-details" aria-live="polite"></span>
                            </span>
                            <span class="hvnly-wizard-accordion__desc"><?php esc_html_e('Configure credentials or confirm tile settings for your selected provider.', 'havenlytics'); ?></span>
                        </span>
                    </span>
                    <span class="hvnly-wizard-accordion__chevron" aria-hidden="true"><i class="fas fa-chevron-down"></i></span>
                </summary>
                <div class="hvnly-wizard-accordion__body">
                    <div class="hvnly-location-step__card hvnly-location-step__card--details">
                        <div class="hvnly--property--import-form-row google-api-row hvnly-import-provider-panel hvnly-import-provider-google"<?php echo $hvnly_map_provider === 'google' ? '' : ' hidden'; ?>>
                            <div class="hvnly--property--import-form-label"><?php esc_html_e('Google API Key', 'havenlytics'); ?></div>
                            <div class="hvnly--property--import-form-controls">
                                <input type="password" class="hvnly--property--import-text-input" id="google-api-key" placeholder="<?php esc_attr_e('Enter your Google Maps API Key', 'havenlytics'); ?>" value="<?php echo esc_attr($hvnly_google_api_key); ?>"<?php echo $hvnly_map_provider === 'google' ? '' : ' tabindex="-1" aria-hidden="true"'; ?>>
                                <button type="button" id="save-google-api-key" class="hvnly--property--import-save-api-btn"<?php echo $hvnly_map_provider === 'google' ? '' : ' hidden'; ?>>
                                    <i class="fas fa-save" aria-hidden="true"></i> <?php esc_html_e('Save & Load Map', 'havenlytics'); ?>
                                </button>
                            </div>
                        </div>
                        <div class="hvnly--property--import-form-row leaflet-osm-row hvnly-import-provider-panel hvnly-import-provider-leaflet"<?php echo $hvnly_map_provider === 'google' ? ' hidden' : ''; ?>>
                            <div class="hvnly--property--import-form-label"><?php esc_html_e('Map Tiles', 'havenlytics'); ?></div>
                            <div class="hvnly--property--import-form-controls">
                                <p class="description hvnly-import-leaflet-osm-note hvnly-location-step__provider-note">
                                    <?php
                                    if ($hvnly_map_provider === 'openstreetmap') {
                                        esc_html_e('OpenStreetMap (OSM) tiles active. No Google API key required.', 'havenlytics');
                                    } else {
                                        esc_html_e('Leaflet map with OpenStreetMap tiles. No Google API key required.', 'havenlytics');
                                    }
                                    ?>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </details>

            <details class="hvnly-wizard-accordion__panel" data-accordion-id="default-location">
                <summary class="hvnly-wizard-accordion__trigger">
                    <span class="hvnly-wizard-accordion__trigger-leading">
                        <span class="hvnly-wizard-accordion__icon" aria-hidden="true"><i class="fas fa-map-marker-alt"></i></span>
                        <span class="hvnly-wizard-accordion__trigger-main">
                            <span class="hvnly-wizard-accordion__title-row">
                                <span class="hvnly-wizard-accordion__title"><?php esc_html_e('Default Location', 'havenlytics'); ?></span>
                                <span class="hvnly-wizard-accordion__summary" data-accordion-summary data-accordion-summary-for="default-location" aria-live="polite"></span>
                            </span>
                            <span class="hvnly-wizard-accordion__desc"><?php esc_html_e('Set the starting address and coordinates applied to imported demo properties.', 'havenlytics'); ?></span>
                        </span>
                    </span>
                    <span class="hvnly-wizard-accordion__chevron" aria-hidden="true"><i class="fas fa-chevron-down"></i></span>
                </summary>
                <div class="hvnly-wizard-accordion__body">
                    <div class="hvnly-location-step__card hvnly-location-step__card--location">
                        <div class="hvnly--property--import-form-row hvnly-location-step__address-row">
                            <div class="hvnly--property--import-form-label"><?php esc_html_e('Property Address', 'havenlytics'); ?></div>
                            <div class="hvnly--property--import-form-controls">
                                <input type="text" class="hvnly--property--import-text-input" id="map-address" placeholder="<?php esc_attr_e('Enter property address', 'havenlytics'); ?>" autocomplete="off" value="Austin, TX">
                                <div id="map-places-notice" class="hvnly-map-places-notice" hidden></div>
                                <div class="autocomplete-results" id="autocomplete-results"></div>
                            </div>
                        </div>
                        <div class="hvnly--property--import-map-coordinates">
                            <div class="hvnly--property--import-form-row">
                                <div class="hvnly--property--import-form-label"><?php esc_html_e('Latitude', 'havenlytics'); ?></div>
                                <div class="hvnly--property--import-form-controls">
                                    <input type="text" class="hvnly--property--import-text-input" id="map-latitude" placeholder="<?php esc_attr_e('Latitude', 'havenlytics'); ?>" value="30.2672">
                                </div>
                            </div>
                            <div class="hvnly--property--import-form-row">
                                <div class="hvnly--property--import-form-label"><?php esc_html_e('Longitude', 'havenlytics'); ?></div>
                                <div class="hvnly--property--import-form-controls">
                                    <input type="text" class="hvnly--property--import-text-input" id="map-longitude" placeholder="<?php esc_attr_e('Longitude', 'havenlytics'); ?>" value="-97.7431">
                                </div>
                            </div>
                        </div>
                        <div class="hvnly--property--import-map-container">
                            <div id="import-map" class="hvnly--property--import-map-canvas" role="application" aria-label="<?php esc_attr_e('Property location map', 'havenlytics'); ?>">
                                <div class="hvnly-map-placeholder">
                                    <div class="hvnly-map-loader hvnly-map-loader--idle" aria-hidden="true">
                                        <i class="fas fa-map-marked-alt"></i>
                                    </div>
                                    <span><?php esc_html_e('Map preview will load when you open this step.', 'havenlytics'); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="hvnly--property--import-info-box hvnly-location-step__tip">
                        <div class="hvnly--property--import-info-icon"><i class="fas fa-map-pin" aria-hidden="true"></i></div>
                        <div class="hvnly--property--import-info-text"><?php esc_html_e('Enter an address, drag the map pin, or click on the map to set the property location.', 'havenlytics'); ?></div>
                    </div>
                </div>
            </details>
        </div>
    </div>

    <!-- Step 3 -->
    <div class="hvnly-step-panel hvnly--property--import-step-panel" data-step="3" id="step-panel-3"<?php echo 3 !== $hvnly_initial_step ? ' hidden' : ''; ?>>
        <h2 class="hvnly--property--import-card-title"><?php esc_html_e('Property Media', 'havenlytics'); ?></h2>
        <p class="hvnly--property--import-card-description"><?php esc_html_e('Add YouTube video and preview gallery images for your properties.', 'havenlytics'); ?></p>
        <div class="hvnly--property--import-divider"></div>

        <div class="hvnly-wizard-accordion" data-wizard-step="3">
            <details class="hvnly-wizard-accordion__panel" open data-accordion-id="video-settings">
                <summary class="hvnly-wizard-accordion__trigger">
                    <span class="hvnly-wizard-accordion__trigger-leading">
                        <span class="hvnly-wizard-accordion__icon" aria-hidden="true"><i class="fas fa-video"></i></span>
                        <span class="hvnly-wizard-accordion__trigger-main">
                            <span class="hvnly-wizard-accordion__title-row">
                                <span class="hvnly-wizard-accordion__title"><?php esc_html_e('Video Settings', 'havenlytics'); ?></span>
                                <span class="hvnly-wizard-accordion__summary" data-accordion-summary data-accordion-summary-for="video-settings" aria-live="polite"></span>
                            </span>
                            <span class="hvnly-wizard-accordion__desc"><?php esc_html_e('Optional property tour video for imported listings.', 'havenlytics'); ?></span>
                        </span>
                    </span>
                    <span class="hvnly-wizard-accordion__chevron" aria-hidden="true"><i class="fas fa-chevron-down"></i></span>
                </summary>
                <div class="hvnly-wizard-accordion__body">
                    <div class="hvnly--property--import-form-row">
                        <div class="hvnly--property--import-form-label"><?php esc_html_e('YouTube Video', 'havenlytics'); ?></div>
                        <div class="hvnly--property--import-form-controls">
                            <div class="hvnly--property--import-field-stack">
                                <input type="text" class="hvnly--property--import-text-input" id="youtube-title" placeholder="<?php esc_attr_e('Video Title', 'havenlytics'); ?>" value="Property Tour">
                                <input type="url" class="hvnly--property--import-text-input" id="youtube-url" placeholder="<?php esc_attr_e('YouTube URL', 'havenlytics'); ?>" value="https://www.youtube.com/watch?v=M-j_LvEK2ZA">
                                <input type="url" class="hvnly--property--import-text-input" id="youtube-thumbnail" placeholder="<?php esc_attr_e('Thumbnail URL', 'havenlytics'); ?>" value="https://demo.havenlytics.com/wp-content/uploads/2026/03/20.jpg">
                            </div>
                            <div class="hvnly--property--import-youtube-preview" id="youtube-preview"></div>
                        </div>
                    </div>
                </div>
            </details>

            <details class="hvnly-wizard-accordion__panel" data-accordion-id="media-settings">
                <summary class="hvnly-wizard-accordion__trigger">
                    <span class="hvnly-wizard-accordion__trigger-leading">
                        <span class="hvnly-wizard-accordion__icon" aria-hidden="true"><i class="fas fa-images"></i></span>
                        <span class="hvnly-wizard-accordion__trigger-main">
                            <span class="hvnly-wizard-accordion__title-row">
                                <span class="hvnly-wizard-accordion__title"><?php esc_html_e('Media Settings', 'havenlytics'); ?></span>
                                <span class="hvnly-wizard-accordion__summary" data-accordion-summary data-accordion-summary-for="media-settings" aria-live="polite"></span>
                            </span>
                            <span class="hvnly-wizard-accordion__desc"><?php esc_html_e('Gallery images attached to each imported property.', 'havenlytics'); ?></span>
                        </span>
                    </span>
                    <span class="hvnly-wizard-accordion__chevron" aria-hidden="true"><i class="fas fa-chevron-down"></i></span>
                </summary>
                <div class="hvnly-wizard-accordion__body">
                    <div class="hvnly--property--import-form-row">
                        <div class="hvnly--property--import-form-label"><?php esc_html_e('Gallery', 'havenlytics'); ?></div>
                        <div class="hvnly--property--import-form-controls">
                            <div class="hvnly--property--import-field-stack">
                                <input type="text" class="hvnly--property--import-text-input" id="gallery-title" placeholder="<?php esc_attr_e('Gallery Title', 'havenlytics'); ?>" value="Property Gallery">
                            </div>
                            <div class="hvnly--property--import-gallery-preview">
                                <p><i class="fas fa-images" aria-hidden="true"></i> <?php esc_html_e('3 demo images will be automatically added to each property', 'havenlytics'); ?></p>
                                <div class="hvnly--property--import-gallery-thumbs">
                                    <?php foreach ($hvnly_demo_gallery as $hvnly_idx => $hvnly_img_url) : ?>
                                        <div class="hvnly--property--import-gallery-thumb">
                                            <img src="<?php echo esc_url($hvnly_img_url); ?>" alt="<?php
                                            /* translators: %d: demo gallery image number. */
                                            echo esc_attr( sprintf( __( 'Demo gallery image %d', 'havenlytics' ), $hvnly_idx + 1 ) );
                                            ?>">
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="hvnly--property--import-info-box">
                        <div class="hvnly--property--import-info-icon"><i class="fas fa-video" aria-hidden="true"></i></div>
                        <div class="hvnly--property--import-info-text"><?php esc_html_e('Add a YouTube video tour. Gallery images will use demo property images automatically.', 'havenlytics'); ?></div>
                    </div>
                </div>
            </details>
        </div>
    </div>

    <!-- Step 4 -->
    <div class="hvnly-step-panel hvnly--property--import-step-panel" data-step="4" id="step-panel-4"<?php echo 4 !== $hvnly_initial_step ? ' hidden' : ''; ?>>
        <h2 class="hvnly--property--import-card-title"><?php esc_html_e('Set Import Preferences', 'havenlytics'); ?></h2>
        <p class="hvnly--property--import-card-description"><?php esc_html_e('Configure your import preferences.', 'havenlytics'); ?></p>
        <div class="hvnly--property--import-divider"></div>

        <div class="hvnly-wizard-accordion" data-wizard-step="4">
            <details class="hvnly-wizard-accordion__panel" open data-accordion-id="import-quantity">
                <summary class="hvnly-wizard-accordion__trigger">
                    <span class="hvnly-wizard-accordion__trigger-leading">
                        <span class="hvnly-wizard-accordion__icon" aria-hidden="true"><i class="fas fa-sliders-h"></i></span>
                        <span class="hvnly-wizard-accordion__trigger-main">
                            <span class="hvnly-wizard-accordion__title-row">
                                <span class="hvnly-wizard-accordion__title"><?php esc_html_e('Import Quantity', 'havenlytics'); ?></span>
                                <span class="hvnly-wizard-accordion__summary" data-accordion-summary data-accordion-summary-for="import-quantity" aria-live="polite"></span>
                            </span>
                            <span class="hvnly-wizard-accordion__desc"><?php esc_html_e('How many demo properties to create in this import.', 'havenlytics'); ?></span>
                        </span>
                    </span>
                    <span class="hvnly-wizard-accordion__chevron" aria-hidden="true"><i class="fas fa-chevron-down"></i></span>
                </summary>
                <div class="hvnly-wizard-accordion__body">
                    <div class="hvnly--property--import-form-row">
                        <div class="hvnly--property--import-form-label"><?php esc_html_e('Properties to Import', 'havenlytics'); ?></div>
                        <div class="hvnly--property--import-form-controls">
                            <div class="hvnly--property--import-quantity-slider-container">
                                <div class="hvnly--property--import-slider-wrapper">
                                    <input type="range" min="10" max="<?php echo esc_attr($hvnly_max_import); ?>" value="<?php echo esc_attr($hvnly_default_quantity); ?>" class="hvnly--property--import-quantity-slider" id="propertyQuantitySlider">
                                    <div class="hvnly--property--import-quantity-value" id="quantityValue"><?php echo esc_html($hvnly_default_quantity); ?></div>
                                </div>
                            </div>
                            <p class="hvnly--property--import-field-hint">
                                <?php
                                printf(
                                    /* translators: %d: maximum properties per import */
                                    esc_html__('Maximum: %d properties per import.', 'havenlytics'),
                                    absint($hvnly_max_import)
                                );
                                ?>
                            </p>
                        </div>
                    </div>
                </div>
            </details>

            <details class="hvnly-wizard-accordion__panel" data-accordion-id="import-options">
                <summary class="hvnly-wizard-accordion__trigger">
                    <span class="hvnly-wizard-accordion__trigger-leading">
                        <span class="hvnly-wizard-accordion__icon" aria-hidden="true"><i class="fas fa-toggle-on"></i></span>
                        <span class="hvnly-wizard-accordion__trigger-main">
                            <span class="hvnly-wizard-accordion__title-row">
                                <span class="hvnly-wizard-accordion__title"><?php esc_html_e('Import Options', 'havenlytics'); ?></span>
                                <span class="hvnly-wizard-accordion__summary" data-accordion-summary data-accordion-summary-for="import-options" aria-live="polite"></span>
                            </span>
                            <span class="hvnly-wizard-accordion__desc"><?php esc_html_e('Images, email notifications, and cache preferences.', 'havenlytics'); ?></span>
                        </span>
                    </span>
                    <span class="hvnly-wizard-accordion__chevron" aria-hidden="true"><i class="fas fa-chevron-down"></i></span>
                </summary>
                <div class="hvnly-wizard-accordion__body">
                    <div class="hvnly--property--import-form-row">
                        <div class="hvnly--property--import-form-label"><?php esc_html_e('Include Images', 'havenlytics'); ?></div>
                        <div class="hvnly--property--import-form-controls">
                            <label class="hvnly--property--import-toggle-switch">
                                <input type="checkbox" id="include-images" checked>
                                <span class="hvnly--property--import-toggle-slider"></span>
                            </label>
                        </div>
                    </div>
                    <div class="hvnly--property--import-form-row">
                        <div class="hvnly--property--import-form-label"><?php esc_html_e('Email Notifications', 'havenlytics'); ?></div>
                        <div class="hvnly--property--import-form-controls">
                            <label class="hvnly--property--import-toggle-switch">
                                <input type="checkbox" id="email-notifications" <?php checked( ! empty( $hvnly_import_email_notify_default ) ); ?>>
                                <span class="hvnly--property--import-toggle-slider"></span>
                            </label>
                        </div>
                    </div>
                    <div class="hvnly--property--import-form-row">
                        <div class="hvnly--property--import-form-label">
                            <?php esc_html_e('Cache During Import', 'havenlytics'); ?>
                            <p class="hvnly--property--import-field-hint">
                                <?php esc_html_e('Enable Cache After Import', 'havenlytics'); ?>
                            </p>
                        </div>
                        <div class="hvnly--property--import-form-controls">
                            <label class="hvnly--property--import-toggle-switch">
                                <input type="checkbox" id="enable-cache-after-import" <?php checked( ! empty( $hvnly_cache_enabled ) ); ?>>
                                <span class="hvnly--property--import-toggle-slider"></span>
                            </label>
                            <p class="hvnly--property--import-field-hint">
                                <?php esc_html_e('If enabled, property listing cache will activate after import completes for faster performance.', 'havenlytics'); ?>
                            </p>
                            <p class="hvnly--property--import-field-hint" id="cache-import-status" aria-live="polite">
                                <?php
                                echo esc_html(
                                    ! empty( $hvnly_cache_enabled )
                                        ? __( 'Status: ON — listings (grid, list, map, sidebar) use cached AJAX responses.', 'havenlytics' )
                                        : __( 'Status: OFF — listings use live queries without cache.', 'havenlytics' )
                                );
                                ?>
                            </p>
                        </div>
                    </div>
                </div>
            </details>

            <details class="hvnly-wizard-accordion__panel" data-accordion-id="agent-settings">
                <summary class="hvnly-wizard-accordion__trigger">
                    <span class="hvnly-wizard-accordion__trigger-leading">
                        <span class="hvnly-wizard-accordion__icon" aria-hidden="true"><i class="fas fa-user-tie"></i></span>
                        <span class="hvnly-wizard-accordion__trigger-main">
                            <span class="hvnly-wizard-accordion__title-row">
                                <span class="hvnly-wizard-accordion__title"><?php esc_html_e('Agent Settings', 'havenlytics'); ?></span>
                                <span class="hvnly-wizard-accordion__summary" data-accordion-summary data-accordion-summary-for="agent-settings" aria-live="polite"></span>
                            </span>
                            <span class="hvnly-wizard-accordion__desc"><?php esc_html_e('Demo agents and agencies included with every import.', 'havenlytics'); ?></span>
                        </span>
                    </span>
                    <span class="hvnly-wizard-accordion__chevron" aria-hidden="true"><i class="fas fa-chevron-down"></i></span>
                </summary>
                <div class="hvnly-wizard-accordion__body">
                    <div class="hvnly--property--import-info-box hvnly-wizard-accordion__agent-note">
                        <div class="hvnly--property--import-info-icon"><i class="fas fa-users" aria-hidden="true"></i></div>
                        <div class="hvnly--property--import-info-text"><?php esc_html_e('Demo agents, agencies, and property assignments are created automatically during import. Agent profile pages and Contact Agent forms will be ready after setup completes.', 'havenlytics'); ?></div>
                    </div>
                </div>
            </details>

            <details class="hvnly-wizard-accordion__panel" data-accordion-id="additional-content">
                <summary class="hvnly-wizard-accordion__trigger">
                    <span class="hvnly-wizard-accordion__trigger-leading">
                        <span class="hvnly-wizard-accordion__icon" aria-hidden="true"><i class="fas fa-file-alt"></i></span>
                        <span class="hvnly-wizard-accordion__trigger-main">
                            <span class="hvnly-wizard-accordion__title-row">
                                <span class="hvnly-wizard-accordion__title"><?php esc_html_e('Additional Content', 'havenlytics'); ?></span>
                                <span class="hvnly-wizard-accordion__summary" data-accordion-summary data-accordion-summary-for="additional-content" aria-live="polite"></span>
                            </span>
                            <span class="hvnly-wizard-accordion__desc"><?php esc_html_e('What happens when you complete the wizard.', 'havenlytics'); ?></span>
                        </span>
                    </span>
                    <span class="hvnly-wizard-accordion__chevron" aria-hidden="true"><i class="fas fa-chevron-down"></i></span>
                </summary>
                <div class="hvnly-wizard-accordion__body">
                    <div class="hvnly--property--import-info-box">
                        <div class="hvnly--property--import-info-icon"><i class="fas fa-info-circle" aria-hidden="true"></i></div>
                        <div class="hvnly--property--import-info-text"><?php esc_html_e('Click Complete to start importing your properties. Demo agents, agencies, and property assignments are included automatically.', 'havenlytics'); ?></div>
                    </div>
                </div>
            </details>
        </div>
    </div>
</div>
