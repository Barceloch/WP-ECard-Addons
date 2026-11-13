<?php
/**
 * QR payload, options and cache-key tests.
 *
 * @package MyECardExtensions
 */

namespace MyECardExtensions\Tests\Unit;

use MyECardExtensions\CardData;
use MyECardExtensions\QRCodeGenerator;
use MyECardExtensions\QRCodeUnavailableException;
use MyECardExtensions\Tests\TestCase;
use WP_Post;

/**
 * Covers the parts of QR generation that work without the optional library.
 */
final class QRCodeGeneratorTest extends TestCase {

	/**
	 * Public reference card.
	 */
	const CARD_ID = 90;

	/**
	 * Card that is not published.
	 */
	const DRAFT_ID = 91;

	/**
	 * Register a stubbed ECard card.
	 *
	 * @param int   $post_id Post ID.
	 * @param array $props   Extra post properties.
	 * @return WP_Post
	 */
	private function card( int $post_id = self::CARD_ID, array $props = array() ): WP_Post {
		$post = new WP_Post(
			array_merge(
				array(
					'ID'            => $post_id,
					'post_type'     => 'ecard_digital_card',
					'post_title'    => 'Lucía Méndez',
					'post_name'     => 'lucia-mendez',
					'post_status'   => 'publish',
					'post_password' => '',
					'post_content'  => '',
				),
				$props
			)
		);

		$GLOBALS['my_ecard_test_posts'][ $post_id ] = $post;

		return $post;
	}

	/**
	 * Assign the contact metadata used by the vCard payload.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	private function configure( int $post_id ): void {
		update_post_meta( $post_id, '_ecard_designation', 'Arquitecta' );
		update_post_meta( $post_id, '_ecard_company_name', 'Estudio Sur' );
		update_post_meta( $post_id, '_ecard_email', 'lucia@example.com' );
		update_post_meta( $post_id, '_ecard_phone', '+34 600 111 222' );
	}

	/**
	 * Build a generator for a stored card.
	 *
	 * @param int   $post_id  Post ID.
	 * @param array $settings Setting overrides.
	 * @return QRCodeGenerator
	 */
	private function generator( int $post_id, array $settings = array() ): QRCodeGenerator {
		$card = CardData::from_post( $post_id );

		$this->assert_not_null( $card );

		return new QRCodeGenerator( $card, array_merge( $card->settings, $settings ) );
	}

	/**
	 * Availability reflects the real runtime requirements.
	 *
	 * @return void
	 */
	public function test_availability_reflects_renderer_requirements(): void {
		$reason = QRCodeGenerator::unavailable_reason();

		$this->assert_same( QRCodeGenerator::renderer_available(), QRCodeGenerator::is_available() );
		$this->assert_same( QRCodeGenerator::renderer_available(), '' === $reason );

		if ( ! QRCodeGenerator::renderer_available() ) {
			$this->assert_true(
				in_array( $reason, array( 'library', 'gd' ), true ),
				'An unavailable renderer must report a machine-readable reason.'
			);
		}
	}

	/**
	 * Rendering fails closed when the renderer is missing.
	 *
	 * @return void
	 */
	public function test_generate_throws_when_the_renderer_is_missing(): void {
		if ( QRCodeGenerator::renderer_available() ) {
			$this->assert_true( true );

			return;
		}

		$this->card();
		$this->configure( self::CARD_ID );

		try {
			$this->generator( self::CARD_ID )->generate();
			$this->fail( 'generate() returned instead of throwing.' );
		} catch ( QRCodeUnavailableException $exception ) {
			$this->assert_true( strlen( $exception->getMessage() ) > 0 );
		} catch ( \Throwable $exception ) {
			$this->fail( 'Expected QRCodeUnavailableException, got ' . get_class( $exception ) );
		}
	}

	/**
	 * A real PNG is produced when the library and GD are available.
	 *
	 * @return void
	 */
	public function test_generate_produces_a_valid_png_when_available(): void {
		if ( ! QRCodeGenerator::renderer_available() ) {
			$this->assert_true( true );

			return;
		}

		$this->card();
		$this->configure( self::CARD_ID );

		$png = $this->generator( self::CARD_ID, array( 'qr_size' => 220 ) )->generate();

		$this->assert_same( "\x89PNG\r\n\x1a\n", substr( $png, 0, 8 ) );

		$info = getimagesize( 'data://image/png;base64,' . base64_encode( $png ) );

		$this->assert_true( is_array( $info ) );
		$this->assert_same( 'image/png', $info['mime'] );
		$this->assert_same( $info[0], $info[1] );
		$this->assert_true( $info[0] >= 96 && $info[0] <= 512, 'PNG size must follow the requested size.' );

		$image = imagecreatefromstring( $png );

		$this->assert_true( false !== $image );

		$corner = imagecolorat( $image, 2, 2 );
		$rgb    = array( ( $corner >> 16 ) & 0xff, ( $corner >> 8 ) & 0xff, $corner & 0xff );

		$this->assert_same( array( 255, 255, 255 ), $rgb );

		$found_dark = false;

		for ( $x = 0; $x < $info[0]; $x++ ) {
			$pixel  = imagecolorat( $image, $x, (int) ( $info[1] / 2 ) );
			$colors = array( ( $pixel >> 16 ) & 0xff, ( $pixel >> 8 ) & 0xff, $pixel & 0xff );

			if ( array( 15, 23, 42 ) === $colors ) {
				$found_dark = true;
				break;
			}
		}

		$this->assert_true( $found_dark, 'The dark modules must use the configured color.' );

		imagedestroy( $image );
	}

	/**
	 * The module scale is derived from the real matrix size.
	 *
	 * @return void
	 */
	public function test_scale_fits_the_requested_size(): void {
		$this->assert_same( 5, QRCodeGenerator::scale_for( 220, 41 ) );
		$this->assert_same( 1, QRCodeGenerator::scale_for( 10, 41 ) );
		$this->assert_same( 1, QRCodeGenerator::scale_for( 220, 0 ) );
	}

	/**
	 * The default payload is the public card permalink.
	 *
	 * @return void
	 */
	public function test_payload_is_the_card_permalink_in_card_mode(): void {
		$this->card();
		$this->configure( self::CARD_ID );

		$card = CardData::from_post( self::CARD_ID );

		$this->assert_not_null( $card );
		$this->assert_same( 'card', $card->settings['qr_mode'] );
		$this->assert_same( $card->url, $this->generator( self::CARD_ID )->payload() );
		$this->assert_same( 'https://example.test/ecard/lucia-mendez/', $this->generator( self::CARD_ID )->payload() );
	}

	/**
	 * Contact mode encodes the vCard document itself.
	 *
	 * @return void
	 */
	public function test_payload_is_a_vcard_in_contact_mode(): void {
		$this->card();
		$this->configure( self::CARD_ID );

		$payload = $this->generator( self::CARD_ID, array( 'qr_mode' => 'contact' ) )->payload();

		$this->assert_same( 0, strpos( $payload, 'BEGIN:VCARD' ) );
		$this->assert_contains( 'FN:', $payload );
		$this->assert_contains( 'END:VCARD', $payload );
	}

	/**
	 * Non-public cards cannot be encoded.
	 *
	 * @return void
	 */
	public function test_payload_rejects_non_public_cards(): void {
		$this->card( self::DRAFT_ID, array( 'post_status' => 'draft' ) );

		try {
			$this->generator( self::DRAFT_ID )->payload();
			$this->fail( 'payload() returned for a draft card.' );
		} catch ( \RuntimeException $exception ) {
			$this->assert_contains( 'not publicly available', $exception->getMessage() );
		}
	}

	/**
	 * Settings map to renderer options, with safe fallbacks.
	 *
	 * @return void
	 */
	public function test_options_map_settings_and_validate_colors(): void {
		$this->card();
		$this->configure( self::CARD_ID );

		$options = $this->generator(
			self::CARD_ID,
			array(
				'qr_size'  => 300,
				'qr_dark'  => '#ff0000',
				'qr_light' => '#000000',
			)
		)->options();

		$this->assert_same( 300, $options['size'] );
		$this->assert_same( array( 255, 0, 0 ), $options['fgValues'] );
		$this->assert_same( array( 0, 0, 0 ), $options['bgValues'] );

		$invalid = $this->generator(
			self::CARD_ID,
			array(
				'qr_dark'  => 'not-a-color',
				'qr_light' => '#zzzzzz',
			)
		)->options();

		$this->assert_same( array( 15, 23, 42 ), $invalid['fgValues'] );
		$this->assert_same( array( 255, 255, 255 ), $invalid['bgValues'] );
	}

	/**
	 * The cache key is stable per configuration and changes with it.
	 *
	 * @return void
	 */
	public function test_cache_key_is_deterministic_and_setting_sensitive(): void {
		$this->card();
		$this->configure( self::CARD_ID );

		$card = CardData::from_post( self::CARD_ID );

		$this->assert_not_null( $card );

		$key = ( new QRCodeGenerator( $card, $card->settings ) )->cache_key();

		$this->assert_same( $key, ( new QRCodeGenerator( $card, $card->settings ) )->cache_key() );
		$this->assert_same( $key, $this->generator( self::CARD_ID )->cache_key() );

		$larger = $this->generator( self::CARD_ID, array( 'qr_size' => 300 ) )->cache_key();
		$darker = $this->generator( self::CARD_ID, array( 'qr_dark' => '#ff0000' ) )->cache_key();
		$contact = $this->generator( self::CARD_ID, array( 'qr_mode' => 'contact' ) )->cache_key();

		$this->assert_true( $larger !== $key, 'The key must change with the size.' );
		$this->assert_true( $darker !== $key, 'The key must change with the dark color.' );
		$this->assert_true( $contact !== $key, 'The key must change with the mode.' );
		$this->assert_true( $larger !== $darker );
	}

	/**
	 * The cache key changes when the encoded payload changes.
	 *
	 * @return void
	 */
	public function test_cache_key_invalidates_on_payload_changes(): void {
		$this->card();
		$this->configure( self::CARD_ID );

		$card_key    = $this->generator( self::CARD_ID )->cache_key();
		$contact_key = $this->generator( self::CARD_ID, array( 'qr_mode' => 'contact' ) )->cache_key();

		update_post_meta( self::CARD_ID, '_ecard_phone', '+34 600 999 000' );

		$contact_after = $this->generator( self::CARD_ID, array( 'qr_mode' => 'contact' ) )->cache_key();

		$this->assert_true( $contact_key !== $contact_after, 'Contact changes must invalidate contact-mode QR codes.' );
		$this->assert_same( $card_key, $this->generator( self::CARD_ID )->cache_key() );

		$GLOBALS['my_ecard_test_posts'][ self::CARD_ID ]->post_name = 'lucia-mendez-renamed';

		$renamed = $this->generator( self::CARD_ID )->cache_key();
		$contact_renamed = $this->generator( self::CARD_ID, array( 'qr_mode' => 'contact' ) )->cache_key();

		$this->assert_true( $card_key !== $renamed, 'A new public URL must invalidate card-mode QR codes.' );
		$this->assert_true( $contact_after !== $contact_renamed, 'A new public URL must invalidate contact-mode QR codes.' );
	}
}
