<?php
/*
Plugin Name: File Version Manager
Description: Conveniently upload and update files site-wide.
Version: 0.7.0
Author: Riley Sandborg
Author URI: https://rileysandb.org/
*/

namespace LVAI\FileVersionManager;

if (!defined('ABSPATH')) {
	exit;
}

// Define PLUGIN_URL constant
define('LVAI\FileVersionManager\PLUGIN_URL', plugin_dir_url(__FILE__));

#todo: add autoloader
// require_once __DIR__ . '/vendor/autoload.php';

// Manually require all necessary files
require_once __DIR__ . '/includes/Constants.php';
require_once __DIR__ . '/classes/Plugin.php';
require_once __DIR__ . '/classes/FileManager.php';
require_once __DIR__ . '/classes/AdminPage.php';
require_once __DIR__ . '/classes/SettingsPage.php';
require_once __DIR__ . '/classes/Shortcode.php';
require_once __DIR__ . '/classes/Activate.php';
require_once __DIR__ . '/classes/Deactivate.php';
require_once __DIR__ . '/classes/FileListTable.php';
require_once __DIR__ . '/classes/UpdateIDsPage.php';
require_once __DIR__ . '/classes/UpdateIDs.php';
// require_once __DIR__ . '/ajax-handlers.php';

$file_manager = new FileManager($GLOBALS['wpdb']);
$settings_page = new SettingsPage();
$shortcode = new Shortcode($GLOBALS['wpdb']);
$admin_page = new AdminPage($file_manager);
$update_ids = new UpdateIDs();
$update_ids_page = new UpdateIDsPage($update_ids);

$plugin = new Plugin(
	$file_manager,
	$admin_page,
	$settings_page,
	$shortcode,
	$update_ids_page
);

$plugin->init();

register_activation_hook(__FILE__, [Activate::class, 'activate']);
register_deactivation_hook(__FILE__, [Deactivate::class, 'deactivate']);

// add_action( 'wp_ajax_bulk_delete_files', function () {
// 	$file_list_table = new FileListTable( new FileManager( $GLOBALS['wpdb'] ) );
// 	$file_list_table->process_bulk_action();
// } );