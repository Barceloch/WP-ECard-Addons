<?php
/**
 * Test bootstrap: WordPress stubs, plugin includes and base test case.
 *
 * @package MyECardExtensions
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__ ) . '/' );
}

define( 'MY_ECARD_VERSION', '0.1.0' );
define( 'MY_ECARD_FILE', dirname( __DIR__ ) . '/wp-ecard-addons.php' );
define( 'MY_ECARD_PATH', dirname( __DIR__ ) . '/' );
define( 'MY_ECARD_URL', 'https://example.test/wp-content/plugins/wp-ecard-addons/' );

$my_ecard_autoload = MY_ECARD_PATH . 'vendor/autoload.php';

if ( is_readable( $my_ecard_autoload ) ) {
	require_once $my_ecard_autoload;
}

unset( $my_ecard_autoload );

require_once __DIR__ . '/wp-stubs.php';

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

require_once __DIR__ . '/TestCase.php';
