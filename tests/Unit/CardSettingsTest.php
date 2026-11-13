<?php
/**
 * Per-card settings tests.
 *
 * @package MyECardExtensions
 */

namespace MyECardExtensions\Tests\Unit;

use MyECardExtensions\CardSettings;
use MyECardExtensions\QRCodeGenerator;
use MyECardExtensions\Tests\TestCase;
use MyECardExtensions\ThemeRegistry;
use WP_Post;

/**
 * Covers defaults, stored value revalidation, sanitization and saving.
 */
final class CardSettingsTest extends TestCase {

	/**
	 * Build settings backed by the fixture theme registry.
	 *
	 * @return CardSettings
	 */
	private function settings(): CardSettings {
		return new CardSettings(
			new ThemeRegistry( array( dirname( __DIR__ ) . '/fixtures/themes' ), '0.1.0' )
		);
	}

	/**
	 * Create a stubbed ECard card.
	 *
	 * @param int $post_id Post ID.
	 * @return WP_Post
	 */
	private function card( int $post_id = 10 ): WP_Post {
		$post = new WP_Post(
			array(
				'ID'           => $post_id,
				'post_type'    => 'ecard_digital_card',
				'post_title'   => 'Jane Doe',
				'post_name'    => 'jane-doe',
				'post_status'  => 'publish',
				'post_content' => '',
			)
		);

		$GLOBALS['my_ecard_test_posts'][ $post_id ] = $post;

		return $post;
	}

	/**
	 * Defaults cover every documented setting.
	 *
	 * @return void
	 */
	public function test_defaults(): void {
		$defaults = CardSettings::defaults();

		$this->assert_count( 19, $defaults );
		$this->assert_same( '', $defaults['theme'] );
		$this->assert_same( 1, $defaults['show_avatar'] );
		$this->assert_same( 0, $defaults['cover_image'] );
		$this->assert_same( 220, $defaults['header_height'] );
		$this->assert_same( 'fixed', $defaults['bar_position'] );
		$this->assert_same( 'show', $defaults['bar_labels'] );
		$this->assert_same( 220, $defaults['qr_size'] );
		$this->assert_same( '#0f172a', $defaults['qr_dark'] );
		$this->assert_same( '#ffffff', $defaults['qr_light'] );
	}

	/**
	 * Meta keys use the addon prefix.
	 *
	 * @return void
	 */
	public function test_meta_key_prefix(): void {
		$this->assert_same( '_my_ecard_theme', CardSettings::meta_key( 'theme' ) );
		$this->assert_same( '_my_ecard_qr_size', CardSettings::meta_key( 'qr_size' ) );
	}

	/**
	 * Missing meta falls back to defaults.
	 *
	 * @return void
	 */
	public function test_read_without_meta_returns_defaults(): void {
		$this->assert_same( CardSettings::defaults(), CardSettings::read( 10 ) );
	}

	/**
	 * Numeric values are clamped back into range.
	 *
	 * @return void
	 */
	public function test_read_clamps_numeric_values(): void {
		update_post_meta( 10, '_my_ecard_header_height', 9999 );
		update_post_meta( 10, '_my_ecard_qr_size', 10 );
		update_post_meta( 10, '_my_ecard_cover_image', -5 );

		$values = CardSettings::read( 10 );

		$this->assert_same( 480, $values['header_height'] );
		$this->assert_same( 96, $values['qr_size'] );
		$this->assert_same( 0, $values['cover_image'] );
	}

	/**
	 * Booleans are normalized to 0 or 1.
	 *
	 * @return void
	 */
	public function test_read_normalizes_booleans(): void {
		update_post_meta( 10, '_my_ecard_show_bar', '0' );
		update_post_meta( 10, '_my_ecard_enable_qr', '1' );
		update_post_meta( 10, '_my_ecard_enable_vcard', '7' );

		$values = CardSettings::read( 10 );

		$this->assert_same( 0, $values['show_bar'] );
		$this->assert_same( 1, $values['enable_qr'] );
		$this->assert_same( 1, $values['enable_vcard'] );
	}

	/**
	 * Stored choices outside the allowlist fall back to the default.
	 *
	 * @return void
	 */
	public function test_read_revalidates_choices(): void {
		update_post_meta( 10, '_my_ecard_bar_position', 'absolute' );
		update_post_meta( 10, '_my_ecard_qr_mode', 'evil' );

		$values = CardSettings::read( 10 );

		$this->assert_same( 'fixed', $values['bar_position'] );
		$this->assert_same( 'card', $values['qr_mode'] );
	}

	/**
	 * A stored sticky bar position is a supported choice.
	 *
	 * @return void
	 */
	public function test_read_accepts_sticky_bar_position(): void {
		$this->card();

		update_post_meta( 10, '_my_ecard_bar_position', 'sticky' );

		$this->assert_same( 'sticky', CardSettings::read( 10 )['bar_position'] );
		$this->assert_same( 'sticky', CardSettings::sanitize( array( 'bar_position' => 'sticky' ), array() )['bar_position'] );
	}

	/**
	 * Stored colors are revalidated against sanitize_hex_color().
	 *
	 * @return void
	 */
	public function test_read_revalidates_colors(): void {
		update_post_meta( 10, '_my_ecard_qr_dark', 'javascript:alert(1)' );
		update_post_meta( 10, '_my_ecard_accent_color', 'red' );

		$values = CardSettings::read( 10 );

		$this->assert_same( '#0f172a', $values['qr_dark'] );
		$this->assert_same( '', $values['accent_color'] );
	}

	/**
	 * Stored theme ids are normalized with sanitize_key().
	 *
	 * @return void
	 */
	public function test_read_normalizes_theme_key(): void {
		update_post_meta( 10, '_my_ecard_theme', 'Bad Theme!' );

		$this->assert_same( 'badtheme', CardSettings::read( 10 )['theme'] );
	}

	/**
	 * Unexpected array values fall back to defaults instead of throwing.
	 *
	 * @return void
	 */
	public function test_read_ignores_non_scalar_values(): void {
		update_post_meta( 10, '_my_ecard_header_height', array( 300 ) );
		update_post_meta( 10, '_my_ecard_bar_position', array( 'static' ) );

		$values = CardSettings::read( 10 );

		$this->assert_same( 220, $values['header_height'] );
		$this->assert_same( 'fixed', $values['bar_position'] );
	}

	/**
	 * Sanitization accepts registered theme ids only.
	 *
	 * @return void
	 */
	public function test_sanitize_accepts_registered_theme(): void {
		$clean = CardSettings::sanitize( array( 'theme' => 'valid' ), array( 'valid' ) );

		$this->assert_same( 'valid', $clean['theme'] );
	}

	/**
	 * Sanitization rejects unknown theme ids.
	 *
	 * @return void
	 */
	public function test_sanitize_rejects_unknown_theme(): void {
		$clean = CardSettings::sanitize( array( 'theme' => 'ghost' ), array( 'valid' ) );

		$this->assert_same( '', $clean['theme'] );
	}

	/**
	 * Sanitization clamps ranges and normalizes choices.
	 *
	 * @return void
	 */
	public function test_sanitize_clamps_and_validates(): void {
		$clean = CardSettings::sanitize(
			array(
				'header_height' => '9999',
				'qr_size'       => '10',
				'bar_position'  => 'absolute',
				'qr_mode'       => 'other',
				'qr_dark'       => '#123456',
				'qr_light'      => 'nope',
			),
			array()
		);

		$this->assert_same( 480, $clean['header_height'] );
		$this->assert_same( 96, $clean['qr_size'] );
		$this->assert_same( 'fixed', $clean['bar_position'] );
		$this->assert_same( 'card', $clean['qr_mode'] );
		$this->assert_same( '#123456', $clean['qr_dark'] );
		$this->assert_same( '#ffffff', $clean['qr_light'] );
	}

	/**
	 * Unchecked checkboxes become 0.
	 *
	 * @return void
	 */
	public function test_sanitize_coerces_checkboxes(): void {
		$clean = CardSettings::sanitize(
			array(
				'show_avatar' => '1',
				'enable_qr'   => '1',
			),
			array()
		);

		$this->assert_same( 1, $clean['show_avatar'] );
		$this->assert_same( 0, $clean['show_bar'] );
		$this->assert_same( 0, $clean['enable_vcard'] );
	}

	/**
	 * The cover image must be an existing image attachment.
	 *
	 * @return void
	 */
	public function test_sanitize_cover_requires_image_attachment(): void {
		$GLOBALS['my_ecard_test_attachments'][7] = array(
			'full'   => 'https://example.test/cover-full.jpg',
			'medium' => 'https://example.test/cover-medium.jpg',
			'title'  => 'Cover',
		);

		$this->assert_same( 7, CardSettings::sanitize( array( 'cover_image' => '7' ), array() )['cover_image'] );
		$this->assert_same( 0, CardSettings::sanitize( array( 'cover_image' => '99' ), array() )['cover_image'] );
		$this->assert_same( 0, CardSettings::sanitize( array( 'cover_image' => 'abc' ), array() )['cover_image'] );
	}

	/**
	 * Saving stores only values that differ from the defaults.
	 *
	 * @return void
	 */
	public function test_save_persists_only_non_default_values(): void {
		$post     = $this->card();
		$settings = $this->settings();

		$_POST[ CardSettings::NONCE_NAME ] = 'stub-nonce';
		$_POST[ CardSettings::FIELD ]      = array(
			'theme'         => 'valid',
			'header_height' => '300',
			'show_avatar'   => '1',
			'show_bar'      => '1',
			'qr_label'      => '1',
			'enable_qr'     => '1',
			'enable_vcard'  => '1',
			'enable_share'  => '1',
			'enable_location' => '1',
			'enable_gallery'  => '1',
			'enable_videos'   => '1',
		);

		$settings->save( $post->ID, $post );

		$this->assert_true( metadata_exists( 'post', $post->ID, '_my_ecard_theme' ) );
		$this->assert_same( 'valid', get_post_meta( $post->ID, '_my_ecard_theme', true ) );
		$this->assert_same( 300, get_post_meta( $post->ID, '_my_ecard_header_height', true ) );
		$this->assert_false( metadata_exists( 'post', $post->ID, '_my_ecard_show_avatar' ) );
		$this->assert_false( metadata_exists( 'post', $post->ID, '_my_ecard_qr_size' ) );
	}

	/**
	 * Unchecking a setting stores an explicit 0 when the default is 1.
	 *
	 * @return void
	 */
	public function test_save_stores_disabled_feature(): void {
		$post     = $this->card( 11 );
		$settings = $this->settings();

		$_POST[ CardSettings::NONCE_NAME ] = 'stub-nonce';
		$_POST[ CardSettings::FIELD ]      = array(
			'show_avatar' => '1',
		);

		$settings->save( $post->ID, $post );

		$this->assert_same( 0, get_post_meta( $post->ID, '_my_ecard_enable_qr', true ) );
		$this->assert_same( 0, CardSettings::read( $post->ID )['enable_qr'] );
	}

	/**
	 * An invalid nonce prevents any write.
	 *
	 * @return void
	 */
	public function test_save_requires_valid_nonce(): void {
		$post     = $this->card( 12 );
		$settings = $this->settings();

		$GLOBALS['my_ecard_test_nonce_valid'] = false;

		$_POST[ CardSettings::NONCE_NAME ] = 'bad-nonce';
		$_POST[ CardSettings::FIELD ]      = array( 'theme' => 'valid' );

		$settings->save( $post->ID, $post );

		$this->assert_false( metadata_exists( 'post', $post->ID, '_my_ecard_theme' ) );
	}

	/**
	 * A user without edit_post cannot save.
	 *
	 * @return void
	 */
	public function test_save_requires_capability(): void {
		$post     = $this->card( 13 );
		$settings = $this->settings();

		$GLOBALS['my_ecard_test_can_edit'] = false;

		$_POST[ CardSettings::NONCE_NAME ] = 'stub-nonce';
		$_POST[ CardSettings::FIELD ]      = array( 'theme' => 'valid' );

		$settings->save( $post->ID, $post );

		$this->assert_false( metadata_exists( 'post', $post->ID, '_my_ecard_theme' ) );
	}

	/**
	 * Posts of another type are ignored.
	 *
	 * @return void
	 */
	public function test_save_ignores_other_post_types(): void {
		$post = new WP_Post(
			array(
				'ID'        => 14,
				'post_type' => 'post',
			)
		);

		$GLOBALS['my_ecard_test_posts'][14] = $post;

		$_POST[ CardSettings::NONCE_NAME ] = 'stub-nonce';
		$_POST[ CardSettings::FIELD ]      = array( 'theme' => 'valid' );

		$this->settings()->save( 14, $post );

		$this->assert_false( metadata_exists( 'post', 14, '_my_ecard_theme' ) );
	}

	/**
	 * The selector lists the bundled themes with previews and selection state.
	 *
	 * @return void
	 */
	public function test_render_theme_selector_lists_registered_themes(): void {
		$post = $this->card( 20 );

		update_post_meta( $post->ID, '_my_ecard_theme', 'reference' );

		$settings = new CardSettings(
			new ThemeRegistry( array( MY_ECARD_PATH . 'themes' ), MY_ECARD_VERSION )
		);

		ob_start();
		$settings->render( $post );
		$html = (string) ob_get_clean();

		$this->assert_contains( 'my-ecard-theme-selector', $html );
		$this->assert_contains( 'my-ecard-theme-card__name', $html );
		$this->assert_contains( 'Reference', $html );
		$this->assert_contains(
			'Light card with a panoramic cover, optional overlapping avatar, branded contacts and a social grid. Gallery and video content opens from the action bar viewers, never inside the card.',
			$html
		);
		$this->assert_same( 1, preg_match( '/value="reference"[^>]*checked/', $html ), 'The Reference radio should be checked.' );
		$this->assert_contains( 'value=""', $html );
		$this->assert_contains( 'my-ecard-theme-card__badge', $html );
		$this->assert_same( 1, preg_match( '/<img[^>]*themes\/reference\/preview\.png/', $html ), 'The Reference preview should be rendered.' );
	}

	/**
	 * A stored theme that no longer exists triggers a warning and falls back.
	 *
	 * @return void
	 */
	public function test_render_theme_selector_warns_when_stored_theme_is_missing(): void {
		$post = $this->card( 21 );

		update_post_meta( $post->ID, '_my_ecard_theme', 'ghost' );

		$settings = new CardSettings(
			new ThemeRegistry( array( MY_ECARD_PATH . 'themes' ), MY_ECARD_VERSION )
		);

		ob_start();
		$settings->render( $post );
		$html = (string) ob_get_clean();

		$this->assert_contains( 'my-ecard-theme-warning', $html );
		$this->assert_contains( 'The theme saved for this card is no longer available.', $html );
		$this->assert_same( 1, preg_match( '/value=""[^>]*checked/', $html ), 'The default radio should be checked.' );
		$this->assert_same( 0, preg_match( '/value="ghost"/', $html ), 'The missing theme must not be listed.' );
	}

	/**
	 * Without stored meta the default option is selected and no warning shows.
	 *
	 * @return void
	 */
	public function test_render_theme_selector_default_is_checked_without_a_theme(): void {
		$post = $this->card( 22 );

		$settings = new CardSettings(
			new ThemeRegistry( array( MY_ECARD_PATH . 'themes' ), MY_ECARD_VERSION )
		);

		ob_start();
		$settings->render( $post );
		$html = (string) ob_get_clean();

		$this->assert_same( 1, preg_match( '/value=""[^>]*checked/', $html ), 'The default radio should be checked.' );
		$this->assert_false( strpos( $html, 'my-ecard-theme-warning' ), 'No warning should appear without a stored theme.' );
	}

	/**
	 * The meta box explains that QR rendering needs an optional dependency.
	 *
	 * @return void
	 */
	public function test_render_theme_selector_shows_qr_availability_notice(): void {
		$post = $this->card( 23 );

		$settings = new CardSettings(
			new ThemeRegistry( array( MY_ECARD_PATH . 'themes' ), MY_ECARD_VERSION )
		);

		ob_start();
		$settings->render( $post );
		$html = (string) ob_get_clean();

		if ( QRCodeGenerator::is_available() ) {
			$this->assert_true( false === strpos( $html, 'QR rendering is unavailable' ) );

			return;
		}

		if ( 'gd' === QRCodeGenerator::unavailable_reason() ) {
			$this->assert_contains( 'the PHP GD extension is not enabled', $html );
		} else {
			$this->assert_contains( 'QR rendering is unavailable until the optional composer dependency is installed.', $html );
		}
	}

	/**
	 * Gallery and video toggles document the theme section they depend on.
	 *
	 * @return void
	 */
	public function test_render_theme_selector_describes_gallery_and_videos_requirements(): void {
		$post = $this->card( 24 );

		$settings = new CardSettings(
			new ThemeRegistry( array( MY_ECARD_PATH . 'themes' ), MY_ECARD_VERSION )
		);

		ob_start();
		$settings->render( $post );
		$html = (string) ob_get_clean();

		$this->assert_contains( 'my-ecard-gallery', $html );
		$this->assert_contains( 'my-ecard-videos', $html );
	}
}
