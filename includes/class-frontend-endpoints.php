<?php
/**
 * Front-end endpoints for vCard downloads and QR images.
 *
 * @package MyECardExtensions
 */

namespace MyECardExtensions;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Serves ?my_ecard_action=vcard and ?my_ecard_action=qr on card permalinks.
 *
 * Every request is re-validated here: the endpoint never trusts the template
 * loader, the theme or the browser, and it always reads card data through
 * CardData instead of touching ECard metadata.
 */
final class FrontendEndpoints {

	/**
	 * Query var that selects an endpoint.
	 */
	const ACTION_VAR = 'my_ecard_action';

	/**
	 * Recognised actions.
	 *
	 * @var string[]
	 */
	const ACTIONS = array( 'vcard', 'qr' );

	/**
	 * QR transient lifetime in seconds (12 hours).
	 */
	const QR_CACHE_TTL = 43200;

	/**
	 * Register the query var and the endpoint handler.
	 *
	 * @return void
	 */
	public function register(): void {
		add_filter( 'query_vars', array( $this, 'query_vars' ) );
		add_action( 'template_redirect', array( $this, 'handle' ) );
	}

	/**
	 * Make the action query var readable through get_query_var().
	 *
	 * @param mixed $vars Registered query vars.
	 * @return array
	 */
	public function query_vars( $vars ): array {
		$vars = is_array( $vars ) ? $vars : array();

		$vars[] = self::ACTION_VAR;

		return $vars;
	}

	/**
	 * Send the response for the current request and stop WordPress.
	 *
	 * @return void
	 */
	public function handle(): void {
		$response = $this->response_for_current_request();

		if ( null === $response ) {
			return;
		}

		$response->send();

		exit;
	}

	/**
	 * Build the response for the current request without sending it.
	 *
	 * @return EndpointResponse|null Null when the request is not an endpoint.
	 */
	public function response_for_current_request(): ?EndpointResponse {
		$action = $this->requested_action();

		if ( ! in_array( $action, self::ACTIONS, true ) ) {
			return null;
		}

		if ( ! is_singular( CardData::POST_TYPE ) ) {
			return $this->not_found();
		}

		$card = CardData::from_post( (int) get_queried_object_id() );

		if ( null === $card ) {
			return $this->not_found();
		}

		if ( ! VCardGenerator::is_public( $card ) ) {
			return $this->not_found();
		}

		if ( 'vcard' === $action ) {
			return $this->vcard_response( $card );
		}

		return $this->qr_response( $card );
	}

	/**
	 * Action requested through the query var.
	 *
	 * @return string
	 */
	private function requested_action(): string {
		$action = get_query_var( self::ACTION_VAR );

		return is_string( $action ) ? $action : '';
	}

	/**
	 * vCard download response.
	 *
	 * @param CardData $card Card read model.
	 * @return EndpointResponse
	 */
	private function vcard_response( CardData $card ): EndpointResponse {
		if ( empty( $card->settings['enable_vcard'] ) ) {
			return $this->not_found();
		}

		try {
			$generator = new VCardGenerator( $card );
			$body      = $generator->render();
			$headers   = $generator->headers();
		} catch ( \RuntimeException $exception ) {
			return $this->not_found();
		}

		return new EndpointResponse( 200, $headers, $body );
	}

	/**
	 * QR PNG response, cached in a transient for twelve hours.
	 *
	 * @param CardData $card Card read model.
	 * @return EndpointResponse
	 */
	private function qr_response( CardData $card ): EndpointResponse {
		if ( empty( $card->settings['enable_qr'] ) ) {
			return $this->not_found();
		}

		if ( ! QRCodeGenerator::is_available() ) {
			return $this->qr_unavailable( QRCodeGenerator::unavailable_reason() );
		}

		$generator = new QRCodeGenerator( $card, $card->settings );
		$cache_key = $generator->cache_key();
		$cached    = $this->cached_png( get_transient( $cache_key ) );

		if ( '' !== $cached ) {
			return $this->png_response( $cached );
		}

		try {
			$png = $generator->generate();
		} catch ( QRCodeUnavailableException $exception ) {
			unset( $exception );

			return $this->qr_unavailable( QRCodeGenerator::unavailable_reason() );
		} catch ( \Throwable $exception ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'WP ECard Addons: QR generation failed. ' . get_class( $exception ) . ': ' . $exception->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			}

			return new EndpointResponse(
				500,
				array(
					'Content-Type: text/plain; charset=utf-8',
					'Cache-Control: no-store',
					'X-Content-Type-Options: nosniff',
				),
				__( 'The QR code could not be generated. Check the plugin error log for details.', 'wp-ecard-addons' )
			);
		}

		/*
		 * Options are stored as text, so raw PNG bytes can be rejected by
		 * wpdb when they are not valid UTF-8. Base64 keeps the transient
		 * writable; the prefix lets cached values be validated on read.
		 */
		set_transient( $cache_key, 'base64:' . base64_encode( $png ), self::QR_CACHE_TTL );

		return $this->png_response( $png );
	}

	/**
	 * Decode a cached QR value into PNG bytes.
	 *
	 * Accepts the current base64 format and legacy raw PNG strings. Anything
	 * else is discarded so a corrupted transient never becomes a response.
	 *
	 * @param mixed $cached Cached transient value.
	 * @return string PNG bytes, or an empty string when the cache is invalid.
	 */
	private function cached_png( $cached ): string {
		if ( ! is_string( $cached ) || '' === $cached ) {
			return '';
		}

		$png = '';

		if ( 0 === strpos( $cached, 'base64:' ) ) {
			$decoded = base64_decode( substr( $cached, 7 ), true );

			if ( is_string( $decoded ) ) {
				$png = $decoded;
			}
		} elseif ( 0 === strpos( $cached, "\x89PNG\r\n\x1a\n" ) ) {
			$png = $cached;
		}

		if ( 0 !== strpos( $png, "\x89PNG\r\n\x1a\n" ) ) {
			return '';
		}

		return $png;
	}

	/**
	 * Controlled 503 response for an unavailable QR feature.
	 *
	 * @param string $reason "library", "gd", or empty when a site filter disabled QR.
	 * @return EndpointResponse
	 */
	private function qr_unavailable( string $reason ): EndpointResponse {
		if ( 'gd' === $reason ) {
			$message = __( 'QR codes are unavailable: the PHP GD extension is not enabled on this server.', 'wp-ecard-addons' );
		} elseif ( 'library' === $reason ) {
			$message = __( 'QR codes are unavailable: the PHP QR library is not installed. Run "composer install" in the plugin directory and try again.', 'wp-ecard-addons' );
		} else {
			$message = __( 'QR codes are disabled for this site.', 'wp-ecard-addons' );
		}

		return new EndpointResponse(
			503,
			array(
				'Content-Type: text/plain; charset=utf-8',
				'Cache-Control: no-store',
				'X-Content-Type-Options: nosniff',
			),
			$message
		);
	}

	/**
	 * Response for raw PNG bytes.
	 *
	 * @param string $png PNG bytes.
	 * @return EndpointResponse
	 */
	private function png_response( string $png ): EndpointResponse {
		return new EndpointResponse(
			200,
			array(
				'Content-Type: image/png',
				'Content-Length: ' . strlen( $png ),
				'Cache-Control: public, max-age=' . self::QR_CACHE_TTL,
				'X-Content-Type-Options: nosniff',
			),
			$png
		);
	}

	/**
	 * Response for every rejected request.
	 *
	 * @return EndpointResponse
	 */
	private function not_found(): EndpointResponse {
		return new EndpointResponse( 404, array( 'Cache-Control: no-store' ), '' );
	}
}
