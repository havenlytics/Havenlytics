<?php
/**
 * Listing lifecycle notice email — submitted, approved, published, rejected.
 *
 * Sprint 24C. One template for every email of this shape (heading + message +
 * optional detail rows + one CTA); the wording and CTA arrive in the context.
 * Override at {theme}/havenlytics/emails/property-notice.php, or point an individual email
 * type at its own template via the `hvnly_email_templates` filter.
 *
 * NOTE: variables are listed explicitly rather than via get_defined_vars(),
 * because hvnly_get_template() extract()s $args into scope — so get_defined_vars()
 * would also capture $args, $located and $template_name, and re-nest the whole
 * context on every partial hop.
 *
 * @package Havenlytics
 * @since   3.2.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$hvnly_vars = array(
	'title'             => isset( $title ) ? (string) $title : '',
	'preheader'         => isset( $preheader ) ? (string) $preheader : '',
	'user_name'         => isset( $user_name ) ? (string) $user_name : '',
	'intro'             => isset( $intro ) ? (string) $intro : '',
	'detail_rows'       => isset( $detail_rows ) && is_array( $detail_rows ) ? $detail_rows : array(),
	'cta_url'           => isset( $cta_url ) ? (string) $cta_url : '',
	'cta_label'         => isset( $cta_label ) ? (string) $cta_label : '',
	'footnote'          => isset( $footnote ) ? (string) $footnote : '',
	'brand_logo_url'    => isset( $brand_logo_url ) ? (string) $brand_logo_url : '',
	'brand_support_url' => isset( $brand_support_url ) ? (string) $brand_support_url : '',
	'brand_docs_url'    => isset( $brand_docs_url ) ? (string) $brand_docs_url : '',
	'brand_version'     => isset( $brand_version ) ? (string) $brand_version : '',
	'brand_site_name'   => isset( $brand_site_name ) ? (string) $brand_site_name : '',
	'brand_site_url'    => isset( $brand_site_url ) ? (string) $brand_site_url : '',
);

hvnly_get_template( 'emails/partials/layout-head.php', $hvnly_vars );
hvnly_get_template( 'emails/partials/notice-body.php', $hvnly_vars );
hvnly_get_template( 'emails/partials/layout-foot.php', $hvnly_vars );
