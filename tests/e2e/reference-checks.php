<?php
/**
 * Host-side HTTP checks for the Reference theme.
 *
 *   php tests/e2e/reference-checks.php [base-url]
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
 * @param string $detail    Failure detail.
 * @return void
 */
function my_ecard_ref_check( string $label, bool $condition, string $detail = '' ): void {
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
 * Fetch a URL.
 *
 * @param string $url URL.
 * @return array{status:int,body:string,error:string}
 */
function my_ecard_ref_fetch( string $url ): array {
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

/**
 * Segment of the document that contains the action bar.
 *
 * The Reference theme does not render its own placeholder bar; the shell owns
 * the shared component, so the segment runs from its marker through the
 * closing nav element.
 *
 * @param string $html Document.
 * @return string Empty string when no bar is rendered.
 */
function my_ecard_ref_bar( string $html ): string {
	$start = strpos( $html, 'data-my-ecard-action-bar' );

	if ( false === $start ) {
		return '';
	}

	$end = strpos( $html, '</nav>', $start );

	if ( false === $end ) {
		return '';
	}

	return substr( $html, $start, $end - $start + strlen( '</nav>' ) );
}

$full = my_ecard_ref_fetch( $base . '/ecard/reference-full/' );
$html = $full['body'];

my_ecard_ref_check( 'reference card responds 200', 200 === $full['status'], (string) $full['status'] . ' ' . $full['error'] );
my_ecard_ref_check( 'addon shell rendered', false !== strpos( $html, 'data-my-ecard-shell' ) );
my_ecard_ref_check( 'reference theme root rendered', false !== strpos( $html, 'my-ecard-theme-reference' ) );
my_ecard_ref_check( 'theme is not the fallback', false === strpos( $html, 'digital-card-content' ) );
my_ecard_ref_check( 'theme stylesheet enqueued', 1 === preg_match( '#/themes/reference/style\.css\?ver=#', $html ) );
my_ecard_ref_check( 'configured header height applied', false !== strpos( $html, '--my-ecard-reference-hero:260px' ) );
my_ecard_ref_check( 'panoramic image rendered', false !== strpos( $html, 'my-ecard-theme-reference__hero-image' ) );
my_ecard_ref_check( 'avatar rendered', false !== strpos( $html, 'my-ecard-theme-reference__avatar"' ) );
my_ecard_ref_check( 'name rendered', false !== strpos( $html, 'Alex Rivera' ) );
my_ecard_ref_check( 'designation rendered', false !== strpos( $html, 'Product Designer &amp; Speaker' ) );
my_ecard_ref_check( 'company rendered', false !== strpos( $html, 'Studio Norte' ) );
my_ecard_ref_check( 'phone link is functional', false !== strpos( $html, 'href="tel:+34600111222"' ) );
my_ecard_ref_check( 'email link is functional', false !== strpos( $html, 'href="mailto:alex@example.com"' ) );
my_ecard_ref_check( 'whatsapp link is functional', false !== strpos( $html, 'https://wa.me/34600333444' ) );
my_ecard_ref_check( 'five social links rendered', 5 === substr_count( $html, 'my-ecard-theme-reference__social-link ' ) );
my_ecard_ref_check( 'instagram brand modifier', false !== strpos( $html, 'social-link--instagram' ) );
my_ecard_ref_check( 'linkedin brand modifier', false !== strpos( $html, 'social-link--linkedin' ) );
my_ecard_ref_check( 'social links open safely', false !== strpos( $html, 'rel="noopener noreferrer nofollow"' ) );
my_ecard_ref_check( 'hero and avatar are different images', 1 === preg_match( '/__hero-image[^>]*src="([^"]+)"/', $html, $hero ) && 1 === preg_match( '/__avatar"[^>]*src="([^"]+)"/', $html, $avatar ) && $hero[1] !== $avatar[1] );
my_ecard_ref_check( 'no PHP diagnostic in output', ! preg_match( '/Fatal error|Warning:|Notice:/', $html ) );

$bar = my_ecard_ref_bar( $html );

my_ecard_ref_check( 'action bar rendered', '' !== $bar && false !== strpos( $html, 'data-my-ecard-action-bar' ) );
my_ecard_ref_check( 'bar defaults to a fixed position', false !== strpos( $bar, 'data-my-ecard-bar-position="fixed"' ) );

preg_match( '/<(?:a|button)\b[^>]*data-my-ecard-action="vcard"[^>]*>/', $bar, $vcard_tag );
preg_match( '/<(?:a|button)\b[^>]*data-my-ecard-action="location"[^>]*>/', $bar, $location_tag );
preg_match( '/<(?:a|button)\b[^>]*data-my-ecard-action="share"[^>]*>/', $bar, $share_tag );

$vcard_tag    = isset( $vcard_tag[0] ) ? $vcard_tag[0] : '';
$location_tag = isset( $location_tag[0] ) ? $location_tag[0] : '';
$share_tag    = isset( $share_tag[0] ) ? $share_tag[0] : '';

my_ecard_ref_check(
	'vCard action links to the download endpoint',
	'' !== $vcard_tag && false !== strpos( $vcard_tag, 'href=' ) && false !== strpos( $vcard_tag, 'my_ecard_action=vcard' ),
	$vcard_tag
);
my_ecard_ref_check(
	'location action links to Google Maps for Madrid',
	'' !== $location_tag && (
		false !== strpos( $location_tag, 'https://maps.google.com/?q=Madrid' )
		|| false !== strpos( $location_tag, 'https%3A%2F%2Fmaps.google.com%2F%3Fq%3DMadrid' )
	),
	$location_tag
);
my_ecard_ref_check(
	'share action is a button carrying the card URL',
	'' !== $share_tag
		&& 1 === preg_match( '/^<button\b/', $share_tag )
		&& false !== strpos( $share_tag, 'data-my-ecard-share-url' ),
	$share_tag
);
$qr_off = my_ecard_ref_fetch( $base . '/ecard/reference-full/?my_ecard_e2e_qr=0' );

my_ecard_ref_check( 'QR policy can hide the action', false === strpos( my_ecard_ref_bar( $qr_off['body'] ), 'data-my-ecard-action="qr"' ) );

preg_match( '/<(?:a|button)\b[^>]*data-my-ecard-action="gallery"[^>]*>/', $bar, $gallery_tag );
preg_match( '/<(?:a|button)\b[^>]*data-my-ecard-action="videos"[^>]*>/', $bar, $videos_tag );

$gallery_tag = isset( $gallery_tag[0] ) ? $gallery_tag[0] : '';
$videos_tag  = isset( $videos_tag[0] ) ? $videos_tag[0] : '';

my_ecard_ref_check(
	'gallery action opens the gallery viewer',
	'' !== $gallery_tag
		&& 1 === preg_match( '/^<button\b/', $gallery_tag )
		&& false !== strpos( $gallery_tag, 'data-my-ecard-gallery-open' )
		&& false !== strpos( $gallery_tag, 'aria-controls="my-ecard-gallery-viewer"' ),
	$gallery_tag
);
my_ecard_ref_check(
	'videos action opens the videos viewer',
	'' !== $videos_tag
		&& 1 === preg_match( '/^<button\b/', $videos_tag )
		&& false !== strpos( $videos_tag, 'data-my-ecard-videos-open' )
		&& false !== strpos( $videos_tag, 'aria-controls="my-ecard-videos-viewer"' ),
	$videos_tag
);
my_ecard_ref_check(
	'card body renders no gallery section',
	false === strpos( $html, 'id="my-ecard-gallery"' )
		&& false === strpos( $html, 'my-ecard-theme-reference__gallery' )
);
my_ecard_ref_check(
	'card body renders no gallery thumbnails',
	false === strpos( $html, 'gallery-grid' )
		&& false === strpos( $html, 'gallery-item' )
);
my_ecard_ref_check(
	'card body renders no videos section',
	false === strpos( $html, 'id="my-ecard-videos"' )
		&& false === strpos( $html, 'my-ecard-theme-reference__video' )
);
my_ecard_ref_check(
	'card body renders no video links',
	false === strpos( $html, 'video-link' )
		&& false === strpos( $html, 'video-list' )
);
my_ecard_ref_check(
	'no empty media wrapper or separator remains',
	false === strpos( $html, 'my-ecard-theme-reference__section' )
);
my_ecard_ref_check(
	'gallery viewer markup is present',
	false !== strpos( $html, 'id="my-ecard-gallery-viewer"' )
);
my_ecard_ref_check(
	'videos viewer markup is present',
	false !== strpos( $html, 'id="my-ecard-videos-viewer"' )
);
my_ecard_ref_check( 'no embedded player is rendered', false === strpos( $html, '<iframe' ) );

$all_actions    = preg_match_all( '/<(?:a|button)\b[^>]*data-my-ecard-action="[^"]+"[^>]*>/', $bar, $action_tags ) ? $action_tags[0] : array();
$missing_labels = 0;

foreach ( $all_actions as $action_tag ) {
	if ( false === strpos( $action_tag, 'aria-label=' ) ) {
		$missing_labels++;
	}
}

my_ecard_ref_check(
	'every action carries an aria-label',
	array() !== $all_actions && 0 === $missing_labels,
	(string) $missing_labels . ' unlabelled'
);
my_ecard_ref_check(
	'share status is an accessible live region',
	1 === preg_match( '/<span\b[^>]*data-my-ecard-share-status[^>]*role="status"[^>]*aria-live="polite"/', $bar )
);
my_ecard_ref_check( 'bar stylesheet is enqueued', false !== strpos( $html, 'assets/css/action-bar.css' ) );
my_ecard_ref_check( 'bar script is enqueued', false !== strpos( $html, 'assets/js/action-bar.js' ) );
my_ecard_ref_check( 'no placeholder availability markers remain', false === strpos( $html, 'data-my-ecard-available' ) );

$plain = my_ecard_ref_fetch( $base . '/ecard/reference-no-avatar/' );
$body  = $plain['body'];

my_ecard_ref_check( 'avatar-less card responds 200', 200 === $plain['status'], (string) $plain['status'] );
my_ecard_ref_check( 'avatar is absent, not hidden', false === strpos( $body, 'my-ecard-theme-reference__avatar' ) );
my_ecard_ref_check( 'no cover keeps the gradient hero', false === strpos( $body, 'my-ecard-theme-reference__hero-image' ) );
my_ecard_ref_check( 'avatar-less card keeps identity', false !== strpos( $body, 'Marta Sol' ) && false !== strpos( $body, 'Founder' ) );
my_ecard_ref_check( 'empty company is not rendered', false === strpos( $body, 'my-ecard-theme-reference__company' ) );
my_ecard_ref_check( 'single social link rendered', 1 === substr_count( $body, 'my-ecard-theme-reference__social-link ' ) );
my_ecard_ref_check( 'only email contact rendered', false !== strpos( $body, 'contact--email' ) && false === strpos( $body, 'contact--phone' ) && false === strpos( $body, 'contact--whatsapp' ) );

$bar = my_ecard_ref_bar( $body );

my_ecard_ref_check( 'avatar-less card renders the action bar', '' !== $bar );
$ref_actions = substr_count( $bar, 'data-my-ecard-action="' );
$ref_has_qr  = false !== strpos( $bar, 'data-my-ecard-action="qr"' );

my_ecard_ref_check(
	'avatar-less bar keeps vCard and share only',
	( 2 + ( $ref_has_qr ? 1 : 0 ) ) === $ref_actions
		&& false !== strpos( $bar, 'data-my-ecard-action="vcard"' )
		&& false !== strpos( $bar, 'data-my-ecard-action="share"' ),
	$bar
);
my_ecard_ref_check( 'bar without a location drops the location action', false === strpos( $bar, 'data-my-ecard-action="location"' ) );
my_ecard_ref_check( 'bar without gallery data drops the gallery action', false === strpos( $bar, 'data-my-ecard-action="gallery"' ) );
my_ecard_ref_check( 'bar without video data drops the videos action', false === strpos( $bar, 'data-my-ecard-action="videos"' ) );

/*
 * The stylesheet actually served by WordPress is the theme file.
 */
preg_match( "#(https?://[^\"']+/themes/reference/style\.css\?ver=[^\"']+)#", $html, $style_url );
$css = array() === $style_url ? array( 'status' => 0, 'body' => '' ) : my_ecard_ref_fetch( $style_url[1] );

my_ecard_ref_check( 'stylesheet responds 200', 200 === $css['status'], (string) $css['status'] );
my_ecard_ref_check( 'stylesheet is namespaced', false !== strpos( $css['body'], '.my-ecard-theme-reference' ) );
my_ecard_ref_check( 'stylesheet defines the hero variable', false !== strpos( $css['body'], '--my-ecard-reference-hero' ) );
my_ecard_ref_check( 'stylesheet ships responsive rules', false !== strpos( $css['body'], '@media (max-width: 480px)' ) );

echo 'summary: ' . $checks . ' checks, ' . $fails . " failed\n";
exit( $fails > 0 ? 1 : 0 );
