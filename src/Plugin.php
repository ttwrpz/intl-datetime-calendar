<?php
/**
 * Plugin wiring.
 *
 * @package Intl_DateTime_Calendar
 */

namespace Intl_DateTime_Calendar;

use Intl_DateTime_Calendar\Format\DateFormatter;
use Intl_DateTime_Calendar\Render\BlockAnnotator;
use Intl_DateTime_Calendar\Render\DateFilter;
use Intl_DateTime_Calendar\Render\Shortcode;
use Intl_DateTime_Calendar\Settings\SettingsPage;
use Intl_DateTime_Calendar\Support\SiteHealth;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Connects the plugin's pieces to WordPress.
 */
final class Plugin {

	/**
	 * Register every hook the plugin uses.
	 */
	public static function boot(): void {
		SettingsPage::register();
		SiteHealth::register();

		add_action( 'init', array( self::class, 'init' ) );
	}

	/**
	 * Register the hooks that depend on the locale being settled.
	 */
	public static function init(): void {
		DateFilter::register();
		BlockAnnotator::register();
		Shortcode::register();

		add_action( 'wp_enqueue_scripts', array( self::class, 'enqueue' ) );
	}

	/**
	 * Enqueue the front-end script.
	 *
	 * It exists to convert dates the server could not, so where the server did
	 * the work it is not sent at all.
	 */
	public static function enqueue(): void {
		if ( DateFormatter::can_render_server() ) {
			return;
		}

		$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';
		$handle = 'intl-datetime-calendar';

		wp_enqueue_script(
			$handle,
			plugins_url( 'js/intl-datetime-calendar' . $suffix . '.js', INTL_DATETIME_CALENDAR_FILE ),
			array(),
			INTL_DATETIME_CALENDAR_VERSION,
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);

		wp_add_inline_script(
			$handle,
			'window.intlDateTimeCalendar = ' . wp_json_encode( DateFormatter::script_settings() ) . ';',
			'before'
		);
	}
}
