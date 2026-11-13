<?php
/**
 * Shared action bar part: resolves the card and delegates to ActionBar.
 *
 * The part accepts optional prebuilt actions and anchors from the render
 * context, so a theme can decide what its layout really contains while the
 * shared component owns availability rules and markup.
 *
 * @package MyECardExtensions
 *
 * @var array $context Optional render context.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$my_ecard_action_bar_card = \MyECardExtensions\my_ecard_card();

if ( ! $my_ecard_action_bar_card instanceof \MyECardExtensions\CardData ) {
	return;
}

$my_ecard_action_bar_context = isset( $context ) && is_array( $context ) ? $context : array();
$my_ecard_action_bar_actions = isset( $my_ecard_action_bar_context['action_bar_actions'] ) && is_array( $my_ecard_action_bar_context['action_bar_actions'] )
	? $my_ecard_action_bar_context['action_bar_actions']
	: null;
$my_ecard_action_bar_anchors = isset( $my_ecard_action_bar_context['action_bar_anchors'] ) && is_array( $my_ecard_action_bar_context['action_bar_anchors'] )
	? $my_ecard_action_bar_context['action_bar_anchors']
	: array();

\MyECardExtensions\ActionBar::render( $my_ecard_action_bar_card, $my_ecard_action_bar_actions, $my_ecard_action_bar_anchors );
