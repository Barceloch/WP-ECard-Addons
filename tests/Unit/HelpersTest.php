<?php
/**
 * Helper function tests.
 *
 * @package MyECardExtensions
 */

namespace MyECardExtensions\Tests\Unit;

use MyECardExtensions\Tests\TestCase;

use function MyECardExtensions\choice;
use function MyECardExtensions\clamp_int;
use function MyECardExtensions\extract_iframe_src;
use function MyECardExtensions\hex_color;
use function MyECardExtensions\is_direct_video_url;
use function MyECardExtensions\map_embed_url;
use function MyECardExtensions\normalize_phone;
use function MyECardExtensions\requirement_satisfied;
use function MyECardExtensions\safe_url;
use function MyECardExtensions\tel_url;
use function MyECardExtensions\video_embed_url;
use function MyECardExtensions\video_provider;
use function MyECardExtensions\vimeo_video_id;
use function MyECardExtensions\whatsapp_url;
use function MyECardExtensions\youtube_video_id;

/**
 * Covers the pure normalization helpers.
 */
final class HelpersTest extends TestCase {

	/**
	 * safe_url keeps http and https URLs.
	 *
	 * @return void
	 */
	public function test_safe_url_keeps_http_and_https(): void {
		$this->assert_same( 'https://example.com/path?x=1', safe_url( 'https://example.com/path?x=1' ) );
		$this->assert_same( 'http://example.com', safe_url( 'http://example.com' ) );
	}

	/**
	 * safe_url rejects dangerous, unsupported and relative values.
	 *
	 * @return void
	 */
	public function test_safe_url_rejects_unsafe_values(): void {
		$this->assert_same( '', safe_url( '' ) );
		$this->assert_same( '', safe_url( '   ' ) );
		$this->assert_same( '', safe_url( 'javascript:alert(1)' ) );
		$this->assert_same( '', safe_url( 'data:text/html;base64,AAAA' ) );
		$this->assert_same( '', safe_url( 'ftp://example.com/file' ) );
		$this->assert_same( '', safe_url( '/relative/path' ) );
	}

	/**
	 * safe_url only allows explicitly requested schemes.
	 *
	 * @return void
	 */
	public function test_safe_url_respects_scheme_allowlist(): void {
		$this->assert_same( 'mailto:hi@example.com', safe_url( 'mailto:hi@example.com', array( 'mailto' ) ) );
		$this->assert_same( '', safe_url( 'https://example.com', array( 'mailto' ) ) );
	}

	/**
	 * normalize_phone strips separators and keeps a leading plus.
	 *
	 * @return void
	 */
	public function test_normalize_phone(): void {
		$this->assert_same( '+34600123456', normalize_phone( '+34 600 123 456' ) );
		$this->assert_same( '600123456', normalize_phone( '600.123.456' ) );
		$this->assert_same( '600123456', normalize_phone( '(600) 123-456' ) );
		$this->assert_same( '', normalize_phone( '' ) );
		$this->assert_same( '', normalize_phone( 'not a phone' ) );
	}

	/**
	 * tel_url only builds a link for a usable number.
	 *
	 * @return void
	 */
	public function test_tel_url(): void {
		$this->assert_same( 'tel:+34600123456', tel_url( '+34 600 123 456' ) );
		$this->assert_same( '', tel_url( '' ) );
	}

	/**
	 * whatsapp_url enforces the international digit range.
	 *
	 * @return void
	 */
	public function test_whatsapp_url(): void {
		$this->assert_same( 'https://wa.me/34600123456', whatsapp_url( '+34 600 123 456' ) );
		$this->assert_same( '', whatsapp_url( '1234567' ) );
		$this->assert_same( '', whatsapp_url( '1234567890123456' ) );
		$this->assert_same( '', whatsapp_url( 'no digits here' ) );
	}

	/**
	 * extract_iframe_src supports single and double quoted attributes.
	 *
	 * @return void
	 */
	public function test_extract_iframe_src(): void {
		$this->assert_same(
			'https://www.google.com/maps/embed?pb=abc',
			extract_iframe_src( '<iframe src="https://www.google.com/maps/embed?pb=abc" width="600"></iframe>' )
		);
		$this->assert_same(
			'https://www.google.com/maps/embed?pb=abc',
			extract_iframe_src( "<iframe src='https://www.google.com/maps/embed?pb=abc'></iframe>" )
		);
		$this->assert_same( '', extract_iframe_src( '<div>no iframe</div>' ) );
		$this->assert_same( '', extract_iframe_src( '' ) );
	}

	/**
	 * map_embed_url only accepts Google Maps embeds.
	 *
	 * @return void
	 */
	public function test_map_embed_url(): void {
		$this->assert_same(
			'https://www.google.com/maps/embed?pb=abc',
			map_embed_url( '<iframe src="https://www.google.com/maps/embed?pb=abc"></iframe>' )
		);

		$this->assert_same(
			'',
			map_embed_url( '<iframe src="https://evil.example.com/maps/embed"></iframe>' )
		);

		$this->assert_same(
			'',
			map_embed_url( '<iframe src="https://www.google.com/search?q=test"></iframe>' )
		);

		$this->assert_same( '', map_embed_url( '' ) );
	}

	/**
	 * video_provider detects known hosts.
	 *
	 * @return void
	 */
	public function test_video_provider(): void {
		$this->assert_same( 'youtube', video_provider( 'https://www.youtube.com/watch?v=abc' ) );
		$this->assert_same( 'youtube', video_provider( 'https://youtu.be/abc' ) );
		$this->assert_same( 'vimeo', video_provider( 'https://vimeo.com/12345' ) );
		$this->assert_same( 'other', video_provider( 'https://example.com/video.mp4' ) );
		$this->assert_same( '', video_provider( 'not a url' ) );
	}

	/**
	 * youtube_video_id accepts every supported URL shape.
	 *
	 * @return void
	 */
	public function test_youtube_video_id(): void {
		$id = 'abcdefghijk';

		$this->assert_same( $id, youtube_video_id( 'https://youtube.com/watch?v=abcdefghijk' ) );
		$this->assert_same( $id, youtube_video_id( 'https://www.youtube.com/watch?v=abcdefghijk' ) );
		$this->assert_same( $id, youtube_video_id( 'https://m.youtube.com/watch?v=abcdefghijk' ) );
		$this->assert_same( $id, youtube_video_id( 'https://youtu.be/abcdefghijk' ) );
		$this->assert_same( $id, youtube_video_id( 'https://youtube.com/shorts/abcdefghijk' ) );
		$this->assert_same( $id, youtube_video_id( 'https://youtube.com/embed/abcdefghijk' ) );
		$this->assert_same( $id, youtube_video_id( 'https://www.youtube-nocookie.com/embed/abcdefghijk' ) );
		$this->assert_same( $id, youtube_video_id( 'youtube.com/watch?v=abcdefghijk' ) );
		$this->assert_same( $id, youtube_video_id( 'https://youtu.be/abcdefghijk?t=30' ) );
		$this->assert_same( $id, youtube_video_id( 'https://www.youtube.com/watch?v=abcdefghijk&t=30' ) );
	}

	/**
	 * youtube_video_id rejects invalid IDs and non-YouTube hosts.
	 *
	 * @return void
	 */
	public function test_youtube_video_id_rejects_invalid_values(): void {
		$this->assert_same( '', youtube_video_id( '' ) );
		$this->assert_same( '', youtube_video_id( 'https://youtube.com/watch' ) );
		$this->assert_same( '', youtube_video_id( 'https://youtube.com/watch?v=abc' ) );
		$this->assert_same( '', youtube_video_id( 'https://youtube.com/watch?v=abcdefghijkl' ) );
		$this->assert_same( '', youtube_video_id( 'https://youtube.com/watch?v=abc!efghijk' ) );
		$this->assert_same( '', youtube_video_id( 'https://youtube.com/shorts/' ) );
		$this->assert_same( '', youtube_video_id( 'https://youtu.be/abc' ) );
		$this->assert_same( '', youtube_video_id( 'https://vimeo.com/76979871' ) );
		$this->assert_same( '', youtube_video_id( 'https://notyoutube.com/watch?v=abcdefghijk' ) );
		$this->assert_same( '', youtube_video_id( 'https://example.com/watch?v=abcdefghijk' ) );
	}

	/**
	 * vimeo_video_id accepts standard and player URLs.
	 *
	 * @return void
	 */
	public function test_vimeo_video_id(): void {
		$this->assert_same( '76979871', vimeo_video_id( 'https://vimeo.com/76979871' ) );
		$this->assert_same( '76979871', vimeo_video_id( 'https://www.vimeo.com/76979871' ) );
		$this->assert_same( '76979871', vimeo_video_id( 'https://player.vimeo.com/video/76979871' ) );
		$this->assert_same( '76979871', vimeo_video_id( 'https://vimeo.com/76979871?share=1' ) );
		$this->assert_same( '76979871', vimeo_video_id( 'https://player.vimeo.com/video/76979871/' ) );
	}

	/**
	 * vimeo_video_id rejects non-numeric and out-of-range values.
	 *
	 * @return void
	 */
	public function test_vimeo_video_id_rejects_invalid_values(): void {
		$this->assert_same( '', vimeo_video_id( '' ) );
		$this->assert_same( '', vimeo_video_id( 'https://vimeo.com/abc' ) );
		$this->assert_same( '', vimeo_video_id( 'https://vimeo.com/12345' ) );
		$this->assert_same( '', vimeo_video_id( 'https://vimeo.com/1234567890123' ) );
		$this->assert_same( '', vimeo_video_id( 'https://player.vimeo.com/video/' ) );
		$this->assert_same( '', vimeo_video_id( 'https://example.com/76979871' ) );
		$this->assert_same( '', vimeo_video_id( 'https://youtube.com/watch?v=abcdefghijk' ) );
	}

	/**
	 * video_embed_url builds provider-specific embed URLs.
	 *
	 * @return void
	 */
	public function test_video_embed_url(): void {
		$this->assert_same(
			'https://www.youtube-nocookie.com/embed/abcdefghijk?autoplay=1&rel=0&modestbranding=1',
			video_embed_url(
				array(
					'url'      => 'https://www.youtube.com/watch?v=abcdefghijk',
					'provider' => 'youtube',
				)
			)
		);

		$this->assert_same(
			'https://www.youtube-nocookie.com/embed/abcdefghijk?autoplay=1&rel=0&modestbranding=1',
			video_embed_url(
				array(
					'url'      => 'https://youtu.be/abcdefghijk',
					'provider' => 'YouTube',
				)
			)
		);

		$this->assert_same(
			'https://player.vimeo.com/video/76979871?autoplay=1',
			video_embed_url(
				array(
					'url'      => 'https://vimeo.com/76979871',
					'provider' => 'vimeo',
				)
			)
		);
	}

	/**
	 * video_embed_url refuses missing or invalid data.
	 *
	 * @return void
	 */
	public function test_video_embed_url_rejects_invalid_data(): void {
		$this->assert_same( '', video_embed_url( array() ) );
		$this->assert_same( '', video_embed_url( array( 'url' => 'https://www.youtube.com/watch?v=abcdefghijk' ) ) );
		$this->assert_same( '', video_embed_url( array( 'provider' => 'youtube' ) ) );
		$this->assert_same(
			'',
			video_embed_url(
				array(
					'url'      => 'https://www.youtube.com/watch?v=abc',
					'provider' => 'youtube',
				)
			)
		);
		$this->assert_same(
			'',
			video_embed_url(
				array(
					'url'      => 'https://vimeo.com/76979871',
					'provider' => 'youtube',
				)
			)
		);
		$this->assert_same(
			'',
			video_embed_url(
				array(
					'url'      => 'https://www.youtube.com/watch?v=abcdefghijk',
					'provider' => 'vimeo',
				)
			)
		);
		$this->assert_same(
			'',
			video_embed_url(
				array(
					'url'      => 'javascript:alert(1)',
					'provider' => 'youtube',
				)
			)
		);
		$this->assert_same(
			'',
			video_embed_url(
				array(
					'url'      => 'https://example.com/watch?v=abcdefghijk',
					'provider' => 'other',
				)
			)
		);
	}

	/**
	 * is_direct_video_url accepts supported file extensions only.
	 *
	 * @return void
	 */
	public function test_is_direct_video_url(): void {
		$this->assert_true( is_direct_video_url( 'https://example.com/clip.mp4' ) );
		$this->assert_true( is_direct_video_url( 'https://example.com/clip.webm' ) );
		$this->assert_true( is_direct_video_url( 'https://example.com/clip.ogg' ) );
		$this->assert_true( is_direct_video_url( 'https://example.com/clip.ogv' ) );
		$this->assert_true( is_direct_video_url( 'https://example.com/clip.m4v' ) );
		$this->assert_true( is_direct_video_url( 'https://example.com/clip.MP4' ) );
		$this->assert_true( is_direct_video_url( 'https://example.com/clip.mp4?token=abc' ) );
		$this->assert_true( is_direct_video_url( 'https://example.com/clip.webm#t=10' ) );

		$this->assert_false( is_direct_video_url( 'https://example.com/page.html' ) );
		$this->assert_false( is_direct_video_url( 'https://example.com/page.php' ) );
		$this->assert_false( is_direct_video_url( 'https://example.com/clip.avi' ) );
		$this->assert_false( is_direct_video_url( 'https://example.com/video' ) );
		$this->assert_false( is_direct_video_url( 'clip.mp4' ) );
		$this->assert_false( is_direct_video_url( '' ) );
		$this->assert_false( is_direct_video_url( 'not a url' ) );
		$this->assert_false( is_direct_video_url( 'javascript:alert(1)' ) );
	}

	/**
	 * Malformed URLs never throw and always fall back to empty values.
	 *
	 * @return void
	 */
	public function test_video_helpers_survive_malformed_urls(): void {
		$malformed = array( '', '   ', 'not a url', 'https://', '://', 'javascript:alert(1)', '%' );

		foreach ( $malformed as $url ) {
			$this->assert_same( '', youtube_video_id( $url ) );
			$this->assert_same( '', vimeo_video_id( $url ) );
			$this->assert_false( is_direct_video_url( $url ) );
		}

		$this->assert_same(
			'',
			video_embed_url(
				array(
					'url'      => 'not a url',
					'provider' => 'youtube',
				)
			)
		);
	}

	/**
	 * requirement_satisfied supports the documented operators.
	 *
	 * @return void
	 */
	public function test_requirement_satisfied(): void {
		$this->assert_true( requirement_satisfied( '1.6.0', '*' ) );
		$this->assert_true( requirement_satisfied( '1.6.0', '' ) );
		$this->assert_true( requirement_satisfied( '1.6.0', '>=1.6.0' ) );
		$this->assert_true( requirement_satisfied( '1.7.2', '>1.6.0' ) );
		$this->assert_false( requirement_satisfied( '1.5.9', '>=1.6.0' ) );
		$this->assert_true( requirement_satisfied( '1.6.0', '<=1.6.0' ) );
		$this->assert_true( requirement_satisfied( '1.6.0', '=1.6.0' ) );
		$this->assert_true( requirement_satisfied( '1.6.0', '1.6.0' ) );
		$this->assert_false( requirement_satisfied( '1.6.0', 'not-a-version' ) );
	}

	/**
	 * clamp_int enforces bounds and falls back on non-numeric input.
	 *
	 * @return void
	 */
	public function test_clamp_int(): void {
		$this->assert_same( 140, clamp_int( '10', 140, 480, 220 ) );
		$this->assert_same( 480, clamp_int( 9999, 140, 480, 220 ) );
		$this->assert_same( 300, clamp_int( '300', 140, 480, 220 ) );
		$this->assert_same( 220, clamp_int( 'abc', 140, 480, 220 ) );
		$this->assert_same( 220, clamp_int( null, 140, 480, 220 ) );
	}

	/**
	 * choice only returns allowlisted values.
	 *
	 * @return void
	 */
	public function test_choice(): void {
		$this->assert_same( 'fixed', choice( 'fixed', array( 'fixed', 'static' ), 'fixed' ) );
		$this->assert_same( 'fixed', choice( 'absolute', array( 'fixed', 'static' ), 'fixed' ) );
		$this->assert_same( 'fixed', choice( array( 'fixed' ), array( 'fixed', 'static' ), 'fixed' ) );
	}

	/**
	 * hex_color validates and falls back.
	 *
	 * @return void
	 */
	public function test_hex_color(): void {
		$this->assert_same( '#0f172a', hex_color( '#0f172a', '#ffffff' ) );
		$this->assert_same( '#fff', hex_color( '#fff', '#ffffff' ) );
		$this->assert_same( '#ffffff', hex_color( 'red', '#ffffff' ) );
		$this->assert_same( '#ffffff', hex_color( '', '#ffffff' ) );
		$this->assert_same( '#ffffff', hex_color( '#1234567', '#ffffff' ) );
	}
}
