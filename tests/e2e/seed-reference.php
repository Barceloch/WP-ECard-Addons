<?php
/**
 * Seed the reference theme cards with real attachments.
 *
 * Run inside the e2e container after seed.php:
 *   docker compose exec -T -u www-data wp php .../tests/e2e/seed-reference.php
 *
 * @package MyECardExtensions
 */

error_reporting( E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED );

require '/var/www/html/wp-load.php';

require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/media.php';
require_once ABSPATH . 'wp-admin/includes/image.php';

$fixtures = MY_ECARD_PATH . 'tests/e2e/fixtures/';
$stored   = get_option( 'my_ecard_e2e_reference_media', array() );
$media    = array();

foreach ( array( 'cover' => 'cover.jpg', 'avatar' => 'avatar.jpg' ) as $key => $file ) {
	$existing = isset( $stored[ $key ] ) ? (int) $stored[ $key ] : 0;

	if ( $existing > 0 && 'attachment' === get_post_type( $existing ) ) {
		$media[ $key ] = $existing;
		continue;
	}

	if ( ! is_readable( $fixtures . $file ) ) {
		fwrite( STDERR, 'missing fixture: ' . $file . "\n" );
		exit( 1 );
	}

	$temporary = wp_tempnam( $file );

	if ( ! $temporary || ! copy( $fixtures . $file, $temporary ) ) {
		fwrite( STDERR, 'unable to stage fixture: ' . $file . "\n" );
		exit( 1 );
	}

	$attachment_id = media_handle_sideload(
		array(
			'name'     => $file,
			'tmp_name' => $temporary,
		),
		0,
		'Reference e2e ' . $key
	);

	if ( is_wp_error( $attachment_id ) ) {
		@unlink( $temporary );
		fwrite( STDERR, 'sideload failed: ' . $attachment_id->get_error_message() . "\n" );
		exit( 1 );
	}

	$media[ $key ] = (int) $attachment_id;
}

update_option( 'my_ecard_e2e_reference_media', $media, false );

$cards = array(
	'reference-full'      => array(
		'title'         => 'Alex Rivera',
		'designation'   => 'Product Designer & Speaker',
		'company'       => 'Studio Norte',
		'email'         => 'alex@example.com',
		'phone'         => '+34 600 111 222',
		'whatsapp'      => '+34 600 333 444',
		'location'      => 'https://maps.google.com/?q=Madrid',
		'cover'         => $media['cover'],
		'avatar'        => wp_get_attachment_url( $media['avatar'] ),
		'height'        => 260,
		'show_avatar'   => 1,
		'social'        => array(
			'facebook'  => 'https://facebook.com/alexrivera',
			'instagram' => 'https://instagram.com/alexrivera',
			'twitter'   => 'https://twitter.com/alexrivera',
			'youtube'   => 'https://youtube.com/@alexrivera',
			'linkedin'  => 'https://linkedin.com/in/alexrivera',
		),
		'gallery'       => array( $media['cover'] ),
		'videos'        => array(
			array(
				'title' => 'Introduction',
				'url'   => 'https://youtu.be/abc123',
			),
		),
	),
	'reference-no-avatar' => array(
		'title'         => 'Marta Sol',
		'designation'   => 'Founder',
		'company'       => '',
		'email'         => 'marta@example.com',
		'phone'         => '',
		'whatsapp'      => '',
		'location'      => '',
		'cover'         => 0,
		'avatar'        => '',
		'height'        => 200,
		'show_avatar'   => 0,
		'social'        => array(
			'linkedin' => 'https://linkedin.com/in/martasol',
		),
		'gallery'       => array(),
		'videos'        => array(),
	),
);

$result = array(
	'base'  => 'http://localhost:8099',
	'media' => array(
		'cover'  => wp_get_attachment_url( $media['cover'] ),
		'avatar' => wp_get_attachment_url( $media['avatar'] ),
	),
	'posts' => array(),
);

foreach ( $cards as $slug => $card ) {
	$existing = get_posts(
		array(
			'post_type'        => 'any',
			'post_status'      => 'any',
			'name'             => $slug,
			'numberposts'      => 1,
			'suppress_filters' => true,
		)
	);

	foreach ( $existing as $stale ) {
		wp_delete_post( $stale->ID, true );
	}

	$post_id = wp_insert_post(
		array(
			'post_type'    => 'ecard_digital_card',
			'post_title'   => $card['title'],
			'post_name'    => $slug,
			'post_status'  => 'publish',
			'post_content' => '<p>Reference theme fixture.</p>',
		),
		true
	);

	if ( is_wp_error( $post_id ) ) {
		fwrite( STDERR, 'seed error for ' . $slug . ': ' . $post_id->get_error_message() . "\n" );
		exit( 1 );
	}

	update_post_meta( $post_id, '_my_ecard_theme', 'reference' );
	update_post_meta( $post_id, '_my_ecard_header_height', (int) $card['height'] );

	if ( ! $card['show_avatar'] ) {
		update_post_meta( $post_id, '_my_ecard_show_avatar', 0 );
	}

	if ( $card['cover'] > 0 ) {
		update_post_meta( $post_id, '_my_ecard_cover_image', (int) $card['cover'] );
	}

	update_post_meta( $post_id, '_ecard_designation', $card['designation'] );
	update_post_meta( $post_id, '_ecard_email', $card['email'] );

	if ( '' !== $card['company'] ) {
		update_post_meta( $post_id, '_ecard_company_name', $card['company'] );
	}

	if ( '' !== $card['phone'] ) {
		update_post_meta( $post_id, '_ecard_phone', $card['phone'] );
	}

	if ( '' !== $card['whatsapp'] ) {
		update_post_meta( $post_id, '_ecard_whatsapp', $card['whatsapp'] );
	}

	if ( '' !== $card['location'] ) {
		update_post_meta( $post_id, '_ecard_location', $card['location'] );
	}

	if ( '' !== $card['avatar'] ) {
		update_post_meta( $post_id, '_ecard_person_photo', $card['avatar'] );
	}

	update_post_meta( $post_id, '_ecard_social_links', $card['social'] );

	if ( array() !== $card['gallery'] ) {
		update_post_meta( $post_id, '_ecard_gallery', $card['gallery'] );
	}

	if ( array() !== $card['videos'] ) {
		update_post_meta( $post_id, '_ecard_videos', $card['videos'] );
	}

	$result['posts'][ $slug ] = array(
		'id'  => $post_id,
		'url' => 'http://localhost:8099/ecard/' . $slug . '/',
	);
}

flush_rewrite_rules( true );

echo wp_json_encode( $result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) . "\n";
