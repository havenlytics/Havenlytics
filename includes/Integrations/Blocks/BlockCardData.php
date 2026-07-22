<?php
/**
 * Normalized property display data for premium block cards.
 *
 * Reuses the existing backend data layer (hvnly_get_property_data, price
 * formatter, gallery SSOT) and reads taxonomies with the CORRECT registered
 * slugs (the legacy hvnly_get_property_* taxonomy helpers query wrong slugs and
 * return nothing). This is a data helper only — presentation lives in the block
 * templates.
 *
 * @package     Havenlytics
 * @subpackage  Integrations\Blocks
 * @since       3.5.0
 */

namespace HvnlyNab\Integrations\Blocks;

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Builds a normalized card-data array for a property.
 *
 * @since 3.5.0
 */
final class BlockCardData {

    /**
     * Build normalized card data for every post in a query.
     *
     * @param \WP_Query $query Executed property query.
     * @return array<int, array<string, mixed>>
     */
    public static function from_query(\WP_Query $query): array {
        $cards = [];

        foreach ((array) $query->posts as $entry) {
            $id = is_object($entry) ? (int) $entry->ID : (int) $entry;
            if ($id > 0) {
                $cards[] = self::get($id);
            }
        }

        return $cards;
    }

    /**
     * @param int $id Property post ID.
     * @return array<string, mixed>
     */
    public static function get(int $id): array {
        $base = function_exists('hvnly_get_property_data') ? (array) hvnly_get_property_data($id) : [];

        $price = function_exists('hvnly_get_property_price')
            ? (string) hvnly_get_property_price($id, true)
            : (string) ($base['price'] ?? '');

        $locations = self::term_names($id, 'hvnly_prop_locations');
        $address   = (string) ($base['address'] ?? '');

        if ('' === $address && !empty($locations)) {
            $address = implode(', ', array_slice($locations, 0, 2));
        }

        $thumb = (string) ($base['featured_image'] ?? '');

        if ('' === $thumb) {
            $gallery = isset($base['gallery_image_ids']) ? (array) $base['gallery_image_ids'] : [];
            if (!empty($gallery)) {
                $thumb = (string) wp_get_attachment_image_url((int) $gallery[0], 'large');
            }
        }

        if ('' === $thumb && defined('HVNLYNAB_ASSETS_URL')) {
            $thumb = trailingslashit(HVNLYNAB_ASSETS_URL) . 'images/placeholder.jpg';
        }

        return [
            'id'         => $id,
            'title'      => (string) ($base['title'] ?? get_the_title($id)),
            'permalink'  => (string) ($base['permalink'] ?? get_permalink($id)),
            'price'      => $price,
            'address'    => $address,
            'bedrooms'   => (string) ($base['bedrooms'] ?? ''),
            'bathrooms'  => (string) ($base['bathrooms'] ?? ''),
            'area'       => (string) ($base['area'] ?? ''),
            'is_featured' => !empty($base['is_featured']),
            'thumbnail'  => $thumb,
            'status'     => self::first_term($id, 'hvnly_prop_status'),
            'type'       => self::first_term($id, 'hvnly_prop_types'),
        ];
    }

    /**
     * @param int    $id  Post ID.
     * @param string $tax Taxonomy slug.
     * @return string First term name, or ''.
     */
    private static function first_term(int $id, string $tax): string {
        $names = self::term_names($id, $tax);

        return $names[0] ?? '';
    }

    /**
     * @param int    $id  Post ID.
     * @param string $tax Taxonomy slug.
     * @return array<int, string>
     */
    private static function term_names(int $id, string $tax): array {
        $terms = wp_get_post_terms($id, $tax, ['fields' => 'names']);

        return (!is_wp_error($terms) && is_array($terms)) ? array_values($terms) : [];
    }
}
