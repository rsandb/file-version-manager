<?php
namespace LVAI\FileVersionManager;

class SettingsPage {
	private $update_ids;

	public function __construct( UpdateIDs $update_ids ) {
		$this->update_ids = $update_ids;
	}

	public function init() {
		add_action( 'admin_menu', [ $this, 'add_settings_page' ] );
		add_action( 'admin_init', [ $this, 'register_settings' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_styles' ] );
		add_action( 'admin_notices', [ $this, 'display_settings_updated_notice' ] );

		add_action( 'wp_ajax_export_wpfilebase_files', [ $this, 'ajax_export_wpfilebase_files' ] );
		add_action( 'wp_ajax_get_export_progress', [ $this, 'ajax_get_export_progress' ] );
		add_action( 'admin_post_fvm_update_ids', [ $this, 'handle_csv_upload' ] );
		add_action( 'admin_post_fvm_clear_log', [ $this, 'handle_clear_log' ] );
	}

	public function add_settings_page() {
		add_submenu_page(
			'fvm_files',
			'File Version Manager Settings',
			'Settings',
			'manage_options',
			'fvm_settings',
			array( $this, 'render_settings_page' )
		);
	}

	public function register_settings() {
		register_setting( 'fvm_settings', 'fvm_custom_directory' );
		register_setting( 'fvm_settings', 'fvm_debug_logs' );
		register_setting( 'fvm_settings', 'fvm_auto_increment_version' );
	}

	public function enqueue_styles() {
		if ( function_exists( 'get_current_screen' ) ) {
			$screen = get_current_screen();
			if ( $screen && $screen->id === 'files_page_fvm_settings' ) {
				wp_enqueue_style( 'file-version-manager-styles', plugin_dir_url( dirname( __FILE__ ) ) . 'css/settings.css' );
			}
		}
	}

	private function display_notification( $status = null, $message = null ) {
		if ( $status === null && $message === null ) {
			if ( isset( $_GET['notification'] ) ) {
				$status = $_GET['notification'];
				$message = isset( $_GET['message'] ) ? urldecode( $_GET['message'] ) : '';
			} else {
				return; // No notification to display
			}
		}

		$class = ( $status === 'success' ) ? 'notice-success' : 'notice-error';
		echo "<div class='notice $class is-dismissible'><p>$message</p></div>";
	}

	private function redirect_with_notification( $status, $message ) {
		wp_redirect( add_query_arg(
			[ 
				'page' => 'fvm_settings',
				'tab' => 'wp-filebase-pro',
				'notification' => $status,
				'message' => urlencode( $message ),
			],
			admin_url( 'admin.php' )
		) );
		exit;
	}

	public function display_settings_updated_notice() {
		// Check if we're on the correct settings page
		$screen = get_current_screen();
		if ( $screen && $screen->id === 'files_page_fvm_settings' ) {
			// Check if settings were just updated
			if ( isset( $_GET['settings-updated'] ) && $_GET['settings-updated'] == 'true' ) {
				$this->display_notification( 'success', __( 'Settings saved successfully.', 'file-version-manager' ) );
			}
		}
	}

	public function render_settings_page() {
		$active_tab = isset( $_GET['tab'] ) ? $_GET['tab'] : 'settings';
		?>
		<div class="wrap">
			<h1>File Version Manager</h1>
			<?php $this->display_notification(); ?>
			<div class="fvm-settings-tabs-container">
				<div class="fvm-settings-tabs">
					<a class="fvm-settings-tab <?php echo $active_tab === 'settings' ? 'active' : ''; ?>"
						href="?page=fvm_settings&tab=settings">Settings</a>
					<a class="fvm-settings-tab <?php echo $active_tab === 'wp-filebase-pro' ? 'active' : ''; ?>"
						href="?page=fvm_settings&tab=wp-filebase-pro">WP-Filebase Pro</a>
				</div>
			</div>
			<div class="fvm_settings-container">
				<?php
				if ( $active_tab === 'settings' ) {
					$this->render_settings_tab();
				} elseif ( $active_tab === 'wp-filebase-pro' ) {
					$this->render_wp_filebase_pro_tab();
				}
				?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render the settings tab
	 * 
	 * @return void
	 */
	private function render_settings_tab() {
		?>
		<form method="post" action="options.php">
			<?php settings_fields( 'fvm_settings' ); ?>
			<?php do_settings_sections( 'fvm_settings' ); ?>
			<div class="fvm_settings-section">
				<h2>General Settings</h2>
				<div class="fvm_settings-section-content">
					<div class="fvm_field-group">
						<h3>Custom Upload Folder</h3>
						<div class="fvm_input-group">
							<span>/wp-content/downloads/</span>
							<input type="text" name="fvm_custom_directory"
								value="<?php echo esc_attr( get_option( 'fvm_custom_directory' ) ); ?>" class="regular-text" />
						</div>
						<small class="description">Enter the name of the folder within the WordPress uploads directory.
							Leave
							blank to use the default 'file-version-manager' folder.</small>
					</div>
					<div class="fvm_field-group">
						<div class="fvm_input-group">
							<input type="checkbox" name="fvm_auto_increment_version" value="1" <?php checked( get_option( 'fvm_auto_increment_version', 1 ), 1 ); ?> />
							<span>Enable Auto-Increment Version</span>
						</div>
						<small class="description">Enable auto-increment version when files are replaced.</small>
					</div>
				</div>
			</div>

			<div class="fvm_settings-section">
				<h2>Developer Settings</h2>
				<div class="fvm_settings-section-content">
					<div class="fvm_field-group">
						<div class="fvm_input-group">
							<input type="checkbox" name="fvm_debug_logs" value="1" <?php checked( get_option( 'fvm_debug_logs' ), 1 ); ?> disabled />
							<span>Enable debug logs</span>
						</div>
						<small class="description">Enable debug logs. (Currently not working)</small>
					</div>
				</div>
			</div>
			<?php submit_button(); ?>
		</form>
		<?php
	}

	/**
	 * Render the WP-Filebase Pro settings tab
	 * 
	 * @return void
	 */
	private function render_wp_filebase_pro_tab() {
		global $wpdb;
		$files_table = $wpdb->prefix . 'wpfb_files';
		$file_count = $wpdb->get_var( "SELECT COUNT(*) FROM $files_table" );

		?>
		<div class="fvm_settings-section">
			<h2>Export WP-Filebase Pro Database</h2>
			<div class="fvm_settings-section-content">
				<div class="fvm_field-group">
					<div class="fvm_input-group">
						<div id="export-progress">
							<progress id="export-progress-bar" value="0" max="100"></progress>
							<span id="export-status"></span>
						</div>
					</div>
					<small class="description">There are currently <?php echo esc_html( $file_count ); ?> entries in the
						WP-Filebase
						files table.</small>
					<p class="submit">
						<button id="export-wpfilebase" class="button button-secondary">Export as CSV</button>
						<span id="export-result"></span>
					</p>
				</div>
			</div>
		</div>

		<div class="fvm_settings-section">
			<h2>Update File IDs</h2>
			<div class="fvm_settings-section-content">
				<div class="fvm_field-group">
					<form method="post" enctype="multipart/form-data"
						action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<div class="fvm_input-group">
							<input type="hidden" name="action" value="fvm_update_ids">
							<?php wp_nonce_field( 'fvm_update_ids', 'fvm_update_ids_nonce' ); ?>
							<input type="file" name="csv_file" id="csv_file" accept=".csv" required>
						</div>
						<small class="description" style="margin-bottom: 20px;">Upload a CSV file with 'ID' and 'File Name'
							columns.</small>
						<?php submit_button( 'Update IDs' ); ?>
					</form>
				</div>

				<?php
				$log_file = WP_CONTENT_DIR . '/fvm_update_ids.log';
				if ( file_exists( $log_file ) ) {
					?>
					<div class="fvm_log-container">
						<pre><?php echo esc_html( file_get_contents( $log_file ) ); ?></pre>
					</div>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="fvm_clear_log">
						<?php wp_nonce_field( 'fvm_clear_log', 'fvm_clear_log_nonce' ); ?>
						<?php submit_button( 'Clear Log', 'secondary', 'clear_log', false ); ?>
					</form>
					<?php
				}
				?>
			</div>
		</div>

		<script>
			jQuery(document).ready(function ($) {
				$('#export-wpfilebase').on('click', function () {
					var $button = $(this);
					var $progress = $('#export-progress');
					var $progressBar = $('#export-progress-bar');
					var $status = $('#export-status');
					var $result = $('#export-result');

					$button.prop('disabled', true);
					$result.html('');
					$progress.show();

					$.ajax({
						url: ajaxurl,
						type: 'POST',
						data: {
							action: 'export_wpfilebase_files',
							nonce: '<?php echo wp_create_nonce( 'export_wpfilebase_files_nonce' ); ?>'
						},
						success: function (response) {
							if (response.success) {
								$button.hide();
								var $downloadButton = $('<a>', {
									text: 'Download CSV',
									href: response.data.file_url,
									class: 'button button-primary',
									target: '_blank'
								});
								$result.html($downloadButton);
							} else {
								$result.html('<div class="error"><p>' + response.data + '</p></div>');
								$button.prop('disabled', false);
							}
						},
						error: function () {
							$result.html('<div class="error"><p>An error occurred during the export process.</p></div>');
							$button.prop('disabled', false);
						}
					});

					// Start progress updates
					var progressInterval = setInterval(function () {
						$.ajax({
							url: ajaxurl,
							type: 'POST',
							data: {
								action: 'get_export_progress',
								nonce: '<?php echo wp_create_nonce( 'get_export_progress_nonce' ); ?>'
							},
							success: function (response) {
								if (response.success) {
									var progress = response.data.progress;
									var total = response.data.total;
									var percentage = Math.round((progress / total) * 100);
									$progressBar.val(percentage);
									$status.text(progress + ' / ' + total + ' files processed');

									if (progress >= total) {
										clearInterval(progressInterval);
									}
								}
							}
						});
					}, 100);
				});
			});
		</script>
		<?php
	}

	public function ajax_export_wpfilebase_files() {
		check_ajax_referer( 'export_wpfilebase_files_nonce', 'nonce' );

		// Start the export process
		$result = $this->export_wpfilebase_files_as_csv();

		if ( is_array( $result ) && isset( $result['success'] ) ) {
			wp_send_json_success( $result );
		} else {
			wp_send_json_error( $result );
		}
	}

	public function ajax_get_export_progress() {
		check_ajax_referer( 'get_export_progress_nonce', 'nonce' );

		$progress = get_transient( 'wpfilebase_export_progress' );
		$total = get_transient( 'wpfilebase_export_total' );

		if ( $progress !== false && $total !== false ) {
			wp_send_json_success( [ 
				'progress' => $progress,
				'total' => $total,
			] );
		} else {
			wp_send_json_error( 'Progress information not available' );
		}
	}

	function export_wpfilebase_files_as_csv() {
		global $wpdb;
		$files_table = $wpdb->prefix . 'wpfb_files';

		// Debug: Check if table exists
		$files_table_exists = $wpdb->get_var( "SHOW TABLES LIKE '$files_table'" ) == $files_table;

		if ( ! $files_table_exists ) {
			return "Error: WP-Filebase files table not found.";
		}

		// Count entries in the files table
		$file_count = $wpdb->get_var( "SELECT COUNT(*) FROM $files_table" );

		if ( $file_count == 0 ) {
			return "No files found in the WP-Filebase files table.";
		}

		set_transient( 'wpfilebase_export_total', $file_count, HOUR_IN_SECONDS );
		set_transient( 'wpfilebase_export_progress', 0, HOUR_IN_SECONDS );

		$upload_dir = wp_upload_dir();
		$custom_dir = $upload_dir['basedir'] . '/fvm_wpfb-csv';

		// Create the custom directory if it doesn't exist
		if ( ! file_exists( $custom_dir ) ) {
			wp_mkdir_p( $custom_dir );
		}

		$file_name = 'wpfilebase_files_export_' . date( 'Y-m-d_H-i-s' ) . '.csv';
		$file_path = $custom_dir . '/' . $file_name;
		$file_url = $upload_dir['baseurl'] . '/fvm_wpfb-csv/' . $file_name;

		$fp = fopen( $file_path, 'w' );
		if ( $fp === false ) {
			return "Error: Unable to create CSV file.";
		}

		// Add the UTF-8 BOM to the output
		fputs( $fp, $bom = ( chr( 0xEF ) . chr( 0xBB ) . chr( 0xBF ) ) );

		// Output header row
		fputcsv( $fp, array( 'ID', 'File Name', 'File Path' ) );

		// Process files in batches
		$batch_size = 100;
		$offset = 0;
		$processed = 0;

		while ( $processed < $file_count ) {
			$query = $wpdb->prepare( "
				SELECT file_id, file_name, file_path
				FROM $files_table
				ORDER BY file_id ASC
				LIMIT %d OFFSET %d
			", $batch_size, $offset );

			$files = $wpdb->get_results( $query, ARRAY_A );

			if ( ! $files ) {
				fclose( $fp );
				return "Error: Failed to retrieve files. MySQL error: " . $wpdb->last_error;
			}

			foreach ( $files as $file ) {
				fputcsv( $fp, array(
					$file['file_id'],
					$file['file_name'],
					$file['file_path'],
				) );
				$processed++;
			}

			set_transient( 'wpfilebase_export_progress', $processed, HOUR_IN_SECONDS );
			$offset += $batch_size;

			// Add an artificial delay (0.1 seconds per batch)
			usleep( 100000 );
		}

		fclose( $fp );

		delete_transient( 'wpfilebase_export_progress' );
		delete_transient( 'wpfilebase_export_total' );

		return array(
			'success' => true,
			'message' => 'CSV file created successfully.',
			'file_url' => $file_url,
		);
	}

	public function handle_csv_upload() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Unauthorized access' );
		}

		check_admin_referer( 'fvm_update_ids', 'fvm_update_ids_nonce' );

		if ( ! isset( $_FILES['csv_file'] ) ) {
			$this->redirect_with_notification( 'error', 'No file uploaded' );
			return;
		}

		$csv_file = $_FILES['csv_file']['tmp_name'];
		$result = $this->update_ids->process_csv( $csv_file );

		$this->write_log();

		if ( $result['success'] ) {
			$this->redirect_with_notification( 'success', $result['message'] );
		} else {
			$this->redirect_with_notification( 'error', $result['message'] );
		}
	}

	private function write_log() {
		$log_file = WP_CONTENT_DIR . '/fvm_update_ids.log';
		file_put_contents( $log_file, implode( "\n", $this->update_ids->get_log() ) . "\n\n", FILE_APPEND );
	}

	public function handle_clear_log() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Unauthorized access' );
		}

		check_admin_referer( 'fvm_clear_log', 'fvm_clear_log_nonce' );

		$log_file = WP_CONTENT_DIR . '/fvm_update_ids.log';
		if ( file_exists( $log_file ) ) {
			unlink( $log_file );
		}

		$this->redirect_with_notification( 'success', 'Log cleared successfully' );
	}
}