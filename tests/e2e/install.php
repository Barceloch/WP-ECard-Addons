<?php
/**
 * Install a disposable WordPress and activate both plugins.
 *
 * Run inside the e2e container:
 *   docker compose exec -T wp php /var/www/html/wp-content/plugins/wp-ecard-addons/tests/e2e/install.php
 *
 * @package MyECardExtensions
 */

if ( ! defined( 'WP_INSTALLING' ) ) {
	define( 'WP_INSTALLING', true );
}

/*
 * wp_guess_url() builds siteurl from the request; without these values a CLI
 * install stores an empty siteurl and WordPress treats the site as broken.
 */
$_SERVER['HTTP_HOST']       = 'localhost:8099';
$_SERVER['SERVER_NAME']     = 'localhost';
$_SERVER['SERVER_PORT']     = '8099';
$_SERVER['REQUEST_URI']     = '/';
$_SERVER['REQUEST_SCHEME']  = 'http';
$_SERVER['SCRIPT_NAME']     = '/index.php';

require '/var/www/html/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/upgrade.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';

if ( ! is_blog_installed() ) {
	wp_install( 'ECard E2E', 'admin', 'admin@example.test', true, '', 'Admin123!Strong' );
	echo "installed\n";
} else {
	echo "already installed\n";
}

update_option( 'siteurl', 'http://localhost:8099' );
update_option( 'home', 'http://localhost:8099' );

/*
 * Phase 3 renders singular cards, so the e2e site needs pretty permalinks.
 */
update_option( 'permalink_structure', '/%postname%/' );
flush_rewrite_rules( true );

$plugins = array(
	'ecard-digital-visiting-card/ecard-digital-visiting-card.php',
	'wp-ecard-addons/wp-ecard-addons.php',
);

$result = activate_plugins( $plugins );

if ( is_wp_error( $result ) ) {
	fwrite( STDERR, 'activation error: ' . $result->get_error_message() . "\n" );
	exit( 1 );
}

echo 'active: ' . implode( ',', (array) get_option( 'active_plugins' ) ) . "\n";
echo "install ok\n";
