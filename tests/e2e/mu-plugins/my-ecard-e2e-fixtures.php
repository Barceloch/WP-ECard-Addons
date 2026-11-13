<?php
/**
 * E2E fixtures: extra theme directory, document hook markers and fallback triggers.
 *
 * Mounted into wp-content/mu-plugins by the e2e docker compose file.
 *
 * @package MyECardExtensions
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
 * Register the fixture theme directory shipped with the addon tests.
 */
add_filter(
	'my_ecard_theme_directories',
	static function ( $directories ) {
		if ( defined( 'MY_ECARD_PATH' ) ) {
			$directories[] = MY_ECARD_PATH . 'tests/fixtures/render-themes';
		}

		return $directories;
	}
);

/*
 * Markers proving the document hooks ran, without depending on the active theme.
 */
add_action(
	'wp_head',
	static function () {
		echo '<!--my-ecard-e2e-head-->';
	},
	1
);

add_action(
	'wp_body_open',
	static function () {
		echo '<!--my-ecard-e2e-body-open-->';
	},
	1
);

add_action(
	'wp_footer',
	static function () {
		echo '<!--my-ecard-e2e-footer-->';
	},
	99
);

/*
 * Force the loader fallback paths from an HTTP request:
 *   ?my_ecard_e2e_break=invalid    returns an unusable context
 *   ?my_ecard_e2e_break=exception  throws while filtering
 */
add_filter(
	'my_ecard_render_context',
	static function ( $context ) {
		$mode = isset( $_GET['my_ecard_e2e_break'] ) ? sanitize_key( wp_unslash( $_GET['my_ecard_e2e_break'] ) ) : '';

		if ( 'invalid' === $mode ) {
			return array( 'mode' => 'card' );
		}

		if ( 'exception' === $mode ) {
			throw new RuntimeException( 'e2e forced exception' );
		}

		return $context;
	}
);

/*
 * Override the QR availability policy for tests:
 *   ?my_ecard_e2e_qr=1  force available
 *   ?my_ecard_e2e_qr=0  force unavailable
 *
 * The endpoint still validates the real library and GD before rendering, so
 * forcing availability cannot produce a fake image.
 */
add_filter(
	'my_ecard_qr_available',
	static function ( $available ) {
		$flag = isset( $_GET['my_ecard_e2e_qr'] ) ? sanitize_key( wp_unslash( $_GET['my_ecard_e2e_qr'] ) ) : '';

		if ( '1' === $flag ) {
			return true;
		}

		if ( '0' === $flag ) {
			return false;
		}

		return $available;
	}
);
