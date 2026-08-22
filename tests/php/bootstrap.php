<?php
/**
 * Minimal WordPress stubs, so the formatting code can be tested without a site.
 *
 * The site timezone defaults to something other than UTC on purpose: the
 * timezone bugs this suite guards against are invisible on a UTC site.
 *
 * @package Intl_DateTime_Calendar
 */

define( 'ABSPATH', dirname( __DIR__, 2 ) . '/' );

// WordPress always runs PHP itself in UTC and tracks the site zone separately.
date_default_timezone_set( 'UTC' );

$GLOBALS['intl_test_options'] = array(
	'date_format' => 'F j, Y',
	'time_format' => 'g:i a',
	'timezone'    => 'Asia/Bangkok',
);

$GLOBALS['intl_test_filters'] = array();

function get_option( $name, $default = false ) {
	return $GLOBALS['intl_test_options'][ $name ] ?? $default;
}

function intl_test_set_option( $name, $value ) {
	$GLOBALS['intl_test_options'][ $name ] = $value;
}

function wp_timezone() {
	return new DateTimeZone( $GLOBALS['intl_test_options']['timezone'] );
}

function get_locale() {
	return $GLOBALS['intl_test_options']['locale'] ?? 'en_US';
}

function determine_locale() {
	// Inside wp-admin WordPress answers with the editor's own language.
	return $GLOBALS['intl_test_options']['user_locale'] ?? get_locale();
}

function apply_filters( $tag, $value ) {
	$args = array_slice( func_get_args(), 1 );

	foreach ( $GLOBALS['intl_test_filters'][ $tag ] ?? array() as $callback ) {
		$args[0] = call_user_func_array( $callback, $args );
	}

	return $args[0];
}

function add_filter( $tag, $callback ) {
	$GLOBALS['intl_test_filters'][ $tag ][] = $callback;
}

function add_shortcode( $tag, $callback ) {}

function is_admin() {
	return false;
}

function shortcode_atts( $pairs, $atts ) {
	$out = array();

	foreach ( $pairs as $name => $default ) {
		$out[ $name ] = array_key_exists( $name, (array) $atts ) ? $atts[ $name ] : $default;
	}

	return $out;
}

function wp_strip_all_tags( $text ) {
	return trim( strip_tags( (string) $text ) );
}

function esc_attr( $text ) {
	return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
}

function esc_html( $text ) {
	return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
}

require_once ABSPATH . 'src/Format/FormatSpec.php';
require_once ABSPATH . 'src/Format/CalendarRenderer.php';
require_once ABSPATH . 'src/Settings/Options.php';
require_once ABSPATH . 'src/Format/DateFormatter.php';
require_once ABSPATH . 'src/Render/Shortcode.php';
require_once ABSPATH . 'src/Render/DateFilter.php';
