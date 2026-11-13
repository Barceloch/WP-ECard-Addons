<?php
/**
 * Host-side HTTP checks for the shared action bar.
 *
 * Runs outside the container against http://localhost:8099:
 *   php tests/e2e/action-bar-checks.php [base-url]
 *
 * Preconditions: the e2e stack is running and install.php, seed.php,
 * seed-reference.php and seed-phase6.php ran.
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
function my_ecard_ab_check( string $label, bool $condition, string $detail = '' ): void {
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
 * Perform an HTTP request.
 *
 * @param string $url Target URL.
 * @return array{status:int,body:string,error:string}
 */
function my_ecard_ab_fetch( string $url ): array {
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
 * The action bar segment, from its opening marker through </nav> inclusive.
 *
 * @param string $html Document.
 * @return string Empty string when no bar is rendered.
 */
function my_ecard_ab_bar( string $html ): string {
	$start = strpos( $html, 'data-my-ecard-action-bar' );

	if ( false === $start ) {
		return '';
	}

	/*
	 * The class attribute lives before the data attribute, so back up to the
	 * opening <nav> to keep position modifiers inside the segment.
	 */
	$nav = strrpos( substr( $html, 0, $start ), '<nav' );

	if ( false !== $nav ) {
		$start = $nav;
	}

	$end = strpos( $html, '</nav>', $start );

	if ( false === $end ) {
		return '';
	}

	return substr( $html, $start, $end - $start + strlen( '</nav>' ) );
}

/**
 * Action keys present in a bar segment, sorted.
 *
 * @param string $bar Bar segment.
 * @return array<int,string>
 */
function my_ecard_ab_actions( string $bar ): array {
	$actions = array();

	if ( preg_match_all( '/data-my-ecard-action="([^"]+)"/', $bar, $matches ) ) {
		$actions = array_values( array_unique( $matches[1] ) );
	}

	sort( $actions );

	return $actions;
}

/**
 * Human-readable action key list for failure details.
 *
 * @param string $bar Bar segment.
 * @return string
 */
function my_ecard_ab_actions_label( string $bar ): string {
	$actions = my_ecard_ab_actions( $bar );

	return array() === $actions ? '(none)' : implode( ',', $actions );
}

/**
 * Whether the bar holds exactly the expected action keys.
 *
 * @param string $bar      Bar segment.
 * @param array  $expected Expected action keys.
 * @return bool
 */
function my_ecard_ab_actions_are( string $bar, array $expected ): bool {
	$actual = my_ecard_ab_actions( $bar );

	/*
	 * QR depends on the optional renderer and GD. When it is available the
	 * action is expected; the dedicated QR checks cover its wiring.
	 */
	if ( in_array( 'qr', $actual, true ) ) {
		$expected[] = 'qr';
	}

	$expected = array_values( array_unique( $expected ) );
	sort( $expected );

	return $expected === $actual;
}

/**
 * Opening tag of one action, or an empty string.
 *
 * @param string $bar    Bar segment.
 * @param string $action Action key.
 * @return string
 */
function my_ecard_ab_tag( string $bar, string $action ): string {
	$pattern = '/<(?:a|button)\b[^>]*data-my-ecard-action="' . preg_quote( $action, '/' ) . '"[^>]*>/';

	if ( preg_match( $pattern, $bar, $match ) ) {
		return $match[0];
	}

	return '';
}

/**
 * Whether the bar markup sits inside the addon shell element.
 *
 * The bar must open after the shell div and before that div is closed.
 *
 * @param string $html Document.
 * @return bool
 */
function my_ecard_ab_in_shell( string $html ): bool {
	$shell = strpos( $html, 'data-my-ecard-shell' );
	$bar   = strpos( $html, 'data-my-ecard-action-bar' );

	if ( false === $shell || false === $bar || $bar < $shell ) {
		return false;
	}

	$open = strrpos( substr( $html, 0, $shell ), '<div' );

	if ( false === $open ) {
		return false;
	}

	$close = strpos( $html, '</nav>', $bar );

	if ( false === $close ) {
		return false;
	}

	$slice = substr( $html, $open, $close - $open );

	return preg_match_all( '/<div\b/i', $slice ) > preg_match_all( '#</div>#i', $slice );
}

$slugs = array(
	'phase6-full',
	'phase6-no-location',
	'phase6-no-vcard',
	'phase6-no-share',
	'phase6-no-bar',
	'phase6-labels-hide',
	'phase6-labels-mobile',
	'phase6-sticky',
	'phase6-static',
	'phase6-all-sections',
	'phase6-no-gallery',
	'phase6-no-videos',
	'phase6-disabled-gallery',
	'phase6-no-actions',
	'phase5-password',
	'phase5-private',
	'phase5-draft',
);

$cards = array();

foreach ( $slugs as $slug ) {
	$cards[ $slug ] = my_ecard_ab_fetch( $base . '/ecard/' . $slug . '/' );
}

$cards['phase6-missing'] = my_ecard_ab_fetch( $base . '/ecard/phase6-does-not-exist/' );

$html = $cards['phase6-full']['body'];
$bar  = my_ecard_ab_bar( $html );

my_ecard_ab_check(
	'full card responds 200',
	200 === $cards['phase6-full']['status'],
	(string) $cards['phase6-full']['status'] . ' ' . $cards['phase6-full']['error']
);
my_ecard_ab_check( 'full card renders the action bar', '' !== $bar );
my_ecard_ab_check(
	'full card bar sits inside the addon shell',
	my_ecard_ab_in_shell( $html )
);
my_ecard_ab_check(
	'full card bar defaults to a fixed position',
	false !== strpos( $bar, 'data-my-ecard-bar-position="fixed"' )
);
my_ecard_ab_check(
	'full card bar defaults to visible labels',
	false !== strpos( $bar, 'data-my-ecard-bar-labels="show"' )
);
my_ecard_ab_check(
	'full card bar lists vCard, location, share, gallery and videos',
	my_ecard_ab_actions_are( $bar, array( 'vcard', 'location', 'share', 'gallery', 'videos' ) ),
	my_ecard_ab_actions_label( $bar )
);

$vcard_tag    = my_ecard_ab_tag( $bar, 'vcard' );
$location_tag = my_ecard_ab_tag( $bar, 'location' );
$share_tag    = my_ecard_ab_tag( $bar, 'share' );

my_ecard_ab_check(
	'vCard action links to the download endpoint',
	'' !== $vcard_tag && false !== strpos( $vcard_tag, 'my_ecard_action=vcard' ),
	$vcard_tag
);
my_ecard_ab_check(
	'location action links to Google Maps for Madrid',
	'' !== $location_tag && (
		false !== strpos( $location_tag, 'https://maps.google.com/?q=Madrid' )
		|| false !== strpos( $location_tag, 'https%3A%2F%2Fmaps.google.com%2F%3Fq%3DMadrid' )
	),
	$location_tag
);
my_ecard_ab_check(
	'share action is a button carrying the card URL',
	'' !== $share_tag
		&& 1 === preg_match( '/^<button\b/', $share_tag )
		&& false !== strpos( $share_tag, 'data-my-ecard-share-url' )
		&& false !== strpos( $share_tag, '/ecard/phase6-full/' ),
	$share_tag
);
$qr_off     = my_ecard_ab_fetch( $base . '/ecard/phase6-full/?my_ecard_e2e_qr=0' );
$qr_off_bar = my_ecard_ab_bar( $qr_off['body'] );

my_ecard_ab_check( 'QR policy can hide the action', false === strpos( $qr_off_bar, 'data-my-ecard-action="qr"' ) );

$gallery_tag = my_ecard_ab_tag( $bar, 'gallery' );
$videos_tag  = my_ecard_ab_tag( $bar, 'videos' );

my_ecard_ab_check(
	'gallery action is a viewer-opening button',
	'' !== $gallery_tag
		&& 1 === preg_match( '/^<button\b/', $gallery_tag )
		&& false !== strpos( $gallery_tag, 'data-my-ecard-gallery-open' )
		&& false !== strpos( $gallery_tag, 'aria-controls="my-ecard-gallery-viewer"' ),
	$gallery_tag
);
my_ecard_ab_check(
	'videos action is a viewer-opening button',
	'' !== $videos_tag
		&& 1 === preg_match( '/^<button\b/', $videos_tag )
		&& false !== strpos( $videos_tag, 'data-my-ecard-videos-open' )
		&& false !== strpos( $videos_tag, 'aria-controls="my-ecard-videos-viewer"' ),
	$videos_tag
);
my_ecard_ab_check(
	'full card renders no in-card gallery section',
	false === strpos( $html, 'id="my-ecard-gallery"' )
);
my_ecard_ab_check(
	'full card renders no in-card videos section',
	false === strpos( $html, 'id="my-ecard-videos"' )
);
my_ecard_ab_check(
	'full card renders the gallery viewer dialog',
	1 === preg_match( '/id="my-ecard-gallery-viewer"[^>]*data-my-ecard-media-viewer="gallery"/', $html )
);
my_ecard_ab_check(
	'full card renders the videos viewer dialog',
	1 === preg_match( '/id="my-ecard-videos-viewer"[^>]*data-my-ecard-media-viewer="videos"/', $html )
);
my_ecard_ab_check(
	'full card server HTML contains no raw iframe',
	false === strpos( $html, '<iframe' )
);
my_ecard_ab_check(
	'QR dialog is absent when the policy hides it',
	false === strpos( $qr_off['body'], 'data-my-ecard-qr-dialog' )
);

$action_pattern = '/<(?:a|button)\b[^>]*data-my-ecard-action="[^"]+"[^>]*>/';
$all_actions    = preg_match_all( $action_pattern, $bar, $action_tags ) ? $action_tags[0] : array();
$missing_labels = 0;

foreach ( $all_actions as $action_tag ) {
	if ( false === strpos( $action_tag, 'aria-label=' ) ) {
		$missing_labels++;
	}
}

my_ecard_ab_check(
	'every action carries an aria-label',
	array() !== $all_actions && 0 === $missing_labels,
	(string) $missing_labels . ' unlabelled'
);
my_ecard_ab_check(
	'bar labels match the actions plus the nav label',
	substr_count( $bar, 'aria-label="' ) === count( $all_actions ) + 1,
	(string) substr_count( $bar, 'aria-label="' ) . ' labels for ' . count( $all_actions ) . ' actions'
);

$icons = preg_match_all( '/<i\b[^>]*my-ecard-action-bar__icon[^>]*>/', $bar, $icon_matches ) ? $icon_matches[0] : array();
$icons_hidden = 0;

foreach ( $icons as $icon ) {
	if ( false !== strpos( $icon, 'aria-hidden="true"' ) ) {
		$icons_hidden++;
	}
}

my_ecard_ab_check(
	'action icons are hidden from assistive tech',
	array() !== $icons && $icons_hidden === count( $icons ),
	$icons_hidden . '/' . count( $icons ) . ' hidden'
);

my_ecard_ab_check(
	'bar stylesheet is enqueued',
	false !== strpos( $html, 'assets/css/action-bar.css' )
);
my_ecard_ab_check(
	'bar script is enqueued',
	false !== strpos( $html, 'assets/js/action-bar.js' )
);
my_ecard_ab_check(
	'share status is an accessible live region',
	1 === preg_match( '/<span\b[^>]*data-my-ecard-share-status[^>]*role="status"[^>]*aria-live="polite"/', $bar )
);

/*
 * Individual actions switch off without disturbing the rest.
 */
$no_location = $cards['phase6-no-location']['body'];
$bar         = my_ecard_ab_bar( $no_location );

my_ecard_ab_check(
	'card without a location keeps vCard and share',
	my_ecard_ab_actions_are( $bar, array( 'vcard', 'share' ) ),
	my_ecard_ab_actions_label( $bar )
);
my_ecard_ab_check(
	'card without a location drops the location action',
	false === strpos( $bar, 'data-my-ecard-action="location"' )
);

$no_vcard = $cards['phase6-no-vcard']['body'];
$bar      = my_ecard_ab_bar( $no_vcard );

my_ecard_ab_check(
	'card with vCard disabled keeps share only',
	my_ecard_ab_actions_are( $bar, array( 'share' ) ),
	my_ecard_ab_actions_label( $bar )
);
my_ecard_ab_check(
	'card with vCard disabled drops the vCard action',
	false === strpos( $bar, 'data-my-ecard-action="vcard"' )
);

$no_share = $cards['phase6-no-share']['body'];
$bar      = my_ecard_ab_bar( $no_share );

my_ecard_ab_check(
	'card with share disabled keeps the vCard action',
	my_ecard_ab_actions_are( $bar, array( 'vcard' ) ),
	my_ecard_ab_actions_label( $bar )
);
my_ecard_ab_check(
	'card with share disabled drops the share action',
	false === strpos( $bar, 'data-my-ecard-action="share"' )
);
my_ecard_ab_check(
	'card with share disabled renders no status element',
	false === strpos( $no_share, 'data-my-ecard-share-status' )
);

/*
 * Bars that must not render at all.
 */
foreach ( array( 'phase6-no-bar', 'phase6-no-actions' ) as $slug ) {
	my_ecard_ab_check(
		$slug . ' renders no action bar',
		false === strpos( $cards[ $slug ]['body'], 'data-my-ecard-action-bar' )
	);
}

my_ecard_ab_check(
	'card without actions renders no media viewers',
	false === strpos( $cards['phase6-no-actions']['body'], 'data-my-ecard-media-viewer' )
);

/*
 * Label and position variants.
 */
$labels = array(
	'phase6-labels-hide'   => 'hide',
	'phase6-labels-mobile' => 'hide_mobile',
);

foreach ( $labels as $slug => $value ) {
	$variant = $cards[ $slug ]['body'];
	$bar     = my_ecard_ab_bar( $variant );

	my_ecard_ab_check(
		$slug . ' renders a bar',
		'' !== $bar
	);
	my_ecard_ab_check(
		$slug . ' carries the ' . $value . ' label mode',
		false !== strpos( $bar, 'data-my-ecard-bar-labels="' . $value . '"' )
			&& false === strpos( $bar, 'data-my-ecard-bar-labels="show"' )
	);
}

$positions = array(
	'phase6-sticky' => 'sticky',
	'phase6-static' => 'static',
);

foreach ( $positions as $slug => $value ) {
	$variant = $cards[ $slug ]['body'];
	$bar     = my_ecard_ab_bar( $variant );

	my_ecard_ab_check(
		$slug . ' renders a bar',
		'' !== $bar
	);
	my_ecard_ab_check(
		$slug . ' carries the ' . $value . ' position',
		false !== strpos( $bar, 'data-my-ecard-bar-position="' . $value . '"' )
	);
	my_ecard_ab_check(
		$slug . ' carries the ' . $value . ' modifier class',
		false !== strpos( $bar, 'my-ecard-action-bar--' . $value )
	);
}

/*
 * Section media: the gallery and videos actions open shell-owned viewer
 * dialogs instead of linking to theme anchors.
 */
$all_sections = $cards['phase6-all-sections']['body'];
$bar          = my_ecard_ab_bar( $all_sections );
$gallery_tag  = my_ecard_ab_tag( $bar, 'gallery' );
$videos_tag   = my_ecard_ab_tag( $bar, 'videos' );

my_ecard_ab_check(
	'all-sections card lists every available action',
	my_ecard_ab_actions_are( $bar, array( 'vcard', 'location', 'share', 'gallery', 'videos' ) ),
	my_ecard_ab_actions_label( $bar )
);
my_ecard_ab_check(
	'all-sections gallery action opens the gallery viewer',
	'' !== $gallery_tag
		&& 1 === preg_match( '/^<button\b/', $gallery_tag )
		&& false !== strpos( $gallery_tag, 'data-my-ecard-gallery-open' )
		&& false !== strpos( $gallery_tag, 'aria-controls="my-ecard-gallery-viewer"' ),
	$gallery_tag
);
my_ecard_ab_check(
	'all-sections videos action opens the videos viewer',
	'' !== $videos_tag
		&& 1 === preg_match( '/^<button\b/', $videos_tag )
		&& false !== strpos( $videos_tag, 'data-my-ecard-videos-open' )
		&& false !== strpos( $videos_tag, 'aria-controls="my-ecard-videos-viewer"' ),
	$videos_tag
);
my_ecard_ab_check(
	'all-sections card renders the gallery section',
	false !== strpos( $all_sections, 'id="my-ecard-gallery"' )
);
my_ecard_ab_check(
	'all-sections card renders the videos section',
	false !== strpos( $all_sections, 'id="my-ecard-videos"' )
);
my_ecard_ab_check(
	'all-sections card renders the gallery viewer dialog',
	1 === preg_match( '/id="my-ecard-gallery-viewer"[^>]*data-my-ecard-media-viewer="gallery"/', $all_sections )
);
my_ecard_ab_check(
	'all-sections card renders the videos viewer dialog',
	1 === preg_match( '/id="my-ecard-videos-viewer"[^>]*data-my-ecard-media-viewer="videos"/', $all_sections )
);

$no_gallery = $cards['phase6-no-gallery']['body'];
$bar        = my_ecard_ab_bar( $no_gallery );

my_ecard_ab_check(
	'card without gallery data drops only the gallery action',
	my_ecard_ab_actions_are( $bar, array( 'vcard', 'share', 'videos' ) ),
	my_ecard_ab_actions_label( $bar )
);
my_ecard_ab_check(
	'card without gallery data renders no gallery viewer',
	false === strpos( $no_gallery, 'id="my-ecard-gallery-viewer"' )
);
my_ecard_ab_check(
	'card without gallery data keeps the videos viewer',
	1 === preg_match( '/id="my-ecard-videos-viewer"[^>]*data-my-ecard-media-viewer="videos"/', $no_gallery )
);

$no_videos = $cards['phase6-no-videos']['body'];
$bar       = my_ecard_ab_bar( $no_videos );

my_ecard_ab_check(
	'card without videos drops only the videos action',
	my_ecard_ab_actions_are( $bar, array( 'vcard', 'share', 'gallery' ) ),
	my_ecard_ab_actions_label( $bar )
);
my_ecard_ab_check(
	'card without videos keeps the gallery viewer',
	1 === preg_match( '/id="my-ecard-gallery-viewer"[^>]*data-my-ecard-media-viewer="gallery"/', $no_videos )
);
my_ecard_ab_check(
	'card without videos renders no videos viewer',
	false === strpos( $no_videos, 'id="my-ecard-videos-viewer"' )
);

$disabled_gallery = $cards['phase6-disabled-gallery']['body'];
$bar              = my_ecard_ab_bar( $disabled_gallery );

my_ecard_ab_check(
	'disabled gallery still renders the gallery section',
	false !== strpos( $disabled_gallery, 'id="my-ecard-gallery"' )
);
my_ecard_ab_check(
	'disabled gallery drops the gallery action',
	false === strpos( $bar, 'data-my-ecard-action="gallery"' )
);
my_ecard_ab_check(
	'disabled gallery renders no gallery viewer',
	false === strpos( $disabled_gallery, 'id="my-ecard-gallery-viewer"' )
);
my_ecard_ab_check(
	'disabled gallery keeps the vCard and share actions',
	my_ecard_ab_actions_are( $bar, array( 'vcard', 'share' ) ),
	my_ecard_ab_actions_label( $bar )
);

/*
 * Protected and missing cards never expose a bar.
 */
$password = $cards['phase5-password'];

my_ecard_ab_check(
	'password card responds 200',
	200 === $password['status'],
	(string) $password['status']
);
my_ecard_ab_check(
	'password card renders the password form',
	false !== strpos( $password['body'], 'data-my-ecard-password' )
		&& false !== strpos( $password['body'], 'post-password-form' )
		&& false !== strpos( $password['body'], 'type="password"' )
);
my_ecard_ab_check(
	'password card renders no action bar',
	false === strpos( $password['body'], 'data-my-ecard-action-bar' )
);

foreach ( array( 'phase5-private', 'phase5-draft', 'phase6-missing' ) as $slug ) {
	$response = $cards[ $slug ];

	my_ecard_ab_check(
		$slug . ' returns 404',
		404 === $response['status'],
		(string) $response['status'] . ' ' . $response['error']
	);
	my_ecard_ab_check(
		$slug . ' renders no action bar',
		false === strpos( $response['body'], 'data-my-ecard-action-bar' )
	);
}

/*
 * Every response must be free of PHP diagnostics.
 */
$noisy = array();

foreach ( $cards as $slug => $response ) {
	if ( preg_match( '/Fatal error|Warning:|Notice:/', $response['body'] ) ) {
		$noisy[] = $slug;
	}
}

my_ecard_ab_check(
	'no page leaks PHP diagnostics',
	array() === $noisy,
	implode( ',', $noisy )
);

/*
 * QR modal integration under a controlled availability mock. The endpoint is
 * also exercised: it renders a real PNG when the renderer is available and
 * fails closed with 503 otherwise.
 */
$qr_mock = my_ecard_ab_fetch( $base . '/ecard/phase6-full/?my_ecard_e2e_qr=1' );
$qr_bar  = my_ecard_ab_bar( $qr_mock['body'] );

my_ecard_ab_check( 'QR mock page responds 200', 200 === $qr_mock['status'], (string) $qr_mock['status'] );
my_ecard_ab_check( 'QR action appears with the availability mock', false !== strpos( $qr_bar, 'data-my-ecard-action="qr"' ) );
my_ecard_ab_check( 'QR action is a dialog opener', false !== strpos( $qr_bar, 'data-my-ecard-qr-open' ) && false !== strpos( $qr_bar, 'aria-haspopup="dialog"' ) );
my_ecard_ab_check( 'QR dialog is rendered', false !== strpos( $qr_mock['body'], 'data-my-ecard-qr-dialog' ) );
my_ecard_ab_check( 'QR dialog has an accessible title', 1 === preg_match( '/data-my-ecard-qr-dialog[^>]*aria-labelledby="[^"]+"/', $qr_mock['body'] ) );
my_ecard_ab_check( 'only the bar provides the QR opener', 1 === substr_count( $qr_mock['body'], 'data-my-ecard-qr-open' ) );
my_ecard_ab_check( 'QR image points at the endpoint', 1 === preg_match( '/data-my-ecard-qr-image[^>]*src="[^"]*my_ecard_action=qr/', $qr_mock['body'] ) );

$qr_image = my_ecard_ab_fetch( $base . '/ecard/phase6-full/?my_ecard_action=qr' );

if ( 200 === $qr_image['status'] ) {
	my_ecard_ab_check( 'QR endpoint returns a PNG when the renderer is available', "\x89PNG\r\n\x1a\n" === substr( $qr_image['body'], 0, 8 ) );
} else {
	my_ecard_ab_check( 'QR endpoint fails closed without the renderer', 503 === $qr_image['status'], (string) $qr_image['status'] );
	my_ecard_ab_check( 'QR 503 explains the missing requirement', false !== strpos( $qr_image['body'], 'composer install' ) || false !== strpos( $qr_image['body'], 'GD extension' ), $qr_image['body'] );
}

echo 'summary: ' . $checks . ' checks, ' . $fails . " failed\n";
exit( $fails > 0 ? 1 : 0 );
