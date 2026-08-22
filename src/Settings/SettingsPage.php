<?php
/**
 * The plugin's settings screen.
 *
 * @package Intl_DateTime_Calendar
 */

namespace Intl_DateTime_Calendar\Settings;

use Intl_DateTime_Calendar\Format\CalendarRenderer;
use Intl_DateTime_Calendar\Format\DateFormatter;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders and saves the settings screen.
 *
 * The preview runs from an enqueued file rather than an inline script block,
 * which Plugin Check rejects and a strict content security policy blocks.
 */
final class SettingsPage {

	private const PAGE  = 'intl-datetime-calendar';
	private const GROUP = 'intl_datetime_calendar';

	/**
	 * Register the admin hooks.
	 */
	public static function register(): void {
		add_action( 'admin_menu', array( self::class, 'add_page' ) );
		add_action( 'admin_init', array( self::class, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( self::class, 'enqueue' ) );
		add_action( 'update_option_' . Options::OPTION_NAME, array( Options::class, 'flush' ) );
	}

	/**
	 * Add the settings page under Settings.
	 */
	public static function add_page(): void {
		add_options_page(
			__( 'Intl DateTime Calendar', 'intl-datetime-calendar' ),
			__( 'Intl DateTime Calendar', 'intl-datetime-calendar' ),
			'manage_options',
			self::PAGE,
			array( self::class, 'render' )
		);
	}

	/**
	 * Register the setting and its schema.
	 */
	public static function register_settings(): void {
		register_setting(
			self::GROUP,
			Options::OPTION_NAME,
			array(
				'type'              => 'object',
				'sanitize_callback' => array( Options::class, 'validate' ),
				'default'           => Options::defaults(),
				'show_in_rest'      => array(
					'schema' => array(
						'type'       => 'object',
						'properties' => array(
							'calendar_type'     => array(
								'type' => 'string',
								'enum' => Options::CALENDARS,
							),
							'numbering_system'  => array(
								'type'    => 'string',
								'pattern' => '^([a-z0-9]{3,8})?$',
							),
							'server_render'     => array( 'type' => 'boolean' ),
							'apply_to_comments' => array( 'type' => 'boolean' ),
						),
					),
				),
			)
		);
	}

	/**
	 * Enqueue the preview script on this screen only.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public static function enqueue( $hook ): void {
		if ( 'settings_page_' . self::PAGE !== $hook ) {
			return;
		}

		$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';
		$handle = 'intl-datetime-calendar-admin';

		wp_enqueue_script(
			$handle,
			plugins_url( 'js/intl-datetime-calendar-admin' . $suffix . '.js', INTL_DATETIME_CALENDAR_FILE ),
			array(),
			INTL_DATETIME_CALENDAR_VERSION,
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);

		wp_add_inline_script(
			$handle,
			'window.intlDateTimeCalendarAdmin = ' . wp_json_encode(
				array(
					'locale'     => DateFormatter::locale(),
					'timeZone'   => DateFormatter::timezone()->getName(),
					'dateFormat' => (string) get_option( 'date_format', 'F j, Y' ),
					'timeFormat' => (string) get_option( 'time_format', 'g:i a' ),
				)
			) . ';',
			'before'
		);
	}

	/**
	 * Human-readable labels for each calendar.
	 *
	 * @return array<string, string> Calendar identifiers mapped to labels.
	 */
	private static function calendar_labels(): array {
		return array(
			'gregory'          => __( 'Gregorian (Western)', 'intl-datetime-calendar' ),
			'buddhist'         => __( 'Buddhist', 'intl-datetime-calendar' ),
			'chinese'          => __( 'Chinese', 'intl-datetime-calendar' ),
			'coptic'           => __( 'Coptic', 'intl-datetime-calendar' ),
			'dangi'            => __( 'Dangi (Korean)', 'intl-datetime-calendar' ),
			'ethioaa'          => __( 'Ethiopic (Amete Alem)', 'intl-datetime-calendar' ),
			'ethiopic'         => __( 'Ethiopic', 'intl-datetime-calendar' ),
			'hebrew'           => __( 'Hebrew', 'intl-datetime-calendar' ),
			'indian'           => __( 'Indian', 'intl-datetime-calendar' ),
			'islamic'          => __( 'Islamic', 'intl-datetime-calendar' ),
			'islamic-civil'    => __( 'Islamic (Civil)', 'intl-datetime-calendar' ),
			'islamic-rgsa'     => __( 'Islamic (Saudi Arabia)', 'intl-datetime-calendar' ),
			'islamic-tbla'     => __( 'Islamic (Tabular)', 'intl-datetime-calendar' ),
			'islamic-umalqura' => __( 'Islamic (Umm al-Qura)', 'intl-datetime-calendar' ),
			'iso8601'          => __( 'ISO 8601', 'intl-datetime-calendar' ),
			'japanese'         => __( 'Japanese', 'intl-datetime-calendar' ),
			'persian'          => __( 'Persian', 'intl-datetime-calendar' ),
			'roc'              => __( 'Republic of China', 'intl-datetime-calendar' ),
		);
	}

	/**
	 * Names for the numbering systems most sites choose.
	 *
	 * The rest are listed by identifier next to a sample of their digits, which
	 * says more about an unfamiliar script than a translated name would.
	 *
	 * @return array<string, string> Identifiers mapped to names.
	 */
	private static function numbering_names(): array {
		return array(
			'latn'     => __( 'Western', 'intl-datetime-calendar' ),
			'arab'     => __( 'Arabic-Indic', 'intl-datetime-calendar' ),
			'arabext'  => __( 'Eastern Arabic-Indic', 'intl-datetime-calendar' ),
			'beng'     => __( 'Bengali', 'intl-datetime-calendar' ),
			'deva'     => __( 'Devanagari', 'intl-datetime-calendar' ),
			'thai'     => __( 'Thai', 'intl-datetime-calendar' ),
			'laoo'     => __( 'Lao', 'intl-datetime-calendar' ),
			'mymr'     => __( 'Myanmar', 'intl-datetime-calendar' ),
			'tibt'     => __( 'Tibetan', 'intl-datetime-calendar' ),
			'guru'     => __( 'Gurmukhi', 'intl-datetime-calendar' ),
			'gujr'     => __( 'Gujarati', 'intl-datetime-calendar' ),
			'knda'     => __( 'Kannada', 'intl-datetime-calendar' ),
			'mlym'     => __( 'Malayalam', 'intl-datetime-calendar' ),
			'orya'     => __( 'Odia', 'intl-datetime-calendar' ),
			'tamldec'  => __( 'Tamil', 'intl-datetime-calendar' ),
			'telu'     => __( 'Telugu', 'intl-datetime-calendar' ),
			'sinh'     => __( 'Sinhala', 'intl-datetime-calendar' ),
			'khmr'     => __( 'Khmer', 'intl-datetime-calendar' ),
			'hanidec'  => __( 'Han decimal', 'intl-datetime-calendar' ),
			'fullwide' => __( 'Full width', 'intl-datetime-calendar' ),
		);
	}

	/**
	 * Render the numbering system options, showing each system's digits.
	 *
	 * @param string $selected The stored numbering system.
	 */
	private static function numbering_options( string $selected ): void {
		$names     = self::numbering_names();
		$available = Options::available_numbering_systems();
		$common    = array();
		$rest      = array();

		foreach ( $available as $numbering_system ) {
			if ( in_array( $numbering_system, Options::COMMON_NUMBERING_SYSTEMS, true ) ) {
				$common[] = $numbering_system;
				continue;
			}

			$rest[] = $numbering_system;
		}

		?>
		<option value="" <?php selected( $selected, '' ); ?>>
			<?php esc_html_e( 'Match the site language', 'intl-datetime-calendar' ); ?>
		</option>
		<?php

		$groups = array(
			__( 'Commonly used', 'intl-datetime-calendar' ) => $common,
			__( 'All other scripts', 'intl-datetime-calendar' ) => $rest,
		);

		foreach ( $groups as $label => $systems ) {
			if ( empty( $systems ) ) {
				continue;
			}
			?>
			<optgroup label="<?php echo esc_attr( $label ); ?>">
				<?php foreach ( $systems as $numbering_system ) : ?>
					<option value="<?php echo esc_attr( $numbering_system ); ?>" <?php selected( $selected, $numbering_system ); ?>>
						<?php
						$digits = (string) Options::digits( $numbering_system );
						$name   = $names[ $numbering_system ] ?? $numbering_system;

						printf(
							/* translators: 1: numbering system name, 2: its ten digits. */
							esc_html__( '%1$s: %2$s', 'intl-datetime-calendar' ),
							esc_html( $name ),
							esc_html( $digits )
						);
						?>
					</option>
				<?php endforeach; ?>
			</optgroup>
			<?php
		}

		// A value saved before this server had the matching ICU data would
		// otherwise vanish from the list and be silently reset on the next save.
		if ( '' !== $selected && ! in_array( $selected, $available, true ) ) {
			?>
			<option value="<?php echo esc_attr( $selected ); ?>" selected>
				<?php
				printf(
					/* translators: %s: numbering system identifier. */
					esc_html__( '%s (not available on this server)', 'intl-datetime-calendar' ),
					esc_html( $selected )
				);
				?>
			</option>
			<?php
		}
	}

	/**
	 * Render the settings page.
	 */
	public static function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$settings = Options::all();
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

			<?php if ( ! CalendarRenderer::is_available() ) : ?>
				<div class="notice notice-info">
					<p>
						<?php
						echo esc_html__(
							'The PHP intl extension is not installed on this server, so dates are converted in the visitor\'s browser. Everything works, but readers see a Gregorian date for a moment before it is rewritten. Ask your host to enable intl to convert dates before the page is sent.',
							'intl-datetime-calendar'
						);
						?>
					</p>
				</div>
			<?php endif; ?>

			<form action="options.php" method="post">
				<?php settings_fields( self::GROUP ); ?>

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">
							<label for="intl-calendar-type"><?php esc_html_e( 'Calendar system', 'intl-datetime-calendar' ); ?></label>
						</th>
						<td>
							<select id="intl-calendar-type" name="<?php echo esc_attr( Options::OPTION_NAME ); ?>[calendar_type]">
								<?php foreach ( self::calendar_labels() as $value => $label ) : ?>
									<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $settings['calendar_type'], $value ); ?>>
										<?php echo esc_html( $label ); ?>
									</option>
								<?php endforeach; ?>
							</select>
							<p class="description">
								<?php esc_html_e( 'Dates are displayed in this calendar. What is stored in your database never changes.', 'intl-datetime-calendar' ); ?>
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="intl-numbering-system"><?php esc_html_e( 'Digits', 'intl-datetime-calendar' ); ?></label>
						</th>
						<td>
							<select id="intl-numbering-system" name="<?php echo esc_attr( Options::OPTION_NAME ); ?>[numbering_system]">
								<?php self::numbering_options( (string) $settings['numbering_system'] ); ?>
							</select>
							<p class="description">
								<?php esc_html_e( 'Which numerals dates are written with. Thai and Arabic sites often want their own digits rather than Western ones.', 'intl-datetime-calendar' ); ?>
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row"><?php esc_html_e( 'Preview', 'intl-datetime-calendar' ); ?></th>
						<td>
							<div id="intl-preview"
								style="font-size:1.2em;padding:12px;background:#f0f0f1;border-left:4px solid #2271b1;max-width:40em;">
								<span id="intl-preview-date"><?php echo esc_html__( 'Loading preview', 'intl-datetime-calendar' ); ?></span>
							</div>
							<p class="description">
								<?php esc_html_e( 'Today\'s date in the selected calendar, using your existing date format.', 'intl-datetime-calendar' ); ?>
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row"><?php esc_html_e( 'Server rendering', 'intl-datetime-calendar' ); ?></th>
						<td>
							<?php // An unchecked box is not submitted, so pair it with a zero. ?>
							<input type="hidden" name="<?php echo esc_attr( Options::OPTION_NAME ); ?>[server_render]" value="0" />
							<label>
								<input type="checkbox"
									name="<?php echo esc_attr( Options::OPTION_NAME ); ?>[server_render]"
									value="1" <?php checked( $settings['server_render'] ); ?>
									<?php disabled( ! CalendarRenderer::is_available() ); ?> />
								<?php esc_html_e( 'Convert dates before the page is sent', 'intl-datetime-calendar' ); ?>
							</label>
							<p class="description">
								<?php esc_html_e( 'Recommended. Turn this off only if another plugin needs to read Gregorian dates from the rendered page.', 'intl-datetime-calendar' ); ?>
							</p>
						</td>
					</tr>
				</table>

				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}
}
