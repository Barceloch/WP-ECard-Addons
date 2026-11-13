<?php
/**
 * Seed the disposable WordPress with cards covering every Phase 5 scenario.
 *
 * Run inside the e2e container after seed.php:
 *   docker compose exec -T -u www-data wp php .../tests/e2e/seed-phase5.php
 *
 * Prints a JSON document with the URLs the host-side checks request.
 *
 * @package MyECardExtensions
 */

error_reporting( E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED );

require '/var/www/html/wp-load.php';

$cards = array(
	'phase5-full'     => array(
		'title'       => 'Emilio Frías',
		'status'      => 'publish',
		'password'    => '',
		'designation' => 'Cantante, Compositor',
		'company'     => 'ACME; Records',
		'phone'       => '+34 600 111 222',
		'whatsapp'    => '+34 600 333 444',
		'email'       => 'emilio@example.com',
		'website'     => 'https://example.com/emilio',
		'address'     => "Calle Mayor 1, 2ºB\n28013 Madrid",
		'location'    => 'https://maps.google.com/?q=Madrid',
		'vcard'       => null,
		'qr'          => null,
	),
	'phase5-minimal'  => array(
		'title'       => 'Madonna',
		'status'      => 'publish',
		'password'    => '',
		'designation' => '',
		'company'     => '',
		'phone'       => '',
		'whatsapp'    => '',
		'email'       => 'madonna@example.com',
		'website'     => '',
		'address'     => '',
		'location'    => '',
		'vcard'       => null,
		'qr'          => null,
	),
	'phase5-no-vcard' => array(
		'title'       => 'No VCard',
		'status'      => 'publish',
		'password'    => '',
		'designation' => '',
		'company'     => '',
		'phone'       => '',
		'whatsapp'    => '',
		'email'       => 'novcard@example.com',
		'website'     => '',
		'address'     => '',
		'location'    => '',
		'vcard'       => 0,
		'qr'          => null,
	),
	'phase5-no-qr'    => array(
		'title'       => 'No QR',
		'status'      => 'publish',
		'password'    => '',
		'designation' => '',
		'company'     => '',
		'phone'       => '',
		'whatsapp'    => '',
		'email'       => 'noqr@example.com',
		'website'     => '',
		'address'     => '',
		'location'    => '',
		'vcard'       => null,
		'qr'          => 0,
	),
	'phase5-draft'    => array(
		'title'       => 'Draft Card',
		'status'      => 'draft',
		'password'    => '',
		'designation' => '',
		'company'     => '',
		'phone'       => '',
		'whatsapp'    => '',
		'email'       => 'draft@example.com',
		'website'     => '',
		'address'     => '',
		'location'    => '',
		'vcard'       => null,
		'qr'          => null,
	),
	'phase5-private'  => array(
		'title'       => 'Private Card',
		'status'      => 'private',
		'password'    => '',
		'designation' => '',
		'company'     => '',
		'phone'       => '',
		'whatsapp'    => '',
		'email'       => 'private@example.com',
		'website'     => '',
		'address'     => '',
		'location'    => '',
		'vcard'       => null,
		'qr'          => null,
	),
	'phase5-password' => array(
		'title'       => 'Password Card',
		'status'      => 'publish',
		'password'    => 'secret',
		'designation' => '',
		'company'     => '',
		'phone'       => '',
		'whatsapp'    => '',
		'email'       => 'password@example.com',
		'website'     => '',
		'address'     => '',
		'location'    => '',
		'vcard'       => null,
		'qr'          => null,
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
			'post_type'     => 'ecard_digital_card',
			'post_title'    => $card['title'],
			'post_name'     => $slug,
			'post_status'   => $card['status'],
			'post_password' => $card['password'],
			'post_content'  => '<p>Phase 5 fixture for ' . $slug . '</p>',
		),
		true
	);

	if ( is_wp_error( $post_id ) ) {
		fwrite( STDERR, 'seed error for ' . $slug . ': ' . $post_id->get_error_message() . "\n" );
		exit( 1 );
	}

	update_post_meta( $post_id, '_my_ecard_theme', 'reference' );

	if ( '' !== $card['designation'] ) {
		update_post_meta( $post_id, '_ecard_designation', $card['designation'] );
	}

	if ( '' !== $card['company'] ) {
		update_post_meta( $post_id, '_ecard_company_name', $card['company'] );
	}

	if ( '' !== $card['phone'] ) {
		update_post_meta( $post_id, '_ecard_phone', $card['phone'] );
	}

	if ( '' !== $card['whatsapp'] ) {
		update_post_meta( $post_id, '_ecard_whatsapp', $card['whatsapp'] );
	}

	if ( '' !== $card['email'] ) {
		update_post_meta( $post_id, '_ecard_email', $card['email'] );
	}

	if ( '' !== $card['website'] ) {
		update_post_meta( $post_id, '_ecard_website', $card['website'] );
	}

	if ( '' !== $card['address'] ) {
		update_post_meta( $post_id, '_ecard_address', $card['address'] );
	}

	if ( '' !== $card['location'] ) {
		update_post_meta( $post_id, '_ecard_location', $card['location'] );
	}

	if ( null !== $card['vcard'] ) {
		update_post_meta( $post_id, '_my_ecard_enable_vcard', (int) $card['vcard'] );
	}

	if ( null !== $card['qr'] ) {
		update_post_meta( $post_id, '_my_ecard_enable_qr', (int) $card['qr'] );
	}

	$result['posts'][ $slug ] = array(
		'id'  => $post_id,
		'url' => 'http://localhost:8099/ecard/' . $slug . '/',
	);
}

flush_rewrite_rules( true );

echo wp_json_encode( $result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) . "\n";
