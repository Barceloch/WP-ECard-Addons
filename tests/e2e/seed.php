<?php
/**
 * Seed the disposable WordPress with cards covering every Phase 3 scenario.
 *
 * Run inside the e2e container after install.php:
 *   docker compose exec -T -u www-data wp php .../tests/e2e/seed.php
 *
 * Prints a JSON document with the URLs the host-side checks request.
 *
 * @package MyECardExtensions
 */

error_reporting( E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED );

require '/var/www/html/wp-load.php';

$fixtures = array(
	'themed'        => array(
		'type'     => 'ecard_digital_card',
		'title'    => 'Themed Card',
		'status'   => 'publish',
		'theme'    => 'render',
		'email'    => 'jane@example.com',
		'password' => '',
	),
	'protected'     => array(
		'type'     => 'ecard_digital_card',
		'title'    => 'Protected Card',
		'status'   => 'publish',
		'theme'    => 'render',
		'email'    => 'secret@example.com',
		'password' => 'secret',
	),
	'draft-card'    => array(
		'type'     => 'ecard_digital_card',
		'title'    => 'Draft Card',
		'status'   => 'draft',
		'theme'    => 'render',
		'email'    => 'draft@example.com',
		'password' => '',
	),
	'private-card'  => array(
		'type'     => 'ecard_digital_card',
		'title'    => 'Private Card',
		'status'   => 'private',
		'theme'    => 'render',
		'email'    => 'private@example.com',
		'password' => '',
	),
	'unknown-theme' => array(
		'type'     => 'ecard_digital_card',
		'title'    => 'Unknown Theme Card',
		'status'   => 'publish',
		'theme'    => 'ghost',
		'email'    => 'ghost@example.com',
		'password' => '',
	),
	'plain-card'    => array(
		'type'     => 'ecard_digital_card',
		'title'    => 'Plain Card',
		'status'   => 'publish',
		'theme'    => '',
		'email'    => 'plain@example.com',
		'password' => '',
	),
	'plain-post'    => array(
		'type'     => 'post',
		'title'    => 'Plain Post',
		'status'   => 'publish',
		'theme'    => '',
		'email'    => '',
		'password' => '',
	),
);

$result = array(
	'base'  => 'http://localhost:8099',
	'posts' => array(),
);

foreach ( $fixtures as $slug => $fixture ) {
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
			'post_type'     => $fixture['type'],
			'post_title'    => $fixture['title'],
			'post_name'     => $slug,
			'post_status'   => $fixture['status'],
			'post_password' => $fixture['password'],
			'post_content'  => '<p>Fixture content for ' . $slug . '</p>',
		),
		true
	);

	if ( is_wp_error( $post_id ) ) {
		fwrite( STDERR, 'seed error for ' . $slug . ': ' . $post_id->get_error_message() . "\n" );
		exit( 1 );
	}

	if ( 'ecard_digital_card' === $fixture['type'] ) {
		update_post_meta( $post_id, '_ecard_designation', 'Architect' );
		update_post_meta( $post_id, '_ecard_company_name', 'ACME' . ucfirst( $slug ) );
		update_post_meta( $post_id, '_ecard_email', $fixture['email'] );
		update_post_meta( $post_id, '_ecard_phone', '+34 600 123 456' );
		update_post_meta( $post_id, '_ecard_website', 'https://example.com/' . $slug );

		if ( '' !== $fixture['theme'] ) {
			update_post_meta( $post_id, '_my_ecard_theme', $fixture['theme'] );
		}
	}

	$result['posts'][ $slug ] = array(
		'id'   => $post_id,
		'type' => $fixture['type'],
		'url'  => 'http://localhost:8099/?p=' . $post_id,
		'path' => ( 'post' === $fixture['type'] ? '/' . $slug . '/' : '/ecard/' . $slug . '/' ),
	);
}

flush_rewrite_rules( true );

echo wp_json_encode( $result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) . "\n";
