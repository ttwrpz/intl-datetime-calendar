<?php
/**
 * Plugin Name: Intl DateTime Calendar
 * Plugin URI: https://github.com/ttwrpz/intl-datetime-calendar
 * Description: Display dates and times in any calendar system, with the digits and wording your readers expect.
 * Version: 2.0.3
 * Requires PHP: 8.1
 * Requires at least: 6.5
 * Tested up to: 7.1
 * Author: ttwrpz
 * Author URI: https://github.com/ttwrpz
 * Text Domain: intl-datetime-calendar
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 *
 * @package Intl_DateTime_Calendar
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Current plugin version.
 */
define( 'INTL_DATETIME_CALENDAR_VERSION', '2.0.3' );

/**
 * Absolute path to this file, used to build asset URLs.
 */
define( 'INTL_DATETIME_CALENDAR_FILE', __FILE__ );

/**
 * Load plugin classes on demand.
 *
 * A small PSR-4 loader avoids shipping a vendor directory to every site.
 *
 * @param string $class_name Fully qualified class name.
 */
spl_autoload_register(
	static function ( $class_name ) {
		$prefix = 'Intl_DateTime_Calendar\\';

		if ( 0 !== strpos( $class_name, $prefix ) ) {
			return;
		}

		$relative = substr( $class_name, strlen( $prefix ) );
		$path     = __DIR__ . '/src/' . str_replace( '\\', '/', $relative ) . '.php';

		if ( is_readable( $path ) ) {
			require_once $path;
		}
	}
);

/**
 * Format a date for display in the configured calendar.
 *
 * For themes and other plugins. Documented in the FAQ since 1.0.2 but only
 * implemented in 2.0.0, so guard calls with function_exists().
 *
 * @param string|int|DateTimeInterface $date   Date to format. Defaults to now.
 * @param string                       $format PHP date format. Defaults to the site format.
 *
 * @return string Time element markup, or an escaped fallback when unreadable.
 */
function intl_datetime_calendar_format_date( $date = 'now', $format = '' ) {
	$timezone = \Intl_DateTime_Calendar\Format\DateFormatter::timezone();

	try {
		if ( $date instanceof DateTimeInterface ) {
			$moment = DateTimeImmutable::createFromInterface( $date );
		} elseif ( is_int( $date ) ) {
			$moment = ( new DateTimeImmutable( '@' . $date ) )->setTimezone( $timezone );
		} else {
			$moment = new DateTimeImmutable( (string) $date, $timezone );
		}
	} catch ( Exception $e ) {
		return esc_html( is_scalar( $date ) ? (string) $date : '' );
	}

	if ( '' === $format ) {
		$format = (string) get_option( 'date_format', 'F j, Y' );
	}

	return \Intl_DateTime_Calendar\Render\Shortcode::element( $moment, (string) $format );
}

\Intl_DateTime_Calendar\Plugin::boot();
