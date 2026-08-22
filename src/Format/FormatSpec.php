<?php
/**
 * Tokenizer for WordPress (PHP) date format strings.
 *
 * @package Intl_DateTime_Calendar
 */

namespace Intl_DateTime_Calendar\Format;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Splits a PHP date format string into literals and fields.
 *
 * The single source of truth for how a format is understood. The JavaScript
 * renderer implements the same algorithm, and tests/fixtures/formats.json
 * keeps the two honest.
 */
final class FormatSpec {

	public const LITERAL = 'literal';
	public const FIELD   = 'field';

	/**
	 * Recognised format characters. Anything else is emitted as a literal,
	 * which is what PHP does too.
	 */
	private const FIELD_CHARS = 'dDjlNSwzWFmMntLoXxYyaABgGhHisuveIOPpTZcrU';

	/**
	 * Tokenize a PHP date format string.
	 *
	 * Adjacent literals are merged so both renderers see the same token list.
	 *
	 * @param string $format PHP date format string, e.g. 'F j, Y'.
	 *
	 * @return array<int, array{type: string, value: string}> Ordered token list.
	 */
	public static function tokenize( string $format ): array {
		$tokens = array();
		$length = strlen( $format );

		for ( $i = 0; $i < $length; $i++ ) {
			$char = $format[ $i ];

			// A backslash escapes the next byte into literal text.
			if ( '\\' === $char ) {
				if ( $i + 1 < $length ) {
					++$i;
					self::push_literal( $tokens, $format[ $i ] );
				}
				continue;
			}

			if ( false !== strpos( self::FIELD_CHARS, $char ) ) {
				$tokens[] = array(
					'type'  => self::FIELD,
					'value' => $char,
				);
				continue;
			}

			self::push_literal( $tokens, $char );
		}

		return $tokens;
	}

	/**
	 * Append literal text, merging into the previous token when possible.
	 *
	 * @param array<int, array{type: string, value: string}> $tokens Token list, modified in place.
	 * @param string                                         $text   Literal text to append.
	 */
	private static function push_literal( array &$tokens, string $text ): void {
		$last = count( $tokens ) - 1;

		if ( $last >= 0 && self::LITERAL === $tokens[ $last ]['type'] ) {
			$tokens[ $last ]['value'] .= $text;

			return;
		}

		$tokens[] = array(
			'type'  => self::LITERAL,
			'value' => $text,
		);
	}

	/**
	 * Whether a format string asks for any date field.
	 *
	 * @param string $format PHP date format string.
	 *
	 * @return bool True when at least one date field is present.
	 */
	public static function has_date( string $format ): bool {
		return self::has_any( $format, 'dDjlNSwzWFmMntLoXxYy' );
	}

	/**
	 * Whether a format string asks for any time field.
	 *
	 * @param string $format PHP date format string.
	 *
	 * @return bool True when at least one time field is present.
	 */
	public static function has_time( string $format ): bool {
		return self::has_any( $format, 'aABgGhHisuv' );
	}

	/**
	 * Whether any unescaped character of $set appears in $format.
	 *
	 * @param string $format PHP date format string.
	 * @param string $set    Characters to look for.
	 *
	 * @return bool True when one is present as a field.
	 */
	private static function has_any( string $format, string $set ): bool {
		foreach ( self::tokenize( $format ) as $token ) {
			if ( self::FIELD === $token['type'] && false !== strpos( $set, $token['value'] ) ) {
				return true;
			}
		}

		return false;
	}
}
