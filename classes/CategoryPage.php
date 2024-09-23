<?php
namespace FVM\FileVersionManager;

class CategoryPage {
	private $category_manager;
	private $category_list_table;
	private $page_hook;

	public function __construct( CategoryManager $category_manager ) {
		$this->category_manager = $category_manager;
	}

	public function init() {
		add_action( 'admin_menu', [ $this, 'add_category_page' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_styles' ] );
		add_action( 'load-files_page_fvm_categories', [ $this, 'setup_list_table' ] );
		add_action( 'admin_post_add_category', [ $this, 'handle_add_category' ] );
		add_action( 'admin_post_update_category', [ $this, 'handle_update_category' ] );
		add_action( 'admin_post_delete_category', [ $this, 'handle_delete_category' ] );
		add_action( 'load-files_page_fvm_categories', [ $this, 'handle_bulk_actions' ] );
	}

	public function add_category_page() {
		add_submenu_page(
			'fvm_files',
			'Categories',
			'Categories',
			'manage_options',
			'fvm_categories',
			array( $this, 'display_admin_page' ),
		);
	}

	public function enqueue_styles() {
		if ( function_exists( 'get_current_screen' ) ) {
			$screen = get_current_screen();
			if ( $screen && $screen->id === 'files_page_fvm_categories' ) {
				wp_enqueue_style( 'file-version-manager-styles', plugin_dir_url( dirname( __FILE__ ) ) . 'css/categories.css' );
			}
		}
	}

	public function setup_list_table() {
		$this->category_list_table = new CategoryListTable( $this->category_manager );
	}

	public function display_admin_page() {

		$this->setup_list_table();

		ob_start();

		$this->category_list_table->prepare_items();

		?>
		<div class="wrap">
			<h1>File Categories</h1>
			<?php
			if ( isset( $_GET['update'] ) && isset( $_GET['message'] ) ) {
				$status = $_GET['update'] === 'success' ? 'updated' : 'error';
				$message = urldecode( $_GET['message'] );
				echo "<div class='notice $status is-dismissible'><p>$message</p></div>";
			}
			?>

			<div id="edit-form-container" style="display:none;">
				<form id="edit-form" method="post" enctype="multipart/form-data">
					<!-- The content of the edit form will be dynamically inserted here -->
				</form>
			</div>

			<form class="search-form wp-clearfix" method="get">
				<input type="hidden" name="page" value="fvm_categories" />
				<?php $this->category_list_table->search_box( 'Search Categories', 'search' ); ?>
			</form>

			<div id="col-container" class="wp-clearfix">
				<div id="col-left">
					<div class="col-wrap">
						<div class="form-wrap">
							<h2>Add New Category</h2>
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
								<input type="hidden" name="action" value="add_category">
								<?php wp_nonce_field( 'add_category', 'add_category_nonce' ); ?>

								<div class="form-field form-required term-name-wrap">
									<label for="cat_name">Name</label>
									<input name="cat_name" type="text" id="cat_name" value="" size="40" class="regular-text"
										required>
									<p id="cat_name_description">The name of the category.</p>
								</div>

								<div class="form-field">
									<label for="cat_slug">Slug</label>
									<input name="cat_slug" type="text" id="cat_slug" value="" size="40" class="regular-text">
									<p id="cat_slug_description">The “slug” is the URL-friendly version of the name. It is
										usually all lowercase and contains only letters, numbers, and hyphens. Leave blank to
										auto-generate.</p>
								</div>

								<div class="form-field term-parent-wrap">
									<label for="cat_parent_id">Parent Category</label>
									<select name="cat_parent_id" id="cat_parent_id">
										<option value="0">None</option>
										<?php
										$categories = $this->category_manager->get_categories_hierarchical();
										if ( ! empty( $categories ) ) {
											$this->display_category_options( $categories );
										}
										?>
									</select>
									<p id="cat_parent_id_description">The parent category of the file. Select 'None' to create a
										top-level category.</p>
								</div>

								<div class="form-field">
									<label for="cat_description">Description</label>
									<textarea name="cat_description" id="cat_description" rows="5" cols="50"></textarea>
									<p id="cat_description_description">The description of the category.</p>
								</div>

								<p class="submit">
									<input type="submit" name="submit" id="submit" class="button button-primary"
										value="Add New Category">
								</p>
							</form>
						</div>
					</div>
				</div>
				<div id="col-right">
					<div class="col-wrap">

						<form method="post">
							<?php
							wp_nonce_field( 'bulk-categories' );
							$this->category_list_table->display_bulk_action_result();
							$this->category_list_table->display();
							?>
						</form>

						<?php
						// Add modals for each category
						foreach ( $this->category_list_table->items as $item ) {
							echo $this->category_list_table->get_edit_form_html( $item['id'], $item );
						}
						?>

					</div>
				</div>
			</div>
		</div>

		<script type="text/javascript">
			document.addEventListener('DOMContentLoaded', function () {
				// Edit category
				document.querySelectorAll('.edit-category').forEach(link => {
					link.addEventListener('click', function (e) {
						e.preventDefault();
						const categoryId = this.getAttribute('data-category-id');
						const modal = document.getElementById('edit-modal-' + categoryId);
						if (modal) {
							modal.style.display = 'block';
						} else {
							console.error('Modal not found for category ID:', categoryId);
						}
					});
				});

				// Close modal
				document.querySelectorAll('.close, .cancel-edit').forEach(button => {
					button.addEventListener('click', function (e) {
						e.preventDefault();
						const modal = this.closest('.edit-modal');
						if (modal) {
							modal.style.display = 'none';
						}
					});
				});

				// Close modal when clicking outside
				window.addEventListener('click', function (event) {
					if (event.target.classList.contains('edit-modal')) {
						event.target.style.display = 'none';
					}
				});
			});
		</script>
		<?php
	}

	private function display_category_options( $categories, $depth = 0 ) {
		foreach ( $categories as $category ) {
			echo '<option value="' . esc_attr( $category->id ) . '">'
				. str_repeat( '&nbsp;', $depth * 3 ) . esc_html( $category->cat_name )
				. '</option>';

			if ( ! empty( $category->children ) ) {
				$this->display_category_options( $category->children, $depth + 1 );
			}
		}
	}

	public function handle_add_category() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'You do not have sufficient permissions to access this page.' );
		}

		check_admin_referer( 'add_category', 'add_category_nonce' );

		$cat_name = isset( $_POST['cat_name'] ) ? sanitize_text_field( $_POST['cat_name'] ) : '';
		$cat_slug = isset( $_POST['cat_slug'] ) ? sanitize_title( $_POST['cat_slug'] ) : '';
		$cat_parent_id = isset( $_POST['cat_parent_id'] ) ? intval( $_POST['cat_parent_id'] ) : 0;
		$cat_description = isset( $_POST['cat_description'] ) ? sanitize_textarea_field( $_POST['cat_description'] ) : '';

		if ( empty( $cat_name ) ) {
			fvm_redirect_with_message( 'fvm_categories', 'error', 'Category name is required.' );
			exit;
		}

		$result = $this->category_manager->add_category( $cat_name, $cat_slug, $cat_parent_id, $cat_description );

		if ( $result === false ) {
			fvm_redirect_with_message( 'fvm_categories', 'error', 'Failed to add category.' );
		} else {
			fvm_redirect_with_message( 'fvm_categories', 'success', 'Category added successfully.' );
		}
	}

	public function handle_update_category() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'You do not have sufficient permissions to access this page.' );
		}

		check_admin_referer( 'edit_category_' . $_POST['category_id'], 'edit_category_nonce' );

		$category_id = intval( $_POST['category_id'] );
		$cat_name = sanitize_text_field( $_POST['cat_name'] );
		$cat_description = sanitize_textarea_field( $_POST['cat_description'] );
		$cat_parent_id = intval( $_POST['cat_parent_id'] );
		$cat_exclude_browser = isset( $_POST['cat_exclude_browser'] ) ? 1 : 0;

		$update_result = $this->category_manager->update_category( $category_id, $cat_name, $cat_description, $cat_parent_id, $cat_exclude_browser );

		if ( $update_result === false ) {
			fvm_redirect_with_message( 'fvm_categories', 'error', 'Failed to update category.' );
		} else {
			fvm_redirect_with_message( 'fvm_categories', 'success', 'Category updated successfully.' );
		}
	}

	public function handle_delete_category() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'You do not have sufficient permissions to access this page.' );
		}

		if ( ! isset( $_GET['category_id'] ) || ! isset( $_GET['_wpnonce'] ) ) {
			wp_die( 'Invalid request.' );
		}

		$category_id = intval( $_GET['category_id'] );

		if ( ! wp_verify_nonce( $_GET['_wpnonce'], 'delete_category_' . $category_id ) ) {
			wp_die( 'Security check failed.', 'Invalid Nonce', array( 'response' => 403 ) );
		}

		$delete_result = $this->category_manager->delete_category( $category_id );

		if ( $delete_result === false ) {
			fvm_redirect_with_message( 'fvm_categories', 'error', 'Failed to delete category.' );
		} else {
			fvm_redirect_with_message( 'fvm_categories', 'success', 'Category deleted successfully.' );
		}
	}

	public function handle_bulk_actions() {
		// if ( ! current_user_can( 'manage_options' ) ) {
		// 	wp_die( 'You do not have sufficient permissions to access this page.' );
		// }

		$this->setup_list_table();

		$action = $this->category_list_table->current_action();

		if ( $action && in_array( $action, [ 'delete', 'bulk-delete' ] ) ) {
			check_admin_referer( 'bulk-categories' );

			$category_ids = isset( $_POST['category'] ) ? (array) $_POST['category'] : [];

			if ( ! empty( $category_ids ) ) {
				$deleted_count = 0;
				foreach ( $category_ids as $category_id ) {
					if ( $this->category_manager->delete_category( intval( $category_id ) ) ) {
						$deleted_count++;
					}
				}

				if ( $deleted_count > 0 ) {
					$message = sprintf(
						_n( '%s category deleted successfully.', '%s categories deleted successfully.', $deleted_count, 'file-version-manager' ),
						number_format_i18n( $deleted_count )
					);
					fvm_redirect_with_message( 'fvm_categories', 'success', $message );
				} else {
					fvm_redirect_with_message( 'fvm_categories', 'error', 'No categories were deleted.' );
				}
			}
		}
	}
}