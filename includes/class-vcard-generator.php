<?php
/**
 * vCard 3.0 generation for public cards.
 *
 * @package MyECardExtensions
 */

namespace MyECardExtensions;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders one card as a downloadable vCard 3.0 document.
 *
 * The generator never touches post meta: every value comes from CardData, and
 * cards that WordPress would not show publicly are rejected.
 */
final class VCardGenerator {

	/**
	 * Maximum octets of the first physical line.
	 */
	const FIRST_LINE_LIMIT = 75;

	/**
	 * Maximum octets carried by a continuation chunk, excluding the leading space.
	 */
	const CONTINUATION_LIMIT = 74;

	/**
	 * Card read model.
	 *
	 * @var CardData
	 */
	private $card;

	/**
	 * Rendered document cache, so headers() never renders twice.
	 *
	 * @var string|null
	 */
	private $rendered = null;

	/**
	 * Constructor.
	 *
	 * @param CardData $card Card read model.
	 */
	public function __construct( CardData $card ) {
		$this->card = $card;
	}

	/**
	 * Whether the card may be exposed through a download.
	 *
	 * Both flags are required: WordPress treats a published, password-protected
	 * post as publicly viewable, so the password gate must be checked separately.
	 *
	 * @param CardData $card Card read model.
	 * @return bool
	 */
	public static function is_public( CardData $card ): bool {
		return (bool) $card->is_publicly_viewable && ! $card->requires_password;
	}

	/**
	 * Render the vCard document.
	 *
	 * @return string CRLF-separated vCard 3.0 content.
	 * @throws \RuntimeException When the card is not publicly viewable.
	 */
	public function render(): string {
		if ( null !== $this->rendered ) {
			return $this->rendered;
		}

		if ( ! self::is_public( $this->card ) ) {
			throw new \RuntimeException( 'The card is not publicly available for download.' );
		}

		$name  = $this->name_parts();
		$lines = array();

		$lines[] = 'BEGIN:VCARD';
		$lines[] = 'VERSION:3.0';
		$lines[] = 'PRODID:-//WP ECard Addons//EN';
		$lines[] = 'N:' . self::escape( $name['family'] ) . ';' . self::escape( $name['given'] ) . ';' . self::escape( $name['additional'] ) . ';;';
		$lines[] = 'FN:' . self::escape( (string) $this->card->title );

		$optional = array(
			array( 'ORG:', (string) $this->card->company ),
			array( 'TITLE:', (string) $this->card->designation ),
			array( 'TEL;TYPE=VOICE:', (string) $this->card->phone ),
			array( 'TEL;TYPE=CELL:', (string) $this->card->whatsapp ),
			array( 'EMAIL;TYPE=INTERNET:', (string) $this->card->email ),
			array( 'URL:', (string) $this->card->website ),
			array( 'URL:', (string) $this->card->url ),
			array( 'ADR;TYPE=WORK:;;', (string) $this->card->address, ';;;;' ),
		);

		foreach ( $optional as $entry ) {
			if ( '' === $entry[1] ) {
				continue;
			}

			$lines[] = $entry[0] . self::escape( $entry[1] ) . ( isset( $entry[2] ) ? $entry[2] : '' );
		}

		$lines[] = 'END:VCARD';

		$output = '';

		foreach ( $lines as $line ) {
			$output .= $this->fold( $line ) . "\r\n";
		}

		$this->rendered = $output;

		return $output;
	}

	/**
	 * Suggested download filename.
	 *
	 * @return string Always ends with ".vcf".
	 */
	public function filename(): string {
		$title = trim( (string) $this->card->title );

		if ( function_exists( 'sanitize_file_name' ) ) {
			$name = (string) sanitize_file_name( $title );

			return '' === $name ? 'card.vcf' : $name . '.vcf';
		}

		$name = $this->transliterate( $title );
		$name = strtolower( $name );
		$name = (string) preg_replace( '/[^a-z0-9]+/', '-', $name );
		$name = trim( $name, '-' );

		return ( '' === $name ? 'card' : $name ) . '.vcf';
	}

	/**
	 * HTTP headers for the download response.
	 *
	 * @return string[]
	 */
	public function headers(): array {
		$body     = $this->render();
		$filename = str_replace( array( '\\', '"' ), array( '\\\\', '\\"' ), $this->filename() );

		return array(
			'Content-Type: text/vcard; charset=utf-8',
			'Content-Disposition: attachment; filename="' . $filename . '"',
			'Content-Length: ' . strlen( $body ),
			'Cache-Control: no-store',
			'X-Content-Type-Options: nosniff',
		);
	}

	/**
	 * Split the display name into vCard name components.
	 *
	 * The first token is the given name, the last token is the family name and
	 * every token in between becomes the additional name. A single token is a
	 * given name.
	 *
	 * @return array{family: string, given: string, additional: string}
	 */
	private function name_parts(): array {
		$title = trim( (string) $this->card->title );
		$parts = array();

		if ( '' !== $title ) {
			$split = preg_split( '/\s+/', $title );
			$parts = is_array( $split ) ? array_values( array_filter( $split, 'strlen' ) ) : array();
		}

		$count = count( $parts );

		if ( 0 === $count ) {
			return array(
				'family'     => '',
				'given'      => '',
				'additional' => '',
			);
		}

		if ( 1 === $count ) {
			return array(
				'family'     => '',
				'given'      => $parts[0],
				'additional' => '',
			);
		}

		return array(
			'family'     => $parts[ $count - 1 ],
			'given'      => $parts[0],
			'additional' => implode( ' ', array_slice( $parts, 1, $count - 2 ) ),
		);
	}

	/**
	 * Escape a vCard text value.
	 *
	 * @param string $value Raw value.
	 * @return string
	 */
	private static function escape( string $value ): string {
		$value = str_replace( array( "\r\n", "\r" ), "\n", $value );
		$value = str_replace( '\\', '\\\\', $value );
		$value = str_replace( array( ',', ';' ), array( '\,', '\;' ), $value );

		return str_replace( "\n", '\n', $value );
	}

	/**
	 * Fold one property line at the vCard octet limits.
	 *
	 * Multibyte characters are never split: when a chunk would end in the
	 * middle of a character, the whole character moves to the next chunk.
	 *
	 * @param string $line Composed property line.
	 * @return string Line with CRLF folding inserted.
	 */
	private function fold( string $line ): string {
		$length = strlen( $line );

		if ( $length <= self::FIRST_LINE_LIMIT ) {
			return $line;
		}

		$folded = '';
		$offset = 0;
		$first  = true;

		while ( $offset < $length ) {
			$limit = $first ? self::FIRST_LINE_LIMIT : self::CONTINUATION_LIMIT;
			$chunk = substr( $line, $offset, $limit );

			if ( $offset + $limit < $length ) {
				$chunk = self::complete_prefix( $chunk );
			}

			if ( '' === $chunk ) {
				break;
			}

			$folded .= ( $first ? '' : ' ' ) . $chunk;
			$offset += strlen( $chunk );

			if ( $offset < $length ) {
				$folded .= "\r\n";
			}

			$first = false;
		}

		return $folded;
	}

	/**
	 * Remove an incomplete trailing UTF-8 character from a byte chunk.
	 *
	 * @param string $chunk Candidate chunk.
	 * @return string Chunk ending on a character boundary.
	 */
	private static function complete_prefix( string $chunk ): string {
		$length = strlen( $chunk );

		for ( $i = 1; $i <= 4 && $i <= $length; $i++ ) {
			$byte = ord( $chunk[ $length - $i ] );

			if ( $byte < 0x80 ) {
				return $chunk;
			}

			if ( 0xC0 !== ( $byte & 0xC0 ) ) {
				continue;
			}

			if ( $byte >= 0xF0 ) {
				$expected = 4;
			} elseif ( $byte >= 0xE0 ) {
				$expected = 3;
			} else {
				$expected = 2;
			}

			return $i === $expected ? $chunk : substr( $chunk, 0, $length - $i );
		}

		return $chunk;
	}

	/**
	 * Transliterate Latin accents for the filename fallback.
	 *
	 * @param string $value Raw value.
	 * @return string
	 */
	private function transliterate( string $value ): string {
		$map = array(
			'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n', 'ç' => 'c',
			'à' => 'a', 'è' => 'e', 'ì' => 'i', 'ò' => 'o', 'ù' => 'u', 'ä' => 'a', 'ë' => 'e', 'ï' => 'i',
			'ö' => 'o', 'ã' => 'a', 'õ' => 'o', 'å' => 'a', 'æ' => 'ae', 'œ' => 'oe', 'ß' => 'ss', 'ø' => 'o',
			'đ' => 'd', 'ł' => 'l', 'š' => 's', 'ž' => 'z', 'ý' => 'y', 'č' => 'c', 'ř' => 'r', 'ť' => 't',
			'ň' => 'n', 'ď' => 'd', 'ě' => 'e', 'ů' => 'u', 'ś' => 's', 'ź' => 'z', 'ż' => 'z', 'ć' => 'c',
			'ń' => 'n',
			'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ü' => 'U', 'Ñ' => 'N', 'Ç' => 'C',
			'À' => 'A', 'È' => 'E', 'Ì' => 'I', 'Ò' => 'O', 'Ù' => 'U', 'Ä' => 'A', 'Ë' => 'E', 'Ï' => 'I',
			'Ö' => 'O', 'Ã' => 'A', 'Õ' => 'O', 'Å' => 'A', 'Æ' => 'AE', 'Œ' => 'OE', 'Ø' => 'O', 'Đ' => 'D',
			'Ł' => 'L', 'Š' => 'S', 'Ž' => 'Z', 'Ý' => 'Y', 'Č' => 'C', 'Ř' => 'R', 'Ť' => 'T', 'Ň' => 'N',
			'Ď' => 'D', 'Ě' => 'E', 'Ů' => 'U', 'Ś' => 'S', 'Ź' => 'Z', 'Ż' => 'Z', 'Ć' => 'C', 'Ń' => 'N',
		);

		return strtr( $value, $map );
	}
}
