<?php
/**
 * Read model that normalizes ECard metadata for the addon.
 *
 * @package MyECardExtensions
 */

namespace MyECardExtensions;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Normalized, read-only view of one ECard card.
 *
 * No template should read ECard metadata directly: this class is the single
 * place where raw post meta becomes validated, predictable values.
 */
final class CardData {

	/**
	 * ECard post type.
	 */
	const POST_TYPE = 'ecard_digital_card';

	/**
	 * Supported social networks, mirroring ECard's fixed list.
	 *
	 * @var array
	 */
	const SOCIAL_NETWORKS = array(
		'facebook'  => 'Facebook',
		'instagram' => 'Instagram',
		'youtube'   => 'YouTube',
		'twitter'   => 'Twitter',
		'linkedin'  => 'LinkedIn',
	);

	/**
	 * Post ID.
	 *
	 * @var int
	 */
	public $id = 0;

	/**
	 * Card title (the person or business name).
	 *
	 * @var string
	 */
	public $title = '';

	/**
	 * Public permalink.
	 *
	 * @var string
	 */
	public $url = '';

	/**
	 * Designation or profession.
	 *
	 * @var string
	 */
	public $designation = '';

	/**
	 * Company name.
	 *
	 * @var string
	 */
	public $company = '';

	/**
	 * Free-text address.
	 *
	 * @var string
	 */
	public $address = '';

	/**
	 * Valid email address or an empty string.
	 *
	 * @var string
	 */
	public $email = '';

	/**
	 * Normalized phone number.
	 *
	 * @var string
	 */
	public $phone = '';

	/**
	 * tel: URL or an empty string.
	 *
	 * @var string
	 */
	public $phone_url = '';

	/**
	 * Normalized WhatsApp number.
	 *
	 * @var string
	 */
	public $whatsapp = '';

	/**
	 * wa.me URL or an empty string.
	 *
	 * @var string
	 */
	public $whatsapp_url = '';

	/**
	 * Website URL.
	 *
	 * @var string
	 */
	public $website = '';

	/**
	 * Google Maps location URL.
	 *
	 * @var string
	 */
	public $location_url = '';

	/**
	 * Validated Google Maps embed URL.
	 *
	 * @var string
	 */
	public $map_embed_url = '';

	/**
	 * Avatar URL (ECard person photo).
	 *
	 * @var string
	 */
	public $avatar_url = '';

	/**
	 * Company logo URL.
	 *
	 * @var string
	 */
	public $logo_url = '';

	/**
	 * Cover attachment ID.
	 *
	 * @var int
	 */
	public $cover_id = 0;

	/**
	 * Cover image URL.
	 *
	 * @var string
	 */
	public $cover_url = '';

	/**
	 * Normalized social links.
	 *
	 * @var array
	 */
	public $social = array();

	/**
	 * Normalized gallery items.
	 *
	 * @var array
	 */
	public $gallery = array();

	/**
	 * Normalized video items.
	 *
	 * @var array
	 */
	public $videos = array();

	/**
	 * Normalized service items.
	 *
	 * @var array
	 */
	public $services = array();

	/**
	 * Raw welcome content (unfiltered post_content).
	 *
	 * @var string
	 */
	public $welcome_html = '';

	/**
	 * Section headings with ECard values and addon defaults.
	 *
	 * @var array
	 */
	public $headings = array();

	/**
	 * Addon settings for this card.
	 *
	 * @var array
	 */
	public $settings = array();

	/**
	 * Whether anonymous visitors can view the card.
	 *
	 * @var bool
	 */
	public $is_publicly_viewable = false;

	/**
	 * Whether the card requires a password.
	 *
	 * @var bool
	 */
	public $requires_password = false;

	/**
	 * Private constructor: use from_post().
	 */
	private function __construct() {
	}

	/**
	 * Build the normalized view for a post.
	 *
	 * @param int $post_id Post ID.
	 * @return self|null Null when the post is not an ECard card.
	 */
	public static function from_post( int $post_id ): ?self {
		$post = get_post( $post_id );

		if ( ! $post instanceof \WP_Post || self::POST_TYPE !== $post->post_type ) {
			return null;
		}

		$data = new self();

		$data->id    = (int) $post->ID;
		$data->title = (string) get_the_title( $post );
		$data->url   = (string) get_permalink( $post );

		$data->designation = get_meta_string( $data->id, '_ecard_designation' );
		$data->company     = get_meta_string( $data->id, '_ecard_company_name' );
		$data->address     = get_meta_string( $data->id, '_ecard_address' );

		$email         = sanitize_email( get_meta_string( $data->id, '_ecard_email' ) );
		$data->email   = is_email( $email ) ? $email : '';

		$data->phone     = normalize_phone( get_meta_string( $data->id, '_ecard_phone' ) );
		$data->phone_url = '' === $data->phone ? '' : 'tel:' . $data->phone;

		$data->whatsapp     = normalize_phone( get_meta_string( $data->id, '_ecard_whatsapp' ) );
		$data->whatsapp_url = whatsapp_url( get_meta_string( $data->id, '_ecard_whatsapp' ) );

		$data->website      = safe_url( get_meta_string( $data->id, '_ecard_website' ) );
		$data->location_url = safe_url( get_meta_string( $data->id, '_ecard_location' ) );
		$data->map_embed_url = map_embed_url( get_meta_string( $data->id, '_ecard_map_iframe' ) );

		$data->avatar_url = safe_url( get_meta_string( $data->id, '_ecard_person_photo' ) );
		$data->logo_url   = safe_url( get_meta_string( $data->id, '_ecard_company_logo' ) );

		$data->settings = CardSettings::read( $data->id );
		$data->cover_id = (int) $data->settings['cover_image'];
		$cover_url      = $data->cover_id > 0 ? wp_get_attachment_image_url( $data->cover_id, 'full' ) : '';

		$data->cover_url = is_string( $cover_url ) ? safe_url( $cover_url ) : '';

		$data->social   = self::social_links( get_meta_array( $data->id, '_ecard_social_links' ) );
		$data->gallery  = self::gallery( get_meta_array( $data->id, '_ecard_gallery' ) );
		$data->videos   = self::videos( get_meta_array( $data->id, '_ecard_videos' ) );
		$data->services = self::services( get_meta_array( $data->id, '_ecard_services' ) );

		$data->welcome_html = is_string( $post->post_content ) ? $post->post_content : '';

		$data->headings = array(
			'welcome'  => self::heading( $data->id, '_ecard_welcome_heading', __( 'Welcome', 'wp-ecard-addons' ) ),
			'services' => self::heading( $data->id, '_ecard_services_heading', __( 'Services', 'wp-ecard-addons' ) ),
			'videos'   => self::heading( $data->id, '_ecard_videos_heading', __( 'Videos', 'wp-ecard-addons' ) ),
		);

		$data->requires_password      = (bool) post_password_required( $post );
		$data->is_publicly_viewable   = function_exists( 'is_post_publicly_viewable' )
			? (bool) is_post_publicly_viewable( $post )
			: ( 'publish' === $post->post_status && '' === (string) $post->post_password );

		return $data;
	}

	/**
	 * Normalize the social links array.
	 *
	 * @param array $raw Raw meta value.
	 * @return array
	 */
	private static function social_links( array $raw ): array {
		$links = array();

		foreach ( self::SOCIAL_NETWORKS as $platform => $label ) {
			$url = isset( $raw[ $platform ] ) && is_string( $raw[ $platform ] ) ? safe_url( $raw[ $platform ] ) : '';

			if ( '' === $url ) {
				continue;
			}

			$links[] = array(
				'platform' => $platform,
				'label'    => $label,
				'url'      => $url,
			);
		}

		return $links;
	}

	/**
	 * Normalize the gallery array of attachment ids.
	 *
	 * @param array $raw Raw meta value.
	 * @return array
	 */
	private static function gallery( array $raw ): array {
		$items = array();
		$seen  = array();

		foreach ( $raw as $value ) {
			$id = absint( $value );

			if ( $id <= 0 || isset( $seen[ $id ] ) ) {
				continue;
			}

			$seen[ $id ] = true;

			$full = wp_get_attachment_image_url( $id, 'large' );

			if ( ! is_string( $full ) || '' === $full ) {
				continue;
			}

			$thumb = wp_get_attachment_image_url( $id, 'medium' );
			$alt   = get_post_meta( $id, '_wp_attachment_image_alt', true );
			$title = (string) get_the_title( $id );

			$items[] = array(
				'id'    => $id,
				'url'   => safe_url( $full ),
				'thumb' => is_string( $thumb ) ? safe_url( $thumb ) : safe_url( $full ),
				'alt'   => is_string( $alt ) && '' !== trim( $alt ) ? trim( $alt ) : $title,
				'title' => $title,
			);
		}

		return $items;
	}

	/**
	 * Normalize the videos array.
	 *
	 * @param array $raw Raw meta value.
	 * @return array
	 */
	private static function videos( array $raw ): array {
		$items = array();

		foreach ( $raw as $video ) {
			if ( ! is_array( $video ) || ! isset( $video['url'] ) || ! is_string( $video['url'] ) ) {
				continue;
			}

			$url = safe_url( $video['url'] );

			if ( '' === $url ) {
				continue;
			}

			$title = isset( $video['title'] ) && is_string( $video['title'] ) ? trim( wp_strip_all_tags( $video['title'] ) ) : '';

			$items[] = array(
				'title'    => $title,
				'url'      => $url,
				'provider' => video_provider( $url ),
			);
		}

		return $items;
	}

	/**
	 * Normalize the services array.
	 *
	 * @param array $raw Raw meta value.
	 * @return array
	 */
	private static function services( array $raw ): array {
		$items = array();

		foreach ( $raw as $service ) {
			if ( ! is_array( $service ) ) {
				continue;
			}

			$name = isset( $service['name'] ) && is_string( $service['name'] ) ? trim( wp_strip_all_tags( $service['name'] ) ) : '';

			if ( '' === $name ) {
				continue;
			}

			$description = isset( $service['description'] ) && is_string( $service['description'] ) ? wp_kses_post( $service['description'] ) : '';

			$items[] = array(
				'name' => $name,
				'html' => $description,
			);
		}

		return $items;
	}

	/**
	 * Read a heading with a translated fallback.
	 *
	 * @param int    $post_id  Post ID.
	 * @param string $meta_key Meta key.
	 * @param string $fallback Fallback heading.
	 * @return string
	 */
	private static function heading( int $post_id, string $meta_key, string $fallback ): string {
		$heading = get_meta_string( $post_id, $meta_key );

		return '' === $heading ? $fallback : $heading;
	}
}
