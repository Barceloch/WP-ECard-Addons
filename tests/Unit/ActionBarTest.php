<?php
/**
 * Shared action bar tests.
 *
 * @package MyECardExtensions
 */

namespace MyECardExtensions\Tests\Unit;

use MyECardExtensions\ActionBar;
use MyECardExtensions\CardData;
use MyECardExtensions\QRCodeGenerator;
use MyECardExtensions\Tests\TestCase;
use WP_Post;

/**
 * Covers action availability, ordering, markup, escaping and settings.
 */
final class ActionBarTest extends TestCase {

	/**
	 * Shared card ID.
	 */
	const CARD_ID = 90;

	/**
	 * Create the card and its metadata.
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
					'post_content'  => '',
				),
				$props
			)
		);

		$GLOBALS['my_ecard_test_posts'][ self::CARD_ID ] = $post;
		$GLOBALS['my_ecard_test_attachments'][900]      = array(
			'full'   => 'https://cdn.example.test/gallery-1.jpg',
			'medium' => 'https://cdn.example.test/gallery-1-medium.jpg',
		);

		return $post;
	}

	/**
	 * Assign the full public card configuration.
	 *
	 * @param int   $post_id   Post ID.
	 * @param array $overrides Meta key => value overrides.
	 * @return void
	 */
	private function configure( int $post_id, array $overrides = array() ): void {
		$values = array_merge(
			array(
				'_ecard_email'      => 'alex@example.com',
				'_ecard_phone'      => '+34 600 111 222',
				'_ecard_location'   => 'https://maps.google.com/?q=Madrid',
				'_ecard_gallery'    => array( 900 ),
				'_ecard_videos'     => array(
					array(
						'title' => 'Intro',
						'url'   => 'https://youtu.be/abc',
					),
				),
				'_my_ecard_enable_vcard'    => 1,
				'_my_ecard_enable_location' => 1,
				'_my_ecard_enable_qr'       => 1,
				'_my_ecard_enable_share'    => 1,
				'_my_ecard_enable_gallery'  => 1,
				'_my_ecard_enable_videos'   => 1,
			),
			$overrides
		);

		foreach ( $values as $key => $value ) {
			update_post_meta( $post_id, $key, $value );
		}
	}

	/**
	 * Build the normalized card for the current metadata.
	 *
	 * @param int $post_id Post ID.
	 * @return CardData
	 */
	private function data( int $post_id ): CardData {
		$card = CardData::from_post( $post_id );

		$this->assert_not_null( $card );

		return $card;
	}

	/**
	 * Render the bar and return its markup.
	 *
	 * @param CardData   $card    Card read model.
	 * @param array|null $actions Optional prebuilt actions.
	 * @param array      $anchors Anchor ids present in the theme.
	 * @return string
	 */
	private function render( CardData $card, ?array $actions = null, array $anchors = array() ): string {
		ob_start();
		ActionBar::render( $card, $actions, $anchors );

		return (string) ob_get_clean();
	}

	/**
	 * Force QR availability without loading the missing library.
	 *
	 * @return void
	 */
	private function enable_qr(): void {
		add_filter(
			'my_ecard_qr_available',
			static function () {
				return true;
			}
		);
	}

	/**
	 * A full public card exposes every data-driven action.
	 *
	 * @return void
	 */
	public function test_full_public_card_exposes_data_driven_actions(): void {
		$this->card();
		$this->configure( self::CARD_ID );

		$actions  = ActionBar::actions( $this->data( self::CARD_ID ) );
		$expected = array( 'vcard', 'location' );

		if ( QRCodeGenerator::is_available() ) {
			$expected[] = 'qr';
		}

		$expected = array_merge( $expected, array( 'share', 'gallery', 'videos' ) );

		$this->assert_same( $expected, array_keys( $actions ) );
		$this->assert_count( count( $expected ), $actions );
	}

	/**
	 * The QR filter adds the QR action in the documented position.
	 *
	 * @return void
	 */
	public function test_qr_filter_adds_action_in_documented_order(): void {
		$this->card();
		$this->configure( self::CARD_ID );
		$this->enable_qr();

		$actions = ActionBar::actions( $this->data( self::CARD_ID ) );

		$this->assert_same( array( 'vcard', 'location', 'qr', 'share', 'gallery', 'videos' ), array_keys( $actions ) );
		$this->assert_same( 'button', $actions['qr']['type'] );
	}

	/**
	 * Gallery and videos are viewer buttons, not anchor links.
	 *
	 * @return void
	 */
	public function test_gallery_and_videos_are_viewer_buttons(): void {
		$this->card();
		$this->configure( self::CARD_ID );

		$anchors = array(
			'gallery' => true,
			'videos'  => true,
		);

		$actions = ActionBar::actions( $this->data( self::CARD_ID ), $anchors );

		$this->assert_same( 'button', $actions['gallery']['type'] );
		$this->assert_same( 'button', $actions['videos']['type'] );
		$this->assert_true( isset( $actions['gallery']['attributes']['data-my-ecard-gallery-open'] ) );
		$this->assert_true( isset( $actions['videos']['attributes']['data-my-ecard-videos-open'] ) );
		$this->assert_same( 'my-ecard-gallery-viewer', $actions['gallery']['attributes']['aria-controls'] );
		$this->assert_same( 'my-ecard-videos-viewer', $actions['videos']['attributes']['aria-controls'] );
	}

	/**
	 * The viewers are shell-owned, so theme anchors no longer gate them.
	 *
	 * @return void
	 */
	public function test_gallery_and_videos_available_without_theme_anchors(): void {
		$this->card();
		$this->configure( self::CARD_ID );

		$card    = $this->data( self::CARD_ID );
		$actions = ActionBar::actions( $card );

		$this->assert_true( ! empty( $card->gallery ) );
		$this->assert_true( ! empty( $card->videos ) );
		$this->assert_true( isset( $actions['gallery'] ) );
		$this->assert_true( isset( $actions['videos'] ) );
		$this->assert_count( QRCodeGenerator::is_available() ? 6 : 5, $actions );
	}

	/**
	 * Each disabled setting removes exactly its own action.
	 *
	 * @return void
	 */
	public function test_disabled_settings_remove_actions(): void {
		$this->card();
		$this->configure( self::CARD_ID );
		$this->enable_qr();

		$anchors = array(
			'gallery' => true,
			'videos'  => true,
		);

		$map = array(
			'enable_vcard'    => 'vcard',
			'enable_location' => 'location',
			'enable_share'    => 'share',
			'enable_gallery'  => 'gallery',
			'enable_videos'   => 'videos',
			'enable_qr'       => 'qr',
		);

		$baseline = ActionBar::actions( $this->data( self::CARD_ID ), $anchors );

		$this->assert_count( 6, $baseline );

		foreach ( $map as $setting => $action ) {
			$this->configure( self::CARD_ID, array( '_my_ecard_' . $setting => 0 ) );

			$actions = ActionBar::actions( $this->data( self::CARD_ID ), $anchors );

			$this->assert_false( isset( $actions[ $action ] ), 'Disabled ' . $setting . ' still lists ' . $action . '.' );
			$this->assert_count( 5, $actions );
		}
	}

	/**
	 * Missing card data removes actions even when the feature is enabled.
	 *
	 * @return void
	 */
	public function test_missing_data_removes_actions(): void {
		$this->card();

		$anchors = array(
			'gallery' => true,
			'videos'  => true,
		);

		$this->configure(
			self::CARD_ID,
			array(
				'_ecard_location' => '',
				'_ecard_gallery'  => array(),
				'_ecard_videos'   => array(),
			)
		);

		$actions  = ActionBar::actions( $this->data( self::CARD_ID ), $anchors );
		$expected = array( 'vcard' );

		if ( QRCodeGenerator::is_available() ) {
			$expected[] = 'qr';
		}

		$expected[] = 'share';

		$this->assert_same( $expected, array_keys( $actions ) );
	}

	/**
	 * A card that is not public exposes no actions and renders nothing.
	 *
	 * @return void
	 */
	public function test_non_public_cards_have_no_actions_or_markup(): void {
		$this->card( array( 'post_status' => 'draft' ) );
		$this->configure( self::CARD_ID );

		$draft = $this->data( self::CARD_ID );

		$this->assert_false( $draft->is_publicly_viewable );
		$this->assert_same( array(), ActionBar::actions( $draft ) );
		$this->assert_same( '', $this->render( $draft ) );

		$this->card( array( 'post_password' => 'secret' ) );
		$this->configure( self::CARD_ID );

		$protected = $this->data( self::CARD_ID );

		$this->assert_true( $protected->is_publicly_viewable );
		$this->assert_true( $protected->requires_password );

		/*
		 * Actions are computed per action, so a card whose password was not
		 * satisfied still knows which actions it *has*. Markup is withheld by
		 * the presentation guard, and URL-dependent actions stay absent.
		 */
		$protected_actions = ActionBar::actions( $protected );

		$this->assert_key( 'gallery', $protected_actions );
		$this->assert_key( 'videos', $protected_actions );
		$this->assert_false( isset( $protected_actions['vcard'] ) );
		$this->assert_false( isset( $protected_actions['share'] ) );
		$this->assert_false( isset( $protected_actions['qr'] ) );
		$this->assert_same( '', $this->render( $protected ) );
	}

	/**
	 * Disabling the bar suppresses the markup entirely.
	 *
	 * @return void
	 */
	public function test_show_bar_disabled_renders_nothing(): void {
		$this->card();
		$this->configure( self::CARD_ID, array( '_my_ecard_show_bar' => 0 ) );

		$card = $this->data( self::CARD_ID );

		$this->assert_false( $card->settings['show_bar'] );
		$this->assert_same( '', $this->render( $card ) );
		$this->assert_same( '', $this->render( $card, array( 'vcard' => array( 'label' => 'x' ) ) ) );
	}

	/**
	 * The bar markup exposes accessible, identifiable controls.
	 *
	 * @return void
	 */
	public function test_render_markup(): void {
		$this->card();
		$this->configure( self::CARD_ID );

		$html = $this->render( $this->data( self::CARD_ID ) );

		$this->assert_contains( '<nav', $html );
		$this->assert_contains( 'data-my-ecard-action-bar', $html );
		$this->assert_contains( 'aria-label="Card actions"', $html );

		$this->assert_contains( 'data-my-ecard-action="vcard"', $html );
		$this->assert_contains( 'my_ecard_action=vcard', $html );
		$this->assert_same(
			1,
			(int) preg_match( '/<a\b[^>]*href="[^"]*my_ecard_action=vcard[^"]*"[^>]*data-my-ecard-action="vcard"/', $html )
		);

		$this->assert_same(
			1,
			(int) preg_match( '/<button\b[^>]*data-my-ecard-action="share"/', $html )
		);
		$this->assert_contains( 'data-my-ecard-share-url', $html );

		$this->assert_same(
			1,
			(int) preg_match( '/<button\b[^>]*data-my-ecard-action="gallery"/', $html )
		);
		$this->assert_same(
			1,
			(int) preg_match( '/<button\b[^>]*data-my-ecard-action="videos"/', $html )
		);
		$this->assert_contains( 'data-my-ecard-gallery-open', $html );
		$this->assert_contains( 'data-my-ecard-videos-open', $html );
		$this->assert_contains( 'aria-controls="my-ecard-gallery-viewer"', $html );
		$this->assert_contains( 'aria-controls="my-ecard-videos-viewer"', $html );

		$expected = QRCodeGenerator::is_available() ? 6 : 5;

		$this->assert_same(
			$expected,
			(int) preg_match_all( '/<(?:a|button)\b[^>]*data-my-ecard-action="[^"]+"[^>]*>/', $html )
		);
		$this->assert_same( $expected, (int) preg_match_all( '/<(?:a|button)\b[^>]*aria-label="[^"]+"/', $html ) );

		$this->assert_same( $expected, substr_count( $html, 'aria-hidden="true"' ) );
		$this->assert_same( $expected, (int) preg_match_all( '/<i\b[^>]*aria-hidden="true"/', $html ) );
	}

	/**
	 * URLs are escaped inside attributes.
	 *
	 * The permalink carries an ampersand, so the share button data attribute
	 * must entity-encode it. vCard is disabled to keep every attribute value
	 * meaningful under the esc_url() stub, which does not entity-encode.
	 *
	 * @return void
	 */
	public function test_render_escapes_attribute_values(): void {
		$this->card( array( 'post_name' => 'alex&rivera' ) );
		$this->configure( self::CARD_ID, array( '_my_ecard_enable_vcard' => 0 ) );

		$html = $this->render( $this->data( self::CARD_ID ) );

		$this->assert_contains( 'data-my-ecard-share-url="https://example.test/ecard/alex&amp;rivera/"', $html );
		$this->assert_false( strpos( $html, 'alex&rivera' ) );

		$this->assert_same(
			substr_count( $html, '="' ),
			(int) preg_match_all( '/="[^"<>]*"/', $html ),
			'Every attribute value must be free of raw quotes and angle brackets.'
		);

		$count = preg_match_all( '/="([^"]*)"/', $html, $matches );

		$this->assert_true( $count > 0 );

		foreach ( $matches[1] as $value ) {
			$this->assert_false(
				preg_match( '/&(?!(?:amp|lt|gt|quot|#\d+|#x[0-9a-fA-F]+);)/', $value ),
				'Raw "&" found in attribute value: ' . $value
			);
		}
	}

	/**
	 * Position and label settings are reflected on the nav element.
	 *
	 * @return void
	 */
	public function test_position_and_label_settings(): void {
		$this->card();
		$this->configure(
			self::CARD_ID,
			array(
				'_my_ecard_bar_position' => 'sticky',
				'_my_ecard_bar_labels'   => 'hide_mobile',
			)
		);

		$html = $this->render( $this->data( self::CARD_ID ) );

		$this->assert_contains( 'data-my-ecard-bar-position="sticky"', $html );
		$this->assert_contains( 'my-ecard-action-bar--sticky', $html );
		$this->assert_contains( 'data-my-ecard-bar-labels="hide_mobile"', $html );
	}

	/**
	 * Only a valid accent color produces the inline custom property.
	 *
	 * @return void
	 */
	public function test_accent_color_is_validated(): void {
		$this->card();
		$this->configure( self::CARD_ID, array( '_my_ecard_accent_color' => '#123456' ) );

		$html = $this->render( $this->data( self::CARD_ID ) );

		$this->assert_contains( '--my-ecard-action-bar-accent:#123456', $html );

		$this->card();
		$this->configure( self::CARD_ID, array( '_my_ecard_accent_color' => 'red' ) );

		$invalid = $this->data( self::CARD_ID );
		$html    = $this->render( $invalid );

		$this->assert_same( '', $invalid->settings['accent_color'] );
		$this->assert_false( strpos( $html, '--my-ecard-action-bar-accent' ) );
	}

	/**
	 * The live status region exists only while sharing is available.
	 *
	 * @return void
	 */
	public function test_share_status_element(): void {
		$this->card();
		$this->configure( self::CARD_ID );

		$html = $this->render( $this->data( self::CARD_ID ) );

		$this->assert_contains( 'data-my-ecard-share-status', $html );
		$this->assert_contains( 'role="status"', $html );
		$this->assert_contains( 'aria-live="polite"', $html );
		$this->assert_same(
			1,
			(int) preg_match( '/<span\b[^>]*data-my-ecard-share-status[^>]*role="status"[^>]*aria-live="polite"/', $html )
		);

		$this->configure( self::CARD_ID, array( '_my_ecard_enable_share' => 0 ) );

		$html = $this->render( $this->data( self::CARD_ID ) );

		$this->assert_false( strpos( $html, 'data-my-ecard-share-status' ) );
		$this->assert_false( strpos( $html, 'role="status"' ) );
	}

	/**
	 * An explicit empty action list renders nothing.
	 *
	 * @return void
	 */
	public function test_explicit_empty_actions_render_nothing(): void {
		$this->card();
		$this->configure( self::CARD_ID );

		$card = $this->data( self::CARD_ID );

		$this->assert_true( ! empty( ActionBar::actions( $card ) ) );
		$this->assert_same( '', $this->render( $card, array() ) );
	}
}
