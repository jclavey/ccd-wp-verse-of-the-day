<?php
/**
 * Looks up today's reading, with a cache that lasts until midnight.
 *
 * @package CCD_Verse_Of_The_Day
 */

defined( 'ABSPATH' ) || exit;

/**
 * Returns the transient key for today's reading, in the site's timezone.
 *
 * @return string
 */
function ccd_votd_cache_key() {
	return 'ccd_votd_' . wp_date( 'Ymd' );
}

/**
 * Returns the reading for today: the latest published reading dated today or
 * earlier. Returns null when there is none.
 *
 * @return array{id:int,title:string}|null
 */
function ccd_votd_get_current_reading() {
	$key    = ccd_votd_cache_key();
	$cached = get_transient( $key );

	if ( is_array( $cached ) ) {
		return $cached['id'] ? $cached : null;
	}

	$ids = get_posts(
		array(
			'post_type'        => CCD_VOTD_POST_TYPE,
			'post_status'      => array( 'publish', 'future' ),
			'posts_per_page'   => 1,
			'orderby'          => 'date',
			'order'            => 'DESC',
			'fields'           => 'ids',
			'no_found_rows'    => true,
			'suppress_filters' => false,
			'date_query'       => array(
				array(
					'before'    => current_time( 'mysql' ),
					'inclusive' => true,
				),
			),
		)
	);

	$reading = array(
		'id'    => $ids ? (int) $ids[0] : 0,
		'title' => $ids ? get_the_title( $ids[0] ) : '',
	);

	// Cache until midnight, site time. Empty results are cached too.
	$midnight = new DateTimeImmutable( 'tomorrow', wp_timezone() );
	set_transient( $key, $reading, max( MINUTE_IN_SECONDS, $midnight->getTimestamp() - time() ) );

	return $reading['id'] ? $reading : null;
}

add_action( 'clean_post_cache', 'ccd_votd_flush_cache', 10, 2 );

/**
 * Clears today's cached reading whenever a reading is saved, trashed or deleted.
 *
 * @param int     $post_id Post ID.
 * @param WP_Post $post    Post object.
 */
function ccd_votd_flush_cache( $post_id, $post ) {
	if ( $post instanceof WP_Post && CCD_VOTD_POST_TYPE === $post->post_type ) {
		delete_transient( ccd_votd_cache_key() );
	}
}
