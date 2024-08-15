<?php

namespace LVAI\FileVersionManager;

class Plugin
{
	private $file_manager;
	private $admin_page;
	private $settings_page;
	private $shortcode;
	private $update_ids;

	public function __construct(
		FileManager $file_manager,
		AdminPage $admin_page,
		SettingsPage $settings_page,
		Shortcode $shortcode,
	) {
		$this->file_manager = $file_manager;
		$this->admin_page = $admin_page;
		$this->settings_page = $settings_page;
		$this->shortcode = $shortcode;
	}

	public function init()
	{
		add_action('plugins_loaded', [$this, 'setup']);
		// Add rewrite rules
		add_action('init', [$this, 'add_rewrite_rules']);
		// Flush rewrite rules if necessary
		add_action('init', [$this, 'maybe_flush_rewrite_rules']);
	}

	public function setup()
	{
		$this->file_manager->init();
		$this->admin_page->init();
		$this->settings_page->init();
		$this->shortcode->init();

		add_action('template_redirect', [$this, 'handle_download']);
		add_filter('admin_footer_text', [$this, 'custom_admin_footer_text']);
	}

	public function add_rewrite_rules()
	{
		add_rewrite_rule(
			'download/([^/]+)/?$',
			'index.php?fvm_download=1&fvm_file=$matches[1]',
			'top'
		);
		add_rewrite_tag('%fvm_download%', '([0-1]+)');
		add_rewrite_tag('%fvm_file%', '([^&]+)');
	}

	public function maybe_flush_rewrite_rules()
	{
		if (get_option('fvm_flush_rewrite_rules')) {
			flush_rewrite_rules();
			delete_option('fvm_flush_rewrite_rules');
		}
	}

	/**
	 * Handle viewing and downloading files
	 * @return void
	 */
	function handle_download()
	{
		if (get_query_var('fvm_download') == 1) {
			$file_name = get_query_var('fvm_file');
			global $wpdb;
			$table_name = $wpdb->prefix . Constants::TABLE_NAME;
			$file = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE file_name = %s", $file_name));

			if ($file) {
				$file_path = $file->file_path;
				if (file_exists($file_path)) {
					$file_type = $file->file_type;
					header('Content-Type: ' . $file_type);

					// Check if the file type is suitable for inline display
					$inline_types = array(
						'application/pdf',
						'text/plain',
						'text/html',
						'text/xml',
						'text/css',
						'text/javascript',
						'application/javascript',
						'application/json',
						'image/jpeg',
						'image/png',
						'image/gif',
						'image/svg+xml',
						'image/webp',
						'image/bmp',
						'image/tiff',
						'audio/mpeg',
						'audio/ogg',
						'audio/wav',
						'video/mp4',
						'video/webm',
						'video/ogg',
					);
					if (in_array($file_type, $inline_types)) {
						header('Content-Disposition: inline; filename="' . $file_name . '"');
					} else {
						header('Content-Disposition: attachment; filename="' . $file_name . '"');
					}

					header('Content-Length: ' . filesize($file_path));
					readfile($file_path);
					exit;
				}
			} else {
				status_header(404);
				nocache_headers();
				include(get_query_template('404'));
				exit;
			}
		}
	}

	public function custom_admin_footer_text($text)
	{
		$screen = get_current_screen();
		if ($screen && (strpos($screen->id, 'fvm_files') !== false || $screen->id === 'files_page_fvm_settings')) {
			$upload_dir = wp_upload_dir();
			$custom_folder = get_option('fvm_custom_directory');

			if (!empty($custom_folder)) {
				$custom_dir = trailingslashit($upload_dir['basedir']) . trim($custom_folder, '/');
			} else {
				$custom_dir = $upload_dir['basedir'] . '/file-version-manager';
			}

			$total_size = $this->get_directory_size($custom_dir);
			$formatted_size = size_format($total_size, 2);
			return "Total size of current directory: {$formatted_size}";
		} elseif ($screen && strpos($screen->id, 'files_page_fvm_update_ids') !== false) {
			$custom_folder = get_option('fvm_custom_directory');
			$upload_dir = wp_upload_dir();
			$current_dir = !empty($custom_folder)
				? trailingslashit($upload_dir['basedir']) . trim($custom_folder, '/')
				: trailingslashit($upload_dir['basedir']) . 'file-version-manager';
			return "Modifying files in this directory: " . $current_dir;
		}
		return $text;
	}

	private function get_directory_size($path)
	{
		$total_size = 0;
		$files = scandir($path);
		foreach ($files as $file) {
			if ($file !== '.' && $file !== '..') {
				$file_path = $path . '/' . $file;
				if (is_file($file_path)) {
					$total_size += filesize($file_path);
				} elseif (is_dir($file_path)) {
					$total_size += $this->get_directory_size($file_path);
				}
			}
		}
		return $total_size;
	}
}