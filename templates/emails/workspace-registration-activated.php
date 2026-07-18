<?php
/**
 * You are back in — Workspace registration lifecycle email.
 *
 * Sprint 24C: retrofitted onto the shared email layout. The template slug and its
 * context variables ($user_name, $intro, $workspace_url, $site_name) are unchanged,
 * so existing theme overrides and the `hvnly_email_templates` map keep working.
 *
 * @package Havenlytics
 * @var string $user_name
 * @var string $intro
 * @var string $workspace_url
 * @var string $site_name
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$hvnly_intro = isset( $intro ) ? (string) $intro : '';
$hvnly_cta   = true && isset( $workspace_url ) ? (string) $workspace_url : '';

$hvnly_vars = array(
	'title'             => isset( $title ) ? (string) $title : __( 'You are back in', 'havenlytics' ),
	'preheader'         => isset( $preheader ) ? (string) $preheader : $hvnly_intro,
	'user_name'         => isset( $user_name ) ? (string) $user_name : '',
	'intro'             => $hvnly_intro,
	'detail_rows'       => isset( $detail_rows ) && is_array( $detail_rows ) ? $detail_rows : array(),
	'cta_url'           => isset( $cta_url ) ? (string) $cta_url : $hvnly_cta,
	'cta_label'         => isset( $cta_label ) ? (string) $cta_label : __( 'Sign in to your workspace', 'havenlytics' ),
	'footnote'          => isset( $footnote ) ? (string) $footnote : '',
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
