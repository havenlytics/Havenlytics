<?php
/**
 * HVN: Property Archive Widget - Complete Integration
 * 
 * This widget loads the ENTIRE property archive template system,
 * including filters, grid/list/map views, pagination, and AJAX.
 * 
 * @package     Havenlytics
 * @subpackage  Integrations\Elementor\Widgets
 * @since       3.0.0
 */

namespace HvnlyNab\Integrations\Elementor\Widgets;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use HvnlyNab\Frontend\Query\PropertyQueryArgsBuilder;
use HvnlyNab\Frontend\Query\PropertyQueryExecutor;

if (!defined('ABSPATH')) {
    exit;
}

class HvnlyAllPropertiesWidget extends Widget_Base {

    /**
     * Store original WP_Query before modification
     *
     * @var \WP_Query|null
     */
    private $original_wp_query = null;

    /**
     * Cached property query for the current render lifecycle.
     *
     * @var \WP_Query|null
     */
    private $render_property_query = null;

    /**
     * Signature for the cached render query.
     *
     * @var string|null
     */
    private $render_property_query_signature = null;

    /**
     * Store original post before modification
     *
     * @var \WP_Post|null
     */
    private $original_post = null;

    public function get_name(): string {
        return 'hvnly_all_properties';
    }

    public function get_title(): string {
        return __('HVN: Property Archive', 'havenlytics');
    }

    public function get_icon(): string {
        // eicon-gallery-grid is overridden in elementor-editor.css with the Havenlytics SVG.
        return 'eicon-gallery-grid';
    }

    public function get_categories(): array {
        return ['havenlytics'];
    }

    public function get_keywords(): array {
        return ['havenlytics', 'property', 'real estate', 'listing', 'archive', 'search', 'filter'];
    }

    /**
     * Styles Elementor loads automatically in the editor canvas and preview iframe.
     *
     * @return array
     */
    public function get_style_depends(): array {
        return [
            'hvnly-fontawesome-all-frontend',
            'hvnly-frontend-default',
            'hvnly-frontend-components',
            'hvnly-frontend-global-grid',
            'hvnly-frontend-property-ajax-filter',
            'hvnly-frontend-property-archive',
            'hvnly-frontend-property-map',
            'hvnly-frontend-property-responsive',
            'hvnly-elementor-archive-widget',
            'hvnly-elementor-widgets',
        ];
    }

    /**
     * Scripts loaded with the widget on frontend (not in Elementor editor canvas).
     *
     * @return array
     */
    public function get_script_depends(): array {
        return ['hvnly-elementor-widgets'];
    }

    protected function register_controls(): void {
        // Display Settings
        $this->start_controls_section(
            'display_section',
            [
                'label' => __('Display Settings', 'havenlytics'),
                'tab' => Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_control(
            'show_filter_sidebar',
            [
                'label' => __('Show Filter Sidebar', 'havenlytics'),
                'type' => Controls_Manager::SWITCHER,
                'default' => 'yes',
            ]
        );

        $this->add_control(
            'show_top_search',
            [
                'label' => __('Show Top Search Bar', 'havenlytics'),
                'type' => Controls_Manager::SWITCHER,
                'default' => 'yes',
            ]
        );

        $this->add_control(
            'default_view',
            [
                'label' => __('Default View', 'havenlytics'),
                'type' => Controls_Manager::SELECT,
                'default' => 'grid',
                'options' => [
                    'grid' => __('Grid View', 'havenlytics'),
                    'list' => __('List View', 'havenlytics'),
                    'map' => __('Map View', 'havenlytics'),
                ],
            ]
        );

        $this->add_control(
            'sidebar_position',
            [
                'label' => __('Sidebar Position', 'havenlytics'),
                'type' => Controls_Manager::SELECT,
                'default' => 'left',
                'options' => [
                    'left' => __('Left', 'havenlytics'),
                    'right' => __('Right', 'havenlytics'),
                ],
                'condition' => ['show_filter_sidebar' => 'yes'],
            ]
        );

        $this->add_control(
            'columns',
            [
                'label' => __('Grid Columns', 'havenlytics'),
                'type' => Controls_Manager::SELECT,
                'default' => '2',
                'options' => [
                    '1' => __('1 Column', 'havenlytics'),
                    '2' => __('2 Columns', 'havenlytics'),
                    '3' => __('3 Columns', 'havenlytics'),
                    '4' => __('4 Columns', 'havenlytics'),
                ],
            ]
        );

        $this->end_controls_section();

        // Query Settings
        $this->start_controls_section(
            'query_section',
            [
                'label' => __('Query Settings', 'havenlytics'),
                'tab' => Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_control(
            'posts_per_page',
            [
                'label' => __('Properties Per Page', 'havenlytics'),
                'type' => Controls_Manager::NUMBER,
                'default' => 4,
                'min' => 1,
                'max' => 100,
            ]
        );

        $this->add_control(
            'orderby',
            [
                'label' => __('Order By', 'havenlytics'),
                'type' => Controls_Manager::SELECT,
                'default' => 'date',
                'options' => [
                    'date' => __('Date (Newest)', 'havenlytics'),
                    'title' => __('Title', 'havenlytics'),
                    'price_low' => __('Price (Low to High)', 'havenlytics'),
                    'price_high' => __('Price (High to Low)', 'havenlytics'),
                    'rand' => __('Random', 'havenlytics'),
                ],
            ]
        );

        $this->add_control(
            'featured_only',
            [
                'label' => __('Featured Only', 'havenlytics'),
                'type' => Controls_Manager::SWITCHER,
                'default' => 'no',
            ]
        );

        $this->end_controls_section();

        // Filter Defaults
        $this->start_controls_section(
            'filter_defaults_section',
            [
                'label' => __('Default Filters', 'havenlytics'),
                'tab' => Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_control(
            'default_department',
            [
                'label' => __('Default Department', 'havenlytics'),
                'type' => Controls_Manager::SELECT2,
                'options' => $this->get_taxonomy_options('hvnly_prop_depts'),
                'label_block' => true,
            ]
        );

        $this->add_control(
            'default_min_price',
            [
                'label' => __('Default Min Price', 'havenlytics'),
                'type' => Controls_Manager::NUMBER,
                'min' => 0,
            ]
        );

        $this->add_control(
            'default_max_price',
            [
                'label' => __('Default Max Price', 'havenlytics'),
                'type' => Controls_Manager::NUMBER,
                'min' => 0,
            ]
        );

        $this->add_control(
            'default_bedrooms',
            [
                'label' => __('Default Min Bedrooms', 'havenlytics'),
                'type' => Controls_Manager::NUMBER,
                'min' => 0,
                'max' => 10,
            ]
        );

        $this->add_control(
            'default_bathrooms',
            [
                'label' => __('Default Min Bathrooms', 'havenlytics'),
                'type' => Controls_Manager::NUMBER,
                'min' => 0,
                'max' => 10,
            ]
        );

        $this->end_controls_section();

        // Style Controls
        $this->start_controls_section(
            'style_section',
            [
                'label' => __('Style Settings', 'havenlytics'),
                'tab' => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'brand_color',
            [
                'label' => __('Brand Color', 'havenlytics'),
                'type' => Controls_Manager::COLOR,
                'default' => '#6C60FE',
                'selectors' => [
                    '{{WRAPPER}}' => '--hvnly-brand-primary: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'secondary_color',
            [
                'label' => __('Secondary Color', 'havenlytics'),
                'type' => Controls_Manager::COLOR,
                'default' => '#764ba2',
                'selectors' => [
                    '{{WRAPPER}}' => '--hvnly-brand-secondary: {{VALUE}};',
                ],
            ]
        );

        $this->end_controls_section();
    }

    /**
     * Get taxonomy options for select controls
     */
    private function get_taxonomy_options(string $taxonomy): array {
        $options = ['' => __('Default (All)', 'havenlytics')];

        if (!taxonomy_exists($taxonomy)) {
            return $options;
        }

        $terms = get_terms([
            'taxonomy' => $taxonomy,
            'hide_empty' => true,
        ]);

        if (!is_wp_error($terms)) {
            foreach ($terms as $term) {
                $options[$term->slug] = $term->name;
            }
        }

        return $options;
    }

    /**
     * Save original WordPress query state before modification
     */
    private function save_original_query_state(): void {
        global $wp_query, $post;
        
        if ($this->original_wp_query === null) {
            $this->original_wp_query = clone $wp_query;
        }
        
        if ($this->original_post === null && isset($post)) {
            $this->original_post = $post;
        }
    }

    /**
     * Restore original WordPress query state after modification
     */
    private function restore_original_query_state(): void {
        global $wp_query, $post;
        
        if ($this->original_wp_query !== null) {
            $wp_query = $this->original_wp_query;
            $this->original_wp_query = null;
        }
        
        if ($this->original_post !== null) {
            $post = $this->original_post;
            setup_postdata($post);
            $this->original_post = null;
        }
        
        wp_reset_postdata();
    }

    /**
     * Setup the WordPress query for widget context via shared builder.
     */
    private function setup_widget_query(array $settings, string $widget_id): \WP_Query {
        $data     = PropertyQueryArgsBuilder::merge_elementor_widget_context($settings, $widget_id);
        $per_page = absint($settings['posts_per_page']);
        $page     = PropertyQueryArgsBuilder::resolve_paged($widget_id, $data, 1);
        $signature = md5(wp_json_encode([$settings, $widget_id, $page]));

        if ($this->render_property_query instanceof \WP_Query && $this->render_property_query_signature === $signature) {
            return $this->render_property_query;
        }

        $this->render_property_query = PropertyQueryExecutor::query($data, $page, $per_page, [
            'widget_id'      => $widget_id,
            'filter_context' => 'ssr',
        ]);
        $this->render_property_query_signature = $signature;

        return $this->render_property_query;
    }

/**
 * Render the widget - LOADS EXISTING TEMPLATE SYSTEM
 */
protected function render(): void {
    $settings = $this->get_settings_for_display();

    if (class_exists('\HvnlyNab\Integrations\Elementor\Bootstrap')) {
        \HvnlyNab\Integrations\Elementor\Bootstrap::get_instance()->enqueue_widget_assets_for_render();
    }

    $this->save_original_query_state();
    
    global $hvnly_elementor_widget_active, $hvnly_elementor_widget_settings, $wp_query;
    $hvnly_elementor_widget_active = true;
    $hvnly_elementor_widget_settings = $settings;

    $widget_id = $this->get_id();
    $grid_id   = 'hvnly-property-grid-' . $widget_id;
    $map_id    = 'hvnly-map-placeholder-' . $widget_id;
    $view_type = PropertyQueryArgsBuilder::resolve_view_type($settings);
    
    $wrapper_classes = [
        'hvnly-content-wrapper',
        'hvnly-property-archive__content-wrapper',
        'hvnly-all-properties-widget',
        'hvnly-elementor-widget',
        'hvnly-widget-' . $widget_id,
    ];
    
    if ($settings['show_filter_sidebar'] !== 'yes') {
        $wrapper_classes[] = 'hvnly-no-sidebar';
    }
    
    if ($settings['sidebar_position'] === 'right') {
        $wrapper_classes[] = 'hvnly-sidebar-right';
    }

    $columns = absint($settings['columns'] ?? 2);
    if ($columns < 1 || $columns > 4) {
        $columns = 2;
    }

    $grid_columns_style = '--hvnly-grid-columns: ' . $columns . ';';

    $property_query = $this->setup_widget_query($settings, $widget_id);
    $original_wp_query = $wp_query;
    $wp_query = $property_query;

    ?>
<div class="<?php echo esc_attr(implode(' ', $wrapper_classes)); ?>"
    data-widget-id="<?php echo esc_attr($widget_id); ?>"
    data-grid-id="<?php echo esc_attr($grid_id); ?>"
    data-map-id="<?php echo esc_attr($map_id); ?>"
    data-columns="<?php echo esc_attr($columns); ?>"
    data-posts-per-page="<?php echo esc_attr($settings['posts_per_page']); ?>"
    data-orderby="<?php echo esc_attr($settings['orderby']); ?>"
    data-featured-only="<?php echo esc_attr($settings['featured_only']); ?>"
    data-default-view="<?php echo esc_attr($settings['default_view']); ?>"
    data-view-type="<?php echo esc_attr($view_type); ?>"
    data-default-department="<?php echo esc_attr($settings['default_department'] ?? ''); ?>"
    data-default-min-price="<?php echo esc_attr($settings['default_min_price'] ?? ''); ?>"
    data-default-max-price="<?php echo esc_attr($settings['default_max_price'] ?? ''); ?>"
    data-default-bedrooms="<?php echo esc_attr($settings['default_bedrooms'] ?? ''); ?>"
    data-default-bathrooms="<?php echo esc_attr($settings['default_bathrooms'] ?? ''); ?>"
    style="<?php echo esc_attr($grid_columns_style); ?>">

    <?php
            if ($settings['show_top_search'] === 'yes') {
                $this->render_search_filters();
            }
            
            echo '<div class="hvnly-with-sidebar__contents__grids">';
            
            if ($settings['show_filter_sidebar'] === 'yes') {
                $this->render_filter_sidebar($settings);
            }
            
            echo '<section class="hvnly-property--grid--listings">';
            
            $this->render_view_controls($property_query, $settings, $widget_id);
            $this->render_property_loop($property_query, $settings, $widget_id, $grid_id, $map_id, $view_type, $columns);
            $this->render_pagination($property_query, $settings, $widget_id);
            
            echo '</section>';
            echo '</div>';
            ?>

</div>
<?php
    
    $wp_query = $original_wp_query;
    wp_reset_postdata();
    $this->restore_original_query_state();
    
    unset($GLOBALS['hvnly_elementor_widget_active']);
    unset($GLOBALS['hvnly_elementor_widget_settings']);
}

    /**
     * Render search filters using existing template
     */
    private function render_search_filters(): void {
        if (function_exists('hvnly_get_template_part')) {
            add_filter('hvnly_should_show_top_search_bar', '__return_true');
            hvnly_get_template_part('search/search', 'filters');
            remove_filter('hvnly_should_show_top_search_bar', '__return_true');
        } elseif (function_exists('hvnly_get_template')) {
            add_filter('hvnly_should_show_top_search_bar', '__return_true');
            hvnly_get_template('search/search-filters.php');
            remove_filter('hvnly_should_show_top_search_bar', '__return_true');
        }
    }

    /**
     * Render filter sidebar using existing template
     *
     * @param array $settings Widget display settings.
     */
    private function render_filter_sidebar(array $settings): void {
        $defaults_filter = function (array $filters) use ($settings): array {
            if ($this->has_active_url_filters()) {
                return $filters;
            }

            if (empty($filters['min_price']) && isset($settings['default_min_price']) && $settings['default_min_price'] !== '') {
                $filters['min_price'] = (string) $settings['default_min_price'];
            }

            if (empty($filters['max_price']) && isset($settings['default_max_price']) && $settings['default_max_price'] !== '') {
                $filters['max_price'] = (string) $settings['default_max_price'];
            }

            if (empty($filters['bedrooms']) && !empty($settings['default_bedrooms'])) {
                $filters['bedrooms'] = (string) $settings['default_bedrooms'];
            }

            if (empty($filters['bathrooms']) && !empty($settings['default_bathrooms'])) {
                $filters['bathrooms'] = (string) $settings['default_bathrooms'];
            }

            if (empty($filters['hvnly_prop_depts']) && !empty($settings['default_department'])) {
                $filters['hvnly_prop_depts'] = [(string) $settings['default_department']];
            }

            return $filters;
        };

        add_filter('hvnly_filter_sidebar_current_values', $defaults_filter, 10, 1);

        if (function_exists('hvnly_get_template_part')) {
            hvnly_get_template_part('search/filter', 'sidebar');
        } elseif (function_exists('hvnly_get_template')) {
            hvnly_get_template('search/filter-sidebar.php');
        }

        remove_filter('hvnly_filter_sidebar_current_values', $defaults_filter, 10);
    }

    /**
     * Whether the request includes URL-driven filter params (SSR only).
     */
    private function has_active_url_filters(): bool {
        $keys = [
            'min_price',
            'max_price',
            'bedrooms',
            'bathrooms',
            'department',
            'type',
            'hvnly_prop_depts',
            'search',
            'address_keyword',
        ];

        foreach ($keys as $key) {
            if (!empty($_GET[$key])) {
                return true;
            }
        }

        return false;
    }

/**
 * Render view controls (sort, grid/list/map toggle) with results count
 * 
 * @param \WP_Query $property_query The property query
 * @param array $settings Widget settings
 */
private function render_view_controls(\WP_Query $property_query, array $settings, string $widget_id): void {
    $current_page = PropertyQueryArgsBuilder::resolve_paged($widget_id);
    $per_page = $property_query->get('posts_per_page');
    $total_properties = $property_query->found_posts;
    $start = $total_properties > 0 ? (($current_page - 1) * $per_page) + 1 : 0;
    $end = min($current_page * $per_page, $total_properties);
    $current_filters = function_exists('hvnly_get_current_filters') ? hvnly_get_current_filters() : [];

    ?>
<div class="hvnly-property--view--controls">
    <?php
    hvnly_get_template_part('search/result', 'count', array(
        'total_properties' => $total_properties,
        'start'            => $start,
        'end'              => $end,
        'current_filters'  => $current_filters,
    ));
    ?>
    <?php hvnly_get_template_part('archive/view', 'controls'); ?>
</div>
<?php
}

/**
 * Render property loop (the actual property cards)
 * 
 * @param \WP_Query $property_query The property query
 * @param array $settings Widget settings
 */
private function render_property_loop(\WP_Query $property_query, array $settings, string $widget_id, string $grid_id, string $map_id, string $view_type, int $columns = 2): void {
    $view_class = '';
    $display_style = '';

    if ($view_type === 'grid') {
        $view_class = 'hvnly-grid-view grid-view hvnly-columns-' . $columns;
        $display_style = 'display: grid; grid-template-columns: repeat(' . $columns . ', 1fr); --hvnly-grid-columns: ' . $columns . '; gap: 20px;';
    } elseif ($view_type === 'list') {
        $view_class = 'hvnly-list-view list-view';
        $display_style = 'display: flex; flex-direction: column; gap: 20px;';
    } elseif ($view_type === 'map') {
        $view_class = 'map-view';
        $display_style = 'display: none;';
    }

    $per_page = $property_query->get('posts_per_page');
    ?>
<div class="hvnly-property-grid-view <?php echo esc_attr($view_class); ?>"
    id="<?php echo esc_attr($grid_id); ?>"
    data-view-type="<?php echo esc_attr($view_type); ?>" data-instance-id="<?php echo esc_attr($widget_id); ?>"
    data-columns="<?php echo esc_attr($columns); ?>"
    style="<?php echo esc_attr($display_style); ?>">
    <?php if ($property_query->have_posts()) : ?>
    <?php while ($property_query->have_posts()) : $property_query->the_post(); ?>
    <?php if (function_exists('hvnly_render_property_card')) : ?>
    <?php hvnly_render_property_card(get_the_ID()); ?>
    <?php else : ?>
    <?php $this->render_fallback_property_card(); ?>
    <?php endif; ?>
    <?php endwhile; ?>
    <?php else : ?>
    <div class="hvnly-content-none-wrapper">
        <div class="hvnly-content-none">
            <div class="hvnly-content-none__icon-wrapper">
                <div class="hvnly-content-none__icon-circle">
                    <i class="fas fa-home hvnly-content-none__icon"></i>
                </div>
            </div>
            <div class="hvnly-content-none__content">
                <h2 class="hvnly-content-none__title"><?php esc_html_e('No Properties Found', 'havenlytics'); ?></h2>
                <p class="hvnly-content-none__message">
                    <?php esc_html_e('No properties match your search criteria. Try adjusting your filters or browse all properties.', 'havenlytics'); ?>
                </p>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<div id="<?php echo esc_attr($map_id); ?>" class="hvnly-map-placeholder<?php echo $view_type === 'map' ? ' is-visible' : ''; ?>"
    data-instance-id="<?php echo esc_attr($widget_id); ?>"
    style="<?php echo $view_type === 'map' ? 'display: block; min-height: 500px;' : 'display: none; min-height: 500px;'; ?>"
    data-provider="<?php echo esc_attr(function_exists('hvnly_get_map_provider') ? hvnly_get_map_provider() : 'leaflet'); ?>">
</div>
<?php
}

    /**
     * Fallback property card rendering
     */
    private function render_fallback_property_card(): void {
        $price = get_post_meta(get_the_ID(), '_hvnly_property_price', true);
        $formatted_price = function_exists('hvnly_format_price') ? hvnly_format_price($price) : '$' . number_format(floatval($price));
        $bedrooms = get_post_meta(get_the_ID(), '_hvnly_property_bedrooms', true);
        $bathrooms = get_post_meta(get_the_ID(), '_hvnly_property_bathrooms', true);
        $thumbnail = get_the_post_thumbnail_url(get_the_ID(), 'medium');
        ?>
<div class="hvnly-property-grid-list-item">
    <div class="hvnly-property-grid-list-thumb">
        <?php if ($thumbnail) : ?>
        <img src="<?php echo esc_url($thumbnail); ?>" alt="<?php the_title_attribute(); ?>">
        <?php else : ?>
        <img src="<?php echo esc_url(HVNLYNAB_ASSETS_URL . '/images/property-placeholder.jpg'); ?>"
            alt="<?php the_title_attribute(); ?>">
        <?php endif; ?>
    </div>
    <div class="hvnly-property--grid-list--single__content">
        <h3 class="hvnly-property--grid-list--title">
            <a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
        </h3>
        <?php if (!empty($price)) : ?>
        <div class="hvnly-property-price"><?php echo wp_kses_post($formatted_price); ?></div>
        <?php endif; ?>
        <div class="hvnly-property--grid-list--meta">
            <?php if (!empty($bedrooms)) : ?>
            <span><i class="fas fa-bed"></i> <?php echo esc_html($bedrooms); ?></span>
            <?php endif; ?>
            <?php if (!empty($bathrooms)) : ?>
            <span><i class="fas fa-bath"></i> <?php echo esc_html($bathrooms); ?></span>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php
    }

/**
 * Render pagination or load more button
 * 
 * @param \WP_Query $property_query The property query
 * @param array $settings Widget settings
 */
private function render_pagination(\WP_Query $property_query, array $settings, string $widget_id): void {
    if (!function_exists('hvnly_render_property_listing_pagination')) {
        return;
    }

    $current_page = PropertyQueryArgsBuilder::resolve_paged($widget_id);
    $max_pages    = (int) $property_query->max_num_pages;
    $found_posts  = (int) $property_query->found_posts;
    $per_page     = absint($settings['posts_per_page'] ?? 0);

    if ($per_page < 1) {
        $per_page = max(1, (int) $property_query->get('posts_per_page'));
    }

    if ($max_pages <= 1 && $found_posts > $per_page) {
        $max_pages = (int) ceil($found_posts / $per_page);
    }

    global $wp_query;
    $original_wp_query = $wp_query;
    $wp_query          = $property_query;

    hvnly_render_property_listing_pagination(
        [
            'current_page'          => $current_page,
            'max_pages'             => $max_pages,
            'per_page'              => $per_page,
            'found_posts'           => $found_posts,
            'instance_id'           => $widget_id,
            'pagination_wrapper_id' => 'hvnly-property-pagination-' . $widget_id,
            'wrap_load_more'        => false,
        ]
    );

    $wp_query = $original_wp_query;
}
}