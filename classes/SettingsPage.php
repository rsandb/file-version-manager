<?php
namespace LVAI\FileVersionManager;

class SettingsPage
{
	public function init()
	{
		add_action('admin_menu', [$this, 'add_settings_page']);
		add_action('admin_init', [$this, 'register_settings']);
		add_action('admin_enqueue_scripts', [$this, 'enqueue_styles']);
	}

	public function add_settings_page()
	{
		add_submenu_page(
			'fvm_files',
			'File Version Manager Settings',
			'Settings',
			'manage_options',
			'fvm_settings',
			array($this, 'render_settings_page')
		);
	}

	public function register_settings()
	{
		register_setting('fvm_settings', 'fvm_custom_directory');
		register_setting('fvm_settings', 'fvm_debug_logs');
		register_setting('fvm_settings', 'fvm_auto_increment_version');
	}

	public function enqueue_styles()
	{
		if (function_exists('get_current_screen')) {
			$screen = get_current_screen();
			if ($screen && $screen->id === 'files_page_fvm_settings') {
				wp_enqueue_style('file-version-manager-styles', plugin_dir_url(dirname(__FILE__)) . 'css/settings.css');
			}
		}
	}

	public function render_settings_page()
	{
		$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'settings';
		?>
		<div class="wrap">
			<h1>File Version Manager</h1>
			<div class="fvm-settings-tabs-container">
				<div class="fvm-settings-tabs">
					<a class="fvm-settings-tab <?php echo $active_tab === 'settings' ? 'active' : ''; ?>"
						href="?page=fvm_settings&tab=settings">Settings</a>
					<a class="fvm-settings-tab <?php echo $active_tab === 'wp-filebase-pro' ? 'active' : ''; ?>"
						href="?page=fvm_settings&tab=wp-filebase-pro">WP Filebase Pro</a>
				</div>
			</div>
			<div class="fvm-settings-container">
				<?php
				if ($active_tab === 'settings') {
					$this->render_settings_tab();
				} elseif ($active_tab === 'wp-filebase-pro') {
					$this->render_wp_filebase_pro_tab();
				}
				?>
			</div>
			<?php

			if (isset($_POST['export_wpfilebase'])) {
				$result = $this->export_wpfilebase_files_as_csv();
				if (is_array($result) && isset($result['success'])) {
					echo '<div class="updated"><p>' . esc_html($result['message']) . ' <a href="' . esc_url($result['file_url']) . '" target="_blank">Download CSV</a></p></div>';
				} else {
					echo '<div class="error"><p>' . esc_html($result) . '</p></div>';
				}
			}
			?>
		</div>
		<?php
	}

	/**
	 * Render the settings tab
	 * 
	 * @return void
	 */
	private function render_settings_tab()
	{
		?>
		<form method="post" action="options.php">
			<?php settings_fields('fvm_settings'); ?>
			<?php do_settings_sections('fvm_settings'); ?>
			<table class="form-table">
				<tr valign="top">
					<th scope="row">Custom Upload Folder</th>
					<td>
						<input type="text" name="fvm_custom_directory"
							value="<?php echo esc_attr(get_option('fvm_custom_directory')); ?>" class="regular-text" />
						<p class="description">Enter the name of the folder within the WordPress uploads directory.
							Leave
							blank to use the default 'file-version-manager' folder.</p>
					</td>
				</tr>
				<tr>
					<th scope="row">Auto-Increment Version</th>
					<td>
						<input type="checkbox" name="fvm_auto_increment_version" value="1" <?php checked(get_option('fvm_auto_increment_version', 1), 1); ?> />
						<p class="description">Enable auto-increment version when files are replaced.</p>
					</td>
				</tr>
				<tr>
					<th scope="row">Debug logs</th>
					<td>
						<input type="checkbox" name="fvm_debug_logs" value="1" <?php checked(get_option('fvm_debug_logs'), 1); ?> disabled />
						<p class="description">Enable debug logs. (Currently not working)</p>
					</td>
				</tr>
			</table>
			<?php submit_button(); ?>
		</form>
		<?php
	}

	/**
	 * Render the WP Filebase Pro settings tab
	 * 
	 * @return void
	 */
	private function render_wp_filebase_pro_tab()
	{
		?>
		<h2>Export WP-Filebase Files</h2>
		<?php
		global $wpdb;
		$files_table = $wpdb->prefix . 'wpfb_files';
		$file_count = $wpdb->get_var("SELECT COUNT(*) FROM $files_table");
		?>
		<p>There are currently <?php echo esc_html($file_count); ?> entries in the WP-Filebase files table.</p>
		<form method="post">
			<input type="submit" name="export_wpfilebase" class="button button-primary" value="Export as CSV">
		</form>
		<?php
		if (isset($_POST['export_wpfilebase'])) {
			$result = $this->export_wpfilebase_files_as_csv();
			if (is_array($result) && isset($result['success'])) {
				echo '<div class="updated"><p>' . esc_html($result['message']) . ' <a href="' . esc_url($result['file_url']) . '" target="_blank">Download CSV</a></p></div>';
			} else {
				echo '<div class="error"><p>' . esc_html($result) . '</p></div>';
			}
		}
	}

	function export_wpfilebase_files_as_csv()
	{
		global $wpdb;
		// Get the table name
		$files_table = $wpdb->prefix . 'wpfb_files';

		// Debug: Check if table exists
		$files_table_exists = $wpdb->get_var("SHOW TABLES LIKE '$files_table'") == $files_table;

		if (!$files_table_exists) {
			return "Error: WP-Filebase files table not found.";
		}

		// Debug: Count entries in the files table
		$file_count = $wpdb->get_var("SELECT COUNT(*) FROM $files_table");

		if ($file_count == 0) {
			return "No files found in the WP-Filebase files table.";
		}

		// Query to get file_id and file_name
		$query = "
        SELECT file_id, file_name
        FROM $files_table
        ORDER BY file_id ASC
    ";

		$files = $wpdb->get_results($query, ARRAY_A);

		if (!$files) {
			return "Error: Failed to retrieve files. MySQL error: " . $wpdb->last_error;
		}

		$upload_dir = wp_upload_dir();
		$file_name = 'wpfilebase_files_export_' . date('Y-m-d_H-i-s') . '.csv';
		$file_path = $upload_dir['path'] . '/' . $file_name;
		$file_url = $upload_dir['url'] . '/' . $file_name;

		$fp = fopen($file_path, 'w');
		if ($fp === false) {
			return "Error: Unable to create CSV file.";
		}

		// Add the UTF-8 BOM to the output
		fputs($fp, $bom = (chr(0xEF) . chr(0xBB) . chr(0xBF)));

		// Output header row
		fputcsv($fp, array('ID', 'File Name'));

		// Output each file as a row
		foreach ($files as $file) {
			fputcsv($fp, array(
				$file['file_id'],
				$file['file_name']
			));
		}

		fclose($fp);

		return array(
			'success' => true,
			'message' => 'CSV file created successfully.',
			'file_url' => $file_url
		);
	}
}