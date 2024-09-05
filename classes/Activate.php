<?php

namespace LVAI\FileVersionManager;

class Activate {

	public static function activate() {
		global $wpdb;
		$table_name = $wpdb->prefix . Constants::FILE_TABLE_NAME;
		$cat_table_name = $wpdb->prefix . Constants::CAT_TABLE_NAME;
		$charset_collate = $wpdb->get_charset_collate();

		self::create_files_table( $table_name, $charset_collate );
		self::create_categories_table( $cat_table_name, $charset_collate );

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		dbDelta( self::$cat_sql );
		dbDelta( self::$sql );

		self::add_foreign_key_constraint( $wpdb, $table_name, $cat_table_name );

		update_option( 'fvm_flush_rewrite_rules', true );
	}

	private static $sql;
	private static $cat_sql;

	private static function create_files_table( $table_name, $charset_collate ) {
		self::$sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            file_name varchar(255) NOT NULL,
            file_display_name varchar(255),
            file_path varchar(255) NOT NULL,
            file_url varchar(255) NOT NULL,
            file_size bigint(20) NOT NULL,
            file_type varchar(100) NOT NULL,
            file_hash varchar(255) NOT NULL,
            version DECIMAL(5,1) NOT NULL,
            file_category_id mediumint(9),
            date_uploaded datetime NOT NULL,
            date_modified datetime NOT NULL,
            PRIMARY KEY  (id)
        ) $charset_collate;";
	}

	private static function create_categories_table( $cat_table_name, $charset_collate ) {
		self::$cat_sql = "CREATE TABLE IF NOT EXISTS $cat_table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            cat_name varchar(255) NOT NULL,
            cat_slug varchar(255) NOT NULL,
            cat_description text,
            cat_parent_id mediumint(9) DEFAULT 0,
            PRIMARY KEY (id),
            UNIQUE KEY cat_slug (cat_slug)
        ) $charset_collate;";
	}

	private static function add_foreign_key_constraint( $wpdb, $table_name, $cat_table_name ) {
		$constraint_exists = $wpdb->get_var( "
            SELECT COUNT(*)
            FROM information_schema.TABLE_CONSTRAINTS
            WHERE CONSTRAINT_SCHEMA = '{$wpdb->dbname}'
            AND CONSTRAINT_NAME = 'fk_file_category'
            AND TABLE_NAME = '$table_name'
        " );

		if ( $constraint_exists == 0 ) {
			$wpdb->query( "ALTER TABLE $table_name
                ADD CONSTRAINT fk_file_category
                FOREIGN KEY (file_category_id) 
                REFERENCES $cat_table_name(id) 
                ON DELETE SET NULL" );
		}
	}
}