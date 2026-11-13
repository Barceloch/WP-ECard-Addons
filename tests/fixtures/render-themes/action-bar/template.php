<?php
/**
 * Fixture theme used by the action bar and section rendering tests.
 *
 * @package MyECardExtensions
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$card = \MyECardExtensions\my_ecard_card();

if ( null === $card ) {
	return;
}
?>
<style>
	body { margin: 0; }
	.my-ecard-fixture-action-bar { box-sizing: border-box; max-width: 100%; padding: 16px; font-family: sans-serif; }
	.my-ecard-fixture-action-bar *,
	.my-ecard-fixture-action-bar *::before,
	.my-ecard-fixture-action-bar *::after { box-sizing: border-box; }
	.my-ecard-fixture-action-bar ul { margin: 0; padding: 0; list-style: none; }
	.my-ecard-fixture-action-bar img { display: block; max-width: 100%; height: auto; }
</style>
<article class="my-ecard-fixture-action-bar" data-my-ecard-template="action-bar">
	<h1 class="my-ecard-fixture-action-bar__title"><?php echo esc_html( $card->title ); ?></h1>

	<?php if ( ! empty( $card->gallery ) ) : ?>
		<section id="my-ecard-gallery" class="my-ecard-fixture-action-bar__gallery">
			<ul class="my-ecard-fixture-action-bar__gallery-list">
				<?php foreach ( $card->gallery as $item ) : ?>
					<li class="my-ecard-fixture-action-bar__gallery-item">
						<a href="<?php echo esc_url( $item['url'] ); ?>">
							<img
								src="<?php echo esc_url( $item['thumb'] ); ?>"
								alt="<?php echo esc_attr( $item['alt'] ); ?>"
								title="<?php echo esc_attr( $item['title'] ); ?>"
								loading="lazy"
								decoding="async"
							/>
						</a>
					</li>
				<?php endforeach; ?>
			</ul>
		</section>
	<?php endif; ?>

	<?php if ( ! empty( $card->videos ) ) : ?>
		<section id="my-ecard-videos" class="my-ecard-fixture-action-bar__videos">
			<ul class="my-ecard-fixture-action-bar__videos-list">
				<?php foreach ( $card->videos as $video ) : ?>
					<li class="my-ecard-fixture-action-bar__video-item">
						<a href="<?php echo esc_url( $video['url'] ); ?>"><?php echo esc_html( $video['title'] ); ?></a>
					</li>
				<?php endforeach; ?>
			</ul>
		</section>
	<?php endif; ?>
</article>
