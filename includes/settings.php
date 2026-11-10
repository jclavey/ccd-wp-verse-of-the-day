<?php
/**
 * Plugin settings: Bible Readings > Settings.
 *
 * @package CCD_Verse_Of_The_Day
 */

defined( 'ABSPATH' ) || exit;

/**
 * Returns the plugin settings merged with their defaults.
 *
 * @return array{auto_footer:bool,label:string,version:string,plan_url:string,plan_text:string}
 */
function ccd_votd_settings() {
	$defaults = array(
		'auto_footer' => true,
		'label'       => __( "Today's Bible Passages:", 'ccd-verse-of-the-day' ),
		'version'     => 'NIVUK',
		'plan_url'    => '',
		'plan_text'   => __( 'Get the reading plan', 'ccd-verse-of-the-day' ),
	);
	return wp_parse_args( (array) get_option( 'ccd_votd_settings', array() ), $defaults );
}

add_action( 'admin_init', 'ccd_votd_register_settings' );

/**
 * Registers the settings and their fields.
 */
function ccd_votd_register_settings() {
	register_setting(
		'ccd_votd',
		'ccd_votd_settings',
		array(
			'type'              => 'array',
			'sanitize_callback' => 'ccd_votd_sanitize_settings',
		)
	);

	add_settings_section( 'ccd_votd_display', __( 'Display', 'ccd-verse-of-the-day' ), '__return_false', 'ccd_votd' );

	add_settings_field(
		'auto_footer',
		__( 'Show on every page', 'ccd-verse-of-the-day' ),
		static function () {
			$settings = ccd_votd_settings();
			printf(
				'<label><input type="checkbox" name="ccd_votd_settings[auto_footer]" value="1" %s> %s</label><p class="description">%s</p>',
				checked( $settings['auto_footer'], true, false ),
				esc_html__( "Show today's readings in a bar above the site footer on every page", 'ccd-verse-of-the-day' ),
				wp_kses(
					__( 'You can also place the readings as plain text anywhere with the <code>[ccd_verse_of_the_day]</code> shortcode, with or without the bar. Use <code>[ccd_verse_of_the_day label=""]</code> to show just the link.', 'ccd-verse-of-the-day' ),
					array( 'code' => array() )
				)
			);
		},
		'ccd_votd',
		'ccd_votd_display'
	);

	add_settings_field(
		'label',
		__( 'Label', 'ccd-verse-of-the-day' ),
		static function () {
			printf(
				'<input type="text" class="regular-text" name="ccd_votd_settings[label]" value="%s">',
				esc_attr( ccd_votd_settings()['label'] )
			);
		},
		'ccd_votd',
		'ccd_votd_display'
	);

	add_settings_field(
		'version',
		__( 'BibleGateway version', 'ccd-verse-of-the-day' ),
		static function () {
			printf(
				'<input type="text" class="small-text" name="ccd_votd_settings[version]" value="%s"><p class="description">%s</p>',
				esc_attr( ccd_votd_settings()['version'] ),
				esc_html__( 'The translation the link opens in, e.g. NIVUK, ESVUK, NRSVA.', 'ccd-verse-of-the-day' )
			);
		},
		'ccd_votd',
		'ccd_votd_display'
	);

	add_settings_section(
		'ccd_votd_plan',
		__( 'Reading plan link', 'ccd-verse-of-the-day' ),
		static function () {
			echo '<p>' . wp_kses(
				__( 'Shown in the Daily Bible Readings card pattern, and anywhere you use the <code>[ccd_reading_plan_link]</code> shortcode. Update it here each year and every card follows. Leave the URL blank to hide the link.', 'ccd-verse-of-the-day' ),
				array( 'code' => array() )
			) . '</p>';
		},
		'ccd_votd'
	);

	add_settings_field(
		'plan_url',
		__( 'URL', 'ccd-verse-of-the-day' ),
		static function () {
			printf(
				'<input type="text" inputmode="url" class="regular-text code" name="ccd_votd_settings[plan_url]" value="%s" placeholder="https://"><p class="description">%s</p>',
				esc_attr( ccd_votd_settings()['plan_url'] ),
				esc_html__( 'A page about the reading plan, or a PDF from the Media Library (copy its File URL).', 'ccd-verse-of-the-day' )
			);
		},
		'ccd_votd',
		'ccd_votd_plan'
	);

	add_settings_field(
		'plan_text',
		__( 'Link text', 'ccd-verse-of-the-day' ),
		static function () {
			printf(
				'<input type="text" class="regular-text" name="ccd_votd_settings[plan_text]" value="%s">',
				esc_attr( ccd_votd_settings()['plan_text'] )
			);
		},
		'ccd_votd',
		'ccd_votd_plan'
	);
}

/**
 * Sanitises the settings form.
 *
 * @param mixed $input Submitted values.
 * @return array
 */
function ccd_votd_sanitize_settings( $input ) {
	$input   = is_array( $input ) ? $input : array();
	$version   = preg_replace( '/[^A-Za-z0-9-]/', '', (string) ( $input['version'] ?? '' ) );
	$plan_text = sanitize_text_field( (string) ( $input['plan_text'] ?? '' ) );

	return array(
		'auto_footer' => ! empty( $input['auto_footer'] ),
		'label'       => sanitize_text_field( (string) ( $input['label'] ?? '' ) ),
		'version'     => '' !== $version ? strtoupper( $version ) : 'NIVUK',
		'plan_url'    => esc_url_raw( trim( (string) ( $input['plan_url'] ?? '' ) ), array( 'http', 'https' ) ),
		'plan_text'   => '' !== $plan_text ? $plan_text : __( 'Get the reading plan', 'ccd-verse-of-the-day' ),
	);
}

add_action( 'admin_menu', 'ccd_votd_add_settings_page' );

/**
 * Adds Bible Readings > Settings.
 */
function ccd_votd_add_settings_page() {
	add_submenu_page(
		'edit.php?post_type=' . CCD_VOTD_POST_TYPE,
		__( 'Verse of the Day Settings', 'ccd-verse-of-the-day' ),
		__( 'Settings', 'ccd-verse-of-the-day' ),
		'manage_options',
		'ccd-votd-settings',
		static function () {
			?>
			<div class="wrap">
				<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
				<form action="options.php" method="post">
					<?php
					settings_fields( 'ccd_votd' );
					do_settings_sections( 'ccd_votd' );
					submit_button();
					?>
				</form>
			</div>
			<?php
		}
	);
}

add_filter( 'plugin_action_links_' . plugin_basename( CCD_VOTD_FILE ), 'ccd_votd_plugin_action_links' );

/**
 * Adds a Settings link to the plugin's row on the Plugins screen.
 *
 * @param string[] $links Action links (Deactivate, etc.).
 * @return string[]
 */
function ccd_votd_plugin_action_links( $links ) {
	if ( current_user_can( 'manage_options' ) ) {
		array_unshift(
			$links,
			sprintf(
				'<a href="%s">%s</a>',
				esc_url( admin_url( 'edit.php?post_type=' . CCD_VOTD_POST_TYPE . '&page=ccd-votd-settings' ) ),
				esc_html__( 'Settings', 'ccd-verse-of-the-day' )
			)
		);
	}
	return $links;
}
