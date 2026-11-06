<?php
/**
 * Bible Readings > Import: loads a year's reading plan from the .xlsx workbook.
 *
 * Expected layout (the "CCD Bible Reading Plan YYYY.xlsx" workbook):
 *  - one worksheet per month, named after the month ("January", "February", ...);
 *  - row 1 is a header row;
 *  - column B is the day of the month, and columns C, D and E are the Psalm,
 *    Old Testament and New Testament references.
 *
 * The year comes from the four digits in the file name, or from the form.
 *
 * Importing happens in two steps. Uploading the workbook only shows a preview
 * of what would be added or changed; nothing is saved until the preview is
 * confirmed. Each day is matched by date, so a corrected workbook updates
 * existing readings instead of duplicating them.
 *
 * @package CCD_Verse_Of_The_Day
 */

defined( 'ABSPATH' ) || exit;

add_action( 'admin_menu', 'ccd_votd_add_import_page' );

/**
 * Adds Bible Readings > Import.
 */
function ccd_votd_add_import_page() {
	add_submenu_page(
		'edit.php?post_type=' . CCD_VOTD_POST_TYPE,
		__( 'Import Reading Plan', 'ccd-verse-of-the-day' ),
		__( 'Import', 'ccd-verse-of-the-day' ),
		'manage_options',
		'ccd-votd-import',
		'ccd_votd_render_import_page'
	);
}

/**
 * Renders the import page: the upload form, a preview, or the import result.
 */
function ccd_votd_render_import_page() {
	$error   = null;
	$preview = null;
	$report  = null;

	if ( isset( $_POST['ccd_votd_preview'] ) ) {
		check_admin_referer( 'ccd_votd_preview' );
		$preview = ccd_votd_handle_upload();
		if ( is_wp_error( $preview ) ) {
			$error   = $preview;
			$preview = null;
		}
	} elseif ( isset( $_POST['ccd_votd_confirm'] ) ) {
		check_admin_referer( 'ccd_votd_confirm' );
		$report = ccd_votd_handle_confirm( sanitize_key( wp_unslash( $_POST['ccd_votd_token'] ?? '' ) ) );
		if ( is_wp_error( $report ) ) {
			$error  = $report;
			$report = null;
		}
	}
	?>
	<div class="wrap">
		<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
		<?php
		if ( $error ) {
			printf( '<div class="notice notice-error"><p>%s</p></div>', esc_html( $error->get_error_message() ) );
		}

		if ( $preview ) {
			ccd_votd_render_preview( $preview );
		} else {
			if ( $report ) {
				ccd_votd_render_import_report( $report );
			}
			ccd_votd_render_upload_form();
		}
		?>
	</div>
	<?php
}

/**
 * Prints the upload form.
 */
function ccd_votd_render_upload_form() {
	?>
	<p><?php esc_html_e( 'Upload the reading plan workbook (.xlsx). It needs one sheet per month named after the month, with the day in column B and the Psalm, Old Testament and New Testament readings in columns C, D and E. Row 1 is treated as a header.', 'ccd-verse-of-the-day' ); ?></p>
	<p><?php esc_html_e( 'You will see a preview of every change before anything is saved. Importing a corrected file again updates existing days rather than duplicating them.', 'ccd-verse-of-the-day' ); ?></p>

	<form method="post" enctype="multipart/form-data">
		<?php wp_nonce_field( 'ccd_votd_preview' ); ?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="ccd_votd_file"><?php esc_html_e( 'Workbook', 'ccd-verse-of-the-day' ); ?></label></th>
				<td><input type="file" id="ccd_votd_file" name="ccd_votd_file" accept=".xlsx" required></td>
			</tr>
			<tr>
				<th scope="row"><label for="ccd_votd_year"><?php esc_html_e( 'Year', 'ccd-verse-of-the-day' ); ?></label></th>
				<td>
					<input type="number" id="ccd_votd_year" name="ccd_votd_year" class="small-text" min="2000" max="2100" step="1">
					<p class="description"><?php esc_html_e( 'Leave blank to take the year from the file name, e.g. "CCD Bible Reading Plan 2026.xlsx".', 'ccd-verse-of-the-day' ); ?></p>
				</td>
			</tr>
		</table>
		<?php submit_button( __( 'Preview import', 'ccd-verse-of-the-day' ), 'primary', 'ccd_votd_preview' ); ?>
	</form>
	<?php
}

/**
 * Validates the uploaded file, parses it and builds a preview. Saves nothing
 * except the parsed readings, held for an hour until the preview is confirmed.
 *
 * @return array|WP_Error The preview (see ccd_votd_build_preview()) plus a token, or an error.
 */
function ccd_votd_handle_upload() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return new WP_Error( 'ccd_votd_forbidden', __( 'You do not have permission to import readings.', 'ccd-verse-of-the-day' ) );
	}

	// phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- validated below; tmp_name is checked with is_uploaded_file().
	$file = $_FILES['ccd_votd_file'] ?? null;
	if ( ! $file || UPLOAD_ERR_OK !== $file['error'] || ! is_uploaded_file( $file['tmp_name'] ) ) {
		return new WP_Error( 'ccd_votd_upload', __( 'The file did not upload. Please choose a file and try again.', 'ccd-verse-of-the-day' ) );
	}

	$name = sanitize_file_name( wp_unslash( $file['name'] ) );
	if ( 'xlsx' !== strtolower( pathinfo( $name, PATHINFO_EXTENSION ) ) ) {
		return new WP_Error( 'ccd_votd_type', __( 'Please upload an .xlsx file.', 'ccd-verse-of-the-day' ) );
	}

	$year = isset( $_POST['ccd_votd_year'] ) ? absint( $_POST['ccd_votd_year'] ) : 0;
	if ( ! $year ) {
		$year = ccd_votd_year_from_filename( $name );
	}
	if ( $year < 2000 || $year > 2100 ) {
		return new WP_Error( 'ccd_votd_year', __( 'Could not work out the year. Enter it in the Year box.', 'ccd-verse-of-the-day' ) );
	}

	$sheets = ccd_votd_read_xlsx( $file['tmp_name'] );
	if ( is_wp_error( $sheets ) ) {
		return $sheets;
	}

	$parsed = ccd_votd_parse_sheets( $sheets, $year );
	if ( ! $parsed['readings'] ) {
		return new WP_Error( 'ccd_votd_empty', __( 'No readings were found in the file. Check that it uses the layout described below.', 'ccd-verse-of-the-day' ) );
	}

	$token = strtolower( wp_generate_password( 20, false ) );
	set_transient( ccd_votd_pending_key( $token ), $parsed, HOUR_IN_SECONDS );

	$preview          = ccd_votd_build_preview( $parsed['readings'], $year );
	$preview['token'] = $token;
	$preview['file']  = $name;
	$preview['problems'] = $parsed['problems'];

	return $preview;
}

/**
 * Applies a previewed import.
 *
 * @param string $token Token from the preview form.
 * @return array|WP_Error Report from ccd_votd_apply_import(), or an error.
 */
function ccd_votd_handle_confirm( $token ) {
	if ( ! current_user_can( 'manage_options' ) ) {
		return new WP_Error( 'ccd_votd_forbidden', __( 'You do not have permission to import readings.', 'ccd-verse-of-the-day' ) );
	}

	$key    = ccd_votd_pending_key( $token );
	$parsed = '' !== $token ? get_transient( $key ) : false;
	if ( ! is_array( $parsed ) || empty( $parsed['readings'] ) ) {
		return new WP_Error( 'ccd_votd_expired', __( 'That preview has expired. Please upload the file again.', 'ccd-verse-of-the-day' ) );
	}
	delete_transient( $key );

	$report             = ccd_votd_apply_import( $parsed['readings'], $parsed['year'] );
	$report['problems'] = array_merge( $parsed['problems'], $report['problems'] );
	return $report;
}

/**
 * Returns the transient key for a pending import. Keyed by user, so one
 * user's preview cannot be confirmed by another.
 *
 * @param string $token Preview token.
 * @return string
 */
function ccd_votd_pending_key( $token ) {
	return 'ccd_votd_pending_' . get_current_user_id() . '_' . $token;
}

/**
 * Finds the year in a file name such as "CCD Bible Reading Plan 2026.xlsx":
 * the last four-digit number before the extension.
 *
 * @param string $name File name.
 * @return int The year, or 0.
 */
function ccd_votd_year_from_filename( $name ) {
	return preg_match( '/(\d{4})\D*$/', pathinfo( $name, PATHINFO_FILENAME ), $matches ) ? (int) $matches[1] : 0;
}

/**
 * Turns workbook rows into readings: one per day, keyed by Y-m-d.
 *
 * @param array $sheets Sheets from ccd_votd_read_xlsx().
 * @param int   $year   The plan's year.
 * @return array{year:int,readings:array<string,string>,problems:string[]}
 */
function ccd_votd_parse_sheets( array $sheets, $year ) {
	$readings = array();
	$problems = array();

	foreach ( $sheets as $sheet_name => $rows ) {
		$sheet_name = (string) $sheet_name;
		$month      = ccd_votd_month_number( $sheet_name );
		if ( ! $month ) {
			/* translators: %s: worksheet name. */
			$problems[] = sprintf( __( 'Skipped sheet "%s": its name is not a month.', 'ccd-verse-of-the-day' ), $sheet_name );
			continue;
		}

		foreach ( $rows as $row_num => $cells ) {
			if ( $row_num < 2 ) {
				continue; // Header row.
			}

			$day   = trim( $cells['B'] ?? '' );
			$parts = array_values(
				array_filter(
					array_map( 'trim', array( $cells['C'] ?? '', $cells['D'] ?? '', $cells['E'] ?? '' ) ),
					'strlen'
				)
			);

			if ( '' === $day && ! $parts ) {
				continue; // Blank row.
			}

			if ( ! ctype_digit( $day ) || ! checkdate( $month, (int) $day, $year ) ) {
				/* translators: 1: worksheet name, 2: row number, 3: cell value. */
				$problems[] = sprintf( __( 'Skipped %1$s row %2$d: "%3$s" is not a day of the month.', 'ccd-verse-of-the-day' ), $sheet_name, $row_num, $day );
				continue;
			}

			if ( ! $parts ) {
				/* translators: 1: worksheet name, 2: row number. */
				$problems[] = sprintf( __( 'Skipped %1$s row %2$d: no readings.', 'ccd-verse-of-the-day' ), $sheet_name, $row_num );
				continue;
			}

			$date = sprintf( '%04d-%02d-%02d', $year, $month, (int) $day );
			if ( isset( $readings[ $date ] ) ) {
				/* translators: 1: worksheet name, 2: row number, 3: date. */
				$problems[] = sprintf( __( '%1$s row %2$d repeats %3$s; the later row was used.', 'ccd-verse-of-the-day' ), $sheet_name, $row_num, $date );
			}
			$readings[ $date ] = implode( '; ', $parts );
		}
	}

	ksort( $readings );
	return array(
		'year'     => (int) $year,
		'readings' => $readings,
		'problems' => $problems,
	);
}

/**
 * Returns the readings already saved for a year, keyed by date.
 *
 * @param int $year Year.
 * @return array<string, WP_Post>
 */
function ccd_votd_existing_readings( $year ) {
	$existing = array();
	$posts    = get_posts(
		array(
			'post_type'      => CCD_VOTD_POST_TYPE,
			'post_status'    => array( 'publish', 'future', 'draft', 'pending', 'private' ),
			'posts_per_page' => -1,
			'no_found_rows'  => true,
			'orderby'        => 'date',
			'order'          => 'ASC',
			'date_query'     => array( array( 'year' => (int) $year ) ),
		)
	);
	foreach ( $posts as $post ) {
		$existing[ substr( $post->post_date, 0, 10 ) ] = $post;
	}
	return $existing;
}

/**
 * Works out what an import would do, without changing anything.
 *
 * @param array<string,string> $readings Readings keyed by date.
 * @param int                  $year     Year.
 * @return array{year:int,rows:array,counts:array{add:int,update:int,unchanged:int}}
 */
function ccd_votd_build_preview( array $readings, $year ) {
	$existing = ccd_votd_existing_readings( $year );
	$rows     = array();
	$counts   = array(
		'add'       => 0,
		'update'    => 0,
		'unchanged' => 0,
	);

	foreach ( $readings as $date => $title ) {
		$post = $existing[ $date ] ?? null;
		if ( ! $post ) {
			$action = 'add';
		} elseif ( $post->post_title !== $title || 'publish' !== $post->post_status ) {
			$action = 'update';
		} else {
			$action = 'unchanged';
		}

		++$counts[ $action ];
		$rows[] = array(
			'date'   => $date,
			'title'  => $title,
			'action' => $action,
			'old'    => $post ? $post->post_title : '',
		);
	}

	return array(
		'year'   => (int) $year,
		'rows'   => $rows,
		'counts' => $counts,
	);
}

/**
 * Creates or updates one reading per day.
 *
 * @param array<string,string> $readings Readings keyed by date.
 * @param int                  $year     Year.
 * @return array{year:int,added:int,updated:int,unchanged:int,problems:string[]}
 */
function ccd_votd_apply_import( array $readings, $year ) {
	$existing = ccd_votd_existing_readings( $year );
	$report   = array(
		'year'      => (int) $year,
		'added'     => 0,
		'updated'   => 0,
		'unchanged' => 0,
		'problems'  => array(),
	);

	foreach ( $readings as $date => $title ) {
		$post = $existing[ $date ] ?? null;

		if ( $post && $post->post_title === $title && 'publish' === $post->post_status ) {
			++$report['unchanged'];
			continue;
		}

		if ( $post ) {
			$id  = wp_update_post(
				array(
					'ID'          => $post->ID,
					'post_title'  => $title,
					'post_status' => 'publish',
				),
				true
			);
			$key = 'updated';
		} else {
			$id  = wp_insert_post(
				array(
					'post_type'   => CCD_VOTD_POST_TYPE,
					'post_title'  => $title,
					'post_status' => 'publish',
					'post_date'   => $date . ' 00:00:00',
				),
				true
			);
			$key = 'added';
		}

		if ( is_wp_error( $id ) ) {
			/* translators: 1: date, 2: error message. */
			$report['problems'][] = sprintf( __( 'Could not save %1$s: %2$s', 'ccd-verse-of-the-day' ), $date, $id->get_error_message() );
		} else {
			++$report[ $key ];
		}
	}

	return $report;
}

/**
 * Returns the month number for a sheet name such as "January" or "Jan".
 *
 * @param string $name Sheet name.
 * @return int 1-12, or 0 if the name is not a month.
 */
function ccd_votd_month_number( $name ) {
	$name = strtolower( trim( $name ) );
	if ( strlen( $name ) < 3 ) {
		return 0;
	}

	$months = array( 'january', 'february', 'march', 'april', 'may', 'june', 'july', 'august', 'september', 'october', 'november', 'december' );
	foreach ( $months as $index => $month ) {
		if ( 0 === strpos( $month, $name ) ) {
			return $index + 1;
		}
	}
	return 0;
}

/**
 * Prints a list of skipped rows and other problems.
 *
 * @param string[] $problems Messages.
 */
function ccd_votd_render_problems( array $problems ) {
	if ( ! $problems ) {
		return;
	}
	echo '<ul style="list-style:disc;padding-left:2em">';
	foreach ( $problems as $problem ) {
		echo '<li>' . esc_html( $problem ) . '</li>';
	}
	echo '</ul>';
}

/**
 * Prints the preview table and the confirm button.
 *
 * @param array $preview Preview from ccd_votd_handle_upload().
 */
function ccd_votd_render_preview( array $preview ) {
	$counts  = $preview['counts'];
	$labels  = array(
		'add'       => __( 'Add', 'ccd-verse-of-the-day' ),
		'update'    => __( 'Update', 'ccd-verse-of-the-day' ),
		'unchanged' => __( 'Unchanged', 'ccd-verse-of-the-day' ),
	);
	$changes = $counts['add'] + $counts['update'];
	?>
	<h2>
		<?php
		/* translators: 1: file name, 2: year. */
		echo esc_html( sprintf( __( 'Preview: %1$s (%2$d)', 'ccd-verse-of-the-day' ), $preview['file'], $preview['year'] ) );
		?>
	</h2>

	<div class="notice notice-info inline">
		<p>
			<?php
			echo esc_html(
				sprintf(
					/* translators: 1: readings to add, 2: readings to update, 3: unchanged readings. */
					__( 'Nothing has been saved yet. Importing will add %1$d readings, update %2$d and leave %3$d unchanged.', 'ccd-verse-of-the-day' ),
					$counts['add'],
					$counts['update'],
					$counts['unchanged']
				)
			);
			?>
		</p>
		<?php ccd_votd_render_problems( $preview['problems'] ); ?>
	</div>

	<form method="post">
		<?php wp_nonce_field( 'ccd_votd_confirm' ); ?>
		<input type="hidden" name="ccd_votd_token" value="<?php echo esc_attr( $preview['token'] ); ?>">
		<p>
			<?php
			submit_button( __( 'Import', 'ccd-verse-of-the-day' ), 'primary', 'ccd_votd_confirm', false, $changes ? array() : array( 'disabled' => 'disabled' ) );
			?>
			<a class="button" href="<?php echo esc_url( menu_page_url( 'ccd-votd-import', false ) ); ?>"><?php esc_html_e( 'Cancel', 'ccd-verse-of-the-day' ); ?></a>
		</p>
	</form>

	<table class="widefat striped">
		<thead>
			<tr>
				<th scope="col"><?php esc_html_e( 'Date', 'ccd-verse-of-the-day' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Readings', 'ccd-verse-of-the-day' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Change', 'ccd-verse-of-the-day' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $preview['rows'] as $row ) : ?>
				<tr>
					<td><?php echo esc_html( wp_date( get_option( 'date_format' ), strtotime( $row['date'] . ' 12:00:00 UTC' ), new DateTimeZone( 'UTC' ) ) ); ?></td>
					<td>
						<?php echo esc_html( $row['title'] ); ?>
						<?php if ( 'update' === $row['action'] && $row['old'] !== $row['title'] ) : ?>
							<br><del style="color:#787c82"><?php echo esc_html( $row['old'] ); ?></del>
						<?php endif; ?>
					</td>
					<td><?php echo 'unchanged' === $row['action'] ? esc_html( $labels['unchanged'] ) : '<strong>' . esc_html( $labels[ $row['action'] ] ) . '</strong>'; ?></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
	<?php
}

/**
 * Prints the result of an import.
 *
 * @param array $report Report from ccd_votd_apply_import().
 */
function ccd_votd_render_import_report( array $report ) {
	$class = $report['problems'] ? 'notice-warning' : 'notice-success';
	printf(
		'<div class="notice %s"><p>%s</p>',
		esc_attr( $class ),
		esc_html(
			sprintf(
				/* translators: 1: year, 2: added count, 3: updated count, 4: unchanged count. */
				__( 'Imported %1$d: %2$d added, %3$d updated, %4$d unchanged.', 'ccd-verse-of-the-day' ),
				$report['year'],
				$report['added'],
				$report['updated'],
				$report['unchanged']
			)
		)
	);
	ccd_votd_render_problems( $report['problems'] );
	echo '</div>';
}
