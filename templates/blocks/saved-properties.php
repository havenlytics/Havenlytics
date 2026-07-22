<?php
/**
 * HVN: Saved Properties block markup.
 *
 * Presentation only. Every card is the Property Card Builder via
 * hvnly_render_property_card(); the signed-out gate is the reused Authentication
 * block; the favorite / remove control is the existing hvnly-favorites.js. No
 * favorites, query or card logic lives here.
 *
 * @package     Havenlytics
 * @subpackage  Templates/blocks
 * @since       3.5.0
 *
 * @var array $args Provided by SavedPropertiesBlockRenderer::render().
 */

if (!defined('ABSPATH')) {
    exit;
}

$hvnly_a = (isset($args) && is_array($args)) ? $args : array();

$hvnly_context = isset($hvnly_a['context']) ? (string) $hvnly_a['context'] : 'list';
$hvnly_wrapper = isset($hvnly_a['wrapper']) ? $hvnly_a['wrapper'] : 'class="hvnly-block-saved"';

$hvnly_show_title    = !empty($hvnly_a['show_title']);
$hvnly_section_title = isset($hvnly_a['section_title']) ? (string) $hvnly_a['section_title'] : '';

/**
 * Small header helper — title + optional description, shown for both states.
 */
$hvnly_render_header = static function () use ($hvnly_show_title, $hvnly_section_title, $hvnly_a) {
    $show_desc    = !empty($hvnly_a['show_description']);
    $section_desc = isset($hvnly_a['section_description']) ? (string) $hvnly_a['section_description'] : '';

    if (!$hvnly_show_title && !($show_desc && '' !== $section_desc)) {
        return;
    }

    echo '<header class="hvnly-block-saved__header">';
    if ($hvnly_show_title && '' !== $hvnly_section_title) {
        echo '<h2 class="hvnly-block-saved__title">' . esc_html($hvnly_section_title) . '</h2>';
    }
    if ($show_desc && '' !== $section_desc) {
        echo '<p class="hvnly-block-saved__description">' . esc_html($section_desc) . '</p>';
    }
    echo '</header>';
};

/* -------------------------------------------------------------------------
 * Signed-out gate — reuse the Authentication block (no second login form).
 * ---------------------------------------------------------------------- */
if ('gate' === $hvnly_context) :
    $hvnly_gate_mode    = isset($hvnly_a['gate_mode']) ? (string) $hvnly_a['gate_mode'] : 'auth';
    $hvnly_auth_html    = isset($hvnly_a['auth_html']) ? (string) $hvnly_a['auth_html'] : '';
    $hvnly_login_url    = isset($hvnly_a['login_url']) ? (string) $hvnly_a['login_url'] : '';
    $hvnly_register_url = isset($hvnly_a['register_url']) ? (string) $hvnly_a['register_url'] : '';
    ?>
    <div <?php echo $hvnly_wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
        <?php $hvnly_render_header(); ?>
        <div class="hvnly-block-saved__gate">
            <?php if ('auth' === $hvnly_gate_mode && '' !== $hvnly_auth_html) : ?>
                <p class="hvnly-block-saved__gate-lead"><?php esc_html_e('Sign in to view your saved properties.', 'havenlytics'); ?></p>
                <?php echo $hvnly_auth_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            <?php else : ?>
                <div class="hvnly-block-saved__gate-panel">
                    <span class="hvnly-block-saved__gate-icon" aria-hidden="true">
                        <i class="far fa-heart"></i>
                    </span>
                    <h3 class="hvnly-block-saved__gate-title"><?php esc_html_e('Sign in required', 'havenlytics'); ?></h3>
                    <p class="hvnly-block-saved__gate-text"><?php esc_html_e('Sign in to see the properties you have saved.', 'havenlytics'); ?></p>
                    <div class="hvnly-block-saved__gate-actions">
                        <?php if ('' !== $hvnly_login_url) : ?>
                            <a class="hvnly-block-saved__btn hvnly-block-saved__btn--primary" href="<?php echo esc_url($hvnly_login_url); ?>"><?php esc_html_e('Sign in', 'havenlytics'); ?></a>
                        <?php endif; ?>
                        <?php if ('' !== $hvnly_register_url) : ?>
                            <a class="hvnly-block-saved__btn hvnly-block-saved__btn--ghost" href="<?php echo esc_url($hvnly_register_url); ?>"><?php esc_html_e('Register', 'havenlytics'); ?></a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php
    return;
endif;

/* -------------------------------------------------------------------------
 * Signed-in list.
 * ---------------------------------------------------------------------- */
$hvnly_layout      = isset($hvnly_a['layout']) ? (string) $hvnly_a['layout'] : 'grid';
$hvnly_columns     = isset($hvnly_a['columns']) ? (int) $hvnly_a['columns'] : 3;
$hvnly_query       = (isset($hvnly_a['query']) && $hvnly_a['query'] instanceof WP_Query) ? $hvnly_a['query'] : null;
$hvnly_is_sample   = !empty($hvnly_a['is_sample']);
$hvnly_browse_url  = isset($hvnly_a['browse_url']) ? (string) $hvnly_a['browse_url'] : '';
$hvnly_empty_btn   = isset($hvnly_a['empty_button_text']) ? (string) $hvnly_a['empty_button_text'] : __('Browse Properties', 'havenlytics');
?>
<div <?php echo $hvnly_wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
    <?php $hvnly_render_header(); ?>

    <?php if (!$hvnly_query instanceof WP_Query || !$hvnly_query->have_posts()) : ?>

        <div class="hvnly-block-saved__empty">
            <span class="hvnly-block-saved__empty-icon" aria-hidden="true">
                <i class="far fa-heart"></i>
            </span>
            <h3 class="hvnly-block-saved__empty-title"><?php esc_html_e('No saved properties yet', 'havenlytics'); ?></h3>
            <p class="hvnly-block-saved__empty-text"><?php esc_html_e('Select the heart on any listing to save it here for later.', 'havenlytics'); ?></p>
            <?php if ('' !== $hvnly_browse_url && '' !== $hvnly_empty_btn) : ?>
                <a class="hvnly-block-saved__btn hvnly-block-saved__btn--primary" href="<?php echo esc_url($hvnly_browse_url); ?>"><?php echo esc_html($hvnly_empty_btn); ?></a>
            <?php endif; ?>
        </div>

    <?php elseif ('carousel' === $hvnly_layout) : ?>

        <?php
        $hvnly_carousel = isset($hvnly_a['carousel']) && is_array($hvnly_a['carousel']) ? $hvnly_a['carousel'] : array();
        hvnly_get_template_part(
            'blocks/carousel',
            null,
            array(
                'query'          => $hvnly_query,
                'header'         => array('show' => false),
                'visible'        => isset($hvnly_carousel['visible']) ? (int) $hvnly_carousel['visible'] : 3,
                'visible_tablet' => 2,
                'visible_mobile' => 1,
                'autoplay'       => !empty($hvnly_carousel['autoplay']),
                'center'         => false,
                'show_nav'       => !isset($hvnly_carousel['show_nav']) || !empty($hvnly_carousel['show_nav']),
                'show_dots'      => !isset($hvnly_carousel['show_dots']) || !empty($hvnly_carousel['show_dots']),
            )
        );
        ?>

    <?php else : ?>

        <?php
        // Grid / List / Compact: loop the favorites query and render each card
        // through the Property Card Builder — the same call the archive loop,
        // Featured and Carousel blocks use. Container classes mirror the archive
        // grid so card-embed sizing + the image un-blur JS apply unchanged.
        if ('list' === $hvnly_layout) {
            $hvnly_view_class    = 'hvnly-list-view list-view';
            $hvnly_display_style = 'display: flex; flex-direction: column; gap: 20px;';
        } else {
            // grid + compact both use a CSS grid; compact tightens via block CSS.
            $hvnly_view_class    = 'hvnly-grid-view grid-view';
            $hvnly_display_style = sprintf(
                'display: grid; grid-template-columns: repeat(%1$d, minmax(0, 1fr)); gap: 20px; --hvnly-grid-columns: %1$d;',
                max(1, $hvnly_columns)
            );
        }
        ?>
        <section class="hvnly-property--grid--listings hvnly-block-saved__listings" data-columns="<?php echo esc_attr((string) $hvnly_columns); ?>" style="--hvnly-grid-columns: <?php echo esc_attr((string) $hvnly_columns); ?>;">
            <div class="hvnly-property-grid-view <?php echo esc_attr($hvnly_view_class); ?>" data-view-type="<?php echo esc_attr('list' === $hvnly_layout ? 'list' : 'grid'); ?>" style="<?php echo esc_attr($hvnly_display_style); ?>">
                <?php
                while ($hvnly_query->have_posts()) :
                    $hvnly_query->the_post();
                    hvnly_render_property_card(get_the_ID());
                endwhile;
                wp_reset_postdata();
                ?>
            </div>
        </section>

        <?php
        /* Numbered pagination — reload-based anchors (favorites lists are
         * user-specific and must never route through the shared archive AJAX).
         * Uses its own item classes so the archive pagination JS never binds. */
        if (!$hvnly_is_sample && 'numbered' === (string) ($hvnly_a['pager'] ?? 'none')) :
            $hvnly_total_pages  = (int) ($hvnly_a['total_pages'] ?? 0);
            $hvnly_current_page = max(1, (int) ($hvnly_a['current_page'] ?? 1));
            $hvnly_page_key     = (string) ($hvnly_a['page_key'] ?? '');
            $hvnly_block_id     = (string) ($hvnly_a['block_id'] ?? '');
            $hvnly_total        = (int) ($hvnly_a['total'] ?? 0);
            $per_page     = max(1, (int) ($hvnly_a['per_page'] ?? 12));

            if ($hvnly_total_pages > 1 && '' !== $hvnly_page_key) :
                $hvnly_anchor    = '' !== $hvnly_block_id ? '#' . $hvnly_block_id : '';
                $hvnly_base_url  = remove_query_arg($hvnly_page_key);

                $hvnly_page_link = static function ($n) use ($hvnly_page_key, $hvnly_base_url, $hvnly_anchor) {
                    $n = max(1, (int) $n);
                    $url = 1 === $n ? remove_query_arg($hvnly_page_key, $hvnly_base_url) : add_query_arg($hvnly_page_key, $n, $hvnly_base_url);
                    return esc_url($url . $hvnly_anchor);
                };

                $hvnly_start = max(1, $hvnly_current_page - 2);
                $hvnly_end   = min($hvnly_total_pages, $hvnly_current_page + 2);
                if ($hvnly_current_page <= 2) {
                    $hvnly_end = min(5, $hvnly_total_pages);
                }
                if ($hvnly_current_page >= $hvnly_total_pages - 1) {
                    $hvnly_start = max(1, $hvnly_total_pages - 4);
                }

                $hvnly_range_start = (($hvnly_current_page - 1) * $per_page) + 1;
                $hvnly_range_end   = min($hvnly_current_page * $per_page, $hvnly_total);
                ?>
                <nav class="hvnly-block-saved__pagination" role="navigation" aria-label="<?php esc_attr_e('Saved properties pagination', 'havenlytics'); ?>">
                    <p class="hvnly-block-saved__pagination-info">
                        <?php
                        echo esc_html(
                            sprintf(
                                /* translators: 1: first item, 2: last item, 3: total saved. */
                                __('Showing %1$d-%2$d of %3$d saved properties', 'havenlytics'),
                                $hvnly_range_start,
                                $hvnly_range_end,
                                $hvnly_total
                            )
                        );
                        ?>
                    </p>
                    <div class="hvnly-block-saved__pages">
                        <?php if ($hvnly_current_page > 1) : ?>
                            <a class="hvnly-block-saved__page hvnly-block-saved__page--prev" href="<?php echo $hvnly_page_link($hvnly_current_page - 1); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>" aria-label="<?php esc_attr_e('Previous page', 'havenlytics'); ?>">
                                <i class="fas fa-chevron-left" aria-hidden="true"></i>
                            </a>
                        <?php endif; ?>

                        <?php for ($hvnly_i = $hvnly_start; $hvnly_i <= $hvnly_end; $hvnly_i++) : ?>
                            <?php if ($hvnly_i === $hvnly_current_page) : ?>
                                <span class="hvnly-block-saved__page is-active" aria-current="page"><?php echo esc_html((string) $hvnly_i); ?></span>
                            <?php else : ?>
                                <a class="hvnly-block-saved__page" href="<?php echo $hvnly_page_link($hvnly_i); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>"><?php echo esc_html((string) $hvnly_i); ?></a>
                            <?php endif; ?>
                        <?php endfor; ?>

                        <?php if ($hvnly_current_page < $hvnly_total_pages) : ?>
                            <a class="hvnly-block-saved__page hvnly-block-saved__page--next" href="<?php echo $hvnly_page_link($hvnly_current_page + 1); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>" aria-label="<?php esc_attr_e('Next page', 'havenlytics'); ?>">
                                <i class="fas fa-chevron-right" aria-hidden="true"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                </nav>
                <?php
            endif;
        endif;
        ?>

    <?php endif; ?>
</div>
