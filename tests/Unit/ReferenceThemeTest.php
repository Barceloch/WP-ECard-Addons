<?php
/**
 * Reference theme tests.
 *
 * @package MyECardExtensions
 */

namespace MyECardExtensions\Tests\Unit;

use MyECardExtensions\CardData;
use MyECardExtensions\TemplateLoader;
use MyECardExtensions\Tests\TestCase;
use MyECardExtensions\ThemeRegistry;
use WP_Post;

/**
 * Covers manifest registration, rendering, avatar states and CSS isolation.
 */
final class ReferenceThemeTest extends TestCase {

	/**
	 * Shared card ID.
	 */
	const CARD_ID = 70;

	/**
	 * Loader backed by the real addon themes directory.
	 *
	 * @return TemplateLoader
	 */
	private function loader(): TemplateLoader {
		return new TemplateLoader( new ThemeRegistry( array( MY_ECARD_PATH . 'themes' ), MY_ECARD_VERSION ) );
	}

	/**
	 * Create the reference card and its metadata.
	 *
	 * @param array $props Extra post properties.
	 * @return WP_Post
	 */
	private function card( array $props = array() ): WP_Post {
		$post = new WP_Post(
			array_merge(
				array(
					'ID'            => self::CARD_ID,
					'post_type'     => 'ecard_digital_card',
					'post_title'    => 'Alex Rivera',
					'post_name'     => 'alex-rivera',
					'post_status'   => 'publish',
					'post_password' => '',
					'post_content'  => '<p>Welcome</p>',
				),
				$props
			)
		);

		$GLOBALS['my_ecard_test_posts'][ self::CARD_ID ] = $post;
		$GLOBALS['my_ecard_test_attachments'][900]      = array(
			'full'   => 'https://cdn.example.test/cover.jpg',
			'medium' => 'https://cdn.example.test/cover-medium.jpg',
		);
		$GLOBALS['my_ecard_test_attachments'][901]      = array(
			'full'   => 'https://cdn.example.test/gallery-1.jpg',
			'medium' => 'https://cdn.example.test/gallery-1-medium.jpg',
		);

		return $post;
	}

	/**
	 * Assign the full reference configuration.
	 *
	 * @param int   $post_id Post ID.
	 * @param array $settings Overrides: theme, show_avatar, cover, height, bar.
	 * @return void
	 */
	private function configure( int $post_id, array $settings = array() ): void {
		$defaults = array(
			'theme'         => 'reference',
			'show_avatar'   => true,
			'cover'         => 900,
			'header_height' => 240,
			'show_bar'      => true,
			'gallery'       => true,
			'videos'        => true,
		);

		$settings = array_merge( $defaults, $settings );

		update_post_meta( $post_id, '_my_ecard_theme', (string) $settings['theme'] );
		update_post_meta( $post_id, '_my_ecard_header_height', (int) $settings['header_height'] );

		if ( ! $settings['show_avatar'] ) {
			update_post_meta( $post_id, '_my_ecard_show_avatar', 0 );
		}

		if ( ! $settings['show_bar'] ) {
			update_post_meta( $post_id, '_my_ecard_show_bar', 0 );
		}

		if ( $settings['cover'] > 0 ) {
			update_post_meta( $post_id, '_my_ecard_cover_image', (int) $settings['cover'] );
		}

		update_post_meta( $post_id, '_ecard_designation', 'Product Designer' );
		update_post_meta( $post_id, '_ecard_company_name', 'Studio Norte' );
		update_post_meta( $post_id, '_ecard_email', 'alex@example.com' );
		update_post_meta( $post_id, '_ecard_phone', '+34 600 111 222' );
		update_post_meta( $post_id, '_ecard_whatsapp', '+34 600 333 444' );
		update_post_meta( $post_id, '_ecard_location', 'https://maps.google.com/?q=Madrid' );
		update_post_meta( $post_id, '_ecard_person_photo', 'https://cdn.example.test/avatar.jpg' );
		update_post_meta( $post_id, '_ecard_social_links', array(
			'facebook'  => 'https://facebook.com/alex',
			'instagram' => 'https://instagram.com/alex',
			'twitter'   => 'https://twitter.com/alex',
			'youtube'   => 'https://youtube.com/@alex',
			'linkedin'  => 'https://linkedin.com/in/alex',
		) );

		if ( $settings['gallery'] ) {
			update_post_meta( $post_id, '_ecard_gallery', array( 901 ) );
		}

		if ( $settings['videos'] ) {
			update_post_meta(
				$post_id,
				'_ecard_videos',
				array(
					array(
						'title' => 'Intro',
						'url'   => 'https://youtu.be/abc',
					),
				)
			);
		}
	}

	/**
	 * Render the reference theme for the current request.
	 *
	 * @return string
	 */
	private function render(): string {
		$GLOBALS['my_ecard_test_singular']   = true;
		$GLOBALS['my_ecard_test_queried_id'] = self::CARD_ID;

		$loader = $this->loader();
		$loader->filter_template( 'original.php' );
		$context = $loader->context();

		$this->assert_not_null( $context );

		ob_start();
		\MyECardExtensions\my_ecard_render_theme_template( $context );

		return (string) ob_get_clean();
	}

	/**
	 * The manifest is discovered with its template and stylesheet.
	 *
	 * @return void
	 */
	public function test_theme_is_registered(): void {
		$registry = new ThemeRegistry( array( MY_ECARD_PATH . 'themes' ), MY_ECARD_VERSION );
		$theme    = $registry->get( 'reference' );

		$this->assert_not_null( $theme );
		$this->assert_same( 'Reference', $theme['name'] );
		$this->assert_same( realpath( MY_ECARD_PATH . 'themes/reference/template.php' ), realpath( $theme['template'] ) );
		$this->assert_count( 1, $theme['styles'] );
		$this->assert_contains( 'themes/reference/style.css', $theme['styles'][0] );
		$this->assert_true( $theme['supports']['avatar'] );
		$this->assert_true( $theme['supports']['cover'] );
	}

	/**
	 * The loader resolves the theme for a published card and enqueues its CSS.
	 *
	 * @return void
	 */
	public function test_loader_resolves_theme_and_enqueues_style(): void {
		$this->card();
		$this->configure( self::CARD_ID );
		$this->render();

		$loader = $this->loader();
		$loader->enqueue_theme_assets();

		$this->assert_key( 'my-ecard-theme-reference-style-0', $GLOBALS['my_ecard_test_styles'] );

		$style = $GLOBALS['my_ecard_test_styles']['my-ecard-theme-reference-style-0'];

		$this->assert_contains( 'plugins/wp-ecard-addons/themes/reference/style.css', $style['src'] );
	}

	/**
	 * The rendered markup contains the full composition.
	 *
	 * @return void
	 */
	public function test_renders_composition_with_card_data(): void {
		$this->card();
		$this->configure( self::CARD_ID );

		$html = $this->render();

		$this->assert_contains( 'my-ecard-theme-reference', $html );
		$this->assert_contains( 'data-my-ecard-theme="reference"', $html );
		$this->assert_contains( '--my-ecard-reference-hero:240px', $html );

		$this->assert_contains( 'my-ecard-theme-reference__hero-image', $html );
		$this->assert_contains( 'https://cdn.example.test/cover.jpg', $html );
		$this->assert_contains( 'my-ecard-theme-reference__avatar', $html );
		$this->assert_contains( 'https://cdn.example.test/avatar.jpg', $html );

		$this->assert_contains( 'Alex Rivera', $html );
		$this->assert_contains( 'Product Designer', $html );
		$this->assert_contains( 'Studio Norte', $html );
	}

	/**
	 * The avatar is removed, not hidden, when the card disables it.
	 *
	 * @return void
	 */
	public function test_avatar_can_be_disabled_without_empty_space(): void {
		$this->card();
		$this->configure( self::CARD_ID, array( 'show_avatar' => false ) );

		$html = $this->render();

		$this->assert_false( strpos( $html, 'my-ecard-theme-reference__avatar-wrap' ) );
		$this->assert_false( strpos( $html, 'cdn.example.test/avatar.jpg' ) );
		$this->assert_contains( 'my-ecard-theme-reference__identity', $html );
	}

	/**
	 * Cover and avatar are independent images.
	 *
	 * @return void
	 */
	public function test_cover_and_avatar_are_independent(): void {
		$this->card();
		$this->configure( self::CARD_ID );

		$html = $this->render();

		$this->assert_contains( 'https://cdn.example.test/cover.jpg', $html );
		$this->assert_contains( 'https://cdn.example.test/avatar.jpg', $html );
		$this->assert_same(
			1,
			preg_match( '/__hero-image[^>]*src="([^"]+)"/', $html, $hero )
		);
		$this->assert_same(
			1,
			preg_match( '/__avatar"[^>]*src="([^"]+)"/', $html, $avatar )
		);
		$this->assert_true( $hero[1] !== $avatar[1] );
	}

	/**
	 * Without a cover the theme renders the decorative hero background.
	 *
	 * @return void
	 */
	public function test_hero_without_cover_has_no_image(): void {
		$this->card();
		$this->configure( self::CARD_ID, array( 'cover' => 0 ) );

		$html = $this->render();

		$this->assert_false( strpos( $html, 'my-ecard-theme-reference__hero-image' ) );
		$this->assert_contains( 'my-ecard-theme-reference__hero', $html );
		$this->assert_false( strpos( $html, 'cdn.example.test/cover.jpg' ) );
	}

	/**
	 * Contact buttons only render available channels with real URLs.
	 *
	 * @return void
	 */
	public function test_contact_links_are_functional(): void {
		$this->card();
		$this->configure( self::CARD_ID );

		$html = $this->render();

		$this->assert_contains( 'href="tel:+34600111222"', $html );
		$this->assert_contains( 'href="mailto:alex@example.com"', $html );
		$this->assert_contains( 'https://wa.me/34600333444', $html );
		$this->assert_contains( 'my-ecard-theme-reference__contact--phone', $html );
		$this->assert_contains( 'my-ecard-theme-reference__contact--email', $html );
		$this->assert_contains( 'my-ecard-theme-reference__contact--whatsapp', $html );
	}

	/**
	 * Incomplete contacts render only what exists.
	 *
	 * @return void
	 */
	public function test_incomplete_contacts_render_only_email(): void {
		$this->card();
		$this->configure( self::CARD_ID );

		update_post_meta( self::CARD_ID, '_ecard_phone', '' );
		update_post_meta( self::CARD_ID, '_ecard_whatsapp', '' );

		$html = $this->render();

		$this->assert_contains( 'my-ecard-theme-reference__contact--email', $html );
		$this->assert_false( strpos( $html, 'my-ecard-theme-reference__contact--phone' ) );
		$this->assert_false( strpos( $html, 'my-ecard-theme-reference__contact--whatsapp' ) );
	}

	/**
	 * Social links use real URLs and brand modifiers.
	 *
	 * @return void
	 */
	public function test_social_links_are_branded(): void {
		$this->card();
		$this->configure( self::CARD_ID );

		$html = $this->render();

		$this->assert_contains( 'https://facebook.com/alex', $html );
		$this->assert_contains( 'https://instagram.com/alex', $html );
		$this->assert_contains( 'https://twitter.com/alex', $html );
		$this->assert_contains( 'https://youtube.com/@alex', $html );
		$this->assert_contains( 'https://linkedin.com/in/alex', $html );
		$this->assert_contains( 'social-link--facebook', $html );
		$this->assert_contains( 'social-link--instagram', $html );
		$this->assert_contains( 'fa-brands fa-facebook-f', $html );
		$this->assert_contains( 'rel="noopener noreferrer nofollow"', $html );
		$this->assert_same( 5, substr_count( $html, 'my-ecard-theme-reference__social-link ' ) );
	}

	/**
	 * A single social network still renders correctly.
	 *
	 * @return void
	 */
	public function test_single_social_network(): void {
		$this->card();
		$this->configure( self::CARD_ID );

		update_post_meta( self::CARD_ID, '_ecard_social_links', array( 'linkedin' => 'https://linkedin.com/in/alex' ) );

		$html = $this->render();

		$this->assert_same( 1, substr_count( $html, 'my-ecard-theme-reference__social-link ' ) );
		$this->assert_contains( 'social-link--linkedin', $html );
	}

	/**
	 * Gallery data is never rendered inside the card body.
	 *
	 * The Reference theme deliberately omits the visual gallery section: the
	 * images stay in CardData and are exposed only through the shell-owned
	 * gallery viewer opened from the action bar.
	 *
	 * @return void
	 */
	public function test_gallery_is_not_rendered_inside_the_card(): void {
		$this->card();
		$this->configure( self::CARD_ID );

		$html = $this->render();

		$this->assert_false( strpos( $html, 'id="my-ecard-gallery"' ) );
		$this->assert_false( strpos( $html, 'gallery-grid' ) );
		$this->assert_false( strpos( $html, 'gallery-item' ) );
		$this->assert_false( strpos( $html, 'gallery-1-medium.jpg' ) );
		$this->assert_false( strpos( $html, 'gallery-1.jpg' ) );
		$this->assert_false( strpos( $html, 'theme-reference__gallery' ) );
	}

	/**
	 * Video data is never rendered inside the card body.
	 *
	 * @return void
	 */
	public function test_videos_are_not_rendered_inside_the_card(): void {
		$this->card();
		$this->configure( self::CARD_ID );

		$html = $this->render();

		$this->assert_false( strpos( $html, 'id="my-ecard-videos"' ) );
		$this->assert_false( strpos( $html, 'video-list' ) );
		$this->assert_false( strpos( $html, 'video-link' ) );
		$this->assert_false( strpos( $html, 'youtu.be/abc' ) );
		$this->assert_false( strpos( $html, 'theme-reference__video' ) );
	}

	/**
	 * No empty media section or reserved separator survives the removal.
	 *
	 * @return void
	 */
	public function test_no_media_section_wrapper_or_separator_remains(): void {
		$this->card();
		$this->configure( self::CARD_ID );

		$html = $this->render();

		$this->assert_false( strpos( $html, 'theme-reference__section' ) );
		$this->assert_false( strpos( $html, '<section' ) );
		$this->assert_false( strpos( $html, 'Gallery' ) );
		$this->assert_false( strpos( $html, 'Videos' ) );
	}

	/**
	 * The card still renders its primary content with media data present.
	 *
	 * @return void
	 */
	public function test_primary_content_survives_while_media_is_present(): void {
		$this->card();
		$this->configure( self::CARD_ID );

		$html = $this->render();

		$this->assert_contains( 'my-ecard-theme-reference__name', $html );
		$this->assert_contains( 'Alex Rivera', $html );
		$this->assert_contains( 'my-ecard-theme-reference__contact', $html );
		$this->assert_contains( 'my-ecard-theme-reference__social-link', $html );
	}

	/**
	 * The card body exposes no media while the shell still renders the viewers.
	 *
	 * This is the core of the requirement: the Reference theme drops its
	 * in-card Gallery and Videos sections, but the very same card data still
	 * feeds the independent viewers that the action bar opens.
	 *
	 * @return void
	 */
	public function test_card_body_has_no_media_while_viewers_still_render(): void {
		$this->card();
		$this->configure( self::CARD_ID );

		$body = $this->render();

		$this->assert_false( strpos( $body, 'id="my-ecard-gallery"' ) );
		$this->assert_false( strpos( $body, 'id="my-ecard-videos"' ) );
		$this->assert_false( strpos( $body, 'gallery-1-medium.jpg' ) );
		$this->assert_false( strpos( $body, 'youtu.be/abc' ) );

		$card = CardData::from_post( self::CARD_ID );

		$this->assert_not_null( $card );
		$this->assert_count( 1, $card->gallery );
		$this->assert_count( 1, $card->videos );

		ob_start();
		\MyECardExtensions\my_ecard_media_viewers( $card );

		$viewers = (string) ob_get_clean();

		$this->assert_contains( 'id="my-ecard-gallery-viewer"', $viewers );
		$this->assert_contains( 'id="my-ecard-videos-viewer"', $viewers );
		$this->assert_contains( 'gallery-1-medium.jpg', $viewers );
		$this->assert_contains( 'youtu.be/abc', $viewers );
	}

	/**
	 * The manifest no longer advertises in-card gallery or video sections.
	 *
	 * @return void
	 */
	public function test_manifest_does_not_declare_media_sections(): void {
		$registry = new ThemeRegistry( array( MY_ECARD_PATH . 'themes' ), MY_ECARD_VERSION );
		$theme    = $registry->get( 'reference' );

		$this->assert_not_null( $theme );
		$this->assert_false( $theme['supports']['gallery'] );
		$this->assert_false( $theme['supports']['videos'] );
		$this->assert_contains( 'themes/reference/preview.png', $theme['preview_url'] );
	}

	/**
	 * The theme leaves the action bar to the shared shell component.
	 *
	 * @return void
	 */
	public function test_theme_does_not_render_the_shared_action_bar(): void {
		$this->card();
		$this->configure( self::CARD_ID );

		$html = $this->render();

		$this->assert_false( strpos( $html, 'data-my-ecard-action-bar' ) );
		$this->assert_false( strpos( $html, 'my-ecard-theme-reference__bar' ) );
		$this->assert_contains( 'my-ecard-theme-reference__identity', $html );
		$this->assert_contains( 'my-ecard-theme-reference__contacts', $html );
		$this->assert_contains( 'my-ecard-theme-reference__social', $html );
	}

	/**
	 * Disabling the bar keeps the theme content untouched.
	 *
	 * @return void
	 */
	public function test_theme_renders_content_when_bar_is_disabled(): void {
		$this->card();
		$this->configure( self::CARD_ID, array( 'show_bar' => false ) );

		$html = $this->render();

		$this->assert_false( strpos( $html, 'data-my-ecard-action-bar' ) );
		$this->assert_contains( 'my-ecard-theme-reference__social', $html );
	}

	/**
	 * The stylesheet stays inside the theme namespace.
	 *
	 * @return void
	 */
	public function test_stylesheet_is_namespaced(): void {
		$path = MY_ECARD_PATH . 'themes/reference/style.css';

		$this->assert_true( is_file( $path ) );

		$css = (string) file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$css = (string) preg_replace( '!/\*.*?\*/!s', '', $css );

		$selectors = 0;

		foreach ( explode( '}', $css ) as $block ) {
			$parts = explode( '{', $block );

			if ( count( $parts ) < 2 ) {
				continue;
			}

			$selector = trim( $parts[0] );

			if ( '' === $selector || '@' === $selector[0] ) {
				continue;
			}

			foreach ( explode( ',', $selector ) as $one ) {
				$one = trim( $one );

				if ( '' === $one ) {
					continue;
				}

				$selectors++;

				$this->assert_true(
					0 === strpos( $one, '.my-ecard-theme-reference' ),
					'Unscoped selector: ' . $one
				);
			}
		}

		$this->assert_true( $selectors > 20, 'Expected a stylesheet with real rules, found ' . $selectors );
	}

	/**
	 * Rendering emits no PHP diagnostics.
	 *
	 * @return void
	 */
	public function test_rendering_emits_no_php_diagnostics(): void {
		$this->card();
		$this->configure( self::CARD_ID );

		$errors = array();

		set_error_handler(
			static function ( $errno, $errstr ) use ( &$errors ) {
				$errors[] = $errstr;
				return true;
			}
		);

		$html = $this->render();

		restore_error_handler();

		$this->assert_same( array(), $errors );
		$this->assert_true( '' !== trim( $html ) );
	}
}
