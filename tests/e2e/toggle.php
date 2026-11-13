<?php
/**
 * Activate or deactivate one e2e plugin.
 *
 * Run inside the e2e container:
 *   docker compose exec -T -u www-data wp php .../tests/e2e/toggle.php deactivate addon
 *
 * @package MyECardExtensions
 */

error_reporting( E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED );

require '/var/www/html/wp-load.php';

require_once ABSPATH . 'wp-admin/includes/plugin.php';

$targets = array(
	'addon' => 'wp-ecard-addons/wp-ecard-addons.php',
	'ecard' => 'ecard-digital-visiting-card/ecard-digital-visiting-card.php',
);

$action = isset( $argv[1] ) ? (string) $argv[1] : '';
$target = isset( $argv[2] ) ? (string) $argv[2] : '';

if ( ! isset( $targets[ $target ] ) ) {
	fwrite( STDERR, "unknown target: " . $target . "\n" );
	exit( 1 );
}

$plugin = $targets[ $target ];

if ( 'deactivate' === $action ) {
	deactivate_plugins( $plugin );

	/*
	 * A deactivated plugin's post types stay registered until the next
	 * request, and WordPress reuses the stored rewrite rules even when the
	 * CPT is gone. Drop both so the e2e site mirrors a clean install.
	 */
	if ( 'ecard' === $target ) {
		if ( post_type_exists( 'ecard_digital_card' ) ) {
			unregister_post_type( 'ecard_digital_card' );
		}

		update_option( 'rewrite_rules', array() ); // phpcs:ignore WordPress.WP.OptionFlags
	}
} elseif ( 'activate' === $action ) {
	$result = activate_plugin( $plugin );

	if ( is_wp_error( $result ) ) {
		fwrite( STDERR, 'activation error: ' . $result->get_error_message() . "\n" );
		exit( 1 );
	}
} else {
	fwrite( STDERR, "unknown action: " . $action . "\n" );
	exit( 1 );
}

flush_rewrite_rules( true );

$active = (array) get_option( 'active_plugins' );

echo $action . ' ' . $target . ': ' . ( in_array( $plugin, $active, true ) ? 'active' : 'inactive' ) . "\n";
