<?php
/**
 * Shared action bar for public cards.
 *
 * @package MyECardExtensions
 */

namespace MyECardExtensions;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds and renders the ordered set of card actions.
 *
 * The action list is the single source of truth for which controls a card may
 * show. A control is listed only when both the addon setting and the card data
 * agree, so a template never has to second-guess availability on its own.
 */
final class ActionBar {

	/**
	 * Accent color pattern accepted for the inline custom property.
	 */
	const ACCENT_PATTERN = '/^#(?:[0-9a-fA-F]{3}){1,2}$/';

	/**
	 * Ordered actions available for a card.
	 *
	 * Gallery and videos are available from card data and settings because the
	 * shell owns their media viewers. The anchors argument is retained for
	 * backwards compatibility with themes that expose optional section anchors.
	 *
	 * @param CardData $card    Card read model.
	 * @param array    $anchors Optional section anchors; no longer gates actions.
	 * @return array Ordered map of action key => action definition.
	 */
	public static function actions( CardData $card, array $anchors = array() ): array {
		$actions = array();

		/*
		 * Every action is gated on its own requirements only. No action may
		 * remove the bar or hide its siblings: a card without media still gets
		 * vCard, location, QR and share, and a card without a public URL still
		 * gets gallery and videos. Availability is never inferred from markup
		 * the theme renders, so removing in-card sections cannot hide actions.
		 */
		if ( ! $card->is_publicly_viewable ) {
			return $actions;
		}

		$public = VCardGenerator::is_public( $card );

		$vcard_url = my_ecard_vcard_url( $card );

		if ( ! empty( $card->settings['enable_vcard'] ) && '' !== $vcard_url ) {
			$actions['vcard'] = array(
				'type'       => 'link',
				'label'      => __( 'vCard', 'wp-ecard-addons' ),
				'aria_label' => __( 'Download vCard', 'wp-ecard-addons' ),
				'icon'       => 'fa-solid fa-download',
				'href'       => $vcard_url,
				'attributes' => array(),
			);
		}

		if ( ! empty( $card->settings['enable_location'] ) && '' !== $card->location_url ) {
			$actions['location'] = array(
				'type'       => 'link',
				'label'      => __( 'Location', 'wp-ecard-addons' ),
				'icon'       => 'fa-solid fa-location-dot',
				'href'       => $card->location_url,
				'attributes' => array(
					'target' => '_blank',
					'rel'    => 'noopener noreferrer',
				),
			);
		}

		if ( ! empty( $card->settings['enable_qr'] ) && $public && QRCodeGenerator::is_available() ) {
			$actions['qr'] = array(
				'type'       => 'button',
				'label'      => __( 'QR code', 'wp-ecard-addons' ),
				'icon'       => 'fa-solid fa-qrcode',
				'attributes' => array(
					'data-my-ecard-qr-open' => '',
					'aria-haspopup'        => 'dialog',
				),
			);
		}

		if ( ! empty( $card->settings['enable_share'] ) && $public && '' !== $card->url ) {
			$actions['share'] = array(
				'type'       => 'button',
				'label'      => __( 'Share', 'wp-ecard-addons' ),
				'icon'       => 'fa-solid fa-share-nodes',
				'attributes' => array(
					'data-my-ecard-share'        => '',
					'data-my-ecard-share-url'    => $card->url,
					'data-my-ecard-share-copied' => __( 'Link copied to the clipboard.', 'wp-ecard-addons' ),
					'data-my-ecard-share-failed' => __( 'The link could not be copied.', 'wp-ecard-addons' ),
					'data-my-ecard-share-shared' => __( 'Link shared.', 'wp-ecard-addons' ),
				),
			);
		}

		if ( ! empty( $card->settings['enable_gallery'] ) && ! empty( $card->gallery ) ) {
			$actions['gallery'] = array(
				'type'       => 'button',
				'label'      => __( 'Gallery', 'wp-ecard-addons' ),
				'aria_label' => __( 'Open gallery', 'wp-ecard-addons' ),
				'icon'       => 'fa-solid fa-images',
				'attributes' => array(
					'data-my-ecard-gallery-open' => '',
					'aria-haspopup'              => 'dialog',
					'aria-controls'              => 'my-ecard-gallery-viewer',
				),
			);
		}

		if ( ! empty( $card->settings['enable_videos'] ) && ! empty( $card->videos ) ) {
			$actions['videos'] = array(
				'type'       => 'button',
				'label'      => __( 'Videos', 'wp-ecard-addons' ),
				'aria_label' => __( 'Open videos', 'wp-ecard-addons' ),
				'icon'       => 'fa-solid fa-circle-play',
				'attributes' => array(
					'data-my-ecard-videos-open' => '',
					'aria-haspopup'             => 'dialog',
					'aria-controls'             => 'my-ecard-videos-viewer',
				),
			);
		}

		return $actions;
	}

	/**
	 * Render the action bar for a card.
	 *
	 * @param CardData   $card    Card read model.
	 * @param array|null $actions Prebuilt actions; computed when null.
	 * @param array      $anchors Anchor ids present in the resolved theme.
	 * @return void
	 */
	public static function render( CardData $card, ?array $actions = null, array $anchors = array() ): void {
		if ( empty( $card->settings['show_bar'] ) ) {
			return;
		}

		/*
		 * Presentation guard only: a card WordPress would not show, or one whose
		 * password was not satisfied, must never expose its bar or media.
		 */
		if ( ! $card->is_publicly_viewable || $card->requires_password ) {
			return;
		}

		$actions = null === $actions ? self::actions( $card, $anchors ) : $actions;
		$items   = self::renderable( $actions );

		if ( array() === $items ) {
			return;
		}

		$position = choice( isset( $card->settings['bar_position'] ) ? $card->settings['bar_position'] : '', array( 'fixed', 'sticky', 'static' ), 'fixed' );
		$labels   = choice( isset( $card->settings['bar_labels'] ) ? $card->settings['bar_labels'] : '', array( 'show', 'hide', 'hide_mobile' ), 'show' );

		$accent = isset( $card->settings['accent_color'] ) && is_string( $card->settings['accent_color'] )
			? $card->settings['accent_color']
			: '';

		$style = preg_match( self::ACCENT_PATTERN, $accent ) ? '--my-ecard-action-bar-accent:' . $accent . ';' : '';

		$style_attribute = '' === $style ? '' : ' style="' . esc_attr( $style ) . '"';
		?>
		<nav
			class="my-ecard-action-bar my-ecard-action-bar--<?php echo esc_attr( $position ); ?>"
			data-my-ecard-action-bar
			data-my-ecard-bar-position="<?php echo esc_attr( $position ); ?>"
			data-my-ecard-bar-labels="<?php echo esc_attr( $labels ); ?>"
			aria-label="<?php esc_attr_e( 'Card actions', 'wp-ecard-addons' ); ?>"<?php echo $style_attribute; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above. ?>
		>
			<ul class="my-ecard-action-bar__list">
				<?php foreach ( $items as $key => $item ) : ?>
					<li class="my-ecard-action-bar__item">
						<?php if ( 'button' === $item['type'] ) : ?>
							<button
								type="button"
								class="my-ecard-action-bar__action"
								data-my-ecard-action="<?php echo esc_attr( $key ); ?>"
								aria-label="<?php echo esc_attr( $item['aria_label'] ); ?>"
								<?php echo self::attributes( $item['attributes'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in attributes(). ?>
							>
						<?php else : ?>
							<a
								class="my-ecard-action-bar__action"
								href="<?php echo esc_url( $item['href'] ); ?>"
								data-my-ecard-action="<?php echo esc_attr( $key ); ?>"
								aria-label="<?php echo esc_attr( $item['aria_label'] ); ?>"
								<?php echo self::attributes( $item['attributes'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in attributes(). ?>
							>
						<?php endif; ?>
							<i class="my-ecard-action-bar__icon <?php echo esc_attr( $item['icon'] ); ?>" aria-hidden="true"></i>
							<span class="my-ecard-action-bar__label"><?php echo esc_html( $item['label'] ); ?></span>
						<?php if ( 'button' === $item['type'] ) : ?>
							</button>
						<?php else : ?>
							</a>
						<?php endif; ?>
					</li>
				<?php endforeach; ?>
			</ul>

			<?php if ( isset( $items['share'] ) ) : ?>
				<span class="my-ecard-action-bar__status" data-my-ecard-share-status role="status" aria-live="polite"></span>
			<?php endif; ?>
		</nav>
		<?php
	}

	/**
	 * Keep only actions that can produce valid markup.
	 *
	 * @param array $actions Raw action map.
	 * @return array Filtered action map in the original order.
	 */
	private static function renderable( array $actions ): array {
		$items = array();

		foreach ( $actions as $key => $action ) {
			if ( ! is_string( $key ) || ! is_array( $action ) ) {
				continue;
			}

			$label = isset( $action['label'] ) && is_string( $action['label'] ) ? trim( $action['label'] ) : '';

			if ( '' === $label ) {
				continue;
			}

			$aria_label = isset( $action['aria_label'] ) && is_string( $action['aria_label'] ) ? trim( $action['aria_label'] ) : '';

			if ( '' === $aria_label ) {
				$aria_label = $label;
			}

			$type = isset( $action['type'] ) && 'button' === $action['type'] ? 'button' : 'link';

			if ( 'link' === $type && ( ! isset( $action['href'] ) || ! is_string( $action['href'] ) || '' === $action['href'] ) ) {
				continue;
			}

			$items[ $key ] = array(
				'type'       => $type,
				'label'      => $label,
				'aria_label' => $aria_label,
				'icon'       => isset( $action['icon'] ) && is_string( $action['icon'] ) ? $action['icon'] : '',
				'href'       => isset( $action['href'] ) && is_string( $action['href'] ) ? $action['href'] : '',
				'attributes' => isset( $action['attributes'] ) && is_array( $action['attributes'] ) ? $action['attributes'] : array(),
			);
		}

		return $items;
	}

	/**
	 * Render extra attributes, escaping both names and values.
	 *
	 * An empty string value renders as a bare boolean attribute.
	 *
	 * @param array $attributes Attribute name => value pairs.
	 * @return string Leading-space-prefixed attribute string.
	 */
	private static function attributes( array $attributes ): string {
		$html = '';

		foreach ( $attributes as $name => $value ) {
			$name = (string) $name;

			if ( '' === $name || ! preg_match( '/^[a-zA-Z_:][a-zA-Z0-9_:.-]*$/', $name ) ) {
				continue;
			}

			if ( ! is_scalar( $value ) ) {
				continue;
			}

			$value = (string) $value;

			$html .= '' === $value
				? ' ' . esc_attr( $name )
				: ' ' . esc_attr( $name ) . '="' . esc_attr( $value ) . '"';
		}

		return $html;
	}
}
