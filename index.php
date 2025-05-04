<?php
/**
 * Plugin Name: Intl DateTime Calendar
 * Plugin URI: https://github.com/ttwrpz/intl-datetime-calendar
 * Description: A plugin that displays dates and times in various calendar systems using the Intl API.
 * Version: 1.0.0
 * Requires PHP: 7.0
 * Requires at least: 5.0
 * Author: ttwrpz
 * Author URI: https://github.com/ttwrpz
 * Text Domain: intl-datetime-calendar
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 */

if ( ! defined( 'WPINC' ) || ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Current plugin version.
 */
define( 'INTL_DATETIME_CALENDAR_VERSION', '1.0.0' );

class Intl_DateTime_Calendar {

	/**
	 * Initialize the class and set its properties.
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'init' ) );
	}

	/**
	 * Initialize the plugin functionality.
	 */
	public function init() {
		// Add settings page
		add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );

		// Enqueue scripts and styles
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );

		// Block editor specific hooks
		add_filter( 'render_block_core/post-date', array( $this, 'filter_post_date_block' ), 10, 2 );
		add_filter( 'render_block_core/post-time', array( $this, 'filter_post_time_block' ), 10, 2 );
		add_filter( 'render_block_core/post-modified-date', array( $this, 'filter_modified_date_blocks' ), 10, 2 );
		add_filter( 'render_block_core/post-modified-time', array( $this, 'filter_modified_date_blocks' ), 10, 2 );

		// General filter for blocks that might contain date/time
		add_filter( 'render_block', array( $this, 'filter_datetime_blocks' ), 10, 2 );

		// Shortcode for manual integration
		add_shortcode( 'intl_datetime', array( $this, 'intl_datetime_shortcode' ) );
	}

	/**
	 * Add settings page to WordPress admin menu.
	 */
	public function add_settings_page() {
		add_options_page(
			__( 'Intl DateTime Calendar', 'intl-datetime-calendar' ),
			__( 'Intl DateTime Calendar', 'intl-datetime-calendar' ),
			'manage_options',
			'intl-datetime-calendar',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Register plugin settings.
	 */
	public function register_settings() {
		register_setting(
			'intl_datetime_calendar',
			'intl_datetime_calendar_settings',
			array(
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
				'default'           => array(
					'calendar_type' => 'gregory',
				)
			)
		);

		add_settings_section(
			'intl_datetime_calendar_general',
			__( 'General Settings', 'intl-datetime-calendar' ),
			array( $this, 'settings_section_callback' ),
			'intl-datetime-calendar'
		);

		add_settings_field(
			'calendar_type',
			__( 'Calendar Type', 'intl-datetime-calendar' ),
			array( $this, 'calendar_type_callback' ),
			'intl-datetime-calendar',
			'intl_datetime_calendar_general'
		);
	}

	/**
	 * Sanitize plugin settings.
	 *
	 * @param array $input The settings array being saved.
	 *
	 * @return array The sanitized settings array.
	 */
	public function sanitize_settings( $input ) {
		$sanitized_input = array();

		$valid_calendar_types = array(
			'gregory',
			'buddhist',
			'chinese',
			'coptic',
			'ethiopic',
			'hebrew',
			'indian',
			'islamic',
			'iso8601',
			'japanese',
			'persian',
			'roc'
		);

		if ( isset( $input['calendar_type'] ) ) {
			if ( in_array( $input['calendar_type'], $valid_calendar_types ) ) {
				$sanitized_input['calendar_type'] = sanitize_text_field( $input['calendar_type'] );
			} else {
				// If invalid, default to Gregorian
				$sanitized_input['calendar_type'] = 'gregory';
				add_settings_error(
					'intl_datetime_calendar_settings',
					'invalid_calendar_type',
					__( 'Invalid calendar type selected. Defaulting to Gregorian.', 'intl-datetime-calendar' ),
					'error'
				);
			}
		} else {
			$sanitized_input['calendar_type'] = 'gregory';
		}

		return $sanitized_input;
	}


	/**
	 * Settings section callback function.
	 */
	public function settings_section_callback() {
		echo '<p>' . esc_html__( 'Choose which calendar system to use for displaying dates.', 'intl-datetime-calendar' ) . '</p>';
	}

	/**
	 * Calendar type field callback.
	 */
	public function calendar_type_callback() {
		$options = get_option( 'intl_datetime_calendar_settings', array(
			'calendar_type' => 'gregory',
		) );

		?>
        <select name="intl_datetime_calendar_settings[calendar_type]" id="calendar_type">
            <option value="gregory" <?php selected( $options['calendar_type'], 'gregory' ); ?>><?php echo esc_html__( 'Gregorian (Western)', 'intl-datetime-calendar' ); ?></option>
            <option value="buddhist" <?php selected( $options['calendar_type'], 'buddhist' ); ?>><?php echo esc_html__( 'Buddhist', 'intl-datetime-calendar' ); ?></option>
            <option value="chinese" <?php selected( $options['calendar_type'], 'chinese' ); ?>><?php echo esc_html__( 'Chinese', 'intl-datetime-calendar' ); ?></option>
            <option value="coptic" <?php selected( $options['calendar_type'], 'coptic' ); ?>><?php echo esc_html__( 'Coptic', 'intl-datetime-calendar' ); ?></option>
            <option value="ethiopic" <?php selected( $options['calendar_type'], 'ethiopic' ); ?>><?php echo esc_html__( 'Ethiopic', 'intl-datetime-calendar' ); ?></option>
            <option value="hebrew" <?php selected( $options['calendar_type'], 'hebrew' ); ?>><?php echo esc_html__( 'Hebrew', 'intl-datetime-calendar' ); ?></option>
            <option value="indian" <?php selected( $options['calendar_type'], 'indian' ); ?>><?php echo esc_html__( 'Indian', 'intl-datetime-calendar' ); ?></option>
            <option value="islamic" <?php selected( $options['calendar_type'], 'islamic' ); ?>><?php echo esc_html__( 'Islamic', 'intl-datetime-calendar' ); ?></option>
            <option value="iso8601" <?php selected( $options['calendar_type'], 'iso8601' ); ?>><?php echo esc_html__( 'ISO 8601', 'intl-datetime-calendar' ); ?></option>
            <option value="japanese" <?php selected( $options['calendar_type'], 'japanese' ); ?>><?php echo esc_html__( 'Japanese', 'intl-datetime-calendar' ); ?></option>
            <option value="persian" <?php selected( $options['calendar_type'], 'persian' ); ?>><?php echo esc_html__( 'Persian', 'intl-datetime-calendar' ); ?></option>
            <option value="roc" <?php selected( $options['calendar_type'], 'roc' ); ?>><?php echo esc_html__( 'Republic of China', 'intl-datetime-calendar' ); ?></option>
        </select>
        <p class="description"><?php echo esc_html__( 'Select which calendar system to use for displaying dates.', 'intl-datetime-calendar' ); ?></p>
		<?php
	}

	/**
	 * Render the settings page.
	 */
	public function render_settings_page() {
		?>
        <div class="wrap">
            <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
            <form action="options.php" method="post">
				<?php
				settings_fields( 'intl_datetime_calendar' );
				do_settings_sections( 'intl-datetime-calendar' );
				submit_button();
				?>
            </form>
        </div>
		<?php
	}

	/**
	 * Enqueue necessary scripts and styles.
	 */
	public function enqueue_scripts() {
		wp_enqueue_script(
			'intl-datetime-calendar-js',
			plugin_dir_url( __FILE__ ) . 'js/intl-datetime-calendar.js',
			array( 'jquery' ),
			INTL_DATETIME_CALENDAR_VERSION,
			true
		);

		// Get WordPress native settings
		$options = get_option( 'intl_datetime_calendar_settings', array(
			'calendar_type' => 'gregory',
		) );

		// Get WordPress date/time formats
		$wp_date_format = get_option( 'date_format', 'F j, Y' );
		$wp_time_format = get_option( 'time_format', 'g:i a' );

		// Get current locale based on context (frontend)
		$current_locale = get_locale();

		// Convert locale format if needed (e.g., en_US to en-US for Intl API)
		$current_locale = str_replace( '_', '-', $current_locale );

		wp_localize_script(
			'intl-datetime-calendar-js',
			'intlDateTimeCalendarSettings',
			array(
				'calendar_type'  => $options['calendar_type'],
				'locale'         => $current_locale,
				'wp_date_format' => $wp_date_format,
				'wp_time_format' => $wp_time_format
			)
		);
	}

	/**
	 * Filter blocks for datetime elements - Updated to respect custom formats
	 */
	public function filter_datetime_blocks( $block_content, $block ) {
		if ( in_array( $block['blockName'], array(
			'core/latest-posts',
			'core/latest-comments',
			'core/query'
		) ) ) {
			// Check if the block has a custom format
			$block_format = isset( $block['attrs']['format'] ) ? $block['attrs']['format'] : null;
			if ( function_exists( 'libxml_use_internal_errors' ) ) {
				libxml_use_internal_errors( true ); // Suppress warnings for malformed HTML
			}

			$dom = new DOMDocument();
			$dom->loadHTML( '<?xml encoding="utf-8" ?>' . $block_content );

			$time_elements = $dom->getElementsByTagName( 'time' );

			if ( $time_elements->length > 0 ) {
				foreach ( $time_elements as $time ) {
					$datetime = $time->getAttribute( 'datetime' );

					if ( $datetime ) {
						$timestamp = strtotime( $datetime ) * 1000; // Convert to milliseconds

						// Get plugin settings for calendar type only
						$options = get_option( 'intl_datetime_calendar_settings', array(
							'calendar_type' => 'gregory',
						) );

						$time->setAttribute( 'class', $time->getAttribute( 'class' ) . ' intl-datetime-element' );
						$time->setAttribute( 'data-intl-datetime', $timestamp );
						$time->setAttribute( 'data-calendar', $options['calendar_type'] );

						// If the block has a custom format, use it
						if ( $block_format ) {
							$time->setAttribute( 'data-date-format', 'custom' );
							$time->setAttribute( 'data-time-format', 'custom' );
							$time->setAttribute( 'data-custom-format', $block_format );
						} else {
							// Otherwise try to determine format based on content
							$content  = $time->nodeValue;
							$has_time = preg_match( '/\d{1,2}:\d{2}/', $content );
							$has_date = preg_match( '/\d{4}|\b(Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)\b/i', $content );

							if ( $has_date && ! $has_time ) {
								$time->setAttribute( 'data-date-format', 'wp' );
								$time->setAttribute( 'data-time-format', 'none' );
							} elseif ( ! $has_date && $has_time ) {
								$time->setAttribute( 'data-date-format', 'none' );
								$time->setAttribute( 'data-time-format', 'wp' );
							} else {
								$time->setAttribute( 'data-date-format', 'wp' );
								$time->setAttribute( 'data-time-format', 'wp' );
							}
						}
					}
				}

				$body = $dom->getElementsByTagName( 'body' )->item( 0 );
				if ( $body ) {
					$block_content = '';
					foreach ( $body->childNodes as $node ) {
						$block_content .= $dom->saveHTML( $node );
					}
				}
			}

			if ( function_exists( 'libxml_clear_errors' ) ) {
				libxml_clear_errors();
			}
		}

		return $block_content;
	}

	/**
	 * Filter post date blocks - Updated to respect block's format attribute
	 */
	public function filter_post_date_block( $block_content, $block ) {
		$block_format = isset( $block['attrs']['format'] ) ? $block['attrs']['format'] : null;
		$pattern      = '/<time\s+datetime="([^"]*)"\s*>(.*?)<\/time>/is';

		return preg_replace_callback( $pattern, function ( $matches ) use ( $block_format ) {
			$datetime = $matches[1];
			$content  = $matches[2];

			$timestamp = strtotime( $datetime ) * 1000;
			$options   = get_option( 'intl_datetime_calendar_settings', array(
				'calendar_type' => 'gregory',
			) );

			$output = '<time datetime="' . esc_attr( $datetime ) . '" ';
			$output .= 'class="intl-datetime-element" ';
			$output .= 'data-intl-datetime="' . esc_attr( $timestamp ) . '" ';
			$output .= 'data-calendar="' . esc_attr( $options['calendar_type'] ) . '" ';

			if ( $block_format ) {
				$output .= 'data-date-format="custom" ';
				$output .= 'data-custom-format="' . esc_attr( $block_format ) . '" ';
			} else {
				$output .= 'data-date-format="wp" ';
			}

			$output .= 'data-time-format="none">';
			$output .= $content;
			$output .= '</time>';

			return $output;
		}, $block_content );
	}

	/**
	 * Filter post time blocks - Updated to respect block's format attribute
	 */
	public function filter_post_time_block( $block_content, $block ) {
		$block_format = isset( $block['attrs']['format'] ) ? $block['attrs']['format'] : null;
		$pattern      = '/<time\s+datetime="([^"]*)"\s*>(.*?)<\/time>/is';

		return preg_replace_callback( $pattern, function ( $matches ) use ( $block_format ) {
			$datetime = $matches[1];
			$content  = $matches[2];

			$timestamp = strtotime( $datetime ) * 1000;
			$options   = get_option( 'intl_datetime_calendar_settings', array(
				'calendar_type' => 'gregory',
			) );

			$output = '<time datetime="' . esc_attr( $datetime ) . '" ';
			$output .= 'class="intl-datetime-element" ';
			$output .= 'data-intl-datetime="' . esc_attr( $timestamp ) . '" ';
			$output .= 'data-calendar="' . esc_attr( $options['calendar_type'] ) . '" ';
			$output .= 'data-date-format="none" ';

			if ( $block_format ) {
				$output .= 'data-time-format="custom" ';
				$output .= 'data-custom-format="' . esc_attr( $block_format ) . '" ';
			} else {
				$output .= 'data-time-format="wp" ';
			}

			$output .= '>';
			$output .= $content;
			$output .= '</time>';

			return $output;
		}, $block_content );
	}

	/**
	 * Filter modified date/time blocks - Updated to respect block's format
	 */
	public function filter_modified_date_blocks( $block_content, $block ) {
		if ( $block['blockName'] === 'core/post-modified-date' || $block['blockName'] === 'core/post-modified-time' ) {
			$block_format = isset( $block['attrs']['format'] ) ? $block['attrs']['format'] : null;
			$pattern      = '/<time\s+datetime="([^"]*)"\s*>(.*?)<\/time>/is';

			return preg_replace_callback( $pattern, function ( $matches ) use ( $block, $block_format ) {
				$datetime = $matches[1];
				$content  = $matches[2];

				$timestamp = strtotime( $datetime ) * 1000;
				$options   = get_option( 'intl_datetime_calendar_settings', array(
					'calendar_type' => 'gregory',
				) );

				$isDateBlock = $block['blockName'] === 'core/post-modified-date';
				$isTimeBlock = $block['blockName'] === 'core/post-modified-time';

				$output = '<time datetime="' . esc_attr( $datetime ) . '" ';
				$output .= 'class="intl-datetime-element" ';
				$output .= 'data-intl-datetime="' . esc_attr( $timestamp ) . '" ';
				$output .= 'data-calendar="' . esc_attr( $options['calendar_type'] ) . '" ';

				// Handle date format
				if ( $isDateBlock ) {
					if ( $block_format ) {
						$output .= 'data-date-format="custom" ';
						$output .= 'data-custom-format="' . esc_attr( $block_format ) . '" ';
					} else {
						$output .= 'data-date-format="wp" ';
					}
					$output .= 'data-time-format="none" ';
				}

				// Handle time format
				if ( $isTimeBlock ) {
					$output .= 'data-date-format="none" ';
					if ( $block_format ) {
						$output .= 'data-time-format="custom" ';
						$output .= 'data-custom-format="' . esc_attr( $block_format ) . '" ';
					} else {
						$output .= 'data-time-format="wp" ';
					}
				}

				$output .= '>';
				$output .= $content;
				$output .= '</time>';

				return $output;
			}, $block_content );
		}

		return $block_content;
	}

	/**
	 * Format a time element for a general WordPress element
	 * Helper function to create consistent time element markup
	 */
	public function create_time_element( $timestamp, $content, $is_date = true, $is_time = false ) {
		$options = get_option( 'intl_datetime_calendar_settings', array(
			'calendar_type' => 'gregory',
		) );

		$date = new DateTime();
		$date->setTimestamp( $timestamp / 1000 );
		$iso_datetime = $date->format( 'c' ); // ISO 8601 format

		$output = '<time datetime="' . esc_attr( $iso_datetime ) . '" ';
		$output .= 'class="intl-datetime-element" ';
		$output .= 'data-intl-datetime="' . esc_attr( $timestamp ) . '" ';
		$output .= 'data-calendar="' . esc_attr( $options['calendar_type'] ) . '" ';
		$output .= 'data-date-format="' . ( $is_date ? 'wp' : 'none' ) . '" ';
		$output .= 'data-time-format="' . ( $is_time ? 'wp' : 'none' ) . '">';
		$output .= $content;
		$output .= '</time>';

		return $output;
	}

	/**
	 * Public helper function to generate a time element with proper attributes
	 * This can be used directly in themes or other plugins
	 * @throws Exception
	 */
	public function format_date( $date_string, $is_date = true, $is_time = false ) {
		$timestamp = strtotime( $date_string ) * 1000;
		if ( ! $timestamp ) {
			return $date_string;
		}


		$date_obj = new DateTime( $date_string );
		if ( $is_date && ! $is_time ) {
			// Date only
			$display_content = $date_obj->format( get_option( 'date_format' ) );
		} elseif ( ! $is_date && $is_time ) {
			// Time only
			$display_content = $date_obj->format( get_option( 'time_format' ) );
		} else {
			// Both date and time
			$display_content = $date_obj->format( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) );
		}

		return $this->create_time_element( $timestamp, $display_content, $is_date, $is_time );
	}

	/**
	 * Create a shortcode for manual integration
	 *
	 * [intl_datetime date="2025-05-04" type="date"]
	 * [intl_datetime date="2025-05-04 12:30:45" type="datetime"]
	 * [intl_datetime date="12:30:45" type="time"]
	 */
	public function intl_datetime_shortcode( $atts ) {
		$atts = shortcode_atts( array(
			'date' => current_time( 'mysql' ),
			'type' => 'date', // date, time, or datetime
		), $atts, 'intl_datetime' );

		$is_date = $atts['type'] === 'date' || $atts['type'] === 'datetime';
		$is_time = $atts['type'] === 'time' || $atts['type'] === 'datetime';

		return $this->format_date( $atts['date'], $is_date, $is_time );
	}
}

$intl_datetime_calendar = new Intl_DateTime_Calendar();