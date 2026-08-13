<?php
/**
 * Keep stored UI labels as English msgids; translate only at render time.
 *
 * @package Havenlytics
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Run a callback with the Havenlytics text domain forced to English.
 *
 * Use around code that builds defaults destined for options/post meta so
 * `__()` never persists a translated string into storage.
 *
 * @param callable $callback Callback.
 * @return mixed Callback return value.
 */
function hvnly_with_english_ui( callable $callback ) {
	static $depth = 0;

	$should_switch = ( 0 === $depth );
	$switched      = false;

	if ( $should_switch && function_exists( 'switch_to_locale' ) ) {
		$locale = function_exists( 'determine_locale' ) ? determine_locale() : get_locale();
		if ( is_string( $locale ) && $locale !== '' && 0 !== stripos( $locale, 'en' ) ) {
			$switched = switch_to_locale( 'en_US' );
			if ( $switched ) {
				hvnly_reload_textdomain();
			}
		}
	}

	++$depth;

	try {
		return $callback();
	} finally {
		--$depth;
		if ( $switched ) {
			restore_previous_locale();
			hvnly_reload_textdomain();
		}
	}
}

/**
 * Reload the Havenlytics text domain for the active locale.
 *
 * @return void
 */
function hvnly_reload_textdomain() {
	if ( ! function_exists( 'load_plugin_textdomain' ) || ! defined( 'HVNLYNAB_BASENAME' ) ) {
		return;
	}

	unload_textdomain( 'havenlytics' );
	load_plugin_textdomain(
		'havenlytics',
		false,
		dirname( HVNLYNAB_BASENAME ) . '/languages'
	);
}

/**
 * Reverse map of translated UI strings → English msgids from plugin MO files.
 *
 * @return array<string, string>
 */
function hvnly_ui_translation_reverse_map() {
	static $map = null;

	if ( null !== $map ) {
		return $map;
	}

	$cached = get_transient( 'hvnly_ui_label_reverse_map' );
	if ( is_array( $cached ) ) {
		$map = $cached;
		return $map;
	}

	$map = array();

	if ( ! defined( 'HVNLYNAB_LANG_DIR' ) || ! is_dir( HVNLYNAB_LANG_DIR ) ) {
		return $map;
	}

	if ( ! class_exists( 'MO', false ) ) {
		require_once ABSPATH . WPINC . '/pomo/mo.php';
	}

	$files = glob( trailingslashit( HVNLYNAB_LANG_DIR ) . 'havenlytics-*.mo' );
	if ( ! is_array( $files ) ) {
		$files = array();
	}

	foreach ( $files as $file ) {
		$mo = new MO();
		if ( ! $mo->import_from_file( $file ) ) {
			continue;
		}
		foreach ( $mo->entries as $entry ) {
			if ( ! is_object( $entry ) ) {
				continue;
			}
			$msgid  = isset( $entry->singular ) ? (string) $entry->singular : '';
			$msgstr = '';
			if ( ! empty( $entry->translations ) && is_array( $entry->translations ) ) {
				$msgstr = (string) $entry->translations[0];
			}
			if ( '' === $msgid || '' === $msgstr || $msgid === $msgstr ) {
				continue;
			}
			// Prefer first msgid if two share a translation.
			if ( ! isset( $map[ $msgstr ] ) ) {
				$map[ $msgstr ] = $msgid;
			}
		}
	}

	set_transient( 'hvnly_ui_label_reverse_map', $map, WEEK_IN_SECONDS );

	return $map;
}

/**
 * Normalize a stored UI string back to its English msgid when it matches a known translation.
 *
 * @param string $text Stored label/title/placeholder.
 * @return string
 */
function hvnly_canonicalize_ui_label( $text ) {
	$text = is_string( $text ) ? $text : '';
	if ( '' === $text ) {
		return '';
	}

	$map = hvnly_ui_translation_reverse_map();
	return isset( $map[ $text ] ) ? $map[ $text ] : $text;
}

/**
 * Recursively canonicalize translated UI strings inside builder/settings trees.
 *
 * @param mixed $data Option/config tree.
 * @return mixed
 */
function hvnly_canonicalize_ui_tree( $data ) {
	if ( is_string( $data ) ) {
		return hvnly_canonicalize_ui_label( $data );
	}

	if ( ! is_array( $data ) ) {
		return $data;
	}

	foreach ( $data as $key => $value ) {
		$data[ $key ] = hvnly_canonicalize_ui_tree( $value );
	}

	return $data;
}

/**
 * pre_update_option_* callback: keep English msgids in builder/settings storage.
 *
 * @param mixed $value Option value.
 * @return mixed
 */
function hvnly_pre_update_canonicalize_option( $value ) {
	if ( ! is_array( $value ) ) {
		return $value;
	}
	return hvnly_canonicalize_ui_tree( $value );
}

/**
 * Clear locale-sensitive caches when the site language changes.
 *
 * @return void
 */
function hvnly_invalidate_locale_caches() {
	delete_transient( 'hvnly_ui_label_reverse_map' );

	// Allow re-canonicalization after language-driven option corruption.
	delete_option( 'hvnly_ui_labels_canonical_v1' );

	if ( function_exists( 'hvnly_clear_sidebar_cache' ) ) {
		hvnly_clear_sidebar_cache();
	}
	if ( function_exists( 'hvnly_clear_cache' ) ) {
		hvnly_clear_cache();
	}

	$option_keys = array(
		'hvnly_property_builder.sections',
		'hvnly_property_card.sections',
		'hvnly_plugin_settings',
	);
	foreach ( $option_keys as $key ) {
		wp_cache_delete( $key, 'options' );
	}
}

/**
 * One-time: rewrite persisted translated default labels back to English msgids.
 *
 * @return void
 */
function hvnly_maybe_normalize_stored_ui_labels() {
	if ( get_option( 'hvnly_ui_labels_canonical_v1' ) ) {
		return;
	}

	$keys = array(
		'hvnly_property_builder.sections',
		'hvnly_property_card.sections',
		'hvnly_plugin_settings',
	);

	foreach ( $keys as $key ) {
		$value = get_option( $key, null );
		if ( ! is_array( $value ) ) {
			continue;
		}
		$fixed = hvnly_canonicalize_ui_tree( $value );
		if ( wp_json_encode( $fixed ) !== wp_json_encode( $value ) ) {
			update_option( $key, $fixed, false );
			wp_cache_delete( $key, 'options' );
		}
	}

	update_option( 'hvnly_ui_labels_canonical_v1', '1', false );
}
