<?php
namespace LVAI\FileVersionManager;

/**
 * This class handles the update IDs page in the WordPress admin.
 * It includes functionality to:
 * - Add a submenu page for updating IDs
 * - Render the update IDs page
 * - Handle the CSV upload and update process
 * - Clear the log file
 */
class UpdateIDsPage {
	private $update_ids;
	private $log = [];

	public function __construct( UpdateIDs $update_ids ) {
		$this->update_ids = $update_ids;
	}

	public function init() {
		add_action( 'admin_menu', [ $this, 'add_update_ids_page' ] );
		add_action( 'admin_post_fvm_update_ids', [ $this, 'handle_csv_upload' ] );
		add_action( 'admin_post_fvm_clear_log', [ $this, 'handle_clear_log' ] );
	}

	public function add_update_ids_page() {
		add_submenu_page(
			'file-version-manager',
			'Update IDs',
			'Update IDs',
			'manage_options',
			'file-version-manager-update-ids',
			[ $this, 'render_update_ids_page' ]
		);
	}

	/**
	 * Renders the update IDs page.
	 *
	 * @return void
	 */
	public function render_update_ids_page() {
		?>
		<div class="wrap">
			<h1>Update File IDs</h1>
			<?php
			if ( isset( $_GET['update'] ) ) {
				$message = urldecode( $_GET['message'] );
				$class = ( $_GET['update'] === 'success' ) ? 'notice-success' : 'notice-error';
				echo "<div class='notice $class is-dismissible'><p>$message</p></div>";
			}
			?>
			<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="fvm_update_ids">
				<?php wp_nonce_field( 'fvm_update_ids', 'fvm_update_ids_nonce' ); ?>
				<table class="form-table">
					<tr>
						<th scope="row"><label for="csv_file">CSV File</label></th>
						<td>
							<input type="file" name="csv_file" id="csv_file" accept=".csv" required>
							<p class="description">Upload a CSV file with 'ID' and 'File Name' columns.</p>
						</td>
					</tr>

					<?php
					// #todo: add this back in
					// <tr>
					// 	<th scope="row"><label for="remove_duplicates">Remove duplicates?</label></th>
					// 	<td>
					// 		<input type="checkbox" name="remove_duplicates" id="remove_duplicates" value="1">
					// 		<p class="description">Check to remove duplicate files.</p>
					// 	</td>
					// </tr> -->
					?>

				</table>
				<?php submit_button( 'Update IDs' ); ?>
			</form>
			<?php
			$log_file = WP_CONTENT_DIR . '/fvm_update_ids.log';
			if ( file_exists( $log_file ) ) {
				echo "<h2>Update Log</h2>";
				echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
				echo '<input type="hidden" name="action" value="fvm_clear_log">';
				wp_nonce_field( 'fvm_clear_log', 'fvm_clear_log_nonce' );
				submit_button( 'Clear Log', 'secondary', 'clear_log', false );
				echo '</form>';
				echo "<pre>" . esc_html( file_get_contents( $log_file ) ) . "</pre>";
			}
			?>
		</div>
		<?php
	}

	/**
	 * Handles the CSV file upload, processes it, and updates the file IDs accordingly.
	 *
	 * @return void
	 */
	public function handle_csv_upload() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Unauthorized access' );
		}

		check_admin_referer( 'fvm_update_ids', 'fvm_update_ids_nonce' );

		if ( ! isset( $_FILES['csv_file'] ) ) {
			$this->redirect_with_message( 'error', 'No file uploaded' );
			return;
		}

		$csv_file = $_FILES['csv_file']['tmp_name'];
		$result = $this->update_ids->process_csv( $csv_file );

		$this->write_log();

		if ( $result['success'] ) {
			$this->redirect_with_message( 'success', $result['message'] );
		} else {
			$this->redirect_with_message( 'error', $result['message'] );
		}
	}

	/**
	 * Redirects to the update IDs page with a message.
	 *
	 * @param string $status The status of the update.
	 * @param string $message The message to display.
	 * @return void
	 */
	private function redirect_with_message( $status, $message ) {
		wp_redirect( add_query_arg(
			[ 
				'page' => 'file-version-manager-update-ids',
				'update' => $status,
				'message' => urlencode( $message ),
			],
			admin_url( 'admin.php' )
		) );
		exit;
	}

	/**
	 * Writes the log to a file.
	 *
	 * @return void
	 */
	private function write_log() {
		$log_file = WP_CONTENT_DIR . '/fvm_update_ids.log';
		file_put_contents( $log_file, implode( "\n", $this->update_ids->get_log() ) . "\n\n", FILE_APPEND );
	}

	/**
	 * Handles the clear log action.
	 *
	 * @return void
	 */
	public function handle_clear_log() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Unauthorized access' );
		}

		check_admin_referer( 'fvm_clear_log', 'fvm_clear_log_nonce' );

		$log_file = WP_CONTENT_DIR . '/fvm_update_ids.log';
		if ( file_exists( $log_file ) ) {
			unlink( $log_file );
		}

		wp_redirect( add_query_arg(
			[ 
				'page' => 'file-version-manager-update-ids',
				'update' => 'success',
				'message' => urlencode( 'Log cleared successfully' ),
			],
			admin_url( 'admin.php' )
		) );
		exit;
	}
}