<?php
/**
 * Activation, update and uninstall lifecycle checks.
 *
 * Run inside the e2e container after the functional checks:
 *   docker compose exec -T wp php .../tests/e2e/lifecycle-checks.php
 *
 * This script is destructive: it removes addon data at the end. Run it last.
 *
 * @package MyECardExtensions
 */

error_reporting( E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED );

require '/var/www/html/wp-load.php';

require_once ABSPATH . 'wp-admin/includes/plugin.php';

use MyECardExtensions\Plugin;

$checks = 0;
$fails  = 0;
$plugin = 'wp-ecard-addons/wp-ecard-addons.php';

/**
 * Record one check.
 *
 * @param string $label     Description.
 * @param bool   $condition Result.
 * @param string $detail    Failure detail.
 * @return void
 */
function my_ecard_lc_check( string $label, bool $condition, string $detail = '' ): void {
	global $checks, $fails;

	$checks++;

	if ( $condition ) {
		echo 'PASS  ' . $label . "\n";
		return;
	}

	$fails++;
	echo 'FAIL  ' . $label . ( '' !== $detail ? ' [' . $detail . ']' : '' ) . "\n";
}

$post = get_page_by_path( 'phase5-full', OBJECT, 'ecard_digital_card' );

my_ecard_lc_check( 'card exists before the lifecycle test', $post instanceof WP_Post );

if ( ! $post instanceof WP_Post ) {
	echo 'summary: ' . $checks . ' checks, ' . $fails . " failed\n";
	exit( 1 );
}

$card_id = $post->ID;
$theme   = (string) get_post_meta( $card_id, '_my_ecard_theme', true );

my_ecard_lc_check( 'addon settings are stored before reactivation', '' !== $theme, $theme );
my_ecard_lc_check( 'version option is present', (bool) get_option( 'my_ecard_version' ) );

deactivate_plugins( array( $plugin ) );

my_ecard_lc_check( 'plugin deactivates', ! is_plugin_active( $plugin ) );

$result = activate_plugin( $plugin );

my_ecard_lc_check( 'plugin reactivates without error', null === $result );

Plugin::activate();

my_ecard_lc_check( 'reactivation keeps card settings', $theme === get_post_meta( $card_id, '_my_ecard_theme', true ) );
my_ecard_lc_check( 'reactivation restores the version option', (bool) get_option( 'my_ecard_version' ) );

$qr_key = 'my_ecard_qr_lifecycle_fixture';

set_transient( $qr_key, 'PNG', 300 );
update_option( 'my_ecard_delete_data', 0 );

delete_option( 'my_ecard_delete_data' );

$uninstall = WP_PLUGIN_DIR . '/wp-ecard-addons/uninstall.php';

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	define( 'WP_UNINSTALL_PLUGIN', $plugin );
}

require $uninstall;

my_ecard_lc_check( 'default uninstall keeps card settings', $theme === get_post_meta( $card_id, '_my_ecard_theme', true ) );
my_ecard_lc_check( 'default uninstall keeps the version option', (bool) get_option( 'my_ecard_version' ) );
my_ecard_lc_check( 'default uninstall keeps QR transients', 'PNG' === get_transient( $qr_key ) );

update_option( 'my_ecard_delete_data', 1 );

require $uninstall;

$ecard_email = (string) get_post_meta( $card_id, '_ecard_email', true );

my_ecard_lc_check( 'opt-in uninstall removes addon settings', '' === (string) get_post_meta( $card_id, '_my_ecard_theme', true ) );
my_ecard_lc_check( 'opt-in uninstall keeps ECard metadata', 'emilio@example.com' === $ecard_email, $ecard_email );
my_ecard_lc_check( 'opt-in uninstall removes the version option', false === get_option( 'my_ecard_version' ) );
my_ecard_lc_check( 'opt-in uninstall removes the opt-in option', false === get_option( 'my_ecard_delete_data' ) );
my_ecard_lc_check( 'opt-in uninstall removes QR transients', false === get_transient( $qr_key ) );

echo 'summary: ' . $checks . ' checks, ' . $fails . " failed\n";
exit( $fails > 0 ? 1 : 0 );
