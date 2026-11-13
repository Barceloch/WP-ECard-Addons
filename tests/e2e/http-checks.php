<?php
/**
 * Host-side HTTP checks for the addon template engine.
 *
 * Runs outside the container against http://localhost:8099:
 *   php tests/e2e/http-checks.php [base-url]
 *
 * Preconditions: the e2e stack is running, install.php and seed.php ran.
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
function my_ecard_check( string $label, bool $condition, string $detail = '' ): void {
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
 * @param string $url      Target URL.
 * @param array  $options  post (array), jar (string), follow (bool).
 * @return array{status:int,headers:string,body:string,error:string}
 */
function my_ecard_fetch( string $url, array $options = array() ): array {
	$handle = curl_init( $url );

	curl_setopt_array(
		$handle,
		array(
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_HEADER         => true,
			CURLOPT_FOLLOWLOCATION => ! empty( $options['follow'] ),
			CURLOPT_TIMEOUT        => 30,
			CURLOPT_USERAGENT      => 'my-ecard-e2e/1.0',
		)
	);

	if ( ! empty( $options['jar'] ) ) {
		curl_setopt( $handle, CURLOPT_COOKIEJAR, $options['jar'] );
		curl_setopt( $handle, CURLOPT_COOKIEFILE, $options['jar'] );
	}

	if ( isset( $options['post'] ) ) {
		curl_setopt( $handle, CURLOPT_POST, true );
		curl_setopt( $handle, CURLOPT_POSTFIELDS, http_build_query( (array) $options['post'] ) );
	}

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

	$status       = (int) curl_getinfo( $handle, CURLINFO_RESPONSE_CODE );
	$header_size  = (int) curl_getinfo( $handle, CURLINFO_HEADER_SIZE );
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
 * @param string $body    Body.
 * @param string $needle  Needle.
 * @return bool
 */
function my_ecard_contains( string $body, string $needle ): bool {
	return false !== strpos( $body, $needle );
}

/**
 * Count occurrences.
 *
 * @param string $body    Body.
 * @param string $needle  Needle.
 * @return int
 */
function my_ecard_count( string $body, string $needle ): int {
	return substr_count( $body, $needle );
}

/**
 * Extract the password form and its fields from a page.
 *
 * Submits exactly what a browser would submit, so the check stays valid if
 * core adds hidden fields.
 *
 * @param string $body Page body.
 * @return array{action:string,fields:array}|null
 */
function my_ecard_password_form( string $body ): ?array {
	if ( ! preg_match( '/<form[^>]*class="[^"]*post-password-form[^"]*"[^>]*>.*?<\/form>/s', $body, $match ) ) {
		return null;
	}

	$form = $match[0];

	if ( ! preg_match( '/action="([^"]*)"/', $form, $action ) ) {
		return null;
	}

	$fields = array();

	if ( preg_match_all( '/<input[^>]*name="([^"]+)"[^>]*>/', $form, $inputs, PREG_SET_ORDER ) ) {
		foreach ( $inputs as $input ) {
			$name  = $input[1];
			$value = '';

			if ( preg_match( '/value="([^"]*)"/', $input[0], $found ) ) {
				$value = html_entity_decode( $found[1], ENT_QUOTES );
			}

			$fields[ $name ] = $value;
		}
	}

	return array(
		'action' => html_entity_decode( $action[1], ENT_QUOTES ),
		'fields' => $fields,
	);
}

$home = my_ecard_fetch( $base . '/' );

my_ecard_check( 'site is reachable', 200 === $home['status'], $home['error'] );

$post_checks = array(
	'themed'        => array(),
	'protected'     => array(),
	'unknown-theme' => array(),
	'plain-card'    => array(),
	'plain-post'    => array(),
	'draft-card'    => array(),
	'private-card'  => array(),
);

foreach ( $post_checks as $slug => $unused ) {
	$post_checks[ $slug ] = my_ecard_fetch( $base . ( 'plain-post' === $slug ? '/' . $slug . '/' : '/ecard/' . $slug . '/' ) );
}

/*
 * Published card with a resolved theme.
 */
$themed = $post_checks['themed'];
$body   = $themed['body'];

my_ecard_check( 'themed card responds 200', 200 === $themed['status'], (string) $themed['status'] );
my_ecard_check( 'addon shell rendered', my_ecard_contains( $body, 'data-my-ecard-shell' ) );
my_ecard_check( 'addon theme template rendered', my_ecard_contains( $body, 'data-my-ecard-template="render"' ) );
my_ecard_check( 'card title rendered', my_ecard_contains( $body, 'Themed Card' ) );
my_ecard_check( 'card designation rendered', my_ecard_contains( $body, 'Architect' ) );
my_ecard_check( 'card email rendered', my_ecard_contains( $body, 'jane@example.com' ) );
my_ecard_check( 'theme dir partial rendered', my_ecard_contains( $body, 'fixture-footer' ) );
my_ecard_check( 'doctype rendered once', 1 === my_ecard_count( $body, '<!DOCTYPE html>' ) );
my_ecard_check( 'html lang attribute rendered', 1 === preg_match( '/<html[^>]+lang="/', $body ) );
my_ecard_check( 'charset rendered', my_ecard_contains( $body, '<meta charset="UTF-8">' ) );
my_ecard_check( 'wp_head ran once', 1 === my_ecard_count( $body, '<!--my-ecard-e2e-head-->' ) );
my_ecard_check( 'wp_body_open ran once', 1 === my_ecard_count( $body, '<!--my-ecard-e2e-body-open-->' ) );
my_ecard_check( 'wp_footer ran once', 1 === my_ecard_count( $body, '<!--my-ecard-e2e-footer-->' ) );
my_ecard_check( 'body_class carries the card classes', my_ecard_contains( $body, 'my-ecard-body' ) && my_ecard_contains( $body, 'single-ecard_digital_card' ) );
my_ecard_check( 'ECard original template not used', ! my_ecard_contains( $body, 'digital-card-content' ) );
my_ecard_check( 'no PHP fatal output', ! my_ecard_contains( $body, 'Fatal error' ) );

my_ecard_check(
	'hook order head < body_open < template < footer',
	my_ecard_contains( $body, '<!--my-ecard-e2e-head-->' )
		&& my_ecard_contains( $body, '<!--my-ecard-e2e-body-open-->' )
		&& my_ecard_contains( $body, 'data-my-ecard-template="render"' )
		&& strpos( $body, '<!--my-ecard-e2e-head-->' ) < strpos( $body, '<!--my-ecard-e2e-body-open-->' )
		&& strpos( $body, '<!--my-ecard-e2e-body-open-->' ) < strpos( $body, 'data-my-ecard-template="render"' )
		&& strpos( $body, 'data-my-ecard-template="render"' ) < strpos( $body, '<!--my-ecard-e2e-footer-->' )
);

/*
 * Password-protected card, anonymous.
 */
$protected = $post_checks['protected'];
$body      = $protected['body'];

my_ecard_check( 'password card responds 200', 200 === $protected['status'], (string) $protected['status'] );
my_ecard_check( 'password form rendered', my_ecard_contains( $body, 'post-password-form' ) );
my_ecard_check( 'password shell used', my_ecard_contains( $body, 'data-my-ecard-password' ) );
my_ecard_check( 'password view leaks no card email', ! my_ecard_contains( $body, 'secret@example.com' ) );
my_ecard_check( 'password view leaks no card company', ! my_ecard_contains( $body, 'ACMEP' ) );
my_ecard_check( 'password view uses no theme template', ! my_ecard_contains( $body, 'data-my-ecard-template' ) );
my_ecard_check( 'password view keeps wp_head', my_ecard_contains( $body, '<!--my-ecard-e2e-head-->' ) );
my_ecard_check( 'password view keeps wp_footer', my_ecard_contains( $body, '<!--my-ecard-e2e-footer-->' ) );

/*
 * Password-protected card with the correct password.
 */
$form = my_ecard_password_form( $protected['body'] );

my_ecard_check( 'password form is parsable', is_array( $form ) );

if ( is_array( $form ) ) {
	$jar    = tempnam( sys_get_temp_dir(), 'my-ecard-e2e' );
	$fields = $form['fields'];
	$fields['post_password'] = 'secret';

	$submit = my_ecard_fetch( $form['action'], array(
		'post' => $fields,
		'jar'  => $jar,
	) );

	my_ecard_check( 'password submission redirects', 302 === $submit['status'], (string) $submit['status'] );

	$unlocked = my_ecard_fetch( $base . '/ecard/protected/', array( 'jar' => $jar ) );
	$body     = $unlocked['body'];

	my_ecard_check( 'unlocked card responds 200', 200 === $unlocked['status'], (string) $unlocked['status'] );
	my_ecard_check( 'unlocked card renders the theme', my_ecard_contains( $body, 'data-my-ecard-template="render"' ) );
	my_ecard_check( 'unlocked card exposes card data', my_ecard_contains( $body, 'secret@example.com' ) );
	my_ecard_check( 'unlocked card hides the password form', ! my_ecard_contains( $body, 'post-password-form' ) );

	$wrong_jar    = tempnam( sys_get_temp_dir(), 'my-ecard-e2e' );
	$wrong_fields = $form['fields'];
	$wrong_fields['post_password'] = 'incorrect';

	my_ecard_fetch( $form['action'], array( 'post' => $wrong_fields, 'jar' => $wrong_jar ) );

	$denied = my_ecard_fetch( $base . '/ecard/protected/', array( 'jar' => $wrong_jar ) );
	$body   = $denied['body'];

	my_ecard_check( 'wrong password keeps the gate', my_ecard_contains( $body, 'post-password-form' ) );
	my_ecard_check( 'wrong password leaks no card data', ! my_ecard_contains( $body, 'secret@example.com' ) );

	@unlink( $jar );
	@unlink( $wrong_jar );
}

/*
 * Fallback paths keep the ECard template.
 */
$fallbacks = array(
	'unknown theme'         => $post_checks['unknown-theme'],
	'card without a theme'  => $post_checks['plain-card'],
	'invalid filtered context' => my_ecard_fetch( $base . '/ecard/themed/?my_ecard_e2e_break=invalid' ),
	'exception while filtering' => my_ecard_fetch( $base . '/ecard/themed/?my_ecard_e2e_break=exception' ),
);

foreach ( $fallbacks as $label => $response ) {
	$body = $response['body'];

	my_ecard_check( $label . ' responds 200', 200 === $response['status'], (string) $response['status'] );
	my_ecard_check( $label . ' falls back to ECard', my_ecard_contains( $body, 'digital-card-content' ) );
	my_ecard_check( $label . ' does not use the shell', ! my_ecard_contains( $body, 'data-my-ecard-shell' ) );
	my_ecard_check( $label . ' renders card data through ECard', my_ecard_contains( $body, 'Architect' ) );
	my_ecard_check( $label . ' has no fatal error', ! my_ecard_contains( $body, 'Fatal error' ) );
}

/*
 * Non-viewable cards never expose data anonymously.
 */
foreach ( array( 'draft card' => 'draft-card', 'private card' => 'private-card' ) as $label => $slug ) {
	$response = $post_checks[ $slug ];
	$body     = $response['body'];

	my_ecard_check( $label . ' is not served to anonymous users', 200 !== $response['status'], (string) $response['status'] );
	my_ecard_check( $label . ' exposes no theme shell', ! my_ecard_contains( $body, 'data-my-ecard-shell' ) );
	my_ecard_check( $label . ' exposes no card identifiers', ! my_ecard_contains( $body, 'data-my-ecard-template' ) );
	my_ecard_check( $label . ' exposes no card email', ! my_ecard_contains( $body, '@example.com' ) );
}

/*
 * Normal WordPress content is untouched.
 */
$plain = $post_checks['plain-post'];
$body  = $plain['body'];

my_ecard_check( 'normal post responds 200', 200 === $plain['status'], (string) $plain['status'] );
my_ecard_check( 'normal post is not intercepted', ! my_ecard_contains( $body, 'data-my-ecard-shell' ) );
my_ecard_check( 'normal post uses the active theme', my_ecard_contains( $body, '<!--my-ecard-e2e-head-->' ) );
my_ecard_check( 'normal post renders its content', my_ecard_contains( $body, 'Plain Post' ) );

$search = my_ecard_fetch( $base . '/?s=Themed' );

my_ecard_check( 'search request is not intercepted', ! my_ecard_contains( $search['body'], 'data-my-ecard-shell' ) );
my_ecard_check( 'search request has no fatal error', ! my_ecard_contains( $search['body'], 'Fatal error' ) );

$archive = my_ecard_fetch( $base . '/ecard/' );

my_ecard_check( 'card archive responds 200', 200 === $archive['status'], (string) $archive['status'] );
my_ecard_check( 'card archive is not intercepted', ! my_ecard_contains( $archive['body'], 'data-my-ecard-shell' ) );
my_ecard_check( 'card archive uses no theme template', ! my_ecard_contains( $archive['body'], 'data-my-ecard-template' ) );
my_ecard_check( 'card archive has no fatal error', ! my_ecard_contains( $archive['body'], 'Fatal error' ) );

/*
 * Missing cards.
 */
$missing = my_ecard_fetch( $base . '/ecard/does-not-exist/' );
my_ecard_check( 'missing card returns 404', 404 === $missing['status'], (string) $missing['status'] );
my_ecard_check( 'missing card has no shell', ! my_ecard_contains( $missing['body'], 'data-my-ecard-shell' ) );
my_ecard_check( 'missing card has no fatal error', ! my_ecard_contains( $missing['body'], 'Fatal error' ) );

echo 'summary: ' . $checks . ' checks, ' . $fails . " failed\n";
exit( $fails > 0 ? 1 : 0 );
