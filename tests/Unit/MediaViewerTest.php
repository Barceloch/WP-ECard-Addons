<?php
/**
 * Shared media viewer tests.
 *
 * @package MyECardExtensions
 */

namespace MyECardExtensions\Tests\Unit {

	use MyECardExtensions\CardData;
	use MyECardExtensions\TemplateLoader;
	use MyECardExtensions\Tests\TestCase;
	use MyECardExtensions\ThemeRegistry;
	use WP_Post;

	/**
	 * Covers the gallery and videos dialogs rendered by the shared shell part.
	 */
	final class MediaViewerTest extends TestCase {

		/**
		 * Shared card ID.
		 */
		const CARD_ID = 110;

		/**
		 * YouTube video URL used by the fixtures.
		 */
		const YOUTUBE_URL = 'https://www.youtube.com/watch?v=abc12345678';

		/**
		 * Vimeo video URL used by the fixtures.
		 */
		const VIMEO_URL = 'https://vimeo.com/123456789';

		/**
		 * Direct MP4 URL used by the fixtures.
		 */
		const DIRECT_URL = 'https://cdn.example.test/clip.mp4';

		/**
		 * Unsupported-but-valid URL used by the fixtures.
		 */
		const OTHER_URL = 'https://example.test/watch/123';

		/**
		 * Loader backed by the real addon themes directory.
		 *
		 * @return TemplateLoader
		 */
		private function loader(): TemplateLoader {
			return new TemplateLoader( new ThemeRegistry( array( MY_ECARD_PATH . 'themes' ), MY_ECARD_VERSION ) );
		}

		/**
		 * Register one attachment stub.
		 *
		 * @param int    $id    Attachment ID.
		 * @param string $slug  File slug.
		 * @param string $title Attachment title.
		 * @param string $alt   Image alt text.
		 * @return void
		 */
		private function attachment( int $id, string $slug, string $title, string $alt = '' ): void {
			$GLOBALS['my_ecard_test_attachments'][ $id ] = array(
				'full'   => 'https://cdn.example.test/' . $slug . '.jpg',
				'medium' => 'https://cdn.example.test/' . $slug . '-medium.jpg',
				'title'  => $title,
			);

			update_post_meta( $id, '_wp_attachment_image_alt', $alt );
		}

		/**
		 * Create the card, its gallery attachments and its media metadata.
		 *
		 * @param array $overrides Meta key => value overrides.
		 * @return WP_Post
		 */
		private function card( array $overrides = array() ): WP_Post {
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

			$this->attachment( 900, 'gallery-1', 'Gallery one', 'Gallery one' );
			$this->attachment( 901, 'gallery-2', 'Gallery two', 'Gallery two' );
			$this->attachment( 902, 'gallery-3', 'Gallery three', 'Gallery three' );

			$values = array_merge(
				array(
					'_my_ecard_theme'          => 'reference',
					'_ecard_gallery'           => array( 900, 901, 902 ),
					'_ecard_videos'            => array(
						array(
							'title' => 'YouTube intro',
							'url'   => self::YOUTUBE_URL,
						),
						array(
							'title' => 'Vimeo clip',
							'url'   => self::VIMEO_URL,
						),
						array(
							'title' => 'Direct clip',
							'url'   => self::DIRECT_URL,
						),
						array(
							'title' => 'Other clip',
							'url'   => self::OTHER_URL,
						),
					),
					'_my_ecard_enable_gallery' => 1,
					'_my_ecard_enable_videos'  => 1,
				),
				$overrides
			);

			foreach ( $values as $key => $value ) {
				update_post_meta( self::CARD_ID, $key, $value );
			}

			return $post;
		}

		/**
		 * Build the normalized card for the current metadata.
		 *
		 * @return CardData
		 */
		private function data(): CardData {
			$card = CardData::from_post( self::CARD_ID );

			$this->assert_not_null( $card );

			return $card;
		}

		/**
		 * Render the media viewers part through the resolved request context.
		 *
		 * The query var is populated exactly like a real request: the template
		 * loader swaps the template and stores the context WordPress exposes to
		 * my_ecard_card().
		 *
		 * @param CardData|null $card Optional card to render.
		 * @return string
		 */
		private function render( ?CardData $card = null ): string {
			$GLOBALS['my_ecard_test_singular']   = true;
			$GLOBALS['my_ecard_test_queried_id'] = self::CARD_ID;

			$loader = $this->loader();
			$loader->filter_template( 'original.php' );

			$context = $loader->context();

			$this->assert_not_null( $context );

			if ( ! $card instanceof CardData ) {
				$card = isset( $context['card'] ) && $context['card'] instanceof CardData ? $context['card'] : $this->data();
			}

			ob_start();
			\MyECardExtensions\my_ecard_media_viewers( $card );

			return (string) ob_get_clean();
		}

		/**
		 * Gallery renders one item per attachment with its media data.
		 *
		 * @return void
		 */
		public function test_gallery_viewer_renders_items_and_data(): void {
			$this->card( array( '_ecard_videos' => array() ) );

			$html = $this->render();

			$this->assert_contains( 'id="my-ecard-gallery-viewer"', $html );
			$this->assert_contains( 'data-my-ecard-media-viewer="gallery"', $html );
			$this->assert_same( 3, substr_count( $html, 'data-my-ecard-media-item' ) );
			$this->assert_contains( 'data-my-ecard-media-image', $html );

			$this->assert_contains( 'data-src="https://cdn.example.test/gallery-1.jpg"', $html );
			$this->assert_contains( 'data-thumb="https://cdn.example.test/gallery-1-medium.jpg"', $html );
			$this->assert_contains( 'data-alt="Gallery one"', $html );

			$this->assert_contains( '1 / 3', $html );
			$this->assert_same( false, strpos( $html, 'id="my-ecard-videos-viewer"' ) );
		}

		/**
		 * A single gallery image disables the prev and next buttons.
		 *
		 * @return void
		 */
		public function test_gallery_with_one_item_disables_navigation(): void {
			$this->card(
				array(
					'_ecard_gallery' => array( 901 ),
					'_ecard_videos'  => array(),
				)
			);

			$html = $this->render();

			$this->assert_same( 1, preg_match( '/data-my-ecard-media-prev[^>]*disabled/', $html ) );
			$this->assert_same( 1, preg_match( '/data-my-ecard-media-next[^>]*disabled/', $html ) );
			$this->assert_contains( '1 / 1', $html );
			$this->assert_same( 1, substr_count( $html, 'data-my-ecard-media-item' ) );
		}

		/**
		 * Videos render embed data for supported providers and direct files.
		 *
		 * @return void
		 */
		public function test_videos_viewer_renders_embeds_for_supported_providers(): void {
			$this->card( array( '_ecard_gallery' => array() ) );

			$html = $this->render();

			$this->assert_contains( 'id="my-ecard-videos-viewer"', $html );
			$this->assert_contains( 'data-my-ecard-media-viewer="videos"', $html );
			$this->assert_same( 4, substr_count( $html, 'data-my-ecard-media-item' ) );

			$this->assert_contains( 'data-embed="https://www.youtube-nocookie.com/embed/', $html );
			$this->assert_contains( 'https://www.youtube-nocookie.com/embed/abc12345678', $html );
			$this->assert_contains( 'https://player.vimeo.com/video/123456789', $html );

			$this->assert_contains( 'data-provider="youtube"', $html );
			$this->assert_contains( 'data-provider="vimeo"', $html );
			$this->assert_contains( 'data-direct="' . self::DIRECT_URL . '"', $html );
			$this->assert_contains( 'data-url="' . self::OTHER_URL . '"', $html );

			$this->assert_same( false, strpos( $html, 'id="my-ecard-gallery-viewer"' ) );
		}

		/**
		 * Without media the part renders nothing at all.
		 *
		 * @return void
		 */
		public function test_viewers_are_not_rendered_without_data(): void {
			$this->card(
				array(
					'_ecard_gallery' => array(),
					'_ecard_videos'  => array(),
				)
			);

			$this->assert_same( '', $this->render() );
		}

		/**
		 * Disabling one setting removes only its own viewer.
		 *
		 * @return void
		 */
		public function test_disabled_settings_remove_the_matching_viewer(): void {
			$this->card( array( '_my_ecard_enable_gallery' => 0 ) );

			$html = $this->render();

			$this->assert_same( false, strpos( $html, 'id="my-ecard-gallery-viewer"' ) );
			$this->assert_contains( 'id="my-ecard-videos-viewer"', $html );

			$this->card( array( '_my_ecard_enable_videos' => 0 ) );

			$html = $this->render();

			$this->assert_contains( 'id="my-ecard-gallery-viewer"', $html );
			$this->assert_same( false, strpos( $html, 'id="my-ecard-videos-viewer"' ) );
		}

		/**
		 * URLs dropped by the read model never produce a videos viewer.
		 *
		 * @return void
		 */
		public function test_invalid_video_urls_are_dropped(): void {
			$this->card(
				array(
					'_ecard_videos' => array(
						array(
							'title' => 'Script',
							'url'   => 'javascript:alert(1)',
						),
						array(
							'title' => 'Relative',
							'url'   => 'not-a-url',
						),
						array(
							'title' => 'Empty',
							'url'   => '',
						),
					),
				)
			);

			$card = $this->data();

			$this->assert_count( 0, $card->videos );

			$html = $this->render( $card );

			$this->assert_same( false, strpos( $html, 'id="my-ecard-videos-viewer"' ) );
			$this->assert_contains( 'id="my-ecard-gallery-viewer"', $html );
		}

		/**
		 * Viewer text is escaped and the server never embeds raw media.
		 *
		 * @return void
		 */
		public function test_viewer_markup_escapes_text_and_does_not_embed_raw_media(): void {
			$this->card(
				array(
					'_ecard_gallery' => array( 900 ),
					'_ecard_videos'  => array(),
				)
			);

			$GLOBALS['my_ecard_test_attachments'][900]['title'] = 'Title "quoted" <b>bold</b>';

			update_post_meta( 900, '_wp_attachment_image_alt', 'Alt "quoted" <script>alert(1)</script>' );

			$html = $this->render();

			$this->assert_contains( 'Alt &quot;quoted&quot; &lt;script&gt;alert(1)&lt;/script&gt;', $html );
			$this->assert_contains( 'Title &quot;quoted&quot; &lt;b&gt;bold&lt;/b&gt;', $html );

			$this->assert_same( false, strpos( $html, '<iframe' ) );
			$this->assert_same( false, strpos( $html, '<video' ) );
			$this->assert_same( false, strpos( $html, '<script' ) );
			$this->assert_same( false, strpos( $html, '<b>' ) );
		}

		/**
		 * The part only needs the card in the query var, not a theme or anchors.
		 *
		 * @return void
		 */
		public function test_viewers_are_rendered_for_any_theme_without_section_anchors(): void {
			$this->card();

			$card = $this->data();

			set_query_var( TemplateLoader::QUERY_VAR, array( 'card' => $card ) );

			ob_start();
			\MyECardExtensions\my_ecard_media_viewers();
			$html = (string) ob_get_clean();

			$this->assert_contains( 'id="my-ecard-gallery-viewer"', $html );
			$this->assert_contains( 'id="my-ecard-videos-viewer"', $html );
			$this->assert_contains( 'data-my-ecard-media-viewer="gallery"', $html );
			$this->assert_contains( 'data-my-ecard-media-viewer="videos"', $html );
		}
	}
}
