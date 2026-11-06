<?php
/**
 * A minimal .xlsx reader using PHP's ZipArchive and SimpleXML.
 *
 * It reads cell values only (no formulas, styles or dates), which is all the
 * reading plan needs, so there is no need to bundle PhpSpreadsheet.
 *
 * @package CCD_Verse_Of_The_Day
 */

defined( 'ABSPATH' ) || exit;

/**
 * Reads every worksheet in an .xlsx file.
 *
 * @param string $path Path to the .xlsx file.
 * @return array<string, array<int, array<string, string>>>|WP_Error
 *         Sheets in workbook order, keyed by sheet name, then row number, then column letter.
 */
function ccd_votd_read_xlsx( $path ) {
	if ( ! class_exists( 'ZipArchive' ) ) {
		return new WP_Error( 'ccd_votd_no_zip', __( 'The PHP zip extension is not installed on this server, so .xlsx files cannot be read.', 'ccd-verse-of-the-day' ) );
	}

	$zip = new ZipArchive();
	if ( true !== $zip->open( $path ) ) {
		return new WP_Error( 'ccd_votd_bad_file', __( 'The file could not be opened. Is it an .xlsx spreadsheet?', 'ccd-verse-of-the-day' ) );
	}

	$workbook = ccd_votd_xlsx_load( $zip, 'xl/workbook.xml' );
	$rels     = ccd_votd_xlsx_load( $zip, 'xl/_rels/workbook.xml.rels' );
	if ( ! $workbook || ! $rels || ! isset( $workbook->sheets->sheet ) ) {
		$zip->close();
		return new WP_Error( 'ccd_votd_bad_file', __( 'The file is not a valid .xlsx spreadsheet.', 'ccd-verse-of-the-day' ) );
	}

	$targets = array();
	foreach ( $rels->Relationship as $rel ) {
		$targets[ (string) $rel['Id'] ] = (string) $rel['Target'];
	}

	$strings = array();
	$shared  = ccd_votd_xlsx_load( $zip, 'xl/sharedStrings.xml' );
	if ( $shared ) {
		foreach ( $shared->si as $si ) {
			$strings[] = ccd_votd_xlsx_text( $si );
		}
	}

	$sheets = array();
	foreach ( $workbook->sheets->sheet as $sheet ) {
		$rid = (string) $sheet->attributes( 'http://schemas.openxmlformats.org/officeDocument/2006/relationships' )->id;
		if ( ! isset( $targets[ $rid ] ) ) {
			continue;
		}

		// Targets are usually relative to xl/, but may be absolute within the package.
		$target = $targets[ $rid ];
		$target = '/' === substr( $target, 0, 1 ) ? ltrim( $target, '/' ) : 'xl/' . $target;

		$xml = ccd_votd_xlsx_load( $zip, $target );
		if ( ! $xml || ! isset( $xml->sheetData ) ) {
			continue;
		}

		$rows    = array();
		$row_num = 0;
		foreach ( $xml->sheetData->row as $row ) {
			$row_num = isset( $row['r'] ) ? (int) $row['r'] : $row_num + 1;
			$col_num = 0;

			foreach ( $row->c as $cell ) {
				if ( isset( $cell['r'] ) ) {
					$column  = preg_replace( '/\d+$/', '', (string) $cell['r'] );
					$col_num = ccd_votd_xlsx_column_number( $column );
				} else {
					++$col_num;
					$column = ccd_votd_xlsx_column_letters( $col_num );
				}

				switch ( (string) $cell['t'] ) {
					case 's':
						$value = $strings[ (int) $cell->v ] ?? '';
						break;
					case 'inlineStr':
						$value = isset( $cell->is ) ? ccd_votd_xlsx_text( $cell->is ) : '';
						break;
					default:
						$value = (string) $cell->v;
				}

				$rows[ $row_num ][ $column ] = $value;
			}
		}

		$sheets[ (string) $sheet['name'] ] = $rows;
	}

	$zip->close();
	return $sheets;
}

/**
 * Loads one XML part from the package. Refuses parts over 20 MB uncompressed.
 *
 * @param ZipArchive $zip  The open package.
 * @param string     $name Part name, e.g. xl/workbook.xml.
 * @return SimpleXMLElement|null
 */
function ccd_votd_xlsx_load( ZipArchive $zip, $name ) {
	$stat = $zip->statName( $name );
	if ( ! $stat || $stat['size'] > 20 * MB_IN_BYTES ) {
		return null;
	}

	$contents = $zip->getFromName( $name );
	if ( false === $contents ) {
		return null;
	}

	$previous = libxml_use_internal_errors( true );
	$xml      = simplexml_load_string( $contents, 'SimpleXMLElement', LIBXML_NONET );
	libxml_clear_errors();
	libxml_use_internal_errors( $previous );

	return false === $xml ? null : $xml;
}

/**
 * Returns the text of a shared or inline string, including rich-text runs.
 *
 * @param SimpleXMLElement $node An <si> or <is> element.
 * @return string
 */
function ccd_votd_xlsx_text( SimpleXMLElement $node ) {
	if ( isset( $node->t ) ) {
		return (string) $node->t;
	}

	$text = '';
	foreach ( $node->r as $run ) {
		$text .= (string) $run->t;
	}
	return $text;
}

/**
 * Converts column letters to a 1-based number: A => 1, Z => 26, AA => 27.
 *
 * @param string $letters Column letters.
 * @return int
 */
function ccd_votd_xlsx_column_number( $letters ) {
	$number = 0;
	foreach ( str_split( strtoupper( $letters ) ) as $letter ) {
		$number = $number * 26 + ( ord( $letter ) - 64 );
	}
	return $number;
}

/**
 * Converts a 1-based column number to letters: 1 => A, 27 => AA.
 *
 * @param int $number Column number.
 * @return string
 */
function ccd_votd_xlsx_column_letters( $number ) {
	$letters = '';
	while ( $number > 0 ) {
		$remainder = ( $number - 1 ) % 26;
		$letters   = chr( 65 + $remainder ) . $letters;
		$number    = intdiv( $number - 1, 26 );
	}
	return $letters;
}
