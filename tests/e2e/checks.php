<?php
/**
 * Real-WordPress checks for the addon base.
 *
 * Run inside the e2e container after install.php:
 *   docker compose exec -T wp php .../tests/e2e/checks.php with-ecard
 *   docker compose exec -T wp php .../tests/e2e/checks.php without-ecard
 *
 * @package MyECardExtensions
 */

error_reporting( E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED );

require '/var/www/html/wp-load.php';

require_once ABSPATH . 'wp-admin/includes/plugin.php';

use MyECardExtensions\CardData;
use MyECardExtensions\CardSettings;
use MyECardExtensions\Plugin;
use MyECardExtensions\QRCodeGenerator;
use MyECardExtensions\ThemeRegistry;

$mode   = isset( $argv[1] ) ? (string) $argv[1] : 'with-ecard';
$checks = 0;
$fails  = 0;

/**
 * Record one check.
 *
 * @param string $label     Description.
 * @param bool   $condition Result.
 * @return void
 */
function my_ecard_check( string $label, bool $condition ): void {
	global $checks, $fails;

	$checks++;

	if ( $condition ) {
		echo 'PASS  ' . $label . "\n";
		return;
	}

	$fails++;
	echo 'FAIL  ' . $label . "\n";
}

$registry = new ThemeRegistry( array( MY_ECARD_PATH . 'themes' ), MY_ECARD_VERSION );

echo 'mode: ' . $mode . "\n";

if ( 'without-ecard' === $mode ) {
	my_ecard_check( 'ECard constant is not defined', ! defined( 'ECARD_VERSION' ) );
	my_ecard_check( 'ECard post type is not registered', ! post_type_exists( 'ecard_digital_card' ) );
	my_ecard_check( 'addon bootstrap still loaded', class_exists( Plugin::class ) );
	my_ecard_check( 'integration hook did not fire', 0 === did_action( 'my_ecard_loaded' ) );
	my_ecard_check( 'dependency is not satisfied', ! ( new \MyECardExtensions\Dependencies() )->is_satisfied() );

	$notice = false;

	if ( isset( $GLOBALS['wp_filter']['admin_notices'] ) ) {
		foreach ( $GLOBALS['wp_filter']['admin_notices']->callbacks as $callbacks ) {
			foreach ( $callbacks as $callback ) {
				$function = $callback['function'];

				if ( is_array( $function ) && isset( $function[0] ) && $function[0] instanceof \MyECardExtensions\Dependencies ) {
					$notice = true;
				}
			}
		}
	}

	my_ecard_check( 'dependency admin notice is registered', $notice );

	echo 'summary: ' . $checks . ' checks, ' . $fails . " failed\n";
	exit( $fails > 0 ? 1 : 0 );
}

my_ecard_check( 'ECard is loaded', defined( 'ECARD_VERSION' ) );
my_ecard_check( 'ECard version is 1.6.0', '1.6.0' === ECARD_VERSION );
my_ecard_check( 'ECard post type is registered', post_type_exists( 'ecard_digital_card' ) );
my_ecard_check( 'integration hook fired once', 1 === did_action( 'my_ecard_loaded' ) );
my_ecard_check( 'addon settings meta box is registered', false !== has_action( 'add_meta_boxes' ) );

$post_id = wp_insert_post(
	array(
		'post_type'    => 'ecard_digital_card',
		'post_title'   => 'Jane Doe',
		'post_status'  => 'publish',
		'post_content' => '<p>Welcome body</p>',
	)
);

my_ecard_check( 'card was created', $post_id > 0 && 'ecard_digital_card' === get_post_type( $post_id ) );

update_post_meta( $post_id, '_ecard_designation', 'Architect' );
update_post_meta( $post_id, '_ecard_company_name', 'ACME' );
update_post_meta( $post_id, '_ecard_email', 'jane@example.com' );
update_post_meta( $post_id, '_ecard_phone', '+34 600 123 456' );
update_post_meta( $post_id, '_ecard_whatsapp', '+34 600 123 456' );
update_post_meta( $post_id, '_ecard_website', 'https://example.com' );
update_post_meta( $post_id, '_ecard_location', 'javascript:alert(1)' );
update_post_meta(
	$post_id,
	'_ecard_map_iframe',
	'<iframe src="https://www.google.com/maps/embed?pb=abc"></iframe>'
);
update_post_meta(
	$post_id,
	'_ecard_social_links',
	array(
		'facebook'  => 'https://facebook.com/jane',
		'instagram' => 'javascript:alert(1)',
	)
);
update_post_meta(
	$post_id,
	'_ecard_videos',
	array(
		array(
			'title' => '<b>Intro</b>',
			'url'   => 'https://youtu.be/abc',
		),
		array(
			'title' => 'Broken',
			'url'   => 'javascript:alert(1)',
		),
	)
);
update_post_meta(
	$post_id,
	'_ecard_services',
	array(
		array(
			'name'        => ' Consulting ',
			'description' => '<p>Details</p>',
		),
		array( 'name' => '' ),
	)
);
update_post_meta( $post_id, '_ecard_gallery', array( 999999, 'abc' ) );

$data = CardData::from_post( $post_id );

my_ecard_check( 'CardData returned a card', null !== $data );
my_ecard_check( 'designation normalized', 'Architect' === $data->designation );
my_ecard_check( 'email validated', 'jane@example.com' === $data->email );
my_ecard_check( 'phone normalized', '+34600123456' === $data->phone );
my_ecard_check( 'tel URL built', 'tel:+34600123456' === $data->phone_url );
my_ecard_check( 'WhatsApp URL built', 'https://wa.me/34600123456' === $data->whatsapp_url );
my_ecard_check( 'website kept', 'https://example.com' === $data->website );
my_ecard_check( 'javascript location rejected', '' === $data->location_url );
my_ecard_check( 'map embed extracted', 'https://www.google.com/maps/embed?pb=abc' === $data->map_embed_url );
my_ecard_check( 'serialized social meta decoded', 1 === count( $data->social ) );
my_ecard_check( 'serialized videos decoded', 1 === count( $data->videos ) );
my_ecard_check( 'video provider detected', 'youtube' === $data->videos[0]['provider'] );
my_ecard_check( 'video title stripped', 'Intro' === $data->videos[0]['title'] );
my_ecard_check( 'serialized services decoded', 1 === count( $data->services ) );
my_ecard_check( 'service name trimmed', 'Consulting' === $data->services[0]['name'] );
my_ecard_check( 'missing gallery attachments skipped', 0 === count( $data->gallery ) );
my_ecard_check( 'welcome content kept', '<p>Welcome body</p>' === $data->welcome_html );
my_ecard_check( 'card is publicly viewable', true === $data->is_publicly_viewable );

wp_set_current_user( 1 );

$_POST[ CardSettings::NONCE_NAME ] = wp_create_nonce( CardSettings::NONCE_ACTION );
$_POST[ CardSettings::FIELD ]      = array(
	'theme'           => 'valid',
	'header_height'   => '300',
	'show_avatar'     => '1',
	'show_bar'        => '1',
	'qr_label'        => '1',
	'enable_vcard'    => '1',
	'enable_share'    => '1',
	'enable_location' => '1',
	'enable_gallery'  => '1',
	'enable_videos'   => '1',
	'qr_dark'         => '#123456',
);

$settings = new CardSettings( $registry );
$settings->save( $post_id, get_post( $post_id ) );

my_ecard_check( 'non-default setting persisted', '300' === get_post_meta( $post_id, '_my_ecard_header_height', true ) );
my_ecard_check( 'default setting not persisted', ! metadata_exists( 'post', $post_id, '_my_ecard_show_avatar' ) );
my_ecard_check( 'unregistered theme rejected', ! metadata_exists( 'post', $post_id, '_my_ecard_theme' ) );
my_ecard_check( 'valid color persisted', '#123456' === get_post_meta( $post_id, '_my_ecard_qr_dark', true ) );

$stored = CardSettings::read( $post_id );

my_ecard_check( 'stored height read as int', 300 === $stored['header_height'] );
my_ecard_check( 'stored color read back', '#123456' === $stored['qr_dark'] );
my_ecard_check( 'defaults restored on read', 220 === $stored['qr_size'] );

update_post_meta( $post_id, '_my_ecard_theme', 'reference' );

ob_start();
$settings->render( get_post( $post_id ) );
$html = (string) ob_get_clean();

my_ecard_check( 'metabox renders the theme field', false !== strpos( $html, 'name="my_ecard[theme]"' ) );
my_ecard_check( 'metabox renders the nonce', false !== strpos( $html, 'my_ecard_settings_nonce' ) );
my_ecard_check( 'metabox renders the QR panel', false !== strpos( $html, 'data-my-ecard-qr-panel' ) );
my_ecard_check( 'metabox renders the cover picker', false !== strpos( $html, 'data-my-ecard-cover-choose' ) );
my_ecard_check( 'metabox lists registered themes', false !== strpos( $html, 'value="reference"' ) );
my_ecard_check( 'metabox shows the stored value', false !== strpos( $html, 'value="300"' ) );
my_ecard_check( 'metabox renders the theme selector', false !== strpos( $html, 'my-ecard-theme-selector' ) );
my_ecard_check( 'metabox renders the theme cards', false !== strpos( $html, 'my-ecard-theme-card__name' ) );
my_ecard_check( 'metabox marks the selected theme', 1 === preg_match( '/value="reference"[^>]*checked/', $html ) );
my_ecard_check( 'metabox renders the theme preview', false !== strpos( $html, 'themes/reference/preview.png' ) );
my_ecard_check( 'metabox renders the default theme option', false !== strpos( $html, 'value=""' ) );
$qr_notice = false !== strpos( $html, 'QR rendering is unavailable' );

my_ecard_check(
	'metabox QR notice matches renderer availability',
	QRCodeGenerator::is_available() ? false === $qr_notice : true === $qr_notice
);
my_ecard_check( 'metabox describes the gallery requirement', false !== strpos( $html, 'my-ecard-gallery section' ) );
my_ecard_check( 'metabox describes the videos requirement', false !== strpos( $html, 'my-ecard-videos section' ) );

/*
 * A stored theme that disappeared must warn and fall back to the default.
 */
update_post_meta( $post_id, '_my_ecard_theme', 'ghost' );
ob_start();
$settings->render( get_post( $post_id ) );
$html_ghost = (string) ob_get_clean();
my_ecard_check( 'metabox warns about a missing stored theme', false !== strpos( $html_ghost, 'my-ecard-theme-warning' ) );
my_ecard_check( 'metabox falls back to the default radio', 1 === preg_match( '/value=""[^>]*checked/', $html_ghost ) );
delete_post_meta( $post_id, '_my_ecard_theme' );

$log = '/var/www/html/wp-content/debug.log';

if ( is_readable( $log ) ) {
	$entries = (array) file( $log );
	$ours    = 0;

	foreach ( $entries as $entry ) {
		if ( false !== strpos( $entry, 'wp-ecard-addons' ) && preg_match( '/PHP (Fatal|Warning|Notice|Parse)/', $entry ) ) {
			$ours++;
			echo 'LOG   ' . trim( $entry ) . "\n";
		}
	}

	my_ecard_check( 'no PHP diagnostics from the addon', 0 === $ours );
} else {
	echo "LOG   debug.log not present\n";
}

echo 'summary: ' . $checks . ' checks, ' . $fails . " failed\n";
exit( $fails > 0 ? 1 : 0 );
