<?php
/**
 * Plugin Name:       WP ECard Addons
 * Description:       Themes, QR codes, vCard downloads and an action bar for ECard - Digital Visiting Card.
 * Version:           0.1.0
 * Requires at least: 6.8
 * Requires PHP:      7.4
 * Author:            Barceloch
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wp-ecard-addons
 * Domain Path:       /languages
 *
 * @package MyECardExtensions
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( defined( 'MY_ECARD_VERSION' ) ) {
	return;
}

define( 'MY_ECARD_VERSION', '0.1.0' );
define( 'MY_ECARD_FILE', __FILE__ );
define( 'MY_ECARD_PATH', plugin_dir_path( __FILE__ ) );
define( 'MY_ECARD_URL', plugin_dir_url( __FILE__ ) );

/*
 * Optional Composer dependencies (the QR renderer). Loading is guarded so a
 * plugin directory without vendor/ keeps working with QR disabled instead of
 * raising a fatal error.
 */
$my_ecard_autoload = MY_ECARD_PATH . 'vendor/autoload.php';

if ( is_readable( $my_ecard_autoload ) ) {
	require_once $my_ecard_autoload;
}

unset( $my_ecard_autoload );

require_once MY_ECARD_PATH . 'includes/helpers.php';
require_once MY_ECARD_PATH . 'includes/class-dependencies.php';
require_once MY_ECARD_PATH . 'includes/class-theme-registry.php';
require_once MY_ECARD_PATH . 'includes/class-card-settings.php';
require_once MY_ECARD_PATH . 'includes/class-card-data.php';
require_once MY_ECARD_PATH . 'includes/class-assets.php';
require_once MY_ECARD_PATH . 'includes/class-template-loader.php';
require_once MY_ECARD_PATH . 'includes/class-vcard-generator.php';
require_once MY_ECARD_PATH . 'includes/class-qr-code-generator.php';
require_once MY_ECARD_PATH . 'includes/class-endpoint-response.php';
require_once MY_ECARD_PATH . 'includes/class-frontend-endpoints.php';
require_once MY_ECARD_PATH . 'includes/class-action-bar.php';
require_once MY_ECARD_PATH . 'includes/template-functions.php';
require_once MY_ECARD_PATH . 'includes/class-plugin.php';

register_activation_hook( __FILE__, array( 'MyECardExtensions\\Plugin', 'activate' ) );

MyECardExtensions\Plugin::boot();
