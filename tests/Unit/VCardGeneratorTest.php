<?php
/**
 * vCard 3.0 generation tests.
 *
 * @package MyECardExtensions
 */

namespace MyECardExtensions\Tests\Unit;

use MyECardExtensions\CardData;
use MyECardExtensions\Tests\TestCase;
use MyECardExtensions\VCardGenerator;
use WP_Post;

require_once MY_ECARD_PATH . 'includes/class-vcard-generator.php';

/**
 * Covers the vCard body, folding, escaping, filename and headers.
 */
final class VCardGeneratorTest extends TestCase {

	/**
	 * Public reference card.
	 */
	const CARD_ID = 80;

	/**
	 * Card without a title.
	 */
	const BLANK_ID = 81;

	/**
	 * Card that is not published.
	 */
	const DRAFT_ID = 82;

	/**
	 * Password-protected card.
	 */
	const PASSWORD_ID = 83;

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
					'post_title'    => 'Emilio Frías',
					'post_name'     => 'emilio-frias',
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
	 * Assign the full reference metadata.
	 *
	 * @param int   $post_id Post ID.
	 * @param array $meta    Meta overrides, applied after the defaults.
	 * @return void
	 */
	private function configure( int $post_id, array $meta = array() ): void {
		$defaults = array(
			'_ecard_designation'  => 'Cantante, Compositor',
			'_ecard_company_name' => 'ACME; Records',
			'_ecard_phone'        => '+34 600 111 222',
			'_ecard_whatsapp'     => '+34 600 333 444',
			'_ecard_email'        => 'emilio@example.com',
			'_ecard_website'      => 'https://example.com',
			'_ecard_address'      => "Calle Mayor 1\nMadrid",
		);

		foreach ( array_merge( $defaults, $meta ) as $key => $value ) {
			update_post_meta( $post_id, $key, $value );
		}
	}

	/**
	 * Build a generator for a stored card.
	 *
	 * @param int $post_id Post ID.
	 * @return VCardGenerator
	 */
	private function generator( int $post_id ): VCardGenerator {
		$card = CardData::from_post( $post_id );

		$this->assert_not_null( $card );

		return new VCardGenerator( $card );
	}

	/**
	 * Collect the physical lines of one property.
	 *
	 * @param string $body   Rendered document.
	 * @param string $prefix Property prefix.
	 * @return string[]
	 */
	private function property_lines( string $body, string $prefix ): array {
		$lines  = explode( "\r\n", $body );
		$found  = array();
		$active = false;

		foreach ( $lines as $line ) {
			if ( 0 === strpos( $line, $prefix ) ) {
				$active  = true;
				$found[] = $line;
				continue;
			}

			if ( $active && '' !== $line && ' ' === $line[0] ) {
				$found[] = $line;
				continue;
			}

			if ( $active ) {
				break;
			}
		}

		return $found;
	}

	/**
	 * A complete card renders every property in the documented order.
	 *
	 * @return void
	 */
	public function test_full_card_renders_exact_property_lines(): void {
		$this->card();
		$this->configure( self::CARD_ID );

		$body = $this->generator( self::CARD_ID )->render();

		$expected = array(
			'BEGIN:VCARD',
			'VERSION:3.0',
			'PRODID:-//WP ECard Addons//EN',
			'N:Frías;Emilio;;;',
			'FN:Emilio Frías',
			'ORG:ACME\; Records',
			'TITLE:Cantante\, Compositor',
			'TEL;TYPE=VOICE:+34600111222',
			'TEL;TYPE=CELL:+34600333444',
			'EMAIL;TYPE=INTERNET:emilio@example.com',
			'URL:https://example.com',
			'URL:https://example.test/ecard/emilio-frias/',
			'ADR;TYPE=WORK:;;Calle Mayor 1\nMadrid;;;;',
			'END:VCARD',
		);

		$this->assert_same( implode( "\r\n", $expected ) . "\r\n", $body );

		$this->assert_contains( 'BEGIN:VCARD', $body );
		$this->assert_contains( 'END:VCARD', $body );
		$this->assert_contains( 'VERSION:3.0', $body );
		$this->assert_contains( 'N:Frías;Emilio;;;', $body );
		$this->assert_contains( 'FN:Emilio Frías', $body );
		$this->assert_contains( 'ORG:ACME\; Records', $body );
		$this->assert_contains( 'TITLE:Cantante\, Compositor', $body );
		$this->assert_contains( 'TEL;TYPE=VOICE:+34600111222', $body );
		$this->assert_contains( 'TEL;TYPE=CELL:+34600333444', $body );
		$this->assert_contains( 'EMAIL;TYPE=INTERNET:emilio@example.com', $body );
		$this->assert_contains( 'URL:https://example.com', $body );
		$this->assert_contains( 'URL:https://example.test/ecard/emilio-frias/', $body );
		$this->assert_contains( 'ADR;TYPE=WORK:;;Calle Mayor 1\nMadrid;;;;', $body );
	}

	/**
	 * Empty optional fields are omitted but the card URL always stays.
	 *
	 * @return void
	 */
	public function test_optional_fields_are_omitted(): void {
		$this->card();
		$this->configure(
			self::CARD_ID,
			array(
				'_ecard_designation'  => '',
				'_ecard_company_name' => '',
				'_ecard_phone'        => '',
				'_ecard_whatsapp'     => '',
				'_ecard_website'      => '',
				'_ecard_address'      => '',
			)
		);

		$body = $this->generator( self::CARD_ID )->render();

		$this->assert_false( strpos( $body, 'ORG:' ) );
		$this->assert_false( strpos( $body, 'TITLE:' ) );
		$this->assert_false( strpos( $body, 'TEL' ) );
		$this->assert_false( strpos( $body, 'ADR' ) );
		$this->assert_false( strpos( $body, 'URL:https://example.com' ) );

		$this->assert_contains( 'URL:https://example.test/ecard/emilio-frias/', $body );
		$this->assert_same( 1, substr_count( $body, 'URL:' ) );
		$this->assert_contains( 'EMAIL;TYPE=INTERNET:emilio@example.com', $body );
	}

	/**
	 * Reserved characters and newlines are escaped.
	 *
	 * @return void
	 */
	public function test_reserved_characters_are_escaped(): void {
		$this->card();
		$this->configure(
			self::CARD_ID,
			array(
				'_ecard_company_name' => 'ACME\Holdings; Intl, S.L.',
				'_ecard_designation'  => 'Cantante\Compositor, S.L.; Duo',
				'_ecard_address'      => "Line 1\nLine 2",
			)
		);

		$body = $this->generator( self::CARD_ID )->render();

		$this->assert_contains( 'ORG:ACME\\\\Holdings\; Intl\, S.L.', $body );
		$this->assert_contains( 'TITLE:Cantante\\\\Compositor\, S.L.\; Duo', $body );
		$this->assert_contains( 'ADR;TYPE=WORK:;;Line 1\nLine 2;;;;', $body );

		$this->assert_false( strpos( $body, 'ORG:ACME\Holdings' ) );
		$this->assert_false( strpos( $body, 'TITLE:Cantante\Compositor' ) );
		$this->assert_false( strpos( $body, "ADR;TYPE=WORK:;;Line 1\nLine 2" ) );
	}

	/**
	 * UTF-8 accents are preserved byte for byte.
	 *
	 * @return void
	 */
	public function test_utf8_values_survive_byte_for_byte(): void {
		$this->card();
		$this->configure(
			self::CARD_ID,
			array( '_ecard_designation' => 'Cantante Lírico, Compositor' )
		);

		$body = $this->generator( self::CARD_ID )->render();

		$this->assert_same( 1, preg_match( '//u', $body ) );
		$this->assert_contains( "N:Fr\xC3\xADas;Emilio;;;", $body );
		$this->assert_contains( "FN:Emilio Fr\xC3\xADas", $body );
		$this->assert_contains( "TITLE:Cantante L\xC3\xADrico\, Compositor", $body );
	}

	/**
	 * Long UTF-8 values fold at 75 octets without splitting characters.
	 *
	 * @return void
	 */
	public function test_long_value_folds_on_character_boundaries(): void {
		$this->card();
		$this->configure(
			self::CARD_ID,
			array( '_ecard_address' => str_repeat( 'á', 60 ) )
		);

		$body = $this->generator( self::CARD_ID )->render();

		$this->assert_same( 1, preg_match( '//u', $body ) );

		foreach ( explode( "\r\n", rtrim( $body, "\r\n" ) ) as $physical ) {
			$this->assert_true(
				strlen( $physical ) <= 75,
				'Physical line exceeds 75 octets: ' . strlen( $physical )
			);
		}

		$lines = $this->property_lines( $body, 'ADR;' );

		$this->assert_true( count( $lines ) > 1, 'Expected the ADR property to fold.' );

		$unfolded = '';

		foreach ( $lines as $index => $line ) {
			if ( $index > 0 ) {
				$this->assert_same( ' ', $line[0], 'Continuation lines must start with a space.' );
				$unfolded .= substr( $line, 1 );
				continue;
			}

			$unfolded .= $line;
		}

		$this->assert_same(
			'ADR;TYPE=WORK:;;' . str_repeat( 'á', 60 ) . ';;;;',
			$unfolded
		);
	}

	/**
	 * The document uses CRLF only.
	 *
	 * @return void
	 */
	public function test_document_uses_crlf_line_endings(): void {
		$this->card();
		$this->configure( self::CARD_ID );

		$body = $this->generator( self::CARD_ID )->render();

		$this->assert_contains( "\r\n", $body );
		$this->assert_same( substr_count( $body, "\n" ), preg_match_all( '/\r\n/', $body ) );
		$this->assert_same( 0, preg_match( '/(?<!\r)\n/', $body ) );
	}

	/**
	 * The filename transliterates accents and falls back to "card".
	 *
	 * @return void
	 */
	public function test_filename_is_slugified(): void {
		$this->assert_false(
			function_exists( 'sanitize_file_name' ),
			'The test relies on the fallback filename path.'
		);

		$this->card();
		$this->configure( self::CARD_ID );

		$filename = $this->generator( self::CARD_ID )->filename();

		$this->assert_same( 'emilio-frias.vcf', $filename );
		$this->assert_false( strpos( $filename, '/' ) );
		$this->assert_false( strpos( $filename, '\\' ) );
		$this->assert_false( strpos( $filename, '"' ) );
	}

	/**
	 * A blank title falls back to "card.vcf".
	 *
	 * @return void
	 */
	public function test_blank_title_uses_card_filename(): void {
		$this->card( self::BLANK_ID, array( 'post_title' => '' ) );

		$filename = $this->generator( self::BLANK_ID )->filename();

		$this->assert_same( 'card.vcf', $filename );
		$this->assert_false( strpos( $filename, '/' ) );
		$this->assert_false( strpos( $filename, '\\' ) );
		$this->assert_false( strpos( $filename, '"' ) );
	}

	/**
	 * Headers describe the rendered body exactly.
	 *
	 * @return void
	 */
	public function test_headers_describe_the_download(): void {
		$this->card();
		$this->configure( self::CARD_ID );

		$generator = $this->generator( self::CARD_ID );
		$body      = $generator->render();
		$headers   = $generator->headers();

		$expected = array(
			'Content-Type: text/vcard; charset=utf-8',
			'Content-Disposition: attachment; filename="emilio-frias.vcf"',
			'Content-Length: ' . strlen( $body ),
			'Cache-Control: no-store',
			'X-Content-Type-Options: nosniff',
		);

		$this->assert_count( 5, $headers );
		$this->assert_same( $expected, $headers );
		$this->assert_same( 'Content-Length: ' . strlen( $body ), $headers[2] );
	}

	/**
	 * Non-public and password-protected cards cannot be downloaded.
	 *
	 * @return void
	 */
	public function test_non_public_cards_are_rejected(): void {
		$this->card( self::DRAFT_ID, array( 'post_status' => 'draft' ) );
		$this->card( self::PASSWORD_ID, array( 'post_password' => 'secret' ) );

		$cards = array(
			self::DRAFT_ID    => 'draft card',
			self::PASSWORD_ID => 'password-protected card',
		);

		foreach ( $cards as $post_id => $label ) {
			$card = CardData::from_post( $post_id );

			$this->assert_not_null( $card );
			$this->assert_false( VCardGenerator::is_public( $card ), $label . ' must not be public.' );

			try {
				( new VCardGenerator( $card ) )->render();
				$this->fail( $label . ' rendered instead of throwing.' );
			} catch ( \RuntimeException $exception ) {
				$this->assert_contains( 'not publicly available', $exception->getMessage() );
			}
		}
	}
}
