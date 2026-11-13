<?php
/**
 * Host-side HTTP checks for the gallery and videos sections.
 *
 * Runs outside the container against http://localhost:8099:
 *   php tests/e2e/gallery-video-checks.php [base-url]
 *
 * Preconditions: the e2e stack is running and seed-phase7.php created the
 * phase7-* cards.
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
function my_ecard_gv_check( string $label, bool $condition, string $detail = '' ): void {
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
function my_ecard_gv_fetch( string $url ): array {
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
function my_ecard_gv_bar( string $html ): string {
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
 * One <section> by its id, or an empty string.
 *
 * @param string $html Document.
 * @param string $id   Section id.
 * @return string
 */
function my_ecard_gv_section( string $html, string $id ): string {
	$marker = strpos( $html, 'id="' . $id . '"' );

	if ( false === $marker ) {
		return '';
	}

	$open = strrpos( substr( $html, 0, $marker ), '<section' );

	if ( false === $open ) {
		return '';
	}

	$end = strpos( $html, '</section>', $marker );

	if ( false === $end ) {
		return '';
	}

	return substr( $html, $open, $end - $open + strlen( '</section>' ) );
}

/**
 * Action keys present in a bar segment, sorted.
 *
 * @param string $bar Bar segment.
 * @return array<int,string>
 */
function my_ecard_gv_actions( string $bar ): array {
	$actions = array();

	if ( preg_match_all( '/data-my-ecard-action="([^"]+)"/', $bar, $matches ) ) {
		$actions = array_values( array_unique( $matches[1] ) );
	}

	// QR availability depends on the host; it is covered by dedicated checks.
	$actions = array_values( array_diff( $actions, array( 'qr' ) ) );

	sort( $actions );

	return $actions;
}

/**
 * Human-readable action key list for failure details.
 *
 * @param string $bar Bar segment.
 * @return string
 */
function my_ecard_gv_actions_label( string $bar ): string {
	$actions = my_ecard_gv_actions( $bar );

	return array() === $actions ? '(none)' : implode( ',', $actions );
}

/**
 * Opening tag of one action, or an empty string.
 *
 * @param string $bar    Bar segment.
 * @param string $action Action key.
 * @return string
 */
function my_ecard_gv_tag( string $bar, string $action ): string {
	$pattern = '/<(?:a|button)\b[^>]*data-my-ecard-action="' . preg_quote( $action, '/' ) . '"[^>]*>/';

	if ( preg_match( $pattern, $bar, $match ) ) {
		return $match[0];
	}

	return '';
}

/**
 * Whether a section is present in the document.
 *
 * @param string $html Document.
 * @param string $id   Section id.
 * @return bool
 */
function my_ecard_gv_has_section( string $html, string $id ): bool {
	return false !== strpos( $html, 'id="' . $id . '"' );
}

/**
 * One <dialog> viewer by its id, or an empty string.
 *
 * @param string $html Document.
 * @param string $id   Dialog id.
 * @return string
 */
function my_ecard_gv_viewer( string $html, string $id ): string {
	$marker = strpos( $html, 'id="' . $id . '"' );

	if ( false === $marker ) {
		return '';
	}

	$open = strrpos( substr( $html, 0, $marker ), '<dialog' );

	if ( false === $open ) {
		return '';
	}

	$end = strpos( $html, '</dialog>', $marker );

	if ( false === $end ) {
		return '';
	}

	return substr( $html, $open, $end - $open + strlen( '</dialog>' ) );
}

$cards = array();

foreach ( array( 'phase7-gallery', 'phase7-videos', 'phase7-both', 'phase7-none', 'phase7-actions-off', 'phase7-no-theme', 'phase7-missing-theme', 'phase7-external', 'phase7-external-media', 'phase7-invalid-videos' ) as $slug ) {
	$cards[ $slug ] = my_ecard_gv_fetch( $base . '/ecard/' . $slug . '/' );
}

/*
 * Gallery-only card.
 */
$gallery_html    = $cards['phase7-gallery']['body'];
$gallery_section = my_ecard_gv_section( $gallery_html, 'my-ecard-gallery' );
$gallery_bar     = my_ecard_gv_bar( $gallery_html );

my_ecard_gv_check(
	'gallery card responds 200',
	200 === $cards['phase7-gallery']['status'],
	(string) $cards['phase7-gallery']['status'] . ' ' . $cards['phase7-gallery']['error']
);
my_ecard_gv_check(
	'gallery card renders no gallery section',
	'' === $gallery_section
);
my_ecard_gv_check(
	'gallery card renders no theme gallery markup',
	false === strpos( $gallery_html, 'my-ecard-theme-reference__gallery' )
		&& false === strpos( $gallery_html, 'gallery-grid' )
		&& false === strpos( $gallery_html, 'gallery-item' )
);
my_ecard_gv_check(
	'gallery card renders no media section wrapper',
	false === strpos( $gallery_html, 'my-ecard-theme-reference__section' )
);

$gallery_items = substr_count( $gallery_html, 'data-my-ecard-media-item' );

my_ecard_gv_check(
	'gallery viewer lists three media items',
	3 === $gallery_items,
	(string) $gallery_items . ' items'
);
my_ecard_gv_check(
	'gallery viewer exposes a full-size source per item',
	3 === substr_count( $gallery_html, 'data-src=' )
);
my_ecard_gv_check(
	'gallery viewer renders no theme gallery link',
	false === strpos( $gallery_html, 'my-ecard-theme-reference__gallery-link' )
);
my_ecard_gv_check(
	'gallery card embeds nothing',
	false === strpos( $gallery_html, '<iframe' )
);
my_ecard_gv_check(
	'gallery card renders no videos section',
	false === strpos( $gallery_html, 'id="my-ecard-videos"' )
);

$gallery_action = my_ecard_gv_tag( $gallery_bar, 'gallery' );
$gallery_viewer = my_ecard_gv_viewer( $gallery_html, 'my-ecard-gallery-viewer' );

my_ecard_gv_check(
	'gallery bar opens the gallery viewer',
	'' !== $gallery_action
		&& 1 === preg_match( '/^<button\b/', $gallery_action )
		&& false !== strpos( $gallery_action, 'data-my-ecard-gallery-open' )
		&& false !== strpos( $gallery_action, 'aria-controls="my-ecard-gallery-viewer"' ),
	$gallery_action
);
my_ecard_gv_check(
	'gallery bar has no videos action',
	false === strpos( $gallery_bar, 'data-my-ecard-action="videos"' )
);
$gallery_actions  = my_ecard_gv_actions( $gallery_bar );
$expected_actions = array( 'gallery', 'location', 'share', 'vcard' );

if ( in_array( 'qr', $gallery_actions, true ) ) {
	$expected_actions[] = 'qr';
}

sort( $gallery_actions );
sort( $expected_actions );

my_ecard_gv_check(
	'gallery bar keeps vCard, location, share and gallery',
	$expected_actions === $gallery_actions,
	my_ecard_gv_actions_label( $gallery_bar )
);
my_ecard_gv_check(
	'gallery card renders the gallery viewer dialog',
	'' !== $gallery_viewer && false !== strpos( $gallery_viewer, 'data-my-ecard-media-viewer="gallery"' )
);
my_ecard_gv_check(
	'gallery viewer holds three media items',
	3 === substr_count( $gallery_viewer, 'data-my-ecard-media-item' ),
	(string) substr_count( $gallery_viewer, 'data-my-ecard-media-item' ) . ' items'
);
my_ecard_gv_check(
	'gallery viewer renders a media image',
	false !== strpos( $gallery_viewer, 'data-my-ecard-media-image' )
);
my_ecard_gv_check(
	'gallery card renders no videos viewer',
	false === strpos( $gallery_html, 'id="my-ecard-videos-viewer"' )
);
my_ecard_gv_check(
	'gallery card renders no raw iframe',
	false === strpos( $gallery_html, '<iframe' )
);

/*
 * Videos-only card.
 */
$videos_html    = $cards['phase7-videos']['body'];
$videos_section = my_ecard_gv_section( $videos_html, 'my-ecard-videos' );
$videos_bar     = my_ecard_gv_bar( $videos_html );

my_ecard_gv_check(
	'videos card responds 200',
	200 === $cards['phase7-videos']['status'],
	(string) $cards['phase7-videos']['status'] . ' ' . $cards['phase7-videos']['error']
);
my_ecard_gv_check(
	'videos card renders no videos section',
	'' === $videos_section
);
my_ecard_gv_check(
	'videos card renders no theme video markup',
	false === strpos( $videos_html, 'my-ecard-theme-reference__video' )
		&& false === strpos( $videos_html, 'video-list' )
		&& false === strpos( $videos_html, 'video-link' )
);
my_ecard_gv_check(
	'videos card renders no media section wrapper',
	false === strpos( $videos_html, 'my-ecard-theme-reference__section' )
);
my_ecard_gv_check(
	'videos card holds no in-card video anchors',
	0 === preg_match_all( '/<a\b[^>]*my-ecard-theme-reference__video-link[^>]*>/', $videos_html )
		&& 0 === preg_match_all( '/<a\b[^>]*youtu\.be[^>]*>/', $videos_html )
);

$video_items = substr_count( $videos_html, 'data-my-ecard-media-item' );

my_ecard_gv_check(
	'videos viewer lists two media items',
	2 === $video_items,
	(string) $video_items . ' items'
);
my_ecard_gv_check(
	'videos card embeds nothing',
	false === strpos( $videos_html, '<iframe' )
);
my_ecard_gv_check(
	'videos card renders no gallery section',
	false === strpos( $videos_html, 'id="my-ecard-gallery"' )
);

$videos_action = my_ecard_gv_tag( $videos_bar, 'videos' );
$videos_viewer = my_ecard_gv_viewer( $videos_html, 'my-ecard-videos-viewer' );

my_ecard_gv_check(
	'videos bar opens the videos viewer',
	'' !== $videos_action
		&& 1 === preg_match( '/^<button\b/', $videos_action )
		&& false !== strpos( $videos_action, 'data-my-ecard-videos-open' )
		&& false !== strpos( $videos_action, 'aria-controls="my-ecard-videos-viewer"' ),
	$videos_action
);
my_ecard_gv_check(
	'videos bar has no gallery action',
	false === strpos( $videos_bar, 'data-my-ecard-action="gallery"' )
);
my_ecard_gv_check(
	'videos bar keeps vCard, location, share and videos',
	array( 'location', 'share', 'vcard', 'videos' ) === my_ecard_gv_actions( $videos_bar ),
	my_ecard_gv_actions_label( $videos_bar )
);
my_ecard_gv_check(
	'videos card renders the videos viewer dialog',
	'' !== $videos_viewer && false !== strpos( $videos_viewer, 'data-my-ecard-media-viewer="videos"' )
);
my_ecard_gv_check(
	'videos viewer holds two media items',
	2 === substr_count( $videos_viewer, 'data-my-ecard-media-item' ),
	(string) substr_count( $videos_viewer, 'data-my-ecard-media-item' ) . ' items'
);
my_ecard_gv_check(
	'videos viewer carries the YouTube and Vimeo embed URLs',
	false !== strpos( $videos_viewer, 'data-embed="https://www.youtube-nocookie.com/embed/' )
		&& false !== strpos( $videos_viewer, 'data-embed="https://player.vimeo.com/video/' )
);
my_ecard_gv_check(
	'videos card renders no raw iframe',
	false === strpos( $videos_html, '<iframe' )
);
my_ecard_gv_check(
	'videos card renders no gallery viewer',
	false === strpos( $videos_html, 'id="my-ecard-gallery-viewer"' )
);

/*
 * Card with both sections.
 */
$both_html    = $cards['phase7-both']['body'];
$both_bar     = my_ecard_gv_bar( $both_html );
$both_gallery = my_ecard_gv_section( $both_html, 'my-ecard-gallery' );
$both_videos  = my_ecard_gv_section( $both_html, 'my-ecard-videos' );

my_ecard_gv_check(
	'both card responds 200',
	200 === $cards['phase7-both']['status'],
	(string) $cards['phase7-both']['status'] . ' ' . $cards['phase7-both']['error']
);
my_ecard_gv_check(
	'both card renders no in-card gallery section',
	'' === $both_gallery
);
my_ecard_gv_check(
	'both card renders no in-card videos section',
	'' === $both_videos
);
my_ecard_gv_check(
	'both bar lists every media action',
	array( 'gallery', 'location', 'share', 'vcard', 'videos' ) === my_ecard_gv_actions( $both_bar ),
	my_ecard_gv_actions_label( $both_bar )
);

$both_gallery_viewer = my_ecard_gv_viewer( $both_html, 'my-ecard-gallery-viewer' );
$both_videos_viewer  = my_ecard_gv_viewer( $both_html, 'my-ecard-videos-viewer' );

my_ecard_gv_check(
	'both card renders the gallery viewer with three items',
	'' !== $both_gallery_viewer
		&& false !== strpos( $both_gallery_viewer, 'data-my-ecard-media-viewer="gallery"' )
		&& 3 === substr_count( $both_gallery_viewer, 'data-my-ecard-media-item' ),
	(string) substr_count( $both_gallery_viewer, 'data-my-ecard-media-item' ) . ' items'
);
my_ecard_gv_check(
	'both card renders the videos viewer with two items',
	'' !== $both_videos_viewer
		&& false !== strpos( $both_videos_viewer, 'data-my-ecard-media-viewer="videos"' )
		&& 2 === substr_count( $both_videos_viewer, 'data-my-ecard-media-item' ),
	(string) substr_count( $both_videos_viewer, 'data-my-ecard-media-item' ) . ' items'
);

/*
 * Card without any section.
 */
$none_html = $cards['phase7-none']['body'];
$none_bar  = my_ecard_gv_bar( $none_html );

my_ecard_gv_check(
	'none card responds 200',
	200 === $cards['phase7-none']['status'],
	(string) $cards['phase7-none']['status'] . ' ' . $cards['phase7-none']['error']
);
my_ecard_gv_check(
	'none card renders no gallery section',
	false === strpos( $none_html, 'my-ecard-gallery' )
);
my_ecard_gv_check(
	'none card renders no videos section',
	false === strpos( $none_html, 'my-ecard-videos' )
);
my_ecard_gv_check(
	'none bar has no gallery or videos action',
	false === strpos( $none_bar, 'data-my-ecard-action="gallery"' )
		&& false === strpos( $none_bar, 'data-my-ecard-action="videos"' )
);
my_ecard_gv_check(
	'none bar keeps vCard, location and share',
	array( 'location', 'share', 'vcard' ) === my_ecard_gv_actions( $none_bar ),
	my_ecard_gv_actions_label( $none_bar )
);
my_ecard_gv_check(
	'none card renders no gallery viewer',
	false === strpos( $none_html, 'id="my-ecard-gallery-viewer"' )
);
my_ecard_gv_check(
	'none card renders no videos viewer',
	false === strpos( $none_html, 'id="my-ecard-videos-viewer"' )
);

/*
 * Invalid video URLs are dropped by CardData: no action and no viewer.
 */
$invalid_html = $cards['phase7-invalid-videos']['body'];
$invalid_bar  = my_ecard_gv_bar( $invalid_html );

my_ecard_gv_check(
	'invalid-videos card responds 200',
	200 === $cards['phase7-invalid-videos']['status'],
	(string) $cards['phase7-invalid-videos']['status'] . ' ' . $cards['phase7-invalid-videos']['error']
);
my_ecard_gv_check(
	'invalid video URLs drop the videos action',
	false === strpos( $invalid_bar, 'data-my-ecard-action="videos"' )
);
my_ecard_gv_check(
	'invalid video URLs drop the videos viewer',
	false === strpos( $invalid_html, 'id="my-ecard-videos-viewer"' )
);
my_ecard_gv_check(
	'invalid-videos card keeps vCard, location and share',
	array( 'location', 'share', 'vcard' ) === my_ecard_gv_actions( $invalid_bar ),
	my_ecard_gv_actions_label( $invalid_bar )
);

/*
 * Every action disabled: the bar disappears, the theme content does not.
 */
$off_html = $cards['phase7-actions-off']['body'];
$off_bar  = my_ecard_gv_bar( $off_html );

my_ecard_gv_check(
	'actions-off card responds 200',
	200 === $cards['phase7-actions-off']['status'],
	(string) $cards['phase7-actions-off']['status'] . ' ' . $cards['phase7-actions-off']['error']
);
my_ecard_gv_check(
	'actions-off card renders no action bar',
	'' === $off_bar && false === strpos( $off_html, 'data-my-ecard-action-bar' )
);
my_ecard_gv_check(
	'actions-off card renders no in-card gallery section',
	'' === my_ecard_gv_section( $off_html, 'my-ecard-gallery' )
);
my_ecard_gv_check(
	'actions-off card renders no in-card videos section',
	'' === my_ecard_gv_section( $off_html, 'my-ecard-videos' )
);
my_ecard_gv_check(
	'actions-off card renders no media viewer dialogs',
	false === strpos( $off_html, 'id="my-ecard-gallery-viewer"' )
		&& false === strpos( $off_html, 'id="my-ecard-videos-viewer"' )
);

/*
 * Theme fallbacks never own the addon shell, the bar or the media viewers.
 */
$no_theme_html = $cards['phase7-no-theme']['body'];

my_ecard_gv_check(
	'no-theme card responds 200',
	200 === $cards['phase7-no-theme']['status'],
	(string) $cards['phase7-no-theme']['status'] . ' ' . $cards['phase7-no-theme']['error']
);
my_ecard_gv_check(
	'no-theme card renders no addon shell',
	false === strpos( $no_theme_html, 'data-my-ecard-shell' )
);
my_ecard_gv_check(
	'no-theme card renders no action bar',
	false === strpos( $no_theme_html, 'data-my-ecard-action-bar' )
);
my_ecard_gv_check(
	'no-theme card falls back to the ECard template',
	false !== strpos( $no_theme_html, 'digital-card-content' )
);
my_ecard_gv_check(
	'no-theme fallback keeps the native footer and media hooks',
	false !== strpos( $no_theme_html, 'dc-footer' )
		&& false !== strpos( $no_theme_html, 'g-map' )
		&& false !== strpos( $no_theme_html, 'photo-gallery' )
		&& false !== strpos( $no_theme_html, 'video-gallery' )
);
my_ecard_gv_check(
	'no-theme card renders no media viewer dialogs',
	false === strpos( $no_theme_html, 'id="my-ecard-gallery-viewer"' )
		&& false === strpos( $no_theme_html, 'id="my-ecard-videos-viewer"' )
);

$missing_theme_html = $cards['phase7-missing-theme']['body'];

my_ecard_gv_check(
	'missing-theme card responds 200',
	200 === $cards['phase7-missing-theme']['status'],
	(string) $cards['phase7-missing-theme']['status'] . ' ' . $cards['phase7-missing-theme']['error']
);
my_ecard_gv_check(
	'missing-theme card renders no addon shell',
	false === strpos( $missing_theme_html, 'data-my-ecard-shell' )
);
my_ecard_gv_check(
	'missing-theme card renders no action bar',
	false === strpos( $missing_theme_html, 'data-my-ecard-action-bar' )
);
my_ecard_gv_check(
	'missing-theme card falls back to the ECard template',
	false !== strpos( $missing_theme_html, 'digital-card-content' )
);
my_ecard_gv_check(
	'missing-theme card renders no media viewer dialogs',
	false === strpos( $missing_theme_html, 'id="my-ecard-gallery-viewer"' )
		&& false === strpos( $missing_theme_html, 'id="my-ecard-videos-viewer"' )
);

/*
 * External fixture theme.
 */
$external_html = $cards['phase7-external']['body'];
$external_bar  = my_ecard_gv_bar( $external_html );

my_ecard_gv_check(
	'external card responds 200',
	200 === $cards['phase7-external']['status'],
	(string) $cards['phase7-external']['status'] . ' ' . $cards['phase7-external']['error']
);
my_ecard_gv_check(
	'external card renders the fixture template',
	false !== strpos( $external_html, 'data-my-ecard-template="render"' )
);
my_ecard_gv_check(
	'external card renders the addon shell',
	false !== strpos( $external_html, 'data-my-ecard-shell' )
);
my_ecard_gv_check(
	'external bar keeps vCard, location and share',
	array( 'location', 'share', 'vcard' ) === my_ecard_gv_actions( $external_bar ),
	my_ecard_gv_actions_label( $external_bar )
);
my_ecard_gv_check(
	'external card renders no section actions',
	false === strpos( $external_bar, 'data-my-ecard-action="gallery"' )
		&& false === strpos( $external_bar, 'data-my-ecard-action="videos"' )
);
my_ecard_gv_check(
	'external card renders no gallery or videos section',
	false === strpos( $external_html, 'my-ecard-gallery' )
		&& false === strpos( $external_html, 'my-ecard-videos' )
);

/*
 * External fixture theme with media: the theme renders no section anchors, but
 * the shell still owns the actions and the viewer dialogs.
 */
$external_media_html = $cards['phase7-external-media']['body'];
$external_media_bar  = my_ecard_gv_bar( $external_media_html );

my_ecard_gv_check(
	'external-media card responds 200',
	200 === $cards['phase7-external-media']['status'],
	(string) $cards['phase7-external-media']['status'] . ' ' . $cards['phase7-external-media']['error']
);
my_ecard_gv_check(
	'external-media card renders the fixture template',
	false !== strpos( $external_media_html, 'data-my-ecard-template="render"' )
);
my_ecard_gv_check(
	'external-media fixture renders no gallery or videos section',
	false === my_ecard_gv_has_section( $external_media_html, 'my-ecard-gallery' )
		&& false === my_ecard_gv_has_section( $external_media_html, 'my-ecard-videos' )
);
my_ecard_gv_check(
	'external-media bar keeps the gallery action',
	false !== strpos( $external_media_bar, 'data-my-ecard-action="gallery"' )
);
my_ecard_gv_check(
	'external-media bar keeps the videos action',
	false !== strpos( $external_media_bar, 'data-my-ecard-action="videos"' )
);
my_ecard_gv_check(
	'external-media card renders both viewer dialogs',
	false !== strpos( $external_media_html, 'id="my-ecard-gallery-viewer"' )
		&& false !== strpos( $external_media_html, 'id="my-ecard-videos-viewer"' )
);

/*
 * The both card uses the seeded media and video URLs.
 */
my_ecard_gv_check(
	'both card uses the three seeded gallery images',
	false !== strpos( $both_gallery_viewer, 'gallery-1' )
		&& false !== strpos( $both_gallery_viewer, 'gallery-2' )
		&& false !== strpos( $both_gallery_viewer, 'gallery-3' )
);
my_ecard_gv_check(
	'both card uses the seeded YouTube and Vimeo URLs',
	false !== strpos( $both_videos_viewer, 'youtube.com' ) && false !== strpos( $both_videos_viewer, 'vimeo.com' )
);

/*
 * Gallery and videos actions pair with their viewer dialogs, including the
 * external fixture theme that renders no section anchors.
 */
$unpaired = array();

foreach ( array( 'phase7-gallery', 'phase7-videos', 'phase7-both', 'phase7-none', 'phase7-actions-off', 'phase7-external-media' ) as $slug ) {
	$body = $cards[ $slug ]['body'];
	$bar  = my_ecard_gv_bar( $body );

	$gallery_action = false !== strpos( $bar, 'data-my-ecard-action="gallery"' );
	$videos_action  = false !== strpos( $bar, 'data-my-ecard-action="videos"' );
	$gallery_viewer = '' !== my_ecard_gv_viewer( $body, 'my-ecard-gallery-viewer' );
	$videos_viewer  = '' !== my_ecard_gv_viewer( $body, 'my-ecard-videos-viewer' );

	if ( $gallery_action !== $gallery_viewer || $videos_action !== $videos_viewer ) {
		$unpaired[] = $slug;
	}
}

my_ecard_gv_check(
	'gallery and videos actions pair with their viewer dialogs',
	array() === $unpaired,
	implode( ',', $unpaired )
);

/*
 * Every response must be free of PHP diagnostics.
 */
$noisy = array();

foreach ( $cards as $slug => $response ) {
	if ( preg_match( '/Fatal error|Warning:|Notice:/', $response['body'] ) ) {
		$noisy[] = $slug;
	}
}

my_ecard_gv_check(
	'no page leaks PHP diagnostics',
	array() === $noisy,
	implode( ',', $noisy )
);

echo 'summary: ' . $checks . ' checks, ' . $fails . " failed\n";
exit( $fails > 0 ? 1 : 0 );
