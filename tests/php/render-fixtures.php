<?php
/**
 * Renders the shared fixture matrix with the PHP engine, as JSON, so
 * tests/js/parity.test.js can compare it against the browser engine.
 *
 * @package Intl_DateTime_Calendar
 */

define( 'ABSPATH', __DIR__ );

require __DIR__ . '/../../src/Format/FormatSpec.php';
require __DIR__ . '/../../src/Format/CalendarRenderer.php';

use Intl_DateTime_Calendar\Format\CalendarRenderer;

$fixtures = json_decode( file_get_contents( __DIR__ . '/../fixtures/formats.json' ), true );
$results  = array();

foreach ( $fixtures['locales'] as $config ) {
	$renderer = new CalendarRenderer(
		$config['locale'],
		$config['calendar'],
		$config['numberingSystem'],
		new DateTimeZone( $config['timeZone'] )
	);

	foreach ( $fixtures['moments'] as $moment ) {
		$date = new DateTimeImmutable( $moment );

		foreach ( $fixtures['formats'] as $format ) {
			$key             = $config['locale'] . '|' . $config['calendar'] . '|' . $config['numberingSystem'] . '|' . $moment . '|' . $format;
			$results[ $key ] = $renderer->render( $date, $format );
		}
	}
}

echo wp_json_encode_fallback( $results );

/**
 * Encode JSON without depending on WordPress being loaded.
 *
 * @param mixed $data Data to encode.
 *
 * @return string JSON.
 */
function wp_json_encode_fallback( $data ) {
	return json_encode( $data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT );
}
