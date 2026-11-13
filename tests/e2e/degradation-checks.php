<?php
/**
 * Host-side HTTP checks for the two degradation modes.
 *
 *   php tests/e2e/degradation-checks.php addon-off
 *   php tests/e2e/degradation-checks.php ecard-off
 *
 * @package MyECardExtensions
 */

error_reporting( E_ALL );

$base   = 'http://localhost:8099';
$mode   = isset( $argv[1] ) ? (string) $argv[1] : '';
$checks = 0;
$fails  = 0;
$notes  = 0;

if ( ! in_array( $mode, array( 'addon-off', 'ecard-off' ), true ) ) {
	fwrite( STDERR, "usage: degradation-checks.php addon-off|ecard-off\n" );
	exit( 2 );
}

/**
 * Record one check.
 *
 * @param string $label     Description.
 * @param bool   $condition Result.
 * @param string $detail    Failure detail.
 * @return void
 */
function my_ecard_check( string $label, bool $condition, string $detail = '' ): void {
	global $checks, $fails;

	$checks++;

	if ( $condition ) {
		echo 'PASS  ' . $label . "\n";
		return;
	}

	$fails++;
	echo 'FAIL  ' . $label . ( '' !== $detail ? ' [' . $detail . ']' : '' ) . "\n";
}

/**
 * Record an observed behaviour that is not a pass/fail criterion.
 *
 * @param string $message Message.
 * @return void
 */
function my_ecard_note( string $message ): void {
	global $notes;

	$notes++;
	echo 'NOTE  ' . $message . "\n";
}

/**
 * Fetch a URL.
 *
 * @param string $url URL.
 * @return array{status:int,body:string,error:string}
 */
function my_ecard_fetch( string $url ): array {
	$handle = curl_init( $url );

	curl_setopt_array(
		$handle,
		array(
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_FOLLOWLOCATION => false,
			CURLOPT_TIMEOUT        => 30,
			CURLOPT_USERAGENT      => 'my-ecard-e2e/1.0',
		)
	);

	$body = curl_exec( $handle );
	$err  = curl_error( $handle );
	$code = (int) curl_getinfo( $handle, CURLINFO_RESPONSE_CODE );

	curl_close( $handle );

	return array(
		'status' => $code,
		'body'   => false === $body ? '' : (string) $body,
		'error'  => $err,
	);
}

echo 'mode: ' . $mode . "\n";

if ( 'addon-off' === $mode ) {
	$themed = my_ecard_fetch( $base . '/ecard/themed/' );

	my_ecard_check( 'themed card still responds 200', 200 === $themed['status'], (string) $themed['status'] );
	my_ecard_check( 'ECard template renders the card', false !== strpos( $themed['body'], 'digital-card-content' ) );
	my_ecard_check( 'card data still visible', false !== strpos( $themed['body'], 'jane@example.com' ) );
	my_ecard_check( 'addon shell is gone', false === strpos( $themed['body'], 'data-my-ecard-shell' ) );
	my_ecard_check( 'no action bar without the addon', false === strpos( $themed['body'], 'data-my-ecard-action-bar' ) );
	my_ecard_check( 'no action bar stylesheet without the addon', false === strpos( $themed['body'], 'assets/css/action-bar.css' ) );
	my_ecard_check( 'no PHP fatal', false === strpos( $themed['body'], 'Fatal error' ) );

	$protected = my_ecard_fetch( $base . '/ecard/protected/' );

	my_ecard_check( 'protected card still responds 200', 200 === $protected['status'], (string) $protected['status'] );
	my_ecard_check( 'protected card has no fatal', false === strpos( $protected['body'], 'Fatal error' ) );

	if ( false !== strpos( $protected['body'], 'secret@example.com' ) ) {
		my_ecard_note(
			'ECard 1.6.0 renders password-protected cards without the password gate; '
			. 'this is upstream behaviour, unchanged by deactivating the addon.'
		);
	}

	$missing = my_ecard_fetch( $base . '/ecard/does-not-exist/' );

	my_ecard_check( 'missing card returns 404', 404 === $missing['status'], (string) $missing['status'] );
	my_ecard_check( 'missing card has no fatal', false === strpos( $missing['body'], 'Fatal error' ) );

	$post = my_ecard_fetch( $base . '/plain-post/' );

	my_ecard_check( 'normal post responds 200', 200 === $post['status'], (string) $post['status'] );
	my_ecard_check( 'normal post is not intercepted', false === strpos( $post['body'], 'data-my-ecard-shell' ) );
	my_ecard_check( 'normal post has no fatal', false === strpos( $post['body'], 'Fatal error' ) );
}

if ( 'ecard-off' === $mode ) {
	$card = my_ecard_fetch( $base . '/ecard/themed/' );

	/*
	 * WordPress keeps its stored rewrite rules after a plugin is deactivated,
	 * so the old card URL may resolve to the site front page. What matters is
	 * that nothing from the addon or the card is rendered.
	 */
	my_ecard_check( 'card URL renders no addon shell', false === strpos( $card['body'], 'data-my-ecard-shell' ) );
	my_ecard_check( 'card URL renders no theme template', false === strpos( $card['body'], 'data-my-ecard-template' ) );
	my_ecard_check( 'card URL renders no ECard template', false === strpos( $card['body'], 'digital-card-content' ) );
	my_ecard_check( 'card URL exposes no card email', false === strpos( $card['body'], '@example.com' ) );
	my_ecard_check( 'card URL exposes no card company', false === strpos( $card['body'], 'ACME' ) );
	my_ecard_check( 'card URL renders no action bar', false === strpos( $card['body'], 'data-my-ecard-action-bar' ) );
	my_ecard_check( 'card URL has no PHP fatal', false === strpos( $card['body'], 'Fatal error' ) );

	if ( 200 === $card['status'] ) {
		my_ecard_note(
			'/ecard/themed/ still resolves while ECard is off because WordPress reuses its stored '
			. 'rewrite rules; the response is the site front page with no card content.'
		);
	}

	$post = my_ecard_fetch( $base . '/plain-post/' );

	my_ecard_check( 'normal post responds 200', 200 === $post['status'], (string) $post['status'] );
	my_ecard_check( 'normal post is not intercepted', false === strpos( $post['body'], 'data-my-ecard-shell' ) );
	my_ecard_check( 'normal post has no fatal', false === strpos( $post['body'], 'Fatal error' ) );

	$home = my_ecard_fetch( $base . '/' );

	my_ecard_check( 'home page responds 200', 200 === $home['status'], (string) $home['status'] );
	my_ecard_check( 'home page has no fatal', false === strpos( $home['body'], 'Fatal error' ) );
}

echo 'summary: ' . $checks . ' checks, ' . $fails . ' failed, ' . $notes . " notes\n";
exit( $fails > 0 ? 1 : 0 );
