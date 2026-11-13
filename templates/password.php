<?php
/**
 * Password gate for protected cards.
 *
 * No card data is available in this view: only the core WordPress form.
 *
 * @package MyECardExtensions
 *
 * @var array $context Render context.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$my_ecard_post_id = isset( $context['post_id'] ) ? (int) $context['post_id'] : 0;
$my_ecard_post    = $my_ecard_post_id > 0 ? get_post( $my_ecard_post_id ) : null;

if ( ! $my_ecard_post instanceof \WP_Post ) {
	return;
}
?>
<div class="my-ecard-password" data-my-ecard-password>
	<h1 class="my-ecard-password__title"><?php echo esc_html( get_the_title( $my_ecard_post ) ); ?></h1>
	<?php
	// Core-generated markup, already escaped by WordPress.
	echo get_the_password_form( $my_ecard_post ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	?>
</div>
