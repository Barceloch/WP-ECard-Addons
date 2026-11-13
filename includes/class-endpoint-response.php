<?php
/**
 * Immutable-by-convention HTTP response for the front-end endpoints.
 *
 * @package MyECardExtensions
 */

namespace MyECardExtensions;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Value object describing the response an endpoint should send.
 *
 * Building the response and sending it are separate steps so tests can assert
 * status, headers and body without terminating the request.
 */
final class EndpointResponse {

	/**
	 * HTTP status code.
	 *
	 * @var int
	 */
	public int $status;

	/**
	 * Header lines, each one already formatted.
	 *
	 * @var array
	 */
	public array $headers;

	/**
	 * Response body.
	 *
	 * @var string
	 */
	public string $body;

	/**
	 * Constructor.
	 *
	 * @param int      $status  HTTP status code.
	 * @param string[] $headers Header lines.
	 * @param string   $body    Response body.
	 */
	public function __construct( int $status, array $headers = array(), string $body = '' ) {
		$this->status  = $status;
		$this->headers = $headers;
		$this->body    = $body;
	}

	/**
	 * Send the response.
	 *
	 * The caller decides whether the request must also exit.
	 *
	 * @return void
	 */
	public function send(): void {
		status_header( $this->status );

		foreach ( $this->headers as $header ) {
			if ( is_string( $header ) && '' !== $header ) {
				header( $header );
			}
		}

		echo $this->body; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}
