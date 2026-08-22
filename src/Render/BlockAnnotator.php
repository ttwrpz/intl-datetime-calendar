<?php
/**
 * Marks up date elements for the browser when the server cannot render.
 *
 * @package Intl_DateTime_Calendar
 */

namespace Intl_DateTime_Calendar\Render;

use DateTimeImmutable;
use Exception;
use Intl_DateTime_Calendar\Format\DateFormatter;
use WP_HTML_Tag_Processor;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Annotates rendered `<time>` elements so the browser can convert them.
 *
 * Runs only where ext-intl is missing. Uses WP_HTML_Tag_Processor, which
 * edits attributes in place rather than parsing and reserializing, so
 * surrounding HTML is returned exactly as it came in.
 *
 * Annotation is idempotent: a date block nested in a Query block is rendered
 * first and then handed to the Query block's filter, so the same element is
 * seen more than once.
 */
final class BlockAnnotator {

	/**
	 * Blocks whose output can contain a date the plugin should convert.
	 *
	 * @var array<int, string>
	 */
	private const BLOCKS = array(
		'core/post-date',
		'core/comment-date',
		'core/latest-posts',
		'core/latest-comments',
		'core/query',
		'core/comments',
	);

	/**
	 * Register the filter.
	 */
	public static function register(): void {
		add_filter( 'render_block', array( self::class, 'annotate' ), 10, 2 );
	}

	/**
	 * Annotate the date elements in one rendered block.
	 *
	 * @param string               $block_content Rendered block HTML.
	 * @param array<string, mixed> $block         Parsed block.
	 *
	 * @return string Annotated HTML.
	 */
	public static function annotate( $block_content, $block ) {
		if ( ! is_string( $block_content ) || '' === $block_content ) {
			return $block_content;
		}

		// The server already produced the right text, so nothing to hand over.
		if ( DateFormatter::can_render_server() ) {
			return $block_content;
		}

		if ( ! class_exists( WP_HTML_Tag_Processor::class ) ) {
			return $block_content;
		}

		$name = isset( $block['blockName'] ) ? (string) $block['blockName'] : '';
		if ( ! in_array( $name, self::BLOCKS, true ) ) {
			return $block_content;
		}

		// Cheap reject before paying for the parser.
		if ( false === stripos( $block_content, '<time' ) ) {
			return $block_content;
		}

		$format    = self::format_for( $block );
		$processor = new WP_HTML_Tag_Processor( $block_content );

		while ( $processor->next_tag( array( 'tag_name' => 'TIME' ) ) ) {
			// Already annotated by the inner block that produced it.
			if ( null !== $processor->get_attribute( 'data-intl-format' ) ) {
				continue;
			}

			$datetime = $processor->get_attribute( 'datetime' );
			if ( ! is_string( $datetime ) || '' === $datetime ) {
				continue;
			}

			try {
				$moment = new DateTimeImmutable( $datetime );
			} catch ( Exception $e ) {
				continue;
			}

			foreach ( DateFormatter::attributes( $moment, $format, false ) as $attribute => $value ) {
				$processor->set_attribute( $attribute, $value );
			}

			$processor->add_class( DateFormatter::CSS_CLASS );
		}

		return $processor->get_updated_html();
	}

	/**
	 * Work out which format a block's dates were rendered with.
	 *
	 * @param array<string, mixed> $block Parsed block.
	 *
	 * @return string PHP date format string.
	 */
	private static function format_for( array $block ): string {
		$format = $block['attrs']['format'] ?? '';

		if ( is_string( $format ) && '' !== $format ) {
			return $format;
		}

		return (string) get_option( 'date_format', 'F j, Y' );
	}
}
