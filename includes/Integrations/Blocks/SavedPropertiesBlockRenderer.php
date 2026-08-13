<?php
/**
 * Server render callback for the HVN: Saved Properties block.
 *
 * Presentation layer ONLY. The saved/favorited list is sourced entirely from the
 * existing Favorites module, every card is rendered by the Property Card Builder,
 * the signed-out gate reuses the Authentication block, and the favorite toggle /
 * remove behaviour is the existing hvnly-favorites.js. This renderer introduces
 * no favorites storage, query, REST, AJAX or card logic of its own.
 *
 * Backend reused:
 * - Saved list ....... \HvnlyNab\Favorites\FavoritesRepository::get_page() (custom table, cached, sorted)
 * - Enabled flag ..... hvnly_is_favorites_enabled()
 * - Property card .... hvnly_render_property_card() (Property Card Builder SSOT)
 * - Carousel shell ... templates/blocks/carousel.php (arbitrary WP_Query)
 * - Signed-out gate .. \HvnlyNab\Integrations\Blocks\AuthenticationBlockRenderer::render()
 * - Page resolution .. \HvnlyNab\Frontend\Query\PropertyQueryArgsBuilder::resolve_paged()
 *
 * @package     Havenlytics
 * @subpackage  Integrations\Blocks
 * @since       3.5.0
 */

namespace HvnlyNab\Integrations\Blocks;

use HvnlyNab\Favorites\FavoritesService;
use HvnlyNab\Frontend\Query\PropertyQueryArgsBuilder;
use WP_Query;

// Exit if accessed directly.
if ( ! defined('ABSPATH')) {
    exit;
}

/**
 * Saved Properties block renderer.
 *
 * @since 3.5.0
 */
final class SavedPropertiesBlockRenderer {

    /**
     * Map the block's ordering choice onto the existing Favorites sort enum.
     *
     * Every pair uses a value FavoritesRepository::get_page() already understands
     * (date_added|title|price|date_published × ASC|DESC) — no new sorting logic.
     *
     * @var array<string, array{0:string,1:string}>
     */
    private const ORDER_MAP = array(
        'newest'           => array( 'date_added', 'DESC' ),
        'oldest'           => array( 'date_added', 'ASC' ),
        'recently_updated' => array( 'date_published', 'DESC' ),
        'title'            => array( 'title', 'ASC' ),
        'price'            => array( 'price', 'ASC' ),
        'default'          => array( 'date_added', 'DESC' ),
    );

    /**
     * Render the block.
     *
     * @param array  $attributes Block attributes (validated against block.json).
     * @param string $content    Inner content (unused — dynamic block).
     * @param object $block      Block instance (unused).
     * @return string
     */
    public static function render( $attributes = array(), string $content = '', $block = null ): string {
        unset($content, $block);

        if ( ! function_exists('hvnly_get_template_part')) {
            return '';
        }

        $attributes = is_array($attributes) ? $attributes : array();

        $layout   = self::choice( (string) ( $attributes['layout'] ?? 'grid' ), array( 'grid', 'list', 'compact', 'carousel' ), 'grid');
        $columns  = self::clamp( (int) ( $attributes['columns'] ?? 3 ), 1, 6, 3);
        $per_page = self::clamp( (int) ( $attributes['postsPerPage'] ?? 12 ), 1, 48, 12);
        $order_by = self::choice( (string) ( $attributes['orderby'] ?? 'newest' ), array_keys(self::ORDER_MAP), 'newest');
        $pager    = self::choice( (string) ( $attributes['paginationMode'] ?? 'numbered' ), array( 'numbered', 'none' ), 'numbered');
        $out_mode = self::choice( (string) ( $attributes['loggedOutMode'] ?? 'auth' ), array( 'auth', 'message' ), 'auth');

        $show_title   = ! isset($attributes['showTitle']) || ! empty($attributes['showTitle']);
        $section_ttl  = sanitize_text_field( (string) ( $attributes['sectionTitle'] ?? __('Saved Properties', 'havenlytics') ));
        $show_desc    = ! empty($attributes['showDescription']);
        $section_desc = sanitize_text_field( (string) ( $attributes['sectionDescription'] ?? '' ));
        $empty_btn    = sanitize_text_field( (string) ( $attributes['emptyButtonText'] ?? __('Browse Properties', 'havenlytics') ));

        // Carousel layout never paginates (it scrolls a single set).
        if ('carousel' === $layout) {
            $pager = 'none';
        }

        // A stable, unique instance id keeps pagination state per block.
        $block_id = 'hvnly-block-saved-' . substr(md5(wp_json_encode($attributes)), 0, 8);

        // Editor / REST preview context — the render_callback runs through the
        // REST endpoint for the native (non-iframe) editor preview.
        $is_editor = defined('REST_REQUEST') && REST_REQUEST;

        $favorites_enabled = ! function_exists('hvnly_is_favorites_enabled') || hvnly_is_favorites_enabled();

        // Signed-out visitors get the reused Authentication block gate (never a
        // second login form). In the editor the admin is logged in, so the real
        // preview path runs instead.
        if ( ! is_user_logged_in()) {
            return self::render_gate($block_id, $out_mode, $show_title, $section_ttl);
        }

        [$repo_orderby, $repo_order] = self::ORDER_MAP[ $order_by ];

        // Resolve the current page from the same per-widget key the shared
        // resolver reads, so numbered links map straight back here.
        $page     = 1;
        $page_key = 'hvnly_paged_' . sanitize_key($block_id);
        if ('numbered' === $pager && class_exists(PropertyQueryArgsBuilder::class)) {
            $page = PropertyQueryArgsBuilder::resolve_paged($block_id, array(), 1);
        }

        $ids       = array();
        $total     = 0;
        $is_sample = false;

        if ($favorites_enabled && class_exists(FavoritesService::class)) {
            $service   = new FavoritesService();
            $page_data = $service->repository()->get_page(
                get_current_user_id(),
                array(
                    'page'     => $page,
                    'per_page' => $per_page,
                    'orderby'  => $repo_orderby,
                    'order'    => $repo_order,
                )
            );

            $ids   = isset($page_data['ids']) && is_array($page_data['ids']) ? $page_data['ids'] : array();
            $total = (int) ( $page_data['total'] ?? 0 );
        }

        // In the editor, when the current user has nothing saved, preview a few
        // recent listings so the layout is visible (clearly a sample).
        if (empty($ids) && $is_editor) {
            $ids = self::sample_property_ids($per_page);
            if ( ! empty($ids)) {
                $is_sample = true;
                $total     = count($ids);
                $pager     = 'none';
            }
        }

        $query = empty($ids) ? null : self::build_query($ids);

        $total_pages = ( $per_page > 0 ) ? (int) ceil($total / $per_page) : 0;

        $wrapper_classes = array(
            'hvnly-block-saved',
            'hvnly-block-saved--' . $layout,
        );

        $wrapper = function_exists('get_block_wrapper_attributes')
            ? get_block_wrapper_attributes(array(
                'class'        => implode(' ', $wrapper_classes),
                'id'           => $block_id,
                'style'        => '--hvnly-grid-columns: ' . $columns . ';',
                'data-columns' => (string) $columns,
            ))
            : 'class="' . esc_attr(implode(' ', $wrapper_classes)) . '" id="' . esc_attr($block_id) . '"';

        $template_args = array(
            'context'             => 'list',
            'wrapper'             => $wrapper,
            'block_id'            => $block_id,
            'layout'              => $layout,
            'columns'             => $columns,
            'query'               => $query,
            'show_title'          => $show_title,
            'section_title'       => $section_ttl,
            'show_description'    => $show_desc,
            'section_description' => $section_desc,
            'pager'               => $pager,
            'page_key'            => $page_key,
            'current_page'        => $page,
            'per_page'            => $per_page,
            'total'               => $total,
            'total_pages'         => $total_pages,
            'is_sample'           => $is_sample,
            'empty_button_text'   => $empty_btn,
            'browse_url'          => self::browse_url(),
            'carousel'            => array(
                'visible'   => self::clamp( (int) ( $attributes['visibleSlides'] ?? 3 ), 1, 5, 3),
                'autoplay'  => ! empty($attributes['autoplay']),
                'show_nav'  => ! isset($attributes['showNav']) || ! empty($attributes['showNav']),
                'show_dots' => ! isset($attributes['showDots']) || ! empty($attributes['showDots']),
            ),
        );

        ob_start();
        hvnly_get_template_part('blocks/saved-properties', null, $template_args);

        return (string) ob_get_clean();
    }

    /**
     * Render the signed-out gate by reusing the Authentication block.
     *
     * @param string $block_id     Block instance id.
     * @param string $mode         auth|message.
     * @param bool   $show_title   Whether a heading should show.
     * @param string $section_ttl  Section title.
     * @return string
     */
    private static function render_gate( string $block_id, string $mode, bool $show_title, string $section_ttl ): string {
        $auth_html = '';

        if (class_exists(AuthenticationBlockRenderer::class)) {
            $auth_html = AuthenticationBlockRenderer::render(array(
                'authMode'         => 'login',
                'afterLogin'       => 'current',
                'showRegisterLink' => true,
                'layout'           => 'card',
                'cardAlign'        => 'center',
            ));
        }

        $wrapper = function_exists('get_block_wrapper_attributes')
            ? get_block_wrapper_attributes(array(
                'class' => 'hvnly-block-saved hvnly-block-saved--gate',
                'id'    => $block_id,
            ))
            : 'class="hvnly-block-saved hvnly-block-saved--gate" id="' . esc_attr($block_id) . '"';

        $template_args = array(
            'context'       => 'gate',
            'wrapper'       => $wrapper,
            'block_id'      => $block_id,
            'gate_mode'     => $mode,
            'show_title'    => $show_title,
            'section_title' => $section_ttl,
            'auth_html'     => $auth_html,
            'login_url'     => wp_login_url(self::current_url()),
            'register_url'  => self::register_url(),
        );

        ob_start();
        hvnly_get_template_part('blocks/saved-properties', null, $template_args);

        return (string) ob_get_clean();
    }

    /**
     * Build the display WP_Query for a page of favorite ids, preserving order.
     *
     * Mirrors FavoritesService::hydrate(): one query, post__in order, caches
     * primed in bulk — no per-card meta/term lookups (no N+1).
     *
     * @param int[] $ids Property ids in display order.
     * @return \WP_Query
     */
    private static function build_query( array $ids ): WP_Query {
        return new WP_Query(array(
            'post_type'              => FavoritesService::POST_TYPE,
            'post_status'            => 'publish',
            'post__in'               => $ids,
            'orderby'                => 'post__in',
            'posts_per_page'         => count($ids),
            'ignore_sticky_posts'    => true,
            'no_found_rows'          => true,
            'update_post_meta_cache' => true,
            'update_post_term_cache' => true,
        ));
    }

    /**
     * A few recent published properties for the editor preview only.
     *
     * @param int $limit Max ids.
     * @return int[]
     */
    private static function sample_property_ids( int $limit ): array {
        $post_type = class_exists(FavoritesService::class) ? FavoritesService::POST_TYPE : 'hvnly_property';

        $query = new WP_Query(array(
            'post_type'              => $post_type,
            'post_status'            => 'publish',
            'posts_per_page'         => max(1, min(12, $limit)),
            'orderby'                => 'date',
            'order'                  => 'DESC',
            'fields'                 => 'ids',
            'no_found_rows'          => true,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
        ));

        return array_map('intval', (array) $query->posts);
    }

    /**
     * Archive URL for the empty-state "browse" button (filterable).
     *
     * @return string
     */
    private static function browse_url(): string {
        $post_type = class_exists(FavoritesService::class) ? FavoritesService::POST_TYPE : 'hvnly_property';
        $url       = get_post_type_archive_link($post_type);

        if ( ! is_string($url) || '' === $url) {
            $url = home_url('/');
        }

        /**
         * Filter the Saved Properties empty-state browse URL.
         *
         * @since 3.5.0
         *
         * @param string $url Browse URL.
         */
        return (string) apply_filters('hvnly_saved_properties_browse_url', $url);
    }

    /**
     * Registration URL, preferring the Workspace route when available.
     *
     * @return string
     */
    private static function register_url(): string {
        if (class_exists(\HvnlyNab\Workspace\WorkspaceSettings::class)
            && \HvnlyNab\Workspace\WorkspaceSettings::is_registration_enabled()
        ) {
            $url = \HvnlyNab\Workspace\WorkspaceSettings::route_url('register');
            if (is_string($url) && '' !== $url) {
                return $url;
            }
        }

        return wp_registration_url();
    }

    /**
     * Current front-end URL (used as the login redirect target).
     *
     * @return string
     */
    private static function current_url(): string {
        $permalink = get_permalink();

        if (is_string($permalink) && '' !== $permalink) {
            return $permalink;
        }

        return home_url(add_query_arg(array()));
    }

    /**
     * Clamp an integer into a range with a fallback.
     *
     * @param int $value   Raw value.
     * @param int $min     Minimum.
     * @param int $max     Maximum.
     * @param int $default Fallback when out of range.
     * @return int
     */
    private static function clamp( int $value, int $min, int $max, int $default ): int {
        if ($value < $min || $value > $max) {
            return $default;
        }

        return $value;
    }

    /**
     * Return $value if it is in $allowed, else $default.
     *
     * @param string   $value   Candidate.
     * @param string[] $allowed Allowed values.
     * @param string   $default Fallback.
     * @return string
     */
    private static function choice( string $value, array $allowed, string $default ): string {
        return in_array($value, $allowed, true) ? $value : $default;
    }
}
