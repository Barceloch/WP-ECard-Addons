<?php
/**
 * Reference theme: brand-colored social grid.
 *
 * @package MyECardExtensions
 *
 * @var \MyECardExtensions\CardData $card Normalized card.
 * @var array                       $args Partial arguments.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$my_ecard_icons = array(
	'facebook'  => 'fa-brands fa-facebook-f',
	'instagram' => 'fa-brands fa-instagram',
	'youtube'   => 'fa-brands fa-youtube',
	'twitter'   => 'fa-brands fa-twitter',
	'linkedin'  => 'fa-brands fa-linkedin-in',
);

if ( ! is_array( $card->social ) || array() === $card->social ) {
	return;
}

$my_ecard_links = array();

foreach ( $card->social as $my_ecard_link ) {
	$my_ecard_platform = isset( $my_ecard_link['platform'] ) ? (string) $my_ecard_link['platform'] : '';

	if ( ! isset( $my_ecard_icons[ $my_ecard_platform ] ) ) {
		continue;
	}

	if ( ! isset( $my_ecard_link['url'] ) || '' === (string) $my_ecard_link['url'] ) {
		continue;
	}

	$my_ecard_links[] = array(
		'platform' => $my_ecard_platform,
		'url'      => (string) $my_ecard_link['url'],
		'label'    => isset( $my_ecard_link['label'] ) ? (string) $my_ecard_link['label'] : $my_ecard_platform,
	);
}

if ( array() === $my_ecard_links ) {
	return;
}
?>
<nav class="my-ecard-theme-reference__social" aria-label="<?php esc_attr_e( 'Social profiles', 'wp-ecard-addons' ); ?>">
	<?php foreach ( $my_ecard_links as $my_ecard_link ) : ?>
		<a
			class="my-ecard-theme-reference__social-link my-ecard-theme-reference__social-link--<?php echo esc_attr( $my_ecard_link['platform'] ); ?>"
			href="<?php echo esc_url( $my_ecard_link['url'] ); ?>"
			target="_blank"
			rel="noopener noreferrer nofollow"
			aria-label="<?php echo esc_attr( $my_ecard_link['label'] ); ?>"
			title="<?php echo esc_attr( $my_ecard_link['label'] ); ?>"
		>
			<i class="<?php echo esc_attr( $my_ecard_icons[ $my_ecard_link['platform'] ] ); ?>" aria-hidden="true"></i>
		</a>
	<?php endforeach; ?>
</nav>
