<?php

namespace LVAI\FileVersionManager;

class Activate {
	public static function activate() {
		// Create custom table for file metadata
		global $wpdb;
		$table_name = $wpdb->prefix . Constants::TABLE_NAME;
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE $table_name (
      id mediumint(9) NOT NULL AUTO_INCREMENT,
      file_name varchar(255) NOT NULL,
      file_path varchar(255) NOT NULL,
      file_url varchar(255) NOT NULL,
      file_size bigint(20) NOT NULL,
      file_type varchar(100) NOT NULL,
      version DECIMAL(5,1) NOT NULL,
      date_uploaded datetime NOT NULL,
      date_modified datetime NOT NULL,
      PRIMARY KEY  (id)
  ) $charset_collate;";

		require_once ( ABSPATH . 'wp-admin/includes/upgrade.php' );
		dbDelta( $sql );

		// Set option to flush rewrite rules on next init
		update_option( 'fvm_flush_rewrite_rules', true );
	}
}