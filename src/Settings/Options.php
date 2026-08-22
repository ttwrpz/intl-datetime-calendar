<?php
/**
 * Plugin settings storage, defaults and validation.
 *
 * @package Intl_DateTime_Calendar
 */

namespace Intl_DateTime_Calendar\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reads and validates the plugin's stored settings.
 *
 * Every value reaching a formatter passes through here, so an option row
 * edited by hand cannot put an unsupported calendar into an ICU locale.
 */
final class Options {

	public const OPTION_NAME = 'intl_datetime_calendar_settings';

	/**
	 * Calendar systems ICU supports and this plugin exposes.
	 *
	 * @var array<int, string>
	 */
	public const CALENDARS = array(
		'buddhist',
		'chinese',
		'coptic',
		'dangi',
		'ethioaa',
		'ethiopic',
		'gregory',
		'hebrew',
		'indian',
		'islamic',
		'islamic-civil',
		'islamic-rgsa',
		'islamic-tbla',
		'islamic-umalqura',
		'iso8601',
		'japanese',
		'persian',
		'roc',
	);

	/**
	 * Numbering systems shown first. A convenience, not a limit.
	 *
	 * @var array<int, string>
	 */
	public const COMMON_NUMBERING_SYSTEMS = array(
		'latn',
		'arab',
		'arabext',
		'beng',
		'deva',
		'thai',
		'laoo',
		'mymr',
		'tibt',
		'guru',
		'gujr',
		'knda',
		'mlym',
		'orya',
		'tamldec',
		'telu',
		'sinh',
		'khmr',
		'hanidec',
		'fullwide',
	);

	/**
	 * Every decimal numbering system defined by Unicode.
	 *
	 * Data rather than runtime discovery, because ext-intl has no equivalent of
	 * Intl.supportedValuesOf('numberingSystem'). Entries the installed ICU does
	 * not know are filtered out where the list is used, so a stale list never
	 * blocks a newer ICU. Algorithmic systems such as Roman numerals are absent:
	 * they are not ten positional digits and cannot write a date.
	 *
	 * Regenerate with:
	 * node -p "JSON.stringify(Intl.supportedValuesOf('numberingSystem'))"
	 *
	 * @var array<int, string>
	 */
	public const ALL_NUMBERING_SYSTEMS = array(
		'adlm',
		'ahom',
		'arab',
		'arabext',
		'bali',
		'beng',
		'bhks',
		'brah',
		'cakm',
		'cham',
		'deva',
		'diak',
		'fullwide',
		'gara',
		'gong',
		'gonm',
		'gujr',
		'gukh',
		'guru',
		'hanidec',
		'hmng',
		'hmnp',
		'java',
		'kali',
		'kawi',
		'khmr',
		'knda',
		'krai',
		'lana',
		'lanatham',
		'laoo',
		'latn',
		'lepc',
		'limb',
		'mathbold',
		'mathdbl',
		'mathmono',
		'mathsanb',
		'mathsans',
		'mlym',
		'modi',
		'mong',
		'mroo',
		'mtei',
		'mymr',
		'mymrepka',
		'mymrpao',
		'mymrshan',
		'mymrtlng',
		'nagm',
		'newa',
		'nkoo',
		'olck',
		'onao',
		'orya',
		'osma',
		'outlined',
		'rohg',
		'saur',
		'segment',
		'shrd',
		'sind',
		'sinh',
		'sora',
		'sund',
		'sunu',
		'takr',
		'talu',
		'tamldec',
		'telu',
		'thai',
		'tibt',
		'tirh',
		'tnsa',
		'vaii',
		'wara',
		'wcho',
	);

	/**
	 * The digits of a numbering system, or null when ICU does not know it.
	 *
	 * Doubles as the test for whether a system is usable: an unknown one
	 * silently formats as Latin digits, so ten distinct results is the proof.
	 *
	 * @param string $numbering_system Numbering system identifier.
	 *
	 * @return string|null The ten digits in order, or null when unusable.
	 */
	public static function digits( string $numbering_system ): ?string {
		if ( '' === $numbering_system || ! class_exists( '\NumberFormatter' ) ) {
			return null;
		}

		// ICU rejects some keyword values outright, and the constructor
		// raises rather than returning false.
		try {
			$formatter = new \NumberFormatter( 'en-u-nu-' . $numbering_system, \NumberFormatter::DECIMAL );
			$formatter->setAttribute( \NumberFormatter::GROUPING_USED, 0 );

			$digits = '';
			$seen   = array();

			for ( $digit = 0; $digit <= 9; $digit++ ) {
				$rendered = $formatter->format( $digit );

				if ( false === $rendered ) {
					return null;
				}

				$digits           .= $rendered;
				$seen[ $rendered ] = true;
			}
		} catch ( \Throwable $e ) {
			return null;
		}

		return count( $seen ) === 10 ? $digits : null;
	}

	/**
	 * Numbering systems this server can actually render, in display order.
	 *
	 * @return array<int, string> Identifiers, common ones first.
	 */
	public static function available_numbering_systems(): array {
		$rest = array_diff( self::ALL_NUMBERING_SYSTEMS, self::COMMON_NUMBERING_SYSTEMS );
		sort( $rest );

		$ordered   = array_merge( self::COMMON_NUMBERING_SYSTEMS, $rest );
		$available = array();

		foreach ( $ordered as $numbering_system ) {
			if ( null !== self::digits( $numbering_system ) ) {
				$available[] = $numbering_system;
			}
		}

		return $available;
	}

	/**
	 * Cached settings for the current request.
	 *
	 * @var array<string, mixed>|null
	 */
	private static ?array $cache = null;

	/**
	 * The settings a fresh install starts with.
	 *
	 * Gregorian with the locale's own digits, so installing changes nothing
	 * until the owner chooses a calendar.
	 *
	 * @return array<string, mixed> Default settings.
	 */
	public static function defaults(): array {
		return array(
			'calendar_type'     => 'gregory',
			'numbering_system'  => '',
			'server_render'     => true,
			'apply_to_comments' => true,
			'locale_calendars'  => array(),
		);
	}

	/**
	 * Get the validated settings.
	 *
	 * @return array<string, mixed> Settings merged over the defaults.
	 */
	public static function all(): array {
		if ( null === self::$cache ) {
			$stored = get_option( self::OPTION_NAME, array() );

			self::$cache = self::validate( is_array( $stored ) ? $stored : array() );
		}

		return self::$cache;
	}

	/**
	 * Get one setting.
	 *
	 * @param string $key Setting name.
	 *
	 * @return mixed Setting value, or null when the name is unknown.
	 */
	public static function get( string $key ) {
		$settings = self::all();

		return $settings[ $key ] ?? null;
	}

	/**
	 * Forget the cached settings.
	 *
	 * Called when the option is written so a save is visible to the rest of
	 * the request without a reload.
	 */
	public static function flush(): void {
		self::$cache = null;
	}

	/**
	 * The calendar to use for a locale.
	 *
	 * A multilingual site can map each locale to its own calendar. Without a
	 * mapping the global choice applies.
	 *
	 * @param string $locale WordPress locale, e.g. 'th_TH'.
	 *
	 * @return string ICU calendar identifier.
	 */
	public static function calendar_for_locale( string $locale ): string {
		$settings = self::all();
		$locale   = str_replace( '_', '-', $locale );

		foreach ( $settings['locale_calendars'] as $mapped_locale => $calendar ) {
			if ( strcasecmp( str_replace( '_', '-', (string) $mapped_locale ), $locale ) === 0 ) {
				return $calendar;
			}
		}

		// Fall back to the language subtag, so 'th-TH' matches a 'th' rule.
		$language = strtok( $locale, '-' );

		foreach ( $settings['locale_calendars'] as $mapped_locale => $calendar ) {
			if ( strcasecmp( strtok( str_replace( '_', '-', (string) $mapped_locale ), '-' ), (string) $language ) === 0 ) {
				return $calendar;
			}
		}

		return $settings['calendar_type'];
	}

	/**
	 * Validate a settings array, replacing anything unusable with a default.
	 *
	 * Used both as the sanitize callback and when reading stored values, because
	 * an option row can predate the current schema.
	 *
	 * @param mixed $input Raw settings.
	 *
	 * @return array<string, mixed> Validated settings.
	 */
	public static function validate( $input ): array {
		$defaults = self::defaults();

		if ( ! is_array( $input ) ) {
			return $defaults;
		}

		$clean = $defaults;

		if ( isset( $input['calendar_type'] ) ) {
			$clean['calendar_type'] = self::pick(
				(string) $input['calendar_type'],
				self::CALENDARS,
				$defaults['calendar_type']
			);
		}

		if ( isset( $input['numbering_system'] ) ) {
			$numbering_system = strtolower( trim( (string) $input['numbering_system'] ) );

			// Checked by shape, not against a list: ICU gains systems over time,
			// and an unknown value lands in a locale keyword that ICU ignores,
			// falling back to Latin digits.
			$clean['numbering_system'] = preg_match( '/^[a-z0-9]{3,8}$/', $numbering_system )
				? $numbering_system
				: $defaults['numbering_system'];
		}

		foreach ( array( 'server_render', 'apply_to_comments' ) as $flag ) {
			if ( isset( $input[ $flag ] ) ) {
				$clean[ $flag ] = (bool) $input[ $flag ];
			}
		}

		if ( isset( $input['locale_calendars'] ) && is_array( $input['locale_calendars'] ) ) {
			$clean['locale_calendars'] = self::validate_locale_map( $input['locale_calendars'] );
		}

		return $clean;
	}

	/**
	 * Validate a locale to calendar mapping.
	 *
	 * @param array<mixed, mixed> $map Raw mapping.
	 *
	 * @return array<string, string> Mapping with known calendars only.
	 */
	private static function validate_locale_map( array $map ): array {
		$clean = array();

		foreach ( $map as $locale => $calendar ) {
			$locale = preg_replace( '/[^A-Za-z0-9_-]/', '', (string) $locale );

			if ( '' === $locale || ! in_array( (string) $calendar, self::CALENDARS, true ) ) {
				continue;
			}

			$clean[ $locale ] = (string) $calendar;
		}

		return $clean;
	}

	/**
	 * Return a value when it is in the allowed list, otherwise the fallback.
	 *
	 * @param string             $value    Candidate value.
	 * @param array<int, string> $allowed  Permitted values.
	 * @param string             $fallback Value to use when the candidate is not permitted.
	 *
	 * @return string Allowed value.
	 */
	private static function pick( string $value, array $allowed, string $fallback ): string {
		return in_array( $value, $allowed, true ) ? $value : $fallback;
	}
}
