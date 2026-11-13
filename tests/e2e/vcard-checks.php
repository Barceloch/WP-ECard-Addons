<?php
/**
 * Host-side HTTP checks for the vCard download and QR endpoints.
 *
 * Runs outside the container against http://localhost:8099:
 *   php tests/e2e/vcard-checks.php [base-url]
 *
 * Preconditions: the e2e stack is running, install.php, seed.php and
 * seed-phase5.php ran.
 *
 * @package MyECardExtensions
 */

error_reporting( E_ALL );

$base   = isset( $argv[1] ) ? rtrim( (string) $argv[1], '/' ) : 'http://localhost:8099';
$checks = 0;
$fails  = 0;

/**
 * Record one check.
 *
 * @param string $label     Description.
 * @param bool   $condition Result.
 * @param string $detail    Extra context printed on failure.
 * @return void
 */
function my_ecard_vc_check( string $label, bool $condition, string $detail = '' ): void {
	global $checks, $fails;

	$checks++;

	if ( $condition ) {
		echo 'PASS  ' . $label . "\n";
		return;
	}

	$fails++;

	if ( '' !== $detail ) {
		echo 'FAIL  ' . $label . ' [' . $detail . "]\n";
		return;
	}

	echo 'FAIL  ' . $label . "\n";
}

/**
 * Perform an HTTP request.
 *
 * @param string $url Target URL.
 * @return array{status:int,headers:string,body:string,error:string}
 */
function my_ecard_vc_fetch( string $url ): array {
	$handle = curl_init( $url );

	curl_setopt_array(
		$handle,
		array(
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_HEADER         => true,
			CURLOPT_FOLLOWLOCATION => false,
			CURLOPT_TIMEOUT        => 30,
			CURLOPT_USERAGENT      => 'my-ecard-e2e/1.0',
		)
	);

	$raw = curl_exec( $handle );

	if ( false === $raw ) {
		$error = curl_error( $handle );
		curl_close( $handle );

		return array(
			'status'  => 0,
			'headers' => '',
			'body'    => '',
			'error'   => $error,
		);
	}

	$status      = (int) curl_getinfo( $handle, CURLINFO_RESPONSE_CODE );
	$header_size = (int) curl_getinfo( $handle, CURLINFO_HEADER_SIZE );
	curl_close( $handle );

	return array(
		'status'  => $status,
		'headers' => substr( (string) $raw, 0, $header_size ),
		'body'    => substr( (string) $raw, $header_size ),
		'error'   => '',
	);
}

/**
 * Whether the body contains a string.
 *
 * @param string $body   Body.
 * @param string $needle Needle.
 * @return bool
 */
function my_ecard_vc_contains( string $body, string $needle ): bool {
	return false !== strpos( $body, $needle );
}

/**
 * Read one response header, case-insensitively.
 *
 * @param string $headers Raw header block.
 * @param string $name    Header name.
 * @return string Trimmed value or an empty string.
 */
function my_ecard_vc_header( string $headers, string $name ): string {
	if ( preg_match( '/^' . preg_quote( $name, '/' ) . ':\s*(.*)$/mi', $headers, $match ) ) {
		return trim( $match[1] );
	}

	return '';
}

/**
 * Longest physical line of CRLF-separated content, in octets.
 *
 * @param string $body Body.
 * @return int
 */
function my_ecard_vc_max_line( string $body ): int {
	$max = 0;

	foreach ( explode( "\r\n", $body ) as $line ) {
		$length = strlen( $line );

		if ( $length > $max ) {
			$max = $length;
		}
	}

	return $max;
}

/**
 * Whether every continuation line starts with exactly one space.
 *
 * @param string $body Body.
 * @return bool
 */
function my_ecard_vc_continuations_ok( string $body ): bool {
	$lines = explode( "\r\n", $body );

	foreach ( $lines as $index => $line ) {
		if ( 0 === $index || '' === $line || ' ' !== $line[0] ) {
			continue;
		}

		if ( isset( $line[1] ) && ' ' === $line[1] ) {
			return false;
		}
	}

	return true;
}

/**
 * Fetch the vCard download for a card slug.
 *
 * @param string $base Base URL.
 * @param string $slug Card slug.
 * @return array{status:int,headers:string,body:string,error:string}
 */
function my_ecard_vc_download( string $base, string $slug ): array {
	return my_ecard_vc_fetch( $base . '/ecard/' . $slug . '/?my_ecard_action=vcard' );
}

$home = my_ecard_vc_fetch( $base . '/' );

my_ecard_vc_check( 'site is reachable', 200 === $home['status'], $home['error'] );

/*
 * Complete card: every optional property is present and escaped.
 */
$full        = my_ecard_vc_download( $base, 'phase5-full' );
$body        = $full['body'];
$type        = my_ecard_vc_header( $full['headers'], 'Content-Type' );
$disposition = my_ecard_vc_header( $full['headers'], 'Content-Disposition' );
$cache       = my_ecard_vc_header( $full['headers'], 'Cache-Control' );
$nosniff     = my_ecard_vc_header( $full['headers'], 'X-Content-Type-Options' );

my_ecard_vc_check( 'vCard responds 200', 200 === $full['status'], (string) $full['status'] . ' ' . $full['error'] );
my_ecard_vc_check( 'vCard content type is text/vcard', my_ecard_vc_contains( strtolower( $type ), 'text/vcard' ), $type );
my_ecard_vc_check( 'vCard is UTF-8 encoded', my_ecard_vc_contains( strtolower( $type ), 'charset=utf-8' ), $type );
my_ecard_vc_check( 'vCard is a download attachment', my_ecard_vc_contains( strtolower( $disposition ), 'attachment' ), $disposition );
my_ecard_vc_check(
	'vCard filename is slugified from the title',
	1 === preg_match( '/filename="([^"]+)"/', $disposition, $filename ) && 'emilio-frias.vcf' === strtolower( $filename[1] ),
	$disposition
);

my_ecard_vc_check( 'vCard opens with BEGIN:VCARD', my_ecard_vc_contains( $body, 'BEGIN:VCARD' ) );
my_ecard_vc_check( 'vCard declares version 3.0', my_ecard_vc_contains( $body, 'VERSION:3.0' ) );
my_ecard_vc_check( 'vCard closes with END:VCARD', my_ecard_vc_contains( $body, 'END:VCARD' ) );
my_ecard_vc_check( 'vCard keeps UTF-8 accents in N', my_ecard_vc_contains( $body, 'N:Frías;Emilio;;;' ) );
my_ecard_vc_check( 'vCard renders the formatted name', my_ecard_vc_contains( $body, 'FN:Emilio Frías' ) );
my_ecard_vc_check( 'vCard escapes the company semicolon', my_ecard_vc_contains( $body, 'ORG:ACME\; Records' ) );
my_ecard_vc_check( 'vCard escapes the designation comma', my_ecard_vc_contains( $body, 'TITLE:Cantante\, Compositor' ) );
my_ecard_vc_check( 'vCard renders the normalized phone', my_ecard_vc_contains( $body, 'TEL;TYPE=VOICE:+34600111222' ) );
my_ecard_vc_check( 'vCard renders the normalized WhatsApp number', my_ecard_vc_contains( $body, 'TEL;TYPE=CELL:+34600333444' ) );
my_ecard_vc_check( 'vCard renders the email', my_ecard_vc_contains( $body, 'EMAIL;TYPE=INTERNET:emilio@example.com' ) );
my_ecard_vc_check( 'vCard renders the website', my_ecard_vc_contains( $body, 'URL:https://example.com/emilio' ) );
my_ecard_vc_check( 'vCard renders the card permalink', my_ecard_vc_contains( $body, 'URL:http://localhost:8099/ecard/phase5-full/' ) );
my_ecard_vc_check( 'vCard renders a work address', my_ecard_vc_contains( $body, 'ADR;TYPE=WORK:;;' ) );
my_ecard_vc_check( 'vCard renders the address postal city', my_ecard_vc_contains( $body, '28013 Madrid' ) );
my_ecard_vc_check( 'vCard escapes the address newline', my_ecard_vc_contains( $body, 'Calle Mayor 1\, 2ºB\n28013 Madrid' ) );

$max_line = my_ecard_vc_max_line( $body );

my_ecard_vc_check( 'no physical line exceeds 75 octets', $max_line <= 75, 'max ' . $max_line );
my_ecard_vc_check( 'continuation lines start with a single space', my_ecard_vc_continuations_ok( $body ) );
my_ecard_vc_check( 'no bare LF line endings', 0 === preg_match( '/(?<!\r)\n/', $body ) );

my_ecard_vc_check( 'vCard response is not cacheable', 1 === preg_match( '/no-store/i', $cache ), $cache );
my_ecard_vc_check( 'vCard response disables sniffing', 1 === preg_match( '/nosniff/i', $nosniff ), $nosniff );

/*
 * Minimal card: optional properties are omitted, not blank lines.
 */
$minimal       = my_ecard_vc_download( $base, 'phase5-minimal' );
$minimal_body  = $minimal['body'];
$minimal_lines = explode( "\r\n", $minimal_body );

my_ecard_vc_check( 'minimal vCard responds 200', 200 === $minimal['status'], (string) $minimal['status'] );
my_ecard_vc_check( 'minimal vCard renders the name', my_ecard_vc_contains( $minimal_body, 'FN:Madonna' ) );
my_ecard_vc_check( 'minimal single-token N is a given name', my_ecard_vc_contains( $minimal_body, 'N:;Madonna;;;' ) );

$forbidden = 0;

foreach ( $minimal_lines as $line ) {
	if ( 0 === strpos( $line, 'ORG:' ) || 0 === strpos( $line, 'TITLE:' ) || 0 === strpos( $line, 'TEL' ) || 0 === strpos( $line, 'ADR:' ) ) {
		$forbidden++;
	}
}

my_ecard_vc_check( 'minimal vCard omits empty properties', 0 === $forbidden, (string) $forbidden . ' unexpected lines' );
my_ecard_vc_check( 'minimal vCard keeps the email', my_ecard_vc_contains( $minimal_body, 'EMAIL;TYPE=INTERNET:madonna@example.com' ) );
my_ecard_vc_check( 'minimal vCard keeps the card URL', my_ecard_vc_contains( $minimal_body, 'URL:http://localhost:8099/ecard/phase5-minimal/' ) );

/*
 * Cards that must never be downloadable.
 */
$rejected = array(
	'vCard disabled'   => my_ecard_vc_download( $base, 'phase5-no-vcard' ),
	'draft card'       => my_ecard_vc_download( $base, 'phase5-draft' ),
	'private card'     => my_ecard_vc_download( $base, 'phase5-private' ),
	'password card'    => my_ecard_vc_download( $base, 'phase5-password' ),
	'missing card'     => my_ecard_vc_download( $base, 'phase5-does-not-exist' ),
	'non-card request' => my_ecard_vc_fetch( $base . '/?my_ecard_action=vcard' ),
);

foreach ( $rejected as $label => $response ) {
	my_ecard_vc_check( $label . ' returns 404', 404 === $response['status'], (string) $response['status'] );
}

$array_action = my_ecard_vc_fetch( $base . '/ecard/phase5-full/?my_ecard_action[]=vcard' );
$array_type   = my_ecard_vc_header( $array_action['headers'], 'Content-Type' );

my_ecard_vc_check( 'array action is not a vCard body', ! my_ecard_vc_contains( $array_action['body'], 'BEGIN:VCARD' ) );
my_ecard_vc_check( 'array action is not served as text/vcard', ! my_ecard_vc_contains( strtolower( $array_type ), 'text/vcard' ), $array_type );

/*
 * The plain permalink still renders the themed card.
 */
$plain      = my_ecard_vc_fetch( $base . '/ecard/phase5-full/' );
$plain_type = my_ecard_vc_header( $plain['headers'], 'Content-Type' );

my_ecard_vc_check( 'plain permalink responds 200', 200 === $plain['status'], (string) $plain['status'] );
my_ecard_vc_check( 'plain permalink is HTML', my_ecard_vc_contains( strtolower( $plain_type ), 'text/html' ), $plain_type );
my_ecard_vc_check( 'plain permalink renders the theme', my_ecard_vc_contains( $plain['body'], 'my-ecard-theme-reference' ) );

/*
 * QR endpoint: the release ships the renderer, so it either returns a real
 * PNG or a controlled 503 when the host lacks the library or GD.
 */
$qr      = my_ecard_vc_fetch( $base . '/ecard/phase5-full/?my_ecard_action=qr' );
$qr_type = my_ecard_vc_header( $qr['headers'], 'Content-Type' );

if ( 200 === $qr['status'] ) {
	my_ecard_vc_check( 'QR endpoint returns a PNG with the renderer available', "\x89PNG\r\n\x1a\n" === substr( $qr['body'], 0, 8 ) );
	my_ecard_vc_check( 'QR response is image/png', my_ecard_vc_contains( strtolower( $qr_type ), 'image/png' ), $qr_type );
} else {
	my_ecard_vc_check( 'QR endpoint fails closed without the renderer', 503 === $qr['status'], (string) $qr['status'] );
	my_ecard_vc_check( 'QR 503 explains the missing requirement', my_ecard_vc_contains( $qr['body'], 'composer install' ) || my_ecard_vc_contains( $qr['body'], 'GD extension' ), $qr['body'] );
	my_ecard_vc_check( 'QR 503 is plain text', my_ecard_vc_contains( strtolower( $qr_type ), 'text/plain' ), $qr_type );
}

$qr_no_vcard = my_ecard_vc_fetch( $base . '/ecard/phase5-no-vcard/?my_ecard_action=qr' );

my_ecard_vc_check( 'QR works independently of the vCard flag', in_array( $qr_no_vcard['status'], array( 200, 503 ), true ), (string) $qr_no_vcard['status'] );

$qr_disabled = my_ecard_vc_fetch( $base . '/ecard/phase5-no-qr/?my_ecard_action=qr' );

my_ecard_vc_check( 'disabled QR returns 404', 404 === $qr_disabled['status'], (string) $qr_disabled['status'] );

$qr_draft = my_ecard_vc_fetch( $base . '/ecard/phase5-draft/?my_ecard_action=qr' );

my_ecard_vc_check( 'draft card QR returns 404', 404 === $qr_draft['status'], (string) $qr_draft['status'] );

echo 'summary: ' . $checks . ' checks, ' . $fails . " failed\n";
exit( $fails > 0 ? 1 : 0 );
