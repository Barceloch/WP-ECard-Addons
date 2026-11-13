<?php
/**
 * Fixture theme used by the template engine tests.
 *
 * @package MyECardExtensions
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$my_ecard_function_card = \MyECardExtensions\my_ecard_card();
?>
<article class="my-ecard-fixture" data-my-ecard-template="render">
	<h1 class="my-ecard-fixture__title"><?php echo esc_html( $card instanceof \MyECardExtensions\CardData ? $card->title : 'no-card' ); ?></h1>
	<p class="my-ecard-fixture__designation"><?php echo esc_html( $card instanceof \MyECardExtensions\CardData ? $card->designation : '' ); ?></p>
	<p class="my-ecard-fixture__company"><?php echo esc_html( $card instanceof \MyECardExtensions\CardData ? $card->company : '' ); ?></p>
	<p class="my-ecard-fixture__email"><?php echo esc_html( $card instanceof \MyECardExtensions\CardData ? $card->email : '' ); ?></p>
	<p class="my-ecard-fixture__theme"><?php echo esc_html( isset( $theme['name'] ) ? (string) $theme['name'] : '' ); ?></p>
	<p class="my-ecard-fixture__function"><?php echo esc_html( $my_ecard_function_card instanceof \MyECardExtensions\CardData ? $my_ecard_function_card->designation : '' ); ?></p>
	<?php \MyECardExtensions\my_ecard_template_part( 'parts/footer.php', array( 'label' => 'fixture-footer' ) ); ?>
</article>
