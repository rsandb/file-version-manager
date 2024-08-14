<?php
namespace LVAI\FileVersionManager;

class AdminPage {
	private $file_manager;
	private $wp_list_table;
	private $file_list_table;

	public function __construct( FileManager $file_manager ) {
		$this->file_manager = $file_manager;
	}

	public function init() {
		add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
		add_action( 'admin_init', [ $this, 'handle_file_upload' ] );
		add_action( 'admin_init', [ $this, 'handle_file_deletion' ] );
		add_action( 'admin_init', [ $this, 'handle_bulk_actions' ] );
		add_action( 'admin_init', [ $this, 'handle_file_update' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_styles' ] );
		add_action( 'load-toplevel_page_file-version-manager', [ $this, 'setup_list_table' ] );
		// add_filter( 'set-screen-option', [ $this, 'set_screen_option' ], 10, 3 );
	}

	public function add_admin_menu() {
		add_menu_page(
			'File Version Manager',
			'Files',
			'manage_options',
			'file-version-manager',
			array( $this, 'display_admin_page' ),
			'dashicons-media-default',
			11
		);

		add_submenu_page(
			'file-version-manager',
			'All Files',
			'All Files',
			'manage_options',
			'file-version-manager',
			array( $this, 'display_admin_page' )
		);
	}

	// public function add_screen_options() {
	// 	add_screen_option( 'per_page', array(
	// 		'label' => 'Files per page',
	// 		'default' => 20,
	// 		'option' => 'fvm_files_per_page',
	// 	) );
	// }

	// public function set_screen_option( $status, $option, $value ) {
	// 	if ( 'fvm_files_per_page' == $option ) {
	// 		return $value;
	// 	}
	// 	return $status;
	// }

	// public function modify_screen_options( $columns ) {
	// 	// Remove 'file_name' from the screen options
	// 	if ( isset( $columns['file_name'] ) ) {
	// 		unset( $columns['file_name'] );
	// 	}
	// 	return $columns;
	// }

	public function setup_list_table() {
		$this->wp_list_table = new FileListTable( $this->file_manager );
		add_screen_option( 'per_page', [ 
			'label' => 'Files per page',
			'default' => 20,
			'option' => 'fvm_files_per_page',
		] );
	}

	/**
	 * Enqueue scripts and styles for the admin page
	 * @return void
	 */
	public function enqueue_styles() {
		if ( function_exists( 'get_current_screen' ) ) {
			$screen = get_current_screen();
			if ( $screen && $screen->id === 'toplevel_page_file-version-manager' ) {
				wp_enqueue_style( 'file-version-manager-styles', plugin_dir_url( dirname( __FILE__ ) ) . 'css/admin.css' );
			}
		}
	}

	/**
	 * Display admin page
	 * @return void
	 */
	public function display_admin_page() {
		ob_start();

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

			<div style="padding: 16px; background: white; border: 1px solid #c3c4c7;">
				<h2 style="margin-top: 0;">Upload New File</h2>
				<form method="post" enctype="multipart/form-data">
					<?php wp_nonce_field( 'fvm_file_upload', 'fvm_file_upload_nonce' ); ?>
					<input type="file" name="file" required>
					<br>
					<input type="submit" name="fvm_upload_file" value="Upload File" class="button button-primary"
						style="margin-top: 20px;">
				</form>
			</div>

			<div id="edit-form-container" style="display:none;">
				<form id="edit-form" method="post" enctype="multipart/form-data">
					<!-- The content of the edit form will be dynamically inserted here -->
				</form>
			</div>

			<form method="post">
				<?php
				wp_nonce_field( 'bulk-files' );
				$this->wp_list_table->prepare_items();
				$this->wp_list_table->display_bulk_action_result();
				$this->wp_list_table->search_box( 'Search', 'search' );
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
							modal.style.display = 'block';
						});
					});

					document.querySelectorAll('.close, .cancel-edit').forEach(button => {
						button.addEventListener('click', function (e) {
							e.preventDefault();
							const modal = this.closest('.edit-modal');
							modal.style.display = 'none';
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
				$this->redirect_with_message( 'success', 'File uploaded successfully.' );
			} else {
				$this->redirect_with_message( 'error', 'Error uploading file.' );
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
					$this->redirect_with_message( 'success', 'File deleted successfully.' );
				} else {
					$this->redirect_with_message( 'error', 'Error deleting file.' );
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
			error_log( 'Step 1: Entering handle_file_update method' );
			error_log( 'Step 2: $_FILES: ' . print_r( $_FILES, true ) );
			error_log( 'Step 3: $_POST: ' . print_r( $_POST, true ) );

			if ( isset( $_POST['update_file'] ) && isset( $_POST['file_id'] ) ) {
				error_log( 'Step 4: Update file action detected' );

				check_admin_referer( 'edit_file_' . $_POST['file_id'], 'edit_file_nonce' );
				error_log( 'Step 5: Nonce verified successfully' );

				$file_id = intval( $_POST['file_id'] );
				$new_file = isset( $_FILES['new_file'] ) ? $_FILES['new_file'] : null;
				$version = isset( $_POST['version'] ) ? sanitize_text_field( $_POST['version'] ) : '';

				// If auto-increment is enabled and a new file is uploaded, pass an empty version
				$auto_increment_version = get_option( 'fvm_auto_increment_version', 1 );
				if ( $auto_increment_version && $new_file && ! empty( $new_file['tmp_name'] ) ) {
					$version = '';
				}

				error_log( 'Step 6: $new_file: ' . print_r( $new_file, true ) );

				$update_result = $this->file_manager->update_file( $file_id, $new_file, $version );

				error_log( 'Step 7: Update result for file ID ' . $file_id . ': ' . ( $update_result ? 'success' : 'failure' ) );

				if ( $update_result ) {
					$this->redirect_with_message( 'success', 'File updated successfully.' );
				} else {
					$this->redirect_with_message( 'error', 'Error updating file.' );
				}
			} else {
				error_log( 'Step 8: No update action detected in handle_file_update' );
			}

			error_log( 'Exiting handle_file_update method' );
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
			check_admin_referer( 'bulk-' . $this->wp_list_table->_args['plural'] );

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
					$this->redirect_with_message( 'success', $message );
				} else {
					$this->redirect_with_message( 'error', 'No files were deleted.' );
				}
			}
		}
	}

	/**
	 * Redirect with message
	 * 
	 * @param string $status
	 * @param string $message
	 * @return void
	 */
	private function redirect_with_message( $status, $message ) {
		$redirect_url = add_query_arg(
			[ 
				'page' => 'file-version-manager',
				'update' => $status,
				'message' => urlencode( $message ),
			],
			admin_url( 'admin.php' )
		);

		if ( ! headers_sent() ) {
			wp_redirect( $redirect_url );
			exit;
		} else {
			echo '<script type="text/javascript">';
			echo 'window.location.href="' . esc_js( $redirect_url ) . '";';
			echo '</script>';
			echo '<noscript>';
			echo '<meta http-equiv="refresh" content="0;url=' . esc_url( $redirect_url ) . '" />';
			echo '</noscript>';
			exit;
		}
	}
}