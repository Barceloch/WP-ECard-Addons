<?php
/**
 * Action bar availability matrix.
 *
 * @package MyECardExtensions
 */

namespace MyECardExtensions\Tests\Unit;

use MyECardExtensions\ActionBar;
use MyECardExtensions\CardData;
use MyECardExtensions\Tests\TestCase;
use WP_Post;

/**
 * Proves that each action is gated only by its own requirements.
 *
 * A missing gallery, a missing video, a disabled action or an unavailable QR
 * renderer must never hide the remaining actions, and media availability must
 * never depend on the theme rendering sections.
 */
final class ActionBarMatrixTest extends TestCase {

	/**
	 * Shared card ID.
	 */
	const CARD_ID = 140;

	/**
	 * Create the card under test.
	 *
	 * @return void
	 */
	private function card(): void {
		$GLOBALS['my_ecard_test_posts'][ self::CARD_ID ] = new WP_Post(
			array(
				'ID'            => self::CARD_ID,
				'post_type'     => 'ecard_digital_card',
				'post_title'    => 'Matrix Card',
				'post_name'     => 'matrix-card',
				'post_status'   => 'publish',
				'post_password' => '',
				'post_content'  => '',
			)
		);

		$GLOBALS['my_ecard_test_attachments'][910] = array(
			'full'   => 'https://cdn.example.test/matrix-1.jpg',
			'medium' => 'https://cdn.example.test/matrix-1-medium.jpg',
		);

		update_post_meta( self::CARD_ID, '_ecard_email', 'matrix@example.com' );
		update_post_meta( self::CARD_ID, '_ecard_phone', '+34 600 000 000' );
		update_post_meta( self::CARD_ID, '_ecard_location', 'https://maps.google.com/?q=Madrid' );
	}

	/**
	 * Apply per-card settings and media.
	 *
	 * @param array $meta Meta overrides.
	 * @return CardData
	 */
	private function data( array $meta = array() ): CardData {
		$base = array(
			'_my_ecard_show_bar'        => 1,
			'_my_ecard_enable_vcard'    => 1,
			'_my_ecard_enable_location' => 1,
			'_my_ecard_enable_qr'       => 1,
			'_my_ecard_enable_share'    => 1,
			'_my_ecard_enable_gallery'  => 1,
			'_my_ecard_enable_videos'   => 1,
		);

		foreach ( array_merge( $base, $meta ) as $key => $value ) {
			update_post_meta( self::CARD_ID, $key, $value );
		}

		$card = CardData::from_post( self::CARD_ID );

		$this->assert_not_null( $card );

		return $card;
	}

	/**
	 * Action keys for a card.
	 *
	 * @param CardData $card Card.
	 * @return string[]
	 */
	private function keys( CardData $card ): array {
		return array_keys( ActionBar::actions( $card ) );
	}

	/**
	 * Render the bar.
	 *
	 * @param CardData $card Card.
	 * @return string
	 */
	private function render( CardData $card ): string {
		ob_start();
		ActionBar::render( $card );

		return (string) ob_get_clean();
	}

	/**
	 * Bar enabled with no media still shows the non-media actions.
	 *
	 * @return void
	 */
	public function test_bar_without_media_keeps_non_media_actions(): void {
		$this->card();
		$card = $this->data();

		$keys = $this->keys( $card );

		$this->assert_same( array( 'vcard', 'location', 'qr', 'share' ), $keys );

		$html = $this->render( $card );

		$this->assert_contains( 'data-my-ecard-action-bar', $html );
		$this->assert_contains( 'data-my-ecard-action="vcard"', $html );
		$this->assert_contains( 'data-my-ecard-action="share"', $html );
		$this->assert_false( strpos( $html, 'data-my-ecard-action="gallery"' ) );
		$this->assert_false( strpos( $html, 'data-my-ecard-action="videos"' ) );
	}

	/**
	 * Gallery alone does not remove the other actions.
	 *
	 * @return void
	 */
	public function test_gallery_alone_keeps_the_other_actions(): void {
		$this->card();
		$card = $this->data( array( '_ecard_gallery' => array( 910 ) ) );

		$keys = $this->keys( $card );

		$this->assert_key( 'gallery', array_flip( $keys ) );
		$this->assert_false( in_array( 'videos', $keys, true ) );
		$this->assert_key( 'vcard', array_flip( $keys ) );
		$this->assert_key( 'share', array_flip( $keys ) );
	}

	/**
	 * Videos alone do not remove the other actions.
	 *
	 * @return void
	 */
	public function test_videos_alone_keeps_the_other_actions(): void {
		$this->card();
		$card = $this->data(
			array(
				'_ecard_videos' => array(
					array( 'title' => 'Intro', 'url' => 'https://youtu.be/abc' ),
				),
			)
		);

		$keys = $this->keys( $card );

		$this->assert_key( 'videos', array_flip( $keys ) );
		$this->assert_false( in_array( 'gallery', $keys, true ) );
		$this->assert_key( 'vcard', array_flip( $keys ) );
	}

	/**
	 * Both media types expose every configured action.
	 *
	 * @return void
	 */
	public function test_both_media_types_expose_every_action(): void {
		$this->card();
		$card = $this->data(
			array(
				'_ecard_gallery' => array( 910 ),
				'_ecard_videos'  => array(
					array( 'title' => 'Intro', 'url' => 'https://youtu.be/abc' ),
				),
			)
		);

		$this->assert_same(
			array( 'vcard', 'location', 'qr', 'share', 'gallery', 'videos' ),
			$this->keys( $card )
		);
	}

	/**
	 * Disabling one action hides only that action.
	 *
	 * @return void
	 */
	public function test_disabling_one_action_does_not_hide_the_rest(): void {
		$this->card();
		$card = $this->data( array( '_my_ecard_enable_vcard' => 0 ) );

		$keys = $this->keys( $card );

		$this->assert_false( in_array( 'vcard', $keys, true ) );
		$this->assert_key( 'location', array_flip( $keys ) );
		$this->assert_key( 'share', array_flip( $keys ) );
		$this->assert_key( 'qr', array_flip( $keys ) );
	}

	/**
	 * Without a location only the location action disappears.
	 *
	 * @return void
	 */
	public function test_missing_location_only_hides_location(): void {
		$this->card();
		delete_post_meta( self::CARD_ID, '_ecard_location' );

		$card = $this->data();
		$keys = $this->keys( $card );

		$this->assert_false( in_array( 'location', $keys, true ) );
		$this->assert_key( 'vcard', array_flip( $keys ) );
		$this->assert_key( 'share', array_flip( $keys ) );
	}

	/**
	 * An unavailable QR renderer hides only the QR action.
	 *
	 * @return void
	 */
	public function test_unavailable_qr_hides_only_qr(): void {
		$this->card();
		$card = $this->data();

		$disable = static function () {
			return false;
		};

		add_filter( 'my_ecard_qr_available', $disable );

		$keys = $this->keys( $card );

		$this->assert_false( in_array( 'qr', $keys, true ) );
		$this->assert_key( 'vcard', array_flip( $keys ) );
		$this->assert_key( 'location', array_flip( $keys ) );
		$this->assert_key( 'share', array_flip( $keys ) );
	}

	/**
	 * With the bar disabled nothing is rendered.
	 *
	 * @return void
	 */
	public function test_disabled_bar_renders_nothing(): void {
		$this->card();
		$card = $this->data( array( '_my_ecard_show_bar' => 0 ) );

		$this->assert_same( '', $this->render( $card ) );
	}

	/**
	 * Media availability never depends on section anchors.
	 *
	 * @return void
	 */
	public function test_media_actions_ignore_section_anchors(): void {
		$this->card();
		$card = $this->data(
			array(
				'_ecard_gallery' => array( 910 ),
				'_ecard_videos'  => array(
					array( 'title' => 'Intro', 'url' => 'https://youtu.be/abc' ),
				),
			)
		);

		$without_anchors = array_keys( ActionBar::actions( $card, array( 'gallery' => false, 'videos' => false ) ) );
		$with_anchors    = array_keys( ActionBar::actions( $card, array( 'gallery' => true, 'videos' => true ) ) );

		$this->assert_same( $with_anchors, $without_anchors );
		$this->assert_key( 'gallery', array_flip( $without_anchors ) );
		$this->assert_key( 'videos', array_flip( $without_anchors ) );
	}
}
