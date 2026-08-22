<?php
/**
 * The [intl_datetime] shortcode and the public formatting helper.
 *
 * @package Intl_DateTime_Calendar
 */

namespace Intl_DateTime_Calendar\Render;

use DateTimeImmutable;
use Exception;
use Intl_DateTime_Calendar\Format\DateFormatter;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders a single date supplied by an author or a theme.
 *
 * Input is validated strictly rather than handed to a permissive parser.
 * PHP reads "2025-02-31" and rolls it to 3 March, so a mistyped date would
 * silently become a different one, which is worse than being told it is wrong.
 */
final class Shortcode {

	/**
	 * Formats accepted for the date attribute, in the order they are tried.
	 *
	 * @var array<int, string>
	 */
	private const ACCEPTED_FORMATS = array(
		'Y-m-d H:i:s',
		'Y-m-d H:i',
		'Y-m-d',
		'Y/m/d H:i:s',
		'Y/m/d',
		'H:i:s',
		'H:i',
	);

	/**
	 * Register the shortcode.
	 */
	public static function register(): void {
		add_shortcode( 'intl_datetime', array( self::class, 'render' ) );
	}

	/**
	 * Render the shortcode.
	 *
	 * @param array<string, string>|string $atts Shortcode attributes.
	 *
	 * @return string Rendered HTML.
	 */
	public static function render( $atts ): string {
		$atts = shortcode_atts(
			array(
				'date'   => '',
				'type'   => 'date',
				'format' => '',
			),
			is_array( $atts ) ? $atts : array(),
			'intl_datetime'
		);

		$moment = self::parse( (string) $atts['date'] );

		if ( null === $moment ) {
			// Show the author what they typed rather than a wrong date.
			return esc_html( (string) $atts['date'] );
		}

		$format = self::resolve_format( (string) $atts['format'], (string) $atts['type'] );

		return self::element( $moment, $format );
	}

	/**
	 * Build a time element for a moment and format.
	 *
	 * @param DateTimeImmutable $date   Moment to render.
	 * @param string            $format PHP date format string.
	 *
	 * @return string Time element markup.
	 */
	public static function element( DateTimeImmutable $date, string $format ): string {
		$rendered = DateFormatter::render( $date, $format );
		$server   = '' !== $rendered;

		if ( ! $server ) {
			// No ext-intl: render Gregorian and let the browser convert.
			$rendered = $date->setTimezone( DateFormatter::timezone() )->format( $format );
		}

		$attributes = array(
			'datetime' => DateFormatter::iso( $date ),
			'class'    => DateFormatter::CSS_CLASS,
		) + DateFormatter::attributes( $date, $format, $server );

		$markup = '';
		foreach ( $attributes as $name => $value ) {
			$markup .= sprintf( ' %s="%s"', esc_attr( $name ), esc_attr( $value ) );
		}

		return '<time' . $markup . '>' . esc_html( $rendered ) . '</time>';
	}

	/**
	 * Choose the format for a shortcode invocation.
	 *
	 * @param string $format Explicit format attribute, possibly empty.
	 * @param string $type   One of date, time or datetime.
	 *
	 * @return string PHP date format string.
	 */
	private static function resolve_format( string $format, string $type ): string {
		if ( '' !== $format ) {
			return $format;
		}

		$date_format = (string) get_option( 'date_format', 'F j, Y' );
		$time_format = (string) get_option( 'time_format', 'g:i a' );

		switch ( $type ) {
			case 'time':
				return $time_format;
			case 'datetime':
				return $date_format . ' ' . $time_format;
			default:
				return $date_format;
		}
	}

	/**
	 * Parse a date attribute in the site's timezone.
	 *
	 * Empty means now. Anything else must match an accepted format exactly,
	 * with no rollover.
	 *
	 * @param string $value Raw date attribute.
	 *
	 * @return DateTimeImmutable|null Parsed moment, or null when invalid.
	 */
	private static function parse( string $value ): ?DateTimeImmutable {
		$value    = trim( wp_strip_all_tags( html_entity_decode( $value, ENT_QUOTES, 'UTF-8' ) ) );
		$timezone = DateFormatter::timezone();

		if ( '' === $value ) {
			try {
				return new DateTimeImmutable( 'now', $timezone );
			} catch ( Exception $e ) {
				return null;
			}
		}

		foreach ( self::ACCEPTED_FORMATS as $format ) {
			$parsed = DateTimeImmutable::createFromFormat( '!' . $format, $value, $timezone );

			if ( false === $parsed ) {
				continue;
			}

			$errors = DateTimeImmutable::getLastErrors();

			// A warning here means PHP accepted the string by adjusting it,
			// which is how "2025-02-31" becomes 3 March. Treat that as invalid.
			if ( is_array( $errors ) && ( ! empty( $errors['warning_count'] ) || ! empty( $errors['error_count'] ) ) ) {
				continue;
			}

			return $parsed;
		}

		return null;
	}
}
