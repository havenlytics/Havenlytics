<?php

if (!defined('ABSPATH')) {
    exit;
}
use HvnlyNab\Frontend\LayoutManager;
// ==============================================
// GLOBAL WRAPPER FUNCTIONS (KEEP AS IS)
// ==============================================

/**
 * Output start page wrapper.
 */
if (!function_exists('hvnly_output_content_wrapper_start')) {
    function hvnly_output_content_wrapper_start()
    {
        hvnly_get_template_part('global/wrapper-start');
    }
}

/**
 * Output end page wrapper.
 */
if (!function_exists('hvnly_output_content_wrapper_end')) {
    function hvnly_output_content_wrapper_end()
    {
        hvnly_get_template_part('global/wrapper-end');
    }
}

// ==============================================
// SINGLE PROPERTY FUNCTIONS (NEW DESIGN)
// ==============================================

/* ==== Scroll Progress Functions ==== */

if (!function_exists('hvnly_single_property_scroll_progress')) {
    function hvnly_single_property_scroll_progress()
    {
        // Only show on single property pages
        if (!is_singular('hvnly_property')) {
            return;
        }

        echo '<!-- Scroll Progress Indicator -->
        <div class="hvnly-property-single__scroll-progress">
            <div class="hvnly-property-single__scroll-progress-bar" id="hvnlyPropertySingleScrollProgressBar"></div>
        </div>';
    }
}

/* ==== SVG Icons Functions ==== */

if (!function_exists('hvnly_single_property_svg_icons')) {
    function hvnly_single_property_svg_icons()
    {
        // Only show on single property pages
        if (!is_singular('hvnly_property')) {
            return;
        }

        hvnly_get_template_part('single-property/svg-icons');
    }
}

/* ==== Modal Popup Functions ==== */

if (!function_exists('hvnly_single_property_gallery_popup')) {
    function hvnly_single_property_gallery_popup()
    {
        // Only show on single property pages
        if (!is_singular('hvnly_property')) {
            return;
        }
        hvnly_get_template_part('single-property/modal-gallery');
    }
}

if (!function_exists('hvnly_single_property_video_popup')) {
    function hvnly_single_property_video_popup()
    {
        // Only show on single property pages
        if (!is_singular('hvnly_property')) {
            return;
        }

        hvnly_get_template_part('single-property/modal-video');
    }
}

if (!function_exists('hvnly_single_property_contact_agent_modal')) {
    /**
     * Output Contact Agent inquiry modal on single property pages.
     *
     * @since 3.0.2
     * @return void
     */
    function hvnly_single_property_contact_agent_modal()
    {
        if (!is_singular('hvnly_property')) {
            return;
        }

        if (!function_exists('hvnly_is_contact_agent_enabled') || !hvnly_is_contact_agent_enabled()) {
            return;
        }

        $property_id = get_the_ID();
        if (!$property_id) {
            return;
        }

        $agents = function_exists('hvnly_get_property_agents')
            ? hvnly_get_property_agents($property_id)
            : array();

        if (empty($agents) && function_exists('hvnly_get_property_agent')) {
            $primary = hvnly_get_property_agent($property_id);
            if (is_array($primary) && !empty($primary)) {
                $agents = array($primary);
            }
        }

        $agent = !empty($agents) ? $agents[0] : array();

        hvnly_get_template_part(
            'single-property/modal-contact-agent',
            null,
            array(
                'property_id'    => $property_id,
                'property_title' => get_the_title($property_id),
                'agent'          => is_array($agent) ? $agent : array(),
                'agents'         => is_array($agents) ? $agents : array(),
            )
        );
    }
}

if (!function_exists('hvnly_single_property_scroll_top')) {
    function hvnly_single_property_scroll_top()
    {
        // Check if back to top button is enabled in settings
        if (!hvnly_is_back_to_top_enabled()) {
            return;
        }

        // Only show on single property pages
        if (!is_singular('hvnly_property')) {
            return;
        }
        echo '<!-- Scroll to top button -->
        <div class="hvnly-property-single__gallery-scroll-top">
            <svg class="hvnly-icon hvnly-icon-thin"><use xlink:href="#hvnly-chevron-up"></use></svg>
        </div>';
    }
}

/* ==== Header Functions ==== */

if (!function_exists('hvnly_single_property_header')) {
    function hvnly_single_property_header()
    {
        echo '<div class="hvnly-property-single__header">';
        echo '<div class="hvnly-property-single__container">';
        
        do_action('hvnly_single_property_header');
        
        echo '</div>';
        echo '</div>';
    }
}

if (!function_exists('hvnly_single_property_breadcrumb')) {
    function hvnly_single_property_breadcrumb()
    {
        // Check if breadcrumbs are enabled in settings
        if (!hvnly_is_breadcrumb_enabled()) {
            return; // Exit early if disabled
        }
        
        hvnly_get_template_part('single-property/breadcrumb');
    }
}

if (!function_exists('hvnly_single_property_title_section')) {
    function hvnly_single_property_title_section()
    {
        hvnly_get_template_part('single-property/title-section');
    }
}

if (!function_exists('hvnly_single_property_meta')) {
    function hvnly_single_property_meta()
    {
        hvnly_get_template_part('single-property/meta');
    }
}

if (!function_exists('hvnly_single_property_actions')) {
    function hvnly_single_property_actions()
    {
        /*
         * 3.4.0: the Favorite button follows the Favorites feature, not the
         * legacy `hvnly_EnableSaveProperty` toggle.
         *
         * That toggle pre-dates Favorites, defaults to false, and is stored
         * false on every existing install — gating on it would hide the
         * Favorite button on every single-property page in the wild, while
         * archive cards show it. Behaviour now matches archive cards exactly.
         *
         * `hvnly_is_save_property_enabled()` is still honoured as an explicit
         * opt-IN for sites that turned it on, and the filter below is the
         * supported way to hide the button again.
         */
        $favorites_enabled = function_exists('hvnly_is_favorites_enabled')
            && hvnly_is_favorites_enabled();

        /**
         * Filter whether the Favorite button renders on single property pages.
         *
         * @since 3.4.0
         *
         * @param bool $show Whether to show the Favorite button.
         */
        $save_enabled = (bool) apply_filters(
            'hvnly_show_single_property_favorite',
            $favorites_enabled || hvnly_is_save_property_enabled()
        );

        $print_enabled = hvnly_is_print_button_enabled();

        // Only render if at least one action is enabled
        if (!$save_enabled && !$print_enabled) {
            return;
        }

        hvnly_get_template_part('single-property/actions', '', array(
            'save_enabled' => $save_enabled,
            'print_enabled' => $print_enabled
        ));
    }
}

/* ==== Main Container Functions ==== */

if (!function_exists('hvnly_single_property_main_container_start')) {
    function hvnly_single_property_main_container_start()
    {
        echo '<div class="hvnly-property-single__container">';
    }
}

if (!function_exists('hvnly_single_property_main_container_end')) {
    function hvnly_single_property_main_container_end()
    {
        echo '</div>';
    }
}

/* ==== Layout Grid Functions ==== */

if (!function_exists('hvnly_single_property_layout_grid')) {
    function hvnly_single_property_layout_grid()
    {
        $layout_manager = LayoutManager::instance();
        $grid_classes = apply_filters('hvnly_layout_grid_classes', [], 'single');
        
        echo '<div class="' . esc_attr(implode(' ', $grid_classes)) . '">';
        do_action('hvnly_single_property_layout_grid');
        echo '</div>';
    }
}

/* ==== Main Content Area Functions ==== */

if (!function_exists('hvnly_single_property_main_content_area')) {
    function hvnly_single_property_main_content_area()
    {
        $layout_manager = LayoutManager::instance();
        $content_classes = apply_filters('hvnly_main_content_classes', [], 'single');
        
        echo '<div class="' . esc_attr(implode(' ', $content_classes)) . '">';
        do_action('hvnly_single_property_main_content');
        echo '</div>';
    }
}

/* ==== Sidebar Area Functions ==== */

if (!function_exists('hvnly_single_property_sidebar_area')) {
    function hvnly_single_property_sidebar_area()
    {
        $layout_manager = LayoutManager::instance();
        $should_display = apply_filters('hvnly_should_display_sidebar', true, 'single');
        
        if (!$should_display) {
            return;
        }
        
        $sidebar_classes = apply_filters('hvnly_sidebar_classes', [], 'single');
        
        echo '<div class="' . esc_attr(implode(' ', $sidebar_classes)) . '">';
        do_action('hvnly_single_property_sidebar');
        echo '</div>';
    }
}

/* ==== Widget Functions ==== */

if (!function_exists('hvnly_display_sidebar_widgets')) {
    function hvnly_display_sidebar_widgets()
    {
       hvnly_get_template_part('single-property/sidebar');
    }
}

/* ==== Similar Properties Functions ==== */

if (!function_exists('hvnly_single_property_similar_properties')) {
    function hvnly_single_property_similar_properties()
    {
        echo '<div class="hvnly-property-single__similar-properties">';
        do_action('hvnly_single_property_similar_properties');
        echo '</div>';
    }
}

if (!function_exists('hvnly_single_property_similar_header')) {
    function hvnly_single_property_similar_header()
    {
        hvnly_get_template_part('single-property/similar-carousel-header');
    }
}

if (!function_exists('hvnly_single_property_similar_carousel')) {
    function hvnly_single_property_similar_carousel()
    {
        hvnly_get_template_part('single-property/similar-carousel-properties');
    }
}

if (!function_exists('hvnly_single_property_similar_carousel_dots')) {
    function hvnly_single_property_similar_carousel_dots()
    {
        hvnly_get_template_part('single-property/similar-carousel-dots');
    }
}

// ==============================================
// SEARCH FUNCTIONS (KEEP AS IS)
// ==============================================

/**
 * Output search loop start.
 */
if (!function_exists('hvnly_search_loop_start')) {
    function hvnly_search_loop_start()
    {
        hvnly_get_template_part('search/loop-start');
    }
}

/**
 * Output search loop end.
 */
if (!function_exists('hvnly_search_loop_end')) {
    function hvnly_search_loop_end()
    {
        hvnly_get_template_part('search/loop-end');
    }
}

// ==============================================
// ARCHIVE LAYOUT FUNCTIONS
// ==============================================

/**
 * Add dynamic archive layout styles based on sidebar visibility
 * This function adds CSS to adjust grid columns when sidebar is hidden
 * 
 * @since 2.0.3
 */
if (!function_exists('hvnly_add_archive_layout_styles')) {
    function hvnly_add_archive_layout_styles() {
        // Only apply on property archive pages
        if (!is_post_type_archive('hvnly_property') && !is_tax(get_object_taxonomies('hvnly_property'))) {
            return;
        }
        
        // Check if sidebar should be shown
        $show_sidebar = function_exists('hvnly_should_show_filter_sidebar') ? hvnly_should_show_filter_sidebar() : true;
        
        // If sidebar is shown, no need to add custom styles
        if ($show_sidebar) {
            return;
        }
        
        // Add custom CSS when sidebar is hidden
        ?>
<style id="hvnly-no-sidebar-styles">
/* When sidebar is hidden, make main container full width */
.hvnly-with-sidebar__contents__grids {
    grid-template-columns: 1fr !important;
    gap: 0;
}

/* Hide sidebar element if it exists */
.hvnly-property-filter-sidebar {
    display: none !important;
}

/* Adjust grid columns for full width */
.hvnly-property-grid-view.hvnly-grid-view {
    grid-template-columns: repeat(3, 1fr) !important;
}

/* Responsive adjustments */
@media (max-width: 1200px) {
    .hvnly-property-grid-view.hvnly-grid-view {
        grid-template-columns: repeat(2, 1fr) !important;
    }
}

@media (max-width: 768px) {
    .hvnly-property-grid-view.hvnly-grid-view {
        grid-template-columns: 1fr !important;
    }
}
</style>
<?php
    }
}

// ==============================================
// SINGLE AGENT FUNCTIONS
// ==============================================

if ( ! function_exists( 'hvnly_single_agent_header' ) ) {
	function hvnly_single_agent_header() {
		echo '<div class="hvnly-agent-single__header">';
		echo '<div class="hvnly-agent-single__container">';
		do_action( 'hvnly_single_agent_header' );
		echo '</div>';
		echo '</div>';
	}
}

if ( ! function_exists( 'hvnly_single_agent_breadcrumb' ) ) {
	function hvnly_single_agent_breadcrumb() {
		if ( function_exists( 'hvnly_is_breadcrumb_enabled' ) && ! hvnly_is_breadcrumb_enabled() ) {
			return;
		}

		hvnly_get_template_part( 'single-agent/breadcrumb' );
	}
}

if ( ! function_exists( 'hvnly_single_agent_main_container_start' ) ) {
	function hvnly_single_agent_main_container_start() {
		echo '<div class="hvnly-agent-single__container">';
	}
}

if ( ! function_exists( 'hvnly_single_agent_main_container_end' ) ) {
	function hvnly_single_agent_main_container_end() {
		echo '</div>';
	}
}

if ( ! function_exists( 'hvnly_single_agent_profile_row' ) ) {
	function hvnly_single_agent_profile_row() {
		echo '<div class="hvnly-agent-single__profile-row">';
		do_action( 'hvnly_single_agent_profile_row' );
		echo '</div>';
	}
}

if ( ! function_exists( 'hvnly_single_agent_listings_section' ) ) {
	function hvnly_single_agent_listings_section() {
		echo '<div class="hvnly-agent-single__listings">';
		do_action( 'hvnly_single_agent_listings_section' );
		echo '</div>';
	}
}

if ( ! function_exists( 'hvnly_single_agent_layout_grid' ) ) {
	function hvnly_single_agent_layout_grid() {
		echo '<div class="hvnly-agent-single__layout">';
		do_action( 'hvnly_single_agent_layout_grid' );
		echo '</div>';
	}
}

if ( ! function_exists( 'hvnly_single_agent_main_content_area' ) ) {
	function hvnly_single_agent_main_content_area() {
		echo '<div class="hvnly-agent-single__content">';
		do_action( 'hvnly_single_agent_main_content' );
		echo '</div>';
	}
}

if ( ! function_exists( 'hvnly_single_agent_sidebar_area' ) ) {
	function hvnly_single_agent_sidebar_area() {
		echo '<div class="hvnly-agent-single__sidebar">';
		do_action( 'hvnly_single_agent_sidebar' );
		echo '</div>';
	}
}

if ( ! function_exists( 'hvnly_single_agent_profile_header' ) ) {
	function hvnly_single_agent_profile_header() {
		$agent = function_exists( 'hvnly_get_agent' ) ? hvnly_get_agent( (int) get_the_ID() ) : array();

		hvnly_get_template(
			'single-agent/partials/profile-header.php',
			array(
				'agent' => is_array( $agent ) ? $agent : array(),
			)
		);
	}
}

if ( ! function_exists( 'hvnly_single_agent_about' ) ) {
	function hvnly_single_agent_about() {
		$agent = function_exists( 'hvnly_get_agent' ) ? hvnly_get_agent( (int) get_the_ID() ) : array();

		hvnly_get_template(
			'single-agent/partials/about.php',
			array(
				'agent' => is_array( $agent ) ? $agent : array(),
			)
		);
	}
}

if ( ! function_exists( 'hvnly_single_agent_agency' ) ) {
	function hvnly_single_agent_agency() {
		$agent = function_exists( 'hvnly_get_agent' ) ? hvnly_get_agent( (int) get_the_ID() ) : array();

		hvnly_get_template(
			'single-agent/partials/agency.php',
			array(
				'agent' => is_array( $agent ) ? $agent : array(),
			)
		);
	}
}

if ( ! function_exists( 'hvnly_single_agent_properties' ) ) {
	function hvnly_single_agent_properties() {
		$agent_id = (int) get_the_ID();
		$agent    = function_exists( 'hvnly_get_agent' ) ? hvnly_get_agent( $agent_id ) : array();

		hvnly_get_template(
			'single-agent/partials/properties.php',
			array(
				'agent_id' => $agent_id,
				'agent'    => is_array( $agent ) ? $agent : array(),
			)
		);
	}
}

if ( ! function_exists( 'hvnly_single_agent_sidebar_card' ) ) {
	function hvnly_single_agent_sidebar_card() {
		$agent = function_exists( 'hvnly_get_agent' ) ? hvnly_get_agent( (int) get_the_ID() ) : array();

		hvnly_get_template(
			'single-agent/partials/sidebar.php',
			array(
				'agent' => is_array( $agent ) ? $agent : array(),
			)
		);
	}
}