<?php
/**
 * Shared media viewers: gallery dialog and videos dialog.
 *
 * Renders at most one dialog per media type and only when the card has media
 * enabled and available. The part renders no iframe and no video element: the
 * viewer script builds the media from the data attributes, so nothing is
 * fetched until the visitor opens a dialog.
 *
 * @package MyECardExtensions
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$my_ecard_viewer_card = \MyECardExtensions\my_ecard_card();

if ( isset( $context ) && is_array( $context ) && isset( $context['media_viewer_card'] ) && $context['media_viewer_card'] instanceof \MyECardExtensions\CardData ) {
	$my_ecard_viewer_card = $context['media_viewer_card'];
}

if ( ! $my_ecard_viewer_card instanceof \MyECardExtensions\CardData ) {
	return;
}

$card = $my_ecard_viewer_card;

$gallery = ! empty( $card->settings['enable_gallery'] ) && ! empty( $card->gallery ) ? $card->gallery : array();
$videos  = ! empty( $card->settings['enable_videos'] ) && ! empty( $card->videos ) ? $card->videos : array();

if ( empty( $gallery ) && empty( $videos ) ) {
	return;
}
?>

<?php if ( ! empty( $gallery ) ) : ?>
	<?php
	$my_ecard_gallery_count = count( $gallery );
	$my_ecard_gallery_first = $gallery[0];
	$my_ecard_gallery_alt   = isset( $my_ecard_gallery_first['alt'] ) ? (string) $my_ecard_gallery_first['alt'] : '';
	$my_ecard_gallery_title = isset( $my_ecard_gallery_first['title'] ) ? (string) $my_ecard_gallery_first['title'] : '';
	$my_ecard_gallery_head  = '' !== $my_ecard_gallery_title ? $my_ecard_gallery_title : $my_ecard_gallery_alt;
	?>
	<dialog id="my-ecard-gallery-viewer" class="my-ecard-media-viewer" data-my-ecard-media-viewer="gallery" aria-labelledby="my-ecard-gallery-viewer-title">
		<div class="my-ecard-media-viewer__frame">
			<h2 id="my-ecard-gallery-viewer-title" class="my-ecard-media-viewer__title"><?php echo esc_html__( 'Gallery', 'wp-ecard-addons' ); ?></h2>
			<button type="button" class="my-ecard-media-viewer__close" data-my-ecard-media-close aria-label="<?php echo esc_attr__( 'Close gallery', 'wp-ecard-addons' ); ?>"><span aria-hidden="true">&times;</span></button>

			<div class="my-ecard-media-viewer__stage" data-my-ecard-media-stage>
				<button type="button" class="my-ecard-media-viewer__nav my-ecard-media-viewer__nav--prev" data-my-ecard-media-prev aria-label="<?php echo esc_attr__( 'Previous image', 'wp-ecard-addons' ); ?>"<?php if ( $my_ecard_gallery_count < 2 ) : ?> disabled<?php endif; ?>><span aria-hidden="true">&#8249;</span></button>
				<img class="my-ecard-media-viewer__image" data-my-ecard-media-image src="<?php echo esc_url( $my_ecard_gallery_first['url'] ); ?>" alt="<?php echo esc_attr( $my_ecard_gallery_alt ); ?>" decoding="async" />
				<button type="button" class="my-ecard-media-viewer__nav my-ecard-media-viewer__nav--next" data-my-ecard-media-next aria-label="<?php echo esc_attr__( 'Next image', 'wp-ecard-addons' ); ?>"<?php if ( $my_ecard_gallery_count < 2 ) : ?> disabled<?php endif; ?>><span aria-hidden="true">&#8250;</span></button>
			</div>

			<p class="my-ecard-media-viewer__caption" data-my-ecard-media-caption><?php echo esc_html( $my_ecard_gallery_head ); ?></p>
			<p class="my-ecard-media-viewer__counter" data-my-ecard-media-counter aria-live="polite">1 / <?php echo esc_html( (string) $my_ecard_gallery_count ); ?></p>

			<ul class="my-ecard-media-viewer__thumbs" data-my-ecard-media-list>
				<?php foreach ( $gallery as $my_ecard_gallery_index => $my_ecard_gallery_item ) : ?>
					<?php
					$my_ecard_item_alt   = isset( $my_ecard_gallery_item['alt'] ) ? (string) $my_ecard_gallery_item['alt'] : '';
					$my_ecard_item_title = isset( $my_ecard_gallery_item['title'] ) ? (string) $my_ecard_gallery_item['title'] : '';
					$my_ecard_item_thumb = isset( $my_ecard_gallery_item['thumb'] ) ? (string) $my_ecard_gallery_item['thumb'] : '';
					$my_ecard_item_url   = isset( $my_ecard_gallery_item['url'] ) ? (string) $my_ecard_gallery_item['url'] : '';
					?>
					<li>
						<button
							type="button"
							class="my-ecard-media-viewer__thumb"
							data-my-ecard-media-item
							data-index="<?php echo esc_attr( (string) $my_ecard_gallery_index ); ?>"
							data-src="<?php echo esc_url( $my_ecard_item_url ); ?>"
							data-thumb="<?php echo esc_url( $my_ecard_item_thumb ); ?>"
							data-alt="<?php echo esc_attr( $my_ecard_item_alt ); ?>"
							data-title="<?php echo esc_attr( $my_ecard_item_title ); ?>"
							aria-label="<?php echo esc_attr( sprintf( /* translators: %d: image position. */ __( 'Show image %d', 'wp-ecard-addons' ), $my_ecard_gallery_index + 1 ) ); ?>"<?php if ( 0 === $my_ecard_gallery_index ) : ?> aria-current="true"<?php endif; ?>
						>
							<img src="<?php echo esc_url( $my_ecard_item_thumb ); ?>" alt="" loading="lazy" decoding="async" />
						</button>
					</li>
				<?php endforeach; ?>
			</ul>
		</div>
	</dialog>
<?php endif; ?>

<?php if ( ! empty( $videos ) ) : ?>
	<?php
	$my_ecard_videos_count = count( $videos );
	$my_ecard_videos_head  = isset( $card->headings['videos'] ) && is_string( $card->headings['videos'] ) && '' !== $card->headings['videos']
		? $card->headings['videos']
		: __( 'Videos', 'wp-ecard-addons' );
	$my_ecard_videos_icons = array(
		'youtube' => 'fa-brands fa-youtube',
		'vimeo'   => 'fa-brands fa-vimeo-v',
	);
	$my_ecard_videos_first = $videos[0];
	$my_ecard_videos_cap   = isset( $my_ecard_videos_first['title'] ) ? (string) $my_ecard_videos_first['title'] : '';
	?>
	<dialog id="my-ecard-videos-viewer" class="my-ecard-media-viewer" data-my-ecard-media-viewer="videos" aria-labelledby="my-ecard-videos-viewer-title">
		<div class="my-ecard-media-viewer__frame">
			<h2 id="my-ecard-videos-viewer-title" class="my-ecard-media-viewer__title"><?php echo esc_html( $my_ecard_videos_head ); ?></h2>
			<button type="button" class="my-ecard-media-viewer__close" data-my-ecard-media-close aria-label="<?php echo esc_attr__( 'Close videos', 'wp-ecard-addons' ); ?>"><span aria-hidden="true">&times;</span></button>

			<div class="my-ecard-media-viewer__stage my-ecard-media-viewer__stage--video" data-my-ecard-media-stage>
				<button type="button" class="my-ecard-media-viewer__nav my-ecard-media-viewer__nav--prev" data-my-ecard-media-prev aria-label="<?php echo esc_attr__( 'Previous video', 'wp-ecard-addons' ); ?>"<?php if ( $my_ecard_videos_count < 2 ) : ?> disabled<?php endif; ?>><span aria-hidden="true">&#8249;</span></button>
				<div class="my-ecard-media-viewer__video" data-my-ecard-media-video></div>
				<button type="button" class="my-ecard-media-viewer__nav my-ecard-media-viewer__nav--next" data-my-ecard-media-next aria-label="<?php echo esc_attr__( 'Next video', 'wp-ecard-addons' ); ?>"<?php if ( $my_ecard_videos_count < 2 ) : ?> disabled<?php endif; ?>><span aria-hidden="true">&#8250;</span></button>
			</div>

			<p class="my-ecard-media-viewer__caption" data-my-ecard-media-caption><?php echo esc_html( $my_ecard_videos_cap ); ?></p>
			<p class="my-ecard-media-viewer__counter" data-my-ecard-media-counter aria-live="polite">1 / <?php echo esc_html( (string) $my_ecard_videos_count ); ?></p>

			<ul class="my-ecard-media-viewer__list" data-my-ecard-media-list>
				<?php foreach ( $videos as $my_ecard_videos_index => $my_ecard_video ) : ?>
					<?php
					$my_ecard_video_title    = isset( $my_ecard_video['title'] ) ? (string) $my_ecard_video['title'] : '';
					$my_ecard_video_url      = isset( $my_ecard_video['url'] ) ? (string) $my_ecard_video['url'] : '';
					$my_ecard_video_provider = isset( $my_ecard_video['provider'] ) ? (string) $my_ecard_video['provider'] : '';
					$my_ecard_video_embed    = \MyECardExtensions\video_embed_url( $my_ecard_video );
					$my_ecard_video_direct   = \MyECardExtensions\is_direct_video_url( $my_ecard_video_url ) ? $my_ecard_video_url : '';
					$my_ecard_video_icon     = isset( $my_ecard_videos_icons[ $my_ecard_video_provider ] )
						? $my_ecard_videos_icons[ $my_ecard_video_provider ]
						: 'fa-solid fa-circle-play';
					$my_ecard_video_label    = '' !== $my_ecard_video_title
						? $my_ecard_video_title
						: __( 'Play video', 'wp-ecard-addons' );
					?>
					<li>
						<button
							type="button"
							class="my-ecard-media-viewer__video-item"
							data-my-ecard-media-item
							data-index="<?php echo esc_attr( (string) $my_ecard_videos_index ); ?>"
							data-provider="<?php echo esc_attr( $my_ecard_video_provider ); ?>"
							data-embed="<?php echo esc_url( $my_ecard_video_embed ); ?>"
							data-direct="<?php echo esc_url( $my_ecard_video_direct ); ?>"
							data-url="<?php echo esc_url( $my_ecard_video_url ); ?>"
							data-title="<?php echo esc_attr( $my_ecard_video_title ); ?>"
							data-open-label="<?php esc_attr_e( 'Open video', 'wp-ecard-addons' ); ?>"
							aria-label="<?php echo esc_attr( $my_ecard_video_label ); ?>"<?php if ( 0 === $my_ecard_videos_index ) : ?> aria-current="true"<?php endif; ?>
						>
							<i class="<?php echo esc_attr( $my_ecard_video_icon ); ?>" aria-hidden="true"></i>
							<span><?php echo esc_html( '' !== $my_ecard_video_title ? $my_ecard_video_title : __( 'Watch video', 'wp-ecard-addons' ) ); ?></span>
						</button>
					</li>
				<?php endforeach; ?>
			</ul>
		</div>
	</dialog>
<?php endif; ?>
