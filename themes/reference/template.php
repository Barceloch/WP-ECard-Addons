<?php
/**
 * Reference theme.
 *
 * Panoramic cover, optional overlapping avatar, centered identity, circular
 * contacts and a brand-colored social grid.
 *
 * Gallery and video content is intentionally NOT rendered inside the card. The
 * shell-owned media viewers expose it through the shared action bar, so the
 * card body stays limited to its primary identity and contact content.
 *
 * The theme reads card information exclusively through CardData and the public
 * addon functions. It renders markup only: no QR, vCard, share or admin logic.
 * The theme does not own an action bar; the shared bar is rendered by the
 * shell around the theme output.
 *
 * @package MyECardExtensions
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$my_ecard_card  = \MyECardExtensions\my_ecard_card();
$my_ecard_theme = \MyECardExtensions\my_ecard_theme();

if ( null === $my_ecard_card ) {
	return;
}

$my_ecard_settings = $my_ecard_card->settings;
$my_ecard_hero     = '' !== $my_ecard_card->cover_url ? $my_ecard_card->cover_url : $my_ecard_card->logo_url;
$my_ecard_avatar   = ! empty( $my_ecard_settings['show_avatar'] ) ? $my_ecard_card->avatar_url : '';
$my_ecard_height   = isset( $my_ecard_settings['header_height'] ) ? (int) $my_ecard_settings['header_height'] : 220;
$my_ecard_accent   = isset( $my_ecard_settings['accent_color'] ) && is_string( $my_ecard_settings['accent_color'] )
	? $my_ecard_settings['accent_color']
	: '';

if ( $my_ecard_height < 140 || $my_ecard_height > 480 ) {
	$my_ecard_height = 220;
}

$my_ecard_style = '--my-ecard-reference-hero:' . $my_ecard_height . 'px;';

if ( '' !== $my_ecard_accent && preg_match( '/^#(?:[0-9a-fA-F]{3}){1,2}$/', $my_ecard_accent ) ) {
	$my_ecard_style .= '--my-ecard-reference-accent:' . $my_ecard_accent . ';';
}
?>
<div class="my-ecard-theme-reference" data-my-ecard-theme="reference" style="<?php echo esc_attr( $my_ecard_style ); ?>">
	<article class="my-ecard-theme-reference__card">
		<header class="my-ecard-theme-reference__hero">
			<?php if ( '' !== $my_ecard_hero ) : ?>
				<img
					class="my-ecard-theme-reference__hero-image"
					src="<?php echo esc_url( $my_ecard_hero ); ?>"
					alt="<?php echo esc_attr( $my_ecard_card->title ); ?>"
					decoding="async"
				/>
			<?php endif; ?>
		</header>

		<div class="my-ecard-theme-reference__body">
			<?php if ( '' !== $my_ecard_avatar ) : ?>
				<div class="my-ecard-theme-reference__avatar-wrap">
					<img
						class="my-ecard-theme-reference__avatar"
						src="<?php echo esc_url( $my_ecard_avatar ); ?>"
						alt="<?php echo esc_attr( $my_ecard_card->title ); ?>"
						width="116"
						height="116"
						decoding="async"
					/>
				</div>
			<?php endif; ?>

			<div class="my-ecard-theme-reference__identity">
				<h1 class="my-ecard-theme-reference__name"><?php echo esc_html( $my_ecard_card->title ); ?></h1>

				<?php if ( '' !== $my_ecard_card->designation ) : ?>
					<p class="my-ecard-theme-reference__designation"><?php echo esc_html( $my_ecard_card->designation ); ?></p>
				<?php endif; ?>

				<?php if ( '' !== $my_ecard_card->company ) : ?>
					<p class="my-ecard-theme-reference__company"><?php echo esc_html( $my_ecard_card->company ); ?></p>
				<?php endif; ?>
			</div>

			<?php \MyECardExtensions\my_ecard_template_part( 'parts/contacts.php' ); ?>
			<?php \MyECardExtensions\my_ecard_template_part( 'parts/social.php' ); ?>
		</div>
	</article>
</div>
