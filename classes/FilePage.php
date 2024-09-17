<?php
namespace LVAI\FileVersionManager;

class FilePage {
	private $file_manager;
	private $wp_list_table;
	private $file_list_table;

	public function __construct( FileManager $file_manager ) {
		$this->file_manager = $file_manager;
	}

	public function init() {
		add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_styles' ] );
		add_action( 'load-toplevel_page_fvm_files', [ $this, 'setup_list_table' ] );
		add_action( 'load-toplevel_page_fvm_files', [ $this, 'handle_file_upload' ] );
		add_action( 'load-toplevel_page_fvm_files', [ $this, 'handle_file_deletion' ] );
		add_action( 'load-toplevel_page_fvm_files', [ $this, 'handle_file_update' ] );
		add_action( 'load-toplevel_page_fvm_files', [ $this, 'handle_bulk_actions' ] );
		// add_filter( 'set-screen-option', [ $this, 'set_screen_option' ], 10, 3 );
	}

	public function add_admin_menu() {
		add_menu_page(
			'File Version Manager',
			'Files',
			'manage_options',
			'fvm_files',
			array( $this, 'display_admin_page' ),
			'dashicons-media-default',
			11
		);

		add_submenu_page(
			'fvm_files',
			'All Files',
			'All Files',
			'manage_options',
			'fvm_files',
			array( $this, 'display_admin_page' )
		);
	}

	/**
	 * Enqueue scripts and styles for the admin page
	 * @return void
	 */
	public function enqueue_styles() {
		if ( function_exists( 'get_current_screen' ) ) {
			$screen = get_current_screen();
			if ( $screen && $screen->id === 'toplevel_page_fvm_files' ) {
				wp_enqueue_style( 'file-version-manager-styles', plugin_dir_url( dirname( __FILE__ ) ) . 'css/admin.css' );
			}
		}
	}

	public function setup_list_table() {
		$this->wp_list_table = new FileListTable( $this->file_manager );
	}

	/**
	 * Display admin page
	 * @return void
	 */
	public function display_admin_page() {

		$this->setup_list_table();

		ob_start();

		$this->wp_list_table->prepare_items();
		$this->handle_file_upload();
		$this->handle_file_deletion();
		$this->handle_file_update();

		?>

		<div class="wrap">
			<h1>File Version Manager</h1>
			<?php
			if ( isset( $_GET['update'] ) && isset( $_GET['message'] ) ) {
				$status = $_GET['update'] === 'success' ? 'updated' : 'error';
				$message = urldecode( $_GET['message'] );
				echo "<div class='notice $status is-dismissible'><p>$message</p></div>";
			}
			?>

			<div id="fvm-upload-container" class="fvm-upload-container">
				<form method="post" enctype="multipart/form-data" id="fvm-upload-form">
					<?php wp_nonce_field( 'fvm_file_upload', 'fvm_file_upload_nonce' ); ?>
					<div class="fvm-dropzone">
						<div class="upload-ui">
							<h2 class="fvm-upload-instructions">Drop files to upload</h2>
							<p class="fvm-upload-instructions">or</p>
							<p id="fvm-file-name" style="display: none;"></p>
							<input type="file" name="file" id="fvm-file-input" style="display: none;" required>
							<button type="button" id="fvm-select-file" class="browser button button-hero">Select File</button>
							<input type="submit" name="fvm_upload_file" id="fvm-upload-button" value="Upload File"
								class="button button-primary button-hero" style="display: none;">
						</div>
						<div class="post-upload-ui" id="post-upload-info">
							<p class="fvm-upload-instructions">
								Maximum upload file size: <?php echo size_format( wp_max_upload_size() ); ?>.

								<?php
								// Check if Big File Uploads plugin is active
								if ( class_exists( 'BigFileUploads' ) ) {
									$bfu_settings_url = admin_url( 'options-general.php?page=big_file_uploads' );
									echo ' <small><a href="' . esc_url( $bfu_settings_url ) . '" style="text-decoration:none;">' . esc_html__( 'Change', 'file-version-manager' ) . '</a></small>';
								}
								?>

							</p>
						</div>
					</div>
				</form>
			</div>

			<div id="edit-form-container" style="display:none;">
				<form id="edit-form" method="post" enctype="multipart/form-data">
					<!-- The content of the edit form will be dynamically inserted here -->
				</form>
			</div>

			<form method="get">
				<input type="hidden" name="page" value="fvm_files" />
				<?php
				$this->wp_list_table->search_box( 'Search Files', 'search' );
				?>
			</form>

			<form method="post">
				<?php
				wp_nonce_field( 'bulk-files' );
				$this->wp_list_table->display_bulk_action_result();
				$this->wp_list_table->display();
				?>
			</form>

			<?php
			// Add modals for each file
			foreach ( $this->wp_list_table->items as $item ) {
				echo $this->wp_list_table->get_edit_form_html( $item['id'], $item );
			}
			?>

			<script type="text/javascript">
				document.addEventListener('DOMContentLoaded', function () {
					const dropzone = document.querySelector('.fvm-dropzone');
					const fileInput = document.getElementById('fvm-file-input');
					const selectFileBtn = document.getElementById('fvm-select-file');
					const uploadForm = document.getElementById('fvm-upload-form');
					const uploadButton = document.getElementById('fvm-upload-button');
					const fileNameDisplay = document.getElementById('fvm-file-name');
					const uploadInstructions = document.querySelectorAll('.fvm-upload-instructions');
					const postUploadInfo = document.getElementById('post-upload-info');

					// Drag and drop functionality
					['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
						dropzone.addEventListener(eventName, preventDefaults, false);
					});

					function preventDefaults(e) {
						e.preventDefault();
						e.stopPropagation();
					}

					['dragenter', 'dragover'].forEach(eventName => {
						dropzone.addEventListener(eventName, highlight, false);
					});

					['dragleave', 'drop'].forEach(eventName => {
						dropzone.addEventListener(eventName, unhighlight, false);
					});

					function highlight() {
						dropzone.classList.add('highlight');
					}

					function unhighlight() {
						dropzone.classList.remove('highlight');
					}

					dropzone.addEventListener('drop', handleDrop, false);

					function handleDrop(e) {
						const dt = e.dataTransfer;
						const files = dt.files;
						handleFiles(files);

						// Set the file input value
						if (files.length > 0) {
							const dT = new DataTransfer();
							dT.items.add(files[0]);
							fileInput.files = dT.files;
						}
					}

					// Select file button functionality
					selectFileBtn.addEventListener('click', () => {
						fileInput.click();
					});

					fileInput.addEventListener('change', () => {
						handleFiles(fileInput.files);
					});

					function handleFiles(files) {
						if (files.length > 0) {
							const fileName = files[0].name;
							fileNameDisplay.textContent = fileName;
							fileNameDisplay.style.display = 'block';
							selectFileBtn.style.display = 'none';
							if (uploadButton) {
								uploadButton.style.display = 'inline-block';
							}
							// Hide upload instructions and post-upload info
							uploadInstructions.forEach(el => el.style.display = 'none');
							if (postUploadInfo) {
								postUploadInfo.style.display = 'none';
							}

							// Set the file input value
							const dT = new DataTransfer();
							dT.items.add(files[0]);
							fileInput.files = dT.files;
						}
					}

					// // Add form submission handler
					// uploadForm.addEventListener('submit', function (e) {
					// 	e.preventDefault();
					// 	if (fileInput.files.length > 0) {
					// 		this.submit();
					// 	} else {
					// 		alert('Please select a file to upload.');
					// 	}
					// });
				});
			</script>

			<script type="text/javascript">
				document.addEventListener('DOMContentLoaded', function () {
					document.querySelectorAll('.copy-shortcode').forEach(button => {
						button.addEventListener('click', function (e) {
							e.preventDefault();
							const shortcode = this.getAttribute('data-shortcode');
							copyToClipboard(shortcode, this);
						});
					});

					function copyToClipboard(text, button) {
						const textArea = document.createElement("textarea");
						textArea.value = text;
						document.body.appendChild(textArea);
						textArea.select();

						try {
							document.execCommand('copy');
							const originalText = button.textContent;
							button.textContent = 'Copied!';
							setTimeout(function () {
								button.textContent = originalText;
							}, 2000);
						} catch (err) {
							console.error('Unable to copy to clipboard', err);
						}

						document.body.removeChild(textArea);
					}

					document.querySelectorAll('.edit-file').forEach(link => {
						link.addEventListener('click', function (e) {
							e.preventDefault();
							const fileId = this.getAttribute('data-file-id');
							const modal = document.getElementById('edit-modal-' + fileId);
							if (modal) {
								modal.style.display = 'block';
							} else {
								console.error('Modal not found for file ID:', fileId);
							}
						});
					});

					document.querySelectorAll('.close, .cancel-edit').forEach(button => {
						button.addEventListener('click', function (e) {
							e.preventDefault();
							const modal = this.closest('.edit-modal');
							if (modal) {
								modal.style.display = 'none';
							}
						});
					});

					window.onclick = function (event) {
						if (event.target.classList.contains('edit-modal')) {
							event.target.style.display = 'none';
						}
					}


				});
			</script>
		</div>
		<?php
		ob_end_flush();
	}

	/**
	 * Get file icon class
	 * @param string $file_type
	 * @return string
	 */
	private function get_file_icon_class( $file_type ) {
		$icon_map = [ 
			'image' => 'dashicons-format-image',
			'audio' => 'dashicons-format-audio',
			'video' => 'dashicons-format-video',
			'application/pdf' => 'dashicons-pdf',
			'text' => 'dashicons-text',
			'application/msword' => 'dashicons-media-document',
			'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'dashicons-media-document',
			'application/vnd.ms-excel' => 'dashicons-spreadsheet',
			'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'dashicons-spreadsheet',
			'application/zip' => 'dashicons-media-archive',
		];

		$type_parts = explode( '/', $file_type );
		$general_type = $type_parts[0];

		if ( isset( $icon_map[ $file_type ] ) ) {
			return $icon_map[ $file_type ];
		} elseif ( isset( $icon_map[ $general_type ] ) ) {
			return $icon_map[ $general_type ];
		} else {
			return 'dashicons-media-default';
		}
	}

	/**
	 * Handle file upload, redirect with message
	 * 
	 * @return void
	 */
	public function handle_file_upload() {
		if ( isset( $_POST['fvm_upload_file'] ) && isset( $_FILES['file'] ) ) {
			check_admin_referer( 'fvm_file_upload', 'fvm_file_upload_nonce' );

			$upload_result = $this->file_manager->upload_file( $_FILES['file'] );

			if ( $upload_result ) {
				fvm_redirect_with_message( 'fvm_files', 'success', 'File uploaded successfully.' );
			} else {
				fvm_redirect_with_message( 'fvm_files', 'error', 'Error uploading file.' );
			}
		}
	}

	/**
	 * Handle file deletion, redirect with message
	 * 
	 * @return void
	 */
	public function handle_file_deletion() {
		if ( isset( $_GET['action'] ) && $_GET['action'] === 'delete' && isset( $_GET['file_id'] ) ) {
			error_log( 'Entering handle_file_deletion method' );

			if ( isset( $_GET['action'] ) && $_GET['action'] === 'delete' && isset( $_GET['file_id'] ) ) {
				error_log( 'Delete action detected for file ID: ' . $_GET['file_id'] );

				check_admin_referer( 'delete_file_' . $_GET['file_id'] );
				error_log( 'Nonce verified successfully' );

				$file_id = intval( $_GET['file_id'] );
				$delete_result = $this->file_manager->delete_file( $file_id );

				error_log( "Delete result for file ID $file_id: " . ( $delete_result ? 'success' : 'failure' ) );

				if ( $delete_result ) {
					fvm_redirect_with_message( 'fvm_files', 'success', 'File deleted successfully.' );
				} else {
					fvm_redirect_with_message( 'fvm_files', 'error', 'Error deleting file.' );
				}

			} else {
				error_log( 'No delete action detected in handle_file_deletion' );
			}

			error_log( 'Exiting handle_file_deletion method' );
		}
	}

	/**
	 * Handle file update, redirect with message
	 * 
	 * @return void
	 */
	public function handle_file_update() {
		if ( isset( $_POST['update_file'] ) && isset( $_POST['file_id'] ) ) {

			if ( isset( $_POST['update_file'] ) && isset( $_POST['file_id'] ) ) {

				check_admin_referer( 'edit_file_' . $_POST['file_id'], 'edit_file_nonce' );

				$file_id = intval( $_POST['file_id'] );
				$new_file = isset( $_FILES['new_file'] ) ? $_FILES['new_file'] : null;
				$version = isset( $_POST['version'] ) ? sanitize_text_field( $_POST['version'] ) : '';
				$file_display_name = isset( $_POST['file_display_name'] ) ? sanitize_text_field( $_POST['file_display_name'] ) : '';
				$file_category_id = isset( $_POST['file_category_id'] ) ? intval( $_POST['file_category_id'] ) : 0;

				// If auto-increment is enabled and a new file is uploaded, pass an empty version
				$auto_increment_version = get_option( 'fvm_auto_increment_version', 1 );
				if ( $auto_increment_version && $new_file && ! empty( $new_file['tmp_name'] ) ) {
					$version = '';
				}

				$update_result = $this->file_manager->update_file( $file_id, $new_file, $version, $file_display_name, $file_category_id );

				if ( $update_result ) {
					fvm_redirect_with_message( 'fvm_files', 'success', 'File updated successfully.' );
				} else {
					fvm_redirect_with_message( 'fvm_files', 'error', 'Error updating file.' );
				}
			} else {
				error_log( 'Step 8: No update action detected in handle_file_update' );
			}
		}
	}

	/**
	 * Handle bulk actions
	 * 
	 * @return void
	 */
	public function handle_bulk_actions() {
		$this->setup_list_table();

		$action = $this->wp_list_table->current_action();

		if ( $action && in_array( $action, [ 'delete', 'bulk-delete' ] ) ) {
			check_admin_referer( 'bulk-files' );

			$file_ids = isset( $_REQUEST['file'] ) ? (array) $_REQUEST['file'] : [];

			if ( ! empty( $file_ids ) ) {
				$deleted_count = 0;
				foreach ( $file_ids as $file_id ) {
					if ( $this->file_manager->delete_file( intval( $file_id ) ) ) {
						$deleted_count++;
					}
				}

				if ( $deleted_count > 0 ) {
					$message = sprintf(
						_n( '%s file deleted successfully.', '%s files deleted successfully.', $deleted_count, 'file-version-manager' ),
						number_format_i18n( $deleted_count )
					);
					fvm_redirect_with_message( 'fvm_files', 'success', $message );
				} else {
					fvm_redirect_with_message( 'fvm_files', 'error', 'No files were deleted.' );
				}
			}
		}
	}
}