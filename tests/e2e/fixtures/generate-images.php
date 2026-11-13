<?php
/**
 * Generate the synthetic images used by the reference theme e2e checks.
 *
 * Usage: php tests/e2e/fixtures/generate-images.php
 *
 * The images are abstract fixtures, not a design reference: a neon cover, a
 * portrait placeholder and three gallery tiles. They only exist so the theme
 * can be rendered with independent images and a populated gallery.
 *
 * @package MyECardExtensions
 */

if ( ! function_exists( 'imagecreatetruecolor' ) ) {
	fwrite( STDERR, "GD is required to generate the fixtures.\n" );
	exit( 1 );
}

$directory = __DIR__;

/**
 * Vertical gradient helper.
 *
 * @param resource|\GdImage $image Canvas.
 * @param int               $width Width.
 * @param int               $height Height.
 * @param array             $from  Start RGB.
 * @param array             $to    End RGB.
 * @return void
 */
function my_ecard_gradient( $image, int $width, int $height, array $from, array $to ): void {
	for ( $y = 0; $y < $height; $y++ ) {
		$ratio = $height > 1 ? $y / ( $height - 1 ) : 0;
		$red   = (int) round( $from[0] + ( $to[0] - $from[0] ) * $ratio );
		$green = (int) round( $from[1] + ( $to[1] - $from[1] ) * $ratio );
		$blue  = (int) round( $from[2] + ( $to[2] - $from[2] ) * $ratio );
		$color = imagecolorallocate( $image, $red, $green, $blue );
		imageline( $image, 0, $y, $width, $y, $color );
	}
}

/*
 * Cover: 1200x600 neon abstraction with light strips and bokeh.
 */
$cover = imagecreatetruecolor( 1200, 600 );
my_ecard_gradient( $cover, 1200, 600, array( 11, 20, 48 ), array( 38, 12, 61 ) );

for ( $i = 0; $i < 7; $i++ ) {
	$x     = 90 + $i * 165;
	$alpha = 96 - ( $i % 3 ) * 22;
	$color = imagecolorallocatealpha( $cover, 99, 102, 241, $alpha );
	imagefilledrectangle( $cover, $x, 0, $x + 26, 600, $color );
}

for ( $i = 0; $i < 40; $i++ ) {
	$radius = random_int( 6, 26 );
	$alpha  = random_int( 40, 110 );
	$color  = imagecolorallocatealpha( $cover, 56, 189, 248, $alpha );
	$x      = random_int( 0, 1200 );
	$y      = random_int( 0, 600 );
	imagefilledellipse( $cover, $x, $y, $radius * 2, $radius * 2, $color );
}

$vignette = imagecolorallocatealpha( $cover, 2, 6, 23, 70 );
imagefilledrectangle( $cover, 0, 0, 1200, 40, $vignette );
imagefilledrectangle( $cover, 0, 560, 1200, 600, $vignette );

imagejpeg( $cover, $directory . '/cover.jpg', 82 );
imagedestroy( $cover );

/*
 * Avatar: 400x400 portrait placeholder with a soft radial background.
 */
$avatar = imagecreatetruecolor( 400, 400 );

for ( $y = 0; $y < 400; $y++ ) {
	for ( $x = 0; $x < 400; $x += 4 ) {
		$distance = sqrt( pow( $x - 200, 2 ) + pow( $y - 160, 2 ) ) / 320;
		$distance = min( 1, $distance );
		$red      = (int) round( 219 - 120 * $distance );
		$green    = (int) round( 234 - 110 * $distance );
		$blue     = (int) round( 254 - 60 * $distance );
		$color    = imagecolorallocate( $avatar, $red, $green, $blue );
		imagefilledrectangle( $avatar, $x, $y, $x + 4, $y, $color );
	}
}

$ink      = imagecolorallocate( $avatar, 30, 41, 59 );
$shoulder = imagecolorallocate( $avatar, 51, 65, 85 );

imagefilledellipse( $avatar, 200, 158, 150, 150, $ink );
imagefilledellipse( $avatar, 200, 390, 280, 260, $shoulder );

imagejpeg( $avatar, $directory . '/avatar.jpg', 86 );
imagedestroy( $avatar );

/*
 * Gallery: three 900x700 tiles with distinct abstract compositions so the
 * gallery checks can tell them apart by content, not just by file name.
 */
$gallery = imagecreatetruecolor( 900, 700 );
my_ecard_gradient( $gallery, 900, 700, array( 255, 94, 58 ), array( 126, 34, 111 ) );

for ( $i = 0; $i < 5; $i++ ) {
	$radius = 60 + $i * 34;
	$alpha  = 70 - $i * 10;
	$color  = imagecolorallocatealpha( $gallery, 255, 237, 213, $alpha );
	imagefilledellipse( $gallery, 700 - $i * 18, 180 + $i * 12, $radius * 2, $radius * 2, $color );
}

$bar = imagecolorallocatealpha( $gallery, 24, 15, 46, 84 );
imagefilledrectangle( $gallery, 80, 520, 300, 560, $bar );
imagefilledrectangle( $gallery, 80, 592, 420, 632, $bar );

imagejpeg( $gallery, $directory . '/gallery-1.jpg', 84 );
imagedestroy( $gallery );

$gallery = imagecreatetruecolor( 900, 700 );
my_ecard_gradient( $gallery, 900, 700, array( 15, 118, 110 ), array( 6, 27, 58 ) );

for ( $i = 0; $i < 9; $i++ ) {
	$offset = -200 + $i * 130;
	$color  = imagecolorallocatealpha( $gallery, 153, 246, 228, 88 );
	imageline( $gallery, $offset, 700, $offset + 380, 0, $color );
}

$core = imagecolorallocatealpha( $gallery, 8, 47, 73, 46 );
imagefilledellipse( $gallery, 300, 350, 300, 300, $core );
imagefilledrectangle( $gallery, 640, 120, 800, 260, $core );

imagejpeg( $gallery, $directory . '/gallery-2.jpg', 84 );
imagedestroy( $gallery );

$gallery = imagecreatetruecolor( 900, 700 );
my_ecard_gradient( $gallery, 900, 700, array( 67, 56, 202 ), array( 14, 116, 144 ) );

for ( $i = 0; $i < 6; $i++ ) {
	$y     = 90 + $i * 90;
	$alpha = 92 - $i * 12;
	$color = imagecolorallocatealpha( $gallery, 224, 231, 255, $alpha );
	imagefilledrectangle( $gallery, 60, $y, 840, $y + 22, $color );
}

for ( $row = 0; $row < 3; $row++ ) {
	for ( $column = 0; $column < 4; $column++ ) {
		$x     = 560 + $column * 62;
		$y     = 430 + $row * 62;
		$color = imagecolorallocatealpha( $gallery, 165, 243, 252, 60 );
		imagefilledrectangle( $gallery, $x, $y, $x + 40, $y + 40, $color );
	}
}

imagejpeg( $gallery, $directory . '/gallery-3.jpg', 84 );
imagedestroy( $gallery );

echo 'cover.jpg ' . filesize( $directory . '/cover.jpg' ) . " bytes\n";
echo 'avatar.jpg ' . filesize( $directory . '/avatar.jpg' ) . " bytes\n";
echo 'gallery-1.jpg ' . filesize( $directory . '/gallery-1.jpg' ) . " bytes\n";
echo 'gallery-2.jpg ' . filesize( $directory . '/gallery-2.jpg' ) . " bytes\n";
echo 'gallery-3.jpg ' . filesize( $directory . '/gallery-3.jpg' ) . " bytes\n";
