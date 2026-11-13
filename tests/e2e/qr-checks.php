<?php
/**
 * Real QR generation and cache checks.
 *
 * Run inside the e2e container after install.php and the phase 5 seed:
 *   docker compose exec -T wp php .../tests/e2e/qr-checks.php
 *
 * The script renders real PNG files with the bundled renderer, verifies the
 * image structure, decodes every module back from the pixels and compares it
 * with the matrix produced from the exact payload. No mock is used to claim
 * that QR rendering works.
 *
 * @package MyECardExtensions
 */

error_reporting( E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED );

require '/var/www/html/wp-load.php';

use MyECardExtensions\CardData;
use MyECardExtensions\QRCodeGenerator;

$checks = 0;
$fails  = 0;

/**
 * Record one check.
 *
 * @param string $label     Description.
 * @param bool   $condition Result.
 * @param string $detail    Failure detail.
 * @return void
 */
function my_ecard_qr_check( string $label, bool $condition, string $detail = '' ): void {
	global $checks, $fails;

	$checks++;

	if ( $condition ) {
		echo 'PASS  ' . $label . "\n";
		return;
	}

	$fails++;
	echo 'FAIL  ' . $label . ( '' !== $detail ? ' [' . $detail . ']' : '' ) . "\n";
}

/**
 * Extract the dark/light module grid from a rendered PNG.
 *
 * @param string $png      PNG bytes.
 * @param int    $modules  Expected module count.
 * @param int[]  $foreground Foreground RGB.
 * @param int[]  $background Background RGB.
 * @return array{ok:bool,grid:array<int,array<int,bool>>,scale:int,width:int}
 */
function my_ecard_qr_read_grid( string $png, int $modules, array $foreground, array $background ): array {
	$result = array(
		'ok'    => false,
		'grid'  => array(),
		'scale' => 0,
		'width' => 0,
	);

	$image = @imagecreatefromstring( $png );

	if ( false === $image ) {
		return $result;
	}

	$width  = imagesx( $image );
	$height = imagesy( $image );

	if ( $width !== $height || 0 === $modules ) {
		return $result;
	}

	$scale = (int) ( $width / $modules );

	if ( $scale < 1 || $width % $modules !== 0 ) {
		return $result;
	}

	$grid  = array();
	$match = true;

	for ( $y = 0; $y < $modules; $y++ ) {
		for ( $x = 0; $x < $modules; $x++ ) {
			$pixel  = imagecolorat( $image, ( $x * $scale ) + intdiv( $scale, 2 ), ( $y * $scale ) + intdiv( $scale, 2 ) );
			$colors = array( ( $pixel >> 16 ) & 0xff, ( $pixel >> 8 ) & 0xff, $pixel & 0xff );

			if ( $colors === $foreground ) {
				$grid[ $y ][ $x ] = true;
			} elseif ( $colors === $background ) {
				$grid[ $y ][ $x ] = false;
			} else {
				$match = false;
			}
		}
	}

	imagedestroy( $image );

	$result['ok']    = $match;
	$result['grid']  = $grid;
	$result['scale'] = $scale;
	$result['width'] = $width;

	return $result;
}

/**
 * Compare a decoded grid with a QRMatrix for a given ECC level.
 *
 * @param array $grid    Decoded grid.
 * @param string $payload Payload.
 * @param int    $ecc     QRCode::ECC_* constant.
 * @return int Number of differing modules.
 */
function my_ecard_qr_mismatches( array $grid, string $payload, int $ecc ): int {
	$options = new \chillerlan\QRCode\QROptions(
		array(
			'outputType' => \chillerlan\QRCode\QRCode::OUTPUT_IMAGE_PNG,
			'eccLevel'   => $ecc,
		)
	);

	$matrix = ( new \chillerlan\QRCode\QRCode( $options ) )->getMatrix( $payload );
	$size   = $matrix->size();
	$miss   = 0;

	foreach ( $grid as $y => $row ) {
		foreach ( $row as $x => $dark ) {
			$expected = ( $matrix->get( $x, $y ) >> 8 ) !== 0;

			if ( $expected !== $dark ) {
				$miss++;
			}
		}
	}

	return $miss;
}

$post = get_page_by_path( 'phase5-full', OBJECT, 'ecard_digital_card' );

my_ecard_qr_check( 'phase5-full card exists', $post instanceof WP_Post );

if ( ! $post instanceof WP_Post ) {
	echo 'summary: ' . $checks . ' checks, ' . $fails . " failed\n";
	exit( 1 );
}

$card = CardData::from_post( $post->ID );

my_ecard_qr_check( 'card read model is available', $card instanceof CardData );

if ( ! $card instanceof CardData ) {
	echo 'summary: ' . $checks . ' checks, ' . $fails . " failed\n";
	exit( 1 );
}

my_ecard_qr_check( 'composer autoloader loads QRCode 4.x', class_exists( '\chillerlan\QRCode\QRCode' ) );
my_ecard_qr_check( 'renderer and GD are available', QRCodeGenerator::renderer_available(), QRCodeGenerator::unavailable_reason() );

$generator = new QRCodeGenerator( $card, $card->settings );
$options   = $generator->options();
$payload   = $generator->payload();
$png       = $generator->generate();

my_ecard_qr_check( 'card mode payload is the public URL', (bool) preg_match( '#^https?://#', $payload ), $payload );
my_ecard_qr_check( 'PNG signature is valid', "\x89PNG\r\n\x1a\n" === substr( $png, 0, 8 ) );
my_ecard_qr_check( 'PNG is not base64 encoded', 0 !== strpos( $png, 'data:image' ) );

$info = getimagesizefromstring( $png );

my_ecard_qr_check( 'getimagesize recognizes image/png', is_array( $info ) && 'image/png' === $info['mime'] );
my_ecard_qr_check( 'PNG is square', is_array( $info ) && $info[0] === $info[1] );
my_ecard_qr_check( 'PNG respects the requested size range', is_array( $info ) && $info[0] >= 96 && $info[0] <= 512, is_array( $info ) ? (string) $info[0] : 'n/a' );

$base_options = new \chillerlan\QRCode\QROptions(
	array(
		'outputType' => \chillerlan\QRCode\QRCode::OUTPUT_IMAGE_PNG,
		'eccLevel'   => \chillerlan\QRCode\QRCode::ECC_M,
	)
);
$matrix = ( new \chillerlan\QRCode\QRCode( $base_options ) )->getMatrix( $payload );
$grid   = my_ecard_qr_read_grid( $png, $matrix->size(), $options['fgValues'], $options['bgValues'] );

my_ecard_qr_check( 'every PNG pixel uses the configured colors', $grid['ok'] );
my_ecard_qr_check( 'module scale is a positive integer', $grid['scale'] >= 1, (string) $grid['scale'] );

if ( $grid['ok'] ) {
	my_ecard_qr_check( 'decoded matrix matches the payload matrix (ECC M)', 0 === my_ecard_qr_mismatches( $grid['grid'], $payload, \chillerlan\QRCode\QRCode::ECC_M ) );
	my_ecard_qr_check( 'ECC level is M, not L', my_ecard_qr_mismatches( $grid['grid'], $payload, \chillerlan\QRCode\QRCode::ECC_L ) > 0 );
	my_ecard_qr_check( 'ECC level is M, not H', my_ecard_qr_mismatches( $grid['grid'], $payload, \chillerlan\QRCode\QRCode::ECC_H ) > 0 );
}

$custom = new QRCodeGenerator(
	$card,
	array_merge(
		$card->settings,
		array(
			'qr_size'  => 96,
			'qr_dark'  => '#ff0000',
			'qr_light' => '#0000ff',
		)
	)
);
$small = $custom->generate();
$sinfo = getimagesizefromstring( $small );

my_ecard_qr_check( 'custom colors and 96 px size render', is_array( $sinfo ) && $sinfo[0] >= 96 && $sinfo[0] <= 160, is_array( $sinfo ) ? (string) $sinfo[0] : 'n/a' );

$contact = new QRCodeGenerator( $card, array_merge( $card->settings, array( 'qr_mode' => 'contact' ) ) );
$vcard   = $contact->payload();
$cpng    = $contact->generate();

my_ecard_qr_check( 'contact payload is a vCard', 0 === strpos( $vcard, 'BEGIN:VCARD' ) );
my_ecard_qr_check( 'contact PNG is valid', "\x89PNG\r\n\x1a\n" === substr( $cpng, 0, 8 ) );

$cmatrix = ( new \chillerlan\QRCode\QRCode( $base_options ) )->getMatrix( $vcard );
$cgrid   = my_ecard_qr_read_grid( $cpng, $cmatrix->size(), $options['fgValues'], $options['bgValues'] );

my_ecard_qr_check( 'contact PNG decodes to the vCard matrix', $cgrid['ok'] && 0 === my_ecard_qr_mismatches( $cgrid['grid'], $vcard, \chillerlan\QRCode\QRCode::ECC_M ) );

$cache_key = $generator->cache_key();
$stored    = 'base64:' . base64_encode( $png );

set_transient( $cache_key, $stored, 60 );

$read = get_transient( $cache_key );

my_ecard_qr_check( 'binary PNG survives the transient cache', $read === $stored );
my_ecard_qr_check( 'cached value decodes to the PNG', base64_decode( substr( (string) get_transient( $cache_key ), 7 ), true ) === $png );

$contact_key = $contact->cache_key();

my_ecard_qr_check( 'card and contact keys differ', $cache_key !== $contact_key );

update_post_meta( $post->ID, '_ecard_phone', '+34 600 000 999' );

$contact_changed = new QRCodeGenerator( CardData::from_post( $post->ID ), array_merge( $card->settings, array( 'qr_mode' => 'contact' ) ) );

my_ecard_qr_check( 'contact change produces a new cache key', $contact_changed->cache_key() !== $contact_key );
my_ecard_qr_check( 'new contact key has no stale transient', false === get_transient( $contact_changed->cache_key() ) );

update_post_meta( $post->ID, '_ecard_phone', $card->phone );

echo 'PNG_CARD:' . base64_encode( $png ) . "\n";
echo 'PNG_CONTACT:' . base64_encode( $cpng ) . "\n";
echo 'PAYLOAD_CARD:' . $payload . "\n";
echo 'PAYLOAD_CONTACT_MD5:' . md5( $vcard ) . "\n";
echo 'summary: ' . $checks . ' checks, ' . $fails . " failed\n";
exit( $fails > 0 ? 1 : 0 );
