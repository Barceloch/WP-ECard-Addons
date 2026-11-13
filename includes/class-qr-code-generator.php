<?php
/**
 * QR code generation for public cards.
 *
 * @package MyECardExtensions
 */

namespace MyECardExtensions;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Raised when the optional QR renderer is not installed.
 *
 * The addon fails closed: no QR image is ever invented when the library is
 * missing, because a fake image would be worse than an explicit error.
 */
final class QRCodeUnavailableException extends \RuntimeException {
}

/**
 * Prepares QR payloads, options and cache keys, and renders PNG bytes.
 *
 * Rendering needs the optional chillerlan/php-qrcode library. Every other
 * method works without it, so the endpoint and the templates can reason about
 * availability before any output is attempted.
 */
final class QRCodeGenerator {

	/**
	 * Card read model.
	 *
	 * @var CardData
	 */
	private $card;

	/**
	 * Validated card settings.
	 *
	 * @var array
	 */
	private $settings;

	/**
	 * Constructor.
	 *
	 * @param CardData $card     Card read model.
	 * @param array    $settings Card settings; missing keys use the defaults.
	 */
	public function __construct( CardData $card, array $settings = array() ) {
		$this->card     = $card;
		$this->settings = array_merge( CardSettings::defaults(), $settings );
	}

	/**
	 * Whether the QR renderer library is actually loadable.
	 *
	 * This is the hard, non-filterable truth: it only reports what the PHP
	 * runtime can load. The library needs ext-mbstring at load time; the GD
	 * requirement belongs to the PNG renderer and is checked separately.
	 *
	 * @return bool
	 */
	public static function library_loaded(): bool {
		return class_exists( '\chillerlan\QRCode\QRCode' )
			&& class_exists( '\chillerlan\QRCode\QROptions' )
			&& extension_loaded( 'mbstring' );
	}

	/**
	 * Whether a PNG QR code can actually be rendered here.
	 *
	 * Both the library and the GD extension are required. Rendering checks
	 * this directly, so no filter can make generate() run on a runtime that
	 * cannot produce an image.
	 *
	 * @return bool
	 */
	public static function renderer_available(): bool {
		return self::library_loaded() && extension_loaded( 'gd' );
	}

	/**
	 * Machine-readable reason why rendering is unavailable.
	 *
	 * @return string Empty when rendering is possible; "library" or "gd" otherwise.
	 */
	public static function unavailable_reason(): string {
		if ( ! self::library_loaded() ) {
			return 'library';
		}

		if ( ! extension_loaded( 'gd' ) ) {
			return 'gd';
		}

		return '';
	}

	/**
	 * Whether QR codes are available for this site.
	 *
	 * Availability is the renderer being usable, but sites may switch the
	 * feature off (or report a custom state) through the
	 * `my_ecard_qr_available` filter. Templates and the endpoint use this
	 * decision; generate() deliberately uses renderer_available() instead.
	 *
	 * @return bool
	 */
	public static function is_available(): bool {
		return (bool) apply_filters( 'my_ecard_qr_available', self::renderer_available() );
	}

	/**
	 * Content encoded in the QR code.
	 *
	 * @return string
	 */
	public function payload(): string {
		if ( ! VCardGenerator::is_public( $this->card ) ) {
			throw new \RuntimeException( 'The card is not publicly available for QR generation.' );
		}

		if ( 'contact' === $this->mode() ) {
			return ( new VCardGenerator( $this->card ) )->render();
		}

		return (string) $this->card->url;
	}

	/**
	 * Mapped options for the renderer.
	 *
	 * "size" is the requested display size in pixels. It is translated into
	 * the library pixel-per-module scale once the matrix size is known, so the
	 * PNG never grows to thousands of pixels.
	 *
	 * @return array{size: int, fgValues: int[], bgValues: int[]}
	 */
	public function options(): array {
		return array(
			'size'     => clamp_int( $this->settings['qr_size'], 96, 512, 220 ),
			'fgValues' => self::hex_to_rgb( (string) $this->settings['qr_dark'], array( 15, 23, 42 ) ),
			'bgValues' => self::hex_to_rgb( (string) $this->settings['qr_light'], array( 255, 255, 255 ) ),
		);
	}

	/**
	 * Pixel-per-module scale that best fits a target size.
	 *
	 * @param int $target  Requested image size in pixels.
	 * @param int $modules Module count including the quiet zone.
	 * @return int At least 1.
	 */
	public static function scale_for( int $target, int $modules ): int {
		if ( $modules < 1 ) {
			return 1;
		}

		$scale = (int) round( $target / $modules );

		return max( 1, $scale );
	}

	/**
	 * Deterministic transient key for this card and QR configuration.
	 *
	 * The payload hash is part of the key: changing the public URL or any
	 * contact field invalidates the cached PNG instead of serving stale data
	 * for up to twelve hours.
	 *
	 * @return string
	 * @throws \RuntimeException When the card is not publicly available.
	 */
	public function cache_key(): string {
		$options = $this->options();
		$payload = $this->payload();

		return 'my_ecard_qr_' . md5(
			implode(
				'|',
				array(
					'v1',
					(string) (int) $this->card->id,
					$this->mode(),
					(string) $options['size'],
					implode( ',', $options['fgValues'] ),
					implode( ',', $options['bgValues'] ),
					md5( $payload ),
				)
			)
		);
	}

	/**
	 * Render the QR code as PNG bytes with chillerlan/php-qrcode 4.4.x.
	 *
	 * Colors are applied through module values, which is the 4.x mechanism.
	 * The scale is derived from the real matrix size so the requested display
	 * size is respected.
	 *
	 * @return string Raw PNG bytes.
	 * @throws QRCodeUnavailableException When the library or GD is missing.
	 * @throws \RuntimeException When the renderer produces no data.
	 */
	public function generate(): string {
		$reason = self::unavailable_reason();

		if ( '' !== $reason ) {
			throw new QRCodeUnavailableException(
				'gd' === $reason
					? 'The GD extension is not available for QR rendering.'
					: 'The QR code library is not installed.'
			);
		}

		$options       = $this->options();
		$payload       = $this->payload();
		$module_values = self::module_values( $options['fgValues'], $options['bgValues'] );

		$base_options = new \chillerlan\QRCode\QROptions(
			array(
				'outputType'         => \chillerlan\QRCode\QRCode::OUTPUT_IMAGE_PNG,
				'eccLevel'           => \chillerlan\QRCode\QRCode::ECC_M,
				'imageBase64'        => false,
				'imageTransparent'   => false,
				'imageTransparencyBG' => $options['bgValues'],
				'moduleValues'       => $module_values,
			)
		);

		$qr     = new \chillerlan\QRCode\QRCode( $base_options );
		$matrix = $qr->getMatrix( $payload );
		$scale  = self::scale_for( $options['size'], $matrix->size() );

		$qr_options = new \chillerlan\QRCode\QROptions(
			array(
				'outputType'         => \chillerlan\QRCode\QRCode::OUTPUT_IMAGE_PNG,
				'eccLevel'           => \chillerlan\QRCode\QRCode::ECC_M,
				'scale'              => $scale,
				'imageBase64'        => false,
				'imageTransparent'   => false,
				'imageTransparencyBG' => $options['bgValues'],
				'moduleValues'       => $module_values,
			)
		);

		$png = ( new \chillerlan\QRCode\QRCode( $qr_options ) )->render( $payload );

		if ( ! is_string( $png ) || '' === $png ) {
			throw new \RuntimeException( 'The QR code renderer returned no data.' );
		}

		return $png;
	}

	/**
	 * Build the 4.x module color map from a foreground and background RGB.
	 *
	 * @param int[] $foreground Dark module RGB triplet.
	 * @param int[] $background Light module RGB triplet.
	 * @return array<int, int[]>
	 */
	private static function module_values( array $foreground, array $background ): array {
		$values = array();

		foreach ( \chillerlan\QRCode\Output\QROutputInterface::DEFAULT_MODULE_VALUES as $type => $is_dark ) {
			$values[ $type ] = $is_dark ? $foreground : $background;
		}

		return $values;
	}

	/**
	 * Validated QR content mode.
	 *
	 * @return string "card" or "contact".
	 */
	private function mode(): string {
		return choice( $this->settings['qr_mode'], array( 'card', 'contact' ), 'card' );
	}

	/**
	 * Convert a hex color into an RGB triplet.
	 *
	 * @param string $hex      Hex color such as #0f172a or #fff.
	 * @param int[]  $fallback Fallback triplet.
	 * @return int[]
	 */
	private static function hex_to_rgb( string $hex, array $fallback ): array {
		$hex = ltrim( trim( $hex ), '#' );

		if ( 3 === strlen( $hex ) ) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}

		if ( ! is_string( $hex ) || ! preg_match( '/^[0-9a-fA-F]{6}$/', $hex ) ) {
			return $fallback;
		}

		return array(
			(int) hexdec( substr( $hex, 0, 2 ) ),
			(int) hexdec( substr( $hex, 2, 2 ) ),
			(int) hexdec( substr( $hex, 4, 2 ) ),
		);
	}
}
