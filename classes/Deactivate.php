<?php

namespace FVM\FileVersionManager;

class Deactivate {
	public static function deactivate() {

		global $wpdb;
		$old_table = $wpdb->prefix . 'fvm_metadata';
		$table_name = $wpdb->prefix . Constants::FILE_TABLE_NAME;
		$category_table_name = $wpdb->prefix . Constants::CAT_TABLE_NAME;
		$rel_table_name = $wpdb->prefix . Constants::REL_TABLE_NAME;

		// Drop the custom table
		$wpdb->show_errors();
		$results = [];
		$results[] = $wpdb->query( "DROP TABLE IF EXISTS $table_name" );
		$results[] = $wpdb->query( "DROP TABLE IF EXISTS $category_table_name" );
		$results[] = $wpdb->query( "DROP TABLE IF EXISTS $rel_table_name" );
		$results[] = $wpdb->query( "DROP TABLE IF EXISTS $old_table" );

		// Log the results
		error_log( 'FVM Deactivation Results: ' . print_r( $results, true ) );
		error_log( 'FVM Deactivation Errors: ' . $wpdb->last_error );

		// Delete settings
		// delete_option( 'fvm_custom_directory' );
		delete_option( 'fvm_debug_logs' );
		delete_option( 'fvm_auto_increment_version' );

		flush_rewrite_rules();
	}
}