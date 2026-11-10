<?php
/**
 * REST endpoint for today's reading, for external services.
 *
 * GET /wp-json/ccd-votd/v1/today
 *
 * Returns the same reading the footer bar and shortcode show, through the
 * same cache (ccd_votd_get_current_reading()). Only today's reading is
 * exposed: the post type itself stays out of the REST API so future days
 * remain hidden.
 *
 * @package CCD_Verse_Of_The_Day
 */

defined( 'ABSPATH' ) || exit;

add_action( 'rest_api_init', 'ccd_votd_register_rest_routes' );

/**
 * Registers the ccd-votd/v1/today route.
 */
function ccd_votd_register_rest_routes() {
	register_rest_route(
		'ccd-votd/v1',
		'/today',
		array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => 'ccd_votd_rest_today',
			// Public: it is the same text every visitor sees on every page.
			'permission_callback' => '__return_true',
		)
	);
}

/**
 * Handles GET ccd-votd/v1/today.
 *
 * @return WP_REST_Response|WP_Error
 */
function ccd_votd_rest_today() {
	$reading = ccd_votd_get_current_reading();

	if ( ! $reading || '' === $reading['title'] ) {
		return new WP_Error(
			'ccd_votd_no_reading',
			__( 'There is no reading for today.', 'ccd-verse-of-the-day' ),
			array( 'status' => 404 )
		);
	}

	$data = array(
		'date'       => get_post_time( 'Y-m-d', false, $reading['id'] ),
		'references' => $reading['title'],
		'url'        => ccd_votd_passage_url( $reading['title'] ),
		'version'    => ccd_votd_settings()['version'],
	);

	// Each passage on its own: reading1, reading2, … in the order given.
	$passages = array_values( array_filter( array_map( 'trim', explode( ';', $reading['title'] ) ), 'strlen' ) );
	foreach ( $passages as $i => $passage ) {
		$data[ 'reading' . ( $i + 1 ) ] = $passage;
	}

	/**
	 * Filters the REST response data for today's reading.
	 *
	 * @param array $data    Response data: date, references, url, version, reading1, reading2, ….
	 * @param array $reading The reading: id and title.
	 */
	return rest_ensure_response( apply_filters( 'ccd_votd_rest_today', $data, $reading ) );
}
