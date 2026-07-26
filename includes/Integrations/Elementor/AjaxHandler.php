<?php
/**
 * Elementor AJAX Handler for Load More Properties
 *
 * @package     Havenlytics
 * @subpackage  Integrations\Elementor
 * @since       3.0.0
 */

namespace HvnlyNab\Integrations\Elementor;

use HvnlyNab\Frontend\Query\PropertyQueryArgsBuilder;
use HvnlyNab\Frontend\Query\PropertyQueryExecutor;

if (!defined('ABSPATH')) {
    exit;
}

class AjaxHandler {

    public function __construct() {
        add_action('wp_ajax_hvnly_elementor_load_more_properties', [$this, 'load_more_properties']);
        add_action('wp_ajax_nopriv_hvnly_elementor_load_more_properties', [$this, 'load_more_properties']);
    }

    public function load_more_properties(): void {
        // Verify nonce
        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
        if (!wp_verify_nonce($nonce, 'hvnly_ajax_request')) {
            wp_send_json_error('Security check failed.');
        }

        try {
            // Get and sanitize parameters
            $page = isset($_POST['page']) ? absint($_POST['page']) : 1;
            $per_page = isset($_POST['per_page']) ? absint($_POST['per_page']) : 12;
            $widget_id = isset($_POST['widget_id']) ? sanitize_text_field(wp_unslash($_POST['widget_id'])) : '';
            $orderby = isset($_POST['orderby']) ? sanitize_text_field(wp_unslash($_POST['orderby'])) : 'date';
            $featured_only = isset($_POST['featured_only']) ? sanitize_text_field(wp_unslash($_POST['featured_only'])) : 'no';
            $view_type = isset($_POST['view_type']) ? sanitize_text_field(wp_unslash($_POST['view_type'])) : 'grid';

            // Build query args and execute via shared cache layer
            $query_data = $_POST;
            if ($orderby !== '') {
                $query_data['orderby'] = $orderby;
            }
            if (PropertyQueryArgsBuilder::is_featured_only(['featured_only' => $featured_only])) {
                $query_data['featured_only'] = 'yes';
            }

            $properties = PropertyQueryExecutor::query($query_data, $page, $per_page, [
                'filter_context' => 'elementor_load_more',
                'widget_id'        => $widget_id,
            ]);

            if (!$properties->have_posts()) {
                wp_send_json_success([
                    'html' => '<div class="hvnly-all-properties-no-results"><p>' . esc_html__('No more properties found.', 'havenlytics') . '</p></div>',
                    'has_more' => false,
                    'current_page' => $page,
                    'max_pages' => $properties->max_num_pages,
                ]);
            }

            // Generate HTML
            ob_start();
            $this->render_properties($properties, $view_type, $page, $per_page);
            $html = ob_get_clean();

            $has_more = $page < $properties->max_num_pages;

            // Generate updated results bars
            $results_bar_top = $this->render_results_bar_html($properties, 'top');
            $results_bar_bottom = $this->render_results_bar_html($properties, 'bottom');

            wp_send_json_success([
                'html' => $html,
                'results_bar_top' => $results_bar_top,
                'results_bar_bottom' => $results_bar_bottom,
                'has_more' => $has_more,
                'current_page' => $page,
                'max_pages' => $properties->max_num_pages,
                'found_posts' => $properties->found_posts,
                'post_count' => $properties->post_count,
            ]);

        } catch (\Exception $e) {
            wp_send_json_error('An error occurred while loading more properties.');
        }
    }

    private function render_properties(\WP_Query $properties, string $view_type, int $page, int $per_page): void {
        $counter = ($page - 1) * $per_page;
        
        while ($properties->have_posts()) {
            $properties->the_post();
            $counter++;
            
            if (function_exists('hvnly_render_property_card')) {
                if ($view_type === 'list') {
                    echo '<div class="hvnly-property-list-item-wrapper">';
                    hvnly_render_property_card(get_the_ID());
                    echo '</div>';
                } else {
                    hvnly_render_property_card(get_the_ID());
                }
            } else {
                $this->render_fallback_property_card(get_the_ID(), $view_type, $counter);
            }
        }
        wp_reset_postdata();
    }

    private function render_fallback_property_card(int $property_id, string $view_type = 'grid', int $index = 0): void {
        $price = get_post_meta($property_id, '_hvnly_property_price', true);
        $formatted_price = function_exists('hvnly_format_price') ? hvnly_format_price($price) : '$' . number_format(floatval($price));
        $bedrooms = get_post_meta($property_id, '_hvnly_property_bedrooms', true);
        $bathrooms = get_post_meta($property_id, '_hvnly_property_bathrooms', true);
        $thumbnail = function_exists('hvnly_get_property_thumbnail_url')
            ? hvnly_get_property_thumbnail_url($property_id, 'medium')
            : (string) get_the_post_thumbnail_url($property_id, 'medium');
        $permalink = get_permalink($property_id);
        $title = get_the_title($property_id);
        $card_variant = ($index % 2 == 0) ? 'even' : 'odd';
        $gallery_order = ($property_id % 2 == 0) ? 'ASC' : 'DESC';
        $list_class = $view_type === 'list' ? 'hvnly-list-view-item' : '';
        ?>
<div class="hvnly-property-grid-list-item <?php echo esc_attr($list_class); ?> hvnly-card-variant--<?php echo esc_attr($card_variant); ?>"
    data-property-id="<?php echo esc_attr($property_id); ?>"
    data-gallery-order="<?php echo esc_attr($gallery_order); ?>">
    <div class="hvnly-property--grid-list--single__thumb">
        <img src="<?php echo esc_url($thumbnail); ?>" alt="<?php echo esc_attr($title); ?>" loading="lazy">
    </div>
    <div class="hvnly-property--grid-list--single__content">
        <div class="hvnly-property--grid-list--single__title">
            <a href="<?php echo esc_url($permalink); ?>"><?php echo esc_html($title); ?></a>
        </div>
        <?php if (!empty($price)) : ?>
        <div class="hvnly-property-price"><?php echo wp_kses_post($formatted_price); ?></div>
        <?php endif; ?>
        <div class="hvnly-property--grid-list--single__meta">
            <?php if (!empty($bedrooms)) : ?>
            <span class="hvnly-meta-item"><i class="fas fa-bed"></i> <?php echo esc_html($bedrooms); ?></span>
            <?php endif; ?>
            <?php if (!empty($bathrooms)) : ?>
            <span class="hvnly-meta-item"><i class="fas fa-bath"></i> <?php echo esc_html($bathrooms); ?></span>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php
    }

    private function build_query_args(array $data, int $page, int $per_page, string $orderby, string $featured_only): array {
        if ($orderby !== '') {
            $data['orderby'] = $orderby;
        }

        if (PropertyQueryArgsBuilder::is_featured_only(['featured_only' => $featured_only])) {
            $data['featured_only'] = 'yes';
        }

        return PropertyQueryArgsBuilder::build(
            $data,
            $page,
            $per_page,
            ['filter_context' => 'elementor_load_more']
        );
    }

    private function render_results_bar_html(\WP_Query $properties, string $position): string {
        $total = $properties->found_posts;
        $per_page = $properties->get('posts_per_page');
        $paged = max(1, $properties->get('paged'));
        $start = (($paged - 1) * $per_page) + 1;
        $end = min($paged * $per_page, $total);
        $showing_all = ($total <= $per_page);

        // Get current department for display
        $current_department = '';
        $tax_query = $properties->get('tax_query');
        if (!empty($tax_query)) {
            foreach ($tax_query as $tax_item) {
                if (isset($tax_item['taxonomy']) && $tax_item['taxonomy'] === 'hvnly_prop_depts' && !empty($tax_item['terms'])) {
                    $term = get_term_by('slug', $tax_item['terms'], 'hvnly_prop_depts');
                    if ($term && !is_wp_error($term)) {
                        $current_department = $term->name;
                    }
                }
            }
        }

        ob_start();
        ?>
<div class="hvnly-all-properties-results-bar hvnly-results-bar--<?php echo esc_attr($position); ?>">
    <div class="hvnly-results-bar__inner">
        <div class="hvnly-results-info">
            <?php if ($showing_all) : ?>
            <span class="hvnly-results-info__text hvnly-results-info__text--all">
                <i class="fas fa-home"></i>
                <?php
                /* translators: %d: total number of properties. */
                printf( esc_html( _n( 'Showing %d property', 'Showing all %d properties', $total, 'havenlytics' ) ), intval( $total ) );
                ?>
            </span>
            <?php else : ?>
            <span class="hvnly-results-info__text hvnly-results-info__text--range">
                <i class="fas fa-chart-line"></i>
                <?php
                /* translators: 1: first result number, 2: last result number, 3: total results. */
                printf( esc_html__( 'Showing %1$d–%2$d of %3$d results', 'havenlytics' ), intval( $start ), intval( $end ), intval( $total ) );
                ?>
            </span>
            <?php endif; ?>

            <?php if (!empty($current_department)) : ?>
            <span class="hvnly-results-badge">
                <i class="fas fa-building"></i> <?php echo esc_html($current_department); ?>
            </span>
            <?php endif; ?>
        </div>

        <div class="hvnly-results-actions">
            <?php if ($total > 0) : ?>
            <span class="hvnly-results-stats">
                <i class="fas fa-chart-simple"></i>
                <?php echo esc_html($end - $start + 1); ?> <?php esc_html_e('properties shown', 'havenlytics'); ?>
            </span>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php
        return ob_get_clean();
    }
}

// Initialize AJAX handler
new AjaxHandler();