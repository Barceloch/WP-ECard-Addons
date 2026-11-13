<?php
/**
 * Template-facing functions available to addon themes.
 *
 * @package MyECardExtensions
 */

namespace MyECardExtensions;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Context resolved by the template loader for the current request.
 *
 * @return array Empty array when the addon is not rendering.
 */
function my_ecard_context(): array {
	$context = get_query_var( TemplateLoader::QUERY_VAR );

	return is_array( $context ) ? $context : array();
}

/**
 * Normalized card for the current request.
 *
 * @return CardData|null
 */
function my_ecard_card(): ?CardData {
	$context = my_ecard_context();

	return isset( $context['card'] ) && $context['card'] instanceof CardData ? $context['card'] : null;
}

/**
 * Resolved theme record for the current request.
 *
 * @return array
 */
function my_ecard_theme(): array {
	$context = my_ecard_context();

	return isset( $context['theme'] ) && is_array( $context['theme'] ) ? $context['theme'] : array();
}

/**
 * Download URL for the vCard of a card.
 *
 * Defaults to the card resolved for the current request.
 *
 * @param CardData|null $card Optional card. Defaults to my_ecard_card().
 * @return string Empty string when the card is missing, private or has vCard disabled.
 */
function my_ecard_vcard_url( ?CardData $card = null ): string {
	$card = $card instanceof CardData ? $card : my_ecard_card();

	if ( ! $card instanceof CardData ) {
		return '';
	}

	if ( ! VCardGenerator::is_public( $card ) || empty( $card->settings['enable_vcard'] ) ) {
		return '';
	}

	return add_query_arg( array( 'my_ecard_action' => 'vcard' ), $card->url );
}

/**
 * URL of the QR image for a card.
 *
 * Defaults to the card resolved for the current request. The URL builder does
 * not require the QR library: the modal checks availability before rendering.
 *
 * @param CardData|null $card Optional card. Defaults to my_ecard_card().
 * @return string Empty string when the card is missing, private or has QR disabled.
 */
function my_ecard_qr_url( ?CardData $card = null ): string {
	$card = $card instanceof CardData ? $card : my_ecard_card();

	if ( ! $card instanceof CardData ) {
		return '';
	}

	if ( ! VCardGenerator::is_public( $card ) || empty( $card->settings['enable_qr'] ) ) {
		return '';
	}

	return add_query_arg( array( 'my_ecard_action' => 'qr' ), $card->url );
}

/**
 * Render the shared QR modal for the current card.
 *
 * The shared part renders nothing when QR is disabled or unavailable, so any
 * theme can call this unconditionally. Pass false to render only the dialog
 * when the shell's action bar already provides the opener.
 *
 * @param bool $opener Whether the part renders its own opener button.
 * @return void
 */
function my_ecard_qr_modal( bool $opener = true ): void {
	$context = my_ecard_context();
	$context['qr_modal_opener'] = $opener;

	my_ecard_render_file( MY_ECARD_PATH . 'templates/parts/qr-modal.php', $context );
}

/**
 * Render the shared action bar for a card.
 *
 * @param CardData|null $card    Optional card. Defaults to my_ecard_card().
 * @param array|null    $actions Prebuilt actions; the part computes them when null.
 * @param array         $anchors Anchor ids present in the resolved theme.
 * @return void
 */
function my_ecard_action_bar( ?CardData $card = null, ?array $actions = null, array $anchors = array() ): void {
	$card = $card instanceof CardData ? $card : my_ecard_card();

	if ( ! $card instanceof CardData ) {
		return;
	}

	$context = my_ecard_context();

	$context['action_bar_actions'] = $actions;
	$context['action_bar_anchors'] = $anchors;

	my_ecard_render_file( MY_ECARD_PATH . 'templates/parts/action-bar.php', $context );
}

/**
 * Render the shared gallery and videos viewers.
 *
 * The viewers are owned by the addon shell and are independent of the active
 * theme; the ActionBar buttons open them directly.
 *
 * @param CardData|null $card Optional card. Defaults to my_ecard_card().
 * @return void
 */
function my_ecard_media_viewers( ?CardData $card = null ): void {
	$card = $card instanceof CardData ? $card : my_ecard_card();

	if ( ! $card instanceof CardData ) {
		return;
	}

	$context = my_ecard_context();

	$context['media_viewer_card'] = $card;

	my_ecard_render_file( MY_ECARD_PATH . 'templates/parts/media-viewers.php', $context );
}

/**
 * Include a file with the render context available as $context.
 *
 * @param string $file    Absolute file path.
 * @param array  $context Render context.
 * @return void
 */
function my_ecard_render_file( string $file, array $context ): void {
	if ( ! is_file( $file ) || ! is_readable( $file ) ) {
		return;
	}

	include $file;
}

/**
 * Render the resolved theme template with $card and $theme in scope.
 *
 * @param array $context Render context.
 * @return void
 */
function my_ecard_render_theme_template( array $context ): void {
	$template = isset( $context['template'] ) ? $context['template'] : '';

	if ( ! is_string( $template ) || '' === $template || ! is_file( $template ) || ! is_readable( $template ) ) {
		return;
	}

	$card  = isset( $context['card'] ) && $context['card'] instanceof CardData ? $context['card'] : null;
	$theme = isset( $context['theme'] ) && is_array( $context['theme'] ) ? $context['theme'] : array();

	try {
		include $template;
	} catch ( \Throwable $exception ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				sprintf(
					'WP ECard Addons: theme template failed (%s): %s',
					$template,
					$exception->getMessage()
				)
			);
		}
	}
}

/**
 * Include a PHP partial from the resolved theme directory.
 *
 * Traversal outside the theme directory is rejected.
 *
 * @param string $name Relative partial path, for example "parts/header.php".
 * @param array  $args Optional data exposed as $args to the partial.
 * @return void
 */
function my_ecard_template_part( string $name, array $args = array() ): void {
	$theme = my_ecard_theme();
	$dir   = isset( $theme['dir'] ) && is_string( $theme['dir'] ) ? $theme['dir'] : '';

	if ( '' === $dir ) {
		return;
	}

	$name = ltrim( str_replace( '\\', '/', $name ), '/' );

	if ( '' === $name || false !== strpos( $name, '..' ) ) {
		return;
	}

	$base = realpath( $dir );
	$file = realpath( $dir . $name );

	if ( false === $base || false === $file ) {
		return;
	}

	if ( 0 !== strpos( $file, $base . DIRECTORY_SEPARATOR ) || ! is_file( $file ) ) {
		return;
	}

	if ( 'php' !== strtolower( (string) pathinfo( $file, PATHINFO_EXTENSION ) ) ) {
		return;
	}

	$card = my_ecard_card();

	include $file;
}
