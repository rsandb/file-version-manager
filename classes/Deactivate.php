<?php

namespace FVM\FileVersionManager;

class Deactivate {

	public static function deactivate() {

		global $wpdb;

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'Deactivate function called' );
		}

		$file_table_name = $wpdb->prefix . Constants::FILE_TABLE_NAME;
		$category_table_name = $wpdb->prefix . Constants::CAT_TABLE_NAME;
		$rel_table_name = $wpdb->prefix . Constants::REL_TABLE_NAME;

		$wpdb->query( "DROP TABLE IF EXISTS $rel_table_name" );
		$wpdb->query( "DROP TABLE IF EXISTS $file_table_name" );
		$wpdb->query( "DROP TABLE IF EXISTS $category_table_name" );

		// Delete settings
		// delete_option( 'fvm_custom_directory' );
		// delete_option( 'fvm_debug_logs' );
		delete_option( 'fvm_auto_increment_version' );

		flush_rewrite_rules();
	}
}