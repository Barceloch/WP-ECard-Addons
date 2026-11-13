<?php
/**
 * Asset versioning tests.
 *
 * @package MyECardExtensions
 */

namespace MyECardExtensions\Tests\Unit {

	use MyECardExtensions\Assets;
	use MyECardExtensions\CardData;
	use MyECardExtensions\TemplateLoader;
	use MyECardExtensions\Tests\TestCase;
	use WP_Post;

	/**
	 * Covers the release-safe asset version strategy (audit defect D1).
	 *
	 * The release build normalizes every packaged file mtime to 1980-01-01, so
	 * filemtime() cannot drive the `ver` query argument: it would freeze the
	 * value for every release and let browsers keep stale CSS or JavaScript.
	 */
	final class AssetsTest extends TestCase {

		/**
		 * Shared card ID.
		 */
		const CARD_ID = 130;

		/**
		 * Create the card used by the enqueue tests.
		 *
		 * @param array $meta Extra meta key => value pairs.
		 * @return int
		 */
		private function card( array $meta = array() ): int {
			$post = new WP_Post(
				array(
					'ID'            => self::CARD_ID,
					'post_type'     => 'ecard_digital_card',
					'post_title'    => 'Alex Rivera',
					'post_name'     => 'alex-rivera',
					'post_status'   => 'publish',
					'post_password' => '',
					'post_content'  => '',
				)
			);

			$GLOBALS['my_ecard_test_posts'][ self::CARD_ID ] = $post;

			update_post_meta( self::CARD_ID, '_ecard_email', 'alex@example.com' );
			update_post_meta( self::CARD_ID, '_ecard_location', 'https://maps.google.com/?q=Madrid' );

			foreach ( $meta as $key => $value ) {
				update_post_meta( self::CARD_ID, $key, $value );
			}

			return self::CARD_ID;
		}

		/**
		 * Publish the card in the render context and run the frontend enqueues.
		 *
		 * @return void
		 */
		private function enqueue(): void {
			$card = CardData::from_post( self::CARD_ID );

			$this->assert_not_null( $card );

			set_query_var( TemplateLoader::QUERY_VAR, array( 'card' => $card ) );

			( new Assets() )->frontend();
		}

		/**
		 * The asset version is the plugin version, not a file timestamp.
		 *
		 * @return void
		 */
		public function test_asset_version_is_the_plugin_version(): void {
			$this->assert_same( MY_ECARD_VERSION, \MyECardExtensions\my_ecard_asset_version() );
		}

		/**
		 * The plugin header and MY_ECARD_VERSION cannot drift apart.
		 *
		 * This is the chain that makes a version bump change the asset `ver`:
		 * header -> MY_ECARD_VERSION -> my_ecard_asset_version() -> enqueue.
		 *
		 * @return void
		 */
		public function test_plugin_header_version_matches_the_constant(): void {
			$source = (string) file_get_contents( MY_ECARD_FILE );

			$this->assert_true(
				1 === preg_match( '/^[ \t]*\*[ \t]*Version:[ \t]*(\S+)/m', $source, $matches ),
				'Version header not found in the plugin main file'
			);

			$this->assert_same( $matches[1], MY_ECARD_VERSION );
		}

		/**
		 * Normalized mtimes cannot influence the asset version.
		 *
		 * @return void
		 */
		public function test_asset_version_is_independent_of_file_mtime(): void {
			$asset = MY_ECARD_PATH . 'assets/css/action-bar.css';

			$this->assert_true( is_file( $asset ), 'action-bar.css must exist' );

			$mtime = (string) filemtime( $asset );

			$this->assert_false(
				\MyECardExtensions\my_ecard_asset_version() === $mtime,
				'the asset version must not be derived from filemtime()'
			);
		}

		/**
		 * Every enqueued action bar asset carries the plugin version.
		 *
		 * @return void
		 */
		public function test_action_bar_assets_use_the_plugin_version(): void {
			$this->card();
			$this->enqueue();

			$this->assert_key( 'my-ecard-action-bar', $GLOBALS['my_ecard_test_styles'] );
			$this->assert_key( 'my-ecard-action-bar', $GLOBALS['my_ecard_test_scripts'] );

			$this->assert_same(
				MY_ECARD_VERSION,
				$GLOBALS['my_ecard_test_styles']['my-ecard-action-bar']['ver']
			);
			$this->assert_same(
				MY_ECARD_VERSION,
				$GLOBALS['my_ecard_test_scripts']['my-ecard-action-bar']['ver']
			);
		}

		/**
		 * The media viewer loads only when a media action exists.
		 *
		 * @return void
		 */
		public function test_media_viewer_assets_are_conditional(): void {
			$this->card();
			$this->enqueue();

			$this->assert_false(
				isset( $GLOBALS['my_ecard_test_styles']['my-ecard-media-viewer'] ),
				'the media viewer must not load without gallery or videos'
			);

			$this->reset_wp_state();

			$GLOBALS['my_ecard_test_attachments'][901] = array(
				'full'   => 'https://cdn.example.test/gallery-1.jpg',
				'medium' => 'https://cdn.example.test/gallery-1-medium.jpg',
			);

			$this->card( array( '_ecard_gallery' => array( 901 ) ) );
			$this->enqueue();

			$this->assert_key( 'my-ecard-media-viewer', $GLOBALS['my_ecard_test_styles'] );
			$this->assert_key( 'my-ecard-media-viewer', $GLOBALS['my_ecard_test_scripts'] );

			$this->assert_same(
				MY_ECARD_VERSION,
				$GLOBALS['my_ecard_test_styles']['my-ecard-media-viewer']['ver']
			);
			$this->assert_same(
				MY_ECARD_VERSION,
				$GLOBALS['my_ecard_test_scripts']['my-ecard-media-viewer']['ver']
			);
		}

		/**
		 * A card without the bar enqueues nothing.
		 *
		 * @return void
		 */
		public function test_no_assets_without_the_bar(): void {
			$this->card( array( '_my_ecard_show_bar' => 0 ) );
			$this->enqueue();

			$this->assert_false(
				isset( $GLOBALS['my_ecard_test_styles']['my-ecard-action-bar'] ),
				'the action bar must not load when the bar is disabled'
			);
		}
	}
}
