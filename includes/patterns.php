<?php
/**
 * Block pattern: "Daily Bible Readings card".
 *
 * A card showing the eyebrow "DAILY BIBLE READINGS", today's readings and a
 * link to the reading plan. Built from core blocks so it works with any theme;
 * the card's look (background, shadow, accent stripe, icon) comes from the
 * .ccd-readings-card rules in assets/verse.css.
 *
 * @package CCD_Verse_Of_The_Day
 */

defined( 'ABSPATH' ) || exit;

add_action( 'init', 'ccd_votd_register_patterns' );

/**
 * Registers the pattern and its category.
 */
function ccd_votd_register_patterns() {
	if ( ! function_exists( 'register_block_pattern' ) ) {
		return;
	}

	register_block_pattern_category(
		'ccd',
		array( 'label' => __( 'Christ Church Dunstable', 'ccd-verse-of-the-day' ) )
	);

	register_block_pattern(
		'ccd-verse-of-the-day/readings-card',
		array(
			'title'       => __( 'Daily Bible Readings card', 'ccd-verse-of-the-day' ),
			'description' => __( "A card with today's Bible readings and a link to the reading plan.", 'ccd-verse-of-the-day' ),
			'categories'  => array( 'ccd' ),
			'keywords'    => array( 'bible', 'reading', 'verse', 'scripture' ),
			'content'     => ccd_votd_readings_card_pattern(),
		)
	);
}

/**
 * Returns the pattern's block markup.
 *
 * @return string
 */
function ccd_votd_readings_card_pattern() {
	$eyebrow = esc_html__( 'Daily Bible Readings', 'ccd-verse-of-the-day' );

	return <<<HTML
<!-- wp:group {"className":"ccd-readings-card","layout":{"type":"flex","flexWrap":"wrap","verticalAlignment":"center"}} -->
<div class="wp-block-group ccd-readings-card"><!-- wp:group {"className":"ccd-readings-card__text","layout":{"type":"default"}} -->
<div class="wp-block-group ccd-readings-card__text"><!-- wp:paragraph {"className":"ccd-readings-card__eyebrow"} -->
<p class="ccd-readings-card__eyebrow">{$eyebrow}</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"className":"ccd-readings-card__readings"} -->
<p class="ccd-readings-card__readings">[ccd_verse_of_the_day label=""]</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->

<!-- wp:paragraph {"className":"ccd-readings-card__more"} -->
<p class="ccd-readings-card__more">[ccd_reading_plan_link]</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->
HTML;
}
