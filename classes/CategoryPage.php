<?php
namespace LVAI\FileVersionManager;

#todo: add new categories function
#todo: add edit categories function
#todo: add delete categories function
#todo: link count number to files in admin page with a query

class CategoryPage {
	private $wpdb;
	private $table_name;
	private $wp_list_table;

	public function __construct() {
		global $wpdb;
		$this->wpdb = $wpdb;
		$this->table_name = $wpdb->prefix . Constants::CAT_TABLE_NAME;
	}

	public function init() {
		add_action( 'admin_menu', [ $this, 'add_category_page' ] );
		add_action( 'admin_init', [ $this, 'setup_list_table' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_styles' ] );
	}

	public function enqueue_styles() {
		if ( function_exists( 'get_current_screen' ) ) {
			$screen = get_current_screen();
			if ( $screen && $screen->id === 'files_page_fvm_categories' ) {
				wp_enqueue_style( 'file-version-manager-styles', plugin_dir_url( dirname( __FILE__ ) ) . 'css/categories.css' );
			}
		}
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

	public function setup_list_table() {
		$this->wp_list_table = new CategoryListTable( $this->wpdb );
	}

	public function display_admin_page() {
		ob_start();
		?>
		<div class="wrap">
			<h1>File Categories</h1>

			<?php
			$this->wp_list_table->prepare_items();
			?>

			<form class="search-form wp-clearfix" method="get">
				<?php $this->wp_list_table->search_box( 'Search Categories', 'search' ); ?>
			</form>

			<div id="col-container" class="wp-clearfix">
				<div id="col-left">
					<div class="col-wrap">
						<div class="form-wrap">
							<h2>Add New Category</h2>
							<form method="post" action="">

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
										$categories = $this->get_categories_hierarchical();
										echo "<!-- Debug: " . print_r( $categories, true ) . " -->";
										if ( ! empty( $categories ) ) {
											$this->display_category_options( $categories );
										} else {
											echo "<!-- No categories found in get_categories_hierarchical() -->";
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
							$this->wp_list_table->display_bulk_action_result();
							$this->wp_list_table->display();
							?>
						</form>
					</div>
				</div>
			</div>
		</div>
		<?php
		ob_end_flush();
	}

	private function get_categories_hierarchical( $parent_id = 0 ) {
		$query = $this->wpdb->prepare(
			"SELECT id, cat_name, cat_parent_id FROM {$this->table_name} WHERE cat_parent_id = %d ORDER BY cat_name ASC",
			$parent_id
		);
		$categories = $this->wpdb->get_results( $query );

		if ( $this->wpdb->last_error ) {
			return [];
		}

		foreach ( $categories as $category ) {
			$category->children = $this->get_categories_hierarchical( $category->id );
		}

		return $categories;
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
}