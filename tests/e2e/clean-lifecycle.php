<?php
/**
 * Deactivation, reactivation and uninstall checks for the clean install.
 *
 * Run inside the clean e2e container after the host-side checks.
 *
 * @package MyECardExtensions
 */

error_reporting( E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED );

require '/var/www/html/wp-load.php';

require_once ABSPATH . 'wp-admin/includes/plugin.php';

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
function my_ecard_clean_check( string $label, bool $condition, string $detail = '' ): void {
	global $checks, $fails;

	$checks++;

	if ( $condition ) {
		echo 'PASS  ' . $label . "\n";
		return;
	}

	$fails++;
	echo 'FAIL  ' . $label . ( '' !== $detail ? ' [' . $detail . ']' : '' ) . "\n";
}

$post = get_page_by_path( 'clean-card', OBJECT, 'ecard_digital_card' );

my_ecard_clean_check( 'clean card exists', $post instanceof WP_Post );

if ( ! $post instanceof WP_Post ) {
	echo 'summary: ' . $checks . ' checks, ' . $fails . " failed\n";
	exit( 1 );
}

$card_id = $post->ID;
$theme   = (string) get_post_meta( $card_id, '_my_ecard_theme', true );

my_ecard_clean_check( 'theme persists after installation', 'reference' === $theme, $theme );

deactivate_plugins( array( $plugin ) );

my_ecard_clean_check( 'plugin deactivates', ! is_plugin_active( $plugin ) );

$result = activate_plugin( $plugin );

my_ecard_clean_check( 'plugin reactivates', null === $result );
my_ecard_clean_check( 'settings persist across reactivation', $theme === get_post_meta( $card_id, '_my_ecard_theme', true ) );

$uninstall = WP_PLUGIN_DIR . '/wp-ecard-addons/uninstall.php';

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	define( 'WP_UNINSTALL_PLUGIN', $plugin );
}

delete_option( 'my_ecard_delete_data' );

require $uninstall;

my_ecard_clean_check( 'default uninstall keeps settings', $theme === get_post_meta( $card_id, '_my_ecard_theme', true ) );
my_ecard_clean_check( 'default uninstall keeps ECard data', 'clean@example.com' === get_post_meta( $card_id, '_ecard_email', true ) );

update_option( 'my_ecard_delete_data', 1 );

require $uninstall;

my_ecard_clean_check( 'opt-in uninstall removes addon settings', '' === (string) get_post_meta( $card_id, '_my_ecard_theme', true ) );
my_ecard_clean_check( 'opt-in uninstall keeps ECard metadata', 'clean@example.com' === get_post_meta( $card_id, '_ecard_email', true ) );
my_ecard_clean_check( 'opt-in uninstall keeps the card published', 'publish' === get_post_status( $card_id ) );
my_ecard_clean_check( 'opt-in uninstall removes the version option', false === get_option( 'my_ecard_version' ) );

echo 'summary: ' . $checks . ' checks, ' . $fails . " failed\n";
exit( $fails > 0 ? 1 : 0 );
