<?php
/**
 * Server-side date rendering through ICU.
 *
 * @package Intl_DateTime_Calendar
 */

namespace Intl_DateTime_Calendar\Format;

use DateTimeImmutable;
use DateTimeZone;
use IntlCalendar;
use IntlDateFormatter;
use NumberFormatter;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders a timestamp through a PHP date format string in any ICU calendar.
 *
 * The whole format is compiled into one ICU pattern and rendered in a single
 * call, because ICU chooses word forms from the surrounding fields. Russian
 * gives "май" for a month alone but "мая" inside a full date, as do Czech,
 * Polish and Catalan, so fields are never rendered separately and glued.
 *
 * Format characters ICU has no letter for are computed here and injected as
 * quoted literals, which keeps that single call intact.
 */
final class CalendarRenderer {

	/**
	 * PHP format characters that map onto ICU pattern letters.
	 */
	private const ICU_PATTERN = array(
		'd' => 'dd',
		'j' => 'd',
		'D' => 'EEE',
		'l' => 'EEEE',
		'F' => 'MMMM',
		'M' => 'MMM',
		'm' => 'MM',
		'n' => 'M',
		'Y' => 'y',
		'y' => 'yy',
		'g' => 'h',
		'G' => 'H',
		'h' => 'hh',
		'H' => 'HH',
		'i' => 'mm',
		's' => 'ss',
		'A' => 'a',
	);

	/**
	 * Computed fields a visitor reads as prose, so their digits are localized.
	 *
	 * Everything else computed here is machine facing, such as offsets and
	 * timestamps, where localizing the digits would corrupt the value.
	 */
	private const LOCALIZED_COMPUTED = 'NwzWtB';

	/**
	 * Formatters, keyed by locale and timezone.
	 *
	 * @var array<string, IntlDateFormatter>
	 */
	private static array $formatters = array();

	/**
	 * Digit tables, keyed by locale.
	 *
	 * @var array<string, array<int, string>>
	 */
	private static array $digits = array();

	/**
	 * Locale, already carrying any calendar and numbering keywords.
	 *
	 * @var string
	 */
	private string $locale;

	/**
	 * Timezone every rendered date is resolved against.
	 *
	 * @var DateTimeZone
	 */
	private DateTimeZone $timezone;

	/**
	 * Numbering system, or '' for the locale default.
	 *
	 * @var string
	 */
	private string $numbering_system;

	/**
	 * Build a renderer for one locale, calendar and timezone.
	 *
	 * @param string       $locale           Base locale such as 'th-TH'.
	 * @param string       $calendar         ICU calendar identifier.
	 * @param string       $numbering_system Numbering system, or '' for the locale default.
	 * @param DateTimeZone $timezone         Timezone to resolve dates against.
	 */
	public function __construct( string $locale, string $calendar, string $numbering_system, DateTimeZone $timezone ) {
		$this->locale           = self::build_locale( $locale, $calendar, $numbering_system );
		$this->timezone         = $timezone;
		$this->numbering_system = $numbering_system;
	}

	/**
	 * Whether server-side rendering is possible here.
	 *
	 * @return bool True when the intl extension is available.
	 */
	public static function is_available(): bool {
		return extension_loaded( 'intl' ) && class_exists( '\IntlDateFormatter' );
	}

	/**
	 * Compose a locale carrying the calendar and numbering keywords.
	 *
	 * IntlDateFormatter ignores a -u-ca- keyword unless it is also handed an
	 * IntlCalendar, so this keyword is what builds that calendar.
	 *
	 * @param string $locale           Base locale.
	 * @param string $calendar         ICU calendar identifier.
	 * @param string $numbering_system Numbering system, or ''.
	 *
	 * @return string Locale with Unicode extension keywords appended.
	 */
	private static function build_locale( string $locale, string $calendar, string $numbering_system ): string {
		$locale     = str_replace( '_', '-', $locale );
		$extensions = '';

		if ( '' !== $calendar ) {
			$extensions .= '-ca-' . $calendar;
		}

		if ( '' !== $numbering_system ) {
			$extensions .= '-nu-' . $numbering_system;
		}

		return '' === $extensions ? $locale : $locale . '-u' . $extensions;
	}

	/**
	 * Render a date through a PHP date format string.
	 *
	 * @param DateTimeImmutable $date       Moment to render.
	 * @param string            $php_format PHP date format string, e.g. 'j F Y'.
	 *
	 * @return string Rendered date.
	 */
	public function render( DateTimeImmutable $date, string $php_format ): string {
		$date    = $date->setTimezone( $this->timezone );
		$pattern = '';

		// Buffered, not quoted per token: ICU reads the apostrophe pair
		// between two quoted sections as one escaped apostrophe.
		$literal = '';

		// Last ICU letter with no text after it, used to spot fusing fields.
		$last_letter = '';

		foreach ( FormatSpec::tokenize( $php_format ) as $token ) {
			if ( FormatSpec::LITERAL === $token['type'] ) {
				$literal .= $token['value'];
				if ( '' !== $token['value'] ) {
					$last_letter = '';
				}
				continue;
			}

			$char = $token['value'];

			if ( isset( self::ICU_PATTERN[ $char ] ) ) {
				$icu = self::ICU_PATTERN[ $char ];

				// A run of one letter is one field whose width is the run length, so
				// two fields sharing a letter fuse: "mm" means the month twice,
				// "MMMM" means its name. No zero width separator exists, so the
				// second is rendered alone.
				if ( '' === $literal && $last_letter === $icu[0] ) {
					$literal    .= $this->render_raw( $date, $icu );
					$last_letter = '';
					continue;
				}

				$pattern    .= self::quote( $literal ) . $icu;
				$literal     = '';
				$last_letter = substr( $icu, -1 );
				continue;
			}

			$computed = $this->compute( $date, $char );
			$literal .= $computed;

			// Only text that reaches the pattern can separate two runs.
			if ( '' !== $computed ) {
				$last_letter = '';
			}
		}

		return $this->render_raw( $date, $pattern . self::quote( $literal ) );
	}

	/**
	 * Resolve a PHP format character that ICU cannot express.
	 *
	 * @param DateTimeImmutable $date Moment being rendered, in the target timezone.
	 * @param string            $char PHP format character.
	 *
	 * @return string Resolved text, ready to be quoted into the pattern.
	 */
	private function compute( DateTimeImmutable $date, string $char ): string {
		// PHP's lowercase day period has no ICU letter of its own.
		if ( 'a' === $char ) {
			return $this->lowercase( $this->render_raw( $date, 'a' ) );
		}

		if ( 'W' === $char || 'o' === $char || 't' === $char || 'z' === $char ) {
			$value = (string) $this->calendar_field( $date, $char );

			// PHP pads the week number to two digits. The others are bare.
			if ( 'W' === $char ) {
				$value = str_pad( $value, 2, '0', STR_PAD_LEFT );
			}

			return $this->localize_digits( $value );
		}

		$value = $date->format( $char );

		if ( false !== strpos( self::LOCALIZED_COMPUTED, $char ) ) {
			return $this->localize_digits( $value );
		}

		return $value;
	}

	/**
	 * Read a calendar-dependent numeric field through ICU.
	 *
	 * Month and year lengths differ per calendar, so these cannot come from
	 * the Gregorian date: a Hebrew or Islamic month is 29 or 30 days.
	 *
	 * @param DateTimeImmutable $date Moment being rendered.
	 * @param string            $char One of W, o, t or z.
	 *
	 * @return int Field value using PHP's conventions.
	 */
	private function calendar_field( DateTimeImmutable $date, string $char ): int {
		$calendar = IntlCalendar::createInstance( $this->timezone, $this->locale );

		// ISO-8601 week rules, matching PHP's W and o.
		$calendar->setFirstDayOfWeek( IntlCalendar::DOW_MONDAY );
		$calendar->setMinimalDaysInFirstWeek( 4 );
		$calendar->setTime( $date->getTimestamp() * 1000 );

		switch ( $char ) {
			case 'W':
				return $calendar->get( IntlCalendar::FIELD_WEEK_OF_YEAR );
			case 'o':
				return $calendar->get( IntlCalendar::FIELD_YEAR_WOY );
			case 't':
				return $calendar->getActualMaximum( IntlCalendar::FIELD_DAY_OF_MONTH );
			case 'z':
				// PHP counts the day of the year from zero.
				return $calendar->get( IntlCalendar::FIELD_DAY_OF_YEAR ) - 1;
		}

		return 0;
	}

	/**
	 * Render a bare ICU pattern against a date.
	 *
	 * @param DateTimeImmutable $date    Moment to render.
	 * @param string            $pattern ICU pattern.
	 *
	 * @return string Rendered text.
	 */
	private function render_raw( DateTimeImmutable $date, string $pattern ): string {
		$formatter = $this->formatter();
		$formatter->setPattern( $pattern );

		$rendered = $formatter->format( $date );

		return false === $rendered ? '' : $rendered;
	}

	/**
	 * Get the formatter for this locale and timezone.
	 *
	 * @return IntlDateFormatter Configured formatter.
	 */
	private function formatter(): IntlDateFormatter {
		$key = $this->locale . '|' . $this->timezone->getName();

		if ( ! isset( self::$formatters[ $key ] ) ) {
			$calendar = IntlCalendar::createInstance( $this->timezone, $this->locale );

			self::$formatters[ $key ] = new IntlDateFormatter(
				$this->locale,
				IntlDateFormatter::NONE,
				IntlDateFormatter::NONE,
				$this->timezone,
				$calendar
			);
		}

		return self::$formatters[ $key ];
	}

	/**
	 * Convert ASCII digits to the active numbering system.
	 *
	 * Digits are mapped one for one rather than reformatted, so padding
	 * applied earlier survives.
	 *
	 * @param string $value Text containing ASCII digits.
	 *
	 * @return string Text in the active numbering system.
	 */
	private function localize_digits( string $value ): string {
		if ( '' === $this->numbering_system || 'latn' === $this->numbering_system ) {
			return $value;
		}

		if ( ! isset( self::$digits[ $this->locale ] ) ) {
			$number_formatter = new NumberFormatter( $this->locale, NumberFormatter::DECIMAL );
			$number_formatter->setAttribute( NumberFormatter::GROUPING_USED, 0 );

			$table = array();
			for ( $digit = 0; $digit <= 9; $digit++ ) {
				$table[ $digit ] = $number_formatter->format( $digit );
			}

			self::$digits[ $this->locale ] = $table;
		}

		return strtr( $value, self::$digits[ $this->locale ] );
	}

	/**
	 * Lowercase text in a multibyte-safe way.
	 *
	 * @param string $value Text to lowercase.
	 *
	 * @return string Lowercased text.
	 */
	private function lowercase( string $value ): string {
		return function_exists( 'mb_strtolower' ) ? mb_strtolower( $value, 'UTF-8' ) : strtolower( $value );
	}

	/**
	 * Quote literal text for inclusion in an ICU pattern.
	 *
	 * Every ASCII letter is a pattern character, so literals are always
	 * quoted rather than only when they look risky.
	 *
	 * @param string $text Literal text.
	 *
	 * @return string Quoted literal, or '' when there is nothing to emit.
	 */
	private static function quote( string $text ): string {
		if ( '' === $text ) {
			return '';
		}

		return "'" . str_replace( "'", "''", $text ) . "'";
	}
}
