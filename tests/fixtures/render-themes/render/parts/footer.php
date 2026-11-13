<?php
/**
 * Fixture partial.
 *
 * @package MyECardExtensions
 *
 * @var array $args Partial arguments.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<footer class="my-ecard-fixture__footer"><?php echo esc_html( isset( $args['label'] ) ? (string) $args['label'] : '' ); ?>|<?php echo esc_html( $card instanceof \MyECardExtensions\CardData ? $card->company : '' ); ?></footer>
