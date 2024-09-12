<?php

# - Add compatibility with other file upload/management plugins
# - Purge unused database entries

namespace LVAI\FileVersionManager;

use Exception;

/**
 * This class handles the process of updating file IDs based on a CSV input.
 * It includes functionality to:
 * - Process a CSV file containing new ID and file name pairs
 * - Update the database with the new IDs for matching file names
 * - Log the entire process for debugging and auditing purposes
 */
class MigrateFilebasePro {
	private $log = [];
	private $highest_id = 0;
	private $wpdb;
	private $file_table_name;
	private $category_table_name;

	public function __construct( $wpdb ) {
		$this->wpdb = $wpdb;
		$this->file_table_name = $wpdb->prefix . Constants::FILE_TABLE_NAME;
		$this->category_table_name = $wpdb->prefix . Constants::CAT_TABLE_NAME;
	}

	/**
	 * Imports data from wp_wpfb_cats and wp_wpfb_files tables to the plugin's tables.
	 * @return array
	 */
	public function import_from_wpfilebase() {
		$this->wpdb->query( 'START TRANSACTION' );

		try {
			$this->log[] = "--------------------------";
			$this->log[] = "--- Starting Migration ---";
			$this->log[] = "--------------------------\n";

			// Import categories first
			$this->import_categories();

			// Then import files
			$this->import_files();

			$this->wpdb->query( 'COMMIT' );

			return [ 'success' => true, 'message' => "Import completed successfully." ];
		} catch (Exception $e) {
			$this->wpdb->query( 'ROLLBACK' );
			return [ 'success' => false, 'message' => $e->getMessage() ];
		}
	}

	/**
	 * Imports categories from wp_wpfb_cats table.
	 */
	private function import_categories() {
		$wpfb_cats_table = $this->wpdb->prefix . 'wpfb_cats';
		$categories = $this->wpdb->get_results( "SELECT * FROM $wpfb_cats_table" );

		if ( empty( $categories ) ) {
			$this->log[] = "No categories found in the WP-Filebase table.";
			return;
		}

		foreach ( $categories as $category ) {
			$this->insert_category( $category );
		}

		$this->log[] = "\n-------------------------------";
		$this->log[] = "--- Imported " . count( $categories ) . " categories. ---";
		$this->log[] = "-------------------------------\n";
	}

	/**
	 * Imports a single category.
	 * @param object $category
	 */
	private function insert_category( $category ) {
		$existing_category = $this->wpdb->get_row( $this->wpdb->prepare(
			"SELECT * FROM wp_fvm_categories WHERE id = %d",
			$category->cat_id
		) );

		$unique_slug = $this->get_unique_slug( $category->cat_name );

		$data = [ 
			'id' => $category->cat_id,
			'cat_name' => $category->cat_name,
			'cat_description' => $category->cat_description,
			'cat_slug' => $unique_slug,
			'cat_parent_id' => $category->cat_parent ? $category->cat_parent : 0,
		];

		if ( $existing_category ) {
			$this->wpdb->update( 'wp_fvm_categories', $data, [ 'id' => $category->cat_id ] );
			$this->log[] = "Updated category: " . htmlspecialchars( $category->cat_name );
		} else {
			$data['id'] = $category->cat_id;
			$this->wpdb->insert( 'wp_fvm_categories', $data );
			$this->log[] = "Imported category: " . htmlspecialchars( $category->cat_name );
		}
	}

	/**
	 * Imports files from wp_wpfb_files table.
	 */
	private function import_files() {
		$wpfb_files_table = $this->wpdb->prefix . 'wpfb_files';
		$files = $this->wpdb->get_results( "SELECT * FROM $wpfb_files_table" );

		if ( empty( $files ) ) {
			$this->log[] = "No files found in the WP-Filebase table.";
			return;
		}

		$this->log[] = "\n-----------------------";
		$this->log[] = "--- Importing files ---";
		$this->log[] = "-----------------------\n";

		foreach ( $files as $file ) {
			$this->insert_file( $file );
		}

		$this->log[] = "Imported " . count( $files ) . " files.";
	}

	/**
	 * Imports a single file.
	 * @param object $file
	 */
	private function insert_file( $file ) {
		$this->log[] = "Incoming file:";
		$this->log[] = "-> ({$file->file_id}) {$file->file_name}";

		$existing_file = $this->wpdb->get_row( $this->wpdb->prepare(
			"SELECT * FROM {$this->file_table_name} WHERE file_name = %s",
			$file->file_name
		) );

		$this->log[] = $existing_file ? "Target file:" : "No target file found with this name.";
		$this->log[] = $existing_file ? "-> ({$existing_file->id}) {$existing_file->file_name}" : "";

		// Check if the category exists
		$category_exists = $file->file_category ? $this->wpdb->get_var( $this->wpdb->prepare(
			"SELECT COUNT(*) FROM {$this->category_table_name} WHERE id = %d",
			$file->file_category
		) ) : 0;

		$data = [ 
			'id' => $file->file_id,
			'file_name' => $file->file_name,
			'file_display_name' => $file->file_display_name,
			'file_category_id' => ( $file->file_category && $category_exists ) ? $file->file_category : NULL,
			'date_modified' => current_time( 'mysql' ),
		];

		$this->log[] = "Prepared data for insertion/update: " . print_r( $data, true );

		if ( $existing_file ) {
			// If the existing file has a different ID, update the ID to match the incoming file
			if ( $existing_file->id != $file->file_id ) {
				$this->log[] = "Existing file ID ({$existing_file->id}) doesn't match incoming file ID ({$file->file_id}).";

				// Check if the incoming ID already exists in the target table
				$id_exists = $this->wpdb->get_var( $this->wpdb->prepare(
					"SELECT COUNT(*) FROM {$this->file_table_name} WHERE id = %d",
					$file->file_id
				) );

				if ( $id_exists ) {
					$this->log[] = "Incoming file ID already exists in the target table. Deleting existing record to avoid duplicate ID error.";
					// If the incoming ID exists, delete the existing record to avoid duplicate ID error
					$this->wpdb->delete( $this->file_table_name, [ 'id' => $file->file_id ] );
					$this->log[] = "Deleted existing record with ID: {$file->file_id}";
				}

				// Update the existing file's ID to match the incoming file's ID
				$this->wpdb->update( $this->file_table_name, [ 'id' => $file->file_id ], [ 'file_name' => $file->file_name ] );
				$this->log[] = "Updated existing file's ID to: {$file->file_id}";
			}

			// Update the existing file with the new data
			$update_result = $this->wpdb->update( $this->file_table_name, $data, [ 'file_name' => $file->file_name ] );
			if ( $update_result !== false ) {
				$this->log[] = "Updated file:";
				$this->log[] = "-> ({$file->file_id}) {$file->file_name}";
			} else {
				$this->log[] = "Error updating file: " . htmlspecialchars( $file->file_name ) . ". Database error: " . $this->wpdb->last_error;
			}
		} else {
			$this->log[] = "No existing file found. Proceeding with insertion.";
			// Insert the new file
			$insert_result = $this->wpdb->insert( $this->file_table_name, $data );
			if ( $insert_result !== false ) {
				$this->log[] = "Imported file: " . htmlspecialchars( $file->file_name );
			} else {
				$this->log[] = "Error importing file: " . htmlspecialchars( $file->file_name ) . ". Database error: " . $this->wpdb->last_error;
			}
		}

		$this->log[] = "Finished processing file: " . htmlspecialchars( $file->file_display_name ) . "\n";
	}


	/**
	 * Generates a unique slug for a category.
	 * @param string $category_name
	 * @return string
	 */
	private function get_unique_slug( $category_name ) {
		$base_slug = sanitize_title( $category_name );
		$slug = $base_slug;
		$counter = 1;
		while ( $this->slug_exists( $slug ) ) {
			$slug = $base_slug . '-' . $counter;
			$counter++;
		}
		return $slug;
	}

	/**
	 * Checks if a slug already exists in the database.
	 * @param string $slug
	 * @return bool
	 */
	private function slug_exists( $slug ) {
		return $this->wpdb->get_var( $this->wpdb->prepare(
			"SELECT COUNT(*) FROM wp_fvm_categories WHERE cat_slug = %s", $slug ) ) > 0;
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