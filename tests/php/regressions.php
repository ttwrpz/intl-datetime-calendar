<?php
/**
 * Regression tests for the defects fixed in 2.0.0.
 *
 * Each case shipped in 1.0.3, so the names describe the old behaviour.
 * Exits non-zero on failure.
 *
 * @package Intl_DateTime_Calendar
 */

require __DIR__ . '/bootstrap.php';

use Intl_DateTime_Calendar\Format\CalendarRenderer;
use Intl_DateTime_Calendar\Render\Shortcode;

$failures = 0;
$checks   = 0;

/**
 * Assert two values match.
 *
 * @param string $name     What is being checked.
 * @param mixed  $expected Expected value.
 * @param mixed  $actual   Actual value.
 */
function check( $name, $expected, $actual ) {
	global $failures, $checks;
	++$checks;

	if ( $expected === $actual ) {
		printf( "  PASS  %s\n", $name );

		return;
	}

	++$failures;
	printf( "  FAIL  %s\n        expected %s\n        actual   %s\n", $name, var_export( $expected, true ), var_export( $actual, true ) );
}

/**
 * Assert a string contains a substring.
 *
 * @param string $name     What is being checked.
 * @param string $needle   Expected substring.
 * @param string $haystack String to search.
 */
function check_contains( $name, $needle, $haystack ) {
	global $failures, $checks;
	++$checks;

	if ( str_contains( $haystack, $needle ) ) {
		printf( "  PASS  %s\n", $name );

		return;
	}

	++$failures;
	printf( "  FAIL  %s\n        expected to contain %s\n        actual %s\n", $name, var_export( $needle, true ), var_export( $haystack, true ) );
}

echo "Timezone handling (site is Asia/Bangkok, PHP runs in UTC)\n";

// 1.0.3 read the site wall clock as if it were UTC, publishing an instant
// seven hours away from the one the author wrote and labelling it +00:00.
$markup = Shortcode::render( array( 'date' => '2025-05-04 12:30:45', 'type' => 'datetime' ) );
check_contains( 'datetime attribute carries the site offset', 'datetime="2025-05-04T12:30:45+07:00"', $markup );
check( 'attribute is not stamped UTC', false, str_contains( $markup, '+00:00' ) );

$expected_timestamp = ( new DateTimeImmutable( '2025-05-04 12:30:45', new DateTimeZone( 'Asia/Bangkok' ) ) )->getTimestamp();
check_contains( 'timestamp is the real instant', 'data-intl-timestamp="' . $expected_timestamp . '"', $markup );

echo "\nStrict date validation\n";

// 1.0.3 let PHP roll impossible dates forward, so 31 February became 3 March.
check( 'impossible date is refused, not rolled forward', '2025-02-31', Shortcode::render( array( 'date' => '2025-02-31' ) ) );
check( 'garbage is refused', '99-99-99', Shortcode::render( array( 'date' => '99-99-99' ) ) );
check( 'whitespace is refused rather than meaning now', 'x  x', Shortcode::render( array( 'date' => 'x  x' ) ) );
check_contains( 'a valid date still renders', '<time', Shortcode::render( array( 'date' => '2025-05-04' ) ) );

// 1.0.3 tested the timestamp for truthiness, so the epoch counted as invalid.
check_contains( 'the Unix epoch is a valid date', '<time', Shortcode::render( array( 'date' => '1970-01-01 00:00:00' ) ) );

echo "\nEscaping\n";

$xss = Shortcode::render( array( 'date' => '" onmouseover="alert(1)' ) );
check( 'rejected input is escaped', false, str_contains( $xss, '" onmouseover="' ) );

$format_xss = Shortcode::render( array( 'date' => '2025-05-04', 'format' => '"><img src=x onerror=alert(1)>' ) );
check( 'a hostile format cannot break out of the attribute', false, str_contains( $format_xss, '<img' ) );

if ( CalendarRenderer::is_available() ) {
	echo "\nCalendar rendering\n";

	intl_test_set_option( 'locale', 'th_TH' );
	update_test_calendar( 'buddhist', 'thai' );

	$thai = Shortcode::render( array( 'date' => '2025-05-04', 'format' => 'd/m/Y' ) );

	// 1.0.3 rendered this as "04/05/พ.ศ. 2568", leaking the era into a
	// numeric format, and never localized the digits at all.
	check_contains( 'Buddhist date uses Thai digits without an era prefix', '>๐๔/๐๕/๒๕๖๘<', $thai );
	check( 'era marker does not leak into a numeric format', false, str_contains( $thai, 'พ.ศ.' ) );

	// The machine-readable attribute must stay Gregorian and unambiguous.
	check_contains( 'datetime attribute stays Gregorian', 'datetime="2025-05-04T00:00:00+07:00"', $thai );

	$zone = new DateTimeZone( 'Asia/Bangkok' );

	echo "\nStored dates are never rewritten\n";

	// 2.0.2 hooked wp_date, so a plugin calling wp_date('Y-m-d') for a
	// database key received 2569-08-29 and a retention job comparing against a
	// 2569 cutoff deleted every Gregorian row it had.
	check(
		'the wp_date filter is not registered at all',
		false,
		isset( $GLOBALS['intl_test_filters']['wp_date'] )
	);

	$post = new WP_Post();

	// Filtering by format cannot save it: a site may set its date format to
	// Y-m-d, so the display format and the storage shape are the same string.
	intl_test_set_option( 'date_format', 'Y-m-d' );
	check(
		'a display filter still converts when the site format is Y-m-d',
		'๒๕๖๙-๐๘-๒๙',
		\Intl_DateTime_Calendar\Render\DateFilter::filter_post_date( '2026-08-29', '', $post )
	);
	intl_test_set_option( 'date_format', 'F j, Y' );

	echo "\nDisplay filters still convert\n";

	check(
		'a post date is converted',
		'๒๙ สิงหาคม ๒๕๖๙',
		\Intl_DateTime_Calendar\Render\DateFilter::filter_post_date( '29 August 2026', 'j F Y', $post )
	);
	check(
		'a modified date reads the modified field',
		'๓๐ สิงหาคม ๒๕๖๙',
		\Intl_DateTime_Calendar\Render\DateFilter::filter_modified_date( '30 August 2026', 'j F Y', $post )
	);

	$comment = new WP_Comment();
	check(
		'a comment date is converted',
		'๒๙ สิงหาคม ๒๕๖๙',
		\Intl_DateTime_Calendar\Render\DateFilter::filter_comment_date( '29 August 2026', 'j F Y', $comment )
	);
	check(
		'a comment asked for untranslated is left alone',
		'2026-08-29',
		\Intl_DateTime_Calendar\Render\DateFilter::filter_comment_time( '2026-08-29', 'Y-m-d', false, false, $comment )
	);

	// The core date block builds its datetime attribute with get_the_date('c').
	check(
		'the machine-readable c format is left alone',
		'2026-08-29T10:00:00+07:00',
		\Intl_DateTime_Calendar\Render\DateFilter::filter_post_date( '2026-08-29T10:00:00+07:00', 'c', $post )
	);
	check(
		'a Unix timestamp is left alone',
		'1787972400',
		\Intl_DateTime_Calendar\Render\DateFilter::filter_post_date( '1787972400', 'U', $post )
	);

	echo "\nRequests that produce data, not pages\n";

	foreach ( array( 'cron', 'ajax', 'json', 'feed' ) as $context ) {
		$GLOBALS['intl_test_context'] = array( $context => true );

		check(
			$context . ' is left alone',
			'29 August 2026',
			\Intl_DateTime_Calendar\Render\DateFilter::filter_post_date( '29 August 2026', 'j F Y', $post )
		);

		$GLOBALS['intl_test_context'] = array();
	}

	echo "\nLocales ICU has no data for\n";

	// WordPress translates more languages than ICU carries. Asking ICU for one
	// of those left an unconstructed formatter that raised on first use, taking
	// the whole front end down with a white screen.
	foreach ( array( 'azb', 'skr', 'haz', 'kmr' ) as $unsupported ) {
		$renderer = new CalendarRenderer( $unsupported, 'islamic-umalqura', 'arab', $zone );
		$failed   = false;

		try {
			// Repeated, because the first failure used to be cached and reused.
			for ( $attempt = 0; $attempt < 3; $attempt++ ) {
				$rendered = $renderer->render( new DateTimeImmutable( '2026-08-23', $zone ), 'Y-m-d' );
			}
		} catch ( Throwable $e ) {
			$failed = true;
		}

		check( $unsupported . ' does not fatal', false, $failed );
		check( $unsupported . ' defers to WordPress', '', $rendered );
	}

	// The date must survive the filter untouched rather than vanishing.
	intl_test_set_option( 'locale', 'azb' );
	\Intl_DateTime_Calendar\Settings\Options::flush();
	check(
		'an unsupported locale keeps WordPress own date',
		'2026-08-23',
		\Intl_DateTime_Calendar\Render\DateFilter::filter_post_date( '2026-08-23', 'Y-m-d', new WP_Post() )
	);
	intl_test_set_option( 'locale', 'th_TH' );
	\Intl_DateTime_Calendar\Settings\Options::flush();

	// An empty locale once composed into "-u-ca-islamic", which is not a locale.
	$empty = new CalendarRenderer( '', 'islamic-umalqura', 'arab', $zone );
	check( 'an empty locale still renders', true, '' !== $empty->render( new DateTimeImmutable( '2026-08-23', $zone ), 'Y-m-d' ) );

	// A region ICU lacks should fall back to the language, not give up.
	$region = new CalendarRenderer( 'ar_XX', 'gregory', '', $zone );
	check( 'an unknown region falls back to the language', true, '' !== $region->render( new DateTimeImmutable( '2026-08-23', $zone ), 'j F Y' ) );

	echo "\nThe settings preview describes the front end\n";

	// determine_locale() answers with the editor's own language inside
	// wp-admin, so the preview used to show months a visitor never sees.
	intl_test_set_option( 'locale', 'th_TH' );
	intl_test_set_option( 'user_locale', 'en_US' );
	\Intl_DateTime_Calendar\Settings\Options::flush();

	check( 'the current request follows the editor', 'en-US', \Intl_DateTime_Calendar\Format\DateFormatter::locale() );
	check( 'the preview follows the site', 'th-TH', \Intl_DateTime_Calendar\Format\DateFormatter::site_locale() );

	$preview = new CalendarRenderer(
		\Intl_DateTime_Calendar\Format\DateFormatter::site_locale(),
		'buddhist',
		'thai',
		new DateTimeZone( 'Asia/Bangkok' )
	);
	check(
		'the preview renders Thai months, not English ones',
		'๒๓ สิงหาคม ๒๕๖๙',
		$preview->render( new DateTimeImmutable( '2026-08-23', new DateTimeZone( 'Asia/Bangkok' ) ), 'j F Y' )
	);

	unset( $GLOBALS['intl_test_options']['user_locale'] );
	\Intl_DateTime_Calendar\Settings\Options::flush();
} else {
	echo "\nSKIP calendar rendering: ext-intl is not installed\n";
}

printf( "\n%d/%d checks passed\n", $checks - $failures, $checks );

exit( $failures > 0 ? 1 : 0 );

/**
 * Point the settings at a calendar and numbering system.
 *
 * @param string $calendar         ICU calendar identifier.
 * @param string $numbering_system Numbering system identifier.
 */
function update_test_calendar( $calendar, $numbering_system ) {
	$GLOBALS['intl_test_options']['intl_datetime_calendar_settings'] = array(
		'calendar_type'    => $calendar,
		'numbering_system' => $numbering_system,
		'server_render'    => true,
	);

	\Intl_DateTime_Calendar\Settings\Options::flush();
}
