<?php
/**
 * Install WordPress from scratch and install the plugin from the release ZIP.
 *
 * Run inside the clean e2e container:
 *   docker compose -f tests/e2e/docker-compose.clean.yml -p my-ecard-clean \
 *     exec -T -u www-data wp php /var/www/html/clean-install.php
 *
 * @package MyECardExtensions
 */

if ( ! defined( 'WP_INSTALLING' ) ) {
	define( 'WP_INSTALLING', true );
}

$_SERVER['HTTP_HOST']      = 'localhost:8098';
$_SERVER['SERVER_NAME']    = 'localhost';
$_SERVER['SERVER_PORT']    = '8098';
$_SERVER['REQUEST_URI']    = '/';
$_SERVER['REQUEST_SCHEME'] = 'http';
$_SERVER['SCRIPT_NAME']    = '/index.php';

require '/var/www/html/wp-load.php';

require_once ABSPATH . 'wp-admin/includes/upgrade.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
require_once ABSPATH . 'wp-admin/includes/file.php';

if ( ! is_blog_installed() ) {
	wp_install( 'ECard Clean', 'admin', 'admin@example.test', true, '', 'Admin123!Strong' );
	echo "installed\n";
} else {
	echo "already installed\n";
}

update_option( 'siteurl', 'http://localhost:8098' );
update_option( 'home', 'http://localhost:8098' );
update_option( 'permalink_structure', '/%postname%/' );

$target = WP_PLUGIN_DIR . '/wp-ecard-addons';

if ( is_dir( $target ) ) {
	echo "plugin directory already exists\n";
} else {
	$zip = new ZipArchive();

	if ( true !== $zip->open( '/tmp/wp-ecard-addons.zip' ) ) {
		fwrite( STDERR, "cannot open the release ZIP\n" );
		exit( 1 );
	}

	if ( ! $zip->extractTo( WP_PLUGIN_DIR ) ) {
		fwrite( STDERR, "cannot extract the release ZIP\n" );
		exit( 1 );
	}

	$zip->close();
	echo "extracted\n";
}

if ( ! is_file( $target . '/vendor/autoload.php' ) ) {
	fwrite( STDERR, "vendor/autoload.php is missing from the release ZIP\n" );
	exit( 1 );
}

$result = activate_plugins(
	array(
		'ecard-digital-visiting-card/ecard-digital-visiting-card.php',
		'wp-ecard-addons/wp-ecard-addons.php',
	)
);

if ( is_wp_error( $result ) ) {
	fwrite( STDERR, 'activation error: ' . $result->get_error_message() . "\n" );
	exit( 1 );
}

$existing = get_page_by_path( 'clean-card', OBJECT, 'ecard_digital_card' );

if ( $existing instanceof WP_Post ) {
	wp_delete_post( $existing->ID, true );
}

$card_id = wp_insert_post(
	array(
		'post_type'   => 'ecard_digital_card',
		'post_title'  => 'Clean Install',
		'post_name'   => 'clean-card',
		'post_status' => 'publish',
		'post_content' => '',
	)
);

if ( is_wp_error( $card_id ) || 0 === (int) $card_id ) {
	fwrite( STDERR, "cannot create the card\n" );
	exit( 1 );
}

/**
 * Create a gallery attachment without uploading a file.
 *
 * @param int    $parent Card ID.
 * @param string $file   Relative upload path.
 * @param string $title  Attachment title.
 * @return int Attachment ID.
 */
function my_ecard_clean_attachment( int $parent, string $file, string $title ): int {
	$id = wp_insert_post(
		array(
			'post_type'      => 'attachment',
			'post_mime_type' => 'image/jpeg',
			'post_title'     => $title,
			'post_status'    => 'inherit',
			'post_parent'    => $parent,
		)
	);

	update_post_meta( $id, '_wp_attached_file', $file );
	update_post_meta(
		$id,
		'_wp_attachment_metadata',
		array(
			'width'  => 1200,
			'height' => 800,
			'file'   => $file,
		)
	);

	return (int) $id;
}

$gallery = array(
	my_ecard_clean_attachment( $card_id, '2026/01/clean-1.jpg', 'Clean 1' ),
	my_ecard_clean_attachment( $card_id, '2026/01/clean-2.jpg', 'Clean 2' ),
);

update_post_meta( $card_id, '_ecard_designation', 'Architect' );
update_post_meta( $card_id, '_ecard_company_name', 'Clean Studio' );
update_post_meta( $card_id, '_ecard_email', 'clean@example.com' );
update_post_meta( $card_id, '_ecard_phone', '+34 600 123 456' );
update_post_meta( $card_id, '_ecard_location', 'https://maps.google.com/?q=Madrid' );
update_post_meta( $card_id, '_ecard_gallery', $gallery );
update_post_meta(
	$card_id,
	'_ecard_videos',
	array(
		array(
			'title' => 'Clean video',
			'url'   => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
		),
	)
);
update_post_meta( $card_id, '_my_ecard_theme', 'reference' );
update_post_meta( $card_id, '_my_ecard_enable_qr', 1 );
update_post_meta( $card_id, '_my_ecard_enable_gallery', 1 );
update_post_meta( $card_id, '_my_ecard_enable_videos', 1 );

flush_rewrite_rules( true );

echo 'card: http://localhost:8098/ecard/clean-card/' . "\n";
echo 'active: ' . implode( ',', (array) get_option( 'active_plugins' ) ) . "\n";
echo "clean install ok\n";
