<?php
/**
 * Reference theme: circular contact buttons.
 *
 * Only contact channels with usable data are rendered.
 *
 * @package MyECardExtensions
 *
 * @var \MyECardExtensions\CardData $card Normalized card.
 * @var array                       $args Partial arguments.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$my_ecard_contacts = array();

if ( '' !== $card->phone_url ) {
	$my_ecard_contacts[] = array(
		'modifier' => 'phone',
		'url'      => $card->phone_url,
		'icon'     => 'fa-solid fa-phone',
		'label'    => __( 'Call', 'wp-ecard-addons' ),
	);
}

if ( '' !== $card->email ) {
	$my_ecard_contacts[] = array(
		'modifier' => 'email',
		'url'      => 'mailto:' . $card->email,
		'icon'     => 'fa-solid fa-envelope',
		'label'    => __( 'Email', 'wp-ecard-addons' ),
	);
}

if ( '' !== $card->whatsapp_url ) {
	$my_ecard_contacts[] = array(
		'modifier' => 'whatsapp',
		'url'      => $card->whatsapp_url,
		'icon'     => 'fa-brands fa-whatsapp',
		'label'    => __( 'WhatsApp', 'wp-ecard-addons' ),
	);
}

if ( array() === $my_ecard_contacts ) {
	return;
}
?>
<nav class="my-ecard-theme-reference__contacts" aria-label="<?php esc_attr_e( 'Contact', 'wp-ecard-addons' ); ?>">
	<?php foreach ( $my_ecard_contacts as $my_ecard_contact ) : ?>
		<a
			class="my-ecard-theme-reference__contact my-ecard-theme-reference__contact--<?php echo esc_attr( $my_ecard_contact['modifier'] ); ?>"
			href="<?php echo esc_url( $my_ecard_contact['url'] ); ?>"
			aria-label="<?php echo esc_attr( $my_ecard_contact['label'] ); ?>"
			title="<?php echo esc_attr( $my_ecard_contact['label'] ); ?>"
		>
			<i class="<?php echo esc_attr( $my_ecard_contact['icon'] ); ?>" aria-hidden="true"></i>
		</a>
	<?php endforeach; ?>
</nav>
