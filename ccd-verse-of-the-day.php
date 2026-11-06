<?php
/**
 * Plugin Name:       CCD Verse of the Day
 * Plugin URI:        https://github.com/jclavey/ccd-wp-verse-of-the-day
 * Description:       Imports a year of daily Bible readings from the reading-plan spreadsheet and shows today's readings on every page.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Jonathan Clavey
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       ccd-verse-of-the-day
 *
 * @package CCD_Verse_Of_The_Day
 */

defined( 'ABSPATH' ) || exit;

define( 'CCD_VOTD_VERSION', '1.0.0' );
define( 'CCD_VOTD_FILE', __FILE__ );
define( 'CCD_VOTD_DIR', plugin_dir_path( __FILE__ ) );
define( 'CCD_VOTD_URL', plugin_dir_url( __FILE__ ) );
define( 'CCD_VOTD_POST_TYPE', 'ccd_reading' );

require_once CCD_VOTD_DIR . 'includes/post-type.php';
require_once CCD_VOTD_DIR . 'includes/query.php';
require_once CCD_VOTD_DIR . 'includes/settings.php';
require_once CCD_VOTD_DIR . 'includes/display.php';
require_once CCD_VOTD_DIR . 'includes/patterns.php';

if ( is_admin() ) {
	require_once CCD_VOTD_DIR . 'includes/xlsx-reader.php';
	require_once CCD_VOTD_DIR . 'includes/importer.php';
}

register_activation_hook(
	__FILE__,
	static function () {
		ccd_votd_register_post_type();
		flush_rewrite_rules();
	}
);

register_deactivation_hook( __FILE__, 'flush_rewrite_rules' );
