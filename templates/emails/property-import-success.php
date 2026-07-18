<?php
/**
 * Property setup success email.
 *
 * Sprint 24C: retrofitted onto the shared email layout. Slug and context
 * variables ($user_name, $intro, $import_count, $properties_url, $site_name)
 * are unchanged.
 *
 * @package Havenlytics
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$hvnly_count = isset( $import_count ) ? (string) $import_count : '0';
$hvnly_intro = isset( $intro ) ? (string) $intro : '';

$hvnly_vars = array(
	'title'             => __( 'Your property setup is complete', 'havenlytics' ),
	'preheader'         => $hvnly_intro,
	'user_name'         => isset( $user_name ) ? (string) $user_name : '',
	'intro'             => $hvnly_intro,
	'detail_rows'       => array(
		__( 'Listings ready', 'havenlytics' ) => $hvnly_count,
	),
	'cta_url'           => isset( $properties_url ) ? (string) $properties_url : '',
	'cta_label'         => __( 'View your properties', 'havenlytics' ),
	'footnote'          => '',
	'brand_logo_url'    => isset( $brand_logo_url ) ? (string) $brand_logo_url : '',
	'brand_support_url' => isset( $brand_support_url ) ? (string) $brand_support_url : '',
	'brand_docs_url'    => isset( $brand_docs_url ) ? (string) $brand_docs_url : '',
	'brand_version'     => isset( $brand_version ) ? (string) $brand_version : '',
	'brand_site_name'   => isset( $brand_site_name ) ? (string) $brand_site_name : ( isset( $site_name ) ? (string) $site_name : '' ),
	'brand_site_url'    => isset( $brand_site_url ) ? (string) $brand_site_url : '',
);

hvnly_get_template( 'emails/partials/layout-head.php', $hvnly_vars );
hvnly_get_template( 'emails/partials/notice-body.php', $hvnly_vars );
hvnly_get_template( 'emails/partials/layout-foot.php', $hvnly_vars );
