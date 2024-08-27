<?php
namespace LVAI\FileVersionManager;

use Exception;

/**
 * This class handles the process of updating file IDs based on a CSV input.
 * It includes functionality to:
 * - Randomize all existing IDs in the database
 * - Process a CSV file containing new ID and file name pairs
 * - Update the database with the new IDs for matching file names
 * - Log the entire process for debugging and auditing purposes
 */
class UpdateIDs {
	private $log = [];
	private $highest_id = 0;

	/**
	 * Processes a CSV file containing new ID and file name pairs.
	 * @param mixed $csv_file
	 * @return array
	 */
	public function process_csv( $csv_file ) {
		global $wpdb;
		$wpdb->query( 'START TRANSACTION' );

		try {
			$file = fopen( $csv_file, 'r' );
			if ( ! $file ) {
				throw new Exception( "Unable to open CSV file." );
			}

			$headers = $this->get_csv_headers( $file );
			$id_index = array_search( 'id', $headers );
			$file_name_index = array_search( 'file name', $headers );
			$file_path_index = array_search( 'file path', $headers );

			if ( $id_index === false || $file_name_index === false ) {
				throw new Exception( "CSV file must contain 'id' and 'file name' columns." );
			}

			$this->update_ids_from_csv( $file, $id_index, $file_name_index, $file_path_index );
			$this->update_remaining_files();

			$wpdb->query( 'COMMIT' );
			fclose( $file );

			return [ 'success' => true, 'message' => "Updated IDs successfully. Highest ID: {$this->highest_id}" ];
		} catch (Exception $e) {
			$wpdb->query( 'ROLLBACK' );
			if ( isset( $file ) ) {
				fclose( $file );
			}
			return [ 'success' => false, 'message' => $e->getMessage() ];
		}
	}

	/**
	 * Extracts and returns the headers from a CSV file.
	 *
	 * @param resource $file The file handle of the CSV file.
	 * @return string[] The extracted headers as an array.
	 */
	private function get_csv_headers( $file ) {
		$first_line = fgets( $file );
		$bom = pack( 'H*', 'EFBBBF' );
		$first_line = preg_replace( "/^$bom/", '', $first_line );
		$headers = str_getcsv( trim( $first_line ) );
		return array_map( 'strtolower', array_map( 'trim', $headers ) );
	}

	/**
	 * Randomizes all existing IDs in the database.
	 *
	 * @return bool Whether the randomization was successful.
	 */
	public function randomize_all_ids() {
		global $wpdb;
		$table_name = $wpdb->prefix . Constants::TABLE_NAME;

		$result = $wpdb->query( "
			UPDATE $table_name
			SET id = FLOOR(RAND() * (999999 - 99999 + 1) + 99999)
		" );

		if ( $result === false ) {
			error_log( "Failed to randomize IDs. Error: " . $wpdb->last_error );
			return false;
		}

		return true;
	}

	// /**
	//  * Updates the ID for a specific file name in the database.
	//  *
	//  * @param string $file_name The file name to update.
	//  * @param int $new_id The new ID to set for the file.
	//  * @return bool Whether the update was successful.
	//  */
	// public function update_file_id( $file_name, $new_id, $file_path ) {
	// 	global $wpdb;
	// 	$table_name = $wpdb->prefix . Constants::TABLE_NAME;

	// 	// Check if there are duplicate file names
	// 	$duplicate_count = $wpdb->get_var( $wpdb->prepare(
	// 		"SELECT COUNT(*) FROM $table_name WHERE file_name = %s",
	// 		$file_name
	// 	) );

	// 	$current_time = current_time( 'mysql' );

	// 	if ( $duplicate_count > 1 ) {
	// 		// If there are duplicates, update only one file and leave the others
	// 		$result = $wpdb->query( $wpdb->prepare(
	// 			"UPDATE $table_name SET id = %d, date_modified = %s WHERE file_name = %s LIMIT 1",
	// 			$new_id,
	// 			$current_time,
	// 			$file_name,
	// 			$file_path
	// 		) );
	// 	} else {
	// 		// If there's only one file, update it as before
	// 		$result = $wpdb->update(
	// 			$table_name,
	// 			[ 'id' => $new_id, 'date_modified' => $current_time ],
	// 			[ 'file_name' => $file_name, 'file_path' => $file_path ],
	// 			[ '%d', '%s' ],
	// 			[ '%s', '%s' ]
	// 		);
	// 	}

	// 	if ( $result === false ) {
	// 		error_log( "Failed to update ID for file: $file_name. Error: " . $wpdb->last_error );
	// 		return false;
	// 	} elseif ( $result === 0 ) {
	// 		error_log( "No rows updated for file: $file_name. File might not exist." );
	// 		return false;
	// 	}

	// 	return true;
	// }

	/**
	 * Updates the IDs for file names in the database based on the CSV content.
	 *
	 * @param resource $file The file handle of the CSV file.
	 * @param int $id_index The index of the ID column in the CSV file.
	 * @param int $file_name_index The index of the file name column in the CSV file.
	 * @return void
	 */
	private function update_ids_from_csv( $file, $id_index, $file_name_index, $file_path_index ) {
		global $wpdb;
		$table_name = $wpdb->prefix . Constants::TABLE_NAME;

		$duplicates = [];

		while ( ( $data = fgetcsv( $file ) ) !== FALSE ) {
			$id = intval( $data[ $id_index ] );
			$file_name = $data[ $file_name_index ];
			$file_path = $data[ $file_path_index ];

			$this->highest_id = max( $this->highest_id, $id );

			// Check for duplicate file names
			$duplicate_count = $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(*) FROM $table_name WHERE file_name = %s",
				$file_name
			) );

			// Temporarily update existing files with the new ID
			$existing_files_with_new_id = $wpdb->get_results( $wpdb->prepare(
				"SELECT id, file_name FROM $table_name WHERE id = %d",
				$id
			) );

			foreach ( $existing_files_with_new_id as $existing_file ) {
				$temp_id = $this->get_temporary_id();
				$wpdb->update(
					$table_name,
					[ 'id' => $temp_id ],
					[ 'id' => $id ],
					[ '%d' ],
					[ '%d' ]
				);
				$this->log[] = "Temporarily updated file '{$existing_file->file_name}' with ID $id to temporary ID $temp_id";
			}

			if ( $duplicate_count > 1 ) {
				// Update only one of the duplicates
				$result = $wpdb->query( $wpdb->prepare(
					"UPDATE $table_name SET id = %d, date_modified = %s WHERE file_name = %s LIMIT 1",
					$id,
					current_time( 'mysql' ),
					$file_name
				) );

				if ( $result === false ) {
					$this->log[] = "Error updating one of duplicate files: $file_name with ID: $id";
					throw new \Exception( "Database error: " . $wpdb->last_error );
				} else {
					$this->log[] = "Updated one of duplicate files: $file_name with ID: $id";
					$duplicates[] = [ 'file_name' => $file_name, 'id' => $id ];
				}
			} else {
				// Existing logic for non-duplicate files
				$result = $wpdb->update(
					$table_name,
					[ 'id' => $id, 'date_modified' => current_time( 'mysql' ) ],
					[ 'file_name' => $file_name ],
					[ '%d', '%s' ],
					[ '%s' ]
				);

				if ( $result === false ) {
					$insert_result = $wpdb->insert(
						$table_name,
						[ 'id' => $id, 'file_name' => $file_name, 'file_path' => $file_path, 'date_modified' => current_time( 'mysql' ) ],
						[ '%d', '%s', '%s', '%s' ]
					);

					if ( $insert_result === false ) {
						$this->log[] = "Error updating/inserting file: $file_name with ID: $id";
						throw new \Exception( "Database error: " . $wpdb->last_error );
					} else {
						$this->log[] = "Inserted new file: $file_name with ID: $id";
					}
				} else {
					$this->log[] = "Updated file: $file_name with ID: $id";
				}
			}
		}

		// Log all duplicates that got pushed to the end
		foreach ( $duplicates as $duplicate ) {
			$this->log[] = "Duplicate file pushed to end: {$duplicate['file_name']} with ID: {$duplicate['id']}";
		}
	}

	private function get_temporary_id() {
		global $wpdb;
		$table_name = $wpdb->prefix . Constants::TABLE_NAME;

		$temp_id = $wpdb->get_var( "SELECT MAX(id) FROM $table_name" ) + 1;
		return $temp_id;
	}

	/**
	 * Updates the IDs for the remaining files in the database.
	 *
	 * @return void
	 */
	private function update_remaining_files() {
		global $wpdb;
		$table_name = $wpdb->prefix . Constants::TABLE_NAME;

		$wpdb->query( $wpdb->prepare(
			"UPDATE $table_name SET id = @new_id := @new_id + 1, date_modified = %s 
             WHERE id IS NULL OR id = 0
             ORDER BY file_name",
			current_time( 'mysql' )
		) );

		$this->highest_id = $wpdb->get_var( "SELECT MAX(id) FROM $table_name" );
		$this->log[] = "Updated remaining files. New highest ID: {$this->highest_id}";
	}

	/**
	 * Retrieves the log of the update process.
	 *
	 * @return string[] The log of the update process.
	 */
	public function get_log() {
		return $this->log;
	}
}