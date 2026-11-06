<?php
/**
 * The Bible reading post type.
 *
 * One post per day. The title holds that day's references, e.g.
 * "Psalm 1; Genesis 1; Matthew 1", and the post date is the day it applies to.
 *
 * @package CCD_Verse_Of_The_Day
 */

defined( 'ABSPATH' ) || exit;

add_action( 'init', 'ccd_votd_register_post_type' );

/**
 * Registers the reading post type. Readings are managed in the admin only and
 * have no public URLs.
 */
function ccd_votd_register_post_type() {
	register_post_type(
		CCD_VOTD_POST_TYPE,
		array(
			'labels'          => array(
				'name'               => __( 'Bible Readings', 'ccd-verse-of-the-day' ),
				'singular_name'      => __( 'Bible Reading', 'ccd-verse-of-the-day' ),
				'menu_name'          => __( 'Bible Readings', 'ccd-verse-of-the-day' ),
				'add_new_item'       => __( 'Add New Reading', 'ccd-verse-of-the-day' ),
				'edit_item'          => __( 'Edit Reading', 'ccd-verse-of-the-day' ),
				'new_item'           => __( 'New Reading', 'ccd-verse-of-the-day' ),
				'search_items'       => __( 'Search Readings', 'ccd-verse-of-the-day' ),
				'not_found'          => __( 'No readings found.', 'ccd-verse-of-the-day' ),
				'not_found_in_trash' => __( 'No readings found in Trash.', 'ccd-verse-of-the-day' ),
				'all_items'          => __( 'All Readings', 'ccd-verse-of-the-day' ),
			),
			'public'          => false,
			'show_ui'         => true,
			'show_in_menu'    => true,
			// Off on purpose: the REST API would serve every published
			// reading, including future days, to logged-out visitors.
			// Readings only have a title, so the classic edit screen is enough.
			'show_in_rest'    => false,
			'menu_icon'       => 'dashicons-book-alt',
			'supports'        => array( 'title' ),
			'has_archive'     => false,
			'rewrite'         => false,
			'query_var'       => false,
			'capability_type' => 'post',
		)
	);
}

add_filter( 'wp_insert_post_data', 'ccd_votd_keep_readings_published', 10, 2 );

/**
 * Stores future-dated readings as "publish" rather than "future".
 *
 * WordPress normally schedules future-dated posts and relies on WP-Cron to
 * publish them, which misses its schedule on quiet sites. Readings are not
 * public, and the front end only ever shows the latest one dated today or
 * earlier, so they can be published up front instead. This also avoids
 * adding one cron event per day of the year.
 *
 * @param array $data    Sanitised post data about to be saved.
 * @param array $postarr Raw post data passed to wp_insert_post().
 * @return array
 */
function ccd_votd_keep_readings_published( $data, $postarr ) {
	if ( CCD_VOTD_POST_TYPE === $data['post_type'] && 'future' === $data['post_status'] ) {
		$data['post_status'] = 'publish';
	}
	return $data;
}
