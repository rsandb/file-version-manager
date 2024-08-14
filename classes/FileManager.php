<?php
namespace LVAI\FileVersionManager;

class FileManager {
	private $upload_dir;
	private $wpdb;
	private $table_name;

	public function __construct( \wpdb $wpdb ) {
		$this->wpdb = $wpdb;
		$this->table_name = $wpdb->prefix . Constants::TABLE_NAME;
		$this->set_upload_dir();
	}

	public function init() {
		add_action( 'admin_init', [ $this, 'scan_files' ] );
		add_action( 'admin_post_update_file', [ $this, 'handle_file_update' ] );
		add_action( 'admin_post_nopriv_update_file', [ $this, 'handle_file_update' ] );
	}

	/**
	 * Set the upload directory
	 * 
	 * @return void
	 */
	private function set_upload_dir() {
		$upload_dir = wp_upload_dir();
		$custom_folder = get_option( 'fvm_custom_directory', 'file-version-manager' );
		$this->upload_dir = trailingslashit( $upload_dir['basedir'] ) . trim( $custom_folder, '/' );
		wp_mkdir_p( $this->upload_dir );
	}

	/**
	 * Customizes the upload directory path based on the custom folder option set in the settings page.
	 * 
	 * @param array $uploads Array containing upload directory information.
	 * @return array Modified array with customized upload directory path.
	 */
	public function custom_upload_dir( $uploads ) {
		$custom_folder = get_option( 'fvm_custom_directory', 'file-version-manager' );
		$uploads['subdir'] = '/' . trim( $custom_folder, '/' );
		$uploads['path'] = $uploads['basedir'] . $uploads['subdir'];
		$uploads['url'] = $uploads['baseurl'] . $uploads['subdir'];
		return $uploads;
	}

	/**
	 * Scans the directory for new files and updates the database accordingly.
	 * This method compares the files in the database with the files in the upload directory.
	 * If a file is found in the directory but not in the database, it adds the file to the database.
	 * If a file is found in the database but not in the directory, it removes the file from the database.
	 * 
	 * @return void
	 */
	public function scan_files() {
		global $wpdb;
		$table_name = $wpdb->prefix . Constants::TABLE_NAME;

		// Get all files from the database
		$db_files = $wpdb->get_results( "SELECT * FROM $table_name", ARRAY_A );
		$db_file_paths = wp_list_pluck( $db_files, 'file_path' );

		// Scan the directory recursively
		$existing_files = $this->scan_directory( $this->upload_dir );

		foreach ( $existing_files as $file_path ) {
			// Check if the file already exists in the database
			$key = array_search( $file_path, $db_file_paths );
			if ( $key === false ) {
				// New file, add to database
				$file_url = home_url( 'download/' . basename( $file_path ) );
				$file_size = filesize( $file_path );
				$file_type = wp_check_filetype( $file_path )['type'];
				$this->update_file_metadata(
					basename( $file_path ),
					$file_path,
					$file_url,
					$file_size,
					$file_type,
					'1.0',
					current_time( 'mysql' ),
					current_time( 'mysql' )
				);
			}
		}

		// Remove entries from the database for files that no longer exist
		foreach ( $db_files as $db_file ) {
			if ( ! in_array( $db_file['file_path'], $existing_files ) ) {
				$wpdb->delete( $table_name, [ 'id' => $db_file['id'] ], [ '%d' ] );
			}
		}
	}

	/**
	 * Scan a directory recursively and return an array of file paths.
	 * Does not return hidden files or directories.
	 * 
	 * @param string $dir
	 * @return array
	 */
	private function scan_directory( $dir ) {
		$files = [];
		$iterator = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $dir ) );
		foreach ( $iterator as $file ) {
			if ( $file->isFile() && substr( $file->getFilename(), 0, 1 ) !== '.' ) {
				$files[] = $file->getPathname();
			}
		}
		return $files;
	}

	/**
	 * Upload a file
	 * 
	 * @param array $file
	 * @param int|null $file_id
	 * @param string $version
	 * @return int|false
	 */
	public function upload_file( $file, $file_id = null, $version = '1.0' ) {
		add_filter( 'upload_dir', [ $this, 'custom_upload_dir' ] );
		$movefile = wp_handle_upload( $file, [ 'test_form' => false ] );
		remove_filter( 'upload_dir', [ $this, 'custom_upload_dir' ] );

		if ( $movefile && ! isset( $movefile['error'] ) ) {
			$file_name = basename( $movefile['file'] );
			$file_path = $movefile['file'];
			$file_url = home_url( 'download/' . $file_name );
			$file_type = $movefile['type'];
			$file_size = filesize( $file_path );
			$current_time = current_time( 'mysql' );

			$metadata = [ 
				'file_name' => $file_name,
				'file_path' => $file_path,
				'file_url' => $file_url,
				'file_size' => $file_size,
				'file_type' => $file_type,
				'version' => $version,
				'date_modified' => $current_time,
			];

			if ( $file_id ) {
				$this->wpdb->update( $this->table_name, $metadata, [ 'id' => $file_id ] );
			} else {
				$metadata['date_uploaded'] = $current_time;
				$this->wpdb->insert( $this->table_name, $metadata );
				$file_id = $this->wpdb->insert_id;
			}

			return $file_id;
		}

		return false;
	}

	/**
	 * Update a file
	 * 
	 * @param int $file_id
	 * @param array $file
	 * @param string $version
	 * @return bool
	 */
	public function update_file( $file_id, $new_file, $new_version ) {
		global $wpdb;
		$table_name = $wpdb->prefix . Constants::TABLE_NAME;

		error_log( "Entering update_file method for file ID: $file_id" );
		error_log( "New version: " . $new_version );
		error_log( "New file: " . print_r( $new_file, true ) );

		$existing_file = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_name WHERE id = %d", $file_id ), ARRAY_A );

		if ( ! $existing_file ) {
			error_log( "File not found for ID: $file_id" );
			return false;
		}

		error_log( "Existing file: " . print_r( $existing_file, true ) );

		$update_data = array();
		$update_format = array();

		// Check if auto-increment version is enabled
		$auto_increment_version = get_option( 'fvm_auto_increment_version', 1 );

		// Update version if provided or auto-increment if enabled
		if ( ! empty( $new_version ) ) {
			$update_data['version'] = $new_version;
		} elseif ( $auto_increment_version && $new_file ) {
			$current_version = $existing_file['version'];
			$update_data['version'] = $this->increment_version( $current_version );
		}

		if ( isset( $update_data['version'] ) ) {
			$update_format[] = '%s';
			error_log( "Updating version to: " . $update_data['version'] );
		}

		// Handle file upload if a new file is provided
		if ( $new_file && ! empty( $new_file['tmp_name'] ) ) {
			error_log( "New file uploaded. Processing..." );

			// Check if the file type is allowed by WordPress
			$check_file = wp_check_filetype_and_ext( $new_file['tmp_name'], $new_file['name'] );
			if ( ! $check_file['ext'] || ! $check_file['type'] ) {
				error_log( "File type not allowed: " . $new_file['type'] );
				return false;
			}

			// Delete the old file
			$old_file_path = $existing_file['file_path'];
			if ( file_exists( $old_file_path ) ) {
				if ( unlink( $old_file_path ) ) {
					error_log( "Old file deleted: " . $old_file_path );
				} else {
					error_log( "Failed to delete old file: " . $old_file_path );
				}
			} else {
				error_log( "Old file not found: " . $old_file_path );
			}

			// Upload the new file to the custom directory
			add_filter( 'upload_dir', [ $this, 'custom_upload_dir' ] );
			$movefile = wp_handle_upload( $new_file, [ 'test_form' => false ] );
			remove_filter( 'upload_dir', [ $this, 'custom_upload_dir' ] );

			if ( $movefile && ! isset( $movefile['error'] ) ) {
				$new_file_name = basename( $movefile['file'] );
				$new_file_path = $movefile['file'];
				$file_url = home_url( 'download/' . $new_file_name );
				$file_type = $movefile['type'];
				$file_size = filesize( $new_file_path );

				$update_data['file_name'] = $new_file_name;
				$update_data['file_path'] = $new_file_path;
				$update_data['file_url'] = $file_url;
				$update_data['file_type'] = $file_type;
				$update_data['file_size'] = $file_size;

				$update_format = array_merge( $update_format, array( '%s', '%s', '%s', '%s', '%d' ) );

				error_log( "New file data: " . print_r( $update_data, true ) );
			} else {
				error_log( "Failed to move uploaded file for file ID: $file_id" );
				return false;
			}
		} else {
			error_log( "No new file uploaded or upload error occurred" );
		}

		$update_data['date_modified'] = current_time( 'mysql' );
		$update_format[] = '%s';

		error_log( "Final update data: " . print_r( $update_data, true ) );

		$result = $wpdb->update(
			$table_name,
			$update_data,
			array( 'id' => $file_id ),
			$update_format,
			array( '%d' )
		);

		if ( $result === false ) {
			error_log( "Database update failed for file ID: $file_id" );
			error_log( "Database error: " . $wpdb->last_error );
			return false;
		}

		error_log( "File updated successfully for ID: $file_id" );
		return true;
	}

	private function update_file_metadata( $file_name, $file_path, $file_url, $file_size, $file_type, $version, $date_uploaded, $date_modified ) {
		global $wpdb;
		$table_name = $wpdb->prefix . Constants::TABLE_NAME;

		$result = $wpdb->insert(
			$table_name,
			array(
				'file_name' => $file_name,
				'file_path' => $file_path,
				'file_url' => $file_url,
				'file_size' => $file_size,
				'file_type' => $file_type,
				'version' => $version,
				'date_uploaded' => $date_uploaded,
				'date_modified' => $date_modified,
			),
			array( '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s' )
		);

		if ( $result === false ) {
			error_log( 'Database insertion failed: ' . $wpdb->last_error );
		}
	}

	/**
	 * Increment the version number
	 * 
	 * @param string $version
	 * @return string
	 */
	private function increment_version( $version ) {
		$parts = explode( '.', $version );
		$major = intval( $parts[0] );
		$major++;
		return $major . '.0';
	}

	/**
	 * Get a file by ID
	 * 
	 * @param int $file_id
	 * @return object|false
	 */
	public function get_file( $file_id ) {
		return $this->wpdb->get_row( $this->wpdb->prepare( "SELECT * FROM {$this->table_name} WHERE id = %d", $file_id ) );
	}

	/**
	 * Delete a file
	 * 
	 * @param int $file_id
	 * @return bool
	 */
	public function delete_file( $file_id ) {
		$file = $this->get_file( $file_id );
		if ( $file ) {
			if ( file_exists( $file->file_path ) ) {
				unlink( $file->file_path );
			}
			return $this->wpdb->delete( $this->table_name, [ 'id' => $file_id ], [ '%d' ] );
		}
		return false;
	}
}