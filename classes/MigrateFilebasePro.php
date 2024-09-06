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
	private $table_name;
	private $category_table_name;

	public function __construct( $wpdb ) {
		$this->wpdb = $wpdb;
		$this->table_name = $wpdb->prefix . Constants::FILE_TABLE_NAME;
		$this->category_table_name = $wpdb->prefix . Constants::CAT_TABLE_NAME;
	}

	/**
	 * Imports data from wp_wpfb_cats and wp_wpfb_files tables to the plugin's tables.
	 * @return array
	 */
	public function import_from_wpfilebase() {
		$this->wpdb->query( 'START TRANSACTION' );

		try {
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
			$this->import_category( $category );
		}

		$this->log[] = "Imported " . count( $categories ) . " categories.";
	}

	/**
	 * Imports a single category.
	 * @param object $category
	 */
	private function import_category( $category ) {
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

		foreach ( $files as $file ) {
			$this->import_file( $file );
		}

		$this->log[] = "Imported " . count( $files ) . " files.";
	}

	/**
	 * Imports a single file.
	 * @param object $file
	 */
	private function import_file( $file ) {
		$existing_file = $this->wpdb->get_row( $this->wpdb->prepare(
			"SELECT * FROM {$this->table_name} WHERE file_name = %s",
			$file->file_name
		) );

		$data = [ 
			'file_name' => $file->file_name,
			'file_display_name' => $file->file_display_name,
			'file_category_id' => $file->file_category ? $file->file_category : NULL,
			'date_modified' => current_time( 'mysql' ),
		];

		if ( $existing_file ) {
			$this->wpdb->update( $this->table_name, $data, [ 'id' => $existing_file->id ] );
			$this->log[] = "Updated file: " . htmlspecialchars( $file->file_name );
		} else {
			$data['id'] = $file->file_id;
			$this->wpdb->insert( $this->table_name, $data );
			$this->log[] = "Imported file: " . htmlspecialchars( $file->file_name );
		}
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