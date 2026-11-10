<?php
/**
 * Front-end output.
 *
 * - The [ccd_verse_of_the_day] shortcode prints the readings as inline text
 *   (label and link only), styled by the surrounding theme, so it can go
 *   anywhere: a paragraph, a sidebar, a footer column.
 * - The automatic footer bar wraps the same text in a full-width bar with an
 *   icon. On Kadence it sits directly above the site footer; on other themes
 *   it is printed at the end of the page.
 *
 * @package CCD_Verse_Of_The_Day
 */

defined( 'ABSPATH' ) || exit;

add_action( 'enqueue_block_assets', 'ccd_votd_enqueue_style' );

/**
 * Loads the stylesheet on the front end and in the block editor, so the
 * readings card pattern looks the same in both. It is a small file.
 */
function ccd_votd_enqueue_style() {
	wp_enqueue_style( 'ccd-votd', CCD_VOTD_URL . 'assets/verse.css', array(), CCD_VOTD_VERSION );
}

/**
 * Returns the BibleGateway URL for a set of references, in the translation
 * chosen under Bible Readings > Settings.
 *
 * @param string $references E.g. "Psalm 1; Genesis 1; Matthew 1".
 * @return string Unescaped URL.
 */
function ccd_votd_passage_url( $references ) {
	return 'https://www.biblegateway.com/passage/?' . http_build_query(
		array(
			'search'  => $references,
			'version' => ccd_votd_settings()['version'],
		),
		'',
		'&',
		PHP_QUERY_RFC3986
	);
}

/**
 * Returns today's readings as inline markup, or an empty string if there are none.
 *
 * @param array $args {
 *     Optional.
 *
 *     @type string $label Text before the link. Defaults to the Label setting; empty for none.
 * }
 * @return string
 */
function ccd_votd_render( $args = array() ) {
	$reading = ccd_votd_get_current_reading();
	if ( ! $reading || '' === $reading['title'] ) {
		return '';
	}

	$settings = ccd_votd_settings();
	$label    = isset( $args['label'] ) ? (string) $args['label'] : $settings['label'];
	$url      = ccd_votd_passage_url( $reading['title'] );

	$html = sprintf(
		'<span class="ccd-votd">%s<a class="ccd-votd__link" href="%s" target="_blank" rel="noopener">%s</a></span>',
		'' !== $label ? '<span class="ccd-votd__label">' . esc_html( $label ) . '</span> ' : '',
		esc_url( $url ),
		esc_html( $reading['title'] )
	);

	/**
	 * Filters the inline readings markup, used by both the shortcode and the footer bar.
	 *
	 * @param string $html    The markup.
	 * @param array  $reading The reading: id and title.
	 */
	return (string) apply_filters( 'ccd_votd_html', $html, $reading );
}

add_shortcode( 'ccd_verse_of_the_day', 'ccd_votd_shortcode' );

/**
 * The [ccd_verse_of_the_day] shortcode. Accepts label="..." to change or,
 * with label="", remove the text before the link.
 *
 * @param array|string $atts Shortcode attributes.
 * @return string
 */
function ccd_votd_shortcode( $atts ) {
	$args = array();
	if ( is_array( $atts ) && array_key_exists( 'label', $atts ) ) {
		$args['label'] = sanitize_text_field( $atts['label'] );
	}
	return ccd_votd_render( $args );
}

add_shortcode( 'ccd_reading_plan_link', 'ccd_votd_plan_link_shortcode' );

/**
 * The [ccd_reading_plan_link] shortcode: a link to the reading plan set under
 * Bible Readings > Settings. Prints nothing when no link is set. Accepts
 * text="..." to override the link text.
 *
 * @param array|string $atts Shortcode attributes.
 * @return string
 */
function ccd_votd_plan_link_shortcode( $atts ) {
	$settings = ccd_votd_settings();
	if ( '' === $settings['plan_url'] ) {
		return '';
	}

	$text = $settings['plan_text'];
	if ( is_array( $atts ) && ! empty( $atts['text'] ) ) {
		$text = sanitize_text_field( $atts['text'] );
	}

	return sprintf(
		'<a class="ccd-votd-plan-link" href="%s">%s</a>',
		esc_url( $settings['plan_url'] ),
		esc_html( $text )
	);
}

/**
 * Returns the full-width footer bar, or an empty string if there are no readings.
 *
 * @return string
 */
function ccd_votd_render_bar() {
	$inline = ccd_votd_render();
	if ( '' === $inline ) {
		return '';
	}

	$icon = '<svg class="ccd-votd-bar__icon" aria-hidden="true" focusable="false" viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M12 6.5C10.3 5.2 7.9 4.5 5 4.5H3v13h2c2.9 0 5.3.7 7 2 1.7-1.3 4.1-2 7-2h2v-13h-2c-2.9 0-5.3.7-7 2Z"/><path d="M12 6.5v13"/></svg>';

	return sprintf(
		'<aside class="ccd-votd-bar" aria-label="%s"><div class="ccd-votd-bar__inner">%s%s</div></aside>',
		esc_attr__( "Today's Bible readings", 'ccd-verse-of-the-day' ),
		$icon,
		$inline
	);
}

add_action( 'kadence_before_footer', 'ccd_votd_print_bar' );
add_action( 'wp_footer', 'ccd_votd_print_bar', 5 );

/**
 * Prints the footer bar once per page, when enabled: above the footer on
 * Kadence (kadence_before_footer), otherwise at the end of the page.
 */
function ccd_votd_print_bar() {
	static $printed = false;

	/**
	 * Filters whether the footer bar is shown on the current page.
	 *
	 * @param bool $show Whether to show it. Defaults to the "Show on every page" setting.
	 */
	if ( $printed || ! apply_filters( 'ccd_votd_show_footer_bar', ccd_votd_settings()['auto_footer'] ) ) {
		return;
	}
	$printed = true;

	echo ccd_votd_render_bar(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in ccd_votd_render() and ccd_votd_render_bar().
}
