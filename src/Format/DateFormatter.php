<?php
/**
 * The formatting entry point used by the rest of the plugin.
 *
 * @package Intl_DateTime_Calendar
 */

namespace Intl_DateTime_Calendar\Format;

use DateTimeImmutable;
use DateTimeZone;
use Intl_DateTime_Calendar\Settings\Options;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resolves what to render, and renders it on the server where possible.
 *
 * Server rendering is what removes the flash of the wrong calendar. Without
 * it the page arrives Gregorian and the browser rewrites it a moment later,
 * which is visible on a slow connection and never happens at all without
 * JavaScript, in a feed, or for anything scraping the page. The browser
 * stays as the fallback for hosts lacking ext-intl.
 */
final class DateFormatter {

	/**
	 * Class attached to every element the plugin manages.
	 */
	public const CSS_CLASS = 'intl-datetime-element';

	/**
	 * Formats that mean "describe this as elapsed time" rather than a date.
	 *
	 * WordPress uses this value for the core date block's relative option.
	 */
	public const RELATIVE_FORMAT = 'human-diff';

	/**
	 * Cached renderers, keyed by locale, calendar and numbering system.
	 *
	 * @var array<string, CalendarRenderer>
	 */
	private static array $renderers = array();

	/**
	 * Whether the server can render dates itself.
	 *
	 * @return bool True when ext-intl is present and server rendering is on.
	 */
	public static function can_render_server(): bool {
		return CalendarRenderer::is_available() && (bool) Options::get( 'server_render' );
	}

	/**
	 * The site's timezone.
	 *
	 * WordPress runs PHP in UTC and keeps the site's real zone separately, so
	 * this is the only correct source for turning a stored date into an instant.
	 *
	 * @return DateTimeZone Site timezone.
	 */
	public static function timezone(): DateTimeZone {
		return function_exists( 'wp_timezone' ) ? wp_timezone() : new DateTimeZone( 'UTC' );
	}

	/**
	 * The locale dates should be written in, as a BCP 47 tag.
	 *
	 * @return string Locale such as 'th-TH'.
	 */
	public static function locale(): string {
		$locale = function_exists( 'determine_locale' ) ? determine_locale() : get_locale();

		/**
		 * Filters the locale used to format dates.
		 *
		 * @param string $locale BCP 47 locale tag.
		 */
		return (string) apply_filters(
			'intl_datetime_calendar_locale',
			str_replace( '_', '-', $locale )
		);
	}

	/**
	 * The calendar dates should be written in.
	 *
	 * @return string ICU calendar identifier.
	 */
	public static function calendar(): string {
		$locale   = function_exists( 'determine_locale' ) ? determine_locale() : get_locale();
		$calendar = Options::calendar_for_locale( $locale );

		/**
		 * Filters the calendar system used to format dates.
		 *
		 * @param string $calendar ICU calendar identifier.
		 * @param string $locale   WordPress locale.
		 */
		return (string) apply_filters( 'intl_datetime_calendar_calendar', $calendar, $locale );
	}

	/**
	 * The numbering system dates should be written in.
	 *
	 * @return string Numbering system identifier, or '' for the locale default.
	 */
	public static function numbering_system(): string {
		/**
		 * Filters the numbering system used to write date digits.
		 *
		 * @param string $numbering_system Numbering system identifier, or ''.
		 */
		return (string) apply_filters(
			'intl_datetime_calendar_numbering_system',
			(string) Options::get( 'numbering_system' )
		);
	}

	/**
	 * Render a date to text.
	 *
	 * @param DateTimeImmutable $date   Moment to render.
	 * @param string            $format PHP date format string.
	 *
	 * @return string Rendered text, or '' when the server cannot render.
	 */
	public static function render( DateTimeImmutable $date, string $format ): string {
		if ( ! CalendarRenderer::is_available() ) {
			return '';
		}

		return self::renderer()->render( $date, $format );
	}

	/**
	 * Get the renderer for the current locale, calendar and numbering system.
	 *
	 * @return CalendarRenderer Configured renderer.
	 */
	private static function renderer(): CalendarRenderer {
		$locale           = self::locale();
		$calendar         = self::calendar();
		$numbering_system = self::numbering_system();
		$timezone         = self::timezone();

		$key = $locale . '|' . $calendar . '|' . $numbering_system . '|' . $timezone->getName();

		if ( ! isset( self::$renderers[ $key ] ) ) {
			self::$renderers[ $key ] = new CalendarRenderer( $locale, $calendar, $numbering_system, $timezone );
		}

		return self::$renderers[ $key ];
	}

	/**
	 * The attributes that describe one date to the browser.
	 *
	 * The element carries the format string itself rather than a code such as
	 * "wp" or "custom", so the browser renders exactly what the server would
	 * instead of guessing intent from the visible text.
	 *
	 * @param DateTimeImmutable $date            Moment being described.
	 * @param string            $format          PHP date format string.
	 * @param bool              $rendered_server Whether the text is already correct.
	 *
	 * @return array<string, string> Attribute names mapped to values.
	 */
	public static function attributes( DateTimeImmutable $date, string $format, bool $rendered_server ): array {
		$attributes = array(
			'data-intl-format'    => $format,
			'data-intl-calendar'  => self::calendar(),
			'data-intl-locale'    => self::locale(),
			'data-intl-timezone'  => self::timezone()->getName(),
			'data-intl-timestamp' => (string) $date->getTimestamp(),
		);

		$numbering_system = self::numbering_system();
		if ( '' !== $numbering_system ) {
			$attributes['data-intl-numbering'] = $numbering_system;
		}

		if ( $rendered_server ) {
			// Already correct, so the browser leaves it alone.
			$attributes['data-intl-rendered'] = 'server';
		}

		return $attributes;
	}

	/**
	 * Build an ISO 8601 datetime attribute value.
	 *
	 * Always the real instant in the site's timezone, so the machine-readable
	 * value stays correct whichever calendar the text is written in.
	 *
	 * @param DateTimeImmutable $date Moment to describe.
	 *
	 * @return string ISO 8601 datetime.
	 */
	public static function iso( DateTimeImmutable $date ): string {
		return $date->setTimezone( self::timezone() )->format( 'c' );
	}

	/**
	 * The configuration handed to the browser.
	 *
	 * @return array<string, mixed> Script configuration.
	 */
	public static function script_settings(): array {
		return array(
			'locale'          => self::locale(),
			'calendar'        => self::calendar(),
			'numberingSystem' => self::numbering_system(),
			'timeZone'        => self::timezone()->getName(),
			'dateFormat'      => (string) get_option( 'date_format', 'F j, Y' ),
			'timeFormat'      => (string) get_option( 'time_format', 'g:i a' ),
			'serverRendered'  => self::can_render_server(),
		);
	}
}
