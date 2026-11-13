<?php
/**
 * Admin asset loading.
 *
 * @package MyECardExtensions
 */

namespace MyECardExtensions;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the addon assets.
 *
 * Admin editor assets load on the card edit screen. Frontend action bar
 * assets load only when the current card actually renders the shared bar.
 */
final class Assets {

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_enqueue_scripts', array( $this, 'admin' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'frontend' ), 20 );
	}

	/**
	 * Enqueue the shared action bar assets for a rendered card.
	 *
	 * @return void
	 */
	public function frontend(): void {
		$context = \MyECardExtensions\my_ecard_context();

		if ( ! isset( $context['card'] ) || ! $context['card'] instanceof CardData ) {
			return;
		}

		$card = $context['card'];

		if ( empty( $card->settings['show_bar'] ) ) {
			return;
		}

		$actions = \MyECardExtensions\ActionBar::actions(
			$card,
			array(
				'gallery' => true,
				'videos'  => true,
			)
		);

		if ( array() === $actions ) {
			return;
		}

		$css_ver = \MyECardExtensions\my_ecard_asset_version();
		$js_ver  = \MyECardExtensions\my_ecard_asset_version();

		wp_enqueue_style(
			'my-ecard-action-bar',
			MY_ECARD_URL . 'assets/css/action-bar.css',
			array(),
			$css_ver
		);

		wp_enqueue_script(
			'my-ecard-action-bar',
			MY_ECARD_URL . 'assets/js/action-bar.js',
			array(),
			$js_ver,
			true
		);

		if ( isset( $actions['gallery'] ) || isset( $actions['videos'] ) ) {
			$viewer_css_ver = \MyECardExtensions\my_ecard_asset_version();
			$viewer_js_ver  = \MyECardExtensions\my_ecard_asset_version();

			wp_enqueue_style(
				'my-ecard-media-viewer',
				MY_ECARD_URL . 'assets/css/media-viewer.css',
				array(),
				$viewer_css_ver
			);

			wp_enqueue_script(
				'my-ecard-media-viewer',
				MY_ECARD_URL . 'assets/js/media-viewer.js',
				array(),
				$viewer_js_ver,
				true
			);
		}
	}

	/**
	 * Enqueue assets on the ECard card edit screen only.
	 *
	 * @param string $hook Current admin page.
	 * @return void
	 */
	public function admin( string $hook ): void {
		if ( 'post.php' !== $hook && 'post-new.php' !== $hook ) {
			return;
		}

		$screen = get_current_screen();

		if ( ! $screen || CardData::POST_TYPE !== $screen->post_type ) {
			return;
		}

		wp_enqueue_media();

		wp_enqueue_style(
			'my-ecard-admin',
			MY_ECARD_URL . 'assets/css/admin.css',
			array(),
			MY_ECARD_VERSION
		);

		wp_enqueue_script(
			'my-ecard-admin',
			MY_ECARD_URL . 'assets/js/admin.js',
			array( 'media' ),
			MY_ECARD_VERSION,
			true
		);
	}
}
