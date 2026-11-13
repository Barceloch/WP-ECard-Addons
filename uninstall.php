<?php
/**
 * Uninstall routine for WP ECard Addons.
 *
 * Data removal is opt-in and disabled by default: uninstalling the plugin
 * keeps every stored setting unless the site explicitly asks for deletion.
 * Enable it with the `my_ecard_delete_data` option set to 1, or with the
 * `MY_ECARD_DELETE_DATA_ON_UNINSTALL` constant set to true in wp-config.php.
 *
 * Only addon-owned data is removed:
 *
 * - Post meta prefixed with `_my_ecard_`.
 * - The `my_ecard_version` and `my_ecard_delete_data` options.
 * - QR transients prefixed with `my_ecard_qr_` and their timeout rows.
 *
 * ECard metadata (`_ecard_*`), cards, users, attachments and any other
 * plugin data are never touched.
 *
 * @package MyECardExtensions
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

if ( ! function_exists( 'my_ecard_uninstall_site' ) ) {
	/**
	 * Remove addon data for the current site.
	 *
	 * @return void
	 */
	function my_ecard_uninstall_site() {
		global $wpdb;

		$meta_like = $wpdb->esc_like( '_my_ecard_' ) . '%';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$meta_like
			)
		);

		$transient_like         = $wpdb->esc_like( '_transient_my_ecard_qr_' ) . '%';
		$transient_timeout_like = $wpdb->esc_like( '_transient_timeout_my_ecard_qr_' ) . '%';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$transient_like,
				$transient_timeout_like
			)
		);

		delete_option( 'my_ecard_version' );
		delete_option( 'my_ecard_delete_data' );

		wp_cache_flush();
	}
}

$my_ecard_remove_data = (bool) get_option( 'my_ecard_delete_data', false );

if ( defined( 'MY_ECARD_DELETE_DATA_ON_UNINSTALL' ) ) {
	$my_ecard_remove_data = (bool) MY_ECARD_DELETE_DATA_ON_UNINSTALL;
}

if ( $my_ecard_remove_data ) {
	if ( is_multisite() ) {
		$my_ecard_sites = get_sites(
			array(
				'fields' => 'ids',
				'number' => 0,
			)
		);

		foreach ( $my_ecard_sites as $my_ecard_site_id ) {
			switch_to_blog( (int) $my_ecard_site_id );
			my_ecard_uninstall_site();
			restore_current_blog();
		}
	} else {
		my_ecard_uninstall_site();
	}
}

unset( $my_ecard_remove_data );
