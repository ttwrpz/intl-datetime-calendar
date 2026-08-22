<?php
/**
 * Converts dates at the point WordPress formats them.
 *
 * @package Intl_DateTime_Calendar
 */

namespace Intl_DateTime_Calendar\Render;

use DateTimeImmutable;
use DateTimeZone;
use Intl_DateTime_Calendar\Format\DateFormatter;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Replaces formatted dates through the wp_date filter.
 *
 * WordPress funnels every localized date through wp_date(), so hooking it
 * converts post dates, comments, archives and theme output at the source,
 * without touching rendered HTML.
 *
 * Machine-readable formats are left alone. The core date block builds its
 * datetime attribute with get_the_date('c'), which arrives here like any
 * other format, and rewriting that would publish a Buddhist year inside an
 * attribute meant to be unambiguous. The renderer passes c, r and U through.
 */
final class DateFilter {

	/**
	 * Register the filter.
	 */
	public static function register(): void {
		add_filter( 'wp_date', array( self::class, 'filter_date' ), 10, 4 );
	}

	/**
	 * Convert one formatted date.
	 *
	 * @param string            $date      Date as WordPress formatted it.
	 * @param string            $format    PHP date format string.
	 * @param int               $timestamp Unix timestamp.
	 * @param DateTimeZone|null $timezone  Timezone the date was rendered in.
	 *
	 * @return string Converted date, or the original when conversion does not apply.
	 */
	public static function filter_date( $date, $format, $timestamp, $timezone ) {
		if ( ! is_string( $date ) || ! is_string( $format ) || '' === $format || ! is_int( $timestamp ) ) {
			return $date;
		}

		if ( ! self::should_convert( $format ) ) {
			return $date;
		}

		$moment = ( new DateTimeImmutable( '@' . $timestamp ) )
			->setTimezone( $timezone instanceof DateTimeZone ? $timezone : DateFormatter::timezone() );

		$converted = DateFormatter::render( $moment, $format );

		return '' === $converted ? $date : $converted;
	}

	/**
	 * Whether a date in this context should be converted.
	 *
	 * @param string $format PHP date format string.
	 *
	 * @return bool True when the date should be rewritten.
	 */
	private static function should_convert( string $format ): bool {
		$convert = DateFormatter::can_render_server() && ! is_admin();

		/**
		 * Filters whether a formatted date is converted to the chosen calendar.
		 *
		 * Return false to leave a date in the Gregorian calendar, for example
		 * where another plugin parses the output of wp_date() rather than
		 * displaying it.
		 *
		 * @param bool   $convert Whether to convert this date.
		 * @param string $format  PHP date format string.
		 */
		return (bool) apply_filters( 'intl_datetime_calendar_should_convert', $convert, $format );
	}
}
