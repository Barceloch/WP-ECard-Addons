<?php
/**
 * Front-end endpoint tests.
 *
 * @package MyECardExtensions
 */

namespace MyECardExtensions\Tests\Unit;

use MyECardExtensions\CardData;
use MyECardExtensions\EndpointResponse;
use MyECardExtensions\FrontendEndpoints;
use MyECardExtensions\QRCodeGenerator;
use MyECardExtensions\Tests\TestCase;
use WP_Post;

/**
 * Covers action routing, visibility gates, downloads and QR caching.
 */
final class FrontendEndpointsTest extends TestCase {

	/**
	 * Public reference card.
	 */
	const CARD_ID = 60;

	/**
	 * Draft card.
	 */
	const DRAFT_ID = 61;

	/**
	 * Private card.
	 */
	const PRIVATE_ID = 62;

	/**
	 * Password-protected card.
	 */
	const PASSWORD_ID = 63;

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
					'post_title'    => 'Marta Ruiz',
					'post_name'     => 'marta-ruiz',
					'post_status'   => 'publish',
					'post_password' => '',
					'post_content'  => '<p>Welcome</p>',
				),
				$props
			)
		);

		$GLOBALS['my_ecard_test_posts'][ $post_id ] = $post;

		return $post;
	}

	/**
	 * Assign the contact metadata used by the vCard download.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	private function configure( int $post_id ): void {
		update_post_meta( $post_id, '_ecard_designation', 'Product Designer' );
		update_post_meta( $post_id, '_ecard_company_name', 'Studio Norte' );
		update_post_meta( $post_id, '_ecard_email', 'marta@example.com' );
		update_post_meta( $post_id, '_ecard_phone', '+34 600 111 222' );
	}

	/**
	 * Simulate a request for one endpoint action.
	 *
	 * @param string $action    Value of the action query var.
	 * @param int    $post_id   Queried object ID.
	 * @param bool   $singular  Whether the request is singular.
	 * @return EndpointResponse|null
	 */
	private function request( string $action, int $post_id, bool $singular = true ): ?EndpointResponse {
		$GLOBALS['my_ecard_test_query_vars'][ FrontendEndpoints::ACTION_VAR ] = $action;
		$GLOBALS['my_ecard_test_singular']   = $singular;
		$GLOBALS['my_ecard_test_queried_id'] = $post_id;

		return ( new FrontendEndpoints() )->response_for_current_request();
	}

	/**
	 * Flatten response headers for substring assertions.
	 *
	 * @param EndpointResponse $response Response.
	 * @return string
	 */
	private function headers( EndpointResponse $response ): string {
		return implode( "\n", $response->headers );
	}

	/**
	 * Requests without a known action are ignored, not answered.
	 *
	 * @return void
	 */
	public function test_unknown_action_returns_null(): void {
		$this->card();
		$this->configure( self::CARD_ID );

		$this->assert_null( $this->request( 'bogus', self::CARD_ID ) );
		$this->assert_null( $this->request( '', self::CARD_ID ) );
	}

	/**
	 * Endpoints only answer singular requests.
	 *
	 * @return void
	 */
	public function test_non_singular_request_returns_404(): void {
		$this->card();
		$this->configure( self::CARD_ID );

		$response = $this->request( 'vcard', self::CARD_ID, false );

		$this->assert_not_null( $response );
		$this->assert_same( 404, $response->status );
		$this->assert_same( '', $response->body );
	}

	/**
	 * Draft, private and password-protected cards are rejected.
	 *
	 * @return void
	 */
	public function test_non_public_cards_return_404(): void {
		$cards = array(
			self::DRAFT_ID    => array( 'post_status' => 'draft' ),
			self::PRIVATE_ID  => array( 'post_status' => 'private' ),
			self::PASSWORD_ID => array( 'post_password' => 'secret' ),
		);

		foreach ( $cards as $post_id => $props ) {
			$this->card( (int) $post_id, $props );

			$response = $this->request( 'vcard', (int) $post_id );

			$this->assert_not_null( $response );
			$this->assert_same( 404, $response->status, 'Card ' . $post_id . ' must be rejected.' );
		}
	}

	/**
	 * Disabling a feature turns its endpoint into a 404.
	 *
	 * @return void
	 */
	public function test_disabled_features_return_404(): void {
		$this->card();
		$this->configure( self::CARD_ID );

		update_post_meta( self::CARD_ID, '_my_ecard_enable_vcard', 0 );

		$vcard = $this->request( 'vcard', self::CARD_ID );

		$this->assert_not_null( $vcard );
		$this->assert_same( 404, $vcard->status );

		update_post_meta( self::CARD_ID, '_my_ecard_enable_vcard', 1 );
		update_post_meta( self::CARD_ID, '_my_ecard_enable_qr', 0 );

		$qr = $this->request( 'qr', self::CARD_ID );

		$this->assert_not_null( $qr );
		$this->assert_same( 404, $qr->status );
	}

	/**
	 * A public card serves the vCard as a download.
	 *
	 * @return void
	 */
	public function test_public_card_serves_vcard_download(): void {
		$this->card();
		$this->configure( self::CARD_ID );

		$response = $this->request( 'vcard', self::CARD_ID );

		$this->assert_not_null( $response );
		$this->assert_same( 200, $response->status );

		$headers = $this->headers( $response );

		$this->assert_contains( 'Content-Type: text/vcard; charset=utf-8', $headers );
		$this->assert_contains( 'Content-Disposition: attachment', $headers );

		$this->assert_contains( 'BEGIN:VCARD', $response->body );
		$this->assert_contains( 'END:VCARD', $response->body );
		$this->assert_contains( 'FN:Marta Ruiz', $response->body );
	}

	/**
	 * The endpoint renders a PNG when the renderer is available, and explains
	 * the missing requirement otherwise.
	 *
	 * @return void
	 */
	public function test_qr_endpoint_follows_renderer_availability(): void {
		$this->card();
		$this->configure( self::CARD_ID );

		$response = $this->request( 'qr', self::CARD_ID );

		$this->assert_not_null( $response );

		if ( QRCodeGenerator::is_available() ) {
			$this->assert_same( 200, $response->status );
			$this->assert_contains( 'Content-Type: image/png', $this->headers( $response ) );
			$this->assert_same( "\x89PNG\r\n\x1a\n", substr( $response->body, 0, 8 ) );

			return;
		}

		$this->assert_same( 503, $response->status );
		$this->assert_contains( 'Content-Type: text/plain; charset=utf-8', $this->headers( $response ) );

		if ( 'gd' === QRCodeGenerator::unavailable_reason() ) {
			$this->assert_contains( 'GD extension', $response->body );
		} else {
			$this->assert_contains( 'composer install', $response->body );
		}
	}

	/**
	 * A site can switch QR off; the endpoint degrades to a controlled 503.
	 *
	 * @return void
	 */
	public function test_qr_is_unavailable_when_disabled_by_filter(): void {
		$this->card();
		$this->configure( self::CARD_ID );

		add_filter(
			'my_ecard_qr_available',
			static function () {
				return false;
			}
		);

		$response = $this->request( 'qr', self::CARD_ID );

		$this->assert_not_null( $response );
		$this->assert_same( 503, $response->status );

		if ( '' === QRCodeGenerator::unavailable_reason() ) {
			$this->assert_contains( 'disabled for this site', $response->body );
		} else {
			// The concrete runtime blocker (library or GD) is more actionable.
			$this->assert_true(
				false !== strpos( $response->body, 'composer install' )
					|| false !== strpos( $response->body, 'GD extension' ),
				$response->body
			);
		}
	}

	/**
	 * A cached PNG is served without touching the missing library.
	 *
	 * @return void
	 */
	public function test_qr_response_uses_the_cached_png(): void {
		$this->card();
		$this->configure( self::CARD_ID );

		$card = CardData::from_post( self::CARD_ID );

		$this->assert_not_null( $card );

		$key = ( new QRCodeGenerator( $card, $card->settings ) )->cache_key();
		$png = "\x89PNG\r\n\x1a\n" . 'FAKE';

		$GLOBALS['my_ecard_test_transients'][ $key ] = 'base64:' . base64_encode( $png );

		/*
		 * The endpoint now gates the cache behind is_available(). Force the
		 * filterable availability policy without loading the library: the
		 * cached PNG is served and generate() is never reached.
		 */
		add_filter(
			'my_ecard_qr_available',
			static function () {
				return true;
			}
		);

		$response = $this->request( 'qr', self::CARD_ID );

		$this->assert_not_null( $response );
		$this->assert_same( 200, $response->status );
		$this->assert_same( $png, $response->body );
		$this->assert_contains( 'Content-Type: image/png', $this->headers( $response ) );
		$this->assert_contains( 'Content-Length: ' . strlen( $png ), $this->headers( $response ) );
	}

	/**
	 * A corrupted transient value is never served as an image.
	 *
	 * @return void
	 */
	public function test_qr_response_ignores_a_corrupted_cache(): void {
		$this->card();
		$this->configure( self::CARD_ID );

		$card = CardData::from_post( self::CARD_ID );

		$this->assert_not_null( $card );

		$key = ( new QRCodeGenerator( $card, $card->settings ) )->cache_key();

		$GLOBALS['my_ecard_test_transients'][ $key ] = 'NOT-A-PNG';

		add_filter(
			'my_ecard_qr_available',
			static function () {
				return true;
			}
		);

		$response = $this->request( 'qr', self::CARD_ID );

		$this->assert_not_null( $response );
		$this->assert_true( in_array( $response->status, array( 200, 503 ), true ) );
		$this->assert_true( 'NOT-A-PNG' !== $response->body );

		if ( 200 === $response->status ) {
			$this->assert_same( "\x89PNG\r\n\x1a\n", substr( $response->body, 0, 8 ) );
		}
	}

	/**
	 * Sending a response writes the status and the body.
	 *
	 * The CLI test runner has already written output, so the native header()
	 * calls warn; those warnings are captured and verified, not printed.
	 *
	 * @return void
	 */
	public function test_send_sets_status_and_echoes_the_body(): void {
		$response = new EndpointResponse( 418, array( 'Content-Type: text/plain' ), 'teapot body' );
		$warnings = array();

		set_error_handler(
			static function ( $errno, $errstr ) use ( &$warnings ) {
				unset( $errno );
				$warnings[] = $errstr;
				return true;
			}
		);

		ob_start();
		$response->send();
		$output = (string) ob_get_clean();

		restore_error_handler();

		foreach ( $warnings as $warning ) {
			$this->assert_contains( 'Cannot modify header information', $warning );
		}

		$this->assert_same( 418, $GLOBALS['my_ecard_test_status'] );
		$this->assert_same( 'teapot body', $output );
	}
}
