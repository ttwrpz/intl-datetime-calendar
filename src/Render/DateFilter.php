<?php
/**
 * Converts dates at the points WordPress formats them for display.
 *
 * @package Intl_DateTime_Calendar
 */

namespace Intl_DateTime_Calendar\Render;

use DateTimeImmutable;
use DateTimeZone;
use Intl_DateTime_Calendar\Format\DateFormatter;
use Throwable;
use WP_Comment;
use WP_Post;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Replaces formatted dates through the display filters.
 *
 * These hooks are the ones that carry intent. get_the_date() means a date
 * meant to be read, so converting it is safe. wp_date() means any date at
 * all, and 2.0.2 hooked that instead: a plugin calling wp_date( 'Y-m-d' ) to
 * build a database key received 2569-08-29, stored Buddhist Era rows, and a
 * retention job comparing against a 2569 cutoff deleted its entire history.
 *
 * Filtering by format cannot rescue that approach. A site is free to set its
 * date format to Y-m-d, so the same string is both its display format and the
 * shape every storage key uses, and no allow list can tell the two apart. The
 * calling function is the only reliable signal, so that is what is hooked.
 */
final class DateFilter {

	/**
	 * Formats that exist to be parsed rather than read.
	 *
	 * The core date block builds its datetime attribute with get_the_date('c'),
	 * which arrives through these filters like any other format.
	 */
	private const MACHINE_FORMATS = array( 'c', 'r', 'U' );

	/**
	 * Register the display filters.
	 */
	public static function register(): void {
		add_filter( 'get_the_date', array( self::class, 'filter_post_date' ), 10, 3 );
		add_filter( 'get_the_time', array( self::class, 'filter_post_time' ), 10, 3 );
		add_filter( 'get_the_modified_date', array( self::class, 'filter_modified_date' ), 10, 3 );
		add_filter( 'get_the_modified_time', array( self::class, 'filter_modified_time' ), 10, 3 );
		add_filter( 'get_comment_date', array( self::class, 'filter_comment_date' ), 10, 3 );
		add_filter( 'get_comment_time', array( self::class, 'filter_comment_time' ), 10, 5 );
	}

	/**
	 * Convert a post's publication date.
	 *
	 * @param mixed  $date   Date as WordPress formatted it.
	 * @param string $format PHP date format, empty for the site default.
	 * @param mixed  $post   Post the date belongs to.
	 *
	 * @return mixed Converted date, or the original.
	 */
	public static function filter_post_date( $date, $format = '', $post = null ) {
		return self::convert_post( $date, $format, $post, 'date', 'date_format' );
	}

	/**
	 * Convert a post's publication time.
	 *
	 * @param mixed  $time   Time as WordPress formatted it.
	 * @param string $format PHP date format, empty for the site default.
	 * @param mixed  $post   Post the time belongs to.
	 *
	 * @return mixed Converted time, or the original.
	 */
	public static function filter_post_time( $time, $format = '', $post = null ) {
		return self::convert_post( $time, $format, $post, 'date', 'time_format' );
	}

	/**
	 * Convert a post's modified date.
	 *
	 * @param mixed  $date   Date as WordPress formatted it.
	 * @param string $format PHP date format, empty for the site default.
	 * @param mixed  $post   Post the date belongs to.
	 *
	 * @return mixed Converted date, or the original.
	 */
	public static function filter_modified_date( $date, $format = '', $post = null ) {
		return self::convert_post( $date, $format, $post, 'modified', 'date_format' );
	}

	/**
	 * Convert a post's modified time.
	 *
	 * @param mixed  $time   Time as WordPress formatted it.
	 * @param string $format PHP date format, empty for the site default.
	 * @param mixed  $post   Post the time belongs to.
	 *
	 * @return mixed Converted time, or the original.
	 */
	public static function filter_modified_time( $time, $format = '', $post = null ) {
		return self::convert_post( $time, $format, $post, 'modified', 'time_format' );
	}

	/**
	 * Convert a comment's date.
	 *
	 * @param mixed  $date    Date as WordPress formatted it.
	 * @param string $format  PHP date format, empty for the site default.
	 * @param mixed  $comment Comment the date belongs to.
	 *
	 * @return mixed Converted date, or the original.
	 */
	public static function filter_comment_date( $date, $format = '', $comment = null ) {
		return self::convert_comment( $date, $format, $comment, false, true, 'date_format' );
	}

	/**
	 * Convert a comment's time.
	 *
	 * @param mixed  $time      Time as WordPress formatted it.
	 * @param string $format    PHP date format, empty for the site default.
	 * @param bool   $gmt       Whether the GMT date is in use.
	 * @param bool   $translate Whether the caller wanted a readable value.
	 * @param mixed  $comment   Comment the time belongs to.
	 *
	 * @return mixed Converted time, or the original.
	 */
	public static function filter_comment_time( $time, $format = '', $gmt = false, $translate = true, $comment = null ) {
		return self::convert_comment( $time, $format, $comment, (bool) $gmt, (bool) $translate, 'time_format' );
	}

	/**
	 * Convert a date belonging to a post.
	 *
	 * @param mixed  $value  Value WordPress produced.
	 * @param string $format PHP date format, empty for the site default.
	 * @param mixed  $post   Post the date belongs to.
	 * @param string $field  Either 'date' or 'modified'.
	 * @param string $option Option holding the default format.
	 *
	 * @return mixed Converted value, or the original.
	 */
	private static function convert_post( $value, $format, $post, string $field, string $option ) {
		$format = self::resolve_format( $format, $option );

		if ( ! is_string( $value ) || ! self::should_convert( $format ) ) {
			return $value;
		}

		if ( ! $post instanceof WP_Post || ! function_exists( 'get_post_datetime' ) ) {
			return $value;
		}

		$moment = get_post_datetime( $post, $field );

		return $moment instanceof DateTimeImmutable ? self::render( $moment, $format, $value ) : $value;
	}

	/**
	 * Convert a date belonging to a comment.
	 *
	 * @param mixed  $value     Value WordPress produced.
	 * @param string $format    PHP date format, empty for the site default.
	 * @param mixed  $comment   Comment the date belongs to.
	 * @param bool   $gmt       Whether the GMT date is in use.
	 * @param bool   $translate Whether the caller wanted a readable value.
	 * @param string $option    Option holding the default format.
	 *
	 * @return mixed Converted value, or the original.
	 */
	private static function convert_comment( $value, $format, $comment, bool $gmt, bool $translate, string $option ) {
		$format = self::resolve_format( $format, $option );

		// A caller that asked not to translate wants the raw value.
		if ( ! $translate || ! is_string( $value ) || ! self::should_convert( $format ) ) {
			return $value;
		}

		if ( ! $comment instanceof WP_Comment ) {
			return $value;
		}

		$stored = $gmt ? $comment->comment_date_gmt : $comment->comment_date;
		$zone   = $gmt ? new DateTimeZone( 'UTC' ) : DateFormatter::timezone();

		try {
			$moment = new DateTimeImmutable( (string) $stored, $zone );
		} catch ( Throwable $e ) {
			return $value;
		}

		return self::render( $moment, $format, $value );
	}

	/**
	 * Render a moment, falling back to what WordPress produced.
	 *
	 * @param DateTimeImmutable $moment   Moment to render.
	 * @param string            $format   PHP date format string.
	 * @param string            $fallback Value WordPress produced.
	 *
	 * @return string Converted date, or the fallback.
	 */
	private static function render( DateTimeImmutable $moment, string $format, string $fallback ): string {
		$converted = DateFormatter::render( $moment, $format );

		return '' === $converted ? $fallback : $converted;
	}

	/**
	 * Fill in the site's format when the caller did not name one.
	 *
	 * @param mixed  $format Format the caller passed.
	 * @param string $option Option holding the default.
	 *
	 * @return string PHP date format string.
	 */
	private static function resolve_format( $format, string $option ): string {
		if ( is_string( $format ) && '' !== $format ) {
			return $format;
		}

		$default = 'time_format' === $option ? 'g:i a' : 'F j, Y';

		return (string) get_option( $option, $default );
	}

	/**
	 * Whether a date in this context should be converted.
	 *
	 * @param string $format PHP date format string.
	 *
	 * @return bool True when the date should be rewritten.
	 */
	private static function should_convert( string $format ): bool {
		$convert = '' !== $format
			&& ! in_array( $format, self::MACHINE_FORMATS, true )
			&& DateFormatter::can_render_server()
			&& ! self::is_machine_request();

		/**
		 * Filters whether a formatted date is converted to the chosen calendar.
		 *
		 * Return false to leave a date in the Gregorian calendar.
		 *
		 * @param bool   $convert Whether to convert this date.
		 * @param string $format  PHP date format string.
		 */
		return (bool) apply_filters( 'intl_datetime_calendar_should_convert', $convert, $format );
	}

	/**
	 * Whether this request produces data rather than a page someone reads.
	 *
	 * Nothing here formats a date for a person, and a scheduled job writing a
	 * converted date into a table is how the 2.0.2 data loss happened, so
	 * these are left alone even though the hooks above are display hooks.
	 *
	 * @return bool True when the request is not a page render.
	 */
	private static function is_machine_request(): bool {
		if ( is_admin() ) {
			return true;
		}

		if ( function_exists( 'wp_doing_cron' ) && wp_doing_cron() ) {
			return true;
		}

		if ( function_exists( 'wp_doing_ajax' ) && wp_doing_ajax() ) {
			return true;
		}

		if ( function_exists( 'wp_is_json_request' ) && wp_is_json_request() ) {
			return true;
		}

		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return true;
		}

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			return true;
		}

		if ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) {
			return true;
		}

		return function_exists( 'is_feed' ) && is_feed();
	}
}
