<?php
/**
 * Server render callback for the HVN: Property Inquiry Form block.
 *
 * Frontend entry point ONLY. It renders the existing Contact Agent inquiry form
 * partial and lets the existing Contact Agent JS submit it to the existing AJAX
 * endpoint. Storage, validation, spam protection, rate limiting, emails, lead
 * creation, agent assignment and the success/error UI all belong to the existing
 * \HvnlyNab\ContactAgent module — nothing is duplicated here.
 *
 * @package     Havenlytics
 * @subpackage  Integrations\Blocks
 * @since       3.5.0
 */

namespace HvnlyNab\Integrations\Blocks;

// Exit if accessed directly.
if ( ! defined('ABSPATH')) {
    exit;
}

/**
 * Property Inquiry Form block renderer.
 *
 * @since 3.5.0
 */
final class PropertyInquiryBlockRenderer {

    /**
     * Property post type.
     */
    private const POST_TYPE = 'hvnly_property';

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

        $is_editor = defined('REST_REQUEST') && REST_REQUEST;

        $enabled = ! function_exists('hvnly_is_contact_agent_enabled') || hvnly_is_contact_agent_enabled();
        if ( ! $enabled) {
            return $is_editor ? self::notice(__('The Contact Agent system is disabled in Havenlytics settings, so this inquiry form will not appear on the frontend.', 'havenlytics')) : '';
        }

        $property_source = self::choice(
            (string) ( $attributes['propertySource'] ?? 'current' ),
            array( 'current', 'selected', 'multiple', 'manual', 'none' ),
            'current'
        );
        $agent_source    = self::choice(
            (string) ( $attributes['agentSource'] ?? 'auto' ),
            array( 'auto', 'selected', 'multiple', 'current', 'manual', 'none' ),
            'auto'
        );
        $layout          = self::choice(
            (string) ( $attributes['layout'] ?? 'vertical' ),
            array( 'vertical', 'horizontal', 'two-column', 'compact', 'card', 'minimal', 'floating' ),
            'vertical'
        );

        $property_choices = self::resolve_property_choices($attributes, $property_source, $is_editor);
        $property_id      = ! empty($property_choices) ? (int) $property_choices[0]['id'] : 0;

        if ($property_id <= 0) {
            if ($is_editor || ( function_exists('current_user_can') && current_user_can('edit_posts') )) {
                return self::notice(__('Select or set a property for this inquiry form (the Havenlytics inquiry system routes every message to a property’s agent).', 'havenlytics'));
            }
            return '';
        }

        $agents = self::resolve_agents(
            $property_id,
            $agent_source,
            (int) ( $attributes['agentId'] ?? 0 ),
            self::int_list($attributes['agentIds'] ?? array())
        );

        $property_title = in_array($property_source, array( 'none' ), true)
            ? ''
            : (string) get_the_title($property_id);

        $show_title   = ! isset($attributes['showTitle']) || ! empty($attributes['showTitle']);
        $form_title   = sanitize_text_field( (string) ( $attributes['formTitle'] ?? __('Send an Inquiry', 'havenlytics') ));
        $show_desc    = ! empty($attributes['showDescription']);
        $form_desc    = sanitize_text_field( (string) ( $attributes['formDescription'] ?? '' ));
        $form_width   = max(0, (int) ( $attributes['formWidth'] ?? 0 ));
        $show_consent = ! empty($attributes['showConsent']);
        $consent_text = sanitize_text_field( (string) ( $attributes['consentText'] ?? '' ));

        $show_image   = ! empty($attributes['showPropertyImage']);
        $show_price   = ! empty($attributes['showPropertyPrice']);
        $show_address = ! empty($attributes['showPropertyAddress']);
        $show_agent   = ! empty($attributes['showAgentCard']);

        $show_name    = ! isset($attributes['showName']) || ! empty($attributes['showName']);
        $show_email   = ! isset($attributes['showEmail']) || ! empty($attributes['showEmail']);
        $show_phone   = ! isset($attributes['showPhone']) || ! empty($attributes['showPhone']);
        $show_message = ! isset($attributes['showMessage']) || ! empty($attributes['showMessage']);

        $button_text  = sanitize_text_field( (string) ( $attributes['buttonText'] ?? '' ));
        $button_width = self::choice( (string) ( $attributes['buttonWidth'] ?? 'auto' ), array( 'auto', 'full' ), 'auto');
        $button_align = self::choice( (string) ( $attributes['buttonAlign'] ?? 'left' ), array( 'left', 'center', 'right' ), 'left');
        $button_icon  = self::choice( (string) ( $attributes['buttonIcon'] ?? 'none' ), array( 'none', 'send', 'envelope', 'check' ), 'none');

        $show_loading = ! isset($attributes['showLoadingState']) || ! empty($attributes['showLoadingState']);
        $show_success = ! isset($attributes['showSuccessState']) || ! empty($attributes['showSuccessState']);
        $success_msg  = sanitize_text_field( (string) ( $attributes['successMessage'] ?? '' ));
        // Match Authentication block: allow only validated (typically same-host) redirects.
        $success_url = wp_validate_redirect(
            esc_url_raw(trim( (string) ( $attributes['successRedirectUrl'] ?? '' ))),
            ''
        );

        self::enqueue_contact_agent_assets($property_id);

        $wrapper_classes = array(
            'hvnly-block-inquiry',
            'hvnly-block-inquiry--' . $layout,
            'hvnly-block-inquiry--btn-' . $button_width,
            'hvnly-block-inquiry--btn-align-' . $button_align,
        );

        if ( ! $show_name) {
            $wrapper_classes[] = 'hvnly-block-inquiry--hide-name';
        }
        if ( ! $show_email) {
            $wrapper_classes[] = 'hvnly-block-inquiry--hide-email';
        }
        if ( ! $show_phone) {
            $wrapper_classes[] = 'hvnly-block-inquiry--hide-phone';
        }
        if ( ! $show_message) {
            $wrapper_classes[] = 'hvnly-block-inquiry--hide-message';
        }
        if ( ! $show_loading) {
            $wrapper_classes[] = 'hvnly-block-inquiry--no-loading';
        }
        if ( ! $show_success) {
            $wrapper_classes[] = 'hvnly-block-inquiry--no-success';
        }

        $wrapper_style = $form_width > 0 ? 'max-width:' . $form_width . 'px;' : '';

        $extra_attrs = array();
        if ('' !== $success_msg) {
            $extra_attrs['data-hvnly-success-message'] = $success_msg;
        }
        if ('' !== $success_url) {
            $extra_attrs['data-hvnly-success-redirect'] = $success_url;
        }

        $wrapper = function_exists('get_block_wrapper_attributes')
            ? get_block_wrapper_attributes(array_merge(
                array_filter(array(
                    'class' => implode(' ', $wrapper_classes),
                    'style' => $wrapper_style,
                )),
                $extra_attrs
            ))
            : 'class="' . esc_attr(implode(' ', $wrapper_classes)) . '"';

        $header = self::build_property_header($property_id, $show_image, $show_price, $show_address);

        $template_args = array(
            'wrapper'            => $wrapper,
            'layout'             => $layout,
            'show_title'         => $show_title,
            'form_title'         => $form_title,
            'show_description'   => $show_desc,
            'form_description'   => $form_desc,
            'show_consent'       => $show_consent,
            'consent_text'       => $consent_text,
            'is_editor'          => $is_editor,
            'property_choices'   => $property_choices,
            'property_header'    => $header,
            'show_agent_card'    => $show_agent,
            'button_text'        => $button_text,
            'button_icon'        => $button_icon,
            'form_args'          => array(
                'property_id'    => $property_id,
                'property_title' => $property_title,
                'agents'         => $agents,
                'agent'          => ! empty($agents) ? $agents[0] : array(),
            ),
        );

        ob_start();
        hvnly_get_template_part('blocks/property-inquiry', null, $template_args);

        return (string) ob_get_clean();
    }

    /**
     * Build optional property header data (image / price / address).
     *
     * @param int  $property_id Property id.
     * @param bool $show_image  Show featured image.
     * @param bool $show_price  Show price.
     * @param bool $show_address Show address.
     * @return array<string, mixed>
     */
    private static function build_property_header( int $property_id, bool $show_image, bool $show_price, bool $show_address ): array {
        $header = array(
            'show'    => false,
            'image'   => '',
            'title'   => (string) get_the_title($property_id),
            'price'   => '',
            'address' => '',
            'url'     => (string) get_permalink($property_id),
        );

        if ($show_image) {
            $thumb = get_the_post_thumbnail_url($property_id, 'medium_large');
            if (is_string($thumb) && '' !== $thumb) {
                $header['image'] = $thumb;
                $header['show']  = true;
            }
        }

        if ($show_price) {
            $raw = get_post_meta($property_id, '_hvnly_property_price', true);
            if ('' !== (string) $raw && function_exists('hvnly_format_price')) {
                $header['price'] = (string) hvnly_format_price($raw);
                $header['show']  = true;
            } elseif ('' !== (string) $raw) {
                $header['price'] = (string) $raw;
                $header['show']  = true;
            }
        }

        if ($show_address) {
            $line1 = (string) get_post_meta($property_id, '_hvnly_property_address_line_1', true);
            $city  = (string) get_post_meta($property_id, '_hvnly_property_town_city', true);
            $parts = array_filter(array( $line1, $city ));
            if ( ! empty($parts)) {
                $header['address'] = implode(', ', $parts);
                $header['show']    = true;
            }
        }

        return $header;
    }

    /**
     * Enqueue Contact Agent assets + block chrome/enhancer.
     *
     * @param int $property_id Resolved property id.
     * @return void
     */
    private static function enqueue_contact_agent_assets( int $property_id ): void {
        $ver = defined('HVNLYNAB_VERSION') ? HVNLYNAB_VERSION : '3.5.0';

        if ( ! wp_style_is('hvnly-frontend-contact-agent', 'registered')) {
            wp_register_style(
                'hvnly-frontend-contact-agent',
                HVNLYNAB_ASSETS_URL . '/frontend/css/hvnly-frontend-contact-agent.css',
                array(),
                $ver
            );
        }

        if ( ! wp_script_is('hvnly-frontend-contact-agent', 'registered')) {
            wp_register_script(
                'hvnly-frontend-contact-agent',
                HVNLYNAB_ASSETS_URL . '/frontend/js/hvnly-frontend-contact-agent.js',
                array( 'jquery' ),
                $ver,
                true
            );
        }

        wp_enqueue_style('hvnly-frontend-contact-agent');

        if ( ! wp_style_is('hvnly-block-inquiry', 'registered')) {
            wp_register_style(
                'hvnly-block-inquiry',
                HVNLYNAB_ASSETS_URL . '/frontend/blocks/css/hvnly-block-inquiry.css',
                array( 'hvnly-frontend-contact-agent' ),
                $ver
            );
        }
        wp_enqueue_style('hvnly-block-inquiry');
        wp_enqueue_script('hvnly-frontend-contact-agent');

        if ( ! wp_script_is('hvnly-block-inquiry', 'registered')) {
            $path  = 'frontend/blocks/js/hvnly-block-inquiry.js';
            $mtime = is_readable(trailingslashit(HVNLYNAB_ASSETS_PATH) . $path)
                ? (int) filemtime(trailingslashit(HVNLYNAB_ASSETS_PATH) . $path)
                : 0;
            wp_register_script(
                'hvnly-block-inquiry',
                HVNLYNAB_ASSETS_URL . '/frontend/blocks/js/hvnly-block-inquiry.js',
                array( 'hvnly-frontend-contact-agent' ),
                $mtime > 0 ? $ver . '.' . $mtime : $ver,
                true
            );
        }
        wp_enqueue_script('hvnly-block-inquiry');

        if (function_exists('hvnly_localize_contact_agent_script')) {
            hvnly_localize_contact_agent_script($property_id);
        }
    }

    /**
     * Resolve one or more published property choices for the form.
     *
     * @param array  $attributes Block attributes.
     * @param string $source     Property source.
     * @param bool   $is_editor  Editor preview.
     * @return array<int, array{id:int,title:string}>
     */
    private static function resolve_property_choices( array $attributes, string $source, bool $is_editor ): array {
        $ids = array();

        switch ($source) {
            case 'selected':
            case 'manual':
                $ids = array( self::valid_property( (int) ( $attributes['propertyId'] ?? 0 )) );
                break;

            case 'multiple':
                $ids = array_map(array( self::class, 'valid_property' ), self::int_list($attributes['propertyIds'] ?? array()));
                break;

            case 'none':
                $ids = array( self::valid_property( (int) ( $attributes['fallbackPropertyId'] ?? 0 )) );
                break;

            case 'current':
            default:
                $current = self::current_property_id();
                if ($current <= 0) {
                    $current = self::valid_property( (int) ( $attributes['fallbackPropertyId'] ?? 0 ));
                }
                $ids = array( $current );
                break;
        }

        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));

        if (empty($ids) && $is_editor) {
            $sample = self::sample_property_id();
            if ($sample > 0) {
                $ids = array( $sample );
            }
        }

        $out = array();
        foreach ($ids as $id) {
            $out[] = array(
                'id'    => $id,
                'title' => (string) get_the_title($id),
            );
        }

        return $out;
    }

    /**
     * Detect a property from the current request context.
     *
     * @return int
     */
    private static function current_property_id(): int {
        if (function_exists('is_singular') && is_singular(self::POST_TYPE)) {
            return (int) get_the_ID();
        }

        if (function_exists('get_queried_object')) {
            $obj = get_queried_object();
            if ($obj instanceof \WP_Post && $obj->post_type === self::POST_TYPE) {
                return (int) $obj->ID;
            }
        }

        $current = (int) ( function_exists('get_the_ID') ? get_the_ID() : 0 );
        if ($current > 0 && get_post_type($current) === self::POST_TYPE) {
            return $current;
        }

        return 0;
    }

    /**
     * Resolve agents for the form dropdown / primary recipient.
     *
     * @param int    $property_id Property id.
     * @param string $agent_source Source mode.
     * @param int    $agent_id    Single agent id.
     * @param int[]  $agent_ids   Multiple agent ids.
     * @return array<int, array<string, mixed>>
     */
    private static function resolve_agents( int $property_id, string $agent_source, int $agent_id, array $agent_ids ): array {
        if ('none' === $agent_source) {
            return array();
        }

        if ('multiple' === $agent_source) {
            $agents = array();
            foreach ($agent_ids as $id) {
                $profile = self::agent_profile($id);
                if ( ! empty($profile)) {
                    $agents[] = $profile;
                }
            }
            return array_values($agents);
        }

        if (in_array($agent_source, array( 'selected', 'manual' ), true) && $agent_id > 0) {
            $profile = self::agent_profile($agent_id);
            if ( ! empty($profile)) {
                // Prefer property agents with the chosen agent promoted first.
                $property_agents = self::property_agents($property_id);
                return self::promote_agent($property_agents ?: array( $profile ), $agent_id);
            }
        }

        $agents = self::property_agents($property_id);

        if ('current' === $agent_source) {
            $current = self::current_agent_id();
            if ($current > 0) {
                $agents = self::promote_agent($agents, $current);
                if (empty($agents)) {
                    $profile = self::agent_profile($current);
                    if ( ! empty($profile)) {
                        $agents = array( $profile );
                    }
                }
            }
        }

        return array_values($agents);
    }

    /**
     * Property-assigned agents via existing helpers.
     *
     * @param int $property_id Property id.
     * @return array<int, array<string, mixed>>
     */
    private static function property_agents( int $property_id ): array {
        $agents = function_exists('hvnly_get_property_agents')
            ? (array) hvnly_get_property_agents($property_id)
            : array();

        if (empty($agents) && function_exists('hvnly_get_property_agent')) {
            $primary = hvnly_get_property_agent($property_id);
            if (is_array($primary) && ! empty($primary)) {
                $agents = array( $primary );
            }
        }

        return array_values($agents);
    }

    /**
     * Load a single agent profile via existing helper.
     *
     * @param int $agent_id Agent CPT id.
     * @return array<string, mixed>
     */
    private static function agent_profile( int $agent_id ): array {
        if ($agent_id <= 0) {
            return array();
        }

        if (function_exists('hvnly_is_valid_agent') && ! hvnly_is_valid_agent($agent_id)) {
            return array();
        }

        if (function_exists('hvnly_get_agent')) {
            $profile = hvnly_get_agent($agent_id);
            return is_array($profile) ? $profile : array();
        }

        return array();
    }

    /**
     * Promote an agent id to the front of the list when present.
     *
     * @param array<int, array<string, mixed>> $agents Agents.
     * @param int                              $agent_id Id to promote.
     * @return array<int, array<string, mixed>>
     */
    private static function promote_agent( array $agents, int $agent_id ): array {
        if ($agent_id <= 0 || empty($agents)) {
            return $agents;
        }

        $front = array();
        $rest  = array();
        foreach ($agents as $agent) {
            if ( (int) ( $agent['id'] ?? 0 ) === $agent_id) {
                $front[] = $agent;
            } else {
                $rest[] = $agent;
            }
        }

        return ! empty($front) ? array_merge($front, $rest) : $agents;
    }

    /**
     * Current agent CPT id when viewing an agent singular page.
     *
     * @return int
     */
    private static function current_agent_id(): int {
        $agent_type = 'hvnly_agent';
        if (class_exists('\\HvnlyNab\\Agent\\AgentConstants')) {
            $agent_type = \HvnlyNab\Agent\AgentConstants::POST_TYPE;
        }

        if (function_exists('is_singular') && is_singular($agent_type)) {
            return (int) get_the_ID();
        }

        if (function_exists('get_queried_object')) {
            $obj = get_queried_object();
            if ($obj instanceof \WP_Post && $obj->post_type === $agent_type) {
                return (int) $obj->ID;
            }
        }

        return 0;
    }

    /**
     * Normalize an attribute list to positive ints.
     *
     * @param mixed $value Attribute value.
     * @return int[]
     */
    private static function int_list( $value ): array {
        if ( ! is_array($value)) {
            return array();
        }

        return array_values(array_unique(array_filter(array_map('intval', $value))));
    }

    /**
     * A published property id, or 0.
     *
     * @param int $id Candidate id.
     * @return int
     */
    private static function valid_property( int $id ): int {
        if ($id <= 0) {
            return 0;
        }

        if (get_post_type($id) !== self::POST_TYPE) {
            return 0;
        }

        return ( get_post_status($id) === 'publish' ) ? $id : 0;
    }

    /**
     * Most recent published property (editor preview only).
     *
     * @return int
     */
    private static function sample_property_id(): int {
        $query = new \WP_Query(array(
            'post_type'              => self::POST_TYPE,
            'post_status'            => 'publish',
            'posts_per_page'         => 1,
            'orderby'                => 'date',
            'order'                  => 'DESC',
            'fields'                 => 'ids',
            'no_found_rows'          => true,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
        ));

        $ids = array_map('intval', (array) $query->posts);

        return ! empty($ids) ? $ids[0] : 0;
    }

    /**
     * Render a simple admin/editor notice.
     *
     * @param string $message Notice text.
     * @return string
     */
    private static function notice( string $message ): string {
        $wrapper = function_exists('get_block_wrapper_attributes')
            ? get_block_wrapper_attributes(array( 'class' => 'hvnly-block-inquiry hvnly-block-inquiry--notice' ))
            : 'class="hvnly-block-inquiry hvnly-block-inquiry--notice"';

        return '<div ' . $wrapper . '><p class="hvnly-block-inquiry__notice">' . esc_html($message) . '</p></div>';
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
