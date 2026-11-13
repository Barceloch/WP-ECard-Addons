<?php
/**
 * Autonomous document shell for addon-rendered cards.
 *
 * Theme templates render the card body only. This file owns the document so
 * wp_head(), wp_body_open(), body_class() and wp_footer() always run once.
 *
 * @package MyECardExtensions
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$my_ecard_context = \MyECardExtensions\my_ecard_context();

if ( ! is_array( $my_ecard_context ) || empty( $my_ecard_context['mode'] ) ) {
	return;
}

$my_ecard_mode         = (string) $my_ecard_context['mode'];
$my_ecard_body         = '';
$my_ecard_bar_actions  = array();
$my_ecard_bar_anchors  = array(
	'gallery' => false,
	'videos'  => false,
);
$my_ecard_bar_position = '';
$my_ecard_card         = null;

if ( \MyECardExtensions\TemplateLoader::MODE_PASSWORD !== $my_ecard_mode ) {
	if ( isset( $my_ecard_context['card'] ) && $my_ecard_context['card'] instanceof \MyECardExtensions\CardData ) {
		$my_ecard_card = $my_ecard_context['card'];
	}

	ob_start();

	/**
	 * Fires before the theme template renders the card body.
	 *
	 * @param \MyECardExtensions\CardData $card  Normalized card.
	 * @param array                       $theme Resolved theme record.
	 */
	do_action( 'my_ecard_before_card', $my_ecard_context['card'], $my_ecard_context['theme'] );

	\MyECardExtensions\my_ecard_render_theme_template( $my_ecard_context );

	/**
	 * Fires after the theme template renders the card body.
	 *
	 * @param \MyECardExtensions\CardData $card  Normalized card.
	 * @param array                       $theme Resolved theme record.
	 */
	do_action( 'my_ecard_after_card', $my_ecard_context['card'], $my_ecard_context['theme'] );

	$my_ecard_body = (string) ob_get_clean();

	$my_ecard_bar_anchors = array(
		'gallery' => false !== strpos( $my_ecard_body, 'id="my-ecard-gallery"' ),
		'videos'  => false !== strpos( $my_ecard_body, 'id="my-ecard-videos"' ),
	);

	if ( $my_ecard_card instanceof \MyECardExtensions\CardData ) {
		$my_ecard_bar_actions = \MyECardExtensions\ActionBar::actions( $my_ecard_card, $my_ecard_bar_anchors );

		if ( ! empty( $my_ecard_card->settings['show_bar'] ) && array() !== $my_ecard_bar_actions ) {
			$my_ecard_bar_position = \MyECardExtensions\choice( $my_ecard_card->settings['bar_position'] ?? '', array( 'fixed', 'sticky', 'static' ), 'fixed' );
		}
	}
}

$my_ecard_shell_classes = 'my-ecard-shell';

if ( 'fixed' === $my_ecard_bar_position ) {
	$my_ecard_shell_classes .= ' my-ecard-shell--has-fixed-bar';
}
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>
<body <?php body_class( 'my-ecard-body' ); ?>>
<?php wp_body_open(); ?>
<div class="<?php echo esc_attr( $my_ecard_shell_classes ); ?>" data-my-ecard-shell>
	<?php
	if ( \MyECardExtensions\TemplateLoader::MODE_PASSWORD === $my_ecard_mode ) {
		\MyECardExtensions\my_ecard_render_file( MY_ECARD_PATH . 'templates/password.php', $my_ecard_context );
	} else {
		echo $my_ecard_body; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- already-rendered theme markup.

		if ( array() !== $my_ecard_bar_actions && ! empty( $my_ecard_card->settings['show_bar'] ) ) {
			\MyECardExtensions\my_ecard_action_bar( $my_ecard_card, $my_ecard_bar_actions, $my_ecard_bar_anchors );

			if ( isset( $my_ecard_bar_actions['gallery'] ) || isset( $my_ecard_bar_actions['videos'] ) ) {
				\MyECardExtensions\my_ecard_media_viewers( $my_ecard_card );
			}
		}

		if ( $my_ecard_card instanceof \MyECardExtensions\CardData && isset( $my_ecard_bar_actions['qr'] ) ) {
			\MyECardExtensions\my_ecard_qr_modal( false );
		}
	}
	?>
</div>
<?php wp_footer(); ?>
</body>
</html>
