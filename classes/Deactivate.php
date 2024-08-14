<?php

namespace LVAI\FileVersionManager;

class Deactivate {
	public static function deactivate() {
		global $wpdb;
		$table_name = $wpdb->prefix . Constants::TABLE_NAME;

		// Drop the custom table
		$wpdb->query( "DROP TABLE IF EXISTS $table_name" );

		flush_rewrite_rules();
	}
}