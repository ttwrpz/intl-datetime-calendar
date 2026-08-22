<?php
/**
 * Regression guard: the ICU engine must match PHP's own date() output.
 *
 * Existing sites run a Gregorian calendar and their dates come from date().
 * If the new engine renders any of them differently, upgrading would change
 * dates across the site. Exits non-zero on any mismatch.
 *
 * @package Intl_DateTime_Calendar
 */

define( 'ABSPATH', __DIR__ );

require __DIR__ . '/../../src/Format/FormatSpec.php';
require __DIR__ . '/../../src/Format/CalendarRenderer.php';

use Intl_DateTime_Calendar\Format\CalendarRenderer;

if ( ! CalendarRenderer::is_available() ) {
	echo "SKIP: ext-intl is not installed\n";
	exit( 0 );
}

date_default_timezone_set( 'UTC' );

$timezones = array( 'UTC', 'Asia/Bangkok', 'America/New_York', 'Europe/Berlin' );

$moments = array(
	'2025-05-04 13:05:07',
	'2024-02-29 00:00:00',
	'2025-01-01 23:59:59',
	'2025-12-31 12:00:00',
	'1999-07-04 06:30:00',
	'2026-08-19 09:15:45',
	'2000-02-29 12:00:00',
	'2038-01-19 03:14:07',
);

// Every PHP format character the engine claims to support, plus the formats
// WordPress itself offers and a few real-world composites.
$singles = str_split( 'dDjlNSwzWFmMntLoYyaAgGhHisueIOPTZU' );

$composites = array(
	'F j, Y',
	'Y-m-d',
	'm/d/Y',
	'd/m/Y',
	'g:i a',
	'g:i A',
	'H:i',
	'H:i:s',
	'Y-m-d H:i:s',
	'l, F jS, Y',
	'D, d M Y H:i:s',
	'\a\t g:i A',
	'Y-m-d\TH:i:sP',
	'j F Y \e\v\e\n\t',
	'N w z W t L o',
	'jS \o\f F Y',
	"l 'the' jS",
);

$failures = 0;
$checks   = 0;

foreach ( $timezones as $timezone_name ) {
	$timezone = new DateTimeZone( $timezone_name );
	$renderer = new CalendarRenderer( 'en-US', 'gregory', '', $timezone );

	foreach ( $moments as $moment ) {
		$date = new DateTimeImmutable( $moment, new DateTimeZone( 'UTC' ) );

		foreach ( array_merge( $singles, $composites ) as $format ) {
			++$checks;

			$native = $date->setTimezone( $timezone )->format( $format );
			$actual = $renderer->render( $date, $format );

			if ( $native !== $actual ) {
				++$failures;
				printf(
					"MISMATCH tz=%s moment=%s format=%s\n  date()=%s\n  engine=%s\n",
					$timezone_name,
					$moment,
					var_export( $format, true ),
					var_export( $native, true ),
					var_export( $actual, true )
				);
			}
		}
	}
}

// Every ordered pair of field characters.
//
// ICU takes a run of one pattern letter as a single field whose width is the
// run's length, so two neighbouring fields sharing a letter can silently fuse
// into one wider field. That made "mm" render as "May" rather than "0505".
// Checking every pair costs little and closes the whole class rather than the
// handful of cases anyone thought to write down.
$pair_timezone = new DateTimeZone( 'Asia/Bangkok' );
$pair_renderer = new CalendarRenderer( 'en-US', 'gregory', '', $pair_timezone );
$pair_date     = new DateTimeImmutable( '2025-05-04 13:05:07', new DateTimeZone( 'UTC' ) );
$pair_failures = 0;

foreach ( $singles as $first ) {
	foreach ( $singles as $second ) {
		++$checks;

		$format = $first . $second;
		$native = $pair_date->setTimezone( $pair_timezone )->format( $format );
		$actual = $pair_renderer->render( $pair_date, $format );

		if ( $native !== $actual ) {
			++$failures;
			++$pair_failures;

			// Only the first few, so a systemic break stays readable.
			if ( $pair_failures <= 15 ) {
				printf(
					"MISMATCH pair format=%s\n  date()=%s\n  engine=%s\n",
					var_export( $format, true ),
					var_export( $native, true ),
					var_export( $actual, true )
				);
			}
		}
	}
}

printf( "%d/%d checks matched PHP date()\n", $checks - $failures, $checks );

exit( $failures > 0 ? 1 : 0 );
