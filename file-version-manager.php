<?php
/*
Plugin Name: File Version Manager
Description: Conveniently upload and update files site-wide.
Version: 0.11.1
Author: Riley Sandborg
Author URI: https://rileysandb.org/
License: GPLv2 or later
Plugin URI: https://github.com/rsandb/file-version-manager/
Text Domain: file-version-manager
*/

#NOTE: This plugin is compatible with the Big File Uploads plugin and displays a link to change the upload size if it is active

namespace FVM\FileVersionManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'FVM\FileVersionManager\PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once __DIR__ . '/includes/Constants.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/ajax-handler.php';
require_once __DIR__ . '/classes/Plugin.php';
require_once __DIR__ . '/classes/FileManager.php';
require_once __DIR__ . '/classes/FilePage.php';
require_once __DIR__ . '/classes/SettingsPage.php';
require_once __DIR__ . '/classes/Shortcode.php';
require_once __DIR__ . '/classes/Activate.php';
require_once __DIR__ . '/classes/Deactivate.php';
require_once __DIR__ . '/classes/FileListTable.php';
require_once __DIR__ . '/classes/MigrateFilebasePro.php';
require_once __DIR__ . '/classes/CategoryPage.php';
require_once __DIR__ . '/classes/CategoryListTable.php';
require_once __DIR__ . '/classes/CategoryManager.php';

global $wpdb;
$update_ids = new MigrateFilebasePro( $wpdb );
$file_manager = new FileManager( $wpdb );
$shortcode = new Shortcode( $wpdb );
$file_page = new FilePage( $file_manager );
$settings_page = new SettingsPage( $update_ids );
$category_manager = new CategoryManager( $wpdb );
$category_page = new CategoryPage( $category_manager );

$plugin = new Plugin(
	$file_manager,
	$file_page,
	$category_manager,
	$category_page,
	$settings_page,
	$shortcode,
);

$plugin->init();

register_activation_hook( __FILE__, [ Activate::class, 'activate' ] );
register_deactivation_hook( __FILE__, [ Deactivate::class, 'deactivate' ] );

add_filter( 'upload_mimes', function ($mimes) {
	$mimes['xml'] = 'application/xml';
	return $mimes;
} );

/**
 * Add a link to the settings page in the plugins page
 */
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), function ($links) {
	$settings_link = '<a href="' . admin_url( 'admin.php?page=fvm_settings' ) . '">' . __( 'Settings', 'file-version-manager' ) . '</a>';
	array_unshift( $links, $settings_link );
	return $links;
} );

require 'plugin-update-checker/plugin-update-checker.php';
use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

$myUpdateChecker = PucFactory::buildUpdateChecker(
	'https://github.com/rsandb/file-version-manager/',
	__FILE__,
	'file-version-manager'
);

//Set the branch that contains the stable release.
$myUpdateChecker->setBranch( 'main' );

//Optional: If you're using a private repository, specify the access token like this:
// $myUpdateChecker->setAuthentication( 'your-token-here' );