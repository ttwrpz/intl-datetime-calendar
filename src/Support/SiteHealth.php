<?php
/**
 * Site Health reporting for the intl extension.
 *
 * @package Intl_DateTime_Calendar
 */

namespace Intl_DateTime_Calendar\Support;

use Intl_DateTime_Calendar\Format\CalendarRenderer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Tells the site owner whether dates are converted on the server.
 *
 * Without ext-intl the plugin still works, but conversion moves to the
 * browser and readers briefly see Gregorian dates. Reported as a
 * recommendation, never a failure, because the site is not broken.
 */
final class SiteHealth {

	/**
	 * Register the Site Health test.
	 */
	public static function register(): void {
		add_filter( 'site_status_tests', array( self::class, 'add_test' ) );
	}

	/**
	 * Add the test to Site Health.
	 *
	 * @param array<string, mixed> $tests Registered tests.
	 *
	 * @return array<string, mixed> Tests including this plugin's.
	 */
	public static function add_test( $tests ) {
		if ( ! is_array( $tests ) ) {
			return $tests;
		}

		$tests['direct']['intl_datetime_calendar_intl'] = array(
			'label' => __( 'Calendar dates are converted on the server', 'intl-datetime-calendar' ),
			'test'  => array( self::class, 'run_test' ),
		);

		return $tests;
	}

	/**
	 * Run the test.
	 *
	 * @return array<string, mixed> Site Health result.
	 */
	public static function run_test(): array {
		if ( CalendarRenderer::is_available() ) {
			return array(
				'label'       => __( 'Calendar dates are converted on the server', 'intl-datetime-calendar' ),
				'status'      => 'good',
				'badge'       => array(
					'label' => __( 'Performance', 'intl-datetime-calendar' ),
					'color' => 'blue',
				),
				'description' => '<p>' . esc_html__(
					'The PHP intl extension is installed, so dates are written in your chosen calendar before the page is sent. Visitors never see a Gregorian date first, the dates are correct for readers without JavaScript, and no extra script is loaded.',
					'intl-datetime-calendar'
				) . '</p>',
				'test'        => 'intl_datetime_calendar_intl',
			);
		}

		return array(
			'label'       => __( 'Calendar dates are converted in the browser', 'intl-datetime-calendar' ),
			'status'      => 'recommended',
			'badge'       => array(
				'label' => __( 'Performance', 'intl-datetime-calendar' ),
				'color' => 'blue',
			),
			'description' => '<p>' . esc_html__(
				'The PHP intl extension is not installed, so Intl DateTime Calendar converts dates in the visitor\'s browser instead. Everything still works, with three trade-offs: dates appear in the Gregorian calendar for a moment before being rewritten, readers with JavaScript turned off see Gregorian dates, and a small script is added to each page.',
				'intl-datetime-calendar'
			) . '</p><p>' . esc_html__(
				'Ask your host to enable the intl extension to remove all three.',
				'intl-datetime-calendar'
			) . '</p>',
			'actions'     => '',
			'test'        => 'intl_datetime_calendar_intl',
		);
	}
}
