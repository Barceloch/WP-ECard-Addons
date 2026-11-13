<?php
/**
 * CardData normalization tests.
 *
 * @package MyECardExtensions
 */

namespace MyECardExtensions\Tests\Unit;

use MyECardExtensions\CardData;
use MyECardExtensions\Tests\TestCase;
use WP_Post;

/**
 * Covers the single normalization layer for ECard metadata.
 */
final class CardDataTest extends TestCase {

	/**
	 * Register a stubbed ECard card.
	 *
	 * @param int   $post_id Post ID.
	 * @param array $props   Extra post properties.
	 * @return WP_Post
	 */
	private function card( int $post_id = 20, array $props = array() ): WP_Post {
		$post = new WP_Post(
			array_merge(
				array(
					'ID'            => $post_id,
					'post_type'     => 'ecard_digital_card',
					'post_title'    => 'Jane Doe',
					'post_name'     => 'jane-doe',
					'post_status'   => 'publish',
					'post_password' => '',
					'post_content'  => '<p>Welcome body</p>',
				),
				$props
			)
		);

		$GLOBALS['my_ecard_test_posts'][ $post_id ] = $post;

		return $post;
	}

	/**
	 * Non-ECard posts are rejected.
	 *
	 * @return void
	 */
	public function test_non_ecard_post_returns_null(): void {
		$post = new WP_Post(
			array(
				'ID'        => 21,
				'post_type' => 'page',
			)
		);

		$GLOBALS['my_ecard_test_posts'][21] = $post;

		$this->assert_null( CardData::from_post( 21 ) );
		$this->assert_null( CardData::from_post( 999 ) );
	}

	/**
	 * Identity and scalar fields are read and trimmed.
	 *
	 * @return void
	 */
	public function test_identity_fields(): void {
		$post = $this->card();

		update_post_meta( $post->ID, '_ecard_designation', '  Architect  ' );
		update_post_meta( $post->ID, '_ecard_company_name', 'ACME' );
		update_post_meta( $post->ID, '_ecard_address', 'Main Street 1' );

		$data = CardData::from_post( $post->ID );

		$this->assert_not_null( $data );
		$this->assert_same( 20, $data->id );
		$this->assert_same( 'Jane Doe', $data->title );
		$this->assert_same( 'https://example.test/ecard/jane-doe/', $data->url );
		$this->assert_same( 'Architect', $data->designation );
		$this->assert_same( 'ACME', $data->company );
		$this->assert_same( 'Main Street 1', $data->address );
		$this->assert_same( '<p>Welcome body</p>', $data->welcome_html );
	}

	/**
	 * Invalid emails are dropped and valid ones kept.
	 *
	 * @return void
	 */
	public function test_email_is_validated(): void {
		$post = $this->card( 22 );

		update_post_meta( $post->ID, '_ecard_email', 'jane@example.com' );
		$this->assert_same( 'jane@example.com', CardData::from_post( $post->ID )->email );

		update_post_meta( $post->ID, '_ecard_email', 'not-an-email' );
		$this->assert_same( '', CardData::from_post( $post->ID )->email );
	}

	/**
	 * Phone and WhatsApp numbers produce safe links.
	 *
	 * @return void
	 */
	public function test_phone_and_whatsapp(): void {
		$post = $this->card( 23 );

		update_post_meta( $post->ID, '_ecard_phone', '+34 600 123 456' );
		update_post_meta( $post->ID, '_ecard_whatsapp', '+34 600 123 456' );

		$data = CardData::from_post( $post->ID );

		$this->assert_same( '+34600123456', $data->phone );
		$this->assert_same( 'tel:+34600123456', $data->phone_url );
		$this->assert_same( '+34600123456', $data->whatsapp );
		$this->assert_same( 'https://wa.me/34600123456', $data->whatsapp_url );
	}

	/**
	 * Empty contact data produces empty links.
	 *
	 * @return void
	 */
	public function test_missing_contact_data_produces_empty_links(): void {
		$post = $this->card( 24 );
		$data = CardData::from_post( $post->ID );

		$this->assert_same( '', $data->phone );
		$this->assert_same( '', $data->phone_url );
		$this->assert_same( '', $data->whatsapp_url );
		$this->assert_same( '', $data->email );
	}

	/**
	 * Website and location URLs must pass the scheme allowlist.
	 *
	 * @return void
	 */
	public function test_urls_are_validated(): void {
		$post = $this->card( 25 );

		update_post_meta( $post->ID, '_ecard_website', 'https://example.com' );
		update_post_meta( $post->ID, '_ecard_location', 'javascript:alert(1)' );

		$data = CardData::from_post( $post->ID );

		$this->assert_same( 'https://example.com', $data->website );
		$this->assert_same( '', $data->location_url );
	}

	/**
	 * Avatar and logo URLs are validated.
	 *
	 * @return void
	 */
	public function test_avatar_and_logo(): void {
		$post = $this->card( 26 );

		update_post_meta( $post->ID, '_ecard_person_photo', 'https://example.test/avatar.jpg' );
		update_post_meta( $post->ID, '_ecard_company_logo', 'data:text/html;base64,AAAA' );

		$data = CardData::from_post( $post->ID );

		$this->assert_same( 'https://example.test/avatar.jpg', $data->avatar_url );
		$this->assert_same( '', $data->logo_url );
	}

	/**
	 * Map embeds come from the ECard iframe field.
	 *
	 * @return void
	 */
	public function test_map_embed_url(): void {
		$post = $this->card( 27 );

		update_post_meta(
			$post->ID,
			'_ecard_map_iframe',
			'<iframe src="https://www.google.com/maps/embed?pb=abc"></iframe>'
		);

		$this->assert_same(
			'https://www.google.com/maps/embed?pb=abc',
			CardData::from_post( $post->ID )->map_embed_url
		);
	}

	/**
	 * Social links keep known networks with valid URLs only.
	 *
	 * @return void
	 */
	public function test_social_links_allowlist(): void {
		$post = $this->card( 28 );

		update_post_meta(
			$post->ID,
			'_ecard_social_links',
			array(
				'facebook'  => 'https://facebook.com/jane',
				'linkedin'  => 'https://linkedin.com/in/jane',
				'instagram' => 'javascript:alert(1)',
				'tiktok'    => 'https://tiktok.com/@jane',
			)
		);

		$social = CardData::from_post( $post->ID )->social;

		$this->assert_count( 2, $social );
		$this->assert_same( 'facebook', $social[0]['platform'] );
		$this->assert_same( 'Facebook', $social[0]['label'] );
		$this->assert_same( 'linkedin', $social[1]['platform'] );
	}

	/**
	 * Gallery items are unique, resolvable and ordered.
	 *
	 * @return void
	 */
	public function test_gallery_dedupes_and_resolves(): void {
		$post = $this->card( 29 );

		$GLOBALS['my_ecard_test_attachments'][31] = array(
			'full'   => 'https://example.test/gallery-31-full.jpg',
			'medium' => 'https://example.test/gallery-31-medium.jpg',
			'title'  => 'Photo 31',
		);

		update_post_meta( $post->ID, '_ecard_gallery', array( 31, '31', 32, 0, 'abc' ) );

		$gallery = CardData::from_post( $post->ID )->gallery;

		$this->assert_count( 1, $gallery );
		$this->assert_same( 31, $gallery[0]['id'] );
		$this->assert_same( 'https://example.test/gallery-31-medium.jpg', $gallery[0]['thumb'] );
		$this->assert_same( 'Photo 31', $gallery[0]['alt'] );
	}

	/**
	 * Video entries require a valid URL and detect the provider.
	 *
	 * @return void
	 */
	public function test_videos_are_normalized(): void {
		$post = $this->card( 30 );

		update_post_meta(
			$post->ID,
			'_ecard_videos',
			array(
				array(
					'title' => '  <b>Intro</b> ',
					'url'   => 'https://www.youtube.com/watch?v=abc',
				),
				array(
					'title' => 'Broken',
					'url'   => 'javascript:alert(1)',
				),
				array( 'title' => 'No URL' ),
				'not-an-array',
			)
		);

		$videos = CardData::from_post( $post->ID )->videos;

		$this->assert_count( 1, $videos );
		$this->assert_same( 'Intro', $videos[0]['title'] );
		$this->assert_same( 'youtube', $videos[0]['provider'] );
	}

	/**
	 * Services require a name and keep sanitized descriptions.
	 *
	 * @return void
	 */
	public function test_services_require_name(): void {
		$post = $this->card( 31 );

		update_post_meta(
			$post->ID,
			'_ecard_services',
			array(
				array(
					'name'        => ' Consulting ',
					'description' => '<p>Details</p>',
				),
				array(
					'name'        => '',
					'description' => 'No name',
				),
				'not-an-array',
			)
		);

		$services = CardData::from_post( $post->ID )->services;

		$this->assert_count( 1, $services );
		$this->assert_same( 'Consulting', $services[0]['name'] );
		$this->assert_same( '<p>Details</p>', $services[0]['html'] );
	}

	/**
	 * Section headings use ECard values and translated fallbacks.
	 *
	 * @return void
	 */
	public function test_headings(): void {
		$post = $this->card( 32 );

		$defaults = CardData::from_post( $post->ID )->headings;

		$this->assert_same( 'Welcome', $defaults['welcome'] );
		$this->assert_same( 'Services', $defaults['services'] );
		$this->assert_same( 'Videos', $defaults['videos'] );

		update_post_meta( $post->ID, '_ecard_welcome_heading', 'Hello there' );

		$this->assert_same( 'Hello there', CardData::from_post( $post->ID )->headings['welcome'] );
	}

	/**
	 * Addon settings and the cover image are available on the card.
	 *
	 * @return void
	 */
	public function test_settings_and_cover(): void {
		$post = $this->card( 33 );

		$GLOBALS['my_ecard_test_attachments'][41] = array(
			'full'   => 'https://example.test/cover.jpg',
			'medium' => 'https://example.test/cover-medium.jpg',
			'title'  => 'Cover',
		);

		update_post_meta( $post->ID, '_my_ecard_cover_image', 41 );
		update_post_meta( $post->ID, '_my_ecard_header_height', 320 );

		$data = CardData::from_post( $post->ID );

		$this->assert_same( 41, $data->cover_id );
		$this->assert_same( 'https://example.test/cover.jpg', $data->cover_url );
		$this->assert_same( 320, $data->settings['header_height'] );
		$this->assert_same( '', $data->settings['theme'] );
	}

	/**
	 * Visibility flags reflect post status and password protection.
	 *
	 * @return void
	 */
	public function test_visibility_flags(): void {
		$public = $this->card( 34 );

		$data = CardData::from_post( $public->ID );

		$this->assert_true( $data->is_publicly_viewable );
		$this->assert_false( $data->requires_password );

		$protected = $this->card(
			35,
			array(
				'post_status'   => 'publish',
				'post_password' => 'secret',
			)
		);

		$protected_data = CardData::from_post( $protected->ID );

		/*
		 * WordPress treats a published, password-protected post as publicly
		 * viewable; the password gate lives in post_password_required().
		 */
		$this->assert_true( $protected_data->is_publicly_viewable );
		$this->assert_true( $protected_data->requires_password );

		$draft = $this->card( 36, array( 'post_status' => 'draft' ) );

		$this->assert_false( CardData::from_post( $draft->ID )->is_publicly_viewable );
	}
}
