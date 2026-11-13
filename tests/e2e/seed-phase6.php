<?php
/**
 * Seed the disposable WordPress with cards covering every Phase 6 scenario.
 *
 * Run inside the e2e container after seed-reference.php:
 *   docker compose exec -T -u www-data wp php .../tests/e2e/seed-phase6.php
 *
 * Prints a JSON document with the URLs the host-side checks request.
 *
 * @package MyECardExtensions
 */

error_reporting( E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED );

require '/var/www/html/wp-load.php';

$stored = get_option( 'my_ecard_e2e_reference_media', array() );
$cover  = isset( $stored['cover'] ) ? (int) $stored['cover'] : 0;

if ( $cover > 0 && 'attachment' !== get_post_type( $cover ) ) {
	$cover = 0;
}

$videos = array(
	array(
		'title' => 'Intro',
		'url'   => 'https://youtu.be/abc123',
	),
);

$cards = array(
	'phase6-full'            => array(
		'title'       => 'Phase 6 Full',
		'theme'       => 'reference',
		'designation' => 'Consultant',
		'email'       => 'full@example.com',
		'phone'       => '+34 600 111 222',
		'location'    => 'https://maps.google.com/?q=Madrid',
		'gallery'     => true,
		'videos'      => $videos,
		'settings'    => array(),
	),
	'phase6-no-location'     => array(
		'title'       => 'No Location',
		'theme'       => 'reference',
		'designation' => '',
		'email'       => 'nolocation@example.com',
		'phone'       => '',
		'location'    => '',
		'gallery'     => false,
		'videos'      => array(),
		'settings'    => array(),
	),
	'phase6-no-vcard'        => array(
		'title'       => 'No VCard',
		'theme'       => 'reference',
		'designation' => '',
		'email'       => 'novcard@example.com',
		'phone'       => '',
		'location'    => '',
		'gallery'     => false,
		'videos'      => array(),
		'settings'    => array(
			'enable_vcard' => 0,
		),
	),
	'phase6-no-share'        => array(
		'title'       => 'No Share',
		'theme'       => 'reference',
		'designation' => '',
		'email'       => 'noshare@example.com',
		'phone'       => '',
		'location'    => '',
		'gallery'     => false,
		'videos'      => array(),
		'settings'    => array(
			'enable_share' => 0,
		),
	),
	'phase6-no-bar'          => array(
		'title'       => 'No Bar',
		'theme'       => 'reference',
		'designation' => '',
		'email'       => 'nobar@example.com',
		'phone'       => '',
		'location'    => '',
		'gallery'     => false,
		'videos'      => array(),
		'settings'    => array(
			'show_bar' => 0,
		),
	),
	'phase6-labels-hide'     => array(
		'title'       => 'Labels Hide',
		'theme'       => 'reference',
		'designation' => '',
		'email'       => 'labelshide@example.com',
		'phone'       => '',
		'location'    => '',
		'gallery'     => false,
		'videos'      => array(),
		'settings'    => array(
			'bar_labels' => 'hide',
		),
	),
	'phase6-labels-mobile'   => array(
		'title'       => 'Labels Mobile',
		'theme'       => 'reference',
		'designation' => '',
		'email'       => 'labelsmobile@example.com',
		'phone'       => '',
		'location'    => '',
		'gallery'     => false,
		'videos'      => array(),
		'settings'    => array(
			'bar_labels' => 'hide_mobile',
		),
	),
	'phase6-sticky'          => array(
		'title'       => 'Sticky Bar',
		'theme'       => 'reference',
		'designation' => '',
		'email'       => 'sticky@example.com',
		'phone'       => '',
		'location'    => '',
		'gallery'     => false,
		'videos'      => array(),
		'settings'    => array(
			'bar_position' => 'sticky',
		),
	),
	'phase6-static'          => array(
		'title'       => 'Static Bar',
		'theme'       => 'reference',
		'designation' => '',
		'email'       => 'static@example.com',
		'phone'       => '',
		'location'    => '',
		'gallery'     => false,
		'videos'      => array(),
		'settings'    => array(
			'bar_position' => 'static',
		),
	),
	'phase6-all-sections'    => array(
		'title'       => 'All Sections',
		'theme'       => 'action-bar',
		'designation' => '',
		'email'       => 'sections@example.com',
		'phone'       => '',
		'location'    => 'https://maps.google.com/?q=Madrid',
		'gallery'     => true,
		'videos'      => $videos,
		'settings'    => array(),
	),
	'phase6-no-gallery'      => array(
		'title'       => 'No Gallery',
		'theme'       => 'action-bar',
		'designation' => '',
		'email'       => 'nogallery@example.com',
		'phone'       => '',
		'location'    => '',
		'gallery'     => false,
		'videos'      => $videos,
		'settings'    => array(),
	),
	'phase6-no-videos'       => array(
		'title'       => 'No Videos',
		'theme'       => 'action-bar',
		'designation' => '',
		'email'       => 'novideos@example.com',
		'phone'       => '',
		'location'    => '',
		'gallery'     => true,
		'videos'      => array(),
		'settings'    => array(),
	),
	'phase6-disabled-gallery'  => array(
		'title'       => 'Disabled Gallery',
		'theme'       => 'action-bar',
		'designation' => '',
		'email'       => 'disabledgallery@example.com',
		'phone'       => '',
		'location'    => '',
		'gallery'     => true,
		'videos'      => array(),
		'settings'    => array(
			'enable_gallery' => 0,
		),
	),
	'phase6-no-actions'      => array(
		'title'       => 'No Actions',
		'theme'       => 'reference',
		'designation' => '',
		'email'       => 'noactions@example.com',
		'phone'       => '',
		'location'    => '',
		'gallery'     => false,
		'videos'      => array(),
		'settings'    => array(
			'enable_vcard' => 0,
			'enable_share' => 0,
			'enable_qr'    => 0,
		),
	),
);

$result = array(
	'base'  => 'http://localhost:8099',
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
			'post_content' => '<p>Phase 6 fixture for ' . $slug . '</p>',
		),
		true
	);

	if ( is_wp_error( $post_id ) ) {
		fwrite( STDERR, 'seed error for ' . $slug . ': ' . $post_id->get_error_message() . "\n" );
		exit( 1 );
	}

	update_post_meta( $post_id, '_my_ecard_theme', $card['theme'] );

	if ( '' !== $card['designation'] ) {
		update_post_meta( $post_id, '_ecard_designation', $card['designation'] );
	}

	if ( '' !== $card['email'] ) {
		update_post_meta( $post_id, '_ecard_email', $card['email'] );
	}

	if ( '' !== $card['phone'] ) {
		update_post_meta( $post_id, '_ecard_phone', $card['phone'] );
	}

	if ( '' !== $card['location'] ) {
		update_post_meta( $post_id, '_ecard_location', $card['location'] );
	}

	if ( $card['gallery'] && $cover > 0 ) {
		update_post_meta( $post_id, '_ecard_gallery', array( $cover ) );
	}

	if ( array() !== $card['videos'] ) {
		update_post_meta( $post_id, '_ecard_videos', $card['videos'] );
	}

	foreach ( $card['settings'] as $key => $value ) {
		update_post_meta( $post_id, '_my_ecard_' . $key, $value );
	}

	$result['posts'][ $slug ] = array(
		'id'  => $post_id,
		'url' => 'http://localhost:8099/ecard/' . $slug . '/',
	);
}

flush_rewrite_rules( true );

echo wp_json_encode( $result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) . "\n";
