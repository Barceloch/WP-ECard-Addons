<?php
/**
 * Shared QR modal: opener button, native dialog and QR image.
 *
 * Renders nothing when the card has no QR enabled or when the QR renderer is
 * missing, so a theme never shows a broken image or a dead control.
 *
 * @package MyECardExtensions
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$my_ecard_modal_card = \MyECardExtensions\my_ecard_card();

if ( ! $my_ecard_modal_card instanceof \MyECardExtensions\CardData ) {
	return;
}

if ( empty( $my_ecard_modal_card->settings['enable_qr'] ) ) {
	return;
}

if ( ! \MyECardExtensions\QRCodeGenerator::is_available() ) {
	return;
}

$my_ecard_modal_ver = \MyECardExtensions\my_ecard_asset_version();

wp_enqueue_script(
	'my-ecard-qr-modal',
	MY_ECARD_URL . 'assets/js/qr-modal.js',
	array(),
	$my_ecard_modal_ver,
	true
);

$my_ecard_modal_opener = ! isset( $context['qr_modal_opener'] ) || (bool) $context['qr_modal_opener'];
$my_ecard_modal_id     = 'my-ecard-qr-dialog-' . (int) $my_ecard_modal_card->id;
$my_ecard_modal_url   = add_query_arg( array( 'my_ecard_action' => 'qr' ), $my_ecard_modal_card->url );
$my_ecard_modal_show  = ! empty( $my_ecard_modal_card->settings['qr_label'] );
$my_ecard_modal_title = __( 'Scan to open this card', 'wp-ecard-addons' );
$my_ecard_modal_alt   = sprintf(
	/* translators: %s: card title. */
	__( 'QR code for %s', 'wp-ecard-addons' ),
	(string) $my_ecard_modal_card->title
);
?>
<div class="my-ecard-qr">
	<?php if ( $my_ecard_modal_opener ) : ?>
	<button
		type="button"
		class="my-ecard-qr__open"
		data-my-ecard-qr-open
		aria-haspopup="dialog"
		aria-controls="<?php echo esc_attr( $my_ecard_modal_id ); ?>"
	>
		<svg class="my-ecard-qr__icon" viewBox="0 0 24 24" width="24" height="24" aria-hidden="true" focusable="false">
			<path fill="currentColor" d="M3 3h8v8H3V3zm2 2v4h4V5H5zm8-2h8v8h-8V3zm2 2v4h4V5h-4zM3 13h8v8H3v-8zm2 2v4h4v-4H5zm8-2h3v3h-3v-3zm5 0h3v3h-3v-3zm-5 5h3v3h-3v-3zm5 0h3v3h-3v-3z"/>
		</svg>
		<?php if ( $my_ecard_modal_show ) : ?>
			<span class="my-ecard-qr__label"><?php esc_html_e( 'QR code', 'wp-ecard-addons' ); ?></span>
		<?php endif; ?>
	</button>
	<?php endif; ?>

	<dialog
		id="<?php echo esc_attr( $my_ecard_modal_id ); ?>"
		class="my-ecard-qr__dialog"
		data-my-ecard-qr-dialog
		aria-labelledby="<?php echo esc_attr( $my_ecard_modal_id ); ?>-title"
	>
		<h2 id="<?php echo esc_attr( $my_ecard_modal_id ); ?>-title" class="my-ecard-qr__title">
			<?php echo esc_html( $my_ecard_modal_title ); ?>
		</h2>

		<img
			class="my-ecard-qr__image"
			data-my-ecard-qr-image
			src="<?php echo esc_url( $my_ecard_modal_url ); ?>"
			alt="<?php echo esc_attr( $my_ecard_modal_alt ); ?>"
			decoding="async"
		/>

		<form method="dialog" class="my-ecard-qr__actions">
			<button type="submit" class="my-ecard-qr__close" data-my-ecard-qr-close>
				<?php esc_html_e( 'Close', 'wp-ecard-addons' ); ?>
			</button>
		</form>
	</dialog>
</div>
